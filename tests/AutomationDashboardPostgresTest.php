<?php

declare(strict_types=1);

use HPucca\Platform\Core\Config;
use HPucca\Platform\Repositories\EventMetricsRepository;
use HPucca\Platform\Repositories\IntegrationSourceRepository;
use HPucca\Platform\Repositories\TenantRepository;
use HPucca\Platform\Services\AutomationDashboardService;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/bootstrap/app.php';

$enabled = ($_ENV['RUN_POSTGRES_INTEGRATION_TESTS'] ?? getenv('RUN_POSTGRES_INTEGRATION_TESTS')) === '1';

if (!$enabled) {
    echo 'AutomationDashboardPostgresTest skipped: set RUN_POSTGRES_INTEGRATION_TESTS=1.' . PHP_EOL;
    return;
}

if (!in_array('pgsql', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('RUN_POSTGRES_INTEGRATION_TESTS=1 but pdo_pgsql is not available.');
}

$required = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER'];

foreach ($required as $name) {
    if (($_ENV[$name] ?? getenv($name) ?: '') === '') {
        throw new RuntimeException(sprintf('RUN_POSTGRES_INTEGRATION_TESTS=1 but %s is not configured.', $name));
    }
}

$dsn = sprintf(
    'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
    $_ENV['DB_HOST'] ?? getenv('DB_HOST'),
    (int) ($_ENV['DB_PORT'] ?? getenv('DB_PORT')),
    $_ENV['DB_NAME'] ?? getenv('DB_NAME'),
    $_ENV['DB_SSLMODE'] ?? getenv('DB_SSLMODE') ?: 'prefer',
);

$connection = new PDO($dsn, $_ENV['DB_USER'] ?? getenv('DB_USER'), $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

foreach (['tenants', 'integration_sources', 'events'] as $table) {
    $statement = $connection->prepare('SELECT to_regclass(:table_name)');
    $statement->execute(['table_name' => $table]);

    if ($statement->fetchColumn() !== $table) {
        throw new RuntimeException(sprintf('Required table %s was not found. Run migrations first.', $table));
    }
}

$connection->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(6));
    $tenantStatement = $connection->prepare(
        "INSERT INTO tenants (name, slug, status)
         VALUES (:name, :slug, 'active')
         RETURNING id"
    );
    $tenantStatement->execute([
        'name' => 'Metrics Tenant ' . $suffix,
        'slug' => 'metrics-tenant-' . $suffix,
    ]);
    $tenantId = (int) $tenantStatement->fetchColumn();

    $otherTenantStatement = $connection->prepare(
        "INSERT INTO tenants (name, slug, status)
         VALUES (:name, :slug, 'active')
         RETURNING id"
    );
    $otherTenantStatement->execute([
        'name' => 'Metrics Other Tenant ' . $suffix,
        'slug' => 'metrics-other-tenant-' . $suffix,
    ]);
    $otherTenantId = (int) $otherTenantStatement->fetchColumn();

    $sourceStatement = $connection->prepare(
        "INSERT INTO integration_sources (tenant_id, name, slug, api_key_hash, status)
         VALUES (:tenant_id, :name, :slug, :api_key_hash, 'active')
         RETURNING id"
    );
    $sourceStatement->execute([
        'tenant_id' => $tenantId,
        'name' => 'Metrics Source',
        'slug' => 'metrics-source-' . $suffix,
        'api_key_hash' => password_hash('hpk_live_metrics_' . $suffix, PASSWORD_DEFAULT),
    ]);
    $sourceId = (int) $sourceStatement->fetchColumn();

    $otherSourceStatement = $connection->prepare(
        "INSERT INTO integration_sources (tenant_id, name, slug, api_key_hash, status)
         VALUES (:tenant_id, :name, :slug, :api_key_hash, 'active')
         RETURNING id"
    );
    $otherSourceStatement->execute([
        'tenant_id' => $otherTenantId,
        'name' => 'Metrics Other Source',
        'slug' => 'metrics-other-source-' . $suffix,
        'api_key_hash' => password_hash('hpk_live_metrics_other_' . $suffix, PASSWORD_DEFAULT),
    ]);
    $otherSourceId = (int) $otherSourceStatement->fetchColumn();

    $insert = $connection->prepare(
        "INSERT INTO events (
            tenant_id, integration_source_id, event_type, external_id, payload, status, attempts,
            available_at, processed_at, failed_at, last_error, received_at, updated_at
        )
        VALUES (
            :tenant_id, :source_id, :event_type, :external_id, CAST(:payload AS jsonb), :status, :attempts,
            :available_at, :processed_at, :failed_at, :last_error, :received_at, :updated_at
        )"
    );

    $base = [
        'tenant_id' => $tenantId,
        'source_id' => $sourceId,
        'event_type' => 'lead.created',
        'payload' => '{"name":"Metrics"}',
        'available_at' => '2026-08-14 10:00:00+00',
        'processed_at' => null,
        'failed_at' => null,
        'last_error' => null,
        'received_at' => '2026-08-14 10:00:00+00',
        'updated_at' => '2026-08-14 10:00:00+00',
    ];

    foreach ([
        $base + ['external_id' => 'P1-' . $suffix, 'status' => 'processed', 'attempts' => 1, 'processed_at' => '2026-08-14 10:05:00+00'],
        $base + ['external_id' => 'F1-' . $suffix, 'status' => 'failed', 'attempts' => 3, 'failed_at' => '2026-08-14 11:00:00+00', 'last_error' => 'Erro 1', 'updated_at' => '2026-08-14 11:00:00+00'],
        $base + ['external_id' => 'R1-' . $suffix, 'status' => 'pending', 'attempts' => 2, 'available_at' => '2999-01-01 00:00:00+00'],
        $base + ['external_id' => 'PR-RECENT-' . $suffix, 'status' => 'processing', 'attempts' => 1, 'updated_at' => date('Y-m-d H:i:sP', time() - 60)],
        $base + ['external_id' => 'PR-STALE-' . $suffix, 'status' => 'processing', 'attempts' => 2, 'updated_at' => date('Y-m-d H:i:sP', time() - ((max(1, (int) Config::get('app.event_processing_timeout_minutes', 15)) + 5) * 60))],
        $base + ['external_id' => 'OLD-' . $suffix, 'status' => 'failed', 'attempts' => 9, 'received_at' => '2026-07-01 10:00:00+00', 'failed_at' => '2026-07-01 10:00:00+00'],
        $base + ['tenant_id' => $otherTenantId, 'source_id' => $otherSourceId, 'external_id' => 'OTHER-' . $suffix, 'status' => 'processed', 'attempts' => 1],
    ] as $event) {
        $insert->execute($event);
    }

    $repository = new EventMetricsRepository($connection);
    $filters = [
        'received_from' => '2026-08-14 00:00:00+00',
        'received_to' => '2026-08-14 23:59:59+00',
        'tenant_id' => (string) $tenantId,
        'source_id' => (string) $sourceId,
        'event_type' => 'lead.created',
        'status' => '',
    ];

    $summary = $repository->summary($filters);
    assert($summary['received'] === 5);
    assert($summary['processed'] === 1);
    assert($summary['failed'] === 1);
    assert($summary['pending'] === 1);
    assert($summary['processing'] === 2);
    assert($summary['scheduled_retry'] === 1);
    assert(abs(($summary['avg_attempts_finalized'] ?? 0.0) - 2.0) < 0.01);

    $otherFilters = $filters;
    $otherFilters['tenant_id'] = (string) $otherTenantId;
    $otherFilters['source_id'] = (string) $otherSourceId;
    $otherSummary = $repository->summary($otherFilters);
    assert($otherSummary['received'] === 1);

    $statusFilters = $filters;
    $statusFilters['status'] = 'failed';
    assert($repository->summary($statusFilters)['received'] === 1);

    $failures = $repository->recentFailures($filters);
    assert(count($failures) === 1);
    assert($failures[0]['external_id'] === 'F1-' . $suffix);

    $retries = $repository->scheduledRetries($filters);
    assert(count($retries) === 1);
    assert($retries[0]['external_id'] ?? '' === '');
    assert($retries[0]['code'] !== '');

    $processing = $repository->processingEvents($filters);
    assert(count($processing) === 2);
    assert(array_column($processing, 'status') === ['processing', 'processing']);

    $topAttempts = $repository->topAttempts($filters);
    assert(count($topAttempts) === 5);
    assert($topAttempts[0]['attempts'] === 3);

    $series = $repository->dailySeries($filters, 'UTC');
    assert(count($series) === 1);
    assert($series[0]['day'] === '2026-08-14');
    assert($series[0]['received'] === 5);
    assert($series[0]['processed'] === 1);
    assert($series[0]['failed'] === 1);

    $timeoutMinutes = max(1, (int) Config::get('app.event_processing_timeout_minutes', 15));
    $dashboard = (new AutomationDashboardService(
        $repository,
        new IntegrationSourceRepository($connection),
        new TenantRepository($connection),
    ))->dashboard([
        'period' => 'custom',
        'date_from' => '2026-08-14',
        'date_to' => '2026-08-14',
        'tenant_id' => (string) $tenantId,
        'source_id' => (string) $sourceId,
        'event_type' => 'lead.created',
        'status' => '',
    ]);
    assert($dashboard['processingTimeoutMinutes'] === $timeoutMinutes);
    assert(count($dashboard['processingEvents']) === 2);

    foreach ($dashboard['processingEvents'] as $event) {
        assert($event['status'] === 'processing');
        assert(is_int($event['processing_age_minutes']));

        if ($event['processing_age_minutes'] >= $timeoutMinutes) {
            assert($event['processing_state'] === 'stale');
        } else {
            assert($event['processing_state'] === 'recent');
        }
    }

    assert(count(array_filter($dashboard['processingEvents'], static fn (array $event): bool => $event['processing_state'] === 'recent')) === 1);
    assert(count(array_filter($dashboard['processingEvents'], static fn (array $event): bool => $event['processing_state'] === 'stale')) === 1);
    assert($summary['processing'] === 2);

    $connection->rollBack();
    echo 'AutomationDashboardPostgresTest passed.' . PHP_EOL;
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    throw $exception;
}
