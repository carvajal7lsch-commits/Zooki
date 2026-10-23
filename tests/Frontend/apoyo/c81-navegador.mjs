// Verificación visual reproducible: node tests/Frontend/apoyo/c81-navegador.mjs
// Chrome real, vistas y assets reales, respuestas HTTP sintéticas; sin .env ni base de datos.
import { spawn, execFileSync } from 'node:child_process';
import { createServer } from 'node:http';
import { existsSync, readFileSync, writeFileSync, mkdirSync, mkdtempSync, rmSync, realpathSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve, extname, dirname, basename, sep } from 'node:path';
import assert from 'node:assert/strict';

const raiz = resolve(import.meta.dirname, '../../..');
const carpeta = join(raiz, 'specs/referencias/m2-pulido/c8-1-despues');
mkdirSync(carpeta, { recursive: true });
const vistas = Object.fromEntries(['vet_atencion', 'vet_pacientes'].map(accion => [accion, execFileSync('php', [join(import.meta.dirname, 'c81-vistas.php'), accion], { cwd: raiz, encoding: 'utf8' })]));
const mascota = { id_mascota: 7, nombre: 'Luna', nombre_especie: 'Canino', nombre_raza: 'Mestizo', peso: '9.00', numero_historia_clinica: 'HC-000001', propietario_nombre: 'Fabio Propietario', fecha_nacimiento: '2022-01-01' };
const activas = [
    { id_alerta: 1, tipo: 'alergia', tipo_nombre: 'Alergia', descripcion: 'Reacción cutánea con la amoxicilina', clinica_nombre: 'Clínica Norte', usuario_nombre: 'Beto Norte', fecha_registro: '2026-10-01', puede_desactivar: true },
    { id_alerta: 2, tipo: 'condicion_cronica', tipo_nombre: 'Condición crónica', descripcion: 'Epilepsia controlada', clinica_nombre: 'Clínica Sur', usuario_nombre: 'Diego Sur', fecha_registro: '2026-10-01' },
    { id_alerta: 3, tipo: 'medicacion_continua', tipo_nombre: 'Medicación continua', descripcion: 'Medicación continua declarada', clinica_nombre: 'Clínica Norte', fecha_registro: '2026-10-01' },
];
const historia = { success: true, mascota, alertas: { activas, historial: [] }, consultas: [
    { id_consulta: 2, id_cita: null, fecha_hora: '2026-10-01 10:00:00', clinica_nombre: 'Clínica Norte', motivo_consulta: 'Control por vómitos después de comer', diagnostico: 'Gastritis', veterinario: 'Beto Norte', peso: '9.00', temperatura: '38.5', frecuencia_cardiaca: 90, frecuencia_respiratoria: 24, sintomas: [{ codigo: 'sintoma.vomito', etiqueta: 'Vómito' }], anamnesis: 'Come menos desde ayer.', plan_tratamiento: 'Revisión y seguimiento.', tratamientos: [{ medicamento: 'Medicamento registrado', dosis: 'Según registro', via_administracion: 'Oral', duracion: 'Según registro', fecha_inicio: '2026-10-01' }], archivos: [{ id_archivo: 1, nombre_original: 'informe.pdf', extension: 'pdf' }] },
    { id_consulta: 1, id_cita: 4, fecha_hora: '2026-09-01 10:00:00', clinica_nombre: 'Clínica Sur', motivo_consulta: 'Revisión general', diagnostico: 'Sin alteraciones', veterinario: 'Diego Sur', peso: null, es_propia: false, tratamientos: [] },
], vacunas: [{ nombre_vacuna: 'Rabia', fecha_aplicacion: '2026-09-01', fecha_proxima_dosis: '2027-09-01', laboratorio: 'Laboratorio registrado', lote: 'L-01', clinica_nombre: 'Clínica Norte' }], desparasitaciones: [{ producto: 'Producto registrado', tipo: 'interna', fecha_aplicacion: '2026-08-01', fecha_proxima: '2026-11-01', clinica_nombre: 'Clínica Sur', es_propia: false }] };
const servidor = createServer((req, res) => {
    const url = new URL(req.url, 'http://localhost');
    const accion = url.searchParams.get('action');
    if (vistas[accion]) {
        res.setHeader('Content-Type', 'text/html; charset=utf-8');
        // Las librerías externas no son objeto de esta prueba; sí se cargan todos los scripts propios.
        res.end(vistas[accion].replace(/<script[^>]+src="https:[^>]*><\/script>/g, ''));
        return;
    }
    if (accion) {
        res.setHeader('Content-Type', 'application/json');
        res.end(JSON.stringify(accion === 'listar_historial_ajax' ? historia : accion === 'get_mascota_ajax' ? mascota : accion.includes('alertas_medicas') ? { success: true, alertas: historia.alertas } : accion.includes('propietarios') || accion.includes('mascotas') ? [] : { success: true, notificaciones: [], sintomas: [], candidatos: [], datos_faltantes: [], nodos: [] }));
        return;
    }
    const ruta = resolve(raiz, 'public', '.' + decodeURIComponent(url.pathname));
    if (!ruta.startsWith(resolve(raiz, 'public') + sep) || !existsSync(ruta)) {
        res.writeHead(404);
        res.end();
        return;
    }
    res.setHeader('Content-Type', ({ '.css': 'text/css', '.js': 'application/javascript', '.png': 'image/png', '.svg': 'image/svg+xml' })[extname(ruta)] || 'application/octet-stream');
    res.end(readFileSync(ruta));
});
await new Promise(resolver => servidor.listen(0, '127.0.0.1', resolver));
const base = 'http://127.0.0.1:' + servidor.address().port;
const perfil = mkdtempSync(join(tmpdir(), 'zooki-c81-chrome-'));
const chrome = [process.env.CHROME_PATH, 'C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(ruta => ruta && existsSync(ruta));
assert.ok(chrome, 'Chrome o Edge instalado');
const proceso = spawn(chrome, ['--headless=new', '--remote-debugging-port=0', '--user-data-dir=' + perfil, '--no-first-run', '--no-default-browser-check', 'about:blank']);
let ws;
try {
    const direccion = await new Promise((resolver, rechazar) => {
        let salida = '';
        const limite = setTimeout(() => rechazar(new Error('Chrome no arrancó')), 20000);
        proceso.stderr.on('data', trozo => {
            salida += trozo;
            const encontrado = salida.match(/DevTools listening on (ws:\/\/\S+)/);
            if (encontrado) {
                clearTimeout(limite);
                resolver(encontrado[1]);
            }
        });
    });
    const remoto = direccion.replace(/^ws:\/\/([^/]+).*/, 'http://$1');
    const pestaña = await (await fetch(remoto + '/json/new?about:blank', { method: 'PUT' })).json();
    ws = new WebSocket(pestaña.webSocketDebuggerUrl);
    await new Promise(resolver => { ws.onopen = resolver; });
    let secuencia = 0;
    const pendientes = new Map();
    const errores = [];
    ws.onmessage = mensaje => {
        const dato = JSON.parse(mensaje.data);
        if (pendientes.has(dato.id)) {
            const { resolver, rechazar } = pendientes.get(dato.id);
            pendientes.delete(dato.id);
            if (dato.error) rechazar(new Error(dato.error.message));
            else resolver(dato.result);
        } else if (dato.method === 'Runtime.exceptionThrown') errores.push(dato.params.exceptionDetails.exception?.description || dato.params.exceptionDetails.text);
    };
    const enviar = (method, params = {}) => new Promise((resolver, rechazar) => {
        const id = ++secuencia;
        pendientes.set(id, { resolver, rechazar });
        ws.send(JSON.stringify({ id, method, params }));
    });
    const evaluar = async expression => {
        const r = await enviar('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
        if (r.exceptionDetails) throw new Error(r.exceptionDetails.exception?.description || r.exceptionDetails.text);
        return r.result.value;
    };
    const esperar = async expression => {
        for (let i = 0; i < 450; i++) {
            if (await evaluar(expression).catch(() => false)) return;
            await new Promise(resolver => setTimeout(resolver, 100));
        }
        throw new Error('No se cumplió: ' + expression + ' · ' + JSON.stringify(await evaluar('({url:location.href,estado:document.readyState})')) + ' · ' + errores.join(' | '));
    };
    const abrir = async accion => {
        await enviar('Page.navigate', { url: base + '/index.php?action=' + accion });
        await esperar("document.readyState === 'complete' && typeof window.ZookiPacienteCompacto !== 'undefined'");
        await evaluar('localStorage.clear()');
        await new Promise(resolver => setTimeout(resolver, 700));
    };
    const capturar = async nombre => {
        await new Promise(resolver => setTimeout(resolver, 350));
        const r = await enviar('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
        writeFileSync(join(carpeta, nombre + '.png'), Buffer.from(r.data, 'base64'));
    };
    await enviar('Page.enable');
    await enviar('Runtime.enable');
    const medidas = [];
    for (const ancho of [360, 390, 414, 820, 1440]) {
        await enviar('Emulation.setDeviceMetricsOverride', { width: ancho, height: ancho < 768 ? 844 : 1000, deviceScaleFactor: 1, mobile: ancho < 768 });
        await abrir('vet_atencion');
        await esperar("document.querySelector('[data-paciente-alertas]').textContent === '3 alertas'");
        for (let paso = 0; paso < 5; paso++) {
            await evaluar(`document.querySelector('[data-paso="${paso}"]').click()`);
            if (ancho === 820) await evaluar("document.querySelector('.atencion__panel').scrollIntoView({block: 'start'})");
            if (ancho !== 414) await capturar(ancho + '-atencion-paso-' + (paso + 1));
        }
        if (ancho < 768) {
            const medida = await evaluar(`(() => {
                const menu = document.querySelector('.pill-sidebar').getBoundingClientRect();
                const pie = document.getElementById('accionesAtencion').getBoundingClientRect();
                const salir = document.querySelector('.pill-logout').getBoundingClientRect();
                return { ancho: innerWidth, documento: document.documentElement.scrollWidth, menu: menu.toJSON(), pie: pie.toJSON(), salir: salir.toJSON() };
            })()`);
            assert.ok(medida.documento <= ancho, 'Sin desborde en Atención ' + ancho);
            assert.ok(medida.salir.right <= medida.menu.right && medida.salir.left >= medida.menu.left, 'Salir dentro de la barra ' + ancho);
            assert.ok(medida.pie.bottom <= medida.menu.top && medida.pie.top >= 0, 'Pie visible encima del menú ' + ancho);
            assert.ok(await evaluar(`(() => {
                window.scrollTo(0, document.documentElement.scrollHeight);
                const boton = document.getElementById('btnFinalizarAtencion');
                const r = boton.getBoundingClientRect();
                return Boolean(document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2)?.closest('#accionesAtencion'));
            })()`), 'Finalizar sigue accesible al desplazar la página ' + ancho);
            medidas.push(medida);
        }
        await evaluar("window.scrollTo(0, 0)");
        await evaluar("document.querySelector('[data-paciente-ficha]').click()");
        if (ancho !== 414) await capturar(ancho + '-atencion-ficha-abierta');
        await evaluar("document.querySelector('[data-paciente-alertas]').click()");
        if (ancho !== 414) await capturar(ancho + '-atencion-alertas-abiertas');
        await evaluar("document.querySelector('[data-paciente-ficha]').click(); document.querySelector('[data-paciente-alertas]').click(); document.querySelector('[data-tab=historial]').click()");
        await esperar("document.querySelectorAll('#atencionHistorial .historia-fila').length === 4");
        if (ancho !== 414) await capturar(ancho + '-atencion-historial-plegado');
        await evaluar("document.querySelector('#atencionHistorial details').open = true");
        if (ancho !== 414) await capturar(ancho + '-atencion-historial-abierto');
        for (const accion of ['vet_pacientes']) {
            await abrir(accion);
            await evaluar("viewMedicalHistory(7, 'Luna')");
            await esperar("document.querySelectorAll('#historyTimeline details').length === 2");
            if (ancho !== 414) await capturar(ancho + '-' + accion + '-historial-plegado');
            await evaluar("document.querySelector('#historyTimeline details').open = true");
            if (ancho !== 414) await capturar(ancho + '-' + accion + '-historial-abierto');
            assert.ok(await evaluar('document.documentElement.scrollWidth <= innerWidth'), accion + ' sin desborde ' + ancho);
            await evaluar("closeHistorialDrawer(); openConsultationModal(7, 'Luna')");
            await esperar("document.getElementById('consultaPacienteDatos').textContent.includes('HC-000001')");
            if (accion === 'vet_pacientes') continue;
            for (const pestana of ['tabMotivo', 'tabSignos', 'tabResolucion', 'tabArchivos']) {
                await evaluar(`document.querySelector('#modalConsulta [data-tab="${pestana}"]').click()`);
                if (ancho !== 414) await capturar(ancho + '-modal-' + pestana);
            }
            if (ancho < 768) {
                assert.ok(await evaluar(`(() => {
                    const pie = document.querySelector('.consulta-modal__pie').getBoundingClientRect();
                    const cuerpo = document.querySelector('.consulta-modal__cuerpo').getBoundingClientRect();
                    return pie.bottom <= innerHeight + 1 && pie.top > 0 && cuerpo.bottom <= pie.top + 1 && document.documentElement.scrollWidth <= innerWidth;
                })()`), 'Modal: pie y cuerpo dentro del viewport ' + ancho);
            }
            await evaluar("document.querySelector('#modalConsulta [data-paciente-ficha]').click(); document.querySelector('#modalConsulta [data-paciente-alertas]').click()");
            if (ancho !== 414) await capturar(ancho + '-modal-ficha-alertas');
        }
    }
    writeFileSync(join(carpeta, 'medidas.json'), JSON.stringify({ medidas, errores }, null, 2));
    console.log(JSON.stringify({ anchos: [360, 390, 414, 820, 1440], capturas: 'c8-1-despues', errores }, null, 2));
} finally {
    if (ws) ws.close();
    proceso.kill();
    servidor.closeAllConnections();
    servidor.close();
    await new Promise(resolver => setTimeout(resolver, 600));
    const perfilResuelto = realpathSync(perfil);
    assert.equal(dirname(perfilResuelto), realpathSync(tmpdir()), 'Solo se retira un perfil dentro de TEMP');
    assert.ok(basename(perfilResuelto).startsWith('zooki-c81-chrome-'), 'Solo se retira el perfil propio de esta prueba');
    rmSync(perfilResuelto, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
}
