<?php
require_once __DIR__ . '/../helpers/Contexto.php';

/**
 * Auditoría del sistema (HU-T.8, RN-G05). Cada fila lleva la persona
 * (id_usuario) y la clínica (id_clinica) del contexto en que ocurrió; las
 * acciones de la plataforma, como el inicio de sesión, van sin clínica.
 * Las lecturas del panel se acotan siempre a una clínica (RN-G13).
 */
class Auditoria {
    private $db;

    /**
     * Proxies de confianza cuyo X-Forwarded-For sí se acepta: IP exactas o
     * rangos CIDR de TRUSTED_PROXIES (lista separada por comas). Vacío = no
     * se confía en ninguno.
     */
    private static ?array $proxiesConfiables = null;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * IP real del cliente (RN-G05, RN-G15). Es la única fuente de IP del
     * sistema: auditoría, límites de intentos de Security y Turnstile.
     *
     * T-08 — X-Forwarded-For la puede escribir el propio cliente, así que solo
     * se lee si la conexión llega desde un proxy declarado en TRUSTED_PROXIES.
     *
     * D2.1 — Cada proxy agrega a la DERECHA la IP de quien le habló; lo de la
     * izquierda lo pudo escribir el cliente. Por eso la cadena se recorre de
     * derecha a izquierda y vale la primera IP que no sea un proxy confiable.
     * Antes se tomaba la de la izquierda: con un X-Forwarded-For inventado,
     * cualquiera elegía la IP con la que se le contaban los intentos.
     */
    public static function ipCliente(): string {
        $remota = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!self::esProxyConfiable($remota)) {
            return $remota;
        }

        $cadena = array_map('trim', explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')));
        $ultimaValida = $remota;
        for ($i = count($cadena) - 1; $i >= 0; $i--) {
            $candidata = $cadena[$i];
            if (!filter_var($candidata, FILTER_VALIDATE_IP)) {
                // Una entrada que no es una IP no viene de un proxy confiable: se para ahí.
                return $ultimaValida;
            }
            if (!self::esProxyConfiable($candidata)) {
                return $candidata;
            }
            $ultimaValida = $candidata;
        }

        return $ultimaValida;
    }

    /** IP exacta o rango CIDR (IPv4 o IPv6) de TRUSTED_PROXIES. */
    private static function esProxyConfiable(string $ip): bool {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        foreach (self::proxiesConfiables() as $proxy) {
            if (self::coincide($ip, $proxy)) {
                return true;
            }
        }
        return false;
    }

    private static function coincide(string $ip, string $proxy): bool {
        if (!str_contains($proxy, '/')) {
            return $ip === $proxy;
        }
        [$red, $bits] = explode('/', $proxy, 2);
        $binIp = @inet_pton($ip);
        $binRed = @inet_pton($red);
        if ($binIp === false || $binRed === false || strlen($binIp) !== strlen($binRed) || !ctype_digit($bits)) {
            return false;
        }
        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        if (strncmp($binIp, $binRed, $bytes) !== 0) {
            return false;
        }
        $resto = $bits % 8;
        if ($resto === 0) {
            return true;
        }
        $mascara = chr((0xFF << (8 - $resto)) & 0xFF);
        return ($binIp[$bytes] & $mascara) === ($binRed[$bytes] & $mascara);
    }

    private static function proxiesConfiables(): array {
        if (self::$proxiesConfiables === null) {
            $envFile = __DIR__ . '/../.env';
            $lista = getenv('TRUSTED_PROXIES') ?: '';
            if ($lista === '' && file_exists($envFile)) {
                $env = parse_ini_file($envFile);
                $lista = (string) ($env['TRUSTED_PROXIES'] ?? '');
            }
            self::$proxiesConfiables = array_values(array_filter(array_map('trim', explode(',', $lista))));
        }
        return self::$proxiesConfiables;
    }

    /** Punto de inyección para las pruebas (null vuelve a leer TRUSTED_PROXIES). */
    public static function definirProxiesConfiables(?array $proxies): void {
        self::$proxiesConfiables = $proxies;
    }

    /**
     * Registra una acción en la auditoría del sistema.
     *
     * @param int|null       $idUsuario  persona que actúa, o null si no corresponde a una cuenta
     * @param string         $accion     LOGIN, LOGIN_FAIL, LOGOUT, INSERT, UPDATE, DELETE, VIEW, OTHER
     * @param int|null|false $idClinica  false = la del contexto activo; null = acción de la plataforma
     */
    public function log(
        ?int $idUsuario = null,
        $accion = 'OTHER',
        $tabla = null,
        $registroId = null,
        $datosAnteriores = null,
        $datosNuevos = null,
        $descripcion = null,
        $idClinica = false
    ) {
        if ($idClinica === false) {
            $idClinica = Contexto::clinicaActiva();
        }

        try {
            $stmt = $this->db->prepare("
                INSERT INTO auditoria_sistema
                (id_clinica, id_usuario, ip_address, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, descripcion)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $jsonAnteriores = $datosAnteriores ? json_encode($datosAnteriores, JSON_UNESCAPED_UNICODE) : null;
            $jsonNuevos = $datosNuevos ? json_encode($datosNuevos, JSON_UNESCAPED_UNICODE) : null;

            return $stmt->execute([
                $idClinica,
                $idUsuario,
                self::ipCliente(),
                $accion,
                $tabla,
                $registroId === null ? null : (string) $registroId,
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
     * Filtros comunes del panel. «usuario» busca por nombre, documento o
     * correo de la persona que actuó.
     *
     * @return array{0: string, 1: array}
     */
    private function filtrar(int $idClinica, array $filtros): array {
        $sql = " FROM auditoria_sistema a LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario WHERE a.id_clinica = ?";
        $params = [$idClinica];

        $persona = trim((string) ($filtros['usuario'] ?? ''));
        if ($persona !== '') {
            $sql .= " AND (u.nombre_completo LIKE ? OR u.documento = ? OR u.email = ?)";
            array_push($params, '%' . $persona . '%', $persona, $persona);
        }
        if (!empty($filtros['accion'])) {
            $sql .= " AND a.accion = ?";
            $params[] = $filtros['accion'];
        }
        if (!empty($filtros['tabla'])) {
            $sql .= " AND a.tabla_afectada = ?";
            $params[] = $filtros['tabla'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $sql .= " AND a.fecha_hora >= ?";
            $params[] = $filtros['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($filtros['fecha_hasta'])) {
            $sql .= " AND a.fecha_hora <= ?";
            $params[] = $filtros['fecha_hasta'] . ' 23:59:59';
        }
        return [$sql, $params];
    }

    /** Registros de la clínica, del más reciente al más antiguo. */
    public function getLogs(int $idClinica, $filtros = [], $limit = 100, $offset = 0) {
        [$desde, $params] = $this->filtrar($idClinica, $filtros);
        $limit = (int) $limit;
        $offset = (int) $offset;

        $stmt = $this->db->prepare(
            "SELECT a.*, u.nombre_completo AS usuario_nombre"
            . $desde . " ORDER BY a.fecha_hora DESC, a.id_auditoria DESC LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Total de registros de la clínica con esos filtros, para paginar. */
    public function countLogs(int $idClinica, $filtros = []) {
        [$desde, $params] = $this->filtrar($idClinica, $filtros);
        $stmt = $this->db->prepare("SELECT COUNT(*)" . $desde);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** Acciones por tipo de los últimos días, en la clínica. */
    public function getStats(int $idClinica, $dias = 7) {
        try {
            $desde = date('Y-m-d H:i:s', strtotime('-' . max(1, (int) $dias) . ' days'));
            $stmt = $this->db->prepare("
                SELECT accion, COUNT(*) as total
                FROM auditoria_sistema
                WHERE id_clinica = ? AND fecha_hora >= ?
                GROUP BY accion
            ");
            $stmt->execute([$idClinica, $desde]);
            return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $e) {
            error_log("Error en getStats auditoria: " . $e->getMessage());
            return [];
        }
    }

    public function getDistinctAcciones(int $idClinica) {
        $stmt = $this->db->prepare("SELECT DISTINCT accion FROM auditoria_sistema WHERE id_clinica = ? ORDER BY accion");
        $stmt->execute([$idClinica]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getDistinctTablas(int $idClinica) {
        $stmt = $this->db->prepare("SELECT DISTINCT tabla_afectada FROM auditoria_sistema WHERE id_clinica = ? AND tabla_afectada IS NOT NULL ORDER BY tabla_afectada");
        $stmt->execute([$idClinica]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * HU-T.5: actividad reciente de la propia cuenta, de la más nueva a la más
     * antigua: accesos, intentos fallidos y cambios sobre la cuenta. Es de la
     * persona, así que no se filtra por clínica.
     */
    public function actividadDeCuenta(int $idUsuario, int $limite = 8): array {
        $limite = max(1, min(50, $limite));
        $stmt = $this->db->prepare(
            "SELECT fecha_hora, accion, ip_address, descripcion
             FROM auditoria_sistema
             WHERE id_usuario = ?
               AND (accion IN ('LOGIN', 'LOGIN_FAIL')
                    OR (accion = 'UPDATE' AND tabla_afectada = 'usuarios' AND registro_id = ?))
             ORDER BY fecha_hora DESC, id_auditoria DESC
             LIMIT $limite"
        );
        $stmt->execute([$idUsuario, (string) $idUsuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Intentos de acceso fallidos a la cuenta desde una fecha (hora de la base). */
    public function contarAccesosFallidos(int $idUsuario, string $desde): int {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM auditoria_sistema WHERE id_usuario = ? AND accion = 'LOGIN_FAIL' AND fecha_hora >= ?"
        );
        $stmt->execute([$idUsuario, $desde]);
        return (int) $stmt->fetchColumn();
    }
}
