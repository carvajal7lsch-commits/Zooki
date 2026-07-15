<?php

require_once '../config/Database.php';
require_once '../models/Usuario.php';
require_once '../models/PasswordReset.php';
require_once '../models/Auditoria.php';
require_once '../config/EmailService.php';
require_once '../helpers/Csrf.php';

class AuthController {
    private $db;
    private $usuarioModel;
    private $passwordResetModel;
    private $emailService;
    private $auditoria;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->usuarioModel = new Usuario($this->db);
        $this->passwordResetModel = new PasswordReset($this->db);
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

            // Rate limiting: máximo 5 intentos cada 15 minutos
            if (!Security::checkRateLimit()) {
                header("Location: index.php?action=login");
                exit;
            }

            // Limpiamos los datos de entrada
            $documento = trim($_POST['documento']);
            $password = trim($_POST['password']);

            if (empty($documento) || empty($password)) {
                $this->redirectWithError("Por favor, ingrese documento y contraseña.");
                return;
            }

            // Buscamos al usuario en la base de datos
            $user = $this->usuarioModel->getUserByDocumento($documento);

            // Verificamos si existe y si la contraseña coincide (usando password_verify para hashes)
            // NOTA: Para las pruebas iniciales, si guardas la contraseña en texto plano en la BD,
            // esto fallará. Deberás usar password_hash() al insertar usuarios.
            if ($user && password_verify($password, $user['password'])) {
                if ($user['estado'] == 1) {
                    // Login exitoso: creamos las variables de sesión
                    $_SESSION['usuario_doc'] = $user['documento'];
                    $_SESSION['usuario_nombre'] = $user['nombre_completo'];
                    $_SESSION['usuario_rol'] = $user['rol'];
                    $_SESSION['usuario_id_rol'] = $user['id_rol'];
                    $_SESSION['debe_cambiar_password'] = isset($user['debe_cambiar_password']) ? $user['debe_cambiar_password'] : 0;

                    // Verificar si debe cambiar contraseña
                    if (isset($user['debe_cambiar_password']) && $user['debe_cambiar_password'] == 1) {
                        header("Location: index.php?action=cambiar_password");
                        exit();
                    }

                    // Resetear rate limiting tras login exitoso
                    Security::resetRateLimit();

                    // Auditoría: login exitoso
                    $this->auditoria->log(
                        $user['documento'],
                        'LOGIN',
                        'usuarios',
                        $user['documento'],
                        null,
                        ['rol' => $user['rol'], 'id_rol' => $user['id_rol']],
                        'Inicio de sesión exitoso'
                    );

                    // Redirigir según el rol
                    if ($user['id_rol'] == 4) {
                        header("Location: index.php?action=portal_propietario");
                    } elseif ($user['id_rol'] == 1) {
                        header("Location: index.php?action=admin_panel");
                    } elseif ($user['id_rol'] == 2) {
                        header("Location: index.php?action=vet_area");
                    } elseif ($user['id_rol'] == 3) {
                        header("Location: index.php?action=reception_dashboard");
                    } else {
                        header("Location: index.php?action=dashboard");
                    }
                    exit();
                } else {
                    // Auditoría: login fallido (cuenta inactiva)
                    $this->auditoria->log(
                        $user['documento'],
                        'LOGIN_FAIL',
                        'usuarios',
                        $user['documento'],
                        null,
                        null,
                        'Intento de login fallido: cuenta inactiva'
                    );
                    Security::recordFailedLogin();
                    $this->redirectWithError("Su cuenta está inactiva. Contacte al administrador.");
                }
            } else {
                Security::recordFailedLogin();
                // Auditoría: login fallido (credenciales incorrectas)
                $this->auditoria->log(
                    $documento,
                    'LOGIN_FAIL',
                    'usuarios',
                    $documento,
                    null,
                    null,
                    'Intento de login fallido: credenciales incorrectas'
                );
                $this->redirectWithError("Documento o contraseña incorrectos.");
            }
        } else {
            // Si es GET, mostramos la vista
            require_once '../views/auth/login.php';
        }
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
            $user = $this->usuarioModel->getUserDetailsByEmail($email);

            if (!$user || (int)($user['estado'] ?? 0) !== 1) {
                $this->jsonResponse(true, $mensajeGenerico);
            }

            $this->passwordResetModel->deleteExpiredTokens();
            $this->passwordResetModel->invalidateTokensForEmail($email);

            $tokenPlano = bin2hex(random_bytes(32));
            $tokenHash = password_hash($tokenPlano, PASSWORD_DEFAULT);
            $expiresAt = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
            $tokenId = $this->passwordResetModel->createToken($user['documento'], $email, $tokenHash, $expiresAt);

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

            if (strlen($password) < 8) {
                $this->jsonResponse(false, 'La contraseña debe tener al menos 8 caracteres.');
            }

            $reset = $this->passwordResetModel->findById($tokenId);
            if (!$reset || (int)$reset['used'] === 1 || !password_verify($tokenPlano, $reset['token_hash'])) {
                $this->jsonResponse(false, 'El enlace para restablecer la contraseña no es válido o ya fue utilizado.');
            }

            $expira = new DateTime($reset['expires_at']);
            if ($expira < new DateTime()) {
                $this->jsonResponse(false, 'El enlace ha expirado. Solicita uno nuevo.');
            }

            $documento = $reset['usuario_documento'] ?? null;
            $user = $documento ? $this->usuarioModel->getUserByDocumento($documento) : null;
            if (!$user) {
                // Revalidar por email en caso de que no se haya almacenado el documento
                $userDetails = $this->usuarioModel->getUserDetailsByEmail($reset['email']);
                if (!$userDetails) {
                    $this->jsonResponse(false, 'No encontramos la cuenta asociada a este enlace.');
                }
                $documento = $userDetails['documento'];
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            if (!$this->usuarioModel->updatePassword($documento, $passwordHash)) {
                $this->jsonResponse(false, 'No fue posible actualizar la contraseña. Inténtalo nuevamente.');
            }

            $this->usuarioModel->updateDebeCambiarPassword($documento, 0);
            $this->passwordResetModel->markTokenUsed($tokenId);

            $this->jsonResponse(true, 'Tu contraseña se actualizó correctamente. Ya puedes iniciar sesión.');
        } catch (Exception $e) {
            error_log('Error procesando reset de contraseña: ' . $e->getMessage());
            $this->jsonResponse(false, 'Ocurrió un error inesperado. Inténtalo de nuevo más tarde.');
        }
    }

    public function logout() {
        // Auditoría: logout
        $usuarioDoc = $_SESSION['usuario_doc'] ?? null;
        if ($usuarioDoc) {
            $this->auditoria->log(
                $usuarioDoc,
                'LOGOUT',
                'usuarios',
                $usuarioDoc,
                null,
                null,
                'Cierre de sesión'
            );
        }
        session_destroy();
        header("Location: index.php");
        exit();
    }

    public function cambiarPasswordAjax() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            try {
                $nuevaPassword = $_POST['nueva_password'] ?? '';
                $documento = $_SESSION['usuario_doc'] ?? '';

                if (empty($nuevaPassword) || strlen($nuevaPassword) < 6) {
                    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres']);
                    exit;
                }

                $passwordHash = password_hash($nuevaPassword, PASSWORD_DEFAULT);
                
                if ($this->usuarioModel->updatePassword($documento, $passwordHash)) {
                    // Actualizar debe_cambiar_password a 0
                    $this->usuarioModel->updateDebeCambiarPassword($documento, 0);
                    
                    echo json_encode(['success' => true, 'message' => 'Contraseña actualizada exitosamente']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al actualizar la contraseña']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
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
                $_SESSION['error_register'] = "Token de seguridad inválido. Por favor intenta de nuevo.";
                header("Location: index.php?action=login");
                exit();
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
                $_SESSION['error_register'] = "Todos los campos son obligatorios.";
                header("Location: index.php?action=login");
                exit();
            }

            if ($password !== $confirm_password) {
                $_SESSION['error_register'] = "Las contraseñas no coinciden.";
                header("Location: index.php?action=login");
                exit();
            }

            if (strlen($password) < 6) {
                $_SESSION['error_register'] = "La contraseña debe tener al menos 6 caracteres.";
                header("Location: index.php?action=login");
                exit();
            }

            // Verificar si el documento ya está registrado
            if ($this->usuarioModel->getById($documento)) {
                $_SESSION['error_register'] = "El documento ya está registrado en el sistema.";
                header("Location: index.php?action=login");
                exit();
            }

            // Verificar si el correo ya está registrado
            if ($this->usuarioModel->getUserByEmail($email)) {
                $_SESSION['error_register'] = "El correo electrónico ya está registrado.";
                header("Location: index.php?action=login");
                exit();
            }

            // Registrar usuario con rol 4 (propietario)
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $data = [
                'documento' => $documento,
                'tipo_documento' => $tipo_documento,
                'nombre_completo' => $nombre_completo,
                'telefono' => $telefono,
                'email' => $email,
                'password' => $passwordHash,
                'id_rol' => 4, // Propietario
                'estado' => 1,
                'debe_cambiar_password' => 0
            ];

            if ($this->usuarioModel->create($data)) {
                // Login automático del usuario registrado
                $user = $this->usuarioModel->getUserByDocumento($documento);
                $_SESSION['usuario_doc'] = $user['documento'];
                $_SESSION['usuario_nombre'] = $user['nombre_completo'];
                $_SESSION['usuario_rol'] = $user['rol'];
                $_SESSION['usuario_id_rol'] = $user['id_rol'];
                $_SESSION['debe_cambiar_password'] = 0;

                // Auditoría: auto-registro
                $this->auditoria->log(
                    $documento,
                    'LOGIN',
                    'usuarios',
                    $documento,
                    null,
                    ['rol' => $user['rol'], 'id_rol' => $user['id_rol']],
                    'Auto-registro e inicio de sesión automático'
                );

                header("Location: index.php?action=portal_propietario");
                exit();
            } else {
                $_SESSION['error_register'] = "Ocurrió un error al procesar el registro. Intenta más tarde.";
                header("Location: index.php?action=login");
                exit();
            }
        }
    }

    private function redirectWithError($message) {
        $_SESSION['error_login'] = $message;
        header("Location: index.php");
        exit();
    }

    private function buildResetLink(int $tokenId, string $tokenPlano): string
    {
        // Cargar APP_URL del archivo .env si está configurado
        $appUrl = null;
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $env = parse_ini_file($envFile);
            $appUrl = $env['APP_URL'] ?? null;
        }

        $query = http_build_query([
            'action' => 'reset_password',
            'id' => $tokenId,
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
                                    src="https://zooki.secarvajal.com/img/icon_blue.png"
                                    style="display:block;outline:none;border:none;text-decoration:none;height:auto"
                                    width="36" />
                                </td>
                                <td style="vertical-align:middle">
                                  <img
                                    alt="Zooki logotipo"
                                    src="https://zooki.secarvajal.com/img/logotipo.png"
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
            $documento = trim($_POST['documento'] ?? '');
            if (empty($documento)) {
                $this->jsonResponse(false, "Documento vacío.");
            }
            $exists = $this->usuarioModel->getById($documento);
            $this->jsonResponse(true, "Verificado", ['exists' => $exists ? true : false]);
        }
    }

    public function checkEmailAjax()
    {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $email = trim(strtolower($_POST['email'] ?? ''));
            if (empty($email)) {
                $this->jsonResponse(false, "Email vacío.");
            }
            $exists = $this->usuarioModel->getUserByEmail($email);
            $this->jsonResponse(true, "Verificado", ['exists' => $exists ? true : false]);
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

            // Obtener perfil del usuario dependiendo del tipo de token
            if (!empty($accessToken)) {
                $url = 'https://www.googleapis.com/oauth2/v3/userinfo?access_token=' . $accessToken;
            } else {
                $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . $credential;
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                $this->jsonResponse(false, "No se pudo validar el token con Google.");
                return;
            }

            $payload = json_decode($response, true);
            
            // Validar que el token tenga un email
            if (!isset($payload['email'])) {
                $this->jsonResponse(false, "No se pudo obtener el email de Google.");
                return;
            }

            $email = trim(strtolower($payload['email']));
            $nombre_completo = $payload['name'] ?? '';

            // Buscar si el usuario ya existe en nuestra base de datos
            $user = $this->usuarioModel->getUserDetailsByEmail($email);

            if ($user) {
                // Usuario existe, verificamos su estado
                if ($user['estado'] != 1) {
                    $this->jsonResponse(false, "Tu cuenta está inactiva. Contacta al administrador.");
                    return;
                }

                // Iniciar sesión
                $_SESSION['usuario_doc'] = $user['documento'];
                $_SESSION['usuario_nombre'] = $user['nombre_completo'];
                $_SESSION['usuario_id_rol'] = $user['id_rol'];

                $this->auditoria->log($user['documento'], 'Login via Google', 'Usuario', $user['documento'], null);

                $this->jsonResponse(true, "Login exitoso", ['action' => 'login', 'redirect' => 'index.php?action=dashboard']);
            } else {
                // Usuario NO existe, requerimos completar su registro (Cédula y Teléfono)
                // Usamos $_SESSION temporal para guardar su info confirmada por Google
                $_SESSION['google_pending_register'] = [
                    'email' => $email,
                    'nombre_completo' => $nombre_completo,
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

            // Verificar si el documento ya existe
            if ($this->usuarioModel->getById($documento)) {
                $this->jsonResponse(false, "Este documento ya se encuentra registrado en el sistema.");
                return;
            }

            // Crear el usuario con una contraseña dummy inalcanzable ya que usa Google para login
            // Generamos un hash aleatorio complejo imposible de adivinar
            $dummyPassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

            $data = [
                'documento' => $documento,
                'tipo_documento' => $tipo_documento,
                'nombre_completo' => $pendingData['nombre_completo'],
                'telefono' => $telefono,
                'email' => $pendingData['email'],
                'password' => $dummyPassword,
                'id_rol' => 4, // Cliente
                'estado' => 1,
                'debe_cambiar_password' => 0
            ];

            if ($this->usuarioModel->create($data)) {
                // Eliminar sesión temporal de registro
                unset($_SESSION['google_pending_register']);

                // Iniciar sesión
                $_SESSION['usuario_doc'] = $documento;
                $_SESSION['usuario_nombre'] = $data['nombre_completo'];
                $_SESSION['usuario_id_rol'] = 4;

                $this->auditoria->log($documento, 'Registro via Google', 'Usuario', $documento, null);

                $this->jsonResponse(true, "Registro exitoso", ['action' => 'login', 'redirect' => 'index.php?action=dashboard']);
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
