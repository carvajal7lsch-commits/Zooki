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

    // RN-409: cubre avisos anteriores y el que pudiera publicar el vigilante después del cierre.
    private const ATENCION_VIGENTE = "(tipo NOT IN ('ATENCION_ABIERTA','ATENCION_SIN_CERRAR')
        OR NOT EXISTS (SELECT 1 FROM citas c
            WHERE c.id_cita=notificaciones_internas.id_cita
              AND c.id_clinica=notificaciones_internas.id_clinica AND c.estado='completada'))";

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

    /** RN-409/RE-4.5.1: solo los avisos operativos; el llamador mantiene la unidad del cierre. */
    public function expirarAvisosDeAtencion(int $idClinica, int $idCita, string $ahora): bool {
        $stmt = $this->conn->prepare("UPDATE notificaciones_internas SET vigente_hasta=:ahora
            WHERE id_clinica=:id_clinica AND id_cita=:id_cita
              AND tipo IN ('ATENCION_ABIERTA','ATENCION_SIN_CERRAR')
              AND (vigente_hasta IS NULL OR vigente_hasta>:limite)");
        return $stmt->execute([':ahora' => $ahora, ':limite' => $ahora, ':id_clinica' => $idClinica, ':id_cita' => $idCita]);
    }

    /** RE-5.7.4: el portal global solo recibe estimaciones de sus mascotas actuales. */
    private function condicionDestinatario(?int $idClinica): string {
        if ($idClinica !== null) return self::DESTINATARIO;
        return "id_usuario=:id_usuario AND id_rol_destino IS NULL AND tipo='ESTIMACION_CITA'
            AND EXISTS (SELECT 1 FROM citas c JOIN mascotas m ON m.id_mascota=c.id_mascota
                WHERE c.id_cita=notificaciones_internas.id_cita AND c.id_clinica=notificaciones_internas.id_clinica
                AND m.id_propietario=notificaciones_internas.id_usuario)";
    }

    private function enlazarDestinatario(PDOStatement $stmt, ?int $idClinica, int $idUsuario, int $idRol): void {
        $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
        if ($idClinica === null) return;
        $stmt->bindValue(':id_clinica', $idClinica, PDO::PARAM_INT);
        $stmt->bindValue(':id_rol', $idRol, PDO::PARAM_INT);
    }

    /** Notificaciones vigentes de la persona en la clínica (incluye las de su rol). */
    public function obtenerParaUsuario(?int $idClinica, int $idUsuario, int $idRol, $limit = 10) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM " . $this->table_name . "
             WHERE " . $this->condicionDestinatario($idClinica) . "
               AND " . self::ATENCION_VIGENTE . "
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
    public function contarNoLeidas(?int $idClinica, int $idUsuario, int $idRol): int {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) FROM " . $this->table_name . "
             WHERE leida = 0 AND " . $this->condicionDestinatario($idClinica) . "
               AND " . self::ATENCION_VIGENTE . "
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
    public function perteneceA($id_notificacion, ?int $idClinica, int $idUsuario, int $idRol): bool {
        $stmt = $this->conn->prepare(
            "SELECT 1 FROM " . $this->table_name . " WHERE id = :id AND " . $this->condicionDestinatario($idClinica) . " LIMIT 1"
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
    public function marcarLeida($id_notificacion, ?int $idClinica, int $idUsuario, int $idRol): bool {
        $stmt = $this->conn->prepare(
            "UPDATE " . $this->table_name . " SET leida = 1 WHERE id = :id AND " . $this->condicionDestinatario($idClinica)
        );
        $stmt->bindValue(':id', (int) $id_notificacion, PDO::PARAM_INT);
        $this->enlazarDestinatario($stmt, $idClinica, $idUsuario, $idRol);

        return $stmt->execute();
    }

    /** Marcar todas las de la persona en la clínica como leídas. */
    public function marcarTodasLeidas(?int $idClinica, int $idUsuario, int $idRol): bool {
        $stmt = $this->conn->prepare(
            "UPDATE " . $this->table_name . " SET leida = 1 WHERE leida = 0 AND " . $this->condicionDestinatario($idClinica)
        );
        $this->enlazarDestinatario($stmt, $idClinica, $idUsuario, $idRol);

        return $stmt->execute();
    }
}
