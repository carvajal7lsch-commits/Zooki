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
 * haya expirado. Los usuarios anteriores no tienen filas, asi que quedan
 * verificados sin migrar nada; y si la tabla no existe, hayPendiente()
 * devuelve false y el login sigue funcionando para todos.
 */
class VerificacionEmail
{
    private $conn;
    private $table = 'verificaciones_email';

    /** Se recuerda si la tabla falta, para no repetir la comprobacion. */
    private static $tablaDisponible = null;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /** Crea la verificacion pendiente y devuelve su id. */
    public function crear(string $documento, string $email, string $tokenHash, string $expiraEn): int
    {
        if (!$this->tablaLista()) return 0;

        $stmt = $this->conn->prepare(
            "INSERT INTO {$this->table} (usuario_documento, email, token_hash, expires_at)
             VALUES (:documento, :email, :hash, :expira)"
        );
        $stmt->execute([
            ':documento' => $documento,
            ':email'     => $email,
            ':hash'      => $tokenHash,
            ':expira'    => $expiraEn,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    /**
     * True si al usuario le falta verificar su correo.
     *
     * Una verificacion vencida NO cuenta como pendiente: si contara, el
     * usuario quedaria encerrado para siempre sin forma de arreglarlo.
     */
    public function hayPendiente(string $documento): bool
    {
        if (!$this->tablaLista()) return false;

        $stmt = $this->conn->prepare(
            "SELECT 1 FROM {$this->table}
             WHERE usuario_documento = :documento AND used = 0 AND expires_at > :ahora
             LIMIT 1"
        );
        $stmt->execute([':documento' => $documento, ':ahora' => date('Y-m-d H:i:s')]);

        return (bool) $stmt->fetchColumn();
    }

    public function buscarPorId(int $id): ?array
    {
        if (!$this->tablaLista()) return null;

        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    /** Marca la verificacion como usada; el usuario queda habilitado. */
    public function marcarUsada(int $id): bool
    {
        if (!$this->tablaLista()) return false;

        $stmt = $this->conn->prepare("UPDATE {$this->table} SET used = 1 WHERE id = :id");

        return $stmt->execute([':id' => $id]);
    }

    /** Invalida las verificaciones anteriores de un usuario (reenvio). */
    public function invalidarDe(string $documento): bool
    {
        if (!$this->tablaLista()) return false;

        $stmt = $this->conn->prepare(
            "UPDATE {$this->table} SET used = 1 WHERE usuario_documento = :documento AND used = 0"
        );

        return $stmt->execute([':documento' => $documento]);
    }

    /**
     * Detecta la tabla y, si falta, la crea. Si tampoco puede crearla, deja
     * constancia en el log y el sistema sigue funcionando sin bloquear a
     * nadie: encerrar a todos los usuarios seria mucho peor que el problema
     * que se quiere resolver.
     */
    private function tablaLista(): bool
    {
        if (self::$tablaDisponible !== null) return self::$tablaDisponible;

        try {
            $this->conn->query("SELECT 1 FROM {$this->table} LIMIT 1");
            self::$tablaDisponible = true;
        } catch (PDOException $e) {
            try {
                $this->conn->exec(
                    "CREATE TABLE IF NOT EXISTS {$this->table} (
                        id INT(11) NOT NULL AUTO_INCREMENT,
                        usuario_documento VARCHAR(20) NOT NULL,
                        email VARCHAR(255) NOT NULL,
                        token_hash VARCHAR(255) NOT NULL,
                        expires_at DATETIME NOT NULL,
                        used TINYINT(1) NOT NULL DEFAULT 0,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        KEY idx_verif_documento (usuario_documento),
                        KEY idx_verif_pendiente (usuario_documento, used)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
                );
                self::$tablaDisponible = true;
            } catch (PDOException $e2) {
                error_log(
                    'HU-36: no existe la tabla verificaciones_email y no se pudo crear ('
                    . $e2->getMessage() . '). El registro queda sin verificacion de correo; '
                    . 'ejecuta database/05_verificaciones_email.sql.'
                );
                self::$tablaDisponible = false;
            }
        }

        return self::$tablaDisponible;
    }

    /** Solo para pruebas: olvida el resultado cacheado de la deteccion. */
    public static function olvidarEstadoDeTabla(): void
    {
        self::$tablaDisponible = null;
    }
}
