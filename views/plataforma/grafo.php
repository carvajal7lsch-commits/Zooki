<?php
require_once __DIR__ . '/../../config/App.php';
/**
 * HU-2.12 — Mapa del conocimiento clínico (M2 C6). Solo lectura del conocimiento global
 * (RN-212); sin pacientes ni edición (RE-2.12.5). La barra, la cabecera y el encabezado
 * blanco son los del panel de plataforma (M0.2). Los datos llegan por
 * obtener_grafo_conocimiento_ajax y los pinta js/grafo-conocimiento.js.
 */
$v = htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="expect" href="#paginaLista" blocking="render">
    <link rel="stylesheet" href="css/navegacion-transicion.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/carga-contenido.css?v=<?= $v ?>">
    <script src="js/carga-contenido.js?v=<?= $v ?>"></script>
    <script src="js/navegacion-transicion.js?v=<?= $v ?>"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conocimiento clínico · Plataforma · Zooki</title>
    <link rel="icon" href="img/icon_blue.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/dashboard.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/usuarios.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/identidad.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/pill-sidebar.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/contexto.css?v=<?= $v ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/plataforma.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/grafo-conocimiento.css?v=<?= $v ?>">
    <!-- HU-2.12: biblioteca local; respaldo a lista si no hay WebGL. -->
    <script src="js/vendor/3d-force-graph-1.80.0.min.js" defer></script>
    <script src="js/interacciones.js?v=<?= $v ?>" defer></script>
    <script src="js/grafo-conocimiento.js?v=<?= $v ?>" defer></script>
</head>
<body>
<div class="dashboard-layout">
<?php $seccionPlataforma = 'grafo'; require __DIR__ . '/barra_lateral.php'; ?>
<div class="main-content plataforma-contenido">
<?php require __DIR__ . '/cabecera.php'; ?>
<main class="content-body plataforma grafo" data-grafo data-url="index.php?action=obtener_grafo_conocimiento_ajax">
    <?php require __DIR__ . "/../partials/carga_contenido.php"; ?>
    <div class="grafo-composicion">
    <aside class="grafo-filtros-lateral" id="grafoFiltros" aria-label="Explorar conocimiento">
        <form class="users-controls-bar__filters is-active plataforma-filtros grafo-filtros" role="search" data-grafo-filtros hidden>
            <div class="head-search grafo-buscador">
                <div class="search-input">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="search" placeholder="Buscar síntoma, fármaco, raza…" aria-label="Buscar en el conocimiento por etiqueta o sinónimo" autocomplete="off" maxlength="60" data-caracteres="\p{L}\p{M}\p{N}\p{P}\p{S} " data-espacios="normalizar" data-grafo-buscar aria-controls="grafoSugerencias">
                </div>
                <div class="grafo-sugerencias" id="grafoSugerencias" role="listbox" aria-label="Sugerencias" data-grafo-sugerencias hidden></div>
            </div>
            <div class="head-status-filters">
                <select class="filter-select" aria-label="Filtrar por especie" data-grafo-especie></select>
            </div>
            <fieldset class="grafo-tipos" data-grafo-tipos>
                <legend class="grafo-tipos__titulo">Tipos</legend>
                <div class="grafo-tipos__opciones" data-grafo-tipos-opciones></div>
            </fieldset>
        </form>
    <details class="grafo-ayuda"><summary>Cómo leer el mapa</summary><section class="grafo-leyenda" aria-label="Leyenda del mapa" data-grafo-leyenda></section></details>
    <details class="grafo-cobertura-detalle"><summary>Versión y cobertura</summary><section class="grafo-franja" aria-label="Versión, cobertura y validación" data-grafo-franja hidden></section></details>
    <div class="plataforma-vacio grafo-estado" role="status" data-grafo-estado></div>
    <p class="plataforma-alerta grafo-aviso" role="alert" data-grafo-aviso-cdn hidden>No se pudo iniciar el mapa 3D. Se muestran los mismos datos en lista; puedes seguir consultando filtros, relaciones y fuentes.</p>

    </aside>
    <section class="grafo-area" data-grafo-area hidden>
        <div class="grafo-lienzo">
            <div class="grafo-barra">
                <button type="button" class="grafo-lado-boton" data-grafo-lado="filtros" aria-label="Filtros" aria-pressed="true" aria-controls="grafoFiltros" title="Mostrar u ocultar filtros"><i class="bi bi-layout-sidebar" aria-hidden="true"></i></button>
                <div class="grafo-vistas" role="group" aria-label="Vista del conocimiento">
                    <button type="button" data-vista-boton="mapa" aria-pressed="true">3D</button>
                    <button type="button" data-vista-boton="plano" aria-pressed="false">Plano</button>
                    <button type="button" data-vista-boton="lista" aria-pressed="false">Lista</button>
                </div>
                <button type="button" data-grafo-girar aria-pressed="false"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Girar</button>
                <button type="button" data-grafo-pantalla aria-label="Pantalla completa"><i class="bi bi-fullscreen" aria-hidden="true"></i></button>
                <button type="button" class="grafo-lado-boton" data-grafo-lado="ficha" aria-label="Ficha" aria-pressed="true" aria-controls="grafoDetalle" title="Mostrar u ocultar ficha"><i class="bi bi-layout-sidebar-reverse" aria-hidden="true"></i></button>
            </div>
            <div class="grafo-foco" data-grafo-foco hidden><span data-grafo-foco-texto></span><button type="button" data-grafo-ver-ficha hidden>Ver ficha</button><button type="button" data-grafo-todo>Ver todo</button></div>
            <p class="grafo-lienzo__conteo" data-grafo-visibles role="status"></p>
            <div class="grafo-mapa" data-vista-grafo="mapa" data-grafo-mapa role="img" aria-label="Mapa del conocimiento: elige un nodo o usa el buscador; la vista de lista tiene los mismos datos"></div>
            <div class="grafo-lista" data-vista-grafo="lista" hidden></div>
        </div>
        <aside class="grafo-detalle" id="grafoDetalle" aria-labelledby="grafoDetalleTitulo" data-grafo-detalle hidden>
            <div class="grafo-detalle__cabeza">
                <div>
                    <p class="grafo-detalle__subtitulo" data-grafo-detalle-subtitulo></p>
                    <h3 class="grafo-detalle__titulo" id="grafoDetalleTitulo" data-grafo-detalle-titulo></h3>
                    <div data-grafo-detalle-fichas></div>
                </div>
                <button type="button" class="close-modal" aria-label="Cerrar el detalle" data-grafo-cerrar-detalle>×</button>
            </div>
            <div class="grafo-detalle__cuerpo" data-grafo-detalle-cuerpo></div>
        </aside>
    </section>

    <aside class="grafo-detalle-vacio"><i class="bi bi-cursor" aria-hidden="true"></i><h3>Explora el conocimiento</h3><p>Elige un nodo para consultar sus relaciones, reglas y fuentes.</p><p>Es apoyo a la decisión; decide el veterinario.</p></aside>
    </div>
</main>
</div></div>
<span id="paginaLista" hidden></span>
</body>
</html>
