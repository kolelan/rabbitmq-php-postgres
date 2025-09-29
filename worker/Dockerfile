# worker/Dockerfile и console/Dockerfile
FROM php:8.2-cli

# Устанавливаем необходимые расширения и инструменты
RUN apt-get update && apt-get install -y \
    libpq-dev \
    librabbitmq-dev \
    libzip-dev \
    git \
    unzip \
    zip \
    && docker-php-ext-install pdo pdo_pgsql zip sockets \
    && pecl install amqp \
    && docker-php-ext-enable amqp

# Устанавливаем Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Создаем директорию для логов
RUN mkdir -p /app/logs

# Копируем зависимости
COPY composer.json .
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Копируем исходный код
COPY . .

# Регенерируем автозагрузчик после копирования shared директории
RUN composer dump-autoload --optimize

CMD ["php", "worker.php"]