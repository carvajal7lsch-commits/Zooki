<?php
require_once __DIR__ . '/Contexto.php';

/**
 * HU-T.16 (RN-G17) — La sesión, en un solo lugar: lo usan public/index.php y
 * public/ver_archivo.php.
 *
 * - La cookie es de sesión (se borra al cerrar el navegador), HttpOnly,
 *   SameSite=Lax y Secure bajo HTTPS (RE-T.16.3).
 * - Tras 30 minutos sin peticiones la sesión se cierra; la siguiente petición
 *   ya no tiene identidad y Security responde 401 (AJAX) o lleva al login con
 *   el motivo (RE-T.16.1/2). El cierre queda en auditoría (RE-T.16.4).
 * - El identificador se regenera al iniciar sesión y al cambiar de contexto.
 */
final class Sesion
{
    public const INACTIVIDAD_SEGUNDOS = 1800;

    public const MENSAJE_INACTIVIDAD = 'Tu sesión se cerró tras 30 minutos sin actividad. Inicia sesión de nuevo.';

    /** @var callable|null Punto de inyección de las pruebas: se llama en cada regeneración. */
    private static $alRegenerar = null;

    /** Parámetros de la cookie; $https decide Secure para no romper el desarrollo local por HTTP. */
    public static function parametrosCookie(bool $https): array
    {
        return [
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'secure' => $https,
            'samesite' => 'Lax',
        ];
    }

    public static function esHttps(array $servidor): bool
    {
        return (!empty($servidor['HTTPS']) && $servidor['HTTPS'] !== 'off')
            || (($servidor['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

    /** Abre la sesión y aplica la inactividad. */
    public static function iniciar(): void
    {
        session_set_cookie_params(self::parametrosCookie(self::esHttps($_SERVER)));
        // La recolección de PHP (24 min por defecto) no debe cerrar antes que la regla.
        ini_set('session.gc_maxlifetime', (string) self::INACTIVIDAD_SEGUNDOS);
        ini_set('session.use_strict_mode', '1');
        session_start();

        $vencida = self::revisarInactividad($_SESSION, time());
        if ($vencida !== null) {
            try {
                require_once __DIR__ . '/../config/Database.php';
                require_once __DIR__ . '/../models/Auditoria.php';
                $db = (new Database())->getConnection();
                if ($db) {
                    self::auditarCierre(new Auditoria($db), $vencida['id_usuario'], $vencida['id_clinica']);
                }
            } catch (Throwable $e) {
                // Sin auditoría la sesión se cierra igual: no se deja abierta por un fallo del registro.
                error_log('RN-G17: no se registró el cierre por inactividad (' . $e->getMessage() . ')');
            }
            self::regenerar();
        }
    }

    /** RE-T.16.4: el cierre por inactividad queda con usuario, fecha, IP y la clínica en que estaba. */
    public static function auditarCierre(Auditoria $auditoria, int $idUsuario, ?int $idClinica): void
    {
        $auditoria->log($idUsuario, 'LOGOUT', 'usuarios', $idUsuario, null, null, 'Cierre de sesión por inactividad (RN-G17)', $idClinica);
    }

    /**
     * Si la sesión de una identidad lleva más de 30 minutos sin actividad, la
     * vacía, deja el motivo para el login y devuelve quién era. Si no, anota
     * la actividad y devuelve null.
     *
     * @return array{id_usuario: int, id_clinica: ?int}|null
     */
    public static function revisarInactividad(array &$sesion, int $ahora): ?array
    {
        $ultima = $sesion['ultima_actividad'] ?? null;
        $idUsuario = $sesion['id_usuario'] ?? null;

        if ($idUsuario !== null && is_int($ultima) && $ahora - $ultima > self::INACTIVIDAD_SEGUNDOS) {
            $contexto = $sesion['contexto'] ?? null;
            $idClinica = is_array($contexto) && ($contexto['tipo'] ?? null) === Contexto::CLINICA ? (int) $contexto['id_clinica'] : null;
            $sesion = ['error_login' => self::MENSAJE_INACTIVIDAD, 'ultima_actividad' => $ahora];
            return ['id_usuario' => (int) $idUsuario, 'id_clinica' => $idClinica];
        }

        $sesion['ultima_actividad'] = $ahora;
        return null;
    }

    /** Identificador nuevo al cambiar de privilegios (session fixation, T-02). */
    public static function regenerar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        if (self::$alRegenerar !== null) {
            (self::$alRegenerar)();
        }
    }

    /** Para las pruebas (null quita el observador). */
    public static function observarRegeneracion(?callable $observador): void
    {
        self::$alRegenerar = $observador;
    }
}
