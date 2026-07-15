<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zooki - Área Clínica</title>

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
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/usuarios.css">
    <link rel="stylesheet" href="css/medical-module.css?v=6">
    <link rel="stylesheet" href="css/pill-sidebar.css">
    <link rel="stylesheet" href="css/dark-mode.css">
    <meta name="csrf-token" content="<?php require_once __DIR__ . '/../../helpers/Csrf.php'; echo Csrf::token('default'); ?>">
</head>
<body>
    <div id="global-loader"><div class="spinner"></div></div>

    <div class="dashboard-layout">

        <!-- ══ PILL SIDEBAR ════════════════════════════════════════════════════ -->
        <nav class="pill-sidebar">
            <a href="index.php?action=vet_area" class="pill-logo mb-2">
                <img src="img/logo_conlema.png" alt="Zooki" class="logo-sidebar-img">
            </a>

            <div class="pill-nav">
                <?php
                $__action = $_GET["action"] ?? "vet_area";
                $__adminNombre = $_SESSION["usuario_nombre"] ?? "Veterinario";
                $__adminRol = $_SESSION["usuario_rol"] ?? "Vet";
                $__adminIniciales = "";
                foreach (array_filter(explode(" ", trim($__adminNombre))) as $__i => $__parte) {
                    if ($__i > 1) {
                        break;
                    }
                    $__adminIniciales .= strtoupper(substr($__parte, 0, 1));
                }
                if ($__adminIniciales === "") {
                    $__adminIniciales = "V";
                }
                function pillNavLink($href, $icon, $active, $title)
                {
                    $cls = $active ? "pill-nav-item active" : "pill-nav-item";
                    return "<a href=\"$href\" class=\"$cls\" title=\"$title\"><i class=\"fas $icon\"></i></a>";
                }
                ?>

                <?= pillNavLink("index.php?action=vet_area", "fa-home", $__action === "vet_area", "Inicio / Dashboard") ?>
                <?= pillNavLink("index.php?action=vet_consultas", "fa-file-medical-alt", $__action === "vet_consultas", "Consultas Médicas") ?>
                <?= pillNavLink("index.php?action=vet_pacientes", "fa-paw", $__action === "vet_pacientes", "Pacientes") ?>
                <?= pillNavLink("index.php?action=vet_agenda", "fa-calendar-alt", $__action === "vet_agenda", "Calendario") ?>
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
                        $moduleTitles = [
                            'vet_area' => 'Dashboard General',
                            'vet_consultas' => '',
                            'vet_pacientes' => '',
                            'vet_agenda' => 'Calendario'
                        ];
                        $currentTitle = $moduleTitles[$__action] ?? 'Panel Veterinario';
                    ?>
                    <h1 class="top-header-title"><?= htmlspecialchars($currentTitle) ?></h1>
                </div>
                <div class="header-right">
                    <div class="notifications-wrapper">
                        <button class="notif-btn" id="notifBell" onclick="toggleNotifications()">
                            <i class="far fa-bell"></i>
                            <span class="notif-badge" id="notifBadge" style="display:none;"></span>
                        </button>
                        <div class="notif-dropdown" id="notifDropdown">
                            <div class="notif-header">
                                <h3>Alertas Clínicas</h3>
                                <span id="notifCount">0 nuevas</span>
                            </div>
                            <div id="pendingVaccinesList" class="notif-body"></div>
                        </div>
                    </div>
                    
                    <button class="help-btn">
                        <i class="far fa-question-circle"></i>
                    </button>

                    <div class="header-user-wrapper">
                        <div class="header-user" title="<?= htmlspecialchars($__adminNombre) ?>" onclick="toggleProfileMenu()">
                            <div class="header-user__avatar"><?= htmlspecialchars($__adminIniciales) ?></div>
                            <div class="header-user__info">
                                <span class="header-user__name"><?= htmlspecialchars(explode(" ", trim($__adminNombre))[0]) ?></span>
                                <span class="header-user__role">Veterinario</span>
                            </div>
                        </div>
                        <div class="profile-dropdown" id="profileDropdown" style="display:none;">
                            <a href="#" onclick="abrirPersonalizarPerfil(event)"><i class="fas fa-lock"></i> Cambiar Contraseña</a>
                            <div class="profile-dropdown-divider"></div>
                            <a href="index.php?action=logout" class="text-danger"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
                        </div>
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
                        <?php include "area.php"; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        const ZOOKI_ROLE = 2;
    </script>
    <script src="js/dashboard.js"></script>
    <script src="js/medical-module.js?v=11"></script>
    <script src="js/csrf.js"></script>
    <script src="js/extras.js"></script>
</body>
</html>
