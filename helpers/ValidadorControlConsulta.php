<?php

/** RN-431: límites confirmados por el dueño el 2026-10-30. */
final class ValidadorControlConsulta
{
    public const BUSQUEDA_DEFECTO = 30;
    public const SIN_DURACION_DEFECTO = 7;
    public const DIAS_MAX = 365;

    public static function diasValidos(mixed $valor): bool
    {
        return is_string($valor) && ctype_digit($valor) && strlen($valor) <= 3 && (int) $valor >= 1 && (int) $valor <= self::DIAS_MAX;
    }
}
