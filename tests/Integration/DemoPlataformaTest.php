<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../helpers/Security.php';
require_once __DIR__ . '/../../models/Usuario.php';
require_once __DIR__ . '/../../controllers/DemoPlataformaController.php';

/**
 * Botón de demo del panel de plataforma (decisión del dueño, 2026-11-03):
 * interruptor apagado → 404; admin, veterinario y propietario → 403;
 * super-administrador con CSRF → ejecuta y queda auditado, sin claves.
 * SemillaDemo necesita MySQL: aquí se sustituye por un ejecutor que anota la llamada.
 */
final class DemoPlataformaTest extends TestCase
{
    private PDO $db;
    private string $motivo = '';

    protected function setUp(): void
    {
        // Csrf::validate abre la sesión si no hay una, y eso vaciaría $_SESSION a mitad de la prueba.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->db = DosClinicas::sqlite();
        Security::definirFuenteDeContextos(fn (int $id): ?array => (new Usuario($this->db))->contextosDe($id));
        Security::definirAlmacenDeIntentos(null);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        putenv(InterruptorDemo::VARIABLE);
        Security::definirFuenteDeContextos(null);
        Security::definirAlmacenDeIntentos(null);
        $_SESSION = [];
        $_POST = [];
        http_response_code(200);
    }

    private function contexto(int $usuario, int $rol, int $clinica = 1): void
    {
        $_SESSION = ['id_usuario' => $usuario, 'csrf_default' => 'seguro'];
        Contexto::activar($rol === Roles::SUPER_ADMIN ? Contexto::dePlataforma() : ($rol === Roles::PROPIETARIO ? Contexto::dePropietario() : Contexto::deClinica($clinica, 'Clínica', $rol)), 1);
        $_POST = ['csrf_token' => 'seguro'];
    }

    private function codigo(string $accion): ?int
    {
        try {
            Security::autorizar($accion);
            return null;
        } catch (AccesoDenegado $e) {
            $this->motivo = $e->getMessage();
            return $e->codigo();
        }
    }

    private function responder(string $accion, callable $ejecutor): array
    {
        http_response_code(200);
        ob_start();
        (new DemoPlataformaController($this->db, $ejecutor))->ejecutar($accion);
        return ['codigo' => http_response_code(), 'cuerpo' => (string) ob_get_clean()];
    }

    public function testApagadoLasRutasNoExistenNiParaElSuperAdministrador(): void
    {
        putenv(InterruptorDemo::VARIABLE . '=0');
        $this->contexto(7, Roles::SUPER_ADMIN);
        foreach (InterruptorDemo::ACCIONES as $accion) {
            $this->assertSame(404, $this->codigo($accion), $accion);
        }
        $_SESSION = [];
        $this->assertSame(404, $this->codigo('plataforma_demo_escena_ajax'), 'Sin sesión tampoco revela que la ruta existe.');
        $llamado = false;
        $respuesta = $this->responder('plataforma_demo_escena_ajax', function () use (&$llamado): array {
            $llamado = true;
            return [];
        });
        $this->assertSame(404, $respuesta['codigo']);
        $this->assertFalse($llamado);
    }

    public function testEncendidoAdministradorVeterinarioYPropietarioReciben403(): void
    {
        putenv(InterruptorDemo::VARIABLE . '=1');
        foreach ([[1, Roles::ADMIN], [2, Roles::VETERINARIO], [6, Roles::PROPIETARIO]] as [$usuario, $rol]) {
            $this->contexto($usuario, $rol);
            foreach (InterruptorDemo::ACCIONES as $accion) {
                $this->assertSame(403, $this->codigo($accion), $accion . ' con rol ' . $rol);
            }
        }
    }

    public function testSuperAdministradorSinCsrfOSinPostRecibe403(): void
    {
        putenv(InterruptorDemo::VARIABLE . '=1');
        $this->contexto(7, Roles::SUPER_ADMIN);
        $_POST = ['csrf_token' => 'otro'];
        $this->assertSame(403, $this->codigo('plataforma_demo_escena_ajax'));
        $_POST = ['csrf_token' => 'seguro'];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->assertSame(403, $this->codigo('plataforma_demo_escena_ajax'), $this->motivo);
    }

    public function testSuperAdministradorConCsrfEjecutaLaEscenaYQuedaAuditada(): void
    {
        putenv(InterruptorDemo::VARIABLE . '=1');
        $this->contexto(7, Roles::SUPER_ADMIN);
        $this->assertNull($this->codigo('plataforma_demo_escena_ajax'));
        $llamadas = [];
        $respuesta = $this->responder('plataforma_demo_escena_ajax', function (string $modo, array $opciones) use (&$llamadas): array {
            $llamadas[] = $modo;
            return ['dia' => '2026-11-03', 'hora' => '10:35', 'en_curso' => 9, 'niveles' => ['Nala' => ['nivel' => 'amarillo', 'revision' => true, 'id_solicitud' => 4]], 'conteos' => ['clinicas' => 5, 'citas' => 12]];
        });
        $this->assertSame(['escena'], $llamadas);
        $this->assertSame(200, $respuesta['codigo']);
        $datos = json_decode($respuesta['cuerpo'], true);
        $this->assertTrue($datos['success']);
        $this->assertSame('10:35', $datos['resultado']['hora']);
        $this->assertSame(['clinicas' => 5, 'citas' => 12], $datos['resultado']['conteos']);
        $this->assertArrayNotHasKey('id_solicitud', $datos['resultado']['niveles']['Nala']);
        $auditoria = $this->db->query("SELECT id_clinica,id_usuario,accion,descripcion,datos_nuevos FROM auditoria_sistema WHERE descripcion LIKE 'Modo demo:%'")->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $auditoria);
        $this->assertNull($auditoria[0]['id_clinica']);
        $this->assertSame(7, (int) $auditoria[0]['id_usuario']);
        $this->assertSame('Modo demo: escena (ok)', $auditoria[0]['descripcion']);
    }

    public function testElErrorDeSemillaDemoSeMuestraTalCualYSeAudita(): void
    {
        putenv(InterruptorDemo::VARIABLE . '=1');
        $this->contexto(7, Roles::SUPER_ADMIN);
        $mensaje = 'La escena solo se permite de lunes a sábado, entre 8:00 y 18:00, dentro del horario aprobado 7:00–20:00.';
        $respuesta = $this->responder('plataforma_demo_escena_ajax', function () use ($mensaje): array {
            throw new RuntimeException($mensaje);
        });
        $this->assertSame(422, $respuesta['codigo']);
        $this->assertSame($mensaje, json_decode($respuesta['cuerpo'], true)['message']);
        $this->assertSame('Modo demo: escena (error)', $this->db->query("SELECT descripcion FROM auditoria_sistema WHERE descripcion LIKE 'Modo demo:%'")->fetchColumn());
    }

    public function testCrearValidaLaClaveYNuncaLaDevuelveNiLaAudita(): void
    {
        putenv(InterruptorDemo::VARIABLE . '=1');
        $this->contexto(7, Roles::SUPER_ADMIN);
        $llamado = false;
        $_POST += ['dia' => '2026-11-03', 'clave' => 'corta'];
        $respuesta = $this->responder('plataforma_demo_crear_ajax', function () use (&$llamado): array {
            $llamado = true;
            return [];
        });
        $this->assertSame(422, $respuesta['codigo']);
        $this->assertSame('clave', json_decode($respuesta['cuerpo'], true)['campo']);
        $this->assertFalse($llamado);

        $clave = 'Sustenta7Kq';
        $_POST['clave'] = $clave;
        $recibidas = [];
        $respuesta = $this->responder('plataforma_demo_crear_ajax', function (string $modo, array $opciones) use (&$recibidas, $clave): array {
            $recibidas = [$modo, $opciones];
            return ['cuentas' => [['rol' => 'Administrador', 'clinica' => 'San Roque', 'correo' => 'zooki.vet+demo@gmail.com', 'clave' => $clave]], 'conteos' => ['clinicas' => 5]];
        });
        $this->assertSame(['si', ['dia' => '2026-11-03', 'clave' => $clave]], $recibidas);
        $this->assertSame(200, $respuesta['codigo'], $respuesta['cuerpo']);
        $this->assertStringNotContainsString($clave, $respuesta['cuerpo']);
        $this->assertSame('zooki.vet+demo@gmail.com', json_decode($respuesta['cuerpo'], true)['resultado']['cuentas'][0]['correo']);
        $auditoria = (string) $this->db->query("SELECT datos_nuevos FROM auditoria_sistema WHERE descripcion='Modo demo: si (ok)'")->fetchColumn();
        $this->assertStringNotContainsString($clave, $auditoria);
    }
}
