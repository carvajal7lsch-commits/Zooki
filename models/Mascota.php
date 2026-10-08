<?php
require_once __DIR__ . '/ModeloClinica.php';
require_once __DIR__ . '/Auditoria.php';
require_once __DIR__ . '/../helpers/ValidadorMascota.php';
require_once __DIR__ . '/../helpers/Transaccion.php';

/** RN-110 / RNF-11: ficha global, acceso del personal por mascota_clinica. */
class Mascota extends ModeloClinica
{
    private const FICHA = "SELECT m.*, mc.numero_historia_clinica,
        u.nombre_completo AS propietario_nombre, u.documento AS propietario_documento,
        e.nombre_especie, r.nombre_raza
        FROM mascotas m JOIN mascota_clinica mc ON mc.id_mascota = m.id_mascota
        LEFT JOIN usuarios u ON u.id_usuario = m.id_propietario
        LEFT JOIN especies e ON e.id_especie = m.id_especie
        LEFT JOIN razas r ON r.id_raza = m.id_raza
        WHERE mc.id_clinica = ? AND mc.estado = 'activo'";
    private const EDITABLES = ['nombre','id_especie','id_raza','raza_indicada','sexo',
        'fecha_nacimiento','peso','url_foto','esterilizado','estado'];
    private const PROTEGIDOS = ['id_especie','id_raza','raza_indicada','sexo','fecha_nacimiento'];

    public function getAll(bool $incluirInactivas = false): array
    {
        return $this->leer(self::FICHA . ($incluirInactivas ? '' : ' AND m.estado = 1') . ' ORDER BY m.nombre', [$this->clinica()]);
    }
    public function getById($id): array
    {
        $filas = $this->leer(self::FICHA . ' AND m.id_mascota = ?', [$this->clinica(),$id]);
        if (!$filas) $this->denegar($id);
        return $filas[0];
    }
    /**
     * Ficha para el historial de la clínica. Con el vínculo activo, la ficha
     * completa; con el vínculo inactivo (RN-115), solo lo que identifica a la
     * mascota en sus propios registros, sin los datos nuevos del propietario.
     */
    public function getParaHistorial(int $id): array
    {
        $consulta = $this->conn->prepare("SELECT m.id_mascota, m.nombre, m.fecha_nacimiento, e.nombre_especie,
                mc.numero_historia_clinica, mc.estado AS vinculo
            FROM mascotas m
            JOIN mascota_clinica mc ON mc.id_mascota = m.id_mascota AND mc.id_clinica = ?
            LEFT JOIN especies e ON e.id_especie = m.id_especie
            WHERE m.id_mascota = ?");
        $consulta->execute([$this->clinica(), $id]);
        $fila = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($fila === false) {
            $this->denegar($id);
        }
        if ($fila['vinculo'] === 'activo') {
            return $this->getById($id) + ['vinculo' => 'activo', 'solo_lectura' => false];
        }
        return $fila + ['solo_lectura' => true];
    }

    public function search($term): array
    {
        $clinica = $this->clinica();
        if (mb_strlen(trim($term)) < 3) return [];
        $como = '%' . str_replace(['!','%','_'],['!!','!%','!_'],trim($term)) . '%';
        return $this->leer(self::FICHA . " AND m.estado = 1 AND
            (m.nombre LIKE ? ESCAPE '!' OR u.nombre_completo LIKE ? ESCAPE '!' OR u.documento LIKE ? ESCAPE '!')
            ORDER BY m.nombre LIMIT 10",[$clinica,$como,$como,$como]);
    }
    public function getByPropietario($idPropietario): array
    {
        return $this->leer(self::FICHA . ' AND m.id_propietario = ? AND m.estado = 1 ORDER BY m.nombre',[$this->clinica(),$idPropietario]);
    }
    private function leer(string $sql,array $parametros): array
    {
        $stmt=$this->conn->prepare($sql); $stmt->execute($parametros);
        $filas=$stmt->fetchAll(PDO::FETCH_ASSOC);
        // RN-107: relación de colores, sin GROUP_CONCAT dependiente de MySQL.
        $colores=$this->conn->prepare('SELECT c.id_color,c.nombre_color FROM mascota_colores mc
            JOIN colores_base c ON c.id_color = mc.id_color WHERE mc.id_mascota = ? ORDER BY c.id_color');
        foreach ($filas as &$fila) {
            $colores->execute([$fila['id_mascota']]); $lista=$colores->fetchAll(PDO::FETCH_ASSOC);
            $fila['colores_ids']=implode(',',array_column($lista,'id_color'));
            $fila['colores_nombres']=implode(', ',array_column($lista,'nombre_color'));
            $fila['identidad_editable']=(int)$fila['id_clinica_registro'] === $this->clinica();
            $fila['especie']=$fila['nombre_especie']; $fila['raza']=$fila['nombre_raza'];
        }
        return $filas;
    }
    public function esPropietarioValido($id): bool
    {
        $stmt=$this->conn->prepare("SELECT 1 FROM propietario_clinica pc JOIN usuarios u ON u.id_usuario = pc.id_propietario
            WHERE pc.id_propietario = ? AND pc.id_clinica = ? AND pc.estado = 'activo' AND u.estado = 1");
        $stmt->execute([$id,$this->clinica()]); return (bool)$stmt->fetchColumn();
    }
    public function insert(array $datos): int
    {
        $clinica=$this->clinica();
        if (!$this->esPropietarioValido($datos['id_propietario'] ?? 0)) $this->denegar(null);
        $datos=$this->validar($datos);
        return Transaccion::ejecutar($this->conn,function () use ($datos,$clinica) {
            // RN-502: 256 bits independientes del id; base64url de 43 caracteres.
            $token=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');
            $campos=array_intersect_key($datos,array_flip(self::EDITABLES));
            $campos+=['id_propietario'=>$datos['id_propietario'],'id_clinica_registro'=>$clinica,'token_carnet'=>$token];
            $sql='INSERT INTO mascotas (' . implode(',',array_keys($campos)) . ') VALUES (' . implode(',',array_fill(0,count($campos),'?')) . ')';
            $this->conn->prepare($sql)->execute(array_values($campos)); $id=(int)$this->conn->lastInsertId();
            // RN-102: HC NULL hasta la primera consulta (C4).
            $this->conn->prepare('INSERT INTO mascota_clinica (id_mascota,id_clinica) VALUES (?,?)')->execute([$id,$clinica]);
            $this->escribirColores($id,$datos['colores']);
            $this->registrarAuditoria($id,'registro',null,'Ficha y vínculo creados');
            return $id;
        });
    }
    /** RE-1.5.2: propietario confirmado, mascota existente y sin duplicación. */
    public function vincular(int $id,int $idPropietario): void
    {
        if (!$this->esPropietarioValido($idPropietario)) $this->denegar($id);
        try { Transaccion::ejecutar($this->conn,function () use ($id,$idPropietario) {
            $bloqueo=$this->conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $stmt=$this->conn->prepare('SELECT id_mascota FROM mascotas WHERE id_mascota = ? AND id_propietario = ? AND estado = 1' . $bloqueo);
            $stmt->execute([$id,$idPropietario]); if (!$stmt->fetchColumn()) $this->denegar($id);
            $stmt=$this->conn->prepare('SELECT estado FROM mascota_clinica WHERE id_mascota = ? AND id_clinica = ?');
            $stmt->execute([$id,$this->clinica()]); $estado=$stmt->fetchColumn();
            if ($estado === 'activo') return;
            if ($estado === false) $this->conn->prepare('INSERT INTO mascota_clinica (id_mascota,id_clinica) VALUES (?,?)')->execute([$id,$this->clinica()]);
            else $this->conn->prepare("UPDATE mascota_clinica SET estado = 'activo' WHERE id_mascota = ? AND id_clinica = ?")->execute([$id,$this->clinica()]);
            $this->registrarAuditoria($id,'vinculo_clinica',$estado === false ? null : $estado,'activo');
        }); } catch (AccesoDenegado $e) { $this->denegar($id); }
    }
    public function comprobarEdicion(array $actual,array $datos): void
    {
        if (isset($datos['id_propietario']) && (int)$datos['id_propietario'] !== (int)$actual['id_propietario']) $this->denegar($actual['id_mascota']);
        if ((int)$actual['id_clinica_registro'] === $this->clinica()) return;
        foreach (self::PROTEGIDOS as $campo) {
            if (array_key_exists($campo,$datos) && $datos[$campo] != $actual[$campo]) $this->denegar($actual['id_mascota']);
        }
    }
    public function update(array $datos): bool
    {
        $id=(int)($datos['id_mascota'] ?? 0);
        $this->comprobarEdicion($this->getById($id),$datos);
        try {
            return Transaccion::ejecutar($this->conn,function () use ($id,$datos) {
                // RN-110: releer bajo bloqueo; editar peso no sobrescribe identidad concurrente.
                if ($this->conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                    $this->conn->prepare('SELECT id_mascota FROM mascotas WHERE id_mascota = ? FOR UPDATE')->execute([$id]);
                }
                $actual=$this->getById($id);
                $this->comprobarEdicion($actual,$datos);
                $entrada=array_replace($actual,$datos);
                $entrada['colores']=$datos['colores'] ?? array_filter(explode(',',$actual['colores_ids']));
                $entrada=$this->validar($entrada);
                $this->comprobarEdicion($actual,$entrada);
                $campos=array_intersect_key($entrada,array_flip(self::EDITABLES),$datos);
                // Las dos representaciones de raza deben seguir siendo excluyentes.
                if (array_key_exists('id_raza',$datos) || array_key_exists('raza_indicada',$datos)) {
                    $campos['id_raza']=$entrada['id_raza']; $campos['raza_indicada']=$entrada['raza_indicada'];
                }
                $cambios=[];
                foreach ($campos as $campo=>$valor) {
                    if ($actual[$campo] == $valor) { unset($campos[$campo]); continue; }
                    $cambios[]=$campo;
                }
                if ($campos) {
                    $sql='UPDATE mascotas SET ' . implode(',',array_map(fn($campo)=>"$campo = ?",array_keys($campos))) .
                        " WHERE id_mascota = ? AND EXISTS (SELECT 1 FROM mascota_clinica mc WHERE mc.id_mascota = mascotas.id_mascota AND mc.id_clinica = ? AND mc.estado = 'activo')";
                    $this->conn->prepare($sql)->execute([...array_values($campos),$id,$this->clinica()]);
                    foreach ($campos as $campo=>$valor) $this->registrarAuditoria($id,$campo,$actual[$campo],$valor);
                }
                $antes=array_map('intval',array_filter(explode(',',$actual['colores_ids']))); sort($antes);
                if (array_key_exists('colores',$datos) && $antes !== $entrada['colores']) {
                    $this->escribirColores($id,$entrada['colores']);
                    $this->registrarAuditoria($id,'colores',implode(',',$antes),implode(',',$entrada['colores'])); $cambios[]='colores';
                }
                if ($cambios) $this->avisarPropietario($actual,$cambios);
                return true;
            });
        } catch (AccesoDenegado $e) {
            // La denegación se conserva después de deshacer la operación.
            $this->denegar($id);
        }
    }
    public function updateStatus($id,$estado): bool
    {
        if (!in_array((string)$estado,['0','1'],true)) throw new InvalidArgumentException('Estado no válido.');
        return $this->update(['id_mascota'=>$id,'estado'=>(int)$estado]);
    }
    public function getEstado($id): int { return (int)$this->getById($id)['estado']; }
    public function getPropietarioSiActiva($id): ?int
    {
        if (!ctype_digit((string)$id) || (int)$id < 1) return null;
        $m=$this->getById($id);
        return (int)$m['estado'] === 1 && $m['id_propietario'] !== null ? (int)$m['id_propietario'] : null;
    }
    private function validar(array $datos): array
    {
        $resultado=ValidadorMascota::validar([
            'nombre'=>$datos['nombre'] ?? '', 'especie'=>$datos['id_especie'] ?? '',
            'raza'=>!empty($datos['raza_indicada']) ? 'otra' : ($datos['id_raza'] ?? ''),
            'raza_indicada'=>$datos['raza_indicada'] ?? '', 'sexo'=>$datos['sexo'] ?? '',
            'fecha_nacimiento'=>$datos['fecha_nacimiento'] ?? '', 'peso'=>$datos['peso'] ?? '',
        ],new DateTimeImmutable('today',new DateTimeZone('America/Bogota')));
        if ($resultado['error']) throw new InvalidArgumentException($resultado['error']);
        $limpios=$resultado['datos'];
        if (!$limpios['fecha_nacimiento']) throw new InvalidArgumentException('La fecha de nacimiento es obligatoria.');
        if (!$this->especieExiste((int)$limpios['especie'])) throw new InvalidArgumentException('Especie no válida.');
        if ($limpios['raza'] !== null && !$this->razaPerteneceAEspecie($limpios['raza'],$limpios['especie'])) throw new InvalidArgumentException('La raza no corresponde a la especie.');
        $datos=array_replace($datos,['nombre'=>$limpios['nombre'],'id_especie'=>(int)$limpios['especie'],
            'id_raza'=>$limpios['raza'],'raza_indicada'=>$limpios['raza_indicada'],
            'sexo'=>$limpios['sexo'],'peso'=>$limpios['peso'],'fecha_nacimiento'=>$limpios['fecha_nacimiento']]);
        if (!in_array((string)($datos['estado'] ?? 1),['0','1'],true)) throw new InvalidArgumentException('Estado no válido.');
        $datos['estado']=(int)($datos['estado'] ?? 1);
        $esterilizado=$datos['esterilizado'] ?? null;
        if ($esterilizado === '') $esterilizado=null;
        if ($esterilizado !== null && !in_array((string)$esterilizado,['0','1'],true)) throw new InvalidArgumentException('Esterilización no válida.');
        $datos['esterilizado']=$esterilizado === null ? null : (int)$esterilizado;
        $colores=$datos['colores'] ?? [];
        if (!is_array($colores) || !$colores) throw new InvalidArgumentException('Selecciona al menos un color.');
        $permitidos=array_map('intval',array_column($this->getColoresBase(),'id_color'));
        foreach ($colores as $color) {
            if (!ctype_digit((string)$color) || !in_array((int)$color,$permitidos,true)) throw new InvalidArgumentException('Color no válido.');
        }
        $datos['colores']=array_values(array_unique(array_map('intval',$colores))); sort($datos['colores']);
        return $datos;
    }
    private function escribirColores(int $id,array $colores): void
    {
        $this->conn->prepare('DELETE FROM mascota_colores WHERE id_mascota = ?')->execute([$id]);
        $stmt=$this->conn->prepare('INSERT INTO mascota_colores (id_mascota,id_color) VALUES (?,?)');
        foreach ($colores as $color) $stmt->execute([$id,$color]);
    }
    private function registrarAuditoria(int $id,string $campo,$anterior,$nuevo): void
    {
        if (!Contexto::idUsuario()) throw new AccesoDenegado(403,'Identidad requerida.');
        $this->conn->prepare('INSERT INTO auditoria_mascotas (id_clinica,id_mascota,id_usuario,campo_modificado,valor_anterior,valor_nuevo) VALUES (?,?,?,?,?,?)')
            ->execute([$this->clinica(),$id,Contexto::idUsuario(),$campo,$anterior,$nuevo]);
    }
    private function avisarPropietario(array $mascota,array $campos): void
    {
        // RE-1.4.5: aviso durable en la misma transacción; el envío puede reintentarse.
        $stmt=$this->conn->prepare('SELECT u.email,c.nombre FROM usuarios u JOIN clinicas c ON c.id_clinica = ? WHERE u.id_usuario = ?');
        $stmt->execute([$this->clinica(),$mascota['id_propietario']]); $destino=$stmt->fetch(PDO::FETCH_ASSOC);
        $mensaje='La clínica ' . $destino['nombre'] . ' actualizó la ficha de ' . $mascota['nombre'] . '. Campos: ' . implode(', ',$campos) . '.';
        $this->conn->prepare("INSERT INTO notificaciones (id_clinica,id_usuario,tipo_entidad,id_entidad,destinatario_email,tipo_notificacion,asunto,mensaje) VALUES (?,?,'mascota',?,?,'cambio_ficha',?,?)")
            ->execute([$this->clinica(),$mascota['id_propietario'],$mascota['id_mascota'],$destino['email'],'Ficha de mascota actualizada',$mensaje]);
    }
    private function denegar($id): never
    {
        (new Auditoria($this->conn))->log(Contexto::idUsuario(),'OTHER','mascotas',$id,null,null,'Acceso o edición de mascota denegados (RN-110)');
        throw new AccesoDenegado(403,'No tienes permiso para acceder o cambiar estos datos de la mascota.');
    }
    // Excepción global explícita: taxonomía de solo lectura (HU-7.2).
    public function getEspecies(): array { return $this->conn->query('SELECT * FROM especies ORDER BY nombre_especie')->fetchAll(PDO::FETCH_ASSOC); }
    public function getColoresBase(): array { return $this->conn->query('SELECT * FROM colores_base ORDER BY nombre_color')->fetchAll(PDO::FETCH_ASSOC); }
    public function getRazasByEspecie($id): array
    {
        $s=$this->conn->prepare('SELECT * FROM razas WHERE id_especie = ? ORDER BY nombre_raza'); $s->execute([$id]); return $s->fetchAll(PDO::FETCH_ASSOC);
    }
    public function especieExiste(int $id): bool
    {
        $s=$this->conn->prepare('SELECT 1 FROM especies WHERE id_especie = ?'); $s->execute([$id]); return (bool)$s->fetchColumn();
    }
    public function razaPerteneceAEspecie($raza,$especie): bool
    {
        $s=$this->conn->prepare('SELECT 1 FROM razas WHERE id_raza = ? AND id_especie = ?'); $s->execute([$raza,$especie]); return (bool)$s->fetchColumn();
    }
    public function razaEsDeEspecie(int $raza,int $especie): bool { return $this->razaPerteneceAEspecie($raza,$especie); }
    public function idSinRazaDefinida(int $especie): ?int
    {
        $s=$this->conn->prepare("SELECT id_raza FROM razas WHERE id_especie = ? AND nombre_raza = 'Sin raza definida'"); $s->execute([$especie]); $id=$s->fetchColumn(); return $id === false ? null : (int)$id;
    }
    public function guardarRazaIndicada(int $id,?string $raza): bool { return $this->update(['id_mascota'=>$id,'raza_indicada'=>$raza]); }
}
