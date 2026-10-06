#!/bin/sh
# Arranque del contenedor web (cada despliegue de Dokploy lo reconstruye).
#
# 1. Aplica las migraciones de database/ que falten (scripts/migrar.php).
# 2. Reindexa la documentación en Algolia si los .md cambiaron
#    (scripts/algolia_index.php --si-cambio; specs/buscador-documentacion.md, BD-1).
# 3. Arranca Apache, igual que la imagen php:8.2-apache por defecto.
#
# Si una migración o el indexado fallan, Apache arranca igual para no tumbar
# el sitio: el error queda en los logs del contenedor en Dokploy.

php /var/www/html/Zooki/scripts/migrar.php \
  || echo "[migraciones] No se aplicaron todas las migraciones; revisa el error de arriba."

php /var/www/html/Zooki/scripts/algolia_index.php --si-cambio \
  || echo "[algolia] No se pudo reindexar la documentación; el portal usa el buscador local."

exec apache2-foreground
