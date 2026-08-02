<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;

$migration = file_get_contents(dirname(__DIR__) . '/database/migrations/003_add_tenant_code.sql');

assert(is_string($migration));
assert(str_contains($migration, 'CREATE SEQUENCE tenants_code_seq'));
assert(str_contains($migration, "DEFAULT ('TEN' || LPAD(nextval('tenants_code_seq')::TEXT, 6, '0'))"));
assert(str_contains($migration, 'CREATE FUNCTION prevent_tenants_code_update()'));
assert(str_contains($migration, "RAISE EXCEPTION 'tenants.code is immutable'"));
assert(str_contains($migration, 'CREATE TRIGGER tenants_code_immutable'));
assert(str_contains($migration, 'BEFORE UPDATE OF code ON tenants'));

if (($_ENV['RUN_POSTGRES_INTEGRATION_TESTS'] ?? getenv('RUN_POSTGRES_INTEGRATION_TESTS')) !== '1') {
    echo 'TenantsCodeImmutabilityPostgresTest skipped: set RUN_POSTGRES_INTEGRATION_TESTS=1 on EasyPanel.' . PHP_EOL;
    exit(0);
}

if (!in_array('pgsql', PDO::getAvailableDrivers(), true)) {
    echo 'TenantsCodeImmutabilityPostgresTest skipped: pdo_pgsql is not available.' . PHP_EOL;
    exit(0);
}

if (class_exists(Dotenv::class)) {
    Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
}

$required = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER'];

foreach ($required as $name) {
    if (($_ENV[$name] ?? getenv($name) ?: '') === '') {
        echo sprintf('TenantsCodeImmutabilityPostgresTest skipped: %s is not configured.%s', $name, PHP_EOL);
        exit(0);
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

$schema = 'hpucca_tenants_test_' . bin2hex(random_bytes(6));

$connection->beginTransaction();

try {
    $connection->exec(sprintf('CREATE SCHEMA %s', $schema));
    $connection->exec(sprintf('SET LOCAL search_path TO %s', $schema));
    $connection->exec((string) file_get_contents(dirname(__DIR__) . '/database/migrations/001_create_tenants.sql'));
    $connection->exec("INSERT INTO tenants (name, slug) VALUES ('HPucca Informatica', 'hpucca-informatica')");
    $connection->exec($migration);

    $code = $connection->query('SELECT code FROM tenants WHERE id = 1')->fetchColumn();
    assert($code === 'TEN000001');

    $connection->exec('SAVEPOINT code_update_attempt');

    try {
        $connection->exec("UPDATE tenants SET code = 'TEN999999' WHERE id = 1");
        throw new RuntimeException('tenants.code update was not rejected.');
    } catch (PDOException $exception) {
        assert(str_contains($exception->getMessage(), 'tenants.code is immutable'));
        $connection->exec('ROLLBACK TO SAVEPOINT code_update_attempt');
    }

    $connection->exec("UPDATE tenants SET name = 'HPucca Atualizada' WHERE id = 1");
    $name = $connection->query('SELECT name FROM tenants WHERE id = 1')->fetchColumn();
    assert($name === 'HPucca Atualizada');

    $connection->exec("INSERT INTO tenants (name, slug) VALUES ('Empresa Dois', 'empresa-dois')");
    $nextCode = $connection->query("SELECT code FROM tenants WHERE slug = 'empresa-dois'")->fetchColumn();
    assert($nextCode === 'TEN000002');

    echo 'TenantsCodeImmutabilityPostgresTest passed.' . PHP_EOL;
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
}
