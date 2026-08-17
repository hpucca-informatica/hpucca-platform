<?php

declare(strict_types=1);

use HPucca\Platform\Core\View;
use HPucca\Platform\Models\IntegrationDestination;
use HPucca\Platform\Models\Tenant;

require dirname(__DIR__) . '/vendor/autoload.php';

$tenant = new Tenant(1, 'TEN000001', 'Empresa A', 'empresa-a', 'active', '2026-01-01', '2026-01-01');
$destination = new IntegrationDestination(1, 'DST000001', 1, 'Empresa A', 'active', 'Destino n8n', 'destino-n8n', 'n8n', 'active', '{"webhook_url":"https://n8n.example.com/webhook/hpucca-events?token=hidden","timeout_seconds":10}', '2026-01-01', '2026-01-01');

$formBody = View::render('integration-destinations/create.php', [
    'values' => ['tenant_id' => '', 'name' => '', 'slug' => '', 'type' => 'n8n', 'status' => 'active', 'webhook_url' => '', 'timeout_seconds' => '10'],
    'errors' => [],
    'tenants' => [$tenant],
    'csrfField' => '<input type="hidden" name="_csrf" value="token">',
]);
assert(str_contains($formBody, 'data-slug-source'));
assert(str_contains($formBody, 'data-slug-target'));
assert(str_contains($formBody, 'name="type"'));
assert(str_contains($formBody, 'value="n8n"'));
assert(str_contains($formBody, 'name="webhook_url"'));
assert(str_contains($formBody, 'name="timeout_seconds"'));
assert(!str_contains($formBody, 'textarea'));
assert(!str_contains($formBody, 'secret'));
assert(!str_contains($formBody, 'password'));

$indexBody = View::render('integration-destinations/index.php', [
    'destinations' => [$destination],
    'tenants' => [$tenant],
    'search' => '',
    'tenantId' => null,
    'page' => 1,
    'pages' => 1,
]);
assert(str_contains($indexBody, 'DST000001'));
assert(str_contains($indexBody, 'Destino n8n'));
assert(str_contains($indexBody, 'n8n'));
assert(str_contains($indexBody, 'status-badge status-active'));
assert(!str_contains($indexBody, 'api_key'));
assert(!str_contains($indexBody, 'bearer'));

$showBody = View::render('integration-destinations/show.php', [
    'destination' => $destination,
    'csrfField' => '<input type="hidden" name="_csrf" value="token">',
]);
assert(str_contains($showBody, 'DST000001'));
assert(str_contains($showBody, 'https://n8n.example.com/.../hpucca-events'));
assert(str_contains($showBody, '10s'));
assert(str_contains($showBody, '/admin/integration-destinations/1/deactivate'));
assert(!str_contains($showBody, 'token=hidden'));
assert(!str_contains($showBody, '{"webhook_url"'));
assert(!str_contains($showBody, 'credential'));

$migration = (string) file_get_contents(dirname(__DIR__) . '/database/migrations/006_create_integration_destinations.sql');
assert(str_contains($migration, 'integration_destinations_code_seq'));
assert(str_contains($migration, "'DST'"));
assert(str_contains($migration, "CHECK (type IN ('n8n'))"));
assert(str_contains($migration, "CHECK (status IN ('active', 'inactive'))"));
assert(str_contains($migration, 'prevent_integration_destinations_code_update'));
assert(str_contains($migration, 'destination_id BIGINT NULL'));
assert(str_contains($migration, 'integration_sources_destination_id_foreign'));
assert(!str_contains(strtolower($migration), 'token'));
assert(!str_contains(strtolower($migration), 'secret'));
assert(!str_contains(strtolower($migration), 'password'));
assert(!str_contains(strtolower($migration), 'api_key'));

$serviceCode = (string) file_get_contents(dirname(__DIR__) . '/app/Services/IntegrationDestinationService.php');
assert(str_contains($serviceCode, "private const VALID_TYPES = ['n8n']"));
assert(str_contains($serviceCode, "'config' => '{}'"));
assert(str_contains($serviceCode, '/^[a-z0-9-]+$/'));

$sourceServiceCode = (string) file_get_contents(dirname(__DIR__) . '/app/Services/IntegrationSourceService.php');
assert(str_contains($sourceServiceCode, 'destination->tenantId !== $tenantId'));
assert(str_contains($sourceServiceCode, 'Selecione um destino da mesma empresa.'));

echo 'IntegrationDestinationUsabilityTest passed.' . PHP_EOL;
