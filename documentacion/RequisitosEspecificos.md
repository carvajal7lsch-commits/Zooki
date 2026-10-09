# Requisitos Específicos por Historia de Usuario — Proyecto Zooki

> **Revisión 3.1** · 419 requisitos específicos para 95 historias de usuario · Desglose por módulos · Trazabilidad RN → HU → RE · SENA ADSO — Ficha 3142784

Cada requisito (`RE-<módulo>.<n>.<k>`) incluye su tipo, su prioridad y su **criterio de aceptación** verificable. El identificador del requisito espeja el de su historia (p. ej. los requisitos de `HU-4.13` son `RE-4.13.1`, `RE-4.13.2`…).

**Tipos de requisito:** `Funcional` (lo que el sistema hace), `Validación` (datos de entrada), `Restricción` (límites y reglas que el sistema hace cumplir), `Seguridad` (autenticación, autorización, privacidad y auditoría), `Cálculo` (valores derivados), `Integración` (servicios externos), `Usabilidad`, `Rendimiento`, `Confiabilidad` (reintentos, concurrencia e integridad de datos) y `Verificación` (cómo se comprueba un conjunto de requisitos).

**Criterio de aceptación:** describe una prueba concreta, con su entrada y el resultado esperado, que permite decir si el requisito se cumple.

## Módulo 0 — Plataforma: clínicas, planes y suscripción *(v2.0)*

### HU-0.1 — Registrar una clínica (self-service)

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-0.1.1 | El sistema debe ofrecer un formulario público con los datos de la clínica y del administrador inicial. | Funcional | Se capturan ambos conjuntos de datos. | Alta |
| RE-0.1.2 | El sistema debe validar el formato y la unicidad del correo y el NIT. | Validación | Un correo o NIT ya registrado se rechaza; ante un NIT ya registrado se ofrece reportarlo, lo que crea un caso de soporte. | Alta |
| RE-0.1.3 | El sistema debe validar el NIT con el algoritmo del dígito de verificación de la DIAN. | Validación | Un NIT con dígito de verificación incorrecto se rechaza; uno válido se acepta. | Alta |
| RE-0.1.4 | El formulario público debe exigir superar una verificación anti-bot (CAPTCHA) validada en el servidor. | Seguridad | Un envío sin token anti-bot válido se rechaza aunque los datos sean correctos. | Alta |
| RE-0.1.5 | El sistema debe rechazar correos de dominios desechables según una lista mantenida. | Validación | Un correo de un dominio de la lista se rechaza con un mensaje claro. | Media |
| RE-0.1.6 | El sistema debe admitir como máximo 3 registros de clínicas por día desde una misma IP. | Seguridad | El cuarto registro del día desde la misma IP se rechaza indicando que lo intente más tarde. | Media |
| RE-0.1.7 | El sistema debe crear la clínica en estado «pendiente de verificación» con su usuario administrador. | Funcional | La clínica queda pendiente y no opera. | Alta |
| RE-0.1.8 | El sistema debe enviar un correo de verificación con un enlace temporal. | Funcional | Llega el correo con el enlace. | Alta |
| RE-0.1.9 | El sistema debe registrar el alta en auditoría. | Seguridad | La creación queda en auditoría. | Media |
| RE-0.1.10 | El registro de clínicas no debe ofrecer Google y debe exigir aceptar la política de tratamiento de datos. | Restricción | El formulario no muestra la opción de Google; sin aceptar la política, el registro se rechaza. | Alta |

**Reglas de negocio:** RN-001, RN-002, RN-010, RN-011, RN-G06, RN-G19, RN-G20

### HU-0.2 — Verificar el correo y activar la clínica

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-0.2.1 | El enlace de verificación debe vencer a las 24 horas. | Seguridad | Un enlace usado después de 24 horas se rechaza. | Alta |
| RE-0.2.2 | Al verificar, el sistema debe activar la clínica, asignar el plan gratuito y habilitar el rol administrador. | Funcional | La clínica queda activa con plan gratuito. | Alta |
| RE-0.2.3 | Si el plazo expira, el sistema debe eliminar el registro pendiente. | Funcional | La clínica pendiente se elimina. | Media |
| RE-0.2.4 | Tras activar, el administrador debe iniciar sesión acotado a su id_clinica. | Funcional | Entra al ámbito de su clínica. | Alta |
| RE-0.2.5 | Al activar la clínica, el sistema debe copiarle en la misma transacción los catálogos iniciales por defecto: tipos de cita, horarios de atención, vacunas base con su relación por especie, laboratorios y productos de desparasitación. | Funcional | Una clínica recién activada tiene 6 tipos de cita, 7 días de horario, 15 vacunas base con su relación por especie, 11 laboratorios y 15 productos, todos con su `id_clinica`; si la activación falla no queda ninguna copia parcial, y los catálogos de las demás clínicas no cambian. | Alta |

**Reglas de negocio:** RN-002, RN-003, RN-005, RN-G11

### HU-0.3 — Panel del super-administrador

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-0.3.1 | El sistema debe listar las clínicas con su estado, plan y estado de suscripción. | Funcional | Con tres clínicas registradas (activa, pendiente y suspendida), el listado muestra las tres con su plan y el estado de su suscripción. | Alta |
| RE-0.3.2 | El sistema debe permitir cambiar el plan y el estado de la suscripción de una clínica. | Funcional | Al cambiar una clínica del plan gratuito al profesional, sus límites dejan de aplicarse en la siguiente acción y el cambio queda en auditoría. | Alta |
| RE-0.3.3 | El super-administrador no debe acceder a los datos clínicos internos salvo soporte, que queda auditado. | Seguridad | No ve datos clínicos; el acceso de soporte queda en auditoría. | Alta |
| RE-0.3.4 | El super-administrador debe operar como rol de plataforma, no filtrado por una sola id_clinica. | Seguridad | Opera sobre todas las clínicas. | Alta |
| RE-0.3.5 | El super-administrador debe poder suspender o dar de baja una clínica indicando el motivo. | Funcional | La clínica suspendida no opera y el motivo queda en auditoría. | Media |
| RE-0.3.6 | El super-administrador debe poder ocultar una reseña por moderación indicando el motivo. | Funcional | La reseña oculta no se muestra ni cuenta en el promedio; el motivo queda en auditoría. | Media |

**Reglas de negocio:** RN-004, RN-012, RN-804, RN-G13, RN-G14

### HU-0.4 — Control de límites del plan (freemium)

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-0.4.1 | El sistema debe leer el plan y la suscripción antes de una acción que consume cupo. | Funcional | Se evalúa antes de crear o vincular una mascota y de crear una cita o un usuario. | Alta |
| RE-0.4.2 | Dentro del límite, la acción debe ejecutarse; alcanzado el límite, el sistema debe impedirla e informar el motivo. | Restricción | Al límite, la acción se bloquea con un mensaje. | Alta |
| RE-0.4.3 | El mensaje al propietario debe ser neutral y notificar a la clínica; al personal, indicar el límite y la mejora de plan. | Funcional | Al llegar al límite, el propietario ve «no es posible completar la acción ahora» y la clínica recibe un aviso; el personal ve el límite alcanzado y la opción de mejorar el plan. | Media |
| RE-0.4.4 | El plan gratuito debe incluir las capacidades limitadas por volumen. | Restricción | Las capacidades funcionan dentro del límite. | Media |
| RE-0.4.5 | El límite de mascotas debe contar las mascotas vinculadas a la clínica, incluidas las que ya existían en la plataforma. | Restricción | Vincular una mascota existente consume cupo igual que crear una nueva. | Alta |
| RE-0.4.6 | Una urgencia 🔴 no debe bloquearse por el límite del plan ni por la mora. | Restricción | Con la clínica al límite y en mora, un caso 🔴 se registra y se atiende. | Alta |
| RE-0.4.7 | El plan gratuito debe limitar a 5 mascotas vinculadas, 30 citas por mes y 2 cuentas de personal. | Restricción | La sexta mascota, la cita 31 del mes y la tercera cuenta de personal se rechazan con el mensaje del límite. | Alta |

**Reglas de negocio:** RN-003, RN-005, RN-006, RN-420

### HU-0.5 — Suscripción y congelación por mora

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-0.5.1 | El sistema debe registrar la suscripción mensual o anual, con descuento en la anual. | Funcional | Se registra la periodicidad y el precio. | Media |
| RE-0.5.2 | En mora, el sistema debe aplicar temporalmente los límites del plan gratuito a las acciones nuevas, sin ocultar ni borrar datos. | Restricción | La clínica sigue atendiendo a sus mascotas y consultando su historia; solo se bloquean las altas que superen los límites gratuitos, salvo urgencias rojas. | Alta |
| RE-0.5.3 | El control de vencimiento debe ejecutarse como tarea programada. | Funcional | El cron marca las suscripciones vencidas. | Media |
| RE-0.5.4 | El cobro real por pasarela no debe implementarse en esta versión (queda como RF-F.1). | Restricción | Documentado como requisito futuro (RF-F.1). | Baja |
| RE-0.5.5 | Al bajar al plan gratuito o caer en mora, el sistema no debe ocultar ni borrar datos; solo debe bloquear lo nuevo por encima de los límites del plan gratuito. | Restricción | Una clínica con 40 mascotas que baja al plan gratuito sigue atendiéndolas, pero no puede vincular una nueva. | Alta |

**Reglas de negocio:** RN-007, RN-008, RN-013

## Módulo T — Acceso, seguridad y administración

### HU-T.1 — Iniciar sesión y autenticación

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-T.1.1 | El sistema debe autenticar al usuario por documento o correo y contraseña contra la base de datos. | Funcional | Con credenciales válidas se concede el acceso; con inválidas se niega. | Alta |
| RE-T.1.2 | El sistema debe almacenar las contraseñas con hash bcrypt + salt. | Seguridad | La contraseña en la base de datos aparece como hash, no legible. | Alta |
| RE-T.1.3 | El sistema debe permitir el inicio de sesión con Google (OAuth 2.0). | Integración | El acceso con Google inicia sesión y asocia la cuenta. | Media |
| RE-T.1.4 | El sistema debe redirigir al panel correspondiente según el rol. | Funcional | Cada rol aterriza en su panel tras autenticarse. | Alta |
| RE-T.1.5 | El sistema debe mostrar un mensaje genérico ante credenciales inválidas. | Seguridad | Con usuario o contraseña incorrectos se muestra el mismo mensaje. | Media |

**Reglas de negocio:** RN-G01, RN-G03, RN-G09

### HU-T.2 — Cambiar mi contraseña

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-T.2.1 | El sistema debe solicitar la contraseña actual, salvo en cuentas creadas con Google. | Seguridad | Sin la contraseña actual correcta (cuenta local), el cambio se rechaza. | Alta |
| RE-T.2.2 | El sistema debe exigir que la nueva contraseña cumpla la política mínima. | Validación | Una nueva contraseña que no cumple la política es rechazada. | Alta |
| RE-T.2.3 | El sistema debe actualizar el hash y confirmar el cambio. | Funcional | Tras el cambio se puede iniciar sesión con la nueva contraseña. | Alta |
| RE-T.2.4 | _(v2.0)_ En una cuenta sin contraseña, el sistema debe ofrecer «Crear contraseña» y exigir confirmar la identidad con Google antes de guardarla. | Seguridad | Con una sesión abierta pero sin confirmar con Google, la contraseña no se crea; tras confirmarla, se puede entrar con ambos medios. | Alta |
| RE-T.2.5 | _(v2.0)_ Al cambiar o crear la contraseña, el sistema debe cerrar las demás sesiones abiertas de la cuenta y avisar por correo. | Seguridad | Una sesión abierta en otro navegador queda cerrada tras el cambio, y llega el aviso al correo. | Alta |

**Reglas de negocio:** RN-G03, RN-G22

### HU-T.3 — Recuperar contraseña olvidada

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-T.3.1 | El sistema debe permitir solicitar el restablecimiento con el correo y responder de forma genérica. | Seguridad | La respuesta no revela si el correo existe. | Alta |
| RE-T.3.2 | El sistema debe enviar un enlace con un token temporal. | Funcional | Llega un correo con el enlace de restablecimiento. | Alta |
| RE-T.3.3 | El enlace debe ser de un solo uso y expirar a la hora de emitido. | Seguridad | Un enlace usado o vencido es rechazado. | Alta |
| RE-T.3.4 | El sistema debe permitir definir una nueva contraseña válida. | Funcional | Se puede establecer una contraseña que cumple la política. | Alta |

**Reglas de negocio:** RN-G04

### HU-T.4 — Cerrar sesión

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-T.4.1 | El sistema debe destruir la sesión activa al cerrar sesión. | Funcional | Tras cerrar, no queda sesión activa. | Alta |
| RE-T.4.2 | El sistema debe exigir re-autenticación en rutas protegidas tras el cierre. | Seguridad | Acceder a una ruta protegida tras cerrar pide login. | Alta |
| RE-T.4.3 | El sistema debe registrar el cierre de sesión en auditoría. | Funcional | Tras cerrar sesión, la auditoría tiene una entrada con el usuario, la fecha y la IP. | Baja |

**Reglas de negocio:** RN-G01, RN-G05

### HU-T.5 — Ver y actualizar mi perfil y datos de contacto

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-T.5.1 | El sistema debe mostrar los datos del perfil del usuario. | Funcional | El usuario ve sus datos actuales. | Media |
| RE-T.5.2 | El sistema debe permitir actualizar teléfono y correo. | Funcional | Los cambios se guardan y se reflejan. | Media |
| RE-T.5.3 | El sistema debe validar la unicidad del nuevo correo. | Validación | Un correo ya usado por otra cuenta es rechazado. | Media |
| RE-T.5.4 | El sistema debe mostrar la actividad reciente de la cuenta: accesos, intentos fallidos y cambios, con fecha en hora de la clínica e IP. | Seguridad | Solo aparecen eventos de la propia cuenta y los intentos fallidos se destacan. | Media |
| RE-T.5.5 | El sistema debe registrar en auditoría el cambio de contraseña, sin guardar la contraseña. | Seguridad | Tras cambiarla, el evento aparece en la actividad. | Alta |
| RE-T.5.6 | El formulario de contraseña debe mostrar los requisitos de la política mientras se escribe y permitir ver lo escrito. | Usabilidad | Cada requisito se marca al cumplirse y la confirmación avisa si no coincide. | Baja |
| RE-T.5.7 | _(v2.0)_ Cambiar el correo debe exigir confirmar la identidad, y una cuenta sin contraseña debe crearla antes. El correo nuevo debe aplicarse solo tras verificarlo; el anterior debe recibir un aviso y la vinculación con Google del correo anterior debe retirarse. | Seguridad | Hasta verificar, se sigue entrando con el correo anterior; tras verificar, el correo anterior recibe el aviso. | Alta |
| RE-T.5.8 | _(v2.0)_ El titular debe poder corregir su documento confirmando su identidad; el sistema debe validar formato y unicidad, auditar el valor anterior y avisar por correo. | Seguridad | Un documento que ya usa otra cuenta no se aplica y se crea el caso para soporte; uno válido se aplica y queda en auditoría. | Alta |
| RE-T.5.9 | _(v2.0)_ El veterinario debe poder editar su bio y su foto públicas, pero no sus especialidades. | Seguridad | El perfil público muestra la bio nueva; intentar cambiar las especialidades devuelve 403. | Media |

**Reglas de negocio:** RN-G05, RN-G06, RN-G07, RN-G23, RN-G24, RN-705

### HU-T.6 — Gestionar mis notificaciones internas

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-T.6.1 | El sistema debe mostrar el contador de notificaciones no leídas. | Funcional | El contador refleja las no leídas. | Media |
| RE-T.6.2 | El sistema debe listar las notificaciones recientes. | Funcional | Se ven las últimas notificaciones del usuario. | Media |
| RE-T.6.3 | El sistema debe permitir marcar una o todas como leídas. | Funcional | Al marcar, el contador disminuye. | Media |
| RE-T.6.4 | El sistema debe mostrar solo las notificaciones del rol/usuario. | Seguridad | No se ven notificaciones dirigidas a otros. | Media |

**Reglas de negocio:** RN-G02, RN-409

### HU-T.7 — Gestión de usuarios del sistema

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-T.7.1 | El sistema debe permitir crear, editar y desactivar usuarios (nombre, correo, rol, estado). | Funcional | Crear, editar y desactivar un usuario persiste el cambio. | Media |
| RE-T.7.2 | El sistema debe restringir el módulo de usuarios al rol administrador. | Restricción | Un usuario no administrador recibe 403. | Alta |
| RE-T.7.3 | El sistema debe impedir eliminar o inactivar al único administrador. | Restricción | Intentar eliminar/inactivar el último admin es rechazado. | Media |
| RE-T.7.4 | El sistema debe validar la unicidad del correo del usuario. | Validación | Registrar un correo ya existente muestra error. | Media |
| RE-T.7.5 | _(v2.0)_ Al dar de alta personal, si el documento o el correo ya existen en la plataforma, el sistema debe asignar el rol en la clínica a esa persona en lugar de crear otra cuenta. | Funcional | Un veterinario que ya es propietario o trabaja en otra clínica conserva una sola cuenta con los dos roles. | Alta |

**Reglas de negocio:** RN-G08, RN-701, RN-G06, RN-705

### HU-T.8 — Logs de auditoría y seguridad

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-T.8.1 | El sistema debe registrar login exitoso/fallido y las operaciones CRUD. | Funcional | Login y operaciones CRUD generan entradas de auditoría. | Media |
| RE-T.8.2 | El sistema debe almacenar usuario, fecha/hora, IP, operación, tabla y datos previos/nuevos. | Funcional | Cada entrada contiene todos esos campos. | Media |
| RE-T.8.3 | El sistema debe ofrecer una vista de logs filtrable. | Funcional | La vista filtra por usuario, operación y fechas. | Media |
| RE-T.8.4 | El sistema debe mantener los registros como solo lectura. | Restricción | No hay opción de editar o borrar la auditoría. | Media |

**Reglas de negocio:** RN-G05

### HU-T.9 — Backup automático de la base de datos

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-T.9.1 | El sistema debe volcar la base de datos y comprimir el archivo. | Funcional | Se genera un respaldo comprimido y no vacío que se puede restaurar en una base vacía. | Media |
| RE-T.9.2 | El sistema debe programar el respaldo cada 24 horas con una tarea programada. | Integración | La tarea (Schedule de Dokploy en producción) ejecuta el respaldo diariamente. | Media |
| RE-T.9.3 | El sistema debe almacenar el respaldo en un directorio externo. | Restricción | El respaldo queda en el directorio externo configurado. | Media |
| RE-T.9.4 | El sistema debe retener/rotar los últimos respaldos. | Funcional | Los respaldos antiguos se eliminan según la rotación. | Baja |

**Reglas de negocio:** RN-704

### HU-T.10 — Autorización central por rol (RBAC real)

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-T.10.1 | El sistema debe aplicar una matriz acción → roles permitidos antes de ejecutar cualquier acción. | Seguridad | Una acción sin rol permitido no se ejecuta. | Alta |
| RE-T.10.2 | El sistema debe responder 403 al rol sin permiso. | Seguridad | El acceso indebido devuelve 403. | Alta |
| RE-T.10.3 | El control de rol debe cubrir todos los endpoints AJAX. | Seguridad | Ningún endpoint queda sin control de rol. | Alta |

**Reglas de negocio:** RN-G01, RN-G02

### HU-T.11 — Corregir escalada de privilegios y proteger al último admin

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-T.11.1 | El sistema debe exigir rol administrador para crear/editar usuarios. | Seguridad | Un no administrador no puede crear ni editar usuarios. | Alta |
| RE-T.11.2 | El sistema debe validar el rol asignado contra una lista permitida. | Seguridad | No se puede asignar un rol fuera de la lista permitida. | Alta |
| RE-T.11.3 | El sistema debe impedir desactivar/eliminar al último administrador activo. | Restricción | Desactivar el último admin es rechazado. | Alta |

**Reglas de negocio:** RN-G08, RN-701

### HU-T.12 — Política de contraseñas, verificación de correo y OAuth seguro

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-T.12.1 | El sistema debe aplicar una política única (mínimo 8 + complejidad) en todos los flujos. | Seguridad | Una contraseña débil se rechaza en cualquier flujo. | Media |
| RE-T.12.2 | El sistema debe verificar el correo en el registro antes de activar la cuenta. | Seguridad | La cuenta se activa solo tras verificar el correo. | Media |
| RE-T.12.3 | El sistema debe validar aud/iss del token de Google contra el client_id propio. | Seguridad | Un token emitido para otra app es rechazado. | Media |

**Reglas de negocio:** RN-G03, RN-G04, RN-G10, RN-G11, RN-G12

### HU-T.13 — Endurecer rate limiting y reducir enumeración/fuga

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-T.13.1 | El sistema debe contar los intentos del lado servidor por IP/cuenta. | Seguridad | El límite no se evade descartando la cookie. | Media |
| RE-T.13.2 | El sistema no debe revelar la existencia de documentos/correos. | Seguridad | No se puede enumerar cuentas por estas consultas. | Media |
| RE-T.13.3 | El sistema debe mostrar errores genéricos al cliente. | Seguridad | El cliente no recibe detalles técnicos del error. | Media |
| RE-T.13.4 | Tras 5 intentos fallidos en 15 minutos desde una IP, el sistema debe bloquear temporalmente esa IP con un mensaje genérico. | Seguridad | El sexto intento desde la IP dentro de la ventana se rechaza aunque la contraseña sea correcta; el mensaje no revela si la cuenta existe. | Alta |
| RE-T.13.5 | _(v2.0)_ Tras 5 intentos fallidos sobre una misma cuenta, el sistema debe exigir el CAPTCHA en lugar de bloquear la cuenta. | Seguridad | Desde otra IP, el titular entra con su contraseña correcta superando el CAPTCHA; sin CAPTCHA válido el intento se rechaza. | Alta |

**Reglas de negocio:** RN-G03, RN-G15

### HU-T.14 — Restablecer la contraseña de un usuario (administrador)

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-T.14.1 | El sistema debe permitir al administrador generar un restablecimiento para un usuario. | Funcional | El administrador dispara el restablecimiento. | Media |
| RE-T.14.2 | El sistema debe forzar el cambio de contraseña en el próximo ingreso del usuario. | Seguridad | El usuario debe cambiarla al ingresar. | Media |
| RE-T.14.3 | El sistema debe permitirlo solo al administrador y registrarlo en auditoría. | Seguridad | Solo el administrador; la acción queda en auditoría. | Media |

**Reglas de negocio:** RN-G08, RN-701, RN-G05

### HU-T.15 — Aislamiento por clínica y rol super-administrador

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-T.15.1 | Toda operación de negocio debe filtrar por id_clinica. | Seguridad | Ninguna acción devuelve datos de otra clínica. | Alta |
| RE-T.15.2 | El acceso a un recurso de otra clínica debe rechazarse con HTTP 403 y quedar en auditoría. | Seguridad | Un intento cruzado da 403 y se audita. | Alta |
| RE-T.15.3 | Security debe validar rol, CSRF y clínica en cada petición. | Seguridad | Una petición sin permiso o clínica se rechaza. | Alta |
| RE-T.15.4 | El super-administrador debe ser la única excepción, sin acceso a datos clínicos salvo soporte. | Seguridad | El super-admin no entra a datos clínicos. | Alta |
| RE-T.15.5 | El aislamiento debe centralizarse y cubrirse con pruebas. | Verificación | Las pruebas verifican el filtro por id_clinica. | Alta |

**Reglas de negocio:** RN-G13, RN-G14, RN-001, RN-004

### HU-T.16 — Expiración de la sesión por inactividad

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-T.16.1 | El sistema debe cerrar la sesión tras 30 minutos sin actividad. | Seguridad | Tras 30 minutos sin peticiones, la siguiente acción redirige al inicio de sesión con el motivo. | Alta |
| RE-T.16.2 | Una petición AJAX con la sesión vencida debe recibir 401 y la interfaz debe redirigir al inicio de sesión. | Funcional | La respuesta es 401 y no un HTML de login embebido. | Media |
| RE-T.16.3 | La cookie de sesión debe ser HttpOnly, SameSite=Lax y Secure bajo HTTPS, y el identificador debe regenerarse al iniciar sesión. | Seguridad | Las cabeceras Set-Cookie muestran los tres atributos; el id cambia tras el login. | Alta |
| RE-T.16.4 | El cierre por inactividad debe registrarse en auditoría. | Seguridad | Queda la entrada con usuario, fecha e IP. | Media |

**Reglas de negocio:** RN-G17, RN-G05

### HU-T.17 — Una persona con varios roles: elegir el contexto al iniciar sesión

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-T.17.1 | Una persona debe tener una sola identidad con varios roles: propietario y, por clínica, administrador o veterinario. | Funcional | Un veterinario que tiene perro usa la misma cédula y el mismo correo para ambos roles. | Alta |
| RE-T.17.2 | Con más de un contexto, el sistema debe pedir elegir el contexto al iniciar sesión; con uno solo, entrar directo. | Funcional | Se ofrecen el portal y cada clínica con su rol. | Alta |
| RE-T.17.3 | El cambio de contexto debe aplicar los permisos y la clínica del nuevo contexto sin volver a iniciar sesión. | Seguridad | Desde el portal de propietario no se accede a acciones de personal (403). | Alta |
| RE-T.17.4 | Una persona no debe poder calificarse a sí misma. | Restricción | Un veterinario que atendió a su propio perro no ve la encuesta de esa cita. | Media |
| RE-T.17.5 | El super-administrador no debe combinarse con roles de clínica. | Seguridad | No se puede asignar un rol de clínica a un super-administrador. | Alta |

**Reglas de negocio:** RN-G01, RN-G06, RN-G13, RN-G18

### HU-T.18 — Registrarme e iniciar sesión con Google (propietario)

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-T.18.1 | El registro con Google debe ofrecerse solo a propietarios, desde el enlace o QR de una clínica o eligiéndola de la lista. | Restricción | El formulario de registro de clínica no muestra la opción de Google. | Alta |
| RE-T.18.2 | Con un correo nuevo, el sistema debe exigir aceptar la política antes de crear la cuenta y vincularla a la clínica elegida; luego guarda el nombre, correo y foto de Google sin pedir nueva verificación del correo. | Funcional | Sin aceptación no existen cuenta ni vínculo; tras aceptarla, la cuenta existe en la clínica elegida y tiene una prueba en `consentimientos_datos`. | Alta |
| RE-T.18.3 | Mientras el perfil esté incompleto, el sistema debe exigir documento y teléfono e impedir registrar mascotas o agendar. | Restricción | Con el perfil incompleto, intentar agendar lleva a la pantalla de completar perfil. | Alta |
| RE-T.18.4 | Si el correo ya tiene cuenta, el sistema debe vincular Google a esa cuenta y verificarla si estaba pendiente; al verificar una cuenta pendiente debe anular su contraseña y volver a pedir sus datos. | Seguridad | No se crea una segunda cuenta; una cuenta pendiente queda verificada, la contraseña con la que se creó ya no sirve y el perfil queda por completar. | Alta |

**Reglas de negocio:** RN-G20, RN-G21, RN-G19, RN-G12, RN-109

### HU-T.19 — Aceptar la política de tratamiento de datos

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-T.19.1 | El registro por formulario, Google y el alta presencial de propietarios exige aceptación expresa antes de crear la cuenta. Excepción: el personal nuevo queda pendiente e inerte, sin contraseña ni consentimiento, y recibe un enlace de 72 horas para aceptar la política y crear su contraseña. | Restricción | Sin aceptación no se crea una cuenta de propietario. El personal no puede aceptar por el titular: la cuenta pendiente no inicia sesión y solo se activa al aceptar y crear la contraseña; si vence la invitación, se elimina únicamente si no tiene otros vínculos. | Alta |
| RE-T.19.2 | El sistema debe guardar la prueba de la aceptación: usuario, versión, medio, fecha e IP, salvo la cuenta de super-administrador. | Seguridad | Cada cuenta nueva habilitada tiene su prueba en `consentimientos_datos`; el personal pendiente la registra al activar. El super-administrador está exento de aceptación y de esta prueba por ser una cuenta operativa de plataforma. | Alta |
| RE-T.19.3 | Si la política cambia de versión, el sistema debe pedir aceptarla en el siguiente inicio de sesión antes de continuar. | Restricción | Tras publicar una versión nueva, el usuario no entra al panel hasta aceptarla. | Media |
| RE-T.19.4 | Revocar la autorización debe llevar a la solicitud de eliminación de la cuenta. | Funcional | La opción de revocar abre el flujo de HU-5.14. | Media |

**Reglas de negocio:** RN-G19, RN-G07, RN-G16

## Módulo 1 — Mascotas y propietarios

### HU-1.1 — Registrar mascota

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-1.1.1 | El sistema debe exigir nombre, especie, raza, fecha de nacimiento, peso, sexo y color; si se conoce, registra si está esterilizada, porque el triage lo usa. | Funcional | Guardar sin un campo obligatorio muestra error. | Alta |
| RE-1.1.2 | El sistema debe permitir subir una fotografía JPG/PNG validando tipo y tamaño. | Validación | Un archivo no permitido o excedido es rechazado. | Media |
| RE-1.1.3 | El sistema debe impedir guardar una mascota sin propietario. | Restricción | Guardar sin propietario es rechazado. | Alta |
| RE-1.1.4 | El sistema debe validar los campos en cliente y servidor. | Validación | La validación falla en cliente y en servidor. | Alta |
| RE-1.1.5 | El sistema debe reflejar la mascota en el listado y la búsqueda. | Funcional | La mascota creada aparece en listado y búsqueda. | Alta |
| RE-1.1.6 | Si el propietario ya tiene mascotas en la plataforma, el sistema debe ofrecer vincular una existente antes de registrar una nueva (HU-1.5). | Funcional | Al registrar para un propietario con mascotas, se muestra primero la lista para vincular. | Alta |

**Reglas de negocio:** RN-101, RN-102, RN-106, RN-107, RN-110, RN-111

### HU-1.2 — Registrar propietario

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-1.2.1 | El sistema debe exigir nombre, tipo y número de documento, teléfono y correo. | Funcional | Faltar un campo obligatorio impide guardar. | Alta |
| RE-1.2.2 | El sistema debe validar la unicidad del documento y del correo; una cuenta existente se busca por coincidencia exacta y se vincula previa confirmación del titular por correo (RN-109). | Validación | Un duplicado no crea otra cuenta. Solicitar vinculación no cambia la identidad ni crea el vínculo; el enlace válido, vigente y de un solo uso crea el vínculo exclusivamente con la clínica solicitada. | Alta |
| RE-1.2.3 | El sistema debe listar las mascotas del propietario en su perfil. | Funcional | El perfil lista todas sus mascotas. | Media |

**Reglas de negocio:** RN-101, RN-103, RN-109, RN-G06

### HU-1.3 — Buscar paciente

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-1.3.1 | El sistema debe buscar por nombre de mascota, propietario o documento. | Funcional | Buscar por cada criterio retorna la mascota esperada. | Alta |
| RE-1.3.2 | El sistema debe responder en menos de 2 s con mínimo 3 caracteres. | Rendimiento | Con 3+ caracteres los resultados llegan en menos de 2 s. | Alta |
| RE-1.3.3 | El sistema debe mostrar nombre, especie, propietario y miniatura. | Funcional | Cada resultado muestra esos datos. | Media |
| RE-1.3.4 | El sistema debe excluir las inactivas por defecto. | Restricción | Una mascota inactiva no aparece por defecto. | Media |

**Reglas de negocio:** RN-105

### HU-1.4 — Editar y desactivar mascota

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-1.4.1 | El sistema debe permitir editar la ficha; la especie, la raza, el sexo y la fecha de nacimiento solo los cambian el propietario o la clínica que registró la mascota. | Funcional | Una clínica vinculada que no la registró puede cambiar el peso, pero no la especie (403). | Alta |
| RE-1.4.2 | El sistema debe registrar en auditoría fecha, usuario y campo modificado. | Funcional | El cambio genera una entrada de auditoría. | Media |
| RE-1.4.3 | El sistema debe ocultar de búsquedas activas a las inactivas conservando su historial. | Restricción | La inactiva desaparece pero su historial se consulta. | Media |
| RE-1.4.4 | El sistema debe impedir la eliminación física con historial clínico. | Restricción | Eliminar una mascota con historial es rechazado. | Alta |
| RE-1.4.5 | _(v2.0)_ Todo cambio en la ficha debe notificarse al propietario. | Funcional | El propietario recibe el aviso con el campo cambiado y la clínica que lo hizo. | Media |

**Reglas de negocio:** RN-104, RN-105, RN-108, RN-110

### HU-1.5 — Vincular una mascota existente a la clínica

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-1.5.1 | El sistema debe mostrar las mascotas que el propietario tiene en la plataforma con nombre, especie, raza y foto, sin datos clínicos. | Seguridad | La lista no muestra consultas, diagnósticos ni archivos. | Alta |
| RE-1.5.2 | Al vincular, la mascota debe conservar su ficha, carnet y QR; el número de historia clínica de la clínica se genera en su primera consulta allí. | Funcional | No se crea una mascota nueva; tras la primera consulta aparece su número de HC en la clínica. | Alta |
| RE-1.5.3 | La vinculación debe contar para el límite del plan y registrarse en auditoría. | Restricción | Al límite, la vinculación se impide (HU-0.4); si procede, queda en auditoría. | Alta |

**Reglas de negocio:** RN-003, RN-102, RN-110, RN-111

## Módulo 2 — Historia clínica y soporte a la decisión clínica (Grafo I)

### HU-2.1 — Registrar consulta clínica

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-2.1.1 | El sistema debe registrar fecha/hora, motivo, anamnesis, examen físico, diagnóstico y plan. | Funcional | La consulta conserva todos los campos. | Alta |
| RE-2.1.2 | El sistema debe generar un número de HC único en la primera consulta. | Funcional | La primera consulta genera un N.° de HC único. | Alta |
| RE-2.1.3 | El sistema debe impedir guardar una consulta sin diagnóstico. | Restricción | Guardar sin diagnóstico es rechazado. | Alta |
| RE-2.1.4 | El sistema debe vincular la consulta a la mascota y mostrarla en su historial. | Funcional | La consulta aparece en el historial cronológico. | Alta |
| RE-2.1.5 | _(v2.0)_ El veterinario debe poder registrar y desactivar alergias y alertas médicas de la mascota (alergia, condición crónica, medicación continua), enlazadas al nodo del Grafo I cuando exista. | Funcional | Una alerta registrada aparece en la ficha de toda clínica vinculada y en el carnet; al desactivarla deja de mostrarse, pero queda en el historial. | Alta |

**Reglas de negocio:** RN-201, RN-202, RN-203, RN-113

### HU-2.2 — Atención clínica sin cita (urgencias)

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-2.2.1 | El sistema debe ofrecer en Consultas un acceso «Atención sin cita». | Funcional | Permite registrar una consulta sin cita asociada. | Media |
| RE-2.2.2 | El sistema debe permitir buscar al paciente y exigir los mismos datos que una consulta normal. | Funcional | Exige motivo y diagnóstico. | Alta |
| RE-2.2.3 | El sistema debe asignar el número de historia clínica si es la primera consulta. | Funcional | Se genera el N.º de HC la primera vez. | Alta |
| RE-2.2.4 | El sistema debe distinguir en el listado las consultas registradas sin cita. | Funcional | Una consulta registrada sin cita aparece en el listado con la etiqueta «Sin cita»; una ligada a cita, sin ella. | Baja |

**Reglas de negocio:** RN-102, RN-203, RN-208

### HU-2.3 — Adjuntar archivos clínicos

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-2.3.1 | El sistema debe aceptar JPG, PNG y PDF de hasta 10 MB. | Validación | Un archivo mayor o de tipo no permitido es rechazado. | Alta |
| RE-2.3.2 | El sistema debe almacenar los archivos en carpeta protegida. | Seguridad | El acceso directo por URL es bloqueado. | Alta |
| RE-2.3.3 | El sistema debe servir descargas solo a usuarios autorizados. | Seguridad | Sin sesión válida, la descarga devuelve 401/403. | Alta |
| RE-2.3.4 | El sistema debe permitir múltiples archivos por consulta. | Funcional | Se adjuntan y listan varios archivos. | Media |

**Reglas de negocio:** RN-204, RN-205

### HU-2.4 — Registrar tratamiento

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-2.4.1 | El sistema debe registrar medicamento, dosis, vía, duración y observaciones. | Funcional | El tratamiento conserva todos esos datos. | Alta |
| RE-2.4.2 | El sistema debe vincular cada tratamiento a su consulta. | Funcional | El tratamiento aparece vinculado a la consulta. | Alta |
| RE-2.4.3 | El sistema debe permitir múltiples tratamientos por consulta. | Funcional | Se registran varios tratamientos en una consulta. | Media |

**Reglas de negocio:** RN-205

### HU-2.5 — Ver historial clínico completo

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-2.5.1 | El sistema debe mostrar el historial cronológico descendente con acordeón. | Funcional | El historial se ve del más reciente al más antiguo. | Media |
| RE-2.5.2 | Cada entrada debe mostrar fecha, motivo, diagnóstico, tratamientos y archivos. | Funcional | Cada entrada muestra esos elementos. | Media |
| RE-2.5.3 | El historial debe cargar en menos de 3 s para hasta 100 consultas. | Rendimiento | 100 consultas cargan en menos de 3 s. | Media |
| RE-2.5.4 | _(v2.0)_ El historial debe incluir lo registrado por otras clínicas según lo autorizado por el propietario (HU-2.10). | Funcional | Los registros de otras clínicas aparecen marcados con su clínica. | Alta |

**Reglas de negocio:** RN-206, RN-113

### HU-2.6 — Atomicidad y feedback de adjuntos en el registro de consulta

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-2.6.1 | El sistema debe guardar consulta, HC, archivos y tratamientos en una transacción. | Confiabilidad | Si algo falla, no queda una consulta parcial. | Alta |
| RE-2.6.2 | El sistema debe informar por cada adjunto rechazado y su motivo. | Usabilidad | El usuario ve qué archivo se rechazó y por qué. | Alta |
| RE-2.6.3 | El sistema no debe reportar éxito con datos parciales. | Confiabilidad | El éxito solo se reporta si todo se guardó. | Alta |

**Reglas de negocio:** RN-204, RN-206

### HU-2.7 — Validación y control de acceso en registros clínicos y de mascota

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-2.7.1 | El sistema debe exigir rol clínico para registrar consulta/vacuna/desparasitación. | Seguridad | Un no clínico no puede registrar. | Alta |
| RE-2.7.2 | El sistema debe validar que la mascota exista. | Validación | Un id de mascota inexistente es rechazado. | Alta |
| RE-2.7.3 | El sistema debe verificar el permiso del actor sobre la mascota. | Seguridad | No se registra sobre mascotas sin permiso. | Alta |

**Reglas de negocio:** RN-201, RN-207, RN-208, RN-G02

### HU-2.8 — Sugerencia de diagnósticos probables por síntomas

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-2.8.1 | Con los síntomas y el contexto del paciente, el Grafo I debe ordenar los diagnósticos probables por peso. | Funcional | Se muestran diagnósticos rankeados. | Alta |
| RE-2.8.2 | El sistema debe permitir aceptar un diagnóstico sugerido o escribir el propio. | Funcional | El veterinario decide el diagnóstico. | Alta |
| RE-2.8.3 | Si el grafo no encuentra coincidencias, el sistema debe permitir escribir el diagnóstico libremente, y debe exigirlo. | Validación | Sin diagnóstico no se continúa. | Alta |
| RE-2.8.4 | La sugerencia debe ser de apoyo y no diagnosticar por sí sola. | Restricción | La decisión final es del veterinario. | Alta |
| RE-2.8.5 | El conocimiento clínico debe ser global y compartido entre clínicas. | Funcional | El grafo no pertenece a una clínica. | Media |

**Reglas de negocio:** RN-209, RN-210, RN-212, RN-202

### HU-2.9 — Alertas de toxicidad e interacción al prescribir

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-2.9.1 | Al prescribir, el Grafo I debe cruzar el fármaco con la especie, la raza, las alergias y la medicación vigente, incluidas las registradas por otras clínicas. | Funcional | Se evalúa en cada prescripción. | Alta |
| RE-2.9.2 | Una alergia registrada del paciente a ese fármaco o una contraindicación por especie o raza (toxicidad) debe bloquear la prescripción. | Restricción | Una prescripción tóxica o de un fármaco al que el paciente es alérgico se bloquea. | Alta |
| RE-2.9.3 | Una interacción con la medicación vigente debe advertirse; el veterinario confirma o cambia y queda en auditoría. Si el tratamiento que origina la alerta pertenece a otra clínica y no hay autorización de lectura, el motor lo evalúa internamente pero solo emite un aviso genérico; antes de confirmar, el veterinario documenta la conciliación con el propietario o solicita autorización. | Funcional | La alerta detecta la interacción aun sin autorización; ni interfaz ni respuesta AJAX revelan el tratamiento protegido, y la verificación y la decisión quedan en auditoría. | Alta |
| RE-2.9.4 | El soporte debe ser de apoyo y no recetar por sí solo. | Restricción | La decisión final es del veterinario. | Alta |

**Reglas de negocio:** RN-209, RN-211, RN-212, RN-113

### HU-2.10 — Ver la historia compartida de la mascota (multi-clínica)

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-2.10.1 | El sistema debe mostrar siempre los datos básicos, las alergias y alertas, y las vacunas y desparasitaciones de todas las clínicas, indicando la clínica de cada registro. | Funcional | Una vacuna aplicada en otra clínica aparece con el nombre de esa clínica. | Alta |
| RE-2.10.2 | Las consultas, tratamientos y archivos de otra clínica solo deben mostrarse si el propietario autorizó a la clínica que consulta. | Seguridad | Sin autorización no se ve su contenido, ni por la interfaz ni pidiéndolo directamente al servidor (403). | Alta |
| RE-2.10.3 | Sin autorización, el sistema no debe revelar que existe historia en otras clínicas. | Seguridad | Ni la interfaz ni la respuesta del servidor incluyen ni mencionan consultas, tratamientos o archivos de otras clínicas; la autorización la da el propietario desde su portal (HU-5.12). | Media |
| RE-2.10.4 | Los registros de otras clínicas deben ser de solo lectura. | Restricción | Intentar modificar un registro de otra clínica se rechaza (403). | Alta |

**Reglas de negocio:** RN-112, RN-113

### HU-2.11 — Calcular el nivel de triage a partir de los síntomas

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-2.11.1 | El formulario debe capturar los síntomas con una lista para marcar, un texto libre opcional y el tiempo desde el inicio o desde la ingesta. | Funcional | No se puede calcular el nivel sin indicar al menos un síntoma o el texto, y el tiempo. | Alta |
| RE-2.11.2 | El nivel final debe ser el máximo de los síntomas y combinaciones; los datos tranquilizadores no deben bajarlo. | Cálculo | T-04 y T-18 de la batería dan el nivel esperado. | Alta |
| RE-2.11.3 | Las combinaciones y los modificadores de raza, edad, estado, historial y tiempo deben cambiar el nivel. | Cálculo | Los pares T-09/T-10, T-11/T-12 y T-14/T-16 dan niveles distintos. | Alta |
| RE-2.11.4 | Las señales de alarma universales deben dar 🔴 en cualquier especie. | Restricción | T-21 (loro con dificultad respiratoria) da 🔴. | Alta |
| RE-2.11.5 | Sin cobertura o sin síntomas reconocidos, el cálculo automático debe dar 🟡 con la marca «revisión del personal»; la revisión debe ocurrir antes de asignar espacio cuando el relato sugiera urgencia. | Restricción | T-20 da 🟡 y se deriva a revisión inmediata antes de agendar; T-22 da 🟡 con revisión. Un veterinario podrá ajustar el nivel con motivo conforme a RN-419. | Alta |
| RE-2.11.6 | Ante una posible enfermedad contagiosa, el sistema debe avisar a la clínica. | Funcional | T-11 genera el aviso de aislamiento. | Media |
| RE-2.11.7 | La batería de casos de triage debe ejecutarse como prueba automatizada del cálculo. | Verificación | Los 26 casos clínicos coinciden con su nivel esperado o la diferencia queda justificada. | Alta |

**Reglas de negocio:** RN-411, RN-416, RN-417, RN-418, RN-424, RN-425, RN-209

## Módulo 3 — Vacunación, desparasitación y recordatorios

### HU-3.1 — Registrar vacunación

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-3.1.1 | El sistema debe registrar nombre, laboratorio, lote, fecha de aplicación y próxima dosis. | Funcional | La vacuna conserva todos sus datos. | Alta |
| RE-3.1.2 | El sistema debe mostrar la vacuna en el calendario de la mascota. | Funcional | La vacuna aparece en el calendario. | Media |
| RE-3.1.3 | El sistema debe generar una alerta antes de la próxima dosis, por defecto 7 días antes, según los tiempos configurados por la clínica. | Funcional | Con el valor por defecto la alerta aparece 7 días antes; si la clínica configura 10, aparece 10 días antes. | Alta |

**Reglas de negocio:** RN-301, RN-306

### HU-3.2 — Enviar recordatorio por correo

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-3.2.1 | El sistema debe enviar un correo antes del vencimiento, por defecto 7 días y 1 día antes, según los tiempos configurados por la clínica. | Funcional | Con los tiempos por defecto llegan los correos a 7 días y a 1 día; al cambiarlos, se respetan los nuevos. | Alta |
| RE-3.2.2 | El correo debe incluir mascota, tipo y fecha. | Funcional | El correo contiene esos datos. | Media |
| RE-3.2.3 | El sistema no debe enviar si el propietario no tiene correo. | Restricción | Sin correo, no se envía. | Media |
| RE-3.2.4 | El sistema debe registrar cada envío. | Funcional | Cada envío queda registrado con su estado. | Media |
| RE-3.2.5 | El sistema debe ejecutar el envío de recordatorios con una tarea programada una vez al día, a las 7:00 en la zona horaria de la clínica. | Integración | Los correos del día salen en la mañana sin que nadie los dispare a mano. | Media |

**Reglas de negocio:** RN-303, RN-304, RN-305

### HU-3.3 — Enviar recordatorio por WhatsApp

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-3.3.1 | El sistema debe enviar un WhatsApp con el mismo contenido del correo. | Funcional | El WhatsApp llega con el mismo contenido (cuenta de prueba). | Futura |
| RE-3.3.2 | El sistema debe enviar solo si hay número de WhatsApp registrado. | Restricción | Sin número, no se envía. | Futura |
| RE-3.3.3 | El sistema debe usar la WhatsApp Business Cloud API (Meta). | Integración | El envío usa la API oficial de Meta. | Futura |

**Reglas de negocio:** RN-307

### HU-3.4 — Registrar desparasitación

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-3.4.1 | El sistema debe registrar tipo (interna/externa) y periodicidad. | Funcional | La desparasitación guarda tipo y periodicidad. | Media |
| RE-3.4.2 | El sistema debe calcular automáticamente la próxima aplicación. | Cálculo | La próxima fecha se calcula según la periodicidad. | Media |
| RE-3.4.3 | El sistema debe generar alertas igual que en vacunación. | Funcional | Se genera alerta como en vacunación. | Media |

**Reglas de negocio:** RN-302

### HU-3.5 — Panel de vacunaciones pendientes

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-3.5.1 | El sistema debe listar vacunas con próxima dosis en 7 días. | Funcional | El panel lista las próximas a 7 días. | Media |
| RE-3.5.2 | El sistema debe agrupar por día y especie con contador. | Funcional | Se agrupan por día y especie con contador. | Media |
| RE-3.5.3 | El sistema debe enlazar cada entrada con la ficha. | Funcional | Un clic abre la ficha de la mascota. | Baja |

**Reglas de negocio:** RN-301

### HU-3.6 — Robustez de los recordatorios automáticos

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-3.6.1 | El sistema debe usar una ventana de fechas con marca de enviado que recupere los no enviados. | Confiabilidad | Si el cron falla un día, al siguiente se recuperan. | Media |
| RE-3.6.2 | El sistema debe reintentar los envíos fallidos, hasta 3 intentos por aviso. | Confiabilidad | Un aviso con estado de error se vuelve a enviar en la siguiente ejecución; tras 3 fallos, no. | Media |
| RE-3.6.3 | El sistema debe usar la zona horaria de la clínica en los cálculos. | Confiabilidad | El límite de día es correcto para la clínica. | Media |
| RE-3.6.4 | El sistema no debe recordar dosis de mascotas inactivas ni dosis ya renovadas. | Restricción | Una mascota inactiva, o una dosis con una aplicación posterior de la misma vacuna o del mismo tipo de desparasitación, no genera correo. | Media |

**Reglas de negocio:** RN-303, RN-304, RN-305

## Módulo 4 — Agenda de citas inteligente (Grafo II)

### HU-4.1 — Agendar cita

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-4.1.1 | El sistema debe registrar fecha, hora, mascota, tipo/motivo y veterinario. | Funcional | La cita conserva todos esos datos. | Alta |
| RE-4.1.2 | El sistema debe impedir el solapamiento del veterinario. | Restricción | Agendar en un horario ocupado es rechazado. | Alta |
| RE-4.1.3 | El sistema debe respetar el horario de atención configurado. | Restricción | Agendar fuera del horario es rechazado. | Alta |
| RE-4.1.4 | El sistema debe enviar correo de confirmación al crear. | Funcional | El propietario recibe confirmación. | Alta |
| RE-4.1.5 | El sistema debe mostrar la cita en el calendario. | Funcional | La cita aparece en el calendario. | Media |

**Reglas de negocio:** RN-401, RN-402, RN-403, RN-404

### HU-4.2 — Cancelar o reprogramar cita

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-4.2.1 | El sistema debe permitir cancelar o cambiar fecha/hora. | Funcional | Se cancela o cambia la fecha/hora. | Media |
| RE-4.2.2 | El sistema debe notificar el cambio por correo. | Funcional | El propietario recibe correo del cambio. | Media |
| RE-4.2.3 | El sistema debe conservar las canceladas con su estado. | Restricción | La cancelada queda con estado cancelada. | Media |
| RE-4.2.4 | El sistema debe impedir reprogramar a un horario ocupado. | Restricción | Reprogramar a un horario ocupado es rechazado. | Alta |

**Reglas de negocio:** RN-405

### HU-4.3 — Marcar cita como completada

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-4.3.1 | El sistema debe permitir completar solo citas en curso. | Restricción | Una cita en curso o sin cerrar se completa; una pendiente, confirmada, cancelada o no asistida, no. | Media |
| RE-4.3.2 | El sistema debe completar la cita al registrar su consulta, en la misma transacción. | Funcional | Guardar la consulta deja la cita completada; si algo falla no queda ninguna de las dos. | Alta |
| RE-4.3.3 | El sistema debe excluir las completadas de la agenda futura. | Funcional | Una completada no aparece en la agenda futura. | Media |
| RE-4.3.4 | El sistema debe permitir iniciar la atención solo al veterinario asignado, el día de la cita y desde 15 minutos antes de su hora. | Restricción | Otro rol, otro veterinario, otra fecha o una hora anterior son rechazados. | Alta |
| RE-4.3.5 | El sistema debe permitir retomar una atención en curso. | Funcional | Una cita en curso ofrece «Continuar atención» aunque sea de otro día. | Alta |
| RE-4.3.6 | El sistema debe avisar al veterinario cuando una atención sigue en curso 10 minutos después de su hora de fin. | Funcional | Llega un correo y una notificación interna, una sola vez por cita. | Alta |
| RE-4.3.7 | El sistema debe pasar a "sin cerrar" las atenciones que siguen en curso al terminar el día de la cita. | Funcional | Al día siguiente la cita aparece como «Sin cerrar» y el veterinario recibe un aviso. | Alta |
| RE-4.3.8 | _(Derogado en v2.0: RN-410 se deroga.)_ En v1.11.0 el veterinario asignado podía cerrar sin consulta una atención en curso o sin cerrar, con motivo obligatorio. | Funcional | En v2.0 no existe la acción: una atención iniciada solo se completa con su consulta. | Media |
| RE-4.3.9 | El sistema debe revisar las atenciones abiertas con una tarea programada al menos cada 5 minutos, además de al cargar el calendario. | Integración | Sin que nadie abra el calendario, el aviso llega a más tardar 15 minutos después de la hora de fin. | Alta |

**Reglas de negocio:** RN-406, RN-407, RN-409, RN-410

### HU-4.4 — Confirmación automática de cita por correo

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-4.4.1 | El sistema debe enviar el correo de confirmación inmediatamente tras guardar. | Funcional | El correo se envía de inmediato. | Alta |
| RE-4.4.2 | El correo debe incluir fecha, hora, mascota, motivo y dirección. | Funcional | El correo contiene todos esos datos. | Media |
| RE-4.4.3 | El envío no debe bloquear el agendado. | Rendimiento | El agendado responde sin esperar el correo. | Media |
| RE-4.4.4 | El sistema debe registrar el fallo de envío como fallido. | Funcional | Un fallo queda registrado como fallido. | Media |

**Reglas de negocio:** RN-404

### HU-4.5 — Registrar hora real de atención y gestionar retrasos

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-4.5.1 | El sistema debe sellar la hora real al iniciar y al completar la atención. | Funcional | Quedan registradas las horas reales de inicio y fin. | Alta |
| RE-4.5.2 | El sistema debe detectar cuando una atención excede su duración planificada. | Funcional | El sistema identifica cuando una atención se pasó. | Alta |
| RE-4.5.3 | El sistema debe alertar o recalcular el corrimiento de las citas siguientes. | Funcional | Las citas posteriores reflejan o avisan el corrimiento. | Alta |

**Reglas de negocio:** RN-401

### HU-4.6 — Buffer configurable entre citas

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-4.6.1 | El sistema debe permitir configurar el buffer entre citas. | Funcional | El administrador define el buffer. | Media |
| RE-4.6.2 | El sistema debe respetar el buffer al calcular la disponibilidad. | Restricción | No se agenda sin respetar el buffer. | Media |

**Reglas de negocio:** RN-401, RN-403

### HU-4.7 — Estado "no asistió" y ausentismo

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-4.7.1 | El sistema debe permitir marcar una cita como "no asistió". | Funcional | Se marca la cita como no asistida. | Media |
| RE-4.7.2 | El sistema debe liberar el espacio de una cita no asistida. | Funcional | Tras marcar «no asistió», esa franja vuelve a ofrecerse como disponible al agendar. | Media |
| RE-4.7.3 | El sistema debe permitir reportar la tasa de ausentismo. | Funcional | Se obtiene la tasa de ausentismo. | Baja |

**Reglas de negocio:** RN-405, RN-408

### HU-4.8 — Bloqueos de agenda del veterinario y días no laborables

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-4.8.1 | El sistema debe permitir registrar bloqueos por veterinario y fecha. | Funcional | Se crean bloqueos que restan disponibilidad. | Alta |
| RE-4.8.2 | El sistema debe permitir registrar festivos y cierres. | Funcional | Los festivos no ofrecen citas. | Media |
| RE-4.8.3 | El sistema debe excluir esos periodos de la disponibilidad. | Restricción | No se agenda en bloqueos ni festivos. | Alta |

**Reglas de negocio:** RN-402, RN-703

### HU-4.9 — Endurecer la validación de disponibilidad

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-4.9.1 | El sistema debe usar una sola lógica de disponibilidad basada en horarios_clinica. | Confiabilidad | Sugerencias y validación coinciden. | Alta |
| RE-4.9.2 | El sistema debe validar horario + duración en el backend. | Validación | El backend rechaza citas fuera de horario o que exceden el bloque. | Alta |
| RE-4.9.3 | El sistema debe usar transacción/restricción única contra la doble reserva. | Confiabilidad | Dos reservas simultáneas del mismo hueco no coexisten. | Alta |

**Reglas de negocio:** RN-401, RN-402

### HU-4.10 — Confirmar asistencia a la cita desde el recordatorio

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-4.10.1 | El recordatorio debe incluir la opción de confirmar o declinar la asistencia. | Funcional | El correo/enlace permite confirmar o declinar. | Media |
| RE-4.10.2 | La respuesta debe actualizar el estado de la cita. | Funcional | Confirmar o declinar cambia el estado de la cita. | Media |
| RE-4.10.3 | Al declinar, el sistema debe liberar el espacio y notificar a la clínica. | Funcional | El cupo queda libre y la clínica se entera. | Media |

**Reglas de negocio:** RN-404, RN-405

### HU-4.11 — Configurar el horario recurrente del veterinario

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-4.11.1 | El sistema debe permitir definir franjas recurrentes por día de la semana para cada veterinario. | Funcional | Se guardan las franjas por día. | Alta |
| RE-4.11.2 | Las franjas no deben solaparse entre sí ni con el horario del mismo veterinario en otra clínica, y deben caer dentro del horario de la clínica. | Validación | Un solape o una franja fuera de rango se rechaza; si el choque es con otra clínica, el aviso no muestra el horario de esa clínica. | Alta |
| RE-4.11.3 | La disponibilidad solo debe ofrecer espacios dentro de esas franjas. | Restricción | La agenda respeta las franjas. | Alta |
| RE-4.11.4 | Un cambio que deje citas fuera del nuevo horario debe marcarlas para reajuste. | Funcional | Esas citas se marcan para reajuste. | Media |
| RE-4.11.5 | Solo el administrador debe poder modificar el horario; el veterinario solo puede proponer cambios, que se aplican al ser aprobados. | Seguridad | Un veterinario que intenta guardar su horario directamente recibe 403; su propuesta queda pendiente hasta que el administrador la aprueba. | Alta |

**Reglas de negocio:** RN-706, RN-703, RN-415

### HU-4.12 — Registrar ausencia del veterinario y gestionar cobertura

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-4.12.1 | El sistema debe permitir registrar una ausencia por rango que tape la disponibilidad del veterinario. | Funcional | El rango queda bloqueado en la agenda. | Alta |
| RE-4.12.2 | Un rango de fecha y hora inválido debe rechazarse. | Validación | Se rechazan un fin anterior o igual al inicio y las fechas u horas inválidas; una ausencia de día completo y otra de una franja parcial bloquean exactamente sus respectivos rangos. | Media |
| RE-4.12.3 | Con citas en el rango, el sistema debe buscar cobertura y asignarlas a un veterinario con disponibilidad (sin veto). | Funcional | Las citas se asignan al de cobertura. | Alta |
| RE-4.12.4 | Si ningún veterinario puede cubrir, el sistema debe reprogramar las citas con el propietario. | Funcional | Si ningún veterinario tiene espacio compatible, el propietario recibe la propuesta de nuevos horarios y la cita queda pendiente de reprogramar. | Alta |
| RE-4.12.5 | El sistema debe notificar al propietario y a ambos veterinarios y registrar en auditoría. | Funcional | Al asignar la cobertura llegan los avisos al propietario y a los dos veterinarios, y la auditoría registra el cambio de veterinario. | Media |

**Reglas de negocio:** RN-414, RN-415

### HU-4.13 — Agendar cita con triage de 4 niveles

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-4.13.1 | El Grafo I debe calcular el nivel de triage a partir de los síntomas; el propietario no lo elige. | Funcional | El nivel se asigna automáticamente. | Alta |
| RE-4.13.2 | El sistema debe verificar el límite del plan antes de reservar, salvo en los casos 🔴. | Restricción | Con la clínica al límite, un 🟡 se rechaza y un 🔴 se deriva a urgencia igual. | Alta |
| RE-4.13.3 | El sistema debe ubicar la cita según el nivel: rojo a urgencia, naranja sobrecupo, amarillo primer espacio, verde elección. | Funcional | Cada nivel se asigna según su regla. | Alta |
| RE-4.13.4 | La disponibilidad debe calcularse como horario de la clínica ∩ horario del veterinario − ausencias, por duración más margen. | Cálculo | Los espacios respetan la fórmula. | Alta |
| RE-4.13.5 | La reserva debe hacerse con bloqueo y, si el espacio se ocupó, buscar otro. | Restricción | No se crean dos citas en el mismo espacio. | Alta |
| RE-4.13.6 | Al crear la cita, el sistema debe registrar el tipo, la duración, el margen y la prioridad, notificar y auditar. | Funcional | La cita queda creada y notificada. | Alta |
| RE-4.13.7 | Cada bloque de un veterinario debe admitir como máximo el número de sobrecupos 🟠 configurado por la clínica (por defecto, 2). Entre naranjas se respeta el orden de llegada, salvo cambio motivado por un veterinario y auditado. | Restricción | El tercer naranja del bloque no se ubica en él y genera aviso; entre dos naranjas entra el primero por `fecha_registro`/`id_cita`, y un cambio manual sin motivo se rechaza. | Alta |

**Reglas de negocio:** RN-411, RN-415, RN-401, RN-402, RN-420, RN-422

### HU-4.14 — Reajuste de agenda en vivo (efecto cascada)

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-4.14.1 | Mientras el retraso cabe en el margen del tipo de cita, el sistema no debe propagarlo. | Restricción | Dentro del margen no hay cambios. | Alta |
| RE-4.14.2 | Al superar el margen, el retraso debe propagarse recalculando las horas estimadas de las citas siguientes del veterinario. | Funcional | Se recalculan las horas estimadas. | Alta |
| RE-4.14.3 | Si una cita queda fuera del horario disponible, el sistema debe buscar reasignarla a otro veterinario. | Funcional | Se busca un veterinario con espacio. | Alta |
| RE-4.14.4 | La reasignación en vivo debe confirmarla el veterinario destino. | Restricción | Sin confirmación la cita no se mueve. | Alta |
| RE-4.14.5 | Sin veterinario disponible, la cita debe reprogramarse con el propietario; los cambios se notifican y auditan. | Funcional | Se ofrece reprogramar; notificación y auditoría. | Media |
| RE-4.14.6 | El veterinario destino debe confirmar la reasignación dentro del plazo configurado; si no responde o rechaza, se propone al siguiente veterinario disponible. | Funcional | Pasado el plazo sin respuesta, la propuesta pasa al siguiente veterinario; sin ninguno, se reprograma. | Alta |
| RE-4.14.7 | Los cambios de hora estimada deben avisarse solo al superar el umbral acumulado y como máximo una vez cada 30 minutos por cita; la reasignación y la reprogramación se avisan siempre. | Restricción | Diez recálculos de 3 minutos en una hora generan como máximo dos avisos. | Media |

**Reglas de negocio:** RN-412, RN-413, RN-415, RN-428, RN-429

### HU-4.15 — Atender una urgencia (triage rojo o llegada directa)

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-4.15.1 | El sistema debe disparar la urgencia por triage rojo o llegada directa, notificar a la clínica y marcar prioridad máxima. | Funcional | Un caso 🔴 o una llegada directa crea la urgencia con prioridad máxima y la clínica recibe la notificación en el momento. | Alta |
| RE-4.15.2 | El sistema debe asignar un veterinario libre; si no hay, pausar una cita pausable; si ninguno, dejarla en espera por prioridad. | Funcional | Se asigna según disponibilidad. | Alta |
| RE-4.15.3 | En un rojo del portal autenticado, el sistema debe usar la identidad de la sesión y la mascota seleccionada sin volver a verificarlas. En un ingreso presencial, debe usar la cuenta y la ficha existentes si puede verificar su relación sin retrasar la atención; en otro caso debe crear una ficha provisional vinculada a la clínica, sin atribuir propiedad al acompañante ni crear una cuenta. Después debe permitir completar o consolidar la ficha, conservando la trazabilidad. | Funcional | Desde el portal se conservan `id_usuario` e `id_mascota` sin búsqueda adicional. En persona, con relación verificada se reutilizan ambos registros; sin verificación o sin cuenta, la consulta se registra en una mascota «por completar», con carnet inactivo y sin usuario nuevo. El límite del plan no bloquea el ingreso rojo. La conciliación no pierde actos clínicos ni duplica la cuenta. | Media |
| RE-4.15.4 | La atención debe continuar por el flujo de consulta vía Atención/Urgencias. | Funcional | Enlaza con la consulta clínica. | Alta |
| RE-4.15.5 | El uso del espacio debe enlazar con el reajuste de agenda en vivo. | Funcional | La agenda del día se reacomoda. | Media |
| RE-4.15.6 | Un caso 🔴 desde el portal fuera del horario de la clínica debe informar que está cerrada, mostrar su teléfono de urgencias si existe y recomendar un servicio 24 horas. | Funcional | A las 11 p. m. el mensaje no promete atención y muestra la recomendación. | Alta |
| RE-4.15.7 | La urgencia no debe bloquearse por el límite del plan ni por la mora. | Restricción | Con la clínica al límite y en mora, la urgencia se registra y se atiende. | Alta |
| RE-4.15.8 | Solo debe pausarse una cita cuyo tipo esté marcado como pausable, y debe retomarse al terminar la urgencia antes que las siguientes. | Restricción | Una cirugía en curso no se pausa; una vacunación pausada se retoma al cerrar la urgencia. | Alta |

**Reglas de negocio:** RN-411, RN-415, RN-420, RN-421, RN-427

### HU-4.16 — Sugerir veterinario al agendar

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-4.16.1 | Al agendar, el sistema debe ofrecer veterinarios ordenados por afinidad y valoración. | Funcional | Se listan veterinarios sugeridos. | Baja |
| RE-4.16.2 | La sugerencia debe ser una entrada al emparejamiento del Grafo II, sin obligar la elección. | Funcional | El propietario puede elegir un veterinario distinto del primero sugerido y la cita se crea con el que eligió. | Baja |

**Reglas de negocio:** RN-803

### HU-4.17 — Recalcular el triage cuando cambian los síntomas

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-4.17.1 | El portal debe permitir actualizar los síntomas de una cita pendiente. | Funcional | La opción solo aparece en citas pendientes. | Alta |
| RE-4.17.2 | Al actualizar, el nivel debe recalcularse; si sube, la cita se reubica (o se deriva a urgencia si es 🔴) y se notifica. | Funcional | Una cita 🟢 del viernes que pasa a 🟠 el miércoles toma un sobrecupo de ese día. | Alta |
| RE-4.17.3 | Si el nivel baja, la cita debe conservar su espacio. | Restricción | La cita no se mueve. | Media |
| RE-4.17.4 | Cada recálculo debe quedar en auditoría con el nivel anterior y el nuevo. | Seguridad | La entrada muestra ambos niveles. | Media |

**Reglas de negocio:** RN-423, RN-411

### HU-4.18 — Ajustar el nivel de triage (veterinario)

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-4.18.1 | El veterinario debe poder cambiar el nivel de triage con motivo obligatorio. | Funcional | Sin motivo, el cambio se rechaza. | Alta |
| RE-4.18.2 | El cambio debe auditarse y guardar el nivel calculado y el ajustado. | Seguridad | La entrada de auditoría muestra ambos niveles y el motivo. | Alta |
| RE-4.18.3 | Si el ajuste cambia la ubicación, la agenda debe reacomodarse y notificar. | Funcional | La cita se mueve según el nuevo nivel y llega el aviso. | Media |
| RE-4.18.4 | Las reclasificaciones deben verse en el historial del propietario. | Funcional | La clínica ve cuántas veces se reclasificaron sus casos. | Baja |

**Reglas de negocio:** RN-419, RN-209

### HU-4.19 — Manejar la llegada tarde del propietario

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-4.19.1 | El sistema debe registrar la hora de llegada del propietario. | Funcional | La cita guarda la hora de llegada. | Alta |
| RE-4.19.2 | Dentro de la tolerancia, la cita debe atenderse en el tiempo restante; si no cabe, el veterinario elige atender igual o reprogramar. | Funcional | Con 8 minutos de retraso y tolerancia de 10, se ofrece atender; si la cita no cabe, aparecen las dos opciones. | Alta |
| RE-4.19.3 | Pasada la tolerancia, el veterinario debe poder marcar «no asistió», atender en el siguiente espacio libre o reprogramar. | Funcional | Con 15 minutos de retraso aparecen las tres opciones. | Alta |
| RE-4.19.4 | La agenda debe recalcularse y la decisión registrarse en auditoría. | Seguridad | Las citas siguientes muestran su nueva hora estimada y la decisión queda auditada. | Media |

**Reglas de negocio:** RN-426, RN-408, RN-412

## Módulo 5 — Portal del propietario (multi-clínica)

### HU-5.1 — Portal del propietario

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-5.1.1 | El sistema debe permitir el acceso con credenciales propias o con Google. | Funcional | El propietario ingresa con credenciales o con Google. | Alta |
| RE-5.1.2 | El sistema debe mostrar únicamente las mascotas del propietario. | Restricción | Un propietario con dos mascotas ve solo esas dos; pedir por URL la mascota de otro propietario devuelve 403. | Alta |
| RE-5.1.3 | El sistema debe mostrar ficha, historial, próximas citas y vacunas. | Funcional | Para una mascota con consultas, citas y vacunas, el portal muestra su ficha, su historial, sus próximas citas y su calendario de vacunas. | Media |
| RE-5.1.4 | El sistema debe devolver 403 ante datos de terceros. | Seguridad | Acceder a datos ajenos devuelve 403. | Alta |
| RE-5.1.5 | El portal debe adaptarse a móvil (320–767 px), tablet (768–1023 px) y escritorio (≥ 1024 px), según RNF-16. | Usabilidad | Sin desbordes ni superposiciones en 360, 390, 768, 820, 1024, 1280 y 1440 px; en móvil y tablet se navega con la barra inferior y en escritorio con el menú lateral. | Media |
| RE-5.1.6 | Cada sección del portal debe tener su propia dirección. | Usabilidad | El botón «atrás» del navegador vuelve a la sección anterior, cierra la ventana abierta y la recarga conserva la sección. | Media |
| RE-5.1.7 | El portal debe mostrar el horario real de atención de la clínica (HU-43); _(v2.0)_ el de cada clínica con vínculo activo. | Funcional | Indica si la clínica está abierta en este momento, cuándo cierra o cuándo vuelve a abrir, y el horario de la semana. | Media |
| RE-5.1.8 | El portal debe poder usarse con teclado y permitir ampliar la pantalla. | Usabilidad | El zoom no está bloqueado; las ventanas se cierran con Esc y el foco no sale de ellas mientras están abiertas. | Media |
| RE-5.1.9 | El servidor debe validar los datos de mascota que registra o edita el propietario. | Seguridad | Nombre de 1 a 50 caracteres (letras, números, espacio, punto, guion, apóstrofo); especie y raza del catálogo y la raza de esa especie; sexo Macho o Hembra; peso mayor que 0 y hasta 150 kg; nacimiento no futuro ni de más de 40 años; foto JPG o PNG real de hasta 5 MB con nombre generado por el servidor. El propietario no crea razas: si la suya no está, la elige como mestiza («Criollo»), «No sé la raza» o «Mi raza no está en la lista» y la escribe; queda como «Sin raza definida» con la raza indicada, que el personal ve en la ficha, la atención y la lista de pacientes y se borra cuando el personal asigna una raza. Tampoco registra el color: el color lo anota la clínica en la consulta y el portal no lo modifica. | Alta |
| RE-5.1.10 | Al agendar, el portal solo debe ofrecer días y horas en que se puede atender. | Usabilidad | Los días sin atención en toda la jornada y los pasados no se pueden elegir; los horarios salen en botones de mañana y tarde cuando ya hay tipo, veterinario y día, y el botón de confirmar se habilita al completar todo. | Media |

**Reglas de negocio:** RN-G02

### HU-5.2 — Auto-registro de propietario

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-5.2.1 | El sistema debe ofrecer un formulario público de registro. | Funcional | El formulario es accesible sin sesión. | Alta |
| RE-5.2.2 | El sistema debe exigir documento, nombre, teléfono, correo y contraseña segura. | Validación | Faltar un campo o contraseña débil impide registrar. | Alta |
| RE-5.2.3 | El sistema debe validar que documento y correo no existan. | Validación | Un documento o correo existente es rechazado. | Alta |
| RE-5.2.4 | El sistema debe crear el usuario propietario e iniciar sesión. | Funcional | Queda con rol propietario y sesión iniciada. | Alta |
| RE-5.2.5 | El sistema debe proteger el formulario con token CSRF. | Seguridad | Una petición sin token CSRF es rechazada. | Alta |

**Reglas de negocio:** RN-101, RN-G06, RN-501

### HU-5.3 — Agendar cita desde el portal

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-5.3.1 | El sistema debe permitir seleccionar una mascota propia. | Funcional | Solo se eligen mascotas propias. | Alta |
| RE-5.3.2 | El sistema debe permitir elegir tipo, veterinario, fecha y hora. | Funcional | Se elige tipo, veterinario, fecha y hora disponible. | Alta |
| RE-5.3.3 | El sistema debe verificar la disponibilidad dinámicamente. | Restricción | Un horario ocupado no se ofrece. | Alta |
| RE-5.3.4 | El sistema debe enviar confirmación y registrar en auditoría. | Funcional | Se confirma y queda en auditoría. | Media |
| RE-5.3.5 | _(v2.0)_ El portal solo debe ofrecer las clínicas a las que el propietario está vinculado; si la mascota no está vinculada a la clínica elegida, debe vincularse al agendar y contar para el límite del plan. | Restricción | Una clínica no vinculada no aparece en la lista; tras agendar, la mascota aparece en la clínica con su vínculo. | Alta |

**Reglas de negocio:** RN-401, RN-402, RN-501, RN-109, RN-110

### HU-5.4 — Editar mi mascota desde el portal

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-5.4.1 | El sistema debe permitir editar los datos de mi mascota. | Funcional | Los cambios de mi mascota se guardan. | Media |
| RE-5.4.2 | El sistema debe permitir editar solo mis propias mascotas. | Seguridad | No puedo editar mascotas ajenas. | Alta |
| RE-5.4.3 | El sistema debe permitir actualizar la foto con validación. | Validación | Una foto inválida es rechazada. | Media |

**Reglas de negocio:** RN-104, RN-108, RN-G02

### HU-5.5 — Imprimir/exportar el historial de mi mascota

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-5.5.1 | El sistema debe generar una versión imprimible del historial. | Funcional | Se genera una vista imprimible. | Media |
| RE-5.5.2 | El documento debe incluir consultas, vacunas y desparasitaciones. | Funcional | El documento generado incluye todas las consultas, vacunas y desparasitaciones de la mascota, con su fecha. | Media |
| RE-5.5.3 | El sistema debe permitirlo solo para mis mascotas. | Seguridad | No accedo a historiales ajenos. | Alta |

**Reglas de negocio:** RN-206, RN-G02, RN-114

### HU-5.6 — Cancelar o reprogramar mi cita desde el portal

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-5.6.1 | El sistema debe permitir al propietario cancelar una cita futura propia. | Funcional | El propietario cancela su cita y queda en estado cancelada. | Alta |
| RE-5.6.2 | El sistema debe permitir reprogramar a un horario disponible. | Funcional | Se reprograma solo a horarios libres. | Alta |
| RE-5.6.3 | El sistema debe permitirlo solo sobre citas propias. | Seguridad | No puede tocar citas de otros propietarios. | Alta |
| RE-5.6.4 | El sistema debe notificar el cambio a la clínica y registrarlo en auditoría. | Funcional | El cambio notifica a la clínica y queda en auditoría. | Media |

**Reglas de negocio:** RN-405, RN-401, RN-G02

### HU-5.7 — Centro de notificaciones del propietario

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-5.7.1 | El sistema debe mostrar al propietario un listado de sus notificaciones en el portal. | Funcional | El propietario ve sus notificaciones en el portal. | Media |
| RE-5.7.2 | El sistema debe mostrar un contador de no leídas. | Funcional | Hay un contador de no leídas. | Media |
| RE-5.7.3 | El sistema debe permitir marcarlas como leídas. | Funcional | Puede marcar como leídas. | Baja |
| RE-5.7.4 | El sistema debe mostrar solo las notificaciones del propietario. | Seguridad | El propietario A no ve las notificaciones del propietario B ni pidiéndolas por URL (403). | Alta |

**Reglas de negocio:** RN-G02

### HU-5.8 — Registrar al propietario como identidad global

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-5.8.1 | El sistema debe permitir el registro por tres vías, siempre en el contexto de una clínica. | Funcional | Un propietario puede registrarse por cada una de las tres vías y queda vinculado a la clínica de esa vía. | Alta |
| RE-5.8.2 | El correo debe ser único en la plataforma; si ya existe, no se duplica la identidad. | Restricción | Registrar un correo que ya existe no crea otra cuenta: tras verificar al dueño, liga la cuenta existente a la nueva clínica. | Alta |
| RE-5.8.3 | Si el correo ya existe, el sistema debe verificar que es el dueño y ligarlo a la clínica. | Seguridad | Se liga previa verificación del dueño. | Alta |
| RE-5.8.4 | En un registro nuevo, el sistema debe crear la identidad, ligarla, verificar el correo y enviar el enlace de acceso y el QR del portal. | Funcional | Se activa y recibe acceso y QR. | Alta |
| RE-5.8.5 | Cada clínica debe ver solo sus propietarios; el alta queda en auditoría. | Seguridad | La clínica B no ve a un propietario vinculado solo a la clínica A; el alta queda en auditoría. | Alta |

**Reglas de negocio:** RN-109, RN-G06

### HU-5.9 — Iniciar sesión en el portal multi-clínica

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-5.9.1 | El sistema debe autenticar por correo y contraseña (o Google) e informar si las credenciales o el estado son inválidos. | Seguridad | Credenciales inválidas no dan acceso. | Alta |
| RE-5.9.2 | Tras iniciar sesión, el propietario debe ver todas sus mascotas y su historia en todas las clínicas, identificando la clínica de cada registro. | Funcional | Un propietario con dos clínicas ve a su mascota una sola vez, con los registros de ambas. | Alta |
| RE-5.9.3 | Al agendar, cancelar o calificar, el propietario debe elegir la clínica entre las vinculadas; con una sola se usa directamente. | Funcional | Se ofrece la selección solo si tiene varias clínicas. | Alta |
| RE-5.9.4 | Cada acción propia de una clínica debe quedar acotada a la id_clinica de esa clínica. | Seguridad | Una cita agendada en la clínica A no aparece en la agenda de la clínica B. | Alta |

**Reglas de negocio:** RN-109, RN-114, RN-G13

### HU-5.10 — Carnet digital de la mascota con QR de emergencia

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-5.10.1 | El sistema debe generar el carnet con un código QR al guardar la mascota; la mascota tiene un solo carnet en toda la plataforma. | Funcional | Una mascota vinculada a dos clínicas tiene un único QR. | Media |
| RE-5.10.2 | El QR debe apuntar a un token aleatorio de al menos 128 bits, nunca al identificador interno de la mascota. | Seguridad | La URL del carnet no contiene el id de la mascota; cambiar caracteres del token no abre otro carnet. | Alta |
| RE-5.10.3 | El carnet debe mostrar solo nombre, foto, especie y raza, alergias y alertas, vacunas vigentes con su clínica, un teléfono del propietario y el de la clínica. | Seguridad | No aparecen dirección, correo, documento ni historia clínica. | Alta |
| RE-5.10.4 | El QR debe poder imprimirse o compartirse y mantenerse igual hasta que el propietario lo regenere. | Funcional | El QR impreso sigue funcionando mientras no se regenere. | Baja |
| RE-5.10.5 | El propietario debe poder regenerar el QR; el token anterior debe dejar de funcionar al instante y el cambio auditarse. | Seguridad | Tras regenerar, el QR anterior muestra el mensaje genérico de carnet no disponible. | Alta |

**Reglas de negocio:** RN-110, RN-502, RN-503

### HU-5.11 — Acceder al carnet por QR en modo solo lectura

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-5.11.1 | Escanear el QR debe abrir el carnet en modo solo lectura sin iniciar sesión. | Funcional | Escanear el QR desde un navegador sin sesión muestra el carnet y ninguna opción de edición. | Media |
| RE-5.11.2 | Un token inválido, regenerado o de una mascota inactiva debe mostrar un mensaje genérico que no revele si la mascota existe. | Seguridad | Los tres casos muestran el mismo mensaje y el mismo código de respuesta. | Alta |
| RE-5.11.3 | Para ver o editar la ficha completa, el sistema debe solicitar inicio de sesión. | Seguridad | Intentar editar desde el carnet redirige al inicio de sesión; tras iniciar sesión como propietario se abre la ficha completa. | Media |
| RE-5.11.4 | Cada escaneo debe notificar al propietario con fecha y hora, agrupando los escaneos repetidos en un periodo corto. | Funcional | Diez escaneos seguidos generan un solo aviso. | Media |
| RE-5.11.5 | Quien escanea debe poder compartir su ubicación con el dueño solo si la acepta expresamente. | Seguridad | Sin aceptar, no se envía ubicación; al aceptar, el dueño la recibe en el aviso. | Media |
| RE-5.11.6 | El acceso al carnet debe admitir como máximo 30 consultas por hora desde una misma IP y marcar la página como no indexable. | Seguridad | Superado el límite se responde 429; la página incluye la directiva noindex. | Alta |

**Reglas de negocio:** RN-502, RN-503, RN-504, RN-505

### HU-5.12 — Autorizar a una clínica a ver la historia de otras clínicas

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-5.12.1 | El portal debe permitir activar o revocar, por cada clínica vinculada, el acceso a la historia registrada por otras clínicas. | Funcional | Cada clínica tiene su interruptor de autorización. | Alta |
| RE-5.12.2 | La revocación debe aplicar de inmediato. | Seguridad | Tras revocar, la clínica deja de ver las consultas de otras clínicas en su siguiente petición. | Alta |
| RE-5.12.3 | Al desvincularse de una clínica, el sistema debe retirarle la autorización y el acceso a los datos nuevos. | Seguridad | La clínica conserva solo sus propios registros. | Alta |
| RE-5.12.4 | Cada cambio de autorización debe registrarse en auditoría. | Seguridad | Activar o revocar genera una entrada de auditoría. | Media |

**Reglas de negocio:** RN-113, RN-115, RN-G07

### HU-5.13 — Vincularme o desvincularme de una clínica

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-5.13.1 | El portal debe permitir al propietario vincularse con un clic a una clínica activa de la lista, sin repetir la verificación del correo. | Funcional | Tras vincularse, la clínica aparece entre sus clínicas y en la selección al agendar. | Alta |
| RE-5.13.2 | Al vincularse, la clínica nueva solo debe ver los datos del propietario; las mascotas se le vinculan al agendar o al ser atendidas allí. | Seguridad | Recién vinculada, la clínica no ve mascotas del propietario hasta que se cumpla una de las dos condiciones. | Alta |
| RE-5.13.3 | El propietario debe poder desvincularse; se rechaza si tiene citas pendientes en esa clínica. | Restricción | Con citas pendientes se informa que debe cancelarlas primero; sin ellas, el vínculo pasa a inactivo. | Media |
| RE-5.13.4 | Cada vinculación y desvinculación debe registrarse en auditoría. | Seguridad | Ambas operaciones generan entrada de auditoría. | Media |

**Reglas de negocio:** RN-109, RN-110, RN-115

### HU-5.14 — Eliminar mi cuenta (derecho de supresión)

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-5.14.1 | El propietario debe poder solicitar la eliminación de su cuenta confirmando con su contraseña (o Google). | Seguridad | Sin la confirmación la solicitud no procede. | Alta |
| RE-5.14.2 | Las citas pendientes deben cancelarse y notificarse a las clínicas antes de eliminar la cuenta. | Funcional | Las clínicas reciben la notificación de cancelación. | Media |
| RE-5.14.3 | Los datos personales del propietario deben anonimizarse de forma irreversible y su acceso debe cerrarse. | Seguridad | En la BD no queda nombre, documento, correo, teléfono ni dirección; el login falla. | Alta |
| RE-5.14.4 | La historia clínica de sus mascotas debe conservarse anonimizada y los carnets QR deben desactivarse. | Restricción | Las clínicas siguen viendo la historia sin datos del propietario; el QR muestra el mensaje genérico. | Alta |
| RE-5.14.5 | El sistema debe enviar un correo de confirmación y registrar la operación en auditoría sin datos personales. | Funcional | Llega el correo; la entrada de auditoría no contiene datos personales. | Media |

**Reglas de negocio:** RN-G16, RN-G07

## Módulo 6 — Dashboard y reportes

### HU-6.1 — Dashboard principal y panel de pendientes

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-6.1.1 | El sistema debe mostrar citas del día y vacunas/desparasitaciones próximas a 7 días. | Funcional | El panel muestra citas del día y pendientes. | Alta |
| RE-6.1.2 | El sistema debe ofrecer accesos rápidos a funciones frecuentes. | Usabilidad | Los accesos llevan a las funciones correctas. | Media |
| RE-6.1.3 | El dashboard debe cargar en menos de 3 s. | Rendimiento | Carga en menos de 3 s. | Media |
| RE-6.1.4 | El sistema debe filtrar los datos según el usuario. | Seguridad | Cada usuario ve solo lo que le corresponde. | Alta |
| RE-6.1.5 | El panel del veterinario debe destacar el siguiente paciente con la acción disponible: iniciar, esperar la ventana de inicio o continuar. | Usabilidad | El botón respeta la ventana de 15 minutos de RN-407 y se habilita sin recargar. | Alta |
| RE-6.1.6 | El panel del veterinario debe listar sus atenciones sin cerrar o abiertas de días anteriores. | Funcional | Cada una lleva a la pantalla de atención para cerrarla. | Alta |
| RE-6.1.7 | El panel no debe mostrar datos de ejemplo ni valores fijos. | Restricción | Sin datos, el panel muestra un estado vacío. | Alta |

**Reglas de negocio:** RN-G01, RN-407, RN-409

### HU-6.2 — Panel de operación del administrador

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-6.2.1 | El sistema debe mostrar las citas del día, atendidas, no asistidas y consultas del mes comparadas con el mismo periodo del mes anterior. | Funcional | Los valores coinciden con la base de datos en la zona horaria de la clínica. | Alta |
| RE-6.2.2 | El sistema debe mostrar la carga del día por veterinario activo. | Funcional | Aparecen todos los veterinarios activos, incluso sin citas. | Media |
| RE-6.2.3 | El sistema debe listar los pendientes de la operación: atenciones sin cerrar por veterinario, citas pasadas sin marcar y citas por confirmar. | Funcional | Cada conteo coincide con los estados de las citas. | Alta |
| RE-6.2.4 | El sistema debe graficar las citas atendidas y no asistidas de los últimos 6 meses. | Funcional | Los meses sin citas aparecen en cero y los datos tienen una tabla accesible. | Media |
| RE-6.2.5 | El sistema debe listar las citas de hoy con un filtro por estado. | Funcional | Al filtrar por «en curso», solo aparecen las citas de hoy en ese estado. | Media |
| RE-6.2.6 | El panel no debe mostrar datos de ejemplo ni valores fijos. | Restricción | Con la base de datos vacía, todos los indicadores muestran cero. | Media |

**Reglas de negocio:** RN-G01, RN-408, RN-409

### HU-6.3 — Generar reportes en PDF

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-6.3.1 | El sistema debe generar reportes de pacientes, citas y vacunaciones pendientes. | Funcional | Se generan los tres reportes con datos correctos. | Media |
| RE-6.3.2 | El sistema debe permitir filtrar por rango de fechas. | Funcional | Con un rango de fechas, el reporte solo incluye registros dentro de ese rango. | Media |
| RE-6.3.3 | El sistema debe permitir descargar el PDF desde el navegador. | Funcional | El PDF se descarga. | Media |
| RE-6.3.4 | El reporte debe incluir encabezado con nombre de clínica y fecha. | Funcional | El PDF incluye encabezado y fecha. | Baja |

**Reglas de negocio:** RN-701

### HU-6.4 — Ver estadísticas y gráficas del sistema

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-6.4.1 | El sistema debe mostrar indicadores clave (pacientes, clientes, citas, consultas). | Funcional | Se muestran los indicadores correctos. | Media |
| RE-6.4.2 | El sistema debe mostrar gráficas por periodo o categoría. | Funcional | Se ven gráficas coherentes con los datos. | Media |
| RE-6.4.3 | El sistema debe restringir las estadísticas a roles internos. | Seguridad | Un propietario no ve las estadísticas. | Alta |
| RE-6.4.4 | _(v2.0)_ Las estadísticas deben incluir la tasa de ausentismo por periodo y por veterinario. | Funcional | La tasa coincide con las citas «no asistió» sobre las agendadas del periodo. | Media |

**Reglas de negocio:** RN-701

### HU-6.5 — Exportar reportes a Excel/CSV

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-6.5.1 | El sistema debe permitir exportar a Excel/CSV cada reporte disponible en PDF. | Funcional | Cada reporte PDF también se exporta a Excel/CSV. | Media |
| RE-6.5.2 | El archivo exportado debe respetar los filtros aplicados. | Funcional | El archivo respeta los filtros. | Media |
| RE-6.5.3 | Los datos exportados deben coincidir con la vista. | Funcional | Los datos coinciden con lo mostrado. | Baja |

**Reglas de negocio:** RN-701

## Módulo 7 — Configuración del sistema

### HU-7.1 — Configurar los horarios de atención de la clínica

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-7.1.1 | El sistema debe permitir definir bloques de mañana y tarde por día. | Funcional | Se guardan los bloques por día. | Alta |
| RE-7.1.2 | El sistema debe permitir activar o inactivar días. | Funcional | Un día inactivo no ofrece citas. | Media |
| RE-7.1.3 | El sistema debe permitir restaurar los horarios por defecto. | Funcional | Se restauran los horarios por defecto. | Baja |
| RE-7.1.4 | La agenda debe respetar la configuración de horarios. | Restricción | La disponibilidad usa estos horarios. | Alta |

**Reglas de negocio:** RN-703, RN-701

### HU-7.2 — Gestionar los catálogos del sistema

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-7.2.1 | El sistema debe permitir agregar entradas a los catálogos. | Funcional | Se crean nuevas entradas de catálogo. | Media |
| RE-7.2.2 | Las nuevas entradas deben aparecer en los formularios correspondientes. | Funcional | Las nuevas entradas están disponibles al registrar. | Media |
| RE-7.2.3 | Las vacunas base deben asociarse por especie. | Restricción | Una vacuna base aplica a su especie. | Media |

**Reglas de negocio:** RN-702

### HU-7.3 — Gestionar los veterinarios y sus especialidades

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-7.3.1 | El sistema debe permitir el CRUD de los veterinarios de la clínica con sus especialidades. | Funcional | Alta, edición e inactivación de veterinarios. | Alta |
| RE-7.3.2 | Los veterinarios de la clínica deben ser las personas con rol de veterinario en ella; el catálogo de especialidades es global. | Restricción | Un veterinario que trabaja en dos clínicas aparece en ambas con una sola cuenta; la clínica A no puede gestionar a los veterinarios de la clínica B. | Alta |
| RE-7.3.3 | Las especialidades deben alimentar la sugerencia de veterinario y el emparejamiento del Grafo II. | Funcional | Al agendar un tipo de cita asociado a una especialidad, aparecen primero los veterinarios que la tienen. | Media |
| RE-7.3.4 | Inactivar un veterinario debe retirarlo de la disponibilidad futura sin borrar su historial. | Restricción | Un veterinario inactivado no aparece en la disponibilidad futura, y sus consultas anteriores siguen en la historia clínica. | Media |
| RE-7.3.5 | Las acciones deben quedar en auditoría. | Seguridad | Dar de alta, editar o inactivar un veterinario genera una entrada de auditoría. | Baja |

**Reglas de negocio:** RN-705, RN-009, RN-701

### HU-7.4 — Personalizar la clínica: marca y tipos de cita

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-7.4.1 | El sistema debe permitir configurar la marca de la clínica: nombre, logo y datos de contacto, incluido el teléfono de urgencias. | Funcional | Los datos guardados se ven en la configuración y en el portal; cuando la clínica está cerrada, el aviso de urgencia muestra el teléfono registrado. | Media |
| RE-7.4.2 | El sistema debe permitir gestionar los tipos de cita con su duración y su margen. | Funcional | Cada tipo tiene duración y margen. | Alta |
| RE-7.4.3 | El margen del tipo de cita debe ser insumo del cálculo de disponibilidad. | Cálculo | La disponibilidad usa el margen del tipo. | Alta |
| RE-7.4.4 | La marca debe aparecer en el portal, los correos y los documentos de la clínica. | Funcional | El logo y el nombre de la clínica aparecen en su portal, en sus correos y en los documentos que genera. | Baja |
| RE-7.4.5 | Todo debe ser propio de cada clínica y quedar en auditoría. | Restricción | Los datos de marca y los tipos de cita de la clínica A no aparecen en la clínica B, y cada cambio queda en auditoría. | Media |
| RE-7.4.6 | Cada tipo de cita debe indicar si es pausable ante una urgencia. | Funcional | El formulario del tipo de cita tiene la opción y la agenda la respeta. | Media |

**Reglas de negocio:** RN-009, RN-415, RN-421, RN-701, RN-427

### HU-7.5 — Parámetros del sistema configurables

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-7.5.1 | El sistema debe permitir configurar la duración de cita por defecto y el buffer entre citas. | Funcional | Los valores se guardan y se aplican. | Media |
| RE-7.5.2 | El sistema debe aplicar los parámetros en la agenda y en los recordatorios. | Restricción | Agenda y cron usan los parámetros configurados. | Media |
| RE-7.5.3 | _(v2.1)_ El administrador debe poder configurar la tolerancia de llegada, el plazo de reasignación, el umbral de aviso, el tope de sobrecupos y el teléfono de urgencias. Los valores por defecto y el teléfono registrado como dato de la clínica funcionan desde v2.0. | Funcional | En v2.0 la agenda aplica los valores por defecto; en v2.1, al cambiarlos desde configuración, aplica los nuevos valores. | Media |

**Reglas de negocio:** RN-403, RN-303, RN-703, RN-421, RN-422, RN-426, RN-428, RN-429

## Módulo 8 — Reputación y comunicaciones *(v2.0)*

### HU-8.1 — Calificar al veterinario tras una consulta atendida

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-8.1.1 | Solo el propietario de una cita atendida debe poder calificar, una vez por cita. | Restricción | No se puede recalificar una cita. | Alta |
| RE-8.1.2 | Al marcarse la cita como atendida, el sistema debe habilitar la encuesta y notificar. | Funcional | Al marcarse una cita como atendida, el propietario recibe la invitación a calificar y la encuesta queda habilitada en su portal. | Media |
| RE-8.1.3 | El sistema debe registrar estrellas (1 a 5, obligatorio) y un comentario opcional. | Validación | Sin calificación no se guarda. | Media |
| RE-8.1.4 | Guardar la reseña debe recalcular el promedio del veterinario y auditarse. | Funcional | Al guardar una reseña válida, el promedio del veterinario se recalcula y la operación queda en auditoría. | Media |
| RE-8.1.5 | Solo deben contar para la reputación las reseñas de propietarios con correo verificado cuya cita tenga una consulta registrada. | Restricción | Una reseña de una cita sin consulta no altera el promedio. | Alta |
| RE-8.1.6 | La clínica y sus veterinarios no deben poder editar ni eliminar reseñas. | Seguridad | No existe la opción y la petición directa al servidor se rechaza (403). | Alta |
| RE-8.1.7 | El sistema debe impedir que una persona se califique a sí misma. | Restricción | Si el propietario y el veterinario son la misma persona, no se habilita la encuesta. | Media |

**Reglas de negocio:** RN-801, RN-804, RN-805, RN-G18

### HU-8.2 — Perfil público del veterinario

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-8.2.1 | El sistema debe mostrar el promedio, el número de reseñas y las especialidades del veterinario. | Funcional | El perfil muestra el promedio, el número de reseñas y las especialidades del veterinario. | Media |
| RE-8.2.2 | El promedio no debe mostrarse hasta alcanzar 5 reseñas válidas. | Restricción | Con 4 reseñas no se muestra el promedio; con 5, sí. | Media |

**Reglas de negocio:** RN-802

### HU-8.3 — Plantillas de correos y notificaciones por clínica

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-8.3.1 | El sistema debe permitir editar el asunto, el contenido y el estilo de las plantillas de la clínica. | Funcional | El administrador edita el asunto, el contenido y el estilo de una plantilla y los cambios quedan guardados. | Media |
| RE-8.3.2 | El siguiente envío debe usar el nuevo formato. | Funcional | El siguiente correo de ese tipo sale con el asunto y el contenido editados. | Media |
| RE-8.3.3 | Las plantillas deben ser propias de cada clínica. | Restricción | Las plantillas editadas por la clínica A no cambian los correos de la clínica B. | Media |

**Reglas de negocio:** RN-806

### HU-8.4 — Tiempos de envío de recordatorios configurables

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-8.4.1 | El sistema debe permitir configurar los días de anticipación y la hora de envío. | Funcional | El administrador cambia la anticipación a 3 días y la hora a 9:00, y la configuración queda guardada. | Media |
| RE-8.4.2 | Los recordatorios deben enviarse según los tiempos configurados. | Restricción | Con anticipación de 3 días y hora 9:00, el recordatorio sale 3 días antes del vencimiento a las 9:00. | Media |

**Reglas de negocio:** RN-807, RN-303

### HU-8.5 — Bitácora de correos y notificaciones

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-8.5.1 | El sistema debe mostrar el historial de envíos con fecha, destinatario y estado. | Funcional | La bitácora lista cada envío con su fecha, destinatario y estado. | Media |
| RE-8.5.2 | El sistema debe permitir identificar los envíos fallidos. | Funcional | Se puede filtrar la bitácora para ver solo los envíos fallidos. | Media |

**Reglas de negocio:** RN-808

## Módulo 9 — Público e institucional

### HU-9.1 — Página pública y políticas legales

| ID | Requisito específico | Tipo | Criterio de aceptación | Prioridad |
|---|---|---|---|---|
| RE-9.1.1 | El sistema debe presentar el servicio en una página pública (landing). | Funcional | La landing es accesible y describe el servicio. | Baja |
| RE-9.1.2 | El sistema debe ofrecer páginas de privacidad, términos y cookies. | Funcional | Las páginas legales están disponibles. | Baja |
| RE-9.1.3 | La política de datos debe reflejar la Ley 1581 de 2012. | Restricción | La política refleja la normativa colombiana. | Baja |

**Reglas de negocio:** RN-G07

## Matriz de trazabilidad RN → HU → RE

| HU | Reglas de negocio | Requisitos específicos |
|---|---|---|
| HU-0.1 | RN-001, RN-002, RN-010, RN-011, RN-G06, RN-G19, RN-G20 | RE-0.1.1, RE-0.1.2, RE-0.1.3, RE-0.1.4, RE-0.1.5, RE-0.1.6, RE-0.1.7, RE-0.1.8, RE-0.1.9, RE-0.1.10 |
| HU-0.2 | RN-002, RN-003, RN-005, RN-G11 | RE-0.2.1, RE-0.2.2, RE-0.2.3, RE-0.2.4, RE-0.2.5 |
| HU-0.3 | RN-004, RN-012, RN-804, RN-G13, RN-G14 | RE-0.3.1, RE-0.3.2, RE-0.3.3, RE-0.3.4, RE-0.3.5, RE-0.3.6 |
| HU-0.4 | RN-003, RN-005, RN-006, RN-420 | RE-0.4.1, RE-0.4.2, RE-0.4.3, RE-0.4.4, RE-0.4.5, RE-0.4.6, RE-0.4.7 |
| HU-0.5 | RN-007, RN-008, RN-013 | RE-0.5.1, RE-0.5.2, RE-0.5.3, RE-0.5.4, RE-0.5.5 |
| HU-T.1 | RN-G01, RN-G03, RN-G09 | RE-T.1.1, RE-T.1.2, RE-T.1.3, RE-T.1.4, RE-T.1.5 |
| HU-T.2 | RN-G03, RN-G22 | RE-T.2.1, RE-T.2.2, RE-T.2.3, RE-T.2.4, RE-T.2.5 |
| HU-T.3 | RN-G04 | RE-T.3.1, RE-T.3.2, RE-T.3.3, RE-T.3.4 |
| HU-T.4 | RN-G01, RN-G05 | RE-T.4.1, RE-T.4.2, RE-T.4.3 |
| HU-T.5 | RN-G05, RN-G06, RN-G07, RN-G23, RN-G24, RN-705 | RE-T.5.1, RE-T.5.2, RE-T.5.3, RE-T.5.4, RE-T.5.5, RE-T.5.6, RE-T.5.7, RE-T.5.8, RE-T.5.9 |
| HU-T.6 | RN-G02, RN-409 | RE-T.6.1, RE-T.6.2, RE-T.6.3, RE-T.6.4 |
| HU-T.7 | RN-G08, RN-701, RN-G06, RN-705 | RE-T.7.1, RE-T.7.2, RE-T.7.3, RE-T.7.4, RE-T.7.5 |
| HU-T.8 | RN-G05 | RE-T.8.1, RE-T.8.2, RE-T.8.3, RE-T.8.4 |
| HU-T.9 | RN-704 | RE-T.9.1, RE-T.9.2, RE-T.9.3, RE-T.9.4 |
| HU-T.10 | RN-G01, RN-G02 | RE-T.10.1, RE-T.10.2, RE-T.10.3 |
| HU-T.11 | RN-G08, RN-701 | RE-T.11.1, RE-T.11.2, RE-T.11.3 |
| HU-T.12 | RN-G03, RN-G04, RN-G10, RN-G11, RN-G12 | RE-T.12.1, RE-T.12.2, RE-T.12.3 |
| HU-T.13 | RN-G03, RN-G15 | RE-T.13.1, RE-T.13.2, RE-T.13.3, RE-T.13.4, RE-T.13.5 |
| HU-T.14 | RN-G08, RN-701, RN-G05 | RE-T.14.1, RE-T.14.2, RE-T.14.3 |
| HU-T.15 | RN-G13, RN-G14, RN-001, RN-004 | RE-T.15.1, RE-T.15.2, RE-T.15.3, RE-T.15.4, RE-T.15.5 |
| HU-T.16 | RN-G17, RN-G05 | RE-T.16.1, RE-T.16.2, RE-T.16.3, RE-T.16.4 |
| HU-T.17 | RN-G01, RN-G06, RN-G13, RN-G18 | RE-T.17.1, RE-T.17.2, RE-T.17.3, RE-T.17.4, RE-T.17.5 |
| HU-T.18 | RN-G20, RN-G21, RN-G19, RN-G12, RN-109 | RE-T.18.1, RE-T.18.2, RE-T.18.3, RE-T.18.4 |
| HU-T.19 | RN-G19, RN-G07, RN-G16 | RE-T.19.1, RE-T.19.2, RE-T.19.3, RE-T.19.4 |
| HU-1.1 | RN-101, RN-102, RN-106, RN-107, RN-110, RN-111 | RE-1.1.1, RE-1.1.2, RE-1.1.3, RE-1.1.4, RE-1.1.5, RE-1.1.6 |
| HU-1.2 | RN-101, RN-103, RN-109, RN-G06 | RE-1.2.1, RE-1.2.2, RE-1.2.3 |
| HU-1.3 | RN-105 | RE-1.3.1, RE-1.3.2, RE-1.3.3, RE-1.3.4 |
| HU-1.4 | RN-104, RN-105, RN-108, RN-110 | RE-1.4.1, RE-1.4.2, RE-1.4.3, RE-1.4.4, RE-1.4.5 |
| HU-1.5 | RN-003, RN-102, RN-110, RN-111 | RE-1.5.1, RE-1.5.2, RE-1.5.3 |
| HU-2.1 | RN-201, RN-202, RN-203, RN-113 | RE-2.1.1, RE-2.1.2, RE-2.1.3, RE-2.1.4, RE-2.1.5 |
| HU-2.2 | RN-102, RN-203, RN-208 | RE-2.2.1, RE-2.2.2, RE-2.2.3, RE-2.2.4 |
| HU-2.3 | RN-204, RN-205 | RE-2.3.1, RE-2.3.2, RE-2.3.3, RE-2.3.4 |
| HU-2.4 | RN-205 | RE-2.4.1, RE-2.4.2, RE-2.4.3 |
| HU-2.5 | RN-206, RN-113 | RE-2.5.1, RE-2.5.2, RE-2.5.3, RE-2.5.4 |
| HU-2.6 | RN-204, RN-206 | RE-2.6.1, RE-2.6.2, RE-2.6.3 |
| HU-2.7 | RN-201, RN-207, RN-208, RN-G02 | RE-2.7.1, RE-2.7.2, RE-2.7.3 |
| HU-2.8 | RN-209, RN-210, RN-212, RN-202 | RE-2.8.1, RE-2.8.2, RE-2.8.3, RE-2.8.4, RE-2.8.5 |
| HU-2.9 | RN-209, RN-211, RN-212, RN-113 | RE-2.9.1, RE-2.9.2, RE-2.9.3, RE-2.9.4 |
| HU-2.10 | RN-112, RN-113 | RE-2.10.1, RE-2.10.2, RE-2.10.3, RE-2.10.4 |
| HU-2.11 | RN-411, RN-416, RN-417, RN-418, RN-424, RN-425, RN-209 | RE-2.11.1, RE-2.11.2, RE-2.11.3, RE-2.11.4, RE-2.11.5, RE-2.11.6, RE-2.11.7 |
| HU-3.1 | RN-301, RN-306 | RE-3.1.1, RE-3.1.2, RE-3.1.3 |
| HU-3.2 | RN-303, RN-304, RN-305 | RE-3.2.1, RE-3.2.2, RE-3.2.3, RE-3.2.4, RE-3.2.5 |
| HU-3.3 | RN-307 | RE-3.3.1, RE-3.3.2, RE-3.3.3 |
| HU-3.4 | RN-302 | RE-3.4.1, RE-3.4.2, RE-3.4.3 |
| HU-3.5 | RN-301 | RE-3.5.1, RE-3.5.2, RE-3.5.3 |
| HU-3.6 | RN-303, RN-304, RN-305 | RE-3.6.1, RE-3.6.2, RE-3.6.3, RE-3.6.4 |
| HU-4.1 | RN-401, RN-402, RN-403, RN-404 | RE-4.1.1, RE-4.1.2, RE-4.1.3, RE-4.1.4, RE-4.1.5 |
| HU-4.2 | RN-405 | RE-4.2.1, RE-4.2.2, RE-4.2.3, RE-4.2.4 |
| HU-4.3 | RN-406, RN-407, RN-409, RN-410 | RE-4.3.1, RE-4.3.2, RE-4.3.3, RE-4.3.4, RE-4.3.5, RE-4.3.6, RE-4.3.7, RE-4.3.8, RE-4.3.9 |
| HU-4.4 | RN-404 | RE-4.4.1, RE-4.4.2, RE-4.4.3, RE-4.4.4 |
| HU-4.5 | RN-401 | RE-4.5.1, RE-4.5.2, RE-4.5.3 |
| HU-4.6 | RN-401, RN-403 | RE-4.6.1, RE-4.6.2 |
| HU-4.7 | RN-405, RN-408 | RE-4.7.1, RE-4.7.2, RE-4.7.3 |
| HU-4.8 | RN-402, RN-703 | RE-4.8.1, RE-4.8.2, RE-4.8.3 |
| HU-4.9 | RN-401, RN-402 | RE-4.9.1, RE-4.9.2, RE-4.9.3 |
| HU-4.10 | RN-404, RN-405 | RE-4.10.1, RE-4.10.2, RE-4.10.3 |
| HU-4.11 | RN-706, RN-703, RN-415 | RE-4.11.1, RE-4.11.2, RE-4.11.3, RE-4.11.4, RE-4.11.5 |
| HU-4.12 | RN-414, RN-415 | RE-4.12.1, RE-4.12.2, RE-4.12.3, RE-4.12.4, RE-4.12.5 |
| HU-4.13 | RN-411, RN-415, RN-401, RN-402, RN-420, RN-422 | RE-4.13.1, RE-4.13.2, RE-4.13.3, RE-4.13.4, RE-4.13.5, RE-4.13.6, RE-4.13.7 |
| HU-4.14 | RN-412, RN-413, RN-415, RN-428, RN-429 | RE-4.14.1, RE-4.14.2, RE-4.14.3, RE-4.14.4, RE-4.14.5, RE-4.14.6, RE-4.14.7 |
| HU-4.15 | RN-411, RN-415, RN-420, RN-421, RN-427 | RE-4.15.1, RE-4.15.2, RE-4.15.3, RE-4.15.4, RE-4.15.5, RE-4.15.6, RE-4.15.7, RE-4.15.8 |
| HU-4.16 | RN-803 | RE-4.16.1, RE-4.16.2 |
| HU-4.17 | RN-423, RN-411 | RE-4.17.1, RE-4.17.2, RE-4.17.3, RE-4.17.4 |
| HU-4.18 | RN-419, RN-209 | RE-4.18.1, RE-4.18.2, RE-4.18.3, RE-4.18.4 |
| HU-4.19 | RN-426, RN-408, RN-412 | RE-4.19.1, RE-4.19.2, RE-4.19.3, RE-4.19.4 |
| HU-5.1 | RN-G02 | RE-5.1.1, RE-5.1.2, RE-5.1.3, RE-5.1.4, RE-5.1.5, RE-5.1.6, RE-5.1.7, RE-5.1.8, RE-5.1.9, RE-5.1.10 |
| HU-5.2 | RN-101, RN-G06, RN-501 | RE-5.2.1, RE-5.2.2, RE-5.2.3, RE-5.2.4, RE-5.2.5 |
| HU-5.3 | RN-401, RN-402, RN-501, RN-109, RN-110 | RE-5.3.1, RE-5.3.2, RE-5.3.3, RE-5.3.4, RE-5.3.5 |
| HU-5.4 | RN-104, RN-108, RN-G02 | RE-5.4.1, RE-5.4.2, RE-5.4.3 |
| HU-5.5 | RN-206, RN-G02, RN-114 | RE-5.5.1, RE-5.5.2, RE-5.5.3 |
| HU-5.6 | RN-405, RN-401, RN-G02 | RE-5.6.1, RE-5.6.2, RE-5.6.3, RE-5.6.4 |
| HU-5.7 | RN-G02 | RE-5.7.1, RE-5.7.2, RE-5.7.3, RE-5.7.4 |
| HU-5.8 | RN-109, RN-G06 | RE-5.8.1, RE-5.8.2, RE-5.8.3, RE-5.8.4, RE-5.8.5 |
| HU-5.9 | RN-109, RN-114, RN-G13 | RE-5.9.1, RE-5.9.2, RE-5.9.3, RE-5.9.4 |
| HU-5.10 | RN-110, RN-502, RN-503 | RE-5.10.1, RE-5.10.2, RE-5.10.3, RE-5.10.4, RE-5.10.5 |
| HU-5.11 | RN-502, RN-503, RN-504, RN-505 | RE-5.11.1, RE-5.11.2, RE-5.11.3, RE-5.11.4, RE-5.11.5, RE-5.11.6 |
| HU-5.12 | RN-113, RN-115, RN-G07 | RE-5.12.1, RE-5.12.2, RE-5.12.3, RE-5.12.4 |
| HU-5.13 | RN-109, RN-110, RN-115 | RE-5.13.1, RE-5.13.2, RE-5.13.3, RE-5.13.4 |
| HU-5.14 | RN-G16, RN-G07 | RE-5.14.1, RE-5.14.2, RE-5.14.3, RE-5.14.4, RE-5.14.5 |
| HU-6.1 | RN-G01, RN-407, RN-409 | RE-6.1.1, RE-6.1.2, RE-6.1.3, RE-6.1.4, RE-6.1.5, RE-6.1.6, RE-6.1.7 |
| HU-6.2 | RN-G01, RN-408, RN-409 | RE-6.2.1, RE-6.2.2, RE-6.2.3, RE-6.2.4, RE-6.2.5, RE-6.2.6 |
| HU-6.3 | RN-701 | RE-6.3.1, RE-6.3.2, RE-6.3.3, RE-6.3.4 |
| HU-6.4 | RN-701 | RE-6.4.1, RE-6.4.2, RE-6.4.3, RE-6.4.4 |
| HU-6.5 | RN-701 | RE-6.5.1, RE-6.5.2, RE-6.5.3 |
| HU-7.1 | RN-703, RN-701 | RE-7.1.1, RE-7.1.2, RE-7.1.3, RE-7.1.4 |
| HU-7.2 | RN-702 | RE-7.2.1, RE-7.2.2, RE-7.2.3 |
| HU-7.3 | RN-705, RN-009, RN-701 | RE-7.3.1, RE-7.3.2, RE-7.3.3, RE-7.3.4, RE-7.3.5 |
| HU-7.4 | RN-009, RN-415, RN-421, RN-701, RN-427 | RE-7.4.1, RE-7.4.2, RE-7.4.3, RE-7.4.4, RE-7.4.5, RE-7.4.6 |
| HU-7.5 | RN-403, RN-303, RN-703, RN-421, RN-422, RN-426, RN-428, RN-429 | RE-7.5.1, RE-7.5.2, RE-7.5.3 |
| HU-8.1 | RN-801, RN-804, RN-805, RN-G18 | RE-8.1.1, RE-8.1.2, RE-8.1.3, RE-8.1.4, RE-8.1.5, RE-8.1.6, RE-8.1.7 |
| HU-8.2 | RN-802 | RE-8.2.1, RE-8.2.2 |
| HU-8.3 | RN-806 | RE-8.3.1, RE-8.3.2, RE-8.3.3 |
| HU-8.4 | RN-807, RN-303 | RE-8.4.1, RE-8.4.2 |
| HU-8.5 | RN-808 | RE-8.5.1, RE-8.5.2 |
| HU-9.1 | RN-G07 | RE-9.1.1, RE-9.1.2, RE-9.1.3 |
