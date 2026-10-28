<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/PlataformaFixture.php';
require_once __DIR__ . '/../Support/ConocimientoPrueba.php';
require_once __DIR__ . '/../../models/IntentoLogin.php';
require_once __DIR__ . '/../../models/Usuario.php';
require_once __DIR__ . '/../../helpers/Security.php';
require_once __DIR__ . '/../../models/GrafoConocimiento.php';
require_once __DIR__ . '/../../controllers/GrafoConocimientoController.php';

/** M2 C6, HU-2.12: mapa del conocimiento en el panel de plataforma (RE-2.12.1 a RE-2.12.6). */
final class GrafoConocimientoTest extends TestCase
{
    private const ACCIONES = ['plataforma_grafo', 'obtener_grafo_conocimiento_ajax'];
    private const MANIFIESTO = __DIR__ . '/../Fixtures/conocimiento/manifiesto.json';

    private ?string $carpeta = null;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        Security::definirFuenteDeContextos(null);
        Security::definirAlmacenDeIntentos(null);
        if ($this->carpeta !== null) {
            ConocimientoPrueba::borrar($this->carpeta);
        }
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        http_response_code(200);
    }

    private function conPermisos(): PDO
    {
        $db = PlataformaFixture::sqlite();
        // Aunque figure como administradora de Norte, a una superadministradora no se le ofrece ese contexto (Usuario::contextosDe).
        $db->exec('INSERT INTO usuario_clinica (id_usuario, id_clinica, id_rol, estado) VALUES (' . DosClinicas::SUPER_ADMIN . ", 1, 1, 'activo')");
        $usuarios = new Usuario($db);
        Security::definirFuenteDeContextos(fn (int $id): ?array => $usuarios->contextosDe($id));
        Security::definirFuenteDeVersionSesion(null);
        Security::definirAlmacenDeIntentos(new IntentoLogin($db));
        return $db;
    }

    private function conocimiento(): PDO
    {
        $db = ConocimientoPrueba::sqlite();
        $this->carpeta = ConocimientoPrueba::copiar();
        (new CargadorConocimiento($db))->cargar($this->carpeta);
        return $db;
    }

    private function respuesta(GrafoConocimientoController $controlador): array
    {
        ob_start();
        $controlador->datosAjax();
        return json_decode((string) ob_get_clean(), true);
    }

    public function testVistaYEndpointRechazanCualquierContextoQueNoSeaPlataforma(): void
    {
        $this->conPermisos();
        $contextos = [
            'administrador de clínica' => [DosClinicas::ADMIN_NORTE, Contexto::deClinica(1, 'Clínica Norte', 1)],
            'veterinario' => [DosClinicas::VET_NORTE, Contexto::deClinica(1, 'Clínica Norte', 2)],
            'propietario' => [DosClinicas::PROPIETARIO, Contexto::dePropietario()],
            'superadministradora con un contexto de clínica en la sesión' => [DosClinicas::SUPER_ADMIN, Contexto::deClinica(1, 'Clínica Norte', 1)],
        ];
        foreach ($contextos as $nombre => [$usuario, $contexto]) {
            $_SESSION = ['id_usuario' => $usuario];
            Contexto::activar($contexto, 2);
            foreach (self::ACCIONES as $accion) {
                try {
                    Security::autorizar($accion);
                    $this->fail($nombre . ' accedió a ' . $accion);
                } catch (AccesoDenegado $e) {
                    $this->assertSame(403, $e->codigo(), $nombre . ' ' . $accion);
                }
            }
        }
        // El contexto de clínica que no le corresponde se descarta: no hereda los permisos de plataforma.
        $this->assertNull(Contexto::actual());
        PlataformaFixture::plataforma();
        foreach (self::ACCIONES as $accion) {
            Security::autorizar($accion);
        }
        $this->assertSame(Contexto::PLATAFORMA, Contexto::actual()['tipo']);
    }

    public function testRespuestaConVersionLimitacionesCoberturaYReglasEnSusNodos(): void
    {
        $controlador = new GrafoConocimientoController($this->conocimiento(), self::MANIFIESTO);
        $respuesta = $this->respuesta($controlador);
        $this->assertSame(200, http_response_code());
        $this->assertTrue($respuesta['success']);
        $datos = $respuesta['datos'];
        $this->assertFalse($datos['vacio']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $datos['version']['hash']);
        $this->assertSame('Conjunto sintético exclusivo de tests; no es conocimiento clínico.', $datos['limitaciones']);
        $this->assertSame([['codigo' => 'especie.prueba', 'etiqueta' => 'Canino de prueba', 'diagnosticos' => 'sin_cobertura', 'prescripcion' => 'sin_cobertura', 'triage' => 'parcial']], $datos['cobertura']);
        $nodos = array_column($datos['nodes'], null, 'codigo');
        $this->assertCount(6, $nodos);
        $this->assertSame(['regla.prueba'], $nodos['sintoma.prueba']['reglas']);
        $this->assertSame(['regla.prueba'], $nodos['especie.prueba']['reglas']);
        $this->assertSame(['especie.prueba'], $nodos['raza.prueba']['especies'], 'La raza se ubica en su especie del catálogo.');
        $this->assertSame(['Síntoma de prueba'], $nodos['sintoma.prueba']['sinonimos']);
        $this->assertSame(['sintoma.prueba', 'especie.prueba'], $datos['reglas'][0]['nodos']);
        $this->assertSame('arista.prueba', $datos['edges'][0]['codigo']);
        $this->assertSame('sintoma.prueba', $datos['edges'][0]['origen']);
        $fuente = $nodos['sintoma.prueba']['fuentes'][0];
        $this->assertSame(['entidad', 'titulo', 'url', 'localizador', 'fundamento', 'fecha_consulta'], array_keys($fuente));
        $this->assertSame('pendiente', $nodos['sintoma.prueba']['estado_validacion']);
    }

    public function testSoloElementosActivosYSinClavesDePacientes(): void
    {
        $db = $this->conocimiento();
        ConocimientoPrueba::cambiar($this->carpeta, 'nodos', static function (array &$nodos): void {
            array_pop($nodos);
        });
        (new CargadorConocimiento($db))->cargar($this->carpeta);
        // Una arista hacia un nodo inactivo tampoco se muestra.
        $db->exec("UPDATE grafo_nodos SET activo = 0 WHERE codigo = 'diagnostico.prueba'");
        $mapa = (new GrafoConocimiento($db, self::MANIFIESTO))->mapa();
        $codigos = array_column($mapa['nodes'], 'codigo');
        $this->assertNotContains('sustancia.prueba', $codigos);
        $this->assertNotContains('diagnostico.prueba', $codigos);
        $this->assertSame([], $mapa['edges']);
        $json = json_encode($mapa);
        foreach (['id_mascota', 'id_consulta', 'id_cita', 'id_clinica', 'id_tratamiento', 'id_propietario', 'id_usuario', 'id_nodo', 'id_alerta'] as $clave) {
            $this->assertStringNotContainsString('"' . $clave . '"', $json);
        }
    }

    public function testElModeloNoConsultaTablasDePacientesNiEscribe(): void
    {
        $codigo = file_get_contents(__DIR__ . '/../../models/GrafoConocimiento.php');
        preg_match_all('/\b(?:FROM|JOIN)\s+([a-z_]+)/i', $codigo, $tablas);
        $this->assertEqualsCanonicalizing(['grafo_nodos', 'razas', 'grafo_aristas', 'grafo_nodos', 'grafo_nodos', 'grafo_sinonimos', 'grafo_nodos', 'grafo_reglas', 'auditoria_sistema'], $tablas[1]);
        $this->assertDoesNotMatchRegularExpression('/\b(INSERT|UPDATE|DELETE|REPLACE)\s/i', $codigo);
    }

    public function testConocimientoVacioDaEstadoExplicitoYManifiestoAusenteUnaReserva(): void
    {
        $respuesta = $this->respuesta(new GrafoConocimientoController(ConocimientoPrueba::sqlite(), __DIR__ . '/no-existe.json'));
        $this->assertTrue($respuesta['success']);
        $this->assertTrue($respuesta['datos']['vacio']);
        $this->assertNull($respuesta['datos']['version']);
        $this->assertSame([], $respuesta['datos']['cobertura']);
        $this->assertSame(GrafoConocimiento::LIMITACION_RESERVA, $respuesta['datos']['limitaciones']);
    }

    public function testElEndpointSoloConsulta(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $respuesta = $this->respuesta(new GrafoConocimientoController($this->conocimiento(), self::MANIFIESTO));
        $this->assertSame(405, http_response_code());
        $this->assertFalse($respuesta['success']);
        $this->assertArrayNotHasKey('datos', $respuesta);
    }

    public function testLaVistaUsaElPanelYBiblioteca3DLocalConVersionFija(): void
    {
        PlataformaFixture::plataforma();
        ob_start();
        (new GrafoConocimientoController(ConocimientoPrueba::sqlite(), self::MANIFIESTO))->vista();
        $html = (string) ob_get_clean();
        $this->assertStringContainsString('src="js/vendor/3d-force-graph-1.80.0.min.js', $html);
        $this->assertStringNotContainsString('cytoscape', $html);
        $this->assertFileExists(__DIR__ . '/../../public/js/vendor/3d-force-graph-LICENSE.txt');
        $this->assertStringNotContainsString('latest', $html);
        $this->assertStringContainsString('class="pill-nav-item active" href="index.php?action=plataforma_grafo"', $html);
        $this->assertStringContainsString('href="index.php?action=plataforma_inicio"', $html);
        $this->assertStringContainsString('data-url="index.php?action=obtener_grafo_conocimiento_ajax"', $html);
        $this->assertStringContainsString('Super-administrador', $html);
        $this->assertDoesNotMatchRegularExpression('/\sstyle=|\son[a-z]+=/i', $html);
        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\ssrc=)[^>]*>/i', $html, 'Nada de JS en línea.');
        $this->assertDoesNotMatchRegularExpression('/<form[^>]*method="post"|<textarea|type="submit"/i', $html, 'Solo lectura: sin formularios que escriban.');
    }
}
