# Pulido de interfaz — pendientes

## Pulido final P1 — Encargo (2026-10-26)

**Escribe Codex; revisa Claude (arquitecto). Aprobado por el dueño.** Versión 2.2.0 por cambios de servidor; el dueño renombra la rama. Sin esquema nuevo, migraciones ni operaciones Git de escritura.

- **Orden A.1–A.4:** freno general de comprobaciones sin error de campo ni resumen, reintento al enviar y 40 por IP/15 minutos (RE-T.13.2, RN-G15); minigráfica en cero con base/iniciales (RE-6.2.4), zona de ReglaAtencion; singular del tratamiento y fechas dd/mm/aaaa del historial (RE-2.5.2, RE-5.5.1, RE-5.9.2).
- **A.5 completo, después de A.1–A.4 (cambio del dueño):** al abrir un caso, correo individual a cada super-administrador activo/verificado después del commit y de responder; mailer existente, sin envíos reales bajo ZOOKI_PRUEBAS. Asunto `Zooki: nuevo caso de soporte #<id>`; cuerpo solo tipo en palabras, fecha y enlace al panel con sesión, sin identidad del titular. Un fallo se registra y no altera el caso ni la respuesta. Detalle con fecha, tipo, solicitante por id_usuario y cuenta actual del documento usando datos existentes; sin códigos internos visibles, resolución/descarte directo con toast. HU-0.3, RE-0.3.4, RE-T.18.3, RN-G24, RN-G15. Si requiere columna nueva, consultar al dueño.
- **B.6–B.10:** identidad compartida en portal/avisos/registro de mascota/historial; modal Zooki solo para acciones graves y toast directo en reversibles; actualización sin esperar avisos y revisión de carga al mover/reprogramar; borrar únicamente CSS demostrado sin uso. Conservar diseño aprobado y RE de cada pantalla.
- **C fuera de P1 por decisión del dueño (2026-10-26):** responsive completo se hará en otra etapa, pantalla por pantalla con supervisión del dueño. No agregar media queries ni cambiar distribución por ancho. RNF-16: comprobar únicamente que A/B no desborden horizontalmente a 1366 y 390 px.
- **D opcional:** bloqueo visual previo al soltar citas, solo si sobra tiempo. Fuera: P2, HU nuevas, semillas de presentación, validación/restricción de vacunas, desparasitación, agenda y horarios; adaptación de Atención a tableta.
- **Verificación:** pruebas de comportamiento JS (freno, minigráfica, modal y actualización inmediata), PHP del tope y soporte (destinatarios, privacidad, fallo, respuesta constante y acceso exclusivo de plataforma); suites PHP sin host/JS completas una vez, sintaxis y diff; CI MySQL 8 tras push. Exportar PDF/Word del portal. Resultado y estado «entregada, pendiente de revisión»; máximo ocho comprobaciones manuales; commits a cargo del dueño.

> Lista viva de mejoras de interfaz que el usuario detecta en las pruebas manuales y que **no** bloquean la etapa en curso. Se atienden en una etapa final de pulido (después de que estén las funciones nuevas de la v2.0, para no ajustar dos veces), salvo que el usuario pida adelantar alguna. Cada punto dice de dónde salió.

## Agenda

- **Validar al arrastrar, antes de soltar** (recorrido C8/C9, 2026-10-08). Mientras se arrastra una cita, los días no laborables, las horas pasadas y los huecos ocupados deben verse bloqueados (por ejemplo, en gris) y no admitir el soltado. Hoy se valida al soltar y el servidor devuelve la cita. En FullCalendar: `eventAllow` con el horario de la clínica y los eventos cargados; el servidor sigue siendo la validación final.
- **Indicador de carga lento** al reprogramar o mover citas (recorrido C8/C9). Revisar si la demora es de la petición o de la interfaz (ver «Respuesta inmediata» abajo).

## Respuesta inmediata en pantalla

- **La pantalla se actualiza 3–4 s tarde** después de vincular o desvincular una clínica, cancelar una cita o registrar una mascota en el portal (recorrido C8/C9). Causa probable, vista en `public/js/portal.js`: la recarga espera a que se cierre el aviso (`avisoPortal(...).then(() => location.reload())`). Debe actualizarse la vista en cuanto responde el servidor y mostrar el aviso en paralelo (o después de recargar). **Se corrige en C9.1** (recarga sin esperar el aviso) y **queda resuelto en M5 C2b**: el portal se pone al día en su sitio, sin recargar, al agendar, cancelar, vincularse, desvincularse, autorizar y registrar o editar una mascota.

## Formularios

- **Validación en tiempo real** de los campos (recorrido C8/C9): el teléfono del perfil del propietario no tiene longitud máxima ni filtra caracteres mientras se escribe. Revisar el mismo patrón en todos los formularios. **El teléfono del perfil se corrige en C9.1**; el resto, en el pulido.
- **Formulario de tratamiento** de la consulta: mejorar sus campos (dosis, duración, vía) y su presentación (recorrido C8/C9). **Hecho en M2 C5b** (modal y Atención): fármaco del catálogo o nombre libre, dosis, vía de una lista, duración, inicio, fin calculado desde una duración inequívoca, observaciones y las alertas de prescripción por fila, con máximos del servidor y avisos junto al campo.
- **Campos obligatorios de la consulta:** el aviso de «falta el motivo» llega al finalizar; marcar los obligatorios desde el inicio y avisar en el campo (recorrido C8/C9). **Hecho en M2 C5a** (modal y Atención): motivo y diagnóstico salen «Obligatorio.» desde que se abre, el aviso va junto al campo mientras se escribe y los errores del servidor (también los de los signos) salen junto a su campo. **Desde M2 C8 (hallazgo H5)** los marca el asterisco y el aviso rojo sale solo con un error: «Obligatorio.» suelto se leía como una etiqueta.
- **Peso desconocido de la mascota:** decidir cómo registrar un peso aproximado o desconocido (pregunta del usuario, recorrido C8/C9). **Decisión del usuario (2026-10-15), hecha en M2 C5b:** casilla «No se pudo pesar»; el peso queda NULL, nunca 0, y no hay peso aproximado.
- **Modales:** mejorar su diseño en general (recorrido C8/C9). **El de consulta, hecho en M2 C8** (secciones claras, pie siempre a la vista, hoja completa en el celular); el resto sigue para el pulido final.
- **Modal de consulta sin frecuencia respiratoria ni observaciones** (hallazgo de M2 A.1). **Hecho en M2 C7**, con los límites y avisos de `ValidadorClinico`.
- **`style=` restantes en `medical-module.js`** (expediente, buscador de pacientes de «Atención sin cita» e impresión) (revisión de M2 C5a). **Hecho en M2 C7:** pasaron a clases con los mismos valores; el buscador también dejó sus eventos en línea.
- **Historial con los pendientes de M2** (revisiones de C1, C5a y C5b). **Hecho en M2 C7:** «Sin peso registrado» para todo peso NULL (cajón, ficha, impresión, Atención, detalle de Consultas y portal); síntomas marcados en el detalle de la lista de Consultas, el historial del portal y su imprimible; alergias y alertas activas, con su clínica, en el imprimible del portal.
- **Vincular una mascota existente a la clínica está escondido** (usuario, 2026-10-15, pruebas de M2 C5a): hoy solo se llega por Pacientes → Nuevo registro → pestaña «Mascota» → buscar al propietario por documento → «Vincular a la clínica», y antes el propietario tiene que vincularse a la clínica desde su portal. Sin instrucciones no se encuentra. Propuesta: un acceso visible en Pacientes («Vincular mascota de un cliente»), que diga en una línea qué hacer si el propietario aún no está vinculado (pedir confirmación por correo). RE-1.5.1 a RE-1.5.3. **Hecho en M2 C7:** botón «Vincular mascota de un cliente» junto a «Nuevo Registro», que abre el registro de mascota directo en la búsqueda del propietario, con el mismo flujo; sin vínculo dice en una línea que se pida la confirmación por correo o que se vincule desde su portal.
- **«Primera consulta» en la línea de tiempo vacía** (usuario, 2026-10-15): se lee como si hiciera falta una cita antes. Una consulta sin cita es válida (HU-2.2, urgencias o imprevistos); cambiar el texto a «Registrar consulta» y, si no viene de una cita, indicar «Sin cita». **Hecho en M2 C7:** «Registrar consulta», con la aclaración de que no hace falta una cita, y «Sin cita» en el detalle de la lista de Consultas y en el historial (cajón, ficha y Atención).

## Rediseño de la consulta y del vínculo — M2 C8 (2026-10-16)

Hallazgos H2 a H9 del usuario en las pruebas antes del despliegue (plan M2). Esta vez el usuario sí pidió rediseñar: la consulta (modal y Atención) y el vínculo de mascotas quedan al nivel del portal. Capturas antes y después en `specs/referencias/m2-pulido/c8-antes/` y `c8-despues/`.

- **Identidad (H5):** `public/css/identidad.css` define las variables `--z-*` (los mismos nombres y valores del portal) y la cargan el layout clínico y el del portal. `atencion.css`, `consulta-clinica.css`, `alertas-medicas.css` y `pacientes.css` ya no tienen colores sueltos: azul de marca y neutros; rojo y ámbar solo para bloqueo/error y advertencia. «Finalizar atención» y «Guardar consulta» van en azul. La mascota se ve con el avatar azul con su inicial (o su foto) en Atención, en el modal y en el vínculo.
- **Atención, columna izquierda (H4):** datos del paciente en una cuadrícula densa; «Alergias y alertas» con el título en una línea y su contador, «Registrar» como icono y las dos primeras alertas con «Ver todas»; propietario, motivo, pendientes y última consulta en filas cortas.
- **Campos (H5, H9):** los de la consulta usan `consulta-campo` en las dos entradas; obligatorios con asterisco, aviso rojo solo con error y ayuda discreta (`consulta-ayuda`); «+ Agregar» pasa a «Agregar medicamento» con el botón del sistema.
- **Signos (H8):** restricción de entrada con `data-formato="decimal"` (`interacciones.js`) y los atributos de `ValidadorClinico::atributosSigno`: el peso no deja escribir más de 3 enteros y 2 decimales ni pasar de 200; lo mismo en temperatura y frecuencias, también al pegar. La coma se toma como punto.
- **Diagnóstico y plan (H7):** cada paso de Atención es el único que desplaza (sin listas con scroll dentro); diagnósticos sugeridos en filas compactas que se expanden; «Datos que ayudarían» en una línea; etiquetas que ya no se montan.
- **Teclado (H6):** `public/js/combobox.js` (patrón combobox de ARIA) en los buscadores de síntomas, fármacos y nodos de alergias.
- **Modal de consulta (H9):** secciones, pie fijo, fila de tratamiento en una cuadrícula fija y hoja completa en el celular.
- **Vincular (H2, H3):** mini-tarjetas horizontales en un carrusel con flechas o arrastre; al vincular, Pacientes pone al día la lista, el contador del propietario y su expediente sin recargar.

**Pendiente para el pulido final (fuera de C8):**

- `css/portal/base.css` repite los valores de `identidad.css`; unificarlos (el portal ya carga `identidad.css`).
- Vacuna y desparasitación de Atención conservan sus campos (`.campo`), ya con las variables; el resto de pantallas clínicas (lista de Consultas, expediente, calendario) sigue con sus hojas de siempre.
- El aviso final de Atención («Atención finalizada») y los de SweetAlert usan `ZOOKI_COLOR_OK` (`#5560FF`) de todo el sistema, no el azul de marca.
- En el registro de mascota, «Es otra mascota: registrar nueva» y el formulario «Registrar alergia o alerta» conservan su diseño.

## Validación reutilizable — D2 (2026-10-08)

`public/js/validacion-cuenta.js` consulta `ValidadorCuenta` y `PoliticaPassword`, muestra el error junto al campo y bloquea el envío mientras valida. Desde D3, la ayuda solo comprueba formato y política, nunca existencia de una cuenta (RE-T.13.2). Aplicado a registro, completar perfil, activar personal, crear/restablecer/cambiar contraseña, correo y documento del titular, contacto del perfil, Usuarios/Clientes y alta/edición del propietario en Pacientes (incluido Fabio).

Pendiente extender el patrón a los formularios del resto del sistema: mascota (**hecha: la del portal en M5 C2b y la del personal, registro y edición, en M5 C3**, con `validacion-mascota.js` y las reglas de `ValidadorMascota`), consulta (motivo, diagnóstico y signos **hechos en M2 C5a**; tratamiento **hecho en M2 C5b**), vacunas, desparasitación, agenda (crear/reprogramar), horarios y configuración. Sus reglas siguen en sus propios helpers; no se debe usar `ValidadorCuenta` para campos clínicos. Registro de clínicas y NIT se aplican en D3. La comprobación visual en móvil y el ajuste general de media queries siguen en el pulido final.

## Restricción de entrada — D3.1 (2026-10-10)

Aplicada con `data-caracteres` de `interacciones.js` y atributos de los helpers a registro de clínica, registro de propietarios (portal y personal), completar perfil, Datos de acceso, Usuarios y formularios de contraseña (Mi perfil, portal, cambio obligatorio, activación y restablecimiento). La activación de clínica solo tiene consentimiento y campos ocultos; conserva el diseño de enlaces del correo. Documento de hasta 15 dígitos; NIT solo dígitos y un guion antes del DV; nombres con letras, espacios, apóstrofo y guion; correo sin espacios y hasta 255; teléfono conforme a `ValidadorTelefono`. El servidor normaliza espacios y rechaza caracteres ilegales; las contraseñas conservan sus espacios y símbolos con límite de 72 bytes.

Las de mascota quedan hechas (**portal en M5 C2b y personal en M5 C3**: nombre, raza indicada y peso). Pendiente aplicar restricciones y máximos compartidos a vacunas, desparasitación, agenda, horarios/configuración y los formularios futuros de reputación, comunicaciones y administración de plataforma; la consulta y sus tratamientos ya los tienen desde M2 C5a y C5b (`ValidadorClinico` y `ValidadorTratamiento`). Conservar las reglas de cada módulo y no restringir textos clínicos con la regla de nombres de persona. El filtro de servicios del portal se retiró con la sección en M5 C2a; no se introdujeron eventos ni estilos en línea en D3.1.

## Adaptación a pantallas

- **Media queries de todo el sistema** al final, cuando ya no entren funciones nuevas (decisión del usuario, 2026-10-08).

## Etapa R — Responsive (2026-10-26)

**Escribe Codex; supervisa cada pantalla el dueño; revisa Claude (arquitecto). En curso.** RNF-16; versión 2.2.0 aún sin publicar. Una pantalla por vez: capturas previas 390×844, 360×800, 820×1180 y 1366×768, propuesta y visto bueno antes de cambiar su distribución; no pasar a la siguiente sin «listo». Escritorio se conserva. Prueba de desborde a 360/390 por pantalla cerrada; suites completas solo al terminar R. Historial y estado de entrega final se actualizan cuando el dueño cierre R.

- **Registro de clínica — escribió Codex; aceptada por el dueño («listo», 2026-10-26), entregada y pendiente de revisión de Claude:** márgenes/cabecera compactos, entradas de 16 px, texto mínimo de 14 px, controles de 40 px, acción inferior reutilizable en identidad.css, adorno oculto que desbordaba 8 px a 360 y formulario más arriba en tableta; distribución de escritorio conservada. Propuesta aprobada expresamente antes de aplicar. `RRegistroClinica.test.cjs`: los tres pasos sin desborde a 360/390/1366, acción visible e inputs de 16 px; capturas previas y posteriores de los tres pasos a 390×844, 360×800, 820×1180 y 1366×768 en `scratch/r/registro-clinica/antes/` y `despues/`. Pantalla cerrada por el dueño; etapa R todavía en curso.
- **Observaciones autorizadas por el dueño, 2026-10-26:** corregir el giro entre login y registro con preparación síncrona externa antes del primer pintado, fondo compartido, curva/perspectiva y movimiento reducido; verificar cuadros de ambas direcciones. Un único NIT o cédula: sin guion calcula el DV conservando todas las cifras; con guion comprueba el DV escrito. Limpia puntos/espacios al pegar y no añade guion al salir. Máximo 14 cifras de número, más DV y guion solo si se escriben (rectificación posterior aprobada por el dueño). Ayuda y error con el texto indicado por el dueño; comprobación DIAN en el servidor.
- **Excepción expresa al alcance solo de vista:** PHP de `ValidadorNit`, validación de clínica, registro, comprobación AJAX y reporte de NIT; separación central por guion, o cálculo automático de DV para el número completo sin guion, incluida cédula de persona natural. Sustituye la decisión de casillas separadas del 10/10; sin esquema ni migración, almacenamiento normalizado conservado. RE-0.1.1/2/3/10 y Modelos §12.1 actualizados con la misma decisión. No sube versión.
- **NIT y giro — resultado de Codex:** ejemplo válido 900123456-8 confirmado por el dueño; una conversión central de NIT/cédula sin guion (DV calculado) o con guion (DV explícito comprobado) para registro, AJAX y reporte. Casos con/sin guion, pegado con puntos/espacios, DV erróneo, cédula de diez cifras y un dígito solo. PHP dirigido sin host: 48 pruebas/379 aserciones; JS dirigido: 55/55; sintaxis/diff correctos. En Chrome, 52 cuadros de ida/vuelta y muestras de cada pintado: tarjeta de canto antes de iniciar la entrada, fondo html/body rgb(233,236,245), sin fundido superpuesto; acceso directo y movimiento reducido sin giro. Evidencia en `scratch/r/acceso-giro/despues/` (`cuadros.json`, `verificacion.json`, `comprobaciones.json` y muestras PNG). No se leyó .env, no hubo base real ni envíos.
- **Sigue, por petición del dueño:** commit de esta entrega antes de integrar `claude/suscripcion-rediseno` en release/v2.2.0; operaciones Git a cargo del dueño. Después de revisar/verificar la integración, preguntar cuál pantalla sigue. R sigue en curso. Suites completas, exportación de los documentos del portal, Historial 2.2.0 y estado final de R solo al cierre de la etapa.
- **Corrección de supervisión del dueño, 2026-10-26 — escribe Codex:** ayuda de NIT a 0.3rem del input, sin la reserva de validación duplicada dentro de su grupo; aclara que también se añade el DV del RUT cuando el NIT coincide con la cédula (sin modificar la regla del servidor). En móvil, 12 px entre flecha/título y título/párrafo, más separación de la barra superior y del indicador de pasos. Prueba dirigida `RRegistroClinica.test.cjs` correcta; tres pasos en los cuatro tamaños sin desborde ni errores. Capturas de esta corrección en `scratch/r/registro-clinica/ajuste-espaciado/`. Corrección aceptada posteriormente por el dueño.
- **Rectificación posterior aprobada por el dueño, 2026-10-26 — escribe Codex:** se acepta cédula/NIT sin DV. Sin guion, todas las cifras son el número y el servidor calcula el DV; con guion, valida el DV escrito. Esta regla reemplaza la obligación previa de tratar siempre la última cifra como DV y el formateo al salir. No se adivina por longitud ni por una coincidencia del DV. La entrada queda intacta en pantalla; registro, AJAX y reporte convierten mediante ValidadorNit::normalizarEntrada, separado del normalizador idempotente de datos ya compuestos. Mismo almacenamiento, sin esquema ni migración y dentro de 2.2.0. HU-0.1, RE-0.1.3 y Modelos §12.1 actualizados. Verificación: 48 pruebas PHP/379 aserciones y 55 JS dirigidas; Chrome avanza del primer paso con cédula sola, sin recortarla, usando el controlador real con SQLite efímero (`r-validacion-clinica.php`). Duplicado y reporte coinciden con/sin DV, y los datos ya guardados no reciben un segundo DV. Capturas actuales de los tres pasos y cuatro tamaños sin desborde ni errores en `scratch/r/registro-clinica/nit-opcional/`. Registro de clínica aceptado por el dueño; entregado y pendiente de revisión de Claude.

## Confirmaciones con el modal de Zooki — M5 C4a (2026-10-13)

`zookiConfirmarCorto` (`avisos.js`) ya es el modal de Zooki en todo el sistema (decisión del usuario): lo usan el portal (agendar, cancelar una cita, cita adicional del mismo día) y Clientes del personal. **Pendiente para el pulido final:** pasar a ese modal las confirmaciones que siguen con SweetAlert: `zookiConfirmar` en `atencion.js`, `horarios.js` y `usuarios.js`, y los `Swal.fire` con botones del panel de plataforma y de «Gestionar». Los toasts siguen con SweetAlert.

## Relacionado con módulos futuros (no es pulido)

- **Motivo de la cita obligatorio** cuando exista el Grafo I: el triage necesita síntomas (módulo 4, HU-4.13 y HU-2.11).
- **Manual de usuario:** incluir que las citas se pueden arrastrar (entregable pendiente).
- **Interruptor «Activo» del registro de mascota en verde** (revisión de M2 C8, 2026-10-16): pasar a la identidad (azul de marca) junto con el resto del pulido general.

## Observaciones fuera de M2 C8.1 (2026-10-16)

- El icono verde de la cabecera del cajón «Historial clínico» y otros acentos de `medical/consultas.css` conservan el diseño heredado; unificarlos en el pulido general. C8.1 solo cambia las filas del historial y el indicador móvil de alertas.
- En tableta (820 px), Atención conserva la ficha completa encima del formulario conforme al diseño existente; el alcance aprobado compacta la cabecera por debajo de 768 px. Revisar la distribución de tableta en el pulido general si el usuario lo pide.

## Observaciones fuera de M2 C8.2 (2026-10-17)

- El chip de las filas del historial dice «1 tratamientos»: falta el singular («1 tratamiento»). C8.2 no cambió el texto de los chips.
- El imprimible del historial (`imprimirHistorial` en `medical-module.js`), la fecha de nacimiento del expediente y el historial del portal del propietario siguen con `toLocaleDateString()` (formato según el navegador); unificarlos con `fechaHistorial()` (dd/mm/aaaa) si el usuario lo pide.

## Mi perfil por secciones — Resultado (2026-10-21)

**Autor: Codex. Entregada, pendiente de revisión.** Rediseño solicitado por el dueño, sin cambios de servidor, rutas, permisos ni versión.

- Escritorio: lateral de 300 px con hero vertical, menú sticky y Cerrar sesión; una sección a la derecha con desbordes largos dentro del área, sin scroll de página en la revisión a 1366×844.
- Mi cuenta conserva contacto y sus controles; Datos de acceso es tarjeta abierta con ambos formularios. Seguridad conserva contraseña y actividad en dos columnas, cinco eventos y Ver más/Ver menos local.
- Vet: reutiliza las tarjetas y eventos de coordinación; Ausencias primero, reajustes solo con elementos y cierres compactos de solo lectura al final. Puntos del menú derivados del DOM ya cargado, sin nuevas peticiones.
- dialogos-zooki.js conserva el patrón compartido de pestañas; agrega flechas verticales y cambios de hash. Cuenta por defecto y enlace #seguridad conservado.
- Tableta: hero horizontal y menú desplazable dentro de su contenedor. Móvil: avatar/nombre/rol compactos; sin desborde de página en 900/390 px.
- HU-T.5, RE-T.5.7/8 y RNF-16 → PerfilSecciones: admin sin pestañas vet, hash inicial, teclado arriba/abajo, cinco/ocho eventos sin red, puntos, vacíos, orden de ausencias, cierres sin escrituras y validación existente. Vistas PHP reales en Chrome con datos simulados, sin iniciar sesiones de usuarios reales.
- JS completo propio 299/299; PHPUnit completo sin host 1017 pruebas/8056 aserciones, 71 omitidas de motor. Sintaxis de cuatro vistas y ambos JS correcta; git diff --check correcto. No es prueba MySQL/MariaDB o CI nuevo.
- Identificadores, name y data de validación originales conservados. Partial de identidad mantiene el comportamiento del portal y permite inyectar el identificador público de Google en la fixture para no leer configuración local.
- Limitación del encargo: PerfilController::mostrar solo admite ADMIN/VETERINARIO y Roles ya no define recepción; no se amplían accesos para roles inexistentes o no autorizados.
- Sin modificaciones de layouts, calendario, Pacientes, lógica de servidor, esquema ni exportaciones. Se preservan los cambios y anotaciones de otros trabajos; sin commit/push/tags.
- Pendiente: revisión independiente y comprobación del dueño en la aplicación, con datos reales de sus cuentas y propuestas/ausencias.

## Mi perfil — Pulido de horario, acceso y móvil (2026-10-22)

**Autor: Codex. Entregada, pendiente de revisión.** Se conserva el lateral y la navegación por secciones del rediseño anterior.

- Horario del veterinario: tabla semanal de siete filas, interruptores y desde→hasta con step 60, encabezado único y franjas adicionales por día. El mismo partial funciona en Configuración como admin, sin duplicar formularios.
- Mantiene las claves y tipos del JSON de franjas; desactivar excluye el día sin perder lo escrito, quitar conserva otras franjas. Las horas recibidas con segundos se conservan si no se editan. Validación de solapes, carga, doble envío, claves y respuesta canónica conservadas.
- Datos de acceso: dos filas de solo lectura y Cambiar abre cada formulario existente en un diálogo nativo. IDs, name y validación conservados; el portal mantiene su presentación y sus formularios actuales.
- Inputs de 42 px/radio 10 y mismo foco de Configuración; placeholders de contraseña, requisitos ✓ verde/✗ gris y Guardar cambios compacto a la derecha.
- Móvil: etiquetas cortas, íconos en pestañas no activas para caber a 360 px, Cerrar sesión al final de Seguridad; vuelve bajo el menú al ensanchar. Título común sin partirse mediante CSS compartido, sin editar layouts.
- HU-T.5/RE-T.5.7/8, HU-4.11 y RNF-16 → PerfilSecciones y CoordinacionHorarios: diálogos, menú/salida por ancho, placeholders/tamaño, teclado y hash; siete días, varias franjas, activar/desactivar/quitar y JSON con segundos, rutas de proponer/guardar e idempotencia existente.
- Navegador aislado con vistas PHP reales y datos simulados: admin/vet a 1366/900/390 y menú a 360; Configuración › Horarios como admin en los tres anchos. Sin desbordes horizontales; no se recorrió la aplicación ni se enviaron cambios a cuentas reales.
- JS completo propio 301/301. PHPUnit completo sin host 1017 pruebas/8056 aserciones, 71 omitidas de motor; sin ejecución nueva MySQL/MariaDB ni CI. Sintaxis de PHP/JS y git diff --check correctos.
- Se conservan cambios sin commit de otros trabajos, versión, esquema, rutas y permisos. Sin leer .env, consultar bases ni hacer commit/push/tags.
- Pendiente: revisión independiente y visual del dueño con sus datos reales; no se aprueba la propia entrega.


## Observaciones del dueño — Mi perfil en una sola hoja (2026-10-22)

Encargo registrado por Codex antes de implementar; conserva los resultados anteriores y sus verificaciones como historial.

A. Estructura «una sola hoja»
- Quita las tarjetas sueltas. Un solo contenedor redondeado que ocupa el alto disponible de la pantalla (100vh menos el header).
- Dentro: a la izquierda el lomo azul (~280px) con avatar, rol, nombre, correo, «Miembro desde», «Acceso anterior», el menú de secciones y «Cerrar sesión» al fondo; a la derecha el contenido de la sección, sin espacio entre ambos.
- Si el contenido de una sección es más alto que la hoja, solo scrollea el panel derecho. Ajusta el contenido para que eso casi nunca pase.
- Tableta y celular: el lomo pasa arriba compacto (avatar + nombre + rol) con pestañas cortas («Cuenta», «Seguridad», «Horario», «Ausencias»), sin cortes en 360px. «Cerrar sesión» va al final de Seguridad.

B. Mi cuenta: una sola tarjeta «Mi cuenta» en grid de 2 columnas con ancho máximo (~720px), campos de ancho razonable:
- Documento: solo lectura, con botón «Cambiar» dentro del campo.
- Rol: solo lectura.
- Correo: solo lectura, con «Cambiar» dentro del campo.
- Teléfono: editable con su validación actual.
- «Guardar cambios»: botón compacto alineado a la derecha, habilitado solo si el teléfono cambió y es válido.
- La nota «El rol lo gestiona el administrador…» en una línea pequeña.
- Elimina la tarjeta separada «Datos de acceso». Los formularios de cambio de correo y de documento (cambios_identidad.php) pasan a ventanas que abren esos botones «Cambiar». Conserva ids, names, endpoints y validaciones.
- En el espacio libre debajo, una franja de resumen (solo vet) con dos tarjetas pequeñas:
  - «Mi semana»: los 7 días con su horario abreviado, o «Libre».
  - «Ausencias»: la próxima, o «Sin ausencias».
  - Cada una con un enlace que abre su sección.
  - Para admin y otros roles: solo la tarjeta de cuenta, centrada.

C. Estándar de ventanas (en dialogos-zooki.css, para que aplique en todo el sistema):
- Encabezado con ícono en un cuadro de color suave a la izquierda + título + subtítulo corto, como los demás modales del sistema.
- Cuerpo y pie del mismo blanco. El pie solo con borde superior; quita el fondo #F8FAFC.
- Ancho máximo ~520px. Campos relacionados en la misma fila (Tipo de documento + Número; Desde + Hasta). Los inputs no ocupan todo el ancho si no lo necesitan.
- Toda contraseña con botón de ojito (reutiliza data-ver de perfil.js) y validación en tiempo real bajo el campo.
- Se aplica a: Cambiar correo, Cambiar documento, Solicitar ausencia y las de Configuración.
- En celular siguen como hoja inferior.

D. Mi horario (vet) y Horario del veterinario (admin en Configuración):
- Reemplaza la lista de «franjas» por el mismo componente de la tabla semanal de la clínica: una fila por día con interruptor, bloque Mañana y bloque Tarde (selector de hora como en horarios.js) y la ventana «Aplicar a varios días».
- Cero scroll en escritorio.
- Texto: nunca «franja». Usa «Horario de trabajo», «turno de mañana/tarde» y «de 8:00 a 12:00».
- Lo que se envía al servidor no cambia: convierte los bloques al mismo JSON de franjas que hoy arma coordinacion-horarios.js. Si un día guardado tiene más de 2 franjas o no encaja en mañana/tarde, muéstralo como dos bloques cuando se pueda; si no, muestra un aviso en esa fila sin perder datos.
- Vet: el botón dice «Enviar para aprobación» y muestra el estado de la propuesta (pendiente/aprobada/rechazada con motivo). Admin: «Guardar horario».

E. Ausencias: misma ventana estándar (Desde + Hasta en una fila, Motivo opcional). Lista compacta con el estado en chip. «Citas pendientes de reajuste» solo si hay. «Cierres puntuales» como lista pequeña de solo lectura.

F. Seguridad (al final):
- «Cambiar contraseña» y «Actividad reciente» lado a lado, con la misma altura.
- Actividad con paginación de 5 por página (Anterior/Siguiente y «Página 1 de N»).
- Si la consulta del servidor no tiene límite, ponle LIMIT 100 y avisa.

G. Verificación:
- Sin CSS ni JS en línea.
- Actualiza los tests (PerfilSecciones, CoordinacionHorarios y los de diálogos/ausencias).
- Corre node --test --test-concurrency=1 tests/Frontend/*.test.cjs y vendor/bin/phpunit.
- Revisa en el navegador como vet y como admin, a 1920, 1366, 900 y 390 px, cada sección y cada ventana, y también Configuración › Horarios.
- Al terminar, anota en agentes/estado.md y dime qué archivos cambiaste.

## Mi perfil en una sola hoja — Resultado (2026-10-22)

**Autor: Codex. Entregada, pendiente de revisión.** Implementación del encargo A–G registrado arriba; se conservan las entregas anteriores como historial.

- A/RNF-16: hoja con lomo azul de 280 px, menú y salida al fondo; solo el panel derecho se desplaza en escritorio. En tableta/móvil, identidad compacta y salida al final de Seguridad; etiquetas cortas a 360 px.
- B/HU-T.5: cuenta única hasta 720 px, documento/rol/correo de solo lectura, Cambiar dentro del campo y teléfono editable; Guardar se habilita con teléfono cambiado y válido. El vet resume siete días y la próxima ausencia no rechazada/anulada, indicando su estado, usando respuestas existentes; no hay consultas adicionales de resumen.
- C/RE-T.5.7–8: formularios de identidad en ventanas, mismos identificadores/controles/CSRF y validación. El portal conserva su presentación. Encabezados con icono/subtítulo, pie blanco, ancho de 520 px, campos relacionados juntos y ojos de contraseña; Configuración/cierre/ausencia usan el mismo estándar.
- D/HU-4.11: tabla y ventana semanal extraídas de la clínica a partials compartidos; ambos editores usan BlockPicker. El adaptador del vet conserva originales y segundos hasta una edición explícita; horarios no representables quedan bloqueados con aviso legible. Aplicar a varios días rechaza sobrescribirlos. No cambia el contrato JSON, el servidor ni las rutas de proponer/guardar/aprobar.
- El selector compartido conserva minutos al mediodía en la tarde y rechaza valores incompletos; dentro de la ventana de varios días se abre en la capa correcta y Escape cierra primero el selector. El horario de clínica conserva «Cerrado» y el del vet «Libre».
- E: lista compacta con chips de estado, reajustes solo con elementos y cierres de solo lectura para el vet. F: contraseña/actividad lado a lado y de igual altura; paginación local de cinco eventos. El servidor ya usa actividadDeCuenta(id, 8), por lo que no se introduce LIMIT 100 ni cambia la lectura.
- Configuración conserva ambos horarios; su contenido largo queda dentro de un contenedor desplazable de escritorio. No se promete que ambas tablas completas se vean simultáneamente en una pantalla de 768 px de alto.
- G → PerfilSecciones: roles, hash/teclado, pestañas/salida por ancho, cuenta/guardar válido, resúmenes sin red extra, ventanas/ojos/pie blanco, paginación, indicadores y vacíos. CoordinacionHorarios: siete días, ambos editores, originales/segundos, bloques, varios días, ventanas de Configuración, carga, doble envío, error, reintento y respuesta canónica. AgendaClinica: estado en chip y validación/actualización existentes.
- Evidencia propia final: `node --test --test-concurrency=1 tests/Frontend/*.test.cjs` → **302/302**, sin omisiones. `C:\xampp\php\php.exe vendor/phpunit/phpunit/phpunit --colors=never` → **1017 pruebas, 8057 aserciones, 71 omitidas** de motor; variables explícitas de pruebas, TRUSTED_PROXIES=127.0.0.2 y sin host. No es MySQL/MariaDB ni CI nuevo.
- Sintaxis: nueve vistas/fixture PHP y seis JS correctos; `git diff --check` correcto. Chrome aislado, vistas PHP reales con datos simulados: admin/vet, cada sección y ventana a **1920×1080, 1366×768, 900×844 y 390×844**; menú a 360 px. Configuración real, ambos componentes cargados a la vez, cinco ventanas y selector anidado; sin desborde horizontal ni scroll de página de escritorio.
- Capturas y registros de comprobación quedan en scratch, ignorados. No se ejecutaron operaciones contra cuentas reales, motores ni correo. Sin cambios de versión, esquema, rutas, permisos, layouts, calendario o Pacientes; otros cambios pendientes conservados.
- Pendiente: revisión independiente y comprobación del dueño con sus propuestas/ausencias/actividad reales antes del despliegue. Sin commit, push ni tags; no se aprueba la propia entrega.

### Archivos de esta entrega (25)

| Grupo | Archivos |
|---|---|
| Perfil | `views/perfil/index.php`, `public/css/perfil.css`, `public/js/perfil.js` |
| Identidad | `views/partials/cambios_identidad.php` |
| Coordinación/ausencias | `views/partials/coordinacion_horarios.php`, `views/partials/ausencias_agenda.php`, `public/js/coordinacion-horarios.js`, `public/js/ausencias-agenda.js`, `public/css/coordinacion-horarios.css` |
| Horario compartido | `views/partials/tabla_horario_semanal.php` (nuevo), `views/partials/horario_varios_dias.php` (nuevo), `public/js/horario-trabajo.js` (nuevo), `views/admin/horarios.php`, `public/js/horarios.js`, `public/js/time-picker.js`, `public/css/horarios.css` |
| Configuración/ventanas | `views/admin/configuracion.php`, `public/css/configuracion-clinica.css`, `public/css/dialogos-zooki.css` |
| Pruebas | `tests/Frontend/PerfilSecciones.test.cjs`, `tests/Frontend/CoordinacionHorarios.test.cjs`, `tests/Frontend/AgendaClinica.test.cjs`, `tests/Frontend/apoyo/perfil-vista.php` |
| Seguimiento | `specs/pulido-interfaz.md`, `agentes/estado.md` |

## Mi perfil — Ajustes visuales del dueño (2026-10-22)

**Autor: Codex. Entregada, pendiente de revisión.** Se conserva el resultado anterior y se atiende la captura/comentarios posteriores del dueño.

- Observaciones: distribución inicial poco equilibrada; el paginador subía al quedar tres eventos; demasiadas filas de horario; usar el calendario del sistema en Desde/Hasta de ausencias.
- Cuenta alineada con el inicio del contenido, ancho útil ajustado y resúmenes compactos; Mi semana en dos columnas y Ausencias con altura según contenido.
- Actividad reserva cinco lugares de igual altura; prueba ejecutable compara la posición del paginador antes/después de cambiar cinco eventos por tres, también en móvil.
- Horario conserva siete días y los mismos controles/JSON, presentados en cuadrícula de cuatro, tres o dos columnas según ancho. Turnos compactos en formato de HH:mm a HH:mm, botón para aplicar a varios días y originales especiales conservados; no cambia aprobación ni permisos.
- Calendario reutilizado desde el selector de Pacientes: extracción a `selector-fechas.js`, sin duplicar la navegación de meses/años. `medical-module.js` delega con sus opciones originales de nacimiento; el layout vet solo agrega su carga. Ausencias usa el mismo componente/estilos y dependencia Flatpickr que ya usa el sistema, dentro de la ventana nativa, con horas/segundos y fechas futuras. Calendario abierto ocupa la fila completa para no recortar días/año.
- Conservados names inicio/fin, valor ISO con segundos, validación inmediata, CSRF, doble envío, endpoints y servidor. No se crean reglas de negocio ni se cambian datos.
- HU-T.5/RE-T.5.7–8, HU-4.11 y RNF-16 → PerfilSecciones: posición estable, cuadrícula por roles/ancho, ventanas y calendario real; SelectorFechas: opciones de nacimiento conservadas y fecha/hora futura para ausencia. Regresiones de coordinación e idempotencia existentes conservadas.
- Verificación propia: JS completo `node --test --test-concurrency=1 tests/Frontend/*.test.cjs` **303/303**; PHP completo sin host **1017 pruebas/8058 aserciones, 71 omitidas** de motor. TRUSTED_PROXIES=127.0.0.2 y base de pruebas explícita; no hay ejecución nueva de MySQL/MariaDB ni CI.
- Chrome aislado con las vistas PHP y datos simulados: admin/vet a 1920×1080, 1366×768, 900×844 y 390×844; menú a 360. Calendario real ejercitado con los mismos activos públicos del sistema, copiados a scratch solo para QA: fecha 2030-01-07T09:00:30, apertura dentro del diálogo, meses futuros habilitados y días sin recortes. En CI la comprobación básica no depende de estos activos de scratch; el modo real se activa con ZOOKI_CALENDARIO_REAL=1.
- Sintaxis de PHP/JS y diff correctos. Capturas/registros en scratch ignorados. Sin cambios de calendario de agenda, Pacientes, servidor, permisos, versión o base; conservado lo pendiente de otros trabajos. Sin commit/push/tags.
- Pendiente: revisión independiente y comprobación del dueño con su zoom y sus datos reales; no se aprueba la propia entrega.

Archivos de este ajuste: `public/css/perfil.css`, `public/css/horarios.css`, `public/css/coordinacion-horarios.css`, `public/js/horario-trabajo.js`, `public/js/time-picker.js`, `public/js/selector-fechas.js` (nuevo), `public/js/medical-module.js`, `public/js/ausencias-agenda.js`, `views/partials/ausencias_agenda.php`, `views/vet/layout.php` (solo script agregado), `tests/Frontend/PerfilSecciones.test.cjs`, `tests/Frontend/SelectorFechas.test.cjs` (nuevo), `specs/pulido-interfaz.md` y `agentes/estado.md`.

## Mi perfil — Aplicar a varios días en cabecera (2026-10-22)

**Autor: Codex. Entregada, pendiente de revisión.** A petición del dueño, el botón queda en la cabecera junto a Horario de trabajo; elimina su fila independiente y sube las tarjetas de los días. Conserva el componente, sus candados de interfaz y la ventana compartida con Configuración.

PerfilSecciones comprueba contención y alineación vertical con el título a 1920/1366; coordinación conserva las comprobaciones de ambos roles y varios días. Pruebas dirigidas **12/12**, sintaxis PHP y `git diff --check` correctos. Suites completas de la corrección anterior: JS 303/303 y PHP 1017/8058 con 71 omitidas de motor, como antecedente sin repetirlas por este movimiento de presentación.

**Autorización del dueño:** guardar en un commit todas las mejoras/correcciones pendientes de la conversación (modal Nueva cita, Mi perfil, horarios, calendario compartido, pruebas y seguimiento). Codex ejecuta este commit por la petición expresa posterior del usuario; no realiza push ni tags y no aprueba su propia entrega.

## Inicio del veterinario y retiro de «Consultas médicas» — Encargo (2026-10-22)

**Autor: Claude (ejecutor). HU-6.1 (RE-6.1.1 a RE-6.1.12), HU-2.2, RNF-11, RNF-16.** Referencia visual y de comportamiento aprobada por el dueño: `specs/referencias/inicio-veterinario.dc.html` (se repite su diseño, no su código).

**Decisiones del dueño (2026-10-22):**

- Se elimina el módulo «Consultas médicas» (`vet_consultas`): sale del menú, de la ruta, de la matriz de `Security.php` y de sus pruebas. El historial de cada mascota sigue en Pacientes y en Atención.
- «Atención sin cita» pasa al Inicio y lleva a Agenda → pestaña Llegadas, que es lo que ya hacía el botón de Consultas (HU-2.2: se registra la llegada y se crea la cita antes de atender). No cambia lo que se guarda. Se retira el buscador modal de consultas.php, que ningún botón abría y que, al elegir una mascota, también mandaba a Llegadas sin llevarla. Abrir la consulta directa sin cita se descartó porque contradice HU-2.2.
- «+ Agendar» en un hueco de «Tu día» abre el modal de agendar de la agenda en esa fecha (`vet_agenda&agendar=AAAA-MM-DD&hora=HH:MM`). Cuando aparecen los horarios, marca esa hora si sigue libre. El modal no se duplica en el Inicio.

**Decisiones de implementación (para revisión):**

- La agenda abre la pestaña de herramientas que indica el hash (`#llegadas`, `#triage`, `#reasignaciones`). Antes, `#llegadas` no la abría.
- «Registrar llegada» del siguiente paciente usa la misma acción `registrar_llegada_ajax` de Llegadas. Si la llegada queda fuera de la tolerancia, lleva a Llegadas para decidir (RN-426), sin duplicar esas decisiones en el Inicio. Ya registrada, la tarjeta ofrece la acción de RE-6.1.5.
- Los huecos de «Tu día» salen de `DisponibilidadAgenda::diaDelVeterinario` (jornada ∩ apertura − cierres − ausencias − ocupación, la misma resta que usan sugerir y reservar). De hoy solo se muestran desde ahora y de días pasados no se muestran. Se ocultan los huecos más cortos que el tipo de cita activo más corto de la clínica, porque no se pueden agendar.
- Entre franjas de la jornada aparece una fila gris de almuerzo; también los cierres puntuales y las ausencias aprobadas.
- «Pendientes» reutiliza los listados existentes: atenciones abiertas del panel, `SolicitudAgenda::listar` (triages por revisar, de la clínica) y `CascadaAgenda::listar` (reasignaciones para este vet). Las vacunas y desparasitaciones próximas se abren en una ventana del propio Inicio.
- La acción nueva `panel_dia_ajax` es de solo lectura y exclusiva del veterinario, sobre su propia agenda: el id sale de la sesión, nunca de la petición. Devuelve los fragmentos «Tu día» o el calendario renderizados por los mismos parciales PHP del primer render.

## Inicio del veterinario y retiro de «Consultas médicas» — Resultado (2026-10-22)

**Autor: Claude. Entregada, pendiente de revisión.**

- **Inicio (HU-6.1, RE-6.1.5 a RE-6.1.12):** `views/vet/area.php` repite el diseño del mockup aprobado. Arriba: siguiente paciente (nivel, edad, propietario, llegada, motivo y alergias; «Registrar llegada» o la acción de RE-6.1.5, y «Ver ficha»), el calendario del mes, «Atención sin cita» y «Hoy». Abajo: «Tu día», «Pendientes» y «Pacientes recientes».
- **Primer render y AJAX:** el primer render de hoy viene del servidor. Los parciales `views/vet/partials/inicio_dia.php` e `inicio_calendario.php` sirven también a `panel_dia_ajax`, así que no hay un segundo pintor en JS.
- **Servidor:** `DisponibilidadAgenda::diaDelVeterinario` es solo para el veterinario de la sesión. `Panel` suma días con citas, próximo día con citas, alertas activas, pacientes recientes y duración mínima de cita. `DiaVeterinario` arma las filas del día sin base de datos. `PanelController::diaAjax` valida la fecha y el mes y toma la agenda de la sesión. `panel_dia_ajax` entra en la matriz solo para el veterinario.
- **Consultas médicas retirada:** se quitan la ruta, el menú, la matriz, `views/vet/consultas.php`, `public/js/consultas.js`, `ConsultaController::listar` y, de `medical-module.js` y `consulta-clinica.css`, el buscador modal sin uso. `Consulta::listarDeLaClinica` se conserva, porque sus pruebas de RN-113 siguen vigentes.
- **Agenda:** abre la pestaña de herramientas del hash. Con `?agendar=&hora=` abre su modal en ese día y marca la hora cuando aparecen los horarios, si sigue libre.
- **Estilo y responsive (RNF-16):** hoja propia `public/css/inicio-vet.css`, cargada solo en vet_area; `panel.css` queda para el administrador.
  - Escritorio: cabe sin scroll en 1366×768 y 1440×900; solo la lista de «Tu día» se desplaza.
  - Tableta: siguiente paciente a todo el ancho y el resto en dos columnas.
  - Celular: una columna en el orden pedido y los cinco recientes. Bajo 480 px se ocultan el gato y el cachorro, que tapaban botones y texto.
- **Pruebas:**
  - Nuevas: `tests/Integration/InicioVeterinarioTest.php`, con huecos con almuerzo y cierre, huecos desde ahora y sin huecos en días pasados, día sin citas y sin jornada, aislamiento por clínica y por veterinario (un `id_veterinario` en la petición no cambia la agenda), calendario de otro mes y fechas inválidas, recientes y siguiente paciente. También `tests/Frontend/InicioVeterinario.test.cjs`.
  - Ampliadas: `ResumenPanelTest` y `SeguridadClinicaTest`.
  - Actualizadas por el retiro de Consultas: M2C5a, M2C7, M2C8.2, PropietarioDetalle (marcador de corte) y los arneses c81/c82. AgendaCitaPasos y DisponibilidadAgenda reciben el stub de `marcarHoraPreferida`.
- **Evidencia propia:**
  - `vendor/bin/phpunit`: 1027 pruebas, 8132 aserciones, 71 omitidas de motor.
  - `node --test --test-concurrency=1 tests/Frontend/*.test.cjs`: 312/312.
  - Consultas nuevas de `Panel` ejecutadas en MariaDB 10.4 local, solo lectura.
  - Chrome con el layout real, `PanelController` real y fixture SQLite (día lleno, pocas citas con cierre, día sin citas y sin pendientes) a 1440×900, 1366×768, 900, 600, 390 y 360 px: sin desborde horizontal y sin scroll de página en escritorio.
  - Interacciones comprobadas: otro día, día libre, día pasado sin huecos, otro mes y ventana de dosis, sin errores JS.
- **Pendiente:**
  - Revisión independiente y prueba del dueño con sus datos reales.
  - No hay evidencia de MySQL 8 ni de CI.
  - `panel.css` conserva reglas `panel--vet` sin uso y `medical/consultas.css` mezcla clases del cajón de historial con la lista retirada; limpiarlos queda para el pulido final.

## Inicio del veterinario — Detalles menores de presentación (2026-10-22)

**Autor: Codex (ejecutor). Entregada, pendiente de revisión.** Corrección de las cuatro observaciones remitidas por el dueño tras la entrega de Claude; se conserva el diseño y el contrato del Inicio.

- RE-6.1.10: nombres repetidos agrupados con cantidad («Mia (2) y Doki»); los contadores siguen contando solicitudes, sin eliminar ni fusionar triages. El resumen se escapa como antes.
- RE-6.1.11: estado vacío con la ilustración existente de Día libre, «Aún no has atendido pacientes» y explicación referida a la clínica activa. Sin activos nuevos ni carga de pacientes ajenos.
- RE-6.1.9: las agendas de hasta tres filas se centran verticalmente en el espacio de Tu día; se conservan horas, estados, enlaces y huecos. La alineación segura evita perder la primera fila si el contenido crece. No se agrega un resumen semanal, consultas nuevas ni otro flujo.
- RNF-16/HU-T.17: se elimina la pastilla vacía del encabezado móvil cuando su etiqueta está oculta y no existe Cambiar; con varios contextos, Cambiar sigue visible. Escritorio conserva el indicador completo. El ajuste vive en el CSS del contexto compartido, sin mover notificaciones/perfil ni editar el layout.
- Regresiones nuevas en InicioPulido: agrupación y total, escape, imagen real decodificada, agenda corta/larga/vacía y contexto único/múltiple. Fixture con controlador/layout reales y SQLite en memoria, reloj fijo; Chrome a 1366×768, 900/390/360×844, sin desborde horizontal ni scroll de página en escritorio. Capturas y logs ignorados en scratch.
- PHPUnit completo propio: **1027 pruebas, 8132 aserciones, 71 omitidas** de motor, sin host y con TRUSTED_PROXIES=127.0.0.2/base de pruebas explícita. No es MariaDB/MySQL ni CI nuevo.
- Sin nuevas reglas, permisos, servidor, esquema, versión o exportaciones. Sin .env, bases reales, correos ni operaciones de negocio. Se conserva la entrega de Claude; revisión independiente y prueba visual del dueño pendientes.
- Archivos: `views/vet/area.php`, `views/vet/partials/inicio_dia.php`, `public/css/inicio-vet.css`, `public/css/contexto.css`, `tests/Frontend/InicioPulido.test.cjs` (nuevo), `tests/Frontend/apoyo/inicio-pulido-vista.php` (nuevo), este plan y `agentes/estado.md`.

**Aclaración del dueño sobre Git:** el registro anterior que presentaba 987149b como autorizado fue una interpretación errónea del agente. El dueño indicó que solicita los comandos/mensaje para verificarlos y ejecutarlos él. No ejecutar commit, push ni tags por expresiones como «dame el commit»; entregar los comandos en el chat. No se modifica el historial existente.

Verificación final propia del pulido: **JavaScript 314/314**, sin omisiones; sintaxis de las dos vistas y la fixture PHP, y `git diff --check` correctos. No se ejecutaron comandos de commit, push, tags ni cambios del índice de Git.

## Casos de soporte del super-administrador (prueba del dueño en producción, 2026-10-24)

Registro con Google y una cédula que ya es de otra cuenta: abre el caso `documento_duplicado` como dice Modelos §13.1 (funciona). Pendiente de pulido, sin cambiar la regla:

- **Aviso por correo al super-administrador** cuando se abre un caso (decisión del dueño: lo quiere recibir). Sin datos del titular en el asunto.
- **Detalle del caso:** hoy la tarjeta no deja ver el detalle (qué cuenta lo pidió, cuándo, la cuenta dueña del documento) ni ofrece acciones claras.
- **Sin códigos internos en la descripción:** quitar «(al completar el perfil, RE-T.18.3)» del texto que ve el super-administrador; el RE va en la auditoría o en el código, no en la interfaz.
- El mensaje al titular («No se pudo completar el perfil. Revisa los datos o contacta a soporte.») se conserva (decisión del dueño).

## Inicio del administrador — Encargo (2026-10-24)

**Escribe: Claude Code (ejecutor). Revisa: Claude (arquitecto). HU-6.2 (RE-6.2.5 y RE-6.2.7 a RE-6.2.13), RN-415, RN-G01, RNF-11, RNF-16.** Referencia aprobada por el dueño: `specs/referencias/inicio-administrador.dc.html`. Es el gemelo del Inicio del veterinario y reutiliza sus piezas desde archivos comunes.

**Decisiones del dueño (2026-10-24):**

- Mockup aprobado: la lista filtrable de citas de hoy pasa a Gestión de citas (cambia RE-6.2.5); con más de tres veterinarios, la agenda se desplaza en horizontal dentro de la tarjeta; sin pendientes, la tarjeta se encoge y «Este mes» ocupa el espacio; «Este mes» muestra el % de no asistencia.
- Sin botón «Agendar» en «Hoy en la clínica»: por RN-401 el administrador no crea citas (`registrar_cita_ajax` es solo del veterinario).
- La minigráfica de «Este mes» muestra por mes las atendidas con las no asistidas encima, y su tabla accesible trae las dos cifras (cumple RE-6.2.4).
- «En espera»: citas de hoy con la llegada registrada que aún no empiezan, más las llegadas sin reserva en espera presencial. «Urgencias»: casos rojos de hoy, atendidos o en cola.

**Decisiones de implementación (para revisión):**

- Sin pantalla de planes para el administrador, «Ver planes» no se pone.
- Los botones de pendientes van a Gestión de citas (`admin_citas`, que no acepta filtros por URL) o a Configuración (`#horarios` para propuestas y `#ausencias` para ausencias; las pestañas ya se abren por hash).
- El hoy de «no asistieron» (RE-6.2.1) va en el subtítulo de «Hoy en la clínica» cuando es mayor que cero.
- Las ausencias se muestran como «Ausente · hasta las …», sin el motivo del mockup.

## Inicio del administrador — Resultado (2026-10-24)

**Escribió: Claude Code (ejecutor). Revisa: Claude (arquitecto). Entregada, pendiente de revisión.**

- **Qué:** `views/admin/panel.php` repite el mockup aprobado con las decisiones del encargo:
  - «Hoy en la clínica» (sin «Agendar»), calendario, uso del plan con accesos, agenda por veterinario, pendientes y «Este mes».
  - La agenda sale de `PanelController::filasDelDia`, que ahora comparte con «Tu día» del vet (sin duplicar huecos ni ausencias). `DisponibilidadAgenda::diaDelVeterinario` admite al administrador para los veterinarios activos de su clínica.
  - Lo común con el Inicio del vet pasó a archivos compartidos: `inicio-comun.css` y `inicio-comun.js` (tarjeta azul `inicio-hero`, calendario, filas, pendientes, esqueleto) y `views/partials/inicio_calendario.php`.
  - Se retiran `panel.css` y `panel-admin.js`. Sin cambios de esquema.
- **Decisión de implementación nueva:** entre 1024 y 1279 px, con tres o más veterinarios se ven dos columnas y el resto se desliza (tres columnas de ~140 px cortaban nombres y citas). En ese rango también se oculta el golden, que tapaba las cifras.

| RE | Prueba |
|---|---|
| RE-6.2.5 (cambia), RE-6.2.6 | `InicioAdministradorTest::testEstadosVacios`; `InicioAdministrador.test.cjs` (sin «Agendar», sin lista filtrable) |
| RE-6.2.7 | `testElInicioDeNorteNoTraeNadaDeSur` (en espera y urgencias por clínica) |
| RE-6.2.8 | `testCambioDeDiaYDeMesPorLaAccionDelAdministrador`; `SeguridadClinicaTest::testElDiaDelInicioDelAdministradorEsSoloDelAdministrador`; JS: cambio de día, mes y descarte de respuestas viejas |
| RE-6.2.9 | `testUnVeterinarioSinCitasApareceEnLaAgenda`, `testHuecosYAusenciasIgualesALaDisponibilidadReal`, `testElAdministradorSoloLeeVeterinariosDeSuClinica`; Chrome: 4 veterinarios se desplazan a los lados |
| RE-6.2.10 | `testUsoDelPlanContraLimitePlan`; `ResumenPanelTest::testElUsoDelPlanTieneSuNivel` |
| RE-6.2.11 | `testConteosDePendientes` |
| RE-6.2.12 | `testEsteMesPropietariosNuevosYNoAsistencia` |
| RE-6.2.13 | Chrome en `InicioAdministrador.test.cjs` (1366/1024/390) y capturas |

- **Verificación:**
  - PHPUnit sin motor: 1068 pruebas, 8472 aserciones, 74 omitidas de motor.
  - PHPUnit completo con MariaDB 10.4.32 local, solo `zooki_test_base_v2`: 1068 pruebas, 9583 aserciones, ninguna omitida (incluye `BaseV2MysqlTest`, que ejecuta `datosAdministrador` sobre el esquema real).
  - JS: 331/331.
  - Capturas en Chrome con layout y controlador reales sobre SQLite: 2 y 4 veterinarios, con y sin pendientes, a 1366×768, 1024×768 y 390×844, más la clínica vacía. Sin desborde horizontal de página y sin scroll de página en escritorio.
  - El Inicio del vet, revisado otra vez a 1440/1366/900/390, quedó igual tras partir la hoja.
- **Pendientes:**
  - Revisión independiente y prueba del dueño.
  - Sin MySQL 8 ni CI propios.
  - Las descargas de documentos se regeneran al cerrar la entrega (método §4).
  - Gestión de citas no acepta filtros por URL, así que los botones de pendientes abren la pantalla sin filtrar.
  - Las ausencias se ven como «Ausencia · hasta las …», sin el motivo del mockup.
  - En 1366×768, con los cinco tipos de pendiente, caben cuatro filas enteras y la lista se desplaza.

## Inicio del administrador — Revisión (2026-10-24)

**Revisó:** Claude (arquitecto). **Resultado: aprobada.**

- **Verificado:** diseño fiel al mockup en 1366×768 (sin scroll de página), 1024 y 390 px; cambio de día por el calendario; agenda por veterinario con citas, huecos, ocupación y ausencias desde la misma lógica del Inicio del vet; pendientes vacíos encogen la tarjeta. `diaDelVeterinario` admite al administrador solo para veterinarios activos de su clínica (sigue la comprobación de membresía); `panel_admin_dia_ajax` solo para el administrador. Las decisiones del encargo (sin «Agendar» por RN-401, sin «Ver planes» mientras no exista la pantalla, botones de pendientes a pantallas existentes) son correctas.
- **Pruebas del revisor:** PHPUnit sin motor 1068 en verde (74 omitidas de motor); PHPUnit completo con MySQL 8, base `zooki_test_base_v2`: **1068 pruebas, 9587 aserciones, sin fallos**.
- **Para el pulido final (no bloquean):**
  - Con la serie de 6 meses en cero, la minigráfica no se ve y deja un hueco; mostrar línea base y las iniciales de los meses (RE-6.2.4: los meses sin citas aparecen en cero).
  - `DisponibilidadAgenda::finDeApertura` escribe `America/Bogota`; usar `ReglaAtencion::ZONA`.

## Registro: el freno de comprobaciones parece un error del campo (prueba del dueño, 2026-10-25)

- Al registrar una clínica con muchas comprobaciones seguidas, `validar_cuenta_ajax` responde «espera un momento» y el formulario lo muestra como error del campo que tocaba (resumen: «Revisa estos campos… Correo de contacto: espera un momento antes de seguir comprobando»). Parece que el correo es inválido.
- Pulido: si la respuesta es el límite de comprobaciones, mostrar un aviso general («Hiciste muchas comprobaciones seguidas. Espera unos segundos y vuelve a intentar») sin marcar el campo en rojo ni incluirlo en el resumen, y reintentar la comprobación al enviar.
- Revisar el tope: `Security::MAX_VERIFICACIONES` = 20 comprobaciones por IP en 15 minutos (bloqueo de 15 min) se agota fácil en el registro de clínica, que comprueba NIT, DV y tres correos. Proponer un tope mayor o contar solo valores nuevos, sin abrir la enumeración (RE-T.13.2).

## Pulido final P1 — Resultado (2026-10-26)

**Escribió Codex; revisa Claude (arquitecto). Entregada, pendiente de revisión.** Versión preparada 2.2.0; rama actual release/v2.2.0. Sin esquema nuevo ni migraciones.
- A.1–A.4 primero: 40 comprobaciones por IP/15 minutos, freno general sin rojo/resumen de campos y reintento al enviar; base/iniciales de seis meses vacíos; zona común; singular y fechas compartidas dd/mm/aaaa.
- A.5 después: correo individual a super-administradores activos/verificados tras commit y respuesta completa; fallo aislado/log, sin identidad del titular ni SMTP real en pruebas. Reporte de NIT comparte CasoSoporte. Duplicados antiguos no abren otro caso ni reavisan.
- Detalle privado, también de cerrados: fecha/tipo, solicitante por id_usuario y cuenta actual del documento extraído solo del formato conocido; ausencia explícita si no se identifica. Códigos históricos ocultos sin alterar datos; resolver/descartar con nota y toast directo. Matriz existente solo de plataforma, revalidada por el modelo.
- B: tokens comunes del portal desde identidad.css; marca en avisos, mascota e historial; modal Zooki para acciones graves (también agenda), vínculo/restantes acciones reversibles directos; Atención actualiza sin esperar aviso y reprogramación usa indicador local. Se retiran 22 reglas sin consumidores comprobados; panel--vet ya no existía.
- **C fuera de P1 por decisión del dueño (2026-10-26):** otra etapa, pantalla por pantalla con supervisión. No se agregaron media queries ni cambios de distribución por ancho. D queda pendiente; no se empieza P2.
- **RNF-16:** nueve pantallas reales con datos simulados, solo 1366/390, sin desborde horizontal; detalle de soporte abierto. Capturas/medidas en scratch/p1. No es una aprobación del responsive completo.
- **Trazabilidad:** RE-T.13.2 → D21Correccion/RegistroClinica + D2ValidacionCuenta; RE-6.2.4/7 → InicioAdministrador/PulidoP1Visual; RE-2.5.2/RE-5.5.1/RE-5.9.2 → M2C82Historial/MascotaDetalle; RE-1.1.1 → comprobación visual de Pacientes; RE-T.14.1/RE-7.1.3/RE-4.2.1/RE-4.3.2 → D21Interfaz/PulidoP1Interacciones/CoordinacionHorarios; RE-0.3.4/RE-T.18.3/RE-T.5.8/RN-G24 → PulidoP1SoporteTest + HTTP.
- **Verificación:** PHP completo sin host 1165 pruebas/9351 aserciones, 91 omitidas de motor; JS final 354/354, sin omisiones. Cinco arneses antiguos adaptados tras la primera pasada JS; afectados 14/14 y completa en verde y cierre posterior al ajuste del enlace. Sintaxis y diff correctos. SMTP falso lento de 1,5 s: JSON idéntico recibido antes del envío, también al fallar. CI/MySQL 8 queda al push.
- Documentación HU/RE/Modelos, matriz RN-G24 y plan M0 coherentes, sin nuevos IDs ni conteos. Descargas del portal regeneradas con la plantilla, incluido el cierre HU-4.7; nueve PDF leídos y muestra visual revisada, nueve DOCX ZIP/XML correctos e índices actualizados con Word en copias fuera de OneDrive. LibreOffice no está instalado y Word no terminó de exportar la muestra en dos intentos: revisión visual de DOCX pendiente; fuentes finales conservadas. Evidencia en scratch/p1-documentos.
- Pendiente: revisión de Claude y prueba del dueño; integración/despliegue a cargo del dueño. Sin .env, base de trabajo, SMTP/API reales ni operaciones Git de escritura. Archivos y comandos en scratch/p1-entrega.md.

### P1 — Prueba manual del dueño (máximo ocho comprobaciones)

1. Registro de clínica: agotar comprobaciones, ver el aviso general sin campo rojo; esperar y enviar de nuevo.
2. Inicio del administrador sin citas en seis meses: línea base e iniciales, también en celular.
3. Historial, expediente e imprimible: «1 tratamiento», fechas dd/mm/aaaa y acentos de marca; contrastar el historial del portal.
4. Atención: cancelar el modal grave no guarda; finalizar actualiza el resumen sin esperar un aviso. Restaurar horarios y restablecer contraseña usan el modal de Zooki.
5. Pedir un documento ocupado desde perfil Google y desde Datos de acceso: mensaje genérico conservado, un caso abierto por titular/documento; correo privado a plataforma con asunto/cuerpo sin identidad del titular.
6. Plataforma → Soporte: ver cuentas/fecha/tipo, resolver o descartar con nota sin segunda confirmación y con toast; otros roles no acceden. Reabrir el detalle de un caso cerrado.
7. Portal: vincular/desvincular clínica, cancelar cita y registrar mascota actualizan al responder; reprogramar o arrastrar en agenda no mantiene el velo global mientras se envía el correo.
8. Repetir las pantallas anteriores en escritorio y celular: comprobar desborde horizontal a 1366/390. El pulido responsive completo C se hará aparte con el dueño.

## Pulido final P1 — Revisión (2026-10-26)

**Revisó:** Claude (arquitecto). **Resultado: aprobada**, con una corrección hecha por el revisor.

- **Verificado:** diff de PHP, vistas, JS y documentos. Tope 40/IP/15 min sin cambiar la respuesta genérica (RE-T.13.2). El freno ya no marca el campo y se reintenta al enviar. Correo de soporte después del commit, solo a super-administradores activos y verificados, sin identidad del titular; si el caso se deshizo, no se avisa. Detalle del caso con solicitante y cuenta dueña del documento, solo en plataforma. `ZOOKI_COLOR_OK` = `--z-primary`. `zookiConfirmar` delega en el modal de Zooki.
- **Pruebas del revisor:** PHPUnit sin motor 1165/9352 (91 omitidas de motor); JS 352/352; MySQL 8 dirigido: `PulidoP1SoporteTest` 7/43 y `BaseV2MysqlTest` 65/801. La suite completa en MySQL 8 la corre el CI.
- **Corrección del revisor (P2):** `CasoSoporte::programarAviso` medía Content-Length solo con su búfer. Con `output_buffering` del php.ini (XAMPP), una salida anterior quedaba en el búfer de abajo y el navegador recortaba el JSON. Ahora junta los búferes antes de medir. Comprobado con un servidor PHP con búfer previo (longitud exacta) y con `PulidoP1SoporteHttp` y `PulidoP1SoporteTest`.
- **Para después (no bloquea):** `tipoVisible` muestra «Solicitud de soporte» para cuenta_duplicada, abuso_plan y otro. D (bloquear al arrastrar) queda pendiente.
