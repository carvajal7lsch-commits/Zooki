<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../helpers/InicioSesion.php';
require_once __DIR__ . '/../../helpers/Security.php';
require_once __DIR__ . '/../../controllers/CuentaController.php';

/**
 * HU-T.19 (RN-G19) — Versión vigente en un solo lugar, prueba de la
 * aceptación y re-aceptación antes de continuar. El super-administrador
 * está exento (revisión de B, B.5-2).
 */
final class PoliticaDatosTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        $usuarios = new Usuario($this->db);
        Security::definirFuenteDeContextos(static fn (int $id): ?array => $usuarios->contextosDe($id));
        Security::definirAuditoria(new Auditoria($this->db));
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        Security::definirFuenteDeContextos(null);
        Security::definirAuditoria(false);
        $_SESSION = [];
        $_POST = [];
    }

    private function entrar(int $idUsuario): string
    {
        $usuario = (new Usuario($this->db))->buscarPorId($idUsuario);
        return (new InicioSesion($this->db))->abrir($usuario, 'password');
    }

    private function aceptar(int $idUsuario, string $version): void
    {
        $this->db->prepare("INSERT INTO consentimientos_datos (id_usuario, version_politica, medio, ip_address) VALUES (?, ?, 'formulario', '127.0.0.1')")
            ->execute([$idUsuario, $version]);
    }

    private function denegacion(string $accion): ?AccesoDenegado
    {
        try {
            Security::autorizar($accion);
            return null;
        } catch (AccesoDenegado $e) {
            return $e;
        }
    }

    public function testSinAceptarLaVersionVigenteNoSeContinuaHastaAceptarla(): void
    {
        $this->aceptar(DosClinicas::ADMIN_NORTE, '2020-01-01');

        $this->assertSame('aceptar_politica', $this->entrar(DosClinicas::ADMIN_NORTE));

        foreach (['admin_panel', 'get_pendientes_ajax', 'seleccionar_contexto', 'cambiar_password'] as $accion) {
            $e = $this->denegacion($accion);
            $this->assertNotNull($e, "$accion pasó sin aceptar la política.");
            $this->assertSame('aceptar_politica', $e->redirigir());
        }
        $this->assertNull($this->denegacion('aceptar_politica'));
        $this->assertNull($this->denegacion('logout'));
    }

    public function testAceptarGuardaLaPruebaYLiberaLaSesion(): void
    {
        $this->entrar(DosClinicas::ADMIN_NORTE);
        $controlador = new CuentaController($this->db);

        try {
            $controlador->registrarAceptacion(DosClinicas::ADMIN_NORTE, '', '127.0.0.1');
            $this->fail('Se aceptó sin marcar la casilla.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM consentimientos_datos')->fetchColumn());
        }

        $controlador->registrarAceptacion(DosClinicas::ADMIN_NORTE, '1', '10.0.0.8');

        $prueba = $this->db->query('SELECT * FROM consentimientos_datos')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(DosClinicas::ADMIN_NORTE, (int) $prueba['id_usuario']);
        $this->assertSame(PoliticaDatos::VERSION, $prueba['version_politica']);
        $this->assertSame(PoliticaDatos::MEDIO_FORMULARIO, $prueba['medio']);
        $this->assertSame('10.0.0.8', $prueba['ip_address']);
        $this->assertNotEmpty($prueba['fecha']);
        $this->assertNull($this->denegacion('admin_panel'));
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE tabla_afectada = 'consentimientos_datos'")->fetchColumn());
    }

    public function testConLaVersionVigenteAceptadaEntraDirecto(): void
    {
        $this->aceptar(DosClinicas::ADMIN_NORTE, PoliticaDatos::VERSION);

        $this->assertSame('admin_panel', $this->entrar(DosClinicas::ADMIN_NORTE));
        $this->assertSame(0, $_SESSION['debe_aceptar_politica']);
    }

    public function testElSuperAdministradorEstaExento(): void
    {
        $this->assertSame('plataforma_inicio', $this->entrar(DosClinicas::SUPER_ADMIN));
        $this->assertSame(0, $_SESSION['debe_aceptar_politica']);
        $this->assertNull($this->denegacion('plataforma_inicio'));
    }

    public function testLaVersionViveEnUnSoloLugar(): void
    {
        $this->assertLessThanOrEqual(20, strlen(PoliticaDatos::VERSION), 'Cabe en version_politica.');
        $raiz = __DIR__ . '/../..';
        $this->assertStringContainsString('PoliticaDatos::VERSION', file_get_contents($raiz . '/views/legal/privacidad.php'));
        $this->assertStringContainsString('PoliticaDatos::VERSION', file_get_contents($raiz . '/controllers/PropietarioController.php'));
        $this->assertStringNotContainsString('versionPolitica', file_get_contents($raiz . '/controllers/PropietarioController.php'));
    }
}
