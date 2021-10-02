FROM php:7.4-cli

# system dependecies
RUN apt-get update --allow-releaseinfo-change \
 && apt-get remove -y mariadb-server mariadb-client \
 && apt-get install -y \
 git \
 libssl-dev \
 default-mysql-client \
 libmcrypt-dev \
 libicu-dev \
 libpq-dev \
 libjpeg62-turbo-dev \
 libjpeg-dev  \
 libpng-dev \
 zlib1g-dev \
 libonig-dev \
 libxml2-dev \
 libzip-dev \
 unzip

# PHP dependencies
RUN docker-php-ext-install \
 gd \
 intl \
 mbstring \
 pdo \
 zip

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && sync \
 && install-php-extensions mcrypt tidy xsl pcntl sysvsem sockets \
 && pecl install xdebug \
 && docker-php-ext-enable xdebug

RUN mv $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/bin --filename=composer --quiet

RUN groupadd --gid 1000 app && \
    useradd --gid 1000 --uid 1000 --home-dir /app app
#RUN addgroup -gid 1000 app && adduser -u 1000 -G app -s /bin/sh -D app
#RUN adduser --system --group app --shell=/bin/sh
WORKDIR /app

USER app