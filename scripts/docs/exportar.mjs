// Exporta la documentación publicada a PDF y Word con el formato de la
// plantilla de documentos del proyecto (portada, ficha del documento,
// encabezado y pie) y los rótulos de tablas y figuras de APA 7
// (specs/exportar-documentos.md).
//
//     node scripts/docs/exportar.mjs              # los 9 documentos
//     node scripts/docs/exportar.mjs --solo=ers   # uno (o varios: --solo=ers,mer)
//     node scripts/docs/exportar.mjs --revisar    # cuáles están desactualizados
//     node scripts/docs/exportar.mjs --sin-word   # solo PDF
//
// Requiere Chrome (o Edge) y, para el Word, Pandoc. El PDF lo imprime Chrome
// a partir de plantilla/impresion.html; el Word lo arma Pandoc con
// plantilla/referencia.docx y el filtro plantilla/word.lua.

import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { abrirChrome } from './chrome.mjs';
import { contarPaginas, paginasDeDestinos } from './pdf.mjs';
import { actualizarConWord, generarWord } from './word.mjs';

const AQUI = dirname(fileURLToPath(import.meta.url));
const RAIZ = resolve(AQUI, '../..');
const PLANTILLA = join(AQUI, 'plantilla');
const config = JSON.parse(readFileSync(join(AQUI, 'config.json'), 'utf8'));

// ── Argumentos ──
const args = Object.fromEntries(process.argv.slice(2).map(a => {
    const [clave, valor] = a.replace(/^--/, '').split('=');
    return [clave, valor ?? true];
}));
const SALIDA = resolve(RAIZ, typeof args.salida === 'string' ? args.salida : config.salida);
// Huellas de los .md exportados: --revisar las compara con los actuales.
const MANIFIESTO = join(SALIDA, 'manifiesto.json');

// El catálogo es el de DocsIndex::docsMap(), el mismo del portal y del
// buscador: así un documento nuevo se exporta sin tocar este script.
function documentos() {
    const json = execFileSync('php', ['-r', "require 'helpers/DocsIndex.php'; echo json_encode(DocsIndex::docsMap());"], { cwd: RAIZ });
    const mapa = JSON.parse(json);
    const solo = typeof args.solo === 'string' ? args.solo.split(',') : null;
    return Object.entries(mapa)
        .filter(([id]) => !config.excluir.includes(id))
        .filter(([id]) => !solo || solo.includes(id))
        .map(([id, ruta]) => ({ id, ruta }));
}

const huella = texto => createHash('sha1').update(texto).digest('hex');

function leerManifiesto() {
    try { return JSON.parse(readFileSync(MANIFIESTO, 'utf8')); } catch { return {}; }
}

// ── Datos de la portada y de la ficha del documento ──
// El título sale del H1 («Especificación … — Proyecto Zooki v2.0»): la parte
// antes de la raya es el título del documento; el proyecto sale de config.
// La línea «> **Revisión X** · Conforme al estándar **Y** · …» y la tabla de
// revisiones que la sigue (en el ERS) pasan a la portada y a la ficha del
// documento, y se quitan del cuerpo porque repetirían lo que ya dicen.
function partirTitulo(h1) {
    const partes = h1.split(/\s+[—-]\s+/);
    // «Zooki - Sistema de Gestión…» (README): el nombre del producto va primero.
    if (partes.length > 1 && /^zooki$/i.test(partes[0].trim())) return partes.slice(1).join(' — ');
    return partes[0].trim();
}

function celdas(fila) {
    return fila.trim().replace(/^\||\|$/g, '').split('|').map(c => c.trim().replace(/\*\*/g, ''));
}

function prepararDocumento(markdown) {
    const lineas = markdown.split(/\r?\n/);
    let titulo = '';
    let subtitulo = '';
    let revision = '';
    let historial = null;
    const cuerpo = [];

    for (let i = 0; i < lineas.length; i++) {
        const linea = lineas[i];
        if (!titulo && /^#\s+/.test(linea)) {
            titulo = partirTitulo(linea.replace(/^#\s+/, '').trim());
            continue;
        }
        const rev = !subtitulo && linea.match(/^>\s*\*\*Revisi[oó]n\s+([\d.]+)\*\*(.*)$/);
        if (rev) {
            revision = rev[1];
            const estandar = rev[2].match(/est[aá]ndar\s+\*\*([^*]+)\*\*/i);
            subtitulo = estandar ? `Revisión ${revision} · ${estandar[1]}` : `Revisión ${revision}`;
            continue;
        }
        // Tabla de revisiones antes de la primera sección (la del ERS).
        if (!historial && linea.startsWith('|') && !cuerpo.some(l => /^##\s/.test(l))
            && /revisi[oó]n/i.test(linea) && /^\|[\s:|-]+\|$/.test((lineas[i + 1] || '').trim())) {
            const filas = [];
            let j = i + 2;
            while (j < lineas.length && lineas[j].startsWith('|')) filas.push(celdas(lineas[j++]));
            historial = { encabezado: celdas(linea), filas };
            i = j - 1;
            continue;
        }
        cuerpo.push(linea);
    }

    return { titulo, subtitulo, revision, historial, markdown: cuerpo.join('\n').trim() };
}

// Documentos sin tabla de revisiones: una fila con la revisión vigente.
function historialPorDefecto(datos) {
    if (datos.historial) return datos.historial;
    const hoy = new Intl.DateTimeFormat('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date());
    return {
        encabezado: ['Fecha', 'Revisión', 'Autor'],
        filas: [[hoy, datos.revision || '—', config.autor]]
    };
}

// «Octubre de 2026», como en la portada de la plantilla.
function mesYAnio() {
    const texto = new Intl.DateTimeFormat('es-CO', { month: 'long', year: 'numeric' }).format(new Date());
    return texto.charAt(0).toUpperCase() + texto.slice(1);
}

function logoComoDataUri() {
    if (!config.logo) return null;
    const ruta = join(RAIZ, config.logo);
    return `data:image/png;base64,${readFileSync(ruta).toString('base64')}`;
}

// ── PDF ──
function paginaImpresion(datos, carpetaTemporal, id) {
    const plantilla = readFileSync(join(PLANTILLA, 'impresion.html'), 'utf8');
    // <\/script> y <!-- dentro del JSON cerrarían la etiqueta antes de tiempo.
    const json = JSON.stringify(datos).replace(/</g, '\\u003c');
    const html = plantilla
        .replace('{{BASE}}', pathToFileURL(PLANTILLA + '/').href)
        .replace('{{TITULO}}', datos.titulo.replace(/[<&]/g, ''))
        .replace('{{DATOS}}', () => json);
    const ruta = join(carpetaTemporal, `${id}.html`);
    writeFileSync(ruta, html);
    return pathToFileURL(ruta).href;
}

// Se imprime, se lee en qué hoja quedó cada destino y se corrige, hasta que
// el resultado no cambie:
//  - EX-5/EX-6: un título o un rótulo de tabla que quedó en otra hoja que lo
//    que le sigue recibe un salto de hoja (forzarSalto).
//  - EX-4: el índice recibe el número de página de cada título. Reserva el
//    ancho del número desde la primera pasada, así que no cambia de largo.
const MAX_PASADAS = 8;
const ANCHO_MAX_FIGURA_CM = 16.5;

// EX-7: los diagramas que impresion.js pasó a PNG, guardados para el Word.
function guardarFiguras(figuras, carpetaTemporal, id) {
    return figuras.map((f, i) => {
        const ruta = join(carpetaTemporal, `${id}-figura-${i + 1}.png`);
        writeFileSync(ruta, Buffer.from(f.png.split(',')[1], 'base64'));
        return { ruta, ancho: `${Math.min(f.anchoCm, ANCHO_MAX_FIGURA_CM).toFixed(2)}cm` };
    });
}

async function generarPdf(chrome, datos, carpetaTemporal, id) {
    const pestana = await chrome.nuevaPestana();
    try {
        await pestana.abrir(paginaImpresion(datos, carpetaTemporal, id));
        const estado = await pestana.esperar();
        const pares = estado.pares || [];
        const figuras = guardarFiguras(estado.figuras || [], carpetaTemporal, id);

        let pdf = await pestana.imprimirPdf();
        let anteriores = null;
        for (let pasada = 1; ; pasada++) {
            const destinos = paginasDeDestinos(pdf);
            const solos = pares
                .filter(([a, b]) => destinos[a] && destinos[b] && destinos[a] < destinos[b])
                .map(([a]) => a);
            const huellaDestinos = JSON.stringify(destinos);

            if (!solos.length && huellaDestinos === anteriores) break;
            if (pasada > MAX_PASADAS) {
                console.warn(`\n  ! ${id}: la paginación no se estabilizó en ${MAX_PASADAS} pasadas.`);
                break;
            }

            await pestana.evaluar(`window.__zooki.forzarSalto(${JSON.stringify(solos)})`);
            await pestana.evaluar(`window.__zooki.ponerPaginas(${huellaDestinos})`);
            anteriores = solos.length ? null : huellaDestinos;
            pdf = await pestana.imprimirPdf();
        }

        return { pdf, paginas: contarPaginas(pdf), figuras };
    } finally {
        pestana.cerrar();
    }
}

// ── Principal ──
async function main() {
    const lista = documentos();
    const manifiesto = leerManifiesto();

    if (args.revisar) {
        const viejos = lista.filter(d => manifiesto[d.id] !== huella(readFileSync(d.ruta)));
        if (!viejos.length) {
            console.log('Las descargas están al día.');
        } else {
            console.log('Desactualizados (el .md cambió desde la última exportación):');
            viejos.forEach(d => console.log(`  - ${d.id}`));
        }
        return;
    }

    mkdirSync(SALIDA, { recursive: true });
    const temporal = mkdtempSync(join(tmpdir(), 'zooki-docs-'));
    const chrome = await abrirChrome();
    const logo = config.logo ? join(RAIZ, config.logo) : null;
    const generados = [];
    const comunes = {
        autor: config.autor,
        programa: config.programa,
        institucion: config.institucion,
        centro: config.centro,
        ciudad: config.ciudad,
        curso: config.curso,
        instructor: config.instructor,
        proyecto: config.proyecto,
        fecha: mesYAnio(),
        logo: logoComoDataUri()
    };

    try {
        for (const doc of lista) {
            const fuente = readFileSync(doc.ruta, 'utf8');
            const datos = { ...comunes, ...prepararDocumento(fuente), docId: doc.id };
            datos.historial = historialPorDefecto(datos);
            process.stdout.write(`- ${doc.id}: PDF… `);

            const { pdf, paginas, figuras } = await generarPdf(chrome, datos, temporal, doc.id);
            writeFileSync(join(SALIDA, `${doc.id}.pdf`), pdf);
            process.stdout.write(`${paginas} páginas`);

            if (!args['sin-word']) {
                process.stdout.write(' · Word… ');
                const destino = join(SALIDA, `${doc.id}.docx`);
                generarWord({ datos, figuras, logo, carpetaTemporal: temporal, destino, plantilla: PLANTILLA });
                generados.push(destino);
                process.stdout.write('listo');
            }
            process.stdout.write('\n');

            manifiesto[doc.id] = huella(fuente);
            writeFileSync(MANIFIESTO, JSON.stringify(manifiesto, null, 2) + '\n');
        }

        if (generados.length) {
            console.log('\nRetoque de los Word (índice y tablas)…');
            if (!actualizarConWord(generados, temporal)) {
                console.log('  No se pudo usar Word: el índice se llena al actualizar campos al abrir cada archivo.');
            }
        }
    } finally {
        chrome.cerrar();
        rmSync(temporal, { recursive: true, force: true });
    }

    console.log(`\nArchivos en ${SALIDA}`);
}

main().catch(error => {
    console.error(`\nERROR: ${error.message}`);
    process.exit(1);
});
