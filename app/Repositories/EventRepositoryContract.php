<?php

declare(strict_types=1);

namespace HPucca\Platform\Repositories;

use DateTimeImmutable;
use HPucca\Platform\Models\Event;
use HPucca\Platform\Services\PublicCodeGenerator;

interface EventRepositoryContract
{
    /**
     * @param array<string, string> $filters
     * @return Event[]
     */
    public function paginate(array $filters, int $page, int $perPage): array;

    /**
     * @param array<string, string> $filters
     */
    public function count(array $filters): int;

    public function findById(int $id): ?Event;

    public function findDuplicate(int $sourceId, string $externalId, string $eventType): ?Event;

    public function reserveNextPending(): ?Event;

    public function markProcessed(int $eventId, DateTimeImmutable $processedAt): bool;

    public function markFailed(int $eventId, string $sanitizedError, DateTimeImmutable $failedAt): bool;

    public function requeueFailed(int $eventId): bool;

    public function recoverStaleProcessing(int $timeoutMinutes): int;

    public function scheduleRetry(int $eventId, DateTimeImmutable $availableAt, string $sanitizedError): bool;

    /**
     * @param array{tenant_id: int, integration_source_id: int, event_type: string, external_id: string, payload: string, occurred_at: string|null} $data
     * @return array{event: Event, duplicate: bool}
     */
    public function create(array $data, PublicCodeGenerator $codes): array;
}
