FROM php:8.1-apache

# Instalar extensiones PHP necesarias en una sola capa
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Habilitar mod_rewrite para Apache
RUN a2enmod rewrite

# Configurar Apache para permitir .htaccess con AllowOverride All
RUN sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

# Copiar archivos del proyecto al directorio web
COPY . /var/www/html/

# Configurar permisos en una sola capa (reduce tamaño de imagen)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Exponer puerto 80
EXPOSE 80

# Comando por defecto
CMD ["apache2-foreground"]
