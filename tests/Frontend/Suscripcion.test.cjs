const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {execFileSync} = require('node:child_process');
const {abrirChrome, buscarChrome} = require('./apoyo/chrome-cdp.cjs');
const RAIZ = path.join(__dirname, '../..');
const leer = archivo => fs.readFileSync(path.join(RAIZ, archivo), 'utf8');

test('S2 deshabilitada no reserva, sin estilos ni scripts en línea', () => {
    const boton = {disabled:false,addEventListener(){}};
    const raiz = {dataset:{pagoHabilitado:'0'},querySelector:s=>s==='.suscripcion__pagar'?boton:null};
    const contexto = {module:{exports:{}}, document:{querySelector:()=>raiz}};
    vm.runInNewContext(leer('public/js/suscripcion.js'), contexto);
    assert.equal(boton.disabled,true);
    assert.doesNotMatch(leer('views/admin/suscripcion.php'), /\sstyle=|\son[a-z]+=|<script/i);
    assert.match(leer('views/admin/panel.php'), /class="inicio-rapido" href="index\.php\?action=admin_suscripcion"/);
    assert.match(leer('views/admin/layout.php'), /src="https:\/\/checkout.wompi.co\/widget.js"/);
});

function interfaz(servicios) {
    const contexto={module:{exports:{}},URLSearchParams,document:{querySelector:()=>({content:'csrf-falso'})}};
    // Inicialización automática sin pantalla en este documento de prueba.
    contexto.document.querySelector=s=>s==='meta[name="csrf-token"]'?{content:'csrf-falso'}:null;
    vm.runInNewContext(leer('public/js/suscripcion.js'),contexto);
    const boton={disabled:false,addEventListener(){}};
    const mensaje={textContent:''};
    const raiz={dataset:{pagoHabilitado:'1',version:'0'},querySelector:s=>s==='.suscripcion__pagar'?boton:s==='#suscripcionMensaje'?mensaje:s.startsWith('input')?{value:'mensual'}:null};
    return {ui:contexto.module.exports.iniciar(raiz,servicios),boton,mensaje};
}

test('primer clic deshabilita, doble clic no repite y cierre solo consulta estado local',async()=>{
    let resolver;
    let llamadas=0;
    let cerrar;
    const servicios={fetch:(url,opciones)=>{
        llamadas++;
        if(url.includes('pagar')) {
            assert.equal(pantalla.boton.disabled,true);
            assert.match(opciones.body.toString(),/csrf_token=csrf-falso/);
            return new Promise(r=>resolver=r);
        }
        return Promise.resolve({ok:true,json:async()=>({ok:true,estado:{pago:{estado:'pendiente',intento:'pendiente'}}})});
    },widget:datos=>({open:callback=>{
        assert.equal(datos.reference,'ref_falsa');
        cerrar=callback;
    }})};
    const pantalla=interfaz(servicios);
    const primero=pantalla.ui.pagar();
    const segundo=pantalla.ui.pagar();
    assert.equal(llamadas,1);
    assert.equal(pantalla.boton.disabled,true);
    resolver({ok:true,json:async()=>({ok:true,pago:{abrir:true,mensaje:'Completa',checkout:{reference:'ref_falsa'}}})});
    await Promise.all([primero,segundo]);
    assert.equal(pantalla.boton.disabled,false);
    cerrar(undefined);
    await new Promise(r=>setImmediate(r));
    assert.equal(llamadas,2);
    assert.match(pantalla.mensaje.textContent,/pendiente/);
});

test('error de reserva o SDK restaura botón y muestra mensaje sin acreditar',async()=>{
    for(const fetchFalso of [async()=>{throw new Error('Sin conexión');},async()=>({ok:false,json:async()=>({ok:false,mensaje:'No disponible'})}),async()=>({ok:true,json:async()=>({ok:true,pago:{abrir:true,checkout:{},mensaje:'Abrir'}})})]) {
        const pantalla=interfaz({fetch:fetchFalso,widget:()=>{throw new Error('SDK no disponible');}});
        await pantalla.ui.pagar();
        assert.equal(pantalla.boton.disabled,false);
        assert.match(pantalla.mensaje.textContent,/Sin conexión|No disponible|SDK no disponible/);
    }
});

function vista(escenario) {
    const php = process.env.PHP_BINARY || (process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php');
    return execFileSync(php,['tests/Frontend/apoyo/suscripcion-vista.php',escenario],{cwd:RAIZ,encoding:'utf8',env:{...process.env,ZOOKI_TEST_MYSQL_HOST:'',ZOOKI_TEST_MYSQL_DB:'zooki_test_base_v2'}});
}

function cssLocal(archivo) {
    return fs.readFileSync(archivo,'utf8').replace(/@import\s+url\(['"]?([^'"\)]+)['"]?\);/g,(_,ruta)=>/^https?:/.test(ruta)?'':cssLocal(path.join(path.dirname(archivo),ruta.split('?')[0])));
}

test('actualizar estado muestra la fecha incluida del servidor sin medianoche ni jerga',async()=>{
    const fin={textContent:''};
    const mensaje={textContent:''};
    const contexto={module:{exports:{}},URLSearchParams,document:{querySelector:()=>null}};
    vm.runInNewContext(leer('public/js/suscripcion.js'),contexto);
    const raiz={dataset:{pagoHabilitado:'0'},querySelector:s=>s==='[data-suscripcion-fin]'?fin:s==='#suscripcionMensaje'?mensaje:null};
    const ui=contexto.module.exports.iniciar(raiz,{fetch:async()=>({ok:true,json:async()=>({ok:true,estado:{plan:'Profesional',situacion:'gracia',fin:'2026-10-25 00:00:00',fin_incluido:'24/10/2026'}})})});
    await ui.estado();
    assert.equal(fin.textContent,'24/10/2026');
    assert.doesNotMatch(fin.textContent,/00:00/);
    assert.match(mensaje.textContent,/En gracia/);
    assert.doesNotMatch(mensaje.textContent,/al_dia|confirmación local/);
});

test('Chrome: pantalla real activa, deshabilitada e historial a 1366 y 390 sin red', {skip:!buscarChrome()}, async () => {
    const nav = await abrirChrome(buscarChrome());
    try {
        await nav.enviar('Network.enable');
        await nav.enviar('Network.setBlockedURLs',{urls:['http://*','https://*']});
        for(const escenario of ['gracia','congelada','legado','activa','historial','fuente','automatico','nequi']) {
            let html = vista(escenario);
            assert.doesNotMatch(html,/Fatal error|Warning:|Notice:/);
            assert.match(html,/Clínica Norte &lt;prueba&gt;/);
            const estilos=[...html.matchAll(/<link[^>]+href="(css\/[^"?]+)[^"]*"[^>]*>/g)].map(m=>'<style>'+cssLocal(path.join(RAIZ,'public',m[1]))+'</style>').join('\n');
            html=html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'').replace(/<link\b[^>]*>/gi,'').replace(/<img\b[^>]*>/gi,'').replace('</head>',estilos+'<style>#global-loader{display:none}*{animation:none!important;transition:none!important}</style></head>');
            for(const ancho of [1366,390]) {
                await nav.enviar('Emulation.setDeviceMetricsOverride',{width:ancho,height:844,deviceScaleFactor:1,mobile:ancho<768});
                const arbol=await nav.enviar('Page.getFrameTree');
                await nav.enviar('Page.setDocumentContent',{frameId:arbol.frameTree.frame.id,html});
                const estado=await nav.evaluar(`({cabe:document.documentElement.scrollWidth<=innerWidth,pago:document.querySelector('.suscripcion__pagar').disabled,texto:document.querySelector('[data-suscripcion-estado]').textContent})`);
                assert.equal(estado.cabe,true,JSON.stringify({escenario,ancho,estado}));
                assert.equal(estado.pago,!['activa','historial','fuente','automatico','nequi'].includes(escenario));
                assert.match(estado.texto,escenario==='congelada'?/congelados/:['gracia','activa','fuente','automatico','nequi'].includes(escenario)?/gracia/:/Al día/);
                if(['fuente','automatico'].includes(escenario)) assert.match(html,/VISA · termina en 4242/);
                if(escenario==='automatico') assert.match(html,/Cobro mensual autorizado/);
                if(escenario==='nequi') assert.match(html,/>Nequi</);
                if(escenario==='historial') assert.match(html,/confirmado/);
                if(process.env.ZOOKI_CAPTURAR_S2==='1' && ['gracia','activa','historial'].includes(escenario)) {
                    const captura=await nav.enviar('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});
                    fs.writeFileSync(path.join(RAIZ,'scratch','s2-suscripcion-'+escenario+'-'+ancho+'.png'),Buffer.from(captura.data,'base64'));
                }
                if(process.env.ZOOKI_CAPTURAR_S3==='1' && ['activa','fuente','automatico','nequi'].includes(escenario)) {
                    const metricas=await nav.enviar('Page.getLayoutMetrics');
                    const captura=await nav.enviar('Page.captureScreenshot',{format:'png',captureBeyondViewport:true,clip:{x:0,y:0,width:ancho,height:metricas.cssContentSize.height,scale:1}});
                    fs.writeFileSync(path.join(RAIZ,'scratch','s3-suscripcion-'+escenario+'-'+ancho+'.png'),Buffer.from(captura.data,'base64'));
                }
            }
        }
    } finally { await nav.cerrar(); }
});
