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

## E1 — Cinco pruebas del usuario (2026-10-10)

Usa las cuentas de prueba que ya tienes; no hace falta regenerar sus contraseñas.
Abre primero Gina en un navegador y usa otra sesión para el personal.
Los correos se revisan en `logs/correos/` si tienes `MAIL_MODO=archivo` configurado en local.
Haz las comprobaciones de pantalla también en celular.

- [ ] **1. Gina — Clínicas y permisos.** En `?action=plataforma_inicio`, comprueba tarjetas, cambio a tabla y que al recargar conserve la vista; busca por nombre/NIT y filtra estado. Un registro sin confirmar debe verse como «Pendiente de verificación». Gina recibe 403 al abrir `?action=vet_pacientes`; Ana/Beto reciben 403 al abrir el panel de plataforma.
- [ ] **2. Gina y Beto — Plan y suspensión.** En Gestionar, cambia plan/suscripción con motivo y comprueba el resultado inmediato; sin motivo no guarda. Suspende Norte con confirmación y motivo. La siguiente petición de Beto pierde ese contexto, y Norte no se ofrece en el registro ni para agendar desde el portal. Reactiva con motivo: Beto vuelve a elegir Norte en el selector. Para «Dar de baja», comprueba el texto de confirmación y pulsa Cancelar; no des de baja una clínica que quieras seguir usando en las pruebas.
- [ ] **3. Gina — Soporte.** Con dos casos de prueba (se pueden generar reportando un NIT duplicado), resuelve uno y descarta el otro con nota; confirma y comprueba que el estado cambia sin recargar a mano. La pantalla no contiene enlaces a mascotas, consultas ni citas.
- [ ] **4. Gina — IP y aviso manual.** Mantén abierta la sesión de Gina; desde otra sesión genera 20 accesos fallidos con cuentas conocidas de Norte/Sur. El panel debe mostrar la IP, vencimiento, esas cuentas y clínicas. Abrir «Enviar aviso» no crea un correo: elige administrador, edita texto y confirma Enviar; solo ese destinatario recibe el aviso. Desbloquea con confirmación y vuelve a intentar entrar desde la misma sesión bloqueada; el CAPTCHA por cuenta se conserva cuando corresponde.
- [ ] **5. Registro de clínica — NIT/DV.** En `?action=registro_clinica`, NIT y DV están juntos y separados: NIT `900123456`, DV `8` válido; DV `2` muestra error bajo el campo. Letras/guion no entran, DV solo deja un dígito y pegar un NIT largo respeta el máximo. Con un NIT de una clínica ya registrada, el reporte usa ambas casillas y el sexto envío de ese día/IP se rechaza sin crear otro caso.

Dime solo lo que falló, con el número del paso.
