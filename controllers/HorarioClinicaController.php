<?php
require_once __DIR__ . "/../config/Database.php";

class HorarioClinicaController
{
    private $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    private function requireAuth()
    {
        if (!isset($_SESSION["usuario_doc"]) || $_SESSION["usuario_id_rol"] != 1) {
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "message" => "No autorizado",
            ]);
            exit();
        }
    }

    public function getHorariosAjax()
    {
        header("Content-Type: application/json");
        $this->requireAuth();
        
        try {
            $query = "SELECT * FROM horarios_clinica ORDER BY dia_semana ASC";
            $stmt = $this->db->query($query);
            $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(["success" => true, "horarios" => $horarios]);
        } catch (Exception $e) {
            echo json_encode([
                "success" => false,
                "message" => $e->getMessage(),
            ]);
        }
        exit();
    }

    public function guardarHorariosAjax()
    {
        header("Content-Type: application/json");
        $this->requireAuth();
        
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            echo json_encode(["success" => false, "message" => "Método no permitido"]);
            exit();
        }

        try {
            $horarios = $_POST["horarios"] ?? [];
            
            foreach ($horarios as $dia_semana => $horario) {
                $activo = $horario["activo"] ?? 0;
                $morningActivo   = $horario["morning_activo"]   ?? 1;
                $afternoonActivo = $horario["afternoon_activo"] ?? 1;
                $morningInicio = $horario["morning_inicio"] ?? null;
                $morningFin = $horario["morning_fin"] ?? null;
                $afternoonInicio = $horario["afternoon_inicio"] ?? null;
                $afternoonFin = $horario["afternoon_fin"] ?? null;
                
                // Validar bloques de mañana (6:00 AM - 11:59 AM) - solo si el bloque está activo
                if ($activo == 1 && $morningActivo == 1 && $morningInicio && $morningFin) {
                    $startHour = (int)substr($morningInicio, 0, 2);
                    $endHour = (int)substr($morningFin, 0, 2);
                    
                    if ($startHour < 6 || $startHour > 11) {
                        echo json_encode([
                            "success" => false,
                            "message" => "El bloque de mañana debe estar entre 6:00 AM y 11:59 AM",
                        ]);
                        exit();
                    }
                    
                    $endMinute = (int)substr($morningFin, 3, 2);
                    if ($endHour < 6 || $endHour > 12 || ($endHour === 12 && $endMinute > 0)) {
                        echo json_encode([
                            "success" => false,
                            "message" => "El bloque de mañana debe estar entre 6:00 AM y 12:00 PM (mediodía)",
                        ]);
                        exit();
                    }
                    
                    if ($morningFin <= $morningInicio) {
                        echo json_encode([
                            "success" => false,
                            "message" => "El horario de mañana: la hora fin debe ser posterior a la hora inicio",
                        ]);
                        exit();
                    }
                }
                
                // Validar bloques de tarde (12:00 PM - 9:00 PM)
                if ($activo == 1 && $afternoonActivo == 1 && $afternoonInicio && $afternoonFin) {
                    $startHour = (int)substr($afternoonInicio, 0, 2);
                    $endHour = (int)substr($afternoonFin, 0, 2);
                    
                    if ($startHour < 12 || $startHour >= 21) {
                        echo json_encode([
                            "success" => false,
                            "message" => "El bloque de tarde debe estar entre 12:00 PM y 9:00 PM",
                        ]);
                        exit();
                    }
                    
                    if ($endHour < 12 || $endHour >= 21) {
                        echo json_encode([
                            "success" => false,
                            "message" => "El bloque de tarde debe estar entre 12:00 PM y 9:00 PM",
                        ]);
                        exit();
                    }
                    
                    if ($afternoonFin <= $afternoonInicio) {
                        echo json_encode([
                            "success" => false,
                            "message" => "El horario de tarde: la hora fin debe ser posterior a la hora inicio",
                        ]);
                        exit();
                    }
                }
                
                // Validar que los bloques no se superpongan
                if ($activo == 1 && $morningActivo == 1 && $afternoonActivo == 1 && $morningInicio && $morningFin && $afternoonInicio && $afternoonFin) {
                    if ($morningFin > $afternoonInicio) {
                        echo json_encode([
                            "success" => false,
                            "message" => "Los bloques de mañana y tarde no deben superponerse",
                        ]);
                        exit();
                    }
                }
                
                // Verificar si ya existe el horario para este día
                $checkQuery = "SELECT id FROM horarios_clinica WHERE dia_semana = ?";
                $checkStmt = $this->db->prepare($checkQuery);
                $checkStmt->execute([$dia_semana]);
                $existing = $checkStmt->fetch();
                
                if ($existing) {
                    // Actualizar
                    $updateQuery = "UPDATE horarios_clinica SET activo = ?, bloque_morning_activo = ?, bloque_afternoon_activo = ?, bloque_morning_inicio = ?, bloque_morning_fin = ?, bloque_afternoon_inicio = ?, bloque_afternoon_fin = ? WHERE dia_semana = ?";
                    $updateStmt = $this->db->prepare($updateQuery);
                    $updateStmt->execute([$activo, $morningActivo, $afternoonActivo, $morningInicio, $morningFin, $afternoonInicio, $afternoonFin, $dia_semana]);
                } else {
                    // Insertar
                    $insertQuery = "INSERT INTO horarios_clinica (dia_semana, activo, bloque_morning_activo, bloque_afternoon_activo, bloque_morning_inicio, bloque_morning_fin, bloque_afternoon_inicio, bloque_afternoon_fin) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $insertStmt = $this->db->prepare($insertQuery);
                    $insertStmt->execute([$dia_semana, $activo, $morningActivo, $afternoonActivo, $morningInicio, $morningFin, $afternoonInicio, $afternoonFin]);
                }
            }
            
            echo json_encode(["success" => true, "message" => "Horarios guardados correctamente"]);
        } catch (Exception $e) {
            echo json_encode([
                "success" => false,
                "message" => $e->getMessage(),
            ]);
        }
        exit();
    }

    public function restaurarPorDefectoAjax()
    {
        header("Content-Type: application/json");
        $this->requireAuth();
        
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            echo json_encode(["success" => false, "message" => "Método no permitido"]);
            exit();
        }

        try {
            $this->db->beginTransaction();
            // Eliminar todos los horarios existentes
            $this->db->query("DELETE FROM horarios_clinica");

            // Insertar horarios por defecto con bloques de mañana y tarde
            $query = "INSERT INTO horarios_clinica (dia_semana, activo, bloque_morning_activo, bloque_afternoon_activo, bloque_morning_inicio, bloque_morning_fin, bloque_afternoon_inicio, bloque_afternoon_fin) VALUES
                (1, 1, 1, 1, '08:00:00', '12:00:00', '14:00:00', '18:00:00'),
                (2, 1, 1, 1, '08:00:00', '12:00:00', '14:00:00', '18:00:00'),
                (3, 1, 1, 1, '08:00:00', '12:00:00', '14:00:00', '18:00:00'),
                (4, 1, 1, 1, '08:00:00', '12:00:00', '14:00:00', '18:00:00'),
                (5, 1, 1, 1, '08:00:00', '12:00:00', '14:00:00', '18:00:00'),
                (6, 0, 1, 0, '08:00:00', '12:00:00', NULL, NULL),
                (7, 0, 0, 0, NULL, NULL, NULL, NULL)";
            $this->db->query($query);
            $this->db->commit();
            
            echo json_encode(["success" => true, "message" => "Horarios restaurados por defecto"]);
        } catch (Exception $e) {
            echo json_encode([
                "success" => false,
                "message" => $e->getMessage(),
            ]);
        }
        exit();
    }

    public function getHorarioDisponible($dia_semana)
    {
        try {
            $query = "SELECT * FROM horarios_clinica WHERE dia_semana = ? AND activo = 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$dia_semana]);
            $horario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $horario;
        } catch (Exception $e) {
            return null;
        }
    }

    public function validarHorarioLaboral($fecha, $hora)
    {
        try {
            // Obtener el día de la semana (1=Lunes, 7=Domingo)
            $dia_semana = date('N', strtotime($fecha));
            
            // Obtener el horario configurado para ese día
            $query = "SELECT * FROM horarios_clinica WHERE dia_semana = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$dia_semana]);
            $horario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Si no existe el horario o el día está inactivo
            if (!$horario || $horario['activo'] != 1) {
                return [
                    'valido' => false,
                    'mensaje' => 'El día seleccionado no es laborable'
                ];
            }
            
            // Validar que la hora esté dentro de los bloques configurados
            $hora_time = strtotime($hora);
            
            // Validar bloque de mañana
            if ($horario['bloque_morning_inicio'] && $horario['bloque_morning_fin']) {
                $morning_inicio = strtotime($horario['bloque_morning_inicio']);
                $morning_fin = strtotime($horario['bloque_morning_fin']);
                
                if ($hora_time >= $morning_inicio && $hora_time < $morning_fin) {
                    return [
                        'valido' => true,
                        'mensaje' => 'Horario válido'
                    ];
                }
            }
            
            // Validar bloque de tarde
            if ($horario['bloque_afternoon_inicio'] && $horario['bloque_afternoon_fin']) {
                $afternoon_inicio = strtotime($horario['bloque_afternoon_inicio']);
                $afternoon_fin = strtotime($horario['bloque_afternoon_fin']);
                
                if ($hora_time >= $afternoon_inicio && $hora_time < $afternoon_fin) {
                    return [
                        'valido' => true,
                        'mensaje' => 'Horario válido'
                    ];
                }
            }
            
            // La hora no está dentro de ningún bloque laboral
            return [
                'valido' => false,
                'mensaje' => 'La hora seleccionada está fuera del horario laboral'
            ];
            
        } catch (Exception $e) {
            return [
                'valido' => false,
                'mensaje' => 'Error al validar horario: ' . $e->getMessage()
            ];
        }
    }

    public function obtenerHorasDisponibles($fecha, $intervalo = 30)
    {
        try {
            $dia_semana = date('N', strtotime($fecha));

            $query = "SELECT * FROM horarios_clinica WHERE dia_semana = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$dia_semana]);
            $horario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$horario || $horario['activo'] != 1) {
                return [];
            }

            $horas = [];
            // Usar el intervalo proporcionado (por defecto 30 min para coincidir con sugerencias)
            $intervalo = max(15, intval($intervalo));

            // Generar horas del bloque de mañana
            if ($horario['bloque_morning_inicio'] && $horario['bloque_morning_fin']) {
                $inicio = strtotime($horario['bloque_morning_inicio']);
                $fin = strtotime($horario['bloque_morning_fin']);

                while ($inicio < $fin) {
                    $horas[] = date('H:i', $inicio);
                    $inicio = strtotime("+$intervalo minutes", $inicio);
                }
            }

            // Generar horas del bloque de tarde
            if ($horario['bloque_afternoon_inicio'] && $horario['bloque_afternoon_fin']) {
                $inicio = strtotime($horario['bloque_afternoon_inicio']);
                $fin = strtotime($horario['bloque_afternoon_fin']);

                while ($inicio < $fin) {
                    $horas[] = date('H:i', $inicio);
                    $inicio = strtotime("+$intervalo minutes", $inicio);
                }
            }

            return $horas;

        } catch (Exception $e) {
            return [];
        }
    }

    public function obtenerHorasDisponiblesAjax()
    {
        header("Content-Type: application/json");

        if (!isset($_SESSION["usuario_doc"])) {
            echo json_encode(["success" => false, "message" => "No autorizado"]);
            exit();
        }

        $fecha = $_GET["fecha"] ?? null;
        $intervalo = $_GET["intervalo"] ?? 30; // Intervalo en minutos (default 30)

        if (!$fecha) {
            echo json_encode(["success" => false, "message" => "Fecha no proporcionada"]);
            exit();
        }

        $horas = $this->obtenerHorasDisponibles($fecha, $intervalo);

        echo json_encode(["success" => true, "horas" => $horas]);
        exit();
    }
}
?>
