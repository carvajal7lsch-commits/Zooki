const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const raiz = path.join(__dirname, '../..');
const codigo = archivo => fs.readFileSync(path.join(raiz, archivo), 'utf8');

/** Elemento mínimo: lo que usan los módulos de M0.1, sin navegador. */
function elemento(datos = {}) {
    const clases = new Set();
    const atributos = {};
    const eventos = {};
    return {
        hidden: false,
        disabled: false,
        dataset: {},
        textContent: '',
        hijos: [],
        classList: {
            add: clase => clases.add(clase),
            remove: clase => clases.delete(clase),
            contains: clase => clases.has(clase),
            toggle: (clase, activa) => (activa ? clases.add(clase) : clases.delete(clase)),
        },
        setAttribute(nombre, valor) { atributos[nombre] = String(valor); },
        removeAttribute(nombre) { delete atributos[nombre]; },
        getAttribute: nombre => atributos[nombre] ?? null,
        addEventListener(tipo, callback) { (eventos[tipo] ||= []).push(callback); },
        disparar(tipo, evento) { return Promise.all((eventos[tipo] || []).map(callback => callback(evento))); },
        dispatchEvent(evento) { (eventos[evento.tipo] || []).forEach(callback => callback(evento)); return true; },
        appendChild(hijo) { this.hijos.push(hijo); },
        focus() { this.enfocado = true; },
        closest: () => null,
        querySelector: () => null,
        querySelectorAll: () => [],
        ...datos,
    };
}

class EventoPropio {
    constructor(tipo, datos) {
        this.tipo = tipo;
        this.detail = datos.detail;
    }
}

// ── Selector «Crear cuenta» ─────────────────────────────────────────────

function paginaDeLogin() {
    const enlace = elemento();
    const modal = elemento({ hidden: true });
    const flipper = elemento();
    const opcion = elemento();
    const cerrar = elemento();
    const body = elemento();
    const teclas = [];
    modal.querySelector = () => opcion;
    const documento = {
        body,
        querySelector: selector => (selector === '[data-crear-cuenta-abrir]' ? enlace : null),
        getElementById: id => ({ crearCuentaModal: modal, authFlipper: flipper })[id] || null,
        addEventListener(tipo, callback) {
            if (tipo === 'DOMContentLoaded') callback();
            if (tipo === 'keydown') teclas.push(callback);
        },
    };
    const contexto = { document: documento, setTimeout: callback => callback() };
    contexto.window = contexto;
    vm.runInNewContext(codigo('public/js/crear-cuenta.js'), contexto);
    const objetivo = (destino, coincide) => ({ target: { closest: selector => (coincide === selector ? destino : null) }, preventDefault() { this.prevenido = true; } });
    return { enlace, modal, flipper, opcion, cerrar, body, teclas, objetivo };
}

test('M0.1: «Crear cuenta» abre el selector y «Soy propietario» gira al registro', () => {
    const p = paginaDeLogin();
    const clic = { preventDefault() { this.prevenido = true; } };
    p.enlace.disparar('click', clic);
    assert.equal(clic.prevenido, true, 'Con JavaScript no navega: abre el selector.');
    assert.equal(p.modal.hidden, false);
    assert.equal(p.modal.classList.contains('active'), true);
    assert.equal(p.body.classList.contains('modal-open'), true);
    assert.equal(p.opcion.enfocado, true, 'El foco va a la primera opción.');
    const propietario = p.objetivo(p.opcion, '[data-crear-cuenta="propietario"]');
    p.modal.disparar('click', propietario);
    assert.equal(propietario.prevenido, true);
    assert.equal(p.modal.hidden, true, 'El selector se cierra.');
    assert.equal(p.flipper.classList.contains('flipped'), true, 'Se ve el registro del propietario.');
});

test('M0.1: el selector se cierra con la X, con el fondo y con Escape sin girar la tarjeta', () => {
    const p = paginaDeLogin();
    p.enlace.disparar('click', { preventDefault() {} });
    p.modal.disparar('click', p.objetivo(p.cerrar, '[data-crear-cuenta-cerrar]'));
    assert.equal(p.modal.hidden, true);
    p.enlace.disparar('click', { preventDefault() {} });
    p.modal.disparar('click', { target: p.modal });
    assert.equal(p.modal.hidden, true);
    p.enlace.disparar('click', { preventDefault() {} });
    p.teclas.forEach(callback => callback({ key: 'Escape' }));
    assert.equal(p.modal.hidden, true);
    assert.equal(p.flipper.classList.contains('flipped'), false);
});

test('M0.1: el login tiene un solo «Crear cuenta» con las dos opciones y sin JS en línea', () => {
    const vista = codigo('views/auth/login.php');
    assert.doesNotMatch(vista, /Registra tu clínica/);
    assert.equal((vista.match(/data-crear-cuenta-abrir/g) || []).length, 1);
    assert.match(vista, /href="index\.php\?action=register" data-crear-cuenta-abrir>Crear cuenta</, 'Sin JavaScript abre el registro del propietario.');
    assert.match(vista, /<strong>Soy propietario<\/strong>/);
    assert.match(vista, /<a class="crear-cuenta-opcion" href="index\.php\?action=registro_clinica">/);
    assert.match(vista, /<strong>Registrar mi clínica<\/strong>/);
    assert.doesNotMatch(vista, /\sstyle="|\son[a-z]+="|<script>\s*\S/);
    assert.doesNotMatch(codigo('views/auth/registro_clinica.php'), /\sstyle="|\son[a-z]+="|<script>\s*\S/);
});

// ── Pasos del registro de clínica ───────────────────────────────────────

function registroPorPasos(validos) {
    const form = elemento({ dataset: { registroPasos: 'registroPasos' } });
    const pasos = [1, 2, 3].map(numero => elemento({ dataset: { paso: String(numero) } }));
    const campos = {
        1: ['nombre_clinica', 'nit', 'dv'],
        2: ['documento', 'email'],
        3: ['acepta_datos'],
    };
    const porPaso = {};
    Object.entries(campos).forEach(([numero, nombres]) => {
        porPaso[numero] = nombres.map(nombre => {
            const campo = elemento({ name: nombre, tagName: 'INPUT', type: nombre === 'acepta_datos' ? 'checkbox' : 'text', value: '' });
            campo.closest = selector => (selector === '[data-validacion-cuenta]' ? form : (selector === '[data-paso]' ? pasos[Number(numero) - 1] : null));
            campo.checkValidity = () => validos.has(nombre);
            return campo;
        });
        pasos[Number(numero) - 1].querySelectorAll = () => porPaso[numero];
    });
    form.querySelectorAll = selector => (selector === '[data-paso]' ? pasos : []);
    form.contains = () => true;
    const items = [1, 2, 3].map(numero => elemento({ dataset: { pasoIndicador: String(numero) } }));
    const lista = elemento({ hidden: true, querySelectorAll: () => items });
    const comprobados = [];
    const limpiados = [];
    const contexto = {
        document: {
            getElementById: id => (id === 'registroPasos' ? lista : null),
            querySelectorAll: () => [],
            addEventListener() {},
        },
        CustomEvent: EventoPropio,
    };
    contexto.window = contexto;
    contexto.ZookiValidacionCuenta = {
        comprobar: async (formulario, campo) => {
            comprobados.push(campo.name);
            return validos.has(campo.name);
        },
    };
    contexto.ZookiRegistro = { limpiarResumen: formulario => limpiados.push(formulario) };
    vm.runInNewContext(codigo('public/js/registro-pasos.js'), contexto);
    contexto.ZookiPasos.vincular(form);
    const boton = direccion => elemento({ dataset: { pasoIr: direccion } });
    const pulsar = async direccion => {
        const objetivo = boton(direccion);
        await form.disparar('click', { target: { closest: () => objetivo }, preventDefault() {} });
        return objetivo;
    };
    const visible = () => pasos.findIndex(paso => !paso.hidden) + 1;
    return { form, pasos, porPaso, items, lista, comprobados, limpiados, pulsar, visible };
}

test('M0.1: el registro de clínica arranca en el paso 1 con el indicador visible', () => {
    const r = registroPorPasos(new Set());
    assert.equal(r.form.classList.contains('registro-por-pasos'), true);
    assert.equal(r.lista.hidden, false, 'El indicador solo aparece con JavaScript.');
    assert.equal(r.visible(), 1);
    assert.deepEqual(r.pasos.map(paso => paso.hidden), [false, true, true]);
    assert.equal(r.items[0].getAttribute('aria-current'), 'step');
    assert.equal(r.items[0].classList.contains('activo'), true);
    assert.equal(r.porPaso[1][0].enfocado, undefined, 'Al abrir no enfoca: en el celular abriría el teclado.');
});

test('M0.1: un paso con errores no avanza y avisa en el resumen', async () => {
    const r = registroPorPasos(new Set(['nombre_clinica', 'nit']));
    const invalidas = [];
    r.form.addEventListener('cuenta:invalida', evento => invalidas.push(Array.from(evento.detail.campos, campo => campo.name)));
    await r.pulsar('siguiente');
    assert.equal(r.visible(), 1, 'Sigue en el paso 1.');
    assert.deepEqual(r.comprobados, ['nombre_clinica', 'nit', 'dv'], 'Valida solo los campos del paso, con las reglas de validacion-cuenta.js.');
    assert.deepEqual(invalidas, [['dv']]);
    assert.equal(r.porPaso[1][2].enfocado, true, 'El foco va al primer campo con error.');
});

test('M0.1: con el paso válido avanza, y al volver atrás conserva lo escrito', async () => {
    const r = registroPorPasos(new Set(['nombre_clinica', 'nit', 'dv', 'documento', 'email']));
    r.porPaso[1][0].value = 'Clínica Huellitas';
    r.porPaso[1][1].value = '900123456';
    await r.pulsar('siguiente');
    assert.equal(r.visible(), 2);
    assert.equal(r.items[0].classList.contains('hecho'), true);
    assert.equal(r.items[1].getAttribute('aria-current'), 'step');
    assert.equal(r.porPaso[2][0].enfocado, true);
    assert.equal(r.limpiados.length, 1, 'Un paso válido limpia el resumen.');
    r.porPaso[2][0].value = '1030000001';
    await r.pulsar('anterior');
    assert.equal(r.visible(), 1, 'Atrás no valida.');
    assert.equal(r.porPaso[1][0].value, 'Clínica Huellitas');
    assert.equal(r.porPaso[1][1].value, '900123456');
    await r.pulsar('siguiente');
    await r.pulsar('siguiente');
    assert.equal(r.visible(), 3);
    assert.equal(r.porPaso[2][0].value, '1030000001', 'Lo escrito en el paso 2 sigue ahí.');
    await r.pulsar('siguiente');
    assert.equal(r.visible(), 3, 'No hay paso 4.');
});

test('M0.1: un error al enviar lleva al paso de su campo', () => {
    const r = registroPorPasos(new Set());
    r.pasos.forEach((paso, indice) => { paso.hidden = indice !== 2; });
    r.form.dataset.pasoActual = '3';
    r.form.dispatchEvent(new EventoPropio('cuenta:invalida', { detail: { campos: [r.porPaso[1][1]] } }));
    assert.equal(r.visible(), 1);
});

// ── Aviso de una línea ──────────────────────────────────────────────────

test('M0.1: los avisos bajo el campo son de una línea y guardan el texto completo', async () => {
    const css = codigo('public/css/registro-cuenta.css');
    const regla = css.slice(css.indexOf('.registro-marco .cuenta-validacion {'));
    const bloque = regla.slice(0, regla.indexOf('}'));
    assert.match(bloque, /white-space: nowrap/);
    assert.match(bloque, /text-overflow: ellipsis/);
    assert.match(bloque, /overflow: hidden/);
    // Textos cortos: los de validacion-cuenta.js y password-policy.js caben en media columna.
    const mensajes = codigo('public/js/validacion-cuenta.js').match(/const MENSAJES = \{([\s\S]*?)\};/)[1];
    const textos = [...mensajes.matchAll(/:\s*(['"])(.*?)\1/g)].map(coincidencia => coincidencia[2]);
    assert.equal(textos.length, 7);
    textos.concat([...codigo('public/js/password-policy.js').matchAll(/return '([^']+)';/g)].map(coincidencia => coincidencia[1]))
        .forEach(texto => assert.ok(texto.length <= 35, texto));

    // El aviso también queda en title para leerlo completo si no cabe.
    const salidas = [];
    const campo = {
        name: 'documento', id: 'documento', value: '12', type: 'text', dataset: {}, required: true,
        closest: () => null, atributos: {},
        parentElement: { querySelector: () => salidas[0] || null, appendChild: salida => salidas.push(salida) },
        setAttribute(clave, valor) { this.atributos[clave] = valor; },
        getAttribute(clave) { return this.atributos[clave]; },
        setCustomValidity(valor) { this.error = valor; },
    };
    const form = { dataset: {}, elements: {}, querySelectorAll: () => [campo], addEventListener() {}, dispatchEvent() {} };
    const contexto = {
        document: { forms: [form], querySelector: () => null, getElementById: () => null, createElement: () => ({ dataset: {}, setAttribute() {} }), addEventListener() {} },
        WeakMap, Set, CustomEvent: EventoPropio, setTimeout, clearTimeout,
    };
    contexto.window = contexto;
    vm.runInNewContext(codigo('public/js/validacion-cuenta.js'), contexto);
    contexto.ZookiValidacionCuenta.vincular(form);
    await contexto.ZookiValidacionCuenta.comprobar(form, campo, true);
    assert.equal(salidas[0].textContent, 'Documento de 5 a 15 dígitos.');
    assert.equal(salidas[0].title, 'Documento de 5 a 15 dígitos.');
});

test('M0.1: el envío con errores avisa al resumen con la etiqueta y el detalle de cada campo', () => {
    const resumen = elemento({ hidden: true });
    const form = elemento({ dataset: {} });
    form.querySelector = selector => (selector === '[data-registro-resumen]' ? resumen : null);
    const etiqueta = elemento({ textContent: 'Teléfono' });
    const grupo = elemento({ querySelector: () => etiqueta });
    const campo = elemento({ name: 'telefono', validationMessage: 'Teléfono de 7 a 20 caracteres.', closest: () => grupo });
    const contexto = {
        document: { createElement: () => elemento(), addEventListener() {}, querySelectorAll: () => [] },
    };
    contexto.window = contexto;
    vm.runInNewContext(codigo('public/js/registro-cuenta.js'), contexto);
    contexto.ZookiRegistro.vincular(form);
    form.dispatchEvent(new EventoPropio('cuenta:invalida', { detail: { campos: [campo] } }));
    assert.equal(resumen.hidden, false);
    assert.equal(resumen.hijos[0].textContent, 'Revisa estos campos antes de continuar:');
    assert.equal(resumen.hijos[1].hijos[0].textContent, 'Teléfono: Teléfono de 7 a 20 caracteres.');
});

// ── Ventana de carga al enviar ──────────────────────────────────────────

function registroConEspera(respuesta) {
    const estados = [elemento({ dataset: { esperaEstado: 'cargando' } }), elemento({ dataset: { esperaEstado: 'listo' }, hidden: true })];
    const mensaje = elemento();
    const ventana = elemento({ hidden: true, querySelectorAll: () => estados, querySelector: () => mensaje });
    const resumen = elemento({ hidden: true });
    const form = elemento({ id: 'registroClinicaForm', dataset: { registroEspera: 'registroClinicaEspera' } });
    form.querySelector = selector => (selector === '[data-registro-resumen]' ? resumen : null);
    form.reset = () => { form.reiniciado = true; };
    const reporte = elemento({ hidden: true });
    let completar;
    const peticiones = [];
    const renovados = [];
    const pasos = [];
    const contexto = {
        document: {
            createElement: () => elemento(),
            querySelectorAll: () => [],
            getElementById: id => ({ registroClinicaEspera: ventana, registroClinicaForm: form, reportarNit: reporte })[id] || null,
            addEventListener(tipo, callback) { if (tipo === 'DOMContentLoaded') callback(); },
        },
        FormData: class {},
        fetch: async (url, opciones) => {
            peticiones.push({ url, opciones });
            await new Promise(resolve => { completar = resolve; });
            return { headers: { get: () => 'application/json; charset=utf-8' }, json: async () => respuesta };
        },
    };
    contexto.window = contexto;
    contexto.ZookiTurnstile = { reiniciar: formulario => renovados.push(formulario) };
    contexto.ZookiPasos = { ir: (formulario, numero) => pasos.push(numero) };
    vm.runInNewContext(codigo('public/js/registro-cuenta.js'), contexto);
    vm.runInNewContext(codigo('public/js/registro-clinica.js'), contexto);
    const enviar = async () => {
        const envio = form.disparar('submit', { preventDefault() {} });
        await new Promise(resolve => setImmediate(resolve));
        return { envio, terminar: async () => { completar(); await envio; } };
    };
    return { form, ventana, estados, mensaje, resumen, reporte, peticiones, renovados, pasos, enviar };
}

test('M0.1: al enviar el registro de clínica aparece la ventana de carga y luego «Revisa tu correo»', async () => {
    const r = registroConEspera({ success: true, message: 'Revisa tu correo para activar la clínica' });
    const { terminar } = await r.enviar();
    assert.equal(r.form.hidden, true, 'El formulario se oculta mientras carga.');
    assert.equal(r.ventana.hidden, false);
    assert.deepEqual(r.estados.map(estado => estado.hidden), [false, true], 'Primero, la carga.');
    assert.equal(r.peticiones[0].url, 'index.php?action=registrar_clinica_ajax');
    assert.equal(r.peticiones[0].opciones.headers['X-Requested-With'], 'XMLHttpRequest');
    await terminar();
    assert.deepEqual(r.estados.map(estado => estado.hidden), [true, false], 'Después, «Revisa tu correo».');
    assert.equal(r.mensaje.textContent, 'Revisa tu correo para activar la clínica');
    assert.equal(r.form.reiniciado, true);
    assert.deepEqual(r.renovados, [r.form], 'El CAPTCHA de un solo uso se renueva.');
});

test('M0.1: si el servidor rechaza, vuelve al formulario con el mensaje y el NIT duplicado al paso 1', async () => {
    const r = registroConEspera({ success: false, message: 'Este NIT ya está registrado en Zooki.', reportar_nit: true });
    const { terminar } = await r.enviar();
    await terminar();
    assert.equal(r.form.hidden, false);
    assert.equal(r.ventana.hidden, true);
    assert.equal(r.resumen.hidden, false);
    assert.equal(r.resumen.hijos[0].textContent, 'Este NIT ya está registrado en Zooki.');
    assert.equal(r.reporte.hidden, false, '«Reportar a soporte» queda a mano.');
    assert.deepEqual(r.pasos, [1]);
    assert.equal(r.form.reiniciado, undefined, 'No se borra lo escrito.');
});

test('M0.1: los dos registros usan la misma ventana de espera y la misma casilla de la política', () => {
    const login = codigo('views/auth/login.php');
    const clinica = codigo('views/auth/registro_clinica.php');
    [login, clinica].forEach(vista => {
        assert.match(vista, /partials\/registro_espera\.php/);
        assert.match(vista, /partials\/consentimiento_datos\.php/);
        assert.match(vista, /js\/registro-cuenta\.js/);
        assert.match(vista, /data-registro data-registro-espera=/);
    });
    assert.match(codigo('public/js/register.js'), /ZookiRegistro\.enviar\(registerForm, 'process_register'\)/);
});

// ── Turnstile ───────────────────────────────────────────────────────────

test('M0.1: Turnstile en tema claro, del ancho del formulario y solo cuando su contenedor se ve', () => {
    const registro = elemento({ dataset: { sitekey: 'sitio' }, clientWidth: 400 });
    const login = elemento({ dataset: { sitekey: 'sitio', turnstileApariencia: 'always' }, hidden: true, clientWidth: 0 });
    const angosto = elemento({ dataset: { sitekey: 'sitio' }, clientWidth: 280 });
    const formLogin = elemento({ querySelector: () => login });
    const pintados = [];
    let iniciar;
    const contexto = {
        document: {
            querySelectorAll: () => [registro, login, angosto],
            addEventListener(tipo, callback) { iniciar = callback; },
        },
    };
    contexto.window = contexto;
    contexto.turnstile = {
        render(contenedor, opciones) {
            pintados.push({ contenedor, opciones });
            return 'widget-' + pintados.length;
        },
        reset() {},
    };
    vm.runInNewContext(codigo('public/js/turnstile-formularios.js'), contexto);
    iniciar();
    assert.equal(pintados.length, 2, 'El widget oculto del login no se pinta.');
    assert.deepEqual({ ...pintados[0].opciones }, { sitekey: 'sitio', theme: 'light', language: 'es', size: 'flexible', appearance: 'interaction-only' });
    assert.equal(pintados[1].opciones.size, 'compact', 'En un celular angosto no se sale del formulario.');
    contexto.ZookiTurnstile.mostrar(formLogin);
    assert.equal(login.hidden, false);
    assert.equal(pintados[2].opciones.appearance, 'always', 'El login lo muestra cuando el servidor lo pide.');
    contexto.ZookiTurnstile.mostrar(formLogin);
    assert.equal(pintados.length, 3, 'No se duplica.');
});

test('M0.1: Google con CAPTCHA pendiente muestra el widget del login', () => {
    const formulario = elemento();
    const mostrados = [];
    const avisos = [];
    const contexto = {
        document: { body: { dataset: {} }, getElementById: id => (id === 'loginForm' ? formulario : null), addEventListener() {} },
        Swal: { fire: opciones => avisos.push(opciones) },
        console: { log() {}, error() {} },
    };
    contexto.window = contexto;
    contexto.ZookiTurnstile = { mostrar: form => mostrados.push(form) };
    vm.runInNewContext(codigo('public/js/login.js') + '\nthis.responder = responderGoogle;', contexto);
    contexto.responder({ success: false, message: 'Por seguridad, completa la verificación y vuelve a continuar con Google.', extra: { captcha: true } });
    assert.deepEqual(mostrados, [formulario]);
    assert.equal(avisos[0].icon, 'info');
});
