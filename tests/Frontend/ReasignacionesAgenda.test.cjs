const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { crearComando } = require('../../public/js/comando-agenda.js');

class Elemento {
    constructor() {
        this.hijos = [];
        this.eventos = {};
        this.textContent = '';
    }
    get textContent() { return this.texto + this.hijos.map(h => h.textContent).join(''); }
    set textContent(valor) { this.texto = valor; this.hijos = []; }
    append(...elementos) { this.hijos.push(...elementos); }
    replaceChildren() { this.hijos = []; }
    addEventListener(nombre, accion) { this.eventos[nombre] = accion; }
    querySelectorAll() { return this.hijos.filter(h => h.eventos.click); }
}

function preparar() {
    const lista = new Elemento();
    const mensaje = new Elemento();
    const actualizar = new Elemento();
    const pendientes = [];
    const llamadas = [];
    const bloque = { querySelector: selector => ({ '[data-reasignaciones-lista]': lista, '[data-reasignaciones-mensaje]': mensaje, '[data-reasignaciones-actualizar]': actualizar })[selector] };
    let iniciar;
    let secuencia = 0;
    const contexto = {
        document: { querySelector: () => bloque, createElement: () => new Elemento(), addEventListener: (_, fn) => { iniciar = fn; } },
        FormData,
        zookiCrearComandoAgenda: () => crearComando(() => new Uint8Array(32).fill(++secuencia)),
        fetch: (url, opciones) => {
            llamadas.push({ url, opciones });
            return new Promise((resolver, rechazar) => pendientes.push({ resolver: objeto => resolver({ json: async () => objeto }), rechazar }));
        }
    };
    vm.runInNewContext(fs.readFileSync('public/js/reasignaciones-agenda.js', 'utf8'), contexto);
    iniciar();
    return { lista, mensaje, actualizar, pendientes, llamadas };
}

const vaciar = () => new Promise(resolver => setImmediate(resolver));
const propuesta = { id_reasignacion: 31, mascota: '<img src=x onerror=1>', inicio_propuesto: '2030-01-07 14:00:30', fecha_limite: '2030-01-07 11:30:00' };

test('C5 ejecuta la bandeja real, conserva segundos y presenta datos como texto', async () => {
    const p = preparar();
    p.pendientes.shift().resolver({ success: true, propuestas: [propuesta] });
    await vaciar();
    assert.equal(p.lista.hijos.length, 1);
    assert.match(p.lista.hijos[0].hijos[0].textContent, /14:00:30/);
    assert.match(p.lista.hijos[0].hijos[0].textContent, /<img/);
    assert.equal(p.lista.hijos[0].hijos[0].innerHTML, undefined);
});

test('C5 bloquea doble envío y conserva clave al perder la respuesta', async () => {
    const p = preparar();
    p.pendientes.shift().resolver({ success: true, propuestas: [propuesta] });
    await vaciar();
    const botones = p.lista.hijos[0].querySelectorAll();
    const primero = botones[0].eventos.click();
    botones[1].eventos.click();
    assert.equal(p.llamadas.length, 2);
    assert.equal(botones[0].disabled, true);
    const clave = p.llamadas[1].opciones.body.get('clave_operacion');
    assert.equal(p.llamadas[1].opciones.body.get('respuesta'), 'aceptar');
    p.pendientes.shift().rechazar(new Error('Respuesta perdida'));
    await primero;
    const segundo = botones[0].eventos.click();
    assert.equal(p.llamadas[2].opciones.body.get('clave_operacion'), clave);
    p.pendientes.shift().resolver({ success: true, message: 'Reasignación aceptada.' });
    await vaciar();
    p.pendientes.shift().resolver({ success: true, propuestas: [] });
    await segundo;
    assert.equal(p.lista.hijos.length, 0);
    assert.equal(p.mensaje.textContent, 'Reasignación aceptada.');
});

test('C5 descarta respuestas y errores de una lectura anterior', async () => {
    const p = preparar();
    const antigua = p.pendientes.shift();
    const nueva = p.actualizar.eventos.click();
    p.pendientes.shift().resolver({ success: true, propuestas: [propuesta] });
    await nueva;
    antigua.rechazar(new Error('Error obsoleto'));
    await vaciar();
    assert.equal(p.mensaje.textContent, '');
    assert.equal(p.lista.hijos.length, 1);
});

test('C5 conserva hora pactada y distingue estimación/reprogramación en los clientes', () => {
    const calendario = fs.readFileSync('public/js/calendario.js', 'utf8');
    assert.match(calendario, /inicio_estimado: e\.inicio_estimado/);
    assert.match(calendario, /\['Inicio estimado', p\.inicio_estimado\]/);
    assert.match(calendario, /start: `\$\{e\.fecha\}T\$\{e\.hora/);
    assert.match(fs.readFileSync('public/js/portal.js', 'utf8'), /escapeHtml\(c\.inicio_estimado\)/);
    const vista = fs.readFileSync('views/partials/reasignaciones_agenda.php', 'utf8');
    assert.doesNotMatch(vista, /\s(?:style|onclick|onsubmit)=/i);
    assert.match(vista, /Roles::VETERINARIO/);
});
