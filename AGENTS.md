# AGENTS.md — Zooki

Instrucciones para cualquier agente de IA (Claude Code, Codex, Cursor, Copilot, Gemini…) que trabaje en este repositorio. Las personas que lo lean también tienen aquí el resumen de cómo se trabaja.

Zooki es un sistema de gestión veterinaria: PHP 8.2 sin framework (MVC propio), MySQL 8, JavaScript sin bundler. Todo el código, los comentarios y la documentación van en **español**.

## Flujo de trabajo: primero la especificación

En Zooki no se escribe código sin una especificación que diga qué debe cumplir. El orden es:

1. **Especificar.** Buscar la historia en [documentacion/HistoriasUsuario.md](documentacion/HistoriasUsuario.md) y sus requisitos en [documentacion/RequisitosEspecificos.md](documentacion/RequisitosEspecificos.md).
   - Si el cambio no está cubierto, se agrega primero el criterio a la HU y un requisito `RE-NN.M` con su criterio de aceptación, y se pide al usuario que lo apruebe.
   - Las reglas de negocio (`RN-…`) están en [documentacion/ReglasNegocio.md](documentacion/ReglasNegocio.md) y no se contradicen.
2. **Planificar.** Si el cambio toca más de un par de archivos, trae una migración o cambia un flujo, se crea `specs/HU-NN-nombre.md` a partir de [specs/_plantilla.md](specs/_plantilla.md). Ahí van la especificación, el plan (archivos, datos, riesgos) y las tareas. El usuario aprueba el plan antes de implementar.
3. **Implementar** siguiendo las tareas del plan y marcándolas al terminar. Si durante la implementación la especificación resulta equivocada, se corrige el documento, no solo el código.
4. **Verificar.** Cada `RE` nuevo debe quedar cubierto por una prueba (o, si es solo visual, por una verificación descrita en el plan). Luego se actualizan el README y la versión (ver «Versiones»).

Los cambios pequeños y evidentes (un error tipográfico, un bug de una línea) no necesitan archivo en `specs/`, pero sí citar la HU o el RE que corrigen.

## Comandos

```powershell
composer install                                   # dependencias (PHPMailer, PHPUnit)
vendor/bin/phpunit                                 # todas las pruebas
vendor/bin/phpunit tests/Unit/ValidadorMascotaTest.php   # una sola
php scripts/migrar.php --revisar                   # qué migraciones faltan en la base local
php scripts/migrar.php                             # aplicarlas
```

- El usuario trabaja en Windows con PowerShell 5.1: **no existe `&&`**, se encadena con `;`.
- Las pruebas de integración usan SQLite en memoria, no necesitan la base real.
- Local corre en XAMPP (MariaDB); producción en Docker con Dokploy (MySQL 8).

## Estructura

| Carpeta | Contenido |
|---|---|
| `public/index.php` | Front controller: un `switch` sobre `?action=`. Aquí se registran las rutas. |
| `helpers/Security.php` | Matriz de roles por acción, CSRF y contraseña temporal. **Toda acción nueva debe agregarse a la matriz**: lo que no está se deniega. |
| `controllers/` | Reciben la petición, validan y responden (vista o JSON en las acciones `*_ajax`). |
| `models/` | Acceso a datos con PDO y consultas preparadas. |
| `helpers/` | Lógica reutilizable y probable sin base de datos (`ValidadorMascota`, `HorarioAtencion`, `ReglaAtencion`…). |
| `views/` | Solo estructura HTML/PHP, por rol: `admin/`, `vet/`, `reception/`, `portal/`, `auth/`. |
| `public/css/`, `public/js/` | Estilos y scripts, un archivo o módulo por pantalla. |
| `database/` | `01_schema.sql` y migraciones `NN_nombre.sql`. |
| `tests/Unit`, `tests/Integration` | PHPUnit 10. |
| `documentacion/` | ERS, HU, RE, RN, MER. Es la fuente de verdad de lo que el sistema debe hacer. |

Roles: 1 administrador, 2 veterinario, 3 recepcionista, 4 propietario.

## Reglas de código

Son las del manifiesto `documentacion/ZOOKI_REGLAS.md` (que no se sube al repositorio), resumidas para que viajen con el código:

- **Separación de capas y SOLID:** cada clase o función hace una sola cosa; la lógica de datos no va en las vistas ni la de presentación en los modelos.
- **Nada de CSS ni JS en línea** en las vistas (`style=`, `onclick=`, `<script>` con código). Los datos que necesita el JS van en atributos `data-*`.
- **Nada de `alert()`/`confirm()`/`prompt()`**: se usa SweetAlert2.
- **Validar en el servidor siempre**, aunque el formulario ya valide en el navegador. Los límites viven en un helper (ej. `ValidadorMascota::NOMBRE_MAX`) y el HTML usa los mismos.
- Escapar toda salida con `htmlspecialchars`; consultas siempre preparadas; toda petición que modifica datos lleva token CSRF.
- Un propietario solo ve sus propias mascotas (RN-G02): comprobarlo en el servidor en cada acción del portal.
- No duplicar: si algo se usa dos veces, va a un helper o a `public/js/extras.js`.
- Comentar el **porqué** (una regla, un bug que se evitó), citando el `RE`/`RN` cuando aplica. No comentar lo que el código ya dice.
- Diseño responsive con los cortes de RNF-07: móvil < 768 px, tablet 768–1023 px, escritorio ≥ 1024 px.

## Base de datos y migraciones

- Una migración nueva es `database/NN_nombre.sql` con el número siguiente, y **debe poder ejecutarse dos veces sin error**. Se consulta `information_schema` antes de crear columnas o índices, y se revisa si la fila ya existe antes de insertarla. Ejemplo: [database/13_razas_portal.sql](database/13_razas_portal.sql).
- Empieza con `SET NAMES utf8mb4;` si inserta texto con tildes.
- Al desplegar se aplican solas (`docker/iniciar.sh` → `scripts/migrar.php`). Nunca se pide al usuario que entre al servidor a correrlas.
- No se modifican migraciones ya publicadas: se escribe una nueva.
- Si cambia el modelo, se actualiza [documentacion/MER.md](documentacion/MER.md).

## Versiones, commits y ramas

- **El usuario hace los commits, las ramas, los push y los tags.** El agente no ejecuta `git commit`, `git push`, `git tag` ni `git checkout` de otra rama: entrega los comandos listos para pegar en PowerShell.
- Mensajes con Conventional Commits en español, sin tildes, citando la HU/RE:
  `feat(portal): agendar con calendario y horarios en botones (HU-15, RE-15.10)`
  - Tipos usados: `feat`, `fix`, `docs`, `chore`, `refactor`, `test`.
  - Un commit por tema; la documentación de la especificación puede ir en su propio `docs:`.
- Versión `X.Y.Z` (ver README, «Esquema de Versionamiento»):
  - `Y` sube si cambia el backend o hay migración.
  - `Z` sube si el cambio es solo de frontend.
  - Se actualizan a la vez `config/App.php` (`VERSION`) y la sección nueva del README, que queda marcada «(Actual)».
- Cada versión va en la rama `release/vX.Y.Z` con PR a `main`. Después del merge se crea el tag anotado `vX.Y.Z` sobre el commit de merge (por su hash, sin hacer checkout de `main`).

## Lo que no se toca

- `.env`: tiene las credenciales y no se sube. Toda variable nueva se agrega también a `.env.example`, con un valor de ejemplo. Nunca se escribe una contraseña o clave en un archivo versionado.
- `documentacion/AnalisisVaciosDiseno.md`: describe vulnerabilidades sin corregir. No se sube ni se cita en archivos públicos.
- `vendor/`: lo maneja Composer.
- `public/uploads/`: son archivos de los usuarios.
- `scratch/`: pruebas sueltas, no forman parte del sistema.
