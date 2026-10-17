<?php
require_once __DIR__ . '/EvaluadorCondiciones.php';
require_once __DIR__ . '/ConocimientoClinico.php';

/** HU-2.8: afinidad por evidencia explícita, nunca probabilidad ni elección automática. */
final class MotorDiagnosticos
{
    public static function sugerir(ConocimientoClinico $grafo, array $contexto, DateTimeImmutable $fecha): array
    {
        $candidatos = [];
        $faltantes = [];
        foreach ($grafo->aristas as $arista) {
            if ($arista['tipo_relacion'] !== 'sugiere') {
                continue;
            }
            $origen = $grafo->nodos[$arista['origen']] ?? null;
            $destino = $grafo->nodos[$arista['destino']] ?? null;
            if (($origen['tipo'] ?? null) !== 'sintoma' || ($destino['tipo'] ?? null) !== 'diagnostico') {
                continue;
            }
            $codigo = $destino['codigo'];
            $evaluacion = EvaluadorCondiciones::evaluar($arista['condiciones'] ?? null, $contexto, $fecha);
            $marcado = array_key_exists($origen['codigo'], $contexto['sintomas'] ?? []);
            if ($evaluacion['valor'] === null && $marcado) {
                $faltantes[] = ['elemento' => $arista['codigo'], 'campos' => $evaluacion['faltantes']];
            }
            if ($evaluacion['valor'] !== true) {
                continue;
            }
            $candidatos[$codigo] ??= ['puntuacion' => 0.0, 'caracteristicos' => [], 'coincidentes' => [], 'aristas' => [], 'reglas' => [], 'fuentes' => $destino['fuentes']];
            $positivo = $arista['signo'] === 'positivo' && $arista['peso'] > 0;
            if ($positivo) {
                $candidatos[$codigo]['caracteristicos'][$origen['codigo']] = true;
            }
            if (!$marcado) {
                continue;
            }
            $candidatos[$codigo]['puntuacion'] += ($arista['signo'] === 'positivo' ? 1 : -1) * $arista['peso'];
            if ($positivo) {
                $candidatos[$codigo]['coincidentes'][$origen['codigo']] = true;
            }
            $candidatos[$codigo]['aristas'][] = $arista['codigo'];
            $candidatos[$codigo]['fuentes'] = [...$candidatos[$codigo]['fuentes'], ...$arista['fuentes']];
        }
        foreach ($grafo->reglas as $regla) {
            $codigo = $regla['resultado']['diagnostico'] ?? null;
            if ($regla['motor'] !== 'diagnosticos' || empty($candidatos[$codigo]['coincidentes'])) {
                continue;
            }
            $evaluacion = EvaluadorCondiciones::evaluar($regla['condiciones'], $contexto, $fecha);
            if ($evaluacion['valor'] === null) {
                $faltantes[] = ['elemento' => $regla['codigo'], 'campos' => $evaluacion['faltantes']];
            }
            if ($evaluacion['valor'] !== true) {
                continue;
            }
            $candidatos[$codigo]['puntuacion'] += $regla['resultado']['peso'];
            $candidatos[$codigo]['reglas'][] = $regla['codigo'];
            $candidatos[$codigo]['fuentes'] = [...$candidatos[$codigo]['fuentes'], ...$regla['fuentes']];
        }
        $salida = [];
        foreach ($candidatos as $codigo => $candidato) {
            if ($candidato['coincidentes'] === [] || $candidato['puntuacion'] <= 0) {
                continue;
            }
            $x = count($candidato['coincidentes']);
            $y = count($candidato['caracteristicos']);
            $coincidentes = array_keys($candidato['coincidentes']);
            sort($coincidentes);
            $salida[] = [
                'codigo' => $codigo,
                'etiqueta' => $grafo->nodos[$codigo]['etiqueta'],
                'puntuacion' => round($candidato['puntuacion'], 10),
                'cobertura' => ['coinciden' => $x, 'caracteristicos' => $y, 'texto' => "Coincide $x de $y síntomas característicos"],
                'sintomas_coincidentes' => $coincidentes,
                'aristas_aplicadas' => $candidato['aristas'],
                'reglas_aplicadas' => $candidato['reglas'],
                'fuentes' => array_values(array_unique($candidato['fuentes'], SORT_REGULAR)),
                'estado_validacion' => 'pendiente',
            ];
        }
        usort($salida, static function (array $a, array $b): int {
            $peso = $b['puntuacion'] <=> $a['puntuacion'];
            $cobertura = ($b['cobertura']['coinciden'] * $a['cobertura']['caracteristicos']) <=> ($a['cobertura']['coinciden'] * $b['cobertura']['caracteristicos']);
            return $peso ?: ($cobertura ?: strcmp($a['codigo'], $b['codigo']));
        });
        return ['candidatos' => array_slice($salida, 0, 5), 'datos_faltantes' => $faltantes, 'version' => $grafo->version, 'limitaciones' => $grafo->limitaciones, 'aviso' => 'Sugerencia de apoyo; el diagnóstico final lo decide el veterinario.', 'diagnostico_libre' => true];
    }
}
