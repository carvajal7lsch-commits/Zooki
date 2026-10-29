const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { execFileSync } = require('node:child_process');
const { abrirChrome, buscarChrome } = require('./apoyo/chrome-cdp.cjs');

const fuente = fs.readFileSync('public/js/calendario.js', 'utf8');
function tramo(desde, hasta) {
    const inicio = fuente.indexOf(desde);
    const fin = fuente.indexOf(hasta, inicio);
    assert.ok(inicio >= 0 && fin > inicio);
    return fuente.slice(inicio, fin);
}

test('Nueva cita de escritorio: dos pasos, guía, resumen, Enter, rojo y horarios persistentes a 1366 px', { skip: !buscarChrome() }, async () => {
    const php = process.env.PHP_BINARY || (process.platform === 'win32' && fs.existsSync('C:/xampp/php/php.exe') ? 'C:/xampp/php/php.exe' : 'php');
    const vista = execFileSync(php, ['tests/Frontend/apoyo/agenda-cita-vista.php'], { encoding: 'utf8', env: { ...process.env, TRUSTED_PROXIES: '127.0.0.2', ZOOKI_TEST_MYSQL_HOST: '', ZOOKI_TEST_MYSQL_DB: 'zooki_test_base_v2' } });
    assert.doesNotMatch(vista, /Fatal error|Warning:|\s(?:style|onclick|onsubmit)=/);
    const html = vista.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '').replace(/<link\b[^>]*>/gi, '');
    const css = ['styles.css', 'usuarios.css', 'calendar-modules/base.css', 'calendar-modules/agenda.css', 'triage-captura.css', 'calendar-modules/modals.css'].map(p => `<style>${fs.readFileSync('public/css/' + p, 'utf8').replace(/@import[^;]+;/g, '')}</style>`).join('');
    const nav = await abrirChrome(buscarChrome());
    try {
        const arbol = await nav.enviar('Page.getFrameTree');
        await nav.enviar('Page.setDocumentContent', { frameId: arbol.frameTree.frame.id, html: `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">${css}</head><body>${html}</body></html>` });
        await nav.evaluar(fs.readFileSync('public/js/agenda-herramientas.js', 'utf8') + '\niniciarHerramientasAgenda(document);');
        await nav.evaluar(fs.readFileSync('public/js/comando-agenda.js', 'utf8'));
        await nav.evaluar(`
            window.__estado = { resultado: null, reservable: false };
            let triageModalC4 = { consulta: () => __estado.reservable ? '123' : '', resultado: () => __estado.resultado };
            let _turnoSlotsCita = 0;
            let _mascotaNombre = 'Doki';
            const enviosC3 = { reserva: false };
            window.__peticiones = 0;
            window.__envios = 0;
            function val(id, nuevo) {
                const el = document.getElementById(id);
                if (nuevo !== undefined) el.value = nuevo;
                return el.value;
            }
            function esc(s) {
                const el = document.createElement('div');
                el.textContent = s;
                return el.innerHTML;
            }
            function getVetModal() { return '2'; }
            function unirLista(a) { return a.join(' y '); }
            function format12h(h) { return h; }
            ${tramo('function formatearHoraReserva(', 'function formatHora(')}
            function minutosDe(h) { return Number(h.slice(0, 2)) * 60 + Number(h.slice(3, 5)); }
            function filtrarMascotas() {}
            function onModalVeterinarioChange() {}
            function cargarSlotsReprogramar() {}
            function marcarHoraPreferida() {}
            function closeCitaModal() { document.getElementById('citaModalOverlay').classList.remove('is-open'); }
            async function postCita() {
                __envios++;
                return new Promise(resolve => { window.__resolverEnvio = resolve; });
            }
            window.fetch = async () => {
                __peticiones++;
                return { json: async () => ({ success: true, sugerencias: ['09:00:30', '10:00:00'] }) };
            };
            ${tramo('function setSlotsEstado(', '/** Si la fecha es hoy')}
            ${tramo('function renderSlots(', '// ═')}
            ${tramo('function textoGuiaSlots(', 'function abrirCitaModal(date)')}
            ${tramo('function onModalTipoCitaChange(', '// ═')}
            ${tramo('function conectarFormulariosC3(', '// Clic o teclado')}
            conectarFormulariosC3();
            document.getElementById('modal_tipo_cita').innerHTML += '<option value="1" data-nombre="Consulta">Consulta</option>';
            val('modal_fecha', '2030-01-07');
        `);
        for (const width of [1366]) {
            await nav.enviar('Emulation.setDeviceMetricsOverride', { width, height: 844, deviceScaleFactor: 1, mobile: width < 768 });
            await nav.evaluar(`(async () => {
                document.getElementById('citaModalOverlay').classList.add('is-open');
                val('modal_mascota', '');
                val('modal_tipo_cita', '');
                __estado = { resultado: null, reservable: false };
                mostrarPasoCita(0, false);
                await cargarSlotsModal();
            })()`);
            let estado = await nav.evaluar(`({ guia: document.getElementById('modal_slots_container').textContent, falta: document.getElementById('cm_resumen').textContent, continuar: document.getElementById('cm_siguiente').disabled, indicador: document.querySelector('[data-cita-indicador="1"]').disabled })`);
            assert.match(estado.guia, /Elige mascota y tipo de cita/);
            assert.equal(estado.falta, 'Falta: mascota y tipo de cita.');
            assert.equal(estado.continuar, true);
            assert.equal(estado.indicador, true);
            await nav.evaluar(`(async () => {
                val('modal_mascota', '4');
                val('modal_tipo_cita', '1');
                await cargarSlotsModal();
                document.querySelector('[data-cita-indicador="1"]').click();
            })()`);
            estado = await nav.evaluar(`({ guia: document.getElementById('modal_slots_container').textContent, falta: document.getElementById('cm_resumen').textContent, cancelar: document.getElementById('cm_cancelar').hidden, atras: document.getElementById('cm_anterior').hidden, agendar: document.getElementById('cm_submit').disabled })`);
            assert.match(estado.guia, /Calcula el nivel de urgencia para ver horarios/);
            assert.equal(estado.falta, 'Falta: nivel de urgencia y horario.');
            assert.equal(estado.cancelar, true);
            assert.equal(estado.atras, false);
            assert.equal(estado.agendar, true);
            const colores = await nav.evaluar(`(() => {
                const calcular = getComputedStyle(document.querySelector('#formCitaModal [data-triage-calcular]'));
                const principal = getComputedStyle(document.getElementById('cm_siguiente'));
                return {
                    fondo: calcular.backgroundColor,
                    principal: principal.backgroundColor,
                    texto: calcular.color,
                    notaAusente: !document.querySelector('#formCitaModal .triage-captura__nota'),
                    accionesPaciente: document.getElementById('cm_siguiente').closest('[data-cita-paso]').dataset.citaPaso,
                    accionesUrgencia: document.getElementById('cm_anterior').closest('[data-cita-paso]').dataset.citaPaso,
                    envioEnPie: !!document.getElementById('cm_submit').closest('footer'),
                };
            })()`);
            assert.equal(colores.fondo, colores.principal, 'Calcular usa el azul de los botones principales');
            assert.equal(colores.texto, 'rgb(255, 255, 255)');
            assert.equal(colores.notaAusente, true, 'El texto del catálogo no se genera en Nueva cita');
            assert.equal(colores.accionesPaciente, '0');
            assert.equal(colores.accionesUrgencia, '1');
            assert.equal(colores.envioEnPie, true);
            await nav.evaluar(`(async () => {
                __estado = { resultado: { nivel: 'amarillo' }, reservable: true };
                await cargarSlotsModal();
                document.querySelector('[data-hora="09:00:30"]').click();
            })()`);
            estado = await nav.evaluar(`({ hora: val('modal_hora'), resumen: document.getElementById('cm_horario_resumen').textContent, deshabilitado: document.getElementById('cm_submit').disabled, falta: document.getElementById('cm_resumen').textContent })`);
            assert.deepEqual(estado, { hora: '09:00:30', resumen: 'Doki · Consulta · Amarillo · 9:00 a. m.', deshabilitado: false, falta: '' });
            for (const paso of [0, 1]) {
                const medidas = await nav.evaluar(`(() => {
                    document.querySelector('[data-cita-indicador="${paso}"]').click();
                    const izquierda = document.querySelector('.cita-pasos').getBoundingClientRect();
                    const derecha = document.querySelector('.cita-horarios').getBoundingClientRect();
                    document.querySelector('.cita-pasos__contenido').scrollTop = 10000;
                    const pie = document.querySelector('#citaModalOverlay .zk-modal__foot').getBoundingClientRect();
                    return { sinDesborde: document.documentElement.scrollWidth <= innerWidth, derecha: derecha.left >= izquierda.right - 1, visible: derecha.height > 0, pieVisible: pie.top >= 0 && pie.bottom <= innerHeight, hora: val('modal_hora'), falta: document.getElementById('cm_resumen').textContent };
                })()`);
                assert.equal(medidas.sinDesborde, true);
                assert.equal(medidas.visible, true);
                assert.equal(medidas.pieVisible, true, 'La navegación inferior permanece visible al desplazar el contenido');
                if (width >= 768) assert.equal(medidas.derecha, true);
                assert.equal(medidas.hora, '09:00:30');
                assert.equal(medidas.falta, '');
                if (process.env.ZOOKI_CAPTURA_PACIENTES === '1') {
                    const captura = await nav.enviar('Page.captureScreenshot', { format: 'png' });
                    fs.writeFileSync(`scratch/cita-dos-pasos-${width}-${paso}.png`, Buffer.from(captura.data, 'base64'));
                }
            }
            const enter = await nav.evaluar(`(() => {
                return ['modal_motivo', 'cm_submit'].every(id => {
                    const evento = new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true });
                    document.getElementById(id).dispatchEvent(evento);
                    return evento.defaultPrevented;
                }) && __envios === 0;
            })()`);
            assert.equal(enter, true);
            const rojo = await nav.evaluar(`(async () => {
                const antes = __peticiones;
                __estado = { resultado: { nivel: 'rojo' }, reservable: false };
                await cargarSlotsModal();
                const aviso = document.getElementById('modal_slots_container').textContent;
                const oculto = document.getElementById('cm_submit').hidden;
                const boton = !document.getElementById('cm_urgencias').hidden;
                document.getElementById('cm_urgencias').click();
                return { aviso, oculto, boton, sinConsulta: antes === __peticiones, cerrado: !document.getElementById('citaModalOverlay').classList.contains('is-open'), pestana: document.getElementById('agenda-tab-urgencias').getAttribute('aria-selected') };
            })()`);
            assert.match(rojo.aviso, /requiere atención de urgencias/);
            assert.deepEqual({ ...rojo, aviso: '' }, { aviso: '', oculto: true, boton: true, sinConsulta: true, cerrado: true, pestana: 'true' });
        }
        const envio = await nav.evaluar(`(async () => {
            __estado = { resultado: { nivel: 'verde' }, reservable: true };
            mostrarPasoCita(1, false);
            await cargarSlotsModal();
            document.querySelector('[data-hora="09:00:30"]').click();
            document.getElementById('cm_submit').click();
            document.getElementById('cm_submit').click();
            document.querySelector('[data-cita-indicador="0"]').click();
            const resultado = { envios: __envios, paso: pasoCitaModal, bloqueado: document.getElementById('cm_submit').disabled };
            __resolverEnvio({ success: false, message: 'Conflicto simulado' });
            await new Promise(resolve => setTimeout(resolve, 0));
            return resultado;
        })()`);
        assert.deepEqual(envio, { envios: 1, paso: 1, bloqueado: true });
    } finally {
        await nav.cerrar();
    }
});
