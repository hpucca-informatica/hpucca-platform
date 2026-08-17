<?php

declare(strict_types=1);

use HPucca\Platform\Controllers\IntegrationDestinationController;
use HPucca\Platform\Core\Database;
use HPucca\Platform\Core\Request;
use HPucca\Platform\Core\Response;
use HPucca\Platform\Middleware\AuthMiddleware;
use HPucca\Platform\Middleware\OwnerMiddleware;
use HPucca\Platform\Models\IntegrationDestination;
use HPucca\Platform\Repositories\IntegrationDestinationRepositoryContract;
use HPucca\Platform\Repositories\TenantRepository;
use HPucca\Platform\Services\AuthService;
use HPucca\Platform\Services\CsrfService;
use HPucca\Platform\Services\IntegrationDestinationService;
use HPucca\Platform\Services\PublicCodeGenerator;

require dirname(__DIR__) . '/vendor/autoload.php';

ini_set('session.save_path', dirname(__DIR__) . '/storage/cache');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

final class IntegrationDestinationCrudStatement extends PDOStatement
{
    private array $params = [];

    public function __construct(
        private array $tenants,
        private bool $all = false,
    ) {
    }

    public function execute(?array $params = null): bool
    {
        $this->params = $params ?? [];

        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        $id = (int) ($this->params['id'] ?? 0);

        return $this->tenants[$id] ?? false;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return array_values($this->tenants);
    }
}

final class IntegrationDestinationCrudTenantPdo extends PDO
{
    /**
     * @param array<int, array<string, mixed>> $tenants
     */
    public function __construct(
        private array $tenants,
    ) {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new IntegrationDestinationCrudStatement($this->tenants);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        return new IntegrationDestinationCrudStatement($this->tenants, true);
    }
}

final class InMemoryIntegrationDestinationRepository implements IntegrationDestinationRepositoryContract
{
    /**
     * @var array<int, IntegrationDestination>
     */
    public array $destinations = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $lastCreateData = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $lastUpdateData = null;

    public function __construct()
    {
        $this->destinations[1] = $this->destination(1, 'DST000001', 1, 'Destino A', 'destino-a', 'n8n', 'active');
        $this->destinations[2] = $this->destination(2, 'DST000002', 1, 'Destino Inativo', 'destino-inativo', 'n8n', 'inactive');
        $this->destinations[3] = $this->destination(3, 'DST000003', 2, 'Destino B', 'destino-b', 'n8n', 'active');
    }

    public function paginate(string $search, ?int $tenantId, int $page, int $perPage): array
    {
        return array_values(array_filter(
            $this->destinations,
            static fn (IntegrationDestination $destination): bool => $tenantId === null || $destination->tenantId === $tenantId,
        ));
    }

    public function count(string $search, ?int $tenantId): int
    {
        return count($this->paginate($search, $tenantId, 1, PHP_INT_MAX));
    }

    public function findById(int $id): ?IntegrationDestination
    {
        return $this->destinations[$id] ?? null;
    }

    public function findByCode(string $code): ?IntegrationDestination
    {
        foreach ($this->destinations as $destination) {
            if ($destination->code === $code) {
                return $destination;
            }
        }

        return null;
    }

    public function findByTenantAndSlug(int $tenantId, string $slug): ?IntegrationDestination
    {
        foreach ($this->destinations as $destination) {
            if ($destination->tenantId === $tenantId && $destination->slug === $slug) {
                return $destination;
            }
        }

        return null;
    }

    public function findByTenant(int $tenantId, bool $activeOnly = false): array
    {
        return array_values(array_filter(
            $this->destinations,
            static fn (IntegrationDestination $destination): bool => $destination->tenantId === $tenantId
                && (!$activeOnly || $destination->status === 'active'),
        ));
    }

    public function findBySourceId(int $sourceId): ?IntegrationDestination
    {
        return null;
    }

    public function create(array $data, PublicCodeGenerator $codes): IntegrationDestination
    {
        $this->lastCreateData = $data;
        $id = max(array_keys($this->destinations)) + 1;
        $destination = $this->destination($id, PublicCodeGenerator::format('DST', $id), (int) $data['tenant_id'], $data['name'], $data['slug'], $data['type'], $data['status'], $data['config']);
        $this->destinations[$id] = $destination;

        return $destination;
    }

    public function update(int $id, array $data): IntegrationDestination
    {
        $this->lastUpdateData = $data;
        $current = $this->destinations[$id];
        $this->destinations[$id] = $this->destination($id, $current->code, (int) $data['tenant_id'], $data['name'], $data['slug'], $data['type'], $data['status'], $data['config'], $current->createdAt, '2026-01-02');

        return $this->destinations[$id];
    }

    public function activate(int $id): bool
    {
        return $this->changeStatus($id, 'active');
    }

    public function deactivate(int $id): bool
    {
        return $this->changeStatus($id, 'inactive');
    }

    private function changeStatus(int $id, string $status): bool
    {
        $current = $this->destinations[$id] ?? null;

        if (!$current instanceof IntegrationDestination) {
            return false;
        }

        $this->destinations[$id] = $this->destination($current->id, $current->code, $current->tenantId, $current->name, $current->slug, $current->type, $status, $current->config, $current->createdAt, '2026-01-02');

        return true;
    }

    private function destination(
        int $id,
        string $code,
        int $tenantId,
        string $name,
        string $slug,
        string $type,
        string $status,
        string $config = '{}',
        string $createdAt = '2026-01-01',
        string $updatedAt = '2026-01-01',
    ): IntegrationDestination {
        return new IntegrationDestination($id, $code, $tenantId, 'Empresa ' . $tenantId, 'active', $name, $slug, $type, $status, $config, $createdAt, $updatedAt);
    }
}

function integrationDestinationCrudTenants(): TenantRepository
{
    return new TenantRepository(new IntegrationDestinationCrudTenantPdo([
        1 => ['id' => 1, 'code' => 'TEN000001', 'name' => 'Empresa A', 'slug' => 'empresa-a', 'status' => 'active', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01'],
        2 => ['id' => 2, 'code' => 'TEN000002', 'name' => 'Empresa B', 'slug' => 'empresa-b', 'status' => 'active', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01'],
    ]));
}

function integrationDestinationCrudStatus(Response $response): int
{
    $property = new ReflectionProperty($response, 'statusCode');
    $property->setAccessible(true);

    return (int) $property->getValue($response);
}

$repository = new InMemoryIntegrationDestinationRepository();
$service = new IntegrationDestinationService(
    $repository,
    integrationDestinationCrudTenants(),
    (new ReflectionClass(PublicCodeGenerator::class))->newInstanceWithoutConstructor(),
);

$_SESSION = [];
$unauthenticated = (new AuthMiddleware(new AuthService()))->handle(static fn (): Response => Response::html('protected'));
assert(integrationDestinationCrudStatus($unauthenticated) === 302);

foreach (['admin', 'manager', 'user'] as $type) {
    $_SESSION = ['user_id' => 2, 'tenant_id' => 1, 'type' => $type, 'name' => 'Blocked'];
    $blocked = (new OwnerMiddleware())->handle(static fn (): Response => Response::html('owner'));
    assert(integrationDestinationCrudStatus($blocked) === 403);
}

$_SESSION = ['user_id' => 1, 'tenant_id' => 1, 'type' => 'owner', 'name' => 'Owner'];
$owner = (new OwnerMiddleware())->handle(static fn (): Response => Response::html('owner'));
assert(integrationDestinationCrudStatus($owner) === 200);

$created = $service->create([
    'tenant_id' => '1',
    'name' => 'Destino Novo',
    'slug' => 'destino-novo',
    'type' => 'n8n',
    'status' => 'active',
    'webhook_url' => 'https://n8n.example.com/webhook/novo',
    'timeout_seconds' => '10',
    'code' => 'DST999999',
    'created_at' => '2099-01-01',
    'updated_at' => '2099-01-01',
]);
assert($created['destination'] instanceof IntegrationDestination);
assert($created['destination']->code === 'DST000004');
assert($repository->lastCreateData['config'] === '{"webhook_url":"https://n8n.example.com/webhook/novo","timeout_seconds":10}');
assert(!array_key_exists('code', $repository->lastCreateData ?? []));
assert(!array_key_exists('created_at', $repository->lastCreateData ?? []));
assert(!array_key_exists('updated_at', $repository->lastCreateData ?? []));

$updated = $service->update(4, [
    'tenant_id' => '1',
    'name' => 'Destino Editado',
    'slug' => 'destino-editado',
    'type' => 'n8n',
    'status' => 'inactive',
    'webhook_url' => 'https://n8n.example.com/webhook/editado?secret=hidden',
    'timeout_seconds' => '12',
    'code' => 'DST999999',
    'created_at' => '2099-01-01',
    'updated_at' => '2099-01-01',
]);
assert($updated['destination'] instanceof IntegrationDestination);
assert($updated['destination']->code === 'DST000004');
assert($updated['destination']->status === 'inactive');
assert($repository->lastUpdateData['config'] === '{"webhook_url":"https://n8n.example.com/webhook/editado?secret=hidden","timeout_seconds":12}');
assert(!array_key_exists('code', $repository->lastUpdateData ?? []));
assert(!array_key_exists('created_at', $repository->lastUpdateData ?? []));
assert(!array_key_exists('updated_at', $repository->lastUpdateData ?? []));

$validConfig = ['webhook_url' => 'https://n8n.example.com/webhook/teste', 'timeout_seconds' => '10'];

$invalidSlug = $service->create(['tenant_id' => '1', 'name' => 'Destino', 'slug' => 'Destino Invalido', 'type' => 'n8n', 'status' => 'active', ...$validConfig]);
assert(isset($invalidSlug['errors']['slug']));

$invalidType = $service->create(['tenant_id' => '1', 'name' => 'Destino', 'slug' => 'destino-type', 'type' => 'webhook', 'status' => 'active', ...$validConfig]);
assert(isset($invalidType['errors']['type']));

$invalidStatus = $service->create(['tenant_id' => '1', 'name' => 'Destino', 'slug' => 'destino-status', 'type' => 'n8n', 'status' => 'paused', ...$validConfig]);
assert(isset($invalidStatus['errors']['status']));

$invalidWebhook = $service->create(['tenant_id' => '1', 'name' => 'Destino', 'slug' => 'destino-webhook', 'type' => 'n8n', 'status' => 'active', 'webhook_url' => 'https://localhost/webhook', 'timeout_seconds' => '10']);
assert(isset($invalidWebhook['errors']['webhook_url']));

$invalidTimeout = $service->create(['tenant_id' => '1', 'name' => 'Destino', 'slug' => 'destino-timeout', 'type' => 'n8n', 'status' => 'active', 'webhook_url' => 'https://n8n.example.com/webhook/teste', 'timeout_seconds' => '31']);
assert(isset($invalidTimeout['errors']['timeout_seconds']));

assert($service->activate(2) === true);
assert($repository->findById(2)?->status === 'active');
assert($service->deactivate(1) === true);
assert($repository->findById(1)?->status === 'inactive');
assert($service->activate(999) === false);
assert($service->deactivate(999) === false);

$_POST = [];
$missingCsrf = (new IntegrationDestinationController(new Database([])))->store(new Request('POST', '/admin/integration-destinations'));
assert(integrationDestinationCrudStatus($missingCsrf) === 403);

$_POST = [CsrfService::FIELD => 'invalid'];
$invalidCsrf = (new IntegrationDestinationController(new Database([])))->store(new Request('POST', '/admin/integration-destinations'));
assert(integrationDestinationCrudStatus($invalidCsrf) === 403);

echo 'IntegrationDestinationCrudTest passed.' . PHP_EOL;
