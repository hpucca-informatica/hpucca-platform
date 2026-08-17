<?php

declare(strict_types=1);

use HPucca\Platform\Models\Event;
use HPucca\Platform\Models\IntegrationDestination;
use HPucca\Platform\Services\EventProcessorResolver;
use HPucca\Platform\Services\N8nEventProcessor;
use HPucca\Platform\Services\PermanentProcessingException;

require dirname(__DIR__) . '/vendor/autoload.php';

$destination = new IntegrationDestination(1, 'DST000001', 1, 'Empresa A', 'active', 'Destino n8n', 'destino-n8n', 'n8n', 'active', '{}', '2026-01-01', '2026-01-01');
$resolver = new EventProcessorResolver();
$processor = $resolver->resolve($destination);
assert($processor instanceof N8nEventProcessor);

$unknownDestination = new IntegrationDestination(2, 'DST000002', 1, 'Empresa A', 'active', 'Destino desconhecido', 'destino-desconhecido', 'unknown', 'active', '{}', '2026-01-01', '2026-01-01');
try {
    $resolver->resolve($unknownDestination);
    assert(false);
} catch (PermanentProcessingException $exception) {
    assert($exception->getMessage() === 'Unsupported integration destination type.');
    assert(!str_contains($exception->getMessage(), 'DST000002'));
    assert(!str_contains($exception->getMessage(), 'unknown'));
}

$event = new Event(
    1,
    'EVT000001',
    1,
    'Empresa A',
    1,
    'Fonte A',
    'lead.created',
    'LEAD-001',
    '{"token":"should-not-leak","payload":"hidden"}',
    'pending',
    0,
    '2026-01-01 00:00:00',
    null,
    null,
    null,
    '2026-01-01 00:00:00',
    '2026-01-01 00:00:00',
    '2026-01-01 00:00:00',
    '2026-01-01 00:00:00',
);

try {
    $processor->process($event);
    assert(false);
} catch (PermanentProcessingException $exception) {
    assert($exception->getMessage() === 'Integration destination is not configured.');
    assert(!str_contains($exception->getMessage(), 'token'));
    assert(!str_contains($exception->getMessage(), 'payload'));
    assert(!str_contains($exception->getMessage(), 'LEAD-001'));
}

$resolverCode = (string) file_get_contents(dirname(__DIR__) . '/app/Services/EventProcessorResolver.php');
$n8nCode = (string) file_get_contents(dirname(__DIR__) . '/app/Services/N8nEventProcessor.php');
assert(!str_contains($resolverCode, 'curl'));
assert(!str_contains($resolverCode, 'file_get_contents'));
assert(!str_contains($resolverCode, 'PDO'));
assert(!str_contains($resolverCode, 'RetryPolicy'));
assert(!str_contains($resolverCode, 'Scheduler'));
assert(!str_contains($n8nCode, 'file_get_contents'));
assert(!str_contains($n8nCode, 'PDO'));
assert(!str_contains($n8nCode, 'RetryPolicy'));
assert(!str_contains($n8nCode, 'Scheduler'));

echo 'EventProcessorResolverTest passed.' . PHP_EOL;
