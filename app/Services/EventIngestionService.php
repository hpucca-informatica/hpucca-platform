<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

use DateTimeImmutable;
use HPucca\Platform\Models\Event;
use HPucca\Platform\Models\IntegrationSource;
use HPucca\Platform\Repositories\EventRepositoryContract;
use HPucca\Platform\Repositories\IntegrationSourceRepositoryContract;

final readonly class EventIngestionService
{
    public const MAX_PAYLOAD_BYTES = 65536;

    private SensitivePayloadValidator $payloadValidator;

    public function __construct(
        private IntegrationSourceRepositoryContract $sources,
        private EventRepositoryContract $events,
        private PublicCodeGenerator $codes,
        private ApiKeyService $apiKeys,
        ?SensitivePayloadValidator $payloadValidator = null,
    ) {
        $this->payloadValidator = $payloadValidator ?? new SensitivePayloadValidator();
    }

    /**
     * @return array{httpStatus: int, body: array<string, string>}
     */
    public function ingest(string $apiKey, string $rawBody): array
    {
        if ($apiKey === '') {
            return $this->error(401, 'unauthorized', 'API key obrigatoria.');
        }

        $source = $this->authenticate($apiKey);
        if (!$source instanceof IntegrationSource) {
            return $this->error(401, 'unauthorized', 'API key invalida.');
        }

        if ($source->status !== 'active' || $source->tenantStatus !== 'active') {
            return $this->error(403, 'forbidden', 'Fonte ou empresa inativa.');
        }

        if (strlen($rawBody) > self::MAX_PAYLOAD_BYTES) {
            return $this->error(422, 'invalid_payload', 'Payload acima do limite permitido.');
        }

        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            return $this->error(422, 'invalid_json', 'JSON invalido.');
        }

        $validation = $this->validatePayload($decoded);
        if ($validation['error'] !== '') {
            return $this->error(422, 'invalid_payload', $validation['error']);
        }

        if ($this->payloadValidator->containsSensitiveData($decoded['data'])) {
            return [
                'httpStatus' => 422,
                'body' => ['error' => 'Payload contains sensitive data.'],
            ];
        }

        $eventType = $validation['event'];
        $externalId = $validation['external_id'];
        $duplicate = $this->events->findDuplicate($source->id, $externalId, $eventType);

        if ($duplicate instanceof Event) {
            return [
                'httpStatus' => 200,
                'body' => ['status' => 'duplicate', 'event_code' => $duplicate->code],
            ];
        }

        $payload = json_encode($decoded['data'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $result = $this->events->create([
            'tenant_id' => $source->tenantId,
            'integration_source_id' => $source->id,
            'event_type' => $eventType,
            'external_id' => $externalId,
            'payload' => $payload,
            'occurred_at' => $validation['occurred_at'],
        ], $this->codes);
        $this->sources->touchLastUsedAt($source->id);

        return [
            'httpStatus' => $result['duplicate'] ? 200 : 202,
            'body' => [
                'status' => $result['duplicate'] ? 'duplicate' : 'accepted',
                'event_code' => $result['event']->code,
            ],
        ];
    }

    private function authenticate(string $apiKey): ?IntegrationSource
    {
        foreach ($this->sources->sourcesForAuthentication() as $source) {
            if ($this->apiKeys->verify($apiKey, $source->apiKeyHash)) {
                return $source;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{event: string, external_id: string, occurred_at: string|null, error: string}
     */
    private function validatePayload(array $payload): array
    {
        $allowed = ['event', 'external_id', 'occurred_at', 'data'];
        foreach (array_keys($payload) as $key) {
            if (!in_array((string) $key, $allowed, true)) {
                return $this->validation('', '', null, 'Campo nao permitido no payload.');
            }
        }

        $event = is_string($payload['event'] ?? null) ? strtolower(trim($payload['event'])) : '';
        if ($event === '' || strlen($event) > 100 || !preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $event)) {
            return $this->validation('', '', null, 'Evento invalido.');
        }

        $externalId = is_string($payload['external_id'] ?? null) ? trim($payload['external_id']) : '';
        if ($externalId === '' || strlen($externalId) > 150) {
            return $this->validation('', '', null, 'External ID invalido.');
        }

        if (!isset($payload['data']) || !is_array($payload['data']) || array_is_list($payload['data'])) {
            return $this->validation('', '', null, 'Data deve ser um objeto JSON.');
        }

        $occurredAt = null;
        if (array_key_exists('occurred_at', $payload) && $payload['occurred_at'] !== null && $payload['occurred_at'] !== '') {
            if (!is_string($payload['occurred_at'])) {
                return $this->validation('', '', null, 'Occurred_at invalido.');
            }

            try {
                $occurredAt = (new DateTimeImmutable($payload['occurred_at']))->format('Y-m-d H:i:sP');
            } catch (\Throwable) {
                return $this->validation('', '', null, 'Occurred_at invalido.');
            }
        }

        return $this->validation($event, $externalId, $occurredAt, '');
    }

    /**
     * @return array{event: string, external_id: string, occurred_at: string|null, error: string}
     */
    private function validation(string $event, string $externalId, ?string $occurredAt, string $error): array
    {
        return [
            'event' => $event,
            'external_id' => $externalId,
            'occurred_at' => $occurredAt,
            'error' => $error,
        ];
    }

    /**
     * @return array{httpStatus: int, body: array<string, string>}
     */
    private function error(int $httpStatus, string $status, string $message): array
    {
        return [
            'httpStatus' => $httpStatus,
            'body' => ['status' => $status, 'message' => $message],
        ];
    }
}
