<?php
/**
 * D2 — Páginas de enlace de la cuenta: activar la cuenta del personal
 * (RE-T.19.1) y confirmar el correo nuevo (RE-T.5.7). D2.2: con el diseño de
 * reset_password.php, y el titular de una cuenta nueva puede no aceptar.
 */
require_once __DIR__ . '/../../helpers/PoliticaDatos.php';
$e = static fn ($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$activar = $proposito === 'activacion_personal';
$accion = $activar ? 'activar_personal' : 'confirmar_cambio_correo';
$tituloPagina = $activar ? 'Activar cuenta' : 'Confirmar nuevo correo';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php require __DIR__ . '/../partials/cabecera_enlace.php'; ?>
    <script src="js/password-policy.js?v=d22"></script>
</head>
<body class="login-page reset-page">
<div class="reset-wrapper">
    <main class="reset-card">
        <div class="reset-hero">
            <div class="reset-icon"><i class="<?= $activar ? 'ri-user-add-line' : 'ri-mail-check-line' ?>" aria-hidden="true"></i></div>
            <?php if ($rechazada ?? false): ?>
                <h1>Invitación rechazada</h1>
                <p class="reset-subtitle">No se activó ninguna cuenta y tus datos se retiraron de la clínica.</p>
            <?php elseif ($terminado): ?>
                <h1><?= $activar ? 'Tu cuenta está activa' : 'Correo actualizado' ?></h1>
                <p class="reset-subtitle"><?= $activar ? 'Ya puedes iniciar sesión con tu documento o tu correo y la contraseña que elegiste.' : 'Ya puedes entrar con el correo nuevo. Las demás sesiones abiertas se cerraron.' ?></p>
            <?php elseif ($fila === null): ?>
                <h1>Enlace no disponible</h1>
                <p class="reset-subtitle">El enlace no es válido, venció o ya fue utilizado.</p>
            <?php elseif ($activar): ?>
                <h1>Activa tu cuenta</h1>
                <p class="reset-subtitle">Esta invitación vence en 72 horas. Acepta la política y elige tu contraseña.</p>
            <?php else: ?>
                <h1>Confirma el correo nuevo</h1>
                <p class="reset-subtitle">Solo confirma si solicitaste cambiar tu correo por <strong><?= $e($fila['email']) ?></strong>.</p>
            <?php endif; ?>
        </div>

        <?php if ($error !== null): ?>
            <p class="reset-aviso" role="alert"><?= $e($error) ?></p>
        <?php endif; ?>

        <?php if (!$terminado && !($rechazada ?? false) && $fila !== null): ?>
            <form method="POST" action="index.php?action=<?= $accion ?>" class="reset-form" data-validacion-cuenta>
                <?php Csrf::field(); ?>
                <input type="hidden" name="id" value="<?= $e($id) ?>">
                <input type="hidden" name="token" value="<?= $e($token) ?>">
                <input type="hidden" name="id_enlace" value="<?= $e($id) ?>">
                <input type="hidden" name="token_enlace" value="<?= $e($token) ?>">
                <?php if ($activar): ?>
                    <div class="input-group">
                        <label for="activarPassword">Nueva contraseña</label>
                        <div class="input-wrapper">
                            <i class="ri-shield-keyhole-line" aria-hidden="true"></i>
                            <input type="password" id="activarPassword" name="password" data-cuenta-ayuda="activarPasswordAyuda" placeholder="••••••••" autocomplete="new-password" maxlength="72" required>
                            <button type="button" class="toggle-password" tabindex="-1" aria-label="Mostrar contraseña"><i class="ri-eye-off-line" aria-hidden="true"></i></button>
                        </div>
                        <span class="validation-msg" id="activarPasswordAyuda">Mínimo 8 caracteres, con mayúscula, minúscula y número</span>
                    </div>
                    <div class="input-group">
                        <label for="activarConfirmar">Confirmar contraseña</label>
                        <div class="input-wrapper">
                            <i class="ri-check-double-line" aria-hidden="true"></i>
                            <input type="password" id="activarConfirmar" name="confirm_password" placeholder="Repite tu contraseña" autocomplete="new-password" maxlength="72" required>
                            <button type="button" class="toggle-password" tabindex="-1" aria-label="Mostrar contraseña"><i class="ri-eye-off-line" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <label class="reset-check">
                        <input type="checkbox" name="acepta_datos" value="1" required>
                        <span>Acepto la <a href="index.php?action=privacidad" target="_blank" rel="noopener">política de tratamiento de datos</a> (<?= $e(PoliticaDatos::VERSION) ?>).</span>
                    </label>
                <?php endif; ?>
                <button type="submit" class="btn-primary">
                    <span><?= $activar ? 'Aceptar y activar mi cuenta' : 'Confirmar cambio de correo' ?></span>
                    <i class="ri-check-line" aria-hidden="true"></i>
                </button>
            </form>
            <?php if ($activar): ?>
                <form method="POST" action="index.php?action=activar_personal" class="reset-form reset-form--secundario">
                    <?php Csrf::field(); ?>
                    <input type="hidden" name="id" value="<?= $e($id) ?>">
                    <input type="hidden" name="token" value="<?= $e($token) ?>">
                    <button type="submit" name="decision" value="rechazar" class="btn-secondary">No acepto esta invitación</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <a class="reset-enlace-login" href="index.php?action=login">Ir al inicio de sesión</a>
    </main>
</div>
<?php require __DIR__ . '/../partials/validacion_cuenta.php'; ?>
<script src="js/cuenta-password.js?v=d22"></script>
</body>
</html>
