const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { execFileSync } = require('node:child_process');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');
const fuente = fs.readFileSync('public/js/carnet.js', 'utf8');

function entorno() {
    const avisos = [];
    const envios = [];
    const panel = { innerHTML: '', textContent: '' };
    const tarjeta = { dataset: { enlace: 'https://zooki.example/index.php?action=carnet&t=token', idMascota: '1', tokenVisto: 'anterior' } };
    const boton = { disabled: false, closest: () => tarjeta };
    const escuchas = {};
    const contexto = {
        navigator: {},
        document: { getElementById: () => panel, addEventListener: (tipo, funcion) => { escuchas[tipo] = funcion; } },
        zookiToast: (...datos) => avisos.push(datos),
        zookiConfirmarCorto: async () => true,
        postPortal: async (...datos) => { envios.push(datos); return { success: true, regenerado: true, html: 'nuevo QR' }; },
        print: () => { contexto.impresiones = (contexto.impresiones || 0) + 1; },
        fetch: async () => ({ ok: true, json: async () => ({ success: true, html: 'tarjeta' }) })
    };
    contexto.window = contexto;
    vm.runInNewContext(fuente, contexto);
    return { contexto, avisos, envios, panel, boton, tarjeta, escuchas };
}

test('Compartir usa Web Share; sin soporte copia el mismo enlace y avisa', async () => {
    const e = entorno();
    let compartido;
    e.contexto.navigator.share = async datos => { compartido = datos; };
    await e.contexto.ZookiCarnet.compartir(e.tarjeta.dataset.enlace);
    assert.equal(compartido.url, e.tarjeta.dataset.enlace);
    assert.equal(e.avisos.length, 0);
    delete e.contexto.navigator.share;
    let copiado;
    e.contexto.navigator.clipboard = { writeText: async texto => { copiado = texto; } };
    await e.contexto.ZookiCarnet.compartir(e.tarjeta.dataset.enlace);
    assert.equal(copiado, e.tarjeta.dataset.enlace);
    assert.match(e.avisos[0][0], /copiado/);
});

test('Cancelar compartir no muestra error; fallo del portapapeles se comunica', async () => {
    const e = entorno();
    e.contexto.navigator.share = async () => { throw { name: 'AbortError' }; };
    await e.contexto.ZookiCarnet.compartir('url');
    assert.equal(e.avisos.length, 0);
    delete e.contexto.navigator.share;
    await e.contexto.ZookiCarnet.compartir('url');
    assert.equal(e.avisos[0][1], 'error');
});

test('Regenerar confirma antes de escribir, bloquea doble clic y pinta al responder', async () => {
    const e = entorno();
    let resolver;
    e.contexto.zookiConfirmarCorto = () => new Promise(r => { resolver = r; });
    const primera = e.contexto.ZookiCarnet.regenerar(e.boton);
    assert.equal(e.boton.disabled, true);
    await e.contexto.ZookiCarnet.regenerar(e.boton);
    assert.equal(e.envios.length, 0);
    resolver(true);
    await primera;
    assert.equal(e.envios.length, 1);
    assert.equal(e.envios[0][0], 'portal_regenerar_carnet_ajax');
    assert.equal(e.envios[0][1].token_visto, 'anterior');
    assert.equal(e.panel.innerHTML, 'nuevo QR');
    assert.equal(e.boton.disabled, false);
});

test('Cancelar regeneración no envía; error conserva la tarjeta y libera el botón', async () => {
    const e = entorno();
    e.contexto.zookiConfirmarCorto = async () => false;
    await e.contexto.ZookiCarnet.regenerar(e.boton);
    assert.equal(e.envios.length, 0);
    e.contexto.zookiConfirmarCorto = async () => true;
    e.contexto.postPortal = async () => ({ success: false, message: 'Error de prueba' });
    await e.contexto.ZookiCarnet.regenerar(e.boton);
    assert.equal(e.panel.innerHTML, '');
    assert.equal(e.avisos[0][0], 'Error de prueba');
    assert.equal(e.boton.disabled, false);
});

test('Cambiar de mascota descarta una carga y una confirmación anteriores', async () => {
    const e = entorno();
    const pendientes = [];
    e.contexto.fetch = () => new Promise(r => pendientes.push(r));
    const primera = e.contexto.ZookiCarnet.cargar(1);
    const segunda = e.contexto.ZookiCarnet.cargar(2);
    pendientes[1]({ ok: true, json: async () => ({ success: true, html: 'mascota dos' }) });
    await segunda;
    pendientes[0]({ ok: true, json: async () => ({ success: true, html: 'mascota uno' }) });
    await primera;
    assert.equal(e.panel.innerHTML, 'mascota dos');
    let confirmar;
    e.contexto.zookiConfirmarCorto = () => new Promise(r => { confirmar = r; });
    const renovacion = e.contexto.ZookiCarnet.regenerar(e.boton);
    const tercera = e.contexto.ZookiCarnet.cargar(3);
    confirmar(true);
    await renovacion;
    assert.equal(e.envios.length, 0);
    pendientes[2]({ ok: true, json: async () => ({ success: true, html: 'mascota tres' }) });
    await tercera;
});

test('Imprimir delega en window.print sin estilos ni eventos en línea', () => {
    const e = entorno();
    const boton = { hasAttribute: nombre => nombre === 'data-carnet-imprimir' };
    e.escuchas.click({ target: { closest: () => boton } });
    assert.equal(e.contexto.impresiones, 1);
    for (const archivo of ['views/portal/carnet.php', 'views/portal/imprimir_carnet.php']) {
        assert.doesNotMatch(fs.readFileSync(archivo, 'utf8'), /\s(?:style|on\w+)\s*=/i);
    }
    assert.match(fs.readFileSync('public/css/portal/carnet.css', 'utf8'), /@media print/);
});

test('Carnet del propietario en Chrome a 1366/390 px e impresión A6', { skip: !buscarChrome() }, async () => {
    const php = process.env.PHP_BINARY || (process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php');
    const vistas = JSON.parse(execFileSync(php, ['tests/Frontend/apoyo/carnet-vista.php'], { encoding: 'utf8' }));
    assert.equal((vistas.tarjeta.match(/<time>/g) || []).length, 10);
    assert.match(vistas.tarjeta, /Ver en el mapa/);
    assert.match(vistas.tarjeta, /23\/10\/2026 12:11:00/);
    assert.doesNotMatch(vistas.tarjeta, /\s(?:style|on\w+)\s*=/i);
    const navegador = await abrirChrome(buscarChrome());
    try {
        const cssCarnet = fs.readFileSync('public/css/portal/carnet.css', 'utf8');
        const reemplazarRecursos = html => html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '').replace(/<link\b[^>]*>/gi, '');
        const cssPortal = fs.readFileSync('public/css/usuarios.css', 'utf8') + ['base', 'navegacion', 'componentes', 'inicio', 'agenda', 'mascota', 'cuenta', 'ventanas', 'calendario', 'formularios'].map(p => fs.readFileSync(`public/css/portal/${p}.css`, 'utf8')).join('\n');
        const arbol = await navegador.enviar('Page.getFrameTree');
        const documento = reemplazarRecursos(vistas.portal).replace('</head>', `<style>${cssPortal}\n${cssCarnet}</style></head>`);
        await navegador.enviar('Page.setDocumentContent', { frameId: arbol.frameTree.frame.id, html: documento });
        const portalJs = fs.readFileSync('public/js/portal.js', 'utf8');
        await navegador.evaluar(fs.readFileSync('public/js/avisos.js', 'utf8'));
        await navegador.evaluar(portalJs);
        await navegador.evaluar(fuente);
        await navegador.evaluar(`window.__enviosCarnet = 0; window.__avisosCarnet = []; zookiToast = (...datos) => __avisosCarnet.push(datos); postPortal = async () => { __enviosCarnet++; return { success: true, regenerado: true, html: ${JSON.stringify(vistas.tarjeta)} }; };`);
        await navegador.evaluar('renderSummary({nombre:"Luna",fecha_nacimiento:"2020-01-01",sexo:"Hembra",peso:12});');
        await navegador.evaluar(`
            document.getElementById('carnetContenido').innerHTML = ${JSON.stringify(vistas.tarjeta)};
            document.querySelectorAll('.portal-panel').forEach(p => p.hidden = p.id !== 'tab-carnet');
            openDrawer();
            document.getElementById('drawerPetTitle').textContent = 'Luna';
            document.querySelector('[data-pestana="tab-carnet"]').click();
            document.querySelector('.carnet-qr').src = 'data:image/svg+xml;base64,' + ${JSON.stringify(Buffer.from(vistas.svg).toString('base64'))};
        `);
        for (const ancho of [1366, 390]) {
            await navegador.enviar('Emulation.setDeviceMetricsOverride', { width: ancho, height: 900, deviceScaleFactor: 1, mobile: ancho < 768 });
            const geometria = await navegador.evaluar(`(() => {
                const tarjeta = document.querySelector('.carnet-tarjeta');
                const r = tarjeta.getBoundingClientRect();
                return { ancho: r.width, scroll: tarjeta.scrollWidth, cliente: tarjeta.clientWidth, qr: document.querySelector('.carnet-qr').naturalWidth };
            })()`);
            assert.ok(geometria.scroll <= geometria.cliente + 1, JSON.stringify(geometria));
            assert.ok(geometria.ancho > 0, 'La tarjeta debe estar visible');
            assert.ok(geometria.ancho >= 320, 'El carnet debe tener espacio suficiente para leerlo');
            assert.equal(await navegador.evaluar('document.getElementById("tab-carnet").hidden'), false);
            assert.equal(await navegador.evaluar('document.getElementById("tab-historial").hidden'), true);
            assert.equal(await navegador.evaluar('document.documentElement.scrollWidth <= innerWidth'), true, 'Sin desborde horizontal del portal');
            await navegador.evaluar('document.querySelector("[data-carnet-regenerar]").click()');
            assert.equal(await navegador.evaluar('document.querySelector(".zooki-confirmacion__panel").getAttribute("role")'), 'alertdialog');
            await navegador.evaluar('document.querySelector(".zooki-confirmacion .btn-modal-secondary").click()');
            await navegador.esperar('!document.querySelector("[data-carnet-regenerar]").disabled');
            assert.equal(await navegador.evaluar('__enviosCarnet'), 0);
            if (process.env.ZOOKI_CARNET_EVIDENCIA) {
                fs.mkdirSync(process.env.ZOOKI_CARNET_EVIDENCIA, { recursive: true });
                fs.writeFileSync(`${process.env.ZOOKI_CARNET_EVIDENCIA}/carnet-${ancho}.png`, Buffer.from((await navegador.enviar('Page.captureScreenshot', { captureBeyondViewport: true })).data, 'base64'));
            }
        }
        await navegador.evaluar('document.querySelector("[data-carnet-regenerar]").click(); document.querySelector(".zooki-confirmacion .btn-modal-primary").click();');
        await navegador.esperar('__enviosCarnet === 1 && __avisosCarnet.length === 1');
        await navegador.enviar('Page.setDocumentContent', { frameId: arbol.frameTree.frame.id, html: reemplazarRecursos(vistas.impresion).replace('</head>', `<style>${cssCarnet}</style></head>`) });
        await navegador.evaluar(`document.querySelector('.carnet-qr').src = 'data:image/svg+xml;base64,' + ${JSON.stringify(Buffer.from(vistas.svg).toString('base64'))}`);
        await navegador.esperar('document.querySelector(".carnet-qr").complete && document.querySelector(".carnet-qr").naturalWidth > 0');
        await navegador.enviar('Emulation.setEmulatedMedia', { media: 'print' });
        const impresion = await navegador.evaluar(`({ ancho: document.querySelector('.carnet-tarjeta').getBoundingClientRect().width, barra: getComputedStyle(document.querySelector('.carnet-barra-impresion')).display })`);
        assert.ok(Math.abs(impresion.ancho - 148 * 96 / 25.4) < 1);
        assert.equal(impresion.barra, 'none');
        if (process.env.ZOOKI_CARNET_EVIDENCIA) fs.writeFileSync(`${process.env.ZOOKI_CARNET_EVIDENCIA}/carnet-impresion.png`, Buffer.from((await navegador.enviar('Page.captureScreenshot', { captureBeyondViewport: true })).data, 'base64'));
        assert.deepEqual(navegador.errores, []);
    } finally {
        await navegador.cerrar();
    }
});
