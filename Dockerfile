FROM php:8.2-apache

# Instalamos dependencias gráficas y utilidades
RUN apt-get update && apt-get install -y libpng-dev libjpeg-dev libfreetype6-dev zip unzip

# Extensiones GD y Base de datos
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install gd pdo pdo_mysql

# Módulos de Apache
RUN a2enmod rewrite headers

# --- Aumentamos el límite de subida de fotos a 10MB ---
RUN echo "upload_max_filesize = 10M\npost_max_size = 10M" > /usr/local/etc/php/conf.d/uploads.ini

# Copiamos el código
COPY . /var/www/html/

# --- Forzamos la creación de las carpetas de fotos por si Git no las subió ---
RUN mkdir -p /var/www/html/includes
RUN mkdir -p /var/www/html/uploads/obras

# Enlazamos el archivo config de Render
RUN ln -sf /var/www/html/config.php /var/www/html/includes/config.php

# Permisos totales para que PHP pueda escribir las fotos
RUN chown -R www-data:www-data /var/www/html/
RUN chmod -R 777 /var/www/html/

EXPOSE 80