<?php
// Asegurarnos de que la sesión esté iniciada para mostrar errores
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Si ya hay una sesión activa, lo mandamos al dashboard
if(isset($_SESSION['id_usuario'])) {
    header("Location: index.php?action=dashboard");
    exit();
}
// Configuración básica de página
$pageTitle = "Iniciar Sesión - Zooki";
// Extraer GOOGLE_CLIENT_ID desde .env.
// Se usa parse_ini_file (igual que Database.php) en lugar de partir las
// líneas a mano: el parser manual solo hacía trim de espacios y dejaba las
// comillas dentro del valor cuando el .env venía escrito como
// GOOGLE_CLIENT_ID="...", que es como lo genera Dokploy. Ese valor llegaba
// con comillas al frontend y Google rechazaba el client_id
// (error flowName=GeneralOAuthFlow).
$envFile = __DIR__ . '/../../.env';
$googleClientId = '';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile) ?: [];
    $googleClientId = trim($env['GOOGLE_CLIENT_ID'] ?? '');
}
require_once __DIR__ . '/../../helpers/ValidadorTelefono.php';
require_once __DIR__ . '/../../helpers/Csrf.php';
// HU-5.8: AuthController::mostrarAcceso() entrega las clínicas activas y la
// elegida por enlace; si se abre la vista sola, el registro queda sin lista.
$clinicasRegistro = $clinicasRegistro ?? [];
$clinicaElegida = $clinicaElegida ?? 0;
$abrirRegistro = $abrirRegistro ?? false;
$e = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="icon" type="image/png" href="img/icon_blue.png">
    <!-- Fuentes y estilos -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <!-- El CSS se enlaza desde la carpeta public/css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="css/styles.css?v=<?php echo time(); ?>">
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<?php // D1: la configuración del JS viaja en data-*, sin JS en línea. ?>
<body class="login-page" data-google-client-id="<?= $e($googleClientId) ?>">
    <div class="login-container">
        <!-- Lado del Formulario (IZQUIERDO — como en la referencia Vet da Cidade) -->
        <div class="login-form-area animate__animated animate__fadeIn">
            <!-- Marca y regreso a la landing -->
            <div class="login-topbar">
                <a href="index.php" class="login-brand" aria-label="Zooki, ir al inicio">
                    <img src="img/icon_blue.png" alt="" class="brand-logo" draggable="false">
                    <span class="brand-text">Zooki</span>
                </a>
                <a href="index.php" class="login-back">
                    <i class="ri-arrow-left-line" aria-hidden="true"></i>
                    <span>Volver al inicio</span>
                </a>
            </div>
            <div class="flip-container">
                <div class="flipper" id="authFlipper" data-abrir-registro="<?= $abrirRegistro ? '1' : '0' ?>">
                    <!-- CARA FRONTAL: LOGIN -->
                    <div class="front">
                        <div class="form-wrapper animate__animated animate__fadeIn">
                            <h2>Iniciar sesión</h2>
                            <p class="form-subtitle">¡Bienvenido de vuelta! Accede a tu cuenta para continuar.</p>
                            <!-- Confirmacion de correo / registro (HU-36) -->
                            <?php if(isset($_SESSION['success_register'])): ?>
                                <div class="alert-success">
                                    <i class="ri-checkbox-circle-line"></i>
                                    <span><?php echo htmlspecialchars($_SESSION['success_register'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success_register']); ?></span>
                                </div>
                            <?php endif; ?>
                            <!-- Mostrar errores de login si existen -->
                            <?php if(isset($_SESSION['error_login'])): ?>
                                <div class="alert-error animate__animated animate__shakeX">
                                    <i class="ri-error-warning-line"></i>
                                    <span><?php echo $_SESSION['error_login']; unset($_SESSION['error_login']); ?></span>
                                </div>
                            <?php endif; ?>
                            <form id="loginForm" action="index.php?action=login" method="POST">
                                <?php require_once __DIR__ . '/../../helpers/Csrf.php'; Csrf::field('login'); ?>
                                
                                <div class="input-group">
                                    <label for="identificador">Documento o correo</label>
                                    <div class="input-wrapper">
                                        <input type="text" id="identificador" name="identificador" placeholder="Tu número de documento o tu correo" required autocomplete="username" maxlength="255">
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label for="password">Contraseña</label>
                                    <div class="input-wrapper">
                                        <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password" maxlength="72">
                                        <button type="button" class="toggle-password" id="togglePassword" tabindex="-1">
                                            <i class="ri-eye-off-line"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="form-options">
                                    <label class="remember-me">
                                        <input type="checkbox" name="remember" id="rememberMe">
                                        <span>Recuérdame</span>
                                    </label>
                                    <a href="#" class="forgot-password" id="forgotPasswordBtn">¿Olvidaste tu contraseña?</a>
                                </div>
                                <button type="submit" class="btn-primary">
                                    <span>Entrar</span>
                                </button>
                                
                                <div class="divider">
                                    <span>O continua con</span>
                                </div>
                                <button type="button" class="btn-secondary" id="btnGoogleLogin">
                                    <svg class="icono-google" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.16v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.16C1.43 8.55 1 10.22 1 12s.43 3.45 1.16 4.93l3.68-2.84z" fill="#FBBC05"/>
                                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.16 7.07l3.68 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                                    </svg>
                                    Entrar con Google
                                </button>
                            </form>
                            <div class="form-footer">
                                <p>¿No tienes una cuenta? <a href="#" id="showRegisterBtn">Regístrate</a></p>
                            </div>
                        </div>
                    </div>
                    <!-- CARA TRASERA: REGISTRO -->
                    <div class="back">
                        <div class="form-wrapper">
                            <div class="form-header">
                                <button type="button" class="btn-back-top" id="btnBackToLoginTop" title="Volver al inicio">
                                    <i class="ri-arrow-left-line"></i>
                                </button>
                                <h2>Crea tu cuenta</h2>
                            </div>
                            <p class="form-subtitle">Regístrate para agendar citas y revisar el historial de tus mascotas.</p>
                            <?php if(isset($_SESSION['error_register'])): ?>
                                <div class="alert-error animate__animated animate__shakeX">
                                    <i class="ri-error-warning-line"></i>
                                    <span><?php echo $_SESSION['error_register']; unset($_SESSION['error_register']); ?></span>
                                </div>
                            <?php endif; ?>
                            <form id="registerForm" action="index.php?action=process_register" method="POST">
                                <?php require_once __DIR__ . '/../../helpers/Csrf.php'; Csrf::field('register'); ?>
                                
                                <div class="auth-grid">
                                    <?php // HU-5.8 (RN-109): el registro siempre es en una clínica que el propietario elige. ?>
                                    <div class="input-group full-width">
                                        <label for="id_clinica_reg">Clínica</label>
                                        <div class="input-wrapper">
                                            <select id="id_clinica_reg" name="id_clinica" required>
                                                <option value="">Elige la clínica donde atiendes a tu mascota</option>
                                                <?php foreach ($clinicasRegistro as $clinica): ?>
                                                    <option value="<?= (int) $clinica['id_clinica'] ?>" <?= (int) $clinica['id_clinica'] === $clinicaElegida ? 'selected' : '' ?>><?= $e($clinica['nombre']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="input-group">
                                        <label for="tipo_documento">Tipo Doc.</label>
                                        <div class="input-wrapper">
                                            <select id="tipo_documento" name="tipo_documento" required autocomplete="off">
                                                <option value="CC">Cédula</option>
                                                <option value="TI">T. Identidad</option>
                                                <option value="CE">C. Extranjería</option>
                                                <option value="PP">Pasaporte</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="input-group">
                                        <label for="documento_reg">Documento</label>
                                        <div class="input-wrapper">
                                            <input type="text" id="documento_reg" name="documento" placeholder="Ej. 1075..." required maxlength="15" autocomplete="off">
                                        </div>
                                        <span class="validation-msg" id="docValidationMsg"></span>
                                    </div>
                                    <div class="input-group full-width">
                                        <label for="nombre_completo">Nombre Completo</label>
                                        <div class="input-wrapper">
                                            <input type="text" id="nombre_completo" name="nombre_completo" placeholder="Nombres y Apellidos completos" required maxlength="100" autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="input-group">
                                        <label for="telefono">Teléfono</label>
                                        <div class="input-wrapper">
                                            <input type="tel" id="telefono" name="telefono" placeholder="Ej: 300 123 4567" required minlength="<?= ValidadorTelefono::MIN ?>" <?= ValidadorTelefono::atributosHtml() ?> autocomplete="tel">
                                        </div>
                                    </div>
                                    <div class="input-group">
                                        <label for="email_reg">Correo</label>
                                        <div class="input-wrapper">
                                            <input type="email" id="email_reg" name="email" placeholder="correo@ejemplo.com" required maxlength="100" autocomplete="off">
                                        </div>
                                        <span class="validation-msg" id="emailValidationMsg"></span>
                                    </div>
                                    <div class="input-group">
                                        <label for="password_reg">Contraseña</label>
                                        <div class="input-wrapper">
                                            <input type="password" id="password_reg" name="password" placeholder="••••••••" required minlength="8" maxlength="72" autocomplete="new-password">
                                            <button type="button" class="toggle-password" id="togglePasswordReg" tabindex="-1" aria-label="Mostrar u ocultar la contraseña" aria-pressed="false">
                                                <i class="ri-eye-off-line"></i>
                                            </button>
                                        </div>
                                        <div class="password-meter-container">
                                            <div class="password-meter" id="passwordMeter"></div>
                                        </div>
                                        <span class="validation-msg" id="passwordValidationMsg">Mínimo 8 caracteres, con mayúscula, minúscula y número</span>
                                    </div>
                                    <div class="input-group">
                                        <label for="confirm_password">Confirmar</label>
                                        <div class="input-wrapper">
                                            <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required minlength="8" maxlength="72" autocomplete="new-password">
                                            <button type="button" class="toggle-password" id="toggleConfirmPasswordReg" tabindex="-1" aria-label="Mostrar u ocultar la contraseña" aria-pressed="false">
                                                <i class="ri-eye-off-line"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <label class="consent-check">
                                    <input type="checkbox" name="acepta_datos" id="aceptaDatos" value="1" required>
                                    <span>
                                        Autorizo el tratamiento de mis datos personales conforme a la
                                        <a href="index.php?action=privacidad" target="_blank" rel="noopener">Política de Tratamiento de Datos</a>.
                                    </span>
                                </label>
                                <button type="submit" class="btn-primary">
                                    <span>Registrarse</span>
                                </button>
                                
                                <div class="form-footer form-footer--registro">
                                    <p>¿Ya tienes una cuenta? <a href="#" id="showLoginBtn">Iniciar Sesión</a></p>
                                </div>
                                
                                <div class="divider">
                                    <span>O regístrate con</span>
                                </div>
                                <button type="button" class="btn-secondary" id="btnGoogleRegister">
                                    <svg class="icono-google" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.16v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.16C1.43 8.55 1 10.22 1 12s.43 3.45 1.16 4.93l3.68-2.84z" fill="#FBBC05"/>
                                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.16 7.07l3.68 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                                    </svg>
                                    Registrarse con Google
                                </button>
                            </form>

                            <!-- Pantalla de espera del registro (HU-36). Reemplaza al
                                 formulario sin recargar la pagina: la cuenta ya quedo
                                 creada y solo falta que el usuario abra el enlace del
                                 correo. En cuanto lo haga, el sondeo lo lleva al portal. -->
                            <div class="registro-espera" id="registroEspera" hidden>
                                <div class="registro-espera-icono">
                                    <i class="ri-mail-send-line"></i>
                                    <span class="registro-espera-spinner" aria-hidden="true"></span>
                                </div>
                                <h3>Revisa tu correo</h3>
                                <p class="registro-espera-texto">
                                    Enviamos un enlace de confirmación a
                                    <strong id="registroEsperaEmail"></strong>.
                                    Ábrelo para activar tu cuenta; esta página continúa sola.
                                </p>
                                <p class="registro-espera-estado" id="registroEsperaEstado" role="status" aria-live="polite">
                                    Esperando la confirmación…
                                </p>
                                <p class="registro-espera-ayuda">
                                    Si no lo ves, revisa la carpeta de spam. El enlace vence en 24 horas.
                                </p>
                                <button type="button" class="btn-secondary" id="registroEsperaVolver">
                                    Volver al inicio de sesión
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="login-copyright">
                <p>© Zooki App, 2026</p>
            </div>
        </div>
        <!-- Lado de la Imagen / Bento Grid (DERECHO - con formas originales) -->
        <div class="login-hero animate__animated animate__fadeIn">
            <div class="hero-grid-collage">
                <!-- Fila 1 -->
                <div class="collage-item bg-primary-dark br-tr collage-puppy-wrapper">
                    <img src="img/cachorro-negro.png" alt="Cachorro Negro" class="collage-puppy-img" draggable="false">
                </div>
                <div class="collage-item bg-primary-light br-bl"></div>
                <div class="collage-item bg-medium-blue br-tl collage-cat-wrapper">
                    <img src="img/gato-blanco.png" alt="Gato Blanco" class="collage-cat-img" draggable="false">
                </div>
                <!-- Fila 2 -->
                <div class="collage-item bg-white br-circle">
                    <svg class="cross-icon" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                        <rect x="35" y="10" width="30" height="80" rx="15" fill="#93C5FD"/>
                        <rect x="10" y="35" width="80" height="30" rx="15" fill="#93C5FD"/>
                        <line x1="15" y1="15" x2="85" y2="85" stroke="white" stroke-width="8" />
                    </svg>
                </div>
                <div class="collage-item bg-primary-dark br-pill-left"></div>
                <div class="collage-item bg-white"></div>
                <!-- Fila 3 -->
                <div class="collage-item bg-primary-dark br-pill-bottom"></div>
                <div class="collage-item bg-primary-light br-bl"></div>
                <div class="collage-item bg-primary-light br-tr collage-golden-wrapper">
                    <img src="img/golden-retriever.png" alt="Golden Retriever" class="collage-golden-img" draggable="false">
                </div>
                <!-- Fila 4 -->
                <div class="collage-item bg-medium-blue br-tr collage-gray-cat-wrapper">
                    <img src="img/gato-gris.png" alt="Gato Gris" class="collage-gray-cat-img" draggable="false">
                </div>
                <div class="collage-item bg-white br-circle">
                    <svg class="cross-icon" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                        <rect x="35" y="10" width="30" height="80" rx="15" fill="#93C5FD"/>
                        <rect x="10" y="35" width="80" height="30" rx="15" fill="#93C5FD"/>
                        <line x1="15" y1="15" x2="85" y2="85" stroke="white" stroke-width="8" />
                    </svg>
                </div>
                <div class="collage-item bg-medium-blue br-leaf"></div>
            </div>
        </div>
    </div>
    <!-- Modal: Recuperar contraseña -->
    <div class="modal-backdrop" id="resetPasswordModal" hidden>
        <div class="modal-card">
            <button class="modal-close" id="closeResetModal" aria-label="Cerrar">
                <i class="ri-close-line"></i>
            </button>
            <div class="modal-illustration">
                <i class="ri-mail-send-line"></i>
            </div>
            <h3>Recupera tu acceso</h3>
            <p class="modal-subtitle">Ingresa el correo registrado y enviaremos un enlace para crear una nueva contraseña.</p>
            <form id="resetRequestForm">
                <label for="resetEmail">Correo electrónico</label>
                <div class="input-wrapper">
                    <i class="ri-at-line"></i>
                    <input type="email" id="resetEmail" name="email" placeholder="tu@correo.com" required>
                </div>
                <button type="submit" class="btn-primary" id="resetRequestBtn">
                    <span>Enviar enlace</span>
                    <i class="ri-send-plane-2-line"></i>
                </button>
            </form>
            <div class="modal-hint">
                <i class="ri-shield-check-line"></i>
                <span>Si el correo está en nuestra base, recibirás un mensaje en pocos minutos.</span>
            </div>
        </div>
    </div>
    <!-- RE-T.18.2: con un correo nuevo, antes de crear la cuenta se elige la
         clínica y se acepta la política. Sin aceptación no se guarda nada. -->
    <div class="modal-backdrop" id="completeGoogleRegisterModal" hidden>
        <div class="modal-card">
            <button class="modal-close" id="closeGoogleModal" aria-label="Cerrar">
                <i class="ri-close-line"></i>
            </button>
            <div class="modal-illustration">
                <i class="ri-google-fill icono-google-modal" aria-hidden="true"></i>
            </div>
            <h3>Crea tu cuenta con Google</h3>
            <p class="modal-subtitle">Google confirmó tu correo <strong id="googleUserEmail"></strong>. Elige tu clínica y acepta la política para crear la cuenta; después te pediremos tu documento y tu teléfono.</p>
            <form id="completeGoogleForm">
                <?php Csrf::field('google'); ?>
                <div class="input-group">
                    <label for="google_id_clinica">Clínica</label>
                    <div class="input-wrapper">
                        <select id="google_id_clinica" name="id_clinica" required>
                            <option value="">Elige la clínica</option>
                            <?php foreach ($clinicasRegistro as $clinica): ?>
                                <option value="<?= (int) $clinica['id_clinica'] ?>"><?= $e($clinica['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <label class="consent-check">
                    <input type="checkbox" name="acepta_datos" id="googleAceptaDatos" value="1" required>
                    <span>
                        Autorizo el tratamiento de mis datos personales conforme a la
                        <a href="index.php?action=privacidad" target="_blank" rel="noopener">Política de Tratamiento de Datos</a>.
                    </span>
                </label>
                <button type="submit" class="btn-primary" id="completeGoogleBtn">
                    <span>Crear mi cuenta</span>
                    <i class="ri-check-line"></i>
                </button>
            </form>
        </div>
    </div>
    <!-- JS externo — ZOOKI_REGLAS: cero JS en línea -->
    <script src="js/password-policy.js?v=<?php echo time(); ?>"></script>
    <script src="js/interacciones.js?v=2"></script>
    <script src="js/login.js?v=<?php echo time(); ?>"></script>
    <script src="js/register.js?v=<?php echo time(); ?>"></script>
    
    <!-- Google Identity Services -->
    <script src="https://accounts.google.com/gsi/client?onload=initGoogleAuth" async defer></script>
</body>
</html>
