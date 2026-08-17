<?php

declare(strict_types=1);

namespace HPucca\Platform\Repositories;

use HPucca\Platform\Models\IntegrationSource;
use HPucca\Platform\Services\PublicCodeGenerator;
use PDO;

final readonly class IntegrationSourceRepository implements IntegrationSourceRepositoryContract
{
    public function __construct(
        private PDO $connection,
    ) {
    }

    /**
     * @return IntegrationSource[]
     */
    public function paginate(string $search, ?int $tenantId, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        [$where, $params] = $this->where($search, $tenantId);
        $statement = $this->connection->prepare(
            $this->selectSql() . "
             WHERE {$where}
             ORDER BY s.created_at DESC
             LIMIT :limit OFFSET :offset"
        );

        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $statement->bindValue('limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn (array $row): IntegrationSource => $this->map($row), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function count(string $search, ?int $tenantId): int
    {
        [$where, $params] = $this->where($search, $tenantId);
        $statement = $this->connection->prepare(
            "SELECT COUNT(*)
             FROM integration_sources s
             INNER JOIN tenants t ON t.id = s.tenant_id
             WHERE {$where}"
        );
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    public function findById(int $id): ?IntegrationSource
    {
        $statement = $this->connection->prepare($this->selectSql() . ' WHERE s.id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->map($row);
    }

    public function findByTenantAndSlug(int $tenantId, string $slug): ?IntegrationSource
    {
        $statement = $this->connection->prepare(
            $this->selectSql() . ' WHERE s.tenant_id = :tenant_id AND s.slug = :slug LIMIT 1'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'slug' => $slug,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->map($row);
    }

    /**
     * @return IntegrationSource[]
     */
    public function sourcesForAuthentication(): array
    {
        $statement = $this->connection->query(
            $this->selectSql() . ' ORDER BY s.id ASC'
        );

        return array_map(fn (array $row): IntegrationSource => $this->map($row), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param array{tenant_id: string, name: string, slug: string, status: string, destination_id: string, api_key_hash: string} $data
     */
    public function create(array $data, PublicCodeGenerator $codes): IntegrationSource
    {
        $statement = $this->connection->prepare(
            'INSERT INTO integration_sources (code, tenant_id, name, slug, api_key_hash, status, destination_id)
             VALUES (:code, :tenant_id, :name, :slug, :api_key_hash, :status, :destination_id)
             RETURNING id'
        );
        $statement->execute([
            'code' => $codes->next('SRC'),
            'tenant_id' => (int) $data['tenant_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'api_key_hash' => $data['api_key_hash'],
            'status' => $data['status'],
            'destination_id' => $data['destination_id'] === '' ? null : (int) $data['destination_id'],
        ]);

        return $this->findById((int) $statement->fetchColumn()) ?? throw new \RuntimeException('Integration source not found after create.');
    }

    /**
     * @param array{tenant_id: string, name: string, slug: string, status: string, destination_id: string} $data
     */
    public function update(int $id, array $data): IntegrationSource
    {
        $statement = $this->connection->prepare(
            'UPDATE integration_sources
             SET tenant_id = :tenant_id, name = :name, slug = :slug, status = :status, destination_id = :destination_id, updated_at = NOW()
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'tenant_id' => (int) $data['tenant_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'status' => $data['status'],
            'destination_id' => $data['destination_id'] === '' ? null : (int) $data['destination_id'],
        ]);

        return $this->findById($id) ?? throw new \RuntimeException('Integration source not found after update.');
    }

    public function activate(int $id): bool
    {
        return $this->changeStatus($id, 'active');
    }

    public function deactivate(int $id): bool
    {
        return $this->changeStatus($id, 'inactive');
    }

    public function touchLastUsedAt(int $id): void
    {
        $statement = $this->connection->prepare(
            'UPDATE integration_sources SET last_used_at = NOW(), updated_at = NOW() WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
    }

    private function changeStatus(int $id, string $status): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE integration_sources SET status = :status, updated_at = NOW() WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'status' => $status,
        ]);

        return $statement->rowCount() > 0;
    }

    /**
     * @return array{0: string, 1: array<string, string|int>}
     */
    private function where(string $search, ?int $tenantId): array
    {
        $where = "(
            :search = ''
            OR LOWER(s.code) LIKE LOWER(:like_search)
            OR LOWER(s.name) LIKE LOWER(:like_search)
            OR LOWER(s.slug) LIKE LOWER(:like_search)
            OR LOWER(t.name) LIKE LOWER(:like_search)
        )";
        $params = [
            'search' => $search,
            'like_search' => '%' . $search . '%',
        ];

        if ($tenantId !== null) {
            $where .= ' AND s.tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }

        return [$where, $params];
    }

    private function selectSql(): string
    {
        return 'SELECT s.id, s.code, s.tenant_id, t.name AS tenant_name, t.status AS tenant_status,
                    s.name, s.slug, s.api_key_hash, s.status, s.last_used_at, s.destination_id,
                    d.code AS destination_code, d.name AS destination_name, d.type AS destination_type, d.status AS destination_status,
                    s.created_at, s.updated_at
                FROM integration_sources s
                INNER JOIN tenants t ON t.id = s.tenant_id
                LEFT JOIN integration_destinations d ON d.id = s.destination_id';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): IntegrationSource
    {
        return new IntegrationSource(
            (int) $row['id'],
            (string) $row['code'],
            (int) $row['tenant_id'],
            (string) $row['tenant_name'],
            (string) $row['tenant_status'],
            (string) $row['name'],
            (string) $row['slug'],
            (string) $row['api_key_hash'],
            (string) $row['status'],
            is_string($row['last_used_at'] ?? null) ? $row['last_used_at'] : null,
            $row['destination_id'] === null ? null : (int) $row['destination_id'],
            is_string($row['destination_code'] ?? null) ? $row['destination_code'] : null,
            is_string($row['destination_name'] ?? null) ? $row['destination_name'] : null,
            is_string($row['destination_type'] ?? null) ? $row['destination_type'] : null,
            is_string($row['destination_status'] ?? null) ? $row['destination_status'] : null,
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }
}
