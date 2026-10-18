# M4 — Agenda inteligente y Grafo II

> Estado: **B1.1 revisada y aprobada (2026-10-18); H-B1-1 cerrado**. B1 y su diseño quedan aprobados con esta precisión de conciliación. Siguiente: B2 tras guardar la entrega; alpha.4 y SQL ejecutable aún intactos. No se declara funcionalidad implementada.
> Entrega: v2.0 · Fecha: 2026-10-17 · Salida propuesta: `2.0.0-alpha.5`.
> Escribe Codex; revisa Codex en una sesión independiente (corrección expresa del usuario, 2026-10-17). El autor del Anexo A y el arquitecto que firma su revisión son sesiones distintas.
> Rama actual comprobada: `v2/m4-agenda`. La rama la creó el usuario; el agente no ejecutó operaciones Git de escritura.

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

**Fuera de esta propuesta v2.0:** reporte de ausentismo RE-4.7.3; HU-4.10 (confirmar desde recordatorio), HU-4.16 (afinidad/reputación) y HU-4.17 (actualización de síntomas por el propietario): v2.1, conforme a la decisión confirmada de §6. O-06 se conserva como aceptación futura de HU-4.17, no se declara pasado por M4. Tampoco entran suscripción/cobro de HU-0.5, gestión general de especialidades, plantillas/bitácora de M8, carnet/QR ni rediseño global. Sí se prueba la excepción roja frente al estado de mora sin construir la suscripción.

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
| HU-4.15 | RE-4.15.1–RE-4.15.10 | RN-411, RN-415, RN-420, RN-421, RN-427; RN-G07, RN-G16, RN-G19, RN-110, RN-206, RN-207 | C4a, C7a, C7b | O-01/O-02/O-09/O-10; libre → pausable → espera; sin cuotas/mora; ficha y cuenta existentes o provisional; conciliación transaccional sin perder actos. |
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
| HU-2.2 | RE-2.2.1–RE-2.2.5 | RN-102, RN-203, RN-208, RN-411, RN-415 | C8b | Origen «llegada sin reserva», cita obligatoria y atención coherentes, sin duplicado ante doble envío. Sin sobrecupo no urgente automático; espera/alternativas y origen conservado, sin modificar históricos NULL. |
| Transversal | RE-T.10.1, alcance RNF-11/RNF-16 y requisitos de acceso ya vigentes | RN-G01, RN-G02, RN-G13, RN-G19 | Todas, D | Contexto activo, CSRF, clínica y titularidad; A concreta RE transversales adicionales; revisión de interfaz en móvil/tableta/escritorio. |

Los criterios normativos permanecen en `documentacion/`; no se cambian silenciosamente en esta tabla. Las nuevas reglas de llegada y las decisiones no cubiertas requieren criterio y aprobación antes del código.

## 3. Etapas y dependencias

**Contrato actual:** las propuestas históricas A/A.1 se conservan como evidencia; prevalecen §6 y el Anexo B1. M está confirmada, C descartada; no usar A.8 como fuente de pendientes ya resueltos.

**Asignación corregida por el usuario (2026-10-17):** escribe Codex; revisa Codex en una sesión independiente. El autor de A no escribe su revisión. Trabajo por turnos en el mismo checkout, sin agentes paralelos sobre archivos compartidos. La columna de autor de las etapas siguientes se entiende con esta corrección, no como un encargo a Claude Code.

| Etapa | Entregable comprobable | Depende de | Escribe | Revisa |
|---|---|---|---|---|
| **A. Inventario y contrato** | Solo lectura del código y anexo de diseño: rutas internas/portal, calendario, pacientes, atención, tipos, configuración, correos, vigilante y pruebas; regresiones conservadas; trazabilidad por RE; diferencias con Modelos §2–§8. Proponer algoritmo de intervalos/cascada, orden de candados, cierre puntual, revisión humana de triage, cola roja sin vet aún y persistencia mínima; resolver datos del acompañante y selección entre veterinarios igualmente compatibles antes de B. | Aprobación del plan | Codex | Codex independiente |
| **B. Datos y contratos documentales** | Migraciones solo donde A demuestre huecos; MER/drawDB/esquema alineados; relleno conservador e idempotencia. Ajustes de HU/RE/RN/diagramas aprobados. No se habilitan rutas parciales. | A revisada y decisiones de datos aprobadas | Codex | Codex independiente |
| **B1. Contrato documental y diseño de datos** | Alinear HU/RE/RN/Modelos con §6, matriz de permisos y propuesta mínima de esquema con campos/índices/relleno. Cerrar un único protocolo HC/candados. Sin SQL ejecutable ni código; revisar el diseño antes de B2. | A/A.1 revisadas y decisiones de §6 | Codex | Codex independiente |
| **B1.1. Corrección de conciliación** | Contrato único de identidad vigente, solicitudes activas/terminales, contexto/revisión, eventos, idempotencia y rollback; revisada y aprobada (2026-10-18), H-B1-1 cerrado. | H-B1-1 de la revisión B1 | Codex | Codex independiente |
| **B2. Esquema y migraciones** | Ejecutar únicamente el diseño revisado de B1: migración nueva repetible, esquema ejecutable/MER/drawDB coherentes y pruebas de instalación/actualización. No habilita rutas de aplicación. | B1 revisada y decisiones de datos resueltas | Codex | Codex independiente |
| **C1. Marca y tipos** | HU-7.4 completa: configuración, duración/margen/pausable, teléfono; propagación de marca y aislamiento; mismo diseño y validación en vivo. | B | Codex | Codex independiente |
| **C2. Horarios** | HU-4.11: admin configura, vet propone y admin resuelve, privacidad entre clínicas, citas afectadas marcadas. Antes de C5 esas marcas quedan visibles como pendientes, sin fingir reajuste automático ya terminado. | C1 | Codex | Codex independiente |
| **C3. Disponibilidad y reserva** | Una lógica de intersección, resta de ausencias/cierres y ocupación por duración+margen para sugerencias, reserva y reprogramación; candados y pruebas concurrentes; nunca disponibilidad permanente por falta de horario. Cierre de HU-4.9 y base de HU-4.6/4.8. | C2 | Codex | Codex independiente |
| **C4a. Agendar con triage** | Interno y portal comparten captura/buscador C4 de M2; cuatro ramas, excepción roja, clínica cerrada, revisión humana y contagio, persistencia y notificaciones. Rojo deriva a circuito clínico; no crea reserva ordinaria ni promete atención por el solo cálculo remoto. | C3 | Codex | Codex independiente |
| **C4b. Sobrecupos y cambios** | Tope y orden naranja con motivo; primer espacio amarillo/elección verde; conflictos sobrevenidos; cancelación/reprogramación interna y portal con misma lógica; revisión previa al horario y registro del nivel aprobado por vet. | C4a | Codex | Codex independiente |
| **C5a. Grafo II y cascada** | Secuencia/conflicto/emparejamiento; horas estimadas sin destruir horas pactadas o reales; margen, huecos y fin de franja. Disparos durante atención y al finalizar, sin depender de abrir calendario; procesador idempotente. | C4b | Codex | Codex independiente |
| **C5b. Reasignación en vivo** | Propuesta/aceptación/rechazo/vencimiento, siguiente candidato y reprogramación con propietario; revalidación al aceptar; avisos agrupados RN-429 y auditoría. Procesa marcas de horario C2. | C5a | Codex | Codex independiente |
| **C6. Ausencias y cobertura** | Alta admin/solicitud propia vet, bloqueo exacto y cobertura sin veto; sin cobertura queda pendiente de reprogramar y se ofrecen alternativas; auditoría y avisos. No reutilizar el veto de C5b. | C5b | Codex | Codex independiente |
| **C7a. Atención roja y pausas** | Libre/pausable/espera, llegada directa y derivación del portal, identidad conocida, prioridad máxima, excepción a plan/mora; pausa y reanudación antes de siguientes; consulta y cascada coherentes. | C6 | Codex | Codex independiente |
| **C7b. Ficha provisional y conciliación** | Cuatro ramas de identidad de HU-4.15; captura mínima, tarea pendiente, completar o consolidar sin perder actos ni cuenta nueva, carnet inactivo y datos del acompañante protegidos. | C7a y contrato de datos aprobado | Codex | Codex independiente |
| **C8. Ajustes y llegada tarde** | HU-4.18 completa (más lo adelantado por revisión humana), historial de reclasificaciones; hora llegada, opciones por tolerancia y capacidad restante, no asistió y recálculo. | C7b | Codex | Codex independiente |
| **C8b. Llegada sin reserva** | HU-2.2 según decisión 3 confirmada: cita obligatoria, origen distinguible y ocupación real; nunca convertir un verde en naranja para eludir las reglas. | C8 y criterios de capacidad aprobados | Codex | Codex independiente |
| **D. Correcciones y verificación final** | Corregir hallazgos, regresión M2/agenda/portal, comparación de diagramas, aislamiento y pruebas completas; 3–5 pruebas manuales por entrega. Sin rediseño general. | Etapas anteriores revisadas | Codex | Codex independiente |
| **F. Cierre y despliegue** | Matriz final por RE, estados documentales, una revisión por documento, PDF/Word y diagramas verificados; versión/historial `2.0.0-alpha.5`; guía de respaldo, integración y comprobación de producción. El usuario ejecuta Git y despliegue. | D y pruebas del usuario | Codex | Codex independiente |

Las filas C4/C5/C7 de la matriz agrupan sus subetapas a/b. Si el inventario muestra que una fila no cabe en una entrega verificable, se divide antes de emitir el prompt; no se implementan dos por comodidad. Cada prompt sigue literalmente la estructura de `agentes/metodo.md` §5 y cita sus diagramas, alcance, exclusiones y pruebas concretas.

## 4. Datos y migraciones

**Precisión vigente B1.1:** el Anexo B1.1 corrige exclusivamente la conciliación incompleta de B1. Todas las solicitudes afectadas usan la identidad vigente; las evaluaciones históricas conservan procedencia y no autorizan comandos sobre el nuevo contexto. No cambia campos/relaciones del destino de 58 tablas ni las 52 ejecutables.

**B1 actual:** destino mínimo en MER/drawDB de 58 tablas (52 existentes + seis nuevas); no se modifican 01_schema.sql/02_semilla.sql ni 03–07. Diseño/campos/índices/rellenos concretos y protocolo único en Anexo B1; B2 solo después de su revisión. Las alternativas de A.4 quedan históricas, sin libertad de mezclar GET_LOCK y candado clínico al implementar.


- **Se conserva la v2 desplegada.** No hay reinicio de base ni migración de datos v1. B1 alinea `specs/HU-4.15-ingreso-emergencia.md` con la ampliación aditiva de alpha.4.
- `01_schema.sql` ya contiene las entidades principales; no recrearlas. La próxima migración disponible es **08**, prevista como `08_agenda_grafo_ii.sql` en B1; contenido sujeto a revisión antes de B2. Si se publica una 08 antes, se toma el siguiente número libre.
- **Huecos resueltos documentalmente en B1, pendientes de implementación:** horario estimado y último aviso RN-429; marca de reajuste/reprogramación pendiente; solicitudes de ausencia; cierre puntual de clínica; pertenencia de sobrecupo a bloque; orden/prioridad de espera y pausa/reanudación; urgencia sin veterinario aún (`citas.id_veterinario` hoy es obligatorio); revisión humana antes de reservar. No se aprueban aquí columnas nuevas ni se introduce NULL en una FK sin revisar todos sus lectores.
- Decidir qué estado debe persistir entre peticiones y qué se recalcula. No usar texto de observaciones como estado operativo ni auditoría como sustituto accidental de una cola. Toda ampliación pasa primero por MER/drawDB, criterio de aceptación y aprobación si cambia alcance.
- Conservar instantáneas de duración/margen y triage calculado frente a ajustado, marcas y tiempos por evento, contexto/versión del conocimiento; no copiar tratamientos protegidos al contexto de la cita.
- B1 define relleno verificable para citas antiguas sin duración/tipo y para clínicas/veterinarios sin horario. No inventar síntomas ni triage retrospectivos ni asignar automáticamente jornada completa. No modificar horarios pactados ni citas completadas para hacerlas encajar.
- **Concurrencia:** serializar la comprobación y la escritura incluso cuando no existe todavía una cita en el hueco. Un candado solo sobre citas existentes es insuficiente. B1 define orden estable para vet/mascota/clínica y plan, reserva, sobrecupo, aceptación y conciliación; MySQL prueba dos conexiones y rollback. Las excepciones de lectura de ocupación entre clínicas quedan encapsuladas y no devuelven datos ajenos.
- Migraciones idempotentes y probadas dos veces sobre esquema nuevo y base previa con datos; sin editar 03–07 publicadas; sincronizar `01_schema.sql`, semilla si corresponde, MER y drawDB. Semilla solo inserta faltantes.
- Despliegue automático por migrador; primero migración satisfactoria, luego conocimiento. En XAMPP el usuario ejecuta `php scripts/migrar.php` y después `php scripts/cargar_conocimiento.php`. No se lee `.env` ni se ejecutan pruebas contra `zooki_v2_prueba`.
- Recuperación: respaldo antes del despliegue, cambios aditivos donde sea posible y comprobación de compatibilidad antes de volver al código anterior; no prometer rollback de código seguro tras modificar estados/FK sin demostrarlo.

## 5. Código, permisos y pantallas

- **Modelos/helpers:** partir de `models/Cita.php`, `models/HorarioClinica.php`, `helpers/HorarioAtencion.php`, `helpers/MotorTriage.php`, validadores, límites y vigilante existentes. Separar cálculo puro del Grafo II, acceso a disponibilidad y coordinación transaccional; contrato y llamadores concretos en B1.1; archivos B2 en B1.5. Reutilizar motor/buscador de M2, sin otro cálculo de triage en JS.
- **Controladores/rutas:** `controllers/CitaController.php`, consulta y los controladores de configuración/portal implicados; registrar todas las acciones nuevas en `public/index.php` y `helpers/Security.php`. Tarea programada para vencimientos/retrasos sin navegador abierto; reaprovechar infraestructura existente sin duplicar avisos de atenciones abiertas.
- **Administrador:** horario, aprobación de propuestas, ausencias/cobertura y configuración de su clínica. No agenda, no inicia ni completa consulta. La retirada de la creación de citas se verifica también por petición directa y en todos los accesos existentes, no solo ocultando botones.
- **Veterinario:** sus propuestas/solicitudes y sus atenciones; ajuste/reordenación motivados y respuesta a propuestas dirigidas a él, con alcance exacto revisado en A. No guarda directamente horario recurrente. Nunca atiende con contexto de administrador.
- **Propietario:** solo sus mascotas y citas, en clínica legítimamente vinculada; sin poder autodeclarar prioridad ni exponer estado comercial de la clínica. Revalidar vínculo, titularidad, actividad y datos del cálculo al guardar.
- **Plataforma:** Gina no obtiene permiso clínico por ser superadministradora. Probar denegación con su contexto activo.
- **Pantallas:** conservar calendario, gestión de citas, atención, Pacientes, formularios de configuración y portal. Reutilizar `public/js/consulta-sintomas.js` y extraer componentes compartidos cuando corresponda; no duplicar el selector. Validación/restricción en vivo, mensajes junto al campo, datos `data-*`, sin JS/CSS en línea ni confirmaciones para decisiones reversibles ya motivadas.
- Las lecturas globales de alergias/alertas se mantienen conforme a RN-113. La ocupación y horarios de un vet de varias clínicas se verifican internamente con respuesta genérica; no amplían acceso al calendario ni historia de la otra clínica.
- HU-7.4 incluye comprobar la marca en todas las salidas que exige RE-7.4.4; no se declara completa solo por mostrar logo en configuración. Las descargas públicas del portal documental no pasan a ser documentos personalizados de una clínica.

## 6. Riesgos y decisiones pendientes

**Lectura vigente en B1:** las confirmaciones siguientes prevalecen sobre los «pendiente» históricos. Las reglas de producto de capacidad, revisión, naranja, ausencias, política M, urgencias, contacto, marca y conciliación ya están resueltas; el orden de la espera no urgente también queda confirmado: amarillo antes de verde y llegada física/ID dentro del color. El esquema/protocolo de B1 quedan pendientes de revisión técnica, no de una aprobación clínica ficticia.


Las diferencias normativas de esta tabla se detectaron en A y se alinean documentalmente en B1; los riesgos ejecutables persisten hasta integrar C.

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

**Decisión del usuario (2026-10-17) — orden de rojos presentes:** entre varios rojos en espera, usar llegada física como orden inicial; el veterinario puede cambiarlo con motivo y auditoría. Si otro rojo espera mientras existe una atención pausada, el veterinario decide la prioridad. No automatizar interrupciones anidadas ni permitir que una derivación remota sin llegada desplace pacientes presentes. Se conserva RN-427 para reanudar la atención pausada antes de las citas siguientes cuando no medie esa decisión clínica de urgencia.

**Decisión del usuario (2026-10-17) — contacto del acompañante:** nombre y teléfono se usan para atender y resolver identidad; al finalizar esa necesidad, se eliminan/anonimizan esos datos de contacto conservando ingreso, identificadores y todos los actos clínicos. Si aún es necesario conservarlos, debe quedar un motivo de conservación. No se fija un plazo automático en días. B debe precisar el disparador verificable, permisos y tratamiento de copias/auditoría sin convertir esta decisión de producto en una aprobación jurídica.

**Decisión del usuario (2026-10-17) — marca e historial:** marca de cada clínica en sus correos y documentos; historial multi-clínica con cabecera de Zooki y procedencia identificada por acto. Reclasificaciones visibles al personal autorizado de la clínica sobre sus propios casos, sin ampliar acceso a casos ajenos.

**Decisión del usuario (2026-10-17) — permisos de conciliación:** tras explicar la ficha provisional, confirma que tanto administrador como veterinario autorizado de la clínica pueden completar/consolidar después de verificar primero la titularidad. El propietario aporta la verificación, sin fusionar por su cuenta actos clínicos ni adjudicarse una mascota por conocer su ID. El administrador no recibe por ello permiso para registrar/modificar consultas o crear citas; completar/consolidar identidad usa su acción específica, transacción y auditoría, conservando clínica y autor de los actos. B1 debe fijar los permisos de cada acción y sus criterios de verificación antes de implementarlos.

**Decisión del usuario (2026-10-17) — llegada no urgente sin hueco:** registrar la llegada y ofrecer el próximo espacio compatible u otro veterinario disponible de la misma clínica; no crear sobrecupos automáticos para verdes o amarillos ni iniciar consulta hasta crear su cita de llegada. No se aprueba la alternativa de un cupo adicional por bloque. La cita obligatoria para toda atención y la derivación roja se conservan; el orden de solicitudes en espera se concreta en la confirmación posterior de este apartado.

**Decisión del usuario (2026-10-17) — orden de llegadas no urgentes en espera:** amarillo antes de verde y, dentro de cada color, llegada física ascendente y luego id_solicitud ascendente para desempatar. Confirmación recibida a la pregunta de B1. No cambia el color, no crea sobrecupos ni permite consulta sin cita; se revalida capacidad completa antes de asignar.

**Decisión del usuario (2026-10-17) — aprobación de ausencias:** una solicitud del veterinario permanece pendiente y no tapa disponibilidad hasta que el administrador la apruebe. La ausencia registrada directamente por el administrador es efectiva de inmediato y activa búsqueda de cobertura conforme a RN-414. B alineará HU/RE/Modelos antes del código.

**Decisión del usuario (2026-10-17) — revisión previa al horario:** todo resultado de triage que requiera revisión humana debe ser revisado por un veterinario, que confirma o ajusta el nivel, antes de ofrecer un horario reservable. Incluye «Otro / no sé» y casos sin cobertura suficiente. Los rojos conservan derivación inmediata y no esperan en esa puerta para recibir el aviso/derivación de emergencia. B debe dejar explícito este orden y sus pruebas.

**Decisión del usuario (2026-10-17) — bloque y consumo de cupo naranja:** después del ejemplo, confirma el funcionamiento: el tope configurable (dos por defecto) corresponde a la franja continua original del veterinario para esa fecha; una ausencia parcial no multiplica ese tope. Los sobrecupos atendidos/completados siguen consumiendo el cupo; cancelados/no_asistio lo liberan. Un tercero sobre el tope genera aviso y decisión del personal, sin inserción automática que lo eluda. La pregunta posterior sobre ausencias por horas se aclara con RE-4.12.1/2, que ya exige día completo y franja parcial; no se interpreta como decisión de retirar esa capacidad del alcance.

**Decisión del usuario (2026-10-17) — política temporal M:** se adopta la alternativa M revisada en A.1: el inicio real tardío y las pausas desplazan el fin nominal y su margen único; la duración activa realizada y los sellos reales se conservan. Ejemplo confirmado: inicio 09:00, servicio activo de 30 minutos, pausa 09:10–09:30 y fin clínico 09:50 dejan el extremo de ocupación a las 10:00 con margen de 10 minutos. No se reinicia el margen por pausa ni por recálculo; el exceso activo consume ese margen conforme a A.3. La alternativa C se conserva solo como antecedente descartado. B alineará los criterios/diagramas afectados antes del código; las demás propuestas de A.8 no quedan aprobadas por esta decisión.

**Decisión del usuario (2026-10-17) — comandos de entrega:** no incluir `git diff --cached --stat` en los comandos para pegar; usa también CMD y pide únicamente los pasos necesarios para guardar/subir, uno por línea. Preferencia registrada en agentes/metodo.md §8; no cambia las verificaciones que debe realizar el revisor.

1. **Decisión del usuario (2026-10-17) — alcance:** HU-4.10, HU-4.16, HU-4.17 y el reporte RE-4.7.3 quedan en v2.1. Corregir la nota contradictoria de HU-4.10 al alinear documentos. No adelantar el re-triage del propietario para cerrar M4.
2. **Decisión del usuario (2026-10-17) — quién agenda:** solo el veterinario y el propietario pueden agendar; el administrador no crea citas. Conservar sus funciones de configuración, horarios y ausencias. A inventaría todos los accesos de creación y B alinea HU-4.1/HU-4.13, RE y diagramas con esta decisión antes de que C4 retire el permiso en servidor y pantallas. No ampliar `calcular_triage_ajax` al administrador. Su capacidad actual en HU-4.1 y `Security.php` es la discrepancia a corregir, no el comportamiento aprobado para M4. No interpretar esta decisión sobre agendar como retirada de otros permisos administrativos no tratados.
3. **Decisión del usuario (2026-10-17) — atención sin reserva:** crear **siempre** una cita de llegada detrás del acceso, conservando el origen «llegada sin reserva», para reflejar su ocupación y evitar cruces. Ajustar HU-2.2/RE y sus flujos antes del código. Rojo sigue por urgencias. A debe proponer capacidad, orden y relación con el tope naranja cuando no cabe un no rojo; RN-422 no autoriza por sí sola sobrecupos verdes ilimitados. Esa política concreta todavía no está aprobada.
4. **Decisión del usuario (2026-10-17) — acompañante:** nombre y teléfono opcionales, sin pedir documento por defecto. Puede traer la mascota su propietario u otra persona; ser acompañante no acredita titularidad. Si la relación ya está verificada se reutilizan cuenta y ficha; los datos provisionales se usan cuando no puede verificarse la identidad/relación sin retrasar la atención. Atender sin exigir esos datos. Se conserva el acceso por personal autorizado de la clínica; A debe presentar la retención/anonimización para aprobación antes de B/C7b, sin inventar plazo ni borrar actos clínicos. No crear cuentas automáticamente.

**Decisión del usuario (2026-10-17) — aprobación del plan:** confirma dejar la propuesta con los acuerdos anteriores y guardar/subir la planificación. Quedan aprobados el plan y el inicio del inventario A; la capacidad del sobrecupo no urgente, la retención de datos y los contratos técnicos pendientes requieren su resolución antes de implementar las etapas afectadas. Las decisiones ya confirmadas en M2 y en el plan excepcional de HU-4.15 se conservan.

## 7. Tareas y verificación

- [x] Usuario aprueba alcance/roles/llegada/captura mínima y el plan (2026-10-17); decisiones en §6.
- [x] Rama del módulo comprobada: `v2/m4-agenda`; encargo recibido **solo de A**, con la corrección de autor y revisor del usuario.
- [x] A entregada por ejecutor y revisada de forma independiente (2026-10-17), con corrección A.1 antes de aprobar el contrato; ver revisión al final. Decisiones de producto pendientes antes de B.
- [x] B1 entregada, pendiente de revisión independiente (2026-10-17); contrato y destino de datos en Anexo B1.
- [ ] B2: no iniciada; requiere revisión de B1 antes de migración 08.
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

**Resultado de la planificación inicial:** no se ejecutaron suites en esa planificación. La verificación del inventario A se registra en su anexo; no acredita implementación de M4.

## Anexo A — Resultado

**Fecha:** 2026-10-17.
**Autor:** Codex, ejecutor de A en esta sesión.
**Revisor previsto:** Codex, en otra sesión independiente.
**Corrección expresa del usuario (2026-10-17):** «escribe Codex y revisa Codex» sustituye la asignación inicial a Claude Code.
**Estado:** entregada, pendiente de revisión.
No contiene una revisión ni una aprobación de esta entrega.

Solo se modifican este plan y agentes/estado.md.
El código, esquema, migraciones, conocimiento, documentación normativa, diagramas, versión y exportaciones se conservan.
Los nombres de servicios, campos y pruebas que siguen son propuestas de diseño, no archivos ni funcionalidades implementados.
Las diferencias normativas se dejan para resolver en B; las decisiones de producto no confirmadas no se atribuyen al usuario.

### A.1. Riesgos comprobados y diferencias documentales

| Evidencia actual | Diferencia o riesgo | Ajuste que debe preparar B, antes del código afectado |
|---|---|---|
| models/Cita.php: registrarEvaluada, exigirHorarioLibre, ocupadas, intervalos y sugerencias | Copia margen, pero compara solo duración; sugerencias recorre 08:00–18:00, sin franjas del veterinario, ausencias ni cierres. El cliente cruza dos listas distintas. | Un contrato de disponibilidad completa, incluidas mascota y ocupación entre clínicas. RE-4.6.2, RE-4.9.1/2, RE-4.13.4; Modelos §2/§4. |
| helpers/LimitePlan.php: transaccion bloquea clinicas; uq_cita_veterinario_horario solo impide comienzos iguales | El candado actual serializa altas de la misma clínica; dos clínicas distintas pueden insertar intervalos solapados de un veterinario o mascota globales con comienzos distintos. CitaTest simula la carrera saltando una validación en una sola conexión. | Candados estables globales de A.4 y prueba con dos conexiones reales. No declarar resuelta la concurrencia por el índice. |
| models/Recordatorio.php:91–111, citasDeManana; VinculosPropietario::desvincular | La consulta de correo no comprueba directamente propietario_clinica activo, a diferencia de ContactoPropietario. El flujo normal sí impide desvincular con citas sin resolver y desactiva los vínculos de mascotas. No se demuestra una fuga alcanzable por ese flujo; falta una defensa/prueba explícita de elegibilidad en la consulta de recordatorios. | Proponer P11 con estado inconsistente propietario_clinica inactivo/mascota_clinica activo y con cambios concurrentes; exigir cero envíos y unificar elegibilidad al tocar notificaciones en C4a/C4b. No se declara vulnerabilidad ni envío indebido confirmado. |
| helpers/Security.php:408; CitaController::registrarAjax/veterinarioDeLaReserva; AutorizacionRolTest:243 | Administrador aún puede crear por POST directo. HU-4.1 lo admite, HU-4.13 y Modelos §4 lo excluyen. | Aplicar decisión confirmada: solo vet/propietario crean. Alinear actores, pruebas y permisos de creación. Conservar permisos administrativos restantes. |
| RE-2.2.1 exige consulta sin cita; RN-208 propone ese acceso para atender un cambio de profesional | La decisión de crear siempre una cita de llegada cambia ese criterio. Usar el acceso para esquivar una cita activa podría duplicar ocupación. | Ajustar HU-2.2 y sus RE, RN-208 y Modelos §3: origen sin reserva, cita obligatoria nueva; si ya hay cita activa, reasignación legítima o continuidad, sin una segunda llegada. Históricos sin cita se conservan. |
| Modelos §4 pasa de color a límite/horario; §6 asigna o pausa tras el rojo remoto | Falta puerta de revisión humana (RE-2.11.5); T-20 podría recibir horario. El rojo remoto podría pausar otra atención sin presencia física. | Introducir revisión previa y separar derivación remota, llegada acreditada y atención. §3/§4/§6 deben compartir la transición de A.5. No cambiar el amarillo esperado de T-20. |
| Modelos §5 calcula contra fin+margen y después pregunta si supera margen | Lectura ambigua que puede descontarlo dos veces; solo dibuja finalización, aunque RE-4.5.2 y RN-412 exigen detectar durante la atención. | Expresar las fórmulas y ejemplos de A.3; disparos de reloj, inicio, fin, pausa, llegada y cambios de disponibilidad. |
| Modelos §7 y §13.4; modelos/controladores existentes | Solo existe la configuración recurrente de la clínica; no el horario del vet ni propuestas operativas. | Implementar propuestas con aprobación y revalidación al aplicar; marcar citas afectadas atómicamente. No entregar una interfaz que aparente reajuste terminado antes de C5. |
| Modelos §8 comienza solo con administrador; RN-414 también admite solicitud propia del vet | Falta solicitud y resolución en el flujo. Cobertura por ausencia no requiere aceptación del destino. | Agregar solicitud propia y su resolución propuesta, separadas del veto/plazo de la reasignación en vivo. Confirmar la política de aprobación de ausencia en A.8. |
| Nota de HU-4.8 y calendario semanal | Horarios recurrentes y ausencias no representan un festivo único de toda la clínica. | Cierre puntual independiente; no apagar todos los lunes ni inventar ausencia por cada vet. RE-4.8.2/3. |
| HU-4.10 tiene nota v2.0; plan de entregas la pone en v2.1 | Contradicción ya resuelta por el usuario. | Corregir nota; HU-4.10/4.16/4.17 y RE-4.7.3 siguen en v2.1; O-06 queda pendiente. |
| RE-4.18.4 dice historial del propietario; aceptación dice que la clínica ve sus reclasificaciones | Ambigüedad de destinatario y alcance, sin flujo dedicado de ajuste/llegada tarde. | Propuesta: historial de casos asociados al propietario, visible al personal autorizado de esa clínica, sin sanción ni puntuación automática. Confirmar con usuario; precisar HU/RE y ramas de Modelos §4/§5/§6. No ampliar permisos por interpretación. |
| MER §6 afirma que solo se persisten reasignaciones y propuestas | Ya existen horas reales, síntomas y avisos; además hacen falta estados operativos recuperables. | Describir persistencia mínima de A.6; el grafo sigue reconstruyéndose en memoria. |
| specs/HU-4.15-ingreso-emergencia.md §2 habla de migración conservadora v1 | Ya se desplegó la base v2; ingresos_emergencia existe en los tres esquemas. | Sustituir la nota de transición v1 por ampliación aditiva v2, sin recrear tabla ni migrar datos de v1. |

Las diferencias nuevas quedan expuestas aquí para revisión del usuario antes de decidirlas.
A no corrige diagramas ni convierte estas propuestas en RE aprobados.
Tampoco aplica literalmente el comentario histórico de la revisión C4 de M2 que permitía ampliar roles «si» otro personal agendaba: la decisión vigente excluye al administrador.

### A.2. Inventario del código y regresiones que se conservan

| Área y archivos | Métodos o entradas concretos | Funciona hoy / conservación / brecha |
|---|---|---|
| models/Cita.php | registrar, registrarEvaluada, tiemposDelTipo, getById, paraPropietario, listarRango, listarTodas, veterinariosActivos, getTiposCita, getTipoCitaById | Reserva verde y guarda instantánea de duración/margen; valida clínica, vínculo y veterinario; lee por contexto y propietario. Conservar denegación auditada y contactos condicionados a vínculo. No guarda aún episodios ni ejecuta las cuatro ramas. Tipo omitido usa 30 minutos; tipo inactivo merece revalidación explícita en M4. |
| Mismo modelo | sugerencias, exigirHorarioLibre, ocupadasDelVeterinario, ocupadasDeLaMascota, ejecutarReserva, reprogramar | Detecta solapes secuenciales de vet y mascota entre clínicas; convierte duplicado en HorarioOcupado. Excluye sobrecupos por completo y no aplica margen/horarios reales a la ocupación; no basta para Grafo II. Reprogramar conserva instantáneas, pero falta transacción común, disponibilidad completa y control de cuota al cambiar mes. |
| Mismo modelo | iniciarAtencion, completarAtencion, marcarNoAsistio, confirmar, cancelar, transicion | Transiciones condicionales evitan reactivar canceladas y doble inicio; conserva sellos reales. Pausada existe solo en ENUM, fuera de ESTADOS_EN_ATENCION; no hay pausa/reanudación ni llegada registrada. |
| controllers/CitaController.php | registrarAjax, registrar, veterinarioDeLaReserva, fechaYHoraFuturas, exigirHorarioLaboral | Valida fecha/hora, motivo, vet propio y horario inicial de clínica; aviso por otra cita del día con ignore_warning. Solo crear cambia de roles; no inferir pérdida de supervisión/reprogramación del admin. |
| Mismo controlador | listarSemanaAjax, eventoDeCita, listarTodasCitasAjax, getCitaAjax, listarVeterinariosAjax, listarTiposCitaAjax, getSugerenciasHorarioAjax | Calendario acota citas propias del vet y prevención de clínica; admin supervisa todas. Conservar eventos de vacunas/desparasitación, sin correos en calendario. Falta incluir prioridad, estimadas, pausas y pendientes con acceso mínimo. |
| Mismo controlador, portal | portalVeterinariosAjax, portalTiposCitaAjax, portalHorasAjax, portalSugerenciasAjax, agendarDesdePortalAjax, clinicaDelPortal | Clínica elegida debe estar vinculada; ficha propia; alta de mascota_clinica y reserva dentro de LimitePlan, con rollback si falla. Conservar selección multi-clínica y mensaje neutral comercial. No hay reprogramación propia: reprogramarCitaAjax no admite contexto propietario. |
| Mismo controlador, cambios y atención | reprogramarCitaAjax, cancelarAjax, confirmarAjax, iniciarAtencionAjax, marcarNoAsistioAjax, atencion | Admin reprograma/reasigna; vet solo las suyas; propietario cancela las propias con vínculo activo. Inicia solo asignado, día y ventana; continúa en_curso/sin_cerrar. Mantener cierre mediante consulta y rechazo de cerrar sin consulta. |
| models/Consulta.php y controllers/ConsultaController.php | registrar/escribir, exigirCitaParaConsulta, completarCita, asignarNumeroHistoria, tomarCandadoNumeracion; registrarAjax, listar, listarHistorialAjax | Consulta, síntomas, tratamientos y adjuntos se guardan de forma atómica; cierre de cita y numeración HC; rollback de archivos movidos. Hoy id_cita puede ser NULL. Conservar M2 y HC por clínica; C8b cambiará solo nuevas atenciones sin reserva, sin rellenar ficticiamente las históricas. |
| models/ConsultaSintoma.php; helpers/ValidadorClinico.php; models/ArchivoClinico.php y Tratamiento.php | codigosDeEntrada, nodosActivos, insertarEnConsulta, deConsultas; validación/adjuntos/tratamientos actuales | Síntomas de consulta opcionales, como códigos, no son todavía episodios temporales de agenda. Reutilizar buscador/validación permitida; no confundir sus dos contratos ni exigir tiempos nuevos a toda consulta por inferencia. Conservar alertas y prescripción protegida de M2. |
| helpers/MotorTriage.php, BuscadorSintomas.php, ValidadorTriage.php, ValidadorContextoClinico.php; models/GrafoConocimiento.php, PacienteDiagnostico.php; controllers/TriageController.php | MotorTriage::calcular, BuscadorSintomas::buscar, ValidadorTriage::episodios; TriageController::buscarAjax/calcularAjax; contexto/instantanea | Cálculo puro y búsqueda probados; API sin escrituras para vet/propietario, contexto y conocimiento vigentes, alergias globales con RN-113. No tiene captura en Agendar ni revisión operativa ni avisos de aislamiento. |
| models/ModeloClinica.php, ModeloHistoria.php, Mascota.php, MascotaPropietario.php, VinculosPropietario.php, HistoriaPropietario.php | enClinicaDelPropietario, conMismoAlcance, exigirMascotaActiva; Mascota::vincular; ficha; clinicas/horarios; citas/citasDeSusMascotas/detalleCita | Aislamiento centralizado y titularidad; autorización de historia y contactos. Conservar prueba del vínculo en cada escritura, sin confiar en id_clinica del cliente. Lectores de citas usan JOIN a usuarios vet, por eso no se propone volver nula esa FK para la cola roja. |
| helpers/LimitePlan.php y Transaccion.php | consumir, transaccion, uso; Transaccion::ejecutar | Fila estable de clínica y savepoints; límite mensual por fecha de cita, cancelación libera cuota; rojo evaluado exceptuado, mora cae a gratuito. Conservar y ampliar orden de candados; no sustituir por un contador en JS. |
| models/HorarioClinica.php y controllers/HorarioClinicaController.php | listar/dia/guardar/restaurar; getHorariosAjax, guardarHorariosAjax, restaurarPorDefectoAjax, validarHorarioLaboral, obtenerHorasDisponibles, obtenerHorasDisponiblesAjax | Semana propia con mañana/tarde, validación y guardado completo, restauración sin borrar filas. ValidarHorarioLaboral solo comprueba inicio; obtenerHorasDisponibles no comprueba ocupación ni duración final. No hay modelos/controladores operativos de horario vet, ausencias, propuestas o reasignaciones. |
| models/CatalogoClinica.php, CopiaCatalogosClinica.php; helpers/ValoresInicialesClinica.php | tiposCita/tipoCita, copiar/copiarTabla; TIPOS/HORARIOS | Tipos por clínica y copia inicial sin duplicar; no hay CRUD de marca/tipos/pausable. La copia omite margen/pausable y usa defaults SQL (10/1); no acredita decisiones clínicas sobre cirugía. C1 debe hacerlos explícitos y preservar personalizaciones. |
| views/admin/horarios.php, public/js/horarios.js; controllers/PerfilController.php | admin_configuracion; mostrar/actualizarAjax del perfil | Configuración actual solo de horario de clínica; perfil actual sin solicitud/gestión de horario o ausencia. Conservar auto-guardado, errores de franja y diseño; no agregar gestión de especialidades/reseñas v2.1. |
| views/vet/calendario.php, public/js/calendario.js | vet_agenda; abrirCitaModal, postCita; registrar_cita_ajax, reprogramar_cita_ajax y arrastre | Calendario y panel por día, modal reserva y reprogramación; correo disparado desde cliente. Conservar arrastre con misma validación servidor y pista de uso; no duplicar el selector M2 ni calcular prioridad en JS. |
| views/vet/pacientes.php y public/js/medical-module.js | acción rápida data-c3-click=evento20; modalCita/formCita; openAppointmentModal, loadVetsForAppointment, saveAppointment, evento58 | Evento20 hoy redirige a vet_agenda sin preseleccionar mascota. Quedan modal antiguo y funciones de envío directo a registrar_cita_ajax; no aparece otro llamador de openAppointmentModal. Ese formulario carece de tipo, triage y disponibilidad común: integrar o retirar el camino residual, no dejar puerta paralela. También comprobar buscar_mascotas y sus layouts, que comparte el personal. |
| views/admin/citas.php y public/js/admin-citas.js | admin_citas; cargarDatosIniciales y acciones de confirmar/cancelar/reprogramar | Tablero administrativo de supervisión, sin formulario de alta localizado. Conservar filtros/estados/administración ya autorizados. No quitar todo admin_citas por la prohibición de crear. |
| views/vet/consultas.php, modal_consulta.php, atencion.php; views/partials/consulta_*; public/js/consultas.js, consulta-formulario.js, consulta-sintomas.js, consulta-diagnosticos.js, consulta-tratamientos.js y medical-module.js | Atención sin cita, formulario compartido y atención de cita; selector expuesto como ZookiSintomas | Dos entradas clínicas usan componentes M2; muestran HC/historial/alertas/peso desconocido. Mantener ambos diseños y reglas. El selector de consulta captura códigos; extender/reutilizar en agenda con adaptador de episodios y exposiciones, sin romper el contrato de consulta. |
| views/portal/index.php y layout.php, public/js/portal.js; controllers/PortalController.php | index, conCancelacion, verDetalleMascotaAjax, getDetalleCitaClinicaAjax, imprimirHistorial; abrirBooking/cargarCatalogos y data-agendar | Todos los accesos del menú, barra móvil y estados vacíos confluyen en un formulario; cambio de clínica invalida selección, respuestas tardías se descartan. Conserva cancelación y catálogo/mascota propia. Falta reprogramar; no equivale a agendar otra y cancelar después. |
| controllers/CitaController.php, config/EmailService.php | avisarCita, retirarAvisosCita, enviarEmailAjax/enviarCorreoCita, responderYSeguir; obtenerPlantillaBaseHTML, enviarCorreoPersonalizado | Avisos internos tras crear/cambiar; correo en una segunda petición de JS, responde antes del SMTP. No garantiza correo sin navegador y el mensaje anuncia envío sin acreditar entrega; plantilla global Zooki, faltan motivo/dirección en cuerpo y registro durable del fallo inmediato. Proponer envío desde evento confirmado y cola de A.6, preservando no bloquear reserva. |
| models/NotificacionInterna.php y controllers/NotificacionController.php | crearParaUsuario/crearParaRol, expirarDeCita, obtenerParaUsuario, perteneceA, marcarLeida/marcarTodasLeidas | Avisos por destinatario/rol y clínica, con vencimiento. Conservar control de acceso. No reutilizar como cola sin vet ni como historial de aceptación. |
| scripts/send_reminders.php; models/Recordatorio.php; helpers/EnviadorRecordatorios.php, VentanaRecordatorio.php | ejecutar, citasDeManana, puedeEnviar, registrar | Recordatorio de citas de mañana, prevención con ventana/reintentos y registro en notificaciones. Conservar exclusión de inactivos y contactos legítimos; confirmación/declinación desde recordatorio queda fuera de M4. |
| scripts/vigilar_atenciones.php; helpers/VigilanteAtenciones.php, ReglaAtencion.php; models/AtencionesEnCurso.php; scripts/zooki.cron | revisar, debeAvisarAbierta, quedaSinCerrar; listar, sellarAviso, marcarSinCerrar | Cron cada 5 minutos y respaldo al abrir calendario; aviso único y sin_cerrar. No hay procesador de cascada ni vencimientos de reasignación. Mantener RN-409 separado de RN-429; actualizar comentario viejo RN-410 cuando se toque esa tarea. |
| Salidas de marca | views/portal/layout.php, index.php, imprimir_historial.php; config/EmailService.php y EnviadorRecordatorios::contenido | Portal global con varias clínicas e historial global: no imponer una clínica elegida arbitrariamente. Propuesta: marca en tarjeta/detalle de cada clínica, correo de su evento y documento de esa clínica; historial multi-clínica conserva cabecera de plataforma y procedencia por acto. RE-7.4.4 requiere aclararlo antes de C1/F. |

**Inventario de rutas y permisos que se debe mantener explícito:**

- En public/index.php y Security::actionRoles, registrar_cita_ajax hoy es ADMIN/VETERINARIO; cambiar solo su creación a VETERINARIO y exigir ese rol también al entrar al servicio.
- portal_agendar_cita_ajax, portal_get_vets_ajax, portal_get_tipos_cita_ajax, portal_get_horas_ajax y portal_get_sugerencias_ajax son del propietario; no reutilizar reprogramar_cita_ajax sin una entrada de titularidad propia.
- buscar_sintomas_ajax y calcular_triage_ajax conservan VETERINARIO/PROPIETARIO; plataforma y ADMIN reciben 403.
- listar_citas_ajax, get_cita_ajax, listar_veterinarios_ajax, listar_tipos_cita_ajax, reprogramar_cita_ajax y confirmar_cita_ajax conservan sus permisos actuales de personal salvo decisión nueva explícita.
- cancelar_cita_ajax y enviar_email_ajax admiten propietario y personal con comprobación del recurso; get_horas_disponibles_ajax/get_sugerencias_horario_ajax son compartidas hoy y deben delegar al mismo servicio con contexto validado.
- iniciar_cita_ajax, completar_cita_ajax, marcar_no_asistio_ajax, vet_atencion y registrar_consulta_ajax siguen siendo del vet asignado o del alcance clínico definido para consulta; nunca del admin ni de plataforma.
- admin_configuracion, guardar_horarios_clinica_ajax/restaurar_horarios_defecto_ajax, listar_todas_citas_ajax y admin_citas se conservan. Las acciones nuevas de propuesta, revisión, urgencia y conciliación se enumeran en B/C con matriz y CSRF; no se habilitan implícitamente por un rol genérico.

AutorizacionRolTest::testCadaRolConservaSusAccionesHabituales actualmente admite registrar_cita_ajax al administrador y debe cambiar con C4a.
AgendaPeticionesTest::testElPortalTodaviaNoAgenda solo niega la ruta interna bajo contexto propietario; PortalAgendaTest prueba la ruta portal que sí funciona.
No usar el nombre antiguo del primer test como evidencia de que el portal está sin implementar.

### A.3. Contrato propuesto del Grafo II

**Separación de responsabilidades técnicas.**
Proponer helpers/IntervalosAgenda.php (intersección/resta/solape), helpers/MotorAgenda.php (grafo puro y estimaciones), models/DisponibilidadAgenda.php (datos mínimos y excepción global encapsulada) y models/OperacionAgenda.php (coordinación transaccional).
Cita conserva acceso/persistencia por clínica; MotorTriage y BuscadorSintomas no conocen cuotas ni candados.
Los controladores y tareas llaman al mismo coordinador; las vistas solo presentan.
No se crean estas clases en A.

**Intervalos y reloj.**
Todas las operaciones reciben un reloj y trabajan con fechas completas en la zona vigente America/Bogota, usando ZonaHoraria/ReglaAtencion; no depender de fecha UTC del navegador.
Usar intervalos semiabiertos [inicio, fin): inicio incluido y fin excluido.
Dos intervalos se solapan si inicioA < finB y inicioB < finA.
Una ausencia de día completo es [día 00:00, día siguiente 00:00); una de 10:00 a 11:00 permite comenzar exactamente a las 11:00.
Rechazar fin <= inicio y rangos inválidos, sin redondear minutos que alteren la regla.
En C3 no se admiten reservas ordinarias que crucen el fin de una franja o la medianoche; las atenciones reales que la crucen mantienen DATETIME y continuidad.

Disponibilidad = intersección de franjas activas de clínica y veterinario, menos cierres puntuales y ausencias aprobadas, menos ocupación compatible de vet y mascota.
Sin horario de cualquiera de los dos, no se ofrece agenda ordinaria.
Una cita de duración d y margen m necesita [s, s+d+m) contenido completamente en una sola franja restante.
hora_fin sigue significando fin clínico pactado s+d; el extremo de ocupación se calcula con el margen, no se vuelve a almacenar como hora_fin.
Duración/margen salen del tipo activo validado en servidor, jamás del número enviado por JS.
La sugerencia recibe mascota y tipo, además del vet; hoy sugerencias no recibe mascota.

**Aristas y prioridad.**

- Secuencia: orden de ejecución de un veterinario; un caso rojo presencial y la reanudación de su cita interrumpida son dependencias explícitas. Una pausa no equivale a cancelación.
- Conflicto: intersección incompatible por veterinario o mascota, incluidas sus otras clínicas; puede existir conflicto previsto entre capacidad ordinaria y un sobrecupo autorizado, pero nunca dos atenciones reales simultáneas del mismo vet.
- Emparejamiento: cita ↔ veterinario activo de esa clínica, con intervalo completo compatible, disponibilidad vigente y mascota sin conflicto. No incluye afinidad, reputación ni especialidades inferidas (HU-4.16 es v2.1).
- Rojo presencial tiene precedencia máxima; naranja usa orden de llegada fecha_registro/id_cita, salvo motivo del vet auditado; amarillo toma primer espacio y verde conserva elección. No inventar una puntuación dentro del color.
- Una propuesta de reasignación no ocupa ni mueve todavía la cita; al aceptar se revalida. Si se quiere apartar provisionalmente un hueco, sería una regla adicional que requiere aprobación, no un bloqueo SQL abierto durante diez minutos.

**Bloque y sobrecupo naranja, propuesta a confirmar.**
Identificar un bloque por clínica, veterinario, fecha y componente continuo de la intersección recurrente antes de restar ausencias/cierres parciales.
Restar una ausencia no duplica el tope ni crea otro bloque con contador nuevo.
Guardar la identidad del bloque de cada naranja y no recrearla para eludir su tope al editar horarios.
Contar los naranjas del bloque que no estén cancelados/no_asistio; completados siguen consumiendo el cupo de ese bloque.
El tercer naranja con tope 2 genera aviso y decisión del personal, sin degradarlo a verde ni saltar al siguiente día silenciosamente.
Sobrecupo autoriza demanda adicional en esa cola, no simultaneidad real ni cruces del profesional entre clínicas.
La predicción de cada naranja debe incluir su duración y margen y disparar reajuste si desborda franja; no ocultarlo del grafo como hace hoy ocupadas.
La unidad de bloque y liberación de cupo son concreciones de RN-422 que B debe hacer explícitas, pendientes de conformidad.

**Horas pactadas, estimadas y reales.**
fecha/hora y duración/margen guardan el compromiso original vigente; una reprogramación expresa lo cambia con auditoría del anterior.
inicio_estimado/fin_estimado son la predicción vigente del inicio y del fin clínico; el último no incluye margen ni sustituye hora_fin pactada.
Tras iniciar, inicio_estimado se presenta anclado al primer inicio real; una reanudación no lo sustituye.
Si la cita está pausada, la próxima reanudación estimada se expresa aparte como dato derivado, condicional, del caso rojo que ocupa al veterinario.
Cambiar predicciones no reescribe el compromiso ni los sellos reales.
hora_inicio_real se sella una sola vez; hora_fin_real solo al completar la consulta.
Las pausas conservan cada segmento, no reinician la atención ni vuelven a contar el tiempo activo ya realizado.
Una cita futura que no se haya acordado adelantar no se estima antes de su hora pactada: evita avisar adelantos no aceptados.

**Cascada por estado — corrección H-A1 revisada y política M confirmada.**
La regla M desplaza el fin nominal con el inicio real y las pausas y conserva un solo margen después de ese nominal desplazado.
**Decisión del usuario (2026-10-17):** aplicar M, conforme a §6, conservando el resultado 10:00 del ejemplo de pausa. B debe reflejar esta precisión en RN/RE/Modelos antes del código afectado.
La alternativa C y la condición de aprobación que recoge el Anexo A.1 se conservan como evidencia histórica previa a esta elección; C no se implementa.
Las fórmulas y ejemplos siguientes usan exclusivamente M.

**Magnitudes y reconstrucción.**
Usar instantes completos y duraciones en segundos al operar; 30 minutos se convierte una vez en 1800 segundos, sin redondear tolerancias ni segundos.
En las fórmulas se abrevia la suma de una duración a un instante.

- s: inicio pactado (fecha+hora); d: duración pactada copiada del tipo; m: margen copiado al reservar.
- e: inicio estimado de una cita aún sin iniciar, reconstruido desde s, la cola y el reloj; no es su inicio real.
- r: primer inicio real inmutable; f: fin real al completar, también inmutable.
- D: objetivo de tiempo activo del servicio; en el caso explícito de servicio completo es d. Si el veterinario decidió otra duración, solo se usa la duración expresamente registrada con su decisión; si falta, queda pendiente, sin deducirla del retraso, de la tolerancia ni del tiempo de franja restante. D y d son datos distintos; d nunca se sobrescribe.
- Pausas [a_i, b_i): a_i es inicio real y b_i reanudación real; a lo sumo una tiene b_i desconocido. Deben estar ordenadas, sin solapes, dentro de la atención y no superar f al completarla.
- P_c: suma de duraciones de pausas cerradas; P(t): duración de todas las pausas hasta t, incluyendo solo el tramo ya transcurrido de la abierta, si existe.
- A(t): tiempo activo transcurrido = (t − r) − P(t), con t limitado a f si ya completó. Una pausa no cuenta como trabajo. R(t) = máximo(0, D − A(t)) es el tiempo activo nominal restante, no una afirmación clínica de cuánto falta realmente.
- N: fin clínico nominal del servicio según su base vigente; F: fin clínico estimado como límite inferior revisable; L: extremo estimado de ocupación para secuenciar la cola, incluido el margen una sola vez. L no acredita una liberación real mientras la atención o la pausa sigan abiertas.

**Una fórmula de ocupación, con base definida por estado.**
Bajo M, L = máximo(N + m, F).
El exceso de duración que puede propagarse respecto de ese nominal es máximo(0, F − N − m), sin volver a restar m.
El desplazamiento de inicio o por interrupción se refleja primero en N y en L; no se descuenta de nuevo al recorrer las siguientes citas.

| Estado | Base nominal, fin clínico y ocupación propuestos bajo M |
|---|---|
| Sin iniciar | e = máximo(s, L del predecesor, ahora) para una cita que todavía espera inicio. N = e + D; F = N; L = N + m. Una reserva futura conserva s como mínimo; no reducir D para hacerla caber ni usar un resultado anterior de e como un delta que se vuelve a sumar. Si la decisión de duración está pendiente, no emitir una estimación definitiva inventada. |
| Iniciada, sin pausa abierta | La base pasa de e a r. N = r + D + P_c. F = máximo(N, t + R(t)); L = máximo(N + m, F). Sin pausas P_c=0; un inicio real tardío con servicio completo desplaza N a r+d. |
| Pausada | A(a) y R(a) quedan congelados al comenzar la pausa abierta en a. Si u es la reanudación estimada, con u >= ahora, N_u = r + D + P_c + (u − a); F_u = máximo(N_u, u + R(a)); L_u = máximo(N_u + m, F_u). u procede de la disponibilidad prevista para retomar tras la urgencia y es condicional. Sin u sustentada, solo puede mostrarse la cota «si retomara ahora», u=ahora, nunca anunciar liberación, completar o despachar una siguiente cita. |
| Reanudada | Cerrar la pausa con su b real; P_c suma cada pausa cerrada una sola vez. Volver a N = r + D + P_c y F = máximo(N, t + R(t)); L = máximo(N + m, F). No hacer r=b ni N=t+R(t) en cada recálculo. El sobrepaso ya consumido antes de pausar sigue consumido al retomar. |
| Completada | Todas las pausas deben estar cerradas; N = r + D + P_c permanece reconstruible, F = f y L = máximo(N + m, f). El reloj posterior no desplaza L. No sustituir D por la duración observada: hacerlo ocultaría el exceso y añadiría otro margen después del fin real. |

Si A(t) ya alcanzó D y la atención sigue abierta, R(t)=0 solo significa que agotó el tiempo nominal: F sigue siendo un mínimo revisable y el veterinario aún está ocupado.
Por ejemplo, tras reanudar no usar t + R(t) + m como nuevo extremo en cada tick; eso regeneraría margen aunque ya se hubiera gastado.
Tampoco reiniciar un margen por cada pausa: solo se desplaza N por la duración de cada interrupción, conservando todo el tiempo activo anterior, incluido el exceso.
Si D se modifica expresamente por una decisión clínica, registrar nueva versión y decisión y recalcular desde esa evidencia; el algoritmo no cambia D automáticamente para absorber un sobrepaso.

Para una siguiente cita ordinaria j aún sin iniciar, reconstruir e_j desde máximo(s_j, L del predecesor, ahora) y validar su intervalo completo hasta L_j contra franjas y compromisos.
La cita pausada sigue teniendo la dependencia de reanudarse antes de las siguientes (RN-427); su cota condicional no autoriza saltarla.
El hueco entre citas puede absorber desplazamiento, sin sumar el mismo delta a toda la lista.
Repetir el cálculo con los mismos hechos y reloj devuelve lo mismo; avanzar el reloj puede cambiar F y L de una atención aún abierta, pero no su N ni sus sellos por sí solo.
Calcular toda la cola desde s, r, D, pausas y f vigentes; las estimaciones previas son salidas, no una nueva fuente de tiempo consumido.

| Ejemplo verificable | Resultado esperado bajo la propuesta M |
|---|---|
| A pactada e iniciada 09:00, D=d=30, m=10; B pactada 09:40 | N=09:30. Fin A 09:37: L=09:40 y B sigue 09:40; fin A exactamente 09:40: igual; fin A 09:41: L=09:41 y B 09:41. No 09:31 ni 09:51. |
| A sigue abierta a las 09:45 con los datos anteriores | A=45, R=0; N=09:30, F=09:45, L=09:45 como mínimo revisable. Repetir ese reloj devuelve 09:45; a las 09:46 da 09:46, no 09:51. No es permiso para atender B mientras A continúe abierta. |
| A termina 09:47; B 09:40, D=d=20/m=5; C 10:20; cálculo antes de 09:47 | A deja L=09:47. B sin iniciar: e=09:47, N=10:07, L=10:12; C conserva 10:20. El hueco absorbe el retraso. Si B inicia realmente a otra hora, la base pasa a ese r. |
| Franja hasta 12:00; cita 11:20, d=30/m=10 | Cabe exactamente; 11:21 no cabe. Si la cascada lleva la cita a 11:25, no truncar su duración: queda para reasignación/reprogramación. |
| Pactada 09:00; inicio real 09:20; vet decide servicio completo D=d=30, m=10, sin pausas | N=09:20+30=09:50; L nominal=10:00. A las 09:45: A=25/R=5, F=09:50 y L=10:00. Si completa 09:50, sigue L=10:00. Hora pactada 09:00 e inicio real 09:20 permanecen distintos. |
| Inicio 09:00, D=d=30/m=10; pausa 09:10–09:30 | Antes de pausa A=10/R=20. Durante ella, con u condicional 09:30: N_u=09:00+30+20=09:50, F_u=09:30+20=09:50, L_u=10:00. Al reanudar realmente 09:30, P_c=20/N=09:50; si termina 09:50, L=10:00. La base ya no es 09:30, sino el fin nominal desplazado 09:50; no se reutiliza el antiguo N+m=09:40. |
| Inicio 09:00, D=30/m=10; pausas 09:10–09:20 y 09:25–09:40 | Trabajó 10+5=15 min al comenzar la segunda; R=15. Pausas totales P_c=10+15=25; al retomar 09:40: N=09:00+30+25=09:55, F=09:55, L=10:05. Si completa 09:55, conserva L=10:05: un margen de 10, no dos de 10. |
| Pausa única 09:10–09:30 del ejemplo anterior; sigue abierta al retomar | A las 09:59: A=39/R=0, N=09:50, F=09:59 y L=10:00. A las 10:00: A=40 y L=10:00; a las 10:01: A=41 y L=10:01. Si completa 10:05, A=45 y L=10:05, sin agregar 10 para dar 10:15. |
| Inicio 09:00, D=30/m=10; ya trabajó 40 min al pausar 09:40–10:00 | A=40/R=0 antes de la pausa; P_c=20 y N=09:50 al retomar. L mínimo=10:00, sin un margen nuevo hasta 10:10; la atención sigue ocupada hasta su cierre. Si completa 10:02, L=10:02. El exceso anterior a la pausa no se borra. |
| Recalcular dos veces al reloj 09:40, varias pausas del ejemplo 09:10–09:20/09:25–09:40 | En ambos: P_c=25, A=15, R=15, N=09:55 y L=10:05. Al reloj 09:45: A=20/R=10, N=09:55 y L sigue 10:05. Tras completar a las 09:55, recalcular a las 10:30 conserva N=09:55, F=09:55 y L=10:05. |
| Llegada 10:10 para 10:00, tolerancia=10; inicio efectivo 10:10, D=d=30/m=10 solo si vet eligió servicio completo | Está dentro exactamente; N=10:40 y L=10:50 bajo M. Si no cabe, las opciones del vet permanecen; no transformar automáticamente D en 20 por la llegada. Si eligió otra duración, usar solo su valor registrado; si reprograma, no inventar inicio real. A 10:10:01 está fuera: no_asistio/siguiente hueco/reprogramar. |

**Cobertura frente a reasignación en vivo.**
Ausencia aprobada tapa el intervalo y busca cobertura sin veto, dentro de la clínica; no crea una propuesta con vencimiento para que el destino «acepte» una obligación ya asignable.
La reasignación por cascada sí guarda propuesta pendiente: mantiene origen hasta aceptación del destino en contexto clínico correcto, revalida y cambia atómicamente; rechazo o vencimiento prueba siguiente candidato.
Proponer vigencia [fecha, fecha_limite): aceptar exactamente en fecha_limite cuenta como vencida; su plazo se lee de clinicas.plazo_reasignacion_min (default existente 10).
Ese extremo temporal queda para ratificar en la revisión técnica, sin inventar otro plazo.

**Avisos.**
Guardar última estimación comunicada y último instante de aviso RN-429.
Comparar el desplazamiento vigente contra la última hora comunicada, no contra el último recálculo ni la suma de pequeños deltas repetidos.
Con umbral 15: a +15 no avisa; a +16 puede avisar si pasaron al menos 30 minutos; exactamente 30 habilita.
Tomando comunicación inicial 09:00, diez recálculos +3 cada seis minutos producen primer aviso en el sexto (+18, minuto 36), y ninguno más antes del 66; cumple «como máximo dos» en una hora sin forzar dos.
Reasignación/reprogramación siempre generan su evento y pueden actualizar la base comunicada; no se suprimen por la ventana de estimaciones.
Un fallo SMTP no revierte una reserva ni se considera una comunicación entregada; la cola conserva fallo y permite resolver reintentos sin duplicar el evento.
No extender a esta cola los tres intentos de recordatorios como regla nueva sin acordarlo.

### A.4. Contrato transaccional propuesto

**Recurso estable aunque no haya citas.**
Usar la fila existente de clinicas para la configuración/cuota; las filas globales de usuarios de todos los veterinarios afectados para exclusión de agenda; las de mascotas para exclusión del paciente.
Una fila de cita vacía no es el recurso de serialización.
No hace falta crear una tabla de candados por cada hueco.
La serialización inicial es conservadora (por profesional, incluso entre días); optimizar por día se evaluaría después con pruebas, sin cambiar la garantía.

**Orden único.**
Comprobar acceso sin revelar datos y determinar los IDs afectados; abrir una transacción corta.
Bloquear en este orden: clínicas involucradas por id ascendente → usuarios afectados (vets y, si se cambia titularidad, titular) por id ascendente → mascotas por id ascendente → bloques fechados por id → citas por id → solicitudes/evaluaciones/reasignaciones/ingresos por id → filas de avisos.
Ninguna operación bloquea «primero la cita» y luego intenta conseguir un profesional.
Si durante el cálculo aparece otro recurso no bloqueado, deshacer y recomenzar con el conjunto completo ordenado, sin ampliar candados en orden inverso.
Los cambios de plan siguen bloqueando la clínica primero; registrar/reprogramar desde portal dejan de abrir envolturas con un orden distinto.
Transaccion y sus savepoints se reutilizan, pero un savepoint no libera candados ni hace vigente una lectura anterior.

Después de los candados, releer permisos, contexto de recurso, actividad, propietario/vínculos, tipo, horario, ocupación, tope y revisión con lecturas actuales de MySQL.
No usar una instantánea consistente abierta antes de esperar al candado bajo REPEATABLE READ.
La implementación puede usar lecturas de bloqueo actuales y proyección mínima; no mezclar con consultas de snapshot anteriores.
Mantener candados hasta commit/rollback; no esperar SMTP ni confirmaciones humanas.
Evitar consultas con JOIN de contactos en el cálculo global.
Un deadlock/timeout produce rollback completo y reintento técnico acotado del comando idempotente o respuesta de conflicto, nunca resultado parcial.

**Excepciones globales encapsuladas (RNF-11).**
DisponibilidadAgenda solo consulta internamente intervalos/estado de ocupación del mismo id_veterinario o id_mascota entre clínicas; para horario recurrente del vet, día y extremos.
Todas las modificaciones y listados visibles siguen filtrados por clínica activa.
El cliente recibe «No está disponible en ese horario» o «Choca con otro compromiso», nunca nombre de otra clínica, paciente, motivo, dueño o calendario ajeno.
No ofrecer un endpoint público de ocupación global.
Revalidar que el solicitante puede actuar sobre el vet/mascota antes de consultar esos intervalos.
Una ausencia de otra clínica no se revela como tal; su alcance local/global requiere precisar la disponibilidad real en B si se pretende bloquear también compromisos fuera de la clínica que la registra.

| Operación | Unidad atómica y revalidación |
|---|---|
| Reserva ordinaria | Calcular triage antes de cuota; revisión cuando proceda; bloquear clínica/vet/mascota/bloque; releer datos y buscar intervalo completo; consumir cuotas de mascota y cita solo una vez; vincular mascota si procede, guardar cita/episodios/instantánea, auditoría y evento de notificación juntos. Si falla, no deja vínculo nuevo ni cuota consumida. Rojo no atraviesa la comprobación de cuota de mascotas ni de citas. |
| Hueco ocupado entre sugerencia y POST | Bajo los mismos candados, amarillo busca primer intervalo siguiente y naranja reevalúa bloque/tope; verde devuelve alternativas para elección, sin confirmar otra hora unilateralmente. El resultado nuevo queda revisable por el actor. |
| Reprogramación interna/portal | Mantener la reserva original hasta confirmar la nueva; bloquear origen y destino, excluir solo la cita propia; revalidar estado abierto, duración/margen, bloque y mascota, más cuota mensual destino si cambia mes sin contar dos veces la misma cita. Actualizar una sola fila y sus marcas/eventos/auditoría. Si no cabe, rollback deja la cita original intacta. |
| Cupo naranja | Bajo clínica+vet+bloque contar naranjas vigentes, comparar clinicas.tope_sobrecupos y asignar orden estable. Dos conexiones compitiendo por el último cupo: solo una lo consume. Reordenación exige vet autorizado y motivo, conserva fecha de registro e historia del orden. |
| Horario, cierre o aprobación de propuesta | Bloquear clínica y todos los vets/mascotas/citas afectados con orden global; validar intersecciones y choques del vet entre clínicas; guardar configuración y marcas de reajuste en la misma operación. Invalidar ofertas anteriores. No mover horas pactadas para esconder el conflicto. Una reserva concurrente se hace íntegramente antes o después del cambio. Restaurar horarios también usa este contrato. |
| Ausencia/cobertura | Solicitud pendiente no cambia disponibilidad en la propuesta de A.8; al aprobar/registrar, bloquear origen y candidatos antes de confirmar ausencia. Asignar cobertura sin veto por cada cita compatible, o dejar marca pendiente de reprogramar con evento para propietario. Una cobertura parcial no hace perder las otras citas; cada resultado se registra y puede retomarse idempotentemente. Ausencia y marcas de todas las afectadas se guardan juntas aunque la búsqueda se procese después. |
| Aceptación o vencimiento de reasignación | Bloquear clínica, ambos profesionales, mascota, bloque, cita y propuesta; comprobar pendiente, destinatario, reloj < fecha_limite, versión y disponibilidad actual. Aceptar mueve y cierra propuesta conjuntamente; si perdió hueco, no mueve la cita y continúa siguiente candidato. El cron usa el mismo cambio condicional; aceptación y vencimiento concurrentes tienen un solo ganador. Candidatos intentados se conservan para no recorrer infinitamente los mismos. |
| Inicio, pausa, reanudación y finalización | Mismo protocolo de capacidad; llegada presencial verificada antes de pausar; compare-and-set del estado esperado, una sola atención activa del vet. Guardar pausa, urgencia y marca de cascada juntas. Consulta solo completa cita reanudada/en_curso o sin_cerrar según contrato; no completa una pausada. La numeración HC actual toma GET_LOCK antes de su transacción: C7 debe integrar ese orden (candado HC antes de cualquier candado de filas en todos sus llamadores, o sustituirlo uniformemente por serialización de clínica), nunca adquirirlo tarde desde una transacción que ya tenga filas bloqueadas. |
| Cascada/conciliación de estimaciones | Bloquear conjunto de agenda afectado y versión, recalcular desde compromisos/segmentos actuales, escribir estimaciones/marcas/eventos juntos y limpiar trabajo solo si sigue la misma versión. Otro cambio durante el proceso deja trabajo pendiente; repetir no suma el retraso dos veces ni duplica aviso. |
| Conciliación de identidad | Ver A.5: bloquear clínica, usuarios verificados, fichas provisional/final ordenadas, actos/citas y el ingreso; releer titularidad, referencias y estado. Completar o consolidar todo con auditoría, o rollback de todo. Dos operadores conciliando el mismo ingreso no pueden duplicar el traslado. |

**Idempotencia y recuperación.**
Los POST de reserva/llegada/derivación llevan una clave opaca de operación ligada a clínica+actor+tipo de comando; mismo comando repetido devuelve el mismo resultado y otra carga con la misma clave se rechaza.
Se propone un registro mínimo operaciones_agenda con clave única, huella de entrada, actor, clínica, tipo y referencia de resultado, escrito dentro de la transacción.
No reutilizar CSRF como clave ni conservar datos clínicos/contactos en esa huella legible.
El índice de citas sigue como defensa adicional; no abrir huecos en la restricción para solucionar una falla del algoritmo.
Las tareas reclaman trabajo de forma condicional y recuperable; un proceso muerto no deja una cita eternamente «en proceso».
La reclamación técnica y recuperación no introducen un plazo clínico nuevo; el mecanismo exacto se verifica en B/C5.

### A.5. Integración con M2, rojo y conciliación

Reutilizar exactamente MotorTriage::calcular, BuscadorSintomas::buscar, ValidadorTriage::episodios, ValidadorContextoClinico y el contexto de PacienteDiagnostico.
El buscador de ZookiSintomas se comparte con un adaptador para episodios: la consulta actual envía códigos y no conserva tiempos.
Agendar necesita además tiempo por síntoma/exposición (conocido y minutos, no_se o no_aplica solo preventivo), sustancias/fármacos confirmados y contexto permitido.
No inferir sustancias desde nota ni duplicar en JS las reglas P1–P8.
Las exposiciones no se fuerzan a cita_sintomas como si fueran síntomas: conservarlas en el contexto estructurado y validar su tipo con ValidadorTriage.
El guardado vuelve a calcular en servidor con datos vigentes, código/ID de nodo, contexto, fecha de evaluación y hash de conocimiento; un color enviado desde el cliente se ignora.
Las marcas de cita van a cita_sintomas.detalles con sus tiempos; sintomas_texto es solo nota del personal; inicio_sintomas legado no sustituye varios eventos.
No copiar medicación protegida al JSON de la cita, al evento ni al propietario.

**Revisión humana previa.**
Evaluación con revision_inmediata (T-20) no devuelve una oferta reservable.
Devuelve un estado pendiente y aviso al personal autorizado, sin prometer tiempo de respuesta ni asignar espacio.
Propuesta conservadora: todo caso con revision requiere liberación veterinaria antes de reservar; T-22 y T-16 conservan amarillo provisional y revisión, sin escalarlos automáticamente.
La extensión de esa puerta a toda revisión, más allá del mínimo obligatorio T-20, se somete al usuario en A.8.
Un vet puede aprobar el nivel calculado o ajustarlo con motivo; guardar autor, fecha, calculado/ajustado y datos revisados.
La resolución se liga a la huella de marcas/tiempos/contexto y versión; si cambian antes de reservar, se invalida y se revisa nuevamente.
No vale una autorización enviada por el propietario ni una bandera «revisado» de JS.
La revalidación de la misma evaluación no es HU-4.17: no habilita edición de síntomas de citas pendientes por el propietario en esta versión.

**Aislamiento.**
T-11 conserva contagio incluso si otra marca eleva a rojo.
Al confirmar evaluación/derivación, generar aviso operativo para la clínica elegida y su personal, independiente del correo de confirmación y del color final.
No esperar a que haya hueco o cupo para preparar aislamiento.
No incluir ese aviso ni contactos en otra clínica; duplicar POST no duplica ingreso ni aislamiento.

**Máquina de estados de rojo propuesta, sin acreditar presencia remota.**

1. Evaluación remota roja: guardar derivación con propietario de sesión y mascota propia; notificar y mostrar urgencia. No crear reserva ordinaria, asignar capacidad, pausar una cita, sellar llegada ni iniciar consulta. Repetir el cálculo sin confirmar un comando no crea múltiples derivaciones.
2. Clínica cerrada: informar cierre con horario y cierres puntuales, teléfono de urgencias si existe y recomendación de servicio 24 horas. Conservar el aviso; no prometer atención ni bloquear un ingreso físico efectivamente atendido por el personal.
3. Llegada física: solo personal clínico con contexto legítimo acredita fecha_hora_llegada y enlaza la derivación cuando existe. No pedir verificación adicional al propietario ya autenticado por el mero cálculo; comprobar nuevamente que la mascota sigue siendo suya en cada comando. Una llegada sin derivación crea el caso presencial, sin duplicar el existente.
4. Identidad conocida: reutilizar ficha/cuenta y vínculo legítimo; si no se acredita la relación sin retrasar atención, crear ficha provisional de clínica, propietario NULL, ficha_por_completar=1 y carnet inactivo, más ingresos_emergencia. Acompañante es contacto eventual, nunca titular por defecto.
5. Capacidad presencial: libre compatible → asignar; si todos ocupados, solo tipo pausable → pausar y asignar; si ninguno, cola roja presencial sin veterinario hasta que exista capacidad. Orden propuesto entre rojos presentes: llegada física e ID, con decisión clínica motivada; confirmar en A.8. No «asignar» anticipadamente al primero que posiblemente termine.
6. Al asignar, crear/enlazar la cita roja con el vet real, origen y llegada, antes de atención clínica; estado en_curso y sellos solo al iniciar realmente. Excepción roja a límites también en el vínculo de mascota/ficha provisional; auditar la excepción.
7. Cierre de urgencia con consulta → reanudar la cita interrumpida antes de las siguientes; no completar dos citas con una consulta ni reiniciar hora_inicio_real de la pausada. Cada paso marca el trabajo de cascada para procesarse sin navegador.

La cola de A.6 conserva la espera sin volver nulo citas.id_veterinario.
Una mascota aún no identificable se liga a la ficha provisional, no a un ID ficticio de usuario.
No reutilizar ingresos_emergencia para todos los rojos: su definición normativa es solo el ingreso provisional; el enlace común de derivación/llegada/espera debe ser independiente.

**Cuatro ramas de identidad y conciliación.**

- Portal autenticado con mascota seleccionada: mismos IDs, sin provisional ni cuenta nueva; al llegar enlaza su derivación.
- Presencial con cuenta/ficha y titularidad verificadas: mismos IDs; no crear ingreso provisional.
- Cuenta existente pero relación no verificada: ficha provisional, sin asignar esa cuenta como propietaria hasta verificación.
- Sin cuenta: ficha provisional y contacto opcional; una eventual cuenta se crea por el proceso vigente de consentimiento/activación, nunca automáticamente por atender.

Completar ficha nueva conserva id_mascota y registra id_mascota_final igual a la provisional; consolidar sobre existente conserva ambos IDs en ingresos_emergencia y desactiva la provisional.
Enumerar y trasladar referencias de citas, consultas, vacunas, desparasitaciones y alertas; revisar también mascota_clinica, mascota_colores, carnet_escaneos y auditoria_mascotas.
Los tratamientos, archivos y consulta_sintomas siguen su id_consulta; cita_sintomas y reasignaciones siguen id_cita y no deben perderse.
Conservar clínica, autor, fechas, números de historia y documentos clínicos; si ambas fichas ya tienen HC en la misma clínica, no sobreescribir una con otra: conservar su alias/procedencia en la evidencia de consolidación.
En mascota_clinica, unir sin degradar vínculos ni permisos; titularidad final verificada y autorización de historia permanecen explícitas.
No reasignar indiscriminadamente la auditoría antigua ni reactivar el token público de la provisional.
Un ingreso provisional de otra clínica no puede consolidarse desde esta: devolver 403; una inconsistencia interclínica se deriva a resolución autorizada, sin apropiarse de sus actos.
Si existe cualquier conflicto de vínculo/HC/referencias no cubierto por el contrato revisado, rollback y tarea pendiente; nunca borrar el acto conflictivo para completar.
La prueba debe fallar tras mover consultas y antes de finalizar para demostrar que también vuelven atrás citas, vínculos, ingreso y auditoría.

### A.6. Persistencia faltante y transición propuesta

**Contraste real, no implementación inferida.**
01_schema.sql, MER y drawDB ya describen tipos con duración/margen/pausable; horario vet; propuestas; ausencias por DATETIME; prioridad calculada/ajustada y motivo; cita_sintomas y detalles; contexto_triage; sobrecupo/orden; llegada y horas reales; estado pausada; reasignación con plazo e ingresos provisionales con acompañante opcional.
La migración 07 aporta detalles/contexto del conocimiento ya publicado.
El MER es conceptual: omite columnas físicas como hora_fin y algunos índices; drawDB representa ocupa_horario como columna convencional comentada, mientras el ejecutable la calcula.
Esa diferencia de representación debe seguir documentada, no convertir la columna calculada en dato editable.
No se detecta una solución operativa ya presente para los estados faltantes siguientes en ninguno de los tres contratos.

| Necesidad demostrada | Propuesta de campos/relaciones e índices, pendiente de B |
|---|---|
| Cierre puntual de clínica | Nueva cierres_clinica: id_cierre, id_clinica FK, inicio/fin DATETIME, motivo, activo, autor y fechas. Índice (id_clinica, activo, inicio, fin); validar fin > inicio. Un cierre local no modifica recurrencia. |
| Solicitud de ausencia y su resolución | Ampliar ausencias_veterinario con estado (solicitada/aprobada/rechazada/anulada), id_solicitante/id_revisor FK usuarios, fechas de solicitud/revisión y motivo_resolucion. Índice (id_clinica, estado, fecha_hora_inicio); índice global del vet y rango para cálculo interno. Reutiliza rango/id_cobertura existentes; no duplicar tabla de solicitudes si esta extensión cubre el flujo. Solo aprobadas restan disponibilidad. |
| Revisión humana antes de tener cita | Nueva evaluaciones_agenda: id_evaluacion, clínica/mascota/actor FK, fecha, contexto estructurado/episodios y versión/huella del cálculo, nivel_calculado, revision, revision_inmediata, contagio, estado_revision, id_revisor, fecha_revision, nivel_aprobado/motivo, cita resultante nullable. Para la propuesta de espera no urgente de A.8, origen remoto/presencial, fecha_llegada y estado_solicitud (en_evaluacion/en_espera/sin_hueco/convertida/retirada) separado de la resolución clínica; no se inicia consulta mientras falte cita. Índices por clínica+estado+fecha y por mascota; FK cita, con unicidad para impedir consumir dos veces la misma evaluación. Sin fecha límite clínica inventada: vigencia por datos/versiones. No guardar secretos ni medicación protegida. |
| Bloque y cupo naranja | Nueva bloques_agenda con id_bloque, id_clinica/id_veterinario FK, fecha e inicio/fin originales y versión/estado de disponibilidad. Identidad única por clínica/vet/fecha/origen de franja; citas.id_bloque FK nullable para históricos, índice (id_bloque, es_sobrecupo, estado, fecha_registro, id_cita). Mantener es_sobrecupo exclusivamente como contador naranja, no como bandera de toda llegada. Cambiar horario no borra bloques usados ni reinicia cupos. |
| Origen, estimaciones y reajuste pendiente | Ampliar citas: origen (reserva/llegada_sin_reserva/urgencia; legado al migrar), inicio_estimado/fin_estimado DATETIME, estado_reajuste (ninguno/pendiente_calculo/pendiente_revision/pendiente_reasignacion/pendiente_reprogramacion), motivo_reajuste, version_agenda y fecha_ultimo_calculo. Corrección H-A1: fin_estimado representa F clínico, sin margen; N y L se derivan por estado según A.3. Proponer duracion_activa_objetivo_segundos para D cuando corresponda una duración decidida, con procedencia/autor/motivo en la auditoría; el servicio completo usa d convertido a segundos y una duración diferente requiere su valor explícito. Si falta el dato necesario, NULL/pending de duración, sin inventarlo para un histórico o una decisión incompleta. Identificar también la versión/política temporal aplicada para reconstruir cómo se calculó L cuando se apruebe una; no fijar un default de producto en A.1. Índice (id_clinica, estado_reajuste, fecha); ocupación por vet/mascota+estimación con índices según consultas de C3. Las marcas no sustituyen estado clínico ni D sustituye duracion_minutos pactada. |
| Derivación y cola roja sin vet | Nueva urgencias_agenda: id_urgencia, id_clinica/id_mascota FK, id_evaluacion nullable, id_ingreso nullable, id_cita nullable único, origen, estado (derivada/presente_en_espera/asignada/en_atencion/finalizada/derivada_a_otro_servicio), fecha_derivacion/llegada/asignacion/fin, orden/motivo de decisión. Índice (id_clinica, estado, fecha_llegada, id_urgencia); evaluación/operación únicas evitan duplicados. No usar esa cola para pacientes remotos ausentes. Su decisión clínica y transición deben auditarse. |
| Reasignación recuperable | Ampliar reasignaciones con id_clinica (rellenada desde cita y validada), inicio/fin propuestos, version_cita, motivo y ciclo de búsqueda. Índices clínica+estado+fecha_limite y cita+ciclo; unicidad condicional de una pendiente por cita mediante columna calculada compatible MySQL/MariaDB. Conservar intentos rechazados/vencidos y candidato de cada uno; no borrar historia para volver a proponer. |
| Pausas/reanudaciones múltiples | Nueva pausas_cita: id_pausa, id_cita FK, id_urgencia FK, inicio DATETIME real y fin DATETIME de reanudación real, NULL mientras siga abierta, autor y motivo. Inicio real de atención, D y estos intervalos completos permiten reconstruir A/R/P_c/N/L de A.3, sin una nueva fila por recálculo. La duración activa restante al pausar, si se conserva como instantánea, se expresa en segundos y debe coincidir con máximo(0, D−A(a)); no es una segunda fuente mutable ni se resta otra vez de D al retomar. Índice cita+inicio; unicidad condicional de pausa abierta por cita; validar orden/no solape y pausas cerradas antes de completar. No reiniciar hora_inicio_real, copiar reanudación estimada a fin real de pausa ni crear un margen por interrupción. Una reanudación estimada u necesita referencia al caso rojo y corte/versión de su cálculo, como predicción condicional; no se persiste como hecho clínico. El estado/tipo de la cita sigue siendo la autorización de pausa. |
| Control de comunicación durable | Reutilizar notificaciones para historial de envío y notificaciones_internas para destinatarios; nueva eventos_agenda para trabajo pendiente con clínica/cita o urgencia/evaluación, clave de evento única, tipo, destinatario autorizado, versión/estimación a comunicar, estado, reclamación e intentos/error/fechas. Índice por estado+fecha, clínica+recurso; sin copiar contacto provisional a payload ni a logs. En citas agregar ultima_hora_comunicada y ultimo_aviso_estimacion para RN-429, actualizados con resultado coherente del evento. No confundir aviso_atencion_abierta RN-409 con esta ventana. |
| Doble envío de comandos | Nueva operaciones_agenda mínima de A.4, UNIQUE(clínica, actor, tipo, clave_operacion). Huella opaca y referencia al resultado; insertar junto al negocio, sin usar auditoría como tabla de exclusión. No es una nueva bitácora de correos M8. |
| Conciliación/contacto provisional | Reutilizar ingresos_emergencia, sin pedir documento_acompanante; agregar fecha_conciliacion, id_conciliador, evidencia de mapa provisional→final y HC previas, contacto_anonimizado_en y causa de conservación excepcional si se aprueba. Índice clínica+estado+fecha_ingreso ya previsto. Nombres/teléfonos usan validadores existentes, límite servidor/HTML y acceso protegido; nunca se enlazan por similitud a una cuenta. |

Cada nueva tabla de negocio lleva clínica, FK sin borrado clínico en cascada y validación de pertenencia en modelo.
Para las tablas ligadas a cita/evaluación, comprobar que ambas referencias son de la misma clínica; una FK simple no acredita esa coherencia.
Las restricciones de una sola pausa/propuesta activa necesitan prueba MySQL real, no asumir que SQLite reproduce columnas calculadas.
No agregar tablas para aristas del grafo, nuevas edades, afinidad ni catálogo de reglas clínicas.
La lista es una propuesta mínima por necesidad; la revisión puede reducirla fusionando estados con la misma semántica, sin perder recuperación ni trazabilidad.

**Transición v2, sin inferir historia.**

1. B prepara la siguiente migración libre, propuesta 08_agenda_contratos.sql, solo después de revisar contratos y decisiones. En A no existe ese SQL. No editar 03–07.
2. Ampliaciones aditivas, campos nullable donde el dato histórico sea desconocido y creación repetible de tablas/índices. Sin cambios destructivos ni activación de rutas a medias.
3. Duración histórica: conservar la ya guardada; si falta y hora_fin es válida, inferir solo su diferencia positiva sin cruce ambiguo; si no se demuestra, marcar pendiente de revisión. No copiar la duración actual de un tipo como si se conociera la antigua. El fallback técnico actual 30 no es evidencia de duración histórica. Margen 0 existente se conserva, no se sustituye por 10 automáticamente.
4. No inventar prioridad_calculada, síntomas, tiempos, llegada, pausas o presencia para citas antiguas; origen=legado hasta clasificación demostrable. Una consulta histórica id_cita NULL sigue con su etiqueta «Sin cita». El nuevo origen distinguirá llegadas sin reserva aunque sí tengan cita.
5. Proponer inicializar estimaciones de futuras ordinarias desde fecha/hora/duración solo cuando sean válidas, con fuente migrada; conflictos quedan marcados. Completadas y horas reales no se recolocan.
6. No crear jornadas de vet copiando las de clínica automáticamente; hasta configuración expresa, no hay disponibilidad nueva. Publicar diagnóstico de citas futuras afectadas, sin borrarlas ni alterar el compromiso.
7. Ausencias preexistentes representan bloqueos efectivos: rellenar estado=aprobada sin inventar aprobador; conservar ausencia y cobertura originales. Nuevos campos de autor se permiten desconocidos en legado.
8. Sin bloques históricos demostrables para un sobrecupo, dejar pendiente de revisión; no asignarlo a un bloque nuevo que le regale cupo. No poblar revisiones humanas retrospectivas.
9. Sincronizar 01_schema.sql, MER, drawDB y, solo si procede, inserciones faltantes de 02_semilla.sql con la migración equivalente para bases existentes. Probar instalación nueva y actualización de alpha.4 dos veces; no sobrescribir personalizaciones.
10. Despliegue aplica migración por scripts/migrar.php, sin pedir SQL manual en producción; compatibilidad del código anterior se comprueba antes de prometer rollback. Esta A no migra ni cambia servicios.

### A.7. Cobertura existente y trazabilidad individual

**Alias de cobertura existente (archivos reales):**

- CT = tests/Integration/CitaTest.php: registro, instantáneas, aislamiento, mascota/vet activos, solape secuencial, simulación del índice único, reprogramación y catálogos.
- AP = tests/Integration/AgendaPeticionesTest.php: peticiones por contexto, vet propio, fechas pasadas, horario inicial, calendario, reprogramación admin/arrastre e inicio asignado.
- CE = tests/Integration/CitaEstadoTest.php y tests/Unit/ReglaAtencionTest.php: transiciones, inicio real único, ventana de 15 minutos, aviso de 10 minutos, sin_cerrar y acción derogada.
- PA = tests/Integration/PortalAgendaTest.php: clínica vinculada, mascota propia, alta de vínculo con rollback, catálogos, límites y mensaje neutral.
- CC = tests/Integration/ConfiguracionClinicaTest.php y tests/Unit/HorarioAtencionTest.php: semana/bloques, aislamiento, guardado válido, restauración, cierres recurrentes y extremos de apertura.
- CH = tests/Integration/ConsultaHistorialTest.php, ConsultaSintomasTest.php y ConsultaTratamientoTest.php: consulta normal/sin cita, HC única, consulta asignada, atómico con tratamientos/archivos y rollback.
- TR = tests/Integration/TriageTest.php, tests/Unit/MotorTriageTest.php y BuscadorSintomasTest.php, tests/Fixtures/triage.php: validación/roles, cálculo y 26 casos T.
- LP = tests/Integration/LimitePlanTest.php: cupos de mascota/cita, mora, rojo evaluado, mensaje neutral y rollback de vínculo.
- NA = tests/Integration/NotificacionAccesoTest.php, pruebas existentes de recordatorios y correo: destinatario/rol/clínica, lectura/vencimiento e intentos de recordatorios. No prueban la futura entrega durable de eventos de agenda.
- MY = tests/Integration/BaseV2MysqlTest.php: testC5AgendaEnElEsquemaReal, testC6PortalEnElEsquemaReal, testConfiguracionDeDosClinicasEnElEsquemaReal, testC8CronRealConCorreoSimuladoYDosClinicas y testLaDobleReservaSeRechazaSalvoSiLaCitaEstaLibreOEsSobrecupo. No hay aquí prueba concurrente de intervalos de distinto comienzo en dos conexiones.
- JS = tests/Frontend/M5C2Portal.test.cjs, M5C2bPortal.test.cjs, M5C3Personal.test.cjs, M5C4aPortal.test.cjs, M2C5aConsulta.test.cjs, M2C5bTratamientos.test.cjs, M2C7Pulido.test.cjs, M2C8Rediseno.test.cjs, M2C81Celular.test.cjs y M2C82Historial.test.cjs; preservar formulario, síntomas, contactos, alertas, historial y diseño. No hay suite específica actual del motor Grafo II.
- Acceso = tests/Unit/AutorizacionRolTest.php y pruebas de contexto/aislamiento vigentes; testCadaRolConservaSusAccionesHabituales acepta hoy creación del admin y deberá actualizarse con la decisión confirmada.

La evidencia existente protege lo indicado, no cierra las brechas nuevas.
Una prueba SQLite o la simulación de CT no demuestra serialización InnoDB.
La suite existente en verde no significa que los RE pendientes de M4 estén implementados.

**Casos propuestos, todavía sin escribir ni ejecutar:**

| Caso | Preparación y resultado comprobable |
|---|---|
| P01 Acceso/contexto | Ana Norte admin no crea por POST ni entrada residual de Pacientes; conserva supervisión, reprogramación y configuración. Beto Norte vet y Fabio propietario crean solo dentro de su alcance. Diego Sur y Gina plataforma reciben 403 auditado ante IDs de Norte; cambiar contexto de una cuenta multirol no conserva facultades del anterior. CSRF ausente/inválido no escribe. |
| P02 Intervalos completos | Clínica 08–12/14–18, vet 09–11: duración30+margen10; 10:20 cabe, 10:21 no; ausencia [10,11) bloquea cruce y permite 11:00 cuando exista franja siguiente; cierre único no cierra otra semana; sin horario no hay espacios. Sugerir y guardar producen el mismo resultado por mascota/tipo y rechazan parámetros adulterados. |
| P03 Dos conexiones reales | Barrera entre validar y guardar: conexiones independientes A/B, citas 09:00/09:15 de 30+10 para el mismo vet en Norte/Sur; solo una compatible confirma. Repetir con vets distintos y misma mascota global, mismo inicio, reprogramación y reserva, y hueco inicialmente vacío. Control: dos recursos independientes pueden confirmar. Releer después de esperar; rollback del ganador permite al segundo reevaluar. |
| P04 Horario/propuestas | Vet guarda directo:403; propone solo lo suyo en Norte; admin Sur no resuelve; aprobar/rechazar motivado en Norte; conflicto recurrente entre clínicas responde genérico. Editar/restaurar/cerrar mientras otra conexión reserva produce antes/después completo y marcas de citas afectadas, nunca una reserva oculta fuera de disponibilidad. |
| P05 Cuatro ramas/cuota | T rojos derivan antes de límites incluso en mora y con cupo de mascotas agotado; naranja consume bloque, amarillo primer espacio, verde elige. Nivel enviado por cliente no manda. Portal al límite recibe mensaje neutral y no deja vínculo nuevo; interno recibe motivo del plan. O-01/02/03/10. |
| P06 Revisión y aislamiento | T-20 sin oferta/POST reservable hasta resolución vet; T-22/T-16 según puerta aprobada. Propietario no se autoriza a sí mismo; cambio de marca, contexto, conocimiento o titularidad invalida resolución anterior. T-11 avisa aislamiento a Norte incluso si no hay cupo; agregar P1 conserva contagio con rojo. Sur no recibe aviso. |
| P07 Naranja/tope/orden | Tope=2: dos naranjas en bloque, tercero sin inserción y con aviso; dos conexiones por último cupo dejan uno. Empate fecha_registro ordena por id_cita; cambio sin motivo falla. Editar o partir bloque por ausencia no regala dos cupos extra. O-04/05. |
| P08 Reprogramación/cancelación | Fabio cancela/reprograma futura propia; mascota ajena, clínica desvinculada o estado ya cambiado:403/conflicto sin fuga. Intervalo completo y mes destino revalidan cuota; fracaso conserva cita original, instantáneas y vínculo. Auditoría+evento en commit; cancelar/no_asistio liberan espacio según regla. |
| P09 Tiempo/cascada | Verificar cada estado de A.3 bajo la política temporal que se apruebe, sin mezclar alternativas: servicio completo r=09:20/D=30/m=10 da N=09:50/L=10:00 bajo M; sin pausa, fin 09:39/09:40/09:41 da L=09:40/09:40/09:41. Tras pausa 09:10–09:30, verificar L=10:00 a las 09:59 y 10:00, y L=10:01 a las 10:01; cierre 10:05 no produce 10:15. Hueco absorbente, franja exacta y exceso durante atención sin calendario. Mismos hechos/reloj repiten idéntico resultado; tras completar, avanzar reloj tampoco desplaza L. Pactadas y sellos reales intactos. Conflicto externo del mismo vet/mascota devuelve solo indisponibilidad. |
| P10 Vencimientos | Destino correcto acepta antes del límite; otro vet/admin/Sur:403. Exactamente al límite vence; rechazo/no respuesta pasan siguiente candidato y sin ninguno dejan reprogramación pendiente. Dos conexiones aceptan/expiran o toman hueco: una transición, ningún traslado incompatible, sin dos pendientes activas. |
| P11 Avisos/correos | Correo/evento sin JS posterior; motivo/dirección/marca de su clínica; SMTP falla y reserva queda confirmada con fallo registrado; doble worker/POST no duplica evento. Diez recálculos de +3: umbral exacto no avisa, >15 sí con ventana >=30; máximo dos en hora, cambios de vet/reprogramación siempre. Sin correo/vínculo válido o cuenta pendiente no emitir automático. |
| P12 Ausencia/cobertura | Ausencia parcial y de día completo con extremos exactos; solicitud vet según decisión aprobada; registro/aprobación concurrente con reserva. Destino compatible recibe cobertura sin veto; sin cobertura cita pendiente y propuestas al propietario; avisos a ambos vets y propietario, auditoría y aislamiento. |
| P13 Presencia/urgencia | Rojo remoto a las 23:00 informa cierre/teléfono/24h, no pausa ni sella llegada. Presencial enlaza una única derivación; libre→pausable→espera sin vet. Todos no pausables: cero pausas, espera roja durable y estimación honesta. Quiebre de cuota/mora no bloquea el caso ni la ficha; O-01/02/09/10. |
| P14 Pausas | Cirugía no pausable nunca se interrumpe; vacunación pausable conserva inicio real, una pausa abierta y segmentos; vuelve antes de la siguiente tras cerrar rojo. Bajo la propuesta M: pausa 09:10–09:30 con r=09:00/D=30/m=10 deja R=20 al retomar, N=09:50/L=10:00; dos pausas 09:10–09:20 y 09:25–09:40 suman 25, R=15 al retomar, N=09:55/L=10:05. Si ya trabajó 40 min antes de pausar 09:40–10:00, no recuperar otro margen hasta 10:10; cierre 10:02 da L=10:02. Pausa abierta sin u sustentada solo muestra cota condicional, no libera al vet. Repetición no duplica pausa ni acumula otra vez su duración; rechazar intervalos solapados o cierre con pausa abierta. Doble llegada/cierre/reanudación no duplica. Consulta sobre pausada se rechaza sin escribir; fallo de auditoría revierte pausa/cita/urgencia/marcas. |
| P15 Identidad/conciliación | Cuatro ramas A.5, contacto vacío permitido; acompañante no se convierte en propietario/cuenta. Completar conserva ID; consolidar mueve todas las referencias enumeradas, conserva clínica/vet/HC/archivos y desactiva provisional/carnet. Titularidad falsa o ingreso Sur:403; dos conciliadores, choque HC y fallo a mitad producen rollback total, sin pérdida clínica ni reactivación de QR. |
| P16 Ajuste de nivel | Solo vet autorizado, motivo requerido, calculado intacto y ajustado separado con autor/fecha; bajar rojo a verde permitido con motivo (O-07), subir deriva/reubica y notifica. Repeticiones visibles solo en alcance aprobado, sin puntaje punitivo ni datos de Sur. O-08. |
| P17 Llegada/tolerancia | Llegadas con tolerancia 10 a 8, 10, 10min+1seg y 15 minutos; suficiencia/insuficiencia de tiempo, opciones y decisión del vet, auditoría y cascada. Si para cita 10:00 decide servicio completo e inicia realmente 10:10 con D=d=30/m=10, bajo M obtener N=10:40/L=10:50, sin mover hora pactada ni recortar D a 20. Si decidió otra duración, usar solo el valor registrado; falta de ese dato deja decisión/estimación pendiente, sin inventar duración efectiva. Llegada, inicio efectivo y reprogramación son hechos distintos; no sellar inicio por registrar llegada. Hora futura no_asistio rechazada; al marcar no asistió se vuelve a sugerir el espacio válido. |
| P18 Atención sin reserva | Desde Consultas y Pacientes, nueva atención crea una sola cita con origen y enlaza consulta; doble envío devuelve misma. Sin hueco aplica solo capacidad/orden aprobados; no convertir verde en naranja ni crear consulta huérfana. Históricos NULL siguen legibles como Sin cita. |
| P19 Marca/tipos | Norte y Sur con nombre/logo/contactos/tipos diferentes; admin ajeno no escribe, duración/margen/pausable y teléfonos validados servidor/en vivo; tipo desactivado no se usa para nueva reserva. Cambiar tipo no reescribe instantáneas anteriores. Marca por clínica en salidas acordadas, sin atribuir una sola clínica al historial global. |
| P20 Esquema/transición | Instalación nueva y actualización alpha.4 con datos, repetir migración; faltantes quedan pendientes sin inventar triage/horarios. FK/índices/columna calculada coherentes en MySQL/MariaDB; rollback y rechazo de referencias de clínicas distintas. No reiniciar base ni alterar 03–07. |
| P21 Regresión clínica | Mantener pruebas CE/CH/M2: inicio asignado/ventana, cierre atómico solo con consulta, continuar sin_cerrar, alerta única RN-409 y cron sin calendario; no reaparece cerrar sin consulta; peso desconocido, alergias RN-113, adjuntos y medicación protegida sin regresión. |
| P22 JS compartido | Reserva en calendario, Pacientes y portal usa mismo contrato: tiempos/exposiciones, nota sola, clínica/mascota cambiada, respuestas fuera de orden, revisión y doble envío. Arrastre/reprogramación servidor falla: restaura presentación y muestra error junto al campo. Conserva diseño y cortes RNF-16; verificación visual se describe en C/D, no se ejecuta en A. |
| P23 Contacto/retención | Con política aprobada, conciliación anonimiza contacto cuando procede sin eliminar actos ni autor; custodia excepcional con motivo visible solo a personal autorizado; sin exportar contacto, copiarlo a usuario ni ponerlo en auditoría/notificaciones. Solicitud de supresión conserva referencias y conserva solo lo justificado por el procedimiento vigente. |

**Trazabilidad por RE incluido.**
Las filas distinguen estado actual, brecha, responsable de implementación y evidencia concreta existente o propuesta.
Todas las P son futuras; A solo verifica el inventario.
Son 107 RE únicos: 101 de dominio (incluido RE-4.3.8 derogado para proteger su retirada) y seis transversales de acceso/aislamiento.

| RE | Comportamiento actual y brecha | Subetapa | Prueba existente → propuesta |
|---|---|---|---|
| RE-7.4.1 | Datos iniciales de clínica, sin editor de marca/teléfono urgencias operativo. | C1/C4a | CC parcial → P19/P13. |
| RE-7.4.2 | Catálogo aislado con duración/margen, sin gestión completa. | C1 | CT/CC lectura y copia → P19. |
| RE-7.4.3 | Margen guardado, no resta disponibilidad. | C1/C3 | CT instantánea → P02/P09. |
| RE-7.4.4 | Cabeceras/correos globales; procedencia clínica en registros, no marca completa. Aclarar salida global. | B/C1/F | JS/M2 imprimible parcial → P19/P11. |
| RE-7.4.5 | Lecturas aisladas y copia inicial; falta auditar edición nueva de marca/tipos. | C1 | CC/CT → P01/P19. |
| RE-7.4.6 | Campo pausable con default, sin formulario ni ejecución de pausa. | C1/C7a | Esquema solamente → P19/P14. |
| RE-4.11.1 | Tabla de horario vet sin escritura operativa. | C2 | CC solo clínica → P04. |
| RE-4.11.2 | No valida franjas recurrentes del vet ni choque entre clínicas. | C2 | Sin cobertura específica → P04/P03. |
| RE-4.11.3 | Sugerencias no leen franjas del vet. | C3 | CT parcial → P02. |
| RE-4.11.4 | No marca citas al cambiar horario. | C2/C5 | CC parcial → P04/P09. |
| RE-4.11.5 | No existen propuestas/aprobación funcionales. | C2 | Acceso base → P01/P04. |
| RE-4.12.1 | Ausencias en esquema, sin bloqueo operativo por rango. | C3/C6 | Sin cobertura específica → P02/P12. |
| RE-4.12.2 | Rango DATETIME documentado, sin validación de entrada del flujo. | C6 | Sin cobertura específica → P12. |
| RE-4.12.3 | Sin búsqueda/asignación de cobertura; no confundir reprogramación manual admin. | C6 | AP reasignación manual → P12. |
| RE-4.12.4 | Sin estado/propuesta pendiente al propietario cuando falta cobertura. | C6 | Sin cobertura específica → P12/P08. |
| RE-4.12.5 | Avisos básicos de citas, sin evento de cobertura a tres destinatarios. | C6 | NA parcial → P12/P11. |
| RE-4.13.1 | Motor/API calculan; reserva siempre entra verde. | C4a | TR → P05/P22. |
| RE-4.13.2 | LimitePlan exceptúa rojo evaluado, ruta normal no calcula primero. | C4a/C7a | LP → P05/P13. |
| RE-4.13.3 | No hay cuatro ramas operativas. | C4a/C4b/C7a | TR solo cálculo → P05/P13/P07. |
| RE-4.13.4 | Horario inicial de clínica + duración; falta intersección/margen/ausencias. | C3 | CT/CC parciales → P02. |
| RE-4.13.5 | Candado de clínica e índice por comienzo, insuficientes entre clínicas. | C3/C4b | CT/MY simulación/índice → P03/P07. |
| RE-4.13.6 | Guarda tipo/duración/margen/prioridad y audita alta; faltan episodios y entrega fiable. | C4a | CT/PA → P05/P06/P11. |
| RE-4.13.7 | Tope/orden existen en datos, sin consumo de bloque. | C4b | Sin cobertura específica → P07. |
| RE-2.11.1 | API valida episodios y nota sola; captura de Agendar no integrada. | C4a | TR/BuscadorSintomasTest → P22/P06. |
| RE-2.11.2 | Máximo y P3 implementados; conservar al reservar. | C4a | MotorTriageTest T-04/T-18 → P05. |
| RE-2.11.3 | Contexto/modificadores y tiempo calculados; falta guardar entrada de cita. | C4a | TR T-09/10, T-11/12, T-14/16 → P05/P06. |
| RE-2.11.4 | P1 universal probado; falta derivación real. | C4a/C7a | TR T-21 → P13. |
| RE-2.11.5 | Motor indica revisión; no hay puerta humana previa al horario. | C4a/C4b | TR T-20/T-22 → P06. |
| RE-2.11.6 | Bandera de contagio sí; aviso de aislamiento no. | C4a | TR T-11 y rojo+contagio → P06/P11. |
| RE-2.11.7 | Batería automatizada de 26 ya existe; conservar sin variar esperados. | Todas/D | MotorTriageTest::testBateriaConConocimientoReal → misma batería más P05/P06. |
| RE-4.14.1 | No hay cálculo de absorción, solo vigilante de atención abierta. | C5a | CE no equivale → P09. |
| RE-4.14.2 | Sin estimaciones persistidas ni procesador de cascada. | C5a | Sin cobertura específica → P09. |
| RE-4.14.3 | Sin emparejamiento de cita fuera de franja. | C5a/C5b | AP solo reasignación manual → P09/P10. |
| RE-4.14.4 | Tabla de propuestas sin aceptación del destino. | C5b | Sin cobertura específica → P10. |
| RE-4.14.5 | No existe estado/reprogramación concertada por cascada. | C5b | NA/AP parciales → P10/P08/P11. |
| RE-4.14.6 | Plazo en clínica/FK en esquema, sin vencimiento operativo. | C5b | Sin cobertura específica → P10. |
| RE-4.14.7 | Sin acumulado/base comunicada ni ventana por cita. | C5b | NA no prueba RN-429 → P11. |
| RE-4.15.1 | Motor devuelve rojo; no registra derivación/ingreso operativo. | C4a/C7a | TR → P13. |
| RE-4.15.2 | Sin cola/asignación/pausa; FK vet obligatoria en cita. | C7a | Esquema no basta → P13/P14. |
| RE-4.15.3 | Ficha provisional e ingreso en esquema; no cuatro ramas ni conciliación. | C7b | CH solo actos existentes → P15/P23. |
| RE-4.15.4 | Consulta existente no integra urgencia ni provisional. | C7a/C7b | CH → P13/P15/P21. |
| RE-4.15.5 | Sin cascada al ocupar emergencia. | C7a | Sin cobertura específica → P13/P09. |
| RE-4.15.6 | Horario abierto/cerrado visible en portal, sin mensaje rojo/teléfono específico. | C4a | HorarioAtencionTest → P13. |
| RE-4.15.7 | Excepción del modelo de cuota probada, falta atravesar ficha/vínculo/urgencia. | C4a/C7a/C7b | LP → P05/P13/P15. |
| RE-4.15.8 | Estado/campo de pausa, sin transición/reanudación. | C7a | CE no cubre pausa → P14. |
| RE-4.18.1 | Motivo/campos existen, falta acción autorizada. | C4b/C8 | Acceso/TR parcial → P16/P06. |
| RE-4.18.2 | Prioridades separadas en esquema, sin historial operativo. | C4b/C8 | Sin cobertura específica → P16. |
| RE-4.18.3 | No reubica ni avisa tras ajuste. | C8 | Sin cobertura específica → P16/P09/P11. |
| RE-4.18.4 | No historial de reclasificaciones; destinatario ambiguo. | B/C8 | Sin cobertura específica → P16/P01 tras aclaración. |
| RE-4.19.1 | hora_llegada existe, no se sella en ruta actual. | C8 | Sin cobertura específica → P17. |
| RE-4.19.2 | Se puede iniciar tarde el mismo día, sin duración restante/tolerancia. | C8 | ReglaAtencionTest parcial → P17. |
| RE-4.19.3 | no_asistio existe; no conjunto de tres opciones por tolerancia. | C8 | CE/AP parcial → P17. |
| RE-4.19.4 | Sin cálculo ni auditoría de decisión de llegada. | C8 | Sin cobertura específica → P17/P09. |
| RE-4.1.1 | Guarda campos básicos, interno/portal; falta triage compartido y restricción admin. | C3/C4a | CT/PA/AP → P01/P05. |
| RE-4.1.2 | Solape secuencial de vet sí; margen/carrera interclínica pendientes. | C3 | CT/MY → P02/P03. |
| RE-4.1.3 | Solo valida hora inicial de clínica. | C3 | AP/CC → P02. |
| RE-4.1.4 | Correo mediante POST posterior de JS; no entrega asegurada por crear. | C4a | JS parcial → P11. |
| RE-4.1.5 | Citas en calendario por clínica/vet, con eventos preventivos. | C4a/C5/D | AP → P22/P09/P21. |
| RE-4.2.1 | Cancelar y reprogramar internas funcionan, portal solo cancela. | C4b | AP/CT/PA parcial → P08. |
| RE-4.2.2 | Avisos internos y correo separado dependiente de cliente. | C4b | NA/JS parcial → P11/P08. |
| RE-4.2.3 | Cancela conservando fila y estado. | C4b/D | CE/CT → P08/P21. |
| RE-4.2.4 | Rechaza solape secuencial; falta margen y carrera. | C3/C4b | CT/AP → P03/P08. |
| RE-4.3.1 | Cierre condicional de en_curso/sin_cerrar, no de pendientes. | C7a/D | CE → P14/P21. |
| RE-4.3.2 | Consulta completa cita en su transacción; incorporar pausa/urgencia sin romperlo. | C7a | CH/CE/MY → P14/P21. |
| RE-4.3.3 | Portal separa próximas/históricas; listarRango no filtra completadas por sí mismo. | C5/D | PresentacionPortalTest/AP parcial → P21/P22 con exclusión explícita de futura. |
| RE-4.3.4 | Solo asignado, día, desde 15 minutos antes. | C7a/D | AP/CE/Acceso → P21. |
| RE-4.3.5 | Continuar en_curso/sin_cerrar funciona; pausada requiere reanudar primero. | C7a | CE/AP → P14/P21. |
| RE-4.3.6 | Vigilante sella y avisa una vez diez minutos tras fin. | C5/C7a/D | CE/MY cron → P21/P14. |
| RE-4.3.7 | Pasa a sin_cerrar al día siguiente, no borra atención. | C7a/D | CE → P21 y pausa que cruza día. |
| RE-4.3.8 | Derogado; la acción cerrar sin consulta ya no existe. | D | CE::testYaNoExisteCerrarSinConsulta → P21; no reintroducir. |
| RE-4.3.9 | Cron cada cinco minutos y respaldo al cargar calendario. | C5/D | MY::testC8CronRealConCorreoSimuladoYDosClinicas → P21, conservarlo junto al nuevo procesador. |
| RE-4.4.1 | Segunda petición de navegador, no evento de servidor tras commit. | C4a | JS parcial → P11 sin segunda petición. |
| RE-4.4.2 | Cuerpo actual omite motivo y dirección aunque select los obtiene. | C4a | Inspección enviarCorreoCita → P11. |
| RE-4.4.3 | Responde antes de SMTP; conservar vía cola tras commit. | C4a | ResponderYSeguir inspeccionado → P11 con transporte lento/fallido. |
| RE-4.4.4 | Boolean/error_log, sin estado durable del fallo inmediato. | C4a | NA solo recordatorios → P11. |
| RE-4.5.1 | Inicio/fin reales se sellan, falta conservar segmentos al pausar. | C5/C7a | CE/CH → P14/P21. |
| RE-4.5.2 | Vigilante detecta abierta tras gracia, no exceso contra margen durante atención. | C5a | CE parcial → P09. |
| RE-4.5.3 | No mueve estimaciones siguientes. | C5a | Sin cobertura específica → P09. |
| RE-4.6.1 | Campo margen sin gestión completa. | C1 | CT/CC parcial → P19. |
| RE-4.6.2 | Ocupación ignora margen. | C3 | CT detecta duración → P02/P09. |
| RE-4.7.1 | Solo asignado marca abierta cuya hora pasó. | C8/D | CE/AP → P17/P21. |
| RE-4.7.2 | Estado libera en consultas e índice. | C3/C8 | CE/CT → P17 y nueva sugerencia completa. |
| RE-4.8.1 | Ausencias solo en tabla, sin flujo de bloqueo por fecha. | C3/C6 | Sin cobertura específica → P12/P02. |
| RE-4.8.2 | Día semanal inactivo sí, cierre puntual no. | B/C2/C3 | CC parcial → P02/P04. |
| RE-4.8.3 | Excluye bloques semanales apagados, no ausencia/cierre fechado. | C3 | CC → P02. |
| RE-4.9.1 | Cliente cruza listas; servidor valida inicio separadamente. | C3 | CT/CC/AP parciales → P02/P22. |
| RE-4.9.2 | Duración no se comprueba contra fin de franja de clínica/vet. | C3 | AP parcial → P02. |
| RE-4.9.3 | Índice y candado clínico no resuelven intervalo global vacío. | C3 | CT/MY índice → P03. |
| RE-5.3.1 | Selección de mascota propia y validación servidor. | C4a/D | PA::testNoAgendaParaUnaMascotaAjenaNiConUnVeterinarioDeOtraClinica → P01. |
| RE-5.3.2 | Selección tipo/vet/fecha/hora; integrar ramas de triage. | C4a | PA/JS → P05/P22. |
| RE-5.3.3 | Dinámica por intersección cliente de listas incompletas. | C3/C4a | PA/JS → P02/P03/P22. |
| RE-5.3.4 | Alta auditada, correo separado de cliente. | C4a | PA/CT → P11. |
| RE-5.3.5 | Vincula mascota y consume plan con rollback al fallar. | C4a | PA::testAgendarConLaMascotaSinVincularLaVincula/testSiLaReservaFallaLaMascotaNoQuedaVinculada → P05/P03. |
| RE-5.6.1 | Propietario cancela abierta propia; falta exigir futura explícitamente en servidor. | C4b | AP cancelación propia → P08 con pasada/futura. |
| RE-5.6.2 | Portal no tiene ruta de reprogramación propia. | C4b | Sin cobertura específica → P08/P22. |
| RE-5.6.3 | Cancelación comprueba dueño/vínculo; extender a reprogramar. | C4b | AP/PA → P01/P08. |
| RE-5.6.4 | Cancelar avisa a clínica; no audita cambio en CitaController; reprogramación portal falta. | C4b | NA/AP parcial → P08/P11. |
| RE-2.2.1 | Atención sin cita crea consulta id_cita NULL; criterio debe cambiar por decisión. | B/C8b | CH::testCualquierVeterinarioDeLaClinicaRegistraSinCita → P18. |
| RE-2.2.2 | Busca paciente y exige motivo/diagnóstico; conservar en nueva cita de llegada. | C8b | CH/JS consulta → P18/P21. |
| RE-2.2.3 | HC se asigna una vez por mascota/clínica; conservar transacción/candado. | C8b/C7b | CH numeración y MY::testC4ElNumeroDeHistoriaSeAsignaBajoCandadoSinRepetirse → P15/P18. |
| RE-2.2.4 | Etiqueta Sin cita depende de NULL; nuevas llegadas requieren origen persistido. | B/C8b | CH/JS listados → P18, históricos y nuevos diferenciados. |
| RE-T.10.1 | Matriz previa y cierre por defecto; cambiar creación admin y enumerar rutas nuevas. | C2–C8/D | Acceso → P01. |
| RE-T.15.1 | Aislamiento de modelos; ocupación global es excepción técnica mínima. | C3–C8/D | CT/AP/PA/CH/NA → P01/P03/P15. |
| RE-T.15.2 | Recurso ajeno responde 403 auditado; preservar incluso al límite. | Todas/D | CT/AP/LP → P01/P15. |
| RE-T.15.3 | Security controla rol/CSRF/contexto; faltan acciones de M4. | Todas/D | Acceso/TR → P01/P04/P10. |
| RE-T.15.4 | Plataforma no tiene permiso clínico; no ampliarlo con urgencias. | Todas/D | Acceso/TR → P01/P13. |
| RE-T.15.5 | Aislamiento centralizado con fixtures Norte/Sur; ampliar a nuevas entidades. | Todas/D | DosClinicas y suites anteriores → P01/P03/P06/P12/P15. |

**Alcance diferido individual:** RE-4.7.3, RE-4.10.1, RE-4.10.2, RE-4.10.3, RE-4.16.1, RE-4.16.2, RE-4.17.1, RE-4.17.2, RE-4.17.3 y RE-4.17.4 quedan fuera de esta entrega.
No usar confirmación administrativa existente como cumplimiento de HU-4.10 ni desempate técnico como afinidad de HU-4.16.
O-06 sigue pendiente de v2.1.
O-01/02/09/10 se cubren operativamente en P05/P13/P15; O-03/04/05 en P07; O-07/08 en P16; O-11 conserva las alergias globales de M2 y se verifica en P05/P06/P21.
Los 26 T conservan datos y esperados originales, incluidas las reservas de T-16/T-20; no se declara validación veterinaria por pasarlos.

### A.8. Propuestas pendientes de aprobación

Las decisiones de §6 (actores, alcance v2.1, cita obligatoria por atención sin reserva y captura opcional del acompañante) ya están confirmadas.
Esta tabla no las reabre.
La tabla conserva las propuestas originales y sus alternativas para trazabilidad. **Actualización (2026-10-17):** §6 ya confirma capacidad no urgente, bloque naranja, aprobación de ausencias, revisión previa, orden rojo, contacto del acompañante, permisos de conciliación y marca/reclasificaciones. Las decisiones fechadas de §6 prevalecen sobre el estado pendiente histórico de esta tabla. Quedan para B1 el cierre técnico de desempates/candados/HC, la propuesta mínima de esquema y la precisión verificable de los criterios sin inventar reglas adicionales.

| Asunto | Propuesta concreta y recomendación | Naturaleza / condición |
|---|---|---|
| Capacidad sin hueco para llegadas no urgentes | Recomiendo **cero sobrecupos no urgentes automáticos**: la llegada pasa a evaluación/espera de solicitud, ofrece primer hueco compatible u otro vet de la misma clínica y no inicia atención hasta crear su cita de llegada. Al liberarse capacidad se ordena por nivel aprobado y, dentro de cada nivel, llegada física/ID; verde no adquiere prioridad naranja. Alternativa para el usuario: autorizar **un cupo adicional de llegada por bloque**, separado del tope naranja, solo con decisión motivada del vet y propagación visible del retraso. | Regla de producto: necesita aprobación antes de B/C8b. En ambas variantes, toda atención tiene cita; esperar no es registrar una consulta oculta. La segunda variante requiere parámetro propio propuesto, clase de cupo y estado de espera explícitos; no reutilizar es_sobrecupo ni aprobar un default por este anexo. |
| Relación del cupo de llegada con RN-422 | Los naranjas cuentan exclusivamente para el tope naranja existente. Si se elige el cupo adicional de la alternativa anterior, contador independiente; rojos conservan excepción, demás colores respetan cuota del plan y capacidad admitida. No desplazar silenciosamente reservas ya pactadas. | Nueva regla de capacidad con aceptación y prueba; falta en HU-2.2/RN actuales. No implementarla por inferencia de RN-422. |
| Bloque y cupo consumido | Recomiendo bloque fechado de intersección recurrente sin multiplicar cupo al restar ausencias; completados consumen cupo de ese bloque, cancelados/no_asistio lo liberan. | Concreción de RN-422 de A.3, a ratificar antes de B/C4b; ajustar matriz y Modelos. |
| Desempate de veterinarios compatibles | Para búsqueda automática, elegir inicio compatible más temprano y luego id_usuario ascendente; si el actor ya eligió vet y puede conservarlo, respetarlo. Para ausencia/en vivo, usar igual orden y saltar candidatos ya intentados en el mismo ciclo. Sin afinidad, reputación, puntuación clínica ni sorteo; no comparar nombres. | Decisión técnica determinista propuesta, revisión independiente antes de C3/C5/C6. Cualquier preferencia por carga/especialidad/rotación sería regla nueva de producto y no se da por aprobada. |
| Solicitud de ausencia | Recomiendo que la solicitud propia quede pendiente y solo tape agenda al aprobarla el admin; registro directo del admin es efectivo. Rechazo requiere motivo. Una ausencia no puede ocultar citas afectadas durante su resolución. | RN-414 admite solicitar, pero no especifica la aprobación: requiere confirmar y plasmar en HU/RE/Modelos §8/§13.4 antes de C6. |
| Revisión previa a reservar | Mínimo obligatorio: T-20/revision_inmediata nunca recibe horario antes de vet. Recomiendo exigir liberación también a todo revision=true, dejando T-16/T-22 amarillos provisionales hasta resolución. Sin tiempo de respuesta inventado. | La extensión a toda revisión es propuesta de seguridad de flujo, no una regla clínica nueva confirmada. Aprobar antes de B/C4a; nota libre sigue fuera del cálculo. |
| Orden rojo presencial | Recomiendo FIFO por llegada física/ID entre rojos presentes, con prioridad de reanudación de la cita pausada al finalizar su urgencia conforme RN-427; cualquier cambio clínico de orden se motiva y audita. Una nueva alarma roja se evalúa clínicamente, sin reglas automáticas de interrupción anidada. | Falta política expresa para empates de rojos y nuevas urgencias mientras hay pausa; aprobar antes de C7a. No convertir orden técnico en una decisión clínica ya autorizada. |
| Contacto provisional | Recomiendo usar nombre/teléfono opcionales exclusivamente para atención/conciliación; al resolver titularidad y finalizar esa necesidad, anonimizar esos campos a NULL y sellar fecha/actor/causa, conservando ingreso, IDs y actos. Si el responsable identifica una obligación de custodia o conciliación aún pendiente, conservar solo lo necesario con motivo y revisión humana; sin borrado automático por días. Alternativa: conservación justificada junto al expediente con acceso restringido y procedimiento de supresión existente. | Política local views/legal/privacidad.php §Vigencia conserva datos según cuenta/obligaciones, pero no fija plazo para acompañante sin cuenta. RN-G07/G16/G19 y plan HU-4.15 no autorizan inventar uno ni confundirlo con los 72 h de invitaciones. Requiere aprobación antes de B/C7b; esto es alineación con política existente, no dictamen jurídico ni aprobación legal. |
| Quién concilia | Proponer vet de la clínica para ingreso/actos; admin solo para completar/conciliar identidad conforme al permiso específico aprobado; propietario aporta/verifica su titularidad, sin fusionar por sí mismo historia ni adjudicarse mascota con ID. Nunca un endpoint público. | «Personal autorizado» no enumera acciones en plan HU-4.15; fijar matriz explícita en B/C7b, sin retirar las facultades administrativas actuales ajenas a este caso. |
| Marca y reclasificaciones | Marca por clínica en contexto/evento/documento de esa clínica; plataforma en historial multi-clínica con identificación por acto. Reclasificaciones del propietario visibles a personal de la clínica dentro de sus casos, sin sanciones automáticas. | Aclaraciones RE-7.4.4/RE-4.18.4 pendientes antes de C1/C8; cualquier vista adicional del propietario debe definirse expresamente. |

Los extremos de intervalo, fórmula de margen, candados, idempotencia, proyección mínima de ocupación y separación de servicios son decisiones técnicas propuestas para revisión del contrato.
No cambian por sí solas el alcance confirmado.
La aprobación del plan anterior no equivale a aprobación de los defaults/reglas nuevas de esta tabla.

### A.9. Dependencias: ajuste mínimo propuesto antes de implementar

| Dependencia detectada | Ajuste mínimo de orden/contrato, pendiente de revisión |
|---|---|
| C2 marca citas para un C5 que aún no existe | B define estado/versionado y C2 muestra la marca pendiente con aviso explícito, sin movimiento automático. C5a toma esas marcas idempotentemente. Cambiar horario o restaurarlo ya usa protocolo de exclusión de C3, por lo que el coordinador básico de candados se adelanta a C2; C3 completa el cálculo. |
| C3 debe restar ausencias que se gestionan en C6 | B fija representación y estado efectivo; C3 ya sabe restar rangos aprobados y cierres. C6 aporta solicitud/cobertura. No permitir que C3 ignore filas existentes por «etapa posterior». |
| C4a requiere revisión humana que HU-4.18/C8 deja tarde | Adelantar a C4a la evaluación pendiente y resolución mínima motivada del vet, con calculado/ajustado y revalidación. C4b integra reubicación al reservar; C8 completa historial/reclasificación al llegar. No ofrecer horarios con una bandera sin resolución operativa. |
| C4a exige naranja pero su tope es C4b; rojo clínico es C7 | No declarar C4a funcionalmente cerrada en cuatro ramas si solo deriva a acciones inexistentes. Separar la entrega de captura/derivación remota de la habilitación integral: integrar C4b y C7a antes de habilitar todo el nuevo agendamiento. Si se requiere desplegar C4a sola, debe acordarse explícitamente el comportamiento provisional y su aceptación, sin presentar deuda como RE cumplido. |
| C4a promete correo fiable, RN-429 se construye en C5b | B define evento durable e idempotencia; C4a entrega productor/consumidor mínimo de confirmación/cambio y registro de fallo. C5b agrega acumulación/ventana de estimaciones. No dejar la garantía de correo básico dependiendo de una futura etapa ni de JS. |
| C5a predice fin de franja antes de aceptación/reprogramación de C5b | C5a puede calcular y persistir estado pendiente visible, sin mover vet ni hora pactada. C5b resuelve mediante propuesta/aceptación/reprogramación y siempre revalida. La integración se considera completa al revisar ambas; no simular aceptación. |
| C7 usa atención/llegada de HU-2.2, ubicada en C8b | Adelantar a C7a la infraestructura común de crear cita desde una llegada y conservar origen, solo con política roja ya aprobada. C8b integra el acceso no urgente y capacidad aprobada. No habilitar un atajo clínico sin cita entre esas etapas ni aplicar RN-422 a verde. |
| Pausas interfieren con cierre de consulta/numeración actual | C7a incluye Consulta::exigirCitaParaConsulta/completarCita, VigilanteAtenciones y protocolo HC; prueba cero cierres sobre pausadas. No esperar a C8b/D para resolver el candado o perder continuidad de M2. |
| Ficha provisional necesita contexto incompleto que M2 presupone existente | C7b incorpora adaptador de ficha por completar con datos desconocidos y vínculos/HC legítimos; mantiene los predicados M2, sin modificar conocimiento ni inventar edad/especie. La entrada roja no depende de lograr registrar una mascota ordinaria con propietario. |
| Retención, capacidad y campos nuevos condicionan B | Revisar A y resolver A.8 antes de generar SQL/contratos normativos afectados. Si el usuario aplaza una decisión, puede avanzarse únicamente en partes independientes aprobadas; no habilitar flujos que necesiten ese dato. |

La tabla de §3 conserva el recorrido aprobado, con estas propuestas de anticipación mínima de infraestructura.
El revisor debe incorporar el ajuste preciso al preparar el prompt de B y de cada etapa; A no empieza ninguna.

### A.10. Verificación de A y entrega

La verificación consiste en leer código/especificación y ejecutar las suites existentes.
No se escribieron pruebas nuevas ni se recorrieron pantallas.
La prueba headless que ya forma parte de JS es automática y no constituye un recorrido manual de la aplicación.

| Verificación ejecutada el 2026-10-17 | Resultado |
|---|---|
| PHP completo sin host: php vendor/phpunit/phpunit/phpunit --do-not-cache-result; ZOOKI_TEST_MYSQL_DB=zooki_test_base_v2 y host vacío | 854 pruebas, 7212 aserciones, 44 omitidas de MySQL. Código de salida 0. Incluye la batería T de M2. |
| PHP completo con ZOOKI_TEST_MYSQL_HOST=127.0.0.1, PORT=3306, USER=root, PASS vacío y DB=zooki_test_base_v2 | MariaDB 10.4.32: 854 pruebas, 7813 aserciones, ninguna omitida; código 0. Configuración explícita de pruebas independiente del archivo de entorno. No se instaló ni inició ningún servicio. |
| JS existente: node --test "tests/Frontend/**/*.test.cjs" | 238/238, cero fallos, canceladas u omitidas; código 0. Incluida medición automática headless de C8.2. |
| Diff y alcance | git diff --check sin incidencias; solo specs/M4-agenda-grafo-ii.md y agentes/estado.md modificados. Sin código de aplicación, migraciones, tests nuevos, exportación ni versión. |

Los primeros intentos dentro del aislamiento no son evidencia de regresión: PHP detectó bootstrap.php como no legible y, usando un bootstrap temporal, produjo errores de acceso a recursos de OneDrive; Chrome de JS no arrancó y esa corrida se interrumpió.
Se repitieron las suites originales fuera del aislamiento, sin editar fuentes, y dieron los resultados de la tabla.
El intento de copia temporal de fuentes para aislar el problema no se usó como evidencia; la ejecución válida fue sobre el checkout actual.
La disponibilidad de MariaDB se comprobó con mysql --no-defaults y SELECT VERSION(), sin seleccionar base ni consultar credenciales de la aplicación.
Solo la suite de pruebas creó/recreó su base desechable zooki_test_base_v2 y sus fixtures; no se regeneraron cuentas o contraseñas de trabajo ni se ejecutó scripts/dev/datos_prueba.php.
No se leyó ni escribió .env desde las acciones de esta entrega; no se usó zooki_v2_prueba.
**Limitación:** no se verificó con MySQL 8 en esta máquina; MariaDB real sí se verificó. Las pruebas concurrentes específicas P03/P07/P10/P15 todavía deben implementarse en sus etapas.

**Límite de la evidencia:** las suites existentes comprueban la base actual; P01–P23 siguen propuestos para la implementación.
Ni tablas existentes ni casos T en verde acreditan que la agenda operativa, la concurrencia de intervalos, la revisión humana o la conciliación de M4 ya funcionen.

**Siguiente:** revisión independiente de A por Codex y resolución de A.8; después preparar B.
No hay veredicto de revisión en este anexo.
No comenzar B ni modificar HU/RE/RN/diagramas hasta esa revisión y las aprobaciones que correspondan.

**Comandos de commit para el usuario, PowerShell 5.1:**

    git diff --check
    git add -- specs/M4-agenda-grafo-ii.md agentes/estado.md
    git commit -m "docs(agenda): inventario y contratos de A (HU-4.13, RE-4.9.3, RE-4.15.3)"

Son comandos para el usuario; el agente no ejecutó add, commit, push, tag ni cambios de rama.

### A — Revisión (2026-10-17)

**Revisó Codex, arquitecto de esta conversación; el Anexo A fue escrito por otra sesión ejecutora de Codex. Veredicto: revisada con corrección requerida A.1.** El inventario y su trazabilidad son válidos; no se aprueba todavía el contrato completo para implementar B. La corrección es documental y corresponde al ejecutor; el revisor no reescribe su algoritmo.

**Alcance y evidencia propia.** Se revisaron el diff respecto de HEAD y el anexo completo. Solo cambian este plan y agentes/estado.md: no hay código, tests, migraciones ni documentos normativos nuevos. Se comprobaron las 107 filas de RE, sin duplicados y con identificadores existentes en RequisitosEspecificos.md; las P01–P23 son propuestas de pruebas, no evidencia ejecutada de M4.

Se contrastaron directamente `Cita::sugerencias/exigirHorarioLibre/ocupadas`, `LimitePlan::transaccion`, `Consulta::registrar/tomarCandadoNumeracion`, creación/reprogramación/cancelación/correos de CitaController, matriz de Security, esquema de citas y las pruebas existentes de agenda y portal. Se confirma el horario fijo de sugerencias, el margen omitido de ocupación, la serialización local por clínica y el límite del índice por comienzo; la prueba de carrera actual de CitaTest usa una sola conexión y no demuestra la garantía interclínica propuesta. También se confirma la falta de comprobación directa de propietario_clinica en Recordatorio::citasDeManana; se mantiene como defensa pendiente, sin afirmar una fuga alcanzable demostrada.

**Comparación con diagramas.** Modelos §2/§4–§8 y §13.4 respaldan disponibilidad, propuestas de horario, cobertura sin veto y reasignación con aceptación. Las diferencias de A.1 son pertinentes: falta puerta humana previa al horario, el diagrama remoto rojo entra directamente en asignación/pausa, §5 no explicita el cálculo durante una atención abierta ni evita inequívocamente el doble descuento del margen, y §8 omite la solicitud del veterinario. §3 y HU-2.2 necesitan alinearse con la cita de llegada confirmada por el usuario. A hizo bien en documentarlas sin cambiar todavía los criterios ni los diagramas.

**Verificación propia:**

- PHP completo sin host MySQL, fuera del aislamiento de archivos de OneDrive: **854 pruebas, 7212 aserciones y 44 omitidas**, salida 0.
- PHP completo con MariaDB local: **854 pruebas, 7813 aserciones y ninguna omitida**, salida 0 (5 min 17 s). Configuración explícita: host 127.0.0.1, puerto 3306, usuario local de pruebas y exclusivamente `zooki_test_base_v2`; versión comprobada 10.4.32-MariaDB. No se declara verificación con MySQL 8.
- JavaScript completo, incluida la prueba automática de Chromium: **238/238, cero omitidas y cero fallos**, salida 0. El intento dentro del aislamiento quedó sin completar y se detuvo; solo se usa como evidencia la ejecución completa fuera de él.
- `git diff --check` sin incidencias. Las suites verifican la base existente y la batería T de M2, no implementaciones futuras de M4. No se recorrieron pantallas, se leyeron credenciales de la aplicación ni se ejecutó el generador de usuarios.

**Hallazgo que exige corrección antes de B:**

| ID / prioridad | Archivo y líneas revisadas | RE/RN | Reproducción y efecto | Corrección exigida |
|---|---|---|---|---|
| H-A1 / P2 — Base temporal de cascada incompleta para pausa e inicio real | `specs/M4-agenda-grafo-ii.md:294–301`, ejemplo de pausa en línea 310 (numeración al revisar A) | RE-4.5.1–RE-4.5.3, RE-4.14.1–RE-4.14.2, RE-4.15.8; RN-412, RN-415, RN-427 | Con inicio estimado e=09:00, d=30 y m=10, la pausa 09:10–09:30 deja 20 minutos activos y fin 09:50. Aplicar literalmente `max(e+d+m, fin_real)` libera a las 09:50, pero la tabla exige 10:00. No se define qué base reemplaza e+d tras una pausa. Tampoco queda fijada la base si inicia realmente a las 09:20 y el vet decide prestar los 30 minutos completos: el fin nominal no puede seguir en 09:30. «Recalcular desde la causa» no permite implementar ni comprobar una única interpretación. | Definir por estado la base de inicio/fin nominal, tiempo activo restante y efecto de una o varias pausas; preservar hora pactada e inicio real. Unificar fórmula y ejemplos, sin sumar margen dos veces ni inventar una duración efectiva cuando el vet eligió otra opción de llegada tarde. Añadir ejemplos aritméticos de inicio tardío con servicio completo, pausa única, varias pausas, sobrepaso tras reanudar y repetición del cálculo. Indicar qué campos existentes/propuestos sostienen cada valor. |

Este hallazgo afecta a un contrato propuesto, no demuestra una regresión en producción ni justifica cambiar el código actual durante A.1. La decisión de cómo absorber el margen tras una pausa debe quedar explícita: si la corrección revela una regla de producto nueva, presentarla al usuario como propuesta en lugar de elegirla por su cuenta.

**Contrato técnico aceptable como dirección, con condiciones para B:** intervalos semiabiertos, separación de horas pactadas/estimadas/reales, candados sobre recursos estables, relectura vigente tras esperar, idempotencia transaccional, proyección mínima de ocupación global y separación cobertura/reasignación. Antes de escribir SQL, cerrar un único protocolo para la numeración HC y los nuevos candados: A.4 reconoce correctamente la inversión posible con GET_LOCK, pero no se implementarán simultáneamente sus dos alternativas. Las siete tablas nuevas propuestas en A.6 no quedan aprobadas automáticamente por validar el inventario: B debe justificar y revisar el esquema mínimo después de resolver las decisiones de producto.

**Dependencias:** se acepta el diagnóstico de A.9. Los prompts deben adelantar coordinación básica a C2, lectura de ausencias/cierres a C3, resolución mínima de revisión y eventos de correo a C4a, y el cierre/continuidad de consulta al introducir pausas. C4a no puede declararse cierre integral de las cuatro ramas ni habilitarse con rutas inexistentes; no hay aprobación de un despliegue intermedio que degrade urgencias.

**Siguiente: A.1 — corrección documental del contrato temporal**, escrita por el ejecutor y revisada de nuevo por este arquitecto. Solo plan y estado; sin código, SQL, tests nuevos ni cambios normativos. Conservar el Anexo A y esta revisión, agregar resultado de A.1 y marcarla entregada, pendiente de revisión. No hace falta repetir las suites completas por una corrección limitada a texto: usar esta línea base y comprobar diff, aritmética y trazabilidad. Después, resolver las propuestas de A.8 por grupos antes del prompt de B; ninguna se convierte aquí en «Decisión del usuario».

**Prueba manual:** no corresponde una lista de pantallas en A/A.1 porque no cambia ninguna funcionalidad. Las listas de 3–5 pruebas por usuario se entregarán con las subetapas que sí modifiquen interfaz o comportamiento.

## Anexo A.1 — Resultado

**Fecha:** 2026-10-17.
**Autor:** Codex ejecutor, en esta sesión.
**Revisor previsto:** Codex arquitecto, en la conversación independiente.
**Estado:** entregada, pendiente de revisión.
**Alcance:** corrección documental de H-A1; el cierre del hallazgo corresponde al revisor.
Se conserva íntegra «A — Revisión (2026-10-17)».
No se implementan funcionalidades ni se empieza B.

**Corrección entregada.**
Se sustituyó la fórmula de A.3 que conservaba e+d después del inicio o la pausa por una definición explícita de N/F/L en cinco estados: sin iniciar, iniciada, pausada, reanudada y completada.
Se distinguen s/d/m pactados, r/f reales, D como objetivo activo del servicio decidido, A como tiempo activo ya trabajado y R como restante nominal.
Con una pausa abierta, el instante de reanudación u es una predicción condicional; nunca acredita reanudación ni libera de hecho al veterinario.
Las estimaciones anteriores no se usan como un delta que se acumula al repetir el cálculo.

**Resolución aritmética del ejemplo, bajo la propuesta recomendada M.**
Inicio real 09:00; D=30; trabajo 09:00–09:10=10; pausa 09:10–09:30=20; restante al retomar=30−10=20.
El fin nominal pasa de 09:00+30=09:30 a N=09:00+30+20=09:50.
Si termina a las 09:50, F=f=09:50 y L=máximo(09:50+10, 09:50)=10:00.
La hora de 09:50 es el fin clínico nominal/real de ese ejemplo, no el extremo de ocupación con margen bajo M.
Antes se comparaba erróneamente con 09:00+30+10=09:40, una base que omitía la interrupción.
Se mantiene r=09:00, la hora pactada y los dos extremos reales de la pausa; no se reinicia la consulta a las 09:30 ni se agregan dos márgenes.

**Política de producto pendiente, limitada a H-A1.**
RN-412 y RN-415 definen absorción por margen, y RN-427 exige reanudar después de la urgencia, pero no fijan expresamente si un inicio tardío/interrupción desplaza también la ubicación de ese margen.
No se trata como una decisión ya confirmada.

| Alternativa | Fórmula coherente y efecto | Valoración |
|---|---|---|
| M — Margen referido al nominal desplazado | Reconstruir N desde inicio real, D y pausas; L_M=máximo(N+m, F). Una sola pausa de 20 min desplaza el nominal 20 min, sin borrar tiempo activo ni crear un margen por pausa. | **Recomendada**, pendiente de aprobación: conserva el resultado 10:00 de la tabla original y la duración completa decidida; hace visible todo el desplazamiento del comienzo/interrupción. El margen solo absorbe el exceso activo respecto de ese nominal. |
| C — Interrupción/inicio tardío consumen la ocupación pactada | Mantener el extremo pactado T=s+d+m; reconstruir el mismo fin clínico F con servicio activo y pausas, pero L_C=máximo(T, F). La interrupción o demora puede consumir el margen original; no se añade uno nuevo tras la reanudación. | Alternativa válida como política distinta: cambiaría el ejemplo original a 09:50. Requiere ajustar la tabla y el contrato si el usuario la elige; no combinarla con M dentro de una misma cita. |

| Datos compartidos | Bajo M recomendada | Bajo C alternativa |
|---|---|---|
| s=r=09:00, d=D=30/m=10, pausa 09:10–09:30, f=09:50 | N=09:50; L=10:00. | T=09:40; L=09:50. |
| s=09:00, r=09:20, d=D=30/m=10, sin pausa, f=09:50 | N=09:50; L=10:00. | T=09:40; L=09:50. |
| s=r=09:00, d=D=30/m=10, pausas de 10 y 15 min, f=09:55 | N=09:55; L=10:05. | T=09:40; L=09:55. |

Esta propuesta no cambia las decisiones pendientes de A.8 ni el plazo de tolerancia, el tipo pausable o la prioridad de reanudación ya definida.
Hasta resolver la política temporal tras la revisión independiente, no usar estos ejemplos como una regla de producto aprobada para implementar B.
Si se elige C, la modificación se hace explícitamente, conservando los mismos datos y sellos, sin convertirla silenciosamente en M durante una reanudación.

**Campos que sostienen cada resultado, sin SQL nuevo.**

| Magnitud | Fuente existente o propuesta |
|---|---|
| s/d/m y fin pactado | citas.fecha/hora, duracion_minutos, margen_minutos y hora_fin existentes. Permanecen inmutables salvo reprogramación expresa auditada. |
| r/f y estado | citas.hora_inicio_real, hora_fin_real y estado existentes; completar exige no dejar pausa abierta. La llegada usa hora_llegada y no sustituye r. |
| D | Propuesta de A.6: duracion_activa_objetivo_segundos con procedencia de la decisión en auditoría. Servicio completo usa d convertido a segundos; una decisión distinta necesita valor explícito. Una decisión incompleta o un legado desconocido no se rellena con una duración inventada. |
| P_c/P(t), A/R | Propuesta pausas_cita con inicio y reanudación reales de cada pausa. Se derivan desde r y D; un restante guardado al pausar sería instantánea comprobable, no saldo que se vuelve a descontar. |
| u en pausa abierta | Predicción de disponibilidad para reanudar, referida al caso rojo y su corte/versión; si no existe, solo cota condicional al reloj, sin inventar duración de urgencia. Nunca se escribe como reanudación real. |
| N/F/L | N y L se reconstruyen con la política identificada y hechos del caso; F se presenta/persiste como fin_estimado clínico, sin margen. version_agenda/fecha_ultimo_calculo propuestos distinguen los cortes; no convierten una estimación anterior en causa temporal. |
| Elección M/C | Identificar versión/política temporal aplicada cuando se apruebe para poder reconstruir resultados históricos; A.1 no introduce un default aprobado ni rellena retrospectivamente esa elección. |

**Evidencia y trazabilidad.**
RE-4.5.1 se protege conservando r/f y la distinción de horas; RE-4.5.2/4.14.1/4.14.2 y RN-412/415 con N/F/L y sus extremos; RE-4.5.3 con la reconstrucción de cola sin acumulación; RE-4.15.8 y RN-427 con intervalos activos/pausados y precedencia de reanudación.
Se ajustaron solamente el contrato temporal de A.3, las dos filas afectadas de persistencia en A.6 y P09/P14/P17, además de cabecera, este anexo y estado.
Los casos propuestos ahora incluyen inicio tardío con servicio completo, pausa única, varias pausas, sobrepaso antes/después de retomar, recálculo idéntico y reloj posterior a una cita completada.

Se comprobaron en memoria **16 escenarios aritméticos**, sin crear archivos de tests ni ejecutar código de aplicación: todos coincidieron con N/L declarados y repitieron resultado con los mismos hechos/reloj.
Incluyen margen exacto y excedido sin pausa; durante pausa abierta con u=09:30; pausa cerrada de 20; dos pausas que suman 25; sobrepasos de 9/10/11/15 min; margen ya consumido antes de otra pausa; inicio efectivo 09:20/10:10 y avance de reloj tras completar.
La comprobación es aritmética de documentación, no cobertura automatizada nueva de M4.
La comparación de alternativas usa los mismos hechos clínicos; cambia solo la política de margen declarada.
Se verifican también por sustitución la cita sin iniciar del hueco absorbente y el final de franja exacto de A.3.

**Contraste con Modelos §5–§6, sin modificarlos.**
§5 deberá definir la base nominal por estado y distinguir fin clínico de extremo con margen; incluir inicio efectivo, pausa/reanudación, atención aún abierta y recálculo al reloj.
Su cálculo del exceso no debe descontar m dos veces ni reconstruir N desde el último estimado.
§6 deberá enlazar inicio/cierre de cada pausa con reanudación real antes de las siguientes y con el mismo cálculo temporal elegido; mientras la pausa siga abierta, una predicción no libera capacidad.
El ajuste normativo posterior depende de la elección M/C; aquí no se cambian diagramas, HU, RE ni RN.

**Verificación de alcance.**
Las únicas escrituras de A.1 son specs/M4-agenda-grafo-ii.md y agentes/estado.md.
agentes/metodo.md ya aparecía modificado al comenzar; se conserva byte a byte, al igual que A.8 y la revisión anterior.
No se repiten suites por esta corrección documental: se conserva como línea base la verificación del revisor, PHP 854/7813 con MariaDB y JS 238/238.
Sin acceso al archivo de entorno o a bases de datos, código, SQL, tests nuevos, versión, exportación ni operaciones Git de escritura.

**Pendientes:** revisión independiente de A.1 y elección explícita de política temporal por el usuario; los demás pendientes de A.8 y protocolo HC siguen como estaban.
No se declara A.1 aprobada ni H-A1 cerrado por el revisor.

**Comandos de commit para el usuario, compatibles con CMD/PowerShell:**

    git add specs/M4-agenda-grafo-ii.md agentes/estado.md
    git commit -m "docs(agenda): corregir base temporal de cascada (RE-4.5.1, RE-4.14.2, RE-4.15.8)"

### A.1 — Revisión (2026-10-17)

**Revisó Codex arquitecto de esta conversación, independiente del Codex ejecutor de A.1. Veredicto: aprobada como corrección documental.** Se cierra H-A1 en cuanto a la contradicción y ausencia de base temporal: ahora las fórmulas fijan cinco estados y los ejemplos declaran expresamente la política usada. La aprobación técnica de esta corrección no elige M ni C por el usuario, ni autoriza B.

**Verificación propia:** lectura completa del Anexo A.1, contrato A.3, filas afectadas de A.6 y P09/P14/P17; contraste con RN-412/415/427 y Modelos §5–§6. Se confirma la separación entre fin clínico y extremo con margen, la conservación del primer inicio real y la duración pactada, el tiempo activo calculado descontando pausas y el carácter condicional de una reanudación estimada. No hay una predicción que autorice por sí sola a liberar al veterinario ni una selección M/C encubierta como decisión del usuario.

Se reprodujeron **17 escenarios aritméticos independientes, 17 coincidentes y repetibles** con los mismos hechos/reloj: fin antes/en/después del margen; atención abierta a 09:45/09:46; inicio real tardío abierto y completado; pausa abierta con reanudación condicional y pausa cerrada; dos pausas y avance de reloj; reloj posterior a completar; excesos tras reanudar; exceso consumido antes de otra pausa; llegada tardía con servicio completo. En particular, bajo M: inicio 09:00, trabajo 10 minutos, pausa de 20 y otros 20 activos dejan nominal/fin clínico 09:50 y extremo 10:00; dos pausas de 10+15 minutos dejan nominal 09:55 y extremo 10:05. El margen no se multiplica por el número de pausas ni se regenera con cada recálculo.

La comprobación fue aritmética en memoria, sin crear código de aplicación o archivos de pruebas. No acredita cobertura automatizada del futuro motor; los casos siguen propuestos para C5/C7. Se mantiene la línea base propia de la revisión A: PHP 854/7813 con MariaDB y JS 238/238; no se repitieron suites por esta corrección exclusivamente documental, conforme al encargo. No se consultaron bases, credenciales ni pantallas.

**Alcance:** solo plan y estado cambiaron por A.1; la modificación de metodo.md es la preferencia de comandos registrada previamente por el arquitecto. No hay cambios de código, esquema, conocimiento, documentos normativos ni versión. `git diff --check` sin incidencias. No se encontraron nuevos hallazgos que requieran otra corrección del ejecutor en A.1.

**Decisión que sigue pendiente:** elegir si inicio tardío/pausa desplaza el nominal con su margen (M) o consume la ocupación pactada (C). Se recomienda **M**, coherente con la referencia temporal propuesta y con conservar un margen único respecto del servicio desplazado; es una recomendación de producto, no una decisión confirmada. Antes de B también deben resolverse los grupos de A.8 y fijarse el protocolo HC; ninguna de las nuevas tablas ni reglas pendientes se aprueba automáticamente por cerrar H-A1.

**Siguiente:** usuario elige política temporal; registrar únicamente lo confirmado y continuar las decisiones de A.8 antes del prompt de B. El ejecutor no empieza otra etapa por haber terminado A.1. Sin pruebas manuales de navegador porque no cambió interfaz ni funcionalidad.


## Anexo B1 — Resultado

**Fecha:** 2026-10-17.
**Autor:** Codex ejecutor, en esta sesión.
**Revisor previsto:** Codex arquitecto en la conversación independiente.
**Estado:** entregada, pendiente de revisión.
**Alcance:** contratos documentales y diseño de destino; B2 no empezó.
No se aprueba esta entrega por su autor ni se atribuyen al usuario las elecciones técnicas siguientes.
Las decisiones confirmadas de §6 prevalecen sobre pendientes históricos de A/A.1 y sus revisiones, que se conservan.

**Aplicación de decisiones.**
Se alinean creadores de citas y facultades administrativas restantes, cita obligatoria antes de toda nueva atención, espera/alternativas sin sobrecupo no urgente automático, tope naranja por franja original/fecha y ausencia efectiva solo tras aprobación o registro admin.
La revisión veterinaria precede toda hora reservable; derivación roja sigue inmediata.
Se adopta M confirmada: nominal desde inicio real y pausas con un margen único, conservando los ejemplos de A.1.
Rojo remoto no prueba presencia; rojos físicos se ordenan por llegada/ID salvo decisión vet motivada, sin nueva interrupción anidada automática.
Conciliación admin/vet no concede facultades clínicas ni fusión al propietario; contacto opcional y limpieza cuando termine su necesidad.
Marca por clínica, cabecera Zooki con procedencia en historial multi-clínica y reclasificaciones solo propias.
HU-4.10/4.16/4.17 y RE-4.7.3 continúan en v2.1.

### B1.1. Protocolo técnico único para todos los llamadores

**Elección:** serialización transaccional por fila de clínica y recursos globales; retirar GET_LOCK de numeración HC al integrar este contrato.
El GET_LOCK actual es de conexión, no de transacción: rollback/savepoint no lo libera y su adquisición exterior entra en conflicto con servicios que ya tienen filas bloqueadas.
Se sustituye uniformemente, no se permite elegir por cada llamador.
El comentario actual de Consulta sobre choque con las FK es pertinente si se pide el candado clínico después de insertar o adquirir otros candados; el contrato nuevo lo toma primero, antes de esas escrituras y de reservar HC.
No se introduce contador HC ni otra tabla de candados: basta la fila estable clinicas y el índice único existente de número por clínica.

1. Validar sintaxis y permisos/contexto sin revelar datos; identificar conjunto inicial de recursos. Una lectura previa de autorización no sustituye la revalidación transaccional.
2. Iniciar transacción exterior corta. Bloquear por SELECT FOR UPDATE, en orden: clínicas implicadas por ID ascendente; usuarios implicados por ID; mascotas por ID; vínculos usuario_clinica/propietario_clinica/mascota_clinica por clave; configuración/horarios/ausencias/cierres; bloques por ID; citas por ID; solicitudes, reasignaciones, ingresos y pausas por tabla/ID; operaciones y eventos. El orden de tablas del último grupo es el aquí escrito.
3. La clínica se bloquea incluso sin citas/HC/horario; usuarios y mascotas son mutex globales que serializan sus compromisos entre Norte/Sur. Si falta otro recurso/candidato, rollback y recomenzar con el conjunto completo ordenado, sin adquirir un ID anterior tarde.
4. Usar lecturas actuales de bloqueo después de esperar: no consumir una instantánea REPEATABLE READ creada antes del candado. Las consultas previas se descartan para decidir capacidad/cuota/titularidad. Los savepoints no renuevan lecturas ni liberan candados; una operación exterior debe entrar con este protocolo desde el comienzo.
5. Revalidar clínica/roles/vínculos/propietario y actividad, tipo, revisión/huella/versión, franjas/cierres/ausencias, ocupación vet y mascota, bloque/tope/cuota. El triage va antes de cuota; rojo exceptúa también la creación/vínculo de mascota necesario a la atención, sin fingir prioridad enviada por cliente.
6. Reclamar clave idempotente del comando dentro de la misma transacción. Misma clínica+actor+acción+clave y huella devuelve referencias del resultado, revalidando acceso antes de responder; carga distinta con misma clave da conflicto. CSRF sigue independiente; no se entrega un resultado de otra sesión/clínica.
7. Guardar cita/vínculos/HC/consulta/segmentos, marcas, decisiones, auditoría y eventos en una única unidad. Fallo: rollback de todo, incluso idempotencia; adjuntos físicos conservan la compensación vigente que elimina lo movido si falla la BD.
8. Commit antes de SMTP o esperar intervención humana. Un deadlock/timeout aborta la operación completa; el coordinador puede reintentar técnicamente de forma acotada el comando idempotente o responder conflicto. Nunca reintentar solamente la última sentencia sobre datos viejos.

**Numeración HC.**
Con la fila de clínica ya bloqueada, releer mascota_clinica actual de la ficha.
Si ya tiene HC, conservarlo.
Si es primera consulta y sigue sin HC, leer el último HC con formato vigente por clínica mediante lectura actual ordenada de bloqueo, incluso cuando no hay ninguno; el candado de la clínica protege ese vacío.
Asignar el siguiente correlativo en el mismo commit de consulta y mantener UNIQUE(id_clinica, numero_historia_clinica) como defensa.
No reservar un HC al abrir formulario, registrar espera o conciliar una ficha que nunca tuvo consulta.
En conciliación no se recalculan números existentes: conservar alias/procedencia como indica B1.3.
El cambio debe abarcar Consulta::registrar/asignarNumeroHistoria/tomarCandadoNumeracion/soltarCandadoNumeracion, creación de cita de llegada con consulta, y toda modificación de mascota_clinica que afecte numeración.
Los llamadores no pueden entrar con una transacción que ya tomó otros recursos y después pedir clínica/HC.
SQLite comprueba atomicidad funcional; la exclusión efectiva se probará con dos conexiones MySQL/MariaDB.

**Disponibilidad y mutaciones.**
Una capa de modelos calcula la misma intersección/resta e intervalos [inicio,fin) para sugerir/reservar/reprogramar/arrastrar/cubrir/aceptar.
Duración+margen completos; inicio y fin exactos se comparan sin truncar segundos.
Vet y mascota globales se consultan internamente con proyección de intervalos/estado, jamás pacientes, clínica ajena, motivo o contactos.
El choque recurrente del vet entre clínicas se comprueba igual con respuesta genérica.
Ausencias/cierres se modifican solo en la clínica a la que pertenecen; registrar una ausencia no autoriza modificar jornadas de otras clínicas.
Una solicitud pendiente no bloquea; efectiva bloquea y marca citas afectadas en el mismo commit, sin veto para cobertura.
Reprogramar conserva la reserva original hasta éxito; valida mes destino contra cuota sin contar dos veces esa cita, estado abierto y snapshot de duración/margen, excluyendo solo su propio ID.
Cancelación/no_asistio liberan ocupación y cupo naranja; completada conserva consumo del bloque, aunque ya no sea atención futura.
El bloque de fecha ya usado no se reemplaza por otro para reiniciar su tope al editar horario ni por los fragmentos que deja una ausencia.
Las marcas de C2 quedan visibles y recuperables para C5, no como un reajuste ya resuelto.

**Emparejamiento determinista.**
Respetar selección de veterinario del actor cuando siga siendo compatible.
En búsqueda automática, ordenar por primer inicio compatible y luego id_usuario ascendente; ignorar candidatos ya intentados en ese ciclo.
El último criterio solo desambigua equivalentes de modo reproducible, no evalúa calidad, afinidad, reputación, carga clínica ni especialidades.
Una propuesta en vivo no aparta hueco; acepta solo destino antes del límite y tras revalidación bajo los mismos mutex.
Exactamente en fecha_limite ya está vencida; aceptar/vencer/reprogramar concurrentemente deja una sola transición.
Cobertura de ausencia se asigna sin esa aceptación; ambos casos se auditan y notifican.

**Eventos, fallos y ventana de aviso.**
Productor durable en el commit clínico; despachador reclama evento+destinatario de forma condicional, libera transacción y luego envía.
Revalida vínculo/contacto/actividad al despachar; cuenta pendiente o vínculo inactivo no recibe automático.
Se reutilizan notificaciones para registro de envío y notificaciones_internas para avisos; eventos_agenda no pretende entregar bitácora general M8 ni confirmación desde recordatorio v2.1.
Una misma clave semántica/versión/destino/canal no genera otro evento; reclamarlo no equivale a entregarlo.
Fallo de SMTP se registra y no borra reserva; si el proceso cae tras posible envío y antes de confirmar resultado, estado incierto y resolución operativa, sin reenvío automático que prometa exactamente una entrega SMTP.
No se introduce un nuevo plazo clínico ni número de intentos de producto.
RN-429 se aplica a eventos de estimación: comparar contra última hora comunicada, umbral estricto mayor y al menos 30 minutos desde el último aviso, serializados por cita; máximo dos eventos de esa clase en una hora.
No se reserva otra ventana por recálculo ni por un fallo; reasignación/reprogramación siempre producen su evento propio.
La base de comunicación cambia cuando el evento se registra como entregado; un evento pendiente se coalesce por cita/versión sin crear otro envío concurrente.
La idempotencia lógica no es una garantía de entrega física exactamente una vez del proveedor SMTP.

**Integración mínima obligatoria.**
Antes de habilitar horarios C2, el coordinador de mutex y marcas debe existir en todos sus escritores; C3 completa disponibilidad, no cambia el orden.
Al integrar C4a se incluye revisión veterinaria y productor/despachador de correo básico; no anunciar cierre de cuatro ramas antes de C4b/C7a.
Una nueva pausa exige tipo pausable, rojo físicamente presente y ninguna otra pausa abierta del mismo veterinario; ante otro rojo, la decisión clínica no crea automáticamente un segundo nivel de interrupción.
C7a incluye continuidad/cierre de Consulta, VigilanteAtenciones, vínculo que no puede desactivarse con cita pausada y el protocolo HC común.
C7b incorpora conciliación en esa misma unidad; C8b reutiliza la creación de llegada sin permitir consulta nueva id_cita NULL.
LimitePlan, HorarioClinica::guardar/restaurar, Cita, Consulta, Mascota::vincular, VinculoClinica::desactivar y sus controladores/tareas deben delegar o respetar el mismo protocolo; no mezclar las transacciones propias de horario con una exterior incompatible.

### B1.2. Permisos de destino e identidad existente

Son contratos de acciones, no rutas ejecutables añadidas.
Los nombres finales se registrarán juntos en public/index.php y Security cuando se implemente cada etapa.

| Acción | Contexto autorizado y alcance |
|---|---|
| Crear reserva o cita de llegada/iniciar/cerrar atención | Solo veterinario dentro de su alcance; propietario crea su reserva en clínica vinculada, sin facultad de inicio clínico. Admin/plataforma no crean citas ni registran/modifican consultas. |
| Supervisar, cancelar, confirmar y reprogramar/reasignar administrativamente | Se conservan los permisos administrativos actuales de la clínica; vet gestiona las suyas; propietario solo las propias por rutas de portal. No se interpreta prohibición de crear como retirada de estas funciones. |
| Registrar recepción/llegada | Personal autorizado de la clínica, admin/vet: solo acredita recepción de un caso legítimo; no asigna color clínico, inicia consulta ni crea cita desde contexto admin. |
| Calcular triage/buscar síntomas | Vet y propietario, como M2; admin/plataforma siguen denegados. Resolución/reclasificación clínica solo vet autorizado y con motivo al ajustar. |
| Proponer horario/solicitar ausencia | Vet sobre sí y clínica activa. Admin guarda/aprueba/rechaza configuración o ausencia; no obtiene permisos clínicos por ello. |
| Reordenar rojos/naranjas, decidir otro rojo frente a pausada | Solo vet autorizado con motivo y auditoría; sin interrupción anidada automática ni efecto presencial del rojo remoto. |
| Aceptar reasignación en vivo | Solo vet destino, propuesta de su clínica y vigente; la cobertura de ausencia no tiene veto. |
| Consultar/completar/consolidar provisional | Admin o vet autorizado de la clínica del ingreso, con titularidad acreditada; propietario aporta información/verificación de lo suyo pero no fusiona historias. Otras clínicas/plataforma reciben 403 auditado. |
| Eliminar contacto al finalizar necesidad/justificar conservación | Admin o vet autorizado de la clínica del ingreso; comprobación se dispara también al cierre de atención/conciliación, con actor/sistema auditado sin valores de contacto. |

Reutilizar id_usuario de sesión y mascotas.id_propietario, propietario_clinica y mascota_clinica legítimos vigentes.
Portal autenticado con mascota propia ya satisface identidad sin nueva verificación por el color rojo; se revalida titularidad actual al comando.
En persona, una cuenta/ficha y relación previamente verificadas se reutilizan; si se requiere vinculación/activación, se usa el flujo vigente de confirmación del titular de M5, sin nuevo documento obligatorio, CAPTCHA clínico, token, plazo o prueba inventada.
No confundir búsqueda exacta por documento/correo con prueba de titularidad: el ID o el teléfono del acompañante tampoco la acreditan.
El operador autorizado deja referencia de la cuenta/vínculo o confirmación existente usada; sin evidencia suficiente conserva la provisional y no consolida, sin retrasar atención roja.
Una cuenta nueva solo se crea por el procedimiento de consentimiento/activación vigente, nunca automáticamente por el ingreso.

### B1.3. Diseño mínimo de datos, relaciones y transición

**Destino propuesto:** 58 tablas de negocio, 52 ya presentes más seis nuevas, sin tabla de grafo ni de candados.
Las siete de A no se aprueban en bloque: evaluaciones_agenda y urgencias_agenda se sustituyen por solicitudes_agenda, con revisión/derivación/espera en el mismo caso y sin confundir presencia.
No se crea tabla de solicitudes de ausencia: se amplía ausencias_veterinario.
No se crea tabla de restante activo ni de estimaciones por tick: se derivan desde hechos/snapshots.
La bitácora de envío y los destinatarios internos reutilizan tablas vigentes; solo falta el trabajo durable de eventos.
Idempotencia y envío tienen distinta vida/transacción: no se fusionan operaciones_agenda con eventos_agenda.
Los campos, tipos e índices enumerados aquí son exactamente los reflejados en MER/drawDB, no cambios instalados en alpha.4.


#### cierres_clinica — tabla nueva

**Necesidad y RE:** Cierre puntual no representable por recurrencia ni ausencia personal. RE-4.8.2/3.
**Clínica:** id_clinica propio, existente o añadido; todas las acciones revalidan el contexto; referencias globales no autorizan lectura ajena.

| Campo propuesto | Tipo de destino | Necesidad dentro de los RE indicados |
|---|---|---|
| id_cierre | INT NOT NULL PRIMARY KEY AUTO_INCREMENT | Identifica el cierre sin depender de su fecha. |
| id_clinica | INT NOT NULL | Pertenencia y mutex de clínica; no se acepta del cliente sin contexto. |
| inicio | DATETIME NOT NULL | Extremo real del intervalo semiabierto. |
| fin | DATETIME NOT NULL | Extremo exclusivo del cierre puntual; es obligatorio y posterior al inicio. |
| motivo | VARCHAR(255) NOT NULL | Justifica cambio/interrupción; no sustituye evidencia de identidad. |
| activo | TINYINT(1) NOT NULL DEFAULT 1 | Baja lógica sin perder referencia. |
| id_autor | INT NOT NULL | Atribución al usuario autorizado. |
| fecha_registro | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP | Orden y procedencia del hecho. |

**Índices y necesidad:**

- `KEY idx_cierre_rango (id_clinica, activo, inicio, fin)`: lectura acotada del predicado del flujo por los campos ordenados; no sustituye autorización.

**Relaciones:** id_clinica → clinicas(id_clinica); id_autor → usuarios(id_usuario).
**Estados, restricciones y transición:** fin>inicio; [inicio,fin); baja lógica, sin borrar cierres usados. Transición: vacía, no inventar festivos.

#### bloques_agenda — tabla nueva

**Necesidad y RE:** Identidad estable de franja original/fecha que no se multiplica al restar ausencias. RE-4.13.7.
**Clínica:** id_clinica propio, existente o añadido; todas las acciones revalidan el contexto; referencias globales no autorizan lectura ajena.

| Campo propuesto | Tipo de destino | Necesidad dentro de los RE indicados |
|---|---|---|
| id_bloque | INT NOT NULL PRIMARY KEY AUTO_INCREMENT | Identidad estable del bloque para consumo naranja. |
| id_clinica | INT NOT NULL | Pertenencia y mutex de clínica; no se acepta del cliente sin contexto. |
| id_veterinario | INT NOT NULL | Recurso global y miembro de la clínica. |
| fecha | DATE NOT NULL | Sello persistido del hecho. |
| franja_origen | CHAR(64) NOT NULL | Identidad opaca de la franja original. |
| inicio_original | DATETIME NOT NULL | Inicio original anterior a recortes. |
| fin_original | DATETIME NOT NULL | Fin original anterior a recortes. |
| estado | ENUM('vigente','revisar','retirado') NOT NULL DEFAULT 'vigente' | Máquina de estados indicada en el tipo, sin inferir cumplimiento. |

**Índices y necesidad:**

- `UNIQUE KEY uq_bloque_origen (id_clinica, id_veterinario, fecha, franja_origen)`: defensa de unicidad/relación de clínica descrita; no sustituye el mutex ni el intervalo completo.
- `UNIQUE KEY uq_bloque_clinica (id_bloque, id_clinica)`: defensa de unicidad/relación de clínica descrita; no sustituye el mutex ni el intervalo completo.
- `KEY idx_bloque_vet_fecha (id_veterinario, fecha)`: lectura acotada del predicado del flujo por los campos ordenados; no sustituye autorización.

**Relaciones:** id_clinica → clinicas(id_clinica); id_veterinario → usuarios(id_usuario); id_veterinario,id_clinica → usuario_clinica(id_usuario,id_clinica).
**Estados, restricciones y transición:** Identificador opaco servidor de franja original; no cambia por recortes de ausencia ni se borra/recrea para regalar cupo. Cambios recurrentes preservan bloque ya usado de esa fecha, marcan revisar y no reinician contador. Contar citas naranja salvo cancelada/no_asistio; no persistir contador duplicado ni tope histórico distinto al parámetro vigente. Transición: vacía; sobrecupos legados sin origen demostrable se marcan pendientes.

#### solicitudes_agenda — tabla nueva

**Necesidad y RE:** Una sola entidad para captura/revisión, derivación remota y espera física sin cita ni veterinario; evita tablas separadas de evaluación y urgencia. RE-2.2.5, RE-2.11.5, RE-4.15.1/2/3.
**Clínica:** id_clinica propio, existente o añadido; todas las acciones revalidan el contexto; referencias globales no autorizan lectura ajena.

| Campo propuesto | Tipo de destino | Necesidad dentro de los RE indicados |
|---|---|---|
| id_solicitud | INT NOT NULL PRIMARY KEY AUTO_INCREMENT | Caso durable sin asignar un veterinario ficticio. |
| id_clinica | INT NOT NULL | Pertenencia y mutex de clínica; no se acepta del cliente sin contexto. |
| id_mascota | INT NOT NULL | Identidad global conocida o provisional autorizada. |
| id_actor | INT NOT NULL | Procedencia de captura, no titularidad implícita. |
| origen | ENUM('portal','llegada') NOT NULL | Distingue reserva/llegada/legado sin reescribir historia. |
| fecha_registro | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP | Orden y procedencia del hecho. |
| fecha_llegada | DATETIME DEFAULT NULL | Sello de presencia física; remoto conserva NULL. |
| id_receptor | INT DEFAULT NULL | Responsable de acreditar presencia. |
| estado | ENUM('abierta','derivada','espera_presencial','asignada','en_atencion','finalizada','pendiente_reprogramacion','retirada') NOT NULL DEFAULT 'abierta' | Máquina de estados indicada en el tipo, sin inferir cumplimiento. |
| estado_revision | ENUM('no_requerida','pendiente','resuelta') NOT NULL | Separa puerta clínica de la situación operativa. |
| nivel_calculado | ENUM('rojo','naranja','amarillo','verde') DEFAULT NULL | Salida original del motor; NULL si no existió cálculo. |
| nivel_aprobado | ENUM('rojo','naranja','amarillo','verde') DEFAULT NULL | Resultado clínico utilizable tras resolver revisión. |
| contexto_triage | JSON NOT NULL | Marcas y tiempos necesarios para reproducir evaluación. |
| version_conocimiento | CHAR(64) DEFAULT NULL | Versión real del motor usada, no inventada para declaración clínica. |
| huella_entrada | CHAR(64) NOT NULL | Identifica contenido confirmado y evita reutilización divergente. |
| huella_revision | CHAR(64) DEFAULT NULL | Liga la aprobación a la entrada vigente. |
| id_revisor | INT DEFAULT NULL | Usuario que resolvió revisión/aprobación. |
| fecha_revision | DATETIME DEFAULT NULL | Sello de resolución. |
| motivo_revision | VARCHAR(255) DEFAULT NULL | Justifica revisión/ajuste clínico. |
| contagio | TINYINT(1) NOT NULL DEFAULT 0 | Conserva advertencia sin cambiar automáticamente prioridad. |
| orden_espera | BIGINT DEFAULT NULL | Orden explícito motivado, no criterio de prioridad nuevo. |
| id_cita | INT DEFAULT NULL | Vínculo a cita una vez asignada, con la misma clínica. |
| id_ingreso | INT DEFAULT NULL | Proveniencia de identidad provisional en la misma clínica. |
| version_solicitud | INT NOT NULL DEFAULT 0 | Invalida decisiones sobre una versión anterior. |
| fuente_nivel | ENUM('grafo','clinico_presencial') DEFAULT NULL | Distingue cálculo de declaración clínica presencial. |

**Índices y necesidad:**

- `UNIQUE KEY uq_solicitud_clinica (id_solicitud, id_clinica)`: defensa de unicidad/relación de clínica descrita; no sustituye el mutex ni el intervalo completo.
- `UNIQUE KEY uq_solicitud_cita (id_cita)`: defensa de unicidad/relación de clínica descrita; no sustituye el mutex ni el intervalo completo.
- `KEY idx_solicitud_espera (id_clinica, estado, fecha_llegada, id_solicitud)`: lectura acotada del predicado del flujo por los campos ordenados; no sustituye autorización.
- `KEY idx_solicitud_revision (id_clinica, estado_revision, fecha_registro)`: lectura acotada del predicado del flujo por los campos ordenados; no sustituye autorización.
- `KEY idx_solicitud_mascota (id_mascota, estado)`: lectura acotada del predicado del flujo por los campos ordenados; no sustituye autorización.

**Relaciones:** id_clinica → clinicas(id_clinica); id_mascota → mascotas(id_mascota); id_actor → usuarios(id_usuario); id_receptor → usuarios(id_usuario); id_revisor → usuarios(id_usuario); id_cita,id_clinica → citas(id_cita,id_clinica); id_ingreso,id_clinica → ingresos_emergencia(id_ingreso,id_clinica).
**Estados, restricciones y transición:** Remoto no lleva llegada/receptor ni activa atención; presencia se acredita por personal autorizado. Revisión resuelta requiere revisor/fecha/huella vigente; ajuste requiere motivo. Sin asignación id_cita=NULL y no hay vet ficticio; al asignar se crea cita en la misma transacción. Ficha provisional solo para rojo presencial sin identidad. JSON guarda episodios/tiempos y contexto permitido, nunca medicación protegida ni contacto del acompañante. orden_espera es orden explícito motivado para excepciones clínicas rojas, no puntaje; la espera no urgente se deriva de nivel aprobado (amarillo antes de verde), fecha_llegada e id_solicitud ascendentes, sin sobrecupo. Transición: vacía, sin inventar evaluaciones/presencia. Rojo presencial clínicamente declarado conserva fuente clinico_presencial/nivel_aprobado rojo/revisor y motivo, sin fingir cálculo ni versión de grafo; un registro aún sin triage no es reservable. Flujo ordinario exige cálculo del motor, huella y versión válidos.

#### pausas_cita — tabla nueva

**Necesidad y RE:** Reconstruir múltiples interrupciones y tiempo activo de política M. RE-4.15.8, RE-4.5.1/2/3.
**Clínica:** id_clinica propio, existente o añadido; todas las acciones revalidan el contexto; referencias globales no autorizan lectura ajena.

| Campo propuesto | Tipo de destino | Necesidad dentro de los RE indicados |
|---|---|---|
| id_pausa | INT NOT NULL PRIMARY KEY AUTO_INCREMENT | Identifica cada segmento de una o varias pausas. |
| id_clinica | INT NOT NULL | Pertenencia y mutex de clínica; no se acepta del cliente sin contexto. |
| id_cita | INT NOT NULL | Vínculo a cita una vez asignada, con la misma clínica. |
| id_solicitud_roja | INT NOT NULL | Causa roja presencial comprobable. |
| inicio | DATETIME NOT NULL | Extremo real del intervalo semiabierto. |
| fin | DATETIME DEFAULT NULL | Cierre del intervalo; NULL solo significa pausa abierta. |
| id_autor | INT NOT NULL | Atribución al usuario autorizado. |
| motivo | VARCHAR(255) NOT NULL | Justifica cambio/interrupción; no sustituye evidencia de identidad. |
| cita_abierta | INT DEFAULT NULL | Clave auxiliar de unicidad de pausa abierta. |

**Índices y necesidad:**

- `UNIQUE KEY uq_pausa_abierta (cita_abierta)`: defensa de unicidad/relación de clínica descrita; no sustituye el mutex ni el intervalo completo.
- `KEY idx_pausa_cita (id_clinica, id_cita, inicio)`: lectura acotada del predicado del flujo por los campos ordenados; no sustituye autorización.

**Relaciones:** id_clinica → clinicas(id_clinica); id_cita,id_clinica → citas(id_cita,id_clinica); id_solicitud_roja,id_clinica → solicitudes_agenda(id_solicitud,id_clinica); id_autor → usuarios(id_usuario).
**Estados, restricciones y transición:** cita_abierta es calculada en B2: id_cita solo si fin IS NULL; drawDB la representa convencional por compatibilidad. fin>inicio, sin solapes, una pausa abierta, tipo pausable y rojo presente en esa clínica; cerrar antes de completar. R/N/L se derivan, sin saldo mutable ni margen por pausa. Transición: vacía; una pausada legada sin segmentos queda para revisión, sin fabricar horas.

#### operaciones_agenda — tabla nueva

**Necesidad y RE:** Idempotencia atómica de comandos; no puede sustituirse por avisos con otra vida útil. RE-4.9.3, RE-4.15.3.
**Clínica:** id_clinica propio, existente o añadido; todas las acciones revalidan el contexto; referencias globales no autorizan lectura ajena.

| Campo propuesto | Tipo de destino | Necesidad dentro de los RE indicados |
|---|---|---|
| id_operacion | INT NOT NULL PRIMARY KEY AUTO_INCREMENT | Identifica resultado idempotente. |
| id_clinica | INT NOT NULL | Pertenencia y mutex de clínica; no se acepta del cliente sin contexto. |
| id_actor | INT DEFAULT NULL | Procedencia de captura, no titularidad implícita. |
| actor_clave | VARCHAR(80) NOT NULL | Desambiguación servidor de usuario/tarea. |
| accion | VARCHAR(80) NOT NULL | Ámbito del comando idempotente. |
| clave_operacion | CHAR(64) NOT NULL | Clave opaca de reintento independiente de CSRF. |
| huella_entrada | CHAR(64) NOT NULL | Identifica contenido confirmado y evita reutilización divergente. |
| resultado | JSON NOT NULL | Referencias y código de resultado; no copia datos personales. |
| fecha | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP | Sello persistido del hecho. |

**Índices y necesidad:**

- `UNIQUE KEY uq_operacion_clave (id_clinica, actor_clave, accion, clave_operacion)`: defensa de unicidad/relación de clínica descrita; no sustituye el mutex ni el intervalo completo.

**Relaciones:** id_clinica → clinicas(id_clinica); id_actor → usuarios(id_usuario).
**Estados, restricciones y transición:** actor_clave se deriva en servidor (usuario o tarea), nunca del cliente; resultado solo referencias internas/código, no datos personales. Cita/solicitud/ingreso del resultado se validan en esa clínica; relación lógica por resultado, sin FK polimórfica falsa. Misma clave/huella devuelve mismo resultado; otra huella conflicto. Comando fallido revierte su fila. Transición: vacía, claves no derivadas de CSRF.

#### eventos_agenda — tabla nueva

**Necesidad y RE:** Evento durable tras commit, separación de SMTP e idempotencia de avisos, no nueva bitácora general M8. RE-4.4.1/3/4, RE-4.14.7, RE-4.12.5, RE-2.11.6.
**Clínica:** id_clinica propio, existente o añadido; todas las acciones revalidan el contexto; referencias globales no autorizan lectura ajena.

| Campo propuesto | Tipo de destino | Necesidad dentro de los RE indicados |
|---|---|---|
| id_evento | INT NOT NULL PRIMARY KEY AUTO_INCREMENT | Unidad durable de despacho. |
| id_clinica | INT NOT NULL | Pertenencia y mutex de clínica; no se acepta del cliente sin contexto. |
| id_cita | INT DEFAULT NULL | Vínculo a cita una vez asignada, con la misma clínica. |
| id_solicitud | INT DEFAULT NULL | Caso durable sin asignar un veterinario ficticio. |
| tipo | VARCHAR(80) NOT NULL | Clase semántica del aviso. |
| clave_evento | CHAR(64) NOT NULL | Misma causa/version no duplica evento. |
| destino_clave | VARCHAR(80) NOT NULL | Identidad estable servidor del destinatario. |
| id_destinatario | INT DEFAULT NULL | Usuario legítimo concreto. |
| id_rol_destino | INT DEFAULT NULL | Destino interno por rol de la clínica. |
| canal | ENUM('interno','correo') NOT NULL | Distingue correo de entrega interna. |
| version_recurso | INT NOT NULL | Evita avisar como vigente una versión superada. |
| hora_a_comunicar | DATETIME DEFAULT NULL | Estimación propuesta, sin contacto ni HTML. |
| estado | ENUM('pendiente','reclamado','enviado','error','incierto','omitido') NOT NULL DEFAULT 'pendiente' | Máquina de estados indicada en el tipo, sin inferir cumplimiento. |
| reclamacion | CHAR(64) DEFAULT NULL | Token del único despachador reclamante. |
| fecha_reclamacion | DATETIME DEFAULT NULL | Diagnóstico de interrupción del envío. |
| fecha_resultado | DATETIME DEFAULT NULL | Sello del resultado de despacho. |
| intentos | INT NOT NULL DEFAULT 0 | Trazabilidad técnica sin inventar política de reintentos. |
| error_tecnico | VARCHAR(255) DEFAULT NULL | Diagnóstico saneado sin credenciales ni contacto. |
| fecha | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP | Sello persistido del hecho. |

**Índices y necesidad:**

- `UNIQUE KEY uq_evento_destino (id_clinica, clave_evento, destino_clave, canal)`: defensa de unicidad/relación de clínica descrita; no sustituye el mutex ni el intervalo completo.
- `KEY idx_evento_pendiente (estado, fecha, id_evento)`: lectura acotada del predicado del flujo por los campos ordenados; no sustituye autorización.
- `KEY idx_evento_cita (id_clinica, id_cita, tipo)`: lectura acotada del predicado del flujo por los campos ordenados; no sustituye autorización.

**Relaciones:** id_clinica → clinicas(id_clinica); id_cita,id_clinica → citas(id_cita,id_clinica); id_solicitud,id_clinica → solicitudes_agenda(id_solicitud,id_clinica); id_destinatario → usuarios(id_usuario); id_rol_destino → roles(id_rol).
**Estados, restricciones y transición:** Una referencia al menos; destinatario usuario o rol legítimo, destino_clave generada en servidor. No guardar HTML/contacto provisional; reconstruir marca y contacto permitido al despachar. Clave semántica y versión evitan evento doble. Reclamar fuera de SMTP; incierto no reenvía automáticamente hasta resolución operativa. notificaciones registra resultado de correo, notificaciones_internas sigue entregando internos. Transición: vacía, no fabricar envíos previos.

#### citas — ampliación de tabla existente

**Necesidad y RE:** Completar el flujo conservando identidad y datos existentes. RE-2.2.1/4, RE-4.5.1–3, RE-4.13.7, RE-4.14.7.
**Clínica:** id_clinica propio, existente o añadido; todas las acciones revalidan el contexto; referencias globales no autorizan lectura ajena.

| Campo propuesto | Tipo de destino | Necesidad dentro de los RE indicados |
|---|---|---|
| origen | ENUM('reserva','llegada_sin_reserva','urgencia','legado') NOT NULL DEFAULT 'legado' | Distingue reserva/llegada/legado sin reescribir historia. |
| id_bloque | INT DEFAULT NULL | Identidad estable del bloque para consumo naranja. |
| inicio_estimado | DATETIME DEFAULT NULL | Inicio operativo separado del pactado y del real. |
| fin_estimado | DATETIME DEFAULT NULL | Fin clínico estimado sin margen. |
| duracion_activa_objetivo_segundos | INT DEFAULT NULL | Objetivo D documentado, no tiempo descontado dos veces. |
| politica_temporal | VARCHAR(16) DEFAULT NULL | Identifica M para reconstrucción histórica. |
| estado_reajuste | ENUM('ninguno','pendiente_calculo','pendiente_revision','pendiente_reasignacion','pendiente_reprogramacion') NOT NULL DEFAULT 'ninguno' | Trabajo pendiente visible sin alterar estado clínico. |
| motivo_reajuste | VARCHAR(255) DEFAULT NULL | Causa del reajuste/diagnóstico legado. |
| version_agenda | INT NOT NULL DEFAULT 0 | Control de vigencia de propuestas y eventos. |
| fecha_ultimo_calculo | DATETIME DEFAULT NULL | Corte del cálculo reproducible. |
| ultima_hora_comunicada | DATETIME DEFAULT NULL | Base del umbral, solo tras entrega. |
| ultimo_aviso_estimacion | DATETIME DEFAULT NULL | Control de ventana RN-429. |

**Índices y necesidad:**

- `UNIQUE KEY uq_cita_clinica (id_cita, id_clinica)`: defensa de unicidad/relación de clínica descrita; no sustituye el mutex ni el intervalo completo.
- `KEY idx_cita_bloque (id_bloque, es_sobrecupo, estado, fecha_registro, id_cita)`: lectura acotada del predicado del flujo por los campos ordenados; no sustituye autorización.
- `KEY idx_cita_reajuste (id_clinica, estado_reajuste, fecha)`: lectura acotada del predicado del flujo por los campos ordenados; no sustituye autorización.
- `KEY idx_cita_vet_estimada (id_veterinario, inicio_estimado)`: lectura acotada del predicado del flujo por los campos ordenados; no sustituye autorización.
- `KEY idx_cita_mascota_estimada (id_mascota, inicio_estimado)`: lectura acotada del predicado del flujo por los campos ordenados; no sustituye autorización.

**Relaciones:** id_bloque,id_clinica → bloques_agenda(id_bloque,id_clinica).
**Estados, restricciones y transición:** Conservar fecha/hora/hora_fin y sellos. D>0 si conocido; F clínico no incluye margen, N/L derivados con M identificada. Sin nuevas prioridades ni estados clínicos. Bloque obligatorio para naranja nuevo y de ese vet/fecha; no mezclar demanda extra con simultaneidad. Valores nuevos por defecto no acreditan historia. Legado sin dato verificable conserva NULL/legado y futura conflictiva queda pendiente; no copiar valores actuales de tipo como antiguos.

#### ausencias_veterinario — ampliación de tabla existente

**Necesidad y RE:** Completar el flujo conservando identidad y datos existentes. RE-4.12.1/2/3.
**Clínica:** id_clinica propio, existente o añadido; todas las acciones revalidan el contexto; referencias globales no autorizan lectura ajena.

| Campo propuesto | Tipo de destino | Necesidad dentro de los RE indicados |
|---|---|---|
| estado | ENUM('solicitada','aprobada','rechazada','anulada') NOT NULL DEFAULT 'aprobada' | Máquina de estados indicada en el tipo, sin inferir cumplimiento. |
| id_solicitante | INT DEFAULT NULL | Procedencia de solicitud; legado desconocido conserva NULL. |
| id_revisor | INT DEFAULT NULL | Usuario que resolvió revisión/aprobación. |
| fecha_revision | DATETIME DEFAULT NULL | Sello de resolución. |
| motivo_resolucion | VARCHAR(255) DEFAULT NULL | Motivo de aprobación/rechazo/anulación. |

**Índices y necesidad:**

- `KEY idx_ausencia_revision (id_clinica, estado, fecha_hora_inicio)`: lectura acotada del predicado del flujo por los campos ordenados; no sustituye autorización.
- `KEY idx_ausencia_vet (id_veterinario, estado, fecha_hora_inicio, fecha_hora_fin)`: lectura acotada del predicado del flujo por los campos ordenados; no sustituye autorización.

**Relaciones:** id_solicitante → usuarios(id_usuario); id_revisor → usuarios(id_usuario).
**Estados, restricciones y transición:** Solo aprobada bloquea. Nuevas solicitudes envían solicitada explícita; admin registra aprobada con autor/revisor. Legacy ya era bloqueo efectivo: aprobada sin inventar autores. Rango exacto parcial/completo, sin eliminar registros.

#### reasignaciones — ampliación de tabla existente

**Necesidad y RE:** Completar el flujo conservando identidad y datos existentes. RE-4.14.4/6.
**Clínica:** se añade id_clinica demostrable desde cita; todas las acciones revalidan el contexto; referencias globales no autorizan lectura ajena.

| Campo propuesto | Tipo de destino | Necesidad dentro de los RE indicados |
|---|---|---|
| id_clinica | INT NOT NULL | Pertenencia y mutex de clínica; no se acepta del cliente sin contexto. |
| inicio_propuesto | DATETIME DEFAULT NULL | Intervalo completo que debe revalidarse. |
| fin_propuesto | DATETIME DEFAULT NULL | Incluye ocupación exigida, no reserva anticipada. |
| version_cita | INT DEFAULT NULL | Versión que puede aceptar el destino. |
| ciclo | INT NOT NULL DEFAULT 0 | Desambigua búsqueda nueva e intentos previos. |
| motivo | VARCHAR(255) DEFAULT NULL | Justifica cambio/interrupción; no sustituye evidencia de identidad. |
| cita_pendiente | INT DEFAULT NULL | Clave auxiliar única para propuesta pendiente. |

**Índices y necesidad:**

- `KEY idx_reasig_clinica_limite (id_clinica, estado, fecha_limite)`: lectura acotada del predicado del flujo por los campos ordenados; no sustituye autorización.
- `KEY idx_reasig_intentos (id_cita, ciclo, id_veterinario_destino)`: lectura acotada del predicado del flujo por los campos ordenados; no sustituye autorización.
- `UNIQUE KEY uq_reasig_pendiente (cita_pendiente)`: defensa de unicidad/relación de clínica descrita; no sustituye el mutex ni el intervalo completo.

**Relaciones:** id_clinica → clinicas(id_clinica); id_cita,id_clinica → citas(id_cita,id_clinica).
**Estados, restricciones y transición:** cita_pendiente calculada en B2=id_cita si estado pendiente, NULL en resto; drawDB convencional comentada. id_clinica demostrable desde cita; nuevas pendientes requieren intervalo y versión, no apartan capacidad. Destino miembro activo; aceptar antes del límite y releer todo. Legacy sin intervalo/versión no puede aceptarse ciegamente; se marca cita para revisión. Duplicados pendientes preexistentes hacen detener actualización con diagnóstico, no borrado automático.

#### ingresos_emergencia — ampliación de tabla existente

**Necesidad y RE:** Completar el flujo conservando identidad y datos existentes. RE-4.15.3/9/10.
**Clínica:** id_clinica propio, existente o añadido; todas las acciones revalidan el contexto; referencias globales no autorizan lectura ajena.

| Campo propuesto | Tipo de destino | Necesidad dentro de los RE indicados |
|---|---|---|
| id_conciliador | INT DEFAULT NULL | Admin/vet autorizado que acreditó titularidad. |
| fecha_conciliacion | DATETIME DEFAULT NULL | Sello de consolidación. |
| evidencia_conciliacion | JSON DEFAULT NULL | Referencias legítimas existentes y mapa de IDs/HC. |
| necesidad_contacto_resuelta_en | DATETIME DEFAULT NULL | Fin de necesidad comprobado, sin plazo nuevo. |
| motivo_conservacion_contacto | VARCHAR(255) DEFAULT NULL | Excepción necesaria y motivada. |
| contacto_anonimizado_en | DATETIME DEFAULT NULL | Evidencia de limpieza sin conservar sus valores. |

**Índices y necesidad:**

- `UNIQUE KEY uq_ingreso_clinica (id_ingreso, id_clinica)`: defensa de unicidad/relación de clínica descrita; no sustituye el mutex ni el intervalo completo.

**Relaciones:** id_conciliador → usuarios(id_usuario).
**Estados, restricciones y transición:** Solo admin/vet autorizado verificando titularidad; evidencia referencias a sesión/vínculos legítimos y mapa de IDs/HC, sin nuevo reto, documento obligatorio ni datos de contacto. Campos contacto existentes opcionales; documento_acompanante queda legado, no captura por defecto. Al cerrar atención y resolver identidad verificar necesidad: contacto a NULL salvo conservación necesaria motivada; registrar fecha/actor por auditoría sin guardar valores, limpiar copias operativas y no borrar ingreso/actos. Legacy sin evidencia no se declara conciliado ni limpiado retrospectivamente.

### B1.4. Conciliación y conservación sin pérdida clínica

La verificación es la vigente de B1.2, no una obligación nueva de documento o de plazo.
Antes de consolidar, bloquear ambas mascotas, sus vínculos y los actos afectados bajo B1.1; confirmar pertenencia del ingreso y autoridad del operador.
Si aparecen recursos no previstos, reiniciar la unidad ordenada; no mover primero media historia.
Completar la misma provisional o trasladar referencias a ficha final: citas, consultas, alertas, vacunaciones y desparasitaciones; síntomas, archivos y tratamientos siguen su padre.
Conservar clínica, veterinario, sellos, autor y filas clínicas; auditoría y escaneos previos conservan origen con mapa explícito de IDs en ingreso.
Desactivar provisional y no reactivar su carnet; no borrar ni cambiar propietario por conocer un ID.
Si la final ya tiene HC, conservarlo y mantener la fila mascota_clinica provisional inactiva como alias/procedencia.
Si la final no tiene HC y la provisional sí, transferir bajo mutex de clínica sin duplicar UNIQUE, guardando el HC/ID de origen en evidencia.
Si ninguna tiene HC, no asignar por conciliar: hacerlo en primera consulta.
Referencias clínicas de la provisional en otra clínica detienen la consolidación, sin apropiarse de actos ajenos.

Cierre clínico y resolución de identidad disparan comprobación de fin de necesidad del contacto.
Mientras una necesidad concreta siga vigente, se conserva solo lo necesario; al terminar, nombre/teléfono y documento legado se ponen a NULL, salvo excepción necesaria con motivo.
Se conserva ingreso y actos; fecha/actor y motivo quedan en auditoría sin los valores eliminados.
Evitar copiar el contacto en eventos, HTML persistido, bitácora o temporales; limpiar las copias operativas que existan.
Respaldos siguen su política vigente restringida; no se promete borrar retroactivamente copias inmutables ni se inventa una retención nueva.
Legado sin evidencia no queda automáticamente conciliado ni marcado como limpiado.

### B1.5. B2 concreta, todavía sin ejecutar

**Migración siguiente libre comprobada:** database/08_agenda_grafo_ii.sql.
Es un nombre previsto, no archivo creado.
B2 modifica database/01_schema.sql y añade 08 con el mismo destino; 02_semilla.sql solo cambiaría si se demuestra un dato inicial necesario, no hay catálogo global nuevo justificado por B1.
Mantener database/modelo/drawdb_schema_v2.sql y MER sincronizados con el diseño revisado.
Pruebas previstas en tests/Integration/BaseV2MysqlTest.php y casos de contrato específicos en tests/Integration; fixtures derivados de alpha.4, nunca base manual del usuario.
No se habilitan rutas, permisos, cálculos ni pantallas en B2.

1. Prevalidación de versión v2, columnas/índices existentes y diagnóstico de referencias huérfanas, propuestas pendientes duplicadas o bloques no demostrables.
Un conflicto de unicidad histórico detiene la actualización con IDs y diagnóstico; no se elimina ni escoge una fila silenciosamente.
2. Añadir tablas vacías y columnas inicialmente compatibles; rellenar solo id_clinica de reasignación desde su cita y estado aprobado de ausencias que ya eran efectivas.
Conservar autor/fecha desconocidos en NULL; origen legado no equivale a llegada.
No reconstruir pausas, presencia, versión de motor ni franja original desde datos que no lo demuestren.
D se copia de snapshot propio positivo de cita solo cuando acredita el objetivo completo y no hay decisión/pausa desconocida; nunca del tipo actual.
Si no se acredita, queda NULL y la futura cita que requiera cálculo se marca pendiente_revision, sin fabricar resultado.
3. Validar rellenos antes de NOT NULL, índices y FK compuestas de clínica.
Generadas cita_abierta/cita_pendiente deben proyectar exactamente la condición declarada, con índice único y NULL múltiple; en drawDB se representan convencionales por compatibilidad.
Comprobar fin>inicio, rangos semiabiertos, D positivo, XOR destinatario usuario/rol, coherencia de revisión/presencia y nuevos estados.
Invariantes entre filas (solapes, clínica/vet/fecha de bloque, triage aprobado, pertenencia de actores) necesitan protocolo transaccional; un CHECK local no prueba esas relaciones.
B2 instala restricciones compatibles verificables; la aplicación de cada comando se implementa en C, sin declarar invariantes de filas legadas inexistentes.
4. Consultar information_schema antes de cada DDL; no modificar migraciones publicadas.
MariaDB/MySQL hacen commit implícito en DDL: la repetibilidad de cada paso recupera una ejecución parcial, no se promete rollback global del DDL.
Conservar datos previos y configuración personalizada, sin recrear usuarios/contraseñas ni resetear la base desplegada.

**Pruebas de aceptación de B2, pendientes:**

| Escenario | Resultado exigido |
|---|---|
| Instalación nueva 01+02 y migraciones | 58 tablas de negocio; columnas, FK y auxiliares calculadas del destino revisado; semilla sin duplicados. |
| Actualización fixture alpha.4 con citas/consulta/HC/ausencias/ingresos y configuración propia | Mismos IDs, horas pactadas/reales, actos y parámetros; 58 tablas, rellenos demostrables; NULL desconocidos conservados. |
| 08 dos veces y reanudación tras paso DDL parcial | Segunda pasada sin error ni cambios adicionales; ningún dato propio sobrescrito. |
| Restricciones y clínica | FK compuesta rechaza cita/solicitud/ingreso de otra clínica; una pausa abierta y propuesta pendiente por cita; rechazo de intervalos inválidos. |
| Legado no reconstruible o duplicado | Diagnóstico verificable, sin inventar cronología o borrar actos; cita pendiente cuando haga falta, nunca habilitada por un default. |
| Compatibilidad MySQL 8 y MariaDB disponible | Estructura y resultados equivalentes, utf8mb4_general_ci/InnoDB; no presentar SQLite como prueba de bloqueo. |

Las carreras de disponibilidad, numeración HC, doble llegada, pausa/cierre, cuota y aceptación/vencimiento se automatizan al integrar C2–C8, con dos conexiones reales y barreras de sincronización.
Deben demostrar relectura actual después de esperar, exclusión Norte/Sur para vet/mascota y HC únicos incluso con clínica inicialmente sin HC.
La cobertura de datos de B2 no acredita que esos escritores futuros ya usan el mutex.

### B1.6. Trazabilidad y evidencia documental

Se mantienen 96 HU y 120 RN.
Se conservan los 427 RE publicados y se añaden consecutivamente RE-2.2.5 y RE-4.15.9/10: **430 RE**.
Se actualizan matrices RN→HU y RN→HU→RE, cabeceras actuales, ERS y Ficha Técnica.
Los conteos históricos de cierres anteriores se conservan como históricos.
Se corrige además un defecto sintáctico preexistente del drawDB: FK fk_arista_destino declarada dos veces y sin coma antes de la declaración en línea; se conserva solo la declaración ALTER existente, sin cambiar conocimiento clínico ni SQL ejecutable.
MER/drawDB tienen 58 tablas de destino; database/01_schema.sql conserva las 52 instaladas.
Las revisiones y la versión no se incrementan; PDF/Word del portal quedan para cierre M4.

| RE/área de la matriz §2 | Evidencia de B1 | Verificación funcional futura |
|---|---|---|
| RE-4.1.1–3; RE-4.9.1–3; RE-4.6.1/2; RE-5.3 y RE-5.6 incluidos | HU/RE/RN-401/415; Modelos §4; B1.1/2 roles, intervalos y locks | P01–P04/P08 y regresiones de reserva/reprogramación C3/C4/D. |
| RE-4.5.1–3; RE-4.14.1/2; RE-4.15.8 | RN-412/427; Modelos §5/6; A.1 íntegro con M confirmada | P09/P14/P17, C5/C7; no afirmar pruebas nuevas de motor. |
| RE-4.13.1–7; RE-2.11.5/6; RE-4.18.4 | Puerta de revisión, procedencia/versiones y tope original en Modelos §4/6; RN-418/419/422 | T-01–T-26 existentes y casos O aplicables; P05–P07/P16/P21, C4/C7. |
| RE-4.11.4; RE-4.12.1–5; RE-4.8.2/3 | Solicitud/aprobación, rangos completos y marcas en Modelos §7/8/13.4 | P02/P04/P12 y C2/C6; carrera de cambio contra reserva. |
| RE-2.2.1/4/5; RE-4.15.1–3/9/10 | Modelos §3/6; solicitud sin vet, presencia, evidencia y contacto; plan excepcional actualizado | P13–P15/P18/P23 y C7/C8b: ninguna consulta nueva sin cita, sin adjudicación indebida y limpieza sin perder actos. |
| RE-4.14.4/6/7; RE-4.4.1–4; RE-4.12.5 | Propuesta no ocupa; vencimiento/aceptación actual; eventos e incertidumbre SMTP B1.1 | P10/P11/P12 y C5/C6, incluido reloj límite y aviso siempre por reasignación. |
| RE-7.4; RE-4.18; RN-G07/G16/G19/110/206/207 | Marca, procedencia, clínica propia, titularidad y privacidad en HU/RE/Modelos/MER | P16/P19/P23 en C1/C4b/C7b/D; aislamiento y datos de contacto no presentes en logs/eventos. |
| Restantes RE de §2 | Trazabilidad de A.7 conservada; no se retira ni declara implementado su criterio | Se mantienen los casos P01–P23 y regresiones T/O para C/D; v2.1 excluida. |

La tabla anterior agrupa rangos por área; el inventario de A.7 sigue detallando cada RE de la matriz original de 107, más los tres añadidos en B1.
P01–P23 son diseño de prueba, no archivos nuevos ni casos ejecutados de M4.
Las suites existentes validan la base actual y no prueban funcionalidades todavía propuestas.

### B1.7. Verificaciones, alcance y pendientes

- PHP sin host: 854 pruebas, 7212 aserciones, 44 omitidas; salida 0.
- PHP con MariaDB 10.4.32, exclusivamente zooki_test_base_v2: 854 pruebas, 7813 aserciones, cero omitidas; salida 0.
- JavaScript existente: 238/238, cero fallos/omisiones; salida 0, incluida prueba automatizada Chromium.
- Mermaid 11.4.1: renderizado de los 27 bloques actuales (26 de Modelos y uno del MER), 27 SVG generados sin error.
No se recorrieron pantallas ni se inventaron pruebas manuales.
- Las ejecuciones válidas de suites/Chrome necesitaron salir del aislamiento local que impedía lectura/socket/arranque; no se cambiaron servicios.
MySQL 8 no está verificado aquí.
- Comprobación documental: 430 RE sin saltos ni IDs duplicados, matrices RN→HU y HU→RN/RE consistentes, 58 entidades iguales en MER/drawDB, campos/índices/FK propuestos cotejados y sin duplicados.
MER es un diagrama resumido para las tablas existentes; el diccionario añadido explicita todos los campos nuevos, sin pretender que el diagrama histórico enumera cada columna legada.
Se reprodujeron 17 escenarios aritméticos M en memoria, coincidentes y repetibles; A/A.1 y sus revisiones se compararon íntegros tras normalizar saltos de línea, sin cambios.
Diff de alcance limitado a los once archivos listados; git diff --check sin incidencias.
Ningún cambio ejecutable, tests nuevos, entorno, conocimiento, versión, historial ni exportaciones.
Las verificaciones de B1 no aprueban B2 ni la entrega por el ejecutor.

**Archivos cambiados:** specs/M4-agenda-grafo-ii.md; specs/HU-4.15-ingreso-emergencia.md; agentes/estado.md; documentacion/HistoriasUsuario.md; documentacion/RequisitosEspecificos.md; documentacion/ReglasNegocio.md; documentacion/Modelos.md; documentacion/MER.md; documentacion/ERS.md; documentacion/FichaTecnica_Zooki.md; database/modelo/drawdb_schema_v2.sql.
El estado incluía el encargo previo del arquitecto y se conserva.

**Pendientes:** revisión independiente de B1 y de las restricciones/rellenos propuestos antes de escribir B2.
**Confirmación posterior del usuario (2026-10-17):** amarillo antes de verde y, dentro de cada color, llegada física/id_solicitud. Se cierra el pendiente de orden de espera y se alinean HU-2.2, RE-2.2.5, RN-208, Modelos y la explicación del MER, sin cambiar el esquema ni habilitar funcionalidad.
Ejemplo de aceptación: verde llegado 09:00 y amarillos llegados 09:05/09:05 con IDs 21/22 se ordenan amarillo 21, amarillo 22, verde; capacidad y revisión clínica se revalidan antes de asignar.
B1 sigue entregada, pendiente de revisión independiente; esta confirmación de producto no aprueba el diseño técnico ni inicia B2.

**Comandos de commit para el usuario, compatibles con CMD/PowerShell:**

    git add specs/M4-agenda-grafo-ii.md specs/HU-4.15-ingreso-emergencia.md agentes/estado.md documentacion/HistoriasUsuario.md documentacion/RequisitosEspecificos.md documentacion/ReglasNegocio.md documentacion/Modelos.md documentacion/MER.md documentacion/ERS.md documentacion/FichaTecnica_Zooki.md database/modelo/drawdb_schema_v2.sql
    git commit -m "docs(agenda): definir contratos y datos de B1 (HU-4.13, RE-4.15.10)"

### B1 — Revisión (2026-10-18)

**Revisó Codex arquitecto de esta conversación, independiente del Codex ejecutor de B1. Veredicto: revisada con corrección requerida B1.1 antes de B2.** La documentación aplica las decisiones de producto, la arquitectura propuesta es coherente en general y el SQL ejecutable no cambia. No se aprueba aún implementar el contrato de conciliación incompleto.

**Verificación propia:**

- PHP completo sin host: **854 pruebas, 7212 aserciones y 44 omitidas**, salida 0.
- PHP completo con MariaDB local **10.4.32**, configuración de pruebas explícita y exclusivamente **zooki_test_base_v2**: **854 pruebas, 7813 aserciones, ninguna omitida**, salida 0. No se verificó MySQL 8 en esta máquina.
- JavaScript completo: **238/238**, cero fallos y cero omitidas, incluida la prueba automática Chromium; salida 0.
- Mermaid **11.4.1**, renderizado independiente con Chrome sin interfaz: **27/27** (26 Modelos y un MER), sin errores. Esto verifica diagramas, no es un recorrido de pantallas de la aplicación.
- Conteo independiente: **96 HU, 430 RE únicos sin saltos y 120 RN**. Se conservan los 427 RE de HEAD y solo se añaden RE-2.2.5, RE-4.15.9 y RE-4.15.10. MER y drawDB describen las mismas **58 entidades**, sin diferencias de nombres; el ejecutable conserva **52**.
- Diff de once archivos documentales/de referencia; sin código de aplicación, cambios de 01_schema.sql/02_semilla.sql, migraciones, tests, conocimiento ni versión. `git diff --check` sin incidencias. Las suites comprueban la base actual, no las futuras restricciones de las seis tablas propuestas.

**Aceptado:** separación entre esquema de destino y desplegado; fusión de evaluación/urgencia en solicitudes_agenda sin vet ficticio; franjas fechadas estables para sobrecupo; segmentos de pausa y política M; eventos durables separados de idempotencia y reconocimiento de incertidumbre SMTP; FK compuestas de clínica y unicidad condicional pendiente de verificar en B2; transición que no inventa hechos clínicos. Se conserva el carácter propuesto del esquema hasta la revisión de su corrección y su comprobación real en B2.

Se contrastaron los flujos de Modelos §3–§8 y §13.4 con §6: solo vet/propietario crean, admin conserva gestión autorizada y conciliación específica, revisión antes del horario, rojo remoto sin presencia, ausencias solicitadas/aprobadas y cobertura sin veto frente a reasignación confirmada. Espera no urgente usa el orden que el ejecutor documenta como confirmado por el usuario; no se introduce puntuación ni sobrecupo verde. Marca, reclasificaciones, privacidad, políticas pendientes de implementación y exclusiones v2.1 quedan expresas.

**Contrato de HC:** es técnicamente admisible sustituir GET_LOCK por bloqueo de clínica antes de cualquier fila/escritura y usar lectura actual para el siguiente HC. La aceptación está condicionada a migrar uniformemente los llamadores al integrarlo, sin mezclar ambos órdenes; la concurrencia de dos primeras consultas y la transacción exterior requieren pruebas reales en la etapa ejecutora. Las suites actuales no prueban ese cambio porque todavía no existe.

**Hallazgo que exige corrección documental:**

| ID / prioridad | Archivo/línea al revisar | RE/RN | Reproducción y efecto | Corrección exigida |
|---|---|---|---|---|
| H-B1-1 / P2 — Falta el destino de la solicitud al consolidar mascota | `specs/M4-agenda-grafo-ii.md:1340`, `documentacion/MER.md:1016`, `specs/HU-4.15-ingreso-emergencia.md` contrato de conciliación; nueva FK en B1.3/solicitudes_agenda | RE-4.15.3, RN-110, RN-206, RN-207; coherencia de identidad del comando y su revisión | La solicitud S apunta a la provisional P, con revisión/huella/contexto e id_cita=C. Se consolida P en la ficha F existente según la lista documentada: C y las consultas pasan a F, P queda inactiva, pero no se define qué ocurre con S.id_mascota. Seguir literalmente esa lista permite S→P y C→F. Tampoco se fija qué validaciones, versiones y eventos pendientes quedan vigentes si F aporta otra especie/raza/alertas/titularidad. Una FK válida y una misma clínica no detectan esa incoherencia. | Definir un único contrato para solicitudes nuevas/activas/finalizadas al completar/consolidar: qué referencia se actualiza o conserva como procedencia, cómo se resuelve la identidad vigente, qué contexto/huella se invalida o conserva históricamente y cómo se revalidan comandos idempotentes/eventos pendientes. Incluir esas referencias y cambios en la misma transacción/orden de bloqueo y en el rollback. No recalcular ni reescribir decisiones clínicas históricas por inferencia. Alinear plan, MER, plan excepcional y el diagrama si hace falta; precisar P15 con solicitud+cita+ficha final y fallo intermedio. |

El hallazgo no implica que se haya perdido información en producción: el modelo nuevo aún no está instalado. Es una brecha del contrato que debe quedar inequívoca antes de escribir B2/C7b. Puede conservarse procedencia en registros cerrados, pero debe indicarse explícitamente y no reutilizar una autorización ligada a la ficha/contexto anterior para comandos nuevos.

**Corrección mínima del revisor:** en la tabla de cierres_clinica de B1.3 se corrigió una frase copiada de pausas («NULL solo significa pausa abierta») para indicar que fin es obligatorio y exclusivo. No se cambió el tipo DATETIME NOT NULL, el esquema ni el comportamiento aprobado.

**Siguiente: B1.1 — cerrar conciliación de solicitudes y referencias operativas**, por el ejecutor, solo documentación/diseño. Conservar B1 y esta revisión; agregar resultado y marcarlo entregado, pendiente de revisión. No empezar B2 ni añadir código, SQL ejecutable o tests nuevos. Si la solución requiere cambios de campos/relaciones de destino, justificarlos y reflejarlos en MER/drawDB; el revisor no diseña ni implementa esa corrección por el autor. No hace falta repetir suites completas si el ajuste sigue siendo exclusivamente documental; sí comprobar coherencia, ejemplos, diagramas afectados y alcance.

**Prueba manual:** no corresponde lista de navegador para B1/B1.1, sin pantallas ni funciones nuevas. Las verificaciones de migraciones y las listas de 3–5 pruebas por usuario se entregarán en sus etapas.


## Anexo B1.1 — Resultado

**Fecha:** 2026-10-18.
**Autor:** Codex ejecutor, en esta sesión.
**Revisor previsto:** Codex arquitecto, en la conversación independiente.
**Estado:** entregada, pendiente de revisión.
**Alcance:** corrección documental de H-B1-1; no se cierra el hallazgo por el autor.
Se conservan B1 y su revisión completos; no confundir este Anexo B1.1 con el apartado «B1.1. Protocolo técnico único» del anexo anterior.

### B1.1.1. Identidad vigente, procedencia y solicitudes

**Elección única:** solicitudes_agenda.id_mascota es siempre la identidad vigente, también para solicitudes finalizadas.
No se deja S→P cuando su cita C pasa a F.
Completar la misma ficha usa destino P y estado de ingreso completado; consolidar sobre existente usa destino F y estado consolidado.
No se cambia el destino de una conciliación ya confirmada por un reintento.
ingresos_emergencia.id_mascota_provisional conserva P y id_mascota_final guarda el destino; id_ingreso, id_solicitud, id_cita y todos los IDs de actos permanecen estables.
En consolidación P queda inactiva/carnet inactivo y se conserva como procedencia, no como paciente operativo.
En completado P es la misma ficha regularizada: ficha_por_completar=0 con sus datos/titularidad legítimos; no se duplica, no se le asigna otro ID ni se activa automáticamente su carnet.
Al consolidar no se sobrescriben titularidad/especie/raza/alertas de F con datos parciales de P; se preservan los actos trasladados y los valores vigentes autorizados de F.
Todos los ingresos afectados de P conservan origen y el mismo destino confirmado; uno ya resuelto hacia otro destino detiene la unidad por incoherencia.

El conjunto afectado incluye todas las solicitudes de P en esa clínica, con o sin cita, y las ya apuntadas al destino que enlacen cualquiera de las citas/ingresos afectados.
Se comprueba la pertenencia y correspondencia de cada enlace; una solicitud de otro paciente enlazada a C es un conflicto de integridad, no se corrige por inferencia.
Se verifican referencias de P en otras clínicas antes de escribir, conforme al límite de B1.4; no se trasladan actos ajenos.
No se mezclan las demás solicitudes de F, de otros episodios, con las de P.
Nueva solicitud sobre una ficha ya consolidada solo se crea con F y permisos/contexto actuales; una petición nueva con P obsoleto recibe conflicto para recargar, sin autorizarla por redirección implícita.

| Objeto | Completar P→P o consolidar P→F |
|---|---|
| Todas las solicitudes afectadas, activas o terminales | Actualizar id_mascota al destino y aumentar version_solicitud una vez por conciliación efectiva, incluso P→P porque se regulariza identidad/contexto. Conservar id_solicitud, id_cita, id_ingreso, origen, actores originales, llegada y fecha de registro. |
| Activas: abierta, derivada, espera_presencial, asignada, en_atencion, pendiente_reprogramacion | Mantener estado operativo; poner estado_revision=pendiente y huella_revision=NULL. El último episodio clínico se conserva como histórico, sin autorización vigente. No cancelar ni iniciar atención por esta marca. |
| Terminales: finalizada y retirada | Mantener estado y episodio clínico, incluidos estado_revision/huella_revision que documentan su decisión histórica. El aumento de versión no renueva esa aprobación: nunca habilita un comando nuevo ni reabre el caso. |
| Citas y actos clínicos afectados | Actualizar id_mascota al destino en la misma unidad; preservar IDs, clínica, autor, prioridad/decisión histórica, síntomas, duración, horas pactadas y sellos. Aumentar version_agenda de cada cita una vez, incluidas completadas, como cambio de referencia, sin recalcular la atención. |
| Citas aún operativas/propuestas de reasignación | Marcar estado_reajuste=pendiente_revision con causa identidad/contexto, sin alterar estado clínico. version_cita anterior deja de aceptar una propuesta; no renovar huella, aceptar ni reasignar por conciliación. La propuesta conserva pendiente y su versión antigua: aceptar falla por contexto desactualizado; solo el rechazo del destino o el vencimiento en su límite original la resuelven y permiten otra. No renovar plazo, fingir respuesta del destino ni crear otra pendiente simultánea. |
| Ingreso, HC, vínculos, auditoría, escaneos y pausas | Preservar el mapa P→destino, HC/procedencia y referencias a IDs estables de B1.4. Pausas siguen apuntando a la misma solicitud roja/cita; no se reescriben segmentos ni auditoría previa. Registrar todas las referencias/versiones antes y después en evidencia_conciliacion y auditoria_sistema. |

**Resolución de identidad para consumidores.**
Lectura, comandos, cobertura, consulta y despacho resuelven S/C por ID estable y clínica con lecturas actuales; exigen S.id_mascota=C.id_mascota cuando haya C y que coincidan con el destino confirmado del ingreso.
Sin conciliación efectiva se usa P; completado resuelve P; consolidado resuelve F.
Si la igualdad/mapa falla, conflicto de integridad y nada de actuar/notificar usando la ficha encontrada primero.
El mapa de ingreso sirve para procedencia y detección de peticiones obsoletas, no concede acceso a F ni a la historia de otra clínica.
La autorización siempre se comprueba sobre paciente, titular, vínculos y contexto activo vigentes.
Para historia se presenta «identidad vigente F; evaluación original de P» con acceso clínico legítimo; no se atribuye el contexto viejo a F ni se expone el contacto del acompañante.

### B1.1.2. Contexto clínico y vigencia para el siguiente comando

contexto_triage conserva el episodio confirmado: marcas/tiempos y atributos del paciente efectivamente usados.
Se precisan dentro del JSON existente los metadatos id_mascota_evaluada y version_solicitud_evaluada, ligados a huella_entrada; no son nuevos síntomas ni una FK operativa.
Una solicitud nueva registra ambos al evaluar; antes de conciliar se completa su procedencia demostrable y se conserva la copia anterior por auditoría.
No se inventan atributos que no se hubieran capturado.
La evidencia del ingreso enumera cada S, C, identidad y versión de antes/después; auditoria_sistema conserva el episodio anterior y su revisión con usuario/fecha, sin contacto provisional ni medicación protegida.
No se añade tabla, columna, índice ni FK: estos metadatos/evidencia usan los JSON/bitácora ya previstos.

Al conciliar se conservan sin recalcular contexto_triage, huella_entrada, version_conocimiento, niveles calculado/aprobado, fuente, contagio y datos del revisor del episodio anterior.
En solicitudes activas id_revisor/fecha_revision/motivo_revision describen esa revisión anterior; estado pendiente y huella_revision NULL impiden presentarlos como autorización actual.
En terminales la revisión se conserva íntegra como historia.
La huella antigua identifica la evaluación antigua, no una confirmación de la nueva ficha.
No sustituir especie/raza/alertas en ese snapshot por los datos de F ni modificar colores históricos por inferencia.

Antes de un comando nuevo se releen destino, titularidad/vínculos, especie/raza/edad/alertas permitidas, marcas/tiempos pertinentes, versión de conocimiento y versiones S/C.
La huella de autorización incluye ese contexto canónico y la identidad/versiones vigentes; un token anterior falla aunque usuario, síntomas o color coincidan.
Comparar los valores actuales relevantes evita depender únicamente de version_solicitud si se modificó un atributo global por una vía legítima.
No se ofrecen/aceptan nuevos horarios ni reasignaciones desde una revisión pendiente.
Solo el veterinario resuelve la revisión que proceda: confirma datos actuales y evalúa explícitamente el episodio operativo necesario, o documenta su decisión clínica presencial por la vía existente.
Una nueva evaluación explícita incrementa version_solicitud, identifica esa nueva versión en version_solicitud_evaluada y emite la huella vigente después de ese incremento; conserva el episodio sustituido en auditoría y no recalcula ni sobrescribe el pasado.
El administrador solo regulariza identidad y marca invalidez mecánica; no calcula triage, resuelve revisión ni registra actos clínicos.
No se exige volver a atender una cita completada; una atención roja en curso no se cancela ni espera la conciliación: continuidad/cierre por veterinario relee F y revalida el comando clínico conforme Modelos §3.
La excepción roja y sus decisiones previas siguen conservadas; no se habilita actualización de síntomas por propietario ni HU-4.17 de v2.1.

### B1.1.3. Eventos y reintentos: referencias estables, contenido vigente

**Eventos aún no despachados.**
Conservar id_evento, clave_evento, id_cita/id_solicitud, clínica, destinatario original, destino_clave y canal.
version_recurso compara version_agenda si hay id_cita; en otro caso version_solicitud; si aparecen ambos recursos se comprueba también su coherencia vigente.
La clave del evento identifica la causa original y su versión causal; version_recurso es el control de vigencia del recurso, no una causa nueva.
Al renovar solo identidad no se recalcula clave_evento ni se inserta otro evento; un reintento del productor reconoce la misma causa original.
La conciliación no copia P/contacto a otro payload ni crea un destinatario o aviso de identidad nuevo.
En pendiente/error, antes de renovar version_recurso se demuestra que la misma causa de aviso sigue siendo aplicable y el destinatario original sigue legítimo para el recurso vigente.
Solo entonces se reconstruye contenido desde F/P vigente y marca de la clínica.
Si el mensaje exigiría reutilizar una revisión clínica vencida, esperar su resolución antes de despachar; no transformar datos históricos en una afirmación clínica nueva.
Si la causa o destinatario dejó de ser válido, marcar omitido con causa saneada.
No redirigirlo automáticamente al nuevo propietario; una comunicación futura solo surge de los comandos ya especificados y a sus destinatarios autorizados.
enviado e incierto conservan la historia del intento, nunca se reenvían por una conciliación.
Las notificaciones internas ya emitidas mantienen procedencia; su consulta/enlace vuelve a autorizar contra la identidad vigente.

**Carrera entre conciliación y despacho.**
El worker identifica recursos sin bloquear primero el evento; entra al mismo orden de clínica/usuarios/mascotas/.../eventos de B1.
Bajo esos candados relee versiones, identidad, permisos/vínculo/contacto y causa antes de reclamar.
La reclamación durable es la barrera técnica del envío: tras commit no se mantienen candados SQL durante SMTP.
Si la conciliación encuentra un evento afectado reclamado o incierto aún sin resolución operativa, devuelve conflicto transitorio sin trasladar nada.
No invalida a ciegas un envío que pudo salir ni permite consolidar y luego transmitir el snapshot anterior.
El worker descarta cualquier contenido preparado antes de la revalidación y no envía si no obtuvo la reclamación vigente.
Tras resultado conocido libera la barrera con estado final; si cae, se aplica incierto y resolución operativa ya prevista, sin nuevo plazo ni reenvío automático.
La resolución debe acreditar que el worker anterior ya no puede continuar su envío antes de habilitar otro despacho o la conciliación.
Así, si gana el conciliador, el worker relee F; si gana la reclamación, la conciliación no cambia identidad a mitad del envío.
La barrera solo difiere regularización de identidad: no bloquea la atención roja ni mantiene una transacción esperando SMTP.
No se promete deshacer un correo ya transmitido ni acceso permanente a un mensaje histórico.

**Idempotencia del comando y respuesta.**
operaciones_agenda conserva clave, huella del comando original y resultado con IDs/referencias, no una respuesta HTML/JSON personal ya renderizada.
Para comparar un reintento se usa la carga original, sin canonicalizar P a F y hacerla pasar por una orden nueva.
Si ya hubo commit, no repetir escrituras, versiones, decisiones ni eventos.
Antes de responder resolver las referencias actuales, comprobar acceso a F/P vigente y reconstruir la vista autorizada; no devolver literalmente el resultado histórico ni el contacto viejo.
Sin permiso actual, denegar sin datos; una respuesta exitosa anterior no concede acceso futuro.
Con permiso actual, informar que la operación ya se realizó y sus referencias vigentes, con versión actual; ese acuse no equivale a ejecutar de nuevo la orden con la revisión vieja.
Misma clave con otra carga da conflicto.
Una clave nueva con huella/revisión/contexto anterior también falla hasta recargar/revalidar; no reaprovecha el éxito de la clave anterior.
El reintento exacto de conciliación misma P/destino ya confirmado devuelve acuse autorizado sin otra conciliación.
Otra clave de otro operador para el mismo destino detecta el ingreso ya resuelto y no incrementa versiones; destino diferente da conflicto.
La respuesta queda autorizada al corte de su lectura transaccional; cualquier comando posterior vuelve a revalidar.

### B1.1.4. Unidad de conciliación, candados y rollback

Se conserva la elección única de B1: clínica primero, usuarios y mascotas globales por ID, vínculos, configuración/horarios/ausencias/cierres, bloques, citas, solicitudes, reasignaciones, ingresos, pausas, operaciones y eventos.
Dentro de cada tabla usar clave ascendente.
Incluir P, F, titulares/operadores y veterinarios afectados, todas las S activas/terminales con/sin C, sus C, ingresos, propuestas, pausas y eventos del conjunto; las demás referencias clínicas se bloquean tras el grupo de pausas y antes de operaciones/eventos, en orden consultas, alertas_medicas, vacunas, desparasitaciones y luego sus hijos por tabla/ID.
La clínica sigue primero para todos los escritores, también si el conjunto está vacío.
Cualquier nuevo recurso no previsto exige rollback y reinicio ordenado, nunca adquirir tarde otro usuario/mascota.
Releer después de esperar, sin snapshot REPEATABLE READ anterior; clínica/mutex de mascotas impiden insertar una S/C afectada fuera del conjunto sin esperar.
Usuarios y mascotas de destino se autorizan y vinculan solo por los mecanismos vigentes, no por copiar titular de P.

1. Revalidar permiso específico admin/vet, titularidad acreditada, pertenencia/estado del ingreso, destino activo legítimo, mapa/HC, correspondencias S/C y barrera de envío.
2. Guardar en evidencia/auditoría la procedencia antes de cambiar las referencias; no reescribir auditoría previa.
3. Trasladar actos/citas y todas las S; aplicar versiones, invalidez de revisiones activas, marcas operativas, tratamiento de eventos y HC.
4. Finalizar ingreso, mapa P→destino y estado de P; verificar todas las correspondencias y registrar resultado idempotente.
5. Commit único; publicar/consumir respuestas y avisos solo después de él.

Una excepción después de mover C pero antes de actualizar S/ingreso revierte C, actos, S, mapa/HC, versiones, revisiones, eventos y fila de operación/auditoría nueva.
La regularización de identidad no emite SMTP ni mueve archivos clínicos; los adjuntos conservan su padre/ID.
No hay estado intermedio confirmado C→F/S→P.
Dos operadores de la misma clínica serializan en su fila: el segundo espera y relee el ingreso ya resuelto, no traslada otra vez ni elige otro F.
Una lectura/petición sin autorización no recibe el destino por consultar P.
La conservación/limpieza del contacto sigue RE-4.15.9/10; no se añade captura, destinatario, plazo ni permiso clínico.

### B1.1.5. Ampliación de P15 — ejemplos propuestos

Se conserva la fila P15 original de A y se amplía aquí su aceptación.
Son escenarios documentales para C7b, no tests implementados ni ejecución de conciliaciones reales.
Datos de referencia: clínica Norte, provisional P=101, ficha final F=202 activa/vinculada con titularidad acreditada, ingreso I=80, cita C=300 y solicitud S=40.
S enlaza C/I; antes S.id_mascota=C.id_mascota=101, version_solicitud=5 y version_agenda=8.
La evaluación conservada e5 tiene id_mascota_evaluada=101, version_solicitud_evaluada=5, huella hP y revisión rP, con atributos desconocidos.
F aporta especie/raza/alertas y propietario ya verificados distintos de los datos parciales de P; no se infieren equivalencias clínicas.

| Caso | Entrada y resultado exigido |
|---|---|
| P15.1 — Completar la misma ficha | I pasa a completado con id_mascota_provisional=id_mascota_final=101; S/C siguen en 101, versiones 6/9; sin duplicar ficha/HC/actos. Si S activa, revisión pendiente y huella_revision NULL, aunque no cambie el ID; e5/hP permanecen como episodio anterior. |
| P15.2 — Consolidar solicitud activa asignada | I consolidado 101→202; S/C pasan a 202 en el mismo commit, IDs 40/300/80 estables, versiones 6/9. S conserva asignada, revisión pendiente/huella NULL; C conserva asignación, prioridad y horas, con reajuste pendiente_revision. P queda inactiva. Ningún consumidor operativo sigue tomando 101 como paciente. |
| P15.3 — Espera activa sin cita | Otra S=41 de P, espera_presencial e id_cita NULL, también pasa a 202 e incrementa su propia versión; conserva llegada/ID y espera. No se crea C ni se pierde prioridad roja por conciliar; para nuevos horarios se resuelve revisión vigente. Las otras solicitudes de F ajenas a P no se alteran. |
| P15.4 — Solicitud finalizada | Con S.finalizada/C.completada se trasladan ambas referencias a 202 y versiones a 6/9, sin reabrir estado ni cambiar rP, hP, color, conocimiento, diagnóstico o sellos. Historia muestra paciente vigente 202 y evaluación original 101/e5. Un comando nuevo no puede usar rP ni operar sobre la finalizada. Igual preservación para retirada. |
| P15.5 — Contexto final distinto | Una orden nueva de reservar/aceptar con v5/hP/rP falla antes de escribir, aunque el actor siga siendo legítimo. Admin no resuelve revisión. Vet relee especie/raza/alertas autorizadas de 202, confirma los datos del episodio operativo y genera evaluación/huella vigente de forma explícita; e5 queda conservado. Nada recalcula el triage o actos ya finalizados; propietario no obtiene HU-4.17. |
| P15.6 — Evento pendiente | E=90, C=300/S=40, version_recurso=8 y destinatario U=9. Tras traslado, refs y destinatario de E permanecen. Si causa/permiso/contacto actual de U siguen válidos y el contenido no depende de revisión vencida, revalidar y actualizar versión a 9, reconstruir desde 202 y despachar una vez con su clave original. Si U ya no está autorizado, omitido; no enviarlo a otro titular ni usar contacto de P. |
| P15.7 — Worker concurrente | Si conciliación confirma primero, el worker toma identidad 202/versiones nuevas y no transmite lo preparado con 101. Si E ya está reclamado/incierto, conciliación responde conflicto sin cambios y espera nueva ejecución tras resolución del envío; no conserva un candado SQL durante SMTP. No reutilizar un token de worker descartado ni reenviar incierto automáticamente. |
| P15.8 — Reintento de operación ya confirmada | La misma clave K/carga original devuelve acuse reconstruido de S=40/C=300 y paciente actual 202 solo si sigue autorizado; versiones permanecen 6/9 y no se duplica E. Si perdió acceso, 403 sin datos. Misma K con destino/carga distinta da conflicto; clave nueva con v5/rP no obtiene autorización. |
| P15.9 — Fallo intermedio | Forzar fallo documentalmente después de trasladar C/consulta a 202 y antes de S/ingreso: tras rollback S/C/consulta siguen 101, versiones 5/8, revisión rP, P activa provisional, I pendiente y evento/HC intactos. No queda operación K confirmada ni auditoría parcial de éxito. Reintentar usa nuevo estado leído, no una confirmación ficticia. |
| P15.10 — Dos conciliadores | Admin A y vet B intentan el mismo I=80. A confirma primero; B espera la clínica y relee consolidado. Mismo destino 202: acuse autorizado sin otra subida de versiones, traslado ni evento. Destino 203: conflicto sin redirigir el mapa. Un operador de Sur recibe denegación sin conocer F. |
| P15.11 — Enlace incoherente o ingreso ajeno | S de otro paciente que enlaza C, mapa contradictorio o referencias de P de otra clínica detienen la unidad; una FK sintácticamente válida no permite aprobar el traslado. S/C no se reparan tomando indistintamente la primera identidad accesible. |
| P15.12 — Cambio de atributos sin otro ID | Completar P o una edición legítima de datos relevantes después de la revisión exige cotejar valores actuales y huella, aunque el ID o la versión S no hayan cambiado por esa otra vía. El comando devuelve conflicto/revisión vigente; decisiones previas permanecen, sin recalcularlas al leer. |

### B1.1.6. Evidencia, alcance y pendientes

**Trazabilidad:** RE-4.15.3; RN-110 (identidad global y titularidad), RN-206 (acumulación/procedencia sin pérdida), RN-207 (actos nuevos sobre ficha activa).
Se precisa solo el criterio de aceptación de RE-4.15.3; sin IDs nuevos ni cambios de referencias/matrices.
Se conservan 96 HU, 430 RE, 120 RN, 58 tablas de destino y 52 ejecutables.
Se cotejan estados existentes, campos JSON/versiones, mapa de identidad y ejemplos P15.1–P15.12 con MER y Modelos §3/§6.
**Verificación propia B1.1 (2026-10-18):** Mermaid 11.4.1 con Chrome sin interfaz, 27/27 bloques renderizados y SVG válidos (26 Modelos y uno MER); solo cambian los diagramas §3/§6, el diagrama MER permanece idéntico.
Cotejo documental de los doce ejemplos P15.1–P15.12, las ocho situaciones operativas de S y los estados existentes de ingreso/revisión/eventos; sin ejecutar comandos clínicos ni simular pruebas de BD.
430 RE únicos y consecutivos con los mismos IDs; solo cambia el criterio de RE-4.15.3, matrices/referencias sin cambios.
Comparación íntegra de anexos/revisiones previos tras normalizar saltos de línea; sin cambios.
Diff limitado a seis archivos y git diff --check sin incidencias.
Anexos A/A.1/B1 y sus revisiones, incluida «B1 — Revisión (2026-10-18)», se preservan íntegros.
La precisión de B1.1 prevalece únicamente en los puntos de conciliación corregidos, sin reabrir §6.

**Persistencia suficiente:** solicitudes usa id_mascota/version_solicitud/estado_revision/huella_revision y metadatos del JSON; citas version_agenda/marca; ingreso mapa/evidencia; auditoría snapshots permitidos; eventos reclamación/versión/estados; operaciones clave/carga/refs.
Ninguna tabla, columna, relación ni índice nuevo; drawDB no cambia.
No se ejecuta aplicación, consulta BD, archivo de entorno, migración, test nuevo, versión, exportación ni recorrido de pantallas.
La línea base **del revisor B1, 2026-10-18**, es PHP 854/7813 con MariaDB, JS 238/238 y Mermaid 27/27; no se presenta como ejecución nueva ni se repiten suites completas.

**Archivos de B1.1:** specs/M4-agenda-grafo-ii.md; agentes/estado.md; documentacion/MER.md; specs/HU-4.15-ingreso-emergencia.md; documentacion/RequisitosEspecificos.md; documentacion/Modelos.md.
El estado ya contenía el encargo del arquitecto al comenzar; se conserva como antecedente.
**Pendientes:** revisión independiente de B1.1 y verificación real de las carreras/rollback en C7b; el ejecutor no aprueba ni declara cerrado H-B1-1.
No aparece una decisión de producto nueva: son reglas técnicas de identidad, vigencia y exclusión del envío sobre las decisiones ya confirmadas.
B2 no se inicia.

**Comandos de commit, uno por línea, compatibles con CMD/PowerShell:**

    git add specs/M4-agenda-grafo-ii.md agentes/estado.md documentacion/MER.md specs/HU-4.15-ingreso-emergencia.md documentacion/RequisitosEspecificos.md documentacion/Modelos.md
    git commit -m "docs(agenda): precisar conciliacion de solicitudes (RE-4.15.3)"

### B1.1 — Revisión (2026-10-18)

**Revisó Codex arquitecto de esta conversación, independiente del Codex ejecutor de B1.1. Veredicto: aprobada; H-B1-1 cerrado.** B1 queda aprobada con la precisión de este anexo. Puede prepararse B2 después del commit del usuario; no se aprueba por ello una migración aún inexistente ni se declara implementada la conciliación.

**Verificación propia:** lectura completa del Anexo B1.1 y diff de sus seis archivos, cotejados con RE-4.15.3, MER, plan excepcional de ingreso y Modelos §3/§6. Se verifican los doce casos P15.1–P15.12 como ejemplos del contrato, sin presentarlos como pruebas de aplicación/BD. No cambian tablas, columnas, índices, relaciones, código, SQL ejecutable, conocimiento, tests ni versión.

Comprobación independiente de conteos: **96 HU, 430 RE únicos sin saltos, 120 RN, 58 entidades iguales en MER/drawDB y 52 tablas ejecutables**. Renderizado propio con Mermaid 11.4.1 y Chrome sin interfaz: **27/27 diagramas sin errores**. El Anexo B1 anterior se comparó con HEAD normalizando saltos de línea y permanece íntegro; su revisión se conserva. `git diff --check` sin incidencias.

No se repitieron las suites PHP/JS conforme al encargo, exclusivamente documental. Se conserva la línea base propia de B1: PHP **854/7813 con MariaDB y cero omitidas**, sin host **854/7212 y 44 omitidas**, JS **238/238**. Esa línea base no es una ejecución nueva ni demuestra las futuras carreras de conciliación. No se leyeron credenciales ni se consultaron bases o pantallas en B1.1.

**Aceptado para implementación posterior:**

- Todas las solicitudes afectadas, activas o terminales, y sus citas resuelven el mismo paciente vigente. Completar P→P y consolidar P→F tienen efectos definidos; los IDs operativos permanecen estables y el ingreso conserva procedencia.
- La evaluación original no se atribuye silenciosamente a F: conserva paciente/versiones evaluados, contexto y decisión histórica. Solicitudes activas invalidan autorización de revisión; terminales no se reabren. Continuidad roja y cierre clínico releen/revalidan los datos actuales sin cancelar atención ni otorgar triage al administrador.
- Contexto y versiones anteriores no autorizan nuevos comandos; los reintentos confirmados reconstruyen un acuse con permisos actuales y no repiten escrituras/eventos. Una petición obsoleta sobre P no obtiene acceso a F por redirección implícita.
- Eventos pendientes se revalidan sobre causa y destinatario originales legítimos; no se redirigen al nuevo propietario. La barrera de reclamación impide confirmar conciliación mientras un envío afectado pueda continuar con el contexto anterior. Su recuperación exige resolver de forma verificable el worker/estado incierto; no se mantiene SQL abierto durante SMTP ni se promete entrega exactamente una vez.
- Conjunto de recursos, orden de bloqueo, dos conciliadores y fallo entre C/S/I tienen resultado y rollback explícitos, incluidas versiones, revisiones, HC, eventos y operación. El diseño usa los campos ya propuestos, sin ampliar el esquema.

No se encuentran nuevos hallazgos bloqueantes en esta corrección documental. La verificación real de exclusión/reclamación, permisos, huellas y rollback corresponde a C7b y a las etapas de eventos/coordinación; no se acepta sustituirla por los ejemplos del anexo. Las restricciones y compatibilidad MySQL/MariaDB del destino deberán comprobarse en B2, sin suponerlas probadas por el render del MER.

**Siguiente:** guardar B1.1 y esta revisión. Después, prompt solo de B2: esquema ejecutable y migración nueva conforme a B1+B1.1 revisadas, con pruebas de instalación nueva, actualización de alpha.4, repetición y conservación de datos. Sin habilitar rutas ni implementar coordinadores/consulta/SMTP en B2. Los contratos de ejecución HC/candados se integrarán uniformemente en las subetapas C correspondientes, conservando las pruebas concurrentes previstas.

**Prueba manual:** no corresponde navegador en B1.1, sin interfaz o comportamiento nuevos. Se entregará la lista por usuario cuando una subetapa funcional lo requiera.
