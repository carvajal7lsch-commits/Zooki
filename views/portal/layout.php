<?php
require_once __DIR__ . '/../../config/App.php';
require_once __DIR__ . '/../../helpers/Csrf.php';
$v = App::assetVersion();

// Secciones del portal: una sola lista para el menú lateral y la barra inferior.
$secciones = [
    'home'          => ['Inicio', 'ri-home-5-line'],
    'explore'       => ['Servicios', 'ri-compass-3-line'],
    'agenda'        => ['Agenda', 'ri-calendar-todo-line'],
    'notifications' => ['Recordatorios', 'ri-notification-3-line'],
    'account'       => ['Perfil', 'ri-user-3-line'],
];
$iniciales = '';
foreach (array_slice(preg_split('/\s+/', trim($_SESSION['usuario_nombre'] ?? '')), 0, 2) as $parte) {
    $iniciales .= mb_strtoupper(mb_substr($parte, 0, 1));
}
$iniciales = $iniciales ?: 'U';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <!-- RNF-07 / accesibilidad: sin bloquear el zoom; viewport-fit para la muesca del iPhone. -->
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#FAF9FF">
    <title>Mi Mascota | Zooki</title>
    <link rel="icon" type="image/png" href="img/icon_blue.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <?php foreach (['base', 'navegacion', 'inicio', 'servicios', 'agenda', 'mascota', 'cuenta', 'ventanas', 'calendario', 'formularios'] as $modulo): ?>
    <link rel="stylesheet" href="css/portal/<?= $modulo ?>.css?v=<?= $v ?>">
    <?php endforeach; ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <meta name="csrf-token" content="<?= Csrf::token('default') ?>">
</head>
<body class="portal-body">

<div class="portal-shell">
    <!-- Menú lateral: escritorio (≥ 1024 px). En móvil y tablet se usa la barra inferior. -->
    <aside class="portal-rail" aria-label="Menú del portal">
        <a class="portal-rail__marca" href="index.php?action=portal_propietario">
            <img src="img/icon_blue.png" alt="" width="36" height="36">
            <span>Zooki</span>
        </a>

        <button type="button" class="portal-rail__agendar" data-agendar>
            <i class="ri-calendar-event-line" aria-hidden="true"></i> Agendar cita
        </button>

        <nav class="portal-rail__nav">
            <?php foreach ($secciones as $id => [$etiqueta, $icono]): ?>
                <button type="button" class="portal-rail__item<?= $id === 'home' ? ' active' : '' ?>" data-nav="<?= $id ?>" <?= $id === 'home' ? 'aria-current="page"' : '' ?>>
                    <i class="<?= $icono ?>" aria-hidden="true"></i>
                    <span><?= $etiqueta ?></span>
                    <?php if ($id === 'notifications'): ?><span class="portal-rail__punto" data-badge-alertas hidden></span><?php endif; ?>
                </button>
            <?php endforeach; ?>
        </nav>

        <div class="portal-rail__usuario">
            <span class="portal-rail__avatar" aria-hidden="true"><?= htmlspecialchars($iniciales) ?></span>
            <span class="portal-rail__nombre"><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Propietario') ?></span>
            <a href="index.php?action=logout" class="portal-rail__salir" title="Cerrar sesión" aria-label="Cerrar sesión">
                <i class="ri-logout-box-r-line" aria-hidden="true"></i>
            </a>
        </div>
    </aside>

    <main class="portal-main" id="contenido">
        <?php include $view; ?>
    </main>
</div>

<!-- Barra inferior: móvil y tablet (< 1024 px). -->
<nav class="mobile-nav" aria-label="Menú del portal">
    <div class="mobile-nav__items">
        <?php foreach (['home', 'explore'] as $id): [$etiqueta, $icono] = $secciones[$id]; ?>
            <button type="button" class="mobile-nav-item<?= $id === 'home' ? ' active' : '' ?>" data-nav="<?= $id ?>" <?= $id === 'home' ? 'aria-current="page"' : '' ?>>
                <i class="<?= $icono ?>" aria-hidden="true"></i><span><?= $etiqueta ?></span>
            </button>
        <?php endforeach; ?>

        <div class="mobile-nav-item--center">
            <button type="button" class="center-fab-button" data-agendar aria-label="Agendar cita">
                <i class="ri-add-line" aria-hidden="true"></i>
            </button>
            <span class="center-fab-label" aria-hidden="true">Agendar</span>
        </div>

        <?php foreach (['agenda', 'account'] as $id): [$etiqueta, $icono] = $secciones[$id]; ?>
            <button type="button" class="mobile-nav-item" data-nav="<?= $id ?>">
                <i class="<?= $icono ?>" aria-hidden="true"></i><span><?= $etiqueta ?></span>
            </button>
        <?php endforeach; ?>
    </div>
</nav>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<!-- TR-02: helpers de aviso; debe ir antes de quien los usa -->
<script src="js/avisos.js?v=<?= $v ?>"></script>
<script src="js/password-policy.js?v=<?= $v ?>"></script>
<script src="js/portal.js?v=<?= $v ?>"></script>
<script src="js/csrf.js?v=<?= $v ?>"></script>
</body>
</html>
