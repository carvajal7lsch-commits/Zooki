# Ficha Técnica del Proyecto — Zooki

> **Revisión 3.1** · Documento alineado con la plataforma **v2.0** (SaaS multi-inquilino) · Sistema de Gestión Clínica Veterinaria · SENA ADSO — Ficha 3142784

## Identificación del Proyecto

| Campo | Valor |
|---|---|
| Nombre del Proyecto | Zooki — Plataforma de Gestión Clínica Veterinaria |
| Propuesta N° | 16 — Competencia 220501094 |
| Sector | Salud Animal / Tecnología |
| Tipo de Software | Aplicación Web **SaaS multi-inquilino** |
| Institución | SENA — Centro Tecnológico de la Amazonia (Florencia, Caquetá) |
| Mercado objetivo | Clínicas veterinarias de pequeña escala en Colombia |
| Programa Formativo | Análisis y Desarrollo de Software (ADSO) |
| Ficha | 3142784 |
| Metodología | Scrum + Tablero Kanban |
| Estado actual | v1.12.0 en producción · **v2.0 en construcción** (especificación aprobada) |
| Versión del documento | 3.1 — Octubre 2026 |

## 1. Descripción General del Proyecto

Zooki nació para resolver la gestión manual de las clínicas veterinarias de pequeña escala, que llevan el registro en fichas de cartón y agendas físicas, con la consiguiente pérdida de información, falta de seguimiento proactivo y riesgo para la salud animal. Las versiones 1.x resolvieron esto para una sola clínica (instalación única): historia clínica centralizada, recordatorios automáticos de vacunación y desparasitación, agenda de citas y portal del propietario.

La **versión 2.0** transforma Zooki en una **plataforma SaaS multi-inquilino**: una única instalación atiende a muchas clínicas con sus datos aislados entre sí (por `id_clinica`), con registro autónomo de clínicas y suscripción por planes (freemium). Además incorpora **dos capacidades diferenciadoras basadas en grafos**: un soporte a la decisión clínica (Grafo I) y un agendamiento inteligente (Grafo II), junto con el propietario como identidad multi-clínica, el carnet digital con QR y la reputación de veterinarios.

**Problema que resuelve:**

- Historias clínicas en papel → pérdida de datos y trazabilidad nula.
- Los propietarios olvidan las fechas de vacunación y desparasitación.
- La clínica no tiene seguimiento proactivo ni fidelización.
- No hay respaldo ni acceso remoto a la información del paciente.
- Una clínica pequeña no tiene presupuesto ni personal técnico para instalar, mantener y respaldar su propio software y servidor.
- El diagnóstico y la prescripción dependen solo de la memoria del veterinario: una toxicidad por especie o una interacción entre fármacos puede pasar inadvertida.
- La agenda es rígida: un retraso o una urgencia descuadra todas las citas siguientes (efecto cascada) y no hay forma de atender primero al paciente más grave.

## 2. Objetivos

**Objetivo general.** Desarrollar una plataforma web SaaS multi-inquilino para clínicas veterinarias que gestione el registro de mascotas, historias clínicas, vacunación, agenda de citas y recordatorios automáticos, incorporando soporte a la decisión clínica y agendamiento inteligente basados en grafos, para mejorar la calidad del servicio y la salud animal.

**Objetivos específicos:**

- Implementar el registro de mascotas y propietarios y la historia clínica con archivos adjuntos.
- Crear el calendario de vacunación y desparasitación con alertas automáticas por correo.
- Construir la agenda de citas con disponibilidad real del veterinario y horarios configurables.
- Habilitar el portal del propietario con auto-registro.
- Ofrecer autenticación local y federada (Google) con control de acceso por roles.
- **(v2.0)** Operar como plataforma multi-inquilino con aislamiento por `id_clinica`, registro autónomo de clínicas y suscripción por planes (freemium) administrada por un super-administrador.
- **(v2.0)** Incorporar el **Grafo I** (soporte a la decisión clínica): sugerencia de diagnósticos por peso y alertas de toxicidad/interacción al prescribir.
- **(v2.0)** Incorporar el **Grafo II** (agendamiento inteligente): triage de 4 niveles, reajuste de agenda en cascada, ausencias y cobertura del veterinario.
- **(v2.0)** Habilitar el propietario como identidad global multi-clínica, el carnet digital con QR de emergencia y la reputación de veterinarios.

## 3. Stack Tecnológico

Se decidió **mantener el stack nativo** de la v1.x por ser suficiente a la escala objetivo; la v2.0 no migra de tecnología, sino que la extiende (aislamiento por `id_clinica`, índices compuestos y recorrido de los grafos en memoria).

| Capa | Tecnología / Herramienta | Versión | Justificación |
|---|---|---|---|
| Frontend | HTML5 + CSS3 (Grid + Flexbox) | Estándar | Diseño responsivo sin frameworks; control total del diseño. |
| Frontend | JavaScript (ES6+) + Fetch API | Nativo | Lógica de cliente y comunicación asíncrona sin librerías. |
| Frontend | FullCalendar.js | 6.x | Renderizado del calendario de citas y vacunas. |
| Backend | PHP nativo (MVC a mano) | 8.2+ | Dominio previo; MVC sin curva de framework. |
| Backend | PDO (PHP Data Objects) | Incluido | Sentencias preparadas; previene inyección SQL. |
| Backend | Sesiones PHP | Nativo | Autenticación y control de sesión (panel y portal). |
| Backend | Resolución de inquilino (`id_clinica`) | Propio | **(v2.0)** Aislamiento multi-inquilino centralizado en `Security` y la capa de modelos. |
| Backend | Motores de grafos (en memoria) | Propio | **(v2.0)** Grafo I (decisión clínica) y Grafo II (agenda) recorridos como listas de adyacencia. |
| Backend | Google Identity (OAuth 2.0) + cURL | Cloud | Inicio de sesión federado con Google. |
| Backend | PHPMailer | 7.x | Correos transaccionales vía SMTP. |
| Backend | Cron del servidor | Linux | Recordatorios, vencimiento de suscripciones y respaldos. |
| Base de Datos | MySQL | 8.x | Motor relacional con integridad, transacciones e índices; filas aisladas por `id_clinica`. |
| Almacenamiento | Sistema de archivos del servidor | — | Imágenes y archivos clínicos en carpeta protegida. |
| Servidor | Apache (mod_php) en contenedor Docker, detrás de Traefik | 2.4 / PHP 8.2 | Servidor propio (VPS Linux) administrado con Dokploy; URLs limpias con mod_rewrite y HTTPS en el proxy. |
| DevOps | Git + GitHub + Docker + Dokploy | — | Control de versiones, contenedores y despliegue. |
| Pruebas | PHPUnit | 10.x | Pruebas unitarias y de integración. |

## 4. Arquitectura del Sistema

Zooki v2.0 es una aplicación web de **tres capas (3-Tier)**, autónoma y con tecnologías nativas, que opera como **plataforma multi-inquilino**: una única instancia atiende a muchas clínicas con sus datos aislados mediante `id_clinica`, y un **super-administrador** administra la plataforma por encima de las clínicas. El detalle visual está en el [diagrama de componentes](Modelos.md#arquitectura--diagrama-de-componentes) del documento de Modelos.

| Capa | Descripción |
|---|---|
| Presentación (Frontend) | Vistas HTML con CSS propio y diseño responsivo. Dos contextos: panel de la clínica (administrador, veterinario) y portal del propietario; además, páginas públicas y el carnet QR de solo lectura. JavaScript nativo con Fetch API. |
| Lógica de Negocio (Backend PHP 8.2) | MVC a mano con **front controller** (`public/index.php`) y `Security` (rol + CSRF + resolución de `id_clinica`) antes de cada acción. Módulos: Plataforma/Suscripción, Auth, Mascotas, Historia Clínica (con Grafo I), Agenda (con Grafo II), Horarios, Ausencias, Notificaciones, Reputación y Auditoría. Los dos grafos se recorren **en memoria**. Cron para recordatorios, vencimiento de suscripciones y respaldos; PHPMailer para correo. |
| Datos (MySQL + PDO) | Modelo relacional con las tablas de negocio **aisladas por `id_clinica`**, más las entidades nuevas de clínicas, planes, suscripciones, grafos y reseñas. Sentencias preparadas; archivos clínicos en carpeta protegida; respaldos con cron. |

**Flujo de comunicación.** Usuario → HTTPS → Front controller + `Security` (rol/CSRF/`id_clinica`) → Controladores (MVC) → lógica de dominio (Grafo I / Grafo II / servicios) → PDO → MySQL. Notificaciones: Cron → Script PHP → PHPMailer → Propietario.

## 5. Patrones de Diseño Aplicados

| Patrón | Aplicación en Zooki |
|---|---|
| MVC | Carpetas separadas: /models (consultas PDO), /controllers (lógica) y /views (HTML + PHP). |
| Front Controller | `public/index.php` como punto de entrada único; enrutamiento manual y resolución de inquilino. |
| DAO (Data Access Object) | Clases por entidad que encapsulan las consultas SQL (siempre filtradas por `id_clinica`). |
| Singleton | Instancia única de la conexión PDO reutilizada en toda la aplicación. |
| Template Method | Plantillas PHP base (header, footer, layout) incluidas en cada vista. |
| Multi-inquilino (fila por `id_clinica`) | **(v2.0)** Toda operación de negocio filtra por `id_clinica`; su omisión se considera defecto de seguridad. |
| Grafo con lista de adyacencia | **(v2.0)** El conocimiento clínico (Grafo I) y la agenda (Grafo II) se modelan como grafos y se recorren en memoria. |

## 6. Equipo de Trabajo

Zooki es un proyecto de desarrollo **individual**. Una sola persona (el aprendiz) asume todos los roles de Scrum, que se declaran con fines formativos. El instructor SENA no forma parte del equipo Scrum: revisa y evalúa los entregables, pero no define ni prioriza el backlog.

| Rol Scrum | Responsable | Responsabilidades | Dedicación |
|---|---|---|---|
| Product Owner | Juan S. Carvajal | Define la visión del producto, redacta y prioriza el backlog (historias de usuario) y acepta los incrementos. | Completa |
| Scrum Master | Juan S. Carvajal | Facilita las ceremonias, gestiona el tablero y la metodología. | Completa |
| Development Team (Full-Stack) | Juan S. Carvajal | Frontend, backend PHP/MVC, modelo de datos MySQL, DevOps. | Completa |
| QA / Tester | Juan S. Carvajal | Pruebas funcionales, unitarias y de integración; reporte de bugs. | Completa |
| Evaluador (externo a Scrum) | Instructor SENA | Revisa y valida los entregables formativos y da retroalimentación. No interviene en el backlog ni en las decisiones del producto. | Puntual |

## 7. Cronograma de Desarrollo

La línea base de la v1.x se organizó en cuatro sprints de una semana; luego el producto evolucionó por versiones (v1.1 → v1.11.0). La **v2.0** se aborda con un enfoque **documentación primero** antes de construir, en fases:

| Fase / Sprint | Enfoque | Entregable |
|---|---|---|
| v1 · Sprints 1–4 | Mascotas, historia clínica, agenda, portal y reportes | Sistema v1.x en producción |
| v2 · Fase 1 | Visión y alcance; ERS (ISO/IEC/IEEE 29148) | Alcance de la v2.0 fijado |
| v2 · Fase 2 | Modelos: grafos, procesos de negocio, flujos de sistema y diagrama de componentes | Documento de Modelos y maestro draw.io |
| v2 · Fase 3 | Reglas de Negocio v2 (119 RN), Historias de Usuario v2 (95 HU) y Requisitos Específicos (419 RE) | Trazabilidad RN → HU → RE completa |
| v2 · Construcción | Esquema multi-inquilino y módulos v2 | Plataforma SaaS v2.0 |

## 8. Análisis de Riesgos

| ID | Descripción | Prob. | Impacto | Nivel | Mitigación |
|---|---|---|---|---|---|
| R-01 | Retrasos por subestimación de tareas. | Alta | Alto | Crítico | Subtareas ≤ 4 h; revisar velocidad en el Daily. |
| R-02 | Integración con WhatsApp Business API denegada o demorada. | Media | Alto | Alto | Mitigado: el correo es el canal principal; WhatsApp diferido. |
| R-03 | Pérdida de datos por falla del servidor de BD. | Baja | Muy Alto | Alto | Respaldos diarios con cron; pruebas de restauración. |
| R-04 | Baja adopción por resistencia al cambio. | Media | Alto | Alto | Validación temprana con usuario real; UI intuitiva. |
| R-05 | Acceso no autorizado a historias clínicas. | Baja | Muy Alto | Alto | RBAC; auditoría; CSRF y sentencias preparadas. |
| R-06 | **(v2.0)** Fuga de datos entre clínicas (fallo del aislamiento multi-inquilino). | Baja | Muy Alto | Crítico | Filtro por `id_clinica` centralizado en `Security` y modelos; pruebas que verifican el aislamiento ([RNF-11](ERS.md#332-seguridad-y-privacidad)). |
| R-07 | **(v2.0)** Complejidad de los motores de grafos (rendimiento o resultados erróneos). | Media | Alto | Alto | Recorrido en memoria; grafo clínico de apoyo (no diagnostica solo); validación con casos reales. |
| R-08 | Carga excesiva de archivos clínicos. | Baja | Medio | Medio | Límite de 10 MB por archivo; ajustar php.ini. |
| R-09 | Cambio de requisitos a mitad del proyecto. | Media | Alto | Alto | Gestionar cambios entre fases vía el Product Owner; documentación primero. |
| R-10 | **(v2.0)** Abuso del plan gratuito: una misma clínica crea varias cuentas para esquivar los límites del plan. | Media | Alto | Alto | NIT único validado con su dígito de verificación; correo verificado y sin dominios desechables; CAPTCHA y límite de registros por IP en el formulario público; revisión del super-administrador. |
| R-11 | **(v2.0)** Exposición de datos por el carnet QR público (enumeración de carnets o QR filtrado). | Media | Alto | Alto | El QR lleva un token aleatorio no predecible (nunca el id de la mascota), revocable por el propietario; el carnet muestra solo datos de emergencia; límite de consultas por IP y página no indexable. |
| R-12 | **(v2.0)** Manipulación de la reputación de veterinarios (reseñas falsas para inflar o hundir). | Baja | Medio | Medio | Solo reseña quien tuvo una cita atendida, una por cita ([RN-801](ReglasNegocio.md#módulo-8--reputación-y-comunicaciones-nuevo-v2)); promedio oculto bajo un mínimo de reseñas (RN-802); la clínica no puede editar ni borrar reseñas; moderación del super-administrador. |

## 9. Presupuesto e Inversión

Proyecto formativo desarrollado por un único aprendiz. Se distinguen la **inversión real** del aprendiz y la **valoración referencial de mercado**. El análisis económico detallado (costo, horas y retorno de la inversión) está en el documento [Presupuesto y ROI](PresupuestoROI.md).

- **Inversión real del proyecto (aprendiz).** ≈ **145 h × $40.000 COP/h = $5.800.000 COP** de esfuerzo propio (estimación; no se llevó registro de horas). En contexto SENA no representa un costo monetario directo, pero sí el valor del trabajo invertido.
- **Valoración referencial de mercado** (si se contratara externamente, todos los roles: Scrum Master, Frontend, Backend, BD & DevOps y QA): las mismas 145 h a la tarifa de cada rol ≈ **$6.430.000 COP**. El aprendiz cobra todos los roles a tarifa de junior.
- **Infraestructura y herramientas.** Servidor propio (VPS), MySQL, almacenamiento en filesystem, PHPMailer con Brevo, Algolia, Cloudflare Turnstile, Google Identity, GitHub y Docker/Dokploy: propios o en su plan gratuito.

## 10. Resultados Esperados e Impacto

- Reducción cercana al 90% en el uso de papel para historias clínicas.
- Disminución de citas y vacunaciones olvidadas gracias a los recordatorios automáticos.
- Mayor fidelización de propietarios mediante el portal y el carnet digital.
- Trazabilidad completa de la salud de cada paciente.
- **(v2.0)** Una sola plataforma atiende a varias clínicas con costos compartidos (modelo SaaS), con apoyo a la decisión clínica y una agenda que se reacomoda sola ante retrasos y ausencias.

**Indicadores de éxito:** 100% de RF y RNF de prioridad Alta implementados; ≥ 60% de cobertura de pruebas en backend; aislamiento multi-inquilino verificado (0 fugas entre clínicas en las pruebas); < 3 s de respuesta con red ≥ 5 Mbps; registro de mascota y agendamiento en < 10 min; 0 defectos críticos abiertos en la entrega.

## 11. Estado Actual del Sistema

**v1.11.0 — en producción.** Línea base completa y endurecida (seguridad, recordatorios robustos, respaldos, panel de operación). El historial detallado de versiones (v1.0.0 → v1.11.0) está en el documento [Historial de Versiones](HistorialVersiones.md).

**v2.0 — en desarrollo (documentación completa).** Está terminada la especificación documentación-primero: Visión y Alcance, [ERS](ERS.md) (ISO/IEC/IEEE 29148, Rev 3.1), [Reglas de Negocio](ReglasNegocio.md) (Rev 2.0, 119 RN), [MER](MER.md), [Modelos y diagramas](Modelos.md) (Fase 2, con los dos grafos y el diagrama de componentes), [Historias de Usuario](HistoriasUsuario.md) (Rev 3.0, 95 HU) y [Requisitos Específicos](RequisitosEspecificos.md) (Rev 3.0, 419 RE). El siguiente paso es la construcción del software v2.0 sobre esta especificación.

## 12. Entregables Finales

- Código fuente completo en repositorio GitHub con [README técnico](../README.md).
- Aplicación desplegada en el servidor propio.
- Base de datos con datos de prueba.
- [Documento ERS](ERS.md) conforme a **ISO/IEC/IEEE 29148:2018** (Revisión 3.2).
- Esta Ficha Técnica del Proyecto (Revisión 3.1).
- [Reglas de Negocio](ReglasNegocio.md), [Historias de Usuario](HistoriasUsuario.md) (Rev 3.0) y [Requisitos Específicos](RequisitosEspecificos.md) (Rev 3.0).
- [Modelo Entidad-Relación](MER.md) y [Modelos y diagramas](Modelos.md) (grafos, procesos, flujos de sistema y componentes) con su maestro draw.io.
- [Historial de Versiones](HistorialVersiones.md) y documento de [Presupuesto y ROI](PresupuestoROI.md).
- Manual de usuario. _(Pendiente)_
- Presentación ejecutiva. _(Pendiente)_

_Documento elaborado por Juan Sebastián Carvajal Home — Ficha 3142784 · SENA, Florencia (Caquetá) · 2026. Uso académico._
