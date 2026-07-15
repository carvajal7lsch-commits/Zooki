<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zooki - Recepción</title>

    <!-- Fuentes e iconos -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="img/icon_blue.png">

    <!-- Librerías externas -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/pill-sidebar.css">
    <link rel="stylesheet" href="css/dark-mode.css">
    <meta name="csrf-token" content="<?php require_once __DIR__ . '/../../helpers/Csrf.php'; echo Csrf::token('default'); ?>">
</head>
<body>
    <div id="global-loader"><div class="spinner"></div></div>

    <div class="dashboard-layout">

        <!-- ══ PILL SIDEBAR ════════════════════════════════════════════════════ -->
        <nav class="pill-sidebar">
            <a href="index.php?action=reception_dashboard" class="pill-logo" style="margin-bottom: 1.5rem; text-decoration: none;">
                <img src="img/logo.png" alt="Zooki" style="width: 65px; height: auto; object-fit: contain; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.05)); transition: transform 0.3s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
            </a>

            <div class="pill-nav">
                <?php
                $__action = $_GET["action"] ?? "reception_dashboard";
                function pillNavLink($href, $icon, $active)
                {
                    $cls = $active ? "pill-nav-item active" : "pill-nav-item";
                    return "<a href=\"$href\" class=\"$cls\"><i class=\"fas $icon\"></i></a>";
                }
                ?>

                <?= pillNavLink("index.php?action=reception_dashboard", "fa-home", $__action === "reception_dashboard") ?>
                <?= pillNavLink("index.php?action=reception_agenda", "fa-calendar-alt", $__action === "reception_agenda") ?>
                <?= pillNavLink("index.php?action=reception_nueva_cita", "fa-plus-circle", $__action === "reception_nueva_cita") ?>
                <?= pillNavLink("index.php?action=reception_pacientes", "fa-paw", $__action === "reception_pacientes") ?>
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
                            </div>
                            <div id="pendingVaccinesList" class="notif-body"></div>
                        </div>
                    </div>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION["usuario_nombre"]); ?>&background=F59E0B&color=fff"
                         style="width:40px;height:40px;border-radius:50%;flex-shrink:0;" alt="Perfil">
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
                        <?php include "dashboard.php"; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        const ZOOKI_ROLE = 3;
    </script>
    <script src="js/dashboard.js"></script>
    <script src="js/csrf.js"></script>
    <script src="js/extras.js"></script>
</body>
</html>
