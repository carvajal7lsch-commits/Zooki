<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../helpers/Security.php';
require_once __DIR__ . '/../../models/Usuario.php';
require_once __DIR__ . '/../../models/Auditoria.php';
require_once __DIR__ . '/../../controllers/UsuarioController.php';
require_once __DIR__ . '/../../controllers/NotificacionController.php';

/**
 * HU-T.15 y HU-T.17 — Aislamiento por clínica y permisos del contexto activo,
 * sobre el fixture de dos clínicas (RE-T.15.1–5, RE-T.17.3, RN-G13, RN-G18,
 * RN-004).
 */
class SeguridadClinicaTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        $usuarios = new Usuario($this->db);
        Security::definirFuenteDeContextos(static fn (int $id): ?array => $usuarios->contextosDe($id));
        Security::definirAuditoria(new Auditoria($this->db));

        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        Security::definirFuenteDeContextos(null);
        Security::definirAuditoria(false);
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
    }

    /** Abre la sesión de la persona en el contexto indicado (o sin contexto). */
    private function entrarComo(int $idUsuario, ?string $clave): void
    {
        $_SESSION = ['id_usuario' => $idUsuario, 'usuario_nombre' => 'Prueba', 'debe_cambiar_password' => 0];
        if ($clave !== null) {
            $disponibles = (new Usuario($this->db))->contextosDe($idUsuario);
            Contexto::activar(Contexto::buscar($disponibles, $clave), count($disponibles));
        }
    }

    /** Código de la denegación, o 0 si la acción pasa. */
    private function resultado(string $accion): int
    {
        try {
            Security::autorizar($accion);
            return 0;
        } catch (AccesoDenegado $e) {
            return $e->codigo();
        }
    }

    private function denegacion(string $accion): AccesoDenegado
    {
        try {
            Security::autorizar($accion);
        } catch (AccesoDenegado $e) {
            return $e;
        }
        $this->fail("La acción $accion debía denegarse.");
    }

    private function auditoriasDenegadas(): array
    {
        return $this->db->query("SELECT id_clinica, id_usuario, tabla_afectada, registro_id
                                 FROM auditoria_sistema WHERE descripcion LIKE 'Acceso denegado%' ORDER BY id_auditoria")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Ejecuta un controlador y descarta lo que imprime. */
    private function silencioso(callable $accion): void
    {
        ob_start();
        try {
            $accion();
        } finally {
            ob_end_clean();
        }
    }

    // ── Sesión y contexto ────────────────────────────────────────────────

    public function testSinSesionLaPeticionNoPasa(): void
    {
        $this->assertSame(401, $this->resultado('admin_usuarios'));
        $this->assertSame(0, $this->resultado('login'), 'Lo público sigue abierto');
    }

    /** RE-T.17.2: con identidad pero sin contexto, solo se puede elegir uno. */
    public function testSinContextoSeLlevaAlSelector(): void
    {
        $this->entrarComo(DosClinicas::DOBLE, null);

        $e = $this->denegacion('dashboard');
        $this->assertSame('seleccionar_contexto', $e->redirigir());
        $this->assertSame(0, $this->resultado('seleccionar_contexto'));
        $this->assertSame(0, $this->resultado('logout'));
    }

    /**
     * RE-T.17.3 / RN-G18 — La misma persona en tres contextos: cada uno da sus
     * permisos y ninguno los del otro.
     */
    public function testDosContextosNoMezclanPermisos(): void
    {
        $this->entrarComo(DosClinicas::DOBLE, 'propietario');
        $this->assertSame(0, $this->resultado('portal_propietario'));
        $this->assertSame(403, $this->resultado('admin_usuarios'), 'Como propietaria no administra la clínica');
        $this->assertSame(403, $this->resultado('vet_area'));

        $this->entrarComo(DosClinicas::DOBLE, 'clinica:2:1');
        $this->assertSame(0, $this->resultado('admin_usuarios'));
        $this->assertSame(403, $this->resultado('portal_propietario'), 'Como administradora no entra al portal');
        $this->assertSame(403, $this->resultado('vet_area'), 'En Sur no es veterinaria');

        $this->entrarComo(DosClinicas::DOBLE, 'clinica:1:2');
        $this->assertSame(0, $this->resultado('vet_area'));
        $this->assertSame(403, $this->resultado('admin_usuarios'), 'En Norte no es administradora');
    }

    /** RN-G08: si le retiran el rol con la sesión abierta, pierde ese contexto en la siguiente petición. */
    public function testUnContextoRetiradoSeQuitaDeLaSesion(): void
    {
        $this->entrarComo(DosClinicas::DOBLE, 'clinica:2:1');
        (new Usuario($this->db))->cambiarEstadoEnClinica(DosClinicas::DOBLE, DosClinicas::SUR, false);

        $this->assertSame('seleccionar_contexto', $this->denegacion('admin_usuarios')->redirigir());
        $this->assertNull(Contexto::actual());
    }

    public function testUnaCuentaInactivadaPierdeLaSesion(): void
    {
        $this->entrarComo(DosClinicas::ADMIN_NORTE, 'clinica:1:1');
        $this->db->exec('UPDATE usuarios SET estado = 0 WHERE id_usuario = 1');

        $this->assertSame(401, $this->resultado('admin_usuarios'));
        $this->assertSame([], $_SESSION);
    }

    // ── Aislamiento por clínica (HU-T.15) ────────────────────────────────

    /** RE-T.15.2 / RE-T.15.3: una petición que nombra otra clínica da 403 y queda en auditoría. */
    public function testUnaPeticionAOtraClinicaDa403YQuedaEnAuditoria(): void
    {
        $this->entrarComo(DosClinicas::ADMIN_NORTE, 'clinica:1:1');
        $_GET['id_clinica'] = (string) DosClinicas::SUR;

        $this->assertSame(403, $this->resultado('admin_usuarios'));

        $auditadas = $this->auditoriasDenegadas();
        $this->assertCount(1, $auditadas);
        $this->assertSame(
            [1, 1, 'clinicas', '2'],
            [(int) $auditadas[0]['id_clinica'], (int) $auditadas[0]['id_usuario'], $auditadas[0]['tabla_afectada'], $auditadas[0]['registro_id']]
        );

        $_GET['id_clinica'] = (string) DosClinicas::NORTE;
        $this->assertSame(0, $this->resultado('admin_usuarios'), 'La propia clínica sí pasa');
    }

    /** RE-T.15.1 / RE-T.15.2: un recurso de otra clínica (una persona del personal de Sur) da 403 y se audita. */
    public function testElAdministradorNoVeNiModificaPersonalDeOtraClinica(): void
    {
        $this->entrarComo(DosClinicas::ADMIN_NORTE, 'clinica:1:1');
        $controlador = new UsuarioController($this->db, new class { public function enviarCredencialesUsuario(...$a) { return true; } });

        $_GET['id_usuario'] = (string) DosClinicas::VET_SUR;
        try {
            $this->silencioso(fn () => $controlador->getUsuarioAjax());
            $this->fail('Debió denegarse la consulta de personal de otra clínica.');
        } catch (AccesoDenegado $e) {
            $this->assertSame(403, $e->codigo());
        }

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['id_usuario' => (string) DosClinicas::VET_SUR, 'estado' => '0'];
        try {
            $this->silencioso(fn () => $controlador->cambiarEstadoAjax());
            $this->fail('Debió denegarse el cambio de estado en otra clínica.');
        } catch (AccesoDenegado $e) {
            $this->assertSame(403, $e->codigo());
        }

        $estado = $this->db->query("SELECT estado FROM usuario_clinica WHERE id_usuario = 4 AND id_clinica = 2")->fetchColumn();
        $this->assertSame('activo', $estado, 'El personal de Sur no cambió');

        $auditadas = $this->auditoriasDenegadas();
        $this->assertCount(2, $auditadas);
        foreach ($auditadas as $fila) {
            $this->assertSame([1, 'usuarios', '4'], [(int) $fila['id_clinica'], $fila['tabla_afectada'], $fila['registro_id']]);
        }
    }

    /** El listado del personal es solo el de la clínica activa. */
    public function testElListadoDePersonalEsDeLaClinicaActiva(): void
    {
        $this->entrarComo(DosClinicas::ADMIN_NORTE, 'clinica:1:1');
        $controlador = new UsuarioController($this->db);

        $this->assertSame([1, 2, 5, 8], array_map('intval', array_column($controlador->listar(), 'id_usuario')));
        $this->assertSame([5, 6], array_map('intval', array_column($controlador->listarPropietarios(), 'id_usuario')));
    }

    /** RN-G13: una notificación de otra clínica no se puede marcar, aunque el rol coincida. */
    public function testUnaNotificacionDeOtraClinicaDa403(): void
    {
        $this->db->exec("INSERT INTO notificaciones_internas (id, id_clinica, id_rol_destino, tipo, titulo, mensaje) VALUES (50, 2, 1, 'X', 'Sur', 'Para administradores de Sur')");
        $this->entrarComo(DosClinicas::ADMIN_NORTE, 'clinica:1:1');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['id' => '50'];

        try {
            $this->silencioso(fn () => (new NotificacionController($this->db))->marcarLeida());
            $this->fail('Debió denegarse.');
        } catch (AccesoDenegado $e) {
            $this->assertSame(403, $e->codigo());
        }
        $this->assertSame(0, (int) $this->db->query('SELECT leida FROM notificaciones_internas WHERE id = 50')->fetchColumn());
    }

    /** RE-T.15.4 / RN-004: el super-administrador no entra a datos clínicos por serlo. */
    public function testElSuperAdministradorNoEntraADatosClinicos(): void
    {
        $this->entrarComo(DosClinicas::SUPER_ADMIN, 'plataforma');

        $this->assertSame(0, $this->resultado('plataforma_inicio'));
        foreach (['listar_historial_ajax', 'vet_pacientes', 'get_mascota_ajax', 'admin_usuarios', 'admin_auditoria', 'portal_propietario'] as $accion) {
            $this->assertSame(403, $this->resultado($accion), "$accion no es del super-administrador");
        }
    }

    // ── Reglas que se mantienen de la v1 ────────────────────────────────

    /** T-15: una acción que no está en la matriz se deniega a todos. */
    public function testUnaAccionSinEntradaEnLaMatrizSeDeniega(): void
    {
        $this->entrarComo(DosClinicas::ADMIN_NORTE, 'clinica:1:1');
        $this->assertSame(403, $this->resultado('accion_inventada'));
    }

    /** T-05: con contraseña temporal solo se puede cambiarla o salir. */
    public function testConPasswordTemporalSoloSePuedeCambiarla(): void
    {
        $this->entrarComo(DosClinicas::ADMIN_NORTE, 'clinica:1:1');
        $_SESSION['debe_cambiar_password'] = 1;

        $this->assertSame('cambiar_password', $this->denegacion('admin_usuarios')->redirigir());
        $this->assertSame(0, $this->resultado('cambiar_password'));
    }

    public function testUnPostSinTokenCsrfSeRechaza(): void
    {
        $this->entrarComo(DosClinicas::ADMIN_NORTE, 'clinica:1:1');
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->assertSame(403, $this->resultado('cambiar_estado_usuario_ajax'));
    }
}
