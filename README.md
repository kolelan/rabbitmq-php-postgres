# RabbitMQ PHP PostgreSQL Project

Проект архитектуры микросервисов с использованием PHP, RabbitMQ и PostgreSQL с полным ведением журнала и мониторингом.

## Архитектура

- **PostgreSQL**: База данных для хранения журналов пользователей и сообщений.
- **RabbitMQ**: Очередь сообщений для асинхронной обработки
- **PHP Worker**: Фоновый рабочий процесс, который обрабатывает сообщения из очереди
- **PHP Console**: Интерактивное консольное приложение для отправки сообщений
- **PHP API**: сервер REST API для отправки сообщений на основе HTTP
- **Grafana + Loki**: Стек ведения журнала и мониторинга

## Сервисы

### База данных (PostgreSQL)
- Порт: 5432
- Хранит пользовательские данные и журналы обработки сообщений
- Автоматическая инициализация схемой из `database/init.sql`

### Очередь сообщений (RabbitMQ)
- Порт AMQP: 5672
- Интерфейс управления: http://localhost:15672
- Учетные данные по умолчанию: guest/guest

### PHP Worker
- Обрабатывает сообщения из `data_queue`
- Автоматически повторяет попытки при сбоях подключения
- Регистрирует все операции обработки

### PHP Console
- Интерактивный интерфейс командной строки
- Позволяет отправлять сообщения вручную
- Введите "quit", чтобы завершить работу

### PHP API
- Сервер REST API на порту 8080
- Конечные точки:
  - `POST /users` - Создать нового пользователя (отправляет сообщение в очередь)
  - `GET /health` - Конечная точка проверки работоспособности

### Стек мониторинга
- **Loki**: Агрегация журналов (порт 3100)
- **Promtail**: Агент сбора логов
- **Grafana**: Визуализация логов (порт 3000, admin/admin)

### Администрирование базы данных
- **pgAdmin**: Веб-интерфейс для управления PostgreSQL (порт 5050, admin@admin.com/admin)

## Настройка

1. **Скопируйте переменные среды:**
   ```bash
   cp env.example .env
   ```

2. **Запустите все службы:**
   ```bash
   docker-compose up -d
   ```

3. **Доступ к сервисам:**
   - API: http://localhost:8080
   - Управление RabbitMQ: http://localhost:15672
   - Grafana: http://localhost:3000
   - pgAdmin: http://localhost:5050

## Usage

### Используя API
```bash
# Create a user
curl -X POST http://localhost:8080/users \
  -H "Content-Type: application/json" \
  -d '{"name": "John Doe"}'

# Проверяем работоспособность
curl http://localhost:8080/health
```

### Using the Console
```bash
# Доступ к консольному контейнеру
docker-compose exec php-console php console.php

# Введите имена пользователей при появлении запроса
# Введите "quit" для выхода
```

### Журналы мониторинга
- Доступ к Grafana по адресу http://localhost:3000
- Используйте источник данных Loki для запроса логов
- Примеры запросов:
  - `{job="php-worker"}` - Логи рабочих
  - `{job="php-api"}" - логи API
  - `{задание="php-консоль"}` - Логи консоли

### Используя pgAdmin
1. Откройте http://localhost:5050 в браузере
2. Войдите с учетными данными: admin@admin.com / admin
3. Добавьте новый сервер:
   - **Name**: PostgreSQL Server
   - **Host**: postgres
   - **Port**: 5432
   - **Username**: user (или значение из .env)
   - **Password**: password (или значение из .env)
4. Просматривайте и управляйте базой данных через веб-интерфейс

## Структура проекта

```
├── api/                    # PHP API server
│   ├── Dockerfile
│   ├── composer.json
│   └── index.php
├── console/                # PHP console application
│   ├── Dockerfile
│   ├── composer.json
│   └── console.php
├── worker/                 # PHP background worker
│   ├── Dockerfile
│   ├── composer.json
│   └── worker.php
├── shared/                 # Shared PHP classes
│   └── src/
│       ├── Database/
│       ├── Logging/
│       └── Messaging/
├── database/               # Database initialization
│   └── init.sql
├── logging/                # Logging configuration
│   ├── grafana-datasource.yml
│   └── promtail-config.yml
├── docker-compose.yml      # Docker services configuration
├── env.example            # Environment variables template
└── README.md
```

## Переменные среды

Скопируйте `env.example` в `.env` и настройте:

- `POSTGRES_DB`: Имя базы данных
- `POSTGRES_USER`: Пользователь базы данных
- `POSTGRES_PASSWORD`: пароль базы данных
- `RABBITMQ_USER`: имя пользователя RabbitMQ
- `RABBITMQ_PASSWORD`: пароль RabbitMQ
- `LOG_LEVEL": уровень ведения журнала (отладка, информация, предупреждение, ошибка)

## Разработка

### Добавление новых типов сообщений

1. Обновите метод `ProcessMessage` в `worker/worker.php`
2. Добавьте соответствующую логику в API и консольные приложения.
3. Обновите схему базы данных, если необходимо

### Расширяя API

1. Добавьте новые конечные точки в `api/index.php`
2. Обновите метод `handleRequest`
3. Добавьте соответствующую логику обработки сообщений

## Устранение неполадок

### Проверьте статус службы
```bash
docker-compose ps
```

### Просмотр логов
```bash
# All services
docker-compose logs

# Конкретная служба
docker-compose logs php-worker
docker-compose logs php-api
```

### Перезапустить службы
```bash
# Restart all
docker-compose restart

# Restart specific service
docker-compose restart php-worker
```

### Проблемы с подключением к базе данных
- Убедитесь, что PostgreSQL запущен и доступен
- Проверьте переменные среды в `.env`
- Проверьте сетевое подключение между контейнерами

### Проблемы с подключением RabbitMQ
- Проверьте пользовательский интерфейс управления RabbitMQ по адресу http://localhost:15672
- Проверьте учетные данные в переменных среды
- Проверьте объявление очереди и публикацию сообщений