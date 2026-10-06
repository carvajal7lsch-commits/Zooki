// Lectura mínima de un PDF impreso por Chrome: en qué página quedó cada
// destino con nombre (los ids de los títulos). Sirve para numerar el índice,
// que Chrome no sabe numerar por sí solo.
//
// Chrome escribe los objetos sin comprimir y sin flujos de objetos, con el
// catálogo apuntando a /Pages (árbol de páginas) y /Dests (diccionario
// nombre → [página /XYZ …]). Si eso cambiara en una versión de Chrome, la
// función lo detecta y falla con un mensaje claro en vez de numerar mal.

function objetos(pdf) {
    const texto = pdf.toString('latin1');
    const mapa = new Map();
    const patron = /(?:^|\n)(\d+) 0 obj\s*([\s\S]*?)\nendobj/g;
    let m;
    while ((m = patron.exec(texto))) {
        // Solo interesa el diccionario de cada objeto, no su flujo.
        const cuerpo = m[2];
        const corte = cuerpo.indexOf('stream');
        mapa.set(Number(m[1]), corte === -1 ? cuerpo : cuerpo.slice(0, corte));
    }
    return mapa;
}

function referencia(diccionario, clave) {
    const m = diccionario.match(new RegExp(`/${clave} (\\d+) 0 R`));
    return m ? Number(m[1]) : null;
}

/** @returns {Record<string, number>} nombre del destino → número de página (desde 1) */
export function paginasDeDestinos(pdf) {
    const objs = objetos(pdf);
    const catalogo = [...objs.values()].find(d => /\/Type \/Catalog\b/.test(d));
    if (!catalogo) throw new Error('El PDF no tiene catálogo legible (¿cambió el formato de Chrome?).');

    // Orden de las páginas: recorrido en profundidad del árbol /Pages.
    const orden = new Map();
    const recorrer = numero => {
        const nodo = objs.get(numero) || '';
        if (/\/Type \/Page\b/.test(nodo)) {
            orden.set(numero, orden.size + 1);
            return;
        }
        const hijos = nodo.match(/\/Kids \[([^\]]*)\]/);
        if (hijos) [...hijos[1].matchAll(/(\d+) 0 R/g)].forEach(h => recorrer(Number(h[1])));
    };
    recorrer(referencia(catalogo, 'Pages'));

    const dests = objs.get(referencia(catalogo, 'Dests')) || '';
    const paginas = {};
    for (const m of dests.matchAll(/\/([^\s/[\]<>()]+)\s*\[(\d+) 0 R/g)) {
        // Los nombres PDF escapan caracteres especiales como #xx.
        const nombre = m[1].replace(/#([0-9a-f]{2})/gi, (_, h) => String.fromCharCode(parseInt(h, 16)));
        if (orden.has(Number(m[2]))) paginas[nombre] = orden.get(Number(m[2]));
    }
    return paginas;
}

export function contarPaginas(pdf) {
    const m = pdf.toString('latin1').match(/\/Type \/Pages\s*\/Count (\d+)/g) || [];
    return Math.max(0, ...m.map(x => Number(x.match(/(\d+)$/)[1])));
}
