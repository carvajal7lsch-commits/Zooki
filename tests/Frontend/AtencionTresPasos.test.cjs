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

test('Atención en tres pasos: navegación, sugerencias ligadas, borrador y diseño 390/1366', { skip: chrome ? false : 'Sin Chrome' }, async () => {
    const vistas = Object.fromEntries(['vet_atencion', 'vet_pacientes'].map(accion => [accion, execFileSync('php', ['tests/Frontend/apoyo/c81-vistas.php', accion], { cwd: raiz, encoding: 'utf8' })]));
    let consultas = 0;
    let validaciones = 0;
    let retrasar = 0;
    let veterinarioContexto = 2;
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
            if (accion === 'consulta_contexto_ajax') respuesta = { success: true, id_clinica: 1, id_veterinario: veterinarioContexto, tiene_horario: true, control_dias_sin_duracion: 7, ultimo_peso: 9, cambio_peso: 0.1, referencias: { temperatura: [37.5,39.2], frecuencia_cardiaca: [60,120], frecuencia_respiratoria: [18,24] }, aviso_referencias: 'Referencia pendiente de validación veterinaria' };
            if (accion === 'fechas_control_ajax') { const desde=url.searchParams.get('desde'); const hasta=url.searchParams.get('hasta'); respuesta.dias_abiertos = Array.from({length:Number(hasta.slice(-2))}, (_,i) => desde.slice(0,8)+String(i+1).padStart(2,'0')); }
            if (accion === 'get_mascota_ajax') respuesta = { id_mascota: 7, nombre: 'Luna', peso: '9.00', propietario_nombre: 'Prueba', fecha_nacimiento: '2022-01-01' };
            if (accion === 'buscar_sintomas_ajax') respuesta.sugerencias = [{ codigo: 'sintoma.prueba', etiqueta: 'Síntoma sintético' }];
            if (accion === 'sugerir_diagnosticos_ajax') respuesta = { success: true, candidatos: [ { ...candidato, codigo: 'diagnostico.otitis', etiqueta: 'Otitis sintética' }, { ...candidato, codigo: 'diagnostico.ehrlichiosis', etiqueta: 'Ehrlichiosis sintética' } ], datos_faltantes: [] };
            if (accion === 'validar_prescripcion_ajax') {
                validaciones++;
                const cantidad = [...cuerpo.keys()].filter(k => /\[farmaco\]$/.test(k)).length;
                respuesta.filas = Array.from({ length: cantidad }, () => seguridad);
            }
            if (accion === 'sugerir_tratamientos_ajax') {
                consultas++;
                const enfermedad = cuerpo.get('diagnostico').split('.').pop();
                const peso = Number(cuerpo.get('peso'));
                const dosis = peso > 0 ? `${peso / 2} mg (0,5 mg/kg) cada 24 h` : '0,5 mg/kg · calcula con el peso cada 24 h';
                const pauta = { codigo: 'trata.'+enfermedad, farmaco: 'farmaco.prueba', medicamento: 'Fármaco '+enfermedad, dosis, via_administracion: 'Oral', duracion: '5 días', observaciones: 'Indicación sintética', seguridad: { ...seguridad, huella: undefined } };
                respuesta = { success: true, plan: { examenes: ['Examen '+enfermedad, 'Segundo examen'], recomendaciones: ['Recomendación '+enfermedad] }, tratamientos: [pauta, { ...pauta, codigo: 'trata.otra.'+enfermedad, farmaco: 'farmaco.otro', medicamento: 'Otro sintético' }, { ...pauta, codigo: 'trata.bloqueada', seguridad: { ...seguridad, bloqueada: true } }] };
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
    const carpeta = path.join(raiz, 'scratch/r/atencion-3-pasos');
    fs.mkdirSync(carpeta, { recursive: true });
    const captura = async nombre => {
        const r = await navegador.enviar('Page.captureScreenshot', { format: 'png' });
        fs.writeFileSync(path.join(carpeta, nombre + '.png'), Buffer.from(r.data, 'base64'));
    };
    try {
        for (const ancho of [360, 390, 1366]) {
            await navegador.enviar('Emulation.setDeviceMetricsOverride', { width: ancho, height: ancho < 768 ? 844 : 900, deviceScaleFactor: 1, mobile: ancho < 768 });
            for (const accion of ['vet_atencion', 'vet_pacientes']) {
                const url = `http://127.0.0.1:${servidor.address().port}/index.php?action=${accion}`;
                await navegador.enviar('Page.navigate', { url });
                await navegador.esperar("document.readyState === 'complete' && typeof ZookiConsultaPasos !== 'undefined'");
                await evaluar('localStorage.clear()');
                if (accion === 'vet_pacientes') await evaluar("openConsultationModal(7, 'Luna')");
                const selector = accion === 'vet_atencion' ? '#formConsultaAtencion' : '#formConsulta';
                const preparar = async () => evaluar(`window.f = document.querySelector('${selector}'); window.poner = (s,v) => { const c=f.querySelector(s); c.value=v; c.dispatchEvent(new Event('input',{bubbles:true})); }; window.ir = n => (document.querySelector('#atencionApp [data-consulta-ir="'+n+'"]') || f.querySelector('[data-consulta-ir="'+n+'"]')).click();`);
                await preparar();
                const paso = () => evaluar("f.querySelector('[data-consulta-tres]').dataset.pasoActual");
                assert.equal(await paso(), '0');
                await evaluar("poner('[name=motivo]',''); ir(1)");
                assert.equal(await paso(), '0', 'No avanza sin motivo');
                await evaluar("poner('[name=motivo]','Evaluación sintética'); poner('[name=peso]','24'); poner('[name=temperatura]','40'); poner('[data-sintomas-buscar]','prueba')");
                await navegador.esperar("f.querySelector('[data-codigo=\"sintoma.prueba\"]')");
                await evaluar("f.querySelector('[data-codigo=\"sintoma.prueba\"]').click()");
                await navegador.esperar("f.querySelectorAll('[data-usar-diagnostico]').length === 2");
                await navegador.esperar("f.querySelector('[data-vital=temperatura]').classList.contains('consulta-vital--fuera')");
                const vital = await evaluar("({color: getComputedStyle(f.querySelector('[data-vital=temperatura] input')).color, texto:f.querySelector('[data-vital=temperatura] [data-vital-referencia]').textContent})");
                assert.match(vital.texto, /Por encima/);
                assert.equal(vital.color, 'rgb(0, 82, 255)');
                await evaluar("f.querySelector('[data-sin-peso]').click()");
                assert.equal(await evaluar("f.querySelector('[data-vital=peso]').classList.contains('consulta-vital--fuera')"), false, 'Sin peso no muestra un cambio de peso antiguo');
                await evaluar("f.querySelector('[data-sin-peso]').click(); poner('[name=peso]','24')");
                assert.equal(await evaluar("f.querySelectorAll('[data-sintomas-panel] .consulta-chip').length"), 1);
                await captura(`${accion}-${ancho}-1`);
                const imagen = await evaluar("getComputedStyle(f.querySelector('.consulta-collage img')).display");
                assert.equal(imagen === 'none', ancho < 1024, 'El perro solo aparece en escritorio');
                await evaluar("f.querySelector('[data-usar-diagnostico]').click()");
                await navegador.esperar("f.querySelectorAll('[data-tratamiento]').length === 2");
                assert.equal(await paso(), '1', 'Usar desde evaluación lleva al paso 2');
                assert.equal(await evaluar("f.querySelector('[data-sugerencia-aviso] [role=status]').getBoundingClientRect().height > 0"), true, 'El aviso de lo llenado y Deshacer se ven');
                assert.match(await evaluar("f.querySelector('[data-usar-diagnostico]').textContent"), /En uso/);
                assert.equal(await evaluar("Boolean(f.querySelector('[data-pauta-sugerida=\"trata.bloqueada\"]'))"), false);
                await evaluar("const c=f.querySelector('[data-examenes-elegibles] input'); c.click(); c.click();");
                assert.match(await evaluar("f.querySelector('[name=plan_tratamiento]').value"), /Examen otitis/,'Seleccionar de nuevo restaura el examen');
                await captura(`${accion}-${ancho}-2`);
                await evaluar("poner('[data-tratamiento-campo=dosis]','Dosis propia'); f.querySelectorAll('[data-usar-diagnostico]')[1].click()");
                await navegador.esperar("f.querySelector('[name=diagnostico]').value.includes('Ehrlichiosis')");
                assert.equal(await evaluar("f.querySelectorAll('[data-tratamiento]').length"), 3, 'Conserva editada y cambia dos sugeridas');
                assert.equal(await evaluar("f.querySelector('[data-tratamiento-campo=dosis]').value"), 'Dosis propia');
                assert.equal(await evaluar("Boolean(f.querySelector('[data-pauta-sugerida=\"trata.otitis\"]'))"), false);
                assert.doesNotMatch(await evaluar("f.querySelector('[name=plan_tratamiento]').value"), /Recomendación otitis/);
                assert.match(await evaluar("f.querySelector('[name=plan_tratamiento]').value"), /Examen otitis/, 'Conserva el examen revisado como propio');
                await evaluar("ir(0)");
                assert.equal(await evaluar("f.querySelector('[name=motivo]').value"), 'Evaluación sintética');
                await evaluar("ir(1); ir(2)");
                assert.equal(await paso(), '2');
                await evaluar("const datos=new DataTransfer(); datos.items.add(new File(['prueba'],'archivo.txt',{type:'text/plain'})); f.querySelector('[data-soltar-adjuntos]').dispatchEvent(new DragEvent('drop',{bubbles:true,cancelable:true,dataTransfer:datos}));");
                assert.match(await evaluar("f.querySelector('[data-adjuntos-aviso]').textContent"), /JPG, PNG o PDF/);
                assert.equal(await evaluar("ZookiConsultaForm.validarTodo(f.querySelector('[data-consulta-paso=\"2\"]')).type"), 'file');
                await evaluar("const archivo=f.querySelector('[data-consulta-archivos]'); archivo.value=''; archivo.dispatchEvent(new Event('change',{bubbles:true}));");
                assert.equal(await evaluar("f.querySelector('[data-adjuntos-aviso]').textContent"), '');
                assert.equal(await evaluar("(document.querySelector('#btnFinalizarAtencion') || f.querySelector('[data-consulta-finalizar]')).hidden"), false);
                await navegador.esperar("f.querySelector('[data-control-fecha]').dataset.valida === '1'");
                assert.equal(await evaluar("f.querySelector('[data-control-fecha]').value === [...f.querySelectorAll('[data-tratamiento-campo=fecha_fin]')].map(c=>c.value).sort().at(-1)"), true, 'Propone el fin conocido más lejano');
                await captura(`${accion}-${ancho}-3`);
                assert.equal(await evaluar('document.documentElement.scrollWidth <= innerWidth'), true, 'Sin desborde '+accion+'/'+ancho);
                if (accion === 'vet_atencion') {
                    await navegador.esperar("Object.keys(localStorage).some(k=>k.startsWith('zooki_atencion_borrador_'))");
                    await evaluar("window.dispatchEvent(new Event('pagehide'))");
                    await navegador.enviar('Page.reload', {});
                    await navegador.esperar("document.readyState === 'complete' && document.querySelector('[data-borrador-aviso]').hidden === false");
                    await preparar();
                    assert.match(await evaluar("f.querySelector('[data-borrador-aviso]').textContent"), /Recuperamos tu borrador/);
                    assert.equal(await evaluar("f.querySelectorAll('[data-tratamiento]').length"), 3);
                    await evaluar("poner('[name=diagnostico]','Diagnóstico escrito por el veterinario')");
                    assert.equal(await evaluar("f.querySelector('[name=diagnostico]').value"), 'Diagnóstico escrito por el veterinario');
                    assert.equal(await evaluar("f.querySelectorAll('[data-tratamiento]').length"), 1, 'Sustituir a mano también retira los sugeridos sin editar');
                    await evaluar("poner('[name=diagnostico]','')");
                    assert.equal(await evaluar("f.querySelectorAll('[data-tratamiento]').length"), 1, 'Borrar diagnóstico tras recuperar quita solo sugeridos');
                    await evaluar("window.dispatchEvent(new Event('pagehide'))");
                    veterinarioContexto = 4;
                    await navegador.enviar('Page.reload', {});
                    await navegador.esperar("document.readyState==='complete' && document.querySelector('#estadoBorrador').textContent.includes('Tu contexto cambió')");
                    await preparar();
                    assert.equal(await evaluar("f.querySelectorAll('[data-tratamiento]').length"), 0, 'Otro contexto no recupera el borrador');
                    assert.equal(await evaluar("f.querySelector('[data-borrador-aviso]').hidden"), true);
                    veterinarioContexto = 2;
                    await navegador.enviar('Page.reload', {});
                    await navegador.esperar("document.readyState==='complete' && document.querySelector('[data-borrador-aviso]').hidden===false");
                    await preparar();
                    await evaluar("f.querySelector('[data-descartar-borrador]').click()");
                    assert.equal(await evaluar("f.querySelectorAll('[data-tratamiento]').length"), 0);
                    assert.equal(await evaluar("Object.keys(localStorage).some(k=>k.startsWith('zooki_atencion_borrador_'))"), false);
                } else {
                    await evaluar("poner('[name=diagnostico]','Diagnóstico escrito por el veterinario')");
                    assert.equal(await evaluar("f.querySelector('[name=diagnostico]').value"), 'Diagnóstico escrito por el veterinario');
                    assert.equal(await evaluar("f.querySelectorAll('[data-tratamiento]').length"), 1, 'Sustituir a mano también retira los sugeridos sin editar');
                    await evaluar("poner('[name=diagnostico]','')");
                    assert.equal(await evaluar("f.querySelectorAll('[data-tratamiento]').length"), 1);
                    assert.equal(await evaluar("f.querySelector('[data-deshacer-sugerencia]').hidden || f.querySelector('[data-deshacer-sugerencia]').parentElement.hidden"), true);
                }
                await evaluar("window.destinoCierre = null; void ZookiCierreConsulta.mostrar({success:true}, f).then(d=>{window.destinoCierre=d;})");
                assert.match(await evaluar("document.querySelector('.consulta-cierre-dialogo').textContent"), /Atención registrada.*Volver a la agenda/);
                assert.equal(await evaluar("getComputedStyle(document.querySelector('.consulta-cierre-dialogo img')).display==='none'"), ancho < 1024);
                await evaluar("document.querySelector('.consulta-cierre-dialogo button').click()");
                assert.equal(await evaluar("window.destinoCierre"), 'index.php?action=vet_agenda');
            }
        }
        assert.deepEqual(navegador.errores, []);
    } finally {
        await navegador.cerrar();
        await new Promise(r => servidor.close(r));
    }
});
