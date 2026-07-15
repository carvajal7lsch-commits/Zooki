<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Mascota | Zooki</title>
    <link rel="icon" type="image/png" href="img/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/portal.css">
    <meta name="csrf-token" content="<?php require_once __DIR__ . '/../../helpers/Csrf.php'; echo Csrf::token('default'); ?>">
</head>
<body class="portal-body">
    <header class="portal-header">
        <div class="portal-header-inner">
            <a href="index.php?action=portal_propietario" class="portal-brand">
                <span class="portal-brand-icon"><i class="fas fa-paw"></i></span>
                <span class="portal-brand-text">
                    <strong>Zooki</strong>
                    <small>Portal de mascotas</small>
                </span>
            </a>
            <div class="portal-user">
                <span class="portal-user-name"><?php echo htmlspecialchars(explode(' ', trim($_SESSION['usuario_nombre'] ?? ''))[0]); ?></span>
                <a href="index.php?action=logout" class="portal-logout" title="Cerrar sesión">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </header>

    <main class="portal-main">
        <?php include $view; ?>
    </main>

    <footer class="portal-footer">
        <p>&copy; <?php echo date('Y'); ?> Zooki · Clínica veterinaria</p>
    </footer>

    <script src="js/portal.js"></script>
    <script src="js/csrf.js"></script>
</body>
</html>
