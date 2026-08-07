/* docs.js - Control dinámico de navegación SPA, Markdown y Mermaid */

document.addEventListener('DOMContentLoaded', () => {
    
    // ═══════════════════════════════════════════════════════════════
    // SELECCIÓN DE ELEMENTOS DEL DOM
    // ═══════════════════════════════════════════════════════════════
    const sidebar = document.getElementById('sidebar-nav');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    const menuItems = document.querySelectorAll('.menu-item');
    const docRenderArea = document.getElementById('doc-render-area');
    const loadingSpinner = document.getElementById('loading-spinner');
    
    // Breadcrumbs
    const breadcrumbParent = document.getElementById('breadcrumb-parent');
    const breadcrumbCurrent = document.getElementById('breadcrumb-current');
    
    // Botones e Input de Búsqueda
    const searchInput = document.getElementById('doc-search');
    const clearSearchBtn = document.getElementById('clear-search');
    const printBtn = document.getElementById('print-doc');
    
    // Tabla de Contenidos (TOC)
    const tocList = document.getElementById('toc-list');

    // Mapeo para Breadcrumbs de secciones superiores
    const sectionNames = {
        'readme': 'Primeros Pasos',
        'ficha': 'Especificaciones',
        'ers': 'Especificaciones',
        'mer': 'Arquitectura y Desarrollo',
        'backlog': 'Arquitectura y Desarrollo'
    };

    // ═══════════════════════════════════════════════════════════════
    // 1. CARGA DINÁMICA DE DOCUMENTOS (SPA)
    // ═══════════════════════════════════════════════════════════════
    let currentDocId = 'readme';
    let rawDocumentContent = ''; // Almacena el markdown original sin renderizar

    async function loadDocument(docId) {
        if (!docId) docId = 'readme';
        currentDocId = docId;

        // Mostrar indicador de carga
        loadingSpinner.style.display = 'block';
        docRenderArea.style.opacity = '0.3';
        
        // Actualizar estado activo en la barra lateral
        menuItems.forEach(item => {
            if (item.getAttribute('data-doc') === docId) {
                item.classList.add('active');
                
                // Actualizar breadcrumbs
                breadcrumbCurrent.textContent = item.querySelector('span').textContent;
                breadcrumbParent.textContent = sectionNames[docId] || 'Documentación';
            } else {
                item.classList.remove('active');
            }
        });

        try {
            const response = await fetch(`index.php?action=get_doc&id=${docId}`);
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }
            
            rawDocumentContent = await response.text();
            
            // Si el documento es README, agregamos un grid de Bento Cards al final
            let renderedMarkdown = rawDocumentContent;
            if (docId === 'readme') {
                renderedMarkdown += `
\n
## Explorar Documentación
A continuación, puedes profundizar en las especificaciones del sistema:

<div class="docs-grid">
    <a href="#ficha" class="docs-card">
        <h3><i class="fa-solid fa-file-invoice"></i> Ficha Técnica</h3>
        <p>Requisitos de hardware, tecnologías empleadas, servidores y especificaciones del entorno de ejecución de Zooki.</p>
    </a>
    <a href="#ers" class="docs-card">
        <h3><i class="fa-solid fa-clipboard-list"></i> Requisitos (ERS)</h3>
        <p>Casos de uso del sistema, requerimientos funcionales y no funcionales para administración, veterinarios y clientes.</p>
    </a>
    <a href="#mer" class="docs-card">
        <h3><i class="fa-solid fa-diagram-project"></i> Modelo Entidad-Relación</h3>
        <p>Estructura de la base de datos MySQL, definición de tablas principales, claves y diagrama interactivo.</p>
    </a>
    <a href="#backlog" class="docs-card">
        <h3><i class="fa-solid fa-list-check"></i> Historias de Usuario</h3>
        <p>Listado de backlog, historias de usuario detalladas con sus respectivos criterios de aceptación.</p>
    </a>
</div>
`;
            }

            // Renderizar Markdown usando Marked
            docRenderArea.innerHTML = marked.parse(renderedMarkdown);
            
            // Post-procesamiento para Mermaid y TOC
            await processMermaidDiagrams();
            generateTOC();
            
            // Limpiar buscador si tenía datos
            searchInput.value = '';
            clearSearchBtn.style.display = 'none';

        } catch (error) {
            console.error('Error cargando documento:', error);
            docRenderArea.innerHTML = `
                <div style="padding: 40px; text-align: center; color: #ef4444;">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size: 40px; margin-bottom: 16px;"></i>
                    <h2>Error al Cargar Documentación</h2>
                    <p>${error.message}</p>
                </div>
            `;
            tocList.innerHTML = '';
        } finally {
            loadingSpinner.style.display = 'none';
            docRenderArea.style.opacity = '1';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // 2. RENDERIZACIÓN DE DIAGRAMAS DE MERMAID
    // ═══════════════════════════════════════════════════════════════
    async function processMermaidDiagrams() {
        // Encontrar bloques de código que son diagramas de Mermaid
        const mermaidCodes = docRenderArea.querySelectorAll('pre code.language-mermaid');
        
        mermaidCodes.forEach((codeEl) => {
            const preEl = codeEl.parentElement;
            const mermaidCode = codeEl.textContent;
            
            // Crear contenedor de Mermaid
            const mermaidDiv = document.createElement('div');
            mermaidDiv.className = 'mermaid';
            mermaidDiv.textContent = mermaidCode;
            
            // Reemplazar pre con el contenedor
            preEl.replaceWith(mermaidDiv);
        });

        // Si hay elementos Mermaid, forzar el renderizado en tiempo real
        const mermaidNodes = docRenderArea.querySelectorAll('.mermaid');
        if (mermaidNodes.length > 0 && typeof mermaid !== 'undefined') {
            try {
                await mermaid.run({
                    nodes: mermaidNodes
                });
            } catch (err) {
                console.error("Error al compilar diagramas de Mermaid:", err);
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // 3. GENERACIÓN DE LA TABLA DE CONTENIDOS (TOC)
    // ═══════════════════════════════════════════════════════════════
    function generateTOC() {
        tocList.innerHTML = '';
        
        // Buscar encabezados H2 y H3 en el documento activo
        const headings = docRenderArea.querySelectorAll('h2, h3');
        
        if (headings.length === 0) {
            tocList.innerHTML = '<span style="font-size:12px; color:var(--text-muted);">Sin secciones</span>';
            return;
        }

        headings.forEach((heading, index) => {
            // Asignar ID si no lo tiene
            if (!heading.id) {
                heading.id = 'heading-' + index;
            }

            const item = document.createElement('a');
            item.href = '#' + heading.id;
            item.className = 'toc-item';
            
            // Indentar subsecciones (H3)
            if (heading.tagName.toLowerCase() === 'h3') {
                item.classList.add('indent-1');
            }
            
            item.textContent = heading.textContent;
            
            // Manejar clic suave
            item.addEventListener('click', (e) => {
                e.preventDefault();
                heading.scrollIntoView({ behavior: 'smooth', block: 'start' });
                
                // Actualizar clase activa en TOC
                document.querySelectorAll('.toc-item').forEach(el => el.classList.remove('active'));
                item.classList.add('active');
            });

            tocList.appendChild(item);
        });

        // Monitorear scroll para resaltar sección activa en el TOC
        setupTOCScrollSpy(headings);
    }

    // Resaltar sección activa en TOC usando IntersectionObserver
    function setupTOCScrollSpy(headings) {
        const tocItems = document.querySelectorAll('.toc-item');
        
        const observerOptions = {
            root: null,
            rootMargin: '0px 0px -60% 0px', // Activa antes de llegar a la mitad de pantalla
            threshold: 0
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const id = entry.target.id;
                    tocItems.forEach(item => {
                        if (item.getAttribute('href') === '#' + id) {
                            item.classList.add('active');
                        } else {
                            item.classList.remove('active');
                        }
                    });
                }
            });
        }, observerOptions);

        headings.forEach(heading => observer.observe(heading));
    }

    // ═══════════════════════════════════════════════════════════════
    // 4. SISTEMA DE BÚSQUEDA INTERNA (HIGHLIGHTS)
    // ═══════════════════════════════════════════════════════════════
    let searchTimeout = null;

    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.trim().toLowerCase();
        
        // Mostrar / ocultar botón de limpiar
        clearSearchBtn.style.display = query ? 'block' : 'none';

        // Debounce de búsqueda para evitar repeticiones innecesarias
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, 150);
    });

    clearSearchBtn.addEventListener('click', () => {
        searchInput.value = '';
        clearSearchBtn.style.display = 'none';
        performSearch('');
        searchInput.focus();
    });

    function performSearch(query) {
        // Remover marcas existentes de búsquedas previas
        removeHighlights();

        if (!query) return;

        // Buscar coincidencias y envolverlas en etiquetas <mark>
        const renderBody = docRenderArea;
        
        // Función recursiva para buscar sólo en nodos de texto y evitar romper tags HTML
        function highlightTextNodes(node) {
            if (node.nodeType === 3) { // Nodo de texto
                const text = node.nodeValue;
                const index = text.toLowerCase().indexOf(query);
                
                if (index >= 0) {
                    const span = document.createElement('span');
                    
                    // Separar texto e insertar <mark>
                    const before = text.substring(0, index);
                    const match = text.substring(index, index + query.length);
                    const after = text.substring(index + query.length);
                    
                    span.innerHTML = `${escapeHTML(before)}<mark>${escapeHTML(match)}</mark>${escapeHTML(after)}`;
                    
                    // Reemplazar nodo de texto original con el nuevo contenedor
                    node.replaceWith(span);
                }
            } else if (node.nodeType === 1 && node.childNodes && !['script', 'style', 'pre', 'code'].includes(node.tagName.toLowerCase())) {
                // No buscar dentro de scripts, estilos o código formateado
                for (let i = 0; i < node.childNodes.length; i++) {
                    const child = node.childNodes[i];
                    highlightTextNodes(child);
                    // Si el hijo fue reemplazado por un span, debemos omitir el salto para no repetir
                    if (child.nodeType === 1 && child.tagName.toLowerCase() === 'span') {
                        i++;
                    }
                }
            }
        }

        highlightTextNodes(renderBody);

        // Desplazar la pantalla a la primera coincidencia
        const firstMatch = docRenderArea.querySelector('mark');
        if (firstMatch) {
            firstMatch.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    function removeHighlights() {
        // Para remover highlights de forma segura sin dañar el DOM, simplemente
        // regeneramos el Markdown actual desde el contenido original
        const scrollPos = window.scrollY;
        
        // Volvemos a parsear y procesar diagramas
        let renderedMarkdown = rawDocumentContent;
        if (currentDocId === 'readme') {
            renderedMarkdown += `
\n
## Explorar Documentación
A continuación, puedes profundizar en las especificaciones del sistema:

<div class="docs-grid">
    <a href="#ficha" class="docs-card">
        <h3><i class="fa-solid fa-file-invoice"></i> Ficha Técnica</h3>
        <p>Requisitos de hardware, tecnologías empleadas, servidores y especificaciones del entorno de ejecución de Zooki.</p>
    </a>
    <a href="#ers" class="docs-card">
        <h3><i class="fa-solid fa-clipboard-list"></i> Requisitos (ERS)</h3>
        <p>Casos de uso del sistema, requerimientos funcionales y no funcionales para administración, veterinarios y clientes.</p>
    </a>
    <a href="#mer" class="docs-card">
        <h3><i class="fa-solid fa-diagram-project"></i> Modelo Entidad-Relación</h3>
        <p>Estructura de la base de datos MySQL, definición de tablas principales, claves y diagrama interactivo.</p>
    </a>
    <a href="#backlog" class="docs-card">
        <h3><i class="fa-solid fa-list-check"></i> Historias de Usuario</h3>
        <p>Listado de backlog, historias de usuario detalladas con sus respectivos criterios de aceptación.</p>
    </a>
</div>
`;
        }
        
        docRenderArea.innerHTML = marked.parse(renderedMarkdown);
        processMermaidDiagrams().then(() => {
            generateTOC();
            window.scrollTo(0, scrollPos);
        });
    }

    function escapeHTML(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // ═══════════════════════════════════════════════════════════════
    // 5. ENRUTADOR BASADO EN HASH (Navegación del Historial)
    // ═══════════════════════════════════════════════════════════════
    function handleRouting() {
        const hash = window.location.hash.replace('#', '');
        
        // Mapeo de hashes permitidos
        const validDocs = ['readme', 'ficha', 'ers', 'mer', 'backlog'];
        
        if (validDocs.includes(hash)) {
            loadDocument(hash);
        } else {
            // Ruta por defecto
            window.location.hash = '#readme';
            loadDocument('readme');
        }
    }

    // Escuchar cambios de Hash en la URL
    window.addEventListener('hashchange', handleRouting);
    
    // Inicialización del Enrutamiento
    handleRouting();

    // ═══════════════════════════════════════════════════════════════
    // 6. EVENTOS DE INTERFAZ DE USUARIO
    // ═══════════════════════════════════════════════════════════════

    // Toggle de Barra Lateral Móvil
    function toggleSidebar() {
        sidebar.classList.toggle('open');
        sidebarOverlay.classList.toggle('visible');
    }

    sidebarToggle.addEventListener('click', toggleSidebar);
    sidebarOverlay.addEventListener('click', toggleSidebar);

    // Cerrar sidebar móvil al dar clic en un elemento del menú
    menuItems.forEach(item => {
        item.addEventListener('click', () => {
            if (sidebar.classList.contains('open')) {
                toggleSidebar();
            }
        });
    });

    // Impresión
    printBtn.addEventListener('click', () => {
        window.print();
    });

});
