// Capturas del rediseño de Suscripción (2026-10-26): node tests/Frontend/apoyo/suscripcion-navegador.mjs
// Vista real del arnés suscripcion-vista.php con sus hojas y scripts propios; sin red, .env ni base de trabajo.
import { execFileSync } from 'node:child_process';
import { createServer } from 'node:http';
import { createRequire } from 'node:module';
import { existsSync, readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { join, resolve, extname, sep } from 'node:path';
import assert from 'node:assert/strict';

const require = createRequire(import.meta.url);
const { buscarChrome, abrirChrome } = require('./chrome-cdp.cjs');
const raiz = resolve(import.meta.dirname, '../../..');
const carpeta = join(raiz, 'specs/referencias/rediseno-suscripcion');
mkdirSync(carpeta, { recursive: true });
const php = process.env.PHP_BINARY || (process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php');
const vista = (escenario, desde = '') => execFileSync(php, [join(import.meta.dirname, 'suscripcion-vista.php'), escenario, desde], { cwd: raiz, encoding: 'utf8', env: { ...process.env, ZOOKI_TEST_MYSQL_HOST: '' } })
    .replace(/<script[^>]+src="https:[^>]*><\/script>/g, '')
    .replace(/<link[^>]+href="https:[^>]*>/g, '');
const tipos = { '.css': 'text/css', '.js': 'application/javascript', '.png': 'image/png', '.svg': 'image/svg+xml' };
let html = '';
const servidor = createServer((req, res) => {
    const url = new URL(req.url, 'http://localhost');
    if (url.pathname === '/' || url.pathname === '/index.php') {
        res.setHeader('Content-Type', 'text/html; charset=utf-8');
        res.end(html);
        return;
    }
    const ruta = resolve(raiz, 'public', '.' + decodeURIComponent(url.pathname));
    if (!ruta.startsWith(resolve(raiz, 'public') + sep) || !existsSync(ruta)) {
        res.writeHead(404);
        res.end();
        return;
    }
    res.setHeader('Content-Type', tipos[extname(ruta)] || 'application/octet-stream');
    res.end(readFileSync(ruta));
});
await new Promise(r => servidor.listen(0, '127.0.0.1', r));
const base = 'http://127.0.0.1:' + servidor.address().port + '/';
const chrome = buscarChrome();
assert.ok(chrome, 'Chrome o Chromium (CHROME_PATH)');
const nav = await abrirChrome(chrome);
const capturar = async (nombre, completa = false) => {
    await new Promise(r => setTimeout(r, 300));
    const opciones = { format: 'png' };
    if (completa) {
        const m = await nav.enviar('Page.getLayoutMetrics');
        opciones.captureBeyondViewport = true;
        opciones.clip = { x: 0, y: 0, width: m.cssContentSize.width, height: m.cssContentSize.height, scale: 1 };
    }
    const r = await nav.enviar('Page.captureScreenshot', opciones);
    writeFileSync(join(carpeta, nombre + '.png'), Buffer.from(r.data, 'base64'));
};
const cargar = async (escenario, ancho, alto, desde = '') => {
    html = vista(escenario, desde);
    await nav.enviar('Emulation.setDeviceMetricsOverride', { width: ancho, height: alto, deviceScaleFactor: 1, mobile: ancho < 768 });
    await nav.enviar('Page.navigate', { url: base + '?n=' + Date.now() });
    await nav.esperar("document.readyState === 'complete' && document.querySelector('[data-suscripcion-pantalla]')");
    await new Promise(r => setTimeout(r, 400));
};
const medir = () => nav.evaluar(`({ cabe: document.documentElement.scrollWidth <= innerWidth, alto: document.documentElement.scrollHeight, volver: document.querySelector('.encabezado-pagina__volver')?.textContent.trim() })`);
const informe = [];
try {
    for (const [ancho, alto] of [[1440, 900], [1366, 768]]) {
        for (const escenario of ['historial', 'automatico', 'gracia', 'congelada']) {
            await cargar(escenario, ancho, alto);
            const m = await medir();
            assert.ok(m.cabe, 'Sin desborde ' + escenario + ' ' + ancho);
            informe.push({ escenario, ancho, ...m });
            await capturar(ancho + '-' + escenario);
        }
    }
    await cargar('historial', 1440, 900, 'configuracion');
    assert.equal((await medir()).volver, 'Configuración');
    await capturar('1440-desde-configuracion');
    await nav.evaluar("document.querySelector('[data-fuente-preparar]').disabled = false; document.querySelector('[data-fuente-preparar]').click(); document.querySelector('[data-fuente-condiciones]').hidden = false; true");
    await capturar('1440-ventana-medio');
    for (const ancho of [390, 360]) {
        await cargar('historial', ancho, 844);
        const m = await medir();
        assert.ok(m.cabe, 'Sin desborde celular ' + ancho);
        informe.push({ escenario: 'historial', ancho, ...m });
        await capturar(ancho + '-mi-plan');
        await nav.evaluar("document.querySelector('[data-suscripcion-abrir]').click(); true");
        await capturar(ancho + '-hoja-pagar');
        await nav.evaluar("document.querySelector('.suscripcion__cerrar').click(); document.querySelector('[data-suscripcion-pestana=historial]').click(); true");
        await capturar(ancho + '-historial');
    }
    await cargar('automatico', 390, 844);
    await capturar('390-mi-plan-con-medio');
    await nav.evaluar("document.querySelector('[data-fuente-preparar]').click(); document.querySelector('[data-fuente-condiciones]').hidden = false; true");
    await capturar('390-ventana-medio');
    assert.deepEqual(nav.errores, []);
    writeFileSync(join(carpeta, 'medidas.json'), JSON.stringify(informe, null, 2));
    console.log('Capturas en specs/referencias/rediseno-suscripcion/');
} finally {
    await nav.cerrar();
    servidor.closeAllConnections();
    servidor.close();
}
