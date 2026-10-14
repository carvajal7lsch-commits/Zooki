<?php
require_once __DIR__ . '/ValidadorTelefono.php';
require_once __DIR__ . '/PoliticaPassword.php';
require_once __DIR__ . '/DominiosDesechables.php';

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
        return preg_match('/^\d{5,15}$/D', $valor) ? null : 'Documento de 5 a 15 dígitos.';
    }

    /**
     * M5 C4a: el aviso dice qué le falta al correo, no solo que no sirve. Los
     * mismos mensajes y el mismo orden que motivoCorreo() de validacion-cuenta.js.
     */
    public static function correo(string $valor): ?string
    {
        $valor = self::normalizar('email', $valor);
        if (strlen($valor) > self::CORREO_MAX) {
            return 'El correo admite hasta ' . self::CORREO_MAX . ' caracteres.';
        }
        $arrobas = substr_count($valor, '@');
        if ($arrobas === 0) {
            return 'Al correo le falta la @.';
        }
        if ($arrobas > 1) {
            return 'El correo lleva una sola @.';
        }
        [$usuario, $dominio] = explode('@', $valor);
        if ($usuario === '') {
            return 'Escribe lo que va antes de la @.';
        }
        if (!preg_match('/^[^.\s]+(\.[^.\s]+)+$/D', $dominio)) {
            return 'Escribe el dominio completo, por ejemplo gmail.com.';
        }
        return filter_var($valor, FILTER_VALIDATE_EMAIL) ? null : 'Escribe un correo válido.';
    }

    /**
     * M5 C4b (decisión 3 del usuario, 2026-10-13; RE-0.1.5): el correo de una
     * cuenta tampoco puede ser temporal, como el de la clínica. Lo usan el
     * registro del propietario, su alta por la clínica, el cambio de correo y
     * la ayuda al escribir, para que el navegador y el servidor digan lo mismo.
     */
    public static function correoDeCuenta(string $valor): ?string
    {
        return self::correo($valor) ?? DominiosDesechables::validar(self::normalizar('email', $valor));
    }

    public static function telefono(string $valor): ?string
    {
        return ValidadorTelefono::esValido(ValidadorTelefono::normalizar($valor)) ? null : ValidadorTelefono::MENSAJE;
    }

    public static function nombre(string $valor): ?string
    {
        $valor = self::normalizar('nombre_completo', $valor);
        if (mb_strlen($valor) < 3 || mb_strlen($valor) > self::NOMBRE_MAX) {
            return 'Nombre de 3 a 100 caracteres.';
        }
        return preg_match('/^[' . self::NOMBRE_CARACTERES . ']+$/uD', $valor) ? null : "Nombre: letras, espacios, ' y -.";
    }
}
