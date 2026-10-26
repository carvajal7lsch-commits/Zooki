const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const codigo = fs.readFileSync(path.join(__dirname, '../../public/js/plataforma.js'), 'utf8');

function pagina() {
    const eventos = {};
    const solicitudes = [];
    const confirmaciones = [];
    const memoria = new Map();
    const tarjetas = { dataset: { listaVista: 'tarjetas' }, hidden: false };
    const tabla = { dataset: { listaVista: 'tabla' }, hidden: true };
    let actualizado = false;
    const contexto = {
        TextEncoder,
        window: { location: { href: 'index.php?action=plataforma_inicio' }, zookiToast: () => {} },
        localStorage: { getItem: clave => memoria.get(clave), setItem: (clave, valor) => memoria.set(clave, valor) },
        document: {
            addEventListener: (tipo, callback) => { eventos[tipo] = callback; },
            querySelectorAll: selector => selector === '[data-lista-vista]' ? [tarjetas, tabla] : [],
            querySelector: () => null,
            createElement: () => ({ setAttribute() {} }),
            getElementById: () => ({ replaceWith: () => { actualizado = true; } }),
        },
        DOMParser: class {
            parseFromString() { return { getElementById: () => ({}) }; }
        },
        FormData: class { constructor(form) { this.form = form; } },
        zookiConfirmarCorto: async (...opciones) => { confirmaciones.push(opciones); return true; },
        fetch: async (url, opciones) => {
            solicitudes.push([url, opciones]);
            return { ok: true, json: async () => ({ success: true, message: 'Guardado' }), text: async () => '' };
        },
    };
    vm.runInNewContext(codigo, contexto);
    function form(accion, estado = 'activa') {
        const salida = { textContent: '' };
        return {
            dataset: { plataformaAccion: accion }, elements: { estado: { value: estado } },
            querySelectorAll: () => [], querySelector: selector => selector === '[data-resultado]' ? salida : null, reportValidity: () => true,
        };
    }
    return { contexto, eventos, solicitudes, confirmaciones, memoria, tarjetas, tabla, form, api: contexto.window.ZookiPlataforma, actualizado: () => actualizado };
}

test('E1: cargar y elegir vista no envía correo ni hace peticiones', () => {
    const p = pagina();
    p.eventos.DOMContentLoaded();
    assert.equal(p.tarjetas.hidden, false);
    assert.equal(p.tabla.hidden, true);
    assert.equal(p.solicitudes.length, 0);
    p.api.vista('tabla');
    assert.equal(p.tabla.hidden, false);
    p.eventos.DOMContentLoaded();
    assert.equal(p.tarjetas.hidden, true);
    assert.equal(p.tabla.hidden, false);
});

test('P1: el enlace del correo abre Soporte y después conserva la pestaña que el usuario elige', () => {
    const p = pagina();
    p.contexto.window.location.hash = '#soporte';
    p.contexto.window.location.href += '#soporte';
    p.contexto.window.history = { replaceState: (_estado, _titulo, url) => {
        p.contexto.window.location.href = url;
        p.contexto.window.location.hash = '';
    } };
    p.eventos.DOMContentLoaded();
    assert.equal(p.memoria.get('zooki-plataforma-pestana'), 'soporte');
    p.api.pestana('clinicas');
    p.eventos.DOMContentLoaded();
    assert.equal(p.memoria.get('zooki-plataforma-pestana'), 'clinicas');
});

test('E1: motivo valida mientras se escribe y el máximo también cuenta bytes', () => {
    const p = pagina();
    const ayuda = {};
    const campo = { value: ' ', dataset: { maxBytes: '65535' }, parentElement: { querySelector: () => ayuda }, setAttribute: () => {}, setCustomValidity: valor => { campo.error = valor; } };
    assert.equal(p.api.validar(campo), false);
    assert.match(ayuda.textContent, /motivo/);
    campo.value = 'Revisión válida';
    assert.equal(p.api.validar(campo), true);
    assert.equal(campo.error, '');
    campo.value = 'ñ'.repeat(32768);
    assert.equal(p.api.validar(campo), false);
});

test('S1: fecha fin valida en vivo el calendario, incluido bisiesto, y respeta gratuito', () => {
    const p = pagina();
    const salida = {};
    const campo = { value: '', required: true, disabled: false, parentElement: { querySelector: () => salida }, setCustomValidity: valor => { campo.error = valor; }, setAttribute() {} };
    assert.equal(p.api.validarFechaConcesion(campo), false);
    campo.value = '2026-02-29';
    assert.equal(p.api.validarFechaConcesion(campo), false);
    campo.value = '2028-02-29';
    assert.equal(p.api.validarFechaConcesion(campo), true);
    campo.value = '';
    campo.disabled = true;
    assert.equal(p.api.validarFechaConcesion(campo), true);
    assert.equal(campo.error, '');
});

test('E1: destructivas confirman y reactivar usa aviso breve', () => {
    const p = pagina();
    assert.ok(p.api.confirmacion(p.form('plataforma_estado_ajax', 'baja')));
    for (const [accion, estado] of [['plataforma_estado_ajax', 'suspendida'], ['plataforma_suscripcion_ajax', 'cancelada'], ['plataforma_caso_ajax', 'resuelto'], ['plataforma_aviso_ajax', 'activa'], ['plataforma_desbloquear_ajax', 'activa']]) {
        assert.equal(p.api.confirmacion(p.form(accion, estado)), null);
    }
    assert.equal(p.api.confirmacion(p.form('plataforma_estado_ajax', 'activa')), null);
    assert.equal(p.api.confirmacion(p.form('plataforma_suscripcion_ajax', 'activa')), null);
});

test('E1: cancelar confirmación no modifica ni envía avisos', async () => {
    const p = pagina();
    p.contexto.zookiConfirmarCorto = async () => false;
    const form = p.form('plataforma_estado_ajax', 'baja');
    form.closest = () => null;
    p.contexto.document.body = {};
    await p.api.enviar(form, { disabled: false });
    assert.equal(p.solicitudes.length, 0);
});

test('E1: envío explícito actualiza el panel antes del toast y usa POST', async () => {
    const p = pagina();
    let actualizadoAlAvisar = false;
    p.contexto.window.zookiToast = () => { actualizadoAlAvisar = p.actualizado(); };
    await p.api.enviar(p.form('plataforma_aviso_ajax'), { disabled: false });
    assert.equal(p.confirmaciones.length, 0);
    assert.equal(p.solicitudes[0][0], 'index.php?action=plataforma_aviso_ajax');
    assert.equal(p.solicitudes[0][1].method, 'POST');
    assert.equal(actualizadoAlAvisar, true);
});

test('E1: doble envío no repite la petición mientras espera confirmación', async () => {
    const p = pagina();
    let resolver;
    p.contexto.zookiConfirmarCorto = () => new Promise(resolve => { resolver = resolve; });
    const form = p.form('plataforma_estado_ajax', 'baja');
    form.closest = () => null;
    p.contexto.document.body = {};
    const primero = p.api.enviar(form, { disabled: false });
    await p.api.enviar(form, { disabled: false });
    resolver(true);
    await primero;
    assert.equal(p.solicitudes.filter(([, opciones]) => opciones.method === 'POST').length, 1);
});

// M0.2.1: el modal «Gestionar» se prueba en M021Gestionar.test.cjs.
test('E2: la plataforma usa componentes administrativos y su modal no entra en la capa superior nativa', () => {
    const vista = fs.readFileSync(path.join(__dirname, '../../views/plataforma/inicio.php'), 'utf8');
    for (const elemento of ['css/styles.css', 'css/dashboard.css', 'css/usuarios.css', 'person-card', 'personal-grid', 'users-modal', 'users-table', 'view-toggle', 'tabs-wrapper', 'search-input', 'filter-select']) assert.ok(vista.includes(elemento));
    assert.doesNotMatch(vista, /<dialog|data-validacion-cuenta|\sstyle=|\sonclick=/);
    const css = fs.readFileSync(path.join(__dirname, '../../public/css/plataforma.css'), 'utf8');
    assert.doesNotMatch(css, /(?:^|\n)(?:body|button|input|:root|\.person-card|\.users-modal)\s*\{/);
});
