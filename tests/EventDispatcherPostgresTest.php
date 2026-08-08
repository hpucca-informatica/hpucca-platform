<?php

declare(strict_types=1);

use HPucca\Platform\Repositories\EventRepository;
use HPucca\Platform\Services\DispatcherLock;
use HPucca\Platform\Services\EventDispatcher;
use HPucca\Platform\Services\SimulatedEventProcessor;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/bootstrap/app.php';

$enabled = ($_ENV['RUN_POSTGRES_INTEGRATION_TESTS'] ?? getenv('RUN_POSTGRES_INTEGRATION_TESTS')) === '1';

if (!$enabled) {
    echo 'EventDispatcherPostgresTest skipped: set RUN_POSTGRES_INTEGRATION_TESTS=1.' . PHP_EOL;
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

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
];

$setup = new PDO($dsn, $_ENV['DB_USER'] ?? getenv('DB_USER'), $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '', $options);
$firstConnection = new PDO($dsn, $_ENV['DB_USER'] ?? getenv('DB_USER'), $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '', $options);
$secondConnection = new PDO($dsn, $_ENV['DB_USER'] ?? getenv('DB_USER'), $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '', $options);

foreach (['tenants', 'integration_sources', 'events'] as $table) {
    $statement = $setup->prepare('SELECT to_regclass(:table_name)');
    $statement->execute(['table_name' => $table]);

    if ($statement->fetchColumn() !== $table) {
        throw new RuntimeException(sprintf('Required table %s was not found. Run migrations first.', $table));
    }
}

$suffix = bin2hex(random_bytes(6));
$tenantId = null;
$sourceId = null;
$eventId = null;
$staleEventId = null;
$recentEventId = null;

try {
    $tenantStatement = $setup->prepare(
        "INSERT INTO tenants (name, slug, status)
         VALUES (:name, :slug, 'active')
         RETURNING id"
    );
    $tenantStatement->execute([
        'name' => 'Dispatcher Tenant ' . $suffix,
        'slug' => 'dispatcher-tenant-' . $suffix,
    ]);
    $tenantId = (int) $tenantStatement->fetchColumn();

    $sourceStatement = $setup->prepare(
        "INSERT INTO integration_sources (tenant_id, name, slug, api_key_hash, status)
         VALUES (:tenant_id, :name, :slug, :api_key_hash, 'active')
         RETURNING id"
    );
    $sourceStatement->execute([
        'tenant_id' => $tenantId,
        'name' => 'Dispatcher Source',
        'slug' => 'dispatcher-source-' . $suffix,
        'api_key_hash' => password_hash('hpk_live_dispatcher_' . $suffix, PASSWORD_DEFAULT),
    ]);
    $sourceId = (int) $sourceStatement->fetchColumn();

    $eventStatement = $setup->prepare(
        "INSERT INTO events (tenant_id, integration_source_id, event_type, external_id, payload)
         VALUES (:tenant_id, :source_id, 'lead.created', :external_id, CAST(:payload AS jsonb))
         RETURNING id"
    );
    $eventStatement->execute([
        'tenant_id' => $tenantId,
        'source_id' => $sourceId,
        'external_id' => 'DISPATCH-' . $suffix,
        'payload' => '{"name":"Dispatcher"}',
    ]);
    $eventId = (int) $eventStatement->fetchColumn();

    $firstLock = new DispatcherLock($firstConnection);
    $secondLock = new DispatcherLock($secondConnection);
    assert($firstLock->acquire());
    assert(!$secondLock->acquire());
    $firstLock->release();
    assert($secondLock->acquire());
    $secondLock->release();

    $firstConnection->beginTransaction();
    $secondConnection->beginTransaction();

    $firstDispatcher = new EventDispatcher(new EventRepository($firstConnection), new SimulatedEventProcessor());
    $secondDispatcher = new EventDispatcher(new EventRepository($secondConnection), new SimulatedEventProcessor());

    $first = $firstDispatcher->dispatchOnce();
    $second = $secondDispatcher->dispatchOnce();

    assert($first['status'] === 'processed');
    assert($first['event'] !== null);
    assert($first['event']->id === $eventId);
    assert($second['status'] === 'idle');
    assert($second['event'] === null);
    assert($firstConnection->inTransaction());
    assert($secondConnection->inTransaction());
    $firstConnection->rollBack();
    $secondConnection->rollBack();

    $staleStatement = $setup->prepare(
        "INSERT INTO events (tenant_id, integration_source_id, event_type, external_id, payload, status, attempts, updated_at)
         VALUES (:tenant_id, :source_id, 'lead.created', :external_id, CAST(:payload AS jsonb), 'processing', 3, CURRENT_TIMESTAMP - INTERVAL '30 minutes')
         RETURNING id"
    );
    $staleStatement->execute([
        'tenant_id' => $tenantId,
        'source_id' => $sourceId,
        'external_id' => 'STALE-' . $suffix,
        'payload' => '{"name":"Stale"}',
    ]);
    $staleEventId = (int) $staleStatement->fetchColumn();

    $recentStatement = $setup->prepare(
        "INSERT INTO events (tenant_id, integration_source_id, event_type, external_id, payload, status, attempts, updated_at)
         VALUES (:tenant_id, :source_id, 'lead.created', :external_id, CAST(:payload AS jsonb), 'processing', 4, CURRENT_TIMESTAMP)
         RETURNING id"
    );
    $recentStatement->execute([
        'tenant_id' => $tenantId,
        'source_id' => $sourceId,
        'external_id' => 'RECENT-' . $suffix,
        'payload' => '{"name":"Recent"}',
    ]);
    $recentEventId = (int) $recentStatement->fetchColumn();

    $repository = new EventRepository($setup);
    assert($repository->recoverStaleProcessing(15) === 1);
    $staleEvent = $repository->findById($staleEventId);
    $recentEvent = $repository->findById($recentEventId);
    assert($staleEvent !== null && $staleEvent->status === 'pending');
    assert($staleEvent->attempts === 3);
    assert($recentEvent !== null && $recentEvent->status === 'processing');

    $recoveredDispatch = (new EventDispatcher($repository, new SimulatedEventProcessor()))->dispatchOnce();
    assert($recoveredDispatch['status'] === 'processed');
    $processedStale = $repository->findById($staleEventId);
    assert($processedStale !== null && $processedStale->attempts === 4);

    echo 'EventDispatcherPostgresTest passed.' . PHP_EOL;
} finally {
    if ($secondConnection->inTransaction()) {
        $secondConnection->rollBack();
    }

    if ($firstConnection->inTransaction()) {
        $firstConnection->rollBack();
    }

    if ($recentEventId !== null) {
        $deleteRecent = $setup->prepare('DELETE FROM events WHERE id = :id');
        $deleteRecent->execute(['id' => $recentEventId]);
    }

    if ($staleEventId !== null) {
        $deleteStale = $setup->prepare('DELETE FROM events WHERE id = :id');
        $deleteStale->execute(['id' => $staleEventId]);
    }

    if ($eventId !== null) {
        $deleteEvent = $setup->prepare('DELETE FROM events WHERE id = :id');
        $deleteEvent->execute(['id' => $eventId]);
    }

    if ($sourceId !== null) {
        $deleteSource = $setup->prepare('DELETE FROM integration_sources WHERE id = :id');
        $deleteSource->execute(['id' => $sourceId]);
    }

    if ($tenantId !== null) {
        $deleteTenant = $setup->prepare('DELETE FROM tenants WHERE id = :id');
        $deleteTenant->execute(['id' => $tenantId]);
    }
}
