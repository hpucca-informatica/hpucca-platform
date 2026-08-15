<?php

declare(strict_types=1);

namespace HPucca\Platform\Controllers;

use HPucca\Platform\Core\Config;
use HPucca\Platform\Core\Database;
use HPucca\Platform\Core\Request;
use HPucca\Platform\Core\Response;
use HPucca\Platform\Core\View;
use HPucca\Platform\Repositories\EventMetricsRepository;
use HPucca\Platform\Repositories\IntegrationSourceRepository;
use HPucca\Platform\Repositories\TenantRepository;
use HPucca\Platform\Services\AuthService;
use HPucca\Platform\Services\AutomationDashboardService;
use RuntimeException;
use Throwable;

final readonly class AutomationController
{
    public function __construct(
        private Database $database,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->admin('automation/index.php', [
            'title' => 'Automacao',
            'activeMenu' => 'automation',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Automacao' => null],
            ...$this->service()->dashboard($this->filters($request)),
        ]);
    }

    private function service(): AutomationDashboardService
    {
        try {
            $connection = $this->database->connection();

            return new AutomationDashboardService(
                new EventMetricsRepository($connection),
                new IntegrationSourceRepository($connection),
                new TenantRepository($connection),
            );
        } catch (Throwable) {
            throw new RuntimeException('Automation module unavailable.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function admin(string $view, array $data, int $status = 200): Response
    {
        return View::admin($view, array_merge([
            'user' => AuthService::sessionUser(),
            'tenant' => AuthService::sessionTenant(),
            'version' => Config::get('app.version'),
        ], $data))->withStatus($status);
    }

    /**
     * @return array<string, string>
     */
    private function filters(Request $request): array
    {
        return [
            'period' => $request->query('period'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'tenant_id' => $request->query('tenant_id'),
            'source_id' => $request->query('source_id'),
            'event_type' => $request->query('event_type'),
            'status' => $request->query('status'),
        ];
    }
}
