// Genera el .docx de un documento con Pandoc, la plantilla de Word
// (plantilla/referencia.mjs) y el filtro plantilla/word.lua. Al final,
// si Microsoft Word está instalado, actualizarConWord() llena el índice y
// ajusta lo que Pandoc no puede expresar (ver actualizar-word.ps1).

import { execFileSync, spawnSync } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { crearReferencia } from './plantilla/referencia.mjs';
import { leerZip, escribirZip } from './zip.mjs';

function buscarPandoc() {
    const candidatos = [
        process.env.PANDOC_PATH,
        process.env.LOCALAPPDATA && join(process.env.LOCALAPPDATA, 'Pandoc', 'pandoc.exe'),
        'C:/Program Files/Pandoc/pandoc.exe'
    ];
    const ruta = candidatos.find(r => r && existsSync(r));
    if (ruta) return ruta;
    try {
        execFileSync('pandoc', ['--version'], { stdio: 'ignore' });
        return 'pandoc';
    } catch {
        throw new Error('No se encontró Pandoc. Instálalo (winget install JohnMacFarlane.Pandoc) o define PANDOC_PATH; con --sin-word se genera solo el PDF.');
    }
}

let pandoc = null;
let referencia = null;

export function generarWord({ datos, figuras, logo, carpetaTemporal, destino, plantilla }) {
    if (!pandoc) {
        pandoc = buscarPandoc();
        referencia = join(carpetaTemporal, 'referencia.docx');
        writeFileSync(referencia, crearReferencia(pandoc, logo));
    }

    const md = join(carpetaTemporal, `${datos.docId}.md`);
    // Los datos van en JSON aparte y no como metadatos de Pandoc, porque
    // Pandoc lee los metadatos como Markdown: en una ruta de Windows se comía
    // las barras invertidas y convertía el «_» de icon_blue.png en cursiva.
    const meta = join(carpetaTemporal, `${datos.docId}.zooki.json`);
    writeFileSync(md, datos.markdown);
    writeFileSync(meta, JSON.stringify({
        titulo: datos.titulo,
        subtitulo: datos.subtitulo,
        proyecto: datos.proyecto,
        autor: datos.autor,
        programa: datos.programa,
        institucion: datos.institucion,
        centro: datos.centro,
        ciudad: datos.ciudad,
        curso: datos.curso,
        instructor: datos.instructor,
        fecha: datos.fecha,
        historial: datos.historial,
        logo: logo || '',
        figuras
    }));

    const r = spawnSync(pandoc, [
        md,
        '--from=gfm',
        '--to=docx',
        `--reference-doc=${referencia}`,
        `--lua-filter=${join(plantilla, 'word.lua')}`,
        `--output=${destino}`
    ], { encoding: 'utf8', env: { ...process.env, ZOOKI_DATOS: meta } });
    if (r.status !== 0) {
        throw new Error(`Pandoc falló con ${datos.docId}: ${r.stderr || r.error}`);
    }
    completarEncabezado(destino, datos);
    // Un aviso de Pandoc (una imagen que no encontró, por ejemplo) no corta
    // la exportación, pero no se debe perder.
    const avisos = r.stderr.trim();
    if (avisos) {
        const sangrado = avisos.split(/\r?\n/).map(l => `    ${l}`).join('\n');
        process.stdout.write(`\n${sangrado}\n  `);
    }
}

// El encabezado y el pie de la plantilla son comunes; aquí reciben el
// título, la revisión y el pie de este documento (EX-9).
function xml(texto) {
    return String(texto ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function completarEncabezado(destino, datos) {
    const zip = leerZip(readFileSync(destino));
    const valores = {
        '{{PROYECTO}}': datos.proyecto,
        '{{TITULO}}': datos.titulo,
        '{{REVISION}}': datos.revision ? `Rev. ${datos.revision}` : '',
        '{{PIE}}': [datos.institucion, datos.curso].filter(Boolean).join(' · ')
    };
    for (const parte of ['word/header1.xml', 'word/footer1.xml']) {
        if (!zip.has(parte)) continue;
        let contenido = zip.get(parte).toString('utf8');
        for (const [marca, valor] of Object.entries(valores)) contenido = contenido.split(marca).join(xml(valor));
        zip.set(parte, contenido);
    }
    writeFileSync(destino, escribirZip(zip));
}

// Word llena el índice (EX-4), repite el encabezado de cada tabla en cada
// hoja, no parte filas y mantiene enteras las tablas cortas (EX-6). Sin
// Word el archivo sirve igual: el índice se llena al actualizar campos.
export function actualizarConWord(archivos, carpetaTemporal) {
    if (process.platform !== 'win32' || !archivos.length) return false;
    const lista = join(carpetaTemporal, 'word.txt');
    writeFileSync(lista, archivos.join('\n'));
    try {
        execFileSync('powershell', [
            '-NoProfile', '-ExecutionPolicy', 'Bypass',
            '-File', join(import.meta.dirname, 'actualizar-word.ps1'),
            '-Lista', lista
        ], { stdio: ['ignore', 'inherit', 'inherit'] });
        return true;
    } catch {
        return false;
    }
}
