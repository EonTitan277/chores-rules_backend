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

RUN printf 'SHELL=/bin/sh\nPATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin\n\n\
0 0 * * * www-data /usr/local/bin/php /var/www/includes/reset-chores-checkboxes.php >> /var/www/logs/chores-reset.log 2>&1\n\
1 0 * * * www-data /usr/local/bin/php /var/www/includes/cleanup-login-attempts.php >> /var/www/logs/cleanup.log 2>&1\n' \
    > /etc/cron.d/chores-rules \
    && chmod 0644 /etc/cron.d/chores-rules

RUN printf '#!/bin/sh\n\
printenv | grep -E "^(LOGS_PATH|DATA_PATH|MYSQL_HOST|MYSQL_DB|MYSQL_USER|MYSQL_PASSWORD|TIME_ZONE|TZ)=" > /etc/environment\n\
chown root:root /etc/environment && chmod 0600 /etc/environment\n\
# Re-assert www-data ownership on every start (the Dockerfile chown does not apply to mounts).\n\
chown -R www-data:www-data /var/www/data /var/www/logs\n\
exec docker-php-entrypoint "$@"\n' \
    > /usr/local/bin/start.sh \
    && chmod 0755 /usr/local/bin/start.sh

ENTRYPOINT ["/usr/local/bin/start.sh"]

WORKDIR /var/www

COPY includes/ ./includes/

COPY public/  ./public/
COPY api/     ./api/

COPY data/    ./data/
COPY logs/    ./logs/

# Normalize permissions for PHP/Apache-readable application code. Host ACLs
# and source file modes can otherwise leave copied files or parent directories
# inaccessible to the www-data process on other Unix hosts.
RUN find /var/www/includes /var/www/public /var/www/api -type d -exec chmod 0755 {} + \
    && find /var/www/includes /var/www/public /var/www/api -type f -exec chmod 0644 {} +

RUN chown -R www-data:www-data /var/www/data /var/www/logs


EXPOSE 80
