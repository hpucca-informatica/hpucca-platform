<?php

declare(strict_types=1);

use HPucca\Platform\Models\Event;
use HPucca\Platform\Models\IntegrationSource;
use HPucca\Platform\Repositories\EventRepositoryContract;
use HPucca\Platform\Repositories\IntegrationSourceRepositoryContract;
use HPucca\Platform\Services\ApiKeyService;
use HPucca\Platform\Services\EventIngestionService;
use HPucca\Platform\Services\PublicCodeGenerator;
use HPucca\Platform\Services\SensitivePayloadValidator;

require dirname(__DIR__) . '/vendor/autoload.php';

final class EventIngestionSourceRepository implements IntegrationSourceRepositoryContract
{
    /**
     * @param IntegrationSource[] $sources
     */
    public function __construct(
        private array $sources,
        public int $touches = 0,
    ) {
    }

    public function paginate(string $search, ?int $tenantId, int $page, int $perPage): array
    {
        return $this->sources;
    }

    public function count(string $search, ?int $tenantId): int
    {
        return count($this->sources);
    }

    public function findById(int $id): ?IntegrationSource
    {
        foreach ($this->sources as $source) {
            if ($source->id === $id) {
                return $source;
            }
        }

        return null;
    }

    public function findByTenantAndSlug(int $tenantId, string $slug): ?IntegrationSource
    {
        foreach ($this->sources as $source) {
            if ($source->tenantId === $tenantId && $source->slug === $slug) {
                return $source;
            }
        }

        return null;
    }

    public function sourcesForAuthentication(): array
    {
        return $this->sources;
    }

    public function create(array $data, PublicCodeGenerator $codes): IntegrationSource
    {
        throw new RuntimeException('Not used.');
    }

    public function update(int $id, array $data): IntegrationSource
    {
        throw new RuntimeException('Not used.');
    }

    public function activate(int $id): bool
    {
        return true;
    }

    public function deactivate(int $id): bool
    {
        return true;
    }

    public function touchLastUsedAt(int $id): void
    {
        $this->touches++;
    }
}

final class EventIngestionEventRepository implements EventRepositoryContract
{
    /**
     * @var Event[]
     */
    public array $events = [];

    public function paginate(array $filters, int $page, int $perPage): array
    {
        return $this->events;
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
        throw new RuntimeException('Not used.');
    }

    public function markProcessed(int $eventId, DateTimeImmutable $processedAt): bool
    {
        throw new RuntimeException('Not used.');
    }

    public function markFailed(int $eventId, string $sanitizedError, DateTimeImmutable $failedAt): bool
    {
        throw new RuntimeException('Not used.');
    }

    public function scheduleRetry(int $eventId, DateTimeImmutable $availableAt, string $sanitizedError): bool
    {
        throw new RuntimeException('Not used.');
    }

    public function requeueFailed(int $eventId): bool
    {
        throw new RuntimeException('Not used.');
    }

    public function recoverStaleProcessing(int $timeoutMinutes): int
    {
        throw new RuntimeException('Not used.');
    }

    public function create(array $data, PublicCodeGenerator $codes): array
    {
        $duplicate = $this->findDuplicate(
            $data['integration_source_id'],
            $data['external_id'],
            $data['event_type'],
        );

        if ($duplicate instanceof Event) {
            return ['event' => $duplicate, 'duplicate' => true];
        }

        $id = count($this->events) + 1;
        $event = new Event(
            $id,
            PublicCodeGenerator::format('EVT', $id),
            $data['tenant_id'],
            'Empresa A',
            $data['integration_source_id'],
            'Site',
            $data['event_type'],
            $data['external_id'],
            $data['payload'],
            'pending',
            0,
            '2026-01-01 00:00:00',
            null,
            null,
            null,
            $data['occurred_at'],
            '2026-01-01 00:00:00',
            '2026-01-01 00:00:00',
            '2026-01-01 00:00:00',
        );
        $this->events[$id] = $event;

        return ['event' => $event, 'duplicate' => false];
    }
}

$apiKeys = new ApiKeyService();
$apiKey = $apiKeys->generate();
$hash = $apiKeys->hash($apiKey);
$codes = (new ReflectionClass(PublicCodeGenerator::class))->newInstanceWithoutConstructor();

assert(str_starts_with($apiKey, ApiKeyService::PREFIX));
assert($apiKey !== $hash);
assert($apiKeys->verify($apiKey, $hash));
assert(!$apiKeys->verify('invalid', $hash));

$activeSource = new IntegrationSource(1, 'SRC000001', 1, 'Empresa A', 'active', 'Site', 'site', $hash, 'active', null, null, null, null, null, null, '2026-01-01', '2026-01-01');
$sourceRepository = new EventIngestionSourceRepository([$activeSource]);
$eventRepository = new EventIngestionEventRepository();
$service = new EventIngestionService($sourceRepository, $eventRepository, $codes, $apiKeys);

$missingKey = $service->ingest('', '{}');
assert($missingKey['httpStatus'] === 401);

$invalidKey = $service->ingest('hpk_live_invalid', '{}');
assert($invalidKey['httpStatus'] === 401);

$invalidJson = $service->ingest($apiKey, '{');
assert($invalidJson['httpStatus'] === 422);

$missingEvent = $service->ingest($apiKey, '{"external_id":"LEAD-001","data":{}}');
assert($missingEvent['httpStatus'] === 422);

$missingExternal = $service->ingest($apiKey, '{"event":"lead.created","data":{}}');
assert($missingExternal['httpStatus'] === 422);

$invalidData = $service->ingest($apiKey, '{"event":"lead.created","external_id":"LEAD-001","data":[]}');
assert($invalidData['httpStatus'] === 422);

$oversized = $service->ingest($apiKey, '{"event":"lead.created","external_id":"LEAD-001","data":{"blob":"' . str_repeat('a', EventIngestionService::MAX_PAYLOAD_BYTES) . '"}}');
assert($oversized['httpStatus'] === 422);

$sensitivePayloads = [
    '{"event":"lead.created","external_id":"SENSITIVE-001","data":{"password":"x"}}',
    '{"event":"lead.created","external_id":"SENSITIVE-002","data":{"senha":"x"}}',
    '{"event":"lead.created","external_id":"SENSITIVE-003","data":{"token":"x"}}',
    '{"event":"lead.created","external_id":"SENSITIVE-004","data":{"access_token":"x"}}',
    '{"event":"lead.created","external_id":"SENSITIVE-005","data":{"refresh_token":"x"}}',
    '{"event":"lead.created","external_id":"SENSITIVE-006","data":{"api_key":"x"}}',
    '{"event":"lead.created","external_id":"SENSITIVE-007","data":{"api-key":"x"}}',
    '{"event":"lead.created","external_id":"SENSITIVE-008","data":{"Api Key":"x"}}',
    '{"event":"lead.created","external_id":"SENSITIVE-009","data":{"secret":"x"}}',
    '{"event":"lead.created","external_id":"SENSITIVE-010","data":{"client_secret":"x"}}',
    '{"event":"lead.created","external_id":"SENSITIVE-011","data":{"authorization":"Bearer x"}}',
    '{"event":"lead.created","external_id":"SENSITIVE-012","data":{"customer":{"token":"y"}}}',
    '{"event":"lead.created","external_id":"SENSITIVE-013","data":{"items":[{"api_key":"hpk_live_hidden"}]}}',
    '{"event":"lead.created","external_id":"SENSITIVE-014","data":{"ToKeN":"mixed"}}',
    '{"event":"lead.created","external_id":"SENSITIVE-015","data":{"credential":"hpk_live_abc123"}}',
];

foreach ($sensitivePayloads as $sensitivePayload) {
    $rejected = $service->ingest($apiKey, $sensitivePayload);
    $responseBody = json_encode($rejected['body'], JSON_THROW_ON_ERROR);

    assert($rejected['httpStatus'] === 422);
    assert(($rejected['body']['error'] ?? '') === 'Payload contains sensitive data.');
    assert(!str_contains($responseBody, 'hpk_live_hidden'));
    assert(!str_contains($responseBody, 'hpk_live_abc123'));
    assert(!str_contains($responseBody, 'Bearer x'));
    assert(!str_contains($responseBody, 'mixed'));
    assert(count($eventRepository->events) === 0);
}

$validator = new SensitivePayloadValidator();
assert(in_array('password', $validator->sensitiveKeys(), true));

$accepted = $service->ingest($apiKey, '{"event":"lead.created","external_id":"LEAD-001","data":{"name":"Teste"}}');
assert($accepted['httpStatus'] === 202);
assert($accepted['body']['status'] === 'accepted');
assert($accepted['body']['event_code'] === 'EVT000001');
assert(count($eventRepository->events) === 1);
assert($eventRepository->events[1]->status === 'pending');
assert(!str_contains($eventRepository->events[1]->payload, 'hpk_live_'));

$duplicate = $service->ingest($apiKey, '{"event":"lead.created","external_id":"LEAD-001","data":{"name":"Outro"}}');
assert($duplicate['httpStatus'] === 200);
assert($duplicate['body']['status'] === 'duplicate');
assert($duplicate['body']['event_code'] === 'EVT000001');
assert(count($eventRepository->events) === 1);

$inactiveSource = new IntegrationSource(2, 'SRC000002', 1, 'Empresa A', 'active', 'Inactive', 'inactive', $hash, 'inactive', null, null, null, null, null, null, '2026-01-01', '2026-01-01');
$inactiveService = new EventIngestionService(new EventIngestionSourceRepository([$inactiveSource]), new EventIngestionEventRepository(), $codes, $apiKeys);
assert($inactiveService->ingest($apiKey, '{"event":"lead.created","external_id":"LEAD-002","data":{}}')['httpStatus'] === 403);

$inactiveTenant = new IntegrationSource(3, 'SRC000003', 2, 'Empresa B', 'inactive', 'Tenant inactive', 'tenant-inactive', $hash, 'active', null, null, null, null, null, null, '2026-01-01', '2026-01-01');
$inactiveTenantService = new EventIngestionService(new EventIngestionSourceRepository([$inactiveTenant]), new EventIngestionEventRepository(), $codes, $apiKeys);
assert($inactiveTenantService->ingest($apiKey, '{"event":"lead.created","external_id":"LEAD-003","data":{}}')['httpStatus'] === 403);

echo 'EventIngestionTest passed.' . PHP_EOL;
