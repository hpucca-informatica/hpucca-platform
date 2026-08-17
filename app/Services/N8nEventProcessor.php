<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

use HPucca\Platform\Core\Config;
use HPucca\Platform\Models\Event;
use Throwable;

final readonly class N8nEventProcessor implements EventProcessorContract
{
    private const MAX_PAYLOAD_BYTES = 262144;

    public function __construct(
        private HttpClientContract $httpClient = new CurlHttpClient(),
        private ?WebhookUrlValidator $urlValidator = null,
    ) {
    }

    public function process(Event $event): void
    {
        try {
            $this->deliver($event);
        } catch (TransientProcessingException $exception) {
            throw $exception;
        } catch (PermanentProcessingException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new PermanentProcessingException('Permanent n8n delivery failure.');
        }
    }

    private function deliver(Event $event): void
    {
        if ($event->destinationId === null || $event->destinationConfig === null) {
            throw new PermanentProcessingException('Integration destination is not configured.');
        }

        if ($event->destinationStatus !== 'active') {
            throw new PermanentProcessingException('Permanent n8n delivery failure.');
        }

        $config = N8nDestinationConfig::fromJson($event->destinationConfig);

        if (!$this->validator()->isValid($config->webhookUrl)) {
            throw new PermanentProcessingException('Permanent n8n delivery failure.');
        }

        $data = json_decode($event->payload, true);

        if (!is_array($data)) {
            throw new PermanentProcessingException('Permanent n8n delivery failure.');
        }

        $envelope = [
            'event_code' => $event->code,
            'event_type' => $event->eventType,
            'external_id' => $event->externalId,
            'tenant_code' => $event->tenantCode,
            'source_code' => $event->integrationSourceCode,
            'destination_code' => $event->destinationCode,
            'occurred_at' => $event->occurredAt,
            'data' => $data,
        ];

        $encoded = json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!is_string($encoded) || strlen($encoded) > self::MAX_PAYLOAD_BYTES) {
            throw new PermanentProcessingException('Permanent n8n delivery failure.');
        }

        try {
            $response = $this->httpClient->postJson($config->webhookUrl, $envelope, $config->timeoutSeconds);
        } catch (HttpTransportException) {
            throw new TransientProcessingException('Transient n8n delivery failure.');
        }

        if ($response->statusCode >= 200 && $response->statusCode <= 299) {
            return;
        }

        if (in_array($response->statusCode, [408, 425, 429, 500, 502, 503, 504], true)) {
            throw new TransientProcessingException('Transient n8n delivery failure.');
        }

        throw new PermanentProcessingException('Permanent n8n delivery failure.');
    }

    private function validator(): WebhookUrlValidator
    {
        return $this->urlValidator ?? new WebhookUrlValidator((string) Config::get('app.env', 'local'));
    }
}
