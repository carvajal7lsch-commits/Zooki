const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const raiz = path.join(__dirname, '../..');
const codigo = (archivo) => fs.readFileSync(path.join(raiz, archivo), 'utf8');

// ── avisos.js: recargar sin esperar al aviso ─────────────────────────────

function avisos(almacen) {
    const toasts = [];
    const recargas = [];
    const eventos = {};
    const entorno = {
        console,
        JSON,
        sessionStorage: almacen,
        document: { addEventListener: (tipo, callback) => { eventos[tipo] = callback; } },
        Swal: { fire: (opciones) => { toasts.push([opciones.title, opciones.icon]); return Promise.resolve({}); } },
    };
    entorno.window = { Swal: entorno.Swal, location: { reload: () => recargas.push('recarga') } };
    vm.runInNewContext(codigo('public/js/avisos.js'), entorno);
    return { entorno, toasts, recargas, eventos };
}

function memoria() {
    const datos = new Map();
    return {
        datos,
        getItem: (clave) => (datos.has(clave) ? datos.get(clave) : null),
        setItem: (clave, valor) => datos.set(clave, valor),
        removeItem: (clave) => datos.delete(clave),
    };
}

test('recarga de inmediato y el aviso sale después de recargar, una sola vez', () => {
    const almacen = memoria();
    const antes = avisos(almacen);
    antes.entorno.zookiRecargarConAviso('Cita cancelada.');
    assert.deepEqual(antes.recargas, ['recarga']);
    assert.equal(antes.toasts.length, 0);

    const despues = avisos(almacen);
    despues.eventos.DOMContentLoaded();
    assert.deepEqual(despues.toasts, [['Cita cancelada.', 'success']]);
    despues.eventos.DOMContentLoaded();
    assert.equal(despues.toasts.length, 1);
});

test('sin sessionStorage el aviso se muestra igual y la recarga no espera', () => {
    const almacen = {
        getItem() { throw new Error('Bloqueado'); },
        setItem() { throw new Error('Bloqueado'); },
        removeItem() { throw new Error('Bloqueado'); },
    };
    const pagina = avisos(almacen);
    pagina.entorno.zookiRecargarConAviso('Listo', 'success');
    assert.deepEqual(pagina.toasts, [['Listo', 'success']]);
    assert.deepEqual(pagina.recargas, ['recarga']);
    assert.doesNotThrow(() => pagina.eventos.DOMContentLoaded());
});

test('un aviso guardado con un tipo desconocido sale como éxito', () => {
    const almacen = memoria();
    almacen.setItem('zooki.avisoPendiente', JSON.stringify({ mensaje: 'Hola', tipo: '<script>' }));
    const pagina = avisos(almacen);
    pagina.eventos.DOMContentLoaded();
    assert.deepEqual(pagina.toasts, [['Hola', 'success']]);
});

test('ningún JS espera a que se cierre un aviso para recargar', () => {
    const carpeta = path.join(raiz, 'public/js');
    const espera = /\.then\(\s*\(\)\s*=>\s*(window\.)?location\.reload|setTimeout\(\s*\(\)\s*=>\s*(window\.)?location\.reload|await\s+mensaje\([^)]*\);\s*(window\.)?location\.reload/;
    const culpables = fs.readdirSync(carpeta)
        .filter((archivo) => archivo.endsWith('.js'))
        .filter((archivo) => espera.test(fs.readFileSync(path.join(carpeta, archivo), 'utf8')));
    assert.deepEqual(culpables, []);
});

// ── interacciones.js: teléfono con los caracteres del servidor ──────────────

function interacciones() {
    const eventos = {};
    const entorno = { document: { addEventListener: (tipo, callback) => { eventos[tipo] = callback; } } };
    vm.runInNewContext(codigo('public/js/interacciones.js'), entorno);
    return { eventos, entorno };
}

test('el teléfono descarta letras y símbolos y no pasa de su máximo al escribir o pegar', () => {
    const { eventos } = interacciones();
    const campo = { value: '+57 (300) abc-123 4567 890', maxLength: 20, dataset: { caracteres: '0-9+\\s-' } };
    eventos.input({ target: campo });
    assert.equal(campo.value, '+57 300 -123 4567 89');
    assert.equal(campo.value.length, 20);
});

test('un campo sin data-caracteres no se toca', () => {
    const { eventos } = interacciones();
    const campo = { value: 'Hola!', maxLength: -1, dataset: {} };
    eventos.input({ target: campo });
    assert.equal(campo.value, 'Hola!');
});

test('la vista del portal toma los límites del teléfono de ValidadorTelefono', () => {
    const vista = codigo('views/portal/index.php');
    // Hasta value=: el ?> de cada atributo PHP cortaría un [^>]*.
    const campo = vista.match(/<input type="tel" name="telefono" id="portal_contact_phone".*?value=/);
    assert.ok(campo);
    // D1: maxlength, pattern y data-caracteres salen de un solo método.
    assert.match(campo[0], /<\?= ValidadorTelefono::atributosHtml\(\) \?>/);
    assert.doesNotMatch(vista, /id="portalContactEditForm"[^>]*onsubmit/);
});

// ── Portal: imprimir historial desde el detalle de la mascota ────────────────

test('el detalle de la mascota tiene el botón de imprimir y portal.js le pone su enlace', () => {
    const vista = codigo('views/portal/index.php');
    const portal = codigo('public/js/portal.js');
    const detalle = vista.slice(vista.indexOf('id="screen-pet-detail"'));
    assert.match(detalle, /<a href="#" id="btnImprimirFichaMascota" class="btn-imprimir-ficha" hidden>/);
    assert.match(detalle.slice(0, detalle.indexOf('</header>')), /Imprimir historial/);
    const verDetalle = portal.slice(portal.indexOf('async function verDetalle'), portal.indexOf('renderSummary(m);'));
    assert.match(verDetalle, /imprimir\.href = `index\.php\?action=portal_imprimir_historial&id_mascota=\$\{encodeURIComponent\(m\.id_mascota\)\}`/);
    assert.match(verDetalle, /imprimir\.hidden = false/);
});

// ── usuarios.js: búsqueda, rol y estado en tarjetas y tabla ──────────────────

function fila(datos) {
    return { dataset: datos, hidden: false };
}

function paginaFiltros() {
    const tarjetas = [
        fila({ buscar: 'ana norte 1001', rol: '1', estado: '1' }),
        fila({ buscar: 'beto vet 1002', rol: '2', estado: '1' }),
        fila({ buscar: 'hugo vet 1008', rol: '2', estado: '0' }),
    ];
    const tabla = tarjetas.map((t) => fila({ ...t.dataset }));
    const vacio = (filas) => ({ hidden: true });
    const vistas = [tarjetas, tabla].map((filas) => {
        const sinResultados = vacio(filas);
        return {
            filas,
            sinResultados,
            querySelectorAll: (selector) => (selector === '[data-fila]' ? filas : []),
            querySelector: (selector) => (selector === '[data-sin-resultados]' ? sinResultados : null),
        };
    });
    const panel = { querySelectorAll: (selector) => (selector === '[data-vista-contenido]' ? vistas : []) };
    const campos = {
        texto: { value: '' },
        rol: { value: '' },
        estado: { value: '' },
    };
    const eventosBarra = {};
    const barra = {
        dataset: { filtros: 'personal' },
        classList: { toggle() {} },
        addEventListener: (tipo, callback) => { eventosBarra[tipo] = callback; },
        querySelector(selector) {
            if (selector === '[data-filtro-texto]') return campos.texto;
            if (selector === '[data-filtro-rol]') return campos.rol;
            if (selector === '[data-filtro-estado]:checked') return campos.estado;
            return null;
        },
    };
    const modulo = {
        dataset: {},
        addEventListener() {},
        querySelectorAll: (selector) => (selector === '[data-filtros]' ? [barra] : []),
        querySelector(selector) {
            if (selector === '[data-panel="personal"]') return panel;
            if (selector === '[data-filtros="personal"]') return barra;
            return null;
        },
    };
    const campo = { addEventListener() {} };
    const modal = { querySelector: () => ({}), addEventListener() {} };
    const form = { elements: { documento: campo, email: campo }, addEventListener() {} };
    const document = {
        getElementById: (id) => ({ usuariosModulo: modulo, usuarioModal: modal, usuarioForm: form })[id],
        addEventListener() {},
    };
    const almacen = { getItem: () => null, setItem() {} };
    vm.runInNewContext(codigo('public/js/usuarios.js'), { document, localStorage: almacen, console });
    return { tarjetas, tabla, vistas, campos, eventosBarra };
}

const visibles = (filas) => filas.filter((f) => !f.hidden).map((f) => f.dataset.buscar.split(' ')[0]);

test('buscar, rol y estado filtran a la vez tarjetas y tabla', () => {
    const p = paginaFiltros();
    p.campos.rol.value = '2';
    p.eventosBarra.change();
    assert.deepEqual(visibles(p.tarjetas), ['beto', 'hugo']);
    assert.deepEqual(visibles(p.tabla), ['beto', 'hugo']);
    p.campos.estado.value = '0';
    p.eventosBarra.change();
    assert.deepEqual(visibles(p.tarjetas), ['hugo']);
    p.campos.estado.value = '';
    p.campos.rol.value = '';
    p.campos.texto.value = '  1002 ';
    p.eventosBarra.input();
    assert.deepEqual(visibles(p.tarjetas), ['beto']);
    assert.deepEqual(visibles(p.tabla), ['beto']);
});

test('sin coincidencias se muestra el aviso de sin resultados en las dos vistas', () => {
    const p = paginaFiltros();
    p.campos.texto.value = 'nadie';
    p.eventosBarra.input();
    assert.deepEqual(visibles(p.tarjetas), []);
    assert.equal(p.vistas[0].sinResultados.hidden, false);
    assert.equal(p.vistas[1].sinResultados.hidden, false);
    p.campos.texto.value = '';
    p.eventosBarra.input();
    assert.equal(p.vistas[0].sinResultados.hidden, true);
});
