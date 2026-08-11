**FICHA TÉCNICA DEL PROYECTO**

***Sistema de Gestión Clínica para Veterinarias***

+---------------------+------------------------------------------------+
| > **Nombre del      | > Zooki -- App de Registro y Seguimiento de    |
| > Proyecto**        | > Mascotas                                     |
+=====================+================================================+
| > **Propuesta N°**  | > 16 -- Competencia 220501094                  |
+---------------------+------------------------------------------------+
| > **Sector**        | > Salud Animal / Tecnología                    |
+---------------------+------------------------------------------------+
| > **Tipo de         | > Aplicación Web (SaaS)                        |
| > Software**        |                                                |
+---------------------+------------------------------------------------+
| > **Institución**   | > SENA -- Centro Tecnológico de la Amazonia    |
+---------------------+------------------------------------------------+
| > **Programa        | > Análisis y Desarrollo de Software (ADSO)     |
| > Formativo**       |                                                |
+---------------------+------------------------------------------------+
| > **Ficha**         | > 3142784                                      |
+---------------------+------------------------------------------------+
| > **Metodología**   | > Scrum + Tablero Kanban                       |
+---------------------+------------------------------------------------+
| > **N° de Sprints** | > 4 Sprints × 1 semana                         |
+---------------------+------------------------------------------------+
| > **Fecha de Inicio | > Abril 2026                                   |
| > Estimada**        |                                                |
+---------------------+------------------------------------------------+
| > **Fecha de        | > Mayo 2026                                    |
| > Entrega           |                                                |
| > Estimada**        |                                                |
+---------------------+------------------------------------------------+
| > **Versión del     | > 1.0.0 -- Abril 2026                          |
| > Documento**       |                                                |
+---------------------+------------------------------------------------+

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

+-----------------------------------------------------------------------+
| > **Problema que Resuelve**                                           |
+=======================================================================+
| -   Historias clínicas en papel → pérdida de datos y trazabilidad     |
|     nula.                                                             |
+-----------------------------------------------------------------------+
| -   Propietarios olvidan fechas de vacunación y desparasitación.      |
+-----------------------------------------------------------------------+
| -   La clínica no tiene mecanismos de seguimiento proactivo ni        |
|     fidelización.                                                     |
+-----------------------------------------------------------------------+
| -   No hay respaldo ni acceso remoto a la información del paciente.   |
+-----------------------------------------------------------------------+

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

+-----------+---------------+----------+------------------------------+
| **Capa**  | >             | > *      | **Justificación**            |
|           |  **Tecnología | *Versión |                              |
|           | > /           | > Refe   |                              |
|           | >             | rencia** |                              |
|           | Herramienta** |          |                              |
+===========+===============+==========+==============================+
| > **F     | > HTML5 +     | >        | > Estructura semántica y     |
| rontend** | > CSS3        | Estándar | > estilos nativos del        |
|           |               | > actual | > navegador. Sin             |
|           |               |          | > dependencias externas;     |
|           |               |          | > control total sobre el     |
|           |               |          | > diseño.                    |
+-----------+---------------+----------+------------------------------+
|           | > JavaScript  | Nativo   | > Lógica del cliente con     |
|           | > (ES6+)      |          | > módulos ES6, fetch API     |
|           |               |          | > para peticiones al backend |
|           |               |          | > y manipulación del DOM.    |
+-----------+---------------+----------+------------------------------+
|           | > CSS Grid +  | Nativo   | > Diseño responsivo sin      |
|           | > Flexbox     |          | > necesidad de frameworks    |
|           |               |          | > externos; layouts          |
|           |               |          | > adaptativos para           |
|           |               |          | > escritorio, tablet y       |
|           |               |          | > móvil.                     |
+-----------+---------------+----------+------------------------------+
|           | > Fetch API / | Nativo   | > Comunicación asíncrona con |
|           | > X           |          | > el backend PHP sin         |
|           | MLHttpRequest |          | > librerías adicionales.     |
+-----------+---------------+----------+------------------------------+
| > **      | > PHP nativo  | 8.2+     | > Lenguaje del servidor con  |
| Backend** |               |          | > arquitectura MVC           |
|           |               |          | > implementada a mano.       |
|           |               |          | > Dominio previo del equipo; |
|           |               |          | > sin curva de aprendizaje   |
|           |               |          | > de frameworks.             |
+-----------+---------------+----------+------------------------------+
|           | > PDO (PHP    | >        | > Capa de abstracción para   |
|           | > Data        | Incluido | > consultas MySQL con        |
|           | >             | > en PHP | > soporte de sentencias      |
|           | > Objects)    |          | > preparadas; previene       |
|           |               |          | > inyección SQL.             |
+-----------+---------------+----------+------------------------------+
|           | > Sesiones    | Nativo   | > Control de autenticación y |
|           | > PHP + JWT   |          | > sesiones de usuario para   |
|           | > manual      |          | > el panel administrativo y  |
|           |               |          | > el portal del propietario. |
+-----------+---------------+----------+------------------------------+
|           | > PHPMailer   | 6.x      | > Librería liviana para      |
|           |               |          | > envío de correos           |
|           |               |          | > transaccionales vía SMTP:  |
|           |               |          | > confirmaciones de cita y   |
|           |               |          | > recordatorios.             |
+-----------+---------------+----------+------------------------------+
|           | > cURL (PHP)  | >        | > Integración con la API de  |
|           |               | Incluido | > WhatsApp Business y        |
|           |               | > en PHP | > cualquier servicio externo |
|           |               |          | > mediante peticiones HTTP.  |
+-----------+---------------+----------+------------------------------+
|           | > Cron Jobs   | Linux    | > Tareas programadas en el   |
|           | > del         | Cron     | > servidor propio para el    |
|           | > servidor    |          | > envío automático de        |
|           |               |          | > recordatorios de vacunas y |
|           |               |          | > citas.                     |
+-----------+---------------+----------+------------------------------+
| > **Base  | > MySQL       | 8.x      | > Motor relacional robusto   |
| > de      |               |          | > con soporte completo de    |
| > Datos** |               |          | > integridad referencial,    |
|           |               |          | > transacciones y índices.   |
+-----------+---------------+----------+------------------------------+
|           | > Consultas   | ---      | > Control total sobre las    |
|           | > SQL nativas |          | > consultas sin abstracción  |
|           | > vía PDO     |          | > de ORM; mayor comprensión  |
|           |               |          | > del modelo de datos.       |
+-----------+---------------+----------+------------------------------+

+-----------+---------------+----------+------------------------------+
| **Capa**  | >             | > *      | **Justificación**            |
|           |  **Tecnología | *Versión |                              |
|           | > /           | > Refe   |                              |
|           | >             | rencia** |                              |
|           | Herramienta** |          |                              |
+===========+===============+==========+==============================+
| > **Alma  | > Sistema de  | ---      | > Almacenamiento de imágenes |
| cenamient | > archivos    |          | > y archivos clínicos        |
| > o**     | > del         |          | > directamente en el         |
|           | > servidor    |          | > servidor propio con        |
|           |               |          | > control de acceso por PHP. |
+-----------+---------------+----------+------------------------------+
| > **S     | > Apache /    | > 2.4 /  | > Servidor web del equipo    |
| ervidor** | > Nginx       | > 1.x    | > propio. Apache con         |
|           |               |          | >                            |
|           |               |          | > .htaccess para reescritura |
|           |               |          | > de URLs limpias y control  |
|           |               |          | > de acceso a carpetas.      |
+-----------+---------------+----------+------------------------------+
|           | > PHP-FPM     | 8.2+     | > Procesamiento eficiente de |
|           |               |          | > scripts PHP en el          |
|           |               |          | > servidor; configuración de |
|           |               |          | > límites de subida de       |
|           |               |          | > archivos.                  |
+-----------+---------------+----------+------------------------------+
| >         | > PHPMailer + | 6.x      | > Correos transaccionales    |
| **Notific | > SMTP propio |          | > enviados desde el servidor |
| aciones** |               |          | > usando cuenta SMTP         |
|           |               |          | > configurada.               |
+-----------+---------------+----------+------------------------------+
|           | > WhatsApp    | Cloud    | > Envío de recordatorios vía |
|           | > Business    | API      | > API de Meta usando cURL    |
|           | > API (cURL)  |          | > desde PHP; plantillas de   |
|           |               |          | > mensajes aprobadas.        |
+-----------+---------------+----------+------------------------------+
| > *       | > Git +       | ---      | > Control de versiones con   |
| *DevOps** | > GitHub      |          | > ramas por sprint y pull    |
|           |               |          | > requests para revisión de  |
|           |               |          | > código.                    |
+-----------+---------------+----------+------------------------------+
|           | > FTP / SSH   | ---      | > Despliegue directo al      |
|           | > al servidor |          | > servidor del equipo        |
|           | > propio      |          | > mediante SSH o FTP seguro  |
|           |               |          | > (SFTP).                    |
+-----------+---------------+----------+------------------------------+
| > **      | > Jira /      | ---      | > Tablero Kanban para        |
| Gestión** | > Trello      |          | > gestión de sprints,        |
|           |               |          | > backlog y seguimiento de   |
|           |               |          | > tareas.                    |
+-----------+---------------+----------+------------------------------+
|           | > Figma       | ---      | > Diseño de prototipos y     |
|           |               |          | > wireframes de alta         |
|           |               |          | > fidelidad antes del Sprint |
|           |               |          | > 1.                         |
+-----------+---------------+----------+------------------------------+

# Arquitectura del Sistema

> Zooki sigue una arquitectura de **tres capas (3-Tier)** implementada
> con tecnologías nativas. El frontend en HTML/CSS/JS se comunica con el
> backend PHP mediante peticiones HTTP, que a su vez interactúa con
> MySQL usando PDO.

+------------------+---------------------------------------------------+
| > **Capa de      | > Vistas HTML con CSS propio para diseño          |
| > Presentación   | > responsivo (Grid + Flexbox). Dos contextos: (1) |
| > (Frontend --   | > Panel administrativo para                       |
| > HTML + CSS +   | > veterinario/recepcionista, (2) Portal externo   |
| > JS)**          | > para propietarios. JavaScript nativo (ES6+) con |
|                  | > Fetch API para comunicación asíncrona con el    |
|                  | > backend.                                        |
+==================+===================================================+
| > **Capa de      | > Arquitectura MVC implementada a mano con PHP    |
| > Lógica de      | > puro. Módulos: Auth (sesiones), Mascotas,       |
| > Negocio        | > Historia Clínica, Agenda, Notificaciones,       |
| > (Backend --    | > Reportes. Cron Jobs del servidor para envío     |
| > PHP 8.2        | > automático de recordatorios. PHPMailer para     |
| > nativo)**      | > correos y cURL para integración con WhatsApp    |
|                  | > Business API.                                   |
+------------------+---------------------------------------------------+
| > **Capa de      | > Base de datos relacional MySQL con consultas    |
| > Datos (MySQL + | > SQL nativas vía PDO y sentencias preparadas.    |
| > PDO)**         | > Entidades principales: Usuario, Propietario,    |
|                  | > Mascota, Consulta, Vacuna, Cita. Archivos       |
|                  | > clínicos almacenados en carpeta protegida del   |
|                  | > servidor propio. Backups programados con Cron   |
|                  | > Job + mysqldump.                                |
+------------------+---------------------------------------------------+

## Flujo de Comunicación

> **Usuario** → **HTTPS** → **HTML/CSS/JS (Frontend)** → **PHP Backend
> (MVC)** → **PDO** → **MySQL**
>
> Las notificaciones siguen el flujo: Cron Job (servidor) → Script PHP →
> PHPMailer (email) / cURL + WhatsApp API → Propietario.

## Patrones de Diseño Aplicados

+-----------------+-----------------------------------------------------+
| **Patrón**      | **Aplicación en VetTrack Pro**                      |
+=================+=====================================================+
| > **MVC**       | > Carpetas separadas: /models (consultas PDO),      |
|                 | > /controllers (lógica), /views (HTML+PHP).         |
+-----------------+-----------------------------------------------------+
| > **Front       | > Archivo index.php como punto de entrada único;    |
| > Controller**  | > enrutamiento manual por parámetros GET.           |
+-----------------+-----------------------------------------------------+
| > **DAO (Data   | > Clases PHP por entidad (MascotaDAO, ConsultaDAO)  |
| > Access        | > que encapsulan todas las consultas SQL.           |
| > Object)**     |                                                     |
+-----------------+-----------------------------------------------------+
| > **Singleton** | > Instancia única de conexión PDO reutilizada en    |
|                 | > toda la aplicación.                               |
+-----------------+-----------------------------------------------------+
| > **Template    | > Plantillas PHP base (header, footer, sidebar)     |
| > Method**      | > incluidas en cada vista para consistencia visual. |
+-----------------+-----------------------------------------------------+

# Equipo de Trabajo

+-----------+------------+-----------------------------------+---------+
| > **Rol   | > **Perfil | > **Responsabilidades             | **Dedic |
| > Scrum** | > /        | > Principales**                   | ación** |
|           | > Res      |                                   |         |
|           | ponsable** |                                   |         |
+===========+============+===================================+=========+
| >         | >          | > Define y prioriza el Product    | >       |
| **Product | Instructor | > Backlog. Valida los entregables | Parcial |
| > Owner** | > SENA     | > de cada Sprint Review.          | > (revi |
|           |            | > Representa al cliente           | siones) |
|           |            | > (veterinaria).                  |         |
+-----------+------------+-----------------------------------+---------+
| > **Scrum | > Líder    | > Facilita las ceremonias Scrum.  | C       |
| >         | > del      | > Elimina impedimentos. Garantiza | ompleta |
|  Master** | > equipo   | > adherencia a la metodología y   |         |
|           | > ADSO     | > calidad del proceso.            |         |
+-----------+------------+-----------------------------------+---------+
| > **Dev   | > Aprendiz | > Desarrollo de vistas            | C       |
| > --      | > ADSO     | > HTML/CSS/JS. Diseño responsivo  | ompleta |
| > F       |            | > del panel administrativo y      |         |
| rontend** |            | > portal del propietario.         |         |
+-----------+------------+-----------------------------------+---------+
| > **Dev   | > Aprendiz | > Desarrollo del backend PHP con  | C       |
| > --      | > ADSO     | > arquitectura MVC. Lógica de     | ompleta |
| >         |            | > negocio, autenticación por      |         |
| Backend** |            | > sesiones, Cron Jobs y           |         |
|           |            | > PHPMailer.                      |         |
+-----------+------------+-----------------------------------+---------+
| > **Dev   | > Aprendiz | > Diseño del modelo relacional    | C       |
| > -- BD & | > ADSO     | > MySQL. Clases DAO con PDO.      | ompleta |
| >         |            | > Configuración del servidor      |         |
|  DevOps** |            | > Apache/Nginx y despliegue vía   |         |
|           |            | > SSH/FTP.                        |         |
+-----------+------------+-----------------------------------+---------+
| > **QA /  | > Aprendiz | > Diseño y ejecución de casos de  | >       |
| >         | > ADSO     | > prueba funcionales y no         | Parcial |
|  Tester** |            | > funcionales. Reporte y          | >       |
|           |            | > seguimiento de bugs.            | (Sprint |
|           |            |                                   | > 3-4)  |
+-----------+------------+-----------------------------------+---------+

## Ceremonias Scrum

+---------------+------------------------------------------------------+
| >             | > **Descripción y Duración**                         |
| **Ceremonia** |                                                      |
+===============+======================================================+
| > **Sprint    | > Inicio de cada sprint. Se seleccionan las          |
| > Planning**  | > historias del backlog y se definen las tareas.     |
|               | > Duración: 1 hora.                                  |
+---------------+------------------------------------------------------+
| > **Daily     | > Reunión diaria de 15 minutos. Cada miembro         |
| > Standup**   | > responde: ¿Qué hice ayer? ¿Qué haré hoy? ¿Hay      |
|               | > impedimentos?                                      |
+---------------+------------------------------------------------------+
| > **Sprint    | > Demostración del incremento funcional al Product   |
| > Review**    | > Owner al final de cada sprint. Duración: 30        |
|               | > minutos.                                           |
+---------------+------------------------------------------------------+
| > **Sprint    | Análisis de lo que funcionó, lo que falló y acciones |
| > Re          | de mejora. Duración: 30 minutos.                     |
| trospective** |                                                      |
+---------------+------------------------------------------------------+

# Cronograma de Desarrollo

> El proyecto se divide en cuatro sprints de una semana, con entregables
> funcionales al final de cada iteración.

+------+------+--------------+-----------------------+---------------+
| **   | **   | > **Módulo / | > **Actividades       | > *           |
| Spri | Sema | > Enfoque**  | > Clave**             | *Entregable** |
| nt** | na** |              |                       |               |
+======+======+==============+=======================+===============+
| **Sp | 1    | > Registro   | -   Diseño BD y       | > App con     |
| rint |      | > de         |     modelo ER • CRUD  | > registro y  |
| 1**  |      | > Mascotas y |     mascotas y        | > búsqueda    |
|      |      | >            |     propietarios •    | > funcional   |
|      |      | Propietarios |     Autenticación JWT |               |
|      |      |              |     • Búsqueda en     |               |
|      |      |              |     tiempo real •     |               |
|      |      |              |     Pruebas unitarias |               |
|      |      |              |     módulo 1          |               |
+------+------+--------------+-----------------------+---------------+
| **Sp | 2    | > Historia   | -   Módulo de         | > Historia    |
| rint |      | > Clínica y  |     consultas •       | > clínica con |
| 2**  |      | > Consultas  |     Registro de       | > adjuntos    |
|      |      |              |     tratamientos •    | > operativa   |
|      |      |              |     Subida de         |               |
|      |      |              |     archivos clínicos |               |
|      |      |              |     • Vista           |               |
|      |      |              |     cronológica       |               |
|      |      |              |     historial         |               |
|      |      |              |                       |               |
|      |      |              | -   Pruebas           |               |
|      |      |              |     > integración     |               |
+------+------+--------------+-----------------------+---------------+
| **Sp | 3    | >            | -   Módulo de         | > Alertas     |
| rint |      |  Calendario, |     vacunación •      | > automáticas |
| 3**  |      | > Vacunas y  |     Desparasitación   | > enviándose  |
|      |      | > R          |     programada • Cron | >             |
|      |      | ecordatorios |     Jobs en servidor  | correctamente |
|      |      |              |     • Integración     |               |
|      |      |              |     PHPMailer (email) |               |
|      |      |              |     • Integración     |               |
|      |      |              |     WhatsApp API      |               |
|      |      |              |     (cURL)            |               |
+------+------+--------------+-----------------------+---------------+
| **Sp | 4    | > Portal     | -   Agenda de citas • | > Sistema     |
| rint |      | >            |     Portal externo    | > completo    |
| 4**  |      | Propietario, |     propietario •     | > desplegado  |
|      |      | > Citas y    |     Generación        | > y validado  |
|      |      | > Reportes   |     reportes PDF •    |               |
|      |      |              |     Pruebas de        |               |
|      |      |              |     aceptación (UAT)  |               |
|      |      |              |     • Despliegue en   |               |
|      |      |              |     producción        |               |
+------+------+--------------+-----------------------+---------------+

## Hitos del Proyecto

+------+-------------+-------+---------------------------------------+
| **Hi | >           | **Sem | > **Criterio de Completitud**         |
| to** |  **Nombre** | ana** |                                       |
+======+=============+=======+=======================================+
| H-01 | > **Fin     | Sem.  | > CRUD de mascotas funcional,         |
|      | > Sprint    | 1     | > autenticación operativa             |
|      | > 1**       |       |                                       |
+------+-------------+-------+---------------------------------------+
| H-02 | > **Fin     | Sem.  | > Historia clínica con archivos       |
|      | > Sprint    | 2     | > adjuntos                            |
|      | > 2**       |       |                                       |
+------+-------------+-------+---------------------------------------+
| H-03 | > **Fin     | Sem.  | > Recordatorios automáticos probados  |
|      | > Sprint    | 3     | > en producción                       |
|      | > 3**       |       |                                       |
+------+-------------+-------+---------------------------------------+
| H-04 | > **Fin     | Sem.  | > Sistema completo, UAT aprobada,     |
|      | > Sprint    | 4     | > entregado al PO                     |
|      | > 4**       |       |                                       |
+------+-------------+-------+---------------------------------------+

# Análisis de Riesgos

> Se identificaron los principales riesgos del proyecto, evaluados por
> probabilidad e impacto, con sus respectivas estrategias de mitigación.

+----+----------------+----+------+-----+----------------------------+
| ** | > *            | *  | >    | **N | > **Estrategia de          |
| ID | *Descripción** | *P |  **I | ive | > Mitigación**             |
| ** |                | ro | mpac | l** |                            |
|    |                | b. | to** |     |                            |
|    |                | ** |      |     |                            |
+====+================+====+======+=====+============================+
| R- | > Retrasos en  | ** | **Al | **  | > Dividir tareas en        |
| 01 | > el           | Al | to** | Crí | > subtareas ≤ 4 horas en   |
|    | >              | ta |      | tic | > el Sprint Planning.      |
|    | > desarrollo   | ** |      | o** | > Revisar velocidad del    |
|    | > por          |    |      |     | > equipo en Daily.         |
|    | >              |    |      |     |                            |
|    |  subestimación |    |      |     |                            |
|    | > de tareas    |    |      |     |                            |
+----+----------------+----+------+-----+----------------------------+
| R- | > Integración  | *  | **Al | **  | > Preparar cuenta de       |
| 02 | > con WhatsApp | *M | to** | Alt | > prueba con anticipación. |
|    | > Business API | ed |      | o** | > Tener email como canal   |
|    | > denegada o   | ia |      |     | > alternativo confirmado   |
|    | > demorada     | ** |      |     | > desde Sprint 1.          |
+----+----------------+----+------+-----+----------------------------+
| R- | > Pérdida de   | ** | > *  | **  | > Configurar backups       |
| 03 | > datos por    | Ba | *Muy | Alt | > diarios automatizados    |
|    | > falla en el  | ja | > Al | o** | > desde el Sprint 1.       |
|    | > servidor de  | ** | to** |     | > Probar restauración en   |
|    | > BD           |    |      |     | > Sprint 3.                |
+----+----------------+----+------+-----+----------------------------+
| R- | > Baja         | *  | **Al | **  | > Involucrar al usuario    |
| 04 | > adopción por | *M | to** | Alt | > real desde Sprint 2 para |
|    | > parte del    | ed |      | o** | > validaciones tempranas.  |
|    | > veterinario  | ia |      |     | > Diseñar UI lo más        |
|    | > (resistencia | ** |      |     | > intuitiva posible.       |
|    | > al cambio)   |    |      |     |                            |
+----+----------------+----+------+-----+----------------------------+
| R- | > Vu           | ** | > *  | **  | > Implementar RBAC desde   |
| 05 | lnerabilidades | Ba | *Muy | Alt | > Sprint 1. Auditoría de   |
|    | > de seguridad | ja | > Al | o** | > rutas en Sprint 2.       |
|    | > (acceso no   | ** | to** |     | > Pruebas de penetración   |
|    | > autorizado a |    |      |     | > básicas en Sprint 4.     |
|    | > historias    |    |      |     |                            |
|    | > clínicas)    |    |      |     |                            |
+----+----------------+----+------+-----+----------------------------+
| R- | > Problemas de | ** | *    | **M | > Definir navegadores      |
| 06 | >              | Ba | *Med | edi | > soportados en Sprint 1.  |
|    | compatibilidad | ja | io** | o** | > Pruebas cross-browser al |
|    | > entre        | ** |      |     | > final de cada sprint.    |
|    | > navegadores  |    |      |     |                            |
+----+----------------+----+------+-----+----------------------------+
| R- | > Carga        | ** | *    | **M | > Limitar tamaño de        |
| 07 | > excesiva de  | Ba | *Med | edi | > archivo a 10 MB.         |
|    | > archivos     | ja | io** | o** | > Configurar               |
|    | > clínicos     | ** |      |     | > upload_max_filesize y    |
|    | >              |    |      |     | > post_max_size en php.ini |
|    | > colapsa el   |    |      |     | > desde Sprint 2.          |
|    | >              |    |      |     |                            |
|    | >              |    |      |     |                            |
|    | almacenamiento |    |      |     |                            |
+----+----------------+----+------+-----+----------------------------+
| R- | > Cambio de    | *  | **Al | **  | > Gestionar cambios solo   |
| 08 | > requisitos a | *M | to** | Alt | > entre sprints a través   |
|    | > mitad del    | ed |      | o** | > del Product Owner.       |
|    | > proyecto     | ia |      |     | > Congelar requisitos al   |
|    |                | ** |      |     | > inicio de cada sprint.   |
+----+----------------+----+------+-----+----------------------------+

+-----------------------------------------------------------------------+
| > **Leyenda de Niveles de Riesgo**                                    |
+=======================================================================+
| -   Crítico / Alto: Requiere plan de acción inmediato y seguimiento   |
|     semanal.                                                          |
+-----------------------------------------------------------------------+
| -   Medio: Monitorear en cada Sprint Review; activar plan si aumenta  |
|     probabilidad.                                                     |
+-----------------------------------------------------------------------+
| -   Bajo: Registrar y revisar al final de cada sprint.                |
+-----------------------------------------------------------------------+

# Presupuesto Estimado

> El proyecto se desarrolla en el marco formativo del SENA, por lo que
> los costos de recurso humano se valoran a modo referencial (según
> tarifas de mercado junior en Colombia). Los costos de infraestructura
> corresponden a servicios con planes gratuitos o de bajo costo.

## Recurso Humano

+--------------+-------+-------+-----+-------+-----------+-----------+
| **Rol**      | *     | > *   | **  | > **  | >         | > **Valor |
|              | *N°** | *Hora | Spr | Total |  **Tarifa | > Total   |
|              |       | s/Spr | int | > Ho  | > Ref.    | > (COP)** |
|              | **    | >     | s** | ras** | >         |           |
|              | Perso | int** |     |       | (COP/h)** |           |
|              | nas** |       |     |       |           |           |
+==============+=======+=======+=====+=======+===========+===========+
| > Scrum      | 1     | 10    | 4   | 40    | \$ 25.000 | \$        |
| > Master     |       |       |     |       |           | 1.000.000 |
+--------------+-------+-------+-----+-------+-----------+-----------+
| > D          | 1     | 40    | 4   | 160   | \$ 20.000 | \$        |
| esarrollador |       |       |     |       |           | 3.200.000 |
| > Frontend   |       |       |     |       |           |           |
+--------------+-------+-------+-----+-------+-----------+-----------+
| > D          | 1     | 40    | 4   | 160   | \$ 20.000 | \$        |
| esarrollador |       |       |     |       |           | 3.200.000 |
| > Backend    |       |       |     |       |           |           |
+--------------+-------+-------+-----+-------+-----------+-----------+
| > Dev BD &   | 1     | 30    | 4   | 120   | \$ 20.000 | \$        |
| > DevOps     |       |       |     |       |           | 2.400.000 |
+--------------+-------+-------+-----+-------+-----------+-----------+
| > QA /       | 1     | 20    | 2   | 40    | \$ 18.000 | \$        |
| > Tester     |       |       |     |       |           | 720.000   |
+--------------+-------+-------+-----+-------+-----------+-----------+
| > **TOTAL    |       |       |     | 520 h |           | \$        |
| > RECURSO    |       |       |     |       |           | 1         |
| > HUMANO**   |       |       |     |       |           | 0.520.000 |
+--------------+-------+-------+-----+-------+-----------+-----------+

## Infraestructura y Herramientas

+----------------------+--------------+------------+------+----------+
| > **Recurso /        | **Plan /     | > **Costo  | *    | **Total  |
| > Servicio**         | Tier**       | > Mensual  | *Mes | (COP)**  |
|                      |              | > (COP)**  | es** |          |
+======================+==============+============+======+==========+
| > Servidor propio    | Inf          | \$ 0       | 1    | \$ 0     |
| > (hosting)          | raestructura |            |      |          |
|                      | propia       |            |      |          |
+----------------------+--------------+------------+------+----------+
| > MySQL (BD)         | Incluido en  | \$ 0       | 1    | \$ 0     |
|                      | servidor     |            |      |          |
+----------------------+--------------+------------+------+----------+
| > Cloudinary         | Free (25 GB) | \$ 0       | 1    | \$ 0     |
| > (almacenamiento)   |              |            |      |          |
+----------------------+--------------+------------+------+----------+
| > PHPMailer (email)  | Open source  | \$ 0       | 1    | \$ 0     |
+----------------------+--------------+------------+------+----------+
| > WhatsApp Business  | Cloud API    | Variable\* | 1    | \~ \$    |
| > API                | (Meta)       |            |      | 15.000   |
+----------------------+--------------+------------+------+----------+
| > GitHub             | Free         | \$ 0       | 1    | \$ 0     |
| > (repositorio)      |              |            |      |          |
+----------------------+--------------+------------+------+----------+
| > Figma (diseño UI)  | Free         | \$ 0       | 1    | \$ 0     |
+----------------------+--------------+------------+------+----------+
| > **TOTAL            |              |            |      | \~ \$    |
| > INFRAESTRUCTURA**  |              |            |      | 40.000   |
+----------------------+--------------+------------+------+----------+

> \* Costo aproximado basado en 200 mensajes de prueba en el mes de
> desarrollo.

## Resumen de Presupuesto Total

##  {#section .unnumbered}

+-------------------+--------------+-----------------------------------+
| **Categoría**     | > **Valor    | **Observación**                   |
|                   | > Estimado   |                                   |
|                   | > (COP)**    |                                   |
+===================+==============+===================================+
| > **Recurso       | \$           | > Valorado a tarifas junior de    |
| > Humano          | 10.520.000   | > mercado. En contexto SENA es    |
| > (referencial)** |              | > sin costo directo.              |
+-------------------+--------------+-----------------------------------+
| >                 | \~ \$ 40.000 | > Servicios cloud con planes      |
| **Infraestructura |              | > gratuitos para prototipo        |
| > y               |              | > académico.                      |
| > Herramientas**  |              |                                   |
+-------------------+--------------+-----------------------------------+
| **Imprevistos     | \~ \$        | > Reserva para contingencias      |
| (10%)**           | 1.056.000    | > técnicas o de infraestructura.  |
+-------------------+--------------+-----------------------------------+
| **TOTAL GENERAL** | \~ \$        | > Presupuesto referencial para    |
|                   | 11.616.000   | > presentación del proyecto       |
|                   |              | > formativo.                      |
+-------------------+--------------+-----------------------------------+

# Resultados Esperados e Impacto

+-----------------------------------------------------------------------+
| > **Impacto Esperado en la Clínica Veterinaria**                      |
+=======================================================================+
| -   Reducción del 90% en el uso de papel para gestión de historias    |
|     clínicas.                                                         |
+-----------------------------------------------------------------------+
| -   Disminución de citas y vacunaciones olvidadas gracias a           |
|     recordatorios automáticos.                                        |
+-----------------------------------------------------------------------+
| -   Mayor fidelización de propietarios mediante el portal de acceso a |
|     fichas digitales.                                                 |
+-----------------------------------------------------------------------+
| -   Trazabilidad completa de la salud de cada paciente desde su       |
|     primera consulta.                                                 |
+-----------------------------------------------------------------------+
| -   Ahorro de tiempo en búsqueda y consulta de información vs.        |
|     gestión manual.                                                   |
+-----------------------------------------------------------------------+

## Indicadores de Éxito

+-----------------+-----------------------------------------------------+
| > **Indicador** | **Meta al Finalizar el Sprint 4**                   |
+=================+=====================================================+
| > **RF Alta     | > 100% de los 11 requisitos funcionales de alta     |
| >               | > prioridad.                                        |
| implementados** |                                                     |
+-----------------+-----------------------------------------------------+
| > **RNF Alta    | > 100% de los 6 requisitos no funcionales de alta   |
| >               | > prioridad.                                        |
| implementados** |                                                     |
+-----------------+-----------------------------------------------------+
| > **Cobertura   | > Mínimo 60% de cobertura unitaria en backend.      |
| > de pruebas**  |                                                     |
+-----------------+-----------------------------------------------------+
| > **Tiempo de   | > \< 3 segundos para búsquedas y consultas con red  |
| > respuesta**   | > ≥ 5 Mbps.                                         |
+-----------------+-----------------------------------------------------+
| > **D           | > ≥ 95% en horario de operación durante la semana   |
| isponibilidad** | > de UAT.                                           |
+-----------------+-----------------------------------------------------+
| >               | > Usuario nuevo registra mascota y agenda cita en   |
|  **Usabilidad** | > \< 10 minutos.                                    |
+-----------------+-----------------------------------------------------+
| > **Defectos    | > 0 defectos Severity 1 abiertos en entrega final.  |
| > críticos**    |                                                     |
+-----------------+-----------------------------------------------------+

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
