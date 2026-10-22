# Pulido de interfaz — pendientes

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
