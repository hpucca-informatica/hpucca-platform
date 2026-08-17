<?php

declare(strict_types=1);

use HPucca\Platform\Models\Event;
use HPucca\Platform\Services\CurlHttpClient;
use HPucca\Platform\Services\N8nEventProcessor;
use HPucca\Platform\Services\WebhookUrlValidator;

require dirname(__DIR__) . '/vendor/autoload.php';

if (($_ENV['RUN_N8N_HTTP_INTEGRATION_TESTS'] ?? getenv('RUN_N8N_HTTP_INTEGRATION_TESTS') ?: '') !== '1') {
    echo 'N8nHttpIntegrationTest skipped. Set RUN_N8N_HTTP_INTEGRATION_TESTS=1 to enable.' . PHP_EOL;
    return;
}

$webhookUrl = trim((string) ($_ENV['N8N_TEST_WEBHOOK_URL'] ?? getenv('N8N_TEST_WEBHOOK_URL') ?: ''));

if ($webhookUrl === '') {
    echo 'N8nHttpIntegrationTest skipped. Set N8N_TEST_WEBHOOK_URL to enable.' . PHP_EOL;
    return;
}

$event = new Event(
    1,
    'EVT999001',
    1,
    'Empresa Teste',
    1,
    'Fonte Teste',
    'test.created',
    'TEST-001',
    '{"test":true}',
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
    'TEN999001',
    'SRC999001',
    1,
    'DST999001',
    'Destino teste HTTP real',
    'n8n',
    'active',
    json_encode(['webhook_url' => $webhookUrl, 'timeout_seconds' => 10], JSON_UNESCAPED_SLASHES) ?: '{}',
);

(new N8nEventProcessor(new CurlHttpClient(), new WebhookUrlValidator('production')))->process($event);

echo 'N8nHttpIntegrationTest passed.' . PHP_EOL;
