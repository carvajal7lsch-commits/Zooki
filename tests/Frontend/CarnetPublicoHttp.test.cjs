const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const net = require('node:net');
const { spawn } = require('node:child_process');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');

test('HTTP real: cabeceras iguales en 404, noindex válido/429 y Chrome público a 1366/390', { skip: !buscarChrome() }, async () => {
    const carpeta = fs.mkdtempSync(path.join(os.tmpdir(), 'zooki-carnet-http-'));
    const servidorPuerto = net.createServer();
    await new Promise(r => servidorPuerto.listen(0, '127.0.0.1', r));
    const puerto = servidorPuerto.address().port;
    await new Promise(r => servidorPuerto.close(r));
    const php = process.env.PHP_BINARY || (process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php');
    const servidor = spawn(php, ['-S', `127.0.0.1:${puerto}`, '-t', path.resolve('public'), path.resolve('tests/Frontend/apoyo/carnet-publico-router.php')], { env: { ...process.env, CARNET_IP_SECRETO: 's'.repeat(64), ZOOKI_CARNET_B_SQLITE: path.join(carpeta, 'prueba.sqlite') }, stdio: ['ignore', 'ignore', 'pipe'] });
    let log = '';
    servidor.stderr.on('data', dato => { log += dato.toString(); });
    const base = `http://127.0.0.1:${puerto}/index.php?action=carnet&t=`;
    let navegador;
    try {
        let listo = false;
        for (let i = 0; i < 100; i++) {
            try {
                await fetch(base + 'invalido');
                listo = true;
                break;
            } catch {
                await new Promise(r => setTimeout(r, 50));
            }
        }
        assert.ok(listo, log);
        const cabeceras = respuesta => Object.fromEntries(['x-robots-tag', 'cache-control', 'referrer-policy', 'x-content-type-options', 'content-type', 'set-cookie'].map(clave => [clave, respuesta.headers.get(clave)]));
        const respuestas = [];
        for (const token of ['invalido', 'z'.repeat(43), 'c'.repeat(43), 'p'.repeat(43)]) {
            const respuesta = await fetch(base + token);
            assert.equal(respuesta.status, 404);
            respuestas.push({ cuerpo: await respuesta.text(), cabeceras: cabeceras(respuesta) });
        }
        for (const respuesta of respuestas) assert.deepEqual(respuesta, respuestas[0]);
        const valida = await fetch(base + 'a'.repeat(43));
        assert.equal(valida.status, 200);
        assert.equal(valida.headers.get('x-robots-tag'), 'noindex, nofollow');
        assert.equal(valida.headers.get('cache-control'), 'no-store');
        assert.equal(valida.headers.get('referrer-policy'), 'no-referrer');
        assert.equal(valida.headers.get('set-cookie'), null);
        const html = await valida.text();
        assert.doesNotMatch(html, /id_mascota|data-id-|data-carnet-regenerar|HISTORIA-PRIVADA|privado@example/);
        navegador = await abrirChrome(buscarChrome());
        for (const ancho of [1366, 390]) {
            await navegador.enviar('Emulation.setDeviceMetricsOverride', { width: ancho, height: 900, deviceScaleFactor: 1, mobile: ancho < 768 });
            await navegador.enviar('Page.navigate', { url: base + 'a'.repeat(43) });
            await navegador.esperar('document.readyState === "complete" && !!document.querySelector(".carnet-tarjeta")');
            assert.equal(await navegador.evaluar('document.documentElement.scrollWidth <= innerWidth'), true);
            assert.equal(await navegador.evaluar('document.querySelector(".carnet-qr").naturalWidth > 0'), true);
            if (process.env.ZOOKI_CARNET_EVIDENCIA) {
                fs.mkdirSync(process.env.ZOOKI_CARNET_EVIDENCIA, { recursive: true });
                fs.writeFileSync(`${process.env.ZOOKI_CARNET_EVIDENCIA}/publico-${ancho}.png`, Buffer.from((await navegador.enviar('Page.captureScreenshot', { captureBeyondViewport: true })).data, 'base64'));
            }
            await navegador.enviar('Page.navigate', { url: base + 'invalido' });
            await navegador.esperar('document.readyState === "complete" && !!document.querySelector(".carnet-publico__aviso")');
            assert.equal(await navegador.evaluar('document.documentElement.scrollWidth <= innerWidth'), true);
            if (process.env.ZOOKI_CARNET_EVIDENCIA) fs.writeFileSync(`${process.env.ZOOKI_CARNET_EVIDENCIA}/generico-${ancho}.png`, Buffer.from((await navegador.enviar('Page.captureScreenshot', { captureBeyondViewport: true })).data, 'base64'));
        }
        await fetch(`http://127.0.0.1:${puerto}/index.php?action=__reiniciar_accesos`);
        for (let i = 0; i < 30; i++) {
            const respuesta = await fetch(base + (i % 2 ? 'a'.repeat(43) : 'invalido'));
            assert.equal(respuesta.status, i % 2 ? 200 : 404);
        }
        const limitada = await fetch(base + 'a'.repeat(43));
        assert.equal(limitada.status, 429);
        assert.equal(limitada.headers.get('x-robots-tag'), 'noindex, nofollow');
        assert.equal(limitada.headers.get('cache-control'), 'no-store');
        assert.match(await limitada.text(), /noindex, nofollow/);
        assert.deepEqual(navegador.errores, []);
        assert.doesNotMatch(log, /Fatal error|Warning:/);
    } finally {
        if (navegador) await navegador.cerrar();
        servidor.kill();
        await new Promise(r => servidor.once('exit', r));
        fs.rmSync(carpeta, { recursive: true, force: true, maxRetries: 10, retryDelay: 200 });
    }
});
