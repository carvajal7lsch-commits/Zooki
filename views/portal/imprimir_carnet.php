<?php
require_once __DIR__ . '/../../config/App.php';
$impresionCarnet = true;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Imprimir carnet | Zooki</title>
    <link rel="stylesheet" href="css/portal/carnet.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <script src="js/carnet.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>" defer></script>
</head>
<body class="carnet-impresion">
    <main>
        <div class="carnet-barra-impresion"><h1>Carnet de emergencia</h1><button type="button" data-carnet-imprimir>Imprimir</button><p>Imprime al 100 %. Si hay muchos registros, se conserva toda la información.</p></div>
        <?php require __DIR__ . '/carnet.php'; ?>
    </main>
</body>
</html>
