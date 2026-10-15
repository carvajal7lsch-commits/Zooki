<?php
require_once __DIR__ . '/EvaluadorCondiciones.php';
require_once __DIR__ . '/ConocimientoClinico.php';

/** RE-2.9.1–4: evaluación interna pura; sus evidencias protegidas nunca se serializan directamente. */
final class MotorPrescripcion
{
    public static function evaluar(?string $farmaco, array $contexto, array $alergias, array $vigentes, array $otrasFilas, ConocimientoClinico $grafo, DateTimeImmutable $fecha): array
    {
        $resultado = ['bloqueada' => false, 'motivos' => [], 'advertencias' => [], 'evaluacion_incompleta' => false, 'datos_faltantes' => [], 'requiere_decision' => false, 'requiere_conciliacion' => false, 'version' => $grafo->version];
        if ($farmaco === null) {
            $resultado['evaluacion_incompleta'] = true;
            $resultado['datos_faltantes'][] = 'farmaco';
            return $resultado;
        }
        foreach ($alergias as $alergia) {
            if (($alergia['farmaco'] ?? null) === $farmaco) {
                $resultado['motivos'][] = ['tipo' => 'alergia', 'mensaje' => 'Alergia activa registrada a este fármaco. Elige otro.', 'alerta' => $alergia['id'] ?? null];
            } elseif (($alergia['farmaco'] ?? null) === null) {
                self::incompleta($resultado, 'alergia_sin_enlace');
            }
        }
        foreach ($grafo->aristas as $arista) {
            if ($arista['tipo_relacion'] !== 'toxico_para' || $arista['destino'] !== $farmaco) {
                continue;
            }
            $tipo = $grafo->nodos[$arista['origen']]['tipo'] ?? null;
            $dato = $tipo === 'especie' ? ($contexto['especie'] ?? null) : ($contexto['raza']['codigo'] ?? null);
            if ($dato === null) {
                self::incompleta($resultado, $tipo === 'especie' ? 'especie' : 'raza');
                continue;
            }
            if ($dato !== $arista['origen'] || !self::aplica($arista['condiciones'] ?? null, $contexto, $fecha, $resultado)) {
                continue;
            }
            $evidencia = ['tipo' => 'toxicidad', 'mensaje' => 'Toxicidad documentada para la especie o raza del paciente.', 'arista' => $arista['codigo'], 'fuentes' => $arista['fuentes'], 'protegida' => false];
            if ($arista['efecto'] === 'bloqueo') {
                $resultado['motivos'][] = $evidencia;
            } elseif ($arista['efecto'] === 'advertencia') {
                $resultado['advertencias'][] = $evidencia;
            }
        }
        foreach ([...$vigentes, ...$otrasFilas] as $medicacion) {
            $otro = $medicacion['farmaco'] ?? null;
            if ($otro === null) {
                self::incompleta($resultado, 'medicacion_sin_enlace');
                continue;
            }
            if (($medicacion['vigencia'] ?? 'vigente') === 'desconocida') {
                self::incompleta($resultado, 'vigencia_desconocida');
            }
            foreach ($grafo->aristas as $arista) {
                $pareja = ($arista['origen'] === $farmaco && $arista['destino'] === $otro) || ($arista['destino'] === $farmaco && $arista['origen'] === $otro);
                if ($arista['tipo_relacion'] !== 'interactua_con' || !$pareja || !self::aplica($arista['condiciones'] ?? null, $contexto, $fecha, $resultado)) {
                    continue;
                }
                $desconocida = ($medicacion['vigencia'] ?? 'vigente') === 'desconocida';
                $resultado['advertencias'][] = ['tipo' => 'interaccion', 'mensaje' => $desconocida ? 'Posible interacción con vigencia desconocida; conciliar.' : 'Interacción con otra medicación.', 'farmaco' => $otro, 'medicacion' => $medicacion, 'arista' => $arista['codigo'], 'fuentes' => $arista['fuentes'], 'protegida' => !($medicacion['visible'] ?? false)];
            }
        }
        foreach ($grafo->reglas as $regla) {
            if ($regla['motor'] !== 'prescripcion' || !self::aplica($regla['condiciones'], $contexto, $fecha, $resultado)) {
                continue;
            }
            $evidencia = ['tipo' => $regla['resultado']['motivo'], 'mensaje' => 'Regla de seguridad de prescripción aplicable.', 'regla' => $regla['codigo'], 'fuentes' => $regla['fuentes'], 'protegida' => false];
            if ($regla['resultado']['efecto'] === 'bloqueo') {
                $resultado['motivos'][] = $evidencia;
            } elseif ($regla['resultado']['efecto'] === 'advertencia') {
                $resultado['advertencias'][] = $evidencia;
            }
        }
        $resultado['bloqueada'] = $resultado['motivos'] !== [];
        $resultado['requiere_decision'] = !$resultado['bloqueada'] && $resultado['advertencias'] !== [];
        $resultado['requiere_conciliacion'] = in_array(true, array_column($resultado['advertencias'], 'protegida'), true);
        return $resultado;
    }

    private static function aplica(?array $condicion, array $contexto, DateTimeImmutable $fecha, array &$resultado): bool
    {
        $evaluacion = EvaluadorCondiciones::evaluar($condicion, $contexto, $fecha);
        foreach ($evaluacion['faltantes'] as $campo) {
            self::incompleta($resultado, $campo);
        }
        return $evaluacion['valor'] === true;
    }

    private static function incompleta(array &$resultado, string $campo): void
    {
        $resultado['evaluacion_incompleta'] = true;
        $resultado['datos_faltantes'] = array_values(array_unique([...$resultado['datos_faltantes'], $campo]));
    }
}
