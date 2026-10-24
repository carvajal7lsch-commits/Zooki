<?php
final class UbicacionCarnet
{
    /** RE-5.11.5: DECIMAL(9,6), sin exponentes ni redondeo silencioso en servidor. */
    public static function coordenada(mixed $valor, int $limite): ?string
    {
        if (!is_string($valor) || !preg_match('/^-?\d{1,3}(?:\.\d{1,6})?$/D', $valor) || abs((float) $valor) > $limite) {
            return null;
        }
        return number_format((float) $valor, 6, '.', '');
    }

    public static function mapa(string $latitud, string $longitud): string
    {
        return 'https://www.openstreetmap.org/?mlat=' . rawurlencode($latitud) . '&mlon=' . rawurlencode($longitud);
    }
}
