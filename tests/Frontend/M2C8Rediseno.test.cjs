/**
 * M2 C8 — rediseño visual de la consulta y del vínculo (hallazgos H2 a H9 del
 * usuario en las pruebas antes del despliegue) y el error H3:
 * - H3: vincular pone al día Pacientes sin recargar (lista, contador y expediente).
 * - H8: los signos no dejan escribir más que el máximo del servidor, tampoco al pegar.
 * - H6: flechas, Enter y Esc en los buscadores de síntomas, fármacos y nodos de alergias.
 * - H7: diagnósticos sugeridos en filas compactas que se expanden; «Usar» sigue copiando.
 * - H4: la lista compacta de alergias muestra las primeras y «Ver todas».
 * - H5, H9: solo variables de identidad en las hojas tocadas, sin style= ni eventos en línea.
 */
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const { crearDocumento, colgar } = require('./apoyo/dom-datos.cjs');
const { plantillaFila } = require('./apoyo/consulta-fila.cjs');

const RAIZ = path.join(__dirname, '../..');
const leer = archivo => fs.readFileSync(path.join(RAIZ, archivo), 'utf8');
const esperar = async (veces = 4) => {
    for (let i = 0; i < veces; i++) await new Promise(resolver => setImmediate(resolver));
};
// Los objetos creados dentro de vm tienen otro prototipo: se comparan por su contenido.
const plano = valor => JSON.parse(JSON.stringify(valor));

class EventoFalso {
    constructor(type, opciones = {}) {
        this.type = type;
        Object.assign(this, opciones);
    }
}

// ── H3 ─────────────────────────────────────────────────────────────

/** Pacientes con medical-module.js y propietario-mascota.js, como en el layout del veterinario. */
function pacientes() {
    const documento = crearDocumento();
    const raiz = documento.raiz;
    documento.body = { style: {} };
    for (const id of ['ownersGrid', 'ownersTableBody', 'petsGrid', 'petsTableBody', 'dossierView', 'mascotasExistentes', 'ownerSearchAyuda', 'ownerSearchWrapper', 'selectedOwnerInfo']) {
        colgar(raiz, 'div', {}, { id });
    }
    colgar(raiz, 'input', {}, { id: 'ownerSearch', value: '' });
    colgar(raiz, 'input', {}, { id: 'ownerSearchInput', value: '1000000006' });
    colgar(raiz, 'input', {}, { id: 'petOwnerDoc' });
    const estado = { vinculada: false, pedidos: [], avisos: [], recargas: 0 };
    const mascotas = () => [{ id_mascota: 7, nombre: 'Luna' }, ...(estado.vinculada ? [{ id_mascota: 21, nombre: 'Doki' }] : [])];
    const respuestas = {
        get_propietario_ajax: () => ({ id_usuario: 6, nombre_completo: 'Fabio Propietario', documento: '1000000006', estado: 1 }),
        listar_mascotas_propietario_ajax: mascotas,
        listar_propietarios_ajax: () => [{ id_usuario: 6, nombre_completo: 'Fabio Propietario', documento: '1000000006', estado: 1, total_mascotas: mascotas().length, pendiente_activacion: 0 }],
        vincular_mascota_ajax: () => {
            estado.vinculada = true;
            return { success: true };
        },
    };
    const contexto = {
        console,
        document: documento,
        window: { location: { href: 'index.php?action=vet_pacientes', reload: () => { estado.recargas++; } } },
        FormData: class { constructor() { this.datos = {}; } set(k, v) { this.datos[k] = v; } append(k, v) { this.datos[k] = v; } },
        // La página pedida otra vez trae la tarjeta de la mascota recién vinculada.
        DOMParser: class { parseFromString() { return { getElementById: id => (id === 'petsGrid' ? { innerHTML: '<div class="pet-card" data-id="21">Doki</div>' } : null) }; } },
        setTimeout: fn => { fn(); return 1; },
        clearTimeout() {},
        zookiToast: mensaje => estado.avisos.push(mensaje),
        zookiAviso: mensaje => estado.avisos.push(`Error: ${mensaje}`),
        fetch: async (url, opciones = {}) => {
            estado.pedidos.push({ url, opciones });
            const accion = (/action=([a-z_]+)/.exec(url) || [])[1];
            const datos = respuestas[accion] ? respuestas[accion]() : { success: true };
            return { json: async () => datos, text: async () => '<html></html>' };
        },
    };
    contexto.window.document = documento;
    vm.createContext(contexto);
    vm.runInContext(leer('public/js/fechas.js') + '\n' + leer('public/js/medical-module.js'), contexto);
    vm.runInContext(leer('public/js/propietario-mascota.js'), contexto);
    // El expediente se pinta con su función de siempre; aquí solo se observa qué mascotas recibe.
    const expedientes = [];
    contexto.renderOwnerDossier = (owner, lista) => expedientes.push(lista.map(m => m.nombre));
    contexto.mostrarDossier = () => {};
    return { contexto, documento, estado, expedientes };
}

test('H3: vincular pone al día Pacientes sin recargar: la lista de mascotas, el contador del propietario y su expediente abierto', async () => {
    const p = pacientes();
    await p.contexto.openOwnerDossier('6');
    assert.deepEqual(plano(p.expedientes), [['Luna']], 'El expediente de Fabio está abierto, con Luna');

    const panel = p.documento.getElementById('mascotasExistentes');
    const doki = { id_mascota: 21, nombre: 'Doki', nombre_especie: 'Canino', nombre_raza: 'Dóberman', raza_indicada: null, url_foto: null };
    p.contexto.window.ZookiPropietarioMascota.pintar(panel, { vinculado: true, id_usuario: 6, nombre_completo: 'Fabio Propietario', pendiente: false, mascotas: [doki] }, '1000000006');
    const vincular = panel.querySelector('[data-vincular-mascota="21"]');
    p.estado.pedidos.length = 0;
    p.documento.disparar('click', { target: { closest: selector => (selector === 'button' ? vincular : null) } });
    await esperar(8);

    const acciones = p.estado.pedidos.map(pedido => (/action=([a-z_]+)/.exec(pedido.url) || [])[1]);
    assert.equal(acciones[0], 'vincular_mascota_ajax');
    assert.deepEqual(plano(p.estado.pedidos[0].opciones.body.datos), { id_propietario: 6, id_mascota: '21' });
    assert.ok(acciones.includes('vet_pacientes'), 'La lista de mascotas se pide otra vez');
    assert.ok(acciones.includes('listar_propietarios_ajax'), 'Antes de C8 la tarjeta del propietario no se pedía: su contador se quedaba viejo');
    assert.ok(acciones.includes('listar_mascotas_propietario_ajax'), 'Ni el expediente abierto');
    assert.equal(p.documento.getElementById('petsGrid').innerHTML, '<div class="pet-card" data-id="21">Doki</div>');
    assert.match(p.documento.getElementById('ownersGrid').textContent, /Fabio Propietario.*2 mascota\(s\)/);
    assert.deepEqual(plano(p.expedientes.at(-1)), ['Luna', 'Doki'], 'El expediente ya muestra a Doki');
    assert.match(panel.textContent, /Doki.*Vinculada/);
    assert.deepEqual(plano(p.estado.avisos), ['Doki quedó vinculada a la clínica.'], 'Un aviso breve y ningún error');
    assert.equal(p.estado.recargas, 0, 'Sin recargar');
});

// ── H2 ─────────────────────────────────────────────────────────────

test('H2: las mascotas del propietario van en mini-tarjetas horizontales con avatar, especie · raza y «Vincular», en un carrusel', () => {
    const p = pacientes();
    const panel = p.documento.getElementById('mascotasExistentes');
    const mascotas = ['Doki', 'gato', 'Kira'].map((nombre, i) => ({ id_mascota: 20 + i, nombre, nombre_especie: 'Canino', nombre_raza: 'Pinscher', raza_indicada: null, url_foto: null }));
    mascotas.push({ id_mascota: 30, nombre: 'Luna', nombre_especie: 'Felino', nombre_raza: null, raza_indicada: null, url_foto: 'luna.jpg' });
    p.contexto.window.ZookiPropietarioMascota.pintar(panel, { vinculado: true, id_usuario: 6, nombre_completo: 'Fabio Propietario', pendiente: false, mascotas }, '1000000006');

    const pista = panel.querySelector('.mascotas-carrusel__pista');
    assert.equal(pista.getAttribute('role'), 'list');
    const tarjetas = pista.querySelectorAll('.mascota-mini');
    assert.equal(tarjetas.length, 4);
    const [doki] = tarjetas;
    assert.equal(doki.getAttribute('role'), 'listitem');
    assert.equal(doki.querySelector('.avatar-iniciales').textContent, 'D', 'Sin foto: el avatar azul con la inicial');
    assert.equal(doki.querySelector('.mascota-mini__detalle').textContent, 'Canino · Pinscher');
    const boton = doki.querySelector('[data-vincular-mascota="20"]');
    assert.equal(boton.textContent, 'Vincular');
    assert.equal(boton.className, 'mascota-mini__vincular');
    assert.equal(boton.getAttribute('aria-label'), 'Vincular a Doki a la clínica');
    const foto = tarjetas[3].querySelector('.mascota-mini__foto');
    assert.equal(foto.src, 'uploads/mascotas/luna.jpg', 'Con foto, la foto (RE-1.5.1)');
    const flechas = panel.querySelectorAll('[data-carrusel]');
    assert.deepEqual(flechas.map(f => f.getAttribute('aria-label')), ['Mascotas anteriores', 'Más mascotas']);
    assert.ok(flechas.every(f => f.hidden), 'Las flechas aparecen solo si hay más mascotas hacia ese lado');
    assert.ok(['pointerdown', 'pointermove', 'pointerup', 'scroll'].every(tipo => (pista.escuchas[tipo] || []).length > 0), 'Se desplaza arrastrando o con el dedo');

    const css = leer('public/css/pacientes.css');
    assert.match(css, /\.mascotas-carrusel__pista \{[^}]*overflow-x: auto;[^}]*scrollbar-width: none;/, 'Sin barra de scroll a la vista');
    assert.match(css, /\.mascotas-carrusel__pista::-webkit-scrollbar \{\s*display: none;/);
    assert.match(css, /\.mascotas-carrusel \{[^}]*contain: inline-size;/, 'Las tarjetas no ensanchan el modal');
});

// ── H8 ─────────────────────────────────────────────────────────────

/** interacciones.js con campos de texto, como en D31Entrada. */
function entrada() {
    const eventos = {};
    const documento = {
        addEventListener(tipo, callback) {
            eventos[tipo] ??= [];
            eventos[tipo].push(callback);
        },
    };
    vm.runInNewContext(leer('public/js/interacciones.js'), { document: documento, TextEncoder, Event: class { constructor(tipo) { this.type = tipo; } } });
    const emitir = (tipo, evento) => (eventos[tipo] || []).forEach(callback => callback(evento));
    const campo = (dataset, maximo) => ({
        dataset: { ...dataset },
        maxLength: maximo,
        value: '',
        type: 'text',
        selectionStart: 0,
        selectionEnd: 0,
        setSelectionRange(inicio, fin) { this.selectionStart = inicio; this.selectionEnd = fin; },
        dispatchEvent(evento) { emitir(evento.type, { target: this }); },
    });
    const escribir = (input, texto, pegar = false) => {
        const evento = {
            target: input, data: texto, inputType: 'insertText', cancelable: true,
            clipboardData: { getData: () => texto },
            preventDefault() { this.impedido = true; },
        };
        emitir(pegar ? 'paste' : 'beforeinput', evento);
        if (!evento.impedido) {
            const inicio = input.selectionStart;
            input.value = input.value.slice(0, inicio) + texto + input.value.slice(input.selectionEnd);
            input.setSelectionRange(inicio + texto.length, inicio + texto.length);
            emitir('input', { target: input });
        }
    };
    const teclear = (input, texto) => [...texto].forEach(tecla => escribir(input, tecla));
    return { campo, escribir, teclear };
}

// Los mismos atributos de ValidadorClinico::atributosSigno (ValidadorClinicoTest los comprueba en PHP).
const SIGNOS = {
    peso: [{ caracteres: '0-9.,', formato: 'decimal', enteros: '3', decimales: '2', minimo: '0.01', maximo: '200' }, 6],
    temperatura: [{ caracteres: '0-9.,', formato: 'decimal', enteros: '2', decimales: '1', minimo: '25', maximo: '45' }, 4],
    frecuencia_cardiaca: [{ caracteres: '0-9', formato: 'decimal', enteros: '3', decimales: '0', minimo: '10', maximo: '400' }, 3],
    frecuencia_respiratoria: [{ caracteres: '0-9', formato: 'decimal', enteros: '3', decimales: '0', minimo: '5', maximo: '150' }, 3],
};

test('H8: el peso no deja escribir más que el máximo del servidor (3 enteros, 2 decimales, hasta 200), tampoco al pegar', () => {
    const e = entrada();
    const nuevo = () => e.campo(...SIGNOS.peso);

    const tecleado = nuevo();
    e.teclear(tecleado, '20000');
    assert.equal(tecleado.value, '200', 'Antes se podía escribir 20000');

    const pasado = nuevo();
    e.teclear(pasado, '250');
    assert.equal(pasado.value, '25', 'La tecla que lo haría pasar de 200 no entra');

    const pegado = nuevo();
    e.escribir(pegado, '20000', true);
    assert.equal(pegado.value, '200');

    const decimales = nuevo();
    e.escribir(decimales, '12.3456', true);
    assert.equal(decimales.value, '12.34', 'Dos decimales, como decimal(5,2)');

    const coma = nuevo();
    e.teclear(coma, '8,5');
    assert.equal(coma.value, '8.5', 'La coma se toma como punto: el servidor recibe 8.5');

    const letras = nuevo();
    e.escribir(letras, 'abc9e1', true);
    assert.equal(letras.value, '91');
});

test('H8: temperatura y frecuencias tienen la misma restricción con sus límites de ValidadorClinico', () => {
    const e = entrada();
    const temperatura = e.campo(...SIGNOS.temperatura);
    e.teclear(temperatura, '450');
    assert.equal(temperatura.value, '45');
    const conDecimal = e.campo(...SIGNOS.temperatura);
    e.escribir(conDecimal, '38,55', true);
    assert.equal(conDecimal.value, '38.5');

    const cardiaca = e.campo(...SIGNOS.frecuencia_cardiaca);
    e.teclear(cardiaca, '4001');
    assert.equal(cardiaca.value, '400');
    const pasada = e.campo(...SIGNOS.frecuencia_cardiaca);
    e.teclear(pasada, '401');
    assert.equal(pasada.value, '40');
    const sinPunto = e.campo(...SIGNOS.frecuencia_cardiaca);
    e.escribir(sinPunto, '100.5', true);
    assert.equal(sinPunto.value, '100', 'Es un entero: el punto no entra');

    const respiratoria = e.campo(...SIGNOS.frecuencia_respiratoria);
    e.teclear(respiratoria, '151');
    assert.equal(respiratoria.value, '15');
    const pegada = e.campo(...SIGNOS.frecuencia_respiratoria);
    e.escribir(pegada, '150', true);
    assert.equal(pegada.value, '150');
});

test('H8: las dos entradas usan el helper en los cuatro signos y el aviso valida su rango junto al campo', () => {
    for (const vista of ['views/vet/modal_consulta.php', 'views/vet/atencion.php']) {
        const html = leer(vista);
        for (const signo of Object.keys(SIGNOS)) {
            assert.match(html, new RegExp(`ValidadorClinico::atributosSigno\\('${signo}'\\)`), `${vista}: ${signo}`);
            assert.match(html, new RegExp(`ayudaSigno\\('${signo}'\\)`), `${vista}: la ayuda de ${signo}`);
        }
        assert.doesNotMatch(html, /type="number"/, `${vista}: sin el campo numérico del navegador`);
    }

    const documento = crearDocumento();
    const contexto = { document: documento, console, window: {} };
    contexto.window = contexto;
    vm.createContext(contexto);
    vm.runInContext(leer('public/js/consulta-formulario.js'), contexto);
    const { errorDe } = contexto.ZookiConsultaForm;
    const signo = (nombre, valor) => {
        const [datos] = SIGNOS[nombre];
        return colgar(documento.raiz, 'input', { ...datos, avisoRango: `Rango de ${nombre}` }, { type: 'text', value: valor });
    };
    assert.equal(errorDe(signo('temperatura', '20')), 'Rango de temperatura', 'El mínimo se avisa junto al campo');
    assert.equal(errorDe(signo('temperatura', '38.5')), '');
    assert.equal(errorDe(signo('frecuencia_cardiaca', '100.5')), 'Rango de frecuencia_cardiaca', 'Un entero');
    assert.equal(errorDe(signo('peso', '0')), 'Rango de peso');
    assert.equal(errorDe(signo('peso', '8.5')), '');
});

// ── H6 ─────────────────────────────────────────────────────────────

function ventanaCon(fetch) {
    const documento = crearDocumento();
    const ventana = {
        document: documento, console, URLSearchParams, Date, Event: EventoFalso, CustomEvent: EventoFalso,
        setTimeout: fn => { fn(); return 1; },
        clearTimeout: () => {},
        fetch,
    };
    ventana.window = ventana;
    vm.createContext(ventana);
    vm.runInContext(leer('public/js/combobox.js'), ventana);
    return { ventana, documento };
}

const opciones = lista => lista.querySelectorAll('[role="option"]');
const activa = lista => opciones(lista).filter(opcion => opcion.getAttribute('aria-selected') === 'true');

test('H6: el buscador de síntomas se recorre con flechas, elige con Enter y cierra con Esc, sin perder el clic', async () => {
    const sugerencias = [
        { codigo: 'sintoma.vomito', etiqueta: 'Vómito' },
        { codigo: 'sintoma.vomito_sangre', etiqueta: 'Vómito con sangre' },
        { codigo: 'sintoma.diarrea', etiqueta: 'Diarrea' },
    ];
    const { ventana, documento } = ventanaCon(async () => ({ json: async () => ({ success: true, sugerencias }) }));
    vm.runInContext(leer('public/js/consulta-sintomas.js'), ventana);
    const raiz = colgar(documento.raiz, 'div', { sintomas: '', max: '30' });
    const chips = colgar(raiz, 'div', { sintomasChips: '' }, { hidden: true });
    const buscador = colgar(raiz, 'input', { sintomasBuscar: '' }, { type: 'text' });
    const lista = colgar(raiz, 'ul', { sintomasSugerencias: '' }, { hidden: true });
    colgar(raiz, 'small', { campoAviso: 'sintomas' });
    const marcados = [];
    ventana.ZookiSintomas.crear(raiz, { idMascota: () => '1', alCambiar: lista => marcados.push(lista.map(s => s.codigo)) });

    assert.equal(buscador.getAttribute('role'), 'combobox');
    assert.equal(buscador.getAttribute('aria-autocomplete'), 'list');
    assert.equal(buscador.getAttribute('aria-controls'), lista.id);
    assert.equal(lista.getAttribute('role'), 'listbox');

    buscador.value = 'vo';
    buscador.disparar('input');
    await esperar();
    assert.equal(lista.hidden, false);
    assert.equal(opciones(lista).length, 3);

    const abajo = buscador.disparar('keydown', { key: 'ArrowDown' });
    assert.ok(abajo.prevenido, 'La flecha no mueve el cursor del campo');
    assert.equal(activa(lista)[0].dataset.codigo, 'sintoma.vomito');
    assert.ok(activa(lista)[0].classList.contains('is-activa'));
    assert.equal(buscador.getAttribute('aria-activedescendant'), activa(lista)[0].id);
    buscador.disparar('keydown', { key: 'ArrowDown' });
    buscador.disparar('keydown', { key: 'ArrowDown' });
    assert.equal(activa(lista)[0].dataset.codigo, 'sintoma.diarrea');
    buscador.disparar('keydown', { key: 'ArrowDown' });
    assert.equal(activa(lista)[0].dataset.codigo, 'sintoma.vomito', 'Al final vuelve al principio');
    buscador.disparar('keydown', { key: 'ArrowUp' });
    assert.equal(activa(lista)[0].dataset.codigo, 'sintoma.diarrea', 'Y hacia arriba, al último');
    assert.equal(activa(lista).length, 1, 'Una sola activa');

    const enter = buscador.disparar('keydown', { key: 'Enter' });
    assert.ok(enter.prevenido, 'Enter no envía la consulta');
    assert.deepEqual(plano(marcados.at(-1)), ['sintoma.diarrea'], 'Enter marca la opción activa');
    assert.equal(chips.hidden, false);
    assert.equal(lista.hidden, true);
    assert.equal(buscador.getAttribute('aria-activedescendant'), '');

    buscador.value = 'vo';
    buscador.disparar('input');
    await esperar();
    const sinActiva = buscador.disparar('keydown', { key: 'Enter' });
    assert.ok(sinActiva.prevenido);
    assert.equal(marcados.length, 1, 'Sin opción activa, Enter no marca nada');
    let detenido = false;
    const esc = buscador.disparar('keydown', { key: 'Escape', stopPropagation() { detenido = true; } });
    assert.ok(esc.prevenido);
    assert.ok(detenido, 'Esc no cierra la ventana de la consulta');
    assert.equal(lista.hidden, true);

    buscador.value = 'vo';
    buscador.disparar('input');
    await esperar();
    lista.querySelector('[data-codigo="sintoma.vomito"]').click();
    assert.deepEqual(plano(marcados.at(-1)), ['sintoma.diarrea', 'sintoma.vomito'], 'El clic sigue marcando');
});

test('H6: el buscador de fármacos de la fila de tratamiento también se maneja con el teclado', async () => {
    const farmacos = [{ codigo: 'farmaco.paracetamol', etiqueta: 'Paracetamol (acetaminofén)' }, { codigo: 'farmaco.prednisona', etiqueta: 'Prednisona' }];
    const { ventana, documento } = ventanaCon(async url => ({
        json: async () => (String(url).includes('buscar_farmaco_ajax') ? { success: true, farmacos } : { success: true, filas: [] }),
    }));
    vm.runInContext(leer('public/js/consulta-tratamientos.js'), ventana);
    const raiz = colgar(documento.raiz, 'div', { tratamientos: '', avisos: '{}' });
    colgar(raiz, 'div', { tratamientosLista: '' });
    const plantilla = colgar(raiz, 'template', { tratamientoPlantilla: '' });
    plantilla.content = { firstElementChild: plantillaFila() };
    const api = ventana.ZookiTratamientos.crear(raiz, { idMascota: () => '1', avisar: () => {} });
    const fila = api.agregar();
    const medicamento = fila.querySelector('[data-tratamiento-campo="medicamento"]');
    const lista = fila.querySelector('[data-farmaco-sugerencias]');
    assert.equal(medicamento.getAttribute('role'), 'combobox');
    assert.equal(medicamento.getAttribute('aria-controls'), lista.id);

    medicamento.value = 'pre';
    medicamento.disparar('input');
    await esperar();
    assert.equal(opciones(lista).length, 2);
    medicamento.disparar('keydown', { key: 'ArrowDown' });
    medicamento.disparar('keydown', { key: 'ArrowDown' });
    assert.equal(activa(lista)[0].dataset.farmaco, 'farmaco.prednisona');
    const enter = medicamento.disparar('keydown', { key: 'Enter' });
    assert.ok(enter.prevenido);
    assert.equal(fila.querySelector('[data-tratamiento-campo="farmaco"]').value, 'farmaco.prednisona', 'Enter elige el fármaco activo');
    assert.equal(medicamento.value, 'Prednisona');
    assert.equal(lista.hidden, true);

    medicamento.value = 'para';
    medicamento.disparar('input');
    await esperar();
    medicamento.disparar('keydown', { key: 'Escape', stopPropagation() {} });
    assert.equal(lista.hidden, true, 'Esc cierra la lista');
    assert.equal(fila.querySelector('[data-tratamiento-campo="farmaco"]').value, '', 'Sin elegir, el nombre queda libre');
});

test('H6: el buscador de nodos de «Registrar alergia» se maneja con el teclado y Esc no cierra el formulario', async () => {
    const nodos = [
        { codigo: 'farmaco.amoxicilina', etiqueta: 'Amoxicilina', coincidencia: 'amoxicilina' },
        { codigo: 'farmaco.amoxicilina_clav', etiqueta: 'Amoxicilina con ácido clavulánico', coincidencia: 'amoxi' },
    ];
    const { ventana, documento } = ventanaCon(async () => ({ ok: true, json: async () => ({ success: true, nodos }) }));
    const raiz = documento.raiz;
    const modal = colgar(raiz, 'div', { alertaModal: '' });
    colgar(modal, 'p', { alertaMascota: '' });
    const form = colgar(modal, 'form', { alertaForm: '', nodosPorTipo: JSON.stringify({ alergia: ['farmaco'], otra: [] }) });
    form.reset = () => {};
    colgar(form, 'input', { alertaCampo: 'id_mascota' }, { name: 'id_mascota', type: 'hidden' });
    const nodo = colgar(form, 'input', { alertaCampo: 'nodo' }, { name: 'nodo', type: 'hidden' });
    const alergia = colgar(form, 'input', { alertaTipo: '' }, { name: 'tipo', type: 'radio', value: 'alergia' });
    colgar(form, 'small', { alertaAviso: 'tipo' });
    const area = colgar(form, 'textarea', { alertaCampo: 'descripcion' }, { name: 'descripcion' });
    area.setAttribute('maxlength', '255');
    colgar(form, 'small', { alertaAviso: 'descripcion' });
    const zona = colgar(form, 'div', { alertaZonaNodo: '' }, { hidden: true });
    const envoltura = colgar(zona, 'div', { alertaBuscar: '' });
    const buscador = colgar(envoltura, 'input', { alertaCampo: 'busqueda' }, { type: 'search' });
    colgar(zona, 'div', { alertaNodoElegido: '' }, { hidden: true });
    const lista = colgar(zona, 'div', { alertaSugerencias: '' }, { hidden: true });
    colgar(form, 'p', { alertaResultado: '' });
    vm.runInContext(leer('public/js/alertas-medicas.js'), ventana);
    documento.disparar('DOMContentLoaded');

    ventana.ZookiAlertas.abrirFormulario(7, 'Luna');
    alergia.checked = true;
    alergia.disparar('change');
    assert.equal(zona.hidden, false);
    assert.equal(buscador.getAttribute('role'), 'combobox');
    buscador.value = 'amoxi';
    buscador.disparar('input');
    await esperar();
    assert.equal(buscador.getAttribute('aria-expanded'), 'true');
    buscador.disparar('keydown', { key: 'ArrowDown' });
    buscador.disparar('keydown', { key: 'ArrowDown' });
    assert.equal(activa(lista)[0].dataset.nodoSugerido, 'farmaco.amoxicilina_clav');

    let detenido = false;
    buscador.disparar('keydown', { key: 'Escape', stopPropagation() { detenido = true; } });
    assert.equal(lista.hidden, true, 'Esc cierra la lista');
    assert.ok(detenido);
    assert.ok(modal.classList.contains('is-open'), 'El formulario sigue abierto');
    documento.disparar('keydown', { key: 'Escape', defaultPrevented: true });
    assert.ok(modal.classList.contains('is-open'), 'Un Esc ya atendido por la lista no cierra el formulario');

    buscador.value = 'amoxi';
    buscador.disparar('input');
    await esperar();
    buscador.disparar('keydown', { key: 'ArrowDown' });
    const enter = buscador.disparar('keydown', { key: 'Enter' });
    assert.ok(enter.prevenido, 'Enter no envía el formulario');
    assert.equal(nodo.value, 'farmaco.amoxicilina', 'Enter enlaza el nodo activo');
    documento.disparar('keydown', { key: 'Escape' });
    assert.equal(modal.classList.contains('is-open'), false, 'Esc fuera de la lista sigue cerrando el formulario');
});

// ── H7 ─────────────────────────────────────────────────────────────

test('H7: los diagnósticos sugeridos son filas compactas que se expanden con los síntomas y la fuente; «Usar» sigue copiando', async () => {
    const candidato = {
        codigo: 'diagnostico.parvo', etiqueta: 'Parvovirosis canina', puntuacion: 2.2,
        cobertura: { coinciden: 3, caracteristicos: 6, texto: 'Coincide 3 de 6 síntomas característicos' },
        sintomas_coincidentes: ['sintoma.vomito'],
        fuentes: [{ codigo: 'msd', entidad: 'MSD Veterinary Manual', titulo: 'Canine Parvovirus', url: 'https://www.msdvetmanual.com/parvo', fundamento: 'clinico' }],
    };
    const { ventana, documento } = ventanaCon(async () => ({ json: async () => ({ success: true, candidatos: [candidato], datos_faltantes: [] }) }));
    vm.runInContext(leer('public/js/consulta-diagnosticos.js'), ventana);
    const raiz = colgar(documento.raiz, 'section', { diagnosticos: '' });
    colgar(raiz, 'p', { diagnosticosEstado: '' });
    const lista = colgar(raiz, 'ol', { diagnosticosLista: '' }, { hidden: true });
    const datos = colgar(raiz, 'div', { diagnosticosDatos: '' }, { hidden: true });
    colgar(datos, 'ul', { diagnosticosFicha: '' }, { hidden: true });
    const campo = colgar(documento.raiz, 'textarea', {}, { value: '' });
    const api = ventana.ZookiDiagnosticos.crear(raiz, { idMascota: () => '1', campoDiagnostico: campo });
    api.actualizar([{ codigo: 'sintoma.vomito', etiqueta: 'Vómito' }]);
    await esperar();

    const item = lista.querySelector('[data-diagnostico="diagnostico.parvo"]');
    const abrir = item.querySelector('.consulta-sugerencia__abrir');
    const detalle = item.querySelector('.consulta-sugerencia__detalle');
    assert.equal(abrir.textContent, 'Parvovirosis caninaCoincide 3 de 6 síntomas característicos', 'Nombre y «coincide X de Y» en la fila');
    assert.equal(abrir.getAttribute('aria-expanded'), 'false');
    assert.equal(abrir.getAttribute('aria-controls'), detalle.id);
    assert.equal(detalle.hidden, true, 'Plegada: solo la fila');
    assert.match(detalle.textContent, /Coinciden: Vómito/);
    assert.match(detalle.textContent, /Fuente: MSD Veterinary Manual — Canine Parvovirus/);
    assert.match(detalle.textContent, /Sugerencia de apoyo · pendiente de validación veterinaria/);

    abrir.click();
    assert.equal(abrir.getAttribute('aria-expanded'), 'true');
    assert.equal(detalle.hidden, false);
    assert.ok(item.classList.contains('is-abierta'));
    abrir.click();
    assert.equal(detalle.hidden, true);

    const usar = item.querySelector('[data-usar-diagnostico="diagnostico.parvo"]');
    assert.equal(usar.textContent, 'Usar');
    assert.equal(usar.getAttribute('aria-label'), 'Usar este diagnóstico: Parvovirosis canina');
    usar.click();
    assert.equal(campo.value, 'Parvovirosis canina', '«Usar» copia el diagnóstico (RE-2.8.2)');
    assert.ok(campo.enfocado, 'Y deja editarlo');
});

test('H7: en Atención cada paso es el único que desplaza y las etiquetas no se montan', () => {
    const css = leer('public/css/atencion.css');
    const paso = css.match(/\n\.atencion__paso \{[^}]*\}/)[0];
    assert.match(paso, /overflow-y: auto;/);
    assert.match(paso, /overscroll-behavior: contain;/);
    assert.doesNotMatch(css, /tratamientos__lista/, 'La lista de tratamientos ya no tiene su propio scroll');
    const consulta = leer('public/css/consulta-clinica.css');
    assert.doesNotMatch(consulta.match(/\n\.consulta-sugerencias__lista \{[^}]*\}/)[0], /overflow|max-height/);
    assert.match(consulta, /\.consulta-campo--crece \{\s*flex: 1 0 auto;/, 'El campo crece, pero no se encoge por debajo de su contenido');
    const vista = leer('views/vet/atencion.php');
    assert.match(vista, /<label for="atencionDiagnostico" class="consulta-campo__etiqueta">Diagnóstico <\?= \$obligatorio \?><\/label>/);
    assert.match(vista, /<div class="consulta-fila-2">[\s\S]*?Plan \/ recomendaciones[\s\S]*?Observaciones/);
    assert.doesNotMatch(vista, /class="campo campo--crece"[\s\S]*?name="diagnostico"/);
});

// ── H4 ─────────────────────────────────────────────────────────────

test('H4: la lista compacta de alergias muestra las primeras y «Ver todas»; el título va en una línea con su contador', () => {
    const { ventana, documento } = ventanaCon(async () => ({ json: async () => ({}) }));
    vm.runInContext(leer('public/js/alertas-medicas.js'), ventana);
    const alerta = id => ({ id_alerta: id, tipo: 'alergia', tipo_nombre: 'Alergia', descripcion: `Alergia ${id}`, clinica_nombre: 'Clínica Norte', fecha: '2026-10-14', es_propia: true, puede_desactivar: false });
    const compacta = colgar(documento.raiz, 'section', { alertasMedicas: '' }, { className: 'alertas-medicas alertas-medicas--compacta' });
    ventana.ZookiAlertas.pintar(compacta, { activas: [1, 2, 3, 4].map(alerta), historial: [] }, { idMascota: 7 });
    const lista = compacta.querySelector('.alertas-medicas__lista');
    assert.ok(lista.classList.contains('alertas-medicas__lista--limitada'));
    assert.equal(lista.querySelectorAll('[data-alerta]').length, 4, 'Todas en la página; el CSS muestra las dos primeras');
    const ver = compacta.querySelector('[data-ver-todas-alertas]');
    assert.equal(ver.textContent, 'Ver todas (4)');
    assert.equal(ver.getAttribute('aria-expanded'), 'false');
    ver.click();
    assert.ok(lista.classList.contains('is-expandida'));
    assert.equal(ver.textContent, 'Ver menos');
    assert.equal(ver.getAttribute('aria-expanded'), 'true');
    ver.click();
    assert.equal(lista.classList.contains('is-expandida'), false);
    assert.equal(compacta.querySelector('.alertas-medicas__contador').textContent, '4');
    assert.equal(compacta.querySelector('[data-registrar-alerta]').getAttribute('aria-label'), 'Registrar alergia o alerta');

    const pocas = colgar(documento.raiz, 'section', { alertasMedicas: '' }, { className: 'alertas-medicas alertas-medicas--compacta' });
    ventana.ZookiAlertas.pintar(pocas, { activas: [1, 2].map(alerta), historial: [] }, { idMascota: 7 });
    assert.equal(pocas.querySelector('[data-ver-todas-alertas]'), null, 'Con dos no hace falta');
    const ficha = colgar(documento.raiz, 'section', { alertasMedicas: '' }, { className: 'alertas-medicas' });
    ventana.ZookiAlertas.pintar(ficha, { activas: [1, 2, 3, 4].map(alerta), historial: [] }, { idMascota: 7 });
    assert.equal(ficha.querySelector('[data-ver-todas-alertas]'), null, 'La ficha completa muestra todas');

    const css = leer('public/css/alertas-medicas.css');
    assert.match(css, /\.alertas-medicas__titulo \{[^}]*white-space: nowrap;/);
    assert.match(css, /\.alertas-medicas__lista--limitada:not\(\.is-expandida\) > \.alertas-medicas__item:nth-child\(n \+ 3\) \{\s*display: none;/);
    const atencion = leer('public/css/atencion.css');
    assert.match(atencion, /\.atencion__paciente > \* \{\s*flex-shrink: 0;/, 'Los datos del paciente no se recortan');
    assert.match(atencion, /\.paciente__datos \{\s*display: grid;\s*grid-template-columns: repeat\(2, minmax\(0, 1fr\)\);/);
});

// ── H5 y H9 ────────────────────────────────────────────────────────

const HOJAS = ['public/css/atencion.css', 'public/css/consulta-clinica.css', 'public/css/alertas-medicas.css', 'public/css/pacientes.css'];
const sinComentarios = css => css.replace(/\/\*[\s\S]*?\*\//g, '');

test('H5: las hojas tocadas no tienen colores sueltos y toda variable que usan está en identidad.css', () => {
    const identidad = leer('public/css/identidad.css');
    const definidas = new Set([...identidad.matchAll(/(--z-[a-z-]+):/g)].map(m => m[1]));
    assert.ok(definidas.has('--z-primary') && definidas.has('--z-danger') && definidas.has('--z-warning'));
    assert.match(identidad, /--z-primary: #0052FF;/, 'El mismo azul de marca del portal');
    for (const hoja of HOJAS) {
        const css = sinComentarios(leer(hoja));
        assert.doesNotMatch(css, /#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(/i, `${hoja}: un color suelto`);
        assert.doesNotMatch(css, /:\s*(white|black|red|green|blue|gray|grey|orange|purple|yellow)\b/i, `${hoja}: un color con nombre`);
        for (const [, variable] of css.matchAll(/var\((--z-[a-z-]+)/g)) {
            assert.ok(definidas.has(variable), `${hoja}: ${variable} no está en identidad.css`);
        }
    }
    const vet = leer('views/vet/layout.php');
    assert.ok(vet.indexOf('css/identidad.css') > 0 && vet.indexOf('css/identidad.css') < vet.indexOf('css/styles.css'), 'El layout clínico la carga primero');
    const portal = leer('views/portal/layout.php');
    assert.ok(portal.indexOf('css/identidad.css') > 0 && portal.indexOf('css/identidad.css') < portal.indexOf('css/alertas-medicas.css'), 'El portal también, antes de las alertas');
});

test('H5: el botón principal va en el azul de marca y la mascota se ve con el avatar azul con su inicial', () => {
    const atencion = leer('views/vet/atencion.php');
    assert.match(atencion, /class="atencion__btn atencion__btn--primario" id="btnFinalizarAtencion"/);
    assert.doesNotMatch(atencion + leer('public/css/atencion.css'), /--exito/);
    assert.match(atencion, /avatar-iniciales paciente__avatar[^>]*><\?= \$e\(PresentacionPlataforma::iniciales/);
    assert.match(atencion, /<\?php if \(\$tieneFoto\): \?>\s*<img src="<\?= \$e\(\$fotoMascota\) \?>"/, 'Con foto, la foto');
    const modal = leer('views/vet/modal_consulta.php');
    assert.match(modal, /<span class="avatar-iniciales consulta-avatar" id="consultationPetInicial" aria-hidden="true"><\/span>/);
    assert.match(modal, /class="btn-modal-primary"><i class="fas fa-floppy-disk" aria-hidden="true"><\/i> Guardar consulta/);
    assert.match(leer('public/css/consulta-clinica.css'), /\.consulta-modal \.consulta-modal__pie \.btn-modal-primary \{[^}]*background: var\(--z-primary\);/);

    // Al abrir el modal, la inicial de la mascota (la misma regla de las tarjetas de propietarios).
    const documento = crearDocumento();
    colgar(documento.raiz, 'div', {}, { id: 'modalConsulta' });
    const form = colgar(documento.raiz, 'form', {}, { id: 'formConsulta' });
    form.reset = () => {};
    colgar(form, 'input', {}, { id: 'consultation_id_mascota', type: 'hidden' });
    const nombre = colgar(documento.raiz, 'span', {}, { id: 'consultationPetName' });
    const inicial = colgar(documento.raiz, 'span', {}, { id: 'consultationPetInicial' });
    const contexto = { document: documento, console, openModal: () => {}, inicialesDueno: texto => texto.split(' ').map(p => p[0]).join('').toUpperCase() };
    contexto.window = contexto;
    vm.createContext(contexto);
    vm.runInContext(leer('public/js/consulta-formulario.js'), contexto);
    contexto.openConsultationModal(7, 'Luna');
    assert.equal(nombre.textContent, 'Luna');
    assert.equal(inicial.textContent, 'L');
});

test('H5, H9: campos con el estilo del sistema, obligatorios con asterisco y ayuda discreta; «+ Agregar» con estilo', () => {
    const modal = leer('views/vet/modal_consulta.php');
    const atencion = leer('views/vet/atencion.php');
    const fila = leer('views/partials/consulta_tratamiento_fila.php');
    for (const vista of [modal, atencion]) {
        assert.match(vista, /class="consulta-boton consulta-boton--suave"[^>]*data-agregar-tratamiento>\s*<i class="fas fa-plus" aria-hidden="true"><\/i> Agregar medicamento/);
        assert.match(vista, /class="consulta-ayuda"/);
        assert.match(vista, /consulta-formulario/);
        assert.doesNotMatch(vista, /btn-add-treatment|>Obligatorio\.</);
    }
    assert.equal((fila.match(/<\?= \$obligatorio \?>/g) || []).length, 6, 'Medicamento, dosis, vía, duración, inicio y conciliación');
    const css = leer('public/css/consulta-clinica.css');
    assert.match(css, /\.consulta-formulario :is\(input\[type="text"\], input\[type="search"\], input\[type="date"\], select, textarea\),/);
    assert.match(css, /\.consulta-boton--suave \{/);
    assert.match(css, /\.consulta-ayuda \{\s*display: block;\s*font-size: 0\.75rem;/);
    assert.match(css, /small\.cuenta-validacion\[data-campo-aviso\]:empty,/, 'El aviso rojo sale solo con un error');
});

test('H9: el modal tiene el pie siempre a la vista y la fila de tratamiento en una cuadrícula fija; en el celular cabe en el ancho', () => {
    const css = leer('public/css/consulta-clinica.css');
    assert.match(css, /\.consulta-modal \.consulta-modal__cuerpo \{[^}]*overflow-y: auto;/, 'Solo el cuerpo desplaza');
    assert.match(css, /\.consulta-modal \.consulta-modal__pie \{\s*flex-shrink: 0;/, 'El pie no se va con el scroll');
    assert.match(css, /grid-template-areas:\s*"medicamento medicamento dosis via quitar"\s*"duracion inicio fin \. \."\s*"observaciones observaciones observaciones observaciones \.";/);
    const celular = css.slice(css.indexOf('@media (max-width: 767px)'));
    assert.match(celular, /\.consulta-modal \.consulta-modal__panel \{[^}]*width: 100vw !important;/);
    assert.match(celular, /"inicio inicio inicio"\s*"fin fin fin"/, 'Las fechas sin recortar');
    assert.match(celular, /\.consulta-fila-2,\s*\.consulta-signos \{\s*grid-template-columns: minmax\(0, 1fr\);/);
    const fila = leer('views/partials/consulta_tratamiento_fila.php');
    for (const clase of ['medicamento', 'dosis', 'via', 'duracion', 'inicio', 'fin', 'observaciones']) {
        assert.match(fila, new RegExp(`class="consulta-campo consulta-tratamiento__${clase}"`), clase);
    }
});

test('H5, H9: ningún style= ni evento en línea en las vistas y scripts tocados', () => {
    const vistas = ['views/vet/modal_consulta.php', 'views/vet/atencion.php', 'views/partials/consulta_sintomas.php', 'views/partials/consulta_diagnosticos.php', 'views/partials/consulta_tratamiento_fila.php', 'views/partials/modal_alerta_medica.php'];
    for (const vista of vistas) {
        assert.doesNotMatch(leer(vista), /\son[a-z]+=|style=|<script/, vista);
    }
    const scripts = ['public/js/combobox.js', 'public/js/consulta-sintomas.js', 'public/js/consulta-diagnosticos.js', 'public/js/consulta-tratamientos.js', 'public/js/consulta-formulario.js', 'public/js/alertas-medicas.js', 'public/js/propietario-mascota.js'];
    for (const script of scripts) {
        assert.doesNotMatch(leer(script), /style=|\.style\.|\son[a-z]+="/, script);
    }
});
