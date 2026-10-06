# Buscador del portal de documentación

> Estado: terminado (falta el despliegue)
> Versión prevista: v1.12.0 · Fecha: 2026-10-04

El portal de documentación (`public/docs/`) es una herramienta interna del equipo, no una funcionalidad del producto, por eso este cambio no lleva HU en `HistoriasUsuario.md`. Sus requisitos se numeran aquí como `BD-N` (buscador de documentación).

## 1. Especificación (qué y por qué)

**Problema:** al buscar `412` (o `RN-411`) la paleta no muestra nada aunque la regla existe en `ReglasNegocio.md`. Al revisar el índice de Algolia se encontraron tres causas:

1. **El índice está desactualizado.** `scripts/algolia_index.php` solo se ejecuta a mano y nadie lo corre después de editar los `.md`. RN-411 se agregó después del último indexado. La búsqueda de códigos funciona bien con los que sí están indexados: `401` encuentra RN-401 y `G02` encuentra RN-G02.
2. **Secciones recortadas.** Cada sección se corta a 7000 bytes para no pasar el límite de 10 KB por registro de Algolia. «3.2 Requisitos funcionales» del ERS (12,8 KB) y «Versión 1.9.0» del README pierden el final, que nunca aparece en la búsqueda.
3. **Sin contexto de página.** Los resultados salen en el orden de relevancia global, sin importar en qué documento está el usuario.

**Historia:** Como integrante del equipo, quiero que el buscador encuentre cualquier código o texto de la documentación vigente, empezando por el documento que estoy leyendo, para llegar a lo que busco sin recorrer los nueve documentos.

**Requisitos:**

| ID | Requisito | Criterio de aceptación |
|---|---|---|
| BD-1 | El índice de Algolia debe reflejar los `.md` desplegados sin intervención manual. | Dado un cambio en cualquier documento publicado, cuando se despliega, entonces el contenedor reindexa al arrancar. Si los documentos no cambiaron desde el último indexado, no reindexa ni gasta operaciones de Algolia. Si no hay credenciales o Algolia falla, el sitio arranca igual. |
| BD-2 | Si Algolia no devuelve resultados, se debe intentar con el buscador local. | Dado un índice desactualizado, cuando busco un texto que solo está en el `.md` actual, entonces lo encuentro con la búsqueda local y el pie de la paleta dice «Búsqueda local». |
| BD-3 | Ninguna parte de una sección debe quedar fuera del índice. | Dada una sección de más de 7000 bytes, cuando se indexa, entonces se parte en fragmentos (cortando entre líneas) y cada fragmento es un registro. La paleta muestra una sola tarjeta por sección, con el extracto del fragmento que coincide. |
| BD-4 | Un código (`RN-411`, `RE-15.10`, `HU-15`, `RF-4.7`, `RNF-16`, `RN-G02`) se debe encontrar por el código completo o solo por su número, y el lugar donde se define debe salir antes que los lugares que solo lo mencionan. | Dado que RN-411 se define en una fila de `ReglasNegocio.md` y se cita en el ERS y en `Modelos.md`, cuando busco `412` o `RN-411`, entonces el primer resultado es la sección de `ReglasNegocio.md` que lo define. `412` no devuelve `402` ni `413` por tolerancia a errores. |
| BD-5 | Los resultados del documento abierto deben salir primero, y luego los demás documentos en orden de relevancia. | Dado que estoy en «Reglas de negocio», cuando busco un término que aparece en varios documentos, entonces el primer grupo es «Reglas de negocio» (marcado como «Esta página») y luego vienen los demás. Aplica también a la búsqueda local. |

**Fuera de alcance:** saltar a la fila exacta de una tabla (el enlace lleva a la sección que la contiene); cambiar de proveedor de búsqueda; reindexar desde la máquina local de forma automática (sigue existiendo el comando manual).

## 2. Plan (cómo)

- **Archivos:**
  - `helpers/DocsIndex.php`: dos funciones nuevas, probables sin red ni base de datos.
    - `codigosDefinidos(string $cuerpoCrudo, string $encabezado)`: códigos que la sección **define** (los que abren una fila de tabla, `| RN-411 |`, o el propio encabezado, `### HU-15 — …`).
    - `fragmentar(string $cuerpoCrudo, int $maxBytes)`: parte el cuerpo crudo entre líneas en trozos que no pasan el tope. Una línea sola más larga que el tope se corta en el límite de un carácter UTF-8.
  - `scripts/algolia_index.php`:
    - Un registro por fragmento: `objectID` `doc--slug--N`, atributo `seccion` (`doc--slug`) para agrupar, y `codigos` con los códigos definidos en ese fragmento.
    - Ajustes del índice: `searchableAttributes` = `codigos`, `heading`, `docTitle`, `content`; `attributeForDistinct: seccion` con `distinct: 1` (una tarjeta por sección); `attributesForFaceting: filterOnly(docId)`; `allowTyposOnNumericTokens: false`.
    - Huella (SHA-256) de los documentos y de la versión del formato de registro, guardada en `userData` de los ajustes del índice. Opción `--si-cambio`: compara la huella y sale sin hacer nada si coincide. En ese modo, la falta de credenciales no es un error (sale con 0 y un aviso).
  - `docker/iniciar.sh`: después de las migraciones, `php scripts/algolia_index.php --si-cambio`. Si falla, se registra en el log y Apache arranca igual (BD-1).
  - `public/docs/docs.js`:
    - La consulta envía `optionalFilters: docId:<documento actual>` para que Algolia suba los resultados del documento abierto aunque no estén entre los 20 más relevantes del total.
    - Al agrupar, el grupo del documento actual va primero, con hasta 8 tarjetas y la marca «Esta página»; los demás conservan el orden de relevancia, con hasta 5 tarjetas (BD-5).
    - Con 0 resultados de Algolia, se usa `searchLocally()` (BD-2).
    - La búsqueda local también ordena el documento actual primero y enlaza a la sección donde está la coincidencia, no al inicio del documento.
  - `public/docs/docs.css`: estilo de la marca «Esta página».
  - `public/docs/index.php`: subir la versión del `?v=` de `docs.js` y `docs.css` para invalidar la caché.
  - `README.md` y `config/App.php`: versión 1.12.0 (cambia el arranque del contenedor y un script del backend).
- **Datos:** ninguna migración. El índice de Algolia se reconstruye completo la primera vez (cambia el formato de los registros).
- **Rutas y permisos:** ninguna; el portal de documentación no pasa por `public/index.php`.
- **Pantallas:** la paleta conserva su diseño en móvil, tablet y escritorio; solo se agrega la marca del grupo actual.
- **Riesgos:**
  - Que el plan gratuito de Algolia no admita `optionalFilters`. Se comprueba contra el índice real antes de cerrar. Si no lo admite, se quita del pedido y BD-5 queda cubierto solo por el reordenamiento en el navegador (el documento actual sigue saliendo primero, pero solo entre los 20 resultados más relevantes).
  - El índice queda vacío unos segundos mientras se reindexa en el arranque. Mientras tanto, BD-2 cubre la búsqueda con el buscador local.
  - Cuota de Algolia: la huella evita reindexar en cada despliegue que no toca la documentación.

## 3. Tareas

- [x] `DocsIndex::codigosDefinidos()` y `DocsIndex::fragmentar()`
- [x] Pruebas: `tests/Unit/DocsIndexTest.php` cubre BD-3 y BD-4 (fragmentación sin pérdida y con tope, códigos definidos frente a mencionados)
- [x] Indexador: fragmentos, códigos, ajustes nuevos, huella y `--si-cambio` (BD-1, BD-3, BD-4)
- [x] `docker/iniciar.sh` reindexa al arrancar (BD-1)
- [x] `docs.js`: documento actual primero, `optionalFilters`, respaldo local con 0 resultados, búsqueda local por sección (BD-2, BD-5)
- [x] Reindexar y verificar contra Algolia
- [x] Versión: `documentacion/HistorialVersiones.md` (el historial ya no está en el README) y `config/App.php`

## 4. Verificación

- **BD-1:** se reindexó con el script (308 registros) y una segunda corrida con `--si-cambio` respondió «La documentación no cambió». Sin credenciales, `--si-cambio` sale con 0. Falta verlo en el log del contenedor en el primer despliegue.
- **BD-2:** la búsqueda local se probó en Node con los `.md` reales: `atencion` encuentra «atención», cada tarjeta enlaza a su sección (`#reglas--modulo-4-agenda-de-citas-y-portal`) y el documento actual va primero.
- **BD-3:** `DocsIndexTest` (fragmentos que no pasan el tope, cortan entre líneas, no pierden texto ni rompen caracteres UTF-8, y no indexan bloques de código).
- **BD-4:** `DocsIndexTest` (códigos definidos frente a mencionados). En el índice real, `412` y `RN-411` dan primero «Módulo 4» de Reglas de negocio, y `HU-4.13` da primero su propia sección. `402` trae RN-402, no RN-411.
- **BD-5:** el plan de Algolia acepta `optionalFilters`. `triage` con el documento `reglas` abierto devuelve primero dos secciones de Reglas de negocio, y con `modelos`, dos de Modelos. Falta verlo en el navegador.
- Suite completa: 218 pruebas, 896 aserciones, en verde.
