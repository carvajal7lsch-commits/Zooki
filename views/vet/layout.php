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
    <link rel="stylesheet" href="css/dashboard.css?v=3">
    <link rel="stylesheet" href="css/usuarios.css">
    <link rel="stylesheet" href="css/medical-module.css?v=11">
    <link rel="stylesheet" href="css/pill-sidebar.css">
    <?php if (($_GET['action'] ?? '') === 'mi_perfil'): ?>
    <link rel="stylesheet" href="css/perfil.css?v=1">
    <?php endif; ?>
    <link rel="stylesheet" href="css/dark-mode.css">
    <?php if (($_GET['action'] ?? '') === 'vet_agenda'): ?>
    <link rel="stylesheet" href="css/calendario.css?v=12">
    <?php endif; ?>
    <?php if (($_GET['action'] ?? '') === 'vet_atencion'): ?>
    <link rel="stylesheet" href="css/atencion.css?v=1">
    <?php endif; ?>
    <meta name="csrf-token" content="<?php require_once __DIR__ . '/../../helpers/Csrf.php'; echo Csrf::token('default'); ?>">
</head>
<body>
    <div id="global-loader"><div class="spinner"></div></div>

    <div class="dashboard-layout">

        <!-- ══ PILL SIDEBAR ════════════════════════════════════════════════════ -->
        <nav class="pill-sidebar">
            <a href="index.php?action=vet_area" class="pill-logo" title="Ir al inicio">
                <img src="img/icon_blue.png" alt="Zooki" class="pill-logo-img">
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
                <?= pillNavLink("index.php?action=mi_perfil", "fa-user-circle", $__action === "mi_perfil", "Mi perfil") ?>
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
                            'vet_agenda' => 'Calendario',
                            'vet_atencion' => '',
                            'mi_perfil' => 'Mi perfil'
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
                                <!-- HU-45: marcar todas como leidas -->
                                <button type="button" class="notif-mark-all" id="notifMarkAll" hidden>
                                    <i class="fas fa-check-double"></i> Marcar todas
                                </button>
                            </div>
                            <div id="pendingVaccinesList" class="notif-body"></div>
                        </div>
                    </div>
                    
                    <button class="help-btn">
                        <i class="far fa-question-circle"></i>
                    </button>

                    <div class="header-user-wrapper">
                        <a class="header-user" href="index.php?action=mi_perfil" title="Mi perfil">
                            <div class="header-user__avatar"><?= htmlspecialchars($__adminIniciales) ?></div>
                            <div class="header-user__info">
                                <span class="header-user__name"><?= htmlspecialchars(explode(" ", trim($__adminNombre))[0]) ?></span>
                                <span class="header-user__role">Veterinario</span>
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
                        <?php include "area.php"; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        const ZOOKI_ROLE = 2;
    </script>
    <!-- TR-02: helpers de aviso; debe ir antes de quien los usa -->
    <script src="js/avisos.js"></script>
    <script src="js/password-policy.js"></script>
    <script src="js/dashboard.js?v=5"></script>
    <script src="js/medical-module.js?v=14"></script>
    <script src="js/csrf.js"></script>
    <script src="js/extras.js"></script>
    <?php if (($_GET['action'] ?? '') === 'vet_atencion'): ?>
    <script src="js/atencion.js?v=1"></script>
    <?php endif; ?>
</body>
</html>
