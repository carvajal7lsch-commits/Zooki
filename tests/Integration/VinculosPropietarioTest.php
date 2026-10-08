<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/VinculosPropietario.php';
require_once __DIR__ . '/../../models/HistoriaPropietario.php';
require_once __DIR__ . '/../../models/Consulta.php';
require_once __DIR__ . '/../../models/Vacuna.php';
require_once __DIR__ . '/../../models/Cita.php';
require_once __DIR__ . '/../../models/Mascota.php';
require_once __DIR__ . '/../../models/ArchivoClinico.php';

/**
 * HU-5.12 y HU-5.13 con RN-115 (decisión de la revisión de C4).
 *
 * - La autorización de historia compartida cambia lo que ve la otra clínica
 *   en la siguiente petición, y queda en auditoría (RE-5.12.1/2/4).
 * - Vincularse no expone mascotas hasta agendar o ser atendidas (RE-5.13.2).
 * - Desvincularse se rechaza con citas sin resolver (RE-5.13.3); sin ellas,
 *   la clínica conserva sus registros en solo lectura, no registra nada
 *   nuevo y no ve los de otras clínicas (RE-5.12.3, RN-115).
 */
final class VinculosPropietarioTest extends TestCase
{
    private const LUNA = 1;

    private PDO $db;
    private int $consultaDeNorte;
    private int $archivoDeNorte;
    private int $consultaDeSur;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
        DosClinicas::vincularLunaASur($this->db);
        $this->registrarHistoria();
        $this->comoPropietario();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // ── HU-5.12 ─────────────────────────────────────────────────────────

    public function testAutorizarYRevocarCambianLoQueVeLaOtraClinicaAlInstante(): void
    {
        $vinculos = new VinculosPropietario($this->db);

        $vinculos->autorizarHistoria(DosClinicas::SUR, true);
        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR);
        $this->assertContains($this->consultaDeNorte, $this->consultasQueVe());

        $this->comoPropietario();
        $vinculos->autorizarHistoria(DosClinicas::SUR, false);
        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR);
        $this->assertNotContains($this->consultaDeNorte, $this->consultasQueVe());

        $auditoria = $this->db->query("SELECT descripcion FROM auditoria_sistema WHERE id_clinica = 2 AND id_usuario = 6 AND tabla_afectada = 'propietario_clinica' ORDER BY id_auditoria")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['Autorizó la historia compartida (HU-5.12)', 'Revocó la historia compartida (HU-5.12)'], $auditoria);
    }

    public function testSoloAutorizaClinicasConVinculoActivo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new VinculosPropietario($this->db))->autorizarHistoria(DosClinicas::SUSPENDIDA, true);
    }

    // ── HU-5.13: vincularse ─────────────────────────────────────────────

    /** RE-5.13.1/2: se vincula con un clic; la clínica no ve sus mascotas hasta agendar o atenderlas. */
    public function testVincularseAUnaClinicaActivaNoLeExponeSusMascotas(): void
    {
        $this->db->exec('DELETE FROM mascota_clinica WHERE id_clinica = 2');
        $this->db->exec('DELETE FROM propietario_clinica WHERE id_propietario = 6 AND id_clinica = 2');
        $vinculos = new VinculosPropietario($this->db);
        $this->assertSame(['Clínica Sur'], array_column($vinculos->disponibles(), 'nombre'), 'La suspendida no se ofrece');

        $vinculos->vincular(DosClinicas::SUR);

        $this->assertSame(['Clínica Norte', 'Clínica Sur'], array_column($vinculos->clinicas(), 'nombre'));
        $this->assertSame([], $vinculos->disponibles());
        $this->assertRechazo(fn () => $vinculos->vincular(DosClinicas::SUR), 'Ya estás vinculado');
        $this->assertRechazo(fn () => $vinculos->vincular(DosClinicas::SUSPENDIDA), 'no está disponible');

        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR);
        $this->assertSame([], (new Mascota($this->db))->getAll(), 'Recién vinculada, Sur no ve a Luna');
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE id_clinica = 2 AND accion = 'INSERT' AND tabla_afectada = 'propietario_clinica'")->fetchColumn());
    }

    // ── HU-5.13: desvincularse ──────────────────────────────────────────

    public function testConCitasSinResolverNoSeDesvincula(): void
    {
        $this->db->exec("INSERT INTO citas (id_clinica, id_mascota, id_veterinario, fecha, hora, motivo, estado)
            VALUES (2, 1, 4, '2030-01-07', '09:00:00', 'Control', 'en_curso')");

        $this->assertRechazo(fn () => (new VinculosPropietario($this->db))->desvincular(DosClinicas::SUR), '1 cita sin resolver');

        $this->assertSame('activo', $this->db->query('SELECT estado FROM propietario_clinica WHERE id_propietario = 6 AND id_clinica = 2')->fetchColumn());
    }

    /** RN-115 / RE-5.12.3: tras desvincularse, la clínica lee lo suyo y nada más. */
    public function testTrasDesvincularseLaClinicaLeeSusRegistrosYNoRegistraNadaNuevo(): void
    {
        $vinculos = new VinculosPropietario($this->db);
        $vinculos->autorizarHistoria(DosClinicas::SUR, true);

        $vinculos->desvincular(DosClinicas::SUR);

        $vinculo = $this->db->query('SELECT estado, autoriza_historia_compartida FROM propietario_clinica WHERE id_propietario = 6 AND id_clinica = 2')->fetch(PDO::FETCH_NUM);
        $this->assertSame(['inactivo', '0'], array_map('strval', $vinculo));
        $this->assertSame('inactivo', $this->db->query('SELECT estado FROM mascota_clinica WHERE id_mascota = 1 AND id_clinica = 2')->fetchColumn());

        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR);
        $this->assertSame([$this->consultaDeSur], $this->consultasQueVe(), 'Solo lo suyo, nunca lo de Norte');
        $this->assertSame(['Moquillo'], array_column((new Vacuna($this->db))->findByMascota(self::LUNA), 'nombre_vacuna'));
        $this->assertContains($this->consultaDeSur, array_map('intval', array_column((new Consulta($this->db))->listarDeLaClinica(), 'id_consulta')));
        $this->assertSame([], (new Mascota($this->db))->getAll(), 'Ya no está entre sus pacientes');
        $this->assertAccesoDenegado(fn () => (new ArchivoClinico($this->db))->paraDescargar($this->archivoDeNorte));
        $this->assertAccesoDenegado(fn () => (new Vacuna($this->db))->registrar(['id_mascota' => self::LUNA, 'nombre_vacuna' => 'X', 'fecha_aplicacion' => '2026-10-05']));
        $this->assertAccesoDenegado(fn () => (new Consulta($this->db))->registrar(['id_mascota' => self::LUNA, 'motivo' => 'X', 'diagnostico' => 'Y'], [], [], fn (): array => []));
        $this->assertAccesoDenegado(fn () => (new Cita($this->db))->registrar(['id_mascota' => self::LUNA, 'id_veterinario' => DosClinicas::VET_SUR, 'fecha' => '2030-01-07', 'hora' => '09:00', 'motivo' => 'X', 'id_tipo_cita' => 2]));

        // El propietario sigue viendo toda la historia de Luna (RN-114).
        $this->comoPropietario();
        $this->assertCount(2, (new HistoriaPropietario($this->db))->consultas(self::LUNA));
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE id_clinica = 2 AND descripcion LIKE '%desvinculó%'")->fetchColumn());
    }

    /** Volver a vincularse y agendar reactiva el vínculo de la mascota. */
    public function testRevincularseYAgendarReactivaLaMascota(): void
    {
        $vinculos = new VinculosPropietario($this->db);
        $vinculos->desvincular(DosClinicas::SUR);
        $vinculos->vincular(DosClinicas::SUR);
        $this->assertSame('inactivo', $this->db->query('SELECT estado FROM mascota_clinica WHERE id_mascota = 1 AND id_clinica = 2')->fetchColumn());

        $citas = (new Cita($this->db))->enClinicaDelPropietario(DosClinicas::SUR);
        (new Mascota($this->db))->enClinicaDelPropietario(DosClinicas::SUR)->vincular(self::LUNA, DosClinicas::PROPIETARIO);
        $citas->registrar(['id_mascota' => self::LUNA, 'id_veterinario' => DosClinicas::VET_SUR, 'fecha' => '2030-01-07', 'hora' => '09:00', 'motivo' => 'Control', 'id_tipo_cita' => 2]);

        $this->assertSame('activo', $this->db->query('SELECT estado FROM mascota_clinica WHERE id_mascota = 1 AND id_clinica = 2')->fetchColumn());
    }

    // ── Apoyo ───────────────────────────────────────────────────────────

    private function registrarHistoria(): void
    {
        $conAdjunto = fn (int $idConsulta): array => [
            'nombre_original' => 'rx.jpg', 'nombre_servidor' => 'CLI_N.jpg', 'ruta_archivo' => 'uploads/clinicos/CLI_N.jpg',
            'tipo_archivo' => 'image/jpeg', 'extension' => 'jpg', 'tamano_bytes' => 1024,
        ];
        $this->comoVeterinario(DosClinicas::NORTE, DosClinicas::VET_NORTE);
        $this->consultaDeNorte = (new Consulta($this->db))->registrar(['id_mascota' => 1, 'motivo' => 'Oído', 'diagnostico' => 'Otitis'], [], [['nombre' => 'rx']], $conAdjunto);
        $this->archivoDeNorte = (int) $this->db->query('SELECT id_archivo FROM archivos_clinicos')->fetchColumn();
        (new Vacuna($this->db))->registrar(['id_mascota' => 1, 'nombre_vacuna' => 'Rabia', 'fecha_aplicacion' => '2026-09-01']);

        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR);
        $this->consultaDeSur = (new Consulta($this->db))->registrar(['id_mascota' => 1, 'motivo' => 'Control', 'diagnostico' => 'Sano'], [], [], fn (): array => []);
        (new Vacuna($this->db))->registrar(['id_mascota' => 1, 'nombre_vacuna' => 'Moquillo', 'fecha_aplicacion' => '2026-10-01']);
    }

    /** @return list<int> consultas de Luna que ve la clínica activa */
    private function consultasQueVe(): array
    {
        return array_map('intval', array_column((new Consulta($this->db))->historialDeMascota(self::LUNA), 'id_consulta'));
    }

    private function comoPropietario(): void
    {
        $_SESSION = ['id_usuario' => DosClinicas::PROPIETARIO];
        Contexto::activar(Contexto::dePropietario(), 1);
    }

    private function comoVeterinario(int $clinica, int $idUsuario): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::deClinica($clinica, 'Clínica', Roles::VETERINARIO), 1);
    }

    private function assertRechazo(callable $accion, string $parteDelMensaje): void
    {
        try {
            $accion();
            $this->fail('Debió rechazarse: ' . $parteDelMensaje);
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString($parteDelMensaje, $e->getMessage());
        }
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
}
