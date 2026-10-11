<?php
/**
 * D2.1 — ¿Corre Zooki en el equipo de un desarrollador?
 *
 * Es la única comprobación para lo que solo se permite en local: los datos
 * de prueba (scripts/dev/datos_prueba.php) y guardar los correos en archivo
 * (MAIL_MODO=archivo en EmailService). Fuera de local se niegan.
 */
final class EntornoLocal
{
    /**
     * Motivo por el que NO es local, o null si lo es.
     *
     * @param string $baseDeDatos host de la base: el estado de la conexión PDO
     *                            («localhost via TCP/IP») o el DB_HOST del .env
     */
    public static function motivoNoLocal(string $baseDeDatos): ?string
    {
        if (file_exists('/.dockerenv')) {
            return 'corre dentro de Docker';
        }
        if (in_array(strtolower((string) getenv('APP_ENV')), ['production', 'produccion'], true)) {
            return 'APP_ENV es production';
        }
        if (!preg_match('/^(localhost|127\.0\.0\.1|::1)\b/i', trim($baseDeDatos))) {
            return "la base no es local ($baseDeDatos)";
        }
        return null;
    }
}
