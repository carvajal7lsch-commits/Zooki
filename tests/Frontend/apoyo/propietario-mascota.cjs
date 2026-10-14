/**
 * M5 C4b — Un DOM falso para probar propietario-mascota.js (y piezas de
 * medical-module.js): nodos con hijos, clases, atributos y eventos, y el
 * módulo cargado con fetch y temporizadores que controla cada prueba.
 * Salió de M5C3Personal.test.cjs para usarlo también en M5C4bPersonal.
 */
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const raiz = path.join(__dirname, '../../..');
const codigo = (archivo) => fs.readFileSync(path.join(raiz, archivo), 'utf8');

/** Un nodo con lo que usan propietario-mascota.js y medical-module.js. */
function nodo(etiqueta = 'div') {
    const atributos = {};
    const clases = new Set();
    const n = {
        tagName: etiqueta.toUpperCase(),
        hijos: [],
        padre: null,
        textContent: '',
        hidden: false,
        disabled: false,
        dataset: {},
        type: '',
        src: '',
        alt: '',
        value: '',
        escuchas: {},
        get className() { return [...clases].join(' '); },
        set className(valor) { clases.clear(); String(valor).split(/\s+/).filter(Boolean).forEach(c => clases.add(c)); },
        classList: {
            add: (c) => clases.add(c),
            remove: (c) => clases.delete(c),
            toggle: (c, si) => (si ? clases.add(c) : clases.delete(c)),
            contains: (c) => clases.has(c),
        },
        setAttribute: (a, v) => { atributos[a] = String(v); },
        getAttribute: (a) => (a in atributos ? atributos[a] : null),
        addEventListener(tipo, fn) { (n.escuchas[tipo] = n.escuchas[tipo] || []).push(fn); },
        // Como el DOM real, append también acepta texto suelto.
        append(...hijos) { hijos.map(h => (typeof h === 'string' ? texto(h) : h)).forEach(h => { h.padre = n; n.hijos.push(h); }); },
        prepend(h) { h.padre = n; n.hijos.unshift(h); },
        replaceChildren(...hijos) { n.hijos = []; n.append(...hijos); },
        remove() { if (n.padre) n.padre.hijos = n.padre.hijos.filter(h => h !== n); },
        replaceWith(otro) {
            const lista = n.padre.hijos;
            otro.padre = n.padre;
            lista[lista.indexOf(n)] = otro;
        },
        focus() { n.enfocado = true; },
        closest(selector) {
            let actual = n;
            while (actual) {
                if (selector === 'button' && actual.tagName === 'BUTTON') return actual;
                actual = actual.padre;
            }
            return null;
        },
        matches: () => false,
        querySelector(selector) {
            return todos(n).find(h => selector.includes('aviso-solo-lectura') && h.classList.contains('aviso-solo-lectura')) || null;
        },
        get texto() { return [n.textContent, ...n.hijos.map(h => h.texto)].join(' ').trim(); },
    };
    return n;
}

/** Un nodo de texto: solo su contenido. */
function texto(contenido) {
    return { tagName: '#TEXT', textContent: contenido, hijos: [], classList: { contains: () => false }, get texto() { return contenido; } };
}

function todos(raiz) {
    return raiz.hijos.flatMap(h => [h, ...todos(h)]);
}

function botones(raiz) {
    return todos(raiz).filter(h => h.tagName === 'BUTTON');
}

/** propietario-mascota.js con un DOM, fetch y temporizadores que controla cada prueba. */
function modulo({ respuestas = [] } = {}) {
    const ids = {};
    ['mascotasExistentes', 'petOwnerDoc', 'ownerSearchWrapper', 'selectedOwnerInfo', 'ownerSearchAyuda', 'ownerSearchInput'].forEach(id => {
        ids[id] = nodo(id === 'ownerSearchInput' || id === 'petOwnerDoc' ? 'input' : 'div');
    });
    ids.selectedOwnerInfo.hidden = true;
    const escuchas = {};
    const pedidos = [];
    const avisos = [];
    const temporizadores = [];
    let recargas = 0;
    const contexto = {
        console,
        FormData: class { constructor() { this.datos = {}; } set(k, v) { this.datos[k] = v; } append(k, v) { this.datos[k] = v; } },
        document: {
            getElementById: (id) => ids[id] || null,
            createElement: (etiqueta) => nodo(etiqueta),
            addEventListener: (tipo, fn) => { (escuchas[tipo] = escuchas[tipo] || []).push(fn); },
        },
        window: { location: { href: 'index.php?action=vet_pacientes', reload: () => { recargas++; } } },
        setTimeout: (fn, ms) => { temporizadores.push({ fn, ms }); return temporizadores.length; },
        clearTimeout: (id) => { if (temporizadores[id - 1]) temporizadores[id - 1].fn = null; },
        zookiToast: (mensaje, tipo = 'success') => avisos.push([mensaje, tipo]),
        fetch: async (url, opciones = {}) => {
            pedidos.push({ url, opciones });
            const respuesta = respuestas.shift() || { success: true };
            return { json: async () => respuesta, text: async () => '<html></html>' };
        },
        DOMParser: class { parseFromString() { return { getElementById: () => null }; } },
    };
    contexto.window.document = contexto.document;
    vm.createContext(contexto);
    vm.runInContext(codigo('public/js/propietario-mascota.js'), contexto);
    const correr = async () => {
        const pendientes = temporizadores.splice(0).filter(t => t.fn);
        pendientes.forEach(t => t.fn());
        await new Promise(r => setImmediate(r));
        await new Promise(r => setImmediate(r));
    };
    const escribir = (valor) => {
        ids.ownerSearchInput.value = valor;
        ids.ownerSearchInput.matches = (selector) => selector === '[data-buscar-propietario]';
        escuchas.input.forEach(fn => fn({ target: ids.ownerSearchInput }));
    };
    const clic = async (boton) => {
        escuchas.click.forEach(fn => fn({ target: boton }));
        await new Promise(r => setImmediate(r));
        await new Promise(r => setImmediate(r));
    };
    return { api: contexto.window.ZookiPropietarioMascota, ids, pedidos, avisos, temporizadores, correr, escribir, clic, recargas: () => recargas };
}

module.exports = { nodo, todos, botones, modulo, codigo };
