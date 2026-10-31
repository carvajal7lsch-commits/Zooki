/** RE-2.1.6 y RE-2.8.1/6: vistas reales y API sintética; ninguna base de trabajo. */
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { createServer } = require('node:http');
const { buscarChrome, abrirChrome } = require('./apoyo/chrome-cdp.cjs');
const raiz = path.resolve(__dirname, '../..');
const chrome = buscarChrome();

test('Retoques: recuperación, ficha, plan compuesto y presentación 390/1366', { skip: chrome ? false : 'Sin Chrome' }, async () => {
    const vistas = Object.fromEntries(['vet_atencion', 'vet_pacientes'].map(a => [a, execFileSync('php', ['tests/Frontend/apoyo/c81-vistas.php', a], { cwd: raiz, encoding: 'utf8' })]));
    const assets = path.join(raiz, 'scratch/r/atencion-retoques/assets');
    const assetsReales = fs.existsSync(path.join(assets, 'flatpickr.min.js'));
    let falla = false;
    let guardados = 0;
    let calculos = 0;
    let ficha = { sexo: null, esterilizado: null, fecha_nacimiento: null, identidad_editable: true };
    const servidor = createServer(async (req, res) => {
        const url = new URL(req.url, 'http://localhost');
        const a = url.searchParams.get('action');
        if (vistas[a]) {
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            let html = vistas[a];
            if (assetsReales) {
                html = html.replaceAll('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', '/__assets/all.min.css');
                html = html.replaceAll('https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', '/__assets/flatpickr.min.css');
                html = html.replaceAll('src="https://cdn.jsdelivr.net/npm/flatpickr"', 'src="/__assets/flatpickr.min.js"');
            }
            res.end(html.replace(/<script[^>]+src="https:[^>]*><\/script>/g, ''));
            return;
        }
        if (a) {
            res.setHeader('Content-Type', 'application/json');
            let texto = '';
            for await (const parte of req) texto += parte;
            const datos = new URLSearchParams(texto);
            let r = { success: true, notificaciones: [], alertas: { activas: [1,2,3,4].map(n => ({tipo:'alergia',tipo_nombre:'Alergia',descripcion:'Alerta sintética '+n})), historial: [] }, nodos: [], consultas: [], vacunas: [], desparasitaciones: [] };
            if (a === 'consulta_contexto_ajax' && falla) res.statusCode = 500;
            if (a === 'consulta_contexto_ajax') r = falla ? { success:false } : { success:true, id_clinica:1, id_veterinario:2, tiene_horario:true, control_dias_sin_duracion:7, referencias:{temperatura:[37.5,39.2]}, ultimo_peso:null, ficha };
            if (a === 'get_mascota_ajax') r = { id_mascota:7, nombre:'Luna', ...ficha };
            if (a === 'completar_ficha_consulta_ajax') {
                guardados++;
                ficha = { ...ficha, ...Object.fromEntries(datos) };
            }
            if (a === 'buscar_sintomas_ajax') r.sugerencias = [{ codigo:'sintoma.prueba', etiqueta:'Síntoma sintético' }];
            if (a === 'sugerir_diagnosticos_ajax') {
                calculos++;
                r.candidatos = [{codigo:'diagnostico.prueba',etiqueta:'Diagnóstico sintético'+(ficha.sexo?' actualizado':''),cobertura:{texto:'1 síntoma'},fuentes:[],sintomas_coincidentes:['sintoma.prueba']}];
                r.datos_faltantes = [{ campos:Object.entries(ficha).filter(([k,v]) => v === null).map(([k]) => k === 'fecha_nacimiento' ? 'edad_meses' : k) }];
            }
            if (a === 'sugerir_tratamientos_ajax') r = {success:true,plan:{examenes:['Examen sintético','Segundo examen'],recomendaciones:['Reposo sintético']},tratamientos:[]};
            if (a === 'validar_prescripcion_ajax') r.filas = [];
            if (a === 'fechas_control_ajax') r.dias_abiertos = [url.searchParams.get('desde')];
            res.end(JSON.stringify(r));
            return;
        }
        if (assetsReales && (url.pathname.startsWith('/__assets/') || url.pathname.startsWith('/webfonts/'))) {
            const nombre = url.pathname.replace(/^\/__assets\//, '').replace(/^\/webfonts\//, 'webfonts/');
            const archivo = path.resolve(assets, nombre);
            if (archivo.startsWith(assets + path.sep) && fs.existsSync(archivo)) {
                res.setHeader('Content-Type', nombre.endsWith('.css') ? 'text/css' : nombre.endsWith('.js') ? 'application/javascript' : 'font/woff2');
                res.end(fs.readFileSync(archivo));
                return;
            }
        }
        const archivo = path.resolve(raiz, 'public', '.' + decodeURIComponent(url.pathname));
        if (!archivo.startsWith(path.join(raiz,'public')+path.sep) || !fs.existsSync(archivo)) {
            res.writeHead(404);
            res.end();
            return;
        }
        res.setHeader('Content-Type', ({'.js':'application/javascript','.css':'text/css','.svg':'image/svg+xml'})[path.extname(archivo)] || 'application/octet-stream');
        res.end(fs.readFileSync(archivo));
    });
    await new Promise(r => servidor.listen(0,'127.0.0.1',r));
    const n = await abrirChrome(chrome);
    const e = x => n.evaluar(x);
    const carpeta = path.join(raiz,'scratch/r/atencion-retoques',process.env.ZOOKI_CAPTURAS_ANTES ? 'antes' : 'despues');
    fs.mkdirSync(carpeta,{recursive:true});
    const captura = async nombre => {
        await e('document.fonts.ready');
        await new Promise(r => setTimeout(r, 250));
        const r = await n.enviar('Page.captureScreenshot',{format:'png'});
        fs.writeFileSync(path.join(carpeta,nombre+'.png'),Buffer.from(r.data,'base64'));
    };
    try {
        for (const ancho of (process.env.ZOOKI_SOLO_RECUPERACION ? [] : [390,1366])) {
            await n.enviar('Emulation.setDeviceMetricsOverride',{width:ancho,height:900,deviceScaleFactor:1,mobile:ancho<768});
            for (const a of ['vet_atencion','vet_pacientes']) {
                await n.enviar('Page.navigate',{url:`http://127.0.0.1:${servidor.address().port}/index.php?action=${a}`});
                await n.esperar("document.readyState==='complete' && typeof ZookiConsultaPasos !== 'undefined'");
                await e('localStorage.clear()');
                if (a === 'vet_pacientes') await e("openConsultationModal(7,'Luna')");
                await e(`window.f=document.querySelector('${a==='vet_atencion'?'#formConsultaAtencion':'#formConsulta'}'); window.poner=(s,v)=>{const c=f.querySelector(s);c.value=v;c.dispatchEvent(new Event('input',{bubbles:true}));};`);
                await n.esperar('!f.inert');
                if (!process.env.ZOOKI_CAPTURAS_ANTES) {
                    await e("poner('[name=peso]','');ZookiConsultaForm.validarTodo(f.querySelector('[data-consulta-paso=\"0\"]'))");
                    assert.equal(await e("getComputedStyle(f.querySelector('[data-campo-aviso=peso]')).color"),'rgb(185, 28, 28)','Error rojo');
                    if (a === 'vet_atencion') assert.notEqual(await e("getComputedStyle(f.querySelector('[data-campo-aviso=peso]'),'::before').content"), 'none', 'Error con icono debajo del campo');
                    await e("poner('[name=peso]','9');poner('[name=temperatura]','40')");
                    await n.esperar("f.querySelector('[data-vital=temperatura]').classList.contains('consulta-vital--fuera')");
                    assert.equal(await e("getComputedStyle(f.querySelector('[data-vital=temperatura] input')).color"),'rgb(0, 82, 255)','Referencia azul');
                }
                await captura(`${a}-${ancho}-evaluacion`);
                await e("poner('[name=motivo]','Evaluación sintética');poner('[name=peso]','9');poner('[data-sintomas-buscar]','prueba')");
                await n.esperar("f.querySelector('[data-codigo=\"sintoma.prueba\"]')");
                await e("f.querySelector('[data-codigo=\"sintoma.prueba\"]').click()");
                await n.esperar("f.querySelector('[data-usar-diagnostico]')");
                if (!process.env.ZOOKI_CAPTURAS_ANTES) {
                    assert.equal(await e("f.querySelectorAll('.consulta-perro-vacio').length"),0);
                    assert.equal(await e("f.querySelectorAll('[data-vital] label i').length"),4);
                    if (ancho === 390) assert.equal(await e("getComputedStyle(f.querySelector('.consulta-vitales')).gridTemplateColumns.split(' ').length"),2);
                    const antes = calculos;
                    await e("f.querySelector('[data-guardar-ficha]').click()");
                    assert.equal(await e("f.querySelector('[data-ficha-error=sexo]').textContent.length>0"), true, 'Sexo sigue siendo obligatorio en la mini ficha');
                    if (a === 'vet_atencion') assert.equal(await e("[...f.querySelectorAll('[data-ficha-campo=sexo]')].every(c=>c.getAttribute('aria-invalid')==='true')"), true, 'El error se aplica al grupo de radios');
                    if (a === 'vet_atencion') await e("f.querySelector('[data-ficha-campo=sexo][value=Hembra]').click();f.querySelector('[data-ficha-campo=esterilizado][value=\"0\"]').click()");
                    else await e("poner('[data-ficha-campo=sexo]','Hembra');poner('[data-ficha-campo=esterilizado]','0')");
                    await e("poner('[data-ficha-campo=fecha_nacimiento]','2022-01-01');f.querySelector('[data-guardar-ficha]').click()");
                    await n.esperar("f.querySelector('[data-ficha-aviso]').textContent.includes('actualizada')");
                    assert.ok(guardados > 0);
                    await n.esperar(`f.querySelector('[data-ficha-mini]').hidden`);
                    await n.esperar("f.querySelector('[data-usar-diagnostico]').closest('li').textContent.includes('actualizado')");
                    assert.ok(calculos > antes,'Vuelve a calcular después de guardar');
                }
                await e("f.querySelector('[data-usar-diagnostico]').click()");
                await n.esperar("f.querySelector('[data-consulta-tres]').dataset.pasoActual==='1'");
                if (a === 'vet_atencion' && !process.env.ZOOKI_CAPTURAS_ANTES) await n.esperar("document.querySelector('#estadoBorrador').textContent.includes('Borrador guardado')");
                await captura(`${a}-${ancho}-diagnostico`);
                if (!process.env.ZOOKI_CAPTURAS_ANTES) {
                    await e("poner('[data-recomendacion]','Recomendación editada');poner('[data-otras-indicaciones]','Texto propio')");
                    const plan = await e("new FormData(f).get('plan_tratamiento')");
                    assert.match(plan,/Exámenes sugeridos: Examen sintético; Segundo examen/);
                    assert.match(plan,/Recomendación editada/);
                    assert.match(plan,/Texto propio/);
                    assert.equal(await e("f.querySelector('[name=plan_tratamiento]').type"),'hidden');
                    await e("f.querySelector('[data-deshacer-sugerencia]').click()");
                    assert.equal(await e("f.querySelector('[name=plan_tratamiento]').value"),'Recomendaciones: Recomendación editada\nTexto propio','Deshacer conserva ediciones');
                    await e("f.querySelector('[data-usar-diagnostico]').click()");
                    await n.esperar("f.querySelector('[name=diagnostico]').value.length>0");
                }
                await e("(document.querySelector('#btnPasoSiguiente')||f.querySelector('[data-consulta-siguiente]')).click()");
                await n.esperar("f.querySelector('[data-consulta-tres]').dataset.pasoActual==='2'");
                await captura(`${a}-${ancho}-cierre`);
                if (!process.env.ZOOKI_CAPTURAS_ANTES && ancho < 768) assert.equal(await e("[...f.querySelectorAll('[data-hoja-pie] button')].filter(b=>!b.hidden).every(b=>{const r=b.getBoundingClientRect();return r.left>=0 && r.right<=innerWidth;})"),true,'Los botones del pie caben completos');
                if (!process.env.ZOOKI_CAPTURAS_ANTES) {
                    assert.equal(await e("[...f.querySelectorAll('img[src*=portal-bienvenida]')].filter(i=>i.getBoundingClientRect().height && getComputedStyle(i).display!=='none').length"),ancho===1366?1:0);
                    assert.equal(await e("f.querySelector('[data-control-fecha]').type"),'text');
                    if (assetsReales) assert.equal(await e("Boolean(f.querySelector('[data-control-fecha]')._flatpickr)"),true,'Usa el selector flatpickr del sistema');
                    assert.match(await e("f.querySelector('[data-control-descripcion]').textContent"),/En 7 días/);
                }
                if (a === 'vet_atencion') {
                    await e("document.querySelector('[data-consulta-ir=\"0\"]').click()");
                    assert.equal(await e("f.querySelector('[data-consulta-tres]').dataset.pasoActual"),'0','Vuelve a un paso completado');
                    for (const tab of ['vacuna','desparasitacion','historial']) {
                        await e(`document.querySelector('[data-tab=${tab}]').closest('details')?.setAttribute('open','');document.querySelector('[data-tab=${tab}]').click()`);
                        if (!process.env.ZOOKI_CAPTURAS_ANTES && tab !== 'historial') {
                            const campo = tab === 'vacuna' ? 'nombre_vacuna' : 'producto';
                            await e(`window.p=document.querySelector('[data-vista=${tab}] form');
                                p.querySelector('[name=${campo}]').value='   ';
                                p.querySelector('[name=${campo}]').dispatchEvent(new Event('input',{bubbles:true}));`);
                            assert.equal(await e(`p.querySelector('[name=${campo}]').getAttribute('aria-invalid')`),'true','Procedimiento valida mientras se escribe');
                            assert.equal(await e("getComputedStyle(p.querySelector('[data-aviso-procedimiento]')).color"),'rgb(185, 28, 28)');
                            await e(`p.querySelector('[name=${campo}]').value='Procedimiento sintético';
                                p.querySelector('[name=${campo}]').dispatchEvent(new Event('input',{bubbles:true}));`);
                            assert.equal(await e(`p.querySelector('[name=${campo}]').getAttribute('aria-invalid')`),'false');
                        }
                        if (!process.env.ZOOKI_CAPTURAS_ANTES) assert.equal(await e("document.querySelector('[data-vista=consulta]').getBoundingClientRect().height"),0,'Consulta se oculta al abrir otra pestaña');
                        await captura(`${a}-${ancho}-${tab}`);
                        if (!process.env.ZOOKI_CAPTURAS_ANTES) assert.equal(await e(`document.querySelector('[data-vista=${tab}]').getBoundingClientRect().height>0`),true);
                    }
                    if (!process.env.ZOOKI_CAPTURAS_ANTES) {
                        assert.equal(await e("document.querySelector('.atencion-franja__datos dl')!==null"),true);
                        if (ancho < 768) await e("document.querySelector('[data-abrir-alertas]').click()");
                        const cta = await e("(()=>{const b=document.querySelector('[data-registrar-alerta]');b.focus();const r=b.getBoundingClientRect();return {x:r.x+r.width/2,y:r.y+r.height/2,foco:document.activeElement===b,borde:getComputedStyle(b).borderStyle};})()");
                        assert.equal(cta.foco,true);
                        assert.equal(cta.borde,'solid');
                        await n.enviar('Input.dispatchMouseEvent',{type:'mouseMoved',x:cta.x,y:cta.y});
                        if (ancho === 1366) assert.equal(await e("getComputedStyle(document.querySelector('[data-registrar-alerta]')).borderTopColor"),'rgb(0, 82, 255)');
                        assert.equal(await e("document.querySelectorAll('[data-alertas-chips] .consulta-chip--alerta:not([hidden])').length"),2);
                        await e("document.querySelector('[data-alertas-mas]').click()");
                        assert.equal(await e("document.querySelectorAll('[data-alertas-chips] .consulta-chip--alerta:not([hidden])').length"),4);
                    }
                }
                assert.equal(await e('document.documentElement.scrollWidth<=innerWidth'),true);
                ficha = {sexo:null,esterilizado:null,fecha_nacimiento:null,identidad_editable:true};
            }
        }
        for (const ancho of (process.env.ZOOKI_CAPTURAS_ANTES ? [] : [390,1366])) {
            await n.enviar('Emulation.setDeviceMetricsOverride',{width:ancho,height:900,deviceScaleFactor:1,mobile:ancho<768});
            falla = true;
            await n.enviar('Page.navigate',{url:`http://127.0.0.1:${servidor.address().port}/index.php?action=vet_atencion`});
            await n.esperar("document.readyState==='complete' && document.querySelector('#estadoBorrador').textContent.includes('No se pudo')");
            assert.equal(await e("Boolean(document.querySelector('[data-contexto-reintentar]'))"),true,'Ofrece Reintentar si falla el contexto');
            assert.equal(await e("getComputedStyle(document.querySelector('[data-contexto-aviso]')).color"), 'rgb(185, 28, 28)', 'La carga fallida se avisa en rojo');
            assert.equal(await e("document.querySelector('#formConsultaAtencion').inert"),false);
            assert.equal(await e("document.querySelector('[data-control-activo]').disabled"),true);
            await n.esperar("!document.querySelector('[data-ficha-mini]').hidden");
            await e(`window.f=document.querySelector('#formConsultaAtencion');
                f.querySelector('[name=motivo]').value='Consulta usable';
                f.querySelector('[data-sin-peso]').click();
                document.querySelector('#btnPasoSiguiente').click();
                f.querySelector('[name=diagnostico]').value='Diagnóstico propio';
                document.querySelector('#btnPasoSiguiente').click();`);
            assert.equal(await e("f.querySelector('[data-consulta-tres]').dataset.pasoActual"),'2','La consulta permite llegar al cierre aunque el contexto falle');
            assert.equal(await e("new FormData(f).has('control_activo')"),false);
            falla = false;
            await e("document.querySelector('[data-contexto-reintentar]').click()");
            await n.esperar("!document.querySelector('[data-control-activo]').disabled");
            assert.equal(await e("document.querySelector('[name=motivo]').value"),'Consulta usable');
            assert.doesNotMatch(await e("document.querySelector('#estadoBorrador').textContent"),/No se pudo/,'El reintento exitoso limpia el aviso del pie');
        }
        if (!process.env.ZOOKI_CAPTURAS_ANTES) {
            const anterior = 'Plan previo';
            const examen = '\nExámenes sugeridos: Examen legado';
            const recomendacion = '\nRecomendaciones: Recomendación legada';
            const legado = { version:3, paso:1, campos:{motivo:'Consulta anterior',peso:'9',diagnostico:'Diagnóstico legado',plan_tratamiento:anterior+examen+recomendacion}, tratamientos:[], sintomas:[], sugerencias:{plan:{examenes:['Examen legado'],recomendaciones:['Recomendación legada']},operaciones:[{codigo:'diagnostico.legado',textos:[{campo:'diagnostico',inicio:0,texto:'Diagnóstico legado',editado:false},{campo:'plan_tratamiento',inicio:anterior.length,texto:examen,editado:false},{campo:'plan_tratamiento',inicio:anterior.length+examen.length,texto:recomendacion,editado:false}],filas:[]}]}};
            await n.enviar('Page.addScriptToEvaluateOnNewDocument',{source:`localStorage.setItem('zooki_atencion_borrador_1_2_5',${JSON.stringify(JSON.stringify(legado))});`});
            await n.enviar('Page.navigate',{url:`http://127.0.0.1:${servidor.address().port}/index.php?action=vet_atencion`});
            await n.esperar("document.readyState==='complete' && !document.querySelector('[data-borrador-aviso]').hidden");
            assert.equal(await e("Boolean(document.querySelector('[data-borrador-aviso]').closest('[data-hoja-pie]'))"), true, 'El borrador recuperado se informa en el pie');
            assert.equal(await e("getComputedStyle(document.querySelector('[data-descartar-borrador]')).borderTopStyle"), 'none', 'Descartar es una acción de texto');
            assert.equal(await e("document.querySelector('[data-otras-indicaciones]').value"),anterior,'Conserva el texto propio del borrador anterior');
            assert.equal(await e("document.querySelector('[data-examenes-elegibles] input').checked"),true,'Recupera la procedencia del examen anterior');
            await e("document.querySelector('[data-deshacer-sugerencia]').click()");
            assert.equal(await e("document.querySelector('[name=plan_tratamiento]').value"),anterior,'Deshacer del borrador anterior conserva el texto propio');
        }
    } finally {
        await n.cerrar();
        await new Promise(r => servidor.close(r));
    }
});
