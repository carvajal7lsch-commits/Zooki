<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../helpers/ValidadorTelefono.php';
require_once __DIR__ . '/../../helpers/Security.php';
require_once __DIR__ . '/../../controllers/UsuarioController.php';
require_once __DIR__ . '/../../controllers/PerfilController.php';
require_once __DIR__ . '/../../controllers/MascotaController.php';
require_once __DIR__ . '/../../controllers/PropietarioController.php';
require_once __DIR__ . '/../../controllers/PortalController.php';

/**
 * D1 (hallazgo de la revisión de C9.1) — Todos los teléfonos usan la misma
 * regla: ValidadorTelefono en el servidor y sus atributos en el formulario.
 */
final class TelefonosTest extends TestCase
{
    private PDO $db;
    private const RAIZ = __DIR__ . '/../..';

    protected function setUp(): void
    {
        $this->reiniciarBase();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SESSION = [];
        $_POST = [];
    }

    private function reiniciarBase(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        Security::definirAuditoria(new Auditoria($this->db));
    }

    protected function tearDown(): void
    {
        Security::definirAuditoria(false);
        $_SESSION = [];
        $_POST = [];
        http_response_code(200);
    }

    private function json(callable $accion): array
    {
        ob_start();
        $accion();
        return json_decode((string) ob_get_clean(), true) ?? [];
    }

    private function comoAdminNorte(): void
    {
        $_SESSION = ['id_usuario' => DosClinicas::ADMIN_NORTE];
        Contexto::activar(Contexto::deClinica(DosClinicas::NORTE, 'Clínica Norte', Roles::ADMIN), 1);
    }

    /** @return array<string, callable(string): array> cada punto de entrada del servidor con un teléfono */
    private function entradas(): array
    {
        return [
            'alta de personal (Usuarios)' => function (string $telefono): array {
                $this->comoAdminNorte();
                $_POST = ['tipo_documento' => 'CC', 'documento' => '1000000077', 'nombre_completo' => 'Nueva Persona',
                    'email' => 'nueva@zooki.test', 'telefono' => $telefono, 'id_rol' => '2', 'estado' => '1', 'password' => ''];
                $correo = new class {
                    public function limpiarDirecciones(): void {}
                    public function enviarInvitacionPersonal(...$d) { return true; }
                };
                return $this->json(fn () => (new UsuarioController($this->db, $correo))->registrarAjax());
            },
            'perfil del personal' => function (string $telefono): array {
                $this->comoAdminNorte();
                $_POST = ['email' => 'ana@zooki.test', 'telefono' => $telefono];
                return $this->json(fn () => (new PerfilController($this->db))->actualizarAjax());
            },
            'cliente (Usuarios y Pacientes)' => function (string $telefono): array {
                $this->comoAdminNorte();
                $_POST = ['id_usuario' => '6', 'nombre_completo' => 'Fabio Dueño', 'telefono' => $telefono, 'estado' => '1'];
                return $this->json(fn () => (new MascotaController($this->db))->actualizarPropietarioAjax());
            },
            'alta presencial de propietario (Pacientes)' => function (string $telefono): array {
                $this->comoAdminNorte();
                $_POST = ['tipo_documento' => 'CC', 'documento' => '1000000066', 'nombre_completo' => 'Rosa Nueva',
                    'email' => 'rosa@zooki.test', 'telefono' => $telefono, 'titular_presente' => '1', 'acepta_politica' => '1'];
                return $this->json(fn () => (new PropietarioController($this->db, static fn () => true))->registrarAjax());
            },
            'perfil del propietario (portal)' => function (string $telefono): array {
                $_SESSION = ['id_usuario' => DosClinicas::PROPIETARIO];
                Contexto::activar(Contexto::dePropietario(), 1);
                $_POST = ['telefono' => $telefono];
                return $this->json(fn () => (new PortalController($this->db))->actualizarDatosContactoAjax());
            },
        ];
    }

    public function testCadaFormularioDelServidorRechazaLoQueLaReglaRechaza(): void
    {
        foreach ($this->entradas() as $nombre => $enviar) {
            foreach (['300abc4567', '(300) 1234567', str_repeat('3', ValidadorTelefono::MAX + 1)] as $malo) {
                $respuesta = $enviar($malo);
                $this->assertFalse($respuesta['success'] ?? true, "$nombre aceptó «{$malo}».");
                $this->assertSame(ValidadorTelefono::MENSAJE, $respuesta['message'] ?? null, $nombre);
            }
        }
    }

    public function testCadaFormularioAceptaUnTelefonoValido(): void
    {
        foreach ($this->entradas() as $nombre => $enviar) {
            $this->reiniciarBase();
            $respuesta = $enviar('+57 300 123-4567');
            $this->assertNotSame(ValidadorTelefono::MENSAJE, $respuesta['message'] ?? '', $nombre);
        }
    }

    public function testNingunCodigoRepiteLaExpresionDelTelefono(): void
    {
        $archivos = array_merge(
            glob(self::RAIZ . '/controllers/*.php'),
            glob(self::RAIZ . '/models/*.php'),
            glob(self::RAIZ . '/helpers/*.php'),
            glob(self::RAIZ . '/public/js/*.js')
        );
        foreach ($archivos as $archivo) {
            if (basename($archivo) === 'ValidadorTelefono.php') {
                continue;
            }
            $this->assertStringNotContainsString('{7,20}', file_get_contents($archivo), basename($archivo));
        }
    }

    public function testTodoCampoDeTelefonoDeLasVistasUsaLosAtributosDeLaRegla(): void
    {
        $vistas = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::RAIZ . '/views'));
        $campos = 0;
        foreach ($vistas as $vista) {
            if ($vista->getExtension() !== 'php') {
                continue;
            }
            preg_match_all('/<input[^\n]*(?:type="tel"|name="telefono")[^\n]*/', file_get_contents($vista->getPathname()), $encontrados);
            foreach ($encontrados[0] as $campo) {
                $campos++;
                $this->assertMatchesRegularExpression('/ValidadorTelefono::atributosHtml\(\)|\$telefonoHtml/', $campo, $vista->getFilename() . ': ' . $campo);
            }
        }
        // Registro, perfil de Google, portal, perfil del personal, Usuarios (2) y Pacientes (2).
        $this->assertSame(8, $campos);

        $atributos = ValidadorTelefono::atributosHtml();
        $this->assertStringContainsString('maxlength="' . ValidadorTelefono::MAX . '"', $atributos);
        $this->assertStringContainsString('data-caracteres="' . ValidadorTelefono::CARACTERES . '"', $atributos);
        $this->assertStringContainsString('pattern="' . ValidadorTelefono::patronHtml() . '"', $atributos);
    }
}
