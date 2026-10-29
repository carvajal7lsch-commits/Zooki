const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { servidorUsuariosPacientes } = require('./apoyo/usuarios-pacientes-servidor.cjs');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');

const pintar = nav => nav.evaluar('new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)))');
const directorios = {
    usuarios: [
        { tab:'[data-tab=personal]', filas:'[data-panel=personal] [data-vista-contenido=tarjetas] [data-fila]', tabla:'[data-panel=personal] tbody [data-fila]', pie:'[data-panel=personal] .directorio-paginacion', buscar:'[data-filtros=personal] [data-filtro-texto]', resumen:'[data-usuarios-resumen]', filtro:'#ep_activos', tablaBoton:'[data-vista-usuarios=tabla]', tarjetasBoton:'[data-vista-usuarios=tarjetas]', termino:'Persona 0', sustantivo:'personas' },
        { tab:'[data-tab=clientes]', filas:'[data-panel=clientes] [data-vista-contenido=tarjetas] [data-fila]', tabla:'[data-panel=clientes] tbody [data-fila]', pie:'[data-panel=clientes] .directorio-paginacion', buscar:'[data-filtros=clientes] [data-filtro-texto]', resumen:'[data-usuarios-resumen]', filtro:'#ec_activos', tablaBoton:'[data-vista-usuarios=tabla]', tarjetasBoton:'[data-vista-usuarios=tarjetas]', termino:'Cliente 0', sustantivo:'clientes' },
    ],
    pacientes: [
        { tab:'#tabOwners', filas:'#ownersGrid .client-card', tabla:'#ownersTableBody tr', pie:'#ownersDirectory .directorio-paginacion', buscar:'#ownerSearch', resumen:'[data-pacientes-resumen]', filtro:'#eprop_activos', tablaBoton:'#btnOwnerViewList', tarjetasBoton:'#btnOwnerViewGrid', termino:'Propietario 0', sustantivo:'propietarios' },
        { tab:'#tabPets', filas:'#petsGrid .pet-card', tabla:'#petsTableBody tr[data-id]', pie:'#modulePets .directorio-paginacion', buscar:'#tableSearch', resumen:'[data-pacientes-resumen]', filtro:'#emasc_todos', tablaBoton:'#btnViewList', tarjetasBoton:'#btnViewGrid', termino:'Mascota 0', sustantivo:'mascotas' },
    ],
};

test('RNF-16: rango local de12, tres páginas con30 y límites vacíos o decrecientes', () => {
    const contexto = { window:{} };
    vm.runInNewContext(fs.readFileSync('public/js/paginacion-directorio.js', 'utf8'), contexto);
    const rango = contexto.window.zookiPaginacionDirectorio.rango;
    assert.equal(rango(30).paginas, 3);
    assert.equal(rango(30, 2).inicio, 12);
    assert.equal(rango(30, 3).fin, 30);
    assert.equal(rango(9, 3).pagina, 1);
    assert.equal(rango(0).fin, 0);
    assert.equal(rango(30, -2).pagina, 1);
});

for (const tipo of Object.keys(directorios)) {
    test('R directorios ' + tipo + ': páginas/tablas, totales completos, filtros, regreso y anchos', { skip:!buscarChrome() }, async () => {
        const servidor = await servidorUsuariosPacientes(tipo, true);
        const nav = await abrirChrome(buscarChrome());
        try {
            await nav.enviar('Emulation.setDeviceMetricsOverride', { width:1366, height:768, deviceScaleFactor:1, mobile:false });
            await nav.enviar('Page.navigate', { url:servidor.url });
            await nav.esperar('document.querySelectorAll(' + JSON.stringify(directorios[tipo][0].filas) + ').length===30');
            for (const d of directorios[tipo]) {
                const elegir = selector => nav.evaluar('document.querySelector(' + JSON.stringify(selector) + ').click()');
                const consultar = () => nav.evaluar(`(() => {
                    const d=${JSON.stringify(d)};
                    const visibles=s=>[...document.querySelectorAll(s)].filter(n=>n.getBoundingClientRect().height>0);
                    const pie=document.querySelector(d.pie);
                    return { tarjetas:visibles(d.filas).length, tabla:visibles(d.tabla).length, oculto:pie.hidden, rango:pie.querySelector('[role=status]').textContent, resumen:document.querySelector(d.resumen).textContent, anterior:pie.querySelector('button').disabled, siguiente:pie.querySelector('button:last-child').disabled };
                })()`);
                await elegir(d.tab);
                await elegir(d.tarjetasBoton);
                await pintar(nav);
                let datos = await consultar();
                assert.equal(datos.tarjetas, 12, d.tab);
                assert.match(datos.rango, new RegExp('1–12 de 30 ' + d.sustantivo));
                assert.match(datos.resumen, new RegExp('30 ' + d.sustantivo));
                assert.equal(datos.anterior, true);
                const peticiones = servidor.llamadas.length;
                await elegir(d.pie + ' button:last-child');
                datos = await consultar();
                assert.match(datos.rango, /13–24 de 30/);
                assert.match(datos.resumen, /30 /);
                await elegir(d.tablaBoton);
                datos = await consultar();
                assert.equal(datos.tabla, 12);
                await elegir(d.pie + ' button:last-child');
                datos = await consultar();
                assert.equal(datos.tabla, 6);
                assert.match(datos.rango, /25–30 de 30/);
                assert.equal(datos.siguiente, true);
                assert.equal(servidor.llamadas.length, peticiones, 'Paginar y cambiar vista no consulta al servidor');
                await nav.evaluar(`(()=>{const c=document.querySelector(${JSON.stringify(d.buscar)});c.value=${JSON.stringify(d.termino)};c.dispatchEvent(new Event('${tipo === 'usuarios' ? 'input' : 'keyup'}',{bubbles:true}))})()`);
                await pintar(nav);
                datos = await consultar();
                assert.equal(datos.tabla, 9);
                assert.equal(datos.oculto, true);
                assert.match(datos.resumen, /9 /);
                await nav.evaluar(`(()=>{const c=document.querySelector(${JSON.stringify(d.buscar)});c.value='';c.dispatchEvent(new Event('${tipo === 'usuarios' ? 'input' : 'keyup'}',{bubbles:true}))})()`);
                await elegir(d.pie + ' button:last-child');
                await elegir(d.filtro);
                await pintar(nav);
                datos = await consultar();
                assert.match(datos.rango, /^1–12 de /, 'Filtrar reinicia a1');
                await elegir(d.pie + ' button:last-child');
                await elegir(d.tab);
                await pintar(nav);
                datos = await consultar();
                assert.match(datos.rango, /^1–12 de /, 'Cambiar pestaña reinicia a1');
                await elegir(d.tarjetasBoton);
                for (const width of [390, 360, 820, 1366]) {
                    await nav.enviar('Emulation.setDeviceMetricsOverride', { width, height:width<768?844:820, deviceScaleFactor:1, mobile:width<768 });
                    await pintar(nav);
                    assert.equal(await nav.evaluar('document.documentElement.scrollWidth<=innerWidth'), true, tipo+'/'+d.tab+'/'+width);
                    assert.equal((await consultar()).tarjetas, 12);
                    if (width < 768) {
                        await nav.evaluar('window.scrollTo(0, document.documentElement.scrollHeight)');
                        const libre = await nav.evaluar(`document.querySelector(${JSON.stringify(d.pie)}).getBoundingClientRect().bottom <= document.querySelector('.pill-sidebar').getBoundingClientRect().top`);
                        assert.equal(libre, true, 'La barra inferior no tapa el pie: '+d.tab+'/'+width);
                    }
                }
            }
            if (tipo === 'usuarios') {
                await nav.evaluar("window.zookiConfirmarCorto=async()=>true;window.zookiToast=()=>{};window.__pieOriginal=document.querySelector('[data-panel=clientes] .directorio-paginacion');window.__inputAnterior=document.querySelector('[data-panel=clientes] [data-vista-contenido=tarjetas] [data-accion=estado-cliente]');document.querySelector('[data-panel=clientes] [data-vista-contenido=tarjetas] [data-accion=estado-cliente]').click()");
                await nav.esperar("document.querySelector('[data-panel=clientes] .directorio-paginacion')===window.__pieOriginal && document.querySelector('[data-panel=clientes] [data-vista-contenido=tarjetas] [data-accion=estado-cliente]')!==window.__inputAnterior && !document.querySelector('[data-panel=clientes] [data-vista-contenido=tarjetas] [data-accion=estado-cliente]').disabled");
                await nav.esperar("document.querySelector('[data-panel=clientes] .directorio-paginacion [role=status]').textContent.startsWith('1–12 de 30')");
                assert.ok(servidor.llamadas.some(l=>l.accion==='actualizar_propietario_ajax'&&l.metodo==='POST'));
                assert.equal(await nav.evaluar("document.querySelectorAll('[data-panel=clientes] .directorio-paginacion').length"), 1, 'Reponer Clientes conserva un solo paginador');
            }
            assert.deepEqual(nav.errores, []);
        } finally {
            await nav.cerrar();
            await servidor.cerrar();
        }
    });
}

test('R directorios: base de tarjeta compartida y pie ausente con12 o menos', { skip:!buscarChrome() }, async () => {
    const nav = await abrirChrome(buscarChrome());
    const estilos = [];
    try {
        for (const tipo of ['usuarios', 'pacientes']) {
            const servidor = await servidorUsuariosPacientes(tipo);
            try {
                await nav.enviar('Emulation.setDeviceMetricsOverride', { width:1366, height:768, deviceScaleFactor:1, mobile:false });
                await nav.enviar('Page.navigate', { url:servidor.url });
                await nav.esperar('document.querySelector(' + JSON.stringify(directorios[tipo][0].filas) + ')');
                for (const d of directorios[tipo]) {
                    await nav.evaluar('document.querySelector(' + JSON.stringify(d.tab) + ').click()');
                    await pintar(nav);
                    assert.equal(await nav.evaluar('document.querySelector(' + JSON.stringify(d.pie) + ').hidden'), true);
                    estilos.push(await nav.evaluar(`(()=>{const e=document.querySelector(${JSON.stringify(d.filas)});const c=getComputedStyle(e);const a=e.querySelector('.avatar-mini').getBoundingClientRect();return {base:e.classList.contains('directorio-tarjeta'),fondo:c.backgroundColor,borde:c.borderTopWidth,radio:c.borderRadius,sombra:c.boxShadow,avatar:a.width}})()`));
                }
            } finally {
                await servidor.cerrar();
            }
        }
        estilos.forEach(estilo => assert.deepEqual(estilo, estilos[0]));
        assert.equal(estilos[0].base, true);
        assert.equal(estilos[0].avatar, 48);
        assert.deepEqual(nav.errores, []);
    } finally {
        await nav.cerrar();
    }
});
