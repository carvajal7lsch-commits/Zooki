const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { errorCampo } = require('../../public/js/llegadas-agenda.js');
const { activarPanelAgenda, iniciarHerramientasAgenda } = require('../../public/js/agenda-herramientas.js');

// D: validación en tiempo real de los formularios de llegada (RE-4.19, RE-4.18).
test('llegadas valida motivo, segundos, hora y fecha como el servidor', () => {
    assert.equal(errorCampo('motivo', '', true), 'Este campo es obligatorio.');
    assert.equal(errorCampo('motivo', 'a'.repeat(256), true), 'Motivo de 1 a 255 caracteres.');
    assert.equal(errorCampo('motivo', 'Dolor\u0007', true), 'Motivo de 1 a 255 caracteres.');
    assert.equal(errorCampo('motivo', 'Dificultad respiratoria', true), '');
    assert.equal(errorCampo('segundos', '0', true), 'Indica un entero positivo.');
    assert.equal(errorCampo('segundos', '12a', true), 'Indica un entero positivo.');
    assert.equal(errorCampo('segundos', '2147483648', true), 'Indica un entero positivo.');
    assert.equal(errorCampo('segundos', '900', true), '');
    assert.equal(errorCampo('hora', '24:00', true), 'Selecciona una hora válida.');
    assert.equal(errorCampo('hora', '09:10:30', true), '');
    assert.equal(errorCampo('fecha', '2030-13-40', true), 'Selecciona una fecha válida.');
    assert.equal(errorCampo('fecha', '2030-01-07', true), '');
    assert.equal(errorCampo('hora', '', false), '');
});

function dom(claves) {
    const eventos = [];
    const paneles = {};
    const tabs = claves.map((clave, i) => {
        paneles['#agenda-panel-' + clave] = { hidden: i !== 0, dispatchEvent: (e) => eventos.push([clave, e.type]) };
        const atributos = { 'aria-controls': 'agenda-panel-' + clave, 'aria-selected': i === 0 ? 'true' : 'false' };
        return {
            dataset: { agendaTab: clave }, tabIndex: i === 0 ? 0 : -1, oyentes: {}, enfocada: false,
            getAttribute: (n) => atributos[n], setAttribute: (n, v) => { atributos[n] = v; },
            addEventListener(nombre, fn) { this.oyentes[nombre] = fn; }, focus() { this.enfocada = true; },
        };
    });
    const raiz = { querySelectorAll: () => tabs, querySelector: (s) => paneles[s] };
    return { raiz, tabs, paneles, eventos };
}

test('pestañas muestran un solo panel, avisan al mostrarse y navegan con flechas', () => {
    global.CustomEvent = class { constructor(type) { this.type = type; } };
    const d = dom(['triage', 'llegadas', 'urgencias']);
    iniciarHerramientasAgenda({ querySelectorAll: () => [d.raiz] });
    assert.deepEqual(d.eventos, [['triage', 'agenda:mostrar']]);
    d.tabs[2].oyentes.click();
    assert.equal(d.paneles['#agenda-panel-urgencias'].hidden, false);
    assert.equal(d.paneles['#agenda-panel-triage'].hidden, true);
    assert.equal(d.tabs[2].getAttribute('aria-selected'), 'true');
    assert.equal(d.tabs[0].tabIndex, -1);
    let prevenido = false;
    d.tabs[2].oyentes.keydown({ key: 'ArrowRight', preventDefault: () => { prevenido = true; } });
    assert.ok(prevenido);
    assert.equal(d.paneles['#agenda-panel-triage'].hidden, false);
    assert.ok(d.tabs[0].enfocada);
    activarPanelAgenda(d.raiz, 'llegadas', false);
    assert.deepEqual(d.eventos.at(-1), ['llegadas', 'agenda:mostrar']);
});

test('las vistas de herramientas no llevan estilos ni eventos en línea', () => {
    for (const archivo of ['views/partials/agenda_herramientas.php', 'views/partials/solicitudes_agenda.php', 'views/partials/llegadas_agenda.php', 'views/partials/reasignaciones_agenda.php', 'views/partials/urgencias_agenda.php']) {
        assert.doesNotMatch(fs.readFileSync(archivo, 'utf8'), /\sstyle="|\son[a-z]+="|<script>\s*\S/, archivo);
    }
});
