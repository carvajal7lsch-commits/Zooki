<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/RegistroPropietario.php';
require_once __DIR__ . '/../../models/PropietarioClinica.php';
require_once __DIR__ . '/../../helpers/Autenticador.php';

/**
 * HU-5.8 (RN-109, RN-G06, RN-G11) y HU-T.19 — Autorregistro del propietario
 * en la clínica que eligió, sin duplicar identidades.
 */
final class RegistroPropietarioTest extends TestCase
{
    private PDO $db;
    private RegistroPropietario $registro;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        $this->registro = new RegistroPropietario($this->db);
        $_SESSION = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function formulario(array $cambios = []): array
    {
        return $cambios + [
            'id_clinica' => '1', 'tipo_documento' => 'CC', 'documento' => '1000000099',
            'nombre_completo' => 'Olga Nueva', 'telefono' => '+57 300 555 1234', 'email' => 'olga@zooki.test',
            'password' => 'Huellita#2026', 'confirm_password' => 'Huellita#2026', 'acepta_datos' => '1',
        ];
    }

    private function contar(string $sql): int
    {
        return (int) $this->db->query($sql)->fetchColumn();
    }

    private function login(string $email, string $password): string
    {
        $autenticador = new Autenticador(new Usuario($this->db), new VerificacionEmail($this->db));
        return $autenticador->conPassword($email, $password)['resultado'];
    }

    public function testSinAceptacionNoSeCreaNadaYConAceptacionQuedaLaPrueba(): void
    {
        $antes = $this->contar('SELECT COUNT(*) FROM usuarios');
        foreach (['', '0', 'si'] as $acepta) {
            try {
                $this->registro->conFormulario($this->formulario(['acepta_datos' => $acepta]), '10.0.0.1');
                $this->fail('Se registró sin aceptar la política.');
            } catch (InvalidArgumentException $e) {
                $this->assertSame($antes, $this->contar('SELECT COUNT(*) FROM usuarios'));
                $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM verificaciones_email'));
                $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM consentimientos_datos'));
            }
        }

        $r = $this->registro->conFormulario($this->formulario(), '10.0.0.1');
        $prueba = $this->db->query('SELECT * FROM consentimientos_datos')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame($r['id_usuario'], (int) $prueba['id_usuario']);
        $this->assertSame([PoliticaDatos::VERSION, 'formulario', '10.0.0.1'], [$prueba['version_politica'], $prueba['medio'], $prueba['ip_address']]);
    }

    public function testCuentaNuevaNoEntraNiSeVinculaHastaVerificarElCorreo(): void
    {
        $r = $this->registro->conFormulario($this->formulario(), '10.0.0.1');

        $this->assertSame('nueva', $r['resultado']);
        $this->assertSame('Clínica Norte', $r['clinica']);
        $this->assertSame('pendiente', $this->login('olga@zooki.test', 'Huellita#2026'));
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM propietario_clinica WHERE id_propietario = {$r['id_usuario']}"));
        $this->assertSame('+57 300 555 1234', $this->db->query("SELECT telefono FROM usuarios WHERE id_usuario = {$r['id_usuario']}")->fetchColumn());

        $verificado = $this->registro->verificarRegistro($r['enlace']['id'], $r['enlace']['token']);

        $this->assertSame('Clínica Norte', $verificado['clinica']);
        $this->assertSame('ok', $this->login('olga@zooki.test', 'Huellita#2026'));
        $this->assertSame('activo', $this->db->query("SELECT estado FROM propietario_clinica WHERE id_propietario = {$r['id_usuario']} AND id_clinica = 1")->fetchColumn());
        $this->assertNull($this->registro->verificarRegistro($r['enlace']['id'], $r['enlace']['token']), 'El enlace es de un solo uso.');
        // RE-5.8.5: el alta queda en la auditoría de la clínica, y Sur no ve al propietario.
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM auditoria_sistema WHERE id_clinica = 1 AND tabla_afectada = 'propietario_clinica' AND registro_id = '{$r['id_usuario']}'"));
        $this->assertNotContains($r['id_usuario'], array_map('intval', array_column((new Usuario($this->db))->propietariosDeClinica(2), 'id_usuario')));
    }

    public function testUnEnlaceVencidoNoLiberaLaCuentaYRegistrarseOtraVezEnviaOtro(): void
    {
        $r = $this->registro->conFormulario($this->formulario(), '10.0.0.1');
        $this->db->exec("UPDATE verificaciones_email SET expires_at = '2000-01-01 00:00:00'");

        // RN-G11 (decisión D1): antes, pasadas 24 horas, la cuenta entraba sin verificar.
        $this->assertSame('pendiente', $this->login('olga@zooki.test', 'Huellita#2026'));
        $this->assertNull($this->registro->verificarRegistro($r['enlace']['id'], $r['enlace']['token']));

        $hash = $this->db->query("SELECT password FROM usuarios WHERE id_usuario = {$r['id_usuario']}")->fetchColumn();
        $otra = $this->registro->conFormulario($this->formulario(['id_clinica' => '2', 'password' => 'Otra#Clave2026', 'confirm_password' => 'Otra#Clave2026']), '10.0.0.2');

        $this->assertSame('pendiente', $otra['resultado']);
        $this->assertSame($r['id_usuario'], $otra['id_usuario'], 'No se duplica la cuenta.');
        $this->assertSame($hash, $this->db->query("SELECT password FROM usuarios WHERE id_usuario = {$r['id_usuario']}")->fetchColumn(), 'No se toca la contraseña guardada.');
        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM verificaciones_email WHERE used = 0"));

        $verificado = $this->registro->verificarRegistro($otra['enlace']['id'], $otra['enlace']['token']);
        $this->assertSame('Clínica Sur', $verificado['clinica']);
    }

    public function testCorreoExistenteNoDuplicaLaCuentaYSeVinculaAlConfirmar(): void
    {
        $antes = $this->contar('SELECT COUNT(*) FROM usuarios');
        $r = $this->registro->conFormulario($this->formulario(['email' => 'fabio@zooki.test', 'documento' => '1000000006', 'id_clinica' => '2']), '10.0.0.1');

        $this->assertSame('existente', $r['resultado']);
        $this->assertSame(DosClinicas::PROPIETARIO, $r['id_usuario']);
        $this->assertSame($antes, $this->contar('SELECT COUNT(*) FROM usuarios'));
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM propietario_clinica WHERE id_propietario = 6 AND id_clinica = 2'));
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM consentimientos_datos'), 'No se creó cuenta: no hay consentimiento nuevo.');
        $this->assertTrue(password_verify(DosClinicas::PASSWORD, $this->db->query('SELECT password FROM usuarios WHERE id_usuario = 6')->fetchColumn()));

        $this->assertTrue((new PropietarioClinica($this->db))->confirmarVinculo($r['enlace']['id'], $r['enlace']['token']));
        $this->assertSame('activo', $this->db->query('SELECT estado FROM propietario_clinica WHERE id_propietario = 6 AND id_clinica = 2')->fetchColumn());
    }

    public function testYaVinculadoSoloDebeIniciarSesion(): void
    {
        $r = $this->registro->conFormulario($this->formulario(['email' => 'fabio@zooki.test']), '10.0.0.1');

        $this->assertSame('vinculada', $r['resultado']);
        $this->assertNull($r['enlace']);
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM verificaciones_email'));
    }

    public function testElSuperAdministradorNoSeVinculaComoPropietario(): void
    {
        $r = $this->registro->conFormulario($this->formulario(['email' => 'gina@zooki.test']), '10.0.0.1');

        $this->assertSame('existente', $r['resultado'], 'Responde igual que a cualquier correo existente.');
        $this->assertNull($r['enlace'], 'Pero no envía nada.');
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM verificaciones_email'));
    }

    public function testDatosInvalidosOClinicaNoActivaNoCreanNada(): void
    {
        $casos = [
            'documento de otra cuenta' => ['documento' => '1000000001'],
            'teléfono con letras' => ['telefono' => '300abc4567'],
            'teléfono largo' => ['telefono' => str_repeat('3', 21)],
            'clínica suspendida' => ['id_clinica' => '3'],
            'sin clínica' => ['id_clinica' => ''],
            'contraseñas distintas' => ['confirm_password' => 'Distinta#2026'],
        ];
        $antes = $this->contar('SELECT COUNT(*) FROM usuarios');
        foreach ($casos as $nombre => $cambio) {
            try {
                $this->registro->conFormulario($this->formulario($cambio), '10.0.0.1');
                $this->fail("Se aceptó: $nombre");
            } catch (InvalidArgumentException $e) {
                $this->assertSame($antes, $this->contar('SELECT COUNT(*) FROM usuarios'), $nombre);
            }
        }
    }

    public function testLaListaSoloOfreceClinicasActivas(): void
    {
        $this->assertSame(['Clínica Norte', 'Clínica Sur'], array_column($this->registro->clinicasDisponibles(), 'nombre'));
    }
}
