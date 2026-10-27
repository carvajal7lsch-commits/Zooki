const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { execFileSync } = require('node:child_process');
const { buscarChrome, abrirChrome } = require('./apoyo/chrome-cdp.cjs');
const RAIZ = path.resolve(__dirname, '../..');

test('R Ausencias: hoja compacta, motivos y calendario flotante sin agrandar el modal', { skip: !buscarChrome() }, async t => {
    const php = process.env.PHP_BINARY || (process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php');
    let html = execFileSync(php, ['tests/Frontend/apoyo/ajustes-vista.php'], { cwd: RAIZ, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], env: { ...process.env, ZOOKI_TEST_MYSQL_HOST: '' } });
    const reales = ['flatpickr.min.js', 'flatpickr.min.css', 'flatpickr-es.js'].every(f => fs.existsSync(path.join(RAIZ, 'scratch', f)));
    if (reales) html = html.replace('https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', '/flatpickr.min.css').replace('https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js', '/flatpickr-es.js').replace('https://cdn.jsdelivr.net/npm/flatpickr"', '/flatpickr.min.js"');
    else t.diagnostic('Sin los activos locales opcionales del calendario: se comprueban formulario y motivos.');
    html = html.replace(/<script[^>]+src="https:[^>]*><\/script>/g, '').replace(/<link[^>]+href="https:[^>]*>/g, '');
    const vista = { success: true, id_veterinario: 2, veterinarios: [{ id_usuario: 2, nombre_completo: 'Beto Norte' }], franjas: [], propuestas: [], cierres: [], afectadas: [] };
    const escrituras = [];
    const srv = http.createServer((req, res) => {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname === '/index.php') {
            const accion = url.searchParams.get('action');
            res.setHeader('Content-Type', 'application/json');
            if (req.method === 'POST') {
                let cuerpo = '';
                req.on('data', parte => { cuerpo += parte; });
                req.on('end', () => {
                    const campos = {};
                    const lineas = cuerpo.split('\r\n');
                    lineas.forEach((linea, i) => {
                        const m = linea.match(/name="([^"]+)"/);
                        if (m) campos[m[1]] = lineas[i + 2];
                    });
                    escrituras.push({ accion, campos });
                    res.end(JSON.stringify({ success: true, resultado: { vista }, message: 'Registrado.' }));
                });
                return;
            }
            res.end(JSON.stringify(accion === 'get_coordinacion_horarios_ajax' ? vista : accion === 'listar_ausencias_ajax' ? { success: true, ausencias: [] } : { success: true, horarios: [] }));
            return;
        }
        if (url.pathname === '/') {
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            res.end(html);
            return;
        }
        const local = ['/flatpickr.min.js', '/flatpickr.min.css', '/flatpickr-es.js'].includes(url.pathname);
        const base = path.join(RAIZ, local ? 'scratch' : 'public');
        const archivo = path.resolve(base, '.' + url.pathname);
        if (!archivo.startsWith(base + path.sep) || !fs.existsSync(archivo)) {
            res.writeHead(404);
            res.end();
            return;
        }
        res.setHeader('Content-Type', archivo.endsWith('.css') ? 'text/css' : archivo.endsWith('.js') ? 'application/javascript' : 'image/png');
        res.end(fs.readFileSync(archivo));
    });
    await new Promise(r => srv.listen(0, '127.0.0.1', r));
    const nav = await abrirChrome(buscarChrome());
    const capturar = async nombre => {
        if (process.env.ZOOKI_CAPTURAR_AUSENCIAS !== '1') return;
        const carpeta = path.join(RAIZ, 'scratch/r/ajustes-ausencias/despues');
        fs.mkdirSync(carpeta, { recursive: true });
        const captura = await nav.enviar('Page.captureScreenshot', { format: 'png' });
        fs.writeFileSync(path.join(carpeta, nombre + '.png'), Buffer.from(captura.data, 'base64'));
    };
    try {
        await nav.enviar('Page.navigate', { url: 'http://127.0.0.1:' + srv.address().port + '/#ausencias' });
        await nav.esperar("document.readyState === 'complete' && document.querySelector('[data-form-ausencia] [name=id_veterinario]').options.length > 0");
        for (const [width, height] of [[360, 800], [390, 844], [820, 1180], [1366, 768]]) {
            await nav.enviar('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 768 });
            assert.equal(await nav.evaluar('document.documentElement.scrollWidth <= innerWidth'), true, 'Panel a ' + width);
            await capturar(width + '-panel');
            for (const id of ['dialogoCierre', 'dialogoAusencia']) {
                await nav.evaluar(`document.querySelector('[data-abrir-dialogo=${id}]').click(); true`);
                if (reales) {
                    assert.equal(await nav.evaluar(`document.querySelector('#${id} [name=inicio]')._flatpickr.isOpen`), false, 'No abre el calendario al entrar en ' + id);
                    const puntoFecha = await nav.evaluar(`(() => {
                        const campo = document.querySelector('#${id} [name=inicio]')._flatpickr.altInput;
                        const rect = campo.getBoundingClientRect();
                        return { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 };
                    })()`);
                    await nav.enviar('Input.dispatchMouseEvent', { type: 'mousePressed', button: 'left', clickCount: 1, ...puntoFecha });
                    await nav.enviar('Input.dispatchMouseEvent', { type: 'mouseReleased', button: 'left', clickCount: 1, ...puntoFecha });
                    assert.equal(await nav.evaluar(`document.getElementById('${id}').open`), true, 'Tocar la fecha conserva ' + id);
                    const medida = await nav.evaluar(`(() => {
                        const dialogo = document.getElementById('${id}');
                        const campo = dialogo.querySelector('[name=inicio]');
                        const fp = campo._flatpickr;
                        const cuerpo = dialogo.querySelector('.zk-dialogo__cuerpo');
                        const antes = [dialogo.getBoundingClientRect().height, cuerpo.scrollHeight];
                        fp.open();
                        const rect = fp.calendarContainer.getBoundingClientRect();
                        const despues = [dialogo.getBoundingClientRect().height, cuerpo.scrollHeight];
                        const punto = document.elementFromPoint(rect.left + rect.width / 2, rect.top + rect.height / 2);
                        return { estable: JSON.stringify(antes) === JSON.stringify(despues), flotante: getComputedStyle(fp.calendarContainer).position, dentro: rect.left >= 0 && rect.right <= innerWidth + 1 && rect.top >= 0 && rect.bottom <= innerHeight + 1, interactivo: fp.calendarContainer.contains(punto), navegacion: !!fp.calendarContainer.querySelector('.fp-win11-nav'), nativo: campo.type === 'datetime-local' };
                    })()`);
                    assert.deepEqual(medida, { estable: true, flotante: 'fixed', dentro: true, interactivo: true, navegacion: true, nativo: false }, id + ' a ' + width);
                    const reloj = await nav.evaluar(`(() => {
                        const fp = document.querySelector('#${id} [name=inicio]')._flatpickr;
                        const altoFecha = fp.calendarContainer.getBoundingClientRect().height;
                        fp.setDate('2030-01-07T08:15:30', true);
                        const pasoHora = fp.calendarContainer.dataset.paso;
                        const compacto = fp.calendarContainer.getBoundingClientRect().height < altoFecha;
                        const horas = fp.calendarContainer.querySelector('.flatpickr-hour');
                        const minutos = fp.calendarContainer.querySelector('.flatpickr-minute');
                        horas.value = '6000';
                        horas.dispatchEvent(new Event('input', { bubbles: true }));
                        minutos.value = '9999';
                        minutos.dispatchEvent(new Event('input', { bubbles: true }));
                        const limitados = horas.value === '23' && minutos.value === '59' && horas.maxLength === 2 && minutos.maxLength === 2;
                        horas.select();
                        const rechaza = !horas.dispatchEvent(new InputEvent('beforeinput', { data: '3000', inputType: 'insertText', bubbles: true, cancelable: true }));
                        const portapapeles = new DataTransfer();
                        portapapeles.setData('text', '3000');
                        horas.dispatchEvent(new ClipboardEvent('paste', { clipboardData: portapapeles, bubbles: true, cancelable: true }));
                        const pegadoLimitado = horas.value === '23';
                        fp.calendarContainer.querySelector('.selector-fechas-cambiar').click();
                        const vuelveAFecha = fp.calendarContainer.dataset.paso === 'fecha';
                        fp.setDate('2030-01-07T08:15:30', true);
                        const segundos = fp.calendarContainer.querySelector('.flatpickr-second').parentElement;
                        fp.calendarContainer.querySelector('.selector-fechas-listo').click();
                        return { abierto: fp.isOpen, valor: fp.input.value, visible: fp.altInput.value, segundosOcultos: getComputedStyle(segundos).display === 'none', pasoHora, compacto, limitados, rechaza, pegadoLimitado, vuelveAFecha };
                    })()`);
                    assert.deepEqual(reloj, { abierto: false, valor: '2030-01-07T08:15:30', visible: '07/01/2030 08:15', segundosOcultos: true, pasoHora: 'hora', compacto: true, limitados: true, rechaza: true, pegadoLimitado: true, vuelveAFecha: true });
                    await nav.evaluar(`document.querySelector('#${id} [name=inicio]')._flatpickr.open(); true`);
                    await capturar(width + '-' + id + '-calendario');
                    await nav.evaluar(`document.querySelector('#${id} [name=inicio]')._flatpickr.close(); true`);
                }
                assert.equal(await nav.evaluar('document.documentElement.scrollWidth <= innerWidth'), true, id + ' sin desborde');
                await nav.enviar('Input.dispatchMouseEvent', { type: 'mousePressed', button: 'left', clickCount: 1, x: 4, y: 4 });
                await nav.enviar('Input.dispatchMouseEvent', { type: 'mouseReleased', button: 'left', clickCount: 1, x: 4, y: 4 });
                assert.equal(await nav.evaluar(`document.getElementById('${id}').open`), false, 'El fondo exterior sigue cerrando ' + id);
            }
        }
        for (const [id, motivo] of [['dialogoCierre', 'Festivo'], ['dialogoAusencia', 'Vacaciones']]) {
            const resultado = await nav.evaluar(`(() => {
                const dialogo = document.getElementById('${id}');
                dialogo.showModal();
                const opcion = dialogo.querySelector('[data-motivo-opcion]');
                opcion.value = '${motivo}';
                opcion.dispatchEvent(new Event('change', { bubbles: true }));
                const campo = dialogo.querySelector('[name=motivo]');
                for (const [nombre, valor] of [['inicio','2030-01-07T08:00:30'],['fin','2030-01-07T12:00:30']]) {
                    const fecha = dialogo.querySelector('[name='+nombre+']');
                    if (fecha._flatpickr) fecha._flatpickr.setDate(valor, true);
                    else { fecha.value=valor; fecha.dispatchEvent(new Event('input',{bubbles:true})); }
                }
                const resultado = { valor: campo.value, oculto: campo.hidden };
                dialogo.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
                return resultado;
            })()`);
            assert.deepEqual(resultado, { valor: motivo, oculto: true });
            await nav.esperar(`!document.getElementById('${id}').open`);
            assert.equal(escrituras.at(-1).campos.motivo, motivo);
            assert.equal(escrituras.at(-1).campos.inicio, '2030-01-07T08:00:30');
        }
        assert.deepEqual(escrituras.map(e => e.accion), ['crear_cierre_clinica_ajax', 'registrar_ausencia_ajax']);
        const otro = await nav.evaluar(`(() => {
            const grupo = document.querySelector('[data-form-ausencia] [data-motivo-agenda]');
            const selector = grupo.querySelector('select');
            selector.value='otro';
            selector.dispatchEvent(new Event('change'));
            const campo=grupo.querySelector('[name=motivo]');
            campo.value='Consulta personal';
            campo.dispatchEvent(new Event('input',{bubbles:true}));
            return { visible: !campo.hidden, motivo: new FormData(campo.form).get('motivo'), opcionalEnLinea: grupo.querySelector('span:first-child').contains(grupo.querySelector('.coord-opcional')) };
        })()`);
        assert.deepEqual(otro, { visible: true, motivo: 'Consulta personal', opcionalEnLinea: true });
        assert.deepEqual(nav.errores, []);
    } finally {
        await nav.cerrar();
        srv.closeAllConnections();
        await new Promise(r => srv.close(r));
    }
});
