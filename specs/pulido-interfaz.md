# Pulido de interfaz — pendientes

> Lista viva de mejoras de interfaz que el usuario detecta en las pruebas manuales y que **no** bloquean la etapa en curso. Se atienden en una etapa final de pulido (después de que estén las funciones nuevas de la v2.0, para no ajustar dos veces), salvo que el usuario pida adelantar alguna. Cada punto dice de dónde salió.

## Agenda

- **Validar al arrastrar, antes de soltar** (recorrido C8/C9, 2026-10-08). Mientras se arrastra una cita, los días no laborables, las horas pasadas y los huecos ocupados deben verse bloqueados (por ejemplo, en gris) y no admitir el soltado. Hoy se valida al soltar y el servidor devuelve la cita. En FullCalendar: `eventAllow` con el horario de la clínica y los eventos cargados; el servidor sigue siendo la validación final.
- **Indicador de carga lento** al reprogramar o mover citas (recorrido C8/C9). Revisar si la demora es de la petición o de la interfaz (ver «Respuesta inmediata» abajo).

## Respuesta inmediata en pantalla

- **La pantalla se actualiza 3–4 s tarde** después de vincular o desvincular una clínica, cancelar una cita o registrar una mascota en el portal (recorrido C8/C9). Causa probable, vista en `public/js/portal.js`: la recarga espera a que se cierre el aviso (`avisoPortal(...).then(() => location.reload())`). Debe actualizarse la vista en cuanto responde el servidor y mostrar el aviso en paralelo (o después de recargar). **Se corrige en C9.1** (recarga sin esperar el aviso) y **queda resuelto en M5 C2b**: el portal se pone al día en su sitio, sin recargar, al agendar, cancelar, vincularse, desvincularse, autorizar y registrar o editar una mascota.

## Formularios

- **Validación en tiempo real** de los campos (recorrido C8/C9): el teléfono del perfil del propietario no tiene longitud máxima ni filtra caracteres mientras se escribe. Revisar el mismo patrón en todos los formularios. **El teléfono del perfil se corrige en C9.1**; el resto, en el pulido.
- **Formulario de tratamiento** de la consulta: mejorar sus campos (dosis, duración, vía) y su presentación (recorrido C8/C9).
- **Campos obligatorios de la consulta:** el aviso de «falta el motivo» llega al finalizar; marcar los obligatorios desde el inicio y avisar en el campo (recorrido C8/C9). **Hecho en M2 C5a** (modal y Atención): motivo y diagnóstico salen «Obligatorio.» desde que se abre, el aviso va junto al campo mientras se escribe y los errores del servidor (también los de los signos) salen junto a su campo.
- **Peso desconocido de la mascota:** decidir cómo registrar un peso aproximado o desconocido (pregunta del usuario, recorrido C8/C9).
- **Modales:** mejorar su diseño en general (recorrido C8/C9).
- **Vincular una mascota existente a la clínica está escondido** (usuario, 2026-10-15, pruebas de M2 C5a): hoy solo se llega por Pacientes → Nuevo registro → pestaña «Mascota» → buscar al propietario por documento → «Vincular a la clínica», y antes el propietario tiene que vincularse a la clínica desde su portal. Sin instrucciones no se encuentra. Propuesta: un acceso visible en Pacientes («Vincular mascota de un cliente»), que diga en una línea qué hacer si el propietario aún no está vinculado (pedir confirmación por correo). RE-1.5.1 a RE-1.5.3.
- **«Primera consulta» en la línea de tiempo vacía** (usuario, 2026-10-15): se lee como si hiciera falta una cita antes. Una consulta sin cita es válida (HU-2.2, urgencias o imprevistos); cambiar el texto a «Registrar consulta» y, si no viene de una cita, indicar «Sin cita».

## Validación reutilizable — D2 (2026-10-08)

`public/js/validacion-cuenta.js` consulta `ValidadorCuenta` y `PoliticaPassword`, muestra el error junto al campo y bloquea el envío mientras valida. Desde D3, la ayuda solo comprueba formato y política, nunca existencia de una cuenta (RE-T.13.2). Aplicado a registro, completar perfil, activar personal, crear/restablecer/cambiar contraseña, correo y documento del titular, contacto del perfil, Usuarios/Clientes y alta/edición del propietario en Pacientes (incluido Fabio).

Pendiente extender el patrón a los formularios del resto del sistema: mascota (**hecha: la del portal en M5 C2b y la del personal, registro y edición, en M5 C3**, con `validacion-mascota.js` y las reglas de `ValidadorMascota`), consulta (motivo, diagnóstico y signos **hechos en M2 C5a**; tratamiento en C5b), vacunas, desparasitación, agenda (crear/reprogramar), horarios y configuración. Sus reglas siguen en sus propios helpers; no se debe usar `ValidadorCuenta` para campos clínicos. Registro de clínicas y NIT se aplican en D3. La comprobación visual en móvil y el ajuste general de media queries siguen en el pulido final.

## Restricción de entrada — D3.1 (2026-10-10)

Aplicada con `data-caracteres` de `interacciones.js` y atributos de los helpers a registro de clínica, registro de propietarios (portal y personal), completar perfil, Datos de acceso, Usuarios y formularios de contraseña (Mi perfil, portal, cambio obligatorio, activación y restablecimiento). La activación de clínica solo tiene consentimiento y campos ocultos; conserva el diseño de enlaces del correo. Documento de hasta 15 dígitos; NIT solo dígitos y un guion antes del DV; nombres con letras, espacios, apóstrofo y guion; correo sin espacios y hasta 255; teléfono conforme a `ValidadorTelefono`. El servidor normaliza espacios y rechaza caracteres ilegales; las contraseñas conservan sus espacios y símbolos con límite de 72 bytes.

Las de mascota quedan hechas (**portal en M5 C2b y personal en M5 C3**: nombre, raza indicada y peso). Pendiente aplicar restricciones y máximos compartidos a tratamientos (la consulta tiene los de `ValidadorClinico` desde M2 C5a), vacunas, desparasitación, agenda, horarios/configuración y los formularios futuros de reputación, comunicaciones y administración de plataforma. Conservar las reglas de cada módulo y no restringir textos clínicos con la regla de nombres de persona. El filtro de servicios del portal se retiró con la sección en M5 C2a; no se introdujeron eventos ni estilos en línea en D3.1.

## Adaptación a pantallas

- **Media queries de todo el sistema** al final, cuando ya no entren funciones nuevas (decisión del usuario, 2026-10-08).

## Confirmaciones con el modal de Zooki — M5 C4a (2026-10-13)

`zookiConfirmarCorto` (`avisos.js`) ya es el modal de Zooki en todo el sistema (decisión del usuario): lo usan el portal (agendar, cancelar una cita, cita adicional del mismo día) y Clientes del personal. **Pendiente para el pulido final:** pasar a ese modal las confirmaciones que siguen con SweetAlert: `zookiConfirmar` en `atencion.js`, `horarios.js` y `usuarios.js`, y los `Swal.fire` con botones del panel de plataforma y de «Gestionar». Los toasts siguen con SweetAlert.

## Relacionado con módulos futuros (no es pulido)

- **Motivo de la cita obligatorio** cuando exista el Grafo I: el triage necesita síntomas (módulo 4, HU-4.13 y HU-2.11).
- **Manual de usuario:** incluir que las citas se pueden arrastrar (entregable pendiente).
