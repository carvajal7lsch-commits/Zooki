<?php
/**
 * T-05 / RE-T.2.4 — Cambio obligatorio de la contraseña (o crearla, si la
 * cuenta es de Google). Recibe del enrutador $cuentaPassword.
 * M0.2: marco del acceso con la sesión abierta.
 */
require_once __DIR__ . '/../../config/App.php';
require_once __DIR__ . '/../../helpers/ValidadorCuenta.php';
require_once __DIR__ . '/../../helpers/Csrf.php';
$e = static fn ($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$tienePassword = !empty($cuentaPassword['tiene_password']);
$tituloPagina = 'Cambiar contraseña · Zooki';
$accesoSalida = 'sesion';
require __DIR__ . '/../partials/acceso_marco_inicio.php';
$estado = $tienePassword
    ? ['tipo' => 'info', 'icono' => 'ri-key-2-line', 'titulo' => 'Cambia tu contraseña', 'texto' => 'Por seguridad, elige una contraseña nueva antes de continuar.']
    : ['tipo' => 'info', 'icono' => 'ri-key-2-line', 'titulo' => 'Crea tu contraseña', 'texto' => 'Elige una contraseña para entrar también con tu documento o tu correo.'];
require __DIR__ . '/../partials/acceso_estado.php';
?>
            <form class="acceso-form" id="changePasswordForm" data-password-accion="cambiar_password_ajax" data-validacion-cuenta>
                <?php Csrf::field(); ?>
                <?php if (!$tienePassword) require __DIR__ . '/../partials/confirmacion_google.php'; ?>
                <?php if ($tienePassword): ?>
                    <div class="input-group">
                        <label for="password_actual">Contraseña actual</label>
                        <div class="input-wrapper">
                            <input <?= ValidadorCuenta::atributosHtml('password') ?> type="password" id="password_actual" name="password_actual" required autocomplete="current-password" placeholder="Tu contraseña actual">
                            <button type="button" class="toggle-password" tabindex="-1" aria-label="Mostrar u ocultar la contraseña" aria-pressed="false"><i class="ri-eye-off-line" aria-hidden="true"></i></button>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="input-group">
                    <label for="nueva_password">Nueva contraseña</label>
                    <div class="input-wrapper">
                        <input <?= ValidadorCuenta::atributosHtml('password') ?> type="password" id="nueva_password" name="nueva_password" aria-describedby="cambiarClaveAyuda" required minlength="8" autocomplete="new-password" placeholder="••••••••">
                        <button type="button" class="toggle-password" tabindex="-1" aria-label="Mostrar u ocultar la contraseña" aria-pressed="false"><i class="ri-eye-off-line" aria-hidden="true"></i></button>
                    </div>
                </div>
                <div class="input-group">
                    <label for="confirmar_password">Confirma la contraseña</label>
                    <div class="input-wrapper">
                        <input <?= ValidadorCuenta::atributosHtml('password') ?> type="password" id="confirmar_password" name="confirmar_password" required minlength="8" autocomplete="new-password" placeholder="Repite tu contraseña">
                        <button type="button" class="toggle-password" tabindex="-1" aria-label="Mostrar u ocultar la contraseña" aria-pressed="false"><i class="ri-eye-off-line" aria-hidden="true"></i></button>
                    </div>
                </div>
                <p class="registro-nota" id="cambiarClaveAyuda">Mínimo 8 caracteres, con mayúscula, minúscula y número. Mejor si combinas letras, números y símbolos.</p>
                <button type="submit" class="btn-primary"><span><?= $tienePassword ? 'Cambiar contraseña' : 'Crear contraseña' ?></span><i class="ri-key-2-line" aria-hidden="true"></i></button>
                <p class="acceso-resultado" role="status" data-password-resultado></p>
            </form>
<?php require __DIR__ . '/../partials/acceso_marco_fin.php'; ?>
<script src="js/password-policy.js?v=<?= $e(App::assetVersion()) ?>"></script>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<?php require __DIR__ . '/../partials/validacion_cuenta.php'; ?>
<script src="js/cuenta-password.js?v=<?= $e(App::assetVersion()) ?>"></script>
<script src="js/avisos.js?v=<?= $e(App::assetVersion()) ?>"></script>
</body>
</html>
