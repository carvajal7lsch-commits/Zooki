// Lectura y escritura mínima de archivos zip (un .docx es un zip). Alcanza
// para abrir la plantilla de Word de Pandoc, cambiar sus XML y volver a
// empacarla, sin sumar una dependencia de npm. No maneja zip64 ni cifrado:
// una plantilla de Word nunca los usa.

import { crc32, deflateRawSync, inflateRawSync } from 'node:zlib';

/** @returns {Map<string, Buffer>} ruta interna → contenido */
export function leerZip(buffer) {
    // Fin del directorio central: firma 0x06054b50 cerca del final.
    let fin = buffer.length - 22;
    while (fin >= 0 && buffer.readUInt32LE(fin) !== 0x06054b50) fin--;
    if (fin < 0) throw new Error('No es un archivo zip válido.');

    const total = buffer.readUInt16LE(fin + 10);
    let pos = buffer.readUInt32LE(fin + 16);
    const archivos = new Map();

    for (let i = 0; i < total; i++) {
        if (buffer.readUInt32LE(pos) !== 0x02014b50) throw new Error('Directorio central del zip dañado.');
        const metodo = buffer.readUInt16LE(pos + 10);
        const comprimido = buffer.readUInt32LE(pos + 20);
        const largoNombre = buffer.readUInt16LE(pos + 28);
        const largoExtra = buffer.readUInt16LE(pos + 30);
        const largoComentario = buffer.readUInt16LE(pos + 32);
        const local = buffer.readUInt32LE(pos + 42);
        const nombre = buffer.toString('utf8', pos + 46, pos + 46 + largoNombre);

        const inicioDatos = local + 30 + buffer.readUInt16LE(local + 26) + buffer.readUInt16LE(local + 28);
        const datos = buffer.subarray(inicioDatos, inicioDatos + comprimido);
        archivos.set(nombre, metodo === 8 ? inflateRawSync(datos) : Buffer.from(datos));

        pos += 46 + largoNombre + largoExtra + largoComentario;
    }
    return archivos;
}

/** @param {Map<string, Buffer|string>} archivos */
export function escribirZip(archivos) {
    const locales = [];
    const central = [];
    let desplazamiento = 0;

    for (const [nombre, contenido] of archivos) {
        const datos = Buffer.isBuffer(contenido) ? contenido : Buffer.from(contenido, 'utf8');
        const comprimido = deflateRawSync(datos);
        const nombreBytes = Buffer.from(nombre, 'utf8');
        const crc = crc32(datos);

        const cabecera = Buffer.alloc(30);
        cabecera.writeUInt32LE(0x04034b50, 0);
        cabecera.writeUInt16LE(20, 4);           // versión necesaria
        cabecera.writeUInt16LE(0x0800, 6);       // nombres en UTF-8
        cabecera.writeUInt16LE(8, 8);            // deflate
        cabecera.writeUInt32LE(0x00210000, 10);  // fecha fija: 1980-01-01
        cabecera.writeUInt32LE(crc, 14);
        cabecera.writeUInt32LE(comprimido.length, 18);
        cabecera.writeUInt32LE(datos.length, 22);
        cabecera.writeUInt16LE(nombreBytes.length, 26);
        locales.push(cabecera, nombreBytes, comprimido);

        const entrada = Buffer.alloc(46);
        entrada.writeUInt32LE(0x02014b50, 0);
        entrada.writeUInt16LE(20, 4);
        entrada.writeUInt16LE(20, 6);
        entrada.writeUInt16LE(0x0800, 8);
        entrada.writeUInt16LE(8, 10);
        entrada.writeUInt32LE(0x00210000, 12);
        entrada.writeUInt32LE(crc, 16);
        entrada.writeUInt32LE(comprimido.length, 20);
        entrada.writeUInt32LE(datos.length, 24);
        entrada.writeUInt16LE(nombreBytes.length, 28);
        entrada.writeUInt32LE(desplazamiento, 42);
        central.push(entrada, nombreBytes);

        desplazamiento += 30 + nombreBytes.length + comprimido.length;
    }

    const directorio = Buffer.concat(central);
    const fin = Buffer.alloc(22);
    fin.writeUInt32LE(0x06054b50, 0);
    fin.writeUInt16LE(archivos.size, 8);
    fin.writeUInt16LE(archivos.size, 10);
    fin.writeUInt32LE(directorio.length, 12);
    fin.writeUInt32LE(desplazamiento, 16);

    return Buffer.concat([...locales, directorio, fin]);
}
