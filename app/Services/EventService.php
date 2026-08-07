<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

use HPucca\Platform\Models\Event;
use HPucca\Platform\Repositories\EventRepositoryContract;

final readonly class EventService
{
    public function __construct(
        private EventRepositoryContract $events,
    ) {
    }

    /**
     * @return array{status: 'not_found'|'invalid_state'|'requeued', event: Event|null}
     */
    public function reprocess(int $eventId): array
    {
        $event = $this->events->findById($eventId);

        if (!$event instanceof Event) {
            return ['status' => 'not_found', 'event' => null];
        }

        if ($event->status !== 'failed') {
            return ['status' => 'invalid_state', 'event' => $event];
        }

        if (!$this->events->requeueFailed($event->id)) {
            return ['status' => 'invalid_state', 'event' => $event];
        }

        return ['status' => 'requeued', 'event' => $event];
    }
}
