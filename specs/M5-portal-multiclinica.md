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

- [ ] Aprobar este plan (usuario). §6.1 decidida: la foto de Google no se guarda.
- [ ] A — Inventario y verificación (Claude Code; revisa Claude).
- [ ] C1 — Huecos funcionales.
- [ ] C2 — Portal del propietario pulido.
- [ ] C3 — Lado del personal (HU-1.5, HU-2.10).
- [ ] `vendor/bin/phpunit` completo, JS y cada RE de §2 con evidencia.
- [ ] F — `2.0.0-alpha.3`: HU a «Implementada», revisiones de documentos, historial, descargas, merge y tag.
- [ ] Comandos de commit por etapa para PowerShell (el usuario hace ramas, commits, push y tags).

**Resultado de pruebas:** pendiente.
