PHP_MODULE_PATH=php-$(echo "<?= PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION ?>"|php7)

curl -sLo /usr/lib/libfriso.so "http://files.docker.genee.in/alpine/${PHP_MODULE_PATH}/libfriso.so" \
    && curl -sLo /usr/lib/php7/modules/friso.so "http://files.docker.genee.in/alpine/${PHP_MODULE_PATH}/friso.so" \
    && curl -sL http://files.docker.genee.in/friso-etc.tgz | tar -zxf - -C /etc \
    && printf "extension=friso.so\n\n[friso]\nfriso.ini_file=/etc/friso/friso.ini\n" > /etc/php7/conf.d/20_friso.ini
