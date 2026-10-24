<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/EnlaceCuenta.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/** RE-5.10.2/4: el QR codifica únicamente el enlace opaco de emergencia. */
final class QrCarnet
{
    public static function validarToken(mixed $token): string
    {
        if (!is_string($token) || !preg_match('/^[A-Za-z0-9_-]{43}$/D', $token)) {
            throw new InvalidArgumentException('El token del carnet no es válido.');
        }
        return $token;
    }

    public static function enlace(string $token): string
    {
        return EnlaceCuenta::base() . '?action=carnet&t=' . self::validarToken($token);
    }

    public static function svg(string $token): string
    {
        $opciones = new QROptions(['outputBase64' => false, 'svgAddXmlHeader' => false]);
        return (new QRCode($opciones))->render(self::enlace($token));
    }
}
