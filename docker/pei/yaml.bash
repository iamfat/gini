PHP_MODULE_PATH=php-$(echo "<?= PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION ?>"|php7)

apk add yaml \
    && curl -sLo /usr/lib/php7/modules/yaml.so "http://files.docker.genee.in/alpine/${PHP_MODULE_PATH}/yaml.so" \
    && printf "extension=yaml.so\n" > /etc/php7/conf.d/10_yaml.ini
