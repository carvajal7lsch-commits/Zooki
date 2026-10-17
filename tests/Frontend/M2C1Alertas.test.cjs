/**
 * M2 C1 — HU-2.1, RE-2.1.5 y RE-2.10.1: sección «Alergias y alertas» (alertas-medicas.js).
 * Funciones puras y la sección con un DOM mínimo: pintado, validación en tiempo real,
 * buscador de nodos, registro y desactivación que actualizan la lista sin recargar.
 */
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const { crearDocumento, colgar } = require('./apoyo/dom-datos.cjs');

const codigo = fs.readFileSync(path.join(__dirname, '../../public/js/alertas-medicas.js'), 'utf8');
const NODOS_POR_TIPO = { alergia: ['farmaco', 'sustancia'], medicacion_continua: ['farmaco'], condicion_cronica: ['condicion'], otra: [] };

function alerta(extra = {}) {
    return {
        id_alerta: 7, id_clinica: 1, tipo: 'alergia', tipo_nombre: 'Alergia', descripcion: 'Reacción a la amoxicilina', activa: 1,
        fecha: '2026-10-14 09:30:00', clinica_nombre: 'Clínica Norte', veterinario: 'Beto Norte', nodo_codigo: 'farmaco.paracetamol',
        nodo_etiqueta: 'Paracetamol (acetaminofén)', es_propia: true, puede_desactivar: true, ...extra,
    };
}

function cargarApi() {
    const contexto = { window: {} };
    vm.runInNewContext(codigo, contexto);
    return contexto.window.ZookiAlertas;
}

class FormDataFalso {
    constructor(form) {
        this.datos = new Map();
        for (const n of form ? form.todos() : []) {
            if (n.name && (n.type !== 'radio' || n.checked)) this.datos.set(n.name, n.value);
        }
    }
    append(clave, valor) { this.datos.set(clave, valor); }
    set(clave, valor) { this.datos.set(clave, valor); }
    get(clave) { return this.datos.get(clave); }
}

function pagina(respuestas = {}) {
    const documento = crearDocumento();
    const raiz = documento.raiz;
    const ficha = colgar(raiz, 'section', { alertasMedicas: '', alertasFicha: '' }, { hidden: true });
    const modal = colgar(raiz, 'div', { alertaModal: '' });
    colgar(modal, 'p', { alertaMascota: '' });
    colgar(modal, 'button', { alertaCerrar: '' });
    const form = colgar(modal, 'form', { alertaForm: '', nodosPorTipo: JSON.stringify(NODOS_POR_TIPO) });
    form.reset = () => form.todos().forEach(n => { if (n.tagName === 'INPUT' || n.tagName === 'TEXTAREA') { n.value = n.type === 'radio' ? n.value : ''; n.checked = false; } });
    colgar(form, 'input', { alertaCampo: 'id_mascota' }, { name: 'id_mascota', type: 'hidden' });
    colgar(form, 'input', { alertaCampo: 'nodo' }, { name: 'nodo', type: 'hidden' });
    const radios = {};
    for (const tipo of Object.keys(NODOS_POR_TIPO)) {
        radios[tipo] = colgar(form, 'input', { alertaTipo: '' }, { name: 'tipo', type: 'radio', value: tipo });
    }
    colgar(form, 'small', { alertaAviso: 'tipo' });
    const area = colgar(form, 'textarea', { alertaCampo: 'descripcion' }, { name: 'descripcion' });
    area.setAttribute('maxlength', '255');
    colgar(form, 'small', { alertaAviso: 'descripcion' });
    colgar(form, 'span', { alertaContador: '' });
    const zona = colgar(form, 'div', { alertaZonaNodo: '' }, { hidden: true });
    const envoltura = colgar(zona, 'div', { alertaBuscar: '' });
    colgar(envoltura, 'input', { alertaCampo: 'busqueda' }, { type: 'search' });
    colgar(zona, 'div', { alertaNodoElegido: '' }, { hidden: true });
    colgar(zona, 'div', { alertaSugerencias: '' }, { hidden: true });
    colgar(form, 'p', { alertaResultado: '' });
    colgar(form, 'button', { alertaEnviar: '' }, { type: 'submit' });

    const peticiones = [];
    const avisos = [];
    const confirmaciones = [];
    let confirmar = true;
    const ventana = {
        document: documento,
        FormData: FormDataFalso,
        setTimeout: fn => { fn(); return 1; },
        clearTimeout: () => {},
        zookiToast: (mensaje, tipo) => avisos.push([mensaje, tipo]),
        zookiConfirmarCorto: async (pregunta, boton, filas) => { confirmaciones.push([pregunta, boton, filas]); return confirmar; },
        fetch: async (url, opciones = {}) => {
            peticiones.push([url, opciones]);
            const accion = /action=([a-z_]+)/.exec(url)[1];
            const respuesta = respuestas[accion] || { success: true };
            return { ok: respuesta.success !== false, json: async () => respuesta };
        },
    };
    vm.runInNewContext(codigo, { window: ventana });
    documento.disparar('DOMContentLoaded');
    const q = selector => raiz.querySelector(selector);
    return {
        api: ventana.ZookiAlertas, documento, raiz, ficha, modal, form, radios, area, peticiones, avisos, confirmaciones, q,
        noConfirmar: () => { confirmar = false; },
    };
}

const esperar = () => new Promise(resolver => setImmediate(resolver));

test('C1: validación de la descripción con la regla del servidor', () => {
    const api = cargarApi();
    assert.equal(api.validarDescripcion('   ', 255), 'Describe la alergia o la alerta.');
    assert.equal(api.validarDescripcion('a'.repeat(256), 255), 'La descripción admite hasta 255 caracteres.');
    assert.equal(api.validarDescripcion('ñ'.repeat(255), 255), '', 'Cuenta caracteres, no bytes');
    assert.equal(api.validarDescripcion('Alergia\u0007', 255), 'La descripción tiene caracteres no permitidos.');
    assert.equal(api.validarDescripcion('  Alergia   a la   penicilina ', 255), '');
    assert.equal(api.textoEnlace(alerta()), 'Enlazada al conocimiento: «Paracetamol (acetaminofén)»');
    assert.match(api.textoEnlace(alerta({ nodo_etiqueta: null })), /el motor de prescripción no podrá cruzarla/);
    assert.equal(api.textoEnlace(alerta({ tipo: 'otra', nodo_etiqueta: null })), '');
    assert.equal(api.fechaCorta('2026-10-14 09:30:00'), '14/10/2026');
    assert.ok(api.admiteNodo(NODOS_POR_TIPO, 'alergia'));
    assert.ok(!api.admiteNodo(NODOS_POR_TIPO, 'otra'));
    assert.equal(api.normalizar(' ÁCIDO  acetilsalicílico '), 'acido acetilsalicilico');
});

test('C1: pinta las activas con su clínica, desactivar solo en las propias y el historial', () => {
    const p = pagina();
    p.api.pintar(p.ficha, {
        activas: [alerta(), alerta({ id_alerta: 8, tipo: 'medicacion_continua', tipo_nombre: 'Medicación continua', descripcion: 'Fenobarbital', clinica_nombre: 'Clínica Sur', es_propia: false, puede_desactivar: false, nodo_etiqueta: null })],
        historial: [alerta({ id_alerta: 3, activa: 0, descripcion: 'Antigua', desactivada_por: 'Beto Norte', desactivada_en: '2026-10-15 10:00:00', puede_desactivar: false })],
        solo_lectura: false,
    }, { idMascota: 1, nombreMascota: 'Luna' });
    const texto = p.ficha.textContent;
    assert.equal(p.ficha.hidden, false);
    assert.equal(p.ficha.dataset.idMascota, '1');
    assert.ok(p.ficha.classList.contains('alertas-medicas--con-alertas'));
    assert.match(texto, /Alergias y alertas2/);
    assert.match(texto, /Clínica Norte · Beto Norte · 14\/10\/2026Tu clínica/);
    assert.match(texto, /Clínica Sur/);
    assert.match(texto, /Sin enlace al conocimiento/);
    assert.equal(p.ficha.querySelectorAll('[data-desactivar-alerta]').length, 1, 'Solo la propia');
    assert.equal(p.ficha.querySelectorAll('[data-registrar-alerta]').length, 1);
    assert.match(texto, /Historial \(1 desactivada\)/);
    assert.match(texto, /desactivada por Beto Norte el 15\/10\/2026/);
});

test('C1: sin alertas lo dice sin afirmar que no tenga; solo lectura sin botones; portal sin historial', () => {
    const p = pagina();
    p.api.pintar(p.ficha, { activas: [], historial: [], solo_lectura: true }, { idMascota: 1 });
    assert.match(p.ficha.textContent, /Que no haya registro no significa que no tenga/);
    assert.equal(p.ficha.querySelectorAll('[data-registrar-alerta]').length, 0, 'Mascota desvinculada: solo lectura');
    p.api.pintar(p.ficha, { activas: [alerta({ es_propia: undefined, puede_desactivar: undefined, veterinario: undefined })], historial: [alerta({ activa: 0 })] }, { idMascota: 1, lectura: true, iconos: 'ri' });
    assert.equal(p.ficha.querySelectorAll('[data-registrar-alerta]').length, 0);
    assert.equal(p.ficha.querySelectorAll('[data-desactivar-alerta]').length, 0);
    assert.doesNotMatch(p.ficha.textContent, /Historial|Enlazada|Tu clínica/);
    assert.match(p.ficha.textContent, /Clínica Norte/);
    assert.ok(p.ficha.todos().some(n => n.className === 'ri-alert-line'));
});

test('C1: registrar valida en tiempo real, busca el nodo y actualiza la lista sin recargar', async () => {
    const nueva = alerta({ id_alerta: 9, descripcion: 'Penicilina' });
    const p = pagina({
        buscar_nodo_alerta_ajax: { success: true, nodos: [{ codigo: 'farmaco.paracetamol', etiqueta: 'Paracetamol (acetaminofén)', tipo: 'farmaco', coincidencia: 'acetaminofén' }] },
        registrar_alerta_medica_ajax: { success: true, message: 'Alerta registrada. Ya la ven las clínicas vinculadas.', alertas: { activas: [nueva], historial: [], solo_lectura: false } },
    });
    p.api.pintar(p.ficha, { activas: [], historial: [] }, { idMascota: 1, nombreMascota: 'Luna' });
    p.ficha.querySelector('[data-registrar-alerta]').click();
    assert.ok(p.modal.classList.contains('is-open'));
    assert.equal(p.q('[data-alerta-campo="id_mascota"]').value, '1');
    assert.equal(p.q('[data-alerta-mascota]').textContent, 'Mascota: Luna');

    p.area.value = '  ';
    p.area.disparar('input');
    assert.equal(p.q('[data-alerta-aviso="descripcion"]').textContent, 'Describe la alergia o la alerta.');
    p.area.value = 'a'.repeat(256);
    p.area.disparar('input');
    assert.match(p.q('[data-alerta-aviso="descripcion"]').textContent, /hasta 255/);
    assert.equal(p.q('[data-alerta-contador]').textContent, '256/255');
    p.area.value = 'Penicilina';
    p.area.disparar('input');
    assert.equal(p.q('[data-alerta-aviso="descripcion"]').textContent, '');

    // Sin tipo no se envía.
    p.form.disparar('submit');
    await esperar();
    assert.equal(p.q('[data-alerta-aviso="tipo"]').textContent, 'Elige el tipo de alerta.');
    assert.equal(p.peticiones.length, 0);

    p.radios.otra.checked = true;
    p.radios.otra.disparar('change');
    assert.equal(p.q('[data-alerta-zona-nodo]').hidden, true, '«Otra» no se enlaza');
    p.radios.otra.checked = false;
    p.radios.alergia.checked = true;
    p.radios.alergia.disparar('change');
    assert.equal(p.q('[data-alerta-zona-nodo]').hidden, false);

    const buscador = p.q('[data-alerta-campo="busqueda"]');
    buscador.value = 'acetaminofen';
    buscador.disparar('input');
    await esperar();
    assert.match(p.peticiones[0][0], /action=buscar_nodo_alerta_ajax&tipo=alergia&q=acetaminofen/);
    const sugerencia = p.q('[data-nodo-sugerido="farmaco.paracetamol"]');
    assert.match(sugerencia.textContent, /por «acetaminofén»/);
    sugerencia.click();
    assert.equal(p.q('[data-alerta-campo="nodo"]').value, 'farmaco.paracetamol');
    assert.equal(p.q('[data-alerta-nodo-elegido]').hidden, false);

    p.form.disparar('submit');
    await esperar();
    const [url, opciones] = p.peticiones[1];
    assert.match(url, /action=registrar_alerta_medica_ajax/);
    assert.equal(opciones.method, 'POST');
    assert.equal(opciones.body.get('tipo'), 'alergia');
    assert.equal(opciones.body.get('nodo'), 'farmaco.paracetamol');
    assert.equal(opciones.body.get('descripcion'), 'Penicilina');
    assert.ok(!p.modal.classList.contains('is-open'), 'Se cierra al guardar');
    assert.match(p.ficha.textContent, /Penicilina/, 'La lista se actualizó con la respuesta');
    assert.deepEqual(p.avisos[0], ['Alerta registrada. Ya la ven las clínicas vinculadas.', 'success']);
});

test('C1: un error del servidor queda junto al campo y no cierra el formulario', async () => {
    const p = pagina({ registrar_alerta_medica_ajax: { success: false, message: 'La descripción admite hasta 255 caracteres.' } });
    p.api.pintar(p.ficha, { activas: [], historial: [] }, { idMascota: 1 });
    p.api.abrirFormulario(1, 'Luna');
    p.radios.alergia.checked = true;
    p.area.value = 'Válida en el navegador';
    p.form.disparar('submit');
    await esperar();
    assert.equal(p.q('[data-alerta-aviso="descripcion"]').textContent, 'La descripción admite hasta 255 caracteres.');
    assert.ok(p.modal.classList.contains('is-open'));
    assert.equal(p.avisos.length, 0);
});

test('C1: desactivar pide confirmación y actualiza la lista; cancelar no envía nada', async () => {
    const p = pagina({
        desactivar_alerta_medica_ajax: { success: true, message: 'Alerta desactivada. Queda en el historial.', alertas: { activas: [], historial: [alerta({ activa: 0 })], solo_lectura: false } },
    });
    p.api.pintar(p.ficha, { activas: [alerta()], historial: [] }, { idMascota: 1 });
    p.noConfirmar();
    p.ficha.querySelector('[data-desactivar-alerta="7"]').click();
    await esperar();
    assert.equal(p.confirmaciones[0][0], '¿Desactivar esta alergia?');
    assert.equal(p.peticiones.length, 0);

    const otra = pagina({
        desactivar_alerta_medica_ajax: { success: true, message: 'Alerta desactivada. Queda en el historial.', alertas: { activas: [], historial: [alerta({ activa: 0 })], solo_lectura: false } },
    });
    otra.api.pintar(otra.ficha, { activas: [alerta()], historial: [] }, { idMascota: 1 });
    otra.ficha.querySelector('[data-desactivar-alerta="7"]').click();
    await esperar();
    const [url, opciones] = otra.peticiones[0];
    assert.match(url, /action=desactivar_alerta_medica_ajax/);
    assert.equal(opciones.body.get('id_alerta'), '7');
    assert.match(otra.ficha.textContent, /No hay alergias ni alertas registradas/);
    assert.match(otra.ficha.textContent, /Historial \(1 desactivada\)/);
    assert.equal(otra.avisos[0][0], 'Alerta desactivada. Queda en el historial.');
});

test('C1: en Atención la sección se carga sola por su mascota', async () => {
    const documento = crearDocumento();
    const seccion = colgar(documento.raiz, 'section', { alertasMedicas: '', cargar: '', idMascota: '5', nombreMascota: 'Kira' });
    const peticiones = [];
    const ventana = {
        document: documento,
        fetch: async url => {
            peticiones.push(url);
            return { ok: true, json: async () => ({ success: true, alertas: { activas: [alerta({ descripcion: 'Epilepsia', tipo: 'condicion_cronica', tipo_nombre: 'Condición crónica' })], historial: [] } }) };
        },
    };
    vm.runInNewContext(codigo, { window: ventana });
    documento.disparar('DOMContentLoaded');
    await esperar();
    assert.match(peticiones[0], /action=listar_alertas_medicas_ajax&id_mascota=5/);
    assert.match(seccion.textContent, /Condición crónicaEpilepsia/);
});
