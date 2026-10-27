<?php
/**
 * Encabezado de una pantalla interna (rediseño 2026-10-26): botón volver con el nombre del
 * destino, un solo título y una línea de contexto. Lo arma el controlador en $encabezadoPagina
 * con 'titulo', 'subtitulo' y 'volver' => ['href', 'texto']; el layout carga encabezado-pagina.css.
 */
$__eh = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="encabezado-pagina">
    <?php if (!empty($encabezadoPagina['volver'])): ?>
    <a class="encabezado-pagina__volver" href="<?= $__eh($encabezadoPagina['volver']['href']) ?>"><i class="fas fa-arrow-left" aria-hidden="true"></i><span class="encabezado-pagina__volver-texto"><?= $__eh($encabezadoPagina['volver']['texto']) ?></span></a>
    <span class="encabezado-pagina__separador" aria-hidden="true"></span>
    <?php endif; ?>
    <div class="encabezado-pagina__textos">
        <h1 class="encabezado-pagina__titulo top-header-title"><?= $__eh($encabezadoPagina['titulo']) ?></h1>
        <?php if (!empty($encabezadoPagina['subtitulo'])): ?>
        <p class="encabezado-pagina__subtitulo"><?= $__eh($encabezadoPagina['subtitulo']) ?></p>
        <?php endif; ?>
    </div>
</div>
