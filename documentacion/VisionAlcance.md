# Documento de Visión y Alcance — Zooki v2.0 (Arquitectura SaaS)

**Proyecto:** Zooki — Sistema de gestión clínica veterinaria
**Documento:** Visión y Alcance del cambio de arquitectura (línea base v1.11.0 → v2.0)
**Autor:** Juan Sebastián Carvajal Home
**Programa:** Tecnología en Análisis y Desarrollo de Software (ADSO) — SENA, Florencia (Caquetá)
**Mercado objetivo:** Clínicas veterinarias pequeñas en Colombia
**Estándar de referencia:** ISO/IEC/IEEE 29148:2018
**Código:** ZOOKI-VIS-ALCANCE · v1.2

---

## Control de versiones

| Versión | Fecha | Descripción | Autor |
|---|---|---|---|
| 1.0 | 2026-09-22 | Versión inicial. Define la visión, el alcance aprobado y el impacto del cambio a arquitectura SaaS. | J. S. Carvajal H. |
| 1.1 | 2026-09-23 | Ajustes tras revisión: los grafos se incluyen en el plan gratuito (con límites de volumen); encuesta de calificación simplificada; descuento por pago anual; incorporación del Estudio de Factibilidad Económica a la hoja de ruta; nota sobre la decisión del stack y sobre el rol recepcionista. | J. S. Carvajal H. |
| 1.2 | 2026-09-23 | Se confirma la eliminación del rol recepcionista en v2.0 y el mantenimiento del stack actual (PHP 8.2 / MySQL 8). | J. S. Carvajal H. |

---

## Tabla de contenido

1. Introducción y propósito
2. Contexto y motivación del cambio
3. Visión del producto
4. Alcance del cambio
5. Descripción de los componentes nuevos
6. Impacto arquitectónico y en el modelo de datos
7. Actores y roles
8. Modelo de negocio
9. Restricciones y supuestos
10. Riesgos y consideraciones
11. Hoja de ruta de la documentación
12. Próximos pasos

---

## 1. Introducción y propósito

Este documento define la **visión** y el **alcance** del cambio de arquitectura de Zooki, partiendo de la versión estable **v1.11.0** hacia una nueva versión **v2.0** orientada a un modelo **SaaS multi-inquilino (multi-tenant)**.

Su propósito es servir como documento maestro de la Fase 0 del proceso de re-documentación: fija qué se va a construir, qué queda dentro y fuera del alcance, y qué impacto tiene el cambio sobre el modelo de datos, los roles y la arquitectura. De este documento se derivan los demás artefactos (ERS actualizado, historias de usuario, reglas de negocio, requisitos específicos y modelos), por lo que ninguno de ellos debe contradecirlo.

## 2. Contexto y motivación del cambio

Zooki nació como una aplicación web para digitalizar la gestión clínica de veterinarias pequeñas en Neiva (Huila). A la fecha, la versión **v1.11.0** es un sistema estable, en producción, con ocho módulos funcionales, autenticación por sesiones y Google OAuth, historia clínica, agenda de citas, calendario de vacunación, portal del propietario y notificaciones por correo. La documentación actual (ERS Rev. 2.0, 55 historias de usuario, 192 requisitos específicos, 36 reglas de negocio y el modelo entidad-relación) refleja ese estado.

El cambio propuesto responde a dos necesidades:

- **Convertir Zooki en un producto vendible a varias clínicas**, no en una instalación para una sola. Esto exige una arquitectura multi-inquilino y un modelo de negocio por suscripción.
- **Incorporar un diferenciador técnico real**: dos capacidades basadas en grafos (soporte a la decisión clínica y agendamiento inteligente) que hoy no existen en el software veterinario de pequeña escala.

La versión v1.11.0 se cerró de forma deliberada como línea base estable, dado que el cambio es de gran magnitud y toca el modelo de datos, los roles y prácticamente todos los módulos.

## 3. Visión del producto

> Zooki v2.0 será una plataforma **SaaS** que cualquier clínica veterinaria pequeña pueda **registrar y probar de forma gratuita**, y que, mediante un plan de pago, desbloquee un **copiloto clínico** y un **agendamiento inteligente** que reducen errores de prescripción y desorganización de la agenda — capacidades ausentes en el software veterinario convencional.

La propuesta de valor combina una **innovación técnica** (los dos grafos) con una **visión de negocio** (SaaS freemium multi-inquilino), de modo que responde tanto al criterio académico como al comercial.

## 4. Alcance del cambio

### 4.1 Dentro del alcance (aprobado)

| # | Componente | Tipo |
|---|---|---|
| A1 | Arquitectura multi-inquilino (super-administrador y aislamiento por clínica) | Arquitectura |
| A2 | Grafo I — Soporte a la decisión clínica (CDSS con grafo con signo) | Innovación |
| A3 | Grafo II — Agendamiento inteligente de citas | Innovación |
| A4 | Registro self-service de clínicas + panel del super-administrador | SaaS |
| A5 | Suscripción y límites por plan (freemium) | SaaS / Negocio |
| A6 | Configuración por clínica | SaaS |
| A7 | Carnet digital con código QR de emergencia | Clínico |
| A8 | Portal del propietario con auto-agendamiento y centro de notificaciones | Propietario |
| A9 | Perfil y calificación de veterinarios | Confianza / Negocio |

### 4.2 Fuera del alcance

Inventario de medicamentos completo (con proveedores), facturación electrónica / punto de venta, y telemedicina con video. Se documentan como evolución futura, no se implementan en esta versión.

### 4.3 Deseables (si el tiempo lo permite)

Recetas / fórmulas médicas exportables en PDF, motor de salud preventiva por protocolos (esquemas de vacunación y desparasitación según especie y edad) y aplicación instalable (PWA) para el propietario. Se documentan como historias de usuario de prioridad *deseable*; entran solo si el cronograma lo permite.

## 5. Descripción de los componentes nuevos

Cada componente se describe aquí a nivel de visión. Su especificación detallada (historias de usuario, requisitos y criterios de aceptación) se desarrolla en las fases posteriores.

### 5.1 Arquitectura multi-inquilino (A1)

Zooki pasa de servir a una sola clínica a servir a muchas sobre la misma instalación. Cada clínica es un **inquilino (tenant)** cuyos datos están aislados de los demás. Aparece un rol nuevo por encima de todos, el **super-administrador** (operador de la plataforma), con visibilidad sobre todas las clínicas; el resto de roles queda confinado a su propia clínica.
**Problema que resuelve:** permite comercializar Zooki como producto a múltiples clínicas sin instalaciones separadas.
**Prioridad:** alta (habilitador de todo lo demás).

### 5.2 Grafo I — Soporte a la decisión clínica (A2)

El conocimiento veterinario se modela como un **grafo dirigido con signo**: los nodos son conceptos clínicos (síntomas, diagnósticos, fármacos, especies, razas, condiciones) y las aristas sus relaciones, con signo **positivo** (`trata`, `beneficia`) o **negativo** (`tóxico-para`, `contraindica`). Durante la consulta, el sistema recorre el grafo para sugerir diagnósticos ordenados por peso y advertir sobre toxicidades e interacciones antes de prescribir.
**Problema que resuelve:** ausencia de soporte a la decisión clínica en veterinarias pequeñas; previene errores de prescripción.
**Prioridad:** alta (diferenciador principal).

### 5.3 Grafo II — Agendamiento inteligente de citas (A3)

La agenda se modela como un grafo cuyas aristas expresan **secuencia** (precedencia entre citas del mismo veterinario), **conflicto** (choque de horario) y **emparejamiento** (asignación cita–veterinario). Cuando una consulta se extiende, el retraso se propaga por las aristas de secuencia y el sistema recalcula la cola y puede reasignar a otro veterinario disponible. Incorpora **prioridad** por urgencia.
**Problema que resuelve:** el efecto cascada por retrasos y la falta de priorización de citas.
**Prioridad:** alta (segundo diferenciador).

### 5.4 Registro self-service de clínicas y panel del super-administrador (A4)

Una clínica puede **registrarse por sí misma** en la plataforma y comenzar a operar en su propio espacio aislado. El super-administrador dispone de un panel para administrar las clínicas registradas, su estado y su plan.
**Problema que resuelve:** adquisición de clientes sin fricción y administración centralizada del SaaS.
**Prioridad:** alta (necesario para el multi-inquilino).

### 5.5 Suscripción y límites por plan — freemium (A5)

Se define un **plan gratuito** de evaluación y un **plan profesional** de pago. A diferencia de un freemium que oculta lo principal, en Zooki **el plan gratuito incluye los dos grafos en funcionamiento**, para que la clínica compruebe su valor y eficiencia antes de pagar; lo que el plan gratuito limita es el **volumen** (por ejemplo, número máximo de mascotas y de citas/consultas al mes). El **plan profesional** ($100.000 COP al mes o $960.000 COP al año) elimina esos límites. Las **capas avanzadas** (aprendizaje del grafo con los datos de la propia clínica) se prevén para una versión posterior como beneficio del plan profesional. El sistema controla los límites según el plan y congela beneficios si la suscripción no está al día. El **cobro real mediante pasarela** se pospone a una fase posterior; en esta versión se gestiona el estado del plan y sus límites.
**Problema que resuelve:** sostenibilidad económica del producto sin sacrificar la conversión.
**Prioridad:** alta.

### 5.6 Configuración por clínica (A6)

Cada clínica configura sus propios parámetros: horarios de atención, veterinarios, servicios/tipos de cita, y datos de marca (nombre, logo).
**Problema que resuelve:** que el multi-inquilino se adapte a la realidad de cada clínica sin tocar código.
**Prioridad:** media-alta.

### 5.7 Carnet digital con código QR de emergencia (A7)

Cada mascota tiene un **carnet digital accesible por QR** que, al escanearse, muestra un resumen de emergencia: vacunas vigentes, alertas relevantes y contacto del propietario. Útil en guarderías, viajes y urgencias.
**Problema que resuelve:** acceso inmediato a información crítica de la mascota fuera de la clínica.
**Prioridad:** media (gancho de producto).

### 5.8 Portal del propietario con auto-agendamiento y notificaciones (A8)

El propietario puede **agendar, cancelar y reprogramar** sus citas desde el portal, y recibe un **centro de notificaciones** con recordatorios y cambios. Se integra con el Grafo II para ofrecer horarios disponibles reales.
**Problema que resuelve:** descarga a la clínica de la gestión telefónica de citas y mejora la experiencia del propietario.
**Prioridad:** media-alta.

### 5.9 Perfil y calificación de veterinarios (A9)

Tras una consulta **atendida**, el propietario responde una encuesta **muy breve**: una calificación por **estrellas (1 a 5)** y un **comentario opcional**. Se mantiene mínima a propósito, porque una encuesta larga baja la tasa de respuesta. Estas calificaciones alimentan un **perfil público** del veterinario (promedio, número de reseñas, especialidades) que ayuda al propietario a **elegir** con criterio al momento de agendar, y puede alimentar el emparejamiento del Grafo II.
**Salvedades de diseño (obligatorias):** solo puede calificar quien tuvo una cita realmente atendida; una reseña por cita; y no se muestra promedio hasta alcanzar un número mínimo de reseñas, para evitar promedios engañosos o manipulación.
**Problema que resuelve:** el propietario hoy no tiene forma de saber qué veterinario elegir.
**Prioridad:** media.

## 6. Impacto arquitectónico y en el modelo de datos

El cambio es transversal. Los impactos principales:

- **Nueva entidad `clinicas`** y una columna de inquilino (`id_clinica`) en las tablas de negocio (usuarios, mascotas, citas, consultas, vacunas, desparasitaciones, notificaciones, etc.). Toda consulta pasa a **filtrar por clínica**.
- **Nuevo rol super-administrador** por encima de los roles de clínica (administrador, veterinario y propietario). Además, el rol **recepcionista** de v1.11.0 se **elimina** en v2.0 (depuración). La matriz de roles de `helpers/Security.php` debe incorporar el aislamiento por clínica en cada acción: lo que no está autorizado, se deniega.
- **Nuevas entidades de negocio SaaS:** `planes`, `suscripciones` (estado, límites, vigencia) y la relación clínica–plan.
- **Nuevas entidades para los grafos:** `nodos` y `aristas` (con tipo y signo/peso) para el grafo clínico, y la estructura que soporte la propagación y el emparejamiento en el grafo de agenda. Se define si el conocimiento clínico es global (compartido entre clínicas) o configurable por clínica.
- **Nuevas entidades para reputación:** `resenas_veterinario` (atadas a la cita/consulta) y campos de perfil del veterinario.
- **Migraciones:** cada cambio se implementa como migración `database/NN_nombre.sql` reejecutable, y se actualiza `documentacion/MER.md`.

Estos impactos se detallarán en la Fase 2 (modelos) y la Fase 3 (requisitos).

## 7. Actores y roles

| Rol | Alcance | Novedad |
|---|---|---|
| Super-administrador | Toda la plataforma; gestiona clínicas y planes | **Nuevo** |
| Administrador de clínica | Su clínica: usuarios, configuración, datos | Confinado por `id_clinica` |
| Veterinario | Su clínica: consultas, agenda; perfil calificable | Perfil con reputación |
| Propietario | Sus mascotas; auto-agenda y califica | Auto-agendamiento y encuesta |

> **Nota:** el rol *recepcionista* (rol 3) está presente en v1.11.0 pero se **elimina en v2.0** (depuración). El diseño queda lo suficientemente escalable para reincorporarlo en el futuro si se requiere.

## 8. Modelo de negocio

Zooki v2.0 se ofrece como **SaaS multi-inquilino** con estrategia **freemium**: la clínica se registra y prueba gratis. El plan gratuito **incluye los dos grafos en funcionamiento** pero con límites de volumen (mascotas y citas/consultas), de modo que la clínica ve el valor real antes de decidir; el **plan profesional** de pago (referencia: ~$100.000 COP/mes) elimina los límites; las capas avanzadas llegarán en una versión posterior. Se contempla un **descuento por pago anticipado anual** (por ejemplo, el mes efectivo baja a ~$80.000 COP si se paga el año completo). El valor que justifica el pago —reducir errores clínicos y evitar la pérdida de tiempo por desorganización, sin topes de volumen— sostiene la conversión. El modelo escala con cada nueva clínica sin incremento proporcional del costo de desarrollo.

El detalle económico (costos de desarrollo a partir de las horas invertidas, costos de operación, ingresos proyectados, punto de equilibrio y retorno de la inversión) se desarrolla en un documento aparte: el **Estudio de Factibilidad Económica** (ver hoja de ruta).

## 9. Restricciones y supuestos

- **Tiempo:** aproximadamente un mes para toda la versión; proyecto individual.
- **Stack:** se mantiene PHP 8.2 (MVC propio), MySQL 8, JavaScript sin bundler; los grafos se implementan sobre este stack, sin dependencia de servicios de IA externos. Se evaluó migrar de stack y se **descartó**: a la escala objetivo (clínicas pequeñas), PHP 8.2 y MySQL 8 ofrecen rendimiento de sobra, y reescribir desde cero pondría en riesgo el cronograma y el trabajo ya estable. El rendimiento se cuida con **índices compuestos por `id_clinica`**, consultas optimizadas y el recorrido de los grafos **en memoria** (lista de adyacencia); una caché externa (p. ej. Redis) queda como opción futura solo si el volumen lo exige.
- **Flujo de trabajo:** spec-first; `documentacion/*.md` es la fuente de verdad; el código se escribe solo cuando la documentación esté lista.
- **Calidad del conocimiento clínico:** el valor del Grafo I depende de la exactitud de sus datos; se acota inicialmente a perros y gatos y a las condiciones más frecuentes.

## 10. Riesgos y consideraciones

- **Alcance ambicioso para un mes.** Mitigación: los componentes marcados como *deseables* entran solo si el cronograma lo permite; los diferenciadores (grafos y multi-inquilino) tienen prioridad.
- **Multi-inquilino mal aislado.** Un error de filtrado por `id_clinica` expondría datos entre clínicas. Mitigación: aislamiento verificado en `Security.php` y pruebas específicas.
- **Calificación de veterinarios injusta o manipulable.** Mitigación: reseñas atadas a citas atendidas, una por cita, y promedio oculto bajo un mínimo de reseñas.
- **El Grafo I es apoyo, no diagnóstico automático.** La decisión final es siempre del profesional; así se delimita la responsabilidad clínica.

## 11. Hoja de ruta de la documentación

| Fase | Entregable | Estado |
|---|---|---|
| 0 | Documento de Visión y Alcance (este documento) | En revisión |
| 1 | Esqueleto del ERS actualizado (alineado a ISO/IEC/IEEE 29148:2018) | Pendiente |
| 2 | Modelos: casos de uso, MER actualizado + modelo de grafos, arquitectura, flujo de agenda | Pendiente |
| 3 | Requisitos: reglas de negocio, historias de usuario y requisitos específicos + matriz de trazabilidad | Pendiente |
| 4 | ERS integrado + Ficha técnica actualizada | Pendiente |
| 4b | Estudio de Factibilidad Económica (costos, ingresos, punto de equilibrio, ROI) | Pendiente |
| 5 | Implementación del código | Posterior |

## 12. Próximos pasos

1. Revisar y aprobar este documento de Visión y Alcance.
2. Con el alcance aprobado, elaborar el **esqueleto del ERS** bajo ISO/IEC/IEEE 29148:2018 (Fase 1).
3. Desarrollar los modelos, empezando por el **MER actualizado** con el impacto multi-inquilino y las entidades de los grafos (Fase 2).
