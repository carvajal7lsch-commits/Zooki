<?php
require_once __DIR__ . '/Roles.php';
require_once __DIR__ . '/Contexto.php';
require_once __DIR__ . '/AccesoDenegado.php';

/**
 * Security Middleware
 * Protecciones globales: sesión, contexto activo, rol, clínica, CSRF y límite
 * de intentos.
 *
 * Es el único lugar donde se decide si una petición pasa (HU-T.10, HU-T.15):
 * el front controller llama a check() antes de instanciar ningún controlador,
 * y los controladores solo piden aquí que se deniegue un recurso de otra
 * clínica (denegarRecursoAjeno / exigirMismaClinica).
 */
class Security {

    /**
     * Lista de acciones públicas (no requieren CSRF ni sesión)
     */
    private static array $publicActions = [
        'landing', 'privacidad', 'terminos', 'cookies', 'login', 'solicitar_reset_password_ajax', 'reset_password',
        'procesar_reset_password_ajax', 'register', 'process_register', 'verificar_email', 'estado_verificacion_ajax',
        'check_document_ajax', 'check_email_ajax', 'google_login_ajax', 'complete_google_register_ajax',
        'confirmar_vinculo_propietario'
    ];

    /**
     * HU-T.17: acciones que solo exigen identidad, porque sirven para elegir
     * o cambiar el contexto, salir o cambiar la contraseña. Todas las demás
     * se ejecutan con el rol del contexto activo (RN-G18).
     */
    private static array $accionesSinContexto = [
        'seleccionar_contexto', 'cambiar_contexto', 'logout', 'cambiar_password', 'cambiar_password_ajax',
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
     * personas comparten origen, mientras que el id_usuario identifica a una
     * cuenta concreta.
     */
    private const MAX_INTENTOS_IP     = 20;
    private const MAX_INTENTOS_CUENTA = 5;
    private const MAX_VERIFICACIONES  = 20;
    private const VENTANA             = 900;  // 15 minutos
    private const BLOQUEO             = 900;  // 15 minutos

    /** false = todavia no se resolvio; null = no hay almacen disponible. */
    private static $almacenIntentos = false;

    /** callable(int): ?array — contextos vigentes de una persona; null = cuenta inexistente o inactiva. */
    private static $fuenteContextos = null;

    /** false = todavía no se resolvió; null = sin auditoría disponible. */
    private static $auditoria = false;

    /**
     * Identificadores de rol (helpers/Roles.php). Se conservan aquí porque
     * los usa código que todavía no se adapta (C2–C9).
     */
    public const ROL_ADMIN       = Roles::ADMIN;
    public const ROL_VETERINARIO = Roles::VETERINARIO;
    public const ROL_PROPIETARIO = Roles::PROPIETARIO;
    public const ROL_SUPER_ADMIN = Roles::SUPER_ADMIN;

    /**
     * Ejecuta todas las validaciones de seguridad y, si alguna falla, corta la
     * petición con la respuesta que corresponde.
     */
    public static function check(string $action): void {
        try {
            self::autorizar($action);
        } catch (AccesoDenegado $e) {
            self::responder($e, $action);
        }
    }

    /**
     * Decide si la petición pasa. Lanza AccesoDenegado si no; no escribe
     * respuesta ni termina el proceso, para que se pueda probar.
     */
    public static function autorizar(string $action): void {
        // Las acciones publicas no exigen sesion, contexto ni rol.
        if (in_array($action, self::$publicActions, true)) {
            if ($action === 'confirmar_vinculo_propietario' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
                require_once __DIR__ . '/Csrf.php';
                if (!Csrf::validate()) throw new AccesoDenegado(403,'La sesión del formulario venció. Abre de nuevo el enlace.');
            }
            return;
        }

        $idUsuario = Contexto::idUsuario();
        if ($idUsuario === null) {
            throw new AccesoDenegado(401, 'Sesion expirada. Inicia sesion nuevamente.');
        }

        // Los contextos se leen de la base en cada petición: si al usuario le
        // retiran el rol en una clínica, o la clínica deja de estar activa,
        // pierde el acceso en la siguiente petición y no al volver a entrar
        // (RN-G08).
        $disponibles = self::contextosDe($idUsuario);
        if ($disponibles === null) {
            // La cuenta ya no existe o se inactivó con la sesión abierta.
            $_SESSION = [];
            throw new AccesoDenegado(401, 'Sesion expirada. Inicia sesion nuevamente.');
        }
        $_SESSION['contextos_total'] = count($disponibles);

        $actual = Contexto::actual();
        if ($actual !== null) {
            $vigente = Contexto::buscar($disponibles, $actual['clave']);
            if ($vigente === null) {
                Contexto::salir();
                $actual = null;
            } else {
                Contexto::activar($vigente, count($disponibles));
                $actual = $vigente;
            }
        }

        self::validarPasswordTemporal($action);

        if (!in_array($action, self::$accionesSinContexto, true)) {
            if ($actual === null) {
                throw new AccesoDenegado(403, 'Elige con qué perfil quieres continuar.', 'seleccionar_contexto');
            }
            self::validarRol($action, (int) $actual['id_rol'], $idUsuario);
            self::validarClinicaDeLaPeticion($actual);
        }

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
    private static function validarPasswordTemporal(string $action): void {
        if (empty($_SESSION['debe_cambiar_password'])) return;
        if (in_array($action, self::$accionesConPasswordTemporal, true)) return;

        throw new AccesoDenegado(403, 'Debes cambiar tu contrasena temporal antes de continuar.', 'cambiar_password');
    }

    /**
     * Matriz de autorizacion (HU-T.10). Se deniega por defecto (T-15): una
     * accion sin entrada no pasa para nadie, asi que olvidar registrarla
     * falla del lado seguro.
     */
    private static function validarRol(string $action, int $rol, int $idUsuario): void {
        $permitidos = self::actionRoles()[$action] ?? null;

        if ($permitidos === null) {
            error_log(sprintf('RBAC: accion "%s" sin entrada en la matriz de autorizacion', $action));
            throw new AccesoDenegado(403, 'No tienes permisos para realizar esta accion.');
        }

        if (!in_array($rol, $permitidos, true)) {
            error_log(sprintf('RBAC: acceso denegado a "%s" para el rol %d (id_usuario %d)', $action, $rol, $idUsuario));
            throw new AccesoDenegado(403, 'No tienes permisos para realizar esta accion.');
        }
    }

    /**
     * RN-G13 / RE-T.15.2 — Si la petición nombra una clínica, tiene que ser la
     * del contexto activo. Los recursos que no traen la clínica en la
     * petición (un usuario, una cita) los comprueba el controlador con
     * exigirMismaClinica() o denegarRecursoAjeno().
     */
    private static function validarClinicaDeLaPeticion(array $contexto): void {
        if ($contexto['tipo'] !== Contexto::CLINICA) return;

        $pedida = $_POST['id_clinica'] ?? $_GET['id_clinica'] ?? null;
        if ($pedida === null || $pedida === '') return;

        if ((string) $pedida !== (string) $contexto['id_clinica']) {
            self::denegarRecursoAjeno('clinicas', (string) $pedida);
        }
    }

    /**
     * RE-T.15.2 — El recurso pertenece (o podría pertenecer) a otra clínica:
     * se registra el intento en auditoría y se responde 403. No distingue
     * entre «no existe» y «es de otra clínica», para no confirmar ids ajenos.
     *
     * @throws AccesoDenegado siempre
     */
    public static function denegarRecursoAjeno(string $tabla, $registroId): void {
        $clinica = Contexto::clinicaActiva();
        $auditoria = self::auditoria();
        if ($auditoria !== null) {
            $auditoria->log(
                Contexto::idUsuario(),
                'OTHER',
                $tabla,
                $registroId === null ? null : (string) $registroId,
                null,
                ['accion' => $_GET['action'] ?? null],
                'Acceso denegado: recurso fuera de la clínica activa',
                $clinica
            );
        }
        error_log(sprintf('RN-G13: acceso a %s %s fuera de la clinica %s', $tabla, (string) $registroId, (string) $clinica));

        throw new AccesoDenegado(403, 'No tienes acceso a este recurso.');
    }

    /**
     * Exige que el recurso sea de la clínica activa (RN-G13).
     *
     * @throws AccesoDenegado si es de otra clínica, no existe o no hay contexto de clínica
     */
    public static function exigirMismaClinica(?int $idClinicaDelRecurso, string $tabla, $registroId): void {
        $activa = Contexto::clinicaActiva();
        if ($activa === null || $idClinicaDelRecurso === null || $idClinicaDelRecurso !== $activa) {
            self::denegarRecursoAjeno($tabla, $registroId);
        }
    }

    /**
     * Valida token CSRF en todas las peticiones POST excepto acciones públicas.
     */
    private static function validateCsrf(string $action): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
        if (in_array($action, self::$publicActions, true)) return;

        require_once __DIR__ . '/Csrf.php';

        // El frontend envía un token global 'default' (meta tag), no por acción
        if (!Csrf::validate('default')) {
            throw new AccesoDenegado(403, 'Token de seguridad inválido o expirado. Recarga la página.', $action);
        }
    }

    /**
     * Matriz de autorizacion accion -> roles del contexto activo (HU-T.10,
     * RN-G18). Los roles de clinica son 1 y 2; el 4 es el portal del
     * propietario y el 5 la plataforma. El super-administrador no figura en
     * ninguna accion clinica (RN-004, RE-T.15.4).
     *
     * Las acciones de $accionesSinContexto no van aqui: solo exigen identidad.
     */
    private static function actionRoles(): array {
        $admin      = [Roles::ADMIN];
        $soloVet    = [Roles::VETERINARIO];
        $clinico    = [Roles::ADMIN, Roles::VETERINARIO];
        $staff      = [Roles::ADMIN, Roles::VETERINARIO];
        $portal     = [Roles::PROPIETARIO];
        $plataforma = [Roles::SUPER_ADMIN];
        $todos      = [Roles::ADMIN, Roles::VETERINARIO, Roles::PROPIETARIO];

        $matriz = [];

        // Inicio: cada contexto aterriza en su panel.
        $matriz['dashboard'] = [Roles::ADMIN, Roles::VETERINARIO, Roles::PROPIETARIO, Roles::SUPER_ADMIN];

        // Cualquier contexto de clinica o de propietario: avisos y perfil propio.
        foreach ([
            'get_notificaciones_ajax', 'marcar_notificacion_leida_ajax',
            'marcar_todas_notificaciones_leidas_ajax',
            // HU-T.5: el sujeto sale de la sesion, nunca del POST.
            'actualizar_mi_perfil_ajax',
        ] as $a) { $matriz[$a] = $todos; }

        // HU-T.5: el panel "Mi perfil" es del personal; el propietario tiene
        // el suyo en el portal.
        $matriz['mi_perfil'] = $staff;

        // Catalogos y agenda que el portal del propietario tambien consume.
        foreach ([
            'listar_especies_ajax', 'listar_razas_ajax', 'listar_colores_ajax',
            'get_horas_disponibles_ajax', 'get_sugerencias_horario_ajax',
            'cancelar_cita_ajax', 'enviar_email_ajax',
        ] as $a) { $matriz[$a] = $todos; }

        // Administracion de la clinica: usuarios, auditoria y configuracion.
        foreach ([
            'admin_panel', 'admin_usuarios', 'admin_citas',
            'admin_auditoria', 'admin_configuracion',
            'registrar_usuario_ajax', 'actualizar_usuario_ajax',
            'get_usuario_ajax', 'cambiar_estado_usuario_ajax',
            'resetear_password_usuario_ajax',
            'verificar_documento_ajax', 'verificar_email_ajax',
            'get_auditoria_ajax', 'listar_todas_citas_ajax',
            'get_horarios_clinica_ajax', 'guardar_horarios_clinica_ajax',
            'restaurar_horarios_defecto_ajax',
        ] as $a) { $matriz[$a] = $admin; }

        // RN-201: registrar actos clinicos y operar el area del veterinario es
        // exclusivo del rol Veterinario, ni siquiera el administrador entra.
        foreach ([
            'vet_area', 'vet_atencion', 'vet_consultas', 'vet_pacientes', 'vet_agenda',
            'registrar_consulta_ajax', 'registrar_vacuna_ajax',
            'registrar_desparasitacion_ajax', 'registrar_nueva_vacuna_ajax',
            'registrar_nuevo_laboratorio_ajax',
            'registrar_nuevo_producto_desparasitacion_ajax',
            // RN-407: atender una cita (iniciarla, cerrarla o marcarla como no
            // asistida) es del veterinario asignado.
            'iniciar_cita_ajax', 'completar_cita_ajax', 'marcar_no_asistio_ajax', 'cerrar_sin_consulta_ajax',
        ] as $a) { $matriz[$a] = $soloVet; }

        // Consulta de informacion clinica: el administrador si la necesita
        // para auditoria.
        foreach ([
            'listar_historial_ajax',
            'get_laboratorios_ajax', 'get_productos_desparasitacion_ajax',
            'get_vacunas_por_especie_ajax',
        ] as $a) { $matriz[$a] = $clinico; }

        // Personal de la clinica: pacientes, propietarios y citas.
        foreach ([
            'nueva_mascota', 'actualizar_mascota', 'buscar_mascotas', 'guardar_mascota_ajax',
            'actualizar_mascota_ajax', 'cambiar_estado_mascota_ajax', 'get_mascota_ajax',
            'listar_mascotas_ajax', 'listar_mascotas_propietario_ajax',
            'nuevo_propietario', 'guardar_propietario_ajax',
            'listar_propietarios_ajax', 'get_propietario_ajax', 'actualizar_propietario_ajax',
            'buscar_propietario_exacto_ajax', 'solicitar_vinculo_propietario_ajax', 'vincular_mascota_ajax',
            'registrar_cita_ajax', 'listar_citas_ajax',
            'get_cita_ajax', 'reprogramar_cita_ajax', 'confirmar_cita_ajax',
            'listar_veterinarios_ajax', 'listar_tipos_cita_ajax',
            'get_charts_data_ajax', 'get_role_stats_ajax', 'get_timeline_ajax',
            'get_pendientes_ajax',
        ] as $a) { $matriz[$a] = $staff; }

        // Portal del propietario: solo el dueno, sobre sus propios datos.
        foreach ([
            'portal_propietario', 'portal_registrar_mascota_ajax',
            'portal_actualizar_mascota_ajax', 'portal_actualizar_datos_contacto_ajax',
            'portal_agendar_cita_ajax', 'portal_get_vets_ajax', 'portal_get_tipos_cita_ajax',
            'portal_get_detalle_cita_clinica_ajax', 'portal_imprimir_historial',
            'ver_detalle_mascota_propietario_ajax',
        ] as $a) { $matriz[$a] = $portal; }

        // Plataforma: el panel del super-administrador llega en la etapa E.
        $matriz['plataforma_inicio'] = $plataforma;

        return $matriz;
    }

    /**
     * Corta la peticion: JSON para AJAX, redireccion para navegacion normal.
     */
    public static function responder(AccesoDenegado $e, string $action): void {
        if (self::isAjax() || str_ends_with($action, '_ajax')) {
            header('Content-Type: application/json');
            http_response_code($e->codigo());
            $cuerpo = ['success' => false, 'message' => $e->getMessage()];
            if ($e->redirigir() !== 'dashboard' && $e->redirigir() !== $action) {
                $cuerpo['redirect'] = 'index.php?action=' . $e->redirigir();
            }
            echo json_encode($cuerpo);
            exit;
        }

        if ($e->codigo() !== 401) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: index.php?action=' . $e->redirigir());
        exit;
    }

    /** Contextos vigentes de la persona (null si la cuenta no existe o está inactiva). */
    private static function contextosDe(int $idUsuario): ?array {
        if (self::$fuenteContextos === null) {
            require_once __DIR__ . '/../models/Usuario.php';
            $db = self::conexion();
            if ($db === null) {
                throw new AccesoDenegado(401, 'No se pudo verificar la sesion. Intenta nuevamente.');
            }
            $usuario = new Usuario($db);
            self::$fuenteContextos = static fn (int $id): ?array => $usuario->contextosDe($id);
        }
        return (self::$fuenteContextos)($idUsuario);
    }

    /** Punto de inyeccion para las pruebas (null vuelve a la base real). */
    public static function definirFuenteDeContextos(?callable $fuente): void {
        self::$fuenteContextos = $fuente;
    }

    private static function auditoria() {
        if (self::$auditoria !== false) return self::$auditoria;

        try {
            require_once __DIR__ . '/../models/Auditoria.php';
            $db = self::conexion();
            self::$auditoria = $db ? new Auditoria($db) : null;
        } catch (Throwable $e) {
            error_log('Security: sin auditoria (' . $e->getMessage() . ')');
            self::$auditoria = null;
        }
        return self::$auditoria;
    }

    /** Punto de inyeccion para las pruebas. */
    public static function definirAuditoria($auditoria): void {
        self::$auditoria = $auditoria;
    }

    private static ?PDO $conexion = null;

    private static function conexion(): ?PDO {
        if (self::$conexion === null) {
            require_once __DIR__ . '/../config/Database.php';
            self::$conexion = (new Database())->getConnection();
        }
        return self::$conexion;
    }

    /**
     * Rate limiting para login: máximo 5 intentos cada 15 minutos.
     *
     * $cuenta es el id_usuario cuando el identificador escrito corresponde a
     * una cuenta; si no, solo cuenta la IP. Quien llama responde igual en los
     * dos casos, así que el límite no revela si la cuenta existe (RN-G15).
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
            $conexion = self::conexion();
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
