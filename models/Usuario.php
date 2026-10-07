<?php
require_once __DIR__ . '/../helpers/Roles.php';
require_once __DIR__ . '/../helpers/Contexto.php';

/**
 * Identidad de las personas (MER §2) y sus roles por clínica.
 *
 * La clave es id_usuario. El documento y el correo son únicos en la plataforma
 * pero son datos corregibles (RN-G06): nada se relaciona por ellos. Los roles
 * no viven en la persona: el de personal está en usuario_clinica, el de
 * propietario en propietario_clinica y el de plataforma en es_super_admin.
 *
 * Ninguna lectura devuelve la columna password salvo buscarParaLogin(), que
 * solo usa el inicio de sesión (T-03).
 */
class Usuario
{
    private const COLUMNAS = 'u.id_usuario, u.documento, u.tipo_documento, u.nombre_completo, u.telefono, u.email,
        u.estado, u.debe_cambiar_password, u.es_super_admin, u.perfil_completo, u.fecha_registro,
        CASE WHEN u.password IS NULL THEN 0 ELSE 1 END AS tiene_password,
        CASE WHEN u.google_uid IS NULL THEN 0 ELSE 1 END AS tiene_google';

    public const NOMBRE_MAX = 200;
    public const DOCUMENTO_MAX = 20;
    public const TIPOS_DOCUMENTO = ['CC', 'CE', 'TI', 'PP', 'NIT'];

    private PDO $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // ── Identidad ─────────────────────────────────────────────────────────

    public function buscarPorId(int $idUsuario): ?array
    {
        return $this->uno('SELECT ' . self::COLUMNAS . ' FROM usuarios u WHERE u.id_usuario = ?', [$idUsuario]);
    }

    public function buscarPorDocumento(string $documento): ?array
    {
        return $this->uno('SELECT ' . self::COLUMNAS . ' FROM usuarios u WHERE u.documento = ?', [$documento]);
    }

    public function buscarPorEmail(string $email): ?array
    {
        return $this->uno('SELECT ' . self::COLUMNAS . ' FROM usuarios u WHERE u.email = ?', [$email]);
    }

    /**
     * RE-T.1.1 — La persona entra con su documento o con su correo. Es la
     * única lectura que trae el hash de la contraseña (NULL en las cuentas
     * de Google que no han creado una).
     */
    public function buscarParaLogin(string $identificador): ?array
    {
        $identificador = trim($identificador);
        if ($identificador === '') {
            return null;
        }
        $columna = str_contains($identificador, '@') ? 'email' : 'documento';
        return $this->uno('SELECT ' . self::COLUMNAS . ', u.password FROM usuarios u WHERE u.' . $columna . ' = ?', [$identificador]);
    }

    /** RN-G06: el documento es único en la plataforma. */
    public function existeDocumento(string $documento, ?int $excepto = null): bool
    {
        $fila = $this->buscarPorDocumento($documento);
        return $fila !== null && (int) $fila['id_usuario'] !== $excepto;
    }

    /** RN-G06: el correo es único en la plataforma. */
    public function existeEmail(string $email, ?int $excepto = null): bool
    {
        $fila = $this->buscarPorEmail($email);
        return $fila !== null && (int) $fila['id_usuario'] !== $excepto;
    }

    /**
     * Crea la identidad, sin ningún rol. `password` es el hash, o null en una
     * cuenta de Google que todavía no tiene contraseña (MER §2).
     *
     * @return int id_usuario nuevo
     */
    public function crear(array $datos): int
    {
        $stmt = $this->conn->prepare(
            'INSERT INTO usuarios (documento, tipo_documento, nombre_completo, telefono, email, password,
                                   google_uid, perfil_completo, es_super_admin, estado, debe_cambiar_password)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 1, ?)'
        );
        $stmt->execute([
            $this->nuloSiVacio($datos['documento'] ?? null),
            $this->nuloSiVacio($datos['tipo_documento'] ?? null),
            $datos['nombre_completo'] ?? null,
            $this->nuloSiVacio($datos['telefono'] ?? null),
            $datos['email'] ?? null,
            $datos['password'] ?? null,
            $datos['google_uid'] ?? null,
            isset($datos['perfil_completo']) ? (int) $datos['perfil_completo'] : 1,
            !empty($datos['debe_cambiar_password']) ? 1 : 0,
        ]);
        return (int) $this->conn->lastInsertId();
    }

    /** Datos de identidad: documento, tipo, nombre, correo y teléfono. */
    public function actualizarIdentidad(int $idUsuario, array $datos): bool
    {
        return $this->conn->prepare(
            'UPDATE usuarios SET documento = ?, tipo_documento = ?, nombre_completo = ?, email = ?, telefono = ?
             WHERE id_usuario = ?'
        )->execute([
            $datos['documento'],
            $datos['tipo_documento'],
            $datos['nombre_completo'],
            $datos['email'],
            $this->nuloSiVacio($datos['telefono'] ?? null),
            $idUsuario,
        ]);
    }

    public function actualizarContacto(int $idUsuario, string $email, string $telefono): bool
    {
        return $this->conn->prepare('UPDATE usuarios SET email = ?, telefono = ? WHERE id_usuario = ?')
            ->execute([$email, $this->nuloSiVacio($telefono), $idUsuario]);
    }

    /** C1.6: los campos de acceso compartidos ni siquiera se reescriben. */
    public function actualizarDatosPersonal(int $idUsuario, array $datos): bool
    {
        return $this->conn->prepare('UPDATE usuarios SET nombre_completo = ?, telefono = ? WHERE id_usuario = ?')
            ->execute([$datos['nombre_completo'], $this->nuloSiVacio($datos['telefono'] ?? null), $idUsuario]);
    }

    public function actualizarPassword(int $idUsuario, string $hash): bool
    {
        return $this->conn->prepare('UPDATE usuarios SET password = ? WHERE id_usuario = ?')->execute([$hash, $idUsuario]);
    }

    /** True si la contraseña es la de la cuenta; false también si la cuenta no tiene (Google). */
    public function verificarPassword(int $idUsuario, string $password): bool
    {
        $stmt = $this->conn->prepare('SELECT password FROM usuarios WHERE id_usuario = ?');
        $stmt->execute([$idUsuario]);
        $hash = $stmt->fetchColumn();
        return is_string($hash) && $hash !== '' && password_verify($password, $hash);
    }

    public function marcarCambioPassword(int $idUsuario, bool $debeCambiar): bool
    {
        return $this->conn->prepare('UPDATE usuarios SET debe_cambiar_password = ? WHERE id_usuario = ?')
            ->execute([$debeCambiar ? 1 : 0, $idUsuario]);
    }

    /** HU-T.5: fecha en que se creó la cuenta, para «Miembro desde». */
    public function getFechaRegistro(int $idUsuario): ?string
    {
        $stmt = $this->conn->prepare('SELECT fecha_registro FROM usuarios WHERE id_usuario = ?');
        $stmt->execute([$idUsuario]);
        $fecha = $stmt->fetchColumn();
        return $fecha ? (string) $fecha : null;
    }

    // ── Contextos (HU-T.17) ───────────────────────────────────────────────

    /**
     * Contextos en los que puede entrar la persona, o null si la cuenta no
     * existe o está inactiva.
     *
     * · El super-administrador solo tiene la plataforma: no se combina con
     *   roles de clínica (RN-G01, RE-T.17.5), aunque por error tuviera alguno.
     * · Personal: cada fila activa de usuario_clinica con rol 1 o 2 en una
     *   clínica activa.
     * · Propietario: un solo portal si tiene al menos un vínculo activo.
     */
    public function contextosDe(int $idUsuario): ?array
    {
        $persona = $this->uno('SELECT estado, es_super_admin FROM usuarios WHERE id_usuario = ?', [$idUsuario]);
        if ($persona === null || (int) $persona['estado'] !== 1) {
            return null;
        }
        if ((int) $persona['es_super_admin'] === 1) {
            return [Contexto::dePlataforma()];
        }

        $contextos = [];
        $stmt = $this->conn->prepare(
            "SELECT uc.id_clinica, uc.id_rol, c.nombre
             FROM usuario_clinica uc
             JOIN clinicas c ON c.id_clinica = uc.id_clinica
             WHERE uc.id_usuario = ? AND uc.estado = 'activo' AND c.estado = 'activa'
               AND uc.id_rol IN (" . implode(',', Roles::DE_CLINICA) . ")
             ORDER BY c.nombre, uc.id_rol"
        );
        $stmt->execute([$idUsuario]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $contextos[] = Contexto::deClinica((int) $fila['id_clinica'], (string) $fila['nombre'], (int) $fila['id_rol']);
        }

        $stmt = $this->conn->prepare(
            "SELECT 1 FROM propietario_clinica pc
             JOIN clinicas c ON c.id_clinica = pc.id_clinica
             WHERE pc.id_propietario = ? AND pc.estado = 'activo' AND c.estado = 'activa'
             LIMIT 1"
        );
        $stmt->execute([$idUsuario]);
        if ($stmt->fetchColumn()) {
            $contextos[] = Contexto::dePropietario();
        }

        return $contextos;
    }

    // ── Personal de una clínica (HU-T.7) ──────────────────────────────────

    /** C1.6: no transferir al administrador el control de una cuenta compartida. */
    public function identidadEditableEnClinica(int $idUsuario, int $idClinica): bool
    {
        return $this->uno(
            "SELECT 1 AS permitido FROM usuario_clinica uc
             WHERE uc.id_usuario = ? AND uc.id_clinica = ?
               AND NOT EXISTS (SELECT 1 FROM usuario_clinica otra
                   WHERE otra.id_usuario = uc.id_usuario AND otra.id_clinica <> uc.id_clinica)
               AND NOT EXISTS (SELECT 1 FROM propietario_clinica pc WHERE pc.id_propietario = uc.id_usuario)",
            [$idUsuario, $idClinica]
        ) !== null;
    }

    /** Personal de la clínica con su rol y su estado en ella. */
    public function personalDeClinica(int $idClinica): array
    {
        $stmt = $this->conn->prepare(
            'SELECT ' . self::COLUMNAS . ", uc.id_rol, r.nombre_rol,
                    CASE WHEN uc.estado = 'activo' THEN 1 ELSE 0 END AS estado_clinica
             FROM usuario_clinica uc
             JOIN usuarios u ON u.id_usuario = uc.id_usuario
             JOIN roles r ON r.id_rol = uc.id_rol
             WHERE uc.id_clinica = ?
             ORDER BY u.nombre_completo"
        );
        $stmt->execute([$idClinica]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** La persona como personal de esta clínica, o null si no lo es. */
    public function personalEnClinica(int $idUsuario, int $idClinica): ?array
    {
        return $this->uno(
            'SELECT ' . self::COLUMNAS . ", uc.id_rol,
                    CASE WHEN uc.estado = 'activo' THEN 1 ELSE 0 END AS estado_clinica
             FROM usuario_clinica uc
             JOIN usuarios u ON u.id_usuario = uc.id_usuario
             WHERE uc.id_usuario = ? AND uc.id_clinica = ?",
            [$idUsuario, $idClinica]
        );
    }

    /**
     * Propietarios vinculados a la clínica, con cuántas de sus mascotas están
     * vinculadas a ella. Solo lectura: el alta y la edición de propietarios
     * llegan con el módulo 1 (C3).
     */
    public function propietariosDeClinica(int $idClinica): array
    {
        $stmt = $this->conn->prepare(
            "SELECT u.id_usuario, u.documento, u.tipo_documento, u.nombre_completo, u.telefono, u.email,
                    CASE WHEN pc.estado = 'activo' THEN 1 ELSE 0 END AS estado_clinica,
                    (SELECT COUNT(*) FROM mascotas m
                       JOIN mascota_clinica mc ON mc.id_mascota = m.id_mascota AND mc.id_clinica = pc.id_clinica
                      WHERE m.id_propietario = u.id_usuario AND m.estado = 1) AS num_mascotas
             FROM propietario_clinica pc
             JOIN usuarios u ON u.id_usuario = pc.id_propietario
             WHERE pc.id_clinica = ?
             ORDER BY u.nombre_completo"
        );
        $stmt->execute([$idClinica]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Asigna (o reactiva) el rol de personal de la persona en la clínica.
     *
     * Solo admite los roles de clínica 1 y 2 (B.5): el 4 va por
     * propietario_clinica y el 5 por es_super_admin. Y no asigna roles de
     * clínica a un super-administrador (RE-T.17.5).
     *
     * @throws InvalidArgumentException si el rol o la persona no lo admiten
     */
    public function asignarRolEnClinica(int $idUsuario, int $idClinica, int $idRol): void
    {
        if (!Roles::esDeClinica($idRol)) {
            throw new InvalidArgumentException('El rol indicado no es un rol de clínica.');
        }
        $persona = $this->uno('SELECT es_super_admin FROM usuarios WHERE id_usuario = ?', [$idUsuario]);
        if ($persona === null) {
            throw new InvalidArgumentException('La persona no existe.');
        }
        if ((int) $persona['es_super_admin'] === 1) {
            throw new InvalidArgumentException('Un super-administrador no puede tener roles de clínica.');
        }

        $existe = $this->uno('SELECT 1 AS hay FROM usuario_clinica WHERE id_usuario = ? AND id_clinica = ?', [$idUsuario, $idClinica]);
        if ($existe) {
            $this->conn->prepare("UPDATE usuario_clinica SET id_rol = ?, estado = 'activo' WHERE id_usuario = ? AND id_clinica = ?")
                ->execute([$idRol, $idUsuario, $idClinica]);
        } else {
            $this->conn->prepare("INSERT INTO usuario_clinica (id_usuario, id_clinica, id_rol, estado) VALUES (?, ?, ?, 'activo')")
                ->execute([$idUsuario, $idClinica, $idRol]);
        }
    }

    /**
     * RN-G08: inactivar a la persona en una clínica retira su rol en esa
     * clínica sin tocar su cuenta ni sus otros roles.
     */
    public function cambiarEstadoEnClinica(int $idUsuario, int $idClinica, bool $activo): bool
    {
        $stmt = $this->conn->prepare('UPDATE usuario_clinica SET estado = ? WHERE id_usuario = ? AND id_clinica = ?');
        $stmt->execute([$activo ? 'activo' : 'inactivo', $idUsuario, $idClinica]);
        return $stmt->rowCount() > 0;
    }

    /** Administradores activos de la clínica, con la cuenta también activa. */
    public function contarAdminsActivos(int $idClinica): int
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) FROM usuario_clinica uc
             JOIN usuarios u ON u.id_usuario = uc.id_usuario
             WHERE uc.id_clinica = ? AND uc.id_rol = ? AND uc.estado = 'activo' AND u.estado = 1"
        );
        $stmt->execute([$idClinica, Roles::ADMIN]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * RN-G08 / RE-T.11.3 — Si la persona es el único administrador activo de
     * la clínica. Se cuenta por clínica: los administradores de otra clínica
     * no sirven de respaldo.
     */
    public function esUltimoAdminActivo(int $idUsuario, int $idClinica): bool
    {
        $fila = $this->personalEnClinica($idUsuario, $idClinica);
        if ($fila === null || (int) $fila['id_rol'] !== Roles::ADMIN || (int) $fila['estado_clinica'] !== 1 || (int) $fila['estado'] !== 1) {
            return false;
        }
        return $this->contarAdminsActivos($idClinica) <= 1;
    }

    // ── Apoyo ────────────────────────────────────────────────────────────

    private function uno(string $sql, array $params): ?array
    {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    private function nuloSiVacio($valor): ?string
    {
        $valor = $valor === null ? '' : trim((string) $valor);
        return $valor === '' ? null : $valor;
    }
}
