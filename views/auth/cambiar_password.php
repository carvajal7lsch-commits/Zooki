<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña - Zooki</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/password-policy.js"></script>

<link rel="stylesheet" href="css/cambiar-password.css?v=d2">
<link rel="stylesheet" href="css/validacion-cuenta.css?v=d2">
</head>
<body>
    <div class="change-password-container">
        <div class="change-password-header">
            <i class="fas fa-lock"></i>
            <h1>Cambiar Contraseña</h1>
            <p>Por seguridad, debes cambiar tu contraseña en el primer inicio de sesión.</p>
        </div>

        <form id="changePasswordForm" data-password-accion="cambiar_password_ajax" data-validacion-cuenta>
<?php Csrf::field(); ?>
            <?php if (!empty($cuentaPassword['tiene_password'])): ?>
                <label>Contraseña actual <input type="password" name="password_actual" required autocomplete="current-password"></label>
            <?php endif; ?>
            <div class="form-group">
                <label for="nueva_password">Nueva Contraseña</label>
                <div class="input-con-ojito">
                    <input type="password" id="nueva_password" data-cuenta-ayuda="pwdFeedback" name="nueva_password" required minlength="8" maxlength="72" placeholder="Mínimo 8 caracteres, con mayúscula, minúscula y número">
                    <button type="button" class="ojito" data-ojito="nueva_password" tabindex="-1" aria-label="Mostrar u ocultar la contraseña" aria-pressed="false">
                        <i class="fas fa-eye-slash"></i>
                    </button>
                </div>
                <small id="pwdFeedback" class="pwd-feedback" role="status" aria-live="polite"></small>
            </div>

            <div class="form-group">
                <label for="confirmar_password">Confirmar Contraseña</label>
                <div class="input-con-ojito">
                    <input type="password" id="confirmar_password" name="confirmar_password" required minlength="8" maxlength="72" placeholder="Repite tu nueva contraseña">
                    <button type="button" class="ojito" data-ojito="confirmar_password" tabindex="-1" aria-label="Mostrar u ocultar la contraseña" aria-pressed="false">
                        <i class="fas fa-eye-slash"></i>
                    </button>
                </div>
            </div>

            <div class="password-requirements">
                <h4>Requisitos de contraseña:</h4>
                <ul>
                    <li>Mínimo 8 caracteres, con mayúscula, minúscula y número</li>
                    <li>Recomendado: usar letras, números y símbolos</li>
                </ul>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-key"></i> Cambiar Contraseña
            </button>
        <p role="status" data-password-resultado></p>
</form>
    </div>




<?php require __DIR__ . "/../partials/validacion_cuenta.php"; ?>
<script src="js/cuenta-password.js?v=d2"></script>
</body>
</html>
