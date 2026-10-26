<?php
/**
 * Barra de navegación de las áreas internas (administrador, veterinario y plataforma).
 * En escritorio es la barra lateral de siempre. En el celular (rediseño aprobado 2026-10-26)
 * muestra los destinos principales con su nombre y el resto en «Más», junto con Salir.
 *
 * Espera:
 *   $barraInicio  enlace del logo.
 *   $barraItems   lista de ['href', 'icono', 'titulo', 'corto', 'activo' => bool, 'principal' => bool].
 *   $barraSalirEnPantalla  (opcional) true si una pantalla propia ofrece Cerrar sesión; el celular no lo repite.
 * Requiere dialogos-zooki.css/js para la hoja de «Más».
 */
$__bh = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$__secundarios = array_values(array_filter($barraItems, static fn (array $item): bool => empty($item['principal'])));
$__masActivo = (bool) array_filter($__secundarios, static fn (array $item): bool => !empty($item['activo']));
?>
<nav class="pill-sidebar pill-sidebar--etiquetas<?= $__secundarios ? ' pill-sidebar--con-mas' : '' ?><?= !empty($barraSalirEnPantalla) ? ' pill-sidebar--sin-salir' : '' ?>" aria-label="Menú principal">
    <a href="<?= $__bh($barraInicio) ?>" class="pill-logo" title="Ir al inicio">
        <img src="img/icon_blue.png" alt="Zooki" class="pill-logo-img">
    </a>

    <div class="pill-nav">
        <?php foreach ($barraItems as $__item): ?>
        <a class="pill-nav-item<?= !empty($__item['activo']) ? ' active' : '' ?><?= empty($__item['principal']) ? ' pill-nav-item--secundario' : '' ?>" href="<?= $__bh($__item['href']) ?>" title="<?= $__bh($__item['titulo']) ?>" aria-label="<?= $__bh($__item['titulo']) ?>"<?= !empty($__item['activo']) ? ' aria-current="page"' : '' ?>>
            <i class="<?= $__bh($__item['icono']) ?>" aria-hidden="true"></i><span class="pill-nav-texto" aria-hidden="true"><?= $__bh($__item['corto']) ?></span>
        </a>
        <?php endforeach; ?>
        <?php if ($__secundarios): ?>
        <button type="button" class="pill-nav-item pill-nav-mas<?= $__masActivo ? ' active' : '' ?>" data-abrir-dialogo="barraNavegacionMas" aria-haspopup="dialog" aria-label="Más opciones">
            <i class="fas fa-ellipsis-h" aria-hidden="true"></i><span class="pill-nav-texto" aria-hidden="true">Más</span>
        </button>
        <?php endif; ?>
    </div>

    <div class="pill-divider"></div>

    <a href="index.php?action=logout" class="pill-logout" title="Cerrar sesión" aria-label="Cerrar sesión">
        <i class="fas fa-sign-out-alt" aria-hidden="true"></i><span class="pill-nav-texto" aria-hidden="true">Salir</span>
    </a>
</nav>
<?php if ($__secundarios): ?>
<dialog class="zk-dialogo barra-mas" id="barraNavegacionMas" aria-labelledby="barraNavegacionMasTitulo">
    <div class="zk-dialogo__form">
        <div class="zk-dialogo__head">
            <div><h3 id="barraNavegacionMasTitulo">Más opciones</h3></div>
            <button type="button" class="zk-dialogo__cerrar" aria-label="Cerrar" data-cerrar-dialogo><i class="fas fa-times" aria-hidden="true"></i></button>
        </div>
        <div class="zk-dialogo__cuerpo barra-mas__lista">
            <?php foreach ($__secundarios as $__item): ?>
            <a class="barra-mas__enlace<?= !empty($__item['activo']) ? ' is-activo' : '' ?>" href="<?= $__bh($__item['href']) ?>"<?= !empty($__item['activo']) ? ' aria-current="page"' : '' ?>><span class="barra-mas__icono" aria-hidden="true"><i class="<?= $__bh($__item['icono']) ?>"></i></span><?= $__bh($__item['titulo']) ?><i class="fas fa-chevron-right barra-mas__flecha" aria-hidden="true"></i></a>
            <?php endforeach; ?>
            <a class="barra-mas__enlace barra-mas__enlace--salir" href="index.php?action=logout"><span class="barra-mas__icono" aria-hidden="true"><i class="fas fa-sign-out-alt"></i></span>Cerrar sesión</a>
        </div>
    </div>
</dialog>
<?php endif; ?>
