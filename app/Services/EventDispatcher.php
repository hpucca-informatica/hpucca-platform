<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

use DateTimeImmutable;
use DateTimeZone;
use HPucca\Platform\Core\Config;
use HPucca\Platform\Models\Event;
use HPucca\Platform\Repositories\EventRepositoryContract;
use RuntimeException;
use Throwable;

final readonly class EventDispatcher
{
    public function __construct(
        private EventRepositoryContract $events,
        private EventProcessorContract $processor,
    ) {
    }

    /**
     * @return array{status: 'idle'|'processed'|'failed', event: Event|null}
     */
    public function dispatchOnce(): array
    {
        $event = $this->events->reserveNextPending();

        if (!$event instanceof Event) {
            return ['status' => 'idle', 'event' => null];
        }

        try {
            $this->processor->process($event);

            if (!$this->events->markProcessed($event->id, $this->now())) {
                throw new RuntimeException('Event transition failed.');
            }

            return ['status' => 'processed', 'event' => $event];
        } catch (Throwable) {
            $this->events->markFailed($event->id, $this->sanitizeError(), $this->now());

            return ['status' => 'failed', 'event' => $event];
        }
    }

    private function sanitizeError(): string
    {
        return 'Event processing failed.';
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone((string) Config::get('app.timezone', date_default_timezone_get())));
    }
}
