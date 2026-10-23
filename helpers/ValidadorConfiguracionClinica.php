<?php
require_once __DIR__ . '/ValidadorClinica.php';
require_once __DIR__ . '/CampoInvalido.php';

/** HU-7.4: límites del esquema y reglas compartidas de contacto, sin topes de producto nuevos. */
final class ValidadorConfiguracionClinica
{
    public const TIPO_MAX = 100;
    public const LOGO_MAX = 255;
    public const ENTERO_MAX = 2147483647;

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
            'email_contacto' => $valor === '' ? null : ValidadorCuenta::correoDeCuenta($valor),
            'url_logo' => self::logoValido($valor) ? null : 'Usa una URL HTTP/HTTPS o una ruta de imagen, de hasta 255 caracteres.',
            'nombre_tipo' => mb_strlen($valor) >= 1 && mb_strlen($valor) <= self::TIPO_MAX
                && preg_match('~^[' . ValidadorClinica::TEXTO_CARACTERES . ']+$~uD', $valor) === 1 ? null : 'Escribe el nombre del tipo, de hasta 100 caracteres.',
            'duracion_minutos', 'margen_minutos' => ctype_digit($valor) && strlen($valor) <= 10
                && (int) $valor >= ($campo === 'duracion_minutos' ? 1 : 0) && (int) $valor <= self::ENTERO_MAX
                ? null : ($campo === 'duracion_minutos' ? 'Duración entera positiva.' : 'Margen entero no negativo.'),
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
