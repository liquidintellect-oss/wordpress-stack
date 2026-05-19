FROM wordpress:php8.4-fpm-alpine

ARG WP_MAX_UPLOAD="64M"
ARG WP_MAX_FILESIZE="64M"

RUN echo "post_max_size=${WP_MAX_UPLOAD}" > /usr/local/etc/php/conf.d/wp-upload-limits.ini \
    && echo "upload_max_filesize=${WP_MAX_FILESIZE}" >> /usr/local/etc/php/conf.d/wp-upload-limits.ini

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
