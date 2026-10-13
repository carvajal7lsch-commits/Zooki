const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const raiz = path.join(__dirname, '../..');
const codigo = (archivo) => fs.readFileSync(path.join(raiz, archivo), 'utf8');

// M5 C3 — Lado del personal: confirmación corta, búsqueda del propietario en
// tiempo real, tarjetas de vincular sin recargar, solo lectura en la historia
// y Agendar del portal en un solo formulario con su confirmación corta.

/** Un nodo con lo que usan propietario-mascota.js y medical-module.js. */
function nodo(etiqueta = 'div') {
    const atributos = {};
    const clases = new Set();
    const n = {
        tagName: etiqueta.toUpperCase(),
        hijos: [],
        padre: null,
        textContent: '',
        hidden: false,
        disabled: false,
        dataset: {},
        type: '',
        src: '',
        alt: '',
        value: '',
        escuchas: {},
        get className() { return [...clases].join(' '); },
        set className(valor) { clases.clear(); String(valor).split(/\s+/).filter(Boolean).forEach(c => clases.add(c)); },
        classList: {
            add: (c) => clases.add(c),
            remove: (c) => clases.delete(c),
            toggle: (c, si) => (si ? clases.add(c) : clases.delete(c)),
            contains: (c) => clases.has(c),
        },
        setAttribute: (a, v) => { atributos[a] = String(v); },
        getAttribute: (a) => (a in atributos ? atributos[a] : null),
        addEventListener(tipo, fn) { (n.escuchas[tipo] = n.escuchas[tipo] || []).push(fn); },
        append(...hijos) { hijos.forEach(h => { h.padre = n; n.hijos.push(h); }); },
        prepend(h) { h.padre = n; n.hijos.unshift(h); },
        replaceChildren(...hijos) { n.hijos = []; n.append(...hijos); },
        remove() { if (n.padre) n.padre.hijos = n.padre.hijos.filter(h => h !== n); },
        replaceWith(otro) {
            const lista = n.padre.hijos;
            otro.padre = n.padre;
            lista[lista.indexOf(n)] = otro;
        },
        focus() { n.enfocado = true; },
        closest(selector) {
            let actual = n;
            while (actual) {
                if (selector === 'button' && actual.tagName === 'BUTTON') return actual;
                actual = actual.padre;
            }
            return null;
        },
        matches: () => false,
        querySelector(selector) {
            return todos(n).find(h => selector.includes('aviso-solo-lectura') && h.classList.contains('aviso-solo-lectura')) || null;
        },
        get texto() { return [n.textContent, ...n.hijos.map(h => h.texto)].join(' ').trim(); },
    };
    return n;
}

function todos(raiz) {
    return raiz.hijos.flatMap(h => [h, ...todos(h)]);
}

function botones(raiz) {
    return todos(raiz).filter(h => h.tagName === 'BUTTON');
}

/** propietario-mascota.js con un DOM, fetch y temporizadores que controla cada prueba. */
function modulo({ respuestas = [] } = {}) {
    const ids = {};
    ['mascotasExistentes', 'petOwnerDoc', 'ownerSearchWrapper', 'selectedOwnerName', 'selectedOwnerInfo', 'ownerSearchAyuda', 'ownerSearchInput'].forEach(id => {
        ids[id] = nodo(id === 'ownerSearchInput' || id === 'petOwnerDoc' ? 'input' : 'div');
    });
    ids.selectedOwnerInfo.hidden = true;
    const escuchas = {};
    const pedidos = [];
    const avisos = [];
    const temporizadores = [];
    let recargas = 0;
    const contexto = {
        console,
        FormData: class { constructor() { this.datos = {}; } set(k, v) { this.datos[k] = v; } append(k, v) { this.datos[k] = v; } },
        document: {
            getElementById: (id) => ids[id] || null,
            createElement: (etiqueta) => nodo(etiqueta),
            addEventListener: (tipo, fn) => { (escuchas[tipo] = escuchas[tipo] || []).push(fn); },
        },
        window: { location: { href: 'index.php?action=vet_pacientes', reload: () => { recargas++; } } },
        setTimeout: (fn, ms) => { temporizadores.push({ fn, ms }); return temporizadores.length; },
        clearTimeout: (id) => { if (temporizadores[id - 1]) temporizadores[id - 1].fn = null; },
        zookiToast: (mensaje, tipo = 'success') => avisos.push([mensaje, tipo]),
        fetch: async (url, opciones = {}) => {
            pedidos.push({ url, opciones });
            const respuesta = respuestas.shift() || { success: true };
            return { json: async () => respuesta, text: async () => '<html></html>' };
        },
        DOMParser: class { parseFromString() { return { getElementById: () => null }; } },
    };
    contexto.window.document = contexto.document;
    vm.createContext(contexto);
    vm.runInContext(codigo('public/js/propietario-mascota.js'), contexto);
    const correr = async () => {
        const pendientes = temporizadores.splice(0).filter(t => t.fn);
        pendientes.forEach(t => t.fn());
        await new Promise(r => setImmediate(r));
        await new Promise(r => setImmediate(r));
    };
    const escribir = (valor) => {
        ids.ownerSearchInput.value = valor;
        ids.ownerSearchInput.matches = (selector) => selector === '[data-buscar-propietario]';
        escuchas.input.forEach(fn => fn({ target: ids.ownerSearchInput }));
    };
    const clic = async (boton) => {
        escuchas.click.forEach(fn => fn({ target: boton }));
        await new Promise(r => setImmediate(r));
        await new Promise(r => setImmediate(r));
    };
    return { api: contexto.window.ZookiPropietarioMascota, ids, pedidos, avisos, temporizadores, correr, escribir, clic, recargas: () => recargas };
}

const FABIO = {
    vinculado: true,
    pendiente: false,
    id_usuario: 6,
    nombre_completo: 'Fabio Dueño',
    mascotas: [{ id_mascota: 1, nombre: 'Luna', nombre_especie: 'Canino', nombre_raza: 'Criollo', raza_indicada: null, url_foto: null }],
};

test('la búsqueda espera un documento o correo completo y busca sola tras una espera corta', async () => {
    const m = modulo({ respuestas: [{ success: true, propietario: null }] });

    assert.equal(m.api.identificadorCompleto('1000'), false);
    assert.equal(m.api.identificadorCompleto('1000000006'), true);
    assert.equal(m.api.identificadorCompleto('fabio@zooki'), false);
    assert.equal(m.api.identificadorCompleto('fabio@zooki.test'), true);

    m.escribir('10000');
    m.escribir('100000');
    m.escribir('1000000');
    assert.equal(m.pedidos.length, 0, 'No busca en cada tecla.');
    await m.correr();
    assert.equal(m.pedidos.length, 1, 'Una sola búsqueda, la del último valor.');
    assert.match(m.pedidos[0].url, /buscar_propietario_exacto_ajax&identificador=1000000$/);
    assert.ok(m.temporizadores.length === 0);
    assert.match(m.ids.ownerSearchAyuda.textContent, /No hay una cuenta con ese dato/);

    m.escribir('fabio@');
    await m.correr();
    assert.equal(m.pedidos.length, 1, 'Con un correo incompleto no busca.');
    assert.match(m.ids.ownerSearchAyuda.textContent, /documento o el correo completo/);
});

test('sin vínculo sale el aviso del servidor y «Pedir confirmación», sin nombre ni mascotas', async () => {
    const aviso = 'Hay una cuenta con ese dato. Pide la confirmación por correo.';
    const m = modulo({ respuestas: [{ success: true, propietario: { vinculado: false, mensaje: aviso } }, { success: true, message: 'Se envió la solicitud.' }] });

    m.escribir('1000000006');
    await m.correr();

    assert.equal(m.ids.ownerSearchAyuda.textContent, aviso);
    const panel = m.ids.mascotasExistentes;
    assert.doesNotMatch(panel.texto, /undefined|Vincular|Fabio/);
    const [pedir] = botones(panel);
    assert.equal(pedir.textContent, 'Pedir confirmación por correo');
    await m.clic(pedir);
    assert.match(m.pedidos[1].url, /solicitar_vinculo_propietario_ajax/);
    assert.equal(m.pedidos[1].opciones.body.datos.identificador, '1000000006');
    assert.deepEqual(m.avisos, [['Se envió la solicitud.', 'success']]);
});

test('el límite por IP se avisa en la línea de ayuda, sin ventana', async () => {
    const m = modulo({ respuestas: [{ success: false, message: 'Espera un momento antes de seguir buscando.' }] });
    m.escribir('fabio@zooki.test');
    await m.correr();
    assert.equal(m.ids.ownerSearchAyuda.textContent, 'Espera un momento antes de seguir buscando.');
    assert.ok(m.ids.ownerSearchAyuda.classList.contains('cuenta-validacion--error'));
});

test('con vínculo: el cliente y sus mascotas en las tarjetas mini de Usuarios', async () => {
    const m = modulo({ respuestas: [{ success: true, propietario: FABIO }] });
    m.escribir('1000000006');
    await m.correr();

    const tarjetas = todos(m.ids.mascotasExistentes).filter(h => h.tagName === 'ARTICLE');
    assert.equal(tarjetas.length, 2, 'El cliente y Luna.');
    for (const tarjeta of tarjetas) {
        assert.ok(tarjeta.classList.contains('person-card'));
        assert.ok(todos(tarjeta).some(h => h.classList.contains('card-body-mini')));
    }
    assert.match(m.ids.mascotasExistentes.texto, /Fabio Dueño/);
    assert.match(m.ids.mascotasExistentes.texto, /Luna/);
    assert.match(m.ids.mascotasExistentes.texto, /Canino · Criollo/);
    assert.deepEqual(botones(m.ids.mascotasExistentes).map(b => b.textContent), ['Vincular a la clínica', 'Es otra mascota: registrar nueva']);
});

test('vincular se pone al día en su sitio con un aviso breve, sin recargar', async () => {
    const m = modulo({ respuestas: [{ success: true, propietario: FABIO }, { success: true }] });
    m.escribir('1000000006');
    await m.correr();
    const [vincular] = botones(m.ids.mascotasExistentes);

    await m.clic(vincular);

    assert.match(m.pedidos[1].url, /vincular_mascota_ajax/);
    assert.deepEqual(m.pedidos[1].opciones.body.datos, { id_propietario: 6, id_mascota: '1' });
    assert.deepEqual(m.avisos, [['Luna quedó vinculada a la clínica.', 'success']]);
    assert.match(m.ids.mascotasExistentes.texto, /Vinculada a la clínica/);
    assert.equal(botones(m.ids.mascotasExistentes).length, 1, 'El botón de Luna cambió por la marca.');
    assert.equal(m.recargas(), 0);
    assert.ok(m.pedidos.some(p => p.url === 'index.php?action=vet_pacientes'), 'Pide otra vez la lista de pacientes para ponerla al día.');
});

test('elegir al propietario y quitarlo usan hidden, nunca style.display', async () => {
    const m = modulo({ respuestas: [{ success: true, propietario: { ...FABIO, mascotas: [], pendiente: true } }] });
    m.escribir('fabio@zooki.test');
    await m.correr();

    assert.equal(m.ids.petOwnerDoc.value, 6, 'Sin mascotas queda elegido para registrar la primera.');
    assert.equal(m.ids.ownerSearchWrapper.hidden, true);
    assert.equal(m.ids.selectedOwnerInfo.hidden, false);
    assert.equal(m.ids.selectedOwnerName.textContent, 'Fabio Dueño');
    assert.match(m.ids.mascotasExistentes.texto, /Aún no activa su cuenta/);
    assert.deepEqual(botones(m.ids.mascotasExistentes).map(b => b.textContent), ['Reenviar enlace']);

    m.api.quitarPropietario(true);
    assert.equal(m.ids.petOwnerDoc.value, '');
    assert.equal(m.ids.ownerSearchWrapper.hidden, false);
    assert.equal(m.ids.selectedOwnerInfo.hidden, true);

    const js = codigo('public/js/propietario-mascota.js');
    assert.doesNotMatch(js, /\.style\./);
    assert.doesNotMatch(js, /location\.reload|zookiRecargarConAviso/);
    assert.doesNotMatch(codigo('public/js/medical-module.js'), /searchOwnersForPet|function selectOwner|clearOwnerSelection/);
});

test('un documento o correo con cuenta, al registrar, muestra el aviso y «Pedir confirmación» en el formulario', () => {
    const m = modulo();
    const contenedor = nodo('div');
    contenedor.hidden = true;

    m.api.mostrarExistente(contenedor, { vinculado: false, identificador: 'fabio@zooki.test', message: 'Hay una cuenta con ese dato. Pide la confirmación por correo.' });
    assert.equal(contenedor.hidden, false);
    assert.match(contenedor.texto, /Hay una cuenta con ese dato/);
    assert.equal(botones(contenedor)[0].dataset.pedirConfirmacion, 'fabio@zooki.test');

    m.api.mostrarExistente(contenedor, { vinculado: true, identificador: '1000000006', message: 'Ya es cliente de esta clínica.' });
    assert.equal(botones(contenedor).length, 0, 'Si ya es cliente, no se pide nada.');
    assert.doesNotMatch(codigo('views/vet/pacientes.php'), /titular_presente|acepta_politica/);
});

/** avisos.js con un Swal falso que guarda lo que se le pide. */
function avisos(confirma = true) {
    const llamadas = [];
    const contexto = {
        console,
        window: {},
        document: {
            addEventListener() {},
            createElement: () => {
                let texto = '';
                return {
                    set textContent(valor) { texto = valor; },
                    get innerHTML() { return texto.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); },
                };
            },
        },
    };
    contexto.Swal = { fire: async (opciones) => { llamadas.push(opciones); return { isConfirmed: confirma }; } };
    contexto.window.Swal = contexto.Swal;
    vm.createContext(contexto);
    vm.runInContext(codigo('public/js/avisos.js'), contexto);
    return { contexto, llamadas };
}

test('desactivar un cliente pide solo «¿Desactivar a <nombre> como cliente?» con el estilo de Zooki', async () => {
    const { contexto, llamadas } = avisos();

    const ok = await contexto.zookiConfirmarCorto('¿Desactivar a Fabio Dueño como cliente?', 'Desactivar');

    assert.equal(ok, true);
    const [opciones] = llamadas;
    assert.equal(opciones.title, '¿Desactivar a Fabio Dueño como cliente?');
    assert.equal(opciones.text, undefined, 'Sin explicación.');
    assert.equal(opciones.icon, undefined, 'Sin icono.');
    assert.equal(opciones.html, undefined);
    assert.equal(opciones.buttonsStyling, false);
    assert.equal(opciones.customClass.popup, 'zooki-confirmacion');
    assert.equal(opciones.customClass.confirmButton, 'btn-modal-primary');
    assert.equal(opciones.customClass.cancelButton, 'btn-modal-secondary');
    const usuarios = codigo('public/js/usuarios.js');
    assert.match(usuarios, /zookiConfirmarCorto\('¿Desactivar a ' \+ nombre \+ ' como cliente\?', 'Desactivar'\)/);
    assert.doesNotMatch(usuarios, /sus otras clínicas no cambian/, 'Ya no insinúa que tiene otras clínicas.');
});

test('la confirmación corta de Agendar lleva clínica, mascota, día y hora, escapados', async () => {
    const { contexto, llamadas } = avisos(false);

    const ok = await contexto.zookiConfirmarCorto('¿Agendar esta cita?', 'Agendar', [['Clínica', 'Norte <b>'], ['Mascota', 'Luna'], ['Día', 'lunes 7 de enero'], ['Hora', '9:00 a. m.']]);

    assert.equal(ok, false);
    const html = llamadas[0].html;
    assert.match(html, /<dl class="zooki-confirmacion__filas">/);
    assert.match(html, /<dt>Clínica<\/dt><dd>Norte &lt;b&gt;<\/dd>/);
    for (const etiqueta of ['Mascota', 'Día', 'Hora']) assert.match(html, new RegExp(`<dt>${etiqueta}</dt>`));
});

test('Agendar es un solo formulario: una línea dice qué falta y «Agendar» abre la confirmación corta', () => {
    const contexto = { console, document: { addEventListener() {} }, window: { addEventListener() {} } };
    vm.createContext(contexto);
    vm.runInContext(codigo('public/js/portal.js'), contexto);

    const vacio = { clinica: '', mascota: '', tipo: '', veterinario: '', dia: '', hora: '' };
    assert.deepEqual([...contexto.faltanParaAgendar(vacio)], ['la clínica', 'la mascota', 'el tipo de cita', 'el veterinario', 'el día', 'la hora']);
    const casi = { clinica: '1', mascota: '1', tipo: '1', veterinario: '2', dia: '2030-01-07', hora: '' };
    assert.equal(contexto.guiaParaAgendar(contexto.faltanParaAgendar(casi)), 'Elige la hora.');
    assert.equal(contexto.guiaParaAgendar(contexto.faltanParaAgendar({ ...casi, mascota: '' })), 'Elige la mascota y la hora.');
    assert.equal(contexto.guiaParaAgendar(contexto.faltanParaAgendar({ ...casi, hora: '09:00' })), '');

    const js = codigo('public/js/portal.js');
    assert.doesNotMatch(js, /irAPaso|faltanDelPaso|data-paso/);
    assert.match(js, /zookiConfirmarCorto\('¿Agendar esta cita\?', 'Agendar', filasDeLaCita\(\)\)/);
    const filas = js.slice(js.indexOf('function filasDeLaCita'), js.indexOf('/** Tipos de cita y veterinarios'));
    for (const etiqueta of ['Clínica', 'Mascota', 'Día', 'Hora']) assert.match(filas, new RegExp(`\\['${etiqueta}'`));
});

/** medical-module.js con un DOM mínimo. */
function historia() {
    const contexto = {
        console,
        window: {},
        document: {
            addEventListener() {},
            getElementById: () => null,
            createElement: (etiqueta) => nodo(etiqueta),
        },
    };
    vm.createContext(contexto);
    vm.runInContext(codigo('public/js/medical-module.js'), contexto);
    return contexto;
}

test('hueco 6: una mascota desvinculada dice «solo lectura» y no ofrece registrar nada', () => {
    const contexto = historia();
    const panel = nodo('div');

    assert.equal(contexto.avisoSoloLectura(panel, { solo_lectura: true }), true);
    assert.equal(panel.hijos[0].textContent, 'El propietario se desvinculó: solo lectura');
    assert.equal(contexto.avisoSoloLectura(panel, { solo_lectura: true }), true);
    assert.equal(panel.hijos.length, 1, 'No se repite al volver a abrirla.');
    assert.equal(contexto.avisoSoloLectura(panel, { solo_lectura: false }), false);
    assert.equal(panel.hijos.length, 0);

    const sinHistoria = { innerHTML: '', querySelector: () => null };
    contexto.document.getElementById = (id) => (id === 'modalConsulta' ? {} : null);
    contexto.mostrarSinHistoria(sinHistoria, 1, 'Luna', null, true);
    assert.doesNotMatch(sinHistoria.innerHTML, /Registrar primera consulta/);
    contexto.mostrarSinHistoria(sinHistoria, 1, 'Luna', null, false);
    assert.match(sinHistoria.innerHTML, /Registrar primera consulta/, 'Con el vínculo activo sí se ofrece.');
});

test('lo registrado por otra clínica lleva su clínica y «solo lectura» a la vista, no solo en el title', () => {
    const contexto = historia();
    const etiqueta = contexto.etiquetaOrigen({ es_propia: false, clinica_nombre: 'Clínica Sur' });

    assert.match(etiqueta, /Clínica Sur · solo lectura<\/span>$/);
    assert.doesNotMatch(etiqueta, /title=/);
    assert.equal(contexto.etiquetaOrigen({ es_propia: true, clinica_nombre: 'Clínica Norte' }), '');
});

test('«Vincularme» deshabilitado al tope se repinta con la sección y el aviso del rechazo sale en la tarjeta', () => {
    const js = codigo('public/js/portal.js');
    assert.match(js, /'misClinicas'/, 'Clínicas se repinta en su sitio, con el botón ya deshabilitado.');
    const vista = codigo('views/portal/index.php');
    assert.match(vista, /data-vincular-clinica="<\?= \(int\) \$clinica\['id_clinica'\] \?>" data-nombre="<\?= \$e\(\$clinica\['nombre'\]\) \?>"<\?= \$al_tope_clinicas \? ' disabled' : '' \?>>/);
    assert.match(vista, /data-tope-clinicas/);
});
