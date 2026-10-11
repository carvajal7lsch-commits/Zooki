<?php

/** RE-T.13.5: verificación sustituible, reutilizable en HU-0.1 (D3). */
final class Turnstile
{
    private Closure $verificar;

    public function __construct(?callable $verificar = null)
    {
        $this->verificar = Closure::fromCallable($verificar ?? self::consultar(...));
    }

    public static function configuracion(string $clave): string
    {
        $valor = getenv($clave);
        if ($valor !== false) {
            return $valor;
        }
        $archivo = __DIR__ . '/../.env';
        $configuracion = is_file($archivo) ? parse_ini_file($archivo) : [];
        return (string) ($configuracion[$clave] ?? '');
    }

    public function validar(string $token, string $ip): bool
    {
        if ($token === '' || strlen($token) > 2048) {
            return false;
        }
        try {
            return ($this->verificar)($token, $ip) === true;
        } catch (Throwable $e) {
            error_log('Turnstile: no se pudo verificar el desafío.');
            return false;
        }
    }

    private static function consultar(string $token, string $ip): bool
    {
        $clave = self::configuracion('TURNSTILE_SECRET_KEY');
        if ($clave === '') {
            return false;
        }
        $curl = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['secret' => $clave, 'response' => $token, 'remoteip' => $ip]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8,
        ]);
        $respuesta = curl_exec($curl);
        $codigo = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        return $codigo === 200 && (json_decode((string) $respuesta, true)['success'] ?? false) === true;
    }
}
