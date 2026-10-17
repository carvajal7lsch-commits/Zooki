<?php
/** Cabecera del panel de plataforma (M0.2), compartida por sus pantallas. */
require_once __DIR__ . '/../../helpers/PresentacionPlataforma.php';
$nombreUsuario = (string) ($_SESSION['usuario_nombre'] ?? '');
?>
<header class="top-header plataforma-cabecera">
    <div class="plataforma-cabecera__titulo">
        <h1 class="top-header-title">Plataforma</h1>
    </div>
    <div class="plataforma-cabecera__cuenta">
        <?php include __DIR__ . '/../partials/contexto_actual.php'; ?>
        <div class="header-user" title="<?= htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8') ?>">
            <div class="header-user__avatar" aria-hidden="true"><?= htmlspecialchars(PresentacionPlataforma::iniciales($nombreUsuario !== '' ? $nombreUsuario : 'Zooki'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="header-user__info">
                <span class="header-user__name"><?= htmlspecialchars(explode(' ', trim($nombreUsuario))[0] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                <span class="header-user__role">Super-administrador</span>
            </div>
        </div>
    </div>
</header>
