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
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/dashboard.css?v=3">
    <link rel="stylesheet" href="css/usuarios.css">
    <link rel="stylesheet" href="css/pill-sidebar.css">
    <?php if (($_GET['action'] ?? '') === 'mi_perfil'): ?>
    <link rel="stylesheet" href="css/perfil.css?v=1">
    <?php endif; ?>
    <meta name="csrf-token" content="<?php require_once __DIR__ . '/../../helpers/Csrf.php'; echo Csrf::token('default'); ?>">
</head>
<body>
    <div id="global-loader"><div class="spinner"></div></div>

    <div class="dashboard-layout">

        <!-- ══ PILL SIDEBAR ════════════════════════════════════════════════════ -->
        <nav class="pill-sidebar">
            <a href="index.php?action=admin_panel" class="pill-logo" title="Ir al inicio">
                <img src="img/icon_blue.png" alt="Zooki" class="pill-logo-img">
            </a>

            <div class="pill-nav">
                <?php
                $__action = $_GET["action"] ?? "admin_panel";
                $__adminNombre = $_SESSION["usuario_nombre"] ?? "Administrador";
                $__adminRol = $_SESSION["usuario_rol"] ?? "Admin";
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
                function pillNavLink($href, $icon, $active)
                {
                    $cls = $active ? "pill-nav-item active" : "pill-nav-item";
                    return "<a href=\"$href\" class=\"$cls\"><i class=\"fas $icon\"></i></a>";
                }
                ?>

                <?= pillNavLink("index.php?action=admin_panel", "fa-th-large", $__action === "admin_panel") ?>
                <?= pillNavLink("index.php?action=admin_usuarios", "fa-users", $__action === "admin_usuarios") ?>
                <?= pillNavLink("index.php?action=admin_citas", "fa-calendar-alt", $__action === "admin_citas") ?>
                <?php // echo pillNavLink("index.php?action=admin_reportes", "fa-chart-pie", $__action === "admin_reportes"); ?>
                <?php // echo pillNavLink("index.php?action=admin_auditoria", "fa-shield-alt", $__action === "admin_auditoria"); ?>
                <?= pillNavLink("index.php?action=admin_configuracion", "fa-cog", $__action === "admin_configuracion") ?>
                <?= pillNavLink("index.php?action=mi_perfil", "fa-user-circle", $__action === "mi_perfil") ?>
            </div>

            <div class="pill-divider"></div>

            <a href="index.php?action=logout" class="pill-logout" title="Cerrar Sesión">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </nav>

        <!-- ══ CONTENIDO PRINCIPAL ════════════════════════════════════════ -->
        <main class="main-content">

            <!-- Header superior -->
            <header class="top-header">
                <div class="header-left">
                    <?php
                        $tituloModulo = "";
                        switch($__action) {
                            case "admin_usuarios": $tituloModulo = ""; break;
                            case "admin_citas": $tituloModulo = ""; break;
                            case "admin_reportes": $tituloModulo = "Reportes"; break;
                            case "admin_configuracion": $tituloModulo = "Configuración"; break;
                            case "admin_auditoria": $tituloModulo = "Auditoría"; break;
                            case "admin_panel": $tituloModulo = "Dashboard"; break;
                            case "mi_perfil": $tituloModulo = "Mi perfil"; break;
                            default: $tituloModulo = "Zooki"; break;
                        }
                    ?>
                    <h1 class="top-header-title"><?= $tituloModulo ?></h1>
                </div>
                <div class="header-right">
                    <div class="notifications-wrapper">
                        <button class="notif-btn" id="notifBell" onclick="toggleNotifications()">
                            <i class="far fa-bell"></i>
                            <span class="notif-badge" id="notifBadge" style="display:none;"></span>
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
                        <a class="header-user" href="index.php?action=mi_perfil" title="Mi perfil">
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

    <script>
        const ZOOKI_ROLE = 1;
    </script>
    <!-- TR-02: helpers de aviso; debe ir antes de quien los usa -->
    <script src="js/avisos.js"></script>
    <script src="js/password-policy.js"></script>
    <script src="js/dashboard.js?v=5"></script>
    <script src="js/csrf.js"></script>
    <script src="js/extras.js"></script>
</body>
</html>
