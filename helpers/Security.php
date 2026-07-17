<?php
/**
 * Security Middleware
 * Protecciones globales: CSRF, Rate Limiting, Session Validation
 */
class Security {

    /**
     * Lista de acciones públicas (no requieren CSRF ni sesión)
     */
    private static array $publicActions = [
        'login', 'solicitar_reset_password_ajax', 'reset_password',
        'procesar_reset_password_ajax', 'register', 'process_register',
        'check_document_ajax', 'check_email_ajax', 'google_login_ajax', 'complete_google_register_ajax'
    ];

    /**
     * Lista de acciones AJAX que requieren sesión activa
     */
    private static array $ajaxActions = [
        'registrar_usuario_ajax', 'actualizar_usuario_ajax', 'cambiar_estado_usuario_ajax',
        'registrar_cita_ajax', 'confirmar_cita_ajax', 'cancelar_cita_ajax',
        'reprogramar_cita_ajax', 'iniciar_cita_ajax', 'completar_cita_ajax',
        'registrar_consulta_ajax', 'registrar_vacuna_ajax', 'registrar_desparasitacion_ajax',
        'get_pendientes_ajax', 'get_timeline_ajax', 'get_vacunas_pendientes_panel_ajax',
        'registrar_nueva_vacuna_ajax', 'registrar_nuevo_laboratorio_ajax',
        'registrar_nuevo_producto_ajax', 'actualizar_mascota_ajax',
        'get_laboratorios_ajax', 'get_productos_ajax', 'get_vacunas_por_especie_ajax',
        'listar_pendientes_vacunas_ajax', 'listar_pendientes_desparasitaciones_ajax',
        'get_auditoria_ajax', 'buscar_global_ajax',
        'portal_get_tipos_cita_ajax', 'portal_get_vets_ajax', 'portal_get_horas_ajax', 'portal_agendar_cita_ajax',
        'get_notificaciones_ajax', 'ver_detalle_mascota_propietario_ajax'
    ];

    /**
     * Ejecuta todas las validaciones de seguridad según el contexto.
     */
    public static function check(string $action): void {
        self::validateAjaxSession($action);
        self::validateCsrf($action);
    }

    /**
     * Valida que las peticiones AJAX tengan sesión activa.
     */
    private static function validateAjaxSession(string $action): void {
        if (!in_array($action, self::$ajaxActions, true)) return;

        if (empty($_SESSION['usuario_doc'])) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Sesión expirada. Inicia sesión nuevamente.']);
            exit;
        }
    }

    /**
     * Valida token CSRF en todas las peticiones POST excepto acciones públicas.
     */
    private static function validateCsrf(string $action): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        if (in_array($action, self::$publicActions, true)) return;

        require_once __DIR__ . '/Csrf.php';

        // El frontend envía un token global 'default' (meta tag), no por acción
        if (!Csrf::validate('default')) {
            // Si es AJAX, responder JSON; si es navegación normal, redirigir con error
            if (self::isAjax()) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido o expirado. Recarga la página.']);
                exit;
            }
            $_SESSION['error'] = 'Token de seguridad inválido. Recarga la página e intenta nuevamente.';
            header('Location: index.php?action=' . $action);
            exit;
        }
    }

    /**
     * Rate limiting para login: máximo 5 intentos cada 15 minutos.
     */
    public static function checkRateLimit(): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $key = 'rate_limit_' . $ip;
        $window = 900; // 15 minutos
        $maxAttempts = 5;

        $now = time();
        $attempts = $_SESSION[$key]['count'] ?? 0;
        $lastAttempt = $_SESSION[$key]['time'] ?? 0;

        // Resetear ventana si pasó el tiempo
        if (($now - $lastAttempt) > $window) {
            $attempts = 0;
        }

        if ($attempts >= $maxAttempts) {
            $retryAfter = $window - ($now - $lastAttempt);
            $_SESSION['error_login'] = "Demasiados intentos. Espera " . ceil($retryAfter / 60) . " minutos.";
            return false;
        }

        // Incrementar contador (se confirma solo si el login falla, pero aquí pre-cuenta)
        // En realidad, el rate limit se aplica en el controller; esto es un helper.
        return true;
    }

    public static function recordFailedLogin(): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $key = 'rate_limit_' . $ip;
        $_SESSION[$key]['count'] = ($_SESSION[$key]['count'] ?? 0) + 1;
        $_SESSION[$key]['time'] = time();
    }

    public static function resetRateLimit(): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        unset($_SESSION['rate_limit_' . $ip]);
    }

    private static function isAjax(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
