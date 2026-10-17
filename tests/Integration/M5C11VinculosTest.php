<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/PropietarioClinica.php';
require_once __DIR__ . '/../../models/RegistroPropietario.php';
require_once __DIR__ . '/../../models/Mascota.php';
require_once __DIR__ . '/../../models/Consulta.php';
require_once __DIR__ . '/../../models/Vacuna.php';
require_once __DIR__ . '/../../models/Recordatorio.php';
require_once __DIR__ . '/../../controllers/PropietarioController.php';
require_once __DIR__ . '/../../controllers/CitaController.php';

/**
 * M5 C1.1 — Decisiones del usuario en «C1 — Revisión» (2026-10-12).
 *
 * 1. RN-G13: el super-administrador nunca es cliente. La clínica recibe la
 *    respuesta de siempre, pero no se crea la solicitud ni se envía el correo,
 *    y un enlace viejo tampoco lo liga.
 * 2. RN-115: cuando la clínica desactiva el vínculo de un cliente, sus
 *    mascotas quedan inactivas allí, como cuando se desvincula el propietario;
 *    con citas sin resolver no se desactiva (decisión durante C1.1).
 */
final class M5C11VinculosTest extends TestCase
{
    private const GINA = 'gina@zooki.test';
    private const HOY = '2030-01-07';

    private PDO $db;
    private array $correos = [];

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
        DosClinicas::vincularLunaASur($this->db);
        $this->correos = [];
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        http_response_code(200);
    }

    private function comoAdministrador(int $clinica, int $idUsuario): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::deClinica($clinica, 'Clínica Norte', Roles::ADMIN), 1);
    }

    private function comoVeterinario(int $clinica, int $idUsuario): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::deClinica($clinica, 'Clínica', Roles::VETERINARIO), 1);
    }

    private function comoPropietario(): void
    {
        $_SESSION = ['id_usuario' => DosClinicas::PROPIETARIO];
        Contexto::activar(Contexto::dePropietario(), 1);
    }

    private function contar(string $sql): int
    {
        return (int) $this->db->query($sql)->fetchColumn();
    }

    private function vinculoMascota(int $clinica): string
    {
        return (string) $this->db->query("SELECT estado FROM mascota_clinica WHERE id_mascota = 1 AND id_clinica = $clinica")->fetchColumn();
    }

    private function solicitarPorControlador(array $post): array
    {
        $controlador = new PropietarioController($this->db, function ($email, $nombre, $asunto, $html): bool {
            $this->correos[] = $email;
            return true;
        });
        $_POST = $post;
        ob_start();
        try {
            $controlador->solicitarVinculoAjax();
        } finally {
            $salida = (string) ob_get_clean();
        }
        return json_decode($salida, true) ?? [];
    }

    private function desactivarFabioDesdeNorte(): void
    {
        $this->comoAdministrador(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE);
        (new PropietarioClinica($this->db))->actualizar(DosClinicas::PROPIETARIO, ['nombre_completo' => 'Fabio Dueño', 'telefono' => '3001234567', 'estado' => '0']);
    }

    // ── 1. RN-G13: el super-administrador nunca es cliente ──────────────

    public function testLaSolicitudPorCorreoAUnSuperAdministradorRespondeIgualSinCrearNiEnviar(): void
    {
        $this->comoAdministrador(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE);

        $aGina = $this->solicitarPorControlador(['identificador' => self::GINA]);

        $this->assertSame(['success' => true, 'message' => 'Se envió la solicitud al propietario. Vuelve a buscarlo cuando confirme.'], $aGina);
        $this->assertSame([], $this->correos, 'No se envía el correo.');
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM verificaciones_email WHERE proposito = 'vinculo_clinica'"));
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM auditoria_sistema WHERE registro_id = '7' AND descripcion LIKE '%RN-G13%'"));

        $this->comoAdministrador(DosClinicas::SUR, DosClinicas::ADMIN_SUR);
        $aFabioEnSur = $this->solicitarPorControlador(['identificador' => '1000000002']);
        $this->assertSame($aFabioEnSur, $aGina, 'La respuesta es la misma que con una cuenta cualquiera.');
        $this->assertCount(1, $this->correos);
    }

    public function testLaBusquedaYLaSolicitudPorIdTampocoDistinguenNiLigan(): void
    {
        // Un vínculo inactivo anterior a la regla, para que la solicitud por id tenga a quién buscar.
        $this->db->exec("INSERT INTO propietario_clinica (id_propietario, id_clinica, estado) VALUES (7, 1, 'inactivo')");
        $this->comoAdministrador(DosClinicas::NORTE, DosClinicas::ADMIN_NORTE);
        $modelo = new PropietarioClinica($this->db);

        $this->assertSame(['vinculado' => false, 'mensaje' => PropietarioClinica::MENSAJE_SIN_VINCULO], $modelo->buscarExacto(self::GINA));
        $this->assertNull($modelo->solicitarVinculo(self::GINA));
        $this->assertNull($modelo->solicitarVinculoDe(DosClinicas::SUPER_ADMIN));
        $porId = $this->solicitarPorControlador(['id_usuario' => (string) DosClinicas::SUPER_ADMIN]);

        $this->assertTrue($porId['success']);
        $this->assertSame([], $this->correos);
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM verificaciones_email WHERE proposito = 'vinculo_clinica'"));
        $this->assertSame('inactivo', $this->db->query('SELECT estado FROM propietario_clinica WHERE id_propietario = 7 AND id_clinica = 1')->fetchColumn());
    }

    public function testUnEnlaceViejoNoLigaAlSuperAdministrador(): void
    {
        $token = 'enlace-viejo-de-gina';
        $this->db->prepare("INSERT INTO verificaciones_email (id_usuario, email, token_hash, expires_at, id_clinica_vinculo, proposito)
            VALUES (7, ?, ?, ?, 1, 'vinculo_clinica')")->execute([self::GINA, password_hash($token, PASSWORD_DEFAULT), date('Y-m-d H:i:s', time() + 3600)]);
        $idEnlace = (int) $this->db->lastInsertId();

        $this->assertFalse((new PropietarioClinica($this->db))->confirmarVinculo($idEnlace, $token));

        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM propietario_clinica WHERE id_propietario = 7'));
    }

    public function testElRegistroDelPropietarioTampocoLeEnviaUnVinculo(): void
    {
        $respuesta = (new RegistroPropietario($this->db))->conFormulario([
            'id_clinica' => '2', 'tipo_documento' => 'CC', 'documento' => '1000000097', 'nombre_completo' => 'Gina Plataforma',
            'telefono' => '3001112233', 'email' => self::GINA, 'password' => 'Ajena#Clave2026',
            'confirm_password' => 'Ajena#Clave2026', 'acepta_datos' => '1',
        ], '10.0.0.9');

        $this->assertNull($respuesta['enlace']);
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM verificaciones_email WHERE id_usuario = 7"));
    }

    // ── 2. RN-115: la clínica desactiva el vínculo ──────────────────────

    public function testDesactivarDesdeLaClinicaDejaLasMascotasInactivasSoloEnEsaClinica(): void
    {
        $this->db->exec("UPDATE propietario_clinica SET autoriza_historia_compartida = 1 WHERE id_propietario = 6 AND id_clinica = 1");

        $this->desactivarFabioDesdeNorte();

        $this->assertSame('inactivo', $this->vinculoMascota(DosClinicas::NORTE));
        $this->assertSame(0, $this->contar('SELECT autoriza_historia_compartida FROM propietario_clinica WHERE id_propietario = 6 AND id_clinica = 1'));
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM auditoria_sistema WHERE id_clinica = 1 AND descripcion LIKE '%desactivó el vínculo%' AND datos_nuevos LIKE '%\"mascotas_desvinculadas\":1%'"));
        // Aislamiento: Sur no cambia.
        $this->assertSame('activo', $this->vinculoMascota(DosClinicas::SUR));
        $this->assertSame('activo', $this->db->query('SELECT estado FROM propietario_clinica WHERE id_propietario = 6 AND id_clinica = 2')->fetchColumn());
    }

    public function testConCitasSinResolverLaClinicaNoDesactivaNiTocaNada(): void
    {
        $this->db->exec("INSERT INTO citas (id_clinica, id_mascota, id_veterinario, fecha, hora, motivo, estado) VALUES (1, 1, 2, '2030-01-08', '09:00:00', 'Control', 'confirmada')");

        try {
            $this->desactivarFabioDesdeNorte();
            $this->fail('Desactivó con una cita sin resolver.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Tiene 1 cita sin resolver en esta clínica. Cancélala o ciérrala antes de desactivar el vínculo.', $e->getMessage());
        }

        $this->assertSame('activo', $this->db->query('SELECT estado FROM propietario_clinica WHERE id_propietario = 6 AND id_clinica = 1')->fetchColumn());
        $this->assertSame('activo', $this->vinculoMascota(DosClinicas::NORTE));
    }

    public function testLaClinicaNoRegistraConsultaNiVacunaYNoSalenSusRecordatorios(): void
    {
        $this->db->exec("INSERT INTO vacunas (id_clinica, id_mascota, id_veterinario, nombre_vacuna, fecha_aplicacion, fecha_proxima_dosis) VALUES
            (1, 1, 2, 'Rabia', '2029-01-09', '2030-01-09'),
            (2, 1, 4, 'Moquillo', '2029-01-09', '2030-01-09')");
        $this->desactivarFabioDesdeNorte();
        $this->comoVeterinario(DosClinicas::NORTE, DosClinicas::VET_NORTE);

        $intentos = [
            'consulta' => fn () => (new Consulta($this->db))->registrar(['id_mascota' => 1, 'peso' => '8.5', 'motivo' => 'Control', 'diagnostico' => 'Sano'], [], [], fn () => []),
            'vacuna' => fn () => (new Vacuna($this->db))->registrar(['id_mascota' => 1, 'nombre_vacuna' => 'Rabia', 'fecha_aplicacion' => '2026-10-01']),
        ];
        foreach ($intentos as $registro => $intento) {
            try {
                $intento();
                $this->fail("Norte registró una $registro sobre una mascota desvinculada.");
            } catch (AccesoDenegado $e) {
                $this->assertSame(403, $e->codigo(), $registro);
            }
        }
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM consultas'));
        $this->assertSame(2, $this->contar('SELECT COUNT(*) FROM vacunas'));

        $recordatorios = (new Recordatorio($this->db))->dosisPorRecordar(self::HOY);
        $this->assertSame([DosClinicas::SUR], array_map('intval', array_column($recordatorios, 'id_clinica')), 'Solo recuerda la clínica que sigue vinculada.');
    }

    public function testAlReconfirmarYAgendarLaMascotaSeReactiva(): void
    {
        $this->desactivarFabioDesdeNorte();
        $modelo = new PropietarioClinica($this->db);
        $solicitud = $modelo->solicitarVinculoDe(DosClinicas::PROPIETARIO);
        $this->assertTrue($modelo->confirmarVinculo($solicitud['id_enlace'], $solicitud['token']));
        $this->assertSame('inactivo', $this->vinculoMascota(DosClinicas::NORTE), 'Confirmar el vínculo no reactiva sola a la mascota (RN-110).');

        $this->comoPropietario();
        $_POST = ['id_clinica' => '1', 'id_mascota' => '1', 'id_veterinario' => '2', 'id_tipo_cita' => '1', 'fecha' => self::HOY, 'hora' => '09:00', 'motivo' => 'Control'];
        $reloj = fn (): DateTimeImmutable => new DateTimeImmutable(self::HOY . ' 07:00', new DateTimeZone(ReglaAtencion::ZONA));
        ob_start();
        try {
            (new CitaController($this->db, $reloj))->agendarDesdePortalAjax();
        } finally {
            $respuesta = json_decode((string) ob_get_clean(), true);
        }

        $this->assertTrue($respuesta['success'], $respuesta['message'] ?? '');
        $this->assertSame('activo', $this->vinculoMascota(DosClinicas::NORTE));
    }
}
