<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/Recordatorio.php';

/**
 * Selección y reintento de los recordatorios automáticos (HU-37).
 */
class RecordatorioTest extends TestCase
{
    private const HOY = '2026-09-22';

    private PDO $db;
    private Recordatorio $recordatorio;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec("CREATE TABLE usuarios (documento TEXT PRIMARY KEY, nombre_completo TEXT, email TEXT)");
        $this->db->exec("CREATE TABLE mascotas (id_mascota INTEGER PRIMARY KEY, nombre TEXT, doc_propietario TEXT, estado INTEGER DEFAULT 1)");
        $this->db->exec("CREATE TABLE vacunas (id_vacuna INTEGER PRIMARY KEY, id_mascota INTEGER, nombre_vacuna TEXT,
            fecha_aplicacion TEXT, fecha_proxima_dosis TEXT)");
        $this->db->exec("CREATE TABLE desparasitaciones (id_desparasitacion INTEGER PRIMARY KEY, id_mascota INTEGER, tipo TEXT,
            producto TEXT, fecha_aplicacion TEXT, fecha_proxima TEXT)");
        $this->db->exec("CREATE TABLE notificaciones (id_notificacion INTEGER PRIMARY KEY, doc_propietario TEXT, tipo_entidad TEXT,
            id_entidad INTEGER, destinatario_email TEXT, tipo_notificacion TEXT, asunto TEXT, mensaje TEXT, estado TEXT)");

        $this->db->exec("INSERT INTO usuarios VALUES ('P1', 'Carlos Ruiz', 'carlos@correo.com'), ('P2', 'Sin Correo', '')");
        $this->db->exec("INSERT INTO mascotas VALUES (1, 'Toby', 'P1', 1), (2, 'Luna', 'P1', 0), (3, 'Max', 'P2', 1)");

        $this->recordatorio = new Recordatorio($this->db);
    }

    private function idsDe(array $dosis): array
    {
        return array_map(fn($d) => $d['tipo_entidad'] . ':' . $d['id_entidad'], $dosis);
    }

    /** RE-37.1: todas las dosis de la ventana, cada una con su aviso. */
    public function testSeleccionaLasDosisDeLaVentanaConSuAviso(): void
    {
        $this->db->exec("INSERT INTO vacunas VALUES
            (1, 1, 'Rabia', '2025-09-25', '2026-09-25'),
            (2, 1, 'Parvovirus', '2025-09-23', '2026-09-23'),
            (3, 1, 'Moquillo', '2025-10-10', '2026-10-10')");
        $this->db->exec("INSERT INTO desparasitaciones VALUES (1, 1, 'interna', 'Drontal', '2026-06-22', '2026-09-22')");

        $dosis = $this->recordatorio->dosisPorRecordar(self::HOY);

        $this->assertSame(['vacuna:1', 'vacuna:2', 'desparasitacion:1'], $this->idsDe($dosis));
        $this->assertSame(
            [VentanaRecordatorio::PRIMER_AVISO, VentanaRecordatorio::ULTIMO_AVISO, VentanaRecordatorio::ULTIMO_AVISO],
            array_column($dosis, 'tipo_notificacion')
        );
        $this->assertSame('Desparasitación interna (Drontal)', $dosis[2]['nombre_item']);
    }

    /** RE-37.4 y RN-304: ni mascotas inactivas ni propietarios sin correo. */
    public function testExcluyeMascotasInactivasYPropietariosSinCorreo(): void
    {
        $this->db->exec("INSERT INTO vacunas VALUES
            (1, 2, 'Rabia', '2025-09-25', '2026-09-25'),
            (2, 3, 'Rabia', '2025-09-25', '2026-09-25')");
        $this->db->exec("INSERT INTO desparasitaciones VALUES (1, 2, 'externa', 'Bravecto', '2026-06-25', '2026-09-25')");

        $this->assertSame([], $this->recordatorio->dosisPorRecordar(self::HOY));
    }

    /** RE-37.4: una dosis posterior del mismo tipo deja sin efecto la fecha vieja. */
    public function testExcluyeLasDosisYaRenovadas(): void
    {
        $this->db->exec("INSERT INTO vacunas VALUES
            (1, 1, 'Rabia', '2025-09-25', '2026-09-25'),
            (2, 1, 'Rabia', '2026-09-20', '2027-09-20'),
            (3, 1, 'Parvovirus', '2025-09-25', '2026-09-25')");
        $this->db->exec("INSERT INTO desparasitaciones VALUES
            (1, 1, 'interna', 'Drontal', '2026-06-25', '2026-09-25'),
            (2, 1, 'interna', 'Drontal', '2026-09-20', '2026-12-20'),
            (3, 1, 'externa', 'Bravecto', '2026-06-25', '2026-09-25')");

        $this->assertSame(['vacuna:3', 'desparasitacion:3'], $this->idsDe($this->recordatorio->dosisPorRecordar(self::HOY)));
    }

    private function registrar(bool $enviado): void
    {
        $this->recordatorio->registrar([
            'doc_propietario' => 'P1', 'tipo_entidad' => 'vacuna', 'id_entidad' => 1,
            'email' => 'carlos@correo.com', 'tipo_notificacion' => VentanaRecordatorio::PRIMER_AVISO,
            'asunto' => 'Recordatorio', 'mensaje' => 'Cuerpo', 'enviado' => $enviado,
        ]);
    }

    private function puedeEnviar(): bool
    {
        return $this->recordatorio->puedeEnviar('vacuna', 1, VentanaRecordatorio::PRIMER_AVISO);
    }

    public function testUnAvisoEnviadoNoSeRepite(): void
    {
        $this->assertTrue($this->puedeEnviar());
        $this->registrar(true);
        $this->assertFalse($this->puedeEnviar());
        $this->assertTrue($this->recordatorio->puedeEnviar('vacuna', 1, VentanaRecordatorio::ULTIMO_AVISO));
    }

    /** RE-37.2: un fallo se reintenta, hasta el máximo de intentos. */
    public function testUnEnvioFallidoSeReintentaHastaElMaximo(): void
    {
        for ($i = 1; $i < VentanaRecordatorio::MAX_INTENTOS; $i++) {
            $this->registrar(false);
            $this->assertTrue($this->puedeEnviar(), "Tras $i fallo(s) debe reintentarse");
        }
        $this->registrar(false);
        $this->assertFalse($this->puedeEnviar());

        $estados = $this->db->query("SELECT estado FROM notificaciones")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(array_fill(0, VentanaRecordatorio::MAX_INTENTOS, 'error'), $estados);
    }
}
