<?php

declare(strict_types=1);

namespace HPucca\Platform\Repositories;

use HPucca\Platform\Models\Tenant;
use PDO;

final readonly class TenantRepository
{
    public function __construct(
        private PDO $connection,
    ) {
    }

    public function findById(int $id): ?Tenant
    {
        $statement = $this->connection->prepare(
            'SELECT id, code, name, slug, status, created_at, updated_at FROM tenants WHERE id = :id LIMIT 1'
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

    public function findActiveById(int $id): ?Tenant
    {
        $statement = $this->connection->prepare(
            'SELECT id, code, name, slug, status, created_at, updated_at
             FROM tenants
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

    public function findActiveBySlug(string $slug): ?Tenant
    {
        $statement = $this->connection->prepare(
            'SELECT id, code, name, slug, status, created_at, updated_at
             FROM tenants
             WHERE slug = :slug AND status = :status
             LIMIT 1'
        );
        $statement->execute([
            'slug' => mb_strtolower($slug),
            'status' => 'active',
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->map($row);
    }

    /**
     * @return Tenant[]
     */
    public function all(): array
    {
        $statement = $this->connection->query(
            'SELECT id, code, name, slug, status, created_at, updated_at FROM tenants ORDER BY name ASC'
        );

        return array_map(fn (array $row): Tenant => $this->map($row), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): Tenant
    {
        return new Tenant(
            (int) $row['id'],
            (string) $row['code'],
            (string) $row['name'],
            (string) $row['slug'],
            (string) $row['status'],
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }
}
