// Verificación visual de C8.2: node tests/Frontend/apoyo/c82-navegador.mjs
// Vistas, hojas y scripts reales (arnés c81-vistas.php) con respuestas HTTP sintéticas; sin .env ni base de datos.
import { execFileSync } from 'node:child_process';
import { createServer } from 'node:http';
import { createRequire } from 'node:module';
import { existsSync, readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { join, resolve, extname, sep } from 'node:path';
import assert from 'node:assert/strict';

const require = createRequire(import.meta.url);
const { buscarChrome, abrirChrome } = require('./chrome-cdp.cjs');
const raiz = resolve(import.meta.dirname, '../../..');
const carpeta = join(raiz, 'specs/referencias/m2-pulido/c8-2-despues');
mkdirSync(carpeta, { recursive: true });
const vistas = Object.fromEntries(['vet_atencion', 'vet_consultas', 'vet_pacientes'].map(accion => [accion, execFileSync('php', [join(import.meta.dirname, 'c81-vistas.php'), accion], { cwd: raiz, encoding: 'utf8' })]));
const mascota = { id_mascota: 7, nombre: 'Luna', nombre_especie: 'Canino', nombre_raza: 'Mestizo', peso: '9.00', numero_historia_clinica: 'HC-000001', propietario_nombre: 'Fabio Propietario', fecha_nacimiento: '2022-01-01' };
const activas = [
    { id_alerta: 1, tipo: 'alergia', tipo_nombre: 'Alergia', descripcion: 'Reacción cutánea con la amoxicilina', clinica_nombre: 'Clínica Norte', fecha_registro: '2026-10-01', puede_desactivar: true },
    { id_alerta: 2, tipo: 'condicion_cronica', tipo_nombre: 'Condición crónica', descripcion: 'Epilepsia controlada', clinica_nombre: 'Clínica Sur', fecha_registro: '2026-10-01' },
];
const historia = { success: true, mascota, alertas: { activas, historial: [] }, consultas: [
    { id_consulta: 3, id_cita: null, fecha_hora: '2026-10-01 10:00:00', clinica_nombre: 'Clínica Norte', motivo_consulta: 'Control por vómitos después de comer', diagnostico: 'Gastritis', veterinario: 'Beto Norte', peso: '9.00', temperatura: '38.5', frecuencia_cardiaca: 90, frecuencia_respiratoria: 24, sintomas: [{ codigo: 'sintoma.vomito', etiqueta: 'Vómito' }], tratamientos: [{ medicamento: 'Medicamento registrado', dosis: 'Según registro', via_administracion: 'Oral', duracion: 'Según registro', fecha_inicio: '2026-10-01' }], archivos: [] },
    { id_consulta: 2, id_cita: 4, fecha_hora: '2026-09-05 10:00:00', clinica_nombre: 'Clínica Veterinaria del Norte de Bogotá', motivo_consulta: 'Revisión de piel', diagnostico: 'Dermatitis alérgica por picadura de pulgas con infección secundaria', veterinario: 'Diego Sur', peso: null, es_propia: false, tratamientos: [] },
    { id_consulta: 1, id_cita: 2, fecha_hora: '2026-08-15 09:00:00', clinica_nombre: 'Clínica Sur', motivo_consulta: 'Revisión general', diagnostico: '', veterinario: 'Diego Sur', peso: '8.70', es_propia: false, tratamientos: [] },
], vacunas: [{ nombre_vacuna: 'Rabia', fecha_aplicacion: '2026-09-01', fecha_proxima_dosis: '2027-09-01', laboratorio: 'Laboratorio registrado', lote: 'L-01', clinica_nombre: 'Clínica Norte' }], desparasitaciones: [{ producto: 'Producto registrado', tipo: 'interna', fecha_aplicacion: '2026-08-01', fecha_proxima: '2026-11-01', clinica_nombre: 'Clínica Sur', es_propia: false }] };
const tipos = { '.css': 'text/css', '.js': 'application/javascript', '.png': 'image/png', '.svg': 'image/svg+xml' };
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
        const cuerpo = accion === 'listar_historial_ajax' ? historia : accion === 'get_mascota_ajax' ? mascota : accion.includes('alertas_medicas') ? { success: true, alertas: historia.alertas } : accion.includes('propietarios') || accion.includes('mascotas') ? [] : { success: true, notificaciones: [] };
        res.end(JSON.stringify(cuerpo));
        return;
    }
    const ruta = resolve(raiz, 'public', '.' + decodeURIComponent(url.pathname));
    if (!ruta.startsWith(resolve(raiz, 'public') + sep) || !existsSync(ruta)) {
        res.writeHead(404);
        res.end();
        return;
    }
    res.setHeader('Content-Type', tipos[extname(ruta)] || 'application/octet-stream');
    res.end(readFileSync(ruta));
});
await new Promise(resolver => servidor.listen(0, '127.0.0.1', resolver));
const base = 'http://127.0.0.1:' + servidor.address().port;
const chrome = buscarChrome();
assert.ok(chrome, 'Chrome o Chromium instalado (CHROME_PATH)');
const navegador = await abrirChrome(chrome);
const { enviar, evaluar, esperar } = navegador;

// Cada resumen: sin recortes, cada palabra entera dentro de la fila, y si hay dos líneas el contexto va abajo.
const medirFilas = selector => evaluar(`(() => [...document.querySelectorAll(${JSON.stringify(selector)} + ' .historia-fila__resumen')].filter(r => r.offsetParent).map(resumen => {
    const principal = resumen.querySelector('.historia-fila__linea--principal').getBoundingClientRect();
    const contexto = resumen.querySelector('.historia-fila__linea--contexto').getBoundingClientRect();
    const limites = resumen.getBoundingClientRect();
    const rotas = [];
    const caminante = document.createTreeWalker(resumen, NodeFilter.SHOW_TEXT);
    while (caminante.nextNode()) {
        const nodo = caminante.currentNode;
        for (const c of nodo.textContent.matchAll(/\\S+/g)) {
            const rango = document.createRange();
            rango.setStart(nodo, c.index);
            rango.setEnd(nodo, c.index + c[0].length);
            const cajas = [...rango.getClientRects()];
            if (cajas.length !== 1 || cajas[0].right > limites.right + 1 || cajas[0].left < limites.left - 1) rotas.push(c[0]);
        }
    }
    return { texto: resumen.innerText.replace(/\\s+/g, ' ').trim(), dosLineas: contexto.top >= principal.bottom - 1, desborde: resumen.scrollWidth > resumen.clientWidth + 1, rotas };
}))()`);

// Distancia vertical entre bloques consecutivos visibles del cajón.
const medirCajon = selector => evaluar(`(() => {
    const bloques = [...document.querySelectorAll(${JSON.stringify(selector)} + ' .historial-cajon > *')].filter(el => el.getClientRects().length && el.getBoundingClientRect().height > 0);
    return bloques.slice(1).map((el, i) => Math.round(el.getBoundingClientRect().top - bloques[i].getBoundingClientRect().bottom));
})()`);

const capturar = async nombre => {
    await new Promise(resolver => setTimeout(resolver, 350));
    const r = await enviar('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
    writeFileSync(join(carpeta, nombre + '.png'), Buffer.from(r.data, 'base64'));
};
const abrir = async accion => {
    await enviar('Page.navigate', { url: base + '/index.php?action=' + accion });
    await esperar("document.readyState === 'complete' && typeof window.ZookiPacienteCompacto !== 'undefined'");
    await evaluar('localStorage.clear()');
    await new Promise(resolver => setTimeout(resolver, 600));
};
const informe = [];
const revisarFilas = (filas, donde) => {
    assert.ok(filas.length > 0, 'Hay filas visibles: ' + donde);
    for (const fila of filas) {
        assert.equal(fila.desborde, false, 'Sin desborde: ' + donde + ' · ' + fila.texto);
        assert.deepEqual(fila.rotas, [], 'Palabras completas: ' + donde + ' · ' + fila.texto);
        assert.doesNotMatch(fila.texto, /\b\d\/\d{1,2}\/\d{4}\b/, 'Fecha dd/mm/aaaa: ' + donde);
    }
    informe.push({ donde, filas });
};
try {
    for (const ancho of [360, 390, 820, 1440]) {
        await enviar('Emulation.setDeviceMetricsOverride', { width: ancho, height: ancho < 768 ? 844 : 1000, deviceScaleFactor: 1, mobile: ancho < 768 });
        await abrir('vet_atencion');
        await evaluar("document.querySelector('[data-tab=historial]').click()");
        await esperar("document.querySelectorAll('#atencionHistorial .historia-fila').length === 5");
        revisarFilas(await medirFilas('#atencionHistorial'), 'Atención ' + ancho);
        await evaluar("document.querySelector('[data-tab=historial]').scrollIntoView({ block: 'start' })");
        await capturar(ancho + '-atencion-historial-plegado');
        for (const accion of ['vet_pacientes', 'vet_consultas']) {
            await abrir(accion);
            await evaluar("viewMedicalHistory(7, 'Luna')");
            await esperar("document.querySelectorAll('#historyTimeline details').length === 3");
            await new Promise(resolver => setTimeout(resolver, 400));
            const cajon = accion === 'vet_pacientes' ? '#drawerHistorial' : '#historialDrawer';
            revisarFilas(await medirFilas(cajon), accion + ' ' + ancho);
            const huecos = await medirCajon(cajon);
            assert.ok(huecos.every(hueco => hueco <= 16), 'Cajón sin huecos grandes ' + accion + ' ' + ancho + ': ' + huecos.join(', '));
            informe.push({ donde: 'huecos ' + accion + ' ' + ancho, huecos });
            if (ancho === 1440 || ancho === 390) await capturar(ancho + '-' + accion + '-historial-plegado');
        }
    }
    assert.deepEqual(navegador.errores, []);
    writeFileSync(join(carpeta, 'medidas.json'), JSON.stringify(informe, null, 2));
    console.log('C8.2: ' + informe.length + ' mediciones sin recortes; capturas en specs/referencias/m2-pulido/c8-2-despues/');
} finally {
    await navegador.cerrar();
    servidor.closeAllConnections();
    servidor.close();
}
