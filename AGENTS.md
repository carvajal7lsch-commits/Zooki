# AGENTS.md — Zooki

Instrucciones para cualquier agente de IA (Claude Code, Codex, Cursor, Copilot, Gemini…) que trabaje en este repositorio. Las personas que lo lean también tienen aquí el resumen de cómo se trabaja.

Zooki es un sistema de gestión veterinaria: PHP 8.2 sin framework (MVC propio), MySQL 8, JavaScript sin bundler. Todo el código, los comentarios y la documentación van en **español**.

## Empieza aquí

Antes de cualquier tarea, lee **[`agentes/estado.md`](agentes/estado.md)** (dónde vamos y qué sigue) y **[`agentes/metodo.md`](agentes/metodo.md)** (roles de arquitecto, ejecutor y revisor; ciclo de cada subetapa; plantilla de prompt; cómo revisar; preferencias del dueño del producto). Así ninguna sesión necesita que el usuario repita el contexto.

**Al terminar, actualiza `agentes/estado.md`**: el ejecutor anota la subetapa como «entregada, pendiente de revisión»; el revisor la pasa a «revisada» y deja escrito qué sigue y qué decisiones quedan pendientes. Toda decisión del usuario se anota en el plan del módulo.

## Estado del proyecto

- **Producción y `main` están en la v1.12.0**: una instalación para una sola clínica. La v2 se construye en ramas propias (la etapa activa está en [`agentes/estado.md`](agentes/estado.md)); el código de esas ramas ya usa el modelo v2.
- **La especificación ya es la v2**: una plataforma SaaS multi-inquilino con dos grafos (soporte a la decisión clínica y agenda inteligente), repartida entre v2.0 y v2.1. Está en `documentacion/` y es la fuente de verdad.
- **Se construye la v2 por módulos**, en el orden de dependencias del [plan de entregas](documentacion/HistoriasUsuario.md#plan-de-entregas-de-la-v2): primero la entrega v2.0 (lo imprescindible), luego la v2.1. Cada módulo agrupa sus HU y RE; el avance y las pruebas se cierran por etapas pequeñas dentro del módulo.
- Lo nuevo se escribe con el modelo v2 ([MER](documentacion/MER.md)). El MER y `database/modelo/drawdb_schema_v2.sql` describen el destino para drawDB; el esquema ejecutable es `database/01_schema.sql`. Como los datos de la v1 eran solo pruebas, el salto a v2 reinicia la base con un esquema v2 consolidado y datos semilla, sin migrar datos ([plan M0](specs/M0-T-base-saas-identidad.md)). Ninguna etapa se entrega con lecturas o permisos que mezclen a medias los identificadores v1 y v2.

## Mapa de la documentación

| Para saber… | Ver |
|---|---|
| Qué debe hacer una funcionalidad y cómo se acepta | [HistoriasUsuario.md](documentacion/HistoriasUsuario.md) y [RequisitosEspecificos.md](documentacion/RequisitosEspecificos.md) |
| Qué reglas no se pueden romper | [ReglasNegocio.md](documentacion/ReglasNegocio.md) |
| Tablas, columnas y relaciones | [MER.md](documentacion/MER.md) (esquema para drawDB en `database/modelo/drawdb_schema_v2.sql`; vive en una subcarpeta para que ni MySQL ni el migrador lo ejecuten) |
| Cómo fluye un proceso (pasos, ramas de error) | [Modelos.md](documentacion/Modelos.md) |
| Requisitos funcionales y no funcionales (RF, RNF), casos de uso | [ERS.md](documentacion/ERS.md) |
| Qué nivel de triage debe dar cada caso clínico | `documentacion/CasosTriage.md` (batería de pruebas del Grafo I y de la agenda) |
| Qué cambió en cada versión | [HistorialVersiones.md](documentacion/HistorialVersiones.md) |

Si un documento contradice a otro, se avisa al usuario antes de programar; no se elige uno en silencio.

## Identificadores

| Código | Formato | Ejemplo |
|---|---|---|
| Regla de negocio | `RN-<módulo><nn>` (transversales: `RN-G<nn>`) | `RN-416`, `RN-G15` |
| Historia de usuario | `HU-<módulo>.<n>` | `HU-4.13` |
| Requisito específico | `RE-<módulo>.<historia>.<n>` | `RE-4.11.2` |
| Requisito funcional / no funcional | `RF-<módulo>.<n>` (futuros: `RF-F.<n>`) / `RNF-<nn>` | `RF-2.12`, `RNF-11` |

Módulos: `0` plataforma · `T` acceso y seguridad · `1` mascotas · `2` historia clínica y Grafo I · `3` vacunación · `4` agenda y Grafo II · `5` portal del propietario · `6` dashboard · `7` configuración · `8` reputación y comunicaciones · `9` público.

Los identificadores anteriores (`HU-23`, `RE-15.10`…) solo sobreviven en el historial de versiones; su equivalencia está en los apéndices de cada documento. El código nuevo cita siempre los nuevos.

## Flujo de trabajo: primero la especificación

No se escribe código sin una especificación que diga qué debe cumplir.

1. **Especificar.** Buscar la HU, sus RE y las RN que cita.
   - Si el cambio no está cubierto, se agrega primero el criterio a la HU y un RE con su criterio de aceptación, y se pide al usuario que lo apruebe.
2. **Planificar por módulo.** Se crea un solo `specs/M<módulo>-nombre.md` (o `specs/M0-T-nombre.md` si la base cruza módulos) a partir de [specs/_plantilla_modulo.md](specs/_plantilla_modulo.md). Enumera las HU, sus RE y RN, dependencias, etapas, migraciones, archivos, permisos, riesgos, pruebas y responsable de cada entrega. Se agrega al avanzar, sin abrir un archivo nuevo por cada HU. El usuario aprueba el plan del módulo antes de implementar; un cambio material de alcance vuelve a su revisión.
3. **Implementar por etapas verificables.** Cada etapa cierra las HU o los RE que declara y migra completamente lo que toca (datos, modelo, controlador, vista y pruebas). Una migración preparatoria puede agregar y poblar columnas antes del cambio de código, pero no se libera una ruta que lea claves viejas y nuevas de forma inconsistente. Si la especificación resulta equivocada, se corrigen los documentos y el plan, no solo el código.
4. **Verificar** con la definición de terminado (abajo).

Un `specs/HU-...md` se reserva para un flujo excepcional que necesite más detalle y enlaza al plan de su módulo; [HU-4.15](specs/HU-4.15-ingreso-emergencia.md) es el primer caso. Los cambios pequeños y evidentes (un error tipográfico, un bug de una línea) no necesitan archivo nuevo en `specs/`, pero sí citar la HU o el RE que corrigen.

### Coordinación entre agentes

- `AGENTS.md` es la regla común; `CLAUDE.md` remite a este archivo. El plan del módulo en `specs/` es el contrato de trabajo y la lista de avances para Codex, Claude y el usuario.
- Cada etapa tiene **un agente que escribe** y otro que revisa el diff, las pruebas y la trazabilidad sin editar los mismos archivos a la vez. El revisor deja hallazgos con archivo, línea, RE/RN afectado y reproducción; el responsable corrige y vuelve a verificar.
- El trabajo paralelo solo se asigna a etapas sin dependencias compartidas y en checkouts separados. En un mismo checkout, los agentes trabajan por turnos. Los cambios de base e identidad se integran antes de empezar etapas que dependan de ellos.
- Los agentes entregan estado, decisiones pendientes y comandos de commit; el usuario hace las ramas, los commits, los push y los tags según las reglas de abajo.

## Reglas de la v2 que todo código nuevo cumple

1. **Aislamiento por clínica (RNF-11).** Toda consulta de negocio filtra por el `id_clinica` del contexto activo, y el filtro se aplica en un solo lugar (la capa de modelos y `helpers/Security.php`), no a mano en cada consulta. Las excepciones son explícitas: el propietario y la mascota son globales (se ligan por `propietario_clinica` y `mascota_clinica`), los catálogos taxonómicos y el Grafo I son globales, y toda clínica vinculada ve las alergias, alertas, vacunas y desparasitaciones de la mascota (RN-113). Cada excepción lleva su prueba.
2. **Identidad.** Las personas se relacionan por `usuarios.id_usuario`, nunca por documento ni correo, que son datos corregibles. Una persona tiene varios roles: personal por clínica (`usuario_clinica`), propietario (`propietario_clinica`) y super-administrador (`usuarios.es_super_admin`). Los permisos son los del **contexto activo** de la sesión (RN-G01).
3. **Las urgencias rojas nunca se bloquean** por límite del plan ni por mora (RN-420). El triage se calcula antes de verificar el límite.
4. **El Grafo I apoya, no decide** (RN-209). El triage toma el nivel más alto, nunca promedia (RN-416). Cualquier cambio en el cálculo debe seguir pasando los casos de `CasosTriage.md`.
5. **Nada clínico se borra.** Citas, consultas, alertas y reseñas cambian de estado o se desactivan; las cuentas se anonimizan (RN-G16) y conservan su `id_usuario`.
6. **Parámetros, no números mágicos.** Tolerancias, plazos, topes y días de aviso viven en la clínica (`clinicas`, `plantillas_comunicacion`) con su valor por defecto de la regla; el código los lee de ahí.
7. **Mensajes que no revelan datos.** El login y la recuperación responden igual exista o no la cuenta (RN-G15); el carnet público da un mensaje genérico si el token no sirve.

## Comandos

```powershell
composer install                                   # dependencias (PHPMailer, PHPUnit)
vendor/bin/phpunit                                 # todas las pruebas
vendor/bin/phpunit tests/Unit/ValidadorMascotaTest.php   # una sola
php scripts/migrar.php --revisar                   # qué migraciones faltan en la base local
php scripts/migrar.php                             # aplicarlas (y la semilla)
php scripts/crear_superadmin.php                   # crear el super-administrador por consola
php scripts/dev/datos_prueba.php --si              # datos de prueba locales: dos clínicas y una persona por contexto (se niega fuera de una base local)
node scripts/docs/exportar.mjs --revisar           # qué PDF/Word del portal están desactualizados
node scripts/docs/exportar.mjs                     # regenerarlos (formato de la plantilla; necesita PHP, Chrome y Pandoc, y Word para el índice)
```

- El usuario trabaja en Windows con PowerShell 5.1: **no existe `&&`**, se encadena con `;`.
- Las pruebas de integración usan SQLite en memoria, no necesitan la base real. La excepción es `tests/Integration/BaseV2MysqlTest.php`, que carga el esquema en MySQL o MariaDB y se salta sola si no existe la variable `ZOOKI_TEST_MYSQL_HOST` (opcionales: `_PORT`, `_USER`, `_PASS` y `_DB`; esa base se borra y se crea en cada prueba). En CI corre contra MySQL 8.
- Local corre en XAMPP (MariaDB); producción en Docker con Dokploy (MySQL 8).

## Estructura

| Carpeta | Contenido |
|---|---|
| `public/index.php` | Front controller: un `switch` sobre `?action=`. Aquí se registran las rutas. |
| `helpers/Security.php` | Matriz de roles por acción, CSRF y contraseña temporal. **Toda acción nueva se agrega a la matriz**: lo que no está se deniega. |
| `controllers/` | Reciben la petición, validan y responden (vista o JSON en las acciones `*_ajax`). |
| `models/` | Acceso a datos con PDO y consultas preparadas. |
| `helpers/` | Lógica reutilizable que se prueba sin base de datos (`ValidadorMascota`, `HorarioAtencion`, `ReglaAtencion`…). Los motores de los grafos van aquí. |
| `views/` | Solo estructura HTML/PHP, por rol: `admin/`, `vet/`, `portal/`, `auth/`. `reception/` desaparece en la v2. |
| `public/css/`, `public/js/` | Estilos y scripts, un archivo o módulo por pantalla. |
| `public/docs/` | Portal de documentación (`docs.js` lista los documentos publicados). |
| `database/` | `01_schema.sql` (esquema v2), `02_semilla.sql` (datos semilla), migraciones `NN_nombre.sql` desde la 03 y `modelo/` (diagrama de drawDB, no ejecutable). |
| `scripts/` | Tareas programadas (recordatorios, vigilante, respaldo), migrador, índice de Algolia y exportación de documentos. |
| `tests/Unit`, `tests/Integration` | PHPUnit 10. |
| `specs/` | Un plan por módulo a partir de `_plantilla_modulo.md`; planes `HU-...` solo para flujos excepcionales. |

## Reglas de código

Son las del manifiesto `documentacion/ZOOKI_REGLAS.md` (que no se sube al repositorio), resumidas para que viajen con el código:

- **Separación de capas y SOLID:** cada clase o función hace una sola cosa; la lógica de datos no va en las vistas ni la de presentación en los modelos.
- **Nada de CSS ni JS en línea** en las vistas (`style=`, `onclick=`, `<script>` con código). Los datos que necesita el JS van en atributos `data-*`.
- **Nada de `alert()`/`confirm()`/`prompt()`**: se usa SweetAlert2, caso por caso: aviso breve (toast) para acciones frecuentes o reversibles, confirmación para lo destructivo o irreversible, y nunca para errores de validación de un campo.
- **Validación en tiempo real** en los formularios: cada campo se valida mientras se escribe, con el mensaje junto al campo y la misma regla del helper del servidor (formato, longitud, caracteres y, si aplica, unicidad por AJAX). La pantalla se actualiza en cuanto responde el servidor, sin esperar a que se cierre un aviso.
- **Una sentencia por línea** y nombres claros.
- Al tocar una pantalla existente se conserva su diseño (versión anterior y `specs/referencias/`); no se rediseña sin que el usuario lo pida.
- **Validar en el servidor siempre**, aunque el formulario ya valide en el navegador. Los límites viven en un helper (ej. `ValidadorMascota::NOMBRE_MAX`) y el HTML usa los mismos.
- Escapar toda salida con `htmlspecialchars`; consultas siempre preparadas; toda petición que modifica datos lleva token CSRF.
- Un propietario solo ve sus propias mascotas (RN-G02): comprobarlo en el servidor en cada acción del portal.
- No duplicar: si algo se usa dos veces, va a un helper o a `public/js/extras.js`.
- Comentar el **porqué** (una regla, un bug que se evitó), citando el `RE`/`RN` cuando aplica. No comentar lo que el código ya dice.
- Diseño responsive con los cortes de RNF-16: móvil < 768 px, tablet 768–1023 px, escritorio ≥ 1024 px.

## Base de datos y migraciones

- La base v2 nace de `database/01_schema.sql` (las 50 tablas, sin datos) y `database/02_semilla.sql` (roles, planes y catálogos globales). La v2 **no migra datos** de la v1, que eran pruebas: las migraciones v1 (03–13) se retiraron y el migrador se niega a correr sobre una base v1. Detalle en el [plan M0](specs/M0-T-base-saas-identidad.md).
- Una migración nueva es `database/NN_nombre.sql` con el número siguiente (la primera es la `03`), y **debe poder ejecutarse dos veces sin error**: en una instalación nueva la corre MySQL al crear el volumen y luego el migrador. Se consulta `information_schema` antes de crear columnas o índices, y se revisa si la fila ya existe antes de insertarla.
- `02_semilla.sql` se aplica en cada arranque: **solo inserta lo que falta y nunca sobrescribe** (`INSERT IGNORE` con id explícito e índice único; nada de `ON DUPLICATE KEY UPDATE` ni `REPLACE`). Una fila nueva de un catálogo global se agrega ahí con el siguiente id libre, sin cambiar ni reutilizar los existentes.
- Solo los `.sql` del primer nivel de `database/` se ejecutan; lo que no debe correr nunca (como el modelo de drawDB) va en una subcarpeta.
- Empieza con `SET NAMES utf8mb4;` si inserta texto con tildes. Las tablas usan InnoDB y `utf8mb4_general_ci`, que existe en MySQL 8 y en MariaDB 10.4.
- Al desplegar se aplican solas (`docker/iniciar.sh` → `scripts/migrar.php`). Nunca se pide al usuario que entre al servidor a correrlas.
- **No se modifican migraciones ya publicadas**: se escribe una nueva. `01_schema.sql` y `02_semilla.sql` solo cambian junto con una migración que lleve el mismo cambio a las bases existentes.
- Las tablas y columnas nuevas salen del [MER](documentacion/MER.md). Si hace falta una que no está, primero se agrega al MER y a `database/modelo/drawdb_schema_v2.sql` y se avisa al usuario.

## Mantener la documentación coherente

- Si se agrega o cambia una RN, HU o RE, se actualizan también sus referencias: la matriz RN → HU del apéndice de las historias, la matriz RN → HU → RE de los requisitos y los conteos del encabezado de cada documento y de la Ficha Técnica.
- Los requisitos de una historia van numerados sin saltos (`RE-4.11.1`, `RE-4.11.2`…).
- Los enlaces entre documentos solo apuntan a los publicados en el portal (los de `DOC_POR_ARCHIVO` en `public/docs/docs.js`). `VisionAlcance.md`, `CasosTriage.md` y `ROADMAP_v2.md` no se enlazan.
- Los diagramas de `Modelos.md` y del MER están en Mermaid y deben renderizar; el archivo draw.io (`public/docs/diagramas/`) lo resincroniza el usuario.
- Después de cambiar documentos se regeneran los PDF y Word del portal (`node scripts/docs/exportar.mjs`).

## Definición de terminado

Un cambio está terminado cuando:

- [ ] Cada RE nuevo o tocado tiene una prueba (o, si es solo visual, una verificación descrita en el plan), y `vendor/bin/phpunit` pasa completo.
- [ ] Toda acción nueva está en la matriz de `helpers/Security.php` y filtra por `id_clinica`.
- [ ] La migración, si hay, se puede correr dos veces y el MER refleja el cambio.
- [ ] Las HU, los RE y el plan del módulo en `specs/` reflejan lo que se construyó; cada RE de la etapa tiene evidencia de verificación.
- [ ] Se actualizaron la versión en `config/App.php` y su entrada en `documentacion/HistorialVersiones.md`.
- [ ] Se entregan al usuario los comandos de commit (ver abajo).

## Cuando la especificación no alcanza

- Si falta un dato para decidir (un límite, un mensaje, un caso de borde), se pregunta al usuario con una propuesta concreta; no se inventa un requisito.
- Si se descubre un hueco de seguridad o de datos, se reporta aunque no sea parte de la tarea.
- Si una tarea es más grande de lo que el plan decía, se para y se replantea el plan con el usuario.

## Versiones, commits y ramas

- **El usuario hace los commits, las ramas, los push y los tags.** El agente no ejecuta `git commit`, `git push`, `git tag` ni `git checkout` de otra rama: entrega los comandos listos para pegar en PowerShell.
- Mensajes con Conventional Commits en español, sin tildes, citando la HU/RE:
  `feat(agenda): triage de 4 niveles al agendar (HU-4.13, RE-4.13.2)`
  - Tipos usados: `feat`, `fix`, `docs`, `chore`, `refactor`, `test`.
  - Un commit por tema; la documentación de la especificación puede ir en su propio `docs:`.
- Versión `X.Y.Z` (ver «Esquema de versionamiento» en [HistorialVersiones.md](documentacion/HistorialVersiones.md)):
  - `Y` sube si cambia el backend o hay migración.
  - `Z` sube si el cambio es solo de frontend.
  - Se actualizan a la vez `config/App.php` (`VERSION`) y la entrada nueva en `documentacion/HistorialVersiones.md`, marcada «(Actual)».
- Cada versión va en la rama `release/vX.Y.Z` con PR a `main`. Después del merge se crea el tag anotado `vX.Y.Z` sobre el commit de merge (por su hash, sin hacer checkout de `main`).

## Lo que no se toca

- `.env`: tiene las credenciales y no se sube. Toda variable nueva se agrega también a `.env.example`, con un valor de ejemplo. Nunca se escribe una contraseña o clave en un archivo versionado.
- `documentacion/AnalisisVaciosDiseno.md`: describe vulnerabilidades sin corregir. No se sube ni se cita en archivos públicos.
- `database/NN_*.sql` ya publicadas: se agrega una migración nueva en su lugar.
- `vendor/`: lo maneja Composer.
- `public/uploads/`: son archivos de los usuarios.
- `scratch/` y `Claude outputs/`: material de trabajo, no forman parte del sistema.
