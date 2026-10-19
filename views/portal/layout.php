<?php
require_once __DIR__ . '/../../config/App.php';
require_once __DIR__ . '/../../helpers/Csrf.php';
require_once __DIR__ . '/../../helpers/Contexto.php';
$v = App::assetVersion();

// Secciones del portal: una sola lista para el menú lateral y la barra inferior.
// M5 C2 (decisión 4 de «A — Revisión»): sin «Servicios»; «Alertas» vuelve con HU-5.7.
$secciones = [
    'home'     => ['Inicio', 'ri-home-5-line'],
    'citas'    => ['Citas', 'ri-calendar-todo-line'],
    'clinicas' => ['Clínicas', 'ri-hospital-line'],
    'account'  => ['Perfil', 'ri-user-3-line'],
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
    <!-- RNF-16 / accesibilidad: sin bloquear el zoom; viewport-fit para la muesca del iPhone. -->
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#FAF9FF">
    <title>Mi Mascota | Zooki</title>
    <link rel="icon" type="image/png" href="img/icon_blue.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/contexto.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <?php // M5 C4a (punto 11): teléfono con bandera, el mismo de Usuarios y Pacientes. ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.10/build/css/intlTelInput.css">
    <?php // M2 C8: alertas-medicas.css usa las variables compartidas de identidad (las mismas --z-* de portal/base.css). ?>
    <link rel="stylesheet" href="css/identidad.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/marca-clinica.css?v=<?= $v ?>">
    <?php // M5 C2: los componentes de Usuarios (tarjetas mini, etiquetas, pestañas), con los tokens del portal. ?>
    <link rel="stylesheet" href="css/usuarios.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/alertas-medicas.css?v=<?= $v ?>">
    <?php foreach (['base', 'navegacion', 'componentes', 'inicio', 'agenda', 'mascota', 'cuenta', 'ventanas', 'calendario', 'formularios'] as $modulo): ?>
    <link rel="stylesheet" href="css/portal/<?= $modulo ?>.css?v=<?= $v ?>">
    <?php endforeach; ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <meta name="csrf-token" content="<?= Csrf::token('default') ?>">
    <link rel="stylesheet" href="css/validacion-cuenta.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
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
                </button>
            <?php endforeach; ?>
        </nav>

        <?php
        // M5 C4a (punto 1): nombre, contexto (HU-T.17) y salir en una cuadrícula; el contexto en una
        // línea y «Cambiar de perfil» como botón del sistema, no un enlace suelto subrayado.
        $contextoRail = Contexto::actual();
        $nombreRail = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Propietario');
        ?>
        <div class="portal-rail__usuario">
            <span class="portal-rail__avatar" aria-hidden="true"><?= htmlspecialchars($iniciales) ?></span>
            <span class="portal-rail__nombre" title="<?= $nombreRail ?>"><?= $nombreRail ?></span>
            <?php if ($contextoRail !== null): ?>
                <?php // El portal es siempre el contexto de propietario: «Propietario» cabe junto a salir; el nombre completo, en el title. ?>
                <span class="portal-rail__contexto" title="<?= htmlspecialchars(Contexto::etiqueta($contextoRail)) ?>">Propietario</span>
            <?php endif; ?>
            <a href="index.php?action=logout" class="portal-rail__salir" title="Cerrar sesión" aria-label="Cerrar sesión">
                <i class="ri-logout-box-r-line" aria-hidden="true"></i>
            </a>
            <?php if (Contexto::puedeCambiar()): ?>
                <a href="index.php?action=seleccionar_contexto" class="portal-boton-texto portal-rail__cambiar">
                    <i class="ri-arrow-left-right-line" aria-hidden="true"></i> Cambiar de perfil
                </a>
            <?php endif; ?>
        </div>
    </aside>

    <main class="portal-main" id="contenido">
        <?php include $view; ?>
    </main>
</div>

<!-- Barra inferior: móvil y tablet (< 1024 px). -->
<nav class="mobile-nav" aria-label="Menú del portal">
    <div class="mobile-nav__items">
        <?php foreach (['home', 'citas'] as $id): [$etiqueta, $icono] = $secciones[$id]; ?>
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

        <?php foreach (['clinicas', 'account'] as $id): [$etiqueta, $icono] = $secciones[$id]; ?>
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
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.10/build/js/intlTelInput.min.js"></script>
<script src="js/telefono-internacional.js?v=<?= $v ?>"></script>
<script src="js/password-policy.js?v=<?= $v ?>"></script>
<script src="js/validacion-mascota.js?v=<?= $v ?>"></script>
<script src="js/portal.js?v=<?= $v ?>"></script>
<?php // M2 C1: alergias y alertas de la mascota, de solo lectura; portal.js la usa al abrir la ficha. ?>
<script src="js/alertas-medicas.js?v=<?= $v ?>"></script>
<script src="js/csrf.js?v=<?= $v ?>"></script>
<script src="js/sesion.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="js/interacciones.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php require __DIR__ . "/../partials/validacion_cuenta.php"; ?>
<script src="js/cuenta-password.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="https://accounts.google.com/gsi/client" async defer></script>
</body>
</html>
