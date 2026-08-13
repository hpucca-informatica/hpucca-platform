<?php

declare(strict_types=1);

use HPucca\Platform\Models\Event;
use HPucca\Platform\Repositories\EventRepositoryContract;
use HPucca\Platform\Services\EventDispatcher;
use HPucca\Platform\Services\EventProcessorContract;
use HPucca\Platform\Services\PermanentProcessingException;
use HPucca\Platform\Services\PublicCodeGenerator;
use HPucca\Platform\Services\RetryPolicy;
use HPucca\Platform\Services\SimulatedEventProcessor;
use HPucca\Platform\Services\TransientProcessingException;

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

    public function scheduleRetry(int $eventId, DateTimeImmutable $availableAt, string $sanitizedError): bool
    {
        $event = $this->events[$eventId] ?? null;

        if (!$event instanceof Event || $event->status !== 'processing') {
            return false;
        }

        $this->events[$eventId] = self::copy(
            $event,
            status: 'pending',
            availableAt: $availableAt->format('Y-m-d H:i:s'),
            processedAt: null,
            failedAt: null,
            lastError: substr($sanitizedError, 0, 1000),
        );

        return true;
    }

    public function requeueFailed(int $eventId): bool
    {
        $event = $this->events[$eventId] ?? null;

        if (!$event instanceof Event || $event->status !== 'failed') {
            return false;
        }

        $this->events[$eventId] = self::copy(
            $event,
            status: 'pending',
            availableAt: date('Y-m-d H:i:s'),
            processedAt: null,
            failedAt: null,
            lastError: null,
        );

        return true;
    }

    public function recoverStaleProcessing(int $timeoutMinutes): int
    {
        $recovered = 0;
        $cutoff = time() - (max(1, $timeoutMinutes) * 60);

        foreach ($this->events as $event) {
            if ($event->status !== 'processing' || strtotime($event->updatedAt) >= $cutoff) {
                continue;
            }

            $this->events[$event->id] = self::copy(
                $event,
                status: 'pending',
                availableAt: date('Y-m-d H:i:s'),
                processedAt: null,
                failedAt: null,
                lastError: null,
            );
            $recovered++;
        }

        return $recovered;
    }

    public function makeEligible(int $eventId): void
    {
        $event = $this->events[$eventId] ?? null;

        if ($event instanceof Event) {
            $this->events[$eventId] = self::copy($event, availableAt: date('Y-m-d H:i:s', time() - 60));
        }
    }

    public function create(array $data, PublicCodeGenerator $codes): array
    {
        throw new RuntimeException('Not used in dispatcher tests.');
    }

    private static function copy(
        Event $event,
        ?string $status = null,
        ?int $attempts = null,
        ?string $availableAt = null,
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
            $availableAt ?? $event->availableAt,
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

final readonly class TransientEventProcessor implements EventProcessorContract
{
    public function process(Event $event): void
    {
        throw new TransientProcessingException('Temporary downstream outage with payload ' . $event->payload);
    }
}

final readonly class PermanentEventProcessor implements EventProcessorContract
{
    public function process(Event $event): void
    {
        throw new PermanentProcessingException('Permanent validation error with payload ' . $event->payload);
    }
}

function dispatcherEvent(
    int $id,
    string $status = 'pending',
    string $availableAt = '2026-01-01 00:00:00',
    int $attempts = 0,
    string $payload = '{"name":"Teste"}',
    string $updatedAt = '2026-01-01 00:00:00',
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
        $updatedAt,
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
assert($repository->events[6]->lastError === 'Transient event processing failure.');

$repository->events = [7 => dispatcherEvent(7)];
$unsafeDispatcher = new EventDispatcher($repository, new ThrowingEventProcessor());
assert($unsafeDispatcher->dispatchOnce()['status'] === 'failed');
assert($repository->events[7]->lastError === 'Unexpected event processing failure.');
assert(!str_contains($repository->events[7]->lastError ?? '', 'pgsql:'));
assert(!str_contains($repository->events[7]->lastError ?? '', 'password'));
assert(!str_contains($repository->events[7]->lastError ?? '', 'hpk_live'));
assert(!str_contains($repository->events[7]->lastError ?? '', '{"name":"Teste"}'));

$repository->events = [8 => dispatcherEvent(8)];
$firstDispatcher = new EventDispatcher($repository, new SimulatedEventProcessor());
$secondDispatcher = new EventDispatcher($repository, new SimulatedEventProcessor());
assert($firstDispatcher->dispatchOnce()['status'] === 'processed');
assert($secondDispatcher->dispatchOnce()['status'] === 'idle');

$repository->events = [
    9 => dispatcherEvent(9),
    10 => dispatcherEvent(10),
    11 => dispatcherEvent(11),
];
$batch = $dispatcher->dispatchBatch(2);
assert($batch === ['processed' => 2, 'retried' => 0, 'failed' => 0, 'total' => 2, 'idle' => false]);
assert($repository->events[9]->status === 'processed');
assert($repository->events[10]->status === 'processed');
assert($repository->events[11]->status === 'pending');

$repository->events = [
    12 => dispatcherEvent(12),
    13 => dispatcherEvent(13, payload: '{"simulate_failure":"permanent"}'),
    14 => dispatcherEvent(14),
];
$mixedBatch = $dispatcher->dispatchBatch(10);
assert($mixedBatch === ['processed' => 2, 'retried' => 0, 'failed' => 1, 'total' => 3, 'idle' => true]);
assert($repository->events[12]->status === 'processed');
assert($repository->events[13]->status === 'failed');
assert($repository->events[14]->status === 'processed');

$repository->events = [
    15 => dispatcherEvent(15),
    16 => dispatcherEvent(16),
];
$emptyBatch = $dispatcher->dispatchBatch(10);
assert($emptyBatch === ['processed' => 2, 'retried' => 0, 'failed' => 0, 'total' => 2, 'idle' => true]);

$oldProcessing = dispatcherEvent(17, status: 'processing', attempts: 4, updatedAt: date('Y-m-d H:i:s', time() - 3600));
$recentProcessing = dispatcherEvent(18, status: 'processing', attempts: 5, updatedAt: date('Y-m-d H:i:s'));
$repository->events = [17 => $oldProcessing, 18 => $recentProcessing];
assert($repository->recoverStaleProcessing(15) === 1);
assert($repository->events[17]->status === 'pending');
assert($repository->events[17]->attempts === 4);
assert($repository->events[18]->status === 'processing');

$recoveredBatch = $dispatcher->dispatchBatch(1);
assert($recoveredBatch === ['processed' => 1, 'retried' => 0, 'failed' => 0, 'total' => 1, 'idle' => false]);
assert($repository->events[17]->status === 'processed');
assert($repository->events[17]->attempts === 5);

$retryPolicy = new RetryPolicy(3, [60, 300]);
$now = new DateTimeImmutable('2026-01-01 00:00:00');
assert($retryPolicy->shouldRetry(1));
assert($retryPolicy->shouldRetry(2));
assert(!$retryPolicy->shouldRetry(3));
assert($retryPolicy->delaySeconds(1) === 60);
assert($retryPolicy->delaySeconds(2) === 300);
assert($retryPolicy->nextAvailableAt(1, $now)->format('Y-m-d H:i:s') === '2026-01-01 00:01:00');
assert((new RetryPolicy(4, [60, 300]))->delaySeconds(3) === 300);

foreach ([[0, [60]], [11, [60]], [3, []], [3, [-1]]] as $invalidPolicy) {
    try {
        new RetryPolicy($invalidPolicy[0], $invalidPolicy[1]);
        assert(false);
    } catch (InvalidArgumentException) {
        assert(true);
    }
}

foreach (['', '-1', '60,-1', 'abc'] as $invalidDelays) {
    try {
        RetryPolicy::fromConfig(3, $invalidDelays);
        assert(false);
    } catch (InvalidArgumentException) {
        assert(true);
    }
}

$retryRepository = new EventDispatcherMemoryRepository();
$retryRepository->events = [19 => dispatcherEvent(19, payload: '{"simulate_failure":"transient"}')];
$retryDispatcher = new EventDispatcher($retryRepository, new SimulatedEventProcessor(), new RetryPolicy(3, [60, 300]));
$retried = $retryDispatcher->dispatchOnce();
assert($retried['status'] === 'retried');
assert($retryRepository->events[19]->status === 'pending');
assert($retryRepository->events[19]->attempts === 1);
assert(strtotime($retryRepository->events[19]->availableAt) > time());
assert($retryRepository->events[19]->processedAt === null);
assert($retryRepository->events[19]->failedAt === null);
assert($retryRepository->events[19]->lastError === 'Transient event processing failure.');
assert($retryRepository->events[19]->payload === '{"simulate_failure":"transient"}');
assert($retryRepository->events[19]->code === 'EVT000019');
assert($retryDispatcher->dispatchOnce()['status'] === 'idle');

$retryRepository->events[19] = dispatcherEvent(19, attempts: 1, payload: '{"simulate_failure":"transient"}');
$secondRetry = $retryDispatcher->dispatchOnce();
assert($secondRetry['status'] === 'retried');
assert($retryRepository->events[19]->status === 'pending');
assert($retryRepository->events[19]->attempts === 2);

$retryRepository->events[19] = dispatcherEvent(19, attempts: 2, payload: '{"simulate_failure":"transient"}');
$terminalTransient = $retryDispatcher->dispatchOnce();
assert($terminalTransient['status'] === 'failed');
assert($retryRepository->events[19]->status === 'failed');
assert($retryRepository->events[19]->attempts === 3);
assert($retryRepository->events[19]->lastError === 'Transient event processing failure.');

$retryRepository->events = [20 => dispatcherEvent(20)];
$transientProcessorDispatcher = new EventDispatcher($retryRepository, new TransientEventProcessor(), new RetryPolicy(3, [60, 300]));
assert($transientProcessorDispatcher->dispatchOnce()['status'] === 'retried');
assert($retryRepository->events[20]->status === 'pending');
assert($retryRepository->events[20]->lastError === 'Transient event processing failure.');

$retryRepository->events = [21 => dispatcherEvent(21)];
$permanentProcessorDispatcher = new EventDispatcher($retryRepository, new PermanentEventProcessor(), new RetryPolicy(3, [60, 300]));
assert($permanentProcessorDispatcher->dispatchOnce()['status'] === 'failed');
assert($retryRepository->events[21]->status === 'failed');
assert($retryRepository->events[21]->attempts === 1);
assert($retryRepository->events[21]->lastError === 'Permanent event processing failure.');

$retryRepository->events = [
    22 => dispatcherEvent(22),
    23 => dispatcherEvent(23, payload: '{"simulate_failure":"transient"}'),
    24 => dispatcherEvent(24),
];
$retryBatch = $retryDispatcher->dispatchBatch(10);
assert($retryBatch === ['processed' => 2, 'retried' => 1, 'failed' => 0, 'total' => 3, 'idle' => true]);
assert($retryRepository->events[23]->status === 'pending');

$flowRepository = new EventDispatcherMemoryRepository();
$flowPayload = '{"simulate_failure":"transient","name":"Integrated retry"}';
$flowRepository->events = [25 => dispatcherEvent(25, payload: $flowPayload)];
$flowDispatcher = new EventDispatcher($flowRepository, new SimulatedEventProcessor(), new RetryPolicy(3, [60, 300]));
$flowCode = $flowRepository->events[25]->code;
$flowExternalId = $flowRepository->events[25]->externalId;
$flowTenantId = $flowRepository->events[25]->tenantId;
$flowSourceId = $flowRepository->events[25]->integrationSourceId;

$firstFlowRetry = $flowDispatcher->dispatchOnce();
$firstAvailableAt = strtotime($flowRepository->events[25]->availableAt);
assert($firstFlowRetry['status'] === 'retried');
assert($flowRepository->events[25]->status === 'pending');
assert($flowRepository->events[25]->attempts === 1);
assert($firstAvailableAt >= time() + 55 && $firstAvailableAt <= time() + 65);
assert($flowRepository->events[25]->code === $flowCode);
assert($flowRepository->events[25]->payload === $flowPayload);
assert($flowRepository->events[25]->externalId === $flowExternalId);
assert($flowRepository->events[25]->tenantId === $flowTenantId);
assert($flowRepository->events[25]->integrationSourceId === $flowSourceId);
assert($flowRepository->events[25]->processedAt === null);
assert($flowRepository->events[25]->failedAt === null);
assert($flowRepository->events[25]->lastError === 'Transient event processing failure.');

$flowRepository->makeEligible(25);
$secondFlowRetry = $flowDispatcher->dispatchOnce();
$secondAvailableAt = strtotime($flowRepository->events[25]->availableAt);
assert($secondFlowRetry['status'] === 'retried');
assert($flowRepository->events[25]->status === 'pending');
assert($flowRepository->events[25]->attempts === 2);
assert($secondAvailableAt >= time() + 295 && $secondAvailableAt <= time() + 305);
assert($flowRepository->events[25]->code === $flowCode);
assert($flowRepository->events[25]->payload === $flowPayload);
assert($flowRepository->events[25]->externalId === $flowExternalId);
assert($flowRepository->events[25]->tenantId === $flowTenantId);
assert($flowRepository->events[25]->integrationSourceId === $flowSourceId);
assert($flowRepository->events[25]->processedAt === null);
assert($flowRepository->events[25]->failedAt === null);
assert($flowRepository->events[25]->lastError === 'Transient event processing failure.');

$flowRepository->makeEligible(25);
$finalFlowFailure = $flowDispatcher->dispatchOnce();
assert($finalFlowFailure['status'] === 'failed');
assert($flowRepository->events[25]->status === 'failed');
assert($flowRepository->events[25]->attempts === 3);
assert($flowRepository->events[25]->code === $flowCode);
assert($flowRepository->events[25]->payload === $flowPayload);
assert($flowRepository->events[25]->externalId === $flowExternalId);
assert($flowRepository->events[25]->tenantId === $flowTenantId);
assert($flowRepository->events[25]->integrationSourceId === $flowSourceId);
assert($flowRepository->events[25]->processedAt === null);
assert($flowRepository->events[25]->failedAt !== null);
assert($flowRepository->events[25]->lastError === 'Transient event processing failure.');

$repositorySql = (string) file_get_contents(dirname(__DIR__) . '/app/Repositories/EventRepository.php');
assert(str_contains($repositorySql, 'FOR UPDATE OF e SKIP LOCKED'));
assert(str_contains($repositorySql, "AND status = 'processing'"));
assert(str_contains($repositorySql, "AND status = 'pending'"));
assert(str_contains($repositorySql, 'recoverStaleProcessing'));
assert(str_contains($repositorySql, "updated_at < CURRENT_TIMESTAMP - (CAST(:timeout_minutes AS INTEGER) * INTERVAL '1 minute')"));

$cli = (string) file_get_contents(dirname(__DIR__) . '/bin/dispatch-events.php');
assert(str_contains($cli, 'dispatchLimit'));
assert(str_contains($cli, 'Recovered stale events: %d'));
assert(str_contains($cli, 'Processed: %d'));
assert(str_contains($cli, 'Retried: %d'));
assert(str_contains($cli, 'Failed: %d'));
assert(str_contains($cli, 'Total: %d'));
assert(str_contains($cli, 'Dispatcher already running.'));
assert(str_contains($cli, 'Event dispatcher failed.'));
assert(str_contains($cli, 'Invalid arguments.'));
assert(str_contains($cli, 'Invalid limit.'));
assert(str_contains($cli, 'Usage:'));
assert(str_contains($cli, 'php bin/dispatch-events.php [--limit=N]'));
assert(!str_contains($cli, 'getTrace'));
assert(!str_contains($cli, 'payload'));
assert(!str_contains($cli, 'password'));
assert(!str_contains($cli, 'hpk_live'));

$allowedCliArguments = static fn (array $arguments): bool => $arguments === [] || preg_match('/^--limit=([1-9][0-9]*)$/', $arguments[0] ?? '') === 1;
assert($allowedCliArguments([]));
assert($allowedCliArguments(['--limit=1']));
assert($allowedCliArguments(['--limit=25']));
assert(!$allowedCliArguments(['--foo']));
assert(!$allowedCliArguments(['abc']));

$cliPath = dirname(__DIR__) . '/bin/dispatch-events.php';
$invalidCases = [
    '--limit=0',
    '--limit=101',
    '--foo',
    'abc',
];

foreach ($invalidCases as $invalidArgument) {
    $command = sprintf(
        '%s %s %s 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($cliPath),
        escapeshellarg($invalidArgument),
    );
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);
    $body = implode(PHP_EOL, $output);

    assert($exitCode === 2);
    assert(str_contains($body, 'Invalid arguments.') || str_contains($body, 'Invalid limit.'));
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
