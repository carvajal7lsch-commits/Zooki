# Especificación de Requisitos de Software (ERS) — Proyecto Zooki

> **Revisión 2.0** · Conforme al estándar IEEE Std 830-1998 · Propuesta 16 — App de Registro y Seguimiento de Mascotas · SENA ADSO — Ficha 3142784

| Fecha | Revisión | Autor |
|---|---|---|
| 27/04/2026 | 1.0 | Juan Sebastián Carvajal Ome |
| 31/08/2026 | 2.0 | Juan Sebastián Carvajal Ome |

**Historial de revisiones.** La revisión 1.0 fue la línea base sobre el plan de cuatro sprints (abril 2026). La **revisión 2.0** alinea el documento con el sistema realmente construido (v1.8.0): corrige inconsistencias, fija el stack definitivo (sesiones PHP, MySQL 8, almacenamiento en servidor propio, correo transaccional), documenta las funcionalidades incorporadas (autenticación con Google, notificaciones internas, auditoría, catálogos, horarios de clínica), reclasifica WhatsApp como trabajo futuro y añade las secciones IEEE 830 de suposiciones/dependencias, requisitos futuros y diccionario de datos.

## 1. Introducción

### 1.1 Propósito

El presente documento constituye la Especificación de Requisitos de Software (ERS) del sistema **Zooki**, elaborado conforme al estándar IEEE Std 830-1998. Describe de manera precisa, completa y verificable los requisitos funcionales y no funcionales del software, y es el referente principal para las fases de diseño, implementación, pruebas y validación. La presente revisión toma como línea base los cuatro sprints iniciales del ciclo Scrum y los actualiza para reflejar el producto realmente construido (versión 1.8.0).

### 1.2 Alcance

Zooki es una aplicación web diseñada para digitalizar y optimizar la gestión clínica de pequeñas veterinarias en Neiva, Huila (Colombia). El sistema abarca:

- Registro y actualización de fichas de mascotas con información del propietario.
- Gestión completa de historias clínicas: consultas, diagnósticos, tratamientos y archivos adjuntos.
- Calendario inteligente de vacunación y desparasitación con alertas automáticas.
- Módulo de agenda de citas con control de disponibilidad del veterinario y horarios de la clínica.
- Envío automatizado de recordatorios y confirmaciones por correo electrónico.
- Portal web para que los propietarios se registren de forma autónoma, consulten la ficha digital de su mascota y agenden citas.
- Autenticación local y federada (inicio de sesión con Google).
- Búsqueda avanzada de pacientes, notificaciones internas y generación de reportes en PDF.

**Fuera de alcance:** facturación electrónica, inventario de medicamentos, integración con laboratorios externos y nómina de personal. El canal de recordatorios por WhatsApp se contempla como evolución futura (§2.6), no como parte de la línea base entregada.

### 1.3 Personal involucrado

Zooki es un proyecto formativo de desarrollo individual: una sola persona asume la totalidad de los roles del ciclo de vida. Los roles Scrum se declaran con fines formativos.

| Campo | Valor |
|---|---|
| Nombre | Juan Sebastián Carvajal Ome |
| Rol | Desarrollador único (full-stack) · Scrum Master y Development Team (roles formativos) |
| Categoría profesional | Aprendiz SENA — Análisis y Desarrollo de Software (ADSO) |
| Responsabilidades | Análisis, diseño, desarrollo, pruebas, despliegue y documentación del sistema web. |
| Contacto | carvajal7lsch@gmail.com |

### 1.4 Definiciones, acrónimos y abreviaturas

| Término / Sigla | Definición |
|---|---|
| IEEE | Institute of Electrical and Electronics Engineers. |
| RF / RNF | Requisito Funcional / Requisito No Funcional. |
| ERS | Especificación de Requisitos de Software. |
| MVC | Modelo-Vista-Controlador. |
| OAuth 2.0 | Protocolo usado para el inicio de sesión federado con Google. |
| PDO | PHP Data Objects — acceso a datos con sentencias preparadas. |
| RBAC | Role-Based Access Control — control de acceso basado en roles. |
| CSRF | Cross-Site Request Forgery — mitigado con tokens sincronizados. |
| Historia Clínica | Registro digital de todas las atenciones médicas del paciente. |

### 1.5 Referencias normativas

- IEEE Std 830-1998 — Práctica recomendada para la especificación de requisitos de software.
- ISO/IEC/IEEE 29148:2018 — Requirements engineering (referencia complementaria, estándar sucesor del 830).
- Ley 1581 de 2012 y Decreto 1377 de 2013 — Protección de datos personales (Colombia).
- Ley 84 de 1989 — Estatuto Nacional de Protección de los Animales.
- Resolución 1995 de 1999 (Min. Salud) — Manejo de historias clínicas (buenas prácticas).

## 2. Descripción general

### 2.1 Perspectiva del producto

Zooki nace como respuesta a la dependencia de fichas en cartón, agendas físicas y la ausencia de seguimiento proactivo al paciente animal en clínicas veterinarias de pequeña escala en Neiva. Es una aplicación web autónoma, de tres capas, desplegada en infraestructura propia, que no depende de sistemas preexistentes de la clínica.

### 2.2 Funciones principales

Gestión de pacientes; historia clínica; agenda inteligente con horarios configurables; notificaciones por correo e internas; portal del propietario con auto-registro; reportes en PDF; auditoría y control de acceso por roles.

### 2.3 Características de los usuarios

| Rol | Descripción y nivel de acceso |
|---|---|
| Administrador | Perfil técnico y de gestión. Administra usuarios, catálogos, horarios, respaldos y auditoría. |
| Veterinario | Usuario clínico principal. Gestiona pacientes, historias clínicas, vacunación, agenda y reportes. |
| Recepcionista | Gestiona citas y registra nuevos pacientes y propietarios. |
| Propietario | Accede al portal externo con sus propias credenciales (o con Google). Se registra de forma autónoma, consulta sus mascotas y agenda citas. |

### 2.4 Restricciones

- Accesible desde Chrome, Firefox, Edge y Safari vigentes, sin instalación adicional.
- Arquitectura con tecnologías nativas (PHP 8.2 + MySQL 8), sin frameworks de backend.
- Desarrollo organizado en sprints semanales bajo Scrum; línea base de cuatro sprints, evolución por versiones (v1.x).
- Datos clínicos y personales almacenados conforme a la Ley 1581 de 2012.
- Opera con conexión mínima de 1 Mbps; las metas de rendimiento (§3.3.1) se miden sobre una red de referencia de 5 Mbps.
- La autenticación federada depende de la disponibilidad del servicio de identidad de Google (OAuth 2.0).

### 2.5 Suposiciones y dependencias

- El servidor propio (VPS Linux con Docker) está disponible con Apache/Nginx + PHP-FPM 8.2 y MySQL 8 operativos.
- Existe una cuenta SMTP válida (Gmail con contraseña de aplicación) para el envío de correos mediante PHPMailer.
- Las credenciales de Google Cloud (cliente OAuth 2.0) están configuradas para el login federado.
- Los propietarios disponen de un correo válido para recibir credenciales, confirmaciones y recordatorios.
- El navegador soporta JavaScript ES6+ y peticiones asíncronas (Fetch API).
- Dependencias de terceros: PHPMailer (correo) y PHPUnit (pruebas), gestionadas con Composer.

### 2.6 Requisitos futuros y evolución previsible

No forman parte de la línea base entregada; se registran para preservar la trazabilidad.

| ID | Descripción | Prioridad | Criterio de verificación |
|---|---|---|---|
| RF-F01 | Recordatorios por WhatsApp Business API (Meta Cloud API) como canal adicional al correo. | Futura | Mensaje entregado correctamente en cuenta de prueba de WhatsApp Business. |
| RF-F02 | Módulo de facturación e inventario de medicamentos. | Futura | Alta de factura e inventario descontado tras una venta. |
| RF-F03 | Aplicación móvil / PWA instalable para el portal del propietario. | Futura | Instalación en dispositivo y notificaciones push funcionando. |
| RF-F04 | Tablero analítico con métricas accionables (carga del veterinario, pacientes por período). | Futura | Indicadores calculados y contrastados contra la base de datos. |

## 3. Requisitos específicos

### 3.1 Requisitos comunes de las interfaces

#### 3.1.1 Interfaces de usuario

| Vista / Módulo | Descripción de la interfaz |
|---|---|
| Dashboard principal | Resumen del día: citas, vacunaciones próximas y alertas. Acceso rápido a funciones frecuentes. |
| Gestión de pacientes | Listado con buscador en tiempo real, filtros por especie y formulario con validación en línea. |
| Historia clínica | Vista cronológica con acordeón. Formulario de consulta con campos dinámicos y visor de archivos. |
| Calendario / Agenda | Vista mensual/semanal con citas y vacunas diferenciadas por color; bloqueo de solapamientos. |
| Portal del propietario | Interfaz mobile-first con tarjetas visuales por mascota; auto-registro y agendamiento. |
| Reportes | Filtros de fecha, previsualización y exportación a PDF. |

#### 3.1.2 Interfaces de software y comunicación

| Interfaz | Descripción |
|---|---|
| Correo (SMTP / PHPMailer) | Correos transaccionales: bienvenida, confirmación de citas y recordatorios (imágenes incrustadas por CID). |
| Google Identity (OAuth 2.0) | Inicio de sesión federado como alternativa a las credenciales locales. |
| Almacenamiento de archivos | Fotografías y archivos clínicos en el filesystem del servidor propio (carpeta protegida con .htaccess), servidos por un script autenticado. |
| Base de datos | MySQL 8, accedida con PDO y sentencias preparadas. |
| Tareas programadas | Cron del servidor para recordatorios automáticos y respaldos de la base de datos. |
| Navegadores soportados | Chrome ≥ 90, Firefox ≥ 88, Edge ≥ 90, Safari ≥ 14. |

### 3.2 Requisitos funcionales

#### 3.2.1 Módulo 1 — Registro y Gestión de Mascotas

| ID | Descripción del requisito | Prioridad | Criterio de verificación |
|---|---|---|---|
| RF-01 | Registrar una mascota con: nombre, especie, raza, fecha de nacimiento, peso, sexo, color(es) y fotografía. | Alta | El registro aparece en la búsqueda con todos los campos correctos. |
| RF-02 | Vincular cada mascota a un propietario con: nombre, documento, dirección, teléfono y correo. | Alta | No es posible guardar una mascota sin propietario asignado. |
| RF-03 | Actualizar los datos de la ficha registrando automáticamente fecha y usuario del cambio. | Alta | La auditoría refleja campo, valor anterior y nuevo. |
| RF-04 | Buscar mascotas por nombre, propietario, documento o número de historia clínica, en tiempo real. | Alta | Resultados en < 2 s con un mínimo de 3 caracteres. |
| RF-05 | Registrar múltiples mascotas para un mismo propietario, agrupadas en su perfil. | Media | Desde el perfil se listan todas sus mascotas. |
| RF-06 | Marcar una mascota como inactiva sin eliminar su historial clínico. | Media | La inactiva no aparece en búsquedas activas; su historial permanece. |
| RF-28 | Registrar varios colores por mascota (relación N:M). | Baja | Una mascota puede almacenar y mostrar más de un color. |

#### 3.2.2 Módulo 2 — Historia Clínica Veterinaria

| ID | Descripción del requisito | Prioridad | Criterio de verificación |
|---|---|---|---|
| RF-07 | Registrar consultas con fecha/hora, motivo, anamnesis, examen físico, diagnóstico y plan. | Alta | La consulta es visible en el historial cronológico. |
| RF-08 | Adjuntar archivos clínicos (JPG, PNG, PDF) de máximo 10 MB por archivo. | Alta | Los archivos se almacenan y son descargables por un usuario autorizado. |
| RF-09 | Registrar tratamientos con medicamento, dosis, vía, duración y observaciones. | Alta | El tratamiento queda vinculado a la consulta. |
| RF-10 | Generar un número de historia clínica único por mascota en su primera consulta. | Media | El número no se repite en toda la base de datos. |
| RF-11 | Visualizar el resumen cronológico de todas las consultas con acordeón expandible. | Media | La vista carga en < 3 s para historiales de hasta 100 consultas. |

#### 3.2.3 Módulo 3 — Calendario de Vacunación y Recordatorios

| ID | Descripción del requisito | Prioridad | Criterio de verificación |
|---|---|---|---|
| RF-12 | Registrar vacunas con nombre, laboratorio, lote, fecha de aplicación y próxima dosis. | Alta | La vacuna aparece en el calendario y genera alerta 7 días antes. |
| RF-13 | Enviar recordatorios automáticos por correo 7 días y 1 día antes del vencimiento. | Alta | Correos recibidos en la bandeja con datos correctos. |
| RF-15 | Panel de vacunaciones pendientes de la semana, agrupadas por día y especie. | Media | El panel muestra datos correctos contra la base de datos. |
| RF-16 | Registrar el esquema de desparasitación con periodicidad configurable. | Media | Las alertas se generan según la periodicidad configurada. |

> _Nota: el recordatorio por WhatsApp de la línea base original (antiguo RF-14) se reclasifica como trabajo futuro (RF-F01, §2.6). El canal operativo es el correo electrónico._

#### 3.2.4 Módulo 4 — Agenda de Citas y Portal del Propietario

| ID | Descripción del requisito | Prioridad | Criterio de verificación |
|---|---|---|---|
| RF-17 | Agendar citas con fecha, hora, mascota, tipo y veterinario, verificando disponibilidad y horario. | Alta | No permite doble cita al mismo veterinario ni fuera del horario. |
| RF-18 | Enviar confirmación automática de la cita al propietario por correo al crearla. | Alta | Correo de confirmación recibido con datos correctos. |
| RF-19 | Portal con credenciales propias: ficha, historial, citas y calendario de vacunas. | Alta | El propietario solo ve sus mascotas; acceso ajeno devuelve 403. |
| RF-20 | Cancelar o reprogramar citas, notificando al propietario. | Media | El propietario recibe la notificación del cambio. |
| RF-21 | Generar reportes exportables en PDF: pacientes, citas del período y vacunaciones pendientes. | Media | El PDF descargado contiene datos fidedignos. |
| RF-22 | Permitir el auto-registro de propietarios y el agendamiento desde su propio portal. | Alta | El propietario crea su cuenta y agenda citas desde su sesión. |

#### 3.2.5 Módulo 5 — Acceso, Configuración y Evoluciones incorporadas (v1.4–v1.7)

| ID | Descripción del requisito | Prioridad | Criterio de verificación |
|---|---|---|---|
| RF-23 | Autenticación federada con Google (OAuth 2.0) como alternativa al login local. | Media | El usuario inicia sesión con Google y accede según su rol. |
| RF-24 | Recuperación de contraseña por correo mediante token temporal con expiración. | Media | El enlace caduca y permite restablecer la contraseña una sola vez. |
| RF-25 | Notificaciones internas segmentadas por rol ante eventos operativos. | Media | El destinatario ve la notificación y puede marcarla como leída. |
| RF-26 | Configurar los horarios de atención de la clínica por día (bloques mañana/tarde). | Alta | La agenda solo ofrece franjas dentro del horario configurado. |
| RF-27 | Gestionar catálogos paramétricos: especies, razas, colores, vacunas base, laboratorios, productos. | Media | Los formularios se alimentan de los catálogos administrables. |

### 3.3 Requisitos no funcionales

#### 3.3.1 Rendimiento

| ID | Descripción | Prioridad | Criterio de verificación |
|---|---|---|---|
| RNF-01 | Tiempo de respuesta ≤ 3 s para consultas y búsquedas sobre una red de referencia ≥ 5 Mbps. | Alta | Prueba de carga con JMeter a 10 usuarios concurrentes. |
| RNF-02 | Soportar al menos 20 usuarios concurrentes sin degradación visible. | Media | Prueba de 20 usuarios simultáneos sin errores HTTP 5xx. |

#### 3.3.2 Seguridad y privacidad

| ID | Descripción | Prioridad | Criterio de verificación |
|---|---|---|---|
| RNF-03 | HTTPS (TLS 1.2+). Contraseñas con hashing bcrypt + salt. | Alta | Headers confirman HTTPS; contraseñas no legibles en BD. |
| RNF-04 | Control de acceso basado en roles (RBAC): administrador, veterinario, recepcionista y propietario. | Alta | Un "Propietario" no accede a rutas de administración (403). |
| RNF-05 | Archivos clínicos con acceso restringido, servidos por un script de control. | Alta | URL directa sin autenticación devuelve 401/403. |
| RNF-06 | Auditoría de operaciones críticas con usuario, fecha, IP y datos previos/nuevos. | Media | El log muestra las entradas correctas tras operaciones de prueba. |
| RNF-14 | Protección contra CSRF en todos los formularios mediante tokens sincronizados. | Alta | Una petición sin token válido es rechazada. |
| RNF-15 | Prevención de inyección SQL mediante sentencias preparadas (PDO). | Alta | Revisión de código: ninguna consulta concatena entrada sin parametrizar. |

#### 3.3.3 Usabilidad y accesibilidad

| ID | Descripción | Prioridad | Criterio de verificación |
|---|---|---|---|
| RNF-07 | Interfaz responsiva: escritorio (≥ 1024px), tablet (768–1023px) y móvil (320–767px). | Alta | Prueba visual en Chrome DevTools sin superposición. |
| RNF-08 | Un usuario nuevo registra una mascota y agenda una cita en < 10 minutos sin asistencia. | Media | Prueba con 3 usuarios reales; promedio < 10 min. |
| RNF-09 | Mensajes de error en español, descriptivos y con acción correctiva. | Media | Ningún error muestra texto en inglés o códigos técnicos. |

#### 3.3.4 Confiabilidad y disponibilidad

| ID | Descripción | Prioridad | Criterio de verificación |
|---|---|---|---|
| RNF-10 | Disponibilidad ≥ 95% en horario de operación (lunes a sábado, 7:00–20:00). | Alta | Monitoreo de uptime durante el período de pruebas. |
| RNF-11 | Copias de seguridad automáticas de la BD cada 24 horas. | Alta | Verificación de existencia y restauración de respaldos. |

#### 3.3.5 Mantenibilidad y portabilidad

| ID | Descripción | Prioridad | Criterio de verificación |
|---|---|---|---|
| RNF-12 | Código documentado (README) y cubierto por pruebas automatizadas en integración continua. | Media | Reporte de PHPUnit y workflow de CI en verde. |
| RNF-13 | Sistema desplegable en desarrollo y producción mediante variables de entorno y Docker. | Media | Despliegue exitoso en ambos entornos desde la misma imagen. |

## 4. Apéndices

### 4.1 Casos de uso

| CU-ID | Nombre | Actor principal | RF relacionados |
|---|---|---|---|
| CU-01 | Registrar mascota y propietario | Vet. / Recepcionista | RF-01, RF-02, RF-28 |
| CU-02 | Actualizar ficha de mascota | Veterinario | RF-03, RF-06 |
| CU-03 | Buscar paciente | Vet. / Recepcionista | RF-04 |
| CU-04 | Registrar consulta clínica | Veterinario | RF-07, RF-08, RF-09, RF-10 |
| CU-05 | Ver historia clínica completa | Veterinario | RF-11 |
| CU-06 | Registrar vacunación / desparasitación | Veterinario | RF-12, RF-15, RF-16 |
| CU-07 | Enviar recordatorio automático | Sistema | RF-13 |
| CU-08 | Agendar cita | Recep. / Vet. / Propietario | RF-17, RF-18, RF-22 |
| CU-09 | Cancelar o reprogramar cita | Vet. / Recepcionista | RF-20 |
| CU-10 | Auto-registro y acceso al portal | Propietario | RF-19, RF-22, RF-23 |
| CU-11 | Generar reporte | Veterinario | RF-21 |
| CU-12 | Configurar horarios y catálogos | Administrador | RF-26, RF-27 |

### 4.2 Modelo de datos y diccionario de datos

El modelo relacional de Zooki está compuesto por **24 tablas** en MySQL 8, en seis dominios funcionales. El diagrama entidad-relación completo se versiona junto al código (ver documento **Modelo Entidad-Relación**).

| Entidad (tabla) | Descripción y atributos clave |
|---|---|
| roles | Roles del sistema: 1 Administrador, 2 Veterinario, 3 Recepcionista, 4 Propietario. |
| usuarios | Datos personales, credenciales cifradas o UID de Google, rol y estado. (documento PK, id_rol FK) |
| password_resets | Tokens temporales de recuperación de contraseña. |
| especies / razas | Catálogos taxonómicos; una raza pertenece a una especie. |
| colores_base / mascota_colores | Catálogo de colores y relación N:M con las mascotas. |
| mascotas | Ficha del paciente. (id_mascota PK, numero_historia_clinica, doc_propietario FK, id_especie FK, id_raza FK) |
| tipos_cita | Servicios médicos con duración base. |
| citas | Agenda veterinaria. (id_cita PK, id_mascota FK, doc_veterinario FK, fecha, hora, estado) |
| consultas | Ficha clínica: anamnesis, constantes, diagnóstico y plan. (id_consulta PK, id_mascota FK, id_cita FK) |
| tratamientos | Medicamentos y dosis por consulta. |
| archivos_clinicos | Índice de archivos adjuntos a consultas. |
| vacunas / vacunas_base / especie_vacunas | Historial de vacunas y catálogo por especie. |
| desparasitaciones | Control preventivo interno/externo con próxima fecha. |
| laboratorios_base / productos_desparasitacion_base | Catálogos de laboratorios y productos. |
| notificaciones | Notificaciones al propietario (email). |
| notificaciones_internas | Notificaciones internas segmentadas por rol. |
| auditoria_mascotas | Log de cambios sobre fichas de mascotas. |
| auditoria_sistema | Auditoría forense del sistema con payloads JSON. |
| horarios_clinica | Bloques de atención por día de la semana. |

### 4.3 Trazabilidad requisitos – implementación (v1.8.0)

| Requisitos | Módulo | Estado | Evidencia en el código |
|---|---|---|---|
| RF-01 a RF-06, RF-28 | Mascotas y propietarios | Implementado | MascotaController, PropietarioController, auditoria_mascotas |
| RF-07 a RF-11 | Historia clínica | Implementado | ConsultaController, models/Consulta, Tratamiento, archivos_clinicos |
| RF-12, RF-15, RF-16 | Vacunación y desparasitación | Implementado | VacunaController, DesparasitacionController |
| RF-13 | Recordatorios por correo | Implementado | scripts/send_reminders.php, PHPMailer |
| RF-F01 (ex RF-14) | Recordatorios por WhatsApp | Futuro | No implementado — roadmap (§2.6) |
| RF-17, RF-18, RF-20, RF-22 | Agenda y portal | Implementado | CitaController, HorarioClinicaController, views/portal |
| RF-19, RF-23, RF-24 | Acceso y portal del propietario | Implementado | AuthController, PasswordReset, login con Google |
| RF-21 | Reportes PDF | Implementado | views/admin/reportes.php |
| RF-25, RF-26, RF-27 | Notificaciones, horarios y catálogos | Implementado | NotificacionController, HorarioClinicaController |
| RNF-03, RNF-04, RNF-14, RNF-15 | Seguridad | Implementado | helpers/Security.php, helpers/Csrf.php, PDO, password_hash |
| RNF-06 | Auditoría | Implementado | models/Auditoria, auditoria_sistema, auditoria_mascotas |
| RNF-11 | Respaldos | Implementado | scripts/backup.php + cron |
| RNF-12, RNF-13 | Pruebas y despliegue | Implementado | tests/ (PHPUnit), CI, Docker |

### 4.4 Criterios de aceptación global

El sistema se considera conforme cuando: el 100% de los RF de prioridad Alta han sido implementados y superan sus pruebas; el 80% o más de los RF de prioridad Media están implementados; no existen defectos críticos ni mayores sin cerrar; el portal del propietario fue validado con un usuario real externo; los recordatorios por correo se probaron en producción; y la documentación técnica (README, manual y este ERS) está en el repositorio.
