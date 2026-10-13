<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/PropietarioClinica.php';
require_once __DIR__ . '/../../models/ActivacionPropietario.php';
require_once __DIR__ . '/../../models/CuentaTitular.php';
require_once __DIR__ . '/../../models/Mascota.php';
require_once __DIR__ . '/../../models/Vacuna.php';
require_once __DIR__ . '/../../models/Recordatorio.php';
require_once __DIR__ . '/../../models/IntentoLogin.php';
require_once __DIR__ . '/../../helpers/Autenticador.php';
require_once __DIR__ . '/../../controllers/PropietarioController.php';
require_once __DIR__ . '/../../controllers/MascotaController.php';
require_once __DIR__ . '/../../controllers/IdentidadController.php';
require_once __DIR__ . '/../../controllers/CitaController.php';

/**
 * M5 C3 — Lado del personal.
 *
 * Alta del propietario sin aceptación presencial (decisión del usuario,
 * 2026-10-12; RE-T.19.1, RN-G19, RN-109): la cuenta queda pendiente, sin
 * contraseña, consentimiento ni portal, con un enlace de 72 horas para que
 * el titular acepte la política y cree su contraseña. Búsqueda del
 * propietario con límite por IP y la misma respuesta sin vínculo, y el
 * rechazo de la mascota del personal con su campo (C2b).
 */
final class M5C3PersonalTest extends TestCase
{
    private const CLAVE = 'Nuevo#Titular2026';

    private PDO $db;
    private array $correos = [];

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
        $this->db->exec('CREATE TABLE intentos_login (id_intento INTEGER PRIMARY KEY AUTOINCREMENT, identificador TEXT UNIQUE, intentos INTEGER, bloqueado_hasta TEXT, primer_intento TEXT, ultimo_intento TEXT)');
        $this->correos = [];
        $_POST = [];
        $_GET = [];
        $_FILES = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR'] = '192.0.2.30';
        $this->comoVeterinario(DosClinicas::NORTE, DosClinicas::VET_NORTE, 'Clínica Norte');
    }

    protected function tearDown(): void
    {
        Security::definirAlmacenDeIntentos(null);
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_FILES = [];
        http_response_code(200);
    }

    private function comoVeterinario(int $clinica, int $idUsuario, string $nombre): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::deClinica($clinica, $nombre, Roles::VETERINARIO), 1);
    }

    private function controlador(): PropietarioController
    {
        return new PropietarioController($this->db, function (string $email, string $nombre, string $asunto, string $html): bool {
            $this->correos[] = ['email' => $email, 'asunto' => $asunto, 'html' => $html];
            return true;
        });
    }

    /** @return array{0: array, 1: int} la respuesta JSON y el código HTTP */
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

    private function registrarRosa(): array
    {
        $_POST = ['tipo_documento' => 'CC', 'documento' => '1000000040', 'nombre_completo' => 'Rosa Nueva', 'telefono' => '3001234567', 'email' => 'rosa@zooki.test'];
        [$respuesta] = $this->llamar(fn () => $this->controlador()->registrarAjax());
        $this->assertTrue($respuesta['success'], $respuesta['message'] ?? '');
        return $respuesta;
    }

    /** id y token del enlace que llegó en el último correo. */
    private function enlaceDelCorreo(): array
    {
        $html = html_entity_decode(end($this->correos)['html']);
        $this->assertMatchesRegularExpression('/action=activar_propietario&id=(\d+)&token=([0-9a-f]{64})/', $html);
        preg_match('/action=activar_propietario&id=(\d+)&token=([0-9a-f]{64})/', $html, $partes);
        return ['id' => (int) $partes[1], 'token' => $partes[2]];
    }

    private function contar(string $sql): int
    {
        return (int) $this->db->query($sql)->fetchColumn();
    }

    private function mascotaDe(int $idPropietario): int
    {
        return (new Mascota($this->db))->insert(['id_propietario' => $idPropietario, 'nombre' => 'Copito', 'id_especie' => 1, 'id_raza' => 49,
            'fecha_nacimiento' => '2023-01-01', 'peso' => '3.50', 'sexo' => 'Macho', 'colores' => [1]]);
    }

    public function testElAltaDejaLaCuentaPendienteYAlActivarQuedanLaPruebaYLaContrasena(): void
    {
        $alta = $this->registrarRosa();

        $this->assertCount(1, $this->correos, 'Solo el correo de activación.');
        $this->assertSame('rosa@zooki.test', $this->correos[0]['email']);
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM consentimientos_datos'));
        $enlace = $this->enlaceDelCorreo();

        // La página de activación (GET) no cambia nada y no ofrece «No acepto» al propietario.
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = $enlace;
        ob_start();
        (new IdentidadController($this->db))->enlace(ActivacionPropietario::PROPOSITO);
        $pagina = (string) ob_get_clean();
        $this->assertStringContainsString('Activa tu portal', $pagina);
        $this->assertStringContainsString('Clínica Norte te registró en Zooki', $pagina);
        $this->assertStringContainsString('action=activar_propietario', $pagina);
        $this->assertStringNotContainsString('No acepto', $pagina);
        $this->assertSame(0, $this->contar('SELECT estado FROM usuarios WHERE id_usuario = ' . (int) $alta['id_usuario']));

        (new CuentaTitular($this->db))->activar($enlace['id'], $enlace['token'], ['password' => self::CLAVE, 'confirm_password' => self::CLAVE, 'acepta_datos' => '1'], '192.0.2.30', ActivacionPropietario::PROPOSITO);

        $usuarios = new Usuario($this->db);
        $this->assertTrue($usuarios->verificarPassword((int) $alta['id_usuario'], self::CLAVE));
        $prueba = $this->db->query('SELECT * FROM consentimientos_datos')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame((int) $alta['id_usuario'], (int) $prueba['id_usuario']);
        $this->assertSame(PoliticaDatos::VERSION, $prueba['version_politica']);
        $this->assertSame('alta_personal', $prueba['medio']);
        $this->assertSame('192.0.2.30', $prueba['ip_address']);
        $this->assertSame('ok', (new Autenticador($usuarios, new VerificacionEmail($this->db)))->conPassword('rosa@zooki.test', self::CLAVE)['resultado']);
        $this->assertFalse((new ActivacionPropietario($this->db))->esPendiente((int) $alta['id_usuario']));
    }

    public function testSinAceptarLaPoliticaNoSeActiva(): void
    {
        $this->registrarRosa();
        $enlace = $this->enlaceDelCorreo();

        try {
            (new CuentaTitular($this->db))->activar($enlace['id'], $enlace['token'], ['password' => self::CLAVE, 'confirm_password' => self::CLAVE], '192.0.2.30', ActivacionPropietario::PROPOSITO);
            $this->fail('Debió exigir la aceptación.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('política', $e->getMessage());
        }
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM consentimientos_datos'));
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM usuarios WHERE email = 'rosa@zooki.test' AND password IS NOT NULL"));
    }

    public function testAntesDeActivarNoEntraNiRecibeCorreosYLaClinicaRegistraYAtiendeSuMascota(): void
    {
        $alta = $this->registrarRosa();
        $idRosa = (int) $alta['id_usuario'];
        $auth = new Autenticador(new Usuario($this->db), new VerificacionEmail($this->db));
        $this->assertSame('fallo', $auth->conPassword('rosa@zooki.test', self::CLAVE)['resultado'], 'Sin contraseña ni portal.');
        $this->assertSame('inactiva', $auth->conGoogle('rosa@zooki.test')['resultado']);

        $idMascota = $this->mascotaDe($idRosa);
        $hoy = date('Y-m-d');
        $pronto = date('Y-m-d', strtotime('+3 days'));
        (new Vacuna($this->db))->registrar(['id_mascota' => $idMascota, 'nombre_vacuna' => 'Rabia', 'fecha_aplicacion' => $hoy, 'fecha_proxima_dosis' => $pronto]);
        // Control: a Fabio, que sí activó su cuenta, le toca el recordatorio de Luna.
        $this->db->exec("INSERT INTO vacunas (id_clinica, id_mascota, id_veterinario, nombre_vacuna, fecha_aplicacion, fecha_proxima_dosis) VALUES (1, 1, 2, 'Rabia', '$hoy', '$pronto')");
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM vacunas WHERE id_mascota = $idMascota"), 'La clínica atiende a la mascota.');
        (new Mascota($this->db))->update(['id_mascota' => $idMascota, 'peso' => '4.20']);

        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM notificaciones WHERE id_usuario = $idRosa"), 'Sin aviso de cambio de ficha.');
        $correos = array_column((new Recordatorio($this->db))->dosisPorRecordar($hoy), 'email');
        $this->assertContains('fabio@zooki.test', $correos);
        $this->assertNotContains('rosa@zooki.test', $correos, 'Sin recordatorios a esa dirección.');
        $cita = ['email' => 'rosa@zooki.test', 'propietario_estado' => 0, 'propietario_nombre' => 'Rosa Nueva'];
        $enviar = new ReflectionMethod(CitaController::class, 'enviarCorreoCita');
        $this->assertFalse($enviar->invoke(new CitaController($this->db), $cita, 'confirmacion'), 'Sin correos de citas.');
        $this->assertCount(1, $this->correos, 'Solo salió el de activación.');
    }

    public function testReenviarInvalidaElEnlaceAnteriorYSoloLoHaceSuClinica(): void
    {
        $alta = $this->registrarRosa();
        $anterior = $this->enlaceDelCorreo();
        $cuentas = new CuentaTitular($this->db);

        $_POST = ['id_usuario' => (string) $alta['id_usuario']];
        [$respuesta] = $this->llamar(fn () => $this->controlador()->reenviarActivacionAjax());
        $this->assertTrue($respuesta['success']);
        $nuevo = $this->enlaceDelCorreo();

        $this->assertNotSame($anterior['id'], $nuevo['id']);
        $this->assertNull($cuentas->leerEnlace($anterior['id'], $anterior['token'], ActivacionPropietario::PROPOSITO), 'El anterior ya no sirve.');
        $this->assertTrue((new InvitacionPersonal($this->db))->reemplazada($anterior['id'], $anterior['token'], ActivacionPropietario::PROPOSITO), 'Y dice que hay uno más reciente.');
        $this->assertNotNull($cuentas->leerEnlace($nuevo['id'], $nuevo['token'], ActivacionPropietario::PROPOSITO));

        // Aislamiento: Sur no le reenvía nada a un cliente de Norte.
        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR, 'Clínica Sur');
        [$respuesta, $codigo] = $this->llamar(fn () => $this->controlador()->reenviarActivacionAjax());
        $this->assertSame(422, $codigo);
        $this->assertFalse($respuesta['success']);
        $this->assertCount(2, $this->correos);
    }

    public function testLaLimpiezaNoBorraUnaCuentaPendienteConMascotas(): void
    {
        $conMascota = (int) $this->registrarRosa()['id_usuario'];
        $this->mascotaDe($conMascota);
        $_POST = ['tipo_documento' => 'CC', 'documento' => '1000000041', 'nombre_completo' => 'Sergio Nuevo', 'telefono' => '3001234568', 'email' => 'sergio@zooki.test'];
        [$sergio] = $this->llamar(fn () => $this->controlador()->registrarAjax());
        $sinMascota = (int) $sergio['id_usuario'];
        $this->db->exec("UPDATE verificaciones_email SET expires_at = '2000-01-01 00:00:00' WHERE proposito = 'activacion_propietario'");

        $borradas = (new CuentaTitular($this->db))->limpiarPendientes();

        $this->assertSame(1, $borradas);
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM usuarios WHERE id_usuario = $sinMascota"));
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM propietario_clinica WHERE id_propietario = $sinMascota"));
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM usuarios WHERE id_usuario = $conMascota"), 'Con mascotas se conserva.');
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM propietario_clinica WHERE id_propietario = $conMascota AND estado = 'activo'"));

        // La clínica le reenvía el enlace y puede activar su cuenta.
        $_POST = ['id_usuario' => (string) $conMascota];
        [$respuesta] = $this->llamar(fn () => $this->controlador()->reenviarActivacionAjax());
        $this->assertTrue($respuesta['success']);
    }

    /**
     * RN-109 y decisión del usuario (M5 C3): otra clínica ve lo mismo que con
     * cualquier cuenta sin vínculo; si el titular confirma, queda ligado y su
     * cuenta sigue pendiente; la clínica nueva también le reenvía el enlace.
     */
    public function testUnaCuentaPendienteDeNorteVaPorLaConfirmacionDelVinculoDesdeSur(): void
    {
        $idRosa = (int) $this->registrarRosa()['id_usuario'];
        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR, 'Clínica Sur');
        $sur = new PropietarioClinica($this->db);

        $esperado = ['vinculado' => false, 'mensaje' => PropietarioClinica::MENSAJE_SIN_VINCULO];
        $this->assertSame($esperado, $sur->buscarExacto('1000000040'));
        $this->assertSame($esperado, $sur->buscarExacto('rosa@zooki.test'));
        $this->assertSame($esperado, $sur->buscarExacto('1000000006'), 'Igual que alguien nunca vinculado.');
        $this->assertSame($esperado, $sur->buscarExacto('gina@zooki.test'), 'Igual que el super-administrador.');
        $this->assertNotContains($idRosa, array_map('intval', array_column($sur->listar(), 'id_usuario')));

        // Registrarla en Sur con su documento tampoco la duplica.
        $_POST = ['tipo_documento' => 'CC', 'documento' => '1000000040', 'nombre_completo' => 'Rosa Otra', 'telefono' => '3001234567', 'email' => 'otra@zooki.test'];
        [$respuesta, $codigo] = $this->llamar(fn () => $this->controlador()->registrarAjax());
        $this->assertSame(409, $codigo);
        $this->assertSame('1000000040', $respuesta['identificador']);

        $solicitud = $sur->solicitarVinculo('rosa@zooki.test');
        $this->assertTrue($sur->confirmarVinculo($solicitud['id_enlace'], $solicitud['token']));
        $this->assertSame('activo', $this->db->query("SELECT estado FROM propietario_clinica WHERE id_propietario = $idRosa AND id_clinica = 2")->fetchColumn());
        $this->assertTrue((new ActivacionPropietario($this->db))->esPendiente($idRosa), 'Sigue pendiente hasta activar.');
        $this->assertTrue($sur->buscarExacto('1000000040')['pendiente']);
        $this->assertSame(DosClinicas::SUR, (int) $this->db->query("SELECT id_clinica_vinculo FROM verificaciones_email WHERE id = " . $sur->reenviarActivacion($idRosa)['id'])->fetchColumn());
    }

    /** RE-1.5.1 (M5 C3): la búsqueda corre al escribir; el mismo límite por IP que el NIT. */
    public function testLaBusquedaDelPropietarioTieneLimitePorIp(): void
    {
        Security::definirAlmacenDeIntentos(new IntentoLogin($this->db));
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['identificador' => '1000000006'];
        $controlador = new MascotaController($this->db);

        for ($i = 0; $i < 20; $i++) {
            [$respuesta, $codigo] = $this->llamar(fn () => $controlador->buscarPropietarioExactoAjax());
            $this->assertSame(200, $codigo, "búsqueda $i");
            $this->assertTrue($respuesta['success']);
        }
        [$respuesta, $codigo] = $this->llamar(fn () => $controlador->buscarPropietarioExactoAjax());

        $this->assertSame(429, $codigo);
        $this->assertSame(MascotaController::MENSAJE_ESPERA, $respuesta['message']);
        $this->assertArrayNotHasKey('propietario', $respuesta);
    }

    /** M5 C3: el formulario de mascota del personal usa las reglas y avisos de C2b. */
    public function testElServidorDiceElCampoYSuAvisoAlRechazarUnaMascotaDelPersonal(): void
    {
        $_POST = ['id_propietario' => '6', 'nombre' => 'Toby', 'especie' => '1', 'raza' => '49', 'sexo' => 'Macho', 'peso' => '999', 'fecha_nacimiento' => '2023-01-01', 'colores' => ['1']];
        [$respuesta, $codigo] = $this->llamar(fn () => (new MascotaController($this->db))->registrarAjax());
        $this->assertSame(422, $codigo);
        $this->assertSame('peso', $respuesta['campo']);
        $this->assertSame(ValidadorMascota::AVISOS['peso'], $respuesta['aviso']);

        $_POST['peso'] = '4.5';
        $_POST['colores'] = [];
        [$respuesta] = $this->llamar(fn () => (new MascotaController($this->db))->registrarAjax());
        $this->assertSame('colores[]', $respuesta['campo']);
        $this->assertSame(ValidadorMascota::AVISO_COLORES, $respuesta['aviso']);

        $_POST['colores'] = ['1'];
        $_POST['especie'] = '';
        [$respuesta] = $this->llamar(fn () => (new MascotaController($this->db))->registrarAjax());
        $this->assertSame('especie', $respuesta['campo']);
        $this->assertSame(1, $this->contar('SELECT COUNT(*) FROM mascotas'), 'No guarda nada.');
    }

    /** Las vistas del personal: sin aceptación presencial y con las reglas de ValidadorMascota. */
    public function testLasVistasDelPersonalYaNoPidenLaAceptacionPresencial(): void
    {
        $pacientes = file_get_contents(__DIR__ . '/../../views/vet/pacientes.php');

        $this->assertStringNotContainsString('titular_presente', $pacientes);
        $this->assertStringNotContainsString('acepta_politica', $pacientes);
        $this->assertStringNotContainsString('Soy el titular', $pacientes);
        $this->assertSame(2, substr_count($pacientes, 'novalidate data-validacion-mascota'), 'Registrar y editar.');
        $this->assertSame(2, substr_count($pacientes, "ValidadorMascota::atributosHtml('peso')"));
        $this->assertStringContainsString('data-buscar-propietario', $pacientes);
        $this->assertStringNotContainsString('buscarPropietarioExacto', $pacientes, 'Sin botón: se busca al escribir.');
        $layout = file_get_contents(__DIR__ . '/../../views/vet/layout.php');
        $this->assertStringContainsString('js/validacion-mascota.js', $layout);
        $this->assertStringContainsString('js/propietario-mascota.js', $layout);
        foreach (['views/vet/pacientes.php', 'views/admin/usuarios.php', 'views/admin/partials/acciones_cliente.php'] as $vista) {
            $html = file_get_contents(__DIR__ . '/../../' . $vista);
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+="/', $html, "$vista sin eventos en línea.");
        }
    }
}
