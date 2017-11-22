FROM alpine:3.6
MAINTAINER iamfat@gmail.com

ENV TERM="xterm-color" \
    MAIL_HOST="172.17.0.1" \
    MAIL_FROM="sender@gini" \
    GINI_ENV="production" \
    COMPOSER_PROCESS_TIMEOUT=40000 \
    COMPOSER_HOME="/usr/local/share/composer"

ADD . /usr/local/share/gini
ADD docker/msmtprc /etc/msmtprc
ADD docker/gini.sh /etc/profile.d/gini.sh
ADD docker/start /start
ADD docker/pei /usr/local/share/pei
ADD docker/pei.bash /usr/local/bin/pei
ADD docker/start /start

RUN apk update \
    && apk add bash curl gettext php7 php7-fpm \
      && sed -i 's/^listen\s*=.*$/listen = 0.0.0.0:9000/' /etc/php7/php-fpm.conf \
      && sed -i 's/^\;error_log\s*=.*$/error_log = \/dev\/stderr/' /etc/php7/php-fpm.conf \
      && sed -i 's/^\;error_log\s*=\s*syslog\s*$/error_log = \/dev\/stderr/' /etc/php7/php.ini \
      && ln -sf /usr/sbin/php-fpm7 /usr/sbin/php-fpm \
      && ln -sf /usr/bin/php7 /usr/bin/php \
    && pei session intl gd mcrypt pdo pdo_mysql pdo_sqlite curl \
      json phar openssl bcmath dom ctype iconv zip xml zlib mbstring \
      ldap gettext posix pcntl simplexml tokenizer xmlwriter fileinfo yaml \
      zmq redis friso \
    && apk add nodejs nodejs-npm && npm install -g less less-plugin-clean-css uglify-js \
    && apk add msmtp && ln -sf /usr/bin/msmtp /usr/sbin/sendmail \
    && apk add git \
    && mkdir -p /usr/local/bin && (curl -sL https://getcomposer.org/installer | php) \
      && mv composer.phar /usr/local/bin/composer \
    && mkdir -p /data/gini-modules \
    && cd /usr/local/share/gini \
      && bin/gini composer init -f \
      && /usr/local/bin/composer install --no-dev \
      && bin/gini cache \
    && rm -rf /var/cache/apk/*

EXPOSE 9000

ENV PATH="/data/gini-modules/gini/bin:/usr/local/share/composer/vendor/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin" \
GINI_MODULE_BASE_PATH="/data/gini-modules"

WORKDIR /data/gini-modules
CMD ["/start"]
