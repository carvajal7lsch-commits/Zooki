<?php require_once __DIR__ . '/../../config/App.php'; ?>
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
    <?php // M2 C8 (H5): variables de identidad (azul de marca y neutros del portal) para la consulta y el vínculo. ?>
    <link rel="stylesheet" href="css/identidad.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="css/styles.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="css/dashboard.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="css/usuarios.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="css/medical-module.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="css/alertas-medicas.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <?php // M2 C5a: síntomas y diagnósticos sugeridos de la consulta (modal y Atención). ?>
    <link rel="stylesheet" href="css/consulta-clinica.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="css/pill-sidebar.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="css/contexto.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <?php if (($_GET['action'] ?? '') === 'vet_pacientes'): ?>
    <link rel="stylesheet" href="css/pacientes.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if (($_GET['action'] ?? '') === 'mi_perfil'): ?>
    <link rel="stylesheet" href="css/perfil.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if (($_GET['action'] ?? '') === 'vet_agenda'): ?>
    <link rel="stylesheet" href="css/calendario.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="css/agenda-herramientas.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="css/triage-captura.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if (($_GET['action'] ?? '') === 'vet_atencion'): ?>
    <link rel="stylesheet" href="css/atencion.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if (!isset($content_view)): ?>
    <?php // HU-6.1: el Inicio del veterinario tiene su propia hoja (diseño aprobado 2026-10-22). ?>
    <link rel="stylesheet" href="css/inicio-vet.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <meta name="csrf-token" content="<?php require_once __DIR__ . '/../../helpers/Csrf.php'; echo Csrf::token('default'); ?>">
    <link rel="stylesheet" href="css/validacion-cuenta.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
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
                $__adminRol = Roles::nombre((int) Contexto::rolActivo());
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
        <main class="main-content<?= isset($content_view) ? '' : ' main-content--inicio' ?>">

            <!-- Header superior -->
            <header class="top-header<?= $__action === 'vet_pacientes' ? ' top-header--pacientes' : '' ?>">
                <div class="header-left">
                    <?php 
                        $moduleTitles = [
                            'vet_area' => 'Dashboard',
                            'vet_pacientes' => '',
                            'vet_agenda' => 'Calendario',
                            'vet_atencion' => '',
                            'mi_perfil' => 'Mi perfil'
                        ];
                        $currentTitle = $moduleTitles[$__action] ?? 'Panel Veterinario';
                    ?>
                    <?php if ($__action === 'vet_pacientes'): ?>
                        <?php require __DIR__ . '/partials/cabecera_pacientes.php'; ?>
                    <?php elseif (!isset($content_view) && isset($panel)): ?>
                        <div class="inicio-encabezado">
                            <p class="inicio-encabezado__fecha"><?= htmlspecialchars($panel['fecha']) ?></p>
                            <h1 class="inicio-encabezado__saludo"><?= htmlspecialchars($panel['saludo'] . ', ' . explode(" ", trim($__adminNombre))[0]) ?></h1>
                        </div>
                    <?php else: ?>
                        <h1 class="top-header-title"><?= htmlspecialchars($currentTitle) ?></h1>
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

    <!-- TR-02: helpers de aviso; debe ir antes de quien los usa -->
    <script src="js/avisos.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="js/password-policy.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="js/dashboard.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="js/selector-fechas.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="js/medical-module.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php // M2 C8 (H6): flechas, Enter y Esc en los buscadores con lista (síntomas, fármacos y nodos de alergias). ?>
    <script src="js/combobox.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php // M2 C5a: la consulta que comparten el modal y Atención: síntomas, diagnósticos sugeridos y avisos junto al campo. ?>
    <script src="js/consulta-sintomas.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="js/consulta-diagnosticos.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="js/consulta-tratamientos.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="js/consulta-formulario.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php // M2 C1: sección «Alergias y alertas» de la historia y su formulario. ?>
    <script src="js/paciente-compacto.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="js/alertas-medicas.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php // M5 C3: formularios de mascota con las reglas de ValidadorMascota y el propietario buscado en tiempo real. ?>
    <script src="js/validacion-mascota.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="js/propietario-mascota.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="js/csrf.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="js/sesion.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="js/extras.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php if (!isset($content_view)): ?>
    <script src="js/comando-agenda.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="js/inicio-vet.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endif; ?>
    <?php if (($_GET['action'] ?? '') === 'vet_atencion'): ?>
    <script src="js/atencion.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endif; ?>
<script src="js/interacciones.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php require __DIR__ . "/../partials/validacion_cuenta.php"; ?>
<script src="https://accounts.google.com/gsi/client" async defer></script>
</body>
</html>
