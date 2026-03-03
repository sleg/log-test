# Log Ingestion Service

Symfony микросервис для приёма логов по HTTP и публикации в RabbitMQ.

## Требования

- Docker и Docker Compose

## Запуск

```bash
docker compose up --build
```

Сервисы:
- **Приложение** - http://localhost:8081
- **RabbitMQ Management** - http://localhost:15672 (логин: dev, пароль: dev)
- **Elasticsearch** - http://localhost:9200

## API

### POST /api/logs/ingest

Принимает батч логов, валидирует и публикует каждый лог в очередь `logs.ingest`.

#### Успешный запрос (202)

```bash
curl -s -X POST http://localhost:8081/api/logs/ingest \
  -H "Content-Type: application/json" \
  -d '{
    "logs": [
      {
        "timestamp": "2026-02-28T10:00:00+00:00",
        "level": "error",
        "service": "auth-service",
        "message": "Login failed",
        "context": {"user_id": 42},
        "trace_id": "abc-123"
      }
    ]
  }'
```

Ответ:
```json
{
  "status": "accepted",
  "batch_id": "batch_9962ae38665b00d67c4c9a2438df03ba",
  "logs_count": 1
}
```

#### Несколько логов в батче (202)

```bash
curl -s -X POST http://localhost:8081/api/logs/ingest \
  -H "Content-Type: application/json" \
  -d '{
    "logs": [
      {
        "timestamp": "2026-02-28T10:00:00+00:00",
        "level": "info",
        "service": "auth-service",
        "message": "User logged in",
        "context": {"user_id": 1}
      },
      {
        "timestamp": "2026-02-28T10:00:01+00:00",
        "level": "warning",
        "service": "payment-service",
        "message": "Retry attempt",
        "trace_id": "xyz-456"
      }
    ]
  }'
```

Ответ:
```json
{
  "status": "accepted",
  "batch_id": "batch_a1b2c3d4e5f6...",
  "logs_count": 2
}
```

#### Ошибка валидации - отсутствуют обязательные поля (400)

```bash
curl -s -X POST http://localhost:8081/api/logs/ingest \
  -H "Content-Type: application/json" \
  -d '{"logs": [{"level": "info"}]}'
```

Ответ:
```json
{
  "status": "error",
  "errors": [
    "logs[0].[timestamp]: This field is missing.",
    "logs[0].[service]: This field is missing.",
    "logs[0].[message]: This field is missing."
  ]
}
```

#### Ошибка валидации - пустой массив логов (400)

```bash
curl -s -X POST http://localhost:8081/api/logs/ingest \
  -H "Content-Type: application/json" \
  -d '{"logs": []}'
```

Ответ:
```json
{
  "status": "error",
  "errors": [
    "\"logs\" must contain at least one item"
  ]
}
```

#### Ошибка валидации - отсутствует поле logs (400)

```bash
curl -s -X POST http://localhost:8081/api/logs/ingest \
  -H "Content-Type: application/json" \
  -d '{}'
```

Ответ:
```json
{
  "status": "error",
  "errors": [
    "\"logs\" must be an array"
  ]
}
```

#### Ошибка - невалидный JSON (400)

```bash
curl -s -X POST http://localhost:8081/api/logs/ingest \
  -H "Content-Type: application/json" \
  -d 'not-json'
```

Ответ:
```json
{
  "status": "error",
  "errors": [
    "Invalid JSON: Syntax error"
  ]
}
```

### Поля лог-записи

| Поле | Тип | Обязательное | Описание |
|------|-----|:---:|----------|
| timestamp | string | да | ISO 8601 формат (например `2026-02-28T10:00:00+00:00`) |
| level | string | да | Уровень лога (error, warning, info и т.д.) |
| service | string | да | Имя сервиса-источника |
| message | string | да | Текст сообщения |
| context | array | нет | Дополнительный контекст |
| trace_id | string | нет | Идентификатор трейса |

### Ограничения

- Батч должен содержать от 1 до 1000 логов
- Все обязательные поля должны быть заполнены
- `timestamp` должен быть в формате ISO 8601 (ATOM)

## Тесты

```bash
docker compose exec -e APP_ENV=test app php vendor/bin/phpunit
```

Только юнит-тесты:
```bash
docker compose exec -e APP_ENV=test app php vendor/bin/phpunit --testsuite Unit
```

Только интеграционные:
```bash
docker compose exec -e APP_ENV=test app php vendor/bin/phpunit --testsuite Integration
```

## Структура проекта

```
src/
  Controller/LogIngestionController.php   - контроллер
  Application/LogIngestionService.php     - сервис валидации и отправки
  Application/LogValidator.php            - валидатор логов
  Application/LogMessageFactory.php       - фабрика сообщений для RabbitMQ
  Domain/LogEntry.php                     - DTO лог-записи
  Domain/LogBatchRequest.php              - DTO батч-запроса
  Messaging/LogIngestedMessage.php        - класс сообщения Messenger
tests/
  Unit/Application/LogValidatorTest.php   - юнит-тесты
  Integration/Controller/                 - интеграционные тесты API
config/
  packages/messenger.yaml                 - настройка Messenger и AMQP
  packages/framework.yaml                 - настройка фреймворка
```