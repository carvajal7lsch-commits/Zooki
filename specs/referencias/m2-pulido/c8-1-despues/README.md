# C8.1 — Evidencia visual

Lo escribió Codex, 2026-10-16.

72 capturas de Chrome sin interfaz sobre las vistas PHP, las hojas y los scripts reales.
Los datos y las respuestas HTTP son sintéticos; no se leyó `.env` ni se conectó la base del usuario.
La sesión del renderizador PHP vive en memoria.
No sustituye las comprobaciones de Beto Norte en su sesión autenticada.

## Índice

Cada nombre comienza por el ancho: `360-`, `390-`, `820-` o `1440-`.
Los celulares usan 844 px de alto; tableta y escritorio, 1000 px.

| Escena | Sufijo del archivo |
|---|---|
| Atención: motivo, signos, diagnóstico, tratamientos y adjuntos | `atencion-paso-1.png` a `atencion-paso-5.png` |
| Ficha y alertas abiertas de Atención | `atencion-ficha-abierta.png`, `atencion-alertas-abiertas.png` |
| Historial de Atención plegado y abierto | `atencion-historial-plegado.png`, `atencion-historial-abierto.png` |
| Cajones de Consultas y Pacientes | `vet_consultas-historial-plegado.png`, `vet_consultas-historial-abierto.png`, `vet_pacientes-historial-plegado.png`, `vet_pacientes-historial-abierto.png` |
| Modal: motivo, signos, plan y archivos | `modal-tabMotivo.png`, `modal-tabSignos.png`, `modal-tabResolucion.png`, `modal-tabArchivos.png` |
| Ficha y alertas del modal abiertas | `modal-ficha-alertas.png` |

En el celular, la ficha está plegada inicialmente en las capturas de los pasos.
En escritorio y tableta, la ficha de Atención conserva su presentación completa; no tiene botón móvil visible.
Las capturas de los pasos a 820 px desplazan la página hasta el formulario para mostrarlo; las de la ficha vuelven al principio.

## Mediciones

`medidas.json` registra 360, 390 y 414 px: documento sin desborde, Salir dentro de la barra y pie de Atención por encima del menú inferior.
El recorrido también comprueba el acceso a Finalizar después de desplazar la página, el pie del modal dentro del viewport y ambos cajones sin scroll horizontal.
414 px se verifica con las mismas comprobaciones sin duplicar las capturas.
El último recorrido terminó sin excepciones JavaScript.

## Repetir

Desde la raíz del repositorio, con PHP y Node disponibles y Chrome o Edge instalado:

```powershell
node tests/Frontend/apoyo/c81-navegador.mjs
```

El arnés sirve solo la carpeta pública y las vistas renderizadas con datos de prueba.
Los endpoints se simulan dentro del servidor local del arnés; nunca ejecuta `public/index.php`.
