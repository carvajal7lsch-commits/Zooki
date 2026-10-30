<?php

/**
 * HU-2.12 — Arma la respuesta del mapa del conocimiento a partir de filas ya leídas.
 * Solo conocimiento global (RN-212): ninguna clave de pacientes ni IDs internos (RE-2.12.5).
 * Una regla no es una arista: se asocia a los nodos que nombra (RE-2.12.4).
 */
final class MapaConocimiento
{
    public const MOTORES = ['diagnosticos', 'prescripcion', 'triage'];
    private const CAMPOS_FUENTE = ['entidad', 'titulo', 'url', 'localizador', 'fundamento', 'fecha_consulta'];

    /**
     * @param array $nodos Filas de grafo_nodos activas, con `especie_raza` (id_especie de la raza) cuando aplica.
     * @param array $aristas Filas activas con `origen` y `destino` ya resueltos a código.
     * @param array $sinonimos Filas activas con `nodo` (código) y `texto`.
     * @param array $reglas Filas activas de grafo_reglas.
     */
    public static function construir(array $nodos, array $aristas, array $sinonimos, array $reglas, ?string $version, string $limitaciones): array
    {
        $especiePorId = [];
        foreach ($nodos as $nodo) {
            if ($nodo['tipo'] === 'especie' && $nodo['id_especie'] !== null) {
                $especiePorId[(int) $nodo['id_especie']] = $nodo['codigo'];
            }
        }
        $sinonimosPorNodo = [];
        foreach ($sinonimos as $sinonimo) {
            $sinonimosPorNodo[$sinonimo['nodo']][] = $sinonimo['texto'];
        }
        $salidaReglas = [];
        $reglasPorNodo = [];
        foreach ($reglas as $regla) {
            $condiciones = self::json($regla['condiciones']);
            $resultado = self::json($regla['resultado']);
            $nombrados = self::codigosNombrados($condiciones);
            if (isset($resultado['diagnostico'])) {
                $nombrados[] = $resultado['diagnostico'];
            }
            $nombrados = array_values(array_unique($nombrados));
            foreach ($nombrados as $codigo) {
                $reglasPorNodo[$codigo][] = $regla['codigo'];
            }
            $salidaReglas[] = [
                'codigo' => $regla['codigo'],
                'motor' => $regla['motor'],
                'condiciones' => $condiciones,
                'resultado' => $resultado,
                'nodos' => $nombrados,
                'fuentes' => self::fuentes($regla['fuentes']),
                'estado_validacion' => $regla['estado_validacion'],
            ];
        }
        $salidaNodos = [];
        $cobertura = [];
        $conteoTipos = [];
        foreach ($nodos as $nodo) {
            $metadatos = $nodo['metadatos'] === null ? [] : self::json($nodo['metadatos']);
            $conteoTipos[$nodo['tipo']] = ($conteoTipos[$nodo['tipo']] ?? 0) + 1;
            $salidaNodos[] = [
                'codigo' => $nodo['codigo'],
                'tipo' => $nodo['tipo'],
                'etiqueta' => $nodo['etiqueta'],
                'nivel_triage' => $nodo['nivel_triage'],
                'alarma_universal' => (bool) $nodo['alarma_universal'],
                'moderado' => (bool) $nodo['moderado'],
                'metadatos' => $metadatos,
                'especies' => self::especiesDelNodo($nodo, $especiePorId),
                'sinonimos' => $sinonimosPorNodo[$nodo['codigo']] ?? [],
                'reglas' => $reglasPorNodo[$nodo['codigo']] ?? [],
                'fuentes' => self::fuentes($nodo['fuentes']),
                'estado_validacion' => $nodo['estado_validacion'],
            ];
            if ($nodo['tipo'] === 'especie') {
                $estado = ['codigo' => $nodo['codigo'], 'etiqueta' => $nodo['etiqueta']];
                foreach (self::MOTORES as $motor) {
                    // Sin declaración no hay cobertura: nunca se supone (RE-2.12.6).
                    $estado[$motor] = $metadatos['cobertura'][$motor] ?? 'sin_cobertura';
                }
                $cobertura[] = $estado;
            }
        }
        $salidaAristas = [];
        foreach ($aristas as $arista) {
            $salidaAristas[] = [
                'codigo' => $arista['codigo'],
                'origen' => $arista['origen'],
                'destino' => $arista['destino'],
                'tipo_relacion' => $arista['tipo_relacion'],
                'signo' => $arista['signo'],
                'peso' => (float) $arista['peso'],
                'efecto' => $arista['efecto'],
                'condiciones' => $arista['condiciones'] === null ? null : self::json($arista['condiciones']),
                'pauta' => ($arista['pauta'] ?? null) === null ? null : self::json($arista['pauta']),
                'fuentes' => self::fuentes($arista['fuentes']),
                'estado_validacion' => $arista['estado_validacion'],
            ];
        }
        ksort($conteoTipos);
        return [
            'vacio' => $salidaNodos === [],
            'version' => $version === null ? null : ['hash' => $version, 'corta' => substr($version, 0, 12)],
            'limitaciones' => $limitaciones,
            'conteos' => [
                'nodos' => count($salidaNodos),
                'aristas' => count($salidaAristas),
                'reglas' => count($salidaReglas),
                'sinonimos' => count($sinonimos),
                'por_tipo' => $conteoTipos,
            ],
            'pendientes' => self::pendientes([...$salidaNodos, ...$salidaAristas, ...$salidaReglas]),
            'cobertura' => $cobertura,
            'nodes' => $salidaNodos,
            'edges' => $salidaAristas,
            'reglas' => $salidaReglas,
        ];
    }

    /** Códigos de nodo que nombran las condiciones, a cualquier profundidad. */
    public static function codigosNombrados(mixed $condicion): array
    {
        if (!is_array($condicion)) {
            return [];
        }
        $codigos = [];
        if (isset($condicion['codigo']) && is_string($condicion['codigo'])) {
            $codigos[] = $condicion['codigo'];
        }
        foreach (['todos', 'alguno'] as $grupo) {
            foreach ($condicion[$grupo] ?? [] as $hijo) {
                $codigos = [...$codigos, ...self::codigosNombrados($hijo)];
            }
        }
        return $codigos;
    }

    /** Una especie se filtra por sí misma; una raza, por la especie de su catálogo; lo demás es universal. */
    private static function especiesDelNodo(array $nodo, array $especiePorId): array
    {
        if ($nodo['tipo'] === 'especie') {
            return [$nodo['codigo']];
        }
        if ($nodo['tipo'] === 'raza' && isset($especiePorId[(int) ($nodo['especie_raza'] ?? 0)])) {
            return [$especiePorId[(int) $nodo['especie_raza']]];
        }
        return [];
    }

    /** Solo los campos bibliográficos que se muestran; una URL que no sea https no se ofrece como enlace. */
    private static function fuentes(mixed $fuentes): array
    {
        $salida = [];
        foreach (self::json($fuentes) as $fuente) {
            $fila = [];
            foreach (self::CAMPOS_FUENTE as $campo) {
                $fila[$campo] = isset($fuente[$campo]) ? (string) $fuente[$campo] : null;
            }
            if ($fila['url'] !== null && !str_starts_with($fila['url'], 'https://')) {
                $fila['url'] = null;
            }
            $salida[] = $fila;
        }
        return $salida;
    }

    private static function pendientes(array $elementos): int
    {
        return count(array_filter($elementos, static fn (array $elemento): bool => $elemento['estado_validacion'] !== 'validado'));
    }

    private static function json(mixed $valor): array
    {
        if (is_array($valor)) {
            return $valor;
        }
        $datos = json_decode((string) $valor, true);
        return is_array($datos) ? $datos : [];
    }
}
