<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/CuentaTitular.php';
require_once __DIR__ . '/../../models/IntentoLogin.php';
require_once __DIR__ . '/../../helpers/Autenticador.php';
require_once __DIR__ . '/../../controllers/IdentidadController.php';
require_once __DIR__ . '/../../controllers/AuthController.php';

/** D2: aceptación, identidad y enlaces del titular, sin SMTP ni servicios externos. */
final class CuentasD2Test extends TestCase
{
    private PDO $db;
    private CuentaTitular $cuentas;
    private const CLAVE = 'Bosque#Seguro2026';

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        $this->db->exec("CREATE TABLE casos_soporte (id_caso INTEGER PRIMARY KEY AUTOINCREMENT, tipo TEXT, id_usuario INTEGER, id_clinica INTEGER, descripcion TEXT, estado TEXT DEFAULT 'abierto')");
        $this->db->exec('CREATE TABLE intentos_login (id_intento INTEGER PRIMARY KEY AUTOINCREMENT, identificador TEXT UNIQUE, intentos INTEGER, bloqueado_hasta TEXT, primer_intento TEXT, ultimo_intento TEXT)');
        $this->cuentas = new CuentaTitular($this->db);
        $_SESSION = ['id_usuario' => 1];
        Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::ADMIN), 1);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR'] = '192.0.2.10';
        Security::definirAlmacenDeIntentos(new IntentoLogin($this->db));
        Security::definirAuditoria(new Auditoria($this->db));
        $_POST = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        Security::definirAlmacenDeIntentos(null);
        Security::definirAuditoria(false);
        Security::definirFuenteDeContextos(null);
        $_POST = [];
        $_GET = [];
        $_SESSION = [];
    }

    private function alta(): array
    {
        return $this->cuentas->crearPersonal(['documento' => '1000088888', 'tipo_documento' => 'CC',
            'email' => 'nuevo@zooki.test', 'nombre_completo' => 'Laura Nueva', 'telefono' => '3001112233', 'id_rol' => 2], 1);
    }

    private function activar(array $alta, array $datos = []): void
    {
        $this->cuentas->activar($alta['enlace']['id'], $alta['enlace']['token'], $datos + [
            'password' => self::CLAVE, 'confirm_password' => self::CLAVE, 'acepta_datos' => '1',
        ], '192.0.2.10');
    }

    public function testAltaInerteNoEntraPorPasswordNiPorGoogle(): void
    {
        $alta = $this->alta();
        $usuario = (new Usuario($this->db))->buscarPorId($alta['id_usuario']);
        $this->assertSame(0, (int) $usuario['estado']);
        $this->assertSame(0, (int) $usuario['tiene_password']);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM consentimientos_datos')->fetchColumn());
        $auth = new Autenticador(new Usuario($this->db), new VerificacionEmail($this->db));
        $this->assertSame('fallo', $auth->conPassword('nuevo@zooki.test', self::CLAVE)['resultado']);
        $this->assertSame('inactiva', $auth->conGoogle('nuevo@zooki.test')['resultado']);
        $this->assertGreaterThan(time() + 71 * 3600, strtotime($this->cuentas->leerEnlace($alta['enlace']['id'], $alta['enlace']['token'], 'activacion_personal')['expires_at']));
    }

    public function testActivaConAceptacionYGuardaPruebaDelTitular(): void
    {
        $alta = $this->alta();
        $this->activar($alta);
        $this->assertTrue((new Usuario($this->db))->verificarPassword($alta['id_usuario'], self::CLAVE));
        $prueba = $this->db->query('SELECT * FROM consentimientos_datos')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame($alta['id_usuario'], (int) $prueba['id_usuario']);
        $this->assertSame(PoliticaDatos::VERSION, $prueba['version_politica']);
        $this->assertSame('alta_personal', $prueba['medio']);
        $this->assertSame('192.0.2.10', $prueba['ip_address']);
        $this->assertNull($this->cuentas->leerEnlace($alta['enlace']['id'], $alta['enlace']['token'], 'activacion_personal'));
        $this->expectException(InvalidArgumentException::class);
        $this->activar($alta);
    }

    public function testSinAceptacionNoActivaNiConsume(): void
    {
        $alta = $this->alta();
        try {
            $this->activar($alta, ['acepta_datos' => '']);
            $this->fail('Debe exigir aceptación del titular.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(0, (int) (new Usuario($this->db))->buscarPorId($alta['id_usuario'])['tiene_password']);
            $this->assertNotNull($this->cuentas->leerEnlace($alta['enlace']['id'], $alta['enlace']['token'], 'activacion_personal'));
        }
    }

    public function testClaveConNombreSeRechazaAntesDeActivar(): void
    {
        $alta = $this->alta();
        $this->expectException(InvalidArgumentException::class);
        $this->activar($alta, ['password' => 'Laura#2026Segura', 'confirm_password' => 'Laura#2026Segura']);
    }

    public function testGetDeActivacionNoMutaYPostSinCsrfEs403(): void
    {
        $alta = $this->alta();
        $_GET = $alta['enlace'];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_start();
        (new IdentidadController($this->db))->enlace('activacion_personal');
        $html = ob_get_clean();
        $this->assertStringContainsString('Aceptar y activar mi cuenta', $html);
        $this->assertNotNull($this->cuentas->leerEnlace($alta['enlace']['id'], $alta['enlace']['token'], 'activacion_personal'));
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->expectException(AccesoDenegado::class);
        Security::autorizar('activar_personal');
    }

    public function testVencidaNoActivaYLimpiezaEliminaSoloPendienteExclusiva(): void
    {
        $alta = $this->alta();
        (new Auditoria($this->db))->log($alta['id_usuario'], 'LOGIN_FAIL', 'usuarios', $alta['id_usuario'], null, null, 'Intento sobre cuenta pendiente', null);
        $this->db->prepare("UPDATE verificaciones_email SET expires_at = '2000-01-01 00:00:00' WHERE id = ?")->execute([$alta['enlace']['id']]);
        $this->assertNull($this->cuentas->leerEnlace($alta['enlace']['id'], $alta['enlace']['token'], 'activacion_personal'));
        $this->assertSame(1, $this->cuentas->limpiarPendientes());
        $this->assertNull((new Usuario($this->db))->buscarPorId($alta['id_usuario']));
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE descripcion = 'Intento sobre cuenta pendiente' AND id_usuario IS NULL")->fetchColumn());
        $this->assertSame(0, $this->cuentas->limpiarPendientes());
        $this->assertNotNull((new Usuario($this->db))->buscarPorId(8), 'Una cuenta inactiva legítima no se elimina.');
    }

    public function testLimpiezaConservaOtroVinculoInclusoInactivo(): void
    {
        $alta = $this->alta();
        $id = $alta['id_usuario'];
        $this->db->prepare("INSERT INTO usuario_clinica (id_usuario,id_clinica,id_rol,estado) VALUES (?,2,2,'inactivo')")->execute([$id]);
        $this->db->prepare("UPDATE verificaciones_email SET expires_at = '2000-01-01 00:00:00' WHERE id = ?")->execute([$alta['enlace']['id']]);
        $this->assertSame(0, $this->cuentas->limpiarPendientes());
        $this->assertSame(2, (int) $this->db->query('SELECT COUNT(*) FROM usuario_clinica WHERE id_usuario = ' . $id)->fetchColumn());
        $this->db->prepare('DELETE FROM usuario_clinica WHERE id_usuario = ? AND id_clinica = 2')->execute([$id]);
        $this->db->prepare("INSERT INTO propietario_clinica (id_propietario,id_clinica,estado) VALUES (?,1,'inactivo')")->execute([$id]);
        $this->assertSame(0, $this->cuentas->limpiarPendientes());
    }

    public function testCorreoConservaAnteriorHastaConfirmarYRetiraGoogle(): void
    {
        $this->db->exec("UPDATE usuarios SET google_uid = 'google-fabio' WHERE id_usuario = 6");
        $solicitud = $this->cuentas->solicitarCorreo(6, ['email' => 'fabio.nuevo@zooki.test', 'password_actual' => DosClinicas::PASSWORD]);
        $this->assertSame('fabio@zooki.test', (new Usuario($this->db))->buscarPorId(6)['email']);
        $resultado = $this->cuentas->confirmarCorreo($solicitud['enlace']['id'], $solicitud['enlace']['token']);
        $this->assertSame('fabio@zooki.test', $resultado['anterior']);
        $usuario = (new Usuario($this->db))->buscarPorId(6);
        $this->assertSame('fabio.nuevo@zooki.test', $usuario['email']);
        $this->assertSame(0, (int) $usuario['tiene_google']);
        $this->expectException(InvalidArgumentException::class);
        $this->cuentas->confirmarCorreo($solicitud['enlace']['id'], $solicitud['enlace']['token']);
    }

    public function testCorreoExigeIdentidadYDocumentoDeGoogleUsaTokenNuevo(): void
    {
        $google = new CuentaTitular($this->db, static fn ($token) => $token === 'token-real-validado' ? ['email' => 'iris@zooki.test'] : null);
        $this->db->exec("UPDATE usuarios SET google_uid = 'google-iris' WHERE id_usuario = 9");
        $google->cambiarDocumento(9, ['documento' => '1022222222', 'tipo_documento' => 'CC', 'access_token' => 'token-real-validado']);
        $this->assertSame('1022222222', (new Usuario($this->db))->buscarPorId(9)['documento']);
        $this->expectException(InvalidArgumentException::class);
        $google->solicitarCorreo(9, ['email' => 'otro@zooki.test', 'access_token' => 'token-real-validado']);
    }

    public function testDocumentoDuplicadoNoSeAplicaYAbreSoporte(): void
    {
        $antes = (new Usuario($this->db))->buscarPorId(6)['documento'];
        try {
            $this->cuentas->cambiarDocumento(6, ['documento' => '1000000001', 'tipo_documento' => 'CC', 'password_actual' => DosClinicas::PASSWORD]);
            $this->fail('Debe rechazar el documento de Ana.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('soporte', $e->getMessage());
            $this->assertSame($antes, (new Usuario($this->db))->buscarPorId(6)['documento']);
            $this->assertSame('documento_duplicado', $this->db->query('SELECT tipo FROM casos_soporte')->fetchColumn());
        }
    }

    public function testDocumentoValidoSeAuditaConValorAnterior(): void
    {
        $antes = (new Usuario($this->db))->buscarPorId(6)['documento'];
        $this->cuentas->cambiarDocumento(6, ['documento' => '1022222222', 'tipo_documento' => 'CE', 'password_actual' => DosClinicas::PASSWORD]);
        $fila = $this->db->query("SELECT * FROM auditoria_sistema WHERE descripcion LIKE '%RN-G24%' ORDER BY id_auditoria DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame($antes, json_decode($fila['datos_anteriores'], true)['documento']);
        $this->assertSame('1022222222', json_decode($fila['datos_nuevos'], true)['documento']);
        $this->assertNull($fila['id_clinica']);
        $this->expectException(InvalidArgumentException::class);
        $this->cuentas->cambiarDocumento(6, ['documento' => '1023333333', 'tipo_documento' => 'CC', 'password_actual' => 'incorrecta']);
    }

    public function testRestablecimientoRevocaClaveYNoGeneraOtra(): void
    {
        $solicitud = $this->cuentas->restablecerPersonal(2, 1);
        $this->assertFalse((new Usuario($this->db))->verificarPassword(2, DosClinicas::PASSWORD));
        $this->assertSame(0, (int) (new Usuario($this->db))->buscarPorId(2)['tiene_password']);
        $reset = new PasswordReset($this->db);
        $fila = $reset->findById($solicitud['enlace']['id']);
        $this->assertTrue(password_verify($solicitud['enlace']['token'], $fila['token_hash']));
        $this->assertTrue($reset->consumirToken($fila['id']));
        $this->assertFalse($reset->consumirToken($fila['id']));
    }

    public function testCaptchaDesdeOtraIpNoBloqueaLaCuenta(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Security::recordFailedLogin('6');
        }
        $this->assertFalse(Security::checkRateLimit('6'), 'La IP original está bloqueada.');
        $_SESSION = [];
        $_SERVER['REMOTE_ADDR'] = '192.0.2.20';
        $this->assertTrue(Security::checkRateLimit('6'), 'La cuenta no bloquea la IP nueva.');
        $auth = new Autenticador(new Usuario($this->db), new VerificacionEmail($this->db));
        $this->assertSame('fallo', $auth->conPasswordProtegido('fabio@zooki.test', DosClinicas::PASSWORD, '', new Turnstile(static fn () => true))['resultado']);
        $this->assertSame('fallo', $auth->conPasswordProtegido('fabio@zooki.test', DosClinicas::PASSWORD, 'token', new Turnstile(static fn () => false))['resultado']);
        $this->assertSame('ok', $auth->conPasswordProtegido('fabio@zooki.test', DosClinicas::PASSWORD, 'token', new Turnstile(static fn () => true))['resultado']);
        $this->assertSame('fallo', $auth->conGoogleProtegido('fabio@zooki.test', '', new Turnstile(static fn () => true))['resultado']);
        $this->assertSame('ok', $auth->conGoogleProtegido('fabio@zooki.test', 'token', new Turnstile(static fn () => true))['resultado']);
        $this->assertSame('fallo', $auth->conPasswordProtegido('fabio@zooki.test', 'incorrecta', 'token', new Turnstile(static fn () => true))['resultado']);
        $this->db->exec("UPDATE intentos_login SET primer_intento = '2000-01-01 00:00:00'");
        $this->assertFalse(Security::exigeCaptcha('6'));
    }

    public function testValidacionUsaDatosDelTitularYCsrfIndependiente(): void
    {
        $_SESSION = ['id_usuario' => 6, 'csrf_cuenta_validacion' => 'validacion', 'csrf_default' => 'guardado'];
        $_POST = ['cuenta_csrf' => 'validacion', 'csrf_token' => 'guardado', 'campo' => 'password', 'valor' => 'Fabio#Seguro2026'];
        ob_start();
        (new IdentidadController($this->db))->validar();
        $resultado = json_decode(ob_get_clean(), true);
        $this->assertTrue($resultado['success']);
        $this->assertNotNull($resultado['error']);
        $_POST['cuenta_csrf'] = 'incorrecto';
        ob_start();
        (new IdentidadController($this->db))->validar();
        $this->assertFalse(json_decode(ob_get_clean(), true)['success']);
    }

    public function testGetVerificacionDeRegistroNoConsumeElEnlace(): void
    {
        $verificaciones = new VerificacionEmail($this->db);
        $id = $verificaciones->crear(6, 'fabio@zooki.test', password_hash('secreto', PASSWORD_DEFAULT), date('Y-m-d H:i:s', time() + 86400));
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['id' => $id, 'token' => 'secreto'];
        ob_start();
        (new AuthController($this->db, new stdClass()))->verificarEmail();
        $html = ob_get_clean();
        $this->assertStringContainsString('Confirmar mi correo', $html);
        $this->assertSame(0, (int) $verificaciones->buscarPorId($id)['used']);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->expectException(AccesoDenegado::class);
        Security::autorizar('verificar_email');
    }

    public function testValidacionDeEnlaceUsaElTitularAunqueHayaOtraSesion(): void
    {
        $reset = new PasswordReset($this->db);
        $id = $reset->createToken(6, 'fabio@zooki.test', password_hash('secreto', PASSWORD_DEFAULT), date('Y-m-d H:i:s', time() + 86400));
        $_SESSION = ['id_usuario' => 1, 'csrf_cuenta_validacion' => 'validacion'];
        $_POST = ['cuenta_csrf' => 'validacion', 'campo' => 'password', 'valor' => 'Fabio#Seguro2026', 'token_id' => $id, 'token' => 'secreto'];
        ob_start();
        (new IdentidadController($this->db))->validar();
        $resultado = json_decode(ob_get_clean(), true);
        $this->assertNotNull($resultado['error']);
    }

    public function testNuevoCorreoOcupadoAlConfirmarNoSeSobrescribe(): void
    {
        $solicitud = $this->cuentas->solicitarCorreo(6, ['email' => 'compartido@zooki.test', 'password_actual' => DosClinicas::PASSWORD]);
        $this->db->exec("UPDATE usuarios SET email = 'compartido@zooki.test' WHERE id_usuario = 4");
        try {
            $this->cuentas->confirmarCorreo($solicitud['enlace']['id'], $solicitud['enlace']['token']);
            $this->fail('La unicidad se debe comprobar nuevamente al confirmar.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('fabio@zooki.test', (new Usuario($this->db))->buscarPorId(6)['email']);
            $this->assertNotNull($this->cuentas->leerEnlace($solicitud['enlace']['id'], $solicitud['enlace']['token'], 'cambio_correo'));
        }
    }

    public function testCorreoExigePasswordOConfirmacionNuevaDeGoogle(): void
    {
        $_SESSION['login_method'] = 'google';
        $this->expectException(InvalidArgumentException::class);
        $this->cuentas->solicitarCorreo(6, ['email' => 'nuevo@zooki.test']);
    }
}
