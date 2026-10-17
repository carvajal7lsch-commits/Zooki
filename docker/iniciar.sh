#!/bin/sh
# Arranque del contenedor web (cada despliegue de Dokploy lo reconstruye).
#
# 1. Aplica las migraciones de database/ que falten y la semilla
#    (scripts/migrar.php). Se niega si la base todavía tiene el esquema v1.
# 2. Carga el conocimiento global únicamente si las migraciones terminaron bien.
# 3. Reindexa la documentación en Algolia si los .md cambiaron
#    (scripts/algolia_index.php --si-cambio; specs/buscador-documentacion.md, BD-1).
# 4. Arranca Apache, igual que la imagen php:8.2-apache por defecto.
#
# Si una migración o el indexado fallan, Apache arranca igual para no tumbar
# el sitio: el error queda en los logs del contenedor en Dokploy.

if php /var/www/html/Zooki/scripts/migrar.php; then
  php /var/www/html/Zooki/scripts/cargar_conocimiento.php \
    || echo "[conocimiento] No se pudo cargar el conjunto; se conserva lo anterior."
else
  echo "[conocimiento] Se omite la carga porque la migración falló."
fi

php /var/www/html/Zooki/scripts/algolia_index.php --si-cambio \
  || echo "[algolia] No se pudo reindexar la documentación; el portal usa el buscador local."

exec apache2-foreground
