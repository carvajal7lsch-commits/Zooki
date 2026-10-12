<?php
require_once __DIR__ . '/../../helpers/ValidadorCuenta.php';
require_once __DIR__ . '/../../helpers/ValidadorTelefono.php';
require_once __DIR__ . '/../../helpers/Turnstile.php';
$e = static fn ($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$tituloPagina = 'Registra tu clínica';
// M0.1: resultado de un envío sin JavaScript (RegistroClinicaController::responder).
$resultadoRegistro = $resultadoRegistro ?? null;
$registroAceptado = ($resultadoRegistro['success'] ?? false) === true;
$errorRegistro = $resultadoRegistro !== null && !$registroAceptado ? (string) ($resultadoRegistro['message'] ?? '') : null;
$turnstileClave = Turnstile::configuracion('TURNSTILE_SITE_KEY');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php require __DIR__ . '/../partials/cabecera_acceso.php'; ?>
    <link rel="stylesheet" href="css/registro-clinica.css?v=<?= $versionAcceso ?>">
    <script src="js/turnstile-formularios.js?v=<?= $versionAcceso ?>"></script>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=zookiTurnstileListo&render=explicit" async defer></script>
</head>
<body class="login-page registro-clinica-page">
<div class="login-container">
    <div class="login-form-area">
        <div class="login-topbar">
            <a href="index.php" class="login-brand" aria-label="Zooki, ir al inicio"><img src="img/icon_blue.png" alt="" class="brand-logo" draggable="false"><span class="brand-text">Zooki</span></a>
            <a href="index.php?action=login" class="login-back"><i class="ri-login-box-line" aria-hidden="true"></i><span>Iniciar sesión</span></a>
        </div>
        <main class="form-wrapper registro-marco">
            <div class="form-header">
                <a class="btn-back-top" href="index.php?action=login" title="Volver al inicio de sesión" aria-label="Volver al inicio de sesión"><i class="ri-arrow-left-line" aria-hidden="true"></i></a>
                <h2>Registra tu clínica</h2>
            </div>
            <p class="form-subtitle">Empieza con el plan gratuito. Confirma el registro desde tu correo para activar la clínica.</p>
            <?php // M0.1: el indicador solo aparece con JavaScript; sin él se ven los tres pasos a la vez. ?>
            <ol class="registro-pasos" id="registroPasos" aria-label="Pasos del registro" hidden>
                <li data-paso-indicador="1"><span class="registro-pasos-numero">1</span><span class="registro-pasos-nombre">Clínica</span></li>
                <li data-paso-indicador="2"><span class="registro-pasos-numero">2</span><span class="registro-pasos-nombre">Administrador</span></li>
                <li data-paso-indicador="3"><span class="registro-pasos-numero">3</span><span class="registro-pasos-nombre">Confirmar</span></li>
            </ol>
            <form id="registroClinicaForm" method="POST" action="index.php?action=registrar_clinica_ajax" data-validacion-cuenta data-cuenta-datos="formulario" data-registro data-registro-espera="registroClinicaEspera" data-registro-pasos="registroPasos" <?= $registroAceptado ? 'hidden' : '' ?>>
                <?php Csrf::field(); ?>
                <div class="registro-resumen" data-registro-resumen role="alert" <?= $errorRegistro === null ? 'hidden' : '' ?>>
                    <?php if ($errorRegistro !== null): ?><p><?= $e($errorRegistro) ?></p><?php endif; ?>
                </div>
                <fieldset class="registro-paso" data-paso="1">
                    <legend>Datos de la clínica</legend>
                    <div class="registro-grid">
                        <div class="input-group registro-ancho">
                            <label for="nombre_clinica">Nombre de la clínica</label>
                            <div class="input-wrapper"><input <?= ValidadorClinica::atributosHtml('nombre_clinica') ?> id="nombre_clinica" name="nombre_clinica" type="text" minlength="<?= ValidadorClinica::NOMBRE_MIN ?>" placeholder="Ej. Clínica Veterinaria Huellitas" required autocomplete="organization"></div>
                        </div>
                        <div class="registro-clinica-nit registro-ancho">
                            <div class="input-group">
                                <label for="nit">NIT</label>
                                <div class="input-wrapper"><input <?= ValidadorClinica::atributosHtml('nit') ?> id="nit" name="nit" type="text" placeholder="900123456" required></div>
                            </div>
                            <div class="input-group">
                                <label for="dv">DV</label>
                                <div class="input-wrapper"><input <?= ValidadorClinica::atributosHtml('dv') ?> id="dv" name="dv" type="text" required aria-describedby="dvAyuda" data-cuenta-ayuda="nitUnicidad"></div>
                            </div>
                            <small id="dvAyuda" class="registro-nota">El DV está en tu RUT, después del guion.</small>
                            <small id="nitUnicidad" role="status"></small>
                            <button id="reportarNit" type="button" class="btn-secondary registro-reportar" hidden>Reportar a soporte</button>
                        </div>
                        <div class="input-group registro-ancho">
                            <label for="direccion">Dirección</label>
                            <div class="input-wrapper"><input <?= ValidadorClinica::atributosHtml('direccion') ?> id="direccion" name="direccion" type="text" placeholder="Ej. Carrera 7 # 12-34, Neiva" required autocomplete="street-address"></div>
                        </div>
                        <div class="input-group">
                            <label for="telefono_clinica">Teléfono</label>
                            <div class="input-wrapper"><input id="telefono_clinica" name="telefono_clinica" type="tel" placeholder="Ej: 608 871 2345" required minlength="<?= ValidadorTelefono::MIN ?>" data-cuenta-campo="telefono" <?= ValidadorTelefono::atributosHtml() ?>></div>
                        </div>
                        <div class="input-group">
                            <label for="email_clinica">Correo de contacto</label>
                            <div class="input-wrapper"><input <?= ValidadorCuenta::atributosHtml('email') ?> id="email_clinica" name="email_clinica" type="email" placeholder="contacto@clinica.com" required data-cuenta-campo="email_clinica"></div>
                        </div>
                    </div>
                    <div class="registro-acciones">
                        <button type="button" class="btn-primary" data-paso-ir="siguiente">Siguiente <i class="ri-arrow-right-line" aria-hidden="true"></i></button>
                    </div>
                </fieldset>
                <fieldset class="registro-paso" data-paso="2">
                    <legend>Datos del administrador</legend>
                    <div class="registro-grid">
                        <div class="input-group">
                            <label for="tipo_documento">Tipo de documento</label>
                            <div class="input-wrapper"><select id="tipo_documento" name="tipo_documento" required>
                                <?php foreach (Usuario::TIPOS_DOCUMENTO as $tipo): ?>
                                    <option value="<?= $e($tipo) ?>"><?= $e(Usuario::NOMBRES_TIPO_DOCUMENTO[$tipo] ?? $tipo) ?></option>
                                <?php endforeach; ?>
                            </select></div>
                        </div>
                        <div class="input-group">
                            <label for="documento">Número de documento</label>
                            <div class="input-wrapper"><input <?= ValidadorCuenta::atributosHtml('documento') ?> id="documento" name="documento" type="text" placeholder="Ej. 1075..." required autocomplete="off"></div>
                        </div>
                        <div class="input-group registro-ancho">
                            <label for="nombre_completo">Nombre completo</label>
                            <div class="input-wrapper"><input <?= ValidadorCuenta::atributosHtml('nombre_completo') ?> id="nombre_completo" name="nombre_completo" type="text" placeholder="Nombres y apellidos" required autocomplete="name"></div>
                        </div>
                        <div class="input-group">
                            <label for="telefono">Teléfono</label>
                            <div class="input-wrapper"><input id="telefono" name="telefono" type="tel" placeholder="Ej: 300 123 4567" required minlength="<?= ValidadorTelefono::MIN ?>" data-cuenta-campo="telefono" <?= ValidadorTelefono::atributosHtml() ?> autocomplete="tel"></div>
                        </div>
                        <div class="input-group">
                            <label for="email">Correo</label>
                            <div class="input-wrapper"><input <?= ValidadorCuenta::atributosHtml('email') ?> id="email" name="email" type="email" placeholder="correo@ejemplo.com" required data-cuenta-campo="email_administrador" autocomplete="email"></div>
                        </div>
                        <div class="input-group">
                            <label for="password">Contraseña</label>
                            <div class="input-wrapper"><input <?= ValidadorCuenta::atributosHtml('password') ?> id="password" aria-describedby="claveCuentaAyuda" name="password" type="password" placeholder="••••••••" required autocomplete="new-password"><button type="button" class="toggle-password" tabindex="-1" aria-label="Mostrar contraseña"><i class="ri-eye-off-line" aria-hidden="true"></i></button></div>
                        </div>
                        <div class="input-group">
                            <label for="confirm_password">Confirma la contraseña</label>
                            <div class="input-wrapper"><input <?= ValidadorCuenta::atributosHtml('password') ?> id="confirm_password" name="confirm_password" type="password" placeholder="••••••••" required autocomplete="new-password"><button type="button" class="toggle-password" tabindex="-1" aria-label="Mostrar contraseña"><i class="ri-eye-off-line" aria-hidden="true"></i></button></div>
                        </div>
                        <p class="registro-nota registro-ancho" id="claveCuentaAyuda">Mínimo 8 caracteres, con mayúscula, minúscula y número. Si ya tienes cuenta en Zooki, entrarás con tu contraseña de siempre.</p>
                    </div>
                    <div class="registro-acciones">
                        <button type="button" class="btn-secondary" data-paso-ir="anterior"><i class="ri-arrow-left-line" aria-hidden="true"></i> Atrás</button>
                        <button type="button" class="btn-primary" data-paso-ir="siguiente">Siguiente <i class="ri-arrow-right-line" aria-hidden="true"></i></button>
                    </div>
                </fieldset>
                <fieldset class="registro-paso" data-paso="3">
                    <legend>Confirma y registra</legend>
                    <p class="registro-nota">Enviaremos el enlace de activación al correo del administrador. La clínica empieza con el plan gratuito.</p>
                    <?php $consentimientoId = 'aceptaDatosClinica'; require __DIR__ . '/../partials/consentimiento_datos.php'; ?>
                    <div class="registro-acciones">
                        <button type="button" class="btn-secondary" data-paso-ir="anterior"><i class="ri-arrow-left-line" aria-hidden="true"></i> Atrás</button>
                        <button type="submit" class="btn-primary">Registrar clínica</button>
                    </div>
                </fieldset>
                <?php if ($turnstileClave !== ''): ?>
                    <?php // Fuera de los pasos: el reporte de NIT del paso 1 también lo exige. «interaction-only»: solo se ve si Cloudflare pide interactuar. ?>
                    <div class="acceso-turnstile" data-turnstile-formulario data-sitekey="<?= $e($turnstileClave) ?>"></div>
                <?php endif; ?>
            </form>
            <?php
            $esperaId = 'registroClinicaEspera';
            $esperaTexto = 'Si la solicitud puede completarse, el titular recibirá el enlace para activar la clínica. Ábrelo y después inicia sesión.';
            $esperaListo = $registroAceptado;
            $esperaMensaje = $registroAceptado ? (string) $resultadoRegistro['message'] : RegistroClinica::MENSAJE;
            require __DIR__ . '/../partials/registro_espera.php';
            ?>
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
<script src="js/password-policy.js?v=<?= $versionAcceso ?>"></script>
<?php require __DIR__ . '/../partials/validacion_cuenta.php'; ?>
<script src="js/avisos.js?v=<?= $versionAcceso ?>"></script>
<script src="js/cuenta-password.js?v=<?= $versionAcceso ?>"></script>
<script src="js/registro-cuenta.js?v=<?= $versionAcceso ?>"></script>
<script src="js/registro-pasos.js?v=<?= $versionAcceso ?>"></script>
<script src="js/registro-clinica.js?v=<?= $versionAcceso ?>"></script>
</body>
</html>
