const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const codigo = fs.readFileSync(path.join(__dirname, '../../public/js/confirmacion-google.js'), 'utf8');

function pagina(conGoogle = true) {
    const salida = { textContent: '' };
    const token = { value: 'token-anterior' };
    const form = { elements: { access_token: token }, querySelector: () => salida, dataset: {} };
    const eventos = {};
    const boton = { dataset: { googleClient: 'cliente-zooki' }, closest: () => form, addEventListener: (evento, accion) => { eventos[evento] = accion; } };
    const llamadas = {};
    const window = conGoogle ? { google: { accounts: { oauth2: {
        initTokenClient: opciones => {
            llamadas.opciones = opciones;
            return { requestAccessToken: datos => { llamadas.solicitud = datos; } };
        }
    } } } } : {};
    const document = { querySelectorAll: () => [boton], addEventListener: (evento, accion) => accion() };
    vm.runInNewContext(codigo, { document, window });
    return { salida, token, boton, llamadas, eventos };
}

test('pide otra confirmación de Google y solo transmite el token de la respuesta nueva', () => {
    const p = pagina();
    p.eventos.click();
    assert.equal(p.token.value, '');
    assert.equal(p.boton.disabled, true);
    assert.equal(p.llamadas.opciones.client_id, 'cliente-zooki');
    assert.equal(p.llamadas.solicitud.prompt, 'select_account');
    p.llamadas.opciones.callback({ access_token: 'token-nuevo' });
    assert.equal(p.token.value, 'token-nuevo');
    assert.equal(p.boton.disabled, false);
});

test('si Google no carga o el titular cierra la ventana, no se conserva un token anterior', () => {
    const sinGoogle = pagina(false);
    sinGoogle.eventos.click();
    assert.equal(sinGoogle.token.value, '');
    assert.match(sinGoogle.salida.textContent, /No se pudo cargar Google/);
    const cancelada = pagina();
    cancelada.eventos.click();
    cancelada.llamadas.opciones.error_callback();
    assert.equal(cancelada.token.value, '');
    assert.equal(cancelada.boton.disabled, false);
    assert.match(cancelada.salida.textContent, /No se pudo confirmar/);
});
