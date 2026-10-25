const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {execFileSync} = require('node:child_process');
const {abrirChrome, buscarChrome} = require('./apoyo/chrome-cdp.cjs');
const RAIZ = path.join(__dirname, '../..');
const leer = archivo => fs.readFileSync(path.join(RAIZ, archivo), 'utf8');

test('S1 mantiene el pago deshabilitado sin red ni SDK de pasarela', () => {
    const boton = {disabled:false};
    const contexto = {module:{exports:{}}, document:{querySelector:()=>({querySelector:()=>boton})}};
    vm.runInNewContext(leer('public/js/suscripcion.js'), contexto);
    assert.equal(boton.disabled,true);
    assert.doesNotMatch(leer('public/js/suscripcion.js'), /fetch\(|XMLHttpRequest|WidgetCheckout/);
    assert.doesNotMatch(leer('views/admin/suscripcion.php'), /\sstyle=|\son[a-z]+=|<script/i);
    assert.match(leer('views/admin/panel.php'), /class="inicio-rapido" href="index\.php\?action=admin_suscripcion"/);
    assert.match(leer('views/admin/suscripcion.php'), /Disponible pronto/);
});

function vista(escenario) {
    const php = process.env.PHP_BINARY || (process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php');
    return execFileSync(php,['tests/Frontend/apoyo/suscripcion-vista.php',escenario],{cwd:RAIZ,encoding:'utf8',env:{...process.env,ZOOKI_TEST_MYSQL_HOST:'',ZOOKI_TEST_MYSQL_DB:'zooki_test_base_v2'}});
}

function cssLocal(archivo) {
    return fs.readFileSync(archivo,'utf8').replace(/@import\s+url\(['"]?([^'"\)]+)['"]?\);/g,(_,ruta)=>/^https?:/.test(ruta)?'':cssLocal(path.join(path.dirname(archivo),ruta.split('?')[0])));
}

test('Chrome: pantalla real en gracia, congelada y legado a 1366 y 390 sin desborde', {skip:!buscarChrome()}, async () => {
    const nav = await abrirChrome(buscarChrome());
    try {
        await nav.enviar('Network.enable');
        await nav.enviar('Network.setBlockedURLs',{urls:['http://*','https://*']});
        for(const escenario of ['gracia','congelada','legado']) {
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
                assert.equal(estado.pago,true);
                assert.match(estado.texto,escenario==='congelada'?/congelados/:escenario==='gracia'?/gracia/:/Al día/);
                if(process.env.ZOOKI_CAPTURAR_S1==='1' && escenario==='gracia') {
                    const captura=await nav.enviar('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});
                    fs.writeFileSync(path.join(RAIZ,'scratch','s1-suscripcion-'+ancho+'.png'),Buffer.from(captura.data,'base64'));
                }
            }
        }
    } finally { await nav.cerrar(); }
});
