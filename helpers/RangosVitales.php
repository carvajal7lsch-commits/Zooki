<?php

/** RE-2.1.6: referencia informativa pendiente de validación veterinaria; no valida captura.
 * Temperatura: https://www.msdvetmanual.com/multimedia/table/normal-rectal-temperature-ranges
 * Pulso/respiración: https://www.aspcapro.org/sites/default/files/NormalTPRDogsCatsPoster.pdf
 * El intervalo canino sin tamaño es la envolvente de los tres tamaños (decisión del dueño, 2026-10-30).
 */
final class RangosVitales
{
    public const AVISO = 'Referencia pendiente de validación veterinaria';
    public const CAMBIO_PESO = 0.10;

    public static function para(string $especie, ?string $tamanio = null): array
    {
        $especie = mb_strtolower($especie);
        if (in_array($especie, ['canino', 'perro', 'especie.canino'], true)) {
            $pulso = match ($tamanio) {
                'pequeno' => [90, 120],
                'mediano' => [70, 110],
                'grande', 'gigante' => [60, 90],
                default => [60, 120],
            };
            return ['temperatura' => [37.5, 39.2], 'frecuencia_cardiaca' => $pulso, 'frecuencia_respiratoria' => [18, 24]];
        }
        if (in_array($especie, ['felino', 'gato', 'especie.felino'], true)) {
            return ['temperatura' => [38.1, 39.2], 'frecuencia_cardiaca' => [150, 200], 'frecuencia_respiratoria' => [20, 30]];
        }
        return [];
    }
}
