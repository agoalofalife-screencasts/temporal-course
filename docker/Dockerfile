FROM food-delivery-base

# Xdebug (only in app image — quick to install since grpc is already in base)
RUN pecl install xdebug \
 && docker-php-ext-enable xdebug \
 && { \
      echo "xdebug.mode=debug"; \
      echo "xdebug.start_with_request=trigger"; \
      echo "xdebug.client_host=host.docker.internal"; \
      echo "xdebug.client_port=9003"; \
      echo "xdebug.idekey=PHPSTORM"; \
      echo "xdebug.log=/tmp/xdebug.log"; \
      echo "xdebug.discover_client_host=0"; \
    } >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# RoadRunner binary
COPY --from=ghcr.io/roadrunner-server/roadrunner:2025.1.5 /usr/bin/rr /usr/local/bin/rr

WORKDIR /app

COPY . .

# Устанавливаем зависимости
RUN composer install --optimize-autoloader --prefer-dist

# Генерируем autoload
RUN composer dump-autoload --optimize
