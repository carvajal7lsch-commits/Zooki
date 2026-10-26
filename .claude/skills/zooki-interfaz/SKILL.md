---
name: zooki-interfaz
description: Cómo diseña y pule interfaces el dueño de Zooki. Usar antes de rediseñar o pulir cualquier pantalla de Zooki, hacer mockups, revisar el diseño de una entrega o implementar un mockup aprobado.
---

# Zooki — interfaz y experiencia de usuario

Lee también `AGENTS.md` (reglas de código) y `agentes/metodo.md` §8. Esto resume cómo le gustan las pantallas al dueño, para no tener que repetírselo en cada pasada.

## 1. Cómo se trabaja una pantalla

1. **Primero el mockup, después el código.** Ante un rediseño, se hace un lienzo de diseño (artifact de tipo Design) con la pantalla a 1440 px y, si aplica, a 390 px, y un estado o modal clave. El dueño aprueba o pide cambios sobre el lienzo; solo con el mockup aprobado se implementa.
2. **Captura del estado actual.** Se parte de su captura y del código real de la vista (`views/`, su CSS y su JS): mismos datos, mismos estados y mismas acciones. El mockup no inventa funciones; lo que falte va entre corchetes (`[dd/mm/aaaa]`) y lo nuevo se marca como propuesta.
3. **Lo que el dueño dice que se queda, no se toca** (por ejemplo, la barra lateral y las tarjetas de propietarios). Se cambia solo lo que señaló.
4. **Explicar cada cambio en una línea**, con el porqué cuando no es obvio, y dejar una o dos decisiones concretas con recomendación. Nada de análisis largos.
5. **Al implementar:** misma estructura del mockup, sin cambiar rutas, permisos, `data-*` ni respuestas del servidor. Prueba de JS cuando cambia el JS y capturas reales a 1440, 1366 y 390 px.

## 2. Identidad (no negociable)

- Letra **Inter**. Azul de marca **#0052FF** (`--z-primary`), fondo de la aplicación **#E9ECF5**, superficies blancas.
- Colores solo con las variables `--z-*` de `public/css/identidad.css` (o las `--in-*` del Inicio en sus pantallas). Nada de colores sueltos ni azules viejos (`#146b9b`, `#183b56`).
- **Rojo y ámbar son semánticos:** rojo para error o bloqueo, ámbar para advertencia o pendiente. El estado positivo va en azul suave, no en verde, salvo donde el Inicio ya lo usa (propuesta pendiente de confirmar por el dueño).
- Tarjetas: radio 16–22 px, borde `--z-border` o sombra suave `--z-shadow-sm`; nunca las dos fuertes a la vez.
- Iconos de Font Awesome, como el resto de la plataforma; nada de emojis.

## 3. Espacio (lo que más le importa)

- **Cada pixel trabaja.** Nada de huecos sin razón entre bloques, ni títulos con aire de sobra arriba.
- **Nunca estirar contenedores para «llenar» la pantalla.** Una tarjeta mide lo que mide su contenido. Si sobra espacio, se queda como fondo; una caja blanca vacía se ve peor.
- **Nada de tarjetas sueltas flotando.** Varias secciones relacionadas van en una sola hoja blanca, separadas por líneas y con columnas alineadas; una zona de acciones puede llevar un fondo apenas distinto.
- **Agrupar controles en una sola fila** de la misma altura (44 px): buscador, filtros, conmutadores y acciones juntos, no regados en tres alturas.
- **Un solo título por pantalla.** El título va en la barra superior del layout (`top-header-title`); la página no lo repite ni repite el nombre de la clínica.
- Lo secundario se compacta: datos de referencia en filas «etiqueta gris pequeña + valor en negrita»; resúmenes en franjas de 3 columnas con separador; estados en chips.
- En escritorio la pantalla idealmente **cabe sin scroll** en 1440×900 (y no se rompe en 1366×768). Si hay mucho contenido, se prioriza y lo raro va a un modal o a un desplegable.

## 4. Jerarquía y acciones

- **Un solo botón primario azul por zona.** Los demás, secundarios (borde) o de texto. Cinco botones azules llenos compiten entre sí.
- Las acciones van junto a lo que afectan (en la cabecera de su tarjeta o al lado del título), no sueltas al final.
- **Volver siempre visible:** botón con flecha y texto que dice a dónde vuelve («← Propietarios», «← Inicio»), a la izquierda del título. Si a la pantalla se llega desde varios sitios, el texto y el destino cambian según el origen.
- Cada pantalla debe tener una entrada natural en el menú o en la sección a la que pertenece (por ejemplo, Suscripción como pestaña de Configuración), no solo un atajo del Inicio. La ruta pequeña (migas) se puede dejar como ubicación.
- Flujos de varios pasos (aceptar condiciones, guardar un medio de pago, autorizar) van en un **modal de Zooki** paso a paso, no como un formulario desplegado en la página.
- Confirmación solo para lo grave (`zookiConfirmarCorto`); lo demás, directo y con toast (`zookiToast`). Nunca `alert`.

## 5. Componentes de la casa

- **Conmutador segmentado** (fondo `--z-bg-light`, opción activa blanca con sombra o azul) para pestañas, filtros de estado, ciclos y vistas.
- **Chips** redondos para estados y conteos («5», «Al día», «Pendiente» en ámbar).
- **Inputs y selects con estilo:** 44 px, radio 12, fondo `--z-bg-suave`, borde `--z-border`, icono a la izquierda en el buscador, flecha propia en el select. Nunca un `select` nativo sin estilo.
- **Etiqueta arriba del campo.** Obligatorios con asterisco rojo suave (`zk-requerido`); opcionales con «(opcional)».
- Listas largas en **filas compactas** que se expanden (`details/summary`) o en tabla con cabecera ligera; nunca tarjetas gigantes por cada registro.
- Avatares redondos azules con iniciales; ícono en cuadro tintado para entidades (mascota, documento, pago).

## 6. Textos

- Cortos y directos. Sin párrafos explicativos encima de los formularios; la ayuda va en una línea gris junto al control que la necesita.
- Fechas **dd/mm/aaaa**, moneda `$89.990 COP`, singular y plural correctos («1 tratamiento», «1 año»).
- Los mensajes de estado aparecen en su sitio (debajo del botón o en un toast), no como notas sueltas.

## 7. Responsive (RNF-16)

- Cortes: celular < 768 px, tableta 768–1023 px, escritorio ≥ 1024 px.
- En el celular: una columna, filas de herramientas que se apilan, tablas que pasan a filas, acciones accesibles abajo y **sin desborde horizontal** (verificar 390 y 360 px).

## 8. Antes de entregar

- [ ] Coincide con el mockup aprobado y con la identidad (Inter, `--z-*`, un primario por zona).
- [ ] Sin huecos ni contenedores estirados; título único; volver visible.
- [ ] Nada de CSS/JS en línea; `data-*`, rutas y respuestas intactos.
- [ ] Pruebas de JS en verde y capturas a 1440/1366/390 px.
