<?php

declare(strict_types=1);

namespace HPucca\Platform\Repositories;

use HPucca\Platform\Models\IntegrationDestination;
use HPucca\Platform\Services\PublicCodeGenerator;

interface IntegrationDestinationRepositoryContract
{
    /**
     * @return IntegrationDestination[]
     */
    public function paginate(string $search, ?int $tenantId, int $page, int $perPage): array;

    public function count(string $search, ?int $tenantId): int;

    public function findById(int $id): ?IntegrationDestination;

    public function findByCode(string $code): ?IntegrationDestination;

    public function findByTenantAndSlug(int $tenantId, string $slug): ?IntegrationDestination;

    /**
     * @return IntegrationDestination[]
     */
    public function findByTenant(int $tenantId, bool $activeOnly = false): array;

    public function findBySourceId(int $sourceId): ?IntegrationDestination;

    /**
     * @param array{tenant_id: string, name: string, slug: string, type: string, status: string, config: string} $data
     */
    public function create(array $data, PublicCodeGenerator $codes): IntegrationDestination;

    /**
     * @param array{tenant_id: string, name: string, slug: string, type: string, status: string, config: string} $data
     */
    public function update(int $id, array $data): IntegrationDestination;

    public function activate(int $id): bool;

    public function deactivate(int $id): bool;
}
