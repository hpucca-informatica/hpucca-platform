<?php

declare(strict_types=1);

namespace HPucca\Platform\Models;

final readonly class IntegrationDestination
{
    public function __construct(
        public int $id,
        public string $code,
        public int $tenantId,
        public string $tenantName,
        public string $tenantStatus,
        public string $name,
        public string $slug,
        public string $type,
        public string $status,
        public string $config,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
