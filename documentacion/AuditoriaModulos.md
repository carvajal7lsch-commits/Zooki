# Auditoría por Módulos — Proyecto Zooki

> **Revisión 1.5** · Auditoría de cierre previa a la entrega · SENA ADSO — Ficha 3142784
> Base auditada: rama `release/v1.8.0`, commit `98f9177`. El Módulo 4 se revisó sobre los cambios de v1.9.0.

Registro de hallazgos módulo por módulo. Cada módulo se revisa contra cuatro ejes:

1. **Funcional** — ¿se cumplen los criterios de aceptación de sus HU?
2. **Reglas de negocio** — ¿se hace cumplir la RN en el backend, no solo en la UI?
3. **Arquitectura y código** — `ZOOKI_REGLAS.md`: SOLID, DRY, separación de capas.
4. **UI/UX y CSS** — estados vacíos, feedback, responsive, SweetAlert2 en vez de `alert()`.

## Convenciones

**Severidad:** `Crítica` (bloquea la entrega o compromete el sistema) · `Alta` (incumple un criterio o una RN declarada como aplicada) · `Media` (degrada calidad o cumplimiento parcial) · `Baja` (deuda técnica, no visible para el usuario).

**Eje:** `SEG` seguridad · `FUN` funcional · `ARQ` arquitectura/SOLID/DRY · `UX` interfaz y experiencia · `DOC` documentación desalineada con el código.

**Estado:** `Abierto` · `En curso` · `Corregido` · `Aceptado` (se documenta y no se corrige en esta versión).

## Avance de la auditoría

| Módulo | Estado revisión | Hallazgos | Altos | Corregidos |
|---|---|---|---|---|
| Transversales | ✅ Revisado | 6 | 2 | 3 |
| T — Acceso, seguridad y administración | ✅ **Cerrado** | 27 | 7 | 26 |
| 1 — Mascotas y propietarios | ✅ **Cerrado** | 22 | 3 | 20 |
| 2 — Historia clínica | ✅ **Cerrado** | 15 | 5 | 15 |
| 3 — Vacunación y recordatorios | ◐ Parcial (recordatorios, v1.11.0) | 5 | 1 | 4 |
| 4 — Agenda de citas | ✅ **Cerrado** | 23 | 7 | 20 |
| 5 — Portal del propietario | ⬜ Pendiente | — | — | — |
| 6 — Dashboard y reportes | ⬜ Pendiente | — | — | — |
| 7 — Configuración del sistema | ⬜ Pendiente | — | — | — |
| 8 — Público e institucional | ⬜ Pendiente | — | — | — |

---

# Hallazgos transversales

Afectan a todo el sistema; se listan aparte para no repetirlos en cada módulo.

### TR-01 — Violación masiva de la separación de capas · `ARQ` · **Alta** · Abierto

`ZOOKI_REGLAS.md §1` prohíbe CSS y JS en línea. El conteo real sobre `views/`:

| Infracción | Ocurrencias |
|---|---|
| `style="..."` en línea | **692** |
| `onclick=` / `onchange=` / `onsubmit=` | **230** |
| Bloques `<script>` embebidos | **29** |

> **Conteo en v1.11.0:** 319 `style=`, 248 `onclick=`/`onchange=`/`onsubmit=` y 19 bloques `<script>`. Los estilos bajaron a menos de la mitad, sobre todo por el portal (v1.10.0), pero los manejadores en línea **subieron** de 230 a 248. El peor foco pasó a ser [views/reception/pacientes.php](../views/reception/pacientes.php), con 110 `style=`. Queda para la arquitectura nueva.

Peores focos: [views/admin/auditoria.php](../views/admin/auditoria.php) (53 `style=`), [views/admin/usuarios.php](../views/admin/usuarios.php) (32 `onclick=`).

**Acción:** migrar a clases en `public/css/` y a `addEventListener` con delegación de eventos en `public/js/`. Priorizar las vistas que se mostrarán en la sustentación.

### TR-02 — `alert()` nativo en vez de SweetAlert2 · `UX` · **Media** · ✅ Corregido

`ZOOKI_REGLAS.md §4` prohíbe `alert()`. Había **21 usos**, concentrados en [public/js/medical-module.js](../public/js/medical-module.js) (mascota, propietario, consulta, vacuna, desparasitación), más `calendario.js`, `dashboard.js` y `portal.js`.

> **Corrección de un diagnóstico previo.** La revisión inicial anotó que las llamadas `alert(res.message, "success")` descartaban el segundo argumento en silencio. Eso era **incorrecto**: `dashboard.js` sobrescribía `window.alert` con un envoltorio de SweetAlert2 que usaba ese segundo argumento como icono. Funcionaba — pero **solo en las páginas que cargan `dashboard.js`**. El portal del propietario no lo carga, así que allí `portal.js:527` y `portal.js:672` sí eran `alert()` nativo real. El defecto existía; el mecanismo no era el que se describió.

**Corregido:** nuevo [public/js/avisos.js](../public/js/avisos.js) con `zookiToast`, `zookiAviso` y `zookiConfirmar`, cargado en los **cuatro** layouts (incluido el portal, que se quedaba fuera). Los 21 puntos de llamada migrados. Se eliminó el parche de `window.alert`: además de cubrir solo media aplicación, hacía que `alert()` dejara de significar en este proyecto lo que significa en cualquier otro.

### TR-03 — Botón "Funcionalidad en construcción" visible en producción · `UX` · **Alta** · ✅ Corregido

`medical-module.js` renderizaba en cada tarjeta de mascota un botón de Historial Médico cuyo `onclick` era literalmente `alert('Funcionalidad en construcción')`. Visible para el usuario final.

**Corregido:** el botón se eliminó. Era además redundante: el botón "Ver Ficha" que tiene al lado llama a `loadPetDashboard()`, que ya carga la historia clínica completa vía `listar_historial_ajax`. No se perdió ninguna función; se quitó una promesa vacía.

### TR-04 — Cero cabeceras de seguridad HTTP · `SEG` · **Media** · ✅ Corregido

No existe ninguna: `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Strict-Transport-Security`. El panel administrativo es embebible en un iframe (clickjacking).

**Corregido:** [public/index.php](../public/index.php) emite ahora `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` y, bajo HTTPS, `Strict-Transport-Security`.

**`Content-Security-Policy` queda deliberadamente fuera** hasta cerrar TR-01: con 692 estilos y 29 bloques de script en línea, una CSP estricta rompería la interfaz entera, y una laxa (`unsafe-inline`) no protegería de nada. Es una consecuencia directa de la deuda de TR-01, no un olvido.

### TR-05 — Front controller monolítico · `ARQ` · **Baja** · Abierto

[public/index.php](../public/index.php) son 1060 líneas con un `switch` de 124 casos que instancian controladores a mano. Viola SRP y OCP: agregar una ruta obliga a modificar el archivo central. Un mapa `acción → [controlador, método]` lo reduciría a ~30 líneas y permitiría **derivar de él la matriz RBAC**, eliminando de raíz el riesgo de T-15.

### TR-06 — El rol recepcionista está fuera del alcance, pero la documentación lo sigue nombrando · `DOC` · **Baja** · Aceptado

Por decisión del producto, el módulo de recepción no se usa: la operación la llevan el veterinario y el administrador. El rol sigue existiendo en la base y en la matriz, y varias HU todavía lo nombran como actor (por ejemplo HU-02, HU-03 y HU-13).

Donde el error tenía consecuencias ya se corrigió: en el Módulo 4, la matriz le daba acciones de atención que no podía completar (M4-02), y HU-14, HU-19, HU-27 y HU-29 ya no lo mencionan.

**Acción:** decidir si el rol se retira del sistema o se declara fuera de alcance, y ajustar las HU que quedan.

---

# Módulo T — Acceso, seguridad y administración

**HU cubiertas:** HU-17, HU-22, HU-23, HU-24, HU-32, HU-33, HU-36, HU-38, HU-39, HU-40, HU-41, HU-42, HU-45, HU-54.
**RN aplicables:** RN-G01 … RN-G12, RN-501, RN-504.

## Lo que sí está bien resuelto

Conviene registrarlo porque es material de sustentación:

- **HU-32 (RBAC) — cobertura verificada al 100%.** Se contrastaron las acciones del router contra la matriz de [helpers/Security.php](../helpers/Security.php): **126 acciones en el router, 126 en la matriz, cero huérfanas en ambos sentidos**. El control corre en el front controller antes de instanciar el controlador, así que no depende de la interfaz. Criterio cumplido.
- **HU-36 (política de contraseñas).** [helpers/PoliticaPassword.php](../helpers/PoliticaPassword.php) es la fuente única y la consumen los cuatro flujos exigidos (registro, reset, cambio propio y alta por administrador). Criterio cumplido.
- **HU-38 (rate limiting).** El contador migró de `$_SESSION` a la tabla `intentos_login`, con límite por IP y por cuenta; descartar la cookie ya no lo reinicia. Criterio cumplido.
- **HU-33 (último administrador).** `esUltimoAdminActivo()` se verifica tanto en `actualizarAjax` como en `cambiarEstadoAjax`, cubriendo las dos vías de pérdida de acceso: quitar el rol y desactivar. Criterio cumplido.
- **HU-24 (inmutabilidad).** [models/Auditoria.php](../models/Auditoria.php) no expone ningún `update` ni `delete`; el log es de solo lectura a nivel de aplicación.

## Hallazgos

Estado tras la sesión de cierre: **26 de 27 corregidos**, 1 aceptado con justificación.
Verificación: `phpunit` en verde (101 pruebas, 483 aserciones) y cobertura RBAC 127/127.

### Altos

#### T-01 — IDOR: cualquier usuario marca notificaciones ajenas · `SEG` · **Alta** · ✅ Corregido

`NotificacionInterna::marcarLeida()` ejecutaba `UPDATE ... SET leida = 1 WHERE id = :id` **sin filtro de propiedad**, y el controlador solo comprobaba que existiera sesión. La matriz RBAC concede `marcar_notificacion_leida_ajax` a los cuatro roles, así que **un propietario podía marcar como leída la notificación de un administrador** enviando cualquier `id`.

Violaba **RN-G02**. El hallazgo **VD-SEG-04 seguía abierto** pese a que HU-32 se declaró implementada: el RBAC por rol no resuelve el IDOR entre usuarios del mismo rol.

**Corregido:** se añadió [`NotificacionInterna::perteneceA()`](../models/NotificacionInterna.php), que comprueba destinatario (documento o rol) antes de escribir; el controlador responde **403** si no pertenece. El `UPDATE` lleva además el mismo filtro, como segunda barrera.

> La propiedad se verifica con un `SELECT` y no con las filas afectadas por el `UPDATE`: MySQL no cuenta como afectada una fila que ya tenía `leida = 1`, así que volver a marcar algo ya leído se habría confundido con un intento de acceso ajeno. Hay una prueba dedicada a ese caso.

Cubierto por [tests/Integration/NotificacionAccesoTest.php](../tests/Integration/NotificacionAccesoTest.php) (8 pruebas).

#### T-02 — Session fixation: no se regeneraba el id de sesión · `SEG` · **Alta** · ✅ Corregido

El login poblaba `$_SESSION` sobre el identificador de sesión que el cliente ya traía. Quien lograra fijar un `PHPSESSID` en el navegador de la víctima conservaba la sesión ya autenticada.

**Corregido:** `session_regenerate_id(true)` en **los cuatro puntos** donde se crea sesión —login con contraseña, login con Google, alta por verificación de correo y registro con Google—, no solo en el principal. En `logout` se limpia además la cookie del navegador, que antes sobrevivía a `session_destroy()`.

#### T-03 — El hash bcrypt de cualquier usuario se exponía al navegador y al log · `SEG` · **Alta** · ✅ Corregido

`Usuario::getById()` hacía `SELECT *`, así que arrastraba la columna `password`; `getUsuarioAjax` la serializaba tal cual al navegador y la línea anterior volcaba el registro completo al log con `error_log(json_encode($u))`.

**Corregido:** columnas explícitas en `getById()` y eliminación de los `error_log` de depuración. Quien necesita verificar la contraseña usa `getUserByDocumento()`, que sí la trae y nunca se expone.

> Efecto lateral revelador: el cambio rompió 6 pruebas porque el fixture SQLite declaraba una tabla `usuarios` incompleta. El `SELECT *` lo estaba enmascarando. Se alineó el fixture con el esquema real.

#### T-04 — Fuga de errores técnicos al cliente · `SEG` · **Alta** · ✅ Corregido

HU-38 exige textualmente *"Los mensajes de error al cliente son genéricos"*. `UsuarioController` lo incumplía en cuatro puntos con `'Error: ' . $e->getMessage()`, y sobre todo en la rama `PDOException`, que devolvía al navegador **el mensaje SQL crudo más una cadena `Debug:` con los documentos implicados**. El hallazgo **VD-SEG-09 seguía abierto** ahí.

**Corregido:** todo detalle técnico va a `error_log` y al cliente le llega un mensaje genérico. La violación de clave foránea, que sí es accionable para el usuario, se traduce a *"No se puede cambiar el documento: el usuario ya tiene registros asociados"*.

#### T-05 — `debe_cambiar_password` no se hacía cumplir · `SEG` · **Alta** · ✅ Corregido

El login creaba la sesión **con el rol completo** y solo entonces redirigía al formulario. Bastaba escribir `index.php?action=admin_panel` para operar con normalidad sin haber cambiado la contraseña temporal, que seguía siendo válida indefinidamente.

**Corregido:** `Security::validatePasswordTemporal()` corre en el front controller y bloquea toda acción salvo `cambiar_password`, `cambiar_password_ajax` y `logout`. Al cambiarla se baja también el indicador en la sesión, no solo en la base de datos.

> No se reutilizó `denegar()`: su rama 403 redirige al dashboard, que aquí también está bloqueado, y habría provocado un bucle de redirecciones. La ruta de escape apunta al propio formulario.

Esto era además el bloqueante de **HU-54**.

#### T-06 — Backup con credenciales embebidas y usuario `root` · `SEG` · **Alta** · ✅ Corregido

`scripts/backup.php` fijaba `root` y contraseña vacía en el propio archivo, ignorando `.env` y `config/Database.php`: quedaban versionadas en git y el respaldo fallaba en cualquier entorno donde la clave real no fuera vacía.

**Corregido:** las credenciales salen del `.env`, la misma fuente que usa `Database.php`. Además `--password=` se sustituyó por `--defaults-extra-file` con un fichero temporal en modo 0600 que se borra pase lo que pase: los argumentos de un proceso son visibles con un simple `ps` para cualquier usuario del servidor.

### Medios

#### T-07 — Cookie de sesión sin `HttpOnly`, `Secure` ni `SameSite` · `SEG` · **Media** · ✅ Corregido

**Corregido:** `session_set_cookie_params()` en [public/index.php](../public/index.php), **antes** de abrir la sesión —si va después, los flags no aplican—. `Secure` se activa solo bajo HTTPS para no romper el desarrollo local.

#### T-08 — La auditoría aceptaba una IP falsificable · `SEG` · **Media** · ✅ Corregido

`Auditoria::log()` sobrescribía `REMOTE_ADDR` con `HTTP_X_FORWARDED_FOR` **sin mirar quién la enviaba**. Como esa cabecera la pone el propio cliente, cualquiera podía escribir en el log de seguridad la IP que quisiera, incluida la de otra persona. Comprometía **RN-G05**.

**Corregido:** la cabecera solo se acepta si la petición llega desde un proxy declarado en `TRUSTED_PROXIES` (`.env`); en cualquier otro caso vale la IP de la conexión, que no se puede falsificar. El valor tomado se valida además con `FILTER_VALIDATE_IP`.

#### T-09 — HU-42 estaba sobre-declarada como "Implementada" · `DOC`/`FUN` · **Media** · ✅ Corregido

La única ruta existente era `portal_actualizar_datos_contacto_ajax`, **exclusiva del propietario**. Administrador, veterinario y recepcionista no tenían vista de perfil. Recepción era el caso peor: su avatar era un `<img>` estático sin menú, así que **tampoco tenía acceso al cambio de contraseña (HU-39)**.

**Corregido:** nuevo [controllers/PerfilController.php](../controllers/PerfilController.php), con responsabilidad única sobre la cuenta propia —administrar usuarios ajenos sigue siendo de `UsuarioController`, que exige rol administrador—. El sujeto sale siempre de `$_SESSION`, nunca del POST. Recepción recibió un menú de perfil real.

> Documento, nombre y rol son de solo lectura a propósito: el rol lo asigna el administrador (RN-501) y dejarlo editable en el perfil propio sería exactamente la escalada de privilegios que corrigió HU-33.

#### T-10 — HU-45: "marcar todas como leídas" no existía en la interfaz · `FUN` · **Media** · ✅ Corregido

La acción estaba en el router y en la matriz RBAC, pero **ningún archivo de `public/js/` la invocaba**: endpoint muerto.

**Corregido:** botón en los tres layouts internos, con `addEventListener` (no `onclick` inline), su CSS en `dashboard-modules/layout.css`, y visible solo cuando hay pendientes.

#### T-11 — La auditoría registraba `estado_anterior => 'desconocido'` · `FUN` · **Media** · ✅ Corregido

Se escribía literalmente esa cadena como dato previo, lo que dejaba el log inservible para reconstruir el cambio e incumplía el criterio de HU-24.

**Corregido:** se consulta el estado previo antes de escribir, tanto en `cambiarEstadoAjax` como en `actualizarAjax`.

#### T-12 — El respaldo no salía del servidor principal · `FUN` · **Media** · ✅ Corregido

Criterio de HU-23: *"Copia almacenada en un directorio externo al servidor principal"*. Se guardaba en `./backups`, dentro del propio proyecto. Y **RN-504** declara respaldos cada 24 h, pero la automatización solo existía como un comentario.

**Corregido:** destino configurable con `BACKUP_DIR`, retención con `BACKUP_RETENCION_DIAS`, y la programación versionada en [scripts/zooki.cron](../scripts/zooki.cron) junto a la de recordatorios. El log de ejecución se queda en el proyecto aunque el destino sea externo.

#### T-13 — HU-54 pendiente y HU-22 con un criterio sin cubrir · `FUN` · **Media** · ✅ Corregido

**Corregido:** `UsuarioController::resetearPasswordAjax()` genera una temporal que cumple RN-G10, la envía por correo y marca `debe_cambiar_password` —cuya obligatoriedad ahora sí se hace cumplir, gracias a T-05—. Botón en el panel de personal, con confirmación SweetAlert2. Un administrador no puede restablecerse a sí mismo por esta vía: para eso está su perfil, que sí pide la contraseña actual.

#### T-14 — Sin validación de obligatoriedad ni formato en el backend · `SEG`/`ARQ` · **Media** · ✅ Corregido

`registrarAjax` y `actualizarAjax` leían los campos directo de `$_POST` sin `isset` ni validación. `ZOOKI_REGLAS.md §4` exige validar en front **y** en back; solo validaba el front, que es justo la capa que un atacante no ejecuta.

**Corregido:** `validarDatosUsuario()` centraliza obligatoriedad, formato de correo, documento numérico, longitud del nombre, tipo de documento contra lista cerrada, formato de teléfono, rol válido y estado binario. Una sola función para las dos rutas (DRY).

### Bajos

| ID | Hallazgo | Estado | Resolución |
|---|---|---|---|
| T-15 | `validateRole()` era **fail-open**: una acción ausente de la matriz pasaba sin control de rol. | ✅ | Ahora deniega por defecto. Agregar una ruta y olvidar registrarla falla de inmediato y del lado seguro. |
| T-16 | `Security::$ajaxActions` (35 entradas) redundante con la matriz. | ✅ | Eliminada. `validateRole()` ya devuelve 401 sin rol en sesión. |
| T-17 | `UsuarioController::listar()` repetía el control de rol comparando la cadena `'administrador'`. | ✅ | Eliminado. Además fallaba en sesiones de Google donde ese campo no siempre se guardaba. |
| T-18 | `session_start()` repetido en 4 controladores. | ✅ | Eliminados. No solo eran ruido: de haberse ejecutado, habrían abierto la sesión **sin** los flags de T-07. |
| T-19 | `error_log()` de depuración con datos de usuario. | ✅ | Eliminados de `Usuario::update()` y `getUsuarioAjax`. |
| T-20 | Código muerto: `$debugInfo`, `$ratio`. | ✅ | `$debugInfo` eliminado; `$ratio` pasó a usarse en el log del respaldo. |
| T-21 | `trim()` sobre la contraseña en el login. | ✅ | Se recorta el documento, no la contraseña: recortarla dejaba fuera cualquier clave con espacio inicial o final. |
| T-22 | `resetRateLimit()` no se ejecutaba con `debe_cambiar_password = 1`. | ✅ | Movido antes de la redirección. |
| T-23 | `contarNoLeidas()` devolvía una cadena. | ✅ | Cast a entero, con prueba (`assertSame`). |
| T-24 | Comentario obsoleto sobre contraseñas en texto plano. | ✅ | Eliminado. |
| T-25 | El login valida CSRF con el token `'login'` y el resto del sistema con `'default'`. | ⚠️ **Aceptado** | Funciona correctamente y es una duplicación de mecanismo, no un agujero. Tocar la validación CSRF del login a las puertas de la entrega tiene más riesgo que beneficio. Unificar en la limpieza de TR-05. |

### Hallazgos nuevos, detectados durante la corrección

#### T-26 — El toggle de activar/desactivar colaborador nunca funcionó · `FUN` · **Alta** · ✅ Corregido

[views/admin/personal.php](../views/admin/personal.php) llamaba a `cambiar_estado_usuario_ajax` con un `fetch` **GET** y los datos en la query string, pero `cambiarEstadoAjax` solo atiende `POST` y lee `$_POST`. La petición no hacía nada, no se emitía respuesta, y `.json()` reventaba sobre un cuerpo vacío — silenciado por el `catch`.

Es decir: **activar y desactivar colaboradores desde el panel de personal no ha funcionado nunca**. La misma acción desde `usuarios.php` sí, porque allí sí va por POST. Incumplía el primer criterio de HU-22.

**Corregido:** POST con `FormData`, como el resto del sistema.

> Este es el hallazgo que mejor justifica la auditoría: no lo revela leer la documentación ni el backend, solo cruzar quién llama con qué espera el que responde.

#### T-27 — `backups/` y `logs/` no estaban en `.gitignore` · `SEG` · **Media** · ✅ Corregido

Si `BACKUP_DIR` no se configura, los dumps caen en `./backups`. Al no estar ignorado, **un `git add .` habría versionado la historia clínica completa** en el repositorio.

**Corregido:** `/backups/`, `*.sql.gz` y `/logs/` añadidos a [.gitignore](../.gitignore).

## Resumen del Módulo T

| Severidad | Hallazgos | Corregidos | Aceptados |
|---|---|---|---|
| Crítica | 0 | — | — |
| Alta | 7 | 7 | 0 |
| Media | 9 | 9 | 0 |
| Baja | 11 | 10 | 1 |
| **Total** | **27** | **26** | **1** |

### Verificación

| Comprobación | Resultado |
|---|---|
| Suite de pruebas | **101 pruebas, 483 aserciones, en verde** (eran 90) |
| Cobertura RBAC router ↔ matriz | **127 / 127**, cero huérfanas en ambos sentidos |
| Sintaxis PHP de los archivos tocados | Sin errores |
| HU del módulo pendientes | **Ninguna** (HU-54 cerrada) |

Pruebas añadidas: 8 sobre el aislamiento de notificaciones (T-01) y 3 sobre autorización —HU-54 solo administrador, fail-closed de la matriz (T-15) y alcance del bloqueo por contraseña temporal (T-05)—.

**Veredicto:** la base de seguridad de HU-32/33/36/38 era sólida y verificable; lo que fallaba era la **autorización por objeto**, la **higiene de datos sensibles** y **criterios secundarios que se dieron por hechos**. Todo eso queda cerrado. El módulo entra a la entrega sin HU pendientes y con las tres HU sobre-declaradas (HU-42, HU-45, HU-23) ahora cumpliendo sus criterios de verdad.

La deuda que sigue viva es transversal, no de este módulo: **TR-01** (692 estilos y 230 manejadores en línea) y **TR-05** (el front controller monolítico). TR-02 y TR-03 se cerraron durante la revisión del Módulo 1, porque su código vive allí. TR-01 condiciona además la `Content-Security-Policy` que quedó fuera de TR-04.

---

# Módulo 1 — Mascotas y propietarios

**HU cubiertas:** HU-01 (registrar mascota), HU-02 (registrar propietario), HU-03 (buscar paciente), HU-04 (editar y desactivar).
**RN aplicables:** RN-101 … RN-108, RN-G06, RN-G10.

Estado: **22 hallazgos, 20 corregidos**, 2 aceptados con justificación.

## Lo que sí está bien resuelto

- **RN-104 / RN-105.** No existe ningún endpoint de borrado de mascotas: el sistema solo cambia `estado`. La regla se cumple por diseño, no por una comprobación que se pueda olvidar. `search()` y `getByPropietario()` filtran `estado = 1`.
- **RN-102.** El número de historia clínica no se asigna en el alta sino en la primera consulta, como pide la regla.
- **HU-35 (heredado).** `getPropietarioSiActiva()` ya rechazaba identificadores no enteros en vez de dejar que el cast los convirtiera — `(int)"1 OR 1=1"` da `1`. Buen precedente, aplicado ahora al resto del módulo.

## Hallazgos

### Altos

#### M1-01 — La contraseña inicial de todo propietario era su número de documento · `SEG` · **Alta** · ✅ Corregido

`PropietarioController` creaba la cuenta con `password_hash($_POST['documento'])`, y el mensaje de éxito lo anunciaba: *"Su contraseña inicial es su número de documento"*. Tampoco marcaba `debe_cambiar_password`, así que nunca caducaba.

Contradice **literalmente RN-G10**, que está marcada como *Aplicada* y dice que la contraseña *"se rechaza si contiene el documento, el nombre o el correo del titular"*. Y el documento no es un secreto: aparece en el listado de propietarios, en las fichas de paciente y en los resultados de búsqueda. **Cualquier miembro del personal podía entrar como cualquier propietario** con un dato que tenía a la vista.

**Corregido:** se genera una temporal que cumple la política, se envía por correo y queda marcada como obligatoria de cambiar — bloqueo que ya hace cumplir `Security::validatePasswordTemporal()` desde T-05.

> El generador se movió de `UsuarioController` a `PoliticaPassword::generarTemporal()`. Estaba encerrado como método privado de un controlador, y por eso el alta de propietarios no lo usaba: no podía. La duplicación no fue pereza, fue una dependencia mal colocada.

#### M1-02 — Path traversal en el nombre de la foto de la mascota · `SEG` · **Alta** · ✅ Corregido

El nombre del archivo se construía así:

```php
$foto_nombre = time() . '_' . str_replace(' ', '_', $nombre) . '.' . $ext;
```

`str_replace` solo toca los espacios: las barras y los puntos pasaban intactos. Una mascota registrada con el nombre `../../evil` escribía el archivo **fuera** de `public/uploads/mascotas/`.

Estaba en **tres** sitios: `registrarAjax()`, `actualizarAjax()` y la ruta no-AJAX `actualizar()` — esta última sin validar siquiera extensión ni tamaño, y tomando la extensión literal de lo que enviara el cliente.

**Corregido:** un único `procesarFotoMascota()`. El nombre lo genera el servidor (`time()` más 8 caracteres hexadecimales aleatorios) y del nombre de la mascota solo sobreviven letras, dígitos y guiones.

#### M1-03 — HU-02 no validaba documentos ni correos duplicados · `FUN` · **Alta** · ✅ Corregido

Es un criterio textual de HU-02 (*"No se permiten documentos ni correos duplicados"*) y no existía. El duplicado llegaba hasta la restricción de unicidad de la base, que lanzaba una `PDOException` sin capturar: respuesta vacía, `.json()` reventado en el navegador y ningún mensaje que dijera que el propietario ya existía.

**Corregido:** `validarDatosPropietario()` comprueba obligatoriedad, formato y unicidad de documento y correo (RN-G06) antes de escribir, y ambas rutas quedaron envueltas en `try/catch`.

### Medios

| ID | Hallazgo | Estado | Resolución |
|---|---|---|---|
| M1-04 | HU-01 pide nombre, especie, raza, fecha de nacimiento, peso, sexo y color; el backend solo exigía nombre, especie y propietario. Lo demás lo validaba únicamente el formulario, la capa que un atacante no ejecuta. | ✅ | `validarDatosMascota()` cubre los siete campos, con rangos (peso 0-500 kg) y fecha no futura. |
| M1-05 | **RN-106 no se hacía cumplir**: el `id_raza` se tomaba crudo del POST. Nada impedía registrar un gato de raza "Pastor Alemán". | ✅ | `razaPerteneceAEspecie()`, con prueba dedicada. |
| M1-06 | La foto se validaba por la extensión **del nombre que envía el cliente**, no por su contenido. Y `mkdir(..., 0777)`. | ✅ | El tipo se deduce con `getimagesize()` y la extensión sale del tipo real. Permisos a 0755. |
| M1-07 | Sin transacciones: `insert` + `saveColores` (+ auditoría) eran escrituras sueltas. Si fallaba la segunda, la mascota quedaba sin ningún color (RN-107) y nadie se enteraba. | ✅ | Alta y edición envueltas en transacción con `rollBack`. |
| M1-08 | `cambiarEstadoAjax` repetía el `'estado_anterior' => 'desconocido'` de T-11, y **no comprobaba que la mascota existiera**: `updateStatus()` devuelve `true` aunque no afecte ninguna fila, así que inactivar una mascota inexistente se reportaba como éxito. | ✅ | Se lee el estado previo real; si la mascota no existe, error explícito. |
| M1-09 | La auditoría de la ficha (RN-108) omitía `fecha_nacimiento`, `doc_propietario`, `url_foto` y los colores. **Un cambio de dueño no dejaba ningún rastro**, pese a que el criterio de HU-04 pide registrar "el campo modificado". | ✅ | `registrarCambiosMascota()` cubre los nueve campos y compara los colores como conjunto. |
| M1-10 | `insertRaza` no comprobaba duplicados, a diferencia de especies y colores. Cada vez que alguien escribía "Labrador" en el campo de raza nueva se creaba otra entrada repetida. | ✅ | `obtenerOCrearRaza()`, insensible a mayúsculas y por especie. |
| M1-11 | Sin `try/catch` en los endpoints: una `PDOException` producía cuerpo vacío y rompía el front. Es el mismo patrón de fallo silencioso de T-26. | ✅ | Todos los endpoints del módulo capturan `Throwable`. |
| M1-19 | El alta de propietario no se registraba en auditoría (RN-G05) ni enviaba credenciales al interesado. | ✅ | Ambas cosas, dentro del mismo flujo. |
| M1-21 | HU-03 pide que los resultados muestren *"nombre, especie, propietario y foto miniatura"*. **La especie no se mostraba.** | ✅ | Especie y raza añadidas al resultado. |

### Bajos

| ID | Hallazgo | Estado | Resolución |
|---|---|---|---|
| M1-12 | SQL suelto dentro del controlador (`registrarEspecieAjax`, `registrarColorAjax`). | ✅ | Movido al modelo. El controlador ya no tiene ni un `prepare()`. |
| M1-14 | El bloque de subida de foto (~45 líneas) estaba copiado en dos métodos, y una tercera variante degradada en `actualizar()`. | ✅ | Un solo `procesarFotoMascota()`. Por eso M1-02 estaba en tres sitios. |
| M1-15 | HU-03 pide un mínimo de 3 caracteres; front y backend usaban 2. | ✅ | 3 en ambos lados. |
| M1-16 | La foto anterior no se borraba al reemplazarla: una imagen huérfana por cada cambio. | ✅ | Se borra tras el commit, con `basename()` para que no pueda salir de la carpeta. |
| M1-17 | `registrar()` y `registrarAjax()` de propietario eran el mismo código duplicado. | ✅ | Un `crearPropietario()` compartido. |
| M1-18 | `getMascotaAjax` y `listarRazasAjax` no emitían cuerpo si faltaba el parámetro, y el `.json()` del navegador reventaba. | ✅ | Respuesta JSON explícita siempre. |
| M1-22 | `search()` arrastraba dos JOIN y un `GROUP_CONCAT`/`GROUP BY` para traer los colores, que **ningún consumidor de la búsqueda usa**, en cada pulsación de tecla. | ✅ | JOINs eliminados y columnas explícitas en vez de `m.*`. HU-03 pide resultados en menos de 2 s. |
| M1-13 | `MascotaController` gestiona mascotas **y** propietarios **y** especies **y** razas **y** colores: cinco responsabilidades (SRP). | ⚠️ **Aceptado** | Partirlo toca el router, la matriz RBAC y tres vistas. El riesgo no compensa a las puertas de la entrega; queda anotado junto a TR-05. |
| M1-20 | 110 `style=` y 48 `onclick=` en `reception/pacientes.php`; 30 y 48 en `vet/pacientes.php`. | ⚠️ **Aceptado** | Es TR-01, que se trata como una tarea transversal propia. |

## Resumen del Módulo 1

| Severidad | Hallazgos | Corregidos | Aceptados |
|---|---|---|---|
| Alta | 3 | 3 | 0 |
| Media | 10 | 10 | 0 |
| Baja | 9 | 7 | 2 |
| **Total** | **22** | **20** | **2** |

Pruebas añadidas: [tests/Integration/MascotaCatalogoTest.php](../tests/Integration/MascotaCatalogoTest.php), 14 casos sobre RN-101, RN-104, RN-105 y RN-106.

**Veredicto:** es el módulo más antiguo del sistema y se nota. Es el único donde apareció una **regla de negocio contradicha de frente** (M1-01) y no solo criterios sin cubrir. El patrón dominante es el mismo del Módulo T: **la validación existía solo en el formulario**, es decir en la única capa que no protege nada. Las cuatro HU del módulo quedan cumpliendo sus criterios.

---

# Módulo 2 — Historia clínica

**HU cubiertas:** HU-05 (registrar consulta), HU-06 (adjuntos), HU-07 (tratamientos), HU-08 (historial), HU-34 (*era Pendiente*), HU-35.
**RN aplicables:** RN-201 … RN-208, RN-102, RN-G02.

Estado: **15 hallazgos, 15 corregidos**. **HU-34 pasa de Pendiente a Implementada.**

## Lo que sí está bien resuelto

- **HU-35 y `ValidadorClinico`.** Es la parte mejor construida del sistema hasta ahora. `ValidadorClinico::id()` rechaza lo que no sea entero positivo en vez de dejar que el cast lo convierta, y los signos vitales se validan contra rangos plausibles (peso 0,01-200 kg, temperatura 25-45 °C, frecuencia 10-400 lpm) con el argumento correcto: un valor fuera de rango es un error de digitación, y guardarlo ensucia la historia clínica de forma permanente (RN-206).
- **RN-202 y RN-203.** El diagnóstico obligatorio y la comprobación de que la cita vinculada sea de la misma mascota ya estaban, y bien.
- **RN-206.** No existe ningún endpoint que borre o edite una consulta: la historia clínica es acumulativa por diseño.

## Hallazgos

### Altos

#### M2-01 — HU-34: el registro de consulta no era atómico · `FUN` · **Alta** · ✅ Corregido

Consulta, número de historia clínica, adjuntos y tratamientos eran **cuatro escrituras sueltas**. Si fallaba la última, las tres primeras quedaban guardadas y el veterinario recibía un mensaje de éxito igualmente.

**Corregido:** una sola transacción. Y como el sistema de archivos no participa del rollback, los adjuntos ya movidos a disco se borran a mano en el `catch`.

#### M2-02 — Los adjuntos rechazados se descartaban en silencio · `FUN` · **Alta** · ✅ Corregido

El bucle de subida era, en esencia:

```php
if (in_array($ext, $allowed) && $size <= 10 * 1024 * 1024) {
    if (move_uploaded_file(...)) { $this->consultaModel->saveArchivo([...]); }
}
```

Sin `else`. Un archivo con extensión no permitida, de más de 10 MB, con error de subida, o cuyo `move_uploaded_file` fallara, **desaparecía sin dejar rastro**. Y la respuesta decía, literalmente: *"Consulta registrada correctamente con sus adjuntos y tratamientos"*.

Una radiografía perdida sin que nadie se entere es peor que tener que reintentar.

**Corregido:** los adjuntos se revisan **antes** de abrir la transacción. Si alguno no sirve no se guarda nada y la respuesta lleva `adjuntos_rechazados` con archivo y motivo, uno por uno; el formulario los muestra en una lista y se queda abierto con los datos escritos.

#### M2-03 — Ningún adjunto clínico se podía abrir · `FUN` · **Alta** · ✅ Corregido

El enlace del área del veterinario apuntaba a `ver_archivo.php?id=${file.id_archivo}`, pero el script leía `$_GET['file']`. **El parámetro nunca llegaba**: la respuesta era siempre *"Archivo no especificado"*.

Es decir, HU-06 estaba marcada como Implementada y la descarga de adjuntos **no funcionaba en ningún caso**.

**Corregido:** `ver_archivo.php` reescrito para recibir `id`.

> Es el mismo tipo de fallo que T-26: nadie lo detecta leyendo el backend ni la documentación, solo cruzando quién llama con qué espera el que responde.

#### M2-04 — IDOR: cualquier sesión podía leer cualquier adjunto clínico · `SEG` · **Alta** · ✅ Corregido

`ver_archivo.php` tomaba un **nombre de archivo** y lo servía a cualquier usuario autenticado, sin mirar de quién era la mascota. Un propietario podía leer los adjuntos de pacientes ajenos, y los nombres eran parcialmente adivinables porque seguían el patrón `CLI_{id_consulta}_{timestamp}_{i}.{ext}` con `id_consulta` secuencial.

Viola **RN-G02** y el criterio de HU-06.

**Corregido:** se recibe el id, se resuelve el adjunto hasta el dueño de la mascota y se decide por rol — veterinario y administrador ven cualquiera (RN-208), el propietario solo los suyos (RN-G02), recepción ninguno, coherente con que la matriz tampoco le da el historial. Los intentos denegados quedan en el log.

#### M2-05 — El portal enlazaba los archivos saltándose el control de acceso · `SEG` · **Alta** · ✅ Corregido

`portal.js` construía el enlace con `ruta_archivo` directo (`uploads/clinicos/...`), sin pasar por `ver_archivo.php`. Lo único que lo frenaba era el `.htaccess` de la carpeta — que **solo funciona en Apache con `AllowOverride` activo**. En Nginx, o con esa directiva desactivada, la historia clínica completa quedaba descargable sin autenticación.

RN-204 dice "carpeta protegida y solo usuarios autenticados y autorizados pueden descargarlos": la protección no puede depender de la configuración del servidor web.

**Corregido:** el portal enlaza por `ver_archivo.php?id=`. El `.htaccess` se queda como segunda barrera, no como la principal.

### Medios

| ID | Hallazgo | Estado | Resolución |
|---|---|---|---|
| M2-06 | El formato del adjunto se validaba por la extensión **del nombre que envía el cliente**, y al servirlo el tipo salía de `mime_content_type()` con `Content-Disposition: inline`. Un HTML subido como `.jpg` se habría servido y ejecutado en el navegador. | ✅ | El tipo se deduce del contenido (`getimagesize()`, o la firma `%PDF-`), y al servir, el `Content-Type` sale de una lista cerrada de cuatro entradas. |
| M2-07 | `ver_archivo.php` es un punto de entrada fuera de `index.php`: `session_start()` desnudo, sin los flags de cookie de T-07 ni cabeceras de seguridad. | ✅ | Mismos parámetros de cookie y cabeceras que el front controller. |
| M2-08 | Los tratamientos se leían con `$_POST['med_dosis'][$index]` sin comprobar que existieran, y sin validar los campos que HU-07 exige (medicamento, dosis, vía, duración). | ✅ | `revisarTratamientos()` valida y recorta; si falta un campo se rechaza la consulta nombrando el medicamento. |
| M2-09 | `listarHistorialAjax` no emitía cuerpo si faltaba el parámetro, y devolvía `mascota: null` si el id no existía, con lo que la vista fallaba al leer sus campos. | ✅ | Respuesta JSON siempre; los tres consumidores comprueban `success`. |
| M2-10 | **N+1 en el historial**: dos consultas SQL extra por cada consulta clínica. Con 100 entradas eran 201 viajes a la base, y HU-08 pide cargar en menos de 3 segundos justo para ese tamaño. | ✅ | `getArchivosDeConsultas()` y `findByConsultas()` agrupan por `IN`. Ahora son 5 consultas fijas, sea cual sea el tamaño del historial. |
| M2-11 | `getDashboardStatsAjax` vivía en `ConsultaController` (el propio router lo admitía en un comentario: *"Or create a specific dashboard controller"*), con SQL directo y filtrando `$e->getMessage()` al cliente. | ✅ | Ver M2-15. |

### Bajos

| ID | Hallazgo | Estado | Resolución |
|---|---|---|---|
| M2-12 | `Consulta::countByMascota()` devuelve una cadena y no lo usa nadie: el HC se decide con `empty($mascota['numero_historia_clinica'])`. | ✅ | Se deja, pero el flujo de HC quedó documentado en `registrarAjax`. |
| M2-13 | `findAll()` no tiene límite: el listado global de consultas crece sin control. | ⚠️ | Anotado. Hoy el volumen es pequeño; conviene paginar antes de que crezca. |
| M2-14 | `$_FILES['archivos']` se recorría sin comprobar que fuera un arreglo. | ✅ | Se verifica antes de entrar al bucle. |
| M2-15 | **`get_dashboard_stats_ajax` era un endpoint muerto**: cero consumidores en todo el front, y duplicaba lo que `get_role_stats_ajax` ya hace mejor y por rol. | ✅ | Eliminado — ruta, método y entrada en la matriz RBAC. Menos superficie y una fuente de verdad. |

## Resumen del Módulo 2

| Severidad | Hallazgos | Corregidos |
|---|---|---|
| Alta | 5 | 5 |
| Media | 6 | 6 |
| Baja | 4 | 4 |
| **Total** | **15** | **15** |

Pruebas añadidas: [tests/Integration/ConsultaHistorialTest.php](../tests/Integration/ConsultaHistorialTest.php), 9 casos sobre la resolución del dueño de un adjunto (M2-04), el agrupado sin N+1 (M2-10) y el orden cronológico de RN-206.

**Veredicto:** el módulo tenía la mejor capa de validación del sistema (`ValidadorClinico`) y a la vez **el peor manejo de evidencia clínica**. Los tres hallazgos que más pesan no son de validación sino de *entrega*: los adjuntos se perdían en silencio, no se podían abrir, y quien sí lograba abrirlos podía abrir también los ajenos.

`ver_archivo.php` concentraba tres de los cinco altos. Era el único punto de entrada del sistema fuera del front controller, y precisamente por eso se había quedado fuera de todas las mejoras transversales: RBAC, flags de cookie y cabeceras de seguridad pasaban por `index.php` y a él no le llegaba ninguna.

---

# Módulo 4 — Agenda de citas

**HU cubiertas:** HU-13 (agendar), HU-14 (cancelar o reprogramar), HU-19 (completar), HU-21 (confirmación por correo), HU-27 (*era Pendiente*), HU-29 (*era Pendiente*). Relacionadas: HU-28, HU-30, HU-31, HU-50.
**RN aplicables:** RN-401 … RN-409, RN-208, RN-G02.

Estado: **23 hallazgos: 20 corregidos, 2 parciales y 1 aceptado** (M4-20 se cerró en v1.9.1). HU-27, HU-29 y HU-50 pasan de Pendiente a Parcial.

> **Origen de la revisión.** El detonante fue un reporte de uso: una cita iniciada a las 10:15 seguía «en curso» horas después. No era un caso aislado. En la base local había tres citas de junio atascadas en ese estado, tres citas completadas sin consulta y nueve citas pasadas, pendientes o confirmadas, que nadie cerró.

## Flujo de estados

Hasta v1.8.0 el flujo no estaba escrito en ningún documento (VD-AGN-09 ya lo advertía) y cada pantalla aplicaba reglas propias. Queda así, y lo hace cumplir el servidor, no la interfaz:

| Desde | Hacia | Quién | Condición |
|---|---|---|---|
| — | confirmada | Administrador, veterinario (sus citas) o propietario (sus mascotas) | Fecha y hora futuras, dentro del horario y sin solapamientos |
| pendiente | confirmada | Administrador o veterinario asignado | — |
| pendiente / confirmada | en curso | Veterinario asignado | Solo el día de la cita. Sella `hora_inicio_real` |
| en curso | completada | Veterinario asignado | Al guardar la consulta, en la misma transacción. Sella `hora_fin_real` |
| pendiente / confirmada | no asistió | Veterinario asignado | La hora de la cita ya pasó. Libera el espacio |
| pendiente / confirmada | cancelada | Administrador, veterinario asignado o propietario | — |
| pendiente / confirmada | (reprogramada) | Administrador, que puede reasignar el veterinario, o veterinario asignado | Momento futuro, sin solapamientos del veterinario ni de la mascota |

Una cita en curso siempre se puede retomar con «Continuar atención», sea del día que sea. Ninguna transición sale de completada, cancelada o no asistió. Cada cambio de estado lleva el estado de origen en el `WHERE` (`Cita::transicion`), así que dos acciones simultáneas sobre la misma cita no se pisan.

## Lo que sí está bien resuelto

- **RN-401.** El solapamiento del veterinario y de la mascota se valida en el servidor con rangos de tiempo, no solo en el formulario.
- **Notificaciones.** Quedan enlazadas a su cita y vencen con ella: no se acumulan avisos de citas ya resueltas.
- **HU-21.** El correo de la cita se envía después de cerrar la respuesta y con la sesión liberada, sin bloquear el calendario.

## Hallazgos

### Altos

#### M4-01 — Una cita podía quedar «en curso» para siempre · `FUN` · **Alta** · ✅ Corregido

La única salida del estado era el botón «Finalizar atención» de la pantalla de atención. Si el veterinario salía de esa pantalla (la flecha de volver, otra sección, cerrar la pestaña), la cita quedaba en curso sin forma de cerrarla. El calendario no ofrecía nada para eso y, al cambiar de día, ocultaba todos los botones de la cita (`esCitaPasada`). La hora de la cita no influía en nada.

**Corregido:** «Continuar atención» aparece siempre para el veterinario asignado mientras la cita esté en curso, sea del día que sea. Volver a iniciar una cita en curso la retoma en lugar de responder con un error.

#### M4-02 — Recepción y administración podían iniciar una atención que no podían hacer · `SEG`/`FUN` · **Alta** · ✅ Corregido

La matriz daba `iniciar_cita_ajax` y `completar_cita_ajax` a todo el personal, y el controlador lo repetía (`in_array($rol, [1, 3])`). Pero tras iniciar, la respuesta redirigía a `vet_atencion`, que es solo del veterinario: la cita pasaba a en curso y a quien la había iniciado lo rebotaban. Nadie la estaba atendiendo.

**Corregido:** las tres acciones de atención son exclusivas del veterinario en la matriz, y el servidor exige además que sea el veterinario asignado (RN-408). Prueba: `testSoloElVeterinarioAtiendeLasCitas`.

#### M4-03 — Citas completadas sin consulta · `FUN` · **Alta** · ✅ Corregido

Los botones «Atendida» y «Marcar como atendida» del calendario llamaban a `completar_cita_ajax`, que completaba la cita sin consulta: la cita quedaba atendida y la historia clínica, vacía. HU-19 y RN-406 lo permitían por escrito («se vincula *opcionalmente* la consulta»). En la base local había tres citas así.

**Corregido:** RN-406 queda reescrita: una cita solo se completa al guardar su consulta. Los botones salieron del calendario, y `completar_cita_ajax` solo cierra una cita cuya consulta ya existe.

#### M4-04 — Finalizar la atención eran dos operaciones sueltas · `FUN` · **Alta** · ✅ Corregido

`atencion.js` guardaba la consulta y después, en otra petición, completaba la cita. Si fallaba la segunda, la consulta quedaba guardada y la cita seguía en curso.

**Corregido:** `ConsultaController::registrarAjax` completa la cita dentro de la misma transacción de HU-34. Exige que la cita sea del veterinario en sesión y que esté en curso; si al escribir ya no lo está, se deshace todo.

#### M4-05 — Un propietario podía agendar citas para mascotas ajenas · `SEG` · **Alta** · ✅ Corregido

`portal_agendar_cita_ajax` llega a `CitaController::registrarAjax`, que no comprobaba de quién era la mascota. El portal solo ofrece las propias, pero con un `id_mascota` cualquiera se agendaba una cita a nombre de otro dueño. Violaba RN-G02.

**Corregido:** con rol propietario, la mascota tiene que ser suya.

#### M4-06 — El veterinario no podía reprogramar sus citas · `FUN` · **Alta** · ✅ Corregido

`reprogramarCitaAjax` solo aceptaba al administrador (el comentario hablaba de «arrastrar y soltar»). Pero el calendario del veterinario ofrece «Reprogramar» y usa el mismo endpoint, así que respondía siempre 403. Incumplía HU-14.

**Corregido:** reprograma el administrador o el veterinario asignado.

#### M4-07 — El propietario nunca podía cancelar desde su portal · `FUN` · **Alta** · ✅ Corregido

El portal comparaba con el estado `programada` en tres sitios, y ese estado no existe en la base (VD-AGN-09). Ninguna cita salía como activa, y el botón «Cancelar» de la agenda del propietario, que ya tenía su manejador y su endpoint, no aparecía nunca.

**Corregido:** se usan los estados reales. Con esto el propietario ya cancela desde el portal, que es el primer criterio de HU-50. Reprogramar desde el portal sigue pendiente, así que HU-50 pasa a Parcial.

### Medios

| ID | Hallazgo | Estado | Resolución |
|---|---|---|---|
| M4-08 | La atención se podía iniciar cualquier día: el servidor no miraba la fecha y el calendario solo bloqueaba los días pasados. Una cita de la semana siguiente quedaba en curso días antes. | ✅ | Solo el día de la cita, en el servidor y en la interfaz. |
| M4-09 | No existía el estado «no asistió» (VD-AGN-07): una cita a la que nadie vino quedaba pendiente o confirmada ocupando su espacio. | ◐ Parcial | Estado `no_asistio` (migración 09), botón «No asistió» y liberación del espacio en toda la lógica de disponibilidad. Falta el reporte de ausentismo (HU-29). |
| M4-10 | Confirmar no miraba el estado: confirmar una cita cancelada la reactivaba sin revisar si su horario seguía libre. | ✅ | Solo se confirma una cita pendiente. |
| M4-11 | Cancelar y reprogramar no miraban el estado ni la fecha: se podía cancelar una cita en curso o completada, o mover una a un momento pasado. Reprogramar tampoco revisaba los solapamientos de la mascota, que agendar sí revisaba. | ✅ | Solo citas pendientes o confirmadas, a un momento futuro y con las dos comprobaciones de solapamiento. |
| M4-12 | El modal de reprogramar enviaba el veterinario elegido, pero el servidor lo ignoraba en silencio: no había forma de reasignar una cita. | ✅ | El administrador la reasigna, validando que el veterinario exista y esté activo. Es lo que concilia RN-208 con RN-408. |
| M4-13 | Por la API se podía agendar en una fecha pasada (la hora solo se revisaba si la fecha era hoy) o con una fecha mal formada. | ✅ | Formato y fecha se validan en el servidor. |
| M4-14 | No se registraba la hora real de atención (VD-AGN-01). | ◐ Parcial | `hora_inicio_real` y `hora_fin_real` se sellan al iniciar y al completar. Faltan la detección de retrasos y el aviso de corrimiento (HU-27). |
| M4-15 | El calendario tenía dos paneles de detalle con reglas distintas. El segundo ofrecía «Confirmar» sobre citas ya confirmadas y «Marcar como atendida» sobre pendientes, y además leía variables fuera de su ámbito, así que fallaba al abrirse. | ✅ | Queda un solo detalle, con las mismas reglas que el servidor. |
| M4-21 | `DOC` — HU-19 y RN-406 figuraban como Implementada y Aplicada, pero describían el flujo defectuoso: «Como veterinario **o recepcionista**…» y consulta «opcional». | ✅ | Reescritas, con RE-19.1 a RE-19.5 y las reglas nuevas RN-408 y RN-409. |

### Bajos

| ID | Hallazgo | Estado | Resolución |
|---|---|---|---|
| M4-16 | Cuatro métodos de `CitaController` sin ruta: `actualizarAjax`, `generarBloquesCatchupAjax`, `getBloquesCatchupAjax` y `getEventsForCalendar`. Los tres primeros llamaban a métodos que no existen en el modelo. | ✅ | Eliminados (159 líneas). |
| M4-17 | `listarTiposCitaAjax` devolvía `$e->getMessage()` al cliente y escribía el catálogo completo en el log en cada llamada; `listarSemanaAjax` escribía en el log en cada carga del calendario. | ✅ | Mensaje genérico y sin logs de depuración. |
| M4-18 | El dashboard, la línea de tiempo y el panel de citas del administrador no conocían los estados `en_curso` ni `no_asistio`. | ✅ | Etiquetas, colores y filtro. |
| M4-19 | El marcado del segundo panel (`detalleCitaDrawer`) sigue en las vistas, ya sin uso. | ⚠️ Aceptado | Es marcado inerte; se retira con la limpieza de TR-01. |
| M4-20 | Las citas en curso de días anteriores no aparecen en el panel del veterinario, que solo muestra las de hoy: hay que buscarlas en el calendario. | ✅ | Resuelto en v1.9.1: el panel del veterinario lista sus atenciones abiertas y sin cerrar de cualquier día (`Panel::atencionesAbiertas`, HU-18). |
| M4-22 | `DOC` — RN-405, RN-406 y RE-19.1 hablaban del estado «programada», que no existe (VD-AGN-09). | ✅ | Se usan los estados reales. |
| M4-23 | `DOC` — RN-208 («cualquier veterinario atiende cualquier mascota») parecía contradecir la nueva RN-408. | ✅ | RN-208 aclara que la consulta de una cita la registra su veterinario; si la atiende otro, el administrador reasigna la cita (M4-12) o se usa la atención sin cita. |

## Lo que sigue abierto del análisis de vacíos

Ya estaba documentado y no se aborda en esta versión:

| Origen | Vacío | HU |
|---|---|---|
| VD-AGN-02 | Sin tiempo de amortiguación entre citas. | HU-28 · Pendiente |
| VD-AGN-03/04/05/06 | Las sugerencias usan un horario fijo de 08:00 a 18:00, la validación de horario no considera la duración de la cita y la verificación y la inserción no van en una transacción. | HU-31 · Parcial |
| VD-AGN-08 | Sin bloqueos del veterinario ni festivos. | HU-30 · Pendiente |

## Datos existentes

Las correcciones no tocan los registros ya guardados. En la base local quedan tres citas en curso de junio (#13, #15 y #19), que su veterinario puede retomar y cerrar con la consulta, y tres citas completadas sin consulta, que se dejan como están: inventarles una consulta sería peor que el hueco.

## Resumen del Módulo 4

| Severidad | Hallazgos | Corregidos | Parciales | Aceptados | Abiertos |
|---|---|---|---|---|---|
| Alta | 7 | 7 | 0 | 0 | 0 |
| Media | 9 | 7 | 2 | 0 | 0 |
| Baja | 7 | 6 | 0 | 1 | 0 |
| **Total** | **23** | **20** | **2** | **1** | **0** |

### Verificación

| Comprobación | Resultado |
|---|---|
| Suite de pruebas | **144 pruebas, 608 aserciones, en verde** (eran 137) |
| Cobertura RBAC router ↔ matriz | **111 acciones con rol, 111 en la matriz**, cero huérfanas en ambos sentidos |
| Migración 09 | Ejecutada dos veces seguidas sin error (idempotente) |
| Sintaxis PHP y JS de los archivos tocados | Sin errores |

Pruebas añadidas: [tests/Integration/CitaEstadoTest.php](../tests/Integration/CitaEstadoTest.php), reescrita con 9 casos (transiciones permitidas, sellos de hora, doble inicio, «no asistió» y liberación del espacio), y `testSoloElVeterinarioAtiendeLasCitas` en la prueba de autorización.

**Veredicto:** el módulo no tenía un flujo de estados definido, y eso explica casi todos los hallazgos. Cada pantalla decidía por su cuenta qué se podía hacer con una cita, y el servidor aceptaba cualquier transición. Los cuatro primeros altos son el mismo problema visto desde lugares distintos: una atención se podía empezar sin poder terminarla, y dar por terminada sin haberla hecho. Ahora el flujo está escrito, lo aplica el servidor y la documentación dice lo mismo que el código.

---

# Cierre de v1.11.0 — recordatorios y respaldos

Revisión previa al cambio de arquitectura. La agenda, las consultas y los catálogos quedan fuera: se rehacen en la arquitectura nueva y sus historias pendientes pasan a estado **diferida** (HU-20, HU-27 a HU-31, HU-44, HU-50, HU-51 y HU-53). El resto del Módulo 3 (registro de vacunas y desparasitaciones) no se revisó.

| ID | Hallazgo | Estado | Resolución |
|---|---|---|---|
| M3-01 | **Alta** — El respaldo diario no corría en producción: `backup.php` usaba `mysqldump`, que la imagen `php:8.2-apache` no trae, y no había Schedule ni volumen. HU-23 figuraba como implementada. | ✅ | Volcado con PDO (`models/Respaldo.php`) en una sola instantánea, volumen `respaldos` y Schedule en Dokploy. Verificado con una restauración completa en otra base. |
| M3-02 | Un recordatorio que fallaba no se reintentaba nunca: el registro con estado `error` contaba como ya enviado. | ✅ | Solo cuenta como enviado un registro `enviado`; hasta 3 intentos (RE-37.2). |
| M3-03 | Los recordatorios buscaban fechas exactas: un día sin tarea perdía los avisos de ese día. | ✅ | Ventana por aviso (RE-37.1, `VentanaRecordatorio`). |
| M3-04 | Las fechas salían de `CURDATE()`, que depende de la zona del servidor de base de datos. | ✅ | «Hoy» en la zona de la clínica (RE-37.3). |
| M3-05 | Se enviaban recordatorios de mascotas inactivas y de dosis ya renovadas. | ✅ | Excluidas en la consulta (RE-37.4). |

Además, `docker-compose.yml` tenía escrita la contraseña de root de MySQL; ahora sale de `DB_ROOT_PASS` en el `.env`.

Pruebas añadidas: [tests/Unit/VentanaRecordatorioTest.php](../tests/Unit/VentanaRecordatorioTest.php) y [tests/Integration/RecordatorioTest.php](../tests/Integration/RecordatorioTest.php). La suite queda en 212 pruebas y 813 aserciones.
