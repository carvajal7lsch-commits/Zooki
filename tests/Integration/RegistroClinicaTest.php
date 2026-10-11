<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/RegistroClinica.php';
require_once __DIR__ . '/../../controllers/RegistroClinicaController.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/IdentidadController.php';
require_once __DIR__ . '/../../helpers/Autenticador.php';

/** D3: los flujos públicos y sus excepciones al aislamiento se prueban sin SMTP. */
final class RegistroClinicaTest extends TestCase
{
    private PDO $db;
    private RegistroClinica $registro;

    public function testE2NitAntiguoConGuionTambienImpideDuplicarLaClinica(): void
    {
        $this->db->exec("UPDATE clinicas SET nit = '900123456-8' WHERE id_clinica = 1");
        $this->assertTrue($this->registro->nitExiste('900123456', '8'));
        try {
            $this->registro->registrar(self::datos(1, ['nit' => '900123456', 'dv' => '8']), '192.0.2.123');
            $this->fail('El guion antiguo no debe permitir una segunda clínica con el mismo NIT.');
        } catch (NitDuplicado $error) {
            $this->assertSame(3, $this->total('clinicas'));
            $this->assertSame('900123456-8', $this->db->query('SELECT nit FROM clinicas WHERE id_clinica = 1')->fetchColumn());
        }
    }

    public function testE2NitDuplicadoSeAvisaAlEscribirSinRevelarIdentidadesYConLimiteIp(): void
    {
        $this->registro->registrar(self::datos(), '192.0.2.123');
        $_SESSION = ['csrf_cuenta_validacion' => 'validacion'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $entrada = self::datos();
        $_POST = ['cuenta_csrf' => 'validacion', 'campo' => 'dv', 'valor' => $entrada['dv'], 'nit' => $entrada['nit']];
        $controller = new IdentidadController($this->db);
        require_once __DIR__ . '/../../models/IntentoLogin.php';
        Security::definirAlmacenDeIntentos(new IntentoLogin($this->db));
        ob_start();
        $controller->validar();
        $respuesta = json_decode(ob_get_clean(), true);
        $this->assertTrue($respuesta['nit_duplicado']);
        $this->assertStringContainsString('reportarlo a soporte', $respuesta['error']);
        $this->assertArrayNotHasKey('email', $respuesta);
        $this->assertArrayNotHasKey('id_clinica', $respuesta);
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM clinicas WHERE nit = '" . ValidadorNit::normalizar($entrada['nit'] . '-' . $entrada['dv']) . "'")->fetchColumn());
        for ($numero = 1; $numero < 20; $numero++) {
            $_SESSION = ['id_usuario' => 1, 'csrf_cuenta_validacion' => 'validacion'];
            ob_start();
            $controller->validar();
            ob_end_clean();
        }
        ob_start();
        $controller->validar();
        $respuesta = json_decode(ob_get_clean(), true);
        $this->assertSame(429, http_response_code());
        $this->assertFalse($respuesta['success']);
        $this->assertArrayNotHasKey('nit_duplicado', $respuesta);
        Security::definirAlmacenDeIntentos(null);
        http_response_code(200);
    }

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        $this->db->exec('CREATE TABLE intentos_login (identificador TEXT PRIMARY KEY, intentos INTEGER, primer_intento TEXT, ultimo_intento TEXT, bloqueado_hasta TEXT)');
        $this->db->exec("CREATE TABLE casos_soporte (id_caso INTEGER PRIMARY KEY, tipo TEXT, id_usuario INTEGER, id_clinica INTEGER, descripcion TEXT, estado TEXT DEFAULT 'abierto')");
        $this->registro = new RegistroClinica($this->db);
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_SERVER['REMOTE_ADDR'] = '192.0.2.123';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        Security::definirAlmacenDeIntentos(null);
        Auditoria::definirProxiesConfiables([]);
        putenv('APP_URL=http://localhost/Zooki/public');
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        unset($_SERVER['REQUEST_METHOD']);
        putenv('APP_URL');
    }

    public static function datos(int $numero = 1, array $cambios = []): array
    {
        $numeroNit = (string) (900123450 + $numero);
        return $cambios + [
            'nombre_clinica' => 'Clínica Nueva ' . $numero,
            'nit' => $numeroNit,
            'dv' => (string) ValidadorNit::digito($numeroNit),
            'direccion' => 'Carrera 10 número 20-30',
            'telefono_clinica' => '3001112233',
            'email_clinica' => 'clinica' . $numero . '@zooki.test',
            'nombre_completo' => 'Olga Nueva',
            'tipo_documento' => 'CC',
            'documento' => (string) (1030000000 + $numero),
            'telefono' => '3001112233',
            'email' => 'olga' . $numero . '@zooki.test',
            'password' => 'Huellita#Segura2026',
            'confirm_password' => 'Huellita#Segura2026',
            'acepta_datos' => '1',
            'cf-turnstile-response' => 'desafio',
        ];
    }

    private function alta(int $numero = 1, array $cambios = []): array
    {
        return $this->registro->registrar(self::datos($numero, $cambios), Auditoria::ipCliente());
    }

    private function activar(array $alta): int
    {
        return $this->registro->activar($alta['enlace']['id'], $alta['enlace']['token'], ['acepta_datos' => '1'], Auditoria::ipCliente());
    }

    private function total(string $tabla): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM ' . $tabla)->fetchColumn();
    }

    public function testNuevaCuentaEsInerteYActivacionCopiaTodoSinTocarOtrasClinicas(): void
    {
        $alta = $this->alta();
        $fila = $this->registro->leer($alta['enlace']['id'], $alta['enlace']['token']);
        $id = (int) $fila['id_usuario'];
        $usuarios = new Usuario($this->db);
        $this->assertNull($usuarios->contextosDe($id));
        $this->assertTrue($usuarios->verificarPassword($id, self::datos()['password']));
        $this->assertSame(0, $this->total('tipos_cita'));
        $clinica = $this->activar($alta);
        foreach (['tipos_cita' => 6, 'horarios_clinica' => 7, 'vacunas_base' => 15, 'laboratorios_base' => 11, 'productos_desparasitacion_base' => 15] as $tabla => $cantidad) {
            $this->assertSame($cantidad, $this->total($tabla), $tabla);
            $this->assertSame($cantidad, (int) $this->db->query('SELECT COUNT(*) FROM ' . $tabla . ' WHERE id_clinica = ' . $clinica)->fetchColumn());
        }
        $this->assertGreaterThan(0, $this->total('especie_vacunas'));
        $this->assertSame([Contexto::deClinica($clinica, 'Clínica Nueva 1', Roles::ADMIN)], $usuarios->contextosDe($id));
        $this->assertSame(1, (int) $this->db->query('SELECT id_plan FROM clinicas WHERE id_clinica = ' . $clinica)->fetchColumn());
        $this->assertNull($this->registro->leer($alta['enlace']['id'], $alta['enlace']['token']));
        $this->assertSame(2, (int) $this->db->query('SELECT COUNT(*) FROM auditoria_sistema WHERE id_clinica = ' . $clinica)->fetchColumn());
        $_SESSION = ['id_usuario' => $id];
        Contexto::activar(Contexto::deClinica($clinica, 'Clínica Nueva 1', Roles::ADMIN), $id);
        require_once __DIR__ . '/../../models/Mascota.php';
        Security::definirAuditoria(new Auditoria($this->db));
        $this->assertSame([], (new Mascota($this->db))->getAll());
        try {
            (new Mascota($this->db))->getById(1);
            $this->fail('El administrador nuevo accedió a una mascota de Norte.');
        } catch (AccesoDenegado $e) {
            $this->assertSame(403, $e->codigo());
        }
        Security::definirAuditoria(null);
    }

    public function testExistenteConservaIdentidadPasswordYRolesHastaYDespuesDeConfirmar(): void
    {
        $antes = $this->db->query('SELECT * FROM usuarios WHERE id_usuario = 5')->fetch(PDO::FETCH_ASSOC);
        $roles = $this->db->query('SELECT * FROM usuario_clinica WHERE id_usuario = 5')->fetchAll(PDO::FETCH_ASSOC);
        $alta = $this->alta(1, ['email' => $antes['email'], 'documento' => '1000000001']);
        $this->assertSame($antes, $this->db->query('SELECT * FROM usuarios WHERE id_usuario = 5')->fetch(PDO::FETCH_ASSOC));
        $this->assertSame($roles, $this->db->query('SELECT * FROM usuario_clinica WHERE id_usuario = 5')->fetchAll(PDO::FETCH_ASSOC));
        $this->assertSame(10, $this->total('usuarios'));
        $clinica = $this->activar($alta);
        $this->assertSame($antes, $this->db->query('SELECT * FROM usuarios WHERE id_usuario = 5')->fetch(PDO::FETCH_ASSOC));
        $this->assertCount(4, (new Usuario($this->db))->contextosDe(5));
        $this->assertSame(1, (int) $this->db->query('SELECT id_rol FROM usuario_clinica WHERE id_usuario = 5 AND id_clinica = ' . $clinica)->fetchColumn());
    }

    public function testCorreoNuevoDocumentoExistenteConfirmaAlTitularSinCrearOtraCuenta(): void
    {
        $persona = (new Usuario($this->db))->buscarPorId(6);
        $alta = $this->alta(1, ['documento' => $persona['documento']]);
        $this->assertSame('fabio@zooki.test', $alta['email']);
        $this->assertFalse($alta['nueva']);
        $this->activar($alta);
        $this->assertSame(10, $this->total('usuarios'));
        $this->assertTrue((new Usuario($this->db))->verificarPassword(6, DosClinicas::PASSWORD));
        $this->assertCount(2, (new Usuario($this->db))->contextosDe(6));
        $this->assertSame('ok', (new Autenticador(new Usuario($this->db), new VerificacionEmail($this->db)))->conPassword('fabio@zooki.test', DosClinicas::PASSWORD)['resultado']);
    }

    public function testFallaEnCatalogosRevierteClinicaRolConsentimientoYToken(): void
    {
        $alta = $this->alta();
        $antes = $this->db->query('SELECT * FROM verificaciones_email')->fetchAll(PDO::FETCH_ASSOC);
        $this->db->exec("CREATE TRIGGER fallo_catalogo BEFORE INSERT ON laboratorios_base BEGIN SELECT RAISE(ABORT, 'Fallo simulado'); END");
        try {
            $this->activar($alta);
            $this->fail('La activación debía fallar.');
        } catch (PDOException $e) {
            $this->assertSame(0, $this->total('tipos_cita'));
            $this->assertSame(0, $this->total('horarios_clinica'));
            $this->assertSame(0, $this->total('vacunas_base'));
            $this->assertSame($antes, $this->db->query('SELECT * FROM verificaciones_email')->fetchAll(PDO::FETCH_ASSOC));
            $this->assertSame(1, $this->total('consentimientos_datos'));
            $this->assertNotNull($this->registro->leer($alta['enlace']['id'], $alta['enlace']['token']));
        }
        $this->db->exec('DROP TRIGGER fallo_catalogo');
        $this->assertGreaterThan(3, $this->activar($alta));
    }

    public function testLimiteTresPorDiaEIpNoSeEvadeConSesionNuevaNiConXForwardedFor(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $_SESSION = [];
            $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.0.2.' . $i;
            $this->alta($i);
        }
        try {
            $this->alta(4);
            $this->fail('Se admitió el cuarto registro.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('límite', $e->getMessage());
            $this->assertSame(6, $this->total('clinicas'));
        }
        $_SERVER['REMOTE_ADDR'] = '192.0.2.124';
        $this->alta(4);
        $this->assertSame(7, $this->total('clinicas'));
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    public function testNitDuplicadoPuedeReportarseSinExponerAlAdministrador(): void
    {
        $this->alta();
        try {
            $this->alta();
            $this->fail('NIT duplicado admitido.');
        } catch (NitDuplicado $e) {
            $this->assertStringContainsString('soporte', $e->getMessage());
        }
        $this->registro->reportarNit(self::datos());
        $this->assertSame('clinica_duplicada', $this->db->query('SELECT tipo FROM casos_soporte')->fetchColumn());
    }

    public function testLimpiezaEliminaSoloAdministradorCreadoPorEseRegistro(): void
    {
        $nueva = $this->alta();
        $existente = $this->alta(2, ['email' => 'fabio@zooki.test']);
        $this->db->exec("UPDATE verificaciones_email SET expires_at = '2000-01-01 00:00:00'");
        $this->assertNull($this->registro->leer($nueva['enlace']['id'], $nueva['enlace']['token']));
        $this->assertSame(2, $this->registro->limpiarPendientes());
        $this->assertSame(3, $this->total('clinicas'));
        $this->assertSame(10, $this->total('usuarios'));
        $this->assertTrue((new Usuario($this->db))->verificarPassword(6, DosClinicas::PASSWORD));
        $this->assertSame(0, $this->registro->limpiarPendientes());
    }

    public function testLimpiezaPreservaAdministradorNuevoQueAdquirioOtroVinculo(): void
    {
        $alta = $this->alta();
        $fila = $this->registro->leer($alta['enlace']['id'], $alta['enlace']['token']);
        (new Usuario($this->db))->asignarRolEnClinica((int) $fila['id_usuario'], 1, Roles::VETERINARIO);
        $this->db->exec("UPDATE verificaciones_email SET expires_at = '2000-01-01 00:00:00'");
        $this->assertSame(1, $this->registro->limpiarPendientes());
        $this->assertNotNull((new Usuario($this->db))->buscarPorId((int) $fila['id_usuario']));
    }

    public function testReportarNitPermiteCincoPorDiaEIpSinEvadirConOtraSesion(): void
    {
        $this->alta();
        for ($i = 0; $i < RegistroClinica::REPORTES_DIARIOS; $i++) {
            $_SESSION = [];
            $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.0.2.' . $i;
            $this->registro->reportarNit(self::datos());
        }
        try {
            $this->registro->reportarNit(self::datos());
            $this->fail('El sexto reporte debe rechazarse.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(5, $this->total('casos_soporte'));
        }
        $_SERVER['REMOTE_ADDR'] = '192.0.2.125';
        $this->registro->reportarNit(self::datos());
        $this->assertSame(6, $this->total('casos_soporte'));
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    public function testRegistroYReporteRechazanDvIncorrectoYNoExtraenDelNit(): void
    {
        foreach ([['nit' => '9001234568', 'dv' => ''], ['nit' => '900123456', 'dv' => '2'], ['nit' => '900123456-8', 'dv' => '8']] as $cambio) {
            foreach (['registrar', 'reportarNit'] as $metodo) {
                try {
                    if ($metodo === 'registrar') {
                        $this->registro->registrar(self::datos(1, $cambio), Auditoria::ipCliente());
                    } else {
                        $this->registro->reportarNit(self::datos(1, $cambio));
                    }
                    $this->fail('El formato o DV incorrecto no debe crear registros.');
                } catch (InvalidArgumentException $e) {
                    $this->assertSame(3, $this->total('clinicas'));
                    $this->assertSame(0, $this->total('casos_soporte'));
                }
            }
        }
    }

    public function testDosAltasPendientesDeLaMismaCuentaNoDejanIdentidadInerteHuerfana(): void
    {
        $nueva = $this->alta();
        $otra = $this->alta(2, ['email' => $nueva['email']]);
        $this->db->exec("UPDATE verificaciones_email SET expires_at = '2000-01-01 00:00:00'");
        $this->assertSame(2, $this->registro->limpiarPendientes());
        $this->assertSame(10, $this->total('usuarios'));
        $this->assertSame(3, $this->total('clinicas'));
    }

    public function testLimpiezaRetiraClinicaVencidaAunqueOtraLimpiezaBorraraElEnlace(): void
    {
        $alta = $this->alta(1, ['email' => 'fabio@zooki.test']);
        $fila = $this->registro->leer($alta['enlace']['id'], $alta['enlace']['token']);
        $this->db->prepare("UPDATE clinicas SET fecha_registro = '2000-01-01 00:00:00' WHERE id_clinica = ?")->execute([$fila['id_clinica_vinculo']]);
        $this->db->prepare('DELETE FROM verificaciones_email WHERE id = ?')->execute([$alta['enlace']['id']]);
        $this->assertSame(1, $this->registro->limpiarPendientes());
        $this->assertSame(10, $this->total('usuarios'));
        $this->assertSame(3, $this->total('clinicas'));
    }

    public function testGetNoActivaYPostSinCsrfNoActiva(): void
    {
        $alta = $this->alta();
        $_GET = $alta['enlace'];
        $_GET['token'] = $alta['enlace']['token'];
        $controller = new RegistroClinicaController($this->db);
        ob_start();
        $controller->activar();
        $html = ob_get_clean();
        $this->assertStringContainsString('Confirmar y activar', $html);
        $this->assertSame(0, $this->total('tipos_cita'));
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $alta['enlace'] + ['acepta_datos' => '1'];
        ob_start();
        $controller->activar();
        ob_end_clean();
        $this->assertSame(0, $this->total('tipos_cita'));
        $this->expectException(AccesoDenegado::class);
        Security::autorizar('activar_clinica');
    }

    public function testTokenUsadoVencidoOTergiversadoNoActiva(): void
    {
        $alta = $this->alta();
        $this->assertNull($this->registro->leer($alta['enlace']['id'], 'otro'));
        $this->activar($alta);
        $this->expectException(InvalidArgumentException::class);
        $this->activar($alta);
    }

    public function testActivacionSinConsentimientoOVencidaNoModificaDatos(): void
    {
        $alta = $this->alta();
        foreach ([false, true] as $vencer) {
            if ($vencer) {
                $this->db->prepare("UPDATE verificaciones_email SET expires_at = '2000-01-01 00:00:00' WHERE id = ?")->execute([$alta['enlace']['id']]);
            }
            try {
                $this->registro->activar($alta['enlace']['id'], $alta['enlace']['token'], ['acepta_datos' => $vencer ? '1' : '0'], '192.0.2.1');
                $this->fail('Se activó sin consentimiento o con un enlace vencido.');
            } catch (InvalidArgumentException $e) {
                $this->assertSame(0, $this->total('tipos_cita'));
                $this->assertSame(1, $this->total('consentimientos_datos'));
            }
        }
    }

    public function testSinConsentimientoNoSeCreaNada(): void
    {
        $this->expectException(InvalidArgumentException::class);
        try {
            $this->alta(1, ['acepta_datos' => '0']);
        } finally {
            $this->assertSame(3, $this->total('clinicas'));
            $this->assertSame(10, $this->total('usuarios'));
        }
    }

    public function testCaptchaYRespuestaPublicaIdenticaConCorreoNuevoOExistente(): void
    {
        $correo = new class {
            public array $enviados = [];
            public function limpiarDirecciones(): void {}
            public function obtenerPlantillaBaseHTML(...$datos): string { return implode('|', $datos); }
            public function enviarCorreoPersonalizado(...$datos): bool
            {
                $this->enviados[] = $datos;
                return true;
            }
        };
        $controller = new RegistroClinicaController($this->db, $correo, new Turnstile(fn ($token) => $token === 'desafio'));
        $sin = $controller->registrar(self::datos(1, ['cf-turnstile-response' => '']), '192.0.2.1');
        $mal = $controller->registrar(self::datos(1, ['cf-turnstile-response' => 'falso']), '192.0.2.1');
        $this->assertSame($sin, $mal);
        $this->assertFalse($sin['success']);
        $this->assertSame(3, $this->total('clinicas'));
        $nueva = $controller->registrar(self::datos(), '192.0.2.1');
        $existente = $controller->registrar(self::datos(2, ['email' => 'fabio@zooki.test']), '192.0.2.1');
        $this->assertSame(['success' => true, 'message' => RegistroClinica::MENSAJE], $nueva);
        $this->assertSame($nueva, $existente);
        $this->assertCount(2, $correo->enviados);
        $this->assertStringContainsString('Confirma que quieres registrar', $correo->enviados[1][2]);
        $this->assertStringContainsString('La contraseña escrita en el formulario no se usó.', $correo->enviados[1][3]);
        $this->assertStringContainsString('Entrarás con tu contraseña de siempre', $correo->enviados[1][3]);
        $this->assertStringContainsString('elegirás la clínica al entrar', $correo->enviados[1][3]);
        $this->assertStringContainsString('¿Olvidaste tu contraseña?', $correo->enviados[1][3]);
        $this->assertStringContainsString('recuperar=1', $correo->enviados[1][3]);
    }

    public function testD31VistaRegistroComparteCabeceraYEstructuraDelLogin(): void
    {
        ob_start();
        (new RegistroClinicaController($this->db))->formulario();
        $html = ob_get_clean();
        $login = file_get_contents(__DIR__ . '/../../views/auth/login.php');
        $this->assertStringContainsString("require __DIR__ . '/../partials/cabecera_acceso.php'", $login);
        $this->assertStringContainsString('css/styles.css', $html);
        $this->assertStringContainsString('animate.min.css', $html);
        $this->assertStringContainsString('css/validacion-cuenta.css', $html);
        $this->assertStringNotContainsString('css/reset-password.css', $html);
        $this->assertStringContainsString('Si ya tienes cuenta en Zooki, entrarás con tu contraseña de siempre.', $html);
        $dom = new DOMDocument();
        $anterior = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);
        $xpath = new DOMXPath($dom);
        $contenedor = "//div[@class='login-container']";
        $this->assertSame(1, $xpath->query($contenedor . "/div[contains(@class,'login-form-area')]")->length);
        $this->assertSame(1, $xpath->query($contenedor . "/div[contains(@class,'login-hero')]")->length);
        $this->assertSame(0, $xpath->query($contenedor . "/div[contains(@class,'login-hero')]//form")->length);
        foreach (['documento' => '15', 'nombre_completo' => '100', 'email' => '255', 'nit' => '14', 'dv' => '1'] as $campo => $maximo) {
            $input = $xpath->query("//input[@name='$campo']")->item(0);
            $this->assertSame($maximo, $input->getAttribute('maxlength'));
            $this->assertTrue($input->hasAttribute('data-caracteres'));
        }
        $css = file_get_contents(__DIR__ . '/../../public/css/registro-clinica.css');
        $this->assertStringNotContainsString('.login-container', $css);
        $this->assertStringNotContainsString('.login-form-area', $css);
        $this->assertStringContainsString('(max-width: 767px)', $css);
    }

    public function testD31SaneamientoSeGuardaYEntradaIlegalNoCreaCuenta(): void
    {
        $alta = $this->alta(1, ['nombre_clinica' => '  Clínica   Nueva  ', 'nombre_completo' => "  María  O'Neill-Peña  ", 'direccion' => '  Carrera  7 # 12-34  ', 'email' => ' NUEVA@ZOOKI.TEST ', 'telefono' => '  +57   3001234567  ']);
        $fila = $this->registro->leer($alta['enlace']['id'], $alta['enlace']['token']);
        $persona = (new Usuario($this->db))->buscarPorId((int) $fila['id_usuario']);
        $this->assertSame("María O'Neill-Peña", $persona['nombre_completo']);
        $this->assertSame('+57 3001234567', $persona['telefono']);
        $this->assertSame('nueva@zooki.test', $persona['email']);
        $this->assertSame('Clínica Nueva', $fila['clinica']);
        $this->expectException(InvalidArgumentException::class);
        $this->alta(2, ['nombre_completo' => 'Ana 123']);
    }

    public function testD31ConfirmacionExplicaPasswordOriginalAntesYDespuesDelPost(): void
    {
        $alta = $this->alta(1, ['email' => 'fabio@zooki.test']);
        $_GET = $alta['enlace'];
        $controlador = new RegistroClinicaController($this->db);
        foreach (['GET', 'POST'] as $metodo) {
            $_SERVER['REQUEST_METHOD'] = $metodo;
            if ($metodo === 'POST') {
                $_POST = $alta['enlace'] + ['csrf_token' => Csrf::token(), 'acepta_datos' => '1'];
            }
            ob_start();
            $controlador->activar();
            $html = ob_get_clean();
            $this->assertStringContainsString('La contraseña escrita en el formulario no se usó.', $html);
            $this->assertStringContainsString('Entrarás con tu contraseña de siempre.', $html);
            $this->assertStringContainsString('Al iniciar sesión elegirás la clínica.', $html);
            $this->assertStringContainsString('¿Olvidaste tu contraseña?', $html);
            if ($metodo === 'POST') {
                $this->assertStringContainsString('Tu clínica está activa', $html);
            }
        }
        $this->assertTrue((new Usuario($this->db))->verificarPassword(6, DosClinicas::PASSWORD));
    }

    public function testD3AyudaConYSinSesionRespondeSoloFormato(): void
    {
        foreach ([[], ['id_usuario' => 6]] as $sesion) {
            $respuestas = [];
            foreach (['fabio@zooki.test', 'desconocido@zooki.test'] as $email) {
                $_SESSION = $sesion + ['csrf_cuenta_validacion' => 'validacion'];
                $_POST = ['cuenta_csrf' => 'validacion', 'campo' => 'email', 'valor' => $email];
                $_SERVER['REQUEST_METHOD'] = 'POST';
                ob_start();
                (new IdentidadController($this->db))->validar();
                $respuestas[] = json_decode(ob_get_clean(), true);
            }
            $this->assertSame($respuestas[0], $respuestas[1]);
            $this->assertSame(['success' => true, 'error' => null], $respuestas[0]);
        }
    }

    public function testD3AutorregistroPropietarioExigeCaptchaYNoRevelaExistencia(): void
    {
        $correo = new class {
            public array $enviados = [];
            public function limpiarDirecciones(): void {}
            public function enviarCorreoVerificacion(...$datos): bool
            {
                $this->enviados[] = $datos;
                return true;
            }
            public function enviarCorreoPersonalizado(...$datos): bool
            {
                $this->enviados[] = $datos;
                return true;
            }
        };
        $controller = new AuthController($this->db, $correo, new Turnstile(fn ($token) => $token === 'desafio'));
        $datos = self::datos() + ['id_clinica' => '2'];
        $antes = $this->total('usuarios');
        foreach (['', 'falso'] as $token) {
            $datos['cf-turnstile-response'] = $token;
            $this->assertFalse($controller->registrarPropietario($datos, '192.0.2.99')['success']);
            $this->assertSame($antes, $this->total('usuarios'));
        }
        $datos['cf-turnstile-response'] = 'desafio';
        $nuevo = $controller->registrarPropietario($datos, '192.0.2.99');
        $datos['email'] = 'fabio@zooki.test';
        $existente = $controller->registrarPropietario($datos, '192.0.2.99');
        $this->assertSame($nuevo, $existente);
        $this->assertTrue($nuevo['success']);
        $this->assertCount(2, $correo->enviados);
        $this->assertArrayNotHasKey('registro_pendiente', $_SESSION);
    }
}
