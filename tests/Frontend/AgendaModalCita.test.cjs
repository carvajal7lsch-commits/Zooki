/**
 * Pulido del modal «Agendar cita» (2026-10-21, HU-4.13):
 * - El buscador de mascota se recorre con flechas, elige con Enter (sin enviar
 *   el formulario) y Esc cierra solo la lista, con el combobox común.
 * - Los obligatorios llevan asterisco y desaparece el título «Datos de la cita».
 * - Cada marca del triage pone el nombre en su línea y debajo desde cuándo y los
 *   minutos, sin cambiar lo que se envía.
 */
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const { crearDocumento, colgar } = require('./apoyo/dom-datos.cjs');

const RAIZ = path.join(__dirname, '../..');
const leer = archivo => fs.readFileSync(path.join(RAIZ, archivo), 'utf8').replace(/\r\n/g, '\n');
const tramo = (fuente, desde, hasta) => {
    const inicio = fuente.indexOf(desde);
    const fin = fuente.indexOf(hasta, inicio);
    assert.ok(inicio >= 0 && fin > inicio, `No se encontró ${desde}`);
    return fuente.slice(inicio, fin);
};
const esperar = () => new Promise(resolver => setImmediate(resolver));

const MASCOTAS = [
    { id: 4, nombre: 'Luna', propietario: 'Ana Pérez', url_foto: 'luna foto.jpg' },
    { id: 9, nombre: 'Lucas', propietario: 'Juan Gil', url_foto: 'lucas.jpg' },
    { id: 12, nombre: 'Toby', propietario: 'Lucía Mora' },
];

/** La lista recibe el HTML de calendario.js; aquí se convierte en nodos opción. */
function listaConHtml(documento, padre) {
    const lista = colgar(padre, 'div', {}, { id: 'cm_mascota_dropdown', hidden: true });
    Object.defineProperty(lista, 'innerHTML', {
        set(html) {
            lista.htmlRenderizado = html;
            lista.replaceChildren();
            for (const [boton] of html.matchAll(/<button[^>]*>/g)) {
                const opcion = documento.createElement('button');
                opcion.className = (boton.match(/class="([^"]*)"/) || [])[1] || '';
                for (const [, nombre, valor] of boton.matchAll(/\s(role|aria-selected)="([^"]*)"/g)) opcion.setAttribute(nombre, valor);
                for (const [, nombre, valor] of boton.matchAll(/\sdata-([a-z]+)="([^"]*)"/g)) opcion.dataset[nombre] = valor;
                // Como en el navegador, el clic de la opción sube hasta la lista, que lo delega.
                opcion.click = () => lista.disparar('click', { target: opcion });
                lista.appendChild(opcion);
            }
        },
    });
    return lista;
}

function buscadorMascota() {
    const documento = crearDocumento();
    const envoltura = colgar(documento.raiz, 'div', {}, { id: 'cm_mascota_wrap' });
    const campo = colgar(envoltura, 'input', {}, { id: 'cm_mascota_search', type: 'text' });
    const lista = listaConHtml(documento, envoltura);
    const elegidas = [];
    const ventana = { document: documento, console, esc: texto => String(texto), obtenerMascotas: async () => MASCOTAS };
    ventana.window = ventana;
    vm.createContext(ventana);
    vm.runInContext(tramo(leer('public/js/medical-module.js'), "const FOTO_MASCOTA_DEFECTO =", '/** HU-15:'), ventana);
    vm.runInContext(leer('public/js/combobox.js'), ventana);
    const fuente = leer('public/js/calendario.js');
    vm.runInContext(tramo(fuente, 'function abrirListaMascotas()', 'let _mascotaFicha'), ventana);
    vm.runInContext(tramo(fuente, 'function conectarBuscadorMascota()', "document.addEventListener('DOMContentLoaded'"), ventana);
    ventana.seleccionarMascota = (id, nombre) => {
        elegidas.push([id, nombre]);
        ventana.cerrarListaMascotas();
    };
    ventana.conectarBuscadorMascota();
    return { ventana, campo, lista, elegidas };
}

const opciones = lista => lista.querySelectorAll('[role="option"]');
const marcada = lista => opciones(lista).filter(opcion => opcion.getAttribute('aria-selected') === 'true');

test('los resultados muestran foto segura o iniciales como el portal, sin franja azul', async () => {
    const { ventana, lista } = buscadorMascota();
    await ventana.filtrarMascotas('lu');
    assert.match(lista.htmlRenderizado, /src="uploads\/mascotas\/luna%20foto.jpg"/);
    assert.match(lista.htmlRenderizado, /zk-mascota-iniciales" aria-hidden="true">T<\/span>/);
    assert.doesNotMatch(lista.htmlRenderizado, /default-pet.svg/);
    assert.match(lista.htmlRenderizado, /class="avatar-iniciales zk-mascota-iniciales"/);
    assert.match(leer('views/vet/calendario.php'), /id="cm_mascota_iniciales" class="avatar-iniciales zk-mascota-iniciales"/);
    const inicialesCss = tramo(leer('public/css/calendar-modules/modals.css'), '.zk-mascota-iniciales {', '.zk-selected .zk-mascota-iniciales');
    assert.doesNotMatch(inicialesCss, /background:|color:|border-radius:|font-weight:/, 'El formato procede del mismo componente que usa el portal');
    assert.equal(ventana.inicialesMascotaAgenda('  Luna María '), 'LM');
    assert.equal(ventana.inicialesMascotaAgenda('Ágata'), 'Á');
    assert.equal(ventana.inicialesMascotaAgenda(''), '?');
    assert.match(lista.htmlRenderizado, /class="zk-option__datos"/);
    assert.doesNotMatch(lista.htmlRenderizado, /onerror=/);
    const css = leer('public/css/calendar-modules/modals.css');
    const marcada = tramo(css, '.zk-option[aria-selected="true"]', '.zk-option__datos');
    assert.match(marcada, /box-shadow: none/);
    assert.doesNotMatch(marcada, /inset|cal-primary/);
});

test('seleccionar muestra la foto pequeña y una respuesta anterior no reemplaza la selección actual', async () => {
    const documento = crearDocumento();
    const ids = ['cm_mascota_foto', 'cm_mascota_iniciales', 'cm_mascota_chip_name', 'cm_mascota_chip', 'cm_mascota_wrap', 'cm_mascota_search', 'cm_mascota_dropdown'];
    ids.forEach(id => colgar(documento.raiz, id === 'cm_mascota_foto' ? 'img' : 'div', {}, { id }));
    const valores = {};
    const respuestas = [];
    const ventana = { document: documento, obtenerMascotas: () => new Promise(resolve => respuestas.push(resolve)),
        val(id, valor) { if (valor !== undefined) valores[id] = String(valor); return valores[id]; },
        cargarSlotsModal() {}, ocultarErrorModal() {}, actualizarResumenModal() {}, cerrarListaMascotas() {},
        triageModalC4: { consulta() {} } };
    vm.createContext(ventana);
    vm.runInContext(tramo(leer('public/js/medical-module.js'), 'const FOTO_MASCOTA_DEFECTO =', '/** HU-15:'), ventana);
    vm.runInContext(tramo(leer('public/js/calendario.js'), 'function inicialesMascotaAgenda(', 'async function filtrarMascotas('), ventana);
    vm.runInContext(tramo(leer('public/js/calendario.js'), 'let _mascotaFicha = null;', 'function onModalVeterinarioChange()'), ventana);
    const foto = documento.getElementById('cm_mascota_foto');
    ventana.seleccionarMascota(4, 'Luna', 'Ana Pérez');
    assert.equal(foto.hidden, true);
    assert.equal(documento.getElementById('cm_mascota_iniciales').textContent, 'L');
    ventana.seleccionarMascota(9, 'Lucas', 'Juan Gil');
    respuestas[1](MASCOTAS);
    await esperar();
    assert.equal(foto.src, 'uploads/mascotas/lucas.jpg');
    assert.equal(foto.hidden, false);
    assert.equal(documento.getElementById('cm_mascota_iniciales').hidden, true);
    respuestas[0](MASCOTAS);
    await esperar();
    assert.equal(foto.src, 'uploads/mascotas/lucas.jpg');
    assert.equal(documento.getElementById('cm_mascota_chip_name').textContent, 'Lucas · Juan Gil');
    ventana.limpiarMascotaSeleccionada();
    assert.equal(foto.hidden, true);
    assert.equal(documento.getElementById('cm_mascota_chip').hidden, true);
});

test('si falla la foto se muestran las iniciales sin pedir el icono de huella', () => {
    const { ventana } = buscadorMascota();
    const iniciales = { hidden: true };
    const foto = { hidden: false, parentElement: { querySelector: () => iniciales } };
    ventana.mostrarInicialesMascotaAgenda(foto);
    assert.equal(foto.hidden, true);
    assert.equal(iniciales.hidden, false);
    assert.equal(foto.src, undefined);
});

test('el panel derecho de horarios queda fuera de los pasos y permanece presente', () => {
    const formulario = tramo(leer('views/vet/calendario.php'), '<form id="formCitaModal"', '</form>');
    assert.doesNotMatch(formulario, /data-cita-paso="2"|cm_confirmacion/);
    assert.equal((formulario.match(/data-cita-paso=/g) || []).length, 2);
    assert.match(formulario, /data-cita-indicador="1"[^>]*disabled[^>]*><span>2<\/span>Urgencia/);
    assert.match(formulario, /<\/section>\s*<\/div>\s*<\/div>\s*<div class="zk-modal__col zk-modal__col--slots cita-horarios">/);
    assert.match(formulario, /cita-horarios">\s*<p[^>]*>Horario disponible[\s\S]*id="modal_slots_container"/);
    assert.match(leer('public/css/calendar-modules/modals.css'), /\.cita-distribucion\s*\{[^}]*grid-template-columns: minmax\(0, 1fr\) minmax\(0, 1\.1fr\)/);
});

test('los pasos exigen paciente y triage, conservan los datos al volver y bloquean doble envío', () => {
    const documento = crearDocumento();
    const paneles = [0, 1].map(i => {
        const panel = colgar(documento.raiz, 'section', { citaPaso: String(i) });
        const titulo = colgar(panel, 'h4');
        titulo.focus = () => { documento.enfocado = titulo; };
        return panel;
    });
    const indicadores = [0, 1].map(i => {
        const nodo = colgar(documento.raiz, 'li', { citaIndicador: String(i) });
        nodo.removeAttribute = nombre => { delete nodo.atributos[nombre]; };
        return nodo;
    });
    const siguiente = colgar(documento.raiz, 'button', {}, { id: 'cm_siguiente' });
    const anterior = colgar(documento.raiz, 'button', {}, { id: 'cm_anterior' });
    const submit = colgar(documento.raiz, 'button', {}, { id: 'cm_submit' });
    const valores = { modal_mascota: '', modal_tipo_cita: '', modal_hora: '09:00:30' };
    let solicitud = '';
    const ventana = { document: documento, enviosC3: { reserva: false }, val: id => valores[id], getVetModal: () => '2',
        triageModalC4: { consulta: () => solicitud }, ocultarErrorModal() {}, actualizarResumenModal() { ventana.actualizarPasosCita(); } };
    vm.createContext(ventana);
    vm.runInContext(tramo(leer('public/js/calendario.js'), 'let pasoCitaModal = 0;', 'function abrirCitaModal(date)'), ventana);
    ventana.mostrarPasoCita(0);
    assert.equal(siguiente.disabled, true);
    assert.equal(anterior.hidden, true);
    assert.equal(submit.hidden, true);
    ventana.avanzarPasoCita();
    assert.equal(paneles[0].hidden, false);
    valores.modal_mascota = '4';
    valores.modal_tipo_cita = '1';
    ventana.avanzarPasoCita();
    assert.equal(paneles[1].hidden, false);
    assert.equal(submit.disabled, true, 'No salta revisión pendiente ni derivación roja');
    solicitud = '123';
    ventana.actualizarPasosCita();
    assert.equal(paneles[1].hidden, false);
    assert.equal(submit.hidden, false);
    assert.equal(siguiente.hidden, true);
    assert.equal(submit.disabled, false);
    assert.equal(indicadores[1].getAttribute('aria-current'), 'step');
    ventana.mostrarPasoCita(0);
    assert.equal(valores.modal_mascota, '4');
    assert.equal(valores.modal_hora, '09:00:30', 'Atrás conserva el instante exacto seleccionado');
    ventana.enviosC3.reserva = true;
    ventana.avanzarPasoCita();
    assert.equal(paneles[0].hidden, false);
    assert.equal(anterior.disabled, true);
});

test('el buscador de mascota se recorre con flechas, elige con Enter y Esc cierra solo la lista', async () => {
    const { ventana, campo, lista, elegidas } = buscadorMascota();
    assert.equal(campo.getAttribute('role'), 'combobox');
    assert.equal(campo.getAttribute('aria-controls'), 'cm_mascota_dropdown');

    await ventana.filtrarMascotas('lu');
    assert.equal(lista.hidden, false, 'La lista abierta se refleja en hidden, que es lo que lee el combobox');
    assert.equal(campo.getAttribute('aria-expanded'), 'true');
    assert.equal(opciones(lista).length, 3, 'Luna, Lucas y Toby (por su propietaria Lucía)');
    assert.ok(opciones(lista).every(opcion => opcion.getAttribute('aria-selected') === 'false'));

    assert.ok(campo.disparar('keydown', { key: 'ArrowDown' }).prevenido);
    assert.equal(marcada(lista)[0].dataset.nombre, 'Luna');
    campo.disparar('keydown', { key: 'ArrowDown' });
    assert.equal(marcada(lista)[0].dataset.nombre, 'Lucas');
    assert.equal(campo.getAttribute('aria-activedescendant'), marcada(lista)[0].id);
    campo.disparar('keydown', { key: 'ArrowUp' });
    campo.disparar('keydown', { key: 'ArrowUp' });
    assert.equal(marcada(lista)[0].dataset.nombre, 'Toby', 'Arriba desde la primera vuelve a la última');
    assert.equal(marcada(lista).length, 1);

    const enter = campo.disparar('keydown', { key: 'Enter' });
    assert.ok(enter.prevenido, 'Enter no envía el formulario');
    assert.deepEqual(elegidas, [['12', 'Toby']], 'Enter elige la marcada con su mismo clic');
    assert.equal(lista.hidden, true);
    assert.equal(campo.getAttribute('aria-expanded'), 'false');
    assert.ok(campo.disparar('keydown', { key: 'Enter' }).prevenido, 'Con la lista cerrada, Enter tampoco envía');
    assert.equal(elegidas.length, 1);

    await ventana.filtrarMascotas('lu');
    campo.disparar('keydown', { key: 'ArrowDown' });
    let detenida = false;
    const esc = campo.disparar('keydown', { key: 'Escape', stopPropagation() { detenida = true; } });
    assert.ok(esc.prevenido);
    assert.ok(detenida, 'Esc no llega al documento, que cerraría el modal');
    assert.equal(lista.hidden, true);
    assert.equal(campo.getAttribute('aria-activedescendant'), '');
    assert.equal(elegidas.length, 1, 'Esc no elige nada');

    await ventana.filtrarMascotas('');
    assert.equal(lista.hidden, true, 'Sin texto la lista queda cerrada');
});

test('el clic en una mascota sigue eligiéndola', async () => {
    const { ventana, lista, elegidas } = buscadorMascota();
    await ventana.filtrarMascotas('toby');
    opciones(lista)[0].click();
    assert.deepEqual(elegidas, [['12', 'Toby']]);
    assert.equal(lista.hidden, true);
});

test('la lista se muestra según hidden y la opción marcada tiene un estilo visible', () => {
    const vista = leer('views/vet/calendario.php');
    assert.match(vista, /id="cm_mascota_dropdown" role="listbox" hidden/);
    const css = leer('public/css/calendar-modules/modals.css');
    assert.match(css, /\.zk-dropdown\[hidden\] \{ display: none; \}/);
    assert.doesNotMatch(css, /\.zk-dropdown\.is-open/);
    assert.match(css, /\.zk-option\[aria-selected="true"\] \{[^}]*background/);
    assert.doesNotMatch(leer('public/js/calendario.js'), /cm_mascota_dropdown'\)\??\.classList/);
});

test('el modal marca los obligatorios con asterisco y ya no tiene el título «Datos de la cita»', () => {
    const vista = leer('views/vet/calendario.php');
    const formulario = tramo(vista, '<form id="formCitaModal"', '</form>');
    const asterisco = '<span class="zk-requerido" aria-hidden="true">*</span>';
    assert.doesNotMatch(formulario, /Datos de la cita/);
    for (const etiqueta of ['Mascota', 'Veterinario', 'Tipo de cita', 'Horario disponible']) {
        assert.ok(formulario.includes(`${etiqueta} ${asterisco}`), `${etiqueta} lleva asterisco`);
    }
    assert.match(formulario, /Motivo <span class="zk-optional">\(opcional\)<\/span>/);
    const triage = leer('views/partials/triage_agenda.php');
    assert.match(triage, /\$grupoAgenda\('sintomas', '¿Qué le pasa\?', '[^']*', true\)/);
    assert.match(triage, /\$grupoAgenda\('exposiciones', '[^']*', '[^']*'\);/, 'Exposición sigue opcional');
    assert.match(leer('public/css/identidad.css'), /\.zk-requerido \{[^}]*color: var\(--z-danger\)/);
});

function filaTiempo() {
    const documento = crearDocumento();
    const contenedor = colgar(documento.raiz, 'div', { triageTiempos: '' });
    const marcas = [{ codigo: 'sintoma.colapso', etiqueta: 'Colapso, desmayo o no responde' }];
    const contexto = {
        document: documento,
        Option: function Option(texto, valor) { return { text: texto, value: valor }; },
        controles: { sintomas: { marcados: () => marcas }, exposiciones: { marcados: () => [] } },
        episodios: new Map(),
        aviso: { textContent: '' },
        invalidar() {},
        buscar: selector => documento.querySelector(selector),
        MINUTOS_POR: { minutos: 1, horas: 60, dias: 1440, semanas: 10080 },
    };
    vm.createContext(contexto);
    vm.runInContext(tramo(leer('public/js/triage-agenda.js'), 'function tiempos()', '\n        const nombresNivel'), contexto);
    // El select de prueba guarda las opciones y su valor como uno real.
    const crearOriginal = documento.createElement;
    documento.createElement = etiqueta => {
        const nodo = crearOriginal(etiqueta);
        if (etiqueta === 'select') nodo.add = opcion => (nodo.opciones = [...(nodo.opciones || []), opcion]);
        return nodo;
    };
    contexto.tiempos();
    return { contexto, contenedor };
}

test('cada marca del triage pone el nombre arriba (completo en title) y debajo el tiempo y los minutos', () => {
    const { contexto, contenedor } = filaTiempo();
    const [fila] = contenedor.hijos;
    assert.equal(fila.className, 'triage-captura__tiempo');
    const [nombre, controles] = fila.hijos;
    assert.equal(nombre.className, 'triage-captura__tiempo-nombre');
    assert.equal(nombre.textContent, 'Colapso, desmayo o no responde');
    assert.equal(nombre.title, 'Colapso, desmayo o no responde');
    assert.equal(controles.className, 'triage-captura__tiempo-controles');
    const [select, cantidad, unidad] = controles.hijos;
    assert.equal(select.tagName, 'SELECT');
    assert.equal(cantidad.hidden, true, 'La cantidad solo se ve con «Hace…»');
    assert.equal(unidad.hidden, true);
    assert.equal(cantidad.dataset.caracteres, '0-9');
    assert.equal(unidad.value, 'horas', 'La unidad por defecto es horas');

    select.value = 'conocido';
    select.disparar('change');
    assert.equal(cantidad.hidden, false);
    assert.equal(unidad.hidden, false);
    cantidad.value = '3';
    unidad.value = 'dias';
    cantidad.disparar('input');
    const episodio = contexto.episodios.get('sintoma.colapso');
    assert.equal(episodio.minutos, '4320', '3 días viajan como 4320 minutos');
    assert.equal(episodio.tiempo, 'conocido');
    unidad.value = 'semanas';
    unidad.disparar('change');
    assert.equal(episodio.minutos, '30240', '3 semanas');
    cantidad.value = '';
    cantidad.disparar('input');
    assert.equal(episodio.minutos, undefined, 'Sin cantidad no se inventan minutos');
});

test('la fila de tiempo es de una columna con el nombre recortado, también en el celular', () => {
    const css = leer('public/css/triage-captura.css');
    const regla = selector => (css.match(new RegExp(selector.replace(/[.[\]]/g, '\\$&') + ' \\{([^}]*)\\}')) || [])[1] || '';
    assert.match(regla('.triage-captura__tiempo'), /flex-direction: column/);
    assert.match(regla('.triage-captura__tiempo-nombre'), /text-overflow: ellipsis/);
    assert.match(regla('.triage-captura__tiempo-nombre'), /white-space: nowrap/);
    assert.match(regla('.triage-captura__tiempo-nombre'), /contain: inline-size/, 'Una línea larga no ensancha la columna del modal en el celular');
    assert.match(regla('.triage-captura__tiempo-controles'), /display: flex/);
    assert.match(regla('.triage-captura__minutos[hidden]'), /display: none/);
    assert.doesNotMatch(css, /grid-template-columns: minmax\(0, 1fr\) 150px 64px/);
});
