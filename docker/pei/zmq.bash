PHP_MODULE_PATH=php-$(echo "<?= PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION ?>"|php7)

apk add libzmq \
    && curl -sLo /usr/lib/php7/modules/zmq.so "http://files.docker.genee.in/alpine/${PHP_MODULE_PATH}/zmq.so" \
    && printf "extension=zmq.so\n" > /etc/php7/conf.d/10_zmq.ini