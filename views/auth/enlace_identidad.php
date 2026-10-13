<?php
require_once __DIR__ . '/../../config/App.php';
require_once __DIR__ . '/../../helpers/ValidadorCuenta.php';
/**
 * D2 — Páginas de enlace de la cuenta: activar la cuenta del personal
 * (RE-T.19.1) y confirmar el correo nuevo (RE-T.5.7). D2.2: el titular de una
 * cuenta nueva puede no aceptar. M0.2: marco del acceso, la casilla común de
 * la política y el aviso de cada campo en su espacio reservado. M5 C3: el
 * propietario que registró la clínica activa aquí su cuenta (RE-T.19.1); él
 * no tiene «No acepto»: si no quiere el portal, deja vencer el enlace.
 */
require_once __DIR__ . '/../../helpers/PoliticaDatos.php';
$e = static fn ($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$propietario = $proposito === 'activacion_propietario';
$activar = $proposito === 'activacion_personal' || $propietario;
$acciones = ['activacion_personal' => 'activar_personal', 'activacion_propietario' => 'activar_propietario', 'cambio_correo' => 'confirmar_cambio_correo'];
$accion = $acciones[$proposito];
$tituloPagina = ($activar ? 'Activar cuenta' : 'Confirmar nuevo correo') . ' · Zooki';
require __DIR__ . '/../partials/acceso_marco_inicio.php';
if ($rechazada ?? false) {
    $estado = ['tipo' => 'aviso', 'icono' => 'ri-close-circle-line', 'titulo' => 'Invitación rechazada', 'texto' => 'No se activó ninguna cuenta y tus datos se retiraron de la clínica.'];
} elseif ($terminado) {
    $estado = ['tipo' => 'exito', 'titulo' => $activar ? 'Tu cuenta está activa' : 'Correo actualizado', 'texto' => $activar ? 'Ya puedes iniciar sesión con tu documento o tu correo y la contraseña que elegiste.' : 'Ya puedes entrar con el correo nuevo. Las demás sesiones abiertas se cerraron.'];
} elseif (($reemplazada ?? false) && $propietario) {
    $estado = ['tipo' => 'aviso', 'icono' => 'ri-mail-unread-line', 'titulo' => 'Hay un enlace más reciente', 'texto' => 'La clínica te envió un enlace nuevo. Usa el del último correo.'];
} elseif ($reemplazada ?? false) {
    $estado = ['tipo' => 'aviso', 'icono' => 'ri-mail-unread-line', 'titulo' => 'Hay una invitación más reciente', 'texto' => 'Esta invitación se reemplazó por una más reciente. Usa el enlace del último correo.'];
} elseif ($fila === null) {
    $estado = ['tipo' => 'error', 'icono' => 'ri-link-unlink', 'titulo' => 'Enlace no disponible', 'texto' => 'El enlace no es válido, venció o ya fue utilizado.'];
} elseif ($propietario) {
    $estado = ['tipo' => 'info', 'icono' => 'ri-user-add-line', 'titulo' => 'Activa tu portal', 'texto' => ($clinicaDelEnlace !== '' ? $clinicaDelEnlace : 'Tu clínica') . ' te registró en Zooki. Acepta la política y elige tu contraseña para entrar a tu portal. El enlace vence en 72 horas.'];
} elseif ($activar) {
    $estado = ['tipo' => 'info', 'icono' => 'ri-user-add-line', 'titulo' => 'Activa tu cuenta', 'texto' => 'Esta invitación vence en 72 horas. Acepta la política y elige tu contraseña.'];
} else {
    $estado = ['tipo' => 'info', 'icono' => 'ri-mail-check-line', 'titulo' => 'Confirma el correo nuevo', 'html' => 'Solo confirma si solicitaste cambiar tu correo por <strong>' . $e($fila['email']) . '</strong>.'];
}
require __DIR__ . '/../partials/acceso_estado.php';
?>
            <?php if ($error !== null): ?>
                <div class="registro-resumen" role="alert"><p><?= $e($error) ?></p></div>
            <?php endif; ?>
            <?php if (!$terminado && !($rechazada ?? false) && $fila !== null): ?>
                <form method="POST" action="index.php?action=<?= $accion ?>" class="acceso-form" data-validacion-cuenta>
                    <?php Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?= $e($id) ?>">
                    <input type="hidden" name="token" value="<?= $e($token) ?>">
                    <input type="hidden" name="id_enlace" value="<?= $e($id) ?>">
                    <input type="hidden" name="token_enlace" value="<?= $e($token) ?>">
                    <?php if ($activar): ?>
                        <div class="input-group">
                            <label for="activarPassword">Nueva contraseña</label>
                            <div class="input-wrapper">
                                <input <?= ValidadorCuenta::atributosHtml('password') ?> type="password" id="activarPassword" name="password" aria-describedby="activarPasswordAyuda" placeholder="••••••••" autocomplete="new-password" required>
                                <button type="button" class="toggle-password" tabindex="-1" aria-label="Mostrar contraseña"><i class="ri-eye-off-line" aria-hidden="true"></i></button>
                            </div>
                        </div>
                        <div class="input-group">
                            <label for="activarConfirmar">Confirma la contraseña</label>
                            <div class="input-wrapper">
                                <input <?= ValidadorCuenta::atributosHtml('password') ?> type="password" id="activarConfirmar" name="confirm_password" placeholder="Repite tu contraseña" autocomplete="new-password" required>
                                <button type="button" class="toggle-password" tabindex="-1" aria-label="Mostrar contraseña"><i class="ri-eye-off-line" aria-hidden="true"></i></button>
                            </div>
                        </div>
                        <p class="registro-nota" id="activarPasswordAyuda">Mínimo 8 caracteres, con mayúscula, minúscula y número.</p>
                        <?php $consentimientoId = 'aceptaDatosActivar'; $consentimientoVersion = PoliticaDatos::VERSION; require __DIR__ . '/../partials/consentimiento_datos.php'; ?>
                    <?php endif; ?>
                    <button type="submit" class="btn-primary"><span><?= $activar ? 'Aceptar y activar mi cuenta' : 'Confirmar cambio de correo' ?></span><i class="ri-check-line" aria-hidden="true"></i></button>
                </form>
                <?php if ($activar && !$propietario): ?>
                    <form method="POST" action="index.php?action=activar_personal" class="acceso-form acceso-form--secundario">
                        <?php Csrf::field(); ?>
                        <input type="hidden" name="id" value="<?= $e($id) ?>">
                        <input type="hidden" name="token" value="<?= $e($token) ?>">
                        <button type="submit" name="decision" value="rechazar" class="btn-secondary">No acepto esta invitación</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
            <p class="acceso-enlaces"><a class="acceso-enlace" href="index.php?action=login">Ir al inicio de sesión</a></p>
<?php require __DIR__ . '/../partials/acceso_marco_fin.php'; ?>
<script src="js/password-policy.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php require __DIR__ . '/../partials/validacion_cuenta.php'; ?>
<script src="js/cuenta-password.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
