<?php
require_once __DIR__ . '/../config/Database.php';

/**
 * Avisos internos al personal (HU-T.6). Cada aviso pertenece a una clínica y
 * va dirigido a una persona (id_usuario) o a un rol dentro de esa clínica:
 * un administrador de la clínica A no ve los avisos de la B aunque tenga el
 * mismo rol (RN-G13).
 */
class NotificacionInterna {
    private $conn;
    private $table_name = "notificaciones_internas";

    // Las citas se agendan en hora de la clinica; la vigencia se compara igual.
    private const ZONA_HORARIA = 'America/Bogota';

    /** Condición común: de la clínica y dirigidas a la persona o a su rol en ella. */
    private const DESTINATARIO = "id_clinica = :id_clinica AND (id_usuario = :id_usuario OR id_rol_destino = :id_rol)";

    /**
     * La conexion es opcional: el controlador lo instancia sin argumentos y se
     * crea la conexion real de siempre. En pruebas se inyecta un PDO propio
     * (SQLite en memoria).
     */
    public function __construct($db = null) {
        if ($db === null) {
            $database = new Database();
            $db = $database->getConnection();
        }
        $this->conn = $db;
    }

    /**
     * Momento actual en hora de la clinica, en el mismo formato que guarda la
     * base de datos. Se calcula en PHP y no con NOW() para no depender de la
     * zona horaria del servidor MySQL.
     */
    private function ahora(): string {
        return (new DateTimeImmutable('now', new DateTimeZone(self::ZONA_HORARIA)))->format('Y-m-d H:i:s');
    }

    /** Aviso para una persona de la clínica. $vigente_hasta: desde cuándo deja de mostrarse (NULL = nunca). */
    public function crearParaUsuario(int $idClinica, int $idUsuario, $tipo, $titulo, $mensaje, $enlace = null, $id_cita = null, $vigente_hasta = null) {
        return $this->crear($idClinica, $idUsuario, null, $tipo, $titulo, $mensaje, $enlace, $id_cita, $vigente_hasta);
    }

    /** Aviso para todos los que tienen ese rol en la clínica (p. ej. sus administradores). */
    public function crearParaRol(int $idClinica, int $idRolDestino, $tipo, $titulo, $mensaje, $enlace = null, $id_cita = null, $vigente_hasta = null) {
        return $this->crear($idClinica, null, $idRolDestino, $tipo, $titulo, $mensaje, $enlace, $id_cita, $vigente_hasta);
    }

    private function crear(int $idClinica, ?int $idUsuario, ?int $idRol, $tipo, $titulo, $mensaje, $enlace, $id_cita, $vigente_hasta): bool {
        $stmt = $this->conn->prepare(
            "INSERT INTO " . $this->table_name . "
             (id_clinica, id_usuario, id_rol_destino, tipo, titulo, mensaje, enlace, id_cita, vigente_hasta)
             VALUES (:id_clinica, :id_usuario, :id_rol, :tipo, :titulo, :mensaje, :enlace, :id_cita, :vigente_hasta)"
        );
        $stmt->bindValue(':id_clinica', $idClinica, PDO::PARAM_INT);
        $stmt->bindValue(':id_usuario', $idUsuario, $idUsuario === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':id_rol', $idRol, $idRol === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':tipo', $tipo);
        $stmt->bindValue(':titulo', $titulo);
        $stmt->bindValue(':mensaje', $mensaje);
        $stmt->bindValue(':enlace', $enlace);
        $stmt->bindValue(':id_cita', $id_cita, $id_cita === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':vigente_hasta', $vigente_hasta);

        return $stmt->execute();
    }

    /**
     * Retira las notificaciones vigentes de una cita (se canceló, se
     * reprogramó o ya se está atendiendo): dejan de mostrarse, pero la fila
     * se conserva.
     */
    public function expirarDeCita($id_cita): bool {
        $query = "UPDATE " . $this->table_name . "
                  SET vigente_hasta = :ahora
                  WHERE id_cita = :id_cita
                    AND (vigente_hasta IS NULL OR vigente_hasta > :ahora_filtro)";

        $ahora = $this->ahora();
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':ahora', $ahora);
        $stmt->bindValue(':ahora_filtro', $ahora);
        $stmt->bindValue(':id_cita', (int) $id_cita, PDO::PARAM_INT);

        return $stmt->execute();
    }

    private function enlazarDestinatario(PDOStatement $stmt, int $idClinica, int $idUsuario, int $idRol): void {
        $stmt->bindValue(':id_clinica', $idClinica, PDO::PARAM_INT);
        $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->bindValue(':id_rol', $idRol, PDO::PARAM_INT);
    }

    /** Notificaciones vigentes de la persona en la clínica (incluye las de su rol). */
    public function obtenerParaUsuario(int $idClinica, int $idUsuario, int $idRol, $limit = 10) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM " . $this->table_name . "
             WHERE " . self::DESTINATARIO . "
               AND (vigente_hasta IS NULL OR vigente_hasta > :ahora)
             ORDER BY fecha_creacion DESC, id DESC
             LIMIT :limit"
        );
        $this->enlazarDestinatario($stmt, $idClinica, $idUsuario, $idRol);
        $stmt->bindValue(':ahora', $this->ahora());
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Número de notificaciones vigentes no leídas. */
    public function contarNoLeidas(int $idClinica, int $idUsuario, int $idRol): int {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) FROM " . $this->table_name . "
             WHERE leida = 0 AND " . self::DESTINATARIO . "
               AND (vigente_hasta IS NULL OR vigente_hasta > :ahora)"
        );
        $this->enlazarDestinatario($stmt, $idClinica, $idUsuario, $idRol);
        $stmt->bindValue(':ahora', $this->ahora());
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * T-01 (VD-SEG-04) — Indica si la notificacion existe y va dirigida a esta
     * persona o a su rol, en esta clínica.
     *
     * Se comprueba con un SELECT en vez de confiar en las filas afectadas por
     * el UPDATE: MySQL no cuenta como afectada una fila que ya tenia
     * `leida = 1`, asi que volver a marcar algo ya leido se habria confundido
     * con un intento de acceso ajeno.
     */
    public function perteneceA($id_notificacion, int $idClinica, int $idUsuario, int $idRol): bool {
        $stmt = $this->conn->prepare(
            "SELECT 1 FROM " . $this->table_name . " WHERE id = :id AND " . self::DESTINATARIO . " LIMIT 1"
        );
        $stmt->bindValue(':id', (int) $id_notificacion, PDO::PARAM_INT);
        $this->enlazarDestinatario($stmt, $idClinica, $idUsuario, $idRol);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Marcar una notificacion como leida.
     *
     * T-01 — El WHERE incluye al destinatario y la clínica: con solo el id,
     * cualquier sesión podía marcar la notificación de otra persona.
     */
    public function marcarLeida($id_notificacion, int $idClinica, int $idUsuario, int $idRol): bool {
        $stmt = $this->conn->prepare(
            "UPDATE " . $this->table_name . " SET leida = 1 WHERE id = :id AND " . self::DESTINATARIO
        );
        $stmt->bindValue(':id', (int) $id_notificacion, PDO::PARAM_INT);
        $this->enlazarDestinatario($stmt, $idClinica, $idUsuario, $idRol);

        return $stmt->execute();
    }

    /** Marcar todas las de la persona en la clínica como leídas. */
    public function marcarTodasLeidas(int $idClinica, int $idUsuario, int $idRol): bool {
        $stmt = $this->conn->prepare(
            "UPDATE " . $this->table_name . " SET leida = 1 WHERE leida = 0 AND " . self::DESTINATARIO
        );
        $this->enlazarDestinatario($stmt, $idClinica, $idUsuario, $idRol);

        return $stmt->execute();
    }
}
