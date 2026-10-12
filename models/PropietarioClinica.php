<?php
require_once __DIR__ . '/ModeloClinica.php';
require_once __DIR__ . '/Usuario.php';
require_once __DIR__ . '/Auditoria.php';
require_once __DIR__ . '/PasswordReset.php';
require_once __DIR__ . '/../helpers/Transaccion.php';
require_once __DIR__ . '/../helpers/ContactoPropietario.php';

/** RN-109: datos de propietario del personal y confirmación de vínculo. */
final class PropietarioClinica extends ModeloClinica
{
    public const VIGENCIA_HORAS = 24;

    /** RN-115: la clínica no edita a un cliente desvinculado. */
    public const MENSAJE_DESVINCULADO = 'El cliente está desvinculado: sus datos solo los cambia él.';

    /** RE-1.5.1 y RN-109 (decisión del usuario, M5): lo único que se dice de una cuenta sin vínculo activo. */
    public const MENSAJE_SIN_VINCULO = 'Hay una cuenta con ese dato. Pide la confirmación por correo.';

    /**
     * Propietarios de la clínica activa. RN-115: con el vínculo inactivo
     * solo el nombre y la marca «Desvinculado» (ContactoPropietario).
     */
    public function listar(): array
    {
        $sql = 'SELECT u.id_usuario, u.nombre_completo, ' . ContactoPropietario::columnas('u', 'pc') . ",
                pc.estado AS estado_vinculo,
                CASE WHEN pc.estado = 'activo' THEN 1 ELSE 0 END AS estado,
                " . ContactoPropietario::marca('pc') . ",
                (SELECT COUNT(*) FROM mascotas m JOIN mascota_clinica mc ON mc.id_mascota = m.id_mascota
                    WHERE m.id_propietario = u.id_usuario AND mc.id_clinica = pc.id_clinica AND mc.estado = 'activo' AND m.estado = 1) AS total_mascotas
            FROM usuarios u
            JOIN propietario_clinica pc ON pc.id_propietario = u.id_usuario
            WHERE pc.id_clinica = ?
            ORDER BY u.nombre_completo";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$this->clinica()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtener(int $id): array
    {
        foreach ($this->listar() as $persona) {
            if ((int) $persona['id_usuario'] === $id) {
                return $persona;
            }
        }
        $this->denegar($id);
    }

    /**
     * RE-1.5.1, RN-109 y RN-111: excepción global limitada a una coincidencia
     * exacta. Con el vínculo activo, los datos del propietario y sus mascotas
     * básicas (HU-1.5). Sin él (nunca vinculado o desvinculado), la misma
     * respuesta para todos: que la cuenta existe y se pide la confirmación,
     * sin nombre, contacto ni mascotas.
     */
    public function buscarExacto(string $identificador): ?array
    {
        $persona = $this->personaExacta($identificador);
        if ($persona === null) {
            return null;
        }
        $vinculado = $this->estadoVinculo((int) $persona['id_usuario']) === 'activo';
        $auditoria = new Auditoria($this->conn);
        if (!$vinculado) {
            $auditoria->log(Contexto::idUsuario(), 'VIEW', 'usuarios', (int) $persona['id_usuario'], null, null, 'Búsqueda exacta de una cuenta sin vínculo con la clínica (RN-109)');
            return ['vinculado' => false, 'mensaje' => self::MENSAJE_SIN_VINCULO];
        }
        // Sin peso, nacimiento, token, HC, alertas ni actos clínicos de otras clínicas.
        $stmt = $this->conn->prepare('SELECT m.id_mascota, m.nombre, e.nombre_especie, r.nombre_raza, m.raza_indicada, m.url_foto
            FROM mascotas m
            LEFT JOIN especies e ON e.id_especie = m.id_especie
            LEFT JOIN razas r ON r.id_raza = m.id_raza
            WHERE m.id_propietario = ? AND m.estado = 1
            ORDER BY m.nombre');
        $stmt->execute([$persona['id_usuario']]);
        $mascotas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $auditoria->log(Contexto::idUsuario(), 'VIEW', 'usuarios', (int) $persona['id_usuario'], null, null, 'Búsqueda exacta de propietario (RN-111)');
        return $persona + ['vinculado' => true, 'mascotas' => $mascotas];
    }

    /** Cuenta activa con ese documento o correo exacto; solo para uso interno. */
    private function personaExacta(string $identificador): ?array
    {
        $this->clinica();
        $identificador = trim($identificador);
        $esCorreo = filter_var($identificador, FILTER_VALIDATE_EMAIL) !== false;
        if (!$esCorreo && !preg_match('/^\d{5,15}$/D', $identificador)) {
            return null;
        }
        $campo = $esCorreo ? 'email' : 'documento';
        $stmt = $this->conn->prepare("SELECT u.id_usuario, u.nombre_completo, u.tipo_documento, u.documento, u.email, u.telefono
            FROM usuarios u
            WHERE u.$campo = ? AND u.estado = 1");
        $stmt->execute([$identificador]);
        $persona = $stmt->fetch(PDO::FETCH_ASSOC);
        return $persona === false ? null : $persona;
    }

    /** Estado del vínculo de la persona con la clínica activa ('activo', 'inactivo') o null. */
    private function estadoVinculo(int $idPersona): ?string
    {
        $stmt = $this->conn->prepare('SELECT estado FROM propietario_clinica WHERE id_propietario = ? AND id_clinica = ?');
        $stmt->execute([$idPersona, $this->clinica()]);
        $estado = $stmt->fetchColumn();
        return $estado === false ? null : (string) $estado;
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
    /** RN-109: la clínica pide al titular, por correo, confirmar el vínculo. */
    public function solicitarVinculo(string $identificador): array
    {
        $persona = $this->personaExacta($identificador);
        if ($persona === null) {
            throw new InvalidArgumentException('No se encontró una cuenta con ese documento o correo completo.');
        }
        return $this->crearSolicitud($persona);
    }

    /**
     * Clientes (D1, RN-115): para reactivar un vínculo inactivo la clínica pide
     * la confirmación por el id de la persona, porque ya no ve su correo. Solo
     * sirve con quien tuvo vínculo con esta clínica.
     */
    public function solicitarVinculoDe(int $idPersona): array
    {
        if ($this->estadoVinculo($idPersona) === null) {
            $this->denegar($idPersona);
        }
        $stmt = $this->conn->prepare('SELECT u.id_usuario, u.nombre_completo, u.email FROM usuarios u WHERE u.id_usuario = ? AND u.estado = 1');
        $stmt->execute([$idPersona]);
        $persona = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($persona === false) {
            throw new InvalidArgumentException('La cuenta de este cliente no está activa.');
        }
        return $this->crearSolicitud($persona);
    }

    private function crearSolicitud(array $persona): array
    {
        if ($this->estadoVinculo((int) $persona['id_usuario']) === 'activo') {
            throw new InvalidArgumentException('El propietario ya está vinculado a esta clínica.');
        }
        // Un hash por solicitud. No se alteran contraseñas ni verificaciones de registro.
        $token = bin2hex(random_bytes(32));
        $vence = date('Y-m-d H:i:s', time() + self::VIGENCIA_HORAS * 3600);
        $stmt = $this->conn->prepare("INSERT INTO verificaciones_email (id_usuario, email, token_hash, expires_at, id_clinica_vinculo, proposito)
            VALUES (?, ?, ?, ?, ?, 'vinculo_clinica')");
        $stmt->execute([$persona['id_usuario'], $persona['email'], password_hash($token, PASSWORD_DEFAULT), $vence, $this->clinica()]);
        $id = (int) $this->conn->lastInsertId();
        (new Auditoria($this->conn))->log(Contexto::idUsuario(), 'INSERT', 'verificaciones_email', $id, null, null, 'Confirmación de vínculo solicitada al propietario');
        return [
            'id_enlace' => $id,
            'token' => $token,
            'email' => $persona['email'],
            'nombre' => $persona['nombre_completo'],
            'clinica' => Contexto::actual()['clinica'],
        ];
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
        // D1 (RN-109, revisión de C9.1): la clínica puede desactivar el vínculo,
        // pero no reactivarlo por su cuenta. Se reactiva solo cuando el titular
        // confirma por correo (solicitarVinculo → confirmarVinculo).
        if ((int) $actual['estado'] !== 1) {
            if ((string) $datos['estado'] === '1') {
                throw new InvalidArgumentException('El vínculo solo se reactiva cuando el propietario lo confirma. Envíale la solicitud por correo.');
            }
            // RN-115 (decisión del usuario, M5 C1): la clínica ya no ve su
            // contacto, así que tampoco lo sobrescribe en la cuenta global.
            throw new InvalidArgumentException(self::MENSAJE_DESVINCULADO);
        }
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
