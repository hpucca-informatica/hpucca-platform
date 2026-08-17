<?php

declare(strict_types=1);

use HPucca\Platform\Core\Request;
use HPucca\Platform\Core\Response;
use HPucca\Platform\Core\View;
use HPucca\Platform\Models\IntegrationSource;
use HPucca\Platform\Models\Tenant;
use HPucca\Platform\Middleware\AuthMiddleware;
use HPucca\Platform\Middleware\OwnerMiddleware;
use HPucca\Platform\Repositories\EventMetricsRepositoryContract;
use HPucca\Platform\Repositories\IntegrationSourceRepositoryContract;
use HPucca\Platform\Repositories\TenantRepositoryContract;
use HPucca\Platform\Services\AuthService;
use HPucca\Platform\Services\AutomationDashboardService;
use HPucca\Platform\Services\PublicCodeGenerator;

$_ENV['APP_TIMEZONE'] = 'America/Sao_Paulo';
$_ENV['EVENT_PROCESSING_TIMEOUT_MINUTES'] = '15';

require dirname(__DIR__) . '/vendor/autoload.php';

ini_set('session.save_path', dirname(__DIR__) . '/storage/cache');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

final class AutomationMetricsFake implements EventMetricsRepositoryContract
{
    /**
     * @var array<string, string>
     */
    public array $lastFilters = [];

    public function summary(array $filters): array
    {
        $this->lastFilters = $filters;

        return [
            'received' => 8,
            'processed' => 3,
            'failed' => 1,
            'pending' => 2,
            'processing' => 1,
            'scheduled_retry' => 1,
            'avg_attempts_finalized' => 1.75,
        ];
    }

    public function dailySeries(array $filters, string $timezone): array
    {
        assert($timezone === 'America/Sao_Paulo');

        return [
            ['day' => '2026-08-14', 'received' => 4, 'processed' => 3, 'failed' => 1],
        ];
    }

    public function recentFailures(array $filters, int $limit = 10): array
    {
        assert($limit === 10);

        return [
            $this->row(10, 'failed', 4, ['failed_at' => '2026-08-14 12:00:00+00', 'last_error' => '<script>alert(1)</script>', 'external_id' => 'EXT-10']),
            $this->row(9, 'failed', 3, ['failed_at' => '2026-08-14 11:00:00+00', 'last_error' => 'Erro permanente', 'external_id' => 'EXT-9']),
        ];
    }

    public function scheduledRetries(array $filters, int $limit = 10): array
    {
        assert($limit === 10);

        return [
            $this->row(11, 'pending', 2, ['available_at' => '2026-08-14 13:00:00+00']),
            $this->row(12, 'pending', 5, ['available_at' => '2026-08-14 14:00:00+00']),
        ];
    }

    public function processingEvents(array $filters, int $limit = 10): array
    {
        assert($limit === 10);

        return [
            $this->row(13, 'processing', 1, ['updated_at' => date('Y-m-d H:i:sP', time() - 60)]),
            $this->row(14, 'processing', 2, ['updated_at' => date('Y-m-d H:i:sP', time() - 3600)]),
        ];
    }

    public function topAttempts(array $filters, int $limit = 10): array
    {
        assert($limit === 10);

        return [
            $this->row(15, 'failed', 9, ['updated_at' => '2026-08-14 14:00:00+00']),
            $this->row(16, 'pending', 8, ['updated_at' => '2026-08-14 13:00:00+00']),
        ];
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function row(int $id, string $status, int $attempts, array $extra): array
    {
        return [
            'id' => $id,
            'code' => PublicCodeGenerator::format('EVT', $id),
            'status' => $status,
            'attempts' => $attempts,
            'tenant_id' => 1,
            'tenant_name' => 'Empresa <A>',
            'integration_source_id' => 2,
            'integration_source_name' => 'Fonte A',
            'event_type' => 'lead.created',
        ] + $extra;
    }
}

final class AutomationZeroMetricsFake implements EventMetricsRepositoryContract
{
    public function summary(array $filters): array
    {
        return [
            'received' => 0,
            'processed' => 0,
            'failed' => 0,
            'pending' => 0,
            'processing' => 0,
            'scheduled_retry' => 0,
            'avg_attempts_finalized' => null,
        ];
    }

    public function dailySeries(array $filters, string $timezone): array
    {
        return [];
    }

    public function recentFailures(array $filters, int $limit = 10): array
    {
        return [];
    }

    public function scheduledRetries(array $filters, int $limit = 10): array
    {
        return [];
    }

    public function processingEvents(array $filters, int $limit = 10): array
    {
        return [];
    }

    public function topAttempts(array $filters, int $limit = 10): array
    {
        return [];
    }
}

final class AutomationTenantsFake implements TenantRepositoryContract
{
    public function findById(int $id): ?Tenant
    {
        return null;
    }

    public function findActiveById(int $id): ?Tenant
    {
        return null;
    }

    public function findActiveBySlug(string $slug): ?Tenant
    {
        return null;
    }

    public function all(): array
    {
        return [new Tenant(1, 'TEN000001', 'Empresa A', 'empresa-a', 'active', '2026-08-14 00:00:00', '2026-08-14 00:00:00')];
    }
}

final class AutomationSourcesFake implements IntegrationSourceRepositoryContract
{
    public function paginate(string $search, ?int $tenantId, int $page, int $perPage): array
    {
        return [new IntegrationSource(2, 'SRC000002', 1, 'Empresa A', 'active', 'Fonte A', 'fonte-a', 'hash-nao-exibido', 'active', null, null, null, null, null, null, '2026-08-14 00:00:00', '2026-08-14 00:00:00')];
    }

    public function count(string $search, ?int $tenantId): int
    {
        return 1;
    }

    public function findById(int $id): ?IntegrationSource
    {
        return null;
    }

    public function findByTenantAndSlug(int $tenantId, string $slug): ?IntegrationSource
    {
        return null;
    }

    public function sourcesForAuthentication(): array
    {
        return [];
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
        return false;
    }

    public function deactivate(int $id): bool
    {
        return false;
    }

    public function touchLastUsedAt(int $id): void
    {
    }
}

function automationResponseStatus(Response $response): int
{
    $property = new ReflectionProperty($response, 'statusCode');
    $property->setAccessible(true);

    return (int) $property->getValue($response);
}

function automationResponseHeader(Response $response, string $name): string
{
    $property = new ReflectionProperty($response, 'headers');
    $property->setAccessible(true);
    $headers = $property->getValue($response);

    return is_array($headers) && is_string($headers[$name] ?? null) ? $headers[$name] : '';
}

$_SESSION = [];
$unauthenticated = (new AuthMiddleware(new AuthService()))->handle(static fn (): Response => Response::html('owner'));
assert(automationResponseStatus($unauthenticated) === 302);
assert(automationResponseHeader($unauthenticated, 'Location') === '/login');

foreach (['admin', 'manager', 'user'] as $type) {
    $_SESSION = [
        'user_id' => 1,
        'user_code' => 'USR000001',
        'tenant_id' => 1,
        'tenant_name' => 'Empresa A',
        'login' => $type,
        'name' => ucfirst($type),
        'type' => $type,
    ];
    $forbidden = (new OwnerMiddleware())->handle(static fn (): Response => Response::html('owner'));
    assert(automationResponseStatus($forbidden) === 403);
}

$_SESSION = [
    'user_id' => 1,
    'user_code' => 'USR000001',
    'tenant_id' => 1,
    'tenant_name' => 'Empresa A',
    'login' => 'owner',
    'name' => 'Owner',
    'type' => 'owner',
];
$allowed = (new OwnerMiddleware())->handle(static fn (): Response => Response::html('owner'));
assert(automationResponseStatus($allowed) === 200);

$metrics = new AutomationMetricsFake();
$service = new AutomationDashboardService($metrics, new AutomationSourcesFake(), new AutomationTenantsFake());
$dashboard = $service->dashboard([
    'tenant_id' => '1',
    'source_id' => '2',
    'event_type' => 'lead.created',
    'status' => 'failed',
    'period' => 'custom',
    'date_from' => '2026-08-01',
    'date_to' => '2026-08-14',
]);

assert($dashboard['filters']['period'] === 'custom');
assert($dashboard['filters']['date_from'] === '2026-08-01');
assert($dashboard['filters']['date_to'] === '2026-08-14');
assert($metrics->lastFilters['tenant_id'] === '1');
assert($metrics->lastFilters['source_id'] === '2');
assert($metrics->lastFilters['event_type'] === 'lead.created');
assert($metrics->lastFilters['status'] === 'failed');
assert(str_contains($metrics->lastFilters['received_from'], '2026-08-01 00:00:00'));
assert(str_contains($metrics->lastFilters['received_to'], '2026-08-14 23:59:59'));
assert($dashboard['summary']['processed'] === 3);
assert($dashboard['summary']['failed'] === 1);
assert($dashboard['summary']['pending'] === 2);
assert($dashboard['summary']['processing'] === 1);
assert($dashboard['summary']['scheduled_retry'] === 1);
assert($dashboard['summary']['success_rate'] === 75.0);
assert($dashboard['summary']['success_denominator'] === 4);
assert($dashboard['recentFailures'][0]['failed_at'] > $dashboard['recentFailures'][1]['failed_at']);
assert($dashboard['scheduledRetries'][0]['available_at'] < $dashboard['scheduledRetries'][1]['available_at']);
assert($dashboard['processingEvents'][0]['processing_state'] === 'recent');
assert($dashboard['processingEvents'][1]['processing_state'] === 'stale');
assert($dashboard['topAttempts'][0]['attempts'] > $dashboard['topAttempts'][1]['attempts']);
assert(count($dashboard['recentFailures']) <= 10);
assert(count($dashboard['scheduledRetries']) <= 10);
assert(count($dashboard['processingEvents']) <= 10);
assert(count($dashboard['topAttempts']) <= 10);

$defaultDashboard = $service->dashboard([]);
assert($defaultDashboard['filters']['period'] === 'today');
$todayInAppTimezone = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
assert($defaultDashboard['filters']['date_from'] === $todayInAppTimezone);
assert($defaultDashboard['filters']['date_to'] === $todayInAppTimezone);

$zeroDashboard = (new AutomationDashboardService(new AutomationZeroMetricsFake(), new AutomationSourcesFake(), new AutomationTenantsFake()))->dashboard([]);
assert($zeroDashboard['summary']['success_rate'] === null);

$body = View::render('automation/index.php', $dashboard);
assert(str_contains($body, 'Eventos recebidos'));
assert(str_contains($body, 'Taxa de sucesso'));
assert(str_contains($body, '75,0%'));
assert(str_contains($body, 'Retry agendado'));
assert(str_contains($body, 'Stale'));
assert(str_contains($body, 'Recente'));
assert(str_contains($body, 'Eventos por dia'));
assert(str_contains($body, '/admin/events/10'));
assert(!str_contains($body, '<script>alert(1)</script>'));
assert(str_contains($body, '&lt;script&gt;alert(1)&lt;/script&gt;'));
assert(!str_contains($body, 'payload'));
assert(!str_contains($body, 'super-secret-api-key'));
assert(!str_contains($body, 'hash-nao-exibido'));

$emptyBody = View::render('automation/index.php', $zeroDashboard);
assert(str_contains($emptyBody, 'Nenhum evento no periodo.'));
assert(str_contains($emptyBody, 'Nenhuma falha no periodo.'));
assert(str_contains($emptyBody, 'Nenhum evento aguardando retry.'));

$sidebar = View::render('partials/sidebar.php', [
    'user' => ['type' => 'owner'],
    'activeMenu' => 'automation',
    'csrfField' => '',
]);
assert(str_contains($sidebar, '/admin/automation'));
assert(str_contains($sidebar, 'Visao geral'));

$routes = (string) file_get_contents(dirname(__DIR__) . '/routes/api.php');
assert(str_contains($routes, "\$router->get('/admin/automation'"));
assert(str_contains($routes, 'AutomationController'));
assert(str_contains($routes, 'AuthMiddleware'));
assert(str_contains($routes, 'OwnerMiddleware'));
assert(!str_contains($routes, "\$router->post('/admin/automation'"));

$repositorySql = (string) file_get_contents(dirname(__DIR__) . '/app/Repositories/EventMetricsRepository.php');
assert(str_contains($repositorySql, 'prepare('));
assert(str_contains($repositorySql, "e.status = 'pending' AND e.attempts > 0 AND e.available_at > CURRENT_TIMESTAMP"));
assert(preg_match('/e\.failed_at\s+DESC(?:\s+NULLS\s+LAST)?(?:\s*,\s*e\.updated_at\s+DESC)?/i', $repositorySql) === 1);
assert(preg_match('/e\.available_at\s+ASC(?:\s*,\s*e\.updated_at\s+ASC)?/i', $repositorySql) === 1);
assert(preg_match('/e\.attempts\s+DESC\s*,\s*e\.updated_at\s+DESC/i', $repositorySql) === 1);
assert(!str_contains($repositorySql, 'payload'));
assert(!str_contains($repositorySql, 'api_key_hash'));

echo 'AutomationDashboardTest passed.' . PHP_EOL;
