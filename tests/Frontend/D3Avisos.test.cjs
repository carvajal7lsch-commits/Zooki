const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const codigo = archivo => fs.readFileSync(path.join(__dirname, '../..', archivo), 'utf8');

async function cambiar(id, accion, actual) {
    let enviar;
    const avisos = [];
    const destinos = [];
    const recargas = [];
    const boton = { disabled: false };
    const salida = { textContent: '' };
    const form = {
        id,
        dataset: { passwordAccion: accion },
        elements: actual ? { current_password: {} } : {},
        addEventListener(tipo, callback) { enviar = callback; },
        querySelector: () => salida,
        reset() { this.restablecido = true; },
    };
    const contexto = {
        document: {
            addEventListener(tipo, callback) { callback(); },
            querySelectorAll: selector => selector === '[data-password-accion]' ? [form] : [],
        },
        window: {
            zookiToast: mensaje => avisos.push(mensaje),
            zookiRecargarConAviso: mensaje => recargas.push(mensaje),
            zookiRedirigirConAviso: (url, mensaje) => destinos.push({ url, mensaje }),
        },
        FormData: class {
            get() { return 'valor'; }
            set() {}
        },
        fetch: async () => ({ json: async () => ({ success: true, message: 'Contraseña actualizada.' }) }),
    };
    vm.runInNewContext(codigo('public/js/cuenta-password.js'), contexto);
    await enviar({ preventDefault() {}, submitter: boton });
    return { form, avisos, destinos, recargas, boton };
}

test('portal: cambiar contraseña muestra toast sin bloquear la pantalla', async () => {
    const pagina = await cambiar('portalChangePasswordForm', 'portal_cambiar_password_ajax', true);
    assert.deepEqual(pagina.avisos, ['Contraseña actualizada.']);
    assert.equal(pagina.form.restablecido, true);
    assert.equal(pagina.boton.disabled, false);
});

test('portal: crear contraseña lleva el toast a la recarga inmediata', async () => {
    const pagina = await cambiar('portalChangePasswordForm', 'portal_cambiar_password_ajax', false);
    assert.deepEqual(pagina.recargas, ['Contraseña creada.']);
});

test('cambio obligatorio: el aviso viaja al dashboard y no demora la redirección', async () => {
    const pagina = await cambiar('changePasswordForm', 'cambiar_password_ajax', true);
    assert.deepEqual(pagina.destinos, [{ url: 'index.php?action=dashboard', mensaje: 'Contraseña actualizada.' }]);
});

test('el toast de redirección se recupera una sola vez y conserva SweetAlert2 no bloqueante', () => {
    const almacen = new Map();
    const toasts = [];
    let iniciar;
    const contexto = {
        window: { Swal: {}, location: { href: '' } },
        Swal: { fire: opciones => toasts.push(opciones) },
        document: { addEventListener(tipo, callback) { iniciar = callback; } },
        sessionStorage: {
            setItem: (clave, valor) => almacen.set(clave, valor),
            getItem: clave => almacen.get(clave),
            removeItem: clave => almacen.delete(clave),
        },
    };
    vm.runInNewContext(codigo('public/js/avisos.js'), contexto);
    contexto.zookiRedirigirConAviso('index.php?action=dashboard', 'Contraseña creada.');
    assert.equal(contexto.window.location.href, 'index.php?action=dashboard');
    iniciar();
    iniciar();
    assert.equal(toasts.length, 1);
    assert.equal(toasts[0].toast, true);
    assert.equal(toasts[0].showConfirmButton, false);
    assert.equal(toasts[0].title, 'Contraseña creada.');
});

test('registro de clínicas conserva los componentes de acceso y no incluye Google ni código en línea', () => {
    const vista = codigo('views/auth/registro_clinica.php');
    assert.match(vista, /login-hero/);
    assert.match(vista, /data-validacion-cuenta/);
    assert.match(vista, /data-turnstile-formulario/);
    assert.doesNotMatch(vista, /gsi\/client|btnGoogle|\sstyle=|\son[a-z]+=|<script>\s*\S/);
});

test('cada formulario renueva únicamente su widget de Turnstile y no crea duplicados', () => {
    const propietario = { dataset: { sitekey: 'prueba' }, closest: () => null };
    const clinica = { dataset: { sitekey: 'prueba' }, closest: () => null };
    const renderizados = [];
    const renovados = [];
    let iniciar;
    const contexto = {
        document: {
            querySelectorAll: () => [propietario, clinica],
            addEventListener(tipo, callback) { iniciar = callback; },
        },
        window: {
            turnstile: {
                render(contenedor) {
                    renderizados.push(contenedor);
                    return 'widget-' + renderizados.length;
                },
                reset: id => renovados.push(id),
            },
        },
    };
    vm.runInNewContext(codigo('public/js/turnstile-formularios.js'), contexto);
    iniciar();
    contexto.window.zookiTurnstileListo();
    contexto.window.ZookiTurnstile.reiniciar({ querySelector: () => propietario });
    assert.equal(renderizados.length, 2);
    assert.deepEqual(renovados, ['widget-1']);
});

for (const crear of [false, true]) {
    test('Mi perfil: ' + (crear ? 'crear contraseña conserva el toast al recargar' : 'cambiar contraseña muestra toast'), async () => {
        const elemento = () => ({
            value: '', textContent: '', dataset: {}, elements: {}, eventos: {},
            addEventListener(tipo, callback) { this.eventos[tipo] = callback; },
            querySelectorAll: () => [], reset() {},
        });
        const nombres = ['perfilContactoForm', 'perfilEmail', 'perfilTelefono', 'perfilContactoGuardar', 'perfilContactoMsg',
            'perfilPasswordForm', 'perfilPwdActual', 'perfilPwdNueva', 'perfilPwdConfirmar', 'perfilPasswordGuardar', 'perfilPasswordMsg'];
        const nodos = Object.fromEntries(nombres.map(nombre => [nombre, elemento()]));
        if (crear) delete nodos.perfilPwdActual;
        const avisos = [];
        const recargas = [];
        const contexto = {
            document: { getElementById: id => nodos[id] || null },
            window: { location: { hash: '' }, zookiToast: mensaje => avisos.push(mensaje) },
            zookiRecargarConAviso: mensaje => recargas.push(mensaje),
            FormData,
            fetch: async () => ({ json: async () => ({ success: true }) }),
            setTimeout: callback => callback(),
            console,
        };
        vm.runInNewContext(codigo('public/js/perfil.js'), contexto);
        nodos.perfilPwdNueva.value = 'Huellita#Segura2026';
        await nodos.perfilPasswordForm.eventos.submit({ preventDefault() {} });
        assert.deepEqual(crear ? recargas : avisos, [crear ? 'Contraseña creada.' : 'Contraseña actualizada.']);
    });
}

test('autorregistro: éxito genérico conserva la pantalla común y siempre renueva el CAPTCHA', async () => {
    // M0.1: register.js envía con la ventana común de registro-cuenta.js.
    const eventos = {};
    const estados = [{ dataset: { esperaEstado: 'cargando' }, hidden: false }, { dataset: { esperaEstado: 'listo' }, hidden: true }];
    const mensaje = { textContent: '' };
    const ventana = { hidden: true, querySelectorAll: () => estados, querySelector: () => mensaje };
    const resumen = { hidden: true, textContent: '' };
    const formulario = {
        hidden: false,
        dataset: { registroEspera: 'registroEspera' },
        querySelector: () => resumen,
        addEventListener(tipo, callback) { eventos[tipo] = callback; },
    };
    const nodos = { registerForm: formulario, registroEspera: ventana };
    let completar;
    const renovados = [];
    const contexto = {
        document: { addEventListener(tipo, callback) { callback(); }, getElementById: id => nodos[id] || null, querySelectorAll: () => [] },
        FormData: class {},
        fetch: async () => {
            await new Promise(resolve => { completar = resolve; });
            return { headers: { get: () => 'application/json' }, json: async () => ({ success: true, message: 'Revisa tu correo.' }) };
        },
    };
    contexto.window = contexto;
    contexto.ZookiTurnstile = { reiniciar: form => renovados.push(form) };
    vm.runInNewContext(codigo('public/js/registro-cuenta.js'), contexto);
    vm.runInNewContext(codigo('public/js/register.js'), contexto);
    eventos.submit({ preventDefault() {} });
    assert.equal(ventana.hidden, false, 'La ventana de carga aparece al enviar.');
    assert.equal(estados[0].hidden, false);
    completar();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(mensaje.textContent, 'Revisa tu correo.');
    assert.equal(estados[0].hidden, true);
    assert.equal(estados[1].hidden, false);
    assert.equal(formulario.hidden, true);
    assert.deepEqual(renovados, [formulario]);
    assert.doesNotMatch(codigo('public/js/register.js'), /estado_verificacion_ajax|datos\.iniciar_sesion|datos\.esperando_confirmacion/);
});
