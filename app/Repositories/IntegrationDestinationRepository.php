<?php

declare(strict_types=1);

namespace HPucca\Platform\Repositories;

use HPucca\Platform\Models\IntegrationDestination;
use HPucca\Platform\Services\PublicCodeGenerator;
use PDO;

final readonly class IntegrationDestinationRepository implements IntegrationDestinationRepositoryContract
{
    public function __construct(
        private PDO $connection,
    ) {
    }

    public function paginate(string $search, ?int $tenantId, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        [$where, $params] = $this->where($search, $tenantId);
        $statement = $this->connection->prepare(
            $this->selectSql() . "
             WHERE {$where}
             ORDER BY d.created_at DESC
             LIMIT :limit OFFSET :offset"
        );

        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $statement->bindValue('limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn (array $row): IntegrationDestination => $this->map($row), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function count(string $search, ?int $tenantId): int
    {
        [$where, $params] = $this->where($search, $tenantId);
        $statement = $this->connection->prepare(
            "SELECT COUNT(*)
             FROM integration_destinations d
             INNER JOIN tenants t ON t.id = d.tenant_id
             WHERE {$where}"
        );
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    public function findById(int $id): ?IntegrationDestination
    {
        $statement = $this->connection->prepare($this->selectSql() . ' WHERE d.id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->map($row);
    }

    public function findByCode(string $code): ?IntegrationDestination
    {
        $statement = $this->connection->prepare($this->selectSql() . ' WHERE d.code = :code LIMIT 1');
        $statement->execute(['code' => $code]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->map($row);
    }

    public function findByTenantAndSlug(int $tenantId, string $slug): ?IntegrationDestination
    {
        $statement = $this->connection->prepare(
            $this->selectSql() . ' WHERE d.tenant_id = :tenant_id AND d.slug = :slug LIMIT 1'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'slug' => $slug,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->map($row);
    }

    public function findByTenant(int $tenantId, bool $activeOnly = false): array
    {
        $where = 'd.tenant_id = :tenant_id';
        $params = ['tenant_id' => $tenantId];

        if ($activeOnly) {
            $where .= ' AND d.status = :status';
            $params['status'] = 'active';
        }

        $statement = $this->connection->prepare($this->selectSql() . " WHERE {$where} ORDER BY d.name ASC");
        $statement->execute($params);

        return array_map(fn (array $row): IntegrationDestination => $this->map($row), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findBySourceId(int $sourceId): ?IntegrationDestination
    {
        $statement = $this->connection->prepare(
            $this->selectSql() . '
             INNER JOIN integration_sources s ON s.destination_id = d.id
             WHERE s.id = :source_id
             LIMIT 1'
        );
        $statement->execute(['source_id' => $sourceId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->map($row);
    }

    public function create(array $data, PublicCodeGenerator $codes): IntegrationDestination
    {
        $statement = $this->connection->prepare(
            'INSERT INTO integration_destinations (code, tenant_id, name, slug, type, status, config)
             VALUES (:code, :tenant_id, :name, :slug, :type, :status, CAST(:config AS JSONB))
             RETURNING id'
        );
        $statement->execute([
            'code' => $codes->next('DST'),
            'tenant_id' => (int) $data['tenant_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'type' => $data['type'],
            'status' => $data['status'],
            'config' => $data['config'],
        ]);

        return $this->findById((int) $statement->fetchColumn()) ?? throw new \RuntimeException('Integration destination not found after create.');
    }

    public function update(int $id, array $data): IntegrationDestination
    {
        $statement = $this->connection->prepare(
            'UPDATE integration_destinations
             SET tenant_id = :tenant_id, name = :name, slug = :slug, type = :type, status = :status, config = CAST(:config AS JSONB), updated_at = NOW()
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'tenant_id' => (int) $data['tenant_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'type' => $data['type'],
            'status' => $data['status'],
            'config' => $data['config'],
        ]);

        return $this->findById($id) ?? throw new \RuntimeException('Integration destination not found after update.');
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
        $statement = $this->connection->prepare(
            'UPDATE integration_destinations SET status = :status, updated_at = NOW() WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'status' => $status,
        ]);

        return $statement->rowCount() > 0;
    }

    private function where(string $search, ?int $tenantId): array
    {
        $where = "(
            :search = ''
            OR LOWER(d.code) LIKE LOWER(:like_search)
            OR LOWER(d.name) LIKE LOWER(:like_search)
            OR LOWER(d.slug) LIKE LOWER(:like_search)
            OR LOWER(d.type) LIKE LOWER(:like_search)
            OR LOWER(t.name) LIKE LOWER(:like_search)
        )";
        $params = [
            'search' => $search,
            'like_search' => '%' . $search . '%',
        ];

        if ($tenantId !== null) {
            $where .= ' AND d.tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }

        return [$where, $params];
    }

    private function selectSql(): string
    {
        return 'SELECT d.id, d.code, d.tenant_id, t.name AS tenant_name, t.status AS tenant_status,
                    d.name, d.slug, d.type, d.status, d.config::TEXT AS config, d.created_at, d.updated_at
                FROM integration_destinations d
                INNER JOIN tenants t ON t.id = d.tenant_id';
    }

    private function map(array $row): IntegrationDestination
    {
        return new IntegrationDestination(
            (int) $row['id'],
            (string) $row['code'],
            (int) $row['tenant_id'],
            (string) $row['tenant_name'],
            (string) $row['tenant_status'],
            (string) $row['name'],
            (string) $row['slug'],
            (string) $row['type'],
            (string) $row['status'],
            (string) $row['config'],
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }
}
