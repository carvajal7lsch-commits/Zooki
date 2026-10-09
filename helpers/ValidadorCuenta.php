<?php
require_once __DIR__ . '/ValidadorTelefono.php';
require_once __DIR__ . '/PoliticaPassword.php';

/** Reglas del servidor compartidas por las ayudas al escribir y por el guardado. */
final class ValidadorCuenta
{
    public static function documento(string $valor): ?string
    {
        return preg_match('/^\d{5,15}$/D', $valor) ? null : 'El documento debe tener entre 5 y 15 dígitos.';
    }

    public static function correo(string $valor): ?string
    {
        return filter_var($valor, FILTER_VALIDATE_EMAIL) && strlen($valor) <= 255 ? null : 'Escribe un correo electrónico válido.';
    }

    public static function telefono(string $valor): ?string
    {
        return ValidadorTelefono::esValido(ValidadorTelefono::normalizar($valor)) ? null : ValidadorTelefono::MENSAJE;
    }

    public static function nombre(string $valor): ?string
    {
        return mb_strlen(trim($valor)) >= 3 && mb_strlen(trim($valor)) <= 100 ? null : 'El nombre debe tener entre 3 y 100 caracteres.';
    }
}
