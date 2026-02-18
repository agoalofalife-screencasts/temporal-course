FROM food-delivery-base

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
