# DOC — Políticas, seguridad y manual de usuario para Zooki v2

> Plan de trabajo para actualizar los 4 documentos legales/informativos del footer y crear el manual
> de usuario. Lo ejecuta Claude Code o Codex por fases; Claude (Proyecto) revisa cada fase contra el
> inventario de hechos. El usuario hace los commits.

## Regla de oro
Cada afirmación de estos documentos debe describir lo que **el código hace hoy** (v2.x en producción),
no lo que dicen el ERS ni la versión 1. Si algo no está implementado, no se promete. Ante la duda, se
verifica en el código y se anota el archivo en el inventario. No se cambia el diseño de las páginas
legales (`views/legal/*.php` + `landing-modules/legal.css`): solo su contenido. Nada de CSS/JS en línea.

## Estado actual (diagnóstico)

| Documento | Dónde vive | Qué está mal hoy |
|---|---|---|
| Seguridad del sistema | El footer enlaza a `#seguridad` de la landing | No es un documento; no menciona aislamiento por clínica, CAPTCHA, carnet público ni pagos |
| Política de privacidad | `views/legal/privacidad.php` · versión `PoliticaDatos::VERSION = 2026-08-31` | Dice "en desarrollo, no opera comercialmente", Neiva, sin multi-clínica, sin pagos/Wompi, sin carnet QR (escaneos + ubicación), sin Turnstile, sin conservación de datos, sin la mascota global ni la autorización entre clínicas |
| Términos de uso | `views/legal/terminos.php` | "No se ofrece como servicio comercial"; "no emite diagnósticos" (hoy el Grafo I sugiere diagnósticos, triage y bloquea medicamentos); sin planes, precios, oferta, mora, gracia, congelamiento, IVA ni cancelación |
| Política de cookies | `views/legal/cookies.php` | Faltan Cloudflare Turnstile, el checkout de Wompi, `sessionStorage zooki-acceso-giro`, jQuery CDN y el permiso de ubicación del carnet; hay que confirmar los atributos de la cookie de sesión (`helpers/Sesion.php`) |
| Manual de usuario | No existe (footer: "Pronto") | Todo por hacer |

## Fases

### Fase 0 — Inventario de hechos (base de todo; sin redactar políticas todavía)
Crear `documentacion/legal/HechosV2.md` leyendo el código. Tablas, cada fila con el archivo que lo prueba:
1. **Datos que se tratan**, por titular (propietario, personal de clínica, clínica, quien escanea un carnet): campo, dónde se guarda, para qué, quién lo ve.
   Incluye clínicas (NIT, dirección, teléfono, logo), suscripciones/órdenes/pagos (qué guarda Zooki y qué queda en Wompi), invitaciones de personal, actividad y cierre de sesiones (`ActividadCuenta`, `CierreSesiones`), IP o hash de IP (rate limit, CAPTCHA, carnet), accesos y ubicación del carnet (`CarnetPublicoController::ubicacion`, `11_accesos_carnet.sql`), privacidad del carnet (`PrivacidadCarnet`), triage y síntomas, bitácora de comunicaciones y auditoría.
2. **Terceros**: Wompi, Cloudflare Turnstile, Google (OAuth y Fonts), proveedor de correo SMTP (cuál se usa en `config/EmailService.php`), alojamiento (Hostinger VPS: país del datacenter → posible transferencia internacional), jsDelivr, cdnjs, code.jquery.com y Algolia (`config/Algolia.php`: ¿se usa en producción?).
3. **Cookies y almacenamiento** (grep real en `public/js`, `views`, `helpers/Sesion.php`): nombre, tipo, duración, finalidad.
4. **Controles de seguridad reales**: aislamiento por `id_clinica` (403), roles, bcrypt, CSRF, rate limit (20 fallos por IP) + Turnstile, parámetros de la cookie de sesión, cierre de sesiones, archivos clínicos protegidos, carnet con token y límite de consultas, auditoría, HTTPS, respaldos (¿`backup.php` corre en producción? ¿con qué frecuencia?), y firma de los eventos de Wompi.
5. **Reglas comerciales vigentes**: RN-003/005 (topes del gratuito), RN-006 (urgencias rojas), RN-007 (gracia de 3 días y congelamiento), RN-008 (precios, oferta "paga 1, lleva 2", anual, sin IVA, el comprobante no es factura electrónica), RN-013 (no se borran datos), RN-004 (lo que ve el super-admin), RN-113 (autorización entre clínicas) y el estado del cobro automático (apagado).
6. **Lista de decisiones abiertas** para el usuario (abajo). La fase cierra cuando el usuario las responde.

### Fase 1 — Política de privacidad (`views/legal/privacidad.php`)
- **Responsable:** Juan Sebastián Carvajal Ome (persona natural), Florencia, Caquetá; canal zooki.vet@gmail.com. Quitar "en desarrollo/no comercial" y Neiva.
- **Doble rol, explicado claro:**
  - Para los datos de los clientes de cada clínica (propietarios, mascotas, historias), la clínica es Responsable y Zooki es Encargado.
  - Para las cuentas, la plataforma, la facturación de la suscripción y el carnet público, Zooki es Responsable.
- **Secciones nuevas o actualizadas:**
  - Datos (según la Fase 0)
  - Finalidades: suscripción y cobro, carnet de emergencia, apoyo clínico, comunicaciones y seguridad (CAPTCHA, límites)
  - Mascota global y vinculación a varias clínicas, con la autorización revocable entre clínicas (RN-113)
  - Carnet QR: qué ve quien escanea, qué controla el propietario y qué se registra del escaneo (incluida la ubicación opcional)
  - Encargados y terceros (Fase 0)
  - Transferencia o transmisión internacional (art. 26, si el servidor o los proveedores están fuera de Colombia)
  - **Conservación de los datos**: plazos por tipo (historia clínica según la Ley 576, auditoría, pagos y obligaciones tributarias, cuentas inactivas)
  - Historial de cambios
- **Versión:** subir `PoliticaDatos::VERSION` y `VIGENCIA`. Eso hace que todas las cuentas acepten la nueva versión (flujo ya existente: verificar que funcione con todos los medios: formulario, google, alta_personal y activacion_propietario).

### Fase 2 — Términos de uso (`views/legal/terminos.php`)
- **"El servicio":** Zooki como software en la nube por suscripción; perfiles (administrador de clínica, veterinario, propietario; el super-administrador solo opera la plataforma).
- **Planes y pagos** (sección nueva):
  - Gratuito con topes; Profesional mensual o anual con precios del catálogo; oferta de lanzamiento.
  - Pago en línea con Wompi; precio final sin IVA (no responsable de IVA); el comprobante no es factura electrónica.
  - Los cambios de precio no afectan periodos ya pagados; el cobro automático solo con autorización expresa.
  - Mora: gracia de 3 días y luego congelamiento de beneficios, sin borrar datos y sin suspender la cuenta.
  - Cancelación y reembolsos según la decisión D-4.
- **Responsabilidad clínica**, reescrita: el Grafo I **sugiere** diagnósticos, calcula el triage y bloquea combinaciones peligrosas. Es apoyo a la decisión, no un diagnóstico. Su base de conocimiento cubre sobre todo perros y gatos, y la decisión y la responsabilidad son del veterinario (Ley 576). Describir con exactitud qué pasa ante un bloqueo, según el código.
- **Agenda:** el triage y la reorganización automática son ayudas; la clínica confirma.
- **Obligaciones de la clínica** como Responsable de los datos de sus clientes.
- **Carnet QR:** el propietario decide qué se comparte.
- Ley aplicable y canal de contacto; versión, vigencia e historial de cambios.

### Fase 3 — Política de cookies (`views/legal/cookies.php`)
- Rehacer el inventario con la Fase 0, en una tabla: nombre, proveedor, tipo, duración y finalidad.
- Incluir Turnstile y Wompi, que solo cargan en el registro o el acceso y en el pago, y el permiso de ubicación del carnet (es un permiso del navegador, no una cookie, pero va explicado).
- Mantener "sin analítica ni publicidad" solo si sigue siendo cierto.

### Fase 4 — Página de seguridad (nueva: `views/legal/seguridad.php`, ruta `action=seguridad`, misma plantilla legal)
- Para clínicas que evalúan Zooki. Explica, sin exagerar y con lenguaje claro, los controles de la Fase 0:
  - Aislamiento entre clínicas y acceso por rol.
  - Contraseñas, sesiones, CAPTCHA y límites de intentos.
  - Archivos clínicos, auditoría, carnet público y respaldos.
  - Pagos: qué guarda Zooki y qué maneja Wompi.
  - Cómo reportar un fallo de seguridad (correo).
- Sin promesas de certificaciones ni de disponibilidad garantizada.
- Footer: "Seguridad del sistema" pasa a apuntar a esta página.

### Fase 5 — Manual de usuario (`documentacion/ManualUsuario.md`)
- **Estructura por rol, en pasos cortos y con capturas:**
  1. **Primeros pasos:**
     - Registrar la clínica, activar la cuenta y entrar (también con Google).
     - Recuperar la contraseña y cambiar de clínica si la cuenta tiene varias.
  2. **Administrador:**
     - Datos de la clínica y logo.
     - Horarios, tipos de cita y veterinarios.
     - Personal: invitar y desactivar.
     - Propietarios y mascotas.
     - Suscripción: plan, límites, pagar, oferta y mora.
     - Comunicaciones, auditoría y panel.
  3. **Veterinario:**
     - Inicio y agenda: llegadas, retrasos, sobrecupo y reasignación.
     - Urgencias (Nueva urgencia → Atender ahora).
     - Atención: síntomas → triage → diagnóstico → tratamiento → prescripción con alertas.
     - Vacunas y desparasitación, pacientes, mi perfil y notificaciones.
  4. **Propietario:**
     - Registro y vinculación a clínicas, mis mascotas.
     - Agendar: describir síntomas, y qué pasa si el triage sale rojo.
     - Calendario de salud, carnet QR y su privacidad.
     - Autorizar el acceso de otra clínica.
  5. **Solución de problemas y preguntas frecuentes:** límite del plan, correo que no llega, pago pendiente, cuenta congelada.
  6. **Glosario:** colores del triage, sobrecupo, gracia, etc.
- **Capturas:** de zooki-pruebas con la semilla de demo, a 1440 px y algunas a 390 px.
  - Se toman con un script de Playwright que entra con las cuentas demo y recorre una lista fija de rutas; guarda en `public/docs/diagramas/manual/`.
  - Es un script que el agente escribe y corre; no se navega pantalla por pantalla en el chat.
  - Si el usuario prefiere, las toma él y el manual deja marcadores `[captura: …]`.
- **Publicación:**
  - Cablearlo al portal de documentación (`helpers/DocsIndex.php`, `public/docs/index.php`, `public/docs/docs.js`) y generar el .docx/.pdf en `public/docs/descargas/` como los demás (`manifiesto.json`).
  - En el footer, quitar "Pronto" y enlazar al manual.
- **Trabajo por tramos:** se entrega un rol por vez y el usuario revisa cada uno.

### Fase 6 — Cierre
- Footer de la landing y portal: enlaces de los 5 documentos funcionando; la fecha de vigencia sale de una sola constante por documento.
- Pasada final de coherencia entre los 5 (mismos precios, topes, nombres de rol y correo) contra `HechosV2.md`.
- Recomendación: que una persona con conocimiento legal lea privacidad y términos antes de abrir el registro a clínicas reales.

## Decisiones (2026-11-03)
- **D-1 Responsable:** Juan Sebastián Carvajal Ome, persona natural, Florencia (Caquetá), zooki.vet@gmail.com. Sin cédula en el documento.
- **D-2 Servidor:** Hostinger, Estados Unidos. Va la sección de **transferencia internacional** de datos (art. 26 de la Ley 1581): los datos se alojan en EE. UU. con un proveedor que aplica medidas de seguridad; el titular lo autoriza al aceptar la política. Wompi y los servicios de Google/Cloudflare se declaran como encargados.
- **D-3 Conservación** (confirmada; no se programa ningún borrado automático en esta entrega, así que la política describe criterios y no promete purgas que no existen):
  - Cuenta y datos de contacto: mientras la cuenta esté activa; se suprimen a solicitud del titular.
  - Historia clínica y registros de la mascota: pertenecen a la clínica (Responsable). Se conservan mientras la clínica use Zooki y se cumple el deber de conservación de la historia clínica (Ley 576 de 2000). Si la clínica se va, se le entrega una copia a solicitud.
  - Pagos y comprobantes: 5 años (soportes contables y tributarios).
  - Auditoría y registros de seguridad (inicios de sesión, IP en hash, CAPTCHA): mientras sirvan para la seguridad y la trazabilidad; se fija un máximo de 2 años en una versión futura con purga programada.
  - Escaneos y ubicación del carnet: visibles para el propietario; misma regla que la auditoría.
- **D-4 Reembolsos** (confirmada; sin módulo nuevo):
  - **Cobro duplicado o erróneo:** reembolso total, siempre.
  - **Garantía del primer pago:** la clínica puede pedir la devolución total de su **primer** pago (mensual o anual) dentro de los **5 días hábiles** siguientes; vuelve al plan gratuito sin perder datos. Esto cubre de forma voluntaria el retracto, que la Ley 1480 exceptúa cuando el servicio empezó con el acuerdo del consumidor.
  - **Después de esos 5 días** no hay reembolsos parciales del periodo en curso; cancelar = no renovar (el cobro automático está apagado). Excepción: una falla grave del servicio atribuible a Zooki se compensa en proporción.
  - **Reversión del pago (art. 51 de la Ley 1480):** se menciona como derecho del consumidor ante fraude u operación no solicitada, y se tramita con el emisor del medio de pago.
  - **Cómo se pide:** correo a zooki.vet@gmail.com desde el correo del administrador, con la referencia del pago. El dueño revisa el caso, pasa la clínica a Gratuito desde el panel del super-admin (`plataforma_suscripcion_ajax`, queda auditado con motivo «reembolso») y devuelve el dinero manualmente por Wompi o por transferencia. El plazo de devolución es de máximo 30 días calendario.
  - El manual (rol administrador, Suscripción) explica este procedimiento. Un botón «Solicitar reembolso» que avise al super-admin queda como mejora futura.
- **D-5 Plan gratuito:** **no vence** (confirmada). Es freemium con topes (2/50/100), no una prueba; así lo dicen RN-005 y la landing.
- **D-6 Algolia:** sí se usa en producción; se declara como encargado (búsqueda). En la Fase 0 hay que verificar qué datos se le envían.
- **D-7 Super-administrador:** fuera del manual y de los documentos públicos (solo se menciona que la plataforma tiene un operador que no accede a fichas clínicas, RN-004).

## Prompt corto por fase (para pegar en el agente)
"Lee AGENTS.md y specs/DOC-legal-manual-v2.md. Ejecuta solo la Fase N. No cambies el diseño de las
vistas legales ni de la landing. Cada afirmación debe estar respaldada por `documentacion/legal/HechosV2.md`.
Sin suites de pruebas: verifica que las páginas carguen. Entrega la lista de archivos tocados. No hagas commit."
