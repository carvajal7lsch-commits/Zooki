const test = require('node:test');
const assert = require('node:assert/strict');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');
const { servidorPlataforma } = require('./apoyo/plataforma-r-servidor.cjs');
const { instalarMapaFalso } = require('./apoyo/grafo-falso.cjs');

async function pausaDiseño(nav) {
    await nav.evaluar('new Promise(r => setTimeout(r, 250))');
}

test('R mapa: compacto, detalles, columnas recordadas y fullscreen flotante a 1366/820/390', { skip: !buscarChrome() }, async () => {
    const servidor = await servidorPlataforma(true);
    const nav = await abrirChrome(buscarChrome());
    try {
        await nav.enviar('Network.enable');
        await nav.enviar('Network.setBlockedURLs', { urls: ['*3d-force-graph-1.80.0.min.js*'] });
        await nav.enviar('Page.addScriptToEvaluateOnNewDocument', { source: '(' + instalarMapaFalso.toString() + ')()' });
        await nav.enviar('Emulation.setDeviceMetricsOverride', { width: 1366, height: 768, deviceScaleFactor: 1, mobile: false });
        await nav.enviar('Page.navigate', { url: servidor.url });
        await nav.esperar('window.mapaPrueba?.graphData?.nodes.length>0');
        assert.equal(await nav.evaluar(`(() => {
            const p=document.querySelector('.grafo-filtros-lateral');
            return p.scrollHeight<=p.clientHeight+1;
        })()`), true, 'Filtros sin scroll interno con los details cerrados');
        assert.equal(await nav.evaluar("document.querySelector('.grafo-ayuda').open"), false);
        await nav.evaluar("document.querySelector('.grafo-ayuda').open=true; document.querySelector('.grafo-cobertura-detalle').open=true");
        assert.equal(await nav.evaluar("document.querySelectorAll('.grafo-leyenda li').length"), 4);
        assert.match(await nav.evaluar("document.querySelector('[data-grafo-franja]').textContent"), /Parcial/);
        await nav.evaluar("document.querySelector('.grafo-ayuda').open=false; document.querySelector('.grafo-cobertura-detalle').open=false");
        const ancho = () => nav.evaluar("document.querySelector('.grafo-lienzo').getBoundingClientRect().width");
        const inicial = await ancho();
        await nav.evaluar("document.querySelector('[data-grafo-lado=filtros]').click()");
        await pausaDiseño(nav);
        assert.ok(await ancho() > inicial + 200);
        await nav.enviar('Page.reload');
        await nav.esperar('window.mapaPrueba?.graphData?.nodes.length>0');
        assert.equal(await nav.evaluar("document.querySelector('[data-grafo-lado=filtros]').getAttribute('aria-pressed')"), 'false');
        await nav.evaluar("document.querySelector('[data-grafo-lado=ficha]').click()");
        await pausaDiseño(nav);
        assert.ok(await ancho() > inicial + 500);
        await nav.evaluar(`(() => {
            const b=document.querySelector('[data-grafo-buscar]');
            b.value='Vómito';
            b.dispatchEvent(new Event('input',{bubbles:true}));
            document.querySelector('[data-sugerencia]').click();
        })()`);
        assert.equal(await nav.evaluar("document.querySelector('[data-grafo-lado=ficha]').getAttribute('aria-pressed')"), 'false', 'Seleccionar respeta la ficha ocultada');
        assert.equal(await nav.evaluar("document.querySelector('[data-grafo-ver-ficha]').hidden"), false);
        await nav.evaluar("document.querySelector('[data-grafo-ver-ficha]').click()");
        await pausaDiseño(nav);
        assert.ok(await nav.evaluar("Math.max(...Array.from(document.querySelectorAll('.grafo-relacion')).filter(e=>getComputedStyle(e).display!=='none').map(e=>e.getBoundingClientRect().height))") <= 36);
        assert.equal(await nav.evaluar("Array.from(document.querySelectorAll('.grafo-reglas-desplegable')).every(e=>!e.open)"), true);
        const mas = await nav.evaluar("Array.from(document.querySelectorAll('.grafo-mas')).find(e=>!e.hidden)?.textContent");
        assert.match(mas, /Ver \d+ más/);
        await nav.evaluar("Array.from(document.querySelectorAll('.grafo-mas')).find(e=>!e.hidden).click()");
        assert.ok(await nav.evaluar("document.querySelector('.grafo-expandida').children.length") > 6);
        // Fullscreen real desde gesto de usuario; no se simula WebGL ni se altera la API de fullscreen.
        await nav.enviar('Runtime.evaluate', { expression: "document.querySelector('[data-grafo-pantalla]').click()", userGesture: true });
        await nav.esperar("document.querySelector('[data-grafo]').classList.contains('grafo--pantalla')");
        await pausaDiseño(nav);
        assert.equal(await nav.evaluar("document.querySelector('[data-grafo-lado=filtros]').getAttribute('aria-pressed')"), 'false');
        assert.equal(await nav.evaluar("document.querySelector('[data-grafo-lado=ficha]').getAttribute('aria-pressed')"), 'false');
        assert.equal(await nav.evaluar("document.querySelector('.grafo-mapa').clientWidth===innerWidth && document.querySelector('.grafo-mapa').clientHeight===innerHeight"), true);
        await nav.evaluar("document.querySelector('[data-nodo=\"diagnostico.parvovirosis_canina\"]')?.click() || window.mapaPrueba.onNodeClick(window.mapaPrueba.graphData.nodes.find(n=>n.tipo==='diagnostico'))");
        await pausaDiseño(nav);
        assert.equal(await nav.evaluar("document.querySelector('[data-grafo-lado=ficha]').getAttribute('aria-pressed')"), 'true');
        assert.equal(await nav.evaluar("getComputedStyle(document.querySelector('[data-grafo-detalle]')).position"), 'absolute');
        assert.equal(await nav.evaluar("document.querySelector('[data-grafo-detalle]').getBoundingClientRect().width"), 340);
        assert.equal(await nav.evaluar("document.querySelector('[data-grafo-detalle]').getBoundingClientRect().right===innerWidth-12"), true, 'La ficha flota a la derecha');
        await nav.evaluar('document.exitFullscreen()');
        await nav.esperar("!document.querySelector('[data-grafo]').classList.contains('grafo--pantalla')");
        await pausaDiseño(nav);
        assert.equal(await nav.evaluar("document.querySelector('[data-grafo-lado=filtros]').getAttribute('aria-pressed')"), 'false', 'Al salir restaura la elección normal');
        for (const width of [820, 390]) {
            await nav.enviar('Emulation.setDeviceMetricsOverride', { width, height: 844, deviceScaleFactor: 1, mobile: width < 768 });
            await nav.evaluar("localStorage.removeItem('zooki.grafo.lados.v1')");
            await nav.enviar('Page.reload');
            await nav.esperar('window.mapaPrueba?.graphData?.nodes.length>0');
            await pausaDiseño(nav);
            assert.equal(await nav.evaluar('document.documentElement.scrollWidth<=innerWidth+1'), true, 'Sin desborde a ' + width);
            if (width === 820) {
                assert.equal(await nav.evaluar(`(() => {
                    const barra=document.querySelector('.grafo-barra').getBoundingClientRect();
                    return Array.from(document.querySelectorAll('.grafo-barra button')).every(e=>{
                        const r=e.getBoundingClientRect();
                        return r.left>=barra.left && r.right<=barra.right;
                    });
                })()`), true, 'Los controles del mapa caben en tableta');
            }
        }
        await nav.enviar('Page.addScriptToEvaluateOnNewDocument', { source: "Object.defineProperty(window,'localStorage',{get(){throw new Error('Almacenamiento bloqueado')}})" });
        await nav.enviar('Emulation.setDeviceMetricsOverride', { width: 1366, height: 768, deviceScaleFactor: 1, mobile: false });
        await nav.enviar('Page.reload');
        await nav.esperar('window.mapaPrueba?.graphData?.nodes.length>0');
        assert.equal(await nav.evaluar("document.querySelector('[data-grafo-lado=ficha]').getAttribute('aria-pressed')"), 'true', 'Sin almacenamiento se ven los dos paneles');
        await nav.evaluar("document.querySelector('[data-grafo-lado=ficha]').click()");
        assert.equal(await nav.evaluar("document.querySelector('[data-grafo-lado=ficha]').getAttribute('aria-pressed')"), 'true');
        assert.deepEqual(nav.errores, []);
    } finally {
        await nav.cerrar();
        await servidor.cerrar();
    }
});
