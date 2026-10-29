<?php
require_once __DIR__ . '/ValidadorClinica.php';
require_once __DIR__ . '/CampoInvalido.php';

/** HU-7.4: límites compartidos y topes de minutos aprobados por el dueño el 2026-10-26. */
final class ValidadorConfiguracionClinica
{
    public const TIPO_MAX = 100;
    public const LOGO_MAX = 255;
    public const ENTERO_MAX = 2147483647;
    public const DURACION_MAX = 720;
    public const MARGEN_MAX = 120;

    public static function logoValido(string $valor): bool
    {
        if ($valor === '') {
            return true;
        }
        if (mb_strlen($valor) > self::LOGO_MAX || preg_match('/[\s<>"\x27\\\\]/u', $valor)) {
            return false;
        }
        $esquema = parse_url($valor, PHP_URL_SCHEME);
        if ($esquema !== null) {
            return in_array(strtolower((string) $esquema), ['http', 'https'], true)
                && filter_var($valor, FILTER_VALIDATE_URL) !== false;
        }
        return !str_starts_with($valor, '//') && !str_contains($valor, ':');
    }

    public static function campo(string $campo, mixed $valor): ?string
    {
        if (!is_string($valor) && !is_int($valor)) {
            return 'Valor no válido.';
        }
        $valor = trim((string) $valor);
        return match ($campo) {
            'nombre' => ValidadorClinica::campo('nombre_clinica', $valor),
            'direccion' => $valor === '' ? null : ValidadorClinica::campo('direccion', $valor),
            'telefono', 'telefono_urgencias' => $valor === '' || ValidadorTelefono::esValido($valor) ? null : ValidadorTelefono::MENSAJE,
            // Es contacto público de la clínica; no identifica una cuenta ni exige unicidad (RE-7.4.1).
            'email_contacto' => $valor === '' ? null : ValidadorCuenta::correo($valor),
            'url_logo' => self::logoValido($valor) ? null : 'Usa una URL HTTP/HTTPS o una ruta de imagen, de hasta 255 caracteres.',
            'nombre_tipo' => mb_strlen($valor) >= 1 && mb_strlen($valor) <= self::TIPO_MAX
                && preg_match('~^[' . ValidadorClinica::TEXTO_CARACTERES . ']+$~uD', $valor) === 1 ? null : 'Escribe el nombre del tipo, de hasta 100 caracteres.',
            'duracion_minutos' => ctype_digit($valor) && strlen($valor) <= 3 && (int) $valor >= 1 && (int) $valor <= self::DURACION_MAX
                ? null : 'La duración debe ser de 1 a ' . self::DURACION_MAX . ' minutos.',
            'margen_minutos' => ctype_digit($valor) && strlen($valor) <= 3 && (int) $valor <= self::MARGEN_MAX
                ? null : 'El margen debe ser de 0 a ' . self::MARGEN_MAX . ' minutos.',
            'pausable', 'activo' => in_array($valor, ['0', '1'], true) ? null : 'Elige Sí o No.',
            default => 'Campo no válido.',
        };
    }

    public static function datos(array $entrada, bool $tipo = false): array
    {
        $campos = $tipo ? ['nombre_tipo', 'duracion_minutos', 'margen_minutos', 'pausable']
            : ['nombre', 'direccion', 'telefono', 'telefono_urgencias', 'email_contacto', 'url_logo'];
        $datos = [];
        foreach ($campos as $campo) {
            $valor = $entrada[$campo] ?? '';
            if (is_string($valor)) {
                $valor = in_array($campo, ['nombre', 'nombre_tipo', 'direccion', 'telefono', 'telefono_urgencias'], true)
                    ? trim(preg_replace('/\s+/u', ' ', $valor)) : trim($valor);
            }
            $error = self::campo($campo, $valor);
            if ($error !== null) {
                throw new CampoInvalido($campo, $error, $error);
            }
            $datos[$campo] = in_array($campo, ['duracion_minutos', 'margen_minutos', 'pausable'], true)
                ? (int) $valor : ($valor === '' ? null : $valor);
        }
        return $datos;
    }
}
