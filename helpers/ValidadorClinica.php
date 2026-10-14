<?php
require_once __DIR__ . '/ValidadorCuenta.php';
require_once __DIR__ . '/ValidadorNit.php';
require_once __DIR__ . '/DominiosDesechables.php';
require_once __DIR__ . '/../models/Usuario.php';

/** RE-0.1.1–5/10: reglas compartidas por el registro y la ayuda de formato. */
final class ValidadorClinica
{
    public const NOMBRE_MIN = 3;
    public const NOMBRE_MAX = 150;
    public const DIRECCION_MAX = 255;
    public const NIT_PRESENTACION_MAX = ValidadorNit::NUMERO_MAX;
    public const TEXTO_CARACTERES = "\\p{L}\\p{M}\\p{N} .,'’&()+/#:\\-";

    public static function atributosHtml(string $campo): string
    {
        if ($campo === 'nit') {
            return 'inputmode="numeric" maxlength="' . self::NIT_PRESENTACION_MAX . '" data-caracteres="0-9"';
        }
        if ($campo === 'dv') {
            return 'inputmode="numeric" maxlength="1" data-caracteres="0-9"';
        }
        $maximo = $campo === 'nombre_clinica' ? self::NOMBRE_MAX : self::DIRECCION_MAX;
        $caracteres = htmlspecialchars(self::TEXTO_CARACTERES, ENT_QUOTES, 'UTF-8');
        return 'maxlength="' . $maximo . '" data-caracteres="' . $caracteres . '" data-espacios="normalizar"';
    }

    public static function campo(string $campo, string $valor): ?string
    {
        $valor = ValidadorCuenta::normalizar($campo, $valor);
        return match ($campo) {
            'nit' => preg_match('/^[0-9]{1,14}$/D', $valor) && (int) $valor > 0 ? null : 'NIT de 1 a 14 dígitos, sin el DV.',
            'dv' => preg_match('/^[0-9]$/D', $valor) ? null : 'El DV es un solo dígito.',
            'nombre_clinica' => self::textoValido($valor, self::NOMBRE_MIN, self::NOMBRE_MAX) ? null : 'Nombre de 3 a 150 caracteres.',
            'direccion' => self::textoValido($valor, 1, self::DIRECCION_MAX) ? null : 'Dirección de hasta 255 caracteres.',
            'email_clinica', 'email_administrador' => ValidadorCuenta::correoDeCuenta($valor),
            default => 'Campo no válido.',
        };
    }

    public static function formulario(array $entrada): array
    {
        $datos = [];
        foreach (['nombre_clinica', 'nit', 'dv', 'direccion', 'telefono_clinica', 'email_clinica', 'nombre_completo', 'tipo_documento', 'documento', 'telefono', 'email'] as $campo) {
            $datos[$campo] = ValidadorCuenta::normalizar($campo, (string) ($entrada[$campo] ?? ''));
        }
        foreach (['nombre_clinica', 'nit', 'direccion', 'email_clinica'] as $campo) {
            self::exigir(self::campo($campo, $datos[$campo]));
        }
        self::exigir(ValidadorNit::validarSeparado($datos['nit'], $datos['dv']));
        self::exigir(self::campo('email_administrador', $datos['email']));
        self::exigir(ValidadorCuenta::nombre($datos['nombre_completo']));
        self::exigir(ValidadorCuenta::documento($datos['documento']));
        foreach (['telefono', 'telefono_clinica'] as $campo) {
            self::exigir(ValidadorCuenta::telefono($datos[$campo]));
            $datos[$campo] = ValidadorTelefono::normalizar($datos[$campo]);
        }
        if (!in_array($datos['tipo_documento'], Usuario::TIPOS_DOCUMENTO, true)) {
            throw new InvalidArgumentException('Elige el tipo de documento.');
        }
        if (($entrada['acepta_datos'] ?? '') !== '1') {
            throw new InvalidArgumentException('Debes aceptar la política de tratamiento de datos.');
        }
        $datos['password'] = (string) ($entrada['password'] ?? '');
        self::exigir(PoliticaPassword::validar($datos['password'], [$datos['nombre_completo'], $datos['documento'], $datos['email']]));
        if ($datos['password'] !== (string) ($entrada['confirm_password'] ?? '')) {
            throw new InvalidArgumentException('Las contraseñas no coinciden.');
        }
        $datos['nit'] = ValidadorNit::normalizar($datos['nit'] . '-' . $datos['dv']);
        $datos['email'] = mb_strtolower($datos['email']);
        $datos['email_clinica'] = mb_strtolower($datos['email_clinica']);
        return $datos;
    }

    private static function exigir(?string $error): void
    {
        if ($error !== null) {
            throw new InvalidArgumentException($error);
        }
    }

    private static function textoValido(string $valor, int $minimo, int $maximo): bool
    {
        return mb_strlen($valor) >= $minimo && mb_strlen($valor) <= $maximo
            && preg_match('~^[' . self::TEXTO_CARACTERES . ']+$~uD', $valor) === 1;
    }
}
