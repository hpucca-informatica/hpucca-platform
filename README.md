# HPucca Platform

HPucca Platform is a PHP 8.3 modular base for an event-oriented automation platform.

This stage contains a minimal HTTP foundation and a health endpoint. It does not include business rules, database setup, authentication, event dispatching, event bus logic, dependency containers, or integrations.

## Requirements

- PHP 8.3
- Composer

## Structure

```text
app/
  Controllers/
    HealthController.php
  Core/
    Request.php
    Response.php
    Router.php
  Services/
  Repositories/
  Models/
  Middleware/
  Events/
  Dispatcher/
  Integrations/
config/
database/
  migrations/
  seeds/
docs/
public/
  index.php
routes/
  api.php
storage/
  cache/
  logs/
  uploads/
tests/
```

## Install

```bash
composer install
```

## Run Locally

```bash
php -S localhost:8000 -t public
```

## Health Check

```http
GET /api/v1/health
```

Response

```json
{
  "status": "ok",
  "version": "0.1.0"
}
```