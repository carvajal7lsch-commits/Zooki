# Estado del trabajo — dónde vamos

> Lo actualiza el arquitecto al final de cada revisión. Una sesión nueva empieza leyendo este archivo, [`metodo.md`](metodo.md) y el plan activo.

**Actualizado:** 2026-10-12

## Ahora

- **Módulo activo:** M5 — Portal multi-clínica y vínculos (HU-T.18, HU-5.9, HU-1.5, HU-5.12, HU-5.13, HU-2.10; sale como `2.0.0-alpha.3`). Plan: [`specs/M5-portal-multiclinica.md`](../specs/M5-portal-multiclinica.md). Rama: `v2/m5-portal`.
- **Entrega actual de M5:** **C2b — portal: Agendar por pasos, Clínicas, Perfil y actualización en su sitio: entregada, pendiente de revisión (2026-10-12, Claude Code).** Agendar en cuatro pasos con tarjetas de elección; con una sola clínica, su nombre y el id oculto (hueco 8, RE-5.9.3). Todas las acciones se ponen al día sin recargar (`refrescarPortal`/`aplicarPartes`). Clínicas en tarjetas con interruptor, rechazo en la tarjeta y buscador. Perfil en tarjetas plegables. Formularios de mascota con validación en tiempo real, avisos de una línea y restricción de entrada (`ValidadorMascota::atributosHtml`, `validacion-mascota.js`, `CampoInvalido`). Se quitaron las reglas CSS sin uso de `inicio.css`, `agenda.css` y `cuenta.css`. PHP 652 en verde (también con MySQL, ninguna omitida); JS 129. Detalle y 5 pantallas en «Anexo C2b — Resultado».
- **C1.1:** revisada y aprobada (2026-10-12), con commit.
- **C1:** revisada y aprobada (2026-10-12), con commit.
- **A:** revisada y aprobada (2026-10-12), con las decisiones del usuario en «A — Revisión» del plan M5.
- **Módulo anterior:** M0 — Base SaaS, identidad y aislamiento, cerrado. Plan: [`specs/M0-T-base-saas-identidad.md`](../specs/M0-T-base-saas-identidad.md).
- **Hecho y revisado:** A (inventario), B (base v2 limpia), C1–C9 y C9.1 (código pasado al modelo v2, etapa C cerrada, HU-T.15 cerrada), D1 (sesión, política de datos, registro del propietario y Google), D2 (cuentas del personal y del titular; revisada el 2026-10-08, **aprobada con corrección**).
- **Entrega actual:** **M0 cerrado y pulido (2026-10-12).** Producción en 2.0.0-alpha.2 (`main` `875d035`, tag `v2.0.0-alpha.2`): acceso y registros pulidos, panel de plataforma y páginas de los correos con el marco del acceso.
- **M0 cerrado (2026-10-11).** F1 ejecutada por el usuario: producción en 2.0.0-alpha.1, base v2 limpia, super-administrador y clínica demo «Zooki» activos, IP real comprobada (`TRUSTED_PROXIES=10.0.1.0/24,172.16.0.1,172.16.11.1`). Bitácora al final del plan M0.
- **Verificación E2:** PHP 566 en verde (SQLite: 3004 aserciones, 29 pruebas MySQL omitidas; con MariaDB temporal: 3363 aserciones, ninguna omitida, base predeterminada `zooki_test_base_v2`); JS 78 en verde. Modelos §12.1, §12.1.1 y §12.2 renderizados. Migración 05 repetible y límites personalizados conservados. No se accedió a la base de trabajo ni a sus contraseñas.
- **D2.2:** revisada, aprobada y con commit. Verificación de D3 en su anexo del plan.
- **D3:** revisada y **aprobada** (2026-10-10; «D3 — Revisión» en el plan), con commit `fa43c3d`. La prueba del usuario motivó D3.1.
- **Verificación D3.1:** PHP 532 en verde (también con MariaDB temporal, 3114 aserciones, ninguna omitida, base predeterminada `zooki_test_base_v2`); JS 68 en verde. Sin migración ni cambios al flujo de Modelos §12.1.
- **D3.1:** revisada y aprobada en código (2026-10-10, commit `391c73d`); falta la prueba del usuario (pantalla de registro y restricción de entrada).
- **E1:** revisada, **aprobada** y con commit; el usuario pasó IP, aviso y desbloqueo. Los hallazgos posteriores de pantalla y suspensión se corrigieron en E2.
- **Decisiones durante E2 (2026-10-11):** comparación del NIT con/sin guion; solo las clínicas de prueba reconocidas se normalizan y el script se detiene ante un NIT ajeno coincidente, sin modificar datos. Reactivar personal también exige cupo en el botón de estado y el modal. Anotadas en el plan.
- **Siguiente paso:** M5, C2b revisada y aprobada (2026-10-12); faltan la prueba visual del usuario y su commit. Sigue C3 — lado del personal («C3 — Alcance» del plan M5: alta del propietario sin aceptación presencial, confirmación corta, búsqueda en tiempo real, modal de vincular mascota, solo lectura y formulario de mascota). Después F (2.0.0-alpha.3). Pendiente aparte: borrar los respaldos temporales del VPS tras una o dos semanas sin problemas.

## D2.1 — alcance (corrección de D2; hecho y revisado)

1. **Bloqueante antes de F:** límites de intentos, comprobaciones y Turnstile con la IP real (`Auditoria::ipCliente()`, primera IP no confiable desde la derecha de `X-Forwarded-For`, `TRUSTED_PROXIES` en `.env.example`).
2. **Decisión del usuario (2026-10-08):** 20 fallos por IP bloquean la IP; 5 por cuenta exigen CAPTCHA sin bloquear la cuenta. Ajustar RE-T.13.4 y RN-G15.
3. Un acceso correcto no borra el contador de la IP.
4. Comprobaciones al escribir: con sesión, límite por `id_usuario` (120 en 15 minutos); sin sesión, el de la IP. Mensaje de unicidad con el nombre del campo.
5. Validación en cada tecla (reglas del navegador al instante, servidor con debounce sin borrar el aviso).
6. Mi perfil: requisitos de contraseña marcados al escribir y «Las contraseñas coinciden».
7. Correos propios para la invitación del personal y el restablecimiento por el administrador.
8. Modal del personal en Usuarios con el diseño de v1.12.0 (`specs/referencias/usuarios-modal-editar-v1.png`). **Decisión del usuario (2026-10-09):** los modales del propietario en Pacientes no se tocan.
9. La limpieza de pendientes no se detiene si una cuenta falla.
10. «Contraseña actual» en `cambiar_password.php` con el estilo de la pantalla.
11. Prueba local: correos a archivo (`MAIL_MODO=archivo`, solo en local), `datos_prueba.php --clave=...` y política aceptada para los usuarios de prueba; `metodo.md` §9 y `prueba-manual.md` al día.

## D2 — alcance acordado (hecho)

1. Alta de personal (**decisión del usuario, 2026-10-08**): la cuenta se crea pendiente e inerte (sin contraseña, sin poder entrar, sin consentimiento); el titular recibe un enlace que vence en 72 horas, acepta la política y crea su contraseña; si no lo usa, la cuenta pendiente se elimina si no tiene otros vínculos. Ajustar el texto de RE-T.19.1. Se acaba el envío de contraseñas por correo.
2. Cambio de correo (RE-T.5.7, RN-G23) y de documento (RE-T.5.8, RN-G24) por el titular, con verificación.
3. CAPTCHA por cuenta con Cloudflare Turnstile (RE-T.13.5, RN-G15); helper reutilizable para D3.
4. El enlace de verificación de correo se confirma con un botón (POST), no al abrirlo (revisión de D1).
5. Excepción del super-administrador escrita en RE-T.19.2.
6. **Validación en tiempo real** en los formularios de cuenta (hallazgos del usuario en D1): política de contraseña mientras se escribe (sin SweetAlert al enviar), documento único y teléfono con formato en «completar perfil» y en el registro, y el teléfono al editar a Fabio. Componente reutilizable para el resto del sistema.

## Después

- **D3:** registro de clínicas (HU-0.1, con Turnstile y NIT válido) y activación (HU-0.2, plan gratuito y catálogos iniciales, RE-0.2.5).
- **E:** panel del super-administrador (HU-0.3 sin RE-0.3.6) y límites del plan (HU-0.4). **Decisión del usuario (2026-10-09):** el panel lista las IP bloqueadas por RN-G15 con un botón «Desbloquear» solo para el super-administrador; no se envía correo al administrador de la clínica por cada bloqueo (un bot llenaría los buzones), queda en la auditoría. **Ampliación (2026-10-09):** junto a cada IP bloqueada, un botón «Enviar aviso» que el super-administrador usa a mano para escribir al administrador de la clínica (nunca automático). Como una IP no pertenece a una clínica, el panel muestra las cuentas que fallaron desde esa IP (según la auditoría de LOGIN_FAIL) y sus clínicas, y el super-administrador elige a quién avisar.
- **F:** respaldo, base de producción reiniciada, blindaje de Apache (`public/` y `.dockerignore`), despliegue, super-administrador y clínica demo con `zooki.vet@gmail.com`.
- **Cierre de M0:** estados de HU/RE, una revisión nueva por documento, versión, historial y descargas del portal.
- **Pulido de interfaz:** [`specs/pulido-interfaz.md`](../specs/pulido-interfaz.md), al final de la v2.0.

## Agentes

- **Arquitecto y revisor:** sesión de Claude con acceso a la carpeta del proyecto.
- **Ejecutor:** **Codex** escribe el código desde el 2026-10-10 (decisión del usuario); GPT-6.1 Sol, razonamiento medio, alto en subetapas de seguridad. Claude Code queda de respaldo.
- **Revisor:** la sesión de Claude revisa el código, corre las suites (PHP y JS) y compara con los diagramas; no escribe la subetapa.
- **Pruebas en el navegador:** el usuario.
