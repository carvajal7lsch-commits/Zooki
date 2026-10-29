<?php
require_once __DIR__ . '/ModeloClinica.php';
require_once __DIR__ . '/Usuario.php';
require_once __DIR__ . '/Auditoria.php';
require_once __DIR__ . '/../helpers/Transaccion.php';
require_once __DIR__ . '/../helpers/ContactoPropietario.php';
require_once __DIR__ . '/VinculoClinica.php';
require_once __DIR__ . '/ActivacionPropietario.php';
require_once __DIR__ . '/../helpers/ValidadorCuenta.php';

/** RN-109: datos de propietario del personal y confirmación de vínculo. */
final class PropietarioClinica extends ModeloClinica
{
    public const VIGENCIA_HORAS = 24;
    private const MASCOTAS_RESUMEN_MAX = 3;

    /** RN-115: la clínica no edita a un cliente desvinculado. */
    public const MENSAJE_DESVINCULADO = 'El cliente está desvinculado: sus datos solo los cambia él.';

    /** RE-1.5.1 y RN-109 (decisión del usuario, M5): lo único que se dice de una cuenta sin vínculo activo. */
    /** M2 C7: dice en una línea qué hacer si aún no es cliente de esta clínica (RN-109). */
    public const MENSAJE_SIN_VINCULO = 'Hay una cuenta con ese dato, pero aún no es cliente de esta clínica: pide la confirmación por correo o que se vincule desde su portal.';

    /** M5 C3: al registrar a quien ya es cliente de esta clínica. */
    public const MENSAJE_YA_CLIENTE = 'Ya es cliente de esta clínica. Búscalo en «Nueva mascota».';

    /**
     * Propietarios de la clínica activa. RN-115: con el vínculo inactivo
     * solo el nombre y la marca «Desvinculado» (ContactoPropietario).
     */
    public function listar(): array
    {
        // RE-1.2.3/RN-115: solo nombres básicos de mascotas activas de esta clínica y de un vínculo vigente.
        $resumenMascotas = [];
        for ($posicion = 0; $posicion < self::MASCOTAS_RESUMEN_MAX; $posicion++) {
            $resumenMascotas[] = "(SELECT m.nombre FROM mascotas m JOIN mascota_clinica mc ON mc.id_mascota = m.id_mascota
                WHERE m.id_propietario = u.id_usuario AND mc.id_clinica = pc.id_clinica
                    AND mc.estado = 'activo' AND m.estado = 1 AND pc.estado = 'activo'
                ORDER BY m.id_mascota LIMIT 1 OFFSET {$posicion}) AS mascota_resumen_{$posicion}";
        }
        $sql = 'SELECT u.id_usuario, u.nombre_completo, ' . ContactoPropietario::columnas('u', 'pc') . ",
                pc.estado AS estado_vinculo,
                CASE WHEN pc.estado = 'activo' THEN 1 ELSE 0 END AS estado,
                " . ContactoPropietario::marca('pc') . ",
                CASE WHEN " . ActivacionPropietario::pendiente('u') . " THEN 1 ELSE 0 END AS pendiente_activacion,
                (SELECT COUNT(*) FROM mascotas m JOIN mascota_clinica mc ON mc.id_mascota = m.id_mascota
                    WHERE m.id_propietario = u.id_usuario AND mc.id_clinica = pc.id_clinica AND mc.estado = 'activo' AND m.estado = 1) AS total_mascotas,
                " . implode(",\n", $resumenMascotas) . "
            FROM usuarios u
            JOIN propietario_clinica pc ON pc.id_propietario = u.id_usuario
            WHERE pc.id_clinica = ?
            ORDER BY u.nombre_completo";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$this->clinica()]);
        $personas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($personas as &$persona) {
            $persona['mascotas_resumen'] = [];
            for ($posicion = 0; $posicion < self::MASCOTAS_RESUMEN_MAX; $posicion++) {
                $campo = 'mascota_resumen_' . $posicion;
                if ($persona[$campo] !== null) $persona['mascotas_resumen'][] = $persona[$campo];
                unset($persona[$campo]);
            }
        }
        unset($persona);
        return $personas;
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
        // RN-G13: el super-administrador nunca es cliente; se responde como a cualquier cuenta sin vínculo.
        $vinculado = !$this->esPlataforma($persona) && $this->estadoVinculo((int) $persona['id_usuario']) === 'activo';
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
        $pendiente = (int) $persona['pendiente'] === 1;
        unset($persona['es_super_admin'], $persona['pendiente']);
        // M5 C3: la clínica sabe que su cliente aún no activa su cuenta, para reenviarle el enlace.
        return $persona + ['vinculado' => true, 'pendiente' => $pendiente, 'mascotas' => $mascotas];
    }

    /** M5 C4b: mínimo de letras para buscar por nombre y cuántos clientes se muestran. */
    public const NOMBRE_MIN = 3;
    public const NOMBRE_RESULTADOS = 8;

    /**
     * M5 C4b: coincidencia parcial por nombre, solo entre los clientes con
     * vínculo activo de la clínica activa. Lo que la clínica ya ve en su lista
     * de clientes, nada de otras clínicas ni de cuentas sin vínculo (RN-111).
     *
     * @return list<array{id_usuario: int, nombre_completo: string, documento: ?string, identificador: string, pendiente: bool}>
     */
    public function buscarPorNombre(string $texto): array
    {
        $texto = ValidadorCuenta::normalizar('nombre_completo', $texto);
        if (mb_strlen($texto) < self::NOMBRE_MIN || mb_strlen($texto) > ValidadorCuenta::NOMBRE_MAX
            || !preg_match('/^[' . ValidadorCuenta::NOMBRE_CARACTERES . ']+$/u', $texto)) {
            return [];
        }
        // «!» escapa los comodines igual en MySQL y en SQLite (la barra invertida no).
        $patron = '%' . strtr($texto, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        $stmt = $this->conn->prepare("SELECT u.id_usuario, u.nombre_completo, u.documento, u.email,
                CASE WHEN " . ActivacionPropietario::pendiente('u') . " THEN 1 ELSE 0 END AS pendiente
            FROM usuarios u
            JOIN propietario_clinica pc ON pc.id_propietario = u.id_usuario AND pc.id_clinica = ? AND pc.estado = 'activo'
            WHERE u.es_super_admin = 0 AND LOWER(u.nombre_completo) LIKE LOWER(?) ESCAPE '!'
            ORDER BY u.nombre_completo
            LIMIT " . self::NOMBRE_RESULTADOS);
        $stmt->execute([$this->clinica(), $patron]);
        $clientes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $clientes[] = [
                'id_usuario' => (int) $fila['id_usuario'],
                'nombre_completo' => (string) $fila['nombre_completo'],
                'documento' => $fila['documento'],
                // Con este dato se trae la ficha con la búsqueda exacta (sus mascotas).
                'identificador' => (string) ($fila['documento'] ?: $fila['email']),
                'pendiente' => (int) $fila['pendiente'] === 1,
            ];
        }
        return $clientes;
    }

    /**
     * M5 C3 (RN-109): al registrar un propietario cuyo documento o correo ya
     * tiene cuenta. Prevalece el correo (como RN-G06). Devuelve lo que la
     * clínica puede saber: si ya es su cliente y con qué dato pedir la
     * confirmación; nunca de quién es la cuenta.
     *
     * @return array{vinculado: bool, identificador: string}|null
     */
    public function existente(string $documento, string $email): ?array
    {
        foreach ([$email, $documento] as $identificador) {
            $persona = $this->personaExacta($identificador);
            if ($persona === null) {
                continue;
            }
            $vinculado = !$this->esPlataforma($persona) && $this->estadoVinculo((int) $persona['id_usuario']) === 'activo';
            return ['vinculado' => $vinculado, 'identificador' => $identificador];
        }
        $usuarios = new Usuario($this->conn);
        // Una cuenta inactiva (no pendiente) tampoco se duplica; se responde como sin vínculo.
        if ($usuarios->existeEmail($email)) {
            return ['vinculado' => false, 'identificador' => $email];
        }
        if ($usuarios->existeDocumento($documento)) {
            return ['vinculado' => false, 'identificador' => $documento];
        }
        return null;
    }

    /**
     * Cuenta con ese documento o correo exacto, activa o de un propietario
     * que la clínica registró y aún no activa (M5 C3); solo para uso interno.
     */
    private function personaExacta(string $identificador): ?array
    {
        $this->clinica();
        $identificador = trim($identificador);
        $esCorreo = filter_var($identificador, FILTER_VALIDATE_EMAIL) !== false;
        if (!$esCorreo && !preg_match('/^\d{5,15}$/D', $identificador)) {
            return null;
        }
        $campo = $esCorreo ? 'email' : 'documento';
        $stmt = $this->conn->prepare("SELECT u.id_usuario, u.nombre_completo, u.tipo_documento, u.documento, u.email, u.telefono, u.es_super_admin,
                CASE WHEN " . ActivacionPropietario::pendiente('u') . " THEN 1 ELSE 0 END AS pendiente
            FROM usuarios u
            WHERE u.$campo = ? AND " . ActivacionPropietario::usable('u'));
        $stmt->execute([$identificador]);
        $persona = $stmt->fetch(PDO::FETCH_ASSOC);
        return $persona === false ? null : $persona;
    }

    private function esPlataforma(array $persona): bool
    {
        return (int) ($persona['es_super_admin'] ?? 0) === 1;
    }

    /** Estado del vínculo de la persona con la clínica activa ('activo', 'inactivo') o null. */
    private function estadoVinculo(int $idPersona): ?string
    {
        $stmt = $this->conn->prepare('SELECT estado FROM propietario_clinica WHERE id_propietario = ? AND id_clinica = ?');
        $stmt->execute([$idPersona, $this->clinica()]);
        $estado = $stmt->fetchColumn();
        return $estado === false ? null : (string) $estado;
    }

    /**
     * RE-T.19.1 (decisión del usuario, M5 C3): la clínica registra al
     * propietario sin aceptación presencial. La cuenta queda pendiente, sin
     * contraseña, consentimiento ni portal, hasta que el titular acepte la
     * política y cree su contraseña desde el enlace (ActivacionPropietario).
     * Un documento o correo con cuenta no se duplica: va por la confirmación
     * del vínculo (RN-109).
     *
     * @return array{id_usuario: int, id: int, token: string}
     */
    public function registrar(array $datos): array
    {
        $clinica = $this->clinica();
        if ($this->existente((string) $datos['documento'], (string) $datos['email']) !== null) {
            throw new InvalidArgumentException(self::MENSAJE_SIN_VINCULO);
        }
        return Transaccion::ejecutar($this->conn, fn (): array => (new ActivacionPropietario($this->conn))->crear($datos, $clinica));
    }

    /** M5 C3: la clínica reenvía el enlace de activación a su cliente pendiente. */
    public function reenviarActivacion(int $idPersona): array
    {
        return (new ActivacionPropietario($this->conn))->reenviar($idPersona, $this->clinica()) + ['clinica' => Contexto::actual()['clinica']];
    }
    /**
     * RN-109: la clínica pide al titular, por correo, confirmar el vínculo.
     * Devuelve null cuando no se envía nada (cuenta de plataforma, RN-G13):
     * quien llama responde lo mismo que si se hubiera enviado.
     */
    public function solicitarVinculo(string $identificador): ?array
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
    public function solicitarVinculoDe(int $idPersona): ?array
    {
        if ($this->estadoVinculo($idPersona) === null) {
            $this->denegar($idPersona);
        }
        $stmt = $this->conn->prepare('SELECT u.id_usuario, u.nombre_completo, u.email, u.es_super_admin FROM usuarios u WHERE u.id_usuario = ? AND ' . ActivacionPropietario::usable('u'));
        $stmt->execute([$idPersona]);
        $persona = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($persona === false) {
            throw new InvalidArgumentException('La cuenta de este cliente no está activa.');
        }
        return $this->crearSolicitud($persona);
    }

    private function crearSolicitud(array $persona): ?array
    {
        // RN-G13 (decisión del usuario, M5 «C1 — Revisión»): el super-administrador
        // no tiene roles de clínica. La clínica recibe la respuesta de siempre,
        // pero no se crea la solicitud ni se envía el correo.
        if ($this->esPlataforma($persona)) {
            (new Auditoria($this->conn))->log(Contexto::idUsuario(), 'OTHER', 'usuarios', (int) $persona['id_usuario'], null, null, 'Solicitud de vínculo a una cuenta de plataforma: no se envió (RN-G13)');
            return null;
        }
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
            // M5 C3 (decisión del usuario): una cuenta que otra clínica registró y aún no se activa también confirma.
            $stmt=$this->conn->prepare("SELECT v.*,u.email AS email_actual,CASE WHEN " . ActivacionPropietario::usable('u') . " THEN 1 ELSE 0 END AS usuario_activo,u.es_super_admin,c.estado AS clinica_estado
                FROM verificaciones_email v JOIN usuarios u ON u.id_usuario=v.id_usuario
                JOIN clinicas c ON c.id_clinica=v.id_clinica_vinculo
                WHERE v.id=? AND v.proposito='vinculo_clinica'" . $bloqueo);
            $stmt->execute([$id]); $fila=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$fila || (int)$fila['used'] !== 0 || strtotime($fila['expires_at'])<=time()
                || (int)$fila['usuario_activo'] !== 1 || $fila['clinica_estado'] !== 'activa'
                || $fila['email'] !== $fila['email_actual'] || !password_verify($token,$fila['token_hash'])) return false;
            // RN-G13: un enlace anterior a la regla tampoco liga a un super-administrador.
            if ((int) $fila['es_super_admin'] === 1) {
                return false;
            }
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
        $desactiva = (string) $datos['estado'] !== '1';
        $vinculos = new VinculoClinica($this->conn);
        if ($desactiva) {
            $this->exigirSinCitasSinResolver($vinculos, $id);
        }
        Transaccion::ejecutar($this->conn, function () use ($id, $datos, $actual, $desactiva, $vinculos): void {
            $this->conn->prepare('UPDATE usuarios SET nombre_completo = ?, telefono = ? WHERE id_usuario = ?')->execute([$datos['nombre_completo'], $datos['telefono'], $id]);
            $nuevos = array_intersect_key($datos, array_flip(['nombre_completo', 'telefono', 'estado']));
            $descripcion = 'Datos de contacto del propietario actualizados';
            if ($desactiva) {
                // RN-115 (decisión del usuario, M5 «C1 — Revisión»): igual que cuando se desvincula el propietario.
                $nuevos['mascotas_desvinculadas'] = $vinculos->desactivar($id, $this->clinica());
                $descripcion = 'La clínica desactivó el vínculo del propietario y sus mascotas (RN-115)';
            }
            (new Auditoria($this->conn))->log(Contexto::idUsuario(), 'UPDATE', 'propietario_clinica', $id, ['nombre_completo' => $actual['nombre_completo'], 'telefono' => $actual['telefono'], 'estado' => $actual['estado']], $nuevos, $descripcion);
        });
    }

    /** RE-5.13.3 (decisión del usuario, C1.1): la clínica tampoco desactiva con citas sin resolver allí. */
    private function exigirSinCitasSinResolver(VinculoClinica $vinculos, int $id): void
    {
        $pendientes = $vinculos->citasSinResolver($id, $this->clinica());
        if ($pendientes === 0) {
            return;
        }
        throw new InvalidArgumentException(sprintf(
            'Tiene %d cita%s sin resolver en esta clínica. Cancélala%s o ciérrala%s antes de desactivar el vínculo.',
            $pendientes,
            $pendientes === 1 ? '' : 's',
            $pendientes === 1 ? '' : 's',
            $pendientes === 1 ? '' : 's'
        ));
    }
    private function denegar(int $id): never
    {
        (new Auditoria($this->conn))->log(Contexto::idUsuario(),'OTHER','propietario_clinica',$id,null,null,'Acceso o cambio de identidad de propietario denegados');
        throw new AccesoDenegado(403,'No tienes acceso a estos datos del propietario.');
    }
}
