<?php
// HU-0.3: campana de pendientes de plataforma, sin endpoints de avisos clínicos.
require_once __DIR__ . '/../../helpers/PresentacionPlataforma.php';
$nombreUsuario = (string) ($_SESSION['usuario_nombre'] ?? 'Plataforma');
$esMapaPlataforma = ($seccionPlataforma ?? '') === 'grafo';
$eCabecera = static fn ($texto) => htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
?>
<header class="top-header plataforma-cabecera">
    <div class="plataforma-cabecera__titulo">
        <span class="plataforma-titulo-icono" aria-hidden="true"><i class="bi <?= $esMapaPlataforma ? 'bi-diagram-3' : 'bi-buildings' ?>"></i></span>
        <div><h1 class="top-header-title"><?= $esMapaPlataforma ? 'Conocimiento clínico' : 'Plataforma' ?></h1><p><?= $esMapaPlataforma ? 'Mapa global de apoyo a la decisión' : 'Clínicas, planes y suscripciones' ?></p></div>
    </div>
    <div class="plataforma-cabecera__cuenta">
        <span class="plataforma-rol"><i class="bi bi-shield-check" aria-hidden="true"></i> Super-administrador</span>
        <details class="plataforma-menu-cuenta">
            <summary class="plataforma-circulo" aria-label="Pendientes de plataforma"><i class="bi bi-bell" aria-hidden="true"></i><?php if (isset($contadores) && $contadores['casos'] + $contadores['ips'] > 0): ?><span class="plataforma-badge"><?= min(99, $contadores['casos'] + $contadores['ips']) ?></span><?php endif; ?></summary>
            <nav aria-label="Pendientes"><strong>Pendientes de plataforma</strong><a href="index.php?action=plataforma_inicio#soporte">Casos de soporte<?= isset($contadores) ? ' · ' . (int) $contadores['casos'] : '' ?></a><a href="index.php?action=plataforma_inicio#seguridad">Seguridad<?= isset($contadores) ? ' · ' . (int) $contadores['ips'] : '' ?></a></nav>
        </details>
        <details class="plataforma-menu-cuenta">
            <summary class="plataforma-circulo plataforma-avatar" aria-label="<?= $eCabecera('Cuenta: ' . $nombreUsuario) ?>"><?= $eCabecera(PresentacionPlataforma::iniciales($nombreUsuario)) ?></summary>
            <nav aria-label="Cuenta"><strong><?= $eCabecera($nombreUsuario) ?></strong><span>Super-administrador</span><a href="index.php?action=logout">Cerrar sesión</a></nav>
        </details>
    </div>
</header>
<script src="js/plataforma-cabecera.js?v=<?= $v ?>" defer></script>
