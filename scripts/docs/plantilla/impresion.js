// Arma el documento para imprimir con el formato de la plantilla de documentos
// del proyecto: portada, ficha del documento, contenido, encabezado y pie en
// cada hoja, y rótulos APA 7 en tablas y figuras (specs/exportar-documentos.md).
// Lo abre scripts/docs/exportar.mjs en Chrome sin interfaz, espera
// window.__zooki.listo e imprime a PDF con el motor de impresión de Chrome,
// que repite el encabezado de las tablas y respeta break-after: avoid.
// Paged.js se descartó porque separaba el título de una tabla de sus filas.

(function () {
    'use strict';

    const datos = JSON.parse(document.getElementById('datos').textContent);

    // ── Utilidades ──
    function el(tag, clase, texto) {
        const nodo = document.createElement(tag);
        if (clase) nodo.className = clase;
        if (texto !== undefined) nodo.textContent = texto;
        return nodo;
    }

    const ACENTOS = { 'á': 'a', 'é': 'e', 'í': 'i', 'ó': 'o', 'ú': 'u', 'ñ': 'n', 'ü': 'u' };
    const usados = new Map();
    function slug(texto) {
        let s = texto.toLowerCase().replace(/[áéíóúñü]/g, c => ACENTOS[c])
            .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'seccion';
        const n = (usados.get(s) || 0) + 1;
        usados.set(s, n);
        return n > 1 ? `${s}-${n}` : s;
    }

    // Los ids de los títulos son además los nombres de destino del PDF, que
    // exportar.mjs lee para numerar el índice. Con prefijo, un id nunca
    // empieza con número (#1-introduccion) y es un selector válido.
    const PREFIJO = 's-';

    // Título de la sección que contiene un elemento: el encabezado anterior
    // más cercano. APA titula cada tabla y figura; el .md no lo hace, y el
    // nombre de la sección es lo que mejor describe su contenido.
    function tituloDeSeccion(nodo) {
        let actual = nodo.previousElementSibling;
        while (actual) {
            if (/^H[2-6]$/.test(actual.tagName)) return actual.textContent.trim();
            actual = actual.previousElementSibling;
        }
        return datos.titulo;
    }

    // ── Marca: logo y nombre, para la portada y el encabezado ──
    // Un SVG con tamaño propio: en el encabezado (margen de @page) la imagen
    // no se puede escalar con CSS, así que el tamaño viaja en el archivo.
    function marca(anchoCm, altoCm) {
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${anchoCm}cm" height="${altoCm}cm" viewBox="0 0 330 90">`
            + (datos.logo ? `<image href="${datos.logo}" x="0" y="0" width="90" height="90"/>` : '')
            + '<text x="104" y="64" font-family="Arial, Helvetica, sans-serif" font-weight="bold" font-size="54" fill="#1565C0">Zooki</text></svg>';
        return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
    }

    // ── Encabezado y pie de cada hoja, salvo la portada (EX-9) ──
    // Van en las cajas de margen de @page porque dependen del documento
    // (título y revisión); el resto del formato está en documento.css.
    function encabezadoYPie() {
        const cadena = t => '"' + String(t).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"';
        const estilo = el('style');
        estilo.textContent = `@page {
            @top-left { content: url("${marca(3.3, 0.9)}"); }
            @top-center { content: ${cadena(datos.proyecto || '')} "\\A" ${cadena(datos.titulo)}; }
            @top-right { content: ${datos.revision ? cadena('Rev. ' + datos.revision) + ' "\\A" ' : ''}"Pág. " counter(page); }
            @bottom-right { content: ${cadena([datos.institucion, datos.curso].filter(Boolean).join(' · '))}; }
        }
        @page :first {
            @top-left { content: none; border: none; }
            @top-center { content: none; border: none; }
            @top-right { content: none; border: none; }
            @bottom-right { content: none; border: none; }
        }`;
        document.head.appendChild(estilo);
    }

    // ── Portada (EX-3) ──
    // Como la plantilla: hoja en blanco arriba, el bloque del título entre
    // líneas dobles a la derecha, y abajo el logo y el mes.
    function portada() {
        const sec = el('section', 'portada');
        const bloque = el('div', 'portada-bloque');
        bloque.appendChild(el('div', 'linea-doble'));
        bloque.appendChild(el('h1', 'portada-titulo', datos.titulo));
        if (datos.proyecto) bloque.appendChild(el('p', 'portada-proyecto', `Proyecto: ${datos.proyecto}`));
        if (datos.subtitulo) bloque.appendChild(el('p', 'portada-revision', datos.subtitulo));
        const autoria = el('div', 'portada-autoria');
        [
            datos.autor && `Elaborado por: ${datos.autor}`,
            datos.instructor && `Instructor: ${datos.instructor}`,
            [datos.programa, datos.curso].filter(Boolean).join(' · '),
            [datos.institucion, datos.centro].filter(Boolean).join(' · '),
            datos.ciudad
        ].filter(Boolean).forEach(linea => autoria.appendChild(el('p', null, linea)));
        bloque.appendChild(autoria);
        sec.appendChild(bloque);

        const pie = el('div', 'portada-pie');
        pie.appendChild(el('div', 'linea-doble'));
        const fila = el('div', 'portada-pie-fila');
        const logo = el('img', 'portada-marca');
        logo.src = marca(4.4, 1.2);
        logo.alt = '';
        fila.appendChild(logo);
        fila.appendChild(el('span', 'portada-fecha', datos.fecha));
        pie.appendChild(fila);
        sec.appendChild(pie);
        return sec;
    }

    // ── Ficha del documento (EX-10) ──
    // Historial de revisiones y validación por las partes, como en la
    // plantilla: el aprendiz lo elabora y el instructor lo revisa.
    function ficha() {
        const sec = el('section', 'ficha');
        sec.appendChild(el('h2', 'ficha-titulo', 'Ficha del documento'));
        const h = datos.historial || { encabezado: [], filas: [] };
        const tabla = el('table', 'tabla-ficha');
        const cabeza = tabla.createTHead().insertRow();
        h.encabezado.forEach(t => cabeza.appendChild(el('th', null, t)));
        const cuerpoTabla = tabla.createTBody();
        h.filas.forEach(fila => {
            const tr = cuerpoTabla.insertRow();
            fila.forEach(t => tr.appendChild(el('td', null, t)));
        });
        sec.appendChild(tabla);

        sec.appendChild(el('p', 'ficha-validado', 'Documento validado por las partes en fecha: ____________________'));
        const firmas = el('table', 'tabla-firmas');
        const encabezados = firmas.createTHead().insertRow();
        encabezados.appendChild(el('th', null, 'Elaborado por'));
        encabezados.appendChild(el('th', null, 'Revisado por'));
        const cuerpoFirmas = firmas.createTBody();
        const espacio = cuerpoFirmas.insertRow();
        espacio.className = 'firma-espacio';
        espacio.appendChild(el('td'));
        espacio.appendChild(el('td'));
        const nombres = cuerpoFirmas.insertRow();
        nombres.appendChild(el('td', null, `Fdo.: ${datos.autor || ''} — Aprendiz`));
        nombres.appendChild(el('td', null, `Fdo.: ${datos.instructor || ''} — Instructor`));
        sec.appendChild(firmas);
        return sec;
    }

    // ── Cuerpo ──
    function cuerpo() {
        const main = el('main', 'cuerpo');
        const contenido = el('div', 'contenido');
        contenido.innerHTML = marked.parse(datos.markdown, { gfm: true });
        main.appendChild(contenido);

        contenido.querySelectorAll('h2, h3, h4, h5').forEach(h => { h.id = PREFIJO + slug(h.textContent); });

        // EX-8: los enlaces a otros .md no existen dentro del PDF; quedan como texto.
        contenido.querySelectorAll('a[href]').forEach(a => {
            const href = a.getAttribute('href');
            const externo = /^(https?:|mailto:)/i.test(href);
            // El cuerpo aún no está en el documento: se busca dentro de él.
            const destino = href.startsWith('#')
                && contenido.querySelector(`[id="${CSS.escape(PREFIJO + decodeURIComponent(href.slice(1)))}"]`);
            if (destino) {
                a.setAttribute('href', `#${destino.id}`);
            } else if (!externo) {
                a.replaceWith(...a.childNodes);
            }
        });

        // EX-6: «Tabla N» y su título encima. Van en un bloque propio y no como
        // <caption>, porque Chrome sí separa un <caption> de las filas al
        // cambiar de hoja; el bloque lleva break-after: avoid (ver documento.css).
        // Una tabla de una sola fila (Prioridad | Estado | Estimación de cada
        // HU) es una ficha, no una tabla de datos: numerarla repetiría el
        // título que tiene justo encima, decenas de veces por documento.
        let numero = 0;
        contenido.querySelectorAll('table').forEach(tabla => {
            const bloque = el('div', 'tabla-bloque');
            tabla.replaceWith(bloque);
            const filas = tabla.tBodies[0] ? tabla.tBodies[0].rows.length : 0;
            if (filas > 1) {
                const rotulo = el('div', 'tabla-rotulo');
                rotulo.appendChild(el('div', 'tabla-num', `Tabla ${++numero}`));
                rotulo.appendChild(el('div', 'tabla-titulo', tituloDeSeccion(bloque)));
                bloque.appendChild(rotulo);
            }
            bloque.appendChild(tabla);
            columnasCortas(tabla);
        });

        return main;
    }

    // Una columna de valores cortos (RF-0.1, Alta, 1.0) no debe partirse:
    // si no, «RF-0.1» queda en dos renglones. Las demás reparten el ancho.
    const MAX_CORTA = 14;

    function columnasCortas(tabla) {
        const filas = [...tabla.rows];
        const columnas = filas.length ? filas[0].cells.length : 0;
        for (let c = 0; c < columnas; c++) {
            const celdas = filas.map(f => f.cells[c]).filter(Boolean);
            const largo = Math.max(...celdas.slice(1).map(td => td.textContent.trim().length), 0);
            // El encabezado sí puede partirse («Criterio de verificación»).
            if (largo <= MAX_CORTA) celdas.slice(1).forEach(td => td.classList.add('corta'));
        }
    }

    // ── Figuras Mermaid (EX-7) ──
    async function figuras(main) {
        const bloques = main.querySelectorAll('pre > code.language-mermaid');
        if (!bloques.length) return;

        // Sin etiquetas HTML: el texto queda como texto del SVG y no dentro de
        // un <foreignObject>, que algunos visores de PDF no dibujan.
        mermaid.initialize({
            startOnLoad: false,
            theme: 'neutral',
            securityLevel: 'strict',
            htmlLabels: false,
            flowchart: { htmlLabels: false },
            fontFamily: 'Arial, Helvetica, sans-serif'
        });

        let n = 0;
        for (const code of bloques) {
            const pre = code.parentElement;
            n++;
            const figura = el('figure', 'figura');
            figura.appendChild(el('div', 'figura-num', `Figura ${n}`));
            figura.appendChild(el('div', 'figura-titulo', tituloDeSeccion(pre)));
            const { svg } = await mermaid.render(`mmd-${n}`, code.textContent);
            const lienzo = el('div', 'figura-lienzo');
            lienzo.innerHTML = svg;
            figura.appendChild(lienzo);
            pre.replaceWith(figura);
        }
    }

    // ── Tabla de contenido (EX-4) ──
    // Chrome no calcula números de página para un índice. exportar.mjs imprime
    // una vez, lee en qué página quedó cada destino y llama a ponerPaginas()
    // antes de imprimir la versión final. El hueco del número se reserva desde
    // la primera pasada para que el índice no cambie de largo.
    function indice(main) {
        const nav = el('nav', 'indice');
        nav.appendChild(el('h2', 'indice-titulo', 'Contenido'));
        const lista = el('ol', 'indice-lista');
        main.querySelectorAll('.contenido h2, .contenido h3').forEach(h => {
            const li = el('li', h.tagName === 'H2' ? 'nivel-1' : 'nivel-2');
            const a = el('a');
            a.href = `#${h.id}`;
            a.appendChild(el('span', 'indice-texto', h.textContent.trim()));
            a.appendChild(el('span', 'indice-puntos'));
            a.appendChild(el('span', 'indice-pagina', '000'));
            li.appendChild(a);
            lista.appendChild(li);
        });
        nav.appendChild(lista);
        return nav;
    }

    // ── Nada queda solo al pie de una hoja (EX-5, EX-6) ──
    // Chrome no siempre respeta break-after: avoid antes de una tabla larga.
    // Por eso exportar.mjs verifica el resultado: cada título y cada rótulo de
    // tabla forma un par con lo que le sigue; los dos son destinos del PDF
    // (por los enlaces de .destinos-control), y si quedan en hojas distintas
    // se llama a forzarSalto() y se vuelve a imprimir.
    const pares = [];
    let idsControl = 0;

    function idPara(nodo) {
        if (!nodo.id) nodo.id = `c-${++idsControl}`;
        return nodo.id;
    }

    // Lo que sigue a un título: el rótulo de su tabla, su figura o el primer
    // elemento. Lo que sigue a un rótulo: la primera celda de datos.
    function siguienteDe(nodo) {
        if (nodo.classList.contains('tabla-rotulo')) {
            return nodo.parentElement.querySelector('tbody td');
        }
        const sig = nodo.nextElementSibling;
        if (!sig) return null;
        if (sig.classList.contains('tabla-bloque')) return sig.querySelector('.tabla-rotulo, tbody td');
        return sig;
    }

    function prepararControles(main) {
        main.querySelectorAll('.contenido h2, .contenido h3, .contenido h4, .contenido h5, .tabla-rotulo').forEach(nodo => {
            const siguiente = siguienteDe(nodo);
            if (!siguiente) return;
            pares.push([idPara(nodo), idPara(siguiente)]);
        });

        const enlaces = el('div', 'destinos-control');
        enlaces.setAttribute('aria-hidden', 'true');
        pares.flat().forEach(id => {
            const a = el('a', null, '.');
            a.href = `#${id}`;
            enlaces.appendChild(a);
        });
        document.body.appendChild(enlaces);
    }

    // Salto de hoja antes del elemento que quedó solo; si es el rótulo de una
    // tabla, antes de todo su bloque.
    // Un título que pasa a hoja nueva se lleva su tabla: si la tabla tenía su
    // propio salto (porque antes quedó sola su etiqueta), se le quita, o el
    // título quedaría solo en una hoja. Si el título ya empezaba hoja y sigue
    // solo, la tabla no cabe con él: deja de ir entera y se parte, repitiendo
    // su encabezado.
    function forzarSalto(ids) {
        ids.forEach(id => {
            const nodo = document.getElementById(id);
            if (!nodo) return;
            const objetivo = nodo.closest('.tabla-bloque') || nodo;
            const siguiente = objetivo.nextElementSibling;
            if (siguiente && siguiente.classList.contains('tabla-bloque') && !objetivo.classList.contains('tabla-bloque')) {
                if (objetivo.classList.contains('salto')) siguiente.classList.remove('entera');
                siguiente.classList.remove('salto');
            }
            objetivo.classList.add('salto');
        });
    }

    function ponerPaginas(paginas) {
        document.querySelectorAll('.indice-lista a').forEach(a => {
            const n = paginas[a.getAttribute('href').slice(1)];
            a.querySelector('.indice-pagina').textContent = n ? String(n) : '';
        });
    }

    // ── Tablas que caben en una hoja van enteras (EX-6) ──
    // Se miden con el ancho útil de la hoja carta (21,59 cm − 2 × 2,54 cm) y
    // el alto útil sin los márgenes del encabezado (3,4 cm) y del pie (2,6 cm);
    // ver @page en documento.css. Las que caben se marcan para no partirse;
    // las largas se parten fila por fila y repiten su encabezado.
    const ANCHO_UTIL = '16.51cm';
    const ALTO_UTIL_CM = 27.94 - 3.4 - 2.6;

    function pxPorCm() {
        const regla = el('div');
        regla.style.height = '10cm';
        document.body.appendChild(regla);
        const px = regla.getBoundingClientRect().height / 10;
        regla.remove();
        return px;
    }

    function marcarTablasCortas(cm) {
        document.querySelectorAll('.tabla-bloque').forEach(bloque => {
            // Margen del 10 %: la pantalla y la impresión no maquetan idéntico.
            if (bloque.getBoundingClientRect().height < ALTO_UTIL_CM * cm * 0.9) {
                bloque.classList.add('entera');
            }
        });
    }

    // ── Figuras para el Word (EX-7) ──
    // Cada diagrama se pasa a PNG aquí mismo (SVG → canvas, al doble de
    // resolución) con el tamaño que tendrá en la hoja. Una captura de
    // pantalla de la zona salía desfasada; y un PNG, a diferencia del SVG,
    // lo muestra cualquier versión de Word, LibreOffice o Google Docs.
    async function figurasComoPng(cm) {
        const resultado = [];
        for (const svg of document.querySelectorAll('.figura-lienzo svg')) {
            const { width, height } = svg.getBoundingClientRect();
            const copia = svg.cloneNode(true);
            copia.setAttribute('width', width);
            copia.setAttribute('height', height);
            copia.style.maxWidth = 'none';
            const img = new Image();
            img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(new XMLSerializer().serializeToString(copia));
            await img.decode();

            const lienzo = el('canvas');
            lienzo.width = Math.round(width * 2);
            lienzo.height = Math.round(height * 2);
            const ctx = lienzo.getContext('2d');
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, lienzo.width, lienzo.height);
            ctx.drawImage(img, 0, 0, lienzo.width, lienzo.height);
            resultado.push({ png: lienzo.toDataURL('image/png'), anchoCm: width / cm });
        }
        return resultado;
    }

    async function armar() {
        try {
            const main = cuerpo();
            encabezadoYPie();
            document.body.appendChild(portada());
            document.body.appendChild(ficha());
            document.body.appendChild(main);
            await figuras(main);
            document.body.insertBefore(indice(main), main);

            await document.fonts.ready;

            // Las medidas se toman con el ancho útil de la hoja.
            const cm = pxPorCm();
            document.body.style.width = ANCHO_UTIL;
            marcarTablasCortas(cm);
            const pngs = await figurasComoPng(cm);
            document.body.style.width = '';

            prepararControles(main);
            window.__zooki = { listo: true, pares, figuras: pngs, ponerPaginas, forzarSalto };
        } catch (error) {
            window.__zooki = { error: String(error && error.stack || error) };
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', armar);
    } else {
        armar();
    }
})();
