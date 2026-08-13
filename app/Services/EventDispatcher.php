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
        private ?RetryPolicy $retryPolicy = null,
    ) {
    }

    /**
     * @return array{status: 'idle'|'processed'|'retried'|'failed', event: Event|null}
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
        } catch (TransientProcessingException) {
            if ($this->retryPolicy()->shouldRetry($event->attempts)) {
                $availableAt = $this->retryPolicy()->nextAvailableAt($event->attempts, $this->now());

                if ($this->events->scheduleRetry($event->id, $availableAt, $this->transientError())) {
                    return ['status' => 'retried', 'event' => $event];
                }
            }

            $this->events->markFailed($event->id, $this->transientError(), $this->now());

            return ['status' => 'failed', 'event' => $event];
        } catch (PermanentProcessingException) {
            $this->events->markFailed($event->id, $this->permanentError(), $this->now());

            return ['status' => 'failed', 'event' => $event];
        } catch (Throwable) {
            $this->events->markFailed($event->id, $this->unexpectedError(), $this->now());

            return ['status' => 'failed', 'event' => $event];
        }
    }

    /**
     * @return array{processed: int, retried: int, failed: int, total: int, idle: bool}
     */
    public function dispatchBatch(int $limit): array
    {
        $processed = 0;
        $retried = 0;
        $failed = 0;
        $limit = max(1, $limit);

        for ($index = 0; $index < $limit; $index++) {
            $result = $this->dispatchOnce();

            if ($result['status'] === 'idle') {
                return [
                    'processed' => $processed,
                    'retried' => $retried,
                    'failed' => $failed,
                    'total' => $processed + $retried + $failed,
                    'idle' => true,
                ];
            }

            if ($result['status'] === 'processed') {
                $processed++;
                continue;
            }

            if ($result['status'] === 'retried') {
                $retried++;
                continue;
            }

            $failed++;
        }

        return [
            'processed' => $processed,
            'retried' => $retried,
            'failed' => $failed,
            'total' => $processed + $retried + $failed,
            'idle' => false,
        ];
    }

    private function retryPolicy(): RetryPolicy
    {
        return $this->retryPolicy ?? RetryPolicy::fromConfig(
            (int) Config::get('app.event_retry_max_attempts', 3),
            (string) Config::get('app.event_retry_delays_seconds', '60,300'),
        );
    }

    private function transientError(): string
    {
        return 'Transient event processing failure.';
    }

    private function permanentError(): string
    {
        return 'Permanent event processing failure.';
    }

    private function unexpectedError(): string
    {
        return 'Unexpected event processing failure.';
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone((string) Config::get('app.timezone', date_default_timezone_get())));
    }
}
