const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { servidorCitas, fecha } = require('./apoyo/citas-admin-servidor.cjs');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');

async function conVista(rol, ejecutar) {
    const servidor = await servidorCitas(rol);
    const nav = await abrirChrome(buscarChrome());
    try {
        await nav.enviar('Page.navigate', { url: servidor.url });
        await nav.esperar(rol === 'admin' ? "document.querySelectorAll('.kb-card').length===8" : "document.querySelector('[data-conciliar-identidad]')");
        await ejecutar(nav, servidor);
        assert.deepEqual(nav.errores, []);
    } finally {
        await nav.cerrar();
        await servidor.cerrar();
    }
}
async function captura(nav, nombre) {
    if (process.env.ZOOKI_CAPTURAR_CITAS !== '1') return;
    await nav.evaluar("Promise.all([...document.querySelectorAll('.citas-pestanas button,.zk-dialogo')].flatMap(n=>n.getAnimations()).map(a=>a.finished.catch(()=>{})))");
    const carpeta = 'scratch/r/citas-admin/despues';
    fs.mkdirSync(carpeta, { recursive: true });
    const imagen = await nav.enviar('Page.captureScreenshot', { format: 'png' });
    fs.writeFileSync(carpeta + '/' + nombre + '.png', Buffer.from(imagen.data, 'base64'));
}
const cambiar = (nav, id, valor) => nav.evaluar(`document.getElementById(${JSON.stringify(id)}).value=${JSON.stringify(valor)};document.getElementById(${JSON.stringify(id)}).dispatchEvent(new Event('change',{bubbles:true}));true`);
const tecla = async (nav, key, code = key) => {
    const codigo = key === 'Escape' ? 27 : key === 'Enter' ? 13 : 32;
    await nav.enviar('Input.dispatchKeyEvent', { type: 'keyDown', key, code, windowsVirtualKeyCode: codigo, nativeVirtualKeyCode: codigo, ...(key===' '?{text:' '}: {}) });
    await nav.enviar('Input.dispatchKeyEvent', { type: 'keyUp', key, code, windowsVirtualKeyCode: codigo, nativeVirtualKeyCode: codigo });
};

test('R Citas admin: filtros originales, métricas, triage, detalle accesible y diseño', { skip: !buscarChrome(), timeout: 45000 }, async () => {
    await conVista('admin', async nav => {
        for (const [width,height] of [[390,844],[360,800],[820,1180],[1366,768],[1440,768]]) {
            await nav.enviar('Emulation.setDeviceMetricsOverride', { width,height,deviceScaleFactor:1,mobile:width<768 });
            await nav.evaluar("Promise.all(document.querySelector('.pill-sidebar').getAnimations().map(a=>a.finished.catch(()=>{})))");
            assert.equal(await nav.evaluar('document.documentElement.scrollWidth<=innerWidth'),true,'Sin desborde a '+width);
            if (width>=1366) assert.equal(await nav.evaluar('document.documentElement.scrollHeight<=innerHeight+1'),true,'Escritorio sin scroll a '+width);
            const cifras = await nav.evaluar("['total','pendientes','completadas','canceladas'].map(n=>document.getElementById('kpi-'+n).textContent)");
            assert.deepEqual(cifras,['8','3','1','1']);
            if ([390,1366,1440].includes(width)) await captura(nav,width+'-agenda');
        }
        assert.deepEqual(await nav.evaluar("[...document.querySelectorAll('.citas-triage-punto')].map(n=>n.getAttribute('aria-label'))"),['Triage amarillo','Triage naranja','Triage rojo','Triage amarillo','Triage naranja','Triage rojo']);
        const exportacion = await nav.evaluar(`(async () => {
            const filas=[];
            const hoja={mergeCells(){},getCell(){return {};},getRow(){return {};},addRow(valores){if(valores.length===9&&valores[0]!=='ID')filas.push(valores);return {eachCell(){}};}};
            window.ExcelJS={Workbook:class{addWorksheet(){return hoja;}xlsx={writeBuffer:async()=>new ArrayBuffer(0)};}};
            let pdf=[];
            window.jspdf={jsPDF:class{internal={pageSize:{width:842}};setFillColor(){}rect(){}setTextColor(){}setFontSize(){}setFont(){}text(){}autoTable(datos){pdf=datos.body;}save(){}}};
            const clic=HTMLAnchorElement.prototype.click;
            const aviso=window.zookiToast;
            HTMLAnchorElement.prototype.click=function(){};
            window.zookiToast=()=>{};
            try {await exportarCitas();exportarCitasPDF();}finally{HTMLAnchorElement.prototype.click=clic;window.zookiToast=aviso;}
            return {excel:filas.length,pdf:pdf.length,paciente:filas[0][1]};
        })()`);
        assert.deepEqual(exportacion,{excel:8,pdf:8,paciente:'Luna'});
        await cambiar(nav,'filtroFecha','ayer');
        assert.deepEqual(await nav.evaluar('citasFiltradasGlobal.map(c=>c.id_cita)'),[9]);
        await cambiar(nav,'filtroFecha','');
        assert.equal(await nav.evaluar('citasFiltradasGlobal.length'),10);
        await cambiar(nav,'filtroFechaCustom',fecha(2));
        assert.deepEqual(await nav.evaluar('citasFiltradasGlobal.map(c=>c.id_cita)'),[10]);
        await nav.evaluar('limpiarFiltros();true');
        await cambiar(nav,'filtroEstado','en_curso');
        assert.deepEqual(await nav.evaluar('citasFiltradasGlobal.map(c=>c.id_cita)'),[5]);
        assert.equal(await nav.evaluar("document.querySelector('.citas-resumen [data-ui-accion=limpiar-citas]').hidden"),false);
        await nav.evaluar('limpiarFiltros();true');
        assert.equal(await nav.evaluar("document.querySelector('.citas-resumen [data-ui-accion=limpiar-citas]').hidden"),true);
        await cambiar(nav,'filtroTipoCita','Vacunación');
        assert.deepEqual(await nav.evaluar('citasFiltradasGlobal.map(c=>c.id_cita)'),[2,4,6,8]);
        await nav.evaluar("limpiarFiltros();campoCitas('filtroSearch').value='nala';aplicarFiltros();true");
        assert.deepEqual(await nav.evaluar('citasFiltradasGlobal.map(c=>c.id_cita)'),[3,7]);
        await cambiar(nav,'filtroVet','2');
        assert.equal(await nav.evaluar("document.querySelector('[data-citas-vet=\"5\"]').textContent"),'Elena 1','Conteos de chips conservan la búsqueda y permiten cambiar de vet');
        await nav.enviar('Emulation.setDeviceMetricsOverride',{width:1366,height:768,deviceScaleFactor:1,mobile:false});
        await nav.evaluar("limpiarFiltros();todasLasCitas[0].mascota_nombre='<img src=x onerror=window.ataque=1>';todasLasCitas[0].motivo='<script>ataque</script>';aplicarFiltros();document.querySelector('.kb-card').focus();true");
        await tecla(nav,'Enter');
        assert.equal(await nav.evaluar("document.getElementById('citasDetalle').open"),true);
        assert.equal(await nav.evaluar("document.querySelector('[data-cita-detalle=mascota_nombre]').textContent"),'<img src=x onerror=window.ataque=1>');
        assert.equal(await nav.evaluar("document.querySelector('#citasDetalle img')===null && !window.ataque"),true);
        assert.match(await nav.evaluar("document.querySelector('[data-cita-detalle-fecha]').textContent"),/^\d{2}\/\d{2}\/\d{4}/);
        await nav.evaluar("todasLasCitas[0].mascota_nombre='Luna';todasLasCitas[0].motivo='Revisión de rutina';verDetalle(1);aplicarFiltros();true");
        await captura(nav,'1366-detalle');
        await tecla(nav,'Escape');
        await nav.esperar("!document.getElementById('citasDetalle').open");
        assert.equal(await nav.evaluar("document.getElementById('citasDetalle').open"),false);
        await nav.evaluar("document.querySelector('.kb-card').focus();true");
        await tecla(nav,' ','Space');
        assert.equal(await nav.evaluar("document.getElementById('citasDetalle').open"),true);
        await nav.evaluar("document.querySelector('#citasDetalle .zk-dialogo__cerrar').click();document.querySelector('.kb-card').click();true");
        await nav.enviar('Input.dispatchMouseEvent',{type:'mousePressed',x:2,y:2,button:'left',clickCount:1});
        await nav.enviar('Input.dispatchMouseEvent',{type:'mouseReleased',x:2,y:2,button:'left',clickCount:1});
        assert.equal(await nav.evaluar("document.getElementById('citasDetalle').open"),false,'Cierra al tocar el fondo');
        await nav.evaluar("document.querySelector('[data-agenda-tab=triage]').click();true");
        await nav.esperar("document.querySelector('[data-solicitudes-lista] .ah-item')");
        assert.equal(await nav.evaluar("document.querySelector('[data-agenda-solicitudes]').dataset.puedeRevisar"),'0');
        assert.equal(await nav.evaluar("document.querySelector('[data-solicitudes-lista] form')===null"),true);
        assert.equal(await nav.evaluar("document.querySelector('.citas-espera').textContent"),'Espera al veterinario');
        await captura(nav,'1366-triage');
        await nav.evaluar("document.querySelector('[data-agenda-tab=urgencias]').click();true");
        await nav.esperar("document.querySelector('[data-conciliar-identidad]')");
        await captura(nav,'1366-urgencias');
        await nav.evaluar("document.querySelector('[data-agenda-tab=agenda]').click();true");
        await nav.enviar('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
        await nav.evaluar("document.querySelector('[data-abrir-dialogo=citasFiltros]').click();true");
        assert.equal(await nav.evaluar("document.getElementById('citasFiltros').open"),true);
        assert.equal(await nav.evaluar("document.getElementById('citasFiltros').contains(document.getElementById('filtroTipoCita'))"),true);
        const calendario = await nav.evaluar(`(() => {
            const fp=document.getElementById('filtroFechaCustom')._flatpickr;
            if (!fp) return null;
            fp.open();
            const r=fp.calendarContainer.getBoundingClientRect();
            const dentro=r.left>=0&&r.right<=innerWidth+1&&r.top>=0&&r.bottom<=innerHeight+1;
            fp.close();
            return {dentro,rect:{left:r.left,right:r.right,top:r.top,bottom:r.bottom}};
        })()`);
        if (calendario !== null) assert.equal(calendario.dentro,true,'SelectorFechas dentro de la hoja y del viewport: '+JSON.stringify(calendario));
        await nav.evaluar("document.querySelector('[data-citas-estado=pendiente]').click();true");
        assert.equal(await nav.evaluar("document.querySelector('[data-citas-ver]').textContent"),'Ver 2 citas');
        await captura(nav,'390-filtros');
        await nav.evaluar("document.querySelector('[data-citas-ver]').click();document.querySelector('[data-citas-vet=\"2\"]').click();true");
        assert.deepEqual(await nav.evaluar('citasFiltradasGlobal.map(c=>c.id_cita)'),[1]);
        await nav.evaluar("document.querySelector('.kb-card').click();true");
        assert.equal(await nav.evaluar("Math.abs(document.getElementById('citasDetalle').getBoundingClientRect().bottom-innerHeight)<1"),true);
        await captura(nav,'390-detalle');
    });
});

for (const rol of ['admin','vet']) {
    test('R Conciliar identidad en ventana conserva validación y envío: '+rol,{ skip: !buscarChrome(), timeout:45000 },async()=> {
        await conVista(rol,async(nav,servidor)=> {
            if (rol==='admin') await nav.evaluar("document.querySelector('[data-agenda-tab=urgencias]').click();true");
            else await nav.evaluar("document.querySelector('[data-urgencias-agenda]').hidden=false;true");
            await nav.esperar("document.querySelector('[data-conciliar-identidad]')");
            await nav.evaluar("document.querySelector('[data-conciliar-identidad]').click();true");
            assert.equal(await nav.evaluar("document.querySelector('.urgencia-conciliacion').open"),true);
            assert.equal(await nav.evaluar("document.querySelector('.urgencia-conciliacion [type=submit]').disabled"),true);
            await nav.enviar('Emulation.setDeviceMetricsOverride',{width:360,height:800,deviceScaleFactor:1,mobile:true});
            assert.equal(await nav.evaluar("(()=>{const r=document.querySelector('.urgencia-conciliacion').getBoundingClientRect();return r.left>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1;})()"),true,'La conciliación cabe en celular para '+rol);
            await nav.evaluar("(()=>{const f=document.querySelector('.urgencia-conciliacion form');f.elements.namedItem('modo').value='completar';f.elements.namedItem('modo').dispatchEvent(new Event('change'));})()");
            assert.equal(await nav.evaluar("document.querySelector('.urgencia-conciliacion fieldset').disabled"),false);
            assert.equal(await nav.evaluar("document.querySelector('.urgencia-conciliacion [name=id_mascota_final]').disabled"),true);
            await nav.evaluar("(()=>{const f=document.querySelector('.urgencia-conciliacion form');f.elements.namedItem('modo').value='consolidar';f.elements.namedItem('modo').dispatchEvent(new Event('change'));})()");
            await nav.evaluar(`(() => {
                const f=document.querySelector('.urgencia-conciliacion form');
                for(const [nombre,valor] of [['id_propietario','6'],['id_mascota_final','1'],['evidencia','Titular verificado mediante cuenta y vínculo']]) { f.elements.namedItem(nombre).value=valor;f.elements.namedItem(nombre).dispatchEvent(new Event('change',{bubbles:true})); }
                f.requestSubmit();
            })()`);
            await nav.esperar("!document.querySelector('.urgencia-conciliacion').open");
            const llamada=servidor.llamadas.find(n=>n.accion==='conciliar_rojo_ajax');
            assert.ok(llamada);
            for(const campo of ['id_ingreso','id_propietario','id_mascota_final','evidencia','clave_operacion','csrf_token']) assert.ok(llamada.cuerpo.includes('name="'+campo+'"'),campo);
        });
    });
}
