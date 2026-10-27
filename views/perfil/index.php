<?php
require_once __DIR__ . '/../../config/App.php';
require_once __DIR__ . '/../../helpers/ValidadorCuenta.php';
/**
 * HU-42 / HU-39 — Panel "Mi perfil" del personal de la clínica.
 *
 * Lo pinta PerfilController::mostrar() dentro del layout del rol, con:
 * $perfil (cuenta en sesión), $rolNombre, $cuentaGoogle, $pideActual y
 * $cuenta (miembro desde, acceso anterior, intentos fallidos y actividad).
 */
require_once __DIR__ . '/../../helpers/ValidadorTelefono.php';
$e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$partes = array_values(array_filter(explode(' ', trim($perfil['nombre_completo'] ?? ''))));
$iniciales = mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1) . mb_substr($partes[1] ?? '', 0, 1)) ?: 'U';
$esVetPerfil = Contexto::rolActivo() === Roles::VETERINARIO;
?>
<div class="perfil perfil-secciones ajustes ajustes--vet" data-perfil-secciones data-ajustes>
    <aside class="perfil-lateral ajustes-lateral">

    <section class="perfil-hero ajustes-identidad">
        <div class="perfil-hero__avatar" aria-hidden="true"><?= $e($iniciales) ?></div>
        <div class="perfil-hero__info">
            <span class="perfil-hero__rol"><?= $e($rolNombre) ?></span>
            <h2 class="perfil-hero__nombre"><?= $e($perfil['nombre_completo']) ?></h2>
            <ul class="perfil-hero__datos">
                <li><i class="far fa-envelope"></i> <span id="perfilEmailHero"><?= $e($perfil['email']) ?></span></li>
                <?php if ($cuenta['miembro_desde']): ?>
                    <li><i class="far fa-calendar"></i> Miembro desde <?= $e($cuenta['miembro_desde']) ?></li>
                <?php endif; ?>
                <?php if ($cuenta['acceso_anterior']): ?>
                    <li><i class="far fa-clock"></i> Acceso anterior: <?= $e($cuenta['acceso_anterior']) ?></li>
                <?php endif; ?>
                <?php if ($cuentaGoogle): ?>
                    <li><i class="fab fa-google"></i> Sesión iniciada con Google</li>
                <?php endif; ?>
            </ul>
        </div>
    </section>
    <nav class="perfil-menu ajustes-menu__caja" data-config-tabs role="tablist" aria-label="Secciones de Mi perfil" aria-orientation="vertical">
        <button type="button" role="tab" id="perfilTabCuenta" data-config-tab="cuenta" aria-controls="perfilCuenta" aria-selected="true" aria-label="Mi cuenta"><i class="fas fa-user" aria-hidden="true"></i><span class="perfil-tab-texto"><span class="perfil-tab-largo">Mi cuenta</span><span class="perfil-tab-corto">Cuenta</span></span></button>
        <button type="button" role="tab" id="perfilTabSeguridad" data-config-tab="seguridad" aria-controls="seguridad" aria-selected="false" tabindex="-1" aria-label="Seguridad"><i class="fas fa-shield-alt" aria-hidden="true"></i><span class="perfil-tab-texto">Seguridad</span></button>
        <?php if ($esVetPerfil): ?>
        <button type="button" role="tab" id="perfilTabHorarios" data-config-tab="horarios" aria-controls="perfilHorarios" aria-selected="false" tabindex="-1" aria-label="Mi horario"><i class="fas fa-clock" aria-hidden="true"></i><span class="perfil-tab-texto"><span class="perfil-tab-largo">Mi horario</span><span class="perfil-tab-corto">Horario</span></span><span class="perfil-indicador" data-perfil-pendiente="horarios" aria-label="Propuesta de horario pendiente" hidden></span></button>
        <button type="button" role="tab" id="perfilTabAusencias" data-config-tab="ausencias" aria-controls="perfilAusencias" aria-selected="false" tabindex="-1" aria-label="Ausencias"><i class="fas fa-calendar-times" aria-hidden="true"></i><span class="perfil-tab-texto">Ausencias</span><span class="perfil-indicador" data-perfil-pendiente="ausencias" aria-label="Citas pendientes de reajuste" hidden></span></button>
        <?php endif; ?>
    </nav>
    <a href="index.php?action=logout" class="perfil-btn perfil-btn--danger perfil-salir ajustes-salir"><i class="fas fa-sign-out-alt" aria-hidden="true"></i> Cerrar sesión</a>
    </aside>
    <div class="perfil-contenido ajustes-contenido">
    <div class="ajustes-seccion">
        <button type="button" class="ajustes-seccion__volver" data-ajustes-volver aria-label="Volver a Mi perfil"><i class="fas fa-arrow-left" aria-hidden="true"></i><span>Mi perfil</span></button>
        <h2 class="ajustes-seccion__titulo" data-ajustes-titulo>Mi cuenta</h2>
    </div>
    <?php require __DIR__ . '/paneles_cuenta.php'; ?>
    <?php if ($esVetPerfil): ?>
        <div class="config-agenda-hoja">
        <?php require __DIR__ . '/../partials/coordinacion_horarios.php'; ?>
        </div>
    <?php endif; ?>
    </div>
</div>
<script src="js/ajustes.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="js/dialogos-zooki.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>" defer></script>
<script src="js/perfil.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
