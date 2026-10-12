# M0-T — Base SaaS, identidad y aislamiento

> Estado: en curso — A a E cerradas; F0 (preparación del corte, versión 2.0.0-alpha.1) entregada, pendiente de revisión (2026-10-11); falta F1 (el corte en producción, con la guía `specs/M0-F-corte-produccion.md`).
> Entrega: v2.0 · Fecha: 2026-10-06
> C8/C9 (2026-10-08): escribe **Codex**, revisa **Claude** (sesión de revisión).
> Reparto del resto de M0 (2026-10-06): **Claude Code** en el equipo del usuario escribe cada etapa; **Claude** (sesión de revisión, sin editar los mismos archivos) revisa el diff, las pruebas y la trazabilidad. Codex queda disponible como revisor alterno. El usuario puede cambiarlo antes de cada etapa.

## 1. Resultado y límites

**Resultado:** Zooki pasa a ser una plataforma SaaS sobre una base v2 limpia. Las personas tienen un `id_usuario` estable y roles por clínica; el servidor aísla cada operación según el contexto activo; se puede registrar y activar una clínica, aceptar la política de datos y aplicar el plan gratuito; existe un super-administrador. Este bloque deja la base para los dos grafos, pero no implementa sus motores.

**Situación v1 (v1.12.0):** `usuarios.documento` es la clave primaria y referencia de otras tablas; `usuarios.id_rol` es un solo papel; existe el rol recepcionista; las tablas no tienen `id_clinica`. Las migraciones llegan a `13_razas_portal.sql`. **Los datos de producción son solo pruebas del usuario**: no hay clínicas, personas ni mascotas reales que conservar (decisión del 2026-10-06).

**Destino v2:** [MER](../documentacion/MER.md), [reglas](../documentacion/ReglasNegocio.md), [requisitos](../documentacion/RequisitosEspecificos.md) y [plan de entregas](../documentacion/HistoriasUsuario.md#plan-de-entregas-de-la-v2). `database/modelo/drawdb_schema_v2.sql` sigue siendo la referencia para drawDB; el esquema ejecutable es el nuevo `database/01_schema.sql`.

**Enfoque:** como no hay datos reales, **no se migran datos v1**. Se reinicia la base con un esquema v2 consolidado y datos semilla, y el código se pasa entero al modelo v2. Esto reemplaza el plan anterior de migración conservadora (relleno de `id_usuario`/`id_clinica`, verificación de huérfanos y línea base de la clínica legada).

**Fuera de alcance:** cálculo y carga clínica del Grafo I, agenda del Grafo II, urgencias de HU-4.15 ([plan propio](HU-4.15-ingreso-emergencia.md)), reseñas, comunicaciones y cobro real. Las tablas de esos módulos sí se crean en el esquema v2 (B) para no encadenar migraciones, pero su lógica llega con su módulo.

## 2. Trazabilidad

| HU de v2.0 | RE incluidos | RN/RNF principales | Etapa | Evidencia de aceptación (pruebas automáticas; suite completa en verde en F0) |
|---|---|---|---|---|
| HU-T.15 | RE-T.15.1–5 | RN-G13, RN-G14, RN-001, RN-004, RNF-11 | C | **.1** cada módulo filtra por la clínica activa: `SeguridadClinicaTest`, `MascotaAccesoTest`, `CitaTest::testUnaCitaDeANoSeVeNiSeModificaDesdeB`, `AgendaPeticionesTest`, `ConfiguracionClinicaTest`, `RecordatorioTest`, `NotificacionAccesoTest` (anexos C1–C9). **.2** 403 y auditoría: `SeguridadClinicaTest::testUnaPeticionAOtraClinicaDa403YQuedaEnAuditoria`. **.3** rol, CSRF y clínica en cada petición: `SeguridadClinicaTest`, `AutorizacionRolTest`. **.4** super-administrador sin datos clínicos: `SeguridadClinicaTest::testElSuperAdministradorNoEntraADatosClinicos`, `PlataformaTest`. **.5** dos clínicas en SQLite y en MySQL: `DosClinicas`, `BaseV2MysqlTest::testElFixtureDeDosClinicasCargaEnElEsquemaReal`. |
| HU-T.16 | RE-T.16.1–4 | RN-G17, RN-G05, RNF-13, RNF-14 | D1 | **.1–.4** `SesionTest` (30 minutos, 401 en AJAX, cookie HttpOnly/SameSite/Secure, cierre auditado) y `D1Sesion.test.cjs` (el 401 lleva al login). Cierre de las demás sesiones al cambiar la contraseña (RE-T.2.5): `D22CorreccionTest`. |
| HU-T.17 | RE-T.17.1–3 y 17.5 | RN-G01, RN-G06, RN-G13, RN-G18 | C | **.1** `ContextoTest::testUnaPersonaTieneSusRolesComoContextosSeparados`, `D22CorreccionTest::testAceptarCreaElRolYConservaLosDemas`. **.2** `ContextoTest`, `SeguridadClinicaTest::testSinContextoSeLlevaAlSelector`. **.3** `SeguridadClinicaTest::testDosContextosNoMezclanPermisos`, `testUnContextoRetiradoSeQuitaDeLaSesion`. **.5** `ContextoTest::testElSuperAdministradorSoloTieneLaPlataforma`, `UsuarioSeguridadTest::testUnSuperAdministradorNoRecibeRolesDeClinica`. **RE-T.17.4** (no calificarse a sí mismo) se verifica en v2.1 con HU-8.1. |
| HU-T.19 | RE-T.19.1–3 | RN-G19, RN-G20 | D1–D2.2 | **.1** `PropietarioClinicaTest::testAltaSinAceptacionOPresenciaNoCreaCuentaNiVinculoNiToken`, `CuentasD2Test` (personal pendiente e inerte 72 horas), `RegistroClinicaTest::testSinConsentimientoNoSeCreaNada`, `D22CorreccionTest::testElTitularDeUnaCuentaNuevaTambienPuedeRechazar`. **.2** prueba de versión, medio, fecha e IP, y super-administrador exento: `PoliticaDatosTest`, `PropietarioClinicaTest::testAltaConsentidaGuardaPruebaYPasswordResetSinContrasena`. **.3** nueva versión antes de seguir: `PoliticaDatosTest`, `AutorizacionRolTest`. **RE-T.19.4** (revocar) va con HU-5.14 en v2.1. |
| HU-5.8 | RE-5.8.1–6 | RN-109, RN-G06, RN-G11 | D1, D3 | **.1–.5** `RegistroPropietarioTest` (8 casos: identidad global, vínculo confirmado por correo, sin duplicar ni exponer la cuenta), `RegistroGoogleTest`, `RegistroClinicaTest` (respuesta idéntica). **.6** CAPTCHA del propietario: `RegistroClinicaTest`, `D3Avisos.test.cjs`. **RE-5.8.4**: el correo lleva el enlace del portal; el QR llega con el carnet QR del módulo 5 (decisión del usuario, 2026-10-11). |
| HU-0.1 | RE-0.1.1–10 | RN-001, RN-002, RN-010, RN-011, RN-G19, RN-G20 | D3, D3.1, E1 | **.1, .10** `RegistroClinicaTest::testSinConsentimientoNoSeCreaNada`, `testD31VistaRegistroComparteCabeceraYEstructuraDelLogin`, `D3Avisos.test.cjs`. **.2, .7, .8** `RegistroClinicaTest` (cuenta nueva inerte, existente sin cambios, respuesta idéntica, NIT duplicado con soporte). **.3, .5** `ValidadorClinicaTest`, `PlataformaValidacionTest::testNitYDvSeValidanSeparadosSinAdivinarUltimoDigito`, `D31Entrada.test.cjs`. **.4** `RegistroClinicaTest` (CAPTCHA ausente, inválido y válido). **.6** `RegistroClinicaTest::testLimiteTresPorDiaEIpNoSeEvadeConSesionNuevaNiConXForwardedFor`, `BaseV2MysqlTest`. **.9** auditoría en `RegistroClinicaTest`. |
| HU-0.2 | RE-0.2.1–5 | RN-002, RN-003, RN-005, RN-G11 | D3 | **.1–.4** `RegistroClinicaTest` (GET sin activar, POST sin CSRF rechazado, token alterado, vencido o usado, plan Gratuito, rol y contextos). **.5** copia inicial de catálogos sin duplicar y con rollback: `ConfiguracionClinicaTest::testInicializadorNoDuplicaNiSobrescribeCatalogosPropios`, `testCopiaInicialFallaSinDejarDatosParcialesYRespetaTransaccionExterior`, `BaseV2MysqlTest::testConfiguracionDeDosClinicasEnElEsquemaReal`. |
| HU-0.3 | RE-0.3.1–5 | RN-004, RN-012, RN-G13, RN-G14 | E1, E2 | **.1, .4** `PlataformaTest::testListaClinicasActivaPendienteSuspendidaYFiltrosSinDatosClinicos`, `testRolesYPostCsrfEnTodasLasAccionesDelPanel`, `E1Plataforma.test.cjs`. **.2** `PlataformaTest::testPlanSuscripcionMotivoYAuditoriaAtomica`. **.3** `AutorizacionRolTest::testElSuperAdministradorSoloTieneLaPlataforma`, `PlataformaTest::testVistaEscapaDatosYNoOfreceEnlacesClinicos`. **.5** `PlataformaTest::testSuspenderRetiraContextoYReactivarPermiteElegirloSinBorrarDatos`. **RE-0.3.6 (moderar reseñas) se verifica en v2.1 junto con HU-8.1**, porque en v2.0 aún no existen reseñas. |
| HU-0.4 | RE-0.4.1–7 | RN-003, RN-005, RN-006, RN-420 | E2 | **.1, .2, .4, .7** `LimitePlanTest` (bordes 2/50/100, pendientes sin duplicar, reactivación con cupo, cambio de mes, Profesional sin límite), `BaseV2MysqlTest::testE2CuotasYBloqueoDeClinicaEnElEsquemaReal`. **.3, .5** `LimitePlanTest`, `PortalAgendaTest` (mensaje neutral y aviso a la clínica). **.6** `LimitePlanTest::testProfesionalSinLimiteMoraYUrgenciaRojaConservanElUso`: una cita roja evaluada pasa el límite; la clasificación por triage llega con los módulos 2 y 4. |

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
- [x] C — C1–C9 implementadas y verificadas: identidad, contexto, aislamiento, retiro del rol eliminado y del flujo de cierre retirado, cierre de los puntos de fuga de A.6; pruebas de dos clínicas. HU-T.15 cerrada, con RE-T.15.1 en todos los módulos existentes y las excepciones explícitas del sistema y los datos globales (Anexo C9). La revisión de C8/C9 y la prueba visual del usuario quedan pendientes.
  - [x] C1 — Identidad, contexto activo, autorización en `Security` y retiro del recepcionista ([Anexo C1](#anexo-c1--resultado)). Revisada en C1.6; corrección obligatoria aplicada en C1.7, pendiente de revisión por Claude.
  - [x] C2 — Configuración por clínica: horarios, lectura de catálogos y copia inicial D-1 ([Anexo C2](#anexo-c2--resultado)). Implementada y verificada; pendiente de revisión por Claude.
  - [x] C3 — Mascotas y propietarios del personal ([Anexo C3](#anexo-c3--resultado)). Implementada y verificada; Codex escribe y Claude revisa. El usuario aprobó adelantar de D únicamente la confirmación por correo de una vinculación de propietario existente (RN-109). La aceptación del alta nueva sigue siendo presencial, directa del titular (RE-T.19.1).
  - [x] C4 — Historia clínica y prevención ([Anexo C4](#anexo-c4--resultado)). Implementada, verificada y revisada (C4 — Revisión).
  - [x] C5 — Agenda v1 sobre el modelo v2 y retiro de «cerrar sin consulta» ([Anexo C5](#anexo-c5--resultado)). Implementada, verificada y revisada (C5 — Revisión).
  - [x] C6 — Portal del propietario sobre el modelo v2, HU-5.12, HU-5.13 con RN-115 y arrastre en la agenda ([Anexo C6](#anexo-c6--resultado)). Implementada, verificada y revisada por Claude (C6 — Revisión).
  - [x] C7 — Paneles de inicio y pendientes del día por clínica activa; retiro de las estadísticas, gráficas y línea de tiempo del recepcionista ([Anexo C7](#anexo-c7--resultado)). Implementada, verificada y revisada por Claude (C7 — Revisión).
  - [x] C8 — Recordatorios por correo con clínica original, vínculos activos, ventana y reintentos ([Anexo C8](#anexo-c8--resultado)). Lo escribió Codex; implementada y verificada, pendiente de revisión por Claude.
  - [x] C9 — Limpieza de C, tarjetas por defecto con preferencia local, eventos y estilos externos, toasts por acción y auditoría en el menú ([Anexo C9](#anexo-c9--resultado)). Lo escribió Codex; pendiente de revisión por Claude y de prueba visual del usuario.
  - [x] C9.1 — Correcciones del recorrido visual: usuarios con el diseño de v1.12.0, restablecer oculto en identidades compartidas, recarga sin esperar al aviso, teléfono del portal validado al escribir, imprimir historial en el detalle de la mascota y pruebas de JS en el CI ([Anexo C9.1](#anexo-c91--resultado)). Lo escribió Claude Code; pendiente de revisión por Claude y de prueba visual del usuario.
- [x] D — Sesión, consentimiento, registro de propietario y de clínica, activación con copia de los catálogos iniciales (D-1, RE-0.2.5); pruebas.
  - [x] D1 — Sesión (HU-T.16), política de datos versionada y re-aceptación (HU-T.19), registro del propietario como identidad global (HU-5.8), registro con Google con la política antes de la cuenta (RE-T.18.2–4), hueco de RN-109 y teléfonos unificados ([Anexo D1](#anexo-d1--resultado)). Lo escribió Claude Code; revisada y aprobada por Claude, con prueba manual del usuario (2026-10-08).
  - [x] D2 — Cuentas del personal y del titular: alta con enlace de activación, cambio de correo y documento con verificación, CAPTCHA por cuenta y validación reutilizable en tiempo real ([Anexo D2](#anexo-d2--resultado)). Entregada, pendiente de revisión por Claude.
  - [x] D2.1 — Corrección de D2: IP real detrás del proxy, límites de RN-G15 (20 por IP, CAPTCHA por cuenta), comprobaciones por persona, validación en cada tecla, correos propios, modal del personal de v1.12.0, limpieza tolerante y prueba local ([Anexo D2.1](#anexo-d21--resultado)). Lo escribió Claude Code; revisada y aprobada por Claude (2026-10-09).
  - [x] D2.2 — Invitaciones del personal con aceptación, cierre de otras sesiones y correcciones de D2.1 ([Anexo D2.2](#anexo-d22--resultado)). Escribieron Claude Code y Codex; entregada, pendiente de revisión por Claude (2026-10-09).
  - [x] D3 — Registro y activación de clínicas (HU-0.1, HU-0.2), Turnstile del propietario (RE-5.8.6) y pendientes de D2.2 ([Anexo D3](#anexo-d3--resultado)). Lo escribió Codex; revisada y aprobada por Claude, con commit `fa43c3d`. La prueba del usuario motivó D3.1; el cierre de D espera su revisión y prueba visual.
  - [x] D3.1 — Corrección de pantalla de registro, restricciones de entrada y explicación de la contraseña de una cuenta existente ([Anexo D3.1](#anexo-d31--resultado)). Lo escribió Codex; entregada, pendiente de revisión (2026-10-10).
- [x] E — Panel del super-administrador y límites del plan; cerrada con E2 (2026-10-11). La clasificación clínica de triage se integra en los módulos 2/4.
  - [x] E1 — Panel de plataforma (HU-0.3 sin RE-0.3.6), soporte, IP bloqueadas y NIT/DV separados ([Anexo E1](#anexo-e1--resultado)). Lo escribió Codex; revisada y aprobada por Claude (2026-10-10); hallazgos del usuario corregidos en E2.
  - [x] E2 — Límites del plan (HU-0.4) y correcciones de E1 ([Anexo E2](#anexo-e2--resultado)). Lo escribió Codex; revisada y aprobada por Claude, y probada por el usuario (2026-10-11).
- [x] `vendor/bin/phpunit` completo y cada RE de la tabla con evidencia (F0: §2 con la evidencia por RE; suite completa en verde, también con MySQL).
- [x] F0 — Escrita por Claude Code; revisada y aprobada por Claude, 2026-10-11 ([Anexo F0](#anexo-f0--resultado)). Preparación del corte: blindaje de Apache, `.dockerignore`, IP real, invitación reemplazada, clínicas de prueba en Profesional, cierre documental en `2.0.0-alpha.1` y guía del corte ([F0 — Alcance](#f0--alcance-preparación-del-corte-2026-10-11)).
- [x] F — (F1 ejecutada por el usuario el 2026-10-11; bitácora al final del plan) Respaldo, reinicio de la base de producción, despliegue, super-administrador y clínica demo. Incluye blindar Apache para que solo sirva `public/` y agregar `.dockerignore` (B.5).
- [x] Actualizar HU/RE (estado y nota de RE-0.3.6), `config/App.php` e `HistorialVersiones.md` al cerrar; regenerar descargas si cambian documentos publicados (F0, versión `2.0.0-alpha.1`).
- [x] Entregar al usuario los comandos de commit por etapa para PowerShell; el usuario crea ramas, commits, push y tags.

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

## Anexo C6 — Resultado

Lo escribió Claude Code; la revisión corresponde a la sesión de revisión de Claude. Se releyeron A.2 (módulo 5), A.5, A.6, los anexos C1–C5 con sus revisiones (la regla de RN-115 de la revisión de C4 y las decisiones de la revisión de C5), las HU y RE del módulo 5, HU-5.12, HU-5.13, RN-109 a RN-115 y RN-G02.

**Decisiones del usuario (2026-10-07).**

1. Los cambios del propietario a la ficha de su mascota se auditan en `auditoria_sistema` (tabla `mascotas`, sin clínica), porque `auditoria_mascotas.id_clinica` es NOT NULL. No hubo cambio de esquema.
2. En el portal el propietario cambia solo su teléfono. El correo queda de solo lectura hasta que la etapa D agregue su verificación (RE-T.5.7).
3. El inicio muestra una tarjeta de horario por cada clínica vinculada; al agendar, los días cerrados son los de la clínica elegida. Se ajustaron HU-5.1 y RE-5.1.7 (sin subir revisión).
4. Desvincularse se rechaza con cualquier cita sin resolver en esa clínica: pendiente, confirmada, en curso o sin cerrar. HU-5.13/RE-5.13.3 ya fijaban el rechazo; la decisión precisa qué citas cuentan.

**Qué se hizo.**

- **Portal sin clínica activa.** `controllers/PortalController.php` (nuevo) atiende el contexto propietario; `PropietarioController` queda con lo del personal (alta presencial y vínculo, C3). Los modelos del propietario extienden `ModeloPropietario` (nuevo): el alcance es la persona y todo lo ajeno da 403 auditado sin clínica (RN-G02).
  - `MascotaPropietario`: sus mascotas (todas, RN-110), alta sin clínica (se vincula al agendar o al ser atendida, RE-5.13.2) y edición, incluidos especie, raza, sexo y nacimiento, con una fila de auditoría por campo cambiado.
  - `HistoriaPropietario`: consultas con adjuntos y tratamientos, vacunas, desparasitaciones y citas de todas las clínicas, con la clínica de cada registro (RN-114), aunque ya no esté vinculado a alguna.
  - `VinculosPropietario`: sus clínicas con su autorización, las disponibles, vincularse, desvincularse y autorizar o revocar la historia compartida. Cada cambio se audita con la clínica afectada.
- **Elegir una clínica en el portal.** `ModeloClinica::enClinicaDelPropietario()` valida en el modelo, no en el parámetro, que la clínica sea de un vínculo activo del propietario y esté activa (si no, 403 auditado). Devuelve una copia del modelo acotada a esa clínica, que pasa ese alcance a los modelos que usa por dentro (`CatalogoClinica`). Así agendar, tipos, veterinarios, horas y huecos usan las mismas validaciones de C5.
- **Agendar desde el portal** (`CitaController::agendarDesdePortalAjax`): mascota propia, veterinario de la clínica, horario de la clínica, aviso de otra cita ese día, solapamiento y doble reserva. Si la mascota no está vinculada a esa clínica, se vincula en la misma transacción (RN-110); si la reserva falla, no queda vinculada. Se auditan la cita y el vínculo. Prioridad verde y sin sobrecupo.
- **RN-115 (revisión de C4).** El estado del vínculo pasó a `ModeloClinica`:
  - nunca vinculada: 403;
  - vínculo inactivo: la clínica lee solo sus registros (consultas, tratamientos, archivos, vacunas y desparasitaciones) y no los de otras clínicas, aunque hubiera autorización;
  - todo registro nuevo exige vínculo activo (403 auditado).
  - La ficha de solo lectura muestra únicamente nombre, especie, nacimiento y número de historia, sin los datos nuevos del propietario (`Mascota::getParaHistorial`).
  - El listado de consultas de la clínica incluye las suyas de mascotas desvinculadas, marcadas con `vinculo`.
- **Interfaz del portal.**
  - Agendar empieza por la clínica (con una sola, ya elegida).
  - La historia, las citas y la impresión muestran la clínica de cada registro; solo se ofrece cancelar en clínicas con vínculo activo.
  - El perfil tiene «Mis clínicas»: interruptor de historia compartida, desvincularse y vincularse a otras.
  - El documento sale de la base, no de la sesión v1; el correo es de solo lectura.
  - Los eventos de la agenda pasan del `<script>` en línea a `data-*`. Los `onclick` heredados quedan para C9.
- **Arrastre en la agenda** (decisión de la revisión de C5). En `calendario.js` se arrastran solo las citas pendientes o confirmadas del propio veterinario, con el mismo endpoint y las mismas validaciones del modal; si el servidor rechaza, la cita vuelve a su lugar. Se quitó `esAdmin()`, que nunca aplicaba.

**RE y evidencia.**

| Alcance | Prueba |
|---|---|
| RE-5.1.2, RE-5.1.4, RN-G02: nada de otro propietario (403 auditado) | `PortalPropietarioTest::testElPropietarioNoVeNiTocaNadaDeOtroPropietario`, `testLaFichaDeOtraMascotaPorPeticionDirectaDa403`, `testLosModelosDelPortalSonSoloDelContextoPropietario` |
| RE-5.1.3, RE-5.9.2, RN-114: historia en todas las clínicas, con su clínica | `PortalPropietarioTest::testVeLaHistoriaDeSuMascotaEnTodasLasClinicasConSuClinica`, `testDesvinculadoDeUnaClinicaSigueViendoSuHistoria` |
| RE-5.4.1–2, RN-110, RE-5.1.9: edición con auditoría y validación | `PortalPropietarioTest::testEditaEspecieRazaSexoYNacimientoConAuditoria`, `testElControladorValidaLaRazaDeLaEspecie`, `testRegistrarMascotaLaDejaSinClinica` |
| Decisión 2: solo teléfono | `PortalPropietarioTest::testElPropietarioCambiaSuTelefonoPeroNoSuCorreo` |
| RE-5.3.1–3, RE-5.3.5, RE-5.9.3/4: agendar en sus clínicas, con la mascota vinculada al agendar | `PortalAgendaTest` (seis casos: clínica no vinculada aunque mande el id, vínculo automático, rollback del vínculo, catálogos de la clínica, mascota ajena y veterinario de otra clínica, horario y doble reserva) |
| RE-5.12.1/2/4: autorizar y revocar al instante, con auditoría | `VinculosPropietarioTest::testAutorizarYRevocarCambianLoQueVeLaOtraClinicaAlInstante`, `testSoloAutorizaClinicasConVinculoActivo` |
| RE-5.13.1/2/4: vincularse sin exponer mascotas | `VinculosPropietarioTest::testVincularseAUnaClinicaActivaNoLeExponeSusMascotas` |
| RE-5.13.3, RE-5.12.3, RN-115: rechazo con citas sin resolver; solo lectura tras desvincularse | `VinculosPropietarioTest::testConCitasSinResolverNoSeDesvincula`, `testTrasDesvincularseLaClinicaLeeSusRegistrosYNoRegistraNadaNuevo`, `testRevincularseYAgendarReactivaLaMascota`; `HistoriaCompartidaTest::testUnaMascotaNuncaVinculadaNoMuestraNada`, `testConElVinculoInactivoLaClinicaSoloLeeLoSuyo` |
| Arrastre del veterinario con las mismas validaciones | `AgendaPeticionesTest::testElVeterinarioArrastraSuCitaConLasMismasValidaciones` |
| Todo lo anterior en el esquema real | `BaseV2MysqlTest::testC6PortalEnElEsquemaReal` |

La prueba de C4 `testUnaMascotaNoVinculadaNoMuestraNada` suponía 403 con el vínculo inactivo; se partió en dos según la regla de RN-115.

**Verificación.**

| Comando | Resultado |
|---|---|
| `vendor/bin/phpunit` | 358 pruebas, 1617 aserciones, 17 saltadas (las de MySQL, sin variable). |
| Con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1` y la base por defecto `zooki_test_base_v2` (MariaDB 10.4.32) | 358 pruebas, 1829 aserciones, sin fallos ni saltadas. |
| `php -l` y `node --check` de lo tocado | Sin errores. |

**Recorrido HTTP con Fabio** (`php -S` y `curl`; sin navegador en esta sesión), sobre `zooki_v2_prueba`:

- `portal_propietario` → 200, con una tarjeta de horario para Norte y otra para Sur, el selector de clínica, «Mis clínicas» y ningún `<script>` en línea.
- La historia de Luna trae la clínica de cada registro; la ficha de una mascota ajena da 403.
- Catálogos, horas y huecos de Sur; agenda a Luna en Norte → 200; agenda a Kira en Sur, donde no estaba vinculada → 200 y Kira queda vinculada a Sur. Duración y margen copiados, prioridad verde. Luna en Sur con Diego ya ocupado → 422.
- Autorizar a Sur: Diego pasa de no ver consultas a ver las de Norte; al revocar, deja de verlas.
- Desvincularse de Sur con la cita de Kira → 422; tras cancelarla → 200. El vínculo y las mascotas quedan inactivos y la autorización en 0. Diego ve la ficha de Luna en solo lectura y registrar una vacuna da 403; Fabio agendando en Sur con el id → 403. Volver a vincularse → 200.
- Editar la ficha → solo cambió el peso; teléfono → 200 y el correo enviado se ignora; impresión → 200 con la clínica de cada registro.
- La auditoría registra cada paso. El registro del servidor no mostró avisos.

Quedan en `zooki_v2_prueba` dos citas nuevas del 9 de octubre (una confirmada en Norte y otra cancelada en Sur); Fabio quedó vinculado otra vez a Sur, pero sus mascotas allí siguen inactivas hasta que agende o las atiendan. No se tocó la cita 5 del 8 de octubre, que ya existía.

**Pendientes.**

1. **Navegador:** falta revisar a mano el portal en móvil, tablet y escritorio (agendar con clínica, «Mis clínicas», historia con su clínica) y el arrastre en la agenda del veterinario.
2. **Etapa E:** el límite del plan al vincular una mascota agendando (RE-5.3.5 y HU-5.3).
3. **Etapa D:** el cambio de correo con verificación (RE-T.5.7) y la gestión de la política.
4. **Fuera de C6:** HU-5.6 (reprogramar desde el portal) sigue diferida; por la decisión de C5, el propietario solo cancela. HU-5.7, HU-5.10 y HU-5.11 (notificaciones del propietario, carnet y QR) son de su módulo.
5. **C7 y C8:** panel y dashboard; recordatorios por correo por clínica.
6. **C9:** los `onclick` y `style=` heredados de `views/portal/index.php` e `imprimir_historial.php`.
7. La versión de publicación y las descargas del portal se actualizan al cerrar M0 (§7).

### C6 — Revisión (2026-10-07)

**Resultado: aprobada.** Claude (sesión de revisión) revisó el diff y en particular `ModeloClinica::enClinicaDelPropietario()`: solo funciona en el contexto propietario, valida en la base que la clínica sea de un vínculo activo suyo y esté activa, y el controlador solo la usa para veterinarios, tipos, horas, sugerencias y la reserva (con la ficha propia comprobada y `Mascota::vincular()`, que exige mascota del propietario y vínculo activo). Las decisiones del usuario 1 a 4 están bien aplicadas, y RN-115 quedó como se aprobó en la revisión de C4.

**Observación para el futuro (no bloquea):** la copia acotada que devuelve `enClinicaDelPropietario()` conserva todos los métodos públicos del modelo, incluidos los de personal (por ejemplo, `Cita::listarRango()`). Hoy ningún controlador los llama sobre esa copia; quien toque el portal no debe hacerlo. Si el portal crece, conviene que esos métodos rechacen la copia del portal.

**Prueba manual del usuario (2026-10-07).** Bien: elegir clínica, «Mis clínicas» (autorizar, revocar, vincularse y desvincularse; el rechazo con citas sin resolver funciona), historia con su clínica, vista en celular y arrastre en la agenda del veterinario. Hallazgos:

1. **Bug (corregido por la revisión):** al agendar, «No se pudieron cargar los horarios» siempre. En `portal.js::cargarHoras()` la desestructuración `const [clinica, agendaVet]` dentro del `try` ocultaba la constante `clinica` (el id elegido) que usan las propias URL, y lanzaba un `ReferenceError` antes de pedir nada. Se renombró a `horasClinica` / `horasDeLaClinica`; `node --check` correcto. El recorrido de C6 no lo detectó porque probó los endpoints con `curl`, no el JS: las pantallas tocadas por cada subetapa necesitan la prueba en navegador del usuario.
2. **Molestia de uso:** el SweetAlert2 de confirmación al activar o desactivar la historia compartida en cada cambio. Se cambia por un aviso breve no bloqueante (toast de SweetAlert2) en C9. **Decisión del usuario:** SweetAlert2 no se quita del sistema; en C9 se revisan sus usos caso por caso: acciones frecuentes o reversibles pasan a toast, y las destructivas o irreversibles conservan la confirmación.
3. **Sin indicación de que las citas se pueden arrastrar:** va en el manual de usuario (entregable pendiente) y, por decisión del usuario, una pista visible en la agenda del veterinario (C9).

## Anexo C7 — Resultado

Lo escribió Claude Code; la revisión corresponde a la sesión de revisión de Claude. Se releyeron `AGENTS.md`, este plan (A.5, A.6 y los anexos C4–C6 con sus revisiones) y las HU y RE del módulo 6.

**Decisión del usuario (2026-10-07).** Retirar lo muerto y sumar al panel: se quitan `get_role_stats_ajax`, `get_charts_data_ajax` y `get_timeline_ajax`, sus rutas, la matriz y su JS sin pantalla. `get_pendientes_ajax` pasa a un modelo por clínica. El panel del administrador suma «Pacientes activos» y «Propietarios» de la clínica activa.

**Qué se hizo.**

- **`Panel` extiende `ModeloClinica`.** Toda consulta filtra por la clínica activa. El veterinario se identifica por `id_veterinario` (su `id_usuario`) y los nombres salen por `id_usuario`, no por documento. Sin clínica activa falla cerrado con 403: el super-administrador sigue sin panel clínico (etapa E).
  - La carga por veterinario sale de `usuario_clinica` (rol veterinario, vínculo y cuenta activos), no de `usuarios.id_rol`.
  - Los recordatorios del veterinario son vacunas y desparasitaciones registradas en la clínica activa, de mascotas activas con vínculo activo que él atendió o tiene agendadas allí.
  - Nuevos `contarPacientesActivos()` (`mascota_clinica` activa y mascota activa; sin las desvinculadas, RN-115) y `contarPropietarios()` (`propietario_clinica` activo y cuenta activa).
- **`PanelController`:** `datosVeterinario(int $idVeterinario)`; el administrador recibe `pacientes_activos` y `propietarios`. `vet_area` le pasa `Contexto::idUsuario()`.
- **Vistas:** el panel del administrador muestra seis contadores (seis columnas en escritorio, tres en tablet y dos en móvil). El enlace a la ficha desde «Mi día» usa `id_propietario`, como ya esperaba `medical-module.js`.
- **`DashboardController`:** queda solo `getPendientesAjax`, que usa el aviso del navegador de `extras.js`. Sale de `Panel`, `Vacuna::pendientesEntre` y `Desparasitacion::pendientesEntre`, con el reloj de la clínica; el veterinario recibe solo sus citas.
- **`dashboard.js`:** pasó de 850 a 233 líneas.
  - Se quitaron las estadísticas, las gráficas, la línea de tiempo y su cuenta regresiva, la agenda semanal y el modal de reprogramar (usaba `doc_veterinario`).
  - También el buscador global (sin campo en ninguna vista ni ruta `buscar_global_ajax`) y el corte `ZOOKI_ROLE !== 3`.
  - Quedan el loader global, las notificaciones internas y los valores por defecto de Chart.js, que usa la gráfica del panel.
- Sin migración ni cambio de esquema.

**RE y evidencia.**

| Alcance | Prueba |
|---|---|
| RNF-11, RE-6.2.1, RE-6.2.3, RE-6.2.5: Norte no incluye nada de Sur, ni al revés | `PanelTest::testCadaAdministradorVeSoloSuClinica`, `testLasAtencionesAbiertasSonDeLaClinicaActiva`, `testLasConsultasSeCuentanEnUnRangoSemiabiertoDeLaClinica` |
| RE-6.2.4: la tendencia es de la clínica | `PanelTest::testLaTendenciaEsDeLaClinicaActiva` |
| RE-6.1.4, RN-G01: la misma persona ve números distintos según el contexto | `PanelTest::testElMismoVeterinarioVeNumerosDistintosSegunLaClinica`, `testElenaVeSuAgendaEnNorteYLaClinicaEnSur` |
| RE-6.2.2, RE-6.2.6: la carga solo lista veterinarios activos de la clínica, también sin citas | `PanelTest::testLaCargaSoloListaLosVeterinariosActivosDeLaClinica`, `testUnVeterinarioSinCitasApareceEnCero` |
| RE-6.4.1, RN-115: pacientes activos y propietarios, sin vínculos inactivos | `PanelTest::testPacientesYPropietariosSonDeLaClinicaYSinVinculosInactivos` |
| RE-6.1.1: recordatorios de sus pacientes en la clínica activa | `PanelTest::testLosRecordatoriosSonDeSusPacientesEnLaClinicaActiva` |
| RE-6.1.4: pendientes del día por clínica y veterinario | `PanelTest::testLosPendientesDelDiaSonDeLaClinicaYDelVeterinario` |
| Super-administrador sin panel clínico | `PanelTest::testSinClinicaActivaElPanelFallaCerrado` |
| Todo lo anterior en el esquema real | `BaseV2MysqlTest::testC7PanelesEnElEsquemaReal` |
| Matriz y enrutador coinciden tras retirar tres acciones | `AutorizacionRolTest` (sin cambios, en verde) |

`PanelTest` se reescribió con el fixture de dos clínicas (12 casos). Se comprobó que detecta una fuga: quitar el filtro de clínica de las citas, el estado del vínculo en la carga o el de `mascota_clinica` en pacientes o recordatorios hace fallar al menos una prueba.

**Verificación.**

| Comando | Resultado |
|---|---|
| `vendor/bin/phpunit` | 364 pruebas, 1657 aserciones, 18 saltadas (las de MySQL, sin variable). |
| Con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1` y la base por defecto `zooki_test_base_v2` (MariaDB 10.4.32) | 364 pruebas, 1877 aserciones, sin fallos ni saltadas. |
| `php -l` y `node --check` de lo tocado | Sin errores. |

**Recorrido HTTP** (`php -S` y `curl`; sin navegador en esta sesión).

Las contraseñas guardadas ya no coincidían con `zooki_v2_prueba`, así que no se reescribieron. El recorrido corrió sobre una copia del proyecto con su propia base local temporal (esquema, semilla, `datos_prueba.php --si` y citas, consultas y vacunas en las dos clínicas); después se borraron la copia y esa base. Los intentos fallidos de login que dejó el primer intento en `zooki_v2_prueba` se borraron de `intentos_login`; nada más se tocó allí.

- **Ana** (`admin_panel` de Norte) → 200: 3 citas hoy, 2 consultas del mes, 2 pacientes activos y 2 propietarios; carga de Beto y Elena. Coincide con la base.
- **Carla** (Sur) → 200: 2 citas, 1 consulta, 1 paciente activo (la mascota desvinculada no cuenta) y 1 propietario; carga solo de Diego.
- **Beto** (`vet_area` de Norte) → 200: sus citas de Luna y Kira, su atención sin cerrar y la vacuna próxima de Luna en Norte. Pedir `admin_panel` lo redirige.
- **Diego** (Sur) → 200: sus dos citas de Luna y la vacuna de Sur.
- **Elena:** como veterinaria en Norte ve solo su cita de Kira; como administradora en Sur ve lo mismo que Carla, y `vet_area` la redirige.
- **Gina:** `admin_panel` la redirige y `get_pendientes_ajax` responde 403.
- `get_pendientes_ajax` de cada persona coincide con su panel. Las tres acciones retiradas responden 403 (fuera de la matriz).
- El enlace a la ficha (`vet_pacientes&propietario=6&mascota=1`) y sus dos peticiones responden 200. No hubo avisos de PHP en las páginas ni en el registro del servidor.

**Pendientes.**

1. **Navegador** (lección de la revisión de C6): revisar a mano `admin_panel` y `vet_area` en móvil, tablet y escritorio. En particular: los seis contadores, la gráfica de tendencia, las notificaciones y el aviso de citas del navegador después de quitar el código muerto de `dashboard.js`.
2. **Módulo 6:** RE-6.4.4 (tasa de ausentismo por periodo y por veterinario, v2.0) no estaba en el alcance de C7. Tampoco la agrupación por especie de HU-3.5 ni las gráficas de RE-6.4.2.
3. **C8:** recordatorios por correo por clínica. **C9:** limpieza; `views/admin/citas.php` conserva su propio `STATE_COLORS` en línea.
4. La versión de publicación y las descargas del portal se actualizan al cerrar M0 (§7).

### C7 — Revisión (2026-10-08)

**Resultado: aprobada.** Claude (sesión de revisión) revisó el diff: `Panel` extiende `ModeloClinica` y toda cifra sale de la clínica activa; la carga por veterinario sale de `usuario_clinica`; pacientes activos excluye vínculos inactivos (RN-115); el super-administrador falla cerrado. Se valora que se comprobara que las pruebas detectan una fuga al quitar cada filtro, y que el recorrido no reescribiera las contraseñas de `zooki_v2_prueba` (usó una base temporal propia). La decisión del usuario de retirar el código muerto de `dashboard.js` y sumar dos contadores quedó bien aplicada.

**Prueba manual del usuario (2026-10-08):** `admin_panel` y `vet_area` se ven bien con los dos roles.

**Hallazgo (regresión de C1, se corrige en C9):** `admin_usuarios` quedó solo en vista de tabla. En v1.12.0 la vista por defecto era de tarjetas (`person-card`, `personal-grid`) con un botón para cambiar a tabla. **Decisión del usuario:** recuperar la vista de tarjetas como la de por defecto, conservando el botón para cambiar a tabla.

**Fuera de M0, para el módulo 6:** RE-6.4.4 (ausentismo por periodo y veterinario), la agrupación por especie de HU-3.5 y las gráficas de RE-6.4.2.

## Anexo C8 — Resultado

Lo escribió Codex; la revisión corresponde a la sesión de revisión de Claude.

**Qué.** `Recordatorio` usa `id_usuario` y conserva la clínica que aplicó la dosis o reservó la cita. La tarea del sistema recorre todas las clínicas como excepción explícita a RNF-11, sin responder a pantallas. Solo selecciona mascota, vínculo con esa clínica y propietario activos, con correo (RN-115). `EnviadorRecordatorios` prepara el correo con el nombre de la clínica y registra el cuerpo, la persona, la clínica y el resultado. Conserva los avisos a 7 y 1 días, la recuperación por ventana y hasta tres intentos por aviso de la v1.11.0; una dosis posterior en cualquier clínica suprime la antigua.

**RE y pruebas.** `RecordatorioTest` (fixture de dos clínicas, 11 casos) cubre RE-3.2.2/3/4, RE-3.6.1/2/3/4 y RE-T.15.1: origen correcto en correo y bitácora, mascota/cuenta/vínculo inactivos, sin correo, renovación entre clínicas, no duplicados, límite de reintentos, excepción SMTP y separación de intentos por clínica. `VentanaRecordatorioTest` conserva la batería de fechas de la v1.11.0. `BaseV2MysqlTest::testC8CronRealConCorreoSimuladoYDosClinicas` incluye `send_reminders.php` con conexión propia, reloj y correo simulados: 7 avisos, segunda ejecución 0; al desvincular Sur, solo 4 de Norte. Los recordatorios de citas conservan RE-4.4.1.

**Verificación.** Suite SQLite: 371 pruebas, 1709 aserciones, 19 saltadas por MySQL sin variable. Suite con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1`, puerto temporal 3307 y base por defecto `zooki_test_base_v2`: 371 pruebas, 1956 aserciones, sin saltadas ni fallos. MariaDB 10.4.32 con datos temporales fuera de OneDrive (su sincronización bloqueaba el borrado del directorio de prueba). No se leyó ni modificó `zooki_v2_prueba`; ningún envío SMTP real.

**Pendientes.** Revisión de Claude. El horario de las 07:00 sigue definido en `scripts/zooki.cron` y el Schedule de Dokploy; RE-3.2.5 se comprueba operativamente en F. El MER no incluye zona horaria por clínica: se conserva America/Bogota, como C4/C5 y RE-3.6.3. Configurar los días de anticipación es HU-8.4 (v2.1), no se cierra RE-3.2.1 completo aquí. `VigilanteAtenciones` y avisos internos ya se aislaron y probaron en C5. Versión y descargas de publicación al cerrar M0 (§7). No se empezó D.

## Anexo C9 — Resultado

Lo escribió Codex; la revisión corresponde a la sesión de revisión de Claude. Se aplicaron las decisiones de las revisiones C6 y C7; C1.7 sigue protegiendo también los otros vínculos inactivos.

**Qué.** Se retiraron el espejo de rol de sesión, la compatibilidad de documento y el campo de documento sobrante de `Auditoria`, la pestaña y CSS del rol retirado y sus comentarios. La rama muerta de `nuevo_propietario` ya estaba retirada: solo se actualizó su comentario. Los eventos y estilos de las vistas pedidas pasan a archivos externos y `data-*`; los colores del tablero administrativo pasan a CSS, las gráficas breves comparten `indicadores.js`. Se escaparon nombres y motivos al trasladar sus plantillas. El menú activa auditoría, cuya pantalla se verificó. La agenda muestra la pista de arrastre. Usuarios recupera `person-card`/`personal-grid` de v1.12.0 con avatar local, tabla alternativa y preferencia en `localStorage`; ambas vistas comparten los botones y el bloqueo de C1.7.

**HU-T.15 cerrada; RE-T.15.1/2/3/4/5 y evidencia de toda C.**

| Alcance | Pruebas |
|---|---|
| Personal, contexto, rol/CSRF y super-administrador sin acceso clínico | `UsuarioSeguridadTest`, `ContextoTest`, `SeguridadClinicaTest`, `AutorizacionRolTest` |
| Configuración y catálogos de clínica | `ConfiguracionClinicaTest` |
| Mascotas y propietario global con vínculo y búsqueda exacta | `MascotaAccesoTest`, `PropietarioClinicaTest` |
| Historia, adjuntos y prevención; excepciones RN-113 y RN-115 | `ConsultaHistorialTest`, `HistoriaCompartidaTest`, `PrevencionTest` |
| Agenda y vigilancia del sistema | `CitaTest`, `AgendaPeticionesTest`, `CitaEstadoTest` |
| Portal propio, consentimiento de historia y vínculos | `PortalPropietarioTest`, `PortalAgendaTest`, `VinculosPropietarioTest` |
| Paneles, auditoría y avisos | `PanelTest`, `ActividadCuentaAuditoriaTest`, `NotificacionAccesoTest`, `RecordatorioTest` |
| Esquema real y excepciones del sistema | `BaseV2MysqlTest`, incluidos C3–C8 y configuración |

`UsuariosVistaTest` añade cuatro casos: tarjetas por defecto/tabla disponible, datos solo de la clínica, bloqueo compartido en ambas vistas (también vínculo inactivo), rol local y escape de texto. `ActividadCuentaAuditoriaTest` renderiza controlador y layout y comprueba que no aparece el evento de Sur. `node --test tests/Frontend/C9Interacciones.test.cjs` añade cinco casos: persistencia al recargar, navegador sin almacenamiento, clic/teclado sin duplicar, fondo/pestaña del modal y controles deshabilitados.

**SweetAlert2, revisado por acción.**

- **Toast:** activar/revocar historia compartida; vincular/desvincular una clínica (reversible, RE-5.13.1 con un clic; los rechazos de RN-115 siguen en el servidor); guardar teléfono y ficha/alta de mascota del portal; reservar cita y sus respuestas frecuentes; guardar personal o activar su vínculo. Los avisos de éxito y error de «Mis clínicas» no requieren aceptar. La petición bloquea únicamente su propio control mientras termina.
- **Confirmación conservada:** cancelar una cita (libera la reserva definitivamente y avisa); marcar inasistencia (cierra la cita); reservar otra cita de la misma mascota en el día (advertencia del servidor); finalizar atención (asienta historia y completa la cita); restaurar horario (reemplaza la configuración); desactivar personal (revoca acceso); restablecer contraseña (invalida la anterior). El diálogo final de atención conserva la elección agenda/resumen. Los errores de validación, contraseña y adjuntos clínicos que necesitan lectura mantienen su aviso. SweetAlert2 sigue en el sistema; si falta, las acciones que requieren confirmar fallan cerradas, sin `window.confirm`.

**Verificación.** Suite SQLite: 376 pruebas, 1726 aserciones, 19 saltadas por MySQL sin variable. Suite con `ZOOKI_TEST_MYSQL_HOST`, puerto temporal 3307 y base por defecto `zooki_test_base_v2`: 376 pruebas, 1973 aserciones, sin saltadas ni fallos. Cinco pruebas de JS en verde; sintaxis PHP/JS correcta. Búsqueda de las cinco claves v1 en `controllers`, `models`, `helpers`, `views`, `public` y `scripts`: **cero resultados**, al igual que los eventos/estilos en línea de las vistas solicitadas. El SVG del MER v1 (241 MB, ignorado por Git y sin referencias) se conservó en `scratch/MER_zooki-v1.svg`; no se tocó draw.io. PDF y Word de Historias de Usuario regenerados por el cierre de HU-T.15.

**Revisión visual del usuario.** `admin_usuarios` (tarjetas/tabla, recarga, búsquedas, dos clínicas, cuenta compartida y reset bloqueado), `admin_auditoria` (menú, filtros, colores y Excel/PDF), `admin_citas` (filtros, carrusel, detalle y Excel/PDF), `vet_agenda` (pista, arrastre y ambos modales), consulta del veterinario (pestañas y tratamientos), portal (detalle de mascota/cita, «Mis clínicas», teléfono, alta/edición de mascota, reserva/cancelación e historial para imprimir), campana del administrador y del veterinario, y roles de la landing. Probar móvil/tablet/escritorio.

**Pendientes.** Revisión de Claude y recorrido visual anterior; los modelos y permisos permanecen cubiertos por las pruebas. Los demás PDF/Word que ya estaban desactualizados siguen para el cierre de M0 (§7). Sin migración ni versión de publicación en C9; se actualiza al cerrar M0. No se empezó D, ni se hizo commit o push.

### C8 y C9 — Revisión (2026-10-08)

**Resultado: aprobadas. La etapa C queda cerrada y HU-T.15 cerrada.** Claude (sesión de revisión) revisó el diff y comprobó en el código que la búsqueda de las cinco claves v1 (`usuario_doc`, `doc_veterinario`, `doc_propietario`, `usuario_documento`, `usuario_id_rol`) da cero resultados, y que el SVG de 241 MB movido a `scratch/` está ignorado por git. C8 respeta RN-115 (una clínica desvinculada no escribe al propietario) y conserva ventana, reintentos y no duplicados. C9 recupera la vista de tarjetas de usuarios con las reglas de C1.7 y aplica el criterio aprobado de SweetAlert2.

**Observaciones (no bloquean):**

1. **Desvincularse con un toast, sin confirmación.** Es reversible, pero inactiva las mascotas en esa clínica hasta volver a agendar o ser atendidas. Si en la prueba manual se siente demasiado fácil de hacer por error, se le devuelve la confirmación; decide el usuario.
2. **Pruebas de JS fuera del CI.** `tests/Frontend/C9Interacciones.test.cjs` corre con `node --test`, pero GitHub Actions no lo ejecuta. Se agrega al CI en D.

**Pendiente del usuario:** el recorrido visual que lista el Anexo C9 (usuarios en tarjetas y tabla, auditoría, citas del administrador, agenda con la pista y el arrastre, consulta del veterinario, portal completo, campanas y landing), en móvil, tablet y escritorio.

**Etapa D partida en dos (propuesta de la revisión):** D1 — sesión, política de datos y cuentas (HU-T.16, HU-T.19, HU-5.8, consentimiento del registro con Google, alta de personal con enlace de activación, cambio de correo y documento con verificación, CAPTCHA por cuenta con Cloudflare Turnstile); D2 — registro y activación de clínicas (HU-0.1, HU-0.2 con los catálogos iniciales de D-1 y RE-0.2.5).

### C8 y C9 — Recorrido visual del usuario (2026-10-08)

Funcionó: Ana (usuarios en tarjetas y tabla, recarga, solo Norte, bloqueo de Elena, auditoría, citas, campana), Beto (pista de arrastre, arrastre, rechazo al soltar en hueco inválido, consulta con tratamiento), Fabio (Mis clínicas, agendar, cancelar, editar y registrar mascota, teléfono, historial), Elena (selector, Sur solo con su personal, cambio a Norte) y landing sin el rol retirado.

**Se corrige antes de D1 (subetapa C9.1):**

1. **Diseño de usuarios distinto al de producción.** Las tarjetas recuperadas en C9 no se parecen a las de v1.12.0. Referencia aprobada por el usuario: `specs/referencias/usuarios-personal-v1.png` y `specs/referencias/usuarios-clientes-v1.png` (capturas de producción). Pestañas Personal / Clientes, botón «Nuevo Usuario», buscador, filtro de rol (en Personal), segmento Todos / Activos / Inactivos, botón tabla / cuadrícula; tarjetas con avatar de iniciales, interruptor de estado, nombre, chips de rol (y «Inactivo»), documento, teléfono y botones al pie (editar y restablecer contraseña en Personal; editar y ver en Clientes). Se mantiene ese diseño con los datos de la v2.
2. **Botón de restablecer contraseña visible pero deshabilitado** en una identidad compartida (Elena): **decisión del usuario, ocultarlo** cuando no aplica. El bloqueo del servidor (C1.7) sigue igual.
3. **Demora de 3–4 s en actualizar la pantalla** del portal después de vincular, desvincular, cancelar o registrar mascota: la recarga espera a que se cierre el toast (`avisoPortal(...).then(() => location.reload())` en `portal.js`). Actualizar en cuanto responde el servidor.
4. **Teléfono del perfil del propietario** sin longitud máxima ni validación mientras se escribe.
5. **Imprimir historial:** el usuario no encontró la opción. El botón existe (`btnImprimirHistorial`), pero solo aparece al elegir una mascota en el filtro, no con «Todas». Hacerlo visible y claro donde el usuario busca la historia de una mascota.
6. Agregar `node --test tests/Frontend` al CI (observación 2 de la revisión de C8/C9).

**Mejoras que no bloquean** (validar al arrastrar antes de soltar, formularios, modales, media queries, peso desconocido): anotadas en `specs/pulido-interfaz.md` para la etapa final de pulido.

## Anexo C9.1 — Resultado

Lo escribió Claude Code; la revisión corresponde a la sesión de revisión de Claude. Cubre los seis puntos del recorrido visual de C8/C9; no se tocó `specs/pulido-interfaz.md`.

**Qué se hizo.**

1. **Usuarios con el diseño de producción.** `views/admin/usuarios.php` vuelve a la estructura y las clases de v1.12.0 (cuyo CSS seguía en `usuarios.css`), con los datos v2:
   - Pestañas Personal / Clientes, «Nuevo Usuario», buscador por pestaña, filtro de rol (Personal), Todos / Activos / Inactivos y botón tabla / cuadrícula.
   - Tarjetas con avatar de dos iniciales (colores de v1, sin servicio externo), interruptor de estado, nombre con «(Tú)», chips de rol e «Inactivo», documento y teléfono, y botones al pie.
   - Los filtros actúan a la vez sobre tarjetas y tabla, con aviso de «Sin resultados».
   - Interruptor y botones en parciales compartidos por tarjetas y tabla.
   - Personal: el interruptor usa `cambiar_estado_usuario_ajax`; desactivar pide confirmación; nadie se desactiva ni se restablece a sí mismo.
   - Clientes: interruptor, editar (nombre, teléfono y vínculo) y ver (datos y mascotas en la clínica). Usa las acciones existentes `get_propietario_ajax`, `actualizar_propietario_ajax` y `listar_mascotas_propietario_ajax`; documento, correo y contraseña siguen fuera del alcance del administrador (C1.7).
   - **Decisión del usuario (2026-10-08):** el interruptor del cliente es funcional, como el del veterinario en Pacientes, y se anota el hueco de RN-109 (ver pendientes).
2. **Restablecer contraseña oculto** si la persona tiene otro vínculo (`identidad_editable` en falso), en tarjetas y tabla. El servidor sigue rechazando igual (C1.7, `UsuarioSeguridadTest`).
3. **Recarga sin esperar al aviso.** `zookiRecargarConAviso()` en `avisos.js` (lo cargan los tres layouts) guarda el aviso en `sessionStorage`, recarga de inmediato y lo muestra al volver; sin almacenamiento, lo muestra y recarga igual.
   - Portal: reservar, cancelar, registrar o editar mascota, vincular y desvincular.
   - También usuarios, `medical-module.js` (mascota, consulta, vacuna, desparasitación, cita) y la contraseña creada en `perfil.js`, que esperaban o perdían el aviso.
   - El correo de confirmación de la reserva va con `keepalive`, como en `calendario.js`, para que la recarga no lo corte.
4. **Teléfono del perfil del propietario.** Nuevo `helpers/ValidadorTelefono.php` con la regla del servidor (7–20 caracteres: números, espacios, + y guiones). `PortalController` lo usa, y el campo toma de ahí `maxlength`, `minlength`, `pattern` y `data-caracteres`. `interacciones.js` filtra los caracteres mientras se escribe o se pega. El `onsubmit` en línea de ese formulario pasó a `portal.js`. Los formularios de personal y cliente de usuarios usan la misma regla.
5. **Imprimir historial.** Botón «Imprimir historial» en la cabecera del detalle de la mascota, junto a editar, con el enlace de esa mascota (RE-5.11). Sigue también el de la agenda de salud.
6. **CI.** `build-and-test` instala Node 22 y corre `node --test "tests/Frontend/**/*.test.cjs"`. `node --test tests/Frontend` literal falla desde Node 21 (el argumento es un patrón y no acepta una carpeta); el patrón corre las mismas pruebas.

Sin migración ni cambio de esquema; se subieron las versiones de caché de los CSS y JS tocados.

**Pruebas.**

| Alcance | Prueba |
|---|---|
| Diseño, aislamiento, interruptores, (Tú), reset oculto en identidad compartida (también con vínculo inactivo), clientes de la clínica, teléfono, sin JS/CSS en línea y escape | `UsuariosVistaTest` (10 casos) |
| Regla del teléfono igual en servidor y HTML | `ValidadorTelefonoTest`; `PortalPropietarioTest` sin cambios |
| Aviso tras recargar, sin almacenamiento, ningún JS espera al aviso, filtro de caracteres, botón de imprimir, filtros de usuarios | `tests/Frontend/C91Interfaz.test.cjs` (10 casos) |

Comprobación de mutación: volver a mostrar el reset en una identidad compartida hace fallar dos casos de `UsuariosVistaTest`.

**Verificación.**

| Comando | Resultado |
|---|---|
| `vendor/bin/phpunit` | 394 pruebas, 1790 aserciones, 19 saltadas (MySQL sin variable). |
| Con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1` y la base por defecto `zooki_test_base_v2` (MariaDB 10.4.32) | 394 pruebas, 2037 aserciones, sin fallos ni saltadas. |
| `node --test "tests/Frontend/**/*.test.cjs"` | 15 pruebas en verde. |
| `php -l` y `node --check` de lo tocado | Sin errores. |

No se usó ni se modificó `zooki_v2_prueba`. No hubo navegador en esta sesión.

**Para revisar en el navegador** (móvil, tablet y escritorio):

- `admin_usuarios` con Ana (Norte) frente a las dos capturas de referencia: pestañas, filtros combinados, tabla / cuadrícula, interruptor de Beto (confirmar y cancelar), el de Ana deshabilitado, Elena sin la llave, editar y ver a Fabio, y desactivar y activar su vínculo.
- Portal con Fabio: vincular y desvincular, reservar y cancelar, registrar y editar mascota (la pantalla cambia al instante y el aviso sale después); el teléfono de «Editar datos de contacto» (letras, paréntesis, más de 20 caracteres, pegar); «Imprimir historial» en el detalle de Luna.
- Veterinario (Beto): registrar consulta, vacuna, desparasitación y cita; el aviso sale después de recargar.

**Pendientes.**

1. **Hueco de RN-109 (desde C3, servidor):** `actualizar_propietario_ajax` deja que la clínica reactive el vínculo de un propietario que se desvinculó desde su portal, sin su consentimiento. Afecta a Pacientes y a Usuarios. Propuesta: reactivar solo con `solicitar_vinculo_propietario_ajax` (confirmación por correo), en D1 o donde decida el usuario.
2. La misma expresión del teléfono sigue repetida en `MascotaController`, `PerfilController`, `PropietarioController` y `UsuarioController`; pasarla a `ValidadorTelefono` y validar los demás formularios es del pulido.
3. Queda un `onsubmit` en línea en el formulario de contraseña de `views/portal/index.php` (no estaba en el alcance).
4. Revisión de Claude y recorrido visual del usuario. No se empezó D1.

### C9.1 — Revisión (2026-10-08)

**Resultado: aprobada.** Claude (sesión de revisión) revisó el anexo y el diff: los seis puntos están cubiertos, con pruebas de PHP y de JS (y la de JS ya corre en el CI). Se valora la solución de la demora (recargar de inmediato y mostrar el aviso al volver, guardado en `sessionStorage`) y que se aplicara también fuera del portal.

**Hallazgo de seguridad heredado de C3 (pendiente 1 del anexo):** la clínica puede reactivar con `actualizar_propietario_ajax` el vínculo de un propietario que se desvinculó desde su portal, sin su consentimiento (RN-109). **Propuesta de la revisión:** toda reactivación de un vínculo de propietario por parte de la clínica pasa por la confirmación del titular por correo (`solicitar_vinculo_propietario_ajax`), sin cambio de esquema. Se corrige como primer punto de D1. **Aprobado por el usuario el 2026-10-08.**

**Recorrido visual del usuario (2026-10-08): bien.** Hallazgo: el teléfono de Fabio todavía no tiene límite de caracteres ni formato en al menos un formulario, a diferencia de los de administración. Se unifica en D1: todos los campos de teléfono usan `ValidadorTelefono` y el filtro de `interacciones.js` (pendiente 2 del anexo).

**Etapa D en tres partes** (reemplaza la división en dos de la revisión de C8/C9; **aprobada por el usuario el 2026-10-08**):
- **D1 — Sesión, política de datos y registro del propietario:** el hueco de RN-109, HU-T.16 (sesión), HU-T.19 (política versionada y re-aceptación), HU-5.8 (registro del propietario como identidad global) y el consentimiento del registro con Google (RE-T.18.2–4).
- **D2 — Cuentas del personal y del titular:** alta de personal con enlace de activación (sin contraseña por correo), cambio de correo y de documento con verificación (RE-T.5.7, RE-T.5.8) y CAPTCHA por cuenta con Cloudflare Turnstile (RE-T.13.5).
- **D3 — Registro y activación de clínicas:** HU-0.1 (con Turnstile y NIT válido) y HU-0.2 (activación con el plan gratuito y los catálogos iniciales de D-1, RE-0.2.5).

## Anexo D1 — Resultado

Lo escribió Claude Code; la revisión corresponde a la sesión de revisión de Claude. Se releyeron `AGENTS.md`, §2, los anexos C1 a C9.1 con sus revisiones, HU-T.16, HU-T.18, HU-T.19 y HU-5.8 con sus RE, y RN-109, RN-G05, RN-G06, RN-G11, RN-G17 y RN-G19 a RN-G22.

**Decisiones del usuario (2026-10-08).**

1. **RN-G11:** una cuenta sin verificar no entra aunque su enlace venza. Antes, pasadas 24 horas, entraba con la contraseña que puso quien se registró. Se sale registrándose otra vez con ese correo (enlace nuevo, sin tocar la contraseña) o entrando con Google (RN-G21).
2. **RE-5.8.4:** el correo de bienvenida lleva el enlace del portal; el QR queda pendiente, sin dependencias nuevas.
3. **RE-T.18.2:** la foto de Google no se guarda, porque `usuarios` no tiene columna en el MER; se decide al cerrar M0.

**Qué se hizo.**

- **RN-109 (revisión de C9.1):** `PropietarioClinica::actualizar` desactiva el vínculo, pero no lo reactiva. El interruptor de Clientes envía la solicitud por correo (`solicitar_vinculo_propietario_ajax`), y los modales de Usuarios y Pacientes no ofrecen «Activo» para un vínculo inactivo. Se quitó `toggleUserStatus` de `medical-module.js`, código muerto que reactivaba.
- **HU-T.16:** `helpers/Sesion.php` es la única configuración de sesión (la usan `index.php` y `ver_archivo.php`).
  - Cookie de sesión HttpOnly, SameSite=Lax y Secure bajo HTTPS; `gc_maxlifetime` de 30 minutos y modo estricto.
  - A los 30 minutos sin actividad la sesión se vacía, el login muestra el motivo y queda un `LOGOUT` con usuario, clínica e IP.
  - El identificador se regenera en `InicioSesion` y al cambiar de contexto.
  - `public/js/sesion.js` lleva al login ante un 401 en los tres layouts.
- **HU-T.19:** `helpers/PoliticaDatos.php` es la única fuente de la versión y la vigencia; la usan la política, el alta presencial y el registro.
  - `ConsentimientoDatos` guarda versión, medio, fecha e IP.
  - `InicioSesion` marca la re-aceptación y `Security` la exige antes de cualquier acción, salvo aceptar o salir (pantalla `aceptar_politica`, formulario sin JS).
  - El super-administrador está exento. La re-aceptación se guarda con medio `formulario`, porque el ENUM del MER no tiene otro.
- **HU-5.8:** `models/RegistroPropietario.php`. El registro siempre es en una clínica activa, elegida de la lista o por el enlace `index.php?action=register&clinica=ID`, que abre el registro con esa clínica.
  - Cuenta nueva: la prueba del consentimiento se guarda antes de crearla; la verificación lleva la clínica, y el vínculo se crea al verificar, con auditoría en esa clínica.
  - Correo existente: no se duplica y se envía la confirmación de vínculo de C3. Si ya estaba vinculado, se le pide que inicie sesión. Un super-administrador o una cuenta inactiva reciben la misma respuesta, sin envío.
  - Se retiró `views/auth/register.php`, duplicada y sin uso.
- **Google (RE-T.18.2–4):** con un correo nuevo, lo que confirmó Google vive solo en la sesión hasta aceptar la política y elegir la clínica (modal con CSRF).
  - La cuenta nace sin contraseña ni documento, con consentimiento `google`, vínculo y perfil incompleto. Con el perfil incompleto, `Security` solo permite `completar_perfil` en el portal.
  - Con un correo existente aplica RN-G21: una cuenta pendiente queda verificada, sin contraseña, con el perfil por confirmar y ligada a la clínica que había elegido; una cuenta verificada conserva su contraseña.
- **Teléfonos:** `ValidadorTelefono` (`MENSAJE` y `atributosHtml()`) en los cinco puntos del servidor y en los ocho campos de las vistas.
  - Se quitó la expresión repetida de cuatro controladores y de `perfil.js`.
  - También el filtro propio de Pacientes, que dejaba pasar paréntesis.
- `login.php` queda sin JS ni estilos en línea: el `client_id` viaja en `data-*`.

Sin migración ni cambio de esquema.

**RE y pruebas.**

| Alcance | Prueba |
|---|---|
| RN-109: la clínica no reactiva; una petición directa tampoco | `VinculoReactivacionTest` (5) |
| RE-T.16.1–4 | `SesionTest` (7); `D1Sesion.test.cjs` (401 → login, layouts, `login.php` sin código en línea) |
| RE-T.19.1–3 y super-administrador exento | `PoliticaDatosTest` (5); la aceptación previa también en `RegistroPropietarioTest` y `RegistroGoogleTest` |
| RE-5.8.1–5 y RN-G11 | `RegistroPropietarioTest` (8) |
| RE-T.18.2–4, RN-G20–22 | `RegistroGoogleTest` (6) |
| Teléfonos con la misma regla | `TelefonosTest` (4), `ValidadorTelefonoTest` |
| Esquema real (ENUM `medio`, verificación con clínica, RN-109) | `BaseV2MysqlTest::testD1RegistroPoliticaYVinculoEnElEsquemaReal` |

Siete mutaciones hacen fallar su prueba: quitar el bloqueo de RN-109, el del perfil incompleto, el de la política, la regla de RN-G11, la aceptación previa, la regeneración al cambiar de contexto o la validación del teléfono en Usuarios.

**Verificación.**

| Comando | Resultado |
|---|---|
| `vendor/bin/phpunit` | 430 pruebas, 2102 aserciones, 20 saltadas (MySQL sin variable). |
| Con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1` y la base por defecto `zooki_test_base_v2` (MariaDB 10.4.32) | 430 pruebas, 2357 aserciones, sin fallos ni saltadas. |
| `node --test "tests/Frontend/**/*.test.cjs"` | 20 pruebas en verde. |
| `php -l`, `node --check` y `git diff --check` | Sin errores. |

**Recorrido HTTP** (`php -S` y `curl` sobre una copia con la base temporal `zooki_d1_recorrido`, ambas borradas al terminar; sin navegador; `zooki_v2_prueba` no se tocó):

- `Set-Cookie: PHPSESSID=…; path=/; HttpOnly; SameSite=Lax` (sin Secure por ser HTTP), y el identificador cambia al iniciar sesión.
- Política: Ana va a `aceptar_politica`; antes de aceptar, el AJAX da 403 y las páginas redirigen. Sin la casilla vuelve a la pantalla; con ella entra a `admin_panel` y queda la prueba (`2026-08-31`, `formulario`, IP). Fabio acepta y entra al portal; Gina va directo a `plataforma_inicio`.
- Inactividad simulada en el archivo de sesión: el AJAX da 401 con `redirect` al login, la página va al login con el motivo, y queda el `LOGOUT` de Ana en Norte.
- El enlace de Norte abre el registro con Norte elegida.
- Registro:
  - Sin aceptación: rechazado.
  - Nueva cuenta: una sola, con la verificación de la clínica 1, sin vínculo, y todavía no entra.
  - Fabio en Sur: confirmación de vínculo.
  - Fabio en Norte: se le pide iniciar sesión.
  - Teléfono inválido: el mensaje de la regla.
- Perfil incompleto: el portal lleva a `completar_perfil` y agendar da 403. Con un teléfono inválido se ve el error; con uno válido, el portal responde 200.
- No hubo avisos de PHP.

**Pendientes.**

1. Prueba en el navegador del usuario (lista en el resumen de entrega), incluido Google real en local con `GOOGLE_CLIENT_ID` y el origen autorizado.
2. QR del portal (RE-5.8.4) y foto de Google (RE-T.18.2), por decidir al cerrar M0.
3. RE-T.19.4 (revocar lleva a HU-5.14) es de v2.1; no se construyó.
4. RE-T.19.2: falta escribir en el RE la excepción del super-administrador. El alta de personal todavía no pide aceptación (llega en D2); mientras tanto, el personal acepta la política en su primer inicio de sesión (RE-T.19.3).
5. Heredado: un GET a `verificar_email` consume el enlace, y un filtro de correo que lo abra lo usa. La confirmación de vínculo ya no tiene este problema.
6. El formulario de contraseña del portal conserva un `onsubmit` en línea (anotado en C9.1).
7. Estado de HU/RE, versión y descargas: al cerrar M0 (§7). No se empezó D2.

### D1 — Revisión (2026-10-08)

**Resultado: aprobada.** Claude (sesión de revisión) revisó el anexo y el diff; en particular `helpers/Sesion.php` (una sola configuración, cookie segura también detrás del proxy por `X-Forwarded-Proto`, cierre por inactividad auditado) y se comprobó que ningún JS consulta al servidor en intervalos, así que la inactividad de 30 minutos no se renueva sola. El hueco de RN-109 quedó cerrado en el servidor y en las pantallas, y las siete mutaciones que hacen fallar sus pruebas dan confianza. Las decisiones del usuario 1 a 3 quedaron bien aplicadas.

**Para D2:**

1. **Enlace de verificación consumido por un GET** (pendiente 5 del anexo): los filtros de correo que abren enlaces pueden verificar una cuenta sin que el titular haga clic. La página del enlace debe mostrar un botón y verificar con POST, como ya hace la confirmación de vínculo.
2. **Alta de personal y RE-T.19.1** (**aprobado por el usuario el 2026-10-08**): la cuenta del personal se crea **pendiente e inerte** (sin contraseña, sin poder entrar y sin consentimiento); el titular recibe un enlace que vence en 72 horas donde acepta la política y crea su contraseña; si no lo usa, la cuenta pendiente se elimina (si no tiene otros vínculos). El texto de RE-T.19.1 se ajusta para reflejarlo.
3. Escribir en RE-T.19.2 la excepción del super-administrador (pendiente 4 del anexo).

**Prueba manual del usuario (2026-10-08):** la aceptación de la política con Ana, el registro de propietario y el registro con Google en local funcionan. Hallazgos para D2:

- La política de contraseña rechaza bien una clave que contiene el nombre, pero el aviso llega en un SweetAlert al enviar: debe validarse mientras se escribe.
- «Completar perfil» (Google) no valida en tiempo real que el documento no esté registrado ni el formato del teléfono.
- El teléfono al editar a Fabio sigue sin cambios visibles: revisar en D2 qué formulario es y aplicarle la regla.
- El enlace del correo de verificación llevó a producción porque el `APP_URL` del `.env` local apunta allí: en local debe apuntar a la instalación local (anotado en `agentes/metodo.md`, sección 9).

**Desde aquí el trabajo sigue el método de `agentes/metodo.md` y el estado en `agentes/estado.md`.**


## Anexo D2 — Resultado

Lo escribió Codex; la revisión corresponde a la sesión de revisión de Claude. **Entregada, pendiente de revisión.** Se siguieron los seis puntos acordados en `agentes/estado.md`, la revisión de D1 y la prueba manual del usuario.

**Decisiones confirmadas por el usuario (2026-10-08).** Ajustar también HU-T.19, además de RE-T.19.1/2, sin subir la revisión de los documentos. El administrador restablece por enlace: invalida la contraseña actual y el titular elige otra antes de entrar; se conservan las restricciones de C1.6/C1.7. RN-G19 y la trazabilidad del plan reflejan la excepción del personal pendiente.

**Qué y riesgos cubiertos.**

- Alta de personal pendiente, `estado=0`, contraseña `NULL` y sin consentimiento: no entra por contraseña ni Google. Enlace secreto de 72 horas; POST con CSRF exige aceptación y contraseña válida antes de activar, con prueba `alta_personal`. El listado distingue «Pendiente de activación». Restablecer una invitación pendiente reenvía la activación. `limpiar_cuentas_pendientes.php` elimina únicamente altas vencidas sin otro vínculo (incluidos los inactivos), consentimiento ni invitación vigente; conserva la auditoría. Se agregó su tarea horaria a `scripts/zooki.cron`.
- Correo y documento del titular desde perfil del personal y portal, con contraseña actual o token nuevo de Google verificado en servidor. El correo anterior sigue vigente hasta confirmar el nuevo por POST; se retira Google, se invalidan enlaces antiguos de recuperación y se avisa al buzón anterior. Una cuenta de Google sin contraseña debe crearla primero. Documento duplicado: sin cambio de identidad y con caso de soporte sin duplicar el caso abierto; corrección válida con valores anteriores auditados y aviso.
- Turnstile sustituible en pruebas y reutilizable en D3. Cinco fallos en 15 minutos bloquean la IP; desde otra IP la cuenta exige CAPTCHA y sigue pudiendo entrar. Contraseña y Google aplican la regla; los mensajes no revelan la cuenta. `.env.example` contiene las claves públicas de prueba de Cloudflare, que siempre aprueban y deben sustituirse en producción.
- Verificación de registro, activación y cambio de correo: GET presenta el botón, POST con CSRF consume una sola vez. Restablecer por enlace invalida la contraseña anterior, conserva C1.7 y consume el enlace atómicamente con la nueva contraseña. Una recuperación emitida a un correo que ya cambió no modifica la cuenta.
- Un componente `validacion-cuenta.js` consulta los helpers del servidor, muestra avisos junto al campo, descarta respuestas antiguas y bloquea envíos pendientes o sin conexión. Aplicado a todos los formularios de cuenta acordados y al propietario de Pacientes (Fabio); reglas de formato y contraseña en servidor. Se extrajo el código en línea de las pantallas de contraseña. El resto del sistema queda inventariado en `specs/pulido-interfaz.md`.

Sin migración ni cambio de esquema. Las operaciones del titular son globales y solo reciben el usuario de sesión o el enlace secreto; el administrador conserva el contexto activo y las restricciones de identidad compartida. La limpieza es una excepción explícita del sistema sobre altas inertes.

**RE con evidencia.**

| RE / regla | Prueba |
|---|---|
| RE-T.19.1, RN-G19: pendiente, aceptación, 72 horas, limpieza selectiva | `CuentasD2Test` (alta inerte, aceptación, vencimiento, otros vínculos); `UsuarioSeguridadTest`; `BaseV2MysqlTest::testD2CuentasEnElEsquemaReal` |
| RE-T.19.2: super-administrador exento | `PoliticaDatosTest`; texto corregido junto con HU-T.19 |
| RE-T.5.7, RN-G23: identidad, correo vigente hasta confirmar, Google retirado, unicidad al confirmar | `CuentasD2Test`; `BaseV2MysqlTest`; recorrido HTTP con correo simulado, incluido aviso al anterior |
| RE-T.5.8, RN-G24: autenticación nueva, duplicado a soporte, valores anteriores | `CuentasD2Test`; recorrido HTTP de documento duplicado y CSRF de otra sesión |
| RE-T.7.1–5: administración local, unicidad y vínculo existente | `UsuarioSeguridadTest`, `AutorizacionRolTest`; altas por HTTP y validación del frontend |
| RE-T.14.2, RN-G10: restablecer por enlace, anterior revocada, regla C1.7 | `CuentasD2Test`, `UsuarioSeguridadTest`; restablecimiento completo por HTTP con correo simulado |
| RE-T.13.5, RN-G15: cinco fallos, IP distinta y CAPTCHA sustituible | `CuentasD2Test::testCaptchaDesdeOtraIpNoBloqueaLaCuenta`, `TurnstileTest` |
| Enlaces sin mutación por GET y consumo único; CSRF | `CuentasD2Test`, `BaseV2MysqlTest`; recorrido HTTP de activación rechazada sin CSRF y luego aceptada |
| Formato, política al escribir, unicidad y respuestas antiguas | `D2ValidacionCuenta.test.cjs` (11), `TelefonosTest`, `ValidadorTelefonoTest`; navegador sobre copia aislada |

**Verificación.**

- `php vendor/bin/phpunit`: 452 pruebas, 2184 aserciones, sin fallos; 21 saltadas de MySQL sin variable.
- Suite completa con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1`, puerto 3307, MariaDB temporal propia y base por defecto **`zooki_test_base_v2`**: 452 pruebas, 2454 aserciones, sin fallos ni saltadas. Los últimos ajustes de bloqueo del padre y contraseña nula se verificaron además con la suite y la prueba MySQL de D2.
- `node --test "tests/Frontend/**/*.test.cjs"`: 31 pruebas en verde.
- `php -l`, `node --check` y `git diff --check`: sin errores.
- Navegador: registro y portal de Fabio en copia aislada; contraseña con nombre rechazada al escribir, documento ocupado avisado junto al campo, correo/documento visibles en perfil y formato del teléfono. Sin errores de JavaScript en ese recorrido. Evidencia local en `scratch/d2-validacion-portal.png`.
- HTTP sobre copia y base temporal propia `zooki_d2_recorrido`, con correo simulado: alta inerte, activación GET/POST, rechazo sin CSRF, aceptación y acceso; cambio de correo y aviso al anterior; documento duplicado a soporte; CSRF de otra sesión rechazado; restablecimiento del administrador y creación de la contraseña por enlace. No se enviaron correos reales.
- No se leyó ni escribió `.env`, ni se modificó `zooki_v2_prueba` ni sus contraseñas. No se hizo commit ni push ni se empezó D3.

**Pendientes / pantallas para la prueba del usuario.**

1. Revisión de Claude: diff, trazabilidad, seguridad y pruebas; esta entrega no se autoaprueba.
2. Navegador en la instalación del usuario, escritorio y celular: registro, completar perfil con Google, activar personal, verificación de correo, recuperación/restablecimiento, cambio obligatorio de contraseña, Mi perfil del personal, Perfil del portal (contraseña, correo, documento y teléfono), Usuarios/Clientes y Pacientes (alta y edición de Fabio). Probar que la identidad compartida sigue sin admitir restablecimiento del administrador.
3. Google real para confirmar cambios y Turnstile contra Cloudflare; las pruebas automáticas sustituyen ambos servicios. Configurar las nuevas variables según `.env.example` y mantener `APP_URL` local apuntando al entorno local. Las claves de prueba no protegen producción.
4. Activar la tarea de limpieza en el despliegue F; el ejemplo cron ya la incluye. La gestión del caso de soporte queda para E; aquí se crea el caso.
5. Versión, historial, revisión documental y PDF/Word del portal: al cerrar M0, conforme al método. Fuera de D2: D3 y E.

**Ajuste del usuario a D2 (2026-10-08), lo escribió Codex.** Los errores se muestran debajo del contenedor del input; en contraseña se reutiliza la zona de ayuda/requisitos existente (por ejemplo, «Mínimo 8 caracteres»), nunca dentro de la fila del campo. Aplicado en el componente común, portal, perfil del personal, registro y pantallas de contraseña. Pruebas de JS comprueban la ubicación fuera de la envoltura y la reutilización de la ayuda: suite de frontend con 33 pruebas en verde; `node --check` y `git diff --check` sin errores. Sigue pendiente la revisión de Claude.

### D2 — Revisión (2026-10-08)

**Resultado: aprobada con corrección (D2.1).** Claude (sesión de revisión) revisó el anexo, el ajuste del usuario y el diff, y verificó en el código `CuentaTitular`, `IdentidadController`, `Autenticador`, `Security`, `Turnstile`, `validacion-cuenta.js` y `cuenta-password.js`. Suite PHP en una copia: 452 pruebas, 2184 aserciones, 21 saltadas de MySQL (no se corrió la parte MySQL en esta revisión). Pruebas de JS: 33 en verde.

**Bien resuelto.** Los enlaces se guardan con hash, se consumen una sola vez dentro de la transacción (`UPDATE … used = 0`) y solo con POST y CSRF; abrirlos ya no cambia nada. La cuenta pendiente es inerte de verdad: sin contraseña, `estado = 0`, Google la trata como inactiva, no aparece en la agenda (`u.estado = 1`) y el cambio de estado de Usuarios solo toca el vínculo. El cambio de correo deja vigente el anterior, revalida unicidad al confirmar, retira Google, invalida las recuperaciones y avisa al buzón anterior. El documento duplicado crea un caso sin duplicarlo. El CSS sacado de las pantallas de contraseña es idéntico al que estaba en línea, así que no hay cambio de diseño. Los textos de HU-T.19, RE-T.19.1/2 y RN-G19 reflejan la decisión del usuario.

**Hallazgos.**

| # | Tipo | Dónde | Qué pasa | Corrección |
|---|---|---|---|---|
| 1 | Bloqueante (antes de F) | `helpers/Security.php` 472, 520, 537, 563; `Autenticador.php` 80 | Los límites usan `REMOTE_ADDR`. En producción todas las peticiones llegan desde Traefik con la misma IP: cinco fallos de cualquiera bloquean el login de toda la plataforma 15 minutos, y 20 comprobaciones de documento o correo de cualquiera dejan sin validación en tiempo real a todos (RN-G15, RE-T.13.4). | Una sola fuente de IP (`Auditoria::ipCliente()`) en `Security` y en Turnstile. `ipCliente` debe tomar la última IP de `X-Forwarded-For` que no sea un proxy confiable, no la primera (la primera la puede escribir el cliente). `TRUSTED_PROXIES` explicado en `.env.example` para F. Prueba con proxy confiable, no confiable y cabecera falsificada. |
| 2 | Defecto de seguridad | `Security.php` 543 | Un acceso correcto borra el contador de la IP: quien tenga una cuenta puede alternar cuatro intentos contra otras cuentas y un acceso propio, sin bloqueo. Contradice RE-T.13.4 («el sexto intento desde la IP se rechaza»). | El acceso correcto limpia la sesión y la cuenta, no la IP. Prueba. |
| 3 | Defecto | `IdentidadController::validar` → `checkVerificationLimit` | Cada comprobación de documento o correo al escribir gasta el límite anónimo de 20 en 15 minutos, también con sesión: un administrador que da de alta tres o cuatro personas queda en «Espera un momento». | Sin sesión, el límite por IP sigue igual; con sesión, un límite por `id_usuario` más alto (propuesta: 120 en 15 minutos). Prueba. |
| 4 | Defecto | `UsuarioController.php` 173 y 367 | La invitación de personal y el restablecimiento usan `enviarCorreoVerificacion`: «Creaste una cuenta… si no fuiste tú quien se registró, ignora este mensaje». El personal no se registró y en el restablecimiento no se confirma un correo. | Dos plantillas en `EmailService`: invitación (clínica, rol, 72 horas, aceptar la política y crear contraseña) y restablecimiento (24 horas, la anterior ya no sirve). |
| 5 | Regresión | `public/js/perfil.js` 84; `views/perfil/index.php` | Mi perfil conserva la lista de requisitos de la contraseña, pero ya no se marca al escribir (se quitó `pintarRequisitos`), y se perdió «Las contraseñas coinciden». | Restaurar el marcado local de la lista (solo visual) junto a la respuesta del servidor; prueba de JS. |
| 6 | Defecto | `CuentaTitular::limpiarPendientes` 246 | Si una cuenta pendiente queda referenciada por otra tabla (perfil del veterinario, horarios, notificaciones, en módulos que vienen), el `DELETE` falla y la excepción corta todo el lote cada hora. | Error por cuenta: se registra y se sigue con la siguiente. Prueba con una FK ocupada. |
| 7 | Decisión | RE-T.13.4, RN-G15 | Cinco fallos por IP es muy estricto para una clínica que sale a internet por una sola IP: cinco errores del personal en 15 minutos dejan a toda la clínica sin entrar. El código anterior usaba 20 a propósito. | **Decisión del usuario (2026-10-08):** 20 por IP y 5 por cuenta con CAPTCHA; se ajusta el texto de RE-T.13.4 y RN-G15 en D2.1. |
| 8 | Pulido de interfaz | `views/auth/cambiar_password.php` 27 | «Contraseña actual» es una etiqueta sin el `form-group` ni el ojito del resto de la pantalla. | `specs/pulido-interfaz.md`, o de paso en D2.1. |
| 9 | Anotado | `views/auth/login.php` | Turnstile se pinta en el registro con formulario, pero `process_register` no lo verifica (solo lo usa Google desde esa pestaña). | Se decide en D3 con el registro de clínicas. |
| 10 | Cierre de M0 | `CuentaTitular::restablecerPersonal` | Restablecer o cambiar el correo no cierra las sesiones abiertas del titular. | Revisar al cerrar M0 junto con HU-T.16. |

**Lo que debe probar el usuario:** la lista de prueba manual de D2 (abajo). Los hallazgos de esa prueba se suman a D2.1.

#### D2 — Lista de prueba manual

Antes: XAMPP encendido, `php scripts/dev/datos_prueba.php --si`, y en el `.env` local `APP_URL` local, `TURNSTILE_SITE_KEY`/`TURNSTILE_SECRET_KEY` copiadas de `.env.example` y `GOOGLE_CLIENT_ID`. Los correos `@zooki.test` no llegan: para invitaciones y cambios de correo se usa un correo real con alias (`tucorreo+vet1@gmail.com`).

**Ana Norte**
- [ ] 1. `admin_usuarios` → Nuevo integrante con un correo real con alias: no pide contraseña; documento `1000000002` o teléfono con letras avisan junto al campo mientras se escribe. Al guardar, aviso breve y la lista muestra «Pendiente de activación» sin recargar.
- [ ] 2. El correo llega (el texto todavía dice «Creaste una cuenta»: conocido, va en D2.1). El enlace abre «Activa tu cuenta» con un botón; abrirlo no activa nada.
- [ ] 3. En ventana privada, entrar con ese correo antes de activar: mensaje genérico, no entra.
- [ ] 4. En la activación: una contraseña con el nombre avisa al escribir; si no coinciden, avisa; sin la casilla no envía. Bien hecho: «Tu cuenta quedó activa». Abrir otra vez el mismo enlace: «no es válido…».
- [ ] 5. Entrar con la cuenta nueva: entra a Clínica Norte sin volver a pedir la política.
- [ ] 6. Ana → Restablecer a esa persona: confirmación, llega un enlace; la contraseña anterior ya no sirve; con el enlace crea otra y entra.
- [ ] 7. Ana → Elena Doble: no se puede restablecer (identidad compartida).
- [ ] 8. Con Beto Norte, `vet_pacientes` → editar a Fabio: teléfono con letras o paréntesis avisa al escribir; documento `1000000002` avisa «ya registrado».
- [ ] 9. Carla Sur → `admin_usuarios`: no ve a la persona nueva de Norte.

**Fabio (portal)**
- [ ] 10. Perfil → Datos de acceso → Correo nuevo (real con alias) y contraseña actual mala: «No se pudo confirmar tu identidad». Con la buena: «Revisa el correo nuevo»; sigue entrando con el correo viejo.
- [ ] 11. Enlace del correo nuevo → botón → confirmado. Desde ahí entra con el nuevo y el viejo ya no sirve. (Al terminar, `datos_prueba.php --si` lo deja como estaba.)
- [ ] 12. Documento `1000000001`: avisa que al enviar se abrirá un caso; al enviar, mensaje del caso y el documento no cambia. Un documento libre se actualiza en pantalla sin recargar.
- [ ] 13. Contraseña del portal: la política avisa al escribir, sin SweetAlert. Teléfono del perfil: avisa al escribir.

**Beto Norte**
- [ ] 14. Mi perfil: el correo es de solo lectura y «Datos de acceso» funciona como en el portal. Contraseña: avisos al escribir (la lista de requisitos no se marca: conocido, va en D2.1).

**Sin sesión**
- [ ] 15. Registro de propietario con correo real: el enlace del correo abre «Confirma tu correo» con botón; solo al presionarlo queda verificada.
- [ ] 16. Google con un correo nuevo → Completar perfil: documento ocupado y teléfono inválido avisan al escribir.
- [ ] 17. «¿Olvidaste tu contraseña?» con Fabio: enlace, contraseña nueva, entra.
- [ ] 18. Login: se ve el recuadro de Turnstile (con las claves de prueba dice que es de pruebas).
- [ ] 19. **De último:** cinco contraseñas malas con Beto; el sexto intento, aun con la correcta, dice «Demasiados intentos». Bloquea tu IP local 15 minutos.

**Celular** (F12 y Ctrl + Shift + M): repetir 4, 10, 12 y el login. Mirar que los avisos queden debajo del campo y no dentro de la fila.

Dime solo lo que falló, con el número del paso.

#### D2 — Prueba manual del usuario (2026-10-09)

Probó hasta el paso 12; el resto quedó bloqueado por los correos y por el límite de comprobaciones.

- **Regresión (desde C9.1):** el modal de alta y edición del personal en Usuarios no es el de v1.12.0. Referencia: `specs/referencias/usuarios-modal-editar-v1.png` y `modalUsuarioGestion` en `git show main:views/admin/usuarios.php` (líneas 980–1090): título «Editar Usuario» con nombre • documento, Rol, Nombre completo, Correo, Teléfono con bandera (intl-tel-input) e interruptor de Estado.
- **Decisión del usuario (2026-10-09):** los modales del propietario en Pacientes son la referencia buena y no se cambian.
- **Defecto:** la validación no reacciona mientras se escribe: el aviso se borra con cada tecla y solo aparece tras una pausa o al salir del campo. Debe aplicarse en cada tecla.
- **Confirma el hallazgo 3:** en el portal, Fabio no pudo escribir un correo nuevo («Espera un momento antes de seguir comprobando»).
- **Texto:** «Este dato ya está registrado» debe nombrar el campo (correo o documento).
- **No son fallos:** el enlace iba a producción porque el `.env` local tiene `APP_URL` de producción (sin `APP_URL`, `EnlaceCuenta` usa localhost); Beto pidió la política porque era su primer acceso desde D1; la cuenta pendiente no tiene contraseña a propósito (el paso 3 estaba mal redactado).
- **Pasaron:** alta con invitación de 72 horas; teléfono de Fabio en Pacientes; correo de solo lectura y «Datos de acceso» en Mi perfil; Carla Sur no ve el personal de Norte.
- **Fricción de la prueba:** demasiados inicios y cierres de sesión y correos reales. D2.1 agrega correos locales a archivo, clave común opcional y política aceptada en `datos_prueba.php`, y el revisor hace el recorrido funcional en el navegador del equipo del usuario; el usuario solo revisa lo visual.

## Anexo D2.1 — Resultado

Lo escribió Claude Code; la revisión corresponde a la sesión de Claude (arquitecto). **Entregada, pendiente de revisión.** Cubre los hallazgos 1 a 8 de «D2 — Revisión» y los de la prueba manual del usuario; los hallazgos 9 (Turnstile en `process_register`, D3) y 10 (cerrar sesiones al restablecer, cierre de M0) quedan fuera, como pedía el alcance.

**Qué se hizo.**

1. **IP real (RN-G15).** `Auditoria::ipCliente()` es la única fuente de IP: auditoría, los cuatro límites de `Security` y Turnstile en `Autenticador`.
   - Detrás de un proxy confiable recorre `X-Forwarded-For` de derecha a izquierda y toma la primera IP que no sea un proxy confiable; la de la izquierda la puede escribir el cliente.
   - `TRUSTED_PROXIES` acepta IP exactas y rangos CIDR (IPv4 e IPv6), necesarios con Traefik porque su IP en la red Docker cambia. `.env.example` lo explica para la etapa F.
2. **Límites (decisión del usuario, 2026-10-08).** 20 fallos en 15 minutos bloquean la IP, también en la capa de sesión. 5 fallos sobre una cuenta exigen CAPTCHA y la cuenta se registra sin castigo, así que nunca se bloquea. Se ajustaron RE-T.13.4, RN-G15, el criterio de HU-T.13 y RNF-12 del ERS (el mismo límite), sin subir revisión, y el comentario de `Security`. La matriz RN → HU → RE no cambia: son los mismos identificadores.
3. **Acceso correcto:** limpia el contador de la sesión y el de la cuenta, no el de la IP.
4. **`validar_cuenta_ajax`:** sin sesión, 20 comprobaciones por IP; con sesión, 120 por `id_usuario` en 15 minutos. El aviso de unicidad nombra el campo: «Este correo ya está registrado.» / «Este documento ya está registrado.»
5. **Validación en cada tecla** (`validacion-cuenta.js`), en todos los formularios que usan el componente.
   - Al instante: formato del documento, correo, teléfono (el `pattern` de `ValidadorTelefono`) y nombre; requisitos de la contraseña con `password-policy.js`; coincidencia de contraseñas, que ahora también dice «Las contraseñas coinciden.».
   - Con pausa: la unicidad y la política completa del servidor, sin «Comprobando…» y sin borrar el aviso vigente mientras responde; una respuesta vieja no pisa la nueva.
   - `enlace_identidad.php` (activación) no cargaba `password-policy.js`; ahora sí.
6. **Mi perfil:** `perfil.js` vuelve a marcar en cada tecla la lista `perfilPwdRequisitos`; «Las contraseñas coinciden.» lo da el componente.
7. **Correos:** `EmailService::enviarInvitacionPersonal` (clínica, rol, 72 horas, aceptar la política y crear la contraseña) y `enviarRestablecimientoPorAdministrador` (24 horas, la anterior ya no sirve). `UsuarioController` deja de usar `enviarCorreoVerificacion`. Restablecer a una persona con la invitación pendiente reenvía la invitación.
8. **Modal del personal en Usuarios** con el diseño de v1.12.0 (`modalUsuarioGestion` y `usuarios-modal-editar-v1.png`).
   - Encabezado: «Nuevo Usuario» / «Editar Usuario» con nombre • documento y botón ✕.
   - Cuadrícula de tres columnas: Rol, Nombre completo, Correo electrónico, Teléfono con bandera y prefijo (intl-tel-input, la misma configuración de Pacientes) e interruptor de Estado con «Activo» / «Inactivo». Guardar dice «Crear Usuario» / «Guardar Cambios».
   - Reglas v2 conservadas: alta sin contraseña con el aviso de la invitación de 72 horas, C1.7 (correo de solo lectura y aviso en una identidad compartida), nadie se desactiva a sí mismo y validación en tiempo real.
   - Al editar, el tipo y el número de documento van en el subtítulo y no se editan, como en v1.12.0 y RE-T.7.1; la corrección del documento la hace el titular (RE-T.5.8).
   - Sin JS ni CSS en línea. Los modales del propietario en Pacientes no se tocaron.
9. **`CuentaTitular::limpiarPendientes`:** cada cuenta va en su propia transacción. Un error (por ejemplo, una FK ocupada) se registra en el log y la limpieza sigue con las demás.
10. **`cambiar_password.php`:** «Contraseña actual» con `form-group` y ojito.
11. **Prueba local.**
    - `MAIL_MODO=archivo` (comentado en `.env.example`): `EmailService` no envía y guarda cada correo como `.html` en `logs/correos/`, con la fecha, el destinatario y el asunto en el nombre (ya ignorado por git). Solo se respeta en local, con `helpers/EntornoLocal.php`, que ahora también usa `datos_prueba.php`; en otro entorno se ignora y queda en el log.
    - `datos_prueba.php --si --clave=<clave>`: misma clave para todos si cumple la política, y se niega con el motivo antes de tocar la base. Los usuarios de prueba, salvo la super-administradora, quedan con la política vigente aceptada. La lógica está en `scripts/dev/DatosPrueba.php` para probarla.
    - `agentes/metodo.md` §9 y `agentes/prueba-manual.md`: correos en `logs/correos/`, `--clave`, `APP_URL` comentado en local y sesiones simultáneas.

**De paso (anotado):** `.env.example` no se podía leer con `parse_ini_file` desde D2, por los paréntesis de dos comentarios; copiado como `.env`, ninguna variable cargaba. Se corrigió y ahora una prueba lo comprueba.

**RE y pruebas.**

| Alcance | Prueba |
|---|---|
| RN-G15: IP real; proxy no confiable, cadena real, cabecera falsificada, IPv6; Turnstile con la IP real | `D21CorreccionTest` (IP: 4 casos) |
| RE-T.13.4: 20 fallos bloquean la IP y no antes; el acceso correcto no limpia la IP | `D21CorreccionTest`, `SecurityTest`, `CuentasD2Test::testCaptchaDesdeOtraIpNoBloqueaLaCuenta` |
| RE-T.13.5: 5 fallos exigen CAPTCHA; 30 fallos desde otras IP no bloquean la cuenta; con CAPTCHA válido entra | `D21CorreccionTest::testCincoFallosExigenCaptchaYLaCuentaNuncaSeBloquea` |
| Comprobaciones: 20 por IP sin sesión, 120 por persona con sesión; aviso con el nombre del campo | `D21CorreccionTest` (3 casos), `D21Interfaz.test.cjs` |
| Validación en cada tecla, aviso vigente y respuestas tardías | `D21Interfaz.test.cjs` (4 casos), `D2ValidacionCuenta.test.cjs` |
| Mi perfil y modal del personal (título, subtítulo, bandera, interruptor, sin código en línea) | `D21Interfaz.test.cjs` (3 casos), `UsuariosVistaTest` |
| HU-T.14 y RE-T.19.1: asunto, enlace, clínica, rol y vigencia de las dos plantillas; el controlador las usa | `D21CorreccionTest` (3 casos) |
| Limpieza que sigue tras una FK ocupada | `D21CorreccionTest::testLaLimpiezaSigueConLasDemasCuandoUnaCuentaFalla` |
| `MAIL_MODO=archivo` (guarda y no envía, se ignora fuera de local); `--clave` y política aceptada; `.env.example` legible | `D21CorreccionTest` (4 casos) |

Mutaciones: siete del servidor y cinco del JS hacen fallar su prueba: IP tomada por la izquierda, `REMOTE_ADDR` en los límites, el acceso correcto limpiando la IP, 5 por IP, comprobación por IP con sesión, la limpieza que se corta, `MAIL_MODO` fuera de local, borrar el aviso mientras responde el servidor, esperar al servidor para el formato, el aviso sin el nombre del campo, los requisitos sin marcar y el interruptor sin efecto.

**Verificación.**

| Comando | Resultado |
|---|---|
| `vendor/bin/phpunit` | 470 pruebas, 2421 aserciones, 21 saltadas (MySQL sin variable). |
| Con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1` y la base por defecto `zooki_test_base_v2` (MariaDB 10.4.32) | 470 pruebas, 2691 aserciones, sin fallos ni saltadas. |
| `node --test "tests/Frontend/**/*.test.cjs"` | 40 pruebas en verde. |
| `php -l`, `node --check` y `git diff --check` | Sin errores. |

No se leyó ni escribió `.env`, ni se tocó `zooki_v2_prueba` ni sus contraseñas: `datos_prueba.php` solo se corrió con una clave inválida, que se niega antes de conectarse. No hubo navegador en esta sesión.

**Pendientes.**

1. Revisión del arquitecto y recorrido en el navegador (la lista va en el resumen de la entrega).
2. Etapa F: poner en `TRUSTED_PROXIES` el rango de la red de Traefik (`docker network inspect dokploy-network`). Sin eso, detrás del proxy todos comparten una IP y vuelve el bloqueo global.
3. El modal del personal ya no edita el documento (como v1.12.0); si se quiere conservar esa capacidad del administrador para cuentas exclusivas, decidirlo en la revisión.
4. Fuera de D2.1: Turnstile en el registro con formulario (D3), cerrar sesiones abiertas al restablecer o cambiar el correo (cierre de M0) y la validación de los formularios clínicos (pulido). No se empezó D3.

### D2.1 — Revisión (2026-10-09)

**Resultado: aprobada.** Claude (sesión de revisión) revisó el anexo y el diff. Suite PHP en una copia: 470 pruebas, 2421 aserciones, 21 saltadas de MySQL (la parte MySQL no se corrió en esta revisión; el ejecutor la reporta en verde). Pruebas de JS: 40 en verde.

**Verificado en el código.**

- `Auditoria::ipCliente()` es la única fuente de IP (auditoría, los cuatro límites de `Security` y Turnstile). Recorre `X-Forwarded-For` de derecha a izquierda, acepta rangos CIDR y se detiene en la primera entrada que no es IP. Sin `TRUSTED_PROXIES` vale `REMOTE_ADDR`.
- 20 fallos por IP (también en la capa de sesión); la cuenta suma fallos con bloqueo 0, así que solo pide CAPTCHA. Un acceso correcto ya no limpia la IP.
- Comprobaciones al escribir: `chk-usuario:<id>` con 120 en 15 minutos con sesión y `chk:<ip>` con 20 sin sesión.
- `validacion-cuenta.js`: regla local en cada tecla, servidor con pausa de 400 ms sin «Comprobando…», respuesta vieja descartada y mensaje de unicidad con el nombre del campo.
- Correos propios de invitación y restablecimiento; el enlace se escapa una sola vez dentro del `href`.
- Modal del personal con la estructura de `modalUsuarioGestion` de v1.12.0, sin código en línea; los modales de Pacientes no se tocaron.
- `MAIL_MODO=archivo` solo en local (`EntornoLocal`: Docker, `APP_ENV` y host de la base); `datos_prueba.php --clave` valida la clave antes de tocar la base y registra la política con medio `formulario`.

**Aceptado.** Al editar, el administrador ya no cambia el documento: así era v1.12.0 y RE-T.7.1 solo nombra nombre, correo, rol y estado; la corrección es del titular (RE-T.5.8).

**Hallazgos menores (van de paso en D3).**

| # | Tipo | Dónde | Qué pasa | Corrección |
|---|---|---|---|---|
| 1 | Defecto | `config/EmailService.php` 94, 277, 304 | Con `APP_URL` comentado en local (lo que ahora indica `metodo.md`), el botón del correo de bienvenida y los enlaces de la plantilla base apuntan a producción. | Usar `EnlaceCuenta::base()` en lugar de la URL fija. |
| 2 | Código muerto | `config/EmailService.php` 72 | `enviarCredencialesUsuario` (contraseña en claro por correo) ya no se llama desde D2. | Eliminarlo, también de `EmailService.example.php`. |

**Pendiente para F:** poner en `TRUSTED_PROXIES` el rango de la red de Traefik; sin eso vuelve el bloqueo global.

**Prueba:** recorrido funcional del revisor en el navegador integrado del equipo del usuario (con `MAIL_MODO=archivo`) y revisión visual del usuario en escritorio y celular.

#### D2.1 — Recorrido funcional del revisor (2026-10-09)

Claude hizo el recorrido en el navegador integrado del equipo del usuario (`http://localhost:3000`, `php -S`, `MAIL_MODO=archivo`, `datos_prueba.php --clave`), sobre `zooki_v2_prueba`.

**Pasó.**

- Los usuarios de prueba entran sin pedir otra vez la política.
- Alta de personal: validación en cada tecla (documento, correo, teléfono sin letras), invitación propia («Te invitaron a Clínica Norte…»), «Pendiente de activación» sin recargar. Modal «Editar Usuario» igual a la referencia de v1.12.0, con interruptor Activo/Inactivo.
- Activación: la clave con el nombre se rechaza al escribir; sin la casilla no se envía; activa una sola vez; el enlace usado dice «no es válido…». Valeria entra al área clínica sin volver a pedir la política.
- Restablecer: Elena Doble no tiene el botón; con Beto llega «Crea una nueva contraseña», la clave anterior deja de servir y con el enlace elige otra y entra.
- Mi perfil: la lista de requisitos se marca en cada tecla, «Las contraseñas coinciden.», «Este correo ya está registrado.», y el documento duplicado abre el caso sin cambiar el documento.
- Cambio de correo de Fabio: clave mala rechazada, enlace al correo nuevo, confirmación con botón, aviso al correo anterior y entrada con el nuevo. Se dejó con su correo original.
- RE-T.13.5: 5 fallos sobre Beto; la clave correcta sin CAPTCHA se rechaza y con CAPTCHA entra.
- RE-T.13.4: el intento 21 desde la IP se rechaza aunque Ana use la clave correcta; los accesos correctos intermedios no limpiaron la IP.

**Corregido por el revisor (una línea, con su prueba).** `validacion-cuenta.js` llamaba a `requestSubmit` dentro del `submit` original cuando las comprobaciones ya estaban en caché; el navegador lo ignora y el primer clic no hacía nada (había que presionar dos veces). Ahora se reenvía en otra tarea (`setTimeout`). `D2ValidacionCuenta.test.cjs` comprueba que el reenvío no es inmediato. Verificado con un clic real en el portal. JS: 40 pruebas en verde.

**De paso en D3.**

1. `MAIL_MODO=archivo` se ignoraba con `DB_HOST=db`: `EmailService` revisa `DB_HOST` tal cual, pero `Database` lo cambia a `127.0.0.1` en Windows. Debe usar el host efectivo de la conexión.
2. Hallazgos 1 y 2 de «D2.1 — Revisión» (URL de producción en la bienvenida y la plantilla; `enviarCredencialesUsuario` sin uso).
3. Restablecer con la invitación pendiente: el aviso debe decir «Se reenvió la invitación.» (acordado con el usuario).
4. «Nuevo Usuario» con el documento de alguien que ya es personal de la clínica dice «se vinculará a la clínica»: debe decir que ya es parte del personal.
5. `views/vet/layout.php` carga `css/dark-mode.css`, que no existe (404).
6. **Zona horaria:** el sistema no fija `date_default_timezone_set`; PHP usa la de `php.ini` (en XAMPP, Europe/Berlin: un correo de las 10:44 quedó con 17:44). Afecta «hoy», vencimientos y auditoría. Fijar `America/Bogota` en un solo lugar y la zona de la sesión de MySQL, con prueba.
7. Activación de personal, confirmación de correo y «Datos de acceso» (portal y Mi perfil) tienen campos sin el estilo del sistema: deben usar el diseño de `reset_password` y las clases de formulario del portal y de Mi perfil.

**Pulido de interfaz.** Tras crear la contraseña por enlace, el login no muestra confirmación. A 611 px de ancho, el encabezado de Usuarios se monta sobre las pestañas y corta «Nuevo Usuario».

**Decisión del usuario (2026-10-09), para E:** el panel del super-administrador lista las IP bloqueadas por RN-G15 y permite desbloquearlas con un clic. No se avisa por correo al administrador de la clínica en cada bloqueo; el bloqueo queda en la auditoría.

**Ampliación del usuario (2026-10-09), para E:** junto a cada IP bloqueada, un botón «Enviar aviso» que el super-administrador usa a mano para escribir al administrador de la clínica (nunca automático). Como una IP no pertenece a una clínica, el panel muestra las cuentas que fallaron desde esa IP (según la auditoría de LOGIN_FAIL) y sus clínicas, y el super-administrador elige a quién avisar.


## D2.2 — Alcance (corrección antes de D3)

**Decisiones del usuario (2026-10-09).**

1. Hay **D2.2 antes de D3**.
2. **Personal que ya existe en la plataforma:** no se vincula de una. Recibe una invitación («Clínica X te invitó como <rol>») que acepta o rechaza en 72 horas; solo al aceptar queda el rol en la clínica y el administrador ve sus datos. El administrador recibe la misma respuesta exista o no la cuenta. La persona conserva sus otros roles: un propietario que acepta queda propietario y veterinario, y elige el contexto al entrar (Modelos §9). Se ajustan RE-T.7.5, RN-705 y RN-G06.
3. Desde ahora cada subetapa cita su diagrama de `Modelos.md` y la revisión lo compara con el código (`agentes/metodo.md` §4, §5 y §6).

**Hallazgos del usuario en la prueba visual de D2.1.**

- El modal «Editar cliente» sigue siendo el de C9.1: en v1.12.0 clientes y personal usaban el mismo modal (al cliente se le ocultaba el rol).
- El mensaje de contraseña común («listas públicas…») confunde: debe decir «Esa contraseña es muy común y fácil de adivinar. Elige otra.».

**Diagramas desactualizados.** §10 (login) todavía tiene «contraseña temporal». §13.3 y RE-T.2.5 piden cerrar las demás sesiones al crear o cambiar la contraseña, y no está construido. No hay diagrama del alta de personal (nueva o existente).

**Propuesta del arquitecto, pendiente de confirmar:** guardar el rol de la invitación en `verificaciones_email.id_rol_vinculo` (nueva columna, migración 04) y cerrar las demás sesiones con `usuarios.version_sesion` (nueva columna en la misma migración).

**Decisión del usuario (2026-10-09, durante la ejecución de D2.2).** El prompt pedía «la misma respuesta exista o no la cuenta», pero dejaba dos fugas: la cuenta nueva aparecía en la lista como pendiente y la existente no, y «Revisa el documento y el correo» solo salía cuando ambos existían. El usuario eligió **cerrar las dos**: (1) la invitación a una persona existente aparece en la lista como «Pendiente de activación» con lo que escribió el administrador (nombre, tipo y número de documento, correo y teléfono), solo con «Reenviar invitación» y «Cancelar invitación», igual que una cuenta nueva pendiente; (2) si el documento y el correo son de personas distintas, **manda el correo**: se invita a la dueña del correo; si el correo no tiene cuenta y el documento sí, se invita al correo registrado de la dueña del documento. La respuesta es siempre «Invitación enviada. La persona tiene 72 horas para aceptarla.». Por eso la migración 04 también guarda los datos escritos en `verificaciones_email`.

**Decisiones del usuario (2026-10-09, cierre con Codex).**

- Se autoriza corregir en D2.2 la creación de la primera contraseña: una sesión Google abierta no basta; se solicita y verifica un token nuevo de Google, conforme a RE-T.2.4 y Modelos §13.3.
- Se autoriza ajustar RE-T.7.4: la unicidad se rechaza al editar una identidad; el alta invita a la cuenta existente según RE-T.7.5.
- Ambos ajustes conservan los identificadores, conteos y revisión de los documentos.

## Anexo D2.2 — Resultado

**Estado:** entregada, pendiente de revisión por la sesión de Claude (arquitecto), 2026-10-09.
**Autoría:** la implementación principal la escribió Claude Code; las pruebas de MySQL, los documentos, la confirmación nueva de Google autorizada y este cierre los escribió Codex.

### Qué se entregó

- **Claude Code:** invitaciones de 72 horas al personal existente, aceptación antes de asignar el rol, misma respuesta y lista con datos escritos para altas nuevas y existentes; prevalencia del correo; reenviar, cancelar, rechazar y vencer con aislamiento por clínica.
- **Claude Code:** migración 04 repetible, MER, esquema y fixture; cierre de otras sesiones por `version_sesion`, avisos por correo y preservación de la sesión del titular; modal compartido del cliente y personal conservando v1.12.0.
- **Claude Code:** correcciones heredadas de D2.1: URLs de correo, host efectivo para correo local, zona horaria Bogotá, páginas de enlaces con diseño de restablecimiento, estilos de Datos de acceso y mensaje de contraseña común.
- **Codex:** tres pruebas nuevas contra el esquema real y expectativa del migrador 03 + 04; HU-T.7, RE-T.7.4/5, RE-T.19.1, RN-G06 y RN-705 coherentes, sin nuevos identificadores ni cambios en las matrices y sus conteos.
- **Codex:** primera contraseña con otra confirmación de Google verificada por el servidor; componente externo reutilizado en Mi perfil, portal, cambio obligatorio y cambios de identidad, sin JS/CSS en línea ni tokens antiguos tras cancelar.

### RE y evidencia

| Requisito o regla | Prueba que lo demuestra |
|---|---|
| RE-T.7.5, RN-G06, RN-705 | `D22CorreccionTest`: misma respuesta, datos escritos, GET sin efectos, POST con CSRF, aceptación conservando roles, rechazo/vencimiento, aislamiento, reenviar/cancelar y auditoría equivalente; `UsuarioSeguridadTest`: prevalece el correo cuando documento y correo son de personas distintas. |
| RE-T.7.4 | `UsuarioSeguridadTest::testEditarElCorreoRechazaElDeOtraCuentaSinModificarIdentidades` y las pruebas de alta con cuenta existente. |
| RE-T.19.1 | `D22CorreccionTest::testElTitularDeUnaCuentaNuevaTambienPuedeRechazar`, junto con `CuentasD2Test` para activación y consentimiento. |
| RE-T.2.4 | Tres casos nuevos en `D22CorreccionTest`: sin prueba nueva, Google inválido/ajeno y Google del titular; `GoogleTokenTest` cubre audiencia, vigencia y correo; `D22Google.test.cjs` comprueba otra solicitud, token nuevo y cancelación. |
| RE-T.2.5 — implementado, pendiente de revisión | `D22CorreccionTest`: crear/cambiar contraseña, recuperar por enlace, restablecer por administrador y confirmar correo revocan las otras sesiones; se conserva la sesión del titular y se comprueba el aviso. |
| Migración 04 y SQL real | `BaseV2MysqlTest::testMigracionD22ActualizaUnaBaseAnteriorDosVeces`, `testD22InvitacionesYVersionSesionEnElEsquemaReal` y `testElMigradorEstaAlDiaDosVecesSeguidas`: columnas/FK, datos conservados, repetición, UNION/orden, dos clínicas, roles y versión de sesión. |
| Hora del sistema | `BaseV2MysqlTest::testD22ZonaHorariaAlineaNowYDate`: Bogotá, conexión -05:00 y diferencia de hasta dos segundos. |
| Modal, invitaciones y validación | `D21Interfaz.test.cjs`, `D2ValidacionCuenta.test.cjs` y `UsuariosVistaTest`. |

### Diagramas y verificación

- Codex actualizó Modelos §9 (sesiones revocadas), §10 (login sin contraseña temporal), agregó §10.1 (alta de personal) y ajustó §13.3 (confirmación nueva de Google y cierre de sesiones).
- Los cuatro diagramas renderizaron a SVG con Mermaid CLI; no se hizo recorrido de pantallas de la aplicación con el navegador.
- `php vendor/bin/phpunit`: **499 pruebas, 2568 aserciones, verde; 24 omitidas de MySQL** al no definir su host.
- Suite completa con `ZOOKI_TEST_MYSQL_HOST`: **499 pruebas, 2876 aserciones, verde, sin omisiones**, en MariaDB temporal propia, puerto 3307 y base predeterminada `zooki_test_base_v2`.
- `node --test "tests/Frontend/**/*.test.cjs"`: **48 pruebas, verde**.
- PHP lint de los archivos de la entrega, Node check de los JS de Codex y `git diff --check`: sin errores.
- Mutación de Codex: omitir la confirmación Google hace fallar la prueba de sesión abierta sin prueba nueva; restaurada exactamente, vuelve a pasar.
- Mutaciones reportadas por Claude Code: quitar la comparación de versión rompe cuatro pruebas; priorizar el documento sobre el correo rompe su prueba de identidad cruzada.

### Pendientes para Claude y el usuario

1. **Riesgo de enumeración heredado:** `validar_cuenta_ajax` aún informa si un correo/documento existe, incluso con sesión (120 consultas/15 minutos), aunque el alta del personal ya no lo consulta.
   La decisión sobre esa ayuda pública de D2/D2.1 sigue pendiente; no se declara resuelta por D2.2.
2. **Limpieza heredada:** al borrar una cuenta nueva vencida también se borran invitaciones de otras clínicas sin su auditoría de vencimiento; una invitación vencida permanece en la lista hasta ejecutar la limpieza.
   Ambos puntos quedan expresamente para revisión, sin ampliar la implementación que Claude dejó.
3. Probar Google real y el diseño en escritorio/celular; las pruebas automáticas sustituyen la respuesta de Google.
4. Versiones, historial, revisión de documentos y PDF/Word se publican al cierre de M0, según el método acordado.
   D3 no se inició; no se hizo commit ni push.

### Pantallas para la prueba manual del usuario

- **Usuarios:** alta nueva, propietario existente, correo/documento cruzados; pendientes iguales en tarjetas/tabla; reenviar/cancelar; editar cliente activo/inactivo con el modal compartido; correo duplicado al editar.
- **Enlaces de correo:** invitación existente (aceptar/rechazar/vencido/usado), activación nueva (aceptar política o rechazar), confirmar correo nuevo y verificar registro; revisar hora y URLs de `logs/correos/`.
- **Login y contexto:** propietario que acepta invitación conserva su portal y elige también su clínica; restablecimiento por administrador nunca envía contraseña temporal.
- **Mi perfil, portal y cambio obligatorio:** crear primera contraseña exige otro Google del titular; cambiarla con otra sesión abierta cierra la otra y conserva la actual; errores debajo del campo.
- **Restablecimiento y Datos de acceso:** enlace de recuperación, cambio de correo/documento y cierre de otras sesiones al confirmar correo.

### D2.2 — Revisión (2026-10-09)

**Resultado: aprobada.** Claude (sesión de revisión) revisó el anexo, el diff y los diagramas. Suite PHP en una copia: 499 pruebas, 2568 aserciones, 24 saltadas de MySQL (el ejecutor reporta la parte MySQL en verde). JS: 48 pruebas en verde.

**Verificado contra el código y los diagramas.**

- Modelos §10.1 (nuevo) coincide con `UsuarioController::registrarAjax` e `InvitacionPersonal`: la persona existente no queda vinculada hasta aceptar; aceptar y rechazar solo por POST con CSRF y token con hash; aceptar conserva los demás roles; la respuesta al administrador es la misma exista o no la cuenta; la lista muestra solo lo que escribió el administrador; reenviar y cancelar quedan aislados por clínica. Se retiraron `verificar_documento_ajax` y `verificar_email_ajax`.
- RE-T.2.5 / Modelos §13.3: `usuarios.version_sesion` sube al cambiar la contraseña (perfil, portal, cambio obligatorio, recuperación, restablecimiento por el administrador y cambio de correo). `Security::autorizar` lo compara después de cargar los contextos, así que la comprobación corre en cada petición real. La sesión donde se hizo el cambio sigue abierta.
- Migración 04 repetible; `ZonaHoraria` en el arranque y `SET time_zone` en la conexión; los siete puntos de D2.1 quedaron hechos. §9, §10 y §13.3 se actualizaron.

**Decisiones del ejecutor que el usuario debe confirmar.**

1. La migración 04 agrega a `verificaciones_email`, además de `id_rol_vinculo`, cinco columnas con los datos escritos por el administrador (`nombre_invitado`, `tipo_documento_invitado`, `documento_invitado`, `email_invitado`, `telefono_invitado`). Sirven para que la lista muestre igual una invitación a una cuenta nueva y a una existente. Recomendación: aceptarlo.
2. Si el documento y el correo son de personas distintas, en vez del mensaje genérico **prevalece el correo** (se invita al titular del correo) y el administrador recibe la misma respuesta. No revela nada y está en §10.1. Recomendación: aceptarlo.

**Pendientes (para D3).**

| # | Tipo | Dónde | Qué pasa | Propuesta |
|---|---|---|---|---|
| 1 | Decisión | `IdentidadController::validar` | La ayuda al escribir todavía dice si un correo o un documento existen: sin sesión, 20 por IP; con sesión, 120 por persona. Desde D1 el registro con un correo existente ya no necesita decirlo (envía el enlace de vínculo). | Que la ayuda solo valide el formato y que el servidor responda al enviar sin revelar la cuenta. Pendiente de confirmar. |
| 2 | Defecto menor | `CuentaTitular::limpiarUna` | Al borrar una cuenta nueva vencida borra también las invitaciones de otras clínicas a esa cuenta, sin su auditoría. | Auditar «vencida» en cada clínica antes de borrar. |

**Prueba del usuario:** lista en la conversación del 2026-10-09; antes, `php scripts/migrar.php` para aplicar la 04 en la base local.

**Decisiones del usuario (2026-10-09) sobre la revisión de D2.2.** Se aceptan las cinco columnas `*_invitado` de la migración 04 y que prevalezca el correo cuando documento y correo son de personas distintas. Se aprueba la propuesta del pendiente 1: la ayuda al escribir solo valida el formato y el servidor responde al enviar sin revelar si la cuenta existe (va en D3).

**Hallazgo del usuario (2026-10-09), bloqueante para D3.** Correr `vendor/bin/phpunit` en su equipo le bloqueó la IP local. Causa: `phpunit.xml` no tiene bootstrap propio; varias pruebas (por ejemplo `SesionTest`, `RegistroGoogleTest`, `TelefonosTest`, `PoliticaDatosTest`) llegan a `Security::recordFailedLogin` o `checkVerificationLimit` sin inyectar el almacén de intentos, y `Security::conexion()` abre la base del `.env` (`zooki_v2_prueba`) y escribe en `intentos_login` con `REMOTE_ADDR` 127.0.0.1. Las pruebas no deben tocar nunca la base de trabajo (lección de `metodo.md` §10). Corrección: `tests/bootstrap.php` que deja el almacén en null por defecto y una guarda en `Database` que, bajo PHPUnit, se niega a conectar a otra base que no sea la de `ZOOKI_TEST_MYSQL_*`, con su prueba.

**Prueba del usuario de D2.2 (2026-10-09).** Pasó: invitación a una cuenta existente con la misma respuesta y la tarjeta con lo escrito; alta nueva «Pendiente de activación»; cierre de la otra sesión al cambiar la contraseña y al restablecer; «Datos de acceso»; mensaje de contraseña común; hora de Bogotá en `logs/correos/`. Para D3 de paso: al cambiar la contraseña en Mi perfil, el portal o el cambio obligatorio, confirmar con un aviso breve (toast de SweetAlert2), como pide `metodo.md` §8; hoy solo aparece un texto pequeño bajo el botón y el usuario no lo vio. Una invitación pendiente no se edita: se cancela y se invita de nuevo (aceptado).


## D3 — Alcance (registro y activación de clínicas)

Ejecutor: Codex (decisión del usuario, 2026-10-10). Diagrama: Modelos §12.1 (y §9 para el contexto al entrar).

**Decisiones del usuario (2026-10-10).**

1. **Correo que ya tiene cuenta:** la respuesta del registro es la misma para todos («Revisa tu correo para activar la clínica»). Si el correo ya tiene cuenta, se le envía «Confirma que quieres registrar <Clínica> con tu cuenta»; al confirmar, la clínica se activa y esa persona queda como administradora **conservando sus otros roles** (una sola cuenta, el mismo correo y la misma contraseña; elige el contexto al entrar, §9). La contraseña escrita en el formulario se ignora. Se ajusta RE-0.1.2 («un correo ya registrado se rechaza») y HU-0.1.
2. El formulario pide además **tipo y número de documento y teléfono** del administrador.
3. **Correos desechables:** lista en `config/dominios_desechables.txt` (un dominio por línea), mantenida en el repositorio.
4. **CAPTCHA en el registro de propietarios:** `process_register` verifica Turnstile en el servidor, como el registro de clínicas; se agrega un RE a HU-5.8.

**Antes, en la misma subetapa (pendientes de D2.2):** las pruebas no tocan `zooki_v2_prueba`; la ayuda al escribir solo valida el formato (decisión del usuario, 2026-10-09); aviso breve (toast) al cambiar la contraseña; auditoría de invitaciones de otras clínicas al limpiar cuentas pendientes.

**Decisión del usuario (2026-10-10, durante D3).** Si el correo ingresado es nuevo y el documento pertenece a una cuenta, se envía la confirmación al correo registrado del titular del documento. Se conservan su identidad, contraseña y vínculos, con la misma respuesta pública. Cuando correo y documento pertenecen a cuentas distintas, prevalece el correo, como en D2.2.

## Anexo D3 — Resultado

**Lo escribió Codex (2026-10-10). Entregada, pendiente de revisión.** Revisa la sesión de Claude (arquitecto); las pantallas las prueba el usuario.

### Qué quedó

- Bootstrap y guarda de conexión: las pruebas no leen `.env` ni abren la base de trabajo. `Database` exige configuración explícita de pruebas; los subprocesos heredan la protección. Los intentos usan el almacén inyectado o la sesión.
- Ayudas de cuenta solo de formato, con y sin sesión; respuestas iguales al registrar propietarios o solicitar cambio de correo. Documento duplicado abre soporte sin confirmar la existencia. Contraseñas con toast y actualización inmediata. Al borrar una alta de personal vencida, se audita cada invitación pendiente; si falla la auditoría, se conserva la cuenta.
- Registro público de clínicas con el diseño del login, validadores compartidos, NIT DIAN, lista de dominios desechables, Turnstile y límite diario atómico por IP real. NIT duplicado permite reportar soporte. Enlaces desde login y página pública.
- Identidad nueva inactiva con contraseña cifrada; identidad existente conservada y confirmación al titular, incluida la decisión sobre documento existente. Token cifrado de 24 horas y un solo uso; GET de lectura y POST con CSRF y consentimiento. Activación, plan gratuito, rol, catálogos y auditoría en una transacción.
- Limpieza horaria de pendientes sin borrar cuentas preexistentes ni cuentas nuevas con otros vínculos. Se cubren varias solicitudes para la misma cuenta nueva y clínicas cuyo enlace desapareció por otra limpieza. Se reutilizan tablas existentes; no hay migración. El MER describe los propósitos y el contador reutilizados.

### Trazabilidad y pruebas

| RE / regla | Evidencia |
|---|---|
| RE-0.1.1, RE-0.1.10, RE-T.19.1 | `RegistroClinicaTest::testSinConsentimientoNoSeCreaNada`, `testActivacionSinConsentimientoOVencidaNoModificaDatos`; `D3Avisos.test.cjs` comprueba campos, ausencia de Google y recursos externos. |
| RE-0.1.2, RE-0.1.7, RE-0.1.8, RN-G06 | `RegistroClinicaTest`: cuenta nueva inerte; existente conserva identidad/contraseña/roles; documento existente confirma a su titular; respuesta idéntica y correo correspondiente; NIT duplicado con soporte. |
| RE-0.1.3, RE-0.1.5 | `ValidadorClinicaTest`: dígitos DIAN válidos/incorrectos, normalización y dominios desechables en ambos correos. Algoritmo contrastado con la Resolución DIAN 0457 de 2008. |
| RE-0.1.4, RE-5.8.6 | `RegistroClinicaTest`: CAPTCHA ausente, inválido y válido, tanto clínica como propietario; no se crea nada si falla. `D3Avisos.test.cjs`: cada formulario restablece su propio widget sin duplicarlo. |
| RE-0.1.6, RN-G15 | `RegistroClinicaTest::testLimiteTresPorDiaEIpNoSeEvadeConSesionNuevaNiConXForwardedFor`; también cuarto registro rechazado en `BaseV2MysqlTest`. |
| RE-0.1.9, RE-0.2.1 a RE-0.2.5, RNF-11 | `RegistroClinicaTest`: auditoría, GET sin activar, POST sin CSRF rechazado, token alterado/vencido/usado, plan/rol/contextos, catálogos 6/7/15/11/15 y relaciones por especie; fallo intermedio revierte token, consentimiento y copias; acceso a mascota de otra clínica da 403. `BaseV2MysqlTest::testD3RegistroActivacionYRollbackEnElEsquemaReal` verifica FK, rollback y limpieza reales. |
| RE-T.13.2, RE-5.8.2, RE-5.8.3, RN-G23, RN-G24 | `RegistroClinicaTest`: ayuda idéntica con/sin sesión y autorregistro genérico; `CuentasD2Test`: cambio de correo idéntico sin enviar al otro titular, documento duplicado genérico y caso de soporte. Pruebas JS de validación sin existencia. |
| RE-T.7.5, preferencias de avisos | `CuentasD2Test`: vencimiento auditado en todas las clínicas antes de eliminar; fallo de auditoría conserva cuenta/enlace. `D3Avisos.test.cjs`: toasts de Mi perfil, portal y cambio obligatorio, y aviso tras redirigir. |
| Protección de la base de trabajo | `DatabasePruebasTest`: rechaza conexión implícita y configuración de la base de trabajo; intentos del bootstrap sin conexión. `BaseV2MysqlTest` comprueba la conexión explícita a `zooki_test_base_v2`. |

### Verificación y diagramas

- Suite PHP sin MySQL: **526 pruebas, 2707 aserciones; 25 omitidas** por requerir MySQL.
- Suite completa con `ZOOKI_TEST_MYSQL_HOST`: **526 pruebas, 3036 aserciones, ninguna omitida**, contra `zooki_test_base_v2` en MariaDB 10.4 de una instancia temporal propia (puerto 3308). No se usó la base de trabajo; MySQL 8 queda al CI.
- `node --test "tests/Frontend/**/*.test.cjs"`: **57 en verde**, sin omisiones. `git diff --check` en verde; sintaxis PHP comprobada. Ninguna vista nueva contiene JS/CSS en línea.
- Modelos **§11, §12.1, §13.2 y §13.3** actualizados y sus cuatro Mermaid renderizados; §9 se conserva, con pruebas de contextos nuevos y existentes. HU/RE/matriz y conteos actualizados: **420 RE**, nuevo RE-5.8.6; sin subir revisión.

### Pantallas para el usuario y pendientes

1. **Login y página pública:** abrir «Registra tu clínica»; comprobar diseño de escritorio/celular y mensajes debajo del campo mientras escribe. En registro del propietario, todos los casos aceptados muestran la misma espera de correo; después de un error se puede resolver de nuevo el CAPTCHA.
2. **Registro de clínica:** NIT válido de ejemplo `900123456-8`, DV incorrecto y correo desechable; consentimiento y CAPTCHA obligatorios. Datos nuevos generan correo en `logs/correos/` con `MAIL_MODO=archivo`; el cuarto registro diario de la misma IP se rechaza. NIT repetido ofrece reportar soporte, sin revelar al administrador.
3. **Activación:** abrir el correo no activa; el botón confirma. Después se entra como administrador de esa clínica y aparecen sus catálogos/horarios. Reabrir un enlace usado no activa de nuevo.
4. **Cuenta existente:** usar su correo con otros datos/contraseña en el formulario; confirmar desde el correo del titular; entrar con la contraseña original y elegir entre sus contextos. Repetir con correo nuevo y documento existente: el correo llega al titular del documento.
5. **Mi perfil y portal → Datos de acceso:** no revelan existencia al escribir ni al enviar; documento duplicado responde genérico y abre soporte. **Contraseña en Mi perfil, portal y cambio obligatorio:** aviso breve visible, sin retrasar la actualización o redirección.

Pendientes: revisión de Claude y prueba visual del usuario. Gestión de casos, panel de IP y límites del plan pertenecen a E; no se implementaron. Versión, historial, revisión documental y PDF/Word se publican al cerrar M0, conforme a `agentes/metodo.md`. El QR del portal conserva el pendiente de D1/RE-5.8.4; esta entrega no declara cerrada toda HU-5.8. No se hizo commit ni push.


### D3 — Revisión (2026-10-10)

**Resultado: aprobada.** Claude (sesión de revisión) revisó el anexo, el diff y Modelos §12.1. Suite PHP en una copia sin `.env`: 526 pruebas, 2707 aserciones, 25 saltadas de MySQL (el ejecutor reporta la parte MySQL en verde). JS: 57 pruebas en verde.

**Verificado.**

- **Base de trabajo protegida:** `tests/bootstrap.php` deja el almacén de intentos en null y marca el entorno de pruebas; `Database` se niega a conectar bajo PHPUnit salvo a `zooki_test_base_v2` con `ZOOKI_TEST_MYSQL_*`. En la copia sin `.env` la suite pasó sin intentar ninguna conexión.
- **Registro (§12.1):** NIT con el módulo 11 de la DIAN (comprobado a mano con `900123456-8`), dominios desechables, Turnstile en el servidor, límite de 3 por día e IP real con fila bloqueada, NIT duplicado con reporte a soporte, misma respuesta para correo nuevo o existente. Con cuenta existente no se toca la cuenta hasta confirmar; al confirmar, administrador con sus otros roles.
- **Activación:** GET de solo lectura, POST con CSRF y consentimiento; en una transacción: enlace consumido, clínica activa con plan Gratuito, rol, consentimiento, catálogos con `InicializadorClinica` y auditoría.
- **Limpieza:** nunca borra una cuenta que ya existía ni una nueva con otros vínculos.
- **Pendientes de D2.2:** ayuda solo de formato, toasts y auditoría al limpiar quedaron hechos. RE-5.8.6 nuevo (Turnstile en el registro de propietarios).

**Hallazgos menores (para E).**

1. `reportar_nit` es público y solo lo frena Turnstile: sin límite por IP se pueden crear casos de soporte en cantidad. Darle un límite como el del registro al construir la gestión de casos (E).
2. Un NIT queda reservado mientras su registro está pendiente (hasta 24 horas). Alguien podría registrar el NIT de otra clínica sin verificar; el titular lo reporta (RN-010) y la limpieza lo libera. Aceptable; anotar en E.


**Prueba del usuario de D3 (2026-10-10).** Bloqueante: la pantalla «Registra tu clínica» se monta sobre la imagen decorativa del login y casi no se puede usar (la columna del formulario queda debajo del panel de animales); debe verse y adaptarse igual que el login. Regla nueva del usuario: los campos no solo se validan, también **restringen** lo que se escribe (`metodo.md` §8, AGENTS.md). Va en **D3.1**.

**Hallazgo del usuario (2026-10-10), para D3.1:** el titular de una cuenta existente que registra una clínica escribe una contraseña en el formulario y puede creer que entrará con ella. Se aclara en tres lugares, sin revelar nada a quien no es el titular: una ayuda fija junto a la contraseña del formulario («Si ya tienes cuenta en Zooki, entrarás con tu contraseña de siempre»), el correo de confirmación de la cuenta existente («la contraseña del formulario no se usó») y la página de confirmación (enlace a «¿Olvidaste tu contraseña?»).

## Anexo D3.1 — Resultado

**Lo escribió Codex (2026-10-10). Entregada, pendiente de revisión.** Revisa la sesión de Claude (arquitecto); las pantallas las prueba el usuario.

**Qué:** login y registro de clínica comparten `cabecera_acceso.php` y las mismas hojas (incluida `styles.css`). Registro conserva `login-container`, `login-form-area` y `login-hero` con la altura y el desplazamiento del login; su CSS solo añade el formulario y la cuadrícula, de dos columnas a una por debajo de 768 px. No se cambió el diseño del login. Activación conserva la tarjeta de las páginas de enlaces, que ya carga sus estilos y no tiene panel de imágenes.

**Entrada:** atributos compartidos de `ValidadorCuenta`, `ValidadorClinica` y `ValidadorTelefono`; captura en `interacciones.js` antes de validar, también al pegar. Documento 15 dígitos, NIT solo dígitos con un guion y un DV, persona con letras/espacios/apóstrofo/guion; clínica y dirección con letras, números y signos `.,'’&()+/#:-`; correos sin espacios, máximo 255; teléfonos según su helper. Espacios repetidos y extremos normalizados; el servidor rechaza caracteres ilegales y valores demasiado largos. Contraseñas conservan sus espacios y símbolos, hasta 72 bytes, sin alterar el secreto. Se conserva cursor, selección y deshacer nativos en entradas válidas; `type=email` conserva su edición nativa. No se duplican manejadores al cargar el componente desde un layout y una pantalla.

**Textos:** ayuda fija de contraseña para todos en el formulario; solo en el correo y el enlace secreto del titular de una cuenta existente se explica que la contraseña escrita no se usó. GET y POST de confirmación recuerdan la contraseña habitual y la elección de clínica. «¿Olvidaste tu contraseña?» abre el recuperador existente del login; la respuesta pública sigue idéntica entre alta nueva y existente.

| RE / regla | Prueba |
|---|---|
| RE-0.1.1, RNF-16; pantalla reportada por el usuario | `RegistroClinicaTest::testD31VistaRegistroComparteCabeceraYEstructuraDelLogin`: render real, hojas compartidas, formulario y hero como hermanos, sin CSS que sustituya el contenedor, corte de 767 px. Diseño visual pendiente del usuario. |
| Restricción aprobada, RE-0.1.2/3/5 y RE-T.13.2 | `D31Entrada.test.cjs`: escritura/pegado, letras rechazadas, guion único y un DV, máximos, Unicode, espacios, selección, correo sin API de cursor, contraseña en bytes y carga repetida. `D31SaneamientoTest`: reglas del servidor y atributos con los mismos máximos. |
| Saneamiento en alta, identidad y datos de acceso | `RegistroClinicaTest::testD31SaneamientoSeGuardaYEntradaIlegalNoCreaCuenta`: datos normalizados persistidos y nombre ilegal rechazado; `D31SaneamientoTest` y suites existentes de propietarios, Usuarios, completar perfil y cambio de identidad. |
| RE-0.1.2/7/8, RN-G06; aclaración aprobada sobre contraseña | `RegistroClinicaTest`: respuesta pública idéntica, textos del correo; `testD31ConfirmacionExplicaPasswordOriginalAntesYDespuesDelPost`: textos en GET/POST y contraseña original intacta. |

**Verificación:** PHP completo **532 pruebas / 2785 aserciones**, 25 omitidas sin MySQL. Con `ZOOKI_TEST_MYSQL_HOST`, **532 / 3114**, ninguna omitida, en `zooki_test_base_v2` de MariaDB 10.4 temporal propia; MySQL 8 queda al CI. JS **68 en verde**. Sintaxis de los PHP modificados y `git diff --check` correctos. Sin migración ni JS/CSS en línea nuevos. **Modelos §12.1 no cambia:** misma selección del titular, confirmación por POST y activación atómica; solo se aclararon textos y presentación.

**Pantallas para el usuario (escritorio ≥1024 px, tablet 768–1023 px y celular <768 px):**

1. Login → «Registra tu clínica»: poder escribir y desplazarse sin imágenes encima; dos columnas en escritorio/tablet, una en celular. Ver la ayuda fija debajo de contraseña. Documento/NIT rechazan letras; probar pegado largo, NIT con dos guiones, nombres con ñ/tildes y espacios, correo con espacios y teléfonos.
2. Correo de cuenta existente → confirmación → activar: aviso de contraseña habitual antes y después; recuperar contraseña con el enlace; entrar con la contraseña original y seleccionar la clínica. Cuenta nueva conserva la misma respuesta pública del formulario.
3. Login → registro del propietario y completar perfil: mismos filtros y máximos, con avisos debajo de los campos. Pacientes → alta/edición de propietario: mismos filtros, conservando su diseño y los campos de identidad bloqueados.
4. Usuarios → modal; Mi perfil y portal → Datos de acceso: nombres, documentos, correos y teléfonos restringidos; valores compartidos siguen en solo lectura según C1.7.
5. Mi perfil/portal → contraseña; cambio obligatorio, activación de personal y restablecimiento: longitud restringida, requisitos al escribir y avisos debajo del campo; espacios y símbolos de la contraseña se conservan.

**Pendientes:** revisión de Claude y prueba visual del usuario. Restricciones de otros módulos anotadas en `specs/pulido-interfaz.md`. E y pulido general no se iniciaron. Versión, historial, revisión documental y exportaciones siguen diferidos al cierre de M0 por el método acordado. No se leyó ni escribió `.env`, no se tocó la base de trabajo, no se hizo commit ni push.


### D3.1 — Revisión (2026-10-10)

**Resultado: aprobada** (código; la pantalla la confirma el usuario). Claude revisó el anexo y el diff (commit `391c73d`). PHP en una copia sin `.env`: 532 pruebas, 2785 aserciones, 25 saltadas de MySQL. JS: 68 en verde. `registro_clinica.php` usa la misma cabecera (`cabecera_acceso.php`) y la estructura del login (`login-container`, `login-form-area`, `login-hero`); los atributos de restricción salen de los validadores del servidor (`ValidadorCuenta`, `ValidadorClinica`, `ValidadorTelefono`), así el HTML, el JS y el servidor usan el mismo límite. Modelos §12.1 no cambia.


## E — Alcance (aprobado para E1, 2026-10-10)

Se parte en **E1** (panel del super-administrador, HU-0.3 sin RE-0.3.6) y **E2** (límites del plan, HU-0.4).

**Decisiones del usuario (2026-10-10).**

1. El super-administrador **no** accede a historias, consultas ni pacientes de una clínica, ni siquiera «para soporte»: el panel solo muestra datos de la clínica, su plan y su suscripción (RE-0.3.3 se ajusta sin subir revisión).
2. «Citas por mes» cuenta las citas con fecha en ese mes calendario, sin las canceladas. Las urgencias rojas nunca se bloquean (RN-420), pero sí cuentan.
3. Cada subetapa deja al usuario solo 3 a 5 pruebas de navegador, las de lo nuevo y riesgoso (`metodo.md` §3).
4. El NIT se pide en dos casillas: «NIT» (solo números) y «DV» (un dígito, «está en tu RUT después del guion»). Hoy, sin guion, el último dígito se toma como DV y una cédula puede pasar por casualidad.

**Pendiente al preparar E1, ya resuelto:** los valores originales eran 5 mascotas, 30 citas por mes y 2 cuentas de personal. La decisión posterior del 2026-10-10 fija 50/100/2; viven en `planes`, sin topes fijos en el código.

**También en E1:** casos de soporte (NIT y documento duplicados: ver y marcar resuelto), IP bloqueadas con «Desbloquear» y «Enviar aviso» (decisiones del 2026-10-09), límite por IP para `reportar_nit` y los hallazgos menores de D3.

**Decisiones del usuario sobre el plan gratuito (2026-10-10).** 2 cuentas de personal, 50 mascotas vinculadas y 100 citas por mes (confirmadas tras la prueba de E1). Los propietarios no tienen límite. Los límites ya viven en `planes` (`limite_mascotas`, `limite_citas_mes`, `limite_personal`; NULL = sin límite): E2 los ajusta con una migración que solo cambie el plan Gratuito si conserva los valores anteriores, y actualiza RN-005 y RE-0.4.7. Los precios no se tocan por ahora.

## Anexo E1 — Resultado

**Lo escribió Codex.** Revisa la sesión de Claude (arquitecto).
**Estado:** entregada, pendiente de revisión, 2026-10-10.

**Qué.** Panel `plataforma_inicio` con tarjetas por defecto, tabla recordada en el navegador, búsqueda y estado; datos de clínica, plan, suscripción, fecha y administrador, incluyendo los NIT pendientes de verificación. Los modelos de plataforma solo consultan metadatos y exigen una cuenta super-administradora activa con el contexto de plataforma. Cambios con motivo y auditoría en transacción; rollback si no se puede auditar. Suspendida o dada de baja pierde el contexto en la siguiente petición; reactivar devuelve la opción en el selector. Nunca se borran los registros de una clínica dada de baja.

Casos de soporte con resolución o descarte y nota auditada. IP bloqueadas vigentes con cuentas de `LOGIN_FAIL` dentro de la ventana y sus clínicas; cuenta no reconocida se muestra como tal. Desbloqueo central válido también con la cookie anterior; conserva el CAPTCHA por cuenta. Aviso únicamente al administrador elegido y comprobado en servidor, con texto editable, auditoría previa y resultado del envío. Sin envío automático. Registro y reporte usan NIT/DV separados; reporte limitado a cinco por día e IP real, con el mismo contador transaccional del registro.

**RE y pruebas.**

| RE/RN | Evidencia |
|---|---|
| RE-0.3.1/4, RN-G13/G14 | `PlataformaTest::testListaClinicasActivaPendienteSuspendidaYFiltrosSinDatosClinicos`, `testRolesYPostCsrfEnTodasLasAccionesDelPanel`, `testElModeloRechazaContextoClinicoAunqueSeLlameDirecto`; tarjetas y preferencia en `E1Plataforma.test.cjs`. |
| RE-0.3.2 | `PlataformaTest::testPlanSuscripcionMotivoYAuditoriaAtomica`; inserta o actualiza la suscripción y el plan juntos sin duplicar ni tocar otra clínica. Aplicación de límites: HU-0.4, E2. |
| RE-0.3.3, RN-004 | `AutorizacionRolTest::testElSuperAdministradorSoloTieneLaPlataforma`, denegación de acciones clínicas en `PlataformaTest`, `testVistaEscapaDatosYNoOfreceEnlacesClinicos`. RE, HU, RN y ERS coherentes sin excepción de soporte ni cambio de revisión. |
| RE-0.3.5, RN-012/G08 | `PlataformaTest::testSuspenderRetiraContextoYReactivarPermiteElegirloSinBorrarDatos`, `testSinMotivoNiNotaNoCambiaEstadoOCasoYFalloDeAuditoriaNoDesbloquea`; no se activa un registro pendiente sin verificación. |
| RN-010, soporte | `PlataformaTest::testCasosSeResuelvenODescartanConNotaAuditada`; sin nota no cambia y el fallo de auditoría revierte. |
| RN-G15, IP y avisos | `PlataformaTest::testIpMuestraCuentasClinicasYSoloEnviaAlAdministradorElegido`, `testDesbloquearValeConLaMismaCookieYConservaContadorDeCuenta`, `testAvisoFallidoSeAuditaYNuncaSeEnviaSinAuditoria`; JS comprueba cancelación, envío explícito y doble clic. |
| RE-0.1.3 y hallazgo D3 | `PlataformaValidacionTest::testNitYDvSeValidanSeparadosSinAdivinarUltimoDigito`, `RegistroClinicaTest::testRegistroYReporteRechazanDvIncorrectoYNoExtraenDelNit`, `testReportarNitPermiteCincoPorDiaEIpSinEvadirConOtraSesion`; restricción al escribir/pegar en `D31Entrada.test.cjs`. |
| MySQL real | `BaseV2MysqlTest::testE1PanelPlataformaEnElEsquemaReal`: estados ENUM, actualización de suscripción, contexto suspendido, cierre de casos, destinatario, desbloqueo, cinco reportes y rollback sin auditoría. |

**Diagrama.** Nuevo Modelos §12.1.1; §9 enlaza al panel y §12.1 refleja NIT/DV y el límite de reportes. Mermaid renderizado con CLI en `scratch/e1-diagrama-1.svg` (material de verificación, fuera de git).

**Verificación.** PHP completo: 548 pruebas, 2925 aserciones, 26 omitidas de MySQL. Con `ZOOKI_TEST_MYSQL_HOST` en MariaDB temporal propio, puerto 3308 y base predeterminada `zooki_test_base_v2`: 548 pruebas, 3267 aserciones, ninguna omitida. JS completo: 75 en verde. Ninguna prueba accedió a la base de trabajo. Sin migración, cambios de precios ni nuevos valores del plan; versión y exportaciones se cierran con M0 según `metodo.md` §4.

**Confirmaciones.** Suspender, dar de baja, cancelar suscripción, cerrar caso, desbloquear IP y enviar aviso conservan confirmación. Reactivar o guardar un plan/suscripción reversible usa aviso breve; el panel se actualiza antes de mostrarlo. Validación junto al campo, restricción de entrada y máximos compartidos con el servidor; sin JS/CSS en línea.

**Pendientes.** Revisión de Claude y las cinco pruebas de navegador de `agentes/prueba-manual.md`, escritorio y celular. Un correo y la base no forman una transacción única: queda auditada la solicitud antes de enviar y su resultado; las pruebas usan correo simulado. HU-0.4/E2, mora, precios y RE-0.3.6 quedan fuera. No se empezó E2.


### E1 — Revisión (2026-10-10)

**Resultado: aprobada.** Claude revisó el anexo y el diff. PHP en una copia: 548 pruebas, 2925 aserciones, 26 saltadas de MySQL. JS: 75 en verde. Sin JS ni CSS en línea en las vistas nuevas.

**Verificado.** `Plataforma` exige en cada método el contexto de plataforma, el rol de super-administrador y la cuenta activa con `es_super_admin = 1`, y solo consulta metadatos (sin mascotas, historias ni citas). Las acciones del panel están en la matriz solo para la plataforma y exigen POST con CSRF. Los cambios de plan, suscripción, estado y casos piden motivo o nota y se auditan en la misma transacción. Desbloquear borra solo `ip:<ip>` de `intentos_login` (el contador por cuenta se conserva). El aviso solo va a un administrador que el servidor saca de las cuentas que fallaron desde esa IP (nunca un correo del navegador) y queda auditado antes y después del envío. NIT y DV en casillas separadas; `reportar_nit` con 5 por día e IP. Diagrama nuevo §12.1.1.

**Anotado.** `Security::checkRateLimit` ya no usa el contador de la sesión cuando hay almacén en la base (para que el desbloqueo central valga con la cookie anterior); la sesión queda solo de respaldo sin base. Es coherente con RE-T.13.1 (el límite se cuenta del lado del servidor).

**Para E2 de paso (2026-10-10):** `scripts/dev/datos_prueba.php` se niega a correr si la base local tiene clínicas que no son las de prueba; desde D3 el usuario registra clínicas en su base local y queda sin poder regenerar los datos. Como `EntornoLocal` ya garantiza que la base es local, el script debe avisar qué clínicas ajenas encontró y seguir sin tocarlas.

**Prueba del usuario de E1 (2026-10-10).** Pasó: IP bloqueada, aviso y desbloqueo. Falla: no pudo suspender Clínica Norte (pendiente el mensaje exacto para diagnosticar). Pedido: el panel del super-administrador debe heredar los estilos de las vistas administrativas (`views/admin/layout.php`, tarjetas, tabla, modales y botones), sin hoja propia que rehaga lo que ya existe. Se mantiene el NIT en dos casillas.

**Decisión del usuario (2026-10-10):** el plan gratuito queda en **100 citas por mes**, 50 mascotas vinculadas y 2 cuentas de personal (va en E2).

**Prueba del usuario de E1, detalle (2026-10-10).** Suspender: al pulsar el botón del modal (con motivo) no pasa nada, ni mensaje ni envío. Las confirmaciones de SweetAlert en acciones sencillas cansan: solo para lo grave (regla nueva en `metodo.md` §8 y AGENTS.md). El NIT duplicado solo se avisa al enviar: debe avisarse en el campo mientras se escribe, con el reporte a soporte ahí (el NIT no es un dato privado; RE-0.1.2 ya permite decirlo). El NIT en dos casillas se mantiene.

## E2 — Alcance acordado

1. Corregir las cinco observaciones de E1: suspensión desde el modal, confirmación solo al dar de baja, componentes administrativos compartidos, NIT duplicado avisado junto al campo y datos de prueba que conservan clínicas ajenas locales.
2. Gratuito 2/50/100, migración 05 repetible y sin sobrescribir límites personalizados; profesional NULL. Un helper calcula el uso y serializa comprobación y alta por clínica; sin suscripción activa y al día se aplica el gratuito.
3. Crear o vincular mascotas (personal/portal), crear citas del mes de su fecha sin canceladas e invitar personal. Personal activo y pendientes vigentes cuentan por persona, incluido el administrador; el propietario global sin clínica no consume cupo.
4. Excepción roja en el punto de entrada del servidor, antes del control del plan; la clasificación completa de triage se integra en los módulos 2/4. Bloqueos auditados; mensaje del límite al personal y neutral al propietario, con aviso interno a su clínica.
5. Plataforma muestra únicamente números de uso/límite. Actualizar HU, RE, RN, Ficha Técnica y Modelos sin subir revisión; verificación PHP, MariaDB temporal y JS; cuatro pruebas de navegador para el usuario.
6. Fuera: HU-0.5 (mora/congelación), precios, RE-0.3.6, pulido general y F.

**Decisiones del usuario durante E2 (2026-10-11).**

- El mismo NIT se reconoce con o sin guion. El script normaliza únicamente las clínicas de prueba antiguas reconocidas por nombre y NIT, conserva sus identificadores y se detiene antes de modificar datos si el NIT pertenece a otra clínica local. Las demás clínicas ajenas se avisan y conservan.
- Reactivar personal también requiere cupo, tanto por el botón de estado como por el modal. Una invitación vigente conserva su reserva; una vencida necesita cupo al reenviarse. Los vínculos y datos existentes se conservan.

## Anexo E2 — Resultado

**Lo escribió Codex.** Revisa la sesión de Claude (arquitecto).
**Estado:** entregada, pendiente de revisión, 2026-10-11.

**Qué.** `LimitePlan` lee los límites desde `planes`, la última suscripción y el uso de cada clínica; bloquea su fila durante comprobación y alta. Aplica los límites en mascotas, citas e invitaciones, y la reactivación aprobada por el usuario. El rollback conserva la auditoría del intento y el aviso interno al administrador cuando procede del portal. Los pendientes vigentes reservan cupo una sola vez; reenviar uno vencido requiere una reserva nueva. La migración 05 cambia solo el gratuito que conserve 2/5/30; la semilla nueva usa 2/50/100 y no sobrescribe configuraciones. No cambió el MER ni los precios.

**Correcciones de E1.** El modal nativo ocultaba la confirmación de SweetAlert2 detrás de su capa superior, dejando el envío esperando. Ahora usa `users-modal` y las hojas/componentes administrativos, con prueba de clic → motivo → submit → POST. `validacion-cuenta.js` no intercepta estos formularios. Solo **dar de baja** confirma; suspensión, reactivación, plan/suscripción, cierre de caso, desbloqueo y aviso redactado se ejecutan directamente y actualizan antes del toast, por ser reversibles o decisiones ya expresadas en el formulario. El NIT válido se comprueba mientras se escribe con límite por IP, aviso junto al campo y reporte a soporte. El servidor mantiene la comprobación final, también para NIT antiguos con guion. El script conserva clínicas ajenas y protege conflictos antes de regenerar claves.

| RE / regla | Evidencia |
|---|---|
| RE-0.4.1/2/4/7 | `LimitePlanTest`: borde 2/50/100, activos y pendientes sin duplicación, reenvío, reactivación por ambos endpoints, edición al límite, cancelación y cambio de mes; profesional NULL y suscripción no al día. `BaseV2MysqlTest::testE2CuotasYBloqueoDeClinicaEnElEsquemaReal`: exclusión entre conexiones. |
| RE-0.4.3/5, RE-5.3.5 | `LimitePlanTest` y los dos casos E2 de `PortalAgendaTest`: mensaje neutral, aviso solo a la clínica correspondiente, bloqueo al vincular una existente y rollback completo sin dejar el vínculo. |
| RE-0.4.6, RN-420 | `LimitePlanTest::testProfesionalSinLimiteMoraYUrgenciaRojaConservanElUso` y prueba MySQL: cita roja evaluada en servidor se registra y cuenta al límite/con mora; enviar `prioridad=rojo` por la entrada ordinaria no evita el límite. Integración con triage: pendiente del módulo de agenda. |
| RE-0.3.1/3, RN-004/G13 | `PlataformaTest`: números de uso/límite por clínica sin datos clínicos individuales; permisos clínicos siguen denegados al super-administrador. |
| RE-0.1.2 | `RegistroClinicaTest`: consulta pública sin identidad, límite por IP aun con sesión y rechazo de NIT antiguo duplicado. `D2ValidacionCuenta.test.cjs`: debounce, aviso y reporte junto al campo; se ocultan al editar. |
| Migración y script | `BaseV2MysqlTest`: migración dos veces, límites personalizados conservados y ejecución real del script con clínica ajena intacta y Norte antigua normalizada sin duplicación. `DatosPruebaClinicasTest`: conflictos con/sin guion y dos filas equivalentes se rechazan sin modificar datos. |

**Diagramas.** Modelos §12.1 (NIT en tiempo real), §12.1.1 (componentes, agregados y solo baja confirmada), §12.2 (transacción, límites, reactivación y excepción roja). Los tres renderizados con Mermaid CLI; SVG de verificación en `scratch/e2-diagrama-1.svg` a `-3.svg`, fuera de git.

**Verificación.** `vendor/bin/phpunit`: 566 pruebas, 3004 aserciones, 29 MySQL omitidas sin variable; con `ZOOKI_TEST_MYSQL_HOST`: 566 pruebas, 3363 aserciones, ninguna omitida. `node --test "tests/Frontend/**/*.test.cjs"`: 78/78. MariaDB únicamente con la base predeterminada `zooki_test_base_v2` en una instancia temporal propia; no se accedió a la base de trabajo ni a sus contraseñas. `git diff --check` y vistas tocadas sin JS/CSS en línea. Pruebas manuales: cuatro en `agentes/prueba-manual.md`.

**Pendientes.** Revisión de Claude y prueba del usuario en escritorio/celular. Todavía no existe el motor de triage: `Cita::registrarEvaluada` recibe la clasificación futura del servidor; las rutas actuales registran citas ordinarias verdes. No se afirma haber integrado la urgencia presencial completa. El script de desarrollo puede conservar tres personas por clínica, por encima del nuevo gratuito; son datos de prueba existentes, no se eliminan. Versión, historial y descargas se publican al cierre de M0 (`metodo.md` §4). No se empezó F.

### E2 — Revisión (2026-10-11)

**Revisó:** Claude (arquitecto). **Resultado: aprobada**, pendiente de las cuatro pruebas del usuario.

**Verificado.**

- Suites: PHP 566 pruebas, 3004 aserciones, 29 MySQL omitidas (copia limpia del repositorio, sin `.env`); JS 78/78 en el equipo del usuario.
- Migración 05: `UPDATE` condicionado a 2/5/30, repetible y sin pisar límites personalizados. La semilla nueva trae 2/50/100.
- `helpers/LimitePlan.php`: lee los límites de `planes` y de la última suscripción (si no está activa/al día, cae al Gratuito), bloquea la fila de la clínica con `FOR UPDATE` durante comprobación y alta, y audita el intento bloqueado en una transacción aparte para que el rollback de negocio no lo borre. Los pendientes de personal (activación/invitación vigentes) cuentan una sola vez.
- RN-420: la excepción roja solo entra por `Cita::registrarEvaluada`; enviar `prioridad=rojo` por la entrada ordinaria no salta el límite (cubierto por prueba).
- Diagramas: Modelos §12.1, §12.1.1 y §12.2 coinciden con el código (NIT en tiempo real, solo «dar de baja» confirma, transacción con bloqueo y excepción roja).
- Vistas de plataforma sin JS/CSS en línea; el modal usa `users-modal` y los componentes administrativos, como pidió el usuario.

**Observaciones (no bloquean).**

1. El motor de triage no existe todavía: la integración real de urgencias queda para el módulo de agenda (2/4). Ya está anotado como pendiente.
2. Las clínicas de prueba del script conservan tres personas, por encima del gratuito (2). Para probar no estorba: la prueba 3 ya lo contempla desactivando a Beto. Al cierre de M0 conviene que el script deje las clínicas de prueba en Profesional o con dos personas.
3. Deuda técnica conocida: `AuthController` ronda las 900 líneas; partirlo cuando se toque de nuevo, no en M0.

### E2 — Prueba del usuario (2026-10-11)

Las cuatro pruebas de `prueba-manual.md` pasaron (escritorio y celular). **E2 cerrada**, y con ella la etapa E.

**Enlace de Fabio «no disponible» (pendiente desde D2.2), diagnosticado.** No es un fallo. Ana envió la invitación dos veces con 9 segundos de diferencia (id 10 a las 15:45:14 e id 11 a las 15:45:23 del 2026-10-09). La segunda reemplazó a la primera y la marcó usada, como debe ser; Fabio abrió el enlace del primer correo (`id=10`). El del segundo (`id=11`) sigue vigente hasta el 2026-10-12 15:45. Lo que sí falta es decirlo: «no disponible» no le dice a la persona que hay un correo más reciente. Se corrige en F0.

**Decisiones del usuario (2026-10-11).**

- M0 sale a producción como **`2.0.0-alpha.1`**. Cada módulo siguiente sube `alpha.N`; `2.0.0` queda para cuando estén las 28 historias de la entrega v2.0 (plan de entregas de HistoriasUsuario).
- Entran en F0: mensaje propio para una invitación reemplazada por otra más reciente, y las clínicas de prueba de `datos_prueba.php` en plan Profesional (sus tres personas ya no chocan con el gratuito).

## F0 — Alcance (preparación del corte, 2026-10-11)

**Escribe:** Claude Code (decisión del usuario, 2026-10-11; Codex retoma después). **Revisa:** Claude (arquitecto). F se parte en dos: **F0** deja el repositorio listo para producción y escribe la guía exacta del corte; **F1** es el corte mismo, que ejecuta el usuario con esa guía (la tabla de etapas ya lo asigna así). F0 no toca producción.

1. **Blindaje de Apache (B.5).** Solo `public/` se sirve. Las URL públicas actuales no cambian (`/`, `?action=`, el portal de documentación y los archivos por `ver_archivo.php`). Verificar con una imagen local si hay Docker; si no, dejar la comprobación en la guía.
2. **`.dockerignore`.** Fuera de la imagen: `.env`, `.git`, `tests`, `scratch`, `logs`, `Claude outputs`, `node_modules` y lo que no use la aplicación en ejecución. Se conserva lo que necesitan el portal de documentación y el indexado de Algolia.
3. **IP real detrás de nginx → Traefik → Apache (RN-G15).** `.env.example` y la guía explican `TRUSTED_PROXIES` con el rango de `dokploy-network` y qué cabecera debe pasar nginx (`X-Forwarded-For`). Prueba con la cadena de dos saltos.
4. **Invitación reemplazada.** Un enlace válido (token correcto) de una invitación que fue reemplazada por otra vigente para la misma persona y clínica muestra «Esta invitación se reemplazó por una más reciente. Usa el enlace del último correo». Sin token correcto, el mensaje genérico de siempre (no se revela nada).
5. **Clínicas de prueba en Profesional** con suscripción activa, solo en `scripts/dev/`.
6. **Cierre documental de M0.** Versión `2.0.0-alpha.1` en `config/App.php` y donde se muestre; entrada en `HistorialVersiones.md` escrita para quien usa el sistema; estado de las HU y RE cerrados en M0 y nota de RE-0.3.6; la revisión nueva de cada documento tocado, una sola vez (`metodo.md` §4); descargas del portal regeneradas.
7. **Tabla de trazabilidad (§2)** con la evidencia de cada RE y la suite completa en verde, para marcar la casilla pendiente de §7.
8. **Guía del corte (`specs/M0-F-corte-produccion.md`).** Comandos exactos, en orden, separando los de PowerShell y los del VPS: respaldo de la base y de `uploads` y comprobación de que se pueden leer; integración `v2/m0` → `main` y tag (los hace el usuario); variables de Dokploy por nombre, nunca valores (`APP_URL`, correo, Turnstile real, Google, `TRUSTED_PROXIES`, `DB_*`); reinicio de la base y limpieza de `uploads` de prueba; despliegue y lectura del registro del migrador; `crear_superadmin.php`; registro de la clínica demo «Zooki» con `zooki.vet@gmail.com` por HU-0.1; tareas programadas (`scripts/zooki.cron`); 3–5 comprobaciones finales; y vuelta atrás (imagen v1.12.0 + respaldo en una base vacía, porque el respaldo v1 no carga en el esquema v2).

**Fuera de F0:** ejecutar nada en producción, merge, tags, commits, `.env`; HU-0.5, triage y los demás módulos.

## Anexo F0 — Resultado

**Escribió:** Claude Code (2026-10-11). **Estado:** entregada, pendiente de revisión. No se ejecutó nada en producción.

**Decisiones del usuario durante F0 (2026-10-11).**

1. La base de producción vive en el **MySQL central del VPS**, no en el contenedor `db` del compose: la guía reinicia esa base a mano.
2. **`.env` montado al arrancar.** La aplicación lee el `.env` como archivo (`parse_ini_file`); hasta hoy funcionaba porque Dokploy lo escribía junto al compose y `COPY . .` lo metía en la imagen. Sacarlo con `.dockerignore` habría dejado a producción sin credenciales. Queda fuera de la imagen y `docker-compose.yml` lo monta de solo lectura en `/var/www/html/Zooki/.env`.
3. **QR de RE-5.8.4** al módulo 5 (con HU-5.10 y HU-5.11); HU-5.8 se cierra con esa nota, como RE-0.3.6.
4. **HU construidas en parte fuera de §2** (HU-T.18, HU-5.9, HU-1.5, HU-5.12, HU-5.13 y HU-2.10) siguen «Planificada (v2)» y se cierran con su módulo.

### Qué se hizo

1. **Blindaje de Apache (B.5).** `docker/apache-zooki.conf` reemplaza el sitio por defecto: `DocumentRoot` en `public/`, `Alias /Zooki/public` al mismo lugar (un proxy que todavía apunte ahí sigue funcionando, así que el corte no exige tocar el `proxy_pass`), `/var/www/html` denegado, sin listados y sin archivos ocultos. Antes la raíz de Apache era `/var/www/html` y desde la red interna se podía pedir `/Zooki/.env`. Sin Docker en el equipo: la comprobación con imagen local (opcional) y sobre producción está en la guía, pasos 0.2 y 6.2.
2. **`.dockerignore`.** Fuera: `.env`, `.git`, `tests`, `scratch`, `logs`, `Claude outputs`, `node_modules`, `vendor` (la imagen corre su propio `composer install --no-dev`), `specs`, `agentes`, `scripts/dev`, `scripts/docs`, respaldos, `*.bak*`, archivos de `public/uploads` y los documentos no publicados. Dentro: el código, `public/`, `database/`, `scripts/` de las tareas, `docker/`, `README.md` y `documentacion/` (portal y Algolia).
3. **IP real (RN-G15).** `.env.example` explica la cadena nginx → Traefik (9080, `dokploy-network`) → Apache, los dos saltos de confianza y que Traefik debe confiar en nginx (`forwardedHeaders.trustedIPs`); el archivo sigue cargando como `.env` (había que evitar paréntesis y llaves en los comentarios, que `parse_ini_file` rechaza). La guía, paso 0.3, da las órdenes y una comprobación con un intento real (paso 10.4).
4. **Invitación reemplazada** (Modelos §10.1 actualizado). `InvitacionPersonal::reemplazada()` solo responde sí cuando el secreto del enlace coincide, la invitación está usada y existe otra vigente posterior para la misma persona y clínica. Las dos páginas (invitación a cuenta existente y activación de cuenta nueva) muestran «Esta invitación se reemplazó por una más reciente. Usa el enlace del último correo.»; un POST sobre ese enlace no decide nada; con secreto incorrecto, vencida o aceptada, el mensaje genérico de siempre. Solo lee: el GET no cambia nada.
5. **Clínicas de prueba en Profesional.** `DatosPrueba::planProfesional()` (solo `scripts/dev/`): plan Profesional, cierra la suscripción activa anterior y crea una activa y al día; repetible y sin tocar clínicas ajenas.
6. **Cierre documental en 2.0.0-alpha.1.** `config/App.php` (`VERSION`; la muestran el portal de documentación y el cache-busting). `HistorialVersiones.md`: entrada para quien usa el sistema y regla de `alpha.N`. Revisiones nuevas, una por documento tocado en M0: ERS 3.3, Ficha Técnica 3.2, Historias 3.2, Requisitos 3.2 y Reglas 2.2 (MER y Modelos no llevan número de revisión). Las nueve HU de §2 pasan a «Implementada (2.0.0-alpha.1)» y 22 reglas a «Aplicada (2.0.0-alpha.1)» (RN-006 con la nota del triage, RN-420 «Parcial»). Notas en sus filas: RE-0.3.6 y RE-T.17.4 (v2.1, HU-8.1), RE-T.19.4 (v2.1, HU-5.14), QR de RE-5.8.4 (módulo 5) y RE-0.4.6 (triage). RE-T.19.2 ya aclaraba la exención del super-administrador. README y Ficha apuntan a la versión nueva. Descargas del portal regeneradas.
7. **Tabla §2** con la evidencia de cada RE (pruebas existentes, verificadas por nombre).
8. **Guía del corte:** [`specs/M0-F-corte-produccion.md`](M0-F-corte-produccion.md), con los pasos a–j del alcance y los comandos separados entre PowerShell y VPS.

### RE y pruebas

| Punto | Prueba |
|---|---|
| RN-G15, RE-T.13.4: dos saltos | `F0CorteTest::testLaIpRealAtraviesaLosDosSaltosDeProduccion` (rango correcto, solo Traefik, sin proxies y nginx fuera del rango) |
| B.5: Apache e imagen | `F0CorteTest::testApacheSirveSoloPublicSinCambiarLasUrl`, `testLaImagenNoLlevaCredencialesNiPruebasYConservaElPortal` (también el montaje del `.env`) |
| Invitación reemplazada | `F0CorteTest`: reemplazada sin cambiar nada, POST que no decide, token incorrecto, vencida, aceptada, activación de cuenta nueva y otra persona que no cuenta |
| Clínicas de prueba | `DatosPruebaClinicasTest` (2 casos nuevos) y `BaseV2MysqlTest::testE2DatosPruebaContinuaSinCambiarClinicaAjena` (Profesional y suscripción activa en MySQL) |
| `.env.example` sigue cargando | `D21CorreccionTest::testEnvExampleSeLeeYExplicaLosProxiesYLosCorreos` |

### Verificación

- `vendor/bin/phpunit` con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1` (MariaDB de XAMPP) y la base por defecto `zooki_test_base_v2`: **578 pruebas, 3446 aserciones, ninguna omitida**, en verde (incluye las 10 de `F0CorteTest`).
- Descargas del portal regeneradas y «al día» (`exportar.mjs --revisar`). El retoque final de Word no terminó (Word automatizado dejó un archivo bloqueado); el script lo prevé: el índice de esos `.docx` se llena al abrirlos.
- `node --test "tests/Frontend/**/*.test.cjs"`: **78 en verde** (F0 no cambió JS).
- No se leyó ni escribió `.env`, ni se tocó `zooki_v2_prueba`.

### Pendientes y riesgos

- **Lo más riesgoso del corte es el `.env` montado:** si en Dokploy el `.env` no queda junto al compose, el contenedor arranca sin credenciales. La guía lo comprueba en 6.3 antes de seguir.
- La IP con que nginx llega a Traefik depende de la red del VPS: la guía la confirma con un intento real (10.4).
- El enlace «Volver a Zooki» del portal de documentación usa `../../`; en producción lleva a `/`, en XAMPP a `/Zooki/`. No se tocó.
- `AGENTS.md` dice que producción está en v1.12.0: se actualiza en F1, cuando el corte se haga.

### Pantallas para el usuario (local)

1. Con `datos_prueba.php --si`: invita a Fabio desde Norte, pulsa «Reenviar invitación» y abre en `logs/correos/` el **primer** correo: debe decir que hay una invitación más reciente; el segundo abre la invitación normal.
2. Panel del super-administrador: Norte y Sur (prueba) en plan **Profesional**; en Usuarios de Norte se puede invitar a una cuarta persona sin el aviso del tope.
3. Portal de documentación: dice **Zooki v2.0.0-alpha.1** y el historial empieza por esa versión.

### F0 — Revisión (2026-10-11)

**Revisó:** Claude (arquitecto). **Resultado: aprobada**, con una corrección aplicada en la revisión.

**Verificado.**

- Suites: PHP 578 pruebas en verde en una copia limpia sin `.env` (29 MySQL omitidas; la única falla fue por no copiar `public/docs/` a esa copia, y `F0CorteTest` pasó completo, 10 pruebas, al reponerlo); JS 78/78 en el equipo del usuario.
- Apache: `DocumentRoot` en `public/`, `Alias /Zooki/public` para no tocar el `proxy_pass`, `/var/www/html` denegado y archivos ocultos bloqueados. `.dockerignore` deja fuera credenciales, pruebas y material de trabajo, y conserva `documentacion/` para el portal.
- `.env` montado de solo lectura (decisión del usuario): la guía comprueba en 6.3 que se lee antes de seguir.
- Invitación reemplazada: solo con el secreto correcto, invitación usada y otra vigente posterior de la misma persona y clínica; el POST no decide nada y el GET no cambia nada. Coincide con Modelos §10.1.
- Guía del corte: respaldo comprobado restaurándolo en una base aparte antes de borrar nada; el esquema v2 da 50 tablas (igual que `BaseV2MysqlTest`); el tag `v1.12.0` existe para la vuelta atrás; las columnas de la consulta de IP (10.4) existen en `auditoria_sistema`; el NIT demo 901234567-7 cumple el módulo 11.

**Corrección aplicada en la revisión.** `public/uploads/clinicos/.htaccess` no está en git (`.gitignore` ignora `public/uploads/*`) ni entra en la imagen, así que el bloqueo de los adjuntos clínicos dependía de que el volumen lo tuviera, y el paso 5.2 de la guía esperaba verlo. Ahora `docker/apache-zooki.conf` deniega `public/uploads/clinicos` (los adjuntos siguen saliendo solo por `ver_archivo.php`), `F0CorteTest` lo comprueba y la guía revisa `/uploads/clinicos/` en 6.2.

**Para F1.** El paso 0.3.3 cambia el `traefik.yml` que comparten todos los proyectos del VPS; `trustedIPs` con solo el gateway no afecta a los demás, pero conviene hacerlo en un momento tranquilo y abrir otro proyecto después. El NIT demo puede coincidir con el de una empresa real; si un día molesta, se cambia por otro con DV válido.

**Prueba local de F0 (2026-10-11).** `datos_prueba.php` se detuvo, con razón: «Reina Sofia» (id 3, registrada en la prueba de D3 el 2026-10-10) tiene el NIT `9001234568`, el mismo de «Clínica Norte (prueba)», guardada como `900123456-8`. Entró antes de que E2 comparara el NIT con y sin guion; desde E2 el registro ya lo rechaza, y en producción no hay NIT con guion porque la base se reinicia. Arreglo solo de datos locales: otro NIT válido para «Reina Sofia» (`9001112221`) y volver a correr el script.

**«Enlace no disponible» en la prueba local, aclarado (2026-10-11).** Los enlaces se abrían desde el código fuente del `.html` guardado en `logs/correos/`, y llegaban con `&amp;` literal (`?action=…&amp;id=…&amp;token=…`): el servidor no recibía `id` ni `token` y respondía el mensaje genérico. Con los mismos enlaces, el modelo responde bien (`scratch/diag_invitacion_f0.php`: id 14 reemplazada, id 15 vigente). Es también la causa más probable del «no disponible» de Fabio en D2.2. En producción no pasa: el cliente de correo decodifica el HTML. Para las pruebas locales, abrir el `.html` en el navegador y pulsar el botón.

**F0 — Prueba del usuario (2026-10-11).** Las tres pruebas pasaron: el primer correo reenviado dice que hay una invitación más reciente y el segundo abre la invitación normal; Norte y Sur en Profesional permiten una cuarta persona; el portal muestra la v2.0.0-alpha.1. **F0 cerrada.** Sigue F1 con `specs/M0-F-corte-produccion.md`.

## F1 — Bitácora del corte (la ejecuta el usuario; acompaña Claude)

**0.3 Proxy (2026-10-11).** nginx (`sites-available/zooki`, activo): `proxy_pass http://127.0.0.1:9080/Zooki/public/` con `Host`, `X-Real-IP`, `X-Forwarded-For` y `X-Forwarded-Proto`; no se cambia (el `Alias /Zooki/public` de F0 lo cubre). Traefik (`dokploy-traefik`): publica 9080/9443 por la red `bridge` (172.16.0.2) y ya confía en `127.0.0.1/32`, `172.16.0.1/32` y `172.16.11.1/32` en el entrypoint `web`; no se cambia. Desde Apache, el salto inmediato es Traefik en `dokploy-network` (`10.0.1.0/24`) y el anterior es el gateway de `bridge` con el que nginx llega a Traefik (`172.16.0.1`; no `docker_gwbridge` 172.16.8.1, como suponía la guía). Valor para Dokploy: `TRUSTED_PROXIES=10.0.1.0/24,172.16.0.1`. Se confirma en el paso 10.4 con un intento real.

**1.1 Base y volúmenes (2026-10-11). Corrige la decisión 1 del Anexo F0:** la base de producción **no** está en un MySQL central: está en el servicio `db` del compose (contenedor `zooki_db`, mysql:8.0, volumen `zooki-app-pnyxqa_db_data`), y la aplicación usa `DB_HOST=db`, `DB_NAME=zooki_db`. La guía sirve igual tomando `<mysql_central>` = `zooki_db` (el reinicio de los pasos 5 y 11 es un DROP/CREATE dentro de ese contenedor; el volumen no se borra). `<vol_uploads>` = `zooki-app-pnyxqa_uploads`; respaldos en `zooki-app-pnyxqa_respaldos`.

**1.2–1.4 Respaldo (2026-10-11).** `zooki_db_2026-10-11_22-50-19.sql.gz` (27 tablas, 12.6 KB) y `uploads-v1.12.0.tar.gz` (volumen vacío) en `~/respaldo-zooki-f1`. Restauración de prueba en una base aparte: 27 tablas y 23 usuarios. Un lote de 100 filas de `auditoria_sistema` falló con `Data truncated for column 'accion'`: la v1 guardó un valor fuera del ENUM con un `sql_mode` permisivo y MySQL 8 estricto lo rechaza. No afecta al corte (los datos no se migran); la vuelta atrás (paso 11) ahora pone `SET SESSION sql_mode = ''` antes del `SOURCE` para cargar también esas filas.

**2–6 Corte (2026-10-11).** Variables de Dokploy revisadas por el usuario (`TRUSTED_PROXIES=10.0.1.0/24,172.16.0.1`, Turnstile real creado en Cloudflare sin mover el DNS, sin `MAIL_MODO`). Merge `v2/m0` → `main` (`c3b7fb6`) con autodeploy. La v2 arrancó sobre la base v1 y respondió 500, como se esperaba. Base reiniciada con DROP/CREATE en `zooki_db` y `01_schema.sql` + `02_semilla.sql`; el 5.2 no hizo falta (volumen de archivos vacío). Primer arranque: migraciones 03, 04 y 05 aplicadas y semilla; segundo: «La base está al día». Blindaje en producción: `/.env` 403, `/composer.json`, `/tests/`, `/database/`, `/Zooki/.env` y `/Zooki/composer.json` 404, `/uploads/clinicos/` 403; `.env` montado y legible por `www-data`; la imagen no lleva `tests`, `scratch`, `specs` ni `agentes`.

**7–8 Super-administrador y clínica demo (2026-10-11).** `crear_superadmin.php` creó la cuenta del usuario. El primer registro de «Zooki» usó por error el correo del super-administrador como administrador de la clínica: Zooki lo rechazó al confirmar («No se pudo confirmar esta solicitud. Contacta a soporte»), correcto porque el super-administrador no tiene roles de clínica (RN-G13). **Pendiente de pulido (no bloquea):** en esa pantalla el titular ya probó que el correo es suyo, así que puede decirse la causa sin revelar nada a terceros («Esta cuenta administra la plataforma y no puede administrar una clínica; regístrala con otro correo»). La solicitud pendiente se borró a mano en `zooki_db` (los mismos pasos de `RegistroClinica::eliminarClinica`) para volver a registrar con `zooki.vet@gmail.com` sin esperar a la limpieza automática.

**9–10 Tareas y comprobaciones (2026-10-11).** Schedule nuevo «Limpiar altas e invitaciones vencidas» (`0 * * * *`, `php /var/www/html/Zooki/scripts/limpiar_cuentas_pendientes.php`); los cuatro corrieron sin errores con **Run**. El super-administrador ve «Zooki» en Gratuito con su uso. **IP real (10.4), primer intento:** la auditoría guardó `172.16.11.1`, la puerta de enlace de la red `bot-n8n-kleywu`, donde Traefik también está conectado: nginx llega a Traefik por esa red, no por `bridge`. Traefik ya confiaba en ella; Zooki no. Corrección: `TRUSTED_PROXIES=10.0.1.0/24,172.16.0.1,172.16.11.1` (la misma lista de confianza de Traefik), redespliegue y repetir el intento.

**10.4 IP real, segundo intento (2026-10-11).** Con la lista nueva, la auditoría guardó `191.156.231.158`, la IP pública del celular con datos móviles: RN-G15 funciona detrás de nginx → Traefik → Apache.

**F1 cerrada; M0 cerrado (2026-10-11).** Producción en **2.0.0-alpha.1** (`main` `c3b7fb6`, tag `v2.0.0-alpha.1`), base v2 limpia, super-administrador del usuario y clínica demo «Zooki» activa con `zooki.vet@gmail.com`. Respaldo v1 en `~/respaldo-zooki-f1` del VPS y en `C:\Users\Usuario\respaldo-zooki-f1`. Limpieza pendiente cuando el usuario quiera (una o dos semanas sin problemas): `/tmp/respaldo-v1.sql` dentro de `zooki_db`, `/tmp/zooki-f1` y `/tmp/respaldo-v1.sql` del VPS. Pulido anotado: mensaje claro cuando un super-administrador intenta administrar una clínica.

## M0.1 — Alcance: pulido del acceso y los registros (2026-10-11)

**Escribe:** Claude Code (decisión del usuario: mejor en interfaz y experiencia). **Revisa:** Claude (arquitecto). **Sale como `2.0.0-alpha.2`.** **Estado:** entregada, pendiente de revisión (2026-10-11; ver «Anexo M0.1 — Resultado»).

**Decisiones del usuario (2026-10-11).**

- La interfaz se pule **por módulo, al cerrarlo y antes del siguiente**, solo en las pantallas de ese módulo y en los patrones compartidos (formularios, modales, validación, avisos). Lo de `specs/pulido-interfaz.md` que pertenece a pantallas que un módulo próximo va a rehacer (agenda con triage, consulta con HU-2.8/2.9, portal) se atiende dentro de ese módulo.
- **Cada entrega a producción sube la alfa** (`alpha.N`), no solo cada módulo: los CSS y JS se piden con `?v=<versión>` y, sin cambiarla, el navegador sigue con los archivos viejos.
- Orden del pulido de M0: primero acceso y registros (M0.1); después el panel del super-administrador (M0.2).

**Hallazgos del usuario en producción y qué se pide.**

1. **Turnstile en el inicio de sesión.** Hoy el widget sale siempre (en tema oscuro, grande) aunque el servidor solo lo exige tras 5 fallos sobre una cuenta (RN-G15, RE-T.13.5). Se pide: tema claro, tamaño que no estorbe (ancho del formulario como máximo) y que **solo aparezca cuando haga falta**: en el login, cuando el servidor lo pide; en los registros (propietario y clínica), con `appearance: interaction-only` para que Cloudflare solo lo muestre si necesita que la persona interactúe. El servidor sigue validando igual. Cuidado: pedirlo solo a una cuenta existente revelaría que existe (RN-G15/RE-T.13.x); la condición debe dar la misma respuesta para un identificador inexistente.
2. **Un solo «Crear cuenta».** Debajo del inicio de sesión, un único enlace lleva a crear cuenta, con un selector de dos opciones («Soy propietario» / «Registrar mi clínica»). Se retira el enlace suelto de «Registra tu clínica». Los dos formularios se ven igual: mismo ancho, alto de campos, espaciado, tipografía y botones.
3. **Avisos bajo los campos en una línea**, del ancho del campo: textos cortos; el detalle, si hace falta, en el resumen del formulario.
4. **Registro de clínica por pasos:** 1) datos de la clínica, 2) datos del administrador, 3) política de datos y «Registrar clínica». Cada paso valida lo suyo antes de seguir y se puede volver atrás sin perder lo escrito; campos más compactos y mejor repartidos (dos columnas en escritorio, una en celular).
5. **Al enviar el registro de clínica**, la misma ventanita de carga y de «confirma tu correo» que ya tiene el registro del propietario.
6. **Casilla de la política de datos** del registro de clínica con el mismo componente del registro del propietario.

**Fuera de M0.1:** panel del super-administrador (M0.2), mensaje del super-administrador que intenta administrar una clínica (anotado en la bitácora de F1; entra en M0.2 si cabe), y los pendientes de `pulido-interfaz.md` de otros módulos.

**Decisión del usuario durante M0.1 (2026-10-11).** Los 5 fallos que piden el CAPTCHA se cuentan **solo por el documento o correo escrito** (normalizado, sin mayúsculas ni espacios sobrantes), exista o no la cuenta, y ya no por `id_usuario`. Contar por cuenta habría delatado las cuentas reales en cuanto el widget dejó de salir siempre. Matiz aceptado: alternando documento y correo de una misma cuenta se llega a 10 fallos antes del CAPTCHA; el tope de 20 por IP sigue igual. RN-G15, RE-T.13.5, HU-T.13 y RNF-12 quedaron redactados así.

## Anexo M0.1 — Resultado

**Escribió:** Claude Code (2026-10-11). **Estado:** entregada, pendiente de revisión.

### Qué se hizo

1. **Turnstile.** `turnstile-formularios.js` pinta cada widget en tema claro, en español y del ancho del formulario: tamaño `flexible`, o `compact` si el contenedor mide menos de 300 px. No pinta un contenedor oculto hasta que se muestra con `mostrar()`. Se carga con `render=explicit` y desapareció el `.cf-turnstile` de pintado automático.
   - **Login.** El widget, con `appearance: always`, solo se ve cuando el servidor lo pidió en los últimos 15 minutos (`$_SESSION['login_captcha']`). Lo pide en dos casos: tras el quinto fallo, para el intento siguiente, y cuando un intento llega sin token válido. En ese segundo caso responde «Por seguridad, completa la verificación y vuelve a escribir tu contraseña.», con la contraseña correcta o incorrecta y exista o no la cuenta (`Autenticador` devuelve `captcha`).
   - **Google.** Si el servidor exige el CAPTCHA, muestra el widget del login y pide volver a continuar con Google.
   - **Registros.** Usan `interaction-only`. En el de clínica el widget va fuera de los pasos, porque «Reportar a soporte» del paso 1 también lo exige.
   - **Validación del servidor:** no cambió.
2. **Un solo «Crear cuenta».** Bajo el login hay un enlace que abre un selector (`crear-cuenta.js`) con dos opciones:
   - «Soy propietario» gira la tarjeta al registro del propietario.
   - «Registrar mi clínica» lleva a `registro_clinica`.

   Se retiró «Registra tu clínica». Sin JavaScript, el enlace abre `action=register`. Las rutas `register`, `process_register`, `registro_clinica` y `registrar_clinica_ajax` siguen igual.
3. **Marco común** (`registro-cuenta.css`):
   - Ancho de 460 px, campos de 42 px y rejilla de dos columnas: una por debajo de 768 px.
   - Los mismos botones, la misma casilla de la política (`partials/consentimiento_datos.php`, también en el modal de Google) y la misma ventana de espera (`partials/registro_espera.php` y `registro-cuenta.js`).
   - El botón del propietario dice «Crear mi cuenta». Se retiró el medidor de contraseña, que ningún script movía.
4. **Avisos de una línea.** Bajo el campo, el aviso ocupa una sola línea con puntos suspensivos y lleva el texto completo en `title`. Los textos son de 35 caracteres o menos, en `ValidadorCuenta`, `ValidadorClinica`, `ValidadorTelefono`, `ValidadorNit`, `DominiosDesechables`, tres mensajes de `PoliticaPassword` y sus espejos en JS.
   - `RegistroPropietario` usa los mismos textos.
   - Al enviar con errores, `validacion-cuenta.js` emite `cuenta:invalida` y el resumen del formulario lista «Campo: detalle». Allí también aparece el rechazo del servidor.
5. **Registro de clínica en tres pasos** (`registro-pasos.js`):
   - Los pasos son clínica (con NIT y DV en vivo), administrador y política con «Registrar clínica».
   - El indicador de progreso solo aparece con JavaScript.
   - Cada paso valida sus campos con las reglas de `validacion-cuenta.js` y las del navegador antes de avanzar. Atrás conserva lo escrito.
   - Un error al enviar lleva al paso del campo. El NIT duplicado que rechaza el servidor vuelve al paso 1 con «Reportar a soporte».
   - Sin JavaScript se ven los tres pasos y el formulario se envía completo. `RegistroClinicaController` ya no devuelve JSON crudo a un envío normal: vuelve a la página con el error en el resumen, o con «Revisa tu correo».
6. **Ventana de carga** al enviar los dos registros: primero «Enviando tu registro…» y después «Revisa tu correo», con el mismo texto exista o no la cuenta (RE-T.13.2).
7. **Versión 2.0.0-alpha.2.** Cambian:
   - `config/App.php`.
   - La entrada de `HistorialVersiones.md` y la regla de numeración, en la nota de la v2 y en el esquema: cada entrega a producción sube la alfa.
   - README y Ficha.
   - Las páginas de acceso piden sus CSS y JS con `?v=<versión>`.
8. **Diagramas.** Modelos §10 (CAPTCHA por identificador, widget y mensaje), §11 (selector «Crear cuenta» y ventana de carga) y §12.1 (tres pasos, Atrás y ventana de carga). Los tres renderizan con Mermaid CLI.

### RE y pruebas

| Punto | Prueba |
|---|---|
| RE-T.13.5 / RN-G15: sin fallos no hay widget; tras 5 fallos, misma respuesta con o sin cuenta | `M01AccesoTest::testSinFallosElLoginNoMuestraElWidget`, `testCincoFallosDanLaMismaRespuestaConYSinCuenta`, `testCuatroFallosNoLoPidenYMayusculasOEspaciosNoCuentanAparte` |
| RE-T.13.5: el servidor rechaza sin token, también con la contraseña correcta, y el titular entra con un CAPTCHA válido desde otra IP | `M01AccesoTest::testElServidorRechazaSinTokenYAdmiteElTitularConCaptchaValido`, `CuentasD2Test::testCaptchaDesdeOtraIpNoBloqueaLaCuenta`, `D21CorreccionTest` (actualizadas al identificador) |
| RE-0.1.1: tres pasos, sin JS se envía completo | `M01AccesoTest::testRegistroDeClinicaSinJavascriptMuestraElErrorOLaEspera`, `testLosDosRegistrosCompartenMarcoCasillaYVentanaDeEspera`; JS: `M01Acceso.test.cjs` (no avanza con errores, avanza y vuelve sin perder datos, error al paso de su campo) |
| RE-0.1.4 / RE-5.8.6: CAPTCHA en los registros | `M01AccesoTest::testRegistroDeClinicaPorAjaxSigueRespondiendoJson`, `RegistroClinicaTest` y `D3Avisos.test.cjs` (Turnstile: tema claro, `interaction-only`, `compact` en pantallas angostas, sin duplicar) |
| Selector «Crear cuenta» | `M01Acceso.test.cjs` (abre, «Soy propietario» gira, se cierra con X, fondo y Escape; vista sin «Registra tu clínica») |
| Avisos de una línea | `M01AccesoTest::testLosAvisosDelServidorBajoElCampoSonCortos`; JS: CSS de una línea, `title` y textos ≤ 35, resumen con etiqueta |
| Ventana de carga | `M01Acceso.test.cjs` (carga → «Revisa tu correo», rechazo vuelve al formulario sin borrar), `D3Avisos.test.cjs` (registro del propietario) |

Mutaciones comprobadas:
- Pedir el CAPTCHA solo a cuentas existentes hace fallar `testCincoFallosDanLaMismaRespuestaConYSinCuenta`.
- Mostrar el widget siempre hace fallar dos pruebas.

### Verificación

- `vendor/bin/phpunit`: **587 pruebas en verde**. Sin MySQL: 3201 aserciones y 29 omitidas. Con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1` y la base por defecto `zooki_test_base_v2`: 3563 aserciones y ninguna omitida.
- `node --test "tests/Frontend/**/*.test.cjs"`: **92 en verde** (14 nuevas).
- Sin `style=`, `on…=` ni `<script>` con código en las vistas tocadas.
- Descargas del portal regeneradas.
- No se leyó ni escribió `.env`, ni se tocó `zooki_v2_prueba`.

### Pendientes y riesgos

- **El Turnstile real solo se ve en el navegador:** tema, ancho y `interaction-only`. Para probar el widget del login en local, el `.env` debe tener claves de Turnstile, por ejemplo las de prueba de Cloudflare.
- Si Cloudflare pide interactuar en el registro de clínica, el widget aparece debajo de los botones del paso. Es poco frecuente con `interaction-only`.
- Los contadores `cuenta:<id>` que hoy existan en producción quedan sin uso y la limpieza los borra en 24 horas. No hace falta migración.
- RN-G15, RE-T.13.5, HU-T.13, RNF-12, HU-0.1 y RE-0.1.1 se corrigieron sin subir revisión. La revisión nueva sale al cerrar el próximo módulo.
- `AGENTS.md` dice que producción está en alpha.1; se actualiza cuando el usuario despliegue la alpha.2.
- `activar_clinica.php` conserva su casilla propia. No es uno de los dos registros y quedó fuera.

### M0.1 — Revisión (2026-10-11)

**Revisó:** Claude (arquitecto). **Resultado: aprobada**, pendiente de la prueba del usuario.

**Verificado.** PHP 587 pruebas en verde en una copia limpia sin `.env` (29 MySQL omitidas); JS 92/92 en el equipo del usuario; vistas tocadas sin `style=`, `on…=` ni `<script>` con código. El CAPTCHA del login cuenta por la huella SHA-256 del identificador normalizado (no guarda el documento ni el correo), responde `captcha` igual exista o no la cuenta y con la contraseña correcta o no, y el servidor sigue exigiéndolo aunque la página no muestre el widget. El widget solo se pinta cuando la sesión trae la marca del servidor de los últimos 15 minutos. Turnstile: tema claro, español, `flexible`/`compact` según el ancho y `interaction-only` en los registros.

**Observaciones (no bloquean).**

1. Al contar por identificador, una misma cuenta admite 5 fallos por su documento y otros 5 por su correo antes de pedir el CAPTCHA. Es el precio de no revelar qué cuentas existen y sigue acotado por las 20 de la IP. Aceptable.
2. El Anexo no trae la lista de pantallas para el usuario; la da la revisión (abajo).
3. Los contadores `cuenta:<id>` de producción quedan sin uso y se limpian solos.

**Prueba del usuario (local y, después del despliegue, en producción).**

1. Login sin el cuadro de Cloudflare; 5 fallos con un mismo correo (exista o no) muestran el cuadro claro en el sexto intento; con él se entra.
2. «Crear cuenta» abre el selector; «Soy propietario» gira la tarjeta y «Registrar mi clínica» abre su página; los dos con el mismo aspecto.
3. Registro de clínica: no avanza con errores (avisos de una línea), «Atrás» conserva lo escrito, el NIT duplicado avisa en el paso 1 y el envío muestra la ventana de carga y «Revisa tu correo».
4. Las dos pantallas en el celular: una columna, nada se sale.

## M0.2 — Alcance propuesto: últimas correcciones del acceso, panel del super-administrador y páginas de los enlaces de correo (2026-10-11)

**Escribe:** Claude Code. **Revisa:** Claude (arquitecto). M0.1 y M0.2 salen juntas en **`2.0.0-alpha.2`** (un solo despliegue). **Estado:** entregada, pendiente de revisión (2026-10-11; ver «Anexo M0.2 — Resultado»). La estructura del panel es una propuesta de la revisión que el usuario aprueba antes de pegar el prompt.

**A. Correcciones del acceso (hallazgos del usuario sobre M0.1).**

1. Al entrar y salir de un campo sin escribir, el contenido de debajo baja: el aviso vacío no debe ocupar ni cambiar el alto (espacio reservado fijo o aviso superpuesto); ningún salto de diseño al validar.
2. El botón «Atrás» del registro de clínica es el mismo del registro del propietario (componente, icono y posición).
3. La animación de voltear la tarjeta del registro del propietario también al entrar al registro de clínica desde «Crear cuenta» y al volver de él al inicio de sesión.

**B. Panel del super-administrador** (`views/plataforma/`, `public/css/plataforma.css`, `public/js/plataforma.js`). Hoy: tres secciones apiladas (clínicas, casos de soporte, IP bloqueadas), mucho desplazamiento, tarjetas sin estilo y elementos encimados. La barra lateral se conserva. Propuesta:

- **Franja de indicadores** arriba: clínicas activas, pendientes, suspendidas, casos abiertos e IP bloqueadas; cada uno filtra o abre su pestaña.
- **Pestañas** «Clínicas», «Soporte» (con contador) y «Seguridad» (IP bloqueadas): una sola sección a la vista; la pestaña se recuerda como la vista de tarjetas/tabla.
- **Clínicas:** buscador por nombre o NIT y filtros de estado y plan en una línea; tarjetas con nombre, insignia de estado, NIT con DV, plan y suscripción, y el uso con barras (personal x/2, mascotas x/50, citas x/100, «Sin límite» en Profesional), y «Gestionar». La tabla conserva las mismas columnas con el mismo estilo de Usuarios.
- **Modal «Gestionar»** por pestañas internas (Plan y suscripción · Estado · Aviso al administrador) con el estado y el administrador en la cabecera; solo «Dar de baja» confirma (decisión del 2026-10-10).
- **Soporte y Seguridad** en tarjetas o filas con el mismo componente, acciones alineadas y estados vacíos con mensaje.
- Objetivo: la primera vista sin desplazamiento en escritorio (≥ 1024 px); en celular, una columna sin nada encimado ni desbordado.
- Mensaje claro cuando un super-administrador intenta administrar una clínica (bitácora de F1): en `activar_clinica`, donde el titular ya probó su correo, «Esta cuenta administra la plataforma y no puede administrar una clínica. Regístrala con otro correo».

**C. Páginas a las que se llega desde un correo.** Mismo marco visual que el acceso: tarjeta, tipografía, botones, avisos y la casilla de la política de `partials/consentimiento_datos.php`; ningún párrafo, enlace ni casilla sin estilo; estados de éxito, error y enlace no disponible con el mismo componente. Vistas: `activar_clinica`, `aceptar_politica`, `confirmar_vinculo`, `enlace_identidad` (activación del personal y cambio de correo), `invitacion_personal`, `verificar_email`, `reset_password`, `cambiar_password`, `completar_perfil` y `seleccionar_contexto`. También se revisa que el HTML de los correos se vea bien en Gmail (claro y oscuro).

**Fuera de M0.2:** paneles de la clínica, del veterinario y del portal (se rehacen en sus módulos) y los pendientes de `pulido-interfaz.md` de otros módulos.

**Captura del usuario del panel en producción (2026-10-11), defectos concretos para B:** cabecera con «Cambiar contexto» y «Cerrar sesión» como enlaces sin estilo (morados de visitado), título «Clínicas» pegado al subtítulo, el selector tarjetas/tabla solo en una franja blanca de lado a lado, buscador y «Estado» desalineados y con el `select` nativo; en la tarjeta: NIT sin guion del DV (`9012345677` → `901234567-7`), estado como texto suelto (no insignia), etiquetas y valores apilados sin jerarquía, fecha de registro en crudo (`2026-10-11 18:35:45`), «Sin suscripción» en una clínica Gratuito (debe decir algo comprensible, p. ej. «Gratuito · sin pago»), cuadrícula de uso desordenada y botón «Gestionar» más angosto que su texto. **Además:** las vistas piden CSS y JS con versiones escritas a mano (`?v=e2`, `?v=d3`); todas deben usar `App::assetVersion()` para que cada alfa renueve la caché del navegador.

**Decisiones del usuario durante M0.2 (2026-10-11).**

1. El modal «Gestionar» tiene **dos pestañas**, «Plan y suscripción» y «Estado». No se agrega un aviso libre al administrador de una clínica, porque sería una acción nueva. El aviso sigue en cada IP de la pestaña Seguridad.
2. En el registro de clínica, **la flecha junto al título es el único «Atrás»**, el mismo componente del registro del propietario. En el paso 1 vuelve al login con el giro; en los pasos 2 y 3, al paso anterior. Abajo solo queda «Siguiente» o «Registrar clínica».
3. **Revisión del usuario sobre la primera versión del panel (2026-10-12).**
   - Fuera la franja de indicadores: ocupaba casi un quinto de la pantalla cada uno y no aportaba.
   - La identidad de Zooki es el azul: nada de verdes, ámbar, rojos ni violetas de adorno.
   - Las tarjetas deben medir lo mismo y copiar el diseño de Usuarios del administrador de la clínica, reutilizando lo hecho.
   - El buscador ocupaba media pantalla: debe ser corto.

## Anexo M0.2 — Resultado

**Escribió:** Claude Code (2026-10-11). **Estado:** entregada, pendiente de revisión. Sale con M0.1 en `2.0.0-alpha.2`.

### Qué se hizo

1. **Acceso (A).**
   - **Aviso sin salto.** Cada campo de los formularios de acceso reserva una línea fija para su aviso: `.registro-marco .input-group` con `padding-bottom` y el aviso en posición absoluta. Aparecer, cambiar o vaciarse no mueve nada. El NIT duplicado y «Reportar a soporte» comparten una línea reservada; el botón se volvió un enlace de texto.
   - **Un solo «Atrás».** Ver la decisión 2; lo maneja `registro-pasos.js`.
   - **Giro de tarjeta.** El nuevo `acceso-giro.js` gira la tarjeta hasta dejarla de canto, navega, y la página nueva la termina de girar. Funciona entre «Crear cuenta → Registrar mi clínica» y la flecha que vuelve al login. Sin JavaScript los enlaces navegan normal; quien prefiere menos movimiento no ve el giro.
   - La barra superior del registro de clínica dice «Volver al inicio», como el login.
   - El collage del acceso ahora es un parcial (`partials/acceso_hero.php`).
2. **Panel del super-administrador (B).** Está en `views/plataforma/`, `plataforma.css` y `plataforma.js`, con el nuevo `helpers/PresentacionPlataforma.php`.
   - **Cabecera:** el indicador de contexto y la ficha de usuario del sistema, en lugar de enlaces sueltos.
   - **Después de la revisión del usuario (decisión 3):** la página reutiliza tal cual la cabecera, los filtros, la tarjeta y la tabla de Usuarios (`header-container-white`, `tabs-wrapper`, `search-input`, `filter-select`, `person-card` con `card-*-mini`, `tag-mini`, `action-btn-mini`, `users-table`). No hay franja de indicadores y solo se usan azul y grises.
   - **Pestañas** Clínicas, Soporte (con contador de casos abiertos) y Seguridad (con contador de IP bloqueadas): se recuerdan, igual que la vista de tarjetas o tabla.
   - **Buscador y filtros:** buscador corto (280 px), estado y plan en la misma línea, que actúan al escribir y solo se ven en Clínicas. Sin JavaScript filtran en el servidor; el modelo ahora acepta `plan` y busca el NIT con o sin guion.
   - **Tarjeta de Usuarios, todas del mismo alto:** iniciales, nombre en una línea, etiquetas de estado (la activa en azul, las demás en gris con su icono) y de plan, y líneas con NIT con DV, administrador («por confirmar» si está pendiente), suscripción («Sin pago» en el gratuito) y fecha legible («11 oct 2026»). Debajo, barras de uso finas en azul con `<progress>`; en Profesional, «sin límite» con una línea punteada. «Gestionar» es el botón de icono del pie, como «Editar» en Usuarios.
   - **Tabla** con el estilo de Usuarios.
   - **Soporte y Seguridad** con el mismo componente de fila, acciones alineadas y estados vacíos.
   - **Modal por pestañas,** con el estado, el NIT y el administrador en la cabecera. Una clínica pendiente o dada de baja abre en Estado y dice por qué no se cambia el plan. Solo «Dar de baja» confirma.
   - **Acciones y permisos:** no cambian.
   - **`activar_clinica`:** si la cuenta del enlace es de super-administrador, el GET y el POST muestran «Esta cuenta administra la plataforma y no puede administrar una clínica. Regístrala con otro correo.», sin botón y sin cambiar nada (`RegistroClinica::MENSAJE_SUPER_ADMIN`). Modelos §12.1 tiene la rama nueva y §12.1.1 la franja y las pestañas; los dos renderizan con Mermaid CLI.
3. **Versiones (B2).** Todas las vistas, incluidos `admin`, `vet`, `portal`, los parciales y el portal de documentación, piden sus CSS y JS con `App::assetVersion()`. Ya no quedan `?v=e2`, `?v=d3`, `?v=12` ni `time()`.
4. **Páginas de los correos (C).**
   - **Piezas comunes:** `partials/acceso_marco_inicio.php` y `acceso_marco_fin.php` (el mismo marco del login, con `referrer` desactivado porque la URL lleva el token) y `partials/acceso_estado.php` (un solo encabezado de estado para formulario, éxito, error y enlace no disponible).
   - **Las diez vistas** del alcance se reescribieron sobre esas piezas, con la casilla común de la política. La casilla admite la versión: la activación del personal y «aceptar política» la muestran.
   - **Retirados:** `cabecera_enlace.php`, `reset-password.css`, `cuenta-pendiente.css` y `cambiar-password.css`. A `contexto.css` solo le queda el indicador del contexto activo.
5. **Correos.** La plantilla ahora escapa el nombre, el título, el texto del botón y el enlace. **Hallazgo de seguridad:** el título no se escapaba, y en el registro de clínica lleva el nombre escrito por el público. Además había doble escape en los correos del personal; los llamadores pasan ahora texto plano.
   - **Para Gmail claro y oscuro:** `color-scheme: light`, colores de fondo en cada celda, botón como celda con su color, logotipo sobre una placa blanca y sin márgenes negativos, ancho fluido de hasta 600 px y el enlace en texto debajo del botón. `EmailService.example.php` va igual.
6. **Versión:** sigue en 2.0.0-alpha.2. La entrada de `HistorialVersiones.md` ahora incluye el acceso, el panel y las páginas de los correos. HU-0.3 menciona los filtros y la franja.

### RE y pruebas

| Punto | Prueba |
|---|---|
| RE-0.3.1–0.3.4 (lista, plan y suscripción, sin datos clínicos, rol de plataforma) | `M02PanelEnlacesTest::testElPanelOrdenaClinicasSoporteYSeguridadSinDatosClinicos`, `testSinJavascriptFiltraPorEstadoPlanYNitConGuion`, `testPresentacionDelPanel`, y `PlataformaTest` completo |
| Pestañas, filtros por pestaña, contador, modal por pestañas, sin colores ajenos al azul | `M02Panel.test.cjs` (5 casos), `E1Plataforma.test.cjs` |
| RN-G13 en `activar_clinica` (super-administrador) | `M02PanelEnlacesTest::testActivarClinicaExplicaQueLaCuentaEsDeSuperAdministrador` (GET, POST y modelo directo, sin activar ni dar rol) |
| Páginas de enlace con el marco común | `M02PanelEnlacesTest::testLasPaginasDeLosCorreosUsanElMarcoDelAcceso` (11 casos: marco, estado, versión, `no-referrer`, sin diseño viejo, sin enlaces ni párrafos sin clase), `testLasCasillasDeLaPoliticaSonLaComun`, `testExitoErrorYEnlaceNoDisponibleConElMismoComponente` |
| Aviso sin cambio de alto, giro y «Atrás» | `M02Panel.test.cjs` (CSS reservado, giro de salida y de entrada, sin movimiento, flecha por paso) |
| Correos | `M02PanelEnlacesTest::testLaPlantillaDeCorreoEscapaYSeLeeEnGmail` |
| Versión de los recursos | `D1Sesion.test.cjs` (layouts con `App::assetVersion()`), `M02Panel.test.cjs` |

Mutaciones comprobadas:
- No guardar la pestaña hace fallar la prueba de la pestaña recordada.
- Quitar el espacio reservado del aviso hace fallar la prueba del aviso sin salto.

### Verificación

- `vendor/bin/phpunit`: **605 pruebas en verde**. Sin MySQL: 3450 aserciones y 29 omitidas. Con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1` y la base por defecto `zooki_test_base_v2`: 3812 aserciones y ninguna omitida.
- `node --test "tests/Frontend/**/*.test.cjs"`: **101 en verde** (9 nuevas).
- Sin `style=`, `on…=` ni `<script>` con código en las vistas tocadas. `views/vet/calendario.php` ya tenía `on…=` antes de M0.2; solo se le cambió la versión del script y su limpieza queda para el módulo 4.
- Descargas del portal regeneradas (HU e historial).
- No se leyó ni escribió `.env`, ni se tocó `zooki_v2_prueba`.

### Pendientes y riesgos

- **No pude ver Gmail.** La plantilla se probó por su estructura; falta mirar un correo real en Gmail claro y oscuro (pantalla 5).
- **Las diez páginas de enlace cambiaron por completo.** Sus flujos (POST, tokens, CSRF) siguen igual y las suites pasan, pero conviene recorrerlas.
- `dashboard.css` importa sus módulos con `@import ...?v=4`. Un CSS no puede leer `App::assetVersion()`, así que esos módulos no se renuevan con la alfa. Se resuelve cuando el módulo de la clínica rehaga sus estilos.
- Sin la franja, en escritorio la primera fila de tarjetas queda a la vista con la cabecera y los filtros.

### Pantallas para el usuario

1. **Registro (escritorio y celular).**
   - Entra y sal de los campos sin escribir: nada debe moverse.
   - «Crear cuenta → Registrar mi clínica» gira la tarjeta; la flecha del paso 1 vuelve al login con el giro.
   - En los pasos 2 y 3 la flecha vuelve al paso anterior sin perder lo escrito.
2. **Panel del super-administrador (escritorio y celular).**
   - Debe verse como Usuarios: tarjetas del mismo alto, en azul y gris, y buscador corto.
   - La pestaña se recuerda al recargar, y el buscador encuentra «901234567-7».
   - Revisa la tabla y el botón de icono «Gestionar» con sus dos pestañas.
   - En el celular, una columna y nada encimado.
3. **Registrar una clínica con el correo de tu super-administrador** y abrir el enlace: debe salir «Esta cuenta administra la plataforma… Regístrala con otro correo», sin botón.
4. **Páginas de los correos (local, `logs/correos/`).**
   - Invita a una persona nueva y abre «Activar cuenta».
   - Pide «¿Olvidaste tu contraseña?» y abre el enlace.
   - Abre un enlace ya usado: «Enlace no disponible» con el mismo diseño.
   - Revisa también «Elige cómo entrar» y «Completa tu perfil».
5. **Un correo real en Gmail** (producción o tu cuenta con Brevo), en tema claro y oscuro: logotipo visible, botón azul y enlace debajo.

### M0.2 — Revisión (2026-10-12)

**Revisó:** Claude (arquitecto). **Resultado: aprobada**, con un arreglo de commit y pendiente de la prueba del usuario.

**Verificado.** PHP 605 pruebas en verde en una copia limpia sin `.env` (29 MySQL omitidas); JS 101/101 en el equipo del usuario; `views/auth`, `views/plataforma` y `views/partials` sin `style=`, `on…=` ni `<script>` con código; ninguna vista referencia los CSS y el parcial retirados. El panel sigue la decisión del usuario durante M0.2 (reutilizar tal cual los componentes de Usuarios, sin franja de indicadores, solo azul y grises), que reemplaza la franja propuesta en el alcance. El escape de la plantilla de correo cierra un hallazgo real: el título del correo de registro llevaba el nombre de clínica escrito por el público sin escapar.

**Arreglo de commit.** Los commits de M0.2 quedaron sin `views/plataforma/estado_clinica.php` (preparado pero sin commit), y `inicio.php` ya lo incluye en dos sitios: desplegado así, el panel fallaría. Va en un commit aparte antes del push.

**Observaciones (no bloquean).** El `@import ...?v=4` de `dashboard.css` no se renueva con la alfa (queda para el módulo de la clínica). Falta ver un correo real en Gmail claro y oscuro: se mira tras el despliegue.
