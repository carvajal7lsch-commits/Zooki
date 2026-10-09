<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tokenId = $tokenId ?? 0;
$tokenPlano = $tokenPlano ?? '';
$tokenValido = $tokenValido ?? false;
$errorMessage = $errorMessage ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contraseña - Zooki</title>
    <link rel="icon" type="image/png" href="img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/password-policy.js"></script>
<link rel="stylesheet" href="css/reset-password.css?v=d2">
<link rel="stylesheet" href="css/validacion-cuenta.css?v=d2">
</head>
<body class="login-page reset-page">
    <div class="reset-wrapper">
        <div class="reset-card">
            <div class="reset-hero">
                <div class="reset-icon">
                    <i class="ri-lock-password-line"></i>
                </div>
                <h1><?php echo $tokenValido ? 'Crea una nueva contraseña' : 'Enlace no disponible'; ?></h1>
                <p class="reset-subtitle">
                    <?php if ($tokenValido): ?>
                        Protege tu cuenta con una contraseña segura y fácil de recordar.
                    <?php else: ?>
                        <?php echo htmlspecialchars($errorMessage ?: 'El enlace que intentas usar no es válido o ya expiró.'); ?>
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($tokenValido): ?>
            <form class="reset-form" id="resetPasswordForm" data-password-accion="procesar_reset_password_ajax" data-validacion-cuenta>
                <?php Csrf::field(); ?>
                <input type="hidden" name="token_id" value="<?php echo (int)$tokenId; ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($tokenPlano, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="input-group">
                    <label for="newPassword">Nueva contraseña</label>
                    <div class="input-wrapper">
                        <i class="ri-shield-keyhole-line"></i>
                        <input type="password" id="newPassword" data-cuenta-ayuda="passwordValidationMsg" name="password" placeholder="••••••••" required minlength="8" autocomplete="new-password">
                        <button type="button" class="toggle-password" id="toggleNewPassword" tabindex="-1">
                            <i class="ri-eye-off-line"></i>
                        </button>
                    </div>
                    <div class="password-meter-container">
                        <div class="password-meter" id="passwordMeter"></div>
                    </div>
                    <span class="validation-msg" id="passwordValidationMsg">Mínimo 8 caracteres, con mayúscula, minúscula y número</span>
                </div>

                <div class="input-group">
                    <label for="confirmPassword">Confirmar contraseña</label>
                    <div class="input-wrapper">
                        <i class="ri-check-double-line"></i>
                        <input type="password" id="confirmPassword" name="password_confirmation" placeholder="Repite tu contraseña" required minlength="8" autocomplete="new-password">
                        <button type="button" class="toggle-password" id="toggleConfirmPassword" tabindex="-1">
                            <i class="ri-eye-off-line"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary" id="resetSubmitBtn">
                    <span>Actualizar contraseña</span>
                    <i class="ri-refresh-line"></i>
                </button>
            <p role="status" data-password-resultado></p>
</form>
            <?php else: ?>
                <div class="reset-actions">
                    <a class="btn-secondary" href="index.php?action=login">
                        <i class="ri-login-box-line"></i>
                        <span>Ir a inicio de sesión</span>
                    </a>
                    <a class="btn-link" href="index.php?action=login" id="requestAnotherLink">
                        <i class="ri-mail-send-line"></i>
                        <span>Solicitar un nuevo enlace</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>




<?php require __DIR__ . "/../partials/validacion_cuenta.php"; ?>
<script src="js/cuenta-password.js?v=d2"></script>
</body>
</html>
