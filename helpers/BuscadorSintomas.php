<?php
require_once __DIR__ . '/ArchivosConocimiento.php';

/** RN-424: sugiere opciones; jamás selecciona ni calcula un nivel. */
final class BuscadorSintomas
{
    // Ocho opciones caben en el selector; reserva una para «Otro / no sé».
    public const MAX_RESULTADOS = 8;
    // Admite dos errores en palabras de seis letras sin ofrecer cualquier texto corto.
    public const SIMILITUD_MINIMA = 0.65;
    public const CONSULTA_MAX = 100;

    public static function buscar(string $consulta, array $catalogo, ?string $especie = null): array
    {
        $consulta = ArchivosConocimiento::normalizarTexto($consulta);
        $resultados = [];
        $otro = null;
        foreach ($catalogo as $nodo) {
            if ($nodo['tipo'] !== 'sintoma' || (isset($nodo['activo']) && !$nodo['activo'])) {
                continue;
            }
            $opcion = ['codigo' => $nodo['codigo'], 'etiqueta' => $nodo['etiqueta']];
            if ($nodo['codigo'] === 'sintoma.otro_no_se') {
                $otro = $opcion + ['coincidencia' => 'reserva', 'similitud' => 0.0];
                continue;
            }
            $especies = $nodo['especies'] ?? [];
            if ($especie !== null && $especies !== [] && !in_array($especie, $especies, true) && !($nodo['alarma_universal'] ?? false)) {
                continue;
            }
            $mejor = null;
            foreach ([$nodo['etiqueta'], ...($nodo['sinonimos'] ?? [])] as $texto) {
                $texto = ArchivosConocimiento::normalizarTexto($texto);
                $grado = 3;
                $similitud = 0.0;
                if ($texto === $consulta) {
                    $grado = 0;
                    $similitud = 1.0;
                } elseif ($consulta === '' || str_starts_with($texto, $consulta)) {
                    $grado = 1;
                    $similitud = 1.0;
                } else {
                    $palabras = explode(' ', $texto);
                    $buscadas = explode(' ', $consulta);
                    if (array_diff($buscadas, $palabras) === []) {
                        $grado = 2;
                        $similitud = 1.0;
                    } elseif (mb_strlen($consulta) >= 3) {
                        foreach ([$texto, ...$palabras] as $alternativa) {
                            $longitud = max(mb_strlen($consulta), mb_strlen($alternativa), 1);
                            $similitud = max($similitud, 1 - self::distancia($consulta, $alternativa) / $longitud);
                        }
                    }
                }
                if ($grado === 3 && $similitud < self::SIMILITUD_MINIMA) {
                    continue;
                }
                if ($mejor === null || [$grado, -$similitud] < [$mejor['grado'], -$mejor['similitud']]) {
                    $mejor = ['grado' => $grado, 'similitud' => $similitud];
                }
            }
            if ($mejor !== null) {
                $resultados[] = $opcion + $mejor + ['coincidencia' => ['exacta', 'prefijo', 'palabras', 'aproximada'][$mejor['grado']]];
            }
        }
        usort($resultados, static fn (array $a, array $b): int => [$a['grado'], -$a['similitud'], $a['codigo']] <=> [$b['grado'], -$b['similitud'], $b['codigo']]);
        $resultados = array_slice($resultados, 0, self::MAX_RESULTADOS - ($otro === null ? 0 : 1));
        foreach ($resultados as &$resultado) {
            unset($resultado['grado']);
        }
        unset($resultado);
        if ($otro !== null) {
            $resultados[] = $otro;
        }
        return $resultados;
    }

    /** Damerau-Levenshtein completo sobre puntos Unicode, incluidas transposiciones. */
    public static function distancia(string $a, string $b): int
    {
        $a = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY);
        $b = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY);
        $m = count($a);
        $n = count($b);
        $infinito = $m + $n;
        $d = [[0 => $infinito]];
        for ($i = 0; $i <= $m; $i++) {
            $d[$i + 1][0] = $infinito;
            $d[$i + 1][1] = $i;
        }
        for ($j = 0; $j <= $n; $j++) {
            $d[0][$j + 1] = $infinito;
            $d[1][$j + 1] = $j;
        }
        $ultimas = [];
        for ($i = 1; $i <= $m; $i++) {
            $coincidente = 0;
            for ($j = 1; $j <= $n; $j++) {
                $iAnterior = $ultimas[$b[$j - 1]] ?? 0;
                $jAnterior = $coincidente;
                $coste = 1;
                if ($a[$i - 1] === $b[$j - 1]) {
                    $coste = 0;
                    $coincidente = $j;
                }
                $d[$i + 1][$j + 1] = min($d[$i][$j] + $coste, $d[$i + 1][$j] + 1, $d[$i][$j + 1] + 1, $d[$iAnterior][$jAnterior] + ($i - $iAnterior - 1) + 1 + ($j - $jAnterior - 1));
            }
            $ultimas[$a[$i - 1]] = $i;
        }
        return $d[$m + 1][$n + 1];
    }
}
