<?php
require_once __DIR__ . '/../../config/App.php';
$eCarnet = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Carnet de emergencia | Zooki</title>
    <link rel="stylesheet" href="css/portal/carnet.css?v=<?= $eCarnet(App::VERSION) ?>">
    <link rel="stylesheet" href="css/carnet-publico.css?v=<?= $eCarnet(App::VERSION) ?>">
    <script src="js/carnet-publico.js?v=<?= $eCarnet(App::VERSION) ?>" defer></script>
</head>
<body class="carnet-publico">
    <main class="carnet-publico__hoja">
        <header class="carnet-publico__cabecera"><span>Zooki</span><h1>Carnet de emergencia</h1><p>Información vital para ayudar a una mascota.</p></header>
        <?php if ($carnet === null): ?>
            <p class="carnet-publico__aviso" role="status"><?= $eCarnet($mensaje) ?></p>
        <?php else:
            $carnetPublico = true;
            require __DIR__ . '/../portal/carnet.php';
        ?>
            <section class="carnet-publico__ubicacion" aria-label="Ubicación opcional">
                <h2>Ayuda a que su dueño la encuentre</h2>
                <p>Compartir tu ubicación es opcional. Solo se solicita al tocar el botón y se envía si aceptas el permiso del navegador.</p>
                <button type="button" data-carnet-ubicacion data-token="<?= $eCarnet($token) ?>" data-comprobante="<?= $eCarnet($comprobante) ?>">Compartir mi ubicación con el dueño</button>
                <p data-carnet-ubicacion-mensaje role="status" aria-live="polite"></p>
            </section>
            <a class="carnet-publico__dueno" href="index.php?action=login&amp;carnet=<?= $eCarnet($token) ?>">Soy el dueño: ver ficha completa</a>
        <?php endif; ?>
    </main>
</body>
</html>
