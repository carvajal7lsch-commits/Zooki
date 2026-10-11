<?php
require_once __DIR__ . '/Contexto.php';
require_once __DIR__ . '/../models/Usuario.php';

/**
 * RE-T.2.5 (Modelos §13.3) — Al crear o cambiar la contraseña se cierran las
 * demás sesiones abiertas de la cuenta.
 *
 * Cada sesión guarda, al iniciar, la versión de sesión de la cuenta
 * (usuarios.version_sesion). Cambiar la contraseña la sube y Security compara
 * en cada petición: la sesión con una versión vieja se vacía con 401, como
 * el cierre por inactividad de HU-T.16. La sesión donde se hizo el cambio
 * toma la versión nueva y sigue abierta.
 */
final class CierreSesiones
{
    public const MENSAJE = 'Tu sesión se cerró porque la contraseña de tu cuenta cambió. Inicia sesión de nuevo.';

    /** Cierra las demás sesiones del titular; la de esta petición sigue si es suya. */
    public static function cerrarOtras(Usuario $usuarios, int $idUsuario): void
    {
        $usuarios->subirVersionSesion($idUsuario);
        self::conservarActual($usuarios, $idUsuario);
    }

    /**
     * Para cuando la versión ya subió dentro de una transacción del modelo:
     * esta sesión, si es del mismo titular, toma la versión nueva.
     */
    public static function conservarActual(Usuario $usuarios, int $idUsuario): void
    {
        if (Contexto::idUsuario() !== $idUsuario) {
            return;
        }
        $_SESSION['version_sesion'] = $usuarios->versionSesion($idUsuario);
    }

    /** La versión con la que entra una sesión nueva. */
    public static function abrir(array $usuario): void
    {
        $_SESSION['version_sesion'] = (int) ($usuario['version_sesion'] ?? 0);
    }

    /** False si la contraseña cambió desde que se abrió la sesión. */
    public static function vigente(array $sesion, ?int $versionCuenta): bool
    {
        return $versionCuenta !== null && (int) ($sesion['version_sesion'] ?? 0) === $versionCuenta;
    }
}
