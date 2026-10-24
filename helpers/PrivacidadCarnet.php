<?php
require_once __DIR__ . '/IpCliente.php';

/** RN-504/505: la IP nunca se guarda en claro ni con un hash reversible por diccionario. */
final class PrivacidadCarnet
{
    public static function secreto(): string
    {
        $secreto = getenv('CARNET_IP_SECRETO');
        $envFile = __DIR__ . '/../.env';
        // XAMPP no exporta el .env al entorno: se lee como TRUSTED_PROXIES y APP_URL.
        if (($secreto === false || $secreto === '') && !defined('ZOOKI_PRUEBAS') && is_file($envFile)) {
            $secreto = (string) ((parse_ini_file($envFile) ?: [])['CARNET_IP_SECRETO'] ?? '');
        }
        if (!is_string($secreto) || strlen($secreto) < 32) {
            throw new RuntimeException('Configura CARNET_IP_SECRETO con al menos 32 caracteres en el entorno del servidor.');
        }
        return $secreto;
    }

    public static function ipHash(): string
    {
        return hash_hmac('sha256', IpCliente::resolver(), self::secreto());
    }

    /** Comprobante opaco del escaneo: no expone ids ni necesita sesión pública. */
    public static function comprobante(string $token, string $ipHash, int $idEscaneo): string
    {
        return hash_hmac('sha256', 'ubicacion|' . $token . '|' . $ipHash . '|' . $idEscaneo, self::secreto());
    }
}
