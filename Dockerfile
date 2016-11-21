FROM alpine:3.3
MAINTAINER iamfat@gmail.com

ENV TERM="xterm-color" \
    MAIL_HOST="172.17.0.1" \
    MAIL_FROM="sender@gini" \
    GINI_ENV="production" \
    COMPOSER_PROCESS_TIMEOUT=40000 \
    COMPOSER_HOME="/usr/local/share/composer"

# Install msmtp-mta
RUN apk add --no-cache msmtp && ln -sf /usr/bin/msmtp /usr/sbin/sendmail
ADD docker/msmtprc /etc/msmtprc

# Install PHP FPM
RUN apk add --no-cache php-fpm && \
    sed -i 's/^listen\s*=.*$/listen = 0.0.0.0:9000/' /etc/php/php-fpm.conf && \
    sed -i 's/^error_log\s*=.*$/error_log = syslog/' /etc/php/php-fpm.conf && \
    sed -i 's/^\;error_log\s*=\s*syslog\s*$/error_log = syslog/' /etc/php/php.ini

# Install Other Extensions
RUN apk add --no-cache php-intl php-gd php-mcrypt php-pdo \
    php-pdo_mysql php-pdo_sqlite php-curl php-ldap php-gettext php-posix php-pcntl \
    php-bcmath php-dom php-ctype php-iconv php-zip

# Install curl
RUN apk add --no-cache curl

RUN apk add --no-cache yaml \
    && curl -sLo /usr/lib/php/modules/yaml.so http://files.docker.genee.in/php5/yaml.so \
    && echo "extension=yaml.so" > /etc/php/conf.d/yaml.ini

# Install Friso
RUN curl -sLo /usr/lib/libfriso.so http://files.docker.genee.in/php5/libfriso.so \
    && curl -sLo /usr/lib/php/modules/friso.so http://files.docker.genee.in/php5/friso.so \
    && curl -sL http://files.docker.genee.in/friso-etc.tgz | tar -zxf - -C /etc \
    && printf "extension=friso.so\n[friso]\nfriso.ini_file=/etc/friso/friso.ini\n" > /etc/php/conf.d/friso.ini

# Install Redis
RUN curl -sLo /usr/lib/php/modules/redis.so http://files.docker.genee.in/php5/redis.so \
    && printf "extension=redis.so\n" > /etc/php/conf.d/redis.ini

# Install ZeroMQ
RUN apk add --no-cache libzmq \
    && curl -sLo /usr/lib/php/modules/zmq.so http://files.docker.genee.in/php5/zmq.so \
    && printf "extension=zmq.so\n" > /etc/php/conf.d/zmq.ini

# Install NodeJS
RUN apk add --no-cache nodejs && npm install -g less less-plugin-clean-css uglify-js

# Install Development Tools
RUN apk add --no-cache git gettext

# Install Composer
RUN apk add --no-cache php-cli php-json php-phar php-openssl \
    && mkdir -p /usr/local/bin && (curl -sL https://getcomposer.org/installer | php) \
    && mv composer.phar /usr/local/bin/composer \
    && echo 'PATH="/usr/local/share/composer/vendor/bin:$PATH"' >> /etc/profile.d/composer.sh

# Install Gini
ADD . /opt/gini
RUN echo 'PATH="/opt/gini/bin:$PATH"' >> /etc/profile.d/gini.sh

EXPOSE 9000

VOLUME ["/opt/gini", "/etc/php"]

ADD docker/start /start
CMD ["/start"]

