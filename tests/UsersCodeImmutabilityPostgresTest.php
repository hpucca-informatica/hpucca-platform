<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;

$migration = file_get_contents(dirname(__DIR__) . '/database/migrations/002_create_users.sql');

assert(is_string($migration));
assert(str_contains($migration, 'CREATE FUNCTION prevent_users_code_update()'));
assert(str_contains($migration, "RAISE EXCEPTION 'users.code is immutable'"));
assert(str_contains($migration, 'CREATE TRIGGER users_code_immutable'));
assert(str_contains($migration, 'BEFORE UPDATE OF code ON users'));
assert(str_contains($migration, "DEFAULT ('USR' || LPAD(nextval('users_code_seq')::TEXT, 6, '0'))"));

if (($_ENV['RUN_POSTGRES_INTEGRATION_TESTS'] ?? getenv('RUN_POSTGRES_INTEGRATION_TESTS')) !== '1') {
    echo 'UsersCodeImmutabilityPostgresTest skipped: set RUN_POSTGRES_INTEGRATION_TESTS=1 on EasyPanel.' . PHP_EOL;
    exit(0);
}

if (!in_array('pgsql', PDO::getAvailableDrivers(), true)) {
    echo 'UsersCodeImmutabilityPostgresTest skipped: pdo_pgsql is not available.' . PHP_EOL;
    exit(0);
}

if (class_exists(Dotenv::class)) {
    Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
}

$required = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER'];

foreach ($required as $name) {
    if (($_ENV[$name] ?? getenv($name) ?: '') === '') {
        echo sprintf('UsersCodeImmutabilityPostgresTest skipped: %s is not configured.%s', $name, PHP_EOL);
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

$schema = 'hpucca_test_' . bin2hex(random_bytes(6));

$connection->beginTransaction();

try {
    $connection->exec(sprintf('CREATE SCHEMA %s', $schema));
    $connection->exec(sprintf('SET LOCAL search_path TO %s', $schema));
    $connection->exec((string) file_get_contents(dirname(__DIR__) . '/database/migrations/001_create_tenants.sql'));
    $connection->exec($migration);

    $connection->exec("INSERT INTO tenants (name, slug) VALUES ('Empresa Teste', 'empresa-teste')");
    $connection->exec(
        "INSERT INTO users (tenant_id, login, password, name)
         VALUES (1, 'operador', '" . password_hash('secret', PASSWORD_DEFAULT) . "', 'Operador')"
    );

    $code = $connection->query('SELECT code FROM users WHERE id = 1')->fetchColumn();
    assert($code === 'USR000001');

    $connection->exec('SAVEPOINT code_update_attempt');

    try {
        $connection->exec("UPDATE users SET code = 'USR999999' WHERE id = 1");
        throw new RuntimeException('users.code update was not rejected.');
    } catch (PDOException $exception) {
        assert(str_contains($exception->getMessage(), 'users.code is immutable'));
        $connection->exec('ROLLBACK TO SAVEPOINT code_update_attempt');
    }

    $connection->exec("UPDATE users SET name = 'Operador Atualizado' WHERE id = 1");
    $name = $connection->query('SELECT name FROM users WHERE id = 1')->fetchColumn();
    assert($name === 'Operador Atualizado');

    echo 'UsersCodeImmutabilityPostgresTest passed.' . PHP_EOL;
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
}
