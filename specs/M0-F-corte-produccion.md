# M0-F — Guía del corte de producción a 2.0.0-alpha.1

> Etapa F1 del plan [M0-T](M0-T-base-saas-identidad.md). La ejecuta el usuario, en orden, con esta guía; el agente acompaña. Escrita en F0 (2026-10-11) por Claude Code.
> Producción hoy: **v1.12.0** en Dokploy (compose con el servicio `web`), base de datos en el **MySQL central del VPS** (no en el contenedor `db` del compose). Los datos de producción son de prueba: **no se migran** (plan M0 §1 y §6).

## Cómo leer esta guía

- **PowerShell 5.1 (tu PC):** una orden por línea, sin `&&`. Se pegan en la carpeta del proyecto.
- **VPS (bash por SSH):** se pegan en la sesión SSH del servidor.
- **Nunca** escribas una contraseña o una clave en una orden: donde hace falta, el programa la pide y no queda en el historial. Las variables se nombran, nunca se muestran.
- Lo que va entre `<...>` lo reemplazas tú. Los valores se averiguan con la orden que aparece justo antes.
- Si un paso no da lo que dice «Debe salir», **para** y avísale al agente antes de seguir.

| Marcador | Qué es | Cómo se averigua |
|---|---|---|
| `<vps>` | usuario e IP o dominio del SSH, p. ej. `root@203.0.113.10` | el que usas hoy |
| `<dominio>` | dominio público de Zooki | el de `APP_URL` en Dokploy |
| `<mysql_central>` | contenedor del MySQL central | paso 1.1 |
| `<DB_NAME>` / `<DB_USER>` | base y usuario de Zooki | pestaña Environment de Dokploy (solo leer el nombre) |
| `<vol_uploads>` | volumen de las fotos y adjuntos | paso 1.1 |

---

## 0. Antes del día del corte (sin tocar producción)

### 0.1 Commits de F0 (PowerShell)

Los comandos de commit de F0 están en el anexo F0 del plan. Hazlos antes de seguir.

### 0.2 Comprobación del blindaje con una imagen local (opcional, PowerShell)

Solo si tienes Docker Desktop. Si no, la misma comprobación se hace en el paso 6.2 sobre producción.

```powershell
docker build -t zooki-f0 .
docker run --rm zooki-f0 ls -a /var/www/html/Zooki
docker run --rm -d -p 8088:80 --name zooki-f0 zooki-f0
curl.exe -s -o NUL -w "%{http_code} /.env`n" http://localhost:8088/.env
curl.exe -s -o NUL -w "%{http_code} /composer.json`n" http://localhost:8088/composer.json
curl.exe -s -o NUL -w "%{http_code} /tests/`n" http://localhost:8088/tests/
curl.exe -s -o NUL -w "%{http_code} /database/`n" http://localhost:8088/database/
curl.exe -s -o NUL -w "%{http_code} /Zooki/.env`n" http://localhost:8088/Zooki/.env
curl.exe -s -o NUL -w "%{http_code} /docs/`n" http://localhost:8088/docs/
curl.exe -s -o NUL -w "%{http_code} /Zooki/public/docs/`n" http://localhost:8088/Zooki/public/docs/
docker stop zooki-f0
```

**Debe salir:** en el `ls`, **ni** `.env`, `tests`, `scratch`, `specs`, `agentes` ni `logs`. Las cinco primeras URL dan `403` o `404`. `/docs/` y `/Zooki/public/docs/` dan `200` (el portal no necesita la base). La raíz `/` sin base puede dar error de conexión: es normal sin MySQL.

### 0.3 Proxy: que llegue la IP real (VPS)

La cadena es **nginx del host → Traefik (puerto 9080, red `dokploy-network`) → Apache**. Sin estos tres ajustes, todos los usuarios se ven con la misma IP y 20 fallos de cualquiera bloquean a todos (RN-G15).

1. **nginx** debe pasar las cabeceras. Abre el bloque `server` de Zooki (suele estar en `/etc/nginx/sites-available/`):

   ```bash
   sudo grep -rn "9080" /etc/nginx/sites-enabled/
   ```

   Dentro del `location` que hace `proxy_pass` a `:9080` deben estar estas cuatro líneas (agrega las que falten):

   ```nginx
   proxy_set_header Host $host;
   proxy_set_header X-Real-IP $remote_addr;
   proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
   proxy_set_header X-Forwarded-Proto $scheme;
   ```

   ```bash
   sudo nginx -t
   sudo systemctl reload nginx
   ```

   **Debe salir:** `syntax is ok` y `test is successful`. El `proxy_pass` **no** se cambia: la imagen nueva sirve `public/` tanto en `/` como en `/Zooki/public/`.

2. **Rangos de red** que vas a necesitar:

   ```bash
   docker network inspect dokploy-network --format '{{range .IPAM.Config}}{{.Subnet}} {{end}}'
   docker network inspect docker_gwbridge --format '{{range .IPAM.Config}}{{.Gateway}} {{end}}'
   ```

   Anota los dos valores: el primero es el rango donde vive Traefik (p. ej. `10.0.1.0/24`); el segundo suele ser la dirección con la que nginx llega a Traefik (p. ej. `172.18.0.1`). Si `docker_gwbridge` no existe, sigue: el paso 10.4 lo confirma con un intento real.

3. **Traefik** debe confiar en nginx; si no, borra `X-Forwarded-For` y pone su propio `X-Forwarded-Proto`. En Dokploy: *Settings → Web Server → Traefik → editar `traefik.yml`* (o el archivo `/etc/dokploy/traefik/traefik.yml`). En el entrypoint `web` agrega:

   ```yaml
   entryPoints:
     web:
       address: :80
       forwardedHeaders:
         trustedIPs:
           - <gateway del paso 2, p. ej. 172.18.0.1/32>
   ```

   Guarda y reinicia Traefik desde Dokploy (o `docker restart dokploy-traefik`). **Debe salir:** el sitio v1.12.0 sigue abriendo.

4. **`TRUSTED_PROXIES`** se pone en el paso 3 (variables de Dokploy) con los dos valores: `<rango de dokploy-network>,<gateway>`.

---

## 1. Respaldo (VPS)

### 1.1 Ubicar el contenedor de MySQL y los volúmenes

```bash
docker ps --format '{{.Names}}\t{{.Image}}' | grep -i -E 'mysql|maria'
docker volume ls --format '{{.Name}}' | grep -E 'uploads|respaldos'
mkdir -p ~/respaldo-zooki-f1
```

**Debe salir:** el contenedor del MySQL central (`<mysql_central>`) y los volúmenes de Zooki, uno terminado en `uploads` (`<vol_uploads>`) y otro en `respaldos`.

### 1.2 Respaldo de la base con el propio script de Zooki

Usa las credenciales que ya tiene el contenedor: no hay que escribir ninguna.

```bash
docker exec zooki_web php scripts/backup.php
docker exec zooki_web sh -c 'ls -t /var/backups/zooki | head -1'
docker cp zooki_web:/var/backups/zooki/<archivo que salió arriba> ~/respaldo-zooki-f1/
```

**Debe salir:** una línea con el número de tablas y el tamaño, y un archivo `.sql.gz`.

### 1.3 Respaldo del volumen de archivos

```bash
docker run --rm -v <vol_uploads>:/datos:ro -v ~/respaldo-zooki-f1:/destino alpine tar czf /destino/uploads-v1.12.0.tar.gz -C /datos .
ls -lh ~/respaldo-zooki-f1
```

### 1.4 Comprobar que el respaldo se puede leer

```bash
cd ~/respaldo-zooki-f1
gunzip -t *.sql.gz
zcat *.sql.gz | grep -c "CREATE TABLE"
tar tzf uploads-v1.12.0.tar.gz | head
gunzip -c *.sql.gz > /tmp/respaldo-v1.sql
docker cp /tmp/respaldo-v1.sql <mysql_central>:/tmp/respaldo-v1.sql
docker exec -it <mysql_central> mysql -u root -p
```

Dentro de MySQL (pide la clave de root; no se ve al escribirla):

```sql
CREATE DATABASE zooki_restauracion_prueba CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE zooki_restauracion_prueba;
SOURCE /tmp/respaldo-v1.sql;
SELECT COUNT(*) AS tablas FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'zooki_restauracion_prueba';
SELECT COUNT(*) AS usuarios FROM usuarios;
DROP DATABASE zooki_restauracion_prueba;
EXIT;
```

**Debe salir:** `gunzip -t` sin mensajes; el mismo número de `CREATE TABLE` que de tablas restauradas; la lista del `tar`; y conteos mayores que cero. Si algo falla, **no sigas**: sin respaldo legible no hay vuelta atrás.

### 1.5 Copia en tu PC (PowerShell)

```powershell
New-Item -ItemType Directory -Force C:\Users\Usuario\respaldo-zooki-f1
scp "<vps>:~/respaldo-zooki-f1/*" C:\Users\Usuario\respaldo-zooki-f1\
Get-ChildItem C:\Users\Usuario\respaldo-zooki-f1
```

---

## 2. Integración a `main` y tag (PowerShell)

Si Dokploy despliega solo al cambiar `main`, **el merge dispara el despliegue**: por eso el respaldo va antes. Desde la rama `v2/m0`, con F0 ya en commits:

```powershell
git push origin v2/m0
git branch release/v2.0.0-alpha.1 v2/m0
git push -u origin release/v2.0.0-alpha.1
gh pr create --base main --head release/v2.0.0-alpha.1 --title "Zooki 2.0.0-alpha.1: modulo 0 (plataforma, identidad y aislamiento)" --body "Corte de M0. Guia: specs/M0-F-corte-produccion.md"
```

Revisa el PR en GitHub y haz el merge (con merge commit, no squash). Luego el tag anotado sobre el commit de merge, sin cambiar de rama:

```powershell
git fetch origin
git log origin/main -1 --format="%H %s"
git tag -a v2.0.0-alpha.1 <hash que salió arriba> -m "Zooki 2.0.0-alpha.1: modulo 0 (plataforma, identidad y aislamiento)"
git push origin v2.0.0-alpha.1
```

**Debe salir:** el último commit de `origin/main` es el merge del PR.

---

## 3. Variables de Dokploy (pestaña Environment del servicio)

Se revisan **por nombre**; los valores los pones tú en Dokploy y nunca se copian a un chat ni a un archivo versionado.

| Variable | Qué debe tener |
|---|---|
| `APP_URL` | la URL pública con `https://` (los enlaces de los correos salen de aquí) |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM` | los de Brevo (host `smtp-relay.sendinblue.com`) |
| `MAIL_MODO` | **no debe existir** (o comentada): en producción los correos se envían |
| `GOOGLE_CLIENT_ID` | el de producción; el dominio debe estar autorizado en Google Cloud |
| `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | las **reales** de Cloudflare para el dominio, no las de prueba que empiezan por `1x000…` |
| `TRUSTED_PROXIES` | `<rango de dokploy-network>,<gateway>` del paso 0.3 |
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | los del MySQL central (sin cambios) |
| `DB_ROOT_PASS` | solo la usa el servicio `db` del compose; puede seguir como está |
| `BACKUP_DIR`, `BACKUP_RETENCION_DIAS` | `BACKUP_DIR=/var/backups/zooki` (el volumen `respaldos`) |
| `ALGOLIA_APP_ID`, `ALGOLIA_SEARCH_KEY`, `ALGOLIA_ADMIN_KEY`, `ALGOLIA_INDEX_NAME` | opcionales; vacías, el portal usa su buscador local |

**Por qué basta con esa pestaña.** Dokploy escribe estas variables en un archivo `.env` junto al `docker-compose.yml`. Zooki lee ese **archivo** (no variables del sistema), y desde F0 el `.env` ya no entra en la imagen: el compose lo **monta** de solo lectura en `/var/www/html/Zooki/.env` (decisión del usuario, 2026-10-11). Además `env_file: .env` lo declara en el compose, así que Dokploy también lo inyecta como variables del contenedor. El paso 6.3 lo comprueba.

---

## 4. Despliegue (Dokploy)

Si el merge no lo disparó, pulsa **Deploy** en el servicio. En los **logs del despliegue/contenedor** `zooki_web`:

**Debe salir** (es lo esperado esta primera vez, porque la base todavía es la v1):

```
[migraciones] ERROR: La base tiene el esquema v1 (falta la tabla clinicas o la columna usuarios.id_usuario). La v2 no migra datos: ...
```

Apache arranca igual. Entre este paso y el 6 el sitio muestra errores: es la ventana del corte.

---

## 5. Reinicio de la base y de los archivos (VPS)

### 5.1 Esquema v2 desde la imagen nueva

```bash
mkdir -p /tmp/zooki-f1
docker cp zooki_web:/var/www/html/Zooki/database/01_schema.sql /tmp/zooki-f1/
docker cp zooki_web:/var/www/html/Zooki/database/02_semilla.sql /tmp/zooki-f1/
docker cp /tmp/zooki-f1/01_schema.sql <mysql_central>:/tmp/01_schema.sql
docker cp /tmp/zooki-f1/02_semilla.sql <mysql_central>:/tmp/02_semilla.sql
docker exec -it <mysql_central> mysql -u root -p
```

Dentro de MySQL (reemplaza `<DB_NAME>` y `<DB_USER>`; el respaldo del paso 1 ya está comprobado):

```sql
SHOW GRANTS FOR '<DB_USER>'@'%';
DROP DATABASE <DB_NAME>;
CREATE DATABASE <DB_NAME> CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE <DB_NAME>;
SOURCE /tmp/01_schema.sql;
SOURCE /tmp/02_semilla.sql;
SELECT COUNT(*) AS tablas FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE();
SELECT nombre, limite_personal, limite_mascotas, limite_citas_mes FROM planes;
SHOW GRANTS FOR '<DB_USER>'@'%';
EXIT;
```

**Debe salir:** `tablas` = **50**; planes `Gratuito` (2, 50, 100) y `Profesional` (NULL); y el `SHOW GRANTS` igual antes y después (los permisos sobre la base se conservan al recrearla). Si el usuario de Zooki no es `'%'`, usa el host que muestre `SELECT user, host FROM mysql.user WHERE user = '<DB_USER>';`.

### 5.2 Limpiar los archivos de prueba

Borra las fotos y adjuntos de prueba, **sin** borrar las carpetas (el `*` no toca archivos ocultos). El acceso directo a `clinicos/` lo bloquea la configuración de Apache de la imagen, no un `.htaccess` del volumen:

```bash
docker run --rm -v <vol_uploads>:/datos alpine sh -c 'rm -rf /datos/mascotas/* /datos/clinicos/*; ls -la /datos /datos/clinicos'
```

**Debe salir:** `mascotas` y `clinicos` vacías (las carpetas siguen).

---

## 6. Arranque sobre la base nueva y comprobaciones (VPS)

### 6.1 Migrador

```bash
docker restart zooki_web
docker logs --tail 20 zooki_web
```

**Debe salir:**

```
[migraciones] Aplicadas: 03_confirmacion_vinculo_propietario.sql, 04_invitacion_personal_y_sesiones.sql, 05_limites_plan_gratuito.sql
[migraciones] Aplicada 02_semilla.sql (solo agrega las filas que falten).
```

Un segundo `docker restart zooki_web` debe decir `La base está al día.`

### 6.2 Blindaje de Apache

```bash
for ruta in /.env /composer.json /tests/ /database/ /Zooki/.env /Zooki/composer.json /uploads/clinicos/; do curl -s -o /dev/null -w "%{http_code} $ruta\n" "https://<dominio>$ruta"; done
docker exec zooki_web ls -a /var/www/html/Zooki
```

**Debe salir:** `403` o `404` en todas; en el `ls`, **sin** `tests`, `scratch`, `specs` ni `agentes`. Sí aparecen `vendor` (lo instala composer al construir la imagen) y `.env` (montado, no copiado).

### 6.3 Variables y `.env` montado (solo nombres)

```bash
docker exec -u www-data zooki_web test -r /var/www/html/Zooki/.env && echo "el .env se puede leer"
docker exec zooki_web sh -c "grep -v '^#' /var/www/html/Zooki/.env | cut -d= -f1 | sort"
docker exec zooki_web sh -c 'printenv | cut -d= -f1 | sort' | grep -E 'APP_URL|SMTP_|TURNSTILE|GOOGLE|TRUSTED|DB_'
```

**Debe salir:** `el .env se puede leer`, y los nombres de la tabla del paso 3 en las dos listas, **sin** `MAIL_MODO`.

---

## 7. Super-administrador (VPS)

```bash
docker exec -it zooki_web php scripts/crear_superadmin.php
```

Pide correo, nombre y contraseña (la contraseña no se ve). **Debe salir:** el aviso de cuenta creada. Si el correo ya existe, se niega: no se eleva ninguna cuenta.

---

## 8. Clínica demo «Zooki» (navegador)

1. Abre `https://<dominio>/?action=registro_clinica`.
2. Clínica: **Zooki**; NIT de demostración **901234567**, DV **7** (dígito de verificación válido según la DIAN, comprobado con `ValidadorNit`); los demás datos, los de la demo.
3. Administrador: correo **zooki.vet@gmail.com**, con una contraseña nueva que cumpla la política; acepta la política de datos y supera el CAPTCHA.
4. En el buzón de `zooki.vet@gmail.com`, abre el correo de activación de la clínica y pulsa su botón de confirmación.

**Debe salir:** la clínica activa en el plan Gratuito y el panel del administrador al entrar. En el panel del super-administrador aparece «Zooki», activa.

---

## 9. Tareas programadas (Dokploy → servicio `web` → Schedules)

El contenedor no tiene cron: cada tarea es un *Schedule* sobre el servicio `web`, con estos comandos (sin redirigir a `logs/`: Dokploy guarda la salida de cada ejecución). La hora de los scripts ya es la de Colombia (`helpers/ZonaHoraria.php`); confirma en Dokploy la zona del programador.

| Tarea | Cron | Comando |
|---|---|---|
| Respaldo diario | `0 3 * * *` | `php /var/www/html/Zooki/scripts/backup.php` |
| Recordatorios | `0 7 * * *` | `php /var/www/html/Zooki/scripts/send_reminders.php` |
| Atenciones abiertas | `*/5 * * * *` | `php /var/www/html/Zooki/scripts/vigilar_atenciones.php` |
| Altas e invitaciones vencidas (**nueva**) | `0 * * * *` | `php /var/www/html/Zooki/scripts/limpiar_cuentas_pendientes.php` |

Ejecuta cada una una vez con **Run** y revisa su salida. **Debe salir:** sin errores; el respaldo informa tablas y tamaño.

---

## 10. Comprobaciones finales en el navegador

1. `https://<dominio>/` y `https://<dominio>/?action=login` abren; el portal `https://<dominio>/docs/` muestra **Zooki v2.0.0-alpha.1** y la entrada nueva del historial.
2. Entra como super-administrador: ves «Zooki» con su plan y su uso, sin enlaces a mascotas ni historias.
3. Entra con `zooki.vet@gmail.com`: llegas al panel de la clínica Zooki.
4. **IP real:** desde el celular con datos móviles, intenta entrar una vez con una contraseña equivocada. Luego en el VPS:

   ```bash
   docker exec -it <mysql_central> mysql -u root -p -e "SELECT ip_address, fecha_hora FROM <DB_NAME>.auditoria_sistema WHERE accion = 'LOGIN_FAIL' ORDER BY id_auditoria DESC LIMIT 1;"
   ```

   **Debe salir** la IP pública del celular (compárala con la que muestra `https://ifconfig.me` en ese mismo celular). Si sale una IP `172.x` o `10.x`: si es la del *gateway*, agrégala a `trustedIPs` de Traefik y a `TRUSTED_PROXIES`; si es la de Traefik, el `.env` no tiene `TRUSTED_PROXIES` (vuelve al 6.3). Reinicia `zooki_web` y repite.
5. Al registrarte, invitar o restablecer, el correo llega por Brevo y su botón abre `https://<dominio>/…` (no `localhost`).

---

## 11. Vuelta atrás (si algo sale mal)

El respaldo v1 **no carga** en el esquema v2: para volver se usa la imagen v1.12.0 con el respaldo en una base **vacía**.

1. **Código (PowerShell):** una rama desde el tag de la v1.12.0 y Dokploy apuntando a ella.

   ```powershell
   git branch vuelta-v1.12.0 v1.12.0
   git push origin vuelta-v1.12.0
   ```

   En Dokploy cambia la rama del servicio a `vuelta-v1.12.0` y pulsa **Deploy**. (Esa imagen vuelve a copiar el `.env` dentro y sirve desde la raíz de antes; el `proxy_pass` no cambió, así que funciona igual.)

2. **Base (VPS):** con el archivo comprobado en 1.4 (`/tmp/respaldo-v1.sql` dentro de `<mysql_central>`; si ya no está, repite el `gunzip` y el `docker cp` de 1.4).

   ```bash
   docker exec -it <mysql_central> mysql -u root -p
   ```

   ```sql
   DROP DATABASE <DB_NAME>;
   CREATE DATABASE <DB_NAME> CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
   USE <DB_NAME>;
   SOURCE /tmp/respaldo-v1.sql;
   EXIT;
   ```

3. **Archivos (VPS):**

   ```bash
   docker run --rm -v <vol_uploads>:/datos -v ~/respaldo-zooki-f1:/origen alpine sh -c 'rm -rf /datos/mascotas/* /datos/clinicos/*; tar xzf /origen/uploads-v1.12.0.tar.gz -C /datos'
   docker restart zooki_web
   ```

4. **Comprueba** que entras con una cuenta de prueba de la v1.12.0. En Dokploy, vuelve a dejar la rama en `main` solo cuando se corrija lo que falló (el `main` ya tiene la v2).

Traefik (`trustedIPs`) y nginx (cabeceras) pueden quedar como en el paso 0.3: también sirven a la v1.12.0.
