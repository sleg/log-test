FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y git unzip libssl-dev librabbitmq-dev \
    && rm -rf /var/lib/apt/lists/* \
    && pecl install amqp \
    && docker-php-ext-enable amqp

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /log-app
COPY composer.json composer.lock ./
RUN composer install --optimize-autoloader --no-interaction
COPY . .

EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]