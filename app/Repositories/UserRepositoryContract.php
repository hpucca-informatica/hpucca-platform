<?php

declare(strict_types=1);

namespace HPucca\Platform\Repositories;

use HPucca\Platform\Models\User;
use HPucca\Platform\Services\PublicCodeGenerator;

interface UserRepositoryContract
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function paginate(string $search, ?int $tenantId, int $page, int $perPage): array;

    public function count(string $search, ?int $tenantId): int;

    public function findById(int $id): ?User;

    public function findActiveById(int $id): ?User;

    public function findActiveByTenantAndLogin(int $tenantId, string $login): ?User;

    public function loginExists(int $tenantId, string $login, ?int $ignoreId = null): bool;

    public function emailExists(int $tenantId, string $email, ?int $ignoreId = null): bool;

    public function countActiveOwnersByTenant(int $tenantId): int;

    /**
     * @param array{tenant_id: int, login: string, email: string|null, password: string, name: string, type: string, status: string} $data
     */
    public function create(array $data, PublicCodeGenerator $codes): User;

    /**
     * @param array{tenant_id: int, login: string, email: string|null, name: string, type: string, status: string} $data
     */
    public function update(int $id, array $data): User;

    public function updateProfile(int $id, string $name, ?string $email): User;

    public function updatePassword(int $id, string $passwordHash): bool;

    public function activate(int $id): bool;

    public function deactivate(int $id): bool;
}
