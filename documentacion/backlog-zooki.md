# Backlog Zooki — Extraído de Jira

> Proyecto: **Zooki** (`ZOOK`)  
> Fecha de extracción: 2026-06-07  
> Fuente: https://carvajal7lsch.atlassian.net/browse/ZOOK

---

## Índice

- [Tareas Generales / Documentación](#tareas-generales--documentación)
- [Sprint 1](#sprint-1)
  - [HU-01 | Registrar mascota](#hu-01--registrar-mascota)
  - [HU-02 | Registrar propietario](#hu-02--registrar-propietario)
  - [HU-03 | Buscar paciente](#hu-03--buscar-paciente)
  - [HU-04 | Editar y desactivar mascota](#hu-04--editar-y-desactivar-mascota)
- [Sprint 2](#sprint-2)
  - [HU-05 | Registrar consulta clínica](#hu-05--registrar-consulta-clínica)
  - [HU-06 | Adjuntar archivos clínicos](#hu-06--adjuntar-archivos-clínicos)
  - [HU-07 | Registrar tratamiento](#hu-07--registrar-tratamiento)
  - [HU-08 | Ver historial clínico completo](#hu-08--ver-historial-clínico-completo)
- [Sprint 3](#sprint-3)
  - [HU-09 | Registrar vacunación](#hu-09--registrar-vacunación)
  - [HU-10 | Enviar recordatorio por email](#hu-10--enviar-recordatorio-por-email)
  - [HU-11 | Enviar recordatorio por WhatsApp](#hu-11--enviar-recordatorio-por-whatsapp)
  - [HU-12 | Registrar desparasitación](#hu-12--registrar-desparasitación)
- [Sprint 4](#sprint-4)
  - [HU-13 | Agendar cita](#hu-13--agendar-cita)
  - [HU-14 | Cancelar o reprogramar cita](#hu-14--cancelar-o-reprogramar-cita)
  - [HU-15 | Portal del propietario](#hu-15--portal-del-propietario)
  - [HU-16 | Generar reportes en PDF](#hu-16--generar-reportes-en-pdf)
  - [HU-19 | Marcar cita como completada](#hu-19--marcar-cita-como-completada)
  - [HU-21 | Confirmación automática de cita por email](#hu-21--confirmación-automática-de-cita-por-email)
  - [HU-22 | Gestión de usuarios del sistema](#hu-22--gestión-de-usuarios-del-sistema)
  - [HU-23 | Backup automático de BD](#hu-23--backup-automático-de-bd)
  - [HU-24 | Logs de auditoría y seguridad](#hu-24--logs-de-auditoría-y-seguridad)
  - [HU-25 | Auto-registro de propietario](#hu-25--auto-registro-de-propietario)
  - [HU-26 | Agendar cita desde el portal](#hu-26--agendar-cita-desde-el-portal)
- [Sprint Extra — Identificadas del ERS](#sprint-extra--identificadas-del-ers)
  - [HU-18 | Dashboard principal + panel pendientes](#hu-18--dashboard-principal--panel-pendientes)
  - [HU-20 | Panel de vacunaciones pendientes (RF-15)](#hu-20--panel-de-vacunaciones-pendientes-rf-15)

---

## Tareas Generales / Documentación

| Issue | Resumen | Estado | Tipo |
|-------|---------|--------|------|
| ZOOK-1 | Documento ERS formato IEEE-830 | Finalizada | Tarea |
| ZOOK-2 | Modelo Entidad Relacion | Finalizada | Tarea |
| ZOOK-3 | Ficha Tecnica | Pruebas | Tarea |
| ZOOK-4 | Diagrama de Flujo de sistema | En curso | Tarea |

---

## Sprint 1

### HU-01 | Registrar mascota
- **Issue:** ZOOK-5
- **Estado:** Pruebas
- **Prioridad:** Medium

**Descripción:**  
> Como *veterinario*, *quiero* registrar una mascota con sus datos básicos *para* tener una ficha digital completa del paciente.

**Criterios de aceptación:**
- El formulario exige nombre, especie, raza, fecha de nacimiento, peso, sexo y color.
- Se puede subir una fotografía (JPG/PNG, máx. 5 MB).
- Al guardar, la mascota aparece en el listado y en la búsqueda.
- No se puede guardar sin propietario asignado.

**Subtareas:**
- Diseñar formulario HTML de registro.
- Crear clase `MascotaDAO` con método `insert()`.
- Validar campos obligatorios en PHP (servidor) y JS (cliente).
- Implementar subida de fotografía con `move_uploaded_file()`.
- Crear tabla `mascotas` en MySQL con migraciones SQL.

---

### HU-02 | Registrar propietario
- **Issue:** ZOOK-6
- **Estado:** Pruebas
- **Prioridad:** Medium

**Descripción:**  
> Como *recepcionista*, *quiero* registrar los datos del propietario *para* poder contactarlo y vincularle sus mascotas.

**Criterios de aceptación:**
- Campos obligatorios: nombre, tipo y número de documento, teléfono y correo.
- WhatsApp es opcional.
- No se permiten documentos duplicados en la BD.
- Desde el perfil del propietario se listan todas sus mascotas.

**Subtareas:**
- Diseñar formulario HTML de propietario.
- Crear clase `PropietarioDAO` con métodos `insert()` y `findByDocumento()`.
- Validar unicidad de documento antes de guardar.
- Crear tabla `propietarios` y relación con `mascotas`.

---

### HU-03 | Buscar paciente
- **Issue:** ZOOK-7
- **Estado:** Pruebas
- **Prioridad:** Medium

**Descripción:**  
> Como *veterinario o recepcionista*, *quiero* buscar mascotas rápidamente *para* acceder a su ficha sin navegar por listados largos.

**Criterios de aceptación:**
- Búsqueda por nombre de mascota, nombre del propietario o número de documento.
- Resultados aparecen en menos de 2 segundos con mínimo 3 caracteres.
- Resultados muestran nombre, especie, propietario y foto miniatura.

**Subtareas:**
- Endpoint PHP que recibe parámetro de búsqueda y retorna JSON.
- Consulta SQL con `LIKE` sobre múltiples campos.
- Implementar búsqueda en tiempo real con `fetch()` y JS nativo.
- Renderizar resultados dinámicamente en el DOM.

---

### HU-04 | Editar y desactivar mascota
- **Issue:** ZOOK-8
- **Estado:** Pruebas
- **Prioridad:** Medium

**Descripción:**  
> Como *veterinario*, *quiero* actualizar los datos de una mascota o marcarla como inactiva *para* mantener la información vigente sin perder el historial.

**Criterios de aceptación:**
- Se puede editar cualquier campo de la ficha.
- Al guardar, se registra en un log la fecha, usuario y campo modificado.
- Marcar como inactiva oculta la mascota de búsquedas activas pero conserva su historial.
- No se permite eliminar permanentemente una mascota con historial clínico.

**Subtareas:**
- Formulario de edición prellenado con datos actuales.
- Método `update()` en `MascotaDAO` con registro en tabla `auditoria`.
- Campo `estado` (activa/inactiva) en tabla `mascotas`.
- Filtro en búsquedas para excluir inactivas por defecto.

---

### HU-17 | Login y autenticación
- **Issue:** ZOOK-22
- **Estado:** Pendiente
- **Prioridad:** Alta

**Descripción:**
> Como *usuario del sistema*, *quiero* iniciar sesión con credenciales seguras *para* acceder a las funciones autorizadas según mi rol.

**Criterios de aceptación:**
- Login con usuario y contraseña validados contra la BD.
- Contraseñas almacenadas con hashing bcrypt + salt (RNF-03).
- Sesión controlada con PHP nativo + JWT manual.
- Según el rol (veterinario, recepcionista, admin, propietario) se redirige al panel correspondiente.
- Mensaje genérico de error ante credenciales inválidas (no revelar cuál campo falló).

**Subtareas:**
- Crear tabla `usuarios` con campos: id, nombre, email, password_hash, rol, activo.
- Script `login.php` con validación de credenciales y generación de JWT.
- Middleware de autenticación que verifique el token en cada petición protegida.
- Formulario HTML de login con validación en JS.
- Script `logout.php` que destruya la sesión y el JWT.

---

## Sprint 2

### HU-05 | Registrar consulta clínica
- **Issue:** ZOOK-9
- **Estado:** En curso
- **Prioridad:** Medium

**Descripción:**  
> Como *veterinario*, *quiero* registrar una consulta médica completa *para* llevar trazabilidad de la salud del paciente.

**Criterios de aceptación:**
- Campos: fecha/hora, motivo, anamnesis, examen físico (peso, temperatura, FC), diagnóstico y plan de tratamiento.
- Se genera número de historia clínica único en la primera consulta.
- La consulta queda vinculada a la mascota y visible en su historial cronológico.
- No se puede registrar consulta sin diagnóstico.

**Subtareas:**
- Crear tabla `consultas` con FK a `mascotas`.
- Clase `ConsultaDAO` con `insert()` y `findByMascota()`.
- Generar número de HC automático (consecutivo por mascota).
- Formulario HTML con validación de campos obligatorios.
- Vista de historial cronológico con acordeón en JS.

---

### HU-06 | Adjuntar archivos clínicos
- **Issue:** ZOOK-10
- **Estado:** En curso
- **Prioridad:** Medium

**Descripción:**  
> Como *veterinario*, *quiero* adjuntar imágenes y documentos a una consulta *para* tener evidencia visual del estado del paciente.

**Criterios de aceptación:**
- Formatos permitidos: JPG, PNG, PDF.
- Tamaño máximo por archivo: 10 MB.
- Los archivos se almacenan en carpeta protegida del servidor.
- Solo usuarios autenticados pueden descargar los archivos.
- Se pueden adjuntar múltiples archivos por consulta.

**Subtareas:**
- Configurar `upload_max_filesize` y `post_max_size` en `php.ini`.
- Crear carpeta `/uploads/clinicos/` con `.htaccess` que bloquee acceso directo.
- Script PHP que sirve archivos solo si la sesión está activa.
- Tabla `archivos_clinicos` con FK a `consultas`.
- Visor de imágenes en el frontend con JS.

---

### HU-07 | Registrar tratamiento
- **Issue:** ZOOK-11
- **Estado:** En curso
- **Prioridad:** Medium

**Descripción:**  
> Como *veterinario*, *quiero* registrar el tratamiento prescrito en una consulta *para* que que quede documentado en la historia clínica.

**Criterios de aceptación:**
- Campos: medicamento, dosis, vía de administración, duración y observaciones.
- El tratamiento queda vinculado a la consulta.
- Se pueden registrar múltiples tratamientos por consulta.
- Visible en el resumen de la historia clínica.

**Subtareas:**
- Tabla `tratamientos` con FK a `consultas`.
- Clase `TratamientoDAO` con `insert()` and `findByConsulta()`.
- Sección de tratamientos en el formulario de consulta (agregar dinámicamente con JS).
- Mostrar tratamientos en la vista del historial clínico.

---

### HU-08 | Ver historial clínico completo
- **Issue:** ZOOK-12
- **Estado:** En curso
- **Prioridad:** Medium

**Descripción:**  
> Como *veterinario*, *quiero* ver todas las consultas de una mascota en una sola pantalla *para* tener contexto clínico completo antes de atender.

**Criterios de aceptación:**
- Vista cronológica (más reciente primero) con acordeón expandible por consulta.
- Cada entrada muestra: fecha, motivo, diagnóstico, tratamientos y archivos adjuntos.
- Carga en menos de 3 segundos para historiales de hasta 100 consultas.
- Incluye número de HC y resumen de vacunas aplicadas.

**Subtareas:**
- Query SQL con JOIN entre `consultas`, `tratamientos` y `archivos_clinicos`.
- Endpoint PHP que retorna historial en JSON.
- Componente acordeón implementado en JS nativo.
- Optimizar query con índices en `mascota_id` y `fecha`.

---

### HU-18 | Dashboard principal + panel pendientes
- **Issue:** ZOOK-23
- **Estado:** Pendiente
- **Prioridad:** Alta

**Descripción:**
> Como *veterinario*, *quiero* ver un panel de resumen al iniciar sesión *para* tener visibilidad inmediata de citas del día, vacunaciones próximas y alertas.

**Criterios de aceptación:**
- Panel muestra: citas del día, vacunas próximas 7 días, desparasitaciones próximas.
- Accesos rápidos a funciones frecuentes: registrar consulta, buscar paciente, agendar cita.
- Carga en menos de 3 segundos.
- Datos filtrados según el usuario logueado.

**Subtareas:**
- Query SQL que agrupe citas del día (`fecha = CURDATE()`).
- Query de vacunas/desparasitaciones con `fecha_proxima` en los próximos 7 días.
- Endpoint PHP que retorne datos del dashboard en JSON.
- Vista HTML del dashboard con tarjetas y accesos directos.
- Implementar autenticación en el endpoint para no exponer datos sin sesión.

---

## Sprint 3

### HU-09 | Registrar vacunación
- **Issue:** ZOOK-13
- **Estado:** Pruebas
- **Prioridad:** Medium

**Descripción:**  
> Como *veterinario*, *quiero* registrar las vacunas aplicadas a una mascota *para* llevar control del esquema de vacunación.

**Criterios de aceptación:**
- Campos: nombre de vacuna, laboratorio, lote, fecha de aplicación y fecha de próxima dosis.
- Al guardar, la vacuna aparece en el calendario de la mascota.
- Se genera automáticamente una alerta para 7 días antes de la próxima dosis.
- El panel muestra todas las vacunaciones pendientes de la semana agrupadas por día.

**Subtareas:**
- Tabla `vacunas` con FK a `mascotas`.
- Clase `VacunaDAO` con `insert()` y `getPendientes()`.
- Query para obtener vacunas con `fecha_proxima` en los próximos 7 días.
- Vista de calendario con JS (resaltar días con eventos).
- Panel de pendientes de la semana en el dashboard.

---

### HU-10 | Enviar recordatorio por email
- **Issue:** ZOOK-14
- **Estado:** Pruebas
- **Prioridad:** Medium

**Descripción:**  
> Como *sistema*, *quiero* enviar emails automáticos al propietario *para* que no olvide las fechas de vacunación y desparasitación.

**Criterios de aceptación:**
- Email enviado 7 días y 1 día antes del vencimiento.
- Incluye nombre de la mascota, tipo de vacuna y fecha.
- No se envía si el propietario no tiene email registrado.
- Queda registro del envío en la BD (fecha, destinatario, tipo).

**Subtareas:**
- Instalar y configurar PHPMailer con cuenta SMTP del servidor.
- Script `send_reminders.php` que consulta vacunas próximas y envía emails.
- Plantilla HTML del correo de recordatorio.
- Tabla `notificaciones` para log de envíos.
- Configurar Cron Job: `0 8 * * * php /ruta/send_reminders.php`.

---

### HU-11 | Enviar recordatorio por WhatsApp
- **Issue:** ZOOK-15
- **Estado:** Pruebas
- **Prioridad:** Medium

**Descripción:**  
> Como *sistema*, *quiero* enviar mensajes de WhatsApp al propietario *para* aumentar la tasa de apertura de los recordatorios.

**Criterios de aceptación:**
- Mensaje enviado con el mismo contenido del email.
- Solo si el propietario tiene número de WhatsApp registrado.
- Usa la API oficial de WhatsApp Business (Cloud API de Meta).
- El envío queda registrado en la tabla `notificaciones`.

**Subtareas:**
- Configurar cuenta de WhatsApp Business y obtener token de API.
- Función PHP `sendWhatsApp($numero, $mensaje)` con cURL.
- Plantilla de mensaje aprobada en Meta Business Manager.
- Integrar función al script `send_reminders.php`.
- Prueba de envío en entorno real con número de prueba.

---

### HU-12 | Registrar desparasitación
- **Issue:** ZOOK-16
- **Estado:** Pruebas
- **Prioridad:** Medium

**Descripción:**  
> Como *veterinario*, *quiero* registrar y programar la desparasitación de la mascota *para* que el sistema genere alertas automáticas.

**Criterios de aceptación:**
- Tipos: interna y externa.
- Periodicidad configurable: mensual, trimestral o semestral.
- Al registrar, se calcula automáticamente la fecha de la próxima aplicación.
- Las alertas se comportan igual que las de vacunación.

**Subtareas:**
- Tabla `desparasitaciones` con campo `periodicidad` y `fecha_proxima`.
- Clase `DesparasitacionDAO` con `insert()` y cálculo automático de fecha próxima.
- Incluir desparasitaciones en el script de recordatorios.
- Mostrar en calendario junto con vacunas.

---

### HU-20 | Panel de vacunaciones pendientes (RF-15)
- **Issue:** ZOOK-25
- **Estado:** Pendiente
- **Prioridad:** Media

**Descripción:**
> Como *veterinario*, *quiero* ver un panel con las vacunaciones pendientes de la semana agrupadas por día y especie *para* planificar la agenda de atenciones proactivas.

**Criterios de aceptación:**
- Panel muestra vacunas con `fecha_proxima` en los próximos 7 días.
- Agrupación por día de la semana y por especie (canino, felino, etc.).
- Contador por día visible a primera vista.
- Clic en una entrada lleva a la ficha de la mascota.

**Subtareas:**
- Query SQL con `fecha_proxima BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY`.
- Endpoint PHP que retorne datos agrupados por día y especie.
- Vista HTML del panel integrada en el dashboard o como vista separada.
- Índices en `vacunas.fecha_proxima` y `mascotas.especie` para rendimiento.

---

## Sprint 4

### HU-13 | Agendar cita
- **Issue:** ZOOK-17
- **Estado:** Pruebas
- **Prioridad:** Medium

**Descripción:**  
> Como *recepcionista*, *quiero* agendar citas verificando la disponibilidad del veterinario *para* evitar cruces de horario.

**Criterios de aceptación:**
- Campos: fecha, hora, mascota, motivo y veterinario asignado.
- No permite dos citas al mismo veterinario en el mismo horario.
- Al crear, se envía email de confirmación al propietario automáticamente.
- Las citas aparecen en el calendario del dashboard.

**Subtareas:**
- Tabla `citas` con FK a `mascotas` y `usuarios` (veterinario).
- Query de verificación de disponibilidad antes de insertar.
- Clase `CitaDAO` con `insert()`, `checkDisponibilidad()` y `getByFecha()`.
- Integrar envío de email de confirmación con PHPMailer.
- Vista de agenda semanal en el dashboard.

---

### HU-14 | Cancelar o reprogramar cita
- **Issue:** ZOOK-18
- **Estado:** Pruebas
- **Prioridad:** Medium

**Descripción:**  
> Como *veterinario o recepcionista*, *quiero* cancelar o reprogramar una cita *para* mantener la agenda actualizada.

**Criterios de aceptación:**
- Se puede cancelar o cambiar fecha/hora de una cita existente.
- Al cambiar, se notifica al propietario por email automáticamente.
- Las citas canceladas no se eliminan; quedan con estado `cancelada`.
- No se puede reprogramar a un horario ya ocupado.

**Subtareas:**
- Campo `estado` en tabla `citas` (programada / cancelada / completada).
- Método `update()` en `CitaDAO`.
- Email de notificación de cambio con PHPMailer.
- Actualizar vista del calendario al cambiar estado.

---

### HU-15 | Portal del propietario
- **Issue:** ZOOK-19
- **Estado:** Pruebas
- **Prioridad:** Medium

**Descripción:**  
> Como *propietario de mascota*, *quiero* acceder a un portal web con la información de mi mascota *para* consultar su historial y próximas citas sin depender de la clínica.

**Criterios de aceptación:**
- Acceso con credenciales propias (email + contraseña).
- Solo ve las mascotas vinculadas a su cuenta.
- Puede ver: ficha de la mascota, historial de consultas, próximas citas y calendario de vacunas.
- No tiene acceso a datos de otros propietarios (error 403 si intenta).
- Interfaz simple y responsiva, optimizada para móvil.

**Subtareas:**
- Sistema de login separado para propietarios con sesiones PHP.
- Middleware PHP que valida rol antes de mostrar cada vista.
- Vistas HTML simplificadas con solo la info del propietario.
- Query que filtra mascotas por `propietario_id` de la sesión activa.
- Prueba de seguridad: intentar acceder a mascota de otro propietario.

---

### HU-16 | Generar reportes en PDF
- **Issue:** ZOOK-20
- **Estado:** Pruebas
- **Prioridad:** Medium

**Descripción:**  
> Como *veterinario*, *quiero* generar reportes exportables en PDF *para* tener resúmenes de la actividad de la clínica.

**Criterios de aceptación:**
- Reportes disponibles: listado de pacientes, citas del período y vacunaciones pendientes.
- Se puede filtrar por rango de fechas.
- El PDF se descarga directamente desde el navegador.
- El reporte incluye encabezado con nombre de la clínica y fecha de generación.

**Subtareas:**
- Instalar librería `TCPDF` o `FPDF` para generación de PDFs en PHP.
- Clase `ReporteController` con métodos por tipo de reporte.
- Formulario de filtros (fecha inicio / fecha fin / tipo).
- Plantilla de PDF con encabezado, tabla de datos y pie de página.
- Botón de descarga que dispara el script PHP de generación.

---

### HU-19 | Marcar cita como completada
- **Issue:** ZOOK-24
- **Estado:** Pendiente
- **Prioridad:** Media

**Descripción:**
> Como *veterinario o recepcionista*, *quiero* marcar una cita como completada *para* llevar control de atenciones realizadas y liberar la agenda.

**Criterios de aceptación:**
- Estado adicional `completada` en tabla `citas`.
- Solo citas en estado `programada` pueden marcarse como completadas.
- Al completar, se vincula opcionalmente el ID de la consulta clínica registrada.
- La cita completada aparece en el historial del día pero no en la agenda futura.

**Subtareas:**
- Agregar estado `completada` al enum/valores de `citas.estado`.
- Botón "Completar" en la vista de agenda con confirmación modal.
- Método `completar()` en `CitaDAO`.
- Actualizar dashboard para contar solo citas `programada`.

---

### HU-21 | Confirmación automática de cita por email (RF-18)
- **Issue:** ZOOK-27
- **Estado:** Pendiente
- **Prioridad:** Alta

**Descripción:**
> Como *sistema*, *quiero* enviar un email de confirmación automática al propietario en el momento de agendar la cita *para* que tenga constancia del horario y reduzca inasistencias.

**Criterios de aceptación:**
- Email enviado inmediatamente después de que la cita se guarde exitosamente.
- Incluye: fecha, hora, nombre de la mascota, motivo, dirección de la clínica.
- No bloquea la respuesta de la petición de agendado (envío asíncrono o en segundo plano).
- Si el email falla, se registra en `notificaciones` con estado `fallido`.

**Subtareas:**
- Integrar llamada a PHPMailer inmediatamente después de `CitaDAO.insert()`.
- Plantilla HTML de confirmación de cita.
- Tabla `notificaciones` registra envío con tipo `confirmacion_cita`.
- Manejo de errores de SMTP sin interrumpir el flujo del usuario.

---

### HU-22 | Gestión de usuarios del sistema
- **Issue:** ZOOK-26
- **Estado:** Pendiente
- **Prioridad:** Media

**Descripción:**
> Como *administrador*, *quiero* crear, editar y desactivar usuarios del sistema *para* gestionar quién tiene acceso y con qué permisos.

**Criterios de aceptación:**
- CRUD de usuarios: nombre, email, rol, estado (activo/inactivo).
- Solo usuarios con rol `admin` pueden acceder a este módulo.
- No se permite eliminar el único usuario admin.
- Cambio de contraseña con validación de fortaleza.

**Subtareas:**
- Vista HTML de gestión de usuarios con tabla y formulario.
- Clase `UsuarioDAO` con `insert()`, `update()`, `findAll()`, `softDelete()`.
- Validación de unicidad de email.
- Middleware que restrinja rutas de admin a rol `admin`.

---

### HU-23 | Backup automático de BD
- **Issue:** ZOOK-29
- **Estado:** Pendiente
- **Prioridad:** Media

**Descripción:**
> Como *admin del sistema*, *quiero* respaldar automáticamente la base de datos cada 24 horas *para* prevenir pérdida de información clínica.

**Criterios de aceptación:**
- Script PHP que ejecute `mysqldump` y comprima el archivo.
- Copia almacenada en directorio externo al servidor principal.
- Retención de últimos 7 backups (rotación automática).
- Registro de ejecución en log de sistema.

**Subtareas:**
- Script `backup.php` que genere dump con timestamp.
- Configurar Cron Job: `0 3 * * * php /ruta/backup.php`.
- Función de rotación que elimine backups más antiguos a 7 días.
- Validar que el backup generado no esté vacío/corrupto.

---

### HU-24 | Logs de auditoría y seguridad
- **Issue:** ZOOK-28
- **Estado:** Pendiente
- **Prioridad:** Media

**Descripción:**
> Como *admin del sistema*, *quiero* consultar un log de auditoría con operaciones críticas *para* detectar accesos no autorizados o cambios sospechosos.

**Criterios de aceptación:**
- Registro de: login exitoso/fallido, creación, edición, eliminación de registros.
- Campos: usuario, fecha/hora, IP, tipo de operación, tabla afectada, registro ID.
- Vista HTML filtrable por usuario, tipo de operación y rango de fechas.
- Logs inmutables (solo lectura para admin).

**Subtareas:**
- Tabla `auditoria_sistema` con campos de trazabilidad.
- Trigger o hook PHP que registre operaciones críticas automáticamente.
- Vista de logs con paginación y filtros.
- Exportación de logs a CSV para análisis externo.

---

### HU-25 | Auto-registro de propietario
- **Issue:** ZOOK-30
- **Estado:** Pendiente
- **Prioridad:** Alta

**Descripción:**
> Como *propietario / cliente nuevo*, *quiero* registrarme de manera autónoma en el sistema *para* poder acceder al portal y programar citas sin requerir una interacción física o llamada previa con la clínica.

**Criterios de aceptación:**
- Acceso público a un formulario de registro.
- Campos requeridos: documento de identidad, tipo de documento, nombre completo, teléfono y correo electrónico.
- Contraseña con requisitos de seguridad.
- Validación de que el documento o email no existan previamente.
- Una vez registrado, se crea su usuario con rol de propietario (rol 4) y se le inicia sesión automáticamente para redirigirlo a su portal.

**Subtareas:**
- Diseñar la vista de registro `views/auth/register.php`.
- Crear endpoint/método `register()` en `AuthController` para procesar el formulario de registro.
- Añadir validación del lado del cliente en JS y del lado del servidor en PHP.
- Generar token CSRF para el formulario de registro y validarlo.

---

### HU-26 | Agendar cita desde el portal
- **Issue:** ZOOK-31
- **Estado:** Pendiente
- **Prioridad:** Alta

**Descripción:**
> Como *propietario registrado*, *quiero* programar citas para mis mascotas directamente desde mi portal *para* gestionar su atención médica a mi conveniencia.

**Criterios de aceptación:**
- El propietario puede seleccionar una de sus mascotas asociadas.
- Selección de tipo de cita, veterinario, fecha y hora disponible.
- Se verifica dinámicamente la disponibilidad de horarios del veterinario (no permitir empalmes).
- Envío inmediato de correo de confirmación y registro en el panel de auditoría del sistema.

**Subtareas:**
- Diseñar interfaz de agendado dentro del portal en `views/portal/`.
- Crear endpoint asíncrono para verificar disponibilidad de veterinarios y horas libres.
- Procesar agendamiento en el backend y reutilizar lógica de confirmación automática de citas.

---

## Resumen por Estado

| Estado | Cantidad | Issues |
|--------|----------|--------|
| Finalizada | 2 | ZOOK-1, ZOOK-2 |
| Pruebas | 13 | ZOOK-3, ZOOK-5, ZOOK-6, ZOOK-7, ZOOK-8, ZOOK-13, ZOOK-14, ZOOK-15, ZOOK-16, ZOOK-17, ZOOK-18, ZOOK-19, ZOOK-20 |
| En curso | 4 | ZOOK-4, ZOOK-9, ZOOK-10, ZOOK-11, ZOOK-12 |
| Pendiente | 10 | HU-17, HU-18, HU-19, HU-20, HU-21, HU-22, HU-23, HU-24, HU-25, HU-26 |

*(Nota: ZOOK-3 también está en Pruebas.)*

---

## Sprints

| Sprint | Historias de Usuario |
|--------|----------------------|
| **Sprint 1** | HU-01, HU-02, HU-03, HU-04, HU-17 |
| **Sprint 2** | HU-05, HU-06, HU-07, HU-08, HU-18 |
| **Sprint 3** | HU-09, HU-10, HU-11, HU-12, HU-20 |
| **Sprint 4** | HU-13, HU-14, HU-15, HU-16, HU-19, HU-21, HU-22, HU-23, HU-24, HU-25, HU-26 |
