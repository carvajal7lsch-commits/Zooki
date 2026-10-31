# Atención en 3 pasos — mockups aprobados por el dueño

Canvas «Zooki · Veterinario en celular y notificaciones», secciones 9, 10 y 11.

## Sección 11 · Cero scroll en escritorio (2026-10-31) — manda sobre la 10 en escritorio

Decisión del dueño: en escritorio la Atención no tiene scroll, ni de la página ni dentro de las tarjetas. En celular y tableta (< 1024 px) sigue la sección 10: la página baja dentro del paso y la barra inferior queda fija (opción A del dueño).

| Archivo | Pantalla |
|---|---|
| AtencionV3Eval.dc.html | Paso 1 · Evaluación |
| AtencionV3Diag.dc.html | Paso 2 · peor caso: 8 exámenes y 6 recomendaciones (los topes del conocimiento) y 3 tratamientos |
| AtencionV3Cierre.dc.html | Paso 3 · Cierre, con el resumen en el panel derecho |
| AtencionV3Ayuda.dc.html | «Ayudaría a saber» abierto como recuadro flotante |
| AtencionV3Vacuna.dc.html | Pestaña Vacuna (igual Desparasitación e Historial) |

Reglas de la sección 11:
- Medida de referencia: ventana de 1366 × 650 (portátil de 1366 × 768 con el navegador). Todo cabe ahí; en pantallas más grandes sobra aire, nunca se estira el contenido. Solo si la ventana mide menos de 650 de alto se permite el scroll de la página como respaldo.
- La app mide el alto de la ventana: fila 1 = el paciente es la cabecera (volver, foto, nombre y estado, especie·raza, datos en columnas, alertas) y a la derecha clínica, campana y cuenta; fila 2 = pestañas · tipo de cita, hora y número · pasos; debajo, la hoja y el panel llenan lo que queda.
- Cada paso va en dos columnas dentro de la hoja. Una columna lleva un campo que crece (síntomas, anamnesis, otras indicaciones u observaciones) y absorbe el espacio sobrante.
- Paso 2: exámenes como chips; recomendaciones en una línea cada una (se recorta con «…» y se editan con un clic en la misma fila); tratamientos en filas compactas. Agregar o editar un medicamento abre un recuadro (ventana de Zooki), no alarga la lista. Caben 5 tratamientos; desde el 6.º se muestran 4 y «Ver N más».
- El aviso «Zooki llenó… · Deshacer», el borrador y «Descartar» van en el pie, a la izquierda.
- «Ayudaría a saber» es una línea en el panel («Faltan sexo… · Completar») que abre un recuadro flotante junto al panel; no empuja nada.
- Panel derecho: franja con el perro, como máximo 3 diagnósticos en filas cortas; en el paso 3 el panel es el resumen.
- Con una ventana de 1366 px, el paso del medio se rotula «Diagnóstico»; desde 1440 px vuelve a «Diagnóstico y tratamiento».

## Sección 10 · Atención corregida (2026-10-31) — manda en estructura y piezas

Aprobados por el dueño el 2026-10-31 tras probar los retoques de Codex. Corrigen la estructura de la pantalla; el contenido de cada paso sigue siendo el de la sección 9.

| Archivo | Pantalla |
|---|---|
| AtencionV2Eval.dc.html | Paso 1 · Evaluación, con la página haciendo scroll (escritorio) |
| AtencionV2Diag.dc.html | Paso 2 · Diagnóstico y tratamiento (escritorio) |
| AtencionV2Vacuna.dc.html | Pestaña Vacuna: sin pasos, misma cabecera (vale igual para Desparasitación e Historial) |
| AtencionV2Piezas.dc.html | Piezas: pasos, estado de la cita, alertas, pestañas, signos vitales, errores, Zooki vacío, botones, «Ayudaría a saber» |
| AtencionV2Movil.dc.html | Paso 1 en celular |

Reglas de la sección 10:
- **Scroll de la página, nunca dentro de las tarjetas.** Ni la hoja ni el panel Zooki tienen altura fija, `overflow` ni `max-height`. El panel Zooki es `position: sticky` (arriba 20 px) y el pie de la hoja (borrador + botones) es `position: sticky` abajo. En el celular, la barra inferior es sticky.
- Las etiquetas negras «sticky…» y la línea punteada del pliegue son anotaciones del mockup, no interfaz.
- Arriba, tres filas y nada más: (1) volver, «Consulta general» + chip de estado, hora y cita, cuenta; (2) tarjeta del paciente en una fila: foto, nombre, especie·raza, datos en columnas alineadas (Sexo y edad · Peso · Historia · Propietario con teléfono como botón), alertas con «+ Agregar»; (3) pestañas a la izquierda y pasos a la derecha en la misma fila. Sin título repetido, sin franja aparte para los pasos y sin banda de borrador encima del formulario.
- «Recuperamos tu borrador · Descartar» y «Borrador guardado» van en el pie, a la izquierda. «Descartar» es botón de texto azul con hover, nunca un botón nativo.
- Ningún botón parte su texto en dos líneas.
- Los signos vitales llevan ícono. Fuera de rango: azul con flecha. Error de validación: rojo, debajo del campo y con ícono.
- Zooki sin síntomas: ícono, no el perro. El perro va solo en la franja de arriba del panel, en escritorio.
- «Ayudaría a saber» vive en el panel Zooki (en el celular, como tarjeta antes de las sugerencias) y solo muestra los datos que faltan.

## Sección 9 · Contenido de los pasos (2026-10-30)

| Archivo | Pantalla |
|---|---|
| AtencionEval.dc.html | Paso 1 · Evaluación (escritorio) |
| Atencion.dc.html | Paso 2 · Diagnóstico y tratamiento (escritorio) |
| AtencionCierre.dc.html | Paso 3 · Cierre (escritorio) |
| AtencionMovilEval.dc.html | Paso 1 en celular |
| AtencionMovil.dc.html | Paso 2 en celular |
| AtencionMovilCierre.dc.html | Paso 3 en celular |

Decisiones del dueño:
- Zooki es solo azul: sin verdes, amarillos ni naranjas; el rojo para alertas, errores de validación y «Sin cerrar» (retoques confirmados del dueño, 2026-10-31). Un signo vital fuera de rango se marca en azul con «↑ Por encima de lo normal» o «↓ Por debajo de lo normal».
- La ilustración del perro (public/img/portal-bienvenida.png, la misma de la bienvenida del portal) va solo en escritorio, en la franja del panel «Zooki sugiere» y en el resumen del cierre. En el celular no aparece.
- «Próximo control» al cerrar entra en este rediseño.

Los `/_blob/...` de los mockups son imágenes del canvas: en el código se usan public/img/portal-bienvenida.png y el logo existente.
