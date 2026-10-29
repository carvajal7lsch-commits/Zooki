<?php
require_once __DIR__ . '/../../config/App.php';
require_once __DIR__ . '/../../helpers/ValidadorCuenta.php';
require_once __DIR__ . '/../../helpers/ValidadorTelefono.php';
// HU-T.5, RE-4.11.5 y RE-4.12.1: misma presentación, permisos del contexto.
$e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$partes = array_values(array_filter(explode(' ', trim($perfil['nombre_completo'] ?? ''))));
$iniciales = mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1) . mb_substr($partes[1] ?? '', 0, 1)) ?: 'U';
$esVetPerfil = Contexto::rolActivo() === Roles::VETERINARIO;
$nombreClinicaPerfil = $_SESSION['contexto']['clinica'] ?? '';
$cuentaSeleccionadaPerfil = true;
?>
<div class="perfil-secciones ajustes ajustes--vet" data-perfil-secciones data-ajustes>
    <aside class="perfil-lateral ajustes-lateral">
        <?php require __DIR__ . '/identidad.php'; ?>
        <nav class="ajustes-menu" data-config-tabs aria-label="Secciones de Mi perfil" aria-orientation="vertical">
            <?php require __DIR__ . '/menu_cuenta.php'; ?>
            <?php if ($esVetPerfil): ?>
            <p class="ajustes-menu__grupo" id="ajustesGrupoTrabajo">Mi trabajo</p>
            <div class="ajustes-menu__caja">
                <div class="ajustes-menu__lista" role="tablist" aria-labelledby="ajustesGrupoTrabajo" aria-orientation="vertical">
                    <button type="button" role="tab" class="ajustes-menu__item" id="perfilTabHorarios" data-config-tab="horarios" aria-controls="perfilHorarios" aria-selected="false" tabindex="-1"><i class="fas fa-clock" aria-hidden="true"></i><span>Mi horario</span><span class="perfil-indicador" data-perfil-pendiente="horarios" aria-label="Propuesta de horario pendiente" hidden></span><i class="fas fa-chevron-right ajustes-menu__flecha" aria-hidden="true"></i></button>
                    <button type="button" role="tab" class="ajustes-menu__item" id="perfilTabAusencias" data-config-tab="ausencias" aria-controls="perfilAusencias" aria-selected="false" tabindex="-1"><i class="fas fa-calendar-times" aria-hidden="true"></i><span>Ausencias</span><span class="perfil-indicador" data-perfil-pendiente="ausencias" aria-label="Citas pendientes de reajuste" hidden></span><i class="fas fa-chevron-right ajustes-menu__flecha" aria-hidden="true"></i></button>
                </div>
            </div>
            <?php endif; ?>
        </nav>
        <a href="index.php?action=logout" class="ajustes-salir"><i class="fas fa-sign-out-alt" aria-hidden="true"></i> Cerrar sesión</a>
    </aside>
    <div class="perfil-contenido ajustes-contenido">
        <div class="ajustes-seccion">
            <button type="button" class="ajustes-seccion__volver" data-ajustes-volver aria-label="Volver a Mi perfil"><i class="fas fa-arrow-left" aria-hidden="true"></i><span>Mi perfil</span></button>
            <h2 class="ajustes-seccion__titulo" data-ajustes-titulo>Mi cuenta</h2>
        </div>
        <div class="perfil ajustes-cuenta">
            <?php require __DIR__ . '/paneles_cuenta.php'; ?>
        </div>
        <?php if ($esVetPerfil): ?>
        <div class="config-agenda-hoja" data-perfil-horarios>
            <?php require __DIR__ . '/../partials/coordinacion_horarios.php'; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<script src="js/ajustes.js?v=<?= $e(App::assetVersion()) ?>"></script>
<script src="js/dialogos-zooki.js?v=<?= $e(App::assetVersion()) ?>" defer></script>
<script src="js/perfil.js?v=<?= $e(App::assetVersion()) ?>"></script>
<script src="js/ajustes-horarios.js?v=<?= $e(App::assetVersion()) ?>" defer></script>
