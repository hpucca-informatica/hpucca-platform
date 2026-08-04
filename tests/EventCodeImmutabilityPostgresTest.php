<?php

declare(strict_types=1);

$enabled = ($_ENV['RUN_POSTGRES_INTEGRATION_TESTS'] ?? getenv('RUN_POSTGRES_INTEGRATION_TESTS')) === '1';

if (!$enabled) {
    echo 'EventCodeImmutabilityPostgresTest skipped: set RUN_POSTGRES_INTEGRATION_TESTS=1.' . PHP_EOL;
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

$requiredTables = ['tenants', 'integration_sources', 'events'];

foreach ($requiredTables as $table) {
    $statement = $connection->prepare("SELECT to_regclass(:table_name)");
    $statement->execute(['table_name' => $table]);

    if ($statement->fetchColumn() !== $table) {
        throw new RuntimeException(sprintf('Required table %s was not found. Run migrations 004 and 005 first.', $table));
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
        'name' => 'Evento Teste ' . $suffix,
        'slug' => 'evento-teste-' . $suffix,
    ]);
    $tenantId = (int) $tenantStatement->fetchColumn();

    $sourceStatement = $connection->prepare(
        "INSERT INTO integration_sources (tenant_id, name, slug, api_key_hash, status)
         VALUES (:tenant_id, :name, :slug, :api_key_hash, 'active')
         RETURNING id, code"
    );
    $sourceStatement->execute([
        'tenant_id' => $tenantId,
        'name' => 'Fonte Teste',
        'slug' => 'fonte-teste-' . $suffix,
        'api_key_hash' => password_hash('hpk_live_test_' . $suffix, PASSWORD_DEFAULT),
    ]);
    $source = $sourceStatement->fetch(PDO::FETCH_ASSOC);

    assert(is_array($source));
    assert(preg_match('/^SRC\d{6}$/', (string) $source['code']) === 1);

    $connection->exec('SAVEPOINT source_code_update_attempt');

    try {
        $updateSourceCode = $connection->prepare("UPDATE integration_sources SET code = 'SRC999999' WHERE id = :id");
        $updateSourceCode->execute(['id' => (int) $source['id']]);
        throw new RuntimeException('integration_sources.code update was not rejected.');
    } catch (PDOException $exception) {
        assert(str_contains($exception->getMessage(), 'integration_sources.code is immutable'));
        $connection->exec('ROLLBACK TO SAVEPOINT source_code_update_attempt');
    }

    $renameSource = $connection->prepare("UPDATE integration_sources SET name = 'Fonte Teste Atualizada' WHERE id = :id");
    $renameSource->execute(['id' => (int) $source['id']]);
    $sourceName = $connection->query('SELECT name FROM integration_sources WHERE id = ' . (int) $source['id'])->fetchColumn();
    assert($sourceName === 'Fonte Teste Atualizada');

    $eventStatement = $connection->prepare(
        "INSERT INTO events (tenant_id, integration_source_id, event_type, external_id, payload)
         VALUES (:tenant_id, :integration_source_id, 'lead.created', :external_id, CAST(:payload AS jsonb))
         RETURNING id, code"
    );
    $eventStatement->execute([
        'tenant_id' => $tenantId,
        'integration_source_id' => (int) $source['id'],
        'external_id' => 'EXT-' . $suffix,
        'payload' => '{"name":"Teste"}',
    ]);
    $event = $eventStatement->fetch(PDO::FETCH_ASSOC);

    assert(is_array($event));
    assert(preg_match('/^EVT\d{6}$/', (string) $event['code']) === 1);

    $connection->exec('SAVEPOINT event_code_update_attempt');

    try {
        $updateEventCode = $connection->prepare("UPDATE events SET code = 'EVT999999' WHERE id = :id");
        $updateEventCode->execute(['id' => (int) $event['id']]);
        throw new RuntimeException('events.code update was not rejected.');
    } catch (PDOException $exception) {
        assert(str_contains($exception->getMessage(), 'events.code is immutable'));
        $connection->exec('ROLLBACK TO SAVEPOINT event_code_update_attempt');
    }

    $updateEvent = $connection->prepare("UPDATE events SET last_error = 'Erro controlado de teste' WHERE id = :id");
    $updateEvent->execute(['id' => (int) $event['id']]);
    $lastError = $connection->query('SELECT last_error FROM events WHERE id = ' . (int) $event['id'])->fetchColumn();
    assert($lastError === 'Erro controlado de teste');

    echo 'EventCodeImmutabilityPostgresTest passed.' . PHP_EOL;
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
}
