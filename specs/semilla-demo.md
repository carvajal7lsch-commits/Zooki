# Semilla de demostración para la sustentación (2.3.0)

**Ajuste de Sala y triage (2026-11-03, escribió Codex):** Nala conserva su caso amarillo con revisión; la semilla agrega la descripción «Camina de lado…» para Otro síntoma, obligatoria en las evaluaciones nuevas, sin cambiar el resultado del motor ni añadir pacientes.

> Plan de una sola entrega. Arquitecto: Claude. Ejecutor: Codex. Va **después** de «Refuerzo del conocimiento tras pruebas (2.3.0)», porque usa Collie, ivermectina y los fármacos nuevos.

**Estado:** S.1 revisada; guion de navegador y ajuste de escena entregados, pendientes de revisión de Claude.

## Decisión del dueño — continuidad del guion (2026-11-03)

- Sustituye el ensayo sin guardar de la decisión inicial: Rocco se finaliza desde Atención en el tercer paso del userscript; es la única consulta que guarda.
- Mishi tiene la primera reserva de la veterinaria principal después de Rocco, con llegada registrada y sin inicio; el naranja queda posterior a esa primera reserva y la jornada conserva cierre20:00 sin reajustes ni propuestas abiertas.
- Orden: Agenda → Rocco (3 pasos) → Agenda (resaltar recálculo e iniciar Mishi desde su cita) → Atención de Mishi (síntomas y bloqueo de paracetamol, sin guardar) → Portal.
- --escena rehace el día, incluida la consulta/tratamientos de Rocco y el inicio de Mishi; Simón conserva una única evaluación real desde el Portal, limpiada al repetir.
- El descarte se lee debajo de los tratamientos, con motivo/paciente/fuente del motor, escudo, rojo suave y enlace Ver fuente; no se modifica el conocimiento ni el motor.
- Verificación autorizada: solo SemillaDemoTest y recorrido Chrome contra la semilla temporal; escribe Codex y revisa Claude.

## Objetivo

Que en la sustentación (5 minutos) Zooki se vea como una plataforma en uso: una clínica principal llena y creíble donde lucen los dos grafos, una segunda clínica en plan gratuito que muestra el freemium y el panel del super-administrador con varias clínicas en estados distintos.
Todo se carga con un script repetible que primero se prueba en zooki-pruebas y después se corre en producción.

## Decisiones del dueño (2026-10-31)

- **Todas las clínicas quedan en Florencia (Caquetá).** Hoy la clínica solo tiene `direccion` (sin ciudad), y el portal no filtra por ciudad. Así se evita que un propietario de Florencia vea clínicas de Neiva. El discurso: «empieza en Florencia y se puede extender a cualquier ciudad».
- Dos clínicas completas (**Centro Veterinario San Roque**, plan Profesional, y **Veterinaria Huellitas**, plan Gratuito) más **tres livianas** para el panel de la plataforma.
- Los correos de la demo son alias del Gmail del dueño: `zooki.vet+<alias>@gmail.com`. Así los avisos de los crons de producción le llegan a él y no rebotan.
- La fecha de la sustentación no está confirmada (semana del 2 de noviembre de 2026). Por eso el script recibe `--dia` y tiene un modo para preparar la escena justo antes de presentar.
- Las fotos de las mascotas las sube el dueño a mano (5 o 6 fichas que va a mostrar). El script no carga imágenes.
- **Confirmado por el dueño,2026-11-01:** Max muestra la interacción al elegir prednisolona en Alternativas; oclacitinib conserva la precarga vigente, sin cambiar motores.
- **Decisión inicial sustituida por S.1,2026-11-01:** se rechazaba la escena si no cabía en las franjas partidas; ahora rige la jornada continua y la ventana de S.1, indicadas al final de este plan.

## Propuestas del arquitecto (pendientes de que el dueño las objete)

- **No se crean pagos de Wompi falsos.** Las suscripciones quedan asignadas como activas. Un pago real de prueba se hace en el sandbox de zooki-pruebas.
- **No se siembran reseñas ni perfiles de veterinario.** Las tablas `resenas_veterinario`, `veterinario_perfil` y `veterinario_especialidades` existen, pero ninguna pantalla ni motor las usa todavía.

## Script

`scripts/semilla_demo.php`, solo para consola.

| Modo | Qué hace |
|---|---|
| `--entorno=local\|pruebas\|produccion` | Obligatorio. Si no es `local`, pide escribir el nombre del entorno para confirmar. |
| *(sin modo)* | Muestra la base de destino, verifica los requisitos y dice qué haría, sin escribir. |
| `--si [--dia=AAAA-MM-DD] [--clave=X]` | Borra solo lo de la demo y lo vuelve a crear de forma determinista. `--dia` (por defecto hoy) es el «hoy» de la agenda; la historia se calcula hacia atrás y las próximas citas hacia adelante. Con `--clave`, todas las cuentas usan esa clave si cumple la política; si no, genera claves. Al final imprime las cuentas y sus claves. |
| `--escena` | Rehace **solo la agenda del día** de San Roque con respecto a la hora actual (ver «Escena»). Se corre minutos antes de presentar. |
| `--borrar` | Elimina únicamente lo de la demo. |

Reglas:

- **Lo de la demo es identificable y se borra completo, en el orden de las llaves foráneas:** clínicas por NIT del rango de demo y usuarios con correo `zooki.vet+demo-…@gmail.com`.
  Nunca toca la clínica «Zooki» (zooki.vet@gmail.com), el super-administrador, otras clínicas, el conocimiento ni los catálogos globales.
  Las mascotas globales del propietario de demo también se borran, con todo lo suyo.
- **Requisitos que verifica antes de escribir:** migraciones al día (incluida la 18), conocimiento cargado y presencia de los códigos que usa (`raza.collie`, `farmaco.ivermectina`, etc.). Si falta algo, se niega y dice qué correr (`php scripts/migrar.php`, `php scripts/cargar_conocimiento.php`).
- **Usa los modelos o helpers del sistema donde hay invariantes** (alta de clínica con `InicializadorClinica`, contraseñas, política de datos aceptada, vínculos `usuario_clinica`, `propietario_clinica`, `mascota_clinica`). SQL directo solo para la historia.
- **El nivel de cada solicitud del portal se calcula con `MotorTriage` sobre el conocimiento cargado**, no se escribe a mano. Igual con los diagnósticos y tratamientos de la historia: se eligen del grafo y los síntomas marcados son los característicos del diagnóstico.
- Corre dentro del contenedor Docker (pruebas y producción) y en local. No lee `.env` más allá de lo que ya hace `config/Database.php`.
- Todo en una transacción por fase. Si algo falla, no deja la demo a medias.
- Datos inventados pero creíbles: nombres colombianos comunes, cédulas de un rango fijo de demo, teléfonos con `ValidadorTelefono`, NIT con dígito de verificación válido (`ValidadorNit`) y direcciones de barrios de Florencia (Centro, Juan XXIII, La Estrella, Los Alpes, Siete de Agosto, Atalaya…).

## Datos

### Centro Veterinario San Roque (plan Profesional, activa)

- 1 administradora y 3 veterinarios con jornada continua de lunes a sábado,7:00–20:00 (decisión vigente S.1 del dueño).
- Unos 25 propietarios y unas 35 mascotas. Mayoría de criollos (perro «Criollo (Mestizo)» y gato «Criollo») y también Collie, Gran danés, Bulldog francés, Golden hembra entera, Labrador, Pastor alemán, Poodle, Shih tzu y gatos machos y hembras. Pesos coherentes con la raza y la edad.
- **Historia de 6 meses:** unas 120 consultas con síntomas, diagnóstico del grafo, tratamiento (pautas de `MotorPrescripcion`) y peso, más vacunas y desparasitaciones. Solo3 vacunas próximas en7 días; las demás vencidas fuera de la ventana de recordatorio.
- **Alertas médicas:** al menos 3 alergias enlazadas a fármacos del grafo, una epilepsia (`condicion.epilepsia`) y medicación vigente.
- **Clínica viva (S.1):**6 citas por veterinario el día de demo y5 por veterinario en cada uno de los5 días hábiles siguientes, de tipos existentes; si el día elegido es domingo la agenda comienza el lunes.
- Carnet QR configurado en varias mascotas.

### Pacientes guion (los que se muestran en Atención)

| Paciente | Para mostrar |
|---|---|
| **Rocco**, Collie macho de 4 años, 28 kg | Síntomas de sarna: Zooki sugiere sarna sarcóptica, precarga selamectina y deja la ivermectina bloqueada por MDR1 con su fuente. |
| **Max**, criollo de 9 años, 30 kg, con meloxicam vigente | Dermatitis atópica: oclacitinib precargado; al elegir prednisolona en Alternativas aparece la interacción con meloxicam (decisión del dueño,2026-11-01). |
| **Mishi**, gata criolla de 5 años, 4,2 kg, alérgica a un fármaco del grafo | Conserva la alergia del guion; en la demo en vivo se marcan fiebre, decaimiento y vómito y se elige paracetamol para mostrar el bloqueo por toxicidad felina con su fuente. |
| **Simón**, gato criollo macho de 3 años | El propietario evalúa realmente «va a la arena y no hace» y dolor al orinar en el Portal: **rojo**, aviso de emergencia y derivación, sin reserva; la semilla no crea previamente esa solicitud. |
| **Luna**, Golden hembra entera de 6 años | Solicitud con días desde el celo: **naranja**. |
| **Kira**, cachorra criolla de 3 meses sin vacunas registradas | Vómito y diarrea: **naranja**, sin revisión. |

La **propietaria principal del portal** (cuenta para entrar en el celular) tiene a Simón y a otra mascota con historia y carnet, y está vinculada a San Roque y a Huellitas (mascota global, historia por clínica).

### Escena (`--escena`, agenda del día de la veterinaria principal)

Se calcula desde la hora actual H, redondeada a 5 minutos:

- De0 a3 citas completadas en las2 horas antes de H, según las que caben desde la apertura, con `hora_inicio_real`, `hora_fin_real` y duración objetivo.
- **Rocco** es la única cita **en curso**, iniciada en H−35 min con duración de30 min y llegada registrada5 min antes del inicio; Atención abre habilitada y la cascada muestra el retraso real.
- De2 a4 reservas posteriores y una **naranja de sobrecupo**, usando Consulta general y Vacunación/Revisión rápida; todas las estimaciones de la escena terminan, con margen, antes del cierre.
- **Mila queda completada**, con horas reales coherentes; nunca queda como segunda cita en curso.
- Solicitudes del portal del día sembradas: naranja (Luna, Kira), amarilla en revisión y verde; **Simón solo se evalúa en vivo**.
- `--escena` elimina la solicitud de Simón de ese día en San Roque, aunque se haya elegido otro veterinario; `--si` la elimina al recrear toda la demo.
- Nunca deja citas «sin cerrar» ni pausas abiertas: con eso todo el día se iría a revisión.
- Recalcula la cascada al terminar (`CascadaAgenda::procesar`) para que la agenda abra ya estimada.

### Veterinaria Huellitas (plan Gratuito, activa)

- Administradora y un veterinario: personal en **2/2**. Al invitar a un tercero aparece el límite del plan.
- Jornada continua de lunes a sábado,7:00–20:00, igual que San Roque.
- 8 mascotas con unas pocas consultas. Una de ellas es la mascota global de la propietaria principal, con historia propia en esta clínica.

### Clínicas livianas (solo clínica y administrador)

- Clínica Veterinaria El Bosque: activa, plan Profesional.
- PetCare Caquetá: pendiente de verificación.
- Centro Animal La Estrella: suspendida por mora.

## Pruebas

- Integración sobre `zooki_test_base_v2` (nunca `zooki_v2_prueba`):
  - Correr `--si` dos veces da los mismos conteos (repetible).
  - `--borrar` deja intactas la clínica «Zooki», el super-administrador y una clínica ajena creada por la prueba.
  - Aislamiento: el personal de Huellitas no ve nada de San Roque.
  - Las solicitudes sembradas tienen el nivel esperado; Simón no se precarga, su evaluación real da rojo/derivada y rehacer la escena la elimina.
  - `--escena` deja Rocco como única cita en curso, llegada anterior al inicio, Mila completada, ninguna «sin cerrar» y la cascada calculada.
  - Se niega si falta un código del conocimiento.
- Sin suites completas: solo la prueba nueva y las de los modelos que use. El CI corre la suite en MySQL 8 después del push.

## Para el dueño (pasos)

1. zooki-pruebas: desplegar la rama, entrar al contenedor desde Dokploy y correr `php scripts/migrar.php`, `php scripts/cargar_conocimiento.php`, `php scripts/semilla_demo.php --entorno=pruebas --si --clave=<clave>`.
2. Revisar las pantallas.
3. Producción: la víspera, lo mismo con `--entorno=produccion --dia=<día de la sustentación>`. Minutos antes de presentar: `php scripts/semilla_demo.php --entorno=produccion --escena`.
4. Alternativa sin consola: con `ZOOKI_DEMO_HABILITADA=1`, Plataforma → pestaña Demo hace lo mismo (ver abajo). Después de la sustentación, volver a `0`.

### Botón en el panel de plataforma (decisión del dueño, 2026-11-03)

Para no entrar al VPS, el super-administrador tiene la pestaña **Demo** en Plataforma, con la tarjeta «Modo demo».
Llama a la misma clase `SemillaDemo` que el script; no ejecuta nada por consola.

- **Interruptor:** `ZOOKI_DEMO_HABILITADA=1` en las variables del entorno (Dokploy) o en `.env`. Ausente o en `0`, la pestaña no se dibuja y las tres rutas responden 404 a cualquiera.
- **En producción se enciende antes de la sustentación y se apaga después** (de vuelta a `0` y redesplegar o reiniciar el servicio).
- **Crear o rehacer demo** = `--si`: fecha (hoy por defecto) y una clave común para todas las cuentas. La clave es obligatoria: se valida al escribir con la política de contraseñas y el servidor la vuelve a validar, también contra los datos de cada persona de la demo.
- **Preparar escena ahora** = `--escena`: muestra el día y la hora de la escena, los niveles del guion y los conteos.
- **Borrar demo** = `--borrar`: pide confirmación con el modal de Zooki porque no se puede deshacer.
- La respuesta muestra conteos, hora de la escena, cuentas (rol, clínica, correo) y el error tal cual lo da `SemillaDemo` (por ejemplo, fuera de horario o «Falta la demo; ejecuta primero --si»). Nunca muestra ni audita la clave; un error de base de datos se resume y queda en el registro del servidor.
- Mientras corre, los tres botones quedan deshabilitados y el pulsado dice «Preparando…». En el servidor, la sesión se libera antes de empezar y el candado `GET_LOCK` de `SemillaDemo` rechaza una segunda ejecución con «Otra preparación de demo está en curso.».
- Rutas `plataforma_demo_crear_ajax`, `plataforma_demo_escena_ajax` y `plataforma_demo_borrar_ajax`: solo POST con CSRF y solo el contexto de plataforma en la matriz de `helpers/Security.php`; administrador, veterinario y propietario reciben 403.
- Cada ejecución deja una fila en `auditoria_sistema` (sin clínica): quién, cuándo, modo, día y resultado (`Modo demo: escena (ok)` o `(error)` con el motivo).
- El script de consola sigue igual y es la vía de respaldo.

### Consola del contenedor

En Dokploy, abrir la consola del servicio PHP del entorno correspondiente.
Los comandos siguientes parten de `/var/www/html/Zooki`, el directorio de trabajo de la imagen.
No necesitan PHPUnit ni dependencias de desarrollo.
El script muestra la base conectada; fuera de local pide escribir `pruebas` o `produccion`, según el argumento, antes de conectarse.
Sin `--si` se limita a revisar y simular.
Omitir `--clave` genera claves distintas y las imprime: guardar esa salida fuera de Git.
Para una clave común, agregar `--clave=CLAVE_ELEGIDA`; el script valida la política para todas las personas antes de borrar.

zooki-pruebas:

```sh
php scripts/migrar.php
php scripts/cargar_conocimiento.php
php scripts/semilla_demo.php --entorno=pruebas
php scripts/semilla_demo.php --entorno=pruebas --si --dia=2026-11-02
php scripts/semilla_demo.php --entorno=pruebas --escena
```

Producción, reemplazando la fecha del ejemplo por la sustentación:

```sh
php scripts/semilla_demo.php --entorno=produccion
php scripts/semilla_demo.php --entorno=produccion --si --dia=2026-11-02
php scripts/semilla_demo.php --entorno=produccion --escena
php scripts/semilla_demo.php --entorno=produccion --borrar
```

`--si` reemplaza la demo anterior, incluidas claves y tokens QR; cargar las fotos manuales después de la última recreación.
PetCare conserva su administrador pendiente de activación; no se altera esa regla para permitirle entrar.
`--escena` usa siempre el día y la hora reales, sin admitir `--dia`, y solo reemplaza la agenda de la veterinaria principal de ese día.
Funciona de8:00 a18:00 de lunes a sábado, con jornada continua7:00–20:00; fuera de esa ventana se rechaza antes de modificar la agenda.
Si alguien vinculó una persona o mascota de demo con una clínica ajena, el borrado se rechaza completo, incluso si el vínculo está inactivo.
Se exige un super-administrador activo existente para conceder las suscripciones mediante el modelo; si falta, se indica `php scripts/crear_superadmin.php`, sin crear ni modificar esa identidad desde la demo.

### Fases y recuperación

La primera transacción borra la demo anterior y crea identidades, vínculos e historia.
Los horarios se guardan en una unidad coordinada por clínica; la agenda, en otra unidad coordinada de San Roque.
Si falla una fase posterior, se elimina el nuevo conjunto completo y se conserva el resto de la plataforma.
Un candado por base evita dos ejecuciones simultáneas de esta semilla.
La escena es una única unidad coordinada: cualquier error revierte también el borrado de su día.
Las llaves foráneas permanecen activas y las dependencias se eliminan antes que sus padres; solo se corta una referencia anulable cuando existe un ciclo.

## Anexo — Resultado

- **Escribió Codex, 2026-11-01; revisa Claude (arquitecto). Entregada, pendiente de revisión.**
- `scripts/semilla_demo.php` y apoyos en `scripts/demo/`: simulación, creación, escena y borrado; confirmación escrita fuera de local, requisitos completos y candado por base.
- Alta, vínculos, contraseñas, consentimiento, planes, horarios, reservas, llegada y cascada con modelos/helpers existentes; SQL clínico para historia, sin modificar motores ni conocimiento.
- Guion verificado: Rocco/selamectina y bloqueo MDR1 con fuente, Max/meloxicam y prednisolona en Alternativas, Mishi/alergia; Simón rojo, Luna/Kira naranja, Nala amarillo con revisión, Toby verde.
- Integración y modelos utilizados: **196 pruebas/1327 comprobaciones verdes**, con motor local temporal y la base predeterminada `zooki_test_base_v2`.
- Último refuerzo de borrado, incluyendo invitación pendiente ajena: repetibilidad y escena **2 pruebas/105 comprobaciones verdes**, en `scratch/r/semilla-demo/phpunit.log`; entrada CLI sin dependencias de desarrollo comprobada.
- Evidencia dirigida: `scratch/r/semilla-demo/modelos-y-semilla.log`; sintaxis PHP y diff sin errores, Historial2.3.0 y PDF/Word actualizados.
- Corrida CLI real: simulación sin escritura, `--si --dia=2026-11-02`, confirmación de entorno `pruebas`, negativa de escena en domingo y `--borrar` satisfactorios, sin `.env`.
- Salida completa de **cuentas (rol, clínica, correo y clave generada)** y conteos: [corrida-local.log](../scratch/r/semilla-demo/corrida-local.log), evidencia local ignorada por Git para no versionar claves.
- Conteos creados: **5 clínicas, 41 usuarios, 42 mascotas, 43 vínculos de mascota, 128 consultas, 41 vacunas, 42 desparasitaciones, 5 alertas, 168 citas y 45 solicitudes**; borrado final: todos los conteos de demo en 0.
- Protecciones verificadas: Zooki, superadministrador, personas y mascotas ajenas intactos; rechazo de vínculos ajenos inactivos e invitaciones pendientes ajenas, NIT ocupado, clave inválida, migración18 y código del grafo ausentes.
- Escena repetida con reloj controlado: 3 completadas, 1 en curso, 5 pendientes, sobrecupo naranja, Rocco llegado, cascada calculada y sin pausas abiertas ni «sin cerrar»; historia anterior intacta.
- Pendiente: revisión de Claude y ensayo del dueño en zooki-pruebas/Docker; no hay Docker instalado localmente. Conocimiento conserva su validación veterinaria pendiente.
- Versión 2.3.0 conservada; no se hicieron commits ni push y no se tocaron pantallas, motores, conocimiento, fotos ni pagos.


## Revisión de Claude (2026-11-01)

Refuerzo (`c521fc2`) **aprobado**. Banco de pruebas de Claude con el código nuevo: diagnósticos 26/26 en primer lugar, revisiones del triage del 19 % al 13 % (lo que queda es sobre todo el celo de perras enteras), permetrina en gato roja, chocolate sin hora naranja, Collie sin ivermectina precargada (escrita a mano la bloquea), prednisolona con interacción y osteoartritis con un solo AINE precargado.

Semilla (`fe730c9`) **aprobada con corrección antes de producción**. Corrida real de Claude en MariaDB temporal: `--si` en 5 s con los conteos del anexo, niveles del guion correctos, `--borrar` deja solo al super-administrador.

| # | Tipo | Hallazgo | Destino |
|---|---|---|---|
| 1 | P1 demo | `--escena` solo funciona entre 10:00 y 11:30 de lunes a sábado (probado con faketime de 07:30 a 17:00). La hora de la sustentación no se conoce. | Corrección S.1 |
| 2 | P2 | `--si` deja 40 correos NUEVA_CITA pendientes y unas 28 vacunas en ventana de recordatorio: en producción llegan decenas de correos de golpe a la cuenta Gmail que envía. | Corrección S.1 |
| 3 | P2 | El día de la demo solo la veterinaria principal tiene agenda; los días siguientes, 4 citas en total para 3 veterinarios. La clínica se ve vacía. | Corrección S.1 |
| 4 | P3 | La ivermectina bloqueada no se ve en las sugerencias del Collie: el bloqueo solo aparece al escribirla a mano. | Corrección S.1 (mostrar «Descartada por seguridad») |
| 5 | P3 | Buscador: «le huele feo la boca» ya no encuentra Mal aliento; milbemicina sin advertencia MDR1 en Collie. | Corrección S.1 |

## S.1 — Corrección aprobada (2026-11-01)

- Escribe Codex; revisa Claude (arquitecto); alcance limitado a los hallazgos1–5 de esta revisión.
- **Decisión del dueño:** jornada continua7:00–20:00 de lunes a sábado para San Roque y Huellitas; escena entre8:00 y18:00, adaptando completadas y posteriores, con tipos reales existentes, sin tipo exclusivo ni estimaciones fuera del cierre.
- **Decisión del dueño:** no agregar migraciones ni cambiar `Recordatorio`; las citas diarias conservan sus recordatorios reales al Gmail del dueño, unos15 por día.
- El máximo de5 correos de `--si`/`--escena` excluye esos recordatorios diarios; contar por separado eventos de agenda, recordatorios de dosis y citas diarias.
- Los correos de agenda de la semilla quedan `omitido` con motivo de demo, excepto los eventos generados por la escena para Rocco; máximo3 vacunas en ventana y las demás vencidas.
- Los3 veterinarios tienen6 citas el día de demo y5 en cada uno de los5 siguientes días hábiles; la escena reemplaza solo las de la veterinaria principal y conserva las reservas y horas de los otros dos.
- **Confirmación posterior del dueño:** la cascada puede actualizar las estimaciones de los otros veterinarios; sus citas anteriores a H quedan completadas con horas reales coherentes y las posteriores caben antes del cierre.
- Después de la escena, ninguna cita del día de San Roque queda en `pendiente_revision`, `pendiente_reasignacion` o `pendiente_reprogramacion`, ni con propuestas abiertas; verificar a8:00,12:30,15:00 y18:00.
- `MotorPrescripcion::sugerir` separa las pautas bloqueadas como `descartadas`, con seguridad pública, motivo y fuente; nunca entran en `tratamientos` ni se precargan.
- Atención muestra una línea informativa junto a Alternativas; el modal compartido conserva su presentación; se verifican Collie descartada y criollo alternativa.
- Sinónimos de mal aliento solicitados y4 advertencias clínicas de milbemicina/MDR1; la fuente distingue dosis rotuladas de otras formulaciones y la raza no confirma el genotipo.
- Verificación acordada: SemillaDemoTest con reloj a8:00,9:00,12:30,14:00,16:00 y18:00; CasosReforzadosTest, MotorPrescripcionTest, ConocimientoRealTest y ConsultaSugerida230.test.cjs; corrida CLI temporal a dos horas distintas.

## Anexo S.1 — Resultado

- **Escribió Codex,2026-11-01; revisa Claude (arquitecto). Entregada, pendiente de revisión.**
- Hallazgos1/3: jornada continua7:00–20:00 de lunes a sábado, escena8:00–18:00 con tipos reales; completadas adaptables, mínimo2 reservas y sobrecupo, atención retrasada, llegada de Rocco y cierre de toda la jornada antes de20:00.
- Agenda base:6 citas por veterinario el día de demo y5 en los5 días hábiles siguientes; conserva reservas/horas de los otros dos y completa sus citas anteriores a H con los modelos existentes y sellos coherentes; cascada normal, sin reajustes pendientes ni propuestas abiertas.
- Hallazgo2: eventos de agenda `omitido` con motivo de demo salvo Rocco; solo3 vacunas en ventana, resto vencidas; sin migraciones ni cambios de `Recordatorio`.
- La consola informa por separado eventos pendientes, dosis y recordatorios diarios; el máximo5 excluye los diarios por decisión del dueño.
- En la corrida CLI:3 correos de dosis y15 diarios de citas tras cada `--si`;0 adicionales tras cada `--escena` al correr los crons seguidos, respetando marcas de envío y vigencia del evento de Rocco; transporte falso, sin SMTP.
- Sin ejecutar aún los crons, la previsión conservadora de escena suma los eventos pendientes de Rocco más las3 dosis aún sin recordar; el ensayo de los9 relojes conserva el máximo5.
- Recordatorios de citas por fecha de la cita:18 el día de demo y15 en cada uno de los5 siguientes días hábiles; se envían el día anterior y las marcas previenen duplicados.
- Hallazgo4: `descartadas` con motivo/fuente públicos y `precargar=false`; ivermectina fuera de tratamientos en Collie y como alternativa en criollo; línea informativa de Atención, modal sin rediseño.
- Hallazgo5:2 sinónimos de mal aliento y4 advertencias de milbemicina/MDR1 con fuente clínica WSU/UC Davis; dosis rotuladas y genotipo individual distinguidos, validación veterinaria pendiente.
- PHP dirigido:3 clases clínicas, **158 pruebas/3804 comprobaciones**; pasada inicial de SemillaDemoTest **4/353**; cierre global **1/1060** con9 relojes, incluido15:00, sin ajustes/propuestas en toda San Roque y con cronología real coherente; JS afectado **1/1**, Atención/modal390/1366.
- CLI real con reloj inyectado a8:00 y18:00: creación, escena, crons falsos y borrado; base temporal `zooki_test_base_v2`, sin `.env`;5 clínicas,41 cuentas,42 mascotas,128 consultas,221 citas y98 solicitudes tras crear.
- Evidencias locales y cuentas generadas: `scratch/r/semilla-s1/corrida-local.log`, `phpunit.log`, `jornada-completa.log`; claves ignoradas por Git; Historial2.3.0, descargas y flujo de Modelos actualizados.
- Pendiente: revisión de Claude y ensayo en zooki-pruebas/Docker; la cascada conserva su alcance por clínica, sin cambiar su motor ni corregir «sin cerrar» o pausas.
- Versión2.3.0 conservada; sin commit ni push ni otras pantallas, migraciones o cambios del refuerzo.

## Guion de navegador — decisión del dueño (2026-11-01)

- `demo/zooki-demo.user.js` se instala en Tampermonkey; vive fuera de `public/` y no modifica pantallas, motores ni conocimiento.
- Agenda solo resalta las citas reales; Rocco es la única atención en curso desde H−35, duración 30, llegada previa y Mila completada.
- El propietario evalúa realmente a Simón en el Portal con los dos síntomas urinarios; se permite crear esa solicitud de demo, nunca reservar una cita ni mostrar una respuesta de API dentro del panel.
- La semilla no precarga el rojo de Simón; `--escena` limpia su evaluación del día en San Roque y `--si` recrea todos los datos de demo.
- Rocco y Mishi se muestran sin guardar consultas; síntomas y medicamentos se seleccionan en los autocompletados existentes y los resultados los calculan los motores reales.
- Shift+S avanza un paso y Shift+Q reinicia el contador; el clic manual reciente en un campo conserva la escritura durante 3,5 segundos; reiniciar no borra ni revierte datos.
- La guía de instalación, el orden de los cuatro escenarios y las cinco comprobaciones manuales están en `demo/LEEME.md`.

## Anexo del guion — Resultado

- **Escribió Codex, 2026-11-01; revisa Claude (arquitecto). Entregada, pendiente de revisión.**
- Userscript aislado con panel azul minimizable, tecleo nativo 40–60 ms, autocompletados reales y esperas limitadas a 8 s; errores visibles y prevención de duplicado de Simón en la misma pestaña.
- Único ajuste de semilla autorizado: Rocco en curso con llegada anterior, Mila completada y evaluación de Simón exclusivamente desde el Portal; no se agregaron pacientes ni catálogos.
- `SemillaDemoTest`: **4 pruebas/1230 comprobaciones**, MariaDB temporal con `zooki_test_base_v2`; nueve relojes, tipos reales, cierre de 20:00, ausencia de reajustes/propuestas y limpieza de Simón aunque elija otro veterinario.
- Chrome headless por CDP: mismo userscript a 1366×768 y Portal a 390×844; sarna3/3, selamectina y descarte de ivermectina; diagnósticos para Mishi y bloqueo real de paracetamol con fuente; Simón rojo/derivada sin cita.
- Verificación de base y peticiones: cero consultas guardadas, cero tratamientos guardados, cero reservas nuevas y una evaluación de Simón; rehacer la escena la elimina; sin excepciones JS.
- Corrida CLI real `--si`/`--escena` con reloj inyectado en lunes: 5 clínicas, 41 cuentas, 42 mascotas, 128 consultas, 221 citas y 97 solicitudes tras crear; cuentas/claves en evidencia local ignorada por Git.
- Evidencias: `scratch/r/userscript/phpunit.log`, `navegador.log`, `resultado.json`, `corrida-cli.log` y seis capturas; sin `.env`, SMTP, commit ni push.
- Pendiente: revisión de Claude e instalación/ensayo del dueño en Tampermonkey; se conservó el recorte de la línea de descartadas del pie existente a 1366, sin rediseñar Atención; versión 2.3.0 sin cambio.

## Corrección de historia en domingo (2026-11-02)

- **Decisión del dueño:** mantener el domingo cerrado; corregir las fechas de la historia de demostración, sin abrir una jornada dominical.
- Escribió Codex; entregada, pendiente de revisión del dueño: `consultaHistorica` traslada al sábado anterior las fechas ficticias que caían en domingo, conservando hora, cantidades y el episodio vigente de Max.
- La corrección se aplica al ejecutar de nuevo `--si`; `--escena` no rehace los seis meses de historia y no corrige los registros ya sembrados.
- Se conserva la jornada lunes–sábado7:00–20:00; sintaxis PHP comprobada, sin ejecutar suites, integraciones ni la semilla por instrucción del dueño; no se tocó la base cargada.
- Regresión agregada a `SemillaDemoTest` para impedir citas demo en domingo; escrita y sin ejecutar por esa misma instrucción.

## Anexo — Resultado: continuidad de Rocco y Mishi (2026-11-03)

- Escribió Codex; entregada, pendiente de revisión de Claude y del dueño.
- Mishi es la primera reserva posterior con llegada registrada; sobrecupo después de esa reserva y cero reajustes/propuestas pendientes hasta20:00.
- Rocco: síntomas → sarna/selamectina/descarte → Finalizar atención y Volver a la agenda, mediante los controles reales; sin próximo control ni reserva adicional.
- Agenda muestra nuevas estimaciones y pulsa Iniciar atención de Mishi; dos pasos en Atención muestran diagnósticos y bloqueo felino con fuente sin guardar Mishi; Portal conserva la evaluación real de Simón.
- El descarte deja el pie y se lee en una fila completa con escudo y enlace; motivo, medicamento y paciente derivados de la respuesta clínica, sin caso fijo por fármaco; el marco del guion sigue su posición real tras redistribuir la hoja.
- Verificación: los cuatro casos de SemillaDemoTest comprobados; repetido solo el caso afectado de escena,1/1182 aserciones y nueve relojes08:00–18:00; recorrido Chrome CDP correcto en motor temporal zooki_test_base_v2, sin otras suites ni datos manuales.
- Chrome: una consulta guardada (Rocco), Mishi iniciada sin guardar, cero reservas y una derivación de Simón; --escena recupera los conteos iniciales; sin excepciones JS. Descarte completo390×844/1366×768 y página de768px en escritorio; evidencia scratch/r/userscript/resultado.json y navegador.log.
- Misma2.3.0, sin migraciones, motores ni conocimiento; instalación actualizada de Tampermonkey pendiente del dueño; descargas al cierre del bloque.

## Anexo — Resultado: botón de demo en Plataforma (2026-11-03)

- **Escribió Claude Code; entregada, pendiente de revisión.** Misma 2.3.0; sin migración.
- Interruptor `helpers/InterruptorDemo.php` (`ZOOKI_DEMO_HABILITADA`, `.env.example` en 0); controlador `controllers/DemoPlataformaController.php` sobre `SemillaDemo`; lista blanca de la respuesta en `helpers/PresentacionDemo.php`; pestaña `views/plataforma/demo.php` y `public/js/demo-plataforma.js`.
- `helpers/Security.php`: 404 antes de cualquier otra comprobación si está apagado; matriz solo plataforma; solo POST con CSRF.

| Comprobación | Prueba |
|---|---|
| Apagado → 404, también sin sesión, y no ejecuta | `DemoPlataformaTest::testApagadoLasRutasNoExistenNiParaElSuperAdministrador` |
| Admin, veterinario y propietario → 403 | `testEncendidoAdministradorVeterinarioYPropietarioReciben403` |
| Super-administrador sin CSRF o sin POST → 403 | `testSuperAdministradorSinCsrfOSinPostRecibe403` |
| Super-administrador con CSRF ejecuta la escena y queda auditado | `testSuperAdministradorConCsrfEjecutaLaEscenaYQuedaAuditada` |
| Error de SemillaDemo tal cual y auditado | `testElErrorDeSemillaDemoSeMuestraTalCualYSeAudita` |
| Clave validada; nunca en la respuesta ni en la auditoría | `testCrearValidaLaClaveYNuncaLaDevuelveNiLaAudita` |

- Verificación: 6 pruebas / 39 aserciones; Security, Plataforma, PagosPlataforma, M021Gestionar y Suscripción en verde; JS M02Panel y E1Plataforma en verde. Pestaña abierta en Chrome a 1366 y 390 con respuesta simulada: validación de la clave en vivo, «Preparando…» con los tres botones deshabilitados, un solo envío ante doble clic, confirmación de borrar con el modal de Zooki, sin desborde horizontal ni errores de consola; con el interruptor en 0 no hay pestaña. Sin suites completas.
- Pendiente: probar con MySQL real en zooki-pruebas (crear, escena y borrar) y aceptación del dueño.

