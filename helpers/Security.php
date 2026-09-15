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
        'landing', 'privacidad', 'terminos', 'cookies', 'login', 'solicitar_reset_password_ajax', 'reset_password',
        'procesar_reset_password_ajax', 'register', 'process_register', 'verificar_email', 'estado_verificacion_ajax',
        'check_document_ajax', 'check_email_ajax', 'google_login_ajax', 'complete_google_register_ajax'
    ];

    /**
     * Acciones que un usuario con contrasena temporal si puede ejecutar
     * (T-05). Todo lo demas queda bloqueado hasta que la cambie.
     */
    private static array $accionesConPasswordTemporal = [
        'cambiar_password', 'cambiar_password_ajax', 'logout',
    ];

    /**
     * Politica de intentos (HU-38). El limite por IP es mas holgado que el de
     * cuenta a proposito: una clinica sale a internet por una sola IP y varias
     * personas comparten origen, mientras que el documento identifica a una
     * cuenta concreta.
     */
    private const MAX_INTENTOS_IP     = 20;
    private const MAX_INTENTOS_CUENTA = 5;
    private const MAX_VERIFICACIONES  = 20;
    private const VENTANA             = 900;  // 15 minutos
    private const BLOQUEO             = 900;  // 15 minutos

    /** false = todavia no se resolvio; null = no hay almacen disponible. */
    private static $almacenIntentos = false;

    /**
     * Ejecuta todas las validaciones de seguridad según el contexto.
     */
    public static function check(string $action): void {
        self::validateRole($action);
        self::validatePasswordTemporal($action);
        self::validateCsrf($action);
    }

    /**
     * T-05 — Un usuario con contrasena temporal (debe_cambiar_password = 1) no
     * puede operar el sistema. Antes solo se le redirigia al formulario justo
     * despues del login: bastaba escribir otra URL para saltarselo, y la clave
     * enviada por correo seguia siendo valida de forma indefinida. El control
     * vive aqui, en el front controller, asi que cubre tambien los endpoints
     * AJAX invocados directamente.
     */
    private static function validatePasswordTemporal(string $action): void {
        if (empty($_SESSION['usuario_doc'])) return;
        if (empty($_SESSION['debe_cambiar_password'])) return;
        if (in_array($action, self::$accionesConPasswordTemporal, true)) return;
        if (in_array($action, self::$publicActions, true)) return;

        $mensaje = 'Debes cambiar tu contrasena temporal antes de continuar.';

        // No se reutiliza denegar(): su rama 403 redirige al dashboard, que
        // aqui tambien esta bloqueado y provocaria un bucle de redirecciones.
        if (self::isAjax() || str_ends_with($action, '_ajax')) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode([
                'success'  => false,
                'message'  => $mensaje,
                'redirect' => 'index.php?action=cambiar_password',
            ]);
            exit;
        }

        $_SESSION['error'] = $mensaje;
        header('Location: index.php?action=cambiar_password');
        exit;
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
     * Identificadores de rol tal como estan en la tabla `roles`.
     */
    public const ROL_ADMIN         = 1;
    public const ROL_VETERINARIO   = 2;
    public const ROL_RECEPCIONISTA = 3;
    public const ROL_PROPIETARIO   = 4;

    /**
     * Matriz de autorizacion accion -> roles permitidos (HU-32 / VD-SEG-03/04).
     *
     * Es la fuente unica del control de acceso: se aplica en el front
     * controller antes de instanciar ningun controlador, asi que un endpoint
     * invocado directamente (curl, Postman, consola del navegador) queda
     * cubierto igual que uno invocado desde la interfaz.
     *
     * Los conjuntos se derivaron de que vistas y que archivos JS invocan
     * realmente cada accion, para no romper flujos que hoy funcionan.
     */
    private static function actionRoles(): array {
        $admin   = [self::ROL_ADMIN];
        $soloVet = [self::ROL_VETERINARIO];
        $clinico = [self::ROL_ADMIN, self::ROL_VETERINARIO];
        $staff   = [self::ROL_ADMIN, self::ROL_VETERINARIO, self::ROL_RECEPCIONISTA];
        $portal  = [self::ROL_PROPIETARIO];
        $todos   = [self::ROL_ADMIN, self::ROL_VETERINARIO, self::ROL_RECEPCIONISTA, self::ROL_PROPIETARIO];

        $matriz = [];

        // Cualquier sesion valida: navegacion base, cuenta propia y avisos.
        foreach ([
            'dashboard', 'logout', 'cambiar_password', 'cambiar_password_ajax',
            'get_notificaciones_ajax', 'marcar_notificacion_leida_ajax',
            'marcar_todas_notificaciones_leidas_ajax',
            // HU-42: el perfil propio lo consulta y edita cualquier rol; el
            // sujeto sale de la sesion, nunca del POST.
            'get_mi_perfil_ajax', 'actualizar_mi_perfil_ajax',
        ] as $a) { $matriz[$a] = $todos; }

        // HU-42: el panel "Mi perfil" es del personal; el propietario tiene el
        // suyo en el portal.
        $matriz['mi_perfil'] = $staff;

        // Catalogos y agenda que el portal del propietario tambien consume.
        foreach ([
            'listar_especies_ajax', 'listar_razas_ajax', 'listar_colores_ajax',
            'get_horas_disponibles_ajax', 'get_sugerencias_horario_ajax',
            'cancelar_cita_ajax', 'enviar_email_ajax',
        ] as $a) { $matriz[$a] = $todos; }

        // Administracion: usuarios, auditoria, configuracion y reportes.
        foreach ([
            'admin_panel', 'admin_usuarios', 'admin_pacientes', 'admin_nuevo_paciente',
            'admin_editar_paciente', 'admin_personal', 'admin_estadisticas', 'admin_citas',
            'admin_reportes', 'admin_auditoria', 'admin_configuracion',
            'listar_usuarios', 'registrar_usuario_ajax', 'actualizar_usuario_ajax',
            'get_usuario_ajax', 'cambiar_estado_usuario_ajax',
            'resetear_password_usuario_ajax',
            'get_auditoria_ajax', 'listar_todas_citas_ajax',
            'get_horarios_clinica_ajax', 'guardar_horarios_clinica_ajax',
            'restaurar_horarios_defecto_ajax',
        ] as $a) { $matriz[$a] = $admin; }

        // RN-201: registrar actos clinicos y operar el area del veterinario es
        // exclusivo del rol Veterinario, ni siquiera el administrador entra.
        // Coincide con la comprobacion en linea que el enrutador ya hacia.
        foreach ([
            'vet_area', 'vet_atencion', 'vet_consultas', 'vet_nueva_consulta',
            'vet_pacientes', 'vet_agenda', 'vet_historial',
            'registrar_consulta_ajax', 'registrar_vacuna_ajax',
            'registrar_desparasitacion_ajax', 'registrar_nueva_vacuna_ajax',
            'registrar_nuevo_laboratorio_ajax',
            'registrar_nuevo_producto_desparasitacion_ajax',
            // RN-408: atender una cita (iniciarla, cerrarla o marcarla como no
            // asistida) es del veterinario asignado. Recepción y administración
            // podían iniciarla, pero la pantalla de atención es solo del
            // veterinario: la cita quedaba "en curso" sin que nadie la atendiera.
            'iniciar_cita_ajax', 'completar_cita_ajax', 'marcar_no_asistio_ajax', 'cerrar_sin_consulta_ajax',
        ] as $a) { $matriz[$a] = $soloVet; }

        // Consulta de informacion clinica: el administrador si la necesita
        // para auditoria y reportes, recepcion no.
        foreach ([
            'listar_consultas', 'listar_historial_ajax',
            'get_laboratorios_ajax', 'get_productos_desparasitacion_ajax',
            'get_vacunas_por_especie_ajax', 'listar_vacunas_pendientes_ajax',
            'listar_desparasitaciones_pendientes_ajax', 'get_vacunas_pendientes_panel_ajax',
        ] as $a) { $matriz[$a] = $clinico; }

        // Personal de la clinica: recepcion, pacientes, propietarios y citas.
        foreach ([
            'reception_dashboard', 'reception_agenda', 'reception_nueva_cita',
            'reception_pacientes', 'reception_nuevo_paciente', 'calendario',
            'listar_mascotas', 'nueva_mascota', 'editar_mascota', 'guardar_mascota',
            'actualizar_mascota', 'buscar_mascotas', 'guardar_mascota_ajax',
            'actualizar_mascota_ajax', 'cambiar_estado_mascota_ajax', 'get_mascota_ajax',
            'listar_mascotas_ajax', 'listar_mascotas_propietario_ajax',
            'nuevo_propietario', 'guardar_propietario', 'guardar_propietario_ajax',
            'listar_propietarios_ajax', 'get_propietario_ajax', 'actualizar_propietario_ajax',
            'registrar_color_ajax',
            'registrar_cita_ajax', 'listar_citas_ajax', 'listar_calendario_ajax',
            'get_cita_ajax', 'reprogramar_cita_ajax', 'confirmar_cita_ajax',
            'listar_veterinarios_ajax', 'listar_tipos_cita_ajax',
            'get_charts_data_ajax', 'get_role_stats_ajax', 'get_timeline_ajax',
            'get_pendientes_ajax',
            'verificar_documento_ajax', 'verificar_email_ajax',
        ] as $a) { $matriz[$a] = $staff; }

        // Portal del propietario: solo el dueno, sobre sus propios datos.
        foreach ([
            'portal_propietario', 'portal_registrar_mascota_ajax',
            'portal_actualizar_mascota_ajax', 'portal_actualizar_datos_contacto_ajax',
            'portal_agendar_cita_ajax', 'portal_get_vets_ajax', 'portal_get_tipos_cita_ajax',
            'portal_get_detalle_cita_clinica_ajax', 'portal_imprimir_historial',
            'ver_detalle_mascota_propietario_ajax',
        ] as $a) { $matriz[$a] = $portal; }

        return $matriz;
    }

    /**
     * Rol de la sesion actual. Se prefiere el id numerico; el nombre solo se
     * usa como respaldo para sesiones abiertas antes de este despliegue, que
     * podrian no tener `usuario_id_rol` guardado.
     */
    private static function rolActual(): ?int {
        if (!empty($_SESSION['usuario_id_rol'])) {
            return (int) $_SESSION['usuario_id_rol'];
        }

        $porNombre = [
            'administrador' => self::ROL_ADMIN,
            'veterinario'   => self::ROL_VETERINARIO,
            'recepcionista' => self::ROL_RECEPCIONISTA,
            'propietario'   => self::ROL_PROPIETARIO,
        ];
        $nombre = strtolower(trim($_SESSION['usuario_rol'] ?? ''));

        return $porNombre[$nombre] ?? null;
    }

    /**
     * Aplica la matriz de autorizacion. No depende de la interfaz: corre en el
     * front controller, antes del enrutador, para toda peticion.
     */
    private static function validateRole(string $action): void {
        // Las acciones publicas no exigen sesion ni rol.
        if (in_array($action, self::$publicActions, true)) return;

        $permitidos = self::actionRoles()[$action] ?? null;

        // T-15 — Se deniega por defecto. Antes una accion ausente de la matriz
        // pasaba sin control: bastaba agregar una ruta al enrutador y olvidarse
        // de registrarla aqui para dejarla abierta a cualquier sesion, en
        // silencio. Ahora el olvido se nota de inmediato y falla del lado
        // seguro. Las rutas inexistentes las sigue atendiendo el `default` del
        // enrutador, pero solo para usuarios ya autenticados.
        if ($permitidos === null) {
            if (self::rolActual() === null) {
                self::denegar($action, 401, 'Sesion expirada. Inicia sesion nuevamente.');
            }
            error_log(sprintf('RBAC: accion "%s" sin entrada en la matriz de autorizacion', $action));
            self::denegar($action, 403, 'No tienes permisos para realizar esta accion.');
        }

        $rol = self::rolActual();

        if ($rol === null) {
            self::denegar($action, 401, 'Sesion expirada. Inicia sesion nuevamente.');
        }

        if (!in_array($rol, $permitidos, true)) {
            error_log(sprintf(
                'RBAC: acceso denegado a "%s" para el rol %d (documento %s)',
                $action,
                $rol,
                $_SESSION['usuario_doc'] ?? 'desconocido'
            ));
            self::denegar($action, 403, 'No tienes permisos para realizar esta accion.');
        }
    }

    /**
     * Corta la peticion: JSON para AJAX, redireccion para navegacion normal.
     */
    private static function denegar(string $action, int $codigo, string $mensaje): void {
        if (self::isAjax() || str_ends_with($action, '_ajax')) {
            header('Content-Type: application/json');
            http_response_code($codigo);
            echo json_encode(['success' => false, 'message' => $mensaje]);
            exit;
        }

        if ($codigo === 401) {
            header('Location: index.php?action=login');
            exit;
        }

        $_SESSION['error'] = $mensaje;
        header('Location: index.php?action=dashboard');
        exit;
    }

    /**
     * Rate limiting para login: máximo 5 intentos cada 15 minutos.
     */
    public static function checkRateLimit(?string $cuenta = null): bool {
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

        // Capa 2 (HU-38, VD-SEG-05): el contador de arriba vive en $_SESSION y
        // se reinicia con solo no mandar la cookie. Este vive en la base de
        // datos y se consulta por IP y por cuenta, asi que el limite se aplica
        // aunque el cliente descarte su sesion en cada intento.
        $almacen = self::almacenDeIntentos();
        if ($almacen === null) return true;

        $claves = ['ip:' . $ip];
        if ($cuenta !== null && $cuenta !== '') {
            $claves[] = 'cuenta:' . $cuenta;
        }

        foreach ($claves as $clave) {
            $restante = $almacen->segundosDeBloqueo($clave);
            if ($restante > 0) {
                $_SESSION['error_login'] = "Demasiados intentos. Espera " . ceil($restante / 60) . " minutos.";
                return false;
            }
        }

        return true;
    }

    public static function recordFailedLogin(?string $cuenta = null): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $key = 'rate_limit_' . $ip;
        $_SESSION[$key]['count'] = ($_SESSION[$key]['count'] ?? 0) + 1;
        $_SESSION[$key]['time'] = time();

        // Capa 2 (HU-38): el mismo fallo se anota del lado del servidor, donde
        // el cliente no lo puede borrar tirando la cookie.
        $almacen = self::almacenDeIntentos();
        if ($almacen === null) return;

        $almacen->registrarFallo('ip:' . $ip, self::MAX_INTENTOS_IP, self::VENTANA, self::BLOQUEO);
        if ($cuenta !== null && $cuenta !== '') {
            $almacen->registrarFallo('cuenta:' . $cuenta, self::MAX_INTENTOS_CUENTA, self::VENTANA, self::BLOQUEO);
        }
    }

    public static function resetRateLimit(?string $cuenta = null): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        unset($_SESSION['rate_limit_' . $ip]);

        $almacen = self::almacenDeIntentos();
        if ($almacen === null) return;

        $almacen->limpiar('ip:' . $ip);
        if ($cuenta !== null && $cuenta !== '') {
            $almacen->limpiar('cuenta:' . $cuenta);
        }

        // Momento barato para el mantenimiento: ocurre una vez por inicio de
        // sesion correcto, no en cada peticion.
        $almacen->purgar();
    }

    /**
     * HU-38 (VD-SEG-06) — Limite para las verificaciones de documento/correo
     * del formulario de registro. Son publicas por necesidad (avisan si el
     * documento ya existe), asi que lo que se corta aqui es el uso masivo:
     * a mano no se notan, pero un script que enumere se topa con el muro.
     */
    public static function checkVerificationLimit(): bool {
        $almacen = self::almacenDeIntentos();
        if ($almacen === null) return true;

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $clave = 'chk:' . $ip;

        if ($almacen->segundosDeBloqueo($clave) > 0) return false;

        $almacen->registrarFallo($clave, self::MAX_VERIFICACIONES, self::VENTANA, self::BLOQUEO);

        return true;
    }

    /**
     * Almacen de intentos, creado una sola vez. Devuelve null si no hay base
     * de datos disponible; en ese caso solo queda la capa de sesion, que es
     * justo lo que pasaba antes de HU-38.
     *
     * Las pruebas llaman a definirAlmacenDeIntentos() para inyectar el suyo
     * (o null) y no tocar la base real.
     */
    private static function almacenDeIntentos() {
        if (self::$almacenIntentos !== false) return self::$almacenIntentos;

        try {
            require_once __DIR__ . '/../models/IntentoLogin.php';
            require_once __DIR__ . '/../config/Database.php';
            $database = new Database();
            $conexion = $database->getConnection();
            self::$almacenIntentos = $conexion ? new IntentoLogin($conexion) : null;
        } catch (Throwable $e) {
            error_log('HU-38: sin almacen de intentos (' . $e->getMessage() . ')');
            self::$almacenIntentos = null;
        }

        return self::$almacenIntentos;
    }

    /** Punto de inyeccion para las pruebas. */
    public static function definirAlmacenDeIntentos($almacen): void {
        self::$almacenIntentos = $almacen;
    }

    private static function isAjax(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
