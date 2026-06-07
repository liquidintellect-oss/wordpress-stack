FROM wordpress:php8.4-fpm-alpine

ARG WP_MAX_UPLOAD="64M"
ARG WP_MAX_FILESIZE="64M"

# Build dependencies for phpredis, then compile and install the extension.
# --no-cache and the explicit apk del keep the image layer small.
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

RUN echo "post_max_size=${WP_MAX_UPLOAD}" > /usr/local/etc/php/conf.d/wp-upload-limits.ini \
    && echo "upload_max_filesize=${WP_MAX_FILESIZE}" >> /usr/local/etc/php/conf.d/wp-upload-limits.ini

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
