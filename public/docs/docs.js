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
    
    // Tabla de Contenidos (TOC)
    const tocList = document.getElementById('toc-list');

    // Tema claro/oscuro
    const themeToggleBtn = document.getElementById('theme-toggle');

    // Paleta de búsqueda global (Ctrl+K) — único buscador del portal, vive
    // en el header superior (escritorio) y como ícono en el header móvil.
    const globalSearchTrigger = document.getElementById('global-search-trigger');
    const mobileSearchTrigger = document.getElementById('mobile-search-trigger');
    const cpOverlay = document.getElementById('command-palette-overlay');
    const cpInput = document.getElementById('cp-input');
    const cpResults = document.getElementById('cp-results');

    // Metadatos de cada documento para la búsqueda global (ícono + etiqueta)
    const docLabels = {
        'readme': { icon: 'fa-house-chimney', label: 'Inicio / Sinopsis' },
        'ficha': { icon: 'fa-file-invoice', label: 'Ficha Técnica' },
        'ers': { icon: 'fa-clipboard-list', label: 'Requisitos (ERS)' },
        'reglas': { icon: 'fa-scale-balanced', label: 'Reglas de Negocio' },
        'hu': { icon: 'fa-users', label: 'Historias de Usuario' },
        're': { icon: 'fa-clipboard-check', label: 'Requisitos Específicos' },
        'mer': { icon: 'fa-diagram-project', label: 'Modelo Entidad-Relación' },
        'backlog': { icon: 'fa-list-check', label: 'Backlog (Jira)' }
    };

    // ═══════════════════════════════════════════════════════════════
    // 0. SLUGS ESTABLES PARA ANCLAS DE ENCABEZADOS
    // ═══════════════════════════════════════════════════════════════
    // IMPORTANTE: debe producir exactamente el mismo resultado que
    // zooki_slugify() en index.php (mismo mapa de acentos, mismo orden de
    // pasos) para que los links del índice de navegación y los ids
    // generados en el DOM coincidan. Si se edita una, edita también la otra.
    const ACCENT_MAP = {
        'á': 'a', 'é': 'e', 'í': 'i', 'ó': 'o', 'ú': 'u', 'ñ': 'n', 'ü': 'u',
        'Á': 'a', 'É': 'e', 'Í': 'i', 'Ó': 'o', 'Ú': 'u', 'Ñ': 'n', 'Ü': 'u'
    };

    function slugify(text) {
        let result = text.replace(/[áéíóúñüÁÉÍÓÚÑÜ]/g, (ch) => ACCENT_MAP[ch] || ch);
        result = result.toLowerCase();
        result = result.replace(/[^a-z0-9]+/g, '-');
        return result.replace(/^-+|-+$/g, '');
    }

    // Crea una función de slug con deduplicación de colisiones (-2, -3, ...)
    // acotada al documento actual, igual que hace zooki_extract_headings() en PHP.
    function createSlugger() {
        const used = new Map();
        return function (text) {
            let slug = slugify(text) || 'section';
            if (used.has(slug)) {
                const n = used.get(slug) + 1;
                used.set(slug, n);
                slug = `${slug}-${n}`;
            } else {
                used.set(slug, 1);
            }
            return slug;
        };
    }

    // ═══════════════════════════════════════════════════════════════
    // 1. CARGA DINÁMICA DE DOCUMENTOS (SPA)
    // ═══════════════════════════════════════════════════════════════
    let currentDocId = 'readme';
    let rawDocumentContent = ''; // Almacena el markdown original sin renderizar

    // Índice de navegación (títulos + encabezados con slug) de los 9 documentos,
    // precargado para construir el sidebar colapsable y la búsqueda global.
    let navIndex = {};
    // Caché de markdown crudo por documento, usada por la búsqueda global
    // para encontrar coincidencias fuera de los encabezados.
    const docBodyCache = {};
    // Slug pendiente de resaltar en el sidebar una vez el índice de
    // navegación (y por tanto los sub-ítems) exista.
    let pendingSlugToHighlight = null;

    async function loadDocument(docId, targetSlug = null) {
        if (!docId) docId = 'readme';
        currentDocId = docId;
        pendingSlugToHighlight = targetSlug;

        // Mostrar indicador de carga
        loadingSpinner.style.display = 'block';
        docRenderArea.style.opacity = '0.3';

        // Actualizar estado activo en la barra lateral. La expansión de la
        // sub-navegación NO se toca aquí: queda siempre bajo control del
        // usuario (solo la abre/cierra el chevron), para que navegar entre
        // documentos no despliegue listas por su cuenta.
        menuItems.forEach(item => {
            const row = item.closest('.menu-item-row');
            const isActive = item.getAttribute('data-doc') === docId;
            item.classList.toggle('active', isActive);
            if (row) row.classList.toggle('active', isActive);
        });

        let scrollTarget = null;

        try {
            const response = await fetch(`index.php?action=get_doc&id=${docId}`);
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }
            
            rawDocumentContent = await response.text();
            docBodyCache[docId] = rawDocumentContent;

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
        <p>Identificación del proyecto, stack tecnológico, arquitectura, equipo, cronograma, riesgos y presupuesto.</p>
    </a>
    <a href="#ers" class="docs-card">
        <h3><i class="fa-solid fa-clipboard-list"></i> Requisitos (ERS)</h3>
        <p>Especificación IEEE 830: requisitos funcionales y no funcionales, casos de uso, diccionario de datos y trazabilidad.</p>
    </a>
    <a href="#reglas" class="docs-card">
        <h3><i class="fa-solid fa-scale-balanced"></i> Reglas de Negocio</h3>
        <p>Catálogo de reglas (RN) por módulos: restricciones, cálculos, procesos y estructura del dominio.</p>
    </a>
    <a href="#hu" class="docs-card">
        <h3><i class="fa-solid fa-users"></i> Historias de Usuario</h3>
        <p>55 historias por módulos con narrativa, criterios de aceptación, prioridad y estado real de implementación.</p>
    </a>
    <a href="#re" class="docs-card">
        <h3><i class="fa-solid fa-clipboard-check"></i> Requisitos Específicos</h3>
        <p>Desglose de cada historia en requisitos verificables con su criterio de aceptación y trazabilidad RN → HU → RE.</p>
    </a>
    <a href="#mer" class="docs-card">
        <h3><i class="fa-solid fa-diagram-project"></i> Modelo Entidad-Relación</h3>
        <p>Estructura de la base de datos MySQL, definición de tablas principales, claves y diagrama interactivo.</p>
    </a>
    <a href="#backlog" class="docs-card">
        <h3><i class="fa-solid fa-list-check"></i> Backlog (Jira)</h3>
        <p>Historias organizadas por sprint con su estado, estimación y trazabilidad hacia el tablero de Jira.</p>
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

            // Si la navegación pedía un encabezado específico (#doc--slug),
            // localizarlo para desplazarnos hacia él en el bloque finally
            // (evita que el scrollTo(top) de abajo lo pise).
            if (targetSlug) {
                scrollTarget = document.getElementById(targetSlug);
            }
            updateActiveSubmenuItem(docId, targetSlug);

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
            if (scrollTarget) {
                scrollTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
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

        const slugger = createSlugger();
        headings.forEach((heading) => {
            // Asignar un id estable basado en el texto del encabezado (slug),
            // para que enlace de forma consistente desde el sidebar y la
            // búsqueda global, no sólo desde este TOC.
            heading.id = slugger(heading.textContent);

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
        
        // El margen superior negativo descuenta el header fijo: sin él, un
        // encabezado se marcaba como activo mientras todavía estaba oculto
        // detrás de la barra superior.
        const topbarHeight = parseInt(
            getComputedStyle(document.documentElement).getPropertyValue('--topbar-height'), 10
        ) || 64;

        const observerOptions = {
            root: null,
            rootMargin: `-${topbarHeight + 20}px 0px -60% 0px`,
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
    // 3.5. ÍNDICE DE NAVEGACIÓN Y SUB-MENÚS COLAPSABLES DEL SIDEBAR
    // ═══════════════════════════════════════════════════════════════

    // Carga el índice de navegación (títulos + encabezados) de los 9
    // documentos en una sola petición, construye la sub-navegación
    // colapsable del sidebar, y precarga en segundo plano el contenido
    // completo de cada documento para alimentar la búsqueda global.
    async function loadNavIndex() {
        try {
            const response = await fetch('index.php?action=get_index');
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }
            navIndex = await response.json();

            buildSidebarSubmenus();

            if (pendingSlugToHighlight) {
                updateActiveSubmenuItem(currentDocId, pendingSlugToHighlight);
            }

            warmDocBodyCache();
        } catch (error) {
            console.error('Error cargando índice de navegación:', error);
        }
    }

    // Inyecta, bajo cada ítem del menú lateral que tenga secciones (H2),
    // un botón de expandir/colapsar y una lista de sub-ítems enlazando a
    // '#docId--slug'. Los sub-ítems solo muestran H2 (no H3): el detalle
    // fino de cada documento largo sigue disponible en el TOC de la derecha
    // una vez abierto, y es alcanzable también desde la búsqueda global.
    function buildSidebarSubmenus() {
        menuItems.forEach(menuItem => {
            const docId = menuItem.getAttribute('data-doc');
            const doc = navIndex[docId];
            if (!doc || !doc.headings) return;

            const li = menuItem.parentElement;
            if (!li || li.querySelector('.menu-item-row')) return; // Evitar duplicados

            // Todo ítem se envuelve en una fila, tenga o no sub-secciones:
            // así la pastilla del estado activo cubre también el chevron,
            // en vez de dejarlo suelto por fuera del resaltado.
            const row = document.createElement('div');
            row.className = 'menu-item-row';
            if (menuItem.classList.contains('active')) {
                row.classList.add('active');
            }
            li.insertBefore(row, menuItem);
            row.appendChild(menuItem);

            const sections = doc.headings.filter(h => h.level === 2);
            if (sections.length === 0) return;

            const toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.className = 'submenu-toggle';
            toggleBtn.setAttribute('aria-label', 'Expandir secciones');
            toggleBtn.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
            row.appendChild(toggleBtn);

            const submenu = document.createElement('ul');
            submenu.className = 'submenu';

            sections.forEach(section => {
                const subLi = document.createElement('li');
                const subA = document.createElement('a');
                subA.className = 'submenu-item';
                subA.href = `#${docId}--${section.slug}`;
                subA.textContent = section.text;
                subA.title = section.text;
                subA.setAttribute('data-doc', docId);
                subA.setAttribute('data-slug', section.slug);
                subLi.appendChild(subA);
                submenu.appendChild(subLi);
            });

            li.appendChild(submenu);

            toggleBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                li.classList.toggle('expanded');
            });
        });
    }

    // Resalta el sub-ítem del sidebar correspondiente al slug activo.
    function updateActiveSubmenuItem(docId, slug) {
        document.querySelectorAll('.submenu-item.active').forEach(el => el.classList.remove('active'));
        if (!slug) return;
        const match = document.querySelector(`.submenu-item[data-doc="${docId}"][data-slug="${slug}"]`);
        if (match) match.classList.add('active');
    }

    // Descarga en segundo plano el markdown crudo de todos los documentos
    // (a través del endpoint get_doc ya existente) para que la búsqueda
    // global pueda encontrar coincidencias en el cuerpo del texto, no sólo
    // en los títulos de sección. No repite peticiones ya cacheadas por
    // loadDocument().
    function warmDocBodyCache() {
        Object.keys(navIndex).forEach(docId => {
            if (docBodyCache[docId]) return;
            fetch(`index.php?action=get_doc&id=${docId}`)
                .then(res => res.text())
                .then(text => { docBodyCache[docId] = text; })
                .catch(() => {});
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // 4. UTILIDAD DE ESCAPE HTML (usada por la paleta de búsqueda global)
    // ═══════════════════════════════════════════════════════════════
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
        const rawHash = window.location.hash.replace('#', '');

        // Los enlaces a un encabezado específico usan '--' como separador
        // entre el id del documento y el slug (ej. '#hu--hu-17-...'). Ese
        // separador nunca puede aparecer dentro de un slug generado, porque
        // slugify()/zooki_slugify() siempre colapsan guiones consecutivos.
        const sepIndex = rawHash.indexOf('--');
        const hash = sepIndex === -1 ? rawHash : rawHash.slice(0, sepIndex);
        const targetSlug = sepIndex === -1 ? null : rawHash.slice(sepIndex + 2);

        // Mapeo de hashes permitidos
        const validDocs = ['readme', 'ficha', 'ers', 'reglas', 'hu', 're', 'mer', 'backlog'];

        if (validDocs.includes(hash)) {
            loadDocument(hash, targetSlug);
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

    // Precargar el índice de navegación (usado por la sub-navegación
    // colapsable del sidebar y por la búsqueda global) en paralelo.
    loadNavIndex();

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

    // ═══════════════════════════════════════════════════════════════
    // 7. TEMA CLARO / OSCURO
    // ═══════════════════════════════════════════════════════════════

    // Mermaid fija su tema al inicializar y no repinta los diagramas ya
    // renderizados por su cuenta: hay que reinicializarlo con la paleta
    // correcta cada vez que cambia el tema.
    function applyMermaidTheme() {
        if (typeof mermaid === 'undefined') return;
        const isLight = document.documentElement.getAttribute('data-theme') === 'light';
        mermaid.initialize({
            startOnLoad: false,
            theme: isLight ? 'default' : 'dark',
            themeVariables: isLight ? {
                background: '#ffffff',
                primaryColor: '#dbeafe',
                primaryTextColor: '#0f172a',
                primaryBorderColor: '#3b82f6',
                lineColor: '#64748b',
                secondaryColor: '#f1f5f9',
                tertiaryColor: '#e2e8f0'
            } : {
                background: '#22242b',
                primaryColor: '#3b82f6',
                primaryTextColor: '#f3f4f6',
                lineColor: '#8b95a8',
                secondaryColor: '#2a2e37',
                tertiaryColor: '#17181c'
            }
        });
    }

    function updateThemeToggleIcon(theme) {
        if (!themeToggleBtn) return;
        themeToggleBtn.innerHTML = theme === 'light'
            ? '<i class="fa-solid fa-moon"></i><span>Oscuro</span>'
            : '<i class="fa-solid fa-sun"></i><span>Claro</span>';
    }

    function applyTheme(theme) {
        if (theme === 'light') {
            document.documentElement.setAttribute('data-theme', 'light');
        } else {
            document.documentElement.removeAttribute('data-theme');
        }
        try { localStorage.setItem('zooki-docs-theme', theme); } catch (e) { /* localStorage no disponible */ }
        updateThemeToggleIcon(theme);
    }

    // Sincronizar el ícono del botón con el tema ya aplicado por el script
    // anti-parpadeo del <head>, e inicializar Mermaid en consecuencia.
    const initialTheme = document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
    updateThemeToggleIcon(initialTheme);
    applyMermaidTheme();

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const current = document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
            const next = current === 'light' ? 'dark' : 'light';
            applyTheme(next);
            applyMermaidTheme();

            // Los diagramas Mermaid ya renderizados quedan con la paleta
            // anterior: si el documento activo tiene alguno, recargarlo para
            // que se vuelva a dibujar con el tema nuevo.
            if (docRenderArea.querySelector('.mermaid')) {
                loadDocument(currentDocId);
            }
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // 8. PALETA DE BÚSQUEDA GLOBAL (Ctrl+K)
    // ═══════════════════════════════════════════════════════════════

    function openCommandPalette() {
        if (!cpOverlay) return;
        cpOverlay.classList.add('visible');
        cpInput.value = '';
        updateClearButton();
        runGlobalSearch('');
        setTimeout(() => cpInput.focus(), 0);
    }

    function closeCommandPalette() {
        if (!cpOverlay) return;
        cpOverlay.classList.remove('visible');
    }

    if (globalSearchTrigger) {
        globalSearchTrigger.addEventListener('click', openCommandPalette);
    }

    if (mobileSearchTrigger) {
        mobileSearchTrigger.addEventListener('click', openCommandPalette);
    }

    if (cpOverlay) {
        cpOverlay.addEventListener('click', (e) => {
            if (e.target === cpOverlay) closeCommandPalette();
        });
    }

    // ¿El foco está en un campo de texto? Ahí "/" es un carácter, no un atajo.
    function isTypingInField(target) {
        if (!target) return false;
        const tag = target.tagName;
        return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || target.isContentEditable;
    }

    document.addEventListener('keydown', (e) => {
        const paletteOpen = cpOverlay && cpOverlay.classList.contains('visible');
        const isCtrlK = (e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k';
        // "/" abre el buscador, como anuncia el recuadro del navbar. Solo
        // cuando no se está escribiendo en un campo y sin modificadores.
        const isSlash = e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey
            && !paletteOpen && !isTypingInField(e.target);

        if (isCtrlK) {
            e.preventDefault();
            if (paletteOpen) {
                closeCommandPalette();
            } else {
                openCommandPalette();
            }
        } else if (isSlash) {
            e.preventDefault(); // Evita que el "/" se escriba en el input
            openCommandPalette();
        } else if (e.key === 'Escape' && paletteOpen) {
            closeCommandPalette();
        }
    });

    let cpSearchTimeout = null;
    let cpSearchToken = 0; // Descarta respuestas de búsquedas ya superadas
    let cpSelectedIndex = -1; // Ítem resaltado por las flechas del teclado

    // El botón de limpiar solo aparece cuando hay algo escrito.
    function updateClearButton() {
        const btn = document.getElementById('cp-clear');
        if (btn) btn.hidden = cpInput.value.length === 0;
    }

    const cpClearBtn = document.getElementById('cp-clear');
    if (cpClearBtn) {
        cpClearBtn.addEventListener('click', () => {
            cpInput.value = '';
            updateClearButton();
            runGlobalSearch('');
            cpInput.focus();
        });
    }

    if (cpInput) {
        cpInput.addEventListener('input', () => {
            clearTimeout(cpSearchTimeout);
            updateClearButton();
            const query = cpInput.value.trim();
            cpSearchTimeout = setTimeout(() => runGlobalSearch(query), 120);
        });

        cpInput.addEventListener('keydown', (e) => {
            const items = Array.from(cpResults.querySelectorAll('.cp-result-item'));

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                moveSelection(items, 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                moveSelection(items, -1);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const target = items[cpSelectedIndex] || items[0];
                if (target) target.click();
            }
        });
    }

    // Mueve el resaltado del teclado y lo mantiene a la vista dentro de la
    // lista, que tiene scroll propio.
    function moveSelection(items, delta) {
        if (items.length === 0) return;

        cpSelectedIndex += delta;
        if (cpSelectedIndex < 0) cpSelectedIndex = items.length - 1;
        if (cpSelectedIndex >= items.length) cpSelectedIndex = 0;

        items.forEach((item, i) => item.classList.toggle('selected', i === cpSelectedIndex));
        items[cpSelectedIndex].scrollIntoView({ block: 'nearest' });
    }

    // ── Historial de búsquedas recientes (por navegador) ──
    const RECENT_KEY = 'zooki-docs-recent-searches';
    const RECENT_MAX = 5;

    function getRecentSearches() {
        try {
            const raw = localStorage.getItem(RECENT_KEY);
            const list = raw ? JSON.parse(raw) : [];
            return Array.isArray(list) ? list.slice(0, RECENT_MAX) : [];
        } catch (e) {
            return []; // localStorage bloqueado o dato corrupto: sin historial
        }
    }

    function rememberSearch(query) {
        if (!query) return;
        try {
            const list = getRecentSearches().filter(q => q.toLowerCase() !== query.toLowerCase());
            list.unshift(query);
            localStorage.setItem(RECENT_KEY, JSON.stringify(list.slice(0, RECENT_MAX)));
        } catch (e) { /* sin historial si localStorage no está disponible */ }
    }

    function removeRecentSearch(query) {
        try {
            const list = getRecentSearches().filter(q => q !== query);
            localStorage.setItem(RECENT_KEY, JSON.stringify(list));
        } catch (e) { /* sin historial si localStorage no está disponible */ }
    }

    // Estado inicial de la paleta: búsquedas recientes o el mensaje vacío.
    function renderRecentSearches() {
        const recent = getRecentSearches();
        cpResults.innerHTML = '';

        if (recent.length === 0) {
            cpResults.innerHTML = '<div class="cp-empty">Sin búsquedas recientes</div>';
            setAttribution(null);
            return;
        }

        const groupEl = document.createElement('div');
        groupEl.className = 'cp-group';
        groupEl.innerHTML = '<div class="cp-group-title"><i class="fa-solid fa-clock-rotate-left"></i> Recientes</div>';

        recent.forEach(query => {
            // Contenedor <div> en vez de <button>: dentro va el botón de
            // borrar, y anidar un botón dentro de otro es HTML inválido.
            const el = document.createElement('div');
            el.className = 'cp-result-item';
            el.setAttribute('role', 'option');
            el.innerHTML = `
                <span class="cp-item-icon"><i class="fa-solid fa-clock-rotate-left"></i></span>
                <span class="cp-item-text">
                    <span class="cp-item-title">${escapeHTML(query)}</span>
                </span>
            `;

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'cp-remove-recent';
            removeBtn.setAttribute('aria-label', `Quitar "${query}" del historial`);
            removeBtn.title = 'Quitar del historial';
            removeBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
            removeBtn.addEventListener('click', (e) => {
                // Sin esto, el clic también dispararía la búsqueda de la fila.
                e.stopPropagation();
                removeRecentSearch(query);
                renderRecentSearches();
                cpInput.focus();
            });
            el.appendChild(removeBtn);

            el.addEventListener('click', () => {
                cpInput.value = query;
                updateClearButton();
                runGlobalSearch(query);
                cpInput.focus();
            });
            groupEl.appendChild(el);
        });

        cpResults.appendChild(groupEl);
        setAttribution(null);
    }

    // Pie de la paleta: atribución a Algolia, requerida por su plan gratuito.
    // Se muestra siempre que Algolia sea el motor configurado, pero si una
    // búsqueda concreta terminó respondiéndola el respaldo local, se dice
    // explícitamente para no atribuirle a Algolia resultados que no son suyos.
    function setAttribution(engine) {
        const el = document.getElementById('cp-attribution');
        if (!el) return;

        if (engine === 'local') {
            el.textContent = 'Búsqueda local';
        } else if (engine === 'algolia' || algoliaConfig) {
            el.innerHTML = 'Search by <span class="cp-algolia">Algolia</span>';
        } else {
            el.textContent = 'Búsqueda local';
        }
    }

    // ── Motor de búsqueda: Algolia con respaldo local ──
    // Algolia (si hay credenciales) aporta tolerancia a tildes y a errores
    // de tipeo, que la coincidencia por subcadena local no puede dar. Si la
    // petición falla —sin internet, cuota agotada, servicio caído— se cae
    // automáticamente al buscador local para que el portal nunca se quede
    // sin búsqueda.
    const algoliaConfig = window.ZOOKI_ALGOLIA || null;

    // Marcadores de resaltado propios: Algolia devuelve el texto con estas
    // etiquetas alrededor de las coincidencias. Se escapa TODO el texto y
    // recién después se cambian los marcadores por <mark>, para que el
    // contenido nunca pueda inyectar HTML.
    const HL_OPEN = '__ZOOKI_HL__';
    const HL_CLOSE = '__/ZOOKI_HL__';

    function highlightToSafeHTML(text) {
        return escapeHTML(text)
            .split(HL_OPEN).join('<mark>')
            .split(HL_CLOSE).join('</mark>');
    }

    async function runGlobalSearch(query) {
        if (!cpResults) return;

        cpSelectedIndex = -1;

        if (!query) {
            renderRecentSearches();
            return;
        }

        const token = ++cpSearchToken;
        let groups = null;
        let engine = 'local';

        if (algoliaConfig) {
            try {
                groups = await searchWithAlgolia(query);
                engine = 'algolia';
            } catch (error) {
                console.warn('Algolia no disponible, usando búsqueda local:', error.message);
                groups = null;
            }
        }

        if (groups === null) {
            groups = searchLocally(query);
        }

        // Si el usuario siguió escribiendo, esta respuesta ya no sirve.
        if (token !== cpSearchToken) return;

        renderSearchGroups(groups, query, engine);
    }

    // Consulta el índice de Algolia vía su API REST. No se carga el cliente
    // oficial: una sola petición no justifica sumar otra librería por CDN.
    async function searchWithAlgolia(query) {
        const { appId, searchKey, indexName } = algoliaConfig;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 4000);

        try {
            const params = new URLSearchParams({
                query,
                hitsPerPage: '20',
                attributesToSnippet: 'content:30',
                highlightPreTag: HL_OPEN,
                highlightPostTag: HL_CLOSE
            });

            const response = await fetch(
                `https://${appId}-dsn.algolia.net/1/indexes/${encodeURIComponent(indexName)}/query`,
                {
                    method: 'POST',
                    headers: {
                        'X-Algolia-API-Key': searchKey,
                        'X-Algolia-Application-Id': appId,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ params: params.toString() }),
                    signal: controller.signal
                }
            );

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            return groupAlgoliaHits(data.hits || []);
        } finally {
            clearTimeout(timeout);
        }
    }

    // Agrupa los hits de Algolia por documento, respetando el orden de
    // relevancia con que llegaron. Cada hit es UNA tarjeta: el encabezado
    // como título y el extracto del cuerpo como descripción debajo.
    function groupAlgoliaHits(hits) {
        const byDoc = new Map();

        hits.forEach(hit => {
            if (!byDoc.has(hit.docId)) {
                byDoc.set(hit.docId, { docId: hit.docId, items: [] });
            }
            const group = byDoc.get(hit.docId);
            if (group.items.length >= 5) return;

            const highlight = hit._highlightResult || {};
            const snippet = hit._snippetResult || {};

            const titleHTML = highlight.heading
                ? highlightToSafeHTML(highlight.heading.value)
                : escapeHTML(hit.heading || '');

            let descHTML = null;
            if (snippet.content && snippet.content.matchLevel !== 'none') {
                descHTML = `&hellip;${highlightToSafeHTML(snippet.content.value)}&hellip;`;
            }

            group.items.push({
                href: hit.url || `#${hit.docId}--${hit.slug}`,
                titleHTML,
                descHTML
            });
        });

        return Array.from(byDoc.values());
    }

    // Búsqueda local de respaldo: títulos de sección (navIndex) y cuerpo de
    // cada documento (docBodyCache), por coincidencia de subcadena.
    function searchLocally(query) {
        const q = query.toLowerCase();
        const groups = [];

        Object.keys(navIndex).forEach(docId => {
            const doc = navIndex[docId];
            const items = [];

            (doc.headings || [])
                .filter(h => h.text.toLowerCase().includes(q))
                .slice(0, 5)
                .forEach(h => {
                    items.push({
                        href: `#${docId}--${h.slug}`,
                        titleHTML: escapeHTML(h.text),
                        descHTML: null
                    });
                });

            const body = docBodyCache[docId];
            if (body) {
                const idx = body.toLowerCase().indexOf(q);
                if (idx >= 0) {
                    const start = Math.max(0, idx - 40);
                    const end = Math.min(body.length, idx + q.length + 60);
                    // Quitar ruido de sintaxis Markdown cruda (#, -, |, *, saltos
                    // de línea) al inicio del fragmento y normalizar espacios,
                    // ya que el snippet viene del .md sin renderizar.
                    const before = body.slice(start, idx)
                        .replace(/\s+/g, ' ')
                        .replace(/^[#\-|*_\s]+/, '');
                    const match = body.slice(idx, idx + q.length);
                    const after = body.slice(idx + q.length, end).replace(/\s+/g, ' ');

                    items.push({
                        href: `#${docId}`,
                        titleHTML: escapeHTML(doc.title || docId),
                        descHTML: `&hellip;${escapeHTML(before)}<mark>${escapeHTML(match)}</mark>${escapeHTML(after)}&hellip;`
                    });
                }
            }

            if (items.length > 0) {
                groups.push({ docId, items });
            }
        });

        return groups;
    }

    // Pinta en la paleta los resultados ya normalizados, vengan de donde vengan.
    function renderSearchGroups(groups, query, engine) {
        setAttribution(engine);

        if (groups.length === 0) {
            cpResults.innerHTML = `<div class="cp-empty">Sin resultados para "${escapeHTML(query)}"</div>`;
            return;
        }

        cpResults.innerHTML = '';

        groups.forEach(group => {
            const info = docLabels[group.docId] || { icon: 'fa-file', label: group.docId };

            const groupEl = document.createElement('div');
            groupEl.className = 'cp-group';
            groupEl.innerHTML = `<div class="cp-group-title"><i class="fa-solid ${info.icon}"></i> ${escapeHTML(info.label)}</div>`;

            group.items.forEach(item => {
                const el = document.createElement('a');
                el.className = 'cp-result-item';
                el.href = item.href;
                el.setAttribute('role', 'option');
                el.innerHTML = `
                    <span class="cp-item-icon"><i class="fa-regular fa-file-lines"></i></span>
                    <span class="cp-item-text">
                        <span class="cp-item-title">${item.titleHTML}</span>
                        ${item.descHTML ? `<span class="cp-item-desc">${item.descHTML}</span>` : ''}
                    </span>
                    <span class="cp-item-enter" aria-hidden="true">&crarr;</span>
                `;
                el.addEventListener('click', () => {
                    rememberSearch(query);
                    closeCommandPalette();
                });
                groupEl.appendChild(el);
            });

            cpResults.appendChild(groupEl);
        });
    }

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
