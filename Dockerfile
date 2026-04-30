FROM php:7.4-apache

RUN apt-get update && apt-get install -y \
  libpng-dev \
  libjpeg-dev \
  libfreetype6-dev \
  libzip-dev \
  zip \
  && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg

RUN docker-php-ext-install mysqli pdo pdo_mysql gd zip

RUN a2enmod rewrite

# Composer
RUN curl -sS https://getcomposer.org/installer | php \
  && mv composer.phar /usr/local/bin/composer

ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Configurar logs de PHP a stdout para Docker
RUN echo "error_log = /dev/stdout" >> /usr/local/etc/php/conf.d/docker-php-ext-logging.ini
RUN echo "log_errors = On" >> /usr/local/etc/php/conf.d/docker-php-ext-logging.ini

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

COPY . .

RUN composer dump-autoload

RUN chown -R www-data:www-data /var/www/html