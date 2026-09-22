#!/bin/sh
# Arranque del contenedor web (cada despliegue de Dokploy lo reconstruye).
#
# 1. Aplica las migraciones de database/ que falten (scripts/migrar.php).
# 2. Arranca Apache, igual que la imagen php:8.2-apache por defecto.
#
# Si una migración falla, Apache arranca igual para no tumbar el sitio: el
# error queda en los logs del contenedor en Dokploy («[migraciones] ERROR»).

php /var/www/html/Zooki/scripts/migrar.php \
  || echo "[migraciones] No se aplicaron todas las migraciones; revisa el error de arriba."

exec apache2-foreground
