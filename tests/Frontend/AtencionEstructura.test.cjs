/** RE-2.1.6/7, RNF-16: estructura aprobada, con vistas reales y API sintética. */
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { createServer } = require('node:http');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');
const raiz = path.resolve(__dirname, '../..');

test('Atención: scroll de página, cabecera, piezas y pie sticky en 1366/390', { skip: buscarChrome() ? false : 'Sin Chrome' }, async () => {
    const vista = execFileSync('php', ['tests/Frontend/apoyo/c81-vistas.php', 'vet_atencion'], { cwd: raiz, encoding: 'utf8' });
    const carpeta = path.join(raiz, 'scratch/r/atencion-v2');
    const assets = path.join(raiz, 'scratch/r/atencion-retoques/assets');
    fs.mkdirSync(carpeta, { recursive: true });
    let ficha = { sexo: null, esterilizado: null, fecha_nacimiento: null, identidad_editable: true };
    const servidor = createServer(async (req, res) => {
        const url = new URL(req.url, 'http://localhost');
        const accion = url.searchParams.get('action');
        if (accion === 'vet_atencion') {
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            let html = vista.replace(/<script[^>]+src="https:[^>]*><\/script>/g, '');
            html = html.replaceAll('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', '/__assets/all.min.css');
            res.end(html);
            return;
        }
        if (accion) {
            res.setHeader('Content-Type', 'application/json');
            let texto = '';
            for await (const trozo of req) texto += trozo;
            let r = { success: true, notificaciones: [], nodos: [], consultas: [], vacunas: [], desparasitaciones: [], alertas: { activas: [{ tipo: 'alergia', tipo_nombre: 'Alergia', descripcion: 'Alerta sintética' }], historial: [] } };
            if (accion === 'consulta_contexto_ajax') r = { success: true, id_clinica: 1, id_veterinario: 2, tiene_horario: true, control_dias_sin_duracion: 7, referencias: { temperatura: [37.5, 39.2] }, ficha };
            if (accion === 'get_mascota_ajax') r = { id_mascota: 7, nombre: 'Luna', ...ficha };
            if (accion === 'completar_ficha_consulta_ajax') ficha = { ...ficha, ...Object.fromEntries(new URLSearchParams(texto)) };
            if (accion === 'buscar_sintomas_ajax') r.sugerencias = [{ codigo: 'sintoma.prueba', etiqueta: 'Síntoma sintético' }];
            if (accion === 'sugerir_diagnosticos_ajax') r.candidatos = ['prueba', 'alternativo'].map(codigo => ({ codigo: 'diagnostico.' + codigo, etiqueta: 'Diagnóstico sintético ' + codigo, cobertura: { texto: '1 síntoma' }, fuentes: [], sintomas_coincidentes: ['sintoma.prueba'] }));
            if (accion === 'sugerir_tratamientos_ajax') r = { success: true, plan: { examenes: ['Examen sintético'], recomendaciones: ['Recomendación sintética'] }, tratamientos: [] };
            if (accion === 'fechas_control_ajax') r.dias_abiertos = [url.searchParams.get('desde')];
            res.end(JSON.stringify(r));
            return;
        }
        const esAsset = url.pathname.startsWith('/__assets/') || url.pathname.startsWith('/webfonts/');
        const base = esAsset ? assets : path.join(raiz, 'public');
        const archivo = path.resolve(base, '.' + url.pathname.replace('/__assets/', '/'));
        if (!archivo.startsWith(base + path.sep) || !fs.existsSync(archivo)) {
            res.writeHead(404);
            res.end();
            return;
        }
        res.setHeader('Content-Type', ({ '.css': 'text/css', '.js': 'application/javascript', '.svg': 'image/svg+xml', '.woff2': 'font/woff2' })[path.extname(archivo)] || 'application/octet-stream');
        res.end(fs.readFileSync(archivo));
    });
    await new Promise(r => servidor.listen(0, '127.0.0.1', r));
    const n = await abrirChrome(buscarChrome());
    const e = x => n.evaluar(x);
    const medidas = [];
    try {
        await n.enviar('Page.addScriptToEvaluateOnNewDocument', { source: 'localStorage.clear()' });
        for (const ancho of [1366, 390]) {
            await n.enviar('Emulation.setDeviceMetricsOverride', { width: ancho, height: ancho === 1366 ? 768 : 844, deviceScaleFactor: 1, mobile: ancho < 768 });
            await n.enviar('Page.navigate', { url: `http://127.0.0.1:${servidor.address().port}/index.php?action=vet_atencion` });
            await n.esperar("document.readyState==='complete' && document.querySelector('[data-consulta-tres]')?.dataset.pasoActual==='0'");
            await e("localStorage.clear();window.f=document.querySelector('#formConsultaAtencion');window.poner=(s,v)=>{const c=f.querySelector(s);c.value=v;c.dispatchEvent(new Event('input',{bubbles:true}));};");
            await n.esperar("!f.querySelector('[data-ficha-mini]').hidden");
            await e("poner('[name=peso]','28.4');poner('[name=temperatura]','40');poner('[data-sintomas-buscar]','prueba')");
            await n.esperar("f.querySelector('[data-codigo=\"sintoma.prueba\"]')");
            await e("f.querySelector('[data-codigo=\"sintoma.prueba\"]').click()");
            await n.esperar("f.querySelector('[data-usar-diagnostico]')");
            await n.esperar("document.querySelector('#estadoBorrador').textContent.includes('Borrador guardado')");
            for (const paso of [0, 1, 2, 'vacuna']) {
                if (paso === 'vacuna') await e("document.querySelector('[data-tab=vacuna]').click()");
                else {
                    if (paso === 1) {
                        await e("f.querySelector('[data-usar-diagnostico]').click()");
                    }
                    if (paso === 2) await e("document.querySelector('[data-consulta-ir=\"2\"]').click()");
                    await n.esperar(`f.querySelector('[data-consulta-tres]').dataset.pasoActual==='${paso}'`);
                }
                await e('window.scrollTo(0,0)');
                const m = await e(`(() => {
                    const app=document.querySelector('#atencionApp');
                    const visibles=[...app.querySelectorAll('*')].filter(c=>c.getBoundingClientRect().height && getComputedStyle(c).visibility!=='hidden');
                    const recortados=visibles.filter(c=>/auto|scroll/.test(getComputedStyle(c).overflowY) && c.scrollHeight>c.clientHeight+1).map(c=>c.className);
                    const partidos=visibles.filter(c=>c.matches('button')).filter(c=>{
                        const textos=document.createTreeWalker(c,NodeFilter.SHOW_TEXT);
                        const lineas=[];
                        while(textos.nextNode()) {
                            if(!textos.currentNode.textContent.trim()) continue;
                            const rango=document.createRange();
                            rango.selectNodeContents(textos.currentNode);
                            lineas.push(...rango.getClientRects());
                        }
                        return lineas.some(a=>lineas.some(b=>Math.abs(a.top-b.top)>Math.min(a.height,b.height)*.8));
                    }).map(c=>c.textContent.trim());
                    const colores=visibles.flatMap(c=>['color','backgroundColor','borderTopColor'].map(p=>getComputedStyle(c)[p])).filter(c=>{
                        const valores=c.match(/[\d.]+/g)?.map(Number);
                        if(!valores || valores.length>3 && valores[3]===0) return false;
                        const [r,g,b]=valores;
                        return g>r*1.12 && g>b*1.12 || r>120 && g>65 && g<r*.95 && b<g*.8;
                    });
                    return {alto:document.documentElement.scrollHeight,ventana:innerHeight,recortados,partidos,colores,ancho:document.documentElement.scrollWidth,panel:getComputedStyle(app.querySelector('.consulta-panel')).position,pie:getComputedStyle(app.querySelector('[data-hoja-pie]')).position,perros:visibles.filter(c=>c.matches('img[src*=portal-bienvenida]')).length,pasos:app.querySelector('[data-consulta-navegacion]').getBoundingClientRect().height,tabs:app.querySelector('.atencion__tabs').getBoundingClientRect().top,pasosY:app.querySelector('[data-consulta-navegacion]').getBoundingClientRect().top};
                })()`);
                medidas.push({ viewport: ancho, paso, ...m });
                fs.writeFileSync(path.join(carpeta, 'medidas.json'), JSON.stringify(medidas, null, 2));
                const foto = await n.enviar('Page.captureScreenshot', { format: 'png' });
                fs.writeFileSync(path.join(carpeta, `${ancho}-${paso}.png`), Buffer.from(foto.data, 'base64'));
                const plano = await n.enviar('Page.getLayoutMetrics');
                const completa = await n.enviar('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true, clip: { x: 0, y: 0, width: plano.cssContentSize.width, height: plano.cssContentSize.height, scale: 1 } });
                fs.writeFileSync(path.join(carpeta, `${ancho}-${paso}-completa.png`), Buffer.from(completa.data, 'base64'));
                await e('window.scrollTo(0,document.documentElement.scrollHeight);new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)))');
                const inferior = await n.enviar('Page.captureScreenshot', { format: 'png' });
                fs.writeFileSync(path.join(carpeta, `${ancho}-${paso}-inferior.png`), Buffer.from(inferior.data, 'base64'));
                assert.ok(m.alto > m.ventana, `Scroll de página ${ancho}/${paso}: ${JSON.stringify(m)}`);
                assert.deepEqual(m.recortados, [], 'Sin scroll vertical interno');
                assert.deepEqual(m.partidos, [], 'Botones de una línea');
                assert.deepEqual(m.colores, [], 'Solo azul, neutros y rojo');
                assert.ok(m.ancho <= ancho, 'Sin desborde horizontal');
                if (paso !== 'vacuna') {
                    assert.equal(m.pie, 'sticky');
                    if (ancho === 1366) {
                        assert.equal(m.panel, 'sticky');
                        assert.ok(Math.abs(m.tabs - m.pasosY) < 5, 'Pestañas y pasos en la misma fila');
                    } else assert.equal(m.perros, 0);
                } else assert.equal(m.pasos, 0, 'Vacuna sin pasos');
                if (paso !== 'vacuna') {
                    const campo = paso === 0 ? 'anamnesis' : paso === 1 ? 'otras_indicaciones' : 'observaciones';
                    const selector = paso === 1 ? '[data-otras-indicaciones]' : `[name=${campo}]`;
                    await e(`poner('${selector}','Texto sintético para comprobar el scroll de página. '.repeat(45));window.scrollTo(0,600)`);
                    await e('new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)))');
                    const desplazado = await e(`({panel:document.querySelector('.consulta-panel').getBoundingClientRect().top,pie:document.querySelector('[data-hoja-pie]').getBoundingClientRect().bottom,scroll:scrollY})`);
                    medidas[medidas.length - 1].desplazado = desplazado;
                    fs.writeFileSync(path.join(carpeta, 'medidas.json'), JSON.stringify(medidas, null, 2));
                    if (ancho === 1366) assert.ok(Math.abs(desplazado.panel - 20) <= 1, `Panel pegado a20px: ${JSON.stringify(desplazado)}`);
                    assert.ok(desplazado.pie <= (ancho === 1366 ? 768 : 844) + 1, 'Pie visible al desplazar');
                    await e(`poner('${selector}','');window.scrollTo(0,0)`);
                }
            }
        }
    } finally {
        await n.cerrar();
        await new Promise(r => servidor.close(r));
    }
});
