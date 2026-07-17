<?php
// Asegurarnos de que la sesión esté iniciada para mostrar errores
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Si ya hay una sesión activa, lo mandamos al dashboard
if(isset($_SESSION['usuario_doc'])) {
    header("Location: index.php?action=dashboard");
    exit();
}
// Configuración básica de página
$pageTitle = "Iniciar Sesión - Zooki";
// Extraer GOOGLE_CLIENT_ID desde .env
$envFile = __DIR__ . '/../../.env';
$googleClientId = '';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) == 2 && trim($parts[0]) === 'GOOGLE_CLIENT_ID') {
            $googleClientId = trim($parts[1]);
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Configuración para el JS -->
    <script>
        window.ZookiConfig = {
            googleClientId: "<?php echo htmlspecialchars($googleClientId); ?>"
        };
    </script>
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
<body class="login-page">
    <div class="login-container">
        <!-- Lado del Formulario (IZQUIERDO — como en la referencia Vet da Cidade) -->
        <div class="login-form-area animate__animated animate__fadeIn">
            <!-- Marca (Logo) -->
            <div class="login-brand">
                <img src="img/icon_blue.png" alt="Zooki Logo" class="brand-logo" draggable="false">
                <span class="brand-text">Zooki</span>
            </div>
            <div class="flip-container">
                <div class="flipper" id="authFlipper">
                    <!-- CARA FRONTAL: LOGIN -->
                    <div class="front">
                        <div class="form-wrapper animate__animated animate__fadeIn">
                            <h2>Iniciar sesión</h2>
                            <p class="form-subtitle">¡Bienvenido de vuelta! Accede a tu cuenta para continuar.</p>
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
                                    <label for="documento">Documento de Identidad</label>
                                    <div class="input-wrapper">
                                        <input type="text" id="documento" name="documento" placeholder="Ingrese su número de documento" required autocomplete="username" pattern="[0-9]+" maxlength="15">
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label for="password">Contraseña</label>
                                    <div class="input-wrapper">
                                        <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password" maxlength="50">
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
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 8px;">
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
                                            <input type="text" id="telefono" name="telefono" placeholder="Ej: 300..." required maxlength="15" autocomplete="off">
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
                                            <input type="password" id="password_reg" name="password" placeholder="••••••••" required maxlength="50" autocomplete="new-password">
                                        </div>
                                        <div class="password-meter-container">
                                            <div class="password-meter" id="passwordMeter"></div>
                                        </div>
                                        <span class="validation-msg" id="passwordValidationMsg">Mínimo 6 caracteres (letras y números)</span>
                                    </div>
                                    <div class="input-group">
                                        <label for="confirm_password">Confirmar</label>
                                        <div class="input-wrapper">
                                            <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required maxlength="50" autocomplete="new-password">
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn-primary">
                                    <span>Registrarse</span>
                                </button>
                                
                                <div class="form-footer" style="margin-top: 1rem; margin-bottom: 0.5rem; text-align: center;">
                                    <p style="font-size: 0.85rem; margin: 0;">¿Ya tienes una cuenta? <a href="#" id="showLoginBtn">Iniciar Sesión</a></p>
                                </div>
                                
                                <div class="divider">
                                    <span>O regístrate con</span>
                                </div>
                                <button type="button" class="btn-secondary" id="btnGoogleRegister">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 8px;">
                                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.16v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.16C1.43 8.55 1 10.22 1 12s.43 3.45 1.16 4.93l3.68-2.84z" fill="#FBBC05"/>
                                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.16 7.07l3.68 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                                    </svg>
                                    Registrarse con Google
                                </button>
                            </form>
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
    <!-- Modal: Completar Registro Google -->
    <div class="modal-backdrop" id="completeGoogleRegisterModal" hidden>
        <div class="modal-card">
            <button class="modal-close" id="closeGoogleModal" aria-label="Cerrar">
                <i class="ri-close-line"></i>
            </button>
            <div class="modal-illustration">
                <i class="ri-google-fill" style="color: #4285F4;"></i>
            </div>
            <h3>¡Ya casi terminamos!</h3>
            <p class="modal-subtitle">Tu correo <strong id="googleUserEmail"></strong> fue verificado. Solo necesitamos unos datos más para Zooki.</p>
            <form id="completeGoogleForm">
                <div class="input-group">
                    <label for="google_tipo_documento">Tipo Doc.</label>
                    <div class="input-wrapper">
                        <select id="google_tipo_documento" name="tipo_documento" required>
                            <option value="CC">Cédula</option>
                            <option value="TI">T. Identidad</option>
                            <option value="CE">C. Extranjería</option>
                            <option value="PP">Pasaporte</option>
                        </select>
                    </div>
                </div>
                <div class="input-group">
                    <label for="google_documento">Documento</label>
                    <div class="input-wrapper">
                        <i class="ri-profile-line"></i>
                        <input type="text" id="google_documento" name="documento" placeholder="Ej. 1075..." required maxlength="15">
                    </div>
                </div>
                <div class="input-group">
                    <label for="google_telefono">Teléfono</label>
                    <div class="input-wrapper">
                        <i class="ri-phone-line"></i>
                        <input type="text" id="google_telefono" name="telefono" placeholder="Ej. 300..." required maxlength="15">
                    </div>
                </div>
                <button type="submit" class="btn-primary" id="completeGoogleBtn">
                    <span>Finalizar Registro</span>
                    <i class="ri-check-line"></i>
                </button>
            </form>
        </div>
    </div>
    <!-- JS externo — ZOOKI_REGLAS: cero JS en línea -->
    <script src="js/login.js?v=<?php echo time(); ?>"></script>
    <script src="js/register.js?v=<?php echo time(); ?>"></script>
    
    <!-- Google Identity Services -->
    <script src="https://accounts.google.com/gsi/client?onload=initGoogleAuth" async defer></script>
</body>
</html>
