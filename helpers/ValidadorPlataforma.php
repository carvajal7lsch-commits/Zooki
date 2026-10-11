<?php

/** HU-0.3: límites del TEXT de MySQL; HTML y servidor comparten la regla. */
final class ValidadorPlataforma
{
    public const TEXTO_MAX = 65535;
    public const BUSQUEDA_MAX = 255;

    public static function busqueda(string $valor): string
    {
        $valor = trim(preg_replace('/\s+/u', ' ', $valor) ?? '');
        if (mb_strlen($valor) > self::BUSQUEDA_MAX || ($valor !== '' && preg_match('/^[\p{L}\p{M}\p{N}\p{P}\p{S} ]+$/uD', $valor) !== 1)) {
            throw new InvalidArgumentException('Revisa la búsqueda; admite hasta 255 caracteres.');
        }
        return $valor;
    }

    public static function texto(string $valor): string
    {
        $valor = trim(preg_replace('/\s+/u', ' ', $valor) ?? '');
        if ($valor === '' || strlen($valor) > self::TEXTO_MAX || preg_match('/^[\p{L}\p{M}\p{N}\p{P}\p{S} ]+$/uD', $valor) !== 1) {
            throw new InvalidArgumentException('Escribe un motivo o nota de hasta 65535 bytes.');
        }
        return $valor;
    }

    public static function atributos(): string
    {
        return 'required maxlength="' . self::TEXTO_MAX . '" data-max-bytes="' . self::TEXTO_MAX . '" data-caracteres="\\p{L}\\p{M}\\p{N}\\p{P}\\p{S} " data-espacios="normalizar"';
    }
}
