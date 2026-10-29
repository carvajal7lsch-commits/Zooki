const test = require('node:test');
const assert = require('node:assert/strict');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');
const { pantallasCabecera, servidorCabecera, medirCabecera } = require('./apoyo/cabeceras-servidor.cjs');

test('RNF-16: las 13 cabeceras comparten posición y contenido a 1366; sin desborde a 820/390', { skip: !buscarChrome() }, async () => {
    const nav = await abrirChrome(buscarChrome());
    try {
        for (const pantalla of pantallasCabecera) {
            const servidor = await servidorCabecera(pantalla);
            try {
                for (const width of [1366, 820, 390]) {
                    await nav.enviar('Emulation.setDeviceMetricsOverride', { width, height: width < 768 ? 844 : 768, deviceScaleFactor: 1, mobile: width < 768 });
                    await nav.enviar('Page.navigate', { url: servidor.url });
                    const medida = await medirCabecera(nav);
                    assert.ok(medida.ancho <= medida.ventana + 1, pantalla[0] + ' sin desborde a ' + width);
                    if (width !== 1366) continue;
                    const cerca = (actual, esperado, nombre) => assert.ok(Math.abs(actual - esperado) <= 2, pantalla[0] + ' ' + nombre + ': ' + actual);
                    cerca(medida.cabecera.y, 22, 'cabecera arriba');
                    cerca(medida.cabecera.alto, 52, 'alto de cabecera');
                    assert.ok(medida.titulo, pantalla[0] + ' tiene título');
                    cerca(medida.titulo.y, 26, 'posición del título');
                    cerca(medida.titulo.alto, 24, 'alto de línea del título');
                    cerca(medida.cuerpo.y, 92, 'comienzo del contenido');
                    cerca(medida.hoja.y, 92, 'comienzo de la hoja');
                    assert.equal(await nav.evaluar(`Array.from(document.querySelectorAll('.top-header .notif-btn, .top-header .cabecera-cuenta__boton, .top-header .plataforma-circulo')).every(e=>Math.abs(e.getBoundingClientRect().height-40)<=1)`), true, pantalla[0] + ' controles40');
                }
            } finally {
                await servidor.cerrar();
            }
        }
    } finally {
        await nav.cerrar();
    }
});
