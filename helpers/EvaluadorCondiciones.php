<?php

/** A.2: lógica ternaria pura, compartida por los tres motores; null significa desconocido. */
final class EvaluadorCondiciones
{
    public static function evaluar(?array $condicion, array $contexto, DateTimeImmutable $fecha): array
    {
        if ($condicion === null) {
            return ['valor' => true, 'faltantes' => []];
        }
        foreach (['todos', 'alguno'] as $grupo) {
            if (isset($condicion[$grupo])) {
                $resultados = array_map(static fn (array $hijo): array => self::evaluar($hijo, $contexto, $fecha), $condicion[$grupo]);
                $valores = array_column($resultados, 'valor');
                $decisivo = $grupo === 'todos' ? false : true;
                if (in_array($decisivo, $valores, true)) {
                    return ['valor' => $decisivo, 'faltantes' => []];
                }
                $faltantes = [];
                foreach ($resultados as $resultado) {
                    $faltantes = [...$faltantes, ...$resultado['faltantes']];
                }
                return ['valor' => in_array(null, $valores, true) ? null : !$decisivo, 'faltantes' => array_values(array_unique($faltantes))];
            }
        }
        $predicado = $condicion['predicado'];
        $codigo = $condicion['codigo'] ?? null;
        $campo = $predicado;
        $valor = null;
        switch ($predicado) {
            case 'sintoma_marcado':
            case 'exposicion':
                $campo = $predicado === 'sintoma_marcado' ? 'sintomas' : 'exposiciones';
                $valor = isset($contexto[$campo]) ? array_key_exists($codigo, $contexto[$campo]) : null;
                break;
            case 'alerta_activa':
                $campo = 'alertas_activas';
                $valor = isset($contexto[$campo]) ? in_array($codigo, $contexto[$campo], true) : null;
                break;
            case 'raza':
                $campo = 'raza.atributos.' . $condicion['atributo'];
                $dato = $contexto['raza']['atributos'][$condicion['atributo']] ?? null;
                $valor = $dato === null ? null : $dato === $condicion['valor'];
                break;
            case 'inicio_episodio':
            case 'minutos_desde_exposicion':
                $grupo = $predicado === 'inicio_episodio' ? 'sintomas' : 'exposiciones';
                $campo = $grupo . '.' . $codigo . '.minutos';
                $episodios = $contexto[$grupo] ?? null;
                if ($episodios !== null && !array_key_exists($codigo, $episodios)) {
                    $valor = false;
                    break;
                }
                $minutos = self::minutos($episodios[$codigo] ?? null, $fecha);
                $valor = $minutos === null ? null : self::enIntervalo($minutos, $condicion);
                break;
            case 'edad_meses':
            case 'dias_desde_celo':
                $dato = $contexto[$predicado] ?? null;
                $valor = $dato === null ? null : self::enIntervalo($dato, $condicion);
                break;
            case 'especie':
            case 'sexo':
            case 'esterilizado':
            case 'vacunacion':
                $dato = $contexto[$predicado] ?? null;
                $esperado = $codigo ?? $condicion['valor'];
                $valor = $dato === null ? null : $dato === $esperado;
                break;
            default:
                throw new InvalidArgumentException('Predicado no permitido: ' . $predicado);
        }
        return ['valor' => $valor, 'faltantes' => $valor === null ? [$campo] : []];
    }

    private static function minutos(?array $episodio, DateTimeImmutable $fecha): ?float
    {
        if (isset($episodio['minutos'])) {
            return is_numeric($episodio['minutos']) && is_finite((float) $episodio['minutos']) && $episodio['minutos'] >= 0 ? (float) $episodio['minutos'] : null;
        }
        $inicio = $episodio['desde'] ?? null;
        if (!$inicio instanceof DateTimeImmutable || $inicio > $fecha) {
            return null;
        }
        return ($fecha->getTimestamp() - $inicio->getTimestamp()) / 60;
    }

    private static function enIntervalo(float $valor, array $condicion): bool
    {
        if (isset($condicion['minimo']) && ($valor < $condicion['minimo'] || ($valor == $condicion['minimo'] && !$condicion['incluye_minimo']))) {
            return false;
        }
        if (isset($condicion['maximo']) && ($valor > $condicion['maximo'] || ($valor == $condicion['maximo'] && !$condicion['incluye_maximo']))) {
            return false;
        }
        return true;
    }
}
