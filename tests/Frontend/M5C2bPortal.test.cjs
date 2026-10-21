const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const raiz = path.join(__dirname, '../..');
const codigo = (archivo) => fs.readFileSync(path.join(raiz, archivo), 'utf8');

// M5 C2b — Actualización en su sitio, Agendar con la clínica única, Clínicas
// y los formularios de mascota con avisos de una línea y restricción de entrada.

function elemento(datos = {}) {
    const clases = new Set(datos.clases || []);
    const atributos = { ...(datos.atributos || {}) };
    return {
        id: datos.id || '',
        innerHTML: datos.innerHTML || '',
        textContent: '',
        title: '',
        hidden: Boolean(datos.hidden),
        dataset: { ...(datos.dataset || {}) },
        classList: {
            add: (c) => clases.add(c),
            remove: (c) => clases.delete(c),
            toggle: (c, si) => (si ? clases.add(c) : clases.delete(c)),
            contains: (c) => clases.has(c),
        },
        tieneClase: (c) => clases.has(c),
        getAttribute: (a) => (a in atributos ? atributos[a] : null),
        setAttribute: (a, v) => { atributos[a] = String(v); },
        atributos,
        closest: datos.closest || (() => null),
        querySelector: datos.querySelector || (() => null),
        querySelectorAll: datos.querySelectorAll || (() => []),
    };
}

function documento(ids = {}) {
    const eventos = [];
    return {
        eventos,
        addEventListener() {},
        getElementById: (id) => ids[id] || null,
        querySelector: () => null,
        querySelectorAll: () => [],
        dispatchEvent: (evento) => eventos.push(evento.type),
        createElement: () => {
            let texto = '';
            return {
                set textContent(valor) { texto = valor; },
                get innerHTML() { return texto.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); },
            };
        },
    };
}

/** portal.js en un contexto con el DOM, fetch y DOMParser que da cada prueba. */
function portal({ ids = {}, html = '', ok = true } = {}) {
    const document = documento(ids);
    let recargas = 0;
    const contexto = {
        document,
        window: { addEventListener() {}, scrollTo() {}, location: { reload: () => { recargas++; } } },
        location: { hash: '' },
        history: { state: null, pushState() {}, replaceState() {} },
        console,
        CustomEvent: class { constructor(tipo) { this.type = tipo; } },
        fetch: async () => ({ ok, text: async () => html }),
        DOMParser: class {
            parseFromString(texto) {
                const partes = JSON.parse(texto || '{}');
                return {
                    getElementById: (id) => (id in partes ? {
                        innerHTML: partes[id].html || '',
                        getAttribute: (a) => (partes[id].atributos || {})[a] || null,
                    } : null),
                };
            }
        },
    };
    vm.createContext(contexto);
    vm.runInContext(codigo('public/js/avisos.js'), contexto);
    vm.runInContext(codigo('public/js/portal.js'), contexto);
    return { contexto, document, recargas: () => recargas };
}

test('aplicarPartes cambia solo las partes del portal y avisa a quien las pinta', () => {
    const inicio = elemento({ id: 'screen-home', innerHTML: 'viejo' });
    const datos = elemento({ id: 'portalAgendaDatos', atributos: { 'data-eventos': '[]' } });
    const { contexto, document } = portal({ ids: { 'screen-home': inicio, portalAgendaDatos: datos } });
    const nuevo = {
        getElementById: (id) => ({
            'screen-home': { innerHTML: 'nuevo', getAttribute: () => null },
            portalAgendaDatos: { innerHTML: '', getAttribute: () => '[{"tipo":"cita"}]' },
        }[id] || null),
    };

    contexto.aplicarPartes(nuevo);

    assert.equal(inicio.innerHTML, 'nuevo');
    assert.equal(datos.getAttribute('data-eventos'), '[{"tipo":"cita"}]');
    assert.deepEqual(document.eventos, ['portal:actualizado']);
});

test('después de una acción la pantalla se pone al día sin recargar la página', async () => {
    const citas = elemento({ id: 'citas-proximas', innerHTML: 'antes' });
    const html = JSON.stringify({ 'screen-home': { html: 'inicio' }, 'citas-proximas': { html: 'después' } });
    const { contexto, recargas } = portal({ ids: { 'citas-proximas': citas }, html });

    await contexto.refrescarPortal();

    assert.equal(citas.innerHTML, 'después');
    assert.equal(recargas(), 0);
});

test('si la respuesta no trae el portal (venció la sesión), recarga entera', async () => {
    const { contexto, recargas } = portal({ html: JSON.stringify({}) });

    await contexto.refrescarPortal();

    assert.equal(recargas(), 1);
});

test('ninguna acción del portal recarga la página por su cuenta', () => {
    const js = codigo('public/js/portal.js');
    assert.doesNotMatch(js, /zookiRecargarConAviso/);
    // Las dos recargas que quedan son el plan B de refrescarPortal().
    const cuerpo = js.slice(js.indexOf('async function refrescarPortal'), js.indexOf('/* ── Navegación entre secciones'));
    assert.equal((js.match(/location\.reload\(\)/g) || []).length, 2);
    assert.equal((cuerpo.match(/location\.reload\(\)/g) || []).length, 2);
    for (const accion of ['portal_agendar_cita_ajax', 'cancelar_cita_ajax', 'portal_vincular_clinica_ajax', 'portal_registrar_mascota_ajax', 'portal_actualizar_mascota_ajax']) {
        assert.match(js, new RegExp(accion), accion);
    }
    assert.ok((js.match(/refrescarPortal\(\);/g) || []).length >= 4, 'Agendar, cancelar, vínculos y mascota se ponen al día.');
});

test('la clínica única llega oculta y vale como elegida (RE-5.9.3)', () => {
    const { contexto } = portal();
    const oculta = { type: 'hidden', value: '1', dataset: { nombre: 'Clínica Norte' } };
    const unica = contexto.eleccion({ querySelectorAll: () => [oculta], addEventListener() {} }, 'id_clinica');
    assert.equal(unica.value, '1');
    assert.equal(unica.texto(), 'Clínica Norte');

    const norte = { type: 'radio', value: '1', checked: false, dataset: { nombre: 'Norte' } };
    const sur = { type: 'radio', value: '2', checked: true, dataset: { nombre: 'Sur' } };
    const varias = contexto.eleccion({ querySelectorAll: () => [norte, sur], addEventListener() {} }, 'id_clinica');
    assert.equal(varias.value, '2');
    assert.equal(varias.texto(), 'Sur');

    const luna = { type: 'radio', value: '7', checked: false, dataset: { nombre: 'Luna' } };
    const una = contexto.eleccion({ querySelectorAll: () => [luna], addEventListener() {} }, 'id_mascota');
    una.elegirUnica();
    assert.equal(luna.checked, true, 'Con una sola mascota ya queda elegida.');
});

test('el rechazo de Clínicas sale en la misma tarjeta y el buscador filtra sin tildes', () => {
    const aviso = elemento();
    const tarjeta = { querySelector: () => aviso };
    const { contexto } = portal();

    contexto.avisoEnTarjeta({ closest: () => tarjeta }, 'Tienes 1 cita sin resolver en esa clínica.');
    assert.equal(aviso.textContent, 'Tienes 1 cita sin resolver en esa clínica.');
    assert.equal(aviso.title, aviso.textContent, 'El texto completo, al pasar el cursor.');
    assert.equal(aviso.tieneClase('cuenta-validacion--error'), true);

    const norte = elemento({ dataset: { buscar: 'clínica norte carrera 7' } });
    const sur = elemento({ dataset: { buscar: 'clínica sur' } });
    const vacio = elemento({ hidden: true });
    const seccion = {
        querySelectorAll: () => [norte, sur],
        querySelector: () => vacio,
    };
    contexto.filtrarClinicas(seccion, 'CLINICA nor');
    assert.equal(norte.hidden, false);
    assert.equal(sur.hidden, true);
    contexto.filtrarClinicas(seccion, 'oeste');
    assert.equal(vacio.hidden, false, 'Sin coincidencias, el aviso corto.');
});

// ── validacion-mascota.js ───────────────────────────────────────────────

function validador() {
    const avisos = {};
    const document = {
        addEventListener() {},
        getElementById: (id) => avisos[id] || (avisos[id] = elemento({ id })),
    };
    const window = {};
    const contexto = vm.createContext({ document, window, console });
    vm.runInContext(codigo('public/js/validacion-mascota.js'), contexto);
    return { api: window.ZookiValidacionMascota, avisos };
}

function formulario(campos) {
    const resumen = elemento({ hidden: true });
    const form = {
        dataset: { avisoObligatorio: 'Este campo es obligatorio.' },
        elements: campos,
        querySelectorAll: (selector) => campos.filter(c => selector === `[name="${c.name}"]`),
        querySelector: (selector) => (selector === '[data-resumen-mascota]' ? resumen : campos.find(c => selector === `[name="${c.name}"]`) || null),
        resumen,
    };
    campos.forEach(c => { c.form = form; });
    return form;
}

function campo(datos) {
    return {
        type: 'text', tagName: 'INPUT', value: '', required: false, disabled: false,
        maxLength: -1, minLength: -1, pattern: '', dataset: {}, focused: false,
        setAttribute(a, v) { this[a] = v; }, focus() { this.focused = true; },
        ...datos,
    };
}

test('el nombre se valida mientras se escribe con la regla de ValidadorMascota', () => {
    const { api, avisos } = validador();
    const nombre = campo({ name: 'nombre', id: 'add_pet_nombre', required: true, maxLength: 50, pattern: "[\\p{L}\\p{N}][\\p{L}\\p{N} .'\\-]*", dataset: { avisoFormato: "Solo letras, números y . - '" } });
    formulario([nombre]);

    nombre.value = '';
    assert.equal(api.regla(nombre, false), '', 'Mientras empieza a escribir no se le exige.');
    assert.equal(api.regla(nombre, true), 'Este campo es obligatorio.');
    nombre.value = 'Toby<';
    assert.equal(api.regla(nombre, false), "Solo letras, números y . - '");
    nombre.value = "Ñoño D'Artagnan-2";
    assert.equal(api.regla(nombre, false), '');

    api.marcar(nombre, 'Mensaje');
    assert.equal(avisos.add_pet_nombre_aviso.textContent, 'Mensaje');
    assert.equal(avisos.add_pet_nombre_aviso.title, 'Mensaje');
    assert.equal(nombre['aria-invalid'], 'true');
});

test('el peso acepta coma decimal y no pasa del máximo', () => {
    const { api } = validador();
    const peso = campo({ name: 'peso', id: 'add_pet_peso', required: true, maxLength: 6, pattern: '\\d{1,3}([.,]\\d{1,2})?', dataset: { maximo: '150', avisoFormato: 'Mayor que 0 y hasta 150 kg.' } });
    formulario([peso]);

    for (const [valor, esperado] of [['8,5', ''], ['150', ''], ['151', 'Mayor que 0 y hasta 150 kg.'], ['0', 'Mayor que 0 y hasta 150 kg.'], ['8,555', 'Mayor que 0 y hasta 150 kg.']]) {
        peso.value = valor;
        assert.equal(api.regla(peso, true), esperado, valor);
    }
});

test('al enviar se marcan todos los campos y el foco va al primero que falla, sin ventanas', () => {
    const { api, avisos } = validador();
    const especie = campo({ name: 'especie', id: 'add_pet_especie', tagName: 'SELECT', required: true, dataset: { avisoFormato: 'Elige la especie.' } });
    const macho = campo({ name: 'sexo', id: 'add_pet_sexo', type: 'radio', required: true, checked: false, dataset: { avisoFormato: 'Elige macho o hembra.' } });
    const hembra = campo({ name: 'sexo', id: '', type: 'radio', required: true, checked: false, dataset: { avisoFormato: 'Elige macho o hembra.' } });
    const form = formulario([especie, macho, hembra]);

    assert.equal(api.validar(form), false);
    assert.equal(avisos.add_pet_especie_aviso.textContent, 'Elige la especie.');
    assert.equal(avisos.add_pet_sexo_aviso.textContent, 'Elige macho o hembra.', 'Un aviso para el grupo, bajo el primero.');
    assert.equal(especie.focused, true);
    assert.equal(form.resumen.hidden, true);
});

test('el rechazo del servidor va bajo su campo y el detalle al resumen', () => {
    const { api, avisos } = validador();
    const peso = campo({ name: 'peso', id: 'edit_pet_peso' });
    const form = formulario([peso]);

    api.mostrarServidor(form, { success: false, campo: 'peso', aviso: 'Mayor que 0 y hasta 150 kg.', message: 'El peso debe ser un número mayor que 0 y hasta 150 kg, con máximo dos decimales.' });

    assert.equal(avisos.edit_pet_peso_aviso.textContent, 'Mayor que 0 y hasta 150 kg.');
    assert.equal(form.resumen.hidden, false);
    assert.match(form.resumen.textContent, /con máximo dos decimales/);
    assert.equal(peso.focused, true);
});

test('la restricción de entrada del nombre no deja escribir lo que no corresponde', () => {
    const contexto = { document: { zookiInteraccionesRegistradas: true, addEventListener() {} }, console };
    vm.createContext(contexto);
    vm.runInContext(codigo('public/js/interacciones.js'), contexto);
    // Los mismos caracteres de ValidadorMascota::NOMBRE_CARACTERES y PESO_CARACTERES.
    assert.equal(contexto.limpiarCaracteres('To<by>! 2', "\\p{L}\\p{N} .'\\-", 50), 'Toby 2');
    assert.equal(contexto.limpiarCaracteres('8,5 kg', '0-9.,', 6), '8,5');
});

test('el portal no usa SweetAlert para errores de validación ni código en línea', () => {
    const js = codigo('public/js/portal.js');
    assert.doesNotMatch(js, /title: 'Revisa los datos'/);
    // M5 C4a: tampoco para confirmar; cancelar y la cita duplicada usan el modal de Zooki.
    assert.equal((js.match(/Swal\./g) || []).length, 0, 'Sin SweetAlert en el portal (los toasts van por zookiToast).');
    // C4: entre validacion-mascota y portal.js se cargan la captura de síntomas y el triage; portal.js los usa.
    const layout = codigo('views/portal/layout.php');
    const pos = (js) => layout.indexOf(`<script src="js/${js}?v=<?= $v ?>"></script>`);
    for (const previo of ['validacion-mascota.js', 'consulta-sintomas.js', 'triage-agenda.js']) {
        assert.ok(pos(previo) > -1 && pos(previo) < pos('portal.js'), previo + ' antes de portal.js');
    }
    for (const archivo of ['views/portal/index.php', 'views/portal/layout.php']) {
        assert.doesNotMatch(codigo(archivo), /\sstyle="|\son[a-z]+="|<script>\s*\S/, archivo);
    }
});
