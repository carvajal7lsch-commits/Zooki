// Chrome sin interfaz por el protocolo de DevTools, sin dependencias de npm (C8.2).
// Lo usan las pruebas que necesitan medir el diseño real; si no hay navegador, la prueba se omite.
const { spawn } = require('node:child_process');
const { existsSync, mkdtempSync, rmSync } = require('node:fs');
const { tmpdir } = require('node:os');
const { join } = require('node:path');

const RUTAS_CHROME = [
    process.env.CHROME_PATH,
    '/opt/pw-browsers/chromium',
    '/usr/bin/google-chrome',
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
];

function buscarChrome() {
    return RUTAS_CHROME.find(ruta => ruta && existsSync(ruta)) || null;
}

async function abrirChrome(ruta) {
    const perfil = mkdtempSync(join(tmpdir(), 'zooki-cdp-'));
    const argumentos = ['--headless=new', '--remote-debugging-port=0', '--user-data-dir=' + perfil, '--no-first-run', '--no-default-browser-check'];
    // En contenedores Linux corriendo como root Chrome exige desactivar su sandbox.
    if (process.platform === 'linux' && process.getuid && process.getuid() === 0) argumentos.push('--no-sandbox');
    const proceso = spawn(ruta, [...argumentos, 'about:blank']);
    const direccion = await new Promise((resolver, rechazar) => {
        let salida = '';
        const limite = setTimeout(() => rechazar(new Error('Chrome no arrancó')), 20000);
        proceso.stderr.on('data', trozo => {
            salida += trozo;
            const encontrado = salida.match(/DevTools listening on (ws:\/\/\S+)/);
            if (encontrado) {
                clearTimeout(limite);
                resolver(encontrado[1]);
            }
        });
    });
    const remoto = direccion.replace(/^ws:\/\/([^/]+).*/, 'http://$1');
    const pestana = await (await fetch(remoto + '/json/new?about:blank', { method: 'PUT' })).json();
    const ws = new WebSocket(pestana.webSocketDebuggerUrl);
    await new Promise(resolver => { ws.onopen = resolver; });
    let secuencia = 0;
    const pendientes = new Map();
    const errores = [];
    ws.onmessage = mensaje => {
        const dato = JSON.parse(mensaje.data);
        if (pendientes.has(dato.id)) {
            const { resolver, rechazar } = pendientes.get(dato.id);
            pendientes.delete(dato.id);
            if (dato.error) rechazar(new Error(dato.error.message));
            else resolver(dato.result);
        } else if (dato.method === 'Runtime.exceptionThrown') {
            errores.push(dato.params.exceptionDetails.exception?.description || dato.params.exceptionDetails.text);
        }
    };
    const enviar = (method, params = {}) => new Promise((resolver, rechazar) => {
        secuencia += 1;
        pendientes.set(secuencia, { resolver, rechazar });
        ws.send(JSON.stringify({ id: secuencia, method, params }));
    });
    const evaluar = async expression => {
        const r = await enviar('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
        if (r.exceptionDetails) throw new Error(r.exceptionDetails.exception?.description || r.exceptionDetails.text);
        return r.result.value;
    };
    const esperar = async expression => {
        for (let intento = 0; intento < 300; intento++) {
            if (await evaluar(expression).catch(() => false)) return;
            await new Promise(resolver => setTimeout(resolver, 100));
        }
        throw new Error('No se cumplió: ' + expression + ' · ' + errores.join(' | '));
    };
    const cerrar = async () => {
        ws.close();
        proceso.kill();
        await new Promise(resolver => setTimeout(resolver, 400));
        rmSync(perfil, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
    };
    await enviar('Page.enable');
    await enviar('Runtime.enable');
    return { enviar, evaluar, esperar, cerrar, errores };
}

module.exports = { buscarChrome, abrirChrome };
