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
            getElementById: () => ({ replaceWith: () => { actualizado = true; } }),
        },
        DOMParser: class {
            parseFromString() { return { getElementById: () => ({}) }; }
        },
        FormData: class { constructor(form) { this.form = form; } },
        Swal: { fire: async opciones => { confirmaciones.push(opciones); return { isConfirmed: true }; } },
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
            querySelectorAll: () => [], querySelector: () => salida, reportValidity: () => true,
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
    p.contexto.Swal.fire = async () => ({ isConfirmed: false });
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
    p.contexto.Swal.fire = () => new Promise(resolve => { resolver = resolve; });
    const form = p.form('plataforma_estado_ajax', 'baja');
    form.closest = () => null;
    p.contexto.document.body = {};
    const primero = p.api.enviar(form, { disabled: false });
    await p.api.enviar(form, { disabled: false });
    resolver({ isConfirmed: true });
    await primero;
    assert.equal(p.solicitudes.filter(([, opciones]) => opciones.method === 'POST').length, 1);
});

test('E2: abrir modal, escribir motivo y pulsar Suspender llega al POST sin SweetAlert bloqueante', async () => {
    const p = pagina();
    const clase = new Set();
    const atributo = {};
    const errorCampo = { textContent: '' };
    const motivo = {
        value: '', dataset: { maxBytes: '65535' }, matches: () => true,
        parentElement: { querySelector: () => errorCampo },
        setAttribute() {}, setCustomValidity(valor) { this.error = valor; },
    };
    const botonGuardar = { disabled: false, dataset: {}, hasAttribute: () => false };
    botonGuardar.closest = () => botonGuardar;
    const estados = p.form('plataforma_estado_ajax', 'suspendida');
    estados.elements = { estado: { value: '', options: [{ value: 'suspendida' }, { value: 'baja' }, { value: 'activa' }] }, motivo, id_clinica: { value: '' } };
    estados.reset = () => { motivo.value = ''; };
    estados.querySelectorAll = () => [motivo];
    const salida = { textContent: '' };
    estados.querySelector = selector => selector === 'button' ? botonGuardar : (selector === '[data-error-campo]' ? errorCampo : salida);
    estados.reportValidity = () => motivo.value !== '' && !motivo.error;
    const plan = p.form('plataforma_suscripcion_ajax');
    plan.elements.id_plan = { value: '' };
    plan.querySelector = () => ({ disabled: false });
    const modal = {
        classList: { add: valor => clase.add(valor) }, setAttribute: (clave, valor) => { atributo[clave] = valor; },
        querySelectorAll: () => [estados],
        querySelector: selector => selector.includes('suscripcion') ? plan : (selector.includes('estado_ajax') ? estados : (selector === 'select' ? { focus() {} } : {})),
        showModal() { clase.add('dialog-nativo'); },
    };
    const obtenerOriginal = p.contexto.document.getElementById;
    p.contexto.document.getElementById = id => id === 'gestionarClinica' ? modal : obtenerOriginal(id);
    const gestionar = { dataset: { gestionarClinica: '1', nombre: 'Norte', estado: 'activa', plan: '1' }, hasAttribute: () => false };
    p.eventos.click({ target: { closest: () => gestionar } });
    motivo.value = 'Verificar el titular';
    p.eventos.input({ target: motivo });
    p.contexto.Swal.fire = () => { throw new Error('La suspensión no debe esperar otra confirmación'); };
    // El clic activa la validación nativa y luego el submit que delega la pantalla.
    p.eventos.click({ target: botonGuardar });
    let cancelado = false;
    if (estados.reportValidity()) p.eventos.submit({ target: estados, submitter: botonGuardar, preventDefault() { cancelado = true; } });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(cancelado, true);
    assert.equal(atributo['aria-hidden'], 'false');
    assert.equal(p.solicitudes[0][0], 'index.php?action=plataforma_estado_ajax');
    assert.equal(p.solicitudes[0][1].method, 'POST');
    assert.equal(p.solicitudes[0][1].body.form.elements.estado.value, 'suspendida');
    assert.equal(p.solicitudes[0][1].body.form.elements.id_clinica.value, '1');
    assert.equal(p.actualizado(), true);
});

test('E2: la plataforma usa componentes administrativos y su modal no entra en la capa superior nativa', () => {
    const vista = fs.readFileSync(path.join(__dirname, '../../views/plataforma/inicio.php'), 'utf8');
    for (const elemento of ['css/styles.css', 'css/dashboard.css', 'css/usuarios.css', 'person-card', 'personal-grid', 'users-modal', 'users-table', 'view-toggle']) assert.ok(vista.includes(elemento));
    assert.doesNotMatch(vista, /<dialog|data-validacion-cuenta|\sstyle=|\sonclick=/);
    const css = fs.readFileSync(path.join(__dirname, '../../public/css/plataforma.css'), 'utf8');
    assert.doesNotMatch(css, /(?:^|\n)(?:body|button|input|:root|\.person-card|\.users-modal)\s*\{/);
});
