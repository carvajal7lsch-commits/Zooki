<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/CarnetPrueba.php';
require_once __DIR__ . '/../../controllers/CarnetController.php';
require_once __DIR__ . '/../../helpers/Csrf.php';

final class CarnetMascotaTest extends TestCase
{
    private PDO $db;
    private string|false $baseAnterior;

    protected function setUp(): void
    {
        $this->db = CarnetPrueba::crear();
        $this->baseAnterior = getenv('APP_URL');
        putenv('APP_URL=https://zooki.example/portal');
        Auditoria::definirProxiesConfiables([]);
        $_SESSION = ['id_usuario' => 6];
        Contexto::activar(Contexto::dePropietario(), 1);
        $_GET = ['id_mascota' => '1'];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        Security::definirFuenteDeContextos(null);
        putenv($this->baseAnterior === false ? 'APP_URL' : 'APP_URL=' . $this->baseAnterior);
        Auditoria::definirProxiesConfiables(null);
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        http_response_code(200);
    }

    public function testSoloDevuelveLaListaPermitidaSinDatosPrivados(): void
    {
        $datos = (new CarnetMascota($this->db))->datosEmergencia(1);
        $this->assertSame(['nombre', 'foto', 'especie', 'raza', 'telefono_propietario', 'alertas', 'clinicas', 'vacunas'], array_keys($datos));
        $this->assertSame('Mestiza', $datos['raza']);
        $this->assertSame('3101234567', $datos['telefono_propietario']);
        $this->assertCount(2, $datos['alertas']);
        $this->assertSame(['tipo', 'descripcion'], array_keys($datos['alertas'][0]));
        $this->assertSame(['nombre', 'telefono'], array_keys($datos['clinicas'][0]));
        $this->assertSame(['3112222222', '6023333333'], array_column($datos['clinicas'], 'telefono'));
        $json = json_encode($datos);
        foreach (['privado@example.org', 'DOCUMENTO-PRIVADO', 'DIRECCION-PRIVADA', 'HISTORIA-PRIVADA', 'ALERTA-INACTIVA-PRIVADA', 'token_carnet', 'id_propietario', 'id_clinica', 'observaciones', 'direccion', 'email', 'documento'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $json);
        }
    }

    public function testVacunasDeAmbasClinicasSoloUltimaAplicacionVigente(): void
    {
        $datos = (new CarnetMascota($this->db))->datosEmergencia(1);
        $this->assertSame(['Hoy', 'Rabia'], array_column($datos['vacunas'], 'nombre_vacuna'));
        $this->assertSame('Clínica Sur', $datos['vacunas'][1]['clinica']);
        $this->assertSame(['nombre_vacuna', 'fecha_aplicacion', 'fecha_proxima_dosis', 'clinica'], array_keys($datos['vacunas'][1]));
        $this->db->exec("UPDATE mascota_clinica SET estado = 'inactivo' WHERE id_clinica = 2");
        $datos = (new CarnetMascota($this->db))->datosEmergencia(1);
        $this->assertCount(1, $datos['clinicas']);
        $this->assertCount(2, $datos['alertas']);
        $this->assertCount(2, $datos['vacunas']);
    }

    public function testRazaDelCatalogoCuandoNoSeIndicaOtra(): void
    {
        $this->db->exec("UPDATE mascotas SET raza_indicada = '' WHERE id_mascota = 1");
        $this->assertSame('Sin raza definida', (new CarnetMascota($this->db))->datosEmergencia(1)['raza']);
    }

    public function testTokenEstableEnlaceAbsolutoYUnicoCarnetMulticlinica(): void
    {
        $modelo = new CarnetPropietario($this->db);
        $a = $modelo->obtener(1);
        $this->assertSame($a, $modelo->obtener(1));
        $this->assertSame('https://zooki.example/portal/index.php?action=carnet&t=' . str_repeat('a', 43), $a['enlace']);
        $this->assertSame(['action' => 'carnet', 't' => $a['token']], $this->parametros($a['enlace']));
    }

    public function testRegenerarInvalidaTokenActualizaFechaYAuditaUnaVezInclusoConDobleEnvio(): void
    {
        $modelo = new CarnetPropietario($this->db);
        $antes = $modelo->obtener(1);
        $nuevo = $modelo->regenerar(1, $antes['token']);
        $doble = $modelo->regenerar(1, $antes['token']);
        $this->assertTrue($nuevo['regenerado']);
        $this->assertFalse($doble['regenerado']);
        $this->assertSame($nuevo['token'], $doble['token']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/D', $nuevo['token']);
        $this->assertNotSame($antes['token'], $nuevo['token']);
        $consulta = $this->db->prepare('SELECT COUNT(*) FROM mascotas WHERE token_carnet = ?');
        $consulta->execute([$antes['token']]);
        $this->assertSame(0, (int) $consulta->fetchColumn());
        $this->assertNotSame('2020-01-01 00:00:00', $this->db->query('SELECT token_carnet_fecha FROM mascotas WHERE id_mascota = 1')->fetchColumn());
        $auditorias = $this->db->query("SELECT * FROM auditoria_sistema WHERE accion = 'UPDATE'")->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $auditorias);
        $this->assertSame(6, (int) $auditorias[0]['id_usuario']);
        $this->assertNull($auditorias[0]['id_clinica']);
        $this->assertStringContainsString('RE-5.10.5', $auditorias[0]['descripcion']);
        $this->assertStringNotContainsString($nuevo['token'], json_encode($auditorias));
        $this->assertStringNotContainsString($antes['token'], json_encode($auditorias));
    }

    public function testFalloDeAuditoriaRevierteTokenYFecha(): void
    {
        $this->db->exec("CREATE TRIGGER negar_auditoria BEFORE INSERT ON auditoria_sistema BEGIN SELECT RAISE(ABORT, 'Auditoria no disponible'); END");
        try {
            (new CarnetPropietario($this->db))->regenerar(1, str_repeat('a', 43));
            $this->fail('Debía fallar y revertir.');
        } catch (RuntimeException $error) {
            $this->assertSame(str_repeat('a', 43), $this->db->query('SELECT token_carnet FROM mascotas WHERE id_mascota = 1')->fetchColumn());
            $this->assertSame('2020-01-01 00:00:00', $this->db->query('SELECT token_carnet_fecha FROM mascotas WHERE id_mascota = 1')->fetchColumn());
        }
    }

    public function testNingunaRutaLeeNiRegeneraLaMascotaAjena(): void
    {
        $_GET = ['id_mascota' => '2'];
        $_POST = ['id_mascota' => '2', 'token_visto' => str_repeat('m', 43)];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $controlador = new CarnetController($this->db);
        foreach (['vistaAjax', 'svg', 'imprimir', 'regenerarAjax'] as $metodo) {
            try {
                $this->salida(fn () => $controlador->$metodo());
                $this->fail('Debió negar ' . $metodo);
            } catch (AccesoDenegado $error) {
                $this->assertSame(403, $error->codigo());
            }
        }
        $this->assertSame(str_repeat('m', 43), $this->db->query('SELECT token_carnet FROM mascotas WHERE id_mascota = 2')->fetchColumn());
    }

    public function testInactivaSinCarnetYProvisionalSinTitularNoRevelanDatos(): void
    {
        $this->db->exec('UPDATE mascotas SET estado = 0 WHERE id_mascota = 1');
        $modelo = new CarnetPropietario($this->db);
        $this->assertFalse($modelo->obtener(1)['disponible']);
        $this->assertNull((new CarnetMascota($this->db))->datosEmergencia(1));
        $html = json_decode($this->salida(fn () => (new CarnetController($this->db))->vistaAjax()), true)['html'];
        $this->assertStringContainsString('inactiva', $html);
        $this->assertStringNotContainsString('data-token', $html);
        $this->salida(fn () => (new CarnetController($this->db))->svg());
        $this->assertSame(404, http_response_code());
        try {
            $modelo->regenerar(1, str_repeat('a', 43));
            $this->fail('No debe regenerar inactiva.');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString('inactiva', $error->getMessage());
        }
        $this->db->exec('UPDATE mascotas SET estado = 1, carnet_activo = 0 WHERE id_mascota = 1');
        $this->assertFalse($modelo->obtener(1)['disponible']);
        $this->db->exec('UPDATE mascotas SET carnet_activo = 1, id_propietario = NULL WHERE id_mascota = 1');
        $this->assertNull((new CarnetMascota($this->db))->datosEmergencia(1));
        $this->assertStringContainsString('provisional', CarnetMascota::motivoNoDisponible(['id_propietario' => null]));
    }

    public function testVistaEscapaDatosYLaImpresionReutilizaLaTarjetaSinCodigoEnLinea(): void
    {
        $controlador = new CarnetController($this->db);
        $html = json_decode($this->salida(fn () => $controlador->vistaAjax()), true)['html'];
        $this->assertStringContainsString('Penicilina &lt;urgente&gt;', $html);
        $this->assertStringContainsString('vigencia sin confirmar', $html);
        $this->assertStringNotContainsString('Penicilina <urgente>', $html);
        $impresion = $this->salida(fn () => $controlador->imprimir());
        $this->assertStringContainsString('carnet-tarjeta', $impresion);
        $this->assertStringContainsString('data-carnet-imprimir', $impresion);
        $this->assertStringNotContainsString('data-carnet-regenerar', $impresion);
        $this->assertDoesNotMatchRegularExpression('/\s(?:style|on\w+)\s*=|<script(?![^>]*src=)[^>]*>\s*\S/i', $impresion);
    }

    public function testSvgSeGeneraEnServidorYRequiereContextoPropietario(): void
    {
        $svg = $this->salida(fn () => (new CarnetController($this->db))->svg());
        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('<path', $svg);
        $_SESSION = ['id_usuario' => DosClinicas::VET_NORTE];
        Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::VETERINARIO), 1);
        $this->expectException(AccesoDenegado::class);
        (new CarnetPropietario($this->db))->obtener(1);
    }

    public function testRegeneracionValidaMetodoIdYTokenEnServidor(): void
    {
        $controlador = new CarnetController($this->db);
        $this->salida(fn () => $controlador->regenerarAjax());
        $this->assertSame(405, http_response_code());
        $_SERVER['REQUEST_METHOD'] = 'POST';
        foreach ([[], ['id_mascota' => '1', 'token_visto' => ['invalido']], ['id_mascota' => '-1', 'token_visto' => str_repeat('a', 43)]] as $entrada) {
            $_POST = $entrada;
            $this->salida(fn () => $controlador->regenerarAjax());
            $this->assertSame(422, http_response_code());
        }
    }

    public function testLaRutaDeRegenerarExigeCsrfAntesDeEscribir(): void
    {
        $token = Csrf::token();
        $_SESSION['id_usuario'] = 6;
        Contexto::activar(Contexto::dePropietario(), 1);
        Security::definirFuenteDeContextos(static fn (): array => [Contexto::dePropietario()]);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        try {
            Security::autorizar('portal_regenerar_carnet_ajax');
            $this->fail('No debe permitir escribir sin CSRF.');
        } catch (AccesoDenegado $error) {
            $this->assertSame(403, $error->codigo());
            $this->assertStringContainsString('Token', $error->getMessage());
        }
        $_POST['csrf_token'] = $token;
        Security::autorizar('portal_regenerar_carnet_ajax');
        $this->assertTrue(true);
    }

    private function parametros(string $url): array
    {
        parse_str(parse_url($url, PHP_URL_QUERY), $parametros);
        return $parametros;
    }

    private function salida(callable $accion): string
    {
        ob_start();
        try {
            $accion();
            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
