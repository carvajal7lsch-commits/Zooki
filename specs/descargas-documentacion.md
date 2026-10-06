# Descargas del portal de documentación

> Estado: terminado
> Versión prevista: v1.12.0 (va con el buscador, `specs/buscador-documentacion.md`) · Fecha: 2026-10-05

Herramienta interna del equipo, igual que el buscador: no lleva HU en `HistoriasUsuario.md`. Requisitos numerados `DD-N`.

## 1. Especificación (qué y por qué)

**Problema:** la descarga en PDF y Word es una barra con dos botones de colores fuertes (rojo y azul) al inicio de cada documento. No combina con el resto del portal, y como va dentro del contenido, desaparece al hacer scroll. Además no hay forma de ver el PDF antes de descargarlo.

**Historia:** Como integrante del equipo, quiero ver y descargar el documento abierto desde un control discreto que siempre esté a mano, y revisar el PDF antes de bajarlo, como en la vista previa de imprimir.

| ID | Requisito | Criterio de aceptación |
|---|---|---|
| DD-1 | La descarga debe estar en la barra superior, siempre visible, con el mismo estilo que el botón de tema. | Dado un documento descargable, cuando hago scroll hasta el final, entonces el botón «Descargar» sigue visible. Al darle, se abre un menú con «Ver PDF», «Descargar PDF» y «Descargar Word». En móvil (≤ 768 px) es un ícono en el header móvil y abre el mismo menú. |
| DD-2 | El botón solo aparece en los documentos que tienen versión descargable. | En «Modelos y diagramas» el botón no se muestra; al volver a un documento descargable reaparece y apunta a los archivos de ese documento. |
| DD-3 | «Ver PDF» debe mostrar el PDF antes de descargarlo. | En escritorio y tablet se abre un modal grande con el PDF en el visor del navegador (zoom, páginas, imprimir), con «Descargar PDF», «Word», «Abrir en pestaña nueva» y «Cerrar». Se cierra con Esc, con el botón o al hacer clic fuera. En móvil, el PDF se abre en una pestaña nueva, porque los navegadores móviles no muestran PDF embebidos. |
| DD-4 | La barra de colores del inicio de cada documento desaparece. | Ningún documento muestra la barra «Descargar este documento». |

**Fuera de alcance:** regenerar los PDF y Word cuando cambian los `.md` (son archivos fijos en `public/docs/descargas/`).

## 2. Plan (cómo)

- **Archivos:**
  - `public/docs/index.php`: botón «Descargar» en la barra superior (junto al de tema), ícono en el header móvil, el menú (uno solo, fuera de los headers, posicionado bajo el botón que lo abrió) y el modal de vista previa.
  - `public/docs/docs.js`: se reemplaza `injectDownloadBar()` por `updateDownloadControls()`, que muestra u oculta el botón y fija los enlaces del documento actual; apertura y cierre del menú y del modal. En el iframe del PDF, el `src` se carga solo al abrir el modal (los PDF pesan hasta 1,3 MB).
  - `public/docs/docs.css`: estilos del botón, el menú y el modal; se borran los de `.doc-downloads`.
- **Datos, rutas y permisos:** ninguno.
- **Pantallas:** escritorio y tablet con botón de texto en la barra superior y modal; móvil con ícono y pestaña nueva.
- **Riesgos:** algunos navegadores con el visor de PDF desactivado descargan el archivo en vez de mostrarlo dentro del iframe. Por eso el modal ofrece también «Abrir en pestaña nueva».

## 3. Tareas

- [x] Marcado del botón, el menú y el modal (DD-1, DD-3)
- [x] `updateDownloadControls()`, menú y modal en `docs.js` (DD-1 a DD-4)
- [x] Estilos, y borrar `.doc-downloads` (DD-4)
- [x] Verificación en el navegador en escritorio, tablet y móvil

## 4. Verificación

Con Chrome sin interfaz, manejado por el protocolo de depuración, sobre `php -S` local:

- **DD-1:** escritorio (1366 px): después de bajar hasta el final de Reglas de negocio, el botón sigue en la barra superior. El menú abre bajo el botón con «Ver PDF», «Descargar PDF» y «Descargar Word», y se cierra con un clic fuera. Móvil (390 px): el ícono aparece en el header móvil, la barra superior está oculta y el menú abre bajo el ícono.
- **DD-2:** en `#modelos` el botón queda con `display: none`. Al volver a `#ers`, reaparece y apunta a `descargas/ers.pdf` con el nombre `Zooki-ERS.pdf`.
- **DD-3:** escritorio y tablet (900 px): el modal muestra el PDF en el visor de Chrome, con el título del documento, y se cierra con Esc. En tablet, los botones del modal quedan solo con ícono. En móvil, «Ver PDF» llama a `window.open` con el PDF y no abre el modal.
- **DD-4:** ningún documento tiene `.doc-downloads`.
- Hallazgo ajeno a este cambio: en móvil las tablas anchas del ERS y de Reglas de negocio hacen la página más ancha que la pantalla (585 y 435 px en un viewport de 390 px).
