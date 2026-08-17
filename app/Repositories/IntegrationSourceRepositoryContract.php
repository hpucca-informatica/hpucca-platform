<?php

declare(strict_types=1);

namespace HPucca\Platform\Repositories;

use HPucca\Platform\Models\IntegrationSource;
use HPucca\Platform\Services\PublicCodeGenerator;

interface IntegrationSourceRepositoryContract
{
    /**
     * @return IntegrationSource[]
     */
    public function paginate(string $search, ?int $tenantId, int $page, int $perPage): array;

    public function count(string $search, ?int $tenantId): int;

    public function findById(int $id): ?IntegrationSource;

    public function findByTenantAndSlug(int $tenantId, string $slug): ?IntegrationSource;

    /**
     * @return IntegrationSource[]
     */
    public function sourcesForAuthentication(): array;

    /**
     * @param array{tenant_id: string, name: string, slug: string, status: string, destination_id: string, api_key_hash: string} $data
     */
    public function create(array $data, PublicCodeGenerator $codes): IntegrationSource;

    /**
     * @param array{tenant_id: string, name: string, slug: string, status: string, destination_id: string} $data
     */
    public function update(int $id, array $data): IntegrationSource;

    public function activate(int $id): bool;

    public function deactivate(int $id): bool;

    public function touchLastUsedAt(int $id): void;
}
