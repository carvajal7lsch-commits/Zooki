<?php

/** RN-008, D1: precios COP del catálogo; total sin IVA y aritmética entera. */
final class TarifaSuscripcion
{
    public static function calcular(array $plan, string $ciclo): array
    {
        if (!in_array($ciclo, ['mensual', 'anual'], true)) {
            throw new InvalidArgumentException('Elige ciclo mensual o anual.');
        }
        $pesos = $plan[$ciclo === 'anual' ? 'precio_anual' : 'precio_mensual'] ?? null;
        if ($pesos === null || filter_var($pesos, FILTER_VALIDATE_INT) === false || (int) $pesos < 0 || (int) $pesos > intdiv(PHP_INT_MAX, 100)) {
            throw new InvalidArgumentException('La tarifa no está disponible.');
        }
        $total = (int) $pesos * 100;
        return ['moneda' => 'COP', 'subtotal_centavos' => $total, 'impuesto_centavos' => 0, 'total_centavos' => $total];
    }
}
