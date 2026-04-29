FROM php:8.2-apache

# Instalamos extensiones de base de datos
RUN docker-php-ext-install pdo pdo_mysql

# Habilitamos los módulos de Apache necesarios (urls limpias y cabeceras de seguridad)
RUN a2enmod rewrite headers

# Copiamos los archivos del proyecto
COPY . /var/www/html/

# Aseguramos que existe la carpeta includes y enlazamos el secreto de Render
RUN mkdir -p /var/www/html/includes
RUN ln -sf /var/www/html/config.php /var/www/html/includes/config.php

# Permisos adecuados para que Apache pueda leer los archivos
RUN chown -R www-data:www-data /var/www/html/
RUN chmod -R 755 /var/www/html/

EXPOSE 80