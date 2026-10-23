/**
 * Inicio del veterinario (HU-6.1, RE-6.1.5 a RE-6.1.11) y sus enlaces a la agenda.
 * - Tocar un día o cambiar de mes reemplaza «Tu día» o el calendario con el HTML
 *   de panel_dia_ajax; una respuesta vieja no pisa a la nueva.
 * - «Registrar llegada» usa la acción de Llegadas; pasada la tolerancia lleva allá.
 * - La agenda abre la pestaña del hash y el modal de agendar con fecha y hora.
 * - Decisión del dueño (2026-10-22): «Consultas médicas» ya no está.
 */
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const { crearDocumento, colgar } = require('./apoyo/dom-datos.cjs');

const RAIZ = path.join(__dirname, '../..');
const leer = archivo => fs.readFileSync(path.join(RAIZ, archivo), 'utf8').replace(/\r\n/g, '\n');
/** Clic que sube por los ancestros, como en el navegador: el Inicio delega sus eventos. */
const tocar = nodo => {
    for (let actual = nodo; actual; actual = actual.padre) (actual.escuchas.click || []).forEach(fn => fn({ target: nodo, preventDefault() {} }));
};
const esperar = async () => { for (let i = 0; i < 4; i++) await new Promise(resolver => setImmediate(resolver)); };
const tramo = (fuente, desde, hasta) => {
    const inicio = fuente.indexOf(desde);
    const fin = fuente.indexOf(hasta, inicio);
    assert.ok(inicio >= 0 && fin > inicio, `No se encontró ${desde}`);
    return fuente.slice(inicio, fin);
};

/** El Inicio con el DOM mínimo: calendario, «Tu día», la tarjeta del siguiente y el diálogo de dosis. */
function inicio(responder) {
    const documento = crearDocumento();
    const raiz = colgar(documento.raiz, 'div', { inicio: '', clinica: '1' });
    const calendario = colgar(raiz, 'section', { inicioCalendario: '', elegido: '2026-10-21' });
    const contenidoCalendario = colgar(calendario, 'div', { inicioCalendarioContenido: '' });
    const dias = ['2026-10-21', '2026-10-22', '2026-10-23'].map(fecha => colgar(contenidoCalendario, 'button', { inicioFecha: fecha }));
    dias.forEach(d => d.setAttribute('aria-pressed', 'false'));
    const flecha = colgar(contenidoCalendario, 'button', { inicioMes: '2026-11' });
    const dia = colgar(raiz, 'section', { inicioDia: '' });
    const contenidoDia = colgar(dia, 'div', { inicioDiaContenido: '' });
    const llegada = colgar(raiz, 'button', { inicioLlegada: '7' });
    const abrir = colgar(raiz, 'button', { inicioAbrir: 'inicioRecordatorios' });
    const dialogo = colgar(raiz, 'dialog', { inicioDialogo: '' }, { id: 'inicioRecordatorios', open: false });
    dialogo.showModal = () => { dialogo.open = true; };
    dialogo.close = () => { dialogo.open = false; };
    const cerrar = colgar(dialogo, 'button', { inicioCerrar: '' });
    const avatar = colgar(raiz, 'span', { iniciales: 'NA', color: 'inicio-ava--1' }, { className: 'inicio-ava inicio-ava--foto' });
    // La foto de Nala ya falló antes de que corriera el script.
    colgar(avatar, 'img', { inicioFoto: '' }, { complete: true, naturalWidth: 0, parentElement: avatar });
    const llamadas = { fetch: [], avisos: [], recargas: 0 };
    const contexto = {
        console, URLSearchParams, FormData: class { constructor() { this.datos = new Map(); } set(k, v) { this.datos.set(k, String(v)); } entries() { return this.datos.entries(); } get(k) { return this.datos.get(k); } },
        document: documento, module: { exports: {} },
        location: { href: 'index.php?action=vet_area', reload: () => { llamadas.recargas++; } },
        zookiAviso: texto => llamadas.avisos.push(texto), zookiToast: () => {},
        fetch: (url, opciones) => { llamadas.fetch.push([url, opciones]); return responder(url, opciones); },
    };
    contexto.zookiCrearComandoAgenda = () => ({ clave: () => 'a'.repeat(64) });
    vm.createContext(contexto);
    vm.runInContext(leer('public/js/inicio-vet.js'), contexto);
    contexto.module.exports.iniciar(raiz);
    return { contexto, raiz, dias, flecha, dia, contenidoDia, contenidoCalendario, llegada, abrir, dialogo, cerrar, avatar, llamadas };
}

const json = datos => Promise.resolve({ json: async () => datos });

test('tocar un día pide panel_dia_ajax y pinta «Tu día» con el HTML del servidor', async () => {
    const i = inicio(url => json({ success: true, html: `<p>${new URL(url, 'https://zooki.test').searchParams.get('fecha')}</p>` }));
    tocar(i.dias[1]);
    assert.equal(i.dia.getAttribute('aria-busy'), 'true');
    await esperar();
    assert.equal(i.llamadas.fetch[0][0], 'index.php?action=panel_dia_ajax&fecha=2026-10-22');
    assert.equal(i.contenidoDia.innerHTML, '<p>2026-10-22</p>');
    assert.equal(i.dia.getAttribute('aria-busy'), 'false');
    assert.deepEqual(i.dias.map(d => d.getAttribute('aria-pressed')), ['false', 'true', 'false']);
    assert.ok(i.dias[1].classList.contains('is-elegido'));
});

test('una respuesta vieja no pisa al día que se tocó después', async () => {
    const pendientes = [];
    const i = inicio(url => new Promise(resolver => pendientes.push(() => resolver({ json: async () => ({ success: true, html: url }) }))));
    tocar(i.dias[1]);
    tocar(i.dias[2]);
    pendientes[1]();
    await esperar();
    pendientes[0]();
    await esperar();
    assert.match(i.contenidoDia.innerHTML, /fecha=2026-10-23$/);
});

test('cambiar de mes reemplaza el calendario y conserva el día elegido; un error avisa sin borrar', async () => {
    let respuesta = { success: true, html: '<p>Noviembre</p>' };
    const i = inicio(() => json(respuesta));
    tocar(i.dias[1]);
    await esperar();
    tocar(i.flecha);
    await esperar();
    assert.equal(i.llamadas.fetch[1][0], 'index.php?action=panel_dia_ajax&mes=2026-11&elegido=2026-10-22');
    assert.equal(i.contenidoCalendario.innerHTML, '<p>Noviembre</p>');

    respuesta = { success: false, message: 'La fecha no es válida.' };
    const antes = i.contenidoDia.innerHTML;
    tocar(i.dias[0]);
    await esperar();
    assert.equal(i.contenidoDia.innerHTML, antes);
    assert.deepEqual(i.llamadas.avisos, ['No se pudo cargar ese día. Intenta nuevamente.']);
});

test('«Registrar llegada» usa la acción de Llegadas; pasada la tolerancia lleva a decidir allá (RN-426)', async () => {
    let tolerancia = true;
    const i = inicio(() => json({ success: true, resultado: { opciones: { dentro_tolerancia: tolerancia } } }));
    tocar(i.llegada);
    await esperar();
    const [url, opciones] = i.llamadas.fetch[0];
    assert.equal(url, 'index.php?action=registrar_llegada_ajax');
    assert.equal(opciones.method, 'POST');
    assert.deepEqual(Object.fromEntries(opciones.body.entries()), { id_cita: '7', id_clinica: '1', clave_operacion: 'a'.repeat(64) });
    assert.equal(i.llamadas.recargas, 1, 'Dentro de la tolerancia la tarjeta ofrece iniciar');

    tolerancia = false;
    i.llegada.disabled = false;
    tocar(i.llegada);
    await esperar();
    assert.equal(i.contexto.location.href, 'index.php?action=vet_agenda#llegadas');
});

test('las próximas dosis se abren y se cierran en su ventana; una foto que no está muestra iniciales', () => {
    const i = inicio(() => json({}));
    tocar(i.abrir);
    assert.equal(i.dialogo.open, true);
    tocar(i.cerrar);
    assert.equal(i.dialogo.open, false);
    assert.equal(i.avatar.textContent, 'NA');
    assert.ok(i.avatar.classList.contains('inicio-ava--1'));
    assert.ok(!i.avatar.classList.contains('inicio-ava--foto'));
});

test('RN-407: el botón de esperar se habilita al llegar su hora', () => {
    const i = inicio(() => json({}));
    const listo = colgar(i.raiz, 'button', { iniciar: '3', desde: '2000-01-01T08:00:00-05:00' }, { disabled: true, title: 'x' });
    const futuro = colgar(i.raiz, 'button', { iniciar: '4', desde: '2999-01-01T08:00:00-05:00' }, { disabled: true });
    listo.removeAttribute = futuro.removeAttribute = () => {};
    i.contexto.module.exports.habilitarBotonesListos(i.raiz);
    assert.equal(listo.disabled, false);
    assert.equal(listo.textContent, 'Iniciar atención');
    assert.equal(futuro.disabled, true);
});

test('la agenda abre la pestaña que pide el hash del Inicio', () => {
    const { iniciarHerramientasAgenda } = require(path.join(RAIZ, 'public/js/agenda-herramientas.js'));
    const documento = crearDocumento();
    documento.defaultView = { location: { hash: '#llegadas' } };
    const raiz = colgar(documento.raiz, 'section', { agendaHerramientas: '' });
    raiz.scrollIntoView = () => { raiz.desplazada = true; };
    const pestanas = ['triage', 'llegadas', 'reasignaciones'].map((clave, n) => {
        const tab = colgar(raiz, 'button', { agendaTab: clave }, { tabIndex: n ? -1 : 0 });
        tab.setAttribute('aria-selected', n ? 'false' : 'true');
        tab.setAttribute('aria-controls', 'panel-' + clave);
        colgar(raiz, 'section', {}, { id: 'panel-' + clave });
        return tab;
    });
    raiz.querySelector = selector => raiz.todos().find(n => '#' + n.id === selector) || null;
    iniciarHerramientasAgenda(documento);
    assert.deepEqual(pestanas.map(t => t.getAttribute('aria-selected')), ['false', 'true', 'false']);
    assert.equal(raiz.desplazada, true);
});

test('«+ Agendar» abre el modal de la agenda en ese día y marca la hora si sigue libre', () => {
    const fuente = leer('public/js/calendario.js');
    const abiertos = [];
    const clics = [];
    const chip = hora => ({ dataset: { hora }, click: () => clics.push(hora), scrollIntoView() {}, closest: () => ({ dataset: { franja: 'manana' } }) });
    const reemplazos = [];
    const contexto = {
        URLSearchParams, Array,
        window: { location: { search: '?action=vet_agenda&agendar=2030-01-07&hora=10:00', pathname: '/index.php', hash: '' }, history: { replaceState: (_, __, url) => reemplazos.push(url) } },
        isUsuarioVeterinario: () => true,
        abrirCitaModal: fecha => abiertos.push(fecha),
        document: { querySelectorAll: () => [chip('09:00:30'), chip('10:00:00')], querySelector: () => ({ click: () => clics.push('tab') }) },
    };
    vm.createContext(contexto);
    vm.runInContext(tramo(fuente, 'let _horaPreferidaCita = null;', '// La lista de mascotas'), contexto);
    contexto.abrirAgendarDesdeEnlace();
    assert.equal(abiertos.length, 1);
    assert.deepEqual([abiertos[0].getFullYear(), abiertos[0].getMonth(), abiertos[0].getDate()], [2030, 0, 7]);
    assert.deepEqual(reemplazos, ['/index.php?action=vet_agenda'], 'Al recargar no se vuelve a abrir');
    contexto.marcarHoraPreferida('modal_slots_container');
    assert.deepEqual(clics, ['tab', '10:00:00']);
    contexto.marcarHoraPreferida('modal_slots_container');
    assert.equal(clics.length, 2, 'Solo la primera vez que aparecen los horarios');

    contexto.window.location.search = '?action=vet_agenda&agendar=<script>';
    contexto.abrirAgendarDesdeEnlace();
    assert.equal(abiertos.length, 1, 'Una fecha mal formada no abre nada');
});

test('el Inicio no usa estilos ni scripts en línea, carga su hoja propia y ya no enlaza a Consultas', () => {
    const vistas = ['views/vet/area.php', 'views/vet/partials/inicio_dia.php', 'views/vet/partials/inicio_calendario.php'];
    for (const vista of vistas) {
        assert.doesNotMatch(leer(vista), /\sstyle=|\son[a-z]+=|<script/i, vista);
    }
    const layout = leer('views/vet/layout.php');
    assert.match(layout, /<\?php if \(!isset\(\$content_view\)\): \?>\n.*\n    <link rel="stylesheet" href="css\/inicio-vet\.css/);
    assert.match(layout, /js\/inicio-vet\.js/);
    assert.doesNotMatch(layout, /vet_consultas|consultas\.js|panel-vet\.js/);
    assert.doesNotMatch(leer('public/index.php'), /case "vet_consultas"/);
    assert.ok(!fs.existsSync(path.join(RAIZ, 'views/vet/consultas.php')));
    assert.match(leer('views/vet/area.php'), /href="index\.php\?action=vet_agenda#llegadas"/, 'HU-2.2: la atención sin cita se registra como llegada');
    const css = leer('public/css/inicio-vet.css');
    assert.match(css, /grid-template-columns: minmax\(0, 2fr\) minmax\(0, 1\.05fr\) minmax\(0, 0\.95fr\);/);
    assert.match(css, /height: 258px;/);
    assert.match(css, /"siguiente"\s+"lado"\s+"dia"\s+"pendientes"\s+"calendario"\s+"recientes"/, 'RNF-16: orden del celular');
});
