<?php

declare(strict_types=1);

namespace HPucca\Platform\Controllers;

use HPucca\Platform\Core\Config;
use HPucca\Platform\Core\Database;
use HPucca\Platform\Core\Request;
use HPucca\Platform\Core\Response;
use HPucca\Platform\Core\View;
use HPucca\Platform\Models\Event;
use HPucca\Platform\Repositories\EventRepository;
use HPucca\Platform\Repositories\IntegrationSourceRepository;
use HPucca\Platform\Repositories\TenantRepository;
use HPucca\Platform\Services\AuthService;
use HPucca\Platform\Services\EventQueryService;
use HPucca\Platform\Services\FlashService;
use RuntimeException;
use Throwable;

final readonly class EventController
{
    public function __construct(
        private Database $database,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->admin('events/index.php', [
            'title' => 'Eventos',
            'activeMenu' => 'events',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Eventos' => null],
            ...$this->service()->list($this->filters($request), $request->integerQuery('page')),
        ]);
    }

    public function show(Request $request): Response
    {
        $event = $this->service()->find(max(0, (int) ($request->param('id') ?? 0)));

        if (!$event instanceof Event) {
            FlashService::add('Evento nao encontrado.', 'error');

            return Response::redirect('/admin/events');
        }

        return $this->admin('events/show.php', [
            'title' => 'Evento',
            'activeMenu' => 'events',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Eventos' => '/admin/events', $event->code => null],
            'event' => $event,
        ]);
    }

    private function service(): EventQueryService
    {
        try {
            $connection = $this->database->connection();

            return new EventQueryService(
                new EventRepository($connection),
                new IntegrationSourceRepository($connection),
                new TenantRepository($connection),
            );
        } catch (Throwable) {
            throw new RuntimeException('Event module unavailable.');
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
            'search' => $request->query('search'),
            'tenant_id' => $request->query('tenant_id'),
            'source_id' => $request->query('source_id'),
            'event_type' => $request->query('event_type'),
            'status' => $request->query('status'),
            'received_from' => $request->query('received_from'),
            'received_to' => $request->query('received_to'),
        ];
    }
}
