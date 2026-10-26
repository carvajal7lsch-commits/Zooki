<?php require_once __DIR__ . '/../../config/App.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zooki</title>

    <!-- Fuentes e iconos -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" type="image/png" href="img/icon_blue.png">

    <!-- Librerías externas -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Estilos del sistema -->
    <link rel="stylesheet" href="css/styles.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="css/identidad.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="css/dashboard.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="css/usuarios.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="css/pill-sidebar.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="css/contexto.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="css/encabezado-pagina.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="css/dialogos-zooki.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <?php if (in_array($_GET['action'] ?? '', ['admin_suscripcion', 'suscripcion_retorno'], true)): ?>
    <link rel="stylesheet" href="css/suscripcion.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if (($_GET['action'] ?? '') === 'admin_configuracion'): ?>
    <link rel="stylesheet" href="css/perfil.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <meta name="csrf-token" content="<?php require_once __DIR__ . '/../../helpers/Csrf.php'; echo Csrf::token('default'); ?>">
    <?php if (($_GET['action'] ?? '') === 'admin_configuracion'): ?>
    <link rel="stylesheet" href="css/horarios.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="css/configuracion-clinica.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="css/marca-clinica.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="css/ajustes.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if (!isset($content_view)): ?>
    <?php // HU-6.2: el Inicio del administrador, encima de las piezas comunes con el del veterinario (diseño aprobado 2026-10-24). ?>
    <link rel="stylesheet" href="css/inicio-comun.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="css/inicio-admin.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="css/validacion-cuenta.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <div id="global-loader"><div class="spinner"></div></div>

    <div class="dashboard-layout">

        <!-- ══ PILL SIDEBAR ════════════════════════════════════════════════════ -->
        <?php
        $__action = $_GET["action"] ?? "admin_panel";
        $__adminNombre = $_SESSION["usuario_nombre"] ?? "Administrador";
        $__adminRol = Roles::nombre((int) Contexto::rolActivo());
        $__adminIniciales = "";
        foreach (array_filter(explode(" ", trim($__adminNombre))) as $__i => $__parte) {
            if ($__i > 1) {
                break;
            }
            $__adminIniciales .= strtoupper(substr($__parte, 0, 1));
        }
        if ($__adminIniciales === "") {
            $__adminIniciales = "A";
        }
        // Rediseño (2026-10-26): Configuración y Mi perfil son una sola pantalla, «Ajustes». Caben cinco destinos
        // sin «Más» y Cerrar sesión vive al final de Ajustes en el celular.
        $barraInicio = "index.php?action=admin_panel";
        $barraItems = [
            ["href" => "index.php?action=admin_panel", "icono" => "fas fa-th-large", "titulo" => "Inicio", "corto" => "Inicio", "activo" => $__action === "admin_panel", "principal" => true],
            ["href" => "index.php?action=admin_usuarios", "icono" => "fas fa-users", "titulo" => "Usuarios", "corto" => "Usuarios", "activo" => $__action === "admin_usuarios", "principal" => true],
            ["href" => "index.php?action=admin_citas", "icono" => "fas fa-calendar-alt", "titulo" => "Citas", "corto" => "Citas", "activo" => $__action === "admin_citas", "principal" => true],
            ["href" => "index.php?action=admin_auditoria", "icono" => "fas fa-shield-alt", "titulo" => "Auditoría", "corto" => "Auditoría", "activo" => $__action === "admin_auditoria", "principal" => true],
            ["href" => "index.php?action=admin_configuracion", "icono" => "fas fa-cog", "titulo" => "Ajustes", "corto" => "Ajustes", "activo" => in_array($__action, ["admin_configuracion", "admin_suscripcion", "suscripcion_retorno"], true), "principal" => true],
        ];
        $barraSalirEnPantalla = true;
        include __DIR__ . "/../partials/barra_navegacion.php";
        ?>

        <!-- ══ CONTENIDO PRINCIPAL ════════════════════════════════════════ -->
        <main class="main-content<?= isset($content_view) ? '' : ' main-content--inicio' ?>">

            <!-- Header superior -->
            <header class="top-header">
                <div class="header-left">
                    <?php
                        $tituloModulo = "";
                        switch($__action) {
                            case "admin_usuarios": $tituloModulo = ""; break;
                            case "admin_citas": $tituloModulo = ""; break;
                            case "admin_configuracion": $tituloModulo = "Ajustes"; break;
                            case "admin_auditoria": $tituloModulo = "Auditoría"; break;
                            case "admin_panel": $tituloModulo = "Dashboard"; break;
                            default: $tituloModulo = "Zooki"; break;
                        }
                    ?>
                    <?php if (!isset($content_view) && isset($panel)): ?>
                        <div class="inicio-encabezado">
                            <p class="inicio-encabezado__fecha"><?= htmlspecialchars($panel['fecha']) ?></p>
                            <h1 class="inicio-encabezado__saludo"><?= htmlspecialchars($panel['saludo'] . ', ' . explode(" ", trim($__adminNombre))[0]) ?></h1>
                        </div>
                    <?php elseif (isset($encabezadoPagina)): ?>
                        <?php include __DIR__ . '/../partials/encabezado_pagina.php'; ?>
                    <?php else: ?>
                        <h1 class="top-header-title"><?= $tituloModulo ?></h1>
                    <?php endif; ?>
                </div>
                <div class="header-right">
                    <?php include __DIR__ . "/../partials/contexto_actual.php"; ?>
                    <div class="notifications-wrapper">
                        <button class="notif-btn" id="notifBell">
                            <i class="far fa-bell"></i>
                            <span class="notif-badge" id="notifBadge" hidden></span>
                        </button>
                        <div class="notif-dropdown" id="notifDropdown">
                            <div class="notif-header">
                                <h3>Notificaciones</h3>
                                <span id="notifCount">0 nuevas</span>
                                <!-- HU-45: marcar todas como leidas -->
                                <button type="button" class="notif-mark-all" id="notifMarkAll" hidden>
                                    <i class="fas fa-check-double"></i> Marcar todas
                                </button>
                            </div>
                            <div id="pendingVaccinesList" class="notif-body"></div>
                        </div>
                    </div>
                    <div class="header-user-wrapper">
                        <a class="header-user" href="index.php?action=admin_configuracion#cuenta" title="Mi cuenta">
                            <div class="header-user__avatar"><?= htmlspecialchars($__adminIniciales) ?></div>
                            <div class="header-user__info">
                                <span class="header-user__name"><?= htmlspecialchars(explode(" ", trim($__adminNombre))[0]) ?></span>
                                <span class="header-user__role"><?= htmlspecialchars($__adminRol) ?></span>
                            </div>
                        </a>
                    </div>
                </div>
            </header>

            <!-- Cuerpo del contenido -->
            <div class="content-body">
                <?php if (isset($content_view)): ?>
                    <div class="content-wrapper">
                        <?php include $content_view; ?>
                    </div>
                <?php else: ?>
                    <div class="content-wrapper">
                        <?php include "panel.php"; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- TR-02: helpers de aviso; debe ir antes de quien los usa -->
    <script src="js/avisos.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="js/password-policy.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="js/dashboard.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="js/csrf.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="js/sesion.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="js/extras.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="js/dialogos-zooki.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php if (in_array($_GET['action'] ?? '', ['admin_suscripcion', 'suscripcion_retorno'], true)): ?>
    <?php if (!empty($pagoDisponible)): ?>
    <script src="https://checkout.wompi.co/widget.js"></script>
    <?php endif; ?>
    <script src="js/suscripcion.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="js/suscripcion-pantalla.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="js/fuente-suscripcion.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endif; ?>
    <?php if (!isset($content_view)): ?>
    <script src="js/inicio-comun.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="js/inicio-admin.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endif; ?>
<script src="js/interacciones.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php require __DIR__ . "/../partials/validacion_cuenta.php"; ?>
<script src="https://accounts.google.com/gsi/client" async defer></script>
</body>
</html>
