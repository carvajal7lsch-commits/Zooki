<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../models/ConsentimientoDatos.php';
require_once __DIR__ . '/../models/RegistroPropietario.php';
require_once __DIR__ . '/../helpers/Contexto.php';
require_once __DIR__ . '/../helpers/Csrf.php';
require_once __DIR__ . '/../helpers/PoliticaDatos.php';
require_once __DIR__ . '/../helpers/ValidadorTelefono.php';

/**
 * Pantallas que la cuenta debe pasar antes de continuar (D1):
 * - RE-T.19.3: aceptar la versión vigente de la política.
 * - RE-T.18.3: completar documento y teléfono de una cuenta de Google.
 *
 * Son formularios simples (POST con CSRF y redirección), sin JS. La lógica
 * vive en métodos públicos que las pruebas llaman sin servidor web.
 */
class CuentaController
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? (new Database())->getConnection();
    }

    public function aceptarPolitica(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            try {
                $this->registrarAceptacion((int) Contexto::idUsuario(), (string) ($_POST['acepta_datos'] ?? ''), Auditoria::ipCliente());
                $this->redirigir($this->destinoTrasAceptar());
            } catch (InvalidArgumentException $e) {
                $_SESSION['error'] = $e->getMessage();
                $this->redirigir('aceptar_politica');
            }
        }
        $error = $this->tomarError();
        $version = PoliticaDatos::VERSION;
        $vigencia = PoliticaDatos::VIGENCIA;
        require __DIR__ . '/../views/auth/aceptar_politica.php';
    }

    /** RE-T.19.2/3: guarda la prueba de la versión vigente y libera la sesión. */
    public function registrarAceptacion(int $idUsuario, string $acepta, string $ip): void
    {
        if ($acepta !== '1') {
            throw new InvalidArgumentException('Para continuar debes aceptar la política de tratamiento de datos.');
        }
        (new ConsentimientoDatos($this->db))->registrar($idUsuario, PoliticaDatos::MEDIO_FORMULARIO, $ip);
        (new Auditoria($this->db))->log($idUsuario, 'UPDATE', 'consentimientos_datos', $idUsuario, null, ['version_politica' => PoliticaDatos::VERSION], 'Aceptó la versión vigente de la política al iniciar sesión (RE-T.19.3)', null);
        $_SESSION['debe_aceptar_politica'] = 0;
    }

    public function completarPerfil(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            try {
                $this->guardarPerfil((int) Contexto::idUsuario(), $_POST);
                $this->redirigir('portal_propietario');
            } catch (InvalidArgumentException $e) {
                $_SESSION['error'] = $e->getMessage();
                $this->redirigir('completar_perfil');
            }
        }
        $error = $this->tomarError();
        require __DIR__ . '/../views/auth/completar_perfil.php';
    }

    /** RE-T.18.3: documento y teléfono; desde ahí el portal queda completo. */
    public function guardarPerfil(int $idUsuario, array $entrada): void
    {
        (new RegistroPropietario($this->db))->completarPerfil($idUsuario, $entrada);
        $_SESSION['perfil_incompleto'] = 0;
    }

    private function destinoTrasAceptar(): string
    {
        $actual = Contexto::actual();
        if (!empty($_SESSION['debe_cambiar_password'])) {
            return 'cambiar_password';
        }
        return $actual !== null ? Contexto::destino($actual) : 'seleccionar_contexto';
    }

    private function tomarError(): ?string
    {
        $error = $_SESSION['error'] ?? null;
        unset($_SESSION['error']);
        return $error;
    }

    private function redirigir(string $accion): never
    {
        header('Location: index.php?action=' . $accion);
        exit;
    }
}
