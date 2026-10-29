const test = require('node:test');
const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');
const { mkdtempSync, existsSync, readFileSync, unlinkSync, rmSync } = require('node:fs');
const { tmpdir } = require('node:os');
const { join, resolve } = require('node:path');
const { createServer } = require('node:net');

test('P1 HTTP: el JSON termina antes del correo lento y es idéntico sin correo o con fallo', async () => {
    const temporal = mkdtempSync(join(tmpdir(), 'zooki-soporte-'));
    const evidencia = join(temporal, 'correo.json');
    const reserva = createServer();
    await new Promise(r => reserva.listen(0, '127.0.0.1', r));
    const puerto = reserva.address().port;
    await new Promise(r => reserva.close(r));
    const proceso = spawn(process.env.PHP_BINARY || 'php', ['-S', `127.0.0.1:${puerto}`, 'tests/Frontend/apoyo/soporte-respuesta.php'], {
        cwd: resolve(__dirname, '../..'), env: { ...process.env, ZOOKI_SOPORTE_EVIDENCIA: evidencia }, stdio: ['ignore', 'ignore', 'pipe'],
    });
    try {
        await new Promise((resolver, rechazar) => {
            const limite = setTimeout(() => rechazar(new Error('El servidor de pruebas no arrancó')), 10000);
            proceso.stderr.on('data', datos => { if (String(datos).includes('started')) { clearTimeout(limite); resolver(); } });
            proceso.on('error', rechazar);
        });
        const respuestas = [];
        for (const modo of ['sin', 'correcto', 'fallo']) {
            if (existsSync(evidencia)) unlinkSync(evidencia);
            const inicio = performance.now();
            const respuesta = await fetch(`http://127.0.0.1:${puerto}/?modo=${modo}`);
            const cuerpo = await respuesta.text();
            const demora = performance.now() - inicio;
            assert.equal(respuesta.status, 422);
            respuestas.push(cuerpo);
            if (modo !== 'sin') {
                assert.ok(demora < 1400, `La respuesta esperó al SMTP: ${demora} ms`);
                assert.equal(existsSync(evidencia), false, 'El navegador ya recibió todo antes del mailer.');
                for (let n = 0; n < 60 && !existsSync(evidencia); n++) await new Promise(r => setTimeout(r, 50));
                const correo = JSON.parse(readFileSync(evidencia, 'utf8'));
                assert.equal(correo.estado, 'abierto');
                assert.equal(correo.asunto, 'Zooki: nuevo caso de soporte #1');
                assert.doesNotMatch(correo.cuerpo, /1000000001|Fabio|fabio@/);
            }
        }
        assert.equal(new Set(respuestas).size, 1);
    } finally {
        const salida = new Promise(r => proceso.once('exit', r));
        proceso.kill();
        await salida;
        rmSync(temporal, { recursive: true, force: true });
    }
});
