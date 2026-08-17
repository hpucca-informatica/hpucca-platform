<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

use HPucca\Platform\Core\Config;
use HPucca\Platform\Models\IntegrationDestination;
use HPucca\Platform\Repositories\IntegrationDestinationRepositoryContract;
use HPucca\Platform\Repositories\TenantRepository;

final readonly class IntegrationDestinationService
{
    private const VALID_TYPES = ['n8n'];
    private const VALID_STATUSES = ['active', 'inactive'];

    public function __construct(
        private IntegrationDestinationRepositoryContract $destinations,
        private TenantRepository $tenants,
        private PublicCodeGenerator $codes,
    ) {
    }

    public function list(string $search, ?int $tenantId, int $page, int $perPage = 15): array
    {
        $search = trim($search);
        $total = $this->destinations->count($search, $tenantId);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        return [
            'destinations' => $this->destinations->paginate($search, $tenantId, $page, $perPage),
            'tenants' => $this->tenants->all(),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'search' => $search,
            'tenantId' => $tenantId,
            'perPage' => $perPage,
        ];
    }

    public function find(int $id): ?IntegrationDestination
    {
        return $this->destinations->findById($id);
    }

    public function tenants(): array
    {
        return $this->tenants->all();
    }

    public function activeForTenant(int $tenantId): array
    {
        return $this->destinations->findByTenant($tenantId, true);
    }

    public function availableForSourceForm(?int $tenantId, ?int $currentDestinationId = null): array
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

    public function create(array $input): array
    {
        [$values, $errors] = $this->validate($input);

        if ($errors !== []) {
            return ['destination' => null, 'errors' => $errors, 'values' => $values];
        }

        return [
            'destination' => $this->destinations->create($values, $this->codes),
            'errors' => [],
            'values' => $values,
        ];
    }

    public function update(int $id, array $input): array
    {
        if ($this->destinations->findById($id) === null) {
            return [
                'destination' => null,
                'errors' => ['general' => 'Destino de integracao nao encontrado.'],
                'values' => $this->values($input),
            ];
        }

        [$values, $errors] = $this->validate($input, $id);

        if ($errors !== []) {
            return ['destination' => null, 'errors' => $errors, 'values' => $values];
        }

        return [
            'destination' => $this->destinations->update($id, $values),
            'errors' => [],
            'values' => $values,
        ];
    }

    public function activate(int $id): bool
    {
        return $this->destinations->activate($id);
    }

    public function deactivate(int $id): bool
    {
        return $this->destinations->deactivate($id);
    }

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
            $existing = $tenantId > 0 ? $this->destinations->findByTenantAndSlug($tenantId, $values['slug']) : null;
            if ($existing instanceof IntegrationDestination && $existing->id !== $ignoreId) {
                $errors['slug'] = 'O slug informado ja esta em uso nesta empresa.';
            }
        }

        if (!in_array($values['type'], self::VALID_TYPES, true)) {
            $errors['type'] = 'Tipo invalido.';
        }

        if (!in_array($values['status'], self::VALID_STATUSES, true)) {
            $errors['status'] = 'Status invalido.';
        }

        if ($values['type'] === 'n8n') {
            $webhookUrl = $values['webhook_url'];
            $timeoutSeconds = ctype_digit((string) $values['timeout_seconds']) ? (int) $values['timeout_seconds'] : 0;

            if ($webhookUrl === '') {
                $errors['webhook_url'] = 'Informe a URL do webhook.';
            } elseif (!(new WebhookUrlValidator((string) Config::get('app.env', 'local')))->isValid($webhookUrl)) {
                $errors['webhook_url'] = 'Informe uma URL externa segura para o webhook.';
            }

            if ((string) $values['timeout_seconds'] === '' || $timeoutSeconds < 1 || $timeoutSeconds > 30) {
                $errors['timeout_seconds'] = 'Informe um timeout entre 1 e 30 segundos.';
            }
        }

        $values['config'] = json_encode([
            'webhook_url' => $values['webhook_url'],
            'timeout_seconds' => (int) $values['timeout_seconds'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        return [$values, $errors];
    }

    private function values(array $input): array
    {
        $config = [];
        if (is_string($input['config'] ?? null)) {
            $decoded = json_decode($input['config'], true);
            $config = is_array($decoded) ? $decoded : [];
        }

        $timeout = trim((string) ($input['timeout_seconds'] ?? ($config['timeout_seconds'] ?? '10')));

        return [
            'tenant_id' => trim($input['tenant_id'] ?? ''),
            'name' => preg_replace('/\s+/', ' ', trim($input['name'] ?? '')) ?? '',
            'slug' => strtolower(trim($input['slug'] ?? '')),
            'type' => trim($input['type'] ?? 'n8n'),
            'status' => trim($input['status'] ?? 'active'),
            'webhook_url' => trim((string) ($input['webhook_url'] ?? ($config['webhook_url'] ?? ''))),
            'timeout_seconds' => $timeout === '' ? '10' : $timeout,
            'config' => '{}',
        ];
    }
}
