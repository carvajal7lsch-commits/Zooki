const test = require('node:test');
const assert = require('node:assert/strict');
const { servidorPacientes } = require('./apoyo/pacientes-movil-servidor.cjs');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');

async function conDirectorio(ejecutar) {
    const servidor = await servidorPacientes({ directorio: true });
    const nav = await abrirChrome(buscarChrome());
    try {
        await nav.enviar('Emulation.setDeviceMetricsOverride', { width:1366, height:768, deviceScaleFactor:1, mobile:false });
        await nav.enviar('Page.navigate', { url:servidor.url });
        await nav.esperar("document.querySelectorAll('#ownersGrid .client-card').length===8");
        await nav.evaluar("window.Swal={mixin:()=>({fire:async()=>({})}),fire:async()=>({isConfirmed:true})}");
        await ejecutar(nav,servidor);
        assert.deepEqual(nav.errores,[]);
    } finally {
        await nav.cerrar();
        await servidor.cerrar();
    }
}
const pintar = nav => nav.evaluar('new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)))');

test('R retoques: buscador completo, controles centrados, tarjetas y detalle legibles a820/1366', { skip: !buscarChrome() }, async () => {
    await conDirectorio(async nav => {
        for (const width of [820, 1366]) {
            await nav.enviar('Emulation.setDeviceMetricsOverride', { width, height: width === 820 ? 1180 : 768, deviceScaleFactor: 1, mobile: false });
            await pintar(nav);
            for (const modulo of ['owners', 'pets']) {
                await nav.evaluar(`switchModule('${modulo}',{recargar:false})`);
                await pintar(nav);
                const controles = await nav.evaluar(`(() => {
                    const fila = document.querySelector('#${modulo === 'owners' ? 'searchRowOwners' : 'searchRowPets'}');
                    const caja = fila.querySelector('.search-input').getBoundingClientRect();
                    const espacio = fila.querySelector('.head-search').getBoundingClientRect();
                    const filtro = fila.querySelector('.head-status-filters').getBoundingClientRect();
                    const selector = fila.querySelector('select');
                    const estilo = selector && getComputedStyle(selector);
                    return { desborde:document.documentElement.scrollWidth > innerWidth, ancho:caja.width, libre:espacio.width, hueco:filtro.left-caja.right, centrado:[...fila.querySelectorAll('.segmented-control label')].every(e=>getComputedStyle(e).alignItems==='center'), raya:getComputedStyle(document.querySelector('.head-view-toggle')).borderRightWidth, selector:selector && { ancho:selector.getBoundingClientRect().width, alto:selector.getBoundingClientRect().height, color:estilo.color, fondo:estilo.backgroundColor } };
                })()`);
                assert.equal(controles.desborde, false, width + '/' + modulo);
                assert.ok(Math.abs(controles.ancho - controles.libre) < 1, JSON.stringify(controles));
                assert.ok(Math.abs(controles.hueco - 10) < 1, JSON.stringify(controles));
                assert.equal(controles.centrado, true);
                assert.equal(controles.raya, '0px');
                if (modulo === 'pets') assert.deepEqual(controles.selector, { ancho:170, alto:40, color:'rgb(71, 85, 105)', fondo:'rgb(248, 250, 252)' });
            }
            await nav.evaluar("switchModule('owners',{recargar:false})");
            const tarjetas = await nav.evaluar(`(() => {
                const vacias = [...document.querySelectorAll('#ownersGrid [data-total-mascotas="0"]')];
                return { vacias:vacias.length>0 && vacias.every(e=>e.querySelector('.propietario-mascotas__nombres').textContent==='Sin mascotas aún'), espacios:[...document.querySelectorAll('#ownersGrid .client-card')].map(e=>e.querySelector('.propietario-cantidad').getBoundingClientRect().top-e.querySelector('.card-title-mini').getBoundingClientRect().bottom) };
            })()`);
            assert.equal(tarjetas.vacias, true);
            assert.ok(tarjetas.espacios.every(g => Math.abs(g - 2) < 1), JSON.stringify(tarjetas));
            await nav.evaluar('openOwnerDossier(6)');
            const detalle = await nav.evaluar(`(() => {
                const edad = document.querySelector('.propietario-mascota-edad');
                const avatar = document.querySelector('.propietario-mascota-avatar .avatar-iniciales');
                return { desborde:document.documentElement.scrollWidth>innerWidth, edad:edad.getBoundingClientRect().width, salto:getComputedStyle(edad).whiteSpace, fondo:getComputedStyle(avatar).backgroundColor, tarjeta:getComputedStyle(document.querySelector('#ownersGrid .avatar-mini')).backgroundColor, selector:document.getElementById('dossierPetSpeciesFilter').getBoundingClientRect().width };
            })()`);
            assert.equal(detalle.desborde, false, JSON.stringify(detalle));
            assert.equal(detalle.edad, 140);
            assert.equal(detalle.salto, 'nowrap');
            assert.equal(detalle.fondo, detalle.tarjeta);
            assert.equal(detalle.selector, 170);
            await nav.evaluar('hideDossier()');
        }
    });
});

test('R A: al guardar y repintar sin recargar, las tarjetas y filas nuevas conservan ficha y un solo toggle', { skip: !buscarChrome() }, async () => {
    await conDirectorio(async (nav, servidor) => {
        await nav.evaluar("switchModule('pets',{recargar:false});window.__anterior=document.querySelector('#petsGrid .pet-card input[type=checkbox]')");
        await nav.evaluar('ZookiPropietarioMascota.refrescarPacientes()');
        await pintar(nav);
        const preparada = await nav.evaluar(`(() => {
            const tarjeta = document.querySelector('#petsGrid .pet-card');
            const campo = tarjeta.querySelector('input[type=checkbox]');
            window.__nuevo = campo;
            return { nuevo:campo!==__anterior, tab:tarjeta.tabIndex, pie:!!campo.closest('.card-footer-mini'), cantidad:tarjeta.querySelectorAll('input[type=checkbox]').length, respaldo:tarjeta.dataset.fotoRespaldo };
        })()`);
        assert.deepEqual(preparada, { nuevo:true, tab:0, pie:true, cantidad:1, respaldo:'true' });
        const envios = servidor.llamadas.filter(l => l.accion === 'cambiar_estado_mascota_ajax').length;
        await nav.evaluar('window.__nuevo.click()');
        await pintar(nav);
        assert.equal(servidor.llamadas.filter(l => l.accion === 'cambiar_estado_mascota_ajax').length, envios + 1);
        await nav.evaluar("document.querySelector('#petsGrid .pet-card').dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true,cancelable:true}))");
        await nav.esperar("document.getElementById('dossierView').dataset.seccion==='ficha'");
        await nav.evaluar("hideDossier();hideDossier();switchView('list');document.querySelector('#petsTableBody tr[data-id]').click()");
        await nav.esperar("document.getElementById('dossierView').dataset.seccion==='ficha'");
        await nav.enviar('Emulation.setDeviceMetricsOverride', { width:390, height:844, deviceScaleFactor:1, mobile:true });
        await pintar(nav);
        assert.equal(await nav.evaluar("window.__nuevo===document.querySelector('#petsGrid .pet-card input[type=checkbox]') && !!__nuevo.closest('.card-header-mini')"), true);
    });
});

test('R A: hoja, columnas, conteos/plural/filtros, filas y control móvil sin desborde', { skip:!buscarChrome() }, async()=>{
    await conDirectorio(async nav=>{
        for(const width of [1366,1024,820,390]) {
            await nav.enviar('Emulation.setDeviceMetricsOverride',{width,height:width===820?1180:844,deviceScaleFactor:1,mobile:width<768});
            await pintar(nav);
            await nav.evaluar("switchModule('owners',{recargar:false});document.getElementById('eprop_todos').checked=true;filterOwners()");
            await pintar(nav);
            const propietarios=await nav.evaluar("({columnas:getComputedStyle(document.getElementById('ownersGrid')).gridTemplateColumns.split(' ').length,desborde:document.documentElement.scrollWidth>innerWidth,hoja:getComputedStyle(document.querySelector('.pacientes-modulo')).backgroundColor,resumen:document.querySelector('[data-pacientes-resumen]').textContent,tabsEnHoja:!!document.getElementById('tabOwners').closest('[data-pacientes-hoja-cabecera]'),sinContacto:document.querySelector('#ownersGrid [data-estado=\\\"0\\\"] .card-contact-mini')===null})".replaceAll('\\\"','"'));
            assert.equal(propietarios.desborde,false,width);
            assert.equal(propietarios.tabsEnHoja,width>=768,width);
            assert.equal(propietarios.columnas,width>=1280?4:width>=1024?3:width>=768?2:1,width);
            assert.equal(propietarios.sinContacto,true);
            if(width>=768) assert.equal(propietarios.hoja,'rgb(255, 255, 255)');
            assert.match(propietarios.resumen,/8 propietarios · 2 sin activar/);
            await nav.evaluar("document.getElementById('eprop_inactivos').checked=true;filterOwners()");
            await pintar(nav);
            assert.equal(await nav.evaluar("Array.from(document.querySelectorAll('#ownersGrid .client-card')).filter(c=>c.style.display!=='none').length"),1);
            await nav.evaluar("document.getElementById('eprop_todos').checked=true;filterOwners();switchModule('pets',{recargar:false});document.getElementById('emasc_todos').checked=true;filterTable()");
            await pintar(nav);
            assert.equal(await nav.evaluar('document.documentElement.scrollWidth<=innerWidth'),true,width);
            if(width>=768) {
                assert.match(await nav.evaluar("document.querySelector('[data-pacientes-resumen]').textContent"),/12 mascotas · 3 sin historia/);
                assert.equal(await nav.evaluar("getComputedStyle(document.getElementById('petsGrid')).gridTemplateColumns.split(' ').length"),width>=1280?4:width>=1024?3:2);
            }
            await nav.evaluar("switchView('list')");
            assert.equal(await nav.evaluar('document.documentElement.scrollWidth<=innerWidth'),true,width+'/tabla');
            await nav.evaluar("switchView('grid');switchModule('owners',{recargar:false});switchOwnerView('list')");
            assert.equal(await nav.evaluar('document.documentElement.scrollWidth<=innerWidth'),true,width+'/tabla-propietarios');
            await nav.evaluar("switchOwnerView('grid')");
        }
    });
});

test('R A: edición/reenvío/historia/toggle aislados; clic y teclado abren ficha; texto seguro', { skip:!buscarChrome() }, async()=>{
    await conDirectorio(async(nav,servidor)=>{
        await nav.evaluar("window.__editados=[];window.editOwner=id=>__editados.push(id);document.querySelector('#ownersGrid .client-card button[title=Editar]').click()");
        assert.deepEqual(await nav.evaluar('window.__editados'),[6]);
        assert.equal(await nav.evaluar("document.getElementById('dossierView').classList.contains('d-none')"),true);
        await nav.evaluar("document.querySelector('#ownersGrid [data-reenviar-activacion]').click()");
        await pintar(nav);
        assert.equal(await nav.evaluar("document.getElementById('dossierView').classList.contains('d-none')"),true);
        await nav.evaluar("document.querySelector('#ownersGrid .client-card').click()");
        await nav.esperar("document.getElementById('dossierView').dataset.seccion==='listado'");
        assert.equal(await nav.evaluar("getComputedStyle(document.getElementById('dossierPetsScroll')).gridTemplateColumns.split(' ').length"),1);
        await nav.evaluar("hideDossier();switchModule('pets',{recargar:false});window.editPet=id=>__editados.push('mascota:'+id);window.viewMedicalHistory=id=>{window.__historia=id};document.querySelector('#petsGrid [data-c3-click=evento38]').click();document.querySelector('#petsGrid [data-c3-click=evento39]').click()");
        assert.equal(await nav.evaluar('window.__historia'),1);
        assert.equal(await nav.evaluar("document.getElementById('dossierView').classList.contains('d-none')"),true);
        await nav.evaluar("window.__toggle=document.querySelector('#petsGrid .pet-card input[type=checkbox]');__toggle.click()");
        await pintar(nav);
        assert.equal(await nav.evaluar("document.getElementById('dossierView').classList.contains('d-none')"),true);
        assert.ok(servidor.llamadas.some(l=>l.accion==='cambiar_estado_mascota_ajax'&&l.metodo==='POST'));
        await nav.evaluar("document.querySelector('#petsGrid .pet-card').focus();document.querySelector('#petsGrid .pet-card').dispatchEvent(new KeyboardEvent('keydown',{key:' ',bubbles:true,cancelable:true}))");
        await nav.esperar("document.getElementById('dossierView').dataset.seccion==='ficha'");
        await nav.evaluar("hideDossier();hideDossier();switchOwnerView('list');document.querySelector('#ownersTableBody tr').dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true,cancelable:true}))");
        await nav.esperar("document.getElementById('dossierView').dataset.seccion==='listado'");
        await nav.evaluar("hideDossier();switchModule('pets',{recargar:false});switchView('list');document.querySelector('#petsTableBody tr[data-id]').click()");
        await nav.esperar("document.getElementById('dossierView').dataset.seccion==='ficha'");
        await nav.enviar('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
        await pintar(nav);
        assert.equal(await nav.evaluar("window.__toggle===document.querySelector('#petsGrid .pet-card input[type=checkbox]')"),true);
        servidor.propietario.nombre_completo='<img src=x onerror=alert(1)>';
        servidor.propietario.mascotas_resumen=['<svg onload=alert(1)>'];
        await nav.evaluar('loadOwners()');
        assert.equal(await nav.evaluar("!document.querySelector('#ownersGrid .card-title-mini img')&&!document.querySelector('#ownersGrid .propietario-mascotas__nombres svg')"),true);
    });
});
