/** RE-2.1.6/7, RNF-16: estructura aprobada, con vistas reales y API sintética. */
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { createServer } = require('node:http');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');
const raiz = path.resolve(__dirname, '../..');

test('Atención V3: peor caso sin scroll y preservación de móvil', { skip: buscarChrome() ? false : 'Sin Chrome' }, async () => {
    const vista = execFileSync('php', ['tests/Frontend/apoyo/c81-vistas.php', 'vet_atencion'], { cwd: raiz, encoding: 'utf8' });
    const carpeta = path.join(raiz, 'scratch/r/atencion-v3');
    const assets = path.join(raiz, 'scratch/r/atencion-retoques/assets');
    fs.mkdirSync(carpeta, { recursive: true });
    let ficha = { sexo: null, esterilizado: null, fecha_nacimiento: null, identidad_editable: true };
    let fallaContexto = false;
    const servidor = createServer(async (req, res) => {
        const url = new URL(req.url, 'http://localhost');
        const accion = url.searchParams.get('action');
        if (accion === 'vet_atencion') {
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            let html = vista.replaceAll('Luna', 'Luna de los Valles del Norte').replaceAll('Fabio Propietario', 'Fabio Propietario de nombre extenso').replace(/<script[^>]+src="https:[^>]*><\/script>/g, '');
            const registros = Array.from({ length: 18 }, (_, indice) => `<li><details class="historia-fila"><summary class="historia-fila__resumen"><span class="historia-fila__lineas"><span class="historia-fila__linea historia-fila__linea--principal"><time>31/10/2026</time><strong class="historia-fila__titulo">Registro sintético de nombre extenso ${indice + 1}</strong></span><span class="historia-fila__linea historia-fila__linea--contexto">Clínica de pruebas</span></span></summary><div class="historia-fila__detalle"><p>Detalle sintético ${indice + 1}, conservado al abrir el recuadro.</p></div></details></li>`).join('');
            html = html.replace(/(<ul class="registros" id="listaVacunasAplicadas">)[\s\S]*?(<\/ul>)/, '$1' + registros + '$2');
            html = html.replace(/(<ul class="historial" id="atencionHistorial">)[\s\S]*?(<\/ul>)/, '$1' + registros + '$2');
            html = html.replaceAll('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', '/__assets/all.min.css');
            res.end(html);
            return;
        }
        if (accion) {
            res.setHeader('Content-Type', 'application/json');
            let texto = '';
            for await (const trozo of req) texto += trozo;
            let r = { success: true, notificaciones: [], nodos: [], consultas: [], vacunas: [], desparasitaciones: [], alertas: { activas: [1, 2].map(n => ({ tipo: 'alergia', tipo_nombre: 'Alergia', descripcion: 'Alerta sintética extensa ' + n })), historial: [] } };
            if (accion === 'prueba_contexto') fallaContexto = url.searchParams.get('fallo') === '1';
            if (accion === 'consulta_contexto_ajax') r = fallaContexto ? { success: false } : { success: true, id_clinica: 1, id_veterinario: 2, tiene_horario: true, control_dias_sin_duracion: 7, referencias: { temperatura: [37.5, 39.2] }, ficha };
            if (accion === 'get_mascota_ajax') r = { id_mascota: 7, nombre: 'Luna', ...ficha };
            if (accion === 'completar_ficha_consulta_ajax') ficha = { ...ficha, ...Object.fromEntries(new URLSearchParams(texto)) };
            if (accion === 'buscar_sintomas_ajax') r.sugerencias = Array.from({ length: 5 }, (_, n) => ({ codigo: 'sintoma.prueba' + n, etiqueta: 'Síntoma sintético extenso ' + n }));
            if (accion === 'sugerir_diagnosticos_ajax') r.candidatos = ['prueba', 'alternativo', 'tercero', 'cuarto', 'quinto'].map(codigo => ({ codigo: 'diagnostico.' + codigo, etiqueta: 'Diagnóstico sintético ' + codigo, cobertura: { texto: '1 síntoma' }, fuentes: [], sintomas_coincidentes: ['sintoma.prueba'] }));
            if (accion === 'sugerir_tratamientos_ajax') r = { success: true, plan: { examenes: Array.from({length:8},(_,n)=>'Examen sintético '+n), recomendaciones: Array.from({length:6},(_,n)=>('Recomendación '+n+' para el cuidado del paciente. ').repeat(5).slice(0,160)) }, tratamientos: Array.from({length:5},(_,n)=>({codigo:'pauta.'+n,medicamento:'Medicamento sintético de nombre extenso '+n,farmaco:'farmaco.'+n,dosis:'140 mg cada 12 h',via_administracion:'Oral',duracion:'7 días',observaciones:'Indicación sintética',seguridad:{bloqueada:false,motivos:[],advertencias:[],requiere_decision:false,requiere_conciliacion:false,evaluacion_incompleta:false}})) };
            if (accion === 'validar_prescripcion_ajax') r.filas=Array.from({length:6},()=>({bloqueada:false,motivos:[],advertencias:[],requiere_decision:false,requiere_conciliacion:false,evaluacion_incompleta:false,huella:'a'.repeat(64)}));
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
    async function verificar(nombre, ancho, alto) {
        await e('new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)))');
        const medida = await e(`(() => {
            const app=document.querySelector('#atencionApp');
            const caja=app.getBoundingClientRect();
            const recortados=[...app.querySelectorAll('*')].filter(c=>c.getBoundingClientRect().height>0 && !c.matches('input,textarea') && c.scrollHeight>c.clientHeight+1).map(c=>({clase:c.className,id:c.id,alto:c.clientHeight,contenido:c.scrollHeight}));
            const partidos=[...app.querySelectorAll('button')].filter(c=>c.getBoundingClientRect().height>0).filter(c=>{
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
            return {pagina:document.scrollingElement.scrollHeight,ventana:innerHeight,ancho:document.scrollingElement.scrollWidth,limites:{izquierda:caja.left,derecha:caja.right},recortados,partidos};
        })()`);
        medidas.push({nombre,ancho,alto,...medida});
        fs.writeFileSync(path.join(carpeta,'medidas.json'),JSON.stringify(medidas,null,2));
        const captura=await n.enviar('Page.captureScreenshot',{format:'png'});
        fs.writeFileSync(path.join(carpeta,`${ancho}-${alto}-${nombre}.png`),Buffer.from(captura.data,'base64'));
        assert.ok(medida.pagina<=alto,`Página sin scroll: ${JSON.stringify(medida)}`);
        assert.deepEqual(medida.recortados,[],'Sin contenido recortado dentro de Atención');
        assert.deepEqual(medida.partidos,[],'Botones de una línea');
        assert.ok(medida.ancho<=ancho,'Sin desborde horizontal');
        assert.ok(medida.limites.izquierda>=0 && medida.limites.derecha<=ancho,'Atención cabe completa en el ancho de la ventana');
    }
    try {
        await n.enviar('Page.addScriptToEvaluateOnNewDocument',{source:'localStorage.clear()'});
        for (const [ancho,alto] of [[1024,650],[1366,650],[1440,900],[1920,1080]]) {
            await n.enviar('Emulation.setDeviceMetricsOverride',{width:ancho,height:alto,deviceScaleFactor:1,mobile:false});
            await n.enviar('Page.navigate',{url:`http://127.0.0.1:${servidor.address().port}/index.php?action=vet_atencion`});
            await n.esperar("document.readyState==='complete' && document.querySelector('#atencionApp').classList.contains('atencion--escritorio')");
            await e(`window.f=document.querySelector('#formConsultaAtencion');
                window.poner=(s,v)=>{const c=f.querySelector(s);c.value=v;c.dispatchEvent(new Event('input',{bubbles:true}));};
                poner('[name=peso]','28.4');
                poner('[name=temperatura]','40');
                poner('[name=anamnesis]','Historia clínica sintética para verificar el campo que absorbe el espacio. '.repeat(12));`);
            for (let i=0;i<5;i++) {
                await e("poner('[data-sintomas-buscar]','prueba')");
                await n.esperar(`f.querySelector('[data-codigo="sintoma.prueba${i}"]')`);
                await e(`f.querySelector('[data-codigo="sintoma.prueba${i}"]').click()`);
            }
            await n.esperar("f.querySelectorAll('[data-usar-diagnostico]').length===5");
            await verificar('evaluacion',ancho,alto);
            assert.equal(await e("(()=>{const a=document.querySelector('.paciente__telefono');const d=a.closest('dd');return a.getBoundingClientRect().right<=d.getBoundingClientRect().right+1;})()"),true,'El nombre largo no oculta el teléfono');
            await e("document.querySelector('[data-completar-ficha]').click()");
            await verificar('ficha',ancho,alto);
            await e("f.querySelector('[data-ficha-mini]').hidePopover()");
            await e("f.querySelector('[data-usar-diagnostico]').click()");
            await n.esperar("f.querySelectorAll('[data-tratamiento]').length===5");
            await n.esperar("document.querySelector('#estadoBorrador').textContent.includes('Borrador guardado')");
            await verificar('diagnostico',ancho,alto);
            assert.ok(await e("parseFloat(getComputedStyle(f.querySelector('[name=motivo]')).fontSize)>=14"),'Campos con fuente legible');
            assert.ok(await e("parseFloat(getComputedStyle(f.querySelector('[data-recomendacion]')).fontSize)>=12"),'Recomendaciones legibles');
            assert.ok(await e("parseFloat(getComputedStyle(document.querySelector('#btnPasoSiguiente')).fontSize)>=12"),'Botones legibles');
            assert.equal(await e("f.querySelectorAll('[data-recomendacion]').length"),6);
            assert.equal(await e("f.querySelectorAll('[data-examenes-elegibles] input').length"),8);
            await e("fetch('/index.php?action=prueba_contexto&fallo=1')");
            await e("f.querySelector('[data-contexto-reintentar]').click()");
            await n.esperar("!f.querySelector('[data-contexto-reintentar]').hidden");
            await verificar('contexto-fallido',ancho,alto);
            assert.equal(await e("f.querySelector('[data-control-activo]').disabled"),true);
            await e("fetch('/index.php?action=prueba_contexto&fallo=0')");
            await e("f.querySelector('[data-contexto-reintentar]').click()");
            await n.esperar("f.querySelector('[data-contexto-aviso]').hidden");
            await e("document.querySelector('[data-consulta-ir=\"2\"]').click()");
            await n.esperar("f.querySelector('[data-consulta-tres]').dataset.pasoActual==='2'");
            await verificar('cierre',ancho,alto);
            await e("document.querySelector('[data-tab=vacuna]').click()");
            await verificar('vacuna',ancho,alto);
            assert.equal(await e("document.querySelector('#listaVacunasAplicadas').nextElementSibling.hidden"),false,'Vacuna permite ver los registros que no caben');
            await e("document.querySelector('[data-tab=historial]').click()");
            await verificar('historial',ancho,alto);
            await e("document.querySelector('#atencionHistorial').nextElementSibling.click()");
            await verificar('historial-mas',ancho,alto);
            await e("document.querySelector('.atencion-lista-dialogo[open]').close()");
            await e('new Promise(r=>requestAnimationFrame(r))');
            await e("document.querySelector('#atencionHistorial summary').click()");
            await verificar('registro',ancho,alto);
            await e("document.querySelector('.atencion-lista-dialogo[open]').close()");
            await e("document.querySelector('[data-tab=consulta]').click();document.querySelector('[data-consulta-ir=\"1\"]').click();document.querySelector('[data-agregar-tratamiento]').click()");
            await n.esperar("Boolean(f.querySelector('.atencion-editor[open]'))");
            assert.ok(await e("f.querySelector('.atencion-editor[open]').getBoundingClientRect().height>0"),'El editor debe verse, no solo tener open');
            assert.equal(await e("document.activeElement.closest('.atencion-editor')===f.querySelector('.atencion-editor[open]')"),true,'El foco queda en el editor visible');
            const recuadro = await e("(()=>{const r=f.querySelector('.atencion-editor[open]').getBoundingClientRect();return {x:r.x,y:r.y,derecha:r.right,abajo:r.bottom,centro:r.x+r.width/2};})()");
            assert.ok(recuadro.x>=0 && recuadro.y>=0 && recuadro.derecha<=ancho && recuadro.abajo<=alto,'El recuadro cabe completo');
            assert.ok(Math.abs(recuadro.centro-ancho/2)<=1,'Recuadro centrado de Zooki');
            assert.deepEqual(await e("[...f.querySelectorAll('[data-tratamiento]')].map(fila=>fila.dataset.pautaSugerida||'nueva')"),['pauta.0','pauta.1','pauta.2','pauta.3','pauta.4','nueva'],'El recuadro conserva el orden clínico de las filas');
            await verificar('editor-medicamento',ancho,alto);
            await e(`window.ultima=f.querySelector('.atencion-editor[open]').closest('[data-tratamiento]');
                for(const [campo,valor] of Object.entries({medicamento:'Sexto medicamento sintético',dosis:'1 tableta cada 12 h',via_administracion:'Oral',duracion:'7 días'})) {
                    const input=ultima.querySelector('[data-tratamiento-campo="'+campo+'"]');
                    input.value=valor;
                    input.dispatchEvent(new Event('input',{bubbles:true}));
                }
                ultima.querySelector('[data-cerrar-tratamiento]').click();`);
            await n.esperar("!f.querySelector('.atencion-editor[open]')");
            assert.equal(await e("document.querySelector('[data-ver-tratamientos]').textContent"),'Ver 2 más');
            await verificar('seis-tratamientos',ancho,alto);
            await e("document.querySelector('[data-ver-tratamientos]').click()");
            await verificar('mas-tratamientos',ancho,alto);
            await e("document.querySelector('.atencion-tratamientos-mas').hidePopover()");
            assert.equal(await e("new FormData(f).getAll('med_nombre[]').length"),6,'El formulario conserva también los tratamientos del recuadro');
            if (ancho === 1366) {
                for (let indice=6;indice<12;indice++) {
                    await e("document.querySelector('[data-agregar-tratamiento]').click()");
                    await n.esperar("Boolean(f.querySelector('.atencion-editor[open]'))");
                    await e(`window.filaNueva=f.querySelector('.atencion-editor[open]').closest('[data-tratamiento]');
                        for(const [campo,valor] of Object.entries({medicamento:'Medicamento adicional ${indice}',dosis:'1 tableta cada 12 h',via_administracion:'Oral',duracion:'7 días'})) {
                            const input=filaNueva.querySelector('[data-tratamiento-campo="'+campo+'"]');
                            input.value=valor;
                            input.dispatchEvent(new Event('input',{bubbles:true}));
                        }
                        filaNueva.querySelector('[data-cerrar-tratamiento]').click();`);
                    await n.esperar("!f.querySelector('.atencion-editor[open]')");
                }
                await e("document.querySelector('[data-ver-tratamientos]').click()");
                await verificar('tratamientos-paginados',ancho,alto);
                assert.equal(await e("new FormData(f).getAll('med_nombre[]').length"),12,'La paginación conserva todos los datos');
                await e("document.querySelector('.atencion-tratamientos-mas').hidePopover()");
            }
        }
        await n.enviar('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
        await e('new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)))');
        assert.equal(await e("document.querySelector('#atencionApp').classList.contains('atencion--escritorio')"),false);
        assert.ok(await e('document.scrollingElement.scrollHeight>innerHeight'),'El móvil conserva scroll de página');
        assert.equal(await e("getComputedStyle(f.querySelector('[data-hoja-pie]')).position"),'sticky');
        assert.equal(await e("[...f.querySelectorAll('img[src*=portal-bienvenida]')].filter(i=>i.getBoundingClientRect().height>0).length"),0);
        assert.equal(await e("f.querySelectorAll('[data-tratamiento]').length"),6,'Resize conserva todas las filas');
        await n.enviar('Emulation.setDeviceMetricsOverride',{width:1366,height:600,deviceScaleFactor:1,mobile:false});
        await e('new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)))');
        assert.ok(await e('document.scrollingElement.scrollHeight>innerHeight'),'Respaldo de página bajo650px');
        assert.deepEqual(n.errores,[],'Sin excepciones JavaScript');
    } finally {
        await n.cerrar();
        await new Promise(r=>servidor.close(r));
    }
});
