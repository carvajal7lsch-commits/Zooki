<?php
/** D3.1: mismos estilos del login y del registro público de clínica. */
require_once __DIR__ . '/../../config/App.php';
// M0.1: los archivos propios se piden con la versión; cada entrega a producción sube la alfa.
$versionAcceso = htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8');
?>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloPagina ?? $pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" type="image/png" href="img/icon_blue.png">
    <!-- Fuentes y estilos -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <!-- El CSS se enlaza desde la carpeta public/css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="css/styles.css?v=<?= $versionAcceso ?>">
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="css/validacion-cuenta.css?v=<?= $versionAcceso ?>">
    <link rel="stylesheet" href="css/registro-cuenta.css?v=<?= $versionAcceso ?>">
    <?php if (!empty($accesoTelefono)): // M5 C4a: solo las páginas con teléfono cargan la bandera. ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.10/build/css/intlTelInput.css">
    <?php endif; ?>
