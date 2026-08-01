# HPucca Platform

HPucca Platform is a PHP 8.3 modular base for an event-oriented automation platform.

This stage contains a minimal HTTP foundation, environment configuration, PostgreSQL connectivity, health endpoints, a migration runner, and the first authentication foundation. It does not include business rules, permissions, event dispatching, event bus logic, Redis, n8n, integrations, or complete multi-tenant behavior.

## Requirements

- PHP 8.3
- Composer
- PostgreSQL available outside the application container

## Structure

```text
app/
  Controllers/
    HealthController.php
    AuthController.php
    DashboardController.php
  Core/
    Config.php
    Database.php
    Request.php
    Response.php
    Router.php
  Services/
    AuthService.php
    PublicCodeGenerator.php
    UserService.php
  Repositories/
    TenantRepository.php
    UserRepository.php
  Models/
    Tenant.php
    User.php
  Middleware/
    AuthMiddleware.php
  Events/
  Dispatcher/
  Integrations/
bootstrap/
  app.php
bin/
  migrate.php
config/
  app.php
  database.php
database/
  migrations/
    001_create_tenants.sql
    002_create_users.sql
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
views/
  login.php
  dashboard.php
```

## Install

```bash
composer install
```

Copy `.env.example` to `.env` for local development and set the values for your environment. The `.env` file is intentionally ignored by Git.

## Environment

```dotenv
APP_NAME="HPucca Platform"
APP_ENV=local
APP_DEBUG=true
APP_VERSION=0.2.0

DB_HOST=
DB_PORT=5432
DB_NAME=
DB_USER=
DB_PASSWORD=
DB_SSLMODE=prefer
```

Configuration is loaded through `HPucca\Platform\Core\Config`:

```php
Config::get('app.name');
Config::get('app.version');
Config::get('database.host');
```

## Run Locally

```bash
php -S localhost:8000 -t public
```

## PostgreSQL

The application uses PDO with the `pgsql` driver and lazy connections. The application container does not include PostgreSQL; the database must run as a separate service, such as an EasyPanel database service.

## Migrations

Run migrations with:

```bash
php bin/migrate.php
```

The runner creates `schema_migrations` when needed, executes pending `.sql` files in `database/migrations`, wraps each migration in a transaction, and records the migration name and execution date. Rollback is intentionally out of scope for Sprint 2.

## Docker

```bash
docker build -t hpucca-platform .
docker run --rm -p 8080:80 hpucca-platform
```

## Health Check

```http
GET /api/v1/health
```

When the database is connected:

```json
{
  "status": "ok",
  "version": "0.2.0",
  "database": "connected"
}
```

When the database is unavailable, the endpoint still returns HTTP 200:

```json
{
  "status": "ok",
  "version": "0.2.0",
  "database": "unavailable"
}
```

```http
GET /api/v1/health/database
```

Returns HTTP 200 when connected:

```json
{
  "status": "ok",
  "database": "connected"
}
```

Returns HTTP 503 when unavailable:

```json
{
  "status": "error",
  "database": "unavailable"
}
```

No health response exposes credentials, DSN values, stack traces, or internal connection messages.

## Authentication Foundation

Sprint 3 uses PHP sessions and authenticates with:

- tenant/company slug;
- user login;
- password.

E-mail is optional contact data only. It is not a login credential. Login is unique inside each tenant, and e-mail, when present, is also unique inside each tenant.

Users keep the internal `id BIGSERIAL` and also receive an immutable public code such as `USR000001`. User codes are generated from a PostgreSQL sequence (`users_code_seq`), avoiding `MAX(code)` race conditions. The `PublicCodeGenerator` service keeps the formatting reusable for future prefixes such as `TEN`, `CLI`, `LED`, `AGD`, `IMO`, `ATD`, and `FIN`, but those entities are not implemented yet.

Initial user categories are `owner`, `admin`, `manager`, and `user`. The `type` field is only an initial classification and does not replace the future roles and permissions system.

```http
GET /login
POST /login
POST /logout
GET /dashboard
```
