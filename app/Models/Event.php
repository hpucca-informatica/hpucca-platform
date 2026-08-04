<?php

declare(strict_types=1);

namespace HPucca\Platform\Models;

final readonly class Event
{
    public function __construct(
        public int $id,
        public string $code,
        public int $tenantId,
        public string $tenantName,
        public int $integrationSourceId,
        public string $integrationSourceName,
        public string $eventType,
        public string $externalId,
        public string $payload,
        public string $status,
        public int $attempts,
        public string $availableAt,
        public ?string $processedAt,
        public ?string $failedAt,
        public ?string $lastError,
        public ?string $occurredAt,
        public string $receivedAt,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
