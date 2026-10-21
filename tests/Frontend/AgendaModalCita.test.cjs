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
    { id: 4, nombre: 'Luna', propietario: 'Ana Pérez' },
    { id: 9, nombre: 'Lucas', propietario: 'Juan Gil' },
    { id: 12, nombre: 'Toby', propietario: 'Lucía Mora' },
];

/** La lista recibe el HTML de calendario.js; aquí se convierte en nodos opción. */
function listaConHtml(documento, padre) {
    const lista = colgar(padre, 'div', {}, { id: 'cm_mascota_dropdown', hidden: true });
    Object.defineProperty(lista, 'innerHTML', {
        set(html) {
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
    const [select, minutos] = controles.hijos;
    assert.equal(select.tagName, 'SELECT');
    assert.equal(minutos.hidden, true, 'Los minutos solo se ven con «Hace (minutos)»');
    assert.equal(minutos.dataset.caracteres, '0-9.');

    select.value = 'conocido';
    select.disparar('change');
    assert.equal(minutos.hidden, false);
    minutos.value = '5';
    minutos.disparar('input');
    const enviado = JSON.parse(JSON.stringify(contexto.episodios.get('sintoma.colapso')));
    assert.deepEqual(enviado, { codigo: 'sintoma.colapso', tiempo: 'conocido', minutos: '5', grupo: 'sintomas' });
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
