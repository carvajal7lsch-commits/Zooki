const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { servidorInicioMovil } = require('./apoyo/inicio-admin-movil-servidor.cjs');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');

async function conInicio(ejecutar) {
    const servidor = await servidorInicioMovil();
    const nav = await abrirChrome(buscarChrome());
    try {
        await nav.enviar('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
        await nav.enviar('Page.navigate',{url:servidor.url});
        await nav.esperar("document.readyState==='complete'&&document.querySelectorAll('[data-inicio-semana-fecha]').length===7");
        await ejecutar(nav,servidor);
        assert.deepEqual(nav.errores,[]);
    } finally {
        await nav.cerrar();
        await servidor.cerrar();
    }
}
async function capturar(nav,nombre) {
    if(process.env.ZOOKI_CAPTURAR_INICIO_MOVIL!=='1')return;
    const carpeta='scratch/r/inicio-admin-movil/despues';
    fs.mkdirSync(carpeta,{recursive:true});
    const foto=await nav.enviar('Page.captureScreenshot',{format:'png'});
    fs.writeFileSync(carpeta+'/'+nombre+'.png',Buffer.from(foto.data,'base64'));
}
const elegir = (nav,clave) => nav.evaluar(`document.querySelector('[data-inicio-tab="${clave}"]').click();true`);

test('R Inicio admin: pestañas, recuerdo, vet elegido, enlaces y anchos conservados',{skip:!buscarChrome(),timeout:45000},async()=> {
    await conInicio(async(nav,servidor)=> {
        for(const [width,height]of [[390,844],[360,800]]) {
            await nav.enviar('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:true});
            assert.equal(await nav.evaluar('document.documentElement.scrollWidth<=innerWidth'),true,'Sin desborde a '+width);
            assert.equal(await nav.evaluar("getComputedStyle(document.querySelector('.inicio-encabezado__saludo')).fontSize"),'20px');
            assert.equal(await nav.evaluar("[...document.querySelectorAll('.inicio-clinica__cifra')].every(n=>Math.abs(n.getBoundingClientRect().top-document.querySelector('.inicio-clinica__cifra').getBoundingClientRect().top)<1)"),true,'Cuatro números en una fila');
            assert.equal(await nav.evaluar("document.querySelectorAll('[data-inicio-veterinario]:not([hidden])').length"),1);
            await elegir(nav,'pendientes');
            assert.equal(await nav.evaluar("document.querySelector('[data-inicio-panel=agenda]').hidden"),true);
            assert.equal(await nav.evaluar("document.querySelector('[data-inicio-panel=pendientes]').hidden"),false);
            const destinos=await nav.evaluar("[...document.querySelectorAll('.inicio-pendientes a')].map(n=>n.getAttribute('href'))");
            assert.ok(destinos.includes('index.php?action=admin_citas'));
            assert.ok(destinos.includes('index.php?action=admin_configuracion#horarios'));
            assert.ok(destinos.includes('index.php?action=admin_configuracion#ausencias'));
            if(width===390)await capturar(nav,'390-pendientes');
            await elegir(nav,'mes');
            assert.equal(await nav.evaluar("document.querySelector('[data-inicio-panel=mes]').contains(document.querySelector('.inicio-plan'))"),true);
            const accesos=await nav.evaluar("[...document.querySelectorAll('.inicio-plan__accesos a')].map(n=>n.getAttribute('href'))");
            assert.deepEqual(accesos,['index.php?action=admin_usuarios','index.php?action=admin_configuracion&seccion=suscripcion&desde=inicio#suscripcion']);
            assert.equal(await nav.evaluar("getComputedStyle(document.querySelector('.inicio-mes__datos')).gridTemplateColumns.split(' ').length"),3);
            if(width===390)await capturar(nav,'390-mes');
            await nav.evaluar("window.scrollTo(0,document.documentElement.scrollHeight);true");
            assert.equal(await nav.evaluar("document.querySelector('.inicio-plan__accesos a:last-child').getBoundingClientRect().bottom<=document.querySelector('.pill-sidebar').getBoundingClientRect().top+1"),true,'La navegación no tapa el último enlace');
            assert.equal(await nav.evaluar("document.querySelector('.top-header').getBoundingClientRect().top>=0"),true,'Cabecera fija al desplazar');
            await nav.evaluar("window.scrollTo(0,0);true");
            await elegir(nav,'agenda');
            if(width===390)await capturar(nav,'390-agenda');
        }
        await nav.evaluar("document.querySelector('[data-inicio-elegir-vet=\"5\"]').click();true");
        assert.deepEqual(await nav.evaluar("[...document.querySelectorAll('[data-inicio-veterinario]:not([hidden])')].map(n=>n.dataset.inicioVeterinario)"),['5']);
        await elegir(nav,'pendientes');
        await nav.enviar('Page.reload');
        await nav.esperar("document.readyState==='complete'&&document.querySelector('[data-inicio-tab=pendientes]').getAttribute('aria-selected')==='true'");
        assert.equal(await nav.evaluar("document.querySelector('[data-inicio-panel=pendientes]').hidden"),false);
        await nav.evaluar("Object.defineProperty(Storage.prototype,'setItem',{value(){throw new Error('Bloqueado para la prueba');}});document.querySelector('[data-inicio-tab=mes]').click();true");
        assert.equal(await nav.evaluar('location.hash'),'#inicio-mes','El hash recuerda la pestaña aunque se bloquee sessionStorage');
        for(const [width,height]of [[820,1180],[1366,768]]) {
            await nav.enviar('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:false});
            await nav.enviar('Page.navigate',{url:servidor.url});
            await nav.esperar("document.readyState==='complete'&&!!window.ZookiInicio");
            assert.equal(await nav.evaluar('document.documentElement.scrollWidth<=innerWidth'),true);
            assert.equal(await nav.evaluar("document.querySelectorAll('[data-inicio-veterinario][hidden]').length"),0);
            if(width===1366)assert.equal(await nav.evaluar('document.documentElement.scrollHeight<=innerHeight+1'),true,'Escritorio sin scroll de página');
            const medidas=await nav.evaluar("Object.fromEntries(['.top-header','.inicio-clinica','.inicio-cal','.inicio-plan','.inicio-dia','.inicio-pendientes','.inicio-mes'].map(s=>{const r=document.querySelector(s).getBoundingClientRect();return [s,{x:r.x,y:r.y,width:r.width,height:r.height}];}))");
            const anterior='scratch/r/inicio-admin-movil/antes/'+width+'.json';
            if(fs.existsSync(anterior))assert.deepEqual(medidas,JSON.parse(fs.readFileSync(anterior,'utf8')),'Mismas medidas a '+width);
            if(width===1366)await capturar(nav,'1366');
        }
        await nav.enviar('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
        await nav.enviar('Page.navigate',{url:servidor.url+'/?caso=uno#inicio-agenda'});
        await nav.esperar("document.readyState==='complete'&&document.querySelectorAll('[data-inicio-semana-fecha]').length===7");
        assert.equal(await nav.evaluar("document.querySelector('[data-inicio-selector-vet]').hidden"),true);
        assert.equal(await nav.evaluar("document.querySelectorAll('[data-inicio-veterinario]:not([hidden])').length"),1);
    });
});

test('R Inicio admin: semana, cruce de mes, hoja y respuesta vieja no ganadora',{skip:!buscarChrome(),timeout:45000},async()=> {
    await conInicio(async(nav,servidor)=> {
        assert.deepEqual(await nav.evaluar("[...document.querySelectorAll('[data-inicio-semana-fecha]')].filter(n=>n.querySelector('.inicio-semana__punto')).map(n=>n.dataset.inicioSemanaFecha)"),['2026-10-22','2026-10-23']);
        assert.equal(await nav.evaluar("document.querySelector('[data-inicio-semana-fecha][aria-pressed=true]').dataset.inicioSemanaFecha"),'2026-10-23');
        await nav.evaluar("document.querySelector('[data-inicio-semana-paso=\"1\"]').click();true");
        await nav.esperar("!!document.querySelector('[data-inicio-semana-fecha=\"2026-11-01\"]')&&document.querySelector('[data-inicio-dia]').getAttribute('aria-busy')==='false'");
        assert.ok(servidor.llamadas.some(url=>url.includes('mes=2026-11')),'Usa la petición mensual existente al cruzar de mes');
        assert.equal(await nav.evaluar("document.querySelector('[data-inicio-semana-fecha=\"2026-10-26\"] .inicio-semana__punto')!==null"),true);
        await nav.evaluar("document.querySelector('[data-inicio-semana-paso=\"-1\"]').click();true");
        await nav.esperar("!!document.querySelector('[data-inicio-semana-fecha=\"2026-10-23\"][aria-pressed=true]')");
        await nav.evaluar("document.querySelector('[data-inicio-calendario-abrir]').click();true");
        assert.equal(await nav.evaluar("document.getElementById('inicioMovilCalendario').open"),true);
        await capturar(nav,'390-calendario');
        await nav.evaluar("document.querySelector('#inicioMovilCalendario [data-inicio-mes=\"2026-11\"]').click();true");
        await nav.esperar("document.querySelector('[data-inicio-mes-actual]').dataset.inicioMesActual==='2026-11'");
        await nav.evaluar("document.querySelector('#inicioMovilCalendario [data-inicio-fecha=\"2026-11-02\"]').click();true");
        await nav.esperar("!document.getElementById('inicioMovilCalendario').open&&document.querySelector('[data-inicio-dia]').getAttribute('aria-busy')==='false'");
        assert.ok(servidor.llamadas.some(url=>url.includes('fecha=2026-11-02')));
        await nav.enviar('Page.navigate',{url:servidor.url+'/?reinicio=1#inicio-agenda'});
        await nav.esperar("document.readyState==='complete'&&document.querySelectorAll('[data-inicio-semana-fecha]').length===7");
        servidor.retener(true);
        await nav.evaluar("document.querySelector('[data-inicio-semana-fecha=\"2026-10-22\"]').click();document.querySelector('[data-inicio-semana-fecha=\"2026-10-23\"]').click();true");
        for(let intento=0;intento<100&&servidor.pendientes.length<2;intento++)await new Promise(r=>setTimeout(r,20));
        assert.equal(servidor.pendientes.length,2);
        servidor.pendientes[1]();
        await nav.esperar("document.querySelector('[data-inicio-dia]').getAttribute('aria-busy')==='false'");
        servidor.pendientes[0]();
        await nav.evaluar("new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)))");
        assert.equal(await nav.evaluar("document.getElementById('inicioTituloDia').textContent"),'Agenda de hoy por veterinario');
        assert.equal(await nav.evaluar("document.querySelector('[data-inicio-semana-fecha][aria-pressed=true]').dataset.inicioSemanaFecha"),'2026-10-23');
        assert.equal(await nav.evaluar('document.documentElement.scrollWidth<=innerWidth'),true);
    });
});
