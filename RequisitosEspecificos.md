# Requisitos Específicos por Historia de Usuario — Proyecto Zooki

> **Revisión 2.1** · Desglose por módulos · Trazabilidad RN → HU → RE · SENA ADSO — Ficha 3142784

Cada requisito (RE-<HU>.<n>) incluye su tipo, su prioridad y su **criterio de aceptación** verificable.

## Módulo T — Acceso, seguridad y administración

### HU-17 — Iniciar sesión y autenticación

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-17.1 | El sistema debe autenticar al usuario por documento/usuario y contraseña contra la base de datos. | Funcional | Con credenciales válidas se concede el acceso; con inválidas se niega. | Alta |
| RE-17.2 | El sistema debe almacenar las contraseñas con hash bcrypt + salt. | Seguridad | La contraseña en la base de datos aparece como hash, no legible. | Alta |
| RE-17.3 | El sistema debe permitir el inicio de sesión con Google (OAuth 2.0). | Integración | El acceso con Google inicia sesión y asocia la cuenta. | Media |
| RE-17.4 | El sistema debe redirigir al panel correspondiente según el rol. | Funcional | Cada rol aterriza en su panel tras autenticarse. | Alta |
| RE-17.5 | El sistema debe mostrar un mensaje genérico ante credenciales inválidas. | Seguridad | Con usuario o contraseña incorrectos se muestra el mismo mensaje. | Media |

**Reglas de negocio:** RN-G01, RN-G03, RN-G09

### HU-39 — Cambiar mi contraseña

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-39.1 | El sistema debe solicitar la contraseña actual, salvo en cuentas creadas con Google. | Seguridad | Sin la contraseña actual correcta (cuenta local), el cambio se rechaza. | Alta |
| RE-39.2 | El sistema debe exigir que la nueva contraseña cumpla la política mínima. | Validación | Una nueva contraseña que no cumple la política es rechazada. | Alta |
| RE-39.3 | El sistema debe actualizar el hash y confirmar el cambio. | Funcional | Tras el cambio se puede iniciar sesión con la nueva contraseña. | Alta |

**Reglas de negocio:** RN-G03

### HU-40 — Recuperar contraseña olvidada

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-40.1 | El sistema debe permitir solicitar el restablecimiento con el correo y responder de forma genérica. | Seguridad | La respuesta no revela si el correo existe. | Alta |
| RE-40.2 | El sistema debe enviar un enlace con un token temporal. | Funcional | Llega un correo con el enlace de restablecimiento. | Alta |
| RE-40.3 | El enlace debe ser de un solo uso y expirar. | Seguridad | Un enlace usado o vencido es rechazado. | Alta |
| RE-40.4 | El sistema debe permitir definir una nueva contraseña válida. | Funcional | Se puede establecer una contraseña que cumple la política. | Alta |

**Reglas de negocio:** RN-G04

### HU-41 — Cerrar sesión

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-41.1 | El sistema debe destruir la sesión activa al cerrar sesión. | Funcional | Tras cerrar, no queda sesión activa. | Alta |
| RE-41.2 | El sistema debe exigir re-autenticación en rutas protegidas tras el cierre. | Seguridad | Acceder a una ruta protegida tras cerrar pide login. | Alta |
| RE-41.3 | El sistema debe registrar el cierre de sesión en auditoría. | Funcional | El logout queda en el log. | Baja |

**Reglas de negocio:** RN-G01, RN-G05

### HU-42 — Ver y actualizar mi perfil y datos de contacto

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-42.1 | El sistema debe mostrar los datos del perfil del usuario. | Funcional | El usuario ve sus datos actuales. | Media |
| RE-42.2 | El sistema debe permitir actualizar teléfono y correo. | Funcional | Los cambios se guardan y se reflejan. | Media |
| RE-42.3 | El sistema debe validar la unicidad del nuevo correo. | Validación | Un correo ya usado por otra cuenta es rechazado. | Media |

**Reglas de negocio:** RN-G06, RN-G07

### HU-45 — Gestionar mis notificaciones internas

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-45.1 | El sistema debe mostrar el contador de notificaciones no leídas. | Funcional | El contador refleja las no leídas. | Media |
| RE-45.2 | El sistema debe listar las notificaciones recientes. | Funcional | Se ven las últimas notificaciones del usuario. | Media |
| RE-45.3 | El sistema debe permitir marcar una o todas como leídas. | Funcional | Al marcar, el contador disminuye. | Media |
| RE-45.4 | El sistema debe mostrar solo las notificaciones del rol/usuario. | Autorización | No se ven notificaciones dirigidas a otros. | Media |

**Reglas de negocio:** RN-G02

### HU-22 — Gestión de usuarios del sistema

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-22.1 | El sistema debe permitir crear, editar y desactivar usuarios (nombre, correo, rol, estado). | Funcional | Crear, editar y desactivar un usuario persiste el cambio. | Media |
| RE-22.2 | El sistema debe restringir el módulo de usuarios al rol administrador. | Restricción | Un usuario no administrador recibe 403. | Alta |
| RE-22.3 | El sistema debe impedir eliminar o inactivar al único administrador. | Restricción | Intentar eliminar/inactivar el último admin es rechazado. | Media |
| RE-22.4 | El sistema debe validar la unicidad del correo del usuario. | Validación | Registrar un correo ya existente muestra error. | Media |

**Reglas de negocio:** RN-G08, RN-501

### HU-24 — Logs de auditoría y seguridad

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-24.1 | El sistema debe registrar login exitoso/fallido y las operaciones CRUD. | Funcional | Login y operaciones CRUD generan entradas de auditoría. | Media |
| RE-24.2 | El sistema debe almacenar usuario, fecha/hora, IP, operación, tabla y datos previos/nuevos. | Funcional | Cada entrada contiene todos esos campos. | Media |
| RE-24.3 | El sistema debe ofrecer una vista de logs filtrable. | Funcional | La vista filtra por usuario, operación y fechas. | Media |
| RE-24.4 | El sistema debe mantener los registros como solo lectura. | Restricción | No hay opción de editar o borrar la auditoría. | Media |

**Reglas de negocio:** RN-G05

### HU-23 — Backup automático de la base de datos

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-23.1 | El sistema debe ejecutar un mysqldump y comprimir el archivo. | Funcional | Se genera un respaldo comprimido y no vacío. | Media |
| RE-23.2 | El sistema debe programar el respaldo cada 24 horas por cron. | Integración | El cron ejecuta el respaldo diariamente. | Media |
| RE-23.3 | El sistema debe almacenar el respaldo en un directorio externo. | Restricción | El respaldo queda en el directorio externo configurado. | Media |
| RE-23.4 | El sistema debe retener/rotar los últimos respaldos. | Funcional | Los respaldos antiguos se eliminan según la rotación. | Baja |

**Reglas de negocio:** RN-504

### HU-32 — Autorización central por rol (RBAC real)

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-32.1 | El sistema debe aplicar una matriz acción → roles permitidos antes de ejecutar cualquier acción. | Seguridad | Una acción sin rol permitido no se ejecuta. | Alta |
| RE-32.2 | El sistema debe responder 403 al rol sin permiso. | Seguridad | El acceso indebido devuelve 403. | Alta |
| RE-32.3 | El control de rol debe cubrir todos los endpoints AJAX. | Seguridad | Ningún endpoint queda sin control de rol. | Alta |

**Reglas de negocio:** RN-G01, RN-G02

### HU-33 — Corregir escalada de privilegios y proteger al último admin

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-33.1 | El sistema debe exigir rol administrador para crear/editar usuarios. | Seguridad | Un no administrador no puede crear ni editar usuarios. | Alta |
| RE-33.2 | El sistema debe validar el rol asignado contra una lista permitida. | Seguridad | No se puede asignar un rol fuera de la lista permitida. | Alta |
| RE-33.3 | El sistema debe impedir desactivar/eliminar al último administrador activo. | Restricción | Desactivar el último admin es rechazado. | Alta |

**Reglas de negocio:** RN-G08, RN-501

### HU-36 — Política de contraseñas, verificación de correo y OAuth seguro

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-36.1 | El sistema debe aplicar una política única (mínimo 8 + complejidad) en todos los flujos. | Seguridad | Una contraseña débil se rechaza en cualquier flujo. | Media |
| RE-36.2 | El sistema debe verificar el correo en el registro antes de activar la cuenta. | Seguridad | La cuenta se activa solo tras verificar el correo. | Media |
| RE-36.3 | El sistema debe validar aud/iss del token de Google contra el client_id propio. | Seguridad | Un token emitido para otra app es rechazado. | Media |

**Reglas de negocio:** RN-G03, RN-G04

### HU-38 — Endurecer rate limiting y reducir enumeración/fuga

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-38.1 | El sistema debe contar los intentos del lado servidor por IP/cuenta. | Seguridad | El límite no se evade descartando la cookie. | Media |
| RE-38.2 | El sistema no debe revelar la existencia de documentos/correos. | Privacidad | No se puede enumerar cuentas por estas consultas. | Media |
| RE-38.3 | El sistema debe mostrar errores genéricos al cliente. | Seguridad | El cliente no recibe detalles técnicos del error. | Media |

**Reglas de negocio:** RN-G03

### HU-54 — Restablecer la contraseña de un usuario (administrador)

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-54.1 | El sistema debe permitir al administrador generar un restablecimiento para un usuario. | Funcional | El administrador dispara el restablecimiento. | Media |
| RE-54.2 | El sistema debe forzar el cambio de contraseña en el próximo ingreso del usuario. | Seguridad | El usuario debe cambiarla al ingresar. | Media |
| RE-54.3 | El sistema debe permitirlo solo al administrador y registrarlo en auditoría. | Autorización | Solo el administrador; la acción queda en auditoría. | Media |

**Reglas de negocio:** RN-G08, RN-501, RN-G05

## Módulo 1 — Mascotas y propietarios

### HU-01 — Registrar mascota

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-01.1 | El sistema debe exigir nombre, especie, raza, fecha de nacimiento, peso, sexo y color. | Funcional | Guardar sin un campo obligatorio muestra error. | Alta |
| RE-01.2 | El sistema debe permitir subir una fotografía JPG/PNG validando tipo y tamaño. | Validación | Un archivo no permitido o excedido es rechazado. | Media |
| RE-01.3 | El sistema debe impedir guardar una mascota sin propietario. | Restricción | Guardar sin propietario es rechazado. | Alta |
| RE-01.4 | El sistema debe validar los campos en cliente y servidor. | Validación | La validación falla en cliente y en servidor. | Alta |
| RE-01.5 | El sistema debe reflejar la mascota en el listado y la búsqueda. | Funcional | La mascota creada aparece en listado y búsqueda. | Alta |

**Reglas de negocio:** RN-101, RN-102, RN-106, RN-107

### HU-02 — Registrar propietario

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-02.1 | El sistema debe exigir nombre, tipo y número de documento, teléfono y correo. | Funcional | Faltar un campo obligatorio impide guardar. | Alta |
| RE-02.2 | El sistema debe validar la unicidad del documento y del correo. | Validación | Un documento o correo duplicado es rechazado. | Alta |
| RE-02.3 | El sistema debe listar las mascotas del propietario en su perfil. | Funcional | El perfil lista todas sus mascotas. | Media |

**Reglas de negocio:** RN-101, RN-103, RN-G06

### HU-03 — Buscar paciente

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-03.1 | El sistema debe buscar por nombre de mascota, propietario o documento. | Funcional | Buscar por cada criterio retorna la mascota esperada. | Alta |
| RE-03.2 | El sistema debe responder en menos de 2 s con mínimo 3 caracteres. | Rendimiento | Con 3+ caracteres los resultados llegan en menos de 2 s. | Alta |
| RE-03.3 | El sistema debe mostrar nombre, especie, propietario y miniatura. | Funcional | Cada resultado muestra esos datos. | Media |
| RE-03.4 | El sistema debe excluir las inactivas por defecto. | Restricción | Una mascota inactiva no aparece por defecto. | Media |

**Reglas de negocio:** RN-105

### HU-04 — Editar y desactivar mascota

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-04.1 | El sistema debe permitir editar cualquier campo de la ficha. | Funcional | Editar y guardar persiste el cambio. | Media |
| RE-04.2 | El sistema debe registrar en auditoría fecha, usuario y campo modificado. | Funcional | El cambio genera una entrada de auditoría. | Media |
| RE-04.3 | El sistema debe ocultar de búsquedas activas a las inactivas conservando su historial. | Restricción | La inactiva desaparece pero su historial se consulta. | Media |
| RE-04.4 | El sistema debe impedir la eliminación física con historial clínico. | Restricción | Eliminar una mascota con historial es rechazado. | Alta |

**Reglas de negocio:** RN-104, RN-105, RN-108

## Módulo 2 — Historia clínica

### HU-05 — Registrar consulta clínica

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-05.1 | El sistema debe registrar fecha/hora, motivo, anamnesis, examen físico, diagnóstico y plan. | Funcional | La consulta conserva todos los campos. | Alta |
| RE-05.2 | El sistema debe generar un número de HC único en la primera consulta. | Funcional | La primera consulta genera un N.° de HC único. | Alta |
| RE-05.3 | El sistema debe impedir guardar una consulta sin diagnóstico. | Restricción | Guardar sin diagnóstico es rechazado. | Alta |
| RE-05.4 | El sistema debe vincular la consulta a la mascota y mostrarla en su historial. | Funcional | La consulta aparece en el historial cronológico. | Alta |

**Reglas de negocio:** RN-201, RN-202, RN-203

### HU-06 — Adjuntar archivos clínicos

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-06.1 | El sistema debe aceptar JPG, PNG y PDF de hasta 10 MB. | Validación | Un archivo mayor o de tipo no permitido es rechazado. | Alta |
| RE-06.2 | El sistema debe almacenar los archivos en carpeta protegida. | Seguridad | El acceso directo por URL es bloqueado. | Alta |
| RE-06.3 | El sistema debe servir descargas solo a usuarios autorizados. | Seguridad | Sin sesión válida, la descarga devuelve 401/403. | Alta |
| RE-06.4 | El sistema debe permitir múltiples archivos por consulta. | Funcional | Se adjuntan y listan varios archivos. | Media |

**Reglas de negocio:** RN-204, RN-205

### HU-07 — Registrar tratamiento

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-07.1 | El sistema debe registrar medicamento, dosis, vía, duración y observaciones. | Funcional | El tratamiento conserva todos esos datos. | Alta |
| RE-07.2 | El sistema debe vincular cada tratamiento a su consulta. | Funcional | El tratamiento aparece vinculado a la consulta. | Alta |
| RE-07.3 | El sistema debe permitir múltiples tratamientos por consulta. | Funcional | Se registran varios tratamientos en una consulta. | Media |

**Reglas de negocio:** RN-205

### HU-08 — Ver historial clínico completo

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-08.1 | El sistema debe mostrar el historial cronológico descendente con acordeón. | Funcional | El historial se ve del más reciente al más antiguo. | Media |
| RE-08.2 | Cada entrada debe mostrar fecha, motivo, diagnóstico, tratamientos y archivos. | Funcional | Cada entrada muestra esos elementos. | Media |
| RE-08.3 | El historial debe cargar en menos de 3 s para hasta 100 consultas. | Rendimiento | 100 consultas cargan en menos de 3 s. | Media |

**Reglas de negocio:** RN-206

### HU-34 — Atomicidad y feedback de adjuntos en el registro de consulta

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-34.1 | El sistema debe guardar consulta, HC, archivos y tratamientos en una transacción. | Integridad | Si algo falla, no queda una consulta parcial. | Alta |
| RE-34.2 | El sistema debe informar por cada adjunto rechazado y su motivo. | Usabilidad | El usuario ve qué archivo se rechazó y por qué. | Alta |
| RE-34.3 | El sistema no debe reportar éxito con datos parciales. | Integridad | El éxito solo se reporta si todo se guardó. | Alta |

**Reglas de negocio:** RN-204, RN-206

### HU-35 — Validación y control de acceso en registros clínicos y de mascota

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-35.1 | El sistema debe exigir rol clínico para registrar consulta/vacuna/desparasitación. | Autorización | Un no clínico no puede registrar. | Alta |
| RE-35.2 | El sistema debe validar que la mascota exista. | Validación | Un id de mascota inexistente es rechazado. | Alta |
| RE-35.3 | El sistema debe verificar el permiso del actor sobre la mascota. | Autorización | No se registra sobre mascotas sin permiso. | Alta |

**Reglas de negocio:** RN-201, RN-G02

## Módulo 3 — Vacunación, desparasitación y recordatorios

### HU-09 — Registrar vacunación

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-09.1 | El sistema debe registrar nombre, laboratorio, lote, fecha de aplicación y próxima dosis. | Funcional | La vacuna conserva todos sus datos. | Alta |
| RE-09.2 | El sistema debe mostrar la vacuna en el calendario de la mascota. | Funcional | La vacuna aparece en el calendario. | Media |
| RE-09.3 | El sistema debe generar una alerta 7 días antes de la próxima dosis. | Funcional | Se genera la alerta 7 días antes. | Alta |

**Reglas de negocio:** RN-301, RN-306

### HU-10 — Enviar recordatorio por correo

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-10.1 | El sistema debe enviar un correo 7 días y 1 día antes del vencimiento. | Funcional | Se envía correo a 7 y a 1 día. | Alta |
| RE-10.2 | El correo debe incluir mascota, tipo y fecha. | Funcional | El correo contiene esos datos. | Media |
| RE-10.3 | El sistema no debe enviar si el propietario no tiene correo. | Restricción | Sin correo, no se envía. | Media |
| RE-10.4 | El sistema debe registrar cada envío. | Funcional | Cada envío queda registrado con su estado. | Media |

**Reglas de negocio:** RN-303, RN-304, RN-305

### HU-11 — Enviar recordatorio por WhatsApp

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-11.1 | El sistema debe enviar un WhatsApp con el mismo contenido del correo. | Funcional | El WhatsApp llega con el mismo contenido (cuenta de prueba). | Futura |
| RE-11.2 | El sistema debe enviar solo si hay número de WhatsApp registrado. | Restricción | Sin número, no se envía. | Futura |
| RE-11.3 | El sistema debe usar la WhatsApp Business Cloud API (Meta). | Integración | El envío usa la API oficial de Meta. | Futura |

**Reglas de negocio:** RN-307

### HU-12 — Registrar desparasitación

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-12.1 | El sistema debe registrar tipo (interna/externa) y periodicidad. | Funcional | La desparasitación guarda tipo y periodicidad. | Media |
| RE-12.2 | El sistema debe calcular automáticamente la próxima aplicación. | Cálculo | La próxima fecha se calcula según la periodicidad. | Media |
| RE-12.3 | El sistema debe generar alertas igual que en vacunación. | Funcional | Se genera alerta como en vacunación. | Media |

**Reglas de negocio:** RN-302

### HU-20 — Panel de vacunaciones pendientes

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-20.1 | El sistema debe listar vacunas con próxima dosis en 7 días. | Funcional | El panel lista las próximas a 7 días. | Media |
| RE-20.2 | El sistema debe agrupar por día y especie con contador. | Funcional | Se agrupan por día y especie con contador. | Media |
| RE-20.3 | El sistema debe enlazar cada entrada con la ficha. | Funcional | Un clic abre la ficha de la mascota. | Baja |

**Reglas de negocio:** RN-301

### HU-37 — Robustez de los recordatorios automáticos

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-37.1 | El sistema debe usar una ventana de fechas con marca de enviado que recupere los no enviados. | Robustez | Si el cron falla un día, al siguiente se recuperan. | Media |
| RE-37.2 | El sistema debe reintentar los envíos fallidos. | Robustez | Un fallo transitorio se reintenta. | Media |
| RE-37.3 | El sistema debe usar la zona horaria de la clínica en los cálculos. | Robustez | El límite de día es correcto para la clínica. | Media |

**Reglas de negocio:** RN-303, RN-305

## Módulo 4 — Agenda de citas

### HU-13 — Agendar cita

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-13.1 | El sistema debe registrar fecha, hora, mascota, tipo/motivo y veterinario. | Funcional | La cita conserva todos esos datos. | Alta |
| RE-13.2 | El sistema debe impedir el solapamiento del veterinario. | Restricción | Agendar en un horario ocupado es rechazado. | Alta |
| RE-13.3 | El sistema debe respetar el horario de atención configurado. | Restricción | Agendar fuera del horario es rechazado. | Alta |
| RE-13.4 | El sistema debe enviar correo de confirmación al crear. | Funcional | El propietario recibe confirmación. | Alta |
| RE-13.5 | El sistema debe mostrar la cita en el calendario. | Funcional | La cita aparece en el calendario. | Media |

**Reglas de negocio:** RN-401, RN-402, RN-403, RN-404

### HU-14 — Cancelar o reprogramar cita

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-14.1 | El sistema debe permitir cancelar o cambiar fecha/hora. | Funcional | Se cancela o cambia la fecha/hora. | Media |
| RE-14.2 | El sistema debe notificar el cambio por correo. | Funcional | El propietario recibe correo del cambio. | Media |
| RE-14.3 | El sistema debe conservar las canceladas con su estado. | Restricción | La cancelada queda con estado cancelada. | Media |
| RE-14.4 | El sistema debe impedir reprogramar a un horario ocupado. | Restricción | Reprogramar a un horario ocupado es rechazado. | Alta |

**Reglas de negocio:** RN-405

### HU-19 — Marcar cita como completada

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-19.1 | El sistema debe permitir completar solo citas programada/en curso. | Restricción | Solo una cita en curso/programada se completa. | Media |
| RE-19.2 | El sistema debe vincular opcionalmente la consulta al completar. | Funcional | Se puede vincular la consulta registrada. | Baja |
| RE-19.3 | El sistema debe excluir las completadas de la agenda futura. | Funcional | Una completada no aparece en la agenda futura. | Media |

**Reglas de negocio:** RN-406

### HU-21 — Confirmación automática de cita por correo

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-21.1 | El sistema debe enviar el correo de confirmación inmediatamente tras guardar. | Funcional | El correo se envía de inmediato. | Alta |
| RE-21.2 | El correo debe incluir fecha, hora, mascota, motivo y dirección. | Funcional | El correo contiene todos esos datos. | Media |
| RE-21.3 | El envío no debe bloquear el agendado. | Rendimiento | El agendado responde sin esperar el correo. | Media |
| RE-21.4 | El sistema debe registrar el fallo de envío como fallido. | Funcional | Un fallo queda registrado como fallido. | Media |

**Reglas de negocio:** RN-404

### HU-27 — Registrar hora real de atención y gestionar retrasos

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-27.1 | El sistema debe sellar la hora real al iniciar y al completar la atención. | Funcional | Quedan registradas las horas reales de inicio y fin. | Alta |
| RE-27.2 | El sistema debe detectar cuando una atención excede su duración planificada. | Funcional | El sistema identifica cuando una atención se pasó. | Alta |
| RE-27.3 | El sistema debe alertar o recalcular el corrimiento de las citas siguientes. | Funcional | Las citas posteriores reflejan o avisan el corrimiento. | Alta |

**Reglas de negocio:** RN-401

### HU-28 — Buffer configurable entre citas

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-28.1 | El sistema debe permitir configurar el buffer entre citas. | Funcional | El administrador define el buffer. | Media |
| RE-28.2 | El sistema debe respetar el buffer al calcular la disponibilidad. | Restricción | No se agenda sin respetar el buffer. | Media |

**Reglas de negocio:** RN-401, RN-403

### HU-29 — Estado "no asistió" y ausentismo

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-29.1 | El sistema debe permitir marcar una cita como "no asistió". | Funcional | Se marca la cita como no asistida. | Media |
| RE-29.2 | El sistema debe liberar el espacio de una cita no asistida. | Funcional | El espacio queda libre. | Media |
| RE-29.3 | El sistema debe permitir reportar la tasa de ausentismo. | Funcional | Se obtiene la tasa de ausentismo. | Baja |

**Reglas de negocio:** RN-405

### HU-30 — Bloqueos de agenda del veterinario y días no laborables

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-30.1 | El sistema debe permitir registrar bloqueos por veterinario y fecha. | Funcional | Se crean bloqueos que restan disponibilidad. | Alta |
| RE-30.2 | El sistema debe permitir registrar festivos y cierres. | Funcional | Los festivos no ofrecen citas. | Media |
| RE-30.3 | El sistema debe excluir esos periodos de la disponibilidad. | Restricción | No se agenda en bloqueos ni festivos. | Alta |

**Reglas de negocio:** RN-402, RN-503

### HU-31 — Endurecer la validación de disponibilidad

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-31.1 | El sistema debe usar una sola lógica de disponibilidad basada en horarios_clinica. | Consistencia | Sugerencias y validación coinciden. | Alta |
| RE-31.2 | El sistema debe validar horario + duración en el backend. | Validación | El backend rechaza citas fuera de horario o que exceden el bloque. | Alta |
| RE-31.3 | El sistema debe usar transacción/restricción única contra la doble reserva. | Concurrencia | Dos reservas simultáneas del mismo hueco no coexisten. | Alta |

**Reglas de negocio:** RN-401, RN-402

### HU-51 — Confirmar asistencia a la cita desde el recordatorio

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-51.1 | El recordatorio debe incluir la opción de confirmar o declinar la asistencia. | Funcional | El correo/enlace permite confirmar o declinar. | Media |
| RE-51.2 | La respuesta debe actualizar el estado de la cita. | Funcional | Confirmar o declinar cambia el estado de la cita. | Media |
| RE-51.3 | Al declinar, el sistema debe liberar el espacio y notificar a la clínica. | Funcional | El cupo queda libre y la clínica se entera. | Media |

**Reglas de negocio:** RN-404, RN-405

## Módulo 5 — Portal del propietario

### HU-15 — Portal del propietario

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-15.1 | El sistema debe permitir el acceso con credenciales propias o con Google. | Funcional | El propietario ingresa con credenciales o con Google. | Alta |
| RE-15.2 | El sistema debe mostrar únicamente las mascotas del propietario. | Restricción | Solo ve sus mascotas. | Alta |
| RE-15.3 | El sistema debe mostrar ficha, historial, próximas citas y vacunas. | Funcional | Ve toda su información. | Media |
| RE-15.4 | El sistema debe devolver 403 ante datos de terceros. | Seguridad | Acceder a datos ajenos devuelve 403. | Alta |
| RE-15.5 | El portal debe ser responsivo para móvil. | Usabilidad | Se ve correctamente en móvil. | Media |

**Reglas de negocio:** RN-G02

### HU-25 — Auto-registro de propietario

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-25.1 | El sistema debe ofrecer un formulario público de registro. | Funcional | El formulario es accesible sin sesión. | Alta |
| RE-25.2 | El sistema debe exigir documento, nombre, teléfono, correo y contraseña segura. | Validación | Faltar un campo o contraseña débil impide registrar. | Alta |
| RE-25.3 | El sistema debe validar que documento y correo no existan. | Validación | Un documento o correo existente es rechazado. | Alta |
| RE-25.4 | El sistema debe crear el usuario propietario e iniciar sesión. | Funcional | Queda con rol propietario y sesión iniciada. | Alta |
| RE-25.5 | El sistema debe proteger el formulario con token CSRF. | Seguridad | Una petición sin token CSRF es rechazada. | Alta |

**Reglas de negocio:** RN-101, RN-G06, RN-407

### HU-26 — Agendar cita desde el portal

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-26.1 | El sistema debe permitir seleccionar una mascota propia. | Funcional | Solo se eligen mascotas propias. | Alta |
| RE-26.2 | El sistema debe permitir elegir tipo, veterinario, fecha y hora. | Funcional | Se elige tipo, veterinario, fecha y hora disponible. | Alta |
| RE-26.3 | El sistema debe verificar la disponibilidad dinámicamente. | Restricción | Un horario ocupado no se ofrece. | Alta |
| RE-26.4 | El sistema debe enviar confirmación y registrar en auditoría. | Funcional | Se confirma y queda en auditoría. | Media |

**Reglas de negocio:** RN-401, RN-402, RN-407

### HU-46 — Editar mi mascota desde el portal

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-46.1 | El sistema debe permitir editar los datos de mi mascota. | Funcional | Los cambios de mi mascota se guardan. | Media |
| RE-46.2 | El sistema debe permitir editar solo mis propias mascotas. | Autorización | No puedo editar mascotas ajenas. | Alta |
| RE-46.3 | El sistema debe permitir actualizar la foto con validación. | Validación | Una foto inválida es rechazada. | Media |

**Reglas de negocio:** RN-104, RN-108, RN-G02

### HU-47 — Imprimir/exportar el historial de mi mascota

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-47.1 | El sistema debe generar una versión imprimible del historial. | Funcional | Se genera una vista imprimible. | Media |
| RE-47.2 | El documento debe incluir consultas, vacunas y desparasitaciones. | Funcional | Contiene todo el historial. | Media |
| RE-47.3 | El sistema debe permitirlo solo para mis mascotas. | Autorización | No accedo a historiales ajenos. | Alta |

**Reglas de negocio:** RN-206, RN-G02

### HU-50 — Cancelar o reprogramar mi cita desde el portal

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-50.1 | El sistema debe permitir al propietario cancelar una cita futura propia. | Funcional | El propietario cancela su cita y queda en estado cancelada. | Alta |
| RE-50.2 | El sistema debe permitir reprogramar a un horario disponible. | Funcional | Se reprograma solo a horarios libres. | Alta |
| RE-50.3 | El sistema debe permitirlo solo sobre citas propias. | Autorización | No puede tocar citas de otros propietarios. | Alta |
| RE-50.4 | El sistema debe notificar el cambio a la clínica y registrarlo en auditoría. | Funcional | El cambio notifica a la clínica y queda en auditoría. | Media |

**Reglas de negocio:** RN-405, RN-401, RN-G02

### HU-52 — Centro de notificaciones del propietario

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-52.1 | El sistema debe mostrar al propietario un listado de sus notificaciones en el portal. | Funcional | El propietario ve sus notificaciones en el portal. | Media |
| RE-52.2 | El sistema debe mostrar un contador de no leídas. | Funcional | Hay un contador de no leídas. | Media |
| RE-52.3 | El sistema debe permitir marcarlas como leídas. | Funcional | Puede marcar como leídas. | Baja |
| RE-52.4 | El sistema debe mostrar solo las notificaciones del propietario. | Autorización | Solo ve sus notificaciones. | Alta |

**Reglas de negocio:** RN-G02

## Módulo 6 — Dashboard y reportes

### HU-18 — Dashboard principal y panel de pendientes

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-18.1 | El sistema debe mostrar citas del día y vacunas/desparasitaciones próximas a 7 días. | Funcional | El panel muestra citas del día y pendientes. | Alta |
| RE-18.2 | El sistema debe ofrecer accesos rápidos a funciones frecuentes. | Usabilidad | Los accesos llevan a las funciones correctas. | Media |
| RE-18.3 | El dashboard debe cargar en menos de 3 s. | Rendimiento | Carga en menos de 3 s. | Media |
| RE-18.4 | El sistema debe filtrar los datos según el usuario. | Seguridad | Cada usuario ve solo lo que le corresponde. | Alta |

**Reglas de negocio:** RN-G01

### HU-16 — Generar reportes en PDF

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-16.1 | El sistema debe generar reportes de pacientes, citas y vacunaciones pendientes. | Funcional | Se generan los tres reportes con datos correctos. | Media |
| RE-16.2 | El sistema debe permitir filtrar por rango de fechas. | Funcional | El filtro acota el contenido. | Media |
| RE-16.3 | El sistema debe permitir descargar el PDF desde el navegador. | Funcional | El PDF se descarga. | Media |
| RE-16.4 | El reporte debe incluir encabezado con nombre de clínica y fecha. | Funcional | El PDF incluye encabezado y fecha. | Baja |

**Reglas de negocio:** RN-501

### HU-48 — Ver estadísticas y gráficas del sistema

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-48.1 | El sistema debe mostrar indicadores clave (pacientes, clientes, citas, consultas). | Funcional | Se muestran los indicadores correctos. | Media |
| RE-48.2 | El sistema debe mostrar gráficas por periodo o categoría. | Funcional | Se ven gráficas coherentes con los datos. | Media |
| RE-48.3 | El sistema debe restringir las estadísticas a roles internos. | Autorización | Un propietario no ve las estadísticas. | Alta |

**Reglas de negocio:** RN-501

### HU-55 — Exportar reportes a Excel/CSV

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-55.1 | El sistema debe permitir exportar a Excel/CSV cada reporte disponible en PDF. | Funcional | Cada reporte PDF también se exporta a Excel/CSV. | Media |
| RE-55.2 | El archivo exportado debe respetar los filtros aplicados. | Funcional | El archivo respeta los filtros. | Media |
| RE-55.3 | Los datos exportados deben coincidir con la vista. | Funcional | Los datos coinciden con lo mostrado. | Baja |

**Reglas de negocio:** RN-501

## Módulo 7 — Configuración del sistema

### HU-43 — Configurar los horarios de atención de la clínica

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-43.1 | El sistema debe permitir definir bloques de mañana y tarde por día. | Funcional | Se guardan los bloques por día. | Alta |
| RE-43.2 | El sistema debe permitir activar o inactivar días. | Funcional | Un día inactivo no ofrece citas. | Media |
| RE-43.3 | El sistema debe permitir restaurar los horarios por defecto. | Funcional | Se restauran los horarios por defecto. | Baja |
| RE-43.4 | La agenda debe respetar la configuración de horarios. | Restricción | La disponibilidad usa estos horarios. | Alta |

**Reglas de negocio:** RN-503, RN-501

### HU-44 — Gestionar los catálogos del sistema

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-44.1 | El sistema debe permitir agregar entradas a los catálogos. | Funcional | Se crean nuevas entradas de catálogo. | Media |
| RE-44.2 | Las nuevas entradas deben aparecer en los formularios correspondientes. | Funcional | Las nuevas entradas están disponibles al registrar. | Media |
| RE-44.3 | Las vacunas base deben asociarse por especie. | Restricción | Una vacuna base aplica a su especie. | Media |

**Reglas de negocio:** RN-502

### HU-53 — Parámetros del sistema configurables

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-53.1 | El sistema debe permitir configurar la duración de cita por defecto y el buffer entre citas. | Funcional | Los valores se guardan y se aplican. | Media |
| RE-53.2 | El sistema debe permitir configurar la anticipación de los recordatorios. | Funcional | La ventana de recordatorio es configurable. | Media |
| RE-53.3 | El sistema debe aplicar los parámetros en la agenda y en los recordatorios. | Restricción | Agenda y cron usan los parámetros configurados. | Media |

**Reglas de negocio:** RN-403, RN-303, RN-503

## Módulo 8 — Público e institucional

### HU-49 — Página pública y políticas legales

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-49.1 | El sistema debe presentar el servicio en una página pública (landing). | Funcional | La landing es accesible y describe el servicio. | Baja |
| RE-49.2 | El sistema debe ofrecer páginas de privacidad, términos y cookies. | Funcional | Las páginas legales están disponibles. | Baja |
| RE-49.3 | La política de datos debe reflejar la Ley 1581 de 2012. | Restricción | La política refleja la normativa colombiana. | Baja |

**Reglas de negocio:** RN-G07

## Matriz de trazabilidad RN → HU → RE

| HU | Reglas de negocio | Requisitos específicos |
|---|---|---|
| HU-17 | RN-G01, RN-G03, RN-G09 | RE-17.1, RE-17.2, RE-17.3, RE-17.4, RE-17.5 |
| HU-39 | RN-G03 | RE-39.1, RE-39.2, RE-39.3 |
| HU-40 | RN-G04 | RE-40.1, RE-40.2, RE-40.3, RE-40.4 |
| HU-41 | RN-G01, RN-G05 | RE-41.1, RE-41.2, RE-41.3 |
| HU-42 | RN-G06, RN-G07 | RE-42.1, RE-42.2, RE-42.3 |
| HU-45 | RN-G02 | RE-45.1, RE-45.2, RE-45.3, RE-45.4 |
| HU-22 | RN-G08, RN-501 | RE-22.1, RE-22.2, RE-22.3, RE-22.4 |
| HU-24 | RN-G05 | RE-24.1, RE-24.2, RE-24.3, RE-24.4 |
| HU-23 | RN-504 | RE-23.1, RE-23.2, RE-23.3, RE-23.4 |
| HU-32 | RN-G01, RN-G02 | RE-32.1, RE-32.2, RE-32.3 |
| HU-33 | RN-G08, RN-501 | RE-33.1, RE-33.2, RE-33.3 |
| HU-36 | RN-G03, RN-G04 | RE-36.1, RE-36.2, RE-36.3 |
| HU-38 | RN-G03 | RE-38.1, RE-38.2, RE-38.3 |
| HU-54 | RN-G08, RN-501, RN-G05 | RE-54.1, RE-54.2, RE-54.3 |
| HU-01 | RN-101, RN-102, RN-106, RN-107 | RE-01.1, RE-01.2, RE-01.3, RE-01.4, RE-01.5 |
| HU-02 | RN-101, RN-103, RN-G06 | RE-02.1, RE-02.2, RE-02.3 |
| HU-03 | RN-105 | RE-03.1, RE-03.2, RE-03.3, RE-03.4 |
| HU-04 | RN-104, RN-105, RN-108 | RE-04.1, RE-04.2, RE-04.3, RE-04.4 |
| HU-05 | RN-201, RN-202, RN-203 | RE-05.1, RE-05.2, RE-05.3, RE-05.4 |
| HU-06 | RN-204, RN-205 | RE-06.1, RE-06.2, RE-06.3, RE-06.4 |
| HU-07 | RN-205 | RE-07.1, RE-07.2, RE-07.3 |
| HU-08 | RN-206 | RE-08.1, RE-08.2, RE-08.3 |
| HU-34 | RN-204, RN-206 | RE-34.1, RE-34.2, RE-34.3 |
| HU-35 | RN-201, RN-G02 | RE-35.1, RE-35.2, RE-35.3 |
| HU-09 | RN-301, RN-306 | RE-09.1, RE-09.2, RE-09.3 |
| HU-10 | RN-303, RN-304, RN-305 | RE-10.1, RE-10.2, RE-10.3, RE-10.4 |
| HU-11 | RN-307 | RE-11.1, RE-11.2, RE-11.3 |
| HU-12 | RN-302 | RE-12.1, RE-12.2, RE-12.3 |
| HU-20 | RN-301 | RE-20.1, RE-20.2, RE-20.3 |
| HU-37 | RN-303, RN-305 | RE-37.1, RE-37.2, RE-37.3 |
| HU-13 | RN-401, RN-402, RN-403, RN-404 | RE-13.1, RE-13.2, RE-13.3, RE-13.4, RE-13.5 |
| HU-14 | RN-405 | RE-14.1, RE-14.2, RE-14.3, RE-14.4 |
| HU-19 | RN-406 | RE-19.1, RE-19.2, RE-19.3 |
| HU-21 | RN-404 | RE-21.1, RE-21.2, RE-21.3, RE-21.4 |
| HU-27 | RN-401 | RE-27.1, RE-27.2, RE-27.3 |
| HU-28 | RN-401, RN-403 | RE-28.1, RE-28.2 |
| HU-29 | RN-405 | RE-29.1, RE-29.2, RE-29.3 |
| HU-30 | RN-402, RN-503 | RE-30.1, RE-30.2, RE-30.3 |
| HU-31 | RN-401, RN-402 | RE-31.1, RE-31.2, RE-31.3 |
| HU-51 | RN-404, RN-405 | RE-51.1, RE-51.2, RE-51.3 |
| HU-15 | RN-G02 | RE-15.1, RE-15.2, RE-15.3, RE-15.4, RE-15.5 |
| HU-25 | RN-101, RN-G06, RN-407 | RE-25.1, RE-25.2, RE-25.3, RE-25.4, RE-25.5 |
| HU-26 | RN-401, RN-402, RN-407 | RE-26.1, RE-26.2, RE-26.3, RE-26.4 |
| HU-46 | RN-104, RN-108, RN-G02 | RE-46.1, RE-46.2, RE-46.3 |
| HU-47 | RN-206, RN-G02 | RE-47.1, RE-47.2, RE-47.3 |
| HU-50 | RN-405, RN-401, RN-G02 | RE-50.1, RE-50.2, RE-50.3, RE-50.4 |
| HU-52 | RN-G02 | RE-52.1, RE-52.2, RE-52.3, RE-52.4 |
| HU-18 | RN-G01 | RE-18.1, RE-18.2, RE-18.3, RE-18.4 |
| HU-16 | RN-501 | RE-16.1, RE-16.2, RE-16.3, RE-16.4 |
| HU-48 | RN-501 | RE-48.1, RE-48.2, RE-48.3 |
| HU-55 | RN-501 | RE-55.1, RE-55.2, RE-55.3 |
| HU-43 | RN-503, RN-501 | RE-43.1, RE-43.2, RE-43.3, RE-43.4 |
| HU-44 | RN-502 | RE-44.1, RE-44.2, RE-44.3 |
| HU-53 | RN-403, RN-303, RN-503 | RE-53.1, RE-53.2, RE-53.3 |
| HU-49 | RN-G07 | RE-49.1, RE-49.2, RE-49.3 |
