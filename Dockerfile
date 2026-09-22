FROM php:8.2-apache

# Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# Instalar dependencias del sistema y extensiones de PHP necesarias
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar el directorio de trabajo
WORKDIR /var/www/html/Zooki

# Copiar los archivos de la aplicación
COPY . .

# Instalar dependencias de PHP (si existe el archivo composer.json)
RUN if [ -f "composer.json" ]; then composer install --no-dev --optimize-autoloader; fi

# Las carpetas de archivos subidos deben existir en la imagen: el volumen
# "uploads" hereda su dueño al crearse, y si no existen nace de root y Apache
# no puede escribir en él.
RUN mkdir -p public/uploads/mascotas public/uploads/clinicos

# Destino de los respaldos (HU-23): lo cubre el volumen "respaldos" de
# docker-compose.yml y se configura con BACKUP_DIR. Existe en la imagen por la
# misma razón que uploads: si no, el volumen nace de root.
RUN mkdir -p /var/backups/zooki && chown www-data:www-data /var/backups/zooki

# Configurar permisos para que el servidor web pueda leer/escribir
RUN chown -R www-data:www-data /var/www/html/Zooki

# Al arrancar, aplica las migraciones de database/ que falten y luego inicia
# Apache (docker/iniciar.sh). El sed quita los CRLF por si el archivo se editó
# en Windows: con ellos /bin/sh no puede ejecutarlo.
RUN sed -i 's/\r$//' docker/iniciar.sh && chmod +x docker/iniciar.sh
CMD ["/var/www/html/Zooki/docker/iniciar.sh"]
