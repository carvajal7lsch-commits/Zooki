const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');
const { servidorNavegacion, rutasNavegacion } = require('./apoyo/navegacion-servidor.cjs');

function escenario() {
    const pendientes = [];
    const temporizadores = new Map();
    const atributos = new Map();
    const carga = { hidden: true };
    const area = {
        getAttribute: nombre => atributos.get(nombre) ?? null,
        setAttribute: (nombre, valor) => atributos.set(nombre, valor),
        removeAttribute: nombre => atributos.delete(nombre),
    };
    const document = { querySelector: () => area, getElementById: () => carga, addEventListener() {} };
    let secuencia = 0;
    const window = {
        fetch: (...argumentos) => new Promise((resolver, rechazar) => pendientes.push({ argumentos, resolver, rechazar })),
        setTimeout: (callback, demora) => {
            secuencia += 1;
            temporizadores.set(secuencia, { callback, demora });
            return secuencia;
        },
        clearTimeout: id => temporizadores.delete(id),
    };
    vm.runInNewContext(fs.readFileSync('public/js/carga-contenido.js', 'utf8'), { window, document });
    return { window, pendientes, temporizadores, carga, area, vencer: () => [...temporizadores.values()].forEach(t => t.callback()) };
}

test('Skeleton: una respuesta rápida no aparece ni deja la zona ocupada', async () => {
    const e = escenario();
    const respuesta = { ok: true };
    const peticion = e.window.fetch('/datos');
    assert.equal(e.carga.hidden, true);
    assert.equal(e.area.getAttribute('aria-busy'), 'true');
    assert.equal([...e.temporizadores.values()][0].demora, 400);
    e.pendientes[0].resolver(respuesta);
    assert.equal(await peticion, respuesta);
    assert.equal(e.temporizadores.size, 0);
    assert.equal(e.carga.hidden, true);
    assert.equal(e.area.getAttribute('aria-busy'), null);
});

test('Skeleton: espera a las peticiones concurrentes y conserva el aria-busy anterior', async () => {
    const e = escenario();
    e.area.setAttribute('aria-busy', 'false');
    const primera = e.window.fetch('/primera');
    const segunda = e.window.fetch('/segunda');
    assert.equal(e.temporizadores.size, 1);
    e.vencer();
    assert.equal(e.carga.hidden, false);
    e.pendientes[0].resolver({});
    await primera;
    assert.equal(e.carga.hidden, false);
    e.pendientes[1].resolver({});
    await segunda;
    assert.equal(e.carga.hidden, true);
    assert.equal(e.area.getAttribute('aria-busy'), 'false');
});

test('Skeleton: las peticiones de fondo no ocupan ni cubren el contenido', async () => {
    const e = escenario();
    const opciones = { sinLoader: true };
    const peticion = e.window.fetch('/avisos', opciones);
    assert.equal(e.temporizadores.size, 0);
    assert.equal(e.area.getAttribute('aria-busy'), null);
    assert.equal(e.pendientes[0].argumentos[1], opciones);
    e.pendientes[0].resolver({});
    await peticion;
    assert.equal(e.carga.hidden, true);
});

test('Skeleton: un fallo o un aborto quitan la espera y conservan el error original', async () => {
    for (const nombre of ['Error', 'AbortError']) {
        const e = escenario();
        const error = new Error('Petición interrumpida');
        error.name = nombre;
        const peticion = e.window.fetch('/datos');
        e.vencer();
        e.pendientes[0].rechazar(error);
        await assert.rejects(peticion, recibido => recibido === error);
        assert.equal(e.carga.hidden, true);
        assert.equal(e.area.getAttribute('aria-busy'), null);
    }
});

test('Skeleton en Chrome: tres roles, navegación disponible, campos conservados y móvil sin desborde', { skip: !buscarChrome() }, async () => {
    const nav = await abrirChrome(buscarChrome());
    try {
        for (const rol of ['admin', 'vet', 'plataforma']) {
            const servidor = await servidorNavegacion(rol, { demoraAjax: 1000 });
            try {
                await nav.enviar('Emulation.setDeviceMetricsOverride', { width: 1366, height: 768, deviceScaleFactor: 1, mobile: false });
                await nav.enviar('Page.navigate', { url: servidor.url + '/index.php?action=' + rutasNavegacion[rol][0] });
                await nav.esperar("document.readyState==='complete' && document.zookiCargaContenidoRegistrada");
                // La consulta inicial de notificaciones tarda más del umbral y sigue sin cubrir la pantalla.
                await nav.evaluar('new Promise(r => setTimeout(r, 450))');
                assert.equal(await nav.evaluar("document.getElementById('global-loader').hidden"), true);
                await nav.evaluar(`(() => {
                    const campo=document.querySelector('input[type="search"]');
                    if(campo){campo.value='dato que se conserva';campo.focus()}
                    window.cargaTerminada=false;
                    void fetch('index.php?action=listar_propietarios_ajax').then(()=>{window.cargaTerminada=true});
                })()`);
                assert.equal(await nav.evaluar("document.getElementById('global-loader').hidden"), true);
                await nav.esperar("!document.getElementById('global-loader').hidden");
                const medida = await nav.evaluar(`(() => {
                    const carga=document.getElementById('global-loader'),r=carga.getBoundingClientRect();
                    const cabecera=document.querySelector('.top-header').getBoundingClientRect();
                    const item=document.querySelector('.pill-nav-item.active'),b=item.getBoundingClientRect();
                    return {display:getComputedStyle(carga).display,arriba:r.top,cabecera:cabecera.bottom,barraDisponible:Boolean(document.elementFromPoint(b.x+b.width/2,b.y+b.height/2)?.closest('.pill-sidebar')),ocupado:document.querySelector('.content-body').getAttribute('aria-busy'),desborde:document.documentElement.scrollWidth>innerWidth+1};
                })()`);
                assert.notEqual(medida.display, 'none');
                assert.ok(medida.arriba >= medida.cabecera - 1);
                assert.equal(medida.barraDisponible, true);
                assert.equal(medida.ocupado, 'true');
                assert.equal(medida.desborde, false);
                if (rol === 'admin') {
                    fs.mkdirSync('scratch/r/navegacion-interna', { recursive: true });
                    const captura = await nav.enviar('Page.captureScreenshot', { format: 'png' });
                    fs.writeFileSync('scratch/r/navegacion-interna/skeleton-1366.png', Buffer.from(captura.data, 'base64'));
                }
                await nav.esperar("window.cargaTerminada && document.getElementById('global-loader').hidden");
                assert.equal(await nav.evaluar("document.querySelector('input[type=search]')?.value || 'dato que se conserva'"), 'dato que se conserva');
                if (rol === 'admin') {
                    for (const width of [390, 360]) {
                        await nav.enviar('Emulation.setDeviceMetricsOverride', { width, height: 844, deviceScaleFactor: 1, mobile: true });
                        await nav.evaluar("void fetch('index.php?action=listar_propietarios_ajax')");
                        await nav.esperar("!document.getElementById('global-loader').hidden");
                        assert.equal(await nav.evaluar('document.documentElement.scrollWidth<=innerWidth+1'), true);
                        if (width === 390) {
                            const captura = await nav.enviar('Page.captureScreenshot', { format: 'png' });
                            fs.writeFileSync('scratch/r/navegacion-interna/skeleton-390.png', Buffer.from(captura.data, 'base64'));
                        }
                        await nav.esperar("document.getElementById('global-loader').hidden");
                    }
                    await nav.enviar('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
                    assert.equal(await nav.evaluar("getComputedStyle(document.querySelector('.carga-contenido__forma')).animationName"), 'none');
                    await nav.enviar('Emulation.setEmulatedMedia', { features: [] });
                }
            } finally {
                await servidor.cerrar();
            }
        }
        assert.deepEqual(nav.errores, []);
    } finally {
        await nav.cerrar();
    }
});
