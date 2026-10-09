<?php
require_once __DIR__ . '/../helpers/ValidadorCuenta.php';
require_once __DIR__ . '/Usuario.php';
require_once __DIR__ . '/Auditoria.php';
require_once __DIR__ . '/ConsentimientoDatos.php';
require_once __DIR__ . '/VerificacionEmail.php';
require_once __DIR__ . '/PasswordReset.php';
require_once __DIR__ . '/../helpers/PoliticaPassword.php';
require_once __DIR__ . '/../helpers/PoliticaDatos.php';
require_once __DIR__ . '/../helpers/Transaccion.php';
require_once __DIR__ . '/../helpers/GoogleToken.php';

/** D2: operaciones globales de la cuenta; el sujeto viene de sesión o de un enlace secreto. */
final class CuentaTitular
{
    public const ACTIVACION_HORAS = 72;
    private Usuario $usuarios;
    private Auditoria $auditoria;
    private Closure $google;

    public function __construct(private PDO $db, ?callable $google = null)
    {
        $this->usuarios = new Usuario($db);
        $this->auditoria = new Auditoria($db);
        $this->google = Closure::fromCallable($google ?? static function (string $token): ?array {
            $datos = GoogleToken::consultar($token, false);
            return $datos !== null && GoogleToken::motivoDeRechazo($datos, GoogleToken::clientId(), false) === null ? $datos : null;
        });
    }

    private function enlace(int $idUsuario, string $email, string $proposito, int $horas, ?int $clinica = null): array
    {
        $token = bin2hex(random_bytes(32));
        $this->db->prepare('UPDATE verificaciones_email SET used = 1 WHERE id_usuario = ? AND proposito = ? AND used = 0')
            ->execute([$idUsuario, $proposito]);
        $this->db->prepare('INSERT INTO verificaciones_email (id_usuario, email, proposito, id_clinica_vinculo, token_hash, expires_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$idUsuario, $email, $proposito, $clinica, password_hash($token, PASSWORD_DEFAULT), date('Y-m-d H:i:s', time() + $horas * 3600)]);
        return ['id' => (int) $this->db->lastInsertId(), 'token' => $token];
    }

    /** RE-T.19.1: excepción aprobada para personal, sin contraseña ni consentimiento aún. */
    public function crearPersonal(array $datos, int $clinica): array
    {
        return Transaccion::ejecutar($this->db, function () use ($datos, $clinica): array {
            $datos['password'] = null;
            $id = $this->usuarios->crear($datos);
            $this->db->prepare('UPDATE usuarios SET estado = 0 WHERE id_usuario = ?')->execute([$id]);
            $this->usuarios->asignarRolEnClinica($id, $clinica, (int) $datos['id_rol']);
            $enlace = $this->enlace($id, $datos['email'], 'activacion_personal', self::ACTIVACION_HORAS, $clinica);
            $this->auditoria->log(Contexto::idUsuario(), 'INSERT', 'usuarios', $id, null, ['estado' => 0], 'Personal pendiente de aceptación y activación (RE-T.19.1)', $clinica);
            return ['id_usuario' => $id, 'enlace' => $enlace];
        });
    }

    public function leerEnlace(int $id, string $token, string $proposito): ?array
    {
        $fila = (new VerificacionEmail($this->db))->buscarPorId($id);
        return $fila !== null && $fila['proposito'] === $proposito && (int) $fila['used'] === 0
            && strtotime($fila['expires_at']) > time() && password_verify($token, $fila['token_hash']) ? $fila : null;
    }

    /** RE-T.14.2: revoca la clave anterior; solo el titular conoce la nueva. */
    public function restablecerPersonal(int $id, int $clinica): array
    {
        return Transaccion::ejecutar($this->db, function () use ($id, $clinica): array {
            $this->bloquearUsuario($id);
            $usuario = $this->usuarios->buscarPorId($id);
            if ($usuario === null || !$this->usuarios->identidadEditableEnClinica($id, $clinica)) {
                throw new InvalidArgumentException('No se puede restablecer esta cuenta.');
            }
            $pendiente = $this->db->prepare("SELECT 1 FROM verificaciones_email WHERE id_usuario = ? AND proposito = 'activacion_personal' AND used = 0");
            $pendiente->execute([$id]);
            if ((int) $usuario['estado'] === 0 && $pendiente->fetchColumn()) {
                return ['accion' => 'activar_personal', 'horas' => self::ACTIVACION_HORAS,
                    'enlace' => $this->enlace($id, $usuario['email'], 'activacion_personal', self::ACTIVACION_HORAS, $clinica)];
            }
            if ((int) $usuario['estado'] !== 1) {
                throw new InvalidArgumentException('La cuenta está inactiva.');
            }
            $this->db->prepare('UPDATE usuarios SET password = NULL, debe_cambiar_password = 1 WHERE id_usuario = ?')->execute([$id]);
            $this->db->prepare('UPDATE password_resets SET used = 1 WHERE id_usuario = ?')->execute([$id]);
            $token = bin2hex(random_bytes(32));
            $idEnlace = (new PasswordReset($this->db))->createToken($id, $usuario['email'], password_hash($token, PASSWORD_DEFAULT), date('Y-m-d H:i:s', time() + 86400));
            $this->auditoria->log(Contexto::idUsuario(), 'UPDATE', 'usuarios', $id, null, ['debe_cambiar_password' => 1], 'Restablecimiento por enlace: contraseña anterior invalidada (RE-T.14.2)', $clinica);
            return ['accion' => 'reset_password', 'horas' => 24, 'enlace' => ['id' => $idEnlace, 'token' => $token]];
        });
    }

    private function consumir(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE verificaciones_email SET used = 1 WHERE id = ? AND used = 0 AND expires_at > ?');
        $stmt->execute([$id, date('Y-m-d H:i:s')]);
        if ($stmt->rowCount() !== 1) {
            throw new InvalidArgumentException('El enlace no es válido o ya fue utilizado.');
        }
    }

    private function bloquearUsuario(int $id): void
    {
        // La FK de los vínculos toma el mismo padre: impide insertar otro vínculo durante la limpieza.
        $bloqueo = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare('SELECT id_usuario FROM usuarios WHERE id_usuario = ?' . $bloqueo);
        $stmt->execute([$id]);
        $stmt->fetchColumn();
    }

    public function activar(int $id, string $token, array $datos, string $ip): void
    {
        $fila = $this->leerEnlace($id, $token, 'activacion_personal');
        if ($fila === null) {
            throw new InvalidArgumentException('El enlace no es válido o ya fue utilizado.');
        }
        $usuario = $this->usuarios->buscarPorId((int) $fila['id_usuario']);
        if ($usuario === null || (int) $usuario['estado'] !== 0 || (int) $usuario['tiene_password'] !== 0
            || $usuario['email'] !== $fila['email']) {
            throw new InvalidArgumentException('El enlace no es válido o ya fue utilizado.');
        }
        if (($datos['acepta_datos'] ?? '') !== '1') {
            throw new InvalidArgumentException('Debes aceptar la política de tratamiento de datos.');
        }
        $password = (string) ($datos['password'] ?? '');
        $error = PoliticaPassword::validar($password, [$usuario['documento'], $usuario['nombre_completo'], $usuario['email']]);
        if ($error !== null || $password !== ($datos['confirm_password'] ?? '')) {
            throw new InvalidArgumentException($error ?? 'Las contraseñas no coinciden.');
        }
        Transaccion::ejecutar($this->db, function () use ($id, $usuario, $password, $ip, $fila): void {
            $this->consumir($id);
            $this->bloquearUsuario((int) $usuario['id_usuario']);
            $stmt = $this->db->prepare('UPDATE usuarios SET password = ?, estado = 1, debe_cambiar_password = 0 WHERE id_usuario = ? AND estado = 0 AND password IS NULL');
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $usuario['id_usuario']]);
            if ($stmt->rowCount() !== 1) {
                throw new InvalidArgumentException('La cuenta ya no está pendiente.');
            }
            (new ConsentimientoDatos($this->db))->registrar((int) $usuario['id_usuario'], PoliticaDatos::MEDIO_ALTA_PERSONAL, $ip);
            $this->auditoria->log((int) $usuario['id_usuario'], 'UPDATE', 'usuarios', $usuario['id_usuario'], ['estado' => 0], ['estado' => 1], 'Personal activado por el titular (RE-T.19.1)', (int) $fila['id_clinica_vinculo']);
        });
    }

    public function confirmarIdentidad(int $id, array $datos): array
    {
        $usuario = $this->usuarios->buscarPorId($id);
        if ($usuario === null || (int) $usuario['estado'] !== 1) {
            throw new InvalidArgumentException('No se pudo confirmar tu identidad.');
        }
        if ($this->usuarios->verificarPassword($id, (string) ($datos['password_actual'] ?? ''))) {
            return $usuario;
        }
        $token = (string) ($datos['access_token'] ?? '');
        $google = $token !== '' ? ($this->google)($token) : null;
        if ($google !== null && (int) $usuario['tiene_google'] === 1
            && mb_strtolower($google['email'] ?? '') === mb_strtolower($usuario['email'])) {
            return $usuario;
        }
        throw new InvalidArgumentException('No se pudo confirmar tu identidad.');
    }

    public function solicitarCorreo(int $id, array $datos): array
    {
        $usuario = $this->confirmarIdentidad($id, $datos);
        if ((int) $usuario['tiene_password'] !== 1) {
            throw new InvalidArgumentException('Crea una contraseña antes de cambiar el correo.');
        }
        $email = mb_strtolower(trim((string) ($datos['email'] ?? '')));
        if (ValidadorCuenta::correo($email) !== null || strlen($email) > 255 || $email === $usuario['email']) {
            throw new InvalidArgumentException('Escribe un correo nuevo válido.');
        }
        if ($this->usuarios->existeEmail($email, $id)) {
            throw new InvalidArgumentException('No puedes usar ese correo.');
        }
        $enlace = Transaccion::ejecutar($this->db, fn () => $this->enlace($id, $email, 'cambio_correo', 24));
        return ['email' => $email, 'nombre' => $usuario['nombre_completo'], 'enlace' => $enlace];
    }

    /** RE-T.5.7: consume una sola vez y revalida unicidad al confirmar. */
    public function confirmarCorreo(int $id, string $token): array
    {
        $fila = $this->leerEnlace($id, $token, 'cambio_correo');
        if ($fila === null) {
            throw new InvalidArgumentException('El enlace no es válido o ya fue utilizado.');
        }
        return Transaccion::ejecutar($this->db, function () use ($id, $fila): array {
            $this->consumir($id);
            $this->bloquearUsuario((int) $fila['id_usuario']);
            $usuario = $this->usuarios->buscarPorId((int) $fila['id_usuario']);
            if ($usuario === null || (int) $usuario['estado'] !== 1 || (int) $usuario['tiene_password'] !== 1
                || $this->usuarios->existeEmail($fila['email'], (int) $fila['id_usuario'])) {
                throw new InvalidArgumentException('No se pudo aplicar el cambio de correo.');
            }
            $this->db->prepare('UPDATE usuarios SET email = ?, google_uid = NULL WHERE id_usuario = ?')
                ->execute([$fila['email'], $fila['id_usuario']]);
            $this->db->prepare('UPDATE password_resets SET used = 1 WHERE id_usuario = ?')->execute([$fila['id_usuario']]);
            $this->auditoria->log((int) $fila['id_usuario'], 'UPDATE', 'usuarios', $fila['id_usuario'], ['email' => $usuario['email']], ['email' => $fila['email']], 'Correo cambiado y Google desvinculado (RN-G23)', null);
            return ['anterior' => $usuario['email'], 'nombre' => $usuario['nombre_completo']];
        });
    }

    public function cambiarDocumento(int $id, array $datos): array
    {
        $usuario = $this->confirmarIdentidad($id, $datos);
        $documento = trim((string) ($datos['documento'] ?? ''));
        $tipo = (string) ($datos['tipo_documento'] ?? '');
        if (ValidadorCuenta::documento($documento) !== null || !in_array($tipo, Usuario::TIPOS_DOCUMENTO, true)) {
            throw new InvalidArgumentException('Elige un tipo válido y un documento de 5 a 15 dígitos.');
        }
        if ($this->usuarios->existeDocumento($documento, $id)) {
            $this->registrarCasoDocumento($id, $documento);
            throw new InvalidArgumentException('El documento ya está registrado. Creamos un caso para soporte.');
        }
        try {
            Transaccion::ejecutar($this->db, function () use ($id, $tipo, $documento, $usuario): void {
                $this->bloquearUsuario($id);
                $this->db->prepare('UPDATE usuarios SET tipo_documento = ?, documento = ? WHERE id_usuario = ?')->execute([$tipo, $documento, $id]);
                $this->auditoria->log($id, 'UPDATE', 'usuarios', $id, ['documento' => $usuario['documento'], 'tipo_documento' => $usuario['tipo_documento']], ['documento' => $documento, 'tipo_documento' => $tipo], 'Documento corregido por el titular (RN-G24)', null);
            });
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            // Otra solicitud pudo ocupar el documento después de comprobar su disponibilidad.
            $this->registrarCasoDocumento($id, $documento);
            throw new InvalidArgumentException('El documento ya está registrado. Creamos un caso para soporte.');
        }
        return $usuario;
    }

    private function registrarCasoDocumento(int $id, string $documento): void
    {
        $descripcion = 'El titular solicitó el documento ' . $documento . ' ya asociado a otra cuenta (RN-G24).';
        Transaccion::ejecutar($this->db, function () use ($id, $descripcion): void {
            $this->bloquearUsuario($id);
            $this->db->prepare("INSERT INTO casos_soporte (tipo, id_usuario, descripcion)
                SELECT 'documento_duplicado', ?, ? WHERE NOT EXISTS
                (SELECT 1 FROM casos_soporte WHERE tipo = 'documento_duplicado' AND id_usuario = ? AND descripcion = ? AND estado = 'abierto')")
                ->execute([$id, $descripcion, $id, $descripcion]);
        });
    }

    /** Excepción del sistema: solo altas pendientes sin otros vínculos ni consentimiento. */
    public function limpiarPendientes(): int
    {
        $stmt = $this->db->prepare("SELECT v.* FROM verificaciones_email v JOIN usuarios u ON u.id_usuario = v.id_usuario
            WHERE v.proposito = 'activacion_personal' AND v.used = 0 AND v.expires_at <= ?
            AND u.estado = 0 AND u.password IS NULL AND u.google_uid IS NULL AND u.es_super_admin = 0");
        $stmt->execute([date('Y-m-d H:i:s')]);
        $borradas = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            try {
                $borradas += $this->limpiarUna($fila);
            } catch (Throwable $e) {
                // D2.1: una cuenta que no se puede borrar (por ejemplo, una FK que
                // la referencia) se deja anotada y la limpieza sigue con las demás.
                error_log(sprintf('D2.1 limpieza: no se eliminó la cuenta pendiente %d (%s)', (int) $fila['id_usuario'], $e->getMessage()));
            }
        }
        return $borradas;
    }

    /** Elimina una alta vencida dentro de su propia transacción; 0 si debe conservarse. */
    private function limpiarUna(array $fila): int
    {
        return Transaccion::ejecutar($this->db, function () use ($fila): int {
            $id = (int) $fila['id_usuario'];
            $this->bloquearUsuario($id);
            $otro = $this->db->prepare('SELECT 1 FROM usuario_clinica WHERE id_usuario = ? AND id_clinica <> ? UNION SELECT 1 FROM propietario_clinica WHERE id_propietario = ? UNION SELECT 1 FROM consentimientos_datos WHERE id_usuario = ?');
            $otro->execute([$id, $fila['id_clinica_vinculo'], $id, $id]);
            if ($otro->fetchColumn()) {
                return 0;
            }
            // Una reemisión vigente impide borrar una invitación que el titular aún puede usar.
            $vigente = $this->db->prepare("SELECT 1 FROM verificaciones_email WHERE id_usuario = ? AND proposito = 'activacion_personal' AND used = 0 AND expires_at > ?");
            $vigente->execute([$id, date('Y-m-d H:i:s')]);
            if ($vigente->fetchColumn()) {
                return 0;
            }
            $this->db->prepare('DELETE FROM password_resets WHERE id_usuario = ?')->execute([$id]);
            $this->db->prepare('DELETE FROM verificaciones_email WHERE id_usuario = ?')->execute([$id]);
            $this->db->prepare('DELETE FROM usuario_clinica WHERE id_usuario = ? AND id_clinica = ?')->execute([$id, $fila['id_clinica_vinculo']]);
            // Se conservan los fallos de acceso; el registro_id sigue identificando el alta vencida.
            $this->db->prepare('UPDATE auditoria_sistema SET id_usuario = NULL WHERE id_usuario = ?')->execute([$id]);
            $stmt = $this->db->prepare('DELETE FROM usuarios WHERE id_usuario = ? AND estado = 0 AND password IS NULL');
            $stmt->execute([$id]);
            $total = $stmt->rowCount();
            if ($total !== 1) {
                throw new RuntimeException('La cuenta dejó de estar pendiente durante la limpieza.');
            }
            $this->auditoria->log(null, 'DELETE', 'usuarios', $id, ['estado' => 'pendiente'], null, 'Alta de personal vencida eliminada sin otros vínculos (RE-T.19.1)', (int) $fila['id_clinica_vinculo']);
            return $total;
        });
    }
}
