/**
 * M2 — Un DOM mínimo para pantallas que se enganchan por atributos data-*
 * (grafo-conocimiento.js, alertas-medicas.js, consulta-*.js): nodos con hijos,
 * dataset, clases, atributos, eventos y selectores [data-x], [data-x="v"],
 * .clase y :checked; closest, remove e insertAdjacentElement('afterend').
 * No es un DOM completo; solo lo que usan esos archivos.
 */
function kebab(texto) {
    return texto.replace(/[A-Z]/g, letra => '-' + letra.toLowerCase());
}

function textoNodo(texto) {
    return { tagName: '#text', hijos: [], textContent: texto, todos: () => [], dataset: {}, atributos: {}, clases: new Set() };
}

function crearNodo(etiqueta) {
    const clases = new Set();
    const atributos = {};
    const n = {
        tagName: etiqueta.toUpperCase(), hijos: [], padre: null, hidden: false, disabled: false, checked: false,
        value: '', type: '', href: '', target: '', rel: '', title: '', name: '', dataset: {}, escuchas: {}, propiedades: {},
        _texto: '',
        get textContent() { return n._texto + n.hijos.map(h => h.textContent).join(''); },
        set textContent(valor) { n._texto = String(valor); n.hijos = []; },
        get className() { return [...clases].join(' '); },
        set className(valor) { clases.clear(); String(valor).split(/\s+/).filter(Boolean).forEach(c => clases.add(c)); },
        classList: {
            add: (...cs) => cs.forEach(c => clases.add(c)),
            remove: (...cs) => cs.forEach(c => clases.delete(c)),
            toggle: (c, si) => (si === undefined ? (clases.has(c) ? clases.delete(c) : clases.add(c)) : (si ? clases.add(c) : clases.delete(c))),
            contains: c => clases.has(c),
        },
        style: { setProperty: (nombre, valor) => { n.propiedades[nombre] = valor; } },
        get firstChild() { return n.hijos[0] || null; },
        setAttribute: (a, v) => { atributos[a] = String(v); },
        getAttribute: a => (a in atributos ? atributos[a] : null),
        addEventListener(tipo, fn) { (n.escuchas[tipo] = n.escuchas[tipo] || []).push(fn); },
        disparar(tipo, evento = {}) {
            const objeto = { target: n, preventDefault() { objeto.prevenido = true; }, ...evento };
            (n.escuchas[tipo] || []).forEach(fn => fn(objeto));
            return objeto;
        },
        dispatchEvent(evento) {
            if (!evento.target) evento.target = n;
            (n.escuchas[evento.type] || []).forEach(fn => fn(evento));
            return true;
        },
        closest(selector) {
            for (let actual = n; actual; actual = actual.padre) {
                if (coincide(actual, selector)) return actual;
            }
            return null;
        },
        remove() {
            if (n.padre) n.padre.hijos = n.padre.hijos.filter(h => h !== n);
            n.padre = null;
        },
        /** Copia propiedades, dataset, atributos, clases y, si es profundo, los hijos; no los escuchas. */
        cloneNode(profundo) {
            const copia = crearNodo(n.tagName.toLowerCase());
            for (const clave of ['hidden', 'disabled', 'checked', 'value', 'type', 'href', 'title', 'name', 'id', 'required', 'placeholder']) {
                if (Object.prototype.hasOwnProperty.call(n, clave)) copia[clave] = n[clave];
            }
            Object.assign(copia.dataset, n.dataset);
            Object.assign(copia.atributos, n.atributos);
            n.clases.forEach(c => copia.clases.add(c));
            copia._texto = n._texto;
            if (profundo) n.hijos.forEach(h => copia.appendChild(h.tagName === '#text' ? textoNodo(h.textContent) : h.cloneNode(true)));
            return copia;
        },
        insertAdjacentElement(posicion, nuevo) {
            if (posicion !== 'afterend' || !n.padre) throw new Error('Solo afterend con padre');
            const hermanos = n.padre.hijos;
            nuevo.padre = n.padre;
            hermanos.splice(hermanos.indexOf(n) + 1, 0, nuevo);
            return nuevo;
        },
        click() { n.disparar('click'); },
        focus() { n.enfocado = true; },
        appendChild(h) { h.padre = n; n.hijos.push(h); return h; },
        append(...hs) { hs.forEach(h => n.appendChild(typeof h === 'string' ? textoNodo(h) : h)); },
        prepend(...hs) { hs.reverse().forEach(h => { const hijo = typeof h === 'string' ? textoNodo(h) : h; hijo.padre = n; n.hijos.unshift(hijo); }); },
        replaceChildren(...hs) { n.hijos = []; n._texto = ''; n.append(...hs); },
        todos() { return n.hijos.flatMap(h => [h, ...h.todos()]); },
        querySelectorAll(selector) { return n.todos().filter(h => coincide(h, selector)); },
        querySelector(selector) { return n.querySelectorAll(selector)[0] || null; },
        atributos, clases,
    };
    return n;
}

function valorDato(n, nombre) {
    for (const [clave, dato] of Object.entries(n.dataset)) {
        if (kebab(clave) === nombre) return String(dato);
    }
    return null;
}

/** [data-x], [data-x="v"], varios seguidos, más .clase o :checked al final; o solo .clase. */
function coincide(n, selector) {
    if (n.tagName === '#text' || selector === '') return false;
    const partes = selector.match(/^((?:\[[a-z-]+(?:="[^"]*")?\])*)(?:\.([a-z_-]+)|(:checked))?$/);
    if (!partes) return false;
    const [, atributos, clase, marcado] = partes;
    for (const [, atributo, valor] of atributos.matchAll(/\[([a-z-]+)(?:="([^"]*)")?\]/g)) {
        let actual = atributo.startsWith('data-') ? valorDato(n, atributo.slice(5)) : null;
        if (actual === null && atributo in n.atributos) actual = n.atributos[atributo];
        if (actual === null) return false;
        if (valor !== undefined && actual !== valor) return false;
    }
    if (clase !== undefined && !n.clases.has(clase)) return false;
    if (marcado !== undefined && !n.checked) return false;
    return true;
}

/** Documento con escuchas y una raíz donde la prueba arma el HTML de la pantalla. */
function crearDocumento() {
    const documento = { escuchas: {}, raiz: crearNodo('body') };
    documento.createElement = etiqueta => crearNodo(etiqueta);
    documento.createTextNode = textoNodo;
    documento.addEventListener = (tipo, fn) => { (documento.escuchas[tipo] = documento.escuchas[tipo] || []).push(fn); };
    documento.querySelector = selector => documento.raiz.querySelector(selector);
    documento.querySelectorAll = selector => documento.raiz.querySelectorAll(selector);
    documento.disparar = (tipo, evento = {}) => (documento.escuchas[tipo] || []).forEach(fn => fn(evento));
    documento.dispatchEvent = evento => { documento.disparar(evento.type, evento); return true; };
    documento.getElementById = id => documento.raiz.todos().find(h => h.id === id) || null;
    return documento;
}

/** Crea un nodo con dataset y propiedades y lo cuelga de padre. */
function colgar(padre, etiqueta, dataset = {}, extra = {}) {
    const n = crearNodo(etiqueta);
    Object.assign(n.dataset, dataset);
    Object.assign(n, extra);
    padre.appendChild(n);
    return n;
}

module.exports = { crearNodo, textoNodo, coincide, crearDocumento, colgar };
