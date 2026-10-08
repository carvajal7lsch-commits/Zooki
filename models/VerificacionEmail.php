<?php
/**
 * HU-36 (VD-SEG-08) — Verificacion del correo en el auto-registro.
 *
 * Antes, registrarse creaba la cuenta activa e iniciaba sesion de una vez, sin
 * comprobar que el correo fuera del que se registraba. Ahora el registro deja
 * una verificacion pendiente y la cuenta no sirve para entrar hasta que se
 * abra el enlace enviado a ese buzon.
 *
 * Criterio: un usuario esta pendiente si tiene una fila con used = 0 que no
 * haya expirado. Las verificaciones van por id_usuario (MER §2).
 *
 * La v1 creaba la tabla en tiempo de ejecucion si faltaba; con la base v2 la
 * tabla siempre existe (01_schema.sql), y recrearla con una forma vieja
 * dejaria un esquema mezclado (riesgo 6 de A.6), asi que ya no se hace.
 */
class VerificacionEmail
{
    private $conn;
    private $table = 'verificaciones_email';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /** Crea la verificacion pendiente y devuelve su id. */
    public function crear(int $idUsuario, string $email, string $tokenHash, string $expiraEn): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO {$this->table} (id_usuario, email, token_hash, expires_at)
             VALUES (:id_usuario, :email, :hash, :expira)"
        );
        $stmt->execute([
            ':id_usuario' => $idUsuario,
            ':email'      => $email,
            ':hash'       => $tokenHash,
            ':expira'     => $expiraEn,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    /**
     * True si al usuario le falta verificar su correo.
     *
     * D1 (RN-G11, decisión del usuario): un enlace vencido sigue contando como
     * pendiente. Antes se ignoraba y, pasadas 24 horas, quien se registró con
     * un correo ajeno entraba con la contraseña que él mismo puso. Para salir
     * de aquí, registrarse otra vez con ese correo envía un enlace nuevo
     * (RegistroPropietario) y entrar con Google verifica la cuenta (RN-G21).
     */
    public function hayPendiente(int $idUsuario): bool
    {
        $stmt = $this->conn->prepare(
            "SELECT 1 FROM {$this->table}
             WHERE id_usuario = :id_usuario AND proposito = 'registro' AND used = 0
             LIMIT 1"
        );
        $stmt->execute([':id_usuario' => $idUsuario]);

        return (bool) $stmt->fetchColumn();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    /** Marca la verificacion como usada; el usuario queda habilitado. */
    public function marcarUsada(int $id): bool
    {
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET used = 1 WHERE id = :id");

        return $stmt->execute([':id' => $id]);
    }

    /** Invalida las verificaciones anteriores de un usuario (reenvio). */
    public function invalidarDe(int $idUsuario): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table} SET used = 1 WHERE id_usuario = :id_usuario AND proposito = 'registro' AND used = 0"
        );

        return $stmt->execute([':id_usuario' => $idUsuario]);
    }
}
