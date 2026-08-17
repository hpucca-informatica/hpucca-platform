<?php

declare(strict_types=1);

use HPucca\Platform\Models\Event;
use HPucca\Platform\Services\HttpClientContract;
use HPucca\Platform\Services\HttpResponse;
use HPucca\Platform\Services\HttpTransportException;
use HPucca\Platform\Services\N8nEventProcessor;
use HPucca\Platform\Services\PermanentProcessingException;
use HPucca\Platform\Services\TransientProcessingException;
use HPucca\Platform\Services\WebhookUrlValidator;

require dirname(__DIR__) . '/vendor/autoload.php';

final class FakeN8nHttpClient implements HttpClientContract
{
    /**
     * @var array<string, mixed>|null
     */
    public ?array $lastPayload = null;
    public ?string $lastUrl = null;
    public ?int $lastTimeout = null;
    public int $calls = 0;

    public function __construct(
        public int $statusCode = 200,
        public bool $transportFailure = false,
    ) {
    }

    public function postJson(string $url, array $payload, int $timeoutSeconds): HttpResponse
    {
        $this->calls++;
        $this->lastUrl = $url;
        $this->lastPayload = $payload;
        $this->lastTimeout = $timeoutSeconds;

        if ($this->transportFailure) {
            throw new HttpTransportException('secret payload should not leak');
        }

        return new HttpResponse($this->statusCode, '{"ignored":"body"}');
    }
}

function n8nEvent(
    string $payload = '{"name":"Ana"}',
    ?string $destinationConfig = '{"webhook_url":"https://n8n.example.com/webhook/hpucca-events?token=hidden","timeout_seconds":7}',
    ?string $destinationStatus = 'active',
): Event {
    return new Event(
        12,
        'EVT000012',
        1,
        'Empresa A',
        2,
        'Fonte A',
        'lead.created',
        'LEAD-123',
        $payload,
        'processing',
        1,
        '2026-08-17 13:00:00-03',
        null,
        null,
        null,
        '2026-08-17T13:00:00-03:00',
        '2026-08-17 13:00:00-03',
        '2026-08-17 13:00:00-03',
        '2026-08-17 13:00:00-03',
        'TEN000001',
        'SRC000002',
        $destinationConfig === null ? null : 1,
        $destinationConfig === null ? null : 'DST000001',
        $destinationConfig === null ? null : 'n8n teste funcional',
        $destinationConfig === null ? null : 'n8n',
        $destinationStatus,
        $destinationConfig,
    );
}

function n8nProcessor(FakeN8nHttpClient $client): N8nEventProcessor
{
    return new N8nEventProcessor($client, new WebhookUrlValidator('production'));
}

foreach ([200, 201, 204] as $statusCode) {
    $client = new FakeN8nHttpClient($statusCode);
    n8nProcessor($client)->process(n8nEvent());
    assert($client->lastUrl === 'https://n8n.example.com/webhook/hpucca-events?token=hidden');
    assert($client->lastTimeout === 7);
}

$client = new FakeN8nHttpClient(200);
n8nProcessor($client)->process(n8nEvent());
$payload = $client->lastPayload ?? [];
assert($payload['event_code'] === 'EVT000012');
assert($payload['event_type'] === 'lead.created');
assert($payload['external_id'] === 'LEAD-123');
assert($payload['tenant_code'] === 'TEN000001');
assert($payload['source_code'] === 'SRC000002');
assert($payload['destination_code'] === 'DST000001');
assert($payload['occurred_at'] === '2026-08-17T13:00:00-03:00');
assert($payload['data'] === ['name' => 'Ana']);
assert(!array_key_exists('id', $payload));
assert(!array_key_exists('api_key_hash', $payload));
assert(!array_key_exists('config', $payload));
assert(!array_key_exists('secret', $payload));

$transientStatuses = [408, 425, 429, 500, 502, 503, 504];
foreach ($transientStatuses as $statusCode) {
    try {
        n8nProcessor(new FakeN8nHttpClient($statusCode))->process(n8nEvent());
        assert(false);
    } catch (TransientProcessingException $exception) {
        assert($exception->getMessage() === 'Transient n8n delivery failure.');
    }
}

try {
    n8nProcessor(new FakeN8nHttpClient(200, true))->process(n8nEvent());
    assert(false);
} catch (TransientProcessingException $exception) {
    assert($exception->getMessage() === 'Transient n8n delivery failure.');
    assert(!str_contains($exception->getMessage(), 'secret'));
}

$permanentStatuses = [300, 400, 401, 403, 404, 405, 410, 418, 599];
foreach ($permanentStatuses as $statusCode) {
    try {
        n8nProcessor(new FakeN8nHttpClient($statusCode))->process(n8nEvent());
        assert(false);
    } catch (PermanentProcessingException $exception) {
        assert($exception->getMessage() === 'Permanent n8n delivery failure.');
    }
}

$permanentEvents = [
    n8nEvent(destinationConfig: null),
    n8nEvent(destinationConfig: '{"webhook_url":"","timeout_seconds":10}'),
    n8nEvent(destinationConfig: '{"webhook_url":"https://127.0.0.1/webhook","timeout_seconds":10}'),
    n8nEvent(destinationConfig: '{"webhook_url":"https://n8n.example.com/webhook","timeout_seconds":31}'),
    n8nEvent(destinationStatus: 'inactive'),
    n8nEvent(json_encode(['blob' => str_repeat('a', 262145)], JSON_UNESCAPED_SLASHES) ?: '{}'),
];

foreach ($permanentEvents as $event) {
    $client = new FakeN8nHttpClient();

    try {
        n8nProcessor($client)->process($event);
        assert(false);
    } catch (PermanentProcessingException $exception) {
        assert(in_array($exception->getMessage(), ['Permanent n8n delivery failure.', 'Integration destination is not configured.'], true));
        assert($client->calls === 0);
    }
}

echo 'N8nEventProcessorTest passed.' . PHP_EOL;
