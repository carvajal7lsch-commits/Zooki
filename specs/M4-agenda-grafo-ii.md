# M4 — Agenda inteligente y Grafo II

> Estado: **plan aprobado por el usuario (2026-10-17)**. Siguiente: A, inventario y contrato; las decisiones técnicas abiertas se resuelven antes de implementar B.
> Entrega: v2.0 · Fecha: 2026-10-17 · Salida propuesta: `2.0.0-alpha.5`.
> Arquitecto y revisor: Codex (esta sesión, relevo temporal). Ejecutor propuesto: Claude Code, por subetapas y por turnos.
> Rama actual comprobada: `v2/m2-grafo`. Rama propuesta del módulo: `v2/m4-agenda`, desde `main` con M2 integrado. La crea el usuario; no se cambia de rama desde el agente.

## 1. Resultado y límites

**Resultado:** la clínica configura cuándo trabaja cada veterinario y qué duración, margen y posibilidad de pausa tiene cada servicio. Solo el propietario y el veterinario agendan, con síntomas confirmados y prioridad calculada por el motor de M2; el administrador no agenda. La agenda ofrece disponibilidad real, reserva sin carreras, atiende sobrecupos naranjas y deriva los rojos. Refleja retrasos, ausencias, cobertura, pausas por emergencia y llegadas tarde, conservando consultas, identidad y auditoría.

El Grafo II se construye en memoria desde las citas y la disponibilidad: aristas de secuencia, conflicto y emparejamiento. No se crea una base de grafos ni se persiste el grafo completo. El Grafo I calcula el triage; el Grafo II organiza la atención; el veterinario conserva la decisión clínica (RN-209).

**Situación comprobada en `2.0.0-alpha.4`:**

- M2 está cerrado y desplegado según `agentes/estado.md` y el cierre del plan M2. La cabecera de AGENTS.md aún describe el momento previo al despliegue; se señala como texto desactualizado, sin revertir el cierre confirmado.
- Ya existen `citas`, `cita_sintomas`, `tipos_cita`, `horarios_clinica`, `horarios_veterinario`, `ausencias_veterinario`, `propuestas_horario`, `reasignaciones` e `ingresos_emergencia`. La existencia de tablas no acredita que sus flujos estén implementados.
- `citas` ya admite prioridades, síntomas/contexto, duración/margen, sobrecupo, llegada, horas reales y estado `pausada`. El índice único impide comienzos iguales ordinarios, pero por sí solo no resuelve solapes de distinta hora ni carreras de sobrecupos.
- `Cita::registrarEvaluada()` ofrece la entrada de prioridad evaluada y el límite del plan; la entrada ordinaria aún usa verde. El controlador y el modelo actuales deben inventariarse completos en A, incluido el portal y la reprogramación.
- M2 entrega el motor, buscador y entrada estructurada, con 26 casos T automatizados. Siguen pendientes de validación veterinaria; T-16 y T-20 conservan sus limitaciones expresas. No se cambian los esperados para facilitar la agenda.
- `calcular_triage_ajax` permite veterinario y propietario y conserva esos roles. La decisión 2 exige retirar el permiso actual del administrador para crear citas, alineando documentación, servidor y pantallas durante M4.

**Destino v2:** [MER](../documentacion/MER.md), [Modelos](../documentacion/Modelos.md) §2 y §4–§8; §3 para consulta, §9–§10 para contexto y §13.4 para propuestas del veterinario. La batería interna `documentacion/CasosTriage.md` aporta T-01–T-26 y O-01–O-11.

**Incluido como dependencia transversal:** HU-7.4 completa (marca, teléfono de urgencias, tipos, duración, margen y pausable), la conservación de HU-7.1 y las rutas existentes de agenda; HU-5.6 completa, absorbida por la agenda según el plan de entregas. Los parámetros generales de HU-7.5 se leen por clínica con sus valores existentes; su panel general sigue en v2.1.

**Fuera de esta propuesta v2.0:** reporte de ausentismo RE-4.7.3; HU-4.10 (confirmar desde recordatorio), HU-4.16 (afinidad/reputación) y HU-4.17 (actualización de síntomas por el propietario): v2.1, sujeto a resolver la contradicción de HU-4.10. O-06 se conserva como aceptación futura de HU-4.17, no se declara pasado por M4. Tampoco entran suscripción/cobro de HU-0.5, gestión general de especialidades, plantillas/bitácora de M8, carnet/QR ni rediseño global. Sí se prueba la excepción roja frente al estado de mora sin construir la suscripción.

**Decisión del usuario (2026-10-17):** toda atención sin reserva debe crear una cita de llegada y conservar ese origen. La incorporación de HU-2.2 está confirmada; los criterios documentales y el tratamiento de capacidad/sobrecupo deben concretarse antes de implementar, sin permitir una consulta nueva por este acceso que quede fuera de la agenda.

## 2. Trazabilidad

Los rangos incluyen cada RE consecutivo del intervalo. La evidencia indicada es **prevista**, no una prueba ejecutada. A convierte esta matriz en una lista por RE con su prueba concreta y las salvedades de lo ya entregado.

| HU | RE incluidos | RN/RNF | Etapa | Evidencia de aceptación prevista |
|---|---|---|---|---|
| HU-7.4 | RE-7.4.1–RE-7.4.6 | RN-009, RN-415, RN-421, RN-701, RN-427 | C1, C7, F | Marca y teléfono en configuración/portal/correos/documentos; tipos aislados con duración, margen y pausable; auditoría y JS de formulario. |
| HU-4.11 | RE-4.11.1–RE-4.11.5 | RN-706, RN-703, RN-415, RNF-11 | C2, C3, C5 | Franjas válidas; rechazo de solapes incluso entre clínicas sin revelar horarios ajenos; propuesta vet pendiente y aprobación admin; citas afectadas para reajuste. |
| HU-4.12 | RE-4.12.1–RE-4.12.5 | RN-414, RN-415 | C3, C6 | Ausencia de día completo/parcial y extremos; cobertura sin veto, alternativa pendiente del propietario, avisos y auditoría. |
| HU-4.13 | RE-4.13.1–RE-4.13.7 | RN-401, RN-402, RN-411, RN-415, RN-420, RN-422 | C3, C4a, C4b, C7 | Cuatro ramas; bloqueo real concurrente; tipo/duración/margen/prioridad; tope 2 por defecto y orden por fecha/ID; rojo antes del límite. |
| HU-2.11 | RE-2.11.1–RE-2.11.7 (cierre operativo) | RN-209, RN-411, RN-416–RN-418, RN-424, RN-425, RN-113 | C4a, C4b, C7 | Marcas/tiempos/nota persistidos; nota sola rechazada; 26/26 sin alterar esperados; T-20 sin horario antes de revisión; T-11 avisa aislamiento. |
| HU-4.14 | RE-4.14.1–RE-4.14.7 | RN-412, RN-413, RN-415, RN-428, RN-429 | C5a, C5b | Reloj controlado: margen exacto, cascada, fin de franja, aceptación/rechazo/vencimiento de propuestas; avisos acumulados y como máximo dos por hora en el caso de aceptación. |
| HU-4.15 | RE-4.15.1–RE-4.15.8 | RN-411, RN-415, RN-420, RN-421, RN-427; RN-G19, RN-110, RN-206, RN-207 | C4a, C7a, C7b | O-01/O-02/O-09/O-10; libre → pausable → espera; sin cuotas/mora; ficha y cuenta existentes o provisional; conciliación transaccional sin perder actos. |
| HU-4.18 | RE-4.18.1–RE-4.18.4 | RN-419, RN-209 | C4b, C8 | Ajuste solo vet con motivo; calculado y ajustado separados; reubicación y aviso; historial de reclasificaciones visible a la clínica con aislamiento. O-07/O-08. |
| HU-4.19 | RE-4.19.1–RE-4.19.4 | RN-426, RN-408, RN-412 | C8 | Hora llegada; debajo, en y encima de tolerancia; duración restante insuficiente; tres opciones tras tolerancia; recálculo y auditoría. |
| HU-4.1 | RE-4.1.1–RE-4.1.5 | RN-401–RN-404 | C3, C4, D | Regresión: reserva interna/portal, calendario, duración completa, correo y protección de conflictos. |
| HU-4.2 | RE-4.2.1–RE-4.2.4 | RN-405 | C3, C4b, D | Cancelar conserva fila; reprogramar usa la misma disponibilidad y candados; conserva permisos y avisa. |
| HU-4.3 | RE-4.3.1–RE-4.3.9 (RE-4.3.8 derogado) | RN-406–RN-410 | C5, C7, D | Solo asignado inicia/continúa; cierre atómico con consulta; vigilante y sin cerrar; no reaparece cerrar sin consulta; pausa no permite cierre indebido. |
| HU-4.4 | RE-4.4.1–RE-4.4.4 | RN-404 | C4, C5b, D | Correo tras guardar, datos correctos, fallo registrado y sin perder/bloquear reserva. |
| HU-4.5 | RE-4.5.1–RE-4.5.3 | RN-401, RN-412 | C5 | Horas reales conservadas y exceso detectado durante la atención, además de al finalizar. |
| HU-4.6 | RE-4.6.1–RE-4.6.2 | RN-401, RN-403, RN-415 | C1, C3 | Margen por tipo configurable y aplicado una sola vez en sugerencias y reserva. |
| HU-4.7 (parcial) | RE-4.7.1–RE-4.7.2 | RN-405, RN-408 | C8, D | Solo vet asignado tras hora prevista; libera el espacio. RE-4.7.3 sigue en v2.1. |
| HU-4.8 | RE-4.8.1–RE-4.8.3 | RN-402, RN-703 | C2, C3, C6 | Horario recurrente, bloqueo por fecha y cierre/festivo puntual sin citas ofrecidas. A debe resolver su representación: no basta apagar todos los lunes para cerrar un único lunes. |
| HU-4.9 | RE-4.9.1–RE-4.9.3 | RN-401, RN-402 | C3 | Misma lógica para sugerir/guardar; intervalo completo; dos conexiones con horas iniciales diferentes: una sola reserva compatible. |
| HU-5.3 | RE-5.3.1–RE-5.3.5 | RN-401, RN-402, RN-501, RN-109, RN-110, RN-G02 | C4 | Regresión de agendar desde portal, clínica y mascota legítimas, confirmación y sin acceso a mascota ajena. |
| HU-5.6 | RE-5.6.1–RE-5.6.4 | RN-405, RN-401, RN-G02 | C4b, C5b | Cancelación/reprogramación propia con disponibilidad vigente, aviso a clínica y auditoría; petición directa ajena rechazada. |
| HU-2.2 | RE-2.2.1–RE-2.2.4 por ajustar antes de implementar | RN-102, RN-203, RN-208; RN-422 por precisar | C8b | Origen «llegada sin reserva», cita obligatoria y atención coherentes, sin duplicado ante doble envío. Capacidad/sobrecupo por concretar; conservar trazabilidad de las consultas históricas sin cita. |
| Transversal | RE-T.10.1, alcance RNF-11/RNF-16 y requisitos de acceso ya vigentes | RN-G01, RN-G02, RN-G13, RN-G19 | Todas, D | Contexto activo, CSRF, clínica y titularidad; A concreta RE transversales adicionales; revisión de interfaz en móvil/tableta/escritorio. |

Los criterios normativos permanecen en `documentacion/`; no se cambian silenciosamente en esta tabla. Las nuevas reglas de llegada y las decisiones no cubiertas requieren criterio y aprobación antes del código.

## 3. Etapas y dependencias

**Asignación propuesta para todas las filas:** escribe Claude Code; revisa Codex arquitecto de esta sesión. Si el usuario elige un ejecutor Codex, deberá ser otra sesión y dejarse identificado en el anexo. Nunca revisará su propia entrega. Trabajo por turnos en el mismo checkout, sin agentes paralelos sobre archivos compartidos.

| Etapa | Entregable comprobable | Depende de | Escribe | Revisa |
|---|---|---|---|---|
| **A. Inventario y contrato** | Solo lectura del código y anexo de diseño: rutas internas/portal, calendario, pacientes, atención, tipos, configuración, correos, vigilante y pruebas; regresiones conservadas; trazabilidad por RE; diferencias con Modelos §2–§8. Proponer algoritmo de intervalos/cascada, orden de candados, cierre puntual, revisión humana de triage, cola roja sin vet aún y persistencia mínima; resolver datos del acompañante y selección entre veterinarios igualmente compatibles antes de B. | Aprobación del plan | Claude Code | Codex |
| **B. Datos y contratos documentales** | Migraciones solo donde A demuestre huecos; MER/drawDB/esquema alineados; relleno conservador e idempotencia. Ajustes de HU/RE/RN/diagramas aprobados. No se habilitan rutas parciales. | A revisada y decisiones de datos aprobadas | Claude Code | Codex |
| **C1. Marca y tipos** | HU-7.4 completa: configuración, duración/margen/pausable, teléfono; propagación de marca y aislamiento; mismo diseño y validación en vivo. | B | Claude Code | Codex |
| **C2. Horarios** | HU-4.11: admin configura, vet propone y admin resuelve, privacidad entre clínicas, citas afectadas marcadas. Antes de C5 esas marcas quedan visibles como pendientes, sin fingir reajuste automático ya terminado. | C1 | Claude Code | Codex |
| **C3. Disponibilidad y reserva** | Una lógica de intersección, resta de ausencias/cierres y ocupación por duración+margen para sugerencias, reserva y reprogramación; candados y pruebas concurrentes; nunca disponibilidad permanente por falta de horario. Cierre de HU-4.9 y base de HU-4.6/4.8. | C2 | Claude Code | Codex |
| **C4a. Agendar con triage** | Interno y portal comparten captura/buscador C4 de M2; cuatro ramas, excepción roja, clínica cerrada, revisión humana y contagio, persistencia y notificaciones. Rojo deriva a circuito clínico; no crea reserva ordinaria ni promete atención por el solo cálculo remoto. | C3 | Claude Code | Codex |
| **C4b. Sobrecupos y cambios** | Tope y orden naranja con motivo; primer espacio amarillo/elección verde; conflictos sobrevenidos; cancelación/reprogramación interna y portal con misma lógica; revisión previa al horario y registro del nivel aprobado por vet. | C4a | Claude Code | Codex |
| **C5a. Grafo II y cascada** | Secuencia/conflicto/emparejamiento; horas estimadas sin destruir horas pactadas o reales; margen, huecos y fin de franja. Disparos durante atención y al finalizar, sin depender de abrir calendario; procesador idempotente. | C4b | Claude Code | Codex |
| **C5b. Reasignación en vivo** | Propuesta/aceptación/rechazo/vencimiento, siguiente candidato y reprogramación con propietario; revalidación al aceptar; avisos agrupados RN-429 y auditoría. Procesa marcas de horario C2. | C5a | Claude Code | Codex |
| **C6. Ausencias y cobertura** | Alta admin/solicitud propia vet, bloqueo exacto y cobertura sin veto; sin cobertura queda pendiente de reprogramar y se ofrecen alternativas; auditoría y avisos. No reutilizar el veto de C5b. | C5b | Claude Code | Codex |
| **C7a. Atención roja y pausas** | Libre/pausable/espera, llegada directa y derivación del portal, identidad conocida, prioridad máxima, excepción a plan/mora; pausa y reanudación antes de siguientes; consulta y cascada coherentes. | C6 | Claude Code | Codex |
| **C7b. Ficha provisional y conciliación** | Cuatro ramas de identidad de HU-4.15; captura mínima, tarea pendiente, completar o consolidar sin perder actos ni cuenta nueva, carnet inactivo y datos del acompañante protegidos. | C7a y contrato de datos aprobado | Claude Code | Codex |
| **C8. Ajustes y llegada tarde** | HU-4.18 completa (más lo adelantado por revisión humana), historial de reclasificaciones; hora llegada, opciones por tolerancia y capacidad restante, no asistió y recálculo. | C7b | Claude Code | Codex |
| **C8b. Llegada sin reserva** | HU-2.2 según decisión 3 confirmada: cita obligatoria, origen distinguible y ocupación real; nunca convertir un verde en naranja para eludir las reglas. | C8 y criterios de capacidad aprobados | Claude Code | Codex |
| **D. Correcciones y verificación final** | Corregir hallazgos, regresión M2/agenda/portal, comparación de diagramas, aislamiento y pruebas completas; 3–5 pruebas manuales por entrega. Sin rediseño general. | Etapas anteriores revisadas | Claude Code | Codex |
| **F. Cierre y despliegue** | Matriz final por RE, estados documentales, una revisión por documento, PDF/Word y diagramas verificados; versión/historial `2.0.0-alpha.5`; guía de respaldo, integración y comprobación de producción. El usuario ejecuta Git y despliegue. | D y pruebas del usuario | Claude Code | Codex |

Las filas C4/C5/C7 de la matriz agrupan sus subetapas a/b. Si el inventario muestra que una fila no cabe en una entrega verificable, se divide antes de emitir el prompt; no se implementan dos por comodidad. Cada prompt sigue literalmente la estructura de `agentes/metodo.md` §5 y cita sus diagramas, alcance, exclusiones y pruebas concretas.

## 4. Datos y migraciones

- **Se conserva la v2 desplegada.** No hay reinicio de base ni migración de datos v1. La nota antigua sobre v1 de `specs/HU-4.15-ingreso-emergencia.md` se debe alinear con el esquema v2 ya existente en B.
- `01_schema.sql` ya contiene las entidades principales; no recrearlas. La próxima migración disponible es **08**, si A confirma que hace falta; su nombre y contenido se fijan en el anexo de A revisado. Si se publica una 08 antes, se toma el siguiente número libre.
- **Huecos a resolver en A:** horario estimado y último aviso RN-429; marca de reajuste/reprogramación pendiente; solicitudes de ausencia; cierre puntual de clínica; pertenencia de sobrecupo a bloque; orden/prioridad de espera y pausa/reanudación; urgencia sin veterinario aún (`citas.id_veterinario` hoy es obligatorio); revisión humana antes de reservar. No se aprueban aquí columnas nuevas ni se introduce NULL en una FK sin revisar todos sus lectores.
- Decidir qué estado debe persistir entre peticiones y qué se recalcula. No usar texto de observaciones como estado operativo ni auditoría como sustituto accidental de una cola. Toda ampliación pasa primero por MER/drawDB, criterio de aceptación y aprobación si cambia alcance.
- Conservar instantáneas de duración/margen y triage calculado frente a ajustado, marcas y tiempos por evento, contexto/versión del conocimiento; no copiar tratamientos protegidos al contexto de la cita.
- A propone relleno verificable para citas antiguas sin duración/tipo y para clínicas/veterinarios sin horario. No inventar síntomas ni triage retrospectivos ni asignar automáticamente jornada completa. No modificar horarios pactados ni citas completadas para hacerlas encajar.
- **Concurrencia:** serializar la comprobación y la escritura incluso cuando no existe todavía una cita en el hueco. Un candado solo sobre citas existentes es insuficiente. A define orden estable para vet/mascota/clínica y plan, reserva, sobrecupo, aceptación y conciliación; MySQL prueba dos conexiones y rollback. Las excepciones de lectura de ocupación entre clínicas quedan encapsuladas y no devuelven datos ajenos.
- Migraciones idempotentes y probadas dos veces sobre esquema nuevo y base previa con datos; sin editar 03–07 publicadas; sincronizar `01_schema.sql`, semilla si corresponde, MER y drawDB. Semilla solo inserta faltantes.
- Despliegue automático por migrador; primero migración satisfactoria, luego conocimiento. En XAMPP el usuario ejecuta `php scripts/migrar.php` y después `php scripts/cargar_conocimiento.php`. No se lee `.env` ni se ejecutan pruebas contra `zooki_v2_prueba`.
- Recuperación: respaldo antes del despliegue, cambios aditivos donde sea posible y comprobación de compatibilidad antes de volver al código anterior; no prometer rollback de código seguro tras modificar estados/FK sin demostrarlo.

## 5. Código, permisos y pantallas

- **Modelos/helpers:** partir de `models/Cita.php`, `models/HorarioClinica.php`, `helpers/HorarioAtencion.php`, `helpers/MotorTriage.php`, validadores, límites y vigilante existentes. Separar cálculo puro del Grafo II, acceso a disponibilidad y coordinación transaccional; nombres finales y archivos nuevos se concretan en A. Reutilizar motor/buscador de M2, sin otro cálculo de triage en JS.
- **Controladores/rutas:** `controllers/CitaController.php`, consulta y los controladores de configuración/portal implicados; registrar todas las acciones nuevas en `public/index.php` y `helpers/Security.php`. Tarea programada para vencimientos/retrasos sin navegador abierto; reaprovechar infraestructura existente sin duplicar avisos de atenciones abiertas.
- **Administrador:** horario, aprobación de propuestas, ausencias/cobertura y configuración de su clínica. No agenda, no inicia ni completa consulta. La retirada de la creación de citas se verifica también por petición directa y en todos los accesos existentes, no solo ocultando botones.
- **Veterinario:** sus propuestas/solicitudes y sus atenciones; ajuste/reordenación motivados y respuesta a propuestas dirigidas a él, con alcance exacto revisado en A. No guarda directamente horario recurrente. Nunca atiende con contexto de administrador.
- **Propietario:** solo sus mascotas y citas, en clínica legítimamente vinculada; sin poder autodeclarar prioridad ni exponer estado comercial de la clínica. Revalidar vínculo, titularidad, actividad y datos del cálculo al guardar.
- **Plataforma:** Gina no obtiene permiso clínico por ser superadministradora. Probar denegación con su contexto activo.
- **Pantallas:** conservar calendario, gestión de citas, atención, Pacientes, formularios de configuración y portal. Reutilizar `public/js/consulta-sintomas.js` y extraer componentes compartidos cuando corresponda; no duplicar el selector. Validación/restricción en vivo, mensajes junto al campo, datos `data-*`, sin JS/CSS en línea ni confirmaciones para decisiones reversibles ya motivadas.
- Las lecturas globales de alergias/alertas se mantienen conforme a RN-113. La ocupación y horarios de un vet de varias clínicas se verifican internamente con respuesta genérica; no amplían acceso al calendario ni historia de la otra clínica.
- HU-7.4 incluye comprobar la marca en todas las salidas que exige RE-7.4.4; no se declara completa solo por mostrar logo en configuración. Las descargas públicas del portal documental no pasan a ser documentos personalizados de una clínica.

## 6. Riesgos y decisiones pendientes

| Riesgo o diferencia encontrada | Efecto | Mitigación / momento de resolución |
|---|---|---|
| HU-4.10 tiene nota v2.0, pero tabla de entregas v2.1 | Alcance contradictorio | Decisión 1; corregir la nota antes de implementar. |
| HU-4.1 y permisos actuales admiten admin; Modelos §4 lo excluye | Flujo y matriz de permisos incompatibles | Decisión 2; alinear HU-4.13, RE y diagrama, sin ampliar permisos a escondidas. |
| HU-2.2 exige consulta sin cita; la idea de llegada crearía cita | Se pierde trazabilidad «Sin cita» o se aplica mal RN-422 | Decisión 3; definir origen y tratamiento del sobrecupo no naranja antes del prompt C8b. |
| RN-422 regula sobrecupos naranjas, no cualquier llegada | Llegadas verdes podrían saltarse tope/orden o el límite del plan | No reutilizar sin cambios la bandera naranja; reglas específicas aprobadas en A/B. Rojo mantiene su propia excepción. |
| Modelos §4 omite la puerta explícita de revisión humana y aviso de contagio | Se ofrecería horario a T-20 sin revisión | Completar ramas en B con los RE existentes; probar T-20/T-22 y T-11 en UI y servidor. |
| Modelos §5 ilustra el evento al terminar, RE exige detectar exceso durante la atención | Cascada tardía o margen descontado dos veces | A fija una línea temporal con ejemplos; ampliar diagrama con disparos mientras sigue abierta, conservando reglas y vigilante. |
| Sin horario o sin cierre puntual modelado | Disponibilidad ficticia o festivo convertido en cierre recurrente | A resuelve representación y transición de datos; B aprueba antes de habilitar nueva agenda. |
| Horario modificado, ausencia o propuesta aceptada mientras otro reserva | Doble ocupación y citas fuera de horario | Misma política de candados y revalidación para todas las escrituras; pruebas MySQL concurrentes. |
| Cobertura y reasignación mezcladas | Veto indebido en ausencia o traslado sin aceptación en vivo | Servicios/casos separados RN-414 y RN-413/RN-428. |
| Avisos o cálculos remotos duplican urgencias y pausas | Dos ingresos o interrupción de un paciente que aún no está en clínica | A especifica transición derivación → ingreso/atención e idempotencia; no asumir presencia física por una solicitud del portal. No alterar este significado sin aprobación. |
| Datos del acompañante y consolidación | Atribuir propiedad, fuga o pérdida clínica | Decisión 4 y contrato de A; cuatro ramas de RE-4.15.3, permisos, FK completas y rollback. |
| Conocimiento sin validación clínica | Confundir funcionamiento técnico con certificación clínica | Mantener fuentes, avisos de apoyo y reservas T-16/T-20; no modificar conocimiento como atajo de agenda. |

**Decisiones y pendientes tras la respuesta del usuario (2026-10-17):**

1. **Decisión del usuario (2026-10-17) — alcance:** HU-4.10, HU-4.16, HU-4.17 y el reporte RE-4.7.3 quedan en v2.1. Corregir la nota contradictoria de HU-4.10 al alinear documentos. No adelantar el re-triage del propietario para cerrar M4.
2. **Decisión del usuario (2026-10-17) — quién agenda:** solo el veterinario y el propietario pueden agendar; el administrador no crea citas. Conservar sus funciones de configuración, horarios y ausencias. A inventaría todos los accesos de creación y B alinea HU-4.1/HU-4.13, RE y diagramas con esta decisión antes de que C4 retire el permiso en servidor y pantallas. No ampliar `calcular_triage_ajax` al administrador. Su capacidad actual en HU-4.1 y `Security.php` es la discrepancia a corregir, no el comportamiento aprobado para M4. No interpretar esta decisión sobre agendar como retirada de otros permisos administrativos no tratados.
3. **Decisión del usuario (2026-10-17) — atención sin reserva:** crear **siempre** una cita de llegada detrás del acceso, conservando el origen «llegada sin reserva», para reflejar su ocupación y evitar cruces. Ajustar HU-2.2/RE y sus flujos antes del código. Rojo sigue por urgencias. A debe proponer capacidad, orden y relación con el tope naranja cuando no cabe un no rojo; RN-422 no autoriza por sí sola sobrecupos verdes ilimitados. Esa política concreta todavía no está aprobada.
4. **Decisión del usuario (2026-10-17) — acompañante:** nombre y teléfono opcionales, sin pedir documento por defecto. Puede traer la mascota su propietario u otra persona; ser acompañante no acredita titularidad. Si la relación ya está verificada se reutilizan cuenta y ficha; los datos provisionales se usan cuando no puede verificarse la identidad/relación sin retrasar la atención. Atender sin exigir esos datos. Se conserva el acceso por personal autorizado de la clínica; A debe presentar la retención/anonimización para aprobación antes de B/C7b, sin inventar plazo ni borrar actos clínicos. No crear cuentas automáticamente.

**Decisión del usuario (2026-10-17) — aprobación del plan:** confirma dejar la propuesta con los acuerdos anteriores y guardar/subir la planificación. Quedan aprobados el plan y el inicio del inventario A; la capacidad del sobrecupo no urgente, la retención de datos y los contratos técnicos pendientes requieren su resolución antes de implementar las etapas afectadas. Las decisiones ya confirmadas en M2 y en el plan excepcional de HU-4.15 se conservan.

## 7. Tareas y verificación

- [x] Usuario aprueba alcance/roles/llegada/captura mínima y el plan (2026-10-17); decisiones en §6.
- [ ] Usuario crea rama del módulo; arquitecto prepara prompt **solo de A** con método §5.
- [ ] A entregada por ejecutor y revisada de forma independiente; contratos no resueltos vuelven al usuario antes de B.
- [ ] Cada subetapa tiene anexo «Anexo X — Resultado», autor, RE con prueba, comandos y pendientes; estado «entregada, pendiente de revisión».
- [ ] Revisor comprueba diff, riesgos y Modelos paso a paso; escribe «### X — Revisión (fecha)», veredicto, evidencia propia, hallazgos archivo/línea/RE o RN/reproducción y siguiente paso. No inventar ahora anexos o revisiones aprobadas.
- [ ] PHP completo, con y sin host; MySQL 8 si está disponible y/o MariaDB local, siempre **`zooki_test_base_v2`**; pruebas concurrentes con conexiones separadas. Si no hay servidor, declarar omisión y repetir antes del cierre, sin presentar SQLite como prueba de concurrencia MySQL.
- [ ] JS completo: `node --test "tests/Frontend/**/*.test.cjs"`; probar comportamiento de formularios, respuestas fuera de orden, doble envío, cambios de mascota/clínica, errores, actualización inmediata y estados de agenda. Un recorrido HTTP no sustituye JS.
- [ ] 26 casos T y O aplicables: O-01–O-05, O-07–O-11; O-06 queda pendiente de v2.1. Flujos límite, margen y tolerancia exactos; 10 recálculos de 3 minutos; bloque lleno; todos no pausables; propuesta vencida y aceptación en carrera.
- [ ] Migraciones repetidas, datos previos, consultas/tratamientos/adjuntos y trazabilidad de conciliación; aislamiento Norte/Sur y rechazos por contexto o titularidad.
- [ ] El usuario hace las 3–5 pruebas manuales de cada entrega en escritorio y celular; se conservan referencias de diseño y cortes RNF-16. No recorre el navegador el revisor.
- [ ] Antes de F: matriz RE → prueba real, HU/RE/RN/MER/Modelos coherentes, conteos y matrices actualizados si cambian criterios; una revisión documental y exportación PDF/Word al cerrar. Diagramas Mermaid renderizados.
- [ ] Actualizar versión e historial al cierre de la alfa; no subir versión por esta propuesta documental. Entregar comandos de commit uno por línea, sin `&&`; usuario hace commit antes del prompt siguiente.
- [ ] Respaldo, integración, tag y despliegue por usuario; revisar log completo de migración y conocimiento, base al día, `App::VERSION` en contenedor y versión en pantalla.

**Pruebas manuales previstas para la aceptación final** (se ajustarán a las entregas reales, sin darlas hoy por ejecutadas):

1. [ ] **Ana Norte admin y Carla Sur admin:** configurar franjas y tipos distintos; comprobar duración, margen, pausable y aislamiento. **Beto Norte vet** propone horario y no puede guardarlo directamente; Ana lo aprueba.
2. [ ] **Fabio Propietario:** agendar un verde y un amarillo con marcas/tiempo; nota sola no calcula; cancelar/reprogramar una cita propia. Probar T-20 sin oferta de horario hasta revisión y comprobar que no aparece información comercial del plan.
3. [ ] **Beto Norte vet:** dos naranjas y un tercero con bloque al tope, cambio de orden motivado; prolongar una atención y comprobar cascada, propuesta de cobertura en vivo y avisos. Para el destino se necesita otro veterinario de Norte habilitado como dato de prueba; **Diego Sur no cubre Norte por defecto**. Nunca se le asigna clínica ajena para evitar preparar el dato de prueba.
4. [ ] **Beto Norte vet y Ana Norte admin:** ingreso rojo con ficha conocida y provisional; pausa solo de tipo pausable, reanudación y consulta; completar/consolidar sin perder historia. Fabio prueba aviso de clínica cerrada y teléfono de urgencias desde el portal.
5. [ ] **Beto Norte vet:** llegada a los 8 y 15 minutos con tolerancia 10, opciones correctas, ajuste motivado y agenda actualizada. **Diego Sur vet y Gina Plataforma:** peticiones a una cita/ingreso de Norte rechazadas con sus contextos activos.

**Resultado de pruebas en esta propuesta:** no se ejecutaron suites: no hay implementación entregada para revisar. Se leyó especificación, esquema y puntos de entrada actuales; el inventario técnico completo es A. No se leyó `.env`, no se tocó ninguna base ni se cambió código, versión o documentación normativa.
