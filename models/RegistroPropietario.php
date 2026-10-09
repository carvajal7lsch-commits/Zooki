<?php
require_once __DIR__ . '/../helpers/ValidadorCuenta.php';
require_once __DIR__ . '/Usuario.php';
require_once __DIR__ . '/Auditoria.php';
require_once __DIR__ . '/VerificacionEmail.php';
require_once __DIR__ . '/ConsentimientoDatos.php';
require_once __DIR__ . '/../helpers/PoliticaDatos.php';
require_once __DIR__ . '/../helpers/PoliticaPassword.php';
require_once __DIR__ . '/../helpers/ValidadorTelefono.php';
require_once __DIR__ . '/../helpers/Transaccion.php';

/**
 * HU-5.8, HU-T.18 y HU-T.19 — El propietario se registra como identidad
 * global, siempre en el contexto de una clínica que eligió (RN-109).
 *
 * Decide sin enviar correos ni tocar la sesión: devuelve lo que pasó y los
 * enlaces que hay que enviar, y AuthController los envía. Así se prueba sin
 * servidor web ni SMTP.
 *
 * - Sin la aceptación expresa de la política no se guarda nada (RN-G19).
 * - El correo es único (RN-G06): si ya existe no se crea otra cuenta; se
 *   liga a la clínica cuando su dueño lo confirma por correo (RN-109).
 * - Una cuenta nueva por formulario no entra hasta verificar el correo y
 *   solo entonces queda vinculada a la clínica elegida (RN-G11).
 */
final class RegistroPropietario
{
    public const VIGENCIA_HORAS = 24;

    private Usuario $usuarios;
    private VerificacionEmail $verificaciones;
    private Auditoria $auditoria;

    public function __construct(private PDO $conn)
    {
        $this->usuarios = new Usuario($conn);
        $this->verificaciones = new VerificacionEmail($conn);
        $this->auditoria = new Auditoria($conn);
    }

    /** RE-5.8.1: la lista de clínicas en que se puede registrar (solo las activas). */
    public function clinicasDisponibles(): array
    {
        return $this->conn->query("SELECT id_clinica, nombre, direccion FROM clinicas WHERE estado = 'activa' ORDER BY nombre")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Registro con formulario. Resultados:
     *  · nueva      cuenta creada, pendiente de verificar el correo
     *  · pendiente  el correo ya tenía una cuenta sin verificar: enlace nuevo
     *  · existente  el correo ya tiene cuenta: enlace para confirmar el vínculo
     *  · vinculada  ya estaba vinculado a esa clínica: que inicie sesión
     *
     * `enlace` trae id y token del correo que hay que enviar, o null.
     */
    public function conFormulario(array $entrada, string $ip): array
    {
        // RE-T.19.1: lo primero; sin aceptación no se escribe nada.
        $this->exigirAceptacion($entrada);
        $clinica = $this->clinicaElegida($entrada['id_clinica'] ?? null);
        $datos = $this->validarFormulario($entrada);

        $existente = $this->usuarios->buscarPorEmail($datos['email']);
        if ($existente === null) {
            return $this->crearConFormulario($datos, $clinica, $ip);
        }
        return $this->registroDeCuentaExistente($existente, $clinica);
    }

    /**
     * RN-G11 — Confirma el correo de un autorregistro y liga la cuenta a la
     * clínica que eligió. Devuelve la persona, o null si el enlace no sirve
     * (no se distingue por qué, para no confirmar ids).
     *
     * @return array{usuario: array, clinica: ?string}|null
     */
    public function verificarRegistro(int $id, string $token): ?array
    {
        $verificacion = $id > 0 ? $this->verificaciones->buscarPorId($id) : null;
        if ($verificacion === null
            || ($verificacion['proposito'] ?? 'registro') !== 'registro'
            || (int) $verificacion['used'] === 1
            || strtotime($verificacion['expires_at']) <= time()
            || !password_verify($token, $verificacion['token_hash'])) {
            return null;
        }

        $idUsuario = (int) $verificacion['id_usuario'];
        $idClinica = $verificacion['id_clinica_vinculo'] !== null ? (int) $verificacion['id_clinica_vinculo'] : null;
        $clinica = Transaccion::ejecutar($this->conn, function () use ($id, $idUsuario, $idClinica): ?string {
            $this->verificaciones->marcarUsada($id);
            $this->verificaciones->invalidarDe($idUsuario);
            $this->auditoria->log($idUsuario, 'UPDATE', 'usuarios', $idUsuario, null, null, 'Correo electronico confirmado', null);
            return $this->ligarAClinica($idUsuario, $idClinica, 'Propietario registrado y vinculado al confirmar su correo (HU-5.8)');
        });

        $usuario = $this->usuarios->buscarPorId($idUsuario);
        return $usuario === null ? null : ['usuario' => $usuario, 'clinica' => $clinica];
    }

    /**
     * RE-T.18.2 — Cuenta nueva con Google: exige la aceptación ANTES de crear
     * la identidad. Queda vinculada a la clínica elegida, con el perfil
     * incompleto hasta registrar documento y teléfono (RN-G20).
     *
     * @param array{email: string, nombre_completo: string, google_uid: ?string} $google datos ya validados contra Google
     */
    public function conGoogle(array $google, array $entrada, string $ip): int
    {
        $this->exigirAceptacion($entrada);
        $clinica = $this->clinicaElegida($entrada['id_clinica'] ?? null);
        if ($this->usuarios->buscarPorEmail($google['email']) !== null) {
            throw new InvalidArgumentException('Ese correo ya tiene cuenta. Vuelve a entrar con Google.');
        }

        return Transaccion::ejecutar($this->conn, function () use ($google, $clinica, $ip): int {
            $idUsuario = $this->usuarios->crear([
                'nombre_completo' => mb_substr(trim($google['nombre_completo']) ?: $google['email'], 0, 100),
                'email' => $google['email'],
                'password' => null,
                'google_uid' => $google['google_uid'] ?? null,
                'perfil_completo' => 0,
            ]);
            (new ConsentimientoDatos($this->conn))->registrar($idUsuario, PoliticaDatos::MEDIO_GOOGLE, $ip);
            $this->auditoria->log($idUsuario, 'INSERT', 'usuarios', $idUsuario, null, null, 'Registro con Google (RN-G20), perfil por completar', null);
            $this->ligarAClinica($idUsuario, (int) $clinica['id_clinica'], 'Propietario registrado con Google y vinculado (HU-T.18)');
            return $idUsuario;
        });
    }

    /**
     * RN-G21 — Google entra a una cuenta que ya existe. Se liga Google a esa
     * cuenta. Si estaba pendiente de verificar el correo, queda verificada,
     * se liga a la clínica que había elegido, pierde la contraseña (pudo
     * crearla otra persona con ese correo) y vuelve a pedir documento y
     * teléfono. Una cuenta verificada conserva su contraseña.
     */
    public function vincularGoogle(array $usuario, ?string $googleUid): array
    {
        $idUsuario = (int) $usuario['id_usuario'];
        Transaccion::ejecutar($this->conn, function () use ($idUsuario, $googleUid): void {
            if ($googleUid !== null && $googleUid !== '' && $this->usuarios->buscarPorGoogleUid($googleUid) === null) {
                $this->conn->prepare('UPDATE usuarios SET google_uid = ? WHERE id_usuario = ? AND google_uid IS NULL')
                    ->execute([$googleUid, $idUsuario]);
            }
            if (!$this->verificaciones->hayPendiente($idUsuario)) {
                return;
            }
            $idClinica = $this->clinicaDelRegistroPendiente($idUsuario);
            $this->verificaciones->invalidarDe($idUsuario);
            $this->conn->prepare('UPDATE usuarios SET password = NULL, perfil_completo = 0 WHERE id_usuario = ?')->execute([$idUsuario]);
            $this->auditoria->log($idUsuario, 'UPDATE', 'usuarios', $idUsuario, null, ['perfil_completo' => 0], 'Correo verificado con Google (RN-G21): contraseña anulada y perfil por confirmar', null);
            $this->ligarAClinica($idUsuario, $idClinica, 'Propietario vinculado al verificar su correo con Google (RN-G21)');
        });
        return $this->usuarios->buscarPorId($idUsuario) ?? $usuario;
    }

    /** RE-T.18.3 — Documento y teléfono de un perfil incompleto. */
    public function completarPerfil(int $idUsuario, array $entrada): void
    {
        $tipo = trim((string) ($entrada['tipo_documento'] ?? ''));
        $documento = trim((string) ($entrada['documento'] ?? ''));
        $telefono = ValidadorTelefono::normalizar((string) ($entrada['telefono'] ?? ''));
        if (!in_array($tipo, Usuario::TIPOS_DOCUMENTO, true)) {
            throw new InvalidArgumentException('Elige el tipo de documento.');
        }
        if (ValidadorCuenta::documento($documento) !== null) {
            throw new InvalidArgumentException('El documento debe tener entre 5 y 15 dígitos.');
        }
        $this->exigirTelefono($telefono);
        $otro = $this->usuarios->buscarPorDocumento($documento);
        if ($otro !== null && (int) $otro['id_usuario'] !== $idUsuario) {
            throw new InvalidArgumentException('Ese documento ya está registrado en otra cuenta.');
        }

        Transaccion::ejecutar($this->conn, function () use ($idUsuario, $tipo, $documento, $telefono): void {
            $this->conn->prepare('UPDATE usuarios SET tipo_documento = ?, documento = ?, telefono = ?, perfil_completo = 1 WHERE id_usuario = ?')
                ->execute([$tipo, $documento, $telefono, $idUsuario]);
            $this->auditoria->log($idUsuario, 'UPDATE', 'usuarios', $idUsuario, null, ['perfil_completo' => 1], 'Perfil completado: documento y teléfono (RE-T.18.3)', null);
        });
    }

    // ── Apoyo ───────────────────────────────────────────────────────────

    private function exigirAceptacion(array $entrada): void
    {
        if ((string) ($entrada['acepta_datos'] ?? '') !== '1') {
            throw new InvalidArgumentException('Debes aceptar la política de tratamiento de datos para crear la cuenta.');
        }
    }

    private function clinicaElegida($idClinica): array
    {
        $id = ctype_digit((string) $idClinica) ? (int) $idClinica : 0;
        $stmt = $this->conn->prepare("SELECT id_clinica, nombre FROM clinicas WHERE id_clinica = ? AND estado = 'activa'");
        $stmt->execute([$id]);
        $clinica = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$clinica) {
            throw new InvalidArgumentException('Elige una clínica de la lista.');
        }
        return $clinica;
    }

    private function validarFormulario(array $entrada): array
    {
        $datos = [
            'tipo_documento' => trim((string) ($entrada['tipo_documento'] ?? '')),
            'documento' => trim((string) ($entrada['documento'] ?? '')),
            'nombre_completo' => preg_replace('/\s+/u', ' ', trim((string) ($entrada['nombre_completo'] ?? ''))),
            'telefono' => ValidadorTelefono::normalizar((string) ($entrada['telefono'] ?? '')),
            'email' => mb_strtolower(trim((string) ($entrada['email'] ?? ''))),
            'password' => (string) ($entrada['password'] ?? ''),
        ];
        if (!in_array($datos['tipo_documento'], Usuario::TIPOS_DOCUMENTO, true)) {
            throw new InvalidArgumentException('Elige el tipo de documento.');
        }
        if (ValidadorCuenta::documento($datos['documento']) !== null) {
            throw new InvalidArgumentException('El documento debe tener entre 5 y 15 dígitos.');
        }
        if (mb_strlen($datos['nombre_completo']) < 3 || mb_strlen($datos['nombre_completo']) > 100) {
            throw new InvalidArgumentException('El nombre completo debe tener entre 3 y 100 caracteres.');
        }
        $this->exigirTelefono($datos['telefono']);
        if (ValidadorCuenta::correo($datos['email']) !== null || mb_strlen($datos['email']) > 100) {
            throw new InvalidArgumentException('El correo electrónico no tiene un formato válido.');
        }
        if ($datos['password'] !== (string) ($entrada['confirm_password'] ?? '')) {
            throw new InvalidArgumentException('Las contraseñas no coinciden.');
        }
        $motivo = PoliticaPassword::validar($datos['password'], [$datos['documento'], $datos['nombre_completo'], $datos['email']]);
        if ($motivo !== null) {
            throw new InvalidArgumentException($motivo);
        }
        return $datos;
    }

    private function exigirTelefono(string $telefono): void
    {
        if (!ValidadorTelefono::esValido($telefono)) {
            throw new InvalidArgumentException(ValidadorTelefono::MENSAJE);
        }
    }

    private function crearConFormulario(array $datos, array $clinica, string $ip): array
    {
        if ($this->usuarios->buscarPorDocumento($datos['documento']) !== null) {
            throw new InvalidArgumentException('El documento ya está registrado. Si es tuyo, inicia sesión o recupera tu contraseña.');
        }

        return Transaccion::ejecutar($this->conn, function () use ($datos, $clinica, $ip): array {
            $idUsuario = $this->usuarios->crear([
                'documento' => $datos['documento'],
                'tipo_documento' => $datos['tipo_documento'],
                'nombre_completo' => $datos['nombre_completo'],
                'telefono' => $datos['telefono'],
                'email' => $datos['email'],
                'password' => password_hash($datos['password'], PASSWORD_DEFAULT),
            ]);
            (new ConsentimientoDatos($this->conn))->registrar($idUsuario, PoliticaDatos::MEDIO_FORMULARIO, $ip);
            // Sin clínica: la clínica no ve al propietario hasta que confirme el correo (RE-5.8.5).
            $this->auditoria->log($idUsuario, 'INSERT', 'usuarios', $idUsuario, null, null, 'Autorregistro de propietario, pendiente de verificar el correo', null);
            $enlace = $this->nuevoEnlace($idUsuario, $datos['email'], (int) $clinica['id_clinica'], 'registro');
            return $this->resultado('nueva', $idUsuario, $datos['email'], $datos['nombre_completo'], $clinica, $enlace);
        });
    }

    /** RE-5.8.2/3: el correo ya existe; nunca se toca la cuenta ni su contraseña. */
    private function registroDeCuentaExistente(array $persona, array $clinica): array
    {
        $idUsuario = (int) $persona['id_usuario'];
        $email = (string) $persona['email'];
        $nombre = (string) $persona['nombre_completo'];

        // El super-administrador no se combina con otros roles (RE-T.17.5) y
        // una cuenta inactiva no se vincula. Se responde igual, sin enviar nada.
        if ((int) $persona['es_super_admin'] === 1 || (int) $persona['estado'] !== 1) {
            return $this->resultado('existente', $idUsuario, $email, $nombre, $clinica, null);
        }

        // RN-G11: sigue sin verificar; enlace nuevo con la clínica de ahora.
        if ($this->verificaciones->hayPendiente($idUsuario)) {
            return Transaccion::ejecutar($this->conn, function () use ($idUsuario, $email, $nombre, $clinica): array {
                $this->verificaciones->invalidarDe($idUsuario);
                $enlace = $this->nuevoEnlace($idUsuario, $email, (int) $clinica['id_clinica'], 'registro');
                return $this->resultado('pendiente', $idUsuario, $email, $nombre, $clinica, $enlace);
            });
        }

        $stmt = $this->conn->prepare('SELECT estado FROM propietario_clinica WHERE id_propietario = ? AND id_clinica = ?');
        $stmt->execute([$idUsuario, (int) $clinica['id_clinica']]);
        if ($stmt->fetchColumn() === 'activo') {
            return $this->resultado('vinculada', $idUsuario, $email, $nombre, $clinica, null);
        }

        $enlace = $this->nuevoEnlace($idUsuario, $email, (int) $clinica['id_clinica'], 'vinculo_clinica');
        $this->auditoria->log($idUsuario, 'INSERT', 'verificaciones_email', $enlace['id'], null, null, 'Confirmación de vínculo solicitada desde el registro (RN-109)', null);
        return $this->resultado('existente', $idUsuario, $email, $nombre, $clinica, $enlace);
    }

    /** @return array{id: int, token: string} */
    private function nuevoEnlace(int $idUsuario, string $email, int $idClinica, string $proposito): array
    {
        $token = bin2hex(random_bytes(32));
        $expira = date('Y-m-d H:i:s', time() + self::VIGENCIA_HORAS * 3600);
        $stmt = $this->conn->prepare('INSERT INTO verificaciones_email (id_usuario, email, token_hash, expires_at, id_clinica_vinculo, proposito)
            VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$idUsuario, $email, password_hash($token, PASSWORD_DEFAULT), $expira, $idClinica, $proposito]);
        return ['id' => (int) $this->conn->lastInsertId(), 'token' => $token];
    }

    private function resultado(string $resultado, int $idUsuario, string $email, string $nombre, array $clinica, ?array $enlace): array
    {
        return [
            'resultado' => $resultado,
            'id_usuario' => $idUsuario,
            'email' => $email,
            'nombre' => $nombre,
            'clinica' => (string) $clinica['nombre'],
            'enlace' => $enlace,
        ];
    }

    /** Clínica que eligió en el registro todavía sin verificar (la más reciente). */
    private function clinicaDelRegistroPendiente(int $idUsuario): ?int
    {
        $stmt = $this->conn->prepare("SELECT id_clinica_vinculo FROM verificaciones_email
            WHERE id_usuario = ? AND proposito = 'registro' AND used = 0 ORDER BY id DESC LIMIT 1");
        $stmt->execute([$idUsuario]);
        $id = $stmt->fetchColumn();
        return $id === false || $id === null ? null : (int) $id;
    }

    /** Crea o reactiva el vínculo si la clínica sigue activa; devuelve su nombre. */
    private function ligarAClinica(int $idUsuario, ?int $idClinica, string $descripcion): ?string
    {
        if ($idClinica === null) {
            return null;
        }
        $stmt = $this->conn->prepare("SELECT nombre FROM clinicas WHERE id_clinica = ? AND estado = 'activa'");
        $stmt->execute([$idClinica]);
        $nombre = $stmt->fetchColumn();
        if ($nombre === false) {
            return null;
        }

        $stmt = $this->conn->prepare('SELECT estado FROM propietario_clinica WHERE id_propietario = ? AND id_clinica = ?');
        $stmt->execute([$idUsuario, $idClinica]);
        $estado = $stmt->fetchColumn();
        if ($estado === false) {
            $this->conn->prepare("INSERT INTO propietario_clinica (id_propietario, id_clinica, estado) VALUES (?, ?, 'activo')")->execute([$idUsuario, $idClinica]);
        } elseif ($estado !== 'activo') {
            $this->conn->prepare("UPDATE propietario_clinica SET estado = 'activo' WHERE id_propietario = ? AND id_clinica = ?")->execute([$idUsuario, $idClinica]);
        }
        // RE-5.8.5: el alta queda en la auditoría de la clínica que la recibe.
        $this->auditoria->log($idUsuario, 'INSERT', 'propietario_clinica', $idUsuario, ['estado' => $estado === false ? null : $estado], ['estado' => 'activo'], $descripcion, $idClinica);
        return (string) $nombre;
    }
}
