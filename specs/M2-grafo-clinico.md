# M2 — Historia clínica y soporte a la decisión (Grafo I)

> Estado: B, C1 y C6 cerradas; C2 y C3 revisadas y aprobadas; C4 entregada, pendiente de revisión (Codex, 2026-10-15)
> Entrega: v2.0 · Fecha: 2026-10-14 · Sale como `2.0.0-alpha.4`
> Responsable de escritura: Codex (A, B1, inicio de B2, B2.1, C2, C3, C4) y Claude Code (resto de B2, C1, C5, C6) · Revisión independiente: Claude (arquitecto)
> Rama: `v2/m2-grafo`, creada desde `main` en `v2.0.0-alpha.3`.

## 1. Resultado y límites

**Resultado:** al atender, el veterinario marca los síntomas del paciente y Zooki le sugiere los diagnósticos probables ordenados por peso; al prescribir, bloquea un fármaco tóxico para la especie o raza o al que la mascota es alérgica, y advierte una interacción con su medicación vigente; las alergias y alertas médicas se registran y se ven en toda clínica vinculada y en el portal; existe el motor de triage de 4 niveles, probado con la batería de casos, listo para que la agenda lo use en el módulo 4; y el superadministrador ve el grafo completo como un mapa interactivo de nodos para revisar el conocimiento. Todo es de apoyo: la decisión es siempre del veterinario (RN-209).

**Situación (2.0.0-alpha.3):**
- El esquema ya tiene el Grafo I: `grafo_nodos` (tipo `sintoma`, `diagnostico`, `farmaco`, `especie`, `raza`, `condicion`; `etiqueta`), `grafo_aristas` (`tipo_relacion`, `signo` positivo/negativo, `peso`), `consulta_sintomas`, `cita_sintomas`, `tratamientos.id_nodo_farmaco`, `alertas_medicas` (con `id_nodo`) y las columnas de triage de `citas` (`prioridad_calculada`, `sintomas_texto`, `inicio_sintomas`).
- **Ninguna fila de conocimiento** en la semilla y **ningún código** que lea o escriba esas tablas. `alertas_medicas` tampoco se usa (hueco 3 del Anexo A de M5).
- La consulta del veterinario funciona (HU-2.1 a HU-2.7, `ConsultaController`, `Consulta`, `medical-module.js` de 2657 líneas) con el diseño heredado; tiene pendientes de `pulido-interfaz.md` (formulario de tratamiento, obligatorios avisados desde el inicio, peso desconocido).
- La batería `documentacion/CasosTriage.md` define 26 casos (T-01 a T-26) con su nivel esperado; sus niveles están **pendientes de validación por un veterinario**.

**Destino v2:** [MER](../documentacion/MER.md); [Modelos](../documentacion/Modelos.md) §1 (Grafo I con signo) y §3 (consulta clínica con el Grafo I); `CasosTriage.md` como prueba de aceptación.

**Fuera de alcance:**
- Integrar el triage en la agenda, en Agendar del portal, en urgencias y el ajuste del nivel por el veterinario (HU-4.13, HU-4.15, HU-4.18): módulo 4. M2 entrega el motor y su API.
- Carnet y QR con las alertas (HU-5.10, HU-5.11): al final de la v2.0.
- Editar el conocimiento desde una pantalla (v2.1 si se pide); en M2 el conocimiento vive versionado en el repositorio (§6, decisión 2).

## 2. Trazabilidad

| HU | RE | RN | Etapa | Evidencia de aceptación |
|---|---|---|---|---|
| HU-2.1 (alergias y alertas) | RE-2.1.5 | RN-113 | C1 | Registrar y desactivar; visible en toda clínica vinculada y en el portal |
| HU-2.10 (cierre) | RE-2.10.1 (alergias y alertas) | RN-113 | C1 | La parte que quedó pendiente en M5 |
| HU-2.8 | RE-2.8.1–5 | RN-209, RN-210, RN-212, RN-202 | C2, C5 | C2: `MotorDiagnosticosTest`, `EvaluadorCondicionesTest`, `DiagnosticoTest` y `BaseV2MysqlTest::testC2RankingConFichaYConocimientoActivosSinGuardar`; pantalla, aceptación y guardado final en C5 |
| HU-2.9 | RE-2.9.1–4 | RN-209, RN-211, RN-212, RN-113 | C3, C5 | C3: `MotorPrescripcionTest`, `PrescripcionTest` y `BaseV2MysqlTest::testC3PrescripcionSeRevalidaAlGuardarConAuditoriaYRollback`; toxicidad/alergia, interacción, privacidad, decisión vigente y auditoría atómica; presentación y selección del fármaco en C5 |
| HU-2.11 | RE-2.11.1–7 | RN-411, RN-416, RN-417, RN-418, RN-424, RN-425, RN-209 | C4 | `MotorTriageTest` y fixture de 26 casos; P1–P6, tiempo y contexto sin nota; `BuscadorSintomasTest`, `TriageTest` y `BaseV2MysqlTest::testM2C4TriageConFichaPropiaYBusquedaSinEscrituras`; captura, avisos y ajuste operativo en M4 |
| HU-2.12, mapa del grafo (aprobada en revisión de A) | RE-2.12.1–6 | RN-212, RN-209, RN-004/RN-G13 | C6 | El superadministrador explora conocimiento global con peso, signo y fuente; solo lectura y sin pacientes |

## 3. Etapas y dependencias

| Etapa | Entregable comprobable | Depende de | Escribe | Revisa |
|---|---|---|---|---|
| **A. Inventario y diseño** (solo lectura del código) | La consulta actual (código, vistas, JS, pruebas) y qué se conserva; el esquema del grafo frente a lo que piden los RE y la batería (por ejemplo: nivel de triage por síntoma, alarmas universales, sinónimos para el buscador de síntomas, fuente de cada nodo y arista, cobertura por especie, contagio, ventanas de tiempo, interacciones fármaco–fármaco, modificadores de raza, edad y estado); cada caso T-01 a T-26 traducido a lo que el propietario marcaría (síntomas del catálogo, tiempo y paciente) y el conocimiento que exige; propuesta del modelo de datos (para B), del formato de la base de conocimiento y de los tres algoritmos (ranking, prescripción, triage); diagramas §1 y §3 frente a la propuesta. | M5 | Codex | Claude |
| **B1. Esquema y cargador** | Migración `07` repetible (A.2 con los ajustes de la revisión de A), `01_schema.sql`, MER y drawDB; Gran danés en la semilla; validador y cargador idempotente con `--revisar`; arranque que solo carga si la migración salió bien; documentos de A.7; un conjunto de conocimiento mínimo de prueba. | A | Codex | Claude |
| **B2. Conocimiento inicial** | Los archivos de `database/conocimiento/` con lo que exige la batería (A.3), los casos P1 adicionales y un núcleo común de perros y gatos; cada elemento con su fuente específica y la marca «pendiente de validación veterinaria»; carga repetida sin cambios. | B1 | Codex | Claude |
| **B2.1. Correcciones del conocimiento** | Decisiones aprobadas en la revisión de B2: tóxicos por especie con mínimo naranja y etilenglicol rojo, abdomen canino sin tamaño naranja, dos marcas de parto y retiro lógico de la regla anterior; versión idéntica entre CRLF y LF. | B2 | Codex | Claude |
| **C1. Alergias y alertas médicas** | Registrar y desactivar (RE-2.1.5); visibles en la ficha de toda clínica vinculada (RN-113) y en el portal (pestaña de la mascota). | B1 | Claude Code | Claude |
| **C2. Motor de diagnósticos** (HU-2.8) | Helper sin base de datos que ordena por peso y después cobertura de síntomas; informa coincidencias X de Y y presenta máximo 5 candidatos positivos; evaluador de condiciones único compartido por C3/C4; API y pruebas con casos. | B2 | Codex | Claude |
| **C3. Alertas de prescripción** (HU-2.9) | Bloqueo por toxicidad de especie o raza y por alergia; advertencia de interacción con la medicación vigente, confirmación con auditoría; aviso genérico cuando el tratamiento es de otra clínica sin autorización. | B2, C1 | Codex | Claude |
| **C4. Motor de triage** (HU-2.11) | Helper con las reglas P1–P8 (RN-416 a RN-418, RN-425) que calcula el nivel **solo con síntomas marcados**, tiempo y paciente; la opción «Otro / no sé» da 🟡 con revisión del personal (P6); el texto libre se guarda para el personal y no cambia el nivel; buscador del catálogo tolerante a errores (sugiere, el usuario confirma); la batería completa como prueba automatizada. | B2 | Codex | Claude |
| **C5. Consulta del veterinario** | La consulta con síntomas marcados, diagnósticos sugeridos, tratamiento con sus alertas y la sección de alergias y alertas; pendientes de `pulido-interfaz.md` de la consulta. | C1–C3 | Claude Code | Claude |
| **C6. Mapa del grafo** («cerebro de nodos») | Vista de solo lectura en el panel del superadministrador: grafo interactivo con color por tipo de nodo, aristas por signo y grosor por peso, buscador, filtro por tipo y especie, y al tocar un nodo sus relaciones con peso, signo, fuente y la marca «pendiente de validación». Conviene hacerla justo después de B2: es la forma de revisar el conocimiento a ojo. | B2 | Claude Code | Claude |
| **F. Cierre** | `2.0.0-alpha.4`, HU implementadas, revisiones de documentos, descargas, despliegue con la migración 07 y la carga del conocimiento. | todo | | Claude |

## 4. Datos y migraciones

- Tablas existentes del Grafo I (ver §1). A dice qué falta; B lo agrega con una migración repetible (`07`) y el MER.
- **El conocimiento no va en `02_semilla.sql` a mano:** se propone un formato versionado (por ejemplo, JSON o CSV en `database/conocimiento/`) y un cargador idempotente que se ejecuta al desplegar, para poder revisarlo y corregirlo sin tocar SQL (decisión 2).
- El conocimiento es global (RN-212): sin `id_clinica`.

## 5. Código, permisos y pantallas

- **Helpers (sin base de datos, probados con casos):** motor de diagnósticos, motor de prescripción, motor de triage, buscador de síntomas tolerante a errores (solo sugiere).
- **Modelos:** lectura del grafo; `AlertaMedica` con el alcance de RN-113 (toda clínica vinculada lee; solo quien la registró la modifica, RN-112).
- **Controladores y rutas:** sugerencias de diagnóstico, validación de prescripción y triage (API para M4); alertas médicas. Todo en la matriz de `Security.php` (personal clínico; el triage también para el portal en M4).
- **Vistas:** consulta del veterinario (C5); pestaña de alergias y alertas en la mascota del portal (C1); mapa del grafo en el panel del superadministrador (C6), con Cytoscape.js desde cdnjs con versión fija (A confirma la librería).
- **Aislamiento:** el grafo es global; las alertas siguen RN-113; la interacción con un tratamiento de otra clínica sin autorización no revela el tratamiento (RE-2.9.3).

## 6. Decisiones

| Asunto | Decisión |
|---|---|
| 1. **Validez clínica del conocimiento** | **Decisión del usuario (2026-10-14):** no hay un veterinario disponible para revisarlo. Por eso: el conocimiento se arma solo desde fuentes públicas reconocidas (por ejemplo, la lista de tóxicos de la ASPCA y manuales veterinarios de referencia) y **cada nodo y arista guarda su fuente**; todo queda marcado «pendiente de validación veterinaria»; la interfaz dice «Sugerencia de apoyo» (RN-209); ante la duda, el triage sube de nivel y no baja (P2, P6); un bloqueo de prescripción solo se carga con fuente explícita de toxicidad, lo demás queda como advertencia. La falta de validación clínica se declara como limitación en la documentación. |
| 2. **Dónde vive el conocimiento** | **Decisión del usuario (2026-10-14):** archivos versionados en `database/conocimiento/` con un cargador idempotente que corre al desplegar; sin pantalla de edición en la v2.0. **Además pide verlo como un mapa de nodos («cerebro de nodos»):** etapa C6, de solo lectura, en el panel del superadministrador (el conocimiento es global, RN-212). A propone su HU y RE para los documentos. |
| 3. **Texto libre del triage** | **Decisión del usuario (2026-10-14):** no confiar el nivel al texto libre; interpretar lo que el propietario escribe es arriesgado para el triage y muy difícil de probar. **Mecanismo (confirmado en la revisión de A):** el nivel se calcula solo con lo marcado del catálogo, el tiempo y el paciente; el catálogo trae «Otro / no sé», que da 🟡 con revisión del personal (P6); el texto libre queda opcional, como nota para el personal, y no cambia el nivel; un buscador del catálogo tolerante a errores ayuda a encontrar el síntoma («bomita» sugiere «Vómito con sangre») pero el usuario lo marca. Ajustes en los documentos (B): RE-2.11.1 («al menos un síntoma marcado o “Otro / no sé”», ya no «o el texto»), RN-424 (el texto no interviene en el cálculo), CasosTriage: cada caso se expresa como síntomas marcados; T-22 pasa a «Otro / no sé» → 🟡 con revisión; T-23 pasa a prueba del buscador más el caso marcado → 🟠. |
| 4. **Alcance del triage en M2** | M2 entrega el motor, su API y la batería en verde; la captura en Agendar, urgencias y el ajuste del veterinario van en M4. |
| 5. **Quién escribe** | **Decisión del usuario (2026-10-14):** Codex para A, B y los motores (C2–C4); Claude Code para las pantallas (C1, C5 y C6). |
| 6. **Medicación vigente** | Se toma de los tratamientos activos (por duración) y de las alertas de tipo «medicación continua»; A confirma si el esquema alcanza. |

**Decisiones del usuario durante A (2026-10-14):**

- No inventar edades ni horas que falten en la batería.
  Los umbrales geriátricos serán por especie/raza y tendrán fuente; no se deducen de «10 años» para cualquier perro.
  Se permite «No sé» como respuesta temporal, con revisión del personal cuando ese dato sea necesario para calcular.
  B reflejará esta excepción en RE-2.11.1 y RN-424.
- T-16 conserva 🟡 como esperado provisional, con revisión por cantidad y tipo de chocolate desconocidos.
  Las 48 horas no son una regla general para bajar el nivel ni para descartar toxicidad.
  B anotará la limitación en la batería; no se declara validación veterinaria.
- Agregar solo Gran danés al catálogo global en B, con un ID nuevo y sin cambiar los existentes.
  Las categorías taxonómicas amplias o dudosas siguen sin cobertura específica y con revisión; no se inventa su especie clínica.

**Decisión del usuario durante B2 (2026-10-14):** se mantienen exactamente doce predicados.
La obesidad declarada de T-13 y «está en tratamiento» de T-19 se conservan como contexto, sin inferir peso, medicamento ni pauta.
T-13 se expresa mediante anorexia sostenida en gatos; T-19 mediante crisis breve habitual ya finalizada más epilepsia documentada.

## 7. Tareas y verificación

**Decisiones del usuario durante C3 (2026-10-15):** conciliación de máximo 500 caracteres, con rechazo si excede el límite, sin recortar.
La decisión «confirmar» requiere una huella opaca vigente por fila: si cambian los cruces, los permisos o la versión del conocimiento, se vuelve a pedir confirmación sin revelar medicación protegida.

- [x] Aprobar este plan y decidir §6 (2026-10-14).
- [x] A — Inventario y diseño: revisada y aprobada con ajustes (Codex, 2026-10-14).
- [x] B1 — Esquema, cargador y documentos: revisada y aprobada (Codex, 2026-10-14).
- [x] B2 — Conocimiento inicial con fuentes: revisada y aprobada con correcciones en B2.1 (Codex inició, Claude Code terminó; 2026-10-14).
- [x] B2.1 — Correcciones del conocimiento: revisada y aprobada (Codex, 2026-10-14).
- [x] C1 — Alergias y alertas médicas: revisada y cerrada con prueba del usuario (Claude Code, 2026-10-15).
- [x] C2 — Motor de diagnósticos: revisada y aprobada (Codex, 2026-10-15).
- [x] C3 — Alertas de prescripción: revisada y aprobada (Codex, 2026-10-15).
- [x] C4 — Motor de triage con la batería: entregada, pendiente de revisión (Codex, 2026-10-15).
- [ ] C5a — Consulta: síntomas, diagnósticos sugeridos y obligatorios.
- [ ] C5b — Consulta: prescripción con alertas, tratamiento y peso desconocido.
- [x] C6 — Mapa del grafo: revisada y cerrada con prueba del usuario (Claude Code, 2026-10-15).
- [ ] `vendor/bin/phpunit`, JS y cada RE de §2 con evidencia.
- [ ] F — `2.0.0-alpha.4`.

**Resultado de pruebas:** B1 en verde: 703 PHP (4284 aserciones y 34 omitidas sin host; 4692 aserciones y ninguna omitida con MariaDB temporal propia y `zooki_test_base_v2`), 167 JS y 27 diagramas renderizados; detalle en Anexo B1. B2 en verde: 717 PHP (6060 aserciones y 35 omitidas sin host; 6475 aserciones y ninguna omitida con `zooki_test_base_v2`), 167 JS y carga real repetida sin cambios; detalle en Anexo B2. Los motores siguen pendientes.
B2.1 en verde: 724 PHP (6200 aserciones y 36 omitidas sin host; 6624 aserciones y ninguna omitida con MariaDB temporal propia y la base por defecto), 167 JS; simulación real del salto desde B2 sin escrituras, detallada en su anexo.
C6 en verde: 738 PHP (6316 aserciones y 37 omitidas sin host; 6872 aserciones y ninguna omitida con `zooki_test_base_v2`), 176 JS; detalle en Anexo C6.

## Anexo A — Inventario y diseño

**Lo escribió Codex (2026-10-14); revisión independiente: Claude (arquitecto).**
Lectura sobre `v2/m2-grafo`, commit de partida `9276d37`.
El código y las pruebas existentes coinciden con `main`; la diferencia previa de la rama era el plan y el estado del trabajo.
Este anexo propone el contrato de B y C1–C6; no crea tablas, conocimiento, rutas, pantallas ni pruebas.
Las propuestas técnicas y los números nuevos de C6 requieren la revisión de A antes de implementar B.

### A.1. Consulta actual e inserciones para C5

| Pieza existente | Qué hace y se conserva | Separación o ampliación propuesta |
|---|---|---|
| `controllers/ConsultaController.php::registrarAjax` | Rechaza método distinto de POST; revisa todos los adjuntos antes de escribir; recoge tratamientos; responde JSON; elimina del disco lo movido si falla la transacción. | Incorporar síntomas y selección explícita de fármaco; recoger conciliación/confirmación; delegar validación clínica al servicio común de C3, también al guardar, sin confiar en una validación AJAX previa. |
| `ConsultaController::listarHistorialAjax` | Devuelve mascota, consultas visibles, vacunas y desparasitaciones; una mascota no vinculada da 403 auditado. | C1 añade alertas con su clínica de origen; C5 añade síntomas de consultas visibles, sin abrir consultas ajenas ni usar el historial como acceso interno a medicación protegida. |
| `models/Consulta.php::registrar`, `escribir`, `validar` | Exige mascota activa/vinculada, motivo y diagnóstico; valida signos opcionales; consulta, tratamientos y adjuntos se guardan juntos; completa la cita; asigna HC una sola vez por clínica con candado MySQL. | Conservar esa transacción y sus candados; insertar `consulta_sintomas` dentro de ella; repetir prescripción contra datos vigentes antes de confirmar el registro; persistir la decisión del veterinario y la versión del conocimiento usada en auditoría. |
| `models/ModeloHistoria.php` | Centraliza RN-112/RN-113/RN-115; historia propia, ajena autorizada y modo de solo lectura tras desvincularse. | Conservar; C3 tendrá una lectura interna separada y mínima para RN-211, sin ampliar `consultaVisible` ni `findByConsultas`. |
| `models/Tratamiento.php` | Valida medicamento, dosis, vía, duración y fecha inicial; escribe en consulta propia; lectura agrupada de tratamientos visibles. | Hoy no escribe `id_nodo_farmaco` ni `fecha_fin`, aunque las columnas existen; separar validación de datos y evaluación de seguridad; no deducir un principio activo del nombre comercial por coincidencia aproximada. |
| `views/vet/modal_consulta.php` | Modal compartido por Pacientes y Calendario: motivo, signos, diagnóstico/plan, tratamientos y adjuntos. | Conservar diseño; síntomas junto a anamnesis, sugerencias junto a diagnóstico y alertas junto a cada tratamiento; quitar `onsubmit` y `onchange` heredados al tocarlo; máximos, restricciones y mensajes junto al campo con helpers comunes. |
| `views/vet/atencion.php`, `public/js/atencion.js`, `CitaController::atencion` | Segunda entrada real: consulta de una cita, pasos, signos completos, borrador, registro y cierre; usa el mismo endpoint y `addTreatmentRow`. | Incluirla en C5, no solo el modal; reutilizar componentes de síntomas/prescripción y ampliar el borrador solo para datos editables, nunca para una autorización de prescripción reutilizable. |
| `public/js/medical-module.js` (2657 líneas) | Mezcla mascotas, propietarios, consultas, tratamientos, historial, vacunas, desparasitación y agenda. | Extraer solo consulta, selector de síntomas y filas de tratamiento a archivos propios reutilizados por ambas entradas; mantener adaptadores de las llamadas actuales, sin reescribir mascotas/agenda. |
| `ResumenClinico` y vistas de historial | Resumen de último peso/consulta y próximas dosis; varias presentaciones del mismo historial, incluida impresión. | Sus «alertas» actuales son avisos de dosis, no `alertas_medicas`; añadir una sección distinta de seguridad clínica, manteniendo la privacidad de las consultas y el peso desconocido como NULL, nunca 0. |

**Enganches concretos:**

- Síntomas: `tabMotivo` del modal y primer paso de `formConsultaAtencion`; IDs explícitos hacia `consulta_sintomas`, nunca extracción de la anamnesis.
- Diagnósticos: antes del textarea obligatorio de diagnóstico; el buscador o ranking no lo rellena ni lo acepta por sí solo.
- Prescripción: `addTreatmentRow` y `tratamientosDelFormulario`; cada fila tendrá nodo opcional confirmado, fecha de fin y resultado de evaluación.
- Alergias: C1 aporta el componente compartido y las lecturas para clínica y portal; C5 lo presenta antes de prescribir.
- Guardado: `Consulta::escribir` conserva la unidad atómica; si cambia una alergia, medicación o versión de conocimiento desde la vista previa, el servidor vuelve a evaluar y exige una nueva decisión cuando corresponda.
- La comprobación previa de permisos y la auditoría de rechazo se conservan fuera del rollback que borraría su evidencia.

**Hallazgos concretos para C5, sin corregir en A:**

- `saveConsultation` todavía usa un aviso global para motivo/diagnóstico y recarga al guardar; los errores de campo deben verse junto al campo y el estado actualizarse con la respuesta.
- `addTreatmentRow` solo presenta nombre libre, dosis, vía, duración e inicio; no muestra observaciones ni fin, aunque el controlador admite observaciones.
- El modal no presenta frecuencia respiratoria ni observaciones de consulta, que sí admite el modelo y sí tiene Atención.
- La duración libre no demuestra vigencia y un peso aislado no demuestra obesidad.
  No usar ninguno como dato estructurado inferido.
- Persisten eventos en línea del modal y un `style=` en el error de historial de `medical-module.js:1361`; retirarlos en la extracción de C5, conservando el diseño.

**Pruebas que se conservan y se ampliarán en su etapa:**

| Prueba existente | Evidencia actual | Ampliación futura |
|---|---|---|
| `ConsultaHistorialTest` | Campos, motivo/diagnóstico, fecha inicial del tratamiento, rollback de consulta/archivos/tratamientos, mascota activa, HC por clínica, veterinario asignado y listado aislado. | Síntomas en la misma transacción y rechazo servidor de prescripción bloqueada, incluso por petición directa. |
| `HistoriaCompartidaTest` | Sin autorización no se muestra ni se menciona historia ajena; con autorización llega de solo lectura; desvinculación conserva solo lo propio. | Alertas globales con origen y avisos de interacción protegida sin filtración en ninguna clave JSON. |
| `BaseV2MysqlTest::testC4HistoriaClinicaEnElEsquemaReal`, `testC4ElNumeroDeHistoriaSeAsignaBajoCandadoSinRepetirse` | FK, atomicidad y concurrencia de numeración en el esquema real. | Migración 07 repetida, carga repetida y guardado seguro en B/C3/C5. |
| `ValidadorClinicoTest`, `ResumenClinicoTest`, pruebas JS de M5 C3/C4b | Validación clínica, resumen, historial y solo lectura de Pacientes. | Pruebas de componentes de ambas entradas de consulta; hoy no prueban motores inexistentes. |

### A.2. Esquema frente al conocimiento requerido

**Inventario confirmado:** `01_schema.sql`, MER y drawDB coinciden en las dos tablas del grafo y sus enlaces clínicos.
`grafo_nodos` solo tiene ID, tipo y etiqueta; `grafo_aristas`, extremos, relación, signo y peso.
No hay conocimiento en `02_semilla.sql`, ni código de negocio que use nodos, aristas, síntomas estructurados o alertas médicas.
`consulta_sintomas` y `cita_sintomas` alcanzan para marcar nodos sin duplicados, pero no guardan atributos temporales por síntoma.

**Taxonomía real de la semilla:** Canino, Felino, Roedor, Ave, Reptil y Exótico.
Labrador Retriever y Bulldog Francés sí existen; Gran danés no existe.
`Conejo Enano` aparece bajo `Roedor`, y `Loro Amazónico` bajo `Ave`: son categorías heredadas, no cobertura clínica validada.
No deducir «conejo» de cualquier Roedor ni «loro» de cualquier Ave, ni tomar `raza_indicada` como raza verificada.
El usuario aprobó el 2026-10-14 incluir solo Gran danés en B con un ID nuevo y conservar los IDs existentes.
Las categorías amplias o dudosas siguen sin cobertura específica y con revisión; no se reclasifica taxonomía ni se inventa una especie clínica.

La migración 07 propuesta amplía el esquema y, a la vez, B actualiza MER, drawDB y `01_schema.sql`.
Todos los nombres y largos siguientes son propuesta de diseño, no columnas ya aprobadas o existentes.

| Necesidad | Qué alcanza hoy | Propuesta para B / migración 07 |
|---|---|---|
| Identidad estable y retiro lógico | IDs numéricos referenciados por FK; etiquetas no son únicas. | En nodos y aristas: `codigo VARCHAR(100) UNIQUE`, `activo`, `version_conocimiento VARCHAR(50)`; el código externo es inmutable y no se recicla; el ID numérico local no se cambia. |
| Fuente y validación por nodo/arista, decisión 1 | Ninguna columna. | En ambos: `fuentes JSON NOT NULL`, `estado_validacion` (`pendiente`, `validado`, `rechazado`), `validado_por` y `fecha_validacion` opcionales; todos los elementos iniciales quedan pendientes; toda corrección de contenido clínico invalida una validación anterior. |
| Triage básico y alarmas P1 | No existe nivel por síntoma. | Nodos síntoma: `nivel_triage` nullable, `alarma_universal`, `moderado`, `metadatos JSON`; las alarmas universales producen rojo sin depender de cobertura, raza o texto. |
| Sinónimos y búsqueda | Solo etiqueta. | `grafo_sinonimos`: `codigo`, `id_nodo`, `texto`, `normalizado`, `fuentes`, `estado_validacion`, `activo`, `version_conocimiento`; una misma forma puede sugerir más de un nodo; no obliga a una interpretación única. |
| Especie y raza del paciente frente a nodos | No hay enlace entre nodo especie/raza y catálogos taxonómicos. | `grafo_nodos.id_especie` / `id_raza` opcionales con FK e índices; unicidad de cada enlace según tipo; validar coherencia raza–especie; nunca enlazar por IDs supuestos ni por el texto libre `raza_indicada`. |
| Cobertura explícita | Que exista un nodo especie no demuestra cobertura clínica. | Nodo especie con `metadatos.cobertura` por motor (`diagnosticos`, `triage`, `prescripcion`) y alcance de reglas; perro/gato con cobertura parcial declarada; conejo/loro sin cobertura específica de triage; P1 global disponible. |
| Combinaciones, ventanas P5 y modificadores P4 | Una arista binaria no representa por sí sola «A Y B Y paciente», ventanas ni datos desconocidos. | `grafo_reglas`: `id_regla`, `codigo UNIQUE`, `motor`, `condiciones JSON`, `resultado JSON`, `fuentes`, `estado_validacion`, `activo`, `version_conocimiento`, validación/autor opcionales; lenguaje declarativo cerrado, sin PHP/SQL/JavaScript ejecutable. |
| Interacción y toxicidad | Aristas entre fármacos y desde especie/raza alcanzan para relaciones simples. | Aristas con `condiciones JSON` y `efecto` explícito (`evidencia`, `bloqueo`, `advertencia`); `interactua_con` se evalúa en ambos sentidos sin duplicar pares; toxicidad documentada no se decide por un peso ni por cualquier signo negativo. |
| Lirios, chocolate y xilitol | No son fármacos; el enum actual no representa sustancias tóxicas como tales. | Proponer el tipo de nodo `sustancia`, conservando los seis tipos existentes; síntomas de exposición lo enlazan a especie/toxicidad; no registrar una planta como medicamento prescrito. |
| «Otro / no sé» | Puede existir como nodo síntoma, sin significado clínico. | Código estable `sintoma.otro_no_se`, nivel de reserva amarillo y revisión; su fuente es la política RN-418, no una enfermedad; combinarlo con P1 nunca baja un rojo. |
| Contexto temporal y del episodio | `citas.inicio_sintomas` es único y nullable. | `detalles JSON` opcional en `cita_sintomas` y `consulta_sintomas` para inicio conocido/desconocido, episodio en curso/resuelto, número de episodios y otros atributos declarados; `citas.contexto_triage JSON` para datos declarados del paciente y versión/reglas de cálculo; no guardar allí tratamientos protegidos. |
| Paciente P4 | Ficha: especie, raza, nacimiento, sexo y esterilización nullable; vacunas y alertas están disponibles. | Obtener edad a la fecha de evaluación; vacunación incompleta/desconocida y condición corporal no se infieren del silencio ni del peso; preguntas cerradas o alertas de condición crónica; tiempo del celo separado del inicio del flujo, en contexto estructurado del episodio. |
| Medicación vigente | Ya existen `fecha_inicio`, `fecha_fin` e `id_nodo_farmaco`; `alertas_medicas` admite medicación continua con nodo. | No falta una segunda tabla de tratamientos; falta usar esas columnas, pedir fin explícito para tratamiento acotado y leer activas las alertas continuas; NULL de fin en datos heredados significa vigencia desconocida/potencial, no tratamiento terminado. |
| Auditoría de decisiones | `auditoria_sistema` ya guarda clínica, actor, tabla, ID, acción, descripción y datos anteriores/nuevos. | Reutilizarla para regla/versión, rechazo, conciliación y decisión; el servidor conserva trazabilidad interna y la respuesta genérica no incluye IDs ni metadatos del tratamiento protegido. |

**Contrato del lenguaje de reglas:** condiciones `todos`, `alguno` y predicados tipados sobre un conjunto cerrado de datos.
Ejemplos de predicados: síntoma marcado, especie/raza, edad en meses, sexo, esterilización, alerta activa enlazada, inicio de episodio y minutos desde exposición.
Cada tiempo identifica el evento al que pertenece y usa intervalos explícitos con límites inclusivos/exclusivos.
Un dato ausente es `desconocido`, no `false`, cero ni «sin riesgo».
El cargador rechaza campos desconocidos, ciclos de referencias de reglas, unidades incompatibles y un resultado de bloqueo sin fundamento permitido.
Las reglas se evalúan independientemente; no leen el nivel que produjo la regla anterior.

**Medicación vigente y privacidad, decisión 6:**

- Tratamiento acotado: `fecha_inicio <= hoy <= fecha_fin`, con fin inclusivo al tener granularidad DATE; un tratamiento futuro no es vigente.
- C5 pide fecha final o una duración estructurada que permita calcularla inequívocamente; se conserva `duracion` como texto descriptivo.
  No analizar «hasta mejorar», «7 días?» o un nombre comercial para inventar fechas o principios activos.
- Medicación continua: alerta activa de tipo `medicacion_continua`, enlazada a un nodo fármaco cuando esté identificado.
- Fin o fármaco desconocido: marcar evaluación incompleta y solicitar conciliación; no afirmar «sin interacciones».
  No inferir identidad de un fármaco desde una descripción de alerta no enlazada.
- El modelo interno de C3 lee los nodos necesarios de tratamientos de todas las clínicas para esa mascota, tras comprobar vínculo activo de la clínica solicitante.
  No devuelve ese conjunto al controlador de historial ni al navegador.
- Una alerta global de `condicion_cronica` «epilepsia» permite T-19 sin abrir una consulta protegida.
  La ausencia de esa alerta no autoriza leer diagnósticos ajenos ni a afirmar que es una crisis habitual.
- Evaluar también interacciones entre las filas nuevas de la misma consulta.
  La confirmación del veterinario no puede saltarse toxicidad o alergia; solo resuelve advertencias permitidas por RN-211.
- La regla de §6 sobre fuente pública se refiere al conocimiento de toxicidad.
  Una alergia individual registrada y enlazada al fármaco bloquea por RE-2.9.2 aunque no sea una relación bibliográfica.

### A.3. Traducción de T-01 a T-26

Los colores son los esperados provisionales de la batería, no diagnósticos ni una validación veterinaria.
Los nombres siguientes proponen entradas del catálogo; B les asignará códigos estables y fuentes.
«Sin dato» mantiene el atributo desconocido; no se añade una raza, vacunación, estado ni una hora que el caso no declara.
Para casos sin tiempo, se propone la respuesta explícita «No sé» aprobada durante A; una alarma suficiente no espera ese dato y una regla que lo necesita pide revisión.
Una opción tranquilizadora puede precisar qué episodio se describe, pero nunca resta puntos a una alarma.

| Caso | Marcas del catálogo y atributos cerrados del episodio | Tiempo declarado | Paciente y estado aportados | Esperado | Conocimiento mínimo |
|---|---|---|---|---|---|
| T-01 | Dificultad para respirar; encías azuladas/moradas. | En curso; inicio sin dato. | Perro mestizo, 6 años; resto sin dato. | 🔴 | P1 universal; dos alarmas no se promedian. |
| T-02 | Convulsión en curso. | Ahora, duración 6 minutos. | Gata, 3 años; resto sin dato. | 🔴 | P1: basta que siga en curso; no exigir superar 5 minutos para activar la alarma. |
| T-03 | Atropello/traumatismo; hemorragia que no se detiene. | Sangrado actual; hora del atropello sin dato. | Perro, 4 años. | 🔴 | P1 prevalece sobre regla de trauma. |
| T-04 | Intentos repetidos de orinar con salida mínima; dolor/maullido al intentarlo. | Inicio sin dato. | Gato macho castrado, 5 años; se declara «por lo demás bien». | 🔴 | Combinación de signos urinarios de alarma, no exigir que el propietario diagnostique obstrucción ni confundirlo con simple frecuencia urinaria. |
| T-05 | Arcadas sin producir vómito; abdomen distendido; inquietud. | Inicio sin dato. | Perro gran danés, 7 años; raza gigante. | 🔴 | Regla explícita combinación + raza gigante; no confundir arcadas con vómito aislado. |
| T-06 | Mucha sed; decaimiento; secreción genital. | Celo hace 5 semanas; inicio de otros signos sin dato. | Perra, 8 años, no esterilizada. | 🟠 | Sexo/estado reproductivo y evento «último celo»; sospecha de piometra como apoyo, no diagnóstico automático. |
| T-07 | Atropello/traumatismo, aunque camina. | Sin dato. | Perro, 5 años; camina normalmente. | 🟠 | Regla de trauma; caminar no anula el riesgo. |
| T-08 | Mordió/ingirió lirios identificados. | Sin dato. | Gato, 2 años, sin signos visibles. | 🔴 | Sustancia + toxicidad por especie; no exige un síntoma posterior. |
| T-09 | Respiración ruidosa después de ejercicio; cansancio tras ejercicio; exposición a calor. | Después de caminar; intervalo exacto sin dato. | Perro bulldog francés, 3 años. | 🟠 | Modificador braquicéfalo; cansancio no equivale por sí solo a colapso ni respiración ruidosa a dificultad respiratoria P1; añadir cualquiera de esas alarmas da rojo. |
| T-10 | Las mismas marcas de T-09. | Igual que T-09. | Perro labrador, 3 años. | 🟡 | Control sin modificador braquicéfalo; no clasificar «cansancio tras ejercicio» como decaimiento sistémico de P3. |
| T-11 | Vómito; diarrea con sangre; falta de apetito. | Sin dato. | Perro, 3 meses, vacunas incompletas. | 🟠 + aislamiento | P3 y contexto cachorro/vacunación; regla de posible contagio separada del color. |
| T-12 | Vómito, un episodio; diarrea sin sangre declarada. | Inicio sin dato; un vómito. | Perro, 6 años, vacunado, activo. | 🟡 | Episodio aislado y ausencia de marcas de alarma; no inventar vacunas específicas desde una edad. |
| T-13 | No come; se esconde. | Sin comer 3 días. | Gato, 9 años, obesidad declarada. | 🟠 | Anorexia sostenida + especie/condición corporal; peso solo no prueba obesidad; fuente para la ventana usada. |
| T-14 | Ingesta de chicle sin azúcar; posible exposición a xilitol. | 20 minutos. | Perro, 15 kg; edad/raza sin dato. | 🟠 | Sospecha de sustancia y tiempo; no afirmar ingrediente, dosis ni seguridad cuando se desconocen. |
| T-15 | Administración/ingesta de acetaminofén (sinónimo de paracetamol). | Sin dato. | Gato; fiebre referida. | 🔴 | Mismo nodo fármaco y misma relación de toxicidad que prescripción; no inventar dosis. |
| T-16 | Ingesta de chocolate; sin signos declarados. | 48 horas. | Perro; peso, tipo y cantidad de chocolate sin dato. | 🟡 provisional + revisión | P5 contextual, no descuento general por tiempo; faltan cantidad/tipo; decisión del usuario durante A y validación clínica pendiente. |
| T-17 | Vómito repetido; no bebe; decaimiento marcado; encías secas/deshidratación. | 4 vómitos hoy. | Perro, 10 años; raza/tamaño sin dato. | 🟠 | P3 sube una vez la evidencia moderada; no sumar sucesivamente deshidratación, número de síntomas y una geriatría inventada hasta rojo. |
| T-18 | Episodio convulsivo ya finalizado; primera crisis. | Ayer; recuperado ahora. | Perro, 4 años, activo y come. | 🟠 | Primera crisis resuelta es distinta de convulsión en curso; recuperación no borra esa regla. |
| T-19 | Episodio convulsivo finalizado, único, corto y habitual; recuperado. | Pasado, duración exacta sin dato. | Perro con epilepsia conocida y tratamiento; edad/raza sin dato. | 🟡 | Alerta global de epilepsia + medicación conocida, o historia autorizada; no interpretar una consulta protegida; sin evidencia de patrón habitual, revisión. |
| T-20 | No come; no defeca. | Desde ayer. | Conejo, 2 años. | 🟡 + revisión inmediata antes de agendar | P6: conejo sin cobertura específica; no cargar naranja como regla aprobada; ninguna respuesta tranquilizadora elimina la revisión. |
| T-21 | Dificultad para respirar, con ayuda visible «mueve mucho la cola al respirar»; plumaje erizado. | Sin dato. | Loro; edad/raza sin dato. | 🔴 | P1 universal; la marca explícita sustituye la interpretación automática del relato, aunque aves no tengan cobertura específica. |
| T-22 | «Otro / no sé»; nota opcional «está raro». | «No sé». | Perro; resto sin dato. | 🟡 + revisión | P6; una nota sola no satisface la captura ni calcula un nivel. |
| T-23 | Buscar «bomita sangre» y «desaido»; confirmar Vómito con sangre y Decaimiento. | «No sé» si no se conoce. | Gato; resto sin dato. | 🟠 | Búsqueda tolerante + selección explícita; sangre en vómito no equivale por sí sola a hemorragia incontrolable P1; P3/combinación. |
| T-24 | Control preventivo / vacunación, sin síntomas de alarma marcados. | No aplica, motivo preventivo explícito. | Perro, 3 años. | 🟢 | Entrada reconocida del catálogo para atención preventiva; añadir P1 sigue dando rojo. |
| T-25 | Picor de oídos leve; sin otras alarmas marcadas. | Una semana. | Gato, 4 años, come y juega. | 🟢 | Regla de molestia leve estable; no tratar la ausencia de marcas como síntoma desconocido si la entrada sí está cubierta. |
| T-26 | Cojera leve estable; mantiene actividad habitual. | Un mes. | Perro, 6 años. | 🟢 | Intensidad/estabilidad explícitas y cronicidad; no aplicar esa regla a cojera aguda, incapacidad de apoyar ni trauma. |

**Pérdidas de significado y límites de la batería:**

- T-22 deja de probar comprensión de «está raro»; prueba captura de «Otro / no sé» y revisión.
- T-23 pasa a dos comprobaciones: el buscador ofrece opciones y el motor calcula después de confirmarlas.
  «bomita» por sí solo puede sugerir Vómito y Vómito con sangre, pero nunca añade sangre ni selecciona una opción automáticamente.
- T-04, T-06, T-09, T-13, T-18, T-19 y T-21 necesitan etiquetas y preguntas comprensibles para conservar el significado que antes estaba escondido en el texto.
  Una lista de «vómito, diarrea, dolor» sin esos matices no cubre la batería.
- T-24 requiere «No aplica» para tiempo de atención preventiva; no inventar un inicio de síntomas inexistentes.
  B documentará esta precisión junto a «No sé» en RE-2.11.1.
- T-14/T-16 cambian sustancia además de tiempo: no aíslan P5.
  C4 debe añadir pruebas del mismo tóxico con ventanas distintas y P1 agregado, sin cambiar en A los 26 esperados ni inventar una ventana clínica sin fuente.
- P1 también exige casos adicionales de encías pálidas, colapso/inconsciencia, imposibilidad de orinar, golpe de calor y parto con esfuerzo sin cría >30 minutos; la batería no cubre cada alarma aisladamente.
- El esperado 🟡 de T-20 y la ausencia de revisión veterinaria siguen siendo limitaciones abiertas; pasar las pruebas no significa que la batería clínica quede validada.

### A.4. Base versionada y cargador idempotente

**Propuesta: JSON UTF-8**, por su representación directa de condiciones, fuentes y listas; CSV obligaría a ocultar esos datos en celdas o multiplicar joins entre archivos.
El JSON es declarativo y se valida antes de tocar datos.

| Archivo propuesto bajo `database/conocimiento/` | Contenido y campos mínimos |
|---|---|
| `manifiesto.json` | `formato`, `version`, archivos esperados y sus hashes, fecha, limitación de validación, compatibilidad del motor y política de cobertura. |
| `fuentes.json` | Código de fuente, entidad/autores, título, URL exacta, fecha/edición cuando esté publicada, fecha de consulta y sección que respalda el dato; no copiar capítulos completos. |
| `nodos.json` | Código estable, tipo, etiqueta, fuentes con localizador, validación, nivel/moderación/alarma cuando apliquen, referencia taxonómica y metadatos declarados. |
| `aristas.json` | Código estable, códigos de origen/destino, relación, signo, peso, efecto, condiciones, fuentes y validación. |
| `sinonimos.json` | Código estable, código de nodo destino, texto, fuentes y validación; el normalizado se calcula con el mismo normalizador de búsqueda. |
| `reglas.json` | Código estable, motor, condiciones cerradas, resultado tipado, fuentes y validación; referencias a nodos/aristas por código, no por ID local. |

Ejemplos de identidad externa: `sintoma.convulsion_en_curso`, `farmaco.paracetamol`, `sustancia.lirio_lilium`, `regla.triage.alarma_respiratoria`.
No usar posiciones del array ni `MAX(id)+1` como identidad del archivo.
Las referencias a taxonomía se resuelven contra catálogos existentes y se comprueba su especie; si no hay equivalencia inequívoca, la carga falla y B registra la falta, sin crear razas a escondidas.
La ampliación aprobada de Gran danés para T-05 va por `02_semilla.sql` con ID nuevo e inserción que no sobrescribe, junto con su inclusión repetible en 07 cuando corresponda.
El cargador del conocimiento no crea especies o razas.

**Fuentes y límites para B:**

- Cada nodo, arista, sinónimo y regla registra su evidencia específica; el enlace a la portada de un manual no basta.
  Una referencia a RN-417 justifica una política de triage, pero no reemplaza una fuente veterinaria que sostenga la toxicidad.
- Separar fundamento bibliográfico de decisiones del producto y de pesos heurísticos pendientes de validación.
  Una fuente que describe una enfermedad no valida por sí sola el peso 0.8 ni un color de triage.
- [ASPCA: Lilium y gatos](https://www.aspca.org/pet-care/aspca-poison-control/toxic-and-non-toxic-plants/lily) es una fuente candidata concreta para la relación de T-08; no extenderla a cualquier flor llamada «lirio».
- [MSD Veterinary Manual: chocolate](https://www.msdvetmanual.com/toxicology/food-hazards/chocolate-toxicosis-in-animals) considera tipo/cantidad y signos, además del tiempo; no respalda una regla general «48 horas = sin peligro».
  Esta revisión motivó la decisión del usuario sobre T-16.
- B verificará fuentes específicas del resto de los elementos antes de cargarlos; A no da por verificada toda la base futura.
  Si una regla necesaria no tiene sustento suficiente, se informa antes de inventarla o declarar cobertura.

**Cargador propuesto (`scripts/cargar_conocimiento.php`, modelo de escritura y validador separados):**

1. Lee todos los archivos y verifica manifiesto/hashes, unicidad de códigos, tipos, referencias, fuentes, pesos finitos y coherencia de relaciones.
2. Comprueba que migración 07 esté aplicada; prepara en memoria un conjunto completo y toma un bloqueo para evitar dos cargas simultáneas.
3. En una transacción, inserta los códigos nuevos y actualiza solo conocimiento gestionado por esos archivos.
   Conserva IDs numéricos y todas sus referencias clínicas; resuelve extremos por código.
4. Un código que desaparece del manifiesto completo se desactiva, nunca se borra.
   El historial conserva el nodo/etiqueta y el motor deja de usar su conocimiento retirado; el retiro de una regla crítica requiere revisión del diff.
   Un archivo faltante, inválido o un hash incorrecto causa error, nunca un retiro masivo.
5. Un elemento reintroducido recupera su mismo ID; cambiar de concepto exige un código nuevo, no reciclar uno anterior.
6. Un cambio de etiqueta puramente ortográfico conserva identidad; un cambio clínico reinicia validación a pendiente y cambia la versión.
   Se conserva versión/reglas utilizadas en la auditoría de cada decisión para poder reconstruirla desde el repositorio.
7. Solo confirma si todo es válido; ante error conserva íntegro el conocimiento anterior y devuelve código de salida distinto de cero.
8. Correr dos veces el mismo conjunto no crea filas ni altera IDs/validación; registra conteos de altas, cambios y retiros.
   Un modo `--revisar` muestra ese diff sin escribir.

**Despliegue:** B engancha la carga después de `scripts/migrar.php` y antes de iniciar Apache, sin modificar `02_semilla.sql` para meter el grafo.
Hoy `docker/iniciar.sh` arranca Apache incluso si falla la migración: no encadenar una carga sobre un esquema incompleto.
Si falla migración/carga, se conserva el servicio con conocimiento previo compatible; si no hay conjunto compatible, el motor devuelve indisponibilidad/revisión y nunca una prescripción «segura» o triage verde por base vacía.
F verificará en logs migración y versión cargada; A no ejecuta esos scripts sobre la base de trabajo.

### A.5. Helpers puros y contratos

Los helpers reciben arrays normalizados, un conjunto de conocimiento inmutable y la fecha de evaluación explícita.
No consultan PDO, sesión, reloj global ni archivos; modelos/controladores preparan sus entradas y aplican permisos.
Las respuestas incluyen versión, reglas y limitaciones; los serializers para el navegador filtran lo protegido.
El servidor valida IDs y tipos de nodo; seleccionar un ID desconocido o retirado no crea síntomas nuevos.

#### A.5.1. Ranking de diagnósticos — HU-2.8

**Entrada:** síntomas únicos confirmados, atributos de episodio, especie/raza/edad/estado, alertas globales y antecedentes autorizados, grafo activo.
**Salida:** diagnósticos con puntuación de afinidad, evidencia coincidente, fuente y validación; o lista vacía y obligación de diagnóstico libre.

```text
S = conjunto de síntomas explícitamente marcados
para cada diagnóstico D relacionado con algún síntoma de S
    evidencias = aristas aplicables de S hacia D
    si no hay evidencia positiva de síntomas: omitir D
    contexto = modificadores de D cuyos predicados están acreditados
    puntuación[D] = suma(signo(e) * peso(e), e en evidencias y contexto)
    si hay exclusión explícita aplicable: omitir D
ordenar por puntuación descendente y código estable para desempatar
devolver candidatos con evidencia y limitaciones, sin elegir uno
```

No recorrer indefinidamente ciclos, ni acumular sinónimos del mismo síntoma como evidencia independiente.
El peso no es una probabilidad clínica; no mostrar «80 % de certeza» por una suma 0.8.
Ausencia de una marca no equivale a un hallazgo negativo; solo se resta evidencia explícita aplicable.
Los modificadores no leen diagnósticos protegidos y el contexto por sí solo no inventa un síntoma.
El diagnóstico final sigue siendo texto decidido por el veterinario, sugerido o propio, obligatorio por RN-202.

#### A.5.2. Prescripción — HU-2.9

**Entrada:** fármaco confirmado o desconocido, paciente, alergias activas globales, medicamentos vigentes/potenciales internos y filas nuevas, grafo y contexto de evaluación.
**Salida interna:** `bloqueada`, motivos, advertencias, evaluación incompleta, conciliación requerida y reglas/versión.
**Salida pública:** solo motivos visibles; interacción protegida con mensaje genérico sin fármaco ajeno, dosis, ID, clínica, fuente o arista que permita reconstruirlo.

```text
si hay alergia activa enlazada al fármaco: bloquear
si hay toxicidad de especie/raza con fuente explícita aplicable: bloquear
por cada medicamento vigente y cada fila nueva distinta
    buscar interacción en ambos sentidos y comprobar condiciones
    añadir advertencia; marcar protegida si no tiene permiso de lectura
si falta identidad o vigencia necesaria: marcar evaluación incompleta
si hay advertencias: exigir decisión explícita del veterinario
si alguna es protegida: exigir conciliación documentada o autorización verificada
al guardar: releer, recalcular y auditar decisión con usuario y clínica
si sigue bloqueada o falta decisión exigida: no escribir la consulta
```

Un efecto «negativo» no es automáticamente bloqueo: interacción y toxicidad tienen semántica distinta.
Una duda bibliográfica produce advertencia, no una contraindicación inventada; tampoco una declaración de seguridad.
No permitir que un booleano del cliente «ya confirmado» sustituya la comprobación de motivos y conciliación en el servidor.
La autorización para ver historia se relee; su revocación entre vista previa y guardado vuelve genérica la alerta.
El conocimiento global expuesto por C6 no incluye cuáles de sus fármacos toma un paciente.

#### A.5.3. Triage — HU-2.11, P1 a P8

**Entrada:** marcas del catálogo y atributos cerrados, tiempos por evento conocidos/«No sé»/«No aplica», paciente y alertas permitidas, reglas y cobertura, fecha de evaluación.
**Salida:** nivel calculado, revisión requerida e inmediata cuando proceda, posible contagio, reglas/versión y datos necesarios desconocidos.
La nota libre no es una entrada del helper y nunca se interpreta.
El orden de severidad es rojo > naranja > amarillo > verde; no depende del orden alfabético ni del ordinal del ENUM de MySQL.

```text
validar captura y eliminar marcas duplicadas
si no hay marca reconocida: reserva amarilla y revisión P6
evaluar todas las alarmas universales P1 aun sin cobertura
si falta cobertura específica: añadir reserva amarilla y revisión P6
si existe cobertura: reunir niveles básicos aplicables a este episodio
si no hay regla o nivel aplicable a las marcas: añadir reserva amarilla y revisión
aplicar P3 una vez sobre síntomas moderados distintos, no sobre resultados escalados
evaluar reglas de combinación, paciente y tiempo P4/P5 independientemente
si falta un dato que decide una regla: marcar revisión, sin inventar su valor
nivel = máximo de niveles disparados, sin restar por datos tranquilizadores P2
evaluar bandera de contagio RN-425 independientemente del nivel
devolver cálculo y trazabilidad, sin reservar ni ajustar una cita
```

- P1 es universal y no depende de que un manual específico de conejos o aves esté cargado.
- P3 cuenta síntomas clínicos distintos, no la misma marca repetida ni sinónimos, y no se aplica en cascada junto con modificadores de edad hasta generar un rojo sin regla.
- P4/P5 producen candidatos condicionados al episodio, no descuentos sobre una alarma ya disparada.
  Convulsión actual, primera crisis resuelta y crisis habitual resuelta son entradas distintas; la regla temporal de chocolate no borra una alarma universal.
- P6 mantiene T-20 amarillo con revisión inmediata y T-22 amarillo con revisión; ante cualquier P1 ambos pasan a rojo.
- P7: el motor conserva su cálculo; M4 implementa ajuste veterinario, motivo obligatorio, auditoría e historial, usando las columnas ya existentes de citas.
- P8: el motor no recibe cuotas ni mora; devuelve rojo aunque el plan no tenga cupo.
  M4 ejecuta la derivación de urgencia antes de consultar límites y prueba O-01 a O-10.
- M2 prueba el indicador de posible contagio y lo expone a M4; la entrega operativa del aviso asociado al agendamiento pertenece a M4.

#### A.5.4. Buscador de síntomas — solo sugerencias

**Entrada:** consulta de búsqueda, etiquetas y sinónimos activos, filtro de especie con alarmas universales siempre disponibles.
**Salida:** opciones con ID/código/etiqueta, tipo de coincidencia y puntuación de similitud; nunca un nivel ni una marca seleccionada.

```text
normalizar Unicode, mayúsculas, tildes y espacios para comparar
buscar etiqueta/sinónimo exacto, luego prefijo y tokens coincidentes
si hace falta, ordenar candidatos por distancia Damerau-Levenshtein normalizada
resolver empates por coincidencia exacta, distancia y código estable
ofrecer opciones y conservar «Otro / no sé»
solo al confirmar una opción enviar su ID al motor
```

La distancia se calcula sobre Unicode normalizado, no bytes de tildes.
El umbral técnico y número de resultados serán constantes compartidas y verificadas con el corpus en C4, no una puntuación clínica.
Buscar «bomita» no convierte la nota ni la consulta en «vómito con sangre».
No usar LLM, corrección silenciosa de la historia ni extracción de fármacos de una nota.

### A.6. Mapa del grafo C6 y propuesta de HU/RE

**Se confirma Cytoscape.js 3.34.3**, versión disponible verificada en [cdnjs](https://cdnjs.com/libraries/cytoscape), fijada en `https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.34.3/cytoscape.min.js`.
Su [documentación oficial](https://js.cytoscape.org/) admite datos JSON, estilos separados y layouts incluidos; encaja con JavaScript sin bundler y no requiere otra librería de grafos.
Usar layout `cose` incluido, estilos en `public/js/grafo-conocimiento.js`/CSS externo y hash SRI de ese archivo comprobado al implementar C6, sin URL `latest`.
Si falla el CDN, mostrar indisponibilidad y conservar una lista legible de los datos cargados; no una pantalla vacía.
No se implementa ni descarga la librería en A.

**Rutas propuestas:** `plataforma_grafo` y GET `obtener_grafo_conocimiento_ajax`, exclusivas del contexto de superadministrador en `Security`.
El endpoint consulta únicamente conocimiento global activo, sus fuentes, reglas y cobertura; no acepta `id_mascota`, `id_consulta` ni `id_clinica` para ampliar la respuesta.
La vista clínica no usa este endpoint de plataforma: los motores tienen su lectura de conocimiento y sus permisos de paciente por separado.

**Respuesta propuesta:** `version`, `limitaciones`, `cobertura`, `nodes`, `edges`, `reglas`.
Los nodos llevan ID/código, tipo, etiqueta, fuentes y validación; las aristas, extremos, relación, signo, peso, efecto, condiciones, fuentes y validación.
Las reglas con más de dos condiciones se muestran en el detalle relacionado, indicando que no equivalen a una sola arista.
Los filtros de especie conservan relaciones universales; el detalle distingue taxonomía existente de cobertura clínica.
La selección de tipo/especie y el buscador no escriben en base de datos ni cambian el conocimiento.

**Propuesta de historia libre en la numeración actual: HU-2.12 — Explorar el mapa del conocimiento clínico.**
Como superadministrador, quiero recorrer el conocimiento global con sus relaciones y fuentes para revisar su cobertura y estado de validación, sin acceder a datos de pacientes ni editarlo.

| RE propuesto | Criterio de aceptación y prueba futura C6 |
|---|---|
| RE-2.12.1 | Solo el superadministrador en contexto de plataforma abre vista y endpoint; 403 para otros contextos, incluso una persona superadministradora actuando en clínica. |
| RE-2.12.2 | Nodos distinguibles por tipo y aristas dirigidas por signo/grosor de peso, con leyenda; prueba JS de mapeo y revisión visual. |
| RE-2.12.3 | Buscar y filtrar por tipo/especie permite recorrer conexiones, conservando las universales; prueba JS y conjunto conocido. |
| RE-2.12.4 | El detalle de nodo/arista/regla muestra relación, peso cuando aplique, signo, efecto, condiciones, fuente y estado de validación; comprobación de respuesta y detalle. |
| RE-2.12.5 | Solo lectura y solo conocimiento global; ninguna consulta o respuesta incluye datos de mascotas, consultas, tratamientos o citas, ni permite escribir conocimiento. |
| RE-2.12.6 | Muestra versión, cobertura y aviso de validación pendiente; conocimiento vacío o fallo de CDN produce un estado explícito y legible, no cobertura falsa. |

Trazabilidad propuesta: RN-212, RN-209, RN-004/RN-G13 y RNF-16; depende de B y se entrega en C6.
La excepción global del conocimiento no concede soporte clínico al superadministrador.
B incorpora esta propuesta a HU/RE y matrices solo tras aprobación de la revisión de A.

### A.7. Documentos que cambiará B

| Documento | Cambio previsto, sin subir revisión durante B |
|---|---|
| `RequisitosEspecificos.md` | RE-2.11.1: exigir marca del catálogo o «Otro / no sé»; el texto nunca sustituye una marca; tiempo conocido o «No sé», y «No aplica» para preventivo; revisión si falta un dato decisivo; introducir RE-2.12.1–6 si A se aprueba. |
| `ReglasNegocio.md` | RN-424: solo marcas/contexto/tiempos calculan, nota opcional sin efecto; desconocido/no aplica explícitos; mantener RN-416 a RN-419 y RN-425; precisar RN-418 sin reconocimiento de texto libre automático. |
| `HistoriasUsuario.md` | Criterios HU-2.11 coherentes con captura y buscador; propuesta HU-2.12; revisar referencias RN y dependencias; no marcar implementados motores todavía. |
| `CasosTriage.md` | Sustituir «lo que escribe» como entrada del motor por marcas y datos de A.3; conservar relatos como explicación; T-22 «Otro / no sé»; T-23 búsqueda + confirmación; T-16 provisional con revisión y límite de la regla temporal; P6 sin inferencia desde nota. |
| `CasosTriage.md`, advertencia inicial | Reflejar la decisión 1 que permite construir/cargar conocimiento pendiente con fuentes, sin afirmar aprobación clínica; conservar la limitación especial T-20 y la validación veterinaria pendiente del conjunto. |
| `MER.md` y `database/modelo/drawdb_schema_v2.sql` | Entidades/columnas propuestas de A.2, retiro lógico, proveniencia, cobertura, reglas y captura temporal; coincidir con migración 07 y esquema consolidado. |
| `Modelos.md` | Actualizar §1 y §3 según A.8; agregar flujo de exploración C6; alinear §4 en lo relativo a entrada estructurada, sin implementar agenda de M4. |
| `ERS.md` y `FichaTecnica_Zooki.md` | Referencias/alcance del mapa y limitación clínica; conteos solo donde cambien por la HU/los RE nuevos; RF nuevo solo si el documento lo requiere, con número libre y aprobación. |
| Matrices y encabezados de HU/RE | Incorporar HU-2.12/RE-2.12.x, referencias RN, totales y Ficha Técnica coherentes; no renumerar ni quitar requisitos existentes. |

Las revisiones nuevas y regeneración de descargas se hacen una vez en F, como indica `agentes/metodo.md` §4.
No se modifica ninguno de esos documentos en A.
La parte del carnet de RE-2.1.5/RE-2.10.1 sigue siendo dependencia expresamente diferida del plan, no una aceptación que C1 pueda declarar completa en QR.

### A.8. Diferencias con diagramas y cierre de A

| Diagrama | Diferencia y ajuste previsto |
|---|---|
| `Modelos.md` §1, grafo con signo | El ejemplo binario no representa AND, tiempos, cobertura ni procedencia; añadir fuente/validación y reglas contextuales; mostrar una interacción fármaco–fármaco como advertencia, distinta de veto por toxicidad; no tratar todo signo negativo como bloqueo. |
| §1, ejemplo de fármacos | Las aristas ilustrativas y sus pesos no son semilla clínica aprobada; B debe respaldar lo que cargue o conservarlo solo como ejemplo explícito; ranking no implica recomendación automática de dosis o tratamiento. |
| `Modelos.md` §3, captura y diagnóstico | Marcas explícitas antes del ranking, sin impedir sugerencias hasta que ya exista diagnóstico; mantener diagnóstico final obligatorio y alternativa libre, validación junto al campo y ambas entradas de consulta. |
| §3, prescripción | Añadir rama de tratamiento protegido: evaluación interna, aviso genérico, conciliación documentada o autorización verificada, decisión auditada; distinguir bloqueo no confirmable de advertencia confirmable. |
| §3, guardado | Revalidar prescripción/alertas/versión antes del guardado atómico e incluir `consulta_sintomas`; error o advertencia no resuelta conserva formulario y no crea registros parciales; el disco mantiene su limpieza compensatoria. |
| §3, alertas y emergencia | Conservar alertas globales y contexto desconocido sin inventar datos; no ejecutar ahora la ficha provisional ni la derivación de agenda de M4; carnet sigue fuera de M2. |
| Nuevo flujo C6 | Contexto plataforma → permiso → cargar solo conocimiento global → vacío/error o mapa → buscar/filtrar → detalle con fuentes/validación; sin rama de edición ni acceso a pacientes. |
| §4, integración futura M4 | Entrada de catálogo/tiempos/contexto → motor → revisión/contagio/rojo antes de cuotas; nota libre fuera del cálculo; P7 y agenda siguen en M4. |

**Riesgos por orden de impacto:**

1. No hay validación veterinaria; la base no puede presentarse como clínicamente aprobada por pasar 26 pruebas.
   T-16 y T-20 requieren las limitaciones descritas; cantidades/tiempos/estados desconocidos no se inventan.
2. Leer toda la historia para obtener medicación filtraría datos ajenos; solo C3 tiene la excepción interna y la salida se filtra antes de responder.
3. Los enlaces existentes no bastan sin fechas finales, fármaco confirmado, reglas temporales y cobertura explícita; una etiqueta libre o una base vacía nunca equivale a seguridad.
4. P3 y P4 aplicados como sumas sucesivas sobre el resultado previo sobreclasificarían; el máximo se toma entre candidatos independientes y las alarmas no se rebajan.
5. Implementar solo el modal dejaría Atención fuera; C5 comparte los componentes y conserva atomicidad, HC y permisos.

**Verificación de A, sin cambiar pruebas:**

| Ejecución | Resultado |
|---|---|
| `php vendor/bin/phpunit` | Verde: 691 pruebas, 4231 aserciones; 31 pruebas MySQL omitidas por no definir el host, como en `main`. |
| Suite completa con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1`, puerto temporal 3309 y base predeterminada `zooki_test_base_v2` | Verde: 691 pruebas, 4603 aserciones, ninguna omitida; MariaDB 10.4.32 temporal propia, no la instancia/base de trabajo. |
| `node --test "tests/Frontend/**/*.test.cjs"` | Verde: 167 pruebas, ninguna fallida ni omitida. |
| Comparación con `main` y `git diff --check` | Sin diferencias de código/esquema/pruebas respecto de `main`; sin errores de espacios; solo plan M2 y `agentes/estado.md` modificados en el checkout. |

MySQL 8 se verifica en CI; la corrida local real fue con MariaDB, sin atribuirle un resultado de otro motor.
No se leyó ni escribió `.env`, ni se accedió a `zooki_v2_prueba` o sus contraseñas.
No hay pruebas nuevas ni cambios de código, esquema, versiones o descargas en esta etapa.
El usuario aprobó durante A las tres precisiones de §6; quedan para Claude la revisión del mecanismo de decisión 3, el diseño de migración/cargador/algoritmos y HU-2.12 antes de B.
La decisión de Gran danés y la reserva segura de categorías amplias quedó confirmada; su implementación corresponde a B.
No se empezó B.

**Comandos de commit para el usuario (PowerShell 5.1):**

```powershell
git add -- specs/M2-grafo-clinico.md agentes/estado.md
git diff --cached --stat
git commit -m "docs(grafo): inventario y diseno de M2 etapa A (HU-2.8, HU-2.9, HU-2.11)"
```

### A — Revisión (2026-10-14)

**Revisó Claude (arquitecto). Veredicto: aprobada con ajustes.** El código, el esquema y las pruebas no cambian respecto de `main` (solo el plan y el estado); la línea base de Codex (691 PHP, 167 JS) no requiere repetirse para una etapa de solo lectura.

**Lo que se aprueba tal cual:** inventario de la consulta con sus dos entradas (modal y Atención) y la unidad atómica de `Consulta::escribir`; la traducción de T-01 a T-26 (A.3); el contrato de los cuatro helpers puros (A.5) y la regla de tomar el máximo entre candidatos independientes; la lectura interna mínima de C3 para RN-211/RE-2.9.3; el tipo de nodo `sustancia`; el retiro lógico por código estable; Cytoscape.js con versión fija y SRI; HU-2.12 con RE-2.12.1–6; la lista de documentos de A.7 y los cambios de diagramas de A.8.

**Decisión 3 confirmada:** el mecanismo de §6 queda adoptado tal como lo concretan A.3, A.5.3 y A.5.4: el nivel sale solo de marcas, tiempos y paciente; «Otro / no sé», «No sé» y «No aplica» son respuestas explícitas; la nota no entra al helper.

**Ajustes obligatorios para B:**

1. **B se parte en B1 y B2.** B1: migración 07, esquema consolidado, MER, drawDB, Gran danés, validador, cargador, arranque y documentos de A.7, con un conjunto mínimo de prueba en `tests/`. B2: el conocimiento real con sus fuentes. Investigar y citar fuentes es otro trabajo que el esquema, y así cada revisión es manejable.
2. **Sin hashes por archivo en el manifiesto.** Git ya protege la integridad y editar un JSON obligaría a recalcular hashes a mano. El manifiesto lista los archivos esperados (un faltante sigue siendo error, nunca retiro masivo) y el cargador calcula un hash del conjunto completo que se usa como versión cargada y queda en auditoría.
3. **Validación sin columnas de persona:** `estado_validacion` sí; quién y cuándo validó viven en el archivo (no hay usuario del sistema que lo haga en la v2.0). Sin FK `validado_por`.
4. **Lenguaje de reglas acotado:** B1 enumera la lista cerrada de predicados de A.2 (ni uno más sin revisión), anidamiento máximo de dos niveles (`todos`/`alguno` dentro de `todos`/`alguno`) y el validador rechaza lo demás. El evaluador de condiciones es un único helper compartido; se escribe en C2 (primer motor que lo usa) y lo reutilizan C3 y C4.
5. **Reglas generales, no por caso:** ninguna regla puede nombrar un caso ni una raza solo para aprobar la batería; se expresan por atributo con fuente (por ejemplo, «raza gigante» en los metadatos de la raza, no «Gran danés»). La revisión de B2 y C4 rechaza reglas hechas a la medida de un caso.
6. **Ranking (C2):** además de la suma, cada candidato informa la cobertura («coincide X de Y síntomas característicos»), que sirve de segundo criterio de orden; se muestran como máximo 5 candidatos con puntuación positiva.
7. **Arranque:** `docker/iniciar.sh` ejecuta el cargador solo si `migrar.php` terminó bien; Apache arranca igual en ambos casos y el error queda en el log con el prefijo `[conocimiento]`.
8. **Alcance de B2:** lo que exige la batería (A.3) y los casos P1 adicionales, más un núcleo común de perros y gatos (del orden de 15 diagnósticos frecuentes y 15 fármacos o sustancias con toxicidad documentada). Cada elemento con fuente específica y URL verificada; si un dato no tiene fuente suficiente, se informa y no se carga.

**Verificación de esta revisión:** `git diff main` sin cambios fuera de `specs/` y `agentes/`; numeración HU-2.12 y RE-2.12.x libre; RE-2.9.3 y RN-211 ya exigen la conciliación que propone A.5.2; `tratamientos.fecha_fin` e `id_nodo_farmaco` existen en el esquema; `docker/iniciar.sh` hoy arranca Apache aunque falle la migración, como señala A.4.

**Siguiente:** B1 (Codex).

## Anexo B1 — Resultado

**Lo escribió Codex (2026-10-14); revisión independiente: Claude (arquitecto).**
**Estado: entregada, pendiente de revisión.**

### Qué se entregó

- Migración `07_grafo_conocimiento.sql` y esquema consolidado coherentes con MER y drawDB: códigos estables, activo, SHA-256 de 64 caracteres, fuentes, validación, sustancia, sinónimos, reglas y contexto JSON de citas/síntomas.
  No se agregaron columnas `validado_por` ni `fecha_validacion`; esas evidencias permanecen en el archivo.
- Gran danés con ID 55 en semilla y migración, sin sobrescribir filas existentes; el tamaño de raza se carga en B2 como conocimiento con fuente.
  Los conceptos anteriores a 07 conservan su tipo, ID y referencias; quedan inactivos con prefijo reservado `legado.` si no tenían procedencia/código.
- `ValidadorConocimiento`, puro, con los nueve predicados enumerados en el README, máximo dos niveles de grupos, referencias existentes, fuentes obligatorias y bloqueo solo por toxicidad/alergia con fundamento clínico explícito.
  No hay evaluador de condiciones ni motores en B1.
- `ArchivosConocimiento` valida una instantánea completa y calcula su hash; `CargadorConocimiento` escribe solo tablas globales con candado/transacción, conserva IDs, retira lógicamente, recupera IDs al reintroducir y ofrece `--revisar` sin escritura.
  La auditoría conserva el hash completo; no se reutiliza un código para otro tipo o taxón.
- Arranque: carga únicamente si `migrar.php` terminó bien; mensajes `[conocimiento]`, conservación transaccional del conjunto previo ante error y arranque de Apache en ambas ramas.
- `database/conocimiento/` contiene manifiesto sin hashes manuales, cinco listas vacías válidas y README del contrato.
  El conjunto sintético está solo en `tests/Fixtures/conocimiento/`; el contenido clínico es B2.
- Captura, limitaciones de los 26 casos, HU-2.12/RE-2.12.1–6, matrices y conteos documentados: 96 HU, 427 RE, 52 entidades.
  Modelos §1/§3/§4 y nuevo §14 coherentes con el diseño; 27 diagramas de MER/Modelos renderizados con Mermaid 11.4.1.
  Se conservaron las revisiones actuales y no se regeneraron descargas.

### Trazabilidad y pruebas

| RE/RN o decisión | Evidencia B1 | Lo que sigue pendiente |
|---|---|---|
| RN-212; ajustes 1–4 de A | `ValidadorConocimientoTest`: conjunto válido/vacío, nueve predicados, campos/predicados desconocidos, tercer nivel, referencias, fuentes y bloqueos inválidos. | Contenido verificable de B2 y evaluador único de C2. |
| RN-212; identidad y retiro lógico A.4 | `CargadorConocimientoTest`: segunda carga sin escrituras/auditoría, etiqueta con mismo ID, retiro/reintroducción, simulación sin escritura, faltante/JSON/referencia/taxonomía inválidos sin cambios, rollback por fallo de escritura e identidad taxonómica estable. | Fuentes clínicas y revisión del conjunto real en B2. |
| Migración 07, FK, Gran danés | `BaseV2MysqlTest::testB1Migracion07ActualizaElEsquemaAnteriorDosVecesSinCambiarIds`, `testB1CargadorRealIdempotenteConCandadoYRevisarSinEscritura`, `testB1TaxonomiaConFKYTiposCoherentes`: actualización real y DDL estable, instalación consolidada más 07 dos veces, integridad y CLI con éxito/error. | MySQL 8 en CI; la verificación local real fue MariaDB 10.4.32. |
| RE-2.11.1, RN-424/RN-418; decisión 3 | HU, RE, RN, CasosTriage y Modelos expresan marcas confirmadas y tiempos explícitos, sin cálculo por nota; T-16/T-20/T-22/T-23 conservan sus limitaciones. | C4 prueba el cálculo y M4 implementa captura/agenda; B1 no declara aprobado clínicamente el triage. |
| RE-2.12.1–6 | Numeración y matrices incorporadas; RF-2.7 ampliado sin inventar un RF adicional; flujo §14 renderizado. | Pantalla, endpoint, permisos y pruebas de C6; la HU sigue planificada. |
| Ajustes 5, 6 y 8 de A | Contrato sin reglas por caso ni predicado de raza individual; C2 mantiene ranking por peso/cobertura con máximo 5 positivos; B2 mantiene la batería/P1 y núcleo aproximado de 15 diagnósticos y 15 fármacos/sustancias con fuentes. | Su implementación corresponde a B2/C2–C4, no se adelantó. |
| Ajuste 7 de A | Condicional explícito en `docker/iniciar.sh`; CLI probado con `--revisar` y salida no cero ante error. | Comprobación del arranque del contenedor por el usuario; no se levantó Docker durante B1. |

### Verificación

- Suite completa sin host MySQL: **703 pruebas, 4284 aserciones, 34 omitidas** por no habilitar ese motor.
- Suite completa con `ZOOKI_TEST_MYSQL_HOST`: **703 pruebas, 4692 aserciones, ninguna omitida**, sobre MariaDB 10.4.32 temporal propia y la base predeterminada `zooki_test_base_v2`.
- JavaScript: **167/167** en verde, sin código JS modificado.
- `git diff --check` sin errores; conteos documentales comprobados y 27 diagramas renderizados.
- No se leyó ni escribió `.env`; no se accedió a `zooki_v2_prueba` ni se regeneraron contraseñas.
  No se hizo commit, push ni se empezó B2.

### Cómo comprobarlo en local

**Docker local:** después de reconstruir/iniciar el servicio, comprobar en los logs que 07 se aplica antes de `[conocimiento] Carga terminada` y que Apache arranca.
Un reinicio debe mostrar migraciones al día y una carga con cero altas/cambios/retiros para los mismos archivos.
El hash cargado aparece en la línea del cargador y en `auditoria_sistema.datos_nuevos.version`.

```powershell
docker compose up -d --build web
docker compose logs --tail=150 web
docker compose restart web
docker compose logs --tail=150 web
```

**XAMPP:** encender Apache no ejecuta `docker/iniciar.sh`.
Para reproducir por consola el mismo orden sobre la base local del usuario:

```powershell
php scripts/migrar.php
if ($LASTEXITCODE -eq 0) {
    php scripts/cargar_conocimiento.php
}
php scripts/cargar_conocimiento.php --revisar
```

La carga de B1 es vacía deliberadamente: cero nodos no demuestra cobertura ni éxito de un motor clínico.
F comprobará de nuevo el arranque y las descargas al publicar el módulo.

### Commit para el usuario — PowerShell 5.1

```powershell
git add -- database/01_schema.sql database/02_semilla.sql database/07_grafo_conocimiento.sql database/modelo/drawdb_schema_v2.sql database/conocimiento
git add -- helpers/ArchivosConocimiento.php helpers/ValidadorConocimiento.php models/CargadorConocimiento.php scripts/cargar_conocimiento.php docker/iniciar.sh
git add -- tests/Fixtures tests/Unit/ValidadorConocimientoTest.php tests/Integration/CargadorConocimientoTest.php tests/Integration/BaseV2MysqlTest.php tests/Support/ConocimientoPrueba.php
git add -- documentacion/MER.md documentacion/Modelos.md documentacion/HistoriasUsuario.md documentacion/RequisitosEspecificos.md documentacion/ReglasNegocio.md documentacion/CasosTriage.md documentacion/ERS.md documentacion/FichaTecnica_Zooki.md specs/M2-grafo-clinico.md agentes/estado.md
git diff --cached --stat
git commit -m "feat(grafo): esquema y cargador de conocimiento B1 (RN-212, RN-424, HU-2.12)"
```

### B1 — Revisión (2026-10-14)

**Revisó Claude (arquitecto). Veredicto: aprobada; dos correcciones pasan al inicio de B2** (las escribe Codex, que también escribe B2, sobre el validador de B1).

**Verificación propia:** suite completa en una copia limpia: sin MySQL 703 pruebas y 4284 aserciones (34 omitidas); con MariaDB 10.11 propia y la base por defecto, 703 pruebas y 4692 aserciones, ninguna omitida; JS 167/167. La prueba real corre la migración 07 dos veces sobre el esquema anterior.

**Lo que está bien:** 07 repetible y con FK y CHECK de taxonomía; los nodos anteriores quedan como `legado.` inactivos sin perder referencias; Gran danés con ID 55 sin sobrescribir; cargador con candado, transacción, retiro lógico, recuperación de ID, `--revisar` y versión por hash del conjunto (ajuste 2); validador puro con lista cerrada y dos niveles (ajuste 4); reglas que nombran un caso T-nn se rechazan (ajuste 5); `docker/iniciar.sh` solo carga si la migración salió bien (ajuste 7); conocimiento vacío en `database/` y conjunto sintético en `tests/`. Documentos (RE-2.11.1, RN-418, RN-424, HU-2.12, RE-2.12.1–6, CasosTriage) coherentes con la decisión 3.

**Hallazgos (corregir al inicio de B2):**

1. **Faltan predicados para expresar la batería** (`helpers/ValidadorConocimiento.php`, `PREDICADOS`; el hueco viene de la lista de A.2 y no lo vio la revisión de A). Con los nueve actuales no se pueden escribir:
   - T-08, T-14, T-15 y T-16: la exposición a una sustancia o fármaco **sin tiempo conocido**. `minutos_desde_exposicion` exige un intervalo, y con tiempo desconocido daría revisión en vez de 🔴 en T-08 y T-15. Falta `exposicion` (`codigo` de sustancia o fármaco).
   - T-11: la vacunación incompleta (P4). Falta `vacunacion` con valor `completa`, `incompleta` o `desconocida`.
   - T-06: el tiempo desde el último celo (P4). Falta `dias_desde_celo` (intervalo en días).

   Se agregan esos tres al validador, al README y a sus pruebas; con eso la lista cerrada queda en doce. Antes de cargar, B2 comprueba que cada caso de A.3 se puede expresar con ellos y lo deja en una tabla.
2. **Mensajes de error sin ubicación:** «Campo desconocido.» o «Referencia a código inexistente.» no dicen en qué archivo ni en qué elemento. Con cientos de elementos, B2 y cualquier corrección futura a mano serían a ciegas. Cada error del validador nombra el archivo y el código del elemento (y el campo cuando aplica).

**Pendientes anotados para F:** AGENTS.md todavía dice alpha.2 y «50 tablas» (ahora 52); en XAMPP la carga no corre sola: después de `php scripts/migrar.php` hay que ejecutar `php scripts/cargar_conocimiento.php`.

**Siguiente:** B2 (Codex), empezando por las dos correcciones.

## Anexo B2 — Resultado

**Estado: entregada, pendiente de revisión (2026-10-14).** Revisión independiente: Claude (arquitecto).

**Quién escribió cada parte:**

| Parte | Autor |
|---|---|
| Correcciones de la revisión de B1: predicados `exposicion`, `vacunacion` y `dias_desde_celo` (doce en total); errores del validador con archivo, código y campo; README de los predicados; sus pruebas en `ValidadorConocimientoTest`; tabla de cobertura B2.1 | Codex |
| Investigación y verificación de fuentes; los seis JSON de `database/conocimiento/`; `ConocimientoRealTest`; prueba MySQL `testB2ConocimientoRealSeCargaDosVecesSinCambios`; ajuste de una prueba de Codex y del README (abajo); verificación y este anexo | Claude Code |

### B2.1 — Tabla de cobertura (escrita por Codex)

Tabla redactada antes de escribir el conjunto real.
La tabla expresa datos y candidatos independientes; comprobar sus códigos no sustituye ejecutar la batería en C4 ni validarla clínicamente.
En esta tabla se omite el prefijo `sintoma.` en los síntomas y `triage.` en las reglas.

| Caso | Nodos necesarios | Regla o atributo que lo expresa; esperado provisional |
|---|---|---|
| T-01 | `dificultad_respiratoria`, `encias_azuladas` | Ambas alarmas universales rojas; máximo P1. |
| T-02 | `convulsion_en_curso` | Alarma universal roja, sin exigir un tiempo mínimo. |
| T-03 | `traumatismo`, `hemorragia_incontrolable` | Alarma universal roja prevalece sobre trauma naranja. |
| T-04 | `intentos_orinar_minimo`, `dolor_orinar`, `especie.felino` | `urinario_macho`: combinación + macho → rojo. |
| T-05 | `arcadas_improductivas`, `abdomen_distendido`, `inquietud`, `raza.gran_danes` | `abdomen_gigante`: atributo gigante, no raza individual → rojo. |
| T-06 | `sed_aumentada`, `decaimiento`, `secrecion_genital`, `especie.canino` | `reproductivo_poscelo`: hembra entera + intervalo desde celo → naranja; la ventana exige fuente clínica. |
| T-07 | `traumatismo` | Nivel naranja del síntoma; caminar no lo cancela. |
| T-08 | `sustancia.lilium`, `especie.felino` | `lilium_felino`: exposición sin exigir hora → rojo; misma arista de toxicidad que prescripción. |
| T-09 | `respiracion_ruidosa_ejercicio`, `cansancio_ejercicio`, `exposicion_calor`, `raza.bulldog_frances` | `ejercicio_braquicefalo`: atributo braquicéfalo → naranja. |
| T-10 | Los tres síntomas anteriores, `raza.labrador` | Candidatos amarillos, sin atributo braquicéfalo verdadero; cansancio de ejercicio no es decaimiento sistémico. |
| T-11 | `vomito`, `diarrea_sangre`, `no_come`, `especie.canino` | `digestivo_cachorro`: menor de 6 meses + vacunación incompleta → naranja; `contagio_digestivo` separado. |
| T-12 | `vomito`, `diarrea`, `especie.canino` | Candidatos amarillos; número de episodios, actividad y vacunas conservados sin inventar alarmas. |
| T-13 | `no_come`, `se_esconde`, `especie.felino` | `anorexia_felina_sostenida`: tiempo de anorexia → naranja; obesidad queda como contexto aprobado, sin predicado nuevo. |
| T-14 | `sustancia.posible_xilitol`, `especie.canino` | `posible_xilitol`: posible exposición → naranja con revisión; el tiempo conocido se conserva, sin afirmar ingrediente. |
| T-15 | `farmaco.paracetamol`, `especie.felino`, `fiebre` | `paracetamol_felino`: exposición → rojo aunque no haya hora; toxicidad explícita. |
| T-16 | `sustancia.chocolate`, `especie.canino` | `chocolate_tardio`: intervalo temporal → amarillo provisional con revisión; no regla general de seguridad ni descuento de otras alarmas. |
| T-17 | `vomito_repetido`, `no_bebe`, `decaimiento`, `encias_secas` | `digestivo_compromiso`: combinación moderada con compromiso → naranja; P3 solo escala una vez en C4. |
| T-18 | `primera_convulsion_resuelta` | Nivel naranja; distinta de la convulsión activa roja. |
| T-19 | `convulsion_habitual_resuelta`, `condicion.epilepsia` | `crisis_habitual_documentada`: alerta global de epilepsia → amarillo; no se infiere el fármaco. |
| T-20 | `no_come`, `no_defeca`, `especie.roedor` | `apetito_sin_heces`: revisión inmediata; P6 mantiene amarillo sin cobertura específica, sin inferir especie clínica. |
| T-21 | `dificultad_respiratoria`, `plumaje_erizado`, `especie.ave` | P1 rojo aun sin cobertura específica; búsqueda ayuda, no interpreta ni marca sola. |
| T-22 | `otro_no_se` | `reserva_otro`: amarillo con revisión; nota no calcula. |
| T-23 | `vomito_sangre`, `decaimiento` | `digestivo_compromiso`: naranja; sinónimos «bomita sangre» y «desaido», confirmados por la persona. |
| T-24 | `control_preventivo` | `preventivo`: verde, tiempo no aplica; P1 siempre prevalece. |
| T-25 | `picor_oidos_leve_estable` | `oidos_estable`: ventana de estabilidad → verde; no aplica a cualquier dolor ótico. |
| T-26 | `cojera_leve_estable` | `cojera_estable`: ventana crónica estable → verde; no aplica a trauma ni falta de apoyo. |
| P1 adicional | `encias_palidas` | Alarma universal roja. |
| P1 adicional | `colapso` | Alarma universal roja. |
| P1 adicional | `imposibilidad_orinar` | Alarma universal roja, distinta de frecuencia urinaria aislada. |
| P1 adicional | `golpe_calor` | Alarma universal roja, distinta de mera exposición a calor. |
| P1 adicional | `parto_esfuerzo_sin_cria` | B2.1: «más de 30 minutos o no sé» es alarma universal roja sin exigir tiempo adicional; `parto_prolongado` se retira lógicamente. |
| Parto breve | `parto_esfuerzo_menos_30` | B2.1: «menos de 30 minutos» es naranja con revisión mediante `parto_menos_30`; el buscador sugiere ambas marcas y la persona confirma. |

No se necesitan predicados adicionales para esta representación con la decisión del usuario sobre T-13/T-19.
Los tiempos ausentes siguen ausentes; su evaluación incompleta corresponde a C2–C4.

### B2.2 — Conocimiento cargado (Claude Code)

Todo el conjunto está `pendiente`; cada elemento cita su fuente con localizador (sección, apartado o frase) y fundamento.
Claude Code declaró abiertas y verificadas las 46 URL clínicas y de política de B2 el 2026-10-14; ninguna es una portada.
La fuente adicional de B2.1 es la decisión del usuario conservada en este plan, consultado localmente por Codex; su URL identifica el archivo que se publicará con el commit.
Fuentes clínicas: MSD Veterinary Manual (profesional y para propietarios), Cornell Feline Health Center y Riney Canine Health Center, ASPCA Animal Poison Control, FDA (consumidor y CVM), WSU (MDR1), University of Cambridge (BOAS) y un estudio del RVC en PubMed Central.
Fuentes de política: `ReglasNegocio.md` y `CasosTriage.md` del repositorio público en `main` (los colores de triage); los pesos del ranking citan RN-210 como `heuristico`.

| Tipo | B2 aprobada | Conjunto actualizado por Codex en B2.1 |
|---|---|---|
| Fuentes | 46 | 47 (se agrega la política aprobada por el usuario) |
| Nodos | 104: 50 síntomas, 17 diagnósticos, 12 fármacos, 8 sustancias, 10 razas, 6 especies, 1 condición | 105: 51 síntomas; los demás tipos conservan su cantidad |
| Aristas | 105: 75 síntoma→diagnóstico, 12 toxicidades con bloqueo, 6 toxicidades con advertencia, 12 interacciones | 105, sin cambios |
| Sinónimos | 196 | 201 |
| Reglas | 25: 20 de triage, 5 modificadores del ranking | 29: 24 de triage y 5 de ranking (5 altas y 1 retiro) |

- **P1 en B2:** ocho señales eran síntomas con `alarma_universal` y nivel rojo (política), cada una con la fuente clínica que la marca como urgencia (MSD, triage inicial).
  B2.1 agrega el parto de más de 30 minutos o tiempo desconocido como novena marca universal roja y retira `triage.parto_prolongado`.
- **Bloqueos (12), todos `toxico_para` con fundamento clínico explícito:** lirio→gato; chocolate, xilitol, uvas/pasas, macadamia→perro; cebolla/ajo y etilenglicol→perro y gato; paracetamol, permetrina y amitraz→gato.
  Lo que la fuente presenta como dependiente de la exposición o como «no recomendado» queda en advertencia: paracetamol en perro, ibuprofeno en perro y gato, aspirina y meloxicam en gato, y loperamida en Pastor Alemán (MDR1 en ~10 %; solo la prueba individual lo confirma).
- **Interacciones (12, advertencia):** AINE con prednisona (meloxicam, carprofeno, aspirina, ibuprofeno), AINE con AINE (seis pares) y tramadol con selegilina o fluoxetina.
- **Núcleo común:** 17 diagnósticos de perros y gatos (gastritis aguda, gastroenteritis hemorrágica, parvovirosis, panleucopenia, dilatación-torsión gástrica, obstrucción uretral felina, cistitis idiopática felina, piometra, lipidosis hepática felina, epilepsia idiopática, síndrome braquicefálico, golpe de calor, otitis externa, osteoartritis, enfermedad renal crónica, diabetes mellitus y tos de las perreras), con condiciones de especie, sexo o raza en la arista cuando la fuente las limita.
- **Razas, por atributo:** Gran danés gigante; Labrador, Golden y Pastor Alemán grandes (MSD: «retrievers and shepherds»); Bulldog Francés, Bulldog Inglés, Pug, Shih Tzu, Boxer y Persa braquicéfalos; Labrador `braquicefalo: false` (mesocéfalo en el estudio del RVC).
- **Cobertura:** Canino y Felino parcial en los tres motores; Roedor, Ave, Reptil y Exótico sin cobertura.
- **Buscador:** 196 sinónimos con formas habladas en Colombia y errores de dictado («bomita», «desaido», «trasboca», «aguado», «renquea», «rasquiña», «lo cogió un carro», «chocolatina»…); cada marca de A.3 se encuentra por etiqueta o sinónimo normalizado.
  Su fundamento es política (RN-424 y la batería): solo sugieren y la persona confirma.

**Ventanas temporales, todas con fuente:** anorexia felina ≥ 24 horas (Cornell, gato adulto); 28–42 días desde el celo (MSD: piometra «4 to 6 weeks after estrus»); chocolate ≥ 12 horas → amarillo con revisión (MSD: signos habituales entre 6 y 12 horas, hasta 72; no declara seguridad); parto > 30 minutos (MSD distocia).

**Dónde la implementación precisa la tabla B2.1 (sin renombrar códigos):**

1. `triage.abdomen_gigante` acepta tamaño grande **o** gigante, como dice RN-416/P4 («razas grandes o gigantes con abdomen distendido») y MSD (DTG en razas grandes y gigantes); B2.1 decía solo gigante.
2. `triage.oidos_estable` y `triage.cojera_estable` no usan una ventana de tiempo: ninguna fuente fija cuántos días hacen «estable».
   La estabilidad y la leveza están en la marca que la persona confirma; la regla da verde solo con esa marca y nunca se aplica a trauma o cojera aguda, que son otras marcas.
3. `triage.digestivo_cachorro` y los dos avisos de contagio aceptan vacunación `incompleta` o `desconocida`: ante la duda sube (P6), sin inventar el dato.
4. `triage.urinario_macho` exige gato macho con intentos de orinar con salida mínima; el dolor no es requisito (la fuente marca la obstrucción como emergencia aunque no haya quejido).
5. `triage.digestivo_compromiso` exige un síntoma digestivo moderado con decaimiento o encías secas (P3); «no bebe» no cuenta como deshidratación.
6. Se agregó `triage.contagio_digestivo_felino` (panleucopenia, Cornell), análogo felino del aviso de contagio de RN-425.

### B2.3 — Lo que no se cargó por falta de fuente

| Elemento | Qué faltó |
|---|---|
| Nivel de triage para la exposición a otros tóxicos y chocolate reciente (< 12 h) | B2 lo dejó fuera por falta de política de color; decisión 1 aprobada en la revisión y aplicada en B2.1: mínimo naranja por especie y etilenglicol rojo. |
| Dilatación-torsión gástrica en perro de tamaño medio o desconocido | B2 lo dejó fuera por falta de política; decisión 2 aplicada en B2.1: arcadas improductivas y abdomen distendido implican naranja sin exigir tamaño. |
| Umbrales geriátricos por especie o raza (P4) | No se buscó ni encontró una fuente que fije la edad por raza; sin regla geriátrica (decisión de A: no inventar). |
| Anorexia de gatitos (Cornell: 12 horas antes de 6 semanas) | `edad_meses` no expresa 6 semanas sin redondear; no se cargó. |
| Tamaño o braquicefalia de Rottweiler, Dóberman, Chihuahua y el resto de la semilla | Ninguna regla los necesita o la fuente no los nombra; quedan sin atributo. |
| Ivermectina en razas MDR1 | WSU la da como segura a dosis de etiqueta en un producto; depende de la dosis, no se cargó como toxicidad. |
| Selegilina con amitraz | La página de MSD consultada no lo afirma. |
| Verificación de los regionalismos en el DLE | `dle.rae.es` respondió 403; los sinónimos quedan con fundamento de política, no de diccionario. |

Ningún caso de la tabla B2.1 quedó sin expresar.

### B2.4 — Cambios sobre lo que dejó Codex

- `ValidadorConocimientoTest::testConjuntoSinteticoYVacioSonValidos` pasa a `testConjuntoSinteticoYRealSonValidos`: exigía que `database/conocimiento` siguiera vacío (estado de B1), lo que B2 cambia por diseño; conserva la validación de ambos conjuntos.
- `database/conocimiento/README.md`: el párrafo inicial decía que los archivos estaban vacíos; ahora describe el conjunto de B2, sus fundamentos y cómo corregirlo.
- `helpers/ValidadorConocimiento.php` no se cambió: se revisó el diff de Codex (doce predicados, ubicación de errores) y no se encontró error.

### B2.5 — Pruebas y verificación

| Exigencia del prompt | Prueba |
|---|---|
| El conjunto real pasa el validador | `ConocimientoRealTest::testElConjuntoRealPasaElValidadorYNoEstaVacio` y `ValidadorConocimientoTest::testConjuntoSinteticoYRealSonValidos` |
| Cada fila de B2.1 tiene sus códigos | `testCadaFilaDeLaTablaB2TieneTodosSusCodigos` (lee los archivos, sin motor) |
| Núcleo de ~15 diagnósticos y ~15 fármacos o sustancias, interacciones con fuente | `testNucleoComunDePerrosYGatos` |
| Ningún bloqueo sin fuente clínica | `testNingunBloqueoSinFuenteClinicaDeToxicidad` |
| Ninguna regla nombra un caso; reglas por atributo | `testNingunElementoNombraUnCasoYLasReglasUsanAtributosDeRaza`, `testNingunaReglaRebajaNiDejaVerdeUnaAlarma` |
| Fuentes con URL https específica y fecha; todo pendiente | `testFuentesConUrlHttpsEspecificaYFechaDeConsulta` |
| Sin dosis ni pautas | `testSinDosisNiPautasDeTratamiento` |
| Cobertura por especie y taxonomía de la semilla | `testCoberturaPorEspecie`, `testRazasYEspeciesExistenEnLaSemilla` |
| Cada síntoma de A.3 se encuentra | `testCadaSintomaDeA3SeEncuentraPorEtiquetaOSinonimo` (con `ArchivosConocimiento::normalizarTexto`) |
| Carga dos veces con cero cambios (MySQL) | `BaseV2MysqlTest::testB2ConocimientoRealSeCargaDosVecesSinCambios` |

**Verificación (Claude Code, 2026-10-14):**

- Suite completa sin host MySQL: **717 pruebas, 6060 aserciones, 35 omitidas** (las de MySQL).
- Suite completa con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1` y la base por defecto `zooki_test_base_v2` (MariaDB 10.4.32 de XAMPP): **717 pruebas, 6475 aserciones, ninguna omitida**.
- JavaScript: **167/167** en verde, sin JS modificado.
- `php scripts/cargar_conocimiento.php` sobre `zooki_test_base_v2` recién creada con 01, 02 y 07: `--revisar` → 430 altas, 0 cambios, 0 retiros, sin escribir; carga → 430 altas; segundo `--revisar` → 0 altas, 0 cambios, 0 retiros.
  Altas por tipo en la base: nodos 50 síntomas, 17 diagnósticos, 12 fármacos, 8 sustancias, 10 razas, 6 especies, 1 condición; aristas 75 `sugiere`, 12 `toxico_para`/bloqueo, 6 `toxico_para`/advertencia, 12 `interactua_con`; 196 sinónimos; reglas 20 de triage y 5 de ranking.
- `git diff --check` sin errores. No se leyó ni escribió `.env`, no se tocó `zooki_v2_prueba` ni sus contraseñas, no se hizo commit ni se empezó C1 ni C2.
- Hallazgo de B2 corregido en B2.1: el hash de bytes sin normalizar variaba entre Windows y Linux; ahora CRLF y LF producen la misma versión.

### B2.6 — Pendientes

1. Colores de tóxicos y DTG sin tamaño: decisión aprobada en la revisión de B2 y aplicada en B2.1.
2. Parto con tiempo «No sé»: decisión aprobada en la revisión de B2 y aplicada en B2.1 con dos marcas; la desconocida es roja universal.
3. C4 decide cómo trata una raza sin el atributo que pide una regla (desconocido → revisión, según A.2); el Labrador ya declara `braquicefalo: false`.
4. Validación veterinaria del conjunto completo (decisión 1): sigue pendiente.

### B2 — Revisión (2026-10-14)

**Revisó Claude (arquitecto). Veredicto: aprobada; correcciones en B2.1.**

**Verificación propia:** suite completa en una copia limpia: sin MySQL 717 pruebas y 6060 aserciones (35 omitidas); con MariaDB 10.11 propia y la base por defecto, 717 pruebas y 6475 aserciones, ninguna omitida; JS 167/167.

**Lo que está bien:** las correcciones de B1 hechas por Codex (doce predicados y errores con ubicación) y la tabla B2.1; 12 bloqueos, todos `toxico_para` con fundamento clínico; lo dudoso queda en advertencia (ibuprofeno, loperamida en Pastor Alemán); reglas por atributo, ninguna baja un nivel; 196 sinónimos coloquiales que cubren A.3; lo que no tuvo fuente quedó fuera y anotado en B2.3. No se pudieron abrir URL de muestra desde la revisión; queda la comprobación declarada por Claude Code.

**Decisiones del usuario (2026-10-14), sobre los pendientes de B2.6:**

1. Exposición a una sustancia o fármaco con toxicidad documentada (arista `toxico_para`) para la especie del paciente: nivel mínimo 🟠; el etilenglicol da 🔴. El chocolate entra en ese mínimo solo con exposición de menos de 12 horas; con 12 horas o más, o tiempo desconocido, sigue `triage.chocolate_tardio` (🟡 con revisión).
2. Arcadas improductivas con abdomen distendido en un perro sin tamaño grande o gigante conocido: 🟠; con tamaño grande o gigante sigue 🔴.
3. Parto con esfuerzo sin cría y tiempo «No sé»: 🔴. Se expresa con dos marcas: «más de 30 minutos o no sé» (alarma universal roja) y «menos de 30 minutos» (🟠 con revisión, recomendación del revisor).

**Hallazgo técnico:** la versión es el SHA-256 de los bytes; con `autocrlf` en Windows da otra versión que en Linux. Se normalizan los finales de línea antes del hash.

**Siguiente:** B2.1 (Codex, por decisión del usuario sobre el uso disponible).

## Anexo B2.1 — Resultado

**Lo escribió Codex (2026-10-14); entregada, pendiente de revisión de Claude (arquitecto).**
Se construyó sobre el trabajo de B2 sin commit: Codex inició el validador y la tabla; Claude Code terminó el conocimiento, sus pruebas y su anexo.

### Qué y trazabilidad

| Cambio aprobado | Evidencia |
|---|---|
| Decisión 1; RN-416/RN-418, preparación de RE-2.11.2–5 | `ConocimientoRealTest::testCadaToxicoDeEspecieTieneMinimoNaranjaConSuFuente`: cada `toxico_para` con origen especie está en su grupo naranja y conserva sus fuentes clínicas; chocolate solo con tiempo `< 720`. |
| Etilenglicol rojo; conservar los rojos existentes y el amarillo provisional | `testEtilenglicolRojoEnAmbasEspeciesYLiriosYParacetamolConservanRojo` y `testChocolateNoTieneCandidatoSuperiorAAmarilloCon720MinutosOMas`; no se cambió `triage.chocolate_tardio`. |
| Decisión 2; abdomen canino sin tamaño | `testAbdomenCaninoNaranjaNoExigeTamanioYGiganteConservaRojo`: nueva regla naranja sin atributo; regla roja de tamaño grande/gigante conservada. |
| Decisión 3; RN-417/P1 | `testPartoTieneDosMarcasYTiempoDesconocidoEsAlarmaUniversal`: marca existente roja universal, nueva marca menor de 30 minutos naranja con revisión; cinco sinónimos añadidos, sin selección automática. |
| Retiro lógico, RN-212 | `BaseV2MysqlTest::testB21RetiraPartoProlongadoSinBorrarloNiCambiarSuId`: simular no escribe; recargar desactiva la regla antigua sin borrar ni cambiar su ID; repetir da cero cambios. |
| Hash entre Windows y Linux, RN-212 | `ValidadorConocimientoTest::testVersionIdenticaConFinalesCrLfYLf`: todos los JSON con CRLF/LF producen la misma versión; un cambio de etiqueta sí cambia el hash. |

Se actualizaron el README, la tabla y los conteos del Anexo B2, P1 y la nota de parto de `CasosTriage.md` y el texto de RN-417 para reflejar la decisión ya aprobada, sin cambiar número ni conteos de reglas documentales.
`.gitattributes` conserva sus reglas anteriores y agrega finales LF a los JSON del conocimiento.
Los diagramas no cambian: no hay esquema, motor, ruta ni pantalla nuevos.

**Conjunto actual:** 47 fuentes; 105 nodos (51 síntomas, 17 diagnósticos, 12 fármacos, 8 sustancias, 10 razas, 6 especies y 1 condición); 105 aristas; 201 sinónimos; 29 reglas (24 de triage y 5 de ranking).
B2.1 agrega 1 nodo, 5 sinónimos y 5 reglas; retira 1 regla.
El código estable de la marca roja de parto se conserva.

### Verificación

- PHP completo sin host: **724 pruebas, 6200 aserciones, 36 omitidas** (MySQL).
- PHP completo con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1`, puerto 3309 e instancia temporal propia MariaDB 10.4.32: **724 pruebas, 6624 aserciones, ninguna omitida**; base por defecto `zooki_test_base_v2`.
  No se ejecutó contra MySQL 8 en este equipo.
- JS: **167/167**, sin cambios de JS.
- Carga repetida del conjunto real en MySQL/MariaDB: cero altas, cambios y retiros en la segunda ejecución (`testB2ConocimientoRealSeCargaDosVecesSinCambios`).
- CLI real sobre una base temporal con B2 cargado: se reconstruyó la instantánea de B2 revirtiendo únicamente el delta de B2.1 en una copia de prueba, con 430 elementos activos y la regla antigua de parto.
  `php scripts/cargar_conocimiento.php --revisar` informó **11 altas, 429 cambios, 1 retiro**; `solo_revisar: true`.
  Se comparó la instantánea de las cuatro tablas del grafo y su auditoría antes/después: idéntica.
  Los 429 cambios incluyen la actualización de `version_conocimiento` de todas las filas existentes: no representan 429 cambios clínicos.
- `git diff --check` sin errores de espacios; sin acceso a `.env` ni a la base de trabajo, sin modificar contraseñas.
  Sin commit, push ni inicio de C1/C6.

### Pendientes

Todos los elementos continúan `pendiente` de validación veterinaria.
Los colores añadidos son política del usuario con fundamento separado de la toxicidad clínica.
T-16 sigue amarillo provisional con revisión; el tiempo no demuestra seguridad ni rebaja otras alarmas.
El cálculo efectivo de la batería y la combinación por máximo se verifican en C4; estas pruebas comprueban declaraciones y carga, sin adelantar el evaluador.
Revisiones documentales, descargas y versión de la aplicación se cierran en F.

### Commit de TODO B2 y B2.1 — PowerShell 5.1

```powershell
git add -- .gitattributes agentes/estado.md database/conocimiento/README.md database/conocimiento/manifiesto.json database/conocimiento/fuentes.json database/conocimiento/nodos.json database/conocimiento/aristas.json database/conocimiento/sinonimos.json database/conocimiento/reglas.json
git add -- helpers/ArchivosConocimiento.php helpers/ValidadorConocimiento.php tests/Support/ConocimientoPrueba.php tests/Fixtures/parto_regla_b2.json tests/Unit/ValidadorConocimientoTest.php tests/Unit/ConocimientoRealTest.php tests/Integration/BaseV2MysqlTest.php
git add -- documentacion/CasosTriage.md documentacion/ReglasNegocio.md specs/M2-grafo-clinico.md
git diff --cached --stat
git commit -m "feat(grafo): conocimiento inicial y ajustes de triage (HU-2.11, RN-417, RN-212)"
```

### B2.1 — Revisión (2026-10-14)

**Revisó Claude (arquitecto). Veredicto: aprobada. B2 queda cerrada.**

**Verificación propia:** suite completa en una copia limpia: sin MySQL 724 pruebas y 6200 aserciones (36 omitidas); con MariaDB 10.11 propia y la base por defecto, 724 pruebas y 6624 aserciones, ninguna omitida; JS 167/167.

**Lo que está bien:** las tres decisiones del usuario quedaron como reglas generales (`triage.toxicos_canino`, `triage.toxicos_felino`, `triage.etilenglicol`, `triage.abdomen_canino`, las dos marcas de parto y `triage.parto_menos_30`); el chocolate solo entra en el mínimo naranja con menos de 720 minutos y `chocolate_tardio` no cambió; `parto_prolongado` se retira sin borrarse ni cambiar su ID; la versión es igual con CRLF y LF, y `.gitattributes` fija LF en los JSON.

**Notas para C4 (no bloquean):**

1. Las reglas de tóxicos marcan `revision: true`. En T-08 y T-15 el resultado final es 🔴 y la batería no espera revisión: C4 no informa «revisión del personal» cuando el nivel final es 🔴 (la urgencia ya exige atención inmediata), y la batería compara la revisión solo donde CasosTriage la pide.
2. Cada cambio del conocimiento reescribe `version_conocimiento` en todas las filas (429 «cambios» en `--revisar`). Es esperado; el resumen de C6 y el log deben distinguir cambios de contenido de la actualización de versión si se muestran.

**Siguiente:** C6 — mapa del grafo (Claude Code).

## Anexo C6 — Resultado

**Lo escribió Claude Code (2026-10-14); entregada, pendiente de revisión de Claude (arquitecto).**

### Qué se entregó

- **Lectura (RE-2.12.5):** `models/GrafoConocimiento.php` lee solo `grafo_nodos`, `grafo_aristas`, `grafo_sinonimos` y `grafo_reglas` activos, la última carga auditada de `auditoria_sistema` y el catálogo global `razas` (solo `id_especie`, para ubicar cada raza en su especie al filtrar). No escribe.
  Una arista hacia un nodo inactivo tampoco se devuelve.
  Las limitaciones salen de `database/conocimiento/manifiesto.json` porque el cargador no las guarda en la base; si falta el archivo, hay un texto de reserva que dice «pendiente de validación veterinaria».
- **Respuesta (A.6):** `helpers/MapaConocimiento.php`, puro: `version` (hash y abreviada), `limitaciones`, `conteos`, `pendientes`, `cobertura` por especie (sin declaración = sin cobertura), `nodes`, `edges` y `reglas`.
  Cada elemento lleva sus fuentes (entidad, título, URL solo si es https, localizador, fundamento y fecha de consulta) y su estado de validación.
  Las reglas no son aristas: cada regla lista los nodos que nombra y cada nodo, las reglas que lo usan. Sin IDs internos ni claves de pacientes.
- **Rutas y permisos (RE-2.12.1):** `plataforma_grafo` y `obtener_grafo_conocimiento_ajax` (solo GET; otro método da 405), en la matriz de `Security.php` solo para el rol de plataforma.
  Una superadministradora nunca recibe un contexto de clínica (`Usuario::contextosDe`); si una sesión trae uno, se descarta y la acción da 403. Además, la matriz no admite ningún rol de clínica ni el portal.
- **Pantalla:** «Conocimiento clínico» en la barra del panel. La barra y la cabecera pasaron a parciales (`barra_lateral.php`, `cabecera.php`) que también usa `inicio.php`, con el mismo HTML de antes más el ítem nuevo.
  Cytoscape.js 3.34.3 se carga desde cdnjs con SRI `sha512-SQeb…` comprobado dos veces el 2026-10-14 (descarga propia y API de cdnjs), con layout `cose`. La pantalla incluye:
  - **Franja:** versión abreviada (completa al pasar el ratón), conteos, cobertura por especie y la limitación del manifiesto con el número de elementos pendientes.
  - **Mapa:** color y forma por tipo, alarmas P1 con borde rojo; aristas dirigidas, color y trazo por signo, grosor por peso, bloqueo con flecha `┤` roja y advertencia discontinua ámbar; leyenda pintada con las mismas constantes.
  - **Buscador y filtros:** búsqueda por etiqueta y sinónimo, que normaliza igual que `ArchivosConocimiento::normalizarTexto`; filtro por especie (conserva lo universal y las alarmas P1) y por tipo; los vecinos del nodo elegido se resaltan.
  - **Detalle:** de nodo y de arista, con condiciones y resultados en frases, reglas que lo usan, fuentes que abren en otra pestaña y la marca «Pendiente de validación veterinaria».
  - **Estados:** vacío (con los comandos de XAMPP), error con reintento, y fallo del CDN con aviso y la lista de los mismos datos. La lista también es una vista alternativa.
  - **Responsive (RNF-16):** en el celular el detalle es una hoja inferior y el mapa ocupa el ancho; desde 768 px, panel lateral (300 px en tablet, 380 px en escritorio).
- **Código:** JS en `public/js/grafo-conocimiento.js` (funciones puras en `window.ZookiGrafo`) y CSS en `public/css/grafo-conocimiento.css`; sin JS ni CSS en línea en las vistas. El texto de los datos se pinta con `textContent`, nunca con `innerHTML`.
- **Modelos §14:** se agregó la rama «¿cargó la librería del CDN? No → aviso y lista con los mismos datos».
  La nota 2 de «B2.1 — Revisión» no aplica: el mapa muestra la versión cargada, no el resumen de cambios del cargador.

### RE con su prueba

| RE | Prueba |
|---|---|
| RE-2.12.1 | `GrafoConocimientoTest::testVistaYEndpointRechazanCualquierContextoQueNoSeaPlataforma` (403 para administrador, veterinario, propietario y superadministradora con contexto de clínica en la sesión; pasa en plataforma); `AutorizacionRolTest::testElMapaDelConocimientoEsSoloDeLaPlataforma` y lista actualizada de `testElSuperAdministradorSoloTieneLaPlataforma`. |
| RE-2.12.2 | JS: tipos con color y forma distintos, alarma distinguida, grosor por peso, bloqueo y advertencia diferenciados; mapa con Cytoscape falso (estilos de cada elemento). Comprobación adicional con Cytoscape 3.34.3 real sobre los 105 nodos y 105 aristas: cero avisos de estilo y valores aplicados (borde rojo, `tee`, discontinua). |
| RE-2.12.3 | JS: búsqueda con tildes, mayúsculas y sinónimos («bomita» → vómito, primero el exacto); filtro por especie que conserva alarmas universales y lo universal y oculta lo de otra especie; filtro por tipo; vecinos; búsqueda que selecciona y atenúa lo demás. |
| RE-2.12.4 | `MapaConocimientoTest::testReglasSeAsocianALosNodosQueNombranYNoSonAristas`, `GrafoConocimientoTest::testRespuestaConVersionLimitacionesCoberturaYReglasEnSusNodos`, `BaseV2MysqlTest::testC6MapaDelConocimientoRealSoloActivoYConReglasEnSusNodos` (todas las reglas reales enlazadas a sus nodos); JS: condiciones y resultados legibles y detalle con regla, sinónimos y enlace `_blank` con `noopener`. |
| RE-2.12.5 | `GrafoConocimientoTest::testSoloElementosActivosYSinClavesDePacientes`, `testElModeloNoConsultaTablasDePacientesNiEscribe` (las tablas de las consultas del modelo, sin INSERT/UPDATE/DELETE), `testElEndpointSoloConsulta` (405), `testLaVistaUsaElPanelYCytoscapeConVersionFijaYSri` (sin formularios que escriban); `MapaConocimientoTest::testRespuestaSinClavesDePacientesNiIdsInternos`. |
| RE-2.12.6 | `testConocimientoVacioDaEstadoExplicitoYManifiestoAusenteUnaReserva`, `MapaConocimientoTest::testConjuntoVacioSinCoberturaFalsa`; JS: estado vacío sin franja, fallo del CDN con lista y franja, error con reintento. |

### Verificación

- PHP completo sin host MySQL: **738 pruebas, 6316 aserciones, 37 omitidas** (las de MySQL).
- PHP completo con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1` y la base por defecto `zooki_test_base_v2` (MariaDB 10.4.32 de XAMPP): **738 pruebas, 6872 aserciones, ninguna omitida**.
- JavaScript: **176/176** (167 anteriores + 9 de `M2C6Grafo.test.cjs`).
- Cytoscape.js 3.34.3 real, sin navegador, con el conocimiento real cargado en `zooki_test_base_v2`: 105 nodos y 105 aristas, cero avisos de la hoja de estilos y estilos aplicados como se esperaba; el layout `cose` dio 105 posiciones distintas y el filtro «Gato» deja 78 nodos y 80 aristas con las 9 alarmas universales.
- SRI del CDN: el `sha512` calculado sobre la descarga coincide con el que publica la API de cdnjs para `cytoscape.min.js` 3.34.3.
- `git diff --check` sin errores. No se leyó ni escribió `.env`, no se tocó `zooki_v2_prueba` ni sus contraseñas; sin commit ni push, y no se empezó C1.

### Prueba en el navegador (usuario)

En XAMPP, antes: `php scripts/migrar.php`, luego `php scripts/cargar_conocimiento.php`, y `php scripts/dev/datos_prueba.php --si` si hacen falta los usuarios.

1. [ ] **Gina Plataforma** (escritorio): en la barra, «Conocimiento clínico». Debe abrir el mapa con la franja (versión, conteos, cobertura Perro/Gato parcial y las demás sin cobertura, aviso de validación) y la leyenda. Busca «bomita», elige «Vómito» y revisa que se resalten sus vecinos y que el detalle muestre sinónimos, relaciones, reglas en frases y fuentes que abren en otra pestaña.
2. [ ] **Gina Plataforma**: filtra «Gato»; las alarmas (encías azuladas, colapso…) siguen y desaparecen Gran danés y los diagnósticos solo caninos. Toca una arista roja de lirio y otra discontinua de interacción para ver su detalle. Cambia a la vista de lista y vuelve al mapa.
3. [ ] **Gina Plataforma en el celular** (o DevTools a 375 px): el mapa ocupa el ancho y el detalle sale como hoja inferior que se cierra con ×.
4. [ ] **Beto Norte** (veterinario) y **Ana Norte** (administradora): abrir `index.php?action=plataforma_grafo` debe negar el acceso; `index.php?action=obtener_grafo_conocimiento_ajax` responde 403 en JSON.
5. [ ] **Sin conexión al CDN** (DevTools → Network → bloquear `cdnjs.cloudflare.com` y recargar): aviso y lista con los mismos datos, no una pantalla en blanco.

### Pendientes

- HU-2.12 y RE-2.12.1–6 siguen «Planificada» en los documentos; su estado se cierra en F con las revisiones y descargas del módulo.
- Con unos 105 nodos, el layout `cose` sale distinto en cada carga. Si incomoda, se puede fijar la semilla o guardar posiciones (decisión para la revisión).
- Todo el conocimiento sigue pendiente de validación veterinaria (decisión 1).

### C6 — Revisión (2026-10-14)

**Revisó Claude (arquitecto). Veredicto: aprobada en código; pendiente de las pruebas del usuario en el navegador.**

**Verificación propia:** suite completa en una copia limpia: sin MySQL 738 pruebas y 6316 aserciones (37 omitidas); con MariaDB 10.11 propia y la base por defecto, 738 pruebas y 6872 aserciones, ninguna omitida; JS 176/176.

**Lo que está bien:** las dos rutas solo en la matriz del contexto de plataforma; el endpoint es GET (405 si no), sin IDs internos ni claves de pacientes, y el modelo solo lee las tablas del grafo, `razas` y la última carga auditada; Cytoscape 3.34.3 con SRI; vistas sin JS ni CSS en línea y datos pintados con `textContent`; barra y cabecera del panel extraídas a parciales sin cambiar su HTML; estados de vacío, error y fallo del CDN; Modelos §14 al día.

**Decisión abierta para el usuario después de probar:** el layout `cose` acomoda el mapa distinto en cada carga. Si incomoda, se fija (posiciones estables) en el pulido; no bloquea C6.

**Siguiente:** pruebas del usuario (lista del Anexo C6) y C1 — alergias y alertas médicas (Claude Code).

**Pruebas del usuario (2026-10-15):** las cinco pruebas del Anexo C6 pasaron. No pidió fijar el layout del mapa. **C6 cerrada**, con commit `1053ee7`.

## Anexo C1 — Resultado

**Lo escribió Claude Code (2026-10-15); entregada, pendiente de revisión de Claude (arquitecto).**

### Qué se entregó

- **Modelo `AlertaMedica`** (sobre la tabla existente, sin migración) con la misma regla de acceso que las vacunas (`ModeloHistoria`):
  - **Registrar:** solo con vínculo activo y mascota activa. Valida tipo y descripción con `ValidadorAlertaMedica` (máximo 255, el tamaño de la columna). El nodo es opcional, viaja por su **código estable** (nunca un id) y debe estar activo y ser del tipo permitido: alergia → fármaco o sustancia; medicación continua → fármaco; condición crónica → condición; «otra» → ninguno.
  - **Desactivar:** solo la clínica que la registró (RN-112) y con el vínculo activo (RN-115); si no, 403 auditado. Nunca se borra.
  - **Leer:** toda clínica con vínculo activo ve las de todas, con clínica y veterinario; con el vínculo inactivo, solo las propias; sin vínculo, 403 auditado.
  - **Auditoría:** registrar y desactivar quedan en `auditoria_sistema` en la misma transacción; si no se puede auditar, no se guarda.
- **Quién y cuándo desactivó, sin migración:** la tabla no tiene esas columnas. El historial las lee de la fila de auditoría de la desactivación (usuario y `fecha_hora`), escrita en la misma transacción. Si se prefieren columnas propias, hace falta una migración (decisión pendiente).
- **Buscador `NodosEnlazables`:** nodos activos por etiqueta y sinónimo activo, normalizados con `ArchivosConocimiento::normalizarTexto` y filtrados por los tipos que admite la alerta; máximo 8 resultados. Si el nodo no está, se guarda solo la descripción, y la tarjeta y el formulario dicen que el motor de prescripción no podrá cruzarla (A.2).
- **Rutas:** `registrar_alerta_medica_ajax`, `desactivar_alerta_medica_ajax` y `buscar_nodo_alerta_ajax` son solo del veterinario. `listar_alertas_medicas_ajax` es del personal clínico, igual que `listar_historial_ajax`. Lo que escribe exige POST y CSRF. La plataforma no tiene acceso. `listar_historial_ajax` ahora trae `alertas` y el detalle de la mascota del portal también (de solo lectura, RN-G02).
- **Veterinario:** sección «Alergias y alertas» arriba de la ficha de la mascota en Pacientes, en el cajón de historial (Pacientes y Consultas) y en la columna del paciente de Atención (se carga sola). Muestra la clínica, el veterinario y la fecha de cada alerta, y la marca «Tu clínica».
  - **Registrar:** botón con un modal propio (`views/partials/modal_alerta_medica.php`) con validación en tiempo real, contador, restricción de entrada y el buscador de nodos. Se confirma con un aviso breve.
  - **Desactivar:** solo en las propias, con `zookiConfirmarCorto` («deja de mostrarse y de alertar; queda en el historial»).
  - **Lista e historial:** se actualizan con la respuesta, sin recargar, en todas las secciones de la misma mascota. Las desactivadas se ven en el desplegable «Historial», con quién y cuándo.
  - **Sin alertas:** dice «Que no haya registro no significa que no tenga».
- **Portal:** sección de solo lectura arriba del detalle de la mascota, fuera de las pestañas y del filtro por clínica (es información de seguridad), con la clínica de cada alerta activa.
- **Código:** JS en `public/js/alertas-medicas.js` (funciones puras en `window.ZookiAlertas`), CSS en `public/css/alertas-medicas.css`; sin JS ni CSS en línea y textos de la base con `textContent`. El DOM mínimo de las pruebas pasó a `tests/Frontend/apoyo/dom-datos.cjs` y lo usan C1 y C6.
- **Documentos:** Modelos §3, nodo AL1: se registra con el nodo si existe; solo la clínica que la registró la desactiva y queda en el historial. RE-2.1.5, RE-2.10.1 y RN-113 no cambian de texto (su estado se cierra en F).

### RE con su prueba

| RE / regla | Prueba |
|---|---|
| RE-2.1.5: registrar y desactivar en la propia clínica, sin borrar y con auditoría | `AlertaMedicaTest::testRegistrarYDesactivarEnLaPropiaClinicaSinBorrarYConAuditoria`; `BaseV2MysqlTest::testC1AlertasMedicasEnElEsquemaRealConNodoYAuditoria` (FK al nodo real, ENUM y auditoría en MariaDB) |
| RE-2.10.1 y RN-113: otra clínica vinculada las ve con la clínica de origen y no las desactiva (403) | `testOtraClinicaVinculadaLaVeConLaClinicaDeOrigenYNoLaDesactiva`; `HistoriaCompartidaTest` (la historia trae `alertas`) |
| RN-G13: clínica sin vínculo, 403 auditado | `testClinicaSinVinculoNoLaVeNiRegistraY403Auditado` |
| RN-115: vínculo inactivo, solo lo propio y sin escribir | `testConElVinculoInactivoSoloLeeLoPropioYNadaSeEscribe` |
| Tipo y nodo incompatibles, nodo inactivo o inexistente, descripción vacía, larga o con caracteres de control | `testTipoYNodoIncompatiblesNodoInactivoYDescripcionInvalidaSeRechazan` |
| Buscador por etiqueta y sinónimo, por tipo y solo lo activo | `testBuscadorDeNodosPorEtiquetaYSinonimoFiltradoPorTipo` |
| RN-G02: el propietario ve las activas solo de sus mascotas | `testElPropietarioVeLasActivasDeSusMascotasConSuClinicaYNoLasDeOtro` |
| Matriz, CSRF y superadministrador sin acceso | `testEscribirExigeCsrfYLaPlataformaNoEntra`; `AutorizacionRolTest::testAlertasMedicasSoloElVeterinarioEscribeYElPersonalLee` |
| Controlador: lista actualizada, 422 junto al campo, 405 sin POST | `testControladorDevuelveLaListaActualizadaYRechazaSinPost` |
| Modal con los límites del servidor y sin código en línea | `testElModalUsaLosLimitesYTiposDelServidorSinCodigoEnLinea` |
| JS de la sección | `M2C1Alertas.test.cjs`: pintado (clínica, «Tu clínica», desactivar solo en las propias, historial), solo lectura y portal, validación en tiempo real y contador, buscador de nodos, registro y desactivación que actualizan sin recargar, error del servidor junto al campo y carga automática en Atención |

### Verificación

- PHP completo sin host MySQL: **750 pruebas, 6440 aserciones, 38 omitidas** (las de MySQL).
- PHP completo con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1` y la base por defecto `zooki_test_base_v2` (MariaDB 10.4.32 de XAMPP): **750 pruebas, 7004 aserciones, ninguna omitida**.
- JavaScript: **183/183** (176 anteriores + 7 de `M2C1Alertas.test.cjs`).
- Pruebas existentes que cambiaron por diseño: `HistoriaCompartidaTest` espera la clave `alertas` en la historia, y `M2C6Grafo.test.cjs` usa el DOM compartido. En el portal, `alertas-medicas.js` se carga después de `portal.js`, porque `M5C2bPortal` exige que `validacion-mascota.js` vaya justo antes.
- `git diff --check` sin errores. No se leyó ni escribió `.env`, no se tocó `zooki_v2_prueba` ni sus contraseñas; sin commit ni push, y no se empezó C2.

### Prueba en el navegador (usuario)

En XAMPP, antes: `php scripts/migrar.php` y `php scripts/cargar_conocimiento.php` (los nodos para enlazar). Hace falta una mascota de Fabio vinculada a Norte y a Sur, como en las pruebas de M5.

1. [ ] **Beto Norte** (veterinario de Norte), escritorio: en Pacientes, abre la ficha de la mascota. Registra una alergia; escribe «acetaminofen» en «Enlazar al conocimiento» y elige «Paracetamol». Debe aparecer arriba sin recargar, con «Clínica Norte» y «Tu clínica». Prueba también una descripción vacía y otra de más de 255 caracteres: el aviso sale junto al campo mientras escribes.
2. [ ] **Diego Sur** (veterinario de Sur): la misma mascota muestra la alergia con «Clínica Norte» y **sin** botón «Desactivar». Registra una condición crónica propia «Epilepsia» (sin enlace o con «Epilepsia diagnosticada»); sí tiene «Desactivar».
3. [ ] **Beto Norte**: desactiva su alergia; confirma en el modal. Desaparece de las activas y queda en «Historial» con quién y cuándo. Abre una cita de esa mascota en Atención: la columna del paciente muestra la condición de Sur.
4. [ ] **Ana Norte** (administradora): no tiene la pantalla de Pacientes. En `index.php?action=listar_alertas_medicas_ajax&id_mascota=<id>` lee las alertas, y no puede registrar (403 si intenta la ruta de registro).
5. [ ] **Fabio Propietario**, en el celular: en el detalle de la mascota, «Alergias y alertas» arriba, de solo lectura, con la clínica de cada una y sin las desactivadas.

### Pendientes

- **Decisión:** quién y cuándo desactivó vive en la auditoría. Si se quieren columnas en `alertas_medicas` (`desactivada_por`, `fecha_desactivacion`), es una migración nueva.
- **Sugerencia documental para F:** RN-112 enumera consulta, vacuna, desparasitación, tratamiento y archivo; convendría agregar «alerta médica», que ya se comporta así. No se cambió por estar fuera de lo autorizado.
- El historial imprimible del portal (`imprimir_historial.php`) aún no muestra las alertas; el carnet sigue fuera (final de la v2.0).
- El cruce de la alergia con la prescripción (bloqueo) es C3; C1 solo guarda el enlace al nodo.

### C1 — Revisión (2026-10-15)

**Revisó Claude (arquitecto). Veredicto: aprobada en código; pendiente de las pruebas del usuario en el navegador.**

**Verificación propia:** suite completa en una copia limpia: sin MySQL 750 pruebas y 6440 aserciones (38 omitidas); con MariaDB 10.11 propia y la base por defecto, 750 pruebas y 7004 aserciones, ninguna omitida; JS 183/183.

**Lo que está bien:** `AlertaMedica` reutiliza la regla de acceso de `ModeloHistoria` (vínculo activo, RN-115 de solo lectura, 403 auditado sin distinguir «no existe» de «es de otra clínica»); el nodo viaja por código estable y se valida activo y del tipo permitido; registrar y desactivar se auditan en la misma transacción y sin auditoría no se guarda; nunca se borra; el portal muestra las activas fuera del filtro por clínica (información de seguridad); el mensaje «Que no haya registro no significa que no tenga» evita la falsa seguridad.

**Decisión del revisor sobre el pendiente de C1:** quién y cuándo desactivó se queda en la auditoría, sin migración. Se escribe en la misma transacción que el cambio, así que no puede faltar, y una columna repetiría el dato. Si más adelante se necesita filtrar por fecha de desactivación, se agrega entonces.

**Para F:** agregar «alerta médica» a la lista de RN-112; incluir las alertas activas en `imprimir_historial.php` del portal.

**Siguiente:** pruebas del usuario (lista del Anexo C1) y C2 — motor de diagnósticos (Codex).

**Pruebas del usuario (2026-10-15):** las cinco pruebas del Anexo C1 pasaron. **C1 cerrada**, con commit `d8f8519`.

## Anexo C2 — Resultado

**Lo escribió Codex (2026-10-15); entregada, pendiente de revisión; revisión independiente: Claude (arquitecto).**

### Qué y alcance

- `EvaluadorCondiciones`: doce predicados, lógica ternaria, intervalos inclusivos/exclusivos y fecha inyectada.
  Contexto, mapas de eventos y datos desconocidos documentados en el README del conocimiento.
- `GrafoConocimiento::instantanea()` reutiliza C6, conserva el contrato de `mapa()` y entrega `ConocimientoClinico` inmutable y cacheado por petición.
  Lee en una transacción coherente con la lectura repetible de InnoDB; no escribe conocimiento ni pacientes.
- `MotorDiagnosticos`: suma de evidencias con signo y modificadores verdaderos, evidencia positiva obligatoria, orden por puntuación/cobertura/código, máximo cinco candidatos.
  Informa datos faltantes sin aplicar sus condiciones, fuentes, evidencia y estado pendiente; no muestra probabilidades ni porcentajes.
  La cobertura usa síntomas positivos únicos característicos aplicables al contexto; el README detalla su denominador.
- `sugerir_diagnosticos_ajax`: POST con CSRF, solo veterinario; vínculo activo exigido por `PacienteDiagnostico`, 403 auditado si falta o está inactivo.
  La ficha y los nodos enlazados determinan especie/raza, edad en meses cumplidos, sexo y esterilización; las alertas activas son globales, sin leer consultas o tratamientos protegidos.
  Solo vacunación y días desde celo se aceptan como datos adicionales; vacunación omitida queda desconocida, sin inferirla de los registros.
  Códigos inexistentes, retirados o de otro tipo y entradas inválidas dan 422; sin coincidencias se indica escribir el diagnóstico obligatorio.

### RE y pruebas

| RE / regla | Evidencia |
|---|---|
| RE-2.8.1, RN-210 | `MotorDiagnosticosTest`: los cuatro pacientes del encargo con conocimiento real; signos, cobertura, desempate, determinismo y máximo cinco. |
| RE-2.8.3, RN-202 | Sin evidencia, lista vacía aunque existan modificadores; API indica diagnóstico libre obligatorio. El guardado final sigue en C5. |
| RE-2.8.4, RN-209 | Solo candidatos con evidencia positiva y puntuación positiva; fuentes y marca pendiente, sin probabilidades ni elección automática. |
| RE-2.8.5, RN-212 | Instantánea del conocimiento global, activa, inmutable y reutilizada; C6 conserva sus pruebas y contrato. |
| RN-113 / RN-G13 | `DiagnosticoTest`: dos clínicas, vínculo ausente/inactivo con 403 auditado, alertas activas de otra clínica sin leer sus consultas; roles no permitidos, CSRF, 422 y método 405. |
| Contrato A.2 y ajustes 4/6 de A | `EvaluadorCondicionesTest`: tablas ternarias completas, doce predicados presentes/ausentes, cero conocido, límites temporales y fecha explícita. |
| Esquema real y sin guardado | `BaseV2MysqlTest::testC2RankingConFichaYConocimientoActivosSinGuardar`: edad desde nacimiento, esterilización falsa conservada y ranking; ninguna consulta, marca de síntomas ni auditoría de escritura creada. |

El diagrama de Modelos §3 se conserva: la rama de ranking ya exige peso, cobertura y máximo cinco, seguida de decisión y diagnóstico obligatorio del veterinario.
No cambió el flujo ni se implementaron las pantallas de C5.

### Ejemplo de petición y respuesta real

Ejecutado con Security y el controlador reales sobre el fixture SQLite de dos clínicas, fecha inyectada `2026-10-15T12:00:00-05:00`, veterinario de Norte y mascota 1 con nacimiento `2026-07-15`.
No es una prueba de navegador ni una petición a la base de trabajo.

```http
POST /index.php?action=sugerir_diagnosticos_ajax
Content-Type: application/x-www-form-urlencoded

csrf_token=<token_de_la_sesion>&id_mascota=1&sintomas[]=sintoma.vomito&sintomas[]=sintoma.diarrea_sangre&sintomas[]=sintoma.no_come&vacunacion=incompleta
```

Extracto de la respuesta obtenida (se muestran el primer candidato y los metadatos; la respuesta completa también trae fuentes, las demás evidencias y cuatro candidatos adicionales):

```json
{
  "success": true,
  "candidatos": [{
    "codigo": "diagnostico.parvovirosis_canina",
    "etiqueta": "Parvovirosis canina",
    "puntuacion": 2.2,
    "cobertura": {"coinciden": 3, "caracteristicos": 6, "texto": "Coincide 3 de 6 síntomas característicos"},
    "sintomas_coincidentes": ["sintoma.diarrea_sangre", "sintoma.no_come", "sintoma.vomito"],
    "reglas_aplicadas": ["ranking.parvovirosis_contexto"],
    "estado_validacion": "pendiente"
  }],
  "datos_faltantes": [],
  "diagnostico_libre": true,
  "version": "980faceaaff142fa4c77eac28ba36aa57f2b53710f1174aaefa26cdb8edfebc9"
}
```

### Verificación y pendientes

- PHP completo sin host: **767 pruebas, 6628 aserciones, 39 omitidas** (MySQL).
- PHP completo con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1`, puerto 3309 y la base predeterminada `zooki_test_base_v2`, en instancia temporal propia MariaDB 10.4.32: **767 pruebas, 7199 aserciones, ninguna omitida**.
  No se ejecutó MySQL 8 en este equipo.
- JS: **183/183**; no hay cambios de JS ni pantalla nueva, por lo que no se requieren pruebas de navegador en C2.
- `git diff --check` sin errores de espacios; sin lectura/escritura de `.env`, sin tocar la base de trabajo ni contraseñas, sin commit ni push y sin empezar C3.

RE-2.8.2 y la interacción/guardado de RE-2.8.3 se completan en C5; no se declara toda HU-2.8 implementada por esta API.
El conocimiento sigue pendiente de validación veterinaria; C3 y C4 reutilizarán el evaluador.
No se cambió el esquema ni el contenido clínico de los JSON.
Versiones, revisiones documentales y descargas se cierran en F.

### Commit — PowerShell 5.1

```powershell
git add -- controllers/DiagnosticoController.php helpers/ConocimientoClinico.php helpers/EvaluadorCondiciones.php helpers/MotorDiagnosticos.php helpers/Security.php models/GrafoConocimiento.php models/PacienteDiagnostico.php public/index.php
git add -- tests/Unit/EvaluadorCondicionesTest.php tests/Unit/MotorDiagnosticosTest.php tests/Integration/DiagnosticoTest.php tests/Integration/BaseV2MysqlTest.php tests/Support/ConocimientoPrueba.php tests/Support/ConocimientoRealPrueba.php
git add -- database/conocimiento/README.md specs/M2-grafo-clinico.md agentes/estado.md
git diff --cached --stat
git commit -m "feat(grafo): ranking de diagnosticos y evaluador ternario (HU-2.8, RE-2.8.1)"
```

### C2 — Revisión (2026-10-15)

**Revisó Claude (arquitecto). Veredicto: aprobada.**

**Verificación propia:** suite completa en una copia limpia: sin MySQL 767 pruebas y 6628 aserciones (39 omitidas); **con MySQL 8.0.46** (el motor de producción y del CI) y la base por defecto, 767 pruebas y 7199 aserciones, ninguna omitida; JS 183/183.

**Lo que está bien:** evaluador ternario puro con la fecha inyectada; instantánea del conocimiento inmutable y una vez por petición, sin cambiar el contrato de C6; ranking con evidencia positiva obligatoria, cobertura «coincide X de Y» calculada sobre los síntomas característicos aplicables a la especie, orden por puntuación, cobertura y código, máximo cinco y sin porcentajes; la vacunación no se infiere de los registros; acceso igual que la historia (403 auditado sin vínculo); no guarda nada.

**Observación menor (para C5):** `datos_faltantes` puede nombrar aristas o reglas de candidatos que no quedan entre los cinco; la pantalla debe mostrar como «datos que ayudarían» solo los campos (vacunación, días desde el celo…), sin listar códigos.

**Corrección del CI hecha en esta revisión:** `.github/workflows/ci.yml` esperaba 50 tablas y 54 razas; desde B1 son 52 y 55 (`grafo_sinonimos`, `grafo_reglas` y Gran danés). Por eso el CI fallaba desde el push de B1. Se comprobó en MySQL 8.0 el mismo recorrido del CI: esquema y semilla (52 tablas, 55 razas), migrador dos veces («La base está al día») y pruebas de base real en verde.

**Siguiente:** C3 — alertas de prescripción (Codex).

## Anexo C3 — Resultado

**Entrega:** 2026-10-15; entregada, pendiente de revisión.
**Autor:** lo escribió Codex; revisión independiente de Claude (arquitecto).

### Qué se hizo y evidencia

- Motor puro `MotorPrescripcion`, compartiendo el evaluador y la instantánea de C2: toxicidad según efecto, alergia individual sin exigir bibliografía, interacciones en ambos sentidos y entre filas, reglas de prescripción y evaluación incompleta sin inferir fármacos.
- Lectura mínima `MedicacionVigente`, separada de `ModeloHistoria`: exige vínculo activo, incluye el último día, excluye tratamientos futuros o terminados y conserva fin desconocido; suma alertas activas de medicación continua, globales por RN-113.
- `PrescripcionPublica` limita los campos y agrupa las advertencias protegidas; ni el historial ni `Tratamiento::findByConsultas` amplían permisos.
- API veterinaria POST/CSRF `validar_prescripcion_ajax`, sin guardar; contrato de integración en `database/conocimiento/README.md`.
- Guardado con revalidación dentro de la transacción antes de insertar o mover archivos; bloqueo/advertencia sin resolver dan 422 por fila, con rollback completo y rechazo auditado fuera del rollback.
  Las decisiones y conciliaciones se auditan dentro de la transacción con versión y evidencia interna; si falla esa auditoría no se guarda.
  Código activo y fecha final se persisten; las filas heredadas sin código conservan su guardado como evaluación incompleta hasta C5.
- Decisiones aprobadas: conciliación de máximo 500 caracteres sin recortar; confirmación con huella HMAC secreta por sesión y vigente por fila.
  Cambiar cruces, visibilidad o versión invalida la confirmación anterior; se vuelve a evaluar sin revelar medicación protegida.

| RE / regla | Evidencia automatizada |
|---|---|
| RE-2.9.1, RN-211 | `MotorPrescripcionTest`: paracetamol gato/perro, permetrina gato, loperamida Pastor Alemán, interacción meloxicam/prednisona en ambos sentidos y entre filas; `PrescripcionTest::testVigenciaIncluyeFinExcluyeTerminadosYFuturosYConservaDesconocida` |
| RE-2.9.2 | Alergia individual sin arista bibliográfica; `testBloqueoDirectoNoGuardaNiMueveAdjuntosYDejaAuditoriaDeRechazo`; petición directa 422 con fila; esquema real con `BaseV2MysqlTest::testC3PrescripcionSeRevalidaAlGuardarConAuditoriaYRollback` |
| RE-2.9.3, RN-G13, RN-113 | Recorrido recursivo de claves y búsqueda de secretos en JSON; agrupación de múltiples protegidas; autorización/revocación; nueva interacción y versión invalidan huella; conciliación obligatoria, máximo y fallo de auditoría revierten el guardado |
| RE-2.9.4, RN-209, RN-212 | El motor no prescribe ni genera dosis o pautas; solo bloquea/advierte y exige decisión explícita; código desconocido conserva evaluación incompleta; instantánea y versión auditada |
| Seguridad de la API | Veterinario autorizado; 403 a administrador, propietario y plataforma; CSRF y vínculo obligatorio con rechazo auditado; `PrescripcionTest::testApiNuncaExponeClavesProtegidasY403SinVinculoYConOtrosRolesOCsrf` |

### Respuestas reales de la API

Capturadas llamando al controlador y a Security con el fixture de dos clínicas y el conocimiento real, sobre SQLite en memoria, sin navegador.
Vista previa con interacción protegida; HTTP 200 significa que se evaluó la petición, no que esté autorizado guardar:

```json
{
  "success": true,
  "filas": [{
    "bloqueada": false,
    "motivos": [],
    "advertencias": [{
      "tipo": "interaccion_protegida",
      "mensaje": "Posible interacción con medicación registrada por otra clínica; concilie con el propietario antes de confirmar."
    }],
    "evaluacion_incompleta": false,
    "requiere_decision": true,
    "requiere_conciliacion": true,
    "huella": "ca3e39d451cd9d2c06929470f09851d71c16f2bb6de103590ace36f9d2274046"
  }]
}
```

Vista previa con bloqueo por toxicidad; intentar guardarla responde 422 sin crear registros clínicos:

```json
{
  "success": true,
  "filas": [{
    "bloqueada": true,
    "motivos": [{
      "tipo": "toxicidad",
      "mensaje": "Toxicidad documentada para la especie o raza del paciente."
    }],
    "advertencias": [],
    "evaluacion_incompleta": false,
    "requiere_decision": false,
    "requiere_conciliacion": false,
    "huella": "39ae7d29cbc6537c592178e1fcbc8a8a97eeeb2748bffa00dabfc936068578b3"
  }]
}
```

Las huellas capturadas son opacas, pertenecen a aquella sesión y no sirven como confirmaciones de otra sesión.

### Verificación y pendientes

- Suite PHP completa sin host: 783 pruebas, 6758 aserciones y 40 omitidas de MySQL.
- Suite PHP completa con MariaDB 10.4.32 temporal propia y base predeterminada `zooki_test_base_v2`: 783 pruebas, 7337 aserciones, ninguna omitida.
- JS: 183/183; sin cambios de JS ni pantallas nuevas.
- Modelos §3 distingue toxicidad con bloqueo de advertencia, incluye interacciones entre filas, huella vigente y auditoría de decisiones en el guardado atómico; su diagrama se renderizó con Mermaid 11.4.1.
- Sin migración, cambios al conjunto clínico, lecturas de `.env`, acceso a la base de trabajo, commit ni push.
- Pendiente: revisión de Claude; presentación, buscador y campos de confirmación en ambas entradas de consulta en C5; conocimiento pendiente de validación veterinaria.
  C4 no se empezó; versión, revisiones y descargas se publican en F.

### C3 — Revisión (2026-10-15)

**Revisó Claude (arquitecto). Veredicto: aprobada.**

**Verificación propia:** suite completa en una copia limpia: sin MySQL 783 pruebas y 6758 aserciones (40 omitidas); con MySQL 8.0.46 y la base por defecto, 783 pruebas y 7337 aserciones, ninguna omitida; JS 183/183.

**Lo que está bien:** el motor reutiliza el evaluador y la instantánea de C2; la alergia individual bloquea sin bibliografía; un bloqueo no se confirma, solo las advertencias; `PrescripcionPublica` es una lista cerrada de campos y agrupa las protegidas en un aviso genérico; `MedicacionVigente` es una lectura aparte que no amplía la historia; el servidor vuelve a evaluar dentro de la transacción al guardar, y las decisiones y conciliaciones se auditan con la versión del conocimiento; la huella HMAC por sesión obliga a confirmar de nuevo si cambian los cruces, la visibilidad o la versión; las filas sin código siguen guardándose hasta C5.

**Para C5:** el motivo de bloqueo por toxicidad es genérico («para la especie o raza del paciente»); en pantalla conviene nombrar la especie o la raza y enlazar la fuente, que son conocimiento global y no datos protegidos.

**Siguiente:** C4 — motor de triage con la batería (Codex).

## Anexo C4 — Resultado

**Entrega:** 2026-10-15; entregada, pendiente de revisión.
**Autor:** lo escribió Codex; revisión independiente de Claude (arquitecto).

### Qué y trazabilidad

- `MotorTriage` puro reutiliza `EvaluadorCondiciones` y `ConocimientoClinico`: máximo explícito, P1 universal, P3 una sola vez sobre moderados distintos, reglas independientes y reserva P6.
  Los datos desconocidos decisivos piden revisión; rojo no informa revisión del personal y las banderas de revisión inmediata y contagio se conservan por separado.
- `BuscadorSintomas` puro: normalización compartida, etiquetas/sinónimos activos, exacto/prefijo/palabras y Damerau-Levenshtein completo sobre puntos Unicode; umbral 0.65 y ocho opciones como máximo, incluida «Otro / no sé».
  Conserva alarmas y síntomas globales al filtrar; no inventa exclusividades de especie ausentes del catálogo, no selecciona y no calcula.
- APIs sin guardado `buscar_sintomas_ajax` (GET) y `calcular_triage_ajax` (POST/CSRF), para veterinario y propietario conforme a los actores de Modelos §4; plataforma y administrador no entran.
  El contexto común exige vínculo activo de la clínica o mascota propia del titular, con 403 auditado; las declaraciones de vacunación/celo se validan en un helper compartido con C2.
  No se ampliaron permisos de historia ni se leyeron tratamientos protegidos.
- Fixture versionado de los 26 casos, sin inventar edades, horas ni fármacos; batería automatizada y fechada en `CasosTriage.md`, sin cambiar esperados ni JSON clínicos.
  La revisión esperada solo se compara donde la batería la exige (`null` significa no especificada, según B2.1 — Revisión); la tabla muestra además toda revisión obtenida.

| RE / regla | Prueba |
|---|---|
| RE-2.11.1, RN-424 | `TriageTest`: nota sola rechazada, nota no cambia cálculo, tiempos conocidos/no sé/no aplica, códigos inválidos/retirados/tipo incorrecto y duplicados; `BuscadorSintomasTest`: «bomita», «desaido», tildes, palabras y transposición sin selección |
| RE-2.11.2, RN-416 | `MotorTriageTest`: P2 máximo; P3 distintos y una sola vez; T-04/T-17/T-18; P1 prevalece ante verde y amarillo |
| RE-2.11.3 | Batería T-09/T-10, T-11/T-12, T-14/T-16; ventanas chocolate 0/719/720/2880 y tiempo desconocido; tamaño desconocido/grande, tóxico naranja, etilenglicol rojo y parto breve naranja con revisión |
| RE-2.11.4, RN-417 | Las nueve alarmas universales en perro, ave y roedor, incluidas las cinco P1 sin caso propio; T-21 |
| RE-2.11.5, RN-418 | P6 aislada: sin cobertura, vacío, «Otro / no sé» y marca sin nivel/regla; T-20 conserva revisión inmediata; dato decisivo desconocido explícito |
| RE-2.11.6, RN-425 | T-11 devuelve contagio; prueba adicional conserva la bandera incluso con nivel rojo; notificación operativa pertenece a M4 |
| RE-2.11.7 | `MotorTriageTest::testBateriaConConocimientoReal` con proveedor y `tests/Fixtures/triage.php`: 26/26 niveles y banderas exigidas coinciden |
| RN-G02 / RN-G13; sin escrituras | `TriageTest`: propiedad, vínculo activo, CSRF, roles, contexto desde ficha; `BaseV2MysqlTest::testM2C4TriageConFichaPropiaYBusquedaSinEscrituras`: esquema real, propietario/vet y cero citas, consultas, marcas o auditorías de escritura |
| RN-209, P8 | Aviso de apoyo y versión; determinismo con fecha fija; nota, cuota y mora no modifican el cálculo; rojo se conserva sin cupo |

### Batería real

Conocimiento `980faceaaff142fa4c77eac28ba36aa57f2b53710f1174aaefa26cdb8edfebc9`, fecha de evaluación `2026-10-15T12:00:00-05:00`.
R = revisión del personal; I = revisión inmediata; C = contagio.
Las revisiones adicionales de T-11, T-12 y T-17 indican datos decisivos desconocidos, no cambios del color esperado.

| Caso | Esperado | Obtenido | R | I | C |
|---|---|---|---|---|---|
| T-01 | Rojo | Rojo | No | No | No |
| T-02 | Rojo | Rojo | No | No | No |
| T-03 | Rojo | Rojo | No | No | No |
| T-04 | Rojo | Rojo | No | No | No |
| T-05 | Rojo | Rojo | No | No | No |
| T-06 | Naranja | Naranja | No | No | No |
| T-07 | Naranja | Naranja | No | No | No |
| T-08 | Rojo | Rojo | No | No | No |
| T-09 | Naranja | Naranja | No | No | No |
| T-10 | Amarillo | Amarillo | No | No | No |
| T-11 | Naranja + contagio | Naranja + contagio | Sí | No | Sí |
| T-12 | Amarillo | Amarillo | Sí | No | No |
| T-13 | Naranja | Naranja | No | No | No |
| T-14 | Naranja + revisión | Naranja + revisión | Sí | No | No |
| T-15 | Rojo | Rojo | No | No | No |
| T-16 | Amarillo provisional + revisión | Amarillo + revisión | Sí | No | No |
| T-17 | Naranja | Naranja | Sí | No | No |
| T-18 | Naranja | Naranja | No | No | No |
| T-19 | Amarillo | Amarillo | No | No | No |
| T-20 | Amarillo + revisión inmediata | Amarillo + revisión inmediata | Sí | Sí | No |
| T-21 | Rojo | Rojo | No | No | No |
| T-22 | Amarillo + revisión | Amarillo + revisión | Sí | No | No |
| T-23 | Naranja | Naranja | No | No | No |
| T-24 | Verde | Verde | No | No | No |
| T-25 | Verde | Verde | No | No | No |
| T-26 | Verde | Verde | No | No | No |

### Ejemplos reales de las APIs

Ejecutados con controlador, Security y conocimiento reales sobre SQLite en memoria, fixture de dos clínicas y propietario 6; no es prueba de navegador ni de la base de trabajo.

`GET ?action=buscar_sintomas_ajax&q=bomita` devuelve HTTP 200:

```json
{
  "success": true,
  "sugerencias": [
    {"codigo":"sintoma.vomito","etiqueta":"Vómito","similitud":1,"coincidencia":"exacta"},
    {"codigo":"sintoma.vomito_sangre","etiqueta":"Vómito con sangre","similitud":1,"coincidencia":"prefijo"},
    {"codigo":"sintoma.vomito_repetido","etiqueta":"Vómito repetido (varias veces el mismo día)","similitud":0.8333333333333334,"coincidencia":"aproximada"},
    {"codigo":"sintoma.arcadas_improductivas","etiqueta":"Arcadas: intenta vomitar y no sale nada","similitud":0.7142857142857143,"coincidencia":"aproximada"},
    {"codigo":"sintoma.otro_no_se","etiqueta":"Otro / no sé","coincidencia":"reserva","similitud":0}
  ],
  "version":"980faceaaff142fa4c77eac28ba36aa57f2b53710f1174aaefa26cdb8edfebc9"
}
```

`POST ?action=calcular_triage_ajax`, formulario `csrf_token=<token>&id_mascota=1&sintomas[0][codigo]=sintoma.dificultad_respiratoria&sintomas[0][tiempo]=no_se`, devuelve HTTP 200:

```json
{
  "success": true,
  "nivel": "rojo",
  "revision": false,
  "revision_inmediata": false,
  "contagio": false,
  "reglas_aplicadas": ["P1:sintoma.dificultad_respiratoria"],
  "datos_faltantes": [],
  "version": "980faceaaff142fa4c77eac28ba36aa57f2b53710f1174aaefa26cdb8edfebc9",
  "aviso": "Conocimiento inicial armado solo desde fuentes públicas reconocidas, con la fuente de cada elemento. Todo está pendiente de validación veterinaria: no hay un veterinario que lo haya revisado. Los colores de triage son políticas del producto (RN-416 a RN-418) y los pesos del ranking son heurísticos. Cobertura parcial en perros y gatos; las demás especies no tienen cobertura específica. Sin dosis ni pautas de tratamiento. Es apoyo a la decisión (RN-209): decide el veterinario."
}
```

### Verificación y pendientes

- PHP completo sin host: 828 pruebas, 7025 aserciones, 41 omitidas de MySQL.
- PHP completo con MariaDB 10.4.32 temporal propia y `zooki_test_base_v2`: 828 pruebas, 7614 aserciones, ninguna omitida.
- JS: 183/183; sin pantallas, CSS o JS nuevos.
- Modelos §4 conserva el flujo: C4 entrega el cálculo de G1; la captura, la derivación, revisión antes de asignar horario, los límites, aislamiento y ajuste auditado son de M4.
- Conocimiento y casos T-16/T-20 pendientes de validación veterinaria; no hubo ajustes de esperados ni reglas hechas para un caso.
- Pendiente de revisión de Claude; C5 no se empezó.
  Sin migración, lectura/escritura de `.env`, acceso a la base de trabajo, commit ni push; versiones, revisiones y descargas se cierran en F.

### C4 — Revisión (2026-10-15)

**Revisó Claude (arquitecto). Veredicto: aprobada. RE-2.11.7 se cumple: 26 de 26.**

**Verificación propia:** suite completa en una copia limpia: sin MySQL 828 pruebas y 7025 aserciones (41 omitidas); con MySQL 8.0.46 y la base por defecto, 828 pruebas y 7614 aserciones, ninguna omitida; JS 183/183. Se revisó que `MotorTriage` no nombra casos, razas ni sustancias (todo sale del conocimiento) y que el fixture de la batería no inventa edades, horas ni fármacos.

**Lo que está bien:** máximo explícito sin promedios; P1 universal aun sin cobertura; P3 una sola vez (T-17 queda 🟠); P6 sin 🟢 por defecto; revisión omitida cuando el final es 🔴 y revisión inmediata y contagio conservados aparte (notas de B2.1); las decisiones del usuario sobre tóxicos, torsión y parto probadas con sus límites (0, 719, 720 y 2880 minutos de chocolate); el buscador sugiere sin marcar y siempre ofrece «Otro / no sé»; las APIs no guardan nada y respetan vínculo y propiedad.

**Nota para M4:** `calcular_triage_ajax` admite veterinario y propietario, según los actores de Modelos §4; si en la agenda otro rol del personal agenda con triage, se amplía la matriz allí.

**Decisión del revisor:** C5 se parte en dos para que cada revisión y cada prueba en el navegador sean manejables: **C5a** (síntomas guardados, diagnósticos sugeridos y obligatorios avisados desde el inicio, en ambas entradas de consulta) y **C5b** (prescripción con buscador de fármacos, alertas, decisiones y conciliación; formulario de tratamiento y peso desconocido).

**Siguiente:** C5a (Claude Code).
