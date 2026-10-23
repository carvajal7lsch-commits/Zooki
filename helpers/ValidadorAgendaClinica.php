<?php
require_once __DIR__ . '/IntervalosAgenda.php';
require_once __DIR__ . '/ValidadorCuenta.php';
require_once __DIR__ . '/ValidadorTelefono.php';

/** C6/C7: los formularios comparten límites y no convierten contacto en titularidad. */
final class ValidadorAgendaClinica
{
    public const MOTIVO_MAX = 255;

    public static function instante(string $valor): string
    {
        $valor = str_replace('T', ' ', $valor);
        if (strlen($valor) === 16) {
            $valor .= ':00';
        }
        return IntervalosAgenda::fecha($valor)->format('Y-m-d H:i:s');
    }

    public static function motivo(mixed $valor, bool $obligatorio = true): string
    {
        $texto = trim((string) $valor);
        if (($obligatorio && $texto === '') || mb_strlen($texto) > self::MOTIVO_MAX || preg_match('/[\p{C}]/u', $texto)) {
            throw new InvalidArgumentException('El motivo admite de 1 a 255 caracteres de texto.');
        }
        return $texto;
    }

    public static function contacto(array $entrada): array
    {
        $nombre = ValidadorCuenta::normalizar('nombre_completo', (string) ($entrada['nombre_acompanante'] ?? ''));
        $telefono = ValidadorTelefono::normalizar((string) ($entrada['telefono_acompanante'] ?? ''));
        if ($nombre !== '' && ($error = ValidadorCuenta::nombre($nombre)) !== null) {
            throw new InvalidArgumentException($error);
        }
        if ($telefono !== '' && !ValidadorTelefono::esValido($telefono)) {
            throw new InvalidArgumentException(ValidadorTelefono::MENSAJE);
        }
        return [$nombre ?: null, $telefono ?: null];
    }
}
