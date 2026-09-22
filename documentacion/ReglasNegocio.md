# Reglas de Negocio — Proyecto Zooki

> **Revisión 1.1** · Catálogo de reglas por módulos · SENA ADSO — Ficha 3142784

Este documento reúne las reglas de negocio de Zooki: las políticas, restricciones y cálculos que rigen el comportamiento del dominio (gestión clínica veterinaria), con independencia de la tecnología con que se implementen. Es la base de la que se derivan las Historias de Usuario y los Requisitos específicos. Cadena de trazabilidad: **Regla de Negocio (RN) → Historia de Usuario (HU) → Requisito específico (RE)**.

> _Nota: las reglas de arquitectura y estilo de código (SOLID, DRY, modularidad de CSS/JS, SweetAlert2) se gestionan aparte en el manifiesto técnico del repositorio (`ZOOKI_REGLAS.md`) y no forman parte de este catálogo._

## Convenciones

**Tipos de regla:** `Restricción` (condición que el sistema hace cumplir), `Cálculo` (deriva un valor), `Proceso` (comportamiento automático), `Estructura` (hecho o relación del dominio).

**Estados:** `Aplicada` (se cumple en la versión actual), `Parcial` (se cumple con una excepción que se indica), `Futuro` (evolución prevista), `Por confirmar` (verificar en el código).

## Transversales — Acceso, seguridad y datos

| ID | Regla de negocio | Tipo | Estado |
|---|---|---|---|
| RN-G01 | El acceso al sistema requiere autenticación. Cada usuario tiene exactamente un rol (Administrador, Veterinario, Recepcionista o Propietario) que determina sus permisos (RBAC). | Restricción | Aplicada |
| RN-G02 | Un propietario solo puede consultar información de sus propias mascotas; todo intento de acceder a datos de terceros se rechaza (error 403). | Restricción | Aplicada |
| RN-G03 | Las contraseñas se almacenan cifradas (bcrypt + salt), nunca en texto plano. Ante credenciales inválidas se muestra un mensaje genérico. | Restricción | Aplicada |
| RN-G04 | El enlace de recuperación de contraseña es de un solo uso y expira tras un tiempo definido. | Restricción | Aplicada |
| RN-G05 | Toda operación crítica (inicio/cierre de sesión, creación, edición y eliminación) se registra en auditoría con usuario, fecha e IP. Los registros de auditoría son de solo lectura. | Proceso | Aplicada |
| RN-G06 | El número de documento y el correo electrónico de un usuario o propietario son únicos en el sistema. | Restricción | Aplicada |
| RN-G07 | Los datos personales se tratan conforme a la Ley 1581 de 2012 (protección de datos / habeas data). | Restricción | Aplicada |
| RN-G08 | Los usuarios no se eliminan físicamente: se inactivan (soft-delete). No se puede inactivar ni eliminar al único usuario administrador. | Restricción | Aplicada |
| RN-G09 | El sistema admite autenticación federada con Google (OAuth 2.0) además de las credenciales locales. | Estructura | Aplicada |
| RN-G10 | Toda contraseña del sistema cumple la misma política: mínimo 8 caracteres con mayúscula, minúscula y número, y ademas se rechaza si figura en la lista de contraseñas de uso masivo, si es una secuencia o repetición trivial, o si contiene el documento, el nombre o el correo del titular. Rige en el registro, el restablecimiento, el cambio de contraseña y el alta de usuarios por el administrador. | Restricción | Aplicada |
| RN-G11 | El auto-registro no habilita la cuenta: el correo debe confirmarse mediante un enlace de un solo uso que vence a las 24 horas. Hasta entonces la cuenta no permite iniciar sesión. | Proceso | Aplicada |
| RN-G12 | El inicio de sesión con Google solo acepta tokens emitidos para el client_id de Zooki (`aud`) por un emisor legítimo de Google (`iss`), vigentes y con el correo verificado por Google. | Restricción | Aplicada |

## Módulo 1 — Mascotas y propietarios

| ID | Regla de negocio | Tipo | Estado |
|---|---|---|---|
| RN-101 | Toda mascota debe estar vinculada a un propietario; no puede existir una mascota sin propietario asignado. | Restricción | Aplicada |
| RN-102 | Cada mascota se identifica con un número de historia clínica único, generado por el sistema en su primera consulta y no reutilizable. | Restricción | Aplicada |
| RN-103 | Un propietario puede tener varias mascotas; cada mascota pertenece a un único propietario (titular). | Estructura | Aplicada |
| RN-104 | Una mascota con historial clínico no puede eliminarse; solo puede marcarse como inactiva (fallecida/retirada), conservando su historial. | Restricción | Aplicada |
| RN-105 | Las mascotas inactivas no aparecen en las búsquedas activas por defecto, pero su información permanece. | Restricción | Aplicada |
| RN-106 | La especie y la raza provienen de catálogos; la raza seleccionada debe corresponder a la especie de la mascota. | Restricción | Aplicada |
| RN-107 | Una mascota puede tener registrados varios colores de pelaje. | Estructura | Aplicada |
| RN-108 | Toda modificación de la ficha de una mascota registra automáticamente fecha, usuario y campo modificado (auditoría de mascotas). | Proceso | Aplicada |

## Módulo 2 — Historia clínica

| ID | Regla de negocio | Tipo | Estado |
|---|---|---|---|
| RN-201 | Solo el rol Veterinario puede registrar consultas clínicas. | Restricción | Aplicada |
| RN-202 | Una consulta no puede guardarse sin diagnóstico. | Restricción | Aplicada |
| RN-203 | Cada consulta queda vinculada a una mascota y, opcionalmente, a la cita que la originó; esa cita debe corresponder a la misma mascota. | Estructura | Aplicada |
| RN-204 | Los archivos clínicos permitidos son JPG, PNG y PDF, con un máximo de 10 MB por archivo; se almacenan en carpeta protegida y solo usuarios autenticados y autorizados pueden descargarlos. | Restricción | Aplicada |
| RN-205 | Una consulta puede tener múltiples tratamientos y múltiples archivos adjuntos. | Estructura | Aplicada |
| RN-206 | La historia clínica es acumulativa: los registros no se borran, se conservan en orden cronológico. | Restricción | Aplicada |
| RN-207 | Un acto clínico (consulta, vacunación o desparasitación) solo puede registrarse sobre una mascota existente y activa. Una mascota dada de baja conserva su historia clínica pero no admite registros nuevos. | Restricción | Aplicada |
| RN-208 | La atención clínica no está restringida al veterinario asignado en la cita: cualquier veterinario puede registrar actos clínicos sobre cualquier mascota activa. Se descarta exigir cita previa porque bloquearía urgencias sin cita, cambios de turno y reasignaciones. La trazabilidad se conserva porque cada registro guarda el documento del veterinario que lo realizó. La consulta ligada a una cita sí la registra el veterinario asignado a esa cita (RN-408): si la atiende otro, el administrador reasigna la cita o se usa la atención sin cita. | Estructura | Aplicada |

## Módulo 3 — Vacunación, desparasitación y recordatorios

| ID | Regla de negocio | Tipo | Estado |
|---|---|---|---|
| RN-301 | Cada vacuna registra fecha de aplicación y fecha de próxima dosis; el sistema genera una alerta 7 días antes del vencimiento. | Cálculo | Aplicada |
| RN-302 | La desparasitación tiene una periodicidad configurable (mensual, trimestral o semestral) y la fecha de la próxima aplicación se calcula automáticamente. | Cálculo | Aplicada |
| RN-303 | Los recordatorios de vacunación y desparasitación se envían por correo al propietario 7 días y 1 día antes del vencimiento. | Proceso | Aplicada |
| RN-304 | No se envía recordatorio si el propietario no tiene un correo electrónico registrado. | Restricción | Aplicada |
| RN-305 | Cada envío de recordatorio se registra (fecha, destinatario, tipo); un envío fallido se marca con estado de error y se reintenta hasta 3 veces (RE-37.2). | Proceso | Aplicada |
| RN-306 | El catálogo de vacunas aplica por especie: una vacuna base corresponde a determinadas especies. | Restricción | Aplicada |
| RN-307 | El recordatorio por WhatsApp es un canal adicional previsto para una evolución futura; el canal operativo actual es el correo electrónico. | Proceso | Futuro |

## Módulo 4 — Agenda de citas y portal

| ID | Regla de negocio | Tipo | Estado |
|---|---|---|---|
| RN-401 | No pueden existir dos citas para el mismo veterinario en el mismo horario (no se permiten solapamientos). | Restricción | Parcial: dos reservas simultáneas que se solapan sin empezar a la misma hora no se bloquean (HU-31, diferida) |
| RN-402 | Las citas solo pueden agendarse dentro del horario de atención configurado de la clínica. | Restricción | Parcial: se valida la hora de inicio, no la duración (HU-31, diferida) |
| RN-403 | Cada tipo de cita tiene una duración base que determina el bloque de tiempo que ocupa en la agenda. | Cálculo | Aplicada |
| RN-404 | Al crear una cita se envía una confirmación por correo al propietario de forma inmediata; un fallo en el envío no interrumpe el agendado. | Proceso | Aplicada |
| RN-405 | Una cita no se elimina: cambia de estado (pendiente, confirmada, en curso, completada, cancelada, no asistió, sin cerrar o cerrada sin consulta). Solo una cita pendiente o confirmada se puede cancelar o reprogramar, y al hacerlo se notifica automáticamente al propietario. | Restricción | Aplicada |
| RN-406 | Una cita se completa únicamente al registrar su consulta, en la misma operación: no existe una cita completada sin consulta. Solo se completa una cita en curso o sin cerrar. | Restricción | Aplicada |
| RN-407 | El propietario puede auto-registrarse y agendar citas para sus mascotas desde su portal, respetando las mismas reglas de disponibilidad y horario. | Proceso | Aplicada |
| RN-408 | La atención de una cita la inicia y la cierra solo el veterinario asignado, y solo el día de la cita, desde 15 minutos antes de su hora. Una atención iniciada se puede retomar en cualquier momento hasta registrar su consulta. Administración y recepción no atienden citas. | Restricción | Aplicada |
| RN-409 | Una cita pendiente o confirmada cuya hora ya pasó puede marcarse como "no asistió" por el veterinario asignado. Deja de ocupar su espacio en la agenda y cuenta para el ausentismo. | Proceso | Aplicada |
| RN-410 | Si una atención sigue en curso 10 minutos después de la hora de fin de la cita, el sistema avisa al veterinario asignado por correo y en sus notificaciones, una sola vez. Si al terminar el día de la cita sigue abierta, pasa a "sin cerrar" y se le avisa de nuevo. Una atención sin cerrar se completa registrando su consulta. | Proceso | Aplicada |
| RN-411 | El veterinario asignado puede cerrar sin consulta una atención en curso o sin cerrar, con un motivo obligatorio que queda en auditoría. Pasa a "cerrada sin consulta": no se reabre, no cuenta como inasistencia y libera su horario. | Restricción | Aplicada |

## Módulo 5 — Configuración y catálogos

| ID | Regla de negocio | Tipo | Estado |
|---|---|---|---|
| RN-501 | Solo el rol Administrador puede gestionar usuarios, catálogos y horarios de la clínica. | Restricción | Aplicada |
| RN-502 | Los catálogos (especies, razas, colores, vacunas base, laboratorios y productos de desparasitación) son administrables y alimentan los formularios del sistema. | Estructura | Aplicada |
| RN-503 | Los horarios de la clínica se definen por día de la semana (bloques de mañana y tarde) y condicionan la disponibilidad de la agenda. | Restricción | Aplicada |
| RN-504 | Los respaldos de la base de datos se ejecutan automáticamente cada 24 horas. | Proceso | Aplicada |

## Resumen

El catálogo reúne **48 reglas de negocio**. Frente al sistema actual (v1.9.0): **47 aplicadas** y **1 futura** (RN-307, WhatsApp). A partir de este catálogo se elaboran las Historias de Usuario (cada una referencia las reglas que la condicionan) y de ahí los requisitos específicos.
