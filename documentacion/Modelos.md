# Modelos y diagramas — Zooki v2.0

Este documento reúne los modelos de la Fase 2: el modelo conceptual de los dos grafos (soporte a la decisión clínica y agendamiento inteligente), los diagramas de proceso de negocio (BPMN) y los flujos del sistema. Complementa al [ERS](ERS.md) y al [MER](MER.md), y se renderiza en el portal mediante Mermaid.

> **Cómo verlos:** cada diagrama tiene arriba un visor con zoom, desplazamiento y pantalla completa (ajusta al ancho y al alto de la pantalla). El botón **draw.io** descarga o abre la versión con estilo (archivo con todas las hojas). Estos diagramas son la versión **detallada**: incluyen las validaciones, las ramas de error, el triage de 4 niveles, la cobertura de ausencias y el propietario multi-clínica. El detalle normativo (reglas de negocio, requisitos, diccionario de datos) vive en el ERS, el MER y las [Reglas de Negocio](ReglasNegocio.md).

**Convención de formas:** rombo `{ }` = pregunta/decisión · rectángulo `[ ]` = acción · `[/ /]` = mensaje al usuario · `[[ ]]` = subproceso · `{{ }}` = evento · `([ ])` = inicio/fin.

---

## Arquitectura — Diagrama de componentes

Vista de componentes de la plataforma v2.0 (SaaS multi-inquilino, tres capas). El cliente entra siempre por el **front controller**, que resuelve el inquilino y pasa por **Security** (rol + CSRF + `id_clinica`) antes de llegar a los controladores. La lógica de dominio incluye los dos motores de grafos, que se recorren **en memoria**: el Grafo I se guarda en tablas y se carga para recorrerlo; el Grafo II se arma en cada cálculo con los datos de la agenda. Los datos viven en MySQL con las filas **aisladas por `id_clinica`**, y las tareas programadas (cron) alimentan recordatorios, vencimiento de suscripciones, el vigilante de atenciones, el vencimiento de las reasignaciones sin confirmar y los respaldos.

```mermaid
flowchart TB
  subgraph CLI["Cliente — navegador"]
    P1["Portal del propietario"]
    P2["Panel de la clínica (admin / veterinario)"]
    P3["Páginas públicas"]
    P4["Carnet QR (solo lectura)"]
  end

  subgraph APP["Servidor de aplicación — PHP 8.2 (MVC propio)"]
    FC["Front controller<br/>public/index.php"]
    SEC["Security<br/>rol + CSRF + id_clinica"]
    CTRL["Controladores<br/>Auth · Mascota · Consulta · Cita · Vacuna<br/>Propietario · Usuario · Panel · Suscripcion"]
    VIEW["Vistas / plantillas"]
    subgraph DOM["Lógica de dominio"]
      G1["Motor Grafo I — CDSS<br/>(en memoria)"]
      G2["Motor Grafo II — Agenda<br/>(en memoria)"]
      SVC["Servicios<br/>EmailService · GoogleToken · PoliticaPassword<br/>Recordatorios · VigilanteAtenciones"]
    end
  end

  subgraph DAT["Capa de datos"]
    DB[("MySQL 8<br/>filas aisladas por id_clinica")]
    BK["Respaldos"]
  end

  subgraph CRON["Tareas programadas (cron)"]
    CR["Recordatorios · Vencimiento de suscripciones<br/>Vigilante de atenciones · Reasignaciones vencidas · Backup"]
  end

  subgraph EXT["Servicios externos"]
    SMTP["Brevo (correo SMTP)"]
    GO["Google OAuth 2.0"]
    TS["Cloudflare Turnstile (CAPTCHA)"]
    PAY["Pasarela de pago (futura)"]
  end

  CLI -->|HTTPS| FC
  FC --> SEC
  SEC --> CTRL
  CTRL --> VIEW
  CTRL --> G1
  CTRL --> G2
  CTRL --> SVC
  CTRL --> DB
  G1 --> DB
  G2 --> DB
  SVC --> SMTP
  SVC --> GO
  SEC --> TS
  CR --> SVC
  CR --> DB
  BK -.-> DB
  SVC -.-> PAY
```

---

## 1. Grafo I — Conocimiento clínico (grafo con signo)

El conocimiento clínico se modela como un **grafo dirigido, ponderado y con signo**. Los nodos son conceptos (síntomas, diagnósticos, fármacos, especies, razas, condiciones) y las aristas sus relaciones: las **positivas** (`＋ trata`) favorecen una opción y las **negativas** (`－ tóxico-para`, `contraindica`) la vetan. Los pesos en las aristas `síntoma → diagnóstico` permiten ordenar los diagnósticos probables.

El siguiente ejemplo recorre un caso real: tres síntomas apuntan con distinto peso a un diagnóstico, que propone dos fármacos; el nodo *Especie: Gato* dispara una arista negativa que descarta el paracetamol antes de recetarlo.

```mermaid
flowchart LR
  S1([Cojera]) -->|0.8| DX[Dolor agudo]
  S2([Se lame la zona]) -->|0.6| DX
  S3([Decaimiento]) -->|0.5| DX
  DX ==>|＋ trata| F1[Buprenorfina]
  DX -. candidato .-> F2[Paracetamol]
  ESP{{Especie: Gato}} ==>|－ tóxico-para| F2

  classDef pos fill:#E3EDF7,stroke:#2E78B8,color:#12324f;
  classDef neg fill:#F5E4E2,stroke:#B23A34,color:#5a1f1c;
  classDef sp fill:#EEF3F0,stroke:#5C6773,color:#26313B;
  class F1 pos;
  class F2 neg;
  class ESP sp;
  linkStyle 3 stroke:#2E78B8,stroke-width:2px;
  linkStyle 5 stroke:#B23A34,stroke-width:2px;
```

**Lectura:** lo azul (positivo) suma a favor; lo rojo (negativo) veta. El motor recorre el grafo durante la consulta y, en la prescripción, cruza el fármaco con la especie, la raza, las alergias y la medicación vigente del paciente (RN-211). Se almacena como lista de aristas (`grafo_nodos`, `grafo_aristas`) y se recorre en memoria como lista de adyacencia.

---

## 2. Grafo II — Agendamiento inteligente

La agenda se modela como un grafo cuyos nodos son las citas del día por veterinario, con tres tipos de arista: **secuencia** (una cita depende del fin real de la anterior), **conflicto** (dos citas que chocan en horario y veterinario) y **emparejamiento** (asignación cita–veterinario, que permite reasignar). El grafo no se guarda: se construye en tiempo real desde `citas`, `tipos_cita`, los veterinarios (`usuarios`), `horarios_clinica`, `horarios_veterinario` y `ausencias_veterinario`. Solo se guardan las reasignaciones que esperan confirmación (`reasignaciones`) y las propuestas de horario (`propuestas_horario`); ver el [MER](MER.md#6-operación-clínica).

El ejemplo muestra el **efecto cascada**: la cirugía de las 09:30 se extiende y el retraso se propaga por la cola del veterinario A; el sistema detecta un espacio libre en el veterinario B y **propone reasignarle** la última cita para absorberlo.

```mermaid
flowchart LR
  subgraph VA[Veterinario A]
    C1[09:00 · Vacunación] --> C2[09:30 · Cirugía ⏱ +15]
    C2 --> C3[10:00 · Control]
    C3 --> C4[10:30 · Consulta]
  end
  subgraph VB[Veterinario B]
    L[10:30 · Espacio libre]
  end
  C4 -. reasignar .-> L

  classDef warn fill:#F7EEDC,stroke:#B7791F,color:#5a4410;
  classDef free fill:#EEF3F0,stroke:#5C6773,color:#26313B,stroke-dasharray:4 3;
  class C2 warn;
  class L free;
  linkStyle 1 stroke:#B23A34,stroke-width:2px;
  linkStyle 2 stroke:#B23A34,stroke-width:2px;
  linkStyle 3 stroke:#2E78B8,stroke-width:2px;
```

**Lectura:** las aristas rojas son la propagación del retraso; la azul punteada es la reasignación que propone el Grafo II y que el veterinario B debe confirmar (RN-428). Sobre este grafo actúa además la **prioridad (triage)** de 4 niveles que calcula el Grafo I: una cita urgente pesa más y puede tomar el próximo espacio (o un sobrecupo) antes que una ordinaria.

---

## 3. Proceso de negocio — Consulta clínica (con Grafo I)

Actores: Veterinario. Entrada desde una cita del calendario, o sin cita vía el módulo de Atención/Urgencias. El sistema apoya en cada paso; la decisión final es siempre del veterinario.

```mermaid
flowchart TD
  A([Veterinario va a atender]) --> B{Como atiende?}

  B -- Desde una cita (calendario) --> C{"Puede iniciar? Es el vet asignado, es el dia de la cita y faltan 15 min o menos (RN-407)"}
  C -- No --> C1[/"Mensaje: no puede iniciar esta atencion"/]
  C1 --> ZF([Fin])
  C -- Si --> D[Iniciar atencion y sellar hora_inicio_real]
  D --> CTX

  B -- "Sin cita / urgencia (modulo Atencion)" --> F{La mascota esta vinculada a la clinica?}
  F -- Si --> F1[Seleccionar la mascota activa]
  F1 --> CTX
  F -- No --> FX{"Ya existe en la plataforma? (buscar por el documento de quien la trae)"}
  FX -- Si --> FX1["Vincularla a esta clinica al atenderla (RN-110, RN-208)"]
  FX1 --> CTX
  FX -- No --> G["Registro rapido de emergencia: nombre, especie, raza o edad de la mascota<br/>y nombre, documento y telefono de quien la trae"]
  G --> G1["Guardar la mascota con marca 'ficha por completar', a nombre de esa persona<br/>(si no tiene cuenta, queda creada pendiente de activar, §11)"]
  G1 --> CTX

  CTX["Contexto del paciente: especie, raza, edad, alergias y alertas, historia<br/>(consultas de otras clinicas solo si el propietario lo autorizo, RN-113)<br/>+ predisposiciones por raza (Grafo I)"] --> H["Form Consulta: motivo, anamnesis,<br/>examen fisico (peso, temperatura, FC, FR), sintomas"]
  H --> I{Campos obligatorios y rangos validos?}
  I -- No --> I1[/"Mensaje: corrige los campos marcados"/]
  I1 --> H
  I -- Si --> J[Grafo I: ordena diagnosticos probables por peso segun los sintomas]
  J --> K{El grafo encontro coincidencias?}
  K -- No --> L[El veterinario escribe el diagnostico]
  K -- Si --> M[Mostrar diagnosticos probables rankeados]
  M --> L
  L --> N{"Diagnostico registrado? (RN-202)"}
  N -- No --> N1[/"Mensaje: el diagnostico es obligatorio"/]
  N1 --> L
  N -- Si --> O[Grafo I sugiere examenes y tratamiento]
  O --> O2[El veterinario revisa y decide: acepta la sugerencia o define el suyo]
  O2 --> P["Form Tratamiento: medicamento, dosis,<br/>via, duracion, observaciones"]
  P --> Q{"Alergia registrada del paciente, toxico para la especie<br/>o contraindicado por raza? (RN-211)"}
  Q -- Si --> Q1[/"Mensaje de bloqueo: elige otro farmaco"/]
  Q1 --> P
  Q -- No --> R{Interaccion con la medicacion vigente?}
  R -- Si --> R1[Advertencia: el vet confirma o cambia. Queda en auditoria]
  R -- No --> S[Calcular dosis por peso del examen fisico y especie]
  R1 --> S
  S --> T{"Hay condicion que ajuste la dosis? (falla renal/hepatica, preñez)"}
  T -- Si --> T1[Ajustar o advertir la dosis]
  T -- No --> U{Agregar otro tratamiento?}
  T1 --> U
  U -- Si --> P
  U -- No --> AL{"Detecto una alergia o alerta nueva?"}
  AL -- Si --> AL1["Registrarla en las alertas de la mascota<br/>(visible para toda clinica vinculada y en el carnet)"]
  AL1 --> V
  AL -- No --> V{Adjuntar archivos?}
  V -- Si --> W{"Tipo y tamaño validos? (JPG/PNG/PDF, max 10MB, RN-204)"}
  W -- No --> W1[/"Mensaje: archivo no permitido o demasiado grande"/]
  W1 --> V
  W -- Si --> X[Confirmar y guardar]
  V -- No --> X
  X --> Y[["Guardado atomico: consulta + tratamientos + archivos;<br/>N de historia si es la 1a (RN-102);<br/>completar la cita si venia de una (RN-406)"]]
  Y --> Y1{Guardado correcto?}
  Y1 -- No --> Y2[/"Mensaje: error al guardar, reintentar"/]
  Y2 --> X
  Y1 -- Si --> Z1[Registrar en auditoria]
  Z1 --> Z2{Fue registro rapido de emergencia?}
  Z2 -- Si --> Z3[Dejar tarea pendiente: completar la ficha del paciente]
  Z2 -- No --> ZF
  Z3 --> ZF
```

---

## 4. Proceso de negocio — Agendamiento de cita (con Grafo II y triage)

Actores: Propietario (desde su portal) y Veterinario (agenda interna de la clínica). El administrador no participa de este flujo. La prioridad (triage) la calcula el Grafo I a partir de los síntomas; el propietario no la elige.

```mermaid
flowchart TD
  A([Agendar cita]) --> ROL{Quien agenda?}
  ROL -- Propietario (su portal) --> P0{"Perfil completo? (RN-G20)"}
  P0 -- No --> P0a[["Completar el perfil (§13.1)"]]
  P0a --> P0b
  P0 -- Si --> P0b["Elige la clinica (entre las vinculadas) y su mascota"]
  P0b --> P1["Marca los sintomas de la lista,<br/>texto libre opcional y cuando empezaron"]
  ROL -- Veterinario (agenda de la clinica) --> V1["Elige mascota; registra sintomas (lista + texto) y cuando empezaron"]
  P1 --> G1
  V1 --> G1
  G1["Grafo I calcula el nivel de triage (HU-2.11):<br/>maximo sin promediar, combinaciones, modificadores,<br/>alarmas universales; sin cobertura = amarillo con revision"]
  G1 --> TRI{Nivel de triage?}
  TRI -- "Rojo (critico)" --> HOR{La clinica esta dentro de su horario?}
  HOR -- No --> HOR1[/"Aviso: clinica cerrada; telefono de urgencias de la clinica<br/>y recomendacion de un servicio 24 horas"/]
  HOR1 --> HOR2[Notificar a la clinica]
  HOR2 --> ZF
  HOR -- Si --> R1[/"Aviso: caso critico, no se agenda en linea"/]
  R1 --> R2["Notificar a la clinica de inmediato y derivar<br/>(sin verificar el limite del plan)"]
  R2 --> RSUB[["Sub-flujo: Atencion de urgencia (6)"]]
  RSUB --> ZF
  TRI -- "Naranja / Amarillo / Verde" --> LIM

  LIM{La clinica esta dentro del limite del plan?}
  LIM -- "No (agenda el propietario)" --> LIM1[/"Mensaje neutral: no es posible agendar en linea ahora"/]
  LIM1 --> LIM2[Notificar a la clinica para que lo gestione]
  LIM2 --> ZF([Fin])
  LIM -- "No (agenda el veterinario)" --> LIM3[/"Mensaje: limite del plan alcanzado, mejora el plan"/]
  LIM3 --> ZF
  LIM -- Si --> DISP["Calcular disponibilidad:<br/>horario clinica ∩ horario del veterinario − ausencias,<br/>por duracion + margen segun el tipo de cita"]
  DISP --> MODO{Como se asigna el espacio?}
  MODO -- "Naranja (urgente): sobrecupo" --> TOP{"Queda cupo de sobrecupo en el proximo bloque?<br/>(tope configurable, por defecto 2)"}
  TOP -- No --> TOP1[/"Aviso al personal: tope de sobrecupos alcanzado, decide"/]
  TOP1 --> ZF
  TOP -- Si --> N1["Ubicar por sobrecupo; si dos compiten,<br/>primero el mas severo y luego el que llego antes"]
  MODO -- "Amarillo (prioritario): primer espacio" --> Y1[El sistema toma el primer espacio disponible priorizado]
  MODO -- "Verde (no urgente): eleccion" --> VE0{Hay espacios disponibles?}
  VE0 -- No --> VE0a[/"Mensaje: no hay espacios, elige otra fecha o rango"/]
  VE0a --> VE0b[El actor cambia la fecha o el rango]
  VE0b --> DISP
  VE0 -- Si --> VE1[El actor elige un espacio de los disponibles]

  N1 --> RES[Reservar el espacio con bloqueo]
  Y1 --> RES
  VE1 --> RES
  RES --> OK{"La reserva se concreto? (nadie lo tomo antes)"}
  OK -- Si --> CRE["Crear la cita con tipo, duracion, margen y prioridad;<br/>guardar los sintomas y marcar si es sobrecupo"]
  OK -- No --> RMSG[/"Ese espacio se acaba de ocupar, se busca otro"/]
  RMSG --> REPICK["Elegir otro espacio: el sistema toma el siguiente (naranja/amarillo)<br/>o lo elige el actor (verde)"]
  REPICK --> RES
  CRE --> AUD[Registrar en auditoria; notificar al propietario y al veterinario]
  AUD --> ZF
```

> **Orden del flujo:** el triage se calcula **antes** de verificar el límite del plan, porque una urgencia 🔴 nunca se bloquea por límite ni por mora (RN-420). Las pruebas del triage están en la batería de casos del repositorio (`documentacion/CasosTriage.md`).
>
> **Niveles de triage (4):** 🔴 Rojo = crítico (no se agenda, va a urgencias), 🟠 Naranja = urgente (sobrecupo, porque la mascota puede agravarse antes del primer espacio), 🟡 Amarillo = prioritario (primer espacio disponible), 🟢 Verde = no urgente (el actor elige). El nivel lo define el Grafo I (RN-411).

---

## 5. Proceso de negocio — Reajuste de agenda en vivo (efecto cascada)

Actores: Veterinario (origen y destino). El umbral de retraso es el **margen (buffer) por tipo de cita**: mientras el retraso cabe en el margen, no pasa nada; cuando lo supera, se propaga y, si una cita **se sale del horario disponible del veterinario**, el Grafo II busca reasignarla. La reasignación la confirma el **veterinario destino** dentro de un plazo (RN-428), y los avisos al propietario se agrupan para no saturarlo (RN-429). La llegada tarde del propietario sigue la RN-426 y también dispara este recálculo.

```mermaid
flowchart TD
  A{{"Evento: una cita termina mas tarde de lo previsto"}} --> B[Calcular el retraso real contra la hora fin + margen del tipo de cita]
  B --> C{El retraso supera el margen disponible?}
  C -- No --> C1[El margen lo absorbe; sin cambios]
  C1 --> ZF([Fin])
  C -- Si --> D[Propagar el retraso por la cola de citas del veterinario]
  D --> E{Alguna cita queda fuera del horario disponible del veterinario?}
  E -- No --> E1[Actualizar horas estimadas; no se reasigna]
  E -- Si --> F[Grafo II busca otro veterinario con disponibilidad para la cita afectada]
  F --> G{Hay veterinario con espacio compatible?}
  G -- Si --> H["Proponer la reasignacion al veterinario destino<br/>(plazo configurable, por defecto 10 min)"]
  H --> I{El veterinario destino la confirma dentro del plazo?}
  I -- Si --> J[Reasignar la cita y notificar al propietario y a ambos veterinarios]
  I -- "No / sin respuesta" --> NX{Hay otro veterinario con espacio compatible?}
  NX -- Si --> H
  NX -- No --> RP[Reprogramar la cita con el propietario]
  G -- No --> RP
  E1 --> U{"El cambio acumulado supera el umbral de aviso<br/>(por defecto 15 min) y no se aviso en los ultimos 30 min?"}
  U -- Si --> M[Avisar el nuevo horario estimado al propietario]
  U -- No --> L([Fin])
  J --> L
  RP --> L
  M --> L
```

---

## 6. Proceso de negocio — Atención de urgencia (caso Rojo / llegada directa)

Actores: Veterinario y Propietario (que trae la mascota). Se dispara cuando el Grafo I marca un caso Rojo o cuando llega una urgencia directa a la clínica. El uso inmediato del espacio altera la agenda, por eso enlaza con el reajuste en vivo (§5) y con la consulta (§3).

```mermaid
flowchart TD
  A([Caso critico: triage Rojo o llegada directa]) --> B["Notificar a la clinica y marcar prioridad maxima<br/>(sin verificar limite del plan ni mora)"]
  B --> C{Hay veterinario disponible de inmediato?}
  C -- Si --> D[Asignar al veterinario disponible]
  C -- No --> PZ{"Algun veterinario tiene en curso una cita pausable?"}
  PZ -- Si --> E["Pausar esa cita segun su tipo (RN-427);<br/>se retoma al terminar la urgencia"]
  E --> E1[Avisar el corrimiento a los afectados]
  PZ -- No --> E2["Asignar al primer veterinario que termine;<br/>informar el tiempo estimado y, si no puede esperar, un servicio 24 horas"]
  E2 --> E1
  D --> F{La mascota esta registrada?}
  E1 --> F
  F -- Si --> F1[Seleccionar la mascota]
  F -- No --> F2["Registro rapido de emergencia (marca 'ficha por completar')"]
  F1 --> G[["Consulta clinica via modulo Atencion/Urgencias (§3)"]]
  F2 --> G
  G --> H{{El uso del espacio altera la agenda del dia}}
  H --> I[["Reajuste de agenda en vivo (§5)"]]
  I --> J([Fin])
```

---

## 7. Proceso de negocio — Configurar horario del veterinario

Actores: Administrador de la clínica. Define el horario **recurrente** por día de la semana en su clínica (el veterinario solo propone cambios, §13.4); esto reemplaza el supuesto de que todos los veterinarios atienden 24/7. Si al cambiar el horario quedan citas por fuera, se marcan para reajuste (§5).

```mermaid
flowchart TD
  A([Administrador configura el horario del veterinario]) --> B[Selecciona al veterinario]
  B --> C[Define las franjas recurrentes por dia de la semana]
  C --> D{"Franjas validas? (sin solapes, dentro del horario de la clinica<br/>y sin chocar con su horario en otra clinica, RN-706)"}
  D -- No --> D1[/"Mensaje: corrige las franjas (solape, fuera del horario o choque con otro compromiso del veterinario)"/]
  D1 --> C
  D -- Si --> E[Guardar el horario recurrente del veterinario]
  E --> F{Quedan citas por fuera del nuevo horario?}
  F -- Si --> G[Marcar esas citas para reasignar]
  G --> H[["Reajuste de agenda (§5)"]]
  F -- No --> I[Registrar en auditoria]
  H --> I
  I --> J([Fin])
```

---

## 8. Proceso de negocio — Ausencia y cobertura del veterinario

Actores: Administrador (registra la ausencia) y Veterinario de cobertura (destino). Una ausencia es la excepción por fecha que tapa la disponibilidad recurrente. El "intercambio de turnos" no es una entidad nueva: es **ausencia del titular + cobertura del reemplazo** (reasignación de sus citas). La trazabilidad de quién atendió de verdad la da la historia clínica (RN-208), no la agenda.

```mermaid
flowchart TD
  A([Administrador registra una ausencia]) --> B[Selecciona al veterinario y el rango de fechas o franjas]
  B --> C{Rango valido?}
  C -- No --> C1[/"Mensaje: revisa las fechas de la ausencia"/]
  C1 --> B
  C -- Si --> D[Guardar la ausencia: tapa la disponibilidad de ese rango]
  D --> E{Hay citas asignadas en ese rango?}
  E -- No --> F[Registrar en auditoria]
  E -- Si --> G[Grafo II busca cobertura: veterinarios con disponibilidad compatible]
  G --> H{Hay veterinario que pueda cubrir?}
  H -- Si --> I[Proponer la cobertura al veterinario destino]
  I --> J{El veterinario destino confirma?}
  J -- Si --> K[Reasignar las citas al veterinario de cobertura]
  K --> L[Notificar al propietario y a ambos veterinarios]
  J -- No --> M[Marcar las citas sin cobertura para gestion manual]
  H -- No --> M
  M --> N[/"Aviso a la clinica: citas sin cobertura por reprogramar"/]
  L --> F
  N --> F
  F --> O([Fin])
```

---

## 9. Flujo del sistema — Acceso y aislamiento por clínica (multi-inquilino)

Cómo el sistema resuelve el contexto (una persona puede tener varios roles: propietario y personal en una o varias clínicas) y confina cada sesión a la clínica de ese contexto. El aislamiento por `id_clinica` es transversal: no depende de la buena voluntad de cada consulta, sino que se centraliza en `helpers/Security.php` y en la capa de modelos, y se cubre con pruebas (RNF-11).

```mermaid
flowchart TD
  A([Peticion entrante: login o accion]) --> B["Autenticar (local o Google) y cargar la identidad con sus roles"]
  B --> C{Autenticado?}
  C -- No --> C1[/"Redirigir a login (HTTP 401)"/]
  C1 --> ZF([Fin])
  C -- Si --> SX{"Sesion vigente? (30 min sin actividad, RN-G17)"}
  SX -- No --> SX1[/"La sesion expiro: volver a iniciar sesion"/]
  SX1 --> ZF
  SX -- Si --> CX{"Tiene mas de un contexto?<br/>(portal de propietario y/o clinicas donde su rol esta activo)"}
  CX -- Si --> CX1[Elegir el contexto: portal de propietario o una clinica con su rol]
  CX -- No --> D
  CX1 --> D{"Es super-administrador (rol plataforma)?"}
  D -- Si --> D1[Operar la plataforma: clinicas, planes y suscripciones]
  D1 --> ZF
  D -- No --> CA{"La clinica del contexto esta activa? (no suspendida ni de baja)"}
  CA -- No --> CA1[/"Mensaje: la clinica no esta disponible; contacta a la clinica"/]
  CA1 --> ZF
  CA -- Si --> E["Sesion atada al contexto activo (rol + id_clinica)"]
  E --> F[Security valida en cada peticion: rol permitido + CSRF + clinica]
  F --> G{Rol y CSRF validos?}
  G -- No --> G1[/"HTTP 403: token o rol invalido"/]
  G1 --> ZF
  G -- Si --> H{"El recurso es de su clinica (id_clinica)?"}
  H -- No --> H1[/"HTTP 403: acceso denegado"/]
  H1 --> H2[Registrar el intento en auditoria]
  H2 --> ZF
  H -- Si --> I[Ejecutar la accion filtrada por id_clinica]
  I --> ZF
```

---

## 10. Flujo del sistema — Login del propietario (multi-clínica)

El propietario entra con su documento o correo y su contraseña, o con Google. Antes de llegar a su portal, el sistema revisa tres cosas: que la cuenta esté verificada, que el perfil esté completo (las cuentas de Google lo completan una vez, §13.1) y que haya aceptado la versión vigente de la política de datos. Una vez adentro ve **todas** sus mascotas y su historia; al agendar, cancelar o calificar elige **solo entre las clínicas a las que está vinculado** ([HU-5.9](HistoriasUsuario.md#hu-59--iniciar-sesión-en-el-portal-multi-clínica), [HU-T.18](HistoriasUsuario.md#hu-t18--registrarme-e-iniciar-sesión-con-google-propietario)). Si la persona tiene además roles de personal, elige el contexto como en el §9.

```mermaid
flowchart TD
  A([Iniciar sesion]) --> MET{Como inicia sesion?}

  MET -- Documento o correo y contraseña --> B[Ingresar documento o correo y contraseña]
  B --> BL{"La IP esta bloqueada por intentos fallidos?"}
  BL -- Si --> BL1[/"Mensaje: demasiados intentos, espera unos minutos"/]
  BL1 --> ZF([Fin])
  BL -- No --> CAP{"La cuenta acumula 5 fallos y no supero el CAPTCHA?"}
  CAP -- Si --> CAP1[Pedir el CAPTCHA]
  CAP1 --> B
  CAP -- No --> C{Credenciales correctas?}
  C -- No --> C1["Sumar el intento fallido (IP y cuenta)"]
  C1 --> C2[/"Mensaje generico: credenciales invalidas"/]
  C2 --> B
  C -- Si --> D{La cuenta esta verificada y activa?}
  D -- No --> D1[/"Mensaje: verifica tu correo para activar la cuenta"/]
  D1 --> ZF

  MET -- Google --> G1["Validar el token de Google (aud, iss, vigencia, correo verificado)"]
  G1 --> G2{Token valido?}
  G2 -- No --> G2a[/"Mensaje: no se pudo iniciar sesion con Google"/]
  G2a --> ZF
  G2 -- Si --> G3{El correo ya tiene cuenta?}
  G3 -- No --> G3a[["Elegir la clinica de la lista y seguir el registro con Google (§11)"]]
  G3a --> ZF
  G3 -- Si --> G4{La cuenta ya esta vinculada a Google?}
  G4 -- No --> G5["Vincular Google a la cuenta (RN-G21); si estaba pendiente de verificar,<br/>queda verificada, se anula su contraseña y el perfil vuelve a pedir sus datos"]
  G5 --> PC
  G4 -- Si --> PC

  D -- Si --> TMP{"Tiene una contraseña temporal?"}
  TMP -- Si --> TMP1[["Cambiar la contraseña antes de seguir (§13.3)"]]
  TMP1 --> PC
  TMP -- No --> PC{El perfil esta completo?}
  PC -- No --> PC1[["Completar el perfil (§13.1)"]]
  PC1 --> POL
  PC -- Si --> POL{"Acepto la version vigente de la politica de datos?"}
  POL -- No --> POL1["Mostrar la nueva politica y pedir aceptarla"]
  POL1 --> POL2{Acepta?}
  POL2 -- No --> POL3[/"No puede continuar; puede pedir la eliminacion de su cuenta"/]
  POL3 --> ZF
  POL2 -- Si --> POL4[Guardar la prueba de aceptacion]
  POL4 --> H
  POL -- Si --> H["Mostrar su portal: todas sus mascotas y su historia,<br/>con la clinica de cada registro"]
  H --> I{Quiere agendar, cancelar o calificar?}
  I -- No --> ZF
  I -- Si --> E{Esta vinculado a mas de una clinica?}
  E -- Si --> F[Elegir entre sus clinicas vinculadas]
  E -- No --> G[Usar su unica clinica]
  F --> J["Ejecutar la accion acotada a esa clinica (id_clinica)"]
  G --> J
  J --> ZF
```


---

## 11. Flujo del sistema — Registro del propietario (multi-clínica)

Tres caminos de registro, siempre en el contexto de una clínica: **alta por el personal**, **enlace o QR de la clínica** y **autoregistro directo** eligiendo la clínica de la lista. En los dos últimos el propietario puede usar el formulario o **Google**; el alta por el personal no usa Google. Todo registro exige aceptar la política de tratamiento de datos ([RN-G19](ReglasNegocio.md#módulo-t--acceso-seguridad-y-administración-reglas-transversales)). Si el correo ya existe, no se duplica la identidad: se **liga** la cuenta existente a la nueva clínica, previa verificación de que es el dueño del correo ([RN-109](ReglasNegocio.md#módulo-1--mascotas-y-propietarios), [RN-G20](ReglasNegocio.md#módulo-t--acceso-seguridad-y-administración-reglas-transversales)).

```mermaid
flowchart TD
  A([Registro de propietario]) --> B{Como se registra?}
  B -- Alta por el personal --> B1[Tomar el id_clinica del personal que lo registra]
  B -- Enlace o QR de la clinica --> B2[Tomar el id_clinica del enlace o QR]
  B -- Autoregistro directo (pagina publica) --> B3["El propietario elige la clinica de la lista (toma su id_clinica)"]
  B2 --> MED
  B3 --> MED
  MED{Formulario o Google?}
  MED -- Formulario --> C
  B1 --> C

  C["Capturar datos: nombre, tipo y numero de documento, correo, telefono, contraseña<br/>y aceptacion de la politica de datos (en el alta por el personal no se pide contraseña:<br/>el titular la crea y acepta la politica al activar desde el correo)"] --> D{"Campos validos (formato, contraseña segura, politica aceptada)?"}
  D -- No --> D1[/"Mensaje: corrige los campos marcados"/]
  D1 --> C
  D -- Si --> E{El correo ya existe en la plataforma?}

  E -- No --> E2{El documento ya existe en la plataforma?}
  E2 -- Si --> E3[/"Mensaje: ese documento ya tiene cuenta; inicia sesion o contacta soporte"/]
  E3 --> ZF([Fin])
  E2 -- No --> F["Crear la identidad (id_usuario) y guardar la prueba de aceptacion de la politica"]
  F --> G["Ligar el propietario a esta clinica (propietario_clinica)"]
  G --> H[Enviar verificacion de correo]
  H --> I{Verifica dentro de 24 horas?}
  I -- No --> I1[El registro pendiente expira]
  I1 --> ZF
  I -- Si --> J[Activar la cuenta y enviar al correo el enlace de acceso y el QR del portal]
  J --> AUD[Registrar en auditoria]
  AUD --> ZF

  E -- Si --> K{Ya esta ligado a esta clinica?}
  K -- Si --> K1[/"Mensaje: ya tienes cuenta en esta clinica, inicia sesion"/]
  K1 --> ZF
  K -- No --> L["Verificar que es el dueño del correo (login o confirmacion por correo)"]
  L --> M{Verificado?}
  M -- No --> M1[/"Mensaje: no se pudo verificar; no se liga"/]
  M1 --> ZF
  M -- Si --> N[Ligar el propietario existente a esta clinica]
  N --> AUD

  MED -- Google --> GG1["Validar el token de Google (aud, iss, vigencia, correo verificado)"]
  GG1 --> GG2{Token valido?}
  GG2 -- No --> GG2a[/"Mensaje: no se pudo continuar con Google"/]
  GG2a --> ZF
  GG2 -- Si --> GG3{El correo ya tiene cuenta?}
  GG3 -- Si --> GG4["Vincular Google a esa cuenta (si estaba pendiente, queda verificada<br/>y se anula su contraseña, RN-G21) y ligarla a esta clinica si no lo estaba"]
  GG4 --> AUD
  GG3 -- No --> GG5["Crear la identidad con nombre, correo y foto de Google<br/>(perfil incompleto, sin verificar correo)"]
  GG5 --> GG6["Ligar a esta clinica (propietario_clinica)"]
  GG6 --> GG7[["Completar el perfil (§13.1)"]]
  GG7 --> AUD
```


---

## 12. Flujos de proceso por módulo

Detalle del funcionamiento de cada proceso de módulo. Cada flujo se corresponde con una o varias historias de usuario (Fase 3), donde se detalla el comportamiento con sus criterios de aceptación.

### 12.1 Registro de clínica (self-service) — Módulo 0

```mermaid
flowchart TD
  A([Visitante abre el registro de clinica]) --> B["Completar datos de la clinica (nombre, NIT, direccion, telefono, correo)<br/>y del administrador inicial (nombre, correo, contraseña);<br/>aceptacion de la politica de datos y los terminos"]
  B --> R{"Supera el limite de registros desde su IP?"}
  R -- Si --> R1[/"Mensaje: demasiados intentos, intentalo mas tarde"/]
  R1 --> ZF([Fin])
  R -- No --> K{"Verificacion anti-bot (CAPTCHA) valida en el servidor?"}
  K -- No --> K1[/"Mensaje: no pudimos verificar que no eres un robot"/]
  K1 --> B
  K -- Si --> C{"Campos completos y formato valido (correo, contraseña segura)?"}
  C -- No --> C1[/"Mensaje: corrige los campos marcados"/]
  C1 --> B
  C -- Si --> N{"NIT valido segun el digito de verificacion de la DIAN?"}
  N -- No --> N1[/"Mensaje: el NIT no es valido, revisa el digito de verificacion"/]
  N1 --> B
  N -- Si --> T{"El correo es de un dominio desechable?"}
  T -- Si --> T1[/"Mensaje: usa un correo permanente de la clinica"/]
  T1 --> B
  T -- No --> D{"El correo o el NIT ya estan registrados?"}
  D -- Si --> D1[/"Mensaje: ya existe una cuenta o clinica con esos datos"/]
  D1 --> DR{"Es su NIT y otra clinica lo registro?"}
  DR -- Si --> DR1["Reportarlo: se crea el caso para el super-administrador,<br/>que verifica la titularidad (RN-010)"]
  DR1 --> ZF
  DR -- No --> ZF
  D -- No --> E[Crear la clinica en estado 'pendiente de verificacion']
  E --> F[Enviar correo de verificacion con enlace temporal]
  F --> G{Verifica el correo dentro de 24 horas?}
  G -- No --> G1[La clinica pendiente expira y se elimina]
  G1 --> ZF
  G -- Si --> H[Activar la clinica, asignar el plan gratuito y crear el rol administrador]
  H --> I[Registrar en auditoria]
  I --> J[El administrador inicia sesion en su clinica]
  J --> ZF
```

### 12.2 Control de límites del plan (freemium) — Módulo 0

```mermaid
flowchart TD
  A([Accion que consume cupo: crear o vincular mascota, crear cita o usuario]) --> U{"Es la atencion de una urgencia roja?"}
  U -- Si --> E
  U -- No --> B[Leer el plan y el estado de la suscripcion de la clinica]
  B --> C{Suscripcion al dia?}
  C -- No --> C1["Aplicar los limites del plan gratuito (RN-007, RN-013);<br/>los datos existentes no se ocultan ni se bloquean"]
  C1 --> D
  C -- Si --> D{"La accion esta dentro del limite del plan?"}
  D -- Si --> E[Ejecutar la accion]
  E --> E1[Registrar en auditoria]
  E1 --> ZF([Fin])
  D -- No --> F{Quien intenta la accion?}
  F -- Personal --> F1[/"Mensaje: limite del plan alcanzado, mejora el plan"/]
  F1 --> ZF
  F -- Propietario --> F2[/"Mensaje: no es posible completar la accion ahora"/]
  F2 --> F3[Notificar a la clinica para que lo gestione]
  F3 --> ZF
```

### 12.3 Registro de mascota y propietario — Módulo 1

La mascota es **global** del propietario: si él ya tiene la mascota en la plataforma (registrada en otra clínica), se **vincula** en lugar de crear una ficha duplicada ([RN-110, RN-111](ReglasNegocio.md#módulo-1--mascotas-y-propietarios)).

```mermaid
flowchart TD
  A(["Registrar mascota (desde la clinica)"]) --> B["Verificar el limite del plan (Modulo 0)"]
  B --> C{Permitido por el plan?}
  C -- No --> C1[/"Mensaje: limite alcanzado, mejora el plan"/]
  C1 --> ZF([Fin])
  C -- Si --> D{"El propietario ya existe? (buscar por documento)"}
  D -- No --> E[["Alta del propietario por el personal (§11)"]]
  D -- Si --> FL{"Ya esta ligado a esta clinica?"}
  FL -- No --> FL1[["Ligarlo a esta clinica con su confirmacion por correo (§11)"]]
  FL1 --> F
  FL -- Si --> F[Seleccionar al propietario]
  F --> P{"El propietario ya tiene mascotas en la plataforma?"}
  P -- Si --> Q["Mostrar sus mascotas (nombre, especie, raza, foto; sin datos clinicos)"]
  Q --> V{Es una de ellas?}
  V -- Si --> W["Vincular la mascota existente a esta clinica (mascota_clinica);<br/>conserva su ficha, carnet y QR"]
  W --> I
  V -- No --> G
  P -- No --> G
  E --> G
  G["Capturar datos de la mascota: nombre, especie, raza, sexo, nacimiento/edad, peso y color;<br/>esterilizado (si se sabe) y foto (opcionales)"] --> H{"Campos obligatorios y formato validos (servidor)?"}
  H -- No --> H1[/"Mensaje: corrige los campos marcados"/]
  H1 --> G
  H -- Si --> J["Guardar la mascota (global, del propietario), vincularla a esta clinica<br/>y generar el carnet con un token aleatorio para el QR"]
  J --> I["Asignar el numero de historia clinica de esta clinica (si es la primera vez)"]
  I --> K[Registrar en auditoria]
  K --> ZF
```

### 12.4 Vacunación y recordatorio automático — Módulo 3

Dos sub-flujos: **A)** el veterinario registra la vacuna; **B)** el sistema envía los recordatorios automáticos.

```mermaid
flowchart TD
  subgraph A["A) Registro de la vacuna (Veterinario)"]
    A1([Registrar vacuna aplicada]) --> A2[Capturar: vacuna, laboratorio, lote, fecha de aplicacion, veterinario]
    A2 --> A3{Campos validos?}
    A3 -- No --> A3a[/"Mensaje: corrige los campos marcados"/]
    A3a --> A2
    A3 -- Si --> A4[Calcular y guardar la proxima dosis segun el esquema]
    A4 --> A5[Programar el recordatorio en el calendario]
    A5 --> A6[Registrar en auditoria]
    A6 --> A7([Fin])
  end

  subgraph B["B) Recordatorio automatico (Sistema)"]
    B1{{Evento: cron diario}} --> B2["Buscar dosis que vencen en 7 y en 1 dia (por defecto, configurable por la clinica);<br/>sin mascotas inactivas ni dosis ya renovadas"]
    B2 --> B3{Hay dosis por vencer?}
    B3 -- No --> B9([Fin])
    B3 -- Si --> B4["Enviar recordatorio por correo al propietario,<br/>a nombre de la clinica que aplico la dosis"]
    B4 --> B5[Registrar el envio en notificaciones]
    B5 --> B6{El envio fue exitoso?}
    B6 -- Si --> B9
    B6 -- No --> B7["Reintentar el envio (hasta 3 veces)"]
    B7 --> B8{Se entrego en algun intento?}
    B8 -- Si --> B9
    B8 -- No --> B8a[Marcar como no entregado y avisar a la clinica]
    B8a --> B9
  end
```

### 12.5 Carnet de emergencia por QR — Módulo 5

El QR lleva un **token aleatorio**, nunca el id de la mascota; el propietario puede regenerarlo ([RN-502 a RN-505](ReglasNegocio.md#módulo-5--portal-del-propietario-multi-clínica)).

```mermaid
flowchart TD
  A([Escanear el QR de la mascota]) --> L{"Supera el limite de consultas desde su IP?"}
  L -- Si --> L1[/"Respuesta 429: intentalo mas tarde"/]
  L1 --> ZF([Fin])
  L -- No --> B{"El token es valido, el carnet esta activo y la mascota tambien?"}
  B -- No --> B1[/"Mensaje generico: carnet no disponible (no revela si la mascota existe)"/]
  B1 --> ZF
  B -- Si --> C["Abrir el carnet en modo solo lectura (pagina no indexable)"]
  C --> D["Mostrar solo datos de emergencia: nombre, foto, especie/raza, alergias y alertas,<br/>vacunas vigentes con su clinica, telefono del propietario y de la clinica"]
  D --> N["Registrar el escaneo y avisar al propietario (fecha y hora;<br/>escaneos seguidos se agrupan en un solo aviso)"]
  N --> U{Quien escanea comparte su ubicacion?}
  U -- Si --> U1[Enviar la ubicacion al propietario]
  U -- No --> E
  U1 --> E{El propietario quiere ver o editar la ficha completa?}
  E -- No --> E1([Fin: vista de emergencia])
  E -- Si --> F[Solicitar inicio de sesion del propietario]
  F --> G{Autenticacion correcta?}
  G -- No --> G1[/"Mensaje: credenciales invalidas"/]
  G1 --> F
  G -- Si --> H["Abrir la ficha completa (lectura y edicion)"]
  H --> ZF
```

### 12.6 Calificación del veterinario — Módulo 8

```mermaid
flowchart TD
  A([Cita marcada como atendida]) --> B[Habilitar la encuesta y notificar al propietario]
  B --> C{El propietario ya califico esta cita?}
  C -- Si --> C1[/"Mensaje: ya calificaste esta cita"/]
  C1 --> ZF([Fin])
  C -- No --> SF{"Quien califica es el veterinario que atendio? (RN-G18)"}
  SF -- Si --> SF1[/"Mensaje: no puedes calificar tu propia atencion"/]
  SF1 --> ZF
  SF -- No --> D["Registrar estrellas (1 a 5) y comentario opcional"]
  D --> E{"Datos validos (calificacion obligatoria)?"}
  E -- No --> E1[/"Mensaje: selecciona una calificacion"/]
  E1 --> D
  E -- Si --> F[Guardar la reseña]
  F --> V{"Correo del propietario verificado y la cita tiene consulta registrada?"}
  V -- No --> V1["Guardar sin contar para la reputacion"]
  V1 --> J
  V -- Si --> R[Recalcular el promedio del veterinario]
  R --> G{Alcanza el minimo de reseñas?}
  G -- Si --> H[Mostrar el promedio en su perfil publico]
  G -- No --> I[Mantener el promedio oculto hasta alcanzar el minimo]
  H --> J["Registrar en auditoria (la clinica no puede editar ni borrar la reseña;<br/>solo el super-administrador puede ocultarla, con motivo)"]
  I --> J
  J --> ZF
```

---

## 13. Flujos del sistema — Cuenta y perfil

Lo que una persona puede hacer con su propia cuenta: completar el perfil cuando se registró con Google, cambiar sus datos y su contraseña, y, si es veterinario, mantener su perfil público. Las reglas están en el Módulo T de las [Reglas de Negocio](ReglasNegocio.md#módulo-t--acceso-seguridad-y-administración-reglas-transversales) (RN-G19 a RN-G24) y las historias en [HU-T.2](HistoriasUsuario.md#hu-t2--cambiar-mi-contraseña), [HU-T.5](HistoriasUsuario.md#hu-t5--ver-y-actualizar-mi-perfil-y-datos-de-contacto) y [HU-T.18](HistoriasUsuario.md#hu-t18--registrarme-e-iniciar-sesión-con-google-propietario).

### 13.1 Completar el perfil (cuenta creada con Google)

No es una pantalla aparte que la persona busque: aparece sola apenas termina el registro con Google y vuelve a aparecer en cada inicio de sesión hasta que el perfil esté completo.

```mermaid
flowchart TD
  A(["Se dispara al terminar el registro con Google (§11)<br/>y en cada inicio de sesion mientras el perfil siga incompleto (§10)"]) --> B["Pantalla obligatoria: tipo y numero de documento, telefono<br/>y aceptacion de la politica de datos"]
  B --> C{"Campos completos, formato valido y politica aceptada?"}
  C -- No --> C1[/"Mensaje: corrige los campos marcados"/]
  C1 --> B
  C -- Si --> D{El documento ya pertenece a otra cuenta?}
  D -- Si --> D1[/"Mensaje: ese documento ya tiene cuenta; inicia sesion con ella o contacta soporte"/]
  D1 --> D2[Crear el caso para el super-administrador: posible cuenta duplicada]
  D2 --> ZF([Fin: el perfil sigue incompleto])
  D -- No --> E[Guardar documento y telefono; guardar la prueba de aceptacion de la politica]
  E --> F[Marcar el perfil como completo]
  F --> G[Registrar en auditoria]
  G --> H([Portal habilitado: registrar mascotas y agendar])
```

### 13.2 Cambiar mis datos: teléfono, correo y documento

```mermaid
flowchart TD
  A([Editar mi perfil]) --> Q{Que cambia?}

  Q -- Telefono --> T1{Formato valido?}
  T1 -- No --> T2[/"Mensaje: revisa el telefono"/]
  T2 --> A
  T1 -- Si --> T3[Guardar]
  T3 --> AUD

  Q -- Correo --> CP{"La cuenta tiene contraseña?"}
  CP -- No --> CP1[/"Primero crea una contraseña (§13.3): al cambiar el correo se retira Google"/]
  CP1 --> ZF
  CP -- Si --> CI["Confirmar identidad (contraseña o Google)"]
  CI --> C1{"Identidad confirmada, formato valido y no lo usa otra cuenta?"}
  C1 -- No --> C2[/"Mensaje: ese correo no esta disponible"/]
  C2 --> A
  C1 -- Si --> C3["Enviar verificacion al correo nuevo<br/>(el anterior sigue vigente mientras tanto)"]
  C3 --> C4{Verifica dentro del plazo?}
  C4 -- No --> C5[El cambio expira; se conserva el correo anterior]
  C5 --> ZF([Fin])
  C4 -- Si --> C6[Aplicar el correo nuevo]
  C6 --> C7[Avisar del cambio al correo anterior]
  C7 --> C8{La cuenta estaba vinculada a Google con el correo anterior?}
  C8 -- Si --> C9[Retirar la vinculacion con Google]
  C8 -- No --> AUD
  C9 --> AUD

  Q -- Documento --> D1["Confirmar identidad (contraseña o Google)"]
  D1 --> D2{Confirmada?}
  D2 -- No --> D3[/"Mensaje: no se pudo confirmar tu identidad"/]
  D3 --> ZF
  D2 -- Si --> D4{"Formato valido para el tipo de documento?"}
  D4 -- No --> D5[/"Mensaje: revisa el tipo y el numero de documento"/]
  D5 --> A
  D4 -- Si --> D6{El documento ya pertenece a otra cuenta?}
  D6 -- Si --> D7["No se aplica; crear el caso para el super-administrador<br/>(posible cuenta duplicada)"]
  D7 --> ZF
  D6 -- No --> D8[Aplicar el documento nuevo]
  D8 --> D9[Avisar por correo al titular]
  D9 --> AUD

  AUD["Registrar en auditoria (con el valor anterior)"] --> ZF
```

### 13.3 Crear o cambiar mi contraseña

```mermaid
flowchart TD
  A([Contraseña en mi perfil]) --> B{La cuenta ya tiene contraseña?}
  B -- Si --> C[Cambiar contraseña: pedir la contraseña actual]
  C --> C1{La contraseña actual es correcta?}
  C1 -- No --> C2[/"Mensaje: la contraseña actual no es correcta"/]
  C2 --> C
  C1 -- Si --> N
  B -- "No (cuenta creada con Google)" --> G["Crear contraseña: volver a confirmar la identidad con Google"]
  G --> G1{Confirmada?}
  G1 -- No --> G2[/"Mensaje: no se pudo confirmar con Google"/]
  G2 --> ZF([Fin])
  G1 -- Si --> N
  N["Ingresar la nueva contraseña<br/>(se muestran los requisitos de la politica mientras se escribe)"] --> P{Cumple la politica de contraseñas?}
  P -- No --> P1[/"Mensaje: la contraseña no cumple los requisitos"/]
  P1 --> N
  P -- Si --> S[Guardar el hash de la nueva contraseña]
  S --> SS[Cerrar las demas sesiones abiertas y avisar del cambio por correo]
  SS --> AUD[Registrar en auditoria, sin la contraseña]
  AUD --> F([Fin: puede entrar con contraseña y, si tenia, con Google])
```

### 13.4 Perfil del veterinario

```mermaid
flowchart TD
  A([El veterinario abre su perfil]) --> Q{Que hace?}

  Q -- Editar bio o foto publicas --> B1{"Foto con tipo y tamaño validos?"}
  B1 -- No --> B2[/"Mensaje: revisa el archivo de la foto"/]
  B2 --> A
  B1 -- Si --> B3[Guardar; el perfil publico se actualiza]
  B3 --> AUD

  Q -- Ver sus especialidades --> E1[Solo lectura: las asigna el administrador de la clinica]
  E1 --> ZF([Fin])

  Q -- Proponer un cambio de horario --> H1[Proponer franjas por dia para la clinica del contexto activo]
  H1 --> H2{"Sin solapamientos, dentro del horario de la clinica<br/>y sin chocar con su horario en otra clinica?"}
  H2 -- No --> H3[/"Mensaje: la franja se solapa, queda fuera del horario o choca con otro compromiso"/]
  H3 --> H1
  H2 -- Si --> H4[Enviar la propuesta al administrador]
  H4 --> H5{El administrador la aprueba?}
  H5 -- No --> H6[Avisar al veterinario el rechazo y el motivo]
  H6 --> AUD
  H5 -- Si --> H7[Aplicar el nuevo horario]
  H7 --> H8{Quedan citas fuera del nuevo horario?}
  H8 -- Si --> H9[["Marcar esas citas para reajuste (§5)"]]
  H9 --> AUD
  H8 -- No --> AUD

  Q -- Ver sus calificaciones --> R1{Tiene 5 o mas reseñas validas?}
  R1 -- Si --> R2[Ver su promedio y sus reseñas]
  R1 -- No --> R3[Ver sus reseñas; el promedio publico aun no se muestra]
  R2 --> ZF
  R3 --> ZF

  AUD[Registrar en auditoria] --> ZF
```
