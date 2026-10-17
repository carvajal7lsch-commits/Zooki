<?php
require_once __DIR__ . '/../helpers/ArchivosConocimiento.php';

/** Proceso del sistema: solo conocimiento global, excepción explícita RN-212. */
final class CargadorConocimiento
{
    private const TABLAS = ['nodos' => 'grafo_nodos', 'aristas' => 'grafo_aristas', 'sinonimos' => 'grafo_sinonimos', 'reglas' => 'grafo_reglas'];
    private const IDS = ['nodos' => 'id_nodo', 'aristas' => 'id_arista', 'sinonimos' => 'id_sinonimo', 'reglas' => 'id_regla'];
    private const CANDADO = 'zooki_conocimiento';

    public function __construct(private PDO $db)
    {
    }

    public function cargar(string $carpeta, bool $soloRevisar = false): array
    {
        $lectura = ArchivosConocimiento::leer($carpeta);
        if ($this->db->inTransaction()) {
            throw new RuntimeException('El cargador necesita su propia transacción.');
        }
        $esMysql = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if ($esMysql && (int) $this->db->query("SELECT GET_LOCK('" . self::CANDADO . "', 10)")->fetchColumn() !== 1) {
            throw new RuntimeException('Otro proceso está cargando el conocimiento.');
        }
        try {
            $this->db->beginTransaction();
            $actual = [];
            foreach (self::TABLAS as $grupo => $tabla) {
                // Indexar por código; el primer campo no siempre es el ID en una instalación migrada.
                $consulta = $this->db->query('SELECT * FROM ' . $tabla);
                $actual[$grupo] = [];
                foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $fila) {
                    $actual[$grupo][$fila['codigo']] = $fila;
                }
            }
            $preparados = $this->preparar($lectura['datos'], $lectura['version']);
            $resumen = ['version' => $lectura['version'], 'altas' => 0, 'cambios' => 0, 'retiros' => 0, 'solo_revisar' => $soloRevisar];
            $ids = [];
            foreach (self::TABLAS as $grupo => $tabla) {
                foreach ($preparados[$grupo] as $fila) {
                    if ($grupo === 'aristas') {
                        $fila['id_origen'] = $ids[$fila['id_origen']];
                        $fila['id_destino'] = $ids[$fila['id_destino']];
                    } elseif ($grupo === 'sinonimos') {
                        $fila['id_nodo'] = $ids[$fila['id_nodo']];
                    }
                    $anterior = $actual[$grupo][$fila['codigo']] ?? null;
                    if ($grupo === 'nodos' && $anterior !== null && $anterior['tipo'] !== $fila['tipo']) {
                        throw new InvalidArgumentException('Un código estable no cambia de concepto/tipo: ' . $fila['codigo']);
                    }
                    if ($grupo === 'nodos' && $anterior !== null && (($anterior['id_especie'] ?? null) != $fila['id_especie'] || ($anterior['id_raza'] ?? null) != $fila['id_raza'])) {
                        throw new InvalidArgumentException('Un código estable no cambia de identidad taxonómica: ' . $fila['codigo']);
                    }
                    $cambio = $anterior === null || !$this->coincide($fila, $anterior);
                    if ($anterior === null) {
                        $resumen['altas']++;
                    } elseif ($cambio) {
                        $resumen['cambios']++;
                    }
                    $id = $anterior[self::IDS[$grupo]] ?? null;
                    if (!$soloRevisar && $cambio) {
                        $id = $this->guardar($tabla, self::IDS[$grupo], $fila, $id);
                    }
                    if ($grupo === 'nodos') {
                        // En simulación basta una identidad simbólica para calcular el diff de aristas.
                        $ids[$fila['codigo']] = $id ?? ('nuevo:' . $fila['codigo']);
                    }
                }
                $codigos = array_column($preparados[$grupo], 'codigo');
                foreach ($actual[$grupo] as $codigo => $fila) {
                    if (!in_array($codigo, $codigos, true) && (int) $fila['activo'] === 1) {
                        $resumen['retiros']++;
                        if (!$soloRevisar) {
                            $this->db->prepare('UPDATE ' . $tabla . ' SET activo = 0, version_conocimiento = ? WHERE codigo = ?')->execute([$lectura['version'], $codigo]);
                        }
                    }
                }
            }
            $ultima = $this->db->query("SELECT datos_nuevos FROM auditoria_sistema WHERE tabla_afectada = 'grafo_nodos' AND descripcion = 'Carga de conocimiento global (RN-212)' ORDER BY id_auditoria DESC LIMIT 1")->fetchColumn();
            $versionAnterior = $ultima === false ? null : (json_decode($ultima, true)['version'] ?? null);
            if (!$soloRevisar && ($versionAnterior !== $lectura['version'] || $resumen['altas'] + $resumen['cambios'] + $resumen['retiros'] > 0)) {
                $this->db->prepare("INSERT INTO auditoria_sistema (id_clinica, id_usuario, accion, tabla_afectada, registro_id, datos_nuevos, descripcion) VALUES (NULL, NULL, 'OTHER', 'grafo_nodos', ?, ?, 'Carga de conocimiento global (RN-212)')")
                    ->execute(['conjunto', self::json($resumen)]);
            }
            if ($soloRevisar) {
                $this->db->rollBack();
            } else {
                $this->db->commit();
            }
            return $resumen;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        } finally {
            if ($esMysql) {
                $this->db->query("SELECT RELEASE_LOCK('" . self::CANDADO . "')");
            }
        }
    }

    private function preparar(array $datos, string $version): array
    {
        $preparados = ['nodos' => [], 'aristas' => [], 'sinonimos' => [], 'reglas' => []];
        $fuentes = array_column($datos['fuentes'], null, 'codigo');
        foreach (self::TABLAS as $grupo => $tabla) {
            foreach ($datos[$grupo] as $elemento) {
                $evidencias = [];
                foreach ($elemento['fuentes'] as $referencia) {
                    $evidencias[] = [...$fuentes[$referencia['codigo']], ...$referencia];
                }
                $fila = [
                    'codigo' => $elemento['codigo'],
                    'activo' => 1,
                    'version_conocimiento' => $version,
                    'fuentes' => self::json($evidencias),
                    'estado_validacion' => $elemento['estado_validacion'],
                ];
                if ($grupo === 'nodos') {
                    $fila += [
                        'tipo' => $elemento['tipo'],
                        'etiqueta' => $elemento['etiqueta'],
                        'nivel_triage' => $elemento['nivel_triage'] ?? null,
                        'alarma_universal' => (int) ($elemento['alarma_universal'] ?? false),
                        'moderado' => (int) ($elemento['moderado'] ?? false),
                        'metadatos' => isset($elemento['metadatos']) ? self::json((object) $elemento['metadatos']) : null,
                        ...$this->taxonomia($elemento),
                    ];
                } elseif ($grupo === 'aristas') {
                    $fila += [
                        'id_origen' => $elemento['origen'],
                        'id_destino' => $elemento['destino'],
                        'tipo_relacion' => $elemento['tipo_relacion'],
                        'signo' => $elemento['signo'],
                        'peso' => $elemento['peso'],
                        'efecto' => $elemento['efecto'],
                        'condiciones' => isset($elemento['condiciones']) ? self::json($elemento['condiciones']) : null,
                    ];
                } elseif ($grupo === 'sinonimos') {
                    $fila += ['id_nodo' => $elemento['nodo'], 'texto' => $elemento['texto'], 'normalizado' => ArchivosConocimiento::normalizarTexto($elemento['texto'])];
                } else {
                    $fila += ['motor' => $elemento['motor'], 'condiciones' => self::json($elemento['condiciones']), 'resultado' => self::json($elemento['resultado'])];
                }
                $preparados[$grupo][] = $fila;
            }
        }
        return $preparados;
    }

    /** Nunca da de alta taxonomía: valida la pareja declarada contra el catálogo real. */
    private function taxonomia(array $nodo): array
    {
        $ids = ['id_especie' => null, 'id_raza' => null];
        if (!isset($nodo['taxonomia'])) {
            return $ids;
        }
        $consulta = $this->db->prepare('SELECT id_especie FROM especies WHERE nombre_especie = ?');
        $consulta->execute([$nodo['taxonomia']['especie']]);
        $especie = $consulta->fetchColumn();
        if ($especie === false) {
            throw new InvalidArgumentException('Especie no encontrada en el catálogo.');
        }
        if ($nodo['tipo'] === 'especie') {
            $ids['id_especie'] = (int) $especie;
        } else {
            $consulta = $this->db->prepare('SELECT id_raza FROM razas WHERE id_especie = ? AND nombre_raza = ?');
            $consulta->execute([$especie, $nodo['taxonomia']['raza']]);
            $raza = $consulta->fetchColumn();
            if ($raza === false) {
                throw new InvalidArgumentException('La raza no pertenece a la especie declarada.');
            }
            $ids['id_raza'] = (int) $raza;
        }
        return $ids;
    }

    private function coincide(array $nueva, array $anterior): bool
    {
        foreach ($nueva as $columna => $valor) {
            $previo = $anterior[$columna];
            if (in_array($columna, ['fuentes', 'metadatos', 'condiciones', 'resultado'], true) && $valor !== null && $previo !== null) {
                if (json_decode($valor, true) != json_decode($previo, true)) {
                    return false;
                }
            } elseif (($valor === null) !== ($previo === null) || (string) $valor !== (string) $previo) {
                if (!($columna === 'peso' && (float) $valor === (float) $previo)) {
                    return false;
                }
            }
        }
        return true;
    }

    private function guardar(string $tabla, string $columnaId, array $fila, mixed $id): int
    {
        // Nombres SQL definidos por la clase, nunca por el manifiesto ni la petición.
        $columnas = array_keys($fila);
        if ($id === null) {
            $sql = 'INSERT INTO ' . $tabla . ' (' . implode(', ', $columnas) . ') VALUES (' . implode(', ', array_fill(0, count($columnas), '?')) . ')';
            $this->db->prepare($sql)->execute(array_values($fila));
            return (int) $this->db->lastInsertId();
        }
        $sql = 'UPDATE ' . $tabla . ' SET ' . implode(', ', array_map(static fn (string $columna): string => $columna . ' = ?', $columnas)) . ' WHERE ' . $columnaId . ' = ?';
        $this->db->prepare($sql)->execute([...array_values($fila), $id]);
        return (int) $id;
    }

    private static function json(mixed $valor): string
    {
        return json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
