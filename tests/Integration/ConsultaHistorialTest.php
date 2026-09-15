<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/Consulta.php';
require_once __DIR__ . '/../../models/Tratamiento.php';

/**
 * Módulo 2 — Historia clínica.
 *
 * Cubre la autorización de los adjuntos (M2-04 / RN-204 / RN-G02) y la carga
 * del historial sin consultas N+1 (M2-10 / HU-08).
 */
class ConsultaHistorialTest extends TestCase
{
    private PDO $db;
    private Consulta $consultas;
    private Tratamiento $tratamientos;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->db->exec("CREATE TABLE usuarios (documento TEXT PRIMARY KEY, nombre_completo TEXT, id_rol INTEGER)");
        $this->db->exec("
            CREATE TABLE mascotas (
                id_mascota INTEGER PRIMARY KEY AUTOINCREMENT,
                doc_propietario TEXT,
                nombre TEXT,
                numero_historia_clinica TEXT NULL,
                estado INTEGER DEFAULT 1
            )
        ");
        $this->db->exec("
            CREATE TABLE consultas (
                id_consulta INTEGER PRIMARY KEY AUTOINCREMENT,
                id_cita INTEGER NULL,
                id_mascota INTEGER,
                doc_veterinario TEXT,
                fecha_hora TEXT,
                motivo_consulta TEXT NULL,
                anamnesis TEXT NULL,
                peso REAL NULL,
                temperatura REAL NULL,
                frecuencia_cardiaca INTEGER NULL,
                diagnostico TEXT,
                plan_tratamiento TEXT NULL
            )
        ");
        $this->db->exec("
            CREATE TABLE archivos_clinicos (
                id_archivo INTEGER PRIMARY KEY AUTOINCREMENT,
                id_consulta INTEGER,
                nombre_original TEXT,
                nombre_servidor TEXT,
                ruta_archivo TEXT,
                tipo_archivo TEXT,
                extension TEXT,
                tamano_bytes INTEGER,
                descripcion TEXT NULL
            )
        ");
        $this->db->exec("
            CREATE TABLE tratamientos (
                id_tratamiento INTEGER PRIMARY KEY AUTOINCREMENT,
                id_consulta INTEGER,
                medicamento TEXT,
                dosis TEXT,
                via_administracion TEXT,
                duracion TEXT,
                observaciones TEXT NULL
            )
        ");

        $this->db->exec("
            INSERT INTO usuarios (documento, nombre_completo, id_rol) VALUES
            ('111','Ana Propietaria',4), ('222','Beto Propietario',4), ('900','Vet Vega',2)
        ");
        $this->db->exec("INSERT INTO mascotas (id_mascota, doc_propietario, nombre) VALUES (1,'111','Firulais'), (2,'222','Michi')");

        $this->consultas = new Consulta($this->db);
        $this->tratamientos = new Tratamiento($this->db);
    }

    private function crearConsulta(int $idMascota, string $diagnostico = 'Sano'): int
    {
        $this->db->prepare(
            "INSERT INTO consultas (id_mascota, doc_veterinario, fecha_hora, diagnostico)
             VALUES (:m, '900', datetime('now'), :d)"
        )->execute([':m' => $idMascota, ':d' => $diagnostico]);

        return (int) $this->db->lastInsertId();
    }

    private function crearArchivo(int $idConsulta, string $nombre = 'rx.jpg'): int
    {
        $this->consultas->saveArchivo([
            'id_consulta' => $idConsulta,
            'nombre_original' => $nombre,
            'nombre_servidor' => 'CLI_' . $idConsulta . '_x.jpg',
            'ruta_archivo' => 'uploads/clinicos/CLI_' . $idConsulta . '_x.jpg',
            'tipo_archivo' => 'image/jpeg',
            'extension' => 'jpg',
            'tamano_bytes' => 1024,
            'descripcion' => 'Adjunto de consulta',
        ]);

        return (int) $this->db->lastInsertId();
    }

    // ── M2-04: autorización de adjuntos ─────────────────────────────────

    /**
     * El dato que hace posible decidir el permiso: el adjunto se resuelve
     * hasta el dueño de la mascota. Antes ver_archivo.php no llegaba a saberlo
     * porque recibía un nombre de archivo suelto.
     */
    public function testElAdjuntoSeResuelveHastaElDuenoDeLaMascota(): void
    {
        $consulta = $this->crearConsulta(1);
        $idArchivo = $this->crearArchivo($consulta);

        $archivo = $this->consultas->getArchivoConDueno($idArchivo);

        $this->assertNotNull($archivo);
        $this->assertSame('111', $archivo['doc_propietario']);
        $this->assertSame(1, (int) $archivo['id_mascota']);
        $this->assertSame('jpg', $archivo['extension']);
    }

    public function testUnAdjuntoDeOtraMascotaDevuelveOtroDueno(): void
    {
        $archivoAjeno = $this->crearArchivo($this->crearConsulta(2));

        $archivo = $this->consultas->getArchivoConDueno($archivoAjeno);

        $this->assertNotSame('111', $archivo['doc_propietario'], 'Ana no debe figurar como dueña del adjunto de Beto');
        $this->assertSame('222', $archivo['doc_propietario']);
    }

    public function testUnIdDeArchivoInexistenteDevuelveNull(): void
    {
        $this->assertNull($this->consultas->getArchivoConDueno(4242));
    }

    // ── M2-10: historial sin N+1 ────────────────────────────────────────

    public function testLosAdjuntosDeVariasConsultasVienenAgrupadosPorConsulta(): void
    {
        $c1 = $this->crearConsulta(1);
        $c2 = $this->crearConsulta(1);
        $this->crearArchivo($c1, 'rx1.jpg');
        $this->crearArchivo($c1, 'rx2.jpg');
        $this->crearArchivo($c2, 'eco.jpg');

        $porConsulta = $this->consultas->getArchivosDeConsultas([$c1, $c2]);

        $this->assertCount(2, $porConsulta[$c1]);
        $this->assertCount(1, $porConsulta[$c2]);
        $this->assertSame('eco.jpg', $porConsulta[$c2][0]['nombre_original']);
    }

    public function testLosTratamientosDeVariasConsultasVienenAgrupados(): void
    {
        $c1 = $this->crearConsulta(1);
        $c2 = $this->crearConsulta(1);

        foreach ([[$c1, 'Amoxicilina'], [$c1, 'Meloxicam'], [$c2, 'Ivermectina']] as [$c, $med]) {
            $this->tratamientos->insert([
                'id_consulta' => $c,
                'medicamento' => $med,
                'dosis' => '1 ml',
                'via_administracion' => 'Oral',
                'duracion' => '5 días',
                'observaciones' => '',
            ]);
        }

        $porConsulta = $this->tratamientos->findByConsultas([$c1, $c2]);

        $this->assertCount(2, $porConsulta[$c1]);
        $this->assertCount(1, $porConsulta[$c2]);
        $this->assertSame('Ivermectina', $porConsulta[$c2][0]['medicamento']);
    }

    /** Una consulta sin adjuntos no debe aparecer en el agrupado. */
    public function testUnaConsultaSinAdjuntosNoApareceEnElAgrupado(): void
    {
        $c1 = $this->crearConsulta(1);

        $this->assertSame([], $this->consultas->getArchivosDeConsultas([$c1]));
    }

    /** Sin ids no se lanza ninguna consulta con un IN vacío, que es SQL inválido. */
    public function testUnaListaVaciaNoRompe(): void
    {
        $this->assertSame([], $this->consultas->getArchivosDeConsultas([]));
        $this->assertSame([], $this->tratamientos->findByConsultas([]));
    }

    // ── RN-206: el historial es acumulativo y cronológico ───────────────

    public function testElHistorialLlegaConElMasRecientePrimero(): void
    {
        $viejo = $this->crearConsulta(1, 'Primera visita');
        $this->db->exec("UPDATE consultas SET fecha_hora = '2024-01-01 09:00:00' WHERE id_consulta = $viejo");
        $nuevo = $this->crearConsulta(1, 'Control');
        $this->db->exec("UPDATE consultas SET fecha_hora = '2025-06-01 09:00:00' WHERE id_consulta = $nuevo");

        $historial = $this->consultas->findByMascota(1);

        $this->assertCount(2, $historial);
        $this->assertSame('Control', $historial[0]['diagnostico']);
        $this->assertSame('Primera visita', $historial[1]['diagnostico']);
    }

    /** RN-G02: el historial de una mascota no arrastra consultas de otra. */
    public function testElHistorialNoMezclaMascotas(): void
    {
        $this->crearConsulta(1, 'De Firulais');
        $this->crearConsulta(2, 'De Michi');

        $historial = $this->consultas->findByMascota(1);

        $this->assertCount(1, $historial);
        $this->assertSame('De Firulais', $historial[0]['diagnostico']);
    }
}
