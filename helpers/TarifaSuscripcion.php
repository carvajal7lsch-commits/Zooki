<?php

/** RN-008, D1: precios COP del catálogo; total sin IVA y aritmética entera. */
final class TarifaSuscripcion
{
    // RN-008: único interruptor de la oferta; apagarlo conserva los periodos ya acreditados.
    public const OFERTA_LANZAMIENTO_ACTIVA = true;

    public static function mesesConcedidos(string $ciclo, bool $pagosAprobadosPrevios, ?bool $ofertaActiva = null): int
    {
        if (!in_array($ciclo, ['mensual', 'anual'], true)) {
            throw new InvalidArgumentException('Elige ciclo mensual o anual.');
        }
        return $ciclo === 'anual' ? 12 : ((($ofertaActiva ?? self::OFERTA_LANZAMIENTO_ACTIVA) && !$pagosAprobadosPrevios) ? 2 : 1);
    }

    public static function textos(array $plan, bool $ofertaDisponible): array
    {
        $mensual = '$' . number_format((int)$plan['precio_mensual'], 0, ',', '.') . '/mes';
        $anual = '$' . number_format((int)$plan['precio_anual'], 0, ',', '.') . '/año';
        if (self::OFERTA_LANZAMIENTO_ACTIVA && $ofertaDisponible) {
            $mensual .= ' · Oferta de lanzamiento: tu primer mes incluye otro gratis';
        }
        if ((int)$plan['precio_anual'] === (int)$plan['precio_mensual'] * 10) {
            $anual .= ' · 2 meses gratis';
        }
        return ['mensual' => $mensual, 'anual' => $anual];
    }

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
