const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const raiz = path.join(__dirname, '../..');
const codigo = archivo => fs.readFileSync(path.join(raiz, archivo), 'utf8');

/** Elemento mínimo con lo que usan plataforma.js, acceso-giro.js y registro-pasos.js. */
function elemento(datos = {}) {
    const clases = new Set();
    const atributos = {};
    return {
        hidden: false,
        dataset: {},
        value: '',
        textContent: '',
        className: '',
        classList: {
            add: clase => clases.add(clase),
            remove: clase => clases.delete(clase),
            contains: clase => clases.has(clase),
            toggle: (clase, activa) => (activa ? clases.add(clase) : clases.delete(clase)),
        },
        setAttribute(nombre, valor) { atributos[nombre] = String(valor); },
        getAttribute: nombre => atributos[nombre] ?? null,
        removeAttribute(nombre) { delete atributos[nombre]; },
        closest: () => null,
        querySelector: () => null,
        querySelectorAll: () => [],
        focus() { this.enfocado = true; },
        ...datos,
    };
}

// ── Panel: pestañas, filtros y modal ───────────────────────

function panel(memoriaInicial = {}) {
    const memoria = new Map(Object.entries(memoriaInicial));
    const eventos = {};
    const pestanas = ['clinicas', 'soporte', 'seguridad'].map(nombre => elemento({ dataset: { pestana: nombre } }));
    const paneles = ['clinicas', 'soporte', 'seguridad'].map(nombre => elemento({ dataset: { panel: nombre } }));
    const tarjetas = elemento({ dataset: { listaVista: 'tarjetas' } });
    const tabla = elemento({ dataset: { listaVista: 'tabla' } });
    const clinica = (nombre, nit, estado, plan, enTabla) => elemento({
        dataset: { buscar: (nombre + ' ' + nit.replace('-', '') + ' ' + nit).toLowerCase(), estado, plan },
        closest: selector => (selector === '[data-lista-vista="tarjetas"]' && !enTabla ? tarjetas : null),
    });
    const filas = [
        clinica('Clínica Norte', '901234567-7', 'activa', '1', false),
        clinica('Clínica Sur', '900654321-0', 'suspendida', '2', false),
        clinica('Pendiente', '900123456-8', 'pendiente_verificacion', '', false),
        clinica('Clínica Norte', '901234567-7', 'activa', '1', true),
    ];
    const buscar = elemento();
    const estado = elemento();
    const plan = elemento();
    const vacio = elemento({ hidden: true });
    const filtros = elemento();
    const selectores = {
        '[data-pestana]': pestanas,
        '[data-panel]': paneles,
        '[data-lista-vista]': [tarjetas, tabla],
        '[data-vista]': [],
        '[data-clinica-fila]': filas,
    };
    const unico = {
        '[data-filtro-buscar]': buscar,
        '[data-filtro-estado]': estado,
        '[data-filtro-plan]': plan,
        '[data-sin-resultados]': vacio,
        '[data-filtros-clinicas]': filtros,
    };
    const contexto = {
        TextEncoder,
        document: {
            addEventListener: (tipo, callback) => { eventos[tipo] = callback; },
            querySelectorAll: selector => selectores[selector] || [],
            querySelector: selector => unico[selector] || null,
            getElementById: () => null,
        },
        localStorage: { getItem: clave => memoria.get(clave) ?? null, setItem: (clave, valor) => memoria.set(clave, valor) },
    };
    contexto.window = contexto;
    vm.runInNewContext(codigo('public/js/plataforma.js'), contexto);
    return { api: contexto.ZookiPlataforma, eventos, memoria, pestanas, paneles, filas, buscar, estado, plan, vacio, filtros };
}

const visibles = p => p.filas.slice(0, 3).filter(fila => !fila.hidden).length;

test('M0.2: una sola pestaña a la vista y se recuerda al volver', () => {
    const p = panel();
    p.eventos.DOMContentLoaded();
    assert.deepEqual(p.paneles.map(panelX => panelX.hidden), [false, true, true], 'Clínicas por defecto.');
    p.api.pestana('soporte');
    assert.deepEqual(p.paneles.map(panelX => panelX.hidden), [true, false, true]);
    assert.equal(p.pestanas[1].getAttribute('aria-selected'), 'true');
    assert.equal(p.pestanas[0].getAttribute('aria-selected'), 'false');
    assert.equal(p.memoria.get('zooki-plataforma-pestana'), 'soporte');
    const otraVisita = panel(Object.fromEntries(p.memoria));
    otraVisita.eventos.DOMContentLoaded();
    assert.deepEqual(otraVisita.paneles.map(panelX => panelX.hidden), [true, false, true], 'La pestaña se recuerda.');
    p.api.pestana('desconocida');
    assert.equal(p.paneles[0].hidden, false, 'Un valor ajeno vuelve a Clínicas.');
});

test('M0.2: el buscador, los filtros y la vista solo se ven en la pestaña Clínicas', () => {
    const p = panel();
    p.eventos.DOMContentLoaded();
    assert.equal(p.filtros.classList.contains('plataforma-filtros--vivo'), true, 'Con JS el botón Buscar sobra.');
    assert.equal(p.filtros.classList.contains('is-active'), true);
    p.api.pestana('seguridad');
    assert.equal(p.filtros.classList.contains('is-active'), false);
    p.api.pestana('clinicas');
    assert.equal(p.filtros.classList.contains('is-active'), true);
    assert.equal(p.api.indicador, undefined, 'Sin franja de indicadores (revisión del usuario, 2026-10-12).');
});

test('M0.2: el buscador encuentra por nombre o NIT con guion, combina estado y plan, y muestra el vacío', () => {
    const p = panel();
    p.eventos.DOMContentLoaded();
    p.buscar.value = '901234567-7';
    p.eventos.input({ target: { matches: selector => selector === '[data-filtro-buscar]' } });
    assert.equal(visibles(p), 1);
    assert.equal(p.filas[3].hidden, false, 'La tabla filtra igual que las tarjetas.');
    p.buscar.value = 'clínica';
    p.plan.value = '2';
    p.eventos.change({ target: { matches: () => true } });
    assert.equal(visibles(p), 1);
    assert.equal(p.vacio.hidden, true);
    p.estado.value = 'activa';
    assert.equal(p.api.filtrar(), 0);
    assert.equal(p.vacio.hidden, false, 'Sin coincidencias se ve el estado vacío.');
});

test('M0.2: el modal «Gestionar» abre por pestañas con estado, NIT y administrador en la cabecera', () => {
    const pestanasModal = ['plan', 'estado'].map(nombre => elemento({ dataset: { modalPestana: nombre } }));
    const panelesModal = ['plan', 'estado'].map(nombre => elemento({ dataset: { modalPanel: nombre } }));
    const cabecera = { titulo: elemento(), estado: elemento({ prepend(hijo) { this.icono = hijo; } }), nit: elemento(), admin: elemento() };
    const formulario = accion => {
        const salida = elemento();
        const boton = elemento({ disabled: false });
        const aviso = elemento({ hidden: true });
        const opciones = [{ value: 'suspendida' }, { value: 'baja' }, { value: 'activa' }];
        return elemento({
            dataset: { plataformaAccion: accion },
            elements: { id_clinica: { value: '' }, id_plan: { value: '' }, estado: { value: '', options: opciones }, motivo: { setCustomValidity() {} } },
            reset() {},
            querySelector: selector => ({ '[data-resultado]': salida, '[data-error-campo]': elemento(), button: boton, '[data-aviso-plan]': aviso })[selector] || null,
            boton, aviso,
        });
    };
    const plan = formulario('plataforma_suscripcion_ajax');
    const estados = formulario('plataforma_estado_ajax');
    const dialogo = elemento({
        querySelector: selector => ({
            '[data-clinica-titulo]': cabecera.titulo, '[data-clinica-estado]': cabecera.estado, '[data-clinica-nit]': cabecera.nit,
            '[data-clinica-admin]': cabecera.admin, '[data-plataforma-accion="plataforma_suscripcion_ajax"]': plan,
            '[data-plataforma-accion="plataforma_estado_ajax"]': estados, select: elemento(),
        })[selector] || null,
        querySelectorAll: selector => ({ form: [plan, estados], '[data-modal-pestana]': pestanasModal, '[data-modal-panel]': panelesModal })[selector] || [],
    });
    const gestionar = elemento({ dataset: { gestionarClinica: '4', nombre: 'Pendiente', estado: 'pendiente_verificacion', estadoTexto: 'Pendiente de verificación', estadoClase: 'plataforma-estado--pendiente', estadoIcono: 'bi-hourglass-split', nit: '900123456-8', administrador: 'olga@zooki.test (por confirmar)', plan: '' } });
    gestionar.hasAttribute = () => false;
    const eventos = {};
    const contexto = {
        TextEncoder,
        localStorage: { getItem: () => null, setItem() {} },
        document: {
            addEventListener: (tipo, callback) => { eventos[tipo] = callback; },
            querySelectorAll: () => [],
            querySelector: () => null,
            createElement: () => elemento(),
            getElementById: id => (id === 'gestionarClinica' ? dialogo : null),
        },
    };
    contexto.window = contexto;
    vm.runInNewContext(codigo('public/js/plataforma.js'), contexto);
    contexto.ZookiPlataforma.pestanaModal(dialogo, 'estado');
    assert.deepEqual(panelesModal.map(panelX => panelX.hidden), [true, false]);
    assert.equal(pestanasModal[1].getAttribute('aria-selected'), 'true');
    contexto.ZookiPlataforma.pestanaModal(dialogo, 'plan');
    assert.deepEqual(panelesModal.map(panelX => panelX.hidden), [false, true]);
    // Una clínica pendiente abre en Estado, con su cabecera.
    eventos.click({ target: { closest: () => gestionar } });
    assert.equal(cabecera.titulo.textContent, 'Pendiente');
    assert.equal(cabecera.estado.textContent, ' Pendiente de verificación');
    assert.equal(cabecera.estado.className, 'tag-mini plataforma-estado plataforma-estado--pendiente');
    assert.equal(cabecera.estado.icono.className, 'bi bi-hourglass-split', 'La misma etiqueta con icono de la tarjeta.');
    assert.equal(cabecera.nit.textContent, 'NIT 900123456-8');
    assert.equal(cabecera.admin.textContent, 'olga@zooki.test (por confirmar)');
    assert.equal(plan.boton.disabled, true, 'Sin plan hasta verificar.');
    assert.equal(plan.aviso.hidden, false, 'Y se dice por qué.');
    assert.deepEqual(panelesModal.map(panelX => panelX.hidden), [true, false], 'Abre en Estado.');
    assert.equal(dialogo.classList.contains('is-open'), true);
});

test('M0.2: la vista del panel sin JS ni CSS en línea y con un contador por pestaña', () => {
    const vista = codigo('views/plataforma/inicio.php');
    assert.doesNotMatch(vista, /\sstyle=|\son[a-z]+=|<script>\s*\S/);
    assert.match(vista, /data-contador-soporte/);
    assert.match(vista, /data-contador-seguridad/);
    assert.doesNotMatch(vista, /data-indicador|plataforma-indicador/);
    assert.match(vista, /class="header-container-white/);
    assert.doesNotMatch(codigo('public/css/plataforma.css'), /#(?:067647|ECFDF3|B54708|FFFAEB|B42318|FEF3F2|5925DC|F4F3FF)/i, 'Sin verdes, ámbar, rojos ni violetas: identidad azul.');
    assert.equal((vista.match(/data-modal-pestana=/g) || []).length, 2);
    assert.doesNotMatch(vista, /\?v=e2|\?v=d\d/);
});

// ── Acceso: aviso sin salto y giro de la tarjeta ────────────────────────

test('M0.2: el aviso de cada campo vive en un espacio reservado y no mueve el formulario', () => {
    const css = codigo('public/css/registro-cuenta.css');
    const bloque = selector => {
        const inicio = css.indexOf(selector + ' {');
        assert.notEqual(inicio, -1, selector);
        return css.slice(inicio, css.indexOf('}', inicio));
    };
    assert.match(bloque('.registro-marco .input-group'), /padding-bottom: 1\.15rem/);
    assert.match(bloque('.registro-marco .input-group'), /position: relative/);
    const aviso = bloque('.registro-marco .input-group > .cuenta-validacion');
    assert.match(aviso, /position: absolute/);
    assert.match(aviso, /bottom: 0/);
    assert.match(bloque('.registro-marco .cuenta-validacion'), /margin: 0/);
    assert.match(codigo('public/css/registro-clinica.css'), /\.registro-nit-aviso \{[^}]*height: 1\.4rem/);
});

function giro({ marca = null, reducido = false } = {}) {
    const almacen = new Map(marca ? [['zooki-acceso-giro', marca]] : []);
    const tarjeta = elemento();
    const eventos = {};
    const temporizadores = [];
    const ubicacion = { href: 'index.php?action=login' };
    const contexto = {
        document: {
            querySelector: selector => (selector === '[data-acceso-giro-tarjeta]' ? tarjeta : null),
            addEventListener: (tipo, callback) => { eventos[tipo] = callback; },
        },
        sessionStorage: {
            getItem: clave => almacen.get(clave) ?? null,
            setItem: (clave, valor) => almacen.set(clave, valor),
            removeItem: clave => almacen.delete(clave),
        },
        setTimeout: (callback, ms) => { temporizadores.push({ callback, ms }); },
        matchMedia: () => ({ matches: reducido }),
        location: ubicacion,
    };
    contexto.window = contexto;
    vm.runInNewContext(codigo('public/js/acceso-giro.js'), contexto);
    return { contexto, tarjeta, eventos, temporizadores, ubicacion, almacen };
}

test('M0.2: ir al registro de clínica gira la tarjeta antes de navegar y la página nueva termina el giro', () => {
    const salida = giro();
    const enlace = elemento({ getAttribute: nombre => (nombre === 'href' ? 'index.php?action=registro_clinica' : null) });
    enlace.closest = selector => (selector === 'a[data-acceso-giro-ir]' ? enlace : null);
    const clic = { target: { closest: selector => enlace.closest(selector) }, defaultPrevented: false, preventDefault() { this.prevenido = true; } };
    salida.eventos.click(clic);
    assert.equal(clic.prevenido, true);
    assert.equal(salida.tarjeta.classList.contains('acceso-giro-salida'), true);
    assert.equal(salida.ubicacion.href, 'index.php?action=login', 'Todavía no navega: primero gira.');
    assert.equal(salida.temporizadores[0].ms, 380);
    salida.temporizadores[0].callback();
    assert.equal(salida.ubicacion.href, 'index.php?action=registro_clinica');
    assert.equal(salida.almacen.get('zooki-acceso-giro'), '1');

    const llegada = giro({ marca: '1' });
    llegada.eventos.DOMContentLoaded();
    assert.equal(llegada.tarjeta.classList.contains('acceso-giro-entrada'), true);
    assert.equal(llegada.almacen.has('zooki-acceso-giro'), false, 'La marca vale para un solo salto.');
    const sinMarca = giro();
    sinMarca.eventos.DOMContentLoaded();
    assert.equal(sinMarca.tarjeta.classList.contains('acceso-giro-entrada'), false, 'Al abrir la página directo no gira.');
});

test('M0.2: sin movimiento o con la navegación ya cancelada no hay giro', () => {
    const reducido = giro({ reducido: true });
    const enlace = elemento({ getAttribute: () => 'index.php?action=login' });
    enlace.closest = () => enlace;
    reducido.eventos.click({ target: { closest: () => enlace }, defaultPrevented: false, preventDefault() {} });
    assert.equal(reducido.ubicacion.href, 'index.php?action=login');
    assert.equal(reducido.tarjeta.classList.contains('acceso-giro-salida'), false);
    const cancelado = giro();
    const clic = { target: { closest: () => enlace }, defaultPrevented: true, preventDefault() { this.prevenido = true; } };
    cancelado.eventos.click(clic);
    assert.equal(clic.prevenido, undefined, 'registro-pasos.js ya usó la flecha para volver de paso.');
    assert.equal(cancelado.temporizadores.length, 0);
});

test('M0.2: la flecha del registro de clínica es el único Atrás: vuelve de paso y en el paso 1 va al login', async () => {
    const form = elemento({ dataset: { registroPasos: 'registroPasos', registroAtras: 'registroAtras' } });
    const pasos = [1, 2, 3].map(numero => elemento({ dataset: { paso: String(numero) } }));
    form.querySelectorAll = selector => (selector === '[data-paso]' ? pasos : []);
    form.addEventListener = () => {};
    const escuchas = {};
    const flecha = elemento({ addEventListener: (tipo, callback) => { escuchas[tipo] = callback; } });
    const contexto = {
        document: {
            getElementById: id => (id === 'registroAtras' ? flecha : (id === 'registroPasos' ? elemento() : null)),
            querySelectorAll: () => [],
            addEventListener() {},
        },
    };
    contexto.window = contexto;
    vm.runInNewContext(codigo('public/js/registro-pasos.js'), contexto);
    contexto.ZookiPasos.vincular(form);
    assert.equal(flecha.getAttribute('aria-label'), 'Volver al inicio de sesión');
    const enPaso1 = { preventDefault() { this.prevenido = true; } };
    escuchas.click(enPaso1);
    assert.equal(enPaso1.prevenido, undefined, 'En el paso 1 sigue su enlace al login (con el giro).');
    contexto.ZookiPasos.ir(form, 3, false);
    assert.equal(flecha.getAttribute('aria-label'), 'Volver al paso anterior');
    const enPaso3 = { preventDefault() { this.prevenido = true; } };
    escuchas.click(enPaso3);
    assert.equal(enPaso3.prevenido, true);
    assert.equal(form.dataset.pasoActual, '2');
    assert.deepEqual(pasos.map(paso => paso.hidden), [true, false, true]);
    const vista = codigo('views/auth/registro_clinica.php');
    assert.doesNotMatch(vista, /data-paso-ir="anterior"/, 'Sin botones Atrás abajo.');
    assert.match(vista, /<a class="btn-back-top" id="registroAtras" href="index\.php\?action=login" data-acceso-giro-ir/);
    assert.match(codigo('views/auth/login.php'), /class="btn-back-top"/, 'El mismo componente del registro del propietario.');
});
