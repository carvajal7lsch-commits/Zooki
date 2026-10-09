# Estado del trabajo — dónde vamos

> Lo actualiza el arquitecto al final de cada revisión. Una sesión nueva empieza leyendo este archivo, [`metodo.md`](metodo.md) y el plan activo.

**Actualizado:** 2026-10-09

## Ahora

- **Módulo activo:** M0 — Base SaaS, identidad y aislamiento. Plan: [`specs/M0-T-base-saas-identidad.md`](../specs/M0-T-base-saas-identidad.md).
- **Rama:** `v2/m0`. Producción sigue en v1.12.0 (`main`) hasta la etapa F.
- **Hecho y revisado:** A (inventario), B (base v2 limpia), C1–C9 y C9.1 (código pasado al modelo v2, etapa C cerrada, HU-T.15 cerrada), D1 (sesión, política de datos, registro del propietario y Google), D2 (cuentas del personal y del titular; revisada el 2026-10-08, **aprobada con corrección**).
- **Entrega actual:** D2.1 revisada, aprobada y con commit (`a66830f`, `a4fdf31`). Recorrido funcional del revisor hecho el 2026-10-09 (plan, «D2.1 — Recorrido funcional del revisor»); el revisor corrigió una línea en `validacion-cuenta.js` (el primer clic no enviaba), pendiente de commit del usuario.
- **Siguiente paso:** revisión visual del usuario (lista en la conversación) y prompt de D3, con los siete puntos «De paso en D3» del recorrido.
- **Para F:** `TRUSTED_PROXIES` con el rango de la red de Traefik; sin eso vuelve el bloqueo global por IP.

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
- **E:** panel del super-administrador (HU-0.3 sin RE-0.3.6) y límites del plan (HU-0.4).
- **F:** respaldo, base de producción reiniciada, blindaje de Apache (`public/` y `.dockerignore`), despliegue, super-administrador y clínica demo con `zooki.vet@gmail.com`.
- **Cierre de M0:** estados de HU/RE, una revisión nueva por documento, versión, historial y descargas del portal.
- **Pulido de interfaz:** [`specs/pulido-interfaz.md`](../specs/pulido-interfaz.md), al final de la v2.0.

## Agentes

- **Arquitecto y revisor:** sesión de Claude con acceso a la carpeta del proyecto.
- **Ejecutores:** Claude Code (principal) y Codex (cuando Claude Code no tiene cupo). Para Codex: GPT-6.1 Sol, razonamiento medio; alto en subetapas de seguridad.
