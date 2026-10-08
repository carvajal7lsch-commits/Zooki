const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const raiz = path.join(__dirname, '../..');
const codigo = (archivo) => fs.readFileSync(path.join(raiz, archivo), 'utf8');

function paginaUsuarios(almacen) {
    const botones = ['tarjetas', 'tabla'].map((vista) => ({
        dataset: { vistaUsuarios: vista },
        eventos: {},
        atributos: {},
        classList: { toggle() {} },
        setAttribute(clave, valor) { this.atributos[clave] = valor; },
        addEventListener(tipo, callback) { this.eventos[tipo] = callback; },
    }));
    const paneles = ['tarjetas', 'tabla'].map((vista) => ({ dataset: { vistaContenido: vista } }));
    const campo = { addEventListener() {} };
    const modal = { querySelector: () => ({}), addEventListener() {} };
    const form = { elements: { documento: campo, email: campo }, addEventListener() {} };
    const modulo = {
        dataset: {},
        addEventListener() {},
        querySelectorAll(selector) {
            if (selector === '[data-vista-usuarios]') return botones;
            if (selector === '[data-vista-contenido]') return paneles;
            return [];
        },
    };
    const document = {
        getElementById: (id) => ({ usuariosModulo: modulo, usuarioModal: modal, usuarioForm: form })[id],
        addEventListener() {},
    };
    vm.runInNewContext(codigo('public/js/usuarios.js'), { document, localStorage: almacen, console });
    return { modulo, botones, paneles };
}

test('tarjetas al entrar, tabla al elegirla y tabla tras recargar', () => {
    const datos = new Map();
    const almacen = { getItem: (clave) => datos.get(clave), setItem: (clave, valor) => datos.set(clave, valor) };
    const primera = paginaUsuarios(almacen);
    assert.equal(primera.modulo.dataset.vista, 'tarjetas');
    assert.equal(primera.paneles[0].hidden, false);
    assert.equal(primera.paneles[1].hidden, true);
    primera.botones[1].eventos.click();
    assert.equal(primera.modulo.dataset.vista, 'tabla');
    assert.equal(primera.botones[1].atributos['aria-pressed'], 'true');
    const segunda = paginaUsuarios(almacen);
    assert.equal(segunda.modulo.dataset.vista, 'tabla');
    assert.equal(segunda.paneles[0].hidden, true);
    assert.equal(segunda.paneles[1].hidden, false);
});

test('sin almacenamiento disponible sigue funcionando la vista de tarjetas', () => {
    const almacen = { getItem() { throw new Error('Sin almacenamiento'); }, setItem() {} };
    const pagina = paginaUsuarios(almacen);
    assert.equal(pagina.modulo.dataset.vista, 'tarjetas');
    pagina.botones[1].eventos.click();
    assert.equal(pagina.modulo.dataset.vista, 'tabla');
});

function interacciones() {
    const eventos = {};
    const llamadas = [];
    const entorno = {
        document: { addEventListener: (tipo, callback) => { eventos[tipo] = callback; } },
        mostrarDetalleCita: (id) => llamadas.push(['detalle', id]),
        closeModal: (id) => llamadas.push(['cerrar', id]),
        switchModalTab: (evento, tab) => llamadas.push(['pestana', evento.currentTarget, tab]),
    };
    vm.runInNewContext(codigo('public/js/interacciones.js'), entorno);
    return { eventos, llamadas };
}

function elemento(accion, id = '12') {
    return {
        dataset: { uiAccion: accion, uiId: id },
        hasAttribute: () => false,
        closest(selector) {
            if (selector === '[data-ui-accion]' || selector === '[data-ui-accion][role="button"]') return this;
            return null;
        },
    };
}

test('clic y teclado llegan al detalle con id numérico sin duplicar un evento ya atendido', () => {
    const { eventos, llamadas } = interacciones();
    const tarjeta = elemento('detalle-cita-portal');
    eventos.click({ target: tarjeta });
    eventos.keydown({ target: tarjeta, key: 'Enter', preventDefault() {} });
    eventos.keydown({ target: tarjeta, key: 'Enter', defaultPrevented: true });
    assert.deepEqual(llamadas, [['detalle', 12], ['detalle', 12]]);
});

test('el fondo solo cierra al pulsar el fondo y una pestaña recibe su botón real', () => {
    const { eventos, llamadas } = interacciones();
    const fondo = elemento('cerrar-consulta');
    fondo.hasAttribute = () => true;
    const hijo = { closest: (selector) => selector === '[data-ui-accion]' ? fondo : null };
    eventos.click({ target: hijo });
    assert.equal(llamadas.length, 0);
    eventos.click({ target: fondo });
    assert.deepEqual(llamadas[0], ['cerrar', 'modalConsulta']);
    const boton = elemento('pestana-consulta');
    boton.dataset.tab = 'tabSignos';
    eventos.click({ target: boton });
    assert.deepEqual(llamadas[1], ['pestana', boton, 'tabSignos']);
});

test('un botón deshabilitado y una tecla de un hijo no ejecutan la tarjeta', () => {
    const { eventos, llamadas } = interacciones();
    const tarjeta = elemento('detalle-cita-portal');
    tarjeta.disabled = true;
    eventos.click({ target: tarjeta });
    const hijo = { closest: () => tarjeta };
    eventos.keydown({ target: hijo, key: 'Enter', preventDefault() {} });
    assert.equal(llamadas.length, 0);
});
