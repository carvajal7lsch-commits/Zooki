**FICHA TÉCNICA DEL PROYECTO**

***Sistema de Gestión Clínica para Veterinarias***

| **Nombre del Proyecto** | Zooki -- App de Registro y Seguimiento de Mascotas |
| --- | --- |
| **Propuesta N°** | 16 -- Competencia 220501094 |
| **Sector** | Salud Animal / Tecnología |
| **Tipo de Software** | Aplicación Web (SaaS) |
| **Institución** | SENA -- Centro Tecnológico de la Amazonia |
| **Programa Formativo** | Análisis y Desarrollo de Software (ADSO) |
| **Ficha** | 3142784 |
| **Metodología** | Scrum + Tablero Kanban |
| **N° de Sprints** | 4 Sprints × 1 semana |
| **Fecha de Inicio Estimada** | Abril 2026 |
| **Fecha de Entrega Estimada** | Mayo 2026 |
| **Versión del Documento** | 1.0.0 -- Abril 2026 |

# Descripción General del Proyecto

> Zooki es una aplicación web orientada a resolver la problemática de
> gestión manual que enfrentan las clínicas veterinarias de pequeña
> escala en Neiva, Huila. Actualmente estas clínicas llevan el registro
> de sus pacientes en fichas de cartón y agendas físicas, lo que genera
> pérdida de información, imposibilidad de seguimiento proactivo y
> riesgo para la salud animal.
>
> El sistema centraliza el historial clínico, automatiza los
> recordatorios de vacunación y desparasitación, gestiona la agenda de
> citas y habilita un portal digital para los propietarios de mascotas.

| **Problema que Resuelve** |
| --- |
| - Historias clínicas en papel → pérdida de datos y trazabilidad nula. |
| - Propietarios olvidan fechas de vacunación y desparasitación. |
| - La clínica no tiene mecanismos de seguimiento proactivo ni fidelización. |
| - No hay respaldo ni acceso remoto a la información del paciente. |

## Objetivo General

> Desarrollar una aplicación web para clínicas veterinarias que gestione
> el registro de mascotas, historias clínicas, calendario de vacunación,
> agenda de citas y envío automático de recordatorios a los
> propietarios, mejorando la calidad del servicio y la salud animal en
> Neiva.

## Objetivos Específicos

-   Implementar un módulo CRUD completo para el registro de mascotas y
    sus propietarios.

-   Desarrollar el módulo de historia clínica con soporte para archivos
    adjuntos (imágenes, radiografías).

-   Crear un calendario de vacunación y desparasitación con sistema de
    alertas automáticas por email y WhatsApp.

-   Construir una agenda de citas con control de disponibilidad del
    veterinario.

-   Habilitar un portal web de acceso para los propietarios, con vista
    de ficha y próximas citas.

-   Generar reportes exportables en PDF para la gestión interna de la
    clínica.

# Stack Tecnológico

> La siguiente tabla presenta las tecnologías propuestas para el
> desarrollo de Zooki, organizadas por capa de la arquitectura.

| **Capa** | **Tecnología / Herramienta** | * *Versió Refe rencia** | **Justificación** |
| --- | --- | --- | --- |
| **F rontend** | HTML5 + CSS3 | Estándar actual | Estructura semántica y estilos nativos del navegador. Sin dependencias externas; control total sobre el diseño. |
|  | JavaScript (ES6+) | Nativo | Lógica del cliente con módulos ES6, fetch API para peticiones al backend y manipulación del DOM. |
|  | CSS Grid + Flexbox | Nativo | Diseño responsivo sin necesidad de frameworks externos; layouts adaptativos para escritorio, tablet y móvil. |
|  | Fetch API / X MLHttpRequest | Nativo | Comunicación asíncrona co el backend PHP sin librerías adicionales. |
| ** Backend** | PHP nativo | 8.2+ | Lenguaje del servidor con arquitectura MVC implementada a mano. Dominio previo del equipo; sin curva de aprendizaje de frameworks. |
|  | PDO (PHP Data Objects) | Incluido en PHP | Capa de abstracción para consultas MySQL con soporte de sentencias preparadas; previene inyección SQL. |
|  | Sesiones PHP + JWT manual | Nativo | Control de autenticación y sesiones de usuario para el panel administrativo y el portal del propietario. |
|  | PHPMailer | 6.x | Librería liviana para envío de correos transaccionales vía SMTP: confirmaciones de cita y recordatorios. |
|  | cURL (PHP) | Incluido en PHP | Integración con la API de WhatsApp Business y cualquier servicio externo mediante peticiones HTTP. |
|  | Cron Jobs del servidor | Linux Cron | Tareas programadas en el servidor propio para el envío automático de recordatorios de vacunas y citas. |
| **Base de Datos** | MySQL | 8.x | Motor relacional robusto con soporte completo de integridad referencial, transacciones y índices. |
|  | Consultas SQL nativas vía PDO | --- | Control total sobre las consultas sin abstracción de ORM; mayor comprensión del modelo de datos. |

| **Capa** | **Tecnología / Herramienta** | * *Versió Refe rencia** | **Justificación** |
| --- | --- | --- | --- |
| **Alma cenamient o** | Sistema de archivos del servidor | --- | Almacenamiento de imágenes y archivos clínicos directamente en el servidor propio con control de acceso por PHP. |
| **S ervidor** | Apache / Nginx | 2.4 / 1.x | Servidor web del equipo propio. Apache con .htaccess para reescritura de URLs limpias y control de acceso a carpetas. |
|  | PHP-FPM | 8.2+ | Procesamiento eficiente de scripts PHP en el servidor; configuración de límites de subida de archivos. |
| **Notific aciones** | PHPMailer + SMTP propio | 6.x | Correos transaccionales enviados desde el servidor usando cuenta SMTP configurada. |
|  | WhatsApp Business API (cURL) | Cloud API | Envío de recordatorios ví API de Meta usando cURL desde PHP; plantillas de mensajes aprobadas. |
| * *DevOps** | Git + GitHub | --- | Control de versiones con ramas por sprint y pull requests para revisión de código. |
|  | FTP / SSH al servidor propio | --- | Despliegue directo al servidor del equipo mediante SSH o FTP seguro (SFTP). |
| ** Gestión** | Jira / Trello | --- | Tablero Kanban para gestión de sprints, backlog y seguimiento de tareas. |
|  | Figma | --- | Diseño de prototipos y wireframes de alta fidelidad antes del Sprint 1. |

# Arquitectura del Sistema

> Zooki sigue una arquitectura de **tres capas (3-Tier)** implementada
> con tecnologías nativas. El frontend en HTML/CSS/JS se comunica con el
> backend PHP mediante peticiones HTTP, que a su vez interactúa con
> MySQL usando PDO.

| **Capa de Presentación (Frontend -- HTML + CSS + JS)** | Vistas HTML con CSS propio para diseño responsivo (Grid + Flexbox). Dos contextos: (1) Panel administrativo para veterinario/recepcionista, (2) Portal externo para propietarios. JavaScript nativo (ES6+) con Fetch API para comunicación asíncrona con el backend. |
| --- | --- |
| **Capa de Lógica de Negocio (Backend -- PHP 8.2 nativo)** | Arquitectura MVC implementada a mano con PHP puro. Módulos: Auth (sesiones), Mascotas, Historia Clínica, Agenda, Notificaciones, Reportes. Cron Jobs del servidor para envío automático de recordatorios. PHPMailer para correos y cURL para integración con WhatsApp Business API. |
| **Capa de Datos (MySQL + PDO)** | Base de datos relacional MySQL con consultas SQL nativas vía PDO y sentencias preparadas. Entidades principales: Usuario, Propietario, Mascota, Consulta, Vacuna, Cita. Archivos clínicos almacenados en carpeta protegida del servidor propio. Backups programados con Cron Job + mysqldump. |

## Flujo de Comunicación

> **Usuario** → **HTTPS** → **HTML/CSS/JS (Frontend)** → **PHP Backend
> (MVC)** → **PDO** → **MySQL**
>
> Las notificaciones siguen el flujo: Cron Job (servidor) → Script PHP →
> PHPMailer (email) / cURL + WhatsApp API → Propietario.

## Patrones de Diseño Aplicados

| **Patrón** | **Aplicación en VetTrack Pro** |
| --- | --- |
| **MVC** | Carpetas separadas: /models (consultas PDO), /controllers (lógica), /views (HTML+PHP). |
| **Front Controller** | Archivo index.php como punto de entrada único; enrutamiento manual por parámetros GET. |
| **DAO (Data Access Object)** | Clases PHP por entidad (MascotaDAO, ConsultaDAO) que encapsulan todas las consultas SQL. |
| **Singleton** | Instancia única de conexión PDO reutilizada en toda la aplicación. |
| **Template Method** | Plantillas PHP base (header, footer, sidebar) incluidas en cada vista para consistencia visual. |

# Equipo de Trabajo

| **Rol Scrum** | **Perfil / Res ponsable** | **Responsabilidades Principales** | **Dedic ación** |
| --- | --- | --- | --- |
| **Product Owner** | Instructor SENA | Define y prioriza el Product Backlog. Valida los entregables de cada Sprint Review. Representa al cliente (veterinaria). | Parcial (revi siones) |
| **Scrum Master** | Líder del equipo ADSO | Facilita las ceremonias Scrum. Elimina impedimentos. Garantiza adherencia a la metodología y calidad del proceso. | C ompleta |
| **Dev -- F rontend** | Aprendiz ADSO | Desarrollo de vistas HTML/CSS/JS. Diseño responsivo del panel administrativo y portal del propietario. | C ompleta |
| **Dev -- Backend** | Aprendiz ADSO | Desarrollo del backend PHP con arquitectura MVC. Lógica de negocio, autenticación por sesiones, Cron Jobs y PHPMailer. | C ompleta |
| **Dev -- BD & DevOps** | Aprendiz ADSO | Diseño del modelo relacional MySQL. Clases DAO con PDO. Configuración del servidor Apache/Nginx y despliegue vía SSH/FTP. | C ompleta |
| **QA / Tester** | Aprendiz ADSO | Diseño y ejecución de casos de prueba funcionales y no funcionales. Reporte y seguimiento de bugs. | Parcial (Sprint 3-4) |

## Ceremonias Scrum

| **Ceremonia** | **Descripción y Duración** |
| --- | --- |
| **Sprint Planning** | Inicio de cada sprint. Se seleccionan las historias del backlog y se definen las tareas. Duración: 1 hora. |
| **Daily Standup** | Reunión diaria de 15 minutos. Cada miembro responde: ¿Qué hice ayer? ¿Qué haré hoy? ¿Hay impedimentos? |
| **Sprint Review** | Demostración del incremento funcional al Product Owner al final de cada sprint. Duración: 30 minutos. |
| **Sprint Re trospective** | Análisis de lo que funcionó, lo que falló y accion de mejora. Duración: 30 minutos. |

# Cronograma de Desarrollo

> El proyecto se divide en cuatro sprints de una semana, con entregables
> funcionales al final de cada iteración.

| ** Spri nt** | ** Sema na** | **Módulo / Enfoque** | **Actividades Clave** | * *Entregable** |
| --- | --- | --- | --- | --- |
| **Sp rint 1** | 1 | Registro de Mascotas y Propietarios | - Diseño BD y modelo ER • CRUD mascotas y propietarios • Autenticación JWT • Búsqueda en tiempo real • Pruebas unitarias módulo 1 | App con registro y búsqueda funcional |
| **Sp rint 2** | 2 | Historia Clínica y Consultas | - Módulo de consultas • Registro de tratamientos • Subida de archivos clínicos • Vista cronológica historial - Pruebas integración | Historia clínica adjuntos operativa |
| **Sp rint 3** | 3 | Calendario, Vacunas y R ecordatorios | - Módulo de vacunación • Desparasitación programada • Cro Jobs en servidor • Integración PHPMailer (email) • Integración WhatsApp API (cURL) | Alertas automáti enviándose correctamente |
| **Sp rint 4** | 4 | Portal Propietario, Citas y Reportes | - Agenda de citas � Portal externo propietario • Generación reportes PDF • Pruebas de aceptación (UAT) • Despliegue en producción | Sistema completo desplegado y validado |

## Hitos del Proyecto

| **Hi to** | **Nombre** | **Sem ana** | **Criterio de Completitud** |
| --- | --- | --- | --- |
| H-01 | **Fin Sprint 1** | Sem. 1 | CRUD de mascotas funcional, autenticación operativa |
| H-02 | **Fin Sprint 2** | Sem. 2 | Historia clínica con archivos adjuntos |
| H-03 | **Fin Sprint 3** | Sem. 3 | Recordatorios automáticos probados en producción |
| H-04 | **Fin Sprint 4** | Sem. 4 | Sistema completo, UAT aprobada, entregado al PO |

# Análisis de Riesgos

> Se identificaron los principales riesgos del proyecto, evaluados por
> probabilidad e impacto, con sus respectivas estrategias de mitigación.

| ** ID ** | * *Descripción** | * *P ro b. ** | **I mpac to** | **N ive l** | **Estrategia de Mitigación** |
| --- | --- | --- | --- | --- | --- |
| R- 01 | Retrasos en el desarrollo por subestimación de tareas | ** Al ta ** | **Al to** | ** Crí tic o** | Dividir tareas en subtareas ≤ 4 horas en el Sprint Planning. Revisar velocidad del equipo en Daily. |
| R- 02 | Integración con WhatsApp Business API denegada o demorada | * *M ed ia ** | **Al to** | ** Alt o** | Preparar cuenta de prueba con anticipación. Tener email como canal alternativo confirmado desde Sprint 1. |
| R- 03 | Pérdida de datos por falla en el servidor de BD | ** Ba ja ** | * *Muy Al to** | ** Alt o** | Configurar backups diarios automatizados desde el Sprint 1. Probar restauración en Sprint 3. |
| R- 04 | Baja adopción por parte del veterinario (resistencia al cambio) | * *M ed ia ** | **Al to** | ** Alt o** | Involucrar al usuario real desde Sprint 2 para validaciones tempranas. Diseñar UI lo más intuitiva posible. |
| R- 05 | Vu lnerabilidades de seguridad (acceso no autorizado a historias clínicas) | ** Ba ja ** | * *Muy Al to** | ** Alt o** | Implementar RBAC desde Sprint 1. Auditoría de rutas en Sprint 2. Pruebas de penetración básicas en Sprint 4. |
| R- 06 | Problemas de compatibilidad entre navegadores | ** Ba ja ** | * *Med io** | **M edi o** | Definir navegadores soportados en Sprint 1. Pruebas cross-browser al final de cada sprint. |
| R- 07 | Carga excesiva de archivos clínicos colapsa el almacenamiento | ** Ba ja ** | * *Med io** | **M edi o** | Limitar tamaño de archivo a 10 MB. Configurar upload_max_filesize y post_max_size en php.ini desde Sprint 2. |
| R- 08 | Cambio de requisitos a mitad del proyecto | * *M ed ia ** | **Al to** | ** Alt o** | Gestionar cambios solo entre sprints a través del Product Owner. Congelar requisitos al inicio de cada sprint. |

| **Leyenda de Niveles de Riesgo** |
| --- |
| - Crítico / Alto: Requiere plan de acción inmediato y seguimiento semanal. |
| - Medio: Monitorear en cada Sprint Review; activar plan si aumenta probabilidad. |
| - Bajo: Registrar y revisar al final de cada sprint. |

# Presupuesto Estimado

> El proyecto se desarrolla en el marco formativo del SENA, por lo que
> los costos de recurso humano se valoran a modo referencial (según
> tarifas de mercado junior en Colombia). Los costos de infraestructura
> corresponden a servicios con planes gratuitos o de bajo costo.

## Recurso Humano

| **Rol** | * *N°** ** Perso nas** | * *Hora s/Spr int** | ** Spr int s** | ** Total Ho ras** | **Tarifa Ref. (COP/h)** | **Valor Total (COP)** |
| --- | --- | --- | --- | --- | --- | --- |
| Scrum Master | 1 | 10 | 4 | 40 | \$ 25.000 | \$ 1.000.000 |
| D esarrollador Frontend | 1 | 40 | 4 | 160 | \$ 20.000 | \$ 3.200.000 |
| D esarrollador Backend | 1 | 40 | 4 | 160 | \$ 20.000 | \$ 3.200.000 |
| Dev BD & DevOps | 1 | 30 | 4 | 120 | \$ 20.000 | \$ 2.400.000 |
| QA / Tester | 1 | 20 | 2 | 40 | \$ 18.000 | \$ 720.000 |
| **TOTAL RECURSO HUMANO** |  |  |  | 520 h |  | \$ 1 0.520.000 |

## Infraestructura y Herramientas

| **Recurso / Servicio** | **Plan / Tier** | **Costo Mensual (COP)** | * *Mes es** | **Total (COP)** |
| --- | --- | --- | --- | --- |
| Servidor propio (hosting) | Inf raestructura propia | \$ 0 | 1 | \$ 0 |
| MySQL (BD) | Incluido en servidor | \$ 0 | 1 | \$ 0 |
| Cloudinary (almacenamiento) | Free (25 GB) | \$ 0 | 1 | \$ 0 |
| PHPMailer (email) | Open source | \$ 0 | 1 | \$ 0 |
| WhatsApp Business API | Cloud API (Meta) | Variable\* | 1 | \~ \$ 15.000 |
| GitHub (repositorio) | Free | \$ 0 | 1 | \$ 0 |
| Figma (diseño UI) | Free | \$ 0 | 1 | \$ 0 |
| **TOTAL INFRAESTRUCTURA** |  |  |  | \~ \$ 40.000 |

> \* Costo aproximado basado en 200 mensajes de prueba en el mes de
> desarrollo.

## Resumen de Presupuesto Total

##  {#section .unnumbered}

| **Categoría** | **Valor Estimado (COP)** | **Observación** |
| --- | --- | --- |
| **Recurso Humano (referencial)** | \$ 10.520.000 | Valorado a tarifas junior de mercado. En contexto SENA es sin costo directo. |
| **Infraestructura y Herramientas** | \~ \$ 40.000 | Servicios cloud con planes gratuitos para prototipo académico. |
| **Imprevistos (10%)** | \~ \$ 1.056.000 | Reserva para contingencias técnicas o de infraestructura. |
| **TOTAL GENERAL** | \~ \$ 11.616.000 | Presupuesto referencial para presentación del proyecto formativo. |

# Resultados Esperados e Impacto

| **Impacto Esperado en la Clínica Veterinaria** |
| --- |
| - Reducción del 90% en el uso de papel para gestión de historias clínicas. |
| - Disminución de citas y vacunaciones olvidadas gracias a recordatorios automáticos. |
| - Mayor fidelización de propietarios mediante el portal de acceso a fichas digitales. |
| - Trazabilidad completa de la salud de cada paciente desde su primera consulta. |
| - Ahorro de tiempo en búsqueda y consulta de información vs. gestión manual. |

## Indicadores de Éxito

| **Indicador** | **Meta al Finalizar el Sprint 4** |
| --- | --- |
| **RF Alta implementados** | 100% de los 11 requisitos funcionales de alta prioridad. |
| **RNF Alta implementados** | 100% de los 6 requisitos no funcionales de alta prioridad. |
| **Cobertura de pruebas** | Mínimo 60% de cobertura unitaria en backend. |
| **Tiempo de respuesta** | \< 3 segundos para búsquedas y consultas con red ≥ 5 Mbps. |
| **D isponibilidad** | ≥ 95% en horario de operación durante la semana de UAT. |
| **Usabilidad** | Usuario nuevo registra mascota y agenda cita en \< 10 minutos. |
| **Defectos críticos** | 0 defectos Severity 1 abiertos en entrega final. |

## Entregables Finales del Proyecto

-   Código fuente completo en repositorio GitHub con README técnico.

-   Aplicación desplegada en el servidor propio accesible por IP o
    dominio configurado.

-   Base de datos con datos de prueba (mínimo 10 mascotas, 20 consultas,
    5 propietarios).

-   Documento ERS IEEE 830 v1.0.

-   Esta Ficha Técnica del Proyecto v1.0.

-   Manual de usuario básico (PDF y/o integrado en el sistema, mínimo 10
    páginas).

-   Presentación ejecutiva del proyecto (PowerPoint/PDF, mínimo 12
    diapositivas).

-   Evidencias de pruebas: casos de prueba ejecutados, reporte de bugs
    cerrados.

*Documento elaborado por el equipo de desarrollo ADSO -- Ficha3142784 \|
SENA Florencia, Caquetá \| abril 2026*
