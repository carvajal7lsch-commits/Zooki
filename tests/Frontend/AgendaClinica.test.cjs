const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { errores } = require('../../public/js/ausencias-agenda.js');
const { errorCampo } = require('../../public/js/urgencias-agenda.js');
const { buscarChrome, abrirChrome } = require('./apoyo/chrome-cdp.cjs');
const reglas = { nombreMax:100,nombreCaracteres:"\\p{L}\\p{M} '’\\-",telefonoMin:7,telefonoMax:20,mascotaMax:50 };

test('C6 valida rango exacto, motivo y extremos antes de escribir', () => {
    assert.deepEqual(errores({ inicio:'2030-01-07T00:00',fin:'2030-01-08T00:00',motivo:'' }),{});
    assert.ok(errores({ inicio:'2030-01-07T10:00:00',fin:'2030-01-07T10:00:00' }).fin);
    assert.ok(errores({ inicio:'2030-01-07T10:00:01',fin:'2030-01-07T10:00:00' }).fin);
    assert.ok(errores({ inicio:'ayer',fin:'mañana' }).inicio);
    assert.ok(errores({ inicio:'2030-01-07T10:00',fin:'2030-01-07T11:00',motivo:'a'.repeat(256) }).motivo);
});

test('C7 contacto opcional no acredita titularidad; validación coincide con límites compartidos', () => {
    for (const campo of ['nombre_acompanante','telefono_acompanante','nombre_mascota']) assert.equal(errorCampo(campo,'',false,reglas),'');
    assert.equal(errorCampo('nombre_acompanante','María Pérez',false,reglas),'');
    assert.notEqual(errorCampo('nombre_acompanante','A2',false,reglas),'');
    assert.notEqual(errorCampo('telefono_acompanante','123',false,reglas),'');
    assert.equal(errorCampo('telefono_acompanante','+57 3001234567',false,reglas),'');
    assert.notEqual(errorCampo('motivo','',true,reglas),'');
    assert.notEqual(errorCampo('peso','0',true,reglas),'');
    assert.notEqual(errorCampo('evidencia','x'.repeat(256),true,reglas),'');
});

async function pagina(rol, ejecutar) {
    const php = process.env.PHP_BINARY || (process.platform === 'win32' && fs.existsSync('C:\\xampp\\php\\php.exe') ? 'C:\\xampp\\php\\php.exe' : 'php');
    const html = execFileSync(php,[path.join(__dirname,'apoyo/agenda-clinica-vistas.php'),rol],{ encoding:'utf8',env:{...process.env,TRUSTED_PROXIES:'127.0.0.2',ZOOKI_TEST_MYSQL_HOST:'',ZOOKI_TEST_MYSQL_DB:'zooki_test_base_v2'} });
    assert.doesNotMatch(html,/Fatal error|Warning:/);
    assert.doesNotMatch(html,/\s(?:style|onclick)=|<script(?![^>]*src=)[^>]*>/i);
    const navegador = await abrirChrome(buscarChrome());
    try {
        const arbol = await navegador.enviar('Page.getFrameTree');
        await navegador.enviar('Page.setDocumentContent',{frameId:arbol.frameTree.frame.id,html:`<!doctype html><html><body>${html}</body></html>`});
        await navegador.esperar("document.readyState === 'complete'");
        await navegador.evaluar(`window.__lecturas=[];window.__escrituras=[];
          window.__catalogos={mascotas:[{id_mascota:1,id_propietario:6,nombre:'Luna'}],propietarios:[{id_usuario:6,nombre_completo:'Fabio'}],tipos:[{id_tipo_cita:1,nombre_tipo:'Consulta'}],especies:[{id_especie:1,nombre_especie:'Canino'}],razas:[{id_raza:49,id_especie:1,nombre_raza:'Sin raza definida'}]};
          window.fetch=(url,opciones)=>new Promise(resolve=>{
            (opciones ? __escrituras : __lecturas).push({url,resolve,body:opciones?.body});
          });`);
        for (const archivo of ['comando-agenda','configuracion-clinica','urgencias-agenda','ausencias-agenda']) await navegador.evaluar(fs.readFileSync(path.join(__dirname,`../../public/js/${archivo}.js`),'utf8'));
        await navegador.esperar('__lecturas.length >= 2');
        await navegador.evaluar(`for(const q of __lecturas) q.resolve({ok:true,json:async()=>q.url.includes('listar_urgencias')?{success:true,catalogos:__catalogos,rojos:[],ingresos:[]}:q.url.includes('coordinacion')?{success:true,veterinarios:[{id_usuario:2,nombre_completo:'Beto'}]}:{success:true,ausencias:[]}});`);
        await ejecutar(navegador);
        assert.deepEqual(navegador.errores,[]);
    } finally {
        await navegador.cerrar();
    }
}

const chrome = { skip:buscarChrome() ? false : 'Sin Chrome para el DOM aislado' };
test('C7 DOM real: provisional, campos opcionales, restricción, doble envío y replay tras respuesta perdida',chrome,async () => {
    await pagina('vet',async n => {
        await n.esperar("document.querySelector('[name=id_mascota]').options.length===2");
        await n.evaluar(`var f=document.querySelector('[data-ingreso-rojo]');
            f.elements.identidad.value='provisional';f.elements.identidad.dispatchEvent(new Event('change',{bubbles:true}));
            f.elements.motivo.value='Rojo clínico';f.elements.motivo.dispatchEvent(new Event('input',{bubbles:true}));
            f.dispatchEvent(new Event('submit',{cancelable:true}));f.dispatchEvent(new Event('submit',{cancelable:true}));`);
        assert.equal(await n.evaluar('__escrituras.length'),1);
        assert.equal(await n.evaluar("__escrituras[0].body.has('id_mascota')"),false);
        assert.equal(await n.evaluar("__escrituras[0].body.get('nombre_acompanante')"),'');
        await n.evaluar("__escrituras[0].resolve({ok:false,json:async()=>({success:false,message:'Respuesta perdida'})})");
        await n.esperar("!document.querySelector('[data-ingreso-rojo] [type=submit]').disabled");
        await n.evaluar("document.querySelector('[data-ingreso-rojo]').dispatchEvent(new Event('submit',{cancelable:true}))");
        assert.equal(await n.evaluar("__escrituras[0].body.get('clave_operacion')===__escrituras[1].body.get('clave_operacion')"),true);
        await n.evaluar("__escrituras[1].resolve({ok:true,json:async()=>({success:true,message:'Llegada registrada'})})");
        await n.esperar('__lecturas.length>=3');
        await n.evaluar(`__lecturas.at(-1).resolve({ok:true,json:async()=>({success:true,catalogos:__catalogos,rojos:[{id_solicitud:3,id_mascota:9,nombre:'<img src=x onerror=error()>',fecha_llegada:'2030-01-07 09:00:00',estado:'espera_presencial'}],ingresos:[]})})`);
        await n.esperar("document.querySelector('[data-rojos-lista] h3') !== null");
        assert.equal(await n.evaluar("document.querySelector('[data-rojos-lista] img')===null"),true);
        assert.match(await n.evaluar("document.querySelector('[data-urgencias-aviso]').textContent"),/registrada/);
    });
});

test('C6 DOM real: validación en vivo y denegación de doble envío, actualización inmediata',chrome,async () => {
    await pagina('admin',async n => {
        assert.equal(await n.evaluar("document.querySelector('[data-ingreso-rojo]')===null"),true);
        await n.esperar("document.querySelector('[data-form-ausencia] select').options.length===1");
        await n.evaluar(`var f=document.querySelector('[data-form-ausencia]');f.elements.inicio.value='2030-01-07T09:00:00';f.elements.fin.value='2030-01-07T09:00:00';f.dispatchEvent(new Event('input',{bubbles:true}));`);
        assert.equal(await n.evaluar("document.querySelector('[data-form-ausencia] [type=submit]').disabled"),true);
        await n.evaluar(`var f=document.querySelector('[data-form-ausencia]');f.elements.fin.value='2030-01-07T10:00:00';f.dispatchEvent(new Event('input',{bubbles:true}));f.dispatchEvent(new Event('submit',{cancelable:true}));f.dispatchEvent(new Event('submit',{cancelable:true}));`);
        assert.equal(await n.evaluar('__escrituras.length'),1);
        await n.evaluar("__escrituras[0].resolve({ok:true,json:async()=>({success:true,message:'Ausencia efectiva'})})");
        await n.esperar("document.querySelector('[data-form-ausencia] [data-aviso]').textContent==='Ausencia efectiva'");
        await n.evaluar(`__lecturas.at(-1).resolve({ok:true,json:async()=>({success:true,ausencias:[{id:7,veterinario:'Beto',fecha_hora_inicio:'2030-01-07 09:00:00',fecha_hora_fin:'2030-01-07 10:00:00',estado:'aprobada'}]})})`);
        await n.esperar("document.querySelector('[data-lista-ausencias]').textContent.toLowerCase().includes('aprobada')");
        assert.equal(await n.evaluar("document.querySelector('[data-lista-ausencias] .coord-estado').dataset.estado"),'aprobada');
    });
});
