<?php

declare(strict_types=1);

namespace HPucca\Platform\Models;

final readonly class User
{
    public function __construct(
        public int $id,
        public string $code,
        public int $tenantId,
        public string $login,
        public ?string $email,
        public string $password,
        public string $name,
        public string $type,
        public string $status,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
