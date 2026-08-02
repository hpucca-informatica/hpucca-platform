<?php

declare(strict_types=1);

namespace HPucca\Platform\Repositories;

use HPucca\Platform\Models\User;
use HPucca\Platform\Services\PublicCodeGenerator;
use PDO;
use RuntimeException;

final readonly class UserRepository implements UserRepositoryContract
{
    public function __construct(
        private PDO $connection,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function paginate(string $search, ?int $tenantId, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = $this->listSql()
            . ' WHERE (
                    :search = \'\'
                    OR LOWER(u.code) LIKE LOWER(:like_search)
                    OR LOWER(u.name) LIKE LOWER(:like_search)
                    OR LOWER(u.login) LIKE LOWER(:like_search)
                    OR LOWER(COALESCE(u.email, \'\')) LIKE LOWER(:like_search)
                )';

        if ($tenantId !== null) {
            $sql .= ' AND u.tenant_id = :tenant_id';
        }

        $sql .= ' ORDER BY u.name ASC, u.id ASC LIMIT :limit OFFSET :offset';

        $statement = $this->connection->prepare($sql);
        $statement->bindValue('search', $search);
        $statement->bindValue('like_search', '%' . $search . '%');
        $statement->bindValue('limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);

        if ($tenantId !== null) {
            $statement->bindValue('tenant_id', $tenantId, PDO::PARAM_INT);
        }

        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(string $search, ?int $tenantId): int
    {
        $sql = 'SELECT COUNT(*)
                FROM users u
                INNER JOIN tenants t ON t.id = u.tenant_id
                WHERE (
                    :search = \'\'
                    OR LOWER(u.code) LIKE LOWER(:like_search)
                    OR LOWER(u.name) LIKE LOWER(:like_search)
                    OR LOWER(u.login) LIKE LOWER(:like_search)
                    OR LOWER(COALESCE(u.email, \'\')) LIKE LOWER(:like_search)
                )';

        $params = [
            'search' => $search,
            'like_search' => '%' . $search . '%',
        ];

        if ($tenantId !== null) {
            $sql .= ' AND u.tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    public function findById(int $id): ?User
    {
        $statement = $this->connection->prepare($this->userSql() . ' WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->map($row);
    }

    public function findActiveById(int $id): ?User
    {
        $statement = $this->connection->prepare($this->userSql() . ' WHERE id = :id AND status = :status LIMIT 1');
        $statement->execute([
            'id' => $id,
            'status' => 'active',
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->map($row);
    }

    public function findActiveByTenantAndLogin(int $tenantId, string $login): ?User
    {
        $statement = $this->connection->prepare(
            $this->userSql() . ' WHERE tenant_id = :tenant_id AND login = :login AND status = :status LIMIT 1'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'login' => strtolower($login),
            'status' => 'active',
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->map($row);
    }

    public function loginExists(int $tenantId, string $login, ?int $ignoreId = null): bool
    {
        return $this->fieldExists('login', $tenantId, strtolower($login), $ignoreId);
    }

    public function emailExists(int $tenantId, string $email, ?int $ignoreId = null): bool
    {
        return $this->fieldExists('email', $tenantId, strtolower($email), $ignoreId);
    }

    public function countActiveOwnersByTenant(int $tenantId): int
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM users WHERE tenant_id = :tenant_id AND type = :type AND status = :status'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'type' => 'owner',
            'status' => 'active',
        ]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param array{tenant_id: int, login: string, email: string|null, password: string, name: string, type: string, status: string} $data
     */
    public function create(array $data, PublicCodeGenerator $codes): User
    {
        $statement = $this->connection->prepare(
            'INSERT INTO users (code, tenant_id, login, email, password, name, type, status)
             VALUES (:code, :tenant_id, :login, :email, :password, :name, :type, :status)
             RETURNING id, code, tenant_id, login, email, password, name, type, status, created_at, updated_at'
        );
        $statement->execute([
            'code' => $codes->next('USR'),
            'tenant_id' => $data['tenant_id'],
            'login' => $data['login'],
            'email' => $data['email'],
            'password' => $data['password'],
            'name' => $data['name'],
            'type' => $data['type'],
            'status' => $data['status'],
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new RuntimeException('User creation failed.');
        }

        return $this->map($row);
    }

    /**
     * @param array{tenant_id: int, login: string, email: string|null, name: string, type: string, status: string} $data
     */
    public function update(int $id, array $data): User
    {
        $statement = $this->connection->prepare(
            'UPDATE users
             SET tenant_id = :tenant_id,
                 login = :login,
                 email = :email,
                 name = :name,
                 type = :type,
                 status = :status,
                 updated_at = NOW()
             WHERE id = :id
             RETURNING id, code, tenant_id, login, email, password, name, type, status, created_at, updated_at'
        );
        $statement->execute([
            'id' => $id,
            'tenant_id' => $data['tenant_id'],
            'login' => $data['login'],
            'email' => $data['email'],
            'name' => $data['name'],
            'type' => $data['type'],
            'status' => $data['status'],
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new RuntimeException('User update failed.');
        }

        return $this->map($row);
    }

    public function updateProfile(int $id, string $name, ?string $email): User
    {
        $statement = $this->connection->prepare(
            'UPDATE users
             SET name = :name, email = :email, updated_at = NOW()
             WHERE id = :id
             RETURNING id, code, tenant_id, login, email, password, name, type, status, created_at, updated_at'
        );
        $statement->execute([
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new RuntimeException('Profile update failed.');
        }

        return $this->map($row);
    }

    public function updatePassword(int $id, string $passwordHash): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'password' => $passwordHash,
        ]);

        return $statement->rowCount() > 0;
    }

    public function activate(int $id): bool
    {
        return $this->changeStatus($id, 'active');
    }

    public function deactivate(int $id): bool
    {
        return $this->changeStatus($id, 'inactive');
    }

    private function changeStatus(int $id, string $status): bool
    {
        $statement = $this->connection->prepare('UPDATE users SET status = :status, updated_at = NOW() WHERE id = :id');
        $statement->execute([
            'id' => $id,
            'status' => $status,
        ]);

        return $statement->rowCount() > 0;
    }

    private function fieldExists(string $field, int $tenantId, string $value, ?int $ignoreId): bool
    {
        $sql = sprintf('SELECT COUNT(*) FROM users WHERE tenant_id = :tenant_id AND %s = :value', $field);
        $params = [
            'tenant_id' => $tenantId,
            'value' => $value,
        ];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    private function userSql(): string
    {
        return 'SELECT id, code, tenant_id, login, email, password, name, type, status, created_at, updated_at FROM users';
    }

    private function listSql(): string
    {
        return 'SELECT
                    u.id,
                    u.code,
                    u.tenant_id,
                    u.login,
                    u.email,
                    u.name,
                    u.type,
                    u.status,
                    u.created_at,
                    u.updated_at,
                    t.name AS tenant_name,
                    t.status AS tenant_status
                FROM users u
                INNER JOIN tenants t ON t.id = u.tenant_id';
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
