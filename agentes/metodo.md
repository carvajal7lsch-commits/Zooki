# Método de trabajo con agentes — Zooki

> Cómo se construye Zooki con agentes de IA (Claude, Claude Code, Codex u otro). Es independiente de la herramienta: cualquier agente que lo lea puede tomar cualquier rol. Lo complementan [`estado.md`](estado.md) (dónde vamos) y [`prueba-manual.md`](prueba-manual.md) (cómo prueba el usuario). Las reglas técnicas están en [`AGENTS.md`](../AGENTS.md); este documento dice **cómo se trabaja**.

## 1. Al empezar una sesión

1. Leer [`AGENTS.md`](../AGENTS.md), [`estado.md`](estado.md) y el plan del módulo activo que `estado.md` indique (`specs/M<n>-...md`), incluidos sus anexos y revisiones.
2. Saber qué rol se va a cumplir (abajo). Si el usuario no lo dice, deducirlo de `estado.md`: si hay una subetapa entregada sin revisar, toca **revisar**; si la última está revisada y hecha commit, toca **preparar la siguiente**.
3. No pedirle al usuario que repita contexto que ya está en el repositorio.

## 2. Roles

| Rol | Quién | Hace | No hace |
|---|---|---|---|
| **Dueño del producto** | El usuario (Sebastian) | Decide alcance y reglas, aprueba planes, prueba en el navegador, hace commits, push, ramas y tags. | — |
| **Arquitecto y revisor** | Un agente con acceso al repositorio (por ejemplo, una sesión de Claude) | Planifica módulos y etapas, redacta el prompt de cada subetapa, revisa lo entregado, prepara la lista de prueba manual, clasifica hallazgos y mantiene el plan y `estado.md`. | No implementa la subetapa que revisa. Solo corrige directamente cosas mínimas y evidentes (una línea), y lo deja anotado. |
| **Ejecutor** | Claude Code o Codex en el equipo del usuario | Implementa **una** subetapa a partir del prompt, con pruebas, y escribe su anexo en el plan. | No empieza la subetapa siguiente, no hace commit ni push, no decide alcance. |

Un mismo agente puede ser arquitecto en una sesión y ejecutor en otra, pero nunca revisa su propio trabajo en la misma subetapa.

## 3. Ciclo de una subetapa

1. **Planificar** (arquitecto): la subetapa sale del plan del módulo. Si es grande, se parte (C1…C9, D1…D3). Cada una cierra HU o RE concretos y se puede probar sola.
2. **Decisiones antes del prompt:** lo que la especificación no resuelve se le pregunta al usuario con una **propuesta concreta y una recomendación**. Se anota en el plan como «Decisión del usuario (fecha)» **solo si el usuario la confirmó**; si no, como «propuesta, pendiente de confirmar».
3. **Prompt** (arquitecto): con la plantilla de la sección 5. El usuario lo pega en el ejecutor.
4. **Implementación** (ejecutor): sigue `AGENTS.md`, escribe pruebas, verifica, agrega al plan un «Anexo Xn — Resultado» y marca la subetapa en [`estado.md`](estado.md) como «entregada, pendiente de revisión». Si algo no cuadra, pregunta al usuario antes de decidir.
5. **Revisión** (arquitecto): con la lista de la sección 6. Escribe «### Xn — Revisión (fecha)» en el plan.
6. **Lista de prueba manual** (arquitecto): concreta, por usuario de prueba y con casillas ([`prueba-manual.md`](prueba-manual.md)).
7. **Prueba del usuario** en el navegador, en escritorio y celular.
8. **Hallazgos clasificados** (sección 7) y anotados en el plan o en `specs/pulido-interfaz.md`.
9. **Commit** (usuario) con los comandos del ejecutor, **antes** de pegar el siguiente prompt. Si se mezclan dos subetapas en un commit, se deja así y se explica en el mensaje del siguiente; no se reescribe el historial publicado.
10. **Actualizar [`estado.md`](estado.md)** (arquitecto): la subetapa pasa a «revisada», con lo que sigue, el alcance acordado de la siguiente y las decisiones pendientes. Si una sesión termina a mitad de camino, quien la cierra deja `estado.md` al día antes de irse.

## 4. Planificar un módulo nuevo

- El orden lo da el plan de entregas de las historias (v2.0 antes que v2.1) y las dependencias.
- El plan es un solo `specs/M<módulo>-nombre.md` a partir de [`specs/_plantilla_modulo.md`](../specs/_plantilla_modulo.md). Etapas habituales: **A** inventario del código existente (solo lectura); **B** datos y esquema; **C…** implementación en subetapas pequeñas; corrección y pulido; **F** despliegue.
- Antes de implementar: trazabilidad HU → RE → RN, decisiones cerradas con el usuario y riesgos con mitigación. El usuario aprueba el plan.
- Si una subetapa resulta más grande de lo previsto, se para y se replantea con el usuario.
- La documentación (HU, RE, MER) se corrige durante el módulo **sin subir número de revisión**; la revisión nueva de cada documento se publica **una sola vez** al cerrar el módulo, con las descargas del portal regeneradas.

## 5. Plantilla del prompt para el ejecutor

```
Seguimos con Zooki en la rama <rama>. Escribe <Claude Code | Codex>; revisa <arquitecto>. En el anexo pon quién lo escribió. Relee AGENTS.md, agentes/metodo.md y <plan del módulo> (<secciones, anexos y revisiones relevantes>). Busca en documentacion/ <HU, RE y RN>.

Estilo: una sentencia por línea. Pruebas de MySQL con la base por defecto (zooki_test_base_v2), NUNCA zooki_v2_prueba. No reescribas contraseñas de zooki_v2_prueba. Nada de JS ni CSS en línea. Validación en tiempo real en los formularios que toques.

Tu tarea es SOLO la subetapa <Xn>: <nombre>.
1. <qué, con la regla o la decisión que lo justifica>
…
FUERA de <Xn>: <lo que pertenece a otra subetapa>.

Pruebas: <casos concretos, incluidos los de aislamiento entre clínicas si aplica>.

Verificación: suite completa en verde (también con ZOOKI_TEST_MYSQL_HOST y la base por defecto), pruebas de JS en verde, y la lista de pantallas para que el usuario las pruebe en el navegador.

Al terminar: marca <Xn> en el plan y en agentes/estado.md como "entregada, pendiente de revisión", agrega un "Anexo <Xn> — Resultado" corto (qué, RE con su prueba, verificación, pendientes), un resumen con lo más riesgoso primero y los comandos de commit. No hagas commit ni push tú. No empieces <siguiente>. Si algo no cuadra con el plan o los RE, pregúntame antes de decidir.
```

## 6. Cómo revisar una subetapa

1. Leer el anexo y `git status` / `git diff --stat` (con `GIT_OPTIONAL_LOCKS=0` si el agente trabaja sobre la carpeta sincronizada, para no dejar `.git/index.lock`).
2. **Verificar en el código** los puntos de riesgo, no solo leer el anexo: aislamiento por `id_clinica`, permisos en la matriz de `Security`, consultas del propietario limitadas a lo suyo, migraciones repetibles, que nada lea `.env` ni toque `zooki_v2_prueba`.
3. Buscar restos o regresiones con `grep` (claves v1, `onclick=`, `style=`, `alert(`).
4. Comparar el diseño con la versión anterior o con `specs/referencias/` cuando la subetapa toca pantallas existentes: **no se acepta un cambio de diseño que el usuario no pidió**.
5. Comprobar que las pruebas existen y fallarían ante el error (las «mutaciones» que reporta el ejecutor ayudan), y que hay pruebas de JS cuando cambia el JS: **un recorrido con `curl` no prueba el JavaScript**.
6. Escribir la revisión en el plan: resultado (aprobada / con corrección), lo verificado, hallazgos con archivo y regla afectada, decisiones pendientes y lo que debe probar el usuario.
7. Si el hallazgo es una línea evidente, el revisor puede corregirlo y anotarlo; si no, va a una subetapa de corrección.

## 7. Clasificación de hallazgos

| Tipo | Ejemplos | Destino |
|---|---|---|
| **Bloqueante** | Fuga entre clínicas, robo de cuenta, pérdida de datos, pantalla rota | Se corrige antes de seguir (subetapa `Xn.1` o ajuste antes del commit) |
| **Regresión** | Algo que funcionaba en v1.12.0 y dejó de funcionar o de verse igual | Subetapa de corrección |
| **Pulido de interfaz** | Validaciones, modales, tiempos de respuesta menores, media queries | [`specs/pulido-interfaz.md`](../specs/pulido-interfaz.md), etapa final |
| **Módulo futuro** | Requiere una función que aún no existe (Grafo I, reseñas…) | Plan del módulo correspondiente |

## 8. Preferencias del dueño del producto

**Comunicación**
- Español informal y directo («brother», «dale»). Muchas veces dicta por voz: interpretar errores de transcripción por el contexto.
- Quiere opiniones claras con una recomendación, no análisis indecisos.
- Explicar en palabras sencillas el porqué de un error o una decisión; dar comandos listos para PowerShell.
- No narrar el proceso: decir qué salió, qué se encontró y qué le toca hacer.

**Interfaz**
- **Validación en tiempo real**: cada campo se valida mientras se escribe (formato, longitud, caracteres permitidos y, cuando aplica, unicidad de documento o correo por AJAX), con el mensaje junto al campo. La regla vive en un helper del servidor y el HTML y el JS usan la misma (por ejemplo, `ValidadorTelefono`). Validar solo al enviar no basta.
- **SweetAlert2 se queda**, caso por caso: avisos breves (toast) para acciones frecuentes o reversibles; confirmación para lo destructivo o irreversible. No usar SweetAlert para errores de validación de un campo.
- La pantalla se actualiza en cuanto responde el servidor; nunca esperar a que se cierre un aviso.
- Se conserva el diseño de producción (referencias en `specs/referencias/`); no se rediseña sin pedirlo. Prefiere vistas de tarjetas con opción de tabla.
- Le gustan las interacciones directas (arrastrar citas), con una pista visible de que existen.
- El ajuste fino y las media queries de todo el sistema van al final.

**Código y repositorio**
- Una sentencia por línea y nombres claros: el código se sustenta en el SENA y tiene que poder explicarse.
- Él hace commits, push, ramas y tags. Mensajes sin tildes, Conventional Commits, citando HU/RE.
- Los documentos no suben de revisión durante el módulo: una sola revisión nueva por documento al cerrarlo.

## 9. Entorno local

- XAMPP: encender **Apache** y **MySQL** en el panel antes de probar. «Conexión denegada» = MySQL apagado.
- Base de trabajo manual: `zooki_v2_prueba` (la del `.env` local). Base de las pruebas automáticas: `zooki_test_base_v2`, que se borra en cada corrida. Nunca intercambiarlas.
- `php scripts/dev/datos_prueba.php --si` crea los usuarios de prueba y **regenera sus contraseñas** cada vez (las imprime). Con `--clave=<clave>` todos usan esa misma clave si cumple la política (si no, se niega y dice por qué). Todos, salvo la super-administradora, quedan con la política vigente aceptada, así que la prueba no empieza con la pantalla de aceptación.
- **Correos en local:** con `MAIL_MODO=archivo` en el `.env` no se envía nada y cada correo queda como `.html` en `logs/correos/` (fecha, destinatario y asunto en el nombre; fuera de git). Se abre con el navegador y su botón lleva al enlace. Solo funciona con una base local; en otro entorno se ignora y queda en el log.
- **`APP_URL` en local:** déjalo comentado. Sin él, los enlaces de los correos usan la instalación desde la que se abrió la página; si apunta a producción, llevan a producción.
- **Sesiones simultáneas:** la cookie de sesión es por dominio, así que `http://localhost/...` y `http://127.0.0.1/...` son sesiones distintas. Con eso, una ventana privada y otro navegador se prueban hasta cuatro usuarios a la vez sin cerrar sesión.
- Los agentes no leen ni escriben `.env`; las variables nuevas van a `.env.example`.
- La carpeta del proyecto está en OneDrive: puede bloquear el borrado de archivos temporales; las bases y carpetas temporales de prueba van fuera de ella.

## 10. Lecciones aprendidas en M0

- Un recorrido con `curl` no ejecuta JavaScript: un error de variables en `portal.js` dejó el agendado del portal roto y solo lo vio el usuario. Toda pantalla tocada necesita prueba de JS o prueba manual.
- Las pruebas de MySQL borraban la base de trabajo del usuario: las automáticas usan siempre su propia base.
- Una vista reconstruida perdió el diseño de producción (Usuarios): al tocar una pantalla existente se parte de su versión anterior y de las referencias.
- Correr dos veces los comandos de commit mezcló subetapas: commit inmediatamente después de cada subetapa.
- Un dato temporal desactivado (vínculo inactivo) también cuenta para la seguridad: las reglas de acceso consideran todos los vínculos, no solo los activos.
