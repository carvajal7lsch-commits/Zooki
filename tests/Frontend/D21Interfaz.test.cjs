const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const raiz = path.join(__dirname, '../..');
const codigo = (archivo) => fs.readFileSync(path.join(raiz, archivo), 'utf8');

// ── validacion-cuenta.js: aviso en cada tecla ──────────────────────────────

function campo(nombre, extra = {}) {
    const salidas = [];
    return {
        name: nombre,
        id: nombre,
        value: '',
        type: 'text',
        dataset: {},
        required: true,
        pattern: '',
        title: '',
        closest: () => null,
        atributos: {},
        parentElement: {
            querySelector: () => salidas[0] || null,
            appendChild: salida => salidas.push(salida),
        },
        setAttribute(clave, valor) { this.atributos[clave] = valor; },
        getAttribute(clave) { return this.atributos[clave] ?? null; },
        setCustomValidity(valor) { this.error = valor; },
        get aviso() { return salidas[0] ? salidas[0].textContent : ''; },
        get claseAviso() { return salidas[0] ? salidas[0].className : ''; },
        salidas,
        ...extra,
    };
}

function formulario(campos, responder, ventana = {}) {
    const llamadas = [];
    const eventos = {};
    const form = {
        dataset: {},
        elements: Object.fromEntries(campos.map(c => [c.name, c])),
        querySelectorAll: () => campos,
        addEventListener(tipo, callback) { eventos[tipo] = callback; },
        dispatchEvent() {},
        reportValidity: () => true,
        requestSubmit() { this.enviado = true; },
    };
    const temporizadores = [];
    const window = { ...ventana };
    vm.runInNewContext(codigo('public/js/validacion-cuenta.js'), {
        document: {
            forms: [form],
            querySelector: () => ({ dataset: { cuentaCsrf: 'token' } }),
            getElementById: () => null,
            createElement: () => ({ dataset: {}, setAttribute() {} }),
            addEventListener() {},
        },
        window, WeakMap, Map, Set, FormData, RegExp,
        CustomEvent: class { constructor(tipo, datos) { this.detail = datos.detail; } },
        setTimeout: callback => { temporizadores.push(callback); return temporizadores.length; },
        clearTimeout() {},
        fetch: async (url, opciones) => {
            llamadas.push(opciones.body);
            return responder(opciones.body, llamadas.length);
        },
    });
    window.ZookiValidacionCuenta.vincular(form);
    const escribir = (c, valor) => { c.value = valor; eventos.input({ target: c }); };
    return { api: window.ZookiValidacionCuenta, form, eventos, llamadas, temporizadores, escribir };
}

const respuesta = (datos = {}) => ({ ok: true, json: async () => ({ success: true, error: null, exists: false, ...datos }) });

test('el formato se avisa en la misma tecla, sin esperar al servidor', () => {
    const documento = campo('documento');
    const telefono = campo('telefono', { pattern: '[0-9+\\s\\-]{7,20}', title: 'El teléfono solo puede tener números, espacios, + y guiones (de 7 a 20 caracteres).' });
    const correo = campo('email');
    const p = formulario([documento, telefono, correo], () => respuesta());

    p.escribir(documento, '12');
    assert.equal(documento.aviso, 'El documento debe tener entre 5 y 15 dígitos.');
    p.escribir(telefono, '300 abc');
    assert.equal(telefono.aviso, telefono.title);
    p.escribir(correo, 'fabio@');
    assert.equal(correo.aviso, 'Escribe un correo electrónico válido.');
    assert.equal(p.llamadas.length, 0, 'Nada de esto consulta al servidor.');
    assert.equal(p.temporizadores.length, 0, 'Ni programa una consulta.');

    p.escribir(documento, '1000012345');
    assert.equal(documento.aviso, '', 'Al corregirlo, el aviso local desaparece en la misma tecla.');
    assert.equal(p.temporizadores.length, 1, 'Y la unicidad se consulta con pausa.');
});

test('la contraseña usa password-policy.js en cada tecla y la confirmación se revisa al instante', () => {
    const password = campo('password');
    const confirmacion = campo('confirm_password');
    const motivos = { 'bosque2026': 'Falta una letra mayúscula' };
    const p = formulario([password, confirmacion], () => respuesta(), { motivoPasswordInvalida: v => motivos[v] || (v.length < 8 ? 'Mínimo 8 caracteres' : null) });

    p.escribir(password, 'Bo');
    assert.equal(password.aviso, 'Mínimo 8 caracteres');
    p.escribir(password, 'bosque2026');
    assert.equal(password.aviso, 'Falta una letra mayúscula');
    assert.equal(p.llamadas.length, 0);

    p.escribir(password, 'Bosque#Seguro2026');
    p.escribir(confirmacion, 'Bosque#Seguro2026');
    assert.equal(confirmacion.aviso, 'Las contraseñas coinciden.');
    assert.match(confirmacion.claseAviso, /cuenta-validacion--ok/);
    p.escribir(password, 'Bosque#Seguro2027');
    assert.equal(confirmacion.aviso, 'Las contraseñas no coinciden.', 'Cambiar la contraseña revisa la confirmación en la misma tecla.');
});

test('mientras el servidor responde no se borra el aviso vigente y el aviso nombra el campo', async () => {
    const documento = campo('documento');
    const pendientes = [];
    const p = formulario([documento], () => new Promise(resolve => pendientes.push(resolve)));

    p.escribir(documento, '1000000001');
    const primera = p.temporizadores[0]();
    pendientes[0](respuesta({ exists: true }));
    await primera;
    assert.equal(documento.aviso, 'Este documento ya está registrado.');

    p.escribir(documento, '1000000002');
    assert.equal(documento.aviso, 'Este documento ya está registrado.', 'No se borra ni dice «Comprobando…» mientras llega la respuesta.');
    const segunda = p.temporizadores[1]();
    pendientes[1](respuesta({ exists: false }));
    await segunda;
    assert.equal(documento.aviso, '');
});

test('una respuesta vieja no pisa el aviso de la nueva', async () => {
    const correo = campo('email');
    const pendientes = [];
    const p = formulario([correo], () => new Promise(resolve => pendientes.push(resolve)));

    p.escribir(correo, 'ana@zooki.test');
    const vieja = p.temporizadores[0]();
    p.escribir(correo, 'nuevo@zooki.test');
    const nueva = p.temporizadores[1]();
    pendientes[1](respuesta({ exists: false }));
    await nueva;
    pendientes[0](respuesta({ exists: true }));
    await vieja;
    assert.equal(correo.aviso, '', 'La respuesta tardía de «ana@» no marca como registrado el correo nuevo.');
    assert.equal(correo.error, '');

    p.escribir(correo, 'ana@zooki.test');
    const otra = p.temporizadores[2]();
    pendientes[2](respuesta({ exists: true }));
    await otra;
    assert.equal(correo.aviso, 'Este correo ya está registrado.');
});

// ── perfil.js: requisitos marcados en cada tecla ──────────────────────────

function elemento(extra = {}) {
    const eventos = {};
    const clases = new Set();
    return {
        value: '',
        textContent: '',
        className: '',
        dataset: {},
        eventos,
        classList: {
            toggle(nombre, valor) { if (valor) clases.add(nombre); else clases.delete(nombre); },
            contains: nombre => clases.has(nombre),
        },
        addEventListener(tipo, callback) { eventos[tipo] = callback; },
        querySelectorAll: () => [],
        ...extra,
    };
}

test('Mi perfil marca la lista de requisitos de la contraseña al escribir', () => {
    const requisitos = ['longitud', 'mayuscula', 'minuscula', 'numero'].map(nombre => elemento({ dataset: { requisito: nombre } }));
    const formPwd = elemento({ querySelectorAll: selector => (selector === '[data-requisito]' ? requisitos : []) });
    const nueva = elemento();
    const ids = {
        perfilContactoForm: elemento(), perfilEmail: elemento({ value: 'beto@zooki.test' }), perfilTelefono: elemento(),
        perfilContactoGuardar: elemento(), perfilContactoMsg: elemento(), perfilPasswordForm: formPwd,
        perfilPwdActual: elemento(), perfilPwdNueva: nueva, perfilPwdConfirmar: elemento(),
        perfilPasswordGuardar: elemento({ textContent: 'Actualizar contraseña' }), perfilPasswordMsg: elemento(),
    };
    vm.runInNewContext(codigo('public/js/perfil.js'), {
        document: { getElementById: id => ids[id] || null },
        window: { location: { hash: '' } },
        setTimeout: callback => callback(),
    });
    const marcados = () => requisitos.filter(r => r.classList.contains('is-ok')).map(r => r.dataset.requisito);

    nueva.value = 'b';
    nueva.eventos.input();
    assert.deepEqual(marcados(), ['minuscula']);
    nueva.value = 'Bosque2026';
    nueva.eventos.input();
    assert.deepEqual(marcados(), ['longitud', 'mayuscula', 'minuscula', 'numero']);
    nueva.value = 'BOSQUE';
    nueva.eventos.input();
    assert.deepEqual(marcados(), ['mayuscula'], 'Se desmarca lo que deja de cumplirse.');
});

// ── usuarios.js: modal del personal con el diseño de v1.12.0 ───────────────

function paginaUsuarios(usuario) {
    const nodos = {};
    const nodo = (nombre, extra = {}) => (nodos[nombre] = elemento({ hidden: false, disabled: false, checked: false, focus() {}, ...extra }));
    ['[data-aviso]', '[data-titulo]', '[data-subtitulo]', '[data-subtitulo-nombre]', '[data-subtitulo-documento]',
        '[data-texto-guardar]', '[data-estado-interruptor]', '[data-estado-texto]', '[data-icono-titulo]'].forEach(selector => nodo(selector));
    const soloAlta = [nodo('tipo'), nodo('documento-grupo'), nodo('invitacion')];
    const eventosModal = {};
    const modal = {
        dataset: { yo: '1' },
        classList: { add() {}, remove() {}, contains: () => false },
        querySelector: selector => nodos[selector],
        querySelectorAll: selector => (selector === '[data-solo-alta]' ? soloAlta : []),
        addEventListener(tipo, callback) { eventosModal[tipo] = callback; },
    };
    const campoForm = () => elemento({ readOnly: false, disabled: false, focus() {} });
    const elementos = {
        id_usuario: campoForm(), tipo_documento: campoForm(), documento: campoForm(), nombre_completo: campoForm(),
        email: campoForm(), telefono: campoForm(), id_rol: campoForm(), estado: campoForm(), password: campoForm(),
    };
    const form = { elements: elementos, addEventListener() {}, reset() {}, querySelector: () => elementos.documento };
    const eventosModulo = {};
    const modulo = {
        dataset: {},
        addEventListener(tipo, callback) { eventosModulo[tipo] = callback; },
        querySelectorAll: () => [],
        querySelector: () => null,
    };
    const iti = { opciones: null, numero: null, setNumber(valor) { this.numero = valor; }, isValidNumber: () => true, getNumber() { return this.numero; } };
    const window = {
        intlTelInput(campo, opciones) { iti.campo = campo; iti.opciones = opciones; return iti; },
        location: { href: '' },
    };
    vm.runInNewContext(codigo('public/js/usuarios.js'), {
        document: {
            getElementById: id => ({ usuariosModulo: modulo, usuarioModal: modal, usuarioForm: form })[id],
            addEventListener() {},
            body: {},
        },
        window, localStorage: { getItem: () => null, setItem() {} }, console, FormData,
        fetch: async () => ({ status: 200, json: async () => ({ success: true, usuario }) }),
        zookiToast() {},
    });
    const clic = async accion => eventosModulo.click({ target: { closest: () => ({ dataset: { accion, id: String(usuario.id_usuario) } }) } });
    return { nodos, soloAlta, iti, clic, elementos, eventosModal };
}

test('el modal del personal se ve como en v1.12.0: título, bandera e interruptor de estado', async () => {
    const usuario = { id_usuario: 2, nombre_completo: 'Beto Norte', documento: '1000000002', tipo_documento: 'CC', email: 'beto@zooki.test', telefono: '+573001112233', id_rol: 2, estado: 0, identidad_editable: true };
    const p = paginaUsuarios(usuario);
    assert.equal(p.iti.campo, p.elementos.telefono, 'El teléfono usa intl-tel-input, como en Pacientes.');
    assert.equal(p.iti.opciones.initialCountry, 'co');

    await p.clic('nuevo');
    assert.equal(p.nodos['[data-titulo]'].textContent, 'Nuevo Usuario');
    assert.equal(p.nodos['[data-subtitulo]'].hidden, true);
    assert.ok(p.soloAlta.every(n => n.hidden === false), 'El alta pide tipo y documento y avisa la invitación.');
    assert.equal(p.nodos['[data-texto-guardar]'].textContent, 'Crear Usuario');

    await p.clic('editar');
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(p.nodos['[data-titulo]'].textContent, 'Editar Usuario');
    assert.equal(p.nodos['[data-subtitulo]'].hidden, false);
    assert.equal(p.nodos['[data-subtitulo-nombre]'].textContent, 'Beto Norte');
    assert.equal(p.nodos['[data-subtitulo-documento]'].textContent, '1000000002');
    assert.ok(p.soloAlta.every(n => n.hidden === true), 'Al editar, el documento va en el subtítulo.');
    assert.equal(p.nodos['[data-texto-guardar]'].textContent, 'Guardar Cambios');
    assert.equal(p.iti.numero, '+573001112233');
    assert.equal(p.nodos['[data-estado-interruptor]'].checked, false);
    assert.equal(p.nodos['[data-estado-texto]'].textContent, 'Inactivo');
    assert.equal(p.elementos.estado.value, '0');

    const interruptor = p.nodos['[data-estado-interruptor]'];
    interruptor.checked = true;
    interruptor.matches = selector => selector === '[data-estado-interruptor]';
    p.eventosModal.change({ target: interruptor });
    assert.equal(p.nodos['[data-estado-texto]'].textContent, 'Activo');
    assert.equal(p.elementos.estado.value, '1');
});

test('la vista del modal del personal no tiene código en línea y conserva el diseño de v1.12.0', () => {
    const vista = codigo('views/admin/usuarios.php');
    const modal = vista.slice(vista.indexOf('id="usuarioModal"'), vista.indexOf('id="clienteModal"'));
    assert.doesNotMatch(modal, /\sstyle="|\son[a-z]+="/);
    assert.match(modal, /class="close-modal"/);
    assert.match(modal, /data-subtitulo/);
    assert.match(modal, /class="toggle-switch"/);
    assert.match(modal, /Correo electrónico/);
    assert.match(modal, /72 horas/);
    assert.match(vista, /intl-tel-input@23\.0\.10\/build\/js\/intlTelInput\.min\.js/);
});
