<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Confirmar vínculo — Zooki</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="login-page">
<main class="reset-card">
    <h1>Vínculo con una clínica</h1>
    <?php if ($resultado === null): ?>
    <p>Confirma únicamente si elegiste vincular tu cuenta a la clínica indicada en el correo.</p>
    <form method="post" action="index.php?action=confirmar_vinculo_propietario">
        <?php Csrf::field(); ?>
        <input type="hidden" name="id" value="<?= htmlspecialchars((string)$id,ENT_QUOTES,'UTF-8') ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token,ENT_QUOTES,'UTF-8') ?>">
        <button type="submit" class="btn-primary">Confirmar vínculo</button>
    </form>
    <?php else: ?>
    <p><?= $resultado ? 'Confirmaste el vínculo con la clínica.' : 'El enlace no es válido, venció o ya fue utilizado.' ?></p>
    <?php endif; ?>
    <a href="index.php?action=login">Ir al inicio de sesión</a>
</main>
</body></html>
