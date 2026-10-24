const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const codigo = fs.readFileSync('public/js/carnet-publico.js', 'utf8');

function entorno() {
    const eventos = {};
    const mensaje = { textContent: '' };
    const boton = { disabled: false, dataset: { token: 'token-opaco', comprobante: 'firma' } };
    const envios = [];
    const contexto = {
        document: { addEventListener: (tipo, funcion) => { eventos[tipo] = funcion; }, querySelector: () => mensaje },
        navigator: {}, URLSearchParams, Number, Promise,
        fetch: async (url, opciones) => {
            envios.push({ url, opciones });
            return { ok: true, json: async () => ({ success: true, message: 'Solicitud recibida.' }) };
        }
    };
    vm.runInNewContext(codigo, contexto);
    return { contexto, envios, mensaje, boton, clic: () => eventos.click({ target: { closest: () => boton } }) };
}

test('La ubicación solo se solicita al tocar y solo se envía al aceptar, con seis decimales', async () => {
    const e = entorno();
    let aceptar;
    let solicitudes = 0;
    e.contexto.navigator.geolocation = { getCurrentPosition: resolver => {
        solicitudes++;
        aceptar = resolver;
    } };
    assert.equal(solicitudes, 0);
    assert.equal(e.envios.length, 0);
    const clic = e.clic();
    assert.equal(solicitudes, 1);
    assert.equal(e.boton.disabled, true);
    await e.clic();
    assert.equal(solicitudes, 1);
    assert.equal(e.envios.length, 0);
    aceptar({ coords: { latitude: 2.123456789, longitude: -75.987654321 } });
    await clic;
    assert.equal(e.envios.length, 1);
    const cuerpo = e.envios[0].opciones.body;
    assert.equal(cuerpo.get('t'), 'token-opaco');
    assert.equal(cuerpo.get('comprobante'), 'firma');
    assert.equal(cuerpo.get('latitud'), '2.123457');
    assert.equal(cuerpo.get('longitud'), '-75.987654');
    assert.equal(e.envios[0].opciones.credentials, 'omit');
    assert.equal(e.mensaje.textContent, 'Solicitud recibida.');
    assert.equal(e.boton.disabled, false);
});

test('Sin geolocalización o al rechazar no se envía ninguna petición', async () => {
    const e = entorno();
    await e.clic();
    assert.match(e.mensaje.textContent, /no permite/);
    assert.equal(e.envios.length, 0);
    e.contexto.navigator.geolocation = { getCurrentPosition: (_, rechazar) => rechazar({ code: 1 }) };
    await e.clic();
    assert.match(e.mensaje.textContent, /No compartiste/);
    assert.equal(e.envios.length, 0);
    assert.equal(e.boton.disabled, false);
});

test('Error de red, coordenadas inválidas y límite se explican junto al botón', async () => {
    const e = entorno();
    e.contexto.navigator.geolocation = { getCurrentPosition: resolver => resolver({ coords: { latitude: 100, longitude: 1 } }) };
    await e.clic();
    assert.equal(e.envios.length, 0);
    e.contexto.navigator.geolocation = { getCurrentPosition: resolver => resolver({ coords: { latitude: 1, longitude: 2 } }) };
    e.contexto.fetch = async () => { throw new Error('Red'); };
    await e.clic();
    assert.match(e.mensaje.textContent, /No se pudo/);
    assert.equal(e.boton.disabled, false);
    e.contexto.fetch = async () => ({ ok: false, json: async () => ({ success: false, message: 'Demasiadas consultas' }) });
    await e.clic();
    assert.match(e.mensaje.textContent, /Demasiadas consultas/);
    assert.doesNotMatch(codigo, /Swal|alert\(/);
});

test('Carnet conserva Historia primero y Carnet al final; imprimir es un botón sin subrayado', () => {
    const vista = fs.readFileSync('views/portal/index.php', 'utf8');
    const navegacion = vista.slice(vista.indexOf('data-pestanas="mascota"'), vista.indexOf('</nav>', vista.indexOf('data-pestanas="mascota"')));
    const ids = [...navegacion.matchAll(/data-pestana="([^"]+)"/g)].map(m => m[1]);
    assert.equal(ids[0], 'tab-historial');
    assert.equal(ids.at(-1), 'tab-carnet');
    assert.match(navegacion, /class="tab-btn active" data-pestana="tab-historial"/);
    assert.match(fs.readFileSync('public/css/portal/carnet.css', 'utf8'), /\.carnet-acciones a\s*\{[^}]*text-decoration: none/);
    for (const ruta of ['views/publico/carnet.php', 'views/portal/carnet.php']) assert.doesNotMatch(fs.readFileSync(ruta, 'utf8'), /\s(?:style|on\w+)\s*=/i);
});
