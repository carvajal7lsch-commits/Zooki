const test = require('node:test');
const assert = require('node:assert/strict');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');
const { servidorPortalMovil } = require('./apoyo/portal-movil-servidor.cjs');
const pintar = nav => nav.evaluar('new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)))');
async function entrar(nav, url, width) {
    await nav.enviar('Emulation.setDeviceMetricsOverride', { width, height:width<768?844:768, deviceScaleFactor:1, mobile:width<768 });
    await nav.enviar('Page.navigate', { url });
    await nav.esperar("document.readyState==='complete' && typeof verDetalle==='function' && Boolean(document.querySelector('#portal_contact_phone').zookiTelefono)");
    await pintar(nav);
}
async function conPortal(ejecutar) {
    const servidor = await servidorPortalMovil();
    const nav = await abrirChrome(buscarChrome());
    try {
        await ejecutar(nav, servidor);
        assert.deepEqual(nav.errores, []);
    } finally {
        await nav.cerrar();
        await servidor.cerrar();
    }
}
const click = (nav, selector) => nav.evaluar(`document.querySelector(${JSON.stringify(selector)}).click()`);
const dentro = async (nav, width) => assert.equal(await nav.evaluar('document.documentElement.scrollWidth'), width);
async function soltar(nav, form, tipo='image/png', bytes=0, cantidad=1) {
    return nav.evaluar(`(()=>{
        const zona=document.querySelector('${form} .foto-campo');
        const canvas=document.createElement('canvas');
        canvas.width=8;
        canvas.height=8;
        const png=Uint8Array.from(atob(canvas.toDataURL().split(',')[1]),c=>c.charCodeAt(0));
        const dt=new DataTransfer();
        for(let i=0;i<${cantidad};i++) dt.items.add(new File([${bytes}?new Uint8Array(${bytes}):png],'foto.'+(${JSON.stringify(tipo)}==='image/png'?'png':'txt'),{type:${JSON.stringify(tipo)}}));
        zona.dispatchEvent(new DragEvent('dragover',{bubbles:true,cancelable:true,dataTransfer:dt}));
        const resaltada=zona.dataset.arrastrando==='true';
        zona.dispatchEvent(new DragEvent('drop',{bubbles:true,cancelable:true,dataTransfer:dt}));
        return resaltada&&!zona.dataset.arrastrando;
    })()`);
}

test('HU-5.4: foto al soltar en Registrar/Editar, validación y Quitar a360/390/1366', { skip:!buscarChrome() }, async()=>{
    await conPortal(async(nav,s)=>{
        for(const width of [360,390,1366]) {
            await entrar(nav,s.url,width);
            await click(nav,'[data-agregar-mascota]');
            for(const form of ['#portalAddPetForm','#portalEditPetForm']) {
                if(form.includes('Edit')) {
                    await nav.evaluar("closeModal('portalAddPetModal');verDetalle(1,{historial:false})");
                    await nav.esperar('Boolean(window.activePetData)');
                    await click(nav,'#btnEditPetProfile');
                }
                assert.equal(await soltar(nav,form),true);
                await nav.esperar(`document.querySelector('${form} .foto-campo__vista img')?.naturalWidth===8`);
                assert.equal(await nav.evaluar(`document.querySelector('${form} input[name=foto]').files.length`),1);
                await click(nav,form+' .foto-campo__quitar');
                assert.equal(await nav.evaluar(`document.querySelector('${form} input[name=foto]').files.length`),0);
                await soltar(nav,form,'text/plain');
                assert.match(await nav.evaluar(`document.querySelector('${form} .foto-campo__ayuda').textContent`),/no es JPG ni PNG/);
                await soltar(nav,form,'image/png',5*1024*1024+1);
                assert.match(await nav.evaluar(`document.querySelector('${form} .foto-campo__ayuda').textContent`),/5 MB/);
                assert.equal(await nav.evaluar("(()=>{const e=new DragEvent('drop',{bubbles:true,cancelable:true});document.body.dispatchEvent(e);return e.defaultPrevented})()"),true);
                await dentro(nav,width);
            }
        }
    });
});

test('RNF-16: patita, tarjetas compactas, ficha de dos líneas, PDF y HC por clínica', { skip:!buscarChrome() }, async()=>{
    await conPortal(async(nav,s)=>{
        for(const width of [360,390]) {
            await entrar(nav,s.url,width);
            const medidas=await nav.evaluar(`(()=>{
                const boton=document.querySelector('.home-agregar-mascota');
                return {boton:boton.getBoundingClientRect().width,aria:boton.getAttribute('aria-label'),patita:!!boton.querySelector('.ri-baidu-line'),tarjetas:[...document.querySelectorAll('.mascota-mini')].map(e=>({alto:e.getBoundingClientRect().height,ancho:e.getBoundingClientRect().width,avatar:e.querySelector('.mascota-mini__avatar').getBoundingClientRect().width,chips:[...e.querySelectorAll('.mascota-mini__clinicas .tag-mini')].filter(x=>x.getBoundingClientRect().width).length}))};
            })()`);
            assert.equal(medidas.boton,40);
            assert.equal(medidas.aria,'Agregar mascota');
            assert.equal(medidas.patita,true);
            for(const t of medidas.tarjetas) assert.ok(t.alto<=170&&t.ancho===136&&t.avatar===56&&t.chips===1,JSON.stringify(t));
            await click(nav,'.home-agregar-mascota');
            assert.equal(await nav.evaluar("document.getElementById('portalAddPetModal').classList.contains('show') || document.getElementById('portalAddPetModal').getBoundingClientRect().height>0"),true);
            await nav.evaluar("closeModal('portalAddPetModal');verDetalle(1,{historial:false})");
            await nav.esperar("document.querySelector('#drawerPetSummary strong')?.textContent==='HC-000001'");
            assert.equal(await nav.evaluar("document.querySelector('.pet-detail__raza-movil').parentElement.classList.contains('pet-detail__nombre') && getComputedStyle(document.querySelector('.pet-detail__raza-movil')).display==='block'"),true);
            assert.equal(await nav.evaluar("document.querySelector('.pet-detail__pdf').href===document.querySelector('#btnImprimirFichaMascota').href"),true);
            await click(nav,'[data-filtro-clinica="2"]');
            assert.equal(await nav.evaluar("document.querySelector('#drawerPetSummary strong').textContent"),'SUR-000042');
            await click(nav,'[data-filtro-clinica="all"]');
            assert.equal(await nav.evaluar("document.querySelector('#drawerPetSummary strong').textContent"),'HC-000001');
            await nav.evaluar("window.activePetClinicas[1].numero_historia_clinica=null;filtrarFichaPorClinica('2')");
            assert.equal(await nav.evaluar("document.querySelector('#drawerPetSummary strong').textContent"),'—');
            await dentro(nav,width);
        }
    });
});

test('HU-5.3: pie móvil solo Agendar, triage completo sin modals.css a360/390/1366', { skip:!buscarChrome() }, async()=>{
    await conPortal(async(nav,s)=>{
        for(const width of [360,390,1366]) {
            await entrar(nav,s.url,width);
            await click(nav,'[data-agendar]');
            await nav.evaluar("document.querySelector('#portalBookingModal .triage-captura__extra').open=true");
            await click(nav,'#portalBookingModal [data-triage-otro]');
            const m=await nav.evaluar(`(()=>{const f=document.querySelector('#portalBookingForm');return {guia:getComputedStyle(f.querySelector('.booking__guia')).display,boton:f.querySelector('#booking_confirmar').getBoundingClientRect().width,pie:f.querySelector('.booking__pie').clientWidth,campos:[...f.querySelectorAll('.triage-captura .zk-input')].filter(e=>e.getBoundingClientRect().height).map(e=>({alto:e.getBoundingClientRect().height,borde:getComputedStyle(e).borderTopWidth,radio:getComputedStyle(e).borderRadius,fuente:getComputedStyle(e).fontSize})),modals:[...document.styleSheets].some(h=>h.href?.includes('calendar-modules/modals'))}})()`);
            assert.equal(m.modals,false);
            assert.ok(m.campos.length>1);
            for(const c of m.campos) {
                assert.ok(c.alto>=44&&c.borde==='1px'&&c.radio==='12px',JSON.stringify(c));
                if(width<768) assert.equal(c.fuente,'16px');
            }
            if(width<768) {
                assert.equal(m.guia,'none');
                assert.ok(m.pie-m.boton<=40,JSON.stringify(m));
            } else assert.notEqual(m.guia,'none');
            await dentro(nav,width);
        }
    });
});

test('RE-5.5.1–3: imprimible legible/print sin controles y regreso a ficha o Vacunas', { skip:!buscarChrome() }, async()=>{
    await conPortal(async(nav,s)=>{
        for(const origen of ['mascota','salud','']) {
            await nav.enviar('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
            await nav.enviar('Page.navigate',{url:s.url+'/index.php?action=portal_imprimir_historial&id_mascota=1&origen='+origen});
            await nav.esperar("document.readyState==='complete' && Boolean(document.querySelector('.historial-documento'))");
            await dentro(nav,390);
            assert.equal(await nav.evaluar("document.querySelectorAll('.historial-hc dd').length"),2);
            assert.equal(await nav.evaluar("document.querySelectorAll('.historial-consulta').length"),2);
            assert.equal(await nav.evaluar("[...document.querySelectorAll('.historial-consulta header strong')].map(e=>e.textContent).join('|')"),'01/09/2026 · 10:00|01/10/2026 · 14:00');
            await nav.enviar('Emulation.setEmulatedMedia',{media:'print'});
            assert.equal(await nav.evaluar("getComputedStyle(document.querySelector('.floating-actions')).display"),'none');
            assert.equal(await nav.evaluar("getComputedStyle(document.querySelector('.historial-consulta')).breakInside"),'avoid');
            assert.equal(await nav.evaluar("getComputedStyle(document.querySelector('thead')).display"),'table-header-group');
            await nav.enviar('Emulation.setEmulatedMedia',{media:'screen'});
            await click(nav,'.btn-secondary');
            await nav.esperar("typeof switchTab==='function' && document.readyState==='complete'");
            if(origen==='mascota') await nav.esperar("document.querySelector('#screen-pet-detail.active') && window.activePetData?.id_mascota==1");
            else if(origen==='salud') {
                await nav.esperar("!document.querySelector('#agenda-salud').hidden && document.querySelector('.pet-chip[data-pet-id=\"1\"].active')");
            } else assert.equal(await nav.evaluar("document.querySelector('.app-screen.active').id"),'screen-home');
        }
    });
});
