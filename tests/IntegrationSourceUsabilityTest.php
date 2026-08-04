<?php

declare(strict_types=1);

use HPucca\Platform\Core\View;
use HPucca\Platform\Core\ViewHelper;
use HPucca\Platform\Models\Event;
use HPucca\Platform\Models\IntegrationSource;
use HPucca\Platform\Models\Tenant;
use HPucca\Platform\Services\FlashService;

require dirname(__DIR__) . '/vendor/autoload.php';

function integrationSourceTestSlug(string $value): string
{
    $map = [
        "\xC3\xA1" => 'a', "\xC3\xA0" => 'a', "\xC3\xA2" => 'a', "\xC3\xA3" => 'a', "\xC3\xA4" => 'a',
        "\xC3\x81" => 'a', "\xC3\x80" => 'a', "\xC3\x82" => 'a', "\xC3\x83" => 'a', "\xC3\x84" => 'a',
        "\xC3\xA9" => 'e', "\xC3\xA8" => 'e', "\xC3\xAA" => 'e', "\xC3\xAB" => 'e',
        "\xC3\x89" => 'e', "\xC3\x88" => 'e', "\xC3\x8A" => 'e', "\xC3\x8B" => 'e',
        "\xC3\xAD" => 'i', "\xC3\xAC" => 'i', "\xC3\xAE" => 'i', "\xC3\xAF" => 'i',
        "\xC3\x8D" => 'i', "\xC3\x8C" => 'i', "\xC3\x8E" => 'i', "\xC3\x8F" => 'i',
        "\xC3\xB3" => 'o', "\xC3\xB2" => 'o', "\xC3\xB4" => 'o', "\xC3\xB5" => 'o', "\xC3\xB6" => 'o',
        "\xC3\x93" => 'o', "\xC3\x92" => 'o', "\xC3\x94" => 'o', "\xC3\x95" => 'o', "\xC3\x96" => 'o',
        "\xC3\xBA" => 'u', "\xC3\xB9" => 'u', "\xC3\xBB" => 'u', "\xC3\xBC" => 'u',
        "\xC3\x9A" => 'u', "\xC3\x99" => 'u', "\xC3\x9B" => 'u', "\xC3\x9C" => 'u',
        "\xC3\xA7" => 'c', "\xC3\x87" => 'c',
    ];
    $slug = strtolower(strtr($value, $map));
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug) ?? '';
    $slug = preg_replace('/\s+/', '-', $slug) ?? '';
    $slug = preg_replace('/-+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    $slug = substr($slug, 0, 100);

    return rtrim($slug, '-');
}

assert(integrationSourceTestSlug('Fonte Teste Funcional') === 'fonte-teste-funcional');
assert(integrationSourceTestSlug("Cl\xC3\xADnica Jo\xC3\xA3o Pessoa") === 'clinica-joao-pessoa');
assert(integrationSourceTestSlug("Automa\xC3\xA7\xC3\xA3o & Integra\xC3\xA7\xC3\xA3o") === 'automacao-integracao');
assert(integrationSourceTestSlug("\xC3\x87edilha Fonte") === 'cedilha-fonte');
assert(integrationSourceTestSlug('API---Fonte   Especial!!!') === 'api-fonte-especial');
assert(strlen(integrationSourceTestSlug(str_repeat('Fonte ', 30))) <= 100);

$js = (string) file_get_contents(dirname(__DIR__) . '/public/assets/js/admin.js');
assert(str_contains($js, 'slugifyIntegrationSourceName'));
assert(str_contains($js, "normalize('NFD')"));
assert(str_contains($js, '/[\\u0300-\\u036f]/g'));
assert(str_contains($js, 'manualSlug'));
assert(str_contains($js, "slugInput.value.trim() !== ''"));
assert(str_contains($js, "navigator.clipboard.writeText"));
assert(str_contains($js, 'document.execCommand'));
assert(str_contains($js, '[data-copy-target], [data-copy-value]'));
assert(str_contains($js, '2000'));

$flashBody = View::render('partials/flash.php', [
    'flash' => [
        ['type' => 'success', 'message' => 'Registro salvo.'],
        ['type' => 'error', 'message' => 'Nao foi possivel salvar.'],
        ['type' => 'warning', 'message' => 'Revise os dados.'],
        ['type' => 'info', 'message' => 'Mensagem informativa.'],
        ['message' => 'Mensagem sem tipo.'],
        '<strong>Mensagem antiga</strong>',
    ],
]);
assert(str_contains($flashBody, 'flash-success'));
assert(str_contains($flashBody, 'flash-error'));
assert(str_contains($flashBody, 'flash-warning'));
assert(substr_count($flashBody, 'flash-info') >= 2);
assert(substr_count($flashBody, 'role="alert"') === 2);
assert(substr_count($flashBody, 'role="status"') >= 3);
assert(str_contains($flashBody, '&lt;strong&gt;Mensagem antiga&lt;/strong&gt;'));
assert(!str_contains($flashBody, '<strong>Mensagem antiga</strong>'));

ini_set('session.save_path', dirname(__DIR__) . '/storage/cache');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = ['flash_messages' => ['Mensagem legada']];
FlashService::add('Mensagem tipada', 'success');
$consumedFlash = FlashService::consume();
assert($consumedFlash[0] === ['type' => 'info', 'message' => 'Mensagem legada']);
assert($consumedFlash[1] === ['type' => 'success', 'message' => 'Mensagem tipada']);
assert(FlashService::consume() === []);

$tenant = new Tenant(1, 'TEN000001', 'Empresa A', 'empresa-a', 'active', '2026-01-01', '2026-01-01');
$source = new IntegrationSource(1, 'SRC000001', 1, 'Empresa A', 'active', 'Fonte Atual', 'slug-existente', 'hash', 'active', null, '2026-01-01', '2026-01-01');

$formBody = View::render('integration-sources/create.php', [
    'values' => ['tenant_id' => '', 'name' => '', 'slug' => '', 'status' => 'active'],
    'errors' => [],
    'tenants' => [$tenant],
    'csrfField' => '<input type="hidden" name="_csrf" value="token">',
]);
assert(str_contains($formBody, 'data-slug-source'));
assert(str_contains($formBody, 'data-slug-target'));
assert(str_contains($formBody, 'name="slug" type="text"'));
assert(!str_contains($formBody, 'readonly'));

$editBody = View::render('integration-sources/edit.php', [
    'source' => $source,
    'values' => ['tenant_id' => '1', 'name' => 'Fonte Atual', 'slug' => 'slug-existente', 'status' => 'active'],
    'errors' => [],
    'tenants' => [$tenant],
    'csrfField' => '<input type="hidden" name="_csrf" value="token">',
]);
assert(str_contains($editBody, 'value="slug-existente"'));

$apiKey = 'hpk_live_test_only';
$apiKeyBody = View::render('integration-sources/api-key-created.php', [
    'source' => $source,
    'apiKey' => $apiKey,
]);
assert(str_contains($apiKeyBody, 'readonly'));
assert(str_contains($apiKeyBody, 'data-api-key-value'));
assert(str_contains($apiKeyBody, 'data-copy-target="#integration-source-api-key"'));
assert(str_contains($apiKeyBody, 'Copiar API Key'));
assert(str_contains($apiKeyBody, 'aria-live="polite"'));
assert(str_contains($apiKeyBody, $apiKey));

$indexBody = View::render('integration-sources/index.php', [
    'sources' => [$source],
    'tenants' => [$tenant],
    'search' => '',
    'tenantId' => null,
    'page' => 1,
    'pages' => 1,
]);
assert(!str_contains($indexBody, $apiKey));
assert(!str_contains($indexBody, 'hash'));
assert(str_contains($indexBody, 'data-copy-target'));
assert(str_contains($indexBody, 'SRC000001'));
assert(str_contains($indexBody, 'status-badge status-active'));
assert(str_contains($indexBody, '01/01/2026'));

$showBody = View::render('integration-sources/show.php', [
    'source' => $source,
    'csrfField' => '<input type="hidden" name="_csrf" value="token">',
]);
assert(!str_contains($showBody, $apiKey));
assert(!str_contains($showBody, 'hash'));
assert(str_contains($showBody, 'data-copy-target'));

$event = new Event(
    1,
    'EVT000001',
    1,
    'Empresa A',
    1,
    'Fonte Atual',
    'lead.created',
    'LEAD-001',
    '{"name":"Teste","nested":{"ok":true}}',
    'pending',
    0,
    '2026-08-04 13:58:47',
    null,
    null,
    null,
    '2026-08-04 13:50:00',
    '2026-08-04 13:58:47',
    '2026-08-04 13:58:47',
    '2026-08-04 13:58:47',
);

$eventIndexBody = View::render('events/index.php', [
    'events' => [$event],
    'tenants' => [$tenant],
    'sources' => [$source],
    'filters' => [],
    'page' => 1,
    'pages' => 1,
]);
assert(str_contains($eventIndexBody, 'EVT000001'));
assert(str_contains($eventIndexBody, 'data-copy-target'));
assert(str_contains($eventIndexBody, 'status-badge status-pending'));
assert(str_contains($eventIndexBody, '04/08/2026 13:58:47'));

$eventShowBody = View::render('events/show.php', [
    'event' => $event,
]);
assert(str_contains($eventShowBody, 'Copiar JSON'));
assert(str_contains($eventShowBody, 'json-viewer'));
assert(str_contains($eventShowBody, "    &quot;nested&quot;"));
assert(str_contains($eventShowBody, 'status-badge status-pending'));

$invalidPayloadEvent = new Event(
    1,
    'EVT000002',
    1,
    'Empresa A',
    1,
    'Fonte Atual',
    'lead.created',
    'LEAD-002',
    '{invalid-json',
    'failed',
    1,
    '2026-08-04 13:58:47',
    null,
    null,
    'Erro',
    '2026-08-04 13:50:00',
    '2026-08-04 13:58:47',
    '2026-08-04 13:58:47',
    '2026-08-04 13:58:47',
);
$invalidPayloadBody = View::render('events/show.php', ['event' => $invalidPayloadEvent]);
assert(str_contains($invalidPayloadBody, '{invalid-json'));

$emptySourcesBody = View::render('integration-sources/index.php', [
    'sources' => [],
    'tenants' => [],
    'search' => '',
    'tenantId' => null,
    'page' => 1,
    'pages' => 1,
]);
assert(str_contains($emptySourcesBody, 'empty-state'));
assert(str_contains($emptySourcesBody, 'Criar primeira fonte'));

$emptyEventsBody = View::render('events/index.php', [
    'events' => [],
    'tenants' => [],
    'sources' => [],
    'filters' => [],
    'page' => 1,
    'pages' => 1,
]);
assert(str_contains($emptyEventsBody, 'empty-state'));
assert(str_contains($emptyEventsBody, 'Quando as integracoes enviarem eventos'));

assert(ViewHelper::formatDate('2026-08-04 13:58:47') === '04/08/2026 13:58:47');
assert(ViewHelper::formatDate(null) === '-');
assert(str_contains(ViewHelper::statusBadge('failed'), 'Falhou'));

$serviceCode = (string) file_get_contents(dirname(__DIR__) . '/app/Services/IntegrationSourceService.php');
assert(str_contains($serviceCode, '/^[a-z0-9-]+$/'));
assert(str_contains($serviceCode, 'findByTenantAndSlug'));

$migrations = glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [];
$migrationNames = array_map(static fn (string $path): string => basename($path), $migrations);
assert(!in_array('006_product_polish.sql', $migrationNames, true));

echo 'IntegrationSourceUsabilityTest passed.' . PHP_EOL;
