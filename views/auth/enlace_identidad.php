<?php
require_once __DIR__ . '/../../helpers/PoliticaDatos.php';
$e = static fn ($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$activar = $proposito === 'activacion_personal';
$accion = $activar ? 'activar_personal' : 'confirmar_cambio_correo';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title><?= $activar ? 'Activar cuenta' : 'Confirmar nuevo correo' ?> · Zooki</title>
    <link rel="stylesheet" href="css/contexto.css">
    <link rel="stylesheet" href="css/cuenta-pendiente.css">
    <link rel="stylesheet" href="css/validacion-cuenta.css?v=d21">
    <script src="js/password-policy.js"></script>
</head>
<body class="contexto-pagina">
<main class="contexto-tarjeta">
    <h1><?= $activar ? 'Activa tu cuenta' : 'Confirma el correo nuevo' ?></h1>
    <?php if ($error !== null): ?>
        <p role="alert"><?= $e($error) ?></p>
    <?php endif; ?>
    <?php if ($terminado): ?>
        <p><?= $activar ? 'Tu cuenta quedó activa. Ya puedes iniciar sesión.' : 'Tu correo fue actualizado. Ya puedes entrar con el nuevo.' ?></p>
    <?php elseif ($fila === null): ?>
        <p>El enlace no es válido, venció o ya fue utilizado.</p>
    <?php else: ?>
        <form method="POST" action="index.php?action=<?= $accion ?>" class="cuenta-form" data-validacion-cuenta>
            <?php Csrf::field(); ?>
            <input type="hidden" name="id" value="<?= $e($id) ?>">
            <input type="hidden" name="token" value="<?= $e($token) ?>">
            <input type="hidden" name="id_enlace" value="<?= $e($id) ?>">
            <input type="hidden" name="token_enlace" value="<?= $e($token) ?>">
            <?php if ($activar): ?>
                <p>Esta invitación vence en 72 horas. Acepta la política y elige tu contraseña.</p>
                <label>Nueva contraseña <input type="password" name="password" autocomplete="new-password" maxlength="72" required></label>
                <label>Repite la contraseña <input type="password" name="confirm_password" autocomplete="new-password" maxlength="72" required></label>
                <label><input type="checkbox" name="acepta_datos" value="1" required> Acepto la <a href="index.php?action=privacidad" target="_blank" rel="noopener">política de tratamiento de datos</a> (<?= $e(PoliticaDatos::VERSION) ?>).</label>
            <?php else: ?>
                <p>Solo confirma si solicitaste cambiar tu correo por <?= $e($fila['email']) ?>.</p>
            <?php endif; ?>
            <button type="submit" class="cuenta-boton"><?= $activar ? 'Aceptar y activar mi cuenta' : 'Confirmar cambio de correo' ?></button>
        </form>
    <?php endif; ?>
    <a href="index.php?action=login">Ir al inicio de sesión</a>
</main>
<?php require __DIR__ . '/../partials/validacion_cuenta.php'; ?>
</body>
</html>
