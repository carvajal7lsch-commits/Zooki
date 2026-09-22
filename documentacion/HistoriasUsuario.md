# Historias de Usuario — Proyecto Zooki

> **Revisión 2.2** · 57 historias de usuario organizadas por módulos · SENA ADSO — Ficha 3142784

Cadena de trazabilidad: **Regla de Negocio (RN) → Historia de Usuario (HU) → Requisito específico (RE)**. Origen: `ZOOK-xx` (backlog Jira), `Nuevo` (funcionalidad ya existente ahora documentada), `VD-xxx` (derivada del análisis de vacíos), `Deseable` (función propuesta aún no construida).

Estado **diferida**: historia de agenda, consultas o catálogos que no se completa en la versión 1.x porque ese módulo se rehace con el cambio de arquitectura. Conserva sus criterios y se retoma allí.

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
- Veo desde cuándo existe mi cuenta, mi acceso anterior y mi actividad reciente: accesos, intentos fallidos y cambios, con fecha e IP.
- Si hubo intentos fallidos en los últimos 30 días, se me avisa.

**Reglas de negocio:** RN-G05, RN-G06, RN-G07 · **Dependencias:** HU-17, HU-24

> _Nota: `PerfilController` sirve a los cuatro roles; el sujeto sale siempre de la sesión, nunca del POST (RN-G02). Hasta v1.8.0 solo el propietario podía editar sus datos, desde su portal: administrador, veterinario y recepcionista no tenían ninguna vista de perfil. El documento, el nombre y el rol son de solo lectura; dejar el rol editable sería la escalada de privilegios que corrigió HU-33. Desde v1.8.0 el personal entra por «Mi perfil» en el menú del avatar a un panel propio (`index.php?action=mi_perfil`) con sus datos, el contacto editable y el cambio de contraseña (HU-39)._

> _Nota: desde v1.9.1 el panel ocupa la pantalla completa en tres columnas (contacto, contraseña y actividad reciente) bajo un encabezado con los datos de la cuenta. La contraseña actual solo se pide si la cuenta ya tiene una (`password_definida`); las creadas con Google ven «Crear contraseña». El cambio de contraseña no quedaba en la auditoría y ahora sí. Las fechas de la auditoría se convierten a la hora de la clínica, porque la base en Docker guarda en UTC._

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
- Como veterinario, recibo un aviso cuando una atención mía sigue abierta después de su hora de fin y cuando queda sin cerrar al terminar el día (RN-410).

**Reglas de negocio:** RN-G02, RN-410 · **Dependencias:** HU-17

> _Nota: el marcado individual comprueba el destinatario antes de escribir (`NotificacionInterna::perteneceA`); sin eso, cualquier sesión válida podía marcar la notificación de otra persona enviando un id cualquiera, porque los cuatro roles tienen permitida la acción. El marcado masivo existía como endpoint desde el principio pero ningún archivo del front lo invocaba._

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

> _Nota: al crear el usuario, la contraseña que escriba el administrador pasa por `PoliticaPassword`; si la deja vacía se genera una temporal aleatoria que también la cumple. Para un usuario ya existente, el restablecimiento lo cubre HU-54. Los campos del formulario se validan además en el backend (obligatoriedad, formato de correo, documento numérico y tipo de documento de una lista cerrada), no solo en el navegador._

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

- Script que vuelca la base de datos y comprime el archivo.
- Copia almacenada en un directorio externo al contenedor de la aplicación.
- Retención de los últimos respaldos (rotación automática).
- Registro de la ejecución en el log del sistema.

**Reglas de negocio:** RN-504 · **Dependencias:** —

> _Nota: `scripts/backup.php` toma las credenciales del `.env` (antes estaban escritas en el propio archivo con `root` y contraseña vacía) y se las pasa a `mysqldump` por un fichero temporal con permisos 0600, no por `--password=`, que es visible en `ps` para cualquier usuario del servidor. El destino se configura con `BACKUP_DIR` para poder apuntar a un volumen externo, como pide el criterio. La programación cada 24 h está versionada en `scripts/zooki.cron`._

> _Nota: hasta v1.10.1 el respaldo no corría en producción. Dependía de `mysqldump`, que la imagen `php:8.2-apache` no trae, y no había Schedule en Dokploy ni volumen para guardarlo. Desde v1.11.0 el volcado se hace con PDO (`models/Respaldo.php`), en una sola instantánea y sin las columnas generadas, que MySQL rechaza al restaurar. Se guarda en el volumen `respaldos` (`BACKUP_DIR=/var/backups/zooki`) y se programa con un Schedule de Dokploy. Se verificó volcando la base y restaurándola en otra: las mismas filas en las 26 tablas._

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
| Media | Implementada | 3 pts | Deseable |

> Como administrador, quiero restablecer la contraseña de un usuario desde el panel para ayudarlo cuando no puede acceder a su cuenta.

**Criterios de aceptación:**

- Puedo generar un restablecimiento de contraseña para un usuario.
- El usuario debe cambiarla en su próximo ingreso.
- Solo el administrador puede hacerlo.
- La acción queda registrada en auditoría.

**Reglas de negocio:** RN-G08, RN-501, RN-G05 · **Dependencias:** HU-22

> _Nota: `UsuarioController::resetearPasswordAjax()` genera una contraseña temporal que cumple la política (RN-G10), la envía por correo y marca `debe_cambiar_password`. La obligación de cambiarla la hace cumplir `Security::validatePasswordTemporal()` en cada petición, no solo en la redirección posterior al login. El acceso es exclusivo del administrador por la matriz de autorización y la acción queda en auditoría._

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

### HU-56 — Atención clínica sin cita (urgencias)

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 3 pts | Revisión del módulo de consultas |

> Como veterinario, quiero registrar la atención de un paciente que llega sin cita (una urgencia o un imprevisto) para que quede en su historia clínica igual que cualquier otra consulta.

**Criterios de aceptación:**

- El flujo normal es por cita: desde el calendario se inicia la atención y la consulta queda ligada a la cita (HU-19, HU-27).
- En Consultas Médicas, el botón «Atención sin cita» permite buscar al paciente (HU-03) y registrar la consulta sin cita asociada.
- La pantalla explica cuándo usarlo y remite al calendario si el paciente tiene cita.
- Se exigen los mismos datos que en HU-05 (motivo y diagnóstico) y, si es su primera consulta, se asigna el número de historia clínica (RN-102).
- En el listado de consultas, las registradas sin cita se distinguen con la etiqueta «Sin cita».

**Reglas de negocio:** RN-102, RN-203, RN-208 · **Dependencias:** HU-03, HU-05

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

> _Nota: la descarga pasa siempre por `public/ver_archivo.php`, que comprueba el rol y, para un propietario, que la mascota sea suya (RN-G02). El `.htaccess` de la carpeta es una segunda barrera, no la principal: solo cubre Apache con AllowOverride activo. El formato se valida por el contenido del archivo, no por la extensión del nombre que envía el cliente, y al servirlo el `Content-Type` sale de una lista cerrada._

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
| Alta | Implementada | 5 pts | VD-HC-01/02 |

> Como veterinario, quiero que al registrar una consulta todo se guarde de forma atómica y se me informe si algún adjunto fue rechazado, para no perder evidencia clínica sin darme cuenta.

**Criterios de aceptación:**

- Consulta, HC, archivos y tratamientos se guardan en una transacción (todo o nada).
- Si un adjunto es rechazado, se informa el motivo por archivo.
- No se reporta éxito con datos parciales.

**Reglas de negocio:** RN-204, RN-206 · **Dependencias:** HU-05

> _Nota: Deriva del análisis de vacíos (VD-HC-01/02). Consulta, número de historia clínica, adjuntos y tratamientos se escriben en una sola transacción; si algo falla se deshace todo y además se borran los archivos ya movidos, porque el sistema de archivos no participa del rollback. Los adjuntos se revisan **antes** de abrir la transacción: si alguno no es aceptable no se guarda nada y la respuesta detalla qué archivo falló y por qué (`adjuntos_rechazados`), que el formulario muestra en una lista. Antes se descartaban en silencio dentro del bucle y la respuesta seguía diciendo "registrada correctamente con sus adjuntos"._

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
- El envío corre solo una vez al día, a las 7:00 hora de la clínica.

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
| Media | Parcial (diferida) | 3 pts | ZOOK-25 |

> Como veterinario, quiero ver un panel con las vacunaciones pendientes de la semana agrupadas por día y especie para planificar la agenda.

**Criterios de aceptación:**

- Muestra las vacunas con próxima dosis en los próximos 7 días.
- Agrupación por día de la semana y por especie.
- Contador por día visible a primera vista.
- Un clic lleva a la ficha de la mascota.

**Reglas de negocio:** RN-301 · **Dependencias:** HU-09

> _Nota: desde v1.9.1 el panel del veterinario muestra las vacunas y desparasitaciones de sus propios pacientes (RE-18.4), agrupadas por día con contador y con enlace a la ficha. La agrupación por especie no se muestra en el panel._

> _Nota: diferida en v1.11.0. Falta la agrupación por especie (RE-20.2); el panel se rehace con la nueva arquitectura._

### HU-37 — Robustez de los recordatorios automáticos

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 3 pts | VD-REM-01/02/03 |

> Como sistema, quiero que los recordatorios se envíen de forma confiable aunque el cron falle un día, con reintentos y zona horaria correcta, para no dejar a los propietarios sin aviso.

**Criterios de aceptación:**

- Se usa una ventana de fechas con marca de enviado que recupera los no enviados.
- Los envíos fallidos se reintentan, hasta 3 intentos por aviso.
- El cálculo de fechas usa la zona horaria de la clínica.
- No se recuerdan dosis de mascotas inactivas ni dosis que ya se renovaron.

**Reglas de negocio:** RN-303, RN-304, RN-305 · **Dependencias:** HU-10

> _Nota: Deriva del análisis de vacíos (VD-REM-01/02/03)._

> _Nota: implementada en v1.11.0. Antes se buscaban fechas exactas (hoy + 7 y hoy + 1), así que un día sin tarea perdía esos avisos. Además, un envío fallido quedaba registrado con estado `error` y la revisión de duplicados lo encontraba, así que nunca se reintentaba. Ahora el primer aviso cubre del día 7 al 2 y el último, el día anterior y el mismo día (`helpers/VentanaRecordatorio.php`). Solo cuenta como enviado un registro `enviado`, y el «hoy» se calcula en la zona de la clínica, no con `CURDATE()`. La selección vive en `models/Recordatorio.php`, que también excluye las mascotas inactivas y las dosis con una aplicación posterior de la misma vacuna o del mismo tipo de desparasitación._

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

> Como veterinario o administrador, quiero cancelar o reprogramar una cita para mantener la agenda actualizada.

**Criterios de aceptación:**

- Se puede cancelar o cambiar la fecha/hora de una cita pendiente o confirmada, a un momento que no haya pasado.
- El veterinario gestiona sus propias citas; el administrador, cualquiera, y puede reasignar el veterinario.
- Al cambiar, se notifica al propietario por correo.
- Las canceladas quedan con estado cancelada.
- No se puede reprogramar a un horario ocupado del veterinario ni que se cruce con otra cita de la mascota.

**Reglas de negocio:** RN-405 · **Dependencias:** HU-13

### HU-19 — Marcar cita como completada

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 2 pts | ZOOK-24 |

> Como veterinario, quiero que la cita quede completada al registrar su consulta para llevar el control de atenciones realizadas y liberar la agenda.

**Criterios de aceptación:**

- La atención se inicia desde el calendario, solo por el veterinario asignado y solo el día de la cita, desde 15 minutos antes de su hora.
- Solo una cita "en curso" o "sin cerrar" puede completarse.
- La cita se completa al guardar su consulta, en la misma operación: no hay citas completadas sin consulta.
- Una atención iniciada y no finalizada se retoma con «Continuar atención», aunque sea de un día anterior.
- Si la atención sigue abierta 10 minutos después de la hora de fin, recibo un aviso por correo y en mis notificaciones.
- Al terminar el día, una atención abierta pasa a "sin cerrar"; la cierro registrando la consulta o sin consulta, con un motivo.
- La revisión de atenciones abiertas corre sola cada 5 minutos, así que el aviso llega aunque nadie abra el calendario.
- Una atención cerrada sin consulta no se reabre y libera su horario.
- La cita completada aparece en el historial del día pero no en la agenda futura.

**Reglas de negocio:** RN-406, RN-408, RN-410, RN-411 · **Dependencias:** HU-13, HU-05

> _Nota: hasta v1.8.0 la cita se completaba con un botón aparte, sin consulta, y la podían iniciar recepción y administración. Como la pantalla de atención es solo del veterinario, esas citas quedaban "en curso" sin nadie que las atendiera, y una cita de un día anterior perdía todos los botones del calendario. Ver el Módulo 4 de `AuditoriaModulos.md`._

> _Nota: desde v1.9.0 una atención ya no queda abierta indefinidamente. Si el veterinario salía sin terminar, la cita seguía "en curso" para siempre sin que nadie se enterara. Ahora se avisa a los 10 minutos de la hora de fin (`VigilanteAtenciones`, también como tarea programada en `scripts/vigilar_atenciones.php`), pasa a "sin cerrar" al terminar el día y se puede cerrar sin consulta con motivo. Además, la atención solo se inicia desde 15 minutos antes de la hora de la cita, para no abrir por error la de un paciente que aún no llega._

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
| Alta | Parcial (diferida) | 8 pts | VD-AGN-01 |

> Como veterinario, quiero registrar la hora real de inicio y fin de cada atención y que el sistema detecte los retrasos, para que la agenda coincida con la realidad y no se acumulen los choques.

**Criterios de aceptación:**

- Al iniciar y completar se sella la hora real.
- El sistema detecta cuando una atención excede su duración planificada.
- Alerta o recalcula el corrimiento de las citas siguientes del veterinario.

**Reglas de negocio:** RN-401 · **Dependencias:** HU-19

> _Nota: Deriva del análisis de vacíos (VD-AGN-01): el caso del calendario con retrasos en cascada. En v1.9.0 se cumple el primer criterio: `hora_inicio_real` y `hora_fin_real` (migración 09) se sellan al iniciar la atención y al guardar la consulta que completa la cita. Siguen pendientes la detección del exceso de duración y el aviso de corrimiento a las citas siguientes._

> _Nota: diferida en v1.11.0: la agenda se rehace con la nueva arquitectura. Faltan RE-27.2 y RE-27.3._

### HU-28 — Buffer configurable entre citas

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Pendiente (diferida) | 3 pts | VD-AGN-02 |

> Como administrador, quiero definir un tiempo de amortiguación entre citas para absorber pequeños retrasos sin generar choques.

**Criterios de aceptación:**

- Se configura un buffer entre citas.
- La disponibilidad lo respeta al agendar.
- El buffer se refleja en las sugerencias de horario.

**Reglas de negocio:** RN-401, RN-403 · **Dependencias:** HU-13

> _Nota: Deriva del análisis de vacíos (VD-AGN-02)._

> _Nota: diferida en v1.11.0: la agenda se rehace con la nueva arquitectura._

### HU-29 — Estado "no asistió" y ausentismo

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Parcial (diferida) | 3 pts | VD-AGN-07 |

> Como veterinario, quiero marcar una cita como "no asistió" para liberar el espacio y medir el ausentismo.

**Criterios de aceptación:**

- Existe el estado "no asistió".
- Lo marca el veterinario asignado, y solo cuando la hora de la cita ya pasó.
- Una cita marcada así libera el espacio.
- Se puede reportar la tasa de ausentismo.

**Reglas de negocio:** RN-405, RN-409 · **Dependencias:** HU-13

> _Nota: Deriva del análisis de vacíos (VD-AGN-07). En v1.9.0 se implementaron el estado `no_asistio` (migración 09), el botón «No asistió» del calendario y la liberación del espacio en toda la lógica de disponibilidad. Falta un reporte propio de la tasa de ausentismo: por ahora las inasistencias solo se ven en la gráfica «Mis citas» del panel del veterinario (últimos 30 días)._

> _Nota: diferida en v1.11.0: la agenda se rehace con la nueva arquitectura. Falta RE-29.3 (tasa de ausentismo)._

### HU-30 — Bloqueos de agenda del veterinario y días no laborables

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Pendiente (diferida) | 5 pts | VD-AGN-08 |

> Como administrador, quiero registrar bloqueos del veterinario (almuerzo, permiso, incapacidad) y días festivos o cierres, para que no se agenden citas en esos periodos.

**Criterios de aceptación:**

- Se registran bloqueos por veterinario y por fecha.
- Se registran festivos y cierres puntuales.
- La disponibilidad excluye esos periodos.

**Reglas de negocio:** RN-402, RN-503 · **Dependencias:** HU-13

> _Nota: Deriva del análisis de vacíos (VD-AGN-08)._

> _Nota: diferida en v1.11.0: la agenda se rehace con la nueva arquitectura._

### HU-31 — Endurecer la validación de disponibilidad

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Parcial (diferida) | 5 pts | VD-AGN-03/04/05/06 |

> Como responsable del sistema, quiero que toda validación de disponibilidad se haga en el backend, con una sola fuente de horario que respete la duración y sin condición de carrera, para impedir citas inválidas o dobles.

**Criterios de aceptación:**

- La disponibilidad usa una sola lógica basada en horarios_clinica.
- Valida horario + duración en el backend.
- Usa transacción o restricción única para evitar dobles reservas.

**Reglas de negocio:** RN-401, RN-402 · **Dependencias:** HU-13

> _Nota: Deriva del análisis de vacíos (VD-AGN-03/04/05/06)._

> _Nota: diferida en v1.11.0: la agenda se rehace con la nueva arquitectura. Estado en v1.10.1: las sugerencias de horario recorren un horario fijo de 08:00 a 18:00 en lugar de `horarios_clinica` (RE-31.1); `validarHorarioLaboral` solo mira la hora de inicio, no la duración, e ignora los bloques desactivados que conservan sus horas (RE-31.2); el índice `uq_cita_vet_activa` impide dos citas que empiezan a la misma hora, pero no dos reservas simultáneas que se solapan con distinta hora de inicio (RE-31.3)._

### HU-51 — Confirmar asistencia a la cita desde el recordatorio

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Pendiente (diferida) | 3 pts | Deseable |

> Como propietario, quiero confirmar o declinar mi asistencia desde el recordatorio de la cita para que la clínica sepa con anticipación si asistiré.

**Criterios de aceptación:**

- El recordatorio incluye la opción de confirmar o cancelar la asistencia.
- La respuesta actualiza el estado de la cita.
- Si declino, se libera el espacio y se notifica a la clínica.
- La respuesta queda registrada.

**Reglas de negocio:** RN-404, RN-405 · **Dependencias:** HU-21, HU-10

> _Nota: diferida en v1.11.0: la agenda se rehace con la nueva arquitectura._

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
- Interfaz que se adapta a móvil, tablet y escritorio (RNF-07): barra inferior en móvil y tablet, menú lateral en escritorio.
- El botón «atrás» del navegador me devuelve a la sección anterior y cierra la ventana que tenga abierta.
- Veo si la clínica está abierta ahora y su horario de la semana.
- Los formularios indican qué campos son obligatorios y cuáles opcionales.
- Al registrar o editar una mascota elijo la raza del catálogo; si no está, indico que es mestiza, que no la sé o la escribo para que la clínica la confirme, y veo la foto antes de guardarla. El color lo registra la clínica en la consulta.
- Al agendar no puedo elegir días en que la clínica no atiende, y los horarios libres aparecen en botones cuando ya elegí tipo, veterinario y día.

> **Nota:** el portal mostraba un banner fijo de «Médicos calificados las 24 horas del día», que no correspondía al horario configurado en HU-43. Se reemplazó por el horario real.

**Reglas de negocio:** RN-G02 · **Dependencias:** HU-17, HU-43

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
| Alta | Parcial (diferida) | 5 pts | Deseable |

> Como propietario, quiero cancelar o reprogramar mis propias citas desde el portal para gestionar mi tiempo sin tener que llamar a la clínica.

**Criterios de aceptación:**

- Puedo cancelar una cita futura de mi mascota.
- Puedo reprogramarla a un horario disponible.
- Solo puedo hacerlo sobre mis propias citas.
- El cambio notifica a la clínica y queda en auditoría.
- Respeta las reglas de disponibilidad y horario.

**Reglas de negocio:** RN-405, RN-401, RN-G02 · **Dependencias:** HU-26

> _Nota: Función deseable propuesta. Desde v1.9.0 el propietario cancela sus citas pendientes o confirmadas desde el portal: el botón existía, pero el portal buscaba un estado «programada» que no existe y nunca lo mostraba (M4-07). Siguen pendientes reprogramar desde el portal y el registro del cambio en auditoría._

> _Nota: diferida en v1.11.0: la agenda se rehace con la nueva arquitectura. Faltan RE-50.2 (reprogramar desde el portal) y RE-50.4 (la cancelación avisa a la clínica pero no queda en `auditoria_sistema`)._

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
- Destaca el siguiente paciente con el botón para iniciar o continuar la atención, habilitado desde 15 minutos antes de la cita.
- Lista las atenciones sin cerrar o abiertas de días anteriores, cada una con acceso para cerrarla.
- No muestra datos de ejemplo: sin información, se ve un estado vacío.

**Reglas de negocio:** RN-G01, RN-408, RN-410 · **Dependencias:** HU-17

> _Nota: hasta v1.9.0 el panel mezclaba gráficas con datos inventados cuando no había información, la agenda de hoy mostraba las citas de todos los veterinarios y el "hoy" se calculaba en UTC. Desde v1.9.1 el panel se pinta en el servidor con la hora de la clínica (`PanelController`) y responde a "¿qué tengo que hacer ahora?"._

### HU-57 — Panel de operación del administrador

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 5 pts | Rediseño v1.9.1 |

> Como administrador, quiero ver al iniciar sesión cómo va la operación de la clínica hoy y qué queda pendiente, para actuar a tiempo sin revisar módulo por módulo.

**Criterios de aceptación:**

- Muestra citas de hoy, atendidas, no asistidas y consultas del mes comparadas con el mismo periodo del mes anterior.
- Muestra la carga del día por veterinario activo.
- Lista las citas de hoy con filtro por estado.
- Señala las atenciones sin cerrar por veterinario, las citas pasadas sin marcar y las citas por confirmar de los próximos 7 días.
- Grafica las citas atendidas y no asistidas de los últimos 6 meses.
- No muestra datos de ejemplo ni valores fijos.

**Reglas de negocio:** RN-G01, RN-409, RN-410 · **Dependencias:** HU-18, HU-19

### HU-16 — Generar reportes en PDF

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Retirada | 5 pts | ZOOK-20 |

> _Nota: en v1.9.1 se eliminó el módulo de reportes (`views/admin/reportes.php` y la ruta `admin_reportes`). Ya no estaba en el menú y solo imprimía la página desde el navegador. Los indicadores de operación del administrador pasaron a su panel de inicio (HU-57)._

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

> _Nota: desde v1.9.1 la pantalla muestra una fila por día con sus bloques de mañana y tarde, las horas de cada día y un resumen de la semana (días abiertos, horas totales y el horario de hoy). Los días cerrados se leen «Cerrado» en lugar de rangos vacíos y el estado del guardado automático está siempre visible. El CSS y el JS salieron de la vista a `public/css/horarios.css` y `public/js/horarios.js`, con las mismas validaciones y el mismo selector de horas._

### HU-44 — Gestionar los catálogos del sistema

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Parcial (diferida) | 3 pts | Nuevo |

> Como administrador o veterinario, quiero gestionar los catálogos (especies, razas, colores, vacunas base, laboratorios, productos) para que los formularios ofrezcan opciones actualizadas.

**Criterios de aceptación:**

- Puedo agregar entradas a los catálogos.
- Las nuevas entradas aparecen en los formularios correspondientes.
- Las vacunas base se asocian por especie.

**Reglas de negocio:** RN-502 · **Dependencias:** HU-17

> _Nota: sobre-declarada hasta v1.10.1 y diferida en v1.11.0. No existe una pantalla de catálogos: «Configuración» solo tiene los horarios. Especies, razas y colores no se pueden crear. Vacunas, laboratorios y productos solo los crea el veterinario al registrar el acto clínico. Queda por decidir quién gestiona los catálogos: RN-501 dice solo el administrador, esta HU dice administrador o veterinario y la matriz de permisos hoy se lo da solo al veterinario._

### HU-53 — Parámetros del sistema configurables

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Pendiente (diferida) | 5 pts | Deseable |

> Como administrador, quiero configurar parámetros del sistema (duración de cita por defecto, buffer entre citas, ventanas de recordatorio) para adaptar el comportamiento sin tocar el código.

**Criterios de aceptación:**

- Puedo definir la duración de cita por defecto y el buffer entre citas.
- Puedo definir con cuánta anticipación se envían los recordatorios.
- Los cambios se aplican en la agenda y en los recordatorios.

**Reglas de negocio:** RN-403, RN-303, RN-503 · **Dependencias:** HU-43

> _Nota: Función deseable propuesta; sería el hogar natural del buffer (HU-28) y las ventanas de recordatorio (HU-37)._

> _Nota: diferida en v1.11.0: la agenda se rehace con la nueva arquitectura._

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

