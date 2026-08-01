<?php

declare(strict_types=1);

namespace HPucca\Platform\Repositories;

use HPucca\Platform\Models\User;
use HPucca\Platform\Services\PublicCodeGenerator;
use PDO;

final readonly class UserRepository
{
    public function __construct(
        private PDO $connection,
    ) {
    }

    public function findActiveByTenantAndLogin(int $tenantId, string $login): ?User
    {
        $statement = $this->connection->prepare(
            'SELECT id, code, tenant_id, login, email, password, name, type, status, created_at, updated_at
             FROM users
             WHERE tenant_id = :tenant_id AND login = :login AND status = :status
             LIMIT 1'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'login' => mb_strtolower($login),
            'status' => 'active',
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->map($row);
    }

    public function findById(int $id): ?User
    {
        $statement = $this->connection->prepare(
            'SELECT id, code, tenant_id, login, email, password, name, type, status, created_at, updated_at
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute([
            'id' => $id,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->map($row);
    }

    public function findActiveById(int $id): ?User
    {
        $statement = $this->connection->prepare(
            'SELECT id, code, tenant_id, login, email, password, name, type, status, created_at, updated_at
             FROM users
             WHERE id = :id AND status = :status
             LIMIT 1'
        );
        $statement->execute([
            'id' => $id,
            'status' => 'active',
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->map($row);
    }

    public function create(
        int $tenantId,
        string $login,
        ?string $email,
        string $passwordHash,
        string $name,
        string $type,
        PublicCodeGenerator $codes,
    ): User {
        $code = $codes->next('USR');
        $statement = $this->connection->prepare(
            'INSERT INTO users (code, tenant_id, login, email, password, name, type)
             VALUES (:code, :tenant_id, :login, :email, :password, :name, :type)'
            . ' RETURNING id, code, tenant_id, login, email, password, name, type, status, created_at, updated_at'
        );
        $statement->execute([
            'code' => $code,
            'tenant_id' => $tenantId,
            'login' => mb_strtolower($login),
            'email' => $email === null ? null : mb_strtolower($email),
            'password' => $passwordHash,
            'name' => $name,
            'type' => $type,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new \RuntimeException('User creation failed.');
        }

        return $this->map($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): User
    {
        return new User(
            (int) $row['id'],
            (string) $row['code'],
            (int) $row['tenant_id'],
            (string) $row['login'],
            $row['email'] === null ? null : (string) $row['email'],
            (string) $row['password'],
            (string) $row['name'],
            (string) $row['type'],
            (string) $row['status'],
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }
}
