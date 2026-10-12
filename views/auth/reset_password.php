<?php
/**
 * RE-T.2.x — Crear una contraseña nueva desde el enlace del correo. Recibe de
 * AuthController $tokenId, $tokenPlano, $tokenValido y $errorMessage.
 * M0.2: marco del acceso; el aviso de cada campo va en su espacio reservado.
 */
require_once __DIR__ . '/../../config/App.php';
require_once __DIR__ . '/../../helpers/ValidadorCuenta.php';
require_once __DIR__ . '/../../helpers/Csrf.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$e = static fn ($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$tokenId = $tokenId ?? 0;
$tokenPlano = $tokenPlano ?? '';
$tokenValido = $tokenValido ?? false;
$errorMessage = $errorMessage ?? '';
$tituloPagina = 'Restablecer contraseña · Zooki';
require __DIR__ . '/../partials/acceso_marco_inicio.php';
$estado = $tokenValido
    ? ['tipo' => 'info', 'icono' => 'ri-lock-password-line', 'titulo' => 'Crea una contraseña nueva', 'texto' => 'Protege tu cuenta con una contraseña segura y fácil de recordar.']
    : ['tipo' => 'error', 'icono' => 'ri-link-unlink', 'titulo' => 'Enlace no disponible', 'texto' => $errorMessage ?: 'El enlace que intentas usar no es válido o ya expiró.'];
require __DIR__ . '/../partials/acceso_estado.php';
?>
            <?php if ($tokenValido): ?>
            <form class="acceso-form" id="resetPasswordForm" data-password-accion="procesar_reset_password_ajax" data-validacion-cuenta>
                <?php Csrf::field(); ?>
                <input type="hidden" name="token_id" value="<?= (int) $tokenId ?>">
                <input type="hidden" name="token" value="<?= $e($tokenPlano) ?>">
                <div class="input-group">
                    <label for="newPassword">Nueva contraseña</label>
                    <div class="input-wrapper">
                        <input <?= ValidadorCuenta::atributosHtml('password') ?> type="password" id="newPassword" name="password" aria-describedby="resetClaveAyuda" placeholder="••••••••" required minlength="8" autocomplete="new-password">
                        <button type="button" class="toggle-password" id="toggleNewPassword" tabindex="-1" aria-label="Mostrar contraseña"><i class="ri-eye-off-line" aria-hidden="true"></i></button>
                    </div>
                </div>
                <div class="input-group">
                    <label for="confirmPassword">Confirma la contraseña</label>
                    <div class="input-wrapper">
                        <input <?= ValidadorCuenta::atributosHtml('password') ?> type="password" id="confirmPassword" name="password_confirmation" placeholder="Repite tu contraseña" required minlength="8" autocomplete="new-password">
                        <button type="button" class="toggle-password" id="toggleConfirmPassword" tabindex="-1" aria-label="Mostrar contraseña"><i class="ri-eye-off-line" aria-hidden="true"></i></button>
                    </div>
                </div>
                <p class="registro-nota" id="resetClaveAyuda">Mínimo 8 caracteres, con mayúscula, minúscula y número.</p>
                <button type="submit" class="btn-primary" id="resetSubmitBtn"><span>Actualizar contraseña</span><i class="ri-refresh-line" aria-hidden="true"></i></button>
                <p class="acceso-resultado" role="status" data-password-resultado></p>
            </form>
            <?php endif; ?>
            <p class="acceso-enlaces">
                <?php if (!$tokenValido): ?><a class="acceso-enlace" href="index.php?action=login&amp;recuperar=1">Solicitar un enlace nuevo</a><?php endif; ?>
                <a class="acceso-enlace" href="index.php?action=login">Ir al inicio de sesión</a>
            </p>
<?php require __DIR__ . '/../partials/acceso_marco_fin.php'; ?>
<script src="js/password-policy.js?v=<?= $e(App::assetVersion()) ?>"></script>
<?php require __DIR__ . '/../partials/validacion_cuenta.php'; ?>
<script src="js/cuenta-password.js?v=<?= $e(App::assetVersion()) ?>"></script>
<script src="js/avisos.js?v=<?= $e(App::assetVersion()) ?>"></script>
</body>
</html>
