<?php
/** IP real común a auditoría y carnet; respeta la cadena de proxies confiables. */
final class IpCliente
{
    /**
     * Proxies de confianza cuyo X-Forwarded-For sí se acepta: IP exactas o
     * rangos CIDR de TRUSTED_PROXIES (lista separada por comas). Vacío = no
     * se confía en ninguno.
     */
    private static ?array $proxiesConfiables = null;
    /**
     * IP real del cliente (RN-G05, RN-G15). Es la única fuente de IP del
     * sistema: auditoría, límites de intentos de Security y Turnstile.
     *
     * T-08 — X-Forwarded-For la puede escribir el propio cliente, así que solo
     * se lee si la conexión llega desde un proxy declarado en TRUSTED_PROXIES.
     *
     * D2.1 — Cada proxy agrega a la DERECHA la IP de quien le habló; lo de la
     * izquierda lo pudo escribir el cliente. Por eso la cadena se recorre de
     * derecha a izquierda y vale la primera IP que no sea un proxy confiable.
     * Antes se tomaba la de la izquierda: con un X-Forwarded-For inventado,
     * cualquiera elegía la IP con la que se le contaban los intentos.
     */
    public static function resolver(): string {
        $remota = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!self::esProxyConfiable($remota)) {
            return $remota;
        }

        $cadena = array_map('trim', explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')));
        $ultimaValida = $remota;
        for ($i = count($cadena) - 1; $i >= 0; $i--) {
            $candidata = $cadena[$i];
            if (!filter_var($candidata, FILTER_VALIDATE_IP)) {
                // Una entrada que no es una IP no viene de un proxy confiable: se para ahí.
                return $ultimaValida;
            }
            if (!self::esProxyConfiable($candidata)) {
                return $candidata;
            }
            $ultimaValida = $candidata;
        }

        return $ultimaValida;
    }

    /** IP exacta o rango CIDR (IPv4 o IPv6) de TRUSTED_PROXIES. */
    private static function esProxyConfiable(string $ip): bool {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        foreach (self::proxiesConfiables() as $proxy) {
            if (self::coincide($ip, $proxy)) {
                return true;
            }
        }
        return false;
    }

    private static function coincide(string $ip, string $proxy): bool {
        if (!str_contains($proxy, '/')) {
            return $ip === $proxy;
        }
        [$red, $bits] = explode('/', $proxy, 2);
        $binIp = @inet_pton($ip);
        $binRed = @inet_pton($red);
        if ($binIp === false || $binRed === false || strlen($binIp) !== strlen($binRed) || !ctype_digit($bits)) {
            return false;
        }
        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        if (strncmp($binIp, $binRed, $bytes) !== 0) {
            return false;
        }
        $resto = $bits % 8;
        if ($resto === 0) {
            return true;
        }
        $mascara = chr((0xFF << (8 - $resto)) & 0xFF);
        return ($binIp[$bytes] & $mascara) === ($binRed[$bytes] & $mascara);
    }

    private static function proxiesConfiables(): array {
        if (self::$proxiesConfiables === null) {
            $envFile = __DIR__ . '/../.env';
            $lista = getenv('TRUSTED_PROXIES') ?: '';
            if ($lista === '' && !defined('ZOOKI_PRUEBAS') && file_exists($envFile)) {
                $env = parse_ini_file($envFile);
                $lista = (string) ($env['TRUSTED_PROXIES'] ?? '');
            }
            self::$proxiesConfiables = array_values(array_filter(array_map('trim', explode(',', $lista))));
        }
        return self::$proxiesConfiables;
    }

    /** Punto de inyección para las pruebas (null vuelve a leer TRUSTED_PROXIES). */
    public static function definirProxiesConfiables(?array $proxies): void {
        self::$proxiesConfiables = $proxies;
    }
}
