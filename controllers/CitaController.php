<?php
require_once '../config/Database.php';
require_once '../models/Cita.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class CitaController {
    private $db;
    private $model;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->model = new Cita($this->db);
    }

    public function registrarAjax() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id_tipo_cita = $_POST['id_tipo_cita'] ?? null;
            
            // Obtener duración del tipo de cita
            $duracion_minutos = 30; // Valor por defecto
            if ($id_tipo_cita) {
                $tipo_cita = $this->model->getTipoCitaById($id_tipo_cita);
                if ($tipo_cita) {
                    $duracion_minutos = $tipo_cita['duracion_minutos'];
                }
            }

            $data = [
                'id_mascota' => $_POST['id_mascota'],
                'doc_veterinario' => $_POST['doc_veterinario'],
                'fecha' => $_POST['fecha'],
                'hora' => $_POST['hora'],
                'motivo' => $_POST['motivo'],
                'id_tipo_cita' => $id_tipo_cita,
                'duracion_minutos' => $duracion_minutos,
                'estado' => 'confirmada' // Confirmar automáticamente al agendar
            ];

            // Restringir veterinarios: solo pueden agendar para sí mismos
            $rolUsuario = $_SESSION['usuario_id_rol'] ?? null;
            if ((int)$rolUsuario === 2) {
                $docSesion = $_SESSION['usuario_doc'] ?? null;
                if (empty($docSesion)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'No se pudo identificar al veterinario que agenda la cita.'
                    ]);
                    exit;
                }

                if (!empty($data['doc_veterinario']) && $data['doc_veterinario'] !== $docSesion) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'No puedes agendar citas para otros veterinarios.'
                    ]);
                    exit;
                }

                $data['doc_veterinario'] = $docSesion;
            }

            if (empty($data['doc_veterinario'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Selecciona el veterinario responsable de la cita.'
                ]);
                exit;
            }

            // 0. Validar horario laboral configurado
            require_once __DIR__ . '/HorarioClinicaController.php';
            $horarioController = new HorarioClinicaController();
            $validacion = $horarioController->validarHorarioLaboral($data['fecha'], $data['hora']);

            if (!$validacion['valido']) {
                echo json_encode(['success' => false, 'message' => $validacion['mensaje']]);
                exit;
            }

            // 0.5 Validar que la hora no haya pasado si es hoy (zona horaria Colombia)
            date_default_timezone_set('America/Bogota');
            $fecha_cita = $data['fecha'];
            $hora_cita = $data['hora'];
            $hoy = date('Y-m-d');
            if ($fecha_cita === $hoy) {
                $hora_actual = date('H:i');
                if ($hora_cita < $hora_actual) {
                    echo json_encode(['success' => false, 'message' => 'No puedes agendar citas en horas que ya pasaron. Son las ' . $hora_actual . '.']);
                    exit;
                }
            }

            // 1. Verificar disponibilidad con rangos de tiempo
            if (!$this->model->checkDisponibilidad($data['doc_veterinario'], $data['fecha'], $data['hora'], $data['duracion_minutos'])) {
                echo json_encode(['success' => false, 'message' => 'El veterinario no está disponible en ese horario. Hay solapamiento con otra cita.']);
                exit;
            }

            // 2. Insertar cita
            $id_cita = $this->model->insert($data);
            if ($id_cita) {
                // Notificar al veterinario (si no es él mismo quien agendó, o si se desea notificar siempre)
                require_once '../models/NotificacionInterna.php';
                $noti = new NotificacionInterna();
                $noti->crearParaUsuario(
                    $data['doc_veterinario'], 
                    'NUEVA_CITA', 
                    'Nueva cita agendada', 
                    "Tienes una nueva cita programada para el " . date('d/m/Y', strtotime($data['fecha'])) . " a las " . date('H:i', strtotime($data['hora'])), 
                    "index.php?action=vet_agenda"
                );
                
                echo json_encode(['success' => true, 'message' => 'Cita agendada correctamente. Se enviará un correo de confirmación.', 'id_cita' => $id_cita]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al agendar la cita.']);
            }
            exit;
        }
    }

    public function enviarEmailAjax() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id_cita = $_POST['id_cita'] ?? null;
            $tipo = $_POST['tipo'] ?? 'confirmacion';
            
            if ($id_cita) {
                if ($tipo === 'confirmacion_nueva') {
                    $cita = $this->model->getById($id_cita);
                    if ($cita) {
                        $this->enviarEmailConfirmacion($cita['id_mascota'], $cita['doc_veterinario'], $cita['fecha'], $cita['hora']);
                    }
                } else {
                    $this->enviarEmailNotificacion($id_cita, $tipo);
                }
            }
            echo json_encode(['success' => true]);
            exit;
        }
    }

    public function listarSemanaAjax() {
        require_once '../models/Vacuna.php';
        require_once '../models/Desparasitacion.php';
        
        // Por defecto, muestra desde hoy hasta 7 días adelante
        $fecha_inicio = date('Y-m-d');
        $fecha_fin = date('Y-m-d', strtotime('+7 days'));
        
        if (isset($_GET['inicio']) && isset($_GET['fin'])) {
            $fecha_inicio = $_GET['inicio'];
            $fecha_fin = $_GET['fin'];
        }

        // Filtrar por veterinario SOLO si el usuario es veterinario (rol 2)
        // Admin (rol 1) y Recepcionista (rol 3) pueden ver todas las citas
        $doc_veterinario = null;
        if (isset($_SESSION['usuario_id_rol']) && $_SESSION['usuario_id_rol'] == 2) {
            // Rol 2 es Veterinario - solo ver sus propias citas
            $doc_veterinario = $_SESSION['usuario_doc'];
            error_log("Usuario veterinario logueado: " . $_SESSION['usuario_doc'] . " - Filtrando citas por veterinario");
        } else {
            error_log("Usuario no es veterinario (rol: " . ($_SESSION['usuario_id_rol'] ?? 'no definido') . ") - Mostrando todas las citas");
        }
        // Para admin y recepcionista, $doc_veterinario permanece null (ven todas las citas)

        // Obtener citas
        $citas = $this->model->getByFecha($fecha_inicio, $fecha_fin, $doc_veterinario);
        
        // Obtener vacunaciones (no se filtra por veterinario)
        $vacunaModel = new Vacuna($this->db);
        $queryVacunas = "SELECT v.id_vacuna as id_cita, v.fecha_proxima_dosis as fecha, 'vacunacion' as tipo,
                        v.nombre_vacuna as motivo, m.nombre as mascota_nombre, u.nombre_completo as propietario_nombre,
                        '' as veterinario_nombre, 'pendiente' as estado, m.id_mascota
                        FROM vacunas v
                        JOIN mascotas m ON v.id_mascota = m.id_mascota
                        JOIN usuarios u ON m.doc_propietario = u.documento
                        WHERE v.fecha_proxima_dosis BETWEEN :inicio AND :fin
                        ORDER BY v.fecha_proxima_dosis ASC";
        $stmtVacunas = $this->db->prepare($queryVacunas);
        $stmtVacunas->bindParam(':inicio', $fecha_inicio);
        $stmtVacunas->bindParam(':fin', $fecha_fin);
        $stmtVacunas->execute();
        $vacunas = $stmtVacunas->fetchAll(PDO::FETCH_ASSOC);

        // Obtener desparasitaciones (no se filtra por veterinario)
        $desparasitacionModel = new Desparasitacion($this->db);
        $queryDesparasitaciones = "SELECT d.id_desparasitacion as id_cita, d.fecha_proxima as fecha, 'desparasitacion' as tipo,
                                  CONCAT(d.tipo, ' - ', d.producto) as motivo, m.nombre as mascota_nombre, u.nombre_completo as propietario_nombre,
                                  '' as veterinario_nombre, 'pendiente' as estado, m.id_mascota
                                  FROM desparasitaciones d
                                  JOIN mascotas m ON d.id_mascota = m.id_mascota
                                  JOIN usuarios u ON m.doc_propietario = u.documento
                                  WHERE d.fecha_proxima BETWEEN :inicio AND :fin
                                  ORDER BY d.fecha_proxima ASC";
        $stmtDesparasitaciones = $this->db->prepare($queryDesparasitaciones);
        $stmtDesparasitaciones->bindParam(':inicio', $fecha_inicio);
        $stmtDesparasitaciones->bindParam(':fin', $fecha_fin);
        $stmtDesparasitaciones->execute();
        $desparasitaciones = $stmtDesparasitaciones->fetchAll(PDO::FETCH_ASSOC);

        // Combinar todos los eventos
        $eventos = array_merge($citas, $vacunas, $desparasitaciones);
        
        header('Content-Type: application/json');
        echo json_encode($eventos);
        exit;
    }

    public function listarCalendarioAjax() {
        require_once '../models/Vacuna.php';
        require_once '../models/Desparasitacion.php';
        
        $fecha_inicio = date('Y-m-d');
        $fecha_fin = date('Y-m-d', strtotime('+30 days'));
        
        if (isset($_GET['inicio']) && isset($_GET['fin'])) {
            $fecha_inicio = $_GET['inicio'];
            $fecha_fin = $_GET['fin'];
        }

        $vacunaModel = new Vacuna($this->db);
        $desparasitacionModel = new Desparasitacion($this->db);

        // Obtener vacunaciones en el rango de fechas
        $queryVacunas = "SELECT v.id_vacuna as id, v.fecha_proxima_dosis as fecha, 'vacunacion' as tipo, 
                        v.nombre_vacuna as motivo, m.nombre as mascota_nombre, u.nombre_completo as propietario_nombre,
                        v.fecha_proxima_dosis as fecha_proxima_dosis
                        FROM vacunas v
                        JOIN mascotas m ON v.id_mascota = m.id_mascota
                        JOIN usuarios u ON m.doc_propietario = u.documento
                        WHERE v.fecha_proxima_dosis BETWEEN :inicio AND :fin
                        ORDER BY v.fecha_proxima_dosis ASC";
        $stmtVacunas = $this->db->prepare($queryVacunas);
        $stmtVacunas->bindParam(':inicio', $fecha_inicio);
        $stmtVacunas->bindParam(':fin', $fecha_fin);
        $stmtVacunas->execute();
        $vacunas = $stmtVacunas->fetchAll(PDO::FETCH_ASSOC);

        // Obtener desparasitaciones en el rango de fechas
        $queryDesparasitaciones = "SELECT d.id_desparasitacion as id, d.fecha_proxima as fecha, 'desparasitacion' as tipo,
                                  CONCAT(d.tipo, ' - ', d.producto) as motivo, m.nombre as mascota_nombre, u.nombre_completo as propietario_nombre,
                                  d.fecha_proxima as fecha_proxima_dosis
                                  FROM desparasitaciones d
                                  JOIN mascotas m ON d.id_mascota = m.id_mascota
                                  JOIN usuarios u ON m.doc_propietario = u.documento
                                  WHERE d.fecha_proxima BETWEEN :inicio AND :fin
                                  ORDER BY d.fecha_proxima ASC";
        $stmtDesparasitaciones = $this->db->prepare($queryDesparasitaciones);
        $stmtDesparasitaciones->bindParam(':inicio', $fecha_inicio);
        $stmtDesparasitaciones->bindParam(':fin', $fecha_fin);
        $stmtDesparasitaciones->execute();
        $desparasitaciones = $stmtDesparasitaciones->fetchAll(PDO::FETCH_ASSOC);

        // Combinar ambos resultados
        $eventos = array_merge($vacunas, $desparasitaciones);
        
        header('Content-Type: application/json');
        echo json_encode($eventos);
        exit;
    }

    public function listarVeterinariosAjax() {
        $query = "SELECT documento, nombre_completo FROM usuarios WHERE id_rol = 2 AND estado = 1"; // Asumiendo que 2 es Veterinario
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $vets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode($vets);
        exit;
    }

    public function listarTiposCitaAjax() {
        try {
            $tipos = $this->model->getTiposCita();
            error_log('Tipos de cita obtenidos: ' . json_encode($tipos));
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'tipos' => $tipos]);
            exit;
        } catch (Exception $e) {
            error_log('Error en listarTiposCitaAjax: ' . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    public function getCitaAjax() {
        $id = $_GET['id'] ?? null;
        if ($id) {
            $cita = $this->model->getById($id);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'cita' => $cita]);
            exit;
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
        exit;
    }

    public function getSugerenciasHorarioAjax() {
        if (isset($_GET['doc_veterinario']) && isset($_GET['fecha']) && isset($_GET['duracion_minutos'])) {
            $doc_veterinario = $_GET['doc_veterinario'];
            $fecha = $_GET['fecha'];
            $duracion_minutos = $_GET['duracion_minutos'];
            $id_cita_excluir = $_GET['id_cita_excluir'] ?? null;
            $modo = $_GET['modo'] ?? 'normal'; // 'normal', 'cascada', 'escalonado'
            
            $sugerencias = $this->model->getSugerenciasHorario($doc_veterinario, $fecha, $duracion_minutos, $id_cita_excluir);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'sugerencias' => $sugerencias]);
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'Parámetros incompletos']);
        exit;
    }

    public function generarBloquesCatchupAjax() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $doc_veterinario = $_POST['doc_veterinario'];
            $fecha = $_POST['fecha'];
            
            $bloques_creados = $this->model->generarBloquesCatchupAutomaticos($doc_veterinario, $fecha);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'bloques_creados' => $bloques_creados]);
            exit;
        }
    }

    public function getBloquesCatchupAjax() {
        if (isset($_GET['doc_veterinario']) && isset($_GET['fecha'])) {
            $doc_veterinario = $_GET['doc_veterinario'];
            $fecha = $_GET['fecha'];
            
            $bloques = $this->model->getBloquesCatchup($doc_veterinario, $fecha);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'bloques' => $bloques]);
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'Parámetros incompletos']);
        exit;
    }

    public function reprogramarCitaAjax() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
            exit;
        }

        // Solo Administradores pueden usar drag & drop
        if (!isset($_SESSION['usuario_id_rol']) || (int)$_SESSION['usuario_id_rol'] !== 1) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'No tienes permiso para realizar esta acción. Solo los Administradores pueden reprogramar citas mediante arrastrar y soltar.']);
            exit;
        }

        $id_cita = $_POST['id_cita'] ?? null;
        $fecha   = $_POST['fecha']   ?? null;
        $hora    = $_POST['hora']    ?? null;

        if (!$id_cita || !$fecha || !$hora) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Parámetros incompletos: se requieren id_cita, fecha y hora.']);
            exit;
        }

        // Validar horario laboral configurado
        require_once __DIR__ . '/HorarioClinicaController.php';
        $horarioController = new HorarioClinicaController();
        $validacion = $horarioController->validarHorarioLaboral($fecha, $hora);
        
        if (!$validacion['valido']) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $validacion['mensaje']]);
            exit;
        }

        // Obtener los datos actuales de la cita para completar los campos no modificados
        $cita_actual = $this->model->getById($id_cita);
        if (!$cita_actual) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Cita no encontrada.']);
            exit;
        }

        // Mantener los campos no modificados de la cita original
        $doc_veterinario = $cita_actual['doc_veterinario'];
        $motivo          = $cita_actual['motivo'];
        $id_tipo_cita    = $cita_actual['id_tipo_cita'] ?? null;
        $duracion_minutos = $cita_actual['duracion_minutos'] ?? 30;

        // Verificar disponibilidad excluyendo la cita actual (id_cita_excluir)
        if (!$this->model->checkDisponibilidad($doc_veterinario, $fecha, $hora, $duracion_minutos, $id_cita)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'El horario seleccionado no está disponible para ese veterinario. Hay solapamiento con otra cita.']);
            exit;
        }

        if ($this->model->update($id_cita, $doc_veterinario, $fecha, $hora, $motivo, $id_tipo_cita, $duracion_minutos)) {
            echo json_encode(['success' => true, 'message' => 'Cita reprogramada correctamente. Se notificará al propietario.', 'id_cita' => $id_cita]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al reprogramar la cita.']);
        }
        exit;
    }

    public function actualizarAjax() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id_cita = $_POST['id_cita'];
            $doc_veterinario = $_POST['doc_veterinario'];
            $fecha = $_POST['fecha'];
            $hora = $_POST['hora'];
            $motivo = $_POST['motivo'];
            $id_tipo_cita = $_POST['id_tipo_cita'] ?? null;

            // Validar horario laboral configurado
            require_once __DIR__ . '/HorarioClinicaController.php';
            $horarioController = new HorarioClinicaController();
            $validacion = $horarioController->validarHorarioLaboral($fecha, $hora);
            
            if (!$validacion['valido']) {
                echo json_encode(['success' => false, 'message' => $validacion['mensaje']]);
                exit;
            }

            // Verificar permisos: veterinarios solo pueden modificar sus propias citas
            if (isset($_SESSION['usuario_id_rol']) && $_SESSION['usuario_id_rol'] == 2) {
                // Es veterinario, verificar que la cita le pertenezca
                $cita_actual = $this->model->getById($id_cita);
                if (!$cita_actual) {
                    echo json_encode(['success' => false, 'message' => 'Cita no encontrada.']);
                    exit;
                }
                if ($cita_actual['doc_veterinario'] !== $_SESSION['usuario_doc']) {
                    echo json_encode(['success' => false, 'message' => 'No tienes permiso para modificar esta cita. Solo puedes modificar tus propias citas.']);
                    exit;
                }
            }

            $cita_actual = $this->model->getById($id_cita);
            if (!$cita_actual) {
                echo json_encode(['success' => false, 'message' => 'Cita no encontrada.']);
                exit;
            }

            // Obtener duración del tipo de cita
            $duracion_minutos = $cita_actual['duracion_minutos'] ?? 30;
            if ($id_tipo_cita) {
                $tipo_cita = $this->model->getTipoCitaById($id_tipo_cita);
                if ($tipo_cita) {
                    $duracion_minutos = $tipo_cita['duracion_minutos'];
                }
            }

            // Verificar disponibilidad si cambió la fecha, hora o el veterinario
            if ($cita_actual['fecha'] !== $fecha || $cita_actual['hora'] !== $hora || $cita_actual['doc_veterinario'] !== $doc_veterinario) {
                if (!$this->model->checkDisponibilidadConCatchup($doc_veterinario, $fecha, $hora, $duracion_minutos, $id_cita)) {
                    echo json_encode(['success' => false, 'message' => 'El horario seleccionado ya no está disponible para ese veterinario. Hay solapamiento con otra cita o bloque catch-up.']);
                    exit;
                }
            }

            if ($this->model->update($id_cita, $doc_veterinario, $fecha, $hora, $motivo, $id_tipo_cita, $duracion_minutos)) {
                echo json_encode(['success' => true, 'message' => 'Cita reprogramada correctamente. Se notificará al propietario.', 'id_cita' => $id_cita]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al reprogramar la cita.']);
            }
            exit;
        }
    }

    public function cancelarAjax() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id_cita = $_POST['id_cita'];

            // Verificar permisos:
            if (isset($_SESSION['usuario_id_rol'])) {
                $rolUsuario = (int)$_SESSION['usuario_id_rol'];
                $docSesion = $_SESSION['usuario_doc'] ?? null;
                
                if ($rolUsuario == 2) {
                    // Es veterinario, verificar que la cita le pertenezca
                    $cita_actual = $this->model->getById($id_cita);
                    if (!$cita_actual) {
                        echo json_encode(['success' => false, 'message' => 'Cita no encontrada.']);
                        exit;
                    }
                    if ($cita_actual['doc_veterinario'] !== $docSesion) {
                        echo json_encode(['success' => false, 'message' => 'No tienes permiso para cancelar esta cita. Solo puedes cancelar tus propias citas.']);
                        exit;
                    }
                } elseif ($rolUsuario == 4) {
                    // Es propietario, verificar que la mascota de la cita le pertenezca
                    $cita_actual = $this->model->getById($id_cita);
                    if (!$cita_actual) {
                        echo json_encode(['success' => false, 'message' => 'Cita no encontrada.']);
                        exit;
                    }
                    require_once '../models/Mascota.php';
                    $mascotaModel = new Mascota($this->db);
                    $mascota = $mascotaModel->getById($cita_actual['id_mascota']);
                    if (!$mascota || $mascota['doc_propietario'] !== $docSesion) {
                        echo json_encode(['success' => false, 'message' => 'No tienes permiso para cancelar esta cita. Esta mascota no te pertenece.']);
                        exit;
                    }
                }
            }

            if ($this->model->cambiarEstado($id_cita, 'cancelada')) {
                require_once '../models/NotificacionInterna.php';
                $noti = new NotificacionInterna();
                $cita = $this->model->getById($id_cita);
                if ($cita) {
                    $noti->crearParaUsuario($cita['doc_veterinario'], 'CITA_CANCELADA', 'Cita cancelada', 'Se ha cancelado la cita programada para el ' . date('d/m/Y', strtotime($cita['fecha'])) . ' a las ' . date('H:i', strtotime($cita['hora'])), 'index.php?action=vet_agenda');
                    $noti->crearParaRol(1, 'CITA_CANCELADA', 'Cita cancelada', 'Se ha cancelado una cita del veterinario ' . $cita['veterinario_nombre'] . ' para el ' . date('d/m/Y', strtotime($cita['fecha'])) . ' a las ' . date('H:i', strtotime($cita['hora'])), 'index.php?action=admin_citas');
                }
                echo json_encode(['success' => true, 'message' => 'Cita cancelada correctamente.', 'id_cita' => $id_cita]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al cancelar la cita.']);
            }
            exit;
        }
    }

    public function iniciarAtencionAjax() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            exit;
        }

        $id_cita = $_POST['id_cita'] ?? null;
        if (!$id_cita) {
            echo json_encode(['success' => false, 'message' => 'Identificador de cita no proporcionado.']);
            exit;
        }

        $cita = $this->model->getById($id_cita);
        if (!$cita) {
            echo json_encode(['success' => false, 'message' => 'Cita no encontrada.']);
            exit;
        }

        $rolUsuario = $_SESSION['usuario_id_rol'] ?? null;
        $docSesion  = $_SESSION['usuario_doc'] ?? null;

        if ((int)$rolUsuario === 2) {
            if (empty($docSesion) || $cita['doc_veterinario'] !== $docSesion) {
                echo json_encode(['success' => false, 'message' => 'No tienes permiso para iniciar esta atención.']);
                exit;
            }
        } elseif (!in_array((int)$rolUsuario, [1, 3], true)) {
            echo json_encode(['success' => false, 'message' => 'No autorizado para iniciar la atención.']);
            exit;
        }

        if ($cita['estado'] === 'cancelada') {
            echo json_encode(['success' => false, 'message' => 'La cita se encuentra cancelada.']);
            exit;
        }

        if ($cita['estado'] === 'completada') {
            echo json_encode(['success' => false, 'message' => 'La cita ya fue completada.']);
            exit;
        }

        if ($this->model->cambiarEstado($id_cita, 'en_curso')) {
            echo json_encode([
                'success' => true,
                'message' => 'Atención iniciada. Redirigiendo...',
                'estado' => 'en_curso',
                'redirect_url' => 'index.php?action=vet_atencion&id_cita=' . $id_cita
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No se pudo iniciar la atención. Intenta nuevamente.']);
        }
        exit;
    }

    public function completarAtencionAjax() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            exit;
        }

        $id_cita = $_POST['id_cita'] ?? null;
        if (!$id_cita) {
            echo json_encode(['success' => false, 'message' => 'Identificador de cita no proporcionado.']);
            exit;
        }

        $cita = $this->model->getById($id_cita);
        if (!$cita) {
            echo json_encode(['success' => false, 'message' => 'Cita no encontrada.']);
            exit;
        }

        $rolUsuario = $_SESSION['usuario_id_rol'] ?? null;
        $docSesion  = $_SESSION['usuario_doc'] ?? null;

        if ((int)$rolUsuario === 2) {
            if (empty($docSesion) || $cita['doc_veterinario'] !== $docSesion) {
                echo json_encode(['success' => false, 'message' => 'No tienes permiso para completar esta cita.']);
                exit;
            }
        } elseif (!in_array((int)$rolUsuario, [1, 3], true)) {
            echo json_encode(['success' => false, 'message' => 'No autorizado para completar la cita.']);
            exit;
        }

        if ($cita['estado'] === 'cancelada') {
            echo json_encode(['success' => false, 'message' => 'La cita se encuentra cancelada.']);
            exit;
        }

        if ($cita['estado'] === 'completada') {
            echo json_encode(['success' => true, 'message' => 'La cita ya estaba marcada como completada.', 'estado' => 'completada']);
            exit;
        }

        // Validación: solo se puede completar si la atención fue iniciada
        if ($cita['estado'] !== 'en_curso') {
            echo json_encode([
                'success' => false,
                'message' => 'No puedes completar esta cita porque la atención aún no ha sido iniciada. Haz clic en "Iniciar Atención" primero.',
                'estado_actual' => $cita['estado']
            ]);
            exit;
        }

        if ($this->model->cambiarEstado($id_cita, 'completada')) {
            // Verificar si existe consulta vinculada
            require_once '../models/Consulta.php';
            $consultaModel = new Consulta($this->db);
            $consulta = $consultaModel->findByCita($id_cita);

            $response = [
                'success' => true,
                'message' => 'Cita marcada como completada.',
                'estado' => 'completada'
            ];
            if ($consulta) {
                $response['id_consulta'] = $consulta['id_consulta'];
                $response['message'] .= ' Consulta #' . $consulta['id_consulta'] . ' vinculada.';
            }
            echo json_encode($response);
        } else {
            echo json_encode(['success' => false, 'message' => 'No se pudo completar la cita. Intenta nuevamente.']);
        }
        exit;
    }

    public function confirmarAjax() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id_cita = $_POST['id_cita'];

            // Verificar permisos: veterinarios solo pueden confirmar sus propias citas
            if (isset($_SESSION['usuario_id_rol']) && $_SESSION['usuario_id_rol'] == 2) {
                // Es veterinario, verificar que la cita le pertenezca
                $cita_actual = $this->model->getById($id_cita);
                if (!$cita_actual) {
                    echo json_encode(['success' => false, 'message' => 'Cita no encontrada.']);
                    exit;
                }
                if ($cita_actual['doc_veterinario'] !== $_SESSION['usuario_doc']) {
                    echo json_encode(['success' => false, 'message' => 'No tienes permiso para confirmar esta cita. Solo puedes confirmar tus propias citas.']);
                    exit;
                }
            }

            if ($this->model->cambiarEstado($id_cita, 'confirmada')) {
                echo json_encode(['success' => true, 'message' => 'Cita confirmada y notificada al propietario.', 'id_cita' => $id_cita]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al confirmar la cita.']);
            }
            exit;
        }
    }

    private function enviarEmailConfirmacion($id_mascota, $doc_vet, $fecha, $hora) {
        // Obtener datos del propietario y mascota
        $query = "SELECT m.nombre as mascota, u.nombre_completo as propietario, u.email, v.nombre_completo as veterinario 
                  FROM mascotas m 
                  JOIN usuarios u ON m.doc_propietario = u.documento
                  JOIN usuarios v ON v.documento = :doc_vet
                  WHERE m.id_mascota = :id_mascota";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':id_mascota' => $id_mascota, ':doc_vet' => $doc_vet]);
        $info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$info || empty($info['email'])) {
            return false;
        }

        return $this->mandarPHPMailer($info['email'], $info['propietario'], 'Confirmación de Cita Veterinaria - Zooki', 
            $this->generarCuerpoEmail('¡Cita Confirmada!', $info, $fecha, $hora, '#10B981'));
    }

    private function enviarEmailNotificacion($id_cita, $tipo) {
        $cita = $this->model->getById($id_cita);
        if (!$cita || empty($cita['email'])) return false;

        $info = [
            'propietario' => $cita['propietario_nombre'],
            'mascota' => $cita['mascota_nombre'],
            'veterinario' => $cita['veterinario_nombre']
        ];

        if ($tipo === 'reprogramacion') {
            return $this->mandarPHPMailer($cita['email'], $info['propietario'], 'Actualización de Cita Veterinaria - Zooki',
                $this->generarCuerpoEmail('Tu cita ha sido reprogramada 🕒', $info, $cita['fecha'], $cita['hora'], '#F59E0B'));
        } else if ($tipo === 'confirmacion') {
            return $this->mandarPHPMailer($cita['email'], $info['propietario'], 'Confirmación de Cita - Zooki',
                $this->generarCuerpoEmail('Tu cita ha sido confirmada ✅', $info, $cita['fecha'], $cita['hora'], '#10B981'));
        } else {
            return $this->mandarPHPMailer($cita['email'], $info['propietario'], 'Cancelación de Cita Veterinaria - Zooki',
                $this->generarCuerpoEmail('Tu cita ha sido cancelada ❌', $info, $cita['fecha'], $cita['hora'], '#EF4444', 'La cita programada ha sido cancelada. Por favor, contáctanos si deseas reprogramarla.'));
        }
    }

    private function generarCuerpoEmail($titulo, $info, $fecha, $hora, $colorBorde, $mensajeExtra = "Te esperamos puntual para brindar el mejor cuidado a tu mascota.") {
        return "
        <div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: auto; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;'>
            <div style='background-color: #0f172a; padding: 20px; text-align: center; color: white;'>
                <h2>Zooki - $titulo</h2>
            </div>
            <div style='padding: 20px;'>
                <p>Hola <strong>{$info['propietario']}</strong>,</p>
                <p>Detalles sobre la cita para <strong>{$info['mascota']}</strong>:</p>
                
                <div style='background-color: #f8fafc; padding: 15px; border-left: 4px solid $colorBorde; margin: 20px 0;'>
                    <p style='margin: 5px 0;'><strong>Fecha:</strong> " . date('d/m/Y', strtotime($fecha)) . "</p>
                    <p style='margin: 5px 0;'><strong>Hora:</strong> $hora</p>
                    <p style='margin: 5px 0;'><strong>Veterinario:</strong> Dr(a). {$info['veterinario']}</p>
                </div>
                
                <p>$mensajeExtra</p>
            </div>
        </div>";
    }

    private function mandarPHPMailer($dest_email, $dest_nombre, $asunto, $cuerpo) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'zooki.vet@gmail.com';
            $mail->Password   = 'rpnccdwtrglvukff';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom('no-reply@zooki.com', 'Clínica Veterinaria Zooki');
            $mail->addAddress($dest_email, $dest_nombre);
            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body = $cuerpo;
            $mail->send();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function listarTodasCitasAjax() {
        // Solo admin puede ver todas las citas
        if (!isset($_SESSION['usuario_id_rol']) || $_SESSION['usuario_id_rol'] != 1) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit;
        }

        // Verificar si la tabla tipos_cita existe
        $checkTable = $this->db->query("SHOW TABLES LIKE 'tipos_cita'");
        $tableExists = $checkTable->rowCount() > 0;

        if ($tableExists) {
            $query = "SELECT c.id_cita, c.fecha, c.hora, c.motivo, c.estado, c.doc_veterinario, c.id_tipo_cita,
                      m.nombre as mascota_nombre, u.nombre_completo as propietario_nombre,
                      v.nombre_completo as veterinario_nombre,
                      tc.nombre_tipo as tipo_cita_nombre
                      FROM citas c
                      JOIN mascotas m ON c.id_mascota = m.id_mascota
                      JOIN usuarios u ON m.doc_propietario = u.documento
                      JOIN usuarios v ON c.doc_veterinario = v.documento
                      LEFT JOIN tipos_cita tc ON c.id_tipo_cita = tc.id_tipo_cita
                      ORDER BY c.fecha DESC, c.hora DESC";
        } else {
            $query = "SELECT c.id_cita, c.fecha, c.hora, c.motivo, c.estado, c.doc_veterinario, c.id_tipo_cita,
                      m.nombre as mascota_nombre, u.nombre_completo as propietario_nombre,
                      v.nombre_completo as veterinario_nombre,
                      NULL as tipo_cita_nombre
                      FROM citas c
                      JOIN mascotas m ON c.id_mascota = m.id_mascota
                      JOIN usuarios u ON m.doc_propietario = u.documento
                      JOIN usuarios v ON c.doc_veterinario = v.documento
                      ORDER BY c.fecha DESC, c.hora DESC";
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'citas' => $citas]);
        exit;
    }

    public function getEventsForCalendar() {
        require_once '../models/Vacuna.php';
        require_once '../models/Desparasitacion.php';
        
        $fecha_inicio = $_GET['inicio'] ?? date('Y-m-d', strtotime('-30 days'));
        $fecha_fin = $_GET['fin'] ?? date('Y-m-d', strtotime('+30 days'));

        // Obtener citas
        $queryCitas = "SELECT c.id_cita, c.fecha, c.hora, c.motivo, c.estado, 'cita' as tipo,
                       m.nombre as mascota_nombre, u.nombre_completo as propietario_nombre,
                       v.nombre_completo as veterinario_nombre
                       FROM citas c
                       JOIN mascotas m ON c.id_mascota = m.id_mascota
                       JOIN usuarios u ON m.doc_propietario = u.documento
                       JOIN usuarios v ON c.doc_veterinario = v.documento
                       WHERE c.fecha BETWEEN :inicio AND :fin
                       ORDER BY c.fecha, c.hora";
        $stmtCitas = $this->db->prepare($queryCitas);
        $stmtCitas->bindParam(':inicio', $fecha_inicio);
        $stmtCitas->bindParam(':fin', $fecha_fin);
        $stmtCitas->execute();
        $citas = $stmtCitas->fetchAll(PDO::FETCH_ASSOC);

        // Obtener vacunaciones
        $queryVacunas = "SELECT v.id_vacuna as id_cita, v.fecha_proxima_dosis as fecha, 'vacunacion' as tipo,
                        v.nombre_vacuna as motivo, m.nombre as mascota_nombre, u.nombre_completo as propietario_nombre,
                        '' as veterinario_nombre, 'pendiente' as estado
                        FROM vacunas v
                        JOIN mascotas m ON v.id_mascota = m.id_mascota
                        JOIN usuarios u ON m.doc_propietario = u.documento
                        WHERE v.fecha_proxima_dosis BETWEEN :inicio AND :fin
                        ORDER BY v.fecha_proxima_dosis ASC";
        $stmtVacunas = $this->db->prepare($queryVacunas);
        $stmtVacunas->bindParam(':inicio', $fecha_inicio);
        $stmtVacunas->bindParam(':fin', $fecha_fin);
        $stmtVacunas->execute();
        $vacunas = $stmtVacunas->fetchAll(PDO::FETCH_ASSOC);

        // Obtener desparasitaciones
        $queryDesparasitaciones = "SELECT d.id_desparasitacion as id_cita, d.fecha_proxima as fecha, 'desparasitacion' as tipo,
                                  CONCAT(d.tipo, ' - ', d.producto) as motivo, m.nombre as mascota_nombre, u.nombre_completo as propietario_nombre,
                                  '' as veterinario_nombre, 'pendiente' as estado
                                  FROM desparasitaciones d
                                  JOIN mascotas m ON d.id_mascota = m.id_mascota
                                  JOIN usuarios u ON m.doc_propietario = u.documento
                                  WHERE d.fecha_proxima BETWEEN :inicio AND :fin
                                  ORDER BY d.fecha_proxima ASC";
        $stmtDesparasitaciones = $this->db->prepare($queryDesparasitaciones);
        $stmtDesparasitaciones->bindParam(':inicio', $fecha_inicio);
        $stmtDesparasitaciones->bindParam(':fin', $fecha_fin);
        $stmtDesparasitaciones->execute();
        $desparasitaciones = $stmtDesparasitaciones->fetchAll(PDO::FETCH_ASSOC);

        // Combinar todos los eventos
        $eventos = array_merge($citas, $vacunas, $desparasitaciones);
        
        header('Content-Type: application/json');
        echo json_encode($eventos);
        exit;
    }

    /**
     * Pantalla integral de atención médica para una cita (HU-19+)
     * Muestra todo del paciente: datos, historial, y permite registrar
     * consulta, vacuna o desparasitación desde un solo lugar.
     */
    public function atencion() {
        $id_cita = $_GET['id_cita'] ?? null;
        if (!$id_cita) {
            header('Location: index.php?action=vet_agenda');
            exit;
        }

        $cita = $this->model->getById($id_cita);
        if (!$cita) {
            header('Location: index.php?action=vet_agenda');
            exit;
        }

        // Validar permiso
        $rol = (int) ($_SESSION['usuario_id_rol'] ?? 0);
        $doc = $_SESSION['usuario_doc'] ?? '';
        if ($rol === 2 && $cita['doc_veterinario'] !== $doc) {
            header('Location: index.php?action=vet_agenda');
            exit;
        }
        if (!in_array($rol, [1, 2, 3], true)) {
            header('Location: index.php?action=login');
            exit;
        }

        // Cargar datos del paciente
        require_once '../models/Mascota.php';
        $mascotaModel = new Mascota($this->db);
        $mascota = $mascotaModel->getById($cita['id_mascota']);

        // Historial de consultas
        require_once '../models/Consulta.php';
        $consultaModel = new Consulta($this->db);
        $consultas = $consultaModel->findByMascota($cita['id_mascota']);

        // Historial de vacunas
        require_once '../models/Vacuna.php';
        $vacunaModel = new Vacuna($this->db);
        $vacunas = $vacunaModel->findByMascota($cita['id_mascota']);

        // Historial de desparasitaciones
        require_once '../models/Desparasitacion.php';
        $despModel = new Desparasitacion($this->db);
        $desparasitaciones = $despModel->findByMascota($cita['id_mascota']);

        // Datos del propietario
        $stmtProp = $this->db->prepare("SELECT documento, nombre_completo, telefono, email FROM usuarios WHERE documento = ?");
        $stmtProp->execute([$mascota['doc_propietario'] ?? '']);
        $propietario = $stmtProp->fetch(PDO::FETCH_ASSOC);

        // Veterinario de la cita
        $stmtVet = $this->db->prepare("SELECT documento, nombre_completo FROM usuarios WHERE documento = ?");
        $stmtVet->execute([$cita['doc_veterinario']]);
        $veterinario = $stmtVet->fetch(PDO::FETCH_ASSOC);

        // Tipos de cita para el selector
        $tiposCita = [];
        try {
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'tipos_cita'");
            if ($tableCheck && $tableCheck->rowCount() > 0) {
                $tiposCita = $this->db
                    ->query("SELECT id_tipo_cita, nombre_tipo, duracion_minutos FROM tipos_cita WHERE estado = 1 ORDER BY nombre_tipo")
                    ->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $ex) {
            $tiposCita = [];
        }

        $content_view = "../views/vet/atencion.php";
        require_once "../views/vet/layout.php";
    }
}
?>
