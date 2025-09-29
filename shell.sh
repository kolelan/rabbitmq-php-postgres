# Создаем директории для конфигурации
mkdir -p logging

# Сборка и запуск
docker-compose up -d --build

# Запуск консольного приложения
docker-compose run php-console php console.php

# Просмотр логов воркера
docker-compose logs -f php-worker

# Запуск нескольких воркеров
docker-compose up -d --scale php-worker=3

# Запуск консоли в интерактивном режиме
docker-compose run --rm php-console bash

# Просмотр очереди в RabbitMQ UI
# Откройте http://localhost:15672 (guest/guest)

# Проверка базы данных
docker-compose exec postgres psql -U user -d myapp -c "SELECT * FROM users;"