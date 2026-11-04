<?php
/** HU-0.1/0.5: una intención de compra nunca es una asignación de plan. */
final class EleccionPlanRegistro
{
    public static function ciclo(array $entrada): ?string
    {
        $plan = $entrada['plan'] ?? '';
        $ciclo = $entrada['ciclo'] ?? '';
        if (!is_string($plan) || !is_string($ciclo) || !in_array($plan, ['', 'profesional'], true)) {
            throw new InvalidArgumentException('La elección del plan no es válida.');
        }
        if ($plan === '') {
            if ($ciclo !== '') throw new InvalidArgumentException('Elige Profesional para indicar un ciclo de pago.');
            return null;
        }
        $ciclo = $ciclo === '' ? 'mensual' : $ciclo;
        if (!in_array($ciclo, ['mensual', 'anual'], true)) throw new InvalidArgumentException('Elige ciclo mensual o anual.');
        return $ciclo;
    }
}
