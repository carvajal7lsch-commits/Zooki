<?php
require_once '../config/Database.php';
require_once '../models/Cita.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../helpers/ReglaAtencion.php';

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
                'id_mascota' => $_POST['id_mascota'] ?? '',
                'doc_veterinario' => $_POST['doc_veterinario'] ?? '',
                'fecha' => $_POST['fecha'] ?? '',
                'hora' => $_POST['hora'] ?? '',
                'motivo' => $_POST['motivo'] ?? '',
                'id_tipo_cita' => $id_tipo_cita,
                'duracion_minutos' => $duracion_minutos,
                'estado' => 'confirmada' // Confirmar automáticamente al agendar
            ];

            // Sin mascota la cita no se puede guardar: antes seguía de largo y
            // la base de datos rechazaba el INSERT, y el usuario solo veía
            // "error interno del servidor" sin saber qué faltaba.
            if (!ctype_digit((string) $data['id_mascota']) || (int) $data['id_mascota'] <= 0) {
                echo json_encode(['success' => false, 'message' => 'Selecciona la mascota para la que es la cita.']);
                exit;
            }
            if ($data['fecha'] === '' || $data['hora'] === '') {
                echo json_encode(['success' => false, 'message' => 'Selecciona la fecha y uno de los horarios disponibles.']);
                exit;
            }
            $fechaValida = DateTime::createFromFormat('Y-m-d', $data['fecha']);
            if (!$fechaValida || $fechaValida->format('Y-m-d') !== $data['fecha']) {
                echo json_encode(['success' => false, 'message' => 'La fecha de la cita no es válida.']);
                exit;
            }
            // Una cita no se agenda en una fecha pasada (la hora de hoy se revisa
            // más abajo). Antes solo se miraba la hora cuando la fecha era hoy,
            // así que por la API se podía crear una cita para ayer.
            if ($data['fecha'] < $this->ahora()->format('Y-m-d')) {
                echo json_encode(['success' => false, 'message' => 'No puedes agendar citas en fechas que ya pasaron.']);
                exit;
            }

            // RN-G02: el propietario solo agenda para sus mascotas. El portal le
            // ofrece solo las suyas, pero el servidor no lo comprobaba: con un
            // id_mascota ajeno se agendaba una cita a nombre de otro dueño.
            if ((int) ($_SESSION['usuario_id_rol'] ?? 0) === 4) {
                require_once '../models/Mascota.php';
                $mascotaCita = (new Mascota($this->db))->getById($data['id_mascota']);
                if (!$mascotaCita || $mascotaCita['doc_propietario'] !== ($_SESSION['usuario_doc'] ?? null)) {
                    echo json_encode(['success' => false, 'message' => 'Esa mascota no está registrada a tu nombre.']);
                    exit;
                }
            }

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

            // 1. Verificar disponibilidad de la mascota (evitar que la misma mascota tenga citas simultáneas / solapadas) - BLOQUEO DIRECTO
            if (!$this->model->checkMascotaDisponible($data['id_mascota'], $data['fecha'], $data['hora'], $data['duracion_minutos'])) {
                echo json_encode(['success' => false, 'message' => 'La mascota ya tiene una consulta programada que se solapa con este horario. Por favor, elige otra hora.']);
                exit;
            }

            // Validar si la mascota ya tiene otra cita activa programada para el mismo día
            $ignore_warning = $_POST['ignore_warning'] ?? '0';
            if ($ignore_warning !== '1' && !empty($data['id_mascota']) && !empty($data['fecha'])) {
                $queryCitaHoy = "SELECT hora FROM citas 
                                 WHERE id_mascota = :id_mascota 
                                 AND fecha = :fecha
                                 AND estado NOT IN ('cancelada', 'no_asistio', 'cerrada_sin_consulta')
                                 LIMIT 1";
                $stmtCitaHoy = $this->db->prepare($queryCitaHoy);
                $stmtCitaHoy->execute([
                    ':id_mascota' => $data['id_mascota'],
                    ':fecha' => $data['fecha']
                ]);
                $citaHoy = $stmtCitaHoy->fetch(PDO::FETCH_ASSOC);
                
                if ($citaHoy) {
                    // Obtener nombre de la mascota
                    $queryMascota = "SELECT nombre FROM mascotas WHERE id_mascota = :id_mascota";
                    $stmtMascota = $this->db->prepare($queryMascota);
                    $stmtMascota->execute([':id_mascota' => $data['id_mascota']]);
                    $pet = $stmtMascota->fetch(PDO::FETCH_ASSOC);
                    $petName = $pet ? $pet['nombre'] : 'la mascota';
                    
                    $horaCita = date('h:i A', strtotime($citaHoy['hora']));
                    echo json_encode([
                        'success' => false,
                        'has_warning' => true,
                        'message' => "La mascota {$petName} ya tiene una cita programada hoy a las {$horaCita}. ¿Desea continuar?"
                    ]);
                    exit;
                }
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

            // 2. Insertar cita. Si alguien tomó el mismo horario entre la
            // validación y el INSERT, la base de datos lo rechaza por el índice
            // único de citas activas: se responde con un mensaje claro y no con
            // una respuesta sin formato que el navegador muestra como
            // "error de conexión".
            try {
                $id_cita = $this->model->insert($data);
            } catch (PDOException $e) {
                error_log('Error al insertar cita: ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => $e->getCode() === '23000'
                    ? 'Ese horario acaba de ocuparse. Elige otro de los horarios disponibles.'
                    : 'No se pudo agendar la cita. Intenta nuevamente.']);
                exit;
            }
            if ($id_cita) {
                $cita = $this->model->getById($id_cita);
                if ($cita) {
                    $this->avisarCita('NUEVA_CITA', 'Nueva cita agendada', $cita, false);
                }

                echo json_encode(['success' => true, 'message' => 'Cita agendada correctamente. Se enviará un correo de confirmación.', 'id_cita' => $id_cita]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al agendar la cita.']);
            }
            exit;
        }
    }

    // ── Notificaciones internas de citas ─────────────────────────────────
    // Cada aviso queda enlazado a su cita y vence a la hora de la cita:
    // pasada esa hora ya no le aporta nada a nadie. Tampoco se avisa a quien
    // hizo el cambio de su propia acción. Un fallo al avisar se registra en
    // el log pero no tumba la operación sobre la cita, que ya se guardó.

    private function avisarCita(string $tipo, string $titulo, array $cita, bool $aAdministradores): void {
        try {
            require_once __DIR__ . '/../models/NotificacionInterna.php';
            $noti = new NotificacionInterna($this->db);

            $vigencia = date('Y-m-d H:i:s', strtotime($cita['fecha'] . ' ' . $cita['hora']));
            $ahora = (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))->format('Y-m-d H:i:s');
            if ($vigencia <= $ahora) {
                return;
            }

            $mensaje = $this->describirCita($cita);
            $docSesion = $_SESSION['usuario_doc'] ?? null;
            $rolSesion = (int) ($_SESSION['usuario_id_rol'] ?? 0);

            if ($cita['doc_veterinario'] !== $docSesion) {
                $noti->crearParaUsuario($cita['doc_veterinario'], $tipo, $titulo, $mensaje, 'index.php?action=vet_agenda', (int) $cita['id_cita'], $vigencia);
            }
            if ($aAdministradores && $rolSesion !== 1) {
                $noti->crearParaRol(1, $tipo, $titulo, 'Dr(a). ' . $cita['veterinario_nombre'] . ' · ' . $mensaje, 'index.php?action=admin_citas', (int) $cita['id_cita'], $vigencia);
            }
        } catch (Throwable $e) {
            error_log('No se pudo crear la notificación de la cita ' . ($cita['id_cita'] ?? '?') . ': ' . $e->getMessage());
        }
    }

    // La cita se canceló, se reprogramó o ya se está atendiendo: sus avisos
    // anteriores dejan de tener sentido.
    private function retirarAvisosCita($id_cita): void {
        try {
            require_once __DIR__ . '/../models/NotificacionInterna.php';
            (new NotificacionInterna($this->db))->expirarDeCita($id_cita);
        } catch (Throwable $e) {
            error_log('No se pudieron retirar las notificaciones de la cita ' . $id_cita . ': ' . $e->getMessage());
        }
    }

    // "Max · 21/07/2026 a las 08:30 — Vacunación"
    private function describirCita(array $cita): string {
        $texto = date('d/m/Y', strtotime($cita['fecha'])) . ' a las ' . date('H:i', strtotime($cita['hora']));
        if (!empty($cita['mascota_nombre'])) {
            $texto = $cita['mascota_nombre'] . ' · ' . $texto;
        }
        if (!empty($cita['motivo'])) {
            $texto .= ' — ' . $cita['motivo'];
        }
        return $texto;
    }

    /**
     * Envía el correo de una cita (confirmación, reprogramación, cancelación).
     *
     * El envío por SMTP tarda varios segundos. Antes se hacía con la sesión
     * abierta, y PHP bloquea la sesión mientras un request la tiene: todas
     * las peticiones que el calendario lanza justo después (recargar
     * eventos, detalle del día) quedaban esperando a que terminara el correo
     * y la pantalla se veía "cargando" un buen rato tras confirmar una cita.
     *
     * Ahora se libera la sesión, se responde al navegador de inmediato y el
     * correo se envía después de cerrar la respuesta.
     */
    public function enviarEmailAjax() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id_cita = $_POST['id_cita'] ?? null;
            $tipo = $_POST['tipo'] ?? 'confirmacion';

            session_write_close();
            $this->responderYSeguir(['success' => true]);

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
            exit;
        }
    }

    /**
     * Entrega la respuesta JSON y cierra la conexión, pero deja que el script
     * siga corriendo (para trabajo lento que el usuario no necesita esperar).
     */
    private function responderYSeguir(array $respuesta): void {
        ignore_user_abort(true);
        set_time_limit(60);

        $cuerpo = json_encode($respuesta);
        header('Content-Type: application/json');
        header('Content-Length: ' . strlen($cuerpo));
        header('Connection: close');
        echo $cuerpo;

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            return;
        }
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
    }

    public function listarSemanaAjax() {
        $this->revisarAtencionesAbiertas();

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
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'tipos' => $tipos]);
            exit;
        } catch (Exception $e) {
            // HU-38: el detalle técnico va al log, no al cliente.
            error_log('Error en listarTiposCitaAjax: ' . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'No se pudieron cargar los tipos de cita.']);
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

    public function reprogramarCitaAjax() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
            exit;
        }

        header('Content-Type: application/json');

        $id_cita = $_POST['id_cita'] ?? null;
        $fecha   = $_POST['fecha']   ?? null;
        $hora    = $_POST['hora']    ?? null;

        if (!$id_cita || !$fecha || !$hora) {
            echo json_encode(['success' => false, 'message' => 'Parámetros incompletos: se requieren id_cita, fecha y hora.']);
            exit;
        }

        $cita_actual = $this->model->getById($id_cita);
        if (!$cita_actual) {
            echo json_encode(['success' => false, 'message' => 'Cita no encontrada.']);
            exit;
        }

        // HU-14: reprograma el administrador (también arrastrando en el
        // calendario) o el veterinario sus propias citas. Antes solo el
        // administrador, así que el botón «Reprogramar» del veterinario
        // respondía siempre 403.
        $rol = (int) ($_SESSION['usuario_id_rol'] ?? 0);
        $esAdmin = $rol === 1;
        if (!$esAdmin && !($rol === 2 && $cita_actual['doc_veterinario'] === ($_SESSION['usuario_doc'] ?? null))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Solo el administrador o el veterinario de la cita pueden reprogramarla.']);
            exit;
        }

        // RN-405: solo se reprograma una cita sin resolver, y a un momento que
        // no haya pasado. Antes se podía mover una cita completada o cancelada.
        if (!in_array($cita_actual['estado'], Cita::ESTADOS_ABIERTOS, true)) {
            echo json_encode(['success' => false, 'message' => $this->mensajeCitaCerrada($cita_actual['estado'])]);
            exit;
        }
        $nuevoInicio = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $fecha . ' ' . substr($hora, 0, 5), new DateTimeZone('America/Bogota'));
        if (!$nuevoInicio || $nuevoInicio < $this->ahora()) {
            echo json_encode(['success' => false, 'message' => 'Elige una fecha y hora que no hayan pasado.']);
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

        // Mantener los campos no modificados de la cita original. El
        // administrador puede además reasignar el veterinario: el modal
        // siempre lo enviaba, pero el servidor lo ignoraba en silencio.
        $doc_veterinario = $cita_actual['doc_veterinario'];
        $vetPedido = trim((string) ($_POST['doc_veterinario'] ?? ''));
        if ($esAdmin && $vetPedido !== '' && $vetPedido !== $doc_veterinario) {
            $stmtVet = $this->db->prepare("SELECT 1 FROM usuarios WHERE documento = ? AND id_rol = 2 AND estado = 1");
            $stmtVet->execute([$vetPedido]);
            if (!$stmtVet->fetchColumn()) {
                echo json_encode(['success' => false, 'message' => 'El veterinario seleccionado no existe o está inactivo.']);
                exit;
            }
            $doc_veterinario = $vetPedido;
        }
        $motivo          = $cita_actual['motivo'];
        $id_tipo_cita    = $cita_actual['id_tipo_cita'] ?? null;
        $duracion_minutos = $cita_actual['duracion_minutos'] ?? 30;

        // Verificar disponibilidad excluyendo la cita actual (id_cita_excluir)
        if (!$this->model->checkDisponibilidad($doc_veterinario, $fecha, $hora, $duracion_minutos, $id_cita)) {
            echo json_encode(['success' => false, 'message' => 'El horario seleccionado no está disponible para ese veterinario. Hay solapamiento con otra cita.']);
            exit;
        }

        // La mascota tampoco puede quedar con dos citas solapadas: al agendar
        // se comprobaba, al reprogramar no.
        if (!$this->model->checkMascotaDisponible($cita_actual['id_mascota'], $fecha, $hora, $duracion_minutos, $id_cita)) {
            echo json_encode(['success' => false, 'message' => 'La mascota ya tiene otra cita que se solapa con ese horario.']);
            exit;
        }

        if ($this->model->update($id_cita, $doc_veterinario, $fecha, $hora, $motivo, $id_tipo_cita, $duracion_minutos)) {
            $this->retirarAvisosCita($id_cita);
            $cita = $this->model->getById($id_cita);
            if ($cita) {
                $this->avisarCita('CITA_REPROGRAMADA', 'Cita reprogramada', $cita, false);
            }
            echo json_encode(['success' => true, 'message' => 'Cita reprogramada correctamente. Se notificará al propietario.', 'id_cita' => $id_cita]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al reprogramar la cita.']);
        }
        exit;
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

            // RN-405: solo se cancela una cita sin resolver. Antes se podía
            // cancelar una cita en curso o ya completada.
            $cita_estado = $this->model->getById($id_cita);
            if (!$cita_estado) {
                echo json_encode(['success' => false, 'message' => 'Cita no encontrada.']);
                exit;
            }
            if (!in_array($cita_estado['estado'], Cita::ESTADOS_ABIERTOS, true)) {
                echo json_encode(['success' => false, 'message' => $this->mensajeCitaCerrada($cita_estado['estado'])]);
                exit;
            }

            if ($this->model->cambiarEstado($id_cita, 'cancelada')) {
                // El aviso de "nueva cita" ya no aplica; lo reemplaza el de cancelación.
                $this->retirarAvisosCita($id_cita);
                $cita = $this->model->getById($id_cita);
                if ($cita) {
                    $this->avisarCita('CITA_CANCELADA', 'Cita cancelada', $cita, true);
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

        // RN-408: la atención la inicia solo el veterinario asignado.
        if ((int) $rolUsuario !== 2 || empty($docSesion) || $cita['doc_veterinario'] !== $docSesion) {
            echo json_encode(['success' => false, 'message' => 'Solo el veterinario asignado puede iniciar la atención de esta cita.']);
            exit;
        }

        // Ya iniciada (el veterinario salió de la pantalla sin finalizar, o la
        // atención quedó sin cerrar): se retoma donde quedó en vez de dar error.
        if (in_array($cita['estado'], Cita::ESTADOS_EN_ATENCION, true)) {
            echo json_encode([
                'success' => true,
                'message' => 'Retomando la atención...',
                'estado' => 'en_curso',
                'redirect_url' => 'index.php?action=vet_atencion&id_cita=' . (int) $id_cita
            ]);
            exit;
        }

        if (!in_array($cita['estado'], Cita::ESTADOS_ABIERTOS, true)) {
            echo json_encode(['success' => false, 'message' => $this->mensajeCitaCerrada($cita['estado'])]);
            exit;
        }

        // Solo el día de la cita: iniciar hoy una cita de la semana siguiente
        // la dejaba "en curso" días antes de que llegara el paciente.
        $ahora = $this->ahora();
        $hoy = $ahora->format('Y-m-d');
        if ($cita['fecha'] !== $hoy) {
            echo json_encode(['success' => false, 'message' => $cita['fecha'] > $hoy
                ? 'Esta cita es del ' . date('d/m/Y', strtotime($cita['fecha'])) . '. La atención solo se puede iniciar el día de la cita.'
                : 'Esta cita ya pasó. Si el paciente no vino, márcala como "No asistió".']);
            exit;
        }

        // RN-408: desde 15 minutos antes de la hora de la cita. Antes se podía
        // iniciar a cualquier hora del día, y así se abría por error la
        // atención de un paciente que todavía no había llegado.
        if (!ReglaAtencion::puedeIniciar($cita['fecha'], $cita['hora'], $ahora)) {
            echo json_encode(['success' => false, 'message' => 'La atención de esta cita se puede iniciar desde las '
                . ReglaAtencion::iniciaDesde($cita['fecha'], $cita['hora'])->format('g:i A') . '.']);
            exit;
        }
        // Un fallo de la base (por ejemplo, sin la migración 09 no existe
        // hora_inicio_real) salía como página de error en vez de JSON.
        try {
            $iniciada = $this->model->iniciarAtencion($id_cita, $ahora->format('Y-m-d H:i:s'));
        } catch (Throwable $e) {
            error_log('No se pudo iniciar la atención de la cita ' . $id_cita . ': ' . $e->getMessage());
            $iniciada = false;
        }

        if ($iniciada) {
            $this->retirarAvisosCita($id_cita);
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

        // RN-406: una cita se completa al registrar su consulta, en la misma
        // transacción (ConsultaController::registrarAjax). Este endpoint solo
        // cierra una cita cuya consulta ya existe y quedó abierta (datos de
        // antes de v1.9.0). Antes completaba sin consulta: la cita quedaba
        // "atendida" sin nada en la historia clínica.
        if ((int) $rolUsuario !== 2 || empty($docSesion) || $cita['doc_veterinario'] !== $docSesion) {
            echo json_encode(['success' => false, 'message' => 'Solo el veterinario asignado puede cerrar esta cita.']);
            exit;
        }

        if ($cita['estado'] === 'completada') {
            echo json_encode(['success' => true, 'message' => 'La cita ya estaba marcada como completada.', 'estado' => 'completada']);
            exit;
        }

        if (!in_array($cita['estado'], Cita::ESTADOS_EN_ATENCION, true)) {
            echo json_encode(['success' => false, 'message' => 'La atención de esta cita no está en curso.', 'estado_actual' => $cita['estado']]);
            exit;
        }

        require_once '../models/Consulta.php';
        if (!(new Consulta($this->db))->findByCita($id_cita)) {
            echo json_encode(['success' => false, 'message' => 'Registra la consulta de la cita para completarla.']);
            exit;
        }

        try {
            $completada = $this->model->completarAtencion($id_cita, $this->ahora()->format('Y-m-d H:i:s'));
        } catch (Throwable $e) {
            error_log('No se pudo completar la cita ' . $id_cita . ': ' . $e->getMessage());
            $completada = false;
        }

        if ($completada) {
            $this->retirarAvisosCita($id_cita);
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

    /**
     * RN-409 / HU-29 — El paciente no llegó. Lo marca el veterinario de la
     * cita y solo cuando la hora de la cita ya pasó: antes de esa hora no se
     * sabe si va a llegar. La cita deja de ocupar su espacio en la agenda.
     */
    public function marcarNoAsistioAjax() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            exit;
        }

        $id_cita = $_POST['id_cita'] ?? null;
        $cita = ctype_digit((string) $id_cita) ? $this->model->getById($id_cita) : null;
        if (!$cita) {
            echo json_encode(['success' => false, 'message' => 'Cita no encontrada.']);
            exit;
        }

        if ((int) ($_SESSION['usuario_id_rol'] ?? 0) !== 2 || $cita['doc_veterinario'] !== ($_SESSION['usuario_doc'] ?? null)) {
            echo json_encode(['success' => false, 'message' => 'Solo el veterinario asignado puede marcar la inasistencia.']);
            exit;
        }

        if (!in_array($cita['estado'], Cita::ESTADOS_ABIERTOS, true)) {
            echo json_encode(['success' => false, 'message' => $this->mensajeCitaCerrada($cita['estado'])]);
            exit;
        }

        $inicio = new DateTimeImmutable($cita['fecha'] . ' ' . $cita['hora'], new DateTimeZone('America/Bogota'));
        if ($inicio > $this->ahora()) {
            echo json_encode(['success' => false, 'message' => 'Todavía no es la hora de la cita: la inasistencia se marca cuando esa hora ya pasó.']);
            exit;
        }

        if ($this->model->marcarNoAsistio($id_cita)) {
            $this->retirarAvisosCita($id_cita);
            echo json_encode(['success' => true, 'message' => 'La cita quedó marcada como no asistida.', 'estado' => 'no_asistio']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No se pudo marcar la inasistencia. Intenta nuevamente.']);
        }
        exit;
    }

    /**
     * RN-411 — Cierra sin consulta una atención que no se va a documentar (se
     * inició por error, el paciente se retiró...). Solo el veterinario de la
     * cita, con motivo obligatorio, y queda en auditoría. La cita no se reabre:
     * si el paciente vuelve se agenda otra, y su horario ya quedó libre.
     */
    public function cerrarSinConsultaAjax() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            exit;
        }

        $id_cita = $_POST['id_cita'] ?? null;
        $cita = ctype_digit((string) $id_cita) ? $this->model->getById($id_cita) : null;
        if (!$cita) {
            echo json_encode(['success' => false, 'message' => 'Cita no encontrada.']);
            exit;
        }

        if ((int) ($_SESSION['usuario_id_rol'] ?? 0) !== 2 || $cita['doc_veterinario'] !== ($_SESSION['usuario_doc'] ?? null)) {
            echo json_encode(['success' => false, 'message' => 'Solo el veterinario asignado puede cerrar esta atención.']);
            exit;
        }

        if (!in_array($cita['estado'], Cita::ESTADOS_EN_ATENCION, true)) {
            echo json_encode(['success' => false, 'message' => $this->mensajeCitaCerrada($cita['estado'])]);
            exit;
        }

        $motivo = trim((string) ($_POST['motivo'] ?? ''));
        if (mb_strlen($motivo) < 5) {
            echo json_encode(['success' => false, 'message' => 'Escribe el motivo del cierre (mínimo 5 caracteres).']);
            exit;
        }
        $motivo = mb_substr($motivo, 0, 255);

        require_once '../models/Consulta.php';
        if ((new Consulta($this->db))->findByCita($id_cita)) {
            echo json_encode(['success' => false, 'message' => 'Esta cita ya tiene su consulta registrada: no se cierra sin consulta.']);
            exit;
        }

        try {
            $cerrada = $this->model->cerrarSinConsulta($id_cita, $motivo, $this->ahora()->format('Y-m-d H:i:s'));
        } catch (Throwable $e) {
            error_log('No se pudo cerrar sin consulta la cita ' . $id_cita . ': ' . $e->getMessage());
            $cerrada = false;
        }

        if (!$cerrada) {
            echo json_encode(['success' => false, 'message' => 'No se pudo cerrar la atención. Intenta nuevamente.']);
            exit;
        }

        $this->retirarAvisosCita($id_cita);
        try {
            require_once __DIR__ . '/../models/Auditoria.php';
            (new Auditoria($this->db))->log(
                $_SESSION['usuario_doc'],
                'UPDATE',
                'citas',
                (string) $id_cita,
                ['estado' => $cita['estado']],
                ['estado' => 'cerrada_sin_consulta', 'motivo_cierre' => $motivo],
                'Atención cerrada sin consulta'
            );
        } catch (Throwable $e) {
            error_log('No se pudo auditar el cierre sin consulta de la cita ' . $id_cita . ': ' . $e->getMessage());
        }

        echo json_encode(['success' => true, 'message' => 'La atención quedó cerrada sin consulta.', 'estado' => 'cerrada_sin_consulta']);
        exit;
    }

    // RN-410: antes de mostrar la agenda, avisa de las atenciones que siguen
    // abiertas y pasa a "sin cerrar" las de días anteriores, aunque la tarea
    // programada no esté activa. Un fallo aquí no debe impedir ver la agenda.
    private function revisarAtencionesAbiertas(): void {
        try {
            require_once __DIR__ . '/../helpers/VigilanteAtenciones.php';
            (new VigilanteAtenciones($this->db))->revisar($this->ahora());
        } catch (Throwable $e) {
            error_log('No se pudieron revisar las atenciones abiertas: ' . $e->getMessage());
        }
    }

    // Hora actual de la clínica: las citas se agendan en hora de Colombia.
    private function ahora(): DateTimeImmutable {
        return new DateTimeImmutable('now', new DateTimeZone('America/Bogota'));
    }

    // Mensaje para una acción sobre una cita que ya se resolvió.
    private function mensajeCitaCerrada(string $estado): string {
        $mensajes = [
            'en_curso' => 'La atención de esta cita ya está en curso.',
            'completada' => 'Esta cita ya fue atendida.',
            'cancelada' => 'Esta cita fue cancelada.',
            'no_asistio' => 'Esta cita quedó marcada como no asistida.',
            'sin_cerrar' => 'La atención de esta cita quedó sin cerrar.',
            'cerrada_sin_consulta' => 'Esta atención se cerró sin consulta.',
        ];
        return $mensajes[$estado] ?? 'La cita no está en un estado válido para esta acción.';
    }

    public function confirmarAjax() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id_cita = $_POST['id_cita'] ?? null;
            $cita_actual = $id_cita ? $this->model->getById($id_cita) : null;
            if (!$cita_actual) {
                echo json_encode(['success' => false, 'message' => 'Cita no encontrada.']);
                exit;
            }

            // Verificar permisos: veterinarios solo pueden confirmar sus propias citas
            if ((int) ($_SESSION['usuario_id_rol'] ?? 0) === 2 && $cita_actual['doc_veterinario'] !== $_SESSION['usuario_doc']) {
                echo json_encode(['success' => false, 'message' => 'No tienes permiso para confirmar esta cita. Solo puedes confirmar tus propias citas.']);
                exit;
            }

            // Solo se confirma una cita pendiente. Antes no se miraba el estado:
            // "confirmar" una cita cancelada la reactivaba sin revisar si su
            // horario seguía libre.
            if ($cita_actual['estado'] !== 'pendiente') {
                echo json_encode(['success' => false, 'message' => 'Solo se puede confirmar una cita pendiente.']);
                exit;
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

        require_once __DIR__ . '/../config/EmailService.php';
        $emailService = new EmailService();

        $envFile = __DIR__ . '/../.env';
        $appUrl = 'https://zooki.secarvajal.com/index.php';
        if (file_exists($envFile)) {
            $env = parse_ini_file($envFile);
            if (isset($env['APP_URL'])) {
                $appUrl = rtrim($env['APP_URL'], '/') . '/index.php';
            }
        }

        $fechaFormateada = date('d/m/Y', strtotime($fecha));
        
        $contenido = '
        <p style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;">
          Queremos confirmarte que la cita para tu mascota ha sido programada con éxito.
        </p>
        
        <div style="background-color:#f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 24px 0;">
            <p style="margin: 0 0 8px 0; font-size: 15px; color: #1d1c1d;"><strong>Mascota:</strong> ' . htmlspecialchars($info['mascota']) . '</p>
            <p style="margin: 0 0 8px 0; font-size: 15px; color: #1d1c1d;"><strong>Fecha:</strong> ' . $fechaFormateada . '</p>
            <p style="margin: 0 0 8px 0; font-size: 15px; color: #1d1c1d;"><strong>Hora:</strong> ' . htmlspecialchars($hora) . '</p>
            <p style="margin: 0; font-size: 15px; color: #1d1c1d;"><strong>Veterinario:</strong> Dr(a). ' . htmlspecialchars($info['veterinario']) . '</p>
        </div>
        
        <p style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;">
          Te esperamos puntual para brindar el mejor cuidado a tu mascota.
        </p>';

        $cuerpoHTML = $emailService->obtenerPlantillaBaseHTML($info['propietario'], '¡Cita Confirmada!', $contenido, 'Ver en mi Portal', $appUrl);

        return $emailService->enviarCorreoPersonalizado($info['email'], $info['propietario'], 'Confirmación de Cita Veterinaria - Zooki', $cuerpoHTML);
    }

    private function enviarEmailNotificacion($id_cita, $tipo) {
        $cita = $this->model->getById($id_cita);
        if (!$cita || empty($cita['email'])) return false;

        $info = [
            'propietario' => $cita['propietario_nombre'],
            'mascota' => $cita['mascota_nombre'],
            'veterinario' => $cita['veterinario_nombre']
        ];

        require_once __DIR__ . '/../config/EmailService.php';
        $emailService = new EmailService();

        $envFile = __DIR__ . '/../.env';
        $appUrl = 'https://zooki.secarvajal.com/index.php';
        if (file_exists($envFile)) {
            $env = parse_ini_file($envFile);
            if (isset($env['APP_URL'])) {
                $appUrl = rtrim($env['APP_URL'], '/') . '/index.php';
            }
        }

        $fechaFormateada = date('d/m/Y', strtotime($cita['fecha']));
        $titulo = '';
        $asunto = '';

        if ($tipo === 'reprogramacion') {
            $titulo = 'Tu cita ha sido reprogramada';
            $asunto = 'Actualización de Cita Veterinaria - Zooki';
            $mensaje = 'Te informamos que tu cita ha sido reprogramada con los siguientes detalles:';
        } else if ($tipo === 'confirmacion') {
            $titulo = 'Tu cita ha sido confirmada';
            $asunto = 'Confirmación de Cita - Zooki';
            $mensaje = 'Te confirmamos los detalles finales de tu cita programada:';
        } else { // cancelacion
            $titulo = 'Tu cita ha sido cancelada';
            $asunto = 'Cancelación de Cita Veterinaria - Zooki';
            $mensaje = 'Te informamos que la cita programada ha sido cancelada. Por favor, contáctanos si deseas reprogramarla.';
        }

        $contenido = '
        <p style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;">
          ' . $mensaje . '
        </p>
        
        <div style="background-color:#f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 24px 0;">
            <p style="margin: 0 0 8px 0; font-size: 15px; color: #1d1c1d;"><strong>Mascota:</strong> ' . htmlspecialchars($info['mascota']) . '</p>
            <p style="margin: 0 0 8px 0; font-size: 15px; color: #1d1c1d;"><strong>Fecha:</strong> ' . $fechaFormateada . '</p>
            <p style="margin: 0 0 8px 0; font-size: 15px; color: #1d1c1d;"><strong>Hora:</strong> ' . htmlspecialchars($cita['hora']) . '</p>
            <p style="margin: 0; font-size: 15px; color: #1d1c1d;"><strong>Veterinario:</strong> Dr(a). ' . htmlspecialchars($info['veterinario']) . '</p>
        </div>';

        if ($tipo !== 'cancelacion') {
            $contenido .= '
            <p style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;">
              Te esperamos puntual para brindar el mejor cuidado a tu mascota.
            </p>';
        }

        $cuerpoHTML = $emailService->obtenerPlantillaBaseHTML($info['propietario'], $titulo, $contenido, 'Ver en mi Portal', $appUrl);

        return $emailService->enviarCorreoPersonalizado($cita['email'], $info['propietario'], $asunto, $cuerpoHTML);
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

        // RN-408: la pantalla de atención es del veterinario asignado. La
        // matriz ya la limita al rol veterinario; aquí se exige que sea el suyo.
        $rol = (int) ($_SESSION['usuario_id_rol'] ?? 0);
        $doc = $_SESSION['usuario_doc'] ?? '';
        if ($rol !== 2 || $cita['doc_veterinario'] !== $doc) {
            header('Location: index.php?action=vet_agenda');
            exit;
        }

        // RN-408: la vista solo ofrece "Iniciar atención" el día de la cita,
        // desde 15 minutos antes de su hora.
        $esDiaDeLaCita = ReglaAtencion::puedeIniciar($cita['fecha'], $cita['hora'], $this->ahora());

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

        // Consulta ya registrada para esta cita (id_cita es UNIQUE en
        // consultas): con ella la pantalla se muestra en modo lectura.
        $consultaCita = $consultaModel->findByCita($id_cita) ?: null;
        $tratamientosCita = [];
        if ($consultaCita) {
            require_once '../models/Tratamiento.php';
            $idConsulta = (int) $consultaCita['id_consulta'];
            $tratamientosCita = (new Tratamiento($this->db))->findByConsultas([$idConsulta])[$idConsulta] ?? [];
        }

        // Lo que la vista muestra ya calculado: la vista solo pinta.
        require_once '../helpers/ResumenClinico.php';
        $hoy = new DateTimeImmutable('now', new DateTimeZone('America/Bogota'));
        $resumenClinico = ResumenClinico::construir($consultas, $vacunas, $desparasitaciones, $hoy);
        $edadMascota = ResumenClinico::edadLegible($mascota['fecha_nacimiento'] ?? null, $hoy);
        $fotoMascota = !empty($mascota['url_foto'])
            ? 'uploads/mascotas/' . rawurlencode($mascota['url_foto'])
            : 'img/default-pet.svg';

        $tipoCitaNombre = '';
        foreach ($tiposCita as $tipo) {
            if ((int) $tipo['id_tipo_cita'] === (int) ($cita['id_tipo_cita'] ?? 0)) {
                $tipoCitaNombre = $tipo['nombre_tipo'];
                break;
            }
        }

        $content_view = "../views/vet/atencion.php";
        require_once "../views/vet/layout.php";
    }
}
?>
