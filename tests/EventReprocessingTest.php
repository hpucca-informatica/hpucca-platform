<?php

declare(strict_types=1);

use HPucca\Platform\Controllers\EventController;
use HPucca\Platform\Core\Database;
use HPucca\Platform\Core\Request;
use HPucca\Platform\Core\Response;
use HPucca\Platform\Core\View;
use HPucca\Platform\Models\Event;
use HPucca\Platform\Middleware\AuthMiddleware;
use HPucca\Platform\Middleware\OwnerMiddleware;
use HPucca\Platform\Repositories\EventRepositoryContract;
use HPucca\Platform\Services\AuthService;
use HPucca\Platform\Services\CsrfService;
use HPucca\Platform\Services\EventDispatcher;
use HPucca\Platform\Services\EventProcessorContract;
use HPucca\Platform\Services\EventService;
use HPucca\Platform\Services\FlashService;
use HPucca\Platform\Services\PublicCodeGenerator;

require dirname(__DIR__) . '/vendor/autoload.php';

ini_set('session.save_path', dirname(__DIR__) . '/storage/cache');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

final class EventReprocessingMemoryRepository implements EventRepositoryContract
{
    /**
     * @var array<int, Event>
     */
    public array $events = [];

    public function paginate(array $filters, int $page, int $perPage): array
    {
        return array_values($this->events);
    }

    public function count(array $filters): int
    {
        return count($this->events);
    }

    public function findById(int $id): ?Event
    {
        return $this->events[$id] ?? null;
    }

    public function findDuplicate(int $sourceId, string $externalId, string $eventType): ?Event
    {
        return null;
    }

    public function reserveNextPending(): ?Event
    {
        foreach ($this->events as $event) {
            if ($event->status === 'pending') {
                $reserved = $this->copy($event, status: 'processing', attempts: $event->attempts + 1);
                $this->events[$event->id] = $reserved;

                return $reserved;
            }
        }

        return null;
    }

    public function markProcessed(int $eventId, DateTimeImmutable $processedAt): bool
    {
        $event = $this->events[$eventId] ?? null;

        if (!$event instanceof Event || $event->status !== 'processing') {
            return false;
        }

        $this->events[$eventId] = $this->copy(
            $event,
            status: 'processed',
            processedAt: $processedAt->format('Y-m-d H:i:s'),
            failedAt: null,
            lastError: null,
        );

        return true;
    }

    public function markFailed(int $eventId, string $sanitizedError, DateTimeImmutable $failedAt): bool
    {
        return false;
    }

    public function requeueFailed(int $eventId): bool
    {
        $event = $this->events[$eventId] ?? null;

        if (!$event instanceof Event || $event->status !== 'failed') {
            return false;
        }

        $this->events[$eventId] = $this->copy(
            $event,
            status: 'pending',
            availableAt: '2026-08-07 12:00:00',
            failedAt: null,
            lastError: null,
        );

        return true;
    }

    public function recoverStaleProcessing(int $timeoutMinutes): int
    {
        throw new RuntimeException('Not used.');
    }

    public function create(array $data, PublicCodeGenerator $codes): array
    {
        throw new RuntimeException('Not used.');
    }

    private function copy(
        Event $event,
        ?string $status = null,
        ?int $attempts = null,
        ?string $availableAt = null,
        ?string $processedAt = null,
        ?string $failedAt = null,
        ?string $lastError = null,
    ): Event {
        return new Event(
            $event->id,
            $event->code,
            $event->tenantId,
            $event->tenantName,
            $event->integrationSourceId,
            $event->integrationSourceName,
            $event->eventType,
            $event->externalId,
            $event->payload,
            $status ?? $event->status,
            $attempts ?? $event->attempts,
            $availableAt ?? $event->availableAt,
            $processedAt,
            $failedAt,
            $lastError,
            $event->occurredAt,
            $event->receivedAt,
            $event->createdAt,
            '2026-08-07 12:00:00',
        );
    }
}

final readonly class SuccessfulEventProcessor implements EventProcessorContract
{
    public function process(Event $event): void
    {
    }
}

function reprocessingEvent(
    int $id,
    string $status,
    int $attempts = 1,
    ?string $processedAt = null,
    ?string $failedAt = '2026-08-07 11:00:00',
    ?string $lastError = 'Event processing failed.',
): Event {
    return new Event(
        $id,
        PublicCodeGenerator::format('EVT', $id),
        10,
        'Empresa A',
        20,
        'Fonte A',
        'lead.created',
        'EXT-' . $id,
        '{"name":"Teste"}',
        $status,
        $attempts,
        '2026-08-07 10:00:00',
        $processedAt,
        $failedAt,
        $lastError,
        null,
        '2026-08-07 09:00:00',
        '2026-08-07 09:00:00',
        '2026-08-07 11:00:00',
    );
}

function responseStatus(Response $response): int
{
    $property = new ReflectionProperty($response, 'statusCode');
    $property->setAccessible(true);

    return (int) $property->getValue($response);
}

function responseHeader(Response $response, string $name): string
{
    $property = new ReflectionProperty($response, 'headers');
    $property->setAccessible(true);
    $headers = $property->getValue($response);

    return is_array($headers) && is_string($headers[$name] ?? null) ? $headers[$name] : '';
}

function eventReprocessingOwnerSession(): void
{
    $_SESSION = [
        'user_id' => 1,
        'user_code' => 'USR000001',
        'tenant_id' => 1,
        'tenant_name' => 'Empresa A',
        'login' => 'owner',
        'name' => 'Owner',
        'type' => 'owner',
    ];
}

function eventReprocessingController(): EventController
{
    return new EventController(new Database([
        'host' => 'invalid',
        'port' => 5432,
        'name' => 'invalid',
        'user' => 'invalid',
        'password' => 'invalid',
        'sslmode' => 'disable',
    ]));
}

$_SESSION = [];
$unauthenticated = (new AuthMiddleware(new AuthService()))->handle(static fn (): Response => Response::html('owner'));
assert(responseStatus($unauthenticated) === 302);
assert(responseHeader($unauthenticated, 'Location') === '/login');

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
    assert(responseStatus($forbidden) === 403);
}

eventReprocessingOwnerSession();
$allowed = (new OwnerMiddleware())->handle(static fn (): Response => Response::html('owner'));
assert(responseStatus($allowed) === 200);

$csrfRejectedEvent = reprocessingEvent(2, 'failed', attempts: 7);

eventReprocessingOwnerSession();
CsrfService::token();
$_POST = [];
$missingCsrf = eventReprocessingController()->reprocess(new Request('POST', '/admin/events/2/reprocess', ['id' => '2']));
$missingCsrfFlash = FlashService::consume();
assert(responseStatus($missingCsrf) === 302);
assert(responseHeader($missingCsrf, 'Location') === '/admin/events/2');
assert($csrfRejectedEvent->status === 'failed');
assert($csrfRejectedEvent->attempts === 7);
assert(!in_array(['type' => 'success', 'message' => 'Evento recolocado na fila para reprocessamento.'], $missingCsrfFlash, true));

eventReprocessingOwnerSession();
CsrfService::token();
$_POST = [CsrfService::FIELD => 'invalid-token'];
$invalidCsrf = eventReprocessingController()->reprocess(new Request('POST', '/admin/events/2/reprocess', ['id' => '2']));
$invalidCsrfFlash = FlashService::consume();
assert(responseStatus($invalidCsrf) === 302);
assert(responseHeader($invalidCsrf, 'Location') === '/admin/events/2');
assert($csrfRejectedEvent->status === 'failed');
assert($csrfRejectedEvent->attempts === 7);
assert(!in_array(['type' => 'success', 'message' => 'Evento recolocado na fila para reprocessamento.'], $invalidCsrfFlash, true));

$_POST = [];
$repository = new EventReprocessingMemoryRepository();
$service = new EventService($repository);

assert($service->reprocess(404)['status'] === 'not_found');

foreach (['processed', 'pending', 'processing'] as $status) {
    $repository->events = [1 => reprocessingEvent(1, $status, processedAt: $status === 'processed' ? '2026-08-07 11:00:00' : null)];
    assert($service->reprocess(1)['status'] === 'invalid_state');
    assert($repository->events[1]->status === $status);
}

$failed = reprocessingEvent(2, 'failed', attempts: 1);
$repository->events = [2 => $failed];
$result = $service->reprocess(2);
$requeued = $repository->events[2];

assert($result['status'] === 'requeued');
assert($requeued->status === 'pending');
assert($requeued->availableAt === '2026-08-07 12:00:00');
assert($requeued->failedAt === null);
assert($requeued->lastError === null);
assert($requeued->processedAt === null);
assert($requeued->attempts === 1);
assert($requeued->payload === $failed->payload);
assert($requeued->code === $failed->code);
assert($requeued->tenantId === $failed->tenantId);
assert($requeued->integrationSourceId === $failed->integrationSourceId);
assert($requeued->externalId === $failed->externalId);
assert($requeued->eventType === $failed->eventType);

$dispatcher = new EventDispatcher($repository, new SuccessfulEventProcessor());
$dispatched = $dispatcher->dispatchOnce();
assert($dispatched['status'] === 'processed');
assert($repository->events[2]->status === 'processed');
assert($repository->events[2]->attempts === 2);

$failedBody = View::render('events/show.php', [
    'event' => reprocessingEvent(3, 'failed'),
    'csrfField' => '<input type="hidden" name="_csrf" value="token">',
]);
assert(str_contains($failedBody, 'Reprocessar evento'));
assert(str_contains($failedBody, 'method="post"'));
assert(str_contains($failedBody, '/admin/events/3/reprocess'));
assert(str_contains($failedBody, 'name="_csrf"'));
assert(str_contains($failedBody, 'Reprocessar nao executa imediatamente'));

foreach (['pending', 'processing', 'processed'] as $status) {
    $body = View::render('events/show.php', [
        'event' => reprocessingEvent(4, $status),
        'csrfField' => '<input type="hidden" name="_csrf" value="token">',
    ]);
    assert(!str_contains($body, 'Reprocessar evento'));
}

$processingBody = View::render('events/show.php', ['event' => reprocessingEvent(5, 'processing')]);
assert(str_contains($processingBody, 'Evento em processamento.'));
assert(str_contains($processingBody, 'pode permanecer em processing'));

$repositorySql = (string) file_get_contents(dirname(__DIR__) . '/app/Repositories/EventRepository.php');
assert(str_contains($repositorySql, 'requeueFailed'));
assert(str_contains($repositorySql, 'WHERE id = :id'));
assert(str_contains($repositorySql, "AND status = 'failed'"));
assert(str_contains($repositorySql, 'available_at = CURRENT_TIMESTAMP'));
assert(!str_contains($repositorySql, 'attempts = 0'));

$routes = (string) file_get_contents(dirname(__DIR__) . '/routes/api.php');
assert(str_contains($routes, "\$router->post('/admin/events/{id}/reprocess'"));
assert(str_contains($routes, 'AuthMiddleware'));
assert(str_contains($routes, 'OwnerMiddleware'));

$controller = (string) file_get_contents(dirname(__DIR__) . '/app/Controllers/EventController.php');
assert(str_contains($controller, 'CsrfService::validate'));
assert(!str_contains($controller, 'EventDispatcher('));

echo 'EventReprocessingTest passed.' . PHP_EOL;
