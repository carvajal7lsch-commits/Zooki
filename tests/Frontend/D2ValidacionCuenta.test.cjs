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

test('RE-0.1.3: DV erróneo aparece mientras se escribe; un dígito solo no se consulta ni se admite', async () => {
    const mensaje = 'El dígito de verificación no corresponde. Revísalo en tu RUT.';
    for (const valor of ['900123456-7', '1075001234-3']) {
        const p = pagina(['nit'], () => respuesta({ error: mensaje, nit_duplicado: false }));
        p.campos[0].value = valor;
        p.eventos.input({ target: p.campos[0] });
        await p.temporizadores.at(-1)();
        assert.equal(p.llamadas[0].get('valor'), valor);
        assert.equal(p.campos[0].error, mensaje);
        assert.equal(p.campos[0].salidas[0].textContent, mensaje);
    }
    const p = pagina(['nit'], () => respuesta());
    p.campos[0].value = '1';
    assert.equal(await p.api.comprobar(p.form, p.campos[0]), false);
    assert.equal(p.campos[0].error, mensaje);
    assert.equal(p.llamadas.length, 0);
});

test('RE-0.1.3: el NIT o cédula sin guion se consulta completo, sin exigir DV escrito', async () => {
    for (const valor of ['1075001234', '900123456', '9001234567']) {
        const p = pagina(['nit'], () => respuesta({ nit_duplicado: false }));
        p.campos[0].value = valor;
        assert.equal(await p.api.comprobar(p.form, p.campos[0]), true);
        assert.equal(p.llamadas[0].get('valor'), valor);
        assert.equal(p.campos[0].value, valor);
        assert.equal(p.campos[0].error, '');
    }
});

test('P1: el freno avisa sin invalidar el campo y se vuelve a consultar al enviar', async () => {
    const p = pagina(['email'], (_, numero) => numero === 1
        ? { ok: false, status: 429, json: async () => ({ success: false, message: 'Espera un momento.' }) }
        : respuesta());
    p.form.id = 'registroClinicaForm';
    const avisos = [];
    p.form.dispatchEvent = evento => avisos.push(evento.tipo);
    p.campos[0].value = 'correo@zooki.test';
    assert.equal(await p.api.comprobar(p.form, p.campos[0]), false);
    assert.equal(p.campos[0].error, '');
    assert.equal(p.campos[0].atributos['aria-invalid'], 'false');
    assert.deepEqual(avisos, ['cuenta:limitada']);
    await p.eventos.submit({ preventDefault() {}, stopImmediatePropagation() {} });
    assert.equal(p.llamadas.length, 2);
    for (const ejecutar of p.temporizadores) ejecutar();
    assert.equal(p.form.enviado, true);
    assert.equal(avisos.includes('cuenta:invalida'), false);
});

test('P1: si el freno continúa al enviar, no hay resumen de campos ni envío', async () => {
    const p = pagina(['email'], () => ({ ok: false, status: 429, json: async () => ({ success: false }) }));
    p.form.id = 'registroClinicaForm';
    const avisos = [];
    p.form.dispatchEvent = evento => avisos.push(evento.tipo);
    p.campos[0].value = 'correo@zooki.test';
    await p.eventos.submit({ preventDefault() {}, stopImmediatePropagation() {} });
    assert.deepEqual(avisos, ['cuenta:limitada']);
    assert.equal(p.campos[0].error, '');
    assert.equal(p.form.enviado, undefined);
});

test('P1: otros formularios conservan su aviso previo si no cargan el resumen del registro de clínica', async () => {
    const p = pagina(['email'], () => ({ ok: false, status: 429, json: async () => ({ success: false, message: 'Espera un momento antes de seguir comprobando.' }) }));
    p.campos[0].value = 'correo@zooki.test';
    assert.equal(await p.api.comprobar(p.form, p.campos[0]), false);
    assert.equal(p.campos[0].error, 'Espera un momento antes de seguir comprobando.');
});

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

test('E2: completar el NIT único muestra duplicado junto al campo y habilita Reportar a soporte', async () => {
    const p = pagina(['nit'], () => respuesta({ error: 'Este NIT ya está registrado en Zooki. Puedes reportarlo a soporte.', nit_duplicado: true }));
    const reporte = { hidden: true, addEventListener() {} };
    const salida = {};
    const registros = {};
    p.form.addEventListener = (tipo, callback) => { registros[tipo] = callback; };
    p.form.dispatchEvent = evento => registros[evento.tipo]?.(evento);
    vm.runInNewContext(fs.readFileSync(path.join(raiz, 'public/js/registro-clinica.js'), 'utf8'), {
        document: { addEventListener(tipo, callback) { callback(); }, getElementById: id => id === 'registroClinicaForm' ? p.form : (id === 'reportarNit' ? reporte : salida) },
        window: {},
    });
    p.campos[0].value = '900123456-8';
    p.eventos.input({ target: p.campos[0] });
    await p.temporizadores.at(-1)();
    assert.equal(p.llamadas[0].get('nit'), '900123456-8');
    assert.equal(p.llamadas[0].get('campo'), 'nit');
    assert.match(p.campos[0].salidas[0].textContent, /ya está registrado/);
    assert.equal(reporte.hidden, false);
    p.campos[0].value = '900123457';
    registros.input({ target: p.campos[0] });
    assert.equal(reporte.hidden, true);
    const vista = fs.readFileSync(path.join(raiz, 'views/auth/registro_clinica.php'), 'utf8');
    assert.ok(vista.indexOf('id="reportarNit"') < vista.indexOf('id="direccion"'));
    assert.match(vista, />Reportar a soporte</);
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

test('D3: una respuesta heredada de existencia no bloquea ni revela identidades', async () => {
    const p = pagina(['documento'], () => respuesta({ exists: true }));
    p.campos[0].value = '1000011111';
    await p.eventos.submit({ preventDefault() {}, stopImmediatePropagation() {} });
    p.temporizadores.at(-1)();
    assert.equal(p.form.enviado, true);
    assert.equal(p.campos[0].error, '');
});

test('D2.2: el alta de personal no pregunta al servidor si el documento o el correo tienen cuenta', async () => {
    const p = pagina(['documento', 'email'], () => respuesta({ exists: true }));
    p.form.dataset.cuentaUnicidad = 'solo-edicion';
    p.campos[0].value = '1000011111';
    p.campos[1].value = 'fabio@zooki.test';
    p.eventos.input({ target: p.campos[0] });
    p.eventos.input({ target: p.campos[1] });
    assert.equal(p.temporizadores.length, 0, 'Al escribir no se programa ninguna consulta.');
    assert.equal(await p.api.comprobar(p.form, p.campos[0]), true);
    assert.equal(await p.api.comprobar(p.form, p.campos[1]), true);
    assert.equal(p.llamadas.length, 0, 'Ni al comprobar ni al enviar se consulta la existencia.');
    assert.equal(p.campos[0].error, '');
    assert.doesNotMatch(p.campos[0].salidas[0]?.textContent || '', /vincul|registrad/);
});

test('D2.2: el formato sigue validándose en el alta de personal', async () => {
    const p = pagina(['documento'], () => respuesta());
    p.form.dataset.cuentaUnicidad = 'solo-edicion';
    p.campos[0].value = '12ab';
    assert.equal(await p.api.comprobar(p.form, p.campos[0]), false);
    assert.equal(p.campos[0].error, 'Documento de 5 a 15 dígitos.');
    assert.equal(p.llamadas.length, 0);
});

test('una edición de personal solo comprueba el formato del documento', async () => {
    const p = pagina(['documento'], () => respuesta({ exists: true }));
    p.form.dataset.cuentaUnicidad = 'solo-edicion';
    p.form.elements.id_usuario = { value: '2' };
    p.campos[0].value = '1000011111';
    assert.equal(await p.api.comprobar(p.form, p.campos[0]), true);
});

test('registro no consulta existencia de documento al escribir', async () => {
    const p = pagina(['documento', 'email'], datos => respuesta({ exists: datos.get('campo') === 'documento' }));
    p.form.dataset.cuentaVincular = 'correo';
    p.campos[0].value = '1000011111';
    p.campos[1].value = 'nuevo@zooki.test';
    await p.eventos.submit({ preventDefault() {}, stopImmediatePropagation() {} });
    assert.equal(p.form.enviado, undefined);
    assert.equal(p.campos[0].error, '');
});

test('registro con correo existente permite solicitar su vínculo', async () => {
    const p = pagina(['documento', 'email'], () => respuesta({ exists: true }));
    p.form.dataset.cuentaVincular = 'correo';
    p.campos[0].value = '1000011111';
    p.campos[1].value = 'fabio@zooki.test';
    await p.eventos.submit({ preventDefault() {}, stopImmediatePropagation() {} });
    // Revisión D2.1: el reenvío va en otra tarea; dentro del submit original el navegador lo ignora.
    assert.equal(p.form.enviado, undefined);
    p.temporizadores.at(-1)();
    assert.equal(p.form.enviado, true);
    assert.equal(p.llamadas[0].get('campo'), 'email');
});

test('el titular puede enviar documento duplicado para abrir soporte tras verificar identidad', async () => {
    const p = pagina(['documento'], () => respuesta({ exists: true }));
    p.form.dataset.identidadAccion = 'cambiar_documento_ajax';
    p.campos[0].value = '1000011111';
    assert.equal(await p.api.comprobar(p.form, p.campos[0]), true);
    assert.equal(p.campos[0].salidas[0].textContent, '');
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
    ['views/auth/login.php', 'views/auth/completar_perfil.php', 'views/perfil/paneles_cuenta.php', 'views/portal/index.php', 'views/admin/usuarios.php', 'views/vet/pacientes.php'].forEach(archivo => {
        assert.match(fs.readFileSync(path.join(raiz, archivo), 'utf8'), /data-validacion-cuenta/);
    });
});
