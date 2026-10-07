<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/HorarioClinica.php';
require_once __DIR__ . '/../models/Auditoria.php';

class HorarioClinicaController
{
    private HorarioClinica $horario;
    private Auditoria $auditoria;

    public function __construct(?PDO $db = null)
    {
        $db ??= (new Database())->getConnection();
        $this->horario = new HorarioClinica($db);
        $this->auditoria = new Auditoria($db);
    }

    private function auditar(string $descripcion): void
    {
        $this->auditoria->log(Contexto::idUsuario(), 'UPDATE', 'horarios_clinica', null,
            null, null, $descripcion, Contexto::clinicaActiva());
    }

    public function getHorariosAjax()
    {
        header("Content-Type: application/json");

        
        try {
            $horarios = $this->horario->listar();

            echo json_encode(["success" => true, "horarios" => $horarios]);
        } catch (Exception $e) {
            echo json_encode([
                "success" => false,
                "message" => "No se pudo procesar el horario de la clínica.",
            ]);
        }
        return;
    }

    public function guardarHorariosAjax()
    {
        header("Content-Type: application/json");

        
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            echo json_encode(["success" => false, "message" => "Método no permitido"]);
            return;
        }

        try {
            $horarios = $_POST["horarios"] ?? [];
            if (!is_array($horarios) || !$horarios) throw new InvalidArgumentException('No hay horarios.');
            $filas = [];
            
            foreach ($horarios as $dia_semana => $horario) {
                if (!ctype_digit((string) $dia_semana) || (int) $dia_semana < 1 || (int) $dia_semana > 7 || !is_array($horario)) {
                    throw new InvalidArgumentException('Día de semana inválido.');
                }
                $activo = (int) ($horario["activo"] ?? 0) === 1 ? 1 : 0;
                // RE-7.1.2: un día cerrado no ofrece bloques aunque conserve sus horas.
                $morningActivo   = $activo === 1 && (int) ($horario["morning_activo"] ?? 1) === 1 ? 1 : 0;
                $afternoonActivo = $activo === 1 && (int) ($horario["afternoon_activo"] ?? 1) === 1 ? 1 : 0;
                // Las horas vacías llegaban como el texto "null" y MySQL las guardaba como 00:00.
                $hora = static fn($v) => (is_string($v) && preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/', $v)) ? $v : null;
                $morningInicio = $hora($horario["morning_inicio"] ?? null);
                $morningFin = $hora($horario["morning_fin"] ?? null);
                $afternoonInicio = $hora($horario["afternoon_inicio"] ?? null);
                $afternoonFin = $hora($horario["afternoon_fin"] ?? null);

                $nombresDia = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo'];
                $nombreDia = $nombresDia[(int) $dia_semana] ?? 'día';
                if (($morningActivo === 1 && (!$morningInicio || !$morningFin)) || ($afternoonActivo === 1 && (!$afternoonInicio || !$afternoonFin))) {
                    echo json_encode([
                        "success" => false,
                        "message" => "Completa la hora de inicio y de fin de los bloques activos del $nombreDia.",
                    ]);
                    return;
                }
                
                // Validar bloques de mañana (6:00 AM - 11:59 AM) - solo si el bloque está activo
                if ($activo == 1 && $morningActivo == 1 && $morningInicio && $morningFin) {
                    $startHour = (int)substr($morningInicio, 0, 2);
                    $endHour = (int)substr($morningFin, 0, 2);
                    
                    if ($startHour < 6 || $startHour > 11) {
                        echo json_encode([
                            "success" => false,
                            "message" => "El bloque de mañana debe estar entre 6:00 AM y 11:59 AM",
                        ]);
                        return;
                    }
                    
                    $endMinute = (int)substr($morningFin, 3, 2);
                    if ($endHour < 6 || $endHour > 12 || ($endHour === 12 && $endMinute > 0)) {
                        echo json_encode([
                            "success" => false,
                            "message" => "El bloque de mañana debe estar entre 6:00 AM y 12:00 PM (mediodía)",
                        ]);
                        return;
                    }
                    
                    if ($morningFin <= $morningInicio) {
                        echo json_encode([
                            "success" => false,
                            "message" => "El horario de mañana: la hora fin debe ser posterior a la hora inicio",
                        ]);
                        return;
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
                        return;
                    }
                    
                    if ($endHour < 12 || $endHour >= 21) {
                        echo json_encode([
                            "success" => false,
                            "message" => "El bloque de tarde debe estar entre 12:00 PM y 9:00 PM",
                        ]);
                        return;
                    }
                    
                    if ($afternoonFin <= $afternoonInicio) {
                        echo json_encode([
                            "success" => false,
                            "message" => "El horario de tarde: la hora fin debe ser posterior a la hora inicio",
                        ]);
                        return;
                    }
                }
                
                // Validar que los bloques no se superpongan
                if ($activo == 1 && $morningActivo == 1 && $afternoonActivo == 1 && $morningInicio && $morningFin && $afternoonInicio && $afternoonFin) {
                    if ($morningFin > $afternoonInicio) {
                        echo json_encode([
                            "success" => false,
                            "message" => "Los bloques de mañana y tarde no deben superponerse",
                        ]);
                        return;
                    }
                }
                
                $filas[] = [(int) $dia_semana, $activo, $morningActivo, $afternoonActivo,
                    $morningInicio, $morningFin, $afternoonInicio, $afternoonFin];
            }
            $this->horario->guardar($filas);
            $this->auditar('Horarios guardados en la clínica activa');

            echo json_encode(["success" => true, "message" => "Horarios guardados correctamente"]);
        } catch (Exception $e) {
            // T-04: el detalle técnico va al log, nunca al cliente.
            error_log('Error guardando horarios: ' . $e->getMessage());
            echo json_encode([
                "success" => false,
                "message" => "No se pudo guardar el horario. Revisa que las horas de los bloques activos sean válidas.",
            ]);
        }
        return;
    }

    public function restaurarPorDefectoAjax()
    {
        header("Content-Type: application/json");

        
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            echo json_encode(["success" => false, "message" => "Método no permitido"]);
            return;
        }

        try {
            $this->horario->restaurar();
            $this->auditar('Horarios restaurados en la clínica activa');

            echo json_encode(["success" => true, "message" => "Horarios restaurados por defecto"]);
        } catch (Exception $e) {
            echo json_encode([
                "success" => false,
                "message" => "No se pudo procesar el horario de la clínica.",
            ]);
        }
        return;
    }

    public function getHorarioDisponible($dia_semana)
    {
        try {
            $horario = $this->horario->dia((int) $dia_semana);
            if (!$horario || (int) $horario['activo'] !== 1) return null;

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
            $horario = $this->horario->dia((int) $dia_semana);
            
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
            if ((int) $horario['bloque_morning_activo'] === 1 && $horario['bloque_morning_inicio'] && $horario['bloque_morning_fin']) {
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
            if ((int) $horario['bloque_afternoon_activo'] === 1 && $horario['bloque_afternoon_inicio'] && $horario['bloque_afternoon_fin']) {
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
                'mensaje' => 'No se pudo validar el horario de la clínica'
            ];
        }
    }

    public function obtenerHorasDisponibles($fecha, $intervalo = 30)
    {
        try {
            $dia_semana = date('N', strtotime($fecha));

            $horario = $this->horario->dia((int) $dia_semana);

            if (!$horario || $horario['activo'] != 1) {
                return [];
            }

            $horas = [];
            // Usar el intervalo proporcionado (por defecto 30 min para coincidir con sugerencias)
            $intervalo = max(15, intval($intervalo));

            // Generar horas del bloque de mañana
            if ((int) $horario['bloque_morning_activo'] === 1 && $horario['bloque_morning_inicio'] && $horario['bloque_morning_fin']) {
                $inicio = strtotime($horario['bloque_morning_inicio']);
                $fin = strtotime($horario['bloque_morning_fin']);

                while ($inicio < $fin) {
                    $horas[] = date('H:i', $inicio);
                    $inicio = strtotime("+$intervalo minutes", $inicio);
                }
            }

            // Generar horas del bloque de tarde
            if ((int) $horario['bloque_afternoon_activo'] === 1 && $horario['bloque_afternoon_inicio'] && $horario['bloque_afternoon_fin']) {
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

        $fecha = $_GET["fecha"] ?? null;
        $intervalo = $_GET["intervalo"] ?? 30; // Intervalo en minutos (default 30)

        if (!$fecha) {
            echo json_encode(["success" => false, "message" => "Fecha no proporcionada"]);
            return;
        }

        $horas = $this->obtenerHorasDisponibles($fecha, $intervalo);

        echo json_encode(["success" => true, "horas" => $horas]);
        return;
    }
}
