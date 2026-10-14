const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const { crearDocumento } = require('./apoyo/dom-minimo.cjs');

const raiz = path.join(__dirname, '../..');
const codigo = (archivo) => fs.readFileSync(path.join(raiz, archivo), 'utf8');

// M5 C4a — Pulido del portal: Citas con lista y calendario del mes, «Próximo
// en salud» solo desde tableta, confirmaciones y formularios en los modales de
// Zooki (sin SweetAlert) y el teléfono con bandera en tres formularios.

/** portal.js con un documento que guarda sus escuchas para dispararlas en la prueba. */
function portal(extra = {}) {
    const escuchas = {};
    const document = {
        addEventListener(tipo, callback) { (escuchas[tipo] = escuchas[tipo] || []).push(callback); },
        getElementById: () => null,
        querySelector: () => null,
        querySelectorAll: () => [],
        createElement: () => {
            let texto = '';
            return {
                set textContent(valor) { texto = String(valor); },
                get innerHTML() { return texto.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); },
            };
        },
        ...extra.document,
    };
    const contexto = {
        console,
        document,
        window: { addEventListener() {}, scrollTo() {} },
        location: { hash: '' },
        history: { state: null, back() {}, pushState() {}, replaceState() {} },
        CustomEvent: class { constructor(tipo, opciones = {}) { this.type = tipo; this.detail = opciones.detail; } },
        ...extra.contexto,
    };
    vm.createContext(contexto);
    vm.runInContext(codigo('public/js/avisos.js'), contexto);
    vm.runInContext(codigo('public/js/portal.js'), contexto);
    const disparar = (tipo, evento) => (escuchas[tipo] || []).forEach(callback => callback(evento));
    return { contexto, disparar };
}

const EVENTOS = [
    { tipo: 'cita', id_cita: 7, id_mascota: 1, nombre_mascota: 'Luna', titulo: 'Control', detalle: 'Beto · Norte', fecha: '2030-01-14', hora: '09:00', estado: 'pendiente', proxima: null },
    { tipo: 'vacuna', id_mascota: 1, nombre_mascota: 'Luna', titulo: 'Rabia', detalle: 'Sur', fecha: '2029-01-14', hora: null, estado: 'aplicada', proxima: '2030-01-14' },
    { tipo: 'control', id_mascota: 2, nombre_mascota: 'Toby', titulo: 'Drontal', detalle: 'Interna', fecha: '2030-01-14', hora: null, estado: 'aplicado', proxima: null },
    { tipo: 'cita', id_cita: 8, id_mascota: 2, nombre_mascota: 'Toby', titulo: 'Baño', detalle: 'Norte', fecha: '2030-01-15', hora: '10:00', estado: 'pendiente', proxima: null },
];

test('el día elegido trae sus citas, sus aplicaciones y las dosis que tocan ese día, de la mascota elegida', () => {
    const { contexto } = portal();

    const todas = contexto.eventosDelDia(EVENTOS, '2030-01-14');
    assert.deepEqual(todas.map(e => e.titulo), ['Control', 'Rabia', 'Drontal'], 'La Rabia entra por su próxima dosis.');
    assert.deepEqual(contexto.eventosDelDia(EVENTOS, '2030-01-14', '2').map(e => e.titulo), ['Drontal']);
    assert.deepEqual(contexto.eventosDelDia(EVENTOS, '2030-01-14', 1).map(e => e.titulo), ['Control', 'Rabia'], 'El id llega como texto o número.');
    assert.equal(contexto.eventosDelDia(EVENTOS, '2030-02-01').length, 0);
    assert.match(contexto.etiquetaDelDia('2030-01-14'), /^[A-ZÁÉÍÓÚ].*14.*enero/i);
});

test('cada fila dice qué es: la cita abre su detalle y la vacuna es «próxima dosis» el día que toca', () => {
    const { contexto } = portal();

    const cita = contexto.filaEventoDeSalud(EVENTOS[0], '2030-01-14');
    assert.match(cita, /data-ui-accion="detalle-cita-portal" data-ui-id="7"/);
    const dosis = contexto.filaEventoDeSalud(EVENTOS[1], '2030-01-14');
    assert.match(dosis, /Próxima dosis: <strong>Rabia<\/strong>/);
    const aplicada = contexto.filaEventoDeSalud(EVENTOS[1], '2029-01-14');
    assert.match(aplicada, /Vacuna aplicada/);
    const conHtml = contexto.filaEventoDeSalud({ ...EVENTOS[2], nombre_mascota: '<b>Toby</b>' }, '2030-01-14');
    assert.doesNotMatch(conHtml, /<b>Toby/, 'El nombre va escapado.');
});

test('Citas es una sola vista: la lista y, al lado, el calendario del mes, que en celular no se muestra', () => {
    const vista = codigo('views/portal/index.php');
    const citas = vista.slice(vista.indexOf('id="screen-citas"'), vista.indexOf('id="portalAgendaDatos"'));
    assert.match(citas, /class="citas-vista" data-citas-vista/);
    assert.match(citas, /<aside class="citas-vista__calendario"[^>]*>[\s\S]*id="miniCalendarGrid"/);
    assert.ok(citas.indexOf('id="citas-proximas"') < citas.indexOf('class="citas-vista__calendario"'), 'La lista a la izquierda.');
    assert.match(citas, /id="citasDiaElegido" class="citas-dia" hidden/);
    assert.doesNotMatch(citas, /calendarFilterAlert/);

    const css = codigo('public/css/portal/agenda.css');
    assert.match(css, /\.citas-vista__calendario \{ display: none; \}/, 'Celular: sin el mes (decisión 1).');
    const tableta = css.slice(css.search(/@media \(min-width: 768px\) \{\s*\.citas-vista \{/));
    assert.match(tableta, /\.citas-vista__calendario \{\s*display: block;/);
    assert.match(css, /\.citas-vista--dia \[data-pestanas="citas"\],\s*\.citas-vista--dia \[role="tabpanel"\] \{\s*display: none;/);

    const js = codigo('public/js/portal.js');
    assert.match(js, /vista\.classList\.toggle\('citas-vista--dia', hayDia\)/);
    assert.doesNotMatch(js, /calendarFilterAlert/);
});

test('«Próximo en salud» solo se ve desde tableta; en celular queda oculto', () => {
    const css = codigo('public/css/portal/inicio.css');
    assert.match(css, /\.portal-salud \{ display: none; \}/);
    const tableta = css.slice(css.indexOf('/* ── Tableta ── */'), css.indexOf('/* Tableta: el horario'));
    assert.match(tableta, /@media \(min-width: 768px\)[\s\S]*\.portal-salud \{\s*display: flex;/);
    assert.match(codigo('views/portal/index.php'), /<section class="portal-salud" aria-labelledby="tituloSalud" data-proximo-salud>/);
});

test('cancelar una cita confirma en el modal de Zooki con «Volver», y si no se confirma no se envía nada', async () => {
    const pedidos = [];
    const preguntas = [];
    const fila = {
        querySelector: (selector) => ({ '.portal-cita__cuando strong': { textContent: 'Luna' }, '.portal-cita__cuando span': { textContent: 'Hoy · 8:00 a. m.' } })[selector],
    };
    const { contexto } = portal({
        document: { querySelector: (selector) => (selector === '.portal-cita[data-ui-id="7"]' ? fila : null) },
        contexto: { fetch: async (url) => { pedidos.push(url); return { json: async () => ({ success: true }) }; } },
    });
    contexto.zookiConfirmarCorto = async (...argumentos) => { preguntas.push(argumentos); return false; };

    await contexto.cancelarCitaPortal(7);

    assert.equal(pedidos.length, 0);
    assert.deepEqual(JSON.parse(JSON.stringify(preguntas[0])), ['¿Cancelar esta cita?', 'Cancelar cita', [['Mascota', 'Luna'], ['Día y hora', 'Hoy · 8:00 a. m.']], 'Volver']);
});

test('la confirmación de Zooki: Escape y el fondo cancelan, el foco no sale y vuelve a quien la abrió', async () => {
    const document = crearDocumento();
    const contexto = { console, window: {}, document };
    vm.createContext(contexto);
    vm.runInContext(codigo('public/js/avisos.js'), contexto);
    const origen = document.createElement('button');
    origen.focus();

    const primera = contexto.zookiConfirmarCorto('¿Cancelar esta cita?', 'Cancelar cita', [], 'Volver');
    const panel = document.body.hijos[0].hijos[0];
    assert.equal(panel.getAttribute('role'), 'alertdialog');
    assert.equal(panel.getAttribute('aria-modal'), 'true');
    assert.equal(document.activeElement.textContent, 'Cancelar cita', 'El foco entra a la ventana.');
    document.tecla('Tab');
    assert.equal(document.activeElement.textContent, 'Volver');
    document.tecla('Tab');
    assert.equal(document.activeElement.textContent, 'Cancelar cita', 'Tab no sale de la ventana.');
    const escape = document.tecla('Escape');
    assert.equal(await primera, false);
    assert.equal(escape.detenido, true, 'No cierra también la ventana de abajo.');
    assert.equal(document.body.hijos.length, 0);
    assert.equal(document.activeElement, origen, 'El foco vuelve al botón de origen.');
    assert.equal(document.escuchas.filter(e => e.tipo === 'keydown').length, 0, 'No deja el teclado escuchando.');

    const segunda = contexto.zookiConfirmarCorto('¿Agendar esta cita?', 'Agendar');
    document.body.hijos[0].click();
    assert.equal(await segunda, false, 'Tocar el fondo cancela.');
});

test('los modales del Perfil abren con su botón y se cierran al guardar, con el aviso breve', () => {
    const avisos = [];
    const atras = [];
    const { contexto, disparar } = portal();
    contexto.zookiToast = (mensaje, tipo) => avisos.push([mensaje, tipo]);
    contexto.history.state = { zkModal: 'portalCorreoModal' };
    contexto.history.back = () => atras.push('atras');
    const resultado = { textContent: 'Te enviamos un enlace al correo nuevo.' };
    const ventana = { id: 'portalCorreoModal' };
    const form = { closest: (selector) => (selector === '.portal-drawer-overlay' ? ventana : null), querySelector: () => resultado };

    disparar('cuenta:guardada', { target: form, detail: { message: 'Te enviamos un enlace al correo nuevo.' } });

    assert.deepEqual(atras, ['atras'], 'Cierra su ventana (deshace la entrada del historial).');
    assert.deepEqual(avisos, [['Te enviamos un enlace al correo nuevo.', 'success']]);
    assert.equal(resultado.textContent, '');

    const otra = { closest: () => ({ id: 'portalBookingModal' }), querySelector: () => null };
    disparar('cuenta:guardada', { target: otra, detail: { message: 'x' } });
    assert.equal(atras.length, 1, 'Solo cierra los modales del Perfil.');

    const vista = codigo('views/portal/index.php');
    for (const modal of ['portalCorreoModal', 'portalDocumentoModal', 'portalPasswordModal']) {
        assert.match(vista, new RegExp(`data-abrir-modal="${modal}"`));
        assert.match(vista, new RegExp(`<div id="${modal}" class="portal-drawer-overlay">\\s*<div class="portal-drawer portal-drawer--completa"`));
    }
    assert.match(codigo('public/js/validacion-cuenta.js'), /form\.dispatchEvent\(new CustomEvent\('cuenta:guardada'/);
});

test('el portal no usa SweetAlert para confirmar ni para formularios', () => {
    const js = codigo('public/js/portal.js');
    assert.doesNotMatch(js, /Swal\./);
    assert.doesNotMatch(js, /showTikTokModal/);
    assert.match(js, /zookiConfirmarCorto\(res\.message \|\| '¿Agendar otra cita el mismo día\?', 'Agendar igual', \[\], 'Volver'\)/);
    assert.doesNotMatch(codigo('public/js/avisos.js').slice(codigo('public/js/avisos.js').indexOf('function zookiConfirmarCorto')), /Swal\.fire/);
});

/** telefono-internacional.js con una biblioteca falsa que guarda cómo se la llama. */
function telefono(numeroValido) {
    const llamadas = [];
    let alCargar = () => {};
    const envio = [];
    const form = { addEventListener(tipo, callback, captura) { envio.push({ tipo, callback, captura }); } };
    const campo = { value: '300 123 4567', form };
    const contexto = {
        console,
        document: {
            body: {},
            addEventListener: (tipo, callback) => { alCargar = callback; },
            querySelectorAll: (selector) => (selector === 'input[data-telefono-internacional]' ? [campo] : []),
        },
        window: {
            intlTelInput(elemento, opciones) {
                llamadas.push(opciones);
                return { isValidNumber: () => numeroValido, getNumber: () => '+573001234567' };
            },
        },
    };
    vm.createContext(contexto);
    vm.runInContext(codigo('public/js/telefono-internacional.js'), contexto);
    alCargar();
    return { contexto, llamadas, campo, envio };
}

test('el teléfono con bandera se prepara solo, una vez, y al enviar lleva el número con su prefijo', () => {
    const { contexto, llamadas, campo, envio } = telefono(true);

    assert.equal(llamadas.length, 1);
    assert.equal(llamadas[0].initialCountry, 'co');
    assert.equal(llamadas[0].strictMode, true);
    assert.equal(contexto.window.zookiTelefono(campo), campo.zookiTelefono, 'Una sola instancia por campo.');
    assert.equal(llamadas.length, 1);
    assert.equal(envio[0].tipo, 'submit');
    assert.equal(envio[0].captura, true, 'Antes de que la validación lea el valor.');
    envio[0].callback();
    assert.equal(campo.value, '+573001234567');
});

test('si el número no es válido para la bandera, se envía lo escrito y el servidor decide', () => {
    const { contexto, campo } = telefono(false);
    assert.equal(contexto.window.zookiNumeroTelefono(campo), '300 123 4567');
    assert.equal(contexto.window.zookiNumeroTelefono(null), '');
});

test('el teléfono con bandera está en el registro, «Completar perfil», el Perfil del portal, Usuarios y Pacientes', () => {
    const marcados = {
        'views/auth/login.php': 'id="telefono"',
        'views/auth/completar_perfil.php': 'id="perfilTelefono"',
        'views/portal/index.php': 'id="portal_contact_phone"',
    };
    for (const [archivo, campo] of Object.entries(marcados)) {
        const vista = codigo(archivo);
        const linea = vista.split('\n').find(l => l.includes(campo));
        assert.match(linea, /data-telefono-internacional/, archivo);
        assert.match(linea, /data-caracteres=|ValidadorTelefono::atributosHtml\(\)/, `${archivo}: la regla del servidor no cambia.`);
    }
    assert.match(codigo('views/auth/login.php'), /intlTelInput\.min\.js"><\/script>\s*<script src="js\/telefono-internacional\.js/);
    assert.match(codigo('views/auth/completar_perfil.php'), /intlTelInput\.min\.js"><\/script>\s*<script src="js\/telefono-internacional\.js/);
    assert.match(codigo('views/portal/layout.php'), /intlTelInput\.min\.js"><\/script>\s*<script src="js\/telefono-internacional\.js/);
    assert.match(codigo('views/partials/cabecera_acceso.php'), /\$accesoTelefono[\s\S]*intlTelInput\.css/);
    for (const archivo of ['public/js/usuarios.js', 'public/js/medical-module.js']) {
        const js = codigo(archivo);
        assert.match(js, /zookiTelefono\(/, archivo);
        assert.doesNotMatch(js, /window\.intlTelInput\(/, `${archivo}: sin repetir la configuración.`);
    }
});

/** validacion-cuenta.js sin formularios: solo sus reglas. */
function reglasDeCuenta() {
    const window = {};
    vm.runInNewContext(codigo('public/js/validacion-cuenta.js'), {
        window, document: { addEventListener() {}, querySelector: () => null }, WeakMap, Map, Set, RegExp,
    });
    return window.ZookiValidacionCuenta;
}

test('el correo dice qué le falta, con los mismos mensajes que ValidadorCuenta::correo', () => {
    const { motivoCorreo } = reglasDeCuenta();
    // Los mismos casos de tests/Unit/ValidadorCorreoTest.php.
    const casos = {
        'fabio': 'Al correo le falta la @.',
        'fabio@': 'Escribe el dominio completo, por ejemplo gmail.com.',
        '@gmail.com': 'Escribe lo que va antes de la @.',
        'a@b@c.com': 'El correo lleva una sola @.',
        'fabio@gmail': 'Escribe el dominio completo, por ejemplo gmail.com.',
        'fabio@gmail.': 'Escribe el dominio completo, por ejemplo gmail.com.',
        'fabio@.com': 'Escribe el dominio completo, por ejemplo gmail.com.',
        'fabio@gmail.com': null,
    };
    for (const [correo, esperado] of Object.entries(casos)) assert.equal(motivoCorreo(correo), esperado, correo);
    const php = codigo('helpers/ValidadorCuenta.php');
    for (const mensaje of new Set(Object.values(casos).filter(Boolean))) assert.ok(php.includes(`'${mensaje}'`), mensaje);
});

test('en «Cambiar correo», escribir el correo actual avisa «Ese ya es tu correo» sin ir al servidor', () => {
    const { reglaLocal } = reglasDeCuenta();
    const campo = { name: 'email', value: 'Fabio@Zooki.test', required: true, dataset: { correoActual: 'fabio@zooki.test' } };
    assert.deepEqual(JSON.parse(JSON.stringify(reglaLocal({}, campo, false))), { error: 'Ese ya es tu correo.' });
    campo.value = 'otro@zooki.test';
    assert.equal(reglaLocal({}, campo, false), null);
    assert.match(codigo('views/partials/cambios_identidad.php'), /data-correo-actual="' \. \$eIdentidad\(\$cuentaIdentidad\['email'\]/);
});

test('cada contraseña del Perfil tiene el ojito para verla, como en el login', () => {
    const vista = codigo('views/portal/index.php');
    for (const id of ['portal_current_password', 'portal_new_password', 'portal_confirm_password']) {
        assert.match(vista, new RegExp(`id="${id}"[^>]*>\\s*<button type="button" class="portal-ojito" data-ojito="${id}"`), id);
    }
    assert.match(codigo('views/partials/cambios_identidad.php'), /\$enPortal \? '<button type="button" class="portal-ojito" data-ojito="' \. \$prefijo \. 'Clave"/);
    assert.match(codigo('public/js/cuenta-password.js'), /querySelectorAll\('\[data-ojito\], \.toggle-password'\)/, 'Lo atiende el mismo código del login.');
    assert.match(codigo('public/css/portal/base.css'), /\.portal-ojito\[aria-pressed="true"\] \.portal-ojito__ocultar \{ display: inline; \}/);
});

test('las pantallas tocadas no tienen JS ni CSS en línea', () => {
    for (const archivo of ['views/portal/index.php', 'views/portal/layout.php', 'views/auth/completar_perfil.php', 'views/partials/cambios_identidad.php']) {
        assert.doesNotMatch(codigo(archivo), /\sstyle="|\son[a-z]+="|<script>\s*\S/, archivo);
    }
});
