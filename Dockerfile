# 1. Usamos una imagen oficial de PHP con Apache
FROM php:8.2-apache

# 2. Instalamos las extensiones de PHP necesarias para conectar con MariaDB/MySQL
RUN docker-php-ext-install pdo pdo_mysql

# 3. Habilitamos el módulo de reescritura de Apache 
RUN a2enmod rewrite

# 4. Copiamos todo el contenido de la carpeta actual al servidor de Render
COPY . /var/www/html/



# 5. Ajustamos los permisos para que Apache pueda leer los archivos
# y escribir en la carpeta de uploads
RUN chown -R www-data:www-data /var/www/html/

# Creamos un "acceso directo" para que cuando PHP busque 
# 'includes/config.php', el servidor le dé el 'config.php' de la raíz
RUN ln -s /var/www/html/config.php /var/www/html/includes/config.php

EXPOSE 80
