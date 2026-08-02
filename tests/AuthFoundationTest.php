<?php

declare(strict_types=1);

use HPucca\Platform\Models\User;
use HPucca\Platform\Repositories\TenantRepository;
use HPucca\Platform\Repositories\UserRepository;
use HPucca\Platform\Services\AuthService;
use HPucca\Platform\Services\PublicCodeGenerator;

require dirname(__DIR__) . '/vendor/autoload.php';

ini_set('session.save_path', dirname(__DIR__) . '/storage/cache');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function connection(): PDO
{
    $connection = new PDO('sqlite::memory:');
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $connection->exec(
        "CREATE TABLE tenants (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code VARCHAR(20) NOT NULL UNIQUE,
            name VARCHAR(150) NOT NULL,
            slug VARCHAR(150) NOT NULL UNIQUE,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );
    $connection->exec(
        "CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code VARCHAR(20) NOT NULL UNIQUE,
            tenant_id BIGINT NOT NULL,
            login VARCHAR(60) NOT NULL,
            email VARCHAR(255) NULL,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(150) NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'user',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES tenants(id),
            UNIQUE (tenant_id, login),
            UNIQUE (tenant_id, email),
            CHECK (type IN ('owner', 'admin', 'manager', 'user')),
            CHECK (status IN ('active', 'inactive'))
        )"
    );

    return $connection;
}

function insertTenant(PDO $connection, string $name, string $slug, string $status = 'active'): int
{
    static $sequence = 0;

    $sequence++;
    $statement = $connection->prepare(
        'INSERT INTO tenants (code, name, slug, status) VALUES (:code, :name, :slug, :status)'
    );
    $statement->execute([
        'code' => PublicCodeGenerator::format('TEN', $sequence),
        'name' => $name,
        'slug' => $slug,
        'status' => $status,
    ]);

    return (int) $connection->lastInsertId();
}

function insertUser(
    PDO $connection,
    int $tenantId,
    string $code,
    string $login,
    string $password,
    string $status = 'active',
    string $type = 'user',
    ?string $email = null,
): void {
    $statement = $connection->prepare(
        'INSERT INTO users (code, tenant_id, login, email, password, name, type, status)
         VALUES (:code, :tenant_id, :login, :email, :password, :name, :type, :status)'
    );
    $statement->execute([
        'code' => $code,
        'tenant_id' => $tenantId,
        'login' => $login,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'name' => 'Usuario ' . $login,
        'type' => $type,
        'status' => $status,
    ]);
}

function assertFails(callable $callback): void
{
    try {
        $callback();
    } catch (Throwable) {
        return;
    }

    throw new RuntimeException('Expected failure did not happen.');
}

$_SESSION = [];
$connection = connection();
$tenantA = insertTenant($connection, 'Empresa A', 'empresa-a');
$tenantB = insertTenant($connection, 'Empresa B', 'empresa-b');
$tenantInactive = insertTenant($connection, 'Empresa Inativa', 'empresa-inativa', 'inactive');

insertUser($connection, $tenantA, 'USR000001', 'operador', 'secret', type: 'owner', email: 'a@example.com');
insertUser($connection, $tenantB, 'USR000002', 'operador', 'secret', email: 'b@example.com');
insertUser($connection, $tenantA, 'USR000003', 'inativo', 'secret', 'inactive');
insertUser($connection, $tenantInactive, 'USR000004', 'ativo', 'secret');

assertFails(static fn () => insertUser($connection, $tenantA, 'USR000005', 'operador', 'secret'));
assertFails(static fn () => insertUser($connection, $tenantA, 'USR000006', 'outro', 'secret', email: 'a@example.com'));
assertFails(static fn () => insertUser($connection, $tenantA, 'USR000007', 'tipo', 'secret', type: 'invalid'));

$auth = new AuthService(new TenantRepository($connection), new UserRepository($connection));

assert($auth->attempt('empresa-a', 'a@example.com', 'secret') === false);
assert($auth->attempt('empresa-x', 'operador', 'secret') === false);
assert($auth->attempt('empresa-inativa', 'ativo', 'secret') === false);
assert($auth->attempt('empresa-a', 'ausente', 'secret') === false);
assert($auth->attempt('empresa-a', 'inativo', 'secret') === false);
assert($auth->attempt('empresa-a', 'operador', 'wrong') === false);
assert($auth->attempt('empresa-a', 'operador', 'secret') === true);
assert($_SESSION['user_id'] === 1);
assert($_SESSION['user_code'] === 'USR000001');
assert($_SESSION['tenant_id'] === $tenantA);
assert($_SESSION['login'] === 'operador');
assert($_SESSION['name'] === 'Usuario operador');
assert($_SESSION['type'] === 'owner');
assert(!array_key_exists('password', $_SESSION));

$user = (new UserRepository($connection))->findActiveByTenantAndLogin($tenantA, 'operador');
assert($user instanceof User);
assert($user->code === PublicCodeGenerator::format('USR', 1));

assertFails(static function () use ($user): void {
    $user->code = 'USR999999';
});

$auth->logout();
assert($_SESSION === []);

echo 'AuthFoundationTest passed.' . PHP_EOL;
