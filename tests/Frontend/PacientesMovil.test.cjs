const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { servidorPacientes } = require('./apoyo/pacientes-movil-servidor.cjs');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');
const fuente = fs.readFileSync('public/js/medical-module.js', 'utf8');

test('edad civil: singular/plural, aniversarios, meses completos y fecha local', () => {
    const inicio = fuente.indexOf('function calculateAge(');
    const contexto = {};
    vm.runInNewContext(fuente.slice(inicio, fuente.indexOf('// --- DRAWER FUNCTIONS ---', inicio)), contexto);
    const hoy = new Date(2026, 9, 28, 12);
    for (const [nacimiento, esperado] of [
        ['2024-12-28', '1 año, 10 meses'],
        ['2024-10-28', '2 años'],
        ['2026-09-28', '1 mes'],
        ['2026-08-28', '2 meses'],
        ['2025-10-28', '1 año'],
        ['2025-09-28', '1 año, 1 mes'],
        ['2024-12-31', '1 año, 9 meses'],
        ['2026-10-28', '0 meses'],
        ['', '']
    ]) assert.equal(contexto.calculateAge(nacimiento, hoy), esperado, nacimiento);
});

test('Llamar/Correo/Agendar conservan sus destinos sin enviar ni modificar datos', () => {
    const inicio = fuente.indexOf('const eventosPacientes = {');
    const contexto = {
        window: { location: { href: '' } },
        document: { getElementById: id => ({ innerText: id === 'dossierOwnerPhone' ? '3001112233' : 'fabio@zooki.test' }) }
    };
    vm.runInNewContext(fuente.slice(inicio, fuente.indexOf('/**', inicio)) + ';globalThis.acciones = eventosPacientes;', contexto);
    for (const [evento, destino] of [
        ['evento18', 'tel:3001112233'],
        ['evento19', 'mailto:fabio@zooki.test'],
        ['evento20', 'index.php?action=vet_agenda']
    ]) {
        contexto.acciones[evento]();
        assert.equal(contexto.window.location.href, destino);
    }
});

async function conPacientes(ejecutar) {
    const servidor = await servidorPacientes();
    const nav = await abrirChrome(buscarChrome());
    try {
        await nav.enviar('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
        await nav.enviar('Page.navigate', { url: servidor.url });
        await nav.esperar('typeof openOwnerDossier === "function"');
        await ejecutar(nav, servidor);
        assert.deepEqual(nav.errores, []);
    } finally {
        await nav.cerrar();
        await servidor.cerrar();
    }
}

test('Pacientes móvil: regreso al origen, filas, edición, escape, fotos y solo lectura', { skip: !buscarChrome() }, async () => {
    await conPacientes(async (nav, servidor) => {
        for (const origen of ['owners', 'pets']) {
            await nav.evaluar("switchModule('" + origen + "', {recargar:false}); openOwnerDossier(6);");
            await nav.esperar("document.querySelectorAll('.pet-dossier-card').length===5 && !document.getElementById('dossierView').classList.contains('d-none')");
            const cabecera = await nav.evaluar("(()=>{const r=document.querySelector('[data-pacientes-detalle] .propietario-volver').getBoundingClientRect();return {ancho:r.width,alto:r.height,migas:getComputedStyle(document.querySelector('.propietario-ruta p')).display,editar:!!document.querySelector('[data-pacientes-editar] [data-c3-click=evento16]'),filas:document.querySelectorAll('.pet-dossier-card').length}})()");
            assert.deepEqual(cabecera, { ancho:40, alto:40, migas:'none', editar:true, filas:5 });
            await nav.evaluar("window.__ediciones=[]; window.editOwner=id=>__ediciones.push('propietario:'+id); window.editPet=id=>__ediciones.push('mascota:'+id); document.querySelector('[data-pacientes-editar] [data-c3-click=evento16]').click();");
            await nav.evaluar("window.scrollTo(0,document.documentElement.scrollHeight); document.querySelector('.pet-dossier-card').click()");
            await nav.esperar("document.getElementById('dossierView').dataset.seccion==='ficha'");
            assert.equal(await nav.evaluar('window.scrollY'), 0, 'La nueva ficha empieza en su cabecera');
            const ficha = await nav.evaluar("({titulo:document.getElementById('dossierOwnerTitle').textContent,propietario:document.getElementById('fichaPropietarioNombre').textContent,sidebar:getComputedStyle(document.querySelector('.dossier-sidebar')).display,editar:!!document.querySelector('[data-pacientes-editar] #dashEditPetBtn'),resumen:document.getElementById('dashPetResumen').textContent})");
            assert.equal(ficha.titulo, 'Ficha de Doki');
            assert.equal(ficha.propietario, 'Fabio Propietario');
            assert.equal(ficha.sidebar, 'none');
            assert.equal(ficha.editar, true);
            assert.doesNotMatch(ficha.resumen, /1 años|1 meses/);
            await nav.evaluar("document.getElementById('dashEditPetBtn').click(); document.querySelector('.ficha-propietario-movil').click()");
            assert.equal(await nav.evaluar("document.getElementById('dossierView').dataset.seccion"), 'listado');
            assert.deepEqual(await nav.evaluar('window.__ediciones'), ['propietario:6','mascota:1']);
            await nav.evaluar("document.querySelector('[data-c3-click=evento17]').click()");
            assert.equal(await nav.evaluar("document.getElementById('tab" + (origen === 'pets' ? 'Pets' : 'Owners') + "').classList.contains('active') && !document.querySelector('[data-pacientes-directorio]').hidden"), true);
        }
        const malicioso = '<img src=x onerror=alert(1)>';
        servidor.propietario.nombre_completo = malicioso;
        servidor.mascotas[0].nombre = malicioso;
        servidor.mascotas[0].url_foto = 'no-existe.jpg';
        servidor.historial.mascota.nombre = malicioso;
        servidor.historial.mascota.url_foto = 'no-existe.jpg';
        servidor.historial.mascota.solo_lectura = true;
        servidor.historial.mascota.estado = 0;
        servidor.historial.alertas.solo_lectura = true;
        await nav.evaluar('openOwnerDossier(6)');
        await nav.esperar("document.getElementById('dossierOwnerTitle').textContent===" + JSON.stringify(malicioso));
        const seguro = await nav.evaluar("(()=>{const foto=document.querySelector('.pet-dossier-card-photo');foto.dispatchEvent(new Event('error'));return {titular:!document.getElementById('dossierOwnerTitle').querySelector('img'),mascota:!document.querySelector('.pet-dossier-card-name').querySelector('img'),respaldo:foto.hidden&&!foto.nextElementSibling.hidden}})()");
        assert.deepEqual(seguro, { titular:true, mascota:true, respaldo:true });
        await nav.evaluar("document.querySelector('.pet-dossier-card').dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true,cancelable:true}))");
        await nav.esperar("document.getElementById('dossierView').dataset.seccion==='ficha'");
        const lectura = await nav.evaluar("(()=>{const foto=document.getElementById('dashPetPhoto');foto.dispatchEvent(new Event('error'));return {editar:document.getElementById('dashEditPetBtn').hidden,registrar:!!document.querySelector('[data-alertas-ficha] [data-registrar-alerta]'),respaldo:foto.hidden&&!document.getElementById('dashPetIniciales').hidden,escape:!document.getElementById('dashPetName').querySelector('img')&&!document.getElementById('fichaPropietarioNombre').querySelector('img'),inactiva:document.getElementById('dashPetStatus').textContent}})()");
        assert.deepEqual(lectura, { editar:true, registrar:false, respaldo:true, escape:true, inactiva:'Inactiva' });
    });
});

test('RNF-16: 360/390 sin desborde ni última fila tapada; controles restaurados a 820/1366', { skip: !buscarChrome() }, async () => {
    await conPacientes(async nav => {
        await nav.evaluar('openOwnerDossier(6)');
        await nav.esperar("document.querySelectorAll('.pet-dossier-card').length===5");
        for (const width of [360,390,820,1366]) {
            await nav.enviar('Emulation.setDeviceMetricsOverride', { width, height:width===1366?768:844,deviceScaleFactor:1,mobile:width<768 });
            await nav.evaluar('new Promise(requestAnimationFrame)');
            await nav.evaluar("mostrarSeccionDossier('listado')");
            const lista = await nav.evaluar("(()=>{const editar=document.querySelector('[data-c3-click=evento16]');const fila=document.querySelector('.pet-dossier-card');return {desborde:document.documentElement.scrollWidth>innerWidth,edicionArriba:!!editar.closest('[data-pacientes-editar]'),verFicha:fila.querySelector('.btn-ficha').getBoundingClientRect().height>0,filaAlto:fila.getBoundingClientRect().height,sidebar:!!editar.closest('.owner-details-card')}})()");
            assert.equal(lista.desborde,false,width);
            assert.equal(lista.edicionArriba,width<768,width);
            assert.equal(lista.sidebar,width>=768,width);
            if(width<768) {
                assert.equal(lista.verFicha,false);
                assert.ok(lista.filaAlto<=84,lista.filaAlto);
                await nav.evaluar('window.scrollTo(0,document.documentElement.scrollHeight)');
                assert.equal(await nav.evaluar("document.querySelector('.pet-dossier-card:last-child').getBoundingClientRect().bottom <= document.querySelector('.pill-sidebar').getBoundingClientRect().top"),true,'Última mascota sobre la navegación: '+width);
            }
            await nav.evaluar('loadPetDashboard(1)');
            const detalle = await nav.evaluar("(()=>{const editar=document.getElementById('dashEditPetBtn');return {desborde:document.documentElement.scrollWidth>innerWidth,edicionArriba:!!editar.closest('[data-pacientes-editar]'),columnas:getComputedStyle(document.querySelector('.ficha-mascota-datos .dossier-pet-details')).gridTemplateColumns.split(' ').length,propietario:getComputedStyle(document.querySelector('.ficha-propietario-movil')).display}})()");
            assert.equal(detalle.desborde,false,width);
            assert.equal(detalle.edicionArriba,width<768,width);
            assert.equal(detalle.columnas,width>=1024?3:2,width);
            assert.equal(detalle.propietario,width<768?'flex':'none',width);
        }
    });
});
