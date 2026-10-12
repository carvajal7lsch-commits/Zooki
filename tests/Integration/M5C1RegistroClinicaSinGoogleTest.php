<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/RegistroClinica.php';
require_once __DIR__ . '/../../controllers/RegistroClinicaController.php';
require_once __DIR__ . '/RegistroClinicaTest.php';

/**
 * M5 C1, hueco 7 — RE-T.18.1 y RN-G20: Google es solo para propietarios. El
 * registro de clínica no lo ofrece en la pantalla y su controlador no acepta
 * una identidad de Google en lugar de los datos del formulario.
 */
final class M5C1RegistroClinicaSinGoogleTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        $this->db->exec('CREATE TABLE intentos_login (identificador TEXT PRIMARY KEY, intentos INTEGER, primer_intento TEXT, ultimo_intento TEXT, bloqueado_hasta TEXT)');
        $this->db->exec("CREATE TABLE casos_soporte (id_caso INTEGER PRIMARY KEY, tipo TEXT, id_usuario INTEGER, id_clinica INTEGER, descripcion TEXT, estado TEXT DEFAULT 'abierto')");
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_SERVER['REMOTE_ADDR'] = '192.0.2.123';
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
    }

    private function controlador(): RegistroClinicaController
    {
        $correo = new class {
            public function limpiarDirecciones(): void {}
            public function obtenerPlantillaBaseHTML(...$d): string { return ''; }
            public function enviarCorreoPersonalizado(...$d): bool { return true; }
        };
        return new RegistroClinicaController($this->db, $correo, new Turnstile(static fn (): bool => true));
    }

    public function testLaPantallaDelRegistroDeClinicaNoOfreceGoogle(): void
    {
        ob_start();
        try {
            $this->controlador()->formulario();
        } finally {
            $html = (string) ob_get_clean();
        }

        $this->assertStringContainsString('id="registroClinicaForm"', $html, 'Es la pantalla del registro de clínica.');
        $this->assertStringNotContainsString('accounts.google.com', $html);
        $this->assertStringNotContainsString('data-google-client-id', $html);
        $this->assertStringNotContainsString('btnGoogle', $html);
        $this->assertStringNotContainsString('Google', $html);
    }

    public function testElControladorNoAceptaUnaIdentidadDeGoogle(): void
    {
        $google = ['access_token' => 'token-de-google', 'credential' => 'jwt-de-google', 'google_uid' => 'google-sub-olga'];
        $sinContrasena = RegistroClinicaTest::datos(5, $google);
        unset($sinContrasena['password'], $sinContrasena['confirm_password']);

        $rechazo = $this->controlador()->registrar($sinContrasena, '192.0.2.123');

        $this->assertFalse($rechazo['success'], 'Sin contraseña no hay registro, aunque llegue Google.');
        $this->assertSame(0, (int) $this->db->query("SELECT COUNT(*) FROM clinicas WHERE nombre = 'Clínica Nueva 5'")->fetchColumn());

        $this->controlador()->registrar(RegistroClinicaTest::datos(7, $google), '192.0.2.123');

        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM clinicas WHERE nombre = 'Clínica Nueva 7'")->fetchColumn());
        $this->assertNull($this->db->query("SELECT google_uid FROM usuarios WHERE email = 'olga7@zooki.test'")->fetchColumn(), 'La cuenta del administrador no queda ligada a Google.');
    }
}
