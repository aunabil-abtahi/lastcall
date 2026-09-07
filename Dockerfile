FROM php:8.2-apache

# Install system dependencies & required PHP extensions
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    default-mysql-client \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql curl \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Configure Apache DocumentRoot and .htaccess overrides
RUN sed -ri -e 's!/var/www/html!/var/www/html!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!/var/www/html!g' /etc/apache2/apache2.conf \
    && echo "<Directory /var/www/html>\n\
    Options -Indexes +FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>" >> /etc/apache2/conf-available/override.conf \
    && a2enconf override

# Set working directory
WORKDIR /var/www/html

# Copy application source code
COPY . /var/www/html/

# Copy environment example if .env does not exist
RUN if [ ! -f /var/www/html/.env ]; then cp /var/www/html/.env.example /var/www/html/.env; fi

# Set appropriate permissions for Apache
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose standard HTTP port
EXPOSE 80

# Use default Apache foreground process
CMD ["apache2-foreground"]
