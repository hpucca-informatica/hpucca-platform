<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$enabled = ($_ENV['RUN_POSTGRES_INTEGRATION_TESTS'] ?? getenv('RUN_POSTGRES_INTEGRATION_TESTS')) === '1';

if (!$enabled) {
    echo 'IntegrationDestinationPostgresTest skipped: set RUN_POSTGRES_INTEGRATION_TESTS=1.' . PHP_EOL;
    return;
}

if (!extension_loaded('pdo_pgsql')) {
    throw new RuntimeException('RUN_POSTGRES_INTEGRATION_TESTS=1 but pdo_pgsql is not available.');
}

$required = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER'];
foreach ($required as $name) {
    if (($_ENV[$name] ?? getenv($name) ?: '') === '') {
        throw new RuntimeException(sprintf('RUN_POSTGRES_INTEGRATION_TESTS=1 but %s is not configured.', $name));
    }
}

$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
    $_ENV['DB_HOST'] ?? getenv('DB_HOST'),
    $_ENV['DB_PORT'] ?? getenv('DB_PORT'),
    $_ENV['DB_NAME'] ?? getenv('DB_NAME'),
    $_ENV['DB_SSLMODE'] ?? getenv('DB_SSLMODE') ?: 'prefer',
);

$connection = new PDO(
    $dsn,
    $_ENV['DB_USER'] ?? getenv('DB_USER'),
    $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: null,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

$suffix = bin2hex(random_bytes(6));
$tenantIds = [];
$destinationIds = [];
$sourceIds = [];

try {
    $connection->beginTransaction();

    assert($connection->query("SELECT to_regclass('integration_destinations')")->fetchColumn() === 'integration_destinations');
    assert($connection->query("SELECT to_regclass('integration_destinations_code_seq')")->fetchColumn() === 'integration_destinations_code_seq');

    $column = $connection->query(
        "SELECT column_name
         FROM information_schema.columns
         WHERE table_name = 'integration_sources'
           AND column_name = 'destination_id'"
    )->fetchColumn();
    assert($column === 'destination_id');

    $insertTenant = $connection->prepare(
        "INSERT INTO tenants (name, slug, status)
         VALUES (:name, :slug, 'active')
         RETURNING id"
    );
    $insertTenant->execute(['name' => 'Destino Tenant A ' . $suffix, 'slug' => 'destino-tenant-a-' . $suffix]);
    $tenantA = (int) $insertTenant->fetchColumn();
    $tenantIds[] = $tenantA;

    $insertTenant->execute(['name' => 'Destino Tenant B ' . $suffix, 'slug' => 'destino-tenant-b-' . $suffix]);
    $tenantB = (int) $insertTenant->fetchColumn();
    $tenantIds[] = $tenantB;

    $insertDestination = $connection->prepare(
        "INSERT INTO integration_destinations (tenant_id, name, slug, type, status)
         VALUES (:tenant_id, :name, :slug, 'n8n', 'active')
         RETURNING id, code"
    );
    $insertDestination->execute(['tenant_id' => $tenantA, 'name' => 'Destino Um ' . $suffix, 'slug' => 'destino-um-' . $suffix]);
    $firstDestination = $insertDestination->fetch(PDO::FETCH_ASSOC);
    $destinationA = (int) $firstDestination['id'];
    $destinationIds[] = $destinationA;
    assert(preg_match('/^DST\d{6}$/', (string) $firstDestination['code']) === 1);

    $insertDestination->execute(['tenant_id' => $tenantA, 'name' => 'Destino Dois ' . $suffix, 'slug' => 'destino-dois-' . $suffix]);
    $secondDestination = $insertDestination->fetch(PDO::FETCH_ASSOC);
    $destinationIds[] = (int) $secondDestination['id'];
    assert($secondDestination['code'] !== $firstDestination['code']);

    try {
        $connection->exec("UPDATE integration_destinations SET code = 'DST999999' WHERE id = " . $destinationA);
        throw new RuntimeException('integration_destinations.code update was not rejected.');
    } catch (PDOException $exception) {
        assert(str_contains($exception->getMessage(), 'integration_destinations.code is immutable'));
    }

    $connection->prepare(
        "UPDATE integration_destinations
         SET name = :name, slug = :slug, status = 'inactive'
         WHERE id = :id"
    )->execute(['name' => 'Destino Atualizado ' . $suffix, 'slug' => 'destino-atualizado-' . $suffix, 'id' => $destinationA]);
    $updated = $connection->prepare('SELECT name, slug, status FROM integration_destinations WHERE id = :id');
    $updated->execute(['id' => $destinationA]);
    $updatedRow = $updated->fetch(PDO::FETCH_ASSOC);
    assert($updatedRow['name'] === 'Destino Atualizado ' . $suffix);
    assert($updatedRow['status'] === 'inactive');

    try {
        $insertDestination->execute(['tenant_id' => $tenantA, 'name' => 'Destino Duplicado ' . $suffix, 'slug' => 'destino-dois-' . $suffix]);
        throw new RuntimeException('Duplicate tenant/slug was not rejected.');
    } catch (PDOException) {
        assert(true);
    }

    $insertDestination->execute(['tenant_id' => $tenantB, 'name' => 'Destino Mesmo Slug ' . $suffix, 'slug' => 'destino-dois-' . $suffix]);
    $sameSlugOtherTenant = $insertDestination->fetch(PDO::FETCH_ASSOC);
    $destinationIds[] = (int) $sameSlugOtherTenant['id'];

    try {
        $insertDestination->execute(['tenant_id' => 999999999, 'name' => 'Destino FK ' . $suffix, 'slug' => 'destino-fk-' . $suffix]);
        throw new RuntimeException('Invalid tenant FK was not rejected.');
    } catch (PDOException) {
        assert(true);
    }

    $insertSource = $connection->prepare(
        "INSERT INTO integration_sources (tenant_id, name, slug, api_key_hash, status)
         VALUES (:tenant_id, :name, :slug, :api_key_hash, 'active')
         RETURNING id"
    );
    $insertSource->execute([
        'tenant_id' => $tenantA,
        'name' => 'Fonte Destino ' . $suffix,
        'slug' => 'fonte-destino-' . $suffix,
        'api_key_hash' => password_hash('hpk_live_destination_' . $suffix, PASSWORD_DEFAULT),
    ]);
    $sourceId = (int) $insertSource->fetchColumn();
    $sourceIds[] = $sourceId;

    $initialDestination = $connection->prepare('SELECT destination_id FROM integration_sources WHERE id = :id');
    $initialDestination->execute(['id' => $sourceId]);
    assert($initialDestination->fetchColumn() === null);

    $connection->prepare('UPDATE integration_sources SET destination_id = :destination_id WHERE id = :source_id')
        ->execute(['destination_id' => $destinationA, 'source_id' => $sourceId]);
    $linkedDestination = $connection->prepare('SELECT destination_id FROM integration_sources WHERE id = :id');
    $linkedDestination->execute(['id' => $sourceId]);
    assert((int) $linkedDestination->fetchColumn() === $destinationA);

    try {
        $connection->prepare('UPDATE integration_sources SET destination_id = :destination_id WHERE id = :source_id')
            ->execute(['destination_id' => 999999999, 'source_id' => $sourceId]);
        throw new RuntimeException('Invalid destination FK was not rejected.');
    } catch (PDOException) {
        assert(true);
    }

    $connection->prepare('UPDATE integration_sources SET destination_id = NULL WHERE id = :source_id')
        ->execute(['source_id' => $sourceId]);
    $nullDestination = $connection->prepare('SELECT destination_id FROM integration_sources WHERE id = :id');
    $nullDestination->execute(['id' => $sourceId]);
    assert($nullDestination->fetchColumn() === null);

    // PostgreSQL currently enforces destination existence only. Same-tenant enforcement is intentionally handled by IntegrationSourceService.
    $connection->rollBack();
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    throw $exception;
}

echo 'IntegrationDestinationPostgresTest passed.' . PHP_EOL;
