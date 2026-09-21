<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/Panel.php';

/**
 * Consultas de los paneles de inicio (HU-18, HU-20, HU-57): cada panel ve
 * solo lo que le corresponde y los conteos respetan los estados de la cita.
 */
class PanelTest extends TestCase
{
    private PDO $db;
    private Panel $panel;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec("CREATE TABLE usuarios (documento TEXT PRIMARY KEY, nombre_completo TEXT, id_rol INTEGER, estado INTEGER DEFAULT 1)");
        $this->db->exec("CREATE TABLE especies (id_especie INTEGER PRIMARY KEY, nombre_especie TEXT)");
        $this->db->exec("CREATE TABLE tipos_cita (id_tipo_cita INTEGER PRIMARY KEY, nombre_tipo TEXT)");
        $this->db->exec("CREATE TABLE mascotas (id_mascota INTEGER PRIMARY KEY, nombre TEXT, doc_propietario TEXT, id_especie INTEGER, estado INTEGER DEFAULT 1)");
        $this->db->exec("CREATE TABLE citas (id_cita INTEGER PRIMARY KEY, id_mascota INTEGER, doc_veterinario TEXT, fecha TEXT, hora TEXT,
            hora_fin TEXT, duracion_minutos INTEGER DEFAULT 30, estado TEXT, motivo TEXT, id_tipo_cita INTEGER)");
        $this->db->exec("CREATE TABLE consultas (id_consulta INTEGER PRIMARY KEY, id_mascota INTEGER, doc_veterinario TEXT, fecha_hora TEXT)");
        $this->db->exec("CREATE TABLE vacunas (id_vacuna INTEGER PRIMARY KEY, id_mascota INTEGER, nombre_vacuna TEXT, fecha_proxima_dosis TEXT)");
        $this->db->exec("CREATE TABLE desparasitaciones (id_desparasitacion INTEGER PRIMARY KEY, id_mascota INTEGER, producto TEXT, fecha_proxima TEXT)");

        $this->db->exec("INSERT INTO usuarios VALUES ('V1', 'Ana Gómez', 2, 1), ('V2', 'Luis Rojas', 2, 1), ('V3', 'Retirado', 2, 0), ('P1', 'Carlos Ruiz', 4, 1)");
        $this->db->exec("INSERT INTO especies VALUES (1, 'Perro')");
        $this->db->exec("INSERT INTO tipos_cita VALUES (1, 'Vacunación')");
        $this->db->exec("INSERT INTO mascotas VALUES (1, 'Toby', 'P1', 1, 1), (2, 'Luna', 'P1', 1, 1), (3, 'Max', 'P1', 1, 1), (4, 'Viejo', 'P1', 1, 0)");

        $this->panel = new Panel($this->db);
    }

    private function cita(int $mascota, string $vet, string $fecha, string $hora, string $estado, ?int $tipo = null): void
    {
        $stmt = $this->db->prepare("INSERT INTO citas (id_mascota, doc_veterinario, fecha, hora, estado, id_tipo_cita) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$mascota, $vet, $fecha, $hora, $estado, $tipo]);
    }

    /** HU-18: el veterinario ve solo sus citas del día, en orden. */
    public function testLasCitasDelDiaSeFiltranPorVeterinario(): void
    {
        $this->cita(1, 'V1', '2026-09-15', '11:00:00', 'confirmada', 1);
        $this->cita(2, 'V1', '2026-09-15', '09:00:00', 'pendiente');
        $this->cita(3, 'V2', '2026-09-15', '10:00:00', 'pendiente');
        $this->cita(1, 'V1', '2026-09-16', '08:00:00', 'pendiente');

        $delVet = $this->panel->citasDelDia('2026-09-15', 'V1');
        $this->assertSame(['Luna', 'Toby'], array_column($delVet, 'mascota'));
        $this->assertSame(['Consulta', 'Vacunación'], array_column($delVet, 'tipo'));

        $this->assertCount(3, $this->panel->citasDelDia('2026-09-15'));
    }

    public function testLasAtencionesAbiertasSonEnCursoOSinCerrar(): void
    {
        $this->cita(1, 'V1', '2026-09-10', '09:00:00', 'sin_cerrar');
        $this->cita(2, 'V1', '2026-09-15', '09:00:00', 'en_curso');
        $this->cita(3, 'V1', '2026-09-15', '10:00:00', 'completada');
        $this->cita(3, 'V2', '2026-09-15', '10:00:00', 'en_curso');

        $this->assertSame(['sin_cerrar', 'en_curso'], array_column($this->panel->atencionesAbiertas('V1'), 'estado'));
        $this->assertCount(3, $this->panel->atencionesAbiertas());
    }

    /** HU-20: solo dosis próximas de pacientes activos del veterinario. */
    public function testLosRecordatoriosSonDeSusPacientes(): void
    {
        $this->cita(1, 'V1', '2026-09-01', '09:00:00', 'completada');
        $this->db->exec("INSERT INTO consultas VALUES (1, 2, 'V1', '2026-08-01 10:00:00')");
        $this->cita(3, 'V2', '2026-09-01', '09:00:00', 'completada');
        $this->cita(4, 'V1', '2026-09-01', '10:00:00', 'completada');

        $this->db->exec("INSERT INTO vacunas VALUES
            (1, 1, 'Rabia', '2026-09-17'),
            (2, 3, 'Parvovirus', '2026-09-16'),
            (3, 4, 'Moquillo', '2026-09-16'),
            (4, 1, 'Leptospira', '2026-09-30')");
        $this->db->exec("INSERT INTO desparasitaciones VALUES (1, 2, 'Bravecto', '2026-09-15')");

        $r = $this->panel->recordatoriosDeSusPacientes('V1', '2026-09-15', '2026-09-22');

        $this->assertSame(['Luna', 'Toby'], array_column($r, 'mascota'));
        $this->assertSame(['desparasitacion', 'vacuna'], array_column($r, 'tipo'));
    }

    /** RN-409: una cita pasada sin atender ni marcar queda como pendiente. */
    public function testCuentaLasCitasPasadasSinMarcar(): void
    {
        $this->cita(1, 'V1', '2026-09-14', '09:00:00', 'confirmada');
        $this->cita(2, 'V1', '2026-09-15', '08:00:00', 'pendiente');
        $this->cita(3, 'V1', '2026-09-15', '15:00:00', 'pendiente');
        $this->cita(1, 'V1', '2026-09-15', '07:00:00', 'completada');
        $this->cita(2, 'V1', '2026-07-01', '09:00:00', 'pendiente');

        $this->assertSame(2, $this->panel->contarSinMarcar('2026-08-16', '2026-09-15', '10:00:00'));
    }

    public function testLaCargaIncluyeVeterinariosSinCitasYNoCuentaCanceladas(): void
    {
        $this->cita(1, 'V1', '2026-09-15', '09:00:00', 'completada');
        $this->cita(2, 'V1', '2026-09-15', '10:00:00', 'confirmada');
        $this->cita(3, 'V1', '2026-09-15', '11:00:00', 'cancelada');

        $carga = $this->panel->cargaPorVeterinario('2026-09-15');

        $this->assertSame(['Ana Gómez', 'Luis Rojas'], array_column($carga, 'veterinario'));
        $this->assertEquals(2, $carga[0]['total']);
        $this->assertEquals(1, $carga[0]['atendidas']);
        $this->assertEquals(0, $carga[1]['total']);
    }

    public function testLaTendenciaAgrupaAtendidasYNoAsistidasPorMes(): void
    {
        $this->cita(1, 'V1', '2026-08-03', '09:00:00', 'completada');
        $this->cita(2, 'V1', '2026-08-20', '09:00:00', 'no_asistio');
        $this->cita(3, 'V1', '2026-09-02', '09:00:00', 'completada');
        $this->cita(3, 'V1', '2026-09-03', '09:00:00', 'cancelada');

        $filas = $this->panel->tendenciaMensual('2026-04-01', '2026-09-15');

        $this->assertSame(['2026-08', '2026-09'], array_column($filas, 'mes'));
        $this->assertEquals([1, 1], [$filas[0]['atendidas'], $filas[0]['no_asistidas']]);
        $this->assertEquals([1, 0], [$filas[1]['atendidas'], $filas[1]['no_asistidas']]);
    }

    public function testLasConsultasSeCuentanEnUnRangoSemiabierto(): void
    {
        $this->db->exec("INSERT INTO consultas VALUES
            (1, 1, 'V1', '2026-09-01 00:00:00'),
            (2, 1, 'V1', '2026-09-15 23:59:00'),
            (3, 1, 'V1', '2026-09-16 00:00:00')");

        $this->assertSame(2, $this->panel->contarConsultas('2026-09-01 00:00:00', '2026-09-16 00:00:00'));
    }
}
