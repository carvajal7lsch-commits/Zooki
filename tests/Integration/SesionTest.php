<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../helpers/Sesion.php';
require_once __DIR__ . '/../../helpers/InicioSesion.php';
require_once __DIR__ . '/../../helpers/Security.php';
require_once __DIR__ . '/../../controllers/ContextoController.php';

/** HU-T.16 (RN-G17, RN-G05) — Cookie, inactividad, 401 y regeneración del identificador. */
final class SesionTest extends TestCase
{
    private PDO $db;
    private int $regeneraciones = 0;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        $usuarios = new Usuario($this->db);
        Security::definirFuenteDeContextos(static fn (int $id): ?array => $usuarios->contextosDe($id));
        Security::definirAuditoria(new Auditoria($this->db));
        Sesion::observarRegeneracion(function (): void {
            $this->regeneraciones++;
        });
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
        Sesion::observarRegeneracion(null);
        $_SESSION = [];
    }

    public function testLaCookieEsDeSesionHttpOnlyLaxYSecureSoloBajoHttps(): void
    {
        $https = Sesion::parametrosCookie(true);
        $this->assertSame(0, $https['lifetime'], 'Se borra al cerrar el navegador.');
        $this->assertTrue($https['httponly']);
        $this->assertSame('Lax', $https['samesite']);
        $this->assertTrue($https['secure']);
        $this->assertFalse(Sesion::parametrosCookie(false)['secure']);

        $this->assertTrue(Sesion::esHttps(['HTTPS' => 'on']));
        $this->assertTrue(Sesion::esHttps(['HTTP_X_FORWARDED_PROTO' => 'https']));
        $this->assertFalse(Sesion::esHttps(['HTTPS' => 'off']));
    }

    public function testTreintaMinutosSinActividadCierranLaSesionConElMotivo(): void
    {
        $ahora = 1_800_000_000;
        $sesion = ['id_usuario' => 1, 'contexto' => Contexto::deClinica(1, 'Clínica Norte', Roles::ADMIN), 'ultima_actividad' => $ahora - 1799];
        $this->assertNull(Sesion::revisarInactividad($sesion, $ahora), 'A los 29:59 sigue abierta.');
        $this->assertSame($ahora, $sesion['ultima_actividad'], 'Cada petición cuenta como actividad.');

        $vencida = Sesion::revisarInactividad($sesion, $ahora + 1801);
        $this->assertSame(['id_usuario' => 1, 'id_clinica' => 1], $vencida);
        $this->assertArrayNotHasKey('id_usuario', $sesion);
        $this->assertArrayNotHasKey('contexto', $sesion);
        $this->assertSame(Sesion::MENSAJE_INACTIVIDAD, $sesion['error_login']);
    }

    public function testSinIdentidadNoHayCierrePorInactividad(): void
    {
        $sesion = ['ultima_actividad' => 1];
        $this->assertNull(Sesion::revisarInactividad($sesion, 1_800_000_000));
    }

    public function testDespuesDeVencerAjaxRecibe401YLaPaginaVaAlLogin(): void
    {
        $_SESSION = ['id_usuario' => 1, 'ultima_actividad' => time() - 3600];
        Sesion::revisarInactividad($_SESSION, time());

        foreach (['get_pendientes_ajax', 'admin_panel'] as $accion) {
            try {
                Security::autorizar($accion);
                $this->fail("$accion pasó con la sesión vencida.");
            } catch (AccesoDenegado $e) {
                $this->assertSame(401, $e->codigo());
                $this->assertSame('login', $e->redirigir());
            }
        }
    }

    public function testElCierrePorInactividadQuedaEnAuditoria(): void
    {
        Sesion::auditarCierre(new Auditoria($this->db), DosClinicas::ADMIN_NORTE, DosClinicas::NORTE);

        $fila = $this->db->query("SELECT * FROM auditoria_sistema WHERE descripcion LIKE '%inactividad%'")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('LOGOUT', $fila['accion']);
        $this->assertSame(DosClinicas::ADMIN_NORTE, (int) $fila['id_usuario']);
        $this->assertSame(DosClinicas::NORTE, (int) $fila['id_clinica']);
        $this->assertSame('127.0.0.1', $fila['ip_address']);
        $this->assertNotEmpty($fila['fecha_hora']);
    }

    public function testElIdentificadorSeRegeneraAlIniciarSesionYAlCambiarDeContexto(): void
    {
        $elena = (new Usuario($this->db))->buscarPorId(DosClinicas::DOBLE);
        (new InicioSesion($this->db))->abrir($elena, 'password');
        $this->assertSame(1, $this->regeneraciones, 'Al iniciar sesión.');

        $destino = (new ContextoController($this->db))->aplicarCambio('clinica:2:1');
        $this->assertSame(2, $this->regeneraciones, 'Al cambiar de contexto.');
        $this->assertSame('admin_panel', $destino);
        $this->assertSame(DosClinicas::SUR, Contexto::clinicaActiva());
    }

    public function testIndexYVerArchivoUsanElMismoHelperDeSesion(): void
    {
        foreach (['public/index.php', 'public/ver_archivo.php'] as $archivo) {
            $codigo = file_get_contents(__DIR__ . '/../../' . $archivo);
            $this->assertStringContainsString('Sesion::iniciar()', $codigo, $archivo);
            $this->assertStringNotContainsString('session_set_cookie_params', $codigo, $archivo);
            $this->assertStringNotContainsString('session_start()', $codigo, $archivo);
        }
    }
}
