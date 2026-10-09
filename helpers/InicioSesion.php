<?php
require_once __DIR__ . '/Sesion.php';
require_once __DIR__ . '/Contexto.php';
require_once __DIR__ . '/CierreSesiones.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../models/ConsentimientoDatos.php';
require_once __DIR__ . '/../controllers/ContextoController.php';

/**
 * Abre la sesión de una identidad ya autenticada (contraseña, Google o
 * correo recién confirmado) y devuelve a dónde ir (HU-T.17).
 *
 * Aquí se decide, una sola vez por inicio de sesión:
 * - RE-T.16.3: identificador de sesión nuevo.
 * - RE-T.19.3: si la persona no ha aceptado la versión vigente de la
 *   política, no continúa hasta aceptarla. El super-administrador está
 *   exento (revisión de B, B.5-2): no es un titular que se registra.
 * - RE-T.18.3: si el perfil está incompleto (cuenta de Google), el portal le
 *   pide documento y teléfono antes de cualquier otra cosa.
 */
final class InicioSesion
{
    public function __construct(private PDO $db)
    {
    }

    public function abrir(array $usuario, string $metodo): string
    {
        Sesion::regenerar();
        unset($_SESSION['registro_pendiente'], $_SESSION['google_pendiente']);

        $idUsuario = (int) $usuario['id_usuario'];
        Contexto::iniciarIdentidad($idUsuario, (string) $usuario['nombre_completo'], (int) $usuario['debe_cambiar_password'] === 1, $metodo);
        // RE-T.2.5: la sesión recuerda con qué versión entró (CierreSesiones).
        CierreSesiones::abrir($usuario);

        $auditoria = new Auditoria($this->db);
        // El inicio de sesión es de la persona, no de una clínica.
        $auditoria->log($idUsuario, 'LOGIN', 'usuarios', $idUsuario, null, null, $metodo === 'google' ? 'Inicio de sesion con Google' : 'Inicio de sesión exitoso', null);

        $_SESSION['debe_aceptar_politica'] = $this->debeAceptarPolitica($usuario) ? 1 : 0;
        $_SESSION['perfil_incompleto'] = (int) ($usuario['perfil_completo'] ?? 1) === 1 ? 0 : 1;

        $disponibles = (new Usuario($this->db))->contextosDe($idUsuario) ?? [];
        if (count($disponibles) === 1) {
            ContextoController::entrar($disponibles[0], $disponibles, $auditoria);
        }

        if ($_SESSION['debe_aceptar_politica'] === 1) {
            return 'aceptar_politica';
        }
        // T-05: con contraseña temporal, primero el cambio (Security lo exige igual).
        if ((int) $usuario['debe_cambiar_password'] === 1) {
            return 'cambiar_password';
        }
        return count($disponibles) === 1 ? Contexto::destino($disponibles[0]) : 'seleccionar_contexto';
    }

    private function debeAceptarPolitica(array $usuario): bool
    {
        if ((int) ($usuario['es_super_admin'] ?? 0) === 1) {
            return false;
        }
        return !(new ConsentimientoDatos($this->db))->aceptoVigente((int) $usuario['id_usuario']);
    }
}
