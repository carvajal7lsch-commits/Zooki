<?php
require_once __DIR__ . '/EvaluadorCondiciones.php';
require_once __DIR__ . '/ConocimientoClinico.php';

/** HU-2.11: candidatos independientes; no recibe texto libre, límites ni mora. */
final class MotorTriage
{
    public const SEVERIDAD = ['verde' => 0, 'amarillo' => 1, 'naranja' => 2, 'rojo' => 3];

    public static function calcular(array $sintomas, array $exposiciones, array $paciente, ConocimientoClinico $grafo, DateTimeImmutable $fecha): array
    {
        $contexto = $paciente;
        unset($contexto['nota'], $contexto['texto_libre']);
        $contexto['sintomas'] = $sintomas;
        $contexto['exposiciones'] = $exposiciones;
        $candidatos = [];
        $aplicadas = [];
        $faltantes = [];
        $revision = false;
        $inmediata = false;
        $contagio = false;
        $especie = $grafo->nodos[$paciente['especie'] ?? ''] ?? [];
        $cobertura = $especie['metadatos']['cobertura']['triage'] ?? 'sin_cobertura';
        $cubierta = in_array($cobertura, ['parcial', 'completa'], true);
        $moderados = [];
        foreach ($sintomas as $codigo => $episodio) {
            $nodo = $grafo->nodos[$codigo] ?? null;
            if ($nodo === null || $nodo['tipo'] !== 'sintoma') {
                throw new InvalidArgumentException('Síntoma no válido, retirado o de otro tipo.');
            }
            if ($nodo['alarma_universal'] ?? false) {
                $candidatos[] = 'rojo';
                $aplicadas[] = 'P1:' . $codigo;
            } elseif ($cubierta && isset($nodo['nivel_triage'])) {
                $candidatos[] = $nodo['nivel_triage'];
                $aplicadas[] = 'base:' . $codigo;
            }
            if ($nodo['moderado'] ?? false) {
                $moderados[$codigo] = $nodo['nivel_triage'] ?? 'amarillo';
            }
        }
        // P3 escala una sola vez la evidencia moderada, nunca el resultado de otra regla.
        $compromiso = isset($sintomas['sintoma.decaimiento']) || isset($sintomas['sintoma.encias_secas']);
        if (count($moderados) >= 3 || ($compromiso && count($moderados) >= 2)) {
            $base = max(array_map(static fn (string $nivel): int => self::SEVERIDAD[$nivel], $moderados));
            $candidatos[] = array_search(min(3, $base + 1), self::SEVERIDAD, true);
            $aplicadas[] = 'P3';
        }
        foreach ($grafo->reglas as $regla) {
            if ($regla['motor'] !== 'triage') {
                continue;
            }
            $evaluada = EvaluadorCondiciones::evaluar($regla['condiciones'], $contexto, $fecha);
            if ($evaluada['valor'] === null) {
                $revision = true;
                foreach ($evaluada['faltantes'] as $campo) {
                    $faltantes[] = ['regla' => $regla['codigo'], 'campo' => $campo];
                }
            }
            if ($evaluada['valor'] !== true) {
                continue;
            }
            $aplicadas[] = $regla['codigo'];
            $resultado = $regla['resultado'];
            if (isset($resultado['nivel'])) {
                $candidatos[] = $resultado['nivel'];
            }
            $revision = $revision || ($resultado['revision'] ?? false);
            $inmediata = $inmediata || ($resultado['revision_inmediata'] ?? false);
            $contagio = $contagio || ($resultado['contagio'] ?? false);
        }
        if (!$cubierta || isset($sintomas['sintoma.otro_no_se']) || $candidatos === []) {
            $candidatos[] = 'amarillo';
            $revision = true;
            $aplicadas[] = 'P6';
        }
        $severidad = max(array_map(static fn (string $nivel): int => self::SEVERIDAD[$nivel], $candidatos));
        $nivel = array_search($severidad, self::SEVERIDAD, true);
        if ($nivel === 'rojo') {
            $revision = false;
        }
        return ['nivel' => $nivel, 'revision' => $revision, 'revision_inmediata' => $inmediata, 'contagio' => $contagio, 'reglas_aplicadas' => array_values(array_unique($aplicadas)), 'datos_faltantes' => $faltantes, 'version' => $grafo->version, 'aviso' => $grafo->limitaciones];
    }
}
