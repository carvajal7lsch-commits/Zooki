/** HU-2.8/2.9: Chrome, vistas reales y respuestas sintéticas, sin base de trabajo. */
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { createServer } = require('node:http');
const { buscarChrome, abrirChrome } = require('./apoyo/chrome-cdp.cjs');

const raiz = path.resolve(__dirname, '../..');
const chrome = buscarChrome();
const candidato = { codigo: 'diagnostico.prueba', etiqueta: 'Diagnóstico sintético', cobertura: { texto: '1 síntoma' }, fuentes: [], sintomas_coincidentes: ['sintoma.prueba'] };
const seguridad = { bloqueada: false, motivos: [], advertencias: [], evaluacion_incompleta: false, requiere_decision: false, requiere_conciliacion: false, huella: 'a'.repeat(64) };

test('Consulta sugerida 2.3.0: modal y Atención a 390/1366, completar, no duplicar, recalcular y deshacer', { skip: chrome ? false : 'Sin Chrome' }, async () => {
    const vistas = Object.fromEntries(['vet_atencion', 'vet_pacientes'].map(accion => [accion, execFileSync('php', ['tests/Frontend/apoyo/c81-vistas.php', accion], { cwd: raiz, encoding: 'utf8' })]));
    let consultas = 0;
    let validaciones = 0;
    let retrasar = 0;
    const servidor = createServer(async (req, res) => {
        const url = new URL(req.url, 'http://localhost');
        const accion = url.searchParams.get('action');
        if (vistas[accion]) {
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            res.end(vistas[accion].replace(/<script[^>]+src="https:[^>]*><\/script>/g, ''));
            return;
        }
        if (accion) {
            res.setHeader('Content-Type', 'application/json');
            let texto = '';
            for await (const trozo of req) texto += trozo;
            const cuerpo = new URLSearchParams(texto);
            let respuesta = { success: true, notificaciones: [], alertas: { activas: [], historial: [] }, nodos: [], mascotas: [], consultas: [] };
            if (accion === 'get_mascota_ajax') respuesta = { id_mascota: 7, nombre: 'Luna', peso: '9.00', propietario_nombre: 'Prueba', fecha_nacimiento: '2022-01-01' };
            if (accion === 'buscar_sintomas_ajax') respuesta.sugerencias = [{ codigo: 'sintoma.prueba', etiqueta: 'Síntoma sintético' }];
            if (accion === 'sugerir_diagnosticos_ajax') respuesta = { success: true, candidatos: [candidato], datos_faltantes: [] };
            if (accion === 'validar_prescripcion_ajax') {
                validaciones++;
                const cantidad = [...cuerpo.keys()].filter(k => /\[farmaco\]$/.test(k)).length;
                respuesta.filas = Array.from({ length: cantidad }, () => seguridad);
            }
            if (accion === 'sugerir_tratamientos_ajax') {
                consultas++;
                const peso = Number(cuerpo.get('peso'));
                const dosis = peso > 0 ? `${peso / 2} mg (0,5 mg/kg) cada 24 h` : '0,5 mg/kg · calcula con el peso cada 24 h';
                const pauta = { codigo: 'trata.prueba', farmaco: 'farmaco.prueba', medicamento: 'Fármaco sintético', dosis, via_administracion: 'Oral', duracion: '5 días', observaciones: 'Indicación sintética', seguridad: { ...seguridad, huella: undefined } };
                respuesta = { success: true, plan: { examenes: ['Examen sintético'], recomendaciones: ['Recomendación sintética'] }, tratamientos: [pauta, { ...pauta, codigo: 'trata.otra', farmaco: 'farmaco.otro', medicamento: 'Otro sintético' }, { ...pauta, codigo: 'trata.bloqueada', seguridad: { ...seguridad, bloqueada: true } }] };
                if (retrasar) await new Promise(r => setTimeout(r, retrasar));
            }
            res.end(JSON.stringify(respuesta));
            return;
        }
        const ruta = path.resolve(raiz, 'public', '.' + decodeURIComponent(url.pathname));
        if (!ruta.startsWith(path.join(raiz, 'public') + path.sep) || !fs.existsSync(ruta)) {
            res.writeHead(404);
            res.end();
            return;
        }
        res.setHeader('Content-Type', ({ '.js': 'application/javascript', '.css': 'text/css', '.svg': 'image/svg+xml' })[path.extname(ruta)] || 'application/octet-stream');
        res.end(fs.readFileSync(ruta));
    });
    await new Promise(r => servidor.listen(0, '127.0.0.1', r));
    const navegador = await abrirChrome(chrome);
    const evaluar = expresion => navegador.evaluar(expresion);
    try {
        for (const ancho of [390, 1366]) {
            await navegador.enviar('Emulation.setDeviceMetricsOverride', { width: ancho, height: ancho < 768 ? 844 : 950, deviceScaleFactor: 1, mobile: ancho < 768 });
            for (const accion of ['vet_atencion', 'vet_pacientes']) {
                await navegador.enviar('Page.navigate', { url: `http://127.0.0.1:${servidor.address().port}/index.php?action=${accion}` });
                await navegador.esperar("document.readyState === 'complete' && typeof ZookiConsultaForm !== 'undefined'");
                await evaluar("localStorage.clear()");
                if (accion === 'vet_pacientes') await evaluar("openConsultationModal(7, 'Luna')");
                const selector = accion === 'vet_atencion' ? '#formConsultaAtencion' : '#formConsulta';
                await evaluar(`window.formPrueba = document.querySelector('${selector}');
                    window.cambiosSugeridos = 0;
                    formPrueba.addEventListener('input', evento => {
                        if (evento.target === formPrueba) cambiosSugeridos++;
                    });
                    window.ponerPrueba = (selector, valor) => {
                        const campo = formPrueba.querySelector(selector);
                        campo.value = valor;
                        campo.dispatchEvent(new Event('input', { bubbles: true }));
                    };
                    ponerPrueba('[data-consulta-campo="motivo"]', 'Prueba sintética');
                    ponerPrueba('[data-consulta-campo="diagnostico"]', 'Texto previo');
                    ponerPrueba('[name="plan_tratamiento"]', 'Plan previo');
                    ponerPrueba('[data-consulta-campo="peso"]', '24');
                    ponerPrueba('[data-sintomas-buscar]', 'prueba');`);
                await navegador.esperar("formPrueba.querySelector('[data-codigo=\"sintoma.prueba\"]')");
                await evaluar("formPrueba.querySelector('[data-codigo=\"sintoma.prueba\"]').click()");
                await navegador.esperar("formPrueba.querySelector('[data-usar-diagnostico]')");
                const antes = consultas;
                await evaluar(`formPrueba.querySelector('[data-usar-diagnostico]').click();
                    formPrueba.querySelector('[data-usar-diagnostico]').click();`);
                await navegador.esperar("formPrueba.querySelectorAll('[data-tratamiento]').length === 2");
                assert.equal(consultas - antes, 1, 'Doble clic concurrente no duplica la consulta');
                assert.equal(await evaluar('cambiosSugeridos'), 1, 'Usar notifica cambios al formulario completo');
                const datos = await evaluar(`({ diagnostico: formPrueba.querySelector('[data-consulta-campo="diagnostico"]').value,
                    plan: formPrueba.querySelector('[name="plan_tratamiento"]').value,
                    filas: [...formPrueba.querySelectorAll('[data-tratamiento]')].map(f => ({ codigo: f.querySelector('[data-tratamiento-campo="farmaco"]').value, dosis: f.querySelector('[data-tratamiento-campo="dosis"]').value, fin: f.querySelector('[data-tratamiento-campo="fecha_fin"]').value, inicio: f.querySelector('[data-tratamiento-campo="fecha_inicio"]').value, marca: f.querySelector('[data-marca-sugerida]').textContent })),
                    aviso: formPrueba.querySelector('[role="status"]').textContent })`);
                assert.equal(datos.diagnostico, 'Texto previo\nDiagnóstico sintético');
                assert.equal(datos.plan, 'Plan previo\nExámenes sugeridos: Examen sintético\nRecomendaciones: Recomendación sintética');
                assert.equal(datos.filas[0].dosis, '12 mg (0,5 mg/kg) cada 24 h');
                assert.equal(datos.filas[0].marca, 'Sugerido por Zooki · verifica la dosis');
                assert.ok(datos.filas[0].fin >= datos.filas[0].inicio);
                assert.match(datos.aviso, /diagnóstico, plan y 2 tratamientos/);
                assert.equal(await evaluar("Boolean(formPrueba.querySelector('[data-pauta-sugerida=\"trata.bloqueada\"]'))"), false);
                await evaluar("formPrueba.querySelector('[data-usar-diagnostico]').click()");
                assert.equal(consultas - antes, 1, 'Usar otra vez después de completar tampoco duplica');
                await evaluar("ponerPrueba('[data-tratamiento-campo=\"dosis\"]', 'a'.repeat(101))");
                assert.equal(await evaluar("formPrueba.querySelector('[data-tratamiento-campo=\"dosis\"]').value.length"), 100, 'La restricción de entrada conserva el máximo del servidor');
                await evaluar("ponerPrueba('[data-tratamiento-campo=\"dosis\"]', '12 mg (0,5 mg/kg) cada 24 h')");
                // La fila que se editó deja de recalcular; la segunda conserva su dosis sugerida.
                await evaluar("ponerPrueba('[data-consulta-campo=\"peso\"]', '30')");
                await navegador.esperar("formPrueba.querySelectorAll('[data-tratamiento-campo=\"dosis\"]')[1].value.startsWith('15 mg')");
                await evaluar(`ponerPrueba('[data-tratamiento-campo="dosis"]', 'Dosis revisada');
                    ponerPrueba('[data-consulta-campo="peso"]', '40');`);
                await navegador.esperar("formPrueba.querySelectorAll('[data-tratamiento-campo=\"dosis\"]')[1].value.startsWith('20 mg')");
                assert.equal(await evaluar("formPrueba.querySelector('[data-tratamiento-campo=\"dosis\"]').value"), 'Dosis revisada');
                await evaluar(`const sinPeso = formPrueba.querySelector('[data-sin-peso]');
                    sinPeso.checked = true;
                    sinPeso.dispatchEvent(new Event('change', { bubbles: true }));`);
                await navegador.esperar("formPrueba.querySelectorAll('[data-tratamiento-campo=\"dosis\"]')[1].value.includes('calcula con el peso')");
                if (accion === 'vet_atencion') await evaluar("document.querySelector('[data-paso=\"3\"]').click()");
                else await evaluar("document.querySelector('[data-tab=\"tabResolucion\"]').click()");
                await evaluar("formPrueba.querySelector('[data-marca-sugerida]').scrollIntoView({block: 'center'})");
                const carpeta = path.join(raiz, 'scratch/r/consulta230');
                fs.mkdirSync(carpeta, { recursive: true });
                const captura = await navegador.enviar('Page.captureScreenshot', { format: 'png' });
                fs.writeFileSync(path.join(carpeta, `${accion}-${ancho}.png`), Buffer.from(captura.data, 'base64'));
                await evaluar(`ponerPrueba('[name="plan_tratamiento"]', formPrueba.querySelector('[name="plan_tratamiento"]').value + '\\nNota posterior');
                    formPrueba.querySelector('[data-deshacer-sugerencia]').click();`);
                assert.equal(await evaluar("formPrueba.querySelector('[data-consulta-campo=\"diagnostico\"]').value"), 'Texto previo');
                assert.equal(await evaluar("formPrueba.querySelector('[name=\"plan_tratamiento\"]').value"), 'Plan previo\nNota posterior');
                assert.equal(await evaluar("formPrueba.querySelectorAll('[data-tratamiento]').length"), 1, 'Deshacer conserva la fila editada');
                assert.equal(await evaluar('cambiosSugeridos'), 2, 'Deshacer también notifica el cambio');
                await navegador.esperar("formPrueba.querySelector('[data-tratamiento-campo=\"huella\"]').value.length === 64");
                assert.ok(validaciones > 0, 'Las filas pasan por validar_prescripcion_ajax');
                assert.equal(await evaluar("document.documentElement.scrollWidth <= innerWidth"), true, 'Sin desborde ' + accion + '/' + ancho);
                // Una respuesta que llega tras cambiar de paciente/formulario no agrega nada.
                retrasar = 150;
                await evaluar("formPrueba.querySelector('[data-usar-diagnostico]').click()");
                if (accion === 'vet_pacientes') await evaluar("openConsultationModal(8, 'Otra mascota')");
                await new Promise(r => setTimeout(r, 450));
                if (accion === 'vet_pacientes') assert.equal(await evaluar("formPrueba.querySelectorAll('[data-tratamiento]').length"), 0);
                retrasar = 0;
            }
        }
        assert.deepEqual(navegador.errores, []);
    } finally {
        await navegador.cerrar();
        await new Promise(r => servidor.close(r));
    }
});
