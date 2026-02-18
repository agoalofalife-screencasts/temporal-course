# Food Delivery

<img src="cover.png" alt="Temporal Course Cover" align="left"  width="300" style="margin-right: 30px; margin-bottom: 20px;" />

Приложение для доставки еды, построенное на **Laravel 12**, **RoadRunner**, **Temporal** и **PostgreSQL**.

## Стек технологий

- **PHP 8.4**
- **Laravel 12** — фреймворк
- **Laravel Octane** — высокопроизводительный сервер приложений, используется только для http
- **RoadRunner 2025.1** — application server, нужен для temporal
- **Temporal 1.29** — оркестрация workflow-процессов
- **PostgreSQL 15** — база данных (для Temporal и для приложения)
- **Docker & Docker Compose** — контейнеризация

## Архитектура сервисов (Docker Compose)

| Сервис           | Контейнер                      | Порт   | Описание                          |
|------------------|--------------------------------|--------|-----------------------------------|
| `postgresql`     | `food_delivery_postgres`       | 5432   | БД для Temporal                   |
| `temporal`       | `food_delivery_temporal`       | 7233   | Temporal Server (gRPC)            |
| `temporal-ui`    | `food_delivery_temporal_ui`    | 8080   | Веб-интерфейс Temporal            |
| `app-postgresql` | `food_delivery_app_postgresql` | 5433   | БД приложения                     |
| `app`            | `food_delivery_app`            | 8000   | Laravel-приложение (RoadRunner)   |

## Требования

- [Docker](https://docs.docker.com/get-docker/) >= 20.10
- [Docker Compose](https://docs.docker.com/compose/install/) >= 2.0

## Установка с нуля

### 1. Клонировать репозиторий

```bash
git clone git@github.com:agoalofalife-screencasts/temporal-course.git food-delivery
cd food-delivery
```

### 2. Создать файл окружения

```bash
cp .env.example .env
```

### 3. Собрать базовый Docker-образ

Приложение использует двухступенчатую сборку. Сначала нужно собрать базовый образ с PHP и расширениями (protobuf, gRPC, pgsql и др.):
Это долгий процесс на моем M1 - заняло около 4 часов!!

```bash
docker build -f docker/Dockerfile.base -t food-delivery-base .
```

### 4. Запустить все сервисы

```bash
docker compose up -d --build
```

Эта команда:
- Соберёт образ приложения (установит Composer-зависимости, скопирует RoadRunner)
- Поднимет PostgreSQL для Temporal
- Запустит Temporal Server и дождётся его готовности
- Поднимет PostgreSQL для приложения
- Запустит Laravel-приложение через RoadRunner

### 5. Сгенерировать ключ приложения

```bash
docker exec food_delivery_app php artisan key:generate
```

### 6. Выполнить миграции

```bash
docker exec food_delivery_app php artisan migrate
```

### 7. Проверить работу

- **Приложение:** http://localhost:8000
- **Temporal UI:** http://localhost:8080

## Полезные команды

### Логи

```bash
# Логи всех сервисов
docker compose logs -f

# Логи конкретного сервиса
docker compose logs -f app
docker compose logs -f temporal
```

### Остановка

```bash
# Остановить все сервисы
docker compose down

# Остановить и удалить volumes (БД будут очищены)
docker compose down -v
```

### Пересборка приложения

```bash
docker compose up -d --build app
```

### Выполнение Artisan-команд

```bash
docker exec food_delivery_app php artisan <команда>
```

### Запуск тестов

```bash
docker exec food_delivery_app php artisan test
```

### Composer

```bash
docker exec food_delivery_app composer install
docker exec food_delivery_app composer require <пакет>
```

## Структура Docker

```
docker/
  Dockerfile.base   # Базовый образ: PHP 8.4 + расширения (protobuf, gRPC, pgsql, zip, sockets, pcntl)
  Dockerfile        # Образ приложения: Composer + RoadRunner + код проекта
```

## Конфигурация RoadRunner (.rr.yaml)

- HTTP-сервер на порту `8000`
- 2 воркера для HTTP-запросов
- Temporal-воркеры для выполнения Activities
- Адрес Temporal задаётся через переменную окружения `TEMPORAL_ADDRESS`

## Решение проблем

### Temporal не запускается

Убедитесь, что PostgreSQL для Temporal полностью запустился. Temporal зависит от healthcheck базы данных:

```bash
docker compose ps
docker compose logs temporal
```

### Ошибки с расширениями PHP
Если базовый образ не собран, приложение не запустится. Убедитесь, что выполнен шаг 3 (сборка `food-delivery-base`).
