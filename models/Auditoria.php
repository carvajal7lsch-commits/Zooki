<?php

class Auditoria {
    private $db;

    /**
     * Proxies de confianza cuyo X-Forwarded-For si se acepta. Se leen de
     * TRUSTED_PROXIES en .env (lista separada por comas). Vacio = no se
     * confia en ninguno.
     */
    private static ?array $proxiesConfiables = null;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * IP real del cliente para el registro de auditoria (RN-G05).
     *
     * T-08 — Antes se tomaba X-Forwarded-For siempre que viniera, sin mirar
     * quien la enviaba. Como es una cabecera que pone el propio cliente,
     * cualquiera podia escribir en el log de seguridad la IP que quisiera,
     * incluida la de otra persona. Ahora solo se acepta si la peticion llega
     * desde un proxy declarado como confiable; en cualquier otro caso vale la
     * IP de la conexion, que no se puede falsificar.
     */
    private static function ipCliente(): string {
        $remota = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if (self::$proxiesConfiables === null) {
            $envFile = __DIR__ . '/../.env';
            $lista = '';
            if (file_exists($envFile)) {
                $env = parse_ini_file($envFile);
                $lista = $env['TRUSTED_PROXIES'] ?? '';
            }
            self::$proxiesConfiables = array_filter(array_map('trim', explode(',', $lista)));
        }

        if (!in_array($remota, self::$proxiesConfiables, true)) {
            return $remota;
        }

        // Detras de un proxy confiable, el cliente original es la primera
        // entrada de la cadena. Se valida que sea una IP real.
        $cadena = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        foreach (explode(',', $cadena) as $candidata) {
            $candidata = trim($candidata);
            if (filter_var($candidata, FILTER_VALIDATE_IP)) {
                return $candidata;
            }
        }

        return $remota;
    }

    /**
     * Registra una acción en la auditoría del sistema
     *
     * @param string|null $usuarioDoc Documento del usuario o null si no está autenticado
     * @param string $accion LOGIN, LOGIN_FAIL, LOGOUT, INSERT, UPDATE, DELETE, VIEW, OTHER
     * @param string|null $tabla Tabla afectada
     * @param string|null $registroId ID del registro afectado
     * @param array|null $datosAnteriores Datos antes del cambio
     * @param array|null $datosNuevos Datos después del cambio
     * @param string|null $descripcion Descripción legible de la acción
     * @return bool
     */
    public function log(
        $usuarioDoc = null,
        $accion = 'OTHER',
        $tabla = null,
        $registroId = null,
        $datosAnteriores = null,
        $datosNuevos = null,
        $descripcion = null
    ) {
        try {
            $ip = self::ipCliente();

            $stmt = $this->db->prepare("
                INSERT INTO auditoria_sistema
                (usuario_doc, ip_address, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, descripcion)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $jsonAnteriores = $datosAnteriores ? json_encode($datosAnteriores, JSON_UNESCAPED_UNICODE) : null;
            $jsonNuevos = $datosNuevos ? json_encode($datosNuevos, JSON_UNESCAPED_UNICODE) : null;

            return $stmt->execute([
                $usuarioDoc,
                $ip,
                $accion,
                $tabla,
                $registroId,
                $jsonAnteriores,
                $jsonNuevos,
                $descripcion
            ]);
        } catch (Exception $e) {
            error_log("Error en auditoria: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene registros de auditoría con filtros opcionales
     */
    public function getLogs($filtros = [], $limit = 100, $offset = 0) {
        $sql = "SELECT * FROM auditoria_sistema WHERE 1=1";
        $params = [];

        if (!empty($filtros['usuario_doc'])) {
            $sql .= " AND usuario_doc = ?";
            $params[] = $filtros['usuario_doc'];
        }
        if (!empty($filtros['accion'])) {
            $sql .= " AND accion = ?";
            $params[] = $filtros['accion'];
        }
        if (!empty($filtros['tabla'])) {
            $sql .= " AND tabla_afectada = ?";
            $params[] = $filtros['tabla'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $sql .= " AND fecha_hora >= ?";
            $params[] = $filtros['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($filtros['fecha_hasta'])) {
            $sql .= " AND fecha_hora <= ?";
            $params[] = $filtros['fecha_hasta'] . ' 23:59:59';
        }

        $limit = (int) $limit;
        $offset = (int) $offset;
        $sql .= " ORDER BY fecha_hora DESC LIMIT $limit OFFSET $offset";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta total de registros para paginación
     */
    public function countLogs($filtros = []) {
        $sql = "SELECT COUNT(*) FROM auditoria_sistema WHERE 1=1";
        $params = [];

        if (!empty($filtros['usuario_doc'])) {
            $sql .= " AND usuario_doc = ?";
            $params[] = $filtros['usuario_doc'];
        }
        if (!empty($filtros['accion'])) {
            $sql .= " AND accion = ?";
            $params[] = $filtros['accion'];
        }
        if (!empty($filtros['tabla'])) {
            $sql .= " AND tabla_afectada = ?";
            $params[] = $filtros['tabla'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $sql .= " AND fecha_hora >= ?";
            $params[] = $filtros['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($filtros['fecha_hasta'])) {
            $sql .= " AND fecha_hora <= ?";
            $params[] = $filtros['fecha_hasta'] . ' 23:59:59';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtiene estadísticas de acciones para el dashboard
     */
    public function getStats($dias = 7) {
        try {
            $dias = (int) $dias;
            $stmt = $this->db->prepare("
                SELECT accion, COUNT(*) as total
                FROM auditoria_sistema
                WHERE fecha_hora >= DATE_SUB(NOW(), INTERVAL {$dias} DAY)
                GROUP BY accion
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $e) {
            error_log("Error en getStats auditoria: " . $e->getMessage());
            return [];
        }
    }

    public function getDistinctAcciones() {
        $stmt = $this->db->query("SELECT DISTINCT accion FROM auditoria_sistema ORDER BY accion");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getDistinctTablas() {
        $stmt = $this->db->query("SELECT DISTINCT tabla_afectada FROM auditoria_sistema WHERE tabla_afectada IS NOT NULL ORDER BY tabla_afectada");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
