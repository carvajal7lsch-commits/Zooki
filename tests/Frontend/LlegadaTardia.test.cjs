const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { buscarChrome, abrirChrome } = require('./apoyo/chrome-cdp.cjs');
const { servidorAgendaVet } = require('./apoyo/agenda-vet-servidor.cjs');
const fuente = fs.readFileSync('public/js/calendario.js', 'utf8');
const reglas = fuente.slice(fuente.indexOf('function llegadaCitaTardia('), fuente.indexOf('function mostrarDetalleCita('));
const detalle = fuente.slice(fuente.indexOf('function mostrarDetalleCita('), fuente.indexOf('function manejarClicPanel('));
const inicio = new Date('2030-01-07T09:00:00');

test('Calendario usa la tolerancia de clínica y la llegada real, con límite inclusivo', () => {
    const contexto = vm.createContext({ Date });
    vm.runInContext(reglas, contexto);
    const ev = { start: inicio, extendedProps: { tolerancia_llegada_min: 10 } };
    assert.equal(contexto.llegadaCitaTardia(ev, new Date('2030-01-07T09:10:00')), false);
    assert.equal(contexto.llegadaCitaTardia(ev, new Date('2030-01-07T09:10:01')), true);
    ev.extendedProps.tolerancia_llegada_min = 20;
    assert.equal(contexto.llegadaCitaTardia(ev, new Date('2030-01-07T09:15:00')), false);
    ev.extendedProps.hora_llegada = '2030-01-07 09:08:00';
    assert.equal(contexto.llegadaCitaTardia(ev, new Date('2030-01-07T19:00:00')), false);
});

function presentar(estado, llegada = '', hoy = true) {
    const elementos = { agendaPanelHead: {}, agendaPanelBody: {} };
    const ev = { id: '31', start: inicio, extendedProps: { tipo: 'cita', estado, id_veterinario: '2', tolerancia_llegada_min: 10, hora_llegada: llegada } };
    class Reloj extends Date {
        constructor(...args) {
            super(...(args.length ? args : ['2030-01-07T19:00:00']));
        }
    }
    const contexto = vm.createContext({
        Date: Reloj,
        document: { getElementById: id => elementos[id] },
        calendarInstance: { getEventById: () => ev },
        MINUTOS_ANTES_DE_INICIAR: 15,
        ESTADOS_CITA: {},
        esDiaPasado: () => !hoy,
        esHoy: () => hoy,
        isUsuarioVeterinario: () => true,
        getUsuarioId: () => '2',
        cambioPermitidoCalendario: () => false,
        marcarCitaCalendario: () => {},
        esc: valor => String(valor),
        capitalizar: valor => valor,
        formatFecha: () => '7 de enero',
        formatHora: () => '09:00',
    });
    vm.runInContext(reglas + detalle, contexto);
    contexto.mostrarDetalleCita('31');
    return elementos.agendaPanelBody.innerHTML;
}

test('la reserva tardía ofrece Sala y una atención antigua conserva Registrar consulta', () => {
    assert.match(presentar('confirmada'), /data-accion="sala"/);
    assert.doesNotMatch(presentar('confirmada'), /data-accion="iniciar"/);
    assert.match(presentar('confirmada', '2030-01-07 09:08:00'), /data-accion="iniciar"/);
    assert.match(presentar('sin_cerrar', '', false), /Registrar consulta/);
    assert.doesNotMatch(presentar('sin_cerrar', '', false), /data-accion="sala"|data-accion="iniciar"/);
});

test('Ir a Sala cierra la hoja móvil y enfoca el caso sin registrar presencia automáticamente', () => {
    const pasos = [];
    const contexto = vm.createContext({
        document: {
            getElementById: () => ({ open: true, close: () => pasos.push('cerrar detalle') }),
            querySelector: selector => ({ click: () => pasos.push(selector), focus: () => pasos.push('enfocar pestaña') }),
            dispatchEvent: evento => pasos.push(evento.detail.idCita),
        },
        CustomEvent: class {
            constructor(_, datos) {
                this.detail = datos.detail;
            }
        },
    });
    vm.runInContext(reglas, contexto);
    contexto.irASalaCita('31');
    assert.deepEqual(pasos, ['cerrar detalle', '[data-agenda-vista="pendientes"]', '[data-agenda-tab="llegadas"]', 'enfocar pestaña', '31']);
});

test('el enlace directo conserva Sala aunque haya pendientes en otras bandejas', { skip: !buscarChrome() }, async () => {
    const servidor = await servidorAgendaVet();
    const nav = await abrirChrome(buscarChrome());
    try {
        for (const [width, height] of [[1366, 768], [390, 844]]) {
            await nav.enviar('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 768 });
            await nav.enviar('Page.navigate', { url: servidor.url + '?resolver_llegada=3#llegadas' });
            await nav.esperar("document.querySelector('[data-agenda-tab=llegadas]')?.getAttribute('aria-selected')==='true' && document.querySelector('[data-llegadas-aviso]')?.textContent.includes('Resuelve esta cita')");
            assert.equal(await nav.evaluar("Boolean(document.querySelector('[data-llegada-cita=\"3\"]').getClientRects().length)"), true);
            assert.deepEqual(nav.errores, []);
        }
    } finally {
        await nav.cerrar();
        await servidor.cerrar();
    }
});
