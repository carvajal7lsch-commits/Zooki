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
    private const RELLENO = ['esta', 'estan', 'tiene', 'tienen', 'mucho', 'mucha', 'muy', 'le', 'se', 'lo', 'la', 'las', 'los', 'el', 'mi', 'un', 'una', 'como', 'nada', 'que', 'de', 'del', 'con', 'al', 'me', 'ya', 'y', 'a', 'en', 'por', 'para', 'hace', 'todo', 'poco', 'bastante', 'harto', 'perro', 'perrito', 'gato', 'gatito', 'mascota'];
    private const INGESTA = ['comio', 'tomo', 'di', 'dio', 'trago'];

    public static function buscar(string $consulta, array $catalogo, ?string $especie = null, array $tipos = ['sintoma']): array
    {
        $consulta = ArchivosConocimiento::normalizarTexto($consulta);
        $resultados = [];
        $otro = null;
        foreach ($catalogo as $nodo) {
            if (!in_array($nodo['tipo'], $tipos, true) || (isset($nodo['activo']) && !$nodo['activo'])) {
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
            $exposicion = $nodo['tipo'] !== 'sintoma';
            $buscadas = self::palabras($consulta, $exposicion);
            foreach ([$nodo['etiqueta'], ...($nodo['sinonimos'] ?? [])] as $texto) {
                $texto = ArchivosConocimiento::normalizarTexto($texto);
                $palabras = self::palabras($texto, $exposicion);
                $similitudes = [];
                foreach ($buscadas as $buscada) {
                    $similitud = 0.0;
                    foreach ($palabras as $palabra) {
                        $prefijo = str_starts_with($palabra, $buscada) || (mb_strlen($palabra) >= 4 && str_starts_with($buscada, $palabra));
                        $similitud = max($similitud, $prefijo ? 1.0 : 1 - self::distancia($buscada, $palabra) / max(mb_strlen($buscada), mb_strlen($palabra), 1));
                    }
                    $similitudes[] = $similitud;
                }
                if ($buscadas === [] && $consulta !== '') {
                    continue;
                }
                if ($similitudes !== [] && min($similitudes) < self::SIMILITUD_MINIMA) {
                    continue;
                }
                $similitud = $similitudes === [] ? 1.0 : array_sum($similitudes) / count($similitudes);
                $grado = $texto === $consulta ? 0 : (str_starts_with($texto, $consulta) ? 1 : ($similitud === 1.0 ? 2 : 3));
                if ($mejor === null || [-$similitud, $grado] < [-$mejor['similitud'], $mejor['grado']]) {
                    $mejor = ['grado' => $grado, 'similitud' => $similitud];
                }
            }
            if ($mejor !== null) {
                $resultados[] = $opcion + $mejor + ['coincidencia' => ['exacta', 'prefijo', 'palabras', 'aproximada'][$mejor['grado']]];
            }
        }
        usort($resultados, static fn (array $a, array $b): int => [-$a['similitud'], $a['grado'], $a['codigo']] <=> [-$b['similitud'], $b['grado'], $b['codigo']]);
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

    private static function palabras(string $texto, bool $exposicion): array
    {
        return array_values(array_diff(explode(' ', $texto), self::RELLENO, $exposicion ? self::INGESTA : [], ['']));
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
