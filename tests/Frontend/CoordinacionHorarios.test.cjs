const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { execFileSync } = require('node:child_process');
const { webcrypto } = require('node:crypto');
const { errorFranjas, enviar } = require('../../public/js/coordinacion-horarios.js');
const { buscarChrome, abrirChrome } = require('./apoyo/chrome-cdp.cjs');
const { adaptarDia } = require('../../public/js/horario-trabajo.js');

const franja = (inicio, fin, dia = 1) => ({ dia_semana: dia, hora_inicio: inicio, hora_fin: fin });

test('Horario compartido: representa turnos contiguos y conserva los originales, incluidos segundos', () => {
    const originales = [franja('08:00:00','10:00:00'),franja('10:00:00','13:00:00'),franja('14:00:00','18:00:00')];
    const especial = adaptarDia(originales);
    assert.equal(especial.especial,true,'Dos turnos discontinuos en la tarde requieren aviso, nunca pérdida de datos');
    assert.deepEqual(especial.originales,originales);
    const continuo = adaptarDia(originales.slice(0,2));
    assert.equal(continuo.especial,false);
    assert.deepEqual(continuo.bloques,{morning:[['08:00','12:00']],afternoon:[['12:00','13:00']]});
    assert.deepEqual(continuo.originales,originales.slice(0,2));
    for(const datos of [[franja('08:00:30','12:00:00')],[franja('05:00:00','08:00:00')],[franja('08:00:00','09:00:00'),franja('10:00:00','11:00:00')]]) {
        const dia = adaptarDia(datos);
        assert.equal(dia.especial,true);
        assert.deepEqual(dia.originales,datos);
    }
    assert.equal(adaptarDia([]).especial,false);
});

for (const rol of ['vet', 'config']) {
    test(`Tabla semanal real en ${rol}: siete días, turnos y horarios especiales y mismo JSON`, { skip: !buscarChrome() }, async () => {
        const php = process.env.PHP_BINARY || (process.platform === 'win32' && fs.existsSync('C:/xampp/php/php.exe') ? 'C:/xampp/php/php.exe' : 'php');
        const vista = execFileSync(php, ['tests/Frontend/apoyo/perfil-vista.php', rol], { encoding: 'utf8', env: { ...process.env, TRUSTED_PROXIES:'127.0.0.2', ZOOKI_TEST_MYSQL_HOST:'', ZOOKI_TEST_MYSQL_DB:'zooki_test_base_v2' } });
        assert.doesNotMatch(vista, /Fatal error|Warning:|\s(?:style|onclick|onsubmit)=/);
        const html = vista.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'').replace(/<link\b[^>]*>/gi,'');
        const css = ['styles.css','dashboard-modules/layout.css','usuarios.css','horarios.css','configuracion-clinica.css','perfil.css','dialogos-zooki.css','coordinacion-horarios.css'].map(p=>`<style>${fs.readFileSync('public/css/'+p,'utf8').replace(/@import[^;]+;/g,'')}</style>`).join('');
        const nav = await abrirChrome(buscarChrome());
        try {
            const arbol = await nav.enviar('Page.getFrameTree');
            await nav.enviar('Page.setDocumentContent',{frameId:arbol.frameTree.frame.id,html:`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">${css}<style>body{margin:0}.prueba{margin-left:112px;padding:24px}@media(max-width:1023px){.prueba{margin-left:80px}}@media(max-width:767px){.prueba{margin-left:0;padding:16px}}</style></head><body><main class="prueba">${html}</main></body></html>`});
            await nav.evaluar(`
                window.__enviadas=[];
                window.__vista={success:true,id_veterinario:2,veterinarios:[{id_usuario:2,nombre_completo:'Beto'}],franjas:[{dia_semana:1,hora_inicio:'08:00:30',hora_fin:'12:00:00'},{dia_semana:1,hora_inicio:'14:00:00',hora_fin:'17:00:00'},{dia_semana:3,hora_inicio:'09:00:00',hora_fin:'10:00:00'}],propuestas:[],cierres:[],afectadas:[]};
                window.fetch=async(url,opciones)=>{
                    if(url.includes('get_horarios_clinica'))return {ok:true,json:async()=>({success:true,horarios:[1,2,3,4,5,6,7].map(d=>({dia_semana:d,activo:d<=5?1:0,bloque_morning_activo:1,bloque_afternoon_activo:1,bloque_morning_inicio:'08:00:00',bloque_morning_fin:'12:00:00',bloque_afternoon_inicio:'14:00:00',bloque_afternoon_fin:'18:00:00'}))})};
                    if(!opciones)return {ok:true,json:async()=>__vista};
                    const franjas=JSON.parse(opciones.body.get('franjas'));
                    __enviadas.push({url,franjas});
                    __vista.franjas=franjas;
                    return {ok:true,json:async()=>({success:true,resultado:{vista:__vista}})};
                };
                window.zookiToast=()=>{};
                window.zookiAviso=mensaje=>{throw Error(mensaje);};
            `);
            await nav.evaluar(fs.readFileSync('public/js/dialogos-zooki.js','utf8'));
            await nav.evaluar(fs.readFileSync('public/js/configuracion-clinica.js','utf8'));
            await nav.evaluar(fs.readFileSync('public/js/time-picker.js','utf8'));
            if(rol==='config') await nav.evaluar(fs.readFileSync('public/js/horarios.js','utf8'));
            await nav.evaluar(fs.readFileSync('public/js/horario-trabajo.js','utf8'));
            await nav.evaluar(fs.readFileSync('public/js/coordinacion-horarios.js','utf8'));
            await nav.esperar(`document.querySelector('[data-franjas] [data-dia-semana="1"] [data-horario-especial]').textContent.includes('conservado')`);
            await nav.esperar(`document.querySelectorAll('[data-franjas] [data-dia-semana]').length===7`);
            await nav.evaluar(`document.querySelector('[data-config-tab="horarios"]').click();`);
            for(const width of [1920,1366,900,390]) {
                await nav.enviar('Emulation.setDeviceMetricsOverride',{width,height:width===1920?1080:width===1366?768:844,deviceScaleFactor:1,mobile:width<768});
                const tabla=await nav.evaluar(`(() => {
                    const raiz=document.querySelector('[data-franjas]');
                    return {dias:[...raiz.querySelectorAll('[data-dia-semana]')].map(d=>d.dataset.diaSemana),cabeceras:raiz.querySelectorAll('.horarios-fila--cabecera').length,pasos:[...raiz.querySelectorAll('input[type="time"]')].every(e=>e.step==='60'),horizontal:document.documentElement.scrollWidth<=innerWidth,entradas:[...raiz.querySelectorAll('input[type="time"]')].every(e=>e.getBoundingClientRect().right<=innerWidth),activo:raiz.querySelector('[data-dia-semana="1"] [data-dia-activo]').checked,inactivo:!raiz.querySelector('[data-dia-semana="2"] [data-dia-activo]').checked};
                })()`);
                assert.deepEqual(tabla.dias,['1','2','3','4','5','6','7']);
                assert.equal(tabla.cabeceras,1);
                assert.equal(tabla.pasos,true);
                assert.equal(tabla.horizontal,true,JSON.stringify({rol,width,tabla}));
                assert.equal(tabla.entradas,true);
                assert.equal(tabla.activo,true);
                assert.equal(tabla.inactivo,true);
                if(rol==='config' && width>=1024) assert.equal(await nav.evaluar('document.documentElement.scrollHeight<=innerHeight'),true,'La agenda larga queda dentro de su contenedor');
                if(process.env.ZOOKI_CAPTURA_PERFIL==='1') {
                    if(rol==='config') await nav.evaluar(`document.querySelector('[data-horario-vet]').closest('section').scrollIntoView({block:'start'});`);
                    const captura=await nav.enviar('Page.captureScreenshot',{format:'png'});
                    fs.writeFileSync(`scratch/semanal-${rol}-${width}.png`,Buffer.from(captura.data,'base64'));
                }
                if(rol==='config') {
                    for(const [seccion,id] of [['clinica','dialogoMarca'],['clinica','dialogoTipo'],['ausencias','dialogoCierre'],['ausencias','dialogoAusencia'],['horarios','modalMultiDia']]) {
                        const ventana=await nav.evaluar(`(() => {
                            document.querySelector('[data-config-tab="${seccion}"]').click();
                            if('${id}'==='modalMultiDia') document.getElementById('btnVariosDias').click();
                            else if('${id}'==='dialogoTipo') document.querySelector('[data-nuevo-tipo]').click();
                            else document.querySelector('[data-abrir-dialogo="${id}"]').click();
                            const dialogo=document.getElementById('${id}');
                            const r=dialogo.getBoundingClientRect();
                            return {abierto:dialogo.open,limites:r.left>=0&&r.right<=innerWidth&&r.top>=0&&r.bottom<=innerHeight,icono:!!dialogo.querySelector('.zk-dialogo__icono'),pie:getComputedStyle(dialogo.querySelector('.zk-dialogo__pie')).backgroundColor};
                        })()`);
                        assert.deepEqual(ventana,{abierto:true,limites:true,icono:true,pie:'rgb(255, 255, 255)'},JSON.stringify({width,id,ventana}));
                        if(process.env.ZOOKI_CAPTURA_PERFIL==='1') {
                            const captura=await nav.enviar('Page.captureScreenshot',{format:'png'});
                            fs.writeFileSync(`scratch/config-${width}-${id}.png`,Buffer.from(captura.data,'base64'));
                        }
                        if(id==='modalMultiDia') {
                            const selector=await nav.evaluar(`(() => {
                                const dialogo=document.getElementById('modalMultiDia');
                                dialogo.querySelector('.block-picker-display').click();
                                const abierto=!!dialogo.querySelector('.tp-open');
                                dialogo.querySelector('.tp-open').dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true,cancelable:true}));
                                return {abierto,ventana:dialogo.open,cerrado:!dialogo.querySelector('.tp-open')};
                            })()`);
                            assert.deepEqual(selector,{abierto:true,ventana:true,cerrado:true});
                        }
                        await nav.evaluar(`document.getElementById('${id}').close();`);
                    }
                    await nav.evaluar(`document.querySelector('[data-config-tab="horarios"]').click();`);
                }
            }
            await nav.evaluar(`
                const raiz=document.querySelector('[data-coordinacion-horarios]');
                const lunes=raiz.querySelector('[data-dia-semana="1"]');
                if(!lunes.querySelector('[data-dia-activo]').disabled) throw Error('El horario especial no se debe sobrescribir');
                raiz.querySelector('[data-dia-semana="3"] [data-dia-activo]').click();
                const martes=raiz.querySelector('[data-dia-semana="2"]');
                martes.querySelector('[data-dia-activo]').click();
                martes.querySelector('.morning-bloque-activo').click();
                martes.querySelector('.morning-inicio').value='09:00';
                martes.querySelector('.morning-fin').value='10:00';
            document.querySelector('[data-franjas] [data-franja-campo="hora_fin"]').dispatchEvent(new Event('change',{bubbles:true}));
                martes.querySelector('.morning-fin').dispatchEvent(new Event('change',{bubbles:true}));
                raiz.querySelector('[data-horario-vet]').dispatchEvent(new Event('submit',{cancelable:true}));
            `);
            await nav.esperar(`__enviadas.length===1&&!document.querySelector('[data-horario-vet] button[type="submit"]').disabled`);
            const envio=await nav.evaluar('__enviadas[0]');
            assert.match(envio.url,rol==='vet'?/proponer_horario_vet_ajax/:/guardar_horario_vet_ajax/);
            assert.deepEqual(envio.franjas,[
                {dia_semana:1,hora_inicio:'08:00:30',hora_fin:'12:00:00'},
                {dia_semana:1,hora_inicio:'14:00:00',hora_fin:'17:00:00'},
                {dia_semana:'2',hora_inicio:'09:00:00',hora_fin:'10:00:00'},
            ]);
            await nav.evaluar(`document.querySelector('[data-horario-varios]').click();`);
            const varios=await nav.evaluar(`(() => {
                const dialogo=document.querySelector('dialog.horario-varios[open]');
                dialogo.querySelector('.md-day-check[value="1"]').checked=true;
                dialogo.querySelector('[id$="btnAplicarMultiDia"]').click();
                const aviso=dialogo.querySelector('[id$="mdError"]').textContent;
                dialogo.querySelector('.md-day-check[value="1"]').checked=false;
                dialogo.querySelector('.md-day-check[value="4"]').checked=true;
                dialogo.querySelector('[id$="btnAplicarMultiDia"]').click();
                return {aviso,cerrado:!dialogo.open,jueves:document.querySelector('[data-franjas] [data-dia-semana="4"] [data-dia-activo]').checked};
            })()`);
            assert.match(varios.aviso,/especial se conserva/);
            assert.equal(varios.cerrado,true);
            assert.equal(varios.jueves,true);
            assert.deepEqual(nav.errores,[]);
        } finally {
            await nav.cerrar();
        }
    });
}

test('C2 valida días, horas y extremos antes de enviar', () => {
    for (const f of [franja('09:00','09:00'), franja('10:00','09:00'), franja('24:00','25:00'), franja('09:00','10:00',8), franja('','10:00')]) {
        assert.notEqual(errorFranjas([f]),'');
    }
    assert.equal(errorFranjas([franja('08:00','10:00'), franja('10:00','12:00')]),'');
    assert.equal(errorFranjas([]),'');
});

test('C2 detecta solapes aunque las filas estén desordenadas y conserva días diferentes', () => {
    assert.match(errorFranjas([franja('10:00','12:00'),franja('09:00','10:01')]),/solaparse/);
    assert.equal(errorFranjas([franja('10:00','12:00',2),franja('09:00','11:00',1)]),'');
});

test('C2 comparte el guardado: doble envío bloqueado y respuesta canónica antes del aviso', async () => {
    const estado = { enviando: false };
    const pasos = [];
    let terminar;
    let llamadas = 0;
    const dependencias = {
        bloquear: b => pasos.push(b ? 'bloqueado' : 'libre'),
        fetch: async () => {
            llamadas++;
            await new Promise(resolve => { terminar = resolve; });
            return { ok: true, json: async () => ({ success: true, resultado: { vista: { afectadas: [{ id_cita: 3 }] } } }) };
        },
        aviso: () => pasos.push('aviso'),
        error: () => assert.fail()
    };
    const primera = enviar(estado,{},'aprobar_horario_vet_ajax', respuesta => {
        assert.equal(respuesta.resultado.vista.afectadas[0].id_cita,3);
        pasos.push('pintar');
    },dependencias);
    assert.equal(await enviar(estado,{},'aprobar_horario_vet_ajax',()=>assert.fail(),dependencias),false);
    terminar();
    assert.equal(await primera,true);
    assert.equal(llamadas,1);
    assert.deepEqual(pasos,['bloqueado','pintar','aviso','libre']);
});

test('C2 conserva el error del campo y rehabilita el formulario sin éxito aparente', async () => {
    const estado = { enviando: false };
    let campo;
    const bloqueos = [];
    assert.equal(await enviar(estado,{},'guardar_horario_vet_ajax',()=>assert.fail(),{
        fetch: async () => ({ ok: false, json: async () => ({ success: false, campo: 'franjas', aviso: 'Otro compromiso' }) }),
        bloquear: b => bloqueos.push(b),
        aviso: () => assert.fail(),
        error: error => { campo = error.campo; }
    }),false);
    assert.equal(campo,'franjas');
    assert.deepEqual(bloqueos,[true,false]);
});

test('C2 el interceptor conserva clave del mismo cuerpo y mantiene CSRF independiente', async () => {
    const enviadas = [];
    const entorno = { FormData, URLSearchParams, Uint8Array, window: { crypto: webcrypto, fetch: async (url, opciones) => { enviadas.push(opciones.body); } },
        document: { querySelector: () => ({ getAttribute: () => 'token-prueba' }) } };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../../public/js/csrf.js'),'utf8'),entorno);
    const cuerpo = new FormData();
    await entorno.window.fetch('index.php?action=registrar_cita_ajax',{ method: 'POST', body: cuerpo });
    const clave = cuerpo.get('clave_operacion');
    assert.match(clave,/^[a-f0-9]{64}$/);
    await entorno.window.fetch('index.php?action=registrar_cita_ajax',{ method: 'POST', body: cuerpo });
    assert.equal(cuerpo.get('clave_operacion'),clave);
    assert.equal(cuerpo.get('csrf_token'),'token-prueba');
    const otro = new FormData();
    await entorno.window.fetch('index.php?action=registrar_cita_ajax',{ method: 'POST', body: otro });
    assert.notEqual(otro.get('clave_operacion'),clave);
    const ajeno = new FormData();
    await entorno.window.fetch('index.php?action=actualizar_perfil_ajax',{ method: 'POST', body: ajeno });
    assert.equal(ajeno.has('clave_operacion'),false);
});

test('C2 integra formularios sin código en línea y pinta el resultado durable directamente', () => {
    const raiz = path.join(__dirname,'../..');
    const vista = fs.readFileSync(path.join(raiz,'views/partials/coordinacion_horarios.php'),'utf8');
    const codigo = fs.readFileSync(path.join(raiz,'public/js/coordinacion-horarios.js'),'utf8');
    assert.doesNotMatch(vista,/\s(?:onclick|style)=|<script(?![^>]*src=)[^>]*>/i);
    assert.match(vista,/maxlength="255"/);
    assert.match(codigo,/lista.addEventListener\('input', validar\)/);
    assert.match(codigo,/pintar\(canonico.vista\)/);
    assert.match(codigo,/version !== lectura/);
    assert.match(codigo,/comandos.clear\(\)/);
    assert.match(codigo,/motivo.value.trim\(\)/);
    assert.doesNotMatch(codigo,/confirm\(|alert\(|prompt\(/);
});

test('C2 Chrome: formulario real bloquea lecturas/doble envío y conserva error, clave y respuesta canónica', { skip: buscarChrome() ? false : 'Sin Chrome para el DOM automático' }, async () => {
    const navegador = await abrirChrome(buscarChrome());
    try {
        const arbol = await navegador.enviar('Page.getFrameTree');
        await navegador.enviar('Page.setDocumentContent',{ frameId: arbol.frameTree.frame.id, html: (() => {
            const php=process.env.PHP_BINARY || (process.platform==='win32'?'C:/xampp/php/php.exe':'php');
            return execFileSync(php,['tests/Frontend/apoyo/perfil-vista.php','config'],{encoding:'utf8',env:{...process.env,TRUSTED_PROXIES:'127.0.0.2'}}).replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'').replace(/<link\b[^>]*>/gi,'');
        })() });
        await navegador.esperar("document.readyState === 'complete'");
        await navegador.evaluar(`window.__lecturas=[]; window.__escrituras=[]; window.__avisos=[];
            window.__vista={success:true,id_clinica:1,id_veterinario:2,veterinarios:[{id_usuario:2,nombre_completo:'Beto'}],
              franjas:[{dia_semana:1,hora_inicio:'10:00:00',hora_fin:'12:00:00'}],propuestas:[],cierres:[],afectadas:[]};
            window.fetch=(url,opciones)=>new Promise(resolve=>{
              (opciones ? __escrituras : __lecturas).push({resolve,body:opciones?.body});
            });
            window.zookiToast=()=>__avisos.push(document.querySelector('[data-afectadas]').textContent);`);
        await navegador.evaluar(fs.readFileSync(path.join(__dirname,'../../public/js/configuracion-clinica.js'),'utf8'));
        await navegador.evaluar(fs.readFileSync('public/js/time-picker.js','utf8'));
        await navegador.evaluar(fs.readFileSync('public/js/horario-trabajo.js','utf8'));
        await navegador.evaluar(fs.readFileSync(path.join(__dirname,'../../public/js/coordinacion-horarios.js'),'utf8'));
        await navegador.esperar('__lecturas.length === 1');
        assert.equal(await navegador.evaluar("document.querySelector('[data-horario-vet] button').disabled"),true);
        await navegador.evaluar("document.querySelector('[data-horario-vet]').dispatchEvent(new Event('submit',{cancelable:true}))");
        assert.equal(await navegador.evaluar('__escrituras.length'),0);
        await navegador.evaluar('__lecturas[0].resolve({ok:true,json:async()=>__vista})');
        await navegador.esperar("document.querySelectorAll('[data-franjas] [data-franja-campo]').length===28 && !document.querySelector('[data-horario-vet] button').disabled");
        await navegador.evaluar(`document.querySelector('[data-franjas] [data-franja-campo="hora_fin"]').value='10:00';
            document.querySelector('[data-franjas] [data-franja-campo="hora_fin"]').dispatchEvent(new Event('change',{bubbles:true}));
            document.querySelector('[data-horario-vet]').dispatchEvent(new Event('submit',{cancelable:true}));`);
        assert.equal(await navegador.evaluar('__escrituras.length'),0);
        assert.match(await navegador.evaluar("document.querySelector('[data-error-campo=" + '"franjas"' + "]').textContent"),/posterior/);
        await navegador.evaluar(`document.querySelector('[data-franjas] [data-franja-campo="hora_fin"]').value='12:00';
            document.querySelector('[data-franjas] [data-franja-campo="hora_fin"]').dispatchEvent(new Event('change',{bubbles:true}));
            const f=document.querySelector('[data-horario-vet]');
            f.dispatchEvent(new Event('submit',{cancelable:true}));
            f.dispatchEvent(new Event('submit',{cancelable:true}));`);
        assert.equal(await navegador.evaluar('__escrituras.length'),1);
        await navegador.evaluar("__escrituras[0].resolve({ok:false,json:async()=>({success:false,campo:'franjas',aviso:'Otro compromiso'})})");
        await navegador.esperar("!document.querySelector('[data-horario-vet] button').disabled");
        assert.equal(await navegador.evaluar("document.querySelector('[data-error-campo=" + '"franjas"' + "]').textContent"),'Otro compromiso');
        await navegador.evaluar("document.querySelector('[data-horario-vet]').dispatchEvent(new Event('submit',{cancelable:true}))");
        assert.equal(await navegador.evaluar("__escrituras[0].body.get('clave_operacion')===__escrituras[1].body.get('clave_operacion')"),true);
        await navegador.evaluar(`__vista.afectadas=[{id_cita:7,fecha:'2030-01-07',hora:'09:00',estado_reajuste:'pendiente_calculo',motivo_reajuste:'Turno'}];
            __escrituras[1].resolve({ok:true,json:async()=>({success:true,resultado:{vista:__vista}})});`);
        await navegador.esperar('__avisos.length===1');
        assert.match(await navegador.evaluar('__avisos[0]'),/Cita 7.*Pendiente de cálculo/);
        assert.deepEqual(navegador.errores,[]);
    } finally {
        await navegador.cerrar();
    }
});
