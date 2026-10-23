const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { crearComando, formatearHora } = require('../../public/js/comando-agenda.js');

test('C3 conecta los formularios desde el archivo JS y conserva las vistas sin eventos/CSS en línea', () => {
    const vista = fs.readFileSync('views/vet/calendario.php', 'utf8');
    assert.doesNotMatch(vista, /\son(?:click|submit|input|change)\s*=/i);
    assert.doesNotMatch(vista, /\sstyle\s*=/i);
    const fuente = fs.readFileSync('public/js/calendario.js', 'utf8');
    const inicio = fuente.indexOf('function conectarFormulariosC3()');
    const fin = fuente.indexOf("document.addEventListener('DOMContentLoaded'", inicio);
    const conectados = [];
    const contexto = { crearCitaModal() {}, filtrarMascotas() {}, onModalVeterinarioChange() {}, onModalTipoCitaChange() {}, cargarSlotsReprogramar() {},
        document: { querySelectorAll: () => [], getElementById: id => ({ addEventListener: evento => conectados.push([id, evento]) }) } };
    prepararContextoC4(contexto);
    vm.runInContext(fuente.slice(inicio, fin), contexto);
    contexto.conectarFormulariosC3();
    assert.equal(conectados.length, 11);
    assert.deepEqual(conectados[0], ['formCitaModal', 'submit']);
    assert.deepEqual(conectados[1], ['citaModalOverlay', 'keydown']);
    assert.deepEqual(conectados[2], ['cm_urgencias', 'click']);
    assert.deepEqual(conectados[3], ['cm_siguiente', 'click']);
    assert.deepEqual(conectados[4], ['cm_anterior', 'click']);
    assert.deepEqual(conectados.at(-1), ['reprogramar_tipo_cita', 'change']);
});

test('C3 conserva intención al reintentar; cambios y nueva apertura reciben otra clave', () => {
    let numero = 0;
    const comando = crearComando(() => new Uint8Array(32).fill(++numero));
    const datos = new URLSearchParams({ mascota: '1', hora: '09:00' });
    const inicial = comando.clave(datos);
    assert.match(inicial, /^[a-f0-9]{64}$/);
    assert.equal(comando.clave(new URLSearchParams({ hora: '09:00', mascota: '1', csrf_token: 'nuevo', ignore_warning: '1' })), inicial);
    datos.set('hora', '10:00');
    const otra = comando.clave(datos);
    assert.notEqual(otra, inicial);
    comando.reiniciar();
    assert.notEqual(comando.clave(datos), otra);
});

test('C3 arrastrar conserva los segundos y revierte visualmente un rechazo del servidor', async () => {
    const fuente = fs.readFileSync('public/js/calendario.js', 'utf8');
    const inicio = fuente.indexOf('function reprogramarPorArrastre(');
    const fin = fuente.indexOf('// Correo al propietario', inicio);
    let enviada;
    let revertida = false;
    const contexto = { String, toLocalDateStr: () => '2030-01-07', mostrarToast() {},
        postCita: async (accion, datos) => { enviada = datos; return { success: false }; } };
    prepararContextoC4(contexto);
    vm.runInContext(fuente.slice(inicio, fin), contexto);
    contexto.reprogramarPorArrastre({ event: { id: '8', start: new Date(2030, 0, 7, 9, 20, 7) }, revert: () => { revertida = true; } });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(enviada.hora, '09:20:07');
    assert.equal(revertida, true);
});

function funcion(archivo, nombre, final) {
    const fuente = fs.readFileSync(archivo, 'utf8');
    const inicio = fuente.indexOf(`async function ${nombre}(`);
    const fin = fuente.indexOf(final, inicio);
    assert.ok(inicio >= 0 && fin > inicio);
    return fuente.slice(inicio, fin).trim();
}

function contextoSlots(fetch) {
    const valores = { modal_mascota: '1', modal_tipo_cita: '7', modal_fecha: '2030-01-07' };
    const estados = [];
    const dibujadas = [];
    const boton = { disabled: false };
    const contexto = { URLSearchParams, fetch, console, _turnoSlotsCita: 0, document: { getElementById: () => null },
        getVetModal: () => '2', val: (id, valor) => valor === undefined ? valores[id] : valores[id] = valor,
        ocultarErrorModal() {}, actualizarResumenModal: () => { boton.disabled = !valores.modal_hora; }, textoGuiaSlots: () => 'Guía',
        setSlotsEstado: (...args) => estados.push(args),
        renderSlots: (...args) => dibujadas.push(args), actualizarBotonModal() {}, marcarHoraPreferida() {} };
    prepararContextoC4(contexto);
    const codigo = funcion('public/js/calendario.js', 'cargarSlotsModal', '\nfunction actualizarResumenModal');
    instalarFalloSlots(contexto);
    vm.runInContext(codigo, contexto);
    return { contexto, valores, estados, dibujadas, boton };
}

test('C3 consulta una sola capacidad por mascota/tipo, sin tiempos ni prioridades del cliente', async () => {
    const urls = [];
    const { contexto } = contextoSlots(async url => {
        urls.push(new URL(url, 'https://zooki.test'));
        return { json: async () => ({ success: true, sugerencias: [] }) };
    });
    await contexto.cargarSlotsModal();
    assert.equal(urls.length, 1);
    assert.equal(urls[0].searchParams.get('id_mascota'), '1');
    assert.equal(urls[0].searchParams.get('id_tipo_cita'), '7');
    for (const campo of ['duracion_minutos', 'margen_minutos', 'prioridad', 'intervalo']) {
        assert.equal(urls[0].searchParams.has(campo), false);
    }
});

function instalarFalloSlots(contexto) {
    const fuente = fs.readFileSync('public/js/calendario.js', 'utf8');
    vm.runInContext(fuente.slice(fuente.indexOf('function mostrarFalloSlots('), fuente.indexOf('/** Si la fecha es hoy')), contexto);
}

function contextoReprogramar(fetch) {
    const valores = { reprogramar_veterinario: '2', reprogramar_fecha: '2030-01-07', reprogramar_hora: '09:00' };
    const estados = [];
    const dibujadas = [];
    const boton = { disabled: false };
    const contexto = { URLSearchParams, fetch, console, _turnoSlotsReprog: 0, _reprogramarCitaId: 3,
        document: { getElementById: () => ({ dataset: { mascota: '1', tipoOriginal: '7' } }) },
        val: (id, valor) => valor === undefined ? valores[id] : valores[id] = valor,
        actualizarBotonReprog: () => { boton.disabled = !valores.reprogramar_hora; },
        ocultarErrorReprog() {}, esDiaPasado: () => false,
        setSlotsEstado: (...args) => estados.push(args), renderSlots: (...args) => dibujadas.push(args) };
    prepararContextoC4(contexto);
    instalarFalloSlots(contexto);
    vm.runInContext(funcion('public/js/calendario.js', 'cargarSlotsReprogramar', '\nasync function confirmarReprogramacion'), contexto);
    return { contexto, valores, estados, dibujadas, boton };
}

for (const reprogramar of [false, true]) {
    const nombre = reprogramar ? 'reprogramar' : 'reservar';
    const preparar = reprogramar ? contextoReprogramar : contextoSlots;
    const cargar = reprogramar ? 'cargarSlotsReprogramar' : 'cargarSlotsModal';
    const hora = reprogramar ? 'reprogramar_hora' : 'modal_hora';
    test(`C3.1 ${nombre}: revisión conserva mensaje y no ofrece una selección`, async () => {
        const mensaje = 'La disponibilidad de esta fecha requiere revisión.';
        const { contexto, valores, estados, dibujadas, boton } = preparar(async () => ({ json: async () => ({ success: false, message: mensaje }) }));
        await contexto[cargar]();
        assert.equal(valores[hora], '');
        assert.equal(estados.at(-1)[1], 'error');
        assert.equal(estados.at(-1)[2], mensaje);
        assert.equal(dibujadas.length, 0);
        if (boton) assert.equal(boton.disabled, true);
    });
    test(`C3.1 ${nombre}: una consulta válida vacía conserva su mensaje de falta de huecos`, async () => {
        const { contexto, estados } = preparar(async () => ({ json: async () => ({ success: true, sugerencias: [] }) }));
        await contexto[cargar]();
        assert.equal(estados.at(-1)[1], 'vacio');
        assert.match(estados.at(-1)[2], /No (?:hay|quedan) horarios/);
    });
    test(`C3.1 ${nombre}: descarta un error obsoleto tras la respuesta vigente`, async () => {
        let liberar;
        let numero = 0;
        const { contexto, estados, dibujadas } = preparar(() => ++numero === 1
            ? new Promise(resolve => { liberar = resolve; })
            : Promise.resolve({ json: async () => ({ success: true, sugerencias: ['08:00:30'] }) }));
        const anterior = contexto[cargar]();
        await contexto[cargar]();
        const cantidad = estados.length;
        liberar({ json: async () => ({ success: false, message: 'Revisión obsoleta' }) });
        await anterior;
        assert.equal(estados.length, cantidad);
        assert.equal(dibujadas.length, 1);
        assert.equal(dibujadas[0][1][0], '08:00:30');
        dibujadas[0][2]('08:00:30');
        assert.equal(contexto.val(hora), '08:00:30');
    });
}

test('C3.1 presenta segundos no nulos y conserva ambos estilos de hora', () => {
    assert.equal(formatearHora('08:00:30'), '8:00:30 AM');
    assert.equal(formatearHora('14:01:07', 'a. m.', 'p. m.'), '2:01:07 p. m.');
    assert.equal(formatearHora('08:00:00'), '8:00 AM');
    assert.equal(formatearHora('08:00'), '8:00 AM');
});

test('C3.1 el mensaje de revisión se escapa en la estructura real del modal', () => {
    const fuente = fs.readFileSync('public/js/calendario.js', 'utf8');
    const contenedor = { innerHTML: '', onclick() {} };
    const contexto = { document: { getElementById: () => contenedor } };
    prepararContextoC4(contexto);
    vm.runInContext(fuente.slice(fuente.indexOf('function esc('), fuente.indexOf('\n}', fuente.indexOf('function esc(')) + 2), contexto);
    vm.runInContext(fuente.slice(fuente.indexOf('function setSlotsEstado('), fuente.indexOf('/** Si la fecha es hoy')), contexto);
    contexto.mostrarFalloSlots('modal_slots_container', { success: false, message: '<img src=x onerror=alert(1)>' });
    assert.doesNotMatch(contenedor.innerHTML, /<img/);
    assert.match(contenedor.innerHTML, /&lt;img/);
    assert.equal(contenedor.onclick, null);
});

test('C3 descarta respuesta anterior si se limpia la mascota durante la consulta', async () => {
    let liberar;
    const { contexto, valores, estados } = contextoSlots(() => new Promise(resolve => { liberar = resolve; }));
    const anterior = contexto.cargarSlotsModal();
    valores.modal_mascota = '';
    await contexto.cargarSlotsModal();
    const cantidad = estados.length;
    liberar({ json: async () => ({ success: true, sugerencias: ['09:00'] }) });
    await anterior;
    assert.equal(estados.length, cantidad);
    assert.equal(estados.at(-1)[1], 'guia');
});

test('C3 protege doble envío de reprogramación y conserva su intención', async () => {
    const codigo = funcion('public/js/calendario.js', 'confirmarReprogramacion', '\n// ═');
    let resolver;
    let envios = 0;
    const boton = { disabled: false, textContent: '' };
    const contexto = { console, enviosC3: { reprogramacion: false }, _reprogramarCitaId: 3, document: { getElementById: () => boton },
        val: id => ({ reprogramar_hora: '10:00', reprogramar_fecha: '2030-01-07', reprogramar_veterinario: '2' })[id],
        postCita: () => { envios++; return new Promise(resolve => { resolver = resolve; }); },
        mostrarErrorReprog() {}, actualizarBotonReprog: () => { boton.disabled = false; } };
    prepararContextoC4(contexto);
    vm.runInContext(codigo, contexto);
    const primero = contexto.confirmarReprogramacion();
    await contexto.confirmarReprogramacion();
    assert.equal(envios, 1);
    resolver({ success: false, message: 'Conflicto' });
    await primero;
    assert.equal(boton.disabled, false);
});

test('C3 reserva no se duplica aunque la validación en vivo intente rehabilitar el botón', async () => {
    const codigo = funcion('public/js/calendario.js', 'crearCitaModal', '\n// ═');
    let resolver;
    let envios = 0;
    const boton = { disabled: false, textContent: '' };
    const contexto = { console, enviosC3: { reserva: false }, document: { getElementById: () => boton },
        val: () => '1', getVetModal: () => '2', actualizarResumenModal: () => { boton.disabled = false; },
        mostrarErrorModal() {}, postCita: () => { envios++; return new Promise(resolve => { resolver = resolve; }); } };
    prepararContextoC4(contexto);
    vm.runInContext(codigo, contexto);
    const evento = { preventDefault() {} };
    const primero = contexto.crearCitaModal(evento);
    contexto.actualizarResumenModal();
    await contexto.crearCitaModal(evento);
    assert.equal(envios, 1);
    resolver({ success: false, message: 'Conflicto' });
    await primero;
    assert.equal(contexto.enviosC3.reserva, false);
});

test('C3 portal recibe la respuesta canónica sin intersectar ni redondear con el reloj del navegador', async () => {
    const fuente = fs.readFileSync('public/js/portal.js', 'utf8');
    const inicio = fuente.indexOf('    async function cargarHoras()');
    const fin = fuente.indexOf('    /** Clínica, mascota, día y hora:', inicio);
    let dibujadas;
    let consulta;
    const contexto = { console, URLSearchParams, turno: 0, horaInput: { value: '' },
        actualizarGuia() {}, estadoSlots() {},
        clinicaSelect: { value: '2' }, tipoCitaSelect: { value: '9' }, vetSelect: { value: '4' },
        mascotaSelect: { value: '1' }, dateInput: { value: '2030-01-07' },
        fetch: async url => {
            consulta = new URL(url, 'https://zooki.test');
            return { json: async () => ({ success: true, sugerencias: ['08:00:30', '11:20:07'] }) };
        },
        renderSlots: horas => { dibujadas = Array.from(horas); } };
    prepararContextoC4(contexto);
    vm.runInContext(fuente.slice(inicio, fin), contexto);
    await contexto.cargarHoras();
    assert.deepEqual(dibujadas, ['08:00:30', '11:20:07']);
    assert.equal(consulta.searchParams.get('id_mascota'), '1');
    assert.equal(consulta.searchParams.get('id_tipo_cita'), '9');
    assert.equal(consulta.searchParams.has('duracion_minutos'), false);
});

test('C3 portal bloquea doble envío y reintenta el mismo comando tras fallo de red', async () => {
    const fuente = fs.readFileSync('public/js/portal.js', 'utf8');
    const inicio = fuente.indexOf('    const enviarFormulario = async');
    const fin = fuente.indexOf("    form.addEventListener('submit'", inicio);
    let resolver;
    const claves = [];
    const horas = [];
    const contexto = { console: { error() {} }, enviandoC3: false, confirmar: { disabled: false, querySelector: () => ({ textContent: '' }) },
        form: {}, comandoC3: crearComando(() => new Uint8Array(32).fill(4)),
        FormData: function () { return new URLSearchParams({ id_mascota: '1', hora: '09:00:30' }); },
        actualizarGuia() {}, zookiToast() {}, cargarHoras() {},
        fetch: async (url, opciones) => {
            claves.push(opciones.body.get('clave_operacion'));
            horas.push(opciones.body.get('hora'));
            return new Promise(resolve => { resolver = resolve; });
        } };
    prepararContextoC4(contexto);
    vm.runInContext(fuente.slice(inicio, fin) + '\nglobalThis.enviarC3 = enviarFormulario;', contexto);
    const primero = contexto.enviarC3();
    await contexto.enviarC3();
    assert.equal(claves.length, 1);
    resolver({ json: async () => { throw new Error('Respuesta perdida'); } });
    await primero;
    const segundo = contexto.enviarC3();
    resolver({ json: async () => ({ success: false, message: 'Conflicto' }) });
    await segundo;
    assert.equal(claves.length, 2);
    assert.equal(claves[0], claves[1]);
    assert.deepEqual(horas, ['09:00:30', '09:00:30']);
});

test('C3.1 los chips reales internos y del portal presentan y seleccionan el instante literal', () => {
    for (const portal of [false, true]) {
        let seleccionada = '';
        let clic;
        const contenedor = { innerHTML: '', querySelectorAll: () => [], addEventListener: (_, accion) => { clic = accion; } };
        const contexto = { document: { getElementById: () => contenedor }, slots: contenedor,
            horaInput: { value: '' }, actualizarGuia() {}, zookiHoraAgenda: formatearHora,
            minutosDe: h => Number(h.slice(0, 2)) * 60 + Number(h.slice(3, 5)) };
        prepararContextoC4(contexto);
        const calendario = fs.readFileSync('public/js/calendario.js', 'utf8');
        vm.runInContext(calendario.slice(calendario.indexOf('function esc('), calendario.indexOf('\n}', calendario.indexOf('function esc(')) + 2), contexto);
        if (portal) {
            const fuente = fs.readFileSync('public/js/portal.js', 'utf8');
            const hora = fuente.indexOf('    const hora12 =');
            vm.runInContext(fuente.slice(hora, fuente.indexOf('\n', hora)), contexto);
            contexto.escapeHtml = contexto.esc;
            const inicio = fuente.indexOf('    function renderSlots(horas)');
            vm.runInContext(fuente.slice(inicio, fuente.indexOf('    async function cargarHoras()', inicio)), contexto);
            contexto.renderSlots(['08:00:30']);
        } else {
            const inicio = calendario.indexOf('function format12h(');
            vm.runInContext(calendario.slice(inicio, calendario.indexOf('\n}', inicio) + 2), contexto);
            const render = calendario.indexOf('function renderSlots(');
            vm.runInContext(calendario.slice(render, calendario.indexOf('// ═', render)), contexto);
            contexto.renderSlots('modal_slots_container', ['08:00:30'], hora => { seleccionada = hora; });
            clic = contenedor.onclick;
        }
        assert.match(contenedor.innerHTML, /data-hora="08:00:30"/);
        assert.match(contenedor.innerHTML, /8:00:30 (?:AM|a\. m\.)/);
        const chip = { dataset: { hora: '08:00:30' } };
        clic({ target: { closest: selector => selector === '.slot-chip' ? chip : null } });
        assert.equal(portal ? contexto.horaInput.value : seleccionada, '08:00:30');
    }
});

for (const reprogramar of [false, true]) {
    test(`C3.1 envío interno ${reprogramar ? 'reprograma' : 'reserva'} con segundos intactos`, async () => {
        let enviada;
        const nombre = reprogramar ? 'confirmarReprogramacion' : 'crearCitaModal';
        const boton = { disabled: false, textContent: '' };
        const contexto = { console, enviosC3: { reserva: false, reprogramacion: false }, _reprogramarCitaId: 3,
            document: { getElementById: () => boton },
            val: id => id.endsWith('_hora') ? '08:00:30' : '1', getVetModal: () => '2',
            actualizarResumenModal() {}, actualizarBotonReprog() {}, mostrarErrorModal() {}, mostrarErrorReprog() {},
            postCita: async (_, datos) => {
                enviada = datos;
                return { success: false, message: 'Conflicto' };
            } };
        prepararContextoC4(contexto);
        vm.runInContext(funcion('public/js/calendario.js', nombre, '\n// ═'), contexto);
        await contexto[nombre]({ preventDefault() {} });
        assert.equal(enviada.hora, '08:00:30');
    });
}

// C4: estos casos C3 parten de una evaluación previa válida; la puerta negativa se cubre aparte.
function prepararContextoC4(contexto) {
    // Los casos de envío C3 parten del último paso, con la evaluación previa válida.
    contexto.pasoCitaModal = 1;
    contexto.avanzarPasoCita = () => {};
    contexto.triageModalC4 = { consulta: () => '123' };
    contexto.triageC4 = { consulta: () => '123' };
    vm.createContext(contexto);
}
