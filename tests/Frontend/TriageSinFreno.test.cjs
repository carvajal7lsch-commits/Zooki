const test = require('node:test');
const assert = require('node:assert/strict');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');
const { servidorAgendaVet, fecha } = require('./apoyo/agenda-vet-servidor.cjs');
const { servidorPortalMovil } = require('./apoyo/portal-movil-servidor.cjs');

const provisional = 'Nivel provisional: un veterinario lo confirmará. Ya puedes elegir horario.';

// RN-418: ejecutar el componente real y el consumidor de horarios de cada formulario.
for (const portal of [false, true]) {
    for (const caso of ['revision', 'inmediata', 'rojo']) {
        test(`RN-418 ${portal ? 'portal' : 'Nueva cita'}: ${caso}`, { skip: !buscarChrome() }, async () => {
            const rojo = caso === 'rojo';
            const triage = { success: true, id_solicitud: 123, huella: 'vigente', revision: !rojo,
                nivel: rojo ? 'rojo' : 'amarillo', reservable: !rojo,
                resultado: { revision_inmediata: caso === 'inmediata' },
                message: rojo ? 'Emergencia: contacta de inmediato a la clínica.' : provisional };
            const servidor = portal ? await servidorPortalMovil({ triage }) : await servidorAgendaVet({ triage });
            const nav = await abrirChrome(buscarChrome());
            const raiz = portal ? '#portalBookingModal' : '#citaModalOverlay';
            try {
                await nav.enviar('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
                await nav.enviar('Page.navigate', { url: servidor.url });
                if (portal) {
                    await nav.esperar("document.readyState === 'complete' && Boolean(document.querySelector('#portal_contact_phone').zookiTelefono)");
                    await nav.evaluar("document.querySelector('[data-agendar]').click()");
                    await nav.evaluar(`
                        document.querySelector('#portalBookingForm [name=id_clinica][value="1"]').click();
                        document.querySelector('#portalBookingForm [name=id_mascota][value="1"]').click();
                    `);
                    await nav.esperar("document.querySelector('#booking_tipo_cita option[value=\"1\"]') !== null");
                    await nav.evaluar(`
                        document.querySelector('#booking_tipo_cita').value = '1';
                        document.querySelector('#booking_veterinario').value = '2';
                        document.querySelector('#booking_fecha')._flatpickr.setDate('${fecha(1)}', true);
                        document.querySelector('#booking_tipo_cita').dispatchEvent(new Event('change', { bubbles: true }));
                    `);
                } else {
                    await nav.esperar("typeof abrirCitaModal === 'function' && document.querySelectorAll('[data-agenda-evento]').length === 6");
                    await nav.evaluar(`abrirCitaModal(new Date('${fecha(1)}T12:00:00'))`);
                    await nav.esperar("document.querySelector('#modal_tipo_cita option[value=\"1\"]') !== null");
                    await nav.evaluar(`
                        seleccionarMascota('1', 'Doki', 'Fabio Propietario');
                        val('modal_tipo_cita', '1');
                        onModalTipoCitaChange();
                        document.querySelector('#cm_siguiente').click();
                    `);
                }
                await nav.evaluar(`
                    document.querySelector('${raiz} [data-triage-otro]').click();
                    document.querySelector('${raiz} [data-triage-calcular]').click();
                `);
                await nav.esperar(`document.querySelector('${raiz} [data-triage-resultado]').dataset.nivel === '${triage.nivel}'`);
                const mensaje = await nav.evaluar(`document.querySelector('${raiz} [data-triage-resultado]').textContent`);
                assert.ok(mensaje.includes(triage.message));
                if (!portal && !rojo) {
                    assert.equal(await nav.evaluar("document.querySelector('#cm_siguiente').disabled"), false);
                    await nav.evaluar("document.querySelector('#cm_siguiente').click()");
                }
                const slots = portal ? '#booking_slots' : '#modal_slots_container';
                if (rojo) {
                    assert.equal(await nav.evaluar(`document.querySelectorAll('${slots} .slot-chip').length`), 0);
                    assert.equal(servidor.llamadas.filter(l => ['portal_get_sugerencias_ajax', 'get_sugerencias_horario_ajax'].includes(l.accion)).length, 0);
                } else {
                    await nav.esperar(`document.querySelector('${slots} .slot-chip') !== null`);
                    await nav.evaluar(`document.querySelector('${slots} .slot-chip').click()`);
                    const boton = portal ? '#booking_confirmar' : '#cm_submit';
                    assert.equal(await nav.evaluar(`document.querySelector('${boton}').disabled`), false);
                    assert.equal(servidor.llamadas.filter(l => l.accion === 'revisar_triage_agenda_ajax').length, 0);
                }
                assert.deepEqual(nav.errores, []);
            } finally {
                await nav.cerrar();
                await servidor.cerrar();
            }
        });
    }
}
