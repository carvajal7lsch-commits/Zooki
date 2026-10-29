const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { buscarChrome, abrirChrome } = require('./apoyo/chrome-cdp.cjs');
const raiz = path.resolve(__dirname, '../..');

function cssLocal(archivo) {
    return fs.readFileSync(archivo, 'utf8').replace(/@import\s+url\(['"]?([^'"\)]+)['"]?\);/g, (_, ruta) => /^https?:/.test(ruta) ? '' : cssLocal(path.join(path.dirname(archivo), ruta.split('?')[0])));
}
function vista(fixture, argumento) {
    const html = execFileSync(process.env.PHP_BINARY || 'php', [fixture, argumento], { cwd: raiz, encoding: 'utf8' });
    assert.doesNotMatch(html, /Fatal error|Warning:|Notice:/);
    const estilos = [...html.matchAll(/<link[^>]+href="(css\/[^"?]+)[^"]*"[^>]*>/g)].map(m => '<style>' + cssLocal(path.join(raiz, 'public', m[1])) + '</style>').join('\n');
    return html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '').replace(/<link\b[^>]*>/gi, '').replace(/<img\b[^>]*>/gi, '')
        .replace('</head>', estilos + '<style>#global-loader{display:none}*{animation:none!important;transition:none!important}</style></head>');
}

test('P1 RNF-16: solo A/B, sin desborde a 1366/390; minigráfica vacía visible', { skip: !buscarChrome() }, async () => {
    const paginas = Object.fromEntries(['registro', 'atencion', 'pacientes', 'usuarios', 'horarios', 'agenda', 'portal', 'plataforma'].map(nombre => [nombre, vista('tests/Frontend/apoyo/pulido-p1-vista.php', nombre)]));
    paginas.inicio = vista('tests/Frontend/apoyo/inicio-admin-vista.php', 'vacio');
    const nav = await abrirChrome(buscarChrome());
    fs.mkdirSync(path.join(raiz, 'scratch/p1'), { recursive: true });
    const medidas = [];
    try {
        for (const [pantalla, html] of Object.entries(paginas)) {
            for (const ancho of [1366, 390]) {
                await nav.enviar('Emulation.setDeviceMetricsOverride', { width: ancho, height: 844, deviceScaleFactor: 1, mobile: ancho < 768 });
                const arbol = await nav.enviar('Page.getFrameTree');
                await nav.enviar('Page.setDocumentContent', { frameId: arbol.frameTree.frame.id, html });
                if (pantalla === 'plataforma') await nav.evaluar(`document.querySelectorAll('[data-panel]').forEach(p => p.hidden = p.dataset.panel !== 'soporte'); document.querySelector('[id="casoModal3"]').classList.add('is-open');`);
                const medida = await nav.evaluar(`({ ancho: innerWidth, contenido: document.documentElement.scrollWidth })`);
                medidas.push({ pantalla, ancho, ...medida });
                assert.ok(medida.contenido <= medida.ancho, JSON.stringify(medidas.at(-1)));
                if (pantalla === 'inicio') {
                    const grafica = await nav.evaluar(`(() => { const g = document.querySelector('.inicio-grafica--vacia'); return { base: getComputedStyle(g).borderBottomWidth, meses: [...g.children].map(m => getComputedStyle(m, '::after').content) }; })()`);
                    assert.equal(grafica.base, '1px');
                    assert.equal(grafica.meses.length, 6);
                    assert.ok(grafica.meses.every(m => m.length === 3), JSON.stringify(grafica));
                }
                const captura = await nav.enviar('Page.captureScreenshot', { format: 'png' });
                fs.writeFileSync(path.join(raiz, `scratch/p1/${pantalla}-${ancho}.png`), Buffer.from(captura.data, 'base64'));
            }
        }
    } finally {
        fs.writeFileSync(path.join(raiz, 'scratch/p1/medidas.json'), JSON.stringify(medidas, null, 2));
        await nav.cerrar();
    }
});
