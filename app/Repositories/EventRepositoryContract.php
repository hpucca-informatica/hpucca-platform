<?php

declare(strict_types=1);

namespace HPucca\Platform\Repositories;

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

    /**
     * @param array{tenant_id: int, integration_source_id: int, event_type: string, external_id: string, payload: string, occurred_at: string|null} $data
     * @return array{event: Event, duplicate: bool}
     */
    public function create(array $data, PublicCodeGenerator $codes): array;
}
