<?php
// HU-T.5/T.17, RNF-16: mismo contexto y permisos; menú de cuenta compartido por admin/vet.
$esAdminCabecera = Contexto::rolActivo() === Roles::ADMIN;
$enlaceCuentaCabecera = $esAdminCabecera ? 'admin_configuracion#cuenta' : 'mi_perfil';
$eCabecera = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$contextoCabecera = Contexto::actual();
$clinicaCabecera = (string) ($contextoCabecera['clinica'] ?? 'Clínica');
$palabrasCabecera = preg_split('/\s+/u', trim($clinicaCabecera), -1, PREG_SPLIT_NO_EMPTY);
$inicialesClinicaCabecera = mb_strtoupper(implode('', array_map(static fn ($palabra): string => mb_substr($palabra, 0, 1), array_slice($palabrasCabecera, 0, 2))));
$correoCabecera = isset($perfil) && (int) ($perfil['id_usuario'] ?? 0) === Contexto::idUsuario() ? (string) ($perfil['email'] ?? '') : '';
?>
<div class="cabecera-cuenta" data-cabecera-cuenta>
    <div class="header-right" id="cabeceraCuentaPanel">
        <div data-clinica-escritorio>
            <<?= Contexto::puedeCambiar() ? 'a href="index.php?action=seleccionar_contexto"' : 'span' ?> class="cabecera-clinica" data-clinica-cuenta>
                <span class="cabecera-clinica__avatar" aria-hidden="true"><?= $eCabecera($inicialesClinicaCabecera) ?></span>
                <span class="cabecera-clinica__texto"><strong><?= $eCabecera($clinicaCabecera) ?></strong><span><?= $eCabecera($__adminRol) ?></span></span>
                <?php if (Contexto::puedeCambiar()): ?><i class="bi bi-chevron-down" aria-hidden="true"></i><span class="cabecera-clinica__cambiar">Cambiar</span><?php endif; ?>
            </<?= Contexto::puedeCambiar() ? 'a' : 'span' ?>>
        </div>
        <div class="notifications-wrapper">
            <button type="button" class="notif-btn" id="notifBell" aria-label="Notificaciones"><i class="bi bi-bell" aria-hidden="true"></i><span class="notif-badge" id="notifBadge" hidden></span></button>
            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header"><h3><?= $esAdminCabecera ? 'Notificaciones' : 'Alertas Clínicas' ?></h3><span id="notifCount">0 nuevas</span><button type="button" class="notif-mark-all" id="notifMarkAll" hidden><i class="bi bi-check2-all" aria-hidden="true"></i> Marcar todas</button></div>
                <div id="pendingVaccinesList" class="notif-body"></div>
            </div>
        </div>
        <button type="button" class="header-user cabecera-cuenta__boton" data-cabecera-cuenta-boton aria-label="Abrir mi cuenta" aria-expanded="false" aria-controls="cabeceraCuentaMenu" aria-haspopup="dialog">
            <span class="header-user__avatar"><?= $eCabecera($__adminIniciales) ?></span><span class="header-user__name"><?= $eCabecera(explode(' ', trim($__adminNombre))[0]) ?></span><i class="bi bi-chevron-down" aria-hidden="true"></i>
        </button>
    </div>
    <div class="cabecera-cuenta__menu" id="cabeceraCuentaMenu" data-cuenta-menu>
        <header class="cabecera-cuenta__identidad"><span class="header-user__avatar" aria-hidden="true"><?= $eCabecera($__adminIniciales) ?></span><div><h2 id="cabeceraCuentaNombre"><?= $eCabecera($__adminNombre) ?></h2><?php if ($correoCabecera !== ''): ?><p><?= $eCabecera($correoCabecera) ?></p><?php endif; ?></div></header>
        <div data-clinica-movil></div>
        <nav aria-label="Cuenta"><a href="index.php?action=<?= $eCabecera($enlaceCuentaCabecera) ?>" data-cuenta-destino><i class="bi bi-person-circle" aria-hidden="true"></i> Mi cuenta<i class="bi bi-chevron-right" aria-hidden="true"></i></a><a href="index.php?action=logout" class="cabecera-cuenta__salir"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Cerrar sesión</a></nav>
    </div>
    <dialog class="zk-dialogo cabecera-cuenta__dialogo" id="cabeceraCuentaDialogo" aria-labelledby="cabeceraCuentaNombre">
        <button type="button" class="zk-hoja__asa" data-cerrar-dialogo aria-label="Cerrar mi cuenta" autofocus></button>
        <div data-cuenta-hoja></div>
    </dialog>
</div>
