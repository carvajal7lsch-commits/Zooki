<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/RegistroPropietario.php';
require_once __DIR__ . '/../../helpers/InicioSesion.php';
require_once __DIR__ . '/../../helpers/Security.php';
require_once __DIR__ . '/../../controllers/CuentaController.php';

/**
 * HU-T.18 (RE-T.18.2/3/4; RN-G19 a RN-G22) — Registro con Google: política
 * antes de crear la cuenta, perfil incompleto sin portal y una cuenta
 * existente que se liga a Google.
 */
final class RegistroGoogleTest extends TestCase
{
    private PDO $db;
    private RegistroPropietario $registro;

    private const GOOGLE = ['email' => 'pia@gmail.test', 'nombre_completo' => 'Pía Google', 'google_uid' => 'google-sub-pia'];

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        $this->registro = new RegistroPropietario($this->db);
        $usuarios = new Usuario($this->db);
        Security::definirFuenteDeContextos(static fn (int $id): ?array => $usuarios->contextosDe($id));
        Security::definirAuditoria(new Auditoria($this->db));
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        Security::definirFuenteDeContextos(null);
        Security::definirAuditoria(false);
        $_SESSION = [];
    }

    private function contar(string $sql): int
    {
        return (int) $this->db->query($sql)->fetchColumn();
    }

    private function denegacion(string $accion): ?AccesoDenegado
    {
        try {
            Security::autorizar($accion);
            return null;
        } catch (AccesoDenegado $e) {
            return $e;
        }
    }

    public function testSinAceptarLaPoliticaNoSeGuardaNiLaIdentidadNiElVinculo(): void
    {
        $antes = $this->contar('SELECT COUNT(*) FROM usuarios');
        try {
            $this->registro->conGoogle(self::GOOGLE, ['id_clinica' => '1'], '10.0.0.1');
            $this->fail('Se creó la cuenta de Google sin aceptar la política.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame($antes, $this->contar('SELECT COUNT(*) FROM usuarios'));
            $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM usuarios WHERE email = 'pia@gmail.test'"));
            $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM consentimientos_datos'));
            $this->assertSame(3, $this->contar('SELECT COUNT(*) FROM propietario_clinica'));
        }
    }

    public function testConAceptacionQuedaEnLaClinicaConPruebaYPerfilIncompleto(): void
    {
        $id = $this->registro->conGoogle(self::GOOGLE, ['id_clinica' => '2', 'acepta_datos' => '1'], '10.0.0.1');

        $cuenta = $this->db->query("SELECT * FROM usuarios WHERE id_usuario = $id")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('pia@gmail.test', $cuenta['email']);
        $this->assertSame('Pía Google', $cuenta['nombre_completo']);
        $this->assertSame('google-sub-pia', $cuenta['google_uid']);
        $this->assertNull($cuenta['password'], 'RN-G22: sin contraseña.');
        $this->assertNull($cuenta['documento']);
        $this->assertSame(0, (int) $cuenta['perfil_completo']);
        $this->assertSame('google', $this->db->query("SELECT medio FROM consentimientos_datos WHERE id_usuario = $id")->fetchColumn());
        $this->assertSame('activo', $this->db->query("SELECT estado FROM propietario_clinica WHERE id_propietario = $id AND id_clinica = 2")->fetchColumn());
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM propietario_clinica WHERE id_propietario = $id AND id_clinica <> 2"));
    }

    public function testConElPerfilIncompletoNoAgendaNiRegistraMascotasHastaCompletarlo(): void
    {
        $id = $this->registro->conGoogle(self::GOOGLE, ['id_clinica' => '1', 'acepta_datos' => '1'], '10.0.0.1');
        $usuario = (new Usuario($this->db))->buscarPorId($id);

        $this->assertSame('portal_propietario', (new InicioSesion($this->db))->abrir($usuario, 'google'));
        foreach (['portal_agendar_cita_ajax', 'portal_registrar_mascota_ajax', 'portal_propietario'] as $accion) {
            $e = $this->denegacion($accion);
            $this->assertNotNull($e, "$accion pasó con el perfil incompleto.");
            $this->assertSame('completar_perfil', $e->redirigir());
        }
        $this->assertNull($this->denegacion('completar_perfil'));

        $controlador = new CuentaController($this->db);
        try {
            $controlador->guardarPerfil($id, ['tipo_documento' => 'CC', 'documento' => '1000000001', 'telefono' => '3001234567']);
            $this->fail('Aceptó el documento de otra cuenta.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(1, (int) $_SESSION['perfil_incompleto']);
        }
        try {
            $controlador->guardarPerfil($id, ['tipo_documento' => 'CC', 'documento' => '1000000088', 'telefono' => '(300) 123']);
            $this->fail('Aceptó un teléfono inválido.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(ValidadorTelefono::MENSAJE, $e->getMessage());
        }

        $controlador->guardarPerfil($id, ['tipo_documento' => 'CC', 'documento' => '1000000088', 'telefono' => '300 123 4567']);

        $this->assertSame(1, $this->contar("SELECT perfil_completo FROM usuarios WHERE id_usuario = $id"));
        $this->assertNull($this->denegacion('portal_agendar_cita_ajax'));
        $this->assertNull($this->denegacion('portal_registrar_mascota_ajax'));
    }

    public function testUnaCuentaVerificadaConservaSuContrasenaAlEntrarConGoogle(): void
    {
        $ana = (new Usuario($this->db))->buscarPorId(DosClinicas::ADMIN_NORTE);
        $hash = $this->db->query('SELECT password FROM usuarios WHERE id_usuario = 1')->fetchColumn();

        $ligada = $this->registro->vincularGoogle($ana, 'google-sub-ana');

        $this->assertSame('google-sub-ana', $this->db->query('SELECT google_uid FROM usuarios WHERE id_usuario = 1')->fetchColumn());
        $this->assertSame(1, (int) $ligada['tiene_google']);
        $this->assertSame(1, (int) $ligada['perfil_completo']);
        $this->assertSame($hash, $this->db->query('SELECT password FROM usuarios WHERE id_usuario = 1')->fetchColumn());
        $this->assertSame(10, $this->contar('SELECT COUNT(*) FROM usuarios'), 'RE-T.18.4: no se crea otra cuenta.');
    }

    public function testUnaCuentaPendienteQuedaVerificadaSinContrasenaYConPerfilPorConfirmar(): void
    {
        $r = $this->registro->conFormulario([
            'id_clinica' => '2', 'tipo_documento' => 'CC', 'documento' => '1000000098', 'nombre_completo' => 'Quien Sea',
            'telefono' => '3001112233', 'email' => 'pia@gmail.test', 'password' => 'Ajena#Clave2026',
            'confirm_password' => 'Ajena#Clave2026', 'acepta_datos' => '1',
        ], '10.0.0.9');
        $usuario = (new Usuario($this->db))->buscarPorId($r['id_usuario']);

        $ligada = $this->registro->vincularGoogle($usuario, 'google-sub-pia');

        $this->assertNull($this->db->query("SELECT password FROM usuarios WHERE id_usuario = {$r['id_usuario']}")->fetchColumn(), 'RN-G21: la contraseña pudo crearla otra persona.');
        $this->assertSame(0, (int) $ligada['perfil_completo']);
        $this->assertFalse((new VerificacionEmail($this->db))->hayPendiente($r['id_usuario']));
        $this->assertSame('activo', $this->db->query("SELECT estado FROM propietario_clinica WHERE id_propietario = {$r['id_usuario']} AND id_clinica = 2")->fetchColumn());
    }

    public function testUnaCuentaDeGoogleYaLigadaNoSeReasigna(): void
    {
        $this->db->exec("UPDATE usuarios SET google_uid = 'google-sub-x' WHERE id_usuario = 2");
        $ana = (new Usuario($this->db))->buscarPorId(DosClinicas::ADMIN_NORTE);

        $this->registro->vincularGoogle($ana, 'google-sub-x');

        $this->assertNull($this->db->query('SELECT google_uid FROM usuarios WHERE id_usuario = 1')->fetchColumn());
        $this->assertSame('google-sub-x', $this->db->query('SELECT google_uid FROM usuarios WHERE id_usuario = 2')->fetchColumn());
    }
}
