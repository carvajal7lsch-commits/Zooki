<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../controllers/UsuarioController.php';
require_once __DIR__ . '/../../controllers/IdentidadController.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../models/InvitacionPersonal.php';
require_once __DIR__ . '/../../models/CuentaTitular.php';
require_once __DIR__ . '/../../models/IntentoLogin.php';
require_once __DIR__ . '/../../helpers/CierreSesiones.php';
require_once __DIR__ . '/../../helpers/ZonaHoraria.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../config/EmailService.php';

/**
 * D2.2 — Corrección antes de D3.
 *
 *   RE-T.7.5 / RN-705 / RN-G06  el personal que ya existe se invita; el rol llega solo si acepta,
 *                               y el administrador recibe lo mismo exista o no la cuenta
 *   RE-T.2.5 / Modelos §13.3    crear o cambiar la contraseña cierra las demás sesiones
 *   Revisión de D2.1            MAIL_MODO con el host efectivo, zona horaria, «Se reenvió la invitación.»
 */
final class D22CorreccionTest extends TestCase
{
    private const ENVIADA = 'Invitación enviada. La persona tiene 72 horas para aceptarla.';
    private const CLAVE_NUEVA = 'Bosque#Seguro2026';

    private PDO $db;
    private object $correo;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        $this->db->exec("CREATE TABLE casos_soporte (id_caso INTEGER PRIMARY KEY AUTOINCREMENT, tipo TEXT, id_usuario INTEGER, id_clinica INTEGER, descripcion TEXT, estado TEXT DEFAULT 'abierto')");
        $this->db->exec('CREATE TABLE intentos_login (id_intento INTEGER PRIMARY KEY AUTOINCREMENT, identificador TEXT UNIQUE, intentos INTEGER, bloqueado_hasta TEXT, primer_intento TEXT, ultimo_intento TEXT)');
        Security::definirAuditoria(new Auditoria($this->db));
        Security::definirAlmacenDeIntentos(new IntentoLogin($this->db));
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR'] = '192.0.2.10';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_POST = [];
        $_GET = [];
        $this->correo = new class {
            public array $enviados = [];
            public function limpiarDirecciones(): void {}
            public function enviarInvitacionPersonal(...$d) { $this->enviados[] = ['invitacion_personal', ...$d]; return true; }
            public function enviarInvitacionClinica(...$d) { $this->enviados[] = ['invitacion_clinica', ...$d]; return true; }
            public function enviarRestablecimientoPorAdministrador(...$d) { $this->enviados[] = ['restablecimiento', ...$d]; return true; }
            public function enviarAvisoCambioPassword(...$d) { $this->enviados[] = ['aviso_password', ...$d]; return true; }
            public function obtenerPlantillaBaseHTML(...$d) { return (string) $d[2]; }
            public function enviarCorreoPersonalizado(...$d) { $this->enviados[] = ['personalizado', ...$d]; return true; }
        };
    }

    protected function tearDown(): void
    {
        Security::definirAuditoria(false);
        Security::definirAlmacenDeIntentos(null);
        Security::definirFuenteDeContextos(null);
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
    }

    private function comoAdmin(int $idUsuario, int $clinica, string $nombre): UsuarioController
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::deClinica($clinica, $nombre, Roles::ADMIN), 1);
        return new UsuarioController($this->db, $this->correo);
    }

    private function json(callable $accion): array
    {
        ob_start();
        try {
            $accion();
        } finally {
            $salida = (string) ob_get_clean();
        }
        return json_decode($salida, true) ?? [];
    }

    private function invitar(int $admin, int $clinica, array $cambios): array
    {
        $controlador = $this->comoAdmin($admin, $clinica, $clinica === DosClinicas::SUR ? 'Clínica Sur' : 'Clínica Norte');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $cambios + ['tipo_documento' => 'CC', 'documento' => '1000000077', 'nombre_completo' => 'Persona Escrita',
            'email' => 'escrita@zooki.test', 'telefono' => '3001112233', 'id_rol' => '2', 'estado' => '1'];
        return $this->json(fn () => $controlador->registrarAjax());
    }

    /** id y token del último enlace enviado de esa clase. */
    private function ultimoEnlace(string $clase): array
    {
        $envios = array_values(array_filter($this->correo->enviados, fn ($e) => $e[0] === $clase));
        $enlace = end($envios)[5];
        parse_str((string) parse_url($enlace, PHP_URL_QUERY), $consulta);
        return ['id' => (int) $consulta['id'], 'token' => (string) $consulta['token'], 'action' => $consulta['action']];
    }

    private function rolEn(int $idUsuario, int $clinica): ?array
    {
        $stmt = $this->db->prepare('SELECT id_rol, estado FROM usuario_clinica WHERE id_usuario = ? AND id_clinica = ?');
        $stmt->execute([$idUsuario, $clinica]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function decidir(array $enlace, string $decision): string
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['id' => (string) $enlace['id'], 'token' => $enlace['token'], 'decision' => $decision];
        ob_start();
        try {
            (new IdentidadController($this->db, $this->correo))->invitacion();
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }

    // ── RE-T.7.5: invitación a quien ya tiene cuenta ─────────────────────

    public function testLaRespuestaAlAdministradorEsIgualExistaONoLaCuenta(): void
    {
        $nueva = $this->invitar(DosClinicas::ADMIN_SUR, DosClinicas::SUR, []);
        $existente = $this->invitar(DosClinicas::ADMIN_SUR, DosClinicas::SUR, ['documento' => '1000000006', 'email' => 'otro@zooki.test']);
        $cruzada = $this->invitar(DosClinicas::ADMIN_SUR, DosClinicas::SUR, ['documento' => '1000000001', 'email' => 'beto@zooki.test']);

        $this->assertSame(['success' => true, 'message' => self::ENVIADA], $nueva);
        $this->assertSame($nueva, $existente);
        $this->assertSame($nueva, $cruzada, 'Documento y correo de personas distintas: la misma respuesta.');
    }

    public function testLaPersonaExistenteNoQuedaVinculadaYLaListaSoloMuestraLoEscrito(): void
    {
        $this->invitar(DosClinicas::ADMIN_SUR, DosClinicas::SUR, ['documento' => '1000000006', 'email' => 'otro@zooki.test',
            'nombre_completo' => 'Nombre Que Escribió', 'telefono' => '3009998877']);
        $this->invitar(DosClinicas::ADMIN_SUR, DosClinicas::SUR, []);

        $this->assertNull($this->rolEn(DosClinicas::PROPIETARIO, DosClinicas::SUR));
        $controlador = $this->comoAdmin(DosClinicas::ADMIN_SUR, DosClinicas::SUR, 'Clínica Sur');
        $this->assertNotContains(DosClinicas::PROPIETARIO, array_map('intval', array_column($controlador->listar(), 'id_usuario')));
        $pendientes = $controlador->listarInvitaciones();
        $this->assertCount(2, $pendientes);
        $fabio = array_values(array_filter($pendientes, fn ($p) => $p['documento'] === '1000000006'))[0];
        $this->assertSame('Nombre Que Escribió', $fabio['nombre_completo']);
        $this->assertSame('otro@zooki.test', $fabio['email']);
        $this->assertSame('3009998877', $fabio['telefono']);
        $this->assertStringNotContainsString('fabio@zooki.test', json_encode($pendientes));
        $this->assertStringNotContainsString('Fabio', json_encode($pendientes, JSON_UNESCAPED_UNICODE));
        $this->assertSame(array_keys($pendientes[0]), array_keys($pendientes[1]), 'Cuenta nueva y existente se ven igual.');
    }

    public function testElGetMuestraLaInvitacionSinDecidirYElPostSinCsrfSeRechaza(): void
    {
        $this->invitar(DosClinicas::ADMIN_SUR, DosClinicas::SUR, ['documento' => '1000000006', 'email' => 'otro@zooki.test']);
        $enlace = $this->ultimoEnlace('invitacion_clinica');
        $this->assertSame('invitacion_personal', $enlace['action']);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SESSION = [];
        $_GET = ['id' => (string) $enlace['id'], 'token' => $enlace['token']];
        ob_start();
        (new IdentidadController($this->db, $this->correo))->invitacion();
        $html = (string) ob_get_clean();
        $this->assertStringContainsString('Clínica Sur', $html);
        $this->assertStringContainsString('Aceptar la invitación', $html);
        $this->assertStringContainsString('Rechazar', $html);
        $this->assertNull($this->rolEn(DosClinicas::PROPIETARIO, DosClinicas::SUR));
        $this->assertNotNull((new InvitacionPersonal($this->db))->leer($enlace['id'], $enlace['token']));

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['id' => (string) $enlace['id'], 'token' => $enlace['token'], 'decision' => 'aceptar'];
        try {
            Security::autorizar('invitacion_personal');
            $this->fail('Sin CSRF el POST no debe decidir.');
        } catch (AccesoDenegado $e) {
            $this->assertSame(403, $e->codigo());
        }
    }

    /** Un propietario que acepta queda propietario y veterinario, y elige el contexto (Modelos §9). */
    public function testAceptarCreaElRolYConservaLosDemas(): void
    {
        $this->invitar(DosClinicas::ADMIN_SUR, DosClinicas::SUR, ['documento' => '1000000006', 'email' => 'otro@zooki.test']);
        $enlace = $this->ultimoEnlace('invitacion_clinica');

        $html = $this->decidir($enlace, 'aceptar');

        $this->assertStringContainsString('Bienvenido al equipo', $html);
        $this->assertSame(['id_rol' => 2, 'estado' => 'activo'], array_map(fn ($v) => is_numeric($v) ? (int) $v : $v, $this->rolEn(DosClinicas::PROPIETARIO, DosClinicas::SUR)));
        $claves = array_column((new Usuario($this->db))->contextosDe(DosClinicas::PROPIETARIO), 'clave');
        $this->assertSame(['clinica:2:2', 'propietario'], $claves);
        $this->assertSame([], (new InvitacionPersonal($this->db))->pendientesDeClinica(DosClinicas::SUR));
        $auditada = $this->db->query("SELECT id_clinica, id_usuario FROM auditoria_sistema WHERE descripcion = 'Invitación al personal aceptada (RE-T.7.5)'")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame([2, 6], [(int) $auditada['id_clinica'], (int) $auditada['id_usuario']]);
        $this->assertStringContainsString('no es válida', $this->decidir($enlace, 'aceptar'), 'El enlace sirve una sola vez.');
    }

    public function testRechazarOVencerNoVincula(): void
    {
        $this->invitar(DosClinicas::ADMIN_SUR, DosClinicas::SUR, ['documento' => '1000000006', 'email' => 'otro@zooki.test']);
        $rechazada = $this->ultimoEnlace('invitacion_clinica');
        $this->assertStringContainsString('Invitación rechazada', $this->decidir($rechazada, 'rechazar'));
        $this->assertStringContainsString('no es válida', $this->decidir($rechazada, 'aceptar'));
        $this->assertNull($this->rolEn(DosClinicas::PROPIETARIO, DosClinicas::SUR));

        $this->invitar(DosClinicas::ADMIN_SUR, DosClinicas::SUR, ['documento' => '1000000006', 'email' => 'otro@zooki.test']);
        $vencida = $this->ultimoEnlace('invitacion_clinica');
        $this->db->prepare("UPDATE verificaciones_email SET expires_at = '2000-01-01 00:00:00' WHERE id = ?")->execute([$vencida['id']]);
        $this->assertStringContainsString('no es válida', $this->decidir($vencida, 'aceptar'));
        $this->assertNull($this->rolEn(DosClinicas::PROPIETARIO, DosClinicas::SUR));

        (new CuentaTitular($this->db))->limpiarPendientes();
        $this->assertSame([], (new InvitacionPersonal($this->db))->pendientesDeClinica(DosClinicas::SUR));
        $eventos = $this->db->query("SELECT descripcion FROM auditoria_sistema WHERE id_clinica = 2 AND tabla_afectada = 'verificaciones_email' ORDER BY id_auditoria")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('Invitación al personal rechazada (RE-T.7.5)', $eventos);
        $this->assertContains('Invitación al personal vencida (RE-T.7.5)', $eventos);
    }

    public function testLaOtraClinicaNoVeNiTocaLaInvitacion(): void
    {
        $this->invitar(DosClinicas::ADMIN_SUR, DosClinicas::SUR, ['documento' => '1000000006', 'email' => 'otro@zooki.test']);
        $id = $this->ultimoEnlace('invitacion_clinica')['id'];

        $norte = $this->comoAdmin(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE, 'Clínica Norte');
        $this->assertSame([], $norte->listarInvitaciones());
        foreach (['reenviarInvitacionAjax', 'cancelarInvitacionAjax'] as $accion) {
            $_POST = ['id_invitacion' => (string) $id];
            try {
                $this->json(fn () => $norte->$accion());
                $this->fail("$accion debió denegarse en otra clínica");
            } catch (AccesoDenegado $e) {
                $this->assertSame(403, $e->codigo());
            }
        }
        $this->assertCount(1, (new InvitacionPersonal($this->db))->pendientesDeClinica(DosClinicas::SUR));
    }

    /** RE-T.17.5 y cuentas pendientes: la misma respuesta, sin correo y sin nada que aceptar. */
    public function testUnaCuentaPendienteRecibeLaMismaRespuestaYNadaCambia(): void
    {
        $this->invitar(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE, ['documento' => '1000000088', 'email' => 'laura@zooki.test']);
        $idLaura = (int) (new Usuario($this->db))->buscarPorEmail('laura@zooki.test')['id_usuario'];
        $this->correo->enviados = [];

        $r = $this->invitar(DosClinicas::ADMIN_SUR, DosClinicas::SUR, ['documento' => '1000000099', 'email' => 'laura@zooki.test']);

        $this->assertSame(['success' => true, 'message' => self::ENVIADA], $r);
        $this->assertSame([], $this->correo->enviados);
        $this->assertNull($this->rolEn($idLaura, DosClinicas::SUR));
        $this->assertCount(1, (new InvitacionPersonal($this->db))->pendientesDeClinica(DosClinicas::SUR));
    }

    /** Solo se dice lo que el administrador ya ve: su personal y sus invitaciones pendientes. */
    public function testElAvisoPrevioSoloUsaDatosQueElAdministradorYaConoce(): void
    {
        $this->assertSame('Esa persona ya es parte del personal de esta clínica. Edítala desde la lista.',
            $this->invitar(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE, ['email' => 'beto@zooki.test'])['message']);
        $this->invitar(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE, ['documento' => '1000000003', 'email' => 'otro@zooki.test']);
        $pendiente = 'Ya hay una invitación pendiente con ese documento o correo. Reenvíala desde la lista.';
        $this->assertSame($pendiente, $this->invitar(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE, ['documento' => '1000000003', 'email' => 'otra@zooki.test'])['message']);
        $this->invitar(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE, ['documento' => '1000000055', 'email' => 'nueva55@zooki.test']);
        $this->assertSame($pendiente, $this->invitar(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE, ['documento' => '1000000055', 'email' => 'nueva55@zooki.test'])['message']);
    }

    /** La auditoría de la clínica tampoco distingue una cuenta nueva de una existente. */
    public function testLaAuditoriaDeLaClinicaEsIgualParaLasDosClases(): void
    {
        $this->invitar(DosClinicas::ADMIN_SUR, DosClinicas::SUR, []);
        $this->invitar(DosClinicas::ADMIN_SUR, DosClinicas::SUR, ['documento' => '1000000006', 'email' => 'otro@zooki.test']);
        $filas = $this->db->query('SELECT accion, tabla_afectada, descripcion FROM auditoria_sistema WHERE id_clinica = 2')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(2, $filas);
        $this->assertSame($filas[0], $filas[1]);
        $this->assertSame('Invitación al personal enviada (RE-T.7.5)', $filas[0]['descripcion']);
    }

    public function testReenviarYCancelarFuncionanIgualParaLasDosClases(): void
    {
        $this->invitar(DosClinicas::ADMIN_SUR, DosClinicas::SUR, ['documento' => '1000000006', 'email' => 'otro@zooki.test']);
        $vieja = $this->ultimoEnlace('invitacion_clinica');
        $this->invitar(DosClinicas::ADMIN_SUR, DosClinicas::SUR, []);
        $controlador = $this->comoAdmin(DosClinicas::ADMIN_SUR, DosClinicas::SUR, 'Clínica Sur');
        $ids = array_column($controlador->listarInvitaciones(), 'id_invitacion');

        foreach ($ids as $id) {
            $_POST = ['id_invitacion' => (string) $id];
            $this->assertSame(['success' => true, 'message' => 'Se reenvió la invitación.'], $this->json(fn () => $controlador->reenviarInvitacionAjax()));
        }
        $nueva = $this->ultimoEnlace('invitacion_clinica');
        $this->assertNull((new InvitacionPersonal($this->db))->leer($vieja['id'], $vieja['token']), 'El enlace anterior deja de servir.');
        $this->assertNotNull((new InvitacionPersonal($this->db))->leer($nueva['id'], $nueva['token']));

        foreach (array_column($controlador->listarInvitaciones(), 'id_invitacion') as $id) {
            $_POST = ['id_invitacion' => (string) $id];
            $this->assertSame(['success' => true, 'message' => 'Invitación cancelada.'], $this->json(fn () => $controlador->cancelarInvitacionAjax()));
        }
        $this->assertSame([], $controlador->listarInvitaciones());
        $this->assertNull((new Usuario($this->db))->buscarPorDocumento('1000000077'), 'La cuenta nueva sin aceptar se elimina.');
        $this->assertNotNull((new Usuario($this->db))->buscarPorId(DosClinicas::PROPIETARIO), 'La cuenta existente no se toca.');
    }

    public function testElTitularDeUnaCuentaNuevaTambienPuedeRechazar(): void
    {
        $this->invitar(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE, []);
        $enlace = $this->ultimoEnlace('invitacion_personal');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['id' => (string) $enlace['id'], 'token' => $enlace['token'], 'decision' => 'rechazar'];
        ob_start();
        (new IdentidadController($this->db, $this->correo))->enlace('activacion_personal');
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('Invitación rechazada', $html);
        $this->assertNull((new Usuario($this->db))->buscarPorDocumento('1000000077'));
        $this->assertSame([], (new InvitacionPersonal($this->db))->pendientesDeClinica(DosClinicas::NORTE));
    }

    /** Revisión de D2.1: restablecer a alguien con la invitación pendiente lo dice así. */
    public function testRestablecerConLaInvitacionPendienteDiceQueSeReenvio(): void
    {
        $this->invitar(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE, []);
        $id = (int) (new Usuario($this->db))->buscarPorDocumento('1000000077')['id_usuario'];
        $controlador = $this->comoAdmin(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE, 'Clínica Norte');
        $_POST = ['id_usuario' => (string) $id];
        $this->assertSame('Se reenvió la invitación.', $this->json(fn () => $controlador->resetearPasswordAjax())['message']);
    }

    // ── RE-T.2.5: las demás sesiones se cierran ──────────────────────────

    /** Abre «otra sesión» de la persona con la versión actual y la devuelve. */
    private function otraSesion(int $idUsuario): array
    {
        return ['id_usuario' => $idUsuario, 'version_sesion' => (new Usuario($this->db))->versionSesion($idUsuario)];
    }

    /** True si esa sesión sigue sirviendo; false si Security la cierra con 401. */
    private function sigueAbierta(array $sesion): bool
    {
        $usuarios = new Usuario($this->db);
        Security::definirFuenteDeContextos(fn (int $id) => $usuarios->contextosDe($id));
        Security::definirFuenteDeVersionSesion(fn (int $id) => $usuarios->versionSesion($id));
        $guardada = $_SESSION ?? [];
        $metodo = $_SERVER['REQUEST_METHOD'];
        // La siguiente petición de esa sesión: una navegación sin formulario.
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SESSION = $sesion;
        try {
            Security::autorizar('cambiar_password');
            return true;
        } catch (AccesoDenegado $e) {
            $this->assertSame(401, $e->codigo());
            $this->assertSame(['error_login' => CierreSesiones::MENSAJE], $_SESSION);
            return false;
        } finally {
            $_SESSION = $guardada;
            $_SERVER['REQUEST_METHOD'] = $metodo;
            Security::definirFuenteDeContextos(null);
        }
    }

    /** Mi perfil, el portal y cambiar_password usan la misma acción con la sesión del titular. */
    public function testCambiarLaContrasenaPropiaCierraLasOtrasYConservaEsta(): void
    {
        $casos = [
            'Mi perfil' => [DosClinicas::VET_NORTE, Contexto::deClinica(1, 'Clínica Norte', Roles::VETERINARIO)],
            'portal' => [DosClinicas::PROPIETARIO, Contexto::dePropietario()],
            'cambiar_password' => [DosClinicas::ADMIN_SUR, null],
        ];
        foreach ($casos as $camino => [$id, $contexto]) {
            $otra = $this->otraSesion($id);
            $_SESSION = $this->otraSesion($id);
            if ($contexto !== null) {
                Contexto::activar($contexto, 1);
            }
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_POST = ['nueva_password' => self::CLAVE_NUEVA, 'password_actual' => DosClinicas::PASSWORD];
            $r = $this->json(fn () => (new AuthController($this->db, $this->correo, new Turnstile(fn () => true)))->cambiarPasswordAjax());

            $this->assertTrue($r['success'], $camino . ': ' . ($r['message'] ?? ''));
            $this->assertFalse($this->sigueAbierta($otra), "$camino: la otra sesión debe cerrarse");
            $this->assertTrue($this->sigueAbierta($_SESSION), "$camino: la sesión que hizo el cambio sigue");
            $this->assertSame('aviso_password', end($this->correo->enviados)[0], "$camino: se avisa por correo");
        }
    }

    public function testElEnlaceDeRestablecimientoCierraLasSesionesAbiertas(): void
    {
        $otra = $this->otraSesion(DosClinicas::VET_NORTE);
        $token = bin2hex(random_bytes(16));
        $id = (new PasswordReset($this->db))->createToken(DosClinicas::VET_NORTE, 'beto@zooki.test', password_hash($token, PASSWORD_DEFAULT), date('Y-m-d H:i:s', time() + 3600));
        $_SESSION = [];

        $r = (new AuthController($this->db, $this->correo, new Turnstile(fn () => true)))->restablecerConEnlace([
            'token_id' => (string) $id, 'token' => $token, 'password' => self::CLAVE_NUEVA, 'password_confirmation' => self::CLAVE_NUEVA,
        ]);

        $this->assertTrue($r['success'], $r['message']);
        $this->assertFalse($this->sigueAbierta($otra));
        $this->assertSame(['aviso_password', 'beto@zooki.test', 'Beto Norte'], end($this->correo->enviados));
    }

    public function testElRestablecimientoPorElAdministradorCierraSusSesiones(): void
    {
        $otra = $this->otraSesion(DosClinicas::VET_NORTE);
        $controlador = $this->comoAdmin(DosClinicas::ADMIN_NORTE, DosClinicas::NORTE, 'Clínica Norte');
        $_POST = ['id_usuario' => (string) DosClinicas::VET_NORTE];

        $this->assertTrue($this->json(fn () => $controlador->resetearPasswordAjax())['success']);

        $this->assertFalse($this->sigueAbierta($otra));
        $this->assertTrue($this->sigueAbierta($_SESSION), 'La sesión del administrador no cambia.');
        $this->assertSame('restablecimiento', end($this->correo->enviados)[0]);
    }

    public function testConfirmarElCambioDeCorreoCierraLasOtrasSesiones(): void
    {
        // La vista pide un token CSRF: con la sesión ya iniciada (como en la web)
        // session_start() no reemplaza la sesión de esta petición.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $otra = $this->otraSesion(DosClinicas::PROPIETARIO);
        $_SESSION = $this->otraSesion(DosClinicas::PROPIETARIO);
        $solicitud = (new CuentaTitular($this->db))->solicitarCorreo(DosClinicas::PROPIETARIO, ['email' => 'nuevo.fabio@zooki.test', 'password_actual' => DosClinicas::PASSWORD]);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['id' => (string) $solicitud['enlace']['id'], 'token' => $solicitud['enlace']['token']];
        ob_start();
        (new IdentidadController($this->db, $this->correo))->enlace('cambio_correo');
        ob_end_clean();

        $this->assertSame('nuevo.fabio@zooki.test', (new Usuario($this->db))->buscarPorId(DosClinicas::PROPIETARIO)['email']);
        $this->assertFalse($this->sigueAbierta($otra));
        $this->assertTrue($this->sigueAbierta($_SESSION), 'Confirmado desde su propia sesión, esa sigue.');
        $aviso = end($this->correo->enviados);
        $this->assertSame(['personalizado', 'fabio@zooki.test'], [$aviso[0], $aviso[1]]);
        $this->assertStringContainsString('se cerraron las demás sesiones', $aviso[4]);
    }

    public function testUnaSesionSinCambiosSigueYLaDeAntesDelDespliegueTambien(): void
    {
        $this->assertTrue($this->sigueAbierta($this->otraSesion(DosClinicas::VET_NORTE)));
        $this->assertTrue($this->sigueAbierta(['id_usuario' => DosClinicas::VET_NORTE]), 'Sin version_sesion vale 0, la versión inicial.');
    }

    /** RE-T.2.4: una sesión Google abierta no autoriza crear una contraseña. */
    public function testCrearPasswordSinConfirmacionNuevaDeGoogleSeRechaza(): void
    {
        $this->db->exec("UPDATE usuarios SET password = NULL, google_uid = 'google-fabio' WHERE id_usuario = 6");
        $_SESSION = ['id_usuario' => 6, 'version_sesion' => 0, 'login_method' => 'google'];
        $_POST = ['nueva_password' => self::CLAVE_NUEVA];
        $r = $this->json(fn () => (new AuthController($this->db, $this->correo, null, fn () => null))->cambiarPasswordAjax());
        $this->assertFalse($r['success']);
        $this->assertSame(0, (int) (new Usuario($this->db))->buscarPorId(6)['tiene_password']);
        $this->assertSame(0, (new Usuario($this->db))->versionSesion(6));
        $this->assertSame([], $this->correo->enviados);
    }

    public function testCrearPasswordConGoogleAjenoOInvalidoNoCambiaLaCuenta(): void
    {
        $this->db->exec("UPDATE usuarios SET password = NULL, google_uid = 'google-fabio' WHERE id_usuario = 6");
        foreach ([null, ['email' => 'ana@zooki.test']] as $google) {
            $_SESSION = ['id_usuario' => 6, 'version_sesion' => 0];
            $_POST = ['nueva_password' => self::CLAVE_NUEVA, 'access_token' => 'token-nuevo'];
            $r = $this->json(fn () => (new AuthController($this->db, $this->correo, null, fn () => $google))->cambiarPasswordAjax());
            $this->assertFalse($r['success']);
            $this->assertSame(0, (int) (new Usuario($this->db))->buscarPorId(6)['tiene_password']);
            $this->assertSame(0, (new Usuario($this->db))->versionSesion(6));
        }
    }

    /** RE-T.2.4/5: prueba nueva del titular, contraseña creada y demás sesiones cerradas. */
    public function testCrearPasswordConGoogleDelTitularCierraLasOtrasSesiones(): void
    {
        $this->db->exec("UPDATE usuarios SET password = NULL, google_uid = 'google-fabio' WHERE id_usuario = 6");
        $otra = $this->otraSesion(6);
        $_SESSION = $otra;
        $_POST = ['nueva_password' => self::CLAVE_NUEVA, 'access_token' => 'token-nuevo'];
        $recibidos = [];
        $google = static function (string $token) use (&$recibidos): array {
            $recibidos[] = $token;
            return ['email' => 'fabio@zooki.test'];
        };
        $r = $this->json(fn () => (new AuthController($this->db, $this->correo, null, $google))->cambiarPasswordAjax());
        $this->assertTrue($r['success'], $r['message']);
        $this->assertSame(['token-nuevo'], $recibidos);
        $this->assertTrue((new Usuario($this->db))->verificarPassword(6, self::CLAVE_NUEVA));
        $this->assertFalse($this->sigueAbierta($otra));
        $this->assertTrue($this->sigueAbierta($_SESSION));
        $this->assertSame('aviso_password', end($this->correo->enviados)[0]);
    }

    // ── Revisión de D2.1 ─────────────────────────────────────────────────

    public function testMailModoArchivoUsaElHostEfectivoDeLaConexion(): void
    {
        $this->assertSame('127.0.0.1', Database::hostEfectivo('db', 'Windows'));
        $this->assertSame('mysql.interno', Database::hostEfectivo('mysql.interno', 'Windows'));
        $carpeta = sys_get_temp_dir() . '/zooki_d22_' . bin2hex(random_bytes(4));
        $correo = new EmailService(['MAIL_MODO' => 'archivo', 'DB_HOST' => 'db', 'CARPETA_CORREOS' => $carpeta, 'SMTP_FROM' => 'no-reply@zooki.test']);
        if (PHP_OS_FAMILY !== 'Windows' && gethostbyname('db') !== 'db') {
            $this->markTestSkipped('Aquí «db» resuelve: es el contenedor real, no el caso de Windows.');
        }
        $this->assertTrue($correo->guardaEnArchivo(), 'Con DB_HOST=db en Windows la base es 127.0.0.1: local.');
    }

    public function testLaZonaHorariaEsBogotaEnUnSoloLugar(): void
    {
        $anterior = date_default_timezone_get();
        try {
            date_default_timezone_set('Europe/Berlin');
            ZonaHoraria::aplicar();
            $this->assertSame('America/Bogota', date_default_timezone_get());
            $this->assertSame('-05:00', ZonaHoraria::desplazamiento());
            $this->assertSame('-05:00', date('P'));
            $fuente = (string) file_get_contents(__DIR__ . '/../../public/index.php');
            $this->assertMatchesRegularExpression('/ZonaHoraria::aplicar\(\);\s*require_once "\.\.\/helpers\/Sesion\.php";/', $fuente, 'Antes de cualquier fecha.');
            foreach (['scripts/send_reminders.php', 'scripts/vigilar_atenciones.php', 'scripts/backup.php', 'scripts/limpiar_cuentas_pendientes.php', 'public/ver_archivo.php'] as $archivo) {
                $this->assertStringContainsString('ZonaHoraria::aplicar();', (string) file_get_contents(__DIR__ . '/../../' . $archivo), $archivo);
            }
            $this->assertStringContainsString('ZonaHoraria::aplicarEnConexion($this->conn);', (string) file_get_contents(__DIR__ . '/../../config/Database.php'));
        } finally {
            date_default_timezone_set($anterior);
        }
    }

    public function testLosCorreosYaNoTienenLaUrlFijaNiEnvianContrasenas(): void
    {
        foreach (['config/EmailService.php', 'config/EmailService.example.php'] as $archivo) {
            $fuente = (string) file_get_contents(__DIR__ . '/../../' . $archivo);
            $this->assertStringNotContainsString('zooki.secarvajal.com', $fuente, $archivo);
            $this->assertStringNotContainsString('enviarCredencialesUsuario', $fuente, $archivo);
        }
        $this->assertStringNotContainsString('dark-mode.css', (string) file_get_contents(__DIR__ . '/../../views/vet/layout.php'));
    }

    public function testElMensajeDeContrasenaComunSeEntiende(): void
    {
        $mensaje = 'Esa contraseña es muy común y fácil de adivinar. Elige otra.';
        $this->assertSame($mensaje, PoliticaPassword::validar('Password123'));
        $this->assertStringContainsString("'" . $mensaje . "'", (string) file_get_contents(__DIR__ . '/../../public/js/password-policy.js'));
    }
}
