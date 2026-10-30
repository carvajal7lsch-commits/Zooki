<?php
require_once __DIR__ . '/EvaluadorCondiciones.php';
require_once __DIR__ . '/ConocimientoClinico.php';
require_once __DIR__ . '/PrescripcionPublica.php';

/** RE-2.9.1–4: evaluación interna pura; sus evidencias protegidas nunca se serializan directamente. */
final class MotorPrescripcion
{
    /** RE-2.8.2/4, RE-2.9.4: solo pautas aplicables y seguras; nunca una orden clínica. */
    public static function sugerir(string $diagnostico, ?float $peso, array $contexto, array $alergias, array $vigentes, ConocimientoClinico $grafo, DateTimeImmutable $fecha): array
    {
        if (($grafo->nodos[$diagnostico]['tipo'] ?? null) !== 'diagnostico') {
            throw new InvalidArgumentException('Elige un diagnóstico activo del conocimiento.');
        }
        $aplicables = [];
        foreach ($grafo->aristas as $arista) {
            if ($arista['tipo_relacion'] !== 'trata' || $arista['origen'] !== $diagnostico || !is_array($arista['pauta'] ?? null)) {
                continue;
            }
            $condicion = EvaluadorCondiciones::evaluar($arista['condiciones'] ?? null, $contexto, $fecha);
            if ($condicion['valor'] !== true) {
                continue;
            }
            $interna = self::evaluar($arista['destino'], $contexto, $alergias, $vigentes, [], $grafo, $fecha);
            if (!$interna['bloqueada']) {
                $aplicables[] = $arista;
            }
        }
        usort($aplicables, static fn (array $a, array $b): int => ($b['peso'] <=> $a['peso']) ?: strcmp($a['codigo'], $b['codigo']));
        $filas = [];
        $vias = ['oral' => 'Oral', 'subcutanea' => 'Subcutánea', 'intramuscular' => 'Intramuscular', 'intravenosa' => 'Intravenosa', 'topica' => 'Tópica', 'otica' => 'Ótica', 'oftalmica' => 'Oftálmica'];
        foreach ($aplicables as $arista) {
            $pauta = $arista['pauta'];
            $otras = [];
            foreach ($aplicables as $otra) {
                if ($otra['codigo'] !== $arista['codigo']) {
                    $otras[] = ['farmaco' => $otra['destino'], 'visible' => true];
                }
            }
            $seguridad = self::evaluar($arista['destino'], $contexto, $alergias, $vigentes, $otras, $grafo, $fecha);
            $filas[] = [
                'codigo' => $arista['codigo'],
                'farmaco' => $arista['destino'],
                'medicamento' => $grafo->nodos[$arista['destino']]['etiqueta'],
                'dosis' => self::dosis($pauta, $peso),
                'via_administracion' => $vias[$pauta['via']],
                'duracion' => $pauta['duracion_dias'] === null ? 'según evolución' : $pauta['duracion_dias'] . ' días',
                'observaciones' => $pauta['indicacion'],
                'seguridad' => PrescripcionPublica::desde($seguridad, $grafo),
            ];
        }
        return ['diagnostico' => $diagnostico, 'plan' => $grafo->nodos[$diagnostico]['metadatos']['plan'] ?? ['examenes' => [], 'recomendaciones' => []], 'tratamientos' => $filas, 'version' => $grafo->version];
    }

    /** Tres cifras significativas para dosis pequeñas; evita redondearlas a cero. */
    private static function numero(float $numero): string
    {
        $decimales = max(2, 2 - (int) floor(log10($numero)));
        return rtrim(rtrim(number_format($numero, min(12, $decimales), ',', ''), '0'), ',');
    }

    public static function dosis(array $pauta, ?float $peso): string
    {
        $dosis = $pauta['dosis_texto'];
        if ($pauta['dosis_mg_kg'] !== null) {
            $rango = $pauta['dosis_mg_kg'];
            $texto = static fn (float $factor): string => self::numero($rango['minimo'] * $factor) . ($rango['minimo'] == $rango['maximo'] ? '' : '–' . self::numero($rango['maximo'] * $factor));
            $porKg = $texto(1) . ' mg/kg';
            $dosis = $peso !== null && $peso > 0 ? $texto($peso) . ' mg (' . $porKg . ')' : $porKg . ' · calcula con el peso';
        }
        return $dosis . ($pauta['frecuencia_horas'] === null ? ' · dosis única' : ' cada ' . $pauta['frecuencia_horas'] . ' h');
    }

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
            $evidencia = ['tipo' => 'toxicidad', 'mensaje' => 'Toxicidad documentada para la especie o raza del paciente.', 'arista' => $arista['codigo'], 'origen' => $arista['origen'], 'fuentes' => $arista['fuentes'], 'protegida' => false];
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
