<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/CuentaTitular.php';
require_once __DIR__ . '/../../models/IntentoLogin.php';
require_once __DIR__ . '/../../helpers/Autenticador.php';
require_once __DIR__ . '/../../helpers/EntornoLocal.php';
require_once __DIR__ . '/../../controllers/IdentidadController.php';
require_once __DIR__ . '/../../controllers/UsuarioController.php';
require_once __DIR__ . '/../../config/EmailService.php';
require_once __DIR__ . '/../../scripts/dev/DatosPrueba.php';

/**
 * D2.1 — Corrección de D2: IP real detrás del proxy, límites de RN-G15,
 * comprobaciones al escribir, correos propios, limpieza tolerante y prueba
 * local (correos a archivo y datos de prueba).
 */
final class D21CorreccionTest extends TestCase
{
    private PDO $db;
    private string $carpeta;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        $this->db->exec('CREATE TABLE intentos_login (id_intento INTEGER PRIMARY KEY AUTOINCREMENT, identificador TEXT UNIQUE, intentos INTEGER, bloqueado_hasta TEXT, primer_intento TEXT, ultimo_intento TEXT)');
        Security::definirAlmacenDeIntentos(new IntentoLogin($this->db));
        Security::definirAuditoria(new Auditoria($this->db));
        Auditoria::definirProxiesConfiables([]);
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR'] = '192.0.2.10';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $this->carpeta = sys_get_temp_dir() . '/zooki-correos-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        Security::definirAlmacenDeIntentos(null);
        Security::definirAuditoria(false);
        Auditoria::definirProxiesConfiables(null);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $_SESSION = [];
        $_POST = [];
        foreach (glob($this->carpeta . '/*') ?: [] as $archivo) {
            unlink($archivo);
        }
        if (is_dir($this->carpeta)) {
            rmdir($this->carpeta);
        }
        http_response_code(200);
    }

    // ── 1. IP real (RN-G15) ──────────────────────────────────────────────

    public function testSinProxyConfiableValeLaIpDeLaConexion(): void
    {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.7';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';
        $this->assertSame('198.51.100.7', Auditoria::ipCliente(), 'Un cliente directo no elige su IP con la cabecera.');

        Auditoria::definirProxiesConfiables(['10.0.0.0/8']);
        $this->assertSame('198.51.100.7', Auditoria::ipCliente(), 'La conexión no viene de un proxy confiable.');
    }

    public function testDetrasDeUnProxyConfiableValeLaPrimeraIpNoConfiableDesdeLaDerecha(): void
    {
        Auditoria::definirProxiesConfiables(['10.0.0.0/8', '172.16.0.0/12']);
        $_SERVER['REMOTE_ADDR'] = '10.0.1.5';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.9, 172.18.0.2';
        $this->assertSame('203.0.113.9', Auditoria::ipCliente(), 'Cadena real: cliente y un proxy interno.');

        // El cliente escribió una IP falsa a la izquierda; Traefik agregó la real a la derecha.
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '8.8.8.8, 203.0.113.9';
        $this->assertSame('203.0.113.9', Auditoria::ipCliente(), 'X-Forwarded-For falsificado no cambia la IP.');

        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'basura, 203.0.113.9';
        $this->assertSame('203.0.113.9', Auditoria::ipCliente());

        Auditoria::definirProxiesConfiables(['2001:db8::/32']);
        $_SERVER['REMOTE_ADDR'] = '2001:db8::1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '2001:db9::7';
        $this->assertSame('2001:db9::7', Auditoria::ipCliente(), 'Rangos IPv6.');
    }

    public function testLosLimitesCuentanPorLaIpRealYNoPorLaDelProxy(): void
    {
        Auditoria::definirProxiesConfiables(['10.0.0.0/8']);
        $_SERVER['REMOTE_ADDR'] = '10.0.0.2';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.1';
        for ($i = 0; $i < 20; $i++) {
            Security::recordFailedLogin();
        }
        $this->assertFalse(Security::checkRateLimit(), 'La IP del atacante queda bloqueada.');

        $_SESSION = [];
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.2';
        $this->assertTrue(Security::checkRateLimit(), 'Otra persona detrás del mismo proxy sigue entrando.');
        $this->assertSame(0, (int) $this->db->query("SELECT COUNT(*) FROM intentos_login WHERE identificador = 'ip:10.0.0.2'")->fetchColumn());
    }

    // ── 2 y 3. Límites (decisión del usuario, 2026-10-08) ────────────────

    public function testVeinteFallosBloqueanLaIpYNoAntes(): void
    {
        for ($i = 0; $i < 19; $i++) {
            Security::recordFailedLogin();
        }
        $this->assertTrue(Security::checkRateLimit(), '19 fallos todavía no bloquean.');
        Security::recordFailedLogin();
        $this->assertFalse(Security::checkRateLimit(), 'El intento 21 se rechaza.');
        $_SESSION = [];
        $this->assertFalse(Security::checkRateLimit(), 'Sin la cookie sigue bloqueada (RE-T.13.1).');
    }

    public function testCincoFallosExigenCaptchaYLaCuentaNuncaSeBloquea(): void
    {
        // 30 fallos sobre Fabio desde 6 IP distintas, ninguna llega al límite de IP.
        for ($i = 0; $i < 30; $i++) {
            $_SERVER['REMOTE_ADDR'] = '192.0.2.' . (100 + intdiv($i, 5));
            Security::recordFailedLogin('6');
        }
        $this->assertTrue(Security::exigeCaptcha('6'));
        $_SERVER['REMOTE_ADDR'] = '192.0.2.200';
        $_SESSION = [];
        $this->assertTrue(Security::checkRateLimit('6'), 'La cuenta no se bloquea.');
        $auth = new Autenticador(new Usuario($this->db), new VerificacionEmail($this->db));
        $this->assertSame('fallo', $auth->conPasswordProtegido('fabio@zooki.test', DosClinicas::PASSWORD, '', new Turnstile(static fn () => true))['resultado'], 'Sin CAPTCHA no entra.');
        $this->assertSame('ok', $auth->conPasswordProtegido('fabio@zooki.test', DosClinicas::PASSWORD, 'token', new Turnstile(static fn () => true))['resultado'], 'Con CAPTCHA válido entra.');
    }

    public function testTurnstileRecibeLaIpReal(): void
    {
        Auditoria::definirProxiesConfiables(['10.0.0.0/8']);
        $_SERVER['REMOTE_ADDR'] = '10.0.0.2';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50';
        for ($i = 0; $i < 5; $i++) {
            Security::recordFailedLogin('6');
        }
        $ipVista = null;
        $captcha = new Turnstile(function (string $token, string $ip) use (&$ipVista): bool {
            $ipVista = $ip;
            return true;
        });
        (new Autenticador(new Usuario($this->db), new VerificacionEmail($this->db)))->conPasswordProtegido('fabio@zooki.test', DosClinicas::PASSWORD, 'token', $captcha);
        $this->assertSame('203.0.113.50', $ipVista);
    }

    public function testUnAccesoCorrectoNoLimpiaElContadorDeLaIp(): void
    {
        for ($i = 0; $i < 10; $i++) {
            Security::recordFailedLogin('2');
        }
        Security::recordFailedLogin('6');
        Security::resetRateLimit('6');
        $this->assertSame(0, (int) $this->db->query("SELECT COUNT(*) FROM intentos_login WHERE identificador = 'cuenta:6'")->fetchColumn(), 'Limpia la cuenta.');
        $this->assertArrayNotHasKey('rate_limit_192.0.2.10', $_SESSION, 'Limpia la sesión.');
        for ($i = 0; $i < 9; $i++) {
            Security::recordFailedLogin('2');
        }
        $this->assertFalse(Security::checkRateLimit(), 'Alternar con un acceso propio no evita el bloqueo de la IP.');
    }

    // ── 4. Comprobaciones al escribir ───────────────────────────────────

    private function comprobar(string $campo, string $valor): array
    {
        $_SESSION['csrf_cuenta_validacion'] = 'validacion';
        $_POST = ['cuenta_csrf' => 'validacion', 'campo' => $campo, 'valor' => $valor];
        http_response_code(200);
        ob_start();
        (new IdentidadController($this->db))->validar();
        return ['codigo' => http_response_code(), 'datos' => json_decode((string) ob_get_clean(), true)];
    }

    public function testSinSesionElLimiteEsPorIp(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->assertSame(200, $this->comprobar('email', "libre{$i}@zooki.test")['codigo']);
        }
        $r = $this->comprobar('email', 'otro@zooki.test');
        $this->assertSame(429, $r['codigo']);
        $this->assertSame('Espera un momento antes de seguir comprobando.', $r['datos']['message']);
    }

    public function testConSesionElLimiteEsPorPersonaYAlcanzaParaEscribir(): void
    {
        $_SESSION = ['id_usuario' => DosClinicas::PROPIETARIO];
        for ($i = 0; $i < 120; $i++) {
            $this->assertSame(200, $this->comprobar('email', "nuevo{$i}@zooki.test")['codigo'], "comprobación {$i}");
        }
        $this->assertSame(429, $this->comprobar('email', 'otro@zooki.test')['codigo']);

        // Otra persona desde la misma IP no se ve afectada, ni quien no tiene sesión.
        $_SESSION = ['id_usuario' => DosClinicas::ADMIN_NORTE];
        $this->assertSame(200, $this->comprobar('documento', '1000077777')['codigo']);
        $_SESSION = [];
        $this->assertSame(200, $this->comprobar('documento', '1000077777')['codigo']);
    }

    public function testLaUnicidadRespondeExisteParaQueElAvisoNombreElCampo(): void
    {
        $_SESSION = ['id_usuario' => DosClinicas::PROPIETARIO];
        $this->assertTrue($this->comprobar('email', 'ana@zooki.test')['datos']['exists']);
        $this->assertTrue($this->comprobar('documento', '1000000001')['datos']['exists']);
        $js = file_get_contents(__DIR__ . '/../../public/js/validacion-cuenta.js');
        $this->assertStringContainsString("NOMBRES_UNICOS = { documento: 'documento', email: 'correo' }", $js);
        $this->assertStringNotContainsString('Este dato ya está registrado', $js);
    }

    // ── 7. Correos propios ──────────────────────────────────────────────

    private function correoEnArchivo(): EmailService
    {
        return new EmailService(['MAIL_MODO' => 'archivo', 'DB_HOST' => '127.0.0.1', 'CARPETA_CORREOS' => $this->carpeta, 'SMTP_FROM' => 'no-reply@zooki.test']);
    }

    private function unicoCorreo(): array
    {
        $archivos = glob($this->carpeta . '/*.html');
        $this->assertCount(1, $archivos);
        return [basename($archivos[0]), file_get_contents($archivos[0])];
    }

    public function testInvitacionDelPersonal(): void
    {
        $this->assertTrue($this->correoEnArchivo()->enviarInvitacionPersonal('laura@zooki.test', 'Laura Nueva', 'Clínica Norte', 'Veterinario', 'http://localhost/index.php?action=activar_personal&id=5&token=abc', 72));

        [$nombre, $html] = $this->unicoCorreo();
        $this->assertMatchesRegularExpression('/^\d{8}-\d{6}-\d+_laura@zooki\.test_Te-invitaron-a-Cl.*nica-Norte-en-Zooki\.html$/', $nombre);
        $this->assertStringContainsString('Asunto: Te invitaron a Clínica Norte en Zooki', $html);
        $this->assertStringContainsString('Veterinario', $html);
        $this->assertStringContainsString('72 horas', $html);
        $this->assertStringContainsString('política de tratamiento de datos', $html);
        $this->assertStringContainsString('crea tu contraseña', $html);
        $this->assertStringContainsString('action=activar_personal&amp;id=5&amp;token=abc', $html);
        $this->assertStringNotContainsString('Creaste una cuenta', $html);
    }

    public function testRestablecimientoPorElAdministrador(): void
    {
        $this->assertTrue($this->correoEnArchivo()->enviarRestablecimientoPorAdministrador('beto@zooki.test', 'Beto Norte', 'Clínica Norte', 'http://localhost/index.php?action=reset_password&id=9&token=xyz', 24));

        [$nombre, $html] = $this->unicoCorreo();
        $this->assertStringContainsString('Crea-una-nueva-contrase', $nombre);
        $this->assertStringContainsString('Asunto: Crea una nueva contraseña en Zooki', $html);
        $this->assertStringContainsString('ya no sirve', $html);
        $this->assertStringContainsString('24 horas', $html);
        $this->assertStringContainsString('action=reset_password&amp;id=9&amp;token=xyz', $html);
        $this->assertStringNotContainsString('si no fuiste tú quien se registró', mb_strtolower($html));
    }

    public function testUsuarioControllerUsaLasPlantillasPropias(): void
    {
        $correo = new class {
            public array $metodos = [];
            public function limpiarDirecciones(): void {}
            public function enviarInvitacionPersonal(...$datos) { $this->metodos[] = ['invitacion', $datos]; return true; }
            public function enviarRestablecimientoPorAdministrador(...$datos) { $this->metodos[] = ['restablecimiento', $datos]; return true; }
        };
        $_SESSION = ['id_usuario' => DosClinicas::ADMIN_NORTE];
        Contexto::activar(Contexto::deClinica(DosClinicas::NORTE, 'Clínica Norte', Roles::ADMIN), 1);
        $controlador = new UsuarioController($this->db, $correo);

        $_POST = ['tipo_documento' => 'CC', 'documento' => '1000077788', 'nombre_completo' => 'Laura Nueva', 'email' => 'laura@zooki.test',
            'telefono' => '3001112233', 'id_rol' => '2', 'estado' => '1', 'password' => ''];
        ob_start();
        $controlador->registrarAjax();
        ob_end_clean();
        $_POST = ['id_usuario' => '2'];
        ob_start();
        $controlador->resetearPasswordAjax();
        ob_end_clean();

        $this->assertSame('invitacion', $correo->metodos[0][0]);
        $this->assertSame(['laura@zooki.test', 'Laura Nueva', 'Clínica Norte', 'Veterinario'], array_slice($correo->metodos[0][1], 0, 4));
        $this->assertSame('restablecimiento', $correo->metodos[1][0]);
        $this->assertSame('Clínica Norte', $correo->metodos[1][1][2]);
        $this->assertStringNotContainsString('enviarCorreoVerificacion', file_get_contents(__DIR__ . '/../../controllers/UsuarioController.php'));
    }

    // ── 9. Limpieza tolerante ───────────────────────────────────────────

    public function testLaLimpiezaSigueConLasDemasCuandoUnaCuentaFalla(): void
    {
        $_SESSION = ['id_usuario' => DosClinicas::ADMIN_NORTE];
        Contexto::activar(Contexto::deClinica(DosClinicas::NORTE, 'Clínica Norte', Roles::ADMIN), 1);
        $cuentas = new CuentaTitular($this->db);
        $ocupada = $cuentas->crearPersonal(['documento' => '1000088881', 'tipo_documento' => 'CC', 'email' => 'ocupada@zooki.test', 'nombre_completo' => 'Cuenta Ocupada', 'telefono' => '3001112233', 'id_rol' => 2], 1);
        $libre = $cuentas->crearPersonal(['documento' => '1000088882', 'tipo_documento' => 'CC', 'email' => 'libre@zooki.test', 'nombre_completo' => 'Cuenta Libre', 'telefono' => '3001112233', 'id_rol' => 2], 1);
        $this->db->exec("UPDATE verificaciones_email SET expires_at = '2000-01-01 00:00:00'");
        // Un módulo futuro referencia a la primera cuenta (por ejemplo, el perfil del veterinario).
        $this->db->exec('PRAGMA foreign_keys = ON');
        $this->db->exec('CREATE TABLE perfil_prueba (id_usuario INTEGER NOT NULL REFERENCES usuarios(id_usuario))');
        $this->db->prepare('INSERT INTO perfil_prueba (id_usuario) VALUES (?)')->execute([$ocupada['id_usuario']]);

        $log = tempnam(sys_get_temp_dir(), 'zooki-log');
        $anterior = ini_set('error_log', $log);
        try {
            $borradas = $cuentas->limpiarPendientes();
        } finally {
            ini_set('error_log', (string) $anterior);
        }

        $this->assertSame(1, $borradas);
        $this->assertNotNull((new Usuario($this->db))->buscarPorId($ocupada['id_usuario']), 'La ocupada se conserva intacta.');
        $this->assertNull((new Usuario($this->db))->buscarPorId($libre['id_usuario']), 'La limpieza siguió con la otra.');
        $this->assertStringContainsString('no se eliminó la cuenta pendiente ' . $ocupada['id_usuario'], (string) file_get_contents($log));
        unlink($log);
    }

    // ── 11. Prueba local ────────────────────────────────────────────────

    public function testMailModoArchivoGuardaSinEnviarSoloEnLocal(): void
    {
        $local = $this->correoEnArchivo();
        $this->assertTrue($local->guardaEnArchivo());
        $this->assertTrue($local->enviarCorreoPersonalizado('fabio@zooki.test', 'Fabio', 'Prueba', '<p>Hola</p>'));
        $this->assertCount(1, glob($this->carpeta . '/*.html'));

        $log = tempnam(sys_get_temp_dir(), 'zooki-log');
        $anterior = ini_set('error_log', $log);
        try {
            $remoto = new EmailService(['MAIL_MODO' => 'archivo', 'DB_HOST' => 'db.produccion.interna', 'CARPETA_CORREOS' => $this->carpeta,
                'SMTP_HOST' => '127.0.0.1', 'SMTP_PORT' => 9, 'SMTP_FROM' => 'no-reply@zooki.test']);
            $this->assertFalse($remoto->guardaEnArchivo(), 'Fuera de local se ignora.');
            $remoto->enviarCorreoPersonalizado('fabio@zooki.test', 'Fabio', 'Remoto', '<p>Hola</p>');
        } finally {
            ini_set('error_log', (string) $anterior);
        }
        $this->assertCount(1, glob($this->carpeta . '/*.html'), 'Fuera de local no se guarda en archivo.');
        $this->assertStringContainsString('MAIL_MODO=archivo se ignora: la base no es local', (string) file_get_contents($log));
        unlink($log);

        $this->assertFalse((new EmailService(['DB_HOST' => '127.0.0.1', 'SMTP_FROM' => 'no-reply@zooki.test']))->guardaEnArchivo(), 'Por defecto se envía como siempre.');
    }

    public function testEntornoLocalEsLaMismaComprobacionDelScriptDePrueba(): void
    {
        $this->assertNull(EntornoLocal::motivoNoLocal('localhost via TCP/IP'));
        $this->assertNull(EntornoLocal::motivoNoLocal('127.0.0.1'));
        $this->assertNotNull(EntornoLocal::motivoNoLocal('db via TCP/IP'));
        $script = file_get_contents(__DIR__ . '/../../scripts/dev/datos_prueba.php');
        $this->assertStringContainsString('EntornoLocal::motivoNoLocal($conexion)', $script);
    }

    public function testDatosPruebaRechazaUnaClaveQueNoCumpleYRegistraLaPolitica(): void
    {
        $personas = [['Ana Norte', '1000000001', 'ana.norte@zooki.test'], ['Gina Plataforma', null, 'gina.plataforma@zooki.test']];
        $this->assertSame('Zooki#Prueba2026', DatosPrueba::claveDeArgumentos(['datos_prueba.php', '--si', '--clave=Zooki#Prueba2026']));
        $this->assertNull(DatosPrueba::claveDeArgumentos(['datos_prueba.php', '--si']));
        $this->assertStringContainsString('Ana Norte', (string) DatosPrueba::motivoClaveInvalida('corta', $personas));
        $this->assertNotNull(DatosPrueba::motivoClaveInvalida('AnaNorte#2026x', $personas), 'Contiene el nombre de una persona de prueba.');
        $this->assertNull(DatosPrueba::motivoClaveInvalida('Zooki#Prueba2026', $personas));

        $this->assertTrue(DatosPrueba::aceptarPolitica($this->db, DosClinicas::ADMIN_NORTE));
        $this->assertFalse(DatosPrueba::aceptarPolitica($this->db, DosClinicas::ADMIN_NORTE), 'Repetible: no duplica.');
        $fila = $this->db->query('SELECT * FROM consentimientos_datos')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(PoliticaDatos::VERSION, $fila['version_politica']);
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM consentimientos_datos')->fetchColumn());
        $this->assertStringContainsString('DatosPrueba::aceptarPolitica($db, $id)', file_get_contents(__DIR__ . '/../../scripts/dev/datos_prueba.php'));
    }

    public function testEnvExampleSeLeeYExplicaLosProxiesYLosCorreos(): void
    {
        $archivo = __DIR__ . '/../../.env.example';
        $this->assertIsArray(@parse_ini_file($archivo), '.env.example debe poder copiarse como .env.');
        $texto = file_get_contents($archivo);
        $this->assertStringContainsString('TRUSTED_PROXIES=', $texto);
        $this->assertStringContainsString('Traefik', $texto);
        $this->assertStringContainsString('# MAIL_MODO=archivo', $texto);
    }
}
