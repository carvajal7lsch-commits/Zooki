<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/PropietarioClinica.php';
require_once __DIR__ . '/../../models/ActivacionPropietario.php';
require_once __DIR__ . '/../../models/CuentaTitular.php';
require_once __DIR__ . '/../../models/Mascota.php';
require_once __DIR__ . '/../../helpers/Autenticador.php';
require_once __DIR__ . '/../../controllers/PropietarioController.php';
require_once __DIR__ . '/../../controllers/MascotaController.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/IdentidadController.php';

/**
 * M5 C4b — Lado de la clínica y pendientes técnicos.
 *
 * Búsqueda del propietario por nombre solo entre los clientes activos de la
 * clínica; recuperar la activación sin la clínica por las tres vías sin
 * revelar si la cuenta existe (decisión 2); correos temporales rechazados en
 * el registro, el alta por la clínica y el cambio de correo (decisión 3,
 * RE-0.1.5); la mascota se registra sin colores (decisión 1) y la activación
 * guarda su propio medio de consentimiento (migración 06).
 */
final class M5C4bPersonalTest extends TestCase
{
    private const CLAVE = 'Bosque#Seguro2026';

    private PDO $db;
    private array $correos = [];

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
        $this->correos = [];
        $_POST = [];
        $_GET = [];
        $_FILES = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR'] = '192.0.2.40';
        $this->comoVeterinario(DosClinicas::NORTE, DosClinicas::VET_NORTE, 'Clínica Norte');
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        http_response_code(200);
    }

    private function comoVeterinario(int $clinica, int $idUsuario, string $nombre): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::deClinica($clinica, $nombre, Roles::VETERINARIO), 1);
    }

    /** @return array{0: array, 1: int} */
    private function llamar(callable $accion): array
    {
        http_response_code(200);
        ob_start();
        try {
            $accion();
        } finally {
            $salida = (string) ob_get_clean();
        }
        return [json_decode($salida, true) ?? [], http_response_code()];
    }

    private function contar(string $sql): int
    {
        return (int) $this->db->query($sql)->fetchColumn();
    }

    /** Norte registra a Rosa: cuenta pendiente con su enlace de activación (M5 C3). */
    private function registrarRosa(): int
    {
        $_POST = ['tipo_documento' => 'CC', 'documento' => '1000000040', 'nombre_completo' => 'Rosa Nueva', 'telefono' => '3001234567', 'email' => 'rosa@zooki.test'];
        $controlador = new PropietarioController($this->db, fn (): bool => true);
        [$respuesta] = $this->llamar(fn () => $controlador->registrarAjax());
        $this->assertTrue($respuesta['success'], $respuesta['message'] ?? '');
        return (int) $this->db->query("SELECT id_usuario FROM usuarios WHERE email = 'rosa@zooki.test'")->fetchColumn();
    }

    private function correo(): object
    {
        $correos = &$this->correos;
        return new class ($correos) {
            public function __construct(private array &$enviados) {}
            public function limpiarDirecciones(): void {}
            public function enviarCorreoPersonalizado(string $email, string $nombre, string $asunto, string $html): bool
            {
                $this->enviados[] = ['email' => $email, 'asunto' => $asunto, 'html' => $html];
                return true;
            }
            public function enviarCorreoVerificacion(...$datos): bool
            {
                $this->enviados[] = ['email' => $datos[0], 'asunto' => 'verificacion', 'html' => ''];
                return true;
            }
            public function enviarCorreoBienvenida(...$datos): bool { return true; }
        };
    }

    private function auth(): AuthController
    {
        return new AuthController($this->db, $this->correo(), new Turnstile(static fn (string $token): bool => $token === 'valido'));
    }

    /** Enlaces de activación vigentes de una persona. */
    private function enlacesVigentes(int $id): int
    {
        return $this->contar("SELECT COUNT(*) FROM verificaciones_email WHERE id_usuario = $id AND proposito = 'activacion_propietario' AND used = 0");
    }

    // ── Punto 3: buscar por nombre ──────────────────────────────────────

    public function testPorNombreSoloSalenLosClientesActivosDeLaClinica(): void
    {
        $modelo = new PropietarioClinica($this->db);
        $this->assertSame(['Fabio Dueño'], array_column($modelo->buscarPorNombre('fab'), 'nombre_completo'), 'Coincidencia parcial y sin mayúsculas.');
        $this->assertSame('1000000006', $modelo->buscarPorNombre('Fabio')[0]['identificador'], 'Con él se trae su ficha por la búsqueda exacta.');

        // Desvinculado de Norte: deja de salir.
        $this->db->exec("UPDATE propietario_clinica SET estado = 'inactivo' WHERE id_propietario = 6 AND id_clinica = 1");
        $this->assertSame([], $modelo->buscarPorNombre('Fabio'));

        // Aislamiento: Sur no ve a los clientes de Norte, aunque el nombre coincida.
        $this->db->exec("UPDATE propietario_clinica SET estado = 'activo' WHERE id_propietario = 6 AND id_clinica = 1");
        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR, 'Clínica Sur');
        $this->assertSame([], (new PropietarioClinica($this->db))->buscarPorNombre('Fabio'));
    }

    public function testPorNombreExigeTresLetrasYNoAceptaComodines(): void
    {
        $modelo = new PropietarioClinica($this->db);
        foreach (['Fa', '', '%%%', 'Fab_o', '1000000006', 'fabio@zooki.test'] as $texto) {
            $this->assertSame([], $modelo->buscarPorNombre($texto), "«{$texto}»");
        }
    }

    public function testElPersonalBuscaPorNombreDesdeElControladorConSuPropioLimite(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['nombre' => 'fabio'];
        [$respuesta, $codigo] = $this->llamar(fn () => (new MascotaController($this->db))->buscarPropietarioNombreAjax());

        $this->assertSame(200, $codigo);
        $this->assertSame([6], array_column($respuesta['clientes'], 'id_usuario'));
        $this->assertArrayNotHasKey('email', $respuesta['clientes'][0], 'Solo lo necesario para elegirlo.');
    }

    // ── Punto 5: recuperar la activación sin la clínica ─────────────────

    public function testOlvideMiContrasenaConUnaCuentaPendienteEnviaUnEnlaceDeActivacionNuevo(): void
    {
        $idRosa = $this->registrarRosa();
        $anterior = (int) $this->db->query("SELECT id FROM verificaciones_email WHERE id_usuario = $idRosa AND proposito = 'activacion_propietario'")->fetchColumn();
        $_SESSION = [];

        $this->auth()->renovarActivacionPendiente($idRosa);

        $this->assertCount(1, $this->correos);
        $this->assertSame(ActivacionPropietario::ASUNTO, $this->correos[0]['asunto']);
        $this->assertSame('rosa@zooki.test', $this->correos[0]['email']);
        $this->assertStringContainsString('Clínica Norte te registró', $this->correos[0]['html'], 'Con la clínica que lo registró.');
        $this->assertStringContainsString('action=activar_propietario', html_entity_decode($this->correos[0]['html']));
        $this->assertSame(1, $this->enlacesVigentes($idRosa));
        $this->assertSame(1, $this->contar("SELECT used FROM verificaciones_email WHERE id = $anterior"), 'El enlace anterior deja de servir.');

        // Una cuenta activa no recibe nada por esta vía (tiene su restablecimiento de siempre).
        $this->auth()->renovarActivacionPendiente(DosClinicas::PROPIETARIO);
        $this->assertCount(1, $this->correos);
        // Y la ruta lo llama con la misma respuesta genérica (RN-G15).
        $ruta = file_get_contents(__DIR__ . '/../../controllers/AuthController.php');
        $this->assertMatchesRegularExpression('/renovarActivacionPendiente\(\(int\) \$user\[.id_usuario.\]\);\s*\}\s*if \(!\$user \|\| \(int\)\(\$user\[.estado.\] \?\? 0\) !== 1\) \{\s*\$this->jsonResponse\(true, \$mensajeGenerico\);/', $ruta);
    }

    public function testElAutorregistroConElCorreoDeUnaCuentaPendienteResponseIgualYEnviaLaActivacion(): void
    {
        $idRosa = $this->registrarRosa();
        $_SESSION = [];
        $formulario = ['tipo_documento' => 'CC', 'documento' => '1000000040', 'nombre_completo' => 'Rosa Nueva', 'telefono' => '3001234567',
            'email' => 'rosa@zooki.test', 'password' => self::CLAVE, 'confirm_password' => self::CLAVE, 'acepta_datos' => '1', 'id_clinica' => '2', 'cf-turnstile-response' => 'valido'];

        $pendiente = $this->auth()->registrarPropietario($formulario, '192.0.2.40');
        $nueva = $this->auth()->registrarPropietario(['email' => 'nadie@zooki.test', 'documento' => '1000000099'] + $formulario, '192.0.2.40');

        $this->assertSame($nueva, $pendiente, 'RN-G15: la misma respuesta exista o no la cuenta.');
        $this->assertSame(ActivacionPropietario::ASUNTO, $this->correos[0]['asunto']);
        $this->assertSame('rosa@zooki.test', $this->correos[0]['email']);
        $this->assertSame(1, $this->enlacesVigentes($idRosa));
        $this->assertTrue((new ActivacionPropietario($this->db))->esPendiente($idRosa), 'Sigue pendiente hasta que active.');
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM propietario_clinica WHERE id_propietario = $idRosa AND id_clinica = 2"), 'No se liga a otra clínica sin activar.');
    }

    public function testGoogleConUnaCuentaPendientePideAceptarLaPoliticaYLaActivaSinContrasena(): void
    {
        $idRosa = $this->registrarRosa();
        $_SESSION = [];
        $auth = $this->auth();
        $usuario = (new Usuario($this->db))->buscarPorId($idRosa);

        $respuesta = $auth->activacionPendienteConGoogle($usuario, 'rosa@zooki.test', 'Rosa Nueva', 'google-rosa');
        $this->assertSame(['action' => 'activar_cuenta', 'email' => 'rosa@zooki.test', 'clinica' => 'Clínica Norte'], $respuesta);
        $this->assertSame($idRosa, $_SESSION['google_pendiente']['id_activacion']);

        try {
            $auth->completarConGoogle($_SESSION['google_pendiente'], [], '192.0.2.40');
            $this->fail('Sin aceptar la política no se activa.');
        } catch (InvalidArgumentException $e) {
            $this->assertTrue((new ActivacionPropietario($this->db))->esPendiente($idRosa));
        }

        $this->assertSame($idRosa, $auth->completarConGoogle($_SESSION['google_pendiente'], ['acepta_datos' => '1'], '192.0.2.40'));
        $cuenta = $this->db->query("SELECT estado, password, google_uid FROM usuarios WHERE id_usuario = $idRosa")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, (int) $cuenta['estado']);
        $this->assertNull($cuenta['password'], 'RN-G21: sin contraseña; entra con Google.');
        $this->assertSame('google-rosa', $cuenta['google_uid']);
        $this->assertSame('google', $this->db->query("SELECT medio FROM consentimientos_datos WHERE id_usuario = $idRosa")->fetchColumn());
        $this->assertSame(0, $this->enlacesVigentes($idRosa), 'Los enlaces de activación dejan de servir.');
        $this->assertSame('ok', (new Autenticador(new Usuario($this->db), new VerificacionEmail($this->db)))->conGoogle('rosa@zooki.test')['resultado']);

        // Una cuenta activa o inactiva sin activación pendiente sigue el camino de siempre.
        $this->assertNull($auth->activacionPendienteConGoogle($cuenta + ['id_usuario' => $idRosa], 'rosa@zooki.test', 'Rosa Nueva', 'google-rosa'));
    }

    public function testLaActivacionPorEnlaceGuardaSuPropioMedioDeConsentimiento(): void
    {
        $idRosa = $this->registrarRosa();
        $_SESSION = [];
        $enlace = (new ActivacionPropietario($this->db))->renovarPorTitular($idRosa);

        (new CuentaTitular($this->db))->activar($enlace['id'], $enlace['token'], ['acepta_datos' => '1', 'password' => self::CLAVE, 'confirm_password' => self::CLAVE], '192.0.2.40', ActivacionPropietario::PROPOSITO);

        $this->assertSame(PoliticaDatos::MEDIO_ACTIVACION_PROPIETARIO, $this->db->query("SELECT medio FROM consentimientos_datos WHERE id_usuario = $idRosa")->fetchColumn());
        $this->assertNull((new ActivacionPropietario($this->db))->renovarPorTitular($idRosa), 'Ya activa: no se renueva nada.');
    }

    // ── Punto 6: correos temporales ─────────────────────────────────────

    public function testUnCorreoTemporalSeRechazaEnLosTresFormulariosYAlEscribir(): void
    {
        $temporal = 'rosa@mailinator.com';
        $mensaje = 'No se admiten correos temporales.';
        $this->assertSame($mensaje, ValidadorCuenta::correoDeCuenta($temporal));
        $this->assertSame($mensaje, ValidadorCuenta::correoDeCuenta('Rosa@Sub.Mailinator.com'), 'También un subdominio y con mayúsculas.');
        $this->assertNull(ValidadorCuenta::correoDeCuenta('rosa@zooki.test'));

        // Alta por la clínica.
        $_POST = ['tipo_documento' => 'CC', 'documento' => '1000000041', 'nombre_completo' => 'Rosa Nueva', 'telefono' => '3001234567', 'email' => $temporal];
        [$respuesta, $codigo] = $this->llamar(fn () => (new PropietarioController($this->db, fn (): bool => true))->registrarAjax());
        $this->assertFalse($respuesta['success']);
        $this->assertSame($mensaje, $respuesta['message']);
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM usuarios WHERE documento = '1000000041'"));

        // Autorregistro.
        $_SESSION = [];
        $formulario = ['tipo_documento' => 'CC', 'documento' => '1000000042', 'nombre_completo' => 'Rosa Nueva', 'telefono' => '3001234567',
            'email' => $temporal, 'password' => self::CLAVE, 'confirm_password' => self::CLAVE, 'acepta_datos' => '1', 'id_clinica' => '1', 'cf-turnstile-response' => 'valido'];
        $this->assertSame(['success' => false, 'message' => $mensaje], $this->auth()->registrarPropietario($formulario, '192.0.2.40'));

        // Cambio de correo.
        try {
            (new CuentaTitular($this->db))->solicitarCorreo(DosClinicas::PROPIETARIO, ['email' => $temporal, 'password_actual' => DosClinicas::PASSWORD]);
            $this->fail('Debió rechazar el correo temporal.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame($mensaje, $e->getMessage());
        }

        // La ayuda al escribir dice lo mismo, sin consultar si la cuenta existe.
        $_SESSION = ['id_usuario' => DosClinicas::PROPIETARIO, 'csrf_cuenta_validacion' => 'validacion'];
        $_POST = ['cuenta_csrf' => 'validacion', 'campo' => 'email', 'valor' => $temporal];
        [$ayuda] = $this->llamar(fn () => (new IdentidadController($this->db))->validar());
        $this->assertSame($mensaje, $ayuda['error'] ?? null);
    }

    // ── Punto 4: sin colores ────────────────────────────────────────────

    public function testLaMascotaDelPersonalSeRegistraSinColores(): void
    {
        $_POST = ['id_propietario' => '6', 'nombre' => 'Copito', 'especie' => '1', 'raza' => '49', 'sexo' => 'Macho', 'peso' => '3,5', 'fecha_nacimiento' => '2023-01-01', 'colores' => ['1', '2']];
        [$respuesta, $codigo] = $this->llamar(fn () => (new MascotaController($this->db))->registrarAjax());

        $this->assertSame(200, $codigo, $respuesta['message'] ?? '');
        $this->assertTrue($respuesta['success']);
        $id = (int) $this->db->query("SELECT id_mascota FROM mascotas WHERE nombre = 'Copito'")->fetchColumn();
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM mascota_colores WHERE id_mascota = $id"), 'El servidor ya no toma colores del formulario.');

        $vista = file_get_contents(__DIR__ . '/../../views/vet/pacientes.php');
        $this->assertStringNotContainsString('colores[]', $vista);
        $this->assertStringNotContainsString('Colores', $vista);
        $this->assertStringNotContainsString('listar_colores_ajax', file_get_contents(__DIR__ . '/../../public/js/medical-module.js'));
    }
}
