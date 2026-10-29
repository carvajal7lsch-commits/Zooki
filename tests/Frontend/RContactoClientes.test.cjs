const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');

test('Clientes: correo largo completo dentro de la tarjeta compartida, sin desborde', { skip: !buscarChrome(), timeout: 20000 }, async () => {
    const nav = await abrirChrome(buscarChrome());
    try {
        const css = ['identidad.css', 'usuarios.css', 'pacientes.css'].map(archivo => fs.readFileSync('public/css/' + archivo, 'utf8').replace(/@import[^;]+;/g, '')).join('\n');
        const arbol = await nav.enviar('Page.getFrameTree');
        await nav.enviar('Page.setDocumentContent', { frameId: arbol.frameTree.frame.id, html: `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>*{box-sizing:border-box}body{margin:0;padding:16px}#ownersGrid{width:220px}${css}</style></head><body><div id="ownersGrid"></div></body></html>` });
        await nav.evaluar(fs.readFileSync('public/js/medical-module.js', 'utf8'));
        await nav.evaluar(`document.getElementById('ownersGrid').append(tarjetaDueno({id_usuario:9,nombre_completo:'Cliente de prueba',documento:'1000000000',telefono:'+573001234567',email:'nombre.apellido.muy.largo@dominio-de-prueba.example',total_mascotas:1,estado:1,pendiente_activacion:1})); true`);
        for (const [pantalla, ancho] of [[1366, 220], [1366, 266], [390, 358], [360, 328]]) {
            await nav.enviar('Emulation.setDeviceMetricsOverride', { width: pantalla, height: 844, deviceScaleFactor: 1, mobile: pantalla < 768 });
            const resultado = await nav.evaluar(`(() => {
                document.getElementById('ownersGrid').style.width = '${ancho}px';
                const tarjeta = document.querySelector('.person-card');
                const contacto = tarjeta.querySelector('.bi-envelope').parentElement;
                const rect = tarjeta.getBoundingClientRect();
                const rango = document.createRange();
                rango.selectNodeContents(contacto);
                return { dentro: [...rango.getClientRects()].every(r=>r.left>=rect.left&&r.right<=rect.right+1), sinDesborde: contacto.scrollWidth<=contacto.clientWidth+1, completo: contacto.textContent.trim(), pagina: document.documentElement.scrollWidth<=innerWidth };
            })()`);
            assert.deepEqual(resultado, { dentro: true, sinDesborde: true, completo: 'nombre.apellido.muy.largo@dominio-de-prueba.example', pagina: true }, pantalla + '/' + ancho);
        }
        assert.deepEqual(nav.errores, []);
    } finally {
        await nav.cerrar();
    }
});
