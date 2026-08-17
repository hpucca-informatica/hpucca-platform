<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

use HPucca\Platform\Models\IntegrationSource;
use HPucca\Platform\Models\IntegrationDestination;
use HPucca\Platform\Repositories\IntegrationDestinationRepositoryContract;
use HPucca\Platform\Repositories\IntegrationSourceRepositoryContract;
use HPucca\Platform\Repositories\TenantRepository;

final readonly class IntegrationSourceService
{
    private const VALID_STATUSES = ['active', 'inactive'];

    public function __construct(
        private IntegrationSourceRepositoryContract $sources,
        private IntegrationDestinationRepositoryContract $destinations,
        private TenantRepository $tenants,
        private PublicCodeGenerator $codes,
        private ApiKeyService $apiKeys,
    ) {
    }

    /**
     * @return array{sources: IntegrationSource[], tenants: array<int, mixed>, total: int, page: int, pages: int, search: string, tenantId: int|null, perPage: int}
     */
    public function list(string $search, ?int $tenantId, int $page, int $perPage = 15): array
    {
        $search = trim($search);
        $total = $this->sources->count($search, $tenantId);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        return [
            'sources' => $this->sources->paginate($search, $tenantId, $page, $perPage),
            'tenants' => $this->tenants->all(),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'search' => $search,
            'tenantId' => $tenantId,
            'perPage' => $perPage,
        ];
    }

    public function find(int $id): ?IntegrationSource
    {
        return $this->sources->findById($id);
    }

    /**
     * @return array<int, mixed>
     */
    public function tenants(): array
    {
        return $this->tenants->all();
    }

    public function destinationsForForm(?int $tenantId, ?int $currentDestinationId = null): array
    {
        if ($tenantId === null || $tenantId <= 0) {
            return [];
        }

        $destinations = $this->destinations->findByTenant($tenantId, true);
        $current = $currentDestinationId === null ? null : $this->destinations->findById($currentDestinationId);

        if ($current instanceof IntegrationDestination && $current->tenantId === $tenantId) {
            foreach ($destinations as $destination) {
                if ($destination->id === $current->id) {
                    return $destinations;
                }
            }

            $destinations[] = $current;
        }

        return $destinations;
    }

    /**
     * @param array<string, string> $input
     * @return array{source: IntegrationSource|null, apiKey: string|null, errors: array<string, string>, values: array<string, string>}
     */
    public function create(array $input): array
    {
        [$values, $errors] = $this->validate($input);

        if ($errors !== []) {
            return ['source' => null, 'apiKey' => null, 'errors' => $errors, 'values' => $values];
        }

        $apiKey = $this->apiKeys->generate();
        $source = $this->sources->create($values + [
            'api_key_hash' => $this->apiKeys->hash($apiKey),
        ], $this->codes);

        return ['source' => $source, 'apiKey' => $apiKey, 'errors' => [], 'values' => $values];
    }

    /**
     * @param array<string, string> $input
     * @return array{source: IntegrationSource|null, errors: array<string, string>, values: array<string, string>}
     */
    public function update(int $id, array $input): array
    {
        if ($this->sources->findById($id) === null) {
            return [
                'source' => null,
                'errors' => ['general' => 'Fonte de integracao nao encontrada.'],
                'values' => $this->values($input),
            ];
        }

        [$values, $errors] = $this->validate($input, $id);

        if ($errors !== []) {
            return ['source' => null, 'errors' => $errors, 'values' => $values];
        }

        return [
            'source' => $this->sources->update($id, $values),
            'errors' => [],
            'values' => $values,
        ];
    }

    public function activate(int $id): bool
    {
        return $this->sources->activate($id);
    }

    public function deactivate(int $id): bool
    {
        return $this->sources->deactivate($id);
    }

    /**
     * @param array<string, string> $input
     * @return array{0: array{tenant_id: string, name: string, slug: string, status: string, destination_id: string}, 1: array<string, string>}
     */
    private function validate(array $input, ?int $ignoreId = null): array
    {
        $values = $this->values($input);
        $errors = [];
        $tenantId = (int) $values['tenant_id'];

        if (!$this->tenants->findById($tenantId)) {
            $errors['tenant_id'] = 'Selecione uma empresa valida.';
        }

        if (strlen($values['name']) < 2 || strlen($values['name']) > 150) {
            $errors['name'] = 'Informe um nome entre 2 e 150 caracteres.';
        }

        if ($values['slug'] === '' || strlen($values['slug']) > 100 || !preg_match('/^[a-z0-9-]+$/', $values['slug'])) {
            $errors['slug'] = 'Use ate 100 caracteres com letras minusculas, numeros e hifen.';
        } else {
            $existing = $tenantId > 0 ? $this->sources->findByTenantAndSlug($tenantId, $values['slug']) : null;
            if ($existing instanceof IntegrationSource && $existing->id !== $ignoreId) {
                $errors['slug'] = 'O slug informado ja esta em uso nesta empresa.';
            }
        }

        if (!in_array($values['status'], self::VALID_STATUSES, true)) {
            $errors['status'] = 'Status invalido.';
        }

        if ($values['destination_id'] !== '') {
            $destination = $this->destinations->findById((int) $values['destination_id']);
            if (!$destination instanceof IntegrationDestination || $destination->tenantId !== $tenantId) {
                $errors['destination_id'] = 'Selecione um destino da mesma empresa.';
            }
        }

        return [$values, $errors];
    }

    /**
     * @param array<string, string> $input
     * @return array{tenant_id: string, name: string, slug: string, status: string, destination_id: string}
     */
    private function values(array $input): array
    {
        return [
            'tenant_id' => trim($input['tenant_id'] ?? ''),
            'name' => preg_replace('/\s+/', ' ', trim($input['name'] ?? '')) ?? '',
            'slug' => strtolower(trim($input['slug'] ?? '')),
            'status' => trim($input['status'] ?? 'active'),
            'destination_id' => trim($input['destination_id'] ?? ''),
        ];
    }
}
