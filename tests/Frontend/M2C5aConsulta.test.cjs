/**
 * M2 C5a — HU-2.8 (RE-2.8.1 a RE-2.8.4), RN-210, RN-202 y A.1: la consulta con
 * síntomas marcados, diagnósticos sugeridos y obligatorios avisados junto al
 * campo (consulta-sintomas.js, consulta-diagnosticos.js, consulta-formulario.js),
 * compartida por el modal de consulta y Atención.
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
const ARCHIVOS = ['public/js/combobox.js', 'public/js/consulta-sintomas.js', 'public/js/consulta-diagnosticos.js', 'public/js/consulta-formulario.js'];
const AVISO_MOTIVO = 'Escribe el motivo de la consulta.';
const AVISO_DIAGNOSTICO = 'Escribe el diagnóstico: es obligatorio para guardar.';

const SUGERENCIAS = [
    { codigo: 'sintoma.vomito', etiqueta: 'Vómito', coincidencia: 'exacta', similitud: 1 },
    { codigo: 'sintoma.vomito_sangre', etiqueta: 'Vómito con sangre', coincidencia: 'prefijo', similitud: 1 },
    { codigo: 'sintoma.otro_no_se', etiqueta: 'Otro / no sé', coincidencia: 'reserva', similitud: 0 },
];

function candidato(codigo, etiqueta, extra = {}) {
    return {
        codigo, etiqueta, puntuacion: 2.2,
        cobertura: { coinciden: 1, caracteristicos: 6, texto: 'Coincide 1 de 6 síntomas característicos' },
        sintomas_coincidentes: ['sintoma.vomito'],
        fuentes: [
            { codigo: 'zooki.casos_triage', entidad: 'Zooki', titulo: 'Batería', url: 'https://github.com/x', fundamento: 'politica' },
            { codigo: 'msd.parvo', entidad: 'MSD Veterinary Manual', titulo: 'Canine Parvovirus', url: 'https://www.msdvetmanual.com/parvo', fundamento: 'clinico' },
        ],
        estado_validacion: 'pendiente', ...extra,
    };
}

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
            if (n.name) this.datos.push([n.name, n.value]);
        }
    }
    append(clave, valor) { this.datos.push([clave, valor]); }
    getAll(clave) { return this.datos.filter(([c]) => c === clave).map(([, v]) => v); }
    get(clave) { return this.getAll(clave)[0]; }
}

/** El bloque de la consulta: motivo, síntomas, temperatura, diagnósticos sugeridos y diagnóstico. */
function armarConsulta(form) {
    const pestanaMotivo = colgar(form, 'div', {}, { id: 'tabMotivo', className: 'modal-tab-content' });
    const motivo = colgar(pestanaMotivo, 'input', { consultaCampo: 'motivo', avisoRequerido: AVISO_MOTIVO }, { name: 'motivo', type: 'text', required: true });
    motivo.setAttribute('maxlength', '5000');
    colgar(pestanaMotivo, 'small', { campoAviso: 'motivo' }, { className: 'cuenta-validacion' });
    const sintomas = colgar(pestanaMotivo, 'div', { sintomas: '', max: '30' });
    colgar(sintomas, 'div', { sintomasChips: '' }, { hidden: true });
    const buscador = colgar(sintomas, 'input', { sintomasBuscar: '' }, { type: 'text' });
    colgar(sintomas, 'ul', { sintomasSugerencias: '' }, { hidden: true });
    colgar(sintomas, 'small', { campoAviso: 'sintomas' });

    const pestanaSignos = colgar(form, 'div', {}, { id: 'tabSignos', className: 'modal-tab-content' });
    const columna = colgar(pestanaSignos, 'div');
    const temperatura = colgar(columna, 'input', { consultaCampo: 'temperatura', avisoRango: 'La temperatura debe estar entre 25 y 45 °C.' }, { name: 'temperatura', type: 'number' });
    temperatura.setAttribute('min', '25');
    temperatura.setAttribute('max', '45');

    const pestanaPlan = colgar(form, 'div', {}, { id: 'tabResolucion', className: 'modal-tab-content' });
    const panel = colgar(pestanaPlan, 'section', { diagnosticos: '' });
    colgar(panel, 'p', { diagnosticosEstado: '' });
    colgar(panel, 'ol', { diagnosticosLista: '' }, { hidden: true });
    const datos = colgar(panel, 'div', { diagnosticosDatos: '' }, { hidden: true });
    colgar(datos, 'ul', { diagnosticosFicha: '' }, { hidden: true });
    const envolturaVacunacion = colgar(datos, 'label', { dato: 'vacunacion' }, { hidden: true });
    const vacunacion = colgar(envolturaVacunacion, 'select', { datoCampo: 'vacunacion' });
    const envolturaCelo = colgar(datos, 'label', { dato: 'dias_desde_celo' }, { hidden: true });
    colgar(envolturaCelo, 'input', { datoCampo: 'dias_desde_celo' }, { type: 'text' });
    const diagnostico = colgar(pestanaPlan, 'textarea', { consultaCampo: 'diagnostico', avisoRequerido: AVISO_DIAGNOSTICO }, { name: 'diagnostico', required: true });
    diagnostico.setAttribute('maxlength', '5000');
    colgar(pestanaPlan, 'small', { campoAviso: 'diagnostico' }, { className: 'cuenta-validacion' });
    return { motivo, buscador, sintomas, temperatura, panel, datos, envolturaVacunacion, envolturaCelo, vacunacion, diagnostico };
}

/** Página con el modal de consulta y los tres archivos cargados. */
function pagina({ registrar = [], sugerir = [] } = {}) {
    const documento = crearDocumento();
    documento.readyState = 'complete';
    const modal = colgar(documento.raiz, 'div', {}, { id: 'modalConsulta' });
    for (const id of ['tabMotivo', 'tabSignos', 'tabResolucion']) colgar(modal, 'button', { tab: id });
    colgar(modal, 'span', {}, { id: 'consultationPetName' });
    const form = colgar(modal, 'form', {}, { id: 'formConsulta' });
    form.reset = () => form.todos().forEach(n => { if (n.tagName === 'INPUT' || n.tagName === 'TEXTAREA' || n.tagName === 'SELECT') n.value = ''; });
    colgar(form, 'input', {}, { id: 'consultation_id_mascota', name: 'id_mascota', type: 'hidden' });
    const campos = armarConsulta(form);
    colgar(form, 'div', {}, { id: 'treatmentsList' });
    colgar(form, 'button', {}, { type: 'submit' });

    const peticiones = [];
    const llamadas = { toast: [], aviso: [], abrir: [], cerrar: [], pestanas: [], eventos: [] };
    const colaRegistrar = [...registrar];
    const colaSugerir = [...sugerir];
    documento.addEventListener('zooki:consulta-registrada', evento => llamadas.eventos.push(evento.detail));
    const ventana = {
        document: documento,
        console,
        URLSearchParams,
        FormData: FormDataFalso,
        Event: EventoFalso,
        CustomEvent: EventoFalso,
        setTimeout: fn => { fn(); return 1; },
        clearTimeout: () => {},
        zookiToast: mensaje => llamadas.toast.push(mensaje),
        zookiAviso: mensaje => llamadas.aviso.push(mensaje),
        openModal: id => llamadas.abrir.push(id),
        closeModal: id => llamadas.cerrar.push(id),
        switchModalTab: (evento, id) => llamadas.pestanas.push(id),
        fetch: async (url, opciones = {}) => {
            peticiones.push({ url, opciones });
            let respuesta = { success: true, sugerencias: SUGERENCIAS };
            if (url.includes('sugerir_diagnosticos_ajax')) respuesta = colaSugerir.shift() || { success: true, candidatos: [], datos_faltantes: [] };
            if (url.includes('registrar_consulta_ajax')) respuesta = colaRegistrar.shift();
            return { json: async () => respuesta };
        },
    };
    ventana.window = ventana;
    vm.createContext(ventana);
    ARCHIVOS.forEach(archivo => vm.runInContext(leer(archivo), ventana));
    return { ventana, documento, form, campos, peticiones, llamadas };
}

const esperar = () => new Promise(resolver => setImmediate(resolver));
// Los objetos creados dentro de vm tienen otro prototipo: se comparan por su contenido.
const plano = valor => JSON.parse(JSON.stringify(valor));

async function escribir(campo, valor) {
    campo.value = valor;
    campo.disparar('input');
    await esperar();
    await esperar();
}

async function marcar(p, texto, codigo) {
    await escribir(p.campos.buscador, texto);
    const opcion = p.campos.sintomas.querySelector(`[data-codigo="${codigo}"]`);
    assert.ok(opcion, `Debió sugerir ${codigo}`);
    opcion.click();
    await esperar();
    await esperar();
}

const ocultos = p => p.campos.sintomas.querySelectorAll('[data-sintoma]').map(chip => chip.todos().find(n => n.name === 'sintomas[]').value);

test('el buscador sugiere sin marcar nada solo; marcar da un chip con su campo oculto y la × lo quita', async () => {
    const p = pagina();
    p.ventana.openConsultationModal(1, 'Luna');

    await escribir(p.campos.buscador, 'v');
    assert.equal(p.peticiones.length, 0, 'Con una letra no se busca');

    await escribir(p.campos.buscador, 'vo');
    assert.match(p.peticiones[0].url, /buscar_sintomas_ajax&q=vo&id_mascota=1$/);
    const opciones = p.campos.sintomas.querySelectorAll('[data-codigo]').map(b => b.dataset.codigo);
    assert.deepEqual(opciones, ['sintoma.vomito', 'sintoma.vomito_sangre'], '«Otro / no sé» no se ofrece en la consulta');
    assert.equal(ocultos(p).length, 0, 'Sugerir no marca');

    const enter = p.campos.buscador.disparar('keydown', { key: 'Enter' });
    assert.ok(enter.prevenido, 'Enter no envía la consulta');
    assert.equal(ocultos(p).length, 0, 'Enter tampoco marca');

    p.campos.sintomas.querySelector('[data-codigo="sintoma.vomito"]').click();
    await esperar();
    assert.deepEqual(ocultos(p), ['sintoma.vomito']);
    assert.equal(p.campos.buscador.value, '');
    assert.equal(p.campos.sintomas.querySelector('[data-sintomas-sugerencias]').hidden, true);

    await escribir(p.campos.buscador, 'vo');
    const yaMarcado = p.campos.sintomas.querySelectorAll('[data-codigo]').map(b => b.dataset.codigo);
    assert.deepEqual(yaMarcado, ['sintoma.vomito_sangre'], 'Lo ya marcado no se vuelve a ofrecer');

    const chip = p.campos.sintomas.querySelector('[data-sintoma="sintoma.vomito"]');
    assert.equal(chip.querySelector('.consulta-sintoma__quitar').getAttribute('aria-label'), 'Quitar Vómito');
    chip.querySelector('.consulta-sintoma__quitar').click();
    await esperar();
    assert.deepEqual(ocultos(p), []);
});

test('el ranking se recalcula al cambiar los síntomas, con hasta 5 candidatos, cobertura, coincidencias y fuente, sin porcentajes', async () => {
    const seis = ['a', 'b', 'c', 'd', 'e', 'f'].map(letra => candidato(`diagnostico.${letra}`, `Diagnóstico ${letra}`));
    const p = pagina({ sugerir: [{ success: true, candidatos: [candidato('diagnostico.gastritis', 'Gastritis')], datos_faltantes: [] }, { success: true, candidatos: seis, datos_faltantes: [] }] });
    p.ventana.openConsultationModal(1, 'Luna');
    const estado = p.campos.panel.querySelector('[data-diagnosticos-estado]');
    assert.equal(estado.textContent, p.ventana.ZookiDiagnosticos.TEXTOS.sinSintomas);

    await marcar(p, 'vo', 'sintoma.vomito');
    const primera = p.peticiones.filter(x => x.url.includes('sugerir_diagnosticos_ajax'));
    assert.equal(primera.length, 1);
    assert.equal(primera[0].opciones.method, 'POST');
    assert.deepEqual(primera[0].opciones.body.getAll('sintomas[]'), ['sintoma.vomito']);
    assert.equal(primera[0].opciones.body.get('id_mascota'), '1');

    const lista = p.campos.panel.querySelector('[data-diagnosticos-lista]');
    assert.equal(lista.hidden, false);
    const tarjeta = lista.querySelector('[data-diagnostico="diagnostico.gastritis"]');
    assert.match(tarjeta.textContent, /Gastritis/);
    assert.match(tarjeta.textContent, /Coincide 1 de 6 síntomas característicos/);
    assert.match(tarjeta.textContent, /Coinciden: Vómito/);
    assert.match(tarjeta.textContent, /MSD Veterinary Manual — Canine Parvovirus \(\+1\)/, 'La fuente clínica primero');
    assert.match(tarjeta.textContent, /Sugerencia de apoyo · pendiente de validación veterinaria/);
    assert.equal(tarjeta.todos().find(n => n.tagName === 'A').href, 'https://www.msdvetmanual.com/parvo');
    assert.doesNotMatch(tarjeta.textContent, /%|2\.2/, 'Sin porcentajes ni puntuación');
    assert.equal(p.campos.diagnostico.value, '', 'Nunca se rellena solo');

    await marcar(p, 'vo', 'sintoma.vomito_sangre');
    const segunda = p.peticiones.filter(x => x.url.includes('sugerir_diagnosticos_ajax'));
    assert.equal(segunda.length, 2);
    assert.deepEqual(segunda[1].opciones.body.getAll('sintomas[]'), ['sintoma.vomito', 'sintoma.vomito_sangre']);
    assert.equal(lista.querySelectorAll('[data-diagnostico]').length, 5, 'Máximo 5');

    // Quitar uno recalcula con el que queda; quitar el último ya no pide ranking.
    p.campos.sintomas.querySelector('[data-sintoma="sintoma.vomito"]').querySelector('.consulta-sintoma__quitar').click();
    await esperar();
    const tercera = p.peticiones.filter(x => x.url.includes('sugerir_diagnosticos_ajax'));
    assert.equal(tercera.length, 3);
    assert.deepEqual(tercera[2].opciones.body.getAll('sintomas[]'), ['sintoma.vomito_sangre']);
    p.campos.sintomas.querySelector('[data-sintoma="sintoma.vomito_sangre"]').querySelector('.consulta-sintoma__quitar').click();
    await esperar();
    assert.equal(p.peticiones.filter(x => x.url.includes('sugerir_diagnosticos_ajax')).length, 3, 'Sin síntomas no se pide ranking');
    assert.equal(lista.hidden, true);
    assert.equal(estado.textContent, p.ventana.ZookiDiagnosticos.TEXTOS.sinSintomas);
});

test('«Usar este diagnóstico» copia el texto al campo y deja editarlo, sin duplicar ni borrar lo escrito', async () => {
    const p = pagina({ sugerir: [{ success: true, candidatos: [candidato('diagnostico.gastritis', 'Gastritis'), candidato('diagnostico.parvo', 'Parvovirosis canina')], datos_faltantes: [] }] });
    p.ventana.openConsultationModal(1, 'Luna');
    await marcar(p, 'vo', 'sintoma.vomito');
    const aviso = p.form.querySelector('[data-campo-aviso="diagnostico"]');

    p.campos.panel.querySelector('[data-usar-diagnostico="diagnostico.gastritis"]').click();
    assert.equal(p.campos.diagnostico.value, 'Gastritis');
    assert.ok(p.campos.diagnostico.enfocado);
    // M2 C8 (H5): «Obligatorio.» ya no queda suelto; el aviso sale solo con un error (el asterisco marca el obligatorio).
    assert.equal(aviso.textContent, '');
    assert.equal(aviso.classList.contains('cuenta-validacion--error'), false);

    p.campos.diagnostico.value = 'Gastritis aguda por dieta';
    p.campos.panel.querySelector('[data-usar-diagnostico="diagnostico.parvo"]').click();
    assert.equal(p.campos.diagnostico.value, 'Gastritis aguda por dieta\nParvovirosis canina');
    p.campos.panel.querySelector('[data-usar-diagnostico="diagnostico.parvo"]').click();
    assert.equal(p.campos.diagnostico.value, 'Gastritis aguda por dieta\nParvovirosis canina', 'No se repite');
});

test('sin candidatos dice que se escriba el diagnóstico y el campo sigue obligatorio (RE-2.8.3, RN-202)', async () => {
    const p = pagina({ sugerir: [{ success: true, candidatos: [], datos_faltantes: [], message: 'No hay coincidencias.' }] });
    p.ventana.openConsultationModal(1, 'Luna');
    await marcar(p, 'vo', 'sintoma.vomito');

    assert.equal(p.campos.panel.querySelector('[data-diagnosticos-estado]').textContent, 'Sin coincidencias en el conocimiento: escribe el diagnóstico.');
    assert.equal(p.campos.panel.querySelector('[data-diagnosticos-lista]').hidden, true);
    p.campos.motivo.value = 'Vomita';
    assert.equal(p.ventana.ZookiConsultaForm.validarTodo(p.form), p.campos.diagnostico);
    assert.equal(p.form.querySelector('[data-campo-aviso="diagnostico"]').textContent, AVISO_DIAGNOSTICO);
});

test('los datos que faltan salen con nombres legibles, nunca códigos, y sus campos opcionales recalculan', async () => {
    const faltantes = [
        { elemento: 'ranking.parvovirosis_contexto', campos: ['vacunacion'] },
        { elemento: 'arista.raza_gigante', campos: ['edad_meses', 'raza.atributos.tamano', 'sintomas.sintoma.vomito.minutos', 'codigo.desconocido', 'vacunacion'] },
    ];
    const D = pagina().ventana.ZookiDiagnosticos;
    assert.deepEqual(plano(D.datosLegibles(faltantes).map(d => d.clave)), ['vacunacion', 'edad_meses', 'raza', 'inicio']);

    const p = pagina({ sugerir: [{ success: true, candidatos: [candidato('diagnostico.parvo', 'Parvovirosis canina')], datos_faltantes: faltantes }, { success: true, candidatos: [candidato('diagnostico.parvo', 'Parvovirosis canina')], datos_faltantes: [] }] });
    p.ventana.openConsultationModal(1, 'Luna');
    await marcar(p, 'vo', 'sintoma.vomito');

    assert.equal(p.campos.datos.hidden, false);
    assert.equal(p.campos.envolturaVacunacion.hidden, false);
    assert.equal(p.campos.envolturaCelo.hidden, true, 'Solo lo que pidió el motor');
    const texto = p.campos.datos.textContent;
    assert.match(texto, /Edad \(falta la fecha de nacimiento en la ficha\)/);
    assert.match(texto, /Datos de la raza/);
    assert.match(texto, /Desde cuándo empezaron los síntomas/);
    assert.doesNotMatch(p.campos.panel.textContent, /ranking\.|arista\.|raza\.atributos|sintoma\.vomito|codigo\.desconocido/);

    p.campos.vacunacion.value = 'incompleta';
    p.campos.vacunacion.disparar('change');
    await esperar();
    await esperar();
    const ultima = p.peticiones.filter(x => x.url.includes('sugerir_diagnosticos_ajax')).pop();
    assert.equal(ultima.opciones.body.get('vacunacion'), 'incompleta');
    assert.equal(ultima.opciones.body.get('dias_desde_celo'), null, 'Lo vacío no se envía');
    assert.equal(p.campos.envolturaVacunacion.hidden, false, 'Con un valor declarado el campo sigue a la vista');
    assert.equal(p.form.todos().filter(n => n.name === 'vacunacion').length, 0, 'No viaja con la consulta');
});

test('motivo y diagnóstico se ven obligatorios desde que se abre y el aviso va junto al campo mientras se escribe', async () => {
    const p = pagina();
    p.ventana.openConsultationModal(1, 'Luna');
    const avisoMotivo = p.form.querySelector('[data-campo-aviso="motivo"]');
    // M2 C8 (H5): «Obligatorio.» ya no queda suelto; el aviso sale solo con un error (el asterisco marca el obligatorio).
    assert.equal(avisoMotivo.textContent, '');
    assert.equal(p.campos.motivo.required, true, 'Sigue siendo obligatorio');
    assert.equal(avisoMotivo.classList.contains('cuenta-validacion--error'), false);

    await escribir(p.campos.motivo, '   ');
    assert.equal(avisoMotivo.textContent, AVISO_MOTIVO);
    assert.ok(avisoMotivo.classList.contains('cuenta-validacion--error'));
    assert.equal(p.campos.motivo.getAttribute('aria-invalid'), 'true');

    await escribir(p.campos.motivo, 'Vomita desde ayer');
    assert.equal(avisoMotivo.textContent, '');
    assert.equal(avisoMotivo.classList.contains('cuenta-validacion--error'), false);

    // Un signo fuera de rango crea su aviso junto al campo.
    await escribir(p.campos.temperatura, '80');
    const avisoTemperatura = p.form.querySelector('[data-campo-aviso="temperatura"]');
    assert.equal(avisoTemperatura.textContent, 'La temperatura debe estar entre 25 y 45 °C.');
    await escribir(p.campos.temperatura, '38.5');
    assert.equal(avisoTemperatura.textContent, '');

    // Al guardar sin motivo no se envía nada: el aviso queda en el campo y se abre su pestaña, sin ventana.
    await escribir(p.campos.motivo, '');
    p.llamadas.pestanas.length = 0;
    p.form.disparar('submit', { currentTarget: p.form });
    await esperar();
    assert.equal(p.peticiones.filter(x => x.url.includes('registrar_consulta_ajax')).length, 0);
    assert.deepEqual(p.llamadas.aviso, []);
    assert.deepEqual(p.llamadas.pestanas, ['tabMotivo']);
    assert.ok(p.campos.motivo.enfocado);
});

test('los errores del servidor salen junto a su campo y, al guardar, la pantalla se pone al día sin recargar', async () => {
    const p = pagina({
        registrar: [
            { success: false, campo: 'diagnostico', aviso: AVISO_DIAGNOSTICO, message: 'El diagnóstico es obligatorio.' },
            { success: false, campo: 'sintomas', aviso: 'Hay un síntoma que ya no está en el catálogo. Quítalo y búscalo de nuevo.', message: 'Síntoma no válido.' },
            { success: true, message: 'Consulta registrada correctamente.', id_consulta: 9, consulta: { id_consulta: 9, nombre_mascota: 'Luna' } },
        ],
    });
    p.ventana.openConsultationModal(1, 'Luna');
    assert.deepEqual(p.llamadas.abrir, ['modalConsulta']);
    p.campos.motivo.value = 'Vomita';
    p.campos.diagnostico.value = 'Gastritis';
    await marcar(p, 'vo', 'sintoma.vomito');

    p.form.disparar('submit', { currentTarget: p.form });
    await esperar();
    await esperar();
    assert.equal(p.form.querySelector('[data-campo-aviso="diagnostico"]').textContent, AVISO_DIAGNOSTICO);
    assert.equal(p.llamadas.pestanas.at(-1), 'tabResolucion');
    assert.deepEqual(p.llamadas.aviso, [], 'Sin ventana para el error de un campo');

    p.form.disparar('submit', { currentTarget: p.form });
    await esperar();
    await esperar();
    assert.match(p.campos.sintomas.querySelector('[data-campo-aviso="sintomas"]').textContent, /ya no está en el catálogo/);
    assert.equal(p.llamadas.pestanas.at(-1), 'tabMotivo');

    p.form.disparar('submit', { currentTarget: p.form });
    await esperar();
    await esperar();
    const envio = p.peticiones.filter(x => x.url.includes('registrar_consulta_ajax')).pop();
    assert.deepEqual(envio.opciones.body.getAll('sintomas[]'), ['sintoma.vomito']);
    assert.deepEqual(p.llamadas.cerrar, ['modalConsulta']);
    assert.deepEqual(plano(p.llamadas.eventos), [{ idMascota: '1', consulta: { id_consulta: 9, nombre_mascota: 'Luna' } }]);
    assert.deepEqual(p.llamadas.toast, ['Consulta registrada correctamente.']);

    // Al abrir otra consulta no quedan síntomas, sugerencias ni avisos de la anterior.
    p.ventana.openConsultationModal(2, 'Kira');
    assert.deepEqual(ocultos(p), []);
    assert.equal(p.form.querySelector('[data-campo-aviso="diagnostico"]').textContent, '', 'Sin avisos de la anterior (C8: ni «Obligatorio.» suelto)');
    assert.equal(p.documento.getElementById('consultation_id_mascota').value, 2);
});

test('el borrador de Atención restaura los síntomas con poner(), respetando el tope', async () => {
    const p = pagina();
    p.ventana.openConsultationModal(1, 'Luna');
    const consulta = p.ventana.ZookiConsultaForm.iniciar(p.form, { idMascota: () => '1' });
    consulta.sintomas.poner([{ codigo: 'sintoma.vomito', etiqueta: 'Vómito' }, { codigo: '', etiqueta: 'sin código' }]);
    assert.deepEqual(plano(consulta.sintomas.marcados()), [{ codigo: 'sintoma.vomito', etiqueta: 'Vómito' }]);
});

test('el historial muestra los síntomas marcados de cada consulta, escapados, y nada si no hay', () => {
    const contexto = { console, window: {}, document: { addEventListener() {}, getElementById: () => null } };
    vm.createContext(contexto);
    vm.runInContext(leer('public/js/fechas.js') + '\n' + leer('public/js/medical-module.js'), contexto);

    assert.equal(contexto.seccionSintomas({}), '');
    assert.equal(contexto.seccionSintomas({ sintomas: [] }), '');
    const html = contexto.seccionSintomas({ sintomas: [{ codigo: 'sintoma.vomito', etiqueta: 'Vómito' }, { codigo: 'x', etiqueta: '<b>Tos</b>' }] });
    assert.match(html, /Síntomas marcados/);
    assert.match(html, /Vómito, &lt;b&gt;Tos&lt;\/b&gt;/, 'Pueden venir de otra clínica autorizada: se escapan');
    const medical = leer('public/js/medical-module.js');
    assert.match(contexto.filaHistorialCompacto({ motivo_consulta: 'Control', sintomas: [{ etiqueta: '<b>Tos</b>' }] }, 'consulta'), /Síntomas marcados[\s\S]*&lt;b&gt;Tos&lt;\/b&gt;/);
    assert.equal((medical.match(/filaHistorialCompacto\(c, 'consulta'\)/g) || []).length, 1, 'El cajón usa el renderer común');
    assert.match(medical, /filaHistorialCompacto\(evento.registro, evento.tipo\)/, 'La ficha usa el mismo renderer');
    assert.match(leer('views/vet/atencion.php'), /\$con\['sintomas'\]/, 'Historial de Atención');

    // Tras guardar solo se repinta el historial si su cajón está a la vista (Pacientes también lo cierra con closeDrawer).
    const cajon = { classList: new Set(), style: {} };
    cajon.classList.contains = clase => cajon.classList.has(clase);
    contexto.document.getElementById = id => (id === 'drawerHistorial' ? cajon : null);
    assert.equal(contexto.historialEstaAbierto(), false);
    cajon.classList.add('is-open');
    assert.equal(contexto.historialEstaAbierto(), true);
    const deConsultas = { style: { transform: 'translateX(100%)' } };
    contexto.document.getElementById = id => (id === 'historialDrawer' ? deConsultas : null);
    assert.equal(contexto.historialEstaAbierto(), false);
    deConsultas.style.transform = 'translateX(0)';
    assert.equal(contexto.historialEstaAbierto(), true);
});

test('las dos entradas de consulta usan los mismos componentes, sin eventos ni estilos en línea', () => {
    const modal = leer('views/vet/modal_consulta.php');
    const atencion = leer('views/vet/atencion.php');
    for (const vista of [modal, atencion]) {
        assert.match(vista, /partials\/consulta_sintomas\.php/);
        assert.match(vista, /partials\/consulta_diagnosticos\.php/);
        assert.match(vista, /data-consulta-campo="motivo"[^>]*data-aviso-requerido/);
        assert.match(vista, /data-consulta-campo="diagnostico"[^>]*data-aviso-requerido/);
        // M2 C8 (H5): el obligatorio lo marca el asterisco; el aviso empieza vacío.
        assert.match(vista, /data-campo-aviso="motivo"><\/small>/);
        assert.match(vista, /data-campo-aviso="diagnostico"><\/small>/);
        assert.match(vista, /consulta-obligatorio/);
    }
    assert.doesNotMatch(modal, /\son[a-z]+=|style=/, 'El modal sin onsubmit/onchange ni style=');
    assert.match(modal, /<form id="formConsulta"[^>]*novalidate/);

    const parciales = leer('views/partials/consulta_sintomas.php') + leer('views/partials/consulta_diagnosticos.php');
    assert.doesNotMatch(parciales, /\son[a-z]+=|style=|<script/);
    assert.match(parciales, /Sugerencia de apoyo · pendiente de validación veterinaria/);

    const layout = leer('views/vet/layout.php');
    const orden = ['js/medical-module.js', 'js/consulta-sintomas.js', 'js/consulta-diagnosticos.js', 'js/consulta-formulario.js', 'js/atencion.js'].map(archivo => layout.indexOf(archivo));
    assert.ok(orden.every(posicion => posicion > 0));
    assert.deepEqual([...orden].sort((a, b) => a - b), orden, 'Los componentes cargan antes de Atención');
    assert.match(layout, /css\/consulta-clinica\.css/);

    const atencionJs = leer('public/js/atencion.js');
    assert.match(atencionJs, /ZookiConsultaForm\.iniciar\(formConsulta/);
    assert.match(atencionJs, /consulta\.mostrarErrorServidor\(res\)/, 'Atención también pone el error del servidor junto al campo');
    // Sin recargar: Pacientes (ficha e historial) escucha la consulta guardada. La lista de
    // Consultas se retiró por decisión del dueño (2026-10-22).
    assert.doesNotMatch(leer('public/js/consulta-formulario.js'), /location\.reload|zookiRecargarConAviso/);
    assert.ok(!fs.existsSync(path.join(RAIZ, 'public/js/consultas.js')), 'Consultas médicas se retiró');
    const medical = leer('public/js/medical-module.js');
    assert.match(medical, /addEventListener\('zooki:consulta-registrada'/);
    assert.doesNotMatch(medical, /function (openConsultationModal|saveConsultation|updateFileList|avisarAdjuntosRechazados)\b/);
    assert.doesNotMatch(medical, /empty-table" style=/);
    for (const archivo of ARCHIVOS) {
        assert.doesNotMatch(leer(archivo), /style=|innerHTML\s*=|\.style\./, archivo);
    }
});
