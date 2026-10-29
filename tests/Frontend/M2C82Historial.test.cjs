/** C8.2: resumen del historial legible en cualquier ancho, cajón compacto y fechas dd/mm/aaaa. */
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { createServer } = require('node:http');
const { execFileSync } = require('node:child_process');
const { crearDocumento } = require('./apoyo/dom-datos.cjs');
const { buscarChrome, abrirChrome } = require('./apoyo/chrome-cdp.cjs');

const raiz = path.join(__dirname, '../..');
const leer = archivo => fs.readFileSync(path.join(raiz, archivo), 'utf8');

function modulo() {
    const documento = crearDocumento();
    const contexto = { document: documento, window: {}, console };
    vm.createContext(contexto);
    vm.runInContext(leer('public/js/fechas.js') + '\n' + leer('public/js/medical-module.js'), contexto);
    return contexto;
}

const consulta = {
    fecha_hora: '2026-10-01 10:00:00', clinica_nombre: 'Clínica Norte', motivo_consulta: 'Control por vómitos después de comer',
    diagnostico: 'Gastritis', id_cita: null, peso: '9.00',
    tratamientos: [{ medicamento: 'Medicamento registrado', dosis: 'Según registro', via_administracion: 'Oral', duracion: 'Según registro', fecha_inicio: '2026-10-01' }],
};
const consultaLarga = {
    fecha_hora: '2026-09-05 08:30:00', clinica_nombre: 'Clínica Veterinaria del Norte de Bogotá', motivo_consulta: 'Revisión general',
    diagnostico: 'Dermatitis alérgica por picadura de pulgas con infección secundaria', id_cita: 4, peso: null, tratamientos: [],
};
const vacuna = { nombre_vacuna: 'Rabia', fecha_aplicacion: '2026-09-01', fecha_proxima_dosis: '2027-09-01', laboratorio: 'Laboratorio registrado', clinica_nombre: 'Clínica Norte' };
const desparasitacion = { producto: 'Producto registrado', tipo: 'interna', fecha_aplicacion: '2026-08-01', fecha_proxima: '2026-11-01', clinica_nombre: 'Clínica Sur' };

const resumen = html => html.split('</summary>')[0];
const linea = (html, nombre) => (resumen(html).match(new RegExp(`historia-fila__linea--${nombre}">([\\s\\S]*?)</span>\\s*(?:<span class="historia-fila__linea|</span>\\s*<i)`)) || [])[1] || '';

test('C8.2: fechaHistorial da dd/mm/aaaa sin retroceder un día por UTC', () => {
    const c = modulo();
    assert.equal(c.fechaHistorial('2026-10-01'), '01/10/2026');
    assert.equal(c.fechaHistorial('2026-09-05 23:59:59'), '05/09/2026');
    assert.equal(c.fechaHistorial('2026-01-31T00:00:00'), '31/01/2026');
    assert.equal(c.fechaHistorial(null), 'Sin fecha');
    assert.equal(c.fechaHistorial('fecha rota'), 'Sin fecha');
});

test('C8.2: todas las fechas de las filas usan la misma función (consulta, tratamiento, vacuna y desparasitación)', () => {
    const c = modulo();
    const filaConsulta = c.filaHistorialCompacto(consulta, 'consulta');
    assert.match(resumen(filaConsulta), /<time class="historia-fila__fecha">01\/10\/2026<\/time>/);
    assert.match(filaConsulta, /data-etiqueta="Inicio">01\/10\/2026</, 'El inicio del tratamiento también va en dd/mm/aaaa');
    const filaVacuna = c.filaHistorialCompacto(vacuna, 'vacuna');
    assert.match(resumen(filaVacuna), />01\/09\/2026</);
    assert.match(filaVacuna, /Próxima aplicación:<\/strong> 01\/09\/2027/);
    const filaDesparasitacion = c.filaHistorialCompacto(desparasitacion, 'desparasitacion');
    assert.match(resumen(filaDesparasitacion), />01\/08\/2026</);
    assert.match(filaDesparasitacion, /Próxima aplicación:<\/strong> 01\/11\/2026/);
    assert.match(c.filaHistorialCompacto({ producto: 'X', fecha_aplicacion: '2026-08-01' }, 'desparasitacion'), /Próxima aplicación:<\/strong> Sin programar/);
    const medical = leer('public/js/medical-module.js');
    const tabla = medical.slice(medical.indexOf('function tablaTratamientos'), medical.indexOf('function listaAdjuntos'));
    const renderer = medical.slice(medical.indexOf('// fechaHistorial se comparte'), medical.indexOf('// M2 C5a: el historial abierto'));
    for (const parte of [tabla, renderer]) assert.doesNotMatch(parte, /toLocaleDateString|new Date\(/, 'Las filas no dependen del formato regional del navegador');
    assert.match(leer('views/vet/atencion.php'), /\$fechaCorta = fn\(\$fecha\) => empty\(\$fecha\) \? '—' : date\('d\/m\/Y'/, 'Las filas del servidor usan el mismo formato');
});

test('C8.2: línea 1 lleva fecha y diagnóstico (o el motivo si no hay); línea 2, clínica y chips', () => {
    const c = modulo();
    const fila = c.filaHistorialCompacto(consulta, 'consulta');
    const principal = linea(fila, 'principal');
    const contexto = linea(fila, 'contexto');
    assert.match(principal, /01\/10\/2026[\s\S]*historia-fila__titulo">Gastritis</);
    assert.doesNotMatch(principal, /Clínica Norte|Sin cita|Kg/);
    assert.match(contexto, /Clínica Norte[\s\S]*Sin cita[\s\S]*9.00 Kg[\s\S]*1 tratamiento/);
    const sinDiagnostico = c.filaHistorialCompacto({ ...consulta, diagnostico: '' }, 'consulta');
    assert.match(linea(sinDiagnostico, 'principal'), /historia-fila__titulo">Control por vómitos después de comer</);
    const filaVacuna = c.filaHistorialCompacto(vacuna, 'vacuna');
    assert.match(linea(filaVacuna, 'principal'), /historia-fila__titulo">Rabia</);
    assert.match(linea(filaVacuna, 'contexto'), /Clínica Norte[\s\S]*Laboratorio registrado/);
    assert.match(linea(c.filaHistorialCompacto(desparasitacion, 'desparasitacion'), 'contexto'), /Clínica Sur[\s\S]*interna/);
    const escapada = c.filaHistorialCompacto({ ...consulta, diagnostico: '<b>x</b>', clinica_nombre: '<Sur>' }, 'consulta');
    assert.doesNotMatch(escapada, /<b>x<\/b>|<Sur>/);
});

test('C8.2: las filas que pinta el servidor en Atención usan los mismos dos grupos', () => {
    const atencion = leer('views/vet/atencion.php');
    const filaConsulta = atencion.slice(atencion.indexOf('<details class="historia-fila">'), atencion.indexOf('</summary>', atencion.indexOf('<details class="historia-fila">')));
    assert.match(filaConsulta, /historia-fila__linea--principal[\s\S]*historia-fila__fecha[\s\S]*historia-fila__titulo[\s\S]*historia-fila__linea--contexto[\s\S]*historia-fila__clinica[\s\S]*historia-fila__chips/);
    const php = `$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); $fechaCorta = fn($f) => empty($f) ? '—' : date('d/m/Y', strtotime($f)); $registroHistoria = ['fecha_aplicacion' => '2026-09-01', 'nombre_vacuna' => 'Rabia', 'laboratorio' => 'Laboratorio registrado', 'clinica_nombre' => 'Clínica Norte']; include 'views/partials/historial_procedimiento.php';`;
    let salida;
    try {
        salida = execFileSync('php', ['-r', php], { cwd: raiz, encoding: 'utf8' });
    } catch (error) {
        assert.ok(error.code === 'ENOENT', 'php -r falló: ' + error.message);
        return;
    }
    assert.match(linea(salida, 'principal'), /01\/09\/2026[\s\S]*historia-fila__titulo">Rabia</);
    assert.match(linea(salida, 'contexto'), /Clínica Norte[\s\S]*historia-fila__chip">Laboratorio registrado/);
});

test('C8.2: el resumen no recorta texto con puntos suspensivos y el cajón tiene un solo espaciado', () => {
    const css = leer('public/css/consulta-clinica.css');
    const reglasFila = [...css.matchAll(/([^{}]*historia-fila[^{}]*)\{([^}]*)\}/g)].filter(([, selector]) => !selector.includes('__detalle'));
    for (const [, selector, cuerpo] of reglasFila) {
        assert.doesNotMatch(cuerpo, /text-overflow:\s*ellipsis|white-space:\s*nowrap/, selector.trim());
    }
    assert.match(css, /\.historia-fila__lineas \{[^}]*flex-wrap: wrap/);
    assert.match(css, /\.drawer-body\.historial-cajon \{[^}]*gap: 0\.85rem/);
    assert.match(css, /\.historial-cajon \.history-timeline \{[^}]*max-height: none/);
    // «Consultas médicas» se retiró (decisión del dueño, 2026-10-22): el cajón queda en Pacientes.
    for (const vista of ['views/vet/pacientes.php']) {
        const contenido = leer(vista);
        assert.match(contenido, /class="drawer-body historial-cajon"/, vista);
        assert.equal((contenido.match(/historial-cajon__seccion/g) || []).length, 2, vista + ': vacunas y línea de tiempo');
        assert.doesNotMatch(contenido, /\sstyle=|\sonclick=/);
    }
    const identidad = leer('public/css/identidad.css');
    const sinComentarios = css.replace(/\/\*[\s\S]*?\*\//g, '');
    assert.doesNotMatch(sinComentarios, /#[a-f\d]{3,8}\b|rgba?\(|hsla?\(/i);
    for (const variable of sinComentarios.matchAll(/var\((--z-[\w-]+)/g)) assert.ok(identidad.includes(variable[1] + ':'), variable[1]);
});

// Medición real: el resumen pasa a dos líneas cuando el ancho no alcanza y nunca corta una palabra.
const chrome = buscarChrome();
test('C8.2 (Chrome): dos líneas cuando no cabe, una cuando sí, y ninguna palabra truncada', { skip: chrome ? false : 'Sin Chrome ni Chromium; se mide con c82-navegador.mjs' }, async () => {
    const c = modulo();
    const filas = [
        c.filaHistorialCompacto(consulta, 'consulta'),
        c.filaHistorialCompacto(consultaLarga, 'consulta'),
        c.filaHistorialCompacto(vacuna, 'vacuna'),
        c.filaHistorialCompacto(desparasitacion, 'desparasitacion'),
    ].join('');
    // Anchos útiles de la fila: celular 360 y 390, cajón de Pacientes a 1440, Atención a 820 y escritorio.
    const anchos = [296, 326, 410, 700, 1100];
    const pagina = `<!doctype html><html lang="es"><head><meta charset="utf-8">
        <link rel="stylesheet" href="/css/identidad.css"><link rel="stylesheet" href="/css/consulta-clinica.css"></head>
        <body>${anchos.map(ancho => `<div class="caja" data-ancho="${ancho}">${filas}</div>`).join('')}
        <link rel="stylesheet" href="/apoyo.css"></body></html>`;
    const servidor = createServer((req, res) => {
        if (req.url === '/') {
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            res.end(pagina);
            return;
        }
        if (req.url === '/apoyo.css') {
            res.setHeader('Content-Type', 'text/css');
            res.end(anchos.map(ancho => `.caja[data-ancho="${ancho}"] { width: ${ancho}px; margin-bottom: 20px; }`).join('\n'));
            return;
        }
        const ruta = path.join(raiz, 'public', path.normalize(req.url).replace(/^([/\\])+/, ''));
        if (!ruta.startsWith(path.join(raiz, 'public')) || !fs.existsSync(ruta)) {
            res.writeHead(404);
            res.end();
            return;
        }
        res.setHeader('Content-Type', 'text/css');
        res.end(fs.readFileSync(ruta));
    });
    await new Promise(resolver => servidor.listen(0, '127.0.0.1', resolver));
    const navegador = await abrirChrome(chrome);
    try {
        await navegador.enviar('Emulation.setDeviceMetricsOverride', { width: 1200, height: 900, deviceScaleFactor: 1, mobile: false });
        await navegador.enviar('Page.navigate', { url: 'http://127.0.0.1:' + servidor.address().port + '/' });
        await navegador.esperar("document.readyState === 'complete' && document.styleSheets.length === 3");
        const medidas = await navegador.evaluar(`(() => [...document.querySelectorAll('.caja')].map(caja => ({
            ancho: Number(caja.dataset.ancho),
            filas: [...caja.querySelectorAll('.historia-fila__resumen')].map(resumen => {
                const principal = resumen.querySelector('.historia-fila__linea--principal').getBoundingClientRect();
                const contexto = resumen.querySelector('.historia-fila__linea--contexto').getBoundingClientRect();
                const limites = resumen.getBoundingClientRect();
                const recortes = [...resumen.querySelectorAll('*')].filter(el => el.scrollWidth > el.clientWidth + 1 && getComputedStyle(el).overflowX !== 'visible').map(el => el.className);
                const palabras = [];
                const caminante = document.createTreeWalker(resumen, NodeFilter.SHOW_TEXT);
                while (caminante.nextNode()) {
                    const nodo = caminante.currentNode;
                    for (const coincidencia of nodo.textContent.matchAll(/\\S+/g)) {
                        const rango = document.createRange();
                        rango.setStart(nodo, coincidencia.index);
                        rango.setEnd(nodo, coincidencia.index + coincidencia[0].length);
                        const cajas = [...rango.getClientRects()];
                        const visible = cajas.every(r => r.left >= limites.left - 1 && r.right <= limites.right + 1);
                        palabras.push({ palabra: coincidencia[0], partes: cajas.length, visible });
                    }
                }
                return { dosLineas: contexto.top >= principal.bottom - 1, desborde: resumen.scrollWidth > resumen.clientWidth + 1, recortes, palabras };
            }),
        })))()`);
        for (const { ancho, filas } of medidas) {
            for (const [indice, fila] of filas.entries()) {
                const donde = ancho + ' px, fila ' + (indice + 1);
                assert.equal(fila.desborde, false, 'Sin desborde horizontal: ' + donde);
                assert.deepEqual(fila.recortes, [], 'Nada recortado: ' + donde);
                for (const palabra of fila.palabras) {
                    assert.equal(palabra.partes, 1, `«${palabra.palabra}» queda completa en una línea: ${donde}`);
                    assert.ok(palabra.visible, `«${palabra.palabra}» se ve entera: ${donde}`);
                }
            }
        }
        const porAncho = Object.fromEntries(medidas.map(({ ancho, filas }) => [ancho, filas.map(fila => fila.dosLineas)]));
        for (const ancho of [296, 326]) assert.equal(porAncho[ancho][0], true, 'La consulta con chips pasa a dos líneas a ' + ancho + ' px');
        for (const ancho of [296, 326, 410, 700]) assert.equal(porAncho[ancho][1], true, 'El diagnóstico largo deja clínica y chips en la segunda línea a ' + ancho + ' px');
        assert.equal(porAncho[1100][0], false, 'Con espacio, la consulta cabe en una línea');
        assert.equal(porAncho[1100][2], false, 'Con espacio, la vacuna cabe en una línea');
        assert.deepEqual(navegador.errores, []);
    } finally {
        await navegador.cerrar();
        servidor.close();
    }
});
