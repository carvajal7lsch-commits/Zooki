# M2 — Historia clínica y soporte a la decisión (Grafo I)

> Estado: aprobado con las decisiones de §6 (2026-10-14); el mecanismo de la decisión 3 se confirma en la revisión de A
> Entrega: v2.0 · Fecha: 2026-10-14 · Sale como `2.0.0-alpha.4`
> Responsable de escritura: Codex (A, B, C2, C3, C4) y Claude Code (C1, C5, C6) · Revisión independiente: Claude (arquitecto)
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
| HU-2.8 | RE-2.8.1–5 | RN-209, RN-210, RN-212, RN-202 | C2, C5 | Diagnósticos ordenados por peso con casos de prueba; aceptar o escribir el propio |
| HU-2.9 | RE-2.9.1–4 | RN-209, RN-211, RN-212, RN-113 | C3, C5 | Bloqueo por toxicidad y alergia; advertencia de interacción con auditoría; aviso genérico sin revelar lo de otra clínica |
| HU-2.11 | RE-2.11.1–7 | RN-411, RN-416, RN-417, RN-418, RN-424, RN-425, RN-209 | C4 | La batería de 26 casos como prueba automatizada (RE-2.11.7), con síntomas marcados (decisión 3) |
| Nueva: mapa del grafo (A propone HU, RE y número) | por definir | RN-212 | C6 | El superadministrador explora nodos y aristas con su peso, signo y fuente; solo lectura |

## 3. Etapas y dependencias

| Etapa | Entregable comprobable | Depende de | Escribe | Revisa |
|---|---|---|---|---|
| **A. Inventario y diseño** (solo lectura del código) | La consulta actual (código, vistas, JS, pruebas) y qué se conserva; el esquema del grafo frente a lo que piden los RE y la batería (por ejemplo: nivel de triage por síntoma, alarmas universales, sinónimos para el buscador de síntomas, fuente de cada nodo y arista, cobertura por especie, contagio, ventanas de tiempo, interacciones fármaco–fármaco, modificadores de raza, edad y estado); cada caso T-01 a T-26 traducido a lo que el propietario marcaría (síntomas del catálogo, tiempo y paciente) y el conocimiento que exige; propuesta del modelo de datos (para B), del formato de la base de conocimiento y de los tres algoritmos (ranking, prescripción, triage); diagramas §1 y §3 frente a la propuesta. | M5 | Codex | Claude |
| **B. Esquema y conocimiento base** | Migración `07` repetible con lo que falte, MER y drawDB al día; la base de conocimiento versionada en el repositorio con su cargador idempotente; conocimiento inicial que cubre la batería y lo común en perros y gatos, marcado «pendiente de validación veterinaria» y con su fuente pública. | A | Codex | Claude |
| **C1. Alergias y alertas médicas** | Registrar y desactivar (RE-2.1.5); visibles en la ficha de toda clínica vinculada (RN-113) y en el portal (pestaña de la mascota). | B | Claude Code | Claude |
| **C2. Motor de diagnósticos** (HU-2.8) | Helper sin base de datos que ordena diagnósticos por peso según síntomas y contexto; API para la consulta; pruebas con casos. | B | Codex | Claude |
| **C3. Alertas de prescripción** (HU-2.9) | Bloqueo por toxicidad de especie o raza y por alergia; advertencia de interacción con la medicación vigente, confirmación con auditoría; aviso genérico cuando el tratamiento es de otra clínica sin autorización. | B, C1 | Codex | Claude |
| **C4. Motor de triage** (HU-2.11) | Helper con las reglas P1–P8 (RN-416 a RN-418, RN-425) que calcula el nivel **solo con síntomas marcados**, tiempo y paciente; la opción «Otro / no sé» da 🟡 con revisión del personal (P6); el texto libre se guarda para el personal y no cambia el nivel; buscador del catálogo tolerante a errores (sugiere, el usuario confirma); la batería completa como prueba automatizada. | B | Codex | Claude |
| **C5. Consulta del veterinario** | La consulta con síntomas marcados, diagnósticos sugeridos, tratamiento con sus alertas y la sección de alergias y alertas; pendientes de `pulido-interfaz.md` de la consulta. | C1–C3 | Claude Code | Claude |
| **C6. Mapa del grafo** («cerebro de nodos») | Vista de solo lectura en el panel del superadministrador: grafo interactivo con color por tipo de nodo, aristas por signo y grosor por peso, buscador, filtro por tipo y especie, y al tocar un nodo sus relaciones con peso, signo, fuente y la marca «pendiente de validación». Conviene hacerla justo después de B: es la forma de revisar el conocimiento a ojo. | B | Claude Code | Claude |
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
| 3. **Texto libre del triage** | **Decisión del usuario (2026-10-14):** no confiar el nivel al texto libre; interpretar lo que el propietario escribe es arriesgado para el triage y muy difícil de probar. **Mecanismo recomendado (se confirma en la revisión de A):** el nivel se calcula solo con lo marcado del catálogo, el tiempo y el paciente; el catálogo trae «Otro / no sé», que da 🟡 con revisión del personal (P6); el texto libre queda opcional, como nota para el personal, y no cambia el nivel; un buscador del catálogo tolerante a errores ayuda a encontrar el síntoma («bomita» sugiere «Vómito con sangre») pero el usuario lo marca. Ajustes en los documentos (B): RE-2.11.1 («al menos un síntoma marcado o “Otro / no sé”», ya no «o el texto»), RN-424 (el texto no interviene en el cálculo), CasosTriage: cada caso se expresa como síntomas marcados; T-22 pasa a «Otro / no sé» → 🟡 con revisión; T-23 pasa a prueba del buscador más el caso marcado → 🟠. |
| 4. **Alcance del triage en M2** | M2 entrega el motor, su API y la batería en verde; la captura en Agendar, urgencias y el ajuste del veterinario van en M4. |
| 5. **Quién escribe** | **Decisión del usuario (2026-10-14):** Codex para A, B y los motores (C2–C4); Claude Code para las pantallas (C1, C5 y C6). |
| 6. **Medicación vigente** | Se toma de los tratamientos activos (por duración) y de las alertas de tipo «medicación continua»; A confirma si el esquema alcanza. |

## 7. Tareas y verificación

- [x] Aprobar este plan y decidir §6 (2026-10-14).
- [ ] A — Inventario y diseño.
- [ ] B — Esquema y conocimiento base.
- [ ] C1 — Alergias y alertas médicas.
- [ ] C2 — Motor de diagnósticos.
- [ ] C3 — Alertas de prescripción.
- [ ] C4 — Motor de triage con la batería.
- [ ] C5 — Consulta del veterinario.
- [ ] C6 — Mapa del grafo (recomendado justo después de B).
- [ ] `vendor/bin/phpunit`, JS y cada RE de §2 con evidencia.
- [ ] F — `2.0.0-alpha.4`.

**Resultado de pruebas:** pendiente.
