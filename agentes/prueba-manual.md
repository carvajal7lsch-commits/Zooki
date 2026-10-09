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
