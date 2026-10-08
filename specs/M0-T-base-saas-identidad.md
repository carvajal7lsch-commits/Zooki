# M0-T — Base SaaS, identidad y aislamiento

> Estado: en curso — A y B terminadas; C3 y C4 revisadas (2026-10-07); C1.7, C2 y C5 implementadas, pendientes de revisión por Claude.
> Entrega: v2.0 · Fecha: 2026-10-06
> Reparto vigente (2026-10-06): **Claude Code** en el equipo del usuario escribe cada etapa; **Claude** (sesión de revisión, sin editar los mismos archivos) revisa el diff, las pruebas y la trazabilidad. Codex queda disponible como revisor alterno. El usuario puede cambiarlo antes de cada etapa.

## 1. Resultado y límites

**Resultado:** Zooki pasa a ser una plataforma SaaS sobre una base v2 limpia. Las personas tienen un `id_usuario` estable y roles por clínica; el servidor aísla cada operación según el contexto activo; se puede registrar y activar una clínica, aceptar la política de datos y aplicar el plan gratuito; existe un super-administrador. Este bloque deja la base para los dos grafos, pero no implementa sus motores.

**Situación v1 (v1.12.0):** `usuarios.documento` es la clave primaria y referencia de otras tablas; `usuarios.id_rol` es un solo papel; existe el rol recepcionista; las tablas no tienen `id_clinica`. Las migraciones llegan a `13_razas_portal.sql`. **Los datos de producción son solo pruebas del usuario**: no hay clínicas, personas ni mascotas reales que conservar (decisión del 2026-10-06).

**Destino v2:** [MER](../documentacion/MER.md), [reglas](../documentacion/ReglasNegocio.md), [requisitos](../documentacion/RequisitosEspecificos.md) y [plan de entregas](../documentacion/HistoriasUsuario.md#plan-de-entregas-de-la-v2). `database/modelo/drawdb_schema_v2.sql` sigue siendo la referencia para drawDB; el esquema ejecutable es el nuevo `database/01_schema.sql`.

**Enfoque:** como no hay datos reales, **no se migran datos v1**. Se reinicia la base con un esquema v2 consolidado y datos semilla, y el código se pasa entero al modelo v2. Esto reemplaza el plan anterior de migración conservadora (relleno de `id_usuario`/`id_clinica`, verificación de huérfanos y línea base de la clínica legada).

**Fuera de alcance:** cálculo y carga clínica del Grafo I, agenda del Grafo II, urgencias de HU-4.15 ([plan propio](HU-4.15-ingreso-emergencia.md)), reseñas, comunicaciones y cobro real. Las tablas de esos módulos sí se crean en el esquema v2 (B) para no encadenar migraciones, pero su lógica llega con su módulo.

## 2. Trazabilidad

| HU de v2.0 | RE incluidos | RN/RNF principales | Etapa | Evidencia de aceptación |
|---|---|---|---|---|
| HU-T.15 | RE-T.15.1–5 | RN-G13, RN-G14, RN-001, RN-004, RNF-11 | C | Dos clínicas: petición cruzada 403, auditoría y ninguna consulta o modificación ajena. |
| HU-T.16 | RE-T.16.1–4 | RN-G17, RN-G05, RNF-13, RNF-14 | D | Sesión vencida, AJAX 401, cookie segura y auditoría. |
| HU-T.17 | RE-T.17.1–5 | RN-G01, RN-G06, RN-G13, RN-G18 | C | Una persona con rol de propietario y de personal en dos clínicas cambia de contexto sin mezclar permisos. |
| HU-T.19 | RE-T.19.1–4 | RN-G19, RN-G20 | D | Ninguna cuenta se crea sin la aceptación del titular; prueba con versión, medio, fecha e IP. |
| HU-5.8 | RE-5.8.1–5 | RN-109, RN-G06 | D | Una identidad global se vincula a las clínicas que elige, sin duplicar el correo ni exponerla a otra clínica. |
| HU-0.1 | RE-0.1.1–10 | RN-001, RN-002, RN-010, RN-011, RN-G19, RN-G20 | D | Alta pública con NIT válido, CAPTCHA, límites, consentimiento y clínica pendiente. |
| HU-0.2 | RE-0.2.1–5 | RN-002, RN-003, RN-005, RN-G11 | D | La verificación activa la clínica y asigna el plan gratuito; un enlace vencido no la activa. |
| HU-0.3 | RE-0.3.1–5 | RN-004, RN-012, RN-G13, RN-G14 | E | El panel opera sobre clínicas sin abrir historia clínica. **RE-0.3.6 (moderar reseñas) se verifica en v2.1 junto con HU-8.1**, porque en v2.0 aún no existen reseñas. |
| HU-0.4 | RE-0.4.1–7 | RN-003, RN-005, RN-006, RN-420 | E | Topes gratuitos por clínica; una mascota vinculada cuenta; una urgencia roja nunca se bloquea. |

Los RE completos y sus criterios están en `documentacion/RequisitosEspecificos.md`; esta tabla solo organiza la entrega. Una HU no se marca como terminada por tener sus tablas: necesita el flujo y las pruebas que le corresponden.

## 3. Etapas y dependencias

| Etapa | Entregable comprobable | Depende de | Escribe | Revisa |
|---|---|---|---|---|
| A. Inventario de código | Lista de los archivos que dependen de `documento` como clave, de `id_rol` o del recepcionista (hoy 49 archivos PHP entre modelos, controladores, helpers, vistas y scripts, más las pruebas), agrupados por módulo y con el orden de adaptación. Solo lectura. | v1.12.0 y MER v2 | Codex | Claude |
| B. Base v2 limpia | `01_schema.sql` v2 completo y `02_semilla.sql`; migraciones 03–13 retiradas; migrador ajustado; script de super-administrador. Una instalación local desde cero arranca y el migrador dice «al día» dos veces seguidas. | A | Codex | Claude |
| C. Identidad y aislamiento | Modelos, sesión, permisos, rutas y vistas v1 pasados al modelo v2 (`id_usuario`, roles por clínica, contexto activo, filtro por `id_clinica`); recepcionista eliminado del código. Cierra HU-T.15 y HU-T.17 con pruebas de dos clínicas. | B | Codex | Claude |
| D. Consentimiento, registro y activación | Cierra HU-T.16, HU-T.19, HU-5.8, HU-0.1 y HU-0.2 con formularios, correo, auditoría y pruebas. | C | Claude | Codex |
| E. Super-administrador y límites | Cierra HU-0.3 (sin RE-0.3.6) y HU-0.4. | D | Claude | Codex |
| F. Corte de producción | Respaldo, base de producción reiniciada, despliegue, super-administrador creado y clínica demo registrada por el flujo de HU-0.1. | E | Usuario, con comandos del agente | Agente |

Ramas: el trabajo de M0 vive en una rama propia (p. ej. `v2/m0`) y se integra a `main` solo en la etapa F, para que producción siga en v1.12.0 mientras tanto. En un mismo checkout los agentes trabajan por turnos; el revisor no edita los archivos del que escribe.

## 4. Datos y esquema

1. **Esquema v2 consolidado (`database/01_schema.sql`):** se genera a partir del MER aprobado y de `drawdb_schema_v2.sql`, con todas las tablas de la v2 (incluidas las de grafos, reputación y comunicaciones), FK, índices compuestos por `id_clinica`, InnoDB y utf8mb4. Debe cargar sin errores en MySQL 8 (producción) y MariaDB 10.4 (XAMPP local).
2. **Datos semilla (`database/02_semilla.sql`):** roles sin recepcionista, planes (gratuito y profesional con sus límites), catálogos taxonómicos globales (especies, razas y colores, tomados de los datos actuales de `01_schema.sql` y `13_razas_portal.sql`) y demás catálogos que la aplicación necesite para arrancar. Repetible: no duplica filas si se ejecuta dos veces. **No incluye usuarios, clínicas ni contraseñas.**
3. **Migraciones viejas:** `03_drawdb_schema.sql` y `04`–`13` se eliminan de `database/` (quedan en el historial de git). Es la única excepción a «no se modifican migraciones publicadas», aprobada por el salto a v2.
4. **Migrador (`helpers/Migrador.php`):** las migraciones nuevas empiezan en `03`; se retira la lógica de línea base (04–12), que en una base nueva marcaría como aplicadas migraciones v2 que nunca corrieron. Se mantiene la regla de migraciones repetibles, porque en una instalación nueva MySQL ejecuta todos los `.sql` de `database/` y luego el migrador. Pruebas del migrador incluidas.
5. **Super-administrador:** `scripts/crear_superadmin.php` lo crea por consola con el correo del usuario y pide la contraseña (cumple RN-G10); se niega si ya existe uno con ese correo. Nunca hay credenciales en el SQL ni en el repositorio.
6. **Clínica demo:** no se siembra. En la etapa F el usuario registra «Zooki» con `zooki.vet@gmail.com` por el autorregistro (HU-0.1), con un NIT de demostración de dígito de verificación válido. Así se prueba el flujo real.
7. **Consentimiento:** con base nueva toda cuenta acepta la política al registrarse; no hay cuentas anteriores a las que pedir una aceptación posterior.
8. **Recepcionista:** no existe en la semilla ni queda rastro en el código (C).

## 5. Código, permisos y pantallas

- **Modelos y helpers:** todos los que hoy relacionan personas por documento o leen datos de clínica (inventario de A); `helpers/Security.php` centraliza el contexto activo (clínica + rol) y la autorización.
- **Controladores y rutas:** los que usan documento o rol, y las rutas de `public/index.php`; cada acción nueva entra en la matriz de permisos.
- **Recepcionista:** se eliminan `views/reception/`, las rutas `reception_*`, la constante y su entrada en la matriz de `Security.php`, sus referencias en controladores, vistas (`views/admin/usuarios.php`, landing) y pruebas.
- **Pantallas:** selector de contexto, registro y activación de clínica, consentimiento, acceso del propietario y panel de plataforma. Verificación visual en móvil, tablet y escritorio; sin CSS ni JS en línea.
- **Aislamiento:** las consultas de negocio usan el `id_clinica` del contexto activo desde los modelos; la identidad y la mascota globales solo se acceden por el vínculo y el permiso que correspondan. El super-administrador no ve historia clínica por serlo.
- **Pruebas:** las de integración se reescriben sobre el esquema v2 con dos clínicas de prueba; las que solo cubrían al recepcionista se eliminan.

## 6. Decisiones (cerradas el 2026-10-06)

| Asunto | Decisión |
|---|---|
| Datos de producción | Son solo pruebas: se reinicia la base, sin migrar datos v1. Se toma un respaldo antes del corte por precaución. |
| Clínica actual | No se siembra; se registra en F como clínica demo con `zooki.vet@gmail.com` por el autorregistro. |
| Super-administrador | Cuenta nueva y aparte, del usuario, creada por consola en F. No se eleva ninguna cuenta existente. |
| Recepcionista | Se elimina sin rastro: ni rol, ni cuentas, ni código. |
| Consentimientos | Todas las cuentas aceptan la política al registrarse en la base nueva. |
| RE-0.3.6 (moderar reseñas) | Se verifica en v2.1 con HU-8.1; HU-0.3 se cierra en v2.0 con RE-0.3.1–5. La HU y el RE se anotan así al cerrar HU-0.3. |

| Riesgo | Mitigación |
|---|---|
| Cambiar el esquema rompe todos los módulos v1 a la vez. | La etapa C pasa el código completo antes de integrar a `main`; producción sigue en v1.12.0 hasta F. |
| Diferencias entre MySQL 8 y MariaDB 10.4 al cargar el esquema. | B se prueba en ambos antes de cerrarse. |
| El volumen de la base en Dokploy conserva la base vieja y MySQL no vuelve a cargar `database/`. | F incluye reiniciar el volumen `db_data` (y limpiar `uploads` de prueba) después del respaldo; el agente entrega los pasos exactos. |

## 7. Tareas y verificación

- [x] Aprobar el plan y cerrar las decisiones.
- [x] A — Inventario de código por módulo y orden de adaptación ([Anexo A](#anexo-a--inventario-de-código-etapa-a)). Revisado y aprobado; decisiones D-1 a D-9 cerradas (A.7).
- [x] B — ([Anexo B](#anexo-b--resultado-de-la-etapa-b); revisada, CI con MySQL 8 en verde, B.4 resuelto en B.5) Ajustes de MER/drawdb y de HU-0.2/RE-0.2.5 según A.7; `drawdb_schema_v2.sql` a `database/modelo/`; `01_schema.sql` v2, `02_semilla.sql`, retiro de 03–13, migrador sin línea base con guarda v1 y semilla en cada arranque, `crear_superadmin.php`; prueba en CI con MySQL 8 que carga el esquema desde cero y corre el migrador dos veces; instalación desde cero en MariaDB local; README y AGENTS actualizados en la sección de base de datos.
- [ ] C — En subetapas C1–C9 (A.5), cada una con revisión y `phpunit` en verde: identidad, contexto, aislamiento, retiro del recepcionista y de «cerrar sin consulta», cierre de los puntos de fuga de A.6; pruebas de dos clínicas.
  - [x] C1 — Identidad, contexto activo, autorización en `Security` y retiro del recepcionista ([Anexo C1](#anexo-c1--resultado)). Revisada en C1.6; corrección obligatoria aplicada en C1.7, pendiente de revisión por Claude.
  - [x] C2 — Configuración por clínica: horarios, lectura de catálogos y copia inicial D-1 ([Anexo C2](#anexo-c2--resultado)). Implementada y verificada; pendiente de revisión por Claude.
  - [x] C3 — Mascotas y propietarios del personal ([Anexo C3](#anexo-c3--resultado)). Implementada y verificada; Codex escribe y Claude revisa. El usuario aprobó adelantar de D únicamente la confirmación por correo de una vinculación de propietario existente (RN-109). La aceptación del alta nueva sigue siendo presencial, directa del titular (RE-T.19.1).
  - [x] C4 — Historia clínica y prevención ([Anexo C4](#anexo-c4--resultado)). Implementada, verificada y revisada (C4 — Revisión).
  - [x] C5 — Agenda v1 sobre el modelo v2 y retiro de «cerrar sin consulta» ([Anexo C5](#anexo-c5--resultado)). Implementada y verificada; Claude Code escribe y Claude revisa. Pendiente de revisión.
- [ ] D — Sesión, consentimiento, registro de propietario y de clínica, activación con copia de los catálogos iniciales (D-1, RE-0.2.5); pruebas.
- [ ] E — Panel del super-administrador y límites del plan; pruebas (incluida la excepción de urgencia roja).
- [ ] `vendor/bin/phpunit` completo y cada RE de la tabla con evidencia.
- [ ] F — Respaldo, reinicio de la base de producción, despliegue, super-administrador y clínica demo. Incluye blindar Apache para que solo sirva `public/` y agregar `.dockerignore` (B.5).
- [ ] Actualizar HU/RE (estado y nota de RE-0.3.6), `config/App.php` e `HistorialVersiones.md` al cerrar; regenerar descargas si cambian documentos publicados.
- [ ] Entregar al usuario los comandos de commit por etapa para PowerShell; el usuario crea ramas, commits, push y tags.

**Resultado de pruebas:** pendiente hasta la implementación.

## Anexo A — Inventario de código (etapa A)

> Hecho el 2026-10-06 sobre `v2/m0` (commit `8e75496`: código v1.12.0 + especificación v2), en modo de solo lectura. Lo escribió Claude por indicación del usuario; la tabla de etapas lo asignaba a Codex, así que la revisión le toca a Codex. Línea base: `vendor/bin/phpunit` → **218 pruebas, 898 aserciones, todas en verde**. Las líneas citadas son aproximadas (±5) y corresponden a ese commit.

**Marcas usadas en las tablas**

- **D**: usa el documento como clave o relación (`usuarios.documento`, `$_SESSION['usuario_doc']`, `doc_propietario`, `doc_veterinario`, `doc_usuario`, `usuario_doc`, `usuario_documento`).
- **R**: usa `usuarios.id_rol`, `$_SESSION['usuario_id_rol']` o `usuario_rol`, números de rol escritos a mano o las constantes de `Security`.
- **X**: toca al recepcionista.
- **C**: lee o escribe tablas que en v2 llevan `id_clinica` sin ese filtro, o recorre personas y mascotas globales sin pasar por `usuario_clinica`, `propietario_clinica` o `mascota_clinica`.
- **V**: usa columnas o estados que el esquema v2 retira (`cerrada_sin_consulta`, `motivo_cierre`, `aviso_atencion_abierta`, `slot_activo`, `password_definida`, `mascotas.color`, `mascotas.numero_historia_clinica`, `notificaciones_internas.id_cita`/`vigente_hasta`).

### A.1 Resumen

| Grupo | Archivos |
|---|---|
| PHP con cambio de fondo | 49: 19 de T, 4 del módulo 1, 4 del 2, 6 del 3, 7 del 4, 1 del 5, 6 del 6, 1 del 7 y 1 del 9 |
| PHP con cambio mínimo (solo leen `usuario_doc` para la navegación) | 6: `views/auth/login.php`, `views/auth/register.php`, `views/landing/index.php` y las tres de `views/legal/` |
| PHP que se eliminan (recepcionista) | 5: `views/reception/{layout,dashboard,agenda,calendario,pacientes}.php` |
| JS y CSS | 6 JS (`calendario`, `medical-module`, `dashboard`, `portal`, `login`, `register`) y 3 CSS (`styles`, `atencion`, `estados-cita`) |
| Infraestructura de la base (etapa B) | `helpers/Migrador.php`, `scripts/migrar.php`, `docker-compose.yml`, `README.md` (`docker/iniciar.sh` sin cambio funcional) |
| Pruebas | 11 de 26 archivos dependen del esquema v1 o del recepcionista (A.3) |

El plan hablaba de «49 archivos PHP»: coincide con los de cambio de fondo. A ellos se suman las 6 vistas de cambio mínimo y las 5 que se borran.

Sin dependencias del modelo v1 (no cambian en C): `helpers/` `ActividadCuenta`, `Csrf`, `DocsIndex`, `FotoMascota`, `GoogleToken`, `HorarioAtencion`, `ResumenClinico`, `ValidadorClinico`, `ValidadorMascota` y `VentanaRecordatorio`; `models/IntentoLogin.php` y `models/Respaldo.php`; `controllers/LandingController.php`; `scripts/backup.php` y `scripts/algolia_index.php`; `views/admin/horarios.php`, `views/vet/modal_consulta.php` y `views/portal/imprimir_historial.php`. `helpers/PoliticaPassword.php` solo nombra a recepción en comentarios (L108, L283).

### A.2 Dependencias por módulo

#### Módulo T — Acceso, seguridad y administración

| Archivo | Marcas | Dónde |
|---|---|---|
| `helpers/Security.php` | D R X | Constantes `ROL_*`, incluida `ROL_RECEPCIONISTA` (L110–113). La matriz `actionRoles()` (L126–220) usa `$staff` y `$todos`, que incluyen al recepcionista, y tiene las rutas `reception_*` (L195–196). `rolActual()` lee `usuario_id_rol` y, si falta, deduce el rol del nombre, incluido `'recepcionista'` (L227–241). `validatePasswordTemporal()` y el registro de RBAC leen `usuario_doc` (L58, L278). El límite por cuenta usa `'cuenta:'.$documento` (L336–338, L365, L378). No valida la clínica (RE-T.15.3). |
| `public/index.php` | D R X | 108 rutas, todas con su entrada en la matriz. Además de `Security::check`, unas 35 guardas repiten a mano `usuario_doc` + `usuario_id_rol != N`: admin en L153–209 y L724–780, veterinario en L222–266 y L527–628. Rutas `reception_*` en L277–321. Redirecciones por rol con la rama 3 en `dashboard` (L329–339), `nueva_mascota` (L407–417) y `nuevo_propietario` (L430–446); la rama `else` de esta última carga vistas que no existen. `vet_area` pasa `usuario_doc` a `PanelController` (L227). |
| `public/ver_archivo.php` | D R C | Punto de entrada aparte, fuera de la matriz. El administrador y el veterinario ven cualquier adjunto; al propietario se le compara por documento (L50, L76–82). En v2 debe exigir la clínica de la consulta o la autorización de historia compartida (RN-113). |
| `models/Usuario.php` | D R C V | Todo va por `documento`: `getUserByDocumento` (L14), `getById` (L117), `update` (L130–172, que cambia la clave primaria si cambia el documento), `updatePassword` y `updateDebeCambiarPassword` (L220–236), `updateStatus` (L319), `updateContactInfo` (L331) y `getUserByEmailExcluding` (L57). El rol es único: `id_rol` en lecturas y escrituras (L17–21, L47, L139/149, L180); `getAllOwners*` filtra `id_rol = 4` (L71–96); `getAll` filtra `id_rol != 4` (L238–252); también `getRoles`, `getAllRoleIds`, `esRolValido`, `contarAdminsActivos` y `esUltimoAdminActivo` (L254–317). Los listados recorren toda la plataforma; en v2 van por `usuario_clinica` o `propietario_clinica`. V: `password_definida` (L17, L121, L180, L221). |
| `models/PasswordReset.php` | D | `createToken(?string $documento…)` escribe `usuario_documento` (L28–38). |
| `models/VerificacionEmail.php` | D | `crear`, `hayPendiente` e `invalidarDe` van por `usuario_documento` (L29–97). Si la tabla falta, `tablaLista()` la crea en tiempo de ejecución con la forma v1 (L106–140). |
| `models/Auditoria.php` | D C | `log()` escribe `usuario_doc` (L69–105); `getLogs` y `countLogs` filtran por `usuario_doc` (L109–175); también `actividadDeCuenta` y `contarAccesosFallidos` (L205–227). Ninguna lectura filtra por clínica (`getStats`, `getDistinct*`, L179–232). |
| `models/NotificacionInterna.php` | D R C V | El destinatario es `doc_usuario` o un `id_rol_destino` global en todos los métodos (L37–186), sin `id_clinica`. Usa `id_cita` y `vigente_hasta` (L37–92), que el MER v2 no tiene (D-2). |
| `controllers/AuthController.php` | D R X V | El login es solo por documento (L44–60); RE-T.1.1 pide documento o correo. Guarda `usuario_doc`, `usuario_rol` y `usuario_id_rol` en tres flujos (L89–94, L601–606, L680–685) y en Google (L1021–1035, L1110–1115). Redirige por `id_rol` con una rama a `reception_dashboard` (L116–126). Mapa de nombres con `'recepcionista'` (L1029–1034). Alta con `id_rol = 4` (L465, L1094). Restablecimiento y verificación por `usuario_documento` (L191, L270–298, L656–685); `checkDocumentAjax` (L929–946). V: `password_definida` (L357, L1097). |
| `controllers/UsuarioController.php` | D R | Alta, edición, estado y restablecimiento por `documento` (L105–403); permite editar el documento, que es la clave (L190–257). `id_rol` llega por POST y es único por persona (L85–88, L217, L244); en v2 se asigna en `usuario_clinica` de la clínica activa. |
| `controllers/PerfilController.php` | D R X | `documentoEnSesion()` (L82–90) y resumen de cuenta por documento (L41–80); `id_rol` (L149); mapa de layouts con `3 => 'reception'` (L183–185); `login_method` (L195). |
| `controllers/NotificacionController.php` | D R | Las tres acciones leen `usuario_doc` y `usuario_id_rol` (L14–90). |
| `config/EmailService.php` | D | El correo de credenciales presenta el documento como usuario de acceso (L61–67, L174–190). Revisar el texto: en v2 se entra con documento o correo (RE-T.1.1). |
| `views/auth/cambiar_password.php` | R X | Redirección en JS por rol, con una rama a `reception_dashboard` (L267–275). |
| `views/admin/usuarios.php` | D R X | Selector y filtro de rol con la opción 3 (L67–69, L1015–1020); `data-rol` y estilos por rol (L129–215, L835–870); cada persona se identifica por documento en tarjetas, tabla y AJAX (L128–1203); comentario de roles 1–3 (L11). |
| `views/admin/auditoria.php` | D | Filtro `usuario_doc` (L89, L155, L186). |
| `views/admin/layout.php`, `views/vet/layout.php` | R | Muestran `$_SESSION['usuario_rol']` (L50, L55). |
| `views/perfil/index.php` | D | Muestra el documento (L47). |
| `views/auth/login.php`, `views/auth/register.php`, `public/js/login.js`, `public/js/register.js` | D (mínimo) | Solo `isset($_SESSION['usuario_doc'])` para saltar el formulario (L7 y L6). En el JS, el documento es un dato del formulario, no una clave; el login debe aceptar también el correo. |

#### Módulo 1 — Mascotas y propietarios

| Archivo | Marcas | Dónde |
|---|---|---|
| `models/Mascota.php` | D R C V | `doc_propietario` en `insert` (L12–34), `getAll` y `getById`, con JOIN a `usuarios.documento` (L48–87), `update` (L89–130), `search`, que también busca por documento (L299–322), `getByPropietario` (L376–386) y `getPropietarioSiActiva` (L405–427). `esPropietarioValido` exige `id_rol = 4` (L265–272). `registrarAuditoria` escribe `usuario_doc` en `auditoria_mascotas` sin clínica (L141–152). `getAll` y `search` recorren todas las mascotas; en v2 van por `mascota_clinica`. V: `numero_historia_clinica` en `insert` y `actualizarHC` (L14, L132) pasa a `mascota_clinica`; la columna `color` (L14, L96, L119) no existe en v2. Escribe catálogos globales: `insertEspecie`, `insertRaza`, `obtenerOCrearRaza` e `insertColor` (L187–247, L366). |
| `controllers/MascotaController.php` | D C | `doc_propietario` en alta y edición (L167–204, L381–425); auditoría con `usuario_doc` (L58, L253, L599). `listar`, `listarMascotasAjax`, `buscar` y `listarPropietariosAjax` no filtran por clínica (L80–112, L458–463). `actualizarPropietarioAjax` edita por documento (L485–500). Crea razas globales con `obtenerOCrearRaza` (L185, L415), algo que HU-7.2 prohíbe en v2.0. `registrarEspecieAjax` (L510) no tiene ruta: es código muerto. |
| `controllers/PropietarioController.php` (parte del personal) | D R X | Validación y alta por documento con `id_rol = 4` (L46–146); texto de auditoría «registrado desde recepcion» (L130). La parte del portal está en el módulo 5. |
| `views/vet/pacientes.php` + `public/js/medical-module.js` | D | El propietario se identifica por documento en formularios, filtros y AJAX (vista L410–1285; JS L285–1669). |

#### Módulo 2 — Historia clínica

| Archivo | Marcas | Dónde |
|---|---|---|
| `models/Consulta.php` | D C | `insert` con `doc_veterinario` y sin `id_clinica` (L23–49). `findAll` (L60–78) y `findByMascota` (L80–93) leen las consultas de toda la plataforma; en v2 son las de la clínica más las de otras solo con `autoriza_historia_compartida` (RN-113). `getArchivoConDueno` devuelve `doc_propietario` (L139–161). |
| `models/Tratamiento.php` | V | `insert` no envía `fecha_inicio`, que en v2 es NOT NULL (L10–24). |
| `controllers/ConsultaController.php` | D C | `usuario_doc` como veterinario (L30, L167, L208, L286); número de historia guardado en `mascotas` (L314–315). `listarHistorialAjax` (L422–466) devuelve consultas, vacunas y desparasitaciones de todas las clínicas sin regla de visibilidad. |
| `views/vet/consultas.php` | R | `usuario_id_rol == 2` (L37). |

#### Módulo 3 — Vacunación, desparasitación y recordatorios

| Archivo | Marcas | Dónde |
|---|---|---|
| `models/Vacuna.php` | D C | `insert` sin `id_clinica` ni `id_veterinario` (L10–25). `getPendientesSemana` une por documento (L35–46); los pendientes no filtran por clínica (L48–64). Lee y escribe los catálogos `vacunas_base`, `especie_vacunas` y `laboratorios_base` sin clínica (L66–107). |
| `models/Desparasitacion.php` | D C | Igual que vacunas: `insert` (L10–36), `getPendientesSemana` (L38–48) y `productos_desparasitacion_base` (L58–68). |
| `controllers/VacunaController.php` | C | Registro y catálogos sin clínica (L19–185; `getLaboratoriosAjax` en L174). |
| `controllers/DesparasitacionController.php` | C | Registro y productos sin clínica (L23–119; L109). |
| `models/Recordatorio.php` | D C | Une por `u.documento = m.doc_propietario` (L26–98). `registrar` escribe `doc_propietario` en `notificaciones` sin `id_clinica` (L118–134). Que la tarea recorra todas las clínicas es correcto, pero cada aviso debe llevar su clínica y, en v2.1, sus días de anticipación (HU-8.4). |
| `scripts/send_reminders.php` | D | Pasa `doc_propietario` al registrar (L75, L127). |

#### Módulo 4 — Agenda

| Archivo | Marcas | Dónde |
|---|---|---|
| `models/Cita.php` | D C V | `doc_veterinario` en `insert`, disponibilidad, sugerencias, `update` y listados (L10–330); JOIN por documento (L234–235, L261–262, L308, L321, L406). `getTiposCita` y `getTipoCitaById` (L123–146) y `getByFecha` (L230–255) no filtran por clínica. V: `cerrada_sin_consulta` en `ESTADOS`, en la disponibilidad y en `cerrarSinConsulta` (L49, L90, L163, L335, L376–388); `aviso_atencion_abierta` (L390–402). |
| `controllers/CitaController.php` | D R X C V | Veterinario y propietario por documento en todo el flujo: registrar (L36–115), avisos (L237–244), reprogramar (L483–545), cancelar (L563–588), atender (L641–890), confirmar (L946), correo (L968–973) y pantalla de atención (L1145–1183). Comprueba a mano los roles 1, 2 y 4 (L78, L93, L350, L483, L564, L645, L736, L806, L851, L946, L1083, L1145). `listarVeterinariosAjax` lista los veterinarios de toda la plataforma con `id_rol = 2` (L399–407). Tiene SQL directo sin clínica sobre `citas`, `vacunas`, `desparasitaciones` y `tipos_cita` (L132, L364–390, L1094–1112, L1192). Comentario «admin y recepcionista ven todas» (L354). `cerrarSinConsultaAjax` (L837–907). Bug v1: `atencion()` filtra `tipos_cita` por una columna `estado` que no existe y el `catch` deja el selector vacío (L1192). |
| `helpers/VigilanteAtenciones.php` | D | Notifica a `doc_veterinario` (L68). |
| `helpers/ReglaAtencion.php` | V | Lógica de `aviso_atencion_abierta` (RN-409, L56–64); depende de D-2. |
| `views/vet/calendario.php` + `public/js/calendario.js` | D R V | Pasa `USER_ROL` y `usuario_doc` al JS (L195–196); leyenda `cerrada_sin_consulta` (L53). El JS envía `doc_veterinario` (L132–1182) y ofrece «cerrar sin consulta» (L515, L580, L705). |
| `views/admin/citas.php` | D V | `doc_veterinario` en filtros y formulario (L231–744); estado `cerrada_sin_consulta` (L90, L392). |
| `views/vet/atencion.php` | V | Etiqueta `cerrada_sin_consulta` (L13). |

#### Módulo 5 — Portal del propietario

| Archivo | Marcas | Dónde |
|---|---|---|
| `controllers/PropietarioController.php` (portal) | D R C | Cada acción del portal exige `usuario_id_rol == 4` y compara `doc_propietario` con `usuario_doc` (L171–600). `index` lista las mascotas del propietario sin clínica (L177–178). En v2 el portal es el contexto «propietario» (RN-G01), no un rol global. |
| `views/portal/index.php` + `public/js/portal.js` | D V | Muestra `usuario_doc` (L414) y `doc_veterinario` (L616); usa `password_definida`; estado `cerrada_sin_consulta` (vista L22, JS L28–39). El JS envía `doc_veterinario` (L1611, L1666). |

#### Módulo 6 — Dashboard y panel

| Archivo | Marcas | Dónde |
|---|---|---|
| `controllers/DashboardController.php` | D R C | 23 consultas SQL directas sin clínica en `getStatsAjax`, `getChartsDataAjax`, `getCitasHoyTimelineAjax` y `getPendientesAjax` (L26–283), por documento y por `id_rol` 2 o 4 (L41, L145). La auditoría se filtra por `usuario_doc` (L285–358). |
| `models/Panel.php` | D R C | `SELECT_CITA` une por documento (L15–23); filtros por `doc_veterinario` (L31–83); `cargaPorVeterinario` usa `id_rol = 2` (L118–133); contadores sin clínica (L85–147). |
| `controllers/PanelController.php` | D | `datosVeterinario(string $doc)` (L23). |
| `helpers/ResumenPanel.php` | V | Etiqueta `cerrada_sin_consulta` (L165). |
| `views/admin/panel.php` | V | `cerrada_sin_consulta` (L21). |
| `views/vet/area.php` + `public/js/dashboard.js` | D V X | `datosVeterinario($_SESSION['usuario_doc'])` (L10–15). El JS usa `doc_veterinario` y `cerrada_sin_consulta` (L21–31, L480–554) y conserva el timeline «(Recepcionista)» con su comentario (L100, L430). |

#### Módulo 7 — Configuración

| Archivo | Marcas | Dónde |
|---|---|---|
| `controllers/HorarioClinicaController.php` | R C | Guarda a mano por rol 1 (L16). Ninguna consulta de `horarios_clinica` filtra por clínica (L32, L156–168, L226, L244, L305). **`restaurarPorDefectoAjax` hace `DELETE FROM horarios_clinica` (L199): con dos clínicas borraría los horarios de todas.** Los valores por defecto están escritos en el SQL (L202–210). |

#### Módulo 9 — Público, y retiro del recepcionista

| Archivo | Marcas | Dónde |
|---|---|---|
| `views/landing/partials/roles.php` | X | Pestaña y panel «Recepcionista» (L31–32, L87–100). |
| `views/landing/index.php`, `views/legal/{cookies,privacidad,terminos}.php` | D (mínimo) | Solo `isset($_SESSION['usuario_doc'])` para el menú (L9, L18, L19, L17). |
| `views/reception/*.php` (5) | X | Se eliminan. Solo las cargan las rutas `reception_*` (`public/index.php` L277–321); se comprobó que ninguna otra ruta las usa. |
| `public/css/styles.css` | X | `--role-receptionist` (L38) y el avatar de recepción (L1282). |
| `public/css/atencion.css`, `public/css/estados-cita.css` | V | Estilos de `cerrada_sin_consulta`. |

El módulo 0 (plataforma) y el 8 (reputación y comunicaciones) no tienen código en v1: todo lo suyo es nuevo.

### A.3 Pruebas

Las pruebas de integración escriben su propio DDL SQLite dentro de cada archivo (por ejemplo, `CREATE TABLE usuarios (documento TEXT PRIMARY KEY, …)`). Por eso no validan el esquema real.

| Archivo (casos) | Depende de | Acción en C |
|---|---|---|
| `Integration/ActividadCuentaAuditoriaTest` (3) | `auditoria_sistema.usuario_doc` | Reescribir con `id_usuario` e `id_clinica`. |
| `Integration/AuthTest` (2) | `usuarios` v1, `roles` con recepcionista, `password_definida` | Reescribir. |
| `Integration/CitaEstadoTest` (15) | `doc_veterinario`, `cerrada_sin_consulta`, `aviso_atencion_abierta` | Reescribir el esquema. Eliminar los 3 casos de cerrar sin consulta (L150, L160, L170) si se aprueba D-3; el caso del aviso (L177) depende de D-2. |
| `Integration/CitaTest` (3) | `doc_veterinario` | Reescribir. |
| `Integration/ConsultaHistorialTest` (9) | `usuarios.documento`, `doc_propietario`, `doc_veterinario`, `id_rol` | Reescribir y agregar los casos de RN-113 con dos clínicas. |
| `Integration/MascotaAccesoTest` (5) | `doc_propietario` | Reescribir con `id_propietario` y `mascota_clinica`. |
| `Integration/MascotaCatalogoTest` (14) | `usuarios` v1, `id_rol = 4`, creación de razas | Reescribir. Eliminar los 4 casos de `obtenerOCrearRaza` (L96, L105, L112, L122), porque en v2.0 la clínica no crea razas (HU-7.2). |
| `Integration/NotificacionAccesoTest` (11) | `doc_usuario`, rol global, `id_cita`/`vigente_hasta` | Reescribir con `id_usuario`, `id_clinica` y rol del contexto. Los casos de vigencia (L143–160) dependen de D-2. |
| `Integration/PanelTest` (7) | Esquema v1 de 8 tablas | Reescribir con dos clínicas. |
| `Integration/RecordatorioTest` (5) | `doc_propietario`, `notificaciones` v1 | Reescribir con `id_usuario` e `id_clinica`. |
| `Integration/UsuarioSeguridadTest` (10) | `usuarios.id_rol`, roles con recepcionista, `password_definida` | Reescribir: el último administrador activo se cuenta por clínica en `usuario_clinica`. |
| `Unit/AutorizacionRolTest` (17) | Constante `RECEPCIONISTA` y `reception_agenda` (L19, L124, L150, L180) | Reescribir para contexto activo, clínica y super-administrador. Eliminar `testElRolSeResuelvePorNombreCuandoFaltaElId` (L229), que solo protegía sesiones v1. |
| `Unit/ReglaAtencionTest` (9), `Unit/ResumenPanelTest` (10) | `cerrada_sin_consulta` en una lista de estados (L72 y L46) | Ajuste de una línea cada uno. |
| `Unit/SecurityTest` (3) | Clave `cuenta:` del límite de intentos | Igual, salvo que cambie la firma al pasar a `id_usuario`. |
| `Integration/IntentoLoginTest` (9) y las demás unitarias: `ActividadCuenta`, `Csrf`, `DocsIndex`, `GoogleToken`, `HorarioAtencion`, `PoliticaPassword`, `ResumenClinico`, `ValidadorClinico`, `ValidadorMascota` y `VentanaRecordatorio` (86 casos) | Nada del esquema v1 | Quedan igual. |

En total: 11 archivos se reescriben, 3 se ajustan y 12 quedan igual. Si se aprueban D-2 y D-3, se eliminan 8 casos.

Faltan pruebas que el plan exige y hoy no existen:

- un fixture compartido con dos clínicas (RE-T.15.5);
- pruebas del migrador (§4.4). `Migrador` usa `GET_LOCK` e `information_schema`, que SQLite no tiene: hace falta una base MySQL o MariaDB de prueba, o aislar esas llamadas.

### A.4 Insumos para la etapa B

#### A.4.1 Esquema actual frente al destino

El esquema actual son las 24 tablas de `01_schema.sql` más `intentos_login` (04) y `verificaciones_email` (05): 26 tablas. Todas siguen en v2. `drawdb_schema_v2.sql` tiene 50 tablas, así que hay **24 nuevas**:

- **SaaS:** `planes`, `clinicas`, `suscripciones`, `plantillas_comunicacion`.
- **Identidad:** `usuario_clinica`, `propietario_clinica`, `consentimientos_datos`, `casos_soporte`.
- **Pacientes:** `mascota_clinica`, `carnet_escaneos`, `ingresos_emergencia`, `alertas_medicas`.
- **Grafo I:** `grafo_nodos`, `grafo_aristas`, `cita_sintomas`, `consulta_sintomas`.
- **Agenda:** `horarios_veterinario`, `propuestas_horario`, `ausencias_veterinario`, `reasignaciones`.
- **Reputación:** `especialidades`, `veterinario_perfil`, `veterinario_especialidades`, `resenas_veterinario`.

**Cambio de clave.** `usuarios` pasa de clave `documento` a `id_usuario` INT autoincremental. `documento` queda NULL UNIQUE y `password` NULL; se agregan `google_uid`, `perfil_completo` y `es_super_admin`; se retiran `id_rol` y `password_definida`. Las 9 columnas que apuntaban al documento pasan a `id_*` INT:

- `citas.doc_veterinario` y `consultas.doc_veterinario` → `id_veterinario`;
- `mascotas.doc_propietario` → `id_propietario`;
- `notificaciones.doc_propietario` y `notificaciones_internas.doc_usuario` → `id_usuario`;
- `password_resets.usuario_documento` y `verificaciones_email.usuario_documento` → `id_usuario`;
- `auditoria_mascotas.usuario_doc` y `auditoria_sistema.usuario_doc` → `id_usuario`.

| Tabla | Columnas nuevas | Columnas retiradas y otros cambios |
|---|---|---|
| `citas` | `id_clinica`, `id_veterinario`, `margen_minutos`, `prioridad`, `prioridad_calculada`, `motivo_ajuste_prioridad`, `es_sobrecupo`, `orden_sobrecupo`, `sintomas_texto`, `inicio_sintomas`, `hora_llegada` | Se retiran `doc_veterinario`, `slot_activo`, `aviso_atencion_abierta` y `motivo_cierre`. `estado` gana `pausada` y pierde `cerrada_sin_consulta`. `id_tipo_cita` pasa a ser FK. Desaparece el índice único `uq_cita_vet_activa` (D-2). |
| `consultas` | `id_clinica`, `id_veterinario` | Se retira `doc_veterinario`. Hay que conservar el UNIQUE de `id_cita`: el código cuenta con él y drawdb no lo declara. |
| `tratamientos` | `id_nodo_farmaco`, `fecha_inicio` (NOT NULL), `fecha_fin` | — |
| `vacunas`, `desparasitaciones` | `id_clinica`, `id_veterinario` | — |
| `mascotas` | `id_propietario`, `id_clinica_registro`, `token_carnet` (UNIQUE NOT NULL), `carnet_activo`, `token_carnet_fecha`, `esterilizado`, `ficha_por_completar` | Se retiran `doc_propietario`, `numero_historia_clinica` (pasa a `mascota_clinica`), `color` y `patron`. `id_especie`, `id_raza`, `nombre` y `peso` admiten NULL (ficha provisional). `raza_indicada` pasa de 50 a 100 (D-7). |
| `notificaciones` | `id_clinica` (NULL en avisos de la plataforma), `id_usuario` | Se retira `doc_propietario`. |
| `notificaciones_internas` | `id_clinica`, `id_usuario` | Se retiran `doc_usuario`, `id_cita` y `vigente_hasta` (D-2). |
| `auditoria_mascotas`, `auditoria_sistema` | `id_clinica`, `id_usuario` | Se retira `usuario_doc`. `auditoria_sistema` pierde el `CHECK json_valid`. |
| `password_resets`, `verificaciones_email` | `id_usuario` | Se retira `usuario_documento`. |
| `tipos_cita` | `id_clinica`, `margen_minutos`, `pausable` | — |
| `horarios_clinica` | `id_clinica` | El único `uk_dia_semana` pasa a `(id_clinica, dia_semana)`. |
| `vacunas_base`, `laboratorios_base`, `productos_desparasitacion_base` | `id_clinica` | — |
| `roles` | — | En los datos se retira el 3 y se agrega el 5. |
| `archivos_clinicos`, `especies`, `razas`, `colores_base`, `mascota_colores`, `especie_vacunas`, `intentos_login` | — | Sin cambio de columnas. En `intentos_login` cambia el contenido de la clave: `cuenta:<id_usuario>`. |

**Índices que drawdb no expresa y B debe declarar:**

- los compuestos por `id_clinica` (§4.1);
- `consultas.id_cita` UNIQUE;
- `especie_vacunas (id_especie, id_vacuna_base)` UNIQUE;
- `horarios_clinica (id_clinica, dia_semana)` UNIQUE;
- `roles.nombre_rol` UNIQUE;
- la protección contra doble reserva (D-2).

**MER frente a `drawdb_schema_v2.sql`.** Se compararon de forma automática las 49 tablas del diagrama: toda columna del diagrama existe en drawdb. Las diferencias son estas:

1. `propietario_clinica` no tiene FK declaradas en drawdb, aunque el MER dibuja sus relaciones con `usuarios` y `clinicas`.
2. `intentos_login` está en drawdb y en el texto del MER (§2), pero no en el diagrama Mermaid. Tampoco se documenta la clave `chk:<ip>` que usa `Security::checkVerificationLimit`.
3. El MER (§7) dice que `especie_vacunas` es «por clínica (`id_clinica`)», pero no tiene esa columna en ninguno de los dos; hereda la clínica por `vacunas_base`.
4. El MER (§9) dice que `schema_migraciones` queda «sin cambios», y B retira el modo `linea_base`.
5. La cabecera de drawdb dice «Estilo alineado a `database/03_drawdb_schema.sql`», un archivo que B elimina.

#### A.4.2 Datos de catálogo para `02_semilla.sql`

| Tabla | Origen | Filas en v1 | Propuesta |
|---|---|---|---|
| `roles` | `01_schema.sql` L779–783 | 4 (incluido el 3, recepcionista) | 4: 1 administrador, 2 veterinario, 4 propietario, 5 super-administrador (MER §2). Conservar los nombres en minúscula que hoy compara el código, o cambiarlos en C. |
| `planes` | RN-003 y RN-008 (no hay filas en v1) | 0 | 2. Gratuito: $0, 5 mascotas, 30 citas al mes, 2 cuentas de personal. Profesional: $100.000 al mes o $960.000 al año, límites NULL (sin límite). |
| `especies` | `01_schema.sql` L424–431 | 7 | 6, sin «PAN» (D-4). |
| `razas` | `01_schema.sql` L714–762 y `13_razas_portal.sql` («Sin raza definida» por especie) | 48 + 7 | 54: las 48 más 6 «Sin raza definida». |
| `colores_base` | `01_schema.sql` L347–357 | 10 | 8 o 9: sin «blancoo»; «verde» se normaliza a «Verde» o se quita (D-4). |
| `especialidades` | Sin fuente | 0 | Vacía hasta HU-7.3 (v2.1), salvo que el usuario dé una lista (D-9). |
| `grafo_nodos`, `grafo_aristas` | Sin fuente; son del módulo 2 | 0 | No entran en M0. |

**Catálogos por clínica.** En el MER llevan `id_clinica` y no caben en una semilla global (D-1):

- `tipos_cita`: 6 filas (`01_schema.sql` L804–810).
- `horarios_clinica`: 7 filas. Conviene tomar los valores por defecto de `HorarioClinicaController` L202–210 y no los del volcado (L494–501), que tienen cambios de prueba: el miércoles empieza a las 10:00 y el sábado está apagado.
- `vacunas_base`: 15 filas (L913–928).
- `especie_vacunas`: 21 filas (L449–470).
- `laboratorios_base`: 11 filas (L519–530).
- `productos_desparasitacion_base`: 15 filas (L681–696).

**Lo que no pasa a la semilla:** usuarios (con hash de contraseña), mascotas, citas, consultas, vacunas, auditoría (con IP), notificaciones y restablecimientos del volcado actual. Son datos de prueba. Al reescribir `01_schema.sql` salen del árbol, pero siguen en el historial de git.

#### A.4.3 Cambios para la base limpia

- **`helpers/Migrador.php`:**
  - `PRIMERA` pasa de 4 a 3.
  - Se quitan `LINEA_BASE`, el bloque «Primera vez» (L68–80) y el modo `linea_base` del `CREATE TABLE schema_migraciones` (L56–62; el MER §9 se ajusta).
  - Se reescribe el docblock (L1–20), que habla de `03_drawdb_schema.sql` y de 04–12.
  - Se agrega una guarda: hoy solo exige que exista `usuarios` (L43–45), y una base v1 también la tiene. Debe negarse si detecta el esquema v1 (falta `clinicas` o `usuarios.id_usuario`), para no aplicar migraciones v2 sobre la base vieja de producción antes de F.
  - Según D-5, también aplicaría `02_semilla.sql`.
- **`scripts/migrar.php`:** se quita el mensaje de línea base (L45–47) y se agrega un mensaje claro cuando la base es v1.
- **`docker/iniciar.sh`:** no hay cambio funcional; `crear_superadmin.php` se corre a mano en F.
- **`docker-compose.yml`:** monta `./database` completa en `docker-entrypoint-initdb.d` (L32). Al crear la base, MySQL ejecuta en orden alfabético cada `.sql` del primer nivel: `01`, `02`, `03…` y también `drawdb_schema_v2.sql`, que repite los `CREATE TABLE` y aborta la inicialización (D-6). Esto ya pasa hoy con `03_drawdb_schema.sql`: un volumen nuevo no inicializa con el `database/` actual.
- **`README.md`:**
  - Instalación, paso 4 (L67–68): importar `01` y `02`, correr el migrador y crear el super-administrador.
  - Sección de migraciones (L69–76): numeración desde 03, sin línea base, y qué ejecuta MySQL al crear el volumen.
  - Modelo de base de datos (L78–103): roles sin recepcionista (L83), multi-inquilino y las tablas nuevas.
- **Enlaces a revisar si `drawdb_schema_v2.sql` se mueve:** `AGENTS.md` (L12 y L20; la L20 dice «que el migrador no ejecuta», pero MySQL sí lo ejecuta al crear la base), `specs/M0-T-base-saas-identidad.md`, `specs/_plantilla_modulo.md` y `specs/HU-4.15-ingreso-emergencia.md`.

### A.5 Orden propuesto para la etapa C

C es más grande de lo que la tabla de etapas deja ver: unos 60 PHP, 9 JS o CSS y 11 archivos de prueba. Desde B hasta el final de C la aplicación de la rama no funciona. Se propone partirla en subetapas, cada una con su revisión y sus pruebas en verde (D-8).

| Orden | Subetapa | Módulos | Archivos principales | Tamaño | Depende de |
|---|---|---|---|---|---|
| C1 | Identidad, contexto activo y permisos; retiro del recepcionista | T, 9 | `Security`, `public/index.php` (quitar las guardas duplicadas), `Usuario`, `AuthController`, `PasswordReset`, `VerificacionEmail`, `Auditoria`, `NotificacionInterna` y su controlador, `UsuarioController`, `PerfilController`, vistas de `auth/`, `admin/usuarios`, `admin/auditoria` y layouts; borrar `views/reception/`; selector de contexto (HU-T.17); fixture de dos clínicas | Grande: unos 21 PHP y 5 borrados, alrededor del 30 % de C | B |
| C2 | Configuración por clínica | 7 | `HorarioClinicaController`, lectura de `tipos_cita` y catálogos de vacunas y desparasitación por clínica; carga inicial según D-1 | Pequeña: unos 5 archivos | C1 |
| C3 | Mascotas y propietarios | 1 | `Mascota`, `MascotaController`, `PropietarioController` (personal), `vet/pacientes`, `medical-module.js`; `mascota_clinica`, `propietario_clinica` y `raza_indicada` sin crear razas | Mediana a grande: 4 PHP y 1 JS, alrededor de 3.300 líneas existentes | C1 |
| C4 | Historia clínica y prevención | 2, 3 | `Consulta`, `Tratamiento`, `ConsultaController`, `ver_archivo.php` (RN-113), `Vacuna`, `Desparasitacion` y sus controladores, `vet/consultas` | Mediana: unos 9 archivos | C2, C3 |
| C5 | Agenda | 4 | `Cita`, `CitaController`, `ReglaAtencion`, `VigilanteAtenciones`, `vet/calendario`, `admin/citas`, `vet/atencion`, `calendario.js`; retiro de cerrar sin consulta (D-3) | Grande: unos 8 archivos, alrededor de 4.000 líneas existentes | C2, C3 |
| C6 | Portal del propietario | 5 | `PropietarioController` (portal), `portal/index`, `portal.js` | Mediana | C3–C5 |
| C7 | Dashboard y panel | 6 | `DashboardController` (pasar 23 consultas al modelo), `Panel`, `PanelController`, `ResumenPanel`, `admin/panel`, `vet/area`, `dashboard.js` | Mediana | C4, C5 |
| C8 | Recordatorios y tareas programadas | 3, 8 | `Recordatorio`, `send_reminders.php`, `VigilanteAtenciones` y avisos internos por clínica | Pequeña | C4, C5 |
| C9 | Limpieza | 9 | Sesión en landing y legales, CSS de recepción, comentarios, código muerto (`registrarEspecieAjax`, `insertColor`, `insertEspecie`, rama `else` de `nuevo_propietario`) | Pequeña | C1 |

C1 va primero porque toda la aplicación lee la identidad y el rol de la sesión. C2 antes que C4 y C5 porque la agenda y la vacunación leen catálogos por clínica. C3 antes que C4 a C6 porque todo cuelga de la mascota global y su vínculo.

### A.6 Riesgos, contradicciones y decisiones pendientes

**Decisiones para el usuario antes de B.** Cada una lleva una propuesta; no se resolvió ninguna.

| Id | Asunto | Propuesta |
|---|---|---|
| D-1 | **Catálogos de una clínica nueva.** El plan (§4.2) manda a la semilla «los demás catálogos que la aplicación necesite para arrancar», pero en el MER `tipos_cita`, `horarios_clinica`, `vacunas_base` (con `especie_vacunas`), `laboratorios_base` y `productos_desparasitacion_base` son por clínica. Ninguna HU dice qué recibe una clínica al activarse. Sin tipos de cita ni horarios no puede agendar. | Copiar los valores de A.4.2 dentro de la transacción de activación (HU-0.2), desde un helper. Requiere agregar el criterio a HU-0.2 y un RE-0.2.5. La alternativa es volver globales esos catálogos, lo que cambia el MER. |
| D-2 | **Columnas que el código usa y el MER no tiene.** `citas.aviso_atencion_abierta`: RN-409 sigue vigente y exige avisar «una sola vez». `notificaciones_internas.id_cita` y `vigente_hasta`: retiran los avisos de citas pasadas. `citas.slot_activo` con `uq_cita_vet_activa`: es la única protección de la base contra doble reserva (RN-401), y choca con los sobrecupos de RN-422. | Agregar las tres primeras al MER y a drawdb. Para la doble reserva, un índice único que excluya los estados libres y los sobrecupos, o validar en la transacción; decidirlo pensando en M4. |
| D-3 | **Cerrar sin consulta.** RN-410 está derogada en v2 y el esquema v2 no tiene `cerrada_sin_consulta` ni `motivo_cierre`, pero el flujo existe en la ruta, el modelo, las vistas, el JS, el CSS y 3 pruebas. El plan (§1) deja la lógica de la agenda para su módulo. | Retirarlo en C5, porque el esquema ya no lo admite. |
| D-4 | **Datos de prueba en los catálogos:** especie «PAN» y colores «blancoo» y «verde». | Quitar «PAN» y «blancoo»; dejar «Verde» con mayúscula (sirve para aves y reptiles). |
| D-5 | **`02_semilla.sql` en instalaciones existentes.** MySQL solo la ejecuta al crear el volumen; en XAMPP se importa a mano. | Que el migrador la aplique en cada arranque, ya que es repetible; así un catálogo nuevo llega sin entrar al servidor. |
| D-6 | **`drawdb_schema_v2.sql` dentro de `database/`.** Rompe la inicialización de MySQL (A.4.3). | Moverlo a `database/modelo/`: ni MySQL ni el migrador recorren subcarpetas. Actualizar los enlaces. |
| D-7 | **Longitud de la raza indicada:** `raza_indicada` mide 100 en v2, pero `ValidadorMascota::RAZA_MAX` y `razas.nombre_raza` miden 50. | Dejar todo en 50, para que una raza indicada pueda pasar al catálogo sin recortarse. |
| D-8 | **Tamaño de C** (A.5). | Partirla en C1–C9, con revisión por subetapa. |
| D-9 | **Semilla de `especialidades`:** no hay fuente. | Dejarla vacía hasta HU-7.3. |

**Riesgos encontrados**, de mayor a menor:

1. **Puntos de fuga entre clínicas** que C debe cerrar sin excepción:
   - `HorarioClinicaController::restaurarPorDefectoAjax` borra los horarios de todas las clínicas (L199).
   - `ver_archivo.php` sirve cualquier adjunto a cualquier administrador o veterinario.
   - Los listados de personas, veterinarios y mascotas recorren la plataforma: `Usuario::getAll*`, `CitaController::listarVeterinariosAjax` y `Mascota::search`, que además busca por documento.
   - Las 23 consultas de `DashboardController`.
   - Los avisos internos por rol global.
   - La auditoría sin clínica.
2. **El filtro no se puede centralizar todavía.** Hay SQL directo en 8 controladores: Dashboard 23, Cita 13, HorarioClinica 9, Vacuna 3, y Consulta, Desparasitacion, Propietario y Perfil con 1 cada uno. Si no pasa primero a los modelos, la regla de aplicar el filtro en un solo lugar (RNF-11) no se cumple.
3. **Dos fuentes de autorización.** Unas 35 guardas en `public/index.php` repiten la matriz con números de rol. Con el contexto activo divergen fácilmente; conviene quitarlas en C1 y dejar solo `Security`.
4. **La base no se crea desde cero hoy.** Con `03_drawdb_schema.sql` (y luego con `drawdb_schema_v2.sql`) un volumen nuevo no inicializa (A.4.3). F depende de que B lo pruebe con un volumen vacío en MySQL 8 y desde cero en MariaDB 10.4.
5. **Las pruebas no validan el esquema real** (A.3). Una prueba de carga de `01_schema.sql` + `02_semilla.sql` en MySQL o MariaDB debería ser parte de la evidencia de B.
6. **Tablas creadas en tiempo de ejecución.** `VerificacionEmail::tablaLista()` recrearía la tabla con la forma v1 (`usuario_documento`) si faltara; hay que actualizarla o retirarla en C1. `IntentoLogin` ya tiene la forma v2.
7. **Escritura en catálogos globales desde una clínica** (`obtenerOCrearRaza`). En v2 es una escritura de un inquilino que ven todos los demás; HU-7.2 la prohíbe en v2.0.
8. **El login v2 acepta documento o correo** (RE-T.1.1) y el contador por cuenta va por `id_usuario` (MER §2). El login debe resolver a la persona antes de contar el intento sin revelar si existe (RN-G15).
9. **HU-T.18** (Google, v2.0) no está en M0. C1 debe mantener el inicio con Google funcionando con `id_usuario` y contraseña NULL, en lugar de una contraseña aleatoria, aunque sus criterios completos lleguen después.
10. **Bug v1** en `CitaController::atencion()` (L1192, `tipos_cita.estado` no existe) y código muerto (`registrarEspecieAjax`, `insertColor`, `insertEspecie`, rama `else` de `nuevo_propietario`). Conviene no arrastrarlos en C5 y C9.
11. **MariaDB 10.4 y MySQL 8.** El tipo `json` de `propuestas_horario.franjas` es un alias de `LONGTEXT` en MariaDB; las columnas generadas y los `CHECK` funcionan en ambas. El plan ya prevé probar las dos.

**Contradicciones entre el plan y el código o el MER:**

- §1 deja fuera la lógica de la agenda, pero el esquema de B retira estados y columnas que la agenda v1 usa: ver D-2 y D-3.
- §4.2 pide catálogos de arranque en una semilla global, pero el MER los hace por clínica: ver D-1.
- §4.4 dice «pruebas del migrador incluidas», pero no existe ninguna y el migrador depende de funciones de MySQL (A.3).
- La línea 20 de `AGENTS.md` dice que `drawdb_schema_v2.sql` no se ejecuta, pero MySQL sí lo ejecuta al crear el volumen (D-6).
- El MER (§9) deja `schema_migraciones` «sin cambios», y B retira la línea base.

### A.7 Revisión y decisiones (2026-10-06)

**Revisión:** hecha por Claude (sesión de revisión) sobre el diff del anexo. Se verificaron en el código, entre otros, el `DELETE FROM horarios_clinica` sin filtro (`HorarioClinicaController` L199), el índice `uq_cita_vet_activa` con `slot_activo` (`01_schema.sql` L304 y L965), la vigencia de RN-409 y la derogación de RN-410, los valores de RN-003/RN-008 y las 50 tablas de `drawdb_schema_v2.sql`. Resultado: **aprobado**, sin hallazgos que corregir en el inventario.

**Decisiones aprobadas por el usuario:**

| Id | Decisión |
|---|---|
| D-1 | Al activarse (HU-0.2), cada clínica recibe en la misma transacción una copia de los valores por defecto de `tipos_cita`, `horarios_clinica` (los de `HorarioClinicaController` L202–210), `vacunas_base` con `especie_vacunas`, `laboratorios_base` y `productos_desparasitacion_base` (A.4.2), desde un helper. Se agrega el criterio a HU-0.2 y el requisito **RE-0.2.5** con su criterio de aceptación. La implementación es de la etapa D; en B solo se documenta. |
| D-2 | Se agregan al MER y a drawdb `citas.aviso_atencion_abierta`, `notificaciones_internas.id_cita` y `notificaciones_internas.vigente_hasta`. La doble reserva se protege en la base con una columna calculada que vale NULL cuando la cita está en un estado libre **o es sobrecupo**, y un índice único `(id_veterinario, fecha, hora, <columna>)` sin `id_clinica`, para que un veterinario tampoco quede reservado dos veces entre clínicas. El tope de sobrecupos (RN-422) se controla en la transacción de reserva. |
| D-3 | «Cerrar sin consulta» se retira del código en C5 (RN-410 derogada). |
| D-4 | Semilla sin la especie «PAN» ni el color «blancoo»; «Verde» con mayúscula. |
| D-5 | El migrador aplica `02_semilla.sql` en cada arranque. La semilla **solo inserta lo que falta y nunca sobrescribe** (p. ej., un precio de plan cambiado por el super-administrador, RN-008, no vuelve al valor inicial). |
| D-6 | `drawdb_schema_v2.sql` pasa a `database/modelo/`; se actualizan los enlaces. |
| D-7 | `raza_indicada` queda en 50 caracteres, igual que `razas.nombre_raza` y `ValidadorMascota::RAZA_MAX`. |
| D-8 | La etapa C se ejecuta en las subetapas C1–C9 de A.5, cada una con su revisión y `phpunit` en verde. |
| D-9 | `especialidades` queda vacía hasta HU-7.3 (v2.1). |
| Adicional | GitHub Actions suma un servicio MySQL 8 que carga `01_schema.sql` + `02_semilla.sql` desde cero y ejecuta el migrador dos veces; cubre las pruebas del migrador (§4.4) y los riesgos 4 y 5 de A.6. |

**Documentos:** los ajustes que estas decisiones exigen (MER, drawdb, HU-0.2, RE-0.2.5 y las diferencias 1–5 de A.4.1) se hacen durante M0 **sin subir revisión**; la revisión nueva de cada documento se publica una sola vez al cerrar el módulo.

## Anexo B — Resultado de la etapa B

> Hecho el 2026-10-06 sobre `v2/m0` (a partir de `aba0764`) por Claude Code. Falta la revisión de la sesión de revisión y la primera corrida del job de MySQL 8 en GitHub Actions.

### B.1 Qué se hizo

- **Documentos** (sin subir revisión):
  - MER: D-2 (`citas.ocupa_horario` y `citas.aviso_atencion_abierta`; `notificaciones_internas.id_cita` y `vigente_hasta`, con su relación a `citas`), D-7 y las diferencias 1–5 de A.4.1. `intentos_login` entra al diagrama, con la clave `chk:`. Hay una sección nueva, §10, con los índices y restricciones.
  - `drawdb_schema_v2.sql` pasa a `database/modelo/` con los mismos cambios: FK de `propietario_clinica`, claves únicas e índices compuestos por `id_clinica`. `ocupa_horario` va ahí como columna simple, para que drawDB la importe.
  - HU-0.2 suma el criterio de D-1. Se agregan RE-0.2.5 y su fila en la matriz, y los conteos pasan de 418 a 419 en el encabezado de los requisitos y en la Ficha Técnica.
  - Enlaces actualizados en `AGENTS.md`, `specs/_plantilla_modulo.md`, `specs/HU-4.15-ingreso-emergencia.md` y este plan.
- **`database/01_schema.sql`:** las 50 tablas, sin datos, en orden de dependencias, con FK en línea, InnoDB y `utf8mb4_general_ci`.
  - La doble reserva (D-2) se protege con la columna calculada `ocupa_horario` (NULL si la cita está `cancelada` o `no_asistio`, o si es sobrecupo) y `UNIQUE (id_veterinario, fecha, hora, ocupa_horario)`, sin `id_clinica`.
  - Usa `CREATE TABLE` sin `IF NOT EXISTS` a propósito: sobre una base que ya tiene tablas falla en vez de mezclar esquemas.
- **`database/02_semilla.sql`:** 4 roles, 2 planes, 6 especies, 54 razas y 9 colores; `especialidades` queda vacía.
  - Todo con `INSERT IGNORE`, id explícito e índice único por nombre. No hay `ON DUPLICATE KEY UPDATE` ni `REPLACE`.
  - Los roles conservan los nombres en minúscula que compara el código (`Security::rolActual`, `AuthController`); el 5 se llama `super-administrador`.
- **Se borraron** `03_drawdb_schema.sql` y las migraciones 04–13.
- **`helpers/Migrador.php`:**
  - `PRIMERA = 3`, sin línea base; `schema_migraciones` queda solo con `archivo` y `aplicada_en`.
  - Se niega sobre una base vacía o v1 (sin `clinicas` o sin `usuarios.id_usuario`) con un mensaje que dice qué hacer.
  - Aplica `02_semilla.sql` en cada arranque, después de las migraciones.
  - `scripts/migrar.php` informa de la semilla.
- **`docker-compose.yml` y `docker/iniciar.sh`:** sin cambio funcional, solo comentarios. MySQL solo ejecuta el primer nivel de `database/` (`01`, `02`, `03…`) e ignora `modelo/`.
- **Super-administrador:** `scripts/crear_superadmin.php` (solo por consola; sale con 404 fuera de ella) y `helpers/CreadorSuperAdmin.php`.
  - Valida con `PoliticaPassword` (RN-G10) y se niega si el correo existe.
  - Crea la cuenta con `es_super_admin = 1`, sin documento ni rol de clínica, y la audita sin clínica, todo en una transacción.
  - Oculta la contraseña con `stty` (Linux y Docker) o con `Read-Host -AsSecureString` (Windows).
- **Pruebas:**
  - `tests/Unit/CreadorSuperAdminTest.php` (5 casos).
  - `tests/Integration/BaseV2MysqlTest.php` (9 casos contra MySQL o MariaDB): esquema, semilla repetible, migrador dos veces, semilla que no sobrescribe, migración nueva una sola vez, guardas v1 y vacía, doble reserva y super-administrador. Se salta sola sin `ZOOKI_TEST_MYSQL_HOST`.
- **CI:** job `base-v2-mysql` con un servicio MySQL 8. Carga `01` + `02`, crea un `.env` solo del CI, corre `scripts/migrar.php` dos veces (la segunda debe decir «al día»), compara los conteos `4,2,6,54,9,0,0,0`, corre `BaseV2MysqlTest` y crea la base desde un volumen vacío con `database/` montada en `initdb`, como `docker-compose.yml`.
- **README y AGENTS:** instalación, migraciones, semilla, super-administrador, modelo de datos v2 y la prueba con MySQL.

### B.2 Cómo se verificó

| Comando | Resultado |
|---|---|
| `vendor/bin/phpunit` | 232 pruebas, 906 aserciones, 9 saltadas (las de MySQL, sin variable de entorno). Las 218 anteriores siguen en verde. |
| `mysql zooki_v2_prueba < database/01_schema.sql` en MariaDB 10.4.32 (XAMPP), base nueva | Sin errores; 50 tablas, todas InnoDB y `utf8mb4_general_ci`. |
| `02_semilla.sql` dos veces seguidas | Conteos `4,2,6,54,9` en los dos casos; las tildes quedan en UTF-8 (`Café` = `436166C3A9`). |
| `Migrador` dos veces sobre `zooki_v2_prueba` (mismo código que `scripts/migrar.php`, con la conexión directa para no usar `.env`) | Las dos veces: «La base está al día» y semilla aplicada, sin filas nuevas. |
| `ZOOKI_TEST_MYSQL_HOST=127.0.0.1 ZOOKI_TEST_MYSQL_DB=zooki_v2_prueba vendor/bin/phpunit tests/Integration/BaseV2MysqlTest.php` | 9 pruebas, 130 aserciones, todas en verde sobre MariaDB 10.4. |
| `php scripts/crear_superadmin.php` con datos inválidos por tubería | Rechaza contraseñas distintas, una contraseña corta y un correo inválido antes de conectarse a la base (sale con código 1). |
| Comparación automática de `01_schema.sql` y `database/modelo/drawdb_schema_v2.sql` | Mismas 50 tablas y mismas columnas en cada una. |
| Comparación de las razas 1–48 con el volcado v1 (`git show HEAD:database/01_schema.sql`) | Idénticas. |

La base `zooki_v2_prueba` quedó cargada con esquema y semilla. No se tocó `zooki_db` ni se leyó `.env`. El MariaDB local, que estaba detenido, se arrancó para la prueba y se volvió a detener.

### B.3 Decisiones de detalle tomadas dentro de A.7 (para la revisión)

- La columna de D-2 se llama `ocupa_horario` (en español, en lugar del `slot_activo` de la v1) y el índice se llama `uq_cita_veterinario_horario`.
- Hay índices únicos por nombre en los catálogos globales (`planes`, `especies`, `razas` por especie y `colores_base`) para que la semilla no duplique filas (§10 del MER).
- `notificaciones_internas.id_cita` pasa a ser FK hacia `citas`; en la v1 era solo un índice. Las citas nunca se borran (RN-405).
- El plan gratuito tiene `precio_anual` NULL: no tiene cobro anual.
- AGENTS dice ahora que `01_schema.sql` y `02_semilla.sql` solo cambian junto con una migración que lleve el mismo cambio a las bases existentes.

### B.4 Pendiente o dudoso

1. **Posible exposición por web en Docker (urgente, fuera de B).** No hay `.dockerignore` y el `Dockerfile` hace `COPY . .` sin cambiar el `DocumentRoot` (`/var/www/html`). Si Dokploy deja el `.env` en el contexto de construcción, `https://<dominio>/Zooki/.env` podría descargarse, y `scripts/*.php` (migrar, respaldo, recordatorios) podría ejecutarse por web. No se verificó contra producción. Propuesta: comprobar con `curl -I` esas dos rutas y, en una tarea aparte, apuntar el `DocumentRoot` a `public/` y agregar un `.dockerignore`. `crear_superadmin.php` ya se protege.
2. **Pregunta — consentimiento del super-administrador.** RE-T.19.2 dice que toda cuenta nueva tiene su registro en `consentimientos_datos`, pero el `medio` solo admite `formulario`, `google` o `alta_personal`, y la cuenta se crea por consola. Hoy el script no guarda consentimiento. ¿Se exceptúa al operador de la plataforma, o se agrega un medio (p. ej. `consola`) al MER?
3. **MySQL 8 no se probó en local** (no hay Docker en el equipo). Lo cubre el job `base-v2-mysql`, que corre al hacer push; su primera corrida es la evidencia que falta.
4. **Lectura oculta de la contraseña** (`stty` y PowerShell): no se probó en una terminal interactiva. En Windows, una contraseña con tildes podría llegar alterada por la página de códigos de la consola; en producción se usa Docker (Linux).
5. **`specs/HU-4.15-ingreso-emergencia.md`** sigue diciendo que `ingresos_emergencia` llegará en una «migración nueva y conservadora de datos v1». La tabla ya está en `01_schema.sql`. Solo se corrigió el enlace; el texto se ajusta al planear M4.
6. **No verificados:** el renderizado Mermaid del MER (se agregó una entidad y un comentario de columna) y la importación en drawDB de las líneas `KEY`/`UNIQUE KEY`. Los resincroniza el usuario.
7. **Descargas del portal desactualizadas:** `exportar.mjs --revisar` marca readme, ficha, ers, reglas, hu, re, mer, historial y roi (varias ya lo estaban antes de B). Se regeneran al cerrar M0 (§7).
8. **La aplicación de la rama no funciona** sobre la base nueva hasta terminar C, como se previó (A.5).

### B.5 Revisión (2026-10-06)

**Resultado: aprobada.** Claude (sesión de revisión) revisó el diff, `01_schema.sql` (en especial `usuarios`, `clinicas`, `planes` y `citas` con `ocupa_horario`), `02_semilla.sql`, `Migrador`, `CreadorSuperAdmin` y el job de CI. El job `base-v2-mysql` corrió en verde en GitHub Actions (MySQL 8), con lo que queda cubierta la verificación que faltaba en B.4.3.

**Respuestas a B.4:**

1. **Exposición por web:** se comprobó en producción el 2026-10-06. `/` → 200; `/.env` → 404; `/composer.json` → 404; `/../.env` con `--path-as-is` → 400. El proxy solo expone `public/`; no hay fuga. Aun así, en F se blinda Apache (solo `public/` servible) y se agrega `.dockerignore`, para no depender solo del proxy.
2. **Consentimiento del super-administrador:** confirmado por el usuario el 2026-10-06, por recomendación de la revisión: **queda exento**. Es el operador de la plataforma, no un titular que se registra en el servicio. No se agrega un medio `consola` al MER; al cerrar M0 se aclara la excepción en RE-T.19.2.
3. **MySQL 8:** cubierto por el CI (punto anterior).
4–8. Se mantienen como están anotados; se atienden en su momento (F, M4 y el cierre de M0).

**Nota para C1:** el rol 5 (`super-administrador`) existe en `roles`, pero el super-administrador se marca con `usuarios.es_super_admin`. C1 debe impedir que `usuario_clinica` asigne el rol 5 (validación en la aplicación y prueba); los roles de clínica son solo 1 y 2, y el propietario va por `propietario_clinica`.

## Anexo C1 — Resultado

> Hecho el 2026-10-06 sobre `v2/m0` (a partir de `3c7afd9`) por Claude Code. Falta la revisión de la sesión de revisión.

### C1.1 Qué se hizo

**Sesión e identidad.**

- La sesión guarda `id_usuario` y el contexto activo; `usuario_doc` y `usuario_rol` desaparecen.
- `usuario_id_rol` queda solo como espejo del rol del contexto, para que el código de C2–C9 que todavía lo lee actúe con ese rol y, si no lo reconoce, deniegue. Se retira al terminar C.
- `helpers/Autenticador.php` decide el inicio de sesión:
  - Se entra con documento o correo (RE-T.1.1).
  - Una cuenta de Google (`password` NULL) no entra con ninguna contraseña.
  - «No existe» y «contraseña incorrecta» dan el mismo mensaje y tardan lo mismo, con un hash señuelo (RN-G15).
  - El contador por cuenta usa `cuenta:<id_usuario>`.
- El registro con Google crea la cuenta con `password` NULL y `google_uid`; ya no guarda una contraseña aleatoria.
- Pasaron a `id_usuario`:
  - Modelos: `Usuario` (reescrito), `PasswordReset`, `VerificacionEmail` (sin `tablaLista()`, riesgo 6), `Auditoria` (con `id_clinica`; por defecto la del contexto) y `NotificacionInterna`, acotada a la clínica.
  - Controladores: `AuthController`, `UsuarioController`, `PerfilController` y `NotificacionController`.
  - Vistas: las de `auth/`, `admin/usuarios`, `admin/auditoria`, los tres layouts y las de landing y legales (solo la comprobación de sesión).

**Contexto activo (HU-T.17).**

- `helpers/Contexto.php`, `controllers/ContextoController.php`, `views/auth/seleccionar_contexto.php` y el indicador «contexto · Cambiar» (`views/partials/contexto_actual.php`) en los layouts de administración, veterinario y portal.
- Los contextos posibles son: personal por cada fila activa de `usuario_clinica` (roles 1 y 2) en una clínica activa, un único portal si hay algún vínculo activo en `propietario_clinica`, y solo la plataforma si `es_super_admin`.
- Con un contexto se entra directo; con varios, al selector.
- Cambiar de contexto regenera la sesión y queda en auditoría con la clínica correspondiente.
- La clave elegida solo se acepta si está entre los contextos que la base reconoce; si no, 403 y auditoría.

**Autorización en un solo lugar.**

- `Security::autorizar()` lanza `AccesoDenegado`; `check()` y el `try/catch` del front controller lo convierten en JSON o en redirección.
- Los contextos se recalculan en cada petición: un rol retirado o una cuenta inactivada cortan el acceso en la siguiente petición (RN-G08).
- La matriz usa los roles 1, 2, 4 y 5. El 3 no existe y el 5 solo tiene `dashboard` y `plataforma_inicio` (RN-004).
- Una petición que nombra otra `id_clinica` da 403 y se audita. Los recursos sin clínica en la petición pasan por `Security::denegarRecursoAjeno()` o `exigirMismaClinica()`.
- Se quitaron las 34 guardas manuales de `public/index.php` y los `try/catch` que devolvían mensajes de excepción.
- La auditoría pasó a `controllers/AuditoriaController.php` y se quitó de `DashboardController`.
- `plataforma_inicio` es una página mínima, sin datos clínicos.

**Gestión de usuarios (HU-T.7).**

- Alta, edición, estado y restablecimiento por `id_usuario`, dentro de la clínica activa (`usuario_clinica`).
- El documento es un dato editable con unicidad.
- El último administrador activo se cuenta por clínica.
- Solo se aceptan los roles 1 y 2; `Usuario::asignarRolEnClinica()` rechaza también al super-administrador (RE-T.17.5).
- RE-T.7.5: dar de alta a una persona que ya existe le asigna el rol sin crear otra cuenta.
- Vista nueva sin CSS ni JS en línea (`public/js/usuarios.js`, con SweetAlert2): pestaña de personal y pestaña de propietarios vinculados, esta en solo lectura.

**Recepcionista.**

- Se borraron `views/reception/` (5 vistas), las rutas `reception_*`, `ROL_RECEPCIONISTA`, su entrada en la matriz, el layout `3 => 'reception'` del perfil, las redirecciones por rol 3 y las pruebas.
- Se ajustaron comentarios y textos (sin lógica) en `CitaController`, `PropietarioController`, `PoliticaPassword`, `dashboard.js` y `PoliticaPasswordTest`.

**Otros.**

- `scripts/dev/datos_prueba.php`: crea dos clínicas y una persona por cada tipo de contexto (punto 6 del encargo).
- `tests/Support/DosClinicas.php`: fixture compartido para SQLite que también carga en MySQL.
- Textos del correo de credenciales («tu documento o tu correo»).
- Una línea de comandos en AGENTS.

### C1.2 RE cubiertos y su prueba

| RE | Prueba |
|---|---|
| RE-T.15.2 (403 y auditoría) | `SeguridadClinicaTest`: `testUnaPeticionAOtraClinicaDa403YQuedaEnAuditoria`, `testElAdministradorNoVeNiModificaPersonalDeOtraClinica`, `testUnaNotificacionDeOtraClinicaDa403` |
| RE-T.15.3 (rol, CSRF y clínica en cada petición) | `SeguridadClinicaTest` (matriz, CSRF, `id_clinica`), `AutorizacionRolTest` |
| RE-T.15.4 (super-administrador sin datos clínicos) | `SeguridadClinicaTest::testElSuperAdministradorNoEntraADatosClinicos`, `AutorizacionRolTest::testElSuperAdministradorSoloTieneLaPlataforma` |
| RE-T.15.5 (centralizado y probado) | `tests/Support/DosClinicas.php` y las pruebas anteriores; `BaseV2MysqlTest::testElFixtureDeDosClinicasCargaEnElEsquemaReal` |
| RE-T.15.1 (todo filtra por clínica) | **Parcial:** cubierto en lo de C1 (personal, propietarios, auditoría, avisos): `testElListadoDePersonalEsDeLaClinicaActiva`, `ActividadCuentaAuditoriaTest::testElPanelSoloVeLaClinicaActiva`, `NotificacionAccesoTest`. El resto llega en C2–C9. |
| RE-T.17.1 (una identidad, varios roles) | `ContextoTest::testUnaPersonaTieneSusRolesComoContextosSeparados`, `UsuarioSeguridadTest::testElAltaDeUnaPersonaExistenteLeAsignaElRolSinOtraCuenta` |
| RE-T.17.2 (selector o entrada directa) | `ContextoTest`, `SeguridadClinicaTest::testSinContextoSeLlevaAlSelector` |
| RE-T.17.3 (cambiar sin mezclar permisos) | `SeguridadClinicaTest::testDosContextosNoMezclanPermisos`, `testUnContextoRetiradoSeQuitaDeLaSesion` |
| RE-T.17.5 (super-administrador sin roles de clínica) | `ContextoTest::testElSuperAdministradorSoloTieneLaPlataforma`, `UsuarioSeguridadTest::testUnSuperAdministradorNoRecibeRolesDeClinica` |
| RE-T.17.4 (no calificarse a sí mismo) | **Pendiente:** depende de las reseñas (HU-8.1, v2.1). |
| B.5 (rol 5 no asignable; rol 3 no existe) | `UsuarioSeguridadTest::testSoloSeAsignanLosRolesDeClinica`, `testElControladorRechazaElRol5YElRol3`, `AutorizacionRolTest::testLaMatrizSoloUsaLosRolesDeLaV2` |
| RE-T.1.1, RE-T.1.3, RE-T.1.5 (login) | `AuthTest`: documento, correo, mensaje igual, Google con `password` NULL, cuenta inactiva y correo pendiente |
| RE-T.7.1, RE-T.7.5, RE-T.11.2, RE-T.11.3 (personal) | `UsuarioSeguridadTest` (alta, vínculo, último administrador por clínica, RN-G08 por clínica, documento editable) |

### C1.3 Cómo se verificó

| Comando | Resultado |
|---|---|
| `vendor/bin/phpunit` | 274 pruebas, 1012 aserciones, 10 saltadas (las de MySQL sin variable). Las rehechas: `AuthTest`, `UsuarioSeguridadTest`, `ActividadCuentaAuditoriaTest`, `NotificacionAccesoTest` y `AutorizacionRolTest`; nuevas: `ContextoTest` y `SeguridadClinicaTest`. |
| `ZOOKI_TEST_MYSQL_HOST=127.0.0.1 ZOOKI_TEST_MYSQL_DB=zooki_v2_prueba vendor/bin/phpunit tests/Integration/BaseV2MysqlTest.php` (MariaDB 10.4.32) | 10 pruebas, 134 aserciones, en verde, incluida la del fixture sobre el esquema real. |
| `php scripts/dev/datos_prueba.php` (sin y con `--si`, dos veces) | Mostró la base de destino; creó 2 clínicas, 7 personas, 6 roles de personal y 2 vínculos de propietario; la segunda corrida no duplicó nada. |
| Recorrido HTTP con `php -S` sobre `public/` y `curl` con cookie (no hubo navegador disponible en esta sesión) | Ver abajo. |

**Recorrido HTTP.**

- Ana entra con su documento → `admin_panel`.
- En `admin_usuarios`, Ana ve el personal de Norte y no el de Sur.
- Pedir a Carla (Sur) → 403. Pedir con `id_clinica=2` → 403. Desactivar a Diego (Sur) → 403.
- Desactivarse siendo la última administradora → rechazado. Alta con rol 5 → rechazada.
- Dar de alta a Fabio, que ya era propietario → queda vinculado sin crear otra cuenta.
- La auditoría muestra la entrada al contexto y los 3 accesos denegados.
- Elena entra con su correo → selector con 3 opciones; `dashboard` sin contexto → selector.
- Elena como administradora de Sur ve el personal de Sur y el enlace «Cambiar». Como propietaria, `admin_usuarios` → redirige. Una clave que no tiene → redirige y se audita. Como veterinaria de Norte, `admin_usuarios` → redirige.
- Gina → `plataforma_inicio`; el historial clínico → 403.
- Clave mala y cuenta inexistente → el mismo mensaje.
- El registro del servidor no mostró avisos ni errores en archivos de C1.

`zooki_v2_prueba` quedó limpia, con esquema y semilla. Para probar a mano: `php scripts/dev/datos_prueba.php --si`, que imprime las contraseñas.

### C1.4 Pantallas de otros módulos que fallan (C2–C9, esperado)

- **Devuelven 500 por consultas v1:** `admin_panel` (C7), `vet_area` (C7), `vet_pacientes` (C3) y `portal_propietario` (C6). `admin_panel` es donde aterriza el administrador al entrar: para seguir, ir a `index.php?action=admin_usuarios`. Desde el portal, el cambio de contexto se hace en `index.php?action=seleccionar_contexto`.
- **Firmas de C1 que aún llaman con la forma v1:**
  - `NotificacionInterna::crearParaUsuario` y `crearParaRol` desde `CitaController` y `VigilanteAtenciones` (C5 y C8).
  - Métodos v1 de `Usuario` desde `MascotaController` y `PropietarioController` (C3 y C6).
  - `usuario_doc` en `Cita`, `Consulta`, `Dashboard`, `HorarioClinica`, `Mascota`, `Propietario`, `views/vet/{area,calendario}`, `views/portal/index` y `public/ver_archivo.php`. Todas fallan cerrado.
  - `Auditoria::log()` ignora el documento que mandan esos controladores, para no confundirlo con un `id_usuario`.
- **Quedan para C9 (cosmético):**
  - La pestaña «Recepcionista» de `views/landing/partials/roles.php`, y `--role-receptionist` y el avatar de recepción en `styles.css`.
  - El `onclick` de la campana en los layouts y los estilos en línea heredados de `views/admin/auditoria.php`.
  - El enlace a la auditoría sigue comentado en el menú de administración desde la v1.

### C1.5 Pendiente o dudoso (preguntas para el usuario)

1. **¿C1 «cierra» HU-T.15?** RE-T.15.1 (todo filtra por clínica) solo queda cumplido en lo que toca C1; el resto llega con C2–C9. Propuesta: HU-T.15 se cierra al terminar C, no en C1.
2. **RE-T.17.4 (no calificarse a sí mismo)** depende de las reseñas (v2.1). Propuesta: anotarlo como en RE-0.3.6 y verificarlo con HU-8.1.
3. **El administrador edita datos de una identidad global.** Puede cambiar documento, correo y nombre de alguien de su personal que quizá trabaja en otra clínica o es propietario, y restablecer su contraseña, como en la v1. RE-T.5.7 y RE-T.5.8 piden que el titular corrija su correo y su documento con verificación. ¿Se limita la edición del administrador a quien solo está en su clínica, o se deja así hasta D?
4. **Visibilidad del inicio de sesión.** `LOGIN` y `LOGIN_FAIL` se guardan sin clínica, porque al entrar aún no hay contexto. El administrador ve en su auditoría las «Entradas al contexto» de su clínica, pero no los intentos fallidos de su personal. ¿Está bien así?
5. **Clínica no activa.** Una clínica `suspendida` o `baja` no da contexto: su personal queda en «sin acceso». HU-0.5 (v2.1) podría pedir otra cosa para la mora.
6. **CAPTCHA por cuenta (RE-T.13.5, v2.0)** sigue sin hacerse: tras 5 fallos la cuenta se bloquea 15 minutos, como en la v1, ahora contada por `id_usuario`. No está en el plan M0; ¿en qué etapa entra?
7. **Enumeración.** `verificar_documento_ajax` y `verificar_email_ajax` le dicen al administrador si un documento o correo ya existe en la plataforma (lo exige RE-T.7.5). `check_document_ajax` y `check_email_ajax` (registro público) siguen como en la v1, con límite por IP.
8. **Registro sin vínculo.** El autorregistro y el registro con Google crean la identidad sin vínculo: la cuenta entra al selector con «todavía no tiene acceso» hasta HU-5.8 (D). No guardan consentimiento, que es HU-T.19 (D).
9. **El NIT de los datos de prueba** se guarda como `900123456-8` (con dígito de verificación); el formato definitivo lo fija HU-0.1 (D).
10. **No se probó en un navegador** (en esta sesión no había ninguno disponible). Falta la verificación visual en móvil, tablet y escritorio del selector, el indicador de contexto y la pantalla de usuarios.

### C1.6 Revisión (2026-10-06)

**Resultado: aprobada con una corrección obligatoria antes de C2.** Claude (sesión de revisión) revisó el diff, `Security::autorizar()`, `Contexto`, el front controller, `public/ver_archivo.php` (falla cerrado: sin `usuario_doc` niega a todos) y el texto del correo de credenciales. Lo más valioso: contextos recalculados en cada petición, autorización solo en la matriz (denegar por defecto) y login sin enumeración.

**Hallazgo (C1.5-3) — robo de cuenta entre clínicas.** Con la identidad global, el administrador de una clínica podía cambiar el correo, el documento o la contraseña de una persona que también trabaja en otra clínica o es propietaria, y quedarse con su cuenta en ambas. **Regla aprobada por el usuario:** el administrador solo edita correo, documento y contraseña (restablecimiento incluido) de quien está vinculado **únicamente** a su clínica (ningún otro `usuario_clinica` activo ni vínculo en `propietario_clinica`). Si tiene otro vínculo, esos campos quedan en solo lectura y los corrige el titular desde su perfil (RE-T.5.7, RE-T.5.8). Nombre, teléfono, rol en la clínica y estado del vínculo siguen siendo editables. Se valida en el servidor, con prueba.

**Respuestas a C1.5 (aprobadas por el usuario):**

1. HU-T.15 se cierra al terminar C, no en C1.
2. RE-T.17.4 se verifica en v2.1 con HU-8.1, como RE-0.3.6.
3. Ver el hallazgo.
4. `LOGIN` y `LOGIN_FAIL` sin clínica: correcto; los ve el super-administrador en el panel de plataforma (E).
5. Clínica `suspendida` o `baja` sin contexto: correcto por ahora. La mora **no** suspende: aplica los límites del plan gratuito (RE-0.5.2, v2.1).
6. CAPTCHA por cuenta (RE-T.13.5): entra en D, junto con Cloudflare Turnstile del registro.
7. Comprobación de existencia de documento o correo para el administrador: se deja (la exige RE-T.7.5); la pública mantiene el límite por IP.
8. y 9. Registro sin vínculo, consentimiento y formato del NIT: D.
10. Verificación visual en navegador: la hace el usuario.

**Para D:** el alta de personal todavía envía la contraseña en texto plano por correo (herencia de la v1). Según la especificación, el titular crea su contraseña al activar la cuenta; se corrige en D.

### C1.7 Corrección

Lo escribió Codex; la revisión corresponde a la sesión de revisión de Claude.

Se aplicó la regla aprobada en C1.6, con el ajuste de revisión aprobado por el usuario: documento (incluido su tipo), correo y restablecimiento de contraseña solo se administran para personas exclusivas de la clínica. Cualquier otro vínculo de personal, **activo o inactivo**, o cualquier vínculo de propietario bloquea esos cambios con `AccesoDenegado` (403) y auditoría, antes de modificar datos. Se quitó el filtro por estado: una desactivación temporal en otra clínica no permite tomar el control de la cuenta. El formulario pone documento y correo en solo lectura, deshabilita el tipo y el restablecimiento; nombre, teléfono, rol y estado local siguen editables. Los campos de acceso compartidos no se reescriben al guardar estos últimos.

Pruebas: `UsuarioSeguridadTest` cubre cuenta exclusiva, otra clínica activa, propietario activo/inactivo, petición directa de contraseña, restablecimiento, cambios locales permitidos y otro vínculo personal inactivo. Evidencia de RE-T.15.2, RE-T.15.3 y la regla C1.6; conserva los flujos del titular (RE-T.5.7/8) para D. Suite completa: **278 pruebas, 1055 aserciones, 10 saltadas** (MySQL sin variable). Navegador integrado: comprobados los campos de Elena (solo lectura), el restablecimiento deshabilitado y la clave inicial oculta; la matriz responsive completa sigue a cargo del usuario según C1.6-10. No cambia la versión de publicación: se actualiza al cerrar M0.

**Verificación del ajuste de revisión:** `testOtroVinculoPersonalInactivoBloqueaIdentidad` comprueba rechazo 403 de documento, tipo, correo, contraseña directa y restablecimiento, cinco registros de auditoría, cuenta sin cambios y permiso de vista deshabilitado. Suite después del ajuste: **286 pruebas, 1104 aserciones, sin fallos; 11 saltadas de MySQL sin variable**. La evidencia de base real de C2 que sigue corresponde a la ejecución anterior al ajuste.

## Anexo C2 — Resultado

Lo escribió Codex; la revisión corresponde a la sesión de revisión de Claude.

**Qué se hizo.** `HorarioClinicaController` usa `HorarioClinica`, sin SQL directo ni guardas v1; las consultas obtienen la clínica del contexto mediante `ModeloClinica`. Guardar valida todos los días antes de escribir y usa una transacción; restaurar actualiza los siete días exclusivamente de la clínica activa, sin borrar filas. Ambos cambios se auditan. La disponibilidad respeta días y bloques apagados. En el navegador se detectó y corrigió el POST de restauración sin cuerpo: ahora envía `FormData` y el interceptor agrega CSRF.

`CatalogoClinica` centraliza la lectura de tipos de cita (con margen y pausable), vacunas por especie, laboratorios y productos. Los modelos y endpoints de lectura existentes lo utilizan; el selector de tipos de la atención deja el SQL directo con la columna v1 inexistente. No se adaptan aquí los registros clínicos ni las reservas (C4/C5).

`InicializadorClinica` delega la copia en `CopiaCatalogosClinica`; `ValoresInicialesClinica` conserva los valores aprobados de A.4.2 y los siete horarios de la v1. Copia 6 tipos, 7 días, 15 vacunas, 21 relaciones por especie, 11 laboratorios y 15 productos por clínica, reasignando los ids de vacunas. Cada catálogo existente se conserva completo (no se rellena ni sobrescribe un catálogo personalizado). Bloquea la fila de clínica en MySQL para serializar copias simultáneas; usa transacción propia o savepoint si lo llama una transacción exterior. `scripts/dev/datos_prueba.php` lo llama dentro de su transacción para ambas clínicas. No hizo falta cambiar el MER ni agregar una migración.

**RE y evidencia.** En `ConfiguracionClinicaTest`:

| Alcance | Prueba |
|---|---|
| RE-T.15.1/5, RNF-11: horarios y catálogos aislados | `testCatalogosSoloSeLeenDesdeLaClinicaActiva`, `testGuardarYRestaurarSoloModificanLaClinicaActiva`, `testSinContextoNoSeLeenCatalogos` |
| RE-7.1.1/2: bloques y días; validación sin escritura parcial | `testGuardarYRestaurarSoloModificanLaClinicaActiva`, `testDiaInactivoYBloqueApagadoNoOfrecenHoras`, `testValidacionNoGuardaParcialmenteNiAceptaHorasODiasInvalidos` |
| RE-7.1.3: restauración propia, auditada | `testGuardarYRestaurarSoloModificanLaClinicaActiva`; recorrido en navegador con CSRF |
| RE-7.1.4: lectura del horario para disponibilidad (integración final de agenda en C5) | `testDiaInactivoYBloqueApagadoNoOfrecenHoras` y validación laboral en `testGuardarYRestaurarSoloModificanLaClinicaActiva` |
| RE-0.2.5: copia inicial, repetición y rollback (activación HU-0.2 en D) | `testInicializadorNoDuplicaNiSobrescribeCatalogosPropios`, `testCopiaInicialFallaSinDejarDatosParcialesYRespetaTransaccionExterior`; `BaseV2MysqlTest::testConfiguracionDeDosClinicasEnElEsquemaReal` |
| RE-7.4.2/5/6: lectura aislada de tipos, margen y pausable | `testCatalogosSoloSeLeenDesdeLaClinicaActiva`; la gestión completa y el uso clínico no se cierran en C2 |

**Verificación.** Suite completa con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1` y `ZOOKI_TEST_MYSQL_DB=zooki_v2_prueba`: **286 pruebas, 1238 aserciones, sin fallos ni saltadas**. `BaseV2MysqlTest` aparte: **11 pruebas, 146 aserciones**, MariaDB 10.4.32. Script de datos de prueba ejecutado dos veces; catálogos sin duplicados.

**Navegador integrado, dos administradores.** Ana (Norte): horarios iniciales, cerrar lunes, recargar y conservar el lunes cerrado (4 días, 32 horas). Carla (Sur): lunes sigue abierto; cerrar martes y restaurar mediante SweetAlert2; vuelve a 5 días y 40 horas. La consulta final confirma que Norte conserva el lunes cerrado después de la restauración de Sur. También se comprobó la vista protegida de Elena (C1.7). Se entró directamente a `admin_configuracion`: `admin_panel` todavía devuelve el 500 v1 ya documentado para C7.

**Pendientes.** Revisión de Claude; activación HU-0.2 en D; escritura clínica y agenda en C4/C5; portal con selección de clínica en C6; prueba responsive completa a cargo del usuario. HU-T.15 se cierra al terminar C. Versión de publicación y exportaciones al cerrar M0, según sección 7. No se empezó C3.

## Anexo C3 — Resultado

Lo escribió Codex; la revisión corresponde a la sesión de revisión de Claude. Se releyeron A.2 (módulo 1), A.5, A.6, RN-109–113 y las revisiones C1.6/C1.7 y C2 antes de implementar.

**Qué se hizo.** `Mascota` y `PropietarioClinica` resuelven el alcance desde `ModeloClinica`; el personal usa `id_usuario`, `mascota_clinica` y `propietario_clinica`. Una mascota ajena devuelve 403 auditado. El registro crea ficha global, vínculo, colores y token aleatorio de 43 caracteres, sin HC. La vinculación conserva ficha y token; la HC queda para la primera consulta en C4. La taxonomía es de lectura: «otra raza» se guarda en `raza_indicada`, por confirmar, sin insertar razas.

La edición bloquea especie, raza, sexo y nacimiento fuera de la clínica registradora, tanto en pantalla como en el modelo. Relee bajo bloqueo en MySQL y escribe únicamente los campos cambiados; ficha, colores, auditoría y aviso al titular se guardan atómicamente. `AvisoFichaMascota` intenta entregar el correo después del guardado y conserva pendiente/error si falla. No hay eliminación física ni límite del plan en C3.

El alta nueva exige presencia y aceptación directa del titular antes de crear la cuenta; guarda versión del contenido mostrado, fecha e IP en `consentimientos_datos`, medio `alta_personal`. La contraseña queda NULL y se envía un enlace de `PasswordReset`. Para una cuenta existente, el usuario aprobó adelantar únicamente la confirmación por correo: búsqueda exacta, mascotas básicas sin información clínica y vínculo pendiente hasta confirmar. La migración repetible `03_confirmacion_vinculo_propietario.sql` agrega propósito y clínica de destino a `verificaciones_email`; MER, drawDB, esquema y HU/RE reflejan ese ajuste. El token se guarda como hash, vence, es de un solo uso y no se entrega al personal; GET no lo consume y POST exige CSRF. La verificación de registro no consume solicitudes de vínculo ni estas bloquean el login. Los enlaces usan `APP_URL`; el destino alternativo se permite únicamente en localhost, evitando manipulación por `Host`.

**RE y evidencia.**

| Alcance | Prueba / verificación |
|---|---|
| RE-T.15.1/5, RN-110: aislamiento y 403 auditado | `MascotaAccesoTest::testLaMascotaDeANoSeVeNiSeEditaDesdeB`, `testPeticionDirectaDelControladorNoOcultaUn403`, `testVinculoConPropietarioAjenoDejaAuditoriaTrasRollback` |
| RE-1.1.1–5: obligatorios, validación, foto, propietario, listado; RN-502 y colores v2 | `MascotaCatalogoTest::testCamposObligatoriosYLargoSeValidanEnServidor`; `MascotaAccesoTest::testAltaSinPropietarioYFotoInvalidaNoGuardaMascota`, `testRegistrarTokenAleatorioUnicoYColoresSinColumnasV1`; formularios en navegador |
| RE-1.1.6, RE-1.5.1/2 y auditoría de RE-1.5.3 | `PropietarioClinicaTest::testBusquedaGlobalSoloExactaConMascotasBasicas`; `MascotaAccesoTest::testConfirmarPropietarioYVincularMascotaNoDuplicaNiAsignaHC`; oferta de vincular primero en navegador |
| RE-1.2.1/2/3: obligatorios, unicidad, confirmación y mascotas propias de la clínica | `PropietarioClinicaTest::testControladorExigeDatosObligatoriosYRechazaDuplicados`, `testSolicitudNoCambiaIdentidadNiBloqueaLoginYSoloConfirmaLaClinicaGuardada`, `testNoConfirmaTokenDeRegistroVencidoCorreoCambiadoNiClinicaSuspendida`; `MascotaAccesoTest::testBusquedaPorMascotaPropietarioYDocumentoYMinimoTresCaracteres` |
| RE-1.3.1–4: criterios, mínimo tres caracteres, resultados básicos, inactivas | `MascotaAccesoTest::testBusquedaPorMascotaPropietarioYDocumentoYMinimoTresCaracteres`, `testInactivarConservaFichaYNoSaleEnBusquedaActiva`; tiempo local inferior a 2 s, sin prueba de carga |
| RE-1.4.1–5: protección, auditoría, conservación y aviso | `MascotaAccesoTest::testBSoloEditaDatosNoProtegidosYSeAuditaYNotifica`, `testEdicionDePesoNoEscribeIdentidadNiColores`, `testInactivarConservaFichaYNoSaleEnBusquedaActiva`, `testUnaAuditoriaFallidaDeshaceFichaColoresYNotificacion` |
| RE-T.19.1/2: aceptación y evidencia; contraseña sin envío | `PropietarioClinicaTest::testAltaSinAceptacionOPresenciaNoCreaCuentaNiVinculoNiToken`, `testAltaConsentidaGuardaPruebaYPasswordResetSinContrasena`, `testControladorEnviaEnlaceSinExponerloAlPersonalNiEnviarPassword` |
| HU-7.2, RN-702: catálogo global intacto y raza indicada hasta 50 | `MascotaCatalogoTest::testLaTaxonomiaGlobalSeLeeIgualEnAmbasClinicas`, `testRazaIndicadaNoCreaUnaRazaGlobal`, `testRazaDeOtraEspecieYColoresInventadosSeRechazanSinAltaParcial` |
| Seguridad de la confirmación y compatibilidad real del esquema | `PropietarioClinicaTest::testConfirmacionPublicaPostExigeCsrf`, `EnlaceCuentaTest`; `BaseV2MysqlTest::testC3MascotasYConfirmacionEnElEsquemaReal`, `testMigracionC3ActualizaUnaBaseAnteriorYSePuedeRepetir` |

**Verificación.** Suite completa sin MySQL: **296 pruebas, 1296 aserciones, sin fallos; 13 saltadas por falta de la variable**. Suite final con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1` y `ZOOKI_TEST_MYSQL_DB=zooki_v2_prueba`, MariaDB 10.4.32: **296 pruebas, 1466 aserciones, sin fallos ni saltadas**, incluyendo las 13 pruebas de `BaseV2MysqlTest`. La migración se ejecutó dos veces sobre una base anterior y sobre el esquema nuevo. Sintaxis PHP/JS y `git diff --check` correctos.

**Navegador, dos veterinarios.** Beto (Norte) ve Luna; Diego (Sur) ve únicamente Milo antes del vínculo. La búsqueda parcial `fabio@` no da resultados; el correo completo devuelve solo los datos básicos de Luna y solicita confirmación. Para comprobar la edición tras confirmar, se consumió una solicitud sintética del fixture mediante el modelo y se vinculó la misma Luna a Sur: aparece sin duplicarla, con identidad deshabilitada y HC sin asignar. Diego guardó peso 9,25 kg; al reabrir, conserva el peso y los campos protegidos bloqueados. Se comprobó también el alta con las dos casillas de consentimiento inicialmente vacías. La entrega real al buzón no se validó: los envíos se cubren con un remitente simulado y el fallo SMTP conserva el aviso para reintento.

**Pendientes.** Revisión de Claude. C4 asigna HC, adapta historial/prevención y verifica RN-112/113 y conservación del historial; C5 adapta reservas; C6 el portal del propietario. El aterrizaje `vet_area` todavía devuelve 500 por `doc_veterinario` v1 (C7): la prueba entra directamente a `vet_pacientes`. Los botones clínicos del expediente mantienen dependencias C4/C5 y no se consideran verificados. D gestiona versiones completas de la política; E aplica el límite pendiente de RE-1.5.3; C8 completa reintentos programados de comunicaciones. Versión de publicación al cerrar M0 (§7). Se regeneraron los PDF y Word de HU/RE/MER, incluidos índice y tablas mediante Word; las otras descargas ya desactualizadas siguen pendientes del cierre de M0. No se empezó C4.

### C3 — Revisión (2026-10-07)

**Resultado: aprobada.** Claude (sesión de revisión) revisó el diff, `Mascota` (alcance por `mascota_clinica`, `vincular()` exige un `propietario_clinica` activo, que solo existe tras la confirmación del titular), la migración `03_confirmacion_vinculo_propietario.sql` (repetible, consulta `information_schema`) y el ajuste de HU-1.2/RE-1.2.2, aprobado por el usuario durante la subetapa y conforme a RN-109. También se verificó en el código que C1.7 bloquea con cualquier otro vínculo de personal, activo o no.

**Observaciones (no bloquean):**

1. **Estilo de código.** Parte del código nuevo encadena varias sentencias en una línea (`$stmt=...; if (...) ...;`). AGENTS pide código legible: desde C4, una sentencia por línea y nombres claros. No se reescribe C3 solo por esto; se corrige cuando se toque.
2. **Descargas del portal.** Se regeneraron HU, RE y MER antes de tiempo. No hace daño; la regeneración completa sigue siendo al cerrar M0.
3. **Migraciones antes del corte.** Producción aún no tiene la v2, así que `03_` podría haberse plegado en `01_schema.sql`. Se deja como está, porque mantiene al día las bases locales y la del CI; si al llegar a F hay varias, se evalúa consolidarlas.

## Anexo C4 — Resultado

Lo escribió Claude Code; la revisión corresponde a la sesión de revisión de Claude. Se releyeron A.2 (módulos 2 y 3), A.5, A.6, los anexos C1–C3 con sus revisiones, las HU y RE de los módulos 2 y 3, RF-2.4, RF-2.6 y RN-102, RN-112, RN-113, RN-202, RN-206, RN-207, RN-208 y RN-407.

**Decisiones del usuario (2026-10-07).**

1. Sin autorización no se revela que existe historia en otras clínicas. Se ajustaron el criterio de HU-2.10 y RE-2.10.3, que pedían un aviso (sin subir revisión).
2. El número de historia es correlativo por clínica: `HC-000001`, `HC-000002`…
3. El veterinario sigue agregando vacunas, laboratorios y productos desde «Otra…», siempre en la clínica activa. Quién gestiona los catálogos se decide con HU-7.2 (RN-701, RN-702).
4. No existen flujos de edición de registros clínicos. Cada modelo tiene un único `paraModificar()` que devuelve el registro propio o responde 403 con auditoría; no se agregaron pantallas ni rutas.

**Qué se hizo.**

- `models/ModeloHistoria.php` (nuevo) concentra la regla de visibilidad:
  - la mascota tiene que estar vinculada (activa) a la clínica activa; si no, 403 auditado;
  - vacunas y desparasitaciones de todas las clínicas, con `clinica_nombre` y `es_propia`;
  - consultas, tratamientos y archivos propios siempre; los de otra clínica solo si el propietario autorizó a la clínica activa en un vínculo activo.
- `ModeloClinica::denegarAcceso()` audita y lanza 403 sin distinguir «no existe» de «es de otra clínica».
- `Consulta`, `Tratamiento`, `ArchivoClinico` (nuevo), `Vacuna` y `Desparasitacion` extienden `ModeloHistoria`. Los controladores ya no tienen SQL. Cada registro guarda `id_clinica` (tratamientos y archivos, la de su consulta) y el `id_usuario` del veterinario.
- `Consulta::registrar()` valida antes de abrir la transacción: diagnóstico y motivo (RN-202), signos vitales, mascota activa y vinculada (RN-207), y cita de la clínica activa, de la mascota y del veterinario que registra (RN-407). Dentro de la transacción escribe la consulta, los adjuntos, los tratamientos (con `fecha_inicio` obligatoria), completa la cita (RN-406) y asigna el número de historia. Un fallo deshace todo y el controlador borra los archivos ya movidos. Un 403 dentro de la transacción se vuelve a auditar después del rollback.
- **Número de historia (RF-2.4, RN-102).** Se asigna en la primera consulta de la mascota en la clínica y no cambia después. Las primeras consultas de una clínica se serializan con un candado con nombre (`GET_LOCK`), tomado antes de la transacción y soltado después del commit; el índice único `(id_clinica, numero_historia_clinica)` es la última barrera. Se descartó un `FOR UPDATE` sobre `clinicas`: choca con los bloqueos compartidos que toman las FK al insertar y puede trabar dos primeras consultas entre sí.
- `public/ver_archivo.php` pasa por `Security::autorizar('ver_archivo')` (acción nueva en la matriz: administrador, veterinario y propietario) y por `ArchivoClinico::paraDescargar()`. En una clínica aplica RN-113; en el portal, solo los adjuntos de las mascotas propias (RN-G02). Lo que no corresponde da 403 auditado.
- Prevención: registro con clínica y veterinario; pendientes de la semana de la clínica activa (lo que ella aplicó, de mascotas activas y vinculadas); catálogo leído y escrito en la clínica activa (`CatalogoClinica::agregarVacuna/Laboratorio/Producto`, sin duplicar por nombre).
- `views/vet/consultas.php` queda sin CSS ni JS en línea (`public/js/consultas.js` y clases en `public/css/medical/consultas.css`).
- `public/js/medical-module.js`: el historial, la ficha y la impresión escapan todo el texto y marcan la clínica de origen. Con la historia compartida, el texto de otra clínica llegaba crudo a `innerHTML`. Cada fila de tratamiento pide la fecha de inicio (hoy por defecto); `atencion.css` suma esa columna.
- `helpers/RespuestaJson.php` reúne las respuestas JSON de los tres controladores.

**RE y evidencia.**

| Alcance | Prueba |
|---|---|
| RE-2.1.1, RN-112: campos, clínica y veterinario | `ConsultaHistorialTest::testRegistrarGuardaClinicaVeterinarioCamposTratamientoYAdjunto`; `PrevencionTest::testLaVacunaGuardaSusDatosLaClinicaYElVeterinario`, `testLaDesparasitacionCalculaLaProximaYGuardaClinicaYVeterinario` |
| RE-2.1.2, RE-2.2.3, RF-2.4: número único por clínica, una sola vez | `ConsultaHistorialTest::testElNumeroDeHistoriaSeAsignaUnaVezYNoSeRepiteDentroDeLaClinica`; `BaseV2MysqlTest::testC4ElNumeroDeHistoriaSeAsignaBajoCandadoSinRepetirse` |
| RE-2.1.3 (RN-202), RE-2.2.2 | `ConsultaHistorialTest::testSinDiagnosticoNiMotivoNoSeGuarda` |
| RE-2.4.1–3: tratamientos con fecha de inicio | `ConsultaHistorialTest::testTratamientoSinFechaInicioSeRechazaSinGuardarNada`, `testElControladorRechazaUnTratamientoSinFechaInicio`, `testAdjuntosYTratamientosLleganAgrupadosPorConsulta` |
| RE-2.6.1, RE-2.6.3: todo o nada | `ConsultaHistorialTest::testUnFalloAMitadDelRegistroNoDejaDatosParciales`, `testUnErrorDeLaBaseAlGuardarTratamientosDeshaceTodo`, `testElControladorBorraElAdjuntoMovidoSiLaBaseFalla` |
| RE-2.7.1–3, RN-207, RN-208 | `ConsultaHistorialTest::testUnaMascotaInactivaNoAdmiteConsultaYUnaNoVinculadaDa403`, `testCualquierVeterinarioDeLaClinicaRegistraSinCita`; `PrevencionTest::testMascotaInactivaONoVinculadaNoAdmiteVacunaNiDesparasitacion`, `testElControladorDeVacunasDejaPasarEl403` |
| RN-407: cita de la clínica activa y del veterinario asignado | `ConsultaHistorialTest::testLaConsultaDeUnaCitaEsDelVeterinarioAsignadoYDeLaClinicaActiva` |
| RE-2.1.4, RE-2.5.1, RE-2.5.2, RE-2.2.4 | `ConsultaHistorialTest::testElHistorialLlegaConElMasRecientePrimeroYNoMezclaMascotas`, `testElListadoTraeSoloLasConsultasDeLaClinicaActiva` |
| RE-2.10.1, RE-2.5.4: vacunas y desparasitaciones de todas, con su clínica | `HistoriaCompartidaTest::testBVeLasVacunasYDesparasitacionesDeAConLaClinicaQueLasAplico` |
| RE-2.10.2, RE-2.10.3: sin autorización no se ve ni se revela; con autorización, sí; al revocar, no | `HistoriaCompartidaTest::testSinAutorizacionBNoVeNiSabeDeLasConsultasDeA`, `testElHistorialPorPeticionDirectaNoRevelaLasConsultasDeA`, `testConAutorizacionBLasVeMarcadasYAlRevocarDejaDeVerlas`, `testUnaAutorizacionDeUnVinculoInactivoNoCuenta` |
| RE-2.10.4, RN-112: B no modifica nada de A | `HistoriaCompartidaTest::testBNoModificaNingunRegistroDeAYQuedaEnAuditoria`, `testBNoAgregaTratamientosNiAdjuntosAUnaConsultaDeA` |
| RN-113: mascota no vinculada, 403 | `HistoriaCompartidaTest::testUnaMascotaNoVinculadaNoMuestraNada` |
| RE-2.3.3: `ver_archivo` con la misma regla | `HistoriaCompartidaTest` (los casos de `paraDescargar`), `testElPropietarioSoloDescargaLosAdjuntosDeSusMascotas`; `AutorizacionRolTest::testVerArchivoEsDeLaClinicaYDelPortalNoDeLaPlataforma`; recorrido HTTP |
| RE-3.5.1: pendientes de la semana de la clínica activa | `PrevencionTest::testLosPendientesDeLaSemanaSonSoloDeLaClinicaActiva` |
| Catálogo de la clínica (C2, RN-702) | `PrevencionTest::testLasAltasDelCatalogoQuedanEnLaClinicaActivaSinDuplicar` |
| Todo lo anterior en el esquema real | `BaseV2MysqlTest::testC4HistoriaClinicaEnElEsquemaReal` |

`ConsultaHistorialTest` se reescribió sobre el fixture de dos clínicas (`DosClinicas::crearHistoriaSqlite()` y `vincularLunaASur()`); `HistoriaCompartidaTest` y `PrevencionTest` son nuevas.

**Verificación.**

| Comando | Resultado |
|---|---|
| `vendor/bin/phpunit` | 321 pruebas, 1449 aserciones, 15 saltadas (las de MySQL, sin variable). |
| Con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1` y `ZOOKI_TEST_MYSQL_DB=zooki_v2_prueba` (MariaDB 10.4.32) | 321 pruebas, 1641 aserciones, sin fallos ni saltadas; `BaseV2MysqlTest` sola: 15 pruebas, 192 aserciones. En la prueba del candado, una segunda sesión lo retiene: la primera consulta desiste sin guardar nada y, al soltarlo, toma `HC-000002`. |
| `php -l` y `node --check` de lo tocado | Sin errores. |

**Recorrido HTTP** (`php -S` sobre `public/` y `curl` con cookie; en esta sesión no hubo navegador disponible). Base `zooki_v2_prueba` con `datos_prueba.php --si` y Luna vinculada a Norte y Sur por SQL:

- Beto (Norte): tratamiento sin fecha de inicio → 422; consulta con tratamiento y PNG → 200; vacuna → 200; Luna queda con `HC-000001` en Norte y sin número en Sur; descarga su adjunto → 200 `image/png`.
- Diego (Sur), sin autorización: el historial trae la vacuna con «Clínica Norte (prueba)» y `consultas: []`; el adjunto de Norte → 403.
- Con `autoriza_historia_compartida = 1` (por SQL; la pantalla es de HU-5.12): Diego ve la consulta de Norte y descarga el adjunto → 200. Tras revocar: `consultas: []` y 403.
- Sin sesión → 401. Mascota solo de Norte pedida desde Sur → 403. La auditoría de Sur registra los dos 403 del adjunto.
- `vet_consultas` → 200; el diagnóstico con HTML llega escapado en `data-consultas`. `vet_pacientes` (Diego) → 200.

**Pendientes.**

1. **Pregunta: RN-115 frente al 403.** RN-115, HU-5.13 y RE-5.12.3 dicen que, si el propietario se desvincula, la clínica conserva lo que registró. Con la regla de este encargo, una mascota cuyo vínculo con la clínica pasa a `inactivo` da 403 en todo, incluidos los registros propios. Hoy ningún código desactiva `mascota_clinica`, así que todavía no ocurre. Propuesta: decidirlo con HU-5.13 (C6): con el vínculo inactivo, solo los registros propios, en lectura, sin nada nuevo.
2. **C5:** `CitaController` sigue con SQL v1 (eventos de vacunas en la agenda por `doc_propietario`, pantalla de atención). `Consulta::registrar()` completa la cita con una actualización acotada por clínica, mascota, veterinario y estado; el resto de la agenda es de C5. La columna de fecha de inicio en `atencion.css` no se probó en esa pantalla.
3. **C6:** el portal llama a `findByMascota` desde el contexto de propietario y hoy recibe 403: falla cerrado. Quedan la pantalla de autorización (HU-5.12), la historia completa del propietario (RN-114) y el resto de su acceso a adjuntos.
4. **C7 y C8:** panel y dashboard; recordatorios por correo. Se quitó `Vacuna::getPendientesPorDiaYEspecie()`: no tenía uso y era solo de MySQL. La agrupación por especie de HU-3.5 llega con el dashboard.
5. **HU-7.2:** quién gestiona los catálogos de la clínica (decisión 3).
6. **Fuera de C4:** Grafo I, `alertas_medicas` (RE-2.1.5) y RE-3.1.2/3 (calendario y alerta de la próxima dosis).
7. La fecha y hora de la consulta y los pendientes usan `America/Bogota`, como la v1: `clinicas` no tiene zona horaria.
8. `views/vet/modal_consulta.php` conserva `onclick` heredados, y la configuración de la sesión está repetida en `index.php` y `ver_archivo.php` (D, RNF-12).
9. Falta la verificación visual en navegador (móvil, tablet y escritorio) de Consultas, el historial con la marca de clínica y la fila de tratamiento. La hace el usuario.
10. La versión de publicación y las descargas del portal se actualizan al cerrar M0 (§7).

### C4 — Revisión (2026-10-07)

**Resultado: aprobada.** Claude (sesión de revisión) revisó el diff y en particular `ModeloHistoria` (`exigirMascotaVinculada` y `consultaVisible`): la regla de RN-113 vive en un solo lugar y no revela la existencia de registros ajenos sin autorización. Se valora el escape del texto de otra clínica en el historial (evita inyección entre inquilinos) y el código con una sentencia por línea.

**Decisión del usuario sobre el pendiente 1 (RN-115):** con el vínculo `mascota_clinica` inactivo, la clínica ve **solo sus propios registros, en solo lectura**, y no puede agregar nada nuevo. Se implementa en C6 junto con la desvinculación (HU-5.13).

**Base de pruebas:** `BaseV2MysqlTest` borra y recrea la base indicada en `ZOOKI_TEST_MYSQL_DB`. Desde C5 las pruebas usan su base por defecto (`zooki_test_base_v2`) y **no** `zooki_v2_prueba`, que es la base de trabajo manual del usuario.

## Anexo C5 — Resultado

Lo escribió Claude Code; la revisión corresponde a la sesión de revisión de Claude. Se releyeron A.2 (módulo 4), A.5, A.6, A.7 (D-2 y D-3), los anexos C1–C4 con sus revisiones, las HU y RE vigentes del módulo 4 (HU-4.1 a HU-4.10) y RN-401 a RN-410.

**Decisiones del usuario (2026-10-07).**

1. El propietario solo **cancela** (lo que ya existía): citas de sus mascotas en clínicas con vínculo activo. Reprogramar y confirmar siguen siendo del personal; si el portal los necesita, se especifican en C6.
2. La validación de solapamiento y las sugerencias de horario miran las citas del veterinario y de la mascota **en todas sus clínicas** (solo horas, sin otros datos), coherente con D-2.
3. RE-4.3.8 queda **derogado** (como RN-410), sin renumerar ni cambiar conteos; se ajustaron HU-4.3 y RE-4.3.1 (sin subir revisión).
4. `views/admin/citas.php`: solo se adaptaron los datos; sacar su JS y su CSS en línea queda para C9.

**Qué se hizo.**

- `models/Cita.php` extiende `ModeloClinica`:
  - toda lectura y transición filtra por la clínica activa; una cita de otra clínica da 403 auditado;
  - el veterinario va por `id_usuario` y debe estar activo en la clínica (`usuario_clinica`);
  - al reservar se copian `duracion_minutos` y `margen_minutos` del tipo de cita de la clínica (RE-4.13.6); `prioridad` queda `verde` y `es_sobrecupo` en 0;
  - la doble reserva que rechaza el índice `uq_cita_veterinario_horario` (error 1062 o `UNIQUE` de SQLite) se convierte en `HorarioOcupado`: 422 con mensaje, no 500;
  - las transiciones reciben la cita ya validada y repiten `id_clinica` en el `UPDATE`;
  - `paraPropietario()` resuelve la cita del portal: mascota propia y vínculo activo, o 403 auditado.
- `exigirMascotaVinculada()` y `exigirMascotaActiva()` subieron a `ModeloClinica`: las usan la historia (C4) y las citas.
- `models/AtencionesEnCurso.php` (nuevo) es la excepción explícita para RN-409: la vigilancia recorre todas las clínicas sin contexto y no devuelve datos a ninguna pantalla. `VigilanteAtenciones` avisa con `NotificacionInterna` v2 (`id_clinica` de la cita e `id_veterinario`), una sola vez por el sello `aviso_atencion_abierta`; el paso a «sin cerrar» se conserva. El correo es inyectable para las pruebas.
- `controllers/CitaController.php` reescrito sin SQL y con una sentencia por línea (`RespuestaJson`, reloj inyectable). Los eventos de vacunas y desparasitaciones salen de `Vacuna::pendientesEntre()` y `Desparasitacion::pendientesEntre()` (C4). `listarVeterinariosAjax` devuelve solo los veterinarios activos de la clínica. El calendario y el tablero reciben solo los campos que pintan (sin correos).
- **«Cerrar sin consulta» retirado** (D-3): ruta, acción de la matriz, método del controlador y del modelo, botón y función de `calendario.js`, etiquetas y estilos (calendario, atención, tablero del administrador, panel, dashboard, portal, `ResumenPanel`) y sus pruebas. Los mensajes de RN-409 ya no lo ofrecen.
- `atencion()`: el bug de A.6 (riesgo 10) ya lo había corregido C2; ahora la pantalla toma el nombre del tipo de la cita y los datos del propietario de la consulta de la cita, sin SQL directo.
- Vistas y JS: `calendario.php` pasa el rol y el `id_usuario` en `data-*` (sin `<script>` en línea); `calendario.js`, `admin/citas.php`, `pacientes.php` y `medical-module.js` usan `id_veterinario` / `id_usuario` en vez del documento.
- Agendar exige un contexto de clínica: desde el portal falla cerrado (403) hasta C6.

**RE y evidencia.**

| Alcance | Prueba |
|---|---|
| RE-4.1.1, RE-4.13.6: datos, duración y margen copiados, verde y sin sobrecupo | `CitaTest::testRegistrarGuardaClinicaVeterinarioYCopiaDuracionYMargenDelTipo`, `testSinTipoUsaTreintaMinutosYUnTipoDeOtraClinicaSeRechaza`; `BaseV2MysqlTest::testC5AgendaEnElEsquemaReal` |
| RE-4.1.2, RE-4.2.4, RE-4.9.3, RN-401: doble reserva con mensaje, también entre clínicas y por el índice | `CitaTest::testDosReservasAlMismoVeterinarioSeRechazanConMensaje`, `testLaCarreraLaDecideElIndiceUnicoYNoEsUn500`, `testElMismoVeterinarioNoSeReservaDosVecesEntreClinicas`; `AgendaPeticionesTest::testLaSegundaReservaDelMismoHorarioRespondeConMensaje`; `BaseV2MysqlTest::testC5AgendaEnElEsquemaReal` (error 1062) |
| RE-4.1.3, RN-402 y fechas pasadas | `AgendaPeticionesTest::testFueraDelHorarioOEnElPasadoSeRechaza`, `testOtraCitaDeLaMascotaEseDiaPideConfirmar` |
| RE-T.15.1/2, RN-G13: una cita de A no se ve ni se modifica desde B | `CitaTest::testUnaCitaDeANoSeVeNiSeModificaDesdeB`; `AgendaPeticionesTest::testDesdeBNingunaPeticionVeNiModificaUnaCitaDeA` (siete acciones, siete 403 auditados) |
| Veterinarios y calendario de la clínica activa (incluidas vacunas y desparasitaciones) | `CitaTest::testSoloSeAgendaConVeterinariosActivosDeLaClinica`; `AgendaPeticionesTest::testLosVeterinariosYElCalendarioSonDeLaClinicaActiva`, `testElVeterinarioAgendaParaSiMismoYNoParaOtro` |
| RE-4.2.1–3: reprogramar, reasignar, cancelar y avisar | `CitaTest::testReprogramarRespetaElHorarioYConservaLaDuracion`, `testUnaCitaCanceladaLiberaElHorario`; `AgendaPeticionesTest::testElAdministradorReprogramaYReasignaElVeterinario` |
| RN-G02: el propietario solo cancela lo suyo con vínculo activo | `AgendaPeticionesTest::testElPropietarioSoloCancelaCitasDeSusMascotasConVinculoActivo`, `testElPortalTodaviaNoAgenda` |
| RE-4.3.1, RE-4.3.4, RE-4.5.1, RE-4.7.1/2, RN-405–408 | `CitaEstadoTest` (iniciar, completar, no asistió, confirmar, cancelar); `AgendaPeticionesTest::testIniciarAtencionRespetaVeterinarioDiaYHora` |
| RE-4.3.6, RE-4.3.7, RN-409: aviso una sola vez en la clínica de la cita; «sin cerrar» | `CitaEstadoTest::testElAvisoDeAtencionAbiertaSaleUnaSolaVezEnLaClinicaDeLaCita`, `testAlTerminarElDiaLaAtencionQuedaSinCerrarYSeAvisa`; `BaseV2MysqlTest::testC5AgendaEnElEsquemaReal` |
| D-3, RE-4.3.8 derogado | `CitaEstadoTest::testYaNoExisteCerrarSinConsulta`; `AutorizacionRolTest::testYaNoExisteCerrarSinConsulta` |
| A.6 riesgo 10: tipos de cita de la clínica en la atención | `CitaTest::testLosTiposDeCitaSonLosDeLaClinicaActiva` |

`CitaTest` y `CitaEstadoTest` se reescribieron sobre el fixture de dos clínicas (`DosClinicas::poblarAgenda()`, y la tabla `citas` del fixture ahora tiene `ocupa_horario` y el índice único de D-2); `AgendaPeticionesTest` es nueva. Una prueba de C4 creaba dos citas del mismo veterinario a la misma hora: ahora el índice lo impide y se le dio otra hora.

**Verificación.**

| Comando | Resultado |
|---|---|
| `vendor/bin/phpunit` | 334 pruebas, 1520 aserciones, 16 saltadas (las de MySQL, sin variable). |
| Con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1` y la base por defecto `zooki_test_base_v2` (MariaDB 10.4.32) | 334 pruebas, 1723 aserciones, sin fallos ni saltadas. `zooki_v2_prueba` no se usó para las pruebas. |
| `php -l` y `node --check` de lo tocado (incluido el JS en línea de `admin/citas.php`) | Sin errores. |

**Recorrido HTTP** (`php -S` y `curl` con cookie; en esta sesión no hubo navegador) sobre `zooki_v2_prueba`, el miércoles 2026-10-07 a las 15:22:

- Beto (veterinario de Norte): la agenda lleva `data-rol="2" data-usuario="2"`; veterinarios de Norte: Beto y Elena; crea una cita hoy a las 15:30 (duración 15 y margen 10 copiados del tipo, verde, sin sobrecupo); otra mascota a la misma hora → 422 «no está disponible»; inicia la atención → `vet_atencion` 200; registra la consulta de la cita → la cita queda `completada` con sus horas reales. Otra cita de mañana: reprogramar a las 10:00 → 200; cancelar → 200.
- Ana (administradora de Norte): `admin_citas` 200; agenda para Elena y reasigna a Beto al reprogramar → 200; el tablero solo trae citas de Norte.
- Diego (veterinario de Sur): ver y cancelar la cita de Norte → 403 (dos registros en la auditoría de Sur); su calendario no trae citas de Norte; Luna en Sur a la hora que ya tiene en Norte → 422.
- Carla (administradora de Sur): veterinarios de Sur: solo Diego; agendar con Beto → 422 «no existe o no está activo en la clínica»; reprograma y cancela la de Diego → 200.
- Fabio (propietario): cancela la cita de Luna en Norte → 200 (avisos al veterinario y a los administradores de Norte); una cita ya atendida → 422; una inexistente → 403.
- `cerrar_sin_consulta_ajax` → 403 (la acción ya no existe en la matriz).
- El registro del servidor solo mostró avisos de `PropietarioController` (portal, C6).

Quedaron en `zooki_v2_prueba` cuatro citas de prueba: una completada con su consulta y tres canceladas (la última, por Fabio). Las contraseñas se regeneran con `php scripts/dev/datos_prueba.php --si`.

**Pendientes.**

1. **Navegador.** Cambié `calendario.js`, `admin/citas.php` y la ficha de Pacientes sin poder verlos: falta revisar a mano el calendario (filtro por veterinario, detalle, reprogramar arrastrando y por el modal) y el tablero del administrador. Tampoco se probó `enviar_email_ajax`, para no enviar correos reales.
2. **C6:** el portal sigue roto (`PropietarioController` usa SQL v1 y llama a `Cita::getByMascota` y `getProximaByMascota`, que se retiraron). `portal_agendar_cita_ajax`, `portal_get_vets_ajax` y `portal_get_tipos_cita_ajax` fallan cerrado (403) hasta que el portal elija clínica. Además, la regla de RN-115 que aprobó la revisión de C4.
3. **Módulo 4 (v2), fuera de M0:** las sugerencias siguen con el horario fijo de 08:00 a 18:00 (RE-4.9.1); la validación del horario de la clínica mira solo la hora de inicio (RE-4.9.2); el solapamiento usa la duración sin el margen (RN-415); triage, sobrecupos, horarios por veterinario y ausencias.
4. **C7:** panel y dashboard (solo se quitaron sus etiquetas del estado retirado). **C8:** recordatorios. **C9:** JS y CSS en línea de `admin/citas.php`, los `onclick` de `calendario.php` y `modal_consulta.php`.
5. `completar_cita_ajax` se conserva solo para citas con consulta y sin completar (datos de antes de v1.9.0); lo normal es que `Consulta::registrar()` complete la cita (C4).
6. La versión de publicación y las descargas del portal se actualizan al cerrar M0 (§7).

### C5 — Revisión (2026-10-07)

**Resultado: aprobada.** Claude (sesión de revisión) revisó el diff, `models/Cita.php` (toda lectura por la clínica activa, `paraPropietario()` exige mascota propia y `propietario_clinica` activo, doble reserva convertida en 422 con `HorarioOcupado`) y el ajuste de HU-4.3/RE-4.3.1/RE-4.3.8 por la derogación de RN-410, aprobado por el usuario. Se valora que el solapamiento mire las citas del veterinario y de la mascota en todas sus clínicas sin exponer más que las horas (coherente con D-2), y que la vigilancia de RN-409 sea una excepción explícita sin salida a pantallas.

**Prueba manual del usuario (2026-10-07): sin fallos.** Veterinario: detalle de cita, agendar y reprogramar por el modal (fecha, hora y tipo) correctos; sin filtro por veterinario, como corresponde (solo ve sus citas). Administrador: `admin_citas` con filtros por veterinario, fecha, tipo, estado y búsqueda de paciente correctos; detalle de una cita completada correcto. Hallazgos:

- **Arrastrar para reprogramar no funciona, pero no es una regresión de C5:** `calendario.js` solo habilita el arrastre para el administrador (`editable: esAdmin()`), y el administrador no tiene acceso a `vet_agenda` (RN-201). Era código muerto ya en v1.12.0. **Decisión del usuario:** habilitar el arrastre para el veterinario sobre sus propias citas pendientes o confirmadas, con las mismas validaciones del modal; se hace en C6.
- **Una contraseña con un espacio inicial no entra:** comportamiento correcto. La contraseña no se recorta (el espacio es un carácter válido); el documento y el correo sí se normalizan.

**Alcance de C6 (aprobado por el usuario el 2026-10-07):** además de adaptar el portal, C6 cierra HU-5.12 (autorizar historia compartida) y HU-5.13 (vincularse o desvincularse de una clínica) con la regla de RN-115 de la revisión de C4, porque la desvinculación y la autorización son las que ejercitan desde la interfaz las reglas de C3 y C4.
