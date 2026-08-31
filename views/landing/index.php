<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/App.php';

// Estado de sesion compartido por los parciales para decidir el CTA.
$lp_logged_in = isset($_SESSION['usuario_doc']);
$lp_cta_url   = $lp_logged_in ? 'index.php?action=dashboard' : 'index.php?action=login';
$lp_cta_text  = $lp_logged_in ? 'Ir al panel' : 'Iniciar sesión';
$lp_version   = App::assetVersion();

// URL absoluta del sitio, necesaria para canonical y metadatos sociales.
$lp_scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$lp_host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
$lp_base_url  = $lp_scheme . '://' . $lp_host . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\') . '/';
$lp_share_img = $lp_base_url . 'img/logo_conlema.png';

$lp_title = 'Zooki | Software de gestion para clinicas veterinarias';
$lp_desc  = 'Zooki digitaliza la historia clinica de cada mascota, ordena la agenda de tu clinica veterinaria, '
          . 'automatiza los recordatorios de vacunacion y le da a cada propietario un portal para hacer seguimiento.';
?>
<!DOCTYPE html>
<html lang="es-CO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- ===== SEO principal ===== -->
    <title><?php echo $lp_title; ?></title>
    <meta name="description" content="<?php echo $lp_desc; ?>">
    <meta name="author" content="Zooki">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#0052FF">
    <link rel="canonical" href="<?php echo htmlspecialchars($lp_base_url, ENT_QUOTES); ?>">

    <!-- ===== Open Graph (Facebook, WhatsApp, LinkedIn) ===== -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Zooki">
    <meta property="og:locale" content="es_CO">
    <meta property="og:title" content="<?php echo $lp_title; ?>">
    <meta property="og:description" content="<?php echo $lp_desc; ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($lp_base_url, ENT_QUOTES); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($lp_share_img, ENT_QUOTES); ?>">
    <meta property="og:image:alt" content="Logotipo de Zooki con el lema Cuidamos a quienes amas">

    <!-- ===== Twitter Card ===== -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $lp_title; ?>">
    <meta name="twitter:description" content="<?php echo $lp_desc; ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($lp_share_img, ENT_QUOTES); ?>">

    <!-- ===== Iconos ===== -->
    <link rel="icon" type="image/png" href="img/icon_blue.png">
    <link rel="apple-touch-icon" href="img/icon_blue.png">

    <!-- ===== Tipografias: Outfit (titulares) + Inter (cuerpo) ===== -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">

    <!-- ===== Iconografia ===== -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- ===== Estilos de la landing (modulares y autonomos) ===== -->
    <link rel="stylesheet" href="css/landing.css?v=<?php echo $lp_version; ?>">

    <!-- ===== Datos estructurados para buscadores ===== -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "name": "Zooki",
        "applicationCategory": "HealthApplication",
        "operatingSystem": "Navegador web",
        "softwareVersion": "<?php echo $lp_version; ?>",
        "description": "<?php echo $lp_desc; ?>",
        "url": "<?php echo htmlspecialchars($lp_base_url, ENT_QUOTES); ?>",
        "image": "<?php echo htmlspecialchars($lp_share_img, ENT_QUOTES); ?>",
        "inLanguage": "es-CO",
        "author": {
            "@type": "Organization",
            "name": "Zooki",
            "slogan": "Cuidamos a quienes amas",
            "areaServed": {
                "@type": "Place",
                "name": "Neiva, Huila, Colombia"
            }
        }
    }
    </script>
</head>
<body class="landing-body">

    <a class="lp-skip-link" href="#contenido">Saltar al contenido principal</a>

    <?php include __DIR__ . '/partials/nav.php'; ?>

    <main id="contenido">
        <?php
        include __DIR__ . '/partials/hero.php';
        include __DIR__ . '/partials/problema.php';
        include __DIR__ . '/partials/modulos.php';
        include __DIR__ . '/partials/roles.php';
        include __DIR__ . '/partials/pacientes.php';
        include __DIR__ . '/partials/seguridad.php';
        include __DIR__ . '/partials/faq.php';
        ?>
    </main>

    <?php include __DIR__ . '/partials/footer.php'; ?>

    <script src="js/landing.js?v=<?php echo $lp_version; ?>"></script>
</body>
</html>
