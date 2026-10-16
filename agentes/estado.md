# Estado del trabajo — dónde vamos

> Lo actualiza el arquitecto al final de cada revisión. Una sesión nueva empieza leyendo este archivo, [`metodo.md`](metodo.md) y el plan activo.

**Actualizado:** 2026-10-16

## Ahora

- **Módulo activo:** M2 — Historia clínica y soporte a la decisión (Grafo I), previsto como `2.0.0-alpha.4`. Plan: [`specs/M2-grafo-clinico.md`](../specs/M2-grafo-clinico.md). Rama: `v2/m2-grafo`.
- **Entrega actual de M2:** **C8 — rediseño visual de la consulta y del vínculo: revisada y aprobada en código (2026-10-16).** Lo escribió Claude Code. Ver «Anexo C8» y «C8 — Revisión». F con commit `d3be616`.
- **C7:** revisada y aprobada en código (2026-10-16), escrita por Claude Code; ver «C7 — Revisión». Todas las etapas de construcción de M2 están revisadas, sin dar por aprobadas sus pruebas manuales pendientes.
- **Verificación F:** PHP 852 (7182 aserciones y 44 omitidas sin host; 7783 aserciones y ninguna omitida con MariaDB 10.4.32 temporal propia y `zooki_test_base_v2`); JS 211/211; batería 26/26; 27 diagramas renderizados; 96 HU, 427 RE, 120 RN y 52 tablas coherentes. Ocho PDF y ocho Word regenerados, índices completos e integridad/revisiones verificadas; `exportar.mjs --revisar`: «Las descargas están al día». Detalle en Anexo F.
- **Decisión del usuario durante F (2026-10-16):** verificar migración/carga en el log completo, sin `--tail`, y comprobar `App::VERSION` dentro del contenedor y en pantalla; el arranque no imprime la versión y no se modifica para agregarla.
- **C5b — prescripción con alertas, tratamiento y peso desconocido:** revisada y aprobada en código (2026-10-16), pendiente de las pruebas del usuario. Lo escribió Claude Code. Ver «Anexo C5b» y «C5b — Revisión». C5a con commit `f2adbcb`. C1–C4, C6 y B cerradas.
- **C5a:** revisada y aprobada en código (2026-10-15), pendiente de las pruebas del usuario; commit `f2adbcb`. Lo escribió Claude Code. Ver «Anexo C5a» y «C5a — Revisión». C4 con commit `697cac7` (26/26). C1, C2, C3, C6 y B cerradas.
- **C4:** revisada y aprobada (2026-10-15); la batería da 26/26 (RE-2.11.7). Lo escribió Codex. Ver «Anexo C4» y «C4 — Revisión». C3 con commit `bf87ce3`. C1, C2, C6 y B cerradas. C5 se parte en C5a y C5b.
- **C3:** revisada y aprobada (2026-10-15), escrita por Codex; ver «C3 — Revisión». C2 con commit `172a707`; CI corregido en `668cd0a`. C1, C6 y B cerradas.
- **Verificación C4:** PHP 828 (7025 aserciones y 41 omitidas sin host; 7614 aserciones y ninguna omitida con MariaDB 10.4.32 temporal propia y `zooki_test_base_v2`); JS 183/183; 26/26 casos con conocimiento real, sin cambiar esperados ni reglas. C5 no se empezó.
- **C2:** revisada y aprobada (2026-10-15), escrita por Codex. Ver «C2 — Revisión» (verificada también en MySQL 8.0; corregidos los conteos del CI). C1, C6 y B cerradas.
- **Decisiones del usuario durante C3 (2026-10-15):** conciliación de máximo 500 caracteres, rechazando el exceso sin recortar; confirmar exige una huella opaca vigente por fila, invalidada por cambios de cruces, permisos o versión. Anotadas en §7 del plan M2.
- **Verificación C3:** PHP 783 (6758 aserciones y 40 omitidas sin host; 7337 aserciones y ninguna omitida con MariaDB 10.4.32 temporal propia y `zooki_test_base_v2`); JS 183/183; diagrama §3 renderizado y respuestas reales de aviso protegido y bloqueo en el anexo. Pantallas e integración del formulario pendientes de C5.
- **C1 cerrada** el 2026-10-15: el usuario pasó las cinco pruebas; commit `d8f8519`.
- **C6:** cerrada el 2026-10-15 (el usuario pasó las cinco pruebas del navegador; commit `1053ee7`). B (B1, B2, B2.1) cerrada y con commit.
- **B de M2 cerrada:** B1, B2 y B2.1 revisadas y aprobadas (2026-10-14). Conjunto: 47 fuentes, 105 nodos, 105 aristas, 201 sinónimos, 29 reglas, todo pendiente de validación veterinaria. Notas para C4 en «B2.1 — Revisión».
- **B2 de M2:** revisada y aprobada (2026-10-14), con sus correcciones aplicadas en B2.1.
  Conjunto actual: 105 nodos, 105 aristas, 201 sinónimos, 29 reglas y 47 fuentes; todo pendiente de validación veterinaria.
- **Verificación de B2.1:** PHP 724 (6200 aserciones y 36 omitidas sin host; 6624 aserciones y ninguna omitida con MariaDB 10.4.32 temporal propia y `zooki_test_base_v2`); JS 167/167.
  CLI `--revisar` sobre B2 cargado: 11 altas, 429 cambios por versión/contenido y 1 retiro; instantánea sin escrituras comprobada.
- **B1 de M2:** revisada y aprobada (2026-10-14); las dos correcciones de su revisión las hizo Codex al inicio de B2 y se revisan con B2.
- **A de M2:** revisada y aprobada con ocho ajustes (2026-10-14), aplicados a B1 y conservados como contrato de B2/C2–C4 en el plan.
- **Verificación de B1:** Codex: PHP 703 con MariaDB 10.4.32 (4692 aserciones, ninguna omitida); revisión de Claude: lo mismo con MariaDB 10.11 y sin MySQL (4284 aserciones, 34 omitidas); JS 167/167.
- **Decisiones del usuario durante A (2026-10-14):** no inventar edades ni tiempos; umbrales geriátricos por especie/raza con fuente y «No sé» temporal con revisión si hace falta para calcular. T-16 conserva amarillo provisional con revisión por tipo/cantidad de chocolate desconocidos; 48 horas no es una regla general para rebajar el nivel. Anotadas en §6 del plan M2.
- **M5:** cerrado y desplegado como `2.0.0-alpha.3` (2026-10-14); ver «Anexo F — Resultado» y bitácora de despliegue en [`specs/M5-portal-multiclinica.md`](../specs/M5-portal-multiclinica.md).
- **C4b:** revisada y aprobada por Claude (2026-10-13), con los ajustes visuales pedidos por el usuario el 2026-10-14 y commits `8224d98`, `7cdcf65`, `37f4836` y `f03d0b4`. Migración 06: medio `activacion_propietario` del consentimiento; se aplica automáticamente al desplegar.
- **C4a:** revisada y aprobada (2026-10-13), con commit `19728e6`.
- **C3:** revisada, aprobada y probada por el usuario (2026-10-13), con commit `2e251c4`. El propietario dado de alta por el personal queda pendiente, sin aceptación presencial; activa su cuenta desde un enlace de 72 horas donde acepta la política y crea su contraseña. Mientras tanto la clínica lo atiende sin enviarle correos automáticos; puede reenviarle el enlace, y la limpieza no borra una cuenta con mascotas. Además: búsqueda del propietario en tiempo real con límite por IP, vincular la mascota en tarjetas sin recargar, solo lectura en la historia (hueco 6), formulario de mascota del personal con la validación de C2b, confirmación corta al desactivar un cliente, tope de 3 clínicas desde el portal (RN-116 y RE-5.13.5, nuevos) y Agendar de nuevo en un solo formulario. PHP 665 en verde (también con MySQL, ninguna omitida); JS 142. Decisiones del usuario durante C3 y 5 pantallas en «Anexo C3 — Resultado».
- **C2b:** revisada y aprobada (2026-10-12), con commit `da1aadb`.
- **C1.1:** revisada y aprobada (2026-10-12), con commit.
- **C1:** revisada y aprobada (2026-10-12), con commit.
- **A:** revisada y aprobada (2026-10-12), con las decisiones del usuario en «A — Revisión» del plan M5.
- **Módulo anterior:** M0 — Base SaaS, identidad y aislamiento, cerrado. Plan: [`specs/M0-T-base-saas-identidad.md`](../specs/M0-T-base-saas-identidad.md).
- **Hecho y revisado:** A (inventario), B (base v2 limpia), C1–C9 y C9.1 (código pasado al modelo v2, etapa C cerrada, HU-T.15 cerrada), D1 (sesión, política de datos, registro del propietario y Google), D2 (cuentas del personal y del titular; revisada el 2026-10-08, **aprobada con corrección**).
- **Entrega actual:** **M5 cerrado y desplegado (2026-10-14).** Producción en 2.0.0-alpha.3: portal multi-clínica, vínculos y privacidad del propietario, alta sin aceptación presencial, tope de 3 clínicas y pulido del portal y del lado de la clínica. Migración 06 aplicada.
- **M0 cerrado (2026-10-11).** F1 ejecutada por el usuario: producción en 2.0.0-alpha.1, base v2 limpia, super-administrador y clínica demo «Zooki» activos, IP real comprobada (`TRUSTED_PROXIES=10.0.1.0/24,172.16.0.1,172.16.11.1`). Bitácora al final del plan M0.
- **Verificación E2:** PHP 566 en verde (SQLite: 3004 aserciones, 29 pruebas MySQL omitidas; con MariaDB temporal: 3363 aserciones, ninguna omitida, base predeterminada `zooki_test_base_v2`); JS 78 en verde. Modelos §12.1, §12.1.1 y §12.2 renderizados. Migración 05 repetible y límites personalizados conservados. No se accedió a la base de trabajo ni a sus contraseñas.
- **D2.2:** revisada, aprobada y con commit. Verificación de D3 en su anexo del plan.
- **D3:** revisada y **aprobada** (2026-10-10; «D3 — Revisión» en el plan), con commit `fa43c3d`. La prueba del usuario motivó D3.1.
- **Verificación D3.1:** PHP 532 en verde (también con MariaDB temporal, 3114 aserciones, ninguna omitida, base predeterminada `zooki_test_base_v2`); JS 68 en verde. Sin migración ni cambios al flujo de Modelos §12.1.
- **D3.1:** revisada y aprobada en código (2026-10-10, commit `391c73d`); falta la prueba del usuario (pantalla de registro y restricción de entrada).
- **E1:** revisada, **aprobada** y con commit; el usuario pasó IP, aviso y desbloqueo. Los hallazgos posteriores de pantalla y suspensión se corrigieron en E2.
- **Decisiones durante E2 (2026-10-11):** comparación del NIT con/sin guion; solo las clínicas de prueba reconocidas se normalizan y el script se detiene ante un NIT ajeno coincidente, sin modificar datos. Reactivar personal también exige cupo en el botón de estado y el modal. Anotadas en el plan.
- **Verificación de F:** PHP 691 en verde (sin MySQL: 4231 aserciones y 31 omitidas; con MariaDB temporal propia y `zooki_test_base_v2`: 4603 aserciones, ninguna omitida); JS 167/167. Descargas «al día»; diagrama §11 renderizado. No se leyó ni escribió `.env` ni se accedió a la base de trabajo.
- **C8 — rediseño visual de la consulta y del vínculo: entregada, pendiente de revisión (2026-10-16).** Lo escribió Claude Code. Ver «Anexo C8 — Resultado» del plan M2: hallazgos H2–H9 y el error H3, PHP 854 (7812 aserciones con MariaDB y `zooki_test_base_v2`, ninguna omitida), JS 227/227, capturas antes y después en `specs/referencias/m2-pulido/c8-antes/` y `c8-despues/`. Pendiente: revisión del arquitecto y las cuatro pruebas del navegador del anexo.
- **Siguiente paso:** commit de C8; el usuario repite las pruebas (cinco de «Pruebas del usuario antes del despliegue» y cuatro del Anexo C8); respaldo de producción antes del push a `main`; integración y tag `v2.0.0-alpha.4`; comprobación del VPS con el log completo. Luego, plan de M4. Pendiente aparte: borrar los respaldos temporales del VPS.
- **Para el plan de M4 (idea del usuario, 2026-10-15, por decidir allí):** el paciente que llega sin cita y no es urgencia debería pasar por la agenda. Recomendación del revisor: «Atención sin cita» (HU-2.2) crea por detrás una cita de llegada en curso, como sobrecupo si no hay hueco (RN-422), para que la agenda del veterinario refleje la realidad; las urgencias siguen HU-4.15. Hoy (M2) la consulta sin cita no crea cita.
  Reparto de M2: Codex C2–C4; Claude Code C5.
  RN-112 con «alerta médica» queda documentada en F; las alertas del imprimible ya quedaron implementadas y revisadas en C7.
  En XAMPP, antes de probar: `php scripts/migrar.php` y luego `php scripts/cargar_conocimiento.php`. Pendiente aparte: borrar los respaldos temporales del VPS.
- **Decisión del usuario durante B2 (2026-10-14):** mantener los doce predicados; obesidad de T-13 y tratamiento sin fármaco conocido de T-19 quedan como contexto.
  Usar anorexia sostenida en gatos y crisis breve habitual finalizada con epilepsia documentada, sin inferir medicamentos ni datos ausentes.
- **Decisiones confirmadas durante A (2026-10-14):** Gran danés se agrega en B1 con ID nuevo; las categorías taxonómicas amplias siguen sin cobertura específica y con revisión; el mecanismo de la decisión 3 queda adoptado.

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
