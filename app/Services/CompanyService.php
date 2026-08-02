<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

use HPucca\Platform\Models\Tenant;
use HPucca\Platform\Repositories\CompanyRepositoryContract;

final readonly class CompanyService
{
    private const VALID_STATUSES = ['active', 'inactive'];

    public function __construct(
        private CompanyRepositoryContract $companies,
        private PublicCodeGenerator $codes,
    ) {
    }

    /**
     * @return array{companies: Tenant[], total: int, page: int, pages: int, search: string, perPage: int}
     */
    public function list(string $search, int $page, int $perPage = 15): array
    {
        $search = trim($search);
        $total = $this->companies->count($search);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        return [
            'companies' => $this->companies->paginate($search, $page, $perPage),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'search' => $search,
            'perPage' => $perPage,
        ];
    }

    public function find(int $id): ?Tenant
    {
        return $this->companies->findById($id);
    }

    /**
     * @param array<string, string> $input
     * @return array{company: Tenant|null, errors: array<string, string>, values: array<string, string>}
     */
    public function create(array $input): array
    {
        [$values, $errors] = $this->validate($input);

        if ($errors !== []) {
            return ['company' => null, 'errors' => $errors, 'values' => $values];
        }

        return [
            'company' => $this->companies->create($values, $this->codes),
            'errors' => [],
            'values' => $values,
        ];
    }

    /**
     * @param array<string, string> $input
     * @return array{company: Tenant|null, errors: array<string, string>, values: array<string, string>}
     */
    public function update(int $id, array $input, ?int $currentTenantId = null): array
    {
        if ($this->companies->findById($id) === null) {
            return [
                'company' => null,
                'errors' => ['general' => 'Empresa nao encontrada.'],
                'values' => $this->values($input),
            ];
        }

        [$values, $errors] = $this->validate($input, $id);

        if ($errors !== []) {
            return ['company' => null, 'errors' => $errors, 'values' => $values];
        }

        if ($currentTenantId !== null && $id === $currentTenantId && $values['status'] === 'inactive') {
            return [
                'company' => null,
                'errors' => ['status' => 'Nao e possivel inativar a empresa atual.'],
                'values' => $values,
            ];
        }

        return [
            'company' => $this->companies->update($id, $values),
            'errors' => [],
            'values' => $values,
        ];
    }

    public function activate(int $id): bool
    {
        return $this->companies->activate($id);
    }

    public function deactivate(int $id, ?int $currentTenantId): bool
    {
        if ($currentTenantId !== null && $id === $currentTenantId) {
            return false;
        }

        return $this->companies->deactivate($id);
    }

    /**
     * @param array<string, string> $input
     * @return array{0: array{name: string, slug: string, status: string}, 1: array<string, string>}
     */
    private function validate(array $input, ?int $ignoreId = null): array
    {
        $values = $this->values($input);
        $errors = [];

        if (strlen($values['name']) < 2 || strlen($values['name']) > 150) {
            $errors['name'] = 'Informe um nome entre 2 e 150 caracteres.';
        }

        if ($values['slug'] === '' || !preg_match('/^[a-z0-9-]+$/', $values['slug'])) {
            $errors['slug'] = 'Use apenas letras minusculas, numeros e hifen.';
        } elseif ($this->companies->slugExists($values['slug'], $ignoreId)) {
            $errors['slug'] = 'O slug informado ja esta em uso.';
        }

        if (!in_array($values['status'], self::VALID_STATUSES, true)) {
            $errors['status'] = 'Status invalido.';
        }

        return [$values, $errors];
    }

    /**
     * @param array<string, string> $input
     * @return array{name: string, slug: string, status: string}
     */
    private function values(array $input): array
    {
        return [
            'name' => preg_replace('/\s+/', ' ', trim($input['name'] ?? '')) ?? '',
            'slug' => strtolower(trim($input['slug'] ?? '')),
            'status' => trim($input['status'] ?? 'active'),
        ];
    }
}
