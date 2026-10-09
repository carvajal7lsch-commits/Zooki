# Estado del trabajo — dónde vamos

> Lo actualiza el arquitecto al final de cada revisión. Una sesión nueva empieza leyendo este archivo, [`metodo.md`](metodo.md) y el plan activo.

**Actualizado:** 2026-10-08

## Ahora

- **Módulo activo:** M0 — Base SaaS, identidad y aislamiento. Plan: [`specs/M0-T-base-saas-identidad.md`](../specs/M0-T-base-saas-identidad.md).
- **Rama:** `v2/m0`. Producción sigue en v1.12.0 (`main`) hasta la etapa F.
- **Hecho y revisado:** A (inventario), B (base v2 limpia), C1–C9 y C9.1 (código pasado al modelo v2, etapa C cerrada, HU-T.15 cerrada), D1 (sesión, política de datos, registro del propietario y Google), D2 (cuentas del personal y del titular; revisada el 2026-10-08, **aprobada con corrección**).
- **Entrega actual:** D2 revisada; hallazgos en «D2 — Revisión» del plan. Falta el commit de D2 (usuario) y su prueba manual.
- **Siguiente paso:** el usuario hace commit de D2, sigue la lista de prueba manual y responde con lo que falló; con eso, el arquitecto redacta el prompt de **D2.1** (corrección). D3 empieza después de D2.1.

## D2.1 — alcance propuesto (corrección de D2)

1. **Bloqueante antes de F:** límites de intentos, comprobaciones y Turnstile con la IP real (`Auditoria::ipCliente()`, última IP no confiable de `X-Forwarded-For`, `TRUSTED_PROXIES` en `.env.example`).
2. Un acceso correcto no borra el contador de la IP (RE-T.13.4).
3. Comprobaciones al escribir: con sesión, límite por `id_usuario` (propuesta: 120 en 15 minutos); sin sesión, el de la IP.
4. Correos propios para la invitación de personal y para el restablecimiento por el administrador.
5. Mi perfil: la lista de requisitos de contraseña vuelve a marcarse al escribir y «Las contraseñas coinciden».
6. La limpieza de cuentas pendientes no se detiene si una cuenta falla.
7. Lo que falle en la prueba manual de D2.

**Decisión del usuario (2026-10-08):** límite de 20 fallos por IP y 5 por cuenta con CAPTCHA (no se bloquea la cuenta); D2.1 ajusta RE-T.13.4 y RN-G15.

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
