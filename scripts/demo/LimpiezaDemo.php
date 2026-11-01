<?php
require_once __DIR__ . '/GuionDemo.php';

/** Excepción CLI del plan: borrado físico solo del conjunto demo, con FK activas y sin perseguir padres globales. */
final class LimpiezaDemo
{
    private const PROTEGIDAS = ['planes', 'roles', 'especies', 'razas', 'colores_base', 'especialidades', 'grafo_nodos', 'grafo_aristas', 'grafo_sinonimos', 'grafo_reglas', 'schema_migraciones'];

    public function __construct(private PDO $db)
    {
    }

    public function filas(string $sql, array $parametros = []): array
    {
        $q = $this->db->prepare($sql);
        $q->execute($parametros);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ambito(bool $bloquear = false): array
    {
        $nits = [];
        foreach (array_keys(GuionDemo::CLINICAS) as $clave) {
            $nits[ValidadorNit::normalizarEntrada(GuionDemo::nit($clave))] = $clave;
        }
        $sufijo = $bloquear ? ' FOR UPDATE' : '';
        $clinicas = $this->filas('SELECT id_clinica,nit,nombre,email_contacto FROM clinicas WHERE REPLACE(nit,\'-\',\'\') IN (' . self::marcas(array_keys($nits)) . ') ORDER BY id_clinica' . $sufijo, array_keys($nits));
        $idsClinicas = [];
        foreach ($clinicas as $clinica) {
            $clave = $nits[ValidadorNit::normalizar($clinica['nit'])];
            if (strcasecmp(trim($clinica['nombre']), 'Zooki') === 0 || strtolower((string) $clinica['email_contacto']) !== GuionDemo::correo($clave . '-clinica')) {
                throw new RuntimeException('Un NIT reservado para la demo pertenece a una clínica no reconocida. No se modifica.');
            }
            $idsClinicas[] = (int) $clinica['id_clinica'];
        }
        $usuarios = $this->filas('SELECT id_usuario,email,es_super_admin FROM usuarios WHERE email LIKE ? ORDER BY id_usuario' . $sufijo, [GuionDemo::PREFIJO_CORREO . '%@gmail.com']);
        foreach ($usuarios as $usuario) {
            if ((int) $usuario['es_super_admin'] === 1 || !preg_match('/^zooki\.vet\+demo-[a-z0-9-]+@gmail\.com$/iD', $usuario['email'])) {
                throw new RuntimeException('Una cuenta del espacio demo no es eliminable o es super-administradora. No se modifica.');
            }
        }
        $idsUsuarios = array_map('intval', array_column($usuarios, 'id_usuario'));
        if ($idsUsuarios !== []) {
            foreach (['usuario_clinica' => 'id_usuario', 'propietario_clinica' => 'id_propietario'] as $tabla => $columna) {
                $vinculos = $this->filas('SELECT id_clinica FROM ' . $tabla . ' WHERE ' . $columna . ' IN (' . self::marcas($idsUsuarios) . ')', $idsUsuarios);
                foreach ($vinculos as $vinculo) {
                    if (!in_array((int) $vinculo['id_clinica'], $idsClinicas, true)) {
                        throw new RuntimeException('Una persona demo está vinculada a una clínica ajena. Se cancela el borrado completo.');
                    }
                }
            }
        }
        $mascotas = $idsUsuarios === [] ? [] : $this->filas('SELECT id_mascota FROM mascotas WHERE id_propietario IN (' . self::marcas($idsUsuarios) . ')', $idsUsuarios);
        $idsMascotas = array_map('intval', array_column($mascotas, 'id_mascota'));
        if ($idsClinicas !== []) {
            $fichas = $this->filas('SELECT id_mascota,id_propietario FROM mascotas WHERE id_clinica_registro IN (' . self::marcas($idsClinicas) . ')', $idsClinicas);
            foreach ($fichas as $ficha) {
                if ($ficha['id_propietario'] !== null && !in_array((int) $ficha['id_propietario'], $idsUsuarios, true)) {
                    throw new RuntimeException('Una ficha registrada en la demo pertenece a una persona ajena. Se conserva todo.');
                }
                $idsMascotas[] = (int) $ficha['id_mascota'];
            }
        }
        $idsMascotas = array_values(array_unique($idsMascotas));
        if ($idsMascotas !== []) {
            $vinculos = $this->filas('SELECT id_clinica FROM mascota_clinica WHERE id_mascota IN (' . self::marcas($idsMascotas) . ')', $idsMascotas);
            foreach ($vinculos as $vinculo) {
                if (!in_array((int) $vinculo['id_clinica'], $idsClinicas, true)) {
                    throw new RuntimeException('Una mascota demo tiene historia o vínculo ajeno. No se borra su ficha global.');
                }
            }
        }
        return ['clinicas' => $idsClinicas, 'usuarios' => $idsUsuarios, 'mascotas' => $idsMascotas];
    }

    public function borrar(): void
    {
        if (!$this->db->inTransaction()) {
            throw new LogicException('La limpieza demo requiere una transacción exterior.');
        }
        $ambito = $this->ambito(true);
        $raices = [];
        foreach (['clinicas' => 'id_clinica', 'usuarios' => 'id_usuario', 'mascotas' => 'id_mascota'] as $tabla => $columna) {
            $raices[$tabla] = array_map(static fn (int $id): array => [$columna => $id], $ambito[$tabla]);
        }
        // RegistroClinica usa un contador por IP; estas cinco IP de documentación son exclusivas de este guion.
        $intentos = $this->filas("SELECT id_intento FROM intentos_login WHERE identificador REGEXP '^alta-clinica:[0-9]{4}-[0-9]{2}-[0-9]{2}:192[.]0[.]2[.]23[1-5]$'");
        $raices['intentos_login'] = $intentos;
        $this->eliminarDependencias($raices, $ambito['clinicas']);
    }

    public function eliminarDependencias(array $raices, array $clinicasPermitidas): void
    {
        if (!$this->db->inTransaction()) {
            throw new LogicException('Se requiere una transacción para eliminar la fase demo.');
        }
        $claves = [];
        foreach ($this->filas("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND CONSTRAINT_NAME='PRIMARY' ORDER BY ORDINAL_POSITION") as $fila) {
            $claves[$fila['TABLE_NAME']][] = $fila['COLUMN_NAME'];
        }
        $fks = [];
        $referencias = $this->filas('SELECT k.TABLE_NAME,k.CONSTRAINT_NAME,k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,c.IS_NULLABLE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=k.TABLE_SCHEMA AND c.TABLE_NAME=k.TABLE_NAME AND c.COLUMN_NAME=k.COLUMN_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.TABLE_NAME,k.CONSTRAINT_NAME,k.ORDINAL_POSITION');
        foreach ($referencias as $referencia) {
            $clave = $referencia['TABLE_NAME'] . '.' . $referencia['CONSTRAINT_NAME'];
            $fks[$clave] ??= ['TABLE_NAME' => $referencia['TABLE_NAME'], 'REFERENCED_TABLE_NAME' => $referencia['REFERENCED_TABLE_NAME'], 'IS_NULLABLE' => 'NO', 'pares' => [], 'anulables' => []];
            $fks[$clave]['pares'][] = [$referencia['COLUMN_NAME'], $referencia['REFERENCED_COLUMN_NAME']];
            if ($referencia['IS_NULLABLE'] === 'YES') {
                $fks[$clave]['IS_NULLABLE'] = 'YES';
                $fks[$clave]['anulables'][] = $referencia['COLUMN_NAME'];
            }
        }
        $seleccion = [];
        $agregar = static function (string $tabla, array $filas) use (&$seleccion): bool {
            $antes = count($seleccion[$tabla] ?? []);
            foreach ($filas as $fila) {
                $seleccion[$tabla][json_encode($fila, JSON_THROW_ON_ERROR)] = $fila;
            }
            return count($seleccion[$tabla] ?? []) > $antes;
        };
        foreach ($raices as $tabla => $filas) {
            if (in_array($tabla, self::PROTEGIDAS, true)) {
                throw new LogicException('Un catálogo global no puede ser raíz de limpieza.');
            }
            $agregar($tabla, $filas);
        }
        do {
            $cambio = false;
            foreach ($fks as $fk) {
                $padre = $fk['REFERENCED_TABLE_NAME'];
                $tabla = $fk['TABLE_NAME'];
                if (empty($seleccion[$padre]) || in_array($tabla, self::PROTEGIDAS, true)) {
                    continue;
                }
                if (!isset($claves[$tabla])) {
                    throw new RuntimeException('La tabla dependiente ' . $tabla . ' no tiene clave primaria; no se elimina nada.');
                }
                $union = [];
                foreach ($fk['pares'] as [$hija, $referida]) {
                    $union[] = 'h.' . self::identificador($hija) . '=p.' . self::identificador($referida);
                }
                foreach (array_chunk(array_values($seleccion[$padre]), 200) as $lote) {
                    [$where, $parametros] = self::condicion($lote, 'p.');
                    $columnas = array_map(static fn (string $c): string => 'h.' . self::identificador($c), $claves[$tabla]);
                    $filas = $this->filas('SELECT ' . implode(',', $columnas) . ' FROM ' . self::identificador($tabla) . ' h JOIN ' . self::identificador($padre) . ' p ON ' . implode(' AND ', $union) . ' WHERE ' . $where, $parametros);
                    $cambio = $agregar($tabla, $filas) || $cambio;
                }
            }
        } while ($cambio);
        // Una referencia cruzada nunca autoriza borrar un acto de otra clínica.
        $conClinica = [];
        foreach ($this->filas("SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='id_clinica'") as $fila) {
            $conClinica[$fila['TABLE_NAME']]['id_clinica'] = true;
        }
        // También una invitación pendiente tiene clínica, aunque su columna se llame id_clinica_vinculo.
        foreach ($referencias as $referencia) {
            if ($referencia['REFERENCED_TABLE_NAME'] === 'clinicas' && $referencia['REFERENCED_COLUMN_NAME'] === 'id_clinica') {
                $conClinica[$referencia['TABLE_NAME']][$referencia['COLUMN_NAME']] = true;
            }
        }
        foreach ($seleccion as $tabla => $filas) {
            if ($filas === [] || !isset($conClinica[$tabla])) {
                continue;
            }
            $columnas = implode(',', array_map(self::identificador(...), array_keys($conClinica[$tabla])));
            foreach (array_chunk(array_values($filas), 200) as $lote) {
                [$where, $parametros] = self::condicion($lote);
                foreach ($this->filas('SELECT ' . $columnas . ' FROM ' . self::identificador($tabla) . ' WHERE ' . $where, $parametros) as $fila) {
                    foreach ($fila as $clinica) {
                        if ($clinica !== null && !in_array((int) $clinica, $clinicasPermitidas, true)) {
                            throw new RuntimeException('Hay dependencias en una clínica ajena; se conserva todo el conjunto.');
                        }
                    }
                }
            }
        }
        // Hijos antes que padres, también en FK opcionales: eventos exige conservar algún recurso hasta borrarlo.
        // Solo se anula una FK opcional si rompe un ciclo real del orden (p.ej. fuente de pago activa).
        $cortadas = [];
        do {
            $orden = [];
            $visitando = [];
            $corte = null;
            $visitar = function (string $tabla, array $ruta = []) use (&$visitar, &$orden, &$visitando, &$corte, $cortadas, $seleccion, $fks): bool {
                if (isset($orden[$tabla])) {
                    return true;
                }
                if (isset($visitando[$tabla])) {
                    foreach (array_reverse(array_slice($ruta, $visitando[$tabla])) as $clave) {
                        if ($fks[$clave]['IS_NULLABLE'] === 'YES') {
                            $corte = $clave;
                            return false;
                        }
                    }
                    throw new RuntimeException('Ciclo obligatorio en la limpieza; se revierte la fase.');
                }
                $visitando[$tabla] = count($ruta);
                foreach ($fks as $clave => $fk) {
                    if (!isset($cortadas[$clave]) && $fk['REFERENCED_TABLE_NAME'] === $tabla && !empty($seleccion[$fk['TABLE_NAME']])) {
                        if (!$visitar($fk['TABLE_NAME'], [...$ruta, $clave])) {
                            return false;
                        }
                    }
                }
                unset($visitando[$tabla]);
                $orden[$tabla] = true;
                return true;
            };
            foreach (array_keys($seleccion) as $tabla) {
                if (!$visitar($tabla)) {
                    break;
                }
            }
            if ($corte !== null) {
                $fk = $fks[$corte];
                foreach (array_chunk(array_values($seleccion[$fk['TABLE_NAME']]), 200) as $lote) {
                    [$where, $parametros] = self::condicion($lote);
                    $anular = array_map(static fn (string $c): string => self::identificador($c) . '=NULL', $fk['anulables']);
                    $this->db->prepare('UPDATE ' . self::identificador($fk['TABLE_NAME']) . ' SET ' . implode(',', $anular) . ' WHERE ' . $where)->execute($parametros);
                }
                $cortadas[$corte] = true;
            }
        } while ($corte !== null);
        foreach (array_keys($orden) as $tabla) {
            foreach (array_chunk(array_values($seleccion[$tabla] ?? []), 200) as $lote) {
                [$where, $parametros] = self::condicion($lote);
                $this->db->prepare('DELETE FROM ' . self::identificador($tabla) . ' WHERE ' . $where)->execute($parametros);
            }
        }
    }

    private static function condicion(array $filas, string $alias = ''): array
    {
        $partes = [];
        $parametros = [];
        foreach ($filas as $fila) {
            $condiciones = [];
            foreach ($fila as $columna => $valor) {
                $condiciones[] = $alias . self::identificador($columna) . '=?';
                $parametros[] = $valor;
            }
            $partes[] = '(' . implode(' AND ', $condiciones) . ')';
        }
        return ['(' . implode(' OR ', $partes) . ')', $parametros];
    }

    private static function identificador(string $nombre): string
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/iD', $nombre)) {
            throw new RuntimeException('Identificador de esquema no admitido.');
        }
        return '`' . $nombre . '`';
    }

    private static function marcas(array $valores): string
    {
        return implode(',', array_fill(0, count($valores), '?'));
    }
}
