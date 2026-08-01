<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

use HPucca\Platform\Models\Tenant;
use HPucca\Platform\Models\User;
use HPucca\Platform\Repositories\TenantRepository;
use HPucca\Platform\Repositories\UserRepository;

final readonly class UserService
{
    public function __construct(
        private UserRepository $users,
        private TenantRepository $tenants,
        private AuthService $auth,
    ) {
    }

    public function currentUser(): ?User
    {
        $userId = $this->auth->userId();

        if ($userId === null) {
            return null;
        }

        return $this->users->findActiveById($userId);
    }

    public function tenantFor(User $user): ?Tenant
    {
        return $this->tenants->findActiveById($user->tenantId);
    }
}
