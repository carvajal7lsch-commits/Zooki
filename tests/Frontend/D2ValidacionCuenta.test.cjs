const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const raiz = path.join(__dirname, '../..');
const codigo = fs.readFileSync(path.join(raiz, 'public/js/validacion-cuenta.js'), 'utf8');

function pagina(nombres, responder, ayudas = {}) {
    const llamadas = [];
    const campos = nombres.map(nombre => {
        const salidas = [];
        return {
            name: nombre,
            id: nombre,
            value: '',
            type: 'text',
            dataset: {},
            required: true,
            closest: () => null,
            atributos: {},
            parentElement: {
                querySelector: () => salidas[0] || null,
                appendChild: salida => salidas.push(salida),
            },
            setAttribute(clave, valor) { this.atributos[clave] = valor; },
            getAttribute(clave) { return this.atributos[clave]; },
            setCustomValidity(valor) { this.error = valor; },
            salidas,
        };
    });
    const eventos = {};
    const form = {
        dataset: {},
        elements: Object.fromEntries(campos.map(campo => [campo.name, campo])),
        querySelectorAll: () => campos,
        addEventListener(tipo, callback) { eventos[tipo] = callback; },
        dispatchEvent() {},
        reportValidity: () => true,
        requestSubmit() { this.enviado = true; },
    };
    const document = {
        forms: [form],
        querySelector: () => ({ dataset: { cuentaCsrf: 'token-validacion' } }),
        getElementById: id => ayudas[id] || null,
        createElement: () => ({ dataset: {}, setAttribute() {} }),
        addEventListener() {},
    };
    const window = {};
    const temporizadores = [];
    vm.runInNewContext(codigo, {
        document, window, WeakMap, Set, FormData,
        CustomEvent: class { constructor(tipo, datos) { this.tipo = tipo; this.detail = datos.detail; } },
        setTimeout: callback => { temporizadores.push(callback); return temporizadores.length; },
        clearTimeout() {},
        fetch: async (url, opciones) => {
            llamadas.push(opciones.body);
            return responder(opciones.body, llamadas.length);
        },
    });
    window.ZookiValidacionCuenta.vincular(form);
    return { api: window.ZookiValidacionCuenta, form, campos, eventos, llamadas, temporizadores };
}

const respuesta = (datos = {}) => ({ ok: true, json: async () => ({ success: true, error: null, exists: false, ...datos }) });

test('el rechazo de la política del servidor aparece junto al campo mientras se escribe', async () => {
    const p = pagina(['password'], () => respuesta({ error: 'La contraseña contiene tu nombre.' }));
    p.campos[0].value = 'Fabio#Seguro2026';
    p.eventos.input({ target: p.campos[0] });
    await p.temporizadores[0]();
    assert.equal(p.campos[0].error, 'La contraseña contiene tu nombre.');
    assert.equal(p.campos[0].salidas[0].textContent, 'La contraseña contiene tu nombre.');
    assert.equal(p.llamadas[0].get('cuenta_csrf'), 'token-validacion');
    assert.equal(p.llamadas[0].has('csrf_token'), false, 'el interceptor global no reemplaza el token de esta ayuda');
});

test('una respuesta antigua no valida el documento que fue reemplazado', async () => {
    let resolver;
    const p = pagina(['documento'], () => new Promise(resolve => { resolver = resolve; }));
    p.campos[0].value = '1000011111';
    const pendiente = p.api.comprobar(p.form, p.campos[0]);
    p.campos[0].value = '1000022222';
    resolver(respuesta());
    assert.equal(await pendiente, false);
    assert.notEqual(p.campos[0].salidas[0].textContent, 'Documento disponible');
});

test('el aviso queda debajo de la envoltura del input y no dentro de ella', async () => {
    const p = pagina(['password'], () => respuesta({ error: 'Contraseña no válida.' }));
    const salidas = [];
    const contenedor = {
        querySelector: () => salidas[0] || null,
        appendChild: salida => salidas.push(salida),
    };
    p.campos[0].closest = () => ({ parentElement: contenedor });
    p.campos[0].value = 'Fabio#Seguro2026';
    await p.api.comprobar(p.form, p.campos[0]);
    assert.equal(p.campos[0].salidas.length, 0);
    assert.equal(salidas[0].textContent, 'Contraseña no válida.');
});

test('la contraseña reutiliza su ayuda y restaura los requisitos cuando desaparece el error', async () => {
    const ayuda = { id: 'requisitos', dataset: {}, className: 'password-strength-text', textContent: 'Mínimo 8 caracteres.' };
    const p = pagina(['password'], () => respuesta({ error: 'La contraseña contiene tu nombre.' }), { requisitos: ayuda });
    p.campos[0].dataset.cuentaAyuda = 'requisitos';
    p.campos[0].value = 'Fabio#Seguro2026';
    await p.api.comprobar(p.form, p.campos[0]);
    assert.equal(p.campos[0].salidas.length, 0);
    assert.equal(ayuda.textContent, 'La contraseña contiene tu nombre.');
    assert.equal(p.campos[0].atributos['aria-describedby'], 'requisitos');
    p.campos[0].value = '';
    await p.api.comprobar(p.form, p.campos[0]);
    assert.equal(ayuda.textContent, 'Mínimo 8 caracteres.');
});

test('documento duplicado impide enviar el formulario', async () => {
    const p = pagina(['documento'], () => respuesta({ exists: true }));
    p.campos[0].value = '1000011111';
    let bloqueado = false;
    await p.eventos.submit({ preventDefault() {}, stopImmediatePropagation() { bloqueado = true; } });
    assert.equal(bloqueado, true);
    assert.equal(p.form.enviado, undefined);
    assert.equal(p.campos[0].error, 'Este dato ya está registrado.');
});

test('alta de personal existente permite vincular sin prometer otra cuenta', async () => {
    const p = pagina(['documento'], () => respuesta({ exists: true }));
    p.form.dataset.cuentaVincular = 'true';
    p.campos[0].value = '1000011111';
    assert.equal(await p.api.comprobar(p.form, p.campos[0]), true);
    assert.equal(p.campos[0].salidas[0].textContent, 'Cuenta existente: se vinculará a la clínica.');
});

test('una edición de personal no trata un documento duplicado como un alta existente', async () => {
    const p = pagina(['documento'], () => respuesta({ exists: true }));
    p.form.dataset.cuentaVincular = 'true';
    p.form.elements.id_usuario = { value: '2' };
    p.campos[0].value = '1000011111';
    assert.equal(await p.api.comprobar(p.form, p.campos[0]), false);
});

test('registro con documento existente y correo nuevo se rechaza', async () => {
    const p = pagina(['documento', 'email'], datos => respuesta({ exists: datos.get('campo') === 'documento' }));
    p.form.dataset.cuentaVincular = 'correo';
    p.campos[0].value = '1000011111';
    p.campos[1].value = 'nuevo@zooki.test';
    await p.eventos.submit({ preventDefault() {}, stopImmediatePropagation() {} });
    assert.equal(p.form.enviado, undefined);
    assert.equal(p.campos[0].error, 'Este dato ya está registrado.');
});

test('registro con correo existente permite solicitar su vínculo', async () => {
    const p = pagina(['documento', 'email'], () => respuesta({ exists: true }));
    p.form.dataset.cuentaVincular = 'correo';
    p.campos[0].value = '1000011111';
    p.campos[1].value = 'fabio@zooki.test';
    await p.eventos.submit({ preventDefault() {}, stopImmediatePropagation() {} });
    assert.equal(p.form.enviado, true);
    assert.equal(p.llamadas[0].get('campo'), 'email');
});

test('el titular puede enviar documento duplicado para abrir soporte tras verificar identidad', async () => {
    const p = pagina(['documento'], () => respuesta({ exists: true }));
    p.form.dataset.identidadAccion = 'cambiar_documento_ajax';
    p.campos[0].value = '1000011111';
    assert.equal(await p.api.comprobar(p.form, p.campos[0]), true);
    assert.match(p.campos[0].salidas[0].textContent, /soporte/);
});

test('no se envía con validación pendiente o sin conexión', async () => {
    const p = pagina(['telefono'], async () => { throw new Error('Sin conexión'); });
    p.campos[0].value = '3001112233';
    await p.eventos.submit({ preventDefault() {}, stopImmediatePropagation() {} });
    assert.equal(p.form.enviado, undefined);
    assert.equal(p.campos[0].error, 'Sin conexión');
});

test('confirmación distinta se rechaza sin consultar ni abrir un modal', async () => {
    const p = pagina(['password', 'confirm_password'], () => respuesta());
    p.campos[0].value = 'Bosque#Seguro2026';
    p.campos[1].value = 'Otro#Seguro2026';
    assert.equal(await p.api.comprobar(p.form, p.campos[1]), false);
    assert.equal(p.llamadas.length, 0);
    assert.equal(p.campos[1].error, 'Las contraseñas no coinciden.');
});

test('las pantallas de contraseña y el portal no conservan validación en línea', () => {
    ['views/auth/reset_password.php', 'views/auth/cambiar_password.php'].forEach(archivo => {
        assert.doesNotMatch(fs.readFileSync(path.join(raiz, archivo), 'utf8'), /<script>\s*\S|<style>|\son(?:submit|input)=/);
    });
    ['views/auth/login.php', 'views/auth/completar_perfil.php', 'views/perfil/index.php', 'views/portal/index.php', 'views/admin/usuarios.php', 'views/vet/pacientes.php'].forEach(archivo => {
        assert.match(fs.readFileSync(path.join(raiz, archivo), 'utf8'), /data-validacion-cuenta/);
    });
});
