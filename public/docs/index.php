<?php
// index.php - Portal de Documentación de Zooki
session_start();

// Catálogo de documentos, slugs y extracción de encabezados: lógica
// compartida con scripts/algolia_index.php para que un mismo encabezado
// genere el mismo slug en el sidebar, en el ancla del DOM y en Algolia.
require_once __DIR__ . '/../../helpers/DocsIndex.php';
require_once __DIR__ . '/../../config/Algolia.php';
require_once __DIR__ . '/../../config/App.php';

// Archivos de documentación permitidos (evita Directory Traversal)
$docs_map = DocsIndex::docsMap();

// Obtener versión actual de Zooki desde README.md de manera dinámica.
// El patrón lleva el modificador /u: sin él, la clase [oó] compara byte a byte
// y la "ó" (dos bytes en UTF-8) nunca casaba, así que el portal mostraba
// siempre el valor de respaldo en lugar de la versión real del README.
$zooki_version = App::VERSION; // Respaldo: la versión declarada en el código
$readme_path = $docs_map['readme'];
if (file_exists($readme_path)) {
    $readme_content = file_get_contents($readme_path);
    if (preg_match('/###\s+Versi[oó]n\s+([\d\.]+)/iu', $readme_content, $matches)) {
        $zooki_version = $matches[1];
    }
}

// Endpoint API para retornar el contenido Markdown de forma asíncrona
if (isset($_GET['action']) && $_GET['action'] === 'get_doc') {
    $doc_id = $_GET['id'] ?? 'readme';
    
    if (array_key_exists($doc_id, $docs_map)) {
        $file_path = $docs_map[$doc_id];
        if (file_exists($file_path)) {
            header('Content-Type: text/markdown; charset=utf-8');
            echo file_get_contents($file_path);
            exit();
        } else {
            http_response_code(404);
            echo "# Error\nEl archivo de documentación no existe en el servidor.";
            exit();
        }
    } else {
        http_response_code(400);
        echo "# Error\nDocumento no válido solicitado.";
        exit();
    }
}

// Endpoint API: índice de navegación (títulos + encabezados H2/H3 con slug)
// de los 9 documentos, en una sola respuesta, para construir el sidebar
// colapsable y la búsqueda global sin tener que descargar el Markdown
// completo de cada documento por adelantado.
if (isset($_GET['action']) && $_GET['action'] === 'get_index') {
    $index = [];

    foreach ($docs_map as $doc_id => $file_path) {
        $title = '';
        $headings = [];

        if (file_exists($file_path)) {
            $content = file_get_contents($file_path);
            $title = DocsIndex::extractTitle($content);
            $headings = DocsIndex::extractHeadings($content);
        }

        $index[$doc_id] = ['title' => $title, 'headings' => $headings];
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($index, JSON_UNESCAPED_UNICODE);
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zooki - Portal de Documentación Oficial</title>
    
    <!-- Sin Google Fonts: el portal usa las fuentes del sistema
         (mismo stack que Factus), definidas en docs.css -->

    <!-- FontAwesome para Iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Estilos Oficiales del Portal -->
    <link rel="stylesheet" href="docs.css">

    <!-- Aplicar el tema guardado antes del primer pintado, para evitar parpadeo -->
    <script>
        (function () {
            try {
                if (localStorage.getItem('zooki-docs-theme') === 'light') {
                    document.documentElement.setAttribute('data-theme', 'light');
                }
            } catch (e) { /* localStorage no disponible: se mantiene el tema oscuro por defecto */ }
        })();
    </script>

    <!-- Librería Marked para procesar Markdown en Frontend -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

    <!-- Librería Mermaid para renderizar diagramas en tiempo real -->
    <script src="https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js"></script>
    <!-- La inicialización de Mermaid depende del tema activo (claro/oscuro)
         y se hace desde docs.js (applyMermaidTheme), no aquí, para poder
         reinicializarse cuando el usuario cambia de tema. -->
</head>
<body>

    <!-- Header Superior (Escritorio): logo, búsqueda global y tema -->
    <header class="app-topbar">
        <div class="topbar-brand">
            <img src="../img/icon_blue.png" alt="Zooki Logo" class="brand-logo-img">
            <div class="brand-text">
                <h1>Zooki</h1>
            </div>
        </div>

        <button id="global-search-trigger" class="global-search-btn" type="button" title="Buscar (Ctrl + K o /)">
            <i class="fa-solid fa-magnifying-glass"></i>
            <span>Buscar</span>
            <kbd aria-label="Atajo: tecla barra">/</kbd>
        </button>

        <button id="theme-toggle" class="theme-toggle-btn" type="button" title="Cambiar tema">
            <i class="fa-solid fa-sun"></i>
            <span>Claro</span>
        </button>
    </header>

    <!-- Header Móvil -->
    <header class="mobile-header">
        <button id="sidebar-toggle" class="icon-btn" aria-label="Abrir menú">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="mobile-logo">
            <img src="../img/icon_blue.png" alt="Zooki Logo" class="brand-logo-img-mobile">
            <span>Zooki Docs</span>
        </div>
        <button id="mobile-search-trigger" class="icon-btn" aria-label="Buscar en la documentación">
            <i class="fa-solid fa-magnifying-glass"></i>
        </button>
    </header>

    <div class="app-container">

        <!-- Barra Lateral Izquierda (Navegación) -->
        <aside class="sidebar" id="sidebar-nav">
            <nav class="sidebar-menu">
                <div class="menu-section">
                    <span class="menu-section-title">Primeros Pasos</span>
                    <ul>
                        <li>
                            <a href="#readme" class="menu-item active" data-doc="readme">
                                <span>Inicio / Sinopsis</span>
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="menu-section">
                    <span class="menu-section-title">Análisis y Especificación</span>
                    <ul>
                        <li>
                            <a href="#ficha" class="menu-item" data-doc="ficha">
                                <span>Ficha Técnica</span>
                            </a>
                        </li>
                        <li>
                            <a href="#ers" class="menu-item" data-doc="ers">
                                <span>Requisitos (ERS)</span>
                            </a>
                        </li>
                        <li>
                            <a href="#reglas" class="menu-item" data-doc="reglas">
                                <span>Reglas de Negocio</span>
                            </a>
                        </li>
                        <li>
                            <a href="#hu" class="menu-item" data-doc="hu">
                                <span>Historias de Usuario</span>
                            </a>
                        </li>
                        <li>
                            <a href="#re" class="menu-item" data-doc="re">
                                <span>Requisitos Específicos</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="menu-section">
                    <span class="menu-section-title">Arquitectura y Desarrollo</span>
                    <ul>
                        <li>
                            <a href="#mer" class="menu-item" data-doc="mer">
                                <span>Modelo Entidad-Relación</span>
                            </a>
                        </li>
                        <li>
                            <a href="#backlog" class="menu-item" data-doc="backlog">
                                <span>Backlog (Jira)</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <p>Zooki v<?php echo htmlspecialchars($zooki_version); ?> &copy; 2026</p>
                <div class="footer-links">
                    <a href="../../" title="Ir al Sistema Zooki"><i class="fa-solid fa-arrow-left"></i> Volver a Zooki</a>
                </div>
            </div>
        </aside>
        
        <!-- Overlay para pantallas móviles -->
        <div class="sidebar-overlay" id="sidebar-overlay"></div>

        <!-- Área de Contenido Principal -->
        <main class="main-content">
            <!-- Contenedor del documento renderizado -->
            <article class="document-container">
                <div class="loading-overlay" id="loading-spinner" style="display: none;">
                    <div class="spinner"></div>
                    <p>Cargando documentación...</p>
                </div>
                <div class="markdown-body" id="doc-render-area">
                    <!-- El contenido Markdown renderizado se insertará aquí -->
                </div>
            </article>
        </main>

        <!-- Barra Lateral Derecha (Tabla de Contenidos - TOC) -->
        <aside class="toc-sidebar">
            <div class="toc-container">
                <h3 class="toc-title">En esta página</h3>
                <nav id="toc-list" class="toc-list">
                    <!-- Los encabezados del documento actual se generarán aquí dinámicamente -->
                </nav>
            </div>
        </aside>

    </div>

    <!-- Paleta de búsqueda global (Ctrl+K) -->
    <div class="command-palette-overlay" id="command-palette-overlay">
        <div class="command-palette" id="command-palette" role="dialog" aria-label="Búsqueda en toda la documentación">
            <div class="cp-search-box">
                <i class="fa-solid fa-magnifying-glass cp-search-icon"></i>
                <input type="text" id="cp-input" placeholder="Buscar" autocomplete="off"
                       role="combobox" aria-expanded="true" aria-controls="cp-results" aria-autocomplete="list">
                <button type="button" id="cp-clear" class="cp-clear-btn" aria-label="Limpiar búsqueda" hidden>
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="cp-results" id="cp-results" role="listbox"></div>
            <div class="cp-footer">
                <div class="cp-hints">
                    <span><kbd>&crarr;</kbd> para seleccionar</span>
                    <span><kbd>&darr;</kbd><kbd>&uarr;</kbd> para navegar</span>
                    <span><kbd>esc</kbd> para cerrar</span>
                </div>
                <div class="cp-attribution" id="cp-attribution"></div>
            </div>
        </div>
    </div>

    <!-- Configuración de Algolia para el buscador. Solo se expone la llave
         de SOLO BÚSQUEDA (diseñada para el navegador); la de administración
         nunca sale de la CLI. Si no hay credenciales, el objeto queda nulo
         y docs.js usa automáticamente el buscador local. -->
    <script>
        window.ZOOKI_ALGOLIA = <?php echo Algolia::isEnabled()
            ? json_encode(Algolia::publicConfig(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)
            : 'null'; ?>;
    </script>

    <!-- Script de control principal -->
    <script src="docs.js"></script>
</body>
</html>
