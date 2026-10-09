# Reglas de Negocio — Proyecto Zooki

> **Revisión 2.1** · Catálogo de reglas por módulos · Arquitectura SaaS multi-inquilino (v2.0) · SENA ADSO — Ficha 3142784

Este documento reúne las reglas de negocio de Zooki: las políticas, restricciones y cálculos que rigen el comportamiento del dominio (gestión clínica veterinaria), con independencia de la tecnología con que se implementen. Es la base de la que se derivan las [Historias de Usuario](HistoriasUsuario.md) y los [Requisitos específicos](RequisitosEspecificos.md). Cadena de trazabilidad: **Regla de Negocio (RN) → Historia de Usuario (HU) → Requisito específico (RE)**.

> _La Revisión 2.0 incorpora las reglas del cambio de arquitectura a **SaaS multi-inquilino**: plataforma y clínicas, planes y suscripción, soporte a la decisión clínica (Grafo I), agendamiento inteligente (Grafo II), reputación de veterinarios y comunicaciones. Hace global a la mascota y al propietario, con una identidad por persona y varios roles; unifica la numeración de módulos con el resto de la documentación (ver la tabla de equivalencias); define el cálculo del triage y el comportamiento de la agenda ante urgencias, sobrecupos, llegadas tarde y reasignaciones; fija los límites y el precio de los planes; y agrega la autorización de tratamiento de datos, el registro con Google y los cambios de correo y documento._

> _Nota: las reglas de arquitectura y estilo de código (SOLID, DRY, modularidad de CSS/JS, SweetAlert2) se gestionan aparte en el manifiesto técnico del repositorio (`ZOOKI_REGLAS.md`) y no forman parte de este catálogo._

## Convenciones

**Tipos de regla:** `Restricción` (condición que el sistema hace cumplir), `Cálculo` (deriva un valor), `Proceso` (comportamiento automático), `Estructura` (hecho o relación del dominio).

**Estados:** `Aplicada` (se cumple en la versión actual v1.11.0), `Parcial` (se cumple con una excepción que se indica), `Planificada (v2)` (regla nueva del cambio de arquitectura, por implementar en la v2.0), `Derogada (v2)` (regla que deja de aplicarse en la v2.0), `Futuro` (evolución prevista sin fecha), `Por confirmar` (verificar en el código).

> _Los módulos siguen la misma numeración en todos los documentos ([Historias de Usuario](HistoriasUsuario.md), [Requisitos Específicos](RequisitosEspecificos.md), [ERS](ERS.md) y [Modelos](Modelos.md)). El Módulo 6 (Dashboard y reportes) y el Módulo 9 (Público e institucional) no tienen reglas propias: se rigen por las transversales (p. ej. RN-G07). Desde la Revisión 2.0, el número de cada regla indica su módulo; la equivalencia con los identificadores anteriores está al final del documento._

## Módulo T — Acceso, seguridad y administración (reglas transversales)

| ID | Regla de negocio | Tipo | Estado |
|---|---|---|---|
| RN-G01 | El acceso al sistema requiere autenticación. Cada persona tiene **una sola identidad** y puede tener **varios roles**: propietario (global) y, en cada clínica donde trabaje, administrador o veterinario. Al iniciar sesión elige el contexto (su portal de propietario, o una clínica con su rol en ella), y sus permisos son los del contexto activo (RBAC). El super-administrador es un rol de plataforma que no se combina con roles de clínica. | Restricción | Aplicada (un rol por usuario) · Varios roles por persona y super-administrador: Planificada (v2) |
| RN-G02 | Un propietario solo puede consultar información de sus propias mascotas; todo intento de acceder a datos de terceros se rechaza (error 403). | Restricción | Aplicada |
| RN-G03 | Las contraseñas se almacenan cifradas (bcrypt + salt), nunca en texto plano. Ante credenciales inválidas se muestra un mensaje genérico. | Restricción | Aplicada |
| RN-G04 | El enlace de recuperación de contraseña es de un solo uso y expira a la hora de emitido. | Restricción | Aplicada |
| RN-G05 | Toda operación crítica (inicio/cierre de sesión, creación, edición y eliminación) se registra en auditoría con usuario, fecha e IP. Los registros de auditoría son de solo lectura. | Proceso | Aplicada |
| RN-G06 | El número de documento y el correo electrónico identifican a una persona y son únicos en toda la plataforma. Una persona no se duplica por tener varios roles ni por trabajar en varias clínicas: los roles del personal se asignan a su identidad existente previa aceptación del titular de la invitación de la clínica. La invitación vence en 72 horas; prevalece la cuenta del correo sobre la del documento, o el correo registrado del titular del documento si el correo escrito no tiene cuenta. La respuesta y la lista de pendientes no revelan si existía una cuenta: muestran los datos escritos por el administrador. | Restricción | Aplicada (único en la instalación) · Identidad única con varios roles: Planificada (v2) |
| RN-G07 | Los datos personales se tratan conforme a la Ley 1581 de 2012 (protección de datos / habeas data). | Restricción | Aplicada |
| RN-G08 | Los usuarios no se eliminan físicamente: se inactivan (soft-delete). Inactivar a una persona en una clínica retira su rol en esa clínica sin afectar sus otros roles. No se puede inactivar al único administrador de una clínica. | Restricción | Aplicada |
| RN-G09 | El sistema admite autenticación federada con Google (OAuth 2.0) además de las credenciales locales. | Estructura | Aplicada |
| RN-G10 | Toda contraseña del sistema cumple la misma política: mínimo 8 caracteres con mayúscula, minúscula y número, y además se rechaza si figura en la lista de contraseñas de uso masivo, si es una secuencia o repetición trivial, o si contiene el documento, el nombre o el correo del titular. Rige en el registro, el restablecimiento, el cambio de contraseña y el alta de usuarios por el administrador. | Restricción | Aplicada |
| RN-G11 | El auto-registro no habilita la cuenta: el correo debe confirmarse mediante un enlace de un solo uso que vence a las 24 horas. Hasta entonces la cuenta no permite iniciar sesión. | Proceso | Aplicada |
| RN-G12 | El inicio de sesión con Google solo acepta tokens emitidos para el client_id de Zooki (`aud`) por un emisor legítimo de Google (`iss`), vigentes y con el correo verificado por Google. | Restricción | Aplicada |
| RN-G13 | Los registros propios de una clínica llevan `id_clinica`; un usuario solo accede a los de su contexto activo y las peticiones a otra clínica se rechazan (403). Son globales la identidad del usuario, la mascota de su propietario (RN-110), los catálogos taxonómicos y el Grafo I. Los registros clínicos pertenecen a la clínica que los creó y solo se muestran a otra según RN-113; el motor puede evaluar internamente medicación protegida de otra clínica exclusivamente para la alerta genérica de seguridad de RN-211, sin devolver el tratamiento. El super-administrador opera con los límites de RN-004. | Restricción | Planificada (v2) |
| RN-G14 | Existe el rol Super-administrador, por encima de los roles de clínica, con alcance sobre toda la plataforma. No pertenece a ninguna clínica. | Estructura | Planificada (v2) |
| RN-G15 | Tras 20 intentos fallidos de inicio de sesión en 15 minutos **desde una misma IP**, el acceso desde esa IP se bloquea temporalmente (el margen es amplio porque una clínica sale a internet por una sola IP). Sobre **una misma cuenta**, tras 5 intentos fallidos en 15 minutos no se bloquea la cuenta: el siguiente intento exige superar una verificación anti-bot (CAPTCHA), para que nadie pueda dejar sin acceso a otro usuario fallando a propósito con su documento o correo. Los mensajes nunca revelan si la cuenta existe. | Restricción | Aplicada (bloqueo por IP) · CAPTCHA por cuenta: Planificada (v2) |
| RN-G16 | El titular de los datos puede pedir la eliminación de su cuenta (derecho de supresión, Ley 1581 de 2012). Sus datos personales se anonimizan de forma irreversible y su acceso se cierra, pero la historia clínica de sus mascotas se conserva anonimizada porque la clínica tiene la obligación de custodiarla (Ley 576 de 2000). Los carnets QR de sus mascotas se desactivan. | Proceso | Planificada (v2) |
| RN-G17 | La sesión expira tras 30 minutos de inactividad y siempre al cerrar el navegador; para continuar hay que iniciar sesión de nuevo. | Restricción | Planificada (v2) |
| RN-G18 | Las acciones se ejecutan siempre con el rol del contexto activo: lo que una persona hace como propietario no le da permisos de personal, y viceversa. Una persona no puede calificarse a sí misma ni atender como veterinario una cita que agendó como propietario sin que quede registrado en auditoría. | Restricción | Planificada (v2) |
| RN-G19 | **Autorización de tratamiento de datos.** Todo registro, con formulario o con Google, exige aceptar de forma expresa la política de tratamiento de datos personales; sin aceptación no se crea la cuenta. Excepción: el personal nuevo puede quedar pendiente e inerte, sin contraseña ni consentimiento, durante 72 horas; solo el titular lo activa al aceptar la política y crear su contraseña. Si vence, se elimina solo si no tiene otros vínculos. El super-administrador está exento de aceptación por ser una cuenta operativa de plataforma. Se guarda la prueba: quién, qué versión de la política, por qué medio, cuándo y desde qué IP. Si la política cambia, se pide aceptar la nueva en el siguiente inicio de sesión. Revocar la autorización equivale a pedir la supresión (RN-G16). | Restricción | Planificada (v2) |
| RN-G20 | **Registro con Google.** Solo los propietarios pueden registrarse con Google, desde el enlace o QR de una clínica o eligiéndola de la lista; el registro de clínicas y el alta de personal no admiten Google (el personal sí puede iniciar sesión con Google si su correo coincide). Google aporta el nombre, el correo ya verificado y la foto, por lo que no se pide verificar el correo. Antes de crear una cuenta nueva se exige aceptar la política de datos (RN-G19); sin aceptación no se guarda la identidad ni se liga a la clínica. La cuenta aceptada queda con el perfil incompleto hasta registrar el tipo y número de documento y el teléfono; mientras tanto no puede registrar mascotas ni agendar. | Proceso | Planificada (v2) |
| RN-G21 | Si alguien inicia sesión con Google con un correo que ya tiene cuenta, Google se vincula a esa misma cuenta, porque demuestra que el correo es suyo. **Solo si la cuenta estaba pendiente de verificar el correo**, queda verificada y se anula la contraseña que tenía, porque pudo crearla otra persona que usó ese correo; en ese caso el perfil vuelve a pedir el documento y el teléfono, y para entrar con contraseña debe crear una nueva (RN-G22). Una cuenta ya verificada conserva su contraseña. | Proceso | Planificada (v2) |
| RN-G22 | Una cuenta creada con Google no tiene contraseña. Para crear una debe volver a confirmar su identidad con Google; a partir de ahí puede entrar con ambos medios. | Restricción | Planificada (v2) |
| RN-G23 | Cambiar el correo exige confirmar la identidad (contraseña o Google) y verificar el correo nuevo; hasta entonces sigue vigente el anterior, que recibe un aviso del cambio. Si la cuenta estaba vinculada a Google con el correo anterior, esa vinculación se retira; por eso una cuenta sin contraseña debe crear una (RN-G22) antes de cambiar el correo, para no quedar sin forma de entrar. | Restricción | Planificada (v2) |
| RN-G24 | El titular puede corregir su tipo y número de documento desde su perfil, confirmando su identidad (contraseña o Google). Se valida el formato y la unicidad; el valor anterior queda en auditoría y se le avisa por correo. Si el documento nuevo ya pertenece a otra cuenta, el cambio no se aplica y el caso pasa a soporte (super-administrador) para revisar una posible cuenta duplicada. | Restricción | Planificada (v2) |

## Módulo 0 — Plataforma: clínicas, planes y suscripción _(nuevo, v2)_

| ID | Regla de negocio | Tipo | Estado |
|---|---|---|---|
| RN-001 | Zooki es multi-inquilino: una única instalación atiende a varias clínicas, cada una con sus datos aislados de las demás. | Estructura | Planificada (v2) |
| RN-002 | Una clínica puede registrarse de forma autónoma (self-service); el registro crea la clínica y su usuario administrador inicial, y requiere confirmar el correo antes de operar. Este registro no admite Google (RN-G20). | Proceso | Planificada (v2) |
| RN-003 | Cada clínica tiene un plan que define sus límites de uso. El **plan gratuito** admite 5 mascotas vinculadas, 30 citas por mes y 2 cuentas de personal (un administrador y un veterinario); el **plan profesional** no tiene límites de volumen. El límite de mascotas cuenta las mascotas vinculadas a la clínica (RN-110). | Estructura | Planificada (v2) |
| RN-004 | El super-administrador gestiona las clínicas, los planes y las suscripciones; no accede a los datos clínicos internos de una clínica salvo para soporte. | Restricción | Planificada (v2) |
| RN-005 | El plan gratuito incluye las capacidades del sistema (incluidos los grafos) en funcionamiento, pero limitadas por volumen; el plan profesional elimina esos límites. Las capas avanzadas (aprendizaje del grafo con los datos de la propia clínica) se prevén para una versión posterior como beneficio del plan profesional (RF-F.8). | Estructura | Planificada (v2) |
| RN-006 | Cuando una clínica alcanza un límite de su plan, el sistema impide la acción e informa el motivo, invitando a mejorar el plan. Las urgencias 🔴 son la excepción: nunca se bloquean por límite (RN-420). | Restricción | Planificada (v2) |
| RN-007 | Si la suscripción de una clínica no está al día, se congelan los beneficios del plan de pago hasta regularizarla. La atención de urgencias 🔴 no se congela (RN-420). | Proceso | Planificada (v2) |
| RN-008 | El plan profesional cuesta $100.000 COP al mes o $960.000 COP al año por pago anticipado (equivale a $80.000 al mes, 20 % de descuento frente a doce mensualidades). Es el precio inicial; el super-administrador puede cambiarlo en el catálogo de planes, y el cambio no afecta el periodo ya pagado. El cobro real mediante pasarela se prevé para una fase posterior. | Cálculo | Planificada (v2) |
| RN-009 | Cada clínica configura sus propios horarios, catálogos, tipos de cita, veterinarios y datos de marca (nombre, logo). | Estructura | Planificada (v2) |
| RN-010 | El NIT de la clínica es único en la plataforma y debe superar la validación del dígito de verificación de la DIAN. Un NIT ya registrado no puede dar de alta otra clínica. Si una clínica encuentra su NIT registrado por otra, puede reportarlo desde el formulario; el caso pasa al super-administrador, que verifica la titularidad y puede suspender la clínica que lo usó (RN-012). | Restricción | Planificada (v2) |
| RN-011 | El registro público de clínicas exige superar una verificación anti-bot (CAPTCHA), rechaza correos de dominios desechables y admite como máximo 3 registros por día desde una misma IP. | Restricción | Planificada (v2) |
| RN-012 | El super-administrador puede suspender o dar de baja una clínica sospechosa de duplicidad o abuso del plan gratuito, dejando registro del motivo en auditoría. | Proceso | Planificada (v2) |
| RN-013 | Al bajar al plan gratuito o caer en mora no se oculta ni se borra ningún dato: la clínica sigue atendiendo a las mascotas que ya tiene y consultando su historia. Solo se bloquea crear o vincular mascotas, crear citas o dar de alta personal por encima de los límites del plan gratuito. | Restricción | Planificada (v2) |

## Módulo 1 — Mascotas y propietarios

| ID | Regla de negocio | Tipo | Estado |
|---|---|---|---|
| RN-101 | Toda mascota debe estar vinculada a un propietario; no puede existir una mascota sin propietario asignado. | Restricción | Aplicada |
| RN-102 | Cada mascota se identifica con un número de historia clínica único dentro de cada clínica a la que está vinculada, generado por el sistema en su primera consulta en esa clínica y no reutilizable. | Restricción | Aplicada (número por vínculo de clínica: Planificada v2) |
| RN-103 | Un propietario puede tener varias mascotas; cada mascota pertenece a un único propietario (titular). | Estructura | Aplicada |
| RN-104 | Una mascota con historial clínico no puede eliminarse; solo puede marcarse como inactiva (fallecida/retirada), conservando su historial. | Restricción | Aplicada |
| RN-105 | Las mascotas inactivas no aparecen en las búsquedas activas por defecto, pero su información permanece. | Restricción | Aplicada |
| RN-106 | La especie y la raza provienen de catálogos; la raza seleccionada debe corresponder a la especie de la mascota. | Restricción | Aplicada |
| RN-107 | Una mascota puede tener registrados varios colores de pelaje. | Estructura | Aplicada |
| RN-108 | Toda modificación de la ficha de una mascota registra automáticamente fecha, usuario y campo modificado (auditoría de mascotas). | Proceso | Aplicada |
| RN-109 | El propietario es una identidad global en la plataforma (correo único, RN-G06) que se vincula a una o varias clínicas. Puede registrarse por tres vías —alta por el personal de la clínica, enlace/QR de la clínica, o autoregistro directo eligiendo la clínica de una lista, con formulario o con Google (RN-G20)—, siempre en el contexto de una clínica. Si el correo ya existe, no se duplica: previa verificación de que es el dueño del correo, se liga a la nueva clínica. Cada clínica solo ve los propietarios vinculados a ella. El propietario solo queda vinculado a las clínicas que él elige: al registrarse se vincula a una, y desde su portal puede vincularse a otra de la lista o desvincularse cuando quiera. Solo puede agendar, cancelar o calificar en las clínicas a las que está vinculado. | Estructura | Planificada (v2) |
| RN-110 | La mascota es global: pertenece a su propietario, no a una clínica, y se vincula a cada clínica donde se atiende. Una misma mascota tiene una sola ficha, un solo carnet y un solo QR en toda la plataforma. La única excepción temporal es la ficha provisional de una urgencia roja sin titular verificado (RE-4.15.3): no se le atribuye propietario ni se activa su carnet hasta completar o consolidar la ficha. Si el propietario agenda una mascota en una clínica a la que ella aún no está vinculada, la mascota se vincula a esa clínica (y cuenta para su límite de plan). La especie, la raza, el sexo y la fecha de nacimiento solo los cambian el propietario o la clínica que registró la mascota, porque afectan el triage y las alertas en todas las clínicas; el peso, la foto y los demás datos los puede actualizar cualquier clínica vinculada. Todo cambio queda en auditoría (RN-108) y se notifica al propietario. | Estructura | Planificada (v2) |
| RN-111 | Antes de registrar una mascota a un propietario ya existente, la clínica ve las mascotas que él tiene en la plataforma y vincula la existente en lugar de crear un duplicado. | Restricción | Planificada (v2) |
| RN-112 | Cada registro clínico (consulta, vacuna, desparasitación, tratamiento y archivo) conserva la clínica y el veterinario que lo realizó, y solo esa clínica puede modificarlo. | Restricción | Planificada (v2) |
| RN-113 | Toda clínica vinculada ve siempre los datos básicos de la mascota, sus alergias y alertas médicas, y las vacunas y desparasitaciones de todas las clínicas, indicando cuál las aplicó. Las consultas, tratamientos y archivos de otra clínica solo los ve si el propietario la autorizó; la autorización es por clínica y el propietario puede revocarla. | Restricción | Planificada (v2) |
| RN-114 | El propietario ve en su portal la historia completa de su mascota en todas las clínicas, identificando la clínica de cada registro. | Estructura | Planificada (v2) |
| RN-115 | Si el propietario se desvincula de una clínica, los registros que esa clínica creó se conservan en la historia de la mascota, pero la clínica deja de ver los datos nuevos y pierde el acceso a lo registrado por otras clínicas. | Proceso | Planificada (v2) |

## Módulo 2 — Historia clínica y soporte a la decisión clínica (Grafo I)

| ID | Regla de negocio | Tipo | Estado |
|---|---|---|---|
| RN-201 | Solo el rol Veterinario puede registrar consultas clínicas. | Restricción | Aplicada |
| RN-202 | Una consulta no puede guardarse sin diagnóstico. | Restricción | Aplicada |
| RN-203 | Cada consulta queda vinculada a una mascota y, opcionalmente, a la cita que la originó; esa cita debe corresponder a la misma mascota. | Estructura | Aplicada |
| RN-204 | Los archivos clínicos permitidos son JPG, PNG y PDF, con un máximo de 10 MB por archivo; se almacenan en carpeta protegida y solo usuarios autenticados y autorizados pueden descargarlos. | Restricción | Aplicada |
| RN-205 | Una consulta puede tener múltiples tratamientos y múltiples archivos adjuntos. | Estructura | Aplicada |
| RN-206 | La historia clínica es acumulativa: los registros no se borran, se conservan en orden cronológico. | Restricción | Aplicada |
| RN-207 | Un acto clínico (consulta, vacunación o desparasitación) solo puede registrarse sobre una mascota existente y activa. Una mascota dada de baja conserva su historia clínica pero no admite registros nuevos. | Restricción | Aplicada |
| RN-208 | La atención clínica no está restringida al veterinario asignado en la cita: cualquier veterinario de la clínica puede registrar actos clínicos sobre cualquier mascota activa vinculada a la clínica (o que se vincula al atenderla, RN-110). Se descarta exigir cita previa porque bloquearía urgencias sin cita, cambios de turno y reasignaciones. La trazabilidad se conserva porque cada registro guarda el `id_usuario` del veterinario que lo realizó. La consulta ligada a una cita sí la registra el veterinario asignado a esa cita (RN-407): si la atiende otro, el administrador reasigna la cita o se usa la atención sin cita. | Estructura | Aplicada en v1 con documento · relación por `id_usuario`: Planificada (v2) |
| RN-209 | El soporte a la decisión clínica es de apoyo, no un diagnóstico automático: la decisión final es siempre del veterinario. | Restricción | Planificada (v2) |
| RN-210 | Al registrar los síntomas de una consulta, el sistema sugiere los diagnósticos probables ordenados por peso. | Proceso | Planificada (v2) |
| RN-211 | Al prescribir un fármaco, una alergia registrada del paciente a ese fármaco o una contraindicación por especie o raza (toxicidad) bloquean la prescripción; una interacción con la medicación vigente se advierte, y el veterinario confirma o cambia el fármaco, con registro en auditoría. El motor evalúa internamente las alergias y la medicación vigentes registradas por cualquier clínica. Si un tratamiento de otra clínica no es visible por RN-113, el aviso no revela su fármaco, dosis ni contenido: indica una posible interacción con medicación protegida y exige que el veterinario concilie la medicación con el propietario o solicite autorización antes de confirmar su decisión; la verificación y la decisión quedan auditadas. | Restricción | Planificada (v2) |
| RN-212 | El conocimiento clínico (nodos y relaciones con signo) es global: se comparte entre todas las clínicas y no pertenece a ninguna en particular. | Estructura | Planificada (v2) |

## Módulo 3 — Vacunación, desparasitación y recordatorios

| ID | Regla de negocio | Tipo | Estado |
|---|---|---|---|
| RN-301 | Cada vacuna registra fecha de aplicación y fecha de próxima dosis; el sistema genera una alerta antes del vencimiento, por defecto 7 días antes, según los tiempos que configure la clínica (RN-807). | Cálculo | Aplicada (tiempos configurables: Planificada v2) |
| RN-302 | La desparasitación tiene una periodicidad configurable (mensual, trimestral o semestral) y la fecha de la próxima aplicación se calcula automáticamente. | Cálculo | Aplicada |
| RN-303 | Los recordatorios de vacunación y desparasitación se envían por correo al propietario antes del vencimiento, por defecto 7 días y 1 día antes, según los tiempos que configure la clínica (RN-807). | Proceso | Aplicada (tiempos configurables: Planificada v2) |
| RN-304 | No se envía recordatorio si el propietario no tiene un correo electrónico registrado. | Restricción | Aplicada |
| RN-305 | Cada envío de recordatorio se registra (fecha, destinatario, tipo); un envío fallido se marca con estado de error y se reintenta hasta 3 veces (RE-3.6.2). | Proceso | Aplicada |
| RN-306 | El catálogo de vacunas aplica por especie: una vacuna base corresponde a determinadas especies. | Restricción | Aplicada |
| RN-307 | El recordatorio por WhatsApp es un canal adicional previsto para una evolución futura; el canal operativo actual es el correo electrónico. | Proceso | Futuro |

## Módulo 4 — Agenda de citas inteligente (Grafo II)

| ID | Regla de negocio | Tipo | Estado |
|---|---|---|---|
| RN-401 | No pueden existir dos citas para el mismo veterinario en el mismo horario (no se permiten solapamientos). | Restricción | Parcial: dos reservas simultáneas que se solapan sin empezar a la misma hora no se bloquean (HU-4.9, diferida) |
| RN-402 | Las citas solo pueden agendarse dentro del horario de atención configurado de la clínica. | Restricción | Parcial: se valida la hora de inicio, no la duración (HU-4.9, diferida) |
| RN-403 | Cada tipo de cita tiene una duración base que determina el bloque de tiempo que ocupa en la agenda. | Cálculo | Aplicada |
| RN-404 | Al crear una cita se envía una confirmación por correo al propietario de forma inmediata; un fallo en el envío no interrumpe el agendado. | Proceso | Aplicada |
| RN-405 | Una cita no se elimina: cambia de estado (pendiente, confirmada, en curso, pausada, completada, cancelada, no asistió o sin cerrar; el estado «cerrada sin consulta» existe solo en v1.11.0 y se deroga en la v2.0, RN-410). Solo una cita pendiente o confirmada se puede cancelar o reprogramar, y al hacerlo se notifica automáticamente al propietario. | Restricción | Aplicada |
| RN-406 | Una cita se completa únicamente al registrar su consulta, en la misma operación: no existe una cita completada sin consulta. Solo se completa una cita en curso o sin cerrar. | Restricción | Aplicada |
| RN-407 | La atención de una cita la inicia y la cierra solo el veterinario asignado, y solo el día de la cita, desde 15 minutos antes de su hora. Una atención iniciada se puede retomar en cualquier momento hasta registrar su consulta. La administración no atiende citas. | Restricción | Aplicada |
| RN-408 | Una cita pendiente o confirmada cuya hora ya pasó puede marcarse como "no asistió" por el veterinario asignado. Deja de ocupar su espacio en la agenda y cuenta para el ausentismo. | Proceso | Aplicada |
| RN-409 | Si una atención sigue en curso 10 minutos después de la hora de fin de la cita, el sistema avisa al veterinario asignado por correo y en sus notificaciones, una sola vez. Si al terminar el día de la cita sigue abierta, pasa a "sin cerrar" y se le avisa de nuevo. Una atención sin cerrar se completa registrando su consulta. | Proceso | Aplicada |
| RN-410 | _(Derogada en v2.)_ En v1.11.0 el veterinario asignado podía cerrar sin consulta una atención, con motivo obligatorio. **En v2.0 se elimina:** una atención iniciada debe completarse con su consulta; si no se termina el mismo día, queda en estado "sin cerrar" (RN-409) hasta completarse. | Restricción | Aplicada (v1.11) · Derogada (v2) |
| RN-411 | Cada cita tiene un nivel de prioridad (triage) de **4 niveles**: 🔴 rojo (crítico), 🟠 naranja (urgente), 🟡 amarillo (prioritario) y 🟢 verde (no urgente). El nivel lo **calcula el Grafo I** a partir de los síntomas; el propietario no lo elige. El rojo se deriva a atención de urgencia (no se agenda en línea); el naranja toma un sobrecupo; el amarillo, el primer espacio disponible; el verde se agenda por elección del actor. | Estructura | Planificada (v2) |
| RN-412 | Cuando una atención se extiende más allá de su hora de fin más el margen del tipo de cita, el retraso se propaga a las citas siguientes del mismo veterinario y el sistema recalcula sus horas estimadas de inicio. | Proceso | Planificada (v2) |
| RN-413 | Cuando el retraso saca una cita del horario disponible del veterinario, el sistema busca reasignarla a otro veterinario con disponibilidad; la reasignación en vivo la **confirma el veterinario destino**. Si ningún veterinario puede tomarla, la cita se **reprograma** ofreciendo otro espacio al propietario. | Proceso | Planificada (v2) |
| RN-414 | Un veterinario puede ausentarse (permiso, incapacidad); la ausencia la registra el administrador o la solicita el propio veterinario desde su portal, y tapa su disponibilidad en ese rango. Si tiene citas asignadas, el sistema busca cobertura en un veterinario con disponibilidad y le **asigna** esas citas (sin veto: si tiene espacio y nada se lo impide, las toma); solo si ningún veterinario puede cubrir, se reprograman con el propietario. Un intercambio de turnos es una ausencia del titular más la cobertura del reemplazo; la trazabilidad de quién atendió la da la historia clínica (RN-208), no la agenda. | Proceso | Planificada (v2) |
| RN-415 | La disponibilidad real para agendar es `horario de la clínica ∩ horario del veterinario − ausencias`, calculada por la duración de la cita más su margen. Cada tipo de cita define un margen (buffer) que absorbe los retrasos antes de propagarlos. | Cálculo | Planificada (v2) |
| RN-416 | **Cálculo del nivel de triage.** El nivel final es el más alto que dispare cualquier síntoma o combinación; nunca se promedia, y los datos tranquilizadores («come bien», «está activo») no bajan una alarma. Tres o más síntomas moderados, o uno moderado con deshidratación o decaimiento, suben un nivel. La raza, la edad, el estado (sin vacunas, hembra entera, gato macho), el historial (incluidas alergias de cualquier clínica) y el tiempo desde el inicio de los síntomas o desde la ingesta de un tóxico modifican el nivel. | Cálculo | Planificada (v2) |
| RN-417 | **Señales de alarma universales.** En cualquier especie, aunque no tenga cobertura en el grafo, son nivel 🔴: dificultad respiratoria, encías pálidas o azuladas, convulsión en curso, colapso o inconsciencia, hemorragia que no se detiene, no poder orinar, golpe de calor y parto con esfuerzo sin cría por más de 30 minutos. | Restricción | Planificada (v2) |
| RN-418 | Si la especie no tiene cobertura en el grafo o no se reconoce ningún síntoma, el caso nunca queda en 🟢: queda en 🟡 con la marca «revisión del personal», sin perjuicio de las alarmas universales (RN-417). | Restricción | Planificada (v2) |
| RN-419 | El veterinario puede ajustar el nivel de triage, con motivo obligatorio y registro en auditoría; también lo reclasifica al recibir al paciente. La diferencia entre el nivel calculado y el ajustado se guarda para mejorar el grafo, y las reclasificaciones quedan visibles en el historial del propietario. | Proceso | Planificada (v2) |
| RN-420 | Una urgencia 🔴 nunca se bloquea por el límite del plan ni por una suscripción en mora: se atiende y se registra. | Restricción | Planificada (v2) |
| RN-421 | Si un caso 🔴 llega cuando la clínica está fuera de su horario de atención, el sistema no promete atención: informa que la clínica está cerrada, muestra el teléfono de urgencias que la clínica haya configurado y recomienda acudir a un servicio de urgencias 24 horas. | Restricción | Planificada (v2) |
| RN-422 | Cada bloque de atención de un veterinario admite un número máximo de sobrecupos 🟠, configurable por la clínica (por defecto, 2). Entre casos del mismo nivel naranja se respeta el orden de llegada (`fecha_registro`, y `id_cita` si coinciden); no se calcula una puntuación adicional dentro del color. Un veterinario puede cambiar ese orden indicando un motivo, con registro en auditoría. Superado el tope, se avisa al personal para que decida. | Restricción | Planificada (v2) |
| RN-423 | Si el propietario actualiza los síntomas de una cita pendiente, el nivel se recalcula. Si sube, la cita se reubica según el nuevo nivel y se notifica; si baja, la cita conserva su espacio. | Proceso | Planificada (v2) |
| RN-424 | Los síntomas se capturan de forma estructurada: una lista de síntomas para marcar (del catálogo del grafo), un texto libre opcional y la pregunta de cuándo empezaron (o cuándo ocurrió la ingesta de un tóxico). | Restricción | Planificada (v2) |
| RN-425 | Si los síntomas y el paciente indican una posible enfermedad contagiosa (p. ej. cachorro sin vacunas con diarrea con sangre), el sistema avisa a la clínica para que prepare el aislamiento. | Proceso | Planificada (v2) |
| RN-426 | **Llegada tarde del propietario.** Dentro de la tolerancia que configure la clínica (por defecto, 10 minutos), la cita se atiende en el tiempo que queda; si el tipo de cita no cabe, el veterinario decide atenderla igual (el retraso se propaga según RN-412) o reprogramarla. Pasada la tolerancia, el veterinario decide entre marcar «no asistió» (RN-408), atenderla en el siguiente espacio libre o reprogramarla. En todos los casos la agenda se recalcula. | Proceso | Planificada (v2) |
| RN-427 | Cada tipo de cita indica si es **pausable** (configurable por la clínica; p. ej. una vacunación sí, una cirugía no). Una cita en curso solo se pausa para atender una urgencia 🔴, y se retoma al terminar la urgencia con prioridad sobre las citas siguientes del veterinario. | Restricción | Planificada (v2) |
| RN-428 | El veterinario destino tiene un plazo para confirmar una reasignación en vivo (configurable, por defecto 10 minutos). Si no responde o la rechaza, se propone al siguiente veterinario con disponibilidad; si ninguno la toma, la cita se reprograma con el propietario. | Proceso | Planificada (v2) |
| RN-429 | Los cambios de hora estimada se avisan al propietario solo cuando la diferencia acumulada supera un umbral (configurable, por defecto 15 minutos), y como máximo un aviso cada 30 minutos por cita. La reasignación de veterinario y la reprogramación se avisan siempre. | Restricción | Planificada (v2) |

## Módulo 5 — Portal del propietario (multi-clínica)

| ID | Regla de negocio | Tipo | Estado |
|---|---|---|---|
| RN-501 | El propietario puede auto-registrarse y agendar, cancelar o reprogramar citas para sus mascotas desde su portal, respetando las mismas reglas de disponibilidad y horario. | Proceso | Aplicada |
| RN-502 | El carnet público de la mascota se identifica con un token aleatorio no predecible, nunca con el identificador interno de la mascota. El propietario puede regenerarlo, y el token anterior deja de funcionar al instante. Si la mascota se marca inactiva, el carnet deja de mostrarse. | Restricción | Planificada (v2) |
| RN-503 | El carnet público muestra solo datos de emergencia: nombre, foto, especie y raza, alergias y alertas médicas, vacunas vigentes con la clínica que las aplicó, un teléfono de contacto del propietario y el de la clínica. Nunca muestra dirección, correo, documento ni la historia clínica. | Restricción | Planificada (v2) |
| RN-504 | Cada escaneo del carnet público se notifica al propietario con la fecha y la hora; quien escanea puede, si quiere, compartir su ubicación con el dueño. Los avisos se agrupan para no saturar al propietario con escaneos repetidos. | Proceso | Planificada (v2) |
| RN-505 | El acceso al carnet público admite como máximo 30 consultas por hora desde una misma IP y la página no es indexable por buscadores. | Restricción | Planificada (v2) |

## Módulo 7 — Configuración del sistema

| ID | Regla de negocio | Tipo | Estado |
|---|---|---|---|
| RN-701 | Solo el rol Administrador puede gestionar los usuarios, catálogos y horarios de su clínica. | Restricción | Aplicada |
| RN-702 | Los catálogos (especies, razas, colores, vacunas base, laboratorios y productos de desparasitación) son administrables y alimentan los formularios del sistema. Las especies, razas y colores son globales y los administra el super-administrador; los demás catálogos son propios de cada clínica y los administra su administrador. | Estructura | Aplicada (alcance por clínica: Planificada v2) |
| RN-703 | Los horarios de la clínica se definen por día de la semana (bloques de mañana y tarde) y condicionan la disponibilidad de la agenda. | Restricción | Aplicada |
| RN-704 | Los respaldos de la base de datos se ejecutan automáticamente cada 24 horas. | Proceso | Aplicada |
| RN-705 | Cada clínica gestiona sus veterinarios y las especialidades de cada uno. Si la persona ya existe en la plataforma (por documento o correo), recibe una invitación de 72 horas que acepta o rechaza; solo al aceptar se le asigna el rol en la clínica, conservando sus otros roles (RN-G06). Prevalece el correo sobre el documento; si solo el documento existe, se usa su correo registrado. La respuesta es la misma exista o no la cuenta; hasta aceptar, la lista de la clínica muestra únicamente los datos que escribió el administrador, con reenviar y cancelar. | Estructura | Planificada (v2) |
| RN-706 | Cada veterinario tiene un horario recurrente por día de la semana (franjas de atención), sin solapamientos y dentro del horario de la clínica. Si el veterinario también trabaja en otra clínica, su horario no puede chocar con el que tiene allá; el aviso dice que choca con otro compromiso del veterinario sin mostrar el horario de la otra clínica. Lo configura el administrador; el veterinario solo puede proponer cambios, que el administrador aprueba o rechaza; reemplaza el supuesto de disponibilidad permanente. Si al cambiarlo quedan citas fuera del nuevo horario, se marcan para reajuste. | Estructura | Planificada (v2) |

## Módulo 8 — Reputación y comunicaciones _(nuevo, v2)_

| ID | Regla de negocio | Tipo | Estado |
|---|---|---|---|
| RN-801 | Solo el propietario de una cita efectivamente atendida puede calificar al veterinario que lo atendió, y una sola vez por cita. | Restricción | Planificada (v2) |
| RN-802 | El promedio de calificación de un veterinario no se muestra públicamente hasta alcanzar 5 reseñas válidas (RN-805), para evitar promedios engañosos. | Restricción | Planificada (v2) |
| RN-803 | La calificación (estrellas de 1 a 5 y comentario opcional) alimenta el perfil público del veterinario y puede orientar la sugerencia de veterinario al momento de agendar. | Estructura | Planificada (v2) |
| RN-804 | La clínica y sus veterinarios no pueden editar ni eliminar reseñas. Solo el super-administrador puede ocultar una reseña por moderación (lenguaje ofensivo o datos personales), dejando registro del motivo en auditoría. | Restricción | Planificada (v2) |
| RN-805 | Solo cuentan para la reputación las reseñas de propietarios con el correo verificado cuya cita atendida tenga una consulta registrada. | Restricción | Planificada (v2) |
| RN-806 | Cada clínica puede personalizar las plantillas de sus correos y notificaciones (asunto, contenido y formato). | Estructura | Planificada (v2) |
| RN-807 | Los tiempos de envío de los recordatorios y alertas (días de anticipación y hora) son configurables por la clínica. | Estructura | Planificada (v2) |
| RN-808 | La clínica dispone de una bitácora de los correos y notificaciones enviados, con su estado (enviado/fallido). | Proceso | Planificada (v2) |

## Resumen

El catálogo reúne **119 reglas de negocio**: **50 vigentes** en el sistema actual (v1.11.0) — 2 de ellas parciales, 3 que la v2.0 amplía y **RN-410, que queda derogada** en la v2.0 —, **1 futura** (RN-307, WhatsApp) y **68 planificadas** para la v2.0 (arquitectura SaaS: multi-inquilino, planes y suscripción con protección contra abuso del plan gratuito, grafo clínico, triage de 4 niveles, horario y ausencias del veterinario, propietario y mascota multi-clínica, carnet QR seguro, reputación y comunicaciones). A partir de este catálogo se elaboran las Historias de Usuario (cada una referencia las reglas que la condicionan) y de ahí los requisitos específicos.

## Apéndice — Equivalencia de identificadores (Revisión 2.0)

La Revisión 2.0 alinea el número de cada regla con su módulo. Las reglas que no aparecen aquí conservan su identificador. Los comentarios del código y los documentos del repositorio ya usan el identificador nuevo; los mensajes de commits anteriores conservan el antiguo.

| Anterior | Nuevo | Módulo nuevo |
|---|---|---|
| RN-116 | RN-502 | 5 — Portal |
| RN-117 | RN-503 | 5 — Portal |
| RN-118 | RN-504 | 5 — Portal |
| RN-119 | RN-505 | 5 — Portal |
| RN-407 | RN-501 | 5 — Portal |
| RN-408 | RN-407 | 4 — Agenda |
| RN-409 | RN-408 | 4 — Agenda |
| RN-410 | RN-409 | 4 — Agenda |
| RN-411 | RN-410 | 4 — Agenda |
| RN-412 | RN-411 | 4 — Agenda |
| RN-413 | RN-412 | 4 — Agenda |
| RN-414 | RN-413 | 4 — Agenda |
| RN-415 | RN-414 | 4 — Agenda |
| RN-416 | RN-415 | 4 — Agenda |
| RN-501 | RN-701 | 7 — Configuración |
| RN-502 | RN-702 | 7 — Configuración |
| RN-503 | RN-703 | 7 — Configuración |
| RN-504 | RN-704 | 7 — Configuración |
| RN-505 | RN-705 | 7 — Configuración |
| RN-506 | RN-706 | 7 — Configuración |
| RN-601 | RN-209 | 2 — Historia clínica y Grafo I |
| RN-602 | RN-210 | 2 — Historia clínica y Grafo I |
| RN-603 | RN-211 | 2 — Historia clínica y Grafo I |
| RN-604 | RN-212 | 2 — Historia clínica y Grafo I |
| RN-701 | RN-801 | 8 — Reputación y comunicaciones |
| RN-702 | RN-802 | 8 — Reputación y comunicaciones |
| RN-703 | RN-803 | 8 — Reputación y comunicaciones |
| RN-704 | RN-804 | 8 — Reputación y comunicaciones |
| RN-705 | RN-805 | 8 — Reputación y comunicaciones |
| RN-801 | RN-806 | 8 — Reputación y comunicaciones |
| RN-802 | RN-807 | 8 — Reputación y comunicaciones |
| RN-803 | RN-808 | 8 — Reputación y comunicaciones |
