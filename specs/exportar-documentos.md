# Exportación de la documentación a PDF y Word

> Estado: terminado
> Versión prevista: v1.12.0 (junto con el buscador y las descargas del portal) · Fecha: 2026-10-05

Herramienta interna del equipo, como el buscador y las descargas: no lleva HU en `HistoriasUsuario.md`. Requisitos numerados `EX-N`.

## 1. Especificación (qué y por qué)

**Problema:** los PDF y Word que se descargan del portal (`public/docs/descargas/`) se generaron fuera del repositorio, con Pandoc y LibreOffice y la plantilla por defecto de Pandoc. Resultado:

- Tablas sin bordes ni encabezado marcado, a ~¾ del ancho de la hoja y con todas las columnas del mismo ancho. En Reglas de negocio la columna «ID» es ancha y la descripción queda en una tira de 2 o 3 palabras por línea.
- Sin portada, sin número de página y sin separación entre secciones: «1. Introducción» empieza pegada a la tabla de revisiones, y un título puede quedar al final de una hoja con su contenido en la siguiente.
- Los diagramas Mermaid (README, ERS, MER) no se dibujan.
- No se pueden regenerar desde el repositorio, así que se quedan desactualizados con cada cambio de los `.md`.

**Historia:** Como integrante del equipo, quiero descargar cada documento en PDF y Word con el formato de la plantilla de documentos del proyecto (portada, ficha del documento, encabezado y pie) y los rótulos de APA 7, con tablas legibles, generado desde los `.md` con un solo comando, para entregar documentos formales que siempre coincidan con la documentación vigente.

| ID | Requisito | Criterio de aceptación |
|---|---|---|
| EX-1 | Un solo comando debe generar el PDF y el Word de cada documento descargable a partir de su `.md`. | `node scripts/docs/exportar.mjs` deja `<id>.pdf` y `<id>.docx` en `public/docs/descargas/` para los 9 documentos (todos menos «Modelos y diagramas»). `--solo=ers` genera uno. `--revisar` lista los documentos cuyo `.md` cambió después de su última exportación, sin generar nada. |
| EX-2 | Formato de página de la plantilla de documentos. | Tamaño carta, márgenes de 2,54 cm a los lados, Arial 10,5, interlineado de 1,2–1,4 con espacio entre párrafos, texto alineado a la izquierda. |
| EX-3 | Cada documento abre con la portada de la plantilla. | Hoja sin encabezado ni pie. En la mitad inferior derecha, entre una línea doble arriba, el título en negrita gris, «Proyecto: Zooki v2.0», la revisión y el estándar (de la línea «Revisión X · …» del documento) y la autoría: autor, instructor, programa y ficha, SENA y centro de formación, ciudad. Abajo, otra línea doble con el logo de Zooki a la izquierda y el mes y año a la derecha. |
| EX-4 | Contenido. | Después de la ficha del documento, «Contenido» con los títulos de nivel 1 (en mayúsculas y negrita) y 2, y su número de página (en Word, el índice automático de Word). |
| EX-5 | Títulos en negrita y sin títulos huérfanos. | Nivel 1 (`##`) en 16 pt y en hoja nueva; nivel 2 (`###`) en 13 pt; nivel 3 (`####`) en 11,5 pt; nivel 4 en negrita cursiva. Ningún título queda al final de una hoja separado de su contenido. |
| EX-6 | Tablas legibles con rótulo APA. | Encima de cada tabla, «Tabla N» en negrita y debajo el título en cursiva (el de la sección que la contiene). Una tabla de una sola fila (la ficha Prioridad, Estado y Estimación de cada HU) no se numera, porque repetiría el título que tiene justo encima. Ocupa todo el ancho, con columnas proporcionales a su contenido, cuadrícula fina gris y encabezado gris en negrita, como la plantilla. Una tabla que cabe en una hoja no se parte. Si pasa de hoja, el encabezado se repite, ninguna fila se parte entre dos hojas y el rótulo nunca queda solo al pie. Los valores cortos (`RF-0.1`) y las palabras del encabezado («Prioridad») no se parten en dos renglones. |
| EX-7 | Los diagramas se dibujan. | Cada bloque Mermaid aparece como figura con «Figura N» y su título, en PDF y en Word, sin partirse entre hojas. |
| EX-8 | Los enlaces entre documentos no quedan rotos. | Un enlace a otro `.md` (`[Historias de usuario](HistoriasUsuario.md)`) se imprime como texto; los enlaces web siguen funcionando. |
| EX-9 | Encabezado y pie en cada hoja, salvo la portada. | Encabezado: logo y nombre de Zooki a la izquierda, «Zooki v2.0» y el título del documento al centro en negrita, «Rev. X» y «Pág. N» a la derecha, sobre una línea. Pie: «Servicio Nacional de Aprendizaje (SENA) · Ficha 3142784» a la derecha, bajo una línea. |
| EX-10 | Ficha del documento. | En la hoja 2: la tabla de revisiones (la del documento si la tiene, como el ERS, que sale del cuerpo; si no, una fila con la revisión vigente) y «Documento validado por las partes en fecha», con los recuadros de firma «Elaborado por» (el aprendiz) y «Revisado por» (el instructor). |

**Decisiones:**

- **Formato de la plantilla de documentos del proyecto, con rótulos APA.** La primera versión seguía APA 7 de estudiante (Calibri, doble espacio, portada centrada, tablas de tres líneas); se cambió al estilo de la plantilla de especificación con la que el proyecto ya se había presentado (`plantilla_formato_ieee830`): portada con líneas dobles, ficha del documento, encabezado con logo y título, y tablas con cuadrícula. De APA se conservan los rótulos «Tabla N» y «Figura N». Los colores y bordes están en variables de `documento.css` y en las constantes de `referencia.mjs`.
- **Cada sección de nivel 1 empieza en hoja nueva.** Ordena documentos largos como los de este proyecto, que es lo que se pidió.
- **Logo de Zooki en la portada y en el encabezado**, como el logo de la plantilla; sale de `logo` en `config.json`.

**Fuera de alcance:** generar los archivos en el despliegue (necesitaría Chrome y Pandoc dentro del contenedor). Se generan en local y se suben al repositorio, como hoy. Tampoco entra «Modelos y diagramas», que no es descargable.

## 2. Plan (cómo)

Generador en Node 22, sin paquetes de npm: Node ya trae lo necesario para manejar Chrome por su protocolo de depuración (`WebSocket`) y para leer y escribir el `.docx`, que es un zip (`zlib`). PHP no sirve aquí porque la instalación local no tiene la extensión zip.

- **Archivos** (todos en `scripts/docs/`):
  - `exportar.mjs`: el comando. Pide la lista de documentos a `DocsIndex::docsMap()` (vía `php`, para no duplicarla) y por cada uno genera el PDF y el Word. Guarda en `public/docs/descargas/manifiesto.json` la huella de cada `.md` exportado, que es lo que compara `--revisar`.
  - `config.json`: datos de la portada y del pie (autor, programa, institución, centro, ciudad, ficha, instructor, proyecto, logo), carpeta de salida y documentos excluidos.
  - `chrome.mjs`: abre Chrome sin interfaz y expone navegar, evaluar e imprimir a PDF.
  - `pdf.mjs`: lee del PDF impreso en qué página quedó cada destino con nombre.
  - `zip.mjs`: lectura y escritura mínima de zip.
  - `plantilla/impresion.html`, `impresion.js` y `documento.css`: la página que Chrome imprime. Convierte el Markdown con marked y dibuja Mermaid (ambos del CDN con versión fija). Arma la portada, la ficha del documento, el contenido, el encabezado y el pie (en las cajas de margen de `@page`) y los rótulos, mide las tablas y pasa los diagramas a PNG para el Word.
  - `plantilla/referencia.mjs`: arma en cada exportación la plantilla de Word (`reference.docx`) a partir de la de Pandoc, con los estilos, la cuadrícula de las tablas y el encabezado y el pie (con marcas que `word.mjs` reemplaza por el título y la revisión de cada documento). La portada usa encabezado y pie vacíos (primera hoja distinta). No se versiona ningún `.docx`.
  - `plantilla/word.lua`: filtro de Pandoc para la portada, la ficha del documento, el índice, los niveles de título, los rótulos, el ancho de columnas, las celdas a interlineado sencillo y los diagramas.
  - `word.mjs` y `actualizar-word.ps1`: llaman a Pandoc y, si Microsoft Word está instalado, abren cada `.docx` para llenar el índice, ajustar el ancho de las columnas al contenido, repetir encabezados, no partir filas ni valores cortos y mantener enteras las tablas cortas.
- **Paginación del PDF:** la hace el motor de impresión de Chrome, que repite el encabezado de las tablas y respeta `break-after: avoid`. Se probó primero con Paged.js, pero separaba el rótulo de su tabla. Como Chrome tampoco lo respeta siempre antes de una tabla larga, el exportador verifica el resultado. Cada título y cada rótulo son destinos del PDF, igual que lo que les sigue. Si quedan en hojas distintas, se agrega un salto de hoja y se vuelve a imprimir. En esa misma vuelta se numera el índice, porque Chrome no calcula números de página.
- **Datos, rutas y permisos:** ninguno.
- **Documentación:** el comando está en «Comandos» de `AGENTS.md`.
- **Riesgos:**
  - Formato interno del PDF de Chrome: `pdf.mjs` falla con un mensaje claro si deja de encontrar el catálogo o los destinos.
  - Sin Microsoft Word, el `.docx` sirve, pero el índice se llena al actualizar campos y las columnas usan el ancho calculado por el filtro.
  - El salto de hoja por sección deja hojas medio vacías tras las secciones cortas.

## 3. Tareas

- [x] `config.json`, `chrome.mjs` y `zip.mjs`
- [x] Plantilla de impresión (HTML, CSS APA, Paged.js) y PDF de un documento de prueba (ERS)
- [x] Plantilla de Word (`referencia.mjs`), filtro `word.lua` y Word de prueba (ERS)
- [x] `exportar.mjs` con `--solo` y `--revisar`
- [x] Generar los 9 documentos y revisar página por página una muestra (portada, índice, tablas largas, diagramas)
- [x] `AGENTS.md` (comando) y nota de la v1.12.0

## 4. Verificación

Se generaron los 9 documentos. Para revisarlos, los PDF se pasaron a imagen con PyMuPDF y los Word se exportaron a PDF con el mismo Word:

- **EX-1:** `node scripts/docs/exportar.mjs` generó los 9 PDF y Word en unos 5 minutos (casi todo es el retoque con Word). `--solo=ers,mer` y `--sin-word` funcionan. `--revisar` detectó los 4 documentos editados durante la exportación.
- **EX-2, EX-3, EX-9 y EX-10 (formato de la plantilla, 2026-10-05):** se generaron los 9 PDF y Word y se revisaron por imagen la portada, la ficha del documento, el contenido, el encabezado y el pie, y tablas largas del ERS, las HU y los RE. La paginación se estabiliza en los 9 (un título seguido de una tabla que no cabía con él en una hoja hacía oscilar el ERS: ahora esa tabla se parte).
- **EX-4:** índice en la hoja 2 con número de página, en PDF y Word. Los PDF además traen marcadores en el panel lateral.
- **EX-5 y EX-6:** se buscaron en el texto de los 9 PDF hojas que terminaran en «Tabla N» o «Figura N» y no hubo ninguna. Antes de la verificación por destinos aparecían en ERS (hojas 11, 14, 21, 23 y 34). Las tablas repiten su encabezado (ERS, hoja 7) y los ID cortos no se parten (ERS, tabla 8, en PDF y Word).
- **EX-7:** los diagramas del README, el ERS y el MER salen como figuras numeradas en PDF y Word.
- **EX-8:** los enlaces a otros `.md` salen como texto.
