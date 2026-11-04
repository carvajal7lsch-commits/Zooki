/** Userscript sobre MySQL y controladores reales; únicamente el reloj y el transporte de CDN son del arnés. */
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { execFileSync, spawn } = require('node:child_process');
const { buscarChrome, abrirChrome } = require('./apoyo/chrome-cdp.cjs');
const raiz = path.resolve(__dirname, '../..');
const salida = path.join(raiz, 'scratch/r/userscript');
function semilla(modo) {
    const texto = execFileSync('php', ['tests/Frontend/apoyo/demo-semilla.php', modo], { cwd: raiz, encoding: 'utf8', maxBuffer: 4 * 1024 * 1024 });
    if (modo !== 'contar') fs.appendFileSync(path.join(salida, 'corrida-cli.log'), texto + '\n');
    return JSON.parse(texto.trim().split(/\r?\n/).at(-1));
}

async function prepararDependencias() {
    const carpeta = path.join(salida, 'dependencias');
    fs.mkdirSync(carpeta, { recursive: true });
    const urls = {
        'fullcalendar.js': 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js',
        'sweetalert.js': 'https://cdn.jsdelivr.net/npm/sweetalert2@11',
        'chart.js': 'https://cdn.jsdelivr.net/npm/chart.js',
        'flatpickr.css': 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
        'fontawesome.css': 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
        'fa-solid-900.woff2': 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-solid-900.woff2',
        'fa-regular-400.woff2': 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-regular-400.woff2',
        'fa-brands-400.woff2': 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-brands-400.woff2',
        'flatpickr.js': 'https://cdn.jsdelivr.net/npm/flatpickr',
        'flatpickr-es.js': 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js',
        'telefono.js': 'https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.10/build/js/intlTelInput.min.js',
        'telefono-utils.js': 'https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.10/build/js/utils.js',
        'jquery.js': 'https://code.jquery.com/jquery-3.6.0.min.js',
        'select2.js': 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js'
    };
    await Promise.all(Object.entries(urls).map(async ([nombre, url]) => {
        const archivo = path.join(carpeta, nombre);
        if (fs.existsSync(archivo)) return;
        const respuesta = await fetch(url, { signal: AbortSignal.timeout(8000) });
        assert.ok(respuesta.ok, `No se pudo descargar la dependencia real: ${url}`);
        fs.writeFileSync(archivo, Buffer.from(await respuesta.arrayBuffer()));
    }));
    fs.writeFileSync(path.join(carpeta, 'mapa.json'), JSON.stringify(Object.fromEntries(Object.entries(urls).map(([nombre, url]) => [url, '/__dependencia/' + nombre]))));
}

test('Guion Tampermonkey: Agenda, Rocco, Mishi y evaluación real de Simón, cierra solo Rocco e inicia Mishi', { skip: !process.env.ZOOKI_TEST_MYSQL_HOST || !buscarChrome() ? 'Necesita Chrome y el motor temporal de pruebas' : false, timeout: 360000 }, async () => {
    fs.mkdirSync(salida, { recursive: true });
    await prepararDependencias();
    fs.writeFileSync(path.join(salida, 'peticiones.log'), '');
    fs.writeFileSync(path.join(salida, 'corrida-cli.log'), '');
    const inicial = semilla('crear');
    const rocco = inicial.citas.find(c => c.nombre === 'Rocco' && c.estado === 'en_curso');
    assert.ok(rocco);
    assert.equal(inicial.solicitudes_simon.length, 0);
    const sesiones = fs.mkdtempSync(path.join(os.tmpdir(), 'zooki-demo-http-'));
    const registro = fs.openSync(path.join(salida, 'php.log'), 'w');
    const servidor = spawn('php', ['-d', `session.save_path=${sesiones}`, '-S', 'localhost:31300', '-t', path.join(raiz, 'public'), path.join(raiz, 'tests/Frontend/apoyo/demo-servidor.php')], { cwd: raiz, env: process.env, stdio: ['ignore', registro, registro], windowsHide: true });
    let navegador;
    const evidencias = [];
    try {
        const limite = Date.now() + 8000;
        while (true) {
            try {
                const r = await fetch('http://localhost:31300/index.php?action=vet_agenda&__persona=vet');
                assert.equal(r.status, 200, await r.text());
                break;
            } catch (error) {
                if (Date.now() > limite) throw error;
                await new Promise(r => setTimeout(r, 80));
            }
        }
        navegador = await abrirChrome(buscarChrome());
        const codigo = fs.readFileSync(path.join(raiz, 'demo/zooki-demo.user.js'), 'utf8');
        await navegador.enviar('Emulation.setTimezoneOverride', { timezoneId: 'America/Bogota' });
        await navegador.enviar('Page.addScriptToEvaluateOnNewDocument', { source: `
            const FechaRealDemo = Date;
            const origenDemo = FechaRealDemo.now();
            const fijoDemo = new FechaRealDemo('2026-11-02T10:30:00-05:00').getTime();
            window.Date = class extends FechaRealDemo {
                constructor(...args) { super(...(args.length ? args : [fijoDemo + FechaRealDemo.now() - origenDemo])); }
                static now() { return fijoDemo + FechaRealDemo.now() - origenDemo; }
            };
            addEventListener('beforeunload', evento => evento.stopImmediatePropagation(), true);
            document.addEventListener('DOMContentLoaded', () => { ${codigo}\n });
        ` });
        const evaluar = expresion => navegador.evaluar(expresion);
        const panel = "document.getElementById('zkdemo-guion').shadowRoot";
        const navegar = async (accion, persona = '') => {
            await navegador.enviar('Page.navigate', { url: `http://localhost:31300/index.php?action=${accion}${persona ? '&__persona=' + persona : ''}` });
            await navegador.esperar("document.readyState === 'complete' && document.getElementById('zkdemo-guion')");
        };
        const tecla = async letra => {
            await navegador.enviar('Input.dispatchKeyEvent', { type: 'keyDown', key: letra.toUpperCase(), code: 'Key' + letra.toUpperCase(), modifiers: 8 });
            await navegador.enviar('Input.dispatchKeyEvent', { type: 'keyUp', key: letra.toUpperCase(), code: 'Key' + letra.toUpperCase(), modifiers: 8 });
        };
        const paso = async esperado => {
            await tecla('s');
            await navegador.esperar(`${panel}.querySelector('[role="status"]').textContent !== 'Ejecutando este paso en Zooki…'`);
            const estado = await evaluar(`${panel}.querySelector('[role="status"]').textContent`);
            assert.match(estado, esperado);
            assert.equal(await evaluar(`${panel}.querySelector('[role="status"]').classList.contains('error')`), false, estado);
        };
        const capturar = async nombre => {
            const imagen = await navegador.enviar('Page.captureScreenshot', { format: 'png' });
            fs.writeFileSync(path.join(salida, nombre + '.png'), Buffer.from(imagen.data, 'base64'));
        };
        await navegador.enviar('Emulation.setDeviceMetricsOverride', { width: 1366, height: 768, deviceScaleFactor: 1, mobile: false });
        await navegar('vet_agenda', 'vet');
        await navegador.esperar("typeof calendarInstance !== 'undefined' && calendarInstance?.getEvents().some(e => e.title === 'Rocco')");
        await paso(/Datos de la Agenda/);
        assert.match(await evaluar(`${panel}.textContent`), /Rocco.*En curso/);
        assert.ok(await evaluar(`${panel}.querySelectorAll('li').length >= 3`));
        await evaluar(`${panel}.querySelector('header button').click()`);
        assert.ok(await evaluar(`${panel}.querySelector('.panel').hidden && !${panel}.querySelector('.punto').hidden`));
        await evaluar(`${panel}.querySelector('.punto').click()`);
        await capturar('01-agenda-1366');
        await navegar('vet_atencion&id_cita=' + rocco.id_cita);
        await navegador.esperar("document.querySelector('[data-consulta-campo=\"peso\"]').matches(':enabled')");
        await paso(/calculó los diagnósticos/);
        assert.ok(await evaluar("Boolean(document.querySelector('[data-usar-diagnostico=\"diagnostico.sarna_sarcoptica\"]'))"));
        assert.match(await evaluar("document.querySelector('[data-diagnostico=\"diagnostico.sarna_sarcoptica\"] .consulta-sugerencia__cobertura').textContent"), /3 de 3/);
        await capturar('02-rocco-sintomas-1366');
        await paso(/descarte de ivermectina/);
        assert.match(await evaluar("document.querySelector('[data-prescripcion-descartada=\"farmaco.ivermectina\"]').textContent"), /Collie.*MDR1/);
        assert.ok(await evaluar("[...document.querySelectorAll('[data-tratamiento-campo=\"farmaco\"]')].some(n => n.value === 'farmaco.selamectina')"));
        await navegador.esperar(`(() => {
            const fila = document.querySelector('[data-prescripcion-descartada="farmaco.ivermectina"]');
            const marco = ${panel}.querySelector('.marca');
            if (!fila || !marco) return false;
            return Math.abs(fila.getBoundingClientRect().top - marco.getBoundingClientRect().top - 3) < 1;
        })()`);
        await capturar('03-rocco-tratamiento-1366');
        const lectura = [];
        for (const [ancho, alto] of [[390, 844], [1366, 768]]) {
            await navegador.enviar('Emulation.setDeviceMetricsOverride', { width: ancho, height: alto, deviceScaleFactor: 1, mobile: ancho < 768 });
            await evaluar('new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)))');
            const medida = await evaluar(`(() => {
                const fila = document.querySelector('[data-prescripcion-descartada="farmaco.ivermectina"]');
                const texto = fila.querySelector('p');
                return { ancho:innerWidth, alto:innerHeight, pagina:document.scrollingElement.scrollHeight, texto: texto.textContent, enlace:texto.querySelector('a').href, altoTexto:texto.clientHeight, contenido:texto.scrollHeight, enTratamientos:Boolean(fila.closest('[data-tratamientos]')), enPie:Boolean(fila.closest('[data-hoja-pie]')), blanco:getComputedStyle(texto).whiteSpace };
            })()`);
            assert.equal(medida.enTratamientos, true);
            assert.equal(medida.enPie, false);
            assert.equal(medida.blanco, 'normal');
            assert.ok(medida.contenido <= medida.altoTexto + 1);
            console.log('Lectura del descarte:', JSON.stringify(medida));
            assert.ok(medida.ancho < 1024 || medida.pagina <= medida.alto, JSON.stringify(medida));
            lectura.push(medida);
        }
        await tecla('s');
        await navegador.esperar("new URL(location.href).searchParams.get('action') === 'vet_agenda' && document.getElementById('zkdemo-guion')");
        await navegador.esperar("typeof calendarInstance !== 'undefined' && calendarInstance?.getEvents().some(e => e.title === 'Rocco' && e.extendedProps.estado === 'completada')");
        const trasRocco = semilla('contar');
        assert.equal(trasRocco.consultas, inicial.consultas + 1);
        const mishi = trasRocco.citas.find(c => c.nombre === 'Mishi' && c.id_veterinario === rocco.id_veterinario && ['confirmada', 'pendiente'].includes(c.estado));
        assert.ok(mishi.hora_llegada);
        assert.equal(mishi.hora_inicio_real, null);
        await navegador.esperar(`${panel}.querySelector('h2').textContent.includes('iniciar Atención de Mishi')`);
        await tecla('s');
        await navegador.esperar(`new URL(location.href).searchParams.get('action') === 'vet_atencion' && new URL(location.href).searchParams.get('id_cita') === '${mishi.id_cita}' && document.querySelector('[data-consulta-campo="peso"]')?.matches(':enabled') && document.getElementById('zkdemo-guion')`);
        const mishiIniciada = semilla('contar').citas.find(c => c.id_cita === mishi.id_cita);
        assert.equal(mishiIniciada.estado, 'en_curso');
        await paso(/diagnósticos calculados|Diagnósticos calculados/);
        const diagnosticosMishi = await evaluar("[...document.querySelectorAll('#formConsultaAtencion [data-diagnostico]')].map(n => n.dataset.diagnostico)");
        assert.ok(diagnosticosMishi.length > 0);
        await capturar('04-mishi-sintomas-1366');
        await paso(/bloqueo y su fuente/);
        const toxicidad = await evaluar("document.querySelector('.consulta-tratamiento--bloqueo [data-prescripcion-lista]').textContent");
        assert.match(toxicidad, /[Tt]oxicidad/);
        assert.match(toxicidad, /Gato|[Ff]elino/);
        assert.ok(await evaluar("Boolean(document.querySelector('.consulta-tratamiento--bloqueo .consulta-tratamiento__fuente a'))"));
        await capturar('05-mishi-paracetamol-1366');
        const clinicaAntes = semilla('contar');
        assert.equal(clinicaAntes.consultas, trasRocco.consultas);
        assert.equal(clinicaAntes.tratamientos, trasRocco.tratamientos);
        assert.equal(clinicaAntes.solicitudes_simon.length, 0);
        await evaluar('window.onbeforeunload = null');
        await navegador.enviar('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
        await navegar('portal_propietario', 'portal');
        await navegador.esperar("typeof window.ZookiTriageAgenda !== 'undefined'");
        await paso(/Evaluación real de Simón/);
        const resultado = await evaluar("document.querySelector('#portalBookingForm [data-triage-resultado]').textContent");
        assert.match(resultado, /[Rr]ojo|[Cc]rítico/);
        assert.match(resultado, /[Ee]mergencia/);
        assert.ok(await evaluar(`${panel}.querySelector('.panel').hidden`));
        await capturar('06-simon-rojo-390');
        const final = semilla('contar');
        assert.equal(final.solicitudes_simon.length, 1);
        assert.equal(final.solicitudes_simon[0].estado, 'derivada');
        assert.equal(final.solicitudes_simon[0].id_cita, null);
        assert.equal(final.consultas, trasRocco.consultas);
        assert.equal(final.tratamientos, trasRocco.tratamientos);
        assert.equal(final.citas.length, inicial.citas.length);
        await tecla('q');
        await tecla('s');
        assert.match(await evaluar(`${panel}.querySelector('[role="status"]').textContent`), /ya fue evaluado/);
        assert.equal(await evaluar(`${panel}.querySelector('.panel').hidden`), false);
        assert.equal(semilla('contar').solicitudes_simon.length, 1);
        assert.equal((await fetch('http://localhost:31300/demo/zooki-demo.user.js')).status, 404);
        const peticiones = fs.readFileSync(path.join(salida, 'peticiones.log'), 'utf8');
        assert.equal((peticiones.match(/POST calcular_triage_ajax/g) || []).length, 1);
        assert.equal((peticiones.match(/POST registrar_consulta_ajax/g) || []).length, 1);
        assert.equal((peticiones.match(/POST iniciar_cita_ajax/g) || []).length, 1);
        assert.doesNotMatch(peticiones, /POST (portal_agendar_cita_ajax|cancelar|eliminar)/);
        const rehecha = semilla('escena');
        assert.equal(rehecha.solicitudes_simon.length, 0);
        assert.equal(rehecha.consultas, inicial.consultas);
        assert.equal(rehecha.tratamientos, inicial.tratamientos);
        assert.equal(rehecha.citas.filter(c => c.nombre === 'Mishi' && c.estado === 'en_curso').length, 0);
        assert.deepEqual(navegador.errores, []);
        evidencias.push({ escritorio: '1366x768', portal: '390x844', sarna: true, ivermectinaDescartada: true, selamectina: true, diagnosticosMishi, paracetamolBloqueadoConFuente: true, simon: 'rojo/derivada, una solicitud, sin cita', consultasGuardadas: 1, pacienteGuardado: 'Rocco', mishiIniciadaSinGuardar: true, lectura, reservasCreadas: 0, excepciones: navegador.errores });
        fs.writeFileSync(path.join(salida, 'resultado.json'), JSON.stringify(evidencias, null, 2));
    } catch (error) {
        if (navegador) {
            fs.writeFileSync(path.join(salida, 'fallo.html'), await navegador.evaluar('document.documentElement.outerHTML'));
            fs.writeFileSync(path.join(salida, 'excepciones.json'), JSON.stringify(navegador.errores, null, 2));
            const captura = await navegador.enviar('Page.captureScreenshot', { format: 'png' });
            fs.writeFileSync(path.join(salida, 'fallo.png'), Buffer.from(captura.data, 'base64'));
        }
        throw error;
    } finally {
        if (navegador) await navegador.cerrar();
        if (servidor.exitCode === null) {
            servidor.kill();
            await new Promise(r => servidor.once('exit', r));
        }
        fs.closeSync(registro);
        const resuelta = path.resolve(sesiones);
        if (path.dirname(resuelta) === path.resolve(os.tmpdir()) && path.basename(resuelta).startsWith('zooki-demo-http-')) fs.rmSync(resuelta, { recursive: true, force: true });
    }
});
