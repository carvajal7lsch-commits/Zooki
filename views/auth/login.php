<?php
require_once __DIR__ . '/../../helpers/ValidadorCuenta.php';
require_once __DIR__ . '/../../helpers/Turnstile.php';
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
// Un error del registro sin JavaScript vuelve con la cara del registro abierta.
$errorRegistro = $_SESSION['error_register'] ?? null;
unset($_SESSION['error_register']);
$abrirRegistro = ($abrirRegistro ?? false) || $errorRegistro !== null;
// M0.1 (RE-T.13.5): el widget del login solo sale cuando el servidor lo exigió.
$captchaLogin = $captchaLogin ?? false;
$identificadorPrevio = $identificadorPrevio ?? '';
$turnstileClave = Turnstile::configuracion('TURNSTILE_SITE_KEY');
$e = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php require __DIR__ . '/../partials/cabecera_acceso.php'; ?>
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
            <div class="flip-container" data-acceso-giro-tarjeta>
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
                                        <input type="text" id="identificador" name="identificador" value="<?= $e($identificadorPrevio) ?>" placeholder="Tu número de documento o tu correo" required autocomplete="username" maxlength="255">
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label for="password">Contraseña</label>
                                    <div class="input-wrapper">
                                        <input <?= ValidadorCuenta::atributosHtml('password') ?> type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
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
                                <?php if ($turnstileClave !== ''): ?>
                                    <?php // RN-G15: sale tras 5 fallos sobre el mismo documento o correo, exista o no la cuenta. ?>
                                    <div class="acceso-turnstile" data-turnstile-formulario data-turnstile-apariencia="always" data-sitekey="<?= $e($turnstileClave) ?>" <?= $captchaLogin ? '' : 'hidden' ?>></div>
                                <?php endif; ?>
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
                                <p>¿No tienes una cuenta? <a href="index.php?action=register" data-crear-cuenta-abrir>Crear cuenta</a></p>
                            </div>
                        </div>
                    </div>
                    <!-- CARA TRASERA: REGISTRO DEL PROPIETARIO (M0.1: mismo marco que el registro de clínica) -->
                    <div class="back">
                        <div class="form-wrapper registro-marco">
                            <div class="form-header">
                                <button type="button" class="btn-back-top" id="btnBackToLoginTop" title="Volver al inicio de sesión" aria-label="Volver al inicio de sesión">
                                    <i class="ri-arrow-left-line" aria-hidden="true"></i>
                                </button>
                                <h2>Crea tu cuenta</h2>
                            </div>
                            <p class="form-subtitle">Regístrate para agendar citas y revisar el historial de tus mascotas.</p>
                            <form id="registerForm" data-cuenta-vincular="correo" action="index.php?action=process_register" method="POST" data-validacion-cuenta data-registro data-registro-espera="registroEspera">
                                <?php Csrf::field('register'); ?>
                                <div class="registro-resumen" data-registro-resumen role="alert" <?= $errorRegistro === null ? 'hidden' : '' ?>>
                                    <?php if ($errorRegistro !== null): ?><p><?= $e($errorRegistro) ?></p><?php endif; ?>
                                </div>
                                <div class="registro-grid">
                                    <?php // HU-5.8 (RN-109): el registro siempre es en una clínica que el propietario elige. ?>
                                    <div class="input-group registro-ancho">
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
                                        <label for="tipo_documento">Tipo de documento</label>
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
                                        <label for="documento_reg">Número de documento</label>
                                        <div class="input-wrapper">
                                            <input <?= ValidadorCuenta::atributosHtml('documento') ?> type="text" id="documento_reg" name="documento" placeholder="Ej. 1075..." required autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="input-group registro-ancho">
                                        <label for="nombre_completo">Nombre completo</label>
                                        <div class="input-wrapper">
                                            <input <?= ValidadorCuenta::atributosHtml('nombre_completo') ?> type="text" id="nombre_completo" name="nombre_completo" placeholder="Nombres y apellidos" required autocomplete="name">
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
                                            <input <?= ValidadorCuenta::atributosHtml('email') ?> type="email" id="email_reg" name="email" placeholder="correo@ejemplo.com" required autocomplete="email">
                                        </div>
                                    </div>
                                    <div class="input-group">
                                        <label for="password_reg">Contraseña</label>
                                        <div class="input-wrapper">
                                            <input <?= ValidadorCuenta::atributosHtml('password') ?> type="password" id="password_reg" name="password" placeholder="••••••••" required minlength="8" autocomplete="new-password" aria-describedby="registroClaveAyuda">
                                            <button type="button" class="toggle-password" id="togglePasswordReg" tabindex="-1" aria-label="Mostrar u ocultar la contraseña" aria-pressed="false">
                                                <i class="ri-eye-off-line"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="input-group">
                                        <label for="confirm_password">Confirma la contraseña</label>
                                        <div class="input-wrapper">
                                            <input <?= ValidadorCuenta::atributosHtml('password') ?> type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required minlength="8" autocomplete="new-password">
                                            <button type="button" class="toggle-password" id="toggleConfirmPasswordReg" tabindex="-1" aria-label="Mostrar u ocultar la contraseña" aria-pressed="false">
                                                <i class="ri-eye-off-line"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <p class="registro-nota registro-ancho" id="registroClaveAyuda">Mínimo 8 caracteres, con mayúscula, minúscula y número.</p>
                                </div>
                                <?php $consentimientoId = 'aceptaDatos'; require __DIR__ . '/../partials/consentimiento_datos.php'; ?>
                                <?php if ($turnstileClave !== ''): ?>
                                    <div class="acceso-turnstile" data-turnstile-formulario data-sitekey="<?= $e($turnstileClave) ?>"></div>
                                <?php endif; ?>
                                <button type="submit" class="btn-primary">
                                    <span>Crear mi cuenta</span>
                                </button>
                                <div class="form-footer form-footer--registro">
                                    <p>¿Ya tienes una cuenta? <a href="#" id="showLoginBtn">Iniciar sesión</a></p>
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
                            <?php
                            $esperaId = 'registroEspera';
                            $esperaTexto = 'Si la solicitud puede completarse, el titular recibirá el enlace de confirmación. Ábrelo y después inicia sesión para continuar.';
                            require __DIR__ . '/../partials/registro_espera.php';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="login-copyright">
                <p>© Zooki App, 2026</p>
            </div>
        </div>
        <?php require __DIR__ . '/../partials/acceso_hero.php'; ?>
    </div>
    <!-- M0.1: un solo «Crear cuenta» con dos opciones -->
    <div class="modal-backdrop" id="crearCuentaModal" hidden>
        <div class="modal-card crear-cuenta-card" role="dialog" aria-modal="true" aria-labelledby="crearCuentaTitulo">
            <button type="button" class="modal-close" data-crear-cuenta-cerrar aria-label="Cerrar">
                <i class="ri-close-line" aria-hidden="true"></i>
            </button>
            <h3 id="crearCuentaTitulo">Crear cuenta</h3>
            <p class="modal-subtitle">¿Cómo vas a usar Zooki?</p>
            <div class="crear-cuenta-opciones">
                <button type="button" class="crear-cuenta-opcion" data-crear-cuenta="propietario">
                    <span class="crear-cuenta-icono"><i class="ri-heart-3-line" aria-hidden="true"></i></span>
                    <span class="crear-cuenta-texto">
                        <strong>Soy propietario</strong>
                        <span>Agenda citas y consulta la historia de tus mascotas.</span>
                    </span>
                    <i class="ri-arrow-right-s-line crear-cuenta-flecha" aria-hidden="true"></i>
                </button>
                <a class="crear-cuenta-opcion" href="index.php?action=registro_clinica" data-acceso-giro-ir>
                    <span class="crear-cuenta-icono"><i class="ri-hospital-line" aria-hidden="true"></i></span>
                    <span class="crear-cuenta-texto">
                        <strong>Registrar mi clínica</strong>
                        <span>Administra tu veterinaria en Zooki con el plan gratuito.</span>
                    </span>
                    <i class="ri-arrow-right-s-line crear-cuenta-flecha" aria-hidden="true"></i>
                </a>
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
                    <input <?= ValidadorCuenta::atributosHtml('email') ?> type="email" id="resetEmail" name="email" placeholder="tu@correo.com" required>
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
                <?php $consentimientoId = 'googleAceptaDatos'; require __DIR__ . '/../partials/consentimiento_datos.php'; ?>
                <button type="submit" class="btn-primary" id="completeGoogleBtn">
                    <span>Crear mi cuenta</span>
                    <i class="ri-check-line"></i>
                </button>
            </form>
        </div>
    </div>
    <!-- JS externo — ZOOKI_REGLAS: cero JS en línea -->
    <script src="js/turnstile-formularios.js?v=<?= $versionAcceso ?>"></script>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=zookiTurnstileListo&render=explicit" async defer></script>
    <script src="js/password-policy.js?v=<?= $versionAcceso ?>"></script>
    <script src="js/login.js?v=<?= $versionAcceso ?>"></script>
    <script src="js/crear-cuenta.js?v=<?= $versionAcceso ?>"></script>
    <script src="js/acceso-giro.js?v=<?= $versionAcceso ?>"></script>
    <script src="js/registro-cuenta.js?v=<?= $versionAcceso ?>"></script>
    <script src="js/register.js?v=<?= $versionAcceso ?>"></script>

    <!-- Google Identity Services -->
    <script src="https://accounts.google.com/gsi/client?onload=initGoogleAuth" async defer></script>
<?php require __DIR__ . "/../partials/validacion_cuenta.php"; ?>
<script src="js/avisos.js?v=<?= $versionAcceso ?>"></script>
</body>
</html>
