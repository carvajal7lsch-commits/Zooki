<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/CarnetPrueba.php';
require_once __DIR__ . '/../../controllers/CarnetPublicoController.php';
require_once __DIR__ . '/../../models/CarnetPropietario.php';
require_once __DIR__ . '/../../helpers/InicioSesion.php';
require_once __DIR__ . '/../../helpers/PresentacionCarnet.php';

/** Observa las consultas preparadas sin sustituir la base ni la lógica. */
final class SentenciaCarnetPrueba extends PDOStatement
{
    public static array $consultas = [];
    protected function __construct() {}
    public function execute(?array $params = null): bool
    {
        self::$consultas[] = $this->queryString;
        return parent::execute($params);
    }
}

final class CarnetPublicoTest extends TestCase
{
    private PDO $db;
    private DateTimeImmutable $ahora;
    private array $correos = [];
    private array $entorno = [];
    private string $token;

    protected function setUp(): void
    {
        $this->db = CarnetPrueba::crear();
        $this->db->setAttribute(PDO::ATTR_STATEMENT_CLASS, [SentenciaCarnetPrueba::class]);
        foreach (['APP_URL' => 'https://zooki.example', 'CARNET_IP_SECRETO' => str_repeat('s', 64)] as $clave => $valor) {
            $this->entorno[$clave] = getenv($clave);
            putenv($clave . '=' . $valor);
        }
        $this->token = str_repeat('a', 43);
        $this->ahora = new DateTimeImmutable('2026-10-23 12:00:00', new DateTimeZone(ReglaAtencion::ZONA));
        Auditoria::definirProxiesConfiables([]);
        $_SESSION = [];
        $_GET = ['t' => $this->token];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '198.51.100.9';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    protected function tearDown(): void
    {
        foreach ($this->entorno as $clave => $valor) {
            putenv($valor === false ? $clave : $clave . '=' . $valor);
        }
        Auditoria::definirProxiesConfiables(null);
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        http_response_code(200);
    }

    private function modelo(?callable $correo = null): CarnetPublico
    {
        return new CarnetPublico($this->db, $correo ?? function (array $aviso): bool {
            $this->correos[] = $aviso;
            return true;
        });
    }

    private function controlador(?callable $correo = null): CarnetPublicoController
    {
        return new CarnetPublicoController($this->db, $correo ?? function (array $aviso): bool {
            $this->correos[] = $aviso;
            return true;
        }, fn (): DateTimeImmutable => $this->ahora);
    }

    private function capturar(callable $accion): array
    {
        http_response_code(200);
        ob_start();
        try {
            $accion();
            return [http_response_code(), ob_get_contents()];
        } finally {
            ob_end_clean();
        }
    }

    public function testSinSesionMuestraSoloDatosDeEmergenciaYSinIdentificadoresPrivados(): void
    {
        $this->db->exec("UPDATE mascotas SET url_foto = '1.jpg' WHERE id_mascota = 1");
        [$codigo, $html] = $this->capturar(fn () => $this->controlador()->mostrar());
        $this->assertSame(200, $codigo);
        $this->assertStringContainsString('Luna', $html);
        $this->assertStringContainsString('Penicilina &lt;urgente&gt;', $html);
        $this->assertStringContainsString('01/02/2099', $html);
        $this->assertStringContainsString('carnet_foto&amp;t=', $html);
        $this->assertStringContainsString('noindex, nofollow', $html);
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
        foreach (['id_mascota', 'data-id-', 'portal_carnet_svg', 'uploads/', '1.jpg', 'data-carnet-regenerar', 'data-carnet-compartir', 'HISTORIA-PRIVADA', 'DOCUMENTO-PRIVADO', 'DIRECCION-PRIVADA', 'privado@example.org'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $html);
        }
        $datos = $this->modelo()->escanear($this->token, PrivacidadCarnet::ipHash(), $this->ahora)['datos'];
        $this->assertSame(['nombre', 'foto', 'especie', 'raza', 'telefono_propietario', 'alertas', 'clinicas', 'vacunas'], array_keys($datos));
        $this->assertSame([], $_SESSION);
        $this->assertSame(['noindex, nofollow', 'no-store', 'no-referrer', 'nosniff'], array_values(CarnetPublicoController::cabeceras()));
    }

    public function testTodosLosTokensNoDisponiblesDanElMismoCuerpoCodigoYConsultas(): void
    {
        $_SESSION = ['id_usuario' => 6];
        Contexto::activar(Contexto::dePropietario(), 1);
        (new CarnetPropietario($this->db))->regenerar(1, $this->token);
        $vigente = (string) $this->db->query('SELECT token_carnet FROM mascotas WHERE id_mascota = 1')->fetchColumn();
        $_SESSION = [];
        $respuestas = [];
        $consultas = [];
        foreach (['mal formado', str_repeat('z', 43), $this->token, $vigente, $vigente, ['invalido'], strtoupper($vigente)] as $indice => $token) {
            if ($indice === 3) {
                $this->db->exec('UPDATE mascotas SET estado = 0 WHERE id_mascota = 1');
            }
            if ($indice === 4) {
                $this->db->exec('UPDATE mascotas SET estado = 1, id_propietario = NULL, carnet_activo = 0 WHERE id_mascota = 1');
            }
            $_GET = ['t' => $token];
            SentenciaCarnetPrueba::$consultas = [];
            $respuesta = $this->capturar(fn () => $this->controlador()->mostrar());
            $this->assertSame(404, $respuesta[0]);
            $respuestas[] = $respuesta[1];
            $consultas[] = SentenciaCarnetPrueba::$consultas;
        }
        foreach ($respuestas as $respuesta) {
            $this->assertSame($respuestas[0], $respuesta);
        }
        foreach ($consultas as $consulta) {
            $this->assertSame($consultas[0], $consulta);
        }
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM carnet_escaneos')->fetchColumn());
        $this->assertCount(0, $this->correos);
    }

    public function testLimiteCuentaTokensInvalidosYPasa30PeroRechaza31YLimpiaTras24Horas(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $_GET['t'] = $i % 2 === 0 ? $this->token : 'invalido';
            [$codigo] = $this->capturar(fn () => $this->controlador()->mostrar());
            $this->assertSame($i % 2 === 0 ? 200 : 404, $codigo);
        }
        $antes = (int) $this->db->query('SELECT COUNT(*) FROM carnet_escaneos')->fetchColumn();
        $_GET['t'] = $this->token;
        $a = $this->capturar(fn () => $this->controlador()->mostrar());
        $_GET['t'] = 'invalido';
        $b = $this->capturar(fn () => $this->controlador()->mostrar());
        $this->assertSame(429, $a[0]);
        $this->assertSame($a, $b);
        $this->assertStringContainsString('noindex, nofollow', $a[1]);
        $this->assertSame($antes, (int) $this->db->query('SELECT COUNT(*) FROM carnet_escaneos')->fetchColumn());
        $this->ahora = $this->ahora->modify('+1 hour 1 second');
        $_GET['t'] = $this->token;
        $this->assertSame(200, $this->capturar(fn () => $this->controlador()->mostrar())[0]);
        $this->ahora = $this->ahora->modify('+25 hours');
        $this->capturar(fn () => $this->controlador()->mostrar());
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM carnet_accesos')->fetchColumn());
    }

    public function testDiezEscaneosAgrupanUnCorreoYOtroTras31Minutos(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->modelo()->escanear($this->token, PrivacidadCarnet::ipHash(), $this->ahora);
        }
        $this->assertCount(1, $this->correos);
        $this->assertSame(10, (int) $this->db->query('SELECT COUNT(*) FROM carnet_escaneos WHERE notificado = 1')->fetchColumn());
        $aviso = $this->db->query('SELECT * FROM notificaciones')->fetch(PDO::FETCH_ASSOC);
        $this->assertNull($aviso['id_clinica']);
        $this->assertSame('carnet_escaneo', $aviso['tipo_notificacion']);
        $this->assertSame('enviado', $aviso['estado']);
        $this->assertStringContainsString('23/10/2026 12:00:00', $aviso['mensaje']);
        $this->assertStringNotContainsString($this->token, json_encode($this->correos));
        $this->modelo()->escanear($this->token, PrivacidadCarnet::ipHash(), $this->ahora->modify('+31 minutes'));
        $this->assertCount(2, $this->correos);
        $hash = $this->db->query('SELECT ip_hash FROM carnet_escaneos LIMIT 1')->fetchColumn();
        $this->assertSame(hash_hmac('sha256', '198.51.100.9', str_repeat('s', 64)), $hash);
    }

    public function testCorreoFallidoDejaEstadoErrorYNoRompePagina(): void
    {
        [$codigo] = $this->capturar(fn () => $this->controlador(static function (): bool {
            throw new RuntimeException('Fallo simulado');
        })->mostrar());
        $this->assertSame(200, $codigo);
        $this->assertSame('error', $this->db->query('SELECT estado FROM notificaciones')->fetchColumn());
    }

    public function testUbicacionValidaGuardaSeisDecimalesYAvisaAparteSinAgrupar(): void
    {
        $modelo = $this->modelo();
        $resultado = $modelo->escanear($this->token, PrivacidadCarnet::ipHash(), $this->ahora);
        $entrada = ['latitud' => '2.123456', 'longitud' => '-75.987654', 'comprobante' => $resultado['comprobante']];
        $modelo->ubicar($this->token, PrivacidadCarnet::ipHash(), $entrada, $this->ahora);
        $fila = $this->db->query('SELECT latitud, longitud FROM carnet_escaneos')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(2.123456, (float) $fila['latitud']);
        $this->assertSame(-75.987654, (float) $fila['longitud']);
        $this->assertCount(2, $this->correos);
        $this->assertStringContainsString('https://www.openstreetmap.org/?mlat=2.123456&amp;mlon=-75.987654', $this->correos[1]['mensaje']);
        $this->assertSame('carnet_ubicacion', $this->db->query('SELECT tipo_notificacion FROM notificaciones ORDER BY id_notificacion DESC LIMIT 1')->fetchColumn());
        $modelo->ubicar($this->token, PrivacidadCarnet::ipHash(), $entrada, $this->ahora);
        $this->assertCount(2, $this->correos);
        $resultado = $modelo->escanear($this->token, PrivacidadCarnet::ipHash(), $this->ahora->modify('+1 minute'));
        $entrada['comprobante'] = $resultado['comprobante'];
        $modelo->ubicar($this->token, PrivacidadCarnet::ipHash(), $entrada, $this->ahora->modify('+1 minute'));
        $this->assertCount(3, $this->correos);
    }

    public function testUbicacionesNoAdmitidasNoGuardanYRespondenIgual(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['t' => $this->token, 'latitud' => '2.123456', 'longitud' => '-75.987654', 'comprobante' => str_repeat('0', 64)];
        $sinEscaneo = $this->capturar(fn () => $this->controlador()->ubicacion());
        $resultado = $this->modelo()->escanear($this->token, PrivacidadCarnet::ipHash(), $this->ahora);
        $_POST['comprobante'] = $resultado['comprobante'];
        $base = $_POST;
        foreach ([['latitud' => '91'], ['longitud' => '-181'], ['latitud' => '1.1234567'], ['latitud' => ['1']], ['latitud' => '1e2'], ['t' => 'invalido'], ['comprobante' => 'otro']] as $cambio) {
            $_POST = array_replace($base, $cambio);
            $this->assertSame($sinEscaneo, $this->capturar(fn () => $this->controlador()->ubicacion()));
        }
        $_POST = $base;
        $_SERVER['REMOTE_ADDR'] = '198.51.100.10';
        $this->assertSame($sinEscaneo, $this->capturar(fn () => $this->controlador()->ubicacion()));
        $_SERVER['REMOTE_ADDR'] = '198.51.100.9';
        $this->ahora = $this->ahora->modify('+31 minutes');
        $this->assertSame($sinEscaneo, $this->capturar(fn () => $this->controlador()->ubicacion()));
        $this->assertNull($this->db->query('SELECT latitud FROM carnet_escaneos')->fetchColumn());
        $this->assertCount(1, $this->correos);
    }

    public function testUbicacionDeUnEscaneoAnteriorODeUnTokenRegeneradoNoSeReutiliza(): void
    {
        $modelo = $this->modelo();
        $primero = $modelo->escanear($this->token, PrivacidadCarnet::ipHash(), $this->ahora);
        $modelo->escanear($this->token, PrivacidadCarnet::ipHash(), $this->ahora);
        $entrada = ['latitud' => '1', 'longitud' => '2', 'comprobante' => $primero['comprobante']];
        $modelo->ubicar($this->token, PrivacidadCarnet::ipHash(), $entrada, $this->ahora);
        $_SESSION = ['id_usuario' => 6];
        Contexto::activar(Contexto::dePropietario(), 1);
        $nuevo = (new CarnetPropietario($this->db))->regenerar(1, $this->token)['token'];
        $modelo->ubicar($nuevo, PrivacidadCarnet::ipHash(), $entrada, $this->ahora);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM carnet_escaneos WHERE latitud IS NOT NULL')->fetchColumn());
    }

    public function testUbicacionConsumeElMismoLimiteAntesDeGuardar(): void
    {
        for ($i = 0; $i < 30; $i++) (new AccesosCarnet($this->db))->admitir(PrivacidadCarnet::ipHash(), $this->ahora);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        [$codigo] = $this->capturar(fn () => $this->controlador()->ubicacion());
        $this->assertSame(429, $codigo);
        $this->assertSame(30, (int) $this->db->query('SELECT COUNT(*) FROM carnet_accesos')->fetchColumn(), 'Por encima del límite no se registra.');
    }

    public function testSinSecretoFallaDeFormaSegura(): void
    {
        putenv('CARNET_IP_SECRETO');
        [$codigo, $html] = $this->capturar(fn () => $this->controlador()->mostrar());
        $this->assertSame(503, $codigo);
        $this->assertStringNotContainsString('Luna', $html);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM carnet_escaneos')->fetchColumn());
    }

    public function testContactosExcluyenBajaYConservanSuspendidaYFechaEnLasTresVistas(): void
    {
        $this->db->exec("UPDATE clinicas SET estado = 'baja' WHERE id_clinica = 2");
        $this->db->exec("UPDATE clinicas SET estado = 'suspendida' WHERE id_clinica = 1");
        $datos = (new CarnetMascota($this->db))->datosEmergencia(1);
        $this->assertSame(['Clínica Norte'], array_column($datos['clinicas'], 'nombre'));
        $this->assertSame('01/02/2099', PresentacionCarnet::fecha('2099-02-01'));
    }

    public function testUltimosDiezEscaneosSoloSonDelDueno(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->modelo()->escanear($this->token, PrivacidadCarnet::ipHash(), $this->ahora->modify('+' . $i . ' minutes'));
        }
        $_SESSION = ['id_usuario' => 6];
        Contexto::activar(Contexto::dePropietario(), 1);
        $modelo = new CarnetPropietario($this->db);
        $escaneos = $modelo->ultimosEscaneos(1);
        $this->assertCount(10, $escaneos);
        $this->assertSame('2026-10-23 12:11:00', $escaneos[0]['fecha']);
        $this->assertSame(['fecha', 'mapa'], array_keys($escaneos[0]));
        $this->expectException(AccesoDenegado::class);
        $modelo->ultimosEscaneos(2);
    }

    public function testTrasLoginElDuenoAbreSuFichaYOtroPropietarioSuPortalSinDatosAjenos(): void
    {
        foreach ([6 => 1, 9 => null] as $usuario => $esperado) {
            $_SESSION = [];
            RetornoCarnet::recordar($this->token);
            (new ConsentimientoDatos($this->db))->registrar($usuario, PoliticaDatos::MEDIO_FORMULARIO, '127.0.0.1');
            $fila = $this->db->query('SELECT * FROM usuarios WHERE id_usuario = ' . $usuario)->fetch(PDO::FETCH_ASSOC);
            $destino = (new InicioSesion($this->db))->abrir($fila, 'password');
            $this->assertSame('portal_propietario', $destino);
            $this->assertSame($esperado, RetornoCarnet::consumir($this->db));
            $this->assertNull(RetornoCarnet::consumir($this->db));
        }
    }
}
