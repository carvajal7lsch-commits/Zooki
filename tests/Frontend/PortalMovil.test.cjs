const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');
const { servidorPortalMovil } = require('./apoyo/portal-movil-servidor.cjs');
const pintar = nav => nav.evaluar('new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)))');
async function entrar(nav, url, width) {
    await nav.enviar('Emulation.setDeviceMetricsOverride', { width, height:width<768?844:width===820?1180:768, deviceScaleFactor:1, mobile:width<768 });
    await nav.enviar('Page.navigate', { url });
    await nav.esperar("document.readyState==='complete' && typeof switchTab==='function' && Boolean(document.querySelector('#portal_contact_phone').zookiTelefono)");
    await nav.evaluar("window.Swal={mixin:()=>({fire:async()=>({})}),fire:async()=>({isConfirmed:true})}");
    await pintar(nav);
}
async function conPortal(ejecutar, opciones={}) {
    const servidor = await servidorPortalMovil(opciones);
    const nav = await abrirChrome(buscarChrome());
    try {
        await ejecutar(nav, servidor);
        assert.deepEqual(nav.errores, []);
    } finally {
        await nav.cerrar();
        await servidor.cerrar();
    }
}
const pulsar = (nav, selector) => nav.evaluar('document.querySelector('+JSON.stringify(selector)+').click()');
const escribir = (nav, selector, valor) => nav.evaluar(`(()=>{const e=document.querySelector(${JSON.stringify(selector)});e.value=${JSON.stringify(valor)};e.dispatchEvent(new Event('input',{bubbles:true}));e.dispatchEvent(new Event('change',{bubbles:true}))})()`);
const dentro = (nav, width, caso) => nav.evaluar('({ancho:innerWidth,documento:document.documentElement.scrollWidth})').then(m => assert.deepEqual(m, {ancho:width,documento:width},caso));

test('RNF-16: portal360/390 con6 mascotas,5 citas y4 clínicas, sin desborde y bienvenida mini', { skip:!buscarChrome() }, async()=>{
    await conPortal(async(nav,servidor)=>{
        for(const width of [360,390]) {
            await entrar(nav,servidor.url,width);
            assert.equal(await nav.evaluar("document.querySelectorAll('.mascota-mini').length"),6);
            await dentro(nav,width,'Inicio');
            const saludo = await nav.evaluar(`(()=>{
                const banner=document.querySelector('.home-bienvenida');
                const figura=banner.querySelector('.home-bienvenida__perro');
                const img=figura.querySelector('img');
                const canvas=document.createElement('canvas');
                canvas.width=img.naturalWidth;
                canvas.height=img.naturalHeight;
                const ctx=canvas.getContext('2d');
                ctx.drawImage(img,0,0);
                const pix=ctx.getImageData(0,0,canvas.width,canvas.height).data;
                let ultimo=0;
                for(let y=0;y<canvas.height;y++) for(let x=0;x<canvas.width;x++) if(pix[(y*canvas.width+x)*4+3]>=16) ultimo=y+1;
                const r=img.getBoundingClientRect();
                const h=document.getElementById('homeSaludo');
                return {alto:banner.getBoundingClientRect().height,visible:getComputedStyle(figura).display!=='none',cruz:getComputedStyle(banner.querySelector('.home-bienvenida__cruz')).display,patas:r.top+ultimo/canvas.height*r.height-figura.getBoundingClientRect().bottom,cabe:h.scrollWidth<=h.clientWidth&&h.getBoundingClientRect().bottom<=banner.getBoundingClientRect().bottom,carrusel:document.querySelector('.mascotas-carrusel').scrollWidth>document.querySelector('.mascotas-carrusel').clientWidth};
            })()`);
            assert.equal(saludo.visible,true);
            assert.equal(saludo.cruz,'none');
            assert.ok(saludo.alto<=96&&saludo.cabe,JSON.stringify(saludo));
            assert.ok(Math.abs(saludo.patas)<=2,JSON.stringify(saludo));
            assert.equal(saludo.carrusel,true,'El carrusel se desplaza dentro');
            await nav.evaluar("switchTab('citas',{historial:false})");
            assert.equal(await nav.evaluar("document.querySelectorAll('#citas-proximas .portal-cita').length"),2);
            assert.equal(await nav.evaluar("document.querySelectorAll('#citas-pasadas .portal-cita').length"),3);
            for(const panel of ['citas-proximas','citas-pasadas','agenda-salud']) {
                await pulsar(nav,'[data-pestana="'+panel+'"]');
                await dentro(nav,width,'Citas/'+panel);
                assert.equal(await nav.evaluar("[...document.querySelectorAll('#screen-citas .portal-pestanas button')].every(e=>e.getBoundingClientRect().height<=44&&getComputedStyle(e).whiteSpace==='nowrap')"),true);
                assert.equal(await nav.evaluar("[...document.querySelectorAll('.portal-cita')].filter(e=>e.getBoundingClientRect().height).every(e=>getComputedStyle(e.querySelector('.portal-cita__cuando>span:not(.portal-cita__estado)')).whiteSpace==='nowrap'&&e.querySelector('.portal-cita__estado').parentElement.classList.contains('portal-cita__cuando'))"),true);
            }
            assert.equal(await nav.evaluar("document.querySelector('[data-pestana=agenda-salud]').getAttribute('aria-label')"),'Vacunas y controles');
            for(const pantalla of ['clinicas','account']) {
                await nav.evaluar(`switchTab('${pantalla}',{historial:false})`);
                await dentro(nav,width,pantalla);
            }
            await nav.evaluar('verDetalle(1)');
            await dentro(nav,width,'Mascota');
            // La respuesta real conserva el dato que traiga; este valor prueba su formato, sin cambiar la API.
            await nav.evaluar("renderSummary({...window.activePetData,numero_historia_clinica:'HC-000001'})");
            assert.equal(await nav.evaluar("(()=>{const e=document.querySelector('.portal-summary-item strong');return e.textContent==='HC-000001'&&getComputedStyle(e).whiteSpace==='nowrap'&&e.scrollWidth<=e.clientWidth})()"),true);
            assert.equal(await nav.evaluar("getComputedStyle(document.querySelector('.portal-summary-grid')).gridTemplateColumns.split(' ').length"),2);
        }
    });
});

test('Clínicas: ayuda en hoja, tres filas compactas, llamar y nombres de otras clínicas completos', { skip:!buscarChrome() }, async()=>{
    await conPortal(async(nav,servidor)=>{
        for(const width of [360,390]) {
            await entrar(nav,servidor.url,width);
            await nav.evaluar("switchTab('clinicas',{historial:false})");
            await pulsar(nav,'[data-portal-hoja=portalClinicasAyudaModal]');
            assert.equal(await nav.evaluar("document.querySelector('#portalClinicasAyudaModal').classList.contains('is-open')"),true);
            assert.match(await nav.evaluar("document.querySelector('#portalClinicasAyudaModal .portal-drawer-body').textContent"),/las consultas, tratamientos y archivos/);
            await pulsar(nav,'#portalClinicasAyudaCerrar');
            const clinicas=await nav.evaluar(`(()=>{
                const filas=[...document.querySelectorAll('#misClinicas [data-id-clinica]')];
                return {vinculadas:filas.length,otras:document.querySelectorAll('[data-otras-clinicas] .clinica-fila').length,compactas:filas.every(e=>{const a=e.querySelector('h3').getBoundingClientRect(),b=e.querySelector('.clinica-fila__detalle').getBoundingClientRect(),c=e.querySelector('.clinica-fila__interruptor').getBoundingClientRect(),pie=e.querySelector('.clinica-fila__accion').getBoundingClientRect();return a.bottom<=b.top+2&&b.bottom<=c.top+2&&c.bottom<=pie.top+2}),nombres:[...document.querySelectorAll('[data-otras-clinicas] h3')].every(e=>e.scrollWidth<=e.clientWidth&&getComputedStyle(e).textOverflow==='clip'),llamar:filas.every(e=>e.querySelector('a[href^="tel:"]').getBoundingClientRect().width===36)};
            })()`);
            assert.deepEqual(clinicas,{vinculadas:2,otras:2,compactas:true,nombres:true,llamar:true});
            await nav.evaluar('refrescarPortal()');
            assert.equal(await nav.evaluar("document.querySelectorAll('#ayudaHistoriaCompartida').length"),1,'La ayuda conserva un solo id tras repintar');
            await pulsar(nav,'[data-portal-hoja=portalClinicasAyudaModal]');
            assert.match(await nav.evaluar("document.querySelector('#portalClinicasAyudaModal').textContent"),/las consultas, tratamientos y archivos/);
            await pulsar(nav,'#portalClinicasAyudaCerrar');
        }
    });
});

test('Perfil: hojas originales, validación al escribir/guardar, teléfono con bandera y cambio de perfil condicional', { skip:!buscarChrome() }, async()=>{
    await conPortal(async(nav,servidor)=>{
        await entrar(nav,servidor.url,390);
        await nav.evaluar("switchTab('account',{historial:false});window.__telefono=document.getElementById('portal_contact_phone');window.__instancia=__telefono.zookiTelefono;true");
        assert.equal(await nav.evaluar("document.querySelectorAll('.portal-perfil-lista [data-portal-hoja]').length"),4);
        assert.equal(await nav.evaluar("Boolean(document.querySelector('.portal-perfil-lista a[href*=seleccionar_contexto]'))"),true);
        for(const [modal,selector,valor,accion] of [
            ['portalDocumentoModal','#identidadDocumentoNumero','1080361992','cambiar_documento_ajax'],
            ['portalCorreoModal','#identidadCorreoEmail','nuevo@zooki.test','solicitar_cambio_correo_ajax'],
        ]) {
            await pulsar(nav,'[data-portal-hoja='+modal+']');
            assert.equal(await nav.evaluar(`document.getElementById('${modal}').classList.contains('is-open')`),true);
            await escribir(nav,selector,modal==='portalCorreoModal'?'incorrecto':'1');
            assert.equal(await nav.evaluar(`document.querySelector(${JSON.stringify(selector)}).checkValidity()`),false);
            await escribir(nav,selector,valor);
            await escribir(nav,'#'+modal+' [name=password_actual]','Clave#Prueba2026');
            await nav.evaluar(`document.querySelector('#${modal} form').requestSubmit()`);
            await nav.esperar(`!document.getElementById('${modal}').classList.contains('is-open')`);
            assert.ok(servidor.llamadas.some(l=>l.accion===accion&&l.metodo==='POST'),accion);
        }
        await pulsar(nav,'[data-portal-hoja=portalTelefonoModal]');
        assert.equal(await nav.evaluar("Boolean(__telefono.closest('#portalTelefonoModal'))&&__telefono.zookiTelefono===__instancia"),true);
        assert.equal(await nav.evaluar("getComputedStyle(__telefono).fontSize"),'16px');
        await escribir(nav,'#portal_contact_phone','1');
        assert.equal(await nav.evaluar('__telefono.checkValidity()'),false);
        await nav.evaluar("document.getElementById('portalContactEditForm').requestSubmit()");
        await pintar(nav);
        assert.equal(servidor.llamadas.filter(l=>l.accion==='portal_actualizar_datos_contacto_ajax').length,0);
        await escribir(nav,'#portal_contact_phone','+573001234567');
        await nav.evaluar("document.getElementById('btnSubmitContactEdit').click()");
        await nav.esperar("!document.getElementById('portalTelefonoModal').classList.contains('is-open')");
        assert.ok(servidor.llamadas.some(l=>l.accion==='portal_actualizar_datos_contacto_ajax'&&l.cuerpo.includes('573001234567')));
        assert.equal(await nav.evaluar("document.querySelector('[data-portal-telefono]').textContent"),'+573001234567');
        await pulsar(nav,'[data-portal-hoja=portalPasswordModal]');
        await escribir(nav,'#portal_current_password','Clave#Prueba2026');
        await escribir(nav,'#portal_new_password','Nueva#Prueba2026');
        await escribir(nav,'#portal_confirm_password','Nueva#Prueba2026');
        await nav.evaluar("document.getElementById('portalChangePasswordForm').requestSubmit()");
        await nav.esperar("!document.getElementById('portalPasswordModal').classList.contains('is-open')");
        assert.ok(servidor.llamadas.some(l=>l.accion==='cambiar_password_ajax'));
        await nav.enviar('Emulation.setDeviceMetricsOverride',{width:1366,height:768,deviceScaleFactor:1,mobile:false});
        await pintar(nav);
        assert.equal(await nav.evaluar("Boolean(__telefono.closest('.perfil-tarjeta--contacto'))&&__telefono.zookiTelefono===__instancia&&document.getElementById('btnSubmitContactEdit').form.id==='portalContactEditForm'"),true,'Restauración sin duplicar formulario ni instancia');
    });
    await conPortal(async(nav,servidor)=>{
        await entrar(nav,servidor.url,390);
        assert.equal(await nav.evaluar("document.querySelector('.portal-perfil-lista a[href*=seleccionar_contexto]')===null"),true);
    },{uno:true});
});

test('Reprogramar: campos del portal, flatpickr con cierres y mismo POST en todos los anchos; Agendar compacto', { skip:!buscarChrome() }, async()=>{
    await conPortal(async(nav,servidor)=>{
        for(const width of [360,390,820,1366]) {
            await entrar(nav,servidor.url,width);
            await pulsar(nav,'#screen-home [data-reprogramar-cita]');
            await nav.esperar("document.querySelector('#portalReprogramarModal [name=hora]').options.length>1");
            const calendario=await nav.evaluar(`(()=>{
                const form=document.getElementById('portalReprogramarForm');
                const fp=form.elements.fecha._flatpickr;
                // El fixture general abre todos los días; la misma configuración admite domingo cerrado.
                form.dataset.diasCerrados=JSON.stringify({1:[7],2:[]});
                fp.redraw();
                const domingo=new Date();
                domingo.setDate(domingo.getDate()+7+(7-domingo.getDay())%7);
                return {campos:form.querySelectorAll('.input-group .search-input-wrapper').length,real:Boolean(fp),nativo:form.elements.fecha.type,domingo:fp.isEnabled(domingo),vet:form.elements.id_veterinario.value,nombre:form.elements.id_veterinario.selectedOptions[0].textContent,pie:Boolean(form.querySelector('.portal-modal__pie button'))};
            })()`);
            assert.deepEqual(calendario,{campos:3,real:true,nativo:'hidden',domingo:false,vet:'2',nombre:'Beto Norte',pie:true});
            await nav.evaluar("(()=>{const fp=document.getElementById('reprogramarFecha')._flatpickr;const dia=new Date();do{dia.setDate(dia.getDate()+1)}while(!fp.isEnabled(dia));fp.setDate(dia,true)})()");
            await nav.esperar("document.querySelector('#portalReprogramarForm [name=hora]').options.length>1");
            await escribir(nav,'#reprogramarHora','09:30:00');
            await nav.evaluar("document.getElementById('portalReprogramarForm').requestSubmit()");
            await nav.esperar("!document.getElementById('portalReprogramarModal').classList.contains('is-open')");
            assert.ok(servidor.llamadas.some(l=>l.accion==='reprogramar_cita_ajax'&&l.cuerpo.includes('clave_operacion')));
            await pulsar(nav,'[data-agendar]');
            await pintar(nav);
            if(width<768) assert.equal(await nav.evaluar("[...document.querySelectorAll('#portalBookingForm .portal-opcion')].every(e=>e.getBoundingClientRect().height<=64)"),true,'Opciones compactas de mascota y clínica');
            await pulsar(nav,'#btnCloseBookingModal');
        }
    });
});

// Solo posición y ancho: la altura depende de la fuente instalada (Windows frente a CI sin Inter) y no indica un cambio de distribución.
const geometria=m=>m&&({vista:m.vista,ancho:m.ancho,documento:m.documento,pantalla:{x:m.pantalla.x,ancho:m.pantalla.ancho},piezas:m.piezas.map(p=>({clase:p.clase,x:p.x,ancho:p.ancho}))});

test('Portal a820/1366: medidas de Inicio, Citas, Clínicas y Perfil conservadas', { skip:!buscarChrome() }, async()=>{
    const esperado=JSON.parse(fs.readFileSync('tests/Frontend/apoyo/portal-medidas-escritorio.json','utf8'));
    await conPortal(async(nav,servidor)=>{
        for(const width of [820,1366]) {
            await entrar(nav,servidor.url,width);
            for(const vista of ['home','citas','clinicas','account']) {
                await nav.evaluar(`switchTab('${vista}',{historial:false})`);
                await pintar(nav);
                const actual=await nav.evaluar(`(()=>{const pantalla=document.querySelector('.app-screen.active');const c=pantalla.getBoundingClientRect();return {vista:'${vista}',ancho:innerWidth,documento:document.documentElement.scrollWidth,pantalla:{x:c.x,y:c.y,ancho:c.width,alto:c.height},piezas:[...pantalla.querySelectorAll('.portal-cabecera,.home-bienvenida,.portal-cita,.perfil-tarjeta,.clinica-fila,.mascota-mini')].map(e=>{const r=e.getBoundingClientRect();return {clase:e.className,x:r.x,y:r.y,ancho:r.width,alto:r.height}})}})()`);
                assert.deepEqual(geometria(actual),geometria(esperado.find(m=>m.ancho===width&&m.vista===vista)),width+'/'+vista);
            }
        }
    });
});


test('Cita con reajuste: conserva la explicación completa sin partir la hora ni desbordar', { skip:!buscarChrome() }, async()=>{
    await conPortal(async(nav,servidor)=>{
        for(const width of [360,390]) {
            await entrar(nav,servidor.url,width);
            await dentro(nav,width,'Inicio con reajuste');
            await nav.evaluar("switchTab('citas',{historial:false})");
            await dentro(nav,width,'Citas con reajuste');
            assert.equal(await nav.evaluar("[...document.querySelectorAll('.portal-cita__cuando > span')].filter(e=>e.textContent.startsWith('Contacta')).every(e=>getComputedStyle(e).whiteSpace==='normal'&&e.scrollWidth<=e.clientWidth)"),true);
            assert.equal(await nav.evaluar("getComputedStyle(document.querySelector('.portal-cita__hora')).whiteSpace"),'nowrap');
        }
    },{reajuste:true});
});
