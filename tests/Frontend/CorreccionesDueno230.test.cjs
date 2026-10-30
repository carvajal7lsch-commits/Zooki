const test = require('node:test');
const assert = require('node:assert/strict');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');
const { servidorAgendaVet, fecha } = require('./apoyo/agenda-vet-servidor.cjs');
const { servidorPerfil } = require('./apoyo/perfil-comparacion-servidor.cjs');
const { servidorPacientes } = require('./apoyo/pacientes-movil-servidor.cjs');
const { servidorPortalMovil } = require('./apoyo/portal-movil-servidor.cjs');
const { servidorCitas } = require('./apoyo/citas-admin-servidor.cjs');

async function comprobar(servidor, width, ejecutar) {
    const nav = await abrirChrome(buscarChrome());
    try {
        await nav.enviar('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width < 768 });
        await nav.enviar('Page.navigate', { url: servidor.url });
        await nav.esperar("document.readyState === 'complete'");
        await ejecutar(nav, servidor);
        assert.deepEqual(nav.errores, []);
    } finally {
        await nav.cerrar();
        await servidor.cerrar();
    }
}

for (const width of [390, 1366]) {
    test(`01 horario propuesto bloquea fines de semana y limita horas a la apertura a ${width}`, { skip: !buscarChrome() }, async () => {
        await comprobar(await servidorPerfil('vet'), width, async nav => {
            await nav.esperar("document.querySelector('[data-veterinario]').value === '2'");
            await nav.evaluar("document.querySelector('[data-config-tab=horarios]').click()");
            assert.equal(await nav.evaluar("document.querySelector('[data-dia-semana=\"6\"] [data-dia-activo]').disabled"), true);
            assert.equal(await nav.evaluar("document.querySelector('[data-dia-semana=\"7\"] [data-dia-activo]').disabled"), true);
            assert.equal(await nav.evaluar("document.querySelector('[data-dia-semana=\"1\"] .morning-inicio').min"), '08:00');
        });
    });

    test(`02 Nueva cita avisa antes de llenar si no hay horario a ${width}`, { skip: !buscarChrome() }, async () => {
        await comprobar(await servidorAgendaVet({ contextoAgenda: { franjas: [], apertura: [], cierres: [] } }), width, async nav => {
            await nav.esperar("typeof abrirCitaModal === 'function' && calendarInstance !== null");
            await nav.evaluar(`abrirCitaModal(new Date('${fecha(1)}T12:00:00'))`);
            assert.ok(await nav.evaluar("document.body.textContent.includes('Aún no tienes horario aprobado: propón tu horario en Mi perfil')"));
            assert.equal(await nav.evaluar("document.querySelector('#citaModalOverlay').classList.contains('is-open')"), false);
        });
    });

    test(`03 día cerrado no añade ni recibe reservas; conserva la consulta a ${width}`, { skip: !buscarChrome() }, async () => {
        const contextoAgenda = { franjas: [{ dia_semana: 1, hora_inicio: '08:00', hora_fin: '12:00' }], apertura: [], cierres: [] };
        await comprobar(await servidorAgendaVet({ contextoAgenda }), width, async nav => {
            await nav.esperar('calendarInstance !== null');
            const botones = await nav.evaluar(`(() => {
                const celda = document.createElement('div');
                celda.innerHTML = '<div class="fc-daygrid-day-top"></div>';
                mountDayAddButton({ date: new Date('${fecha(1)}T12:00:00'), view: { type: 'dayGridMonth' }, el: celda });
                return celda.querySelectorAll('.fc-day-add-btn').length;
            })()`);
            assert.equal(botones, 0);
            assert.equal(await nav.evaluar(`(calendarInstance.getOption ? calendarInstance.getOption('eventAllow') : calendarInstance.o.eventAllow)({ start: new Date('${fecha(1)}T12:00:00') })`), false);
            await nav.esperar("calendarInstance.getEvents().length === 6");
            await nav.evaluar(`seleccionarDia(new Date('${fecha(0)}T12:00:00'))`);
            assert.equal(await nav.evaluar("document.querySelector('#agendaPanel').textContent.includes('Luna')"), true);
            if (width < 768) {
                await nav.evaluar("document.querySelector('[data-inicio-calendario-abrir]').click()");
                assert.equal(await nav.evaluar(`document.querySelector('[data-agenda-mes-fecha="${fecha(0)}"]').disabled`), false, 'El día cerrado con eventos permite consulta');
            }
        });
    });

    test(`05 obligatorios y opcionales en ambos formularios de mascota a ${width}`, { skip: !buscarChrome() }, async () => {
        await comprobar(await servidorPacientes(), width, async nav => {
            const textos = await nav.evaluar("Array.from(document.querySelectorAll('form[data-validacion-mascota]'), f => f.textContent.replace(/\\s+/g, ' '))");
            assert.equal(textos.length, 2);
            for (const texto of textos) {
                assert.match(texto, /Nombre de la Mascota\s*\*/);
                assert.match(texto, /Peso \(Kg\)\s*\*/);
                assert.match(texto, /Raza\s*\*/);
                assert.match(texto, /Fecha Nacimiento\s*\*/);
                assert.match(texto, /Esterilización\s*\(opcional\)/);
                assert.ok(texto.includes('* Obligatorio. Lo demás es opcional.'));
            }
        });
    });

    test(`07 horarios muestran el más cercano Recomendado a ${width}`, { skip: !buscarChrome() }, async () => {
        await comprobar(await servidorAgendaVet(), width, async nav => {
            await nav.esperar("typeof renderSlots === 'function'");
            await nav.evaluar("renderSlots('modal_slots_container', ['09:00:00', '10:00:00', '11:00:00'], () => {})");
            assert.equal(await nav.evaluar("document.querySelector('#modal_slots_container .slot-chip').textContent.includes('Recomendado')"), true);
        });
    });

    test(`08 rojo abre llegada con mascota y evaluación conservadas a ${width}`, { skip: !buscarChrome() }, async () => {
        await comprobar(await servidorAgendaVet(), width, async nav => {
            await nav.esperar("document.querySelector('[data-ingreso-rojo] select[name=id_mascota] option[value=\"1\"]') !== null");
            await nav.evaluar(`
                val('modal_mascota', '1');
                triageModalC4 = { resultado: () => ({ nivel: 'rojo', id_solicitud: 123 }), resumen: () => 'Dificultad respiratoria' };
                irAUrgenciasDesdeCita();
            `);
            await new Promise(resolve => setTimeout(resolve, 100));
            assert.equal(await nav.evaluar("document.querySelector('#dialogoRojo').open"), true);
            assert.equal(await nav.evaluar("document.querySelector('[data-ingreso-rojo] [name=id_mascota]').value"), '1');
            assert.equal(await nav.evaluar("document.querySelector('[data-ingreso-rojo] [name=id_solicitud]')?.value"), '123');
        });
    });

    test(`09 administrador exige motivo antes de cancelar a ${width}`, { skip: !buscarChrome() }, async () => {
        await comprobar(await servidorCitas(), width, async (nav, servidor) => {
            await nav.esperar("todasLasCitas.length === 10");
            await nav.evaluar("verDetalle(1)");
            assert.equal(await nav.evaluar("document.querySelector('[data-cancelar-cita-admin] [type=submit]').disabled"), true);
            await nav.evaluar("const f = document.querySelector('[data-cancelar-cita-admin]'); f.elements.motivo.value = 'Solicitud presencial'; f.elements.motivo.dispatchEvent(new Event('input', { bubbles: true })); window.zookiConfirmarCorto = async () => true; f.requestSubmit()");
            await nav.esperar("!document.querySelector('#citasDetalle').open");
            const comando = servidor.llamadas.find(l => l.accion === 'cancelar_cita_ajax');
            assert.ok(comando);
            const datos = new URLSearchParams(comando.cuerpo);
            assert.equal(datos.get('motivo'), 'Solicitud presencial');
            assert.equal(datos.get('id_cita'), '1');
            assert.ok(datos.get('csrf_token'));
        });
    });

    for (const portal of [false, true]) {
        for (const nivel of ['verde', 'amarillo', 'naranja']) {
            test(`07 ${portal ? 'portal' : 'Nueva cita'} ${nivel} conserva elección y recomendado a ${width}`, { skip: !buscarChrome() }, async () => {
                const mensaje = nivel === 'naranja' ? 'Nivel naranja: se le asigna el primer horario libre para atenderlo cuanto antes' : 'Nivel provisional: un veterinario lo confirmará. Ya puedes elegir horario.';
                const triage = { success: true, id_solicitud: 123, huella: 'vigente', nivel, reservable: true, message: mensaje };
                const horas = ['09:00:00', '10:00:00', '11:00:00', '14:00:00', '15:00:00'].slice(0, nivel === 'naranja' ? 3 : 5);
                const servidor = portal ? await servidorPortalMovil({ triage, horas }) : await servidorAgendaVet({ triage, horas });
                await comprobar(servidor, width, async nav => {
                    if (portal) {
                        await nav.esperar("Boolean(document.querySelector('#portal_contact_phone').zookiTelefono)");
                        await nav.evaluar("document.querySelector('[data-agendar]').click(); document.querySelector('#portalBookingForm [name=id_clinica][value=\"1\"]').click(); document.querySelector('#portalBookingForm [name=id_mascota][value=\"1\"]').click()");
                        await nav.esperar("document.querySelector('#booking_tipo_cita option[value=\"1\"]') !== null");
                        await nav.evaluar(`document.querySelector('#booking_tipo_cita').value = '1'; document.querySelector('#booking_veterinario').value = '2'; document.querySelector('#booking_fecha')._flatpickr.setDate('${fecha(1)}', true); document.querySelector('#booking_tipo_cita').dispatchEvent(new Event('change', { bubbles: true }))`);
                    } else {
                        await nav.esperar("calendarInstance !== null");
                        await nav.evaluar(`abrirCitaModal(new Date('${fecha(1)}T12:00:00'))`);
                        await nav.esperar("document.querySelector('#modal_tipo_cita option[value=\"1\"]') !== null");
                        await nav.evaluar("seleccionarMascota('1', 'Doki', 'Fabio Propietario'); val('modal_tipo_cita', '1'); onModalTipoCitaChange(); document.querySelector('#cm_siguiente').click()");
                    }
                    const raiz = portal ? '#portalBookingModal' : '#citaModalOverlay';
                    await nav.evaluar(`document.querySelector('${raiz} [data-triage-otro]').click(); document.querySelector('${raiz} [data-triage-calcular]').click()`);
                    await nav.esperar(`document.querySelector('${raiz} [data-triage-resultado]').dataset.nivel === '${nivel}'`);
                    if (!portal) await nav.evaluar("document.querySelector('#cm_siguiente').click()");
                    const slots = portal ? '#booking_slots' : '#modal_slots_container';
                    await nav.esperar(`document.querySelectorAll('${slots} .slot-chip').length === ${horas.length}`);
                    assert.equal(await nav.evaluar(`document.querySelector('${slots} .slot-chip').textContent.includes('Recomendado')`), true);
                    const campo = portal ? '#booking_hora' : '#modal_hora';
                    if (nivel === 'naranja') {
                        assert.equal(await nav.evaluar(`document.querySelector('${campo}').value`), horas[0]);
                        assert.equal(await nav.evaluar(`Array.from(document.querySelectorAll('${slots} .slot-chip')).slice(1).every(b => b.disabled)`), true);
                        assert.ok(await nav.evaluar(`document.querySelector('${raiz} [data-triage-resultado]').textContent.includes('${mensaje}')`));
                    } else {
                        await nav.evaluar(`document.querySelectorAll('${slots} .slot-chip')[4].click()`);
                        assert.equal(await nav.evaluar(`document.querySelector('${campo}').value`), horas[4]);
                    }
                });
            });
        }
    }
}
