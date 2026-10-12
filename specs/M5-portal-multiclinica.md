# M5 — Portal multi-clínica y vínculos

> Estado: borrador (pendiente de aprobación del usuario)
> Entrega: v2.0 · Fecha: 2026-10-12 · Sale como `2.0.0-alpha.3`
> Responsable de escritura: Claude Code (decisión del usuario, 2026-10-12) · Revisión independiente: Claude (arquitecto)
> Rama: `v2/m5-portal`, creada desde `v2/m0` (que ya contiene todo M0 y su pulido).

## 1. Resultado y límites

**Resultado:** el propietario entra una sola vez (contraseña o Google), ve todas sus mascotas con la historia de todas sus clínicas, elige la clínica solo cuando agenda o cancela, se vincula o desvincula de clínicas y decide qué clínica ve la historia de las otras; el veterinario vincula una mascota que ya existe en la plataforma en lugar de duplicarla y ve lo que otras clínicas registraron según lo autorizado. Todo con el marco visual pulido en M0.1/M0.2.

**Situación (M0, 2.0.0-alpha.2):** la mayor parte de la lógica ya existe y tiene pruebas, construida dentro de M0:

| HU | Qué se hizo en M0 | Dónde |
|---|---|---|
| HU-T.18 Google | RE-T.18.2–4: política antes de crear la cuenta, completar perfil obligatorio, vincular Google a la cuenta existente | D1 (`RegistroGoogleTest`, Modelos §11 y §13.1) |
| HU-5.9 Portal multi-clínica | Portal sin clínica activa, historia de todas las clínicas con su clínica (RN-114), elegir clínica al agendar, acciones acotadas | C6 (`PortalPropietarioTest`, `PortalAgendaTest`) |
| HU-1.5 Vincular mascota existente | Búsqueda exacta con mascotas básicas, vincular sin duplicar ni asignar HC; límite del plan en E2 | C3 (`PropietarioClinicaTest`, `MascotaAccesoTest`), E2 (`LimitePlanTest`) |
| HU-5.12 Autorizar historia compartida | Interruptor por clínica, revocación inmediata, auditoría | C6 (`VinculosPropietarioTest`) |
| HU-5.13 Vincularse / desvincularse | Vincular con un clic, rechazo con citas sin resolver, RN-115 en solo lectura | C6 (`VinculosPropietarioTest`, `HistoriaCompartidaTest`) |
| HU-2.10 Historia compartida | Siempre lo básico, alergias, vacunas y desparasitaciones; consultas solo con autorización; sin revelar que existen; solo lectura | C4 (`HistoriaCompartidaTest`, `ConsultaHistorialTest`) |

Lo que falta es, sobre todo: verificar cada RE contra el código actual (M0 cambió mucho después de C3–C6), cerrar los huecos que aparezcan, los diagramas que faltan y **la interfaz del portal**, que conserva el diseño de la v1 y no recibió el pulido de M0.1/M0.2. Por eso las seis HU siguen «Planificada (v2)» (decisión del Anexo F0).

**Destino v2:** [MER](../documentacion/MER.md) (`propietario_clinica`, `mascota_clinica`, `autoriza_historia_compartida`), [Modelos](../documentacion/Modelos.md) §9 (aislamiento), §10 (login del propietario), §11 (registro del propietario), §12.3 (registro de mascota y vínculo de la existente) y §13.1 (completar perfil).

**Fuera de alcance:**
- Calificar al veterinario (HU-5.9 la nombra; es del módulo 8).
- Reprogramar desde el portal (HU-5.6, diferida desde C5: el propietario solo cancela).
- Carnet y QR (HU-5.10, HU-5.11, QR de RE-5.8.4): módulo 5 al final de la v2.0.
- Uso de alergias y alertas por el Grafo I al prescribir (criterio 4 de HU-2.10): módulo 2 (HU-2.9).
- Paneles del veterinario y de la clínica fuera de lo que tocan HU-1.5 y HU-2.10: se rehacen en los módulos 2 y 4.
- Eliminar la cuenta (HU-5.14, v2.1).

## 2. Trazabilidad

| HU | RE incluidos | RN/RNF | Etapa | Evidencia de aceptación |
|---|---|---|---|---|
| HU-T.18 | RE-T.18.1–4 | RN-G20, RN-G21, RN-G19, RN-G12, RN-109 | A, C1, C2 | `RegistroGoogleTest` revisada en A; RE-T.18.1 (sin Google en el registro de clínica) con prueba; pantalla de completar perfil con el marco común |
| HU-5.9 | RE-5.9.1–4 | RN-109, RN-114, RN-G13 | A, C2 | `PortalPropietarioTest`, `PortalAgendaTest`; recorrido del usuario con dos clínicas |
| HU-1.5 | RE-1.5.1–3 | RN-003, RN-102, RN-110, RN-111 | A, C1, C3 | `PropietarioClinicaTest`, `MascotaAccesoTest`, `LimitePlanTest` (vincular al límite) |
| HU-5.12 | RE-5.12.1–4 | RN-113, RN-115, RN-G07 | A, C2 | `VinculosPropietarioTest` |
| HU-5.13 | RE-5.13.1–4 | RN-109, RN-110, RN-115 | A, C2 | `VinculosPropietarioTest`, `HistoriaCompartidaTest` |
| HU-2.10 | RE-2.10.1–4 | RN-112, RN-113 | A, C3 | `HistoriaCompartidaTest`, `ConsultaHistorialTest`; la clínica de cada registro visible en la historia del veterinario |

Los criterios normativos permanecen en `documentacion/`; este plan los enlaza y organiza.

## 3. Etapas y dependencias

| Etapa | Entregable comprobable | Depende de | Escribe | Revisa |
|---|---|---|---|---|
| **A. Inventario y verificación** (solo lectura del código; escribe solo el Anexo A y, si faltan, borradores de diagramas) | Por cada RE de §2: la prueba que lo cubre, si pasa hoy y si el código actual (después de M0.1/M0.2) sigue haciendo lo que dice. Lista de huecos con archivo y línea. Inventario de pantallas del portal y del lado del personal que tocan estas HU: vistas, JS, CSS, `style=`/`on…=`/`<script>` en línea que queden, pendientes de `pulido-interfaz.md` que les corresponden, y una propuesta de estructura visual con el marco de M0.1/M0.2. Diagramas: confirmar §9–§13 y proponer el que falta (vínculos del propietario: vincular, desvincular y autorizar). | M0 | Claude Code | Claude |
| **C1. Huecos funcionales** | Lo que A encuentre que no cumple un RE, con su prueba. | A y decisiones de §6 | Claude Code | Claude |
| **C2. Portal del propietario pulido** | Inicio con las mascotas de todas las clínicas, historia con la clínica de cada registro, agendar eligiendo clínica, «Mis clínicas» (vincular, desvincular, autorizar con aviso breve), perfil y completar perfil, con el marco y los componentes de M0.1/M0.2; respuesta inmediata en pantalla; restricción de entrada; celular. | C1 | Claude Code | Claude |
| **C3. Lado del personal** | Vincular la mascota existente (HU-1.5) y la historia compartida (HU-2.10) claras en la vista del veterinario: la clínica de cada registro y lo ajeno en solo lectura, sin rediseñar el resto del panel. | C1 | Claude Code | Claude |
| **F. Despliegue** | `2.0.0-alpha.3`: HU a «Implementada», revisiones de documentos, descargas, merge a `main` y tag. | C2, C3 | Usuario, con comandos del agente | Claude |

Si A demuestra que C1 queda vacío o muy pequeño, C1 se une a C2.

## 4. Datos y migraciones

- **No se espera cambio de esquema.** Las tablas existen desde M0.
- La foto de Google no se guarda (decisión 1 de §6), así que M5 no tiene migraciones.

## 5. Código, permisos y pantallas

- **Modelos y helpers:** `ModeloPropietario`, `MascotaPropietario`, `HistoriaPropietario`, `VinculosPropietario`, `ModeloClinica::enClinicaDelPropietario()`, `Mascota::vincular()`, `RegistroPropietario`, `LimitePlan`.
- **Controladores:** `PortalController`, `CitaController::agendarDesdePortalAjax`, `MascotaController::vincularMascotaAjax`, `AuthController` (Google), `IdentidadController` (completar perfil).
- **Matriz de `Security.php`:** sin acciones nuevas previstas; si aparece una, se agrega y se prueba.
- **Vistas y JS/CSS:** `views/portal/` (`index.php`, `layout.php`, `imprimir_historial.php`), `public/js/portal.js` y su CSS; en el personal, solo el modal de vincular mascota y la presentación de la historia compartida.
- **Aislamiento:** las excepciones globales (propietario y mascota; RN-113) ya tienen prueba; cada cambio vuelve a pasar `SeguridadClinicaTest` y las de dos clínicas. Observación de C6 vigente: la copia de `enClinicaDelPropietario()` no debe usarse para métodos del personal.

## 6. Riesgos y decisiones pendientes

| Asunto | Efecto | Resolución o dato requerido antes de implementar |
|---|---|---|
| 1. **Foto de Google** | — | **Decisión del usuario (2026-10-12): no se guarda.** Era de la versión inicial. Se ajustaron HU-T.18, RE-T.18.2, RN-G20 y Modelos §11 sin subir revisión. No hay cambio de esquema en M5. |
| 2. **Desfase entre C3–C6 y el código actual** | M0.1/M0.2 cambiaron acceso, validaciones y estilos después de C6 | La etapa A verifica RE por RE antes de tocar nada. |
| 3. **Interfaz del portal heredada de la v1** | Inconsistente con el acceso y el panel ya pulidos | C2 usa los mismos componentes; no se inventa otro estilo. |
| 4. **Pantallas del veterinario que se rehacen en M2/M4** | Pulir de más sería trabajo perdido | C3 solo toca lo de HU-1.5 y HU-2.10. |

## 7. Tareas y verificación

- [x] Aprobar este plan (usuario). §6.1 decidida: la foto de Google no se guarda.
- [x] A — Inventario y verificación (Claude Code; revisada y aprobada por Claude, 2026-10-12; decisiones del usuario en «A — Revisión»). **Entregada, pendiente de revisión (2026-10-12)**: ver «Anexo A — Resultado».
- [ ] C1 — Huecos funcionales (Claude Code). **Entregada, pendiente de revisión (2026-10-12)**: ver «Anexo C1 — Resultado».
- [ ] C1.1 — Super-administrador nunca como cliente y desactivación por la clínica (Claude Code). **Entregada, pendiente de revisión (2026-10-12)**: ver «Anexo C1.1 — Resultado».
- [ ] C2 — Portal del propietario pulido.
- [ ] C3 — Lado del personal (HU-1.5, HU-2.10).
- [ ] `vendor/bin/phpunit` completo, JS y cada RE de §2 con evidencia.
- [ ] F — `2.0.0-alpha.3`: HU a «Implementada», revisiones de documentos, historial, descargas, merge y tag.
- [ ] Comandos de commit por etapa para PowerShell (el usuario hace ramas, commits, push y tags).

**Resultado de pruebas:** pendiente.

## Anexo A — Resultado

**Escribió:** Claude Code (2026-10-12). **Estado:** entregada, pendiente de revisión. Solo lectura del código: no se cambió código ni pruebas. Se escribió este anexo y el borrador del diagrama Modelos §11.1.

Se releyeron `AGENTS.md`, `agentes/metodo.md`, este plan, los Anexos C3, C4, C6 y D1 de M0 con sus revisiones, los Anexos M0.1, M0.2 y M0.2.1, HU-T.18, HU-5.9, HU-1.5, HU-5.12, HU-5.13 y HU-2.10 con sus RE, RN-109 a RN-115, RN-G12, RN-G13 y RN-G19 a RN-G21, y Modelos §9, §10, §11, §12.3 y §13.1.

### 1. Lo más riesgoso primero (huecos)

| # | Hueco | RE / RN | Dónde | Propuesta |
|---|---|---|---|---|
| 1 | **La clínica sigue viendo los datos nuevos del propietario después de que se desvincula.** Clientes lista también los vínculos inactivos y lee correo, teléfono y documento en vivo de `usuarios`: si el propietario cambia su teléfono después de irse, la clínica lo ve. | RE-5.12.3, RN-115 | `models/PropietarioClinica.php:11` (`BASICOS`) y `:14-21` (`listar()` sin filtro de `pc.estado`); `obtener()` `:23-27` usa la misma lista | Con el vínculo inactivo, la clínica ve solo el nombre y la marca «Desvinculado», sin correo, teléfono ni documento. Prueba: desvincular, cambiar el teléfono y comprobar que la clínica no lo recibe. **Decisión 1.** |
| 2 | **La búsqueda exacta muestra nombre y mascotas de una persona que no está vinculada a la clínica.** Basta con escribir su documento o correo completo. §12.3 muestra las mascotas solo **después** de ligar al propietario, y RN-109 dice que cada clínica solo ve a sus propietarios vinculados. C3 aprobó «mascotas básicas», pero no dijo si antes o después del vínculo. | RE-1.5.1, RN-109, RN-111 | `models/PropietarioClinica.php:36-44`; `public/js/medical-module.js:907-938`; la prueba lo fija en `PropietarioClinicaTest::testBusquedaGlobalSoloExactaConMascotasBasicas` | Sin vínculo: «Hay una cuenta con ese dato. Pide la confirmación por correo», sin nombre ni mascotas. Con el vínculo confirmado, sus mascotas (HU-1.5). Hay que ajustar la prueba. **Decisión 2.** |
| 3 | **Alergias y alertas: la tabla `alertas_medicas` existe, pero ningún código la lee ni la escribe.** Ni la historia del veterinario ni el portal las muestran. Registrarlas es RE-2.1.5, que pertenece al módulo 2. | RE-2.10.1, RN-113, RN-114 | `database/01_schema.sql:421-436`; ningún archivo de `models/`, `controllers/`, `views/` ni `public/js/` la nombra | En M5 se construye **la lectura**: alertas activas de todas las clínicas, con su clínica, en la historia del veterinario y en el portal, probadas con filas del fixture. El registro llega con RE-2.1.5 en el módulo 2. Alternativa: cerrar RE-2.10.1 con nota, como RE-0.3.6. **Decisión 3.** |
| 4 | **«Registrarse con Google» desde la clínica X, con una cuenta ya verificada:** entra al portal, pero no queda ligada a X. Solo se ligan las cuentas pendientes, y además a la clínica de su registro pendiente, no a X. §11 (nodo GG4) dice «ligarla a esta clínica si no lo estaba». | RN-109, RE-T.18.4, Modelos §11 | `controllers/AuthController.php:928-934`; `models/RegistroPropietario.php:139-157` | Ligarla a X con auditoría cuando llega desde el registro con la clínica elegida, porque Google ya probó el correo. El botón «Entrar con Google» no manda clínica y sigue igual. Alternativa: corregir el diagrama, porque se vincula con un clic desde «Mis clínicas». **Decisión 4.** |
| 5 | **Completar perfil con un documento que ya usa otra cuenta** no crea el caso de soporte que pide §13.1 (nodo D2). El cambio de documento (RN-G24) sí lo crea. | RE-T.18.3, Modelos §13.1 | `models/RegistroPropietario.php:172-175`; el patrón está en `models/CuentaTitular.php:318-327` (`registrarCasoDocumento`) | Reutilizar ese registro de caso (llevarlo a un solo lugar) y probarlo. C1. |
| 6 | **La historia del veterinario no avisa que la mascota está desvinculada.** El servidor manda `solo_lectura` y `vinculo` (`Mascota::getParaHistorial`), pero ningún JS los usa: la pantalla ofrece registrar consulta o vacuna y el servidor responde 403. | RN-115, RE-2.10.4 | `models/Mascota.php:38-53`; `public/js/medical-module.js` (ningún uso de `solo_lectura`) | Aviso «El propietario se desvinculó: solo lectura» y sin botones de registro. C3. |
| 7 | **RE-T.18.1 cumple, pero sin prueba.** `registro_clinica.php` no ofrece Google; nada lo comprueba. | RE-T.18.1 | `views/auth/registro_clinica.php`; Google solo en `views/auth/login.php:122` y `:233` | Prueba de la vista y del controlador del registro de clínica. C1. |
| 8 | **Con una sola clínica, el `select` de clínica sigue a la vista** (con una opción ya elegida). RE-5.9.3 pide ofrecer la selección solo si hay varias. No hay prueba de la vista. | RE-5.9.3 | `views/portal/index.php:677-682` | Con una clínica, su nombre como texto fijo y el id en un campo oculto; prueba de JS. C2. |

**Menores (van en la etapa que toca la pantalla):**
- `oninput` en línea en el buscador de servicios: `views/portal/index.php:195` (C2).
- Los formularios de mascota del portal muestran los errores de validación en un SweetAlert y no validan mientras se escribe: `public/js/portal.js:1914` (C2).
- El portal recarga la página entera al vincular, desvincular, cancelar o registrar una mascota (`zookiRecargarConAviso`). Además, `PortalController::index()` vuelve a consultar las clínicas una vez por cada mascota (`PortalController.php:61` → `:101`) (C2).
- En el lado del personal, vincular una mascota recarga sin aviso, y el JS cambia `style.display` y encadena varias sentencias por línea: `public/js/medical-module.js:931-932` y `:946-949` (C3).
- `layout.php` agrega sufijos escritos a mano (`-c91`, `-d2`) a `App::assetVersion()`, en `:35`, `:107` y `:109`. No hacen daño, pero sobran (C2).

### 2. RE → prueba → estado

Las suites pasan hoy: PHP 608 sin MySQL y 608 con MySQL, JS 108 (ver Verificación). Por eso «pasa» vale para todas las pruebas citadas.

| RE | Prueba (archivo::método) | Estado | Código |
|---|---|---|---|
| RE-T.18.1 | — | **Cumple sin prueba suficiente** (hueco 7) | `views/auth/registro_clinica.php`; preselección de la clínica del enlace en `public/js/login.js:389-392` |
| RE-T.18.2 | `RegistroGoogleTest::testSinAceptarLaPoliticaNoSeGuardaNiLaIdentidadNiElVinculo`, `::testConAceptacionQuedaEnLaClinicaConPruebaYPerfilIncompleto` | Cumple. La foto no se guarda (decisión 2026-10-12) | `controllers/AuthController.php:936-951` (solo correo, nombre y uid en la sesión) |
| RE-T.18.3 | `RegistroGoogleTest::testConElPerfilIncompletoNoAgendaNiRegistraMascotasHastaCompletarlo` | Cumple. Falta el caso de soporte de §13.1 (hueco 5) | `models/RegistroPropietario.php:160-180`; `controllers/CuentaController.php:56-76` |
| RE-T.18.4 | `RegistroGoogleTest::testUnaCuentaVerificadaConservaSuContrasenaAlEntrarConGoogle`, `::testUnaCuentaPendienteQuedaVerificadaSinContrasenaYConPerfilPorConfirmar`, `::testUnaCuentaDeGoogleYaLigadaNoSeReasigna` | Cumple. La parte de ligar a la clínica es el hueco 4 | `models/RegistroPropietario.php:139-157` |
| RE-5.9.1 | `AuthTest::testUnaCredencialInvalidaNoDistingueSiLaCuentaExiste`, `::testUnaCuentaInactivaNoEntra`, `::testUnCorreoSinVerificarNoEntra`; `GoogleTokenTest` (12) | Cumple | `controllers/AuthController.php:877-934` |
| RE-5.9.2 | `PortalPropietarioTest::testVeLaHistoriaDeSuMascotaEnTodasLasClinicasConSuClinica`, `::testDesvinculadoDeUnaClinicaSigueViendoSuHistoria` | Cumple. Sin alergias ni alertas (hueco 3) | `controllers/PortalController.php:129-151`; `HistoriaPropietario` |
| RE-5.9.3 | `PortalAgendaTest::testAgendarEnUnaClinicaNoVinculadaSeRechazaAunqueMandeElId` (servidor) | **Cumple sin prueba suficiente** (hueco 8) | `views/portal/index.php:677-682`; `public/js/portal.js:1544` |
| RE-5.9.4 | `PortalAgendaTest` (8), `CitaTest::testUnaCitaDeANoSeVeNiSeModificaDesdeB`, `BaseV2MysqlTest::testC6PortalEnElEsquemaReal` | Cumple | `models/ModeloClinica.php:35-55` (`enClinicaDelPropietario`) |
| RE-1.5.1 | `PropietarioClinicaTest::testBusquedaGlobalSoloExactaConMascotasBasicas` | Cumple lo que dice el RE (sin datos clínicos). Antes del vínculo muestra de más (hueco 2) | `models/PropietarioClinica.php:29-47` |
| RE-1.5.2 | `MascotaAccesoTest::testConfirmarPropietarioYVincularMascotaNoDuplicaNiAsignaHC`; `ConsultaHistorialTest::testElNumeroDeHistoriaSeAsignaUnaVezYNoSeRepiteDentroDeLaClinica` (Luna vinculada a Sur recibe `HC-000001` en su primera consulta allí) | Cumple | `models/Mascota.php:116-148`; `models/Consulta.php:313-353` |
| RE-1.5.3 | `LimitePlanTest::testMascota51CreadaYVinculadaRechazadasSinDuplicacion`, `PortalAgendaTest::testE2PortalCuentaVincularMascotaExistenteComoCupo`; auditoría `vinculo_clinica` en `MascotaAccesoTest::testConfirmarPropietarioYVincularMascotaNoDuplicaNiAsignaHC` | Cumple | `models/Mascota.php:121-143` (`LimitePlan::consumir('mascotas')`) |
| RE-5.12.1 | `VinculosPropietarioTest::testAutorizarYRevocarCambianLoQueVeLaOtraClinicaAlInstante`, `::testSoloAutorizaClinicasConVinculoActivo` | Cumple | `models/VinculosPropietario.php:120-133`; `views/portal/index.php:494-497` |
| RE-5.12.2 | El mismo `testAutorizarYRevocar…`; `HistoriaCompartidaTest::testConAutorizacionBLasVeMarcadasYAlRevocarDejaDeVerlas` | Cumple. La autorización se lee de la base en cada petición | `models/ModeloHistoria.php:29-55` |
| RE-5.12.3 | `VinculosPropietarioTest::testTrasDesvincularseLaClinicaLeeSusRegistrosYNoRegistraNadaNuevo`; `HistoriaCompartidaTest::testConElVinculoInactivoLaClinicaSoloLeeLoSuyo`, `::testUnaAutorizacionDeUnVinculoInactivoNoCuenta` | **No cumple del todo**: la autorización y los registros sí; los datos nuevos del propietario siguen visibles (hueco 1) | `models/VinculosPropietario.php:89-117`; `models/PropietarioClinica.php:14-21` |
| RE-5.12.4 | `VinculosPropietarioTest::testAutorizarYRevocar…` (líneas 65-66) | Cumple | `models/VinculosPropietario.php:131-132` |
| RE-5.13.1 | `VinculosPropietarioTest::testVincularseAUnaClinicaActivaNoLeExponeSusMascotas` | Cumple | `models/VinculosPropietario.php:60-81` |
| RE-5.13.2 | El mismo; `PortalAgendaTest::testAgendarConLaMascotaSinVincularLaVincula` | Cumple | `models/VinculosPropietario.php:55-59` |
| RE-5.13.3 | `VinculosPropietarioTest::testConCitasSinResolverNoSeDesvincula` | Cumple (pendiente, confirmada, en curso y sin cerrar; decisión 4 de C6) | `models/VinculosPropietario.php:14`, `:96-103` |
| RE-5.13.4 | `VinculosPropietarioTest` (líneas 94 y 134) | Cumple | `models/VinculosPropietario.php:80`, `:115` |
| RE-2.10.1 | `HistoriaCompartidaTest::testBVeLasVacunasYDesparasitacionesDeAConLaClinicaQueLasAplico` | **No cumple del todo**: vacunas y desparasitaciones sí; alergias y alertas no existen en el código (hueco 3) | `models/Vacuna.php:59-83`; `models/Desparasitacion.php:66-86` |
| RE-2.10.2 | `HistoriaCompartidaTest::testSinAutorizacionBNoVeNiSabeDeLasConsultasDeA`, `::testElHistorialPorPeticionDirectaNoRevelaLasConsultasDeA`, `::testConAutorizacion…`; `AutorizacionRolTest::testVerArchivoEsDeLaClinicaYDelPortalNoDeLaPlataforma` | Cumple | `models/ModeloHistoria.php:29-55`; `models/ArchivoClinico.php:61-110` |
| RE-2.10.3 | `HistoriaCompartidaTest::testSinAutorizacionBNoVeNiSabeDeLasConsultasDeA`, `::testElHistorialPorPeticionDirectaNoRevelaLasConsultasDeA` | Cumple | El mismo |
| RE-2.10.4 | `HistoriaCompartidaTest::testBNoModificaNingunRegistroDeAYQuedaEnAuditoria`, `::testBNoAgregaTratamientosNiAdjuntosAUnaConsultaDeA` | Cumple en el servidor. La pantalla no avisa del solo lectura de una mascota desvinculada (hueco 6) | `paraModificar()` de cada modelo de historia |

### 3. Inventario de pantallas

| Pantalla | Qué hace | JS y CSS | En línea | Pendientes que le tocan |
|---|---|---|---|---|
| `views/portal/layout.php` (117 l.) | Marco: menú lateral en escritorio (≥ 1024 px) y barra inferior con botón central «Agendar» en celular y tableta; contexto actual y salir. | CSS: `contexto.css`, `portal/{base,navegacion,inicio,servicios,agenda,mascota,cuenta,ventanas,calendario,formularios}.css` (3570 l.), `validacion-cuenta.css`, flatpickr. Iconos: Font Awesome **y** Remix. JS: `avisos.js`, `password-policy.js`, `portal.js`, `csrf.js`, `sesion.js`, `interacciones.js`, `cuenta-password.js`, Google GSI, SweetAlert2. | Nada | Sufijos de versión escritos a mano; dos bibliotecas de iconos. |
| `views/portal/index.php` (861 l.), Inicio | Saludo, «Mis compañeros», próxima cita, accesos rápidos y el horario de cada clínica vinculada (o «Aún no tienes clínicas»). | `portal/inicio.css`, `portal.js` | Nada | — |
| Servicios | Lista fija de tres servicios (filtro al escribir) y veterinarios de sus clínicas. | `portal/servicios.css`; `portal.js:147` y `:160` | `oninput="filtrarServicios(this.value)"` (l. 195) | Restricción de entrada D3.1 («eventos en línea ajenos… filtro de servicios»). |
| Agenda | «Mis citas» (cancelar solo en clínicas con vínculo activo) y calendario de salud (próximas dosis e historial). | `portal/agenda.css`, `calendario.css` | Nada | Recarga entera al cancelar. |
| Alertas | Pantalla casi vacía («No tienes alertas…»); se llena con recordatorios de vacunas. | `portal.js` | Nada | Las alertas médicas no existen (hueco 3); el centro de notificaciones es HU-5.7 (v2.1). |
| Perfil | Datos, contacto (teléfono con la regla común), «Mis clínicas» (interruptor de historia compartida, desvincularme y vincularme), datos de acceso, contraseña y salir. | `portal/cuenta.css`; `portal.js:2052-2106`; `validacion-cuenta.js` | Nada | «Pantalla 3–4 s tarde»: C9.1 ya no espera al aviso (recarga y lo muestra después), pero recarga todo. Vincular y desvincular deberían actualizar la tarjeta en su sitio. |
| Detalle de mascota | Pestañas Historial · Citas · Vacunas · Desparasitaciones, con la clínica de cada registro; editar e imprimir. | `portal/mascota.css`, `portal.js` | Nada | Sin alertas (hueco 3). |
| Ventanas (agendar, mascota nueva o editada, detalle de cita) | Agendar empieza por la clínica; mascota con especie y raza; detalle con resultado clínico y receta. | `portal/ventanas.css`, `formularios.css`, `portal.js:1343-1650`, `:1880-1935` | Nada | Errores en SweetAlert (`:1914`); sin validación en tiempo real ni restricción de entrada en mascota; el `select` de clínica con una sola (hueco 8); «Modales: mejorar su diseño». |
| `views/portal/imprimir_historial.php` (153 l.) | Versión imprimible con la clínica de cada registro (RE-5.5). | `imprimir-historial.css`, `imprimir-historial.js`, `interacciones.js` | Nada | Sin alertas (hueco 3). |
| `views/auth/completar_perfil.php` (50 l.) | Documento y teléfono obligatorios de una cuenta de Google. Ya tiene el marco del acceso de M0.2. | `registro-cuenta.css`, `validacion-cuenta.js`, `interacciones.js` | Nada | Caso de soporte por documento duplicado (hueco 5). |
| Personal: «Nueva mascota» → buscar propietario (`views/vet/pacientes.php:709-722`) | Búsqueda exacta por documento o correo; mascotas del propietario con «Vincular X» o «Es otra mascota: registrar nueva»; sin vínculo, «Solicitar confirmación por correo». | `pacientes.css`; `medical-module.js:903-950` | Nada en la vista; el JS cambia `style.display` | Hueco 2; recarga sin aviso; código de C3 con varias sentencias por línea; usar las tarjetas mini de Usuarios. |
| Personal: historia de la mascota | Línea de tiempo de consultas, vacunas y desparasitaciones; lo de otra clínica lleva su nombre e icono, con «solo lectura» en el `title`. | `medical-module.js:1156-1162` (`etiquetaOrigen`), `:1252-1300`, `:1903-1930` | Nada | Hueco 6 (solo lectura); hueco 3 (alertas); la marca de solo lectura solo se ve al pasar el ratón. |

### 4. Propuesta de estructura visual del portal (C2, para aprobar)

Se reutilizan las piezas de M0.1, M0.2 y M0.2.1, solo en azul y grises:
- cabecera blanca (`header-container-white`);
- pestañas (`tabs-wrapper`);
- tarjetas de Usuarios (`person-card`, `card-*-mini`, `tag-mini`, `action-btn-mini`);
- interruptor (`toggle-switch`) y tarjetas de elección de «Gestionar»;
- modal con pie a la derecha (`users-modal__footer`);
- avisos de una línea bajo el campo y estados vacíos compactos.

El marco actual (menú lateral y barra inferior) se conserva.

**Secciones**:

1. **Inicio:** saludo, la próxima cita en una tarjeta y las mascotas en tarjetas. Cada tarjeta lleva foto o iniciales, nombre, especie y raza, las clínicas donde se atiende como etiquetas y su próxima dosis. Debajo, el horario de hoy de cada clínica, compacto («Abierta hasta las 6:00 p. m.»).
2. **Mascota** (al tocar una tarjeta):
   - Cabecera con foto, nombre y etiquetas.
   - Pestañas Historia · Vacunas · Desparasitaciones · Citas, y Alertas si se aprueba la decisión 3.
   - Cada registro lleva la etiqueta de su clínica (RN-114).
   - Filtro por clínica si tiene varias; «Editar» e «Imprimir» como botones de icono.
3. **Citas:** pestañas Próximas, Pasadas y Calendario de salud. Tarjetas con mascota, clínica, veterinario y estado. «Cancelar» confirma, porque es lo único irreversible.
4. **Agendar** (modal):
   - Paso a paso: clínica, mascota, tipo, veterinario, día y hora, y resumen.
   - Clínica y mascota como tarjetas de elección; con una sola clínica, solo su nombre (hueco 8). Tipo y veterinario siguen como selector.
   - Pie con «Agendar».
5. **Clínicas** (sección propia, hoy enterrada en Perfil):
   - Una tarjeta por clínica vinculada: nombre, dirección, abierta o cerrada hoy, el interruptor «Puede ver la historia que registraron otras clínicas» con su ayuda, y el botón de icono «Desvincularme».
   - Desvincularse es directo con aviso breve. El rechazo por citas sin resolver aparece en la tarjeta, en una línea.
   - Abajo, «Otras clínicas en Zooki»: buscador corto y tarjetas con «Vincularme».
   - Todo se actualiza en su sitio con la respuesta del servidor, sin recargar la página.
6. **Perfil:** datos, contacto, datos de acceso, contraseña y salir, en tarjetas plegables como hoy.

**Servicios** se retira como sección: es una lista fija y «Agendar» ya hace ese papel. **Alertas** pasa a un punto en el menú hasta HU-5.7.

```
Escritorio (≥ 1024 px)                         Celular (< 768 px)
┌───────────┬─────────────────────────────┐    ┌───────────────────────┐
│ Zooki     │ Hola, Fabio                 │    │ Hola, Fabio      (FA) │
│[+ Agendar]│ ┌─Próxima cita───────────┐  │    │ ┌Próxima cita───────┐ │
│ Inicio    │ └────────────────────────┘  │    │ └───────────────────┘ │
│ Citas     │ ┌Luna──┐ ┌Kira──┐ ┌Milo─┐  │    │ ┌Luna───────────────┐ │
│ Clínicas  │ │Norte │ │Sur   │ │Norte│  │    │ │Norte · Sur        │ │
│ Perfil    │ └──────┘ └──────┘ └─────┘  │    │ └───────────────────┘ │
│           │ Horario de hoy: Norte · Sur │    │ ┌Kira───────────────┐ │
│ (FA) Fabio│                             │    ├───────────────────────┤
└───────────┴─────────────────────────────┘    │Inicio Citas (+) Clín. Perfil│
                                               └───────────────────────┘
```

En celular: una columna; las tarjetas ocupan todo el ancho; los modales se abren como hoja a pantalla completa con el pie fijo; tablet (768–1023 px) usa dos columnas de tarjetas con la barra inferior.

### 5. Diagramas

| Diagrama | ¿Coincide con el código? |
|---|---|
| §9 Acceso y aislamiento | Sí. El portal no tiene clínica activa: cada acción en una clínica pasa por `enClinicaDelPropietario()`, que es la rama «el recurso es de su clínica». |
| §10 Login del propietario | Sí, con el matiz del hueco 8: con una clínica el `select` sigue a la vista, aunque ya elegida. Cancelar no pide clínica, porque la cita ya tiene la suya. |
| §11 Registro del propietario | **No del todo**: el nodo GG4 liga a la clínica una cuenta existente que entra con Google, y el código solo liga las pendientes (hueco 4). |
| §12.3 Registro de mascota | **No del todo**: el diagrama muestra las mascotas después de ligar al propietario, y el código las muestra antes (hueco 2). El resto coincide: el límite se comprueba al guardar o al vincular, no se duplica, se conservan el carnet y el token, y queda auditoría. |
| §13.1 Completar el perfil | **No del todo**: falta el caso de soporte por documento duplicado (hueco 5). |
| **§11.1 Vínculos del propietario (nuevo, borrador)** | Se agregó a `Modelos.md`: vincularse, desvincularse con la regla de citas sin resolver y RN-115, y autorizar o revocar la historia compartida. Sigue al código de `VinculosPropietario`. Para la parte de RN-115 que dice «ni los datos nuevos», el código no cumple (hueco 1). Renderiza con Mermaid CLI. |

### 6. Decisiones pendientes del usuario (antes de C1)

1. **Hueco 1:** con el vínculo inactivo, ¿la clínica ve solo el nombre y la marca «Desvinculado»? Recomendado: sí. ¿Vale también cuando es la clínica la que desactivó el vínculo (D1)?
2. **Hueco 2:** ¿las mascotas del propietario se muestran solo cuando ya está vinculado a la clínica, como dice §12.3? Recomendado: sí, sin nombre ni mascotas antes de la confirmación.
3. **Hueco 3:** ¿M5 construye la lectura de alergias y alertas y deja el registro para el módulo 2? Recomendado: sí.
4. **Hueco 4:** ¿«Registrarse con Google» desde una clínica liga a esa clínica una cuenta ya verificada? Recomendado: sí, con auditoría.
5. **Propuesta visual (§4):** ¿«Clínicas» pasa a sección propia y se retira «Servicios»?

**Alcance propuesto para C1** (huecos funcionales): los huecos 1 a 5 y 7, según lo que se decida. C2 lleva el hueco 8 y la propuesta visual; C3, el hueco 6 y el modal de vincular mascota.

### 7. Verificación

- `vendor/bin/phpunit` sin MySQL: **608 en verde**, 3487 aserciones, 29 omitidas (las de MySQL).
- Con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1` y la base por defecto `zooki_test_base_v2`: **608 en verde**, 3849 aserciones, ninguna omitida.
- `node --test "tests/Frontend/**/*.test.cjs"`: **108 en verde**.
- El diagrama §11.1 se renderizó con Mermaid CLI (`@mermaid-js/mermaid-cli` con el Chrome del equipo): 47 nodos, sin error de sintaxis.
- No se leyó ni escribió `.env`, ni se tocó `zooki_v2_prueba`. No se cambió código ni pruebas.

### A — Revisión (2026-10-12)

**Revisó:** Claude (arquitecto). **Resultado: aprobada.** Inventario completo y preciso: se comprobaron en el código los huecos 1 (`PropietarioClinica::listar()` sin filtro de `pc.estado` y con correo, teléfono y documento en vivo), 2 (`buscarExacto()` devuelve nombre, contacto y mascotas sin mirar el vínculo) y 3 (`alertas_medicas` no aparece en ningún modelo, controlador, vista ni JS). El diagrama §11.1 queda como base de C1.

**Decisiones del usuario (2026-10-12).**

1. **Huecos 1 y 2 — solo con vínculo activo.** Con el vínculo inactivo (lo haya desactivado el propietario o la clínica), la clínica ve solo el nombre y la marca «Desvinculado», sin correo, teléfono ni documento. La búsqueda exacta de una persona no vinculada responde «Hay una cuenta con ese dato. Pide la confirmación por correo», sin nombre, contacto ni mascotas; las mascotas (HU-1.5) aparecen cuando el vínculo ya está confirmado, como en §12.3.
2. **Hueco 3 — alergias y alertas, todo en el módulo 2.** Registrar y mostrar van juntas con el Grafo I. RE-2.10.1 se cierra en M5 para datos básicos, vacunas y desparasitaciones, con nota de que alergias y alertas llegan con el módulo 2 (como RE-0.3.6).
3. **Hueco 4 — Google desde una clínica.** «Registrarme con Google» desde la clínica X liga a X una cuenta ya verificada, con auditoría (como dice §11). «Entrar con Google» sin clínica no cambia.
4. **Portal (propuesta visual §4 del Anexo A) aprobada:** menú Inicio · Citas · Clínicas · Perfil con «Agendar» al centro; «Clínicas» es sección propia; se retira «Servicios»; «Alertas» sale del menú hasta HU-5.7; todo se actualiza en su sitio sin recargar.

**Alcance de C1:** huecos 1, 2, 4, 5 y 7, la nota de RE-2.10.1 y los diagramas §11, §12.3 y §13.1 alineados. C2: hueco 8, menores del portal y la propuesta visual. C3: hueco 6 y el modal de vincular mascota.

## Anexo C1 — Resultado

**Escribió:** Claude Code (2026-10-12). **Estado:** entregada, pendiente de revisión. Sin migración ni cambio de esquema.

**Decisión del usuario durante C1 (2026-10-12).** Con el vínculo inactivo, la clínica **no edita** los datos del cliente: «Editar» no se ofrece y el servidor lo rechaza («El cliente está desvinculado: sus datos solo los cambia él»). Hasta ahora D1 permitía cambiar nombre y teléfono, y eso escribía a ciegas en la cuenta global. Se ajustó la prueba de D1.

### Qué se hizo (lo más riesgoso primero)

1. **Hueco 1 — RN-115 en todas las lecturas del personal.**
   - `helpers/ContactoPropietario.php` es el único lugar de la regla: tipo y número de documento, correo y teléfono salen solo con el vínculo `propietario_clinica` activo en esa clínica (`NULL` si no), más la marca `desvinculado`.
   - La usan las dos listas de clientes (`PropietarioClinica::listar()`/`obtener()`, y por tanto `get_propietario_ajax`, y `Usuario::propietariosDeClinica()` de Usuarios), la ficha y la búsqueda de mascotas (`Mascota::ficha()`: el documento del propietario y la búsqueda por documento) y las citas (`Cita::selectCita()`: correo y teléfono).
   - Vale igual si el vínculo lo desactivó el propietario o la clínica.
   - Pantallas: la tarjeta y la tabla de Clientes dicen «Desvinculado», sin documento ni «Editar»; el detalle muestra «Desvinculado» en los datos de contacto; la lista de Pacientes, igual.
   - Para reactivarlo, la clínica pide la confirmación **por el id** (`PropietarioClinica::solicitarVinculoDe`, solo con quien tuvo vínculo con ella; si no, 403): ya no necesita ver el correo.
2. **Hueco 2 — búsqueda exacta (RE-1.5.1, RN-109).** Sin vínculo activo (nunca vinculado o desvinculado, propietario o personal), la respuesta es siempre la misma: `{vinculado: false, mensaje: "Hay una cuenta con ese dato. Pide la confirmación por correo."}`, sin id, nombre, contacto ni mascotas. Con el vínculo activo, sus datos y sus mascotas básicas. La solicitud por correo funciona como antes. En `medical-module.js` solo cambió lo necesario: el aviso del servidor y el botón; el nombre y las mascotas, solo con vínculo.
3. **Hueco 4 — Google desde una clínica (RN-G21, §11 GG4).** `RegistroPropietario::vincularGoogle()` recibe la clínica de «Registrarme con Google» (`AuthController::clinicaDelRegistroGoogle()`). Si la cuenta verificada no estaba ligada a esa clínica, la liga con auditoría en la clínica. Un vínculo inactivo se reactiva como desde el portal. «Entrar con Google» no manda clínica y no cambia nada. Nunca liga a un super-administrador (RN-G13) ni a una clínica que no esté activa.
4. **Hueco 5 — caso de soporte al completar el perfil (§13.1 D2).** `models/CasoSoporte.php::documentoDuplicado()` es el único registro del caso: lo usan el cambio de documento (`CuentaTitular`, RN-G24, igual que antes) y «Completar perfil». El caso es uno abierto por titular y documento; al titular se le responde siempre «No se pudo completar el perfil. Revisa los datos o contacta a soporte», sin decir de quién es. También cubre la carrera en la que otra cuenta ocupa el documento entre la comprobación y el guardado.
5. **Hueco 7 — RE-T.18.1** con prueba de la pantalla y del controlador.
6. **RE-2.10.1 y HU-2.10:** nota de que las alergias y alertas llegan con el módulo 2 junto a su registro (RE-2.1.5). Sin subir revisión.
7. **Diagramas:**
   - §11 GG4: reactivación como desde el portal y nunca al super-administrador.
   - §11.1: ya no es borrador. El nodo de RN-115 dice que la clínica ve solo el nombre y la marca, y una nota explica la misma regla cuando desactiva la clínica.
   - §12.3: sin vínculo, solo el aviso; se pide la confirmación y se espera.
   - §13.1: un solo caso abierto.
   - Los cuatro renderizan con Mermaid CLI.

### RE y pruebas

| Punto | Prueba |
|---|---|
| RE-5.12.3, RN-115: el propietario se desvincula y cambia su contacto; la clínica no lo recibe (listas, ficha, `get_propietario_ajax`) | `M5C1HuecosTest::testSiElPropietarioSeDesvinculaLaClinicaNoRecibeSuTelefonoNuevo`; control con vínculo activo en `testConElVinculoActivoLaClinicaVeElContacto` |
| Lo mismo cuando desactiva la clínica, también en la ficha, la búsqueda por documento y las citas | `M5C1HuecosTest::testSiLaClinicaDesactivaElVinculoTampocoVeElContactoNiEnLaFichaNiEnLasCitas`; `UsuariosVistaTest::testClientesDeLaClinicaConInterruptorEditarYVer` (marca, sin documento ni «Editar») |
| Decisión: no se edita; se reactiva por el id | `VinculoReactivacionTest::testUnVinculoInactivoNoSeEditaDesdeLaClinica`; `M5C1HuecosTest::testUnClienteDesvinculadoNoSeEditaYSeReactivaPidiendoLaConfirmacionPorSuId`, `testNoSePideConfirmacionPorIdAQuienNuncaFueClienteDeLaClinica` |
| RE-1.5.1, RN-109: búsqueda exacta y aislamiento entre clínicas | `PropietarioClinicaTest::testBusquedaExactaSinVinculoSoloDiceQueHayUnaCuenta`, `testBusquedaExactaConVinculoActivoMuestraSusMascotasBasicas`; `M5C1HuecosTest::testSurNoVeAlPropietarioDeNorteNiPorListaNiPorBusqueda`, `testLaBusquedaNoDistingueNuncaVinculadoDeDesvinculado`; JS: `M5C1Busqueda.test.cjs` (2) |
| RN-G21, §11 GG4 | `M5C1HuecosTest::testRegistrarmeConGoogleDesdeSurLigaLaCuentaVerificadaConAuditoria`, `testUnVinculoInactivoSeReactivaComoDesdeElPortal`, `testEntrarConGoogleSinClinicaYUnVinculoYaActivoNoCambianNada`, `testNoLigaAlSuperAdministradorNiAUnaClinicaSuspendida`, `testElControladorSoloTomaLaClinicaDelRegistro` |
| RE-T.18.3, §13.1 D2 | `M5C1HuecosTest::testDocumentoDeOtraCuentaAbreUnSoloCasoSinDecirDeQuienEs`; `RegistroGoogleTest::testConElPerfilIncompletoNoAgendaNiRegistraMascotasHastaCompletarlo` (ahora también comprueba el caso); `CuentasD2Test` sigue igual (RN-G24) |
| RE-T.18.1 | `M5C1RegistroClinicaSinGoogleTest::testLaPantallaDelRegistroDeClinicaNoOfreceGoogle`, `testElControladorNoAceptaUnaIdentidadDeGoogle` |

Mutaciones comprobadas: entregar el contacto sin mirar el vínculo, quitar la liga de Google y no abrir el caso de soporte hacen fallar sus pruebas.

### Verificación

- `vendor/bin/phpunit`: **624 en verde** (16 nuevas). Sin MySQL: 3579 aserciones y 29 omitidas. Con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1` y la base por defecto `zooki_test_base_v2`: 3941 aserciones y ninguna omitida.
- `node --test "tests/Frontend/**/*.test.cjs"`: **110 en verde** (2 nuevas).
- `php -l` y `node --check` de lo tocado; vistas tocadas sin `style=`, `on…=` ni `<script>` con código.
- No se leyó ni escribió `.env`, ni se tocó `zooki_v2_prueba`.

### Pendientes y riesgos

- **Hueco de seguridad que encontré (anterior a C1, no lo toqué):** la clínica puede pedir el vínculo de una cuenta de **super-administrador** (`solicitarVinculo` no la excluye) y, si esa persona confirma el correo, quedaría como propietaria de la clínica, en contra de RN-G13. Propuesta: misma respuesta, sin crear la solicitud ni enviar el correo, como ya hace el registro del propietario.
- Cuando la **clínica** desactiva el vínculo, las mascotas siguen vinculadas (`mascota_clinica` activo): la clínica sigue registrando consultas y enviando recordatorios automáticos al correo del propietario. Cuando es el propietario quien se desvincula, las mascotas sí quedan inactivas. Hay que decidir si la desactivación por la clínica debe hacer lo mismo.
- Con el vínculo inactivo, los correos de cita que envía la clínica (confirmar o cancelar) ya no salen, porque el correo llega vacío. Es coherente con RN-115.
- El flujo completo de Google (con token real) no se recorrió. Se probaron el modelo y la toma de la clínica en el controlador.
- C2: portal. C3: modal de vincular mascota y aviso de solo lectura (en Clientes, pedir la confirmación todavía abre una confirmación que no es grave).

### Pantallas para el usuario

1. **Ana (Norte) → Usuarios › Clientes:** desactiva el vínculo de un cliente. La tarjeta debe quedar con su nombre y «Desvinculado», sin documento, teléfono ni «Editar»; «Ver» dice «Desvinculado» en los datos. Vuelve a activar el interruptor: se envía la solicitud (correo en `logs/correos/`); al confirmarla, los datos reaparecen.
2. **Diego (Sur) → Pacientes › Nueva mascota:** busca el documento de Fabio (solo es cliente de Norte). Debe salir solo «Hay una cuenta con ese dato. Pide la confirmación por correo» y el botón, sin nombre ni mascotas. Con un cliente vinculado a Sur, su nombre y sus mascotas.
3. **Registro con Google** (si tienes Google configurado en local): desde el registro, eligiendo Sur y con una cuenta que ya existe, entra y Sur aparece en «Mis clínicas». Sin Google: en «Completar perfil», un documento que ya existe da el mensaje genérico y el caso aparece en la pestaña Soporte del super-administrador.

### C1 — Revisión (2026-10-12)

**Revisó:** Claude (arquitecto). **Resultado: aprobada.** PHP 624 pruebas en verde en una copia limpia sin `.env` (29 MySQL omitidas); JS 110/110 en el equipo del usuario. La regla de RN-115 vive en un solo lugar (`helpers/ContactoPropietario`: documento, correo y teléfono solo con el vínculo activo) y la usan las listas de clientes, la ficha, la búsqueda por documento y las citas. La búsqueda exacta sin vínculo responde igual para «nunca vinculado» y «desvinculado». Google desde una clínica liga la cuenta verificada con auditoría y nunca a un super-administrador ni a una clínica que no esté activa. La decisión del usuario durante C1 (con el vínculo inactivo la clínica no edita los datos del cliente) queda en el Anexo.

**Decisiones del usuario sobre los pendientes de C1 (2026-10-12).**

1. **Super-administrador como cliente (hueco de seguridad anterior a C1, RN-G13):** se corrige ya, en C1.1. La clínica recibe la misma respuesta de siempre, pero no se crea la solicitud ni se envía el correo, como en el registro del propietario. Vale también para la búsqueda exacta y la solicitud por id.
2. **La clínica desactiva el vínculo de un cliente:** sus mascotas quedan inactivas en esa clínica, igual que cuando se desvincula el propietario (solo lectura de lo suyo, sin registros nuevos ni recordatorios). Se reactivan cuando el vínculo se confirma de nuevo y la mascota se agenda o se atiende allí (RN-110).

## C1.1 — Alcance (2026-10-12)

Las dos decisiones anteriores, con sus pruebas, y §11.1 alineado. Escribe Claude Code; revisa Claude.

## Anexo C1.1 — Resultado

**Escribió:** Claude Code (2026-10-12). **Estado:** entregada, pendiente de revisión. Sin migración ni cambio de esquema.

**Decisión del usuario durante C1.1 (2026-10-12).** La clínica **no puede desactivar** el vínculo de un cliente con citas sin resolver en ella (pendiente, confirmada, en curso o sin cerrar). Es la misma regla que se aplica al propietario (RE-5.13.3). Antes, desactivar habría dejado esas citas sin poder atenderse.

### Qué se hizo

1. **RN-G13: el super-administrador nunca es cliente.**
   - **Búsqueda exacta:** responde «Hay una cuenta con ese dato…», como con cualquier cuenta sin vínculo, aunque exista un vínculo anterior.
   - **Solicitud por documento o correo y solicitud por id:** dan la misma respuesta de siempre («Se envió la solicitud…»), pero sin crear la solicitud ni enviar el correo. Queda una auditoría `OTHER` con RN-G13.
   - **Enlace viejo:** `confirmarVinculo` lo rechaza como enlace no disponible y no liga a la cuenta.
   - **Registro del propietario:** ya se comportaba así; se agregó su prueba.
2. **La clínica desactiva el vínculo (RN-115).**
   - `models/VinculoClinica.php` reúne en un solo lugar la regla de citas sin resolver y la desactivación: vínculo inactivo, autorización de historia en 0 y mascotas del propietario inactivas en esa clínica.
   - Lo usan el portal (`VinculosPropietario::desvincular`) y Clientes (`PropietarioClinica::actualizar`), cada uno dentro de su transacción y con auditoría. La de Clientes incluye `mascotas_desvinculadas`.
   - Efecto: la clínica solo lee lo suyo, no registra nada nuevo (403) y no salen sus recordatorios, porque `Recordatorio` ya filtraba por `mascota_clinica` activo.
   - Al confirmar otra vez el vínculo, la mascota vuelve cuando se agenda o se atiende allí (RN-110, `Mascota::vincular`, que cuenta para el plan).
3. **Modelos:**
   - §11.1 tiene una segunda entrada, «Clientes, en la clínica», con el rechazo por citas, y su nota explica las mascotas, la reactivación y RN-G13.
   - §12.3 indica que a un super-administrador no se le envía nada.
   - Los dos renderizan con Mermaid CLI.

### RE y pruebas

| Punto | Prueba |
|---|---|
| RN-G13: la solicitud por correo da la misma respuesta, sin solicitud ni correo | `M5C11VinculosTest::testLaSolicitudPorCorreoAUnSuperAdministradorRespondeIgualSinCrearNiEnviar` (controlador; compara con la respuesta para una cuenta cualquiera) |
| RN-G13: búsqueda exacta y solicitud por id (modelo y controlador) | `testLaBusquedaYLaSolicitudPorIdTampocoDistinguenNiLigan` |
| RN-G13: enlace viejo y registro del propietario | `testUnEnlaceViejoNoLigaAlSuperAdministrador`, `testElRegistroDelPropietarioTampocoLeEnviaUnVinculo` |
| RN-115: mascotas inactivas, autorización en 0, auditoría; Sur no cambia | `testDesactivarDesdeLaClinicaDejaLasMascotasInactivasSoloEnEsaClinica` |
| Decisión: con citas sin resolver no se desactiva | `testConCitasSinResolverLaClinicaNoDesactivaNiTocaNada` |
| Sin consulta ni vacuna (403); recordatorios solo de Sur | `testLaClinicaNoRegistraConsultaNiVacunaYNoSalenSusRecordatorios` |
| RN-110: reconfirmar no reactiva sola a la mascota; agendar sí | `testAlReconfirmarYAgendarLaMascotaSeReactiva` |
| La ficha no muestra el documento con datos anteriores a C1.1 | `M5C1HuecosTest::testLaFichaNoMuestraElDocumentoSiSoloElVinculoDelPropietarioEstaInactivo` (separada de la de C1, porque ahora Luna queda inactiva) |

Mutaciones comprobadas: no excluir al super-administrador al pedir el vínculo, no excluirlo al confirmar y desactivar sin las mascotas hacen fallar sus pruebas.

### Verificación

- `vendor/bin/phpunit`: **633 en verde** (9 nuevas). Sin MySQL: 3618 aserciones y 29 omitidas. Con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1` y la base por defecto `zooki_test_base_v2`: 3980 aserciones y ninguna omitida.
- JS: **110 en verde** (C1.1 no cambió JS).
- No se leyó ni escribió `.env`, ni se tocó `zooki_v2_prueba`.

### Pendientes

- El estado de cita `pausada` (en el esquema, para HU-4.14) no está entre las «citas sin resolver». Hoy ningún código lo usa. Hay que sumarlo cuando llegue el módulo 4.
- C2: portal. C3: modal de vincular mascota y aviso de solo lectura en la historia del veterinario.

### Pantallas para el usuario (C1 y C1.1)

1. **Ana (Norte) → Usuarios › Clientes → desactivar a Fabio.**
   - Primero, con una cita pendiente de Luna en Norte, debe salir «Tiene 1 cita sin resolver…» y nada cambia.
   - Cancela la cita y desactívalo: su tarjeta queda con el nombre y «Desvinculado», sin documento, teléfono ni «Editar».
   - En Pacientes, Luna ya no aparece en Norte; en Sur sigue igual.
2. **Reactivar a Fabio:** vuelve a activar el interruptor. La solicitud llega a `logs/correos/`; confírmala. Sus datos reaparecen, pero Luna sigue fuera de Norte. Con Fabio, agenda a Luna en Norte desde el portal: Luna vuelve a Pacientes de Norte.
3. **Diego (Sur) → Pacientes › Nueva mascota:**
   - Busca el documento de un propietario que no es de Sur. Debe salir solo «Hay una cuenta con ese dato. Pide la confirmación por correo», sin nombre ni mascotas.
   - Busca el correo del super-administrador y pulsa «Solicitar confirmación»: dice «Se envió la solicitud…», pero en `logs/correos/` no aparece ningún correo nuevo.
4. **Completar perfil** con un documento que ya existe: mensaje genérico, y el caso aparece en la pestaña Soporte del super-administrador. Si tienes Google en local: «Registrarme con Google» desde Sur con una cuenta existente deja a Sur en «Mis clínicas».

### C1.1 — Revisión (2026-10-12)

**Revisó:** Claude (arquitecto). **Resultado: aprobada**, pendiente de la prueba del usuario (las cuatro pantallas del Anexo C1.1 juntan C1 y C1.1). PHP 633 pruebas en verde en una copia limpia sin `.env` (29 MySQL omitidas); JS 110/110. `models/VinculoClinica` deja en un solo lugar la regla de citas sin resolver y la desactivación (vínculo, autorización y mascotas), y la usan el portal y Clientes dentro de su transacción. El super-administrador recibe la misma respuesta por las tres vías sin solicitud ni correo, y un enlace viejo no lo liga. La decisión del usuario durante C1.1 (la clínica tampoco desactiva con citas sin resolver) queda en el Anexo.

**Para el módulo 4:** cuando se use el estado de cita `pausada` (HU-4.14), sumarlo a `VinculoClinica::CITAS_SIN_RESOLVER`.

## C2 — Alcance (2026-10-12)

El portal del propietario con la estructura aprobada (Anexo A §4 y decisión 4 de «A — Revisión»), el hueco 8 y los menores del portal del Anexo A. Escribe Claude Code; revisa Claude.
