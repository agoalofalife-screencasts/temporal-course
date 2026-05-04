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

## Отладка через Xdebug (PhpStorm)

Xdebug настроен на отладку **только Activity-воркеров Temporal**. 

### Что уже настроено в репозитории

Конфигурация состоит из трёх частей и применяется автоматически при `docker compose up --build`:

**1. Установка Xdebug в контейнере** (`docker/Dockerfile`):
```ini
xdebug.mode=debug
xdebug.start_with_request=trigger     # сессия стартует только при наличии триггера
xdebug.client_host=host.docker.internal
xdebug.client_port=9003
xdebug.idekey=PHPSTORM
xdebug.log=/tmp/xdebug.log
xdebug.discover_client_host=0
```

**2. Сетевая связность контейнер → хост** (`docker-compose.yml`):
```yaml
extra_hosts:
  - "host.docker.internal:host-gateway"
environment:
  - PHP_IDE_CONFIG=serverName=food-delivery
```

`extra_hosts` нужен на Linux, чтобы `host.docker.internal` резолвился внутри контейнера. На macOS он работает «из коробки», но строка не мешает.

**3. Триггер только для Activity-воркеров** (`.rr.yaml`):
```yaml
temporal:
  activities:
    num_workers: 2
    command: "/usr/bin/env XDEBUG_TRIGGER=PHPSTORM php ./vendor/bin/roadrunner-temporal-worker"
```

`/usr/bin/env XDEBUG_TRIGGER=PHPSTORM` ставит переменную окружения **только** для процессов Activity-воркеров. RoadRunner не поддерживает блок `env:` под `temporal.activities`, поэтому используется этот inline-приём с явным путём к `/usr/bin/env` (чтобы не зависеть от `PATH`).

### Настройка PhpStorm (один раз)

1. **PHP → Servers** → создать сервер:
   - **Name:** `food-delivery` (должно совпадать с `PHP_IDE_CONFIG=serverName=...`)
   - **Host:** `localhost`
   - **Port:** `8000`
   - **Debugger:** Xdebug
   - Включить **Use path mappings** и сопоставить:

     | Project file (host)                          | Path on server (container) |
     |----------------------------------------------|----------------------------|
     | `/Users/<you>/.../food-delivery/`            | `/app/`                    |

2. **PHP → Debug** → Xdebug:
   - **Debug port:** `9003`
   - Включить **Can accept external connections**

3. На верхней панели IDE кликнуть **«Start Listening for PHP Debug Connections»** (иконка телефонной трубки с жуком должна стать зелёной). Без этого шага никаких подключений не будет.

## Решение проблем

### Temporal не запускается

Убедитесь, что PostgreSQL для Temporal полностью запустился. Temporal зависит от healthcheck базы данных:

```bash
docker compose ps
docker compose logs temporal
```

### Ошибки с расширениями PHP
Если базовый образ не собран, приложение не запустится. Убедитесь, что выполнен шаг 3 (сборка `food-delivery-base`).
