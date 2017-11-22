PHP_MODULE_PATH=php-$(echo "<?= PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION ?>"|php7)

curl -sLo /usr/lib/php7/modules/redis.so "http://files.docker.genee.in/alpine/${PHP_MODULE_PATH}/redis.so" \
    && printf "extension=redis.so\n" > /etc/php7/conf.d/20_redis.ini
