// Chrome sin interfaz manejado por su protocolo de depuración (CDP).
// Se usa el WebSocket que trae Node 22 en vez de Puppeteer para no sumar
// una dependencia de npm (y una descarga de Chromium) a un repositorio PHP.

import { spawn } from 'node:child_process';
import { existsSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const RUTAS_CHROME = [
    process.env.CHROME_PATH,
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
    '/usr/bin/google-chrome',
    '/usr/bin/chromium',
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
];

function buscarChrome() {
    const ruta = RUTAS_CHROME.find(r => r && existsSync(r));
    if (!ruta) {
        throw new Error('No se encontró Chrome ni Edge. Define CHROME_PATH con la ruta del ejecutable.');
    }
    return ruta;
}

export async function abrirChrome() {
    const perfil = mkdtempSync(join(tmpdir(), 'zooki-chrome-'));
    const proceso = spawn(buscarChrome(), [
        '--headless=new',
        '--remote-debugging-port=0',
        `--user-data-dir=${perfil}`,
        '--no-first-run',
        '--no-default-browser-check',
        // La plantilla se abre como file:// y carga marked, Mermaid y Paged.js del CDN.
        '--allow-file-access-from-files',
        'about:blank'
    ], { stdio: ['ignore', 'ignore', 'pipe'] });

    // Con el puerto 0 Chrome elige uno libre y lo anuncia por stderr.
    const wsNavegador = await new Promise((resolve, reject) => {
        let salida = '';
        const limite = setTimeout(() => reject(new Error('Chrome no arrancó en 20 s.')), 20000);
        proceso.stderr.on('data', trozo => {
            salida += trozo;
            const m = salida.match(/DevTools listening on (ws:\/\/\S+)/);
            if (m) {
                clearTimeout(limite);
                resolve(m[1]);
            }
        });
        proceso.on('exit', codigo => reject(new Error(`Chrome terminó con código ${codigo}.`)));
    });

    const base = wsNavegador.replace(/^ws:\/\/([^/]+).*/, 'http://$1');

    return {
        async nuevaPestana() {
            const info = await (await fetch(`${base}/json/new?about:blank`, { method: 'PUT' })).json();
            return conectar(info.webSocketDebuggerUrl);
        },
        cerrar() {
            proceso.kill();
            try { rmSync(perfil, { recursive: true, force: true }); } catch { /* Chrome puede tardar en soltar el perfil */ }
        }
    };
}

async function conectar(url) {
    const ws = new WebSocket(url);
    await new Promise((resolve, reject) => {
        ws.onopen = resolve;
        ws.onerror = () => reject(new Error('No se pudo conectar con la pestaña de Chrome.'));
    });

    let siguienteId = 0;
    const pendientes = new Map();
    const errores = [];

    ws.onmessage = mensaje => {
        const datos = JSON.parse(mensaje.data);
        if (datos.id && pendientes.has(datos.id)) {
            const { resolve, reject } = pendientes.get(datos.id);
            pendientes.delete(datos.id);
            if (datos.error) reject(new Error(datos.error.message));
            else resolve(datos.result);
        } else if (datos.method === 'Runtime.exceptionThrown') {
            errores.push(datos.params.exceptionDetails.exception?.description || datos.params.exceptionDetails.text);
        }
    };

    const enviar = (method, params = {}) => new Promise((resolve, reject) => {
        const id = ++siguienteId;
        pendientes.set(id, { resolve, reject });
        ws.send(JSON.stringify({ id, method, params }));
    });

    await enviar('Page.enable');
    await enviar('Runtime.enable');

    const evaluar = async (expresion) => {
        const r = await enviar('Runtime.evaluate', { expression: expresion, awaitPromise: true, returnByValue: true });
        if (r.exceptionDetails) {
            throw new Error(r.exceptionDetails.exception?.description || r.exceptionDetails.text);
        }
        return r.result.value;
    };

    return {
        errores,
        evaluar,

        async abrir(url) {
            await enviar('Page.navigate', { url });
        },

        // Espera a que la página publique window.__zooki = { listo | error }.
        async esperar(segundos = 120) {
            const fin = Date.now() + segundos * 1000;
            while (Date.now() < fin) {
                const estado = await evaluar('window.__zooki ? JSON.stringify(window.__zooki) : null').catch(() => null);
                if (estado) {
                    const e = JSON.parse(estado);
                    if (e.error) throw new Error(e.error);
                    if (e.listo) return e;
                }
                await new Promise(r => setTimeout(r, 250));
            }
            throw new Error(`La página no terminó de maquetar en ${segundos} s. ${errores.join(' | ')}`);
        },

        async imprimirPdf() {
            const r = await enviar('Page.printToPDF', {
                printBackground: true,
                // El tamaño y los márgenes los pone @page (Paged.js).
                preferCSSPageSize: true,
                generateTaggedPDF: true,
                // Marcadores del PDF a partir de los títulos: el panel lateral
                // del lector sirve de índice navegable.
                generateDocumentOutline: true
            });
            return Buffer.from(r.data, 'base64');
        },

        cerrar() {
            ws.close();
        }
    };
}
