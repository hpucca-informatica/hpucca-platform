<?php

declare(strict_types=1);

namespace HPucca\Platform\Repositories;

use HPucca\Platform\Models\Tenant;
use HPucca\Platform\Services\PublicCodeGenerator;

interface CompanyRepositoryContract
{
    /**
     * @return Tenant[]
     */
    public function paginate(string $search, int $page, int $perPage): array;

    public function count(string $search): int;

    public function findById(int $id): ?Tenant;

    public function findBySlug(string $slug): ?Tenant;

    public function slugExists(string $slug, ?int $ignoreId = null): bool;

    /**
     * @param array{name: string, slug: string, status: string} $data
     */
    public function create(array $data, PublicCodeGenerator $codes): Tenant;

    /**
     * @param array{name: string, slug: string, status: string} $data
     */
    public function update(int $id, array $data): Tenant;

    public function activate(int $id): bool;

    public function deactivate(int $id): bool;
}
