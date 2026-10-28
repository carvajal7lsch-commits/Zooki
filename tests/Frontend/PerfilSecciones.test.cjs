const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { execFileSync } = require('node:child_process');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');

function renderizar(rol) {
    const php = process.env.PHP_BINARY || (process.platform === 'win32' && fs.existsSync('C:/xampp/php/php.exe') ? 'C:/xampp/php/php.exe' : 'php');
    return execFileSync(php, ['tests/Frontend/apoyo/perfil-vista.php', rol], { encoding: 'utf8', env: { ...process.env, TRUSTED_PROXIES: '127.0.0.2', ZOOKI_TEST_MYSQL_HOST: '', ZOOKI_TEST_MYSQL_DB: 'zooki_test_base_v2' } });
}

for (const rol of ['admin', 'vet']) {
    test(`Mi perfil ${rol}: pestañas, hash, actividad, formularios y adaptación 1366/900/390`, { skip: !buscarChrome() }, async () => {
        const vista = renderizar(rol);
        assert.doesNotMatch(vista, /Fatal error|Warning:|\s(?:style|onclick|onchange|onsubmit)=/);
        assert.doesNotMatch(vista, /<details[^>]*cuenta-identidad/);
        assert.equal((vista.match(/data-actividad-extra hidden/g) || []).length, 3);
        const html = vista.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '').replace(/<link\b[^>]*>/gi, '');
        const calendarioReal = process.env.ZOOKI_CALENDARIO_REAL === '1';
        const cssCalendario = calendarioReal ? `<style>${fs.readFileSync('scratch/flatpickr.min.css','utf8')}</style>` : '';
        const css = cssCalendario + ['identidad.css', 'styles.css', 'dashboard-modules/layout.css', 'usuarios.css', 'horarios.css', 'perfil.css', 'dialogos-zooki.css', 'portal/calendario.css', 'coordinacion-horarios.css', 'ajustes.css'].map(p => `<style>${fs.readFileSync('public/css/' + p, 'utf8').replace(/@import[^;]+;/g, '')}</style>`).join('');
        const nav = await abrirChrome(buscarChrome());
        try {
            const arbol = await nav.enviar('Page.getFrameTree');
            await nav.enviar('Page.setDocumentContent', { frameId: arbol.frameTree.frame.id, html: `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">${css}<style>body{margin:0}.perfil-prueba{margin-left:112px;padding:100px 32px 24px}.perfil-header-prueba{position:absolute;top:0;left:112px;right:0}@media(max-width:1023px){.perfil-prueba{margin-left:80px;padding:100px 24px 24px}.perfil-header-prueba{left:80px}}@media(max-width:767px){.perfil-prueba{margin-left:0;padding:110px 16px 16px}.perfil-header-prueba{left:0}}</style></head><body><header class="top-header perfil-header-prueba"><div class="header-left"><h1 class="top-header-title">Mi perfil</h1></div><div class="header-right"><button type="button">Notificaciones</button><button type="button">Fabio</button></div></header><main class="perfil-prueba">${html}</main></body></html>` });
            await nav.evaluar(`window.__consultas=0;window.fetch=()=>{window.__consultas++;throw Error('Esta prueba no consulta el servidor');};`);
            await nav.evaluar(fs.readFileSync('public/js/perfil.js', 'utf8'));
            await nav.evaluar(fs.readFileSync('public/js/ajustes.js', 'utf8'));
            await nav.evaluar(fs.readFileSync('public/js/dialogos-zooki.js', 'utf8'));
            assert.equal(await nav.evaluar(`document.querySelector('[aria-selected="true"]').dataset.configTab`), 'cuenta');
            assert.deepEqual(await nav.evaluar(`[...document.querySelectorAll('[data-config-tab]')].map(t=>t.dataset.configTab)`), rol === 'vet' ? ['cuenta', 'seguridad', 'horarios', 'ausencias'] : ['cuenta', 'seguridad']);
            const clases = rol === 'vet' ? ['cuenta', 'seguridad', 'horarios', 'ausencias'] : ['cuenta', 'seguridad'];
            if (rol === 'vet') {
                await nav.evaluar(`window.fetch=async(url)=>{__consultas++;return {ok:true,json:async()=>url.includes('listar_ausencias')?{success:true,ausencias:[]} : ({success:true,id_veterinario:2,veterinarios:[{id_usuario:2,nombre_completo:'Fabio'}],franjas:[1,2,3,4,5].map(d=>({dia_semana:d,hora_inicio:'08:00:00',hora_fin:'17:00:00'})),propuestas:[],cierres:[],afectadas:[]})};};`);
                await nav.evaluar(fs.readFileSync('public/js/configuracion-clinica.js','utf8'));
                await nav.evaluar(fs.readFileSync('public/js/time-picker.js','utf8'));
                await nav.evaluar(fs.readFileSync('public/js/horario-trabajo.js','utf8'));
                await nav.evaluar(fs.readFileSync('public/js/coordinacion-horarios.js','utf8'));
                await nav.esperar(`document.querySelector('[data-resumen-semana]').children.length===7`);
                if(calendarioReal) {
                    await nav.evaluar(fs.readFileSync('scratch/flatpickr.min.js','utf8'));
                    await nav.evaluar(fs.readFileSync('scratch/flatpickr-es.js','utf8'));
                    await nav.evaluar(fs.readFileSync('public/js/selector-fechas.js','utf8'));
                }
                await nav.evaluar(fs.readFileSync('public/js/ausencias-agenda.js','utf8'));
                await nav.esperar(`document.querySelector('[data-resumen-ausencia]').textContent==='Sin ausencias'`);
                await nav.esperar(`document.querySelectorAll('[data-dia-semana]').length===7`);
                await nav.evaluar('window.__consultas=0;');
            }
            for (const width of [1920, 1366, 900, 390, 360]) {
                await nav.enviar('Emulation.setDeviceMetricsOverride', { width, height: width === 1920 ? 1080 : width === 1366 ? 768 : 844, deviceScaleFactor: 1, mobile: width < 768 });
                assert.equal(await nav.evaluar("!!document.querySelector('.perfil-salir').closest('.ajustes-lateral')"), true);
                for (const clave of clases) {
                    const resultado = await nav.evaluar(`(() => {
                        document.querySelector('[data-config-tab="${clave}"]').click();
                        const lateral=getComputedStyle(document.querySelector('.perfil-lateral'));
                        const cuenta=document.querySelector('[data-config-panel="${clave}"]');
                        const rect=cuenta.getBoundingClientRect();
                        return {horizontal:document.documentElement.scrollWidth<=innerWidth,pagina:document.documentElement.scrollHeight<=innerHeight,hash:location.hash,visible:!cuenta.hidden,sticky:lateral.position,ocultos:[...document.querySelectorAll('[data-config-panel]')].filter(p=>p.dataset.configPanel!=='${clave}').every(p=>p.hidden),datosMovil:getComputedStyle(document.querySelector('.perfil-hero__datos')).display,seguridad:getComputedStyle(document.querySelector('.perfil-seguridad')).gridTemplateColumns,rect:[rect.top,rect.bottom]};
                    })()`);
                    assert.equal(resultado.horizontal, true, JSON.stringify({ rol, width, clave, resultado }));
                    if (width >= 1024) assert.equal(resultado.pagina, true, JSON.stringify({ rol, width, clave, resultado }));
                    assert.equal(resultado.hash, '#' + clave);
                    assert.equal(resultado.visible, true);
                    assert.equal(resultado.ocultos, true);
                    if(clave==='horarios' && width>=1024) {
                        const cabecera=await nav.evaluar(`(() => {
                            const boton=document.querySelector('[data-horario-varios]');
                            const titulo=document.getElementById('coordVetTitulo');
                            return {dentro:!!boton.closest('.coord-card__head'),alineado:Math.abs(boton.getBoundingClientRect().top-titulo.getBoundingClientRect().top)<5};
                        })()`);
                        assert.deepEqual(cabecera,{dentro:true,alineado:true});
                    }
                    assert.equal(resultado.sticky, width >= 1024 ? 'sticky' : 'static');
                    if (width < 768) assert.equal(resultado.datosMovil, 'none');
                    const pulido = await nav.evaluar(`(() => {
                        const menu=document.querySelector('.perfil-menu');
                        const salir=document.querySelector('.perfil-salir');
                        const campo=document.getElementById('perfilTelefono');
                        const boton=document.getElementById('perfilContactoGuardar');
                        const titulo=document.querySelector('.top-header-title');
                        return {menuCompleto:menu.scrollWidth<=menu.clientWidth,seguridadSalida:!!salir.closest('#seguridad'),alto:getComputedStyle(campo).height,radio:getComputedStyle(campo).borderRadius,titulo:getComputedStyle(titulo).whiteSpace,boton:boton.getBoundingClientRect().width,formulario:document.getElementById('perfilContactoForm').getBoundingClientRect().width};
                    })()`);
                    assert.equal(pulido.seguridadSalida,false);
                    if (width<768) assert.equal(pulido.menuCompleto,true,'Las cuatro pestañas caben sin recortarse');
                    assert.equal(pulido.alto,'42px');
                    assert.equal(pulido.radio,'10px');
                    assert.equal(pulido.titulo,'nowrap');
                    if (clave==='cuenta') assert.ok(pulido.boton<pulido.formulario);
                    if (process.env.ZOOKI_CAPTURA_PERFIL === '1') {
                        const captura=await nav.enviar('Page.captureScreenshot',{format:'png'});
                        fs.writeFileSync(`scratch/perfil-${rol}-${width}-${clave}.png`,Buffer.from(captura.data,'base64'));
                    }
                }
                // Cada ventana sobre la vista real: proporciones, pie blanco y ningún desborde.
                const ventanas = rol === 'vet' ? ['dialogoIdentidadCorreo','dialogoIdentidadDocumento','dialogoAusencia','vet-modalMultiDia'] : ['dialogoIdentidadCorreo','dialogoIdentidadDocumento'];
                for (const id of ventanas) {
                    const ventana = await nav.evaluar(`(() => {
                        const seccion='${id}'.includes('Ausencia')?'ausencias':'${id}'.includes('MultiDia')?'horarios':'cuenta';
                        document.querySelector('[data-config-tab="'+seccion+'"]').click();
                        const abrir='${id}'.includes('MultiDia')?document.querySelector('[data-horario-varios]'):document.querySelector('[data-abrir-dialogo="${id}"]');
                        abrir.click();
                        const dialogo=document.getElementById('${id}');
                        const r=dialogo.getBoundingClientRect();
                        const ojo=dialogo.querySelector('[data-ver]');
                        let ver=true;
                        if(ojo){ojo.click();ver=document.getElementById(ojo.dataset.ver).type==='text';ojo.click();ver=ver&&document.getElementById(ojo.dataset.ver).type==='password';}
                        return {abierto:dialogo.open,ancho:r.width,limites:r.left>=0&&r.right<=innerWidth&&r.top>=0&&r.bottom<=innerHeight,icono:!!dialogo.querySelector('.zk-dialogo__icono'),pie:getComputedStyle(dialogo.querySelector('.zk-dialogo__pie')).backgroundColor,ver};
                    })()`);
                    assert.equal(ventana.abierto,true);
                    assert.equal(ventana.limites,true,JSON.stringify({width,id,ventana}));
                    assert.equal(ventana.icono,true);
                    assert.equal(ventana.pie,'rgb(255, 255, 255)');
                    assert.equal(ventana.ver,true);
                    if(width>=768) assert.ok(ventana.ancho<=520);
                    if(process.env.ZOOKI_CAPTURA_PERFIL==='1') {
                        const captura=await nav.enviar('Page.captureScreenshot',{format:'png'});
                        fs.writeFileSync(`scratch/perfil-${rol}-${width}-${id}.png`,Buffer.from(captura.data,'base64'));
                    }
                    await nav.evaluar(`document.getElementById('${id}').close();`);
                }
                if(rol==='vet' && calendarioReal) {
                    const calendario=await nav.evaluar(`(() => {
                        document.querySelector('[data-config-tab="ausencias"]').click();
                        document.querySelector('[data-abrir-dialogo="dialogoAusencia"]').click();
                        const campo=document.querySelector('[data-form-ausencia] [name="inicio"]');
                        const fp=campo._flatpickr;
                        fp.open();
                        const r=fp.calendarContainer.getBoundingClientRect();
                        const diasCompletos=[...fp.calendarContainer.querySelectorAll('.flatpickr-day')].every(d=>d.getBoundingClientRect().right<=r.right);
                        fp.setDate('2030-01-07T09:00:30',true);
                        fp.calendarContainer.querySelector('.flatpickr-current-month').click();
                        const futuroPermitido=[...fp.calendarContainer.querySelectorAll('.win11-grid:not(.win11-years) button')].every(b=>!b.disabled);
                        const valor=campo.value;
                        const dentro=!!fp.calendarContainer.closest('dialog[open]');
                        fp.close();
                        document.getElementById('dialogoAusencia').close();
                        return {dentro,valor,nativo:campo.type==='datetime-local',limites:r.left>=0&&r.right<=innerWidth,meses:!!fp.calendarContainer.querySelector('.fp-win11-nav'),futuroPermitido,diasCompletos};
                    })()`);
                    assert.deepEqual(calendario,{dentro:true,valor:'2030-01-07T09:00:30',nativo:false,limites:true,meses:true,futuroPermitido:true,diasCompletos:true},JSON.stringify({width,calendario}));
                }
            }
            const telefono = await nav.evaluar(`(() => {
                const t=document.getElementById('perfilTelefono');
                const b=document.getElementById('perfilContactoGuardar');
                const original=t.value;
                const cambiar=valor=>{t.value=valor;t.dispatchEvent(new Event('input'));return b.disabled;};
                return [cambiar(original),cambiar('1'),cambiar('3001112299'),cambiar(original)];
            })()`);
            assert.deepEqual(telefono,[true,true,false,true]);
            const actividad = await nav.evaluar(`(() => {
                document.querySelector('[data-config-tab="seguridad"]').click();
                const visibles=()=>[...document.querySelectorAll('.perfil-evento')].filter(e=>!e.hidden).length;
                const inicial=visibles();
                const alturaInicial=document.querySelector('.perfil-paginacion').getBoundingClientRect().top;
                document.querySelector('[data-actividad-siguiente]').click();
                const todos=visibles();
                const alturaFinal=document.querySelector('.perfil-paginacion').getBoundingClientRect().top;
                document.querySelector('[data-actividad-anterior]').click();
                return {inicial,todos,final:visibles(),consultas:__consultas,estable:Math.abs(alturaFinal-alturaInicial)<1};
            })()`);
            assert.deepEqual(actividad, { inicial:5, todos:3, final:5, consultas:0,estable:true });
            const acceso = await nav.evaluar(`(() => {
                document.querySelector('[data-config-tab="cuenta"]').click();
                const filas=[...document.querySelectorAll('.perfil-campo-acceso')];
                const formas=[];
                for (const fila of filas) {
                    fila.querySelector('button').click();
                    const dialogo=document.querySelector('.perfil-dialogo[open]');
                    formas.push({abierto:!!dialogo,accion:dialogo.querySelector('form').dataset.identidadAccion,campos:dialogo.querySelectorAll('input').length});
                    dialogo.querySelector('[data-cerrar-dialogo]').click();
                }
                return {filas:filas.length,formas,cerrados:!document.querySelector('.perfil-dialogo[open]'),actual:document.getElementById('perfilPwdActual').placeholder,nueva:document.getElementById('perfilPwdNueva').placeholder,repite:document.getElementById('perfilPwdConfirmar').placeholder,marca:getComputedStyle(document.querySelector('[data-requisito]'),'::before').content};
            })()`);
            assert.equal(acceso.filas,2);
            assert.equal(acceso.formas[0].accion,'cambiar_documento_ajax');
            assert.equal(acceso.formas[1].accion,'solicitar_cambio_correo_ajax');
            assert.equal(acceso.cerrados,true);
            assert.deepEqual([acceso.actual,acceso.nueva,acceso.repite],['Tu contraseña actual','Nueva contraseña','Repite la nueva']);
            assert.match(acceso.marca,/✗/);
            // Las flechas recorren el menú lateral de escritorio; en el celular el menú se oculta al abrir una sección (RNF-16).
            await nav.enviar('Emulation.setDeviceMetricsOverride', { width: 1366, height: 768, deviceScaleFactor: 1, mobile: false });
            const teclado = await nav.evaluar(`(() => {
                const tab=document.querySelector('[data-config-tab="cuenta"]');
                tab.focus();
                tab.dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowDown',bubbles:true,cancelable:true}));
                return document.activeElement.dataset.configTab;
            })()`);
            assert.equal(teclado, 'seguridad');
            const arriba = await nav.evaluar(`(() => {
                document.activeElement.dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowUp',bubbles:true,cancelable:true}));
                return document.activeElement.dataset.configTab;
            })()`);
            assert.equal(arriba, 'cuenta');
            if (rol === 'vet') {
                const indicadores = await nav.evaluar(`(async () => {
                    document.querySelector('[data-propuestas]').innerHTML='<article class="coord-item"><strong>Fabio · Pendiente</strong></article>';
                    document.querySelector('[data-afectadas]').innerHTML='<article class="coord-item">Cita 12</article>';
                    await new Promise(r=>setTimeout(r,0));
                    const puntos=[...document.querySelectorAll('[data-perfil-pendiente]')].every(e=>!e.hidden);
                    document.querySelector('[data-config-tab="ausencias"]').click();
                    const orden=[...document.querySelectorAll('.coordinacion > section')].filter(e=>!e.hidden).map(e=>e.getAttribute('aria-labelledby'));
                    document.querySelector('[data-afectadas]').replaceChildren();
                    document.querySelector('[data-propuestas]').replaceChildren();
                    await new Promise(r=>setTimeout(r,0));
                    return {puntos,orden,vacio:document.querySelector('[data-afectadas]').closest('section').classList.contains('perfil-sin-afectadas'),puntosFuera:[...document.querySelectorAll('[data-perfil-pendiente]')].every(e=>e.hidden),cierresSoloLectura:!document.querySelector('.perfil-cierres button')};
                })()`);
                assert.deepEqual(indicadores, { puntos:true, orden:['ausenciasTitulo','coordAfectadasTitulo','coordCierresTitulo'], vacio:true, puntosFuera:true, cierresSoloLectura:true });
            }
            // Inicializar una página nueva con hash, además de cambiarlo tras cargar.
            await nav.evaluar(`history.replaceState(null,'','#seguridad');window.__zookiDialogos=false;`);
            await nav.evaluar(fs.readFileSync('public/js/dialogos-zooki.js','utf8'));
            assert.equal(await nav.evaluar(`!document.getElementById('seguridad').hidden&&document.querySelector('[data-config-tab="seguridad"]').getAttribute('aria-selected')==='true'`),true);
            const campos=await nav.evaluar(`(() => {const n=document.getElementById('perfilPwdNueva');n.value='Bosque2026';n.dispatchEvent(new Event('input'));return {validacion:document.getElementById('perfilContactoForm').hasAttribute('data-validacion-cuenta'),requisitos:document.querySelectorAll('[data-requisito].is-ok').length,acceso:document.querySelectorAll('[data-identidad-accion]').length};})()`);
            assert.deepEqual(campos,{validacion:true,requisitos:4,acceso:2});
            // HU-T.5: Ajustes no muestra el correo en una cabecera de perfil; guardar contacto debe seguir funcionando.
            const guardado = await nav.evaluar(`(async () => {
                if (${JSON.stringify(rol)} === 'admin') document.getElementById('perfilEmailHero').remove();
                const llamadas = [];
                window.fetch = async (url, opciones) => {
                    llamadas.push({ url, telefono: opciones.body.get('telefono') });
                    return { json: async () => ({ success: true }) };
                };
                const telefono = document.getElementById('perfilTelefono');
                telefono.value = '3001112299';
                telefono.dispatchEvent(new Event('input'));
                document.getElementById('perfilContactoForm').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
                await new Promise(r => setTimeout(r, 0));
                return { llamadas, mensaje: document.getElementById('perfilContactoMsg').textContent, guardado: document.getElementById('perfilContactoGuardar').disabled };
            })()`);
            assert.deepEqual(guardado, { llamadas: [{ url: 'index.php?action=actualizar_mi_perfil_ajax', telefono: '3001112299' }], mensaje: 'Datos actualizados.', guardado: true });
        } finally {
            await nav.cerrar();
        }
    });
}
