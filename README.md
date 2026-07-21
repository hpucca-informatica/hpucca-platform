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

## Request Flow

For `GET /api/v1/health`:

1. The web server sends the request to `public/index.php`.
2. `public/index.php` loads `vendor/autoload.php`.
3. A `Request` is created from PHP globals.
4. A `Router` is created.
5. `routes/api.php` registers the API routes.
6. The router dispatches the request by HTTP method and path.
7. `HealthController` returns a JSON `Response`.
8. The response sends the HTTP status, headers, and JSON body.

Unknown routes return a JSON `404` response. Unhandled exceptions return a JSON `500` response.

## Health Check

```http
GET /api/v1/health
```

Response:

```json
{
  "status": "ok",
  "version": "0.1.0"
}
```

Manual test:

```bash
curl http://localhost:8000/api/v1/health
```
