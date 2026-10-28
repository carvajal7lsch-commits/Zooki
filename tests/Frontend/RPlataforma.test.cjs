const test = require('node:test');
const assert = require('node:assert/strict');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');
const { servidorPlataforma } = require('./apoyo/plataforma-r-servidor.cjs');

// RE-2.12.6: el CI verifica presentación e interacción sin necesitar una GPU.
function instalarMapaFalso() {
    const controles = {};
    const valores = {};
    let escena;
    escena = new Proxy({}, {
        get: (_, clave) => {
            if (clave === 'controls') return () => controles;
            if (clave === 'd3Force') return () => ({ strength: () => {} });
            if (clave === 'graph2ScreenCoords') return () => ({ x: 80, y: 80 });
            return (...args) => {
                if (!args.length) return valores[clave];
                valores[clave] = args[0];
                if (clave === 'graphData') {
                    args[0].nodes.forEach((nodo, indice) => {
                        nodo.x = indice + 1;
                        nodo.y = indice + 2;
                        nodo.z = indice + 3;
                    });
                }
                return escena;
            };
        },
    });
    window.ForceGraph3D = () => () => escena;
    window.mapaPrueba = valores;
    HTMLCanvasElement.prototype.getContext = () => ({});
}

async function encuadrar(nav, ancho) {
    await nav.enviar('Emulation.setDeviceMetricsOverride', {
        width: ancho,
        height: ancho < 768 ? 844 : 768,
        deviceScaleFactor: 1,
        mobile: ancho < 768,
    });
}

async function sinDesborde(nav, contexto) {
    await nav.evaluar('new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)))');
    const medida = await nav.evaluar('({ pagina:document.documentElement.scrollWidth, ventana:innerWidth })');
    assert.ok(medida.pagina <= medida.ventana + 1, contexto + ': ' + JSON.stringify(medida));
}

test('R plataforma: hoja, campana autorizada y detalle con sus tres pestañas a 360/390/820/1366', { skip: !buscarChrome() }, async () => {
    const servidor = await servidorPlataforma();
    const nav = await abrirChrome(buscarChrome());
    try {
        for (const ancho of [360, 390, 820, 1366]) {
            await encuadrar(nav, ancho);
            await nav.enviar('Page.navigate', { url: servidor.url });
            await nav.esperar("document.querySelector('[data-finanzas-conteo]')?.textContent==='1'");
            await nav.evaluar("document.querySelector('[data-pestana=clinicas]').click()");
            await sinDesborde(nav, 'Clínicas ' + ancho);
            assert.equal(await nav.evaluar(`(() => {
                const titulo=document.querySelector('.plataforma-cabecera__titulo').getBoundingClientRect();
                const cuenta=document.querySelector('.plataforma-cabecera__cuenta').getBoundingClientRect();
                return titulo.right<=cuenta.left;
            })()`), true, 'Cabecera sin solapamiento ' + ancho);
            await nav.evaluar("document.querySelector('.plataforma-menu-cuenta').open=true");
            await sinDesborde(nav, 'Campana ' + ancho);
            assert.match(await nav.evaluar("document.querySelector('.plataforma-menu-cuenta nav').textContent"), /Casos de soporte.*Seguridad/);
            await nav.evaluar("location.hash='seguridad'");
            await nav.esperar("document.querySelector('[data-pestana=seguridad]').getAttribute('aria-selected')==='true'");
            await nav.evaluar("document.querySelector('[data-pestana=clinicas]').click()");
            await nav.evaluar("document.querySelector('.plataforma-menu-cuenta').open=false; document.querySelector('[data-clinica-fila]').click()");
            await nav.esperar("document.querySelector('#gestionarClinica').classList.contains('is-open')");
            for (const pestana of ['plan', 'estado', 'pagos']) {
                await nav.evaluar(`document.querySelector('[data-modal-pestana="${pestana}"]').click()`);
                await sinDesborde(nav, 'Detalle ' + pestana + ' ' + ancho);
                assert.equal(await nav.evaluar(`(() => {
                    const hoja=document.querySelector('#gestionarClinica .plataforma-modal');
                    return hoja.scrollWidth<=hoja.clientWidth+1;
                })()`), true, 'Hoja sin recortes ' + ancho);
            }
            await nav.evaluar("document.querySelector('#gestionarClinica [data-cerrar-dialogo]').click()");
            for (const pestana of ['soporte', 'seguridad']) {
                await nav.evaluar(`document.querySelector('[data-pestana="${pestana}"]').click()`);
                await sinDesborde(nav, pestana + ' ' + ancho);
            }
        }
        assert.deepEqual(nav.errores, []);
    } finally {
        await nav.cerrar();
        await servidor.cerrar();
    }
});

test('R conocimiento: mapa/ficha, Plano/Lista y etiquetas seguras sin WebGL real a 360/390/820/1366', { skip: !buscarChrome() }, async () => {
    const servidor = await servidorPlataforma(true);
    const nav = await abrirChrome(buscarChrome());
    try {
        await nav.enviar('Network.enable');
        await nav.enviar('Network.setBlockedURLs', { urls: ['*3d-force-graph-1.80.0.min.js*'] });
        await nav.enviar('Page.addScriptToEvaluateOnNewDocument', { source: '(' + instalarMapaFalso.toString() + ')()' });
        for (const ancho of [360, 390, 820, 1366]) {
            await encuadrar(nav, ancho);
            await nav.enviar('Page.navigate', { url: servidor.url });
            await nav.esperar('window.mapaPrueba?.graphData?.nodes.length>0');
            await sinDesborde(nav, 'Conocimiento ' + ancho);
            assert.equal(await nav.evaluar("document.querySelector('[data-grafo-aviso-cdn]').hidden"), true);
            if (ancho < 768) {
                assert.ok(await nav.evaluar("document.querySelector('[data-grafo-mapa]').clientHeight" ) > 300, 'El mapa conserva espacio en celular');
                assert.equal(await nav.evaluar(`document.querySelector('.grafo-lienzo').getBoundingClientRect().bottom<=document.querySelector('.pill-sidebar').getBoundingClientRect().top`), true, 'La barra inferior no tapa el mapa ni su contador');
                assert.equal(await nav.evaluar(`(() => {
                    const buscar=document.querySelector('.grafo-buscador').getBoundingClientRect();
                    const especie=document.querySelector('[data-grafo-especie]').getBoundingClientRect();
                    return buscar.width>100 && especie.width>100 && Math.abs(buscar.top-especie.top)<2;
                })()`), true, 'Buscar y especie en la misma fila visible');
            }
            await nav.evaluar(`(() => {
                const buscar=document.querySelector('[data-grafo-buscar]');
                buscar.value='Vómito';
                buscar.dispatchEvent(new Event('input',{bubbles:true}));
                document.querySelector('[data-sugerencia]').click();
            })()`);
            await nav.esperar("!document.querySelector('[data-grafo-detalle]').hidden");
            await sinDesborde(nav, 'Ficha conocimiento ' + ancho);
            assert.match(await nav.evaluar("document.querySelector('[data-grafo-detalle]').textContent"), /Fuentes|fuentes/);
            if (ancho < 768) {
                assert.equal(await nav.evaluar(`document.querySelector('[data-grafo-detalle]').getBoundingClientRect().bottom<=document.querySelector('.pill-sidebar').getBoundingClientRect().top`), true, 'La barra inferior no tapa la ficha');
                assert.equal(await nav.evaluar(`window.mapaPrueba.height<=Math.max(100,document.querySelector('[data-grafo-detalle]').getBoundingClientRect().top-document.querySelector('[data-grafo-mapa]').getBoundingClientRect().top)+1`), true, 'La cámara encuadra el nodo en el área que no tapa la hoja');
            }
            await nav.evaluar("document.querySelector('[data-grafo-todo]').click(); document.querySelector('[data-vista-boton=plano]').click()");
            assert.equal(await nav.evaluar('window.mapaPrueba.numDimensions'), 2);
            await nav.evaluar("document.querySelector('[data-vista-boton=lista]').click()");
            await sinDesborde(nav, 'Lista conocimiento ' + ancho);
            assert.equal(await nav.evaluar("document.querySelector('[data-vista-grafo=lista]').hidden"), false);
        }
        assert.deepEqual(nav.errores, []);
    } finally {
        await nav.cerrar();
        await servidor.cerrar();
    }
});
