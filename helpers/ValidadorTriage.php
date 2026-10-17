<?php
require_once __DIR__ . '/ConocimientoClinico.php';

final class ValidadorTriage
{
    public static function episodios(mixed $entrada, bool $exposiciones, ConocimientoClinico $grafo): array
    {
        if (!is_array($entrada) || !array_is_list($entrada)) {
            throw new InvalidArgumentException('Las marcas deben ser una lista de episodios.');
        }
        $salida = [];
        foreach ($entrada as $episodio) {
            if (!is_array($episodio) || !is_string($episodio['codigo'] ?? null)) {
                throw new InvalidArgumentException('Indica el código del episodio.');
            }
            $codigo = $episodio['codigo'];
            $tipo = $grafo->nodos[$codigo]['tipo'] ?? null;
            if (!in_array($tipo, $exposiciones ? ['sustancia', 'farmaco'] : ['sintoma'], true)) {
                throw new InvalidArgumentException('Código no válido, retirado o de otro tipo.');
            }
            if (isset($salida[$codigo])) {
                throw new InvalidArgumentException('No repitas la misma marca con tiempos distintos.');
            }
            $tiempo = $episodio['tiempo'] ?? 'no_se';
            $minutos = $episodio['minutos'] ?? null;
            if (!in_array($tiempo, ['conocido', 'no_se', 'no_aplica'], true) || ($tiempo === 'no_aplica' && ($exposiciones || $codigo !== 'sintoma.control_preventivo'))) {
                throw new InvalidArgumentException('Tiempo no válido; «No aplica» solo corresponde a atención preventiva.');
            }
            if ($tiempo === 'conocido') {
                if ((!is_int($minutos) && !is_float($minutos) && !is_string($minutos)) || !is_numeric($minutos) || !is_finite((float) $minutos) || (float) $minutos < 0) {
                    throw new InvalidArgumentException('Indica minutos conocidos no negativos.');
                }
                $minutos = (float) $minutos;
            } elseif ($minutos !== null) {
                throw new InvalidArgumentException('Un tiempo desconocido o no aplicable no lleva minutos.');
            }
            $salida[$codigo] = ['tiempo' => $tiempo, 'minutos' => $minutos];
        }
        return $salida;
    }
}
