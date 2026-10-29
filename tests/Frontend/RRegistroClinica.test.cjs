const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { execFileSync } = require('node:child_process');
const { buscarChrome, abrirChrome } = require('./apoyo/chrome-cdp.cjs');
const raiz = path.resolve(__dirname, '../..');

// RNF-16: PHP real y scripts de vista, sin instalación, base de trabajo ni servicios externos.
test('R: registro sin desborde a 360/390/1366 y avance con cédula sin DV', { skip: !buscarChrome() }, async () => {
    const html = execFileSync(process.env.PHP_BINARY || 'php', ['tests/Frontend/apoyo/pulido-p1-vista.php', 'registro'], { cwd: raiz, encoding: 'utf8' })
        .replace(/<script\b[^>]*src="https:\/\/challenges\.cloudflare\.com[^>]*><\/script>/g, '');
    assert.doesNotMatch(html, /Fatal error|Warning:|Notice:/);
    const tokenValidacion = html.match(/data-cuenta-csrf="([^"]+)"/)[1];
    const servidor = http.createServer((peticion, respuesta) => {
        const url = new URL(peticion.url, 'http://localhost');
        if (peticion.method === 'POST' && url.searchParams.get('action') === 'validar_cuenta_ajax') {
            let cuerpo = '';
            peticion.setEncoding('utf8');
            peticion.on('data', datos => { cuerpo += datos; });
            peticion.on('end', async () => {
                try {
                    const datos = await new Response(cuerpo, { headers: { 'Content-Type': peticion.headers['content-type'] } }).formData();
                    const json = execFileSync(process.env.PHP_BINARY || 'php', ['tests/Frontend/apoyo/r-validacion-clinica.php', JSON.stringify(Object.fromEntries(datos)), tokenValidacion], {
                        cwd: raiz,
                        encoding: 'utf8',
                        env: { ...process.env, ZOOKI_TEST_MYSQL_HOST: '', ZOOKI_TEST_MYSQL_DB: 'zooki_test_base_v2' },
                    });
                    JSON.parse(json);
                    respuesta.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
                    respuesta.end(json);
                } catch (error) {
                    respuesta.writeHead(500);
                    respuesta.end(JSON.stringify({ success: false, message: 'Falló la validación de pruebas.' }));
                }
            });
            return;
        }
        if (peticion.method !== 'GET') {
            respuesta.writeHead(405);
            respuesta.end();
            return;
        }
        if (url.pathname === '/index.php') {
            respuesta.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            respuesta.end(html);
            return;
        }
        const archivo = path.resolve(raiz, 'public', '.' + url.pathname);
        if (!archivo.startsWith(path.join(raiz, 'public') + path.sep) || !/^\/(css|js|img)\//.test(url.pathname) || !fs.existsSync(archivo)) {
            respuesta.writeHead(404);
            respuesta.end();
            return;
        }
        const tipos = { '.css': 'text/css', '.js': 'text/javascript', '.png': 'image/png', '.svg': 'image/svg+xml' };
        respuesta.writeHead(200, { 'Content-Type': tipos[path.extname(archivo)] || 'application/octet-stream' });
        respuesta.end(fs.readFileSync(archivo));
    });
    let navegador;
    try {
        await new Promise(resolver => servidor.listen(0, '127.0.0.1', resolver));
        navegador = await abrirChrome(buscarChrome());
        for (const ancho of [360, 390, 1366]) {
            const alto = ancho === 360 ? 800 : (ancho === 1366 ? 768 : 844);
            await navegador.enviar('Emulation.setDeviceMetricsOverride', { width: ancho, height: alto, deviceScaleFactor: 1, mobile: ancho < 768 });
            await navegador.enviar('Page.navigate', { url: `http://127.0.0.1:${servidor.address().port}/index.php?action=registro_clinica` });
            await navegador.esperar('window.ZookiPasos && document.querySelector("#registroClinicaForm.registro-por-pasos")');
            for (const paso of [1, 2, 3]) {
                await navegador.evaluar(`ZookiPasos.ir(document.getElementById('registroClinicaForm'), ${paso}, false)`);
                const medida = await navegador.evaluar(`(() => {
                    const panel = document.querySelector('.login-form-area');
                    return { pagina: document.documentElement.scrollWidth, ventana: document.documentElement.clientWidth, panel: panel.scrollWidth, anchoPanel: panel.clientWidth };
                })()`);
                assert.ok(medida.pagina <= medida.ventana, JSON.stringify({ ancho, paso, ...medida }));
                assert.ok(medida.panel <= medida.anchoPanel, JSON.stringify({ ancho, paso, ...medida }));
                if (ancho < 768) {
                    const controles = await navegador.evaluar(`(() => {
                        const accion = document.querySelector('[data-paso="${paso}"] .btn-primary').getBoundingClientRect();
                        const campos = [...document.querySelectorAll('[data-paso="${paso}"] .input-wrapper input, [data-paso="${paso}"] select')];
                        return { arriba: accion.top, abajo: accion.bottom, alto: accion.height, ventana: innerHeight, fuentes: campos.map(c => parseFloat(getComputedStyle(c).fontSize)) };
                    })()`);
                    assert.ok(controles.arriba >= 0 && controles.abajo <= controles.ventana, JSON.stringify({ ancho, paso, ...controles }));
                    assert.ok(controles.alto >= 40);
                    assert.ok(controles.fuentes.every(fuente => fuente >= 16));
                }
            }
        }
        // Caso que falló al dueño: la cédula sola conserva todas sus cifras y permite avanzar.
        await navegador.evaluar(`(() => {
            window.__rPendientes = 0;
            window.__rCompletadas = 0;
            const original = window.fetch;
            window.fetch = async (...argumentos) => {
                window.__rPendientes++;
                try {
                    return await original(...argumentos);
                } finally {
                    window.__rPendientes--;
                    window.__rCompletadas++;
                }
            };
            const form = document.getElementById('registroClinicaForm');
            ZookiPasos.ir(form, 1, false);
            const datos = { nombre_clinica: 'Clínica Demostración', nit: '1075001234', direccion: 'Carrera 7 # 12-34', telefono_clinica: '3001234567', email_clinica: 'clinica@zooki.test' };
            for (const [nombre, valor] of Object.entries(datos)) {
                const campo = form.elements[nombre];
                campo.focus();
                campo.value = valor;
                campo.dispatchEvent(new Event('input', { bubbles: true }));
                campo.blur();
            }
        })()`);
        await navegador.esperar('window.__rCompletadas >= 4 && window.__rPendientes === 0');
        const campos = await navegador.evaluar(`[...document.querySelectorAll('[data-paso="1"] input')].map(c => ({ nombre: c.name, error: c.validationMessage }))`);
        assert.ok(campos.every(campo => campo.error === ''), JSON.stringify(campos));
        await navegador.evaluar(`document.querySelector('[data-paso="1"] .btn-primary').click()`);
        await navegador.esperar('document.getElementById("registroClinicaForm").dataset.pasoActual === "2"');
        const cedula = await navegador.evaluar('document.getElementById("nit").value');
        assert.equal(cedula, '1075001234');
    } finally {
        if (navegador) await navegador.cerrar();
        await new Promise(resolver => servidor.close(resolver));
    }
});
