# Pulido de interfaz — pendientes

> Lista viva de mejoras de interfaz que el usuario detecta en las pruebas manuales y que **no** bloquean la etapa en curso. Se atienden en una etapa final de pulido (después de que estén las funciones nuevas de la v2.0, para no ajustar dos veces), salvo que el usuario pida adelantar alguna. Cada punto dice de dónde salió.

## Agenda

- **Validar al arrastrar, antes de soltar** (recorrido C8/C9, 2026-10-08). Mientras se arrastra una cita, los días no laborables, las horas pasadas y los huecos ocupados deben verse bloqueados (por ejemplo, en gris) y no admitir el soltado. Hoy se valida al soltar y el servidor devuelve la cita. En FullCalendar: `eventAllow` con el horario de la clínica y los eventos cargados; el servidor sigue siendo la validación final.
- **Indicador de carga lento** al reprogramar o mover citas (recorrido C8/C9). Revisar si la demora es de la petición o de la interfaz (ver «Respuesta inmediata» abajo).

## Respuesta inmediata en pantalla

- **La pantalla se actualiza 3–4 s tarde** después de vincular o desvincular una clínica, cancelar una cita o registrar una mascota en el portal (recorrido C8/C9). Causa probable, vista en `public/js/portal.js`: la recarga espera a que se cierre el aviso (`avisoPortal(...).then(() => location.reload())`). Debe actualizarse la vista en cuanto responde el servidor y mostrar el aviso en paralelo (o después de recargar). **Se corrige en C9.1.**

## Formularios

- **Validación en tiempo real** de los campos (recorrido C8/C9): el teléfono del perfil del propietario no tiene longitud máxima ni filtra caracteres mientras se escribe. Revisar el mismo patrón en todos los formularios. **El teléfono del perfil se corrige en C9.1**; el resto, en el pulido.
- **Formulario de tratamiento** de la consulta: mejorar sus campos (dosis, duración, vía) y su presentación (recorrido C8/C9).
- **Campos obligatorios de la consulta:** el aviso de «falta el motivo» llega al finalizar; marcar los obligatorios desde el inicio y avisar en el campo (recorrido C8/C9).
- **Peso desconocido de la mascota:** decidir cómo registrar un peso aproximado o desconocido (pregunta del usuario, recorrido C8/C9).
- **Modales:** mejorar su diseño en general (recorrido C8/C9).

## Validación reutilizable — D2 (2026-10-08)

`public/js/validacion-cuenta.js` consulta `ValidadorCuenta`, `PoliticaPassword` y la unicidad en el servidor, muestra el error junto al campo y bloquea el envío mientras valida. Aplicado a registro, completar perfil, activar personal, crear/restablecer/cambiar contraseña, correo y documento del titular, contacto del perfil, Usuarios/Clientes y alta/edición del propietario en Pacientes (incluido Fabio).

Pendiente extender el patrón a los formularios del resto del sistema: mascota (registro/edición del personal y del portal), consulta (motivo, tratamiento y datos clínicos), vacunas, desparasitación, agenda (crear/reprogramar), horarios y configuración. Sus reglas siguen en sus propios helpers; no se debe usar `ValidadorCuenta` para campos clínicos. Registro de clínicas y NIT se aplican en D3. La comprobación visual en móvil y el ajuste general de media queries siguen en el pulido final.

## Adaptación a pantallas

- **Media queries de todo el sistema** al final, cuando ya no entren funciones nuevas (decisión del usuario, 2026-10-08).

## Relacionado con módulos futuros (no es pulido)

- **Motivo de la cita obligatorio** cuando exista el Grafo I: el triage necesita síntomas (módulo 4, HU-4.13 y HU-2.11).
- **Manual de usuario:** incluir que las citas se pueden arrastrar (entregable pendiente).
