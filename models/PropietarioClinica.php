<?php
require_once __DIR__ . '/ModeloClinica.php';
require_once __DIR__ . '/Usuario.php';
require_once __DIR__ . '/Auditoria.php';
require_once __DIR__ . '/PasswordReset.php';
require_once __DIR__ . '/../helpers/Transaccion.php';

/** RN-109: datos de propietario del personal y confirmación de vínculo. */
final class PropietarioClinica extends ModeloClinica
{
    private const BASICOS = 'u.id_usuario,u.nombre_completo,u.tipo_documento,u.documento,u.email,u.telefono';
    public const VIGENCIA_HORAS = 24;

    public function listar(): array
    {
        $stmt=$this->conn->prepare('SELECT ' . self::BASICOS . ", pc.estado AS estado_vinculo,
            CASE WHEN pc.estado = 'activo' THEN 1 ELSE 0 END AS estado,
            (SELECT COUNT(*) FROM mascotas m JOIN mascota_clinica mc ON mc.id_mascota=m.id_mascota
                WHERE m.id_propietario=u.id_usuario AND mc.id_clinica=pc.id_clinica AND mc.estado='activo' AND m.estado=1) AS total_mascotas
            FROM usuarios u JOIN propietario_clinica pc ON pc.id_propietario=u.id_usuario WHERE pc.id_clinica=? ORDER BY u.nombre_completo");
        $stmt->execute([$this->clinica()]); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function obtener(int $id): array
    {
        foreach ($this->listar() as $persona) if ((int)$persona['id_usuario'] === $id) return $persona;
        $this->denegar($id);
    }
    /** RE-1.5.1: excepción global limitada a una coincidencia exacta, sin clínica. */
    public function buscarExacto(string $identificador): ?array
    {
        $this->clinica(); $identificador=trim($identificador);
        if (!filter_var($identificador,FILTER_VALIDATE_EMAIL) && !preg_match('/^\d{5,15}$/D',$identificador)) return null;
        $campo=filter_var($identificador,FILTER_VALIDATE_EMAIL) ? 'email' : 'documento';
        $stmt=$this->conn->prepare('SELECT ' . self::BASICOS . " FROM usuarios u WHERE u.$campo = ? AND u.estado=1");
        $stmt->execute([$identificador]); $persona=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$persona) return null;
        $stmt=$this->conn->prepare("SELECT estado FROM propietario_clinica WHERE id_propietario=? AND id_clinica=?");
        $stmt->execute([$persona['id_usuario'],$this->clinica()]);
        $persona['vinculado']=$stmt->fetchColumn() === 'activo';
        // Sin peso, nacimiento, token, HC, alertas ni actos clínicos de otras clínicas.
        $stmt=$this->conn->prepare('SELECT m.id_mascota,m.nombre,e.nombre_especie,r.nombre_raza,m.raza_indicada,m.url_foto
            FROM mascotas m LEFT JOIN especies e ON e.id_especie=m.id_especie LEFT JOIN razas r ON r.id_raza=m.id_raza
            WHERE m.id_propietario=? AND m.estado=1 ORDER BY m.nombre');
        $stmt->execute([$persona['id_usuario']]); $persona['mascotas']=$stmt->fetchAll(PDO::FETCH_ASSOC);
        (new Auditoria($this->conn))->log(Contexto::idUsuario(),'VIEW','usuarios',(int)$persona['id_usuario'],null,null,'Búsqueda exacta de propietario (RN-111)');
        return $persona;
    }
    /** RE-T.19.1/2: la aceptación presencial se valida antes de cualquier INSERT. */
    public function registrar(array $datos,string $version,string $ip): array
    {
        $clinica=$this->clinica();
        if (($datos['titular_presente'] ?? '') !== '1' || ($datos['acepta_politica'] ?? '') !== '1') {
            throw new InvalidArgumentException('El titular debe estar presente y aceptar directamente la política antes de crear su cuenta.');
        }
        if ($version === '' || strlen($version)>20) throw new InvalidArgumentException('Política no disponible.');
        $usuario=new Usuario($this->conn);
        if ($usuario->existeDocumento($datos['documento']) || $usuario->existeEmail($datos['email'])) throw new InvalidArgumentException('La cuenta ya existe. Búscala por documento o correo completo y solicita su vinculación.');
        return Transaccion::ejecutar($this->conn,function () use ($datos,$version,$ip,$clinica,$usuario) {
            $id=$usuario->crear(array_replace($datos,['password'=>null,'debe_cambiar_password'=>0]));
            $this->conn->prepare('INSERT INTO consentimientos_datos (id_usuario,version_politica,medio,ip_address) VALUES (?,?,\'alta_personal\',?)')->execute([$id,$version,$ip]);
            $this->conn->prepare('INSERT INTO propietario_clinica (id_propietario,id_clinica) VALUES (?,?)')->execute([$id,$clinica]);
            $token=bin2hex(random_bytes(32));
            $reset=(new PasswordReset($this->conn))->createToken($id,$datos['email'],password_hash($token,PASSWORD_DEFAULT),date('Y-m-d H:i:s',time()+self::VIGENCIA_HORAS*3600));
            (new Auditoria($this->conn))->log(Contexto::idUsuario(),'INSERT','usuarios',$id,null,null,'Alta presencial de propietario con consentimiento y sin contraseña');
            return ['id_usuario'=>$id,'id_enlace'=>$reset,'token'=>$token];
        });
    }
    public function solicitarVinculo(string $identificador): array
    {
        $persona=$this->buscarExacto($identificador);
        if (!$persona) throw new InvalidArgumentException('No se encontró una cuenta con ese documento o correo completo.');
        if ($persona['vinculado']) throw new InvalidArgumentException('El propietario ya está vinculado a esta clínica.');
        // Un hash por solicitud. No se alteran contraseñas ni verificaciones de registro.
        $token=bin2hex(random_bytes(32));
        $stmt=$this->conn->prepare("INSERT INTO verificaciones_email (id_usuario,email,token_hash,expires_at,id_clinica_vinculo,proposito)
            VALUES (?,?,?,?,?,'vinculo_clinica')");
        $stmt->execute([$persona['id_usuario'],$persona['email'],password_hash($token,PASSWORD_DEFAULT),date('Y-m-d H:i:s',time()+self::VIGENCIA_HORAS*3600),$this->clinica()]);
        $id=(int)$this->conn->lastInsertId();
        (new Auditoria($this->conn))->log(Contexto::idUsuario(),'INSERT','verificaciones_email',$id,null,null,'Confirmación de vínculo solicitada al propietario');
        return ['id_enlace'=>$id,'token'=>$token,'email'=>$persona['email'],'nombre'=>$persona['nombre_completo'],'clinica'=>Contexto::actual()['clinica']];
    }
    /** Público: el alcance proviene de la solicitud guardada, jamás del navegador. */
    public function confirmarVinculo(int $id,string $token): bool
    {
        return Transaccion::ejecutar($this->conn,function () use ($id,$token) {
            $bloqueo=$this->conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $stmt=$this->conn->prepare("SELECT v.*,u.email AS email_actual,u.estado AS usuario_activo,c.estado AS clinica_estado
                FROM verificaciones_email v JOIN usuarios u ON u.id_usuario=v.id_usuario
                JOIN clinicas c ON c.id_clinica=v.id_clinica_vinculo
                WHERE v.id=? AND v.proposito='vinculo_clinica'" . $bloqueo);
            $stmt->execute([$id]); $fila=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$fila || (int)$fila['used'] !== 0 || strtotime($fila['expires_at'])<=time()
                || (int)$fila['usuario_activo'] !== 1 || $fila['clinica_estado'] !== 'activa'
                || $fila['email'] !== $fila['email_actual'] || !password_verify($token,$fila['token_hash'])) return false;
            $stmt=$this->conn->prepare('UPDATE verificaciones_email SET used=1 WHERE id=? AND used=0');
            $stmt->execute([$id]); if ($stmt->rowCount() !== 1) return false;
            $stmt=$this->conn->prepare('SELECT estado FROM propietario_clinica WHERE id_propietario=? AND id_clinica=?');
            $stmt->execute([$fila['id_usuario'],$fila['id_clinica_vinculo']]); $estado=$stmt->fetchColumn();
            if ($estado === false) $this->conn->prepare('INSERT INTO propietario_clinica (id_propietario,id_clinica) VALUES (?,?)')->execute([$fila['id_usuario'],$fila['id_clinica_vinculo']]);
            else $this->conn->prepare("UPDATE propietario_clinica SET estado='activo' WHERE id_propietario=? AND id_clinica=?")->execute([$fila['id_usuario'],$fila['id_clinica_vinculo']]);
            (new Auditoria($this->conn))->log((int)$fila['id_usuario'],'INSERT','propietario_clinica',(int)$fila['id_usuario'],null,null,'Vínculo de clínica confirmado por correo (RN-109)',(int)$fila['id_clinica_vinculo']);
            return true;
        });
    }
    public function actualizar(int $id,array $datos): void
    {
        $actual=$this->obtener($id);
        // C1.7: una identidad de propietario no se administra desde el personal.
        foreach (['documento','tipo_documento','email'] as $campo) {
            if (isset($datos[$campo]) && $datos[$campo] !== $actual[$campo]) $this->denegar($id);
        }
        Transaccion::ejecutar($this->conn,function () use ($id,$datos,$actual) {
            $this->conn->prepare('UPDATE usuarios SET nombre_completo=?,telefono=? WHERE id_usuario=?')->execute([$datos['nombre_completo'],$datos['telefono'],$id]);
            $this->conn->prepare('UPDATE propietario_clinica SET estado=? WHERE id_propietario=? AND id_clinica=?')->execute([(string)$datos['estado'] === '1' ? 'activo' : 'inactivo',$id,$this->clinica()]);
            (new Auditoria($this->conn))->log(Contexto::idUsuario(),'UPDATE','propietario_clinica',$id,['nombre_completo'=>$actual['nombre_completo'],'telefono'=>$actual['telefono'],'estado'=>$actual['estado']],array_intersect_key($datos,array_flip(['nombre_completo','telefono','estado'])),'Datos de contacto y vínculo del propietario actualizados');
        });
    }
    private function denegar(int $id): never
    {
        (new Auditoria($this->conn))->log(Contexto::idUsuario(),'OTHER','propietario_clinica',$id,null,null,'Acceso o cambio de identidad de propietario denegados');
        throw new AccesoDenegado(403,'No tienes acceso a estos datos del propietario.');
    }
}
