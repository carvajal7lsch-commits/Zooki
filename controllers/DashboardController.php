<?php
require_once __DIR__ . "/../config/Database.php";

class DashboardController
{
    private $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    private function requireAuth()
    {
        if (!isset($_SESSION["usuario_doc"])) {
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "message" => "No autorizado",
            ]);
            exit();
        }
    }

    public function getStatsAjax()
    {
        header("Content-Type: application/json");
        $this->requireAuth();
        $rol = (int) $_SESSION["usuario_id_rol"];
        $doc = $_SESSION["usuario_doc"];
        $hoy = date("Y-m-d");
        try {
            $stats = [];
            if ($rol === 1) {
                $stats["pacientes"] = (int) $this->db
                    ->query("SELECT COUNT(*) FROM mascotas WHERE estado = 1")
                    ->fetchColumn();
                $stats["clientes"] = (int) $this->db
                    ->query(
                        "SELECT COUNT(*) FROM usuarios WHERE id_rol = 4 AND estado = 1",
                    )
                    ->fetchColumn();
                $s = $this->db->prepare(
                    "SELECT COUNT(*) FROM citas WHERE fecha = ? AND estado != 'cancelada'",
                );
                $s->execute([$hoy]);
                $stats["citas_hoy"] = (int) $s->fetchColumn();
                $s = $this->db->query(
                    "SELECT COUNT(*) FROM consultas WHERE MONTH(fecha_hora)=MONTH(NOW()) AND YEAR(fecha_hora)=YEAR(NOW())",
                );
                $stats["consultas_mes"] = (int) $s->fetchColumn();
            } elseif ($rol === 2) {
                $s = $this->db->prepare(
                    "SELECT COUNT(*) FROM citas WHERE doc_veterinario=? AND fecha=? AND estado != 'cancelada'",
                );
                $s->execute([$doc, $hoy]);
                $stats["citas_hoy"] = (int) $s->fetchColumn();
                $s = $this->db->prepare(
                    "SELECT COUNT(*) FROM consultas WHERE doc_veterinario=? AND DATE(fecha_hora)=?",
                );
                $s->execute([$doc, $hoy]);
                $stats["consultas_hoy"] = (int) $s->fetchColumn();
                $s = $this->db->prepare(
                    "SELECT COUNT(DISTINCT id_mascota) FROM citas WHERE doc_veterinario=? AND estado='completada'",
                );
                $s->execute([$doc]);
                $stats["pacientes_atendidos"] = (int) $s->fetchColumn();
            } elseif ($rol === 3) {
                $s = $this->db->prepare(
                    "SELECT COUNT(*) FROM citas WHERE fecha=? AND estado != 'cancelada'",
                );
                $s->execute([$hoy]);
                $stats["citas_hoy"] = (int) $s->fetchColumn();
                $s = $this->db->prepare(
                    "SELECT COUNT(*) FROM citas WHERE fecha=? AND estado='pendiente'",
                );
                $s->execute([$hoy]);
                $stats["pendientes"] = (int) $s->fetchColumn();
                $s = $this->db->prepare(
                    "SELECT COUNT(*) FROM citas WHERE fecha=? AND estado IN ('confirmada','completada')",
                );
                $s->execute([$hoy]);
                $stats["atendidas"] = (int) $s->fetchColumn();
            }
            echo json_encode(["success" => true, "stats" => $stats]);
        } catch (Exception $e) {
            echo json_encode([
                "success" => false,
                "message" => $e->getMessage(),
            ]);
        }
        exit();
    }

    public function getChartsDataAjax()
    {
        header("Content-Type: application/json");
        $this->requireAuth();
        $rol = (int) $_SESSION["usuario_id_rol"];
        $doc = $_SESSION["usuario_doc"];
        $hoy = date("Y-m-d");
        try {
            $data = [];
            if ($rol === 1) {
                $s = $this->db->query(
                    "SELECT DATE_FORMAT(fecha,'%b %Y') AS mes, DATE_FORMAT(fecha,'%Y-%m') AS mes_key, COUNT(*) AS total FROM citas WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND estado != 'cancelada' GROUP BY mes_key, mes ORDER BY mes_key ASC",
                );
                $data["citas_mes"] = $s->fetchAll(PDO::FETCH_ASSOC);

                $s = $this->db->query(
                    "SELECT e.nombre_especie, COUNT(m.id_mascota) AS total FROM mascotas m JOIN especies e ON m.id_especie = e.id_especie WHERE m.estado = 1 GROUP BY e.id_especie, e.nombre_especie ORDER BY total DESC",
                );
                $data["especies"] = $s->fetchAll(PDO::FETCH_ASSOC);

                $s = $this->db->query(
                    "SELECT DAYOFWEEK(fecha) AS dia_num, COUNT(*) AS total FROM citas WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK) AND estado != 'cancelada' GROUP BY dia_num ORDER BY dia_num ASC",
                );
                $diasRaw = $s->fetchAll(PDO::FETCH_ASSOC);
                $diasMap = [
                    2 => "Lun",
                    3 => "Mar",
                    4 => "Mie",
                    5 => "Jue",
                    6 => "Vie",
                    7 => "Sab",
                    1 => "Dom",
                ];
                $diasFinal = [];
                foreach ([2, 3, 4, 5, 6, 7, 1] as $num) {
                    $found = array_values(
                        array_filter(
                            $diasRaw,
                            fn($d) => (int) $d["dia_num"] === $num,
                        ),
                    );
                    $diasFinal[] = [
                        "dia" => $diasMap[$num],
                        "total" => $found ? (int) $found[0]["total"] : 0,
                    ];
                }
                $data["dias_semana"] = $diasFinal;

                $s = $this->db->query(
                    "SELECT u.nombre_completo, COUNT(DISTINCT c.id_consulta) AS consultas, COUNT(DISTINCT ci.id_cita) AS citas FROM usuarios u LEFT JOIN consultas c ON c.doc_veterinario = u.documento LEFT JOIN citas ci ON ci.doc_veterinario = u.documento AND ci.estado != 'cancelada' WHERE u.id_rol = 2 AND u.estado = 1 GROUP BY u.documento, u.nombre_completo ORDER BY consultas DESC LIMIT 5",
                );
                $data["ranking_vets"] = $s->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($rol === 2) {
                // 1. Tasa de Cumplimiento (Citas Completadas vs Canceladas/No asistidas) de los últimos 30 días
                $s = $this->db->prepare(
                    "SELECT estado, COUNT(*) AS total FROM citas WHERE doc_veterinario = ? AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY estado",
                );
                $s->execute([$doc]);
                $data["cumplimiento"] = $s->fetchAll(PDO::FETCH_ASSOC);

                // 2. Distribución de Especies (Demografía)
                $s = $this->db->prepare(
                    "SELECT e.nombre_especie, COUNT(DISTINCT m.id_mascota) AS total FROM citas c JOIN mascotas m ON c.id_mascota = m.id_mascota JOIN especies e ON m.id_especie = e.id_especie WHERE c.doc_veterinario = ? AND c.estado = 'completada' GROUP BY e.id_especie, e.nombre_especie ORDER BY total DESC LIMIT 5",
                );
                $s->execute([$doc]);
                $data["mis_especies"] = $s->fetchAll(PDO::FETCH_ASSOC);

                // 3. Tendencia de Citas (Últimos 6 meses, por día y tipo)
                $s = $this->db->prepare("
                    SELECT 
                        c.fecha, 
                        COALESCE(tc.nombre_tipo, 'General') AS tipo_cita, 
                        COUNT(*) AS total 
                    FROM citas c
                    LEFT JOIN tipos_cita tc ON c.id_tipo_cita = tc.id_tipo_cita
                    WHERE c.doc_veterinario = ? 
                      AND c.fecha >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) 
                      AND c.estado != 'cancelada'
                    GROUP BY c.fecha, tipo_cita
                    ORDER BY c.fecha ASC
                ");
                $s->execute([$doc]);
                $data["tendencia_citas"] = $s->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($rol === 3) {
                $s = $this->db->prepare(
                    "SELECT estado, COUNT(*) AS total FROM citas WHERE fecha = ? GROUP BY estado",
                );
                $s->execute([$hoy]);
                $data["estado_citas_hoy"] = $s->fetchAll(PDO::FETCH_ASSOC);
            }

            echo json_encode(["success" => true, "data" => $data]);
        } catch (Exception $e) {
            echo json_encode([
                "success" => false,
                "message" => $e->getMessage(),
            ]);
        }
        exit();
    }

    public function getCitasHoyTimelineAjax()
    {
        header("Content-Type: application/json");
        $this->requireAuth();
        $hoy = date("Y-m-d");
        try {
            $s = $this->db->prepare(
                "SELECT c.id_cita, m.nombre AS mascota_nombre, prop.nombre_completo AS propietario_nombre, vet.nombre_completo AS veterinario_nombre, c.hora, c.hora_fin, c.motivo, c.estado, c.duracion_minutos, e.nombre_especie FROM citas c JOIN mascotas m ON c.id_mascota = m.id_mascota JOIN usuarios prop ON m.doc_propietario = prop.documento JOIN usuarios vet ON c.doc_veterinario = vet.documento JOIN especies e ON m.id_especie = e.id_especie WHERE c.fecha = ? ORDER BY c.hora ASC",
            );
            $s->execute([$hoy]);
            $citas = $s->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["success" => true, "citas" => $citas]);
        } catch (Exception $e) {
            echo json_encode([
                "success" => false,
                "message" => $e->getMessage(),
            ]);
        }
        exit();
    }

    public function getPendientesAjax()
    {
        header("Content-Type: application/json");
        $this->requireAuth();
        $rol = (int) $_SESSION["usuario_id_rol"];
        $doc = $_SESSION["usuario_doc"];
        $hoy = date("Y-m-d");
        $sieteDias = date("Y-m-d", strtotime("+7 days"));

        try {
            $pendientes = [];

            // 1. Citas del día
            $sqlCitas = "SELECT c.id_cita, m.nombre AS mascota, prop.nombre_completo AS propietario, vet.nombre_completo AS veterinario, c.hora, c.motivo, c.estado, e.nombre_especie
                         FROM citas c
                         JOIN mascotas m ON c.id_mascota = m.id_mascota
                         JOIN usuarios prop ON m.doc_propietario = prop.documento
                         JOIN usuarios vet ON c.doc_veterinario = vet.documento
                         JOIN especies e ON m.id_especie = e.id_especie
                         WHERE c.fecha = ? AND c.estado IN ('pendiente','confirmada','en_curso')";
            if ($rol === 2) {
                $sqlCitas .= " AND c.doc_veterinario = ?";
                $s = $this->db->prepare($sqlCitas);
                $s->execute([$hoy, $doc]);
            } else {
                $s = $this->db->prepare($sqlCitas);
                $s->execute([$hoy]);
            }
            $pendientes["citas_hoy"] = $s->fetchAll(PDO::FETCH_ASSOC);

            // 2. Vacunas próximas 7 días
            $sqlVacunas = "SELECT v.id_vacuna, m.nombre AS mascota, v.nombre_vacuna, v.fecha_proxima_dosis, e.nombre_especie, prop.nombre_completo AS propietario
                           FROM vacunas v
                           JOIN mascotas m ON v.id_mascota = m.id_mascota
                           JOIN especies e ON m.id_especie = e.id_especie
                           JOIN usuarios prop ON m.doc_propietario = prop.documento
                           WHERE v.fecha_proxima_dosis BETWEEN ? AND ?
                           ORDER BY v.fecha_proxima_dosis ASC";
            $s = $this->db->prepare($sqlVacunas);
            $s->execute([$hoy, $sieteDias]);
            $pendientes["vacunas_proximas"] = $s->fetchAll(PDO::FETCH_ASSOC);

            // 3. Desparasitaciones próximas
            $sqlDesp = "SELECT d.id_desparasitacion, m.nombre AS mascota, d.producto, d.fecha_proxima, d.tipo, e.nombre_especie, prop.nombre_completo AS propietario
                        FROM desparasitaciones d
                        JOIN mascotas m ON d.id_mascota = m.id_mascota
                        JOIN especies e ON m.id_especie = e.id_especie
                        JOIN usuarios prop ON m.doc_propietario = prop.documento
                        WHERE d.fecha_proxima BETWEEN ? AND ?
                        ORDER BY d.fecha_proxima ASC";
            $s = $this->db->prepare($sqlDesp);
            $s->execute([$hoy, $sieteDias]);
            $pendientes["desparasitaciones_proximas"] = $s->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(["success" => true, "pendientes" => $pendientes]);
        } catch (Exception $e) {
            echo json_encode([
                "success" => false,
                "message" => $e->getMessage(),
            ]);
        }
        exit();
    }

    /**
     * Pantalla de auditoría para administradores
     */
    public function listarAuditoria() {
        $this->requireAuth();
        if ((int) $_SESSION["usuario_id_rol"] !== 1) {
            header("Location: index.php?action=login");
            exit();
        }

        require_once __DIR__ . '/../models/Auditoria.php';
        $auditoria = new Auditoria($this->db);

        $filtros = [
            'usuario_doc' => $_GET['usuario_doc'] ?? '',
            'accion' => $_GET['accion'] ?? '',
            'fecha_desde' => $_GET['fecha_desde'] ?? '',
            'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
            'tabla' => $_GET['tabla'] ?? '',
        ];

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $logs = $auditoria->getLogs($filtros, $perPage, $offset);
        $total = $auditoria->countLogs($filtros);

        $acciones = $auditoria->getDistinctAcciones();
        $tablas = $auditoria->getDistinctTablas();

        $content_view = "../views/admin/auditoria.php";
        require_once "../views/admin/layout.php";
    }

    /**
     * Endpoint AJAX para filtrar auditoría
     */
    public function getAuditoriaAjax() {
        header("Content-Type: application/json");
        $this->requireAuth();
        if ((int) $_SESSION["usuario_id_rol"] !== 1) {
            echo json_encode(["success" => false, "message" => "No autorizado"]);
            exit();
        }

        require_once __DIR__ . '/../models/Auditoria.php';
        $auditoria = new Auditoria($this->db);

        $filtros = [
            'usuario_doc' => $_GET['usuario_doc'] ?? '',
            'accion' => $_GET['accion'] ?? '',
            'fecha_desde' => $_GET['fecha_desde'] ?? '',
            'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
            'tabla' => $_GET['tabla'] ?? '',
        ];

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $logs = $auditoria->getLogs($filtros, $perPage, $offset);
        $total = $auditoria->countLogs($filtros);

        echo json_encode([
            "success" => true,
            "logs" => $logs,
            "total" => $total,
            "page" => $page,
            "perPage" => $perPage,
            "totalPages" => ceil($total / $perPage)
        ]);
        exit();
    }

}
?>
