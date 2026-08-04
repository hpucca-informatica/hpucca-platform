<?php

declare(strict_types=1);

namespace HPucca\Platform\Models;

final readonly class IntegrationSource
{
    public function __construct(
        public int $id,
        public string $code,
        public int $tenantId,
        public string $tenantName,
        public string $tenantStatus,
        public string $name,
        public string $slug,
        public string $apiKeyHash,
        public string $status,
        public ?string $lastUsedAt,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
