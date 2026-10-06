# M0-T — Base SaaS, identidad y aislamiento

> Estado: aprobado por el usuario el 2026-10-06; etapa A por iniciar
> Entrega: v2.0 · Fecha: 2026-10-06
> Reparto propuesto: Codex lidera datos y seguridad (B, C); Claude lidera formularios y paneles (D, E); cada entrega la revisa el otro agente. El usuario puede cambiarlo antes de cada etapa.

## 1. Resultado y límites

**Resultado:** Zooki pasa a ser una plataforma SaaS sobre una base v2 limpia. Las personas tienen un `id_usuario` estable y roles por clínica; el servidor aísla cada operación según el contexto activo; se puede registrar y activar una clínica, aceptar la política de datos y aplicar el plan gratuito; existe un super-administrador. Este bloque deja la base para los dos grafos, pero no implementa sus motores.

**Situación v1 (v1.12.0):** `usuarios.documento` es la clave primaria y referencia de otras tablas; `usuarios.id_rol` es un solo papel; existe el rol recepcionista; las tablas no tienen `id_clinica`. Las migraciones llegan a `13_razas_portal.sql`. **Los datos de producción son solo pruebas del usuario**: no hay clínicas, personas ni mascotas reales que conservar (decisión del 2026-10-06).

**Destino v2:** [MER](../documentacion/MER.md), [reglas](../documentacion/ReglasNegocio.md), [requisitos](../documentacion/RequisitosEspecificos.md) y [plan de entregas](../documentacion/HistoriasUsuario.md#plan-de-entregas-de-la-v2). `database/drawdb_schema_v2.sql` sigue siendo la referencia para drawDB; el esquema ejecutable es el nuevo `database/01_schema.sql`.

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
| HU-0.2 | RE-0.2.1–4 | RN-002, RN-003, RN-005, RN-G11 | D | La verificación activa la clínica y asigna el plan gratuito; un enlace vencido no la activa. |
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
- [ ] A — Inventario de código por módulo y orden de adaptación.
- [ ] B — `01_schema.sql` v2, `02_semilla.sql`, retiro de 03–13, migrador sin línea base, `crear_superadmin.php`; instalación desde cero en MariaDB local y MySQL 8; migrador repetible; README y AGENTS actualizados en la sección de base de datos.
- [ ] C — Identidad, contexto, aislamiento y retiro del recepcionista; pruebas de dos clínicas.
- [ ] D — Sesión, consentimiento, registro de propietario y de clínica, activación; pruebas.
- [ ] E — Panel del super-administrador y límites del plan; pruebas (incluida la excepción de urgencia roja).
- [ ] `vendor/bin/phpunit` completo y cada RE de la tabla con evidencia.
- [ ] F — Respaldo, reinicio de la base de producción, despliegue, super-administrador y clínica demo.
- [ ] Actualizar HU/RE (estado y nota de RE-0.3.6), `config/App.php` e `HistorialVersiones.md` al cerrar; regenerar descargas si cambian documentos publicados.
- [ ] Entregar al usuario los comandos de commit por etapa para PowerShell; el usuario crea ramas, commits, push y tags.

**Resultado de pruebas:** pendiente hasta la implementación.
