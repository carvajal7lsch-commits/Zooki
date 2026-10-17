<?php
/**
 * Barra del panel de plataforma (M0.2), compartida por sus pantallas.
 * Espera $seccionPlataforma: 'clinicas' o 'grafo' (HU-2.12).
 */
$seccionPlataforma = $seccionPlataforma ?? 'clinicas';
$itemsPlataforma = [
    'clinicas' => ['plataforma_inicio', 'fas fa-building', 'Plataforma'],
    'grafo' => ['plataforma_grafo', 'fas fa-project-diagram', 'Conocimiento clínico'],
];
?>
<nav class="pill-sidebar" aria-label="Plataforma">
    <a class="pill-logo" href="index.php?action=plataforma_inicio"><img class="pill-logo-img" src="img/icon_blue.png" alt="Zooki"></a>
    <div class="pill-nav">
        <?php foreach ($itemsPlataforma as $clave => [$accion, $icono, $titulo]): ?>
            <a class="pill-nav-item<?= $clave === $seccionPlataforma ? ' active' : '' ?>" href="index.php?action=<?= $accion ?>" title="<?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?>"<?= $clave === $seccionPlataforma ? ' aria-current="page"' : '' ?>><i class="<?= $icono ?>" aria-hidden="true"></i></a>
        <?php endforeach; ?>
    </div>
    <a class="pill-logout" href="index.php?action=logout" title="Cerrar sesión"><i class="fas fa-sign-out-alt" aria-hidden="true"></i></a>
</nav>
