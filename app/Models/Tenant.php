<?php

declare(strict_types=1);

namespace HPucca\Platform\Models;

final readonly class Tenant
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public string $slug,
        public string $status,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
