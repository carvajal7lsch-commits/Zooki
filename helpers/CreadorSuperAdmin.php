<?php
require_once __DIR__ . '/PoliticaPassword.php';

/**
 * Crea la cuenta del super-administrador de la plataforma (plan M0, §4.5).
 *
 * Solo la usa scripts/crear_superadmin.php, por consola: no hay ruta web ni
 * credenciales en el código o en el SQL. La cuenta es nueva y aparte: lleva
 * es_super_admin = 1 y ningún rol de clínica (RE-T.17.5), y nunca se eleva una
 * cuenta existente, así que se niega si el correo ya está registrado.
 */
final class CreadorSuperAdmin
{
    public const NOMBRE_MAX = 200;
    public const EMAIL_MAX = 255;

    public function __construct(private PDO $db)
    {
    }

    /**
     * Motivo por el que los datos no sirven, o null si sirven. No consulta la
     * base: el correo repetido se comprueba al crear.
     */
    public static function validar(string $email, string $nombre, string $password): ?string
    {
        if ($email === '' || strlen($email) > self::EMAIL_MAX || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 'El correo no es válido.';
        }
        if ($nombre === '' || mb_strlen($nombre) > self::NOMBRE_MAX) {
            return 'El nombre es obligatorio y admite hasta ' . self::NOMBRE_MAX . ' caracteres.';
        }
        // RN-G10: la misma política que el resto de las cuentas.
        return PoliticaPassword::validar($password, [$nombre, $email]);
    }

    /**
     * @return int id_usuario de la cuenta creada
     * @throws InvalidArgumentException si los datos no sirven o el correo ya existe
     * @throws RuntimeException si la base no tiene el esquema v2
     */
    public function crear(string $email, string $nombre, string $password): int
    {
        $email = trim($email);
        $nombre = trim($nombre);

        $motivo = self::validar($email, $nombre, $password);
        if ($motivo !== null) {
            throw new InvalidArgumentException($motivo);
        }
        if (!$this->tieneEsquemaV2()) {
            throw new RuntimeException('La base no tiene el esquema v2: carga database/01_schema.sql y database/02_semilla.sql.');
        }
        if ($this->correoRegistrado($email)) {
            throw new InvalidArgumentException('Ya existe una cuenta con ese correo. No se modifica: el super-administrador es una cuenta aparte.');
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                'INSERT INTO usuarios (nombre_completo, email, password, perfil_completo, es_super_admin, estado, debe_cambiar_password)
                 VALUES (?, ?, ?, 1, 1, 1, 0)'
            )->execute([$nombre, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $id = (int) $this->db->lastInsertId();

            // Acción de plataforma: sin clínica. La IP no aplica, se creó por consola.
            $this->db->prepare(
                "INSERT INTO auditoria_sistema (id_clinica, id_usuario, ip_address, accion, tabla_afectada, registro_id, descripcion)
                 VALUES (NULL, ?, NULL, 'INSERT', 'usuarios', ?, 'Super-administrador creado por consola')"
            )->execute([$id, (string) $id]);

            $this->db->commit();
            return $id;
        } catch (PDOException $e) {
            $this->db->rollBack();
            // Otro proceso pudo registrar el mismo correo entre la comprobación y el INSERT.
            if ($e->getCode() === '23000') {
                throw new InvalidArgumentException('Ya existe una cuenta con ese correo.', 0, $e);
            }
            throw $e;
        }
    }

    private function correoRegistrado(string $email): bool
    {
        $st = $this->db->prepare('SELECT 1 FROM usuarios WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        return (bool) $st->fetchColumn();
    }

    private function tieneEsquemaV2(): bool
    {
        $st = $this->db->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'es_super_admin'"
        );
        return (int) $st->fetchColumn() > 0;
    }
}
