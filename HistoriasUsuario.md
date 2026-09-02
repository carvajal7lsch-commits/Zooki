# Historias de Usuario — Proyecto Zooki

> **Revisión 2.1** · 55 historias de usuario organizadas por módulos · SENA ADSO — Ficha 3142784

Cadena de trazabilidad: **Regla de Negocio (RN) → Historia de Usuario (HU) → Requisito específico (RE)**. Origen: `ZOOK-xx` (backlog Jira), `Nuevo` (funcionalidad ya existente ahora documentada), `VD-xxx` (derivada del análisis de vacíos), `Deseable` (función propuesta aún no construida).

## Módulo T — Acceso, seguridad y administración

### HU-17 — Iniciar sesión y autenticación

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 5 pts | ZOOK-22 |

> Como usuario del sistema, quiero iniciar sesión con credenciales seguras para acceder a las funciones autorizadas según mi rol.

**Criterios de aceptación:**

- Login con documento/usuario y contraseña validados contra la base de datos.
- Contraseñas almacenadas con hashing bcrypt + salt (RNF-03).
- Sesión con PHP nativo; además, inicio de sesión con Google (OAuth 2.0).
- Según el rol se redirige al panel correspondiente.
- Mensaje genérico ante credenciales inválidas.

**Reglas de negocio:** RN-G01, RN-G03, RN-G09 · **Dependencias:** —

### HU-39 — Cambiar mi contraseña

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 3 pts | Nuevo |

> Como usuario autenticado de cualquier rol, quiero cambiar mi contraseña desde mi perfil para mantener la seguridad de mi cuenta.

**Criterios de aceptación:**

- Solicita la contraseña actual (salvo cuentas creadas con Google).
- Exige una nueva contraseña que cumpla la política mínima.
- Actualiza el hash y confirma el cambio.
- Si la contraseña actual es incorrecta, muestra error.

**Reglas de negocio:** RN-G03 · **Dependencias:** HU-17

### HU-40 — Recuperar contraseña olvidada

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 5 pts | Nuevo |

> Como usuario, quiero recuperar el acceso si olvidé mi contraseña solicitando un enlace de restablecimiento a mi correo, para volver a entrar sin depender del administrador.

**Criterios de aceptación:**

- Solicito el restablecimiento con mi correo y recibo un mensaje genérico (no revela si el correo existe).
- Recibo un enlace con token temporal.
- El enlace es de un solo uso y expira.
- Puedo definir una nueva contraseña válida.

**Reglas de negocio:** RN-G04 · **Dependencias:** —

### HU-41 — Cerrar sesión

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Baja | Implementada | 1 pts | Nuevo |

> Como usuario autenticado, quiero cerrar mi sesión para proteger mi cuenta al terminar de usar el sistema.

**Criterios de aceptación:**

- El botón de cerrar sesión destruye la sesión activa.
- Tras cerrar, las rutas protegidas exigen autenticarse de nuevo.
- El cierre de sesión queda registrado en auditoría.

**Reglas de negocio:** RN-G01, RN-G05 · **Dependencias:** HU-17

### HU-42 — Ver y actualizar mi perfil y datos de contacto

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 3 pts | Nuevo |

> Como usuario, quiero ver y actualizar mis datos de contacto (teléfono, correo) desde mi perfil para mantener mi información al día.

**Criterios de aceptación:**

- Veo los datos de mi perfil.
- Puedo actualizar teléfono y correo.
- El correo nuevo se valida como único.
- El cambio persiste y se refleja de inmediato.

**Reglas de negocio:** RN-G06, RN-G07 · **Dependencias:** HU-17

### HU-45 — Gestionar mis notificaciones internas

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 3 pts | Nuevo |

> Como usuario interno (administrador, veterinario o recepcionista), quiero ver mis notificaciones dentro del sistema y marcarlas como leídas para estar al tanto de los eventos operativos.

**Criterios de aceptación:**

- Veo un contador de notificaciones no leídas.
- Veo la lista de notificaciones recientes.
- Puedo marcar una o todas como leídas.
- Solo veo las notificaciones dirigidas a mi rol o usuario.

**Reglas de negocio:** RN-G02 · **Dependencias:** HU-17

### HU-22 — Gestión de usuarios del sistema

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 5 pts | ZOOK-26 |

> Como administrador, quiero crear, editar y desactivar usuarios del sistema para gestionar quién tiene acceso y con qué permisos.

**Criterios de aceptación:**

- CRUD de usuarios: nombre, correo, rol y estado (activo/inactivo).
- Solo usuarios con rol administrador acceden a este módulo.
- No se permite eliminar al único usuario administrador.
- Cambio de contraseña con validación de fortaleza.

**Reglas de negocio:** RN-G08, RN-501 · **Dependencias:** HU-17

### HU-24 — Logs de auditoría y seguridad

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 5 pts | ZOOK-28 |

> Como administrador del sistema, quiero consultar un log de auditoría con las operaciones críticas para detectar accesos no autorizados o cambios sospechosos.

**Criterios de aceptación:**

- Registro de login exitoso/fallido y de creación, edición y eliminación.
- Campos: usuario, fecha/hora, IP, operación, tabla y datos previos/nuevos.
- Vista filtrable por usuario, operación y rango de fechas.
- Logs inmutables (solo lectura para el administrador).

**Reglas de negocio:** RN-G05 · **Dependencias:** HU-17

### HU-23 — Backup automático de la base de datos

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 3 pts | ZOOK-29 |

> Como administrador del sistema, quiero respaldar automáticamente la base de datos cada 24 horas para prevenir la pérdida de información clínica.

**Criterios de aceptación:**

- Script que ejecuta mysqldump y comprime el archivo.
- Copia almacenada en un directorio externo al servidor principal.
- Retención de los últimos respaldos (rotación automática).
- Registro de la ejecución en el log del sistema.

**Reglas de negocio:** RN-504 · **Dependencias:** —

### HU-32 — Autorización central por rol (RBAC real)

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 8 pts | VD-SEG-03/04 |

> Como administrador del sistema, quiero que cada acción valide de forma centralizada el rol autorizado para impedir accesos indebidos aunque un endpoint se invoque directamente.

**Criterios de aceptación:**

- Existe una matriz acción → roles permitidos aplicada antes de ejecutar cualquier acción.
- Un rol sin permiso recibe 403.
- El control no depende de la interfaz.
- Se cubren todos los endpoints AJAX.

**Reglas de negocio:** RN-G01, RN-G02 · **Dependencias:** HU-17

> _Nota: Deriva del análisis de vacíos (VD-SEG-03/04). El control central (`Security::validateRole`) aplica una matriz acción → roles antes del enrutador, cubriendo las 108 acciones no públicas._

### HU-33 — Corregir escalada de privilegios y proteger al último administrador

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 5 pts | VD-SEG-01/02 |

> Como administrador, quiero que solo un administrador pueda crear/editar usuarios y asignar roles, y que no se pueda desactivar al último administrador, para evitar la toma de control del sistema.

**Criterios de aceptación:**

- Crear/editar usuario exige rol administrador.
- El rol asignable se valida contra una lista permitida.
- No se puede desactivar ni eliminar al último administrador activo.

**Reglas de negocio:** RN-G08, RN-501 · **Dependencias:** HU-22

> _Nota: Deriva del análisis de vacíos (VD-SEG-01/02, severidad crítica)._

### HU-36 — Política de contraseñas, verificación de correo y OAuth seguro

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 5 pts | VD-SEG-07/08/10 |

> Como responsable del sistema, quiero una política de contraseñas única y fuerte, verificación de correo en el registro y validación de la audiencia del token de Google, para reducir el riesgo de cuentas comprometidas.

**Criterios de aceptación:**

- Política única (mínimo 8 + complejidad) en todos los flujos.
- El registro exige verificar el correo antes de activar.
- El login con Google valida aud/iss contra el client_id propio.

**Reglas de negocio:** RN-G03, RN-G04, RN-G10, RN-G11, RN-G12 · **Dependencias:** HU-17

> _Nota: Deriva del análisis de vacíos (VD-SEG-07/08/10). La política vive en `helpers/PoliticaPassword.php` y la consumen los cuatro flujos; el auto-registro ya no inicia sesión solo, deja una verificación pendiente en `verificaciones_email`; y `helpers/GoogleToken.php` compara `aud`/`iss` contra el client_id propio antes de aceptar el token._

### HU-38 — Endurecer rate limiting y reducir enumeración/fuga de información

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 3 pts | VD-SEG-05/06/09 |

> Como responsable del sistema, quiero un límite de intentos robusto y evitar la enumeración de usuarios y la fuga de errores técnicos, para dificultar los ataques.

**Criterios de aceptación:**

- El límite de intentos se guarda del lado servidor por IP/cuenta y no se evade sin cookie.
- Las verificaciones de documento/correo no revelan existencia ni permiten abuso.
- Los mensajes de error al cliente son genéricos.

**Reglas de negocio:** RN-G03 · **Dependencias:** HU-17

> _Nota: Deriva del análisis de vacíos (VD-SEG-05/06/09). El contador de intentos pasó de `$_SESSION` a la tabla `intentos_login`, con límite por IP y por cuenta, así que descartar la cookie ya no lo reinicia. Los mensajes de login son idénticos exista o no la cuenta. Las verificaciones de documento/correo quedan limitadas a 20 por IP cada 15 minutos y, desde HU-36, el registro ya no habilita la cuenta por sí solo: la existencia que revela el formulario no alcanza para usar una cuenta ajena._

### HU-54 — Restablecer la contraseña de un usuario (administrador)

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Pendiente | 3 pts | Deseable |

> Como administrador, quiero restablecer la contraseña de un usuario desde el panel para ayudarlo cuando no puede acceder a su cuenta.

**Criterios de aceptación:**

- Puedo generar un restablecimiento de contraseña para un usuario.
- El usuario debe cambiarla en su próximo ingreso.
- Solo el administrador puede hacerlo.
- La acción queda registrada en auditoría.

**Reglas de negocio:** RN-G08, RN-501, RN-G05 · **Dependencias:** HU-22

> _Nota: Función deseable propuesta (no construida)._

## Módulo 1 — Mascotas y propietarios

### HU-01 — Registrar mascota

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 5 pts | ZOOK-5 |

> Como veterinario, quiero registrar una mascota con sus datos básicos para tener una ficha digital completa del paciente.

**Criterios de aceptación:**

- El formulario exige nombre, especie, raza, fecha de nacimiento, peso, sexo y color.
- Se puede subir una fotografía (JPG/PNG).
- Al guardar, la mascota aparece en el listado y en la búsqueda.
- No se puede guardar sin propietario asignado.

**Reglas de negocio:** RN-101, RN-102, RN-106, RN-107 · **Dependencias:** HU-02

### HU-02 — Registrar propietario

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 3 pts | ZOOK-6 |

> Como recepcionista, quiero registrar los datos del propietario para poder contactarlo y vincularle sus mascotas.

**Criterios de aceptación:**

- Campos obligatorios: nombre, tipo y número de documento, teléfono y correo.
- No se permiten documentos ni correos duplicados.
- Desde el perfil del propietario se listan todas sus mascotas.

**Reglas de negocio:** RN-101, RN-103, RN-G06 · **Dependencias:** —

### HU-03 — Buscar paciente

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 3 pts | ZOOK-7 |

> Como veterinario o recepcionista, quiero buscar mascotas rápidamente para acceder a su ficha sin navegar por listados largos.

**Criterios de aceptación:**

- Búsqueda por nombre de mascota, nombre del propietario o número de documento.
- Resultados en menos de 2 segundos con un mínimo de 3 caracteres.
- Los resultados muestran nombre, especie, propietario y foto miniatura.

**Reglas de negocio:** RN-105 · **Dependencias:** HU-01

### HU-04 — Editar y desactivar mascota

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 3 pts | ZOOK-8 |

> Como veterinario, quiero actualizar los datos de una mascota o marcarla como inactiva para mantener la información vigente sin perder el historial.

**Criterios de aceptación:**

- Se puede editar cualquier campo de la ficha.
- Al guardar se registra en el log la fecha, el usuario y el campo modificado.
- Marcar como inactiva la oculta de las búsquedas activas pero conserva su historial.
- No se permite eliminar permanentemente una mascota con historial clínico.

**Reglas de negocio:** RN-104, RN-105, RN-108 · **Dependencias:** HU-01

## Módulo 2 — Historia clínica

### HU-05 — Registrar consulta clínica

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 8 pts | ZOOK-9 |

> Como veterinario, quiero registrar una consulta médica completa para llevar la trazabilidad de la salud del paciente.

**Criterios de aceptación:**

- Campos: fecha/hora, motivo, anamnesis, examen físico, diagnóstico y plan de tratamiento.
- Se genera un número de historia clínica único en la primera consulta.
- La consulta queda vinculada a la mascota y visible en su historial.
- No se puede registrar una consulta sin diagnóstico.

**Reglas de negocio:** RN-201, RN-202, RN-203 · **Dependencias:** HU-01

### HU-06 — Adjuntar archivos clínicos

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 5 pts | ZOOK-10 |

> Como veterinario, quiero adjuntar imágenes y documentos a una consulta para tener evidencia visual del estado del paciente.

**Criterios de aceptación:**

- Formatos permitidos: JPG, PNG, PDF.
- Tamaño máximo por archivo: 10 MB.
- Los archivos se almacenan en carpeta protegida del servidor.
- Solo usuarios autenticados pueden descargarlos.
- Se pueden adjuntar múltiples archivos por consulta.

**Reglas de negocio:** RN-204, RN-205 · **Dependencias:** HU-05

### HU-07 — Registrar tratamiento

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 3 pts | ZOOK-11 |

> Como veterinario, quiero registrar el tratamiento prescrito en una consulta para que quede documentado en la historia clínica.

**Criterios de aceptación:**

- Campos: medicamento, dosis, vía de administración, duración y observaciones.
- El tratamiento queda vinculado a la consulta.
- Se pueden registrar múltiples tratamientos por consulta.
- Visible en el resumen de la historia clínica.

**Reglas de negocio:** RN-205 · **Dependencias:** HU-05

### HU-08 — Ver historial clínico completo

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 5 pts | ZOOK-12 |

> Como veterinario, quiero ver todas las consultas de una mascota en una sola pantalla para tener contexto clínico completo antes de atender.

**Criterios de aceptación:**

- Vista cronológica (más reciente primero) con acordeón expandible.
- Cada entrada muestra fecha, motivo, diagnóstico, tratamientos y archivos.
- Carga en menos de 3 segundos para historiales de hasta 100 consultas.
- Incluye el número de HC y un resumen de vacunas.

**Reglas de negocio:** RN-206 · **Dependencias:** HU-05

### HU-34 — Atomicidad y feedback de adjuntos en el registro de consulta

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Pendiente | 5 pts | VD-HC-01/02 |

> Como veterinario, quiero que al registrar una consulta todo se guarde de forma atómica y se me informe si algún adjunto fue rechazado, para no perder evidencia clínica sin darme cuenta.

**Criterios de aceptación:**

- Consulta, HC, archivos y tratamientos se guardan en una transacción (todo o nada).
- Si un adjunto es rechazado, se informa el motivo por archivo.
- No se reporta éxito con datos parciales.

**Reglas de negocio:** RN-204, RN-206 · **Dependencias:** HU-05

> _Nota: Deriva del análisis de vacíos (VD-HC-01/02)._

### HU-35 — Validación y control de acceso en registros clínicos y de mascota

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 5 pts | VD-VAC-01, VD-HC-03 |

> Como responsable del sistema, quiero que registrar consultas, vacunas o desparasitaciones exija el rol clínico y verifique la propiedad/existencia de la mascota, para impedir la manipulación de datos ajenos.

**Criterios de aceptación:**

- Registrar consulta/vacuna/desparasitación exige rol clínico.
- Se valida que la mascota exista.
- Se verifica que el actor tenga permiso sobre esa mascota.
- Las entradas se validan antes de guardar.

**Reglas de negocio:** RN-201, RN-207, RN-208, RN-G02 · **Dependencias:** HU-05

> _Nota: Deriva del análisis de vacíos (VD-VAC-01, VD-HC-03, VD-SEG-04). Los tres registros clínicos validan la mascota con `Mascota::getPropietarioSiActiva` y las entradas con `ValidadorClinico` antes de guardar. El permiso sobre la mascota se interpreta según RN-207 y RN-208 para el rol clínico, y según RN-G02 para el propietario en el portal._

## Módulo 3 — Vacunación, desparasitación y recordatorios

### HU-09 — Registrar vacunación

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 5 pts | ZOOK-13 |

> Como veterinario, quiero registrar las vacunas aplicadas a una mascota para llevar el control de su esquema de vacunación.

**Criterios de aceptación:**

- Campos: nombre, laboratorio, lote, fecha de aplicación y próxima dosis.
- Al guardar, la vacuna aparece en el calendario de la mascota.
- Se genera una alerta 7 días antes de la próxima dosis.
- El panel muestra las vacunaciones pendientes de la semana.

**Reglas de negocio:** RN-301, RN-306 · **Dependencias:** HU-01

### HU-10 — Enviar recordatorio por correo

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 5 pts | ZOOK-14 |

> Como sistema, quiero enviar correos automáticos al propietario para que no olvide las fechas de vacunación y desparasitación.

**Criterios de aceptación:**

- Correo enviado 7 días y 1 día antes del vencimiento.
- Incluye nombre de la mascota, tipo y fecha.
- No se envía si el propietario no tiene correo.
- Queda registro del envío en la base de datos.

**Reglas de negocio:** RN-303, RN-304, RN-305 · **Dependencias:** HU-09

### HU-11 — Enviar recordatorio por WhatsApp

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Pendiente (futuro) | 5 pts | ZOOK-15 |

> Como sistema, quiero enviar mensajes de WhatsApp al propietario para aumentar la tasa de apertura de los recordatorios.

**Criterios de aceptación:**

- Mensaje con el mismo contenido del correo.
- Solo si el propietario tiene número de WhatsApp.
- Usa la API oficial de WhatsApp Business (Meta).
- El envío queda registrado.

**Reglas de negocio:** RN-307 · **Dependencias:** HU-10

> _Nota: No implementada en la v1.8.0. Planificada como evolución futura (canal actual = correo)._

### HU-12 — Registrar desparasitación

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 3 pts | ZOOK-16 |

> Como veterinario, quiero registrar y programar la desparasitación de la mascota para que el sistema genere alertas automáticas.

**Criterios de aceptación:**

- Tipos: interna y externa.
- Periodicidad configurable: mensual, trimestral o semestral.
- Al registrar, se calcula la próxima aplicación.
- Las alertas se comportan igual que las de vacunación.

**Reglas de negocio:** RN-302 · **Dependencias:** HU-01

### HU-20 — Panel de vacunaciones pendientes

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 3 pts | ZOOK-25 |

> Como veterinario, quiero ver un panel con las vacunaciones pendientes de la semana agrupadas por día y especie para planificar la agenda.

**Criterios de aceptación:**

- Muestra las vacunas con próxima dosis en los próximos 7 días.
- Agrupación por día de la semana y por especie.
- Contador por día visible a primera vista.
- Un clic lleva a la ficha de la mascota.

**Reglas de negocio:** RN-301 · **Dependencias:** HU-09

### HU-37 — Robustez de los recordatorios automáticos

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Pendiente | 3 pts | VD-REM-01/02/03 |

> Como sistema, quiero que los recordatorios se envíen de forma confiable aunque el cron falle un día, con reintentos y zona horaria correcta, para no dejar a los propietarios sin aviso.

**Criterios de aceptación:**

- Se usa una ventana de fechas con marca de enviado que recupera los no enviados.
- Los envíos fallidos se reintentan.
- El cálculo de fechas usa la zona horaria de la clínica.

**Reglas de negocio:** RN-303, RN-305 · **Dependencias:** HU-10

> _Nota: Deriva del análisis de vacíos (VD-REM-01/02/03)._

## Módulo 4 — Agenda de citas

### HU-13 — Agendar cita

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 8 pts | ZOOK-17 |

> Como recepcionista, quiero agendar citas verificando la disponibilidad del veterinario para evitar cruces de horario.

**Criterios de aceptación:**

- Campos: fecha, hora, mascota, motivo y veterinario asignado.
- No permite dos citas al mismo veterinario en el mismo horario.
- La cita respeta el horario de atención configurado.
- Al crear, se envía correo de confirmación.
- Las citas aparecen en el calendario.

**Reglas de negocio:** RN-401, RN-402, RN-403, RN-404 · **Dependencias:** HU-01

### HU-14 — Cancelar o reprogramar cita

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 5 pts | ZOOK-18 |

> Como veterinario o recepcionista, quiero cancelar o reprogramar una cita para mantener la agenda actualizada.

**Criterios de aceptación:**

- Se puede cancelar o cambiar la fecha/hora de una cita.
- Al cambiar, se notifica al propietario por correo.
- Las canceladas quedan con estado cancelada.
- No se puede reprogramar a un horario ya ocupado.

**Reglas de negocio:** RN-405 · **Dependencias:** HU-13

### HU-19 — Marcar cita como completada

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 2 pts | ZOOK-24 |

> Como veterinario o recepcionista, quiero marcar una cita como completada para llevar el control de atenciones realizadas y liberar la agenda.

**Criterios de aceptación:**

- Estado adicional "completada" en la cita.
- Solo las citas "programada"/"en curso" pueden completarse.
- Al completar, se vincula opcionalmente la consulta registrada.
- La cita completada aparece en el historial del día pero no en la agenda futura.

**Reglas de negocio:** RN-406 · **Dependencias:** HU-13

### HU-21 — Confirmación automática de cita por correo

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 3 pts | ZOOK-27 |

> Como sistema, quiero enviar un correo de confirmación automática al propietario al agendar la cita para que tenga constancia del horario y reduzca inasistencias.

**Criterios de aceptación:**

- Correo enviado inmediatamente tras guardar la cita.
- Incluye fecha, hora, mascota, motivo y dirección.
- No bloquea la respuesta del agendado.
- Un fallo de envío se registra como fallido.

**Reglas de negocio:** RN-404 · **Dependencias:** HU-13

### HU-27 — Registrar hora real de atención y gestionar retrasos

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Pendiente | 8 pts | VD-AGN-01 |

> Como veterinario o recepcionista, quiero registrar la hora real de inicio y fin de cada atención y que el sistema detecte los retrasos, para que la agenda coincida con la realidad y no se acumulen los choques.

**Criterios de aceptación:**

- Al iniciar y completar se sella la hora real.
- El sistema detecta cuando una atención excede su duración planificada.
- Alerta o recalcula el corrimiento de las citas siguientes del veterinario.

**Reglas de negocio:** RN-401 · **Dependencias:** HU-19

> _Nota: Deriva del análisis de vacíos (VD-AGN-01): el caso del calendario con retrasos en cascada._

### HU-28 — Buffer configurable entre citas

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Pendiente | 3 pts | VD-AGN-02 |

> Como administrador, quiero definir un tiempo de amortiguación entre citas para absorber pequeños retrasos sin generar choques.

**Criterios de aceptación:**

- Se configura un buffer entre citas.
- La disponibilidad lo respeta al agendar.
- El buffer se refleja en las sugerencias de horario.

**Reglas de negocio:** RN-401, RN-403 · **Dependencias:** HU-13

> _Nota: Deriva del análisis de vacíos (VD-AGN-02)._

### HU-29 — Estado "no asistió" y ausentismo

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Pendiente | 3 pts | VD-AGN-07 |

> Como recepcionista o veterinario, quiero marcar una cita como "no asistió" para liberar el espacio y medir el ausentismo.

**Criterios de aceptación:**

- Existe el estado "no asistió".
- Una cita marcada así libera el espacio.
- Se puede reportar la tasa de ausentismo.

**Reglas de negocio:** RN-405 · **Dependencias:** HU-13

> _Nota: Deriva del análisis de vacíos (VD-AGN-07)._

### HU-30 — Bloqueos de agenda del veterinario y días no laborables

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Pendiente | 5 pts | VD-AGN-08 |

> Como administrador, quiero registrar bloqueos del veterinario (almuerzo, permiso, incapacidad) y días festivos o cierres, para que no se agenden citas en esos periodos.

**Criterios de aceptación:**

- Se registran bloqueos por veterinario y por fecha.
- Se registran festivos y cierres puntuales.
- La disponibilidad excluye esos periodos.

**Reglas de negocio:** RN-402, RN-503 · **Dependencias:** HU-13

> _Nota: Deriva del análisis de vacíos (VD-AGN-08)._

### HU-31 — Endurecer la validación de disponibilidad

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Parcial | 5 pts | VD-AGN-03/04/05/06 |

> Como responsable del sistema, quiero que toda validación de disponibilidad se haga en el backend, con una sola fuente de horario que respete la duración y sin condición de carrera, para impedir citas inválidas o dobles.

**Criterios de aceptación:**

- La disponibilidad usa una sola lógica basada en horarios_clinica.
- Valida horario + duración en el backend.
- Usa transacción o restricción única para evitar dobles reservas.

**Reglas de negocio:** RN-401, RN-402 · **Dependencias:** HU-13

> _Nota: Deriva del análisis de vacíos (VD-AGN-03/04/05/06)._

### HU-51 — Confirmar asistencia a la cita desde el recordatorio

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Pendiente | 3 pts | Deseable |

> Como propietario, quiero confirmar o declinar mi asistencia desde el recordatorio de la cita para que la clínica sepa con anticipación si asistiré.

**Criterios de aceptación:**

- El recordatorio incluye la opción de confirmar o cancelar la asistencia.
- La respuesta actualiza el estado de la cita.
- Si declino, se libera el espacio y se notifica a la clínica.
- La respuesta queda registrada.

**Reglas de negocio:** RN-404, RN-405 · **Dependencias:** HU-21, HU-10

> _Nota: Función deseable propuesta (no construida)._

## Módulo 5 — Portal del propietario

### HU-15 — Portal del propietario

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 8 pts | ZOOK-19 |

> Como propietario de mascota, quiero acceder a un portal web con la información de mi mascota para consultar su historial y próximas citas sin depender de la clínica.

**Criterios de aceptación:**

- Acceso con credenciales propias o con Google.
- Solo veo las mascotas vinculadas a mi cuenta.
- Veo ficha, historial, próximas citas y calendario de vacunas.
- No tengo acceso a datos de otros propietarios (403).
- Interfaz responsiva optimizada para móvil.

**Reglas de negocio:** RN-G02 · **Dependencias:** HU-17

### HU-25 — Auto-registro de propietario

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 5 pts | ZOOK-30 |

> Como cliente nuevo, quiero registrarme de manera autónoma en el sistema para acceder al portal y programar citas sin requerir una llamada previa.

**Criterios de aceptación:**

- Formulario público de registro.
- Campos: documento, nombre, teléfono y correo.
- Contraseña con requisitos de seguridad.
- Valida que documento o correo no existan.
- Crea usuario propietario e inicia sesión automáticamente.

**Reglas de negocio:** RN-101, RN-G06, RN-407 · **Dependencias:** —

### HU-26 — Agendar cita desde el portal

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 5 pts | ZOOK-31 |

> Como propietario registrado, quiero programar citas para mis mascotas directamente desde mi portal para gestionar su atención a mi conveniencia.

**Criterios de aceptación:**

- Selecciono una de mis mascotas.
- Selecciono tipo de cita, veterinario, fecha y hora disponible.
- Se verifica la disponibilidad (sin empalmes).
- Envío de confirmación por correo y registro en auditoría.

**Reglas de negocio:** RN-401, RN-402, RN-407 · **Dependencias:** HU-25, HU-13

### HU-46 — Editar mi mascota desde el portal

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 3 pts | Nuevo |

> Como propietario, quiero editar los datos de mi mascota desde mi portal para mantener su ficha actualizada.

**Criterios de aceptación:**

- Puedo editar los datos de mi mascota.
- Solo puedo editar mis propias mascotas.
- Los cambios quedan registrados.
- Puedo actualizar la foto con validación de tipo y tamaño.

**Reglas de negocio:** RN-104, RN-108, RN-G02 · **Dependencias:** HU-15

### HU-47 — Imprimir/exportar el historial de mi mascota

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 3 pts | Nuevo |

> Como propietario, quiero imprimir o exportar el historial clínico de mi mascota para tenerlo o compartirlo fuera del sistema.

**Criterios de aceptación:**

- Genero una versión imprimible del historial.
- Incluye consultas, vacunas y desparasitaciones.
- Solo de mis propias mascotas.

**Reglas de negocio:** RN-206, RN-G02 · **Dependencias:** HU-15

### HU-50 — Cancelar o reprogramar mi cita desde el portal

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Pendiente | 5 pts | Deseable |

> Como propietario, quiero cancelar o reprogramar mis propias citas desde el portal para gestionar mi tiempo sin tener que llamar a la clínica.

**Criterios de aceptación:**

- Puedo cancelar una cita futura de mi mascota.
- Puedo reprogramarla a un horario disponible.
- Solo puedo hacerlo sobre mis propias citas.
- El cambio notifica a la clínica y queda en auditoría.
- Respeta las reglas de disponibilidad y horario.

**Reglas de negocio:** RN-405, RN-401, RN-G02 · **Dependencias:** HU-26

> _Nota: Función deseable propuesta (hoy el portal solo agenda, no cancela ni reprograma)._

### HU-52 — Centro de notificaciones del propietario

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Pendiente | 3 pts | Deseable |

> Como propietario, quiero ver dentro del portal las notificaciones de mi mascota (recordatorios, confirmaciones, cambios de cita) para no depender solo del correo.

**Criterios de aceptación:**

- Veo un listado de mis notificaciones dentro del portal.
- Hay un contador de no leídas.
- Puedo marcarlas como leídas.
- Solo veo las notificaciones que me corresponden.

**Reglas de negocio:** RN-G02 · **Dependencias:** HU-15

> _Nota: Función deseable propuesta (hoy el propietario solo recibe correo)._

## Módulo 6 — Dashboard y reportes

### HU-18 — Dashboard principal y panel de pendientes

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 5 pts | ZOOK-23 |

> Como veterinario, quiero ver un panel de resumen al iniciar sesión para tener visibilidad de las citas del día, las vacunaciones próximas y las alertas.

**Criterios de aceptación:**

- Muestra citas del día, vacunas próximas a 7 días y desparasitaciones próximas.
- Accesos rápidos a funciones frecuentes.
- Carga en menos de 3 segundos.
- Datos filtrados según el usuario.

**Reglas de negocio:** RN-G01 · **Dependencias:** HU-17

### HU-16 — Generar reportes en PDF

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 5 pts | ZOOK-20 |

> Como veterinario, quiero generar reportes exportables en PDF para tener resúmenes de la actividad de la clínica.

**Criterios de aceptación:**

- Reportes: listado de pacientes, citas del período y vacunaciones pendientes.
- Se puede filtrar por rango de fechas.
- El PDF se descarga desde el navegador.
- Incluye encabezado con nombre de la clínica y fecha.

**Reglas de negocio:** RN-501 · **Dependencias:** —

### HU-48 — Ver estadísticas y gráficas del sistema

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 5 pts | Nuevo |

> Como administrador, quiero ver estadísticas y gráficas del sistema (pacientes, clientes, citas, consultas) para tener una visión general de la operación.

**Criterios de aceptación:**

- Veo indicadores clave (pacientes, clientes, citas de hoy, consultas).
- Veo gráficas por periodo o categoría.
- Los datos coinciden con la base de datos.
- Acceso restringido a roles internos.

**Reglas de negocio:** RN-501 · **Dependencias:** HU-17

### HU-55 — Exportar reportes a Excel/CSV

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Pendiente | 3 pts | Deseable |

> Como administrador o veterinario, quiero exportar los reportes también a Excel/CSV además de PDF para analizarlos fuera del sistema.

**Criterios de aceptación:**

- Cada reporte disponible en PDF también se puede exportar a Excel/CSV.
- El archivo exportado respeta los filtros aplicados.
- Los datos exportados coinciden con la vista.

**Reglas de negocio:** RN-501 · **Dependencias:** HU-16

> _Nota: Función deseable propuesta (hoy los reportes solo salen en PDF)._

## Módulo 7 — Configuración del sistema

### HU-43 — Configurar los horarios de atención de la clínica

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 5 pts | Nuevo |

> Como administrador, quiero configurar los horarios de atención por día (bloques de mañana y tarde) para que la agenda ofrezca solo horas válidas.

**Criterios de aceptación:**

- Defino bloques de mañana y tarde por día de la semana.
- Puedo activar o inactivar días.
- Puedo restaurar los horarios por defecto.
- La agenda respeta la configuración.

**Reglas de negocio:** RN-503, RN-501 · **Dependencias:** HU-17

### HU-44 — Gestionar los catálogos del sistema

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 3 pts | Nuevo |

> Como administrador o veterinario, quiero gestionar los catálogos (especies, razas, colores, vacunas base, laboratorios, productos) para que los formularios ofrezcan opciones actualizadas.

**Criterios de aceptación:**

- Puedo agregar entradas a los catálogos.
- Las nuevas entradas aparecen en los formularios correspondientes.
- Las vacunas base se asocian por especie.

**Reglas de negocio:** RN-502 · **Dependencias:** HU-17

### HU-53 — Parámetros del sistema configurables

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Pendiente | 5 pts | Deseable |

> Como administrador, quiero configurar parámetros del sistema (duración de cita por defecto, buffer entre citas, ventanas de recordatorio) para adaptar el comportamiento sin tocar el código.

**Criterios de aceptación:**

- Puedo definir la duración de cita por defecto y el buffer entre citas.
- Puedo definir con cuánta anticipación se envían los recordatorios.
- Los cambios se aplican en la agenda y en los recordatorios.

**Reglas de negocio:** RN-403, RN-303, RN-503 · **Dependencias:** HU-43

> _Nota: Función deseable propuesta; sería el hogar natural del buffer (HU-28) y las ventanas de recordatorio (HU-37)._

## Módulo 8 — Público e institucional

### HU-49 — Página pública y políticas legales

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Baja | Implementada | 2 pts | Nuevo |

> Como visitante o usuario, quiero ver la página pública del servicio y las políticas de privacidad, términos y cookies para conocer y confiar en el sistema.

**Criterios de aceptación:**

- La landing presenta el servicio.
- Existen páginas de privacidad, términos y cookies accesibles.
- La política de datos refleja la Ley 1581 de 2012.

**Reglas de negocio:** RN-G07 · **Dependencias:** —

