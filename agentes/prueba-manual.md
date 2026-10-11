# Prueba manual del usuario — guía

> Formato y datos para las listas de prueba que el arquitecto prepara al cerrar cada subetapa. El usuario las sigue en el navegador y responde solo con lo que falló, citando el número del paso.

## Preparación

1. XAMPP: **Apache** y **MySQL** encendidos.
2. Desde la carpeta del proyecto: `php scripts/dev/datos_prueba.php --si --clave=<clave>` (o `C:\xampp\php\php.exe scripts/dev/datos_prueba.php --si --clave=<clave>`): todos los usuarios de prueba quedan con esa clave y con la política aceptada. Sin `--clave`, el script genera una por usuario y las imprime.
   - En el `.env` local: `MAIL_MODO=archivo` (los correos quedan en `logs/correos/` y se abren con el navegador) y `APP_URL` comentado.
3. URL base local: `http://localhost/Zooki/public/index.php` (una pantalla se abre con `?action=<accion>`).
4. Tamaño celular en Chrome: **F12** y luego **Ctrl + Shift + M**. Recargar sin caché: **Ctrl + F5**.
5. Para tener varios usuarios a la vez sin cerrar sesión: `http://localhost/...`, `http://127.0.0.1/...`, una ventana privada y otro navegador son sesiones distintas.

## Usuarios de prueba

| Usuario | Documento / correo | Contextos |
|---|---|---|
| Ana Norte | 1000000001 · ana.norte@zooki.test | Administradora de Clínica Norte |
| Beto Norte | 1000000002 · beto.norte@zooki.test | Veterinario de Clínica Norte |
| Carla Sur | 1000000003 · carla.sur@zooki.test | Administradora de Clínica Sur |
| Diego Sur | 1000000004 · diego.sur@zooki.test | Veterinario de Clínica Sur |
| Elena Doble | 1000000005 · elena.doble@zooki.test | Veterinaria de Norte, administradora de Sur y propietaria en Norte |
| Fabio Propietario | 1000000006 · fabio.propietario@zooki.test | Propietario (Luna y Kira) |
| Gina Plataforma | gina.plataforma@zooki.test | Super-administradora |

## Formato de la lista

- Agrupada por usuario, en el orden en que conviene probar.
- Cada paso con su casilla, la pantalla (`?action=...`) y **qué debe pasar**.
- Siempre incluye: una comprobación de que una clínica no ve lo de la otra, las pantallas tocadas en celular y lo que cambió en el JavaScript.
- Al final: «dime solo lo que falló, con el número del paso».

## E2 — Cuatro pruebas del usuario (2026-10-11)

Usa las cuentas que ya tienes; no hace falta regenerar sus contraseñas.
Aplica primero `php scripts/migrar.php` en tu entorno local para cargar la migración 05.
Abre Gina en un navegador y otra sesión para el personal.
Los correos se revisan en `logs/correos/` si tienes `MAIL_MODO=archivo` configurado.
Haz las comprobaciones de pantalla también en celular; no repitas las IP y avisos que ya probaste en E1.

- [ ] **1. Gina y Beto — Modal y suspensión.** En `?action=plataforma_inicio`, Gestionar Norte → Suspender con motivo: debe enviar y actualizar la tarjeta sin otra confirmación; Beto pierde ese contexto en su siguiente petición. Reactiva con motivo: Beto vuelve a elegir Norte. «Dar de baja» sí pide confirmación; pulsa Cancelar. Comprueba que el modal conserva los componentes administrativos y se puede usar en celular.
- [ ] **2. Registro de clínica — NIT duplicado al escribir.** En `?action=registro_clinica`, escribe NIT `900123456` y DV `8` (o el NIT/DV de una clínica existente): sin enviar debe aparecer el aviso junto al campo y «Reportar a soporte», sin identificar al administrador. Cambia el DV a `2`: se muestra el error de DV y desaparece el reporte. Vuelve a `8`: reaparece el duplicado y permite reportarlo. Verifica letras/pegado y el diseño en celular.
- [ ] **3. Gina y Ana — Cupo y reactivación de personal.** Gina asigna a Norte el plan Gratuito con motivo. Si están los tres usuarios originales, Ana desactiva a Beto en `?action=admin_usuarios`: quedan Ana y Elena (2/2). Invitar otra persona y reactivar Beto, tanto por el botón de estado como por el modal, deben mostrar el límite sin cambiar sus datos. Desactiva a Elena solo en Norte (queda 1/2), reactiva Beto y comprueba que permite hacerlo (2/2). Carla verifica que el vínculo de Elena en Sur sigue activo.
- [ ] **4. Gina — Uso agregado y cambio de plan.** En el panel, Norte muestra uso contra límites 2/50/100; cambia entre tarjetas y tabla y recarga: conserva la vista. Cambia Norte a Profesional con suscripción activa y motivo: actualiza inmediatamente y muestra «Sin límite» para los recursos. Sur conserva su propio plan y uso. El panel muestra únicamente los números, sin enlaces a mascotas ni citas individuales. Devuelve Norte al plan elegido para tus pruebas.

Los bordes de 50 mascotas/100 citas, las invitaciones pendientes, el aviso neutral del portal y la entrada roja ya tienen pruebas automáticas; no hace falta crear cientos de registros a mano. Estas pruebas pueden dejar a Elena inactiva solo en Norte; el vínculo se conserva.

Dime solo lo que falló, con el número del paso.
