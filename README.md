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
    CompanyController.php
    UserController.php
    ProfileController.php
    PasswordController.php
  Core/
    Config.php
    Database.php
    Request.php
    Response.php
    Router.php
    View.php
    ViewHelper.php
  Services/
    AuthService.php
    CompanyService.php
    CsrfService.php
    FlashService.php
    PublicCodeGenerator.php
    UserService.php
    ApiKeyService.php
    EventIngestionService.php
    EventQueryService.php
    IntegrationSourceService.php
  Repositories/
    CompanyRepository.php
    CompanyRepositoryContract.php
    EventRepository.php
    EventRepositoryContract.php
    IntegrationSourceRepository.php
    IntegrationSourceRepositoryContract.php
    UserRepositoryContract.php
    TenantRepository.php
    UserRepository.php
  Models/
    Tenant.php
    Event.php
    IntegrationSource.php
    User.php
  Middleware/
    AuthMiddleware.php
    OwnerMiddleware.php
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
    003_add_tenant_code.sql
    004_create_integration_sources.sql
    005_create_events.sql
  seeds/
docs/
public/
  index.php
  assets/
    css/
      admin.css
    js/
      admin.js
routes/
  api.php
storage/
  cache/
  logs/
  uploads/
tests/
views/
  layouts/
    admin.php
  partials/
    sidebar.php
    header.php
    flash.php
  placeholders/
    module.php
  companies/
    index.php
    create.php
    _form.php
    edit.php
    show.php
  users/
    index.php
    create.php
    _form.php
    edit.php
    show.php
    reset-password.php
  integration-sources/
    index.php
    create.php
    _form.php
    edit.php
    show.php
    api-key-created.php
  events/
    index.php
    show.php
  profile/
    show.php
  password/
    change.php
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
APP_TIMEZONE=America/Sao_Paulo
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
Config::get('app.timezone');
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

## Administrative Layout

Sprint 4.1 introduces a reusable authenticated admin layout built with plain PHP views. Authenticated pages use `views/layouts/admin.php` with shared sidebar, header, and flash partials.

The sidebar includes Dashboard, Cadastros, Empresas, Usuarios, Sistema, Perfil, Alterar senha, and a POST-only logout action. The dashboard now uses cards for user, public code, login, category, tenant, application version, and database status, plus quick links to protected placeholder pages.

Protected placeholder routes:

```http
GET /admin/companies
GET /admin/users
GET /profile
GET /change-password
```

User, profile, and password pages do not implement CRUD, profile editing, or password changes yet.

## Administrative Company CRUD

Sprint 4.2 adds the administrative CRUD foundation for companies using the existing `tenants` table. There is no physical delete; companies can be activated or deactivated.

Routes:

```http
GET /admin/companies
GET /admin/companies/create
POST /admin/companies
GET /admin/companies/{id}
GET /admin/companies/{id}/edit
POST /admin/companies/{id}
POST /admin/companies/{id}/activate
POST /admin/companies/{id}/deactivate
```

All company routes are private and require the provisional authenticated user category `owner`. This is not the final permissions model.

Migration `003_add_tenant_code.sql` adds immutable public company codes in the format `TEN000001` using the PostgreSQL sequence `tenants_code_seq`. Existing tenants are populated during the migration, and future inserts can use the database default or the `PublicCodeGenerator` service.

Known migration note: when multiple tenants already exist, the initial assignment of tenant codes follows PostgreSQL update processing order and is not intended to encode business priority. In the current environment there is only one existing tenant, so HPucca Informatica receives `TEN000001`.

All POST administrative forms include a reusable CSRF token. Logout remains POST-only and is also protected by CSRF.

Sprint 4.2 intentionally does not add user CRUD, roles, permissions, audit logs, billing, profile editing, password changes, or business modules.

## Administrative User CRUD

Sprint 4.3 adds the administrative CRUD foundation for users using the existing `users` table. There is no physical delete; users can be activated or deactivated.

Administrative user routes:

```http
GET /admin/users
GET /admin/users/create
POST /admin/users
GET /admin/users/{id}
GET /admin/users/{id}/edit
POST /admin/users/{id}
POST /admin/users/{id}/activate
POST /admin/users/{id}/deactivate
GET /admin/users/{id}/reset-password
POST /admin/users/{id}/reset-password
```

All administrative user routes are private and require the provisional authenticated user category `owner`. This is not the final roles and permissions model.

User login and e-mail uniqueness are scoped to the tenant. E-mail remains optional. Passwords are stored only through `password_hash(PASSWORD_DEFAULT)`, and list/detail views never expose password hashes.

Protection rules:

- the current owner cannot inactivate their own user through the CRUD;
- an active tenant must keep at least one active owner;
- the last active owner of a tenant cannot be inactivated or demoted;
- users from inactive tenants cannot be activated.

Self-service routes:

```http
GET /profile
POST /profile
GET /change-password
POST /change-password
```

The profile page allows the authenticated user to update only `name` and `email`. The change-password page validates the current password with `password_verify()`, requires a new password with at least 10 characters, and regenerates the session after success.

Sprint 4.3 intentionally does not add physical deletion, password recovery by e-mail, e-mail sending, 2FA, OAuth, JWT, persisted sessions, audit logs, full roles and permissions, or business modules.

## Event Ingestion Foundation

Sprint 5.1 adds the first event-oriented automation layer without processing, dispatching, workers, cron, retries, dead-letter queues, n8n delivery, WhatsApp, AI, HMAC signatures, or API key rotation.

Two domain concepts are introduced:

- `IntegrationSource`: an external system authorized to send events for one tenant.
- `Event`: an external fact received by the platform and stored in a pending queue.

Administrative routes for integration sources:

```http
GET /admin/integration-sources
GET /admin/integration-sources/create
POST /admin/integration-sources
GET /admin/integration-sources/{id}
GET /admin/integration-sources/{id}/edit
POST /admin/integration-sources/{id}
POST /admin/integration-sources/{id}/activate
POST /admin/integration-sources/{id}/deactivate
```

Administrative event routes:

```http
GET /admin/events
GET /admin/events/{id}
```

All administrative source and event routes require an authenticated `owner` through `AuthMiddleware` and `OwnerMiddleware`. POST administrative routes keep CSRF protection. Non-owner users receive HTTP 403 even if links are hidden from the sidebar.

### API Keys

Each integration source receives its own API key with the prefix `hpk_live_`. The key is generated with `random_bytes()`, displayed only once immediately after creation, and never shown again in listings, detail pages, URLs, logs, or administrative endpoints.

Only a `password_hash()` hash is stored in `integration_sources.api_key_hash`. Runtime authentication uses `password_verify()` with constant-time password hash verification. This favors safe one-way storage and future algorithm upgrades over reversible or plaintext API key storage.

The one-time key screen shows the generated value in a full-width readonly input with a local copy button. The key is still not stored in plaintext, not placed in URLs, and not exposed again after leaving the screen.

API key rotation is intentionally outside this Sprint.

### Administrative Polish

Sprint 5.1.1 adds interface polish only. It does not change migrations, database structure, API contracts, authentication, authorization, event ingestion rules, idempotency, or queue behavior.

Integration source forms generate a slug from the source name in the browser while the slug field is still empty. The generated slug is lowercase, removes accents, converts `ç` to `c`, removes special characters, converts spaces to hyphens, compacts duplicated hyphens, trims hyphens at the edges, and is limited to 100 characters. If the user edits the slug manually, automatic updates stop for that form, and edit pages preserve the existing slug on load.

The backend remains authoritative for slug validation and tenant-scoped uniqueness. JavaScript only improves the form experience.

Administrative event and integration source screens now reuse a small copy component for public codes and JSON payloads, display statuses as badges, use empty states for empty listings, and format dates as `dd/mm/yyyy HH:mm:ss` using `APP_TIMEZONE`.

### Public Webhook

```http
POST /api/v1/events
Content-Type: application/json
X-API-Key: hpk_live_...
```

Expected body:

```json
{
  "event": "lead.created",
  "external_id": "identificador-unico-no-sistema-origem",
  "occurred_at": "2026-08-03T10:00:00-03:00",
  "data": {}
}
```

`occurred_at` is optional. `data` must be a JSON object. The maximum request body size is 65536 bytes. Payloads must not contain passwords, tokens, API keys, or other secrets.

Example:

```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-API-Key: SUA_API_KEY" \
  -d '{"event":"lead.created","external_id":"LEAD-001","data":{"name":"Teste"}}' \
  https://DOMINIO/api/v1/events
```

Webhook responses:

```json
{"status":"accepted","event_code":"EVT000001"}
```

Accepted events return HTTP 202 and are persisted with status `pending`.

Duplicate events return HTTP 200:

```json
{"status":"duplicate","event_code":"EVT000001"}
```

The duplicate decision uses HTTP 200 so external systems can safely retry delivery without treating an already accepted event as a failure.

Other response codes:

- HTTP 401 for missing or invalid API key.
- HTTP 403 for inactive source or inactive tenant.
- HTTP 415 for non-JSON Content-Type.
- HTTP 422 for invalid JSON, invalid fields, missing `event`, missing `external_id`, invalid `data`, invalid `occurred_at`, or oversized payload.
- HTTP 500 for generic internal failures without stack trace, SQL, API key hash, or secrets.

### Idempotency and Queue State

Events are idempotent by `(integration_source_id, external_id, event_type)`. If the source sends the same event again, the platform returns the existing event code and does not create another row or silently replace the payload.

Event statuses are:

- `pending`: received and waiting for future processing.
- `processing`: reserved for a future worker.
- `processed`: reserved for a future successful processing state.
- `failed`: reserved for a future terminal failure state.

Sprint 5.1 only creates `pending` events. The administrative interface allows listing, filtering, and viewing escaped JSON payloads; it does not allow editing payloads, changing status, reprocessing, or deleting events.

## Event Dispatcher Foundation

Sprint 6.1 adds the first manual dispatcher cycle for the event queue. The dispatcher is executed only through CLI and does not add browser buttons, public routes, cron, daemon, external HTTP calls, n8n, WhatsApp, retries, backoff, dead-letter queues, or reprocessing.

```bash
php bin/dispatch-events.php
php bin/dispatch-events.php --limit=1
```

Output examples:

```text
No pending event available.
Processed event EVT000002.
Failed event EVT000003.
```

Exit code `0` means idle or successfully processed. Exit code `1` means processing failed or an internal dispatcher error occurred.

The dispatcher selects one eligible event per execution:

- `events.status = pending`;
- `events.available_at <= CURRENT_TIMESTAMP`;
- tenant is `active`;
- integration source is `active`;
- ordered by `available_at ASC, id ASC`.

Reservation is atomic and short-lived. `EventRepository::reserveNextPending()` opens a transaction, locks one eligible row with PostgreSQL `FOR UPDATE OF e SKIP LOCKED`, updates it to `processing`, increments `attempts`, commits, and only then returns the event to the dispatcher. Processing happens outside that transaction so slow or failing processors do not hold database locks.

Relevant reservation shape:

```sql
SELECT e.id
FROM events e
INNER JOIN tenants t ON t.id = e.tenant_id
INNER JOIN integration_sources s ON s.id = e.integration_source_id
WHERE e.status = 'pending'
  AND e.available_at <= CURRENT_TIMESTAMP
  AND t.status = 'active'
  AND s.status = 'active'
ORDER BY e.available_at ASC, e.id ASC
FOR UPDATE OF e SKIP LOCKED
LIMIT 1
```

Allowed transitions in this Sprint:

- `pending -> processing`;
- `processing -> processed`;
- `processing -> failed`.

Successful processing sets `processed_at`, clears `failed_at` and `last_error`, and preserves the incremented `attempts`. Failed processing sets `failed_at`, keeps `processed_at` null, and stores only a sanitized error such as `Event processing failed.` without stack trace, credentials, API keys, DSN, or payload content.

The initial processor is `SimulatedEventProcessor`. It succeeds by default and fails only when the stored JSON payload contains `"simulate_failure": true`, a temporary test hook for the dispatcher that will be replaced when a real destination exists.

## Manual Event Reprocessing

Sprint 6.2 adds owner-only manual reprocessing for failed events from the event detail screen. The administrative action is:

```http
POST /admin/events/{id}/reprocess
```

The action uses session authentication, `OwnerMiddleware`, POST, and CSRF. It does not execute the Dispatcher, does not call external destinations, and does not process the event during the HTTP request. It only performs the allowed transition:

- `failed -> pending`.

The SQL transition is guarded in the repository with `WHERE id = :id AND status = 'failed'`, so processed, pending, processing, missing, or concurrently changed events are not silently requeued by application checks alone.

Reprocessing sets `status = pending`, `available_at = CURRENT_TIMESTAMP`, clears `failed_at` and `last_error`, keeps `processed_at` null, updates `updated_at`, and preserves immutable event data such as `payload`, `code`, tenant, source, `external_id`, and `event_type`.

`attempts` is intentionally not reset. It represents total historical reservation attempts for the event, so after a failed event is manually returned to the queue, the next Dispatcher reservation increments the existing count.

Manual reprocessing is different from automatic retry. Sprint 6.2 does not add retry policy, backoff, max attempts, cron, continuous worker, supervisor, dead-letter queue, HTTP client, n8n, WhatsApp, Redis, RabbitMQ, Kafka, or a processing history table. Detailed attempt history can be added in a future Sprint.

If a process dies after reserving an event, the row may remain in `processing`. The event detail screen now calls this out as an operational risk, but Sprint 6.2 does not implement timeout, watchdog, or stale processing recovery.

## Scheduled Dispatcher Execution

Sprint 6.3 makes the Dispatcher suitable for safe scheduled execution without adding a continuous worker, supervisor, n8n, WhatsApp, or external HTTP delivery.

The CLI accepts a bounded batch size:

```bash
php bin/dispatch-events.php
php bin/dispatch-events.php --limit=25
```

Configuration defaults:

```env
EVENT_DISPATCH_LIMIT_DEFAULT=10
EVENT_DISPATCH_LIMIT_MAX=100
EVENT_PROCESSING_TIMEOUT_MINUTES=15
```

The default limit is used when no argument is provided. `--limit=N` must be between `1` and the configured maximum. Each execution processes at most `N` events, stops early when the queue is empty, and continues after individual event failures.

Summary output is intentionally small and safe:

```text
Recovered stale events: 0
Processed: 12
Failed: 1
Total: 13
```

No payload, token, API key, DSN, SQL detail, stack trace, or event internals are printed.

Exit codes:

- `0`: normal execution;
- `1`: internal dispatcher failure;
- `2`: invalid arguments;
- `3`: another dispatcher execution is already running.

Before processing, the command acquires a PostgreSQL advisory lock. If another scheduled execution already holds the lock, the command prints `Dispatcher already running.` and exits with code `3` without processing events. The advisory lock is global to the database session and works across containers, which is why it is preferred over a local lock file.

The reserved advisory lock key is `6320001` in the logical namespace `HPucca Platform / Event Dispatcher Scheduler`. Its only purpose is to prevent two global scheduler executions from running at the same time. This key is not a secret or credential, but it must not be reused by other components; future advisory locks should use their own identifiers. Changing the key in production without coordination can temporarily allow two schedulers to run with different locks.

`SKIP LOCKED` and the advisory lock solve different problems. `SKIP LOCKED` protects each event reservation so two transactions do not reserve the same row. The advisory lock protects the scheduler as a whole so two cron invocations do not run the same dispatcher loop at the same time.

Before the batch loop, stale `processing` events are recovered when:

```sql
status = 'processing'
AND updated_at < CURRENT_TIMESTAMP - timeout
```

Recovered events return to `pending`, get `available_at = CURRENT_TIMESTAMP`, clear `processed_at`, `failed_at`, and `last_error`, and keep `attempts` unchanged. Recent `processing` events are not recovered.

For EasyPanel or Hostinger, configure a cron/scheduled command approximately every minute:

```bash
php /var/www/html/bin/dispatch-events.php --limit=25
```

Use the PHP binary from the deployed container/app image and the project directory that contains `bin/dispatch-events.php`. A one-minute interval is enough initially because each run is bounded, lock-protected, and exits cleanly. This is still cron-driven scheduling, not a continuous worker: the process starts, handles a limited batch, and exits.

### Tenant Isolation

An API key belongs to exactly one integration source, and that source belongs to exactly one tenant. The webhook derives `tenant_id` from the authenticated source, never from user-submitted JSON. A source from one tenant cannot create events for another tenant.

Integration source codes use `SRC000001` from the PostgreSQL sequence `integration_sources_code_seq`. Event codes use `EVT000001` from `events_code_seq`. Both codes are immutable through PostgreSQL triggers.

HMAC request signatures are documented as a future hardening step and are not implemented in Sprint 5.1.
