<?php

require_once '../config/Database.php';
require_once '../models/Usuario.php';
require_once '../models/PasswordReset.php';
require_once '../models/VerificacionEmail.php';
require_once '../models/Auditoria.php';
require_once '../config/EmailService.php';
require_once '../helpers/Csrf.php';
require_once '../helpers/Security.php';
require_once '../helpers/GoogleToken.php';
require_once '../helpers/PoliticaPassword.php';
require_once '../helpers/Autenticador.php';
require_once '../helpers/Contexto.php';
require_once __DIR__ . '/ContextoController.php';

class AuthController {
    private $db;
    private $usuarioModel;
    private $passwordResetModel;
    private $verificacionEmailModel;
    private $emailService;
    private $auditoria;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->usuarioModel = new Usuario($this->db);
        $this->passwordResetModel = new PasswordReset($this->db);
        $this->verificacionEmailModel = new VerificacionEmail($this->db);
        $this->emailService = new EmailService();
        $this->auditoria = new Auditoria($this->db);
    }

    public function login() {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            // Validar token CSRF
            if (!Csrf::validate('login')) {
                $this->redirectWithError("Token de seguridad inválido. Recarga la página e intenta nuevamente.");
                return;
            }

            // RE-T.1.1: se entra con el documento o con el correo. T-21: al
            // identificador se le recorta el espacio sobrante, pero a la
            // contrasena no: recortarla altera en silencio lo que se escribio.
            $identificador = trim((string) ($_POST['identificador'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            if ($identificador === '' || $password === '') {
                $this->redirectWithError("Escribe tu documento o correo y tu contraseña.");
                return;
            }

            // RN-G15: el resultado se calcula antes de mirar el límite para
            // conocer el id_usuario y contar por cuenta. El mensaje es el
            // mismo exista o no la cuenta, así que el límite no la revela.
            $intento = (new Autenticador($this->usuarioModel, $this->verificacionEmailModel))
                ->conPassword($identificador, $password);
            $cuenta = $intento['id_cuenta'] !== null ? (string) $intento['id_cuenta'] : null;

            if (!Security::checkRateLimit($cuenta)) {
                $this->redirectWithError("Demasiados intentos fallidos. Espera 15 minutos antes de volver a intentarlo.");
                return;
            }

            switch ($intento['resultado']) {
                case 'ok':
                    // T-22: el contador se limpia antes de cualquier redirección.
                    Security::resetRateLimit($cuenta);
                    header('Location: index.php?action=' . $this->iniciarSesion($intento['usuario'], 'password'));
                    exit();

                case 'pendiente':
                    // HU-36 (VD-SEG-08): este mensaje SI es especifico: solo se
                    // llega aqui despues de acertar la contrasena, asi que no le
                    // sirve a quien enumera cuentas.
                    Security::recordFailedLogin($cuenta);
                    $this->redirectWithError("Debes verificar tu correo antes de iniciar sesion. Revisa el enlace que te enviamos.");
                    return;

                default:
                    // HU-38 (VD-SEG-06): «cuenta inactiva» usa el mismo mensaje
                    // que las credenciales incorrectas; el motivo real queda en
                    // auditoría, sin clínica porque aún no hay contexto.
                    Security::recordFailedLogin($cuenta);
                    $this->auditoria->log(
                        $intento['id_cuenta'],
                        'LOGIN_FAIL',
                        'usuarios',
                        $intento['id_cuenta'],
                        null,
                        null,
                        $intento['resultado'] === 'inactiva'
                            ? 'Intento de login fallido: cuenta inactiva'
                            : 'Intento de login fallido: credenciales incorrectas',
                        null
                    );
                    $this->redirectWithError(self::MENSAJE_CREDENCIALES);
            }
        } else {
            // Si es GET, mostramos la vista
            require_once '../views/auth/login.php';
        }
    }

    /** RE-T.1.5: el mismo mensaje para cualquier credencial inválida. */
    private const MENSAJE_CREDENCIALES = 'Documento, correo o contraseña incorrectos.';

    /**
     * Abre la sesión de una identidad ya autenticada y devuelve a dónde ir
     * (HU-T.17): con un solo contexto entra directo; con varios, al selector.
     */
    private function iniciarSesion(array $usuario, string $metodo): string
    {
        // T-02: identificador de sesion nuevo al elevar privilegios (session fixation).
        session_regenerate_id(true);
        unset($_SESSION['registro_pendiente'], $_SESSION['google_pending_register']);

        $idUsuario = (int) $usuario['id_usuario'];
        Contexto::iniciarIdentidad($idUsuario, (string) $usuario['nombre_completo'], (int) $usuario['debe_cambiar_password'] === 1, $metodo);

        // El inicio de sesión es de la persona, no de una clínica.
        $this->auditoria->log(
            $idUsuario,
            'LOGIN',
            'usuarios',
            $idUsuario,
            null,
            null,
            $metodo === 'google' ? 'Inicio de sesion con Google' : 'Inicio de sesión exitoso',
            null
        );

        $disponibles = $this->usuarioModel->contextosDe($idUsuario) ?? [];
        if (count($disponibles) === 1) {
            ContextoController::entrar($disponibles[0], $disponibles, $this->auditoria);
        }

        // T-05: con contraseña temporal, primero el cambio (Security lo exige igual).
        if ((int) $usuario['debe_cambiar_password'] === 1) {
            return 'cambiar_password';
        }
        return count($disponibles) === 1 ? Contexto::destino($disponibles[0]) : 'seleccionar_contexto';
    }

    public function solicitarResetPasswordAjax() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(false, 'Método no permitido.');
        }

        try {
            $email = trim(strtolower($_POST['email'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->jsonResponse(false, 'Ingresa un correo electrónico válido.');
            }

            $mensajeGenerico = 'Si el correo está registrado, recibirás un mensaje con instrucciones para restablecer tu contraseña.';
            $user = $this->usuarioModel->buscarPorEmail($email);

            if (!$user || (int)($user['estado'] ?? 0) !== 1) {
                $this->jsonResponse(true, $mensajeGenerico);
            }

            $this->passwordResetModel->deleteExpiredTokens();
            $this->passwordResetModel->invalidateTokensForEmail($email);

            $tokenPlano = bin2hex(random_bytes(32));
            $tokenHash = password_hash($tokenPlano, PASSWORD_DEFAULT);
            $expiresAt = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
            $tokenId = $this->passwordResetModel->createToken((int) $user['id_usuario'], $email, $tokenHash, $expiresAt);

            $resetLink = $this->buildResetLink($tokenId, $tokenPlano);
            $nombre = $user['nombre_completo'] ?: 'Usuario de Zooki';

            $this->emailService->limpiarDirecciones();
            $enviado = $this->emailService->enviarCorreoPersonalizado(
                $email,
                $nombre,
                'Restablece tu contraseña de Zooki',
                $this->renderResetEmail($nombre, $resetLink, 60)
            );

            if (!$enviado) {
                $this->jsonResponse(false, 'No fue posible enviar el correo en este momento. Inténtalo de nuevo más tarde.');
            }

            $this->jsonResponse(true, $mensajeGenerico);
        } catch (Exception $e) {
            error_log('Error solicitando reset de contraseña: ' . $e->getMessage());
            $this->jsonResponse(false, 'Ocurrió un error inesperado. Inténtalo de nuevo más tarde.');
        }
    }

    public function mostrarResetPassword() {
        $tokenId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $tokenPlano = $_GET['token'] ?? '';
        $tokenValido = false;
        $errorMessage = '';

        if ($tokenId <= 0 || empty($tokenPlano)) {
            $errorMessage = 'El enlace de restablecimiento es inválido.';
        } else {
            $reset = $this->passwordResetModel->findById($tokenId);
            if (!$reset || (int)$reset['used'] === 1) {
                $errorMessage = 'Este enlace ya fue utilizado o no es válido.';
            } else {
                $expira = new DateTime($reset['expires_at']);
                if ($expira < new DateTime()) {
                    $errorMessage = 'Este enlace ha expirado. Solicita uno nuevo.';
                } elseif (!password_verify($tokenPlano, $reset['token_hash'])) {
                    $errorMessage = 'El enlace de restablecimiento es inválido.';
                } else {
                    $tokenValido = true;
                }
            }
        }

        require_once '../views/auth/reset_password.php';
    }

    public function procesarResetPasswordAjax() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(false, 'Método no permitido.');
        }

        try {
            $tokenId = isset($_POST['token_id']) ? (int)$_POST['token_id'] : 0;
            $tokenPlano = $_POST['token'] ?? '';
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirmation'] ?? '';

            if ($tokenId <= 0 || empty($tokenPlano)) {
                $this->jsonResponse(false, 'El enlace para restablecer la contraseña no es válido.');
            }

            if ($password !== $passwordConfirm) {
                $this->jsonResponse(false, 'Las contraseñas no coinciden.');
            }

            // Primero el token: si el enlace no vale, no hay nada que validar.
            $reset = $this->passwordResetModel->findById($tokenId);
            if (!$reset || (int)$reset['used'] === 1 || !password_verify($tokenPlano, $reset['token_hash'])) {
                $this->jsonResponse(false, 'El enlace para restablecer la contraseña no es válido o ya fue utilizado.');
            }

            $expira = new DateTime($reset['expires_at']);
            if ($expira < new DateTime()) {
                $this->jsonResponse(false, 'El enlace ha expirado. Solicita uno nuevo.');
            }

            // El enlace está ligado a la persona por id_usuario; si no lo
            // trajera, se busca por el correo al que se envió.
            $user = !empty($reset['id_usuario'])
                ? $this->usuarioModel->buscarPorId((int) $reset['id_usuario'])
                : $this->usuarioModel->buscarPorEmail((string) $reset['email']);
            if (!$user) {
                $this->jsonResponse(false, 'No encontramos la cuenta asociada a este enlace.');
            }

            // HU-36: la misma politica de todos los flujos, contrastada ademas
            // con los datos del titular.
            $motivo = PoliticaPassword::validar($password, [
                $user['documento'] ?? '',
                $user['nombre_completo'] ?? '',
                $user['email'] ?? '',
            ]);
            if ($motivo !== null) {
                $this->jsonResponse(false, $motivo);
            }

            $idUsuario = (int) $user['id_usuario'];
            if (!$this->usuarioModel->actualizarPassword($idUsuario, password_hash($password, PASSWORD_DEFAULT))) {
                $this->jsonResponse(false, 'No fue posible actualizar la contraseña. Inténtalo nuevamente.');
            }

            $this->usuarioModel->marcarCambioPassword($idUsuario, false);
            $this->passwordResetModel->markTokenUsed($tokenId);
            $this->auditoria->log($idUsuario, 'UPDATE', 'usuarios', $idUsuario, null, null, 'Cambio de contraseña por restablecimiento', null);

            $this->jsonResponse(true, 'Tu contraseña se actualizó correctamente. Ya puedes iniciar sesión.');
        } catch (Exception $e) {
            error_log('Error procesando reset de contraseña: ' . $e->getMessage());
            $this->jsonResponse(false, 'Ocurrió un error inesperado. Inténtalo de nuevo más tarde.');
        }
    }

    public function logout() {
        // Auditoría: logout, en la clínica del contexto si lo hay.
        $idUsuario = Contexto::idUsuario();
        if ($idUsuario !== null) {
            $this->auditoria->log($idUsuario, 'LOGOUT', 'usuarios', $idUsuario, null, null, 'Cierre de sesión');
        }
        // T-02: no basta con destruir $_SESSION; hay que invalidar tambien la
        // cookie en el navegador para que el id de sesion no se pueda reutilizar.
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        // Sin action el front controller resuelve "landing": hay que nombrar el login.
        header("Location: index.php?action=login");
        exit();
    }

    public function cambiarPasswordAjax() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            try {
                $nuevaPassword = $_POST['nueva_password'] ?? $_POST['password_nueva'] ?? '';
                $idUsuario = Contexto::idUsuario();
                $usuarioActual = $idUsuario !== null ? $this->usuarioModel->buscarPorId($idUsuario) : null;
                if ($usuarioActual === null) {
                    echo json_encode(['success' => false, 'message' => 'Sesion expirada. Inicia sesion nuevamente.']);
                    exit;
                }

                // HU-36: misma politica que el registro y el restablecimiento.
                $motivo = PoliticaPassword::validar($nuevaPassword, [
                    $usuarioActual['documento'] ?? '',
                    $usuarioActual['nombre_completo'] ?? '',
                    $usuarioActual['email'] ?? '',
                ]);
                if ($motivo !== null) {
                    echo json_encode(['success' => false, 'message' => $motivo]);
                    exit;
                }

                // HU-39: se pide la contraseña actual salvo si la cuenta no
                // tiene ninguna (creada con Google: password NULL, MER §2).
                if ((int) $usuarioActual['tiene_password'] === 1) {
                    $passwordActual = $_POST['password_actual'] ?? '';
                    if (empty($passwordActual)) {
                        echo json_encode(['success' => false, 'message' => 'La contraseña actual es requerida']);
                        exit;
                    }
                    if (!$this->usuarioModel->verificarPassword($idUsuario, $passwordActual)) {
                        echo json_encode(['success' => false, 'message' => 'La contraseña actual es incorrecta']);
                        exit;
                    }
                }

                $passwordHash = password_hash($nuevaPassword, PASSWORD_DEFAULT);

                if ($this->usuarioModel->actualizarPassword($idUsuario, $passwordHash)) {
                    $this->usuarioModel->marcarCambioPassword($idUsuario, false);

                    // T-05: hay que bajar tambien el indicador en la sesion. Si
                    // solo se actualiza la base de datos, el bloqueo de
                    // Security::validatePasswordTemporal() sigue activo durante
                    // toda la sesion y el usuario queda encerrado en este
                    // formulario despues de haber cambiado la clave.
                    $_SESSION['debe_cambiar_password'] = 0;

                    // RN-G05 / HU-42: el cambio de contraseña queda en la
                    // actividad de la cuenta, sin guardar la contraseña.
                    $this->auditoria->log($idUsuario, 'UPDATE', 'usuarios', $idUsuario, null, null, 'Cambio de contraseña');

                    // Si el usuario configuró una contraseña por primera vez, cambiamos a 'password'
                    $_SESSION['login_method'] = 'password';

                    echo json_encode(['success' => true, 'message' => 'Contraseña actualizada exitosamente']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al actualizar la contraseña']);
                }
            } catch (Exception $e) {
                // T-04: el detalle tecnico va al log, nunca al cliente.
                error_log('Error al cambiar contrasena: ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'No se pudo actualizar la contraseña. Intenta nuevamente.']);
            }
        }
    }

    public function register() {
        require_once '../views/auth/register.php';
    }

    public function processRegister() {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            // Validar token CSRF
            if (!Csrf::validate('register')) {
                $this->respuestaRegistro(false, "Token de seguridad inválido. Por favor intenta de nuevo.");
            }

            // Limpiar datos
            $tipo_documento = trim($_POST['tipo_documento'] ?? '');
            $documento = trim($_POST['documento'] ?? '');
            $nombre_completo = trim($_POST['nombre_completo'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $email = trim(strtolower($_POST['email'] ?? ''));
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            // Validaciones básicas del servidor
            if (empty($tipo_documento) || empty($documento) || empty($nombre_completo) || empty($telefono) || empty($email) || empty($password)) {
                $this->respuestaRegistro(false, "Todos los campos son obligatorios.");
            }

            // La Ley 1581 de 2012 exige autorización previa, expresa e informada del
            // titular. El atributo `required` del navegador se puede eludir, así que la
            // ausencia de consentimiento debe bloquear el registro también aquí.
            if (empty($_POST['acepta_datos'])) {
                $this->respuestaRegistro(false, "Debes autorizar el tratamiento de tus datos personales para crear la cuenta.");
            }

            if ($password !== $confirm_password) {
                $this->respuestaRegistro(false, "Las contraseñas no coinciden.");
            }

            // HU-36: el registro era el flujo mas debil (6 caracteres, sin
            // complejidad) y por tanto el que definia la politica real.
            $motivoPassword = PoliticaPassword::validar($password, [$documento, $nombre_completo, $email]);
            if ($motivoPassword !== null) {
                $this->respuestaRegistro(false, $motivoPassword);
            }

            // RN-G06: documento y correo únicos en la plataforma.
            if ($this->usuarioModel->existeDocumento($documento)) {
                $this->respuestaRegistro(false, "El documento ya está registrado en el sistema.");
            }
            if ($this->usuarioModel->existeEmail($email)) {
                $this->respuestaRegistro(false, "El correo electrónico ya está registrado.");
            }

            // Se crea la identidad, sin rol. El vínculo con una clínica como
            // propietario (propietario_clinica) y la prueba del consentimiento
            // llegan con HU-5.8 y HU-T.19 (etapa D); hasta entonces la cuenta
            // entra al selector sin contextos disponibles.
            $idUsuario = $this->usuarioModel->crear([
                'documento' => $documento,
                'tipo_documento' => $tipo_documento,
                'nombre_completo' => $nombre_completo,
                'telefono' => $telefono,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
            ]);

            if ($idUsuario > 0) {
                // HU-36 (VD-SEG-08). Antes esto iniciaba sesión de una vez, así
                // que cualquiera podía registrarse con el correo de otra persona
                // y quedar dentro. Ahora la cuenta queda pendiente hasta que se
                // abra el enlace que llega a ese buzón.
                $tokenPlano = bin2hex(random_bytes(32));
                $tokenHash = password_hash($tokenPlano, PASSWORD_DEFAULT);
                $expira = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');
                $verificacionId = $this->verificacionEmailModel->crear($idUsuario, $email, $tokenHash, $expira);

                $this->emailService->limpiarDirecciones();
                $enlace = $this->buildVerificacionLink($verificacionId, $tokenPlano);
                $this->emailService->enviarCorreoVerificacion($email, $nombre_completo, $enlace, 24);

                $this->auditoria->log($idUsuario, 'INSERT', 'usuarios', $idUsuario, null, null, 'Auto-registro, pendiente de verificar correo', null);

                // La pagina de registro se queda esperando la confirmacion en vez
                // de recargar: aqui se guarda a quien hay que vigilar, para que
                // el sondeo no pueda preguntar por una cuenta ajena.
                $_SESSION['registro_pendiente'] = [
                    'id_usuario' => $idUsuario,
                    'email' => $email,
                    'desde' => time(),
                ];

                $this->respuestaRegistro(
                    true,
                    "Cuenta creada. Te enviamos un correo a $email para confirmar tu dirección.",
                    ['email' => $email, 'esperando_confirmacion' => true]
                );
            } else {
                $this->respuestaRegistro(false, "Ocurrió un error al procesar el registro. Intenta más tarde.");
            }
        }
    }

    private function redirectWithError($message) {
        $_SESSION['error_login'] = $message;
        // Debe incluir la accion: `index.php` a secas resuelve a la landing y
        // expulsaria al usuario de la pantalla de login con el error pendiente.
        header("Location: index.php?action=login");
        exit();
    }

    /**
     * Punto unico de salida del registro. Si la peticion viene por AJAX
     * responde JSON y la pagina se queda donde esta; si no, conserva el
     * comportamiento de siempre (mensaje en sesion y vuelta al login), para
     * que el formulario siga funcionando sin JavaScript.
     */
    private function respuestaRegistro(bool $ok, string $mensaje, array $extra = []): void
    {
        if ($this->esPeticionAjax()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array_merge(['success' => $ok, 'message' => $mensaje], $extra));
            exit();
        }

        $_SESSION[$ok ? 'success_register' : 'error_register'] = $mensaje;
        header('Location: index.php?action=login');
        exit();
    }

    private function esPeticionAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Sondeo que usa la pantalla de espera del registro para saber si el
     * usuario ya abrio el enlace del correo.
     *
     * Solo consulta la verificacion guardada en la sesion de quien acaba de
     * registrarse: no recibe ningun identificador por parametro, asi que no
     * sirve para preguntar por cuentas ajenas.
     */
    public function estadoVerificacionAjax()
    {
        header('Content-Type: application/json; charset=utf-8');

        $pendiente = $_SESSION['registro_pendiente'] ?? null;
        if (!$pendiente) {
            // Puede que el enlace se abriera en otra pestana del mismo
            // navegador y esa ya haya iniciado la sesion.
            if (Contexto::idUsuario() !== null) {
                echo json_encode(['estado' => 'confirmado', 'redirect' => 'index.php?action=dashboard']);
                exit();
            }
            echo json_encode(['estado' => 'sin_registro']);
            exit();
        }

        // Corta el sondeo pasadas 24 horas, lo mismo que dura el enlace.
        if (time() - (int) $pendiente['desde'] > 86400) {
            unset($_SESSION['registro_pendiente']);
            echo json_encode(['estado' => 'expirado']);
            exit();
        }

        $idUsuario = (int) ($pendiente['id_usuario'] ?? 0);
        if ($this->verificacionEmailModel->hayPendiente($idUsuario)) {
            echo json_encode(['estado' => 'pendiente']);
            exit();
        }

        // Confirmado: el correo quedo demostrado, asi que se abre la sesion
        // sin pedir la contrasena otra vez.
        $usuario = $this->usuarioModel->buscarPorId($idUsuario);
        unset($_SESSION['registro_pendiente']);

        if (!$usuario || (int) $usuario['estado'] !== 1) {
            echo json_encode(['estado' => 'sin_registro']);
            exit();
        }

        $destino = $this->iniciarSesion($usuario, 'password');
        echo json_encode(['estado' => 'confirmado', 'redirect' => 'index.php?action=' . $destino]);
        exit();
    }

    /**
     * HU-36 (VD-SEG-08) — Endpoint publico que confirma el correo. Es publico
     * a proposito: quien lo abre todavia no puede iniciar sesion, que es
     * justamente lo que viene a habilitar.
     */
    public function verificarEmail()
    {
        $id = (int) ($_GET['id'] ?? 0);
        $tokenPlano = $_GET['token'] ?? '';

        // Mensaje unico para todos los fallos: no se distingue "no existe" de
        // "ya usado" ni de "vencido", para no confirmar que un id es real.
        $mensajeError = 'El enlace de confirmación no es válido o ya fue utilizado.';

        if ($id <= 0 || $tokenPlano === '') {
            $_SESSION['error_login'] = $mensajeError;
            header('Location: index.php?action=login');
            exit();
        }

        $verificacion = $this->verificacionEmailModel->buscarPorId($id);

        if (!$verificacion
            || (int) $verificacion['used'] === 1
            || strtotime($verificacion['expires_at']) <= time()
            || !password_verify($tokenPlano, $verificacion['token_hash'])) {
            $_SESSION['error_login'] = $mensajeError;
            header('Location: index.php?action=login');
            exit();
        }

        $this->verificacionEmailModel->marcarUsada($id);

        $idUsuario = (int) $verificacion['id_usuario'];
        $this->auditoria->log($idUsuario, 'UPDATE', 'usuarios', $idUsuario, null, null, 'Correo electronico confirmado', null);

        // Recien ahora se da la bienvenida: antes no habia certeza de que el
        // buzon fuera del titular.
        $usuario = $this->usuarioModel->buscarPorId($idUsuario);
        if ($usuario) {
            $this->emailService->limpiarDirecciones();
            $this->emailService->enviarCorreoBienvenida($verificacion['email'], $usuario['nombre_completo']);
        }

        // Si quien abre el enlace es el mismo navegador que se acaba de
        // registrar, ya no tiene sentido mandarlo al login: el correo quedo
        // demostrado, asi que entra directo.
        $pendiente = $_SESSION['registro_pendiente'] ?? null;
        if ($usuario && (int) $usuario['estado'] === 1 && $pendiente && (int) ($pendiente['id_usuario'] ?? 0) === $idUsuario) {
            header('Location: index.php?action=' . $this->iniciarSesion($usuario, 'password'));
            exit();
        }

        $_SESSION['success_register'] = 'Tu correo quedó confirmado. Ya puedes iniciar sesión.';
        header('Location: index.php?action=login');
        exit();
    }

    private function buildVerificacionLink(int $id, string $tokenPlano): string
    {
        return $this->buildEnlaceDeAccion('verificar_email', $id, $tokenPlano);
    }

    private function buildResetLink(int $tokenId, string $tokenPlano): string
    {
        return $this->buildEnlaceDeAccion('reset_password', $tokenId, $tokenPlano);
    }

    /**
     * Arma un enlace absoluto a una accion con id y token. Lo comparten el
     * restablecimiento de contrasena y la confirmacion de correo (HU-36), que
     * necesitan exactamente la misma URL con distinta accion.
     */
    private function buildEnlaceDeAccion(string $action, int $id, string $tokenPlano): string
    {
        // Cargar APP_URL del archivo .env si está configurado
        $appUrl = null;
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $env = parse_ini_file($envFile);
            $appUrl = $env['APP_URL'] ?? null;
        }

        $query = http_build_query([
            'action' => $action,
            'id' => $id,
            'token' => $tokenPlano,
        ]);

        if (!empty($appUrl)) {
            $baseUrl = rtrim($appUrl, '/');
            if (!str_ends_with($baseUrl, 'index.php')) {
                $baseUrl .= '/index.php';
            }
            return sprintf('%s?%s', $baseUrl, $query);
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $directory = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        $path = ($directory && $directory !== '.') ? $directory . '/index.php' : '/index.php';

        return sprintf('%s://%s%s?%s', $scheme, $host, $path, $query);
    }

    private function renderResetEmail(string $nombre, string $enlace, int $expiraEnMinutos): string
    {
        $expiraTexto = $expiraEnMinutos >= 60
            ? sprintf('%d hora%s', $expiraEnMinutos / 60, $expiraEnMinutos / 60 > 1 ? 's' : '')
            : sprintf('%d minutos', $expiraEnMinutos);

        return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html dir="ltr" lang="es">
  <head>
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type" />
    <meta name="x-apple-disable-message-reformatting" />
  </head>
  <body style="background-color:#ffffff">
    <div
      style="display:none;overflow:hidden;line-height:1px;opacity:0;max-height:0;max-width:0"
      data-skip-in-text="true">
      Restablece tu contraseña de Zooki
    </div>
    <table
      border="0"
      width="100%"
      cellpadding="0"
      cellspacing="0"
      role="presentation"
      align="center">
      <tbody>
        <tr>
          <td
            style=\'background-color:#ffffff;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif\'>
            <table
              align="center"
              width="100%"
              border="0"
              cellpadding="0"
              cellspacing="0"
              role="presentation"
              style="max-width:37.5em;margin:0 auto;padding:40px 20px 64px 20px;width:600px">
              <tbody>
                <tr style="width:100%">
                  <td>
                    <table
                      align="center"
                      width="100%"
                      border="0"
                      cellpadding="0"
                      cellspacing="0"
                      role="presentation"
                      style="margin-bottom:32px;text-align:left">
                      <tbody>
                        <tr>
                          <td>
                            <table
                              border="0"
                              cellpadding="0"
                              cellspacing="0"
                              style="border-collapse:collapse">
                              <tr>
                                <td
                                  style="vertical-align:middle;padding-right:0px">
                                  <img
                                    alt="Zooki Icon"
                                    height="36"
                                    src="cid:zooki_icon_blue"
                                    style="display:block;outline:none;border:none;text-decoration:none;height:auto"
                                    width="36" />
                                </td>
                                <td style="vertical-align:middle">
                                  <img
                                    alt="Zooki logotipo"
                                    src="cid:zooki_logotipo"
                                    style="display:block;outline:none;border:none;text-decoration:none;margin:-15px 0 -15px -10px;height:auto"
                                    width="110" />
                                </td>
                              </tr>
                            </table>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                    <h1
                      style="color:#1d1c1d;font-size:36px;font-weight:800;letter-spacing:-1.2px;line-height:42px;margin:0 0 20px 0">
                      Restablecer tu contraseña
                    </h1>
                    <p
                      style="font-size:20px;line-height:28px;color:#1d1c1d;margin:0 0 24px 0;margin-top:0;margin-right:0;margin-bottom:24px;margin-left:0">
                      Hola,
                      ' . htmlspecialchars($nombre) . '. Has solicitado cambiar la
                      contraseña para acceder a tu panel de control.
                    </p>
                    <p
                      style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;margin-top:0;margin-right:0;margin-bottom:16px;margin-left:0">
                      Para completar el proceso de restablecimiento, haz clic en
                      el siguiente botón:
                    </p>
                    <table
                      align="center"
                      width="100%"
                      border="0"
                      cellpadding="0"
                      cellspacing="0"
                      role="presentation"
                      style="margin:28px 0">
                      <tbody>
                        <tr>
                          <td>
                            <a
                              href="' . $enlace . '"
                              style="line-height:22px;text-decoration:none;display:inline-block;max-width:100%;mso-padding-alt:0px;background-color:#0052ff;border-radius:4px;color:#ffffff;font-size:15px;font-weight:700;text-align:center;padding:12px 24px;padding-top:12px;padding-right:24px;padding-bottom:12px;padding-left:24px"
                              target="_blank"
                              ><span><!--[if mso]><i style="mso-font-width:400%;mso-text-raise:18" hidden>&#8202;&#8202;&#8202;</i><![endif]--></span><span
                                style="max-width:100%;display:inline-block;line-height:120%;mso-padding-alt:0px;mso-text-raise:9px"
                                >Restablecer contraseña</span><span><!--[if mso]><i style="mso-font-width:400%" hidden>&#8202;&#8202;&#8202;&#8203;</i><![endif]--></span></a>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                    <p
                      style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;margin-top:0;margin-right:0;margin-bottom:16px;margin-left:0">
                      Si el botón no funciona o no responde, puedes copiar y
                      pegar la siguiente dirección en tu navegador:<br /><a
                        href="' . $enlace . '"
                        style="color:#1264a3;text-decoration-line:none;text-decoration:none;word-break:break-all;font-size:14px"
                        target="_blank"
                        >' . $enlace . '</a
                      >
                    </p>
                    <p
                      style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;margin-top:0;margin-right:0;margin-bottom:16px;margin-left:0">
                      Por motivos de seguridad, este enlace es temporal y
                      expirará en ' . $expiraTexto . '. Si no has sido tú quien solicitó
                      este cambio, puedes ignorar este mensaje de forma segura y
                      tu contraseña seguirá siendo la misma.
                    </p>
                    <table
                      align="center"
                      width="100%"
                      border="0"
                      cellpadding="0"
                      cellspacing="0"
                      role="presentation"
                      style="border-top:1px solid #dddddd;margin:32px 0 24px 0">
                      <tbody>
                        <tr>
                          <td></td>
                        </tr>
                      </tbody>
                    </table>
                    <table
                      align="center"
                      width="100%"
                      border="0"
                      cellpadding="0"
                      cellspacing="0"
                      role="presentation"
                      style="text-align:left">
                      <tbody>
                        <tr>
                          <td>
                            <p
                              style="font-size:13px;line-height:18px;color:#868686;margin:0 0 8px 0;margin-top:0;margin-right:0;margin-bottom:8px;margin-left:0">
                              Enviado con 💙 por el equipo de Zooki<br />Zooki
                              Inc. · Gestión y Cuidado Veterinario
                            </p>
                            <p
                              style="font-size:11px;line-height:16px;color:#b0b0b0;margin:0;margin-top:0;margin-bottom:0;margin-left:0;margin-right:0">
                              Si tienes alguna duda o consideras que esto es un
                              error de seguridad, por favor comunícate con
                              nuestro soporte administrativo.
                            </p>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </td>
                </tr>
              </tbody>
            </table>
          </td>
        </tr>
      </tbody>
    </table>
  </body>
</html>';
    }

    public function checkDocumentAjax()
    {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            // HU-38 (VD-SEG-06): el formulario de registro necesita avisar si
            // el documento ya está tomado, así que el endpoint no puede dejar
            // de responder. Lo que se corta es el uso masivo: a mano nadie
            // llega al límite, un script que enumere sí.
            if (!Security::checkVerificationLimit()) {
                $this->jsonResponse(false, "Demasiadas verificaciones. Inténtalo más tarde.");
            }
            $documento = trim($_POST['documento'] ?? '');
            if (empty($documento)) {
                $this->jsonResponse(false, "Documento vacío.");
            }
            $this->jsonResponse(true, "Verificado", ['exists' => $this->usuarioModel->existeDocumento($documento)]);
        }
    }

    public function checkEmailAjax()
    {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            // Mismo límite que checkDocumentAjax (HU-38, VD-SEG-06).
            if (!Security::checkVerificationLimit()) {
                $this->jsonResponse(false, "Demasiadas verificaciones. Inténtalo más tarde.");
            }
            $email = trim(strtolower($_POST['email'] ?? ''));
            if (empty($email)) {
                $this->jsonResponse(false, "Email vacío.");
            }
            $this->jsonResponse(true, "Verificado", ['exists' => $this->usuarioModel->existeEmail($email)]);
        }
    }

    public function processGoogleLoginAjax()
    {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $accessToken = $_POST['access_token'] ?? '';
            $credential = $_POST['credential'] ?? ''; // Para Google One Tap
            
            if (empty($accessToken) && empty($credential)) {
                $this->jsonResponse(false, "No se recibió token de autenticación de Google.");
                return;
            }

            // HU-36 (VD-SEG-10). Antes se pedía el perfil a Google y bastaba un
            // HTTP 200. Eso confirma que el token es de Google, pero no que sea
            // PARA Zooki: un token emitido para otra aplicación pasaba igual, y
            // con él se entraba a la cuenta de cualquier usuario. Ahora se
            // consulta tokeninfo (que sí devuelve `aud`) y se compara contra
            // nuestro client_id antes de mirar el correo.
            $esIdToken = empty($accessToken);
            $token = $esIdToken ? $credential : $accessToken;

            $payload = GoogleToken::consultar($token, $esIdToken);
            if ($payload === null) {
                $this->jsonResponse(false, "No se pudo validar el token con Google.");
                return;
            }

            $motivo = GoogleToken::motivoDeRechazo($payload, GoogleToken::clientId(), $esIdToken);
            if ($motivo !== null) {
                // Al cliente se le responde en genérico (HU-38); el motivo real
                // queda en el log del servidor para poder diagnosticar.
                error_log('HU-36: token de Google rechazado - ' . $motivo);
                $this->jsonResponse(false, "No se pudo validar el token con Google.");
                return;
            }

            $email = trim(strtolower($payload['email']));
            $nombre_completo = $payload['name'] ?? '';

            // tokeninfo no siempre trae el nombre; para el access_token se
            // completa con el perfil, que ya sabemos que pertenece a Zooki.
            if ($nombre_completo === '' && !$esIdToken) {
                $perfil = GoogleToken::perfil($accessToken);
                $nombre_completo = $perfil['name'] ?? '';
            }

            // Se busca la cuenta por el correo que Google acaba de verificar.
            $intento = (new Autenticador($this->usuarioModel, $this->verificacionEmailModel))->conGoogle($email);

            if ($intento['resultado'] === 'inactiva') {
                // Google ya demostró que el buzón es de quien entra: aquí sí se
                // le puede decir por qué no pasa.
                $this->jsonResponse(false, "Tu cuenta está inactiva. Contacta al administrador.");
                return;
            }

            if ($intento['resultado'] === 'ok') {
                $destino = $this->iniciarSesion($intento['usuario'], 'google');
                $this->jsonResponse(true, "Login exitoso", ['action' => 'login', 'redirect' => 'index.php?action=' . $destino]);
            } else {
                // Usuario NO existe, requerimos completar su registro (Cédula y Teléfono)
                // Usamos $_SESSION temporal para guardar su info confirmada por Google.
                // El `sub` liga la cuenta de Google (usuarios.google_uid, MER §2).
                $_SESSION['google_pending_register'] = [
                    'email' => $email,
                    'nombre_completo' => $nombre_completo,
                    'google_uid' => isset($payload['sub']) ? (string) $payload['sub'] : null,
                    'verified' => true
                ];

                $this->jsonResponse(true, "Completa tu registro", [
                    'action' => 'complete_profile',
                    'email' => $email,
                    'name' => $nombre_completo
                ]);
            }
        }
    }

    public function completeGoogleRegisterAjax()
    {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            // Verificar que venga de un proceso de Google iniciado
            if (!isset($_SESSION['google_pending_register'])) {
                $this->jsonResponse(false, "Sesión de Google expirada o inválida. Intenta nuevamente.");
                return;
            }

            $pendingData = $_SESSION['google_pending_register'];
            $documento = trim($_POST['documento'] ?? '');
            $tipo_documento = trim($_POST['tipo_documento'] ?? 'CC');
            $telefono = trim($_POST['telefono'] ?? '');
            
            if (empty($documento) || empty($telefono)) {
                $this->jsonResponse(false, "El documento y el teléfono son obligatorios.");
                return;
            }

            // RN-G06: el documento es único en la plataforma.
            if ($this->usuarioModel->existeDocumento($documento)) {
                $this->jsonResponse(false, "Este documento ya se encuentra registrado en el sistema.");
                return;
            }

            // La cuenta nace sin contraseña (password NULL, MER §2): se entra
            // con Google hasta que su dueño cree una desde su perfil. Antes se
            // guardaba un hash aleatorio que en la base parecía una contraseña real.
            $idUsuario = $this->usuarioModel->crear([
                'documento' => $documento,
                'tipo_documento' => $tipo_documento,
                'nombre_completo' => $pendingData['nombre_completo'],
                'telefono' => $telefono,
                'email' => $pendingData['email'],
                'password' => null,
                'google_uid' => $pendingData['google_uid'] ?? null,
            ]);

            if ($idUsuario > 0) {
                // Enviar correo de bienvenida
                $this->emailService->limpiarDirecciones();
                $this->emailService->enviarCorreoBienvenida($pendingData['email'], $pendingData['nombre_completo']);

                $this->auditoria->log($idUsuario, 'INSERT', 'usuarios', $idUsuario, null, null, 'Registro con Google', null);

                // Como en el autorregistro, el vínculo con una clínica llega
                // con HU-5.8 (etapa D): por ahora entra al selector sin contextos.
                $usuario = $this->usuarioModel->buscarPorId($idUsuario);
                $destino = $this->iniciarSesion($usuario, 'google');
                $this->jsonResponse(true, "Registro exitoso", ['action' => 'login', 'redirect' => 'index.php?action=' . $destino]);
            } else {
                $this->jsonResponse(false, "Ocurrió un error al crear la cuenta. Intenta nuevamente.");
            }
        }
    }

    private function jsonResponse(bool $success, string $message, array $extra = []): void
    {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'extra' => $extra
        ]);
        exit;
    }
}
?>
