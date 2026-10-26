const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const codigo = fs.readFileSync('public/js/fuente-suscripcion.js','utf8');

function pantalla(fetch,extras={}) {
    const nodos = {};
    for (const nombre of ['fuente-preparar','fuente-guardar','automatico-autorizar','automatico-retirar','fuente-quitar','nequi-comprobar','acepta-politica','acepta-datos','acepta-mensual',
        'fuente-mensaje','fuente-validacion','fuente-condiciones','contrato-politica','contrato-datos','fuente-resumen','fuente-estado','automatico-estado','importe-mensual','mandato-importe']) {
        nodos[nombre] = {checked:false,disabled:false,hidden:false,textContent:'',addEventListener(){}};
    }
    const contexto = {module:{exports:{}},URLSearchParams,document:{querySelector:s=>s.startsWith('meta')?{content:'csrf-falso'}:null}};
    vm.runInNewContext(codigo,contexto);
    const raiz = {dataset:{automaticoHabilitado:'1'},querySelector:s=>nodos[s.slice(6,-1)]};
    const llamadas = [];
    const ui = contexto.module.exports.iniciar(raiz,{fetch:async(url,opciones)=>{
        llamadas.push([url,opciones]);
        const resultado = await fetch(url,opciones);
        return {ok:true,json:async()=>({ok:true,resultado})};
    },...extras});
    return {ui,nodos,llamadas};
}

const preparada = fuente => ({nonce:'opaca',importe:8999000,publicKey:'pub_test_falsa',fuente,contratos:{
    acceptance_token:{enlace:'https://wompi.co/condiciones.pdf'},accept_personal_auth:{enlace:'https://wompi.co/datos.pdf'}}});
const fuente = {tipo:'CARD',marca:'VISA',ultimos_cuatro:'4242',estado:'disponible',cobro_automatico:0};
function aceptar(p) {
    p.nodos['acepta-politica'].checked = true;
    p.nodos['acepta-datos'].checked = true;
    p.ui.validar();
}

test('aceptaciones sin premarcar, importe visible y guardar nunca autoriza',async()=>{
    let finalizar;
    const p = pantalla(async(url,opciones)=>{
        if(url.includes('preparar')) return preparada(null);
        assert.match(opciones.body.toString(),/csrf_token=csrf-falso/);
        assert.doesNotMatch(opciones.body.toString(),/acepta_mensual|autorizar/);
        return {fuente,mensaje:'Guardado',pendiente:false};
    },{widget:datos=>({open:callback=>{
        assert.equal(datos.widgetOperation,'tokenize');
        assert.equal(datos.publicKey,'pub_test_falsa');
        finalizar=callback;
    }}),toast:()=>{
        assert.match(p.nodos['fuente-resumen'].textContent,/4242/);
        assert.equal(p.nodos['automatico-estado'].textContent,'Cobro automático desactivado.');
    }});
    await p.ui.preparar();
    assert.equal(p.nodos['acepta-politica'].checked,false);
    assert.equal(p.nodos['acepta-datos'].checked,false);
    assert.equal(p.nodos['acepta-mensual'].checked,false);
    assert.match(p.nodos['mandato-importe'].textContent,/89.990/);
    assert.equal(p.nodos['fuente-guardar'].disabled,true);
    aceptar(p);
    await p.ui.guardar();
    finalizar({payment_source:{token:'tok_test_efimero',type:'CARD'}});
    await new Promise(r=>setImmediate(r));
    assert.equal(p.llamadas.length,2);
    assert.equal(p.nodos['automatico-autorizar'].disabled,true);
    assert.doesNotMatch(fs.readFileSync('views/admin/suscripcion.php','utf8'),/\sstyle=|\son[a-z]+=|<script/);
    assert.doesNotMatch(codigo,/localStorage|sessionStorage|innerHTML|\.token\s*\+|console\.log/);
});

test('Nequi pendiente se comprueba sin darlo por disponible y errores no autorizan',async()=>{
    let intentos=0;
    const p = pantalla(async url=>url.includes('preparar')?preparada(null):{
        pendiente:++intentos===1,fuente:intentos===1?null:{...fuente,tipo:'NEQUI'},mensaje:intentos===1?'Aprueba en tu celular':'Medio guardado'
    },{toast(){}});
    await p.ui.preparar();
    aceptar(p);
    const medio={type:'NEQUI',token:'nequi_test_efimero'};
    await p.ui.guardarToken(medio);
    assert.equal(p.nodos['nequi-comprobar'].hidden,false);
    assert.match(p.nodos['fuente-mensaje'].textContent,/celular/);
    assert.equal(p.nodos['automatico-autorizar'].disabled,true);
    await p.ui.guardarToken(medio);
    assert.equal(p.nodos['nequi-comprobar'].hidden,true);
    assert.equal(p.nodos['fuente-resumen'].textContent,'Nequi');
    const err = pantalla(async()=>{throw new Error('Sin conexión');});
    await err.ui.preparar();
    assert.match(err.nodos['fuente-mensaje'].textContent,/Sin conexión/);
    assert.equal(err.nodos['fuente-preparar'].disabled,false);
});

test('mandato separado, retiro directo y solo quitar pide confirmación Zooki',async()=>{
    let confirmaciones=0;
    const p = pantalla(async(url,opciones)=>{
        if(url.includes('preparar')) return preparada(fuente);
        if(url.includes('automatico')) {
            const si=opciones.body.get('autorizar')==='1';
            if(si) assert.equal(opciones.body.get('acepta_mensual'),'1');
            return {fuente:{...fuente,cobro_automatico:si?1:0},mensaje:si?'Autorizado':'Retirado'};
        }
        return {fuente:null,mensaje:'Quitado'};
    },{confirmar:async()=>{confirmaciones++;return true;},toast(){}});
    await p.ui.preparar();
    aceptar(p);
    await p.ui.automatico(true);
    assert.equal(p.llamadas.length,1);
    p.nodos['acepta-mensual'].checked=true;
    await p.ui.automatico(true);
    assert.equal(p.nodos['automatico-estado'].textContent,'Cobro mensual autorizado.');
    await p.ui.automatico(false);
    assert.equal(confirmaciones,0);
    await p.ui.quitar();
    assert.equal(confirmaciones,1);
    assert.equal(p.nodos['fuente-quitar'].hidden,true);
    assert.equal(p.nodos['automatico-retirar'].hidden,true);
});

test('dos clics de preparación solo hacen una petición y botones se restauran',async()=>{
    let resolver;
    const p = pantalla(()=>new Promise(r=>resolver=r));
    const primero=p.ui.preparar();
    const segundo=p.ui.preparar();
    assert.equal(p.nodos['fuente-preparar'].disabled,true);
    assert.equal(p.llamadas.length,1);
    resolver(preparada(null));
    await Promise.all([primero,segundo]);
    assert.equal(p.nodos['fuente-preparar'].disabled,false);
});
