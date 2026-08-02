<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

use HPucca\Platform\Models\Tenant;
use HPucca\Platform\Models\User;
use HPucca\Platform\Repositories\TenantRepository;
use HPucca\Platform\Repositories\UserRepositoryContract;

final readonly class UserService
{
    private const TYPES = ['owner', 'admin', 'manager', 'user'];
    private const STATUSES = ['active', 'inactive'];
    private const PASSWORD_MIN = 10;

    public function __construct(
        private UserRepositoryContract $users,
        private TenantRepository $tenants,
        private AuthService $auth,
        private ?PublicCodeGenerator $codes = null,
    ) {
    }

    public function currentUser(): ?User
    {
        $userId = $this->auth->userId();

        if ($userId === null) {
            return null;
        }

        return $this->users->findActiveById($userId);
    }

    public function tenantFor(User $user): ?Tenant
    {
        return $this->tenants->findActiveById($user->tenantId);
    }

    /**
     * @return array{users: array<int, array<string, mixed>>, tenants: Tenant[], total: int, page: int, pages: int, search: string, tenantId: int|null, perPage: int}
     */
    public function list(string $search, ?int $tenantId, int $page, int $perPage = 15): array
    {
        $search = trim($search);
        $tenantId = $tenantId !== null && $tenantId > 0 ? $tenantId : null;
        $total = $this->users->count($search, $tenantId);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        return [
            'users' => $this->users->paginate($search, $tenantId, $page, $perPage),
            'tenants' => $this->tenants->all(),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'search' => $search,
            'tenantId' => $tenantId,
            'perPage' => $perPage,
        ];
    }

    public function find(int $id): ?User
    {
        return $this->users->findById($id);
    }

    /**
     * @return Tenant[]
     */
    public function tenants(): array
    {
        return $this->tenants->all();
    }

    /**
     * @param array<string, string> $input
     * @return array{user: User|null, errors: array<string, string>, values: array<string, string>}
     */
    public function create(array $input): array
    {
        [$values, $errors] = $this->validate($input);
        $password = $input['password'] ?? '';
        $confirmation = $input['password_confirmation'] ?? '';

        if (strlen($password) < self::PASSWORD_MIN) {
            $errors['password'] = 'Informe uma senha com pelo menos 10 caracteres.';
        } elseif ($password !== $confirmation) {
            $errors['password_confirmation'] = 'Confirme a senha corretamente.';
        }

        if ($errors !== []) {
            return ['user' => null, 'errors' => $errors, 'values' => $values];
        }

        if (!$this->codes instanceof PublicCodeGenerator) {
            return [
                'user' => null,
                'errors' => ['general' => 'Cadastro de usuario indisponivel.'],
                'values' => $values,
            ];
        }

        $data = [
            'tenant_id' => (int) $values['tenant_id'],
            'login' => $values['login'],
            'email' => $values['email'] === '' ? null : $values['email'],
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'name' => $values['name'],
            'type' => $values['type'],
            'status' => $values['status'],
        ];

        return [
            'user' => $this->users->create($data, $this->codes),
            'errors' => [],
            'values' => $values,
        ];
    }

    /**
     * @param array<string, string> $input
     * @return array{user: User|null, errors: array<string, string>, values: array<string, string>}
     */
    public function update(int $id, array $input, ?int $currentUserId): array
    {
        $user = $this->users->findById($id);

        if (!$user instanceof User) {
            return ['user' => null, 'errors' => ['general' => 'Usuario nao encontrado.'], 'values' => $this->values($input)];
        }

        [$values, $errors] = $this->validate($input, $id);

        if ($errors === []) {
            $errors = $this->ownerTransitionErrors($user, $values, $currentUserId);
        }

        if ($errors !== []) {
            return ['user' => null, 'errors' => $errors, 'values' => $values];
        }

        $updated = $this->users->update($id, [
            'tenant_id' => (int) $values['tenant_id'],
            'login' => $values['login'],
            'email' => $values['email'] === '' ? null : $values['email'],
            'name' => $values['name'],
            'type' => $values['type'],
            'status' => $values['status'],
        ]);

        return ['user' => $updated, 'errors' => [], 'values' => $values];
    }

    public function activate(int $id): array
    {
        $user = $this->users->findById($id);

        if (!$user instanceof User) {
            return ['ok' => false, 'message' => 'Usuario nao encontrado.'];
        }

        $tenant = $this->tenants->findById($user->tenantId);

        if (!$tenant instanceof Tenant || $tenant->status !== 'active') {
            return ['ok' => false, 'message' => 'Nao e possivel ativar usuario de uma empresa inativa.'];
        }

        return [
            'ok' => $this->users->activate($id),
            'message' => 'Nao foi possivel alterar o status do usuario.',
        ];
    }

    public function deactivate(int $id, ?int $currentUserId): array
    {
        $user = $this->users->findById($id);

        if (!$user instanceof User) {
            return ['ok' => false, 'message' => 'Usuario nao encontrado.'];
        }

        if ($currentUserId !== null && $id === $currentUserId) {
            return ['ok' => false, 'message' => 'Nao e possivel inativar o proprio usuario.'];
        }

        if ($user->type === 'owner' && $user->status === 'active' && $this->users->countActiveOwnersByTenant($user->tenantId) <= 1) {
            return ['ok' => false, 'message' => 'A empresa deve possuir pelo menos um owner ativo.'];
        }

        return [
            'ok' => $this->users->deactivate($id),
            'message' => 'Nao foi possivel alterar o status do usuario.',
        ];
    }

    public function resetPassword(int $id, string $password, string $confirmation): array
    {
        if (!$this->users->findById($id) instanceof User) {
            return ['ok' => false, 'errors' => ['general' => 'Usuario nao encontrado.']];
        }

        $errors = $this->passwordErrors($password, $confirmation);

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        return [
            'ok' => $this->users->updatePassword($id, password_hash($password, PASSWORD_DEFAULT)),
            'errors' => [],
        ];
    }

    public function updateProfile(string $name, ?string $email): array
    {
        $current = $this->currentUser();

        if (!$current instanceof User) {
            return ['user' => null, 'errors' => ['general' => 'Usuario nao encontrado.'], 'values' => []];
        }

        $values = [
            'name' => $this->normalizeName($name),
            'email' => $email === null ? '' : strtolower(trim($email)),
        ];
        $errors = [];

        if (strlen($values['name']) < 2 || strlen($values['name']) > 150) {
            $errors['name'] = 'Informe um nome entre 2 e 150 caracteres.';
        }

        if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Informe um email valido.';
        } elseif ($values['email'] !== '' && $this->users->emailExists($current->tenantId, $values['email'], $current->id)) {
            $errors['email'] = 'O email informado ja esta em uso.';
        }

        if ($errors !== []) {
            return ['user' => null, 'errors' => $errors, 'values' => $values];
        }

        $updated = $this->users->updateProfile(
            $current->id,
            $values['name'],
            $values['email'] === '' ? null : $values['email'],
        );
        AuthService::refreshSessionUser($updated, $this->tenants->findById($updated->tenantId));

        return ['user' => $updated, 'errors' => [], 'values' => $values];
    }

    public function changeOwnPassword(string $currentPassword, string $password, string $confirmation): array
    {
        $current = $this->currentUser();

        if (!$current instanceof User) {
            return ['ok' => false, 'errors' => ['general' => 'Usuario nao encontrado.']];
        }

        if (!password_verify($currentPassword, $current->password)) {
            return ['ok' => false, 'errors' => ['current_password' => 'Nao foi possivel alterar a senha.']];
        }

        $errors = $this->passwordErrors($password, $confirmation);

        if ($errors === [] && password_verify($password, $current->password)) {
            $errors['password'] = 'Informe uma senha diferente da atual.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $ok = $this->users->updatePassword($current->id, password_hash($password, PASSWORD_DEFAULT));

        if ($ok) {
            AuthService::regenerateSession();
        }

        return ['ok' => $ok, 'errors' => []];
    }

    /**
     * @param array<string, string> $input
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    private function validate(array $input, ?int $ignoreId = null): array
    {
        $values = $this->values($input);
        $errors = [];
        $tenantId = (int) $values['tenant_id'];
        $tenant = $this->tenants->findById($tenantId);

        if (!$tenant instanceof Tenant) {
            $errors['tenant_id'] = 'Informe uma empresa valida.';
        } elseif ($values['status'] === 'active' && $tenant->status !== 'active') {
            $errors['status'] = 'Nao e possivel ativar usuario de uma empresa inativa.';
        }

        if (strlen($values['login']) < 3 || strlen($values['login']) > 60) {
            $errors['login'] = 'Informe um login entre 3 e 60 caracteres.';
        } elseif (!preg_match('/^[a-z0-9._-]+$/', $values['login'])) {
            $errors['login'] = 'Use letras minusculas, numeros, ponto, hifen e underscore.';
        } elseif ($tenant instanceof Tenant && $this->users->loginExists($tenantId, $values['login'], $ignoreId)) {
            $errors['login'] = 'O login informado ja esta em uso.';
        }

        if (strlen($values['name']) < 2 || strlen($values['name']) > 150) {
            $errors['name'] = 'Informe um nome entre 2 e 150 caracteres.';
        }

        if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Informe um email valido.';
        } elseif ($values['email'] !== '' && $tenant instanceof Tenant && $this->users->emailExists($tenantId, $values['email'], $ignoreId)) {
            $errors['email'] = 'O email informado ja esta em uso.';
        }

        if (!in_array($values['type'], self::TYPES, true)) {
            $errors['type'] = 'Categoria invalida.';
        }

        if (!in_array($values['status'], self::STATUSES, true)) {
            $errors['status'] = 'Status invalido.';
        }

        return [$values, $errors];
    }

    /**
     * @param array<string, string> $values
     * @return array<string, string>
     */
    private function ownerTransitionErrors(User $user, array $values, ?int $currentUserId): array
    {
        $errors = [];
        $newTenantId = (int) $values['tenant_id'];
        $self = $currentUserId !== null && $user->id === $currentUserId;

        if ($self && $user->type === 'owner' && $values['type'] !== 'owner') {
            $errors['type'] = 'Nao e possivel rebaixar o proprio usuario por este cadastro.';
        }

        if ($self && $values['status'] === 'inactive') {
            $errors['status'] = 'Nao e possivel inativar o proprio usuario.';
        }

        $removesActiveOwner = $user->type === 'owner'
            && $user->status === 'active'
            && ($values['type'] !== 'owner' || $values['status'] !== 'active' || $newTenantId !== $user->tenantId);

        if ($removesActiveOwner && $this->users->countActiveOwnersByTenant($user->tenantId) <= 1) {
            $errors['type'] = 'A empresa deve possuir pelo menos um owner ativo.';
        }

        $targetTenant = $this->tenants->findById($newTenantId);

        if ($values['status'] === 'active' && (!$targetTenant instanceof Tenant || $targetTenant->status !== 'active')) {
            $errors['status'] = 'Nao e possivel ativar usuario de uma empresa inativa.';
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function passwordErrors(string $password, string $confirmation): array
    {
        if (strlen($password) < self::PASSWORD_MIN) {
            return ['password' => 'Informe uma senha com pelo menos 10 caracteres.'];
        }

        if ($password !== $confirmation) {
            return ['password_confirmation' => 'Confirme a senha corretamente.'];
        }

        return [];
    }

    /**
     * @param array<string, string> $input
     * @return array<string, string>
     */
    private function values(array $input): array
    {
        return [
            'tenant_id' => trim($input['tenant_id'] ?? ''),
            'login' => strtolower(trim($input['login'] ?? '')),
            'email' => strtolower(trim($input['email'] ?? '')),
            'name' => $this->normalizeName($input['name'] ?? ''),
            'type' => trim($input['type'] ?? 'user'),
            'status' => trim($input['status'] ?? 'active'),
        ];
    }

    private function normalizeName(string $name): string
    {
        return preg_replace('/\s+/', ' ', trim($name)) ?? '';
    }
}
