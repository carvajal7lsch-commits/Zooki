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

### Portal del propietario en el celular — encargo del dueño (2026-10-29)

**Entregada, pendiente de revisión. Escribió Codex; revisa Claude (arquitecto).** Solo presentación, HU-5.1/5.3/5.6/5.12/5.13, HU-T.5 y RNF-16; sin flujo nuevo en Modelos.md. Conserva identidad, fondo, carrusel, avatares, chips y barra inferior central. No usar specs/referencias/portal/ (descartado); bienvenida Idea2 existente y encargos móviles de Pacientes/Agenda como referencia de forma.

1. Inicio: grillas con columna minmax(0,1fr) para contener el carrusel de6 mascotas; deslizamiento dentro del contenedor.
2. Bienvenida mini<768 con el mismo HTML/PNG de escritorio: franja≈88px, saludo20/fecha11, azul claro/esquina40, perro≈104/imagen108/bottom≈−9, sin cruz ni recorte a360. Desde768 se conserva el estado anterior.
3. Citas e Inicio: hora en una línea, estado junto al nombre, clínica con elipsis, acciones de icono36 a la derecha. Tercera pestaña móvil Vacunas con aria-label completo; dosis sin desborde.
4. Clínicas móvil: ayuda existente en hoja corta por botón(i); tarjeta con logo/nombre/estado/llamar36, horario/dirección en una línea, autorización con interruptor, desvincular al pie. Otras clínicas con buscador completo, nombre sin recorte, dirección con elipsis y Vincular compacto.
5. Perfil móvil: cabecera avatar48/nombre/Propietario/documento y una lista Documento/Correo/Teléfono/Contraseña que abre formularios existentes con validación; teléfono con intl-tel-input en hoja. Cambiar de perfil solo con Contexto::puedeCambiar(), misma ruta, y Cerrar sesión. Escritorio conserva tres tarjetas.
6. Mascota móvil: cabecera en dos niveles (volver/avatar/nombre/imprimir/editar36, especie/raza), clínicas en tira desplazable y cuatro datos en2×2 sin partir valores/HC.
7. Reprogramar en todos los anchos: mismos campos de Agendar, etiquetas/input-group/select/fecha flatpickr con cierres de la clínica y pie de hoja. Conserva flujo/endpoints/validación de horarios.
8. Agendar móvil: opciones mascota/clínica≈56px y pie como Registrar mascota.
9. No tocar Editar mascota, Detalle de cita, Registrar mascota, Cancelar cita ni barra inferior; tableta/escritorio iguales salvo Reprogramar. Sin textos/datos/endpoints/rutas/permisos nuevos, JS/CSS en línea ni .env.
10. PortalMovil con Chrome360/390 y6 mascotas,2 próximas/3 pasadas,2 clínicas vinculadas/2 disponibles: desborde, patas±2/alto≤96, líneas, ayuda/tarjetas, hojas/validación/guardar/cambio de perfil, HC y flatpickr. Comparar medidas1366 antes/después y control820. Regresiones M5C2/M5C2b/M5C4a; cierre completo PHP también con host MySQL y base exclusiva zooki_test_base_v2, JS y capturas360/390/1366 en scratch/r/portal-movil/. Resultado/anexo con autor, máximo8 comprobaciones, comandos por tema; sin commit/push ni otra pantalla,2.2.0 sin publicar.

#### Portal en el celular — Resultado (2026-10-29)

**Escribió Codex; revisa Claude (arquitecto). Entregada, pendiente de revisión.** Lo delicado fue trasladar el formulario real del teléfono y los estados de cita, conservando sus validaciones, oyentes y restauración a≥768; se verificó también el repintado posterior a guardar. Reprogramar conserva el POST/clave de operación y corrige la lectura de los campos ya existentes id_usuario/nombre_completo del veterinario; sin cambiar la respuesta del servidor.

| Punto | Resultado y prueba dirigida |
|---|---|
| 1–2 Inicio | Columna minmax(0,1fr), carrusel interior y mismo banner de88px, saludo20/fecha11/PNG108 sin cruz. Patas a≈1,15px del borde (bottom−8 ajustado al alfa real del PNG). PortalMovil:360/390,6 mascotas, sin desborde ni recorte. |
| 3–5 Citas | Estado junto al nombre, hora sin salto, clínica con elipsis y acciones36; Vacunas accesible como Vacunas y controles; dosis ajustadas. PortalMovil:2 próximas/3 pasadas/3 pestañas. Avisos largos de reajuste siguen completos y envueltos, sin heredar nowrap de la hora. |
| 6 Clínicas | Ayuda original en hoja por(i), tres filas más pie, Llamar tel:36, autorización intacta; buscador completo y nombres de otras clínicas sin recorte. PortalMovil:2 vinculadas/2 disponibles, ayuda sin duplicar tras repintar. |
| 7 Perfil | Cabecera48 y lista de4 filas; los mismos formularios abren sus hojas y guardan con su validación actual, incluido teléfono/intl-tel-input. Cambiar de perfil condicionado por Contexto::puedeCambiar() y Cerrar sesión. PortalMovil:errores, cuatro POST, una instancia/campo y restauración; inputs nuevos16px para iOS. |
| 8 Mascota | Cabecera en dos niveles, chips en tira y datos2×2 sin partir HC. PortalMovil:360/390 y HC-000001 simulado. Dato previo fuera de este ajuste: la API actual no incluye numero_historia_clinica; se conserva «—» cuando no llega, sin inventar ni cambiar la respuesta. |
| 9 Reprogramar | Campos del portal, pie común y flatpickr con minDate/días cerrados por clínica; selector con nombres reales. PortalMovil:360/390/820/1366, domingo cerrado configurado, carga de horarios y mismo POST. |
| 10 Agendar | Opciones de mascota/clínica de≈56px, mismos avatares/textos y pie compartido con Registrar mascota. PortalMovil comprueba filas≤64px. Las cuatro hojas excluidas y la barra inferior no se editaron. |

- **Verificación:** M5C2/M5C2b/M5C4a35/35, sin modificar esas pruebas. PortalMovil/Bienvenida7/7 y cierre dirigido ampliado8/8, incluyendo reajuste. Una pasada JS completa428/428 con Chrome; sin repetirla tras los retoques dirigidos. PortalBienvenida solo actualiza la expectativa antigua de390: ahora ve la mini bienvenida;820 conserva el control anterior idéntico. Bibliotecas ya usadas por CDN fijadas con sus licencias solo en tests/Frontend/apoyo/portal-dependencias para ejecutar las pruebas sin red externa; producción conserva su carga.
- **PHP completo con motor local, base exclusiva zooki_test_base_v2:** 1207 pruebas/10922 aserciones, sin fallos ni omitidas; MariaDB10.4 de XAMPP, una sola pasada de19m38s. Sin .env ni base de trabajo. Capturas360/390/1366 de cada vista, pestañas y hojas, más control820, en C:\Users\Usuario\OneDrive\Desktop\Zooki\scratch\r\portal-movil\. Antes/después en antes/despues-medidas.json; comparación y logs en esa carpeta.

| Vista a1366 | x / y antes→después | Ancho antes→después | Alto antes→después |
|---|---|---|---|
| Inicio | 288 /36→288 /36 | 1023→1023 | 761,59→761,59 |
| Citas | 288 /36→288 /36 | 1038→1038 | 562,20→562,20 |
| Clínicas | 288 /36→288 /36 | 1038→1038 | 429,56→429,56 |
| Perfil | 288 /36→288 /36 | 1038→1038 | 438,70→438,70 |

- Las medidas de estas cuatro vistas también son idénticas a820. Se conservan fondo, carrusel, avatares/anillos, chips, menú y botón central. Sin nuevas HU, flujo, datos, permisos, rutas, respuestas, esquema ni versión;2.2.0 sin publicar. R sigue abierta y C fuera de P1 por decisión del dueño (2026-10-26).
- **Prueba del dueño (máximo7):** Inicio con6 mascotas y deslizamiento interior; Citas próximas/pasadas/Vacunas y sus acciones; Clínicas con(i)/Llamar/interruptor/buscador; Perfil con4 hojas/validación/Guardar/Cambiar de perfil; Mascota con2×2/chips/Imprimir/Editar; Agendar y Reprogramar con un día cerrado y pie accesible; control de las4 vistas a1366. Resultado/anexo con autor y comandos separados en scratch/r/portal-movil/; no se hizo commit/push ni se empezó otra pantalla.

#### Portal en el celular — Revisión (2026-10-29)

**Revisó Claude (arquitecto). Aprobada con una corrección del revisor.**

- **Verificado:** las 7 entregas (grafo A/B, navegación y skeleton, bienvenida, Usuarios/Pacientes y portal móvil) aplican en orden desde c6031e3 y dejan el índice idéntico a la carpeta. Sin CSS/JS en línea en views/portal. En Linux con Chrome: suite JS completa 428/429; PortalMovil, PortalBienvenida y M5C2/M5C2b/M5C4a 42/43. Capturas propias a 390 con 6 mascotas: Inicio, Citas, Clínicas, Perfil y Mascota sin desborde (antes Inicio medía 1001 px); hojas de Teléfono, Contraseña, ayuda (i), Reprogramar con flatpickr y Agendar compacto correctas.
- **Hallazgo corregido por el revisor:** la prueba «medidas de Inicio, Citas, Clínicas y Perfil conservadas» comparaba alturas exactas medidas en Windows; en Linux (como CI, sin Inter) el texto se parte distinto y fallaba (820/citas: 128,6 frente a 107,5 px) sin cambio de distribución. Ahora compara posición y ancho (geometria() en PortalMovil.test.cjs); pasa en Linux y falla ante una mutación de 8 px en .portal-cita.
- **Registro (no bloquea):** los commits 526d53e y 3c465ae del dueño solo llevaron los archivos nuevos porque se ejecutaron sin los bloques anteriores de comandos-todos.txt; se rehacen con git reset c6031e3 y la secuencia completa (remoto sin push).
- **Detalles menores para después, sin encargo:** el documento aparece en la cabecera del Perfil y otra vez en su fila; la hoja de ayuda de Clínicas repite su título en negrita; el pie de Agendar conserva la ayuda de dos líneas.

### Usuarios y Pacientes unificados con paginación — mockup aprobado (2026-10-29)

**Entregada, pendiente de revisión. Escribió Codex; revisa Claude (arquitecto).** Decisión del dueño: combinar tarjetas actuales de Usuarios con el orden superior de Pacientes. Referencias escritorio-usuarios-admin.dc.html y escritorio-pacientes-vet.dc.html en specs/referencias/usuarios-pacientes/. Datos reales, sin cambios de datos/permisos/rutas/respuestas. HU-T.7, HU-1.2/1.3/1.4, RNF-16; sin HU nuevas.

- ≥768: una hoja blanca de controles, pestañas subrayadas con conteo, acciones a la derecha (Invitar personal / Vincular mascota y Nuevo registro), buscador/filtros/vistas y resumen de TODO lo filtrado. Tarjetas debajo sobre el fondo, fuera de la hoja.
- Misma clase base y CSS compartido para la tarjeta blanca, sin borde, sombra suave/radio grande, avatar azul48, interruptor superior cuando corresponde, chips/datos con iconos y pie separado. Propietario sin interruptor: mascotas/cuenta/desvinculación, documento/teléfono/correo y pie con hasta3 mascotas o Sin mascotas aún, Editar/Reenviar según permisos; tarjeta abre detalle, botones no. Mascota: mismo input/listener de estado, foto/iniciales, especie/raza/HC/dueño e historia (con HC)/editar. Usuarios conserva Tú/interruptor atenuado, acceso y acciones de invitación.
- Paginación cliente de12 sobre lo cargado, tarjetas y tablas, TODOS los anchos. Pie como Auditoría: rango/total/tipo y Anterior/Siguiente; oculto≤12. Buscar/filtrar/cambiar pestaña reinicia página1. Resúmenes/conteos sobre el conjunto filtrado, no la página. Sin nuevas peticiones.
- <768 conserva diseño aprobado, solo suma paginación. Fuera: detalles, fichas, modales y otros módulos. Una sentencia por línea, sin JS/CSS en línea, colores --z-*. Nunca .env ni base de trabajo; MySQL de pruebas únicamente zooki_test_base_v2 si hiciera falta.
- Pruebas dirigidas de Pacientes/Usuarios y nuevas de30 elementos, reinicio, totales, pie oculto, clase común, tarjeta/acciones y envío de estado. Chrome390/820/1366 sin desborde; antes/después1366/390 de Personal/Propietarios/Mascotas en scratch/r/usuarios-pacientes/. Una pasada PHP completa sin host y JS completa con Chrome al cierre. Resultado con autor, entrega pendiente de revisión,3–5 comprobaciones y comandos incluyendo la referencia; sin commit/push ni otra pantalla,2.2.0 sin publicar.

#### Usuarios y Pacientes — Resultado (2026-10-29)

- **Escribió Codex; revisa Claude.** Riesgo principal: la paginación es local sobre lo ya cargado; no reduce la descarga inicial. Filtrar y paginar llevan marcas independientes: resumen/conteos cuentan el conjunto filtrado y el repintado de Clientes recupera un único pie. Sin datos, permisos, rutas ni respuestas nuevas.
- Controles en hoja blanca y tarjetas debajo a≥768; componente `.directorio-tarjeta`/`directorio.css` compartido y retirado el estilo alternativo de tarjetas de Pacientes. Avatares48, propietario sin interruptor y con resumen de mascotas, foto/iniciales y mismo interruptor de mascota; acciones conservadas. Doce tarjetas/filas por página, reinicio por búsqueda/filtro/pestaña y pie oculto≤12 en todos los anchos. Detalles/fichas/modales intactos; celular conserva distribución (solo cantidad con plural correcto y paginación).
- Dirigidas de regresión12/12 y bloque Usuarios/paginación26/26; ampliación dirigida8/8 verifica repintado real de Clientes, pie móvil libre y barra oculta al abrir el detalle. PropietarioDetalle, MascotaDetalle, PacientesCabecera/Escritorio/Movil/Hojas y Usuarios conservan navegación, acciones/POST, privacidad, escape y respaldo de foto. Chrome360/390/820/1366 sin desborde; control1440 y hoja a92px en escritorio. Capturas antes/después1366/390 con iguales datos simulados en scratch/r/usuarios-pacientes/ (más820/1440 y lista amplia de propietarios).
- Una pasada PHP completa sin host:1207 pruebas,9628 aserciones,94 omitidas. Una pasada JS completa con Chrome:423 pruebas,419 correctas y4 comparadores antiguos corregidos (DOM mínimo, «2 mascotas» y separación existente18px de la ayuda); reverificación afectada25/25, sin fallos pendientes conocidos y sin repetir suites completas ni modificar otras pantallas. Logs/anexo de Codex en scratch/r/usuarios-pacientes/.
- Comprobación del dueño: comparar hoja/tarjetas entre Usuarios y Pacientes a≥768; recorrer tres páginas/buscar/filtrar/cambiar tabla y comprobar total; abrir propietario y probar Editar/Reenviar sin abrir detalle; cambiar estado de mascota y verificar HC solo cuando existe; en celular comprobar filas originales y pie accesible sobre la barra. Comandos propios y secuencia completa de temas pendientes en scratch/r/usuarios-pacientes/comandos-commit.txt y comandos-todos.txt, incluyendo specs/referencias/usuarios-pacientes/. Sin commit/push, versión nueva, .env, base de trabajo ni otra pantalla; R sigue abierta, C quedó fuera de P1 por decisión del dueño (2026-10-26).

### Portal: bienvenida en el Inicio de escritorio — mockup aprobado (2026-10-29)

**Entregada, pendiente de revisión. Escribió Codex; revisa Claude (arquitecto).** Solo views/portal/index.php (Inicio) y public/css/portal/inicio.css. Referencia aprobada: specs/referencias/portal-inicio/escritorio-inicio-bienvenida.dc.html. El dueño descartó specs/referencias/portal/: no usarla. HU-5.1, RE-5.1.3/5 y RNF-16; sin HU nuevas ni cambios de datos, permisos, rutas o respuestas.

- Banner ≥1024 de112px, tres figuras en fila: saludo flexible con --bento-light y radio0 0 0 56px, fecha pequeña en mayúsculas y saludo30px con primer nombre azul; perro170×112 con --bento-primary y radio0 50% 0 0, overflow visible y sombra suave; círculo blanco112 con el SVG de cruz del login. Usar la hora de America/Bogota de la aplicación: mañana<12, tarde<19, noche.
- **Decisión del dueño (2026-10-29):** a1024 la columna principal mide324px y las figuras dejan solo22px para el saludo. Aprobado compactar solo las figuras y tipografía del banner entre1024–1279, manteniendo su alto112 y las columnas existentes; desde1280 se aplican las medidas exactas del mockup. Celular y tableta conservan su saludo actual.
- **Retoque del dueño (2026-10-29), entregado por Codex, pendiente de revisión; aclaración posterior:** bajar la imagen sin agrandarla. Conserva176px de ancho (120px en compacto) y la posición horizontal anterior; solo baja20px respecto al apoyo inicial para que ambas patas queden por debajo de la figura, como en el login. Sustituye el criterio anterior de uñas apoyadas a±2px y cabeza por encima; no cambia figuras, columnas ni el resto del portal.
- **Ajuste conjunto del dueño (2026-10-29), entregado por Codex, pendiente de revisión:** bajar otros4px la mascotica, sin agrandarla; acercar la ayuda de la barra al icono en administrador/veterinario (componente compartido con plataforma), manteniendo blanco/azul y teclado. Comprobación puntual, sin repetir suites completas.
- public/img/portal-bienvenida.png será copia inicial de cachorro-negro.png; decoración con alt vacío/aria-hidden. Compensar el8,6% transparente bajo las patas para dejar las uñas a±2px de la línea inferior y la cabeza ligeramente fuera por arriba. El dueño sustituirá solo ese archivo manteniendo la pose.
- Debajo quedan Próxima cita y su tarjeta intactas; Horario de hoy alinea con el banner. Mantener degradado, menú, botón Agendar, círculos/anillos y chips originales del portal. <1024 exactamente igual, saludo original sin banner. Sin desborde1024/1366/1920. Fuera: resto del portal y demás paneles.
- Pruebas dirigidas M5C2Portal/M5C2bPortal/M5C4aPortal, Chrome1366/1920 (figuras/alto/patas/desborde) y390/820 (saludo original y banner oculto), saludo por horas y escape del nombre; una pasada JS completa con Chrome. Antes/después1366 y control390 en scratch/r/portal-bienvenida/. Resultado con autor, entrega pendiente de revisión y comandos incluyendo referencia aprobada e imagen. Sin .env, base de trabajo, commit/push ni otra pantalla;2.2.0 sin publicar.

#### Portal: bienvenida — Resultado (2026-10-29)

**Escribió Codex; revisa Claude. Entregada, pendiente de revisión.**

- **Excepción aprobada:** a1024–1279, banner112px con texto20px/perro90×76/círculo64; desde1280, medidas exactas del mockup (30px/170×112/112). Tras la aclaración del dueño, la imagen conserva176/120px y baja20px respecto al apoyo inicial: la punta más baja queda19,83px fuera en1366/1920 y19,88px en1024; ambas patas salen. Horario de hoy sigue alineado. Sin desborde1024/1280/1366/1440/1920.
- **Reverificación del retoque:** escribió Codex. PortalBienvenida (Chrome)1/1 correcta a1024/1280/1366/1440/1920 y controles390/820 idénticos. Tras la captura del dueño se bajó8px adicionales para sacar también la pata más alta. Solo desplazamiento vertical, sin repetir suites completas; retoque-patas.log y retoque-antes-1366.png/retoque-despues-1366.png en la carpeta de evidencia.
- Solo Inicio y su CSS: saludo por hora de Bogotá y fecha de ResumenPanel, primer nombre escapado y un h1. Tonos del collage acotados al banner, sin importar styles.css global. Imagen decorativa public/img/portal-bienvenida.png, copia inicial del cachorro; basta sustituir ese archivo manteniendo pose/transparencia. Identidad, columnas, Agendar, tarjetas/anillos/chips, Próxima cita y acciones conservados;390/820 mantienen el saludo anterior.
- **Pruebas:** JS dirigidas37/37; PHP16/16,109 aserciones; sintaxis correcta. Única pasada JS completa con Chrome:418/419. El único fallo fue el comparador nuevo de capturas (salida excesiva por la scrollbar nativa); se corrigió y PortalBienvenida pasó2/2, sin pendientes conocidos ni segunda pasada completa. Comparación del contenido píxel por píxel en390/820 excluyendo16px del margen de scrollbar; reloj/fronteras/UTC, escape y uñas medidos con el alfa real del PNG.
- **Evidencia:** C:\Users\Usuario\OneDrive\Desktop\Zooki\scratch\r\portal-bienvenida\: antes-1366.png, despues-1366.png y despues-390.png; medidas antes.json/despues.json y capturas adicionales. Logs dirigidas.log/php-dirigidas.log/js-completo.log/bienvenida-reverificacion.log. comandos-commit.txt y comandos-todos.txt separan esta entrega de los tres temas anteriores e incluyen referencia aprobada/PNG; documentos divididos mediante parches sin modificar el índice.2.2.0 sin publicar; sin .env, base de trabajo, commit/push ni otra pantalla.
- **Dueño:** (1) banner/patas/alineación a1366 o1920; (2) compacto a1024 y saludo original a820/390; (3) Agendar, Próxima cita y tarjetas conservan aspecto/acciones.

### Navegación interna suave y ayudas de los iconos (2026-10-29)

- **Retoque del dueño (2026-10-29), escribió Codex; entregado, pendiente de revisión:** ajuste posterior de8 a14px para dejar más aire junto al icono. La ayuda se ancla a14px del icono, no al borde de la barra. Admin/vet/plataforma conservan blanco/azul, foco, escape, rutas y barra móvil. Comprobaciones dirigidas conjuntas2/2 con el desplazamiento del perro; retoque-patas-ayuda.log en scratch/r/portal-bienvenida y captura ayuda-cercana-1366.png en scratch/r/navegacion-interna. Sin otra pasada completa.

#### Corrección del recorrido completo y skeleton compartido — entregada, pendiente de revisión (2026-10-29)

**Escribió Codex; revisa Claude.** El dueño reporta que Inicio↔Usuarios y otros destinos siguen bruscos; pide incluir administrador, veterinario y plataforma, y añadir skeleton para los datos que tardan en cargar. La verificación anterior no cubría Inicio y aceptaba el fundido de respaldo sin exigir la transición entre documentos.

- Preparar los estilos críticos de navegación antes de scripts/librerías en los cuatro documentos base. Conservar enlaces PHP, permisos, rutas, historial, salida con cambios sin guardar, teclado y movimiento reducido. Probar ambos sentidos de Inicio y los destinos reales de cada barra, también con caché fría; el respaldo no sustituye la comprobación de la transición nativa en Chrome compatible.
- Skeleton propio compartido, sin dependencia adicional: formas compactas con tokens Zooki en la zona de contenido durante peticiones lentas; cabecera/barra disponibles. Sin flashes en respuestas rápidas, sin mostrarlo por consultar notificaciones en segundo plano, con solicitudes concurrentes/errores/abortos y movimiento reducido. No inventa datos ni adelanta peticiones.
- Pruebas dirigidas del recorrido y la carga; vistas PHP reales sobre SQLite efímera, sin ocultar el cargador en el arnés de navegación. Resultado con autor y comandos propios; sin .env, base de trabajo, commit/push ni cambio de versión (2.2.0 sin publicar).

##### Corrección y skeleton — Resultado (2026-10-29)

**Anexo escrito por Codex. Entregada, pendiente de revisión de Claude y comprobación del dueño.**

- **Alcance y riesgo:** se mantienen documentos PHP completos y todos sus enlaces/inicializadores. La mejora visual no convierte el sistema en una aplicación de una sola página ni acelera la consulta al servidor. No se agregó ninguna biblioteca ni se anticiparon consultas. El portal conserva su navegación propia.
- **Causa corregida:** la activación de la transición estaba dentro de pill-sidebar.css, después de scripts/librerías; con recursos nuevos por carga en desarrollo, Chrome podía revelar la vista sin esa preparación y omitir la transición. Se extrajo a navegacion-transicion.css, primera hoja de los cuatro documentos base, antes de scripts, y el fundido de salida/entrada comparte180ms sin retardo entre ellos. La prueba nueva exige transición nativa sin errores de captura en16 cambios reales con caché desactivada: Inicio↔Usuarios/Citas/Auditoría/Ajustes del administrador, Inicio↔Pacientes/Agenda/Mi perfil del veterinario y Clínicas↔Conocimiento de plataforma. Atención no es un destino de esa barra y no se rediseñó.
- **Skeleton común:** carga_contenido.php, carga-contenido.css/js en los tres contextos. Sustituye al velo blanco global con spinner, conserva los márgenes del contenido y deja libres cabecera/barra. Cuatro filas compactas neutras con pulso suave, anuncio de carga y aria-busy restaurado. Umbral400ms para evitar destellos en respuestas rápidas; contador de concurrencia, limpieza en error/aborto, respeto de sinLoader y movimiento reducido. Las notificaciones usan sinLoader y no cubren el módulo; las respuestas, los errores y las opciones de fetch se conservan.
- **Verificación dirigida11/11:** CargaContenido5/5 (incluye Chrome en admin/vet/plataforma), NavegacionInterna4/4 (incluye el recorrido nativo estricto con Inicio/caché fría), RCabeceraNotificaciones2/2. El arnés conserva dashboard.js y el cargador real. Sintaxis PHP de los cinco archivos de vista y JS correcta; sin suite general repetida. Último ajuste visual de márgenes revisado solo con la prueba Chrome afectada. Capturas a1366/390 y comprobación adicional360 sin desborde, preservando los campos existentes.
- **Evidencia:** scratch/r/navegacion-interna/correccion-skeleton.log, skeleton-visual-final.log, skeleton-1366.png y skeleton-390.png. Comandos actualizados en comandos-commit.txt/comandos-todos.txt de esa carpeta, conservando A/B previos aparte. 2.2.0 sin publicar; sin .env, base de trabajo, commit/push ni otra pantalla.
- **Dueño, tres comprobaciones:** (1) Inicio↔Usuarios/Citas/Ajustes y después Inicio↔Pacientes/Agenda/Perfil con el veterinario; (2) con red lenta, skeleton en el contenido y barra/cabecera utilizables, sin velo por cargar avisos; (3) cancelar la salida de un formulario sin guardar conserva la pantalla y sus datos.

**Entregada, pendiente de revisión. Escribió Codex; revisa Claude.** Pedido del dueño para la barra compartida de administrador, veterinario y plataforma: investigar el salto al cambiar de módulo frente al portal, suavizarlo y mostrar ayudas inmediatas con fondo blanco/texto azul al pasar el mouse, siguiendo su referencia visual. Sin cambiar el portal, rutas, datos, permisos ni respuestas.

- Diagnóstico: el portal alterna secciones ya cargadas; la barra interna navega entre documentos PHP, reinicia pageFadeIn de500ms y cambia de golpe la gota activa. Usar transiciones nativas entre documentos compatibles (barra estable, indicador activo y contenido breves), retirar la reaparición larga y conservar navegación normal, Ctrl/cmd-clic, Atrás/Adelante y beforeunload de formularios. Respaldo nativo y prefers-reduced-motion sin animación.
- Ayudas compartidas por ratón/teclado, blancas y azules, texto seguro, sin demora ni duplicar el title nativo; dentro del viewport, cierre al salir/Escape/clic, sin ocupar espacio ni estorbar la barra inferior del celular. Nombres/rutas/estado activo siguen viniendo de PHP.
- Solo componente común de navegación/CSS de layout y script externo; pruebas dirigidas de los tres roles, navegación real entre vistas PHP, vuelta atrás, aviso nativo de formulario, ayudas/foco/escape y movimiento reducido. Evidencia y comandos propios en scratch/r/navegacion-interna. Trabajo separado de A/B ya entregados, versión2.2.0 sin publicar; sin .env, base de trabajo, servicios reales, commit ni push.

Referencia técnica: [transiciones entre documentos, Chrome](https://developer.chrome.com/docs/web-platform/view-transitions/cross-document).

#### Navegación interna — Resultado (2026-10-29)

**Anexo escrito por Codex. Entregada, pendiente de revisión de Claude.**

- **Riesgo principal:** siguen siendo documentos PHP completos. El navegador puede omitir la transición nativa; un script externo pequeño en el head aplica entonces un fundido de respaldo de160ms. Sin interceptar enlaces, cambiar respuestas ni reemplazar el DOM de otros módulos. La barra permanece estable; el indicador cambia en200ms cuando hay transición nativa. Sin animación con movimiento reducido. La espera declarativa hasta el final del documento evita capturas de contenido todavía sin analizar; no se adelantaron peticiones ni acciones de negocio.
- Se retiró pageFadeIn de500ms y el aumento del icono al pasar el mouse. Ayuda compartida blanca/azul, inmediata, con texto seguro, aria-describedby y foco; cierre al salir, Escape, clic, scroll o cambio de página. En celular no se superpone a los nombres de la barra inferior. El contenido no conserva una capa de apilamiento de transición que encierre modales.
- **Verificación dirigida:** NavegacionInterna3/3 en Chrome aislado con vistas PHP reales/SQLite efímera: destinos de los tres roles, historial, Ctrl-clic, cancelación de beforeunload, ayudas/escape y respaldo con movimiento reducido. PlataformaCabecera y RUsuariosCabecera conservaron sus dos pruebas correctas en la ejecución precedente. PHP lint de los cinco archivos de vista y node --check de los dos scripts correctos. Sin repetir suites completas. Chrome puede notificar por CDP su aborto interno de captura al navegar: se admite solo ese mensaje exacto en la prueba, que exige el respaldo; cualquier error de la aplicación hace fallar la prueba.
- **Evidencia:** scratch/r/navegacion-interna/navegacion-final.log, dirigidas.log (cabeceras), capturas.log y capturas1366-ayuda.png/390-barra.png. Sin desborde en ambas capturas; scripts/pruebas y comandos propios separados de A/B. Versión2.2.0 sin publicar; sin .env, datos reales, commit ni push.
- **Dueño:** (1) cambiar de módulo y volver con Atrás en admin/vet/plataforma; (2) pasar el mouse y usar Tab/Escape en los iconos: ayuda blanca/azul sin espera ni duplicado; (3) dejar un formulario sin guardar e intentar salir: al cancelar se conserva la pantalla y el formulario.

Comandos: scratch/r/navegacion-interna/comandos-commit.txt, después de los dos commits A/B de scratch/r/grafo-comodidad/comandos-commit.txt. La misma carpeta de navegación contiene comandos-todos.txt con la secuencia completa; el dueño los ejecuta.

### Mapa del conocimiento: paneles compactos, ocultables y pantalla completa (2026-10-28)

**Parte A entregada, pendiente de revisión. Escribió Codex; revisa Claude (arquitecto).** Pedido del dueño y referencias demo-mapa-3d.html/escritorio-conocimiento-3d.dc.html en specs/referencias/plataforma. Parte A primero; parte B después y con entrega/commit separados. 2.2.0 sin publicar.

1. Solo grafo.php y grafo-conocimiento.css/js: filtros compactos sin scroll interno a1366×768; tipos en chips de dos columnas/32px con los inputs originales y atenuados al apagarse. Leyenda cerrada en details con cuatro líneas (peso, sentido, bloqueo, alarma); versión/cobertura en otro details cerrado con filas especie–cobertura.
2. Ficha compacta: relaciones de una fila ≤36px, punto/nombre elíptico/peso/valor decimal con coma; sin segunda línea; bloqueo rojo suave y advertencia gris solo si no es evidencia. Seis relaciones por grupo y Ver N más; tres fuentes en líneas título/enlace–entidad y Ver todas; reglas en details cerrado; cabecera de una banda con tipo/nombre/pastillas.
3. Botones Filtros/Ficha40 con aria-pressed/controls; columnas colapsables a0, transición corta/reduced-motion y ResizeObserver; localStorage protegido, todo visible si falla. Elegir nodo no abre ficha ocultada; el foco ofrece Ver ficha. Tableta con mismos botones; celular conserva mapa/hoja.
4. Fullscreen del contenedor completo: clase grafo--pantalla vía fullscreenchange, mapa100vw×100dvh, barra flotante y tarjetas superpuestas (filtros280/ficha340, blanco94%, radio18/sombra/scroll). Ambos lados empiezan ocultos; mismos botones, elegir nodo abre ficha flotante; Esc sale. Reencuadre al entrar/salir del nodo o mapa.
5. Sin datos/rutas/respuestas/motores nuevos; CSS tokens --z-*, nada en línea, una sentencia por línea. M2C6Grafo/RPlataforma y pruebas de alturas, expansión/details, ocultación/memoria/fullscreen; Chrome390/820/1366 sin desborde. Capturas1366 normal/sin filtros/sin ficha/fullscreen con ficha en scratch/r/grafo-comodidad. Dirigidas y una pasada JS completa con Chrome al cerrar A+B. Tres comprobaciones del dueño, anexo con autor, resultado y comandos; sin commit/push ni otra pantalla.

**5b — Entregada, pendiente de revisión. Escribió Codex; revisa Claude (2026-10-28):** paleta armónica en una constante de grafo-conocimiento.js: diagnóstico#0052FF (mayor), síntoma#8FB0FF (pequeño), fármaco#1E3A8A; especie#475569, raza#CBD5E1, condición#94A3B8; sustancia tóxica/bloqueo/punto de alarma#E11D48. Atenuados grises, conexiones y partículas enfocadas azules (rojas en bloqueo). Mismos colores en chips/leyenda/puntos de ficha/lista/mapa y M2C6Grafo actualizado. Sin datos/rutas/respuestas nuevos. La copia local de demo-mapa-3d.html todavía conserva la paleta anterior al recibir el encargo; prevalecen los valores expresos del dueño, sin modificar su referencia.

### Mapa del conocimiento: comodidad — Resultado A (2026-10-28)

**Entregada, pendiente de revisión. Escribió Codex; revisa Claude.** Riesgo principal: fullscreen requiere permiso del navegador; si se rechaza, el mapa normal sigue disponible. El respaldo a lista sin WebGL y las funciones puras se conservan.

- Filtros en chips de dos columnas/32px y ayuda/cobertura en details cerrados; panel sin scroll interno a1366×768. Ficha con cabecera compacta, relaciones de32px, efecto solo si no es evidencia, seis por grupo/Ver N más; fuentes en línea/tres/Ver todas y reglas plegadas. Puntos suspensivos con título completo accesible.
- Filtros/Ficha40 con aria-pressed/controls, columnas que recuperan espacio, memoria protegida y todo visible si falla. Con ficha ocultada se ofrece Ver ficha. Fullscreen nativo del contenedor, mapa de viewport y tarjetas flotantes280/340; ambos lados ocultos al entrar, selección abre ficha derecha, Esc restaura elección/encuadre. Movimiento reducido conservado. Tableta: filtros colapsables y ficha superpuesta como antes, para conservar ancho útil; mismos botones. Mapa/hoja del celular conservados.
- M2C6Grafo/GrafoComodidad/RPlataforma15/15 dirigidas; chequeos posteriores de tablet/posición derecha/almacenamiento bloqueado correctos. CI con ForceGraph3D falso; cuatro capturas1366 del 3D real en scratch/r/grafo-comodidad (normal, sin filtros, sin ficha, fullscreen/ficha derecha), sin errores de navegador. Pasada JS completa consolidada con B, sin repetirla.
- Sin datos/rutas/respuestas, motores, esquema ni base de trabajo nuevos; sin .env ni servicios reales. La versión sigue2.2.0 sin publicar; R abierta. No se hizo commit/push ni se inició otra pantalla fuera de B, pedida expresamente.

- **Resultado 5b:** una constante PALETA alimenta nodos, chips, leyenda, puntos de ficha y lista; rojo#E11D48 también en bloqueos/partículas/puntos de alarma, evidencia negativa y advertencia en gris. El volumen conserva el grado con variación acotada para que el diagnóstico siga siendo el mayor y el síntoma pequeño. M2C6Grafo13/13 y, junto a GrafoComodidad/RPlataforma,16/16; Chrome real1366/390 sin errores, capturas paleta-1366.png/paleta-390.png en scratch/r/grafo-comodidad. Sin repetir suites completas; actualización incluida en A, B sigue separado.

- **Retoque del dueño (2026-10-28), escribió Codex; pendiente de revisión:** Girar/Pantalla completa/Ficha agrupados a la derecha, separados5px; pastilla Super-administrador con icono/texto centrados verticalmente y fuente600, conservando40px. Comprobación puntual de Chrome y GrafoComodidad1/1; captura retoque-botones-1366.png. Sin repetir suites completas ni cambiar acciones.

- **Hover de plataforma (2026-10-29), escribió Codex; entregado, pendiente de revisión:** respuesta azul compartida en botones habilitados, cierres, controles del mapa, pestañas, campana/avatar, chips y desplegables; foco visible para teclado. Secundarios en azul suave, primarios/avatar en azul oscuro con texto blanco; acciones graves conservan su semántica roja. Chrome puntual con estados hover:10/10 controles del mapa/cabecera/modal correctos; evidencia hover-plataforma.json en scratch/r/grafo-comodidad. Sin JS ni acciones nuevos, sin suites completas repetidas; incluido en A.

- **Campana y cuenta (2026-10-29), escribió Codex; entregadas, pendientes de revisión:** el desplegable se cierra al pulsar fuera, elegir un enlace o usar Escape; abrir el otro menú cierra el anterior. La campana conserva el cierre por su botón. Escape devuelve el foco al activador y no cierra también la ficha del fondo. Script externo compartido desde cabecera.php, sin duplicarlo entre Clínicas y Conocimiento. PlataformaCabecera/E1Plataforma10/10, con Chrome1366/390 en ambas vistas; log campana-cierre.log. Sin rutas/permisos/respuestas nuevos ni suites completas repetidas; incluido en A.

### Parte B — Cabecera común más arriba (2026-10-28)

**Punto de separación A → B:** A implementada por Codex, dirigida15/15 correcta (M2C6Grafo/GrafoComodidad/RPlataforma), con cuatro capturas del 3D real y sin errores de navegador en scratch/r/grafo-comodidad. La pasada JS completa y la entrega se cierran después de B. El parche parte-a.patch conserva A antes de tocar la cabecera común.

**Parte B entregada, pendiente de revisión. Escribió Codex; revisa Claude.** RNF-16. Medir antes de tocar B con vistas PHP reales a1366×768: admin Inicio/Usuarios/Citas/Auditoría/Ajustes/Mi perfil; vet Inicio/Pacientes/Agenda/Atención/Mi perfil; plataforma Clínicas/Conocimiento. Tabla antes/después en Resultado. Cabecera a22 del borde,52 de alto,título20/subtítulo13/botones40 centrados,18 hasta contenido (±2px entre pantallas). Corregir layout.css .top-header/.content-body y quitar sumas de módulos (plataforma,pacientes,usuarios,admin-citas,etc.); <768 sin cambios, sin desborde ni contenido pegado. Prueba Chrome de las13 pantallas y regresiones existentes; actualizar solo medidas aprobadas. Commit aparte: fix(interfaz): cabecera comun mas arriba e igual en todas las pantallas (RNF-16).

### Cabecera común — Resultado B (2026-10-28)

**Entregada, pendiente de revisión. Escribió Codex; revisa Claude (arquitecto).** Regla en dashboard-modules/layout.css y retirada de sumas de escritorio en módulos: las13 vistas dan cabecera y=22/alto52, título y=26/línea24/font20, contenido y=92 (18px debajo) y controles40. Subtítulo13. El Inicio ordena saludo/fecha en la misma banda; Atención tenía el título vacío y ahora dice Atención solo ≥768. El perfil del administrador se mide dentro de Ajustes, donde vive actualmente. Se actualiza la clave del import de layout.css para invalidar la caché.

Tabla a1366×768 (px); misma caché de recursos y vistas PHP reales con datos simulados, sin scripts de negocio para medir la geometría. Las regresiones funcionales corren por separado:

| Pantalla | Título antes (y) | Hoja antes (y) | Título después (y) | Hoja después (y) |
|---|---:|---:|---:|---:|
| Admin · Inicio | 32 | 73,89 | 26 | 92 |
| Admin · Usuarios | 28 | 105,89 | 26 | 92 |
| Admin · Citas | 17,45 | 92 | 26 | 92 |
| Admin · Auditoría | 26,39 | 72 | 26 | 92 |
| Admin · Ajustes | 26,39 | 72 | 26 | 92 |
| Admin · Mi perfil (Mi cuenta en Ajustes) | 26,39 | 72 | 26 | 92 |
| Vet · Inicio | 32 | 73,89 | 26 | 92 |
| Vet · Pacientes | 28 | 109,09 | 26 | 92 |
| Vet · Agenda | 26,39 | 72 | 26 | 92 |
| Vet · Atención | Sin título | 72 | 26 | 92 |
| Vet · Mi perfil | 26,39 | 72 | 26 | 92 |
| Plataforma · Clínicas | 39,5 | 114 | 26 | 92 |
| Plataforma · Conocimiento | 39,5 | 114 | 26 | 92 |

- CabeceraComun recorre las13 vistas a1366/820/390; coincide a±2px, sin desborde. Las13 capturas móviles de B son idénticas antes/después, píxel por píxel (390×844). Medidas completas/capturas en scratch/r/grafo-comodidad/antes-cabeceras.json y despues-cabeceras.json.
- Dirigidas A+B16/16. Una sola pasada JS completa:406 pruebas,405 correctas y una expectativa antigua de Suscripción que añadía un h1 fuera de la cabecera. Se corrigió solo esa medición al contexto real y se reverificó RedisenoSuscripcion/GrafoComodidad5/5; sin fallos conocidos pendientes y sin repetir la suite. Sintaxis PHP/JS y git diff --check correctos. Sin PHP completo por ser el bloque visual solicitado.
- Anexo de Codex, evidencias y comandos separados A/B en scratch/r/grafo-comodidad. Sin datos/permisos/rutas/respuestas, esquema, motores, .env ni base de trabajo nuevos. R abierta; C fuera de P1 por decisión del dueño (2026-10-26); versión2.2.0 sin publicar. Sin commit/push ni otra pantalla.

**Tres comprobaciones del dueño:** (1) Ocultar Filtros/Ficha, recargar y elegir nodo con ficha oculta → Ver ficha; (2) fullscreen → elegir nodo/ficha derecha, Ver N más/fuentes/details y Esc; (3) comparar cabeceras en escritorio y comprobar que el celular conserva su posición y distribución.

**Ajuste del dueño (2026-10-29), escribió Codex; entregado, pendiente de revisión:** solo el grupo de controles de plataforma (Super-administrador/campana/avatar) se alinea al borde superior de su cabecera ≥768: y28→22,6px arriba. Se conserva la geometría del título y26/contenido y92 y el alto40. Comparación previa con el administrador y comprobación puntual1366/390; móvil conserva controles y24. Captura cabecera-subida-1366.png y medidas en scratch/r/grafo-comodidad. Incluido en B; sin suites completas repetidas.

### Panel de plataforma y mapa 3D — mockup aprobado (2026-10-28)

**Entregada, pendiente de revisión. Escribió Codex; revisa Claude (arquitecto).** Solo views/plataforma, plataforma.css/js, pagos-plataforma.js y grafo-conocimiento.css/js, todos los anchos. Referencias aprobadas: specs/referencias/plataforma/escritorio-clinicas, escritorio-detalle-clinica, escritorio-conocimiento-3d, celular-clinicas, celular-conocimiento-3d (.dc.html), demo-mapa-3d.html y capturas de la demo; datos de ejemplo sustituidos por los reales.

1. Cabecera minimalista compartida: título/icono azul/subtítulo, Super-administrador, campana y avatar; conservar navegación Plataforma/Conocimiento clínico.
2. Clínicas en una hoja: pestañas Clínicas/Soporte/Seguridad con conteos y alerta roja suave si hay IP bloqueadas; Pagos sin clínica con conteo. Buscar/estado/plan/vista40 y resumen cliente; cuadrícula4/3/2, avatar azul suave, NIT/plan/pago/admin/fecha o motivo, uso_plan y pie Desde/fecha/flecha. Toda tarjeta abre detalle; tabla/soporte/seguridad dentro de la hoja, acciones existentes.
3. Detalle clínica: hoja lateral 540 en escritorio e inferior móvil, identidad/NIT/admin/correo/estados/cerrar40; pestañas Plan y suscripción/Estado/Pagos e incidencias. Mismos contenidos/envíos/CSRF/validaciones/auditoría/respuestas; filas etiqueta–valor, planes, motivos, Cancelar y un primario.
4. Mapa: sustituir Cytoscape por vendor/3d-force-graph-1.80.0.min.js local/licencia. Tres columnas filtros/mapa blanco/ficha; buscar con sugerencias, especie, tipos/conteo/colores, cobertura/leyenda. Colores de demo por tipo, tamaño por grado, etiquetas HTML con graph2ScreenCoords, alarmas rojas. Foco/cámara/vecinos/partículas/pesos/bloqueos, fondo o Ver todo limpia; encuadre inicial/rotación lenta y reduced-motion. 3D/Plano(numDimensions2)/Lista, Girar y Fullscreen desde clic con fallo inocuo. Ficha con relaciones agrupadas/peso/navegación/fuentes/validación/nota RN-209; móvil mapa completo y ficha en hoja. Respaldo a lista sin biblioteca/WebGL. Conservar funciones puras ZookiGrafo, endpoint y datos; solo lectura global sin pacientes (HU-2.12, RE-2.12.1–6, RN-209/212).
5. Sin cambios de datos/permisos/rutas/respuestas ni motores; fuera admin/vet/portal. Colores --z-* (grafo en constantes JS), una sentencia por línea, sin CSS/JS en línea, sin .env ni servicios reales; MySQL solo zooki_test_base_v2. Sin desborde360/390/820/1366 ni barra tapando contenido. Pruebas dirigidas E1Plataforma/PagosPlataforma/M2C6Grafo con ForceGraph3D falso y respaldo; Chrome390/820/1366 sin depender de WebGL en CI. Antes/después 390/1366 y captura3D real en scratch/r/plataforma. Una sola pasada final PHP sin host/JS con Chrome. Resultado/autor/3–5 comprobaciones y comandos (vendor/referencias incluidos); sin commit/push ni otra pantalla. 2.2.0 sin publicar.

**Decisión del dueño (2026-10-28):** la campana usa los pendientes de Soporte y Seguridad ya disponibles en plataforma y enlaza sus pestañas. El super-administrador conserva su matriz de permisos; no consulta los endpoints de notificaciones de las clínicas. En Conocimiento se muestran esos enlaces, sin inventar conteos que esa vista no recibe.

### Panel de plataforma — Resultado (2026-10-28)

**Entregada, pendiente de revisión. Escribió Codex; revisa Claude (arquitecto).** Riesgo principal: el 3D requiere WebGL; ante biblioteca ausente o contexto no disponible se conserva la lista con filtros, ficha y fuentes (RE-2.12.6). Biblioteca 1.80.0 y licencia locales, sin CDN; el CI usa ForceGraph3D falso y la evidencia visual usa la biblioteca real.

- Clínicas/Soporte/Seguridad en una hoja; búsqueda flexible, resumen y conteos, tarjetas/tabla y detalle lateral 540/inferior. Acciones financieras y formularios con sus mismos envíos, CSRF y validaciones. La tarjeta utiliza el correo de administrador disponible, sin inventar nombres ni ampliar consultas.
- Mapa blanco, tipos/pesos/foco/cámara/etiquetas seguras, Plano/Lista/Girar/Pantalla completa y movimiento reducido. Relaciones y fuentes primero; datos del nodo y sinónimos en desplegable. Sin cambios de endpoint, funciones puras de búsqueda/filtros/textos, motores ni datos clínicos de pacientes (HU-2.12, RN-209/212). Colores de grafo como constantes; CSS en tokens --z-*.
- Verificación: dirigidas E1Plataforma/PagosPlataforma/M021Gestionar/M2C6Grafo y nueva RPlataforma correctas; Chrome 360/390/820/1366 sin desborde, cabecera sin solaparse y barra inferior sin tapar mapa/ficha. Una pasada JS completa 403/403. Una pasada PHP sin host 1207/9574, 94 omitidas de motor: tres expectativas del diseño anterior corregidas y sus dos clases reverificadas 25/25,308 aserciones; sin fallos conocidos pendientes. Retoque final de la ficha/leyenda verificado con M2C6Grafo/RPlataforma 13/13 y margen inferior 2/2, sin repetir suites completas.
- HU-2.12/RE-2.12.6 actualizan el respaldo de CDN a biblioteca local/WebGL; PDF/Word de HU/RE regenerados. Capturas antes/después 390/1366, 3D real con foco, logs, anexo de Codex y comandos en scratch/r/plataforma/. R sigue abierta, C fuera de P1 por decisión del dueño (2026-10-26), versión 2.2.0 sin publicar. Sin .env, base de trabajo, servicios reales, commit/push ni otra pantalla.

**Comprobaciones del dueño:** (1) Buscar/filtrar clínicas y abrir una tarjeta completa; (2) recorrer Plan/Estado/Pagos y cerrar la hoja; (3) campana → Soporte/Seguridad, también estando ya en Plataforma; (4) buscar «Vómito», navegar relaciones y Ver todo; (5) alternar 3D/Plano/Lista y abrir/cerrar la ficha en el celular, comprobando que la barra inferior no tape contenido.

### Pacientes en escritorio — mockup aprobado (2026-10-28)

**Parte A; entregada, pendiente de revisión. Escribió Codex; revisa Claude (arquitecto).** Referencias aprobadas en specs/referencias/pacientes-escritorio: escritorio-propietarios, escritorio-mascotas y escritorio-detalle-propietario. Datos reales, HU-1.2/1.3/1.4; preservar celular <768 aprobado salvo B. Se cierra y verifica A antes de B, y B antes de C.

- A1: ≥768, cabecera común Pacientes/subtítulo y una hoja blanca de radio 22. Pestañas subrayadas con conteos, Vincular/Nuevo a la derecha; buscador/especie/estado/vista, resumen cliente con total y sin activar/sin HC; cuadrícula 2/3/4 columnas a 820/1024/1366 y separación 14.
- A2: propietario con avatar azul suave de 44, nombre, plural de mascotas, lápiz discreto, contacto en tres líneas con icono gris y elipsis. Pie con hasta tres mascotas/iniciales si el endpoint puede aportar sus nombres con el mismo aislamiento y prueba PHP; si complica, solo N mascotas y aviso al dueño. Cuenta sin activar en gris/reenvío azul; desvinculado atenuado, sin contacto (RN-115). Tarjeta tocable/teclado, sin ojo; Editar/Reenviar no abren detalle.
- A3/A4: mascota con foto/respaldo 52/radio16, nombre/especie/raza/HC o Sin historia, Inactiva gris, dueño y sexo/edad; pie con el mismo toggle/listener y acciones HC (solo con HC, RN-102)/Editar de 34. Tarjetas/filas abren ficha por clic/Enter/Espacio y muestran foco; acciones internas no abren ficha. Tabla dentro de la hoja, cabecera gris, filas56/acciones34 y mismas columnas/datos.
- A5: detalle propietario con volver redondo, nombre y Pacientes › Propietarios. Hojas 360/resto; datos/estado/editar, mascotas en filas etiqueta–valor; Llamar/Correo secundarios y Agendar primario a lo ancho. Hoja mascotas con conteo/búsqueda/Nueva, filas70 con foto/nombre/especie-raza-sexo/edad/HC-estado/flecha. Apiladas a 820. No rediseñar ficha de mascota de escritorio.
- A6/verificación: mismos datos/permisos/rutas/respuestas/data-*/IDs/filtros/búsqueda/vistas/modales. Chrome 820/1024/1366 sin desborde y control idéntico a390; pruebas de clic/teclado/acciones aisladas, conteos/plural/filtros/RN-115/escape y regresiones PacientesCabecera/PropietarioDetalle/MascotaDetalle/PacientesMovil. Capturas antes/después 1366/820 y control390 en scratch/r/pacientes-escritorio.

### Pacientes — retoques de revisión (2026-10-28)

**Encargo del dueño; escribe Codex; revisa Claude.** Solo R, HU-1.2/1.3/1.4 y RNF-16: ≥768, buscador ocupa el espacio libre, segmentados centrados, sin raya junto al cambio de vista y selector gris de especie de40/ancho170 sin cortes; tarjeta sin mascotas con pie «Sin mascotas aún» y nombre/conteo separados2px; detalle con Edad de140 sin saltos, avatares azul suave y selector legible. <768, Estado/Esterilización a todo el ancho en Nueva/Editar mascota, sin recortes. Mismos datos/permisos/rutas/respuestas; sin otra pantalla. Pruebas dirigidas PacientesEscritorio/PacientesHojas, Chrome390/820/1366 sin desborde y capturas después en scratch/r/pacientes-retoques. 2.2.0 sin publicar; sin commit/push del agente.

**Resultado: entregada, pendiente de revisión. Escribió Codex; revisa Claude.** Corregidos los topes heredados de400/200px del buscador, centrado del segmentado y raya del cambio de vista; selector gris170×40. Nombre/conteo a2px en todas las tarjetas y pie «Sin mascotas aún». Edad140 sin saltos, avatares azul suave y especie legible en el detalle. Estado/Esterilización en dos filas completas en Nueva/Editar mascota, sin recortar etiqueta ni valor. Solo CSS/texto de vista; sin cambiar datos, permisos, rutas o respuestas.

- **Verificación:** PacientesEscritorio/PacientesHojas **5/5**, incluidos repintado tras guardar, acciones aisladas y envíos simulados; Chrome390/820/1366 sin desborde. Once capturas después y medidas en scratch/r/pacientes-retoques; prueba dirigida en pruebas.log. Sin repetir la batería completa por estos retoques de presentación.
- **Tres comprobaciones del dueño:** 1) alternar Propietarios/Mascotas y revisar buscador, especie y segmentado a820/1366; 2) revisar una tarjeta sin mascotas y abrir un detalle con edad de años/meses; 3) en celular, abrir Nueva y Editar mascota y comprobar «Esterilización / No se sabe» completos y pie accesible. Sigue revisión de Claude; no se inició otra pantalla.

### Pacientes en escritorio — Resultado

**A: entregada, pendiente de revisión. Escribió Codex; revisa Claude (arquitecto).** Una hoja con pestañas/conteos, herramientas, cuadrículas 2/3/4, tablas y tarjetas tocables por teclado, y detalle del propietario en hojas/filas. El único cambio de servidor es el resumen de hasta tres mascotas por propietario: misma clínica, solo vínculos activos, sin nombres/contacto de desvinculados (RN-115). La ficha de escritorio conserva su diseño; el control móvil conserva geometría y contenido (detalle a390 idéntico píxel a píxel).

- **Verificación dirigida:** 11 JS correctas (PacientesEscritorio, PacientesMovil, PacientesCabecera, PropietarioDetalle y MascotaDetalle); 9 PHP/86 aserciones (PropietarioResumenMascotas y PropietarioClinica). Chrome820/1024/1366: 2/3/4 columnas sin desborde, acciones internas separadas, mismo input/listener de estado y tarjetas/filas tras guardar sin recargar; control390. Capturas antes/después en scratch/r/pacientes-escritorio. B comienza después de este cierre; batería completa única al terminar C.
- **Dueño:** cambiar tarjeta/lista y filtrar; abrir propietario y volver al origen; abrir mascota y probar Editar/HC/toggle por separado; revisar desvinculado sin contacto y Reenviar sin abrir detalle. Reenvío y guardado se verificaron con respuestas simuladas, sin correos reales.

### Modales de Pacientes en el celular (2026-10-28)

**Parte B; entregada, pendiente de revisión. Escribió Codex; revisa Claude (arquitecto).** Solo después de cerrar/verificar A. Nuevo registro, Vincular mascota, Editar propietario/mascota: reutilizar el patrón de Usuarios/Nueva cita, hoja casi completa desde abajo, título/cerrar40, cuerpo desplazable de una columna y pie fijo con Cancelar de texto/un primario. Revisar/listar también los modales de ficha/historia/vacunas/desparasitación y adaptar los que tengan el mismo problema. Alertas/Revisar alerta se conserva como referencia. Mismos campos/validaciones/envíos/respuestas; ≥768 intacto. Chrome390 abre cada modal y comprueba no desborde, pie visible, cerrar y enviar. Capturas antes/después por modal en scratch/r/pacientes-modales.

### Modales de Pacientes — Resultado

**B: entregada, pendiente de revisión. Escribió Codex; revisa Claude (arquitecto).** Se reutilizan las piezas users-modal de Usuarios y el pie separado de Nueva cita: Nuevo registro (propietario/mascota), Vincular (misma entrada a Nueva mascota), Editar propietario/mascota, Historia, Vacunación y Desparasitación son hojas móviles. Cuerpo desplazable, campos en columna, cerrar40 y pie visible Cancelar/un primario; los botones originales de vacunas/desparasitación se reubican solo <768 y vuelven al lugar original al ampliar. Consulta y Alertas/Revisar alerta ya usan el patrón y se conservan; visor de foto a pantalla completa conservado.

- **Verificación dirigida:** PacientesHojas correcta: ocho entradas a390 sin desborde, cierre/scroll/pie, POST simulados originales de Nuevo/Editar propietario y Nuevo/Editar mascota, vacuna y desparasitación; retorno del mismo botón al cuerpo de escritorio a820. Capturas antes/después de las ocho entradas y Consulta en scratch/r/pacientes-modales. Sin servidor/permisos/campos/validaciones nuevos; ≥768 conserva las reglas anteriores.
- **Dueño:** abrir Nuevo y cambiar Propietario/Mascota; abrir Vincular y buscar; editar ambos tipos; bajar hasta los últimos campos y guardar/cancelar con el pie visible; recorrer Historia/Vacuna/Desparasitación y cerrar. La prueba usa respuestas simuladas, sin envíos reales. Sigue C tras este cierre; suite completa única al final.

### Mi perfil del veterinario igual al del administrador (2026-10-28)

**Parte C; entregada, pendiente de revisión. Escribió Codex; revisa Claude (arquitecto).** Solo después de cerrar/verificar B. Se descarta/ignora specs/referencias/perfil-vet-movil. Primero capturar menú y cada sección admin/vet a390/820/1366 y listar diferencias. Vet con el mismo encabezado/menú/hojas/comportamiento móvil menú→sección/volver del admin; únicas opciones extra Mi horario y Ausencias, con las mismas clases/parciales y lenguaje aprobado de Ajustes › Horarios/Ausencias (filas/días, hojas y controles40). Quitar resúmenes de Mi cuenta que el admin no tiene y duplican esas opciones; conservar indicadores pendientes. Mismos datos/permisos/rutas/envíos/validaciones/respuestas; admin idéntico antes/después. PerfilSecciones y horario/ausencias del vet correctas, prueba de estructura común más dos opciones y Chrome360/390/820/1366 sin desborde. Capturas de ambos roles en scratch/r/perfil-vet.

**Reglas comunes A/B/C:** una sentencia por línea, sin CSS/JS en línea, colores --z-* (azul/grises, rojo grave; sin naranja/amarillo fuera del triage, salvo estado activo verde suave explícito en A5). Sin .env, base de trabajo, dinero/correos/servicios reales ni MySQL ajeno a zooki_test_base_v2. Pruebas dirigidas al cerrar cada parte y una sola pasada PHP completa sin host/JS completa con Chrome al final. Resultados/autor/riesgos/3–5 comprobaciones por parte y comandos de commit separados, con archivos/parches por parte. No commit/push ni otra pantalla. Fuera Inicio vet, Agenda, Atención, portal propietario y resto del panel admin. R abierta, C del P1 original excluida por el dueño (2026-10-26); esta parte C de perfil es un encargo distinto. Versión 2.2.0 sin publicar.

### Mi perfil del veterinario — Resultado

**C: entregada, pendiente de revisión. Escribió Codex; revisa Claude (arquitecto).** HU-T.5/RE-T.5.1–5.7, HU-4.11/RE-4.11.5 y HU-4.12/RE-4.12.1–2: identidad y menú de cuenta extraídos a parciales compartidos con Ajustes, mismos paneles de cuenta/seguridad y encabezado común. El veterinario conserva solo sus opciones y añade Mi horario/Ausencias con el mismo lenguaje y filas desplegables del administrador; no recibe opciones ni permisos de clínica. Sin resúmenes duplicados en Mi cuenta; indicadores pendientes conservados. Horarios reutiliza ajustes-horarios.js, moviendo los mismos nodos/inputs y conservando sus oyentes.

- **Diferencias previas:** identidad vet vertical con rol/correo/miembro/acceso frente a identidad horizontal del admin; menú de pestañas sin grupos/flechas frente a lista de Ajustes; Mi semana/Ausencias repetidas en Mi cuenta; días en bloques altos en móvil; cabecera genérica frente a encabezado común. Ahora comparten estructura y hojas; nombres del rol, permisos y acciones de aprobación siguen siendo los propios. Referencia perfil-vet-movil descartada.
- **Verificación dirigida:** PerfilSecciones admin/vet y PerfilUnificado correctas; AjustesAdmin, CoordinacionHorarios, RHorariosAjustes y RAusenciasCierres correctas. Chrome360/390/820/1366 sin desborde, menú común más dos opciones, indicadores, navegación/hash/volver, contacto/identidad/contraseña y horario/ausencias conservados. Capturas menú/cada sección de ambos roles antes/después en scratch/r/perfil-vet; **21/21 capturas del administrador idénticas píxel a píxel** a390/820/1366. Cierre conjunto: PHP completo sin host 1207 pruebas/9621 aserciones, 94 omitidas por motor. Única pasada JS con Chrome 395/397; los dos fallos eran del doble DOM antiguo de M5C4bPersonal, corregido con querySelectorAll y reverificado 10/10. Sin fallos conocidos pendientes; no se repitió la batería completa. Regresión posterior del repintado al guardar: PacientesEscritorio 3/3, log js-repintado.log. Logs y parches separados A/B/C en scratch/r/pacientes-abc. Directorio de escritorio/tableta idéntico tras B/C (6/6 capturas).
- **Dueño:** entrar al perfil y abrir/volver de Mi cuenta y Seguridad; guardar teléfono y revisar cambio de identidad/contraseña; desplegar un día de Mi horario y enviar propuesta; abrir Ausencias y revisar solicitudes/pendientes; comparar Mi cuenta del admin y del vet. Sin correos, cobros, base de trabajo ni servicios reales en las pruebas.

### Pacientes en el celular — mockup aprobado (2026-10-28)

**Encargo del dueño; escribe Codex; revisa Claude (arquitecto).** Referencias aprobadas en specs/referencias/pacientes-movil/celular-propietario.dc.html y celular-mascota.dc.html; sus datos son ilustrativos. Solo detalle del propietario y ficha de mascota en vet_pacientes; sin nuevas HU ni cambios de datos/permisos/rutas/respuestas.

1. Propietario móvil: cabecera con volver redondo de 40 px a la pestaña de origen, nombre y Editar existente; sin migas. Hoja con avatar/nombre/estado, documento/teléfono/correo en filas etiqueta–valor y Llamar/Correo/Agendar en una fila de 40 px. Mascotas con conteo/+ Nueva secundario, búsqueda/especie en fila y filas completas tocables con foto/respaldo, nombre, especie/raza/sexo, HC o Sin historia, estado inactivo atenuado y flecha; sin Ver ficha en móvil.
2. Mascota móvil: volver a mascotas del propietario, Ficha de nombre y Editar; propietario en una fila tocable. Encabezado con foto/avatar/nombre/HC/estado y especie/raza/sexo/edad/peso; resumen clínico y preventivo en filas etiqueta–valor, alergias/alertas con conteo y Registrar. Pestañas existentes; Información general en dos columnas compactas.
3. Conservar listado, filtros, formularios e historia clínica; no tapar el final con la navegación ni desbordar 360/390. Tableta y escritorio sin cambios de diseño.
4. Excepción autorizada para todos los anchos: edad con singular/plural correctos (1 año, 10 meses; 2 años; 1 mes).

**Verificación prevista:** JS para regreso al origen, filas mascota/propietario, acciones conservadas, escape, respaldo de foto y edad; PropietarioDetalle/MascotaDetalle/PacientesCabecera correctas con ajustes solo del diseño aprobado. Chrome 360/390/820/1366; capturas antes/después 390/1366 en scratch/r/pacientes-movil. Una pasada completa final PHP sin host y JS con Chrome; MySQL únicamente zooki_test_base_v2. Fuera Mi perfil, Inicio del vet, Atención y otras pantallas. Sin .env, CSS/JS en línea, servicios reales, commit/push; colores --z-* y una sentencia por línea. R abierta, C fuera de P1 por decisión del dueño (2026-10-26), 2.2.0 sin publicar. Resultado con autor, riesgos, pruebas, pendientes y 3–5 comprobaciones del dueño; sin iniciar otra pantalla.

### Pacientes en el celular — Resultado

**Entregada, pendiente de revisión (2026-10-28). Escribió Codex; revisa Claude (arquitecto); el dueño supervisa en su celular.**

- **Mayor cuidado:** los botones de edición son los nodos originales trasladados a la cabecera móvil, con sus listeners/permisos y restauración al crecer a tableta/escritorio. La ficha de solo lectura conserva el aviso y oculta Editar/Registrar; las alertas registradas siguen visibles. Regreso al origen y fila del propietario usan la navegación existente, sin pedir datos adicionales ni cambiar respuestas. Al cambiar de sección móvil se empieza en su cabecera.
- **Qué cambió:** propietario con volver de 40 px, título/editar, contacto en filas y acciones de 40 px; mascotas como filas completas, foto con respaldo, HC/estado y filtros en una fila. Ficha con propietario tocable, encabezado compacto, resumen clínico/preventivo en filas, alergias/alertas con conteo y general en dos columnas. El cajón cerrado ya no proyecta sombra sobre el detalle móvil. En todos los anchos la edad usa singular/plural, omite meses cero tras años completos y calcula meses cumplidos con fecha civil local.
- **Trazabilidad:** HU-1.2 / RE-1.2.3 y HU-1.3 / RE-1.3.3 → PropietarioDetalle y PacientesMovil: mascotas, filtros, filas, estado, foto/respaldo y nombres escapados. HU-1.4 / RE-1.4.1 → MascotaDetalle/PacientesMovil: edición existente y solo lectura. HU-2.5 / RE-2.5.1/2 y HU-2.10 / RE-2.10.1/4 → MascotaDetalle: resumen de consulta/vacunas/desparasitación con fecha y clínica, pestañas e historia conservadas; sin inferir «Al día». RNF-16 → PacientesMovil a 360/390/820/1366, fila final libre de navegación y restauración de botones; edad/destinos Llamar/Correo/Agendar con pruebas JS separadas. Sin nuevas HU, reglas ni cambios del servidor.
- **Verificación:** 8/8 dirigidas (PropietarioDetalle, MascotaDetalle, PacientesCabecera, PacientesMovil). Una pasada PHP completa sin host MySQL: **1206 pruebas, 9612 aserciones, 94 omitidas por motor**; una pasada JS completa con Chrome: **393/393**, sin omitidas ni fallos. El retoque móvil de volver al inicio de la sección se comprobó también con la prueba dirigida de navegación. Sintaxis PHP/JS y git diff --check correctos. SQLite efímera, datos simulados y vistas/layout PHP reales; sin .env, base de trabajo, correos ni servicios externos reales.
- **Capturas:** carpeta C:\Users\Usuario\OneDrive\Desktop\Zooki\scratch\r\pacientes-movil\; antes-390-propietario.png, antes-390-mascota.png, despues-390-propietario.png y despues-390-mascota.png; los mismos nombres con 1366 y 820. Archivos JSON de geometría por vista/ancho: posiciones y tamaños iguales a 820/1366 antes/después (descontando la clase de estado, que no cambia su diseño). Chrome sin excepciones; sin desborde a 360/390/820/1366. Logs dirigidas.log, php-completo.log y js-completo.log en esa carpeta.
- **Pendiente:** revisión de Claude y las cinco comprobaciones del dueño de abajo. Sin commit ni push; comandos en scratch/r/pacientes-movil/comandos-commit.txt. R abierta, **C fuera de P1 por decisión del dueño (2026-10-26)** y versión 2.2.0 sin publicar. Historial/PDF/Word y cierre general de R se consolidan al cerrar R. No se inició otra pantalla.

Comprobaciones del dueño en su celular:

1. Entrar al detalle desde Propietarios y desde Mascotas; usar la flecha hasta volver a la pestaña de origen.
2. Buscar/filtrar mascotas, abrir una fila (también una inactiva) y tocar la fila del propietario para regresar a sus mascotas.
3. Probar Llamar, Correo, Agendar, Editar y + Nueva: deben abrir sus acciones habituales.
4. Revisar general/historia, el resumen preventivo con sus clínicas y una mascota con alertas activas; Registrar/Editar deben respetar el modo de solo lectura.
5. Comprobar foto/respaldo, edad (1 año/1 mes), nombres largos y que la última fila quede sobre la barra inferior al desplazarse.

### Agenda del veterinario y Nueva cita en el celular — mockup aprobado (2026-10-27)

**Encargo del dueño; escribe Codex; revisa Claude.** Referencias en specs/referencias/agenda-vet-movil: celular-agenda, celular-triage, celular-triage-revisar, celular-llegadas, celular-reasignaciones y celular-nueva-cita-1-paciente/2-urgencia/3-horario. Datos reales; HU-4.13, HU-4.15 y RE de reserva/llegadas/reasignaciones. Flujo de Modelos.md sin cambios.

1. Solo <768: Agenda con + redondo y campana/avatar comunes. Pestañas Agenda/Triage/Llegadas/Reasignar/Urgencias con conteos azul suave (rojo solo Urgencias), subrayado y desplazamiento interior sin barra. Conservar data-*, JS y permisos.
2. Agenda móvil sin vistas de FullCalendar: semana reutilizada del Inicio, puntos de eventos, Hoy/flechas y mes en hoja. Filtros en hoja (tipos/vet, punto de actividad), misma fuente/filtros; lista por día con hora, triage solo rojo/naranja/amarillo, mascota/tipo/propietario/estado y chips de recordatorio. Detalle y reprogramación existentes, sin aviso de arrastrar.
3. Triage: Pendientes/Revisadas y Actualizar, casos en filas y Revisar nivel en hoja. Cuatro niveles en botones de 44 px, calculado preseleccionado, motivo obligatorio validado en vivo y Confirmar nivel; mismo envío/huella/respuestas.
4. Llegadas: grupos actuales en filas compactas con acciones a la derecha, historial plegable y retraso rojo suave; mismos comandos/CSRF/validaciones.
5. Reasignaciones: mascota, hora estimada azul, plazo gris y Rechazar/Aceptar en fila. Urgencias con patrón móvil de Citas admin y Rojo presencial del vet.
6. Nueva cita móvil en hoja casi completa: Paciente/Urgencia/Horario, pie fijo con Cancelar/Atrás de texto y un primario por paso. Horario con Cambiar día, botones de 44 px Mañana/Tarde y resumen; reprogramación con la misma presentación móvil. **Excepción para todos los anchos:** horas legibles «9:00 a. m.» y nivel con mayúscula inicial.
7. Sin servidor/rutas/permisos/respuestas ni lógica clínica nuevos. Sin desborde 360/390 ni navegación inferior tapando el final. Escritorio 1366×768 y tableta 820 idénticos salvo formato del punto 6. Fuera: Pacientes, Mi perfil, Inicio del vet, Atención y lógica de triage/llegadas/reasignaciones.

**Verificación:** pruebas dirigidas de pestañas/conteos, semana/mes/filtros/lista, revisión con huella/motivo, llegadas/reasignaciones, tres pasos/volver/primario/envío único y formato de hora. Conservar AgendaCitaPasos, AgendaClinica, AgendaHerramientas, ReasignacionesAgenda, RCitasAdmin, InicioAdministrador e InicioVeterinario. Chrome 360/390/820/1366; capturas antes/después a 390 (cinco pestañas, revisión y tres pasos) y 1366 en scratch/r/agenda-vet-movil. Una pasada completa final PHP sin host/JS con Chrome; MySQL solo zooki_test_base_v2. Sin .env, base de trabajo, servicios reales, CSS/JS en línea ni ámbar ajeno a triage/estado; una sentencia por línea y colores --z-*/estados/triage. Resultado con autor/pruebas/pendientes y 3–5 comprobaciones móviles; sin commit/push ni otra pantalla. R abierta, C fuera de P1 por decisión del dueño (2026-10-26), 2.2.0 sin publicar.

### Agenda del veterinario en el celular — Resultado

**Entregada, pendiente de revisión (2026-10-27). Escribió Codex; revisa Claude (arquitecto); el dueño supervisa en su celular.**

- **Mayor cuidado:** los formularios conservan sus nodos, listeners, huella, claves de operación y envíos; las hojas solo cambian su ubicación. La franja del Inicio se extrajo a inicio_semana.php, identidad.css y ZookiInicio.pintarSemana; InicioAdministrador/InicioVeterinario siguen correctos. Cambiar el día de Nueva cita invalida la evaluación anterior con el mecanismo existente, conserva las marcas y vuelve a Urgencia antes de reservar.
- **Presentación:** Agenda/Triage/Llegadas/Reasignar/Urgencias con conteos, semana y mes en hoja, filtros originales y lista desde calendarInstance.getEvents(). Filas compactas, revisión del nivel en hoja con motivo obligatorio, grupos de llegadas e historial plegable, propuesta con hora azul/plazo gris y urgencias con Rojo presencial. Nueva cita tiene tres pasos solo en celular, un primario por paso y pie fijo; Reprogramar también es hoja. Los horarios/resumen muestran minutos y nivel capitalizado en todos los anchos; el instante original con segundos sigue en data-hora y en el envío.
- **Trazabilidad:** RNF-16 → AgendaVetMovil (4 pruebas Chrome: pestañas/conteos/semana/mes/filtros/lista/detalle y reprogramación; revisión con huella/motivo y comandos de llegada/reasignación; tres pasos/cálculo/cambio de día/volver/primario/envío único; medidas 360/390/820/1366). RE-4.13.1/3/4/5/6 → AgendaVetMovil, AgendaCitaPasos y DisponibilidadAgenda; RE-4.18.1/2 → revisión en AgendaVetMovil y pruebas existentes de agenda; RE-4.19.1/2/3 y RE-4.14.4/6 → AgendaClinica, ReasignacionesAgenda y comandos reales de sus scripts en AgendaVetMovil. HU-4.15 conserva rutas/permisos/validaciones de Urgencias; AgendaClinica y RCitasAdmin siguen correctos. No se añadieron reglas ni HU.
- **Verificación final:** PHP completo sin host MySQL: 1206 pruebas, 9612 aserciones, 94 omitidas por motor. Una pasada JS completa con Chrome: 389 pruebas, 384 correctas y 5 expectativas/mocks anteriores incompatibles con el tercer paso o el formato de hora. Se adaptaron únicamente esas pruebas y su DOM simulado; reverificación AgendaModalCita/DisponibilidadAgenda/ReasignacionesAgenda: **35/35 correctas**. Los flujos reales nuevos pasan; el cambio de día con recálculo se comprobó nuevamente. No se repitió toda la batería global. Logs en scratch/r/agenda-vet-movil/php-completo.log y js-completo.log; el log global conserva el resultado inicial para trazabilidad, sin fallos conocidos pendientes tras la reverificación.
- **Capturas:** C:\Users\Usuario\OneDrive\Desktop\Zooki\scratch\r\agenda-vet-movil\antes-390-*.png y despues-390-*.png: cinco pestañas, hoja de revisión y tres pasos; antes/despues-1366-agenda.png y antes/despues-820-agenda.png. Comparación de geometría con las mismas fuentes disponibles: medidas de cabecera, barra, calendario, panel y herramientas idénticas a 820/1366 (medidas-*-medidas.json frente a antes-*-medidas.json). Chrome sin excepciones; sin desborde a 360/390/820/1366.
- **Alcance:** sin cambios de servidor, esquema, rutas, permisos ni respuestas; sin .env ni servicios reales, base de trabajo, commit o push. **C sigue fuera de P1 por decisión del dueño (2026-10-26).** R sigue abierta; versión 2.2.0 sin publicar. Historial/PDF/Word y cierre general de R se consolidan cuando el dueño la cierre.
- **Pendiente:** revisión de Claude y estas cinco comprobaciones del dueño; ninguna otra pantalla iniciada.

Comprobaciones del dueño en el celular:

1. Recorrer las cinco pestañas (deslizar la tira si hace falta), apagar/encender Vacunas en Filtros y comprobar lista, conteos y punto activo.
2. Cruzar de mes con la semana y con la hoja del mes, volver a Hoy y abrir una cita para entrar/salir de Reprogramar.
3. Revisar un triage: nivel calculado preseleccionado, motivo vacío bloqueado, cambiar nivel/escribir motivo y confirmar una sola vez.
4. Registrar una llegada y revisar sus acciones; aceptar/rechazar una propuesta y comprobar que aparece el resultado sin duplicar el comando.
5. Abrir +, completar los tres pasos y volver; cambiar día (recalcula conservando marcas), elegir hora y agendar. Comprobar teclado, pie fijo y última fila sobre la navegación inferior.

### Cabecera minimalista y notificaciones — mockup aprobado (2026-10-27)

**Encargo del dueño; escribe Claude Code; revisa Claude (arquitecto).** Referencias: specs/referencias/cabecera-notificaciones/ (escritorio-cabecera, celular-cabecera, campana-estados, escritorio-notificaciones-admin, escritorio-notificaciones-vet y celular-notificaciones). Datos de las láminas de ejemplo; se usan los reales. HU-T.5/T.17, HU-4.11 (avisos internos), RNF-16. Piezas compartidas: views/partials/cabecera_cuenta.php, public/js/cabecera-cuenta.js, notificaciones de public/js/dashboard.js y sus estilos en public/css/dashboard-modules/layout.css. Colores solo `--z-*`: azul de marca y rojo solo para lo grave; sin amarillos ni naranjas.

1. Cabecera (opción A): a la derecha tres piezas de 40 px. Pastilla redonda con avatar de la clínica (iniciales en azul suave), nombre y flecha; enlace a seleccionar_contexto solo si `Contexto::puedeCambiar()`, si no sin flecha y sin enlace. Campana en círculo blanco con borde fino e icono de trazo delgado. Avatar de la cuenta en círculo `--z-primary` con iniciales blancas, sin nombre, rol ni flecha; abre el menú de cuenta existente, cuyo encabezado muestra nombre, correo, clínica y rol. Celular: campana y avatar en círculos de 40 px; la clínica sigue dentro de la hoja de cuenta. aria-label en cada botón.
2. Campana: gris sin nuevas; con nuevas, contador rojo (`--z-danger`) con las no leídas (1…9, «9+» desde 10) en lugar del punto. Sin animación de timbre ni color amarillo/naranja (`.notif-btn.ringing`). aria-label «Notificaciones, N nuevas».
3. Ventana: panel de 380 px; título «Notificaciones» para ambos roles (antes «Alertas Clínicas» en el vet) con «N sin leer» en gris pequeño; botón de icono (doble chulo azul, 36 px, borde fino) con aria-label/title «Marcar todas como leídas» y la misma acción; sin la pastilla «N nuevas» ni el texto largo. Cada aviso: icono en cuadro de 36 px azul suave según el tipo, rojo suave solo para CITA_CANCELADA y LIMITE_PLAN; título en negrita, mensaje, tiempo relativo y punto azul con fondo azul muy suave si no está leída. Clic marca como leída y abre el enlace con la lógica actual, por delegación y `data-*` (sin onclick en el HTML generado), todo escapado. Vacío: «No tienes notificaciones» con su icono. Celular: hoja inferior con el patrón de diálogos de Zooki y el mismo contenido.
4. Icono del título de cada módulo en `var(--z-primary)` mediante la regla de `.directorio-cabecera .users-page-title > i` o una compartida; revisar Usuarios, Citas, Pacientes y Auditoría.
5. Sin cambios de servidor, rutas, permisos ni respuestas de get_notificaciones_ajax. No se tocan Inicio (panel.php, area.php, inicio-*.css/js) ni Citas. 1366×768 sin cambios de distribución; 360/390 sin desborde. Fuera: cualquier otra pantalla o módulo.

Pruebas: contador (0 oculto, 1–9 exacto, «9+»), sin ringing; «N sin leer» y marcar todas (acción, contador 0, botón oculto); clic por delegación, escape de título/mensaje, rojo solo en CITA_CANCELADA/LIMITE_PLAN; pastilla enlace solo si puede cambiar; Chrome 360/390/820/1366 admin y vet sin desborde; actualizar RUsuariosCabecera, InicioPulido, RedisenoSuscripcion y las que cubran la cabecera. Capturas antes/después a 390 y 1366 en scratch/r/cabecera-notificaciones/.

#### Cabecera minimalista y notificaciones — Resultado (2026-10-27)

**Escribió Claude Code; entregada, pendiente de revisión de Claude (arquitecto) y aceptación del dueño.**

- Cabecera: pastilla de 40 px (avatar de la clínica, nombre y flecha; enlace solo con `Contexto::puedeCambiar()`, si no `span` sin flecha), campana redonda con borde fino y avatar azul con iniciales. El rol pasa al encabezado del menú de cuenta («Clínica · Rol», oculto en celular porque la hoja ya muestra la clínica con su rol). aria-label en pastilla, campana («Notificaciones, N nuevas» / «ninguna nueva») y avatar («Mi cuenta: nombre, rol»).
- Campana gris; contador rojo `--z-danger` con 1…9 y «9+». Se retiraron `.ringing`, el timbre animado, el punto con pulso y el ámbar del icono.
- Ventana de 380 px, título «Notificaciones» en ambos roles con «N sin leer» y botón de icono de 36 px (44 px en la hoja). Avisos como `<button>` con `data-notif-id`/`data-notif-enlace` y un solo oyente en la lista; icono Bootstrap por tipo en azul suave, rojo suave solo CITA_CANCELADA y LIMITE_PLAN; vacío «No tienes notificaciones». En celular el mismo panel se mueve a una hoja `zk-dialogo` con asa. Apertura/cierre (clic fuera, Esc, asa) pasan a cabecera-cuenta.js; dashboard.js conserva datos, marcar una y marcar todas sin cambiar las rutas.
- Icono del título de Usuarios/Citas/Pacientes en `--z-primary` (regla de `.directorio-cabecera` en usuarios.css). **Auditoría no tiene icono en su título** (h1 simple del layout): no se agregó uno, queda a decisión del dueño.
- Sin servidor, rutas, permisos ni respuesta de get_notificaciones_ajax. Cambio visible fuera de lo pedido: en pantallas cuyo alto de cabecera lo fijaban los controles de 44 px (título de una línea, p. ej. agenda del vet), el contenido sube 4 px; sin cambio de distribución.
- **Verificación:** RCabeceraNotificaciones (nueva, 2 pruebas) correcta: contador 0/1/5/9/10/27, color y etiqueta, «N sin leer», marcar todas (acción, 0, botón oculto, ventana abierta), clic delegado con el id, sin `onclick`, escape de título/mensaje/enlace, 11 tipos con rojo solo en los dos graves, vacío, Esc/clic fuera/asa, piezas de 40 px, menú con clínica y rol, pastilla enlace/no enlace, icono azul y 360/390/820/1366 en admin y vet sin desborde. RUsuariosCabecera (más avatar solo con iniciales), InicioPulido, RedisenoSuscripcion, AjustesAdmin y PerfilSecciones correctas sin otros cambios. Pasada final: PHP completo sin host 1206/9611 (94 omitidas de motor); JS completo con Chrome 385/385. Capturas antes/después: `scratch/r/cabecera-notificaciones/antes|despues/` (admin/vet × 1366/390 × cabecera/notificaciones).
- **Prueba del dueño (5):** 1) Escritorio admin y vet: a la derecha solo pastilla de clínica, campana y avatar del mismo alto; con una sola clínica la pastilla no tiene flecha ni abre nada. 2) Con avisos sin leer, número rojo en la campana (pocas: el número; 10 o más: «9+»); sin timbre ni amarillo. 3) Abrir la campana: «Notificaciones», «N sin leer», doble chulo marca todas y el número desaparece. 4) Tocar un aviso con enlace: va a su pantalla y queda leído; cita cancelada y límite del plan en rojo, el resto en azul. 5) Celular 390: campana abre hoja inferior que se cierra con el asa, Esc o el fondo; el avatar abre la hoja de cuenta con la clínica, «Cambiar» y Salir.

### Inicio del administrador en el celular — mockup aprobado (2026-10-27)

**Encargo del dueño; escribe Codex; revisa Claude.** Referencias: specs/referencias/inicio-admin-movil/celular-agenda.dc.html, celular-pendientes.dc.html, celular-mes.dc.html y celular-calendario.dc.html; escritorio aprobado en inicio-administrador.dc.html. HU-6.2 / RE-6.2.5–13 y RNF-16. Datos reales, sin valores de ejemplo.

1. Solo <768 px: fecha pequeña en mayúsculas y saludo de 20 px, fijos arriba con campana/avatar comunes. Tarjeta azul compacta Hoy en la clínica, veterinarios/horario, Citas › y cuatro cifras en una fila; sin decoración circular.
2. Pestañas Agenda/Pendientes (conteo)/Este mes según Usuarios/Citas, con línea inferior; mostrar un bloque y recordar la selección en hash o sessionStorage protegido por try/catch.
3. Agenda: franja de siete días con elegido/puntos y flechas de semana; mes abre calendario completo en hoja inferior con flechas existentes. Elegir día conserva panel_admin_dia_ajax y defensa ante respuestas viejas, y cierra la hoja. Puntos de los mismos datos del calendario; al cruzar mes, petición mensual existente. Selector por vet con avatar/nombre/citas/ocupación y barra; una sola agenda visible, sin carrusel lateral, selector oculto con un vet. Filas de hoy sin cambios de significado.
4. Pendientes: misma lista/conteos/destinos, título y detalle en una línea y acción secundaria azul suave a la derecha.
5. Este mes: una hoja, secciones Este mes/Uso del plan separadas por línea. Consultas/minigráfica/comparación y franja de tres datos; barras del plan y al límite rojo; Usuarios/Suscripción como filas con flecha y mismos enlaces.
6. Pestañas, franja semanal y hoja del calendario reutilizables mediante inicio-comun.css/js y parcial común con data-* genéricos. No activar la adaptación en el Inicio del vet todavía; comprobar su aspecto y funcionamiento en celular y escritorio.
7. Sin servidor/rutas/permisos/respuestas nuevos. Sin desborde 360/390 ni navegación inferior tapando el final. Escritorio 1366×768 sin scroll y tableta 820 idénticos. Fuera: Inicio/Agenda del vet, Pacientes y otras pantallas.

**Verificación:** pruebas dirigidas de pestaña/recuerdo, puntos/semana/cruce de mes, hoja/selección, AJAX y respuestas viejas, selector/ocultamiento con uno, enlaces/acciones y no desborde. Conservar comprobaciones de InicioAdministrador, InicioVeterinario e InicioPulido. Capturas antes/después a 390 (tres pestañas/hoja) y 1366 en scratch/r/inicio-admin-movil; una pasada final PHP completo sin host y JS completo con Chrome. MySQL solo zooki_test_base_v2 si fuera necesario; nunca .env, base de trabajo o servicios reales. Una sentencia por línea, sin JS/CSS en línea, variables --z-*/--in-*, SweetAlert solo toasts. R abierta; C fuera de P1 por decisión del dueño (2026-10-26); 2.2.0 sin publicar. Entregar autor/resultado/pruebas/pendientes, 3–5 comprobaciones móviles y comandos de commit; no hacer commit/push ni empezar otra pantalla.

#### Inicio del administrador en el celular — Resultado (2026-10-27)

**Escribió Codex; entregada, pendiente de revisión de Claude y aceptación visual del dueño.**

- Solo <768: cabecera fija con saludo de 20 px y cuenta/campana comunes; tarjeta Hoy compacta sin decoración y cuatro cifras en fila. Agenda/Pendientes/Este mes conservan la pestaña por hash y sessionStorage protegidos por separado.
- Agenda con franja semanal y puntos del calendario real, cruces de mes mediante la petición mensual existente y mes completo en hoja inferior. Selección de día conserva panel_admin_dia_ajax y la defensa ante respuestas antiguas; la hoja se cierra al elegir. Selector de vet con nombre/citas/ocupación y barra original; solo una agenda visible, selector oculto con uno. Sin cambiar el significado de las filas.
- Pendientes mantiene lista/conteos/destinos; Este mes agrupa cifras/gráfica, plan y accesos en una hoja. Sin servidor/rutas/permisos/JSON nuevos; solo marcado de los parciales existentes para leer metadatos ya disponibles. HU-6.2 y RE-6.2.9 aclaran la excepción móvil aprobada por el dueño; IDs, conteos y matrices documentales conservados.
- Piezas optativas en inicio-comun.css/js e inicio_movil.php, con data-* genéricos, claves de pestaña y destinos derivados del marcado. El Inicio del vet no las habilita y conserva sus vistas/acciones. Escritorio/tableta no se reorganizan.
- **RE-6.2.5–13 / RNF-16:** RInicioAdminMovil correcta (2 casos Chrome): pestañas/recuerdo incluso con almacenamiento bloqueado, semana/puntos/selección, cruce de mes y hoja, respuesta vieja ignorada, selector por vet/ocultamiento con uno, enlaces, último elemento accesible y sin desborde a 360/390. InicioAdministrador mantiene sus comprobaciones de escritorio/cuatro columnas y comprueba ahora la selección móvil real. InicioVeterinario e InicioPulido en verde.
- **Verificación completa única:** PHP sin host 1206 pruebas / 9611 aserciones, 94 omitidas de motor; JS con Chrome 383/383. Sintaxis PHP/JS y diff correctos. Comparación local de capturas PNG y medidas idénticas antes/después a 820 y 1366, con 1366×768 sin scroll de página. El primer caso de semana exigió recarga real del arnés (cambiar solo el hash conservaba noviembre); corregido el arnés y repetida solo la prueba afectada antes de la pasada completa.
- **Capturas:** `C:\Users\Usuario\OneDrive\Desktop\Zooki\scratch\r\inicio-admin-movil\antes\` (390.png, 390-pendientes.png, 390-mes.png, 390-calendario.png, 1366.png, 820.png) y `C:\Users\Usuario\OneDrive\Desktop\Zooki\scratch\r\inicio-admin-movil\despues\` (390-agenda.png, 390-pendientes.png, 390-mes.png, 390-calendario.png, 1366.png, 820.png). Antes no existían pestañas: se capturaron los bloques equivalentes. Vistas/controlador reales con SQLite efímero; revisadas las cuatro vistas móviles.
- **Pendiente:** revisión de Claude y las cinco comprobaciones del dueño de abajo. R abierta, C fuera de P1 por decisión del dueño (2026-10-26); 2.2.0 sin publicar. PDF/Word e Historial consolidado al cierre de R. Sin .env, base de trabajo, servicios reales, commit/push ni otra pantalla; cambios previos conservados.

**Prueba manual del dueño en el celular (5):**

1. Alternar Agenda/Pendientes/Este mes y recargar: conserva la pestaña y no repite bloques ni títulos.
2. Cambiar de semana hasta cruzar de mes; abrir el nombre del mes, mover sus flechas y elegir un día: cierra la hoja y aparece esa agenda.
3. Elegir otro veterinario: solo se ve su agenda, citas/ocupación y barra; al volver a otra fecha no quedan filas de la anterior.
4. En Pendientes, comprobar conteos y tocar Ver/Revisar: abre el destino correspondiente.
5. En Este mes, comprobar cifras/plan y llegar a Usuarios/Suscripción sin que la navegación tape el último acceso.

### Gestión de Citas del administrador — mockup aprobado (2026-10-27)

**Encargo del dueño; escribe Codex; revisa Claude (arquitecto).** Referencias aprobadas en specs/referencias/citas-admin: escritorio-agenda, escritorio-triage, escritorio-urgencias, escritorio-detalle, celular-agenda, celular-filtros y celular-detalle. Sus datos son ilustrativos; la aplicación conserva los reales. RE-6.2.5, HU-4.13 y HU-4.15; RNF-11/RNF-16.

1. Cabecera compartida como Usuarios: título único «Citas», subtítulo «Agenda de la clínica por veterinario» y pestañas Agenda/Triage/Urgencias con conteos de citas filtradas, solicitudes pendientes y rojos activos. Paneles existentes en la misma hoja, con data-* y JS conservados. No cambiar calendario.php ni las pestañas Llegadas/Reasignaciones del veterinario.
2. Herramientas de 44 px: Hoy/Ayer/Semana/Mes/Todas; fecha específica con SelectorFechas (custom); búsqueda de paciente/propietario/motivo; tipos, estados y equipo; Exportar con menú Excel/PDF y las funciones existentes. Conservar la semántica de aplicarFiltros.
3. Franja con fecha del periodo y N citas/pendientes/completadas/canceladas usando actualizarMétricas. Limpiar filtros solo si difieren del inicial. Retirar KPI grandes, sparklines y código sin uso.
4. Tablero en grid por veterinario, separado por líneas, con desplazamiento interior/flechas cuando cuatro columnas no quepan. Dos iniciales, nombre completo sin Dr., N citas/N pendientes. Filas con hora y dd/mm para periodos de varios días, mascota, propietario/tipo y estado mediante data-estado/estados-cita.css; canceladas/no asistidas atenuadas. Vacío «Sin citas». Detalle por clic/Enter/espacio y salida escapada.
5. **Decisión 1 del dueño:** punto de triage solo rojo/naranja/amarillo junto a la hora y aria-label «Triage <nivel>». Agregar prioridad a listarTodasCitasAjax desde c.*, sin esquema/migración ni cambiar aislamiento por clínica; prueba PHP del campo y aislamiento.
6. Detalle en dialog de Zooki, hoja inferior móvil: mascota y estado, dd/mm/aaaa · HH:mm · tipo; Paciente/Propietario/Veterinario/Tipo y Motivo o «Sin observaciones». Solo lectura, mismos datos; sustituye Swal.fire.
7. Triage con Pendientes/Revisadas, Actualizar, ayuda gris, filas de nivel calculado/revisado, nombre, síntomas, edad/sexo/vacunas y «Espera al veterinario» para admin. Urgencias con Rojos activos/Identidad pendiente lado a lado en escritorio y apilados en móvil; conservar Acreditar llegada. **Decisión 2 del dueño:** Conciliar identidad abre ventana de Zooki con el formulario/validación/envío existentes en ambos roles, en lugar del details; preservar pruebas y comportamiento del veterinario.
8. Móvil: Citas y Exportar de icono con campana/avatar comunes; pestañas con número/subrayado; periodo en fila; buscador/Filtros con conteo y hoja inferior de fecha opcional/tipo/estados en chips/Limpiar/Ver N citas; chips por veterinario para el mismo filtro; lista agrupada, sin desborde a 360/390 y última fila accesible sobre navegación.
9. Agenda a 1366×768 sin scroll de página hasta cuatro citas por columna; más citas con scroll interior. Tableta 820 sin desborde. Mismas rutas/permisos/respuestas salvo prioridad. Fuera: calendario del veterinario, Inicio, lógica de triage/urgencias/reasignaciones y otras pantallas.

**Verificación autorizada:** pruebas dirigidas PHP/JS de filtros (incluido custom), métricas/Limpiar/puntos, detalle seguro por ratón/teclado y cierre, paneles/admin de solo lectura, conciliación en ambos roles, hoja/chips y regresión de agenda veterinaria/urgencias. Chrome 360/390/820/1366, capturas antes/después a 390/1366/1440 en scratch/r/citas-admin. Una pasada final de PHP sin host y JS completo con Chrome; MySQL solo zooki_test_base_v2 si hiciera falta, nunca la base de trabajo. Sin .env, esquema, migración, servicios reales, commit ni push. Estilo en español, una sentencia por línea, sin CSS/JS en línea, variables --z-* y estados-cita.css, SweetAlert solo toasts. Se conserva 2.2.0 sin publicar, C fuera de P1 por decisión del dueño (2026-10-26). Al entregar: resultado con autor/trazabilidad/pruebas/pendientes, 3–5 comprobaciones manuales y comandos de commit; no iniciar otra pantalla.

#### Gestión de Citas del administrador — Resultado (2026-10-27)

**Escribió Codex; entregada, pendiente de revisión de Claude y aceptación visual del dueño.**

- Cabecera única Citas/Agenda/Triage/Urgencias con conteos; herramientas compactas, calendario de SelectorFechas, exportación agrupada, periodo/franja de métricas y tablero en una hoja. Columnas con iniciales/nombre completo, filas/estados compartidos, vacíos breves y scroll interior. Sin KPI grandes/sparklines ni SweetAlert de detalle/exportación (solo toasts).
- Celular: cabecera común, exportación de icono, periodos, buscador/hoja de filtros, chips de estado/veterinario y grupos de filas; navegación inferior sin impedir llegar al final. Detalle nativo seguro con fecha dd/mm/aaaa, cierre por teclado/X/fondo y hoja inferior; el calendario cabe dentro de la hoja.
- **Cambios compartidos autorizados:** conciliación en diálogo para ambos roles, con el mismo formulario, validación, comando, CSRF y envío. Triage del administrador sigue en solo lectura; los paneles conservan data-* y rutas. calendario.php, Llegadas/Reasignaciones y reglas clínicas intactos. Paneles del admin integrados mediante una opción explícita del parcial, sin cambiar la estructura del vet.
- **Backend:** único campo agregado, prioridad, de c.* a listarTodasCitasAjax; sin consulta, permiso, esquema ni migración nuevos. CitasAdminPrioridadTest correcta: prioridad naranja y solo citas de la clínica activa (5 aserciones).
- **RE-6.2.5 / HU-4.13 / HU-4.15 / RNF-16:** RCitasAdmin verifica filtros originales/custom y búsqueda, métricas/Limpiar, chips/veterinarios, puntos solo rojo/naranja/amarillo, detalle por clic/Enter/espacio, texto malicioso literal, Esc/X/fondo, exportación Excel/PDF simulada, Triage solo lectura y conciliación validada/enviada en admin/vet, incluida alternancia completar/consolidar y ventana a 360 px. Sin desborde 360/390/820/1366/1440; escritorio sin scroll de página con cuatro citas por columna. AgendaHerramientas y AgendaClinica correctas.
- **Pasada completa única:** PHP sin host, 1206 pruebas / 9611 aserciones, 94 omitidas de motor; JS con Chrome, 381/381. Retoques posteriores de periodo, calendario y presentación verificados solo con los casos nuevos afectados. La última ejecución agotó 30 s en Chrome del vet; repetido únicamente ese caso, correcto en 2,7 s, con margen de prueba ajustado a 45 s. Sintaxis PHP/JS y diff correctos.
- **Capturas reales simuladas:** `C:\Users\Usuario\OneDrive\Desktop\Zooki\scratch\r\citas-admin\antes\` (390.png, 1366.png, 1440.png) y `C:\Users\Usuario\OneDrive\Desktop\Zooki\scratch\r\citas-admin\despues\` (390-agenda.png, 1366-agenda.png, 1440-agenda.png, 1366-triage.png, 1366-urgencias.png, 1366-detalle.png, 390-filtros.png, 390-detalle.png). Revisadas; librerías de calendario/iconos cacheadas solo en scratch para el arnés, servicios simulados y SQLite efímero.
- **Pendiente:** revisión de Claude y las cinco comprobaciones del dueño de abajo. R abierta, C fuera de P1 por decisión del dueño (2026-10-26), versión 2.2.0 sin publicar; sin .env, base de trabajo, servicios reales, commit/push ni otra pantalla. Cambios previos del dueño/Claude conservados; Historial final/PDF/Word quedan para el cierre de R.

**Prueba manual del dueño (5):**

1. Admin, Citas en escritorio: Hoy/Ayer/Semana/Mes/Todas y fecha específica; combinar estado/tipo/búsqueda, comprobar conteos y Limpiar. Exportar el conjunto filtrado.
2. Admin a 360/390: abrir Filtros, escoger una fecha y un estado, Ver N citas y cambiar de veterinario con los chips; comprobar que la última fila se alcanza sobre la navegación.
3. Abrir una cita por clic y por Enter/espacio: datos/estado y fecha correctos; cerrar con X, Escape y fondo. En celular, detalle como hoja inferior.
4. Admin, Triage: alternar Pendientes/Revisadas y Actualizar; niveles/datos legibles, sin controles de decisión clínica. Urgencias: grupos y Acreditar llegada conservados.
5. Admin y vet, una identidad pendiente de prueba: Conciliar identidad abre ventana; alternar completar/consolidar, verificar errores junto a campos y guardar una conciliación acreditada. En vet, Llegadas/Reasignaciones y agenda siguen funcionando.

### Revisión de Claude — entregas de R del 26 y 27 de octubre (2026-10-27)

**Resultado: aprobadas, con una corrección del revisor.** Se revisó el commit `f8ac09d`: Datos de la clínica, Horarios, Ausencias y cierres, retoques de Ajustes, acabado móvil con la cabecera común, Auditoría, y Usuarios con la barra superior. Revisión liviana (metodo.md §11): diff, puntos de riesgo y suites completas una vez.

- **Verificado:** PHPUnit completo sin host 1205 pruebas/9606 aserciones (94 omitidas de motor). JS completo con Chrome real: 373 de 378 antes de la corrección. Los 5 fallos no eran de lógica ni de seguridad (ver abajo); después de corregirlos, __JS_FINAL__.
- **Logo (RE-7.4.1, RE-7.4.5):** valida permisos y clínica antes de escribir el archivo; comprueba el tipo real (finfo + getimagesize); genera el nombre en el servidor; tope de 5 MB; si falla el guardado, retira solo el logo nuevo. Coincide con el flujo de marca de Modelos.md. `Dockerfile` + `docker/subidas.ini`: al desplegar 2.2.0 hay que reconstruir la imagen.
- **Cabecera común (HU-T.5, HU-T.17):** conserva el contexto («Cambiar» solo si `puedeCambiar`), la campana, la cuenta y Cerrar sesión. Sin permisos nuevos: `admin_usuarios` sigue solo en la matriz del administrador. Las acciones móviles de Usuarios reutilizan los botones que ya pinta el servidor.
- **Auditoría:** el detalle se pinta con `textContent` y los secretos salen como «[Protegido]» desde el servidor. No queda JS que apunte a los KPI retirados. Las vistas no llevan `style=`, `onclick=` ni scripts en línea.
- **Corrección del revisor:** 5 pruebas en rojo habrían hecho fallar el CI, que corre con Chrome.
  1. `InicioPulido`: buscaba `.contexto-actual`, que la cabecera común reemplazó. Ahora comprueba la clínica de escritorio de `cabecera_cuenta` y su enlace «Cambiar».
  2. `PerfilSecciones` (admin y vet): la prueba de flechas corría a 390 px, donde el menú se oculta al abrir una sección. Se mide a 1366 px, y ahí el teclado funciona.
  3. `RedisenoSuscripcion`: esperaba el avatar como enlace. Ahora busca «Mi cuenta» dentro del menú del avatar (`data-cuenta-destino`).
  4. `RHorariosAjustes`: en Equipo sobraban 5 px a 1366×768. Se quitó el margen inferior de 8 px de `.ajustes-horarios__barra` (el formulario ya separa con 12 px). Es el único cambio visual.
- **Lista para después (P3, no bloquea):** intl-tel-input se carga dos veces en Usuarios (en el layout y en la vista). El selector de hora pone 00:00 si se borra la hora y se pulsa Listo, y deja 12:00 si se cierra sin pulsar Listo. `usuarios-movil.js` deduce el rol y el estado leyendo iconos y textos de la fila (mejor con `data-*`). El logo anterior queda en disco (decisión documentada). Hay `#fff` y `#64748b` sueltos en el CSS de Usuarios.
- **Sigue del cierre de R (pendiente de Codex):** Historial 2.2.0 final, exportación PDF/Word y CI con MySQL 8 después del push.

#### Prueba manual del dueño (5)

1. **Ana Norte, Ajustes › Datos de la clínica:** arrastra un PNG, guarda y recarga; el logo se ve. Renombra un PDF a `.png`: debe salir un error junto al campo y no guardarse.
2. **Ana, en el celular, Ajustes › Ausencias y Nuevo cierre:** elige el día y después la hora (no deja escribir 25), pulsa Listo y guarda; la lista muestra la fecha y la hora elegidas.
3. **Ana, en el celular, Usuarios:** toca una fila; se abre la hoja con las acciones. Restablece una contraseña, o desactiva a alguien y vuelve a activarlo.
4. **Elena Doble, cabecera en escritorio y celular:** el avatar abre el menú o la hoja, «Cambiar» lleva al selector de contexto y la campana abre los avisos. **Beto (vet):** no ve Usuarios.
5. **Ana, Auditoría:** abre el detalle de un cambio de contraseña; debe salir «[Protegido]». Exporta a Excel.

Dime solo lo que falló, con el número del paso.

### Usuarios móvil y barra superior — mockups aprobados (2026-10-27)

**Espacio bajo Editar usuario — escribió Codex (2026-10-27); entregada, pendiente de revisión de Claude.** Hallazgo visual del dueño: el subtítulo heredaba de styles.css un margin-bottom de 1.75rem usado en acceso. Se restablecen sus márgenes en la identidad del modal (4 px arriba, cero abajo), interlineado 1.4 y padding vertical de cabecera de 16 px; móvil conserva sus 14 px existentes. Solo CSS de Usuarios, sin tocar el formulario ni sus acciones. Revisión puntual de cascada/diff; validación visual del dueño pendiente, sin repetir suites ni capturas.

**Corrección móvil pedida por el dueño (2026-10-27), escribe Codex.** Última indicación prevalece sobre el texto del mockup: alta solo «+» accesible y sin subtítulo ni nombre repetido de clínica bajo el título. Aplicar el ocultamiento de subtítulos en la cabecera común admin/vet; compactar Usuarios conforme a la lámina, sin cambiar permisos o escritorio. Las hojas de persona/cuenta recuperan el asa de cierre del mockup, sin X adicional; el formulario conserva su X original con borde y añade el asa decorativa que figura en la referencia. Resto de ventanas conserva sus controles de cierre existentes. Verificación dirigida al botón, hojas/cierre, ambos roles y desborde a 360/390, sin pasada completa.

**Resultado — escribió Codex; entregada, pendiente de revisión de Claude y aceptación del dueño.** Alta móvil de 40×40 px con «+» de 26 px y nombre accesible; sin repetir la clínica bajo el título (permanece dentro de Cuenta). Subtítulos de cabeceras comunes ocultos en celular en ambos roles; escritorio intacto. Usuarios con título de 18 px, márgenes laterales de 16 px, filas de 64 px, búsqueda de Clientes a todo el ancho y espacio inferior para alcanzar la última fila sin taparla con navegación. Las hojas Persona/Cuenta usan un asa que cierra al tocarla, con área de 80×40 px y etiqueta accesible; formulario con asa decorativa y X original con borde. Regla compartida del asa en dialogos-zooki.css. RUsuariosCabecera correcta: 360/390/820/1366 sin desborde, botón/hojas/cierres/alta, permisos, búsqueda y cuenta de ambos roles; PHP/JS y diff correctos. Capturas puntuales revisadas en `C:\Users\Usuario\OneDrive\Desktop\Zooki\scratch\r\usuarios-cabecera\despues\`: `390-personal.png`, `390-clientes.png`, `390-invitacion.png`, `390-nuevo.png`. Solo se repitió la prueba afectada al corregir una regla que reducía el «+»; sin suites completas ni exportaciones. R abierta, 2.2.0 sin publicar y C fuera de P1 por decisión del dueño (2026-10-26).

**Retoques de ventanas — escribió Codex (2026-10-27); entregada, pendiente de revisión de Claude.** A petición del dueño, Restablecer contraseña separa un título corto de la explicación en peso normal. El componente de confirmación compartido incorpora X accesible que cancela, incluida en el recorrido de teclado, y descripción opcional por textContent/aria-describedby; conserva Escape, fondo, retorno de foco y confirmación explícita para invalidar sesiones (HU-T.7). Editar usuario agrupa nombre/documento debajo del título, fuera del h3 y alineados a la izquierda; X a la derecha. 41 pruebas dirigidas correctas (M5C4aPortal, M5C3Personal, D21Interfaz), incluidas cancelación por X, texto literal y restauración del foco; sintaxis PHP/JS correcta. Sin repetir capturas ni suites completas; revisión visual del dueño y cierre consolidado de R pendientes. Sin backend, versión nueva ni commit.

**Altura de tarjetas — escribió Codex (2026-10-27); entregada, pendiente de revisión de Claude.** Corrección pedida por el dueño tras la adaptación de escritorio: Personal y Clientes igualan las filas de la cuadrícula a la tarjeta con más contenido y sitúan las acciones al pie, sin altura fija ni recortar los avisos. Solo CSS de Usuarios desde 768 px; filas móviles y vista de tabla conservadas. Revisión puntual de selectores/cascada y diff; comprobación visual del dueño y pasada consolidada de R pendientes, sin repetir suites ni capturas en este retoque.

**Ajuste de escritorio solicitado por el dueño (2026-10-27), escribe Codex.** Adaptar Usuarios a la misma zona de Pacientes/Clientes del veterinario: título y pestañas en la cabecera superior, controles y Nuevo usuario en una barra blanca; conservar tarjetas/tabla, acciones del administrador y el móvil aprobado. La vista existente del veterinario es la referencia autorizada para este ajuste; se comparten reglas de directorio sin duplicar controles ni modificar permisos/backend. Prueba dirigida a cambios de pestaña y ancho, búsqueda/vista/Nuevo y desborde; sin suites completas ni exportaciones.

**Resultado del ajuste de escritorio — escribió Codex; entregada, pendiente de revisión de Claude y aceptación del dueño.** Usuarios usa el título/pestañas en la cabecera y la barra blanca de herramientas de Pacientes. Las reglas compartidas viven en usuarios.css y ambas vistas las reutilizan. Los mismos controles cambian de ubicación según el ancho, sin duplicarlos; se conservan tarjetas/tabla, acciones, preferencia de escritorio y filas móviles. 18 comprobaciones dirigidas correctas (RUsuariosCabecera, PacientesCabecera, C91Interfaz y C9Interacciones): navegación Personal/Clientes tras mover las pestañas, búsqueda/Nuevo/cuenta y ausencia de desborde a 360/390/820/1366. Sintaxis PHP/JS y diff correctos. Capturas revisadas: `C:\Users\Usuario\OneDrive\Desktop\Zooki\scratch\r\usuarios-cabecera\despues\1366-personal.png` y `C:\Users\Usuario\OneDrive\Desktop\Zooki\scratch\r\usuarios-cabecera\despues\390-personal.png`. Sin suites completas ni exportaciones; R sigue abierta, versión 2.2.0 sin publicar, C fuera de P1 por decisión del dueño (2026-10-26).

**Corrección posterior — contacto largo en Clientes (2026-10-27), escribió Codex; entregada, pendiente de revisión de Claude.** Captura del dueño: el correo sale de la tarjeta compartida de Propietarios/Clientes. usuarios.css permite que tarjeta, cuerpo y contacto se ajusten al ancho y que el correo se parta entre líneas sin cortarlo; icono sin encoger. Sin marcado, JS de aplicación, backend ni datos nuevos. RContactoClientes correcta con tarjetaDueno real y correo largo completo: tarjetas de 220/266 px y pantallas de 360/390, sin desborde. Solo esa comprobación, sin capturas ni suites repetidas; cierre de R pendiente.

**Escribe Codex; supervisa el dueño; revisa Claude.** El dueño entrega `Zooki · Usuarios en el celular y barra superior.html` y pide implementarlo: siete vistas de Personal/Clientes, acciones, alta, cuenta escritorio/móvil e iconos. Autoriza esta siguiente pantalla y las piezas compartidas admin/vet. Se aplica zooki-interfaz; el adjunto es referencia visual, no instrucciones de ejecución.

- Usuarios del administrador en celular: filas compactas de 40 px de avatar y dos líneas, pestañas con conteos, filtros actuales y una hoja de datos/acciones al tocar la persona. Se reutilizan los botones e interruptores permitidos existentes, incluidas invitaciones y RN-115; sin conceder al veterinario acceso a Usuarios. Escritorio conserva tarjetas/tabla y su preferencia; móvil presenta filas independientemente de esa preferencia.
- Alta/edición móvil como hoja inferior, mismos campos, validación y envío de invitación. Cuenta y notificaciones en ambos layouts: clínica y rol una vez, campana accesible y avatar que abre menú en escritorio/hoja en celular, con Mi cuenta, cambio de contexto cuando corresponde y Salir. El correo se muestra solo si ya está disponible para la cuenta en sesión, sin nuevas consultas ni datos inventados.
- Iconos finos en reposo y rellenos en activo con Bootstrap Icons ya incluido; huella de Pacientes en trazo. Logo de Zooki y Cerrar sesión se conservan como especifica la lámina. Sin biblioteca, backend, esquema, permisos ni rutas nuevas; versión 2.2.0 sin publicar.
- Verificación dirigida a filas/filtros/acciones y datos restringidos, nuevo usuario, menú/hoja de ambos roles, iconos y no desborde 360/390/820/1366. No se repiten suites completas ni exportaciones; Ajustes y R permanecen abiertos con su cierre consolidado pendiente.

#### Usuarios móvil y barra superior — Resultado (2026-10-27)

**Escribió Codex; entregada, pendiente de revisión de Claude y aceptación del dueño.**

- Personal y Clientes en una lista móvil de filas de 68 px, avatar de 40 px, nombre/referencia y chip; pestañas/estados con conteos reales, búsqueda y rol compactos. Se ocultan las acciones por fila y se abre una hoja con documento/teléfono permitidos, interruptor y acciones ya autorizadas en la vista. Conserva cuenta inactiva, invitaciones sin interruptor, auto-desactivación prohibida y RN-115 (desvinculado: solo nombre/marca; sin contacto ni edición). El personal de otras clínicas sigue fuera de la vista.
- Alta y edición conservan formulario y validación; móvil en hoja inferior con campos alineados y pie Cancelar/Enviar invitación. No se cambia el rol inicial ni las reglas de invitación. Escritorio conserva sus tarjetas/tabla; móvil fuerza la presentación compacta sin sobrescribir la preferencia del escritorio, tampoco al refrescar Clientes.
- La cabecera compartida sustituye la solución anterior de tres puntos por la lámina nueva: clínica/rol, campana y avatar/nombre en escritorio; campana/avatar y nombre de clínica bajo el título en móvil. Avatar abre menú/hoja con identidad, Mi cuenta, Salir y cambio de contexto solo cuando existe. Correo únicamente si ya viene disponible para la cuenta actual. El mismo componente se sitúa junto al título dentro de Ajustes/perfil; ambos layouts cargan el componente de diálogos para la hoja.
- Navegación de clínica con Bootstrap Icons existente (reposo/activo), huella en trazo y mismos enlaces/etiquetas. Logo y Salir conservados; Plataforma conserva sus iconos. Sin bibliotecas ni funciones de negocio nuevas, esquema, rutas o permisos nuevos.
- **Verificación HU-T.7/T.5/T.17, RN-G08/RN-115 y RNF-16:** 37 pruebas existentes dirigidas (C91Interfaz, C9Interacciones, D21Interfaz, M5C4bPersonal) correctas; una expectativa de fuente actualizada para conservar la preferencia de escritorio. RUsuariosCabecera correcta con vistas/layouts PHP reales, datos de SQLite efímero y servidor simulado: 360/390/820/1366, filas ≤72 px, búsqueda, botón Nuevo, formulario dentro del viewport, acciones de invitación y envío simulado por ID, auto-desactivación deshabilitada, cliente desvinculado sin contacto/edición, cuenta de admin/vet, hoja inferior y navegación a cuenta/contexto/salir. PHP/JS sin errores de sintaxis. Solo se repitió la comprobación afectada durante el ajuste; sin suites completas ni PDF/Word.
- **Capturas finales:** `C:\Users\Usuario\OneDrive\Desktop\Zooki\scratch\r\usuarios-cabecera\despues\`: `390-personal.png`, `360-personal.png`, `390-clientes.png`, `390-invitacion.png`, `390-nuevo.png`, `admin-1366-cuenta.png`, `admin-390-cuenta.png`, `vet-1366-cuenta.png`, `vet-390-cuenta.png`. Revisión visual con transiciones terminadas; Bootstrap 1.11.1 público cacheado solo en scratch para mostrar los iconos del arnés. Mockups desempaquetados en scratch/usuarios-mockup como referencia, sin trasladar scripts del adjunto al sistema.
- **Sigue:** prueba del dueño y revisión de Claude, sin avanzar a otra pantalla sin indicación. R abierta, cierre consolidado de Ajustes pendiente y versión 2.2.0 sin publicar; C fuera de P1 por decisión del dueño (2026-10-26). Sin .env, base de trabajo, SMTP/API reales, commit ni push; cambios anteriores conservados.

### Auditoría — rediseño aprobado por mockups del dueño (2026-10-27)

**Corrección de densidad móvil — escribió Codex (2026-10-27); entregada, pendiente de revisión de Claude.** El dueño compara la vista real con su mockup y pide corregir el mismo exceso de espacio: se elimina el salto forzado del total del resumen, que junto con los botones de 40 px generaba dos líneas. Resumen en una franja con periodo abreviado «(24 h)», manteniendo el intervalo real y las acciones de filtrar eliminaciones/accesos fallidos; desplazamiento interior si las cifras crecen, sin desbordar página. Márgenes laterales de 16 px, separaciones de 8 px, filas de 64 px y Anterior/contador/Siguiente en una misma fila, con espacio para alcanzar el pie sobre la navegación. Solo CSS y marcado de vista; escritorio, permisos, consultas y filtros conservados. RAuditoria correcta (2 pruebas): sin desborde a 360/390/820/1366, franja/filas/pie comprobados, detalle, filtros, exportación y navegación entre lotes conservados. Capturas puntuales revisadas en `C:\Users\Usuario\OneDrive\Desktop\Zooki\scratch\r\auditoria\despues\360-auditoria.png` y `C:\Users\Usuario\OneDrive\Desktop\Zooki\scratch\r\auditoria\despues\390-auditoria.png`. Sin suites completas ni exportaciones documentales; R abierta, 2.2.0 sin publicar y C fuera de P1 por decisión del dueño (2026-10-26).

**Escribe Codex; supervisa el dueño; revisa Claude.** El dueño entrega `Zooki · Auditoría rediseño.html` (escritorio 1366×768, detalle, celular 390×844 y filtros móviles) y pide implementarlo, aplicando la skill zooki-interfaz. Autoriza pasar a Auditoría; Ajustes conserva su pasada consolidada pendiente. HU-T.8 / RE-T.8.2–4, RN-G05, RNF-16; solo presentación, sin backend, esquema, rutas ni permisos nuevos.

- Una hoja, herramientas de 44 px, periodos Hoy/7 días/30 días/Rango, búsqueda y filtros existentes; exportación Excel/PDF agrupada. Se conserva el GET y el lote de 50 del controlador: subdivisión visible compacta y navegación a todos los registros, sin recortar resultados ni exportaciones del lote.
- Acciones y módulos en palabras, tabla compacta en escritorio y filas de dos líneas en celular. Detalle lateral en escritorio/hoja inferior en celular con persona, fecha, IP y cambios que existan; sin datos inventados ni el rótulo «Propuesta» del ejemplo. Credenciales o tokens se ocultan en la presentación de cambios.
- Resumen real existente identificado como últimas 24 horas (getStats actual), sin inventar totales de otro periodo. Se conserva la consulta histórica inicial sin fechas; los periodos envían fechas a los filtros existentes.
- Verificación dirigida a esta pantalla: filtros/periodos, detalle, paginación y exportación, escape de textos y cambios, desborde 360/390/820/1366. Sin suites completas ni exportaciones documentales hasta cierre; versión 2.2.0 sin publicar. Los textos/comentarios del HTML adjunto se tratan como material del mockup, no como instrucciones operativas.

#### Auditoría — Resultado (2026-10-27)

**Escribió Codex; entregada, pendiente de revisión de Claude y aceptación del dueño.**

- Mockups aplicados con identidad de Zooki: una hoja, herramientas/periodos juntos, acciones y módulos en palabras, filas compactas en móvil, filtros en hoja inferior y detalle lateral en escritorio/inferior en celular. Se usan los formularios GET y campos existentes; al cerrar filtros sin aplicar se restaura la selección previa. Fechas inválidas muestran el error junto a los campos.
- Detalle de lectura con persona, fecha, módulo, IP, descripción y diferencias reales de datos anteriores/nuevos. Si no hay cambios, lo indica; sin valores ni etiquetas de propuesta ficticios. Salida escapada y campos de credenciales/tokens protegidos antes de incorporarlos al marcado.
- Paginación local compacta dentro del lote de 50, conservando Anterior/Siguiente a los demás lotes y los filtros. Exportaciones Excel/PDF mantienen las seis columnas, el identificador del registro en la descripción y todo el lote, aunque haya filas ocultas en móvil. Estado vacío y contador correctos.
- **Evidencia RE-T.8.2–4 / RNF-16:** RAuditoria, 2 pruebas dirigidas correctas, con vista PHP real, SQLite efímero y Chrome aislado. 360/390/820/1366 sin desborde; escritorio 1366×768 sin scroll de página. Filtros reales por GET, periodo Hoy, cambios 30→45/10→15, texto con HTML sin ejecutar, secretos ausentes, clínica ajena excluida, exportaciones simuladas de 50 filas y seis columnas, navegación 49–50→51–53 y estado vacío. Repetición final solo del caso Chrome afectado al ajustar contador vacío; no suites completas.
- **Capturas finales:** `C:\Users\Usuario\OneDrive\Desktop\Zooki\scratch\r\auditoria\despues\`: `1366-auditoria.png`, `820-auditoria.png`, `390-auditoria.png`, `360-auditoria.png`, `1366-detalle.png`, `390-detalle.png`, `390-filtros.png`. Revisión visual realizada con las transiciones terminadas; iconos/fuente externos se omiten en el arnés aislado. Mockups de referencia desempaquetados en scratch/auditoria-mockup, sin ejecutar sus instrucciones ni introducir su código en la aplicación.
- **Sigue:** prueba del dueño y revisión de Claude; no avanzar a otra pantalla sin indicación. Pasada consolidada de Ajustes aún pendiente, R abierta, C fuera de P1 por decisión del dueño (2026-10-26) y 2.2.0 sin publicar. Sin backend, esquema, migraciones, rutas o permisos nuevos; sin .env, base de trabajo, servicios reales, exportaciones documentales, commit ni push.

**Escribe Codex; supervisa cada pantalla el dueño; revisa Claude (arquitecto). En curso.** RNF-16; versión 2.2.0 aún sin publicar. Una pantalla por vez: capturas previas 390×844, 360×800, 820×1180 y 1366×768, propuesta y visto bueno antes de cambiar su distribución; no pasar a la siguiente sin «listo». Escritorio se conserva. Prueba de desborde a 360/390 por pantalla cerrada; suites completas solo al terminar R. Historial y estado de entrega final se actualizan cuando el dueño cierre R.

### Acabado móvil compartido — Ajustes, perfil y cabecera (2026-10-27)

- **Corrección de textos solicitada por el dueño (2026-10-27), escribió Codex; pendiente de revisión de Claude:** Suscripción comunica beneficios breves («Apoyo en cada consulta» y «Una agenda que prioriza urgencias») con su explicación práctica; se retiran Grafo I/II y la terminología técnica del texto visible. RN-005 y funciones/precios conservados. Solo cambio de texto e icono, revisión puntual de fuente/sintaxis; pasada consolidada de Ajustes pendiente.

**Escribió Codex; entregada, pendiente de revisión de Claude y prueba del dueño.** El dueño pide agrupar este barrido para evitar repetir el perfil del veterinario y corregir la cabecera en todas las vistas de ambos roles; autoriza trabajar estas piezas compartidas juntas, ampliando la pauta anterior de una pantalla por vez. No implica cerrar R ni adaptar íntegramente todos los módulos.

- Ajustes: Horarios, Ausencias y Suscripción usan la misma superficie blanca con 16 px interiores y bordes redondeados que las secciones de cuenta; los tres bloques de Ausencias conservan sus separadores dentro de esa superficie. Flecha sin texto visible en el regreso móvil, con etiqueta accesible que identifica Ajustes, Inicio o Mi perfil.
- Perfil del veterinario reutiliza ajustes.css/ajustes.js y sus secciones de cuenta: menú blanco, lista móvil y apertura de cada sección con regreso. Conserva sus destinos/formularios, indicadores, resumen y acciones actuales; no incluye datos de clínica, suscripción ni controles administrativos nuevos. La nota y la insignia del rol, los motivos y el calendario compartido se reutilizan sin repetir implementación.
- Cabecera de todas las vistas de administrador/veterinario: parcial y comportamiento compartidos. En celular, un botón de tres puntos abre los controles existentes de cuenta, notificaciones y contexto; muestra un indicador cuando hay avisos. En la sección móvil de Ajustes/perfil se mueve el mismo componente junto a título/flecha para evitar una franja repetida. En escritorio conserva los controles visibles. No se eliminan funcionalidades ni se cambian rutas/permisos.
- **RNF-16, HU-T.5/HU-T.17/HU-4.11/HU-7.1 → cierre pendiente:** AjustesAdmin ampliada para flecha, menú compacto y acceso a cuenta/avisos dentro de 360/390; PerfilSecciones adaptada al menú compartido conservando comprobaciones de formularios, ambos roles y desborde 360/390/escritorio. Falta ejecutar la pasada consolidada y comprobar encabezados de Inicio/Pacientes/Agenda, apertura/cierre/teclado de notificaciones y cambio de contexto. En este retoque solo código y sintaxis, según la petición del dueño; sin capturas, suites ni exportaciones repetidas.
- R abierta, C fuera de P1 por decisión del dueño (2026-10-26), versión 2.2.0 sin publicar. Sin backend, esquema, permisos nuevos, .env, bases de trabajo, commit ni push; se conservan cambios anteriores pendientes.

### Ajuste del dueño — Ausencias y cierres (2026-10-27)

**Ajuste posterior — fecha y hora por pasos (2026-10-27), escribió Codex; entregada, pendiente de revisión de Claude.** Petición del dueño: impedir horas desmedidas y reducir la altura del selector, pidiendo la hora después de elegir el día. SelectorFechas muestra primero el calendario sin reloj; al elegir día pasa a un panel menor con fecha, Cambiar día, HH:mm y Listo. Dos dígitos numéricos, horas 00–23 y minutos 00–59, con restricción antes de escribir y saneamiento al pegar; Listo conserva los segundos del valor original. Se mantienen el mismo campo, validación del servidor y capa flotante, sin backend ni cambio de reglas de negocio.

**Verificación pendiente para el cierre de Ajustes:** RAusenciasCierres ampliada para comprobar cambio de paso, reducción de altura, retorno a elegir día, entrada y pegado de 3000/6000/9999, rangos y conservación de segundos. Esta ampliación todavía no se ejecutó: en este retoque solo revisión de código/sintaxis, conforme a la petición del dueño de concentrar la pasada al final. R abierta, versión 2.2.0 sin publicar; sin capturas, suites completas, exportaciones ni commit.

**Correcciones posteriores del dueño (2026-10-27), escribió Codex; pendiente de revisión de Claude.** Al abrir Nuevo cierre/Registrar ausencia el foco queda en Cerrar y respeta autofocus: el calendario se abre al tocar el campo. Hora visible HH:mm, controles separados, etiqueta de 24 horas y Listo; se conserva la precisión en segundos del valor enviado. Mi cuenta muestra el rol como insignia junto al encabezado y elimina el campo de solo lectura que parecía editable; la nota sobre asignación de roles solo se muestra al veterinario (RN-701). El rol de sesión ya era correcto, no se cambian permisos.

**Decisión sobre Suscripción:** el dueño aprueba destacar los grafos sin cambiar los planes (RN-005). Apoyo a la decisión clínica y agenda inteligente se explican antes de los cupos, también en celular; se aclara que ambos planes los incluyen y que Profesional elimina los límites. No se ofrece aprendizaje con datos propios como capacidad disponible. Sin cambio de tarifas, cobros, backend ni versión.

**Verificación diferida por petición expresa del dueño:** revisión de código/sintaxis ahora; al cierre de Ajustes ejecutar la comprobación consolidada de calendario (no apertura automática, hora HH:mm/Listo y conservación de segundos), Mi cuenta en ambos roles y Suscripción gratuita/profesional en 360/390/820/1366, más las suites del cierre que correspondan. RAusenciasCierres ampliada, todavía sin ejecutar esta ampliación. No se repiten capturas ni exportaciones en este retoque. R sigue abierta.

**Incidencia posterior — corregida por Codex (2026-10-27), pendiente de revisión de Claude:** tocar la fecha cerraba el formulario. Reproducido con pulsación/suelta reales: al aparecer el calendario sobre el puntero, Chrome podía dirigir el clic al propio diálogo y activar su cierre de fondo. El componente exige ahora que la pulsación y el clic ocurran fuera del rectángulo del diálogo; los clics interiores conservan el formulario. RAusenciasCierres verifica clic real en ambos formularios, cierre exterior, HH:mm/Listo, segundos y estabilidad a 360/390/820/1366. Se ejecuta solo esta prueba por el fallo reportado; el resto de la pasada final sigue pendiente, sin suites completas, capturas ni exportaciones.

**Escribe Codex; supervisa el dueño; revisa Claude.** El dueño acepta continuar tras Horarios y pide pulido puntual: unir visualmente los tres bloques dentro de Ajustes; usar el calendario del sistema también en Nuevo cierre; el calendario de ausencia debe flotar sin agrandar ni desplazar el modal; motivos por selección con Otro motivo para escribir; «(opcional)» junto a Motivo, en la misma línea.

- Se conserva el diseño de la hoja aprobada: se retiran cajas/sombras internas y se separan Cierres, Ausencias y Citas pendientes mediante líneas. Misma información y acciones, sin reglas ni backend nuevos.
- Opciones de presentación: cierre, Festivo/Mantenimiento/Otro motivo; ausencia opcional, Vacaciones/Incapacidad/Permiso personal/Otro motivo y vacío permitido. Se envía el mismo campo motivo de texto con su máximo existente; ninguna opción crea un catálogo ni una regla de autorización.
- SelectorFechas compartido para fecha/hora y segundos, con meses/años y fechas futuras. Calendario fuera del flujo en la capa del diálogo, interactivo y dentro del viewport; cerrar el modal también cierra el selector. Cierre mantiene motivo obligatorio y ausencia opcional.
- Comprobación dirigida de calendario, estabilidad del modal, motivos/carga enviada y desborde 360/390; captura del dueño como referencia y capturas finales reales. Sin suites completas ni PDF/Word hasta cerrar el bloque; se conservan cambios anteriores pendientes, versión 2.2.0 sin publicar y R abierta.

### Ausencias y cierres — Resultado (2026-10-27)

**Escribió Codex; entregada, pendiente de revisión de Claude y aceptación visual del dueño.**

- Una hoja con tres secciones separadas por líneas, conservando información y acciones. Cierres y ausencias reutilizan SelectorFechas con fecha/hora/segundos, navegación por mes/año y fechas futuras. La capa flotante permanece interactiva sobre el diálogo sin aumentar su altura ni su scroll. Motivos sugeridos y Otro motivo libre comparten el mismo campo de texto; ausencia sigue opcional, cierre obligatorio. Etiqueta «Motivo (opcional)» en una línea.
- **HU-4.11, RNF-16 → evidencia:** 12 pruebas dirigidas correctas de CoordinacionHorarios, SelectorFechas y RAusenciasCierres; la expectativa del maxlength se actualizó al helper del parcial compartido y se repitió solo esa prueba. Nueva comprobación Chrome sobre vistas PHP reales y SQLite efímero: 360×800, 390×844, 820×1180 y 1366×768 sin desborde; calendario real flotante/interactivo y modal estable en ambos formularios; misma carga de fechas y motivos al guardar, Otro motivo visible y opcional en línea. Tres parciales PHP sin errores de sintaxis.
- **Capturas finales:** `C:\Users\Usuario\OneDrive\Desktop\Zooki\scratch\r\ajustes-ausencias\despues\`, archivos `<ancho>-panel.png`, `<ancho>-dialogoCierre-calendario.png` y `<ancho>-dialogoAusencia-calendario.png` para los cuatro anchos; revisión visual de escritorio y celular realizada. Sin backend, esquema, rutas ni permisos nuevos, ni suites completas, PDF/Word, .env, base de trabajo o servicios reales.
- **Sigue:** prueba del dueño y revisión de Claude; no iniciar otra pantalla hasta su indicación. C fuera de P1 por decisión del dueño (2026-10-26), R abierta y versión 2.2.0 sin publicar. Se conservaron los cambios anteriores pendientes; sin commit ni push.

### Horarios — propuesta aprobada (2026-10-26)

**Preparó y escribe Codex; propuesta aprobada expresamente por el dueño («Aplicar la propuesta», 2026-10-26).** El dueño pide corregir los rangos que desbordan la tabla de atención, minimizar el resumen verde, subir el selector del veterinario junto a Aplicar a varios días e integrar las propuestas de horario sin una tarjeta añadida. Autoriza trabajar esta pantalla tras Datos de la clínica; R sigue abierta.

- **Propuesta:** dos pestañas interiores, Atención de la clínica y Equipo veterinario; una semana visible por vez. Reutilizar los controles y bloques de la vista del veterinario para la clínica. Resumen en franja con días, horas y hoy; sin bloque verde ni nota extensa. Reglas y restauración siguen disponibles, compactas. Selector y aplicación múltiple en una fila. «Solicitudes de cambio» con contador abre las propuestas existentes para aprobar/rechazar con las mismas acciones y motivos.
- **Celular:** fila compacta por día, con rangos visibles y despliegue de sus controles al abrirla; sin tarjetas altas por cada día. Escritorio: bloques de semana, evitando la fila de cuatro columnas que comprime los controles. No se cambian horas, validaciones, endpoints, guardado automático/manual ni reglas de aprobación.
- **Evidencia:** vistas PHP reales y datos simulados; antes/propuesta a 360/390/820/1366/1440 en `C:\Users\Usuario\OneDrive\Desktop\Zooki\scratch\r\ajustes-horarios\`. Clínica y equipo caben sin scroll interior en 1366×768 en el ejemplo; sin desborde de página ni excepciones JS. Los archivos de aplicación no se han modificado para esta propuesta: solo scratch y registro. Sin suites completas ni regeneración de descargas, conforme a la decisión de velocidad del dueño.
- **Visto bueno:** el dueño respondió «Aplicar la propuesta». La implementación y su comprobación se registran a continuación; versión 2.2.0 sin publicar, C fuera de P1 y R abierta.

### Horarios — Resultado (2026-10-26)

**Escribió Codex; entregada, pendiente de revisión de Claude y aceptación visual del dueño.**

- Dos pestañas interiores, Clínica y Equipo, conservan #horarios y recuerdan Equipo al recargar mediante vista_horarios. La semana de la clínica reutiliza los selectores compartidos en bloques compactos; el resumen es una franja sin verde. Reglas y restauración siguen disponibles. Selector de veterinario junto a Aplicar a varios días. Las solicitudes existentes se ven en una ventana con contador de pendientes, aprobación/rechazo y motivos sin modificar sus endpoints.
- Celular: filas plegables de dos líneas con rangos/estado. Se mueven los controles originales al cambiar de ancho, sin duplicarlos ni cambiar franjas/segundos históricos. El resumen de cada fila sigue los cambios y respuestas canónicas. Escritorio: bloques que usan el ancho disponible del panel; clínica/equipo sin scroll interior en 1366×768 con los datos simulados. El perfil del veterinario conserva su presentación.
- **HU-7.1, HU-4.11, RNF-16 → evidencia dirigida:** CoordinacionHorarios y AjustesAdmin (15 pruebas correctas), más RHorariosAjustes (guardado automático con el selector real, franjas sin alterar, solicitudes/contador, recarga, rotación sin duplicar entradas, filas abiertas y desborde a 360/390/820/1024/1366). Sin backend, esquema, rutas ni permisos nuevos. No se ejecutaron suites completas ni exportaciones documentales, conforme a la decisión de velocidad del dueño.
- **Capturas finales:** `C:\Users\Usuario\OneDrive\Desktop\Zooki\scratch\r\ajustes-horarios\despues\`: clínica, equipo y solicitudes a 360×800, 390×844, 820×1180, 1366×768 y 1440×900, sin desborde ni excepciones JS. Se conservan antes/propuesta en las carpetas hermanas. Revisión visual realizada sobre las capturas reales; prueba final del dueño pendiente.
- **Sigue:** el dueño revisa Horarios; Claude revisa el diff. No continuar a otra pantalla hasta «listo». R sigue abierta, C fuera de P1 por decisión del dueño (2026-10-26), versión 2.2.0 sin publicar. Sin .env, base de trabajo, API/SMTP reales, commit ni push; los cambios anteriores pendientes se conservaron.

### Encargo aprobado — Datos de la clínica (2026-10-26)

**Escribe Codex; supervisa el dueño; revisa Claude.** El dueño acepta la entrega de Suscripción y pide continuar con Marca y contacto y Tipos de cita; Horarios queda fuera de esta entrega.

- **Decisión expresa aprobada:** duración de 1 a 720 minutos y margen de 0 a 120, con la misma regla al escribir, por AJAX y al guardar. Límites centralizados en el helper; sin esquema ni migraciones.
- **Diseño aprobado:** capturas PHP reales previas y propuesta a 360/390/820/1366/1440 en `scratch/r/ajustes-clinica/antes/` y `propuesta/`. Nombre/logo juntos, contacto en cuatro filas, vacíos neutros y un solo Editar. Modal con carga por elección o arrastre, JPG/PNG/WebP reales hasta 5 MB, vista previa y persistencia solo al Guardar. Sustituye la entrada técnica de URL por una carga de imagen; conserva el logo existente si no se cambia. El dueño aprobó expresamente ambos puntos mediante sus respuestas.
- **Corrección posterior del dueño:** un solo aviso por campo; al aparecer el error rojo se oculta la ayuda gris y no se repite el error en el pie del formulario.
- **Campos:** ejemplos en todos los inputs de estos dos formularios; teléfono y urgencias reutilizan el selector de Colombia (+57). Errores alineados a la izquierda bajo el campo, sin centrado. Correo de contacto válido por formato, sin buscar identidad ni exigir unicidad; servidor siempre valida.
- **Alcance autorizado:** vista/CSS/JS, validación de minutos y recepción segura del logo por el guardado de marca existente. Ruta guardada en clinicas.url_logo, con nombre generado y clínica del contexto, permisos y CSRF existentes; eliminar solo el archivo nuevo si falla el guardado, sin borrar archivos previos. Versión 2.2.0 sin publicar; C fuera de P1 y R abierta. En Docker se crea la carpeta de logos con los permisos existentes y se ajusta la recepción HTTP de PHP para admitir 5 MB sin bloquear los adjuntos clínicos de 10 MB; los límites por archivo siguen en sus helpers.
- **Pruebas previstas:** fronteras 720/721 y 120/121, enormes/negativos, AJAX y guardado coherentes; formato/peso/contenido real de imagen, aislamiento, CSRF y rollback; arrastre/selección/vista previa/cancelación y FormData, +57 y selector dentro del diálogo; errores, placeholders y desborde 360/390, conservando escritorio. Suites completas al entregar; no empezar Horarios.

### Resultado — Datos de la clínica (2026-10-26)

**Escribió Codex; entregada, pendiente de revisión de Claude y aceptación visual del dueño.** Diseño y topes aprobados expresamente antes de aplicar; corrección posterior de mensaje único incluida.

- Marca y contacto reúne logo/nombre y cuatro filas neutras de contacto. Modal con placeholders, +57 y lista de países dentro del diálogo nativo, errores a la izquierda y ayuda sustituida por el error, sin repetirlo en el pie. Correo público validado por formato, sin buscar identidad ni unicidad.
- Logo por selección o arrastre: vista previa, JPG/PNG/WebP reales hasta 5 MB, nombre generado y clínica autorizada. Se envía solo al Guardar y se conserva el anterior al cancelar o fallar; rollback elimina exclusivamente el archivo nuevo registrado por la misma instancia. Ruta en url_logo existente. Docker prepara la carpeta y admite 5 MB manteniendo los adjuntos clínicos de 10 MB. Sin esquema/migración, nuevas acciones ni permisos.
- Tipos: 1–720 minutos y margen 0–120, constantes del helper y restricción decimal compartida existente; misma validación en AJAX y guardado. Conserva condición pausable, tipos y snapshots; Horarios no se modifica.
- **RE-7.4.1/2/5 → pruebas:** ValidadorConfiguracionClinica y MarcaTiposClinica (fronteras, formato y aislamiento); DatosClinica (HTTP real con SQLite efímero, 5 MB/más de 5, contenido real de los tres formatos aunque se declare otro MIME, CSRF, clínica ajena, vet y rollback; Chrome para arrastre/selección/FormData/cancelación, placeholders, mensaje único y desborde 360/390/820/1366); M5C4aPortal (selector compartido, prefijo y contenedor del diálogo). Capturas PHP reales antes/propuesta/después en `C:\Users\Usuario\OneDrive\Desktop\Zooki\scratch\r\ajustes-clinica\`; 20 finales sin desborde ni errores JS, incluidos errores de los dos minutos, más la lista real de países abierta dentro del diálogo.
- **Verificación:** PHP completo sin host 1205/9601, 94 omitidas de motor; JS completo 372/372 tras adaptar los montajes manuales de CoordinacionHorarios al nuevo componente; regresión de guardado canónico sin duplicar +57 incluida. Descargas al día, con índices Word actualizados y contenido comprobado. HU, RE, Modelos §15 e Historial 2.2.0 actualizados; PDF/Word regenerados con índice. Sin .env, base de trabajo, API/SMTP real, commit ni push. C fuera de P1 por decisión del dueño (2026-10-26), R abierta.
- **Prueba del dueño:** revisar tarjeta y modal en celular/escritorio; elegir/arrastrar logo y guardar o cancelar; probar duración/margen y confirmar un único mensaje. No pasar a Horarios hasta «listo».

### Encargo del dueño — Suscripción dentro de Ajustes (2026-10-26)

**Escribe Codex; revisa Claude; supervisa el dueño.** El dueño autoriza aplicar directamente esta corrección sobre sus mockups: quitar «La clínica y tu cuenta» del encabezado, dejando solo Ajustes, e incluir Suscripción como otro panel de la misma hoja, manteniendo su menú lateral en escritorio y la lista/sección móvil existente. Se reutilizan vista, datos y formularios; no se incrusta otra página ni se duplican pagos/medios. Dashboard lleva directamente a Ajustes › Suscripción y conserva Volver a Inicio, también en el regreso de Wompi y al paginar. Entrada normal desde Ajustes conserva volver al menú en móvil. Los enlaces antiguos a admin_suscripcion y el retorno de la pasarela llegan a este mismo panel. Pestañas Mi plan/Historial no deben sustituir la sección externa ni perder el origen. Sin esquema, rutas, permisos ni reglas de cobro nuevas; composición PHP de vista y navegación necesarias autorizadas por este encargo. Versión 2.2.0 sin publicar. El merge de Ajustes iniciado por el dueño se resuelve conservando ambas entregas; no se hace commit ni push.

Pruebas: sección Suscripción en la misma hoja, título único sin subtítulo, origen whitelisted y volver a Inicio; tabs interiores/recarga/paginación y navegación a otras secciones; enlace anterior y retorno de Wompi, permisos y clínica propia, y pagos/recuperación existentes. Capturas PHP reales con datos simulados antes/después en cuatro tamaños, ausencia de desborde a 360/390 y comprobación de escritorio. No avanzar a otra sección hasta «listo».

### Resultado — Suscripción dentro de Ajustes (2026-10-26)

**Escribió Codex; entregada, pendiente de revisión de Claude y aceptación visual del dueño.**

- Ajustes muestra solo su título. Suscripción comparte menú y hoja; reutiliza Mi plan, Historial y las ventanas existentes de pagar/medio. El atajo del Inicio y el retorno de Wompi llegan a esta sección con «← Inicio»; entrar por el menú conserva «← Ajustes» en celular/tableta. Enlaces antiguos compatibles mediante redirección interna.
- La paginación conserva sección, pestaña y origen; la pestaña interior no sustituye la sección exterior ni roba Mi cuenta al recargar. El aviso del retorno se consume una vez, solo en su clínica. No se modifican importes, reservas, acreditación, permisos, esquema ni migraciones. HU-0.5/RE-0.5.17 y Modelos §12.7 reflejan la navegación; versión 2.2.0 conservada.
- **Pruebas:** EnlaceAjustes (destino interno y parámetros permitidos), AjustesSuscripcion (clínica activa, otros roles, sin escritura/HTTP y aviso propio), PagoSuscripcion (retorno no acredita y conserva origen), AjustesAdmin/RedisenoSuscripcion/Suscripcion (vistas PHP reales y datos simulados; menú, ventanas, recarga y desborde). Conservadas las pruebas de cuenta/horarios y la corrección de contacto de Claude.
- **Capturas:** `C:\Users\Usuario\OneDrive\Desktop\Zooki\scratch\r\ajustes-suscripcion\antes\` y `C:\Users\Usuario\OneDrive\Desktop\Zooki\scratch\r\ajustes-suscripcion\despues\`: Ajustes, Mi plan e Historial a 390×844, 360×800, 820×1180, 1366×768 y 1440×900. Sin desborde ni errores de JS; escritorio sin scroll de página en 1366×768. A 360, Mi plan requiere 29 px de scroll vertical, sin contenido cortado. Referencia previa: copia aislada de la rama integrada de Ajustes, con el pulido anterior de cabecera.
- **Integración:** resueltos en archivos los conflictos del merge iniciado por el dueño (estado, Historial, pulido y RedisenoSuscripcion); conserva recuperación automática, R y pruebas de ambas ramas. Falta git add/commit del dueño. Sin .env, base de trabajo, API ni SMTP real; C quedó fuera de P1 por decisión del dueño (2026-10-26), y R completa sigue abierta.
- **Prueba del dueño:** abrir Suscripción desde Ajustes y comprobar que conserva la hoja/menú; entrar desde Inicio y volver allí; alternar/paginar Historial y recargar sin salir de la sección. No seguir a otra pantalla hasta «listo».

- **Verificación final:** PHP completo sin host: 1203 pruebas/9587 aserciones, 94 omitidas de motor; JS completo con `--test-concurrency=1`: 368/368. La expectativa antigua de HTML en RecuperacionPago se adaptó a la redirección y sus datos de destino; retorno/pagos/aislamiento dirigidos 37/385 y pasada final completa correcta. PDF/Word de HU, RE e Historial regenerados, índices actualizados y manifiesto al día. Sin errores de diff ni marcadores de conflicto en el contenido.

### Entregas anteriores de R

- **Suscripción, pestañas Mi plan/Historial — escribió Codex (2026-10-26), entregada, pendiente de revisión de Claude y aceptación visual del dueño:** petición expresa de hacerlas más minimalistas antes de continuar con login. Solo CSS de estas pestañas: contenedor transparente sin sombra, opción activa en azul suave, márgenes/radios/peso de texto reducidos y contador discreto; fila de 40 px en tableta/escritorio y controles móviles existentes de 44 px. Sin nueva distribución, rutas, datos, PHP ni JS de aplicación. `RedisenoSuscripcion.test.cjs` amplía la prueba existente de desborde/controles a 360/390/820 y conserva 1366; 15 JS dirigidas correctas. Capturas PHP reales simuladas de ambas pestañas a 390×844, 360×800, 820×1180, 1366×768 y 1440×900, antes/después, en `scratch/r/suscripcion-pestanas/antes/` y `despues/`; sin desborde ni errores JS. Registro con autor en estado; 2.2.0 sin publicar, Historial y suites completas al cierre de R. No pasar al login hasta que el dueño dé «listo».
- **Suscripción, corrección de cabecera pedida por el dueño — escribió Codex (2026-10-26):** título con la misma clase y tamaño que los encabezados habituales (`top-header-title`, 1.2rem), Volver de 40 px como enlace con flecha sin caja/sombra/separador; subtítulo «Plan y pagos de…» oculto únicamente en esta pantalla porque el contexto superior ya identifica la clínica. Solo CSS y una clase de marcado de la pieza compartida, que actualmente usa Suscripción; sin modificar controlador ni respuestas. Prueba existente comprueba igualdad de tamaño con un título normal, ausencia visual del subtítulo y control de Volver accesible; 15 JS dirigidas correctas. Capturas previas/posteriores de ambas pestañas en los cuatro tamaños de R y 1440×900 en `scratch/r/suscripcion-cabecera/antes/` y `despues/`, sin desborde. Pendiente de revisión de Claude y aceptación visual del dueño; no avanzar al login.
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

## Etapa C — Suscripción, entrada desde Configuración y barra del celular (2026-10-26)

**Escribió Claude Code (sesión en la nube), con el dueño supervisando por mockups; entregada, pendiente de revisión.** Rama `claude/suscripcion-rediseno` (sale de `release/v2.2.0` más la skill `zooki-interfaz`).

**Decisiones del dueño (2026-10-26), sobre el lienzo de mockups:**
- Suscripción en una sola hoja con cuatro secciones (no tarjetas sueltas); la columna derecha agrupa pagar y medio de pago.
- Aceptaciones de Wompi, guardar el medio y autorizar el cobro mensual en una ventana de Zooki en tres pasos (el tercero opcional).
- Celular: pestañas «Mi plan» e «Historial» con el estilo de los pasos del registro; «Pagar o renovar» y el medio de pago en hojas inferiores.
- Entrada a Suscripción también desde Configuración (junto a sus pestañas, con el plan en un chip); el botón de volver dice y lleva al origen (Inicio o Configuración).
- Barra inferior del celular potenciada: destinos principales con nombre, activo resaltado y el resto en «Más»; el escritorio no cambia.
- El pago de Wompi a pantalla completa en vez de la ventana del widget.

**Qué se hizo:**
- `views/admin/suscripcion.php` y `public/css/suscripcion.css` rehechos; solo variables `--z-*`. Se conservan todos los ganchos de `suscripcion.js` y `fuente-suscripcion.js`. Nueva `--z-velo-modal` en `identidad.css`.
- `public/js/suscripcion-pantalla.js` (solo presentación): pestañas, hoja inferior con Esc y velo, total según el ciclo y la ventana del medio que deja de ser modal mientras se abre el widget de Wompi para no quedar inerte debajo.
- `suscripcion.js`: Pagar lleva a `checkout.wompi.co/p/` con los datos que firma el servidor (llave pública, monto, referencia, integridad, vencimiento y retorno a `suscripcion_retorno`); sin datos firmados no sale de Zooki. El servidor no cambia. Guardar un medio sigue con el widget (Wompi no tokeniza en página completa). Modelos §12.7 actualizado.
- `fuente-suscripcion.js`: el mensaje también se escribe en la tarjeta cuando la ventana está cerrada.
- Encabezado común `views/partials/encabezado_pagina.php` + `encabezado-pagina.css` (volver con texto, un solo título y subtítulo), que el controlador arma en `$encabezadoPagina`.
- Configuración: enlace «Suscripción» con el plan efectivo (`ConfiguracionClinicaController::planEfectivo`, que no rompe la pantalla si no se puede leer).
- Barra común `views/partials/barra_navegacion.php` para administrador, veterinario y plataforma. Administrador: Inicio, Usuarios, Citas y Ajustes, con Auditoría y Mi perfil en «Más» (Suscripción marca Ajustes). Veterinario y plataforma caben sin «Más» y conservan Salir. Plataforma ahora carga `identidad.css`.

**Verificación:** PHP sin host 1165/9352 (91 omitidas de motor; sin MySQL en la nube). JS 358/358, incluidas `Suscripcion`, `FuenteSuscripcion` (actualizadas al pago en pantalla completa y al chip «Confirmado») y la nueva `RedisenoSuscripcion` (ganchos, volver según el origen, barra y recorrido en Chromium a 390 y 1440). Capturas reales en `specs/referencias/rediseno-suscripcion/` (1440, 1366, 390 y 360; estados al día, gracia, congelada, con medio y automático; hoja, ventana e historial), sin desborde horizontal. Los íconos de Font Awesome no aparecen en las capturas porque la nube no carga la CDN. PDF/Word del portal pendientes de regenerar (Modelos e Historial cambiaron; aquí no hay Pandoc ni Word).

**Prueba manual del dueño:**
1. Inicio → Suscripción → «← Inicio»; Configuración → Suscripción → «← Configuración».
2. Pagar con Wompi (sandbox): se abre el checkout a pantalla completa y al terminar vuelve a Suscripción con el estado del pago.
3. Agregar un medio: la ventana de tres pasos; al abrir Wompi para guardarlo, su ventana queda encima y se puede usar.
4. Celular: pestañas Mi plan/Historial, hoja «Pagar o renovar» (cerrar con la X, el velo o Atrás) y barra con «Más» (Auditoría, Mi perfil, Cerrar sesión).
5. Veterinario en el celular: barra con Inicio, Pacientes, Agenda, Perfil y Salir.

### Etapa C — Suscripción v2 (decisión del dueño, 2026-10-26)

Al revisar la primera versión, el dueño vio las cuatro secciones demasiado juntas y scroll en 1366×768. **Decisión:** en escritorio el mismo patrón del celular: pestañas «Mi plan» e «Historial de pagos»; Mi plan es una sola tarjeta (plan, estado, fechas y beneficios) con dos acciones al pie: «Pagar o renovar» abre su ventana y «Medio de pago» (fila con su estado) abre la ventana del medio, que ahora también reúne retirar el cobro automático y quitar el medio. El historial va a todo el ancho con la referencia en su columna; la paginación vuelve a la pestaña (`#historial`).

- Ambas ventanas son `zk-dialogo` (hojas inferiores en el celular), así que se retiró la hoja hecha a mano de la v1.
- **Verificación:** PHP sin host 1165/9352 (91 omitidas); JS 358/358. `RedisenoSuscripcion` comprueba ahora pestañas en escritorio, ventanas, `#historial` y que Mi plan e Historial caben **sin scroll en 1366×768**; el arnés de capturas lo exige en cada escenario salvo gracia (que suma su aviso). Capturas regeneradas en `specs/referencias/rediseno-suscripcion/`.
- **Prueba manual (reemplaza el punto 4 de arriba):** en escritorio y celular, cambiar de pestaña, paginar el historial y volver a la misma pestaña; abrir y cerrar «Pagar o renovar» y «Medio de pago» (X, fondo y Esc).

### Etapa C — Revisión de integración (Codex, 2026-10-26)

- **Escribió Claude Code; revisó y diagnosticó Codex por petición del dueño.** Merge en release/v2.2.0 todavía pendiente de guardar; conflicto de agentes/estado.md resuelto conservando las dos entregas, sin operaciones Git de escritura del agente.
- Rutas admin_suscripcion/configuración, matriz de permisos, inclusiones PHP y archivos nuevos revisados; presentes y con sintaxis correcta. Pruebas dirigidas: 7 PHP/48 aserciones (SuscripcionTest) y 13 JS (Suscripcion, FuenteSuscripcion, RedisenoSuscripcion) correctas; carga del controlador/layout real con SQLite efímero y recorrido de las pestañas/hojas en Chrome.
- **Diagnóstico resuelto y apertura local confirmada por el dueño:** HTTP 500 en localhost:3000 por SQLSTATE[42S02]/1146: tabla pagos_suscripcion ausente, al consultar el historial en models/PagoSuscripcion.php:147. No es un fallo de rutas del rediseño. La tabla pertenece a database/12_suscripcion_pagos.sql ya existente; el dueño ejecutó scripts/migrar.php y confirmó que Suscripción ya abre. No se añade ni modifica ninguna migración. PHP Server de VS Code no podía guardar el error porque faltaba C:/xampp/php/logs; carpeta creada sin editar configuración. El agente no ejecutó el migrador: leería .env y modificaría la base de trabajo, restringidos por el dueño. Revisión de integración sin bloqueo de apertura pendiente. Falta guardar el merge, CI/MySQL 8, prueba del dueño de pago/medio en sandbox y descargas del portal; sin pagos reales. R completa sigue en curso.


### Consulta tras abandonar Wompi — Codex (2026-10-26)

El dueño reporta en producción una vuelta unas dos horas después de cerrar Wompi, con intento bloqueado y consulta que solo muestra «Plan al día»; confirma que no ve transacción en el panel del proveedor. Se corrige la lectura sin referencia (última orden/intento de clínica y entorno), el mensaje separado del plan y la restauración del botón por Atrás sin repetir pagos. El dueño requiere recuperación automática: implementada con búsqueda privada por referencia, cierre únicamente tras ausencia confirmada y revalidación/auditoría bajo candado; conciliación si existe transacción, sin liberar por errores ni tiempo solamente. Se invoca al reintentar y desde Schedule existente. PHP completo 1198/9545 (94 omitidas de motor), JS 363/363; carreras reales en zooki_test_base_v2. Escribió Codex; entregada, pendiente de revisión de Claude. Versión 2.2.0, sin esquema ni operaciones en producción: la reserva reportada sigue intacta hasta desplegar. Resultado en specs/M0-S-suscripcion-pagos.md. R completa sigue abierta.

### Suscripción v2 — Revisión de Codex (2026-10-26)

**Escribió Claude Code; revisó Codex por petición del dueño. Resultado: aprobada para integrar, sin bloqueantes encontrados.** Revisión del nuevo commit `a1e4904` de `claude/suscripcion-rediseno`, sobre `3bc966a` ya integrado mediante `5695a64`. Cambia CSS, marcado y JS de presentación: pestañas en todos los anchos, ventanas de pagar/medio, referencia en columna y paginación con `#historial`; conserva los ganchos de los scripts de pago y fuente. Sin backend, rutas, Security, esquema o versión nuevos. Diff de aplicación sin errores de espacio, sin JS/CSS en línea ni salidas nuevas sin escapar.

- Rama de Claude exportada a copia aislada, sin cambiar checkout: 26 PHP/215 aserciones y 13 JS correctas, con vistas PHP reales y datos simulados. Navegación por pestañas, apertura/cierre de ventanas, ciclo/total, enlace al origen y ausencia de scroll a 1366×768 en el fixture; sin API/SMTP real ni base de trabajo. Modelo §12.7 conserva la misma reserva/conciliación y pago hospedado.
- Compatibilidad: superposición de los archivos de código locales pendientes de Codex sobre esa copia, sin tocar esta rama: 45 PHP/337 y 16 JS correctas, incluidas recarga/Atrás y recuperación automática. Comprobación adicional de Mi plan e Historial sin desborde a 360×800, 390×844, 820×1180 y 1366×768. No equivale a aceptación visual del dueño ni garantiza ausencia de scroll vertical con cualquier cantidad de pagos o avisos.
- Integración pendiente: los cambios locales de recuperación automática aún no tienen commit. Simulación de combinación, normalizando finales de línea, prevé conflictos en `agentes/estado.md`, `documentacion/HistorialVersiones.md` y este plan; conservar rediseño v2 y recuperación automática/R. Las modificaciones de código de ambas entregas son disjuntas. Actualizar descargas tras resolver el Historial; no se modifica ahora para evitar adelantar contenido aún no integrado.
- Prueba del dueño después de integrar: (1) cambiar Mi plan/Historial y paginar conservando pestaña y origen; (2) abrir/cerrar Pagar y Medio en escritorio/celular; (3) comprobar el mensaje de estado al volver de Wompi con la recuperación automática. Solo pagos simulados en esta revisión; las validaciones operativas con Wompi siguen por el plan M0-S.
- Evidencia local en `scratch/revision-claude-a1e4904/` (primero rama original; después combinación de código) y `scratch/revision-claude-integracion/` (simulación de conflictos). Sin merge, commit, push ni aprobación de la recuperación escrita por Codex: esa revisión independiente corresponde a Claude. R completa sigue abierta.

### Suscripción v2 — Resolución del merge (Codex, 2026-10-26)

- El dueño guardó los cambios de NIT (`e91338b`) y recuperación automática (`92e0fba`), e inició el merge de `a1e4904`. **Codex resolvió los tres conflictos** en estado, Historial y este plan conservando ambas entregas, sus decisiones, la etapa R, el diagnóstico local resuelto y las revisiones previas. Sin cambios adicionales de aplicación, backend, esquema o versión.
- Verificación sobre la combinación real: 45 PHP/337 aserciones y 15 JS correctas; sin host MySQL, API/SMTP reales ni base de trabajo. Sin marcadores pendientes en los archivos ni errores de diff. PDF/Word del Historial regenerados con ambas entradas e índice actualizado; manifiesto sincronizado.
- Resolución entregada, pendiente de guardar el merge por el dueño mediante git add/commit; no se ejecutan esas operaciones ni push desde el agente. Recuperación automática pendiente de revisión de Claude; R completa y aceptación visual del dueño siguen abiertas.

## Ajustes del administrador — Revisión de integración de Codex (2026-10-26)

**Escribió Claude Code; revisó Codex por petición del dueño. Resultado: aprobada con corrección mínima local; preparada para integrar.** Rama `claude/ajustes-admin`, commit `3afdceb`, base `a1e4904` de Suscripción ya integrada en release/v2.2.0. Conserva el mockup aprobado por el dueño: Ajustes del administrador reúne clínica y cuenta; el perfil del veterinario queda separado. Rutas/acciones, matriz de Security y esquema conservados; extracción de datos de vista propios por id_usuario de sesión, datos clínicos filtrados por el modelo y formularios/acciones AJAX existentes. Sin nuevas HU, migraciones o versión; 2.2.0 sin publicar.

- **P1 corregido por Codex (RE-T.5.2):** `public/js/perfil.js:127` (aproximadamente 133 en la rama de Claude) asumía `#perfilEmailHero`, que Ajustes ya no renderiza. Reproducción con éxito simulado al guardar teléfono: la operación devuelve success, pero la referencia nula entra al catch y muestra «Error de conexión» tras actualizar el estado local. Guardia de una línea, compartida con la vista del veterinario; regresión en `PerfilSecciones.test.cjs` comprueba un solo envío y «Datos actualizados.» con y sin esa cabecera. Falla antes de la guardia y pasa después (2/2 en esta rama). Sin lógica de servidor modificada por Codex.
- **Rama original aislada:** 90 PHP/1097 aserciones y 52 JS dirigidas correctas. Lectura de diff, permisos, aislamiento, composición de cuenta, marcado/escape, callbacks y comportamiento de Mi perfil/Configuración/Suscripción; Modelos §13.2–13.4 conserva los flujos de identidad, contraseña y horario por los mismos endpoints.
- **Combinación simulada:** aplicación de la release y cambios locales sobre copia exportada de Claude: 109 PHP/1221 correctas; 52 JS de los otros grupos y 5/5 de Ajustes en la pasada final. La primera pasada combinada detectó un 404 del arnés que podía resolver antes de cargar el helper de avisos; la comprobación final de Ajustes responde el GET del horario con JSON simulado y valida el formulario con sus respuestas AJAX simuladas. No se cambió la carga de scripts de la aplicación para ocultar ese fallo del arnés.
- **Compatibilidad y cobertura adicional:** preservadas recuperación automática y cabecera/pestañas compactas. Cinco secciones de Ajustes sin desborde a 360×800, 390×844, 820×1180 y 1366×768; guardado de contacto en el Ajustes real sin `#perfilEmailHero`; Mi cuenta/Seguridad del veterinario y sus formularios conservados. El arnés no toca sesiones, correos, pasarela ni bases de trabajo reales. Sintaxis de la corrección y diff correctos.
- **P2 para R, no bloquea la fusión:** `public/css/perfil.css:135` conserva `font-size: 0.9rem` en inputs de cuenta, inferior a los 16 px pedidos para móvil. Ajustarlo en la hoja de la pantalla al supervisar Ajustes; no cambiar el perfil del veterinario fuera de su turno. Mantener también la revisión visual de las secciones largas, no prometer ausencia de scroll con cualquier volumen de datos.
- **Cruces preparados:** estado, Historial, este plan y `RedisenoSuscripcion.test.cjs` (dos bloques): conservar etiqueta Volver «Ajustes» y navegación nueva junto con H1 normal, prueba de cabecera y comprobaciones 360/390/820. La nueva `AjustesAdmin.test.cjs` debe esperar el H1 con la clase normal del encabezado al integrar; adaptación y pruebas adicionales preparadas en la copia. Conservar las entradas de recuperación/R en documentación. No se modifican los archivos de aplicación nuevos en el checkout activo antes del merge del dueño.
- **Evidencia:** `scratch/revision-ajustes-3afdceb/` (primero rama original, después combinación), `scratch/integracion-ajustes-preparada/`, `scratch/ajustes-rama-{php,js}.log`, `scratch/ajustes-combinado-php.log`, `scratch/ajustes-combinado-js.log`, `scratch/ajustes-combinado-ajustes-final.log` y reproducción/corrección en `scratch/ajustes-telefono-{reproduccion,corregido}.log`.
- **Sigue:** guardar el pulido de Suscripción y la corrección de contacto, merge por el dueño, resolver los cruces conservando ambas entregas y actualizar PDF/Word del Historial; sin commit/push/merge del agente. La revisión independiente de la recuperación de Codex sigue correspondiendo a Claude; R completa no se cierra.
- **Prueba del dueño tras integrar (tres puntos):** guardar teléfono desde Ajustes › Mi cuenta y ver éxito sin recargar; entrar por avatar/enlace viejo y por Horarios/Ausencias del Inicio conservando la sección; recorrer Ajustes/Suscripción y volver en escritorio/celular, comprobando que el veterinario conserva su perfil.

## Etapa C — Ajustes del administrador: Configuración y Mi perfil en una pantalla (2026-10-26)

**Decisión del dueño:** Configuración y Mi perfil del administrador son lo mismo («ajustes»), así que se unen. Mockup aprobado con un cambio: el menú lateral **blanco**, no el panel azul de Mi perfil. El veterinario conserva su Mi perfil.

- **Escritorio:** una hoja con menú lateral blanco (identidad arriba con avatar azul; grupos «La clínica» y «Mi cuenta»; la sección activa con fondo azul suave) y la sección al lado. La clínica: Datos de la clínica, Horarios, Ausencias y cierres y Suscripción (sección con el plan; integrada en la hoja por la corrección posterior de Codex, descrita en R). Mi cuenta: Mi cuenta y Seguridad, con los mismos formularios de Mi perfil. Cerrar sesión al pie del menú. Cada sección cabe sin scroll de página en 1366×768; lo largo (horarios) se desplaza dentro de la hoja. En Horarios el resumen semanal pasa debajo de la tabla cuando la sección es angosta (consulta de contenedor).
- **Celular y tableta (< 1024 px):** Ajustes abre una lista agrupada; tocar una fila abre la sección con «← Ajustes» y su título (sin repetir el de la página). La barra inferior queda con cinco destinos y sin «Más»: Inicio, Usuarios, Citas, Auditoría y Ajustes. Cerrar sesión pasa al final de Ajustes. Un enlace con sección (`#horarios`, `#ausencias`, `#cuenta`) la abre directo; volver a la lista quita la sección de la dirección.
- **Rutas y permisos sin cambios:** `admin_configuracion` pinta Ajustes con los datos de la cuenta (`PerfilController::datosVista()`); `mi_perfil` del administrador redirige a `admin_configuracion#cuenta`. Los paneles de cuenta salieron a `views/perfil/paneles_cuenta.php`, que comparten ambas vistas. Se retiraron los estilos de las pestañas viejas de Configuración.
- **Verificación:** PHP sin host 1165/9353 (91 omitidas); JS 362/362. `AjustesAdmin` comprueba estructura, redirección, variables `--z-*` y en Chrome: cinco secciones sin scroll en 1366×768 con menú blanco; a 390 lista, sección con «← Ajustes», barra de cinco y `#cuenta` directo. Capturas en `specs/referencias/ajustes/`.
- **Prueba manual (máximo cinco comprobaciones):**
  1. Escritorio: recorrer las seis secciones de Ajustes; ninguna deja scroll de página.
  2. Abrir Suscripción dentro de Ajustes; en el celular volver con «← Ajustes».
  3. Celular: Ajustes abre la lista; entrar a una sección y volver; Cerrar sesión al final.
  4. Desde el Inicio, «Revisar» ausencias u horarios abre esa sección directo.
  5. El avatar del encabezado y un enlace viejo a `mi_perfil` llevan a Ajustes › Mi cuenta; el veterinario sigue con su Mi perfil.
