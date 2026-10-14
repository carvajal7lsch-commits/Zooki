# M5 — Portal multi-clínica y vínculos

> Estado: F entregada, pendiente de revisión (2026-10-14); despliegue pendiente del usuario
> Entrega: v2.0 · Fecha: 2026-10-12 · Sale como `2.0.0-alpha.3`
> Responsable de escritura: Claude Code (implementación y avance de F); Codex (retoma y completa F, 2026-10-14) · Revisión independiente: Claude (arquitecto)
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

| HU | RE incluido | RN/RNF | Etapa | Evidencia final de aceptación |
|---|---|---|---|---|
| HU-T.18 | RE-T.18.1 | RN-G20 | C1 | M5C1RegistroClinicaSinGoogleTest::testLaPantallaDelRegistroDeClinicaNoOfreceGoogle; M5C1RegistroClinicaSinGoogleTest::testElControladorNoAceptaUnaIdentidadDeGoogle |
| HU-T.18 | RE-T.18.2 | RN-G19/G20 | C1 | RegistroGoogleTest::testSinAceptarLaPoliticaNoSeGuardaNiLaIdentidadNiElVinculo; RegistroGoogleTest::testConAceptacionQuedaEnLaClinicaConPruebaYPerfilIncompleto |
| HU-T.18 | RE-T.18.3 | RN-G20 | C1 | RegistroGoogleTest::testConElPerfilIncompletoNoAgendaNiRegistraMascotasHastaCompletarlo; M5C1HuecosTest::testDocumentoDeOtraCuentaAbreUnSoloCasoSinDecirDeQuienEs |
| HU-T.18 | RE-T.18.4 | RN-G21, RN-109 | C1, C4b | RegistroGoogleTest::testUnaCuentaVerificadaConservaSuContrasenaAlEntrarConGoogle; M5C1HuecosTest::testRegistrarmeConGoogleDesdeSurLigaLaCuentaVerificadaConAuditoria; M5C4bPersonalTest::testGoogleConUnaCuentaPendientePideAceptarLaPoliticaYLaActivaSinContrasena |
| HU-5.9 | RE-5.9.1 | RN-G15 | A, C1 | AuthTest::testUnaCredencialInvalidaNoDistingueSiLaCuentaExiste; AuthTest::testUnCorreoSinVerificarNoEntra; GoogleTokenTest |
| HU-5.9 | RE-5.9.2 | RN-114, RN-G02 | C2 | PortalPropietarioTest::testVeLaHistoriaDeSuMascotaEnTodasLasClinicasConSuClinica; PortalPropietarioTest::testDesvinculadoDeUnaClinicaSigueViendoSuHistoria; M5C2PortalTest::testLaFichaTraeLasClinicasDeLaMascotaYLaDeCadaRegistro. Alergias/alertas: módulo 2. |
| HU-5.9 | RE-5.9.3 | RN-109 | C2b, C3 | M5C2bPortalTest::testConUnaSolaClinicaAgendarMuestraSuNombreYElIdOculto; M5C2bPortalTest::testConVariasClinicasSonTarjetasDeEleccionSoloDeLasVinculadas; M5C2bPortal.test.cjs y M5C3Personal.test.cjs |
| HU-5.9 | RE-5.9.4 | RN-G13 | C2, C3 | PortalAgendaTest::testAgendarEnUnaClinicaNoVinculadaSeRechazaAunqueMandeElId; CitaTest::testUnaCitaDeANoSeVeNiSeModificaDesdeB; BaseV2MysqlTest::testC6PortalEnElEsquemaReal |
| HU-1.5 | RE-1.5.1 | RN-109/111 | C1, C3, C4b | PropietarioClinicaTest::testBusquedaExactaSinVinculoSoloDiceQueHayUnaCuenta; PropietarioClinicaTest::testBusquedaExactaConVinculoActivoMuestraSusMascotasBasicas; M5C4bPersonalTest::testPorNombreSoloSalenLosClientesActivosDeLaClinica |
| HU-1.5 | RE-1.5.2 | RN-102/110 | C3 | MascotaAccesoTest::testConfirmarPropietarioYVincularMascotaNoDuplicaNiAsignaHC; ConsultaHistorialTest::testElNumeroDeHistoriaSeAsignaUnaVezYNoSeRepiteDentroDeLaClinica |
| HU-1.5 | RE-1.5.3 | RN-003/110 | C3 | LimitePlanTest::testMascota51CreadaYVinculadaRechazadasSinDuplicacion; MascotaAccesoTest::testConfirmarPropietarioYVincularMascotaNoDuplicaNiAsignaHC (auditoría); M5C3Personal.test.cjs (vincular sin recarga) |
| HU-5.12 | RE-5.12.1 | RN-113 | C2b | VinculosPropietarioTest::testSoloAutorizaClinicasConVinculoActivo; M5C2bPortalTest::testClinicasEnFilasConInterruptorAvisoYBuscador |
| HU-5.12 | RE-5.12.2 | RN-113 | C1, C2 | VinculosPropietarioTest::testAutorizarYRevocarCambianLoQueVeLaOtraClinicaAlInstante; HistoriaCompartidaTest::testConAutorizacionBLasVeMarcadasYAlRevocarDejaDeVerlas |
| HU-5.12 | RE-5.12.3 | RN-115 | C1, C1.1, C3 | M5C1HuecosTest::testSiElPropietarioSeDesvinculaLaClinicaNoRecibeSuTelefonoNuevo; M5C1HuecosTest::testSiLaClinicaDesactivaElVinculoTampocoVeElContactoNiEnLaFichaNiEnLasCitas; HistoriaCompartidaTest::testConElVinculoInactivoLaClinicaSoloLeeLoSuyo |
| HU-5.12 | RE-5.12.4 | RN-G07 | C2 | VinculosPropietarioTest::testAutorizarYRevocarCambianLoQueVeLaOtraClinicaAlInstante (usuario, clínica y autorización en auditoría) |
| HU-5.13 | RE-5.13.1 | RN-109/110 | C2b | VinculosPropietarioTest::testVincularseAUnaClinicaActivaNoLeExponeSusMascotas |
| HU-5.13 | RE-5.13.2 | RN-109 | C2b | M5C2bPortalTest::testConVariasClinicasSonTarjetasDeEleccionSoloDeLasVinculadas; PortalAgendaTest::testAgendarConLaMascotaSinVincularLaVincula |
| HU-5.13 | RE-5.13.3 | RN-115 | C1.1, C2b | VinculosPropietarioTest::testConCitasSinResolverNoSeDesvincula; M5C11VinculosTest::testConCitasSinResolverLaClinicaNoDesactivaNiTocaNada |
| HU-5.13 | RE-5.13.4 | RN-G07 | C2 | VinculosPropietarioTest::testVincularseAUnaClinicaActivaNoLeExponeSusMascotas; VinculosPropietarioTest::testTrasDesvincularseLaClinicaLeeSusRegistrosYNoRegistraNadaNuevo (auditoría) |
| HU-5.13 | RE-5.13.5 | RN-116 | C3 | M5C3TopeClinicasTest: cuarta rechazada, desvincular libera, suspendida no cuenta, la clínica puede ligar con tres y aviso del portal |
| HU-2.10 | RE-2.10.1 | RN-113 | C1, C3 | HistoriaCompartidaTest::testBVeLasVacunasYDesparasitacionesDeAConLaClinicaQueLasAplico. Cerrado para datos básicos, vacunas y desparasitaciones; alergias y alertas llegan con el módulo 2 (RE-2.1.5). |
| HU-2.10 | RE-2.10.2 | RN-112/113 | C1, C3 | HistoriaCompartidaTest::testSinAutorizacionBNoVeNiSabeDeLasConsultasDeA; HistoriaCompartidaTest::testConAutorizacionBLasVeMarcadasYAlRevocarDejaDeVerlas; AutorizacionRolTest::testVerArchivoEsDeLaClinicaYDelPortalNoDeLaPlataforma |
| HU-2.10 | RE-2.10.3 | RN-113 | C1 | HistoriaCompartidaTest::testSinAutorizacionBNoVeNiSabeDeLasConsultasDeA; HistoriaCompartidaTest::testElHistorialPorPeticionDirectaNoRevelaLasConsultasDeA |
| HU-2.10 | RE-2.10.4 | RN-112/115 | C3 | HistoriaCompartidaTest::testBNoModificaNingunRegistroDeAYQuedaEnAuditoria; HistoriaCompartidaTest::testBNoAgregaTratamientosNiAdjuntosAUnaConsultaDeA; M5C3Personal.test.cjs (solo lectura visible sin botones) |

Las pruebas citadas pasan en la verificación final de F. La excepción de RE-2.10.1 permanece explícita; no se declara implementado el módulo 2.

Los criterios normativos permanecen en `documentacion/`; este plan los enlaza y organiza.

## 3. Etapas y dependencias

| Etapa | Entregable comprobable | Depende de | Escribe | Revisa |
|---|---|---|---|---|
| **A. Inventario y verificación** (solo lectura del código; escribe solo el Anexo A y, si faltan, borradores de diagramas) | Por cada RE de §2: la prueba que lo cubre, si pasa hoy y si el código actual (después de M0.1/M0.2) sigue haciendo lo que dice. Lista de huecos con archivo y línea. Inventario de pantallas del portal y del lado del personal que tocan estas HU: vistas, JS, CSS, `style=`/`on…=`/`<script>` en línea que queden, pendientes de `pulido-interfaz.md` que les corresponden, y una propuesta de estructura visual con el marco de M0.1/M0.2. Diagramas: confirmar §9–§13 y proponer el que falta (vínculos del propietario: vincular, desvincular y autorizar). | M0 | Claude Code | Claude |
| **C1. Huecos funcionales** | Lo que A encuentre que no cumple un RE, con su prueba. | A y decisiones de §6 | Claude Code | Claude |
| **C2. Portal del propietario pulido** | Inicio con las mascotas de todas las clínicas, historia con la clínica de cada registro, agendar eligiendo clínica, «Mis clínicas» (vincular, desvincular, autorizar con aviso breve), perfil y completar perfil, con el marco y los componentes de M0.1/M0.2; respuesta inmediata en pantalla; restricción de entrada; celular. | C1 | Claude Code | Claude |
| **C3. Lado del personal** | Vincular la mascota existente (HU-1.5) y la historia compartida (HU-2.10) claras en la vista del veterinario: la clínica de cada registro y lo ajeno en solo lectura, sin rediseñar el resto del panel. | C1 | Claude Code | Claude |
| **C4. Pulido** | Portal y lado de la clínica, según las decisiones y capturas del usuario; C4a y C4b. | C2, C3 | Claude Code | Claude |
| **F. Cierre** | `2.0.0-alpha.3`: tope de activación, HU y reglas con estado, revisiones documentales, descargas y evidencia final. Merge y tag pendientes del usuario. | C4a, C4b | Claude Code (avance); Codex (cierre); despliega el usuario | Claude |

Si A demuestra que C1 queda vacío o muy pequeño, C1 se une a C2.

## 4. Datos y migraciones

- Las tablas existen desde M0; no se guarda la foto de Google.
- **Migración 06**, aprobada durante C4b: agrega `activacion_propietario` al ENUM del medio en `consentimientos_datos`. Es repetible, conserva los valores anteriores y las filas; se aplica automáticamente al arrancar. F no agrega ni modifica migraciones.

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

- [x] Aprobar este plan (usuario).
- [x] A — Inventario y verificación; revisada y aprobada (2026-10-12).
- [x] C1 y C1.1 — Huecos y vínculos; revisadas y aprobadas (2026-10-12).
- [x] C2a y C2b — Portal; revisadas y aprobadas (2026-10-12).
- [x] C3 — Lado del personal, alta pendiente, tope de clínicas y Agendar; revisada, aprobada y probada por el usuario (2026-10-13).
- [x] C4a y C4b — Pulido; revisadas y aprobadas por Claude (2026-10-13), con los ajustes del usuario de C4b (2026-10-14).
- [x] F — Cierre de M5 y `2.0.0-alpha.3`: **entregada, pendiente de revisión (2026-10-14)**. Ver «Anexo F — Resultado»; todavía no desplegada.
- [x] Suites PHP y JS completas y evidencia final por RE en §2; resultados en el Anexo F.
- [x] Revisiones documentales publicadas una vez; PDF/Word regenerados y comprobación `--revisar` en el Anexo F.
- [x] Comandos de commit y despliegue entregados; los ejecuta el usuario.
- [ ] Merge a `main`, tag y despliegue de `2.0.0-alpha.3` (usuario, después de revisión).
- [ ] Tres comprobaciones de producción del Anexo F (usuario).

**Resultado de pruebas:** verificación final en el Anexo F.

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

### Prueba del usuario de C1 y C1.1 (2026-10-12)

El usuario probó la desactivación de un cliente y la búsqueda del veterinario; las demás pruebas las cubren las automáticas (completar perfil solo se llega con una cuenta de Google, y la revisión no dijo con qué usuario: error de la revisión). Ana (administradora) no ve Pacientes, así que la comprobación «Luna ya no aparece en Norte» es con Beto (veterinario). Hallazgos, todos del lado del personal, **van en C3**:

1. **Confirmación al desactivar un cliente** (`public/js/usuarios.js:330`): «Su cuenta y sus otras clínicas no cambian» da información de más (insinúa que tiene otras clínicas) y el diálogo usa el estilo por defecto de SweetAlert2. Debe decir solo lo que pasa en esta clínica, por ejemplo: «¿Desactivar a Fabio Propietario como cliente? Dejarás de ver sus datos de contacto y sus mascotas quedarán inactivas aquí. Para volver, él debe confirmarlo por correo.», con el estilo de Zooki. Se mantiene la confirmación: deshacerlo depende del propietario.
2. **Búsqueda del propietario en «Nueva mascota» no es en tiempo real:** el aviso «Hay una cuenta con ese dato…» o los datos del cliente deben salir mientras se escribe el documento o el correo completo (con espera corta y límite por IP, como la comprobación del NIT), no al enviar.
3. **Alta del propietario por el personal sin aceptación presencial (decisión del usuario, 2026-10-12).** El modal «Nuevo registro → Propietario» pide «Soy el titular y estoy presente» y «Acepto la política»: más trabajo para la clínica y poco lógico. El personal registra al propietario normalmente (con correo obligatorio); el propietario recibe un correo con un enlace donde **él mismo** acepta la política y crea su contraseña para activar su portal, como la activación del personal. Cambia RE-T.19.1, HU-T.19, RN-109 y Modelos §11 y §12.3 (sin subir revisión).
   - **Propuesta de la revisión para los detalles** (el usuario puede ajustarla al pegar el prompt): mientras el propietario no active, la cuenta queda pendiente, sin portal ni contraseña; la clínica sí registra a la mascota y la atiende, porque son registros de la clínica; no salen correos automáticos (recordatorios) a esa dirección hasta que acepte, salvo el de activación; el enlace vence en 72 horas y la clínica puede reenviarlo; una cuenta pendiente **no se borra** si ya tiene mascotas o registros clínicos.

**Ajustes del usuario a esos hallazgos (2026-10-12).** Punto 1: el mensaje propuesto es demasiado largo y explícito; la confirmación debe ser corta, solo la pregunta («¿Desactivar a Fabio Propietario como cliente?») y los botones, con el estilo de Zooki. Punto 3: confirmado, se quita la aceptación presencial y se corrige todo diagrama o documento que la describa (Modelos §11 y §12.3, HU-T.19, RE-T.19.1, RN-109 y los que aparezcan); los detalles propuestos por la revisión quedan como base de C3 (el usuario no los objetó).

## Anexo C2a — Resultado

**Escribió:** Claude Code (2026-10-12). **Estado:** entregada, pendiente de revisión. Sin migración.

**División de C2, como prevé el prompt.** El portal tiene una vista de 861 líneas, un JS de 2106 y diez hojas de estilo. C2 se partió en dos:

- **C2a, esta entrega:** estructura, navegación, Inicio, Mascota y Citas, más los menores que les tocan.
- **C2b, pendiente de que el usuario la confirme:** Agendar por pasos (con la clínica única, hueco 8), Clínicas con tarjetas, Perfil en tarjetas plegables, actualización en su sitio sin recargar, y validación en tiempo real con restricción de entrada en los formularios de mascota.

En C2a, «Clínicas» ya es una sección propia, pero conserva su contenido y su comportamiento de antes.

### Qué se hizo

1. **Menú: Inicio · Citas · Clínicas · Perfil.**
   - El marco se conserva: menú lateral con «Agendar» en escritorio y barra inferior con «Agendar» al centro en celular y tableta.
   - Salen «Servicios» (pantalla, buscador con `oninput`, `filtrarServicios`, `loadVetsExplore` y `portal/servicios.css`) y «Alertas» (pantalla, campana, punto del menú y `loadPortalAlerts`) hasta HU-5.7.
   - Cada sección tiene su dirección (RE-5.1.6). Las anteriores redirigen: `#agenda` va a Citas; `#servicios` y `#recordatorios`, a Inicio.
2. **Componentes de M0.1, M0.2 y M0.2.1.**
   - El portal carga `css/usuarios.css` (tarjeta, etiquetas, avatar de iniciales, botones de icono, pestañas) y `portal/base.css` le da sus 10 tokens.
   - `portal/componentes.css` trae lo propio del portal: cabecera, cuadrícula de una, dos o más columnas, `tag-mini--azul`, estado vacío compacto y horario de hoy.
   - Solo azul y grises.
3. **Inicio:**
   - La próxima cita, en tarjeta.
   - Las mascotas en tarjetas: foto o iniciales, especie y raza, clínicas como etiquetas (o «Sin clínica todavía») y la próxima dosis. Una dosis renovada ya no cuenta (`helpers/ProximaDosis.php`, la regla de `Recordatorio`).
   - El horario de hoy de cada clínica, en compacto: abierta o cerrada, detalle y la semana desplegable (RE-5.1.7).
   - Se retiran «Accesos rápidos» y el saludo con la campana.
4. **Mascota:**
   - Cabecera con avatar, nombre, etiquetas de especie y raza, y las clínicas donde se atiende (la ficha ahora trae `clinicas`).
   - «Editar» e «Imprimir» son botones de icono.
   - Pestañas: Historia · Vacunas · Desparasitaciones · Citas.
   - La clínica de cada registro aparece como etiqueta (RN-114).
   - Con registros de dos o más clínicas, unos chips filtran por clínica.
5. **Citas:**
   - Pestañas Próximas (con contador), Pasadas y Calendario de salud. Próximas son las abiertas de hoy en adelante, la más cercana primero (`helpers/PresentacionPortal.php`).
   - Tarjetas con mascota, día y hora, clínica, tipo, veterinario y estado.
   - «Cancelar cita» solo aparece en una clínica con vínculo activo, y es lo único que confirma.
   - El filtro por mascota actúa en las dos listas.
   - El calendario y sus listas no cambian de lógica.
6. **`PortalController::index()` con un número fijo de consultas:** citas, vacunas, desparasitaciones y clínicas de todas las mascotas llegan en una consulta cada una (`HistoriaPropietario::…DeSusMascotas`, `MascotaPropietario::clinicasDeSusMascotas`). Antes eran tres o cuatro consultas por mascota, más las clínicas otra vez por cada una. Ahora son 13 consultas con 1 mascota y 13 con 6.
7. **Menores del Anexo A:**
   - Sin `oninput`.
   - Sin sufijos de versión escritos a mano.
   - Una sola biblioteca de iconos (Remix): fuera Font Awesome del layout y de la página imprimible.
   - El controlador usa `require` del layout, en lugar de `require_once`, para poder pintarlo dos veces en una prueba.

### RE y pruebas

| Punto | Prueba |
|---|---|
| RN-G02, RE-5.1.2: solo lo del propietario | `M5C2PortalTest::testElPortalSoloMuestraLoDelPropietario` (la mascota y la cita de otro propietario no aparecen) |
| `index()` con consultas fijas | `M5C2PortalTest::testIndexHaceElMismoNumeroDeConsultasConUnaOConSeisMascotas`. La mutación que consulta por mascota la hace fallar (23 frente a 13) |
| RN-114: clínicas de la mascota y de cada registro | `M5C2PortalTest::testInicioMuestraLasClinicasDeLaMascotaYSuProximaDosis`, `testLaFichaTraeLasClinicasDeLaMascotaYLaDeCadaRegistro`; JS: `clinicasDeLosRegistros`, filtro por clínica (`M5C2Portal.test.cjs`) |
| Menú de cuatro secciones, sin Servicios, Alertas ni Font Awesome | `M5C2PortalTest::testLaNavegacionTieneInicioCitasClinicasYPerfilSinServiciosNiAlertas`; JS: estructura y sin código en línea |
| RE-5.1.6: dirección por sección y direcciones anteriores | JS: `seccionDesdeHash`, `switchTab` (pantalla, menú y `#citas`) |
| Próximas y Pasadas; «Cancelar» solo con vínculo activo | `M5C2PortalTest::testCitasSeparaProximasDePasadasYCancelaSoloEnClinicaVinculada`; `PresentacionPortalTest` (separación, estados, especie y raza) |
| Próxima dosis, sin contar las renovadas | `PresentacionPortalTest::testLaProximaDosisEsLaMasCercanaYUnaRenovadaYaNoCuenta` |
| Pestañas comunes | JS: `activarPestana` |

Se actualizó la prueba de C9.1 del botón «Imprimir», que ahora es un botón de icono con «Imprimir historial» en su `aria-label`.

### Verificación

- `vendor/bin/phpunit`: **643 en verde** (10 nuevas). Sin MySQL: 3657 aserciones y 29 omitidas. Con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1` y la base por defecto `zooki_test_base_v2`: 4019 aserciones y ninguna omitida.
- JS: **117 en verde** (7 nuevas).
- `views/portal` sin `style=`, `on…=` ni `<script>` con código.
- No se leyó ni escribió `.env`, ni se tocó `zooki_v2_prueba`.
- Modelos §10 y §11.1 no cambian: los flujos son los mismos.

### Pendientes y riesgos

- **Lo visual solo se ve en el navegador:** el portal ahora carga `usuarios.css`, que es del panel. Hay que revisar en celular, tableta y escritorio que nada choque. La única clase que coincidía, `status-badge`, ya no se usa en lo nuevo.
- `portal-vacio` copia el estado vacío compacto de `plataforma-vacio` (M0.2.1). Conviene unificarlos en el pulido final.
- Quedan reglas sin uso en `inicio.css` y `agenda.css`, de las piezas retiradas («Accesos rápidos», pestañas viejas de la agenda). Se limpian con C2b o en el pulido.
- **C2b** queda pendiente de la confirmación del usuario.

### Pantallas para el usuario (Fabio, escritorio y celular)

1. **Menú.** Inicio · Citas · Clínicas · Perfil, con «Agendar» en el menú lateral (escritorio) y al centro de la barra (celular). Recarga en Citas y debe quedarse en Citas. Abre `…#agenda`: debe llevar a Citas.
2. **Inicio.** La próxima cita en tarjeta. Las mascotas en tarjetas con sus clínicas como etiquetas y la próxima dosis. A la derecha (abajo en celular), el horario de hoy de cada clínica, con «Horario de la semana» desplegable.
3. **Mascota** (toca a Luna).
   - Cabecera con etiquetas y los botones de icono Editar e Imprimir.
   - Pestañas Historia · Vacunas · Desparasitaciones · Citas, cada registro con su clínica.
   - Si Luna tiene registros de Norte y de Sur, los chips filtran por clínica.
4. **Citas.** Próximas, Pasadas y Calendario de salud. El filtro por mascota (con más de una) actúa en las dos listas. «Cancelar cita» pide confirmación. Por ahora la página se recarga; la actualización en su sitio es de C2b.
5. **Tamaños.** Una columna de tarjetas en celular, dos en tableta y varias en escritorio, sin nada encimado ni desbordado.

### C2a — Revisión (2026-10-12)

**Revisó:** Claude (arquitecto). **Resultado: aprobada**, pendiente de la prueba visual del usuario. PHP 643 pruebas en verde en una copia limpia sin `.env` (29 MySQL omitidas); JS 117/117; `views/portal` sin `style=`, `on…=` ni `<script>` con código. `index()` pasa a un número fijo de consultas (13 con una o seis mascotas). La división de C2 en C2a y C2b la prevé el prompt y se acepta. Lo más importante que solo se ve en el navegador: el portal ahora carga `usuarios.css` del panel; el usuario revisa que nada choque en celular, tableta y escritorio.

**C2b** (Agendar por pasos con la clínica única, Clínicas con tarjetas, Perfil plegable, actualización en su sitio sin recargar, validación en tiempo real y restricción de entrada en los formularios de mascota, y la limpieza de reglas CSS sin uso) sigue con el mismo alcance de «C2 — Alcance», después de la prueba de C2a.

## Anexo C2b — Resultado

**Escribió:** Claude Code (2026-10-12). **Estado:** entregada, pendiente de revisión. Sin migración. No había hallazgos de la prueba de C2a en el plan.

### Qué se hizo (lo más riesgoso primero)

1. **Actualización en su sitio, sin recargar** (cierra el pendiente «pantalla 3–4 s tarde» de `pulido-interfaz.md`).
   - Acciones cubiertas: agendar, cancelar, vincularse, desvincularse, y registrar o editar una mascota. Autorizar ya aplicaba en su sitio.
   - El aviso breve sale en cuanto responde el servidor. Después, `refrescarPortal()` pide de nuevo el portal y `aplicarPartes()` cambia solo lo que depende de los datos: Inicio, filtro de mascotas, Próximas y Pasadas con su contador, Clínicas, las elecciones de Agendar, y los datos del calendario y de los días cerrados.
   - La sección, la pestaña de Citas y las ventanas se conservan, porque sus elementos no se reemplazan. La ficha abierta se pone al día en su misma pestaña.
   - Si la respuesta no trae el portal (por ejemplo, venció la sesión), recarga entera.
   - Los botones que se repintan (Agendar, registrar mascota, chips de mascota) pasaron a eventos delegados.
   - Ya no queda ningún `zookiRecargarConAviso`.
2. **Agendar por pasos:** 1) clínica y mascota, 2) tipo y veterinario, 3) día y hora, 4) confirmar.
   - Hay indicador de pasos, «Atrás» y «Siguiente», que solo se habilita con el paso completo. Una línea dice qué falta.
   - El último paso muestra un resumen; el pie es el de Usuarios.
   - Clínica y mascota son tarjetas de elección. **Con una sola clínica, solo su nombre y el id oculto (hueco 8, RE-5.9.3)**; nunca se ofrecen las demás clínicas de la plataforma.
   - La lógica de horarios, catálogos y reserva no cambió. `eleccion()` da a las tarjetas la misma interfaz que tenía el `select`.
   - Si el servidor rechaza la reserva, se vuelve al paso de día y hora con un aviso breve.
3. **Formularios de mascota: validación en tiempo real y restricción de entrada.**
   - `ValidadorMascota` ahora dice el campo de cada error, trae avisos de una línea (35 caracteres o menos) y genera los atributos de cada campo (`atributosHtml`: `maxlength`, `pattern`, `data-caracteres`, `data-maximo`, `data-aviso-formato`).
   - `public/js/validacion-mascota.js` valida mientras se escribe, con las mismas reglas del servidor. El aviso va en una línea reservada bajo el campo; no usa las reglas de cuenta.
   - El servidor rechaza con `CampoInvalido`: un 422 con `campo` y `aviso`. El aviso va bajo su campo y el detalle al resumen del formulario.
   - Sin SweetAlert para errores de validación.
4. **Clínicas en tarjetas** (Modelos §11.1, sin cambios):
   - Cada tarjeta de clínica vinculada trae iniciales, «Abierta ahora» o «Cerrada ahora», dirección y horario de hoy, el interruptor de Usuarios con su ayuda y «Desvincularme», directo y con aviso breve.
   - El rechazo por citas sin resolver sale en la misma tarjeta, en una línea (RE-5.13.3).
   - Debajo, «Otras clínicas en Zooki» con un buscador corto que filtra mientras se escribe (sin tildes ni mayúsculas) y «Vincularme».
5. **Perfil en tarjetas plegables** (`<details>`): Mis datos (abierta), Datos de contacto, Datos de acceso (el parcial común, con la misma clase), Contraseña y Cerrar sesión.
   - Ya no hay funciones para abrir y cerrar.
   - El error del teléfono va junto al campo, no en una ventana.
6. **Limpieza de CSS:**
   - Se quitaron las reglas sin uso de `inicio.css` (de 497 a 32 líneas, reescrito), `agenda.css` (de 497 a 393) y `cuenta.css` (de 245 a 106: el perfil viejo y las clínicas viejas).
   - `formularios.css` cambia la distribución vieja de Agendar por la de pasos.
   - Los dos errores de carga del detalle de cita ahora son avisos breves.

### RE y pruebas

| Punto | Prueba |
|---|---|
| Actualización en su sitio, sin recargar | JS: `aplicarPartes` (partes, atributos y aviso), `refrescarPortal` (sin recarga; recarga solo si no llega el portal), ninguna acción con `zookiRecargarConAviso` (`M5C2bPortal.test.cjs`) |
| RE-5.9.3 (hueco 8): clínica única | `M5C2bPortalTest::testConUnaSolaClinicaAgendarMuestraSuNombreYElIdOculto`, `testConVariasClinicasSonTarjetasDeEleccionSoloDeLasVinculadas`; JS: `eleccion` (oculta, radios, mascota única) |
| Agendar por pasos | `M5C2bPortalTest::testAgendarTieneCuatroPasosConElPieDelModal` |
| Validación en tiempo real y restricción de entrada | `M5C2bPortalTest::testLosCamposDeMascotaLlevanLasReglasYElAvisoDelServidor`; `ValidadorMascotaTest::testCadaErrorDiceSuCampo`, `testLosAvisosCabenEnUnaLineaYLosAtributosUsanLosLimites`; JS: nombre, peso, envío (foco y grupo de radios), rechazo del servidor, `limpiarCaracteres` con los caracteres de `ValidadorMascota` |
| El servidor dice el campo | `M5C2bPortalTest::testElServidorDiceElCampoYSuAvisoCortoAlRechazarUnaMascota` (422, `campo`, `aviso` y detalle; no guarda) |
| Clínicas (RE-5.12.1, RE-5.13.1/3) | `M5C2bPortalTest::testClinicasEnTarjetasConInterruptorAvisoYBuscador`; JS: `avisoEnTarjeta`, `filtrarClinicas` |
| Perfil plegable | `M5C2bPortalTest::testPerfilEnTarjetasPlegables` |
| Sin SweetAlert para validación ni código en línea | JS: solo queda la confirmación de cancelar o de la cita duplicada; vistas sin `style=`, `on…=` ni `<script>` con código |

### Verificación

- `vendor/bin/phpunit`: **652 en verde** (9 nuevas). Sin MySQL: 3742 aserciones y 29 omitidas. Con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1` y la base por defecto `zooki_test_base_v2`: 4104 aserciones y ninguna omitida.
- JS: **129 en verde** (12 nuevas).
- No se leyó ni escribió `.env`, ni se tocó `zooki_v2_prueba`.

### Pendientes y riesgos

- **Lo visual solo se ve en el navegador:** los pasos de Agendar, las tarjetas de elección, el pie fijo de los modales en celular y las tarjetas plegables. La especificidad con `cuenta.css` se resolvió con dos clases (`.profile-card.portal-plegable`).
- Actualizar en su sitio pide el portal entero otra vez: es una segunda petición después de la acción. Es correcto (los datos salen del servidor), pero conviene medirlo en producción.
- `ValidadorMascota` también lo usa el personal; el campo nuevo en sus errores no cambia su comportamiento. El formulario de mascota del personal sigue para C3 o el pulido.
- `portal-vacio` sigue copiando a `plataforma-vacio` (anotado en C2a).

### Pantallas para el usuario (Fabio, escritorio y celular)

1. **Agendar.** Con Fabio vinculado solo a Norte, «Agendar» muestra «Clínica Norte» como texto, sin elegir. Vincúlalo también a Sur y vuelve a abrirlo: ahora salen las dos como tarjetas. Avanza por los cuatro pasos («Siguiente» solo se habilita con el paso completo) y confirma. La cita aparece en Inicio y en Citas sin recargar la página.
2. **Cancelar.** En Citas › Próximas, «Cancelar cita» pide confirmación. Al aceptar, la tarjeta sale de Próximas y la pantalla no se recarga.
3. **Clínicas.**
   - Activa y desactiva el interruptor de historia compartida: debe salir un aviso breve.
   - «Desvincularme» con una cita pendiente en esa clínica: el motivo aparece en una línea dentro de la tarjeta.
   - Sin citas pendientes, la clínica pasa a «Otras clínicas» sin recargar.
   - El buscador filtra mientras escribes («clinica» encuentra «Clínica»).
4. **Mascota.**
   - Registra una con «<» en el nombre: ese carácter no se deja escribir.
   - Un peso de 200 muestra el aviso de una línea bajo el campo. Sin especie, «Elige la especie.».
   - Ninguno de esos errores abre una ventana.
   - Al guardar, la mascota aparece en Inicio sin recargar. Edita la mascota desde su ficha: la ficha se pone al día en la misma pestaña.
5. **Perfil y celular.**
   - Las tarjetas plegables (Mis datos abierta; contacto, datos de acceso y contraseña cerradas) se abren y cierran solas, y guardar el teléfono cierra su tarjeta.
   - En celular, los modales son hojas a pantalla completa con el pie fijo abajo.

### C2b — Revisión (2026-10-12)

**Revisó:** Claude (arquitecto). **Resultado: aprobada**, pendiente de la prueba visual del usuario. PHP 652 pruebas en verde en una copia limpia sin `.env` (29 MySQL omitidas); JS 129/129; `views/portal` sin código en línea. Ya no queda ninguna recarga entera tras una acción del portal; con una sola clínica Agendar muestra solo su nombre (hueco 8); el servidor responde 422 con el campo y su aviso corto (`CampoInvalido`), y el formulario de mascota valida mientras se escribe con las reglas de `ValidadorMascota`. **Observación:** actualizar en su sitio pide el portal entero otra vez tras cada acción; es correcto, y si en producción se nota lento se acota a las partes que cambian.

## C3 — Alcance: lado del personal (2026-10-12)

**Escribe:** Claude Code. **Revisa:** Claude. Junta lo que ya estaba previsto para C3 (hueco 6 y el modal de vincular mascota) con los hallazgos de la prueba de C1/C1.1 y los ajustes del usuario.

1. **Alta del propietario por el personal sin aceptación presencial** (decisión del usuario, 2026-10-12). Se quitan «Soy el titular y estoy presente» y «Acepto la política». El personal registra al propietario con el correo obligatorio; la cuenta queda pendiente, sin contraseña ni portal; el propietario recibe un enlace de 72 horas donde él mismo acepta la política (con su prueba en `consentimientos_datos`) y crea su contraseña, como la activación del personal. Mientras no active: la clínica registra y atiende a la mascota; no salen correos automáticos a esa dirección salvo el de activación; la clínica puede reenviar el enlace; la limpieza de pendientes no borra una cuenta que ya tenga mascotas o registros clínicos. Si el documento o el correo ya tienen cuenta, sigue la confirmación del vínculo por correo (RN-109). `verificaciones_email.proposito` es VARCHAR: no hace falta migración. Se corrigen RE-T.19.1, HU-T.19, RN-109 y Modelos §11 y §12.3, y cualquier otro documento que describa la aceptación presencial (sin subir revisión).
2. **Confirmación al desactivar un cliente:** corta, solo «¿Desactivar a <nombre> como cliente?» y los botones, con el estilo de Zooki; sin mencionar otras clínicas.
3. **Búsqueda del propietario en tiempo real** en «Nueva mascota»: al completar un documento o correo válido, con espera corta y límite por IP (como el NIT), sale el aviso «Hay una cuenta con ese dato…» con «Pedir confirmación», o los datos y mascotas del cliente vinculado; sin enviar el formulario.
4. **Modal de vincular mascota existente** (HU-1.5) con las tarjetas mini de Usuarios, sin `style.display` desde el JS, una sentencia por línea y actualización en su sitio con aviso breve.
5. **Hueco 6:** en la historia del veterinario, una mascota desvinculada muestra «El propietario se desvinculó: solo lectura» y no ofrece registrar consulta, vacuna ni desparasitación; los registros de otra clínica llevan su clínica visible (no solo en `title`).
6. **Formulario de mascota del personal** con la validación en tiempo real y la restricción de entrada de `validacion-mascota.js` (las mismas reglas de C2b).

**Fuera de C3:** el resto del panel del veterinario y de la clínica (módulos 2 y 4), alergias y alertas (módulo 2).

**Decisiones del usuario sobre el portal de C2b (2026-10-12), entran en C3 como punto 7.**

- **Tope de clínicas:** un propietario puede tener como máximo **3 clínicas con vínculo activo** cuando se vincula él mismo desde el portal. El tope no aplica cuando una clínica lo registra o confirma su vínculo (una atención nunca se bloquea); desvincularse libera el cupo. Al llegar al tope, «Vincularme» se deshabilita con una línea que lo explica. Regla y criterio nuevos: RN nueva en el módulo 1 (siguiente número libre), criterio en HU-5.13 y RE-5.13.5 (sin subir revisión).
- **Agendar vuelve a un solo formulario:** los cuatro pasos cansan. Clínica (texto fijo con una sola; tarjetas con varias), mascota, tipo, veterinario, calendario con día y hora, todo a la vista como antes, y al pulsar «Agendar» una ventanita corta de confirmación con clínica, mascota, día y hora. Se conservan la clínica única (hueco 8), la validación y la actualización en su sitio.
- **Carga del servidor al actualizar en su sitio:** se explicó al usuario que no satura (son ~13 consultas solo cuando el propietario hace una acción, no un sondeo continuo). Se mantiene; si en producción se nota, la respuesta de la acción devolverá las partes actualizadas en una sola petición.
- **Pulido final de M5:** antes de F el usuario dará su lista de inconformidades de interfaz y se hará una subetapa de pulido antes de pasar a los grafos.

## Anexo C3 — Resultado

**Escribió:** Claude Code (2026-10-13). **Estado:** entregada, pendiente de revisión. Sin migración: `verificaciones_email.proposito` es VARCHAR y el medio del consentimiento sigue siendo `alta_personal`.

**Decisiones del usuario durante C3 (2026-10-13).**

1. La regla del tope es **RN-116**, el siguiente número libre del módulo 1. En la tabla de equivalencias se anota que no es el RN-116 anterior, que ahora es RN-502.
2. El tope aplica **solo cuando el propietario se vincula desde el portal**. Si se registra desde el enlace o QR de una clínica con una cuenta que ya existe, no se bloquea: es un canal de esa clínica, como el alta por el personal.
3. Una cuenta pendiente que registró otra clínica **sí confirma el vínculo**. La otra clínica ve lo de siempre («Hay una cuenta con ese dato»). Si el titular confirma, queda ligado y su cuenta sigue pendiente hasta que active; esa clínica también le puede reenviar el enlace.
4. En la activación, **el propietario no tiene «No acepto»**. Si no quiere el portal, deja vencer el enlace, y la limpieza borra la cuenta solo si no tiene mascotas.

### Qué se hizo (lo más riesgoso primero)

1. **Alta del propietario sin aceptación presencial** (RE-T.19.1, RN-G19, RN-109; Modelos §11).
   - Ya no están «Soy el titular y estoy presente» ni «Acepto la política».
   - `models/ActivacionPropietario.php` es el único lugar de la regla. Crea la cuenta pendiente (estado 0, sin contraseña ni consentimiento), la liga a la clínica y emite el enlace de 72 horas (`activacion_propietario`). Reenviar anula el enlace anterior.
   - La limpieza forma parte de `CuentaTitular::limpiarPendientes()`. Borra la cuenta solo si no tiene mascotas (y con ellas sus registros clínicos) ni otros vínculos.
   - La activación reutiliza `CuentaTitular::activar()`, igual que la del personal (§10.1): acepta la política, crea la contraseña y guarda la prueba en `consentimientos_datos`. La página es `activar_propietario` y dice qué clínica lo registró.
   - Mientras no active:
     - No entra por contraseña ni por Google, porque la cuenta está inactiva.
     - La clínica sí lo encuentra, registra y atiende a sus mascotas (`Mascota::esPropietarioValido` acepta la cuenta pendiente).
     - No le salen correos automáticos: ni recordatorios (ya filtraban `u.estado = 1`), ni correos de citas (`CitaController::enviarCorreoCita`), ni avisos de cambio de ficha (`Mascota::avisarPropietario`). Solo le llega el de activación.
   - Si el documento o el correo ya tienen cuenta, la respuesta es un 409 con `existe`: el aviso de la búsqueda exacta y «Pedir confirmación» en el mismo formulario (RN-109). Nunca dice de quién es la cuenta.
   - Clientes (Ana) y Pacientes (Beto) marcan «Aún no activa su cuenta» y ofrecen «Reenviar enlace» (`reenviar_activacion_propietario_ajax`, en la matriz como acción del personal).
2. **Tope de clínicas** (RN-116, RE-5.13.5; Modelos §11.1).
   - `VinculosPropietario::vincular()` cuenta las clínicas activas con vínculo activo, las mismas que muestra «Clínicas», bajo el bloqueo de la fila del propietario, y rechaza la cuarta.
   - El portal deshabilita «Vincularme» y explica por qué en una línea. Lo calcula con las clínicas que ya leyó, así que el portal sigue en 13 consultas.
   - La clínica sí liga a quien ya tiene 3. Desvincularse libera el cupo.
3. **Búsqueda del propietario en tiempo real** (RE-1.5.1).
   - `public/js/propietario-mascota.js` (nuevo) busca solo cuando hay un documento de 5 a 15 dígitos o un correo completo, tras 450 ms y una sola vez por valor.
   - El servidor aplica el mismo límite por IP que la comprobación del NIT (`Security::checkVerificationLimit`, 20 en 15 minutos) y responde 429 con el aviso en la línea de ayuda.
   - La respuesta es la misma para una cuenta nunca vinculada, desvinculada, de super-administrador o pendiente de otra clínica.
4. **Vincular la mascota existente** (HU-1.5).
   - El cliente y sus mascotas salen en las tarjetas mini de Usuarios.
   - «Vincular a la clínica» cambia por la marca «Vinculada a la clínica», con un aviso breve, y la lista de pacientes se pone al día sin recargar (`refrescarPacientes`, con `enlazarEventosPacientes` para los eventos de lo que se repinta).
   - No se usa `style.display` y hay una sentencia por línea. El buscador viejo de `medical-module.js` se quitó.
5. **Hueco 6** (RN-115, RE-2.10.4).
   - En el historial (cajón y ficha), una mascota desvinculada muestra «El propietario se desvinculó: solo lectura», sin «Registrar primera consulta» ni «Editar».
   - Lo de otra clínica dice «Clínica X · solo lectura» a la vista, ya no en un `title`.
6. **Formulario de mascota del personal** (registrar y editar) con `validacion-mascota.js` y las reglas de `ValidadorMascota::atributosHtml`, como en C2b.
   - El servidor rechaza con `CampoInvalido` (campo y aviso corto), también para especie, raza, fecha y colores.
   - El peso es texto con coma decimal. Sale «Desconocido» del sexo, que el servidor ya rechazaba.
7. **Confirmación al desactivar un cliente:** solo «¿Desactivar a <nombre> como cliente?» y los botones de los modales de Zooki (`zookiConfirmarCorto` en `avisos.js`, con el estilo en `usuarios.css`).
8. **Agendar del portal en un solo formulario.**
   - Clínica (texto fijo si es una sola, tarjetas si son varias), mascota, tipo, veterinario, día y hora, todo a la vista y en dos columnas desde tableta.
   - Una línea dice qué falta y «Agendar» se habilita al estar todo.
   - Al pulsarlo, una confirmación corta con clínica, mascota, día y hora.
   - Se conservan la clínica única, los horarios y la actualización en su sitio.
9. **Documentos** (sin subir revisión):
   - Reglas e historias: RE-T.19.1 y RE-T.19.2, HU-T.19, HU-1.2, RN-G19, RN-109, RN-116 (nueva), HU-5.13 y RE-5.13.5 (nuevo).
   - ERS: RF-T.7 y RF-5.4.
   - Matrices y conteos: 120 RN y 421 RE, en las historias, los requisitos, la Ficha Técnica y la Visión.
   - Modelos §11, §11.1 y §12.3. Los cuatro diagramas (§10.1, §11, §11.1 y §12.3) renderizan con Mermaid CLI.

### RE y pruebas

| Punto | Prueba |
|---|---|
| Alta sin aceptación: pendiente, con enlace y sin consentimiento | `PropietarioClinicaTest::testAltaSinAceptacionCreaCuentaPendienteConEnlaceYSinConsentimiento`; `BaseV2MysqlTest::testC3MascotasYConfirmacionEnElEsquemaReal` (esquema real, también el duplicado) |
| Al activar quedan la prueba y la contraseña; GET sin cambios y sin «No acepto» | `M5C3PersonalTest::testElAltaDejaLaCuentaPendienteYAlActivarQuedanLaPruebaYLaContrasena`, `testSinAceptarLaPoliticaNoSeActiva` |
| Antes de activar: sin portal ni correos; la clínica registra y atiende | `M5C3PersonalTest::testAntesDeActivarNoEntraNiRecibeCorreosYLaClinicaRegistraYAtiendeSuMascota` |
| Reenviar invalida el anterior; Sur no reenvía a un cliente de Norte | `M5C3PersonalTest::testReenviarInvalidaElEnlaceAnteriorYSoloLoHaceSuClinica` |
| La limpieza no borra una pendiente con mascotas | `M5C3PersonalTest::testLaLimpiezaNoBorraUnaCuentaPendienteConMascotas` |
| Documento o correo existentes van por la confirmación del vínculo; aislamiento Norte/Sur | `PropietarioClinicaTest::testControladorExigeDatosObligatoriosYRechazaDuplicados` (409); `M5C3PersonalTest::testUnaCuentaPendienteDeNorteVaPorLaConfirmacionDelVinculoDesdeSur` |
| Límite por IP de la búsqueda | `M5C3PersonalTest::testLaBusquedaDelPropietarioTieneLimitePorIp` |
| Mascota del personal: campo y aviso | `M5C3PersonalTest::testElServidorDiceElCampoYSuAvisoAlRechazarUnaMascotaDelPersonal`, `testLasVistasDelPersonalYaNoPidenLaAceptacionPresencial` |
| RN-116 / RE-5.13.5 | `M5C3TopeClinicasTest` (5): la cuarta desde el portal se rechaza (modelo y controlador), la clínica sí liga con 3, desvincular libera, una suspendida no ocupa cupo, «Vincularme» deshabilitado con su línea |
| Agendar en un solo formulario | `M5C2bPortalTest::testAgendarEsUnSoloFormularioConElPieDelModal` |
| JS | `M5C3Personal.test.cjs` (13): búsqueda con espera y aviso, límite, sin vínculo, tarjetas mini, vincular sin recarga, `hidden` en vez de `style.display`, aviso de existente, confirmación corta (Clientes y Agendar), lo que falta para agendar, solo lectura sin botones y «solo lectura» visible. `M5C1Busqueda.test.cjs` sigue con la regla de C1 en el módulo nuevo. |

Mutaciones comprobadas: sin el tope, sin el filtro del aviso de ficha o del correo de cita, con la limpieza ignorando las mascotas, sin el límite por IP, con la cuenta creada activa o con la confirmación del vínculo que exige `estado = 1`, sus pruebas fallan.

### Verificación

- `vendor/bin/phpunit`: **665 en verde** (13 nuevas). Sin MySQL: 3902 aserciones y 29 omitidas. Con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1` y la base por defecto `zooki_test_base_v2`: 4265 aserciones y ninguna omitida.
- JS: **142 en verde** (13 nuevas).
- Mermaid CLI: §10.1, §11, §11.1 y §12.3 renderizan.
- Vistas tocadas sin `style=`, `on…=` ni `<script>` con código. No se leyó ni escribió `.env`, ni se tocó `zooki_v2_prueba`.

### Pendientes y riesgos

- **El límite por IP es compartido:** 20 búsquedas en 15 minutos por IP, el mismo contador de la comprobación del NIT. Una recepción con mucho movimiento detrás de una sola IP podría llegar a él. Se avisa en la línea de ayuda y hay que medirlo en producción.
- **Una cuenta pendiente no tiene otras vías.** Con Google sale «inactiva», «Recuperar contraseña» no le sirve y el autorregistro con su correo da la respuesta genérica sin enviar nada. La única vía es su enlace, y la clínica se lo reenvía.
- **«Pendiente» se reconoce por estado 0, sin contraseña, con un enlace de activación y sin consentimiento.** Cuando llegue la supresión de cuentas (HU-5.14, v2.1), revisar que una cuenta anonimizada no se confunda con una pendiente.
- **Siguen recargando la página**, fuera del alcance de C3: registrar o editar una mascota del personal y desactivar un cliente.
- Lo visual solo se ve en el navegador: tarjetas de «Nueva mascota», confirmación corta, Agendar en dos columnas y celular.

### Pantallas para el usuario

1. **Beto (Norte) → Pacientes › Nuevo registro › Propietario.**
   - Registra a alguien nuevo con un correo de prueba. Ya no aparecen las casillas de aceptación.
   - Sale el aviso «Le enviamos el enlace…» y pasa a la pestaña Mascota con el propietario ya elegido.
   - Registra su mascota: «<» no se deja escribir en el nombre, un peso de 200 muestra el aviso bajo el campo y no se abre ninguna ventana.
   - En `logs/correos/` está «Activa tu cuenta en Zooki». Ábrelo en una ventana privada: dice «Clínica Norte te registró», no tiene «No acepto»; acepta, crea la contraseña y entra a su portal.
2. **Ana (Norte) → Usuarios › Clientes**, antes de activar al cliente de la pantalla 1:
   - Su tarjeta dice «Aún no activa su cuenta» y tiene el sobre «Reenviar enlace». El aviso es breve y el enlace del correo anterior dice «Hay un enlace más reciente».
   - Desactiva a otro cliente sin citas pendientes: la confirmación solo pregunta «¿Desactivar a … como cliente?», con los botones de Zooki.
3. **Diego (Sur) → Pacientes › Nuevo registro › Mascota.**
   - Escribe el documento de Fabio (1000000006) sin pulsar nada: tras una pausa corta sale «Hay una cuenta con ese dato…» con «Pedir confirmación por correo». Con el correo de Gina sale lo mismo.
   - Después vincula a Fabio con Sur, desde el portal de Fabio o confirmando el correo, y vuelve a buscarlo: salen Fabio y sus mascotas en tarjetas.
   - «Vincular a la clínica» cambia por «Vinculada a la clínica» y la mascota aparece en la lista sin recargar.
4. **Fabio → portal.**
   - En Agendar está todo a la vista; una línea dice qué falta y «Agendar» abre una confirmación corta con clínica, mascota, día y hora.
   - En Clínicas, si tu base local tiene al menos 4 clínicas activas: con 3 vinculadas, «Vincularme» queda deshabilitado con la línea «Ya tienes 3 clínicas…»; desvincúlate de una y vuelve a habilitarse.
5. **Beto (Norte) → historia de una mascota desvinculada.**
   - Ana desactiva a Fabio en Norte (sin citas pendientes).
   - En Consultas, abre el historial de una consulta anterior de su mascota: arriba dice «El propietario se desvinculó: solo lectura» y no ofrece registrar nada.
   - Si la mascota tiene vacunas de Sur, dicen «Clínica Sur · solo lectura».

### C3 — Revisión (2026-10-13)

**Revisó:** Claude (arquitecto). **Resultado: aprobada**, pendiente de la prueba del usuario. PHP 665 pruebas en verde en una copia limpia sin `.env` (29 MySQL omitidas); JS 142/142. El alta sin aceptación presencial vive en `ActivacionPropietario` y reutiliza la activación del personal; la cuenta pendiente no entra, no recibe correos automáticos y no se borra si tiene mascotas; el tope de 3 (RN-116) se comprueba con la fila del propietario bloqueada; la búsqueda en tiempo real no distingue cuentas. Las cuatro decisiones del usuario durante C3 quedan en el Anexo.

**Para el pulido de M5 (no bloquea):**

1. **Límite de la búsqueda del personal:** `MascotaController` llama a `Security::checkVerificationLimit(null)`, el contador por IP de 20 en 15 minutos que comparte la comprobación pública del NIT. El personal está autenticado: debe usar el contador por usuario (`checkVerificationLimit(Contexto::idUsuario())`, 120 en 15 minutos, como ya hace `IdentidadController`), para que una recepción con mucho movimiento detrás de una sola IP no se bloquee. Ajustar su prueba.
2. **Medio del consentimiento:** la activación del propietario guarda el medio `alta_personal`, que describe el alta del personal. Revisar si conviene un medio propio (p. ej. `alta_clinica`) para que la prueba diga cómo aceptó; si requiere cambiar un ENUM, va con migración.
3. Siguen recargando la página registrar o editar una mascota del personal y desactivar un cliente.

### Prueba del usuario de C3 (2026-10-13)

Las cinco pantallas pasaron. **Pregunta del usuario:** si pasan las 72 horas, el propietario no activó y ya tiene una consulta, ¿qué pasa y cómo recupera el acceso? Hoy (Anexo C3): la cuenta **no se borra** (tiene mascota) y queda pendiente; el enlace vence; la única vía es que la clínica le reenvíe el enlace. «¿Olvidaste tu contraseña?», el autorregistro con su correo y Google no le sirven. **Propuesta de la revisión para el pulido:** que el propietario pueda pedirlo él mismo, sin depender de la clínica: «¿Olvidaste tu contraseña?» y el autorregistro con el correo de una cuenta pendiente le envían un enlace de activación nuevo (en pantalla, la misma respuesta genérica de siempre, sin revelar nada); y entrar con Google con una cuenta pendiente lleva a aceptar la política y la activa sin contraseña (Google ya probó el correo, RN-G21).

## C4 — Alcance: pulido de M5 (2026-10-13)

**Escribe:** Claude Code. **Revisa:** Claude. Capturas del usuario en `specs/referencias/m5-pulido/` (01 a 07). Se parte en **C4a (portal del propietario)** y **C4b (lado de la clínica y pendientes técnicos)**; C4b se encarga después de la prueba de C4a. Agendar del portal no se toca (cambia con el triage del módulo 4), salvo errores.

**Criterio general del usuario:** aprovechar el espacio de la pantalla al máximo y pensar siempre en celular y tableta; diseño minimalista; nada de enlaces sueltos sin estilo ni mal ubicados.

**Decisiones del usuario (2026-10-13), antes del prompt de C4a:**

1. **Calendario de salud:** en Inicio, una lista corta «Próximo en salud» (4 o 5 eventos: dosis y citas) bajo el horario de hoy, solo en escritorio y tableta. En Citas, lista a la izquierda y calendario del mes a la derecha, con puntos en los días que tienen algo; en celular, una sola columna sin el mes.
2. **Confirmaciones en todo el sistema con el modal de Zooki:** se cambia `zookiConfirmarCorto` (`avisos.js`) para que use el modal del sistema (hoja a pantalla completa en celular) en vez de SweetAlert; así cambian a la vez el portal y Clientes del personal. Los avisos breves (toast) siguen con SweetAlert.
3. **Colores de la mascota (C4b, punto 4):** se quita el campo de los formularios del personal y del portal; la columna se queda en la base sin uso, sin migración, con nota en el MER.
4. **Recuperar la activación (C4b, punto 5):** por las tres vías. «¿Olvidaste tu contraseña?» y el autorregistro con el correo de una cuenta pendiente envían un enlace de activación nuevo (respuesta genérica en pantalla); entrar con Google con una cuenta pendiente lleva a aceptar la política y la activa sin contraseña (RN-G21).

### C4a — Portal del propietario (hallazgos del usuario, 2026-10-13)

1. **Pie del menú lateral:** «Mi portal de propietario» se corta (captura 01). Nombre y contexto completos o un texto que quepa.
2. **Próxima cita:** la tarjeta es demasiado grande para lo que dice; compacta, de una o dos líneas (mascota, día y hora, clínica, veterinario, cancelar), que también funcione en celular.
3. **Tarjetas de mascota del Inicio:** las de C2a copian las del panel y no encajan en el portal. Volver al diseño anterior del portal (más pequeño y mejor para el usuario; está en git antes del commit `d8a62f8`) y potenciarlo donde se pueda, conservando lo que aporta (clínicas como etiquetas, próxima dosis).
4. **Columna derecha del Inicio:** además del horario de hoy, un vistazo del calendario de salud de las mascotas (próximas dosis y citas), solo en escritorio y tableta; en celular no se muestra.
5. **Enlaces sin estilo o mal ubicados** («Ver todas», «Horario de la semana» y similares): con estilo de botón o enlace del sistema y en un lugar pensado.
6. **Horario de la semana:** desordenado y alto (captura 02). Ordenarlo como tabla alineada y acortarlo, por ejemplo agrupando los días con el mismo horario («Lun–Vie 8:00–12:00 · 14:00–18:00», «Sáb–Dom Cerrado»).
7. **Citas:** la sección se ve vacía y con módulos sueltos (captura 03). Integrar Próximas, Pasadas y el calendario de salud en una sola vista que aproveche el espacio (por ejemplo, lista a la izquierda y calendario a la derecha en escritorio; en celular, una columna).
8. **Clínicas:** mala distribución del espacio y tarjetas sobredimensionadas, con la misma ayuda larga repetida en cada una (captura 04). Rediseño minimalista y compacto: una fila o tarjeta corta por clínica, la explicación del interruptor una sola vez (o en un icono de ayuda), «Otras clínicas» igual de compacta.
9. **Perfil:** mal uso del espacio en computador. Unir en un solo apartado los datos de contacto y el correo («Contacto»); lo que necesite confirmación (correo, documento, contraseña) se hace en los **modales de Zooki** adaptados a celular (hoja a pantalla completa), no con SweetAlert. Inputs más compactos: hoy ocupan espacio de más y muestran una caja dentro de otra (captura 05). Dos columnas en escritorio.
10. **Modales en celular:** en el portal no se usa SweetAlert para confirmaciones ni formularios en celular; se usan los modales del sistema (como `users-modal` / hoja de celular). En computador, los mismos, por coherencia. Los avisos breves (toast) se mantienen.
11. **Teléfono con banderita y sus validaciones** (el input que ya usan Usuarios y Pacientes): también en el registro del propietario (login), en «Completar perfil» (cuenta de Google) y en el contacto del Perfil del portal.
12. **«Agregar mascota» visible:** hoy es un «+» pequeño; un botón claro en Inicio (y en el estado vacío).

### C4b — Lado de la clínica y pendientes técnicos

1. **Pacientes › Propietarios:** las tarjetas no usan el estilo de tarjeta del sistema (captura 06: botones «Ver propietario» y «Editar» rotos). Usar las tarjetas mini de Usuarios (`person-card`, `card-*-mini`, `tag-mini`, `action-btn-mini`), con «Aún no activa su cuenta» como etiqueta y «Reenviar enlace» como botón de icono.
2. **Registro de mascota › Propietario:** sale una tarjeta y además un recuadro verde con el mismo propietario (captura 07). Debe ser **un solo bloque** que sirva para buscar y para mostrar al elegido; «Aún no activa su cuenta» mejor presentado.
3. **Buscar propietario también por nombre o correo:** por nombre, entre los clientes de esta clínica (coincidencia parcial); por documento o correo completos, la búsqueda global exacta de C1/C3 (sin revelar nada).
4. **Colores de la mascota:** hoy no se pueden registrar. **Confirmado por el usuario (decisión 3, 2026-10-13):** quitar el campo de los formularios (no influye en lo clínico y la foto identifica a la mascota); la columna se queda en la base sin uso, sin migración, con nota en el MER.
5. **Recuperar la activación sin depender de la clínica** (confirmado por el usuario, decisión 4, 2026-10-13): «¿Olvidaste tu contraseña?» y el autorregistro con el correo de una cuenta pendiente envían un enlace de activación nuevo (respuesta genérica en pantalla); Google con una cuenta pendiente lleva a aceptar la política y la activa.
6. Las tres notas de «C3 — Revisión»: límite de la búsqueda del personal por usuario; medio propio del consentimiento de la activación del propietario; registrar o editar mascota del personal y desactivar un cliente sin recargar.

## Anexo C4a — Resultado

**Escribió:** Claude Code (2026-10-13). **Estado:** entregada, pendiente de revisión. Sin migración ni cambios en el servidor más allá de la presentación: los datos nuevos del Inicio salen de lo que el portal ya leía (siguen las mismas consultas). La misma sesión redactó el prompt, así que la revisión la hace otra sesión.

### Qué se hizo (lo más riesgoso primero)

1. **Confirmaciones con el modal de Zooki en todo el sistema** (decisión 2; puntos 9 y 10).
   - `zookiConfirmarCorto` (`avisos.js`) ya no usa SweetAlert. Arma su ventana con `textContent` (sin HTML), con `role="alertdialog"` y `aria-modal`.
   - En el celular es una hoja desde abajo; desde tableta, una ventana centrada (`usuarios.css`, que cargan los cuatro layouts con sesión).
   - Escape y tocar el fondo cancelan. Escape no cierra la ventana de abajo. El foco no sale y vuelve a quien la abrió. Tiene un cuarto parámetro para el botón que no hace nada («Volver» cuando la acción es «Cancelar cita»).
   - En el portal ya no hay `Swal.` (se quitó `showTikTokModal`): cancelar una cita, la cita adicional del mismo día y Agendar usan ese modal. Clientes del personal cambia a la vez.
   - Las demás confirmaciones del personal que siguen con SweetAlert quedan en `specs/pulido-interfaz.md`. Se ajustaron `AGENTS.md` y `metodo.md` §8 a la decisión.
2. **Perfil** (punto 9): tres tarjetas (Mis datos, Contacto, Seguridad), dos columnas en escritorio.
   - El teléfono se edita en Contacto, con bandera.
   - Correo, documento y contraseña se cambian en modales del portal (`portal-drawer`, a pantalla completa en el celular). Sus formularios son los mismos de siempre: `cambios_identidad.php` pinta uno solo con `$identidadDato`, y Mi perfil del personal no cambia.
   - Al guardar, `validacion-cuenta.js` y `cuenta-password.js` avisan con `cuenta:guardada` y el portal cierra el modal con un aviso breve. Modelos §13.2 y §13.3 no cambian: el flujo es el mismo.
   - Se quitó el recuadro azul del autocompletado (la caja dentro de otra caja).
3. **Teléfono con bandera** (punto 11): `public/js/telefono-internacional.js` reúne la configuración que repetían Usuarios y Pacientes. Ahora está también en el registro del propietario, en «Completar perfil» y en el Perfil del portal.
   - Al enviar, el número sale con su prefijo; si la bandera no lo reconoce, se envía lo escrito. `ValidadorTelefono` no cambia.
   - El aviso de validación del campo con bandera va debajo del contenedor (`validacion-cuenta.js`).
4. **Citas en una sola vista** (punto 7, decisión 1).
   - Las pestañas Próximas, Pasadas y «Vacunas y controles» van a la izquierda; el calendario del mes, a la derecha desde tableta, y en el celular no se muestra.
   - Un día marcado muestra lo de ese día (citas, aplicaciones y dosis que tocan) en lugar de las pestañas, con «Ver todo» para volver.
   - El calendario llega hasta dos años adelante, porque las dosis próximas caen ahí.
   - Las funciones del día son puras y se prueban sin DOM: `eventosDelDia`, `filaEventoDeSalud` y `etiquetaDelDia`.
5. **Inicio**:
   - Próxima cita en una fila de dos líneas (la misma fila en Citas), con «Cancelar» como icono en el celular (punto 2).
   - Mascotas con el diseño anterior del portal (avatar redondo, nombre y raza), más las clínicas como etiquetas y la próxima dosis. En el celular son un carrusel; desde tableta, una cuadrícula (punto 3).
   - «Agregar mascota» es un botón claro, también en el estado vacío (punto 12).
   - «Próximo en salud»: 5 citas y dosis por fecha (`PresentacionPortal::proximoEnSalud` y `ProximaDosis::pendientes`), solo desde tableta. En tableta va al lado del horario (punto 4).
6. **Horario de la semana** (punto 6): `HorarioAtencion::agrupar` arma una tabla corta que junta los días seguidos con el mismo horario («Lun–Vie 8:00–12:00 · 14:00–18:00») y marca hoy.
7. **Clínicas** (punto 8):
   - Una fila por clínica y la explicación del interruptor una sola vez (cada interruptor la nombra con `aria-describedby`).
   - «Mis clínicas» dice «2 de 3». «Otras clínicas» va al lado en escritorio.
   - Se conservan el tope (RN-116) y el aviso en la fila (RE-5.13.3).
8. **Enlaces** (punto 5): «Ver todas», «Horario de la semana», «Calendario», «Ver clínicas» y «Cambiar» usan `portal-boton-texto`. **Pie del menú** (punto 1): el nombre y el contexto van en dos líneas, sin la píldora que se cortaba.

### Puntos y pruebas

| Punto | Prueba |
|---|---|
| 1, 5 | Revisión visual (lista de pantallas); `M5C4aPortal`: sin código en línea |
| 2, 7 | `M5C2PortalTest::testCitasSeparaProximasDePasadasYCancelaSoloEnClinicaVinculada`; `M5C4aPortal.test.cjs`: el día elegido, las filas, la vista con el calendario oculto en el celular y cancelar con «Volver» |
| 3 | `M5C2PortalTest::testInicioMuestraLasClinicasDeLaMascotaYSuProximaDosis` (tarjeta nueva), `testIndexHaceElMismoNumeroDeConsultasConUnaOConSeisMascotas` |
| 4 | `M5C2PortalTest::testProximoEnSaludSoloTraeLoDelPropietarioYPorFecha` (RN-G02, RN-114); `PresentacionPortalTest` (2 nuevas); JS: oculto en el celular |
| 6 | `HorarioAtencionTest` (4 nuevas); `M5C2PortalTest::testElHorarioDeLaSemanaAgrupaLosDiasIguales` |
| 8 | `M5C2bPortalTest::testClinicasEnFilasConInterruptorAvisoYBuscador` y `testOtrasClinicasOfreceVincularmeEnSuFila`; `M5C3TopeClinicasTest` sigue en verde |
| 9, 10 | `M5C2bPortalTest::testPerfilConContactoYLosCambiosEnModales`; JS: modales del Perfil, confirmación (Escape, fondo, Tab y foco), sin `Swal.` en el portal; `M5C3Personal` y `D3Avisos` ajustadas al modal |
| 11 | `M5C4aPortal.test.cjs` (3): se prepara una vez, envía con prefijo y está en los cinco formularios; `D21Interfaz` carga el módulo común |
| 12 | Vista: `data-agregar-mascota` en Inicio y en el estado vacío (revisión visual) |

Mutaciones comprobadas: sin el filtro de mascotas ajenas en «Próximo en salud», sin agrupar los días, sin descartar las dosis vencidas, con Escape que deja pasar el evento, con el número del teléfono puesto después de la validación y sin el filtro de mascota del día elegido, sus pruebas fallan.

### Verificación

- `vendor/bin/phpunit`: **677 en verde** (12 nuevas, con los ajustes de abajo). Sin MySQL: 3955 aserciones y 29 omitidas. Con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1` y la base por defecto `zooki_test_base_v2`: 4318 aserciones y ninguna omitida.
- JS: **157 en verde** (15 nuevas, más un DOM mínimo en `tests/Frontend/apoyo/`).
- Vistas tocadas sin `style=`, `on…=` ni `<script>` con código. No se leyó ni escribió `.env`, ni se tocó `zooki_v2_prueba`.
- Sin cambio de versión: sube en F, como en las subetapas anteriores.

### Pendientes y riesgos

- **Lo visual solo se ve en el navegador**: carrusel en el celular, Citas en tableta (lista y calendario de 290 px), filas de Clínicas en el celular y la hoja de confirmación. Hay que recargar con Ctrl+F5: la versión de los archivos no cambió.
- **Teléfonos guardados sin prefijo** («3001234567»): la bandera los toma como de Colombia y al guardar salen como «+573001234567». Es el mismo comportamiento de Usuarios y Pacientes.
- El resto de confirmaciones con SweetAlert del personal quedan para el pulido final (`specs/pulido-interfaz.md`).
- Agendar no se tocó, salvo que su confirmación de cita adicional del mismo día usa ahora el modal de Zooki.

### Ajustes tras la primera mirada del usuario (2026-10-13)

Con sus capturas, antes del commit:

1. **Inicio:** el saludo pasó a la columna principal, así la lateral («Horario de hoy») sube hasta arriba y no deja un hueco a su lado.
2. **Inicial de la mascota** centrada en el círculo (el `display: block` la subía).
3. **Pie del menú:** cuadrícula con avatar, nombre y salir en una fila; «Mi portal de propietario» en una sola línea debajo; «Cambiar de perfil» como botón del sistema, ya no un enlace subrayado suelto. Se arma en el layout con `Contexto` (HU-T.17), sin el parcial de la píldora.
4. **Perfil:** Mis datos y Seguridad se apilan a la izquierda y Contacto va a la derecha, sin el hueco que dejaba Contacto por ser más alta.
5. **Ojito** para ver la contraseña en los cinco campos de contraseña del portal (contraseña actual, nueva y confirmación; contraseña actual de correo y documento). Lo atiende `cuenta-password.js`, como en el login.
6. **El correo dice qué le falta** («Al correo le falta la @», «Escribe lo que va antes de la @», «El correo lleva una sola @», «Escribe el dominio completo, por ejemplo gmail.com»), con los mismos mensajes en `ValidadorCuenta::correo` y en `validacion-cuenta.js`, para todo el sistema. En «Cambiar correo», escribir el correo actual avisa «Ese ya es tu correo» (también el servidor); no revela nada de otra cuenta. Pruebas: `ValidadorCorreoTest` (2), `CuentasD2Test::testCorreoIgualAlActualOMalEscritoDiceElMotivo`, y en JS los mismos casos, el correo actual y el ojito (3).

7. **Pie del menú:** salir queda centrado con el avatar; bajo el nombre dice «Propietario» (cabe al lado de salir) y el contexto completo va en el title.
8. **Páginas de acceso** (login, elegir perfil, registros, activaciones): la tarjeta queda en la mitad de la pantalla. El pie ocupa lo mismo que la barra de arriba (`styles.css`), y se quitó el margen sobrante del logo en celular y tableta, que la bajaba.

**Propuesta para decidir (no hecha):** rechazar también los correos temporales (`DominiosDesechables`, que ya usa el registro de clínicas, RE-0.1.5) al cambiar el correo y en el registro del propietario. Es una regla nueva: necesita su criterio en HU-T.5 y HU-T.18.

### Pantallas para el usuario (Fabio, salvo que se diga otro; computador y celular)

1. **Inicio.**
   - El pie del menú muestra tu nombre y «Mi portal de propietario» completos.
   - La próxima cita ocupa una o dos líneas.
   - Las mascotas son tarjetas pequeñas con foto redonda, clínicas y próxima dosis; en el celular se deslizan de lado. «Agregar mascota» abre el registro.
   - En el computador, a la derecha, «Próximo en salud»; en el celular no sale.
   - «Horario de la semana» es una tabla corta («Lun–Dom …» o «Lun–Vie …»).
2. **Citas.**
   - En el computador, la lista a la izquierda y el calendario del mes a la derecha. Toca un día con puntos: la lista muestra solo ese día; «Ver todo» vuelve.
   - En el celular, sin el mes, con la pestaña «Vacunas y controles».
   - Cancela una cita: sale la confirmación de Zooki («Volver» / «Cancelar cita»), como hoja en el celular; Escape o tocar fuera no cancela nada.
3. **Clínicas.**
   - Una fila por clínica con su interruptor, la explicación una sola vez arriba y «2 de 3».
   - Desvincúlate de una con citas pendientes: el aviso sale en su fila.
4. **Perfil.**
   - Tres tarjetas, en dos columnas en el computador.
   - Cambia el teléfono con la bandera.
   - «Cambiar» del correo, del documento y de la contraseña abre su modal (pantalla completa en el celular). Al guardar se cierra con un aviso breve.
   - El autocompletado del navegador ya no pinta una caja azul dentro del campo.
5. **Registro y «Completar perfil»** (sin sesión), y **Ana → Usuarios › Clientes.**
   - En el registro del propietario y en «Completar perfil» (entra con una cuenta de Google nueva), el teléfono tiene bandera y la cuenta queda con el prefijo.
   - Ana desactiva un cliente: la misma confirmación de Zooki, sin SweetAlert.

### C4a — Revisión (2026-10-13)

**Revisó:** Claude (arquitecto). **Resultado: aprobada**, pendiente de la prueba del usuario (ya hubo una primera mirada con ajustes, anotados en el Anexo). PHP 677 pruebas en verde en una copia limpia sin `.env` (29 MySQL omitidas); JS 157/157; el portal ya no usa `Swal.`. La regla nueva de AGENTS.md y metodo.md §8 (SweetAlert2 solo para avisos breves; confirmaciones y formularios en los modales de Zooki) sigue la decisión del usuario sobre los modales (C4 — Alcance, punto 10). «Próximo en salud» sale de lo que el portal ya leía (sin consultas nuevas) y filtra por propietario.

**Pendiente de decisión del usuario para C4b:** rechazar también los correos temporales (`DominiosDesechables`, RE-0.1.5) en el registro del propietario y al cambiar el correo. Recomendación de la revisión: sí, por coherencia con el registro de clínicas; requiere su criterio en HU-T.5 y HU-T.18. Siguen pendientes las dos confirmaciones de C4b (quitar colores de la mascota y recuperar la activación sin la clínica).

**Decisiones del usuario para C4b (2026-10-13).**

1. **Colores de la mascota:** se quitan de los formularios (portal y personal); la columna queda en la base sin uso, sin migración, con nota en el MER y en la HU de registro de mascota.
2. **Recuperar la activación sin la clínica:** sí. «¿Olvidaste tu contraseña?» y el autorregistro con el correo de una cuenta pendiente envían un enlace de activación nuevo, con la misma respuesta genérica en pantalla; entrar con Google con una cuenta pendiente lleva a aceptar la política y la activa sin contraseña (RN-G21).
3. **Correos temporales:** también se rechazan en el registro del propietario (formulario y alta por la clínica) y al cambiar el correo, con la lista de `DominiosDesechables` y aviso junto al campo; criterio en HU-T.5, HU-T.18 y HU-5.8 (sin subir revisión).

## Anexo C4b — Resultado

**Escribió:** Claude Code (2026-10-13). **Estado:** entregada, pendiente de revisión.

**Decisión del usuario durante C4b (2026-10-13):** el medio propio del consentimiento exigía migración (`consentimientos_datos.medio` es un ENUM). Se eligió la **migración pequeña**: `06_medio_activacion_propietario.sql` agrega `activacion_propietario`. Se puede correr dos veces y se aplica sola al desplegar. `01_schema.sql`, el modelo de drawDB y el MER llevan el mismo cambio.

### Qué se hizo (lo más riesgoso primero)

1. **Recuperar la activación sin la clínica** (decisión 2; RE-T.19.1, RN-G15, RN-G21; Modelos §10 y §11).
   - `ActivacionPropietario::renovarPorTitular` da un enlace nuevo solo si la cuenta sigue pendiente. El enlace lleva la clínica que la registró y el anterior deja de servir.
   - Las tres vías:
     - **«¿Olvidaste tu contraseña?»:** con el correo de una cuenta pendiente envía el enlace de activación. La respuesta en pantalla no cambia.
     - **Autorregistro:** con el correo o el documento de una cuenta pendiente envía el enlace de activación al correo de esa cuenta. La respuesta es la misma que la de un registro nuevo, y la cuenta no se liga a otra clínica sin activarse.
     - **Google:** con una cuenta pendiente abre el mismo modal del registro con Google en modo «Activa tu cuenta», sin elegir clínica. `activarConGoogle` exige aceptar la política, liga Google, activa sin contraseña, anula los enlaces y guarda la prueba con el medio `google`.
   - El correo de activación sale de un solo lugar (`ActivacionPropietario::correoHtml`), que usan la clínica y el titular.
   - Para probarlas sin el `exit` de `jsonResponse`, `AuthController` expone `renovarActivacionPendiente`, `activacionPendienteConGoogle` y `completarConGoogle`.
2. **Correos temporales** (decisión 3; RE-0.1.5).
   - `ValidadorCuenta::correoDeCuenta` (formato + `DominiosDesechables`) es la regla única. La usan el autorregistro, el alta por la clínica, el cambio de correo, la ayuda al escribir (`IdentidadController::validar` y `check_email_ajax`) y el registro de clínicas.
   - **Alcance:** la ayuda al escribir del campo `email` es una sola para todo el sistema. Para que el navegador y el servidor digan lo mismo, la invitación de personal (`UsuarioController`) también rechaza correos temporales.
   - El cambio de correo dice además «Ese ya es tu correo».
3. **Medio del consentimiento:** la activación por enlace del propietario guarda `activacion_propietario` (migración 06); la del personal sigue con `alta_personal`.
4. **Búsqueda por nombre** (RE-1.5.1, RN-111; Modelos §12.3).
   - `PropietarioClinica::buscarPorNombre` hace una coincidencia parcial (desde 3 letras, letras y espacios) solo entre los clientes con vínculo activo de la clínica, con hasta 8 resultados.
   - Ruta nueva `buscar_propietario_nombre_ajax`, en la matriz solo para el personal.
   - Al elegir un resultado se trae la ficha con la búsqueda exacta de siempre.
   - El comodín del LIKE se escapa con `!`, que funciona igual en MySQL y SQLite.
5. **Límite por usuario** (revisión de C3): las dos búsquedas cuentan con `checkVerificationLimit(Contexto::idUsuario())` (120 en 15 minutos). El compañero de la misma recepción no queda bloqueado.
6. **Un solo bloque del propietario** (captura 07):
   - Sin mascotas que vincular, se elige directo.
   - El elegido queda en su tarjeta mini, con «Aún no activa su cuenta» y los botones de icono «Reenviar enlace» y «Cambiar». Se quitaron el recuadro verde y la tarjeta repetida.
   - El campo de búsqueda admite nombres: letras, dígitos, espacio y `@._+'’-`, hasta 255.
7. **Pacientes › Propietarios** (captura 06): tarjetas y filas con las tarjetas mini de Usuarios (`person-card`, `card-*-mini`, `tag-mini`, `action-btn-mini`). Las acciones son botones de icono: editar, reenviar el enlace y ver.
8. **Sin recargas** (revisión de C3):
   - Registrar o editar una mascota del personal cierra el modal y pone al día, en su sitio, los pacientes, los propietarios y el expediente abierto (`ponerAlDiaPacientes`).
   - Desactivar un cliente repinta solo el panel de Clientes, con la vista y el filtro elegidos (`refrescarClientes`).
9. **Sin colores** (decisión 1):
   - Fuera de los formularios de registro y edición, de la ficha y de la tabla de mascotas, del JS (`initColores`) y de la ruta `listar_colores_ajax`.
   - El servidor ya no toma colores del formulario. Si llegan por otra vía, el modelo los acepta solo del catálogo.
   - Se anotó en RN-107, HU-1.1, RE-1.1.1, RE-5.1.9, RF-1.1, RF-1.7 y el MER, sin migración.
10. **Documentos:**
    - Criterios en HU-T.5, HU-T.18, HU-T.19, HU-5.8, HU-1.1 y HU-1.5; RE-T.5.7, RE-T.19.1, RE-1.1.1 y RE-5.1.9; RN-107; ERS; MER.
    - Diagramas de Modelos §10, §11, §12.3 y §13.2.
    - Sin RE ni RN nuevos: los conteos no cambian.

### Puntos y pruebas

| Punto | Prueba |
|---|---|
| Tarjetas y filas de Propietarios | `M5C4bPersonal.test.cjs`: tarjeta mini, «Aún no activa» como etiqueta, botones de icono, desvinculado sin editar, fila de la tabla |
| Bloque único | `M5C3Personal.test.cjs` (reescrita para C4b) y `M5C4bPersonal.test.cjs`: elegido por nombre, una sola tarjeta, panel vacío |
| Búsqueda por nombre y aislamiento | `M5C4bPersonalTest`: solo clientes activos, desvinculado fuera, Sur no ve a los de Norte, tres letras y sin comodines; controlador; JS: tipo de búsqueda, resultados y sin coincidencias; `AutorizacionRolTest`: solo el personal |
| Límite por usuario | `M5C3PersonalTest::testLaBusquedaDelPropietarioTieneLimitePorUsuario` (120, compartido por las dos búsquedas; otro usuario sigue) |
| Activación por las tres vías | `M5C4bPersonalTest`: olvidé mi contraseña (enlace nuevo, el anterior anulado, cuenta activa sin nada), autorregistro (misma respuesta, sin ligar), Google (sin aceptar no activa; activa sin contraseña, medio `google`, enlaces anulados) |
| Medio propio | `M5C4bPersonalTest::testLaActivacionPorEnlaceGuardaSuPropioMedioDeConsentimiento`, `M5C3PersonalTest`; `BaseV2MysqlTest::testMigracionC4bAgregaElMedioDelPropietarioYSePuedeRepetir` y el migrador al día |
| Correos temporales | `M5C4bPersonalTest::testUnCorreoTemporalSeRechazaEnLosTresFormulariosYAlEscribir` (alta por la clínica, autorregistro, cambio de correo y ayuda al escribir) |
| Sin recargas | `M5C4bPersonal.test.cjs`: guardar y editar mascota sin recarga; panel de Clientes repintado en su sitio |
| Sin colores | `M5C4bPersonalTest::testLaMascotaDelPersonalSeRegistraSinColores`; JS: vista y módulo sin colores; `MascotaCatalogoTest` ajustada |
| Modal de Google | `M5C4bPersonal.test.cjs`: modo «Activa tu cuenta», sin clínica |

Mutaciones comprobadas: sin el filtro de vínculo activo o de clínica en la búsqueda por nombre, sin la lista de dominios temporales, sin la renovación en el autorregistro, sin exigir la aceptación con Google, con el controlador tomando otra vez los colores y con la búsqueda por nombre desde 2 caracteres, sus pruebas fallan. Hay una mutación equivalente, sin defecto: agregar la tarjeta al panel antes de elegir no cambia nada, porque elegir vacía el panel.

### Verificación

- `vendor/bin/phpunit`: **688 en verde** (11 nuevas). Sin MySQL: 4216 aserciones y 30 omitidas. Con `ZOOKI_TEST_MYSQL_HOST=127.0.0.1` y la base por defecto `zooki_test_base_v2`: 4581 aserciones y ninguna omitida.
- JS: **166 en verde** (9 nuevas). El DOM falso de propietario-mascota pasó a `tests/Frontend/apoyo/propietario-mascota.cjs`.
- Mermaid CLI: §10, §11, §12.3 y §13.2 renderizan.
- Vistas tocadas sin `style=`, `on…=` ni `<script>` con código. No se leyó ni escribió `.env`, ni se tocó `zooki_v2_prueba`.

### Pendientes y riesgos

- **Migración 06 en producción:** cambia un ENUM de `consentimientos_datos`; el migrador la aplica al desplegar (F). Es repetible y no toca filas.
- **La invitación de personal también rechaza correos temporales** (por la ayuda única al escribir). Si el usuario no lo quiere, se separa el tipo de campo.
- **«¿Olvidaste tu contraseña?» no tiene límite propio:** cualquiera que sepa el correo de una cuenta pendiente puede hacer que le llegue otro enlace de activación, igual que hoy con el restablecimiento. No revela nada, pero puede repetir correos.
- **El autorregistro de una cuenta pendiente no la liga a la clínica elegida:** el enlace va a la clínica que la registró. Después de activar, se vincula a otra desde su portal (HU-5.13).
- El selector de propietario del formulario de **editar** mascota sigue con su diseño (fuera de C4b).

### Ajustes tras la primera mirada del usuario (2026-10-14)

1. **Propietarios:** «Aún no activa su cuenta» (y «Desvinculado») pasa a la cabecera, junto al avatar, y los botones quedan abajo en todas las tarjetas de la fila. Así el contenido empieza a la misma altura.
2. **Modal de mascota:** estado y esterilización en una sola línea, como dos cajas gemelas (misma altura, borde y radio, cada una con su etiqueta pequeña arriba): el interruptor con «Activo» y el select de esterilización. Se quitó la caja gris (`pet-extra-panel`) y la foto queda en 4:3, en registrar y en editar.
3. **Propietario elegido:** una fila compacta (avatar de 36 px, nombre con sus etiquetas e iconos a la derecha) en lugar de la columna centrada de las tarjetas de Usuarios, para que el modal no haga scroll. Lo mismo para la tarjeta del cliente sobre sus mascotas.

4. **Toda la tarjeta de Propietarios abre el detalle** con sus mascotas, como el ojito; sus botones (editar, reenviar) siguen haciendo lo suyo y con el teclado se abre con Enter o Espacio.

Pruebas: `M5C4bPersonal.test.cjs` comprueba la tarjeta que abre el detalle, la marca en la cabecera, el pie abajo, el bloque compacto en los dos formularios y la fila del propietario elegido.

### Pantallas para el usuario (computador y celular)

1. **Beto (Norte) → Pacientes › Propietarios.**
   - Las tarjetas se ven como las de Usuarios › Clientes: avatar, etiquetas y tres botones de icono. Ya no hay «Ver propietario» partido.
   - Un cliente sin activar lleva la etiqueta «Aún no activa su cuenta» y el sobre de reenviar.
2. **Beto → Nuevo registro › Mascota.**
   - Escribe «fab»: sale Fabio como resultado y lo eliges. Queda **un solo bloque**, su tarjeta con «Cambiar».
   - Escribe el nombre de un cliente de Sur: no sale.
   - Ya no aparece «Colores Base».
   - Registra la mascota: el modal se cierra, sale el aviso breve y la lista se actualiza sin recargar. Edita otra mascota: igual.
3. **Ana (Norte) → Usuarios › Clientes:** desactiva un cliente. La lista se pone al día sin recargar y conserva la vista (tarjetas o tabla).
4. **Un propietario que registró Norte y no ha activado** (créalo desde Pacientes con un correo de prueba), en una ventana privada:
   - En el login, «¿Olvidaste tu contraseña?» con su correo: en `logs/correos/` llega «Activa tu cuenta en Zooki» y el enlace anterior ya no sirve.
   - Con Google, si su correo es de Google: «Activa tu cuenta con Google», sin elegir clínica. Al aceptar, entra al portal.
5. **Correos temporales:**
   - En el registro del propietario, en el alta desde Pacientes y en Perfil › Cambiar correo, escribe `alguien@mailinator.com`: junto al campo sale «No se admiten correos temporales».
   - En Cambiar correo, el correo actual dice «Ese ya es tu correo».

### C4b — Revisión (2026-10-13)

**Revisó:** Claude (arquitecto). **Resultado: aprobada**, pendiente de la prueba del usuario. PHP 688 pruebas en verde en una copia limpia sin `.env` (30 MySQL omitidas); JS 166/166. La migración 06 conserva los tres valores anteriores del ENUM (`formulario`, `google`, `alta_personal`, comprobado contra el esquema anterior) y agrega `activacion_propietario`; es repetible. La búsqueda por nombre solo ve clientes con vínculo activo de la clínica y escapa el comodín del LIKE. Las tres vías de recuperar la activación responden igual que antes en pantalla.

**Observaciones.** (1) La invitación de personal también rechaza correos temporales por compartir la ayuda al escribir: se acepta, el personal tampoco debe usar correos temporales. (2) **«¿Olvidaste tu contraseña?» y el autorregistro pueden reenviar el enlace de activación sin límite propio:** se corrige en el cierre, con un tope por cuenta (por ejemplo, un envío cada 5 minutos y 3 por hora, en silencio: la pantalla responde igual).

## F — Alcance: cierre de M5 y 2.0.0-alpha.3 (2026-10-13)

**Escribe:** Claude Code dejó un avance sin commit; Codex lo retoma y completa por petición del usuario (2026-10-14). **Revisa:** Claude. **Despliega:** el usuario.

1. Tope de reenvíos del enlace de activación por cuenta (observación 2 de «C4b — Revisión»), con prueba.
2. Versión `2.0.0-alpha.3` en `config/App.php` y entrada en `HistorialVersiones.md` escrita para quien usa el sistema.
3. Las seis HU de M5 (HU-T.18, HU-5.9, HU-1.5, HU-5.12, HU-5.13, HU-2.10) a «Implementada (2.0.0-alpha.3)», con la nota de RE-2.10.1 (alergias y alertas en el módulo 2); reglas aplicadas (RN-109 a RN-116, RN-G20, RN-G21) con su estado.
4. Revisión nueva de cada documento tocado en M5, una sola vez (metodo §4), y descargas del portal regeneradas.
5. Tabla §2 del plan con la evidencia final de cada RE y §7 marcado.
6. Comandos del despliegue para el usuario: merge `v2/m5-portal` → `main`, tag `v2.0.0-alpha.3`; la migración 06 se aplica sola al arrancar; comprobación del registro del migrador.

## Anexo F — Resultado

**Estado:** entregada, pendiente de revisión (2026-10-14). **Revisa:** Claude (arquitecto).
**Autoría:** Claude Code escribió el tope inicial, su prueba, la versión, el historial y el avance de las revisiones documentales; quedó sin commit. Codex retomó ese avance por petición del usuario: corrigió la concurrencia, agregó las pruebas de borde y de MySQL, completó §2/§7, las descargas, el estado y esta entrega. No rehízo C4 ni cambió la interfaz.

**Qué.** El titular puede recibir un enlace de activación como máximo cada cinco minutos y tres en una hora por cuenta. Al bloquearlo no se reemplaza el enlace vigente ni sale correo, y se conserva la respuesta pública. El reenvío desde la clínica sigue sin ese tope. Google prueba la identidad y activa con aceptación, sin reenviar un enlace. La fila de la cuenta se bloquea antes de comprobar y reservar; el contador se lee actualizado incluso cuando el autorregistro ya tenía una lectura anterior en su transacción.

**RE y pruebas.** RE-T.19.1/RN-G15: `M5C4bPersonalTest::testElTitularPideElEnlaceConTopeYLaClinicaReenviaSinEl`, `testElTopeEsPorCuentaYNoInvalidaElEnlaceCuandoBloquea` y `testGoogleConUnaCuentaPendientePideAceptarLaPoliticaYLaActivaSinContrasena`. La respuesta del autorregistro es idéntica y los bloqueos no envían. `BaseV2MysqlTest::testM5FTopeDeActivacionSerializaLaCuentaYSeComparteEntreConexiones` verifica bloqueo, rollback y una transacción con instantánea anterior. Los **24 RE** de las seis HU tienen evidencia final por requisito en §2. RE-2.10.1/RN-113 siguen con su excepción explícita: alergias y alertas llegan con M2.

**Cierre documental.** `2.0.0-alpha.3`; seis HU implementadas y estados de RN-109–116/RN-G20–21. Una revisión por documento: ERS 3.4, Ficha 3.3, HU 3.3, RE 3.3, Reglas 2.3 y Visión 1.4; se conservó el avance de Claude sin subirlas otra vez. MER y Modelos no tienen revisión numérica, igual que en F0. README e historial actualizados. Modelos §11 refleja el tope y renderiza; el resto del flujo no cambia. PDF/Word de los ocho documentos publicados modificados regenerados; Modelos no tiene descarga según la configuración del exportador.

**Verificación.** PHP: **691 pruebas**, 4231 aserciones y 31 MySQL omitidas sin variable; con MariaDB temporal propia y exclusivamente `zooki_test_base_v2`: **691 pruebas, 4603 aserciones, ninguna omitida**. JS: **167/167**. `exportar.mjs --revisar`: «Las descargas están al día». Los ocho PDF y los ocho paquetes Word se comprobaron íntegros, con sus revisiones correctas. Los bloqueos de OneDrive se resolvieron con un reintento del exportador y el retoque final de Requisitos sobre una copia temporal propia fuera de OneDrive; todos los índices/tablas se completaron. No se leyó ni escribió `.env` ni se accedió a la base de trabajo.

**Pendientes.** Revisión de Claude, commit, merge/tag/despliegue y las tres comprobaciones siguientes: los hace el usuario. Producción sigue en alpha.2 hasta desplegar. No se empezó M2.

### Commit del cierre — PowerShell 5.1

Desde la carpeta del proyecto, después de la revisión:

```powershell
git add -- (Get-Content -LiteralPath .\scratch\archivos-M5-F.txt)
git diff --cached --stat
git commit -m "feat(portal): cerrar M5 y publicar alpha.3 (RE-T.19.1, HU-5.9)"
```

### Despliegue — lo ejecuta el usuario

PowerShell 5.1, después del commit, con el árbol limpio y en `v2/m5-portal`. El push a `main` inicia el despliegue de Dokploy.

```powershell
git push origin v2/m5-portal
git checkout main
git pull --ff-only origin main
git merge --no-ff v2/m5-portal -m "merge: publicar portal multiclinica (v2.0.0-alpha.3)"
$commitAlfa3 = git rev-parse HEAD
git push origin main
git tag -a v2.0.0-alpha.3 $commitAlfa3 -m "Zooki v2.0.0-alpha.3 - portal multiclinica"
git push origin v2.0.0-alpha.3
git checkout v2/m5-portal
```

En la terminal del VPS, después de arrancar la imagen nueva:

```sh
docker logs zooki_web
docker exec zooki_web php /var/www/html/Zooki/scripts/migrar.php --revisar
```

El arranque ejecuta `scripts/migrar.php` automáticamente. Los logs deben incluir `Aplicadas: 06_medio_activacion_propietario.sql` (puede venir junto a otros archivos); después, `--revisar` debe decir «La base está al día». La 06 conserva las filas y los medios anteriores; no se borra ni reinicia la base. Si aparece «No se aplicaron todas las migraciones», revisar ese error antes de comprobar la activación; no se considera aplicado por haber arrancado Apache.

### Tres comprobaciones en producción

- [ ] **1. Versión y portal.** El portal de documentación y su historial muestran `2.0.0-alpha.3`, y las descargas nuevas abren. Con un propietario, comprobar Inicio · Citas · Clínicas · Perfil y Agendar, en computador y celular; cambios de mascotas y citas se reflejan sin recargar toda la pantalla.
- [ ] **2. Privacidad y vínculos.** Con una mascota atendida en dos clínicas, cada registro muestra su clínica. Revocar la autorización deja de mostrar las consultas ajenas a la otra clínica inmediatamente. Desvincularse sin citas pendientes conserva la historia del propietario y deja a esa clínica sin datos de contacto nuevos ni registros nuevos. «Mis clínicas» muestra el tope de tres del portal.
- [ ] **3. Activación.** La clínica registra un propietario sin aceptación presencial: puede atenderlo y le llega el enlace. Pedirlo inmediatamente por «¿Olvidaste tu contraseña?» o autorregistro mantiene el mensaje genérico y no envía otro; pasado el intervalo permite renovarlo. La clínica sí puede reenviarlo. El enlace permite aceptar y crear contraseña; con Google exige aceptación y activa sin contraseña.

### F — Revisión (2026-10-14)

**Revisó:** Claude (arquitecto). **Resultado: aprobada.** PHP 691 pruebas en verde en una copia limpia sin `.env` (31 MySQL omitidas); JS 167/167. El tope de activación bloquea la fila de la cuenta y lee el contador con `FOR UPDATE` (también dentro de la transacción del autorregistro), no reemplaza el enlace vigente al bloquear y no limita el reenvío de la clínica; `created_at` lo pone PHP para medir con la misma hora. Versión `2.0.0-alpha.3`, seis HU implementadas y una revisión por documento, como en el cierre de M0. Escribieron Claude Code y Codex (anotado en el Anexo). Sigue el despliegue por el usuario.

**Despliegue de 2.0.0-alpha.3 (2026-10-14).** El usuario hizo el merge `v2/m5-portal` → `main` y el tag `v2.0.0-alpha.3`. En producción: migración 06 aplicada (`[migraciones] Aplicadas: 06_medio_activacion_propietario.sql`), `--revisar` dice que la base está al día (la semilla se revisa en cada arranque, por diseño) y la aplicación responde `2.0.0-alpha.3`. **M5 cerrado.**
