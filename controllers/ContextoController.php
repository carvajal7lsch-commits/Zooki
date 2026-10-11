<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../helpers/Contexto.php';
require_once __DIR__ . '/../helpers/Security.php';
require_once __DIR__ . '/../helpers/Sesion.php';

/**
 * HU-T.17 — Elegir y cambiar el contexto activo sin cerrar sesión.
 *
 * La clave que llega del selector solo se acepta si está entre los contextos
 * que la base reconoce hoy para la persona: nunca se arma un contexto con
 * datos del cliente.
 */
class ContextoController
{
    private PDO $db;
    private Usuario $usuario;
    private Auditoria $auditoria;

    public function __construct($db = null)
    {
        if ($db === null) {
            $db = (new Database())->getConnection();
        }
        $this->db = $db;
        $this->usuario = new Usuario($db);
        $this->auditoria = new Auditoria($db);
    }

    /**
     * Activa el contexto y deja constancia en la clínica que corresponda
     * (las entradas a una clínica las ve su administrador en la auditoría).
     */
    public static function entrar(array $contexto, array $disponibles, Auditoria $auditoria): void
    {
        Contexto::activar($contexto, count($disponibles));
        $auditoria->log(
            Contexto::idUsuario(),
            'OTHER',
            'usuarios',
            Contexto::idUsuario(),
            null,
            ['contexto' => $contexto['clave']],
            'Entrada al contexto: ' . Contexto::etiqueta($contexto),
            $contexto['id_clinica']
        );
    }

    /** RE-T.17.2 — Selector; con un solo contexto entra directo. */
    public function mostrar(): void
    {
        $idUsuario = (int) Contexto::idUsuario();
        $disponibles = $this->usuario->contextosDe($idUsuario) ?? [];

        if (count($disponibles) === 1 && Contexto::actual() === null && empty($_SESSION['debe_cambiar_password'])) {
            self::entrar($disponibles[0], $disponibles, $this->auditoria);
            header('Location: index.php?action=' . Contexto::destino($disponibles[0]));
            exit;
        }

        $actual = Contexto::actual();
        $nombre = (string) ($_SESSION['usuario_nombre'] ?? '');
        $error = $_SESSION['error'] ?? null;
        unset($_SESSION['error']);
        require __DIR__ . '/../views/auth/seleccionar_contexto.php';
    }

    /**
     * RE-T.17.3 — Cambia de contexto: los permisos y la clínica pasan a ser
     * los del nuevo. Una clave que la persona no tiene se rechaza con 403 y
     * queda en auditoría (RN-G13).
     */
    public function cambiar(): void
    {
        header('Location: index.php?action=' . $this->aplicarCambio((string) ($_POST['contexto'] ?? '')));
        exit;
    }

    /** Aplica el cambio y devuelve el destino; separado de la redirección para probarlo. */
    public function aplicarCambio(string $clave): string
    {
        $idUsuario = (int) Contexto::idUsuario();
        $disponibles = $this->usuario->contextosDe($idUsuario) ?? [];
        $elegido = Contexto::buscar($disponibles, $clave);

        if ($elegido === null) {
            Security::denegarRecursoAjeno('usuario_clinica', $clave);
        }

        // T-02 y RE-T.16.3: identificador de sesión nuevo al cambiar de permisos.
        Sesion::regenerar();
        self::entrar($elegido, $disponibles, $this->auditoria);
        return Contexto::destino($elegido);
    }
}
