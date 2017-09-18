FROM alpine:3.6
MAINTAINER iamfat@gmail.com

ENV TERM="xterm-color" \
    MAIL_HOST="172.17.0.1" \
    MAIL_FROM="sender@gini" \
    GINI_ENV="production" \
    COMPOSER_PROCESS_TIMEOUT=40000 \
    COMPOSER_HOME="/usr/local/share/composer"

ADD . /data/gini-modules/gini
ADD docker/msmtprc /etc/msmtprc
ADD docker/gini.sh /etc/profile.d/gini.sh
ADD docker/start /start


RUN apk add --no-cache bash curl gettext \
    && apk add --no-cache php7 php7-fpm \
      && sed -i 's/^listen\s*=.*$/listen = 0.0.0.0:9000/' /etc/php7/php-fpm.conf \
      && sed -i 's/^error_log\s*=.*$/error_log = syslog/' /etc/php7/php-fpm.conf \
      && sed -i 's/^\;error_log\s*=\s*syslog\s*$/error_log = syslog/' /etc/php7/php.ini \
      && ln -sf /usr/sbin/php-fpm7 /usr/sbin/php-fpm \
      && ln -sf /usr/bin/php7 /usr/bin/php \
    && apk add --no-cache php7-session php7-intl php7-gd \
      php7-mcrypt php7-pdo php7-pdo_mysql php7-pdo_sqlite php7-curl \
      php7-json php7-phar php7-openssl php7-bcmath php7-dom php7-ctype \
      php7-iconv php7-zip php7-xml php7-zlib php7-mbstring \
      php7-ldap php7-gettext php7-posix php7-pcntl php7-simplexml php7-tokenizer php7-xmlwriter \
    && export PHP_EXTENSION_PATH=php-$(echo '<?= PHP_VERSION_ID ?>'|php7) \
    && apk add --no-cache yaml \
      && curl -sLo /usr/lib/php7/modules/yaml.so "http://files.docker.genee.in/${PHP_EXTENSION_PATH}/yaml.so" \
      && printf "extension=yaml.so\n" > /etc/php7/conf.d/00_yaml.ini \
    && curl -sLo /usr/lib/php7/modules/redis.so "http://files.docker.genee.in/${PHP_EXTENSION_PATH}/redis.so" \
      && printf "extension=redis.so\n" > /etc/php7/conf.d/00_redis.ini \
    && apk add --no-cache libzmq \
      && curl -sLo /usr/lib/php7/modules/zmq.so "http://files.docker.genee.in/${PHP_EXTENSION_PATH}/zmq.so" \
      && printf "extension=zmq.so\n" > /etc/php7/conf.d/00_zmq.ini \
    && curl -sLo /usr/lib/libfriso.so "http://files.docker.genee.in/${PHP_EXTENSION_PATH}/libfriso.so" \
      && curl -sLo /usr/lib/php7/modules/friso.so "http://files.docker.genee.in/${PHP_EXTENSION_PATH}/friso.so" \
      && curl -sL http://files.docker.genee.in/friso-etc.tgz | tar -zxf - -C /etc \
      && printf "extension=friso.so\n\n[friso]\nfriso.ini_file=/etc/friso/friso.ini\n" > /etc/php7/conf.d/00_friso.ini \
    && apk add --no-cache nodejs nodejs-npm && npm install -g less less-plugin-clean-css uglify-js \
    && apk add --no-cache msmtp && ln -sf /usr/bin/msmtp /usr/sbin/sendmail \
    && apk add --no-cache git \
    && mkdir -p /usr/local/bin && (curl -sL https://getcomposer.org/installer | php) \
      && mv composer.phar /usr/local/bin/composer \
    && cd /data/gini-modules/gini \
      && bin/gini composer init -f \
      && /usr/local/bin/composer update -no-dev \
      && bin/gini cache

EXPOSE 9000

WORKDIR /data/gini-modules

ENTRYPOINT ["/start"]