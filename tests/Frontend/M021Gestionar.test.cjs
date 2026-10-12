const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const raiz = path.join(__dirname, '../..');
const codigo = archivo => fs.readFileSync(path.join(raiz, archivo), 'utf8');

/** Elemento mínimo con lo que usa el modal «Gestionar». */
function elemento(datos = {}) {
    const clases = new Set();
    const atributos = {};
    return {
        hidden: false,
        disabled: false,
        checked: false,
        dataset: {},
        value: '',
        type: 'text',
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
        setCustomValidity(valor) { this.error = valor; },
        prepend(hijo) { this.icono = hijo; },
        focus() { this.enfocado = true; },
        closest: () => null,
        matches: () => false,
        querySelector: () => null,
        querySelectorAll: () => [],
        ...datos,
    };
}

/** El modal tal como lo arma views/plataforma/inicio.php, con su plataforma.js. */
function pagina() {
    const cabecera = { titulo: elemento(), estado: elemento(), nit: elemento(), admin: elemento() };
    // Plan y suscripción.
    const planes = [elemento({ type: 'radio', name: 'id_plan', value: '1', dataset: { planGratuito: '1' } }), elemento({ type: 'radio', name: 'id_plan', value: '2', dataset: { planGratuito: '0' } })];
    const interruptor = elemento({ type: 'checkbox', checked: true });
    const inactivas = [elemento({ type: 'radio', name: 'suscripcion_inactiva', value: 'suspendida' }), elemento({ type: 'radio', name: 'suscripcion_inactiva', value: 'cancelada' })];
    const valorSuscripcion = elemento({ type: 'hidden', value: 'activa' });
    const bloqueSuscripcion = elemento();
    const grupoInactivas = elemento({ hidden: true });
    const avisoGratuito = elemento({ hidden: true });
    const motivoPlan = elemento({ type: 'textarea', dataset: { maxBytes: '65535' } });
    const sugerenciaPlan = elemento({ type: 'button' });
    const plan = elemento({
        dataset: { plataformaAccion: 'plataforma_suscripcion_ajax' },
        elements: { id_clinica: elemento(), motivo: motivoPlan, estado: valorSuscripcion },
        reset() {},
    });
    plan.querySelectorAll = selector => ({
        'input[name="id_plan"]': planes,
        'input[name="suscripcion_inactiva"]': inactivas,
        'input, textarea, button': [...planes, interruptor, ...inactivas, valorSuscripcion, motivoPlan, sugerenciaPlan],
        '[data-resultado], [data-error-campo]': [],
    })[selector] || [];
    plan.querySelector = selector => ({
        'input[name="id_plan"]:checked': planes.find(opcion => opcion.checked) || null,
        'input[name="suscripcion_inactiva"]:checked': inactivas.find(opcion => opcion.checked) || null,
        '[data-suscripcion]': bloqueSuscripcion,
        '[data-suscripcion-gratuito]': avisoGratuito,
        '[data-suscripcion-activa]': interruptor,
        '[data-suscripcion-inactiva]': grupoInactivas,
        '[data-suscripcion-valor]': valorSuscripcion,
        '[data-resultado]': elemento(),
        '[data-error-campo]': elemento(),
    })[selector] || null;
    // Estado: tarjetas de acción.
    const acciones = ['suspendida', 'activa', 'baja'].map(valor => {
        const opcion = elemento({ type: 'radio', name: 'estado', value: valor });
        return elemento({ dataset: { opcionEstado: valor }, querySelector: () => opcion, opcion });
    });
    const estadoRadios = { get value() { return acciones.map(tarjeta => tarjeta.opcion).find(opcion => opcion.checked)?.value || ''; } };
    const estados = elemento({
        dataset: { plataformaAccion: 'plataforma_estado_ajax' },
        elements: { id_clinica: elemento(), motivo: elemento({ dataset: { maxBytes: '65535' } }), estado: estadoRadios },
        reset() {},
        reportValidity: () => true,
    });
    estados.matches = selector => selector === '[data-estado-acciones]';
    estados.querySelector = selector => ({ '[data-resultado]': elemento(), '[data-error-campo]': elemento() })[selector] || null;
    const guardar = elemento();
    const aplicar = elemento({ textContent: 'Elige una acción' });
    const aviso = elemento({ hidden: true });
    const pie = elemento();
    const detalle = elemento({ hidden: true });
    const notaTexto = elemento();
    const nota = elemento({ hidden: true, querySelector: selector => (selector === '[data-estado-nota-texto]' ? notaTexto : null) });
    const paneles = ['plan', 'estado'].map(nombre => elemento({ dataset: { modalPanel: nombre } }));
    const dialogo = elemento({
        querySelector: selector => ({
            '[data-clinica-titulo]': cabecera.titulo,
            '[data-clinica-estado]': cabecera.estado,
            '[data-clinica-nit]': cabecera.nit,
            '[data-clinica-admin]': cabecera.admin,
            '[data-plataforma-accion="plataforma_suscripcion_ajax"]': plan,
            '[data-guardar-plan]': guardar,
            '[data-aviso-plan]': aviso,
            '[data-estado-acciones]': estados,
            '[data-pie-estado]': pie,
            '[data-estado-detalle]': detalle,
            '[data-estado-nota]': nota,
            '[data-aplicar-estado]': aplicar,
            '.close-modal': elemento(),
        })[selector] || null,
        querySelectorAll: selector => ({
            form: [plan, estados],
            '[data-opcion-estado]': acciones,
            '[data-modal-pestana]': [],
            '[data-modal-panel]': paneles,
        })[selector] || [],
    });
    const eventos = {};
    const solicitudes = [];
    const confirmaciones = [];
    const contexto = {
        TextEncoder,
        FormData: class { constructor(form) { this.form = form; } },
        localStorage: { getItem: () => null, setItem() {} },
        document: {
            body: elemento(),
            addEventListener: (tipo, callback) => { eventos[tipo] = callback; },
            querySelectorAll: () => [],
            querySelector: () => null,
            createElement: () => elemento(),
            getElementById: id => (id === 'gestionarClinica' ? dialogo : null),
        },
        Swal: { fire: async opciones => { confirmaciones.push(opciones); return { isConfirmed: true }; } },
        fetch: async (url, opciones) => {
            solicitudes.push([url, opciones]);
            return { ok: true, json: async () => ({ success: false, message: 'Rechazado en la prueba' }), text: async () => '' };
        },
    };
    contexto.window = contexto;
    vm.runInNewContext(codigo('public/js/plataforma.js'), contexto);
    const abrir = datos => {
        const boton = elemento({ dataset: { gestionarClinica: '4', nombre: 'Clínica X', estadoTexto: 'Texto', estadoClase: 'plataforma-estado--x', estadoIcono: 'bi-x', nit: '901234567-7', administrador: 'admin@zooki.test', suscripcion: '', ...datos } });
        boton.hasAttribute = () => false;
        eventos.click({ target: { closest: () => boton } });
    };
    /** Un cambio en un control del formulario, como lo dispara el navegador. */
    const cambiar = (control, formulario) => {
        control.closest = () => formulario;
        control.matches = selector => selector.includes('name="' + control.name + '"') || (control === interruptor && selector.includes('[data-suscripcion-activa]'));
        eventos.change({ target: control });
    };
    return { api: contexto.ZookiPlataforma, abrir, cambiar, eventos, dialogo, cabecera, plan, planes, interruptor, inactivas, valorSuscripcion, bloqueSuscripcion, grupoInactivas, avisoGratuito, motivoPlan, sugerenciaPlan, guardar, aviso, acciones, estados, aplicar, pie, detalle, nota, notaTexto, paneles, solicitudes, confirmaciones };
}

const visibles = p => p.acciones.filter(tarjeta => !tarjeta.hidden).map(tarjeta => tarjeta.dataset.opcionEstado);

test('M0.2.1: las acciones de estado dependen del estado actual (activa, suspendida, pendiente y de baja)', () => {
    const { accionesEstado } = pagina().api;
    const plano = valor => JSON.parse(JSON.stringify(valor));
    assert.deepEqual(plano(accionesEstado('activa', true)), { acciones: ['suspendida'], baja: true, nota: null });
    assert.deepEqual(plano(accionesEstado('suspendida', true)), { acciones: ['activa'], baja: true, nota: null });
    assert.deepEqual(plano(accionesEstado('suspendida', false)).acciones, [], 'Sin registro verificado no se reactiva (Plataforma::cambiarEstado).');
    assert.deepEqual(plano(accionesEstado('pendiente_verificacion', false)).acciones, ['suspendida'], 'Lo que hoy permite el servidor.');
    assert.deepEqual(plano(accionesEstado('baja', true)), { acciones: [], baja: false, nota: 'La clínica está dada de baja: no se puede reactivar desde este panel.' });
});

test('M0.2.1: una activa muestra las tarjetas «Suspender» y «Dar de baja», sin elegir; al elegir aparecen el motivo y el botón', () => {
    const p = pagina();
    p.abrir({ estado: 'activa', plan: '2', suscripcion: 'activa' });
    assert.deepEqual(visibles(p), ['suspendida', 'baja']);
    assert.equal(p.acciones[1].opcion.disabled, true, '«Reactivar» no se ofrece a una activa.');
    assert.equal(p.acciones.some(tarjeta => tarjeta.opcion.checked), false, 'Nada elegido al abrir.');
    assert.equal(p.detalle.hidden, true);
    assert.equal(p.aplicar.disabled, true);
    assert.equal(p.aplicar.textContent, 'Elige una acción');
    p.acciones[0].opcion.checked = true;
    p.cambiar(p.acciones[0].opcion, p.estados);
    assert.equal(p.detalle.hidden, false, 'Aparece el motivo con sus sugerencias.');
    assert.equal(p.aplicar.disabled, false);
    assert.equal(p.aplicar.textContent, 'Suspender clínica');
    assert.equal(p.aplicar.className, 'btn-modal-primary');
    p.acciones[0].opcion.checked = false;
    p.acciones[2].opcion.checked = true;
    p.cambiar(p.acciones[2].opcion, p.estados);
    assert.equal(p.aplicar.textContent, 'Dar de baja');
    assert.equal(p.aplicar.className, 'btn-modal-peligro', 'Dar de baja en rojo.');
});

test('M0.2.1: una suspendida ofrece «Reactivar»; sin registro verificado solo «Dar de baja», con nota; de baja, nada', () => {
    const p = pagina();
    p.abrir({ estado: 'suspendida', plan: '1' });
    assert.deepEqual(visibles(p), ['activa', 'baja']);
    p.abrir({ estado: 'suspendida', plan: '' });
    assert.deepEqual(visibles(p), ['baja']);
    assert.equal(p.nota.hidden, false);
    assert.match(p.notaTexto.textContent, /no verificó su registro/);
    p.abrir({ estado: 'baja', plan: '1' });
    assert.deepEqual(visibles(p), []);
    assert.equal(p.estados.hidden, true);
    assert.equal(p.pie.hidden, true);
    assert.match(p.notaTexto.textContent, /dada de baja/);
});

test('M0.2.1: pendiente: plan deshabilitado con la nota arriba y abre en Estado con «Suspender»', () => {
    const p = pagina();
    p.abrir({ estado: 'pendiente_verificacion', plan: '' });
    assert.deepEqual(visibles(p), ['suspendida', 'baja']);
    assert.equal(p.aviso.hidden, false);
    assert.equal(p.guardar.disabled, true);
    assert.equal(p.planes.every(opcion => opcion.disabled), true);
    assert.equal(p.interruptor.disabled, true);
    assert.equal(p.motivoPlan.disabled, true);
    assert.equal(p.sugerenciaPlan.disabled, true);
    assert.equal(p.valorSuscripcion.disabled, false, 'El campo oculto no se toca.');
    assert.equal(p.paneles[1].hidden, false);
});

test('M0.2.1: plan marcado, interruptor de suscripción y el Gratuito sin pago', () => {
    const p = pagina();
    p.abrir({ estado: 'activa', plan: '2', suscripcion: 'suspendida' });
    assert.equal(p.planes[1].checked, true, 'El plan actual queda marcado.');
    assert.equal(p.planes[0].checked, false);
    assert.equal(p.interruptor.checked, false, 'Suscripción suspendida: el interruptor apagado.');
    assert.equal(p.grupoInactivas.hidden, false, 'Se ve por qué: Suspendida o Cancelada.');
    assert.equal(p.inactivas[0].checked, true);
    assert.equal(p.valorSuscripcion.value, 'suspendida');
    p.interruptor.checked = true;
    p.cambiar(p.interruptor, p.plan);
    assert.equal(p.grupoInactivas.hidden, true);
    assert.equal(p.valorSuscripcion.value, 'activa');
    p.interruptor.checked = false;
    p.inactivas[0].checked = false;
    p.inactivas[1].checked = true;
    p.cambiar(p.inactivas[1], p.plan);
    assert.equal(p.valorSuscripcion.value, 'cancelada');
    // Al elegir el Gratuito no hay pago: se oculta la suscripción y va «activa».
    p.planes[1].checked = false;
    p.planes[0].checked = true;
    p.cambiar(p.planes[0], p.plan);
    assert.equal(p.bloqueSuscripcion.hidden, true);
    assert.equal(p.avisoGratuito.hidden, false);
    assert.equal(p.valorSuscripcion.value, 'activa');
    assert.equal(p.guardar.disabled, false);
    assert.equal(p.planes[0].disabled, false, 'En una activa los campos están habilitados.');
});

test('M0.2.1: una sugerencia de motivo llena el campo y se puede editar', () => {
    const p = pagina();
    p.abrir({ estado: 'activa', plan: '2' });
    const errores = elemento();
    p.motivoPlan.parentElement = { querySelector: () => errores };
    const sugerencia = elemento({ type: 'button', dataset: { motivoSugerido: 'Pago recibido' } });
    sugerencia.closest = selector => (selector === 'button' ? sugerencia : (selector === 'form' ? { querySelector: () => p.motivoPlan } : null));
    sugerencia.hasAttribute = () => false;
    p.eventos.click({ target: sugerencia });
    assert.equal(p.motivoPlan.value, 'Pago recibido');
    assert.equal(p.motivoPlan.enfocado, true);
    assert.equal(errores.textContent, '', 'Se valida al instante.');
});

test('M0.2.1: suspender se aplica directo; solo «Dar de baja» confirma', async () => {
    const p = pagina();
    p.abrir({ estado: 'activa', plan: '2' });
    p.acciones[0].opcion.checked = true;
    p.eventos.submit({ target: p.estados, submitter: elemento(), preventDefault() {} });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(p.confirmaciones.length, 0, 'Suspender no pide confirmación.');
    assert.equal(p.solicitudes[0][1].body.form.elements.estado.value, 'suspendida');
    p.acciones[0].opcion.checked = false;
    p.acciones[2].opcion.checked = true;
    p.eventos.submit({ target: p.estados, submitter: elemento(), preventDefault() {} });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(p.confirmaciones.length, 1, 'Dar de baja confirma.');
    assert.equal(p.solicitudes[1][1].body.form.elements.estado.value, 'baja');
    assert.equal(p.api.confirmacion(p.plan), null, 'El plan no confirma.');
});

test('M0.2.1: la vista: tarjetas de elección, interruptor, sugerencias, «Dar de baja» como tarjeta y sin listas nativas', () => {
    const vista = codigo('views/plataforma/inicio.php');
    const modal = vista.slice(vista.indexOf('id="gestionarClinica"'));
    assert.match(modal, /plataforma-modal__titulo[\s\S]*data-clinica-titulo[\s\S]*data-clinica-estado/);
    assert.match(modal, /plataforma-modal__datos[\s\S]*data-clinica-nit[\s\S]*data-clinica-admin/);
    assert.ok(modal.indexOf('data-aviso-plan') < modal.indexOf('name="id_plan"'), 'La nota del plan va arriba.');
    assert.doesNotMatch(modal, /<select/, 'Sin listas nativas en el modal.');
    assert.match(modal, /<label class="toggle-switch"><input type="checkbox" data-suscripcion-activa/);
    assert.match(modal, /<label class="plataforma-opcion plataforma-opcion--riesgo" data-opcion-estado="baja">/);
    assert.doesNotMatch(modal, /<details/, '«Dar de baja» ya no es un aviso plegable.');
    assert.ok(modal.includes('data-motivo-sugerido="<?= $e($motivo) ?>"'), 'Sugerencias de motivo.');
    assert.match(vista, /'Pago recibido'/);
    assert.equal((modal.match(/class="users-modal__footer"/g) || []).length, 2);
    assert.doesNotMatch(vista, /\sstyle=|\son[a-z]+=/);
    const css = codigo('public/css/plataforma.css');
    assert.match(css, /\.plataforma-opcion:has\(input:checked\) \{/);
    assert.match(css, /\.plataforma-modal \.users-modal__footer \{[^}]*flex-direction: column-reverse/, 'En celular, una columna.');
});

test('M0.2.1: Soporte y Seguridad en tarjetas de Usuarios en cuadrícula, con modales fuera de las tarjetas y vacíos compactos', () => {
    const vista = codigo('views/plataforma/inicio.php');
    const soporte = vista.slice(vista.indexOf('id="panelSoporte"'), vista.indexOf('id="panelSeguridad"'));
    assert.match(soporte, /<div class="personal-grid view-grid plataforma-tarjetas">/);
    assert.match(soporte, /<article class="person-card plataforma-tarjeta">/);
    assert.ok(soporte.indexOf('</article>') < soporte.indexOf('id="casoModal'), 'El modal no queda dentro de la tarjeta animada.');
    assert.match(soporte, /class="plataforma-vacio"/);
    assert.doesNotMatch(vista, /plataforma-item|empty-state/);
    const css = codigo('public/css/plataforma.css');
    assert.match(css, /\.plataforma-vacio \{[^}]*max-width: 560px/);
    assert.match(css, /\.plataforma-vacio h3 \{[^}]*font-size: 0\.95rem/);
});
