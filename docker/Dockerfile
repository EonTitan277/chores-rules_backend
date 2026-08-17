FROM php:8.5-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends cron \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-enable pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers

ENV APACHE_DOCUMENT_ROOT=/var/www/public

RUN sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" \
        /etc/apache2/sites-available/000-default.conf \
    && sed -ri -e "s!/var/www!${APACHE_DOCUMENT_ROOT%/*}!g" \
        /etc/apache2/apache2.conf

RUN printf '<Directory "%s">\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n' "${APACHE_DOCUMENT_ROOT}" \
    > /etc/apache2/conf-available/document-root.conf \
    && a2enconf document-root

RUN mkdir -p /var/www/data /var/www/logs \
    && chown -R www-data:www-data /var/www/data /var/www/logs

RUN printf 'SHELL=/bin/sh\nPATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin\n\n0 0 * * * www-data /usr/local/bin/php /var/www/includes/reset-chores-checkboxes.php >> /var/www/logs/chores-reset.log 2>&1\n0 3 * * * www-data /usr/local/bin/php /var/www/includes/cleanup-login-attempts.php >> /var/www/logs/cleanup.log 2>&1\n' \
    > /etc/cron.d/cleanup-login-attempts \
    && chmod 0644 /etc/cron.d/cleanup-login-attempts

WORKDIR /var/www

COPY includes/ ./includes/

COPY public/  ./public/
COPY api/     ./api/

COPY data/    ./data/
COPY logs/    ./logs/

RUN chown -R www-data:www-data /var/www/data /var/www/logs


EXPOSE 80
