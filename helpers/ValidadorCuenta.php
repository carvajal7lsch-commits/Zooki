<?php
require_once __DIR__ . '/ValidadorTelefono.php';
require_once __DIR__ . '/PoliticaPassword.php';

/** Reglas del servidor compartidas por las ayudas al escribir y por el guardado. */
final class ValidadorCuenta
{
    public const DOCUMENTO_MAX = 15;
    public const NOMBRE_MAX = 100;
    public const CORREO_MAX = 255;
    public const NOMBRE_CARACTERES = "\\p{L}\\p{M} '’\\-";

    /** D3.1: saneamiento común, sin quitar caracteres ilegales ni alterar contraseñas. */
    public static function normalizar(string $campo, string $valor): string
    {
        if (in_array($campo, ['nombre_completo', 'nombre_clinica', 'direccion', 'telefono', 'telefono_clinica'], true)) {
            return trim(preg_replace('/\s+/u', ' ', $valor));
        }
        if (in_array($campo, ['email', 'email_clinica', 'email_administrador'], true)) {
            return mb_strtolower(trim($valor));
        }
        return $campo === 'password' ? $valor : trim($valor);
    }

    /** Atributos compartidos por todos los formularios de cuenta (AGENTS, restricción de entrada). */
    public static function atributosHtml(string $campo): string
    {
        $e = static fn (string $valor): string => htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
        return match ($campo) {
            'documento' => 'inputmode="numeric" maxlength="' . self::DOCUMENTO_MAX . '" data-caracteres="0-9"',
            'nombre_completo' => 'maxlength="' . self::NOMBRE_MAX . '" data-caracteres="' . $e(self::NOMBRE_CARACTERES) . '" data-espacios="normalizar"',
            'email' => 'maxlength="' . self::CORREO_MAX . '" data-caracteres="\\S"',
            'password' => 'maxlength="' . PoliticaPassword::MAXIMO . '" data-caracteres="\\s\\S" data-max-bytes="' . PoliticaPassword::MAXIMO . '"',
            default => throw new InvalidArgumentException('Campo de cuenta no válido.'),
        };
    }

    public static function documento(string $valor): ?string
    {
        $valor = self::normalizar('documento', $valor);
        return preg_match('/^\d{5,15}$/D', $valor) ? null : 'El documento debe tener entre 5 y 15 dígitos.';
    }

    public static function correo(string $valor): ?string
    {
        $valor = self::normalizar('email', $valor);
        return filter_var($valor, FILTER_VALIDATE_EMAIL) && strlen($valor) <= self::CORREO_MAX ? null : 'Escribe un correo electrónico válido.';
    }

    public static function telefono(string $valor): ?string
    {
        return ValidadorTelefono::esValido(ValidadorTelefono::normalizar($valor)) ? null : ValidadorTelefono::MENSAJE;
    }

    public static function nombre(string $valor): ?string
    {
        $valor = self::normalizar('nombre_completo', $valor);
        if (mb_strlen($valor) < 3 || mb_strlen($valor) > self::NOMBRE_MAX) {
            return 'El nombre debe tener entre 3 y 100 caracteres.';
        }
        return preg_match('/^[' . self::NOMBRE_CARACTERES . ']+$/uD', $valor) ? null : 'El nombre solo admite letras, espacios, apóstrofo y guion.';
    }
}
