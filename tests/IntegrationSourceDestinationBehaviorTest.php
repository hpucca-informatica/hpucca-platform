<?php

declare(strict_types=1);

use HPucca\Platform\Models\IntegrationDestination;
use HPucca\Platform\Models\IntegrationSource;
use HPucca\Platform\Repositories\IntegrationDestinationRepositoryContract;
use HPucca\Platform\Repositories\IntegrationSourceRepositoryContract;
use HPucca\Platform\Repositories\TenantRepository;
use HPucca\Platform\Services\ApiKeyService;
use HPucca\Platform\Services\IntegrationSourceService;
use HPucca\Platform\Services\PublicCodeGenerator;

require dirname(__DIR__) . '/vendor/autoload.php';

final class SourceDestinationTenantStatement extends PDOStatement
{
    private array $params = [];

    public function __construct(
        private array $tenants,
    ) {
    }

    public function execute(?array $params = null): bool
    {
        $this->params = $params ?? [];

        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->tenants[(int) ($this->params['id'] ?? 0)] ?? false;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return array_values($this->tenants);
    }
}

final class SourceDestinationTenantPdo extends PDO
{
    public function __construct(
        private array $tenants,
    ) {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new SourceDestinationTenantStatement($this->tenants);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        return new SourceDestinationTenantStatement($this->tenants);
    }
}

final class InMemorySourceDestinationRepository implements IntegrationDestinationRepositoryContract
{
    /**
     * @var array<int, IntegrationDestination>
     */
    public array $destinations = [];

    public function __construct()
    {
        $this->destinations[1] = new IntegrationDestination(1, 'DST000001', 1, 'Empresa A', 'active', 'Destino A', 'destino-a', 'n8n', 'active', '{}', '2026-01-01', '2026-01-01');
        $this->destinations[2] = new IntegrationDestination(2, 'DST000002', 2, 'Empresa B', 'active', 'Destino B', 'destino-b', 'n8n', 'active', '{}', '2026-01-01', '2026-01-01');
        $this->destinations[3] = new IntegrationDestination(3, 'DST000003', 1, 'Empresa A', 'active', 'Destino Inativo', 'destino-inativo', 'n8n', 'inactive', '{}', '2026-01-01', '2026-01-01');
    }

    public function paginate(string $search, ?int $tenantId, int $page, int $perPage): array
    {
        return [];
    }

    public function count(string $search, ?int $tenantId): int
    {
        return 0;
    }

    public function findById(int $id): ?IntegrationDestination
    {
        return $this->destinations[$id] ?? null;
    }

    public function findByCode(string $code): ?IntegrationDestination
    {
        return null;
    }

    public function findByTenantAndSlug(int $tenantId, string $slug): ?IntegrationDestination
    {
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
        throw new RuntimeException('Not used.');
    }

    public function update(int $id, array $data): IntegrationDestination
    {
        throw new RuntimeException('Not used.');
    }

    public function activate(int $id): bool
    {
        return true;
    }

    public function deactivate(int $id): bool
    {
        return true;
    }
}

final class InMemoryIntegrationSourceDestinationRepository implements IntegrationSourceRepositoryContract
{
    /**
     * @var array<int, IntegrationSource>
     */
    public array $sources = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $lastData = null;

    public function __construct()
    {
        $this->sources[1] = $this->source(1, 1, 'Fonte A', 'fonte-a', null);
        $this->sources[2] = $this->source(2, 1, 'Fonte Inativa Destino', 'fonte-inativa-destino', 3);
    }

    public function paginate(string $search, ?int $tenantId, int $page, int $perPage): array
    {
        return array_values($this->sources);
    }

    public function count(string $search, ?int $tenantId): int
    {
        return count($this->sources);
    }

    public function findById(int $id): ?IntegrationSource
    {
        return $this->sources[$id] ?? null;
    }

    public function findByTenantAndSlug(int $tenantId, string $slug): ?IntegrationSource
    {
        foreach ($this->sources as $source) {
            if ($source->tenantId === $tenantId && $source->slug === $slug) {
                return $source;
            }
        }

        return null;
    }

    public function sourcesForAuthentication(): array
    {
        return array_values($this->sources);
    }

    public function create(array $data, PublicCodeGenerator $codes): IntegrationSource
    {
        $this->lastData = $data;
        $id = max(array_keys($this->sources)) + 1;
        $this->sources[$id] = $this->source($id, (int) $data['tenant_id'], $data['name'], $data['slug'], $data['destination_id'] === '' ? null : (int) $data['destination_id']);

        return $this->sources[$id];
    }

    public function update(int $id, array $data): IntegrationSource
    {
        $this->lastData = $data;
        $this->sources[$id] = $this->source($id, (int) $data['tenant_id'], $data['name'], $data['slug'], $data['destination_id'] === '' ? null : (int) $data['destination_id']);

        return $this->sources[$id];
    }

    public function activate(int $id): bool
    {
        return true;
    }

    public function deactivate(int $id): bool
    {
        return true;
    }

    public function touchLastUsedAt(int $id): void
    {
    }

    private function source(int $id, int $tenantId, string $name, string $slug, ?int $destinationId): IntegrationSource
    {
        $destinationCode = $destinationId === null ? null : PublicCodeGenerator::format('DST', $destinationId);
        $destinationStatus = $destinationId === 3 ? 'inactive' : ($destinationId === null ? null : 'active');

        return new IntegrationSource(
            $id,
            PublicCodeGenerator::format('SRC', $id),
            $tenantId,
            'Empresa ' . $tenantId,
            'active',
            $name,
            $slug,
            'hash',
            'active',
            null,
            $destinationId,
            $destinationCode,
            $destinationId === null ? null : 'Destino ' . $destinationId,
            $destinationId === null ? null : 'n8n',
            $destinationStatus,
            '2026-01-01',
            '2026-01-01',
        );
    }
}

function sourceDestinationTenants(): TenantRepository
{
    return new TenantRepository(new SourceDestinationTenantPdo([
        1 => ['id' => 1, 'code' => 'TEN000001', 'name' => 'Empresa A', 'slug' => 'empresa-a', 'status' => 'active', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01'],
        2 => ['id' => 2, 'code' => 'TEN000002', 'name' => 'Empresa B', 'slug' => 'empresa-b', 'status' => 'active', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01'],
    ]));
}

$sources = new InMemoryIntegrationSourceDestinationRepository();
$destinations = new InMemorySourceDestinationRepository();
$service = new IntegrationSourceService(
    $sources,
    $destinations,
    sourceDestinationTenants(),
    (new ReflectionClass(PublicCodeGenerator::class))->newInstanceWithoutConstructor(),
    new ApiKeyService(),
);

$withoutDestination = $service->create(['tenant_id' => '1', 'name' => 'Fonte Sem Destino', 'slug' => 'fonte-sem-destino', 'status' => 'active', 'destination_id' => '']);
assert($withoutDestination['source'] instanceof IntegrationSource);
assert($withoutDestination['source']->destinationId === null);
assert($sources->lastData['destination_id'] === '');

$validDestination = $service->create(['tenant_id' => '1', 'name' => 'Fonte Com Destino', 'slug' => 'fonte-com-destino', 'status' => 'active', 'destination_id' => '1']);
assert($validDestination['source'] instanceof IntegrationSource);
assert($validDestination['source']->destinationId === 1);

$crossTenant = $service->create(['tenant_id' => '1', 'name' => 'Fonte Cross Tenant', 'slug' => 'fonte-cross-tenant', 'status' => 'active', 'destination_id' => '2']);
assert($crossTenant['source'] === null);
assert(isset($crossTenant['errors']['destination_id']));

$removed = $service->update(1, ['tenant_id' => '1', 'name' => 'Fonte A', 'slug' => 'fonte-a', 'status' => 'active', 'destination_id' => '']);
assert($removed['source'] instanceof IntegrationSource);
assert($removed['source']->destinationId === null);

$tenantChangedWithOldDestination = $service->update(1, ['tenant_id' => '2', 'name' => 'Fonte A', 'slug' => 'fonte-a-tenant-b', 'status' => 'active', 'destination_id' => '1']);
assert($tenantChangedWithOldDestination['source'] === null);
assert(isset($tenantChangedWithOldDestination['errors']['destination_id']));

$activeOptions = $service->destinationsForForm(1);
assert(array_map(static fn (IntegrationDestination $destination): int => $destination->id, $activeOptions) === [1]);

$inactiveCurrentOptions = $service->destinationsForForm(1, 3);
$inactiveCurrentIds = array_map(static fn (IntegrationDestination $destination): int => $destination->id, $inactiveCurrentOptions);
assert(in_array(1, $inactiveCurrentIds, true));
assert(in_array(3, $inactiveCurrentIds, true));
assert($service->find(2)?->destinationStatus === 'inactive');

echo 'IntegrationSourceDestinationBehaviorTest passed.' . PHP_EOL;
