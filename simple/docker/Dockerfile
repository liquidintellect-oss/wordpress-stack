FROM wordpress:php8.4-apache

ARG PHP_MAX_UPLOAD="20M"
ARG PHP_MAX_FILESIZE="20M"

RUN echo "post_max_size=${PHP_MAX_UPLOAD}" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "upload_max_filesize=${PHP_MAX_FILESIZE}" >> /usr/local/etc/php/conf.d/uploads.ini

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]