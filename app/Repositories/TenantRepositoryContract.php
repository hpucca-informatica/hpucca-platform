<?php

declare(strict_types=1);

namespace HPucca\Platform\Repositories;

use HPucca\Platform\Models\Tenant;

interface TenantRepositoryContract
{
    public function findById(int $id): ?Tenant;

    public function findActiveById(int $id): ?Tenant;

    public function findActiveBySlug(string $slug): ?Tenant;

    /**
     * @return Tenant[]
     */
    public function all(): array;
}
