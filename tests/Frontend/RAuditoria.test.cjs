const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { execFileSync } = require('node:child_process');
const { buscarChrome, abrirChrome } = require('./apoyo/chrome-cdp.cjs');
const RAIZ = path.resolve(__dirname, '../..');
const php = process.env.PHP_BINARY || (process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php');
function vista(parametros = '') {
    return execFileSync(php, ['tests/Frontend/apoyo/auditoria-vista.php', parametros], { cwd: RAIZ, encoding: 'utf8', env: { ...process.env, ZOOKI_TEST_MYSQL_HOST: '' } });
}

test('Auditoría escapa contenido, protege credenciales y conserva filtros de la clínica', () => {
    const html = vista();
    assert.doesNotMatch(html, /CLÍNICA AJENA|SECRETO-ANTES|SECRETO-NUEVO|CLAVE-PRIVADA|OTRA-CLAVE/);
    assert.doesNotMatch(html, /\s(?:onclick|style)=|<script>\s*\S/);
    assert.match(html, /data-total="53"/);
    assert.equal((html.match(/data-evento=/g) || []).length, 50);
    assert.doesNotMatch(html, /Centro de Seguridad|Monitorización|Propuesta<\/span>/);
    assert.match(html, /Editó|Acceso fallido/);
    const segunda = vista('page=2&accion=UPDATE&tabla=tipos_cita');
    assert.match(segunda, /name="accion"[\s\S]*?value="UPDATE" selected/);
    const css = fs.readFileSync(path.join(RAIZ, 'public/css/auditoria.css'), 'utf8');
    assert.doesNotMatch(css, /#[a-f\d]{3,8}\b|rgba?\(/i);
});

test('R Auditoría: filtros, detalle, exportaciones completas y paginación en 360/390/820/1366', { skip: !buscarChrome() }, async () => {
    const servidor = http.createServer((req, res) => {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname === '/' || url.pathname === '/index.php') {
            if (url.searchParams.get('action')?.endsWith('_ajax')) {
                res.setHeader('Content-Type', 'application/json');
                res.end(JSON.stringify({ success: true, notificaciones: [] }));
                return;
            }
            const html = vista(url.searchParams.toString()).replace(/<script[^>]+src="https:[^>]*><\/script>/g, '').replace(/<link[^>]+href="https:[^>]*>/g, '');
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            res.end(html);
            return;
        }
        const base = path.join(RAIZ, 'public');
        const archivo = path.resolve(base, '.' + url.pathname);
        if (!archivo.startsWith(base + path.sep) || !fs.existsSync(archivo)) {
            res.writeHead(404);
            res.end();
            return;
        }
        res.setHeader('Content-Type', archivo.endsWith('.css') ? 'text/css' : archivo.endsWith('.js') ? 'application/javascript' : 'image/png');
        res.end(fs.readFileSync(archivo));
    });
    await new Promise(r => servidor.listen(0, '127.0.0.1', r));
    const nav = await abrirChrome(buscarChrome());
    const base = 'http://127.0.0.1:' + servidor.address().port;
    const capturar = async nombre => {
        if (process.env.ZOOKI_CAPTURAR_AUDITORIA !== '1') return;
        const carpeta = path.join(RAIZ, 'scratch/r/auditoria/despues');
        fs.mkdirSync(carpeta, { recursive: true });
        const captura = await nav.enviar('Page.captureScreenshot', { format: 'png' });
        fs.writeFileSync(path.join(carpeta, nombre + '.png'), Buffer.from(captura.data, 'base64'));
    };
    try {
        await nav.enviar('Page.navigate', { url: base });
        await nav.esperar("document.readyState === 'complete' && document.querySelectorAll('[data-evento][hidden]').length > 0");
        for (const [width, height] of [[1366, 768], [820, 1180], [360, 800], [390, 844]]) {
            await nav.enviar('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 768 });
            await nav.evaluar("Promise.all(document.getAnimations().filter(a=>a.effect.getTiming().iterations!==Infinity).map(a=>a.finished.catch(()=>{})))");
            const estado = await nav.evaluar(`(() => ({ desborde: document.documentElement.scrollWidth > innerWidth, pagina: document.documentElement.scrollHeight > innerHeight + 1, visibles: document.querySelectorAll('[data-evento]:not([hidden])').length }))()`);
            assert.equal(estado.desborde, false, 'Sin desborde a ' + width);
            if (width === 1366) assert.equal(estado.pagina, false, 'Escritorio sin scroll de página');
            assert.ok(estado.visibles > 0 && estado.visibles <= (width < 768 ? 6 : 10));
            await nav.evaluar("document.querySelector('[data-evento]:not([hidden]) button').click(); true");
            const detalle = await nav.evaluar(`(() => {
                const d = document.getElementById('auditoriaDetalle');
                const rect = d.getBoundingClientRect();
                return { abierto: d.open, dentro: rect.left >= 0 && rect.right <= innerWidth && rect.bottom <= innerHeight + 1, cambios: d.querySelector('[data-detalle-cambios]').textContent, inyectado: !!window.ataque };
            })()`);
            assert.equal(detalle.abierto, true);
            assert.equal(detalle.dentro, true);
            assert.equal(detalle.inyectado, false);
            assert.match(detalle.cambios, /Duración \(min\)3045/);
            if ([1366, 390].includes(width)) await capturar(width + '-detalle');
            await nav.evaluar("document.getElementById('auditoriaDetalle').close(); true");
            await capturar(width + '-auditoria');
        }
        assert.equal(await nav.evaluar('datosExportacionAuditoria().length'), 50, 'Exportar incluye el lote completo, también en móvil');
        const exportados = await nav.evaluar(`(async () => {
            const hoja = { addRow() {}, addRows(filas) { window.filasExcel = filas; }, getRow() { return {}; } };
            window.ExcelJS = { Workbook: class { addWorksheet() { return hoja; } xlsx = { writeBuffer: async () => new ArrayBuffer(0) }; } };
            window.jspdf = { jsPDF: class { setFontSize() {} text() {} autoTable(datos) { window.filasPDF = datos.body; } save(nombre) { window.nombrePDF = nombre; } } };
            const clicOriginal = HTMLAnchorElement.prototype.click;
            HTMLAnchorElement.prototype.click = function () { window.nombreExcel = this.download; };
            try {
                await exportarAuditoriaExcel();
                exportarAuditoriaPDF();
            } finally {
                HTMLAnchorElement.prototype.click = clicOriginal;
            }
            return { excel: filasExcel.length, pdf: filasPDF.length, columnas: filasExcel[0].length, registro: filasPDF[0][4].includes('ID:'), extensionExcel: nombreExcel.endsWith('.xlsx'), extensionPDF: nombrePDF.endsWith('.pdf') };
        })()`);
        assert.deepEqual(exportados, { excel: 50, pdf: 50, columnas: 6, registro: true, extensionExcel: true, extensionPDF: true });
        await nav.evaluar("document.querySelector('[data-auditoria-siguiente]').click(); true");
        assert.match(await nav.evaluar("document.querySelector('[data-auditoria-contador]').textContent"), /^7–12 de 53/);
        for (let paso = 0; paso < 7; paso++) await nav.evaluar("document.querySelector('[data-auditoria-siguiente]').click(); true");
        assert.match(await nav.evaluar("document.querySelector('[data-auditoria-contador]').textContent"), /^49–50 de 53/);
        await nav.evaluar("document.querySelector('[data-auditoria-siguiente]').click(); true");
        await nav.esperar("document.readyState === 'complete' && new URL(location.href).searchParams.get('page') === '2' && document.querySelectorAll('[data-evento]').length === 3");
        assert.match(await nav.evaluar("document.querySelector('[data-auditoria-contador]').textContent"), /^51–53 de 53/);
        await nav.evaluar("document.querySelector('[data-abrir-dialogo=auditoriaFiltrosDialogo]').click(); true");
        assert.equal(await nav.evaluar("document.querySelector('[data-filtros-movil]').contains(document.querySelector('[data-selectores]'))"), true);
        await capturar('390-filtros');
        await nav.evaluar("document.getElementById('auditoriaFiltros').elements.accion.value='UPDATE'; document.querySelector('[form=auditoriaFiltros][type=submit]').click(); true");
        await nav.esperar("document.readyState === 'complete' && new URL(location.href).searchParams.get('accion') === 'UPDATE' && document.querySelectorAll('[data-evento]').length === 40");
        assert.equal(await nav.evaluar("[...document.querySelectorAll('[data-evento]')].every(n=>JSON.parse(n.dataset.evento).accion==='UPDATE')"), true);
        await nav.evaluar("document.querySelector('[data-periodo=hoy]').click(); true");
        await nav.esperar("document.readyState === 'complete' && !!new URL(location.href).searchParams.get('fecha_desde')");
        assert.equal(await nav.evaluar("document.querySelector('[data-periodo=hoy]').getAttribute('aria-pressed')"), 'true');
        await nav.enviar('Page.navigate', { url: base + '/index.php?action=admin_auditoria&accion=DELETE&page=2' });
        await nav.esperar("document.readyState === 'complete' && document.querySelector('.auditoria-vacia') && document.querySelector('[data-auditoria-contador]').textContent === '0 de 0 eventos'");
        assert.deepEqual(nav.errores, []);
    } finally {
        await nav.cerrar();
        servidor.closeAllConnections();
        await new Promise(r => servidor.close(r));
    }
});
