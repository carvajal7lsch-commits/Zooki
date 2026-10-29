<?php
// HU-T.5/T.17, RNF-16: mismo contexto y permisos; menú de cuenta compartido por admin/vet.
// Mockup aprobado (2026-10-27, specs/referencias/cabecera-notificaciones): clínica, campana y avatar de 40 px.
$esAdminCabecera = Contexto::rolActivo() === Roles::ADMIN;
$enlaceCuentaCabecera = $esAdminCabecera ? 'admin_configuracion#cuenta' : 'mi_perfil';
$eCabecera = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$contextoCabecera = Contexto::actual();
$clinicaCabecera = (string) ($contextoCabecera['clinica'] ?? 'Clínica');
$palabrasCabecera = preg_split('/\s+/u', trim($clinicaCabecera), -1, PREG_SPLIT_NO_EMPTY);
$inicialesClinicaCabecera = mb_strtoupper(implode('', array_map(static fn ($palabra): string => mb_substr($palabra, 0, 1), array_slice($palabrasCabecera, 0, 2))));
$correoCabecera = isset($perfil) && (int) ($perfil['id_usuario'] ?? 0) === Contexto::idUsuario() ? (string) ($perfil['email'] ?? '') : '';
$puedeCambiarCabecera = Contexto::puedeCambiar();
?>
<div class="cabecera-cuenta" data-cabecera-cuenta>
    <div class="header-right" id="cabeceraCuentaPanel">
        <div data-clinica-escritorio>
            <?php if ($puedeCambiarCabecera): ?>
            <a href="index.php?action=seleccionar_contexto" class="cabecera-clinica" data-clinica-cuenta aria-label="<?= $eCabecera($clinicaCabecera . ', ' . $__adminRol . '. Cambiar de clínica o rol') ?>">
            <?php else: ?>
            <span class="cabecera-clinica" data-clinica-cuenta>
            <?php endif; ?>
                <span class="cabecera-clinica__avatar" aria-hidden="true"><?= $eCabecera($inicialesClinicaCabecera) ?></span>
                <span class="cabecera-clinica__texto"><strong><?= $eCabecera($clinicaCabecera) ?></strong><span><?= $eCabecera($__adminRol) ?></span></span>
                <?php if ($puedeCambiarCabecera): ?><i class="bi bi-chevron-down" aria-hidden="true"></i><span class="cabecera-clinica__cambiar">Cambiar</span><?php endif; ?>
            <?= $puedeCambiarCabecera ? '</a>' : '</span>' ?>
        </div>
        <div class="notifications-wrapper">
            <button type="button" class="notif-btn" id="notifBell" aria-label="Notificaciones, ninguna nueva" aria-expanded="false" aria-controls="notifDropdown"><i class="bi bi-bell" aria-hidden="true"></i><span class="notif-badge" id="notifBadge" aria-hidden="true" hidden></span></button>
            <div class="notif-dropdown" id="notifDropdown">
                <section class="notif-panel" data-notif-panel aria-labelledby="notifTitulo">
                    <header class="notif-header">
                        <div><h3 id="notifTitulo">Notificaciones</h3><p id="notifCount">0 sin leer</p></div>
                        <button type="button" class="notif-mark-all" id="notifMarkAll" aria-label="Marcar todas como leídas" title="Marcar todas como leídas" hidden><i class="bi bi-check2-all" aria-hidden="true"></i></button>
                    </header>
                    <div id="pendingVaccinesList" class="notif-body"></div>
                </section>
            </div>
        </div>
        <button type="button" class="header-user cabecera-cuenta__boton" data-cabecera-cuenta-boton aria-label="<?= $eCabecera('Mi cuenta: ' . $__adminNombre . ', ' . $__adminRol) ?>" aria-expanded="false" aria-controls="cabeceraCuentaMenu" aria-haspopup="dialog">
            <span class="header-user__avatar" aria-hidden="true"><?= $eCabecera($__adminIniciales) ?></span>
        </button>
    </div>
    <div class="cabecera-cuenta__menu" id="cabeceraCuentaMenu" data-cuenta-menu>
        <header class="cabecera-cuenta__identidad"><span class="header-user__avatar" aria-hidden="true"><?= $eCabecera($__adminIniciales) ?></span><div><h2 id="cabeceraCuentaNombre"><?= $eCabecera($__adminNombre) ?></h2><?php if ($correoCabecera !== ''): ?><p><?= $eCabecera($correoCabecera) ?></p><?php endif; ?><p class="cabecera-cuenta__contexto"><?= $eCabecera($clinicaCabecera . ' · ' . $__adminRol) ?></p></div></header>
        <div data-clinica-movil></div>
        <nav aria-label="Cuenta"><a href="index.php?action=<?= $eCabecera($enlaceCuentaCabecera) ?>" data-cuenta-destino><i class="bi bi-person-circle" aria-hidden="true"></i> Mi cuenta<i class="bi bi-chevron-right" aria-hidden="true"></i></a><a href="index.php?action=logout" class="cabecera-cuenta__salir"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Cerrar sesión</a></nav>
    </div>
    <dialog class="zk-dialogo cabecera-cuenta__dialogo" id="cabeceraCuentaDialogo" aria-labelledby="cabeceraCuentaNombre">
        <button type="button" class="zk-hoja__asa" data-cerrar-dialogo aria-label="Cerrar mi cuenta" autofocus></button>
        <div data-cuenta-hoja></div>
    </dialog>
    <dialog class="zk-dialogo cabecera-notif__dialogo" id="notifDialogo" aria-labelledby="notifTitulo">
        <button type="button" class="zk-hoja__asa" data-cerrar-dialogo aria-label="Cerrar notificaciones" autofocus></button>
        <div data-notif-hoja></div>
    </dialog>
</div>
