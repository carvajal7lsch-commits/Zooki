/**
 * M2 C5b — HU-2.4 y HU-2.9 (RE-2.9.1 a RE-2.9.4, RN-211, RN-113) y el peso
 * desconocido (decisión del usuario, 2026-10-15): filas de tratamiento con el
 * buscador de fármacos, el fin desde la duración y las alertas de prescripción
 * (consulta-tratamientos.js), en el formulario compartido por el modal y Atención.
 */
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const { crearDocumento, colgar } = require('./apoyo/dom-datos.cjs');

const RAIZ = path.join(__dirname, '../..');
const leer = archivo => fs.readFileSync(path.join(RAIZ, archivo), 'utf8');
// M2 C8: los buscadores usan combobox.js (flechas, Enter y Esc), que el layout carga antes.
const ARCHIVOS = ['public/js/combobox.js', 'public/js/consulta-sintomas.js', 'public/js/consulta-diagnosticos.js', 'public/js/consulta-tratamientos.js', 'public/js/consulta-formulario.js'];
const AVISOS = {
    medicamento: 'Escribe el medicamento o elígelo del catálogo.', dosis: 'Indica la dosis.', via_administracion: 'Elige la vía de administración.',
    duracion: 'Indica la duración.', fecha_inicio: 'Indica una fecha de inicio válida.', fecha_fin: 'El fin no puede ser anterior al inicio.',
};
const AVISO_PESO = 'Indica el peso o marca «No se pudo pesar».';
const H1 = 'a'.repeat(64);
const H2 = 'b'.repeat(64);
const FUENTE = { entidad: 'ASPCA', titulo: 'Acetaminophen', url: 'https://www.aspca.org/pet-care/animal-poison-control', fundamento: 'clinico' };
const PROTEGIDA = 'Posible interacción con medicación registrada por otra clínica; concilie con el propietario antes de confirmar.';

const plano = valor => JSON.parse(JSON.stringify(valor));
const esperar = async () => { for (let i = 0; i < 4; i++) await new Promise(resolver => setImmediate(resolver)); };

class EventoFalso {
    constructor(type, opciones = {}) {
        this.type = type;
        Object.assign(this, opciones);
    }
}

class FormDataFalso {
    constructor(form) {
        this.datos = [];
        for (const n of form ? form.todos() : []) {
            if (n.name && !n.disabled && (n.type !== 'checkbox' || n.checked)) this.datos.push([n.name, n.value]);
        }
    }
    append(clave, valor) { this.datos.push([clave, valor]); }
    getAll(clave) { return this.datos.filter(([c]) => c === clave).map(([, v]) => v); }
    get(clave) { return this.getAll(clave)[0]; }
}

// La misma fila que views/partials/consulta_tratamiento_fila.php (M2 C8: en apoyo/, la comparte M2C8Rediseno).
const { plantillaFila } = require('./apoyo/consulta-fila.cjs');

/** El modal de consulta con peso, alergias, diagnóstico y tratamientos, y los cuatro archivos cargados. */
function pagina({ validar = () => ({ success: true, filas: [] }), registrar = [], farmacos = [] } = {}) {
    const documento = crearDocumento();
    documento.readyState = 'complete';
    const modal = colgar(documento.raiz, 'div', {}, { id: 'modalConsulta' });
    for (const id of ['tabMotivo', 'tabSignos', 'tabResolucion']) colgar(modal, 'button', { tab: id });
    colgar(modal, 'span', {}, { id: 'consultationPetName' });
    const form = colgar(modal, 'form', {}, { id: 'formConsulta' });
    form.reset = () => form.todos().forEach(n => {
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(n.tagName) && !['hidden', 'checkbox'].includes(n.type)) n.value = '';
        if (n.type === 'checkbox') n.checked = false;
    });
    colgar(form, 'input', {}, { id: 'consultation_id_mascota', name: 'id_mascota', type: 'hidden' });
    const motivoTab = colgar(form, 'div', {}, { id: 'tabMotivo', className: 'modal-tab-content' });
    const motivo = colgar(motivoTab, 'input', { consultaCampo: 'motivo', avisoRequerido: 'Escribe el motivo de la consulta.' }, { name: 'motivo', type: 'text', required: true });
    colgar(motivoTab, 'small', { campoAviso: 'motivo' });
    const signos = colgar(form, 'div', {}, { id: 'tabSignos', className: 'modal-tab-content' });
    const peso = colgar(signos, 'input', { consultaCampo: 'peso', avisoRequerido: AVISO_PESO, avisoRango: 'El peso debe estar entre 0.01 y 200 kg.' }, { name: 'peso', type: 'number', required: true });
    peso.setAttribute('min', '0.01');
    peso.setAttribute('max', '200');
    const sinPeso = colgar(signos, 'input', { sinPeso: 'peso' }, { name: 'peso_desconocido', type: 'checkbox', value: '1' });
    colgar(signos, 'small', { campoAviso: 'peso' });
    const plan = colgar(form, 'div', {}, { id: 'tabResolucion', className: 'modal-tab-content' });
    const alertas = colgar(plan, 'section', { alertasMedicas: '', alertasConsulta: '' }, { hidden: true });
    const diagnostico = colgar(plan, 'textarea', { consultaCampo: 'diagnostico', avisoRequerido: 'Escribe el diagnóstico.' }, { name: 'diagnostico', required: true });
    colgar(plan, 'small', { campoAviso: 'diagnostico' });
    const raiz = colgar(plan, 'div', { tratamientos: '', avisos: JSON.stringify(AVISOS) });
    const agregar = colgar(raiz, 'button', { agregarTratamiento: '' }, { type: 'button' });
    const lista = colgar(raiz, 'div', { tratamientosLista: '' }, { id: 'treatmentsList' });
    const plantilla = colgar(raiz, 'template', { tratamientoPlantilla: '' });
    plantilla.content = { firstElementChild: plantillaFila() };
    colgar(form, 'button', {}, { type: 'submit' });

    const peticiones = [];
    const llamadas = { toast: [], aviso: [], pestanas: [], cerrar: [], alertas: [], eventos: [] };
    const colaRegistrar = [...registrar];
    documento.addEventListener('zooki:consulta-registrada', evento => llamadas.eventos.push(evento.detail));
    const ventana = {
        document: documento,
        console,
        URLSearchParams,
        FormData: FormDataFalso,
        Event: EventoFalso,
        CustomEvent: EventoFalso,
        Date,
        setTimeout: fn => { fn(); return 1; },
        clearTimeout: () => {},
        zookiToast: mensaje => llamadas.toast.push(mensaje),
        zookiAviso: mensaje => llamadas.aviso.push(mensaje),
        openModal: () => {},
        closeModal: id => llamadas.cerrar.push(id),
        switchModalTab: (evento, id) => llamadas.pestanas.push(id),
        ZookiAlertas: { cargar: contenedor => llamadas.alertas.push({ id: contenedor.dataset.idMascota, nombre: contenedor.dataset.nombreMascota }) },
        fetch: async (url, opciones = {}) => {
            peticiones.push({ url, opciones });
            let respuesta = { success: true };
            if (url.includes('buscar_farmaco_ajax')) respuesta = { success: true, farmacos };
            if (url.includes('validar_prescripcion_ajax')) respuesta = validar(opciones.body);
            if (url.includes('registrar_consulta_ajax')) respuesta = colaRegistrar.shift();
            return { json: async () => respuesta };
        },
    };
    ventana.window = ventana;
    vm.createContext(ventana);
    ARCHIVOS.forEach(archivo => vm.runInContext(leer(archivo), ventana));
    return { ventana, documento, form, motivo, peso, sinPeso, diagnostico, alertas, agregar, lista, peticiones, llamadas };
}

const campo = (fila, nombre) => fila.querySelector(`[data-tratamiento-campo="${nombre}"]`);
const aviso = (fila, nombre) => fila.querySelector(`[data-tratamiento-aviso="${nombre}"]`);
const validaciones = p => p.peticiones.filter(x => x.url.includes('validar_prescripcion_ajax'));

async function escribir(nodo, valor, evento = 'input') {
    nodo.value = valor;
    nodo.disparar(evento);
    await esperar();
}

/** Una fila completa: con fármaco del catálogo si se da un código. */
async function filaCompleta(p, { nombre = 'Meloxicam', codigo = null } = {}) {
    p.agregar.click();
    const filas = p.lista.querySelectorAll('[data-tratamiento]');
    const fila = filas[filas.length - 1];
    if (codigo) {
        await escribir(campo(fila, 'medicamento'), nombre.slice(0, 4));
        fila.querySelector(`[data-farmaco="${codigo}"]`).click();
    } else {
        await escribir(campo(fila, 'medicamento'), nombre);
    }
    campo(fila, 'dosis').value = '1 tableta';
    campo(fila, 'via_administracion').value = 'Oral';
    await escribir(campo(fila, 'duracion'), '5 días');
    await esperar();
    return fila;
}

function evaluacion(extra = {}) {
    return { bloqueada: false, motivos: [], advertencias: [], evaluacion_incompleta: false, requiere_decision: false, requiere_conciliacion: false, huella: H1, ...extra };
}

async function abrir(p) {
    p.ventana.openConsultationModal(1, 'Luna');
    p.motivo.value = 'Dolor';
    p.peso.value = '8.5';
    p.diagnostico.value = 'Artrosis';
    await esperar();
}

test('el fin se calcula solo desde una duración inequívoca', () => {
    const T = pagina().ventana.ZookiTratamientos;
    assert.equal(T.finDesdeDuracion('2026-10-15', '7 días'), '2026-10-21', 'El día de inicio cuenta');
    assert.equal(T.finDesdeDuracion('2026-10-15', '7 dias'), '2026-10-21');
    assert.equal(T.finDesdeDuracion('2026-10-15', '2 semanas'), '2026-10-28');
    assert.equal(T.finDesdeDuracion('2026-10-15', 'Dosis única'), '2026-10-15');
    assert.equal(T.finDesdeDuracion('2026-10-30', '3 días'), '2026-11-01');
    for (const dudosa of ['hasta mejorar', '7 días?', '1 mes', '0 días', 'una semana', '']) {
        assert.equal(T.finDesdeDuracion('2026-10-15', dudosa), null, dudosa);
    }
    assert.equal(T.finDesdeDuracion('', '7 días'), null);
});

test('el buscador de fármacos pone el código y un nombre libre queda como evaluación incompleta', async () => {
    const p = pagina({ farmacos: [{ codigo: 'farmaco.meloxicam', etiqueta: 'Meloxicam', tipo: 'farmaco' }], validar: () => ({ success: true, filas: [evaluacion({ huella: H1 })] }) });
    await abrir(p);
    const fila = await filaCompleta(p, { codigo: 'farmaco.meloxicam' });
    assert.match(p.peticiones.find(x => x.url.includes('buscar_farmaco_ajax')).url, /buscar_farmaco_ajax&q=Melo$/);
    assert.equal(campo(fila, 'farmaco').value, 'farmaco.meloxicam');
    assert.equal(campo(fila, 'medicamento').value, 'Meloxicam');
    assert.equal(fila.querySelector('[data-farmaco-estado]').textContent, 'Del catálogo: Meloxicam');
    const cuerpo = validaciones(p).at(-1).opciones.body;
    assert.equal(cuerpo.get('tratamientos[0][farmaco]'), 'farmaco.meloxicam');
    assert.equal(cuerpo.get('id_mascota'), '1');
    assert.equal(campo(fila, 'fecha_fin').value !== '', true, '5 días desde hoy da un fin');
    assert.equal(fila.querySelector('[data-fin-ayuda]').textContent, 'Calculado desde la duración; corrígelo si hace falta.');

    // Cambiar el nombre a mano quita el código: no se cruza un nombre libre.
    await escribir(campo(fila, 'medicamento'), 'Meloxicam genérico');
    assert.equal(campo(fila, 'farmaco').value, '');
    assert.equal(fila.querySelector('[data-farmaco-estado]').textContent, 'No se pudo cruzar con el conocimiento: evaluación incompleta.');
    assert.equal(validaciones(p).at(-1).opciones.body.get('tratamientos[0][farmaco]'), '');
    assert.equal(fila.querySelector('[data-prescripcion-estado]').textContent, 'No se pudo cruzar con el conocimiento: evaluación incompleta.');
    assert.doesNotMatch(fila.textContent, /sin interacciones/i);
});

test('un bloqueo muestra la especie y la fuente con enlace, no se confirma e impide guardar esa fila', async () => {
    const bloqueo = evaluacion({ bloqueada: true, motivos: [{ tipo: 'toxicidad', mensaje: 'Toxicidad documentada para la especie o raza del paciente.', paciente: 'Felino', fuentes: [FUENTE] }] });
    const p = pagina({ farmacos: [{ codigo: 'farmaco.paracetamol', etiqueta: 'Paracetamol', tipo: 'farmaco' }], validar: () => ({ success: true, filas: [bloqueo] }) });
    await abrir(p);
    const fila = await filaCompleta(p, { nombre: 'Paracetamol', codigo: 'farmaco.paracetamol' });

    assert.ok(fila.classList.contains('consulta-tratamiento--bloqueo'));
    assert.match(fila.textContent, /Prescripción bloqueada: cambia el fármaco o quita la fila\./);
    assert.match(fila.textContent, /Paciente: Felino\./);
    const enlace = fila.todos().find(n => n.tagName === 'A');
    assert.equal(enlace.href, FUENTE.url);
    assert.equal(enlace.rel, 'noopener noreferrer');
    assert.equal(fila.querySelector('[data-prescripcion-confirmar]').hidden, true, 'Un bloqueo no se confirma');

    p.form.disparar('submit', { currentTarget: p.form });
    await esperar();
    assert.equal(p.peticiones.filter(x => x.url.includes('registrar_consulta_ajax')).length, 0);
    assert.equal(aviso(fila, 'fila').textContent, 'Esta fila está bloqueada: cambia el fármaco o quítala.');
    assert.equal(p.llamadas.pestanas.at(-1), 'tabResolucion');

    // Quitar la fila deja guardar.
    fila.querySelector('[data-quitar-tratamiento]').click();
    assert.equal(p.lista.querySelectorAll('[data-tratamiento]').length, 0);
});

test('una advertencia visible dice con qué fármaco interactúa y pide «Confirmo la prescripción»', async () => {
    const advertencia = evaluacion({ requiere_decision: true, advertencias: [{ tipo: 'interaccion', mensaje: 'Interacción con otra medicación.', farmaco: 'Prednisona', fuentes: [FUENTE] }] });
    const p = pagina({
        farmacos: [{ codigo: 'farmaco.meloxicam', etiqueta: 'Meloxicam', tipo: 'farmaco' }],
        validar: () => ({ success: true, filas: [advertencia] }),
        registrar: [{ success: true, message: 'Consulta registrada correctamente y 1 tratamiento.', consulta: { id_consulta: 3 } }],
    });
    await abrir(p);
    const fila = await filaCompleta(p, { codigo: 'farmaco.meloxicam' });

    assert.ok(fila.classList.contains('consulta-tratamiento--advertencia'));
    assert.match(fila.textContent, /Interactúa con: Prednisona\./);
    assert.equal(fila.querySelector('[data-prescripcion-confirmar]').hidden, false);
    assert.equal(fila.querySelector('[data-prescripcion-conciliacion]').hidden, true);
    assert.equal(campo(fila, 'huella').value, H1);

    p.form.disparar('submit', { currentTarget: p.form });
    await esperar();
    assert.equal(aviso(fila, 'decision').textContent, 'Confirma la prescripción o cambia el fármaco.');
    assert.equal(p.peticiones.filter(x => x.url.includes('registrar_consulta_ajax')).length, 0);

    const casilla = fila.querySelector('[data-prescripcion-casilla]');
    casilla.checked = true;
    casilla.disparar('change');
    assert.equal(campo(fila, 'decision').value, 'confirmar');
    p.form.disparar('submit', { currentTarget: p.form });
    await esperar();
    const envio = p.peticiones.filter(x => x.url.includes('registrar_consulta_ajax')).at(-1).opciones.body;
    assert.deepEqual(plano(envio.getAll('med_decision[]')), ['confirmar']);
    assert.deepEqual(plano(envio.getAll('med_huella[]')), [H1]);
    assert.deepEqual(plano(envio.getAll('med_farmaco[]')), ['farmaco.meloxicam']);
    assert.deepEqual(p.llamadas.cerrar, ['modalConsulta']);
});

test('una advertencia protegida sale tal cual, pide conciliación con contador y no deja datos de la otra clínica', async () => {
    const protegida = evaluacion({ requiere_decision: true, requiere_conciliacion: true, advertencias: [{ tipo: 'interaccion_protegida', mensaje: PROTEGIDA }] });
    const p = pagina({ farmacos: [{ codigo: 'farmaco.meloxicam', etiqueta: 'Meloxicam', tipo: 'farmaco' }], validar: () => ({ success: true, filas: [protegida] }) });
    await abrir(p);
    const fila = await filaCompleta(p, { codigo: 'farmaco.meloxicam' });

    const alertas = fila.querySelector('[data-prescripcion-lista]').textContent;
    assert.equal(alertas, PROTEGIDA, 'Sin fármaco, paciente ni fuente de lo protegido');
    assert.doesNotMatch(fila.textContent, /Interactúa con|Fuente:|Paciente:|prednisona/i);
    assert.equal(fila.querySelector('[data-prescripcion-conciliacion]').hidden, false);

    const casilla = fila.querySelector('[data-prescripcion-casilla]');
    casilla.checked = true;
    casilla.disparar('change');
    p.form.disparar('submit', { currentTarget: p.form });
    await esperar();
    assert.equal(aviso(fila, 'conciliacion').textContent, 'Documenta la conciliación con el propietario.');
    assert.equal(p.peticiones.filter(x => x.url.includes('registrar_consulta_ajax')).length, 0);

    await escribir(campo(fila, 'conciliacion'), 'Confirmado con Fabio');
    assert.equal(fila.querySelector('[data-conciliacion-contador]').textContent, '20/500');
    assert.equal(aviso(fila, 'conciliacion').textContent, '');
    assert.equal(p.ventana.ZookiConsultaForm.iniciar && p.ventana.document.getElementById('formConsulta') === p.form, true);
});

test('si la huella cambia, la fila vuelve a pedir confirmación con un aviso breve; también ante el 422 del servidor', async () => {
    let huella = H1;
    const advertencia = () => evaluacion({ requiere_decision: true, huella, advertencias: [{ tipo: 'interaccion', mensaje: 'Interacción con otra medicación.', farmaco: 'Prednisona' }] });
    const p = pagina({
        farmacos: [{ codigo: 'farmaco.meloxicam', etiqueta: 'Meloxicam', tipo: 'farmaco' }],
        validar: () => ({ success: true, filas: [advertencia()] }),
        registrar: [{ success: false, campo: 'tratamientos.0', fila: 0, message: 'Revisa las advertencias actuales y confirma esta fila con su huella vigente.', prescripcion: evaluacion({ requiere_decision: true, huella: 'c'.repeat(64), advertencias: [{ tipo: 'interaccion', mensaje: 'Interacción con otra medicación.', farmaco: 'Prednisona' }] }) }],
    });
    await abrir(p);
    const fila = await filaCompleta(p, { codigo: 'farmaco.meloxicam' });
    const casilla = fila.querySelector('[data-prescripcion-casilla]');
    casilla.checked = true;
    casilla.disparar('change');

    // Con la misma huella la confirmación se conserva.
    await escribir(campo(fila, 'fecha_fin'), campo(fila, 'fecha_fin').value, 'change');
    assert.equal(casilla.checked, true);

    huella = H2;
    await escribir(campo(fila, 'fecha_inicio'), campo(fila, 'fecha_inicio').value, 'change');
    assert.equal(casilla.checked, false);
    assert.equal(campo(fila, 'decision').value, '');
    assert.equal(campo(fila, 'huella').value, H2);
    assert.equal(aviso(fila, 'decision').textContent, 'Cambió algo en la prescripción: revísala y confirma de nuevo.');
    assert.deepEqual(p.llamadas.toast, ['Cambió algo en la prescripción: revísala y confirma de nuevo.']);

    // El servidor la rechaza con su evaluación nueva: la fila se repinta junto a su aviso, sin ventana.
    casilla.checked = true;
    casilla.disparar('change');
    p.form.disparar('submit', { currentTarget: p.form });
    await esperar();
    assert.equal(campo(fila, 'huella').value, 'c'.repeat(64));
    assert.equal(casilla.checked, false);
    assert.equal(aviso(fila, 'fila').textContent, 'Revisa las advertencias actuales y confirma esta fila con su huella vigente.');
    assert.deepEqual(p.llamadas.aviso, []);
});

test('los errores tratamientos.N.campo del servidor salen junto a su fila y su campo', async () => {
    const p = pagina({
        validar: () => ({ success: true, filas: [evaluacion({ evaluacion_incompleta: true }), evaluacion({ evaluacion_incompleta: true })] }),
        registrar: [{ success: false, campo: 'tratamientos.1.fecha_fin', aviso: AVISOS.fecha_fin, message: 'La fecha de fin del tratamiento debe ser válida y no anterior al inicio.' }],
    });
    await abrir(p);
    const primera = await filaCompleta(p, { nombre: 'Amoxicilina' });
    const segunda = await filaCompleta(p, { nombre: 'Suero oral' });
    assert.equal(validaciones(p).at(-1).opciones.body.get('tratamientos[1][farmaco]'), '', 'Se evalúan todas las filas juntas');

    p.form.disparar('submit', { currentTarget: p.form });
    await esperar();
    assert.equal(aviso(segunda, 'fecha_fin').textContent, AVISOS.fecha_fin);
    assert.ok(aviso(segunda, 'fecha_fin').classList.contains('cuenta-validacion--error'));
    assert.equal(aviso(primera, 'fecha_fin').textContent, '');
    assert.deepEqual(p.llamadas.aviso, [], 'No es un aviso global');
    assert.equal(p.llamadas.pestanas.at(-1), 'tabResolucion');

    // Y en el navegador: el fin antes del inicio se avisa al cambiarlo.
    await escribir(campo(primera, 'fecha_fin'), '2000-01-01', 'change');
    assert.equal(aviso(primera, 'fecha_fin').textContent, AVISOS.fecha_fin);
    await escribir(campo(primera, 'dosis'), '');
    assert.equal(aviso(primera, 'dosis').textContent, AVISOS.dosis);
});

test('las alergias y alertas de la mascota se cargan en el modal antes de prescribir', async () => {
    const p = pagina();
    p.ventana.openConsultationModal(7, 'Michi');
    assert.deepEqual(plano(p.llamadas.alertas), [{ id: '7', nombre: 'Michi' }]);
    p.ventana.openConsultationModal(1, 'Luna');
    assert.deepEqual(plano(p.llamadas.alertas.at(-1)), { id: '1', nombre: 'Luna' });

    const modal = leer('views/vet/modal_consulta.php');
    const plan = modal.slice(modal.indexOf('id="tabResolucion"'));
    assert.ok(plan.indexOf('data-alertas-consulta') < plan.indexOf('data-tratamientos'), 'Arriba de los tratamientos');
    const atencion = leer('views/vet/atencion.php');
    const paso = atencion.slice(atencion.indexOf('data-tratamientos data-avisos'));
    assert.ok(paso.indexOf('data-alertas-medicas data-cargar') < paso.indexOf('data-agregar-tratamiento'), 'Atención también, en el paso de tratamientos');
    assert.match(leer('views/vet/calendario.php'), /partials\/modal_alerta_medica\.php/, 'Calendario puede registrar una alergia desde el modal');
});

test('«No se pudo pesar» vacía y deshabilita el peso; sin marcarla el peso se pide', async () => {
    const p = pagina();
    await abrir(p);
    const avisoPeso = p.form.querySelector('[data-campo-aviso="peso"]');

    await escribir(p.peso, '');
    assert.equal(avisoPeso.textContent, AVISO_PESO);
    assert.equal(p.ventana.ZookiConsultaForm.validarTodo(p.form), p.peso);

    p.peso.value = '8';
    p.sinPeso.checked = true;
    p.sinPeso.disparar('change');
    assert.equal(p.peso.value, '');
    assert.equal(p.peso.disabled, true);
    assert.equal(p.peso.required, false);
    assert.equal(avisoPeso.textContent, '');
    assert.equal(p.ventana.ZookiConsultaForm.validarTodo(p.form), null);
    const datos = new FormDataFalso(p.form);
    assert.equal(datos.get('peso'), undefined, 'El peso no viaja');
    assert.equal(datos.get('peso_desconocido'), '1');

    p.sinPeso.checked = false;
    p.sinPeso.disparar('change');
    assert.equal(p.peso.disabled, false);
    // M2 C8 (H5): «Obligatorio.» ya no queda suelto; el aviso sale solo con un error (el asterisco marca el obligatorio).
    assert.equal(avisoPeso.textContent, '');
    assert.equal(p.peso.required, true);

    // Al abrir otra consulta la casilla vuelve a su estado y el peso se habilita.
    p.sinPeso.checked = true;
    p.sinPeso.disparar('change');
    p.ventana.openConsultationModal(2, 'Kira');
    assert.equal(p.sinPeso.checked, false);
    assert.equal(p.peso.disabled, false);
});

test('el historial dice «Sin peso registrado» (C7) y nunca muestra el peso NULL como 0', () => {
    const contexto = { console, window: {}, document: { addEventListener() {}, getElementById: () => null } };
    vm.createContext(contexto);
    vm.runInContext(leer('public/js/medical-module.js'), contexto);
    assert.equal(contexto.textoPeso(null), 'Sin peso registrado');
    assert.equal(contexto.textoPeso(''), 'Sin peso registrado');
    assert.equal(contexto.textoPeso('8.50'), '8.50 Kg');
    const medical = leer('public/js/medical-module.js');
    assert.doesNotMatch(medical, /c\.peso \|\| '--'/);
    assert.equal((medical.match(/textoPeso\(c\.peso\)/g) || []).length, 3, 'Cajón, ficha e impresión del veterinario');
    const atencion = leer('views/vet/atencion.php');
    assert.match(atencion, /\$textoPeso = fn\(\$peso\): string => \$peso === null \|\| \$peso === '' \? 'Sin peso registrado'/);
    assert.match(atencion, /\$textoPeso\(\$con\['peso'\] \?\? null\)/);
});

test('las dos entradas usan la misma fila y el mismo componente, sin código en línea', () => {
    const modal = leer('views/vet/modal_consulta.php');
    const atencion = leer('views/vet/atencion.php');
    for (const vista of [modal, atencion]) {
        assert.match(vista, /partials\/consulta_tratamiento_fila\.php/);
        assert.match(vista, /data-tratamientos data-avisos=/);
        assert.match(vista, /data-tratamientos-lista/);
        assert.match(vista, /data-agregar-tratamiento/);
        assert.match(vista, /name="peso_desconocido" value="1" data-sin-peso="peso"/);
        assert.match(vista, /No se pudo pesar/);
        assert.match(vista, /AVISO_PESO_REQUERIDO/);
    }
    assert.doesNotMatch(modal, /\son[a-z]+=|style=/);

    const fila = leer('views/partials/consulta_tratamiento_fila.php');
    assert.doesNotMatch(fila, /\son[a-z]+=|style=|<script/);
    for (const gancho of ['data-tratamiento>', 'data-tratamiento-campo="medicamento"', 'data-farmaco-sugerencias', 'data-tratamiento-campo="farmaco"', 'data-farmaco-estado',
        'data-tratamiento-campo="dosis"', 'data-tratamiento-campo="via_administracion"', 'data-tratamiento-campo="duracion"', 'data-tratamiento-campo="fecha_inicio"',
        'data-tratamiento-campo="fecha_fin"', 'data-fin-ayuda', 'data-tratamiento-campo="observaciones"', 'data-quitar-tratamiento', 'data-prescripcion-estado',
        'data-prescripcion-lista', 'data-prescripcion-confirmar', 'data-prescripcion-casilla', 'data-prescripcion-conciliacion', 'data-tratamiento-campo="conciliacion"',
        'data-conciliacion-contador', 'data-tratamiento-campo="decision"', 'data-tratamiento-campo="huella"', 'data-tratamiento-aviso="fila"']) {
        assert.ok(fila.includes(gancho), gancho);
    }
    for (const limite of ['ValidadorTratamiento::MEDICAMENTO_MAX', 'ValidadorTratamiento::DOSIS_MAX', 'ValidadorTratamiento::DURACION_MAX', 'ValidadorTratamiento::OBSERVACIONES_MAX', 'ValidadorPrescripcion::CONCILIACION_MAX', 'ValidadorTratamiento::VIAS']) {
        assert.ok(fila.includes(limite), limite);
    }
    for (const nombre of ['med_nombre[]', 'med_farmaco[]', 'med_dosis[]', 'med_via[]', 'med_duracion[]', 'med_inicio[]', 'med_fin[]', 'med_obs[]', 'med_decision[]', 'med_conciliacion[]', 'med_huella[]']) {
        assert.ok(fila.includes(`name="${nombre}"`), nombre);
    }

    const layout = leer('views/vet/layout.php');
    const orden = ['js/consulta-diagnosticos.js', 'js/consulta-tratamientos.js', 'js/consulta-formulario.js', 'js/atencion.js'].map(archivo => layout.indexOf(archivo));
    assert.deepEqual([...orden].sort((a, b) => a - b), orden);
    const atencionJs = leer('public/js/atencion.js');
    assert.match(atencionJs, /await consulta\.listo\(\)/);
    assert.doesNotMatch(atencionJs, /addTreatmentRow|MutationObserver/);
    assert.doesNotMatch(leer('public/js/medical-module.js'), /function (addTreatmentRow|fechaDeHoy)\b/);
    assert.doesNotMatch(leer('public/js/interacciones.js'), /agregar-tratamiento/);
    assert.match(leer('public/js/consulta-formulario.js'), /await consulta\.listo\(\);\r?\n\s+const invalido = consulta\.validar\(\);/);
    assert.doesNotMatch(leer('public/js/consulta-tratamientos.js'), /style=|innerHTML\s*=|\.style\./);
});
