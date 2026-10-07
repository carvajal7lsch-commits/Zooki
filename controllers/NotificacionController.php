<?php
require_once __DIR__ . '/../models/NotificacionInterna.php';
require_once __DIR__ . '/../helpers/Contexto.php';
require_once __DIR__ . '/../helpers/Security.php';

/**
 * HU-T.6 — Notificaciones internas del contexto activo. La persona, la
 * clínica y el rol salen de la sesión, nunca de la petición. En el portal
 * del propietario todavía no hay avisos internos (HU-5.7, v2.1): la lista va
 * vacía.
 */
class NotificacionController {

    private $notificacionModel;

    // T-18: la sesion ya la abre el front controller (public/index.php).
    public function __construct($db = null) {
        $this->notificacionModel = new NotificacionInterna($db);
    }

    /** [id_clinica, id_usuario, id_rol] del contexto, o null si no es de clínica. */
    private function destinatario(): ?array {
        $clinica = Contexto::clinicaActiva();
        $usuario = Contexto::idUsuario();
        $rol = Contexto::rolActivo();
        return ($clinica === null || $usuario === null || $rol === null) ? null : [$clinica, $usuario, $rol];
    }

    // Retorna JSON con las notificaciones no leídas y las últimas 10
    public function obtenerNotificaciones() {
        header('Content-Type: application/json');

        $d = $this->destinatario();
        if ($d === null) {
            echo json_encode(['success' => true, 'no_leidas' => 0, 'notificaciones' => []]);
            return;
        }

        try {
            echo json_encode([
                'success' => true,
                'no_leidas' => $this->notificacionModel->contarNoLeidas(...$d),
                'notificaciones' => $this->notificacionModel->obtenerParaUsuario(...$d)
            ]);
        } catch (Exception $e) {
            error_log('Notificaciones: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'No se pudieron cargar las notificaciones.']);
        }
    }

    // Marca una notificación específica como leída
    public function marcarLeida() {
        header('Content-Type: application/json');

        $id_notificacion = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id_notificacion <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            return;
        }

        // T-01 (RN-G02, RN-G13): la notificación tiene que ser de esta clínica
        // y dirigida a esta persona o a su rol; si no, 403 y auditoría.
        $d = $this->destinatario();
        if ($d === null || !$this->notificacionModel->perteneceA($id_notificacion, ...$d)) {
            Security::denegarRecursoAjeno('notificaciones_internas', $id_notificacion);
        }

        echo json_encode(['success' => $this->notificacionModel->marcarLeida($id_notificacion, ...$d)]);
    }

    // Marca todas como leídas
    public function marcarTodasLeidas() {
        header('Content-Type: application/json');

        $d = $this->destinatario();
        echo json_encode(['success' => $d === null ? true : $this->notificacionModel->marcarTodasLeidas(...$d)]);
    }
}
