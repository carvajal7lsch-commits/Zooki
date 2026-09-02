<?php
/**
 * HU-36 (VD-SEG-10) — Validacion de los tokens de Google.
 *
 * El flujo anterior preguntaba a Google si el token era autentico y se
 * conformaba con un HTTP 200. Eso comprueba que el token lo emitio Google,
 * pero NO que sea para esta aplicacion: cualquiera con su propio cliente de
 * Google podia emitir un token valido con el correo de un usuario de Zooki y
 * entrar como el. Lo que faltaba es comparar el campo `aud` (audiencia) con
 * el client_id propio.
 *
 * La comprobacion se separa de la llamada de red a proposito, para poder
 * cubrirla con pruebas sin depender de Google.
 */
class GoogleToken
{
    /** Emisores legitimos de un id_token de Google. */
    private const EMISORES = ['accounts.google.com', 'https://accounts.google.com'];

    /** client_id de Zooki, leido del .env igual que en la vista de login. */
    public static function clientId(): string
    {
        $envFile = __DIR__ . '/../.env';
        if (!file_exists($envFile)) return '';

        // parse_ini_file y no un parser a mano: Dokploy escribe los valores
        // entre comillas y un trim() de espacios las dejaria dentro del valor.
        $env = parse_ini_file($envFile) ?: [];

        return trim($env['GOOGLE_CLIENT_ID'] ?? '');
    }

    /**
     * Revisa el contenido de un token ya verificado por Google.
     *
     * @param array  $payload  respuesta de oauth2.googleapis.com/tokeninfo
     * @param string $clientId client_id esperado
     * @param bool   $esIdToken true para id_token (One Tap), false para access_token
     * @return string|null null si el token es aceptable; si no, el motivo del rechazo
     */
    public static function motivoDeRechazo(array $payload, string $clientId, bool $esIdToken): ?string
    {
        if ($clientId === '') {
            return 'No hay GOOGLE_CLIENT_ID configurado en el servidor';
        }

        // --- La comprobacion que faltaba -------------------------------------
        // Sin esto, un token emitido para OTRA aplicacion pasaba como valido.
        $aud = $payload['aud'] ?? $payload['azp'] ?? null;
        if ($aud === null || !hash_equals($clientId, (string) $aud)) {
            return 'El token no fue emitido para esta aplicacion';
        }

        // El emisor solo viaja en el id_token; el access_token no lo trae.
        if ($esIdToken) {
            $iss = (string) ($payload['iss'] ?? '');
            if (!in_array($iss, self::EMISORES, true)) {
                return 'El emisor del token no es Google';
            }
        }

        // Token vencido. Google ya lo rechaza, pero un token con exp en el
        // pasado no debe aceptarse aunque la respuesta llegue cacheada.
        if (isset($payload['exp']) && (int) $payload['exp'] <= time()) {
            return 'El token esta vencido';
        }

        if (empty($payload['email'])) {
            return 'El token no trae correo electronico';
        }

        // Google entrega este campo como cadena "true"/"false" en tokeninfo y
        // como booleano en otras respuestas: se aceptan ambas formas.
        $verificado = $payload['email_verified'] ?? $payload['verified_email'] ?? null;
        if ($verificado !== null && !in_array($verificado, [true, 'true', 1, '1'], true)) {
            return 'Google no da por verificado ese correo';
        }

        return null;
    }

    /**
     * Consulta a Google el contenido de un token.
     *
     * @return array|null payload, o null si Google no lo reconoce
     */
    public static function consultar(string $token, bool $esIdToken): ?array
    {
        $parametro = $esIdToken ? 'id_token' : 'access_token';
        $url = 'https://oauth2.googleapis.com/tokeninfo?' . $parametro . '=' . urlencode($token);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $respuesta = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($codigo !== 200 || $respuesta === false) return null;

        $payload = json_decode($respuesta, true);

        return is_array($payload) ? $payload : null;
    }

    /**
     * Perfil del usuario a partir de un access_token ya validado. tokeninfo
     * no siempre devuelve el nombre, asi que se completa con userinfo.
     */
    public static function perfil(string $accessToken): array
    {
        $ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo?access_token=' . urlencode($accessToken));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $respuesta = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($codigo !== 200 || $respuesta === false) return [];

        $datos = json_decode($respuesta, true);

        return is_array($datos) ? $datos : [];
    }
}
