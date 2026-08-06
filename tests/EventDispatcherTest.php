<?php

declare(strict_types=1);

use HPucca\Platform\Models\Event;
use HPucca\Platform\Repositories\EventRepositoryContract;
use HPucca\Platform\Services\EventDispatcher;
use HPucca\Platform\Services\EventProcessorContract;
use HPucca\Platform\Services\PublicCodeGenerator;
use HPucca\Platform\Services\SimulatedEventProcessor;

require dirname(__DIR__) . '/vendor/autoload.php';

final class EventDispatcherMemoryRepository implements EventRepositoryContract
{
    /**
     * @var array<int, Event>
     */
    public array $events = [];

    /**
     * @var array<int, string>
     */
    public array $tenantStatuses = [];

    /**
     * @var array<int, string>
     */
    public array $sourceStatuses = [];

    public function paginate(array $filters, int $page, int $perPage): array
    {
        return array_values($this->events);
    }

    public function count(array $filters): int
    {
        return count($this->events);
    }

    public function findById(int $id): ?Event
    {
        return $this->events[$id] ?? null;
    }

    public function findDuplicate(int $sourceId, string $externalId, string $eventType): ?Event
    {
        foreach ($this->events as $event) {
            if ($event->integrationSourceId === $sourceId && $event->externalId === $externalId && $event->eventType === $eventType) {
                return $event;
            }
        }

        return null;
    }

    public function reserveNextPending(): ?Event
    {
        $eligible = array_filter($this->events, function (Event $event): bool {
            return $event->status === 'pending'
                && strtotime($event->availableAt) <= time()
                && ($this->tenantStatuses[$event->tenantId] ?? 'active') === 'active'
                && ($this->sourceStatuses[$event->integrationSourceId] ?? 'active') === 'active';
        });

        usort($eligible, static fn (Event $left, Event $right): int => [$left->availableAt, $left->id] <=> [$right->availableAt, $right->id]);
        $event = $eligible[0] ?? null;

        if (!$event instanceof Event) {
            return null;
        }

        $reserved = self::copy($event, status: 'processing', attempts: $event->attempts + 1);
        $this->events[$event->id] = $reserved;

        return $reserved;
    }

    public function markProcessed(int $eventId, DateTimeImmutable $processedAt): bool
    {
        $event = $this->events[$eventId] ?? null;

        if (!$event instanceof Event || $event->status !== 'processing') {
            return false;
        }

        $this->events[$eventId] = self::copy(
            $event,
            status: 'processed',
            processedAt: $processedAt->format('Y-m-d H:i:s'),
            failedAt: null,
            lastError: null,
        );

        return true;
    }

    public function markFailed(int $eventId, string $sanitizedError, DateTimeImmutable $failedAt): bool
    {
        $event = $this->events[$eventId] ?? null;

        if (!$event instanceof Event || $event->status !== 'processing') {
            return false;
        }

        $this->events[$eventId] = self::copy(
            $event,
            status: 'failed',
            processedAt: null,
            failedAt: $failedAt->format('Y-m-d H:i:s'),
            lastError: substr($sanitizedError, 0, 1000),
        );

        return true;
    }

    public function create(array $data, PublicCodeGenerator $codes): array
    {
        throw new RuntimeException('Not used in dispatcher tests.');
    }

    private static function copy(
        Event $event,
        ?string $status = null,
        ?int $attempts = null,
        ?string $processedAt = null,
        ?string $failedAt = null,
        ?string $lastError = null,
    ): Event {
        return new Event(
            $event->id,
            $event->code,
            $event->tenantId,
            $event->tenantName,
            $event->integrationSourceId,
            $event->integrationSourceName,
            $event->eventType,
            $event->externalId,
            $event->payload,
            $status ?? $event->status,
            $attempts ?? $event->attempts,
            $event->availableAt,
            $processedAt,
            $failedAt,
            $lastError,
            $event->occurredAt,
            $event->receivedAt,
            $event->createdAt,
            date('Y-m-d H:i:s'),
        );
    }
}

final readonly class ThrowingEventProcessor implements EventProcessorContract
{
    public function process(Event $event): void
    {
        throw new RuntimeException('pgsql:host=secret password=hidden hpk_live_secret stack trace payload ' . $event->payload);
    }
}

function dispatcherEvent(
    int $id,
    string $status = 'pending',
    string $availableAt = '2026-01-01 00:00:00',
    int $attempts = 0,
    string $payload = '{"name":"Teste"}',
): Event {
    return new Event(
        $id,
        PublicCodeGenerator::format('EVT', $id),
        1,
        'Empresa A',
        1,
        'Fonte A',
        'lead.created',
        'EXT-' . $id,
        $payload,
        $status,
        $attempts,
        $availableAt,
        null,
        null,
        null,
        null,
        '2026-01-01 00:00:00',
        '2026-01-01 00:00:00',
        '2026-01-01 00:00:00',
    );
}

$repository = new EventDispatcherMemoryRepository();
$dispatcher = new EventDispatcher($repository, new SimulatedEventProcessor());
assert($dispatcher->dispatchOnce()['status'] === 'idle');

$repository->events = [1 => dispatcherEvent(1)];
$processed = $dispatcher->dispatchOnce();
assert($processed['status'] === 'processed');
assert($repository->events[1]->status === 'processed');
assert($repository->events[1]->attempts === 1);
assert($repository->events[1]->processedAt !== null);
assert($repository->events[1]->failedAt === null);
assert($repository->events[1]->lastError === null);
assert($repository->events[1]->payload === '{"name":"Teste"}');
assert($repository->events[1]->code === 'EVT000001');

$repository->events = [2 => dispatcherEvent(2, availableAt: '2099-01-01 00:00:00')];
assert($dispatcher->dispatchOnce()['status'] === 'idle');

$repository->events = [3 => dispatcherEvent(3, status: 'processed')];
assert($dispatcher->dispatchOnce()['status'] === 'idle');

$repository->events = [4 => dispatcherEvent(4, status: 'failed')];
assert($dispatcher->dispatchOnce()['status'] === 'idle');

$repository->events = [5 => dispatcherEvent(5)];
$repository->tenantStatuses = [1 => 'inactive'];
assert($dispatcher->dispatchOnce()['status'] === 'idle');

$repository->tenantStatuses = [1 => 'active'];
$repository->sourceStatuses = [1 => 'inactive'];
assert($dispatcher->dispatchOnce()['status'] === 'idle');

$repository->sourceStatuses = [1 => 'active'];
$repository->events = [6 => dispatcherEvent(6, attempts: 2, payload: '{"simulate_failure":true}')];
$failed = $dispatcher->dispatchOnce();
assert($failed['status'] === 'failed');
assert($repository->events[6]->status === 'failed');
assert($repository->events[6]->attempts === 3);
assert($repository->events[6]->processedAt === null);
assert($repository->events[6]->failedAt !== null);
assert($repository->events[6]->lastError === 'Event processing failed.');

$repository->events = [7 => dispatcherEvent(7)];
$unsafeDispatcher = new EventDispatcher($repository, new ThrowingEventProcessor());
assert($unsafeDispatcher->dispatchOnce()['status'] === 'failed');
assert(!str_contains($repository->events[7]->lastError ?? '', 'pgsql:'));
assert(!str_contains($repository->events[7]->lastError ?? '', 'password'));
assert(!str_contains($repository->events[7]->lastError ?? '', 'hpk_live'));
assert(!str_contains($repository->events[7]->lastError ?? '', '{"name":"Teste"}'));

$repository->events = [8 => dispatcherEvent(8)];
$firstDispatcher = new EventDispatcher($repository, new SimulatedEventProcessor());
$secondDispatcher = new EventDispatcher($repository, new SimulatedEventProcessor());
assert($firstDispatcher->dispatchOnce()['status'] === 'processed');
assert($secondDispatcher->dispatchOnce()['status'] === 'idle');

$repositorySql = (string) file_get_contents(dirname(__DIR__) . '/app/Repositories/EventRepository.php');
assert(str_contains($repositorySql, 'FOR UPDATE OF e SKIP LOCKED'));
assert(str_contains($repositorySql, "AND status = 'processing'"));
assert(str_contains($repositorySql, "AND status = 'pending'"));

$cli = (string) file_get_contents(dirname(__DIR__) . '/bin/dispatch-events.php');
assert(str_contains($cli, 'No pending event available.'));
assert(str_contains($cli, 'Processed event %s.'));
assert(str_contains($cli, 'Failed event %s.'));
assert(str_contains($cli, 'Event dispatcher failed.'));
assert(str_contains($cli, 'Invalid arguments.'));
assert(str_contains($cli, 'Usage:'));
assert(str_contains($cli, 'php bin/dispatch-events.php [--limit=1]'));
assert(str_contains($cli, "\$arguments !== [] && \$arguments !== ['--limit=1']"));
assert(strpos($cli, "\$arguments !== [] && \$arguments !== ['--limit=1']") < strpos($cli, "require dirname(__DIR__) . '/vendor/autoload.php';"));
assert(!str_contains($cli, 'getTrace'));
assert(!str_contains($cli, 'payload'));
assert(!str_contains($cli, 'password'));
assert(!str_contains($cli, 'hpk_live'));

$allowedCliArguments = static fn (array $arguments): bool => $arguments === [] || $arguments === ['--limit=1'];
assert($allowedCliArguments([]));
assert($allowedCliArguments(['--limit=1']));
assert(!$allowedCliArguments(['--limit=2']));
assert(!$allowedCliArguments(['--foo']));
assert(!$allowedCliArguments(['abc']));

$cliPath = dirname(__DIR__) . '/bin/dispatch-events.php';
$invalidCases = [
    '--limit=2',
    '--foo',
    'abc',
];

foreach ($invalidCases as $invalidArgument) {
    $command = sprintf(
        'php %s %s 2>&1',
        escapeshellarg($cliPath),
        escapeshellarg($invalidArgument),
    );
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);
    $body = implode(PHP_EOL, $output);

    assert($exitCode === 1);
    assert(str_contains($body, 'Invalid arguments.'));
    assert(str_contains($body, 'Usage:'));
    assert(!str_contains($body, 'Event dispatcher failed.'));
    assert(!str_contains($body, 'No pending event available.'));
    assert(!str_contains($body, 'Processed event'));
    assert(!str_contains($body, 'Failed event'));
    assert(!str_contains($body, 'Stack trace'));
    assert(!str_contains($body, 'payload'));
    assert(!str_contains($body, 'password'));
    assert(!str_contains($body, 'hpk_live'));
}

echo 'EventDispatcherTest passed.' . PHP_EOL;
