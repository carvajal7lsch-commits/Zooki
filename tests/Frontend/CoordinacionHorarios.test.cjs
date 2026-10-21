const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { webcrypto } = require('node:crypto');
const { errorFranjas, enviar } = require('../../public/js/coordinacion-horarios.js');
const { buscarChrome, abrirChrome } = require('./apoyo/chrome-cdp.cjs');

const franja = (inicio, fin, dia = 1) => ({ dia_semana: dia, hora_inicio: inicio, hora_fin: fin });

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
        await navegador.enviar('Page.setDocumentContent',{ frameId: arbol.frameTree.frame.id, html: `<!doctype html><html><body>
            <section data-coordinacion-horarios data-admin="1" data-clinica="1">
              <form data-horario-vet><input name="csrf_token" value="prueba"><select name="id_veterinario" data-veterinario></select>
                <div data-franjas></div><span data-error-campo="franjas"></span><button type="submit">Guardar</button><p data-aviso></p>
              </form><button type="button" data-agregar-franja>Agregar</button>
              <div data-propuestas></div><div data-cierres></div><div data-afectadas></div>
            </section></body></html>` });
        await navegador.esperar("document.readyState === 'complete'");
        await navegador.evaluar(`window.__lecturas=[]; window.__escrituras=[]; window.__avisos=[];
            window.__vista={success:true,id_clinica:1,id_veterinario:2,veterinarios:[{id_usuario:2,nombre_completo:'Beto'}],
              franjas:[{dia_semana:1,hora_inicio:'10:00:00',hora_fin:'12:00:00'}],propuestas:[],cierres:[],afectadas:[]};
            window.fetch=(url,opciones)=>new Promise(resolve=>{
              (opciones ? __escrituras : __lecturas).push({resolve,body:opciones?.body});
            });
            window.zookiToast=()=>__avisos.push(document.querySelector('[data-afectadas]').textContent);`);
        await navegador.evaluar(fs.readFileSync(path.join(__dirname,'../../public/js/configuracion-clinica.js'),'utf8'));
        await navegador.evaluar(fs.readFileSync(path.join(__dirname,'../../public/js/coordinacion-horarios.js'),'utf8'));
        await navegador.esperar('__lecturas.length === 1');
        assert.equal(await navegador.evaluar("document.querySelector('[data-horario-vet] button').disabled"),true);
        await navegador.evaluar("document.querySelector('[data-horario-vet]').dispatchEvent(new Event('submit',{cancelable:true}))");
        assert.equal(await navegador.evaluar('__escrituras.length'),0);
        await navegador.evaluar('__lecturas[0].resolve({ok:true,json:async()=>__vista})');
        await navegador.esperar("document.querySelectorAll('[data-franja-campo]').length===3 && !document.querySelector('[data-horario-vet] button').disabled");
        await navegador.evaluar(`document.querySelector('[data-franja-campo="hora_fin"]').value='10:00';
            document.querySelector('[data-horario-vet]').dispatchEvent(new Event('submit',{cancelable:true}));`);
        assert.equal(await navegador.evaluar('__escrituras.length'),0);
        assert.match(await navegador.evaluar("document.querySelector('[data-error-campo=" + '"franjas"' + "]').textContent"),/posterior/);
        await navegador.evaluar(`document.querySelector('[data-franja-campo="hora_fin"]').value='12:00';
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
