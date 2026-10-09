# Historias de Usuario — Proyecto Zooki

> **Revisión 3.1** · 95 historias de usuario organizadas por módulos (54 de la v1.x + 41 nuevas de la v2, repartidas entre v2.0 y v2.1) · SENA ADSO — Ficha 3142784
>
> **Identificador:** `HU-<módulo>.<n>` — p. ej. `HU-4.3` es la tercera historia del Módulo 4. Dentro de cada módulo van en orden. La equivalencia con los identificadores anteriores (y la referencia Jira) está en el apéndice. Los módulos siguen la misma numeración en todos los documentos ([Reglas de Negocio](ReglasNegocio.md), [Requisitos Específicos](RequisitosEspecificos.md), [ERS](ERS.md) y [Modelos](Modelos.md)).

Cadena de trazabilidad: **Regla de Negocio (RN) → Historia de Usuario (HU) → Requisito específico (RE)**. Origen: `ZOOK-xx` (backlog Jira), `Nuevo` (funcionalidad existente ya documentada), `VD-xxx` (derivada del análisis de vacíos), `Deseable` (propuesta aún no construida), `v2` (derivada de los modelos de proceso de la Fase 2).

Estado **Planificada (v2)**: historia de la arquitectura SaaS aún por construir, asignada a v2.0 o v2.1 según el plan de entregas. Las historias de la v1.x cuyo actor era el recepcionista se reasignaron al administrador o al veterinario, porque ese rol se elimina en la v2. Estado **diferida**: historia de la v1.x cuyo módulo se rehace con el cambio de arquitectura; conserva sus criterios y se retoma según el plan de entregas.

**Índice de módulos:**

- **Módulo 0** — Plataforma: clínicas, planes y suscripción *(v2.0)* · 5 historias
- **Módulo T** — Acceso, seguridad y administración · 19 historias
- **Módulo 1** — Mascotas y propietarios · 5 historias
- **Módulo 2** — Historia clínica y soporte a la decisión clínica (Grafo I) · 11 historias
- **Módulo 3** — Vacunación, desparasitación y recordatorios · 6 historias
- **Módulo 4** — Agenda de citas inteligente (Grafo II) · 19 historias
- **Módulo 5** — Portal del propietario (multi-clínica) · 14 historias
- **Módulo 6** — Dashboard y reportes · 5 historias
- **Módulo 7** — Configuración del sistema · 5 historias
- **Módulo 8** — Reputación y comunicaciones *(v2.0)* · 5 historias
- **Módulo 9** — Público e institucional · 1 historias

---

## Módulo 0 — Plataforma: clínicas, planes y suscripción *(v2.0)*

> Arquitectura SaaS multi-inquilino: onboarding autónomo de clínicas, control de límites por plan (freemium) y administración de la plataforma por el super-administrador.

### HU-0.1 — Registrar una clínica (self-service)

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Planificada (v2) | 8 pts | v2 |

> Como responsable de una clínica, quiero registrar mi clínica de forma autónoma para empezar a usar Zooki sin depender de una gestión manual.

**Criterios de aceptación:**

- Formulario público con los datos de la clínica (nombre, NIT, dirección, teléfono, correo) y del administrador inicial (nombre, correo, contraseña segura).
- Valida el formato de los campos y que el correo y el NIT no estén ya registrados en la plataforma; el NIT debe superar la validación del dígito de verificación de la DIAN. Si el NIT ya lo registró otra clínica, se puede reportar a soporte.
- El formulario exige superar una verificación anti-bot (CAPTCHA), rechaza correos de dominios desechables y limita los registros desde una misma IP en un periodo; al superar el límite se informa que lo intente más tarde.
- El registro de clínicas no admite Google, y exige aceptar la política de tratamiento de datos.
- Crea la clínica en estado "pendiente de verificación" junto con su usuario administrador inicial; la clínica no opera hasta confirmar el correo.
- Envía un correo de verificación con un enlace temporal.
- La operación queda registrada en auditoría.

**Reglas de negocio:** RN-001, RN-002, RN-010, RN-011, RN-G06, RN-G19, RN-G20 · **Requisitos:** RF-0.1, RF-0.3 · **Dependencias:** —

### HU-0.2 — Verificar el correo y activar la clínica

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Planificada (v2) | 3 pts | v2 |

> Como administrador inicial de una clínica recién registrada, quiero confirmar mi correo para activar la clínica y poder operar.

**Criterios de aceptación:**

- El enlace de verificación vence a las 24 horas (RN-G11).
- Al verificar dentro del plazo, la clínica pasa a estado "activa", se le asigna el **plan gratuito** y se habilita el rol administrador.
- En la misma operación, la clínica recibe su copia de los catálogos iniciales: tipos de cita, horarios de atención, vacunas base con las especies a las que aplican, laboratorios y productos de desparasitación. Así puede agendar y registrar desde el primer día; si la activación falla, no queda ninguna copia a medias.
- Si el plazo expira sin verificar, el registro pendiente se elimina (no deja una clínica fantasma).
- Tras activar, el administrador inicia sesión y queda acotado a su `id_clinica`.
- La activación queda registrada en auditoría.

**Reglas de negocio:** RN-002, RN-003, RN-005, RN-G11 · **Requisitos:** RF-0.1 · **Dependencias:** HU-0.1

### HU-0.3 — Panel del super-administrador

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Planificada (v2) | 8 pts | v2 |

> Como super-administrador, quiero gestionar las clínicas, sus planes y sus suscripciones desde un panel para administrar la plataforma sin entrar en los datos clínicos de cada clínica.

**Criterios de aceptación:**

- Lista de clínicas registradas con su estado, plan y estado de suscripción.
- Puede cambiar el plan de una clínica y el estado de su suscripción.
- **No** accede a los datos clínicos internos de una clínica (historias, consultas, pacientes); el acceso solo se justifica para soporte y queda en auditoría.
- El super-administrador es un rol de plataforma: sus acciones no se filtran por una sola `id_clinica` (única excepción al aislamiento, RN-G13).
- Puede suspender o dar de baja una clínica sospechosa de duplicidad o abuso del plan gratuito, indicando el motivo.
- Puede ocultar una reseña por moderación (lenguaje ofensivo o datos personales), indicando el motivo; es el único que puede hacerlo.
- Toda acción queda registrada en auditoría.

**Reglas de negocio:** RN-004, RN-012, RN-804, RN-G13, RN-G14 · **Requisitos:** RF-0.2, RF-0.4, RF-0.5 · **Dependencias:** —

### HU-0.4 — Control de límites del plan (freemium)

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Planificada (v2) | 5 pts | v2 |

> Como clínica, quiero que al crear mascotas, citas o usuarios se respete el límite de mi plan para operar dentro de lo contratado y saber cuándo conviene mejorarlo.

**Criterios de aceptación:**

- Antes de una acción que consume cupo (crear o vincular una mascota, crear una cita o un usuario) se lee el plan y el estado de suscripción de la clínica.
- Dentro del límite: la acción se ejecuta y queda en auditoría.
- Alcanzado el límite: la acción se impide y se informa el motivo invitando a mejorar el plan.
- **Excepción:** una urgencia 🔴 nunca se bloquea por el límite ni por la mora; se atiende y se registra (RN-420).
- Cuando quien intenta la acción es el **propietario** (desde su portal), el mensaje es neutral ("no es posible completar la acción ahora") y se notifica a la clínica para que lo gestione; cuando es **personal**, el mensaje indica el límite alcanzado y la opción de mejorar el plan.
- El límite de mascotas cuenta las mascotas vinculadas a la clínica, incluidas las que ya existían en la plataforma (HU-1.5).
- Límites del plan gratuito: 5 mascotas vinculadas, 30 citas por mes y 2 cuentas de personal; el plan profesional no tiene límites de volumen.
- El plan gratuito incluye las capacidades del sistema (incluidos los grafos) en funcionamiento, pero limitadas por volumen.

**Reglas de negocio:** RN-003, RN-005, RN-006, RN-420 · **Requisitos:** RF-0.4 · **Dependencias:** HU-0.2

### HU-0.5 — Suscripción y congelación por mora

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Planificada (v2) | 5 pts | v2 |

> Como super-administrador, quiero congelar los beneficios del plan de pago cuando la suscripción de una clínica no está al día para incentivar su regularización sin borrar los datos de la clínica.

**Criterios de aceptación:**

- Cada clínica tiene una suscripción con pago mensual o anual; el pago anual aplica un precio con descuento frente a doce mensualidades.
- Si la suscripción no está al día, se congelan los beneficios del plan de pago (vuelven a aplicar los límites del plan gratuito para crear o vincular registros nuevos, RN-013) hasta regularizar; los datos de la clínica no se eliminan.
- Al bajar al plan gratuito o caer en mora no se oculta ni se borra ningún dato: se siguen atendiendo las mascotas que ya tiene y consultando su historia; solo se bloquea crear o vincular mascotas, crear citas o dar de alta personal por encima de los límites del plan gratuito.
- El control de vencimiento de suscripciones corre como tarea programada (cron).
- El cobro real mediante pasarela de pago queda fuera de esta versión (requisito futuro RF-F.1).
- Los cambios de estado de la suscripción quedan en auditoría.

**Reglas de negocio:** RN-007, RN-008, RN-013 · **Requisitos:** RF-0.5 · **Dependencias:** HU-0.3


---
---

## Módulo T — Acceso, seguridad y administración

> Autenticación, gestión de usuarios y auditoría. En la v2.0 se añade el aislamiento por clínica (multi-inquilino) y el rol super-administrador.

### HU-T.1 — Iniciar sesión y autenticación

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 5 pts | ZOOK-22 |

> Como usuario del sistema, quiero iniciar sesión con credenciales seguras para acceder a las funciones autorizadas según mi rol.

**Criterios de aceptación:**

- Login con documento/usuario y contraseña validados contra la base de datos.
- Contraseñas almacenadas con hashing bcrypt + salt (RNF-05).
- Sesión con PHP nativo; además, inicio de sesión con Google (OAuth 2.0).
- Según el rol se redirige al panel correspondiente.
- Mensaje genérico ante credenciales inválidas.

**Reglas de negocio:** RN-G01, RN-G03, RN-G09 · **Dependencias:** —

### HU-T.2 — Cambiar mi contraseña

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 3 pts | Nuevo |

> Como usuario autenticado de cualquier rol, quiero cambiar mi contraseña desde mi perfil para mantener la seguridad de mi cuenta.

**Criterios de aceptación:**

- Solicita la contraseña actual (salvo cuentas creadas con Google).
- _(v2.0)_ En una cuenta creada con Google aparece «Crear contraseña» en lugar de «Cambiar contraseña», y antes de crearla se vuelve a confirmar la identidad con Google.
- Exige una nueva contraseña que cumpla la política mínima.
- Actualiza el hash y confirma el cambio.
- Si la contraseña actual es incorrecta, muestra error.
- _(v2.0)_ Al cambiar o crear la contraseña se cierran las demás sesiones abiertas y se avisa por correo.

**Reglas de negocio:** RN-G03, RN-G22 · **Dependencias:** HU-T.1

### HU-T.3 — Recuperar contraseña olvidada

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

### HU-T.4 — Cerrar sesión

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Baja | Implementada | 1 pts | Nuevo |

> Como usuario autenticado, quiero cerrar mi sesión para proteger mi cuenta al terminar de usar el sistema.

**Criterios de aceptación:**

- El botón de cerrar sesión destruye la sesión activa.
- Tras cerrar, las rutas protegidas exigen autenticarse de nuevo.
- El cierre de sesión queda registrado en auditoría.

**Reglas de negocio:** RN-G01, RN-G05 · **Dependencias:** HU-T.1

### HU-T.5 — Ver y actualizar mi perfil y datos de contacto

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 3 pts | Nuevo |

> Como usuario, quiero ver y actualizar mis datos de contacto (teléfono, correo) desde mi perfil para mantener mi información al día.

**Criterios de aceptación:**

- Veo los datos de mi perfil.
- Puedo actualizar teléfono y correo.
- El correo nuevo se valida como único.
- _(v2.0)_ Cambiar el correo pide confirmar la identidad, y una cuenta sin contraseña debe crearla antes. El correo nuevo solo se aplica después de verificarlo; el correo anterior recibe un aviso del cambio y, si la cuenta estaba vinculada a Google con él, esa vinculación se retira.
- _(v2.0)_ Puedo corregir mi tipo y número de documento confirmando mi identidad (contraseña o Google); si el documento ya pertenece a otra cuenta, el cambio pasa a soporte.
- _(v2.0)_ Si soy veterinario, edito mi bio y mi foto públicas; mis especialidades las asigna el administrador.
- El cambio persiste y se refleja de inmediato.
- Veo desde cuándo existe mi cuenta, mi acceso anterior y mi actividad reciente: accesos, intentos fallidos y cambios, con fecha e IP.
- Si hubo intentos fallidos en los últimos 30 días, se me avisa.

**Reglas de negocio:** RN-G05, RN-G06, RN-G07, RN-G23, RN-G24, RN-705 · **Dependencias:** HU-T.1, HU-T.8

> _Nota: `PerfilController` sirve a los cuatro roles; el sujeto sale siempre de la sesión, nunca del POST (RN-G02). Hasta v1.8.0 solo el propietario podía editar sus datos, desde su portal: administrador, veterinario y recepcionista no tenían ninguna vista de perfil. El documento, el nombre y el rol son de solo lectura; dejar el rol editable sería la escalada de privilegios que corrigió HU-T.11. Desde v1.8.0 el personal entra por «Mi perfil» en el menú del avatar a un panel propio (`index.php?action=mi_perfil`) con sus datos, el contacto editable y el cambio de contraseña (HU-T.2)._

> _Nota: desde v1.9.1 el panel ocupa la pantalla completa en tres columnas (contacto, contraseña y actividad reciente) bajo un encabezado con los datos de la cuenta. La contraseña actual solo se pide si la cuenta ya tiene una (`password_definida`); las creadas con Google ven «Crear contraseña». El cambio de contraseña no quedaba en la auditoría y ahora sí. Las fechas de la auditoría se convierten a la hora de la clínica, porque la base en Docker guarda en UTC._

### HU-T.6 — Gestionar mis notificaciones internas

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 3 pts | Nuevo |

> Como usuario interno (administrador o veterinario), quiero ver mis notificaciones dentro del sistema y marcarlas como leídas para estar al tanto de los eventos operativos.

**Criterios de aceptación:**

- Veo un contador de notificaciones no leídas.
- Veo la lista de notificaciones recientes.
- Puedo marcar una o todas como leídas.
- Solo veo las notificaciones dirigidas a mi rol o usuario.
- Como veterinario, recibo un aviso cuando una atención mía sigue abierta después de su hora de fin y cuando queda sin cerrar al terminar el día (RN-409).

**Reglas de negocio:** RN-G02, RN-409 · **Dependencias:** HU-T.1

> _Nota: el marcado individual comprueba el destinatario antes de escribir (`NotificacionInterna::perteneceA`); sin eso, cualquier sesión válida podía marcar la notificación de otra persona enviando un id cualquiera, porque los cuatro roles tienen permitida la acción. El marcado masivo existía como endpoint desde el principio pero ningún archivo del front lo invocaba._

### HU-T.7 — Gestión de usuarios del sistema

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 5 pts | ZOOK-26 |

> Como administrador, quiero crear, editar y desactivar usuarios del sistema para gestionar quién tiene acceso y con qué permisos.

**Criterios de aceptación:**

- _(v2.0)_ Al dar de alta personal, si la persona ya existe en la plataforma (por documento o correo), se le asigna el rol en esta clínica sin crear otra cuenta.
- CRUD de usuarios: nombre, correo, rol y estado (activo/inactivo).
- Solo usuarios con rol administrador acceden a este módulo.
- No se permite eliminar al único usuario administrador.
- Cambio de contraseña con validación de fortaleza.

**Reglas de negocio:** RN-G08, RN-701, RN-G06, RN-705 · **Dependencias:** HU-T.1

> _Nota: al crear el usuario, la contraseña que escriba el administrador pasa por `PoliticaPassword`; si la deja vacía se genera una temporal aleatoria que también la cumple. Para un usuario ya existente, el restablecimiento lo cubre HU-T.14. Los campos del formulario se validan además en el backend (obligatoriedad, formato de correo, documento numérico y tipo de documento de una lista cerrada), no solo en el navegador._

### HU-T.8 — Logs de auditoría y seguridad

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 5 pts | ZOOK-28 |

> Como administrador del sistema, quiero consultar un log de auditoría con las operaciones críticas para detectar accesos no autorizados o cambios sospechosos.

**Criterios de aceptación:**

- Registro de login exitoso/fallido y de creación, edición y eliminación.
- Campos: usuario, fecha/hora, IP, operación, tabla y datos previos/nuevos.
- Vista filtrable por usuario, operación y rango de fechas.
- Logs inmutables (solo lectura para el administrador).

**Reglas de negocio:** RN-G05 · **Dependencias:** HU-T.1

### HU-T.9 — Backup automático de la base de datos

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 3 pts | ZOOK-29 |

> Como administrador del sistema, quiero respaldar automáticamente la base de datos cada 24 horas para prevenir la pérdida de información clínica.

**Criterios de aceptación:**

- Script que vuelca la base de datos y comprime el archivo.
- Copia almacenada en un directorio externo al contenedor de la aplicación.
- Retención de los últimos respaldos (rotación automática).
- Registro de la ejecución en el log del sistema.

**Reglas de negocio:** RN-704 · **Dependencias:** —

> _Nota: `scripts/backup.php` toma las credenciales del `.env` (antes estaban escritas en el propio archivo con `root` y contraseña vacía) y se las pasa a `mysqldump` por un fichero temporal con permisos 0600, no por `--password=`, que es visible en `ps` para cualquier usuario del servidor. El destino se configura con `BACKUP_DIR` para poder apuntar a un volumen externo, como pide el criterio. La programación cada 24 h está versionada en `scripts/zooki.cron`._

> _Nota: hasta v1.10.1 el respaldo no corría en producción. Dependía de `mysqldump`, que la imagen `php:8.2-apache` no trae, y no había Schedule en Dokploy ni volumen para guardarlo. Desde v1.11.0 el volcado se hace con PDO (`models/Respaldo.php`), en una sola instantánea y sin las columnas generadas, que MySQL rechaza al restaurar. Se guarda en el volumen `respaldos` (`BACKUP_DIR=/var/backups/zooki`) y se programa con un Schedule de Dokploy. Se verificó volcando la base y restaurándola en otra: las mismas filas en las 26 tablas._

### HU-T.10 — Autorización central por rol (RBAC real)

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 8 pts | VD-SEG-03/04 |

> Como administrador del sistema, quiero que cada acción valide de forma centralizada el rol autorizado para impedir accesos indebidos aunque un endpoint se invoque directamente.

**Criterios de aceptación:**

- Existe una matriz acción → roles permitidos aplicada antes de ejecutar cualquier acción.
- Un rol sin permiso recibe 403.
- El control no depende de la interfaz.
- Se cubren todos los endpoints AJAX.

**Reglas de negocio:** RN-G01, RN-G02 · **Dependencias:** HU-T.1

> _Nota: Deriva del análisis de vacíos (VD-SEG-03/04). El control central (`Security::validateRole`) aplica una matriz acción → roles antes del enrutador, cubriendo las 108 acciones no públicas._

### HU-T.11 — Corregir escalada de privilegios y proteger al último administrador

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 5 pts | VD-SEG-01/02 |

> Como administrador, quiero que solo un administrador pueda crear/editar usuarios y asignar roles, y que no se pueda desactivar al último administrador, para evitar la toma de control del sistema.

**Criterios de aceptación:**

- Crear/editar usuario exige rol administrador.
- El rol asignable se valida contra una lista permitida.
- No se puede desactivar ni eliminar al último administrador activo.

**Reglas de negocio:** RN-G08, RN-701 · **Dependencias:** HU-T.7

> _Nota: Deriva del análisis de vacíos (VD-SEG-01/02, severidad crítica)._

### HU-T.12 — Política de contraseñas, verificación de correo y OAuth seguro

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 5 pts | VD-SEG-07/08/10 |

> Como responsable del sistema, quiero una política de contraseñas única y fuerte, verificación de correo en el registro y validación de la audiencia del token de Google, para reducir el riesgo de cuentas comprometidas.

**Criterios de aceptación:**

- Política única (mínimo 8 + complejidad) en todos los flujos.
- El registro exige verificar el correo antes de activar.
- El login con Google valida aud/iss contra el client_id propio.

**Reglas de negocio:** RN-G03, RN-G04, RN-G10, RN-G11, RN-G12 · **Dependencias:** HU-T.1

> _Nota: Deriva del análisis de vacíos (VD-SEG-07/08/10). La política vive en `helpers/PoliticaPassword.php` y la consumen los cuatro flujos; el auto-registro ya no inicia sesión solo, deja una verificación pendiente en `verificaciones_email`; y `helpers/GoogleToken.php` compara `aud`/`iss` contra el client_id propio antes de aceptar el token._

### HU-T.13 — Endurecer rate limiting y reducir enumeración/fuga de información

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 3 pts | VD-SEG-05/06/09 |

> Como responsable del sistema, quiero un límite de intentos robusto y evitar la enumeración de usuarios y la fuga de errores técnicos, para dificultar los ataques.

**Criterios de aceptación:**

- El límite de intentos se guarda del lado servidor por IP/cuenta y no se evade sin cookie.
- Tras 20 intentos fallidos en 15 minutos desde una misma IP, esa IP se bloquea temporalmente con un mensaje genérico; un acceso correcto no reinicia ese contador.
- _(v2.0)_ Sobre una misma cuenta, tras 5 intentos fallidos no se bloquea la cuenta: se exige superar el CAPTCHA. Así nadie puede dejar sin acceso a otro usuario fallando a propósito con su documento o correo.
- Las verificaciones de documento/correo no revelan existencia ni permiten abuso.
- Los mensajes de error al cliente son genéricos.

**Reglas de negocio:** RN-G03, RN-G15 · **Dependencias:** HU-T.1

> _Nota: Deriva del análisis de vacíos (VD-SEG-05/06/09). El contador de intentos pasó de `$_SESSION` a la tabla `intentos_login`, con límite por IP y por cuenta, así que descartar la cookie ya no lo reinicia. Los mensajes de login son idénticos exista o no la cuenta. Las verificaciones de documento/correo quedan limitadas a 20 por IP cada 15 minutos y, desde HU-T.12, el registro ya no habilita la cuenta por sí solo: la existencia que revela el formulario no alcanza para usar una cuenta ajena._

### HU-T.14 — Restablecer la contraseña de un usuario (administrador)

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 3 pts | Deseable |

> Como administrador, quiero restablecer la contraseña de un usuario desde el panel para ayudarlo cuando no puede acceder a su cuenta.

**Criterios de aceptación:**

- Puedo generar un restablecimiento de contraseña para un usuario.
- El usuario debe cambiarla en su próximo ingreso.
- Solo el administrador puede hacerlo.
- La acción queda registrada en auditoría.

**Reglas de negocio:** RN-G08, RN-701, RN-G05 · **Dependencias:** HU-T.7

> _Nota: `UsuarioController::resetearPasswordAjax()` genera una contraseña temporal que cumple la política (RN-G10), la envía por correo y marca `debe_cambiar_password`. La obligación de cambiarla la hace cumplir `Security::validatePasswordTemporal()` en cada petición, no solo en la redirección posterior al login. El acceso es exclusivo del administrador por la matriz de autorización y la acción queda en auditoría._

### HU-T.15 — Aislamiento por clínica y rol super-administrador

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 8 pts | v2 |

> Como plataforma, quiero que cada sesión quede confinada a su clínica y exista un rol super-administrador por encima para que ninguna clínica vea ni altere datos de otra.

**Criterios de aceptación:**

- Toda operación de negocio filtra por `id_clinica`; su omisión se considera defecto de seguridad (RNF-11).
- Un usuario de una clínica no obtiene ni modifica datos de otra clínica: el acceso se rechaza con HTTP 403 y queda en auditoría.
- `Security` valida en cada petición el rol permitido, el token CSRF y la clínica del recurso.
- Excepciones de acceso a datos globales, explícitas: el **super-administrador** (rol de plataforma, RN-004/RN-G13), que no accede a datos clínicos internos salvo soporte, y la **mascota global**, cuyos datos de seguridad (alergias, alertas, vacunas y desparasitaciones) ve toda clínica vinculada y cuyas consultas de otras clínicas solo se ven con autorización del propietario (RN-113, HU-2.10).
- El aislamiento se centraliza (helpers de seguridad y capa de modelos) y se cubre con pruebas.

**Reglas de negocio:** RN-G13, RN-G14, RN-001, RN-004 · **Requisitos:** RF-T.4, RNF-11 · **Dependencias:** HU-T.10

> _Cerrada en M0, etapa C (C1–C9, 2026-10-08). RE-T.15.1 se verifica en personal, configuración, pacientes, historia y prevención, agenda, portal, paneles, auditoría y notificaciones. Los propietarios acceden únicamente a sus propios recursos (RN-G02); las tareas del sistema (`AtencionesEnCurso` y recordatorios) recorren las clínicas sin sesión y escriben cada aviso en la clínica de su registro original. Estas excepciones no permiten lecturas cruzadas desde pantallas. Evidencia: `SeguridadClinicaTest`, `ContextoTest`, `UsuarioSeguridadTest`, `NotificacionAccesoTest`, `ConfiguracionClinicaTest`, `MascotaAccesoTest`, `PropietarioClinicaTest`, `ConsultaHistorialTest`, `HistoriaCompartidaTest`, `PrevencionTest`, `AgendaPeticionesTest`, `VinculosPropietarioTest`, `PortalPropietarioTest`, `PortalAgendaTest`, `PanelTest`, `ActividadCuentaAuditoriaTest`, `RecordatorioTest` y `BaseV2MysqlTest`._

### HU-T.16 — Expiración de la sesión por inactividad

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Planificada (v2) | 3 pts | v2 |

> Como usuario, quiero que mi sesión se cierre sola si la dejo abierta sin usar para que nadie use mi cuenta en un equipo compartido de la clínica.

**Criterios de aceptación:**

- Tras 30 minutos sin actividad, la sesión se cierra y la siguiente acción lleva al inicio de sesión con un mensaje que explica el motivo.
- Las peticiones AJAX con la sesión vencida reciben 401, y la interfaz redirige al inicio de sesión sin perder el aviso.
- La cookie de sesión es HttpOnly, SameSite=Lax y Secure bajo HTTPS, y el identificador de sesión se regenera al iniciar sesión.
- El cierre por inactividad queda en auditoría.

**Reglas de negocio:** RN-G17, RN-G05 · **Requisitos:** RNF-13, RNF-14 · **Dependencias:** HU-T.1, HU-T.4

### HU-T.17 — Una persona con varios roles: elegir el contexto al iniciar sesión

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Planificada (v2) | 5 pts | v2 |

> Como veterinario que también tiene mascota, o que trabaja en dos clínicas, quiero entrar con una sola cuenta y elegir en qué papel entro para no manejar varias cuentas ni mezclar permisos.

**Criterios de aceptación:**

- Una persona tiene una sola identidad (documento y correo) y puede tener varios roles: propietario y, en cada clínica donde trabaja, administrador o veterinario.
- Al iniciar sesión, si tiene más de un contexto, elige entre su portal de propietario y cada clínica con su rol; con un solo contexto entra directo.
- Puede cambiar de contexto sin volver a iniciar sesión; al cambiar, los permisos y la clínica activa cambian con él.
- Lo que hace como propietario no le da permisos de personal, y viceversa; no puede calificarse a sí mismo.
- El super-administrador es un rol aparte que no se combina con roles de clínica.

**Reglas de negocio:** RN-G01, RN-G06, RN-G13, RN-G18 · **Requisitos:** RF-T.5 · **Dependencias:** HU-T.1, HU-T.15

### HU-T.18 — Registrarme e iniciar sesión con Google (propietario)

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Planificada (v2) | 5 pts | v2 |

> Como propietario, quiero registrarme con mi cuenta de Google para no llenar un formulario largo ni recordar otra contraseña.

**Criterios de aceptación:**

- Desde el enlace o QR de una clínica, o eligiéndola de la lista, puedo continuar con Google; el registro de clínicas y el alta de personal no ofrecen esta opción.
- Si mi correo es nuevo, antes de crear la cuenta acepto la política de tratamiento de datos; si no acepto, no se guarda la identidad ni el vínculo con la clínica. Al aceptar, se crea la cuenta con el nombre, el correo y la foto de Google, sin verificar de nuevo el correo, y quedo vinculado a esa clínica.
- Antes de usar el portal completo, una pantalla obligatoria me pide el tipo y número de documento y el teléfono; mientras no la complete, no puedo registrar mascotas ni agendar.
- Si mi correo ya tiene cuenta, Google se vincula a ella; si estaba pendiente de verificar, queda verificada, se anula la contraseña que tenía y me pide confirmar mis datos.
- El token de Google se valida contra el client_id de Zooki (RN-G12).

**Reglas de negocio:** RN-G20, RN-G21, RN-G19, RN-G12, RN-109 · **Requisitos:** RF-T.6 · **Dependencias:** HU-T.1, HU-5.8

### HU-T.19 — Aceptar la política de tratamiento de datos

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Planificada (v2) | 3 pts | v2 |

> Como titular de datos, quiero aceptar de forma expresa la política de tratamiento de datos al registrarme para saber qué se hace con mi información, como exige la Ley 1581 de 2012.

**Criterios de aceptación:**

- El registro con formulario o Google y el alta presencial de propietarios exige aceptación del titular antes de crear la cuenta. Excepción para personal nuevo: la cuenta se crea pendiente e inerte (sin contraseña ni consentimiento); el titular recibe un enlace que vence en 72 horas, acepta la política y crea su contraseña para activarla. Si vence sin usarse, se elimina la cuenta pendiente únicamente si no tiene otros vínculos. El personal no acepta en nombre del titular. El super-administrador está exento de aceptación y de su prueba por ser una cuenta operativa de plataforma.
- Se guarda la prueba: quién, qué versión, por qué medio, cuándo y desde qué IP.
- Si la política cambia, en el siguiente inicio de sesión se pide aceptar la nueva versión antes de continuar.
- Revocar la autorización lleva a la solicitud de eliminación de la cuenta (HU-5.14).

**Reglas de negocio:** RN-G19, RN-G07, RN-G16 · **Requisitos:** RF-T.7 · **Dependencias:** HU-9.1

---
---

## Módulo 1 — Mascotas y propietarios

> Registro y gestión de pacientes y de sus propietarios.

### HU-1.1 — Registrar mascota

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 5 pts | ZOOK-5 |

> Como veterinario, quiero registrar una mascota con sus datos básicos para tener una ficha digital completa del paciente.

**Criterios de aceptación:**

- El formulario exige nombre, especie, raza, fecha de nacimiento, peso, sexo y color.
- Se puede subir una fotografía (JPG/PNG).
- Al guardar, la mascota aparece en el listado y en la búsqueda.
- No se puede guardar sin propietario asignado.
- _(v2.0)_ Si el propietario ya tiene mascotas en la plataforma, antes de registrar se ofrece vincular la existente (HU-1.5) para no duplicar la ficha.

**Reglas de negocio:** RN-101, RN-102, RN-106, RN-107, RN-110, RN-111 · **Dependencias:** HU-1.2

### HU-1.2 — Registrar propietario

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 3 pts | ZOOK-6 |

> Como administrador o veterinario, quiero registrar los datos del propietario para poder contactarlo y vincularle sus mascotas.

**Criterios de aceptación:**

- Campos obligatorios: nombre, tipo y número de documento, teléfono y correo.
- No se permiten documentos ni correos duplicados.
- Si la cuenta ya existe, el personal la busca por documento o correo completo; el vínculo nuevo solo se crea al confirmar el titular desde un enlace enviado a ese correo (RN-109). La solicitud no modifica su identidad ni contraseña.
- Desde el perfil del propietario se listan todas sus mascotas.

**Reglas de negocio:** RN-101, RN-103, RN-109, RN-G06 · **Dependencias:** —

### HU-1.3 — Buscar paciente

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 3 pts | ZOOK-7 |

> Como veterinario o administrador, quiero buscar mascotas rápidamente para acceder a su ficha sin navegar por listados largos.

**Criterios de aceptación:**

- Búsqueda por nombre de mascota, nombre del propietario o número de documento.
- Resultados en menos de 2 segundos con un mínimo de 3 caracteres.
- Los resultados muestran nombre, especie, propietario y foto miniatura.

**Reglas de negocio:** RN-105 · **Dependencias:** HU-1.1

### HU-1.4 — Editar y desactivar mascota

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 3 pts | ZOOK-8 |

> Como veterinario, quiero actualizar los datos de una mascota o marcarla como inactiva para mantener la información vigente sin perder el historial.

**Criterios de aceptación:**

- _(v2.0)_ La especie, la raza, el sexo y la fecha de nacimiento solo los cambian el propietario o la clínica que registró la mascota; el peso, la foto y los demás datos, cualquier clínica vinculada. Todo cambio se notifica al propietario.
- Se puede editar cualquier campo de la ficha.
- Al guardar se registra en el log la fecha, el usuario y el campo modificado.
- Marcar como inactiva la oculta de las búsquedas activas pero conserva su historial.
- No se permite eliminar permanentemente una mascota con historial clínico.

**Reglas de negocio:** RN-104, RN-105, RN-108, RN-110 · **Dependencias:** HU-1.1

### HU-1.5 — Vincular una mascota existente a la clínica

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Planificada (v2) | 3 pts | v2 |

> Como veterinario, quiero ver las mascotas que un propietario ya tiene en la plataforma y vincular la que corresponde para no crear una ficha duplicada.

**Criterios de aceptación:**

- Al registrar una mascota a un propietario ya vinculado a la clínica, se muestran primero las mascotas que él tiene en la plataforma (nombre, especie, raza y foto), sin datos clínicos.
- Si la mascota ya existe, se vincula a la clínica: conserva su ficha, su carnet y su QR; el número de historia clínica de esta clínica se genera en su primera consulta aquí (RN-102).
- Si no existe, se registra como nueva (HU-1.1).
- La vinculación cuenta para el límite de mascotas del plan (HU-0.4) y queda en auditoría.

**Reglas de negocio:** RN-003, RN-102, RN-110, RN-111 · **Requisitos:** RF-1.8 · **Dependencias:** HU-1.1, HU-5.8

---
---

## Módulo 2 — Historia clínica y soporte a la decisión clínica (Grafo I)

> Registro clínico y, en la v2.0, el copiloto clínico basado en el Grafo I (sugerencia de diagnósticos y alertas de toxicidad/interacción; de apoyo, no diagnóstico automático).

### HU-2.1 — Registrar consulta clínica

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 8 pts | ZOOK-9 |

> Como veterinario, quiero registrar una consulta médica completa para llevar la trazabilidad de la salud del paciente.

**Criterios de aceptación:**

- Campos: fecha/hora, motivo, anamnesis, examen físico, diagnóstico y plan de tratamiento.
- Se genera un número de historia clínica único en la primera consulta.
- La consulta queda vinculada a la mascota y visible en su historial.
- No se puede registrar una consulta sin diagnóstico.
- _(v2.0)_ El veterinario registra las alergias y alertas médicas de la mascota (alergia, condición crónica, medicación continua); no se borran, se desactivan, y las ven todas las clínicas vinculadas y el carnet.

**Reglas de negocio:** RN-201, RN-202, RN-203, RN-113 · **Dependencias:** HU-1.1

### HU-2.2 — Atención clínica sin cita (urgencias)

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 3 pts | Revisión del módulo de consultas |

> Como veterinario, quiero registrar la atención de un paciente que llega sin cita (una urgencia o un imprevisto) para que quede en su historia clínica igual que cualquier otra consulta.

**Criterios de aceptación:**

- El flujo normal es por cita: desde el calendario se inicia la atención y la consulta queda ligada a la cita (HU-4.3, HU-4.5).
- En Consultas Médicas, el botón «Atención sin cita» permite buscar al paciente (HU-1.3) y registrar la consulta sin cita asociada.
- La pantalla explica cuándo usarlo y remite al calendario si el paciente tiene cita.
- Se exigen los mismos datos que en HU-2.1 (motivo y diagnóstico) y, si es su primera consulta, se asigna el número de historia clínica (RN-102).
- En el listado de consultas, las registradas sin cita se distinguen con la etiqueta «Sin cita».

**Reglas de negocio:** RN-102, RN-203, RN-208 · **Dependencias:** HU-1.3, HU-2.1

### HU-2.3 — Adjuntar archivos clínicos

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

**Reglas de negocio:** RN-204, RN-205 · **Dependencias:** HU-2.1

> _Nota: la descarga pasa siempre por `public/ver_archivo.php`, que exige sesión y contexto activo (`Security`) y aplica la misma regla que la historia: en una clínica, el adjunto de una consulta visible según RN-113; para un propietario, solo los de sus mascotas (RN-G02). Si no corresponde, responde 403 y queda en auditoría. El `.htaccess` de la carpeta es una segunda barrera, no la principal: solo cubre Apache con AllowOverride activo. El formato se valida por el contenido del archivo, no por la extensión del nombre que envía el cliente, y al servirlo el `Content-Type` sale de una lista cerrada._

### HU-2.4 — Registrar tratamiento

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 3 pts | ZOOK-11 |

> Como veterinario, quiero registrar el tratamiento prescrito en una consulta para que quede documentado en la historia clínica.

**Criterios de aceptación:**

- Campos: medicamento, dosis, vía de administración, duración y observaciones.
- El tratamiento queda vinculado a la consulta.
- Se pueden registrar múltiples tratamientos por consulta.
- Visible en el resumen de la historia clínica.

**Reglas de negocio:** RN-205 · **Dependencias:** HU-2.1

### HU-2.5 — Ver historial clínico completo

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 5 pts | ZOOK-12 |

> Como veterinario, quiero ver todas las consultas de una mascota en una sola pantalla para tener contexto clínico completo antes de atender.

**Criterios de aceptación:**

- Vista cronológica (más reciente primero) con acordeón expandible.
- Cada entrada muestra fecha, motivo, diagnóstico, tratamientos y archivos.
- Carga en menos de 3 segundos para historiales de hasta 100 consultas.
- Incluye el número de HC y un resumen de vacunas.
- _(v2.0)_ Incluye lo registrado por otras clínicas según lo que el propietario autorizó (HU-2.10).

**Reglas de negocio:** RN-206, RN-113 · **Dependencias:** HU-2.1

### HU-2.6 — Atomicidad y feedback de adjuntos en el registro de consulta

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 5 pts | VD-HC-01/02 |

> Como veterinario, quiero que al registrar una consulta todo se guarde de forma atómica y se me informe si algún adjunto fue rechazado, para no perder evidencia clínica sin darme cuenta.

**Criterios de aceptación:**

- Consulta, HC, archivos y tratamientos se guardan en una transacción (todo o nada).
- Si un adjunto es rechazado, se informa el motivo por archivo.
- No se reporta éxito con datos parciales.

**Reglas de negocio:** RN-204, RN-206 · **Dependencias:** HU-2.1

> _Nota: Deriva del análisis de vacíos (VD-HC-01/02). Consulta, número de historia clínica, adjuntos y tratamientos se escriben en una sola transacción; si algo falla se deshace todo y además se borran los archivos ya movidos, porque el sistema de archivos no participa del rollback. Los adjuntos se revisan **antes** de abrir la transacción: si alguno no es aceptable no se guarda nada y la respuesta detalla qué archivo falló y por qué (`adjuntos_rechazados`), que el formulario muestra en una lista. Antes se descartaban en silencio dentro del bucle y la respuesta seguía diciendo "registrada correctamente con sus adjuntos"._

### HU-2.7 — Validación y control de acceso en registros clínicos y de mascota

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 5 pts | VD-VAC-01, VD-HC-03 |

> Como responsable del sistema, quiero que registrar consultas, vacunas o desparasitaciones exija el rol clínico y verifique la propiedad/existencia de la mascota, para impedir la manipulación de datos ajenos.

**Criterios de aceptación:**

- Registrar consulta/vacuna/desparasitación exige rol clínico.
- Se valida que la mascota exista.
- Se verifica que el actor tenga permiso sobre esa mascota.
- Las entradas se validan antes de guardar.

**Reglas de negocio:** RN-201, RN-207, RN-208, RN-G02 · **Dependencias:** HU-2.1

> _Nota: Deriva del análisis de vacíos (VD-VAC-01, VD-HC-03, VD-SEG-04). Los tres registros clínicos exigen una mascota activa y vinculada a la clínica activa (`ModeloHistoria`, v2.0) y validan las entradas con `ValidadorClinico` antes de guardar. El permiso sobre la mascota se interpreta según RN-207 y RN-208 para el rol clínico, y según RN-G02 para el propietario en el portal._

### HU-2.8 — Sugerencia de diagnósticos probables por síntomas

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Planificada (v2) | 8 pts | v2 |

> Como veterinario, quiero que al registrar los síntomas el sistema me proponga los diagnósticos probables ordenados por peso para apoyarme en el análisis sin reemplazar mi criterio.

**Criterios de aceptación:**

- Con el contexto del paciente (especie, raza, edad, historia) y los síntomas registrados, el Grafo I ordena los diagnósticos probables por peso.
- Los diagnósticos se muestran rankeados; el veterinario puede aceptarlos o escribir el suyo.
- Si el grafo no encuentra coincidencias, el veterinario escribe el diagnóstico libremente; el diagnóstico es obligatorio para continuar (RN-202).
- La sugerencia es de apoyo: nunca diagnostica ni prescribe por sí sola.
- El conocimiento clínico es global y compartido entre clínicas; no pertenece a ninguna en particular.

**Reglas de negocio:** RN-209, RN-210, RN-212, RN-202 · **Requisitos:** RF-2.8 · **Dependencias:** HU-2.1

### HU-2.9 — Alertas de toxicidad e interacción al prescribir

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Planificada (v2) | 8 pts | v2 |

> Como veterinario, quiero que el sistema me advierta o bloquee una prescripción peligrosa para la especie o raza del paciente, o que interactúe con su medicación vigente, para recetar con más seguridad.

**Criterios de aceptación:**

- Al prescribir un fármaco, el Grafo I lo cruza con la especie, la raza, las alergias y la medicación vigente del paciente, incluidas las registradas por otras clínicas (RN-113).
- Si hay contraindicación por especie o raza (toxicidad), la prescripción se **bloquea** con un mensaje para elegir otro fármaco.
- Si hay interacción con la medicación vigente, se **advierte**: el veterinario confirma o cambia, y la decisión queda en auditoría. Si la interacción procede de un tratamiento de otra clínica sin autorización de lectura, el aviso es genérico y el veterinario documenta la conciliación con el propietario o solicita autorización antes de confirmar; no se revela el tratamiento protegido.
- El soporte es de apoyo (RN-209): no receta por sí solo; la decisión final es del veterinario.
- El conocimiento (relaciones positivas y negativas con signo) es global a la plataforma.

**Reglas de negocio:** RN-209, RN-211, RN-212, RN-113 · **Requisitos:** RF-2.9, RF-2.10 · **Dependencias:** HU-2.4, HU-2.8

### HU-2.10 — Ver la historia compartida de la mascota (multi-clínica)

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Planificada (v2) | 5 pts | v2 |

> Como veterinario, quiero ver lo que otras clínicas registraron de la mascota, según lo que el propietario autorizó, para no repetir vacunas ni pasar por alto una alergia.

**Criterios de aceptación:**

- Siempre se muestran los datos básicos, las alergias y alertas médicas, y las vacunas y desparasitaciones de todas las clínicas, cada una con la clínica que la aplicó.
- Las consultas, tratamientos y archivos de otra clínica solo se muestran si el propietario autorizó a esta clínica (HU-5.12); si no, no se muestran ni se indica que existan.
- Los registros de otras clínicas son de solo lectura: solo la clínica que los creó puede modificarlos.
- El Grafo I usa las alergias y alertas de todas las clínicas al validar una prescripción (HU-2.9).

**Reglas de negocio:** RN-112, RN-113 · **Requisitos:** RF-2.6 · **Dependencias:** HU-2.5, HU-5.12

### HU-2.11 — Calcular el nivel de triage a partir de los síntomas

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Planificada (v2) | 8 pts | v2 |

> Como veterinario o propietario, quiero que el sistema calcule la urgencia del caso con reglas claras para que una mascota grave no quede al final de la fila y una leve no ocupe un sobrecupo.

**Criterios de aceptación:**

- Los síntomas se capturan con una lista para marcar (del catálogo del grafo), un texto libre opcional y la pregunta de cuándo empezaron o cuándo ocurrió la ingesta de un tóxico.
- El nivel final es el más alto que dispare cualquier síntoma o combinación; los datos tranquilizadores no bajan una alarma.
- Las combinaciones escalan, y la raza, la edad, el estado, el historial (incluidas alergias de otras clínicas) y el tiempo modifican el nivel.
- Las señales de alarma universales dan 🔴 en cualquier especie.
- Sin cobertura de la especie o sin síntomas reconocidos, el caso queda en 🟡 con la marca «revisión del personal»; nunca en 🟢.
- Si el caso indica una posible enfermedad contagiosa, se avisa a la clínica para preparar el aislamiento.
- Los casos de la batería de triage (`documentacion/CasosTriage.md`) se usan como pruebas de aceptación.

**Reglas de negocio:** RN-411, RN-416, RN-417, RN-418, RN-424, RN-425, RN-209 · **Requisitos:** RF-2.12 · **Dependencias:** HU-2.8, HU-2.10

---
---

## Módulo 3 — Vacunación, desparasitación y recordatorios

> Esquemas de vacunación y desparasitación y los recordatorios automáticos.

### HU-3.1 — Registrar vacunación

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 5 pts | ZOOK-13 |

> Como veterinario, quiero registrar las vacunas aplicadas a una mascota para llevar el control de su esquema de vacunación.

**Criterios de aceptación:**

- Campos: nombre, laboratorio, lote, fecha de aplicación y próxima dosis.
- Al guardar, la vacuna aparece en el calendario de la mascota.
- Se genera una alerta 7 días antes de la próxima dosis.
- El panel muestra las vacunaciones pendientes de la semana.

**Reglas de negocio:** RN-301, RN-306 · **Dependencias:** HU-1.1

### HU-3.2 — Enviar recordatorio por correo

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

**Reglas de negocio:** RN-303, RN-304, RN-305 · **Dependencias:** HU-3.1

### HU-3.3 — Enviar recordatorio por WhatsApp

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Pendiente (futuro) | 5 pts | ZOOK-15 |

> Como sistema, quiero enviar mensajes de WhatsApp al propietario para aumentar la tasa de apertura de los recordatorios.

**Criterios de aceptación:**

- Mensaje con el mismo contenido del correo.
- Solo si el propietario tiene número de WhatsApp.
- Usa la API oficial de WhatsApp Business (Meta).
- El envío queda registrado.

**Reglas de negocio:** RN-307 · **Dependencias:** HU-3.2

> _Nota: No implementada en la v1.8.0. Planificada como evolución futura (canal actual = correo)._

### HU-3.4 — Registrar desparasitación

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 3 pts | ZOOK-16 |

> Como veterinario, quiero registrar y programar la desparasitación de la mascota para que el sistema genere alertas automáticas.

**Criterios de aceptación:**

- Tipos: interna y externa.
- Periodicidad configurable: mensual, trimestral o semestral.
- Al registrar, se calcula la próxima aplicación.
- Las alertas se comportan igual que las de vacunación.

**Reglas de negocio:** RN-302 · **Dependencias:** HU-1.1

### HU-3.5 — Panel de vacunaciones pendientes

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Parcial (diferida) | 3 pts | ZOOK-25 |

> Como veterinario, quiero ver un panel con las vacunaciones pendientes de la semana agrupadas por día y especie para planificar la agenda.

**Criterios de aceptación:**

- Muestra las vacunas con próxima dosis en los próximos 7 días.
- Agrupación por día de la semana y por especie.
- Contador por día visible a primera vista.
- Un clic lleva a la ficha de la mascota.

**Reglas de negocio:** RN-301 · **Dependencias:** HU-3.1

> _Nota: desde v1.9.1 el panel del veterinario muestra las vacunas y desparasitaciones de sus propios pacientes (RE-6.1.4), agrupadas por día con contador y con enlace a la ficha. La agrupación por especie no se muestra en el panel._

> _Nota: diferida en v1.11.0. Falta la agrupación por especie (RE-3.5.2); el panel se rehace con la nueva arquitectura._
> _v2.0: la agrupación por especie se completa con el panel del dashboard (RF-3.3)._

### HU-3.6 — Robustez de los recordatorios automáticos

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 3 pts | VD-REM-01/02/03 |

> Como sistema, quiero que los recordatorios se envíen de forma confiable aunque el cron falle un día, con reintentos y zona horaria correcta, para no dejar a los propietarios sin aviso.

**Criterios de aceptación:**

- Se usa una ventana de fechas con marca de enviado que recupera los no enviados.
- Los envíos fallidos se reintentan, hasta 3 intentos por aviso.
- El cálculo de fechas usa la zona horaria de la clínica.
- No se recuerdan dosis de mascotas inactivas ni dosis que ya se renovaron.

**Reglas de negocio:** RN-303, RN-304, RN-305 · **Dependencias:** HU-3.2

> _Nota: Deriva del análisis de vacíos (VD-REM-01/02/03)._

> _Nota: implementada en v1.11.0. Antes se buscaban fechas exactas (hoy + 7 y hoy + 1), así que un día sin tarea perdía esos avisos. Además, un envío fallido quedaba registrado con estado `error` y la revisión de duplicados lo encontraba, así que nunca se reintentaba. Ahora el primer aviso cubre del día 7 al 2 y el último, el día anterior y el mismo día (`helpers/VentanaRecordatorio.php`). Solo cuenta como enviado un registro `enviado`, y el «hoy» se calcula en la zona de la clínica, no con `CURDATE()`. La selección vive en `models/Recordatorio.php`, que también excluye las mascotas inactivas y las dosis con una aplicación posterior de la misma vacuna o del mismo tipo de desparasitación._

---
---

## Módulo 4 — Agenda de citas inteligente (Grafo II)

> Agendamiento. En la v2.0 se reescribe sobre el Grafo II: disponibilidad real (horario de la clínica ∩ horario del veterinario − ausencias), triage de 4 niveles, reajuste en cascada, ausencias/cobertura y urgencias.

### HU-4.1 — Agendar cita

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 8 pts | ZOOK-17 |

> Como administrador o veterinario, quiero agendar citas verificando la disponibilidad del veterinario para evitar cruces de horario.

**Criterios de aceptación:**

- Campos: fecha, hora, mascota, motivo y veterinario asignado.
- No permite dos citas al mismo veterinario en el mismo horario.
- La cita respeta el horario de atención configurado.
- Al crear, se envía correo de confirmación.
- Las citas aparecen en el calendario.

**Reglas de negocio:** RN-401, RN-402, RN-403, RN-404 · **Dependencias:** HU-1.1

> _v2.0: el agendado incorpora el triage de 4 niveles y la disponibilidad real del Grafo II — ver HU-4.13._

### HU-4.2 — Cancelar o reprogramar cita

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

**Reglas de negocio:** RN-405 · **Dependencias:** HU-4.1

### HU-4.3 — Marcar cita como completada

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
- Al terminar el día, una atención abierta pasa a "sin cerrar"; la completo registrando su consulta.
- La revisión de atenciones abiertas corre sola cada 5 minutos, así que el aviso llega aunque nadie abra el calendario.
- La cita completada aparece en el historial del día pero no en la agenda futura.

**Reglas de negocio:** RN-406, RN-407, RN-409, RN-410 · **Dependencias:** HU-4.1, HU-2.1

> _Nota: hasta v1.8.0 la cita se completaba con un botón aparte, sin consulta, y la podían iniciar recepción y administración. Como la pantalla de atención es solo del veterinario, esas citas quedaban "en curso" sin nadie que las atendiera, y una cita de un día anterior perdía todos los botones del calendario. Ver el Módulo 4 de `AuditoriaModulos.md`._

> _v2.0: «cerrar sin consulta» se retira (RN-410 derogada, D-3 del plan M0): una atención iniciada solo se completa con su consulta. RE-4.3.8 queda derogado._

> _Nota: desde v1.9.0 una atención ya no queda abierta indefinidamente. Si el veterinario salía sin terminar, la cita seguía "en curso" para siempre sin que nadie se enterara. Ahora se avisa a los 10 minutos de la hora de fin (`VigilanteAtenciones`, también como tarea programada en `scripts/vigilar_atenciones.php`), pasa a "sin cerrar" al terminar el día y se puede cerrar sin consulta con motivo. Además, la atención solo se inicia desde 15 minutos antes de la hora de la cita, para no abrir por error la de un paciente que aún no llega._

### HU-4.4 — Confirmación automática de cita por correo

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 3 pts | ZOOK-27 |

> Como sistema, quiero enviar un correo de confirmación automática al propietario al agendar la cita para que tenga constancia del horario y reduzca inasistencias.

**Criterios de aceptación:**

- Correo enviado inmediatamente tras guardar la cita.
- Incluye fecha, hora, mascota, motivo y dirección.
- No bloquea la respuesta del agendado.
- Un fallo de envío se registra como fallido.

**Reglas de negocio:** RN-404 · **Dependencias:** HU-4.1

### HU-4.5 — Registrar hora real de atención y gestionar retrasos

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Parcial (diferida) | 8 pts | VD-AGN-01 |

> Como veterinario, quiero registrar la hora real de inicio y fin de cada atención y que el sistema detecte los retrasos, para que la agenda coincida con la realidad y no se acumulen los choques.

**Criterios de aceptación:**

- Al iniciar y completar se sella la hora real.
- El sistema detecta cuando una atención excede su duración planificada.
- Alerta o recalcula el corrimiento de las citas siguientes del veterinario.

**Reglas de negocio:** RN-401 · **Dependencias:** HU-4.3

> _Nota: Deriva del análisis de vacíos (VD-AGN-01): el caso del calendario con retrasos en cascada. En v1.9.0 se cumple el primer criterio: `hora_inicio_real` y `hora_fin_real` (migración 09) se sellan al iniciar la atención y al guardar la consulta que completa la cita. Siguen pendientes la detección del exceso de duración y el aviso de corrimiento a las citas siguientes._

> _Nota: diferida en v1.11.0: la agenda se rehace con la nueva arquitectura. Faltan RE-4.5.2 y RE-4.5.3._

> _v2.0: la detección del retraso y el corrimiento en cascada se completan en HU-4.14._

### HU-4.6 — Buffer configurable entre citas

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Pendiente (diferida) | 3 pts | VD-AGN-02 |

> Como administrador, quiero definir un tiempo de amortiguación entre citas para absorber pequeños retrasos sin generar choques.

**Criterios de aceptación:**

- Se configura un buffer entre citas.
- La disponibilidad lo respeta al agendar.
- El buffer se refleja en las sugerencias de horario.

**Reglas de negocio:** RN-401, RN-403 · **Dependencias:** HU-4.1

> _Nota: Deriva del análisis de vacíos (VD-AGN-02)._

> _Nota: diferida en v1.11.0: la agenda se rehace con la nueva arquitectura._


> _v2.0: el buffer entre citas se generaliza como el margen por tipo de cita (RN-415) — ver HU-4.13, HU-4.14 y HU-7.4._

### HU-4.7 — Estado "no asistió" y ausentismo

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Parcial (diferida) | 3 pts | VD-AGN-07 |

> Como veterinario, quiero marcar una cita como "no asistió" para liberar el espacio y medir el ausentismo.

**Criterios de aceptación:**

- Existe el estado "no asistió".
- Lo marca el veterinario asignado, y solo cuando la hora de la cita ya pasó.
- Una cita marcada así libera el espacio.
- Se puede reportar la tasa de ausentismo.

**Reglas de negocio:** RN-405, RN-408 · **Dependencias:** HU-4.1

> _Nota: Deriva del análisis de vacíos (VD-AGN-07). En v1.9.0 se implementaron el estado `no_asistio` (migración 09), el botón «No asistió» del calendario y la liberación del espacio en toda la lógica de disponibilidad. Falta un reporte propio de la tasa de ausentismo: por ahora las inasistencias solo se ven en la gráfica «Mis citas» del panel del veterinario (últimos 30 días)._

> _Nota: diferida en v1.11.0: la agenda se rehace con la nueva arquitectura. Falta RE-4.7.3 (tasa de ausentismo)._
> _v2.1: se completa el reporte propio de la tasa de ausentismo. El estado `no_asistio` y la liberación del espacio ya existen desde v1.9.0 y se conservan al migrar la agenda en v2.0; HU-4.19 depende solo de esa base operativa._

### HU-4.8 — Bloqueos de agenda del veterinario y días no laborables

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Pendiente (diferida) | 5 pts | VD-AGN-08 |

> Como administrador, quiero registrar bloqueos del veterinario (almuerzo, permiso, incapacidad) y días festivos o cierres, para que no se agenden citas en esos periodos.

**Criterios de aceptación:**

- Se registran bloqueos por veterinario y por fecha.
- Se registran festivos y cierres puntuales.
- La disponibilidad excluye esos periodos.

**Reglas de negocio:** RN-402, RN-703 · **Dependencias:** HU-4.1

> _Nota: Deriva del análisis de vacíos (VD-AGN-08)._

> _Nota: diferida en v1.11.0: la agenda se rehace con la nueva arquitectura._

> _v2.0: bloqueos y días no laborables se cubren con el horario recurrente del veterinario (HU-4.11) y las ausencias (HU-4.12)._

### HU-4.9 — Endurecer la validación de disponibilidad

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Parcial (diferida) | 5 pts | VD-AGN-03/04/05/06 |

> Como responsable del sistema, quiero que toda validación de disponibilidad se haga en el backend, con una sola fuente de horario que respete la duración y sin condición de carrera, para impedir citas inválidas o dobles.

**Criterios de aceptación:**

- La disponibilidad usa una sola lógica basada en horarios_clinica.
- Valida horario + duración en el backend.
- Usa transacción o restricción única para evitar dobles reservas.

**Reglas de negocio:** RN-401, RN-402 · **Dependencias:** HU-4.1

> _Nota: Deriva del análisis de vacíos (VD-AGN-03/04/05/06)._

> _Nota: diferida en v1.11.0: la agenda se rehace con la nueva arquitectura. Estado en v1.10.1: las sugerencias de horario recorren un horario fijo de 08:00 a 18:00 en lugar de `horarios_clinica` (RE-4.9.1); `validarHorarioLaboral` solo mira la hora de inicio, no la duración, e ignora los bloques desactivados que conservan sus horas (RE-4.9.2); el índice `uq_cita_vet_activa` impide dos citas que empiezan a la misma hora, pero no dos reservas simultáneas que se solapan con distinta hora de inicio (RE-4.9.3)._

> _v2.0: la validación de disponibilidad se reescribe sobre el Grafo II — ver HU-4.13._

### HU-4.10 — Confirmar asistencia a la cita desde el recordatorio

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Planificada (v2) | 3 pts | Deseable |

> Como propietario, quiero confirmar o declinar mi asistencia desde el recordatorio de la cita para que la clínica sepa con anticipación si asistiré.

**Criterios de aceptación:**

- El recordatorio incluye la opción de confirmar o cancelar la asistencia.
- La respuesta actualiza el estado de la cita.
- Si declino, se libera el espacio y se notifica a la clínica.
- La respuesta queda registrada.

**Reglas de negocio:** RN-404, RN-405 · **Requisitos:** RF-4.15 · **Dependencias:** HU-4.4, HU-3.2

> _Nota: diferida en v1.11.0: la agenda se rehace con la nueva arquitectura._

> _Nota: Función deseable propuesta (no construida)._
> _v2.0: entra al alcance de la v2 porque ataca el ausentismo y los huecos en la agenda; la cita confirmada o declinada alimenta el Grafo II._

### HU-4.11 — Configurar el horario recurrente del veterinario

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Planificada (v2) | 5 pts | v2 |

> Como administrador de la clínica, quiero definir el horario recurrente de cada veterinario por día de la semana para que la agenda refleje cuándo atiende realmente cada uno.

**Criterios de aceptación:**

- Se definen franjas de atención por día de la semana para cada veterinario, sin solapamientos y dentro del horario de la clínica.
- Si las franjas se solapan o caen fuera del horario de la clínica, se rechaza con un mensaje que indica el motivo.
- El horario recurrente reemplaza el supuesto de disponibilidad permanente: la agenda solo ofrece espacios dentro de esas franjas.
- Lo configura el administrador. El veterinario solo puede proponer un cambio desde su portal, y el cambio se aplica cuando el administrador lo aprueba.
- Si al cambiar el horario quedan citas por fuera del nuevo horario, se marcan para reajuste (HU-4.14) y queda en auditoría.

**Reglas de negocio:** RN-706, RN-703, RN-415 · **Requisitos:** RF-4.8 · **Dependencias:** HU-7.1

### HU-4.12 — Registrar ausencia del veterinario y gestionar cobertura

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Planificada (v2) | 8 pts | v2 |

> Como administrador (o veterinario desde su portal), quiero registrar una ausencia y que el sistema cubra sus citas para que la agenda se mantenga sin huecos cuando alguien no puede atender.

**Criterios de aceptación:**

- La ausencia la registra el administrador o la solicita el propio veterinario; se define por un rango de fechas o franjas y tapa su disponibilidad en ese rango.
- Si el rango es inválido, se rechaza con un mensaje para corregir las fechas.
- Si el veterinario ausente tiene citas en ese rango, el Grafo II busca un veterinario con disponibilidad compatible y le **asigna** esas citas (sin veto: si tiene espacio y nada se lo impide, las toma).
- Si ningún veterinario puede cubrir, las citas se **reprograman** ofreciendo otro espacio al propietario.
- Al asignar cobertura se notifica al propietario y a ambos veterinarios; un "intercambio de turnos" es simplemente una ausencia del titular más la cobertura del reemplazo.
- La trazabilidad de quién atendió de verdad la da la historia clínica (RN-208), no la agenda. Queda en auditoría.

**Reglas de negocio:** RN-414, RN-415 · **Requisitos:** RF-4.9 · **Dependencias:** HU-4.11

### HU-4.13 — Agendar cita con triage de 4 niveles

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Planificada (v2) | 8 pts | v2 |

> Como propietario o veterinario, quiero que al agendar se calcule la prioridad del caso a partir de los síntomas para que la cita se ubique según su urgencia y no solo por orden de llegada.

**Criterios de aceptación:**

- Al describir el motivo y los síntomas, el **Grafo I** calcula el nivel de triage (🔴 rojo, 🟠 naranja, 🟡 amarillo, 🟢 verde); el propietario no lo elige.
- El nivel se calcula según HU-2.11. Si es 🔴, no se verifica el límite del plan (RN-420); en los demás niveles se verifica antes de reservar (HU-0.4).
- 🔴 **Rojo (crítico):** no se agenda en línea; se avisa y se deriva a atención de urgencia (HU-4.15).
- 🟠 **Naranja (urgente):** se ubica por **sobrecupo** sobre el próximo bloque del veterinario adecuado, hasta el tope de sobrecupos del bloque. Si dos compiten, va primero el que llegó antes; un veterinario puede cambiar el orden con motivo obligatorio y auditoría. Superado el tope, se avisa al personal.
- 🟡 **Amarillo (prioritario):** toma el primer espacio disponible priorizado.
- 🟢 **Verde (no urgente):** el actor elige entre los espacios disponibles; si no hay, se ofrece cambiar de fecha o rango.
- La disponibilidad se calcula como `horario clínica ∩ horario del veterinario − ausencias`, por duración + margen del tipo de cita (RN-415).
- La reserva se concreta con bloqueo (si el espacio se ocupó antes, se busca otro); al crear se registran tipo, duración, margen y prioridad, se notifica y queda en auditoría.

**Reglas de negocio:** RN-411, RN-415, RN-401, RN-402, RN-420, RN-422 · **Requisitos:** RF-4.7 · **Dependencias:** HU-4.1, HU-4.11, HU-2.8

### HU-4.14 — Reajuste de agenda en vivo (efecto cascada)

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Planificada (v2) | 8 pts | v2 |

> Como veterinario, quiero que un retraso se propague y se reacomode solo para que la agenda del día siga siendo realista sin tener que reorganizarla a mano.

**Criterios de aceptación:**

- El disparador es el margen (buffer) del tipo de cita: mientras el retraso cabe en el margen, no pasa nada.
- Cuando una atención supera su hora de fin más el margen, el retraso se propaga a las citas siguientes del mismo veterinario y se recalculan sus horas estimadas de inicio.
- Si el retraso saca una cita del **horario disponible** del veterinario, el Grafo II busca reasignarla a otro veterinario con disponibilidad.
- La reasignación en vivo la **confirma el veterinario destino** dentro del plazo configurado (por defecto, 10 minutos); al confirmar, la cita se mueve y se notifica al propietario y a ambos veterinarios. Si no responde o rechaza, se propone al siguiente veterinario disponible.
- Si ningún veterinario puede tomarla, la cita se **reprograma** ofreciendo otro espacio al propietario.
- Los cambios de hora estimada se avisan al propietario solo si la diferencia acumulada supera el umbral configurado (por defecto, 15 minutos) y como máximo una vez cada 30 minutos por cita; la reasignación y la reprogramación se avisan siempre. Todo cambio queda en auditoría.

**Reglas de negocio:** RN-412, RN-413, RN-415, RN-428, RN-429 · **Requisitos:** RF-4.5, RF-4.6 · **Dependencias:** HU-4.13, HU-4.5

### HU-4.15 — Atender una urgencia (triage rojo o llegada directa)

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Planificada (v2) | 8 pts | v2 |

> Como veterinario, quiero atender de inmediato un caso crítico aunque no tenga cita para no perder tiempo en una emergencia.

**Criterios de aceptación:**

- Se dispara cuando el Grafo I marca un caso **rojo** o cuando llega una urgencia directa a la clínica; se notifica a la clínica y se marca prioridad máxima.
- Si el caso llega desde el portal con la clínica fuera de su horario, no se promete atención: se informa que está cerrada, se muestra su teléfono de urgencias (si lo configuró) y se recomienda un servicio de urgencias 24 horas.
- La urgencia no se bloquea por el límite del plan ni por la mora.
- Si hay un veterinario libre de inmediato, se le asigna; si no, se toma a uno cuya cita en curso sea **pausable** según su tipo de cita, y se avisa el corrimiento a los afectados. La cita pausada se retoma al terminar la urgencia, antes que las siguientes.
- Si todos están en atención no pausable, la urgencia queda "en espera por prioridad" y la toma el primer veterinario que se libere.
- Si el rojo se origina en el **portal autenticado**, se usan el propietario de la sesión y la mascota seleccionada; no se repite la verificación de identidad ni se crea una ficha provisional.
- Si el paciente **llega físicamente a la clínica**, el personal usa la cuenta y la ficha existentes cuando puede verificar su relación sin retrasar la atención roja.
- Si en ese ingreso presencial no se puede identificar o vincular con seguridad a la mascota, se crea una **ficha provisional de emergencia** vinculada a la clínica, con los datos disponibles del animal y un ingreso con los datos disponibles de quien lo trae. La ficha queda «por completar», sin carnet público activo y sin crear una cuenta de usuario ni atribuirle la propiedad al acompañante; el límite del plan no bloquea este ingreso rojo.
- Después de la atención se verifica la titularidad. Si la mascota ya tenía ficha, se consolidan los actos clínicos en ella y se conserva la trazabilidad del ingreso provisional; si es nueva, se completa su ficha y se vincula a la cuenta existente del propietario o se crea una cuenta nueva solo tras su consentimiento (RN-G19). Nada clínico se borra.
- La atención continúa por el flujo de consulta clínica vía el módulo de Atención/Urgencias (HU-2.2).
- Como el uso del espacio altera la agenda del día, enlaza con el reajuste en vivo (HU-4.14).

**Reglas de negocio:** RN-411, RN-415, RN-420, RN-421, RN-427 · **Requisitos:** RF-4.10 · **Dependencias:** HU-2.2, HU-4.14

---

### HU-4.16 — Sugerir veterinario al agendar

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Baja | Planificada (v2) | 5 pts | v2 |

> Como propietario, quiero que al agendar el sistema me sugiera veterinarios según su valoración y especialidad para elegir mejor.

**Criterios de aceptación:**

- Al agendar, el sistema ofrece veterinarios ordenados por afinidad (especialidad) y valoración.
- La sugerencia es una entrada al emparejamiento del Grafo II; el actor sigue pudiendo elegir libremente.

**Reglas de negocio:** RN-803 · **Requisitos:** RF-8.4 · **Dependencias:** HU-8.2, HU-4.13

### HU-4.17 — Recalcular el triage cuando cambian los síntomas

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Planificada (v2) | 3 pts | v2 |

> Como propietario, quiero actualizar los síntomas de mi mascota antes de la cita para que, si empeora, la atiendan antes.

**Criterios de aceptación:**

- Desde el portal, el propietario actualiza los síntomas de una cita pendiente.
- El nivel se recalcula (HU-2.11). Si sube, la cita se reubica según el nuevo nivel (o se deriva a urgencia si es 🔴) y se notifica al propietario y al veterinario.
- Si baja, la cita conserva su espacio.
- Cada recálculo queda en auditoría con el nivel anterior y el nuevo.

**Reglas de negocio:** RN-423, RN-411 · **Requisitos:** RF-4.11 · **Dependencias:** HU-4.13, HU-2.11

### HU-4.18 — Ajustar el nivel de triage (veterinario)

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Planificada (v2) | 3 pts | v2 |

> Como veterinario, quiero corregir el nivel de triage cuando mi criterio clínico difiere del sistema para que la agenda refleje la urgencia real.

**Criterios de aceptación:**

- El veterinario puede subir o bajar el nivel de una cita o de un paciente que acaba de llegar, con motivo obligatorio.
- El cambio queda en auditoría, y la diferencia entre el nivel calculado y el ajustado se guarda para mejorar el grafo.
- Si el ajuste cambia la ubicación de la cita, la agenda se reacomoda y se notifica.
- Las reclasificaciones quedan visibles en el historial del propietario, para que la clínica detecte si alguien exagera los síntomas de forma repetida.

**Reglas de negocio:** RN-419, RN-209 · **Requisitos:** RF-4.12 · **Dependencias:** HU-4.13

### HU-4.19 — Manejar la llegada tarde del propietario

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Planificada (v2) | 5 pts | v2 |

> Como veterinario, quiero que la agenda sepa qué hacer cuando un propietario llega tarde para que su retraso no descuadre el resto del día sin que nadie lo decida.

**Criterios de aceptación:**

- El personal registra la hora de llegada del propietario.
- Dentro de la tolerancia configurada (por defecto, 10 minutos), la cita se atiende en el tiempo que queda. Si el tipo de cita no cabe, el veterinario elige: atender igual (el retraso se propaga, HU-4.14) o reprogramar.
- Pasada la tolerancia, el veterinario elige: marcar «no asistió», atender en el siguiente espacio libre o reprogramar.
- En todos los casos la agenda se recalcula y la decisión queda en auditoría.

**Reglas de negocio:** RN-426, RN-408, RN-412 · **Requisitos:** RF-4.14 · **Dependencias:** HU-4.14, HU-4.7

> _La dependencia con HU-4.7 en v2.0 se limita al estado `no_asistio` y a liberar el espacio, ya implementados en v1.9.0. El reporte de ausentismo queda en v2.1._

---
---

## Módulo 5 — Portal del propietario (multi-clínica)

> Portal de autoservicio del propietario. En la v2.0 el propietario es una identidad global vinculable a varias clínicas, con carnet digital por QR.

### HU-5.1 — Portal del propietario

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 8 pts | ZOOK-19 |

> Como propietario de mascota, quiero acceder a un portal web con la información de mi mascota para consultar su historial y próximas citas sin depender de la clínica.

**Criterios de aceptación:**

- Acceso con credenciales propias o con Google.
- Solo veo las mascotas vinculadas a mi cuenta.
- Veo ficha, historial, próximas citas y calendario de vacunas.
- No tengo acceso a datos de otros propietarios (403).
- Interfaz que se adapta a móvil, tablet y escritorio (RNF-16): barra inferior en móvil y tablet, menú lateral en escritorio.
- El botón «atrás» del navegador me devuelve a la sección anterior y cierra la ventana que tenga abierta.
- Veo si la clínica está abierta ahora y su horario de la semana; _(v2.0)_ el de cada clínica a la que estoy vinculado.
- Los formularios indican qué campos son obligatorios y cuáles opcionales.
- Al registrar o editar una mascota elijo la raza del catálogo; si no está, indico que es mestiza, que no la sé o la escribo para que la clínica la confirme, y veo la foto antes de guardarla. El color lo registra la clínica en la consulta.
- Al agendar no puedo elegir días en que la clínica no atiende, y los horarios libres aparecen en botones cuando ya elegí tipo, veterinario y día.

> **Nota:** el portal mostraba un banner fijo de «Médicos calificados las 24 horas del día», que no correspondía al horario configurado en HU-7.1. Se reemplazó por el horario real.

**Reglas de negocio:** RN-G02 · **Dependencias:** HU-T.1, HU-7.1

### HU-5.2 — Auto-registro de propietario

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

**Reglas de negocio:** RN-101, RN-G06, RN-501 · **Dependencias:** —

> _v2.0: reemplazada por HU-5.8 (propietario como identidad global multi-clínica)._

### HU-5.3 — Agendar cita desde el portal

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 5 pts | ZOOK-31 |

> Como propietario registrado, quiero programar citas para mis mascotas directamente desde mi portal para gestionar su atención a mi conveniencia.

**Criterios de aceptación:**

- Selecciono una de mis mascotas.
- Selecciono la clínica (solo entre aquellas a las que estoy vinculado), el tipo de cita, el veterinario, la fecha y la hora disponible.
- _(v2.0)_ Si la mascota aún no está vinculada a esa clínica, se vincula al agendar y cuenta para el límite del plan de la clínica (RN-110).
- Se verifica la disponibilidad (sin empalmes).
- Envío de confirmación por correo y registro en auditoría.

**Reglas de negocio:** RN-401, RN-402, RN-501, RN-109, RN-110 · **Dependencias:** HU-5.2, HU-4.1

> _v2.0: el agendado desde el portal usa la agenda inteligente — ver HU-4.13 y HU-5.9._

### HU-5.4 — Editar mi mascota desde el portal

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 3 pts | Nuevo |

> Como propietario, quiero editar los datos de mi mascota desde mi portal para mantener su ficha actualizada.

**Criterios de aceptación:**

- Puedo editar los datos de mi mascota.
- Solo puedo editar mis propias mascotas.
- Los cambios quedan registrados.
- Puedo actualizar la foto con validación de tipo y tamaño.

**Reglas de negocio:** RN-104, RN-108, RN-G02 · **Dependencias:** HU-5.1

### HU-5.5 — Imprimir/exportar el historial de mi mascota

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 3 pts | Nuevo |

> Como propietario, quiero imprimir o exportar el historial clínico de mi mascota para tenerlo o compartirlo fuera del sistema.

**Criterios de aceptación:**

- Genero una versión imprimible del historial.
- Incluye consultas, vacunas y desparasitaciones.
- Solo de mis propias mascotas.
- _(v2.0)_ Incluye los registros de todas las clínicas donde se atendió, identificando la clínica de cada uno (RN-114).

**Reglas de negocio:** RN-206, RN-G02, RN-114 · **Dependencias:** HU-5.1

### HU-5.6 — Cancelar o reprogramar mi cita desde el portal

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

**Reglas de negocio:** RN-405, RN-401, RN-G02 · **Dependencias:** HU-5.3

> _Nota: Función deseable propuesta. Desde v1.9.0 el propietario cancela sus citas pendientes o confirmadas desde el portal: el botón existía, pero el portal buscaba un estado «programada» que no existe y nunca lo mostraba (M4-07). Siguen pendientes reprogramar desde el portal y el registro del cambio en auditoría._

> _Nota: diferida en v1.11.0: la agenda se rehace con la nueva arquitectura. Faltan RE-5.6.2 (reprogramar desde el portal) y RE-5.6.4 (la cancelación avisa a la clínica pero no queda en `auditoria_sistema`)._

> _v2.0: cancelar/reprogramar desde el portal se integra con la agenda inteligente — ver HU-4.13 y HU-4.14._

### HU-5.7 — Centro de notificaciones del propietario

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Planificada (v2) | 3 pts | Deseable |

> Como propietario, quiero ver dentro del portal las notificaciones de mi mascota (recordatorios, confirmaciones, cambios de cita) para no depender solo del correo.

**Criterios de aceptación:**

- Veo un listado de mis notificaciones dentro del portal.
- Hay un contador de no leídas.
- Puedo marcarlas como leídas.
- Solo veo las notificaciones que me corresponden.

**Reglas de negocio:** RN-G02 · **Requisitos:** RF-5.6 · **Dependencias:** HU-5.1

> _Nota: Función deseable propuesta (hoy el propietario solo recibe correo). En la v2.0 entra al alcance: el portal concentra los avisos de citas, recordatorios, cambios de agenda y escaneos del carnet._

### HU-5.8 — Registrar al propietario como identidad global

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Planificada (v2) | 8 pts | v2 |

> Como propietario, quiero una sola cuenta en la plataforma que sirva para las clínicas donde atiendo a mis mascotas para no repetir registros ni manejar varias contraseñas.

**Criterios de aceptación:**

- Tres vías de registro, siempre en el contexto de una clínica: **alta por el personal**, **enlace/QR de la clínica** y **autoregistro directo** eligiendo la clínica de una lista.
- Al registrarse queda vinculado **solo** a esa clínica; nunca queda vinculado a clínicas que no eligió. Para sumar otra clínica la elige él mismo desde su portal (HU-5.13).
- El correo es único en toda la plataforma (RN-G06); si ya existe, no se duplica la identidad.
- Cuando el correo ya existe, previa **verificación de que es el dueño del correo** (login o confirmación por correo), se liga el propietario existente a la nueva clínica; si ya estaba ligado, se le indica que inicie sesión.
- En un registro nuevo se crea la identidad global, se liga a la clínica (`propietario_clinica`) y se envía verificación de correo; al activar, se le envía su enlace de acceso y el QR de su portal.
- Cada clínica solo ve los propietarios vinculados a ella. Todo queda en auditoría.

**Reglas de negocio:** RN-109, RN-G06 · **Requisitos:** RF-5.3 · **Dependencias:** —

### HU-5.9 — Iniciar sesión en el portal multi-clínica

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Planificada (v2) | 5 pts | v2 |

> Como propietario vinculado a varias clínicas, quiero entrar una sola vez y ver todas mis mascotas y su historia, eligiendo la clínica solo cuando hago algo en ella, para no tener que cambiar de clínica para ver a mi mascota.

**Criterios de aceptación:**

- Inicia sesión con correo y contraseña (o Google); si las credenciales son incorrectas o la cuenta no está verificada/activa, se informa el motivo.
- Tras iniciar sesión ve todas sus mascotas y la historia completa de cada una en todas las clínicas, identificando la clínica de cada registro (RN-114).
- Al agendar, cancelar o calificar elige la clínica **solo entre aquellas a las que está vinculado** (nunca se le muestran las demás clínicas de la plataforma); si está vinculado a una sola, se usa directamente.
- Cada acción propia de una clínica queda acotada a la `id_clinica` de esa clínica (aislamiento, RN-G13).
- El registro del propietario se sigue haciendo siempre en el contexto de una clínica (RN-109, HU-5.8).

**Reglas de negocio:** RN-109, RN-114, RN-G13 · **Requisitos:** RF-5.3 · **Dependencias:** HU-5.8

---

### HU-5.10 — Carnet digital de la mascota con QR de emergencia

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Planificada (v2) | 5 pts | v2 |

> Como propietario, quiero un carnet digital con QR de mi mascota para que, ante una emergencia, cualquiera pueda ver su información vital.

**Criterios de aceptación:**

- Al guardar la mascota se genera su carnet con un código QR (HU-1.1). La mascota tiene un solo carnet en toda la plataforma, aunque se atienda en varias clínicas (RN-110).
- El QR apunta a un token aleatorio no predecible, nunca al identificador interno de la mascota.
- El carnet muestra solo datos de emergencia: nombre, foto, especie y raza, alergias y alertas médicas, vacunas vigentes con la clínica que las aplicó, un teléfono del propietario y el de la clínica. No muestra dirección, correo, documento ni la historia clínica.
- El QR se puede imprimir o compartir y se mantiene igual hasta que el propietario lo regenere.
- El propietario puede regenerar el QR desde su portal; el anterior deja de funcionar al instante y el cambio queda en auditoría.

**Reglas de negocio:** RN-110, RN-502, RN-503 · **Requisitos:** RF-5.7 · **Dependencias:** HU-1.1, HU-3.1

### HU-5.11 — Acceder al carnet por QR en modo solo lectura

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Planificada (v2) | 5 pts | v2 |

> Como rescatista o persona que encuentra a la mascota, quiero escanear su QR y ver su información de emergencia sin iniciar sesión para ayudarla rápido.

**Criterios de aceptación:**

- Escanear el QR abre el carnet en modo solo lectura, sin iniciar sesión y sin exponer datos ajenos a la emergencia.
- Si el token es inválido, fue regenerado o la mascota está inactiva, se muestra un mensaje genérico que no revela si la mascota existe.
- Cada escaneo notifica al propietario con la fecha y la hora; los escaneos repetidos en poco tiempo se agrupan en un solo aviso.
- Quien escanea puede, si quiere, compartir su ubicación con el dueño mediante un botón; la ubicación nunca se toma sin su permiso.
- Se aplica un límite de consultas por IP y la página no es indexable por buscadores.
- Si el propietario quiere ver o editar la ficha completa, se le solicita iniciar sesión.

**Reglas de negocio:** RN-502, RN-503, RN-504, RN-505 · **Requisitos:** RF-5.8 · **Dependencias:** HU-5.10

### HU-5.12 — Autorizar a una clínica a ver la historia de otras clínicas

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Planificada (v2) | 3 pts | v2 |

> Como propietario, quiero decidir qué clínicas pueden ver las consultas que otras clínicas le hicieron a mi mascota para que esa información viaje solo con mi permiso.

**Criterios de aceptación:**

- En el portal, por cada clínica vinculada, el propietario activa o revoca el acceso a la historia registrada por otras clínicas.
- La revocación aplica de inmediato.
- Sin autorización, la clínica solo ve los datos básicos, las alergias y alertas, y las vacunas y desparasitaciones (RN-113).
- Si el propietario se desvincula de una clínica, esta pierde la autorización y deja de ver los datos nuevos (RN-115).
- Cada cambio de autorización queda en auditoría.

**Reglas de negocio:** RN-113, RN-115, RN-G07 · **Requisitos:** RF-2.6 · **Dependencias:** HU-5.8

### HU-5.13 — Vincularme o desvincularme de una clínica

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Planificada (v2) | 3 pts | v2 |

> Como propietario, quiero vincularme a otra clínica cuando yo lo decida, con un clic desde mi portal, para atender a mis mascotas allí sin crear otra cuenta.

**Criterios de aceptación:**

- Desde el portal veo la lista de clínicas activas de la plataforma y puedo vincularme a una con un clic; como ya inicié sesión, no se me pide verificar de nuevo el correo.
- La clínica nueva solo ve mis datos de propietario; mis mascotas se le vinculan cuando agendo con ellas allí o cuando la clínica las atiende (RN-110).
- Puedo desvincularme de una clínica; la clínica conserva lo que registró, deja de ver mis datos nuevos y pierde la autorización a la historia de otras clínicas (RN-115). No puedo desvincularme mientras tenga citas pendientes en ella: primero debo cancelarlas.
- Cada vinculación y desvinculación queda en auditoría.

**Reglas de negocio:** RN-109, RN-110, RN-115 · **Requisitos:** RF-5.4 · **Dependencias:** HU-5.8, HU-5.9

### HU-5.14 — Eliminar mi cuenta (derecho de supresión)

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Planificada (v2) | 5 pts | v2 |

> Como propietario, quiero pedir la eliminación de mi cuenta para ejercer mi derecho de supresión de datos personales (Ley 1581 de 2012).

**Criterios de aceptación:**

- Desde su perfil, el propietario solicita eliminar su cuenta y confirma con su contraseña (o con Google).
- Si tiene citas pendientes, se le informa que se cancelarán y se notifica a las clínicas.
- Sus datos personales (nombre, documento, correo, teléfono, dirección) se anonimizan de forma irreversible y no puede volver a iniciar sesión.
- La historia clínica de sus mascotas se conserva anonimizada, porque las clínicas deben custodiarla (Ley 576 de 2000); los carnets QR de sus mascotas se desactivan.
- Recibe un correo que confirma la eliminación, y la operación queda en auditoría sin datos personales.

**Reglas de negocio:** RN-G16, RN-G07 · **Requisitos:** RF-5.9 · **Dependencias:** HU-T.5

---
---

## Módulo 6 — Dashboard y reportes

> Paneles de operación e indicadores para el personal de la clínica.

### HU-6.1 — Dashboard principal y panel de pendientes

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

**Reglas de negocio:** RN-G01, RN-407, RN-409 · **Dependencias:** HU-T.1

> _Nota: hasta v1.9.0 el panel mezclaba gráficas con datos inventados cuando no había información, la agenda de hoy mostraba las citas de todos los veterinarios y el "hoy" se calculaba en UTC. Desde v1.9.1 el panel se pinta en el servidor con la hora de la clínica (`PanelController`) y responde a "¿qué tengo que hacer ahora?"._

### HU-6.2 — Panel de operación del administrador

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

**Reglas de negocio:** RN-G01, RN-408, RN-409 · **Dependencias:** HU-6.1, HU-4.3

### HU-6.3 — Generar reportes en PDF

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Retirada | 5 pts | ZOOK-20 |

> _Nota: en v1.9.1 se eliminó el módulo de reportes (`views/admin/reportes.php` y la ruta `admin_reportes`). Ya no estaba en el menú y solo imprimía la página desde el navegador. Los indicadores de operación del administrador pasaron a su panel de inicio (HU-6.2)._

> Como veterinario, quiero generar reportes exportables en PDF para tener resúmenes de la actividad de la clínica.

**Criterios de aceptación:**

- Reportes: listado de pacientes, citas del período y vacunaciones pendientes.
- Se puede filtrar por rango de fechas.
- El PDF se descarga desde el navegador.
- Incluye encabezado con nombre de la clínica y fecha.

**Reglas de negocio:** RN-701 · **Dependencias:** —

### HU-6.4 — Ver estadísticas y gráficas del sistema

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Implementada | 5 pts | Nuevo |

> Como administrador, quiero ver estadísticas y gráficas del sistema (pacientes, clientes, citas, consultas) para tener una visión general de la operación.

**Criterios de aceptación:**

- Veo indicadores clave (pacientes, clientes, citas de hoy, consultas).
- Veo gráficas por periodo o categoría.
- Los datos coinciden con la base de datos.
- Acceso restringido a roles internos.
- _(v2.0)_ Incluye la tasa de ausentismo por periodo y por veterinario (citas «no asistió» sobre citas agendadas).

**Reglas de negocio:** RN-701 · **Dependencias:** HU-T.1

### HU-6.5 — Exportar reportes a Excel/CSV

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Pendiente (futuro) | 3 pts | Deseable |

> Como administrador o veterinario, quiero exportar los reportes también a Excel/CSV además de PDF para analizarlos fuera del sistema.

**Criterios de aceptación:**

- Cada reporte disponible en PDF también se puede exportar a Excel/CSV.
- El archivo exportado respeta los filtros aplicados.
- Los datos exportados coinciden con la vista.

**Reglas de negocio:** RN-701 · **Dependencias:** HU-6.3

> _Nota: Función deseable propuesta (hoy los reportes solo salen en PDF)._

---
---

## Módulo 7 — Configuración del sistema

> Configuración por clínica: horarios, catálogos, veterinarios y especialidades, marca y tipos de cita, y parámetros.

### HU-7.1 — Configurar los horarios de atención de la clínica

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Implementada | 5 pts | Nuevo |

> Como administrador, quiero configurar los horarios de atención por día (bloques de mañana y tarde) para que la agenda ofrezca solo horas válidas.

**Criterios de aceptación:**

- Defino bloques de mañana y tarde por día de la semana.
- Puedo activar o inactivar días.
- Puedo restaurar los horarios por defecto.
- La agenda respeta la configuración.

**Reglas de negocio:** RN-703, RN-701 · **Dependencias:** HU-T.1

> _Nota: desde v1.9.1 la pantalla muestra una fila por día con sus bloques de mañana y tarde, las horas de cada día y un resumen de la semana (días abiertos, horas totales y el horario de hoy). Los días cerrados se leen «Cerrado» en lugar de rangos vacíos y el estado del guardado automático está siempre visible. El CSS y el JS salieron de la vista a `public/css/horarios.css` y `public/js/horarios.js`, con las mismas validaciones y el mismo selector de horas._

### HU-7.2 — Gestionar los catálogos del sistema

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Parcial (diferida) | 3 pts | Nuevo |

> Como administrador de la clínica, quiero gestionar los catálogos de mi clínica (vacunas base, laboratorios, productos de desparasitación y tipos de cita) para que los formularios ofrezcan opciones actualizadas.

**Criterios de aceptación:**

- Puedo agregar entradas a los catálogos de mi clínica.
- _(v2.0)_ Las especies, razas y colores ya son catálogos globales precargados; si falta una raza, se conserva el texto indicado como «por confirmar». En v2.1 el super-administrador gestiona esos catálogos y la clínica puede proponer la raza faltante.
- Las nuevas entradas aparecen en los formularios correspondientes.
- Las vacunas base se asocian por especie.

**Reglas de negocio:** RN-702 · **Requisitos:** RF-7.3 · **Dependencias:** HU-T.1

> _Nota: sobre-declarada hasta v1.10.1 y diferida en v1.11.0. No existe una pantalla de catálogos: «Configuración» solo tiene los horarios. Especies, razas y colores no se pueden crear. Vacunas, laboratorios y productos solo los crea el veterinario al registrar el acto clínico. Queda por decidir quién gestiona los catálogos: RN-701 dice solo el administrador, esta HU dice administrador o veterinario y la matriz de permisos hoy se lo da solo al veterinario._
> _v2.0: queda decidido quién gestiona los catálogos: los globales (especies, razas, colores), el super-administrador; los propios de cada clínica, su administrador (RN-702)._

### HU-7.3 — Gestionar los veterinarios y sus especialidades

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Alta | Planificada (v2) | 5 pts | v2 |

> Como administrador de la clínica, quiero gestionar los veterinarios de mi clínica y sus especialidades para que la agenda y las sugerencias usen la información correcta.

**Criterios de aceptación:**

- CRUD de veterinarios de la clínica (alta, edición, inactivación), cada uno con sus especialidades.
- Los veterinarios y sus especialidades son propios de cada clínica (aislamiento por `id_clinica`).
- Las especialidades alimentan la sugerencia de veterinario al agendar (HU-4.16) y el emparejamiento del Grafo II.
- Inactivar un veterinario lo retira de la disponibilidad futura sin borrar su historial de atenciones.
- Toda acción queda en auditoría.

**Reglas de negocio:** RN-705, RN-009, RN-701 · **Requisitos:** RF-7.4 · **Dependencias:** HU-T.7

### HU-7.4 — Personalizar la clínica: marca y tipos de cita

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Planificada (v2) | 5 pts | v2 |

> Como administrador de la clínica, quiero configurar los datos de marca de mi clínica y sus tipos de cita para que el sistema refleje mi identidad y mis tiempos de atención.

**Criterios de aceptación:**

- Configuro los datos de marca de la clínica: nombre, logo y datos de contacto, incluido el teléfono de urgencias que se muestra cuando la clínica está cerrada.
- Gestiono los tipos de cita de la clínica, cada uno con su duración, su margen (buffer), insumo del cálculo de disponibilidad (RN-415), y si es **pausable** ante una urgencia (RN-427).
- Los datos de marca aparecen en el portal, los correos y los documentos de la clínica.
- Todo es propio de cada clínica (aislamiento por `id_clinica`).
- Los cambios quedan en auditoría.

**Reglas de negocio:** RN-009, RN-415, RN-421, RN-701, RN-427 · **Requisitos:** RF-7.1, RF-7.3 · **Dependencias:** HU-0.3

### HU-7.5 — Parámetros del sistema configurables

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Planificada (v2) | 5 pts | Deseable |

> Como administrador, quiero configurar parámetros del sistema (duración de cita por defecto, buffer entre citas, ventanas de recordatorio) para adaptar el comportamiento sin tocar el código.

**Criterios de aceptación:**

- Puedo definir la duración de cita por defecto y el buffer entre citas.
- _(v2.0)_ La agenda usa valores por defecto para tolerancia de llegada tarde, plazo de confirmación de reasignación, umbral de aviso y tope de sobrecupos. El teléfono de urgencias se captura en los datos de la clínica (HU-7.4). En v2.1 el administrador puede configurar estos parámetros.
- Los cambios se aplican en la agenda. Los tiempos de los recordatorios se configuran en HU-8.4.

**Reglas de negocio:** RN-403, RN-303, RN-703, RN-421, RN-422, RN-426, RN-428, RN-429 · **Requisitos:** RF-7.5 · **Dependencias:** HU-7.1

> _Nota: Función deseable propuesta; sería el hogar natural del buffer (HU-4.6) y las ventanas de recordatorio (HU-3.6)._

> _Nota: diferida en v1.11.0: la agenda se rehace con la nueva arquitectura._

---
---

## Módulo 8 — Reputación y comunicaciones *(v2.0)*

> Reputación del veterinario (reseñas tras la atención) y comunicaciones configurables de la clínica (plantillas, tiempos y bitácora de envíos).

### HU-8.1 — Calificar al veterinario tras una consulta atendida

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Planificada (v2) | 5 pts | v2 |

> Como propietario, quiero calificar al veterinario que me atendió para dar mi opinión y ayudar a otros a elegir.

**Criterios de aceptación:**

- Solo el propietario de una cita efectivamente **atendida** puede calificar, y una sola vez por cita.
- Al marcarse la cita como atendida se habilita la encuesta y se notifica al propietario.
- Registra estrellas (1 a 5, obligatorio) y un comentario opcional.
- Si ya calificó esa cita, se le indica que no puede repetir.
- Nadie puede calificarse a sí mismo (p. ej. un veterinario que atendió a su propia mascota).
- Solo cuentan para la reputación las reseñas de propietarios con el correo verificado cuya cita tenga una consulta registrada.
- La clínica y sus veterinarios no pueden editar ni eliminar la reseña; solo el super-administrador puede ocultarla por moderación (HU-0.3).
- Guardar la reseña recalcula el promedio del veterinario y queda en auditoría.

**Reglas de negocio:** RN-801, RN-804, RN-805, RN-G18 · **Requisitos:** RF-8.1 · **Dependencias:** HU-4.3

### HU-8.2 — Perfil público del veterinario

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Planificada (v2) | 3 pts | v2 |

> Como propietario, quiero ver el perfil público del veterinario (valoración, especialidades) para decidir con quién agendar.

**Criterios de aceptación:**

- Muestra promedio, número de reseñas y especialidades del veterinario.
- El promedio **no se muestra** hasta alcanzar 5 reseñas válidas, para evitar promedios engañosos.
- Por debajo del mínimo se indica que aún no hay suficientes reseñas.

**Reglas de negocio:** RN-802 · **Requisitos:** RF-8.2 · **Dependencias:** HU-8.1

### HU-8.3 — Plantillas de correos y notificaciones por clínica

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Planificada (v2) | 5 pts | v2 |

> Como administrador, quiero personalizar las plantillas de los correos y notificaciones de mi clínica para que comuniquen con la identidad de mi negocio.

**Criterios de aceptación:**

- Edito el asunto, el contenido y el estilo/formato de las plantillas de mi clínica.
- El siguiente envío usa el nuevo formato.
- Las plantillas son propias de cada clínica (aislamiento por `id_clinica`).

**Reglas de negocio:** RN-806 · **Requisitos:** RF-8.5 · **Dependencias:** —

### HU-8.4 — Tiempos de envío de recordatorios configurables

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Planificada (v2) | 3 pts | v2 |

> Como administrador, quiero configurar los días de anticipación y la hora de envío de los recordatorios para ajustarlos a la operación de mi clínica.

**Criterios de aceptación:**

- Configuro los días de anticipación y la hora de envío de recordatorios y alertas.
- Los recordatorios se envían según los tiempos configurados.

**Reglas de negocio:** RN-807, RN-303 · **Requisitos:** RF-8.6 · **Dependencias:** HU-3.2

### HU-8.5 — Bitácora de correos y notificaciones

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Media | Planificada (v2) | 3 pts | v2 |

> Como administrador, quiero una bitácora de los correos y notificaciones enviados con su estado para dar seguimiento a lo que llega y lo que falla.

**Criterios de aceptación:**

- Muestra el historial de envíos con fecha, destinatario y estado (enviado/fallido).
- Permite identificar los envíos fallidos para su gestión.

**Reglas de negocio:** RN-808 · **Requisitos:** RF-8.7 · **Dependencias:** —

---
---

## Módulo 9 — Público e institucional

> Páginas públicas y políticas legales.

### HU-9.1 — Página pública y políticas legales

| Prioridad | Estado | Estimación | Origen |
|---|---|---|---|
| Baja | Implementada | 2 pts | Nuevo |

> Como visitante o usuario, quiero ver la página pública del servicio y las políticas de privacidad, términos y cookies para conocer y confiar en el sistema.

**Criterios de aceptación:**

- La landing presenta el servicio.
- Existen páginas de privacidad, términos y cookies accesibles.
- La política de datos refleja la Ley 1581 de 2012.

**Reglas de negocio:** RN-G07 · **Dependencias:** —


---
---

## Plan de entregas de la v2

Todo lo especificado en este documento es alcance de la v2 (salvo lo marcado como futuro). Para que lo que se presenta esté completo y funcionando, la construcción se ordena en dos entregas: la **v2.0** reúne lo imprescindible para demostrar el valor del sistema (multi-clínica, los dos grafos, el portal y el carnet QR) y la **v2.1** completa el resto. Una historia en la v2.1 no queda fuera del alcance: queda especificada y planificada para la entrega siguiente.

Dentro de cada entrega, la tabla sigue las dependencias principales: primero el aislamiento y la identidad, después las funciones que los usan. Las historias v1.x que ya funcionan se migran por completo cuando una historia v2 las toca.

### Entrega v2.0 — imprescindible para la presentación (28 historias, 159 puntos)

| Historia | Título | Puntos |
|---|---|---|
| HU-T.15 | Aislamiento por clínica y rol super-administrador | 8 |
| HU-T.16 | Expiración de la sesión por inactividad | 3 |
| HU-T.17 | Una persona con varios roles: elegir el contexto al iniciar sesión | 5 |
| HU-T.19 | Aceptar la política de tratamiento de datos | 3 |
| HU-5.8 | Registrar al propietario como identidad global | 8 |
| HU-0.1 | Registrar una clínica (self-service) | 8 |
| HU-0.2 | Verificar el correo y activar la clínica | 3 |
| HU-0.3 | Panel del super-administrador | 8 |
| HU-0.4 | Control de límites del plan (freemium) | 5 |
| HU-T.18 | Registrarme e iniciar sesión con Google (propietario) | 5 |
| HU-5.9 | Iniciar sesión en el portal multi-clínica | 5 |
| HU-1.5 | Vincular una mascota existente a la clínica | 3 |
| HU-5.12 | Autorizar a una clínica a ver la historia de otras clínicas | 3 |
| HU-5.13 | Vincularme o desvincularme de una clínica | 3 |
| HU-2.10 | Ver la historia compartida de la mascota (multi-clínica) | 5 |
| HU-2.8 | Sugerencia de diagnósticos probables por síntomas | 8 |
| HU-2.9 | Alertas de toxicidad e interacción al prescribir | 8 |
| HU-2.11 | Calcular el nivel de triage a partir de los síntomas | 8 |
| HU-7.4 | Personalizar la clínica: marca y tipos de cita | 5 |
| HU-4.11 | Configurar el horario recurrente del veterinario | 5 |
| HU-4.12 | Registrar ausencia del veterinario y gestionar cobertura | 8 |
| HU-4.13 | Agendar cita con triage de 4 niveles | 8 |
| HU-4.14 | Reajuste de agenda en vivo (efecto cascada) | 8 |
| HU-4.15 | Atender una urgencia (triage rojo o llegada directa) | 8 |
| HU-4.18 | Ajustar el nivel de triage (veterinario) | 3 |
| HU-4.19 | Manejar la llegada tarde del propietario | 5 |
| HU-5.10 | Carnet digital de la mascota con QR de emergencia | 5 |
| HU-5.11 | Acceder al carnet por QR en modo solo lectura | 5 |

### Entrega v2.1 — completa el alcance (16 historias, 62 puntos)

| Historia | Título | Puntos |
|---|---|---|
| HU-0.5 | Suscripción y congelación por mora | 5 |
| HU-3.5 | Panel de vacunaciones pendientes | 3 |
| HU-4.7 | Estado "no asistió" y ausentismo | 3 |
| HU-4.10 | Confirmar asistencia a la cita desde el recordatorio | 3 |
| HU-4.16 | Sugerir veterinario al agendar | 5 |
| HU-4.17 | Recalcular el triage cuando cambian los síntomas | 3 |
| HU-5.7 | Centro de notificaciones del propietario | 3 |
| HU-5.14 | Eliminar mi cuenta (derecho de supresión) | 5 |
| HU-7.2 | Gestionar los catálogos del sistema | 3 |
| HU-7.3 | Gestionar los veterinarios y sus especialidades | 5 |
| HU-7.5 | Parámetros del sistema configurables | 5 |
| HU-8.1 | Calificar al veterinario tras una consulta atendida | 5 |
| HU-8.2 | Perfil público del veterinario | 3 |
| HU-8.3 | Plantillas de correos y notificaciones por clínica | 5 |
| HU-8.4 | Tiempos de envío de recordatorios configurables | 3 |
| HU-8.5 | Bitácora de correos y notificaciones | 3 |

Mientras llega la v2.1, la solicitud de eliminación de cuenta (HU-5.14) se atiende de forma manual, y los parámetros de la agenda (HU-7.5) usan sus valores por defecto.

### Historias diferidas que absorbe la v2

| Historia | Título | Se completa en |
|---|---|---|
| HU-4.5 | Registrar hora real de atención y gestionar retrasos | HU-4.14 |
| HU-4.6 | Buffer configurable entre citas | HU-4.13 y HU-7.4 |
| HU-4.8 | Bloqueos de agenda del veterinario y días no laborables | HU-4.11 y HU-4.12 |
| HU-4.9 | Endurecer la validación de disponibilidad | HU-4.13 |
| HU-5.6 | Cancelar o reprogramar mi cita desde el portal | HU-4.13 y HU-4.14 |

## Apéndice — Cobertura y trazabilidad (RN → HU)

Auditoría automática de la Fase 3: de **119 reglas de negocio**, **119** están cubiertas por al menos una historia de usuario.

**Reglas sin historia asociada:** — ninguna: cobertura total —

> Las historias de la v2.0 citan su requisito funcional (RF) del [ERS](ERS.md); las de la v1.x citan sus reglas de negocio, y su relación con los RF está en los casos de uso del ERS. El detalle de cada historia en requisitos verificables está en [Requisitos Específicos](RequisitosEspecificos.md).

| Regla | Historias |
|---|---|
| RN-G01 | HU-T.1, HU-T.4, HU-T.10, HU-T.17, HU-6.1, HU-6.2 |
| RN-G02 | HU-T.6, HU-T.10, HU-2.7, HU-5.1, HU-5.4, HU-5.5, HU-5.6, HU-5.7 |
| RN-G03 | HU-T.1, HU-T.2, HU-T.12, HU-T.13 |
| RN-G04 | HU-T.3, HU-T.12 |
| RN-G05 | HU-T.4, HU-T.5, HU-T.8, HU-T.14, HU-T.16 |
| RN-G06 | HU-0.1, HU-T.5, HU-T.7, HU-T.17, HU-1.2, HU-5.2, HU-5.8 |
| RN-G07 | HU-T.5, HU-T.19, HU-5.12, HU-5.14, HU-9.1 |
| RN-G08 | HU-T.7, HU-T.11, HU-T.14 |
| RN-G09 | HU-T.1 |
| RN-G10 | HU-T.12 |
| RN-G11 | HU-0.2, HU-T.12 |
| RN-G12 | HU-T.12, HU-T.18 |
| RN-G13 | HU-0.3, HU-T.15, HU-T.17, HU-5.9 |
| RN-G14 | HU-0.3, HU-T.15 |
| RN-G15 | HU-T.13 |
| RN-G16 | HU-T.19, HU-5.14 |
| RN-G17 | HU-T.16 |
| RN-G18 | HU-T.17, HU-8.1 |
| RN-G19 | HU-0.1, HU-T.18, HU-T.19 |
| RN-G20 | HU-0.1, HU-T.18 |
| RN-G21 | HU-T.18 |
| RN-G22 | HU-T.2 |
| RN-G23 | HU-T.5 |
| RN-G24 | HU-T.5 |
| RN-001 | HU-0.1, HU-T.15 |
| RN-002 | HU-0.1, HU-0.2 |
| RN-003 | HU-0.2, HU-0.4, HU-1.5 |
| RN-004 | HU-0.3, HU-T.15 |
| RN-005 | HU-0.2, HU-0.4 |
| RN-006 | HU-0.4 |
| RN-007 | HU-0.5 |
| RN-008 | HU-0.5 |
| RN-009 | HU-7.3, HU-7.4 |
| RN-010 | HU-0.1 |
| RN-011 | HU-0.1 |
| RN-012 | HU-0.3 |
| RN-013 | HU-0.5 |
| RN-101 | HU-1.1, HU-1.2, HU-5.2 |
| RN-102 | HU-1.1, HU-1.5, HU-2.2 |
| RN-103 | HU-1.2 |
| RN-104 | HU-1.4, HU-5.4 |
| RN-105 | HU-1.3, HU-1.4 |
| RN-106 | HU-1.1 |
| RN-107 | HU-1.1 |
| RN-108 | HU-1.4, HU-5.4 |
| RN-109 | HU-T.18, HU-1.2, HU-5.3, HU-5.8, HU-5.9, HU-5.13 |
| RN-110 | HU-1.1, HU-1.4, HU-1.5, HU-5.3, HU-5.10, HU-5.13 |
| RN-111 | HU-1.1, HU-1.5 |
| RN-112 | HU-2.10 |
| RN-113 | HU-2.1, HU-2.5, HU-2.9, HU-2.10, HU-5.12 |
| RN-114 | HU-5.5, HU-5.9 |
| RN-115 | HU-5.12, HU-5.13 |
| RN-201 | HU-2.1, HU-2.7 |
| RN-202 | HU-2.1, HU-2.8 |
| RN-203 | HU-2.1, HU-2.2 |
| RN-204 | HU-2.3, HU-2.6 |
| RN-205 | HU-2.3, HU-2.4 |
| RN-206 | HU-2.5, HU-2.6, HU-5.5 |
| RN-207 | HU-2.7 |
| RN-208 | HU-2.2, HU-2.7 |
| RN-209 | HU-2.8, HU-2.9, HU-2.11, HU-4.18 |
| RN-210 | HU-2.8 |
| RN-211 | HU-2.9 |
| RN-212 | HU-2.8, HU-2.9 |
| RN-301 | HU-3.1, HU-3.5 |
| RN-302 | HU-3.4 |
| RN-303 | HU-3.2, HU-3.6, HU-7.5, HU-8.4 |
| RN-304 | HU-3.2, HU-3.6 |
| RN-305 | HU-3.2, HU-3.6 |
| RN-306 | HU-3.1 |
| RN-307 | HU-3.3 |
| RN-401 | HU-4.1, HU-4.5, HU-4.6, HU-4.9, HU-4.13, HU-5.3, HU-5.6 |
| RN-402 | HU-4.1, HU-4.8, HU-4.9, HU-4.13, HU-5.3 |
| RN-403 | HU-4.1, HU-4.6, HU-7.5 |
| RN-404 | HU-4.1, HU-4.4, HU-4.10 |
| RN-405 | HU-4.2, HU-4.7, HU-4.10, HU-5.6 |
| RN-406 | HU-4.3 |
| RN-407 | HU-4.3, HU-6.1 |
| RN-408 | HU-4.7, HU-4.19, HU-6.2 |
| RN-409 | HU-T.6, HU-4.3, HU-6.1, HU-6.2 |
| RN-410 | HU-4.3 |
| RN-411 | HU-2.11, HU-4.13, HU-4.15, HU-4.17 |
| RN-412 | HU-4.14, HU-4.19 |
| RN-413 | HU-4.14 |
| RN-414 | HU-4.12 |
| RN-415 | HU-4.11, HU-4.12, HU-4.13, HU-4.14, HU-4.15, HU-7.4 |
| RN-416 | HU-2.11 |
| RN-417 | HU-2.11 |
| RN-418 | HU-2.11 |
| RN-419 | HU-4.18 |
| RN-420 | HU-0.4, HU-4.13, HU-4.15 |
| RN-421 | HU-4.15, HU-7.4, HU-7.5 |
| RN-422 | HU-4.13, HU-7.5 |
| RN-423 | HU-4.17 |
| RN-424 | HU-2.11 |
| RN-425 | HU-2.11 |
| RN-426 | HU-4.19, HU-7.5 |
| RN-427 | HU-4.15, HU-7.4 |
| RN-428 | HU-4.14, HU-7.5 |
| RN-429 | HU-4.14, HU-7.5 |
| RN-501 | HU-5.2, HU-5.3 |
| RN-502 | HU-5.10, HU-5.11 |
| RN-503 | HU-5.10, HU-5.11 |
| RN-504 | HU-5.11 |
| RN-505 | HU-5.11 |
| RN-701 | HU-T.7, HU-T.11, HU-T.14, HU-6.3, HU-6.4, HU-6.5, HU-7.1, HU-7.3, HU-7.4 |
| RN-702 | HU-7.2 |
| RN-703 | HU-4.8, HU-4.11, HU-7.1, HU-7.5 |
| RN-704 | HU-T.9 |
| RN-705 | HU-T.5, HU-T.7, HU-7.3 |
| RN-706 | HU-4.11 |
| RN-801 | HU-8.1 |
| RN-802 | HU-8.2 |
| RN-803 | HU-4.16 |
| RN-804 | HU-0.3, HU-8.1 |
| RN-805 | HU-8.1 |
| RN-806 | HU-8.3 |
| RN-807 | HU-8.4 |
| RN-808 | HU-8.5 |

## Apéndice — Equivalencia de identificadores (nuevo ↔ anterior)

Puente de trazabilidad: los comentarios del código y los documentos `AuditoriaModulos.md` e `HistorialVersiones.md` todavía citan el identificador anterior.

| Nuevo | Anterior | Historia |
|---|---|---|
| HU-0.1 | HU-58 | Registrar una clínica (self-service) |
| HU-0.2 | HU-59 | Verificar el correo y activar la clínica |
| HU-0.3 | HU-60 | Panel del super-administrador |
| HU-0.4 | HU-61 | Control de límites del plan (freemium) |
| HU-0.5 | HU-62 | Suscripción y congelación por mora |
| HU-T.1 | HU-17 | Iniciar sesión y autenticación |
| HU-T.2 | HU-39 | Cambiar mi contraseña |
| HU-T.3 | HU-40 | Recuperar contraseña olvidada |
| HU-T.4 | HU-41 | Cerrar sesión |
| HU-T.5 | HU-42 | Ver y actualizar mi perfil y datos de contacto |
| HU-T.6 | HU-45 | Gestionar mis notificaciones internas |
| HU-T.7 | HU-22 | Gestión de usuarios del sistema |
| HU-T.8 | HU-24 | Logs de auditoría y seguridad |
| HU-T.9 | HU-23 | Backup automático de la base de datos |
| HU-T.10 | HU-32 | Autorización central por rol (RBAC real) |
| HU-T.11 | HU-33 | Corregir escalada de privilegios y proteger al último administrador |
| HU-T.12 | HU-36 | Política de contraseñas, verificación de correo y OAuth seguro |
| HU-T.13 | HU-38 | Endurecer rate limiting y reducir enumeración/fuga de información |
| HU-T.14 | HU-54 | Restablecer la contraseña de un usuario (administrador) |
| HU-T.15 | HU-80 | Aislamiento por clínica y rol super-administrador |
| HU-T.16 | HU-86 | Expiración de la sesión por inactividad |
| HU-T.17 | HU-93 | Una persona con varios roles: elegir el contexto al iniciar sesión |
| HU-T.18 | HU-94 | Registrarme e iniciar sesión con Google (propietario) |
| HU-T.19 | HU-95 | Aceptar la política de tratamiento de datos |
| HU-1.1 | HU-01 | Registrar mascota |
| HU-1.2 | HU-02 | Registrar propietario |
| HU-1.3 | HU-03 | Buscar paciente |
| HU-1.4 | HU-04 | Editar y desactivar mascota |
| HU-1.5 | HU-83 | Vincular una mascota existente a la clínica |
| HU-2.1 | HU-05 | Registrar consulta clínica |
| HU-2.2 | HU-56 | Atención clínica sin cita (urgencias) |
| HU-2.3 | HU-06 | Adjuntar archivos clínicos |
| HU-2.4 | HU-07 | Registrar tratamiento |
| HU-2.5 | HU-08 | Ver historial clínico completo |
| HU-2.6 | HU-34 | Atomicidad y feedback de adjuntos en el registro de consulta |
| HU-2.7 | HU-35 | Validación y control de acceso en registros clínicos y de mascota |
| HU-2.8 | HU-68 | Sugerencia de diagnósticos probables por síntomas |
| HU-2.9 | HU-69 | Alertas de toxicidad e interacción al prescribir |
| HU-2.10 | HU-84 | Ver la historia compartida de la mascota (multi-clínica) |
| HU-2.11 | HU-89 | Calcular el nivel de triage a partir de los síntomas |
| HU-3.1 | HU-09 | Registrar vacunación |
| HU-3.2 | HU-10 | Enviar recordatorio por correo |
| HU-3.3 | HU-11 | Enviar recordatorio por WhatsApp |
| HU-3.4 | HU-12 | Registrar desparasitación |
| HU-3.5 | HU-20 | Panel de vacunaciones pendientes |
| HU-3.6 | HU-37 | Robustez de los recordatorios automáticos |
| HU-4.1 | HU-13 | Agendar cita |
| HU-4.2 | HU-14 | Cancelar o reprogramar cita |
| HU-4.3 | HU-19 | Marcar cita como completada |
| HU-4.4 | HU-21 | Confirmación automática de cita por correo |
| HU-4.5 | HU-27 | Registrar hora real de atención y gestionar retrasos |
| HU-4.6 | HU-28 | Buffer configurable entre citas |
| HU-4.7 | HU-29 | Estado "no asistió" y ausentismo |
| HU-4.8 | HU-30 | Bloqueos de agenda del veterinario y días no laborables |
| HU-4.9 | HU-31 | Endurecer la validación de disponibilidad |
| HU-4.10 | HU-51 | Confirmar asistencia a la cita desde el recordatorio |
| HU-4.11 | HU-63 | Configurar el horario recurrente del veterinario |
| HU-4.12 | HU-64 | Registrar ausencia del veterinario y gestionar cobertura |
| HU-4.13 | HU-65 | Agendar cita con triage de 4 niveles |
| HU-4.14 | HU-66 | Reajuste de agenda en vivo (efecto cascada) |
| HU-4.15 | HU-67 | Atender una urgencia (triage rojo o llegada directa) |
| HU-4.16 | HU-76 | Sugerir veterinario al agendar |
| HU-4.17 | HU-90 | Recalcular el triage cuando cambian los síntomas |
| HU-4.18 | HU-91 | Ajustar el nivel de triage (veterinario) |
| HU-4.19 | HU-92 | Manejar la llegada tarde del propietario |
| HU-5.1 | HU-15 | Portal del propietario |
| HU-5.2 | HU-25 | Auto-registro de propietario |
| HU-5.3 | HU-26 | Agendar cita desde el portal |
| HU-5.4 | HU-46 | Editar mi mascota desde el portal |
| HU-5.5 | HU-47 | Imprimir/exportar el historial de mi mascota |
| HU-5.6 | HU-50 | Cancelar o reprogramar mi cita desde el portal |
| HU-5.7 | HU-52 | Centro de notificaciones del propietario |
| HU-5.8 | HU-70 | Registrar al propietario como identidad global |
| HU-5.9 | HU-71 | Iniciar sesión en el portal multi-clínica |
| HU-5.10 | HU-72 | Carnet digital de la mascota con QR de emergencia |
| HU-5.11 | HU-73 | Acceder al carnet por QR en modo solo lectura |
| HU-5.12 | HU-85 | Autorizar a una clínica a ver la historia de otras clínicas |
| HU-5.13 | HU-87 | Vincularme o desvincularme de una clínica |
| HU-5.14 | HU-88 | Eliminar mi cuenta (derecho de supresión) |
| HU-6.1 | HU-18 | Dashboard principal y panel de pendientes |
| HU-6.2 | HU-57 | Panel de operación del administrador |
| HU-6.3 | HU-16 | Generar reportes en PDF |
| HU-6.4 | HU-48 | Ver estadísticas y gráficas del sistema |
| HU-6.5 | HU-55 | Exportar reportes a Excel/CSV |
| HU-7.1 | HU-43 | Configurar los horarios de atención de la clínica |
| HU-7.2 | HU-44 | Gestionar los catálogos del sistema |
| HU-7.3 | HU-81 | Gestionar los veterinarios y sus especialidades |
| HU-7.4 | HU-82 | Personalizar la clínica: marca y tipos de cita |
| HU-7.5 | HU-53 | Parámetros del sistema configurables |
| HU-8.1 | HU-74 | Calificar al veterinario tras una consulta atendida |
| HU-8.2 | HU-75 | Perfil público del veterinario |
| HU-8.3 | HU-77 | Plantillas de correos y notificaciones por clínica |
| HU-8.4 | HU-78 | Tiempos de envío de recordatorios configurables |
| HU-8.5 | HU-79 | Bitácora de correos y notificaciones |
| HU-9.1 | HU-49 | Página pública y políticas legales |
