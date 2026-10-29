const test = require('node:test');
const assert = require('node:assert/strict');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');
const { servidorPlataforma } = require('./apoyo/plataforma-r-servidor.cjs');
const { instalarMapaFalso } = require('./apoyo/grafo-falso.cjs');

test('Cabecera de plataforma: clic fuera, alternancia, enlaces y Escape en Clínicas/Conocimiento a 1366/390', { skip: !buscarChrome() }, async () => {
    const nav = await abrirChrome(buscarChrome());
    try {
        await nav.enviar('Network.enable');
        await nav.enviar('Network.setBlockedURLs', { urls: ['*3d-force-graph-1.80.0.min.js*'] });
        await nav.enviar('Page.addScriptToEvaluateOnNewDocument', { source: '(' + instalarMapaFalso.toString() + ')()' });
        for (const grafo of [false, true]) {
            const servidor = await servidorPlataforma(grafo);
            try {
                for (const width of [1366, 390]) {
                    await nav.enviar('Emulation.setDeviceMetricsOverride', { width, height: width < 768 ? 844 : 768, deviceScaleFactor: 1, mobile: width < 768 });
                    await nav.enviar('Page.navigate', { url: servidor.url });
                    await nav.esperar("document.readyState==='complete'");
                    if (grafo) {
                        await nav.esperar('window.mapaPrueba?.graphData?.nodes.length>0');
                        await nav.evaluar("document.querySelector('[data-nodo]').click()");
                    }
                    const abiertos = () => nav.evaluar("Array.from(document.querySelectorAll('.plataforma-menu-cuenta')).map(e=>e.open)");
                    const click = selector => nav.evaluar(`document.querySelector(${JSON.stringify(selector)}).click()`);
                    const campana = '.plataforma-menu-cuenta:first-of-type summary';
                    const cuenta = '.plataforma-menu-cuenta:last-of-type summary';
                    await click(campana);
                    assert.deepEqual(await abiertos(), [true, false]);
                    await click('.plataforma-cabecera__titulo');
                    assert.deepEqual(await abiertos(), [false, false], 'El clic fuera cierra');
                    await click(campana);
                    await click(cuenta);
                    assert.deepEqual(await abiertos(), [false, true], 'Abrir Cuenta cierra la campana');
                    await click(cuenta);
                    assert.deepEqual(await abiertos(), [false, false], 'El mismo botón sigue cerrando');
                    await click(campana);
                    await nav.enviar('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
                    await nav.enviar('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
                    assert.deepEqual(await abiertos(), [false, false]);
                    assert.equal(await nav.evaluar(`document.activeElement===document.querySelector(${JSON.stringify(campana)})`), true, 'Escape devuelve el foco a la campana');
                    if (grafo) {
                        assert.equal(await nav.evaluar("document.querySelector('[data-grafo-detalle]').hidden"), false, 'Escape no cierra también la ficha del fondo');
                    }
                    await nav.evaluar("document.addEventListener('click',e=>{if(e.target.closest('.plataforma-menu-cuenta a'))e.preventDefault()})");
                    await click(campana);
                    await click('.plataforma-menu-cuenta a');
                    assert.deepEqual(await abiertos(), [false, false], 'Elegir un enlace cierra el desplegable');
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
