<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/IntentoLogin.php';
require_once __DIR__ . '/../../models/RegistroClinica.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/RegistroClinicaController.php';
require_once __DIR__ . '/../../helpers/Autenticador.php';
require_once __DIR__ . '/RegistroClinicaTest.php';

/**
 * M0.1 — Pulido del acceso y los registros.
 *
 * RE-T.13.5 y RN-G15 (decisión del usuario, 2026-10-11): el widget del login
 * solo aparece cuando el servidor lo exige, tras 5 fallos sobre el mismo
 * documento o correo, y la respuesta es idéntica exista o no la cuenta.
 */
final class M01AccesoTest extends TestCase
{
    private const CLAVE_SITIO = 'clave-sitio-prueba';
    private const INCORRECTA = 'Incorrecta#2026';
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        $this->db->exec('CREATE TABLE intentos_login (id_intento INTEGER PRIMARY KEY AUTOINCREMENT, identificador TEXT UNIQUE, intentos INTEGER, bloqueado_hasta TEXT, primer_intento TEXT, ultimo_intento TEXT)');
        $this->db->exec("CREATE TABLE casos_soporte (id_caso INTEGER PRIMARY KEY, tipo TEXT, id_usuario INTEGER, id_clinica INTEGER, descripcion TEXT, estado TEXT DEFAULT 'abierto')");
        Security::definirAlmacenDeIntentos(new IntentoLogin($this->db));
        Security::definirAuditoria(new Auditoria($this->db));
        Auditoria::definirProxiesConfiables([]);
        putenv('TURNSTILE_SITE_KEY=' . self::CLAVE_SITIO);
        putenv('APP_URL=http://localhost/Zooki/public');
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_SERVER['REMOTE_ADDR'] = '192.0.2.40';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    protected function tearDown(): void
    {
        Security::definirAlmacenDeIntentos(null);
        Security::definirAuditoria(false);
        Auditoria::definirProxiesConfiables(null);
        putenv('TURNSTILE_SITE_KEY');
        putenv('APP_URL');
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        http_response_code(200);
    }

    private function auth(): AuthController
    {
        // El CAPTCHA de prueba solo acepta el token «valido».
        return new AuthController($this->db, new stdClass(), new Turnstile(static fn (string $token): bool => $token === 'valido'));
    }

    private function intentar(string $identificador, string $password, string $token = ''): array
    {
        $destino = $this->auth()->intentarLogin(['identificador' => $identificador, 'password' => $password, 'cf-turnstile-response' => $token]);
        $mensaje = $_SESSION['error_login'] ?? null;
        unset($_SESSION['error_login']);
        return [$destino, $mensaje];
    }

    private function paginaDeLogin(): string
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_start();
        try {
            $this->auth()->login();
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }

    /** El contenedor del widget del login, tal como lo recibe el navegador. */
    private function widgetDelLogin(string $html): string
    {
        $this->assertSame(1, preg_match('/<div class="acceso-turnstile"[^>]*data-turnstile-apariencia="always"[^>]*>/', $html, $coincidencia));
        return $coincidencia[0];
    }

    /** Cinco fallos y un sexto intento sin CAPTCHA desde una IP limpia. */
    private function recorrido(string $identificador, string $sextaClave, string $ip): array
    {
        $_SESSION = [];
        $_SERVER['REMOTE_ADDR'] = $ip;
        $respuestas = [];
        for ($i = 0; $i < 5; $i++) {
            $respuestas[] = $this->intentar($identificador, self::INCORRECTA);
        }
        $widgetTrasCinco = $this->widgetDelLogin($this->paginaDeLogin());
        $respuestas[] = $this->intentar($identificador, $sextaClave);
        $widgetTrasSeis = $this->widgetDelLogin($this->paginaDeLogin());
        return ['respuestas' => $respuestas, 'widget' => [$widgetTrasCinco, $widgetTrasSeis]];
    }

    public function testSinFallosElLoginNoMuestraElWidget(): void
    {
        $html = $this->paginaDeLogin();
        $widget = $this->widgetDelLogin($html);
        $this->assertStringContainsString(' hidden', $widget, 'Sin fallos el widget no se pinta.');
        $this->assertStringContainsString('data-sitekey="' . self::CLAVE_SITIO . '"', $widget);
        $this->assertStringNotContainsString('class="cf-turnstile"', $html, 'Ya no hay widget de pintado automático.');
        $this->assertStringContainsString('render=explicit', $html);
        $this->assertStringNotContainsString('Registra tu clínica', $html, 'Un solo «Crear cuenta».');
        $this->assertStringContainsString('data-crear-cuenta-abrir', $html);
        $this->assertStringContainsString('data-crear-cuenta="propietario"', $html);
        $this->assertStringContainsString('href="index.php?action=registro_clinica"', $html);

        [$destino, $mensaje] = $this->intentar('fabio@zooki.test', self::INCORRECTA);
        $this->assertSame('index.php?action=login', $destino);
        $this->assertSame('Documento, correo o contraseña incorrectos.', $mensaje);
        $this->assertStringContainsString(' hidden', $this->widgetDelLogin($this->paginaDeLogin()), 'Un fallo tampoco lo pide.');
    }

    public function testCincoFallosDanLaMismaRespuestaConYSinCuenta(): void
    {
        // El sexto intento de la cuenta real lleva su contraseña correcta: sin CAPTCHA tampoco entra.
        $existente = $this->recorrido('fabio@zooki.test', DosClinicas::PASSWORD, '192.0.2.41');
        $inexistente = $this->recorrido('nadie@zooki.test', DosClinicas::PASSWORD, '192.0.2.42');

        $this->assertSame($existente, $inexistente, 'La cuenta real y la inexistente reciben exactamente lo mismo.');
        $this->assertSame(['index.php?action=login', 'Documento, correo o contraseña incorrectos.'], $existente['respuestas'][4]);
        $this->assertSame(['index.php?action=login', 'Por seguridad, completa la verificación y vuelve a escribir tu contraseña.'], $existente['respuestas'][5]);
        foreach ($existente['widget'] as $widget) {
            $this->assertStringNotContainsString(' hidden', $widget, 'Tras 5 fallos el widget aparece.');
        }
        $this->assertArrayNotHasKey('id_usuario', $_SESSION);
    }

    public function testCuatroFallosNoLoPidenYMayusculasOEspaciosNoCuentanAparte(): void
    {
        foreach (['Nadie@Zooki.test', ' nadie@zooki.test', 'NADIE@ZOOKI.TEST', 'nadie@zooki.test '] as $variante) {
            $this->intentar($variante, self::INCORRECTA);
        }
        $this->assertStringContainsString(' hidden', $this->widgetDelLogin($this->paginaDeLogin()), 'Cuatro fallos no lo piden.');
        $this->intentar('nadie@ZOOKI.test', self::INCORRECTA);
        $this->assertStringNotContainsString(' hidden', $this->widgetDelLogin($this->paginaDeLogin()), 'El quinto, escrito distinto, sí.');
        $this->assertTrue(Security::exigeCaptcha('NADIE@zooki.test'));
        $this->assertFalse(Security::exigeCaptcha('otra@zooki.test'), 'Otro identificador sigue sin CAPTCHA.');
    }

    public function testElServidorRechazaSinTokenYAdmiteElTitularConCaptchaValido(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->intentar('1000000006', self::INCORRECTA);
        }
        $rechazo = ['index.php?action=login', 'Por seguridad, completa la verificación y vuelve a escribir tu contraseña.'];
        $this->assertSame($rechazo, $this->intentar('1000000006', DosClinicas::PASSWORD), 'Sin token no entra aunque la contraseña sea correcta.');
        $this->assertSame($rechazo, $this->intentar('1000000006', DosClinicas::PASSWORD, 'falso'), 'Con un token inválido tampoco.');
        $this->assertArrayNotHasKey('id_usuario', $_SESSION);
        $this->assertSame(2, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE accion = 'LOGIN_FAIL' AND descripcion = 'Intento de login fallido: falta la verificación anti-bot'")->fetchColumn());

        // RE-T.13.5: desde otra IP el titular entra superando el CAPTCHA; la cuenta nunca se bloquea.
        $_SERVER['REMOTE_ADDR'] = '192.0.2.43';
        [$destino] = $this->intentar('1000000006', DosClinicas::PASSWORD, 'valido');
        $this->assertNotSame('index.php?action=login', $destino);
        $this->assertSame(DosClinicas::PROPIETARIO, (int) $_SESSION['id_usuario']);
        $this->assertArrayNotHasKey('login_captcha', $_SESSION, 'Al entrar, el login deja de pedirlo.');
        $this->assertFalse(Security::exigeCaptcha('1000000006'), 'El acceso correcto limpia el contador del identificador.');
    }

    public function testElLoginVuelveConElIdentificadorEscritoTrasUnFallo(): void
    {
        $this->intentar('fabio@zooki.test', self::INCORRECTA);
        $html = $this->paginaDeLogin();
        $this->assertStringContainsString('name="identificador" value="fabio@zooki.test"', $html);
        $this->assertStringContainsString('name="identificador" value=""', $this->paginaDeLogin(), 'Solo en la vuelta inmediata.');
    }

    // ── Registro de clínica sin JavaScript ──────────────────────────────

    private function enviarClinicaSinJavascript(array $datos, bool $captchaValido): string
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['csrf_default'] = 'seguro';
        $_POST = $datos + ['csrf_token' => 'seguro'];
        $correo = new class {
            public function limpiarDirecciones(): void {}
            public function obtenerPlantillaBaseHTML(...$d): string { return ''; }
            public function enviarCorreoPersonalizado(...$d): bool { return true; }
        };
        $controlador = new RegistroClinicaController($this->db, $correo, new Turnstile(static fn (): bool => $captchaValido));
        ob_start();
        $controlador->procesar();
        $this->assertSame('', ob_get_clean(), 'Sin JavaScript no se responde JSON.');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
        ob_start();
        try {
            $controlador->formulario();
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }

    public function testRegistroDeClinicaSinJavascriptMuestraElErrorOLaEspera(): void
    {
        $error = $this->enviarClinicaSinJavascript(RegistroClinicaTest::datos(7), false);
        $this->assertStringContainsString('Completa la verificación de seguridad e inténtalo de nuevo.', $error);
        $this->assertMatchesRegularExpression('/<div class="registro-resumen" data-registro-resumen role="alert" >/', $error, 'El resumen se ve.');
        $this->assertSame(0, (int) $this->db->query("SELECT COUNT(*) FROM clinicas WHERE nombre = 'Clínica Nueva 7'")->fetchColumn());

        $exito = $this->enviarClinicaSinJavascript(RegistroClinicaTest::datos(7), true);
        $this->assertMatchesRegularExpression('/<form id="registroClinicaForm"[^>]* hidden>/', $exito, 'El formulario se oculta.');
        $this->assertMatchesRegularExpression('/<div class="registro-espera" id="registroClinicaEspera" >/', $exito, 'La espera se ve.');
        $this->assertStringContainsString(RegistroClinica::MENSAJE, $exito);
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM clinicas WHERE nombre = 'Clínica Nueva 7'")->fetchColumn());
    }

    public function testRegistroDeClinicaPorAjaxSigueRespondiendoJson(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['csrf_default'] = 'seguro';
        $_POST = RegistroClinicaTest::datos(8, ['cf-turnstile-response' => '']) + ['csrf_token' => 'seguro'];
        ob_start();
        (new RegistroClinicaController($this->db, null, new Turnstile(static fn (): bool => false)))->procesar();
        $respuesta = json_decode((string) ob_get_clean(), true);
        $this->assertSame(['success' => false, 'message' => 'Completa la verificación de seguridad e inténtalo de nuevo.'], $respuesta);
        $this->assertArrayNotHasKey('registro_clinica_resultado', $_SESSION);
    }

    // ── Avisos de una línea ─────────────────────────────────────────────

    public function testLosAvisosDelServidorBajoElCampoSonCortos(): void
    {
        $avisos = [
            ValidadorCuenta::documento('12'),
            ValidadorCuenta::correo('sin-arroba'),
            ValidadorCuenta::nombre('Al'),
            ValidadorCuenta::nombre('Ana 123'),
            ValidadorCuenta::telefono('12'),
            ValidadorClinica::campo('nit', 'abc'),
            ValidadorClinica::campo('nombre_clinica', 'A'),
            ValidadorClinica::campo('direccion', str_repeat('a', 256)),
            ValidadorClinica::campo('email_clinica', 'persona@yopmail.com'),
            ValidadorNit::validar('900123456-0'),
            PoliticaPassword::validar('Password123'),
            PoliticaPassword::validar('Abcdef12345'),
            PoliticaPassword::validar('Olga#Segura2026', ['Olga Nueva']),
        ];
        foreach ($avisos as $aviso) {
            $this->assertNotNull($aviso);
            if ($aviso === ValidadorNit::MENSAJE) {
                $this->assertSame('El dígito de verificación no corresponde. Revísalo en tu RUT.', $aviso);
                continue;
            }
            // 35 caracteres caben en media columna del marco de 460 px (registro-cuenta.css).
            $this->assertLessThanOrEqual(35, mb_strlen($aviso), $aviso);
        }
    }

    // ── Marco común de los dos registros ────────────────────────────────

    public function testLosDosRegistrosCompartenMarcoCasillaYVentanaDeEspera(): void
    {
        $login = $this->paginaDeLogin();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_start();
        try {
            (new RegistroClinicaController($this->db))->formulario();
        } finally {
            $clinica = (string) ob_get_clean();
        }
        foreach ([$login, $clinica] as $html) {
            $this->assertStringContainsString('class="form-wrapper registro-marco"', $html);
            $this->assertStringContainsString('class="registro-grid"', $html);
            $this->assertStringContainsString('css/registro-cuenta.css?v=' . App::VERSION, $html);
            $this->assertStringContainsString('js/registro-cuenta.js?v=' . App::VERSION, $html);
            $this->assertStringContainsString('Autorizo el tratamiento de mis datos personales conforme a la', $html);
            $this->assertStringContainsString('<h3>Enviando tu registro…</h3>', $html);
            $this->assertStringContainsString('data-registro-resumen', $html);
        }
        // Registros: «interaction-only» (la opción por defecto del JS); el login lo declara «always».
        $this->assertSame(1, substr_count($clinica, 'data-turnstile-formulario'));
        $this->assertStringNotContainsString('data-turnstile-apariencia', $clinica);
        // Tres pasos en un solo formulario; sin JavaScript se envía completo.
        $this->assertSame(3, preg_match_all('/<fieldset class="registro-paso" data-paso="\d">/', $clinica));
        $this->assertSame(1, substr_count($clinica, '<form '));
        $this->assertSame(1, substr_count($clinica, 'type="submit"'));
        $this->assertMatchesRegularExpression('/<ol class="registro-pasos" id="registroPasos"[^>]* hidden>/', $clinica, 'El indicador solo aparece con JavaScript.');
        $this->assertStringNotContainsString('registro-consentimiento', $clinica, 'La casilla es la del propietario.');
    }
}
