FROM php:8.2-fpm

WORKDIR /app

ARG APP_ENV=prod
ARG DATABASE_URL=postgresql://database_user:database_password@0.0.0.0:5432/database_name?serverVersion=12&charset=utf8
ARG MESSENGER_TRANSPORT_DSN=doctrine://default
ARG COMPILER_SOURCE_DIRECTORY=/app/source
ARG COMPILER_TARGET_DIRECTORY=/app/tests
ARG COMPILER_HOST=compiler
ARG COMPILER_PORT=8000
ARG DELEGATOR_HOST=delegator
ARG DELEGATOR_PORT=8000
ARG JOB_TIMEOUT_CHECK_PERIOD_MS=30000
ARG EVENT_DELIVERY_RETRY_LIMIT=3
ARG JOB_COMPLETED_CHECK_PERIOD_MS=1000

ENV APP_ENV=$APP_ENV
ENV DATABASE_URL=$DATABASE_URL
ENV MESSENGER_TRANSPORT_DSN=$MESSENGER_TRANSPORT_DSN
ENV COMPILER_SOURCE_DIRECTORY=$COMPILER_SOURCE_DIRECTORY
ENV COMPILER_TARGET_DIRECTORY=$COMPILER_TARGET_DIRECTORY
ENV COMPILER_HOST=$COMPILER_HOST
ENV COMPILER_PORT=$COMPILER_PORT
ENV DELEGATOR_HOST=$DELEGATOR_HOST
ENV DELEGATOR_PORT=$DELEGATOR_PORT
ENV JOB_TIMEOUT_CHECK_PERIOD_MS=$JOB_TIMEOUT_CHECK_PERIOD_MS
ENV EVENT_DELIVERY_RETRY_LIMIT=$EVENT_DELIVERY_RETRY_LIMIT
ENV JOB_COMPLETED_CHECK_PERIOD_MS=$JOB_COMPLETED_CHECK_PERIOD_MS

ENV DOCKERIZE_VERSION="v2.1.0"

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apt-get -qq update && apt-get -qq -y install  \
  libpq-dev \
  libzip-dev \
  supervisor \
  zip \
  && docker-php-ext-install \
  pdo_pgsql \
  zip \
  && apt-get autoremove -y \
  && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN curl -L --output dockerize.tar.gz \
     https://github.com/presslabs/dockerize/releases/download/$DOCKERIZE_VERSION/dockerize-linux-amd64-$DOCKERIZE_VERSION.tar.gz \
  && tar -C /usr/local/bin -xzvf dockerize.tar.gz \
  && rm dockerize.tar.gz \
  && mkdir -p var/log/supervisor

COPY tests/build/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY tests/build/supervisor/conf.d/app.conf /etc/supervisor/conf.d/supervisord.conf

COPY composer.json symfony.lock /app/
COPY bin/console /app/bin/console
COPY public/index.php public/
COPY src /app/src
COPY config/bundles.php config/services.yaml /app/config/
COPY config/packages/*.yaml /app/config/packages/
COPY config/packages/prod /app/config/packages/prod
COPY config/routes/annotations.yaml /app/config/routes/

RUN composer install --no-dev --no-scripts \
  && rm composer.lock \
  && rm symfony.lock \
  && touch /app/.env \
  && php bin/console cache:clear --env=prod \
  && chown -R www-data:www-data /app/var/log

CMD dockerize -wait tcp://postgres:5432 -timeout 30s supervisord -c /etc/supervisor/supervisord.conf
