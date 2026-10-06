// Arma la plantilla de Word (reference.docx de Pandoc) con el formato de la
// plantilla de documentos del proyecto (specs/exportar-documentos.md): Arial,
// títulos en negrita, tablas con cuadrícula y encabezado gris, y encabezado y
// pie en cada hoja salvo la portada. Parte de la plantilla por defecto de
// Pandoc y reemplaza los estilos que usa. Se arma en cada exportación (tarda
// milisegundos), así que no se versiona ningún .docx binario.
//
// El encabezado y el pie llevan marcas ({{TITULO}}, {{REVISION}}…) que
// word.mjs reemplaza en cada documento generado.

import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { leerZip, escribirZip } from '../zip.mjs';

// Medidas de Word: tamaño de letra en medios puntos, distancias en twips
// (1 cm = 567 twips; 1 pulgada = 1440).
const SENCILLO = 240;
const TEXTO = 288;            // interlineado 1,2
const ANCHO_TEXTO = 9360;     // carta (12 240) − 2 márgenes de 1 440
const SANGRIA_PORTADA = 2950; // 5,2 cm: el bloque del título va a la derecha

const GRIS_TITULO = '595959';
const AZUL = '1F3864';
const BORDE_TABLA = '7F7F7F';
const FONDO_ENCABEZADO_TABLA = 'D9D9D9';

// Los estilos propios de Word (Normal, títulos, índice) no llevan
// customStyle: si lo llevaran, Word no los reconocería como títulos y el
// índice quedaría vacío. Los de Zooki sí, y se llaman igual que su id porque
// Pandoc busca el custom-style del filtro por nombre, no por id.
const PROPIOS_DE_WORD = ['Normal', 'BodyText', 'BlockText', 'Heading1', 'Heading2', 'Heading3', 'Heading4', 'Heading5', 'TOC1', 'TOC2'];

function parrafo(id, nombre, { basado = 'Normal', pPr = '', rPr = '', siguiente = null, nivel = null } = {}) {
    const propio = !PROPIOS_DE_WORD.includes(id);
    const defecto = id === 'Normal' ? ' w:default="1"' : '';
    return `<w:style w:type="paragraph"${propio ? ' w:customStyle="1"' : ''}${defecto} w:styleId="${id}">
  <w:name w:val="${nombre}"/>
  ${basado ? `<w:basedOn w:val="${basado}"/>` : ''}
  ${siguiente ? `<w:next w:val="${siguiente}"/>` : ''}
  <w:qFormat/>
  <w:pPr>${pPr}${nivel !== null ? `<w:outlineLvl w:val="${nivel}"/>` : ''}</w:pPr>
  <w:rPr>${rPr}</w:rPr>
</w:style>`;
}

function caracter(id, nombre, rPr) {
    return `<w:style w:type="character" w:styleId="${id}">
  <w:name w:val="${nombre}"/>
  <w:basedOn w:val="DefaultParagraphFont"/>
  <w:rPr>${rPr}</w:rPr>
</w:style>`;
}

const espaciado = (linea, antes = 0, despues = 0) =>
    `<w:spacing w:before="${antes}" w:after="${despues}" w:line="${linea}" w:lineRule="auto"/>`;
const tamano = mp => `<w:sz w:val="${mp}"/><w:szCs w:val="${mp}"/>`;
const color = c => `<w:color w:val="${c}"/>`;
const negrita = '<w:b/><w:bCs/>';
const cursiva = '<w:i/><w:iCs/>';
// Línea doble de la portada: gruesa arriba y fina abajo, como la plantilla.
const LINEA_DOBLE = 'w:val="thickThinSmallGap" w:sz="24" w:space="8" w:color="000000"';

const DEFAULTS = `<w:docDefaults>
  <w:rPrDefault><w:rPr>
    <w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:eastAsia="Arial" w:cs="Arial"/>
    ${tamano(21)}
    <w:lang w:val="es-CO" w:eastAsia="es-CO" w:bidi="ar-SA"/>
  </w:rPr></w:rPrDefault>
  <w:pPrDefault><w:pPr>${espaciado(TEXTO, 0, 120)}</w:pPr></w:pPrDefault>
</w:docDefaults>`;

const BORDE = `w:val="single" w:sz="4" w:space="0" w:color="${BORDE_TABLA}"`;

const ESTILOS = [
    // Texto (EX-2): Arial 10,5, interlineado 1,2 y espacio después de cada párrafo.
    parrafo('Normal', 'Normal', { basado: '', pPr: espaciado(TEXTO, 0, 120) }),
    parrafo('BodyText', 'Body Text', { pPr: espaciado(TEXTO, 0, 120) }),
    parrafo('FirstParagraph', 'First Paragraph', { basado: 'BodyText', siguiente: 'BodyText' }),
    parrafo('Compact', 'Compact', { pPr: espaciado(TEXTO, 0, 40) }),
    parrafo('BlockText', 'Block Text', { pPr: espaciado(TEXTO, 0, 120) + `<w:pBdr><w:left w:val="single" w:sz="12" w:space="8" w:color="BFBFBF"/></w:pBdr><w:ind w:left="300"/>`, rPr: color('262626') }),

    // Títulos (EX-5), tamaños de la plantilla. El nivel 1 empieza hoja; todos
    // van con lo que sigue.
    parrafo('Heading1', 'heading 1', { siguiente: 'BodyText', nivel: 0,
        pPr: '<w:keepNext/><w:keepLines/><w:pageBreakBefore/>' + espaciado(SENCILLO, 0, 200), rPr: negrita + tamano(32) }),
    parrafo('Heading2', 'heading 2', { siguiente: 'BodyText', nivel: 1,
        pPr: '<w:keepNext/><w:keepLines/>' + espaciado(SENCILLO, 280, 120), rPr: negrita + tamano(26) }),
    parrafo('Heading3', 'heading 3', { siguiente: 'BodyText', nivel: 2,
        pPr: '<w:keepNext/><w:keepLines/>' + espaciado(SENCILLO, 240, 80), rPr: negrita + tamano(23) }),
    parrafo('Heading4', 'heading 4', { siguiente: 'BodyText', nivel: 3,
        pPr: '<w:keepNext/><w:keepLines/>' + espaciado(SENCILLO, 200, 80), rPr: negrita + tamano(21) }),
    parrafo('Heading5', 'heading 5', { siguiente: 'BodyText', nivel: 4,
        pPr: '<w:keepNext/><w:keepLines/>' + espaciado(SENCILLO, 200, 80), rPr: negrita + cursiva + tamano(21) }),

    // Portada (EX-3).
    parrafo('PortadaEspacio', 'PortadaEspacio', { pPr: espaciado(SENCILLO, 0, 0) }),
    parrafo('PortadaTitulo', 'PortadaTitulo', {
        pPr: `<w:pBdr><w:top ${LINEA_DOBLE}/></w:pBdr>` + espaciado(SENCILLO, 0, 160) + `<w:ind w:left="${SANGRIA_PORTADA}"/>`,
        rPr: negrita + tamano(36) + color(GRIS_TITULO) }),
    parrafo('PortadaProyecto', 'PortadaProyecto', {
        pPr: espaciado(SENCILLO, 0, 20) + `<w:ind w:left="${SANGRIA_PORTADA}"/>`,
        rPr: negrita + tamano(22) + color(GRIS_TITULO) }),
    parrafo('PortadaRevision', 'PortadaRevision', {
        pPr: espaciado(SENCILLO, 0, 0) + `<w:ind w:left="${SANGRIA_PORTADA}"/>`,
        rPr: tamano(20) + color(AZUL) }),
    parrafo('PortadaAutoria', 'PortadaAutoria', {
        pPr: espaciado(SENCILLO, 0, 0) + `<w:ind w:left="${SANGRIA_PORTADA}"/>`,
        rPr: tamano(19) + color('404040') }),
    parrafo('PortadaPie', 'PortadaPie', {
        pPr: `<w:pBdr><w:top ${LINEA_DOBLE}/></w:pBdr><w:tabs><w:tab w:val="right" w:pos="${ANCHO_TEXTO}"/></w:tabs>` + espaciado(SENCILLO, 0, 0),
        rPr: tamano(19) + color(AZUL) }),

    // Ficha del documento (EX-10) y contenido (EX-4).
    parrafo('TituloSeccion', 'TituloSeccion', { pPr: '<w:pageBreakBefore/><w:keepNext/>' + espaciado(SENCILLO, 0, 240), rPr: negrita + tamano(32) }),
    parrafo('FichaTexto', 'FichaTexto', { pPr: espaciado(SENCILLO, 1400, 120), rPr: tamano(18) }),

    // Rótulos APA de tablas y figuras (EX-6, EX-7): «Tabla N» en negrita y el
    // título en cursiva, siempre en la misma hoja que lo que rotulan.
    parrafo('Rotulo', 'Rotulo', { pPr: '<w:keepNext/>' + espaciado(SENCILLO, 120, 0), rPr: negrita + tamano(19) }),
    parrafo('RotuloTitulo', 'RotuloTitulo', { pPr: '<w:keepNext/>' + espaciado(SENCILLO, 0, 60), rPr: cursiva + tamano(19) }),
    parrafo('Celda', 'Celda', { pPr: espaciado(SENCILLO, 20, 20), rPr: tamano(18) }),
    parrafo('FiguraImagen', 'FiguraImagen', { pPr: espaciado(SENCILLO) + '<w:jc w:val="center"/>' }),

    // Índice (EX-4): estilos que Word usa al actualizar el campo TOC.
    parrafo('TOC1', 'toc 1', { pPr: `<w:tabs><w:tab w:val="right" w:pos="${ANCHO_TEXTO}"/></w:tabs>` + espaciado(SENCILLO, 160, 40), rPr: negrita + '<w:caps/>' + tamano(19) }),
    parrafo('TOC2', 'toc 2', { pPr: `<w:tabs><w:tab w:val="right" w:pos="${ANCHO_TEXTO}"/></w:tabs>` + espaciado(SENCILLO, 0, 30) + '<w:ind w:left="510"/>', rPr: tamano(19) }),

    caracter('Hyperlink', 'Hyperlink', color('1F4E8C')),
    // Código en línea: más chico que el texto, como en el PDF.
    caracter('VerbatimChar', 'Verbatim Char', '<w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/>' + tamano(18)),

    // Tabla (EX-6): cuadrícula fina y encabezado gris en negrita.
    `<w:style w:type="table" w:default="1" w:styleId="Table">
  <w:name w:val="Table"/>
  <w:basedOn w:val="TableNormal"/>
  <w:qFormat/>
  <w:tblPr>
    <w:tblInd w:w="0" w:type="dxa"/>
    <w:tblBorders><w:top ${BORDE}/><w:left ${BORDE}/><w:bottom ${BORDE}/><w:right ${BORDE}/><w:insideH ${BORDE}/><w:insideV ${BORDE}/></w:tblBorders>
    <w:tblCellMar>
      <w:top w:w="40" w:type="dxa"/><w:left w:w="100" w:type="dxa"/>
      <w:bottom w:w="40" w:type="dxa"/><w:right w:w="100" w:type="dxa"/>
    </w:tblCellMar>
  </w:tblPr>
  <w:tblStylePr w:type="firstRow">
    <w:rPr>${negrita}</w:rPr>
    <w:tcPr><w:shd w:val="clear" w:color="auto" w:fill="${FONDO_ENCABEZADO_TABLA}"/><w:vAlign w:val="bottom"/></w:tcPr>
  </w:tblStylePr>
</w:style>`
];

const NS = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"';

// Logo de 0,9 cm (1 cm = 360 000 EMU) incrustado en el encabezado.
const LADO_LOGO = 324000;
const LOGO = `<w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">
  <wp:extent cx="${LADO_LOGO}" cy="${LADO_LOGO}"/><wp:docPr id="9001" name="Logo Zooki"/>
  <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">
    <pic:pic><pic:nvPicPr><pic:cNvPr id="9001" name="logo.png"/><pic:cNvPicPr/></pic:nvPicPr>
      <pic:blipFill><a:blip r:embed="rIdZookiLogo"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>
      <pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="${LADO_LOGO}" cy="${LADO_LOGO}"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>
    </pic:pic>
  </a:graphicData></a:graphic>
</wp:inline></w:drawing></w:r>`;

const celdaEncabezado = (ancho, contenido) => `<w:tc>
  <w:tcPr><w:tcW w:w="${ancho}" w:type="dxa"/><w:tcBorders><w:bottom w:val="single" w:sz="6" w:space="0" w:color="404040"/></w:tcBorders><w:vAlign w:val="bottom"/></w:tcPr>
  ${contenido}
</w:tc>`;
const lineaEncabezado = (jc, runs) => `<w:p><w:pPr>${espaciado(SENCILLO)}<w:jc w:val="${jc}"/></w:pPr>${runs}</w:p>`;
const run = (texto, rPr = '') => `<w:r><w:rPr>${rPr}${tamano(17)}</w:rPr><w:t xml:space="preserve">${texto}</w:t></w:r>`;
const PAGINA = `<w:r><w:rPr>${tamano(17)}</w:rPr><w:fldChar w:fldCharType="begin"/></w:r>
<w:r><w:rPr>${tamano(17)}</w:rPr><w:instrText xml:space="preserve"> PAGE </w:instrText></w:r>
<w:r><w:rPr>${tamano(17)}</w:rPr><w:fldChar w:fldCharType="separate"/></w:r>
<w:r><w:rPr>${tamano(17)}</w:rPr><w:t>1</w:t></w:r>
<w:r><w:rPr>${tamano(17)}</w:rPr><w:fldChar w:fldCharType="end"/></w:r>`;

// Encabezado (EX-9): logo y nombre | proyecto y título | revisión y página.
const ENCABEZADO = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:hdr ${NS}>
  <w:tbl>
    <w:tblPr><w:tblW w:w="${ANCHO_TEXTO}" w:type="dxa"/><w:tblLayout w:type="fixed"/>
      <w:tblCellMar><w:left w:w="0" w:type="dxa"/><w:right w:w="0" w:type="dxa"/></w:tblCellMar></w:tblPr>
    <w:tblGrid><w:gridCol w:w="2200"/><w:gridCol w:w="4960"/><w:gridCol w:w="2200"/></w:tblGrid>
    <w:tr>
      ${celdaEncabezado(2200, lineaEncabezado('left', LOGO + `<w:r><w:rPr>${negrita}${tamano(26)}${color('1565C0')}</w:rPr><w:t xml:space="preserve"> Zooki</w:t></w:r>`))}
      ${celdaEncabezado(4960, lineaEncabezado('center', run('{{PROYECTO}}', negrita)) + lineaEncabezado('center', run('{{TITULO}}', negrita)))}
      ${celdaEncabezado(2200, lineaEncabezado('right', run('{{REVISION}}')) + lineaEncabezado('right', run('Pág. ') + PAGINA))}
    </w:tr>
  </w:tbl>
  <w:p><w:pPr>${espaciado(SENCILLO)}</w:pPr></w:p>
</w:hdr>`;

// Pie (EX-9): institución y ficha, a la derecha, bajo una línea.
const PIE = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:ftr ${NS}>
  <w:p>
    <w:pPr><w:pBdr><w:top w:val="single" w:sz="6" w:space="4" w:color="404040"/></w:pBdr>${espaciado(SENCILLO)}<w:jc w:val="right"/></w:pPr>
    <w:r><w:rPr>${tamano(16)}${color(AZUL)}</w:rPr><w:t xml:space="preserve">{{PIE}}</w:t></w:r>
  </w:p>
</w:ftr>`;

// Encabezado y pie vacíos para la portada (primera hoja, titlePg).
const VACIO = etiqueta => `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:${etiqueta} ${NS}><w:p/></w:${etiqueta}>`;

// Carta; márgenes de 3,4 cm arriba (encabezado) y 2,6 cm abajo (pie).
// Pandoc toma esta sección de la plantilla para el documento generado.
const SECCION = `<w:sectPr>
  <w:headerReference w:type="default" r:id="rIdZookiEncabezado"/>
  <w:headerReference w:type="first" r:id="rIdZookiEncabezadoPortada"/>
  <w:footerReference w:type="default" r:id="rIdZookiPie"/>
  <w:footerReference w:type="first" r:id="rIdZookiPiePortada"/>
  <w:footnotePr><w:numRestart w:val="eachSect"/></w:footnotePr>
  <w:pgSz w:w="12240" w:h="15840"/>
  <w:pgMar w:top="1928" w:right="1440" w:bottom="1474" w:left="1440" w:header="567" w:footer="567" w:gutter="0"/>
  <w:titlePg/>
</w:sectPr>`;

function quitarEstilo(xml, id) {
    return xml.replace(new RegExp(`<w:style\\b[^>]*w:styleId="${id}"[^>]*>[\\s\\S]*?</w:style>`, 'g'), '');
}

const TIPO_ENCABEZADO = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/header';
const TIPO_PIE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer';

export function crearReferencia(pandoc, logo) {
    const base = execFileSync(pandoc, ['--print-default-data-file', 'reference.docx']);
    const zip = leerZip(base);

    let estilos = zip.get('word/styles.xml').toString('utf8');
    estilos = estilos.replace(/<w:docDefaults>[\s\S]*?<\/w:docDefaults>/, DEFAULTS);
    const ids = ESTILOS.map(e => e.match(/w:styleId="([^"]+)"/)[1]);
    // Los «Char» enlazados a los títulos traen la fuente y el color azul del
    // tema de Pandoc: se quitan junto con sus estilos de párrafo.
    [...ids, 'Heading1Char', 'Heading2Char', 'Heading3Char', 'Heading4Char', 'Heading5Char']
        .forEach(id => { estilos = quitarEstilo(estilos, id); });
    estilos = estilos.replace('</w:styles>', ESTILOS.join('\n') + '\n</w:styles>');
    zip.set('word/styles.xml', estilos);

    let documento = zip.get('word/document.xml').toString('utf8');
    // Según la versión de Pandoc, la sección viene vacía (<w:sectPr />) o completa.
    documento = documento.replace(/<w:sectPr\b[^>]*\/>|<w:sectPr\b[\s\S]*<\/w:sectPr>/, SECCION);
    zip.set('word/document.xml', documento);

    zip.set('word/header1.xml', ENCABEZADO);
    zip.set('word/header2.xml', VACIO('hdr'));
    zip.set('word/footer1.xml', PIE);
    zip.set('word/footer2.xml', VACIO('ftr'));
    zip.set('word/_rels/header1.xml.rels', `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdZookiLogo" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/zooki-logo.png"/></Relationships>`);
    zip.set('word/media/zooki-logo.png', logo ? readFileSync(logo) : Buffer.alloc(0));

    let rels = zip.get('word/_rels/document.xml.rels').toString('utf8');
    rels = rels.replace('</Relationships>',
        `<Relationship Id="rIdZookiEncabezado" Type="${TIPO_ENCABEZADO}" Target="header1.xml"/>`
        + `<Relationship Id="rIdZookiEncabezadoPortada" Type="${TIPO_ENCABEZADO}" Target="header2.xml"/>`
        + `<Relationship Id="rIdZookiPie" Type="${TIPO_PIE}" Target="footer1.xml"/>`
        + `<Relationship Id="rIdZookiPiePortada" Type="${TIPO_PIE}" Target="footer2.xml"/></Relationships>`);
    zip.set('word/_rels/document.xml.rels', rels);

    let tipos = zip.get('[Content_Types].xml').toString('utf8');
    const encabezado = 'application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml';
    const pie = 'application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml';
    if (!/Extension="png"/i.test(tipos)) {
        tipos = tipos.replace('</Types>', '<Default Extension="png" ContentType="image/png"/></Types>');
    }
    tipos = tipos.replace('</Types>',
        `<Override PartName="/word/header1.xml" ContentType="${encabezado}"/>`
        + `<Override PartName="/word/header2.xml" ContentType="${encabezado}"/>`
        + `<Override PartName="/word/footer1.xml" ContentType="${pie}"/>`
        + `<Override PartName="/word/footer2.xml" ContentType="${pie}"/></Types>`);
    zip.set('[Content_Types].xml', tipos);

    return escribirZip(zip);
}
