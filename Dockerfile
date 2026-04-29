FROM php:8.2-apache

# 1. Instalamos las dependencias del sistema para procesar imágenes (QR y PDF)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip

# 2. Configuramos e instalamos la extensión gráfica GD y PDO para base de datos
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install gd pdo pdo_mysql

# 3. Activamos los módulos de Apache
RUN a2enmod rewrite headers

# 4. Copiamos los archivos de tu proyecto
COPY . /var/www/html/

# 5. Aseguramos las carpetas y el enlace del archivo secreto
RUN mkdir -p /var/www/html/includes
RUN ln -sf /var/www/html/config.php /var/www/html/includes/config.php

# 6. Damos permisos totales para que PHP pueda guardar los PDF y QR generados
RUN chown -R www-data:www-data /var/www/html/
RUN chmod -R 777 /var/www/html/

EXPOSE 80