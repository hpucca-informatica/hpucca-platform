<?php

declare(strict_types=1);

namespace HPucca\Platform\Repositories;

use HPucca\Platform\Models\Tenant;
use HPucca\Platform\Services\PublicCodeGenerator;
use PDO;

final readonly class CompanyRepository implements CompanyRepositoryContract
{
    public function __construct(
        private PDO $connection,
    ) {
    }

    /**
     * @return Tenant[]
     */
    public function paginate(string $search, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $statement = $this->connection->prepare(
            "SELECT id, code, name, slug, status, created_at, updated_at
             FROM tenants
             WHERE (
                 :search = ''
                 OR LOWER(name) LIKE LOWER(:like_search)
                 OR LOWER(slug) LIKE LOWER(:like_search)
                 OR LOWER(code) LIKE LOWER(:like_search)
             )
             ORDER BY name ASC
             LIMIT :limit OFFSET :offset"
        );
        $statement->bindValue('search', $search);
        $statement->bindValue('like_search', '%' . $search . '%');
        $statement->bindValue('limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn (array $row): Tenant => $this->map($row), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function count(string $search): int
    {
        $statement = $this->connection->prepare(
            "SELECT COUNT(*)
             FROM tenants
             WHERE (
                 :search = ''
                 OR LOWER(name) LIKE LOWER(:like_search)
                 OR LOWER(slug) LIKE LOWER(:like_search)
                 OR LOWER(code) LIKE LOWER(:like_search)
             )"
        );
        $statement->execute([
            'search' => $search,
            'like_search' => '%' . $search . '%',
        ]);

        return (int) $statement->fetchColumn();
    }

    public function findById(int $id): ?Tenant
    {
        $statement = $this->connection->prepare(
            'SELECT id, code, name, slug, status, created_at, updated_at FROM tenants WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->map($row);
    }

    public function findBySlug(string $slug): ?Tenant
    {
        $statement = $this->connection->prepare(
            'SELECT id, code, name, slug, status, created_at, updated_at FROM tenants WHERE slug = :slug LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->map($row);
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM tenants WHERE slug = :slug';
        $params = ['slug' => $slug];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * @param array{name: string, slug: string, status: string} $data
     */
    public function create(array $data, PublicCodeGenerator $codes): Tenant
    {
        $statement = $this->connection->prepare(
            'INSERT INTO tenants (code, name, slug, status)
             VALUES (:code, :name, :slug, :status)
             RETURNING id, code, name, slug, status, created_at, updated_at'
        );
        $statement->execute([
            'code' => $codes->next('TEN'),
            'name' => $data['name'],
            'slug' => $data['slug'],
            'status' => $data['status'],
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $this->map($row === false ? [] : $row);
    }

    /**
     * @param array{name: string, slug: string, status: string} $data
     */
    public function update(int $id, array $data): Tenant
    {
        $statement = $this->connection->prepare(
            'UPDATE tenants
             SET name = :name, slug = :slug, status = :status, updated_at = NOW()
             WHERE id = :id
             RETURNING id, code, name, slug, status, created_at, updated_at'
        );
        $statement->execute([
            'id' => $id,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'status' => $data['status'],
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $this->map($row === false ? [] : $row);
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
            'UPDATE tenants SET status = :status, updated_at = NOW() WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'status' => $status,
        ]);

        return $statement->rowCount() > 0;
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
