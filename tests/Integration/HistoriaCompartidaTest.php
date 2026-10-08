<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/Consulta.php';
require_once __DIR__ . '/../../models/Vacuna.php';
require_once __DIR__ . '/../../models/Desparasitacion.php';
require_once __DIR__ . '/../../controllers/ConsultaController.php';

/**
 * HU-2.10 / RF-2.6 — Historia compartida entre clínicas (C4).
 *
 * Luna es de Fabio y está vinculada a Norte (A) y a Sur (B). A registra una
 * consulta con tratamiento y adjunto, una vacuna y una desparasitación.
 *
 * - RN-113 / RE-2.10.1: B ve siempre las vacunas y desparasitaciones de A,
 *   con la clínica que las aplicó.
 * - RE-2.10.2: B ve las consultas, tratamientos y archivos de A solo si Fabio
 *   autorizó a B; al revocar, dejan de verse. Sin autorización ni siquiera se
 *   revela que existen (decisión del usuario, C4).
 * - RN-112 / RE-2.10.4: B no modifica nada de A (403 y auditoría).
 * - RE-2.3.3: ver_archivo.php aplica la misma regla (ArchivoClinico::paraDescargar).
 */
final class HistoriaCompartidaTest extends TestCase
{
    private const LUNA = 1;

    private PDO $db;
    private int $consultaDeA;
    private int $tratamientoDeA;
    private int $archivoDeA;
    private int $vacunaDeA;
    private int $desparasitacionDeA;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::vincularLunaASur($this->db);
        $this->registrarHistoriaDeNorte();
        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
    }

    public function testBVeLasVacunasYDesparasitacionesDeAConLaClinicaQueLasAplico(): void
    {
        $vacunas = (new Vacuna($this->db))->findByMascota(self::LUNA);
        $desparasitaciones = (new Desparasitacion($this->db))->findByMascota(self::LUNA);

        $this->assertCount(1, $vacunas);
        $this->assertSame('Clínica Norte', $vacunas[0]['clinica_nombre']);
        $this->assertFalse($vacunas[0]['es_propia']);
        $this->assertSame('Beto Norte', $vacunas[0]['veterinario']);
        $this->assertCount(1, $desparasitaciones);
        $this->assertSame('Clínica Norte', $desparasitaciones[0]['clinica_nombre']);
        $this->assertFalse($desparasitaciones[0]['es_propia']);
    }

    public function testSinAutorizacionBNoVeNiSabeDeLasConsultasDeA(): void
    {
        $propia = $this->registrarConsultaEnSur();

        $consultas = (new Consulta($this->db))->historialDeMascota(self::LUNA);

        $this->assertSame([$propia], array_map('intval', array_column($consultas, 'id_consulta')));
        $this->assertSame([], (new Tratamiento($this->db))->findByConsultas([$this->consultaDeA]));
        $this->assertSame([], (new ArchivoClinico($this->db))->deConsultas([$this->consultaDeA]));
        $this->assertAccesoDenegado(fn () => (new ArchivoClinico($this->db))->paraDescargar($this->archivoDeA));
        $this->assertSame(1, $this->auditoriasDenegadas(DosClinicas::SUR));
    }

    /** RE-2.10.2 por petición directa: la respuesta no trae ni menciona las consultas de A. */
    public function testElHistorialPorPeticionDirectaNoRevelaLasConsultasDeA(): void
    {
        $respuesta = $this->historialPorControlador();

        $this->assertTrue($respuesta['success']);
        $this->assertSame([], $respuesta['consultas']);
        $this->assertSame(['success', 'mascota', 'consultas', 'vacunas', 'desparasitaciones'], array_keys($respuesta));
        $this->assertStringNotContainsString('Otitis', json_encode($respuesta, JSON_UNESCAPED_UNICODE));
    }

    public function testConAutorizacionBLasVeMarcadasYAlRevocarDejaDeVerlas(): void
    {
        $this->autorizarASur(1);

        $consultas = (new Consulta($this->db))->historialDeMascota(self::LUNA);

        $this->assertCount(1, $consultas);
        $this->assertSame('Otitis', $consultas[0]['diagnostico']);
        $this->assertSame('Clínica Norte', $consultas[0]['clinica_nombre']);
        $this->assertFalse($consultas[0]['es_propia']);
        $this->assertCount(1, $consultas[0]['tratamientos']);
        $this->assertCount(1, $consultas[0]['archivos']);
        $archivo = (new ArchivoClinico($this->db))->paraDescargar($this->archivoDeA);
        $this->assertSame('CLI_A.jpg', $archivo['nombre_servidor']);

        $this->autorizarASur(0);

        $this->assertSame([], (new Consulta($this->db))->historialDeMascota(self::LUNA));
        $this->assertSame([], (new Tratamiento($this->db))->findByConsultas([$this->consultaDeA]));
        $this->assertAccesoDenegado(fn () => (new ArchivoClinico($this->db))->paraDescargar($this->archivoDeA));
    }

    /** La autorización es de un vínculo activo: si Fabio deja Sur, la autorización ya no cuenta. */
    public function testUnaAutorizacionDeUnVinculoInactivoNoCuenta(): void
    {
        $this->autorizarASur(1);
        $this->db->exec("UPDATE propietario_clinica SET estado = 'inactivo' WHERE id_propietario = 6 AND id_clinica = 2");

        $this->assertSame([], (new Consulta($this->db))->historialDeMascota(self::LUNA));
    }

    /** RN-112 / RE-2.10.4: aun con autorización de lectura, B no modifica nada de A. */
    public function testBNoModificaNingunRegistroDeAYQuedaEnAuditoria(): void
    {
        $this->autorizarASur(1);

        $this->assertAccesoDenegado(fn () => (new Consulta($this->db))->paraModificar($this->consultaDeA));
        $this->assertAccesoDenegado(fn () => (new Tratamiento($this->db))->paraModificar($this->tratamientoDeA));
        $this->assertAccesoDenegado(fn () => (new ArchivoClinico($this->db))->paraModificar($this->archivoDeA));
        $this->assertAccesoDenegado(fn () => (new Vacuna($this->db))->paraModificar($this->vacunaDeA));
        $this->assertAccesoDenegado(fn () => (new Desparasitacion($this->db))->paraModificar($this->desparasitacionDeA));

        $tablas = $this->db->query("SELECT tabla_afectada FROM auditoria_sistema WHERE id_clinica = 2 AND id_usuario = 4 ORDER BY id_auditoria")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['consultas', 'tratamientos', 'archivos_clinicos', 'vacunas', 'desparasitaciones'], $tablas);

        $this->comoVeterinario(DosClinicas::NORTE, DosClinicas::VET_NORTE);
        $this->assertSame($this->consultaDeA, (int) (new Consulta($this->db))->paraModificar($this->consultaDeA)['id_consulta']);
        $this->assertSame($this->vacunaDeA, (int) (new Vacuna($this->db))->paraModificar($this->vacunaDeA)['id_vacuna']);
    }

    /** B tampoco agrega tratamientos ni adjuntos a una consulta de A. */
    public function testBNoAgregaTratamientosNiAdjuntosAUnaConsultaDeA(): void
    {
        $this->autorizarASur(1);
        $tratamiento = ['medicamento' => 'X', 'dosis' => '1', 'via_administracion' => 'Oral', 'duracion' => '1 día', 'fecha_inicio' => '2026-10-07'];

        $this->assertAccesoDenegado(fn () => (new Tratamiento($this->db))->insertarEnConsulta($this->consultaDeA, $tratamiento));
        $this->assertAccesoDenegado(fn () => (new ArchivoClinico($this->db))->insertarEnConsulta($this->consultaDeA, $this->metadatos('CLI_B.jpg')));

        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM tratamientos')->fetchColumn());
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM archivos_clinicos')->fetchColumn());
    }

    /** RN-113: una mascota que nunca se vinculó a la clínica activa no muestra nada (403). */
    public function testUnaMascotaNuncaVinculadaNoMuestraNada(): void
    {
        $this->db->exec("DELETE FROM mascota_clinica WHERE id_mascota = 1 AND id_clinica = 2");

        $this->assertAccesoDenegado(fn () => (new Consulta($this->db))->historialDeMascota(self::LUNA));
        $this->assertAccesoDenegado(fn () => (new Vacuna($this->db))->findByMascota(self::LUNA));
        $this->assertAccesoDenegado(fn () => (new Desparasitacion($this->db))->findByMascota(self::LUNA));
        $this->assertAccesoDenegado(fn () => (new ArchivoClinico($this->db))->paraDescargar($this->archivoDeA));
        $this->assertSame([], (new Tratamiento($this->db))->findByConsultas([$this->consultaDeA]));
        $this->assertAccesoDenegado(fn () => $this->historialPorControlador());
        $this->assertGreaterThanOrEqual(5, $this->auditoriasDenegadas(DosClinicas::SUR));
    }

    /**
     * RN-115 (decisión de la revisión de C4): con el vínculo inactivo la
     * clínica lee solo sus registros, sin los de otras clínicas aunque haya
     * autorización, y no registra nada nuevo.
     */
    public function testConElVinculoInactivoLaClinicaSoloLeeLoSuyo(): void
    {
        $this->autorizarASur(1);
        $vacunaDeSur = (new Vacuna($this->db))->registrar(['id_mascota' => self::LUNA, 'nombre_vacuna' => 'Moquillo', 'fecha_aplicacion' => '2026-10-02']);
        $this->db->exec("UPDATE mascota_clinica SET estado = 'inactivo' WHERE id_mascota = 1 AND id_clinica = 2");

        $this->assertSame([], (new Consulta($this->db))->historialDeMascota(self::LUNA), 'Sin las consultas de Norte');
        $this->assertSame([$vacunaDeSur], array_map('intval', array_column((new Vacuna($this->db))->findByMascota(self::LUNA), 'id_vacuna')));
        $this->assertSame([], (new Desparasitacion($this->db))->findByMascota(self::LUNA));
        $this->assertAccesoDenegado(fn () => (new ArchivoClinico($this->db))->paraDescargar($this->archivoDeA));
        $this->assertAccesoDenegado(fn () => (new Vacuna($this->db))->registrar(['id_mascota' => self::LUNA, 'nombre_vacuna' => 'Otra', 'fecha_aplicacion' => '2026-10-03']));

        $respuesta = $this->historialPorControlador();
        $this->assertTrue($respuesta['mascota']['solo_lectura']);
        $this->assertArrayNotHasKey('peso', $respuesta['mascota'], 'Sin los datos nuevos de la ficha');
        $this->assertSame(['Moquillo'], array_column($respuesta['vacunas'], 'nombre_vacuna'));
    }

    /** RE-2.3.3 en el portal: el propietario solo descarga los adjuntos de sus mascotas (RN-G02). */
    public function testElPropietarioSoloDescargaLosAdjuntosDeSusMascotas(): void
    {
        $this->comoPropietario(DosClinicas::PROPIETARIO);
        $archivo = (new ArchivoClinico($this->db))->paraDescargar($this->archivoDeA);
        $this->assertSame('CLI_A.jpg', $archivo['nombre_servidor']);

        $this->comoPropietario(DosClinicas::DOBLE);
        $this->assertAccesoDenegado(fn () => (new ArchivoClinico($this->db))->paraDescargar($this->archivoDeA));
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM auditoria_sistema WHERE id_usuario = 5 AND id_clinica IS NULL')->fetchColumn());

        $_SESSION = ['id_usuario' => DosClinicas::SUPER_ADMIN];
        Contexto::activar(Contexto::dePlataforma(), 1);
        $this->assertAccesoDenegado(fn () => (new ArchivoClinico($this->db))->paraDescargar($this->archivoDeA));
    }

    // ── Apoyo ───────────────────────────────────────────────────────────

    private function registrarHistoriaDeNorte(): void
    {
        $this->comoVeterinario(DosClinicas::NORTE, DosClinicas::VET_NORTE);
        $tratamiento = ['medicamento' => 'Amoxicilina', 'dosis' => '1 ml', 'via_administracion' => 'Oral', 'duracion' => '5 días', 'fecha_inicio' => '2026-10-01'];
        $guardar = fn (int $idConsulta, array $adjunto, int $indice): array => $this->metadatos('CLI_A.jpg');
        $entrada = ['id_mascota' => self::LUNA, 'motivo' => 'Rascado de oído', 'diagnostico' => 'Otitis'];

        $this->consultaDeA = (new Consulta($this->db))->registrar($entrada, [$tratamiento], [['nombre' => 'rx.jpg']], $guardar);
        $this->tratamientoDeA = (int) $this->db->query('SELECT id_tratamiento FROM tratamientos')->fetchColumn();
        $this->archivoDeA = (int) $this->db->query('SELECT id_archivo FROM archivos_clinicos')->fetchColumn();
        $this->vacunaDeA = (new Vacuna($this->db))->registrar(['id_mascota' => self::LUNA, 'nombre_vacuna' => 'Rabia', 'fecha_aplicacion' => '2026-10-01']);
        $this->desparasitacionDeA = (new Desparasitacion($this->db))->registrar([
            'id_mascota' => self::LUNA, 'tipo' => 'interna', 'periodicidad' => 'trimestral',
            'producto' => 'Drontal', 'fecha_aplicacion' => '2026-10-01',
        ]);
    }

    private function registrarConsultaEnSur(): int
    {
        $entrada = ['id_mascota' => self::LUNA, 'motivo' => 'Control', 'diagnostico' => 'Sano'];
        $sinAdjuntos = fn (): array => [];
        return (new Consulta($this->db))->registrar($entrada, [], [], $sinAdjuntos);
    }

    private function metadatos(string $nombreServidor): array
    {
        return [
            'nombre_original' => 'rx.jpg',
            'nombre_servidor' => $nombreServidor,
            'ruta_archivo' => 'uploads/clinicos/' . $nombreServidor,
            'tipo_archivo' => 'image/jpeg',
            'extension' => 'jpg',
            'tamano_bytes' => 2048,
        ];
    }

    private function autorizarASur(int $autoriza): void
    {
        $this->db->prepare('UPDATE propietario_clinica SET autoriza_historia_compartida = ? WHERE id_propietario = 6 AND id_clinica = 2')
            ->execute([$autoriza]);
    }

    private function historialPorControlador(): array
    {
        $_GET = ['id_mascota' => (string) self::LUNA];
        ob_start();
        try {
            (new ConsultaController($this->db))->listarHistorialAjax();
        } finally {
            $salida = ob_get_clean();
        }
        return json_decode($salida, true);
    }

    private function comoVeterinario(int $clinica, int $idUsuario): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::deClinica($clinica, 'Clínica', Roles::VETERINARIO), 1);
    }

    private function comoPropietario(int $idUsuario): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::dePropietario(), 1);
    }

    private function assertAccesoDenegado(callable $accion): void
    {
        try {
            $accion();
            $this->fail('Debió responder 403.');
        } catch (AccesoDenegado $e) {
            $this->assertSame(403, $e->codigo());
        }
    }

    private function auditoriasDenegadas(int $clinica): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE id_clinica = $clinica AND accion = 'OTHER'")->fetchColumn();
    }
}
