<?php
require_once __DIR__ . '/../config/Database.php';

class NotificacionInterna {
    private $conn;
    private $table_name = "notificaciones_internas";

    // Las citas se agendan en hora de la clinica; la vigencia se compara igual.
    private const ZONA_HORARIA = 'America/Bogota';

    /**
     * La conexion es opcional: el controlador lo instancia sin argumentos y se
     * crea la conexion real de siempre. En pruebas se inyecta un PDO propio
     * (SQLite en memoria), siguiendo el mismo patron que UsuarioController.
     * Antes la dependencia estaba fijada dentro del constructor y la clase no
     * se podia ejercitar sin base de datos real (ZOOKI_REGLAS 2.5).
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

    // Crear notificación para un usuario específico (ej. un veterinario).
    // $vigente_hasta: a partir de ese momento deja de mostrarse (NULL = nunca).
    public function crearParaUsuario($doc_usuario, $tipo, $titulo, $mensaje, $enlace = null, $id_cita = null, $vigente_hasta = null) {
        $query = "INSERT INTO " . $this->table_name . "
                  (doc_usuario, tipo, titulo, mensaje, enlace, id_cita, vigente_hasta)
                  VALUES (:doc_usuario, :tipo, :titulo, :mensaje, :enlace, :id_cita, :vigente_hasta)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindValue(':doc_usuario', $doc_usuario);
        $stmt->bindValue(':tipo', $tipo);
        $stmt->bindValue(':titulo', $titulo);
        $stmt->bindValue(':mensaje', $mensaje);
        $stmt->bindValue(':enlace', $enlace);
        $stmt->bindValue(':id_cita', $id_cita, $id_cita === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':vigente_hasta', $vigente_hasta);

        return $stmt->execute();
    }

    // Crear notificación para todo un rol (ej. todos los admins = rol 1)
    public function crearParaRol($id_rol_destino, $tipo, $titulo, $mensaje, $enlace = null, $id_cita = null, $vigente_hasta = null) {
        $query = "INSERT INTO " . $this->table_name . "
                  (id_rol_destino, tipo, titulo, mensaje, enlace, id_cita, vigente_hasta)
                  VALUES (:id_rol_destino, :tipo, :titulo, :mensaje, :enlace, :id_cita, :vigente_hasta)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindValue(':id_rol_destino', $id_rol_destino);
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

    // Obtener notificaciones vigentes para un usuario (incluyendo las dirigidas a su rol)
    public function obtenerParaUsuario($doc_usuario, $id_rol, $limit = 10) {
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE (doc_usuario = :doc_usuario OR id_rol_destino = :id_rol)
                    AND (vigente_hasta IS NULL OR vigente_hasta > :ahora)
                  ORDER BY fecha_creacion DESC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':doc_usuario', $doc_usuario);
        $stmt->bindValue(':id_rol', $id_rol);
        $stmt->bindValue(':ahora', $this->ahora());
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener número de notificaciones vigentes no leídas
    public function contarNoLeidas($doc_usuario, $id_rol) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . "
                  WHERE leida = 0
                    AND (doc_usuario = :doc_usuario OR id_rol_destino = :id_rol)
                    AND (vigente_hasta IS NULL OR vigente_hasta > :ahora)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':doc_usuario', $doc_usuario);
        $stmt->bindValue(':id_rol', $id_rol);
        $stmt->bindValue(':ahora', $this->ahora());
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $row['total'];
    }

    /**
     * T-01 (VD-SEG-04) — Indica si la notificacion existe y va dirigida a este
     * usuario (por documento) o a su rol.
     *
     * Se comprueba con un SELECT en vez de confiar en las filas afectadas por
     * el UPDATE: MySQL no cuenta como afectada una fila que ya tenia
     * `leida = 1`, asi que volver a marcar algo ya leido se habria confundido
     * con un intento de acceso ajeno.
     */
    public function perteneceA($id_notificacion, $doc_usuario, $id_rol): bool {
        $query = "SELECT 1 FROM " . $this->table_name . "
                  WHERE id = :id
                    AND (doc_usuario = :doc_usuario OR id_rol_destino = :id_rol)
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', (int) $id_notificacion, PDO::PARAM_INT);
        $stmt->bindValue(':doc_usuario', $doc_usuario);
        $stmt->bindValue(':id_rol', (int) $id_rol, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Marcar una notificacion como leida.
     *
     * T-01 — El WHERE incluye al destinatario. Antes filtraba solo por id, asi
     * que cualquier sesion valida podia marcar la notificacion de otro usuario
     * mandando un id cualquiera; el control por rol no lo evitaba porque los
     * cuatro roles tienen permitida esta accion.
     */
    public function marcarLeida($id_notificacion, $doc_usuario, $id_rol): bool {
        $query = "UPDATE " . $this->table_name . "
                  SET leida = 1
                  WHERE id = :id
                    AND (doc_usuario = :doc_usuario OR id_rol_destino = :id_rol)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', (int) $id_notificacion, PDO::PARAM_INT);
        $stmt->bindValue(':doc_usuario', $doc_usuario);
        $stmt->bindValue(':id_rol', (int) $id_rol, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Marcar todas como leídas para un usuario/rol
    public function marcarTodasLeidas($doc_usuario, $id_rol) {
        $query = "UPDATE " . $this->table_name . "
                  SET leida = 1
                  WHERE leida = 0
                    AND (doc_usuario = :doc_usuario OR id_rol_destino = :id_rol)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':doc_usuario', $doc_usuario);
        $stmt->bindParam(':id_rol', $id_rol);

        return $stmt->execute();
    }
}
?>
