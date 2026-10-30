const test = require('node:test');
const assert = require('node:assert/strict');
const { servidorAgendaVet, fecha } = require('./apoyo/agenda-vet-servidor.cjs');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');

async function conAgenda(ejecutar) {
    const servidor = await servidorAgendaVet();
    const nav = await abrirChrome(buscarChrome());
    try {
        await nav.enviar('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
        await nav.enviar('Page.navigate', { url: servidor.url });
        await nav.esperar("document.querySelectorAll('[data-agenda-evento]').length === 6");
        // Estas regresiones verifican las hojas; RN-430 se prueba con su plazo en CorreccionesDueno230.
        await nav.evaluar("const agenda = document.querySelector('.agenda'); const contexto = JSON.parse(agenda.dataset.contextoAgenda); contexto.anticipacion_cambios_min = 0; agenda.dataset.contextoAgenda = JSON.stringify(contexto)");
        await ejecutar(nav, servidor);
        assert.deepEqual(nav.errores, []);
    } finally {
        await nav.cerrar();
        await servidor.cerrar();
    }
}
const dejarPintar = nav => nav.evaluar('new Promise(r => setTimeout(r, 100))');
async function tab(nav, clave) {
    await nav.evaluar(`document.querySelector('[data-agenda-tab="${clave}"]').click()`);
    await dejarPintar(nav);
}

test('Agenda móvil: pestañas, conteos, misma fuente y filtros, semana cruzando mes y hoja del mes', { skip: !buscarChrome() }, async () => {
    await conAgenda(async (nav, servidor) => {
        const inicial = await nav.evaluar(`({ claves: Array.from(document.querySelectorAll('[data-agenda-tab]'), n => n.dataset.agendaTab), conteos: Array.from(document.querySelectorAll('[data-agenda-conteo]'), n => n.textContent), filas: Array.from(document.querySelectorAll('[data-agenda-evento]'), n => n.dataset.agendaEvento), fuente: calendarInstance.getEvents().map(e => e.id), puntos: Array.from(document.querySelectorAll('.agenda-prioridad'), n => n.dataset.nivel), dias: document.querySelectorAll('[data-inicio-semana-fecha]').length })`);
        assert.deepEqual(inicial.claves, ['agenda', 'triage', 'llegadas', 'reasignaciones', 'urgencias']);
        assert.deepEqual(inicial.conteos, ['6', '1', '3', '1', '1']);
        assert.deepEqual(inicial.filas, inicial.fuente);
        assert.deepEqual(inicial.puntos, ['naranja', 'amarillo']);
        assert.equal(inicial.dias, 7);
        await nav.evaluar(`document.querySelector('[data-abrir-dialogo="agendaFiltrosMovil"]').click(); document.querySelector('[data-tipo="vacunacion"].filter-btn').click();`);
        const filtradas = await nav.evaluar(`({ ids: Array.from(document.querySelectorAll('[data-agenda-evento]'), n => n.dataset.agendaEvento), fuente: calendarInstance.getEvents().filter(e => FilterManager.esVisible(e)).map(e => e.id), punto: !document.querySelector('[data-agenda-filtros-activos]').hidden })`);
        assert.deepEqual(filtradas.ids, filtradas.fuente);
        assert.equal(filtradas.ids.length, 5);
        assert.equal(filtradas.punto, true);
        await nav.evaluar(`document.getElementById('agendaFiltrosMovil').close(); document.querySelector('[data-tipo="vacunacion"].filter-btn').click();`);
        await nav.evaluar(`calendarInstance.gotoDate(new Date('2030-01-31T12:00:00')); seleccionarDia(new Date('2030-01-31T12:00:00'));`);
        await nav.esperar(`document.querySelector('[data-inicio-semana-fecha="2030-02-03"]') !== null`);
        await nav.evaluar(`document.querySelector('[data-inicio-semana-paso="1"]').click()`);
        assert.equal(await nav.evaluar('toLocalDateStr(_selectedDate)'), '2030-02-07');
        await nav.evaluar(`document.querySelector('[data-inicio-calendario-abrir]').click(); document.querySelector('[data-agenda-mes-paso="1"]').click();`);
        await nav.esperar(`document.querySelector('[data-agenda-mes-fecha="2030-03-10"]') !== null`);
        await nav.evaluar(`document.querySelector('[data-agenda-mes-fecha="2030-03-10"]').click()`);
        assert.equal(await nav.evaluar('toLocalDateStr(_selectedDate)'), '2030-03-10');
        assert.ok(servidor.llamadas.some(l => l.accion === 'listar_citas_ajax' && l.consulta.includes('2030-03')));
        await nav.evaluar(`document.querySelector('[data-agenda-hoy]').click()`);
        await nav.esperar("document.querySelectorAll('[data-agenda-evento]').length === 6");
        await nav.evaluar(`document.querySelector('[data-agenda-evento="3"]').click()`);
        assert.equal(await nav.evaluar("document.getElementById('agendaDetalleMovil').open"), true);
        assert.match(await nav.evaluar("document.getElementById('agendaPanel').textContent"), /Luna/);
        await nav.evaluar(`document.querySelector('#agendaPanel [data-accion="reprogramar"]').click()`);
        await nav.esperar("document.querySelector('#reprogramar_slots_container .slot-chip') !== null");
        const reprog = await nav.evaluar(`(()=>{const d=document.querySelector('#reprogramarModalOverlay .zk-modal').getBoundingClientRect();return {detalleCerrado:!document.getElementById('agendaDetalleMovil').open, hoja:d.bottom<=innerHeight&&d.width===innerWidth, hora:document.querySelector('#reprogramar_slots_container .slot-chip').textContent}})()`);
        assert.deepEqual(reprog, { detalleCerrado: true, hoja: true, hora: '9:00 a. m.Recomendado' });
        await nav.evaluar('cerrarModalReprogramar()');
    });
});

test('Agenda móvil: revisión conserva huella y POST; llegadas y reasignaciones conservan sus comandos', { skip: !buscarChrome() }, async () => {
    await conAgenda(async (nav, servidor) => {
        await tab(nav, 'triage');
        await nav.evaluar(`document.querySelector('[data-triage-revisar]').click()`);
        const revision = await nav.evaluar(`({ abierta: document.getElementById('agendaRevisionMovil').open, niveles: Array.from(document.querySelectorAll('[data-agenda-revision-niveles] button'), b => ({ nivel:b.dataset.nivel, alto:b.getBoundingClientRect().height, seleccionado:b.getAttribute('aria-pressed') })), bloqueado: document.querySelector('#agendaRevisionMovil [type="submit"]').disabled })`);
        assert.equal(revision.abierta, true);
        assert.equal(revision.bloqueado, true);
        assert.equal(revision.niveles.length, 4);
        assert.ok(revision.niveles.every(n => n.alto >= 44));
        assert.equal(revision.niveles.find(n => n.seleccionado === 'true').nivel, 'naranja');
        await nav.evaluar(`document.getElementById('agendaRevisionMotivo').dispatchEvent(new Event('input',{bubbles:true}));`);
        assert.match(await nav.evaluar("document.querySelector('#agendaRevisionMovil .ah-error').textContent"), /obligatorio/);
        await nav.evaluar(`document.querySelector('[data-agenda-revision-niveles] [data-nivel="amarillo"]').click(); const motivo=document.getElementById('agendaRevisionMotivo'); motivo.value='Revisión clínica presencial'; motivo.dispatchEvent(new Event('input',{bubbles:true})); document.querySelector('#agendaRevisionMovil [type="submit"]').click();`);
        await nav.esperar("!document.getElementById('agendaRevisionMovil').open");
        const revisionPost = servidor.llamadas.find(l => l.accion === 'revisar_triage_agenda_ajax');
        const datos = new URLSearchParams(revisionPost.cuerpo);
        assert.equal(datos.get('id_solicitud'), '7');
        assert.equal(datos.get('huella'), 'huella-original-prueba');
        assert.equal(datos.get('nivel'), 'amarillo');
        assert.equal(datos.get('motivo'), 'Revisión clínica presencial');
        assert.match(datos.get('clave_operacion'), /^[a-f0-9]{64}$/);
        await tab(nav, 'llegadas');
        await nav.evaluar(`document.querySelector('[data-llegadas-citas] form [type="submit"]').click()`);
        await nav.esperar("document.querySelector('[data-llegadas-citas] .ah-decisiones') !== null");
        assert.ok(servidor.llamadas.some(l => l.accion === 'registrar_llegada_ajax' && /id_cita[\s\S]*3/.test(l.cuerpo) && l.cuerpo.includes('csrf_token')));
        await nav.evaluar(`document.querySelector('[data-llegadas-citas] .ah-decisiones form [type="submit"]').click()`);
        await dejarPintar(nav);
        assert.ok(servidor.llamadas.some(l => l.accion === 'decidir_llegada_ajax' && l.cuerpo.includes('completa')));
        await tab(nav, 'reasignaciones');
        await nav.evaluar(`document.querySelector('[data-reasignaciones-lista] button').click()`);
        await dejarPintar(nav);
        assert.ok(servidor.llamadas.some(l => l.accion === 'responder_reasignacion_ajax' && l.cuerpo.includes('aceptar') && l.cuerpo.includes('clave_operacion')));
    });
});

test('Nueva cita móvil: tres pasos, cálculo real, volver, fecha, un primario y bloqueo del doble envío', { skip: !buscarChrome() }, async () => {
    await conAgenda(async (nav, servidor) => {
        await nav.evaluar(`abrirCitaModal(new Date('${fecha(1)}T12:00:00'))`);
        await nav.esperar("document.querySelector('#modal_tipo_cita option[value=" + '"1"' + "]') !== null");
        assert.equal(await nav.evaluar('pasoCitaModal'), 0);
        const primarios = () => nav.evaluar(`Array.from(document.querySelectorAll('[data-cita-pie]>button')).filter(b=>b.getBoundingClientRect().height>0 && getComputedStyle(b).backgroundColor==='rgb(0, 82, 255)').map(b=>b.id || b.textContent.trim())`);
        assert.deepEqual(await primarios(), ['cm_siguiente']);
        await nav.evaluar(`seleccionarMascota('1','Doki','Fabio Propietario'); val('modal_tipo_cita','1'); onModalTipoCitaChange(); document.getElementById('cm_siguiente').click();`);
        assert.equal(await nav.evaluar('pasoCitaModal'), 1);
        assert.equal((await primarios()).length, 1);
        await nav.evaluar(`document.querySelector('[data-triage-otro]').click(); document.querySelector('#citaModalOverlay [data-triage-calcular]').click();`);
        await nav.esperar("triageModalC4?.consulta() === 123");
        assert.deepEqual(await primarios(), ['cm_siguiente']);
        await nav.evaluar(`document.getElementById('cm_siguiente').click()`);
        assert.equal(await nav.evaluar('pasoCitaModal'), 2);
        await nav.esperar("document.querySelector('#modal_slots_container .slot-chip') !== null");
        assert.equal(await nav.evaluar("document.querySelector('#modal_slots_container .slot-chip').firstChild.textContent"), '9:00 a. m.');
        await nav.evaluar(`document.querySelector('#modal_slots_container .slot-chip').click()`);
        assert.match(await nav.evaluar("document.getElementById('cm_horario_resumen').textContent"), /Amarillo · 9:00 a\. m\./);
        assert.deepEqual(await primarios(), ['cm_submit']);
        await nav.evaluar(`document.getElementById('cm_anterior').click(); document.getElementById('cm_siguiente').click();`);
        assert.equal(await nav.evaluar("val('modal_hora')"), '09:00:30');
        await nav.evaluar(`document.querySelector('[data-cita-cambiar-dia]').click()`);
        assert.equal(await nav.evaluar("document.getElementById('agendaMesMovil').open"), true);
        await nav.enviar('Input.dispatchKeyEvent', { type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27,nativeVirtualKeyCode:27 });
        assert.equal(await nav.evaluar("document.getElementById('citaModalOverlay').classList.contains('is-open')"), true);
        await nav.evaluar(`document.querySelector('[data-cita-cambiar-dia]').click(); if(!document.querySelector('[data-agenda-mes-fecha="${fecha(2)}"]')) document.querySelector('[data-agenda-mes-paso="1"]').click(); document.querySelector('[data-agenda-mes-fecha="${fecha(2)}"]').click();`);
        assert.equal(await nav.evaluar('pasoCitaModal'), 1, 'Un día distinto vuelve a validar la evaluación con el nuevo contexto');
        assert.equal(await nav.evaluar('triageModalC4.consulta()'), '');
        assert.equal(await nav.evaluar("val('modal_fecha')"), fecha(2));
        await nav.evaluar(`document.querySelector('#citaModalOverlay [data-triage-calcular]').click()`);
        await nav.esperar('triageModalC4.consulta() === 123');
        await nav.evaluar(`document.getElementById('cm_siguiente').click()`);
        await nav.esperar("document.querySelector('#modal_slots_container .slot-chip') !== null");
        await nav.evaluar(`document.querySelector('#modal_slots_container .slot-chip').click()`);
        servidor.retener(true);
        await nav.evaluar(`document.getElementById('cm_submit').click(); document.getElementById('cm_submit').click(); document.getElementById('cm_anterior').click();`);
        await nav.esperar("enviosC3.reserva === true");
        await dejarPintar(nav);
        assert.equal(servidor.llamadas.filter(l=>l.accion==='registrar_cita_ajax').length, 1);
        assert.equal(await nav.evaluar('pasoCitaModal'), 2);
        servidor.retener(false);
        servidor.pendientes.splice(0).forEach(enviar => enviar());
        await nav.esperar('enviosC3.reserva === false');
    });
});

test('RNF-16: Agenda y hojas sin desborde a 360/390; composición de tableta/escritorio y hora legible', { skip: !buscarChrome() }, async () => {
    await conAgenda(async (nav) => {
        for (const width of [360, 390, 820, 1366]) {
            await nav.enviar('Emulation.setDeviceMetricsOverride', { width, height: width === 820 ? 1180 : 844, deviceScaleFactor: 1, mobile: width < 768 });
            await dejarPintar(nav);
            for (const clave of ['agenda', 'triage', 'llegadas', 'reasignaciones', 'urgencias']) {
                if (width >= 768 && clave === 'agenda') continue;
                await tab(nav, clave);
                assert.equal(await nav.evaluar('document.documentElement.scrollWidth <= innerWidth'), true, width + '/' + clave);
            }
            await nav.evaluar(`abrirCitaModal(new Date('${fecha(1)}T12:00:00'));`);
            await nav.esperar("document.querySelector('#modal_tipo_cita option[value=" + '"1"' + "]') !== null");
            await nav.evaluar(`seleccionarMascota('1','Doki','Fabio Propietario'); val('modal_tipo_cita','1'); triageModalC4={ consulta:()=>123, resultado:()=>({nivel:'amarillo',reservable:true}), reiniciar:()=>{} }; mostrarPasoCita(citaEsMovil()?2:1); cargarSlotsModal();`);
            await nav.esperar("document.querySelector('#modal_slots_container .slot-chip') !== null");
            await nav.evaluar(`document.querySelector('#modal_slots_container .slot-chip').click()`);
            const medidas = await nav.evaluar(`(()=>{const modal=document.querySelector('#citaModalOverlay .zk-modal').getBoundingClientRect();const pie=document.querySelector('#citaModalOverlay footer').getBoundingClientRect(); const left=document.querySelector('.cita-pasos').getBoundingClientRect(); const right=document.querySelector('.cita-horarios').getBoundingClientRect();return {desborde:document.documentElement.scrollWidth>innerWidth,modalDentro:modal.left>=0&&modal.right<=innerWidth,pieVisible:pie.bottom<=innerHeight, columnas:right.left>=left.right-1,hora:document.querySelector('.slot-chip').textContent,resumen:document.getElementById('cm_horario_resumen').textContent}})()`);
            assert.equal(medidas.desborde, false, width);
            assert.equal(medidas.modalDentro, true, width);
            assert.equal(medidas.pieVisible, true, width);
            assert.equal(medidas.hora, '9:00 a. m.Recomendado');
            assert.match(medidas.resumen, /Amarillo/);
            if (width >= 768) assert.equal(medidas.columnas, true, width);
            await nav.evaluar('closeCitaModal()');
        }
    });
});
