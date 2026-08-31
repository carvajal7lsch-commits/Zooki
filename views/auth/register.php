<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(isset($_SESSION['usuario_doc'])) {
    header("Location: index.php?action=dashboard");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zooki - Crear Cuenta</title>
    <link rel="icon" type="image/png" href="img/icon_blue.png">
    <!-- Fuentes y estilos -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <!-- El CSS se enlaza desde la carpeta public/css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="css/styles.css?v=<?php echo time(); ?>">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="login-page">
    <div class="login-container">
        <!-- Lado del Formulario (IZQUIERDO — como en la referencia Vet da Cidade) -->
        <div class="login-form-area animate__animated animate__fadeIn">
            <!-- Marca (Logo) -->
            <div class="login-brand">
                <img src="img/logo.png" alt="Zooki Logo" class="brand-logo">
                <span class="brand-text">Zooki</span>
            </div>

            <div class="form-wrapper animate__animated animate__fadeIn">
                <h2>Crea tu cuenta 🐾</h2>
                <p class="form-subtitle">Regístrate para agendar citas y revisar el historial de tus mascotas.</p>

                <?php if(isset($_SESSION['error_register'])): ?>
                    <div class="alert-error animate__animated animate__shakeX">
                        <i class="ri-error-warning-line"></i>
                        <span><?php echo $_SESSION['error_register']; unset($_SESSION['error_register']); ?></span>
                    </div>
                <?php endif; ?>

                <form id="registerForm" action="index.php?action=process_register" method="POST">
                    <?php require_once __DIR__ . '/../../helpers/Csrf.php'; Csrf::field('register'); ?>
                    
                    <div class="form-grid-2-gap">
                        <div class="input-group input-group-no-margin">
                            <label for="tipo_documento">Tipo Documento</label>
                            <div class="input-wrapper">
                                <select id="tipo_documento" name="tipo_documento" required>
                                    <option value="CC">Cédula de Ciudadanía</option>
                                    <option value="TI">Tarjeta de Identidad</option>
                                    <option value="CE">Cédula de Extranjería</option>
                                    <option value="PP">Pasaporte</option>
                                </select>
                            </div>
                        </div>

                        <div class="input-group input-group-no-margin">
                            <label for="documento">Documento</label>
                            <div class="input-wrapper">
                                <input type="text" id="documento" name="documento" placeholder="Ej. 1075..." required maxlength="15">
                            </div>
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="nombre_completo">Nombre Completo</label>
                        <div class="input-wrapper">
                            <input type="text" id="nombre_completo" name="nombre_completo" placeholder="Nombres y Apellidos" required maxlength="100">
                        </div>
                    </div>

                    <div class="form-grid-2-gap">
                        <div class="input-group input-group-no-margin">
                            <label for="telefono">Teléfono</label>
                            <div class="input-wrapper">
                                <input type="text" id="telefono" name="telefono" placeholder="Ej: 300..." required maxlength="15">
                            </div>
                        </div>

                        <div class="input-group input-group-no-margin">
                            <label for="email">Correo Electrónico</label>
                            <div class="input-wrapper">
                                <input type="email" id="email" name="email" placeholder="correo@ejemplo.com" required maxlength="100">
                            </div>
                        </div>
                    </div>

                    <div class="form-grid-2-gap">
                        <div class="input-group input-group-no-margin">
                            <label for="password">Contraseña</label>
                            <div class="input-wrapper">
                                <input type="password" id="password" name="password" placeholder="••••••••" required maxlength="50">
                            </div>
                        </div>

                        <div class="input-group input-group-no-margin">
                            <label for="confirm_password">Confirmar</label>
                            <div class="input-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required maxlength="50">
                            </div>
                        </div>
                    </div>

                    <label class="consent-check">
                        <input type="checkbox" name="acepta_datos" value="1" required>
                        <span>
                            Autorizo el tratamiento de mis datos personales conforme a la
                            <a href="index.php?action=privacidad" target="_blank" rel="noopener">Política de Tratamiento de Datos</a>.
                        </span>
                    </label>
                    <button type="submit" class="btn-primary" style="margin-bottom: 1rem;">
                        <span>Registrarse</span>
                    </button>
                    
                    <button type="button" class="btn-secondary" onclick="Swal.fire('Información', 'Integración con Google próximamente', 'info')">
                        <svg class="google-icon" viewBox="0 0 48 48" width="20px" height="20px" xmlns="http://www.w3.org/2000/svg">
                            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.7 17.74 9.5 24 9.5z"/>
                            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                            <path fill="none" d="M0 0h48v48H0z"/>
                        </svg>
                        <span>Registrarse con Google</span>
                    </button>
                </form>

                <div class="form-footer">
                    <p>¿Ya tienes una cuenta? <a href="index.php?action=login" id="loginBackBtn">Iniciar Sesión</a></p>
                </div>
            </div>

            <div class="login-copyright">
                <p>© Zooki App, 2026</p>
            </div>
        </div>

        <!-- Lado de la Imagen / Branding / Bento Grid (DERECHO) -->
        <div class="login-hero animate__animated animate__fadeIn">
            <div class="hero-grid-collage">
                <!-- Fila 1 -->
                <div class="collage-item bg-primary-dark br-tr">
                    <!-- Imagen placeholder (puppy) -->
                </div>
                <div class="collage-item bg-primary-light br-bl"></div>
                <div class="collage-item bg-primary-light br-tl">
                    <!-- Imagen placeholder (cat) -->
                </div>
                <!-- Fila 2 -->
                <div class="collage-item bg-white br-circle">
                    <svg class="cross-icon" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                        <rect x="35" y="10" width="30" height="80" rx="15" fill="#93C5FD"/>
                        <rect x="10" y="35" width="80" height="30" rx="15" fill="#93C5FD"/>
                    </svg>
                </div>
                <div class="collage-item bg-primary-dark br-pill-left"></div>
                <div class="collage-item bg-transparent"></div>
                <!-- Fila 3 -->
                <div class="collage-item bg-primary-light br-pill-bottom"></div>
                <div class="collage-item bg-primary-light br-bl"></div>
                <div class="collage-item bg-primary-dark br-tr">
                    <!-- Imagen placeholder (golden retriever) -->
                </div>
                <!-- Fila 4 -->
                <div class="collage-item bg-primary-light br-tr">
                    <!-- Imagen placeholder (gray cat) -->
                </div>
                <div class="collage-item bg-white br-circle">
                    <svg class="cross-icon" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                        <rect x="35" y="10" width="30" height="80" rx="15" fill="#93C5FD"/>
                        <rect x="10" y="35" width="80" height="30" rx="15" fill="#93C5FD"/>
                    </svg>
                </div>
                <div class="collage-item bg-primary-light br-leaf"></div>
            </div>
        </div>
    </div>

    <!-- Cargar script externo de registro cumpliendo con zooki_reglas.md -->
    <script src="js/register.js"></script>
</body>
</html>
