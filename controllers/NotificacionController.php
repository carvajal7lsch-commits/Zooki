<?php
require_once __DIR__ . '/../models/NotificacionInterna.php';

class NotificacionController {
    
    private $notificacionModel;

    // T-18: la sesion ya la abre el front controller (public/index.php).
    public function __construct() {
        $this->notificacionModel = new NotificacionInterna();
    }

    // Retorna JSON con las notificaciones no leídas y las últimas 10
    public function obtenerNotificaciones() {
        header('Content-Type: application/json');

        if (!isset($_SESSION['usuario_doc']) || !isset($_SESSION['usuario_id_rol'])) {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit;
        }

        $doc_usuario = $_SESSION['usuario_doc'];
        $id_rol = $_SESSION['usuario_id_rol'];

        try {
            $no_leidas = $this->notificacionModel->contarNoLeidas($doc_usuario, $id_rol);
            $notificaciones = $this->notificacionModel->obtenerParaUsuario($doc_usuario, $id_rol, 10);

            echo json_encode([
                'success' => true,
                'no_leidas' => $no_leidas,
                'notificaciones' => $notificaciones
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Marca una notificación específica como leída
    public function marcarLeida() {
        header('Content-Type: application/json');

        if (!isset($_SESSION['usuario_doc']) || !isset($_SESSION['usuario_id_rol'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit;
        }

        $id_notificacion = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id_notificacion <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }

        $doc_usuario = $_SESSION['usuario_doc'];
        $id_rol = $_SESSION['usuario_id_rol'];

        // T-01 (RN-G02): la notificación tiene que ir dirigida a este usuario
        // o a su rol. Sin esta comprobación bastaba enviar cualquier id para
        // marcar como leída la notificación de otra persona.
        if (!$this->notificacionModel->perteneceA($id_notificacion, $doc_usuario, $id_rol)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tienes acceso a esta notificación.']);
            exit;
        }

        $success = $this->notificacionModel->marcarLeida($id_notificacion, $doc_usuario, $id_rol);
        echo json_encode(['success' => $success]);
        exit;
    }

    // Marca todas como leídas
    public function marcarTodasLeidas() {
        header('Content-Type: application/json');

        if (!isset($_SESSION['usuario_doc']) || !isset($_SESSION['usuario_id_rol'])) {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit;
        }

        $doc_usuario = $_SESSION['usuario_doc'];
        $id_rol = $_SESSION['usuario_id_rol'];

        $success = $this->notificacionModel->marcarTodasLeidas($doc_usuario, $id_rol);
        echo json_encode(['success' => $success]);
        exit;
    }
}
?>
