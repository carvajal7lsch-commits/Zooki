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
            let htmlContent = marked.parse(renderedMarkdown);
            
            // Corregir rutas de imágenes y enlaces de "public/img/" a "../img/" para el portal SPA
            htmlContent = htmlContent.replace(/src="public\/img\//g, 'src="../img/');
            htmlContent = htmlContent.replace(/href="public\/img\//g, 'href="../img/');
            
            docRenderArea.innerHTML = htmlContent;
            
            // Post-procesamiento para Mermaid y TOC
            await processMermaidDiagrams();
            generateTOC();
            
            // Inicializar visor de diagramas interactivo si existe en la página
            initSvgViewer();
            
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

    // ═══════════════════════════════════════════════════════════════
    // VISOR INTERACTIVO DE SVG (Zoom y Pan)
    // ═══════════════════════════════════════════════════════════════
    function initSvgViewer() {
        const viewport = document.getElementById('svg-viewport');
        const panContainer = document.getElementById('svg-pan-container');
        const svgObject = document.getElementById('mer-svg-object');
        
        const btnZoomIn = document.getElementById('btn-zoom-in');
        const btnZoomOut = document.getElementById('btn-zoom-out');
        const btnZoomReset = document.getElementById('btn-zoom-reset');
        const btnFullscreen = document.getElementById('btn-fullscreen');
        const zoomPercent = document.getElementById('zoom-percent');
        
        if (!viewport || !panContainer || !svgObject) return;
        
        let scale = 1.0;
        const minScale = 0.1;
        const maxScale = 8.0;
        const baseWidth = 2500; // Ancho base en px para alta resolución
        let baseHeight = 1500;  // Fallback inicial
        
        // Obtener la relación de aspecto de la imagen para cambiar el alto de forma síncrona
        function initDimensions() {
            if (svgObject.naturalWidth && svgObject.naturalHeight) {
                const aspect = svgObject.naturalHeight / svgObject.naturalWidth;
                baseHeight = baseWidth * aspect;
                updateScale();
            }
        }
        
        if (svgObject.complete) {
            initDimensions();
        } else {
            svgObject.addEventListener('load', initDimensions);
        }
        
        // Aplicar escala inicial
        updateScale();
        
        function updateScale() {
            panContainer.style.width = (baseWidth * scale) + 'px';
            panContainer.style.height = (baseHeight * scale) + 'px';
            if (zoomPercent) {
                zoomPercent.textContent = Math.round(scale * 100) + '%';
            }
        }
        
        function zoomFromCenter(zoomIn) {
            const oldScale = scale;
            let newScale = scale;
            
            if (zoomIn) {
                if (scale < maxScale) {
                    newScale = Math.min(maxScale, scale + 0.25);
                }
            } else {
                if (scale > minScale) {
                    newScale = Math.max(minScale, scale - 0.25);
                }
            }
            
            if (newScale !== oldScale) {
                const centerX = viewport.clientWidth / 2;
                const centerY = viewport.clientHeight / 2;
                
                const contentX = centerX + viewport.scrollLeft;
                const contentY = centerY + viewport.scrollTop;
                
                scale = newScale;
                updateScale();
                
                const ratio = newScale / oldScale;
                viewport.scrollLeft = contentX * ratio - centerX;
                viewport.scrollTop = contentY * ratio - centerY;
            }
        }
        
        // Zoom In (Acercar)
        if (btnZoomIn) {
            btnZoomIn.addEventListener('click', () => {
                zoomFromCenter(true);
            });
        }
        
        // Zoom Out (Alejar)
        if (btnZoomOut) {
            btnZoomOut.addEventListener('click', () => {
                zoomFromCenter(false);
            });
        }
        
        // Reset Zoom (Restaurar)
        if (btnZoomReset) {
            btnZoomReset.addEventListener('click', () => {
                scale = 1.0;
                updateScale();
                // Centrar scroll en el viewport
                viewport.scrollLeft = (panContainer.clientWidth - viewport.clientWidth) / 2;
                viewport.scrollTop = (panContainer.clientHeight - viewport.clientHeight) / 2;
            });
        }
        
        // Fullscreen Toggle (Pantalla completa)
        if (btnFullscreen) {
            btnFullscreen.addEventListener('click', () => {
                const viewerCard = viewport.closest('.svg-viewer-card');
                if (!viewerCard) return;
                
                if (!document.fullscreenElement) {
                    viewerCard.requestFullscreen().then(() => {
                        viewerCard.classList.add('fullscreen-mode');
                    }).catch(err => {
                        console.error(`Error al activar pantalla completa: ${err.message}`);
                    });
                } else {
                    document.exitFullscreen();
                }
            });
        }
        
        // Escuchar evento de salida de pantalla completa
        document.addEventListener('fullscreenchange', () => {
            const viewerCard = viewport.closest('.svg-viewer-card');
            if (viewerCard && !document.fullscreenElement) {
                viewerCard.classList.remove('fullscreen-mode');
            }
        });
        
        // ── Drag to Pan (Arrastrar para navegar) ──
        let isDown = false;
        let startX, startY;
        let scrollLeft, scrollTop;
        
        viewport.addEventListener('mousedown', (e) => {
            // Evitar conflicto si se hace clic en botones de control o toolbar
            if (e.target.closest('.viewer-btn') || e.target.closest('.svg-viewer-toolbar')) return;
            
            isDown = true;
            viewport.classList.add('grabbing');
            
            startX = e.pageX - viewport.offsetLeft;
            startY = e.pageY - viewport.offsetTop;
            scrollLeft = viewport.scrollLeft;
            scrollTop = viewport.scrollTop;
        });
        
        viewport.addEventListener('mouseleave', () => {
            isDown = false;
            viewport.classList.remove('grabbing');
        });
        
        viewport.addEventListener('mouseup', () => {
            isDown = false;
            viewport.classList.remove('grabbing');
        });
        
        viewport.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - viewport.offsetLeft;
            const y = e.pageY - viewport.offsetTop;
            const walkX = (x - startX) * 1.5; // multiplicador de velocidad
            const walkY = (y - startY) * 1.5;
            viewport.scrollLeft = scrollLeft - walkX;
            viewport.scrollTop = scrollTop - walkY;
        });
        
        // Zoom directo con la rueda del ratón (centrado bajo el cursor)
        viewport.addEventListener('wheel', (e) => {
            e.preventDefault();
            
            // 1. Obtener coordenadas del mouse dentro del viewport
            const rect = viewport.getBoundingClientRect();
            const mouseX = e.clientX - rect.left;
            const mouseY = e.clientY - rect.top;
            
            // 2. Calcular la posición correspondiente en el contenido con scrollbar
            const contentX = mouseX + viewport.scrollLeft;
            const contentY = mouseY + viewport.scrollTop;
            
            const oldScale = scale;
            let newScale = scale;
            
            if (e.deltaY < 0) {
                // Zoom In (Acercar)
                if (scale < maxScale) {
                    newScale = Math.min(maxScale, scale + 0.25);
                }
            } else {
                // Zoom Out (Alejar)
                if (scale > minScale) {
                    newScale = Math.max(minScale, scale - 0.25);
                }
            }
            
            if (newScale !== oldScale) {
                scale = newScale;
                updateScale();
                
                // 3. Ajustar el scroll del viewport para que el punto bajo el mouse permanezca estático
                const ratio = newScale / oldScale;
                viewport.scrollLeft = contentX * ratio - mouseX;
                viewport.scrollTop = contentY * ratio - mouseY;
            }
        }, { passive: false });
    }

});
