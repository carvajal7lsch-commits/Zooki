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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conocimiento clínico · Plataforma · Zooki</title>
    <link rel="icon" href="img/icon_blue.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/dashboard.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/usuarios.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/pill-sidebar.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/contexto.css?v=<?= $v ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/plataforma.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/grafo-conocimiento.css?v=<?= $v ?>">
    <?php // Versión fija con SRI comprobado contra cdnjs (2026-10-14); si el CDN falla, el JS muestra la lista. ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.34.3/cytoscape.min.js" integrity="sha512-SQebVa91Tn1DeP45Wo74monK/I9wiQg59uKc7P8vp3T7KQ9l5e8IMjZ1GJTgZehUVQt7eS61DTqldQUb1EHfnw==" crossorigin="anonymous" referrerpolicy="no-referrer" defer></script>
    <script src="js/interacciones.js?v=<?= $v ?>" defer></script>
    <script src="js/grafo-conocimiento.js?v=<?= $v ?>" defer></script>
</head>
<body>
<div class="dashboard-layout">
<?php $seccionPlataforma = 'grafo'; require __DIR__ . '/barra_lateral.php'; ?>
<div class="main-content plataforma-contenido">
<?php require __DIR__ . '/cabecera.php'; ?>
<main class="content-body plataforma grafo" data-grafo data-url="index.php?action=obtener_grafo_conocimiento_ajax">
    <div class="header-container-white plataforma-encabezado">
        <div class="head-title-desc">
            <h2 class="users-page-title">Conocimiento clínico</h2>
            <p class="users-module-desc">Mapa de solo lectura del conocimiento global que usan los motores de apoyo: relaciones, reglas, fuentes y cobertura. Es apoyo a la decisión; decide el veterinario.</p>
        </div>
        <div class="head-top-right">
            <div class="view-toggle" role="group" aria-label="Tipo de vista">
                <button type="button" class="view-toggle-btn active" data-vista-boton="mapa" aria-pressed="true" title="Vista de mapa"><i class="bi bi-diagram-3" aria-hidden="true"></i></button>
                <button type="button" class="view-toggle-btn" data-vista-boton="lista" aria-pressed="false" title="Vista de lista"><i class="bi bi-list-ul" aria-hidden="true"></i></button>
            </div>
        </div>
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
    </div>

    <section class="grafo-franja" aria-label="Versión, cobertura y validación" data-grafo-franja hidden></section>
    <div class="plataforma-vacio grafo-estado" role="status" data-grafo-estado></div>
    <p class="plataforma-alerta grafo-aviso" role="alert" data-grafo-aviso-cdn hidden>No se pudo cargar la librería del mapa (sin conexión con el CDN). Abajo están los mismos datos en lista; vuelve a cargar la página para intentar el mapa.</p>

    <section class="grafo-area" data-grafo-area hidden>
        <div class="grafo-lienzo">
            <p class="grafo-lienzo__conteo" data-grafo-visibles role="status"></p>
            <div class="grafo-mapa" data-vista-grafo="mapa" data-grafo-mapa role="img" aria-label="Mapa del conocimiento: elige un nodo o usa el buscador; la vista de lista tiene los mismos datos"></div>
            <div class="grafo-lista" data-vista-grafo="lista" hidden></div>
        </div>
        <aside class="grafo-detalle" aria-labelledby="grafoDetalleTitulo" data-grafo-detalle hidden>
            <div class="grafo-detalle__cabeza">
                <div>
                    <p class="grafo-detalle__subtitulo" data-grafo-detalle-subtitulo></p>
                    <h3 class="grafo-detalle__titulo" id="grafoDetalleTitulo" data-grafo-detalle-titulo></h3>
                </div>
                <button type="button" class="close-modal" aria-label="Cerrar el detalle" data-grafo-cerrar-detalle>×</button>
            </div>
            <div class="grafo-detalle__cuerpo" data-grafo-detalle-cuerpo></div>
        </aside>
    </section>

    <section class="grafo-leyenda" aria-label="Leyenda del mapa" data-grafo-leyenda></section>
</main>
</div></div>
</body>
</html>
