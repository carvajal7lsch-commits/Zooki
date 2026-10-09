<?php
require_once __DIR__ . '/../../helpers/Csrf.php';
$e = static fn ($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirmar correo · Zooki</title>
    <meta name="referrer" content="no-referrer">
    <link rel="stylesheet" href="css/contexto.css">
    <link rel="stylesheet" href="css/cuenta-pendiente.css">
</head>
<body class="contexto-pagina">
<main class="contexto-tarjeta">
    <h1>Confirma tu correo</h1>
    <p>Presiona el botón para confirmar que este correo te pertenece.</p>
    <form method="POST" action="index.php?action=verificar_email">
        <?php Csrf::field(); ?>
        <input type="hidden" name="id" value="<?= $e($id) ?>">
        <input type="hidden" name="token" value="<?= $e($token) ?>">
        <button type="submit">Confirmar mi correo</button>
    </form>
</main>
</body>
</html>
