<?php

/** RN-212 / RN-424: valida datos declarativos; no ejecuta reglas ni consulta la base. */
final class ValidadorConocimiento
{
    public const ARCHIVOS = ['fuentes.json', 'nodos.json', 'aristas.json', 'sinonimos.json', 'reglas.json'];
    public const PREDICADOS = ['sintoma_marcado', 'especie', 'raza', 'edad_meses', 'sexo', 'esterilizado', 'alerta_activa', 'inicio_episodio', 'minutos_desde_exposicion', 'exposicion', 'vacunacion', 'dias_desde_celo'];
    public const MOTORES = ['diagnosticos', 'prescripcion', 'triage'];
    public const NIVELES = ['rojo', 'naranja', 'amarillo', 'verde'];
    private const TIPOS = ['sintoma', 'diagnostico', 'farmaco', 'especie', 'raza', 'condicion', 'sustancia'];
    private const COMUNES = ['codigo', 'fuentes', 'estado_validacion', 'validacion'];

    public static function validar(array $conjunto): void
    {
        self::ubicado('manifiesto.json', '[conjunto]', static function () use ($conjunto): void {
            self::validarConjunto($conjunto);
        });
    }

    private static function validarConjunto(array $conjunto): void
    {
        self::campos($conjunto, ['manifiesto', 'fuentes', 'nodos', 'aristas', 'sinonimos', 'reglas'], ['manifiesto', 'fuentes', 'nodos', 'aristas', 'sinonimos', 'reglas']);
        $manifiesto = $conjunto['manifiesto'];
        self::campos($manifiesto, ['formato', 'version', 'archivos', 'limitaciones'], ['formato', 'version', 'archivos', 'limitaciones']);
        self::exigir($manifiesto['formato'] === 1, 'Formato de conocimiento no compatible.');
        self::texto($manifiesto['version'], 50);
        self::texto($manifiesto['limitaciones'], 2000);
        self::lista($manifiesto['archivos']);
        $archivos = $manifiesto['archivos'];
        sort($archivos);
        $esperados = self::ARCHIVOS;
        sort($esperados);
        self::exigir($archivos === $esperados, 'El manifiesto debe listar todos los archivos esperados, sin extras ni hashes.');
        $fuentes = self::indexar($conjunto['fuentes'], 'fuentes.json');
        foreach ($fuentes as $fuente) {
            self::ubicado('fuentes.json', $fuente['codigo'], static function () use ($fuente): void {
                self::campos($fuente, ['codigo', 'entidad', 'titulo', 'url', 'fecha_consulta', 'seccion', 'fecha_publicacion'], ['codigo', 'entidad', 'titulo', 'url', 'fecha_consulta', 'seccion']);
                foreach (['entidad', 'titulo', 'url', 'seccion'] as $campo) {
                    self::texto($fuente[$campo], 2000, $campo);
                }
                self::exigir(filter_var($fuente['url'], FILTER_VALIDATE_URL) !== false && parse_url($fuente['url'], PHP_URL_SCHEME) === 'https', 'La fuente necesita una URL HTTPS específica.');
                self::fecha($fuente['fecha_consulta'], 'fecha_consulta');
                if (isset($fuente['fecha_publicacion'])) {
                    self::fecha($fuente['fecha_publicacion'], 'fecha_publicacion');
                }
            });
        }
        $nodos = self::indexar($conjunto['nodos'], 'nodos.json');
        foreach ($nodos as $nodo) {
            self::ubicado('nodos.json', $nodo['codigo'], static function () use ($nodo, $fuentes): void {
                self::elemento($nodo, ['tipo', 'etiqueta', 'nivel_triage', 'alarma_universal', 'moderado', 'metadatos', 'taxonomia'], ['tipo', 'etiqueta'], $fuentes);
                self::opcion($nodo['tipo'], self::TIPOS);
                self::texto($nodo['etiqueta'], 150, 'etiqueta');
                $esSintoma = $nodo['tipo'] === 'sintoma';
                if (isset($nodo['nivel_triage'])) {
                    self::exigir($esSintoma, 'Solo un síntoma tiene nivel de triage.');
                    self::opcion($nodo['nivel_triage'], self::NIVELES);
                }
                foreach (['alarma_universal', 'moderado'] as $campo) {
                    if (isset($nodo[$campo])) {
                        self::exigir(is_bool($nodo[$campo]) && ($esSintoma || !$nodo[$campo]), 'Indicador clínico inválido.');
                    }
                }
                if ($nodo['alarma_universal'] ?? false) {
                    self::exigir(($nodo['nivel_triage'] ?? null) === 'rojo', 'Una alarma universal siempre es roja.');
                }
                self::metadatos($nodo['metadatos'] ?? []);
                if (in_array($nodo['tipo'], ['especie', 'raza'], true)) {
                    $taxonomia = $nodo['taxonomia'] ?? null;
                    $campos = $nodo['tipo'] === 'raza' ? ['especie', 'raza'] : ['especie'];
                    self::campos($taxonomia, $campos, $campos);
                    foreach ($taxonomia as $nombre) {
                        self::texto($nombre, 50);
                    }
                } else {
                    self::exigir(!isset($nodo['taxonomia']), 'Solo especie y raza enlazan taxonomía.');
                }
            });
        }
        $aristas = self::indexar($conjunto['aristas'], 'aristas.json');
        foreach ($aristas as $arista) {
            self::ubicado('aristas.json', $arista['codigo'], static function () use ($arista, $fuentes, $nodos): void {
                self::elemento($arista, ['origen', 'destino', 'tipo_relacion', 'signo', 'peso', 'efecto', 'condiciones'], ['origen', 'destino', 'tipo_relacion', 'signo', 'peso', 'efecto'], $fuentes);
                self::referencia($arista['origen'], $nodos, 'origen');
                self::referencia($arista['destino'], $nodos, 'destino');
                self::opcion($arista['tipo_relacion'], ['sugiere', 'trata', 'toxico_para', 'alergia', 'interactua_con', 'contraindica', 'asociado']);
                self::opcion($arista['signo'], ['positivo', 'negativo']);
                self::peso($arista['peso'], 0, 1);
                self::opcion($arista['efecto'], ['evidencia', 'bloqueo', 'advertencia']);
                if ($arista['efecto'] === 'bloqueo') {
                    self::exigir(in_array($arista['tipo_relacion'], ['toxico_para', 'alergia'], true) && $arista['signo'] === 'negativo', 'Bloqueo solo por toxicidad o alergia explícita.');
                    self::fundamentoClinico($arista);
                    $tiposOrigen = $arista['tipo_relacion'] === 'toxico_para' ? ['especie', 'raza'] : ['condicion'];
                    self::exigir(in_array($nodos[$arista['origen']]['tipo'], $tiposOrigen, true) && in_array($nodos[$arista['destino']]['tipo'], ['farmaco', 'sustancia'], true), 'Los extremos del bloqueo no expresan toxicidad o alergia.');
                }
                if ($arista['tipo_relacion'] === 'interactua_con') {
                    self::exigir($arista['efecto'] === 'advertencia', 'Una interacción advierte, no bloquea.');
                    self::exigir($nodos[$arista['origen']]['tipo'] === 'farmaco' && $nodos[$arista['destino']]['tipo'] === 'farmaco', 'La interacción relaciona fármacos.');
                }
                if (isset($arista['condiciones'])) {
                    self::condiciones($arista['condiciones'], $nodos);
                }
            });
        }
        foreach (self::indexar($conjunto['sinonimos'], 'sinonimos.json') as $sinonimo) {
            self::ubicado('sinonimos.json', $sinonimo['codigo'], static function () use ($sinonimo, $fuentes, $nodos): void {
                self::elemento($sinonimo, ['nodo', 'texto'], ['nodo', 'texto'], $fuentes);
                self::referencia($sinonimo['nodo'], $nodos, 'nodo');
                self::texto($sinonimo['texto'], 150, 'texto');
            });
        }
        foreach (self::indexar($conjunto['reglas'], 'reglas.json') as $regla) {
            self::ubicado('reglas.json', $regla['codigo'], static function () use ($regla, $fuentes, $nodos): void {
                self::elemento($regla, ['motor', 'condiciones', 'resultado'], ['motor', 'condiciones', 'resultado'], $fuentes);
                self::exigir(!preg_match('/\bT-\d{2}\b/i', json_encode($regla)), 'Las reglas no nombran casos de la batería.');
                self::opcion($regla['motor'], self::MOTORES);
                self::condiciones($regla['condiciones'], $nodos);
                self::resultado($regla['resultado'], $regla['motor'], $nodos);
                if (($regla['resultado']['efecto'] ?? null) === 'bloqueo') {
                    self::fundamentoClinico($regla);
                }
            });
        }
    }

    private static function elemento(array $elemento, array $extras, array $requeridos, array $fuentes): void
    {
        self::campos($elemento, [...self::COMUNES, ...$extras], ['codigo', 'fuentes', 'estado_validacion', ...$requeridos]);
        self::opcion($elemento['estado_validacion'], ['pendiente', 'validado', 'rechazado']);
        self::lista($elemento['fuentes']);
        self::exigir($elemento['fuentes'] !== [], 'Campo fuentes: todo elemento necesita al menos una fuente.');
        foreach ($elemento['fuentes'] as $referencia) {
            self::campos($referencia, ['codigo', 'localizador', 'fundamento'], ['codigo', 'localizador', 'fundamento']);
            self::referencia($referencia['codigo'], $fuentes);
            self::texto($referencia['localizador'], 1000);
            self::opcion($referencia['fundamento'], ['clinico', 'politica', 'heuristico']);
        }
        if (isset($elemento['validacion'])) {
            self::campos($elemento['validacion'], ['quien', 'fecha'], ['quien', 'fecha']);
            self::texto($elemento['validacion']['quien'], 150);
            self::fecha($elemento['validacion']['fecha']);
        }
        self::exigir($elemento['estado_validacion'] !== 'validado' || isset($elemento['validacion']), 'La validación se acredita en el archivo.');
    }

    /** Dos grupos como máximo; las hojas no aumentan la profundidad de grupos. */
    private static function condiciones(mixed $condicion, array $nodos, int $profundidad = 0): void
    {
        self::exigir(is_array($condicion) && !array_is_list($condicion), 'Condición no válida.');
        if (isset($condicion['todos']) || isset($condicion['alguno'])) {
            self::exigir(count($condicion) === 1 && $profundidad < 2, 'Anidamiento máximo de dos niveles.');
            $hijos = $condicion['todos'] ?? $condicion['alguno'];
            self::lista($hijos);
            self::exigir($hijos !== [], 'Grupo de condiciones vacío.');
            foreach ($hijos as $hijo) {
                self::condiciones($hijo, $nodos, $profundidad + 1);
            }
            return;
        }
        $predicado = $condicion['predicado'] ?? null;
        self::opcion($predicado, self::PREDICADOS);
        if (in_array($predicado, ['sintoma_marcado', 'especie', 'alerta_activa', 'exposicion'], true)) {
            self::campos($condicion, ['predicado', 'codigo'], ['predicado', 'codigo']);
            self::referencia($condicion['codigo'], $nodos);
            $tipos = ['sintoma_marcado' => ['sintoma'], 'especie' => ['especie'], 'alerta_activa' => ['farmaco', 'condicion'], 'exposicion' => ['sustancia', 'farmaco']];
            self::exigir(in_array($nodos[$condicion['codigo']]['tipo'], $tipos[$predicado], true), 'Tipo de referencia no válido para el predicado.');
        } elseif ($predicado === 'raza') {
            self::campos($condicion, ['predicado', 'atributo', 'valor'], ['predicado', 'atributo', 'valor']);
            self::opcion($condicion['atributo'], ['tamanio', 'braquicefalo']);
            self::metadatos([$condicion['atributo'] => $condicion['valor']]);
        } elseif (in_array($predicado, ['edad_meses', 'inicio_episodio', 'minutos_desde_exposicion', 'dias_desde_celo'], true)) {
            $campos = ['predicado', 'minimo', 'maximo', 'incluye_minimo', 'incluye_maximo'];
            if (!in_array($predicado, ['edad_meses', 'dias_desde_celo'], true)) {
                $campos[] = 'codigo';
            }
            self::campos($condicion, $campos, in_array($predicado, ['edad_meses', 'dias_desde_celo'], true) ? ['predicado'] : ['predicado', 'codigo']);
            if (isset($condicion['codigo'])) {
                self::referencia($condicion['codigo'], $nodos);
                $tipo = $predicado === 'inicio_episodio' ? ['sintoma'] : ['sustancia', 'farmaco'];
                self::exigir(in_array($nodos[$condicion['codigo']]['tipo'], $tipo, true), 'El tiempo debe identificar el evento correcto.');
            }
            self::exigir(isset($condicion['minimo']) || isset($condicion['maximo']), 'Intervalo sin límites.');
            foreach (['minimo', 'maximo'] as $limite) {
                if (isset($condicion[$limite])) {
                    self::peso($condicion[$limite], 0, PHP_INT_MAX);
                    self::exigir(isset($condicion['incluye_' . $limite]) && is_bool($condicion['incluye_' . $limite]), 'El intervalo indica si incluye su límite.');
                } else {
                    self::exigir(!isset($condicion['incluye_' . $limite]), 'Inclusión sin límite.');
                }
            }
            self::exigir(!isset($condicion['minimo'], $condicion['maximo']) || $condicion['minimo'] <= $condicion['maximo'], 'Intervalo invertido.');
        } else {
            self::campos($condicion, ['predicado', 'valor'], ['predicado', 'valor']);
            if ($predicado === 'vacunacion') {
                self::opcion($condicion['valor'], ['completa', 'incompleta', 'desconocida']);
            } elseif ($predicado === 'sexo') {
                self::opcion($condicion['valor'], ['Macho', 'Hembra', 'Desconocido']);
            } else {
                self::exigir(is_bool($condicion['valor']), 'Esterilización debe ser booleana.');
            }
        }
    }

    private static function resultado(mixed $resultado, string $motor, array $nodos): void
    {
        self::campos($resultado, ['nivel', 'efecto', 'motivo', 'revision', 'revision_inmediata', 'contagio', 'diagnostico', 'peso'], []);
        self::exigir($resultado !== [], 'Resultado vacío.');
        if ($motor === 'prescripcion') {
            self::exigir(isset($resultado['efecto'], $resultado['motivo']), 'Prescripción necesita efecto y motivo.');
        } elseif ($motor === 'diagnosticos') {
            self::exigir(isset($resultado['diagnostico'], $resultado['peso']), 'Ranking necesita diagnóstico y peso.');
        } else {
            self::exigir(isset($resultado['nivel']) || isset($resultado['revision']) || isset($resultado['contagio']), 'Resultado de triage sin nivel ni indicador.');
        }
        if (isset($resultado['nivel'])) {
            self::exigir($motor === 'triage', 'Solo triage produce un nivel.');
            self::opcion($resultado['nivel'], self::NIVELES);
        }
        foreach (['revision', 'revision_inmediata', 'contagio'] as $campo) {
            if (isset($resultado[$campo])) {
                self::exigir($motor === 'triage' && is_bool($resultado[$campo]), 'Indicador de triage no válido.');
            }
        }
        if (isset($resultado['diagnostico'])) {
            self::exigir($motor === 'diagnosticos', 'Diagnóstico fuera de su motor.');
            self::referencia($resultado['diagnostico'], $nodos);
            self::exigir($nodos[$resultado['diagnostico']]['tipo'] === 'diagnostico', 'Referencia de diagnóstico inválida.');
        }
        if (isset($resultado['peso'])) {
            self::exigir($motor === 'diagnosticos', 'Peso fuera del ranking.');
            self::peso($resultado['peso'], -1, 1);
        }
        if (isset($resultado['efecto'])) {
            self::opcion($resultado['efecto'], ['evidencia', 'bloqueo', 'advertencia']);
        }
        if (isset($resultado['motivo'])) {
            self::opcion($resultado['motivo'], ['toxicidad', 'alergia', 'interaccion', 'apoyo', 'reserva']);
        }
        if (($resultado['efecto'] ?? null) === 'bloqueo') {
            self::exigir($motor === 'prescripcion' && in_array($resultado['motivo'] ?? null, ['toxicidad', 'alergia'], true), 'Bloqueo solo por toxicidad o alergia permitido.');
        }
    }

    private static function metadatos(mixed $metadatos): void
    {
        self::campos($metadatos, ['cobertura', 'tamanio', 'braquicefalo'], []);
        if (isset($metadatos['cobertura'])) {
            self::campos($metadatos['cobertura'], self::MOTORES, []);
            foreach ($metadatos['cobertura'] as $estado) {
                self::opcion($estado, ['sin_cobertura', 'parcial', 'completa']);
            }
        }
        if (isset($metadatos['tamanio'])) {
            self::opcion($metadatos['tamanio'], ['pequeno', 'mediano', 'grande', 'gigante']);
        }
        if (isset($metadatos['braquicefalo'])) {
            self::exigir(is_bool($metadatos['braquicefalo']), 'Braquicefalia debe ser booleana.');
        }
    }

    private static function fundamentoClinico(array $elemento): void
    {
        self::exigir(in_array('clinico', array_column($elemento['fuentes'], 'fundamento'), true), 'Un bloqueo necesita fuente clínica explícita.');
    }

    private static function indexar(mixed $elementos, string $archivo): array
    {
        self::ubicado($archivo, '[lista]', static function () use ($elementos): void {
            self::lista($elementos);
        });
        $indice = [];
        foreach ($elementos as $posicion => $elemento) {
            $codigo = is_array($elemento) && is_string($elemento['codigo'] ?? null) ? $elemento['codigo'] : '[sin codigo #' . $posicion . ']';
            self::ubicado($archivo, $codigo, static function () use ($elemento, $indice): void {
                self::exigir(is_array($elemento), 'Elemento no válido.');
                $codigo = $elemento['codigo'] ?? null;
                self::exigir(is_string($codigo) && strlen($codigo) <= 100 && preg_match('/^[a-z][a-z0-9_.-]*$/D', $codigo), 'Campo codigo: código estable no válido.');
                self::exigir(!str_starts_with($codigo, 'legado.'), 'Campo codigo: prefijo legado reservado.');
                self::exigir(!isset($indice[$codigo]), 'Campo codigo: código repetido.');
            });
            $indice[$codigo] = $elemento;
        }
        return $indice;
    }

    private static function campos(mixed $objeto, array $permitidos, array $requeridos): void
    {
        self::exigir(is_array($objeto), 'Se esperaba un objeto.');
        self::exigir(array_diff(array_keys($objeto), $permitidos) === [], 'Campo desconocido: ' . implode(', ', array_diff(array_keys($objeto), $permitidos)));
        self::exigir(array_diff($requeridos, array_keys($objeto)) === [], 'Falta campo obligatorio: ' . implode(', ', array_diff($requeridos, array_keys($objeto))));
    }

    private static function referencia(mixed $codigo, array $indice, string $campo = 'codigo'): void
    {
        self::exigir(is_string($codigo) && isset($indice[$codigo]), 'Campo ' . $campo . ': referencia a código inexistente (' . (is_scalar($codigo) ? (string) $codigo : 'tipo inválido') . ').');
    }

    private static function lista(mixed $valor): void
    {
        self::exigir(is_array($valor) && array_is_list($valor), 'Se esperaba una lista.');
    }

    private static function texto(mixed $valor, int $maximo, string $campo = 'texto'): void
    {
        self::exigir(is_string($valor) && trim($valor) === $valor && $valor !== '' && mb_strlen($valor) <= $maximo, 'Campo ' . $campo . ': texto vacío, con espacios sobrantes o demasiado largo.');
    }

    private static function opcion(mixed $valor, array $opciones): void
    {
        self::exigir(in_array($valor, $opciones, true), 'Valor o predicado desconocido.');
    }

    private static function fecha(mixed $valor, string $campo = 'fecha'): void
    {
        self::exigir(is_string($valor), 'Campo ' . $campo . ': fecha no válida.');
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        self::exigir($fecha !== false && $fecha->format('Y-m-d') === $valor, 'Campo ' . $campo . ': fecha no válida.');
    }

    private static function peso(mixed $valor, float $minimo, float $maximo): void
    {
        self::exigir((is_int($valor) || is_float($valor)) && is_finite((float) $valor) && $valor >= $minimo && $valor <= $maximo, 'Número fuera de rango.');
    }

    private static function ubicado(string $archivo, string $codigo, callable $validar): void
    {
        try {
            $validar();
        } catch (InvalidArgumentException | TypeError | ValueError $error) {
            // La ubicación más específica se conserva al salir del conjunto completo.
            if (preg_match('/^[a-z]+\.json \[/', $error->getMessage())) {
                throw $error;
            }
            throw new InvalidArgumentException($archivo . ' [' . $codigo . ']: ' . $error->getMessage(), 0, $error);
        }
    }

    private static function exigir(bool $condicion, string $mensaje): void
    {
        if (!$condicion) {
            throw new InvalidArgumentException($mensaje);
        }
    }
}
