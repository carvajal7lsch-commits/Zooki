<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../helpers/InicializadorClinica.php';
require_once __DIR__ . '/../../controllers/ConfiguracionClinicaController.php';
require_once __DIR__ . '/../../models/MarcaClinica.php';
require_once __DIR__ . '/../../models/Cita.php';
require_once __DIR__ . '/../../models/VinculosPropietario.php';
require_once __DIR__ . '/../../models/HistoriaPropietario.php';
require_once __DIR__ . '/../../config/EmailService.php';

/** C1, P01/P19: gestión real aislada, snapshots y composición existente. */
final class MarcaTiposClinicaTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
        Auditoria::definirProxiesConfiables([]);
        Csrf::token();
        $this->como(1, 1, Roles::ADMIN);
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        http_response_code(200);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        http_response_code(200);
    }

    private function como(int $clinica, int $usuario, int $rol): void
    {
        $_SESSION = ['id_usuario' => $usuario];
        Contexto::activar(Contexto::deClinica($clinica, 'Contexto anterior', $rol), 1);
    }

    private function marca(string $nombre = 'Marca Norte'): array
    {
        return ['nombre' => $nombre, 'direccion' => 'Calle 10', 'telefono' => '+57 3001234567',
            'telefono_urgencias' => '3007654321', 'email_contacto' => 'clinica@example.com',
            'url_logo' => 'https://example.com/logo.png'];
    }

    private function tipo(): array
    {
        return ['nombre_tipo' => 'Cirugía de prueba', 'duracion_minutos' => '45', 'margen_minutos' => '0', 'pausable' => '0'];
    }

    public function testMarcaYTiposSoloCambianEnClinicaActivaConAuditoria(): void
    {
        $sur = $this->db->query('SELECT * FROM clinicas WHERE id_clinica=2')->fetch(PDO::FETCH_ASSOC);
        $modelo = new ConfiguracionClinica($this->db);
        $this->assertSame('Marca Norte', $modelo->guardarMarca($this->marca())['nombre']);
        $tipo = $modelo->guardarTipo(null, $this->tipo());
        $this->assertSame(0, (int) $tipo['pausable']);
        $this->assertSame(0, (int) $tipo['margen_minutos']);
        $this->assertSame($sur, $this->db->query('SELECT * FROM clinicas WHERE id_clinica=2')->fetch(PDO::FETCH_ASSOC));
        $this->assertSame(2, (int) $this->db->query('SELECT COUNT(*) FROM auditoria_sistema WHERE id_clinica=1')->fetchColumn());
        $this->como(2, 3, Roles::ADMIN);
        $otro = (new ConfiguracionClinica($this->db))->guardarTipo(null, $this->tipo() + ['id_clinica' => 2]);
        $this->assertNotSame($tipo['id_tipo_cita'], $otro['id_tipo_cita']);
        $this->assertCount(2, (new ConfiguracionClinica($this->db))->leer()['tipos']);
    }

    public function testTiposAjenosContextoForjadoYRolesNoAdquierenGestion(): void
    {
        foreach (['guardarTipo', 'cambiarEstado'] as $metodo) {
            try {
                $modelo = new ConfiguracionClinica($this->db);
                $metodo === 'guardarTipo' ? $modelo->guardarTipo(2, $this->tipo()) : $modelo->cambiarEstado(2, ['activo' => '0']);
                $this->fail('Admitió tipo Sur desde Norte.');
            } catch (AccesoDenegado $error) {
                $this->assertSame(403, $error->getCode());
            }
        }
        foreach ([[1, 2, Roles::VETERINARIO], [1, 6, Roles::PROPIETARIO], [1, 7, Roles::SUPER_ADMIN], [2, 1, Roles::ADMIN]] as [$c, $u, $r]) {
            $this->como($c, $u, $r);
            try {
                (new ConfiguracionClinica($this->db))->guardarMarca($this->marca());
                $this->fail('Admitió gestión sin rol/vínculo vigente.');
            } catch (AccesoDenegado $error) {
                $this->assertSame(403, $error->getCode());
            }
        }
        $this->como(1, 1, Roles::ADMIN);
        $this->expectException(AccesoDenegado::class);
        (new ConfiguracionClinica($this->db))->guardarMarca($this->marca() + ['id_clinica' => 2]);
    }

    public function testEditarYDesactivarConservaSnapshotsEImpideReservarConTipoInactivo(): void
    {
        $citas = new Cita($this->db);
        $datos = ['id_mascota' => 1, 'id_veterinario' => 2, 'id_tipo_cita' => 1, 'fecha' => '2030-01-07', 'hora' => '09:00', 'motivo' => 'Control'];
        $id = $citas->registrar($datos);
        $antes = $this->db->query('SELECT * FROM citas WHERE id_cita=' . $id)->fetch(PDO::FETCH_ASSOC);
        $modelo = new ConfiguracionClinica($this->db);
        $modelo->guardarTipo(1, $this->tipo());
        $modelo->cambiarEstado(1, ['activo' => '0']);
        $this->assertSame([], $citas->getTiposCita());
        $this->assertSame(0, (int) $citas->getTipoCitaById(1)['activo']);
        $this->assertSame($antes, $this->db->query('SELECT * FROM citas WHERE id_cita=' . $id)->fetch(PDO::FETCH_ASSOC));
        try {
            $citas->registrar(array_replace($datos, ['hora' => '11:00']));
            $this->fail('Admitió una reserva manipulada con tipo inactivo.');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString('tipo de cita', $error->getMessage());
        }
        $modelo->cambiarEstado(1, ['activo' => '1']);
        $this->assertCount(1, $citas->getTiposCita());
        $this->assertSame(45, (int) $citas->getTipoCitaById(1)['duracion_minutos']);
    }

    public function testFalloDeAuditoriaRevierteMarcaAltaEdicionYEstado(): void
    {
        $modelo = new ConfiguracionClinica($this->db);
        $antesMarca = $modelo->leer()['marca'];
        $antesTipos = $modelo->leer()['tipos'];
        $this->db->exec("CREATE TRIGGER bloquear_auditoria BEFORE INSERT ON auditoria_sistema BEGIN SELECT RAISE(ABORT, 'auditoria simulada'); END");
        foreach ([fn () => $modelo->guardarMarca($this->marca()), fn () => $modelo->guardarTipo(null, $this->tipo()),
            fn () => $modelo->guardarTipo(1, $this->tipo()), fn () => $modelo->cambiarEstado(1, ['activo' => '0'])] as $operacion) {
            try {
                $operacion();
                $this->fail('Confirmó sin auditoría.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('auditar', $error->getMessage());
            }
            $this->assertSame($antesMarca, $modelo->leer()['marca']);
            $this->assertSame($antesTipos, $modelo->leer()['tipos']);
        }
    }

    public function testValidacionServidorPorCampoNoEscribe(): void
    {
        $modelo = new ConfiguracionClinica($this->db);
        $antes = $modelo->leer();
        foreach ([['nombre', 'A'], ['url_logo', 'javascript:alert(1)'], ['url_logo', str_repeat('a', 256)],
            ['telefono_urgencias', 'abc'], ['email_contacto', 'correo'], ['duracion_minutos', '0'], ['margen_minutos', '-1'],
            ['pausable', ''], ['pausable', 'cirugia'], ['nombre_tipo', str_repeat('a', 101)], ['duracion_minutos', '2147483648']] as [$campo, $valor]) {
            try {
                in_array($campo, ['nombre', 'url_logo', 'telefono_urgencias', 'email_contacto'], true)
                    ? $modelo->guardarMarca(array_replace($this->marca(), [$campo => $valor]))
                    : $modelo->guardarTipo(null, array_replace($this->tipo(), [$campo => $valor]));
                $this->fail('Admitió ' . $campo);
            } catch (CampoInvalido $error) {
                $this->assertSame($campo, $error->campo);
            }
        }
        $this->assertSame($antes, $modelo->leer());
    }

    public function testControladorExigeCsrfYDevuelveEstadoCanonico(): void
    {
        $controlador = new ConfiguracionClinicaController($this->db);
        $_POST = $this->marca();
        try {
            ob_start();
            $controlador->guardarMarcaAjax();
            $this->fail('Guardó sin CSRF.');
        } catch (AccesoDenegado $error) {
            $this->assertSame(403, $error->getCode());
        } finally {
            ob_end_clean();
        }
        $_POST['csrf_token'] = Csrf::token();
        ob_start();
        $controlador->guardarMarcaAjax();
        $respuesta = json_decode(ob_get_clean(), true);
        $this->assertTrue($respuesta['success']);
        $this->assertSame('Marca Norte', $respuesta['marca']['nombre']);
        $this->assertSame('Marca Norte', Contexto::actual()['clinica']);
    }

    public function testCopiaExplicitaRepetibleRespetaPersonalizaciones(): void
    {
        $this->db->exec('DELETE FROM tipos_cita');
        $inicializar = new InicializadorClinica($this->db);
        $inicializar->copiar(1);
        $tipo = (new CatalogoClinica($this->db))->tiposCita()[0];
        $this->assertSame(10, (int) $tipo['margen_minutos']);
        $this->assertSame(1, (int) $tipo['pausable']);
        $this->db->exec('UPDATE tipos_cita SET margen_minutos=23, pausable=0, activo=0 WHERE id_clinica=1');
        $antes = (new CatalogoClinica($this->db))->tiposCita(true);
        $inicializar->copiar(1);
        $inicializar->copiar(1);
        $this->assertSame($antes, (new CatalogoClinica($this->db))->tiposCita(true));
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testMarcaEnPortalCorreoYDocumentoSinImponerClinicaGlobal(): void
    {
        $marca = (new ConfiguracionClinica($this->db))->guardarMarca($this->marca());
        $this->como(2, 3, Roles::ADMIN);
        (new ConfiguracionClinica($this->db))->guardarMarca(array_replace($this->marca('Marca Sur'), ['url_logo' => 'https://example.com/sur.png']));
        $_SESSION = ['id_usuario' => 6];
        Contexto::activar(Contexto::dePropietario(), 1);
        $clinicas = (new VinculosPropietario($this->db))->clinicas();
        $this->assertSame('Marca Norte', $clinicas[0]['nombre']);
        $this->assertSame($marca['url_logo'], $clinicas[0]['url_logo']);
        $correo = new EmailService(['SMTP_FROM' => 'no-reply@example.com']);
        $html = $correo->obtenerPlantillaBaseHTML('Fabio', 'Control', '<p>Recordatorio</p>', null, null, $marca);
        $this->assertStringContainsString('Marca Norte', $html);
        $this->assertStringContainsString($marca['url_logo'], $html);
        $this->assertStringNotContainsString('Marca Sur', $html);
        $global = $correo->obtenerPlantillaBaseHTML('Fabio', 'Cuenta', '<p>Cuenta global</p>');
        $this->assertStringNotContainsString('Marca Norte', $global);
        $this->assertStringContainsString('Zooki', $global);
        $this->assertStringContainsString('Marca Norte', MarcaClinica::html($marca));
        $this->assertSame('', MarcaClinica::logo('javascript:alert(1)'));
        // La historia global incluye actos de ambas clínicas sin tomar la marca de la sesión.
        $this->db->exec("INSERT INTO alertas_medicas (id_mascota, id_clinica, tipo, descripcion) VALUES
            (1, 1, 'otra', 'Acto Norte'), (1, 2, 'otra', 'Acto Sur')");
        $alertas = (new HistoriaPropietario($this->db))->alertas(1);
        $this->assertSame(['Marca Sur', 'Marca Norte'], array_column($alertas, 'clinica_nombre'));
        $mascota = ['nombre' => 'Luna', 'sexo' => 'Hembra', 'fecha_nacimiento' => null, 'peso' => null];
        $consultas = [];
        $vacunas = [];
        $desparasitaciones = [];
        ob_start();
        require __DIR__ . '/../../views/portal/imprimir_historial.php';
        $documento = ob_get_clean();
        $this->assertStringContainsString('<strong class="historial-marca">Zooki</strong>', $documento);
        $this->assertStringContainsString('Marca Norte', $documento);
        $this->assertStringContainsString('Marca Sur', $documento);
        $this->assertStringContainsString('https://example.com/sur.png', $documento);
        $this->assertStringContainsString($marca['url_logo'], $documento);
    }
}
