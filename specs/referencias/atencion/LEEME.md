# Atención en 3 pasos — mockups aprobados por el dueño

Canvas «Zooki · Veterinario en celular y notificaciones», secciones 9 y 10.

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
