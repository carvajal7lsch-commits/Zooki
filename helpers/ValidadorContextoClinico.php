<?php

/** Datos declarados comunes de C2/C4; no deduce vacunación ni fechas desde la ficha. */
final class ValidadorContextoClinico
{
    public static function validar(array $entrada): array
    {
        $vacunacion = $entrada['vacunacion'] ?? null;
        if ($vacunacion !== null && !in_array($vacunacion, ['completa', 'incompleta', 'desconocida'], true)) {
            throw new InvalidArgumentException('Vacunación no válida.');
        }
        $dias = $entrada['dias_desde_celo'] ?? null;
        if ($dias !== null && ((!is_string($dias) && !is_int($dias) && !is_float($dias)) || !is_numeric($dias) || !is_finite((float) $dias) || (float) $dias < 0)) {
            throw new InvalidArgumentException('Días desde el celo no válidos.');
        }
        return ['vacunacion' => $vacunacion, 'dias_desde_celo' => $dias === null ? null : (float) $dias];
    }
}
