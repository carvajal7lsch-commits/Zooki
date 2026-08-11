<?php
// index.php - Portal de Documentación de Zooki
session_start();

// Configuración de archivos de documentación permitidos (evita Directory Traversal)
$docs_map = [
    'readme'  => __DIR__ . '/../../README.md',
    'ers'     => __DIR__ . '/../../ERS.md',
    'ficha'   => __DIR__ . '/../../FichaTecnica_Zooki.md',
    'backlog' => __DIR__ . '/../../backlog-zooki.md',
    'mer'     => __DIR__ . '/../../MER.md'
];

// Obtener versión actual de Zooki desde README.md de manera dinámica
$zooki_version = '1.7.1'; // Fallback
$readme_path = $docs_map['readme'];
if (file_exists($readme_path)) {
    $readme_content = file_get_contents($readme_path);
    if (preg_match('/###\s+Versi[oó]n\s+([\d\.]+)/i', $readme_content, $matches)) {
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zooki - Portal de Documentación Oficial</title>
    
    <!-- Google Fonts: Outfit (Brand & Headings) + Inter (Body Text) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    
    <!-- FontAwesome para Iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Estilos Oficiales del Portal -->
    <link rel="stylesheet" href="docs.css">
    
    <!-- Librería Marked para procesar Markdown en Frontend -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    
    <!-- Librería Mermaid para renderizar diagramas en tiempo real -->
    <script src="https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js"></script>
    <script>
        // Inicializar Mermaid configurado para tema oscuro
        mermaid.initialize({
            startOnLoad: false,
            theme: 'dark',
            themeVariables: {
                background: '#1e1e24',
                primaryColor: '#3b82f6',
                primaryTextColor: '#f3f4f6',
                lineColor: '#4b5563',
                secondaryColor: '#1f2937',
                tertiaryColor: '#111827'
            }
        });
    </script>
</head>
<body class="dark-theme">

    <!-- Header Móvil -->
    <header class="mobile-header">
        <button id="sidebar-toggle" class="icon-btn" aria-label="Abrir menú">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="mobile-logo">
            <img src="../img/icon_blue.png" alt="Zooki Logo" class="brand-logo-img-mobile">
            <span>Zooki Docs</span>
        </div>
        <div class="placeholder-btn"></div>
    </header>

    <div class="app-container">
        
        <!-- Barra Lateral Izquierda (Navegación) -->
        <aside class="sidebar" id="sidebar-nav">
            <div class="sidebar-brand">
                <img src="../img/icon_blue.png" alt="Zooki Logo" class="brand-logo-img">
                <div class="brand-text">
                    <h1>Zooki</h1>
                    <span>Documentación Oficial</span>
                </div>
            </div>
            
            <!-- Buscador Integrado -->
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" id="doc-search" placeholder="Buscar en la página..." autocomplete="off">
                <button id="clear-search" class="clear-btn" style="display: none;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            
            <nav class="sidebar-menu">
                <div class="menu-section">
                    <span class="menu-section-title">Primeros Pasos</span>
                    <ul>
                        <li>
                            <a href="#readme" class="menu-item active" data-doc="readme">
                                <i class="fa-solid fa-house-chimney menu-icon"></i>
                                <span>Inicio / Sinopsis</span>
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="menu-section">
                    <span class="menu-section-title">Especificaciones</span>
                    <ul>
                        <li>
                            <a href="#ficha" class="menu-item" data-doc="ficha">
                                <i class="fa-solid fa-file-invoice menu-icon"></i>
                                <span>Ficha Técnica</span>
                            </a>
                        </li>
                        <li>
                            <a href="#ers" class="menu-item" data-doc="ers">
                                <i class="fa-solid fa-clipboard-list menu-icon"></i>
                                <span>Requisitos (ERS)</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="menu-section">
                    <span class="menu-section-title">Arquitectura y Desarrollo</span>
                    <ul>
                        <li>
                            <a href="#mer" class="menu-item" data-doc="mer">
                                <i class="fa-solid fa-diagram-project menu-icon"></i>
                                <span>Modelo Entidad-Relación</span>
                            </a>
                        </li>
                        <li>
                            <a href="#backlog" class="menu-item" data-doc="backlog">
                                <i class="fa-solid fa-list-check menu-icon"></i>
                                <span>Historias de Usuario / Backlog</span>
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
            <div class="content-header">
                <div class="breadcrumb">
                    <span id="breadcrumb-parent">Primeros Pasos</span>
                    <i class="fa-solid fa-chevron-right separator"></i>
                    <span id="breadcrumb-current" class="active">Inicio / Sinopsis</span>
                </div>
                <div class="header-actions">
                    <button class="theme-btn" id="print-doc" title="Imprimir o Exportar PDF">
                        <i class="fa-solid fa-print"></i> Imprimir / PDF
                    </button>
                </div>
            </div>

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

    <!-- Script de control principal -->
    <script src="docs.js"></script>
</body>
</html>
