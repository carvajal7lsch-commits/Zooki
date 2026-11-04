/** RE-2.1.7: restaurar un medicamento sin dosis no puede dejar toda Atención inerte. */
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { createServer } = require('node:http');
const { execFileSync } = require('node:child_process');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');

test('Borrador incompleto en Evaluación: clic real habilitado y editor visible al abrir Diagnóstico', { skip: !buscarChrome() }, async () => {
    const raiz = path.resolve(__dirname, '../..');
    let html = execFileSync(process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php', ['tests/Frontend/apoyo/c81-vistas.php', 'vet_atencion'], { cwd: raiz, encoding: 'utf8' });
    const carpeta = path.join(raiz, 'scratch/r/userscript/dependencias');
    const mapa = path.join(carpeta, 'mapa.json');
    if (fs.existsSync(mapa)) {
        for (const [remota, local] of Object.entries(JSON.parse(fs.readFileSync(mapa)))) html = html.replaceAll(remota, local);
    }
    html = html
        .replace(/<script[^>]+src="https:[^>]*><\/script>/g, '')
        .replace(/<link[^>]+href="https:[^>]*>/g, '');
    const servidor = createServer((req, res) => {
        const url = new URL(req.url, 'http://localhost');
        if (url.searchParams.get('action') === 'vet_atencion') {
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            res.end(html);
        } else if (url.pathname.startsWith('/__dependencia/') || url.pathname.startsWith('/webfonts/')) {
            const archivo = path.join(carpeta, path.basename(url.pathname));
            res.setHeader('Content-Type', archivo.endsWith('.js') ? 'application/javascript' : archivo.endsWith('.css') ? 'text/css' : 'font/woff2');
            res.end(fs.existsSync(archivo) ? fs.readFileSync(archivo) : '');
        } else if (url.pathname === '/index.php') {
            res.setHeader('Content-Type', 'application/json');
            res.end(JSON.stringify({ success: true, id_clinica: 1, id_veterinario: 2, tiene_horario: true, ultimo_peso: 9, referencias: { temperatura: [37.5, 39.2], frecuencia_cardiaca: [60, 120], frecuencia_respiratoria: [18, 24] }, notificaciones: [], alertas: { activas: [], historial: [] }, consultas: [], vacunas: [], desparasitaciones: [], filas: [], id_mascota: 7, nombre: 'Luna', candidatos: [], datos_faltantes: [], sugerencias: [] }));
        } else {
            const base = path.join(raiz, 'public');
            const archivo = path.resolve(base, '.' + url.pathname);
            if (!archivo.startsWith(base + path.sep) || !fs.existsSync(archivo) || !fs.statSync(archivo).isFile()) {
                res.writeHead(404);
                res.end();
                return;
            }
            res.setHeader('Content-Type', archivo.endsWith('.js') ? 'application/javascript' : archivo.endsWith('.css') ? 'text/css' : 'image/svg+xml');
            res.end(fs.readFileSync(archivo));
        }
    });
    await new Promise(r => servidor.listen(0, '127.0.0.1', r));
    const nav = await abrirChrome(buscarChrome());
    try {
        await nav.enviar('Page.addScriptToEvaluateOnNewDocument', { source: `localStorage.setItem('zooki_atencion_borrador_1_2_5',JSON.stringify({version:3,campos:{motivo:'Control recuperado',diagnostico:'Control',peso:'9'},paso:0,tratamientos:[{campos:{medicamento:'Tratamiento recuperado',dosis:'',via_administracion:'Oral',duracion:'5 días'}}],sintomas:[]}))` });
        for (const width of [1366, 390]) {
            await nav.enviar('Emulation.setDeviceMetricsOverride', { width, height: width === 390 ? 844 : 768, deviceScaleFactor: 1, mobile: width === 390 });
            if (width === 1366) await nav.enviar('Page.navigate', { url: `http://127.0.0.1:${servidor.address().port}/index.php?action=vet_atencion` });
            else await nav.evaluar(`document.querySelector('[data-consulta-ir="0"]').click()`);
            await nav.esperar(`document.readyState==='complete' && document.querySelector('[data-tratamiento]') !== null`);
            await nav.evaluar('new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)))');
            assert.deepEqual(await nav.evaluar(`[...document.querySelectorAll('dialog[open]')].map(n=>n.className)`), []);
            await nav.evaluar(`document.querySelector('[name=motivo]').scrollIntoView({block:'center'})`);
            const punto = await nav.evaluar(`(()=>{const r=document.querySelector('[name=motivo]').getBoundingClientRect();return{x:r.x+20,y:r.y+15}})()`);
            await nav.enviar('Input.dispatchMouseEvent', { type: 'mousePressed', button: 'left', clickCount: 1, ...punto });
            await nav.enviar('Input.dispatchMouseEvent', { type: 'mouseReleased', button: 'left', clickCount: 1, ...punto });
            assert.equal(await nav.evaluar('document.activeElement.name'), 'motivo');
            assert.equal(await nav.evaluar(`document.querySelector('[data-tratamiento-campo=medicamento]').value`), 'Tratamiento recuperado');
            if (width === 1366) {
                await nav.evaluar(`document.querySelector('[data-consulta-ir="1"]').click()`);
                await nav.esperar(`document.querySelector('.atencion-editor[open]')?.getBoundingClientRect().width > 0`);
                assert.equal(await nav.evaluar(`document.activeElement.closest('.atencion-editor') !== null`), true);
            }
        }
        assert.deepEqual(nav.errores, []);
    } finally {
        await nav.cerrar();
        servidor.closeAllConnections();
        await new Promise(r => servidor.close(r));
    }
});
