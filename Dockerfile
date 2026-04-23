FROM php:8.4-apache

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite

# Apache -> DocumentRoot parecido com Laravel (public/)
ENV APACHE_DOCUMENT_ROOT=/var/www/html/src/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

WORKDIR /var/www/html
