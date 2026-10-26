const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const { crearDocumento } = require('./apoyo/dom-minimo.cjs');
const leer = nombre => fs.readFileSync(path.join(__dirname, '../..', nombre), 'utf8');

test('P1: incluso la confirmación heredada abre el modal de Zooki y conserva cancelar/aceptar', async () => {
    const document = crearDocumento();
    const c = { document, window: {}, console };
    vm.createContext(c);
    vm.runInContext(leer('public/js/avisos.js'), c);
    const pendiente = c.zookiConfirmar('Reemplazar configuración', 'Confirmación', 'Restaurar');
    const fondo = document.body.hijos[0];
    assert.equal(fondo.buscar('zooki-confirmacion__titulo').textContent, 'Reemplazar configuración');
    fondo.buscar('btn-modal-secondary').click();
    assert.equal(await pendiente, false);
});

test('P1: las acciones graves de agenda usan el modal; cancelar no envía ninguna petición', async () => {
    const fuente = leer('public/js/calendario.js');
    const tramo = fuente.slice(fuente.indexOf('async function marcarNoAsistio'), fuente.indexOf('function puedeArrastrar('));
    const solicitudes = [];
    const c = { console, zookiConfirmarCorto: async () => false, postCita: async accion => { solicitudes.push(accion); return { success: true }; }, mostrarToast() {}, enviarCorreoCita() {}, calendarInstance: { refetchEvents() {} } };
    vm.createContext(c);
    vm.runInContext(tramo, c);
    await c.cancelarCita(1);
    await c.marcarNoAsistio(1);
    assert.deepEqual(solicitudes, []);
    c.zookiConfirmarCorto = async () => true;
    await c.cancelarCita(1);
    await c.marcarNoAsistio(1);
    assert.deepEqual(solicitudes, ['cancelar_cita_ajax', 'marcar_no_asistio_ajax']);
});

test('P1: reprogramar usa su indicador local y la reserva conserva el indicador habitual', async () => {
    const fuente = leer('public/js/calendario.js');
    const codigo = fuente.slice(fuente.indexOf('async function postCita('), fuente.indexOf('async function iniciarAtencionCita'));
    const peticiones = [];
    const c = { URLSearchParams, comandosC3: {}, fetch: async (url, opciones) => { peticiones.push(opciones); return { json: async () => ({ success: true }) }; } };
    vm.createContext(c);
    vm.runInContext(codigo, c);
    await c.postCita('reprogramar_cita_ajax', { id_cita: 1 });
    await c.postCita('registrar_cita_ajax', { id_mascota: 1 });
    assert.equal(peticiones[0].sinLoader, true);
    assert.equal(peticiones[1].sinLoader, false);
});
