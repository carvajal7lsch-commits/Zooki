# Semilla de demostración para la sustentación (2.3.0)

> Plan de una sola entrega. Arquitecto: Claude. Ejecutor: Codex. Va **después** de «Refuerzo del conocimiento tras pruebas (2.3.0)», porque usa Collie, ivermectina y los fármacos nuevos.

**Estado:** entregada, pendiente de revisión de Claude.

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
- **Confirmado por el dueño,2026-11-01:** `--escena` se niega si la secuencia relativa a la hora real no cabe en los horarios aprobados, incluyendo domingos, noche o bordes del descanso; no abre una jornada excepcional. Las pruebas usan reloj controlado dentro de jornada.

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

- 1 administradora y 3 veterinarios con horarios de lunes a sábado (8:00–12:00 y 14:00–18:00).
- Unos 25 propietarios y unas 35 mascotas. Mayoría de criollos (perro «Criollo (Mestizo)» y gato «Criollo») y también Collie, Gran danés, Bulldog francés, Golden hembra entera, Labrador, Pastor alemán, Poodle, Shih tzu y gatos machos y hembras. Pesos coherentes con la raza y la edad.
- **Historia de 6 meses:** unas 120 consultas con síntomas, diagnóstico del grafo, tratamiento (pautas de `MotorPrescripcion`) y peso, más vacunas y desparasitaciones. Algunas vacunas vencidas y otras próximas en 7 días, para que haya recordatorios.
- **Alertas médicas:** al menos 3 alergias enlazadas a fármacos del grafo, una epilepsia (`condicion.epilepsia`) y medicación vigente.
- **Próximas 2 semanas:** unas 40 citas de los tres veterinarios, de los distintos tipos de cita.
- Carnet QR configurado en varias mascotas.

### Pacientes guion (los que se muestran en Atención)

| Paciente | Para mostrar |
|---|---|
| **Rocco**, Collie macho de 4 años, 28 kg | Síntomas de sarna: Zooki sugiere sarna sarcóptica, precarga selamectina y deja la ivermectina bloqueada por MDR1 con su fuente. |
| **Max**, criollo de 9 años, 30 kg, con meloxicam vigente | Dermatitis atópica: oclacitinib precargado; al elegir prednisolona en Alternativas aparece la interacción con meloxicam (decisión del dueño,2026-11-01). |
| **Mishi**, gata criolla de 5 años, alérgica a un fármaco del grafo | Al escribir ese fármaco o recibirlo como sugerencia, queda bloqueado por la alergia. |
| **Simón**, gato criollo macho de 3 años | Solicitud del portal «va a la arena y no hace»: queda en **rojo** y derivada, sin horario. |
| **Luna**, Golden hembra entera de 6 años | Solicitud con días desde el celo: **naranja**. |
| **Kira**, cachorra criolla de 3 meses sin vacunas registradas | Vómito y diarrea: **naranja**, sin revisión. |

La **propietaria principal del portal** (cuenta para entrar en el celular) tiene a Simón y a otra mascota con historia y carnet, y está vinculada a San Roque y a Huellitas (mascota global, historia por clínica).

### Escena (`--escena`, agenda del día de la veterinaria principal)

Se calcula desde la hora actual H, redondeada a 5 minutos:

- 3 citas completadas en las 2 horas antes de H, con `hora_inicio_real`, `hora_fin_real` y duración objetivo.
- 1 cita **en curso** que empezó en H−35 min con duración de 30 min, para que la cascada muestre retraso en las siguientes.
- 4 o 5 citas pendientes después de H, con colores de triage distintos, incluida una **naranja de sobrecupo**.
- Rocco con **llegada registrada** y en espera, listo para abrir Atención.
- Solicitudes del portal del día: roja derivada (Simón), naranja (Luna, Kira), amarilla en revisión y verde.
- Nunca deja citas «sin cerrar» ni pausas abiertas: con eso todo el día se iría a revisión.
- Recalcula la cascada al terminar (`CascadaAgenda::procesar`) para que la agenda abra ya estimada.

### Veterinaria Huellitas (plan Gratuito, activa)

- Administradora y un veterinario: personal en **2/2**. Al invitar a un tercero aparece el límite del plan.
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
  - Las solicitudes tienen el nivel esperado de la tabla de pacientes guion.
  - `--escena` deja exactamente una cita en curso, ninguna «sin cerrar» y la cascada calculada.
  - Se niega si falta un código del conocimiento.
- Sin suites completas: solo la prueba nueva y las de los modelos que use. El CI corre la suite en MySQL 8 después del push.

## Para el dueño (pasos)

1. zooki-pruebas: desplegar la rama, entrar al contenedor desde Dokploy y correr `php scripts/migrar.php`, `php scripts/cargar_conocimiento.php`, `php scripts/semilla_demo.php --entorno=pruebas --si --clave=<clave>`.
2. Revisar las pantallas.
3. Producción: la víspera, lo mismo con `--entorno=produccion --dia=<día de la sustentación>`. Minutos antes de presentar: `php scripts/semilla_demo.php --entorno=produccion --escena`.

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
Debe caber tanto la historia de las dos horas previas como las reservas posteriores; las 10:30 de lunes a sábado son una referencia compatible con los horarios sembrados.
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
