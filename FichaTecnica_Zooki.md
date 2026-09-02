# Ficha Técnica del Proyecto — Zooki

> **Revisión 2.0** · Documento alineado con el sistema en producción (v1.8.0) · Sistema de Gestión Clínica para Veterinarias · SENA ADSO — Ficha 3142784

## Identificación del Proyecto

| Campo | Valor |
|---|---|
| Nombre del Proyecto | Zooki — App de Registro y Seguimiento de Mascotas |
| Propuesta N° | 16 — Competencia 220501094 |
| Sector | Salud Animal / Tecnología |
| Tipo de Software | Aplicación Web (SaaS) |
| Institución | SENA — Centro Tecnológico de la Amazonia (Florencia, Caquetá) |
| Mercado objetivo | Clínicas veterinarias de pequeña escala en Neiva, Huila |
| Programa Formativo | Análisis y Desarrollo de Software (ADSO) |
| Ficha | 3142784 |
| Metodología | Scrum + Tablero Kanban |
| N.° de Sprints (línea base) | 4 sprints × 1 semana |
| Estado actual | En producción — versión 1.8.0 (evolución continua) |
| Versión del documento | 2.0 — Agosto 2026 |

## 1. Descripción General del Proyecto

Zooki es una aplicación web orientada a resolver la gestión manual que enfrentan las clínicas veterinarias de pequeña escala en Neiva, Huila. Actualmente estas clínicas llevan el registro en fichas de cartón y agendas físicas, lo que genera pérdida de información, imposibilidad de seguimiento proactivo y riesgo para la salud animal. El sistema centraliza la historia clínica, automatiza los recordatorios de vacunación y desparasitación, gestiona la agenda de citas y habilita un portal digital para que los propietarios se registren de forma autónoma.

**Problema que resuelve:**

- Historias clínicas en papel → pérdida de datos y trazabilidad nula.
- Los propietarios olvidan las fechas de vacunación y desparasitación.
- La clínica no tiene mecanismos de seguimiento proactivo ni de fidelización.
- No hay respaldo ni acceso remoto a la información del paciente.

## 2. Objetivos

**Objetivo general.** Desarrollar una aplicación web para clínicas veterinarias que gestione el registro de mascotas, historias clínicas, calendario de vacunación, agenda de citas y envío automático de recordatorios a los propietarios, mejorando la calidad del servicio y la salud animal en Neiva.

**Objetivos específicos:**

- Implementar un módulo CRUD completo para el registro de mascotas y propietarios.
- Desarrollar el módulo de historia clínica con soporte para archivos adjuntos.
- Crear un calendario de vacunación y desparasitación con alertas automáticas por correo.
- Construir una agenda de citas con control de disponibilidad del veterinario y horarios configurables.
- Habilitar un portal web con auto-registro para los propietarios.
- Ofrecer autenticación local y federada (Google) con control de acceso por roles.
- Generar reportes exportables en PDF.

## 3. Stack Tecnológico

| Capa | Tecnología / Herramienta | Versión | Justificación |
|---|---|---|---|
| Frontend | HTML5 + CSS3 (Grid + Flexbox) | Estándar | Diseño responsivo sin frameworks; control total del diseño. |
| Frontend | JavaScript (ES6+) + Fetch API | Nativo | Lógica de cliente y comunicación asíncrona sin librerías. |
| Frontend | FullCalendar.js | 6.x | Renderizado del calendario de citas y vacunas. |
| Backend | PHP nativo (MVC a mano) | 8.2+ | Dominio previo; MVC sin curva de aprendizaje de frameworks. |
| Backend | PDO (PHP Data Objects) | Incluido | Consultas con sentencias preparadas; previene inyección SQL. |
| Backend | Sesiones PHP | Nativo | Autenticación y control de sesión (panel y portal). |
| Backend | Google Identity (OAuth 2.0) + cURL | Cloud | Inicio de sesión federado con Google. |
| Backend | PHPMailer | 7.x | Correos transaccionales vía SMTP. |
| Backend | Cron del servidor | Linux | Recordatorios automáticos y respaldos. |
| Base de Datos | MySQL | 8.x | Motor relacional con integridad, transacciones e índices. |
| Almacenamiento | Sistema de archivos del servidor | — | Imágenes y archivos clínicos en carpeta protegida (.htaccess). |
| Servidor | Apache / Nginx + PHP-FPM | 2.4 / 8.2 | Servidor propio (VPS Linux) con URLs limpias. |
| DevOps | Git + GitHub + GitHub Actions (CI) | — | Control de versiones e integración continua. |
| DevOps | Docker + SSH/SFTP | — | Contenedores y despliegue al servidor propio. |
| Pruebas | PHPUnit | 10.5 | Pruebas unitarias y de integración en CI. |

## 4. Arquitectura del Sistema

Zooki sigue una arquitectura de **tres capas (3-Tier)** con tecnologías nativas.

| Capa | Descripción |
|---|---|
| Presentación (Frontend) | Vistas HTML con CSS propio y diseño responsivo. Dos contextos: panel administrativo (admin, veterinario, recepcionista) y portal externo (propietarios). JavaScript nativo con Fetch API. |
| Lógica de Negocio (Backend PHP 8.2) | MVC a mano. Módulos: Auth (sesiones + Google OAuth), Mascotas, Historia Clínica, Agenda, Horarios, Notificaciones, Reportes y Auditoría. Cron para recordatorios y respaldos; PHPMailer para correo. |
| Datos (MySQL + PDO) | 24 tablas con sentencias preparadas. Archivos clínicos en carpeta protegida del servidor. Respaldos con cron + mysqldump. |

**Flujo de comunicación.** Usuario → HTTPS → HTML/CSS/JS (Frontend) → PHP Backend (MVC) → PDO → MySQL. Notificaciones: Cron → Script PHP → PHPMailer (correo) → Propietario.

## 5. Patrones de Diseño Aplicados

| Patrón | Aplicación en Zooki |
|---|---|
| MVC | Carpetas separadas: /models (consultas PDO), /controllers (lógica) y /views (HTML + PHP). |
| Front Controller | `public/index.php` como punto de entrada único; enrutamiento manual. |
| DAO (Data Access Object) | Clases por entidad (Mascota, Consulta, Cita, etc.) que encapsulan las consultas SQL. |
| Singleton | Instancia única de la conexión PDO reutilizada en toda la aplicación. |
| Template Method | Plantillas PHP base (header, footer, layout) incluidas en cada vista. |

## 6. Equipo de Trabajo

Zooki es un proyecto de desarrollo **individual**. Una sola persona (el aprendiz) asume la totalidad de los roles; los roles Scrum se declaran con fines formativos. El Product Owner corresponde al instructor SENA.

| Rol Scrum | Responsable | Responsabilidades | Dedicación |
|---|---|---|---|
| Product Owner | Instructor SENA | Prioriza el backlog y valida los entregables; representa al cliente. | Parcial |
| Scrum Master | Juan S. Carvajal | Facilita las ceremonias, gestiona el tablero y la metodología. | Completa |
| Development Team (Full-Stack) | Juan S. Carvajal | Frontend, backend PHP/MVC, modelo de datos MySQL, DevOps. | Completa |
| QA / Tester | Juan S. Carvajal | Pruebas funcionales, unitarias y de integración; reporte de bugs. | Completa |

## 7. Cronograma de Desarrollo

La línea base se organizó en cuatro sprints de una semana. Posteriormente el producto evolucionó por versiones (v1.4 a v1.7).

| Sprint | Módulo / Enfoque | Entregable |
|---|---|---|
| 1 | Registro de Mascotas y Propietarios | App con registro y búsqueda funcional |
| 2 | Historia Clínica y Consultas | Historia clínica con adjuntos operativa |
| 3 | Calendario, Vacunas y Recordatorios | Alertas automáticas por correo funcionando |
| 4 | Portal, Citas y Reportes | Sistema completo desplegado y validado |

## 8. Análisis de Riesgos

| ID | Descripción | Prob. | Impacto | Nivel | Mitigación |
|---|---|---|---|---|---|
| R-01 | Retrasos por subestimación de tareas. | Alta | Alto | Crítico | Subtareas ≤ 4 h en el Sprint Planning; revisar velocidad en el Daily. |
| R-02 | Integración con WhatsApp Business API denegada o demorada. | Media | Alto | Alto | Mitigado: el correo se adoptó como canal principal; WhatsApp se difirió a futuro. |
| R-03 | Pérdida de datos por falla del servidor de BD. | Baja | Muy Alto | Alto | Respaldos diarios con cron + mysqldump; pruebas de restauración. |
| R-04 | Baja adopción por resistencia al cambio. | Media | Alto | Alto | Involucrar al usuario real en validaciones tempranas; UI intuitiva. |
| R-05 | Acceso no autorizado a historias clínicas. | Baja | Muy Alto | Alto | RBAC desde el Sprint 1; auditoría; CSRF y sentencias preparadas. |
| R-06 | Incompatibilidad entre navegadores. | Baja | Medio | Medio | Navegadores soportados definidos; pruebas cross-browser. |
| R-07 | Carga excesiva de archivos clínicos. | Baja | Medio | Medio | Límite de 10 MB por archivo; ajustar php.ini. |
| R-08 | Cambio de requisitos a mitad del proyecto. | Media | Alto | Alto | Gestionar cambios solo entre sprints vía el Product Owner. |

## 9. Presupuesto Estimado

Proyecto formativo desarrollado por un único aprendiz; los costos de recurso humano son **referenciales** (valoración de mercado del esfuerzo por especialidad si se contratara externamente).

**Recurso humano (referencial).** Scrum Master, Frontend, Backend, BD & DevOps y QA: **520 h — $ 10.520.000 COP** (valoración de mercado; en contexto SENA, sin costo directo).

**Infraestructura y herramientas.** Servidor propio, MySQL, almacenamiento en filesystem, PHPMailer/SMTP, GitHub + CI y Figma: en su mayoría gratuitos o propios. Reserva referencial de ~$ 15.000 COP para la evolución futura de WhatsApp (no forma parte de la línea base).

**Resumen.** Recurso humano ~$ 10.520.000 · Infraestructura ~$ 40.000 · Imprevistos (10%) ~$ 1.056.000 · **Total general referencial ~$ 11.616.000 COP.**

## 10. Resultados Esperados e Impacto

- Reducción cercana al 90% en el uso de papel para historias clínicas.
- Disminución de citas y vacunaciones olvidadas gracias a los recordatorios automáticos.
- Mayor fidelización de propietarios mediante el portal de acceso a fichas digitales.
- Trazabilidad completa de la salud de cada paciente.
- Ahorro de tiempo frente a la gestión manual.

**Indicadores de éxito:** 100% de RF y RNF de prioridad Alta implementados; ≥ 60% de cobertura de pruebas en backend; < 3 s de respuesta con red ≥ 5 Mbps; ≥ 95% de disponibilidad en UAT; registro de mascota y agendamiento en < 10 min; 0 defectos críticos abiertos en la entrega.

## 11. Estado Actual del Sistema (v1.8.0)

Tras completar la línea base, Zooki continuó evolucionando y hoy está en producción:

- **v1.4** — Correos con imágenes CID, bienvenida automática, inicio de sesión con Google y diseño responsivo.
- **v1.5** — Modales optimizados, bloqueo de solapamientos de citas y mapeo de tipos de cita.
- **v1.6** — Optimización de agenda, exportación de reportes en PDF y edición de contacto en el perfil.
- **v1.7** — Landing page, portal de documentación integrado y migración del MER a un diagrama Mermaid versionable.

**Funcionalidades adicionales más allá del plan original:** autenticación con Google (OAuth 2.0), notificaciones internas por rol, doble auditoría (mascotas y sistema), catálogos paramétricos y horarios de clínica configurables.

## 12. Entregables Finales

- Código fuente completo en repositorio GitHub con README técnico.
- Aplicación desplegada en el servidor propio.
- Base de datos con datos de prueba.
- Documento ERS conforme a IEEE 830-1998 (Revisión 2.0).
- Esta Ficha Técnica del Proyecto (Revisión 2.0).
- Reglas de Negocio, Historias de Usuario y Requisitos Específicos.
- Análisis de Vacíos de Diseño y Casos Borde.
- Manual de usuario y presentación ejecutiva.

_Documento elaborado por Juan Sebastián Carvajal Ome — Ficha 3142784 · SENA, Florencia (Caquetá) · 2026. Uso académico._
