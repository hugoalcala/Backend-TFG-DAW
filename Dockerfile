FROM php:8.4-apache

# Instalar extensiones de PHP necesarias
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_mysql zip \
    && apt-get clean

# Instalar Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configurar Apache
RUN a2dismod mpm_event || true && a2enmod mpm_prefork
RUN a2enmod rewrite
RUN a2enmod headers

# Configurar VirtualHost
RUN echo "<VirtualHost *:8080>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>" > /etc/apache2/sites-available/000-default.conf

# Cambiar puerto de Apache a 8080
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Aumentar límites de subida de PHP
RUN echo "upload_max_filesize=10M\npost_max_size=10M" > /usr/local/etc/php/conf.d/uploads.ini

# Copiar proyecto
COPY . /var/www/html

# Copiar entrypoint script
COPY entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh

# Instalar dependencias
WORKDIR /var/www/html
RUN composer update --no-dev --optimize-autoloader

# Permisos
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Comando para ejecutar entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
