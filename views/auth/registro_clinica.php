<?php
require_once __DIR__ . '/../../helpers/ValidadorCuenta.php';
require_once __DIR__ . '/../../helpers/ValidadorTelefono.php';
$e = static fn ($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$tituloPagina = 'Registra tu clínica';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php require __DIR__ . '/../partials/cabecera_acceso.php'; ?>
    <link rel="stylesheet" href="css/registro-clinica.css?v=d31">
    <script src="js/turnstile-formularios.js?v=d3"></script>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=zookiTurnstileListo" async defer></script>
</head>
<body class="login-page registro-clinica-page">
<div class="login-container">
    <div class="login-form-area">
        <div class="login-topbar">
            <a href="index.php" class="login-brand"><img src="img/icon_blue.png" alt="" class="brand-logo"><span class="brand-text">Zooki</span></a>
            <a href="index.php?action=login" class="login-back">Iniciar sesión</a>
        </div>
        <main class="form-wrapper">
            <h2>Registra tu clínica</h2>
            <p class="form-subtitle">Empieza con el plan gratuito. Confirma el registro desde tu correo para activar la clínica.</p>
            <form id="registroClinicaForm" method="POST" action="index.php?action=registrar_clinica_ajax" data-validacion-cuenta data-cuenta-datos="formulario">
                <?php Csrf::field(); ?>
                <fieldset class="registro-clinica-campos"><legend>Datos de la clínica</legend><div class="registro-clinica-grid">
                    <div class="input-group">
                        <label for="nombre_clinica">Nombre de la clínica</label>
                        <div class="input-wrapper"><input <?= ValidadorClinica::atributosHtml('nombre_clinica') ?> id="nombre_clinica" name="nombre_clinica" type="text" minlength="<?= ValidadorClinica::NOMBRE_MIN ?>" required></div>
                    </div>
                    <div class="input-group">
                        <label for="nit">NIT con dígito de verificación</label>
                        <div class="input-wrapper"><input <?= ValidadorClinica::atributosHtml('nit') ?> id="nit" name="nit" type="text" placeholder="900123456-8" required></div>
                    </div>
                    <div class="input-group">
                        <label for="direccion">Dirección</label>
                        <div class="input-wrapper"><input <?= ValidadorClinica::atributosHtml('direccion') ?> id="direccion" name="direccion" type="text" required></div>
                    </div>
                    <div class="input-group">
                        <label for="telefono_clinica">Teléfono</label>
                        <div class="input-wrapper"><input id="telefono_clinica" name="telefono_clinica" type="tel" required data-cuenta-campo="telefono" <?= ValidadorTelefono::atributosHtml() ?>></div>
                    </div>
                    <div class="input-group">
                        <label for="email_clinica">Correo de contacto</label>
                        <div class="input-wrapper"><input <?= ValidadorCuenta::atributosHtml('email') ?> id="email_clinica" name="email_clinica" type="email" required data-cuenta-campo="email_clinica"></div>
                    </div>
                </div></fieldset>
                <fieldset class="registro-clinica-campos"><legend>Datos del administrador</legend><div class="registro-clinica-grid">
                    <div class="input-group">
                        <label for="tipo_documento">Tipo de documento</label>
                        <div class="input-wrapper"><select id="tipo_documento" name="tipo_documento" required>
                            <?php foreach (Usuario::TIPOS_DOCUMENTO as $tipo): ?>
                                <option value="<?= $e($tipo) ?>"><?= $e($tipo) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    </div>
                    <div class="input-group">
                        <label for="nombre_completo">Nombre completo</label>
                        <div class="input-wrapper"><input <?= ValidadorCuenta::atributosHtml('nombre_completo') ?> id="nombre_completo" name="nombre_completo" type="text" required></div>
                    </div>
                    <div class="input-group">
                        <label for="documento">Número de documento</label>
                        <div class="input-wrapper"><input <?= ValidadorCuenta::atributosHtml('documento') ?> id="documento" name="documento" type="text" required></div>
                    </div>
                    <div class="input-group">
                        <label for="telefono">Teléfono</label>
                        <div class="input-wrapper"><input id="telefono" name="telefono" type="tel" required data-cuenta-campo="telefono" <?= ValidadorTelefono::atributosHtml() ?>></div>
                    </div>
                    <div class="input-group">
                        <label for="email">Correo</label>
                        <div class="input-wrapper"><input <?= ValidadorCuenta::atributosHtml('email') ?> id="email" name="email" type="email" required data-cuenta-campo="email_administrador"></div>
                    </div>
                    <div class="input-group">
                        <label for="password">Contraseña</label>
                        <div class="input-wrapper"><input <?= ValidadorCuenta::atributosHtml('password') ?> id="password" aria-describedby="claveCuentaAyuda" name="password" type="password" required autocomplete="new-password"><button type="button" class="toggle-password" tabindex="-1" aria-label="Mostrar contraseña"><i class="ri-eye-off-line" aria-hidden="true"></i></button></div>
                        <small class="form-subtitle" id="claveCuentaAyuda">Si ya tienes cuenta en Zooki, entrarás con tu contraseña de siempre.</small>
                    </div>
                    <div class="input-group">
                        <label for="confirm_password">Confirma la contraseña</label>
                        <div class="input-wrapper"><input <?= ValidadorCuenta::atributosHtml('password') ?> id="confirm_password" name="confirm_password" type="password" required autocomplete="new-password"><button type="button" class="toggle-password" tabindex="-1" aria-label="Mostrar contraseña"><i class="ri-eye-off-line" aria-hidden="true"></i></button></div>
                    </div>
                </div></fieldset>
                <label class="registro-consentimiento"><input type="checkbox" name="acepta_datos" value="1" required><span>Acepto la <a href="index.php?action=privacidad" target="_blank" rel="noopener">política de tratamiento de datos</a>.</span></label>
                <div data-turnstile-formulario data-sitekey="<?= $e(Turnstile::configuracion('TURNSTILE_SITE_KEY')) ?>"></div>
                <button type="submit" class="btn-primary">Registrar clínica</button>
                <p id="registroClinicaResultado" role="status" aria-live="polite"></p>
                <button id="reportarNit" type="button" class="btn-secondary" hidden>Reportar NIT a soporte</button>
            </form>
        </main>
    </div>
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
<script src="js/password-policy.js?v=d3"></script>
<?php require __DIR__ . '/../partials/validacion_cuenta.php'; ?>
<script src="js/avisos.js?v=d3"></script>
<script src="js/cuenta-password.js?v=d3"></script>
<script src="js/registro-clinica.js?v=d3"></script>
</body>
</html>
