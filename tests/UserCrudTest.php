<?php

declare(strict_types=1);

use HPucca\Platform\Core\Request;
use HPucca\Platform\Core\Response;
use HPucca\Platform\Core\Router;
use HPucca\Platform\Core\View;
use HPucca\Platform\Models\Tenant;
use HPucca\Platform\Models\User;
use HPucca\Platform\Middleware\AuthMiddleware;
use HPucca\Platform\Middleware\OwnerMiddleware;
use HPucca\Platform\Repositories\TenantRepository;
use HPucca\Platform\Repositories\UserRepositoryContract;
use HPucca\Platform\Services\AuthService;
use HPucca\Platform\Services\CsrfService;
use HPucca\Platform\Services\PublicCodeGenerator;
use HPucca\Platform\Services\UserService;

require dirname(__DIR__) . '/vendor/autoload.php';

ini_set('session.save_path', dirname(__DIR__) . '/storage/cache');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

final class InMemoryUserRepository implements UserRepositoryContract
{
    /**
     * @var array<int, User>
     */
    private array $users = [];

    public function __construct()
    {
        $this->users[1] = $this->user(1, 1, 'USR000001', 'owner', 'owner@example.com', 'Owner', 'owner', 'active', 'owner-password');
        $this->users[2] = $this->user(2, 1, 'USR000002', 'admin', 'admin@example.com', 'Admin', 'admin', 'active', 'admin-password');
        $this->users[3] = $this->user(3, 2, 'USR000003', 'owner', null, 'Owner B', 'owner', 'active', 'ownerb-password');
        $this->users[4] = $this->user(4, 3, 'USR000004', 'inactive', null, 'Inactive Tenant User', 'user', 'inactive', 'inactive-password');
    }

    public function paginate(string $search, ?int $tenantId, int $page, int $perPage): array
    {
        $items = array_filter($this->users, static function (User $user) use ($search, $tenantId): bool {
            if ($tenantId !== null && $user->tenantId !== $tenantId) {
                return false;
            }

            return $search === ''
                || str_contains(strtolower($user->code), strtolower($search))
                || str_contains(strtolower($user->name), strtolower($search))
                || str_contains(strtolower($user->login), strtolower($search))
                || str_contains(strtolower($user->email ?? ''), strtolower($search));
        });

        return array_map(
            static fn (User $user): array => [
                'id' => $user->id,
                'code' => $user->code,
                'tenant_id' => $user->tenantId,
                'login' => $user->login,
                'email' => $user->email,
                'name' => $user->name,
                'type' => $user->type,
                'status' => $user->status,
                'created_at' => $user->createdAt,
                'tenant_name' => 'Empresa ' . $user->tenantId,
            ],
            array_slice(array_values($items), max(0, ($page - 1) * $perPage), $perPage),
        );
    }

    public function count(string $search, ?int $tenantId): int
    {
        return count($this->paginate($search, $tenantId, 1, PHP_INT_MAX));
    }

    public function findById(int $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function findActiveById(int $id): ?User
    {
        $user = $this->findById($id);

        return $user instanceof User && $user->status === 'active' ? $user : null;
    }

    public function findActiveByTenantAndLogin(int $tenantId, string $login): ?User
    {
        foreach ($this->users as $user) {
            if ($user->tenantId === $tenantId && $user->login === strtolower($login) && $user->status === 'active') {
                return $user;
            }
        }

        return null;
    }

    public function loginExists(int $tenantId, string $login, ?int $ignoreId = null): bool
    {
        foreach ($this->users as $user) {
            if ($user->tenantId === $tenantId && $user->login === strtolower($login) && $user->id !== $ignoreId) {
                return true;
            }
        }

        return false;
    }

    public function emailExists(int $tenantId, string $email, ?int $ignoreId = null): bool
    {
        foreach ($this->users as $user) {
            if ($user->tenantId === $tenantId && $user->email === strtolower($email) && $user->id !== $ignoreId) {
                return true;
            }
        }

        return false;
    }

    public function countActiveOwnersByTenant(int $tenantId): int
    {
        return count(array_filter(
            $this->users,
            static fn (User $user): bool => $user->tenantId === $tenantId && $user->type === 'owner' && $user->status === 'active',
        ));
    }

    public function create(array $data, PublicCodeGenerator $codes): User
    {
        $id = count($this->users) + 1;
        $user = new User(
            $id,
            PublicCodeGenerator::format('USR', $id),
            $data['tenant_id'],
            $data['login'],
            $data['email'],
            $data['password'],
            $data['name'],
            $data['type'],
            $data['status'],
            '2026-01-01',
            '2026-01-01',
        );
        $this->users[$id] = $user;

        return $user;
    }

    public function update(int $id, array $data): User
    {
        $current = $this->users[$id];
        $this->users[$id] = new User(
            $id,
            $current->code,
            $data['tenant_id'],
            $data['login'],
            $data['email'],
            $current->password,
            $data['name'],
            $data['type'],
            $data['status'],
            $current->createdAt,
            '2026-01-02',
        );

        return $this->users[$id];
    }

    public function updateProfile(int $id, string $name, ?string $email): User
    {
        $current = $this->users[$id];
        $this->users[$id] = new User(
            $id,
            $current->code,
            $current->tenantId,
            $current->login,
            $email,
            $current->password,
            $name,
            $current->type,
            $current->status,
            $current->createdAt,
            '2026-01-02',
        );

        return $this->users[$id];
    }

    public function updatePassword(int $id, string $passwordHash): bool
    {
        $current = $this->users[$id] ?? null;

        if (!$current instanceof User) {
            return false;
        }

        $this->users[$id] = new User(
            $id,
            $current->code,
            $current->tenantId,
            $current->login,
            $current->email,
            $passwordHash,
            $current->name,
            $current->type,
            $current->status,
            $current->createdAt,
            '2026-01-02',
        );

        return true;
    }

    public function activate(int $id): bool
    {
        return $this->status($id, 'active');
    }

    public function deactivate(int $id): bool
    {
        return $this->status($id, 'inactive');
    }

    private function status(int $id, string $status): bool
    {
        $current = $this->users[$id] ?? null;

        if (!$current instanceof User) {
            return false;
        }

        $this->users[$id] = new User(
            $id,
            $current->code,
            $current->tenantId,
            $current->login,
            $current->email,
            $current->password,
            $current->name,
            $current->type,
            $status,
            $current->createdAt,
            '2026-01-02',
        );

        return true;
    }

    private function user(
        int $id,
        int $tenantId,
        string $code,
        string $login,
        ?string $email,
        string $name,
        string $type,
        string $status,
        string $password,
    ): User {
        return new User(
            $id,
            $code,
            $tenantId,
            $login,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $name,
            $type,
            $status,
            '2026-01-01',
            '2026-01-01',
        );
    }
}

function userTestConnection(): PDO
{
    $connection = new PDO('sqlite::memory:');
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $connection->exec(
        "CREATE TABLE tenants (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code VARCHAR(20) NOT NULL UNIQUE,
            name VARCHAR(150) NOT NULL,
            slug VARCHAR(150) NOT NULL UNIQUE,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );
    $connection->exec(
        "INSERT INTO tenants (id, code, name, slug, status)
         VALUES
         (1, 'TEN000001', 'Empresa A', 'empresa-a', 'active'),
         (2, 'TEN000002', 'Empresa B', 'empresa-b', 'active'),
         (3, 'TEN000003', 'Empresa Inativa', 'empresa-inativa', 'inactive')"
    );

    return $connection;
}

function userTestResponseStatus(Response $response): int
{
    $property = new ReflectionProperty($response, 'statusCode');
    $property->setAccessible(true);

    return (int) $property->getValue($response);
}

$repository = new InMemoryUserRepository();
$service = new UserService($repository, new TenantRepository(userTestConnection()), new AuthService(), new PublicCodeGenerator(new PDO('sqlite::memory:')));

$_SESSION = [];
$redirect = (new AuthMiddleware(new AuthService()))->handle(static fn (): Response => Response::html('protected'));
assert(userTestResponseStatus($redirect) === 302);

foreach (['admin', 'manager', 'user'] as $type) {
    $_SESSION = ['user_id' => 2, 'tenant_id' => 1, 'type' => $type, 'name' => 'Blocked'];
    $blocked = (new OwnerMiddleware())->handle(static fn (): Response => Response::html('owner'));
    assert(userTestResponseStatus($blocked) === 403);
}

$_SESSION = ['user_id' => 1, 'tenant_id' => 1, 'type' => 'owner', 'name' => 'Owner'];
$owner = (new OwnerMiddleware())->handle(static fn (): Response => Response::html('owner'));
assert(userTestResponseStatus($owner) === 200);

$created = $service->create([
    'tenant_id' => '1',
    'name' => 'Usuario Novo',
    'login' => 'novo.usuario',
    'email' => '',
    'type' => 'user',
    'status' => 'active',
    'password' => 'senha-segura-10',
    'password_confirmation' => 'senha-segura-10',
]);
assert($created['user'] instanceof User);
assert($created['user']->code === 'USR000005');
assert(password_verify('senha-segura-10', $created['user']->password));

$duplicateLogin = $service->create([
    'tenant_id' => '1',
    'name' => 'Duplicado',
    'login' => 'owner',
    'email' => '',
    'type' => 'user',
    'status' => 'active',
    'password' => 'senha-segura-10',
    'password_confirmation' => 'senha-segura-10',
]);
assert(isset($duplicateLogin['errors']['login']));

$sameLoginDifferentTenant = $service->create([
    'tenant_id' => '2',
    'name' => 'Mesmo Login',
    'login' => 'admin',
    'email' => '',
    'type' => 'user',
    'status' => 'active',
    'password' => 'senha-segura-10',
    'password_confirmation' => 'senha-segura-10',
]);
assert($sameLoginDifferentTenant['user'] instanceof User);

$duplicateEmail = $service->create([
    'tenant_id' => '1',
    'name' => 'Email Duplicado',
    'login' => 'email-dup',
    'email' => 'admin@example.com',
    'type' => 'user',
    'status' => 'active',
    'password' => 'senha-segura-10',
    'password_confirmation' => 'senha-segura-10',
]);
assert(isset($duplicateEmail['errors']['email']));

$shortPassword = $service->create([
    'tenant_id' => '1',
    'name' => 'Senha Curta',
    'login' => 'senha-curta',
    'email' => '',
    'type' => 'user',
    'status' => 'active',
    'password' => 'curta',
    'password_confirmation' => 'curta',
]);
assert(isset($shortPassword['errors']['password']));

$missingConfirmation = $service->create([
    'tenant_id' => '1',
    'name' => 'Sem Confirmacao',
    'login' => 'sem-confirmacao',
    'email' => '',
    'type' => 'user',
    'status' => 'active',
    'password' => 'senha-segura-10',
    'password_confirmation' => '',
]);
assert(isset($missingConfirmation['errors']['password_confirmation']));

$updated = $service->update(2, [
    'tenant_id' => '1',
    'name' => 'Admin Editado',
    'login' => 'admin-editado',
    'email' => 'admin-editado@example.com',
    'type' => 'admin',
    'status' => 'active',
], 1);
assert($updated['user'] instanceof User);
assert($updated['user']->code === 'USR000002');

$selfDeactivate = $service->deactivate(1, 1);
assert($selfDeactivate['ok'] === false);

$lastOwnerDeactivate = $service->deactivate(3, 1);
assert($lastOwnerDeactivate['ok'] === false);

$lastOwnerDemote = $service->update(3, [
    'tenant_id' => '2',
    'name' => 'Owner B',
    'login' => 'owner',
    'email' => '',
    'type' => 'user',
    'status' => 'active',
], 1);
assert(isset($lastOwnerDemote['errors']['type']));

$inactiveTenantActivate = $service->activate(4);
assert($inactiveTenantActivate['ok'] === false);

$activeUserInInactiveTenant = $service->create([
    'tenant_id' => '3',
    'name' => 'Ativo Invalido',
    'login' => 'ativo-invalido',
    'email' => '',
    'type' => 'user',
    'status' => 'active',
    'password' => 'senha-segura-10',
    'password_confirmation' => 'senha-segura-10',
]);
assert(isset($activeUserInInactiveTenant['errors']['status']));

$missingUser = $service->activate(999);
assert($missingUser['ok'] === false);

$listed = $service->list('admin', 1, -5);
assert($listed['page'] === 1);
assert($listed['perPage'] === 15);
assert($listed['total'] >= 1);

$_SESSION = ['user_id' => 2, 'tenant_id' => 1, 'type' => 'admin', 'name' => 'Admin'];
$profile = $service->updateProfile('Admin Perfil', 'perfil@example.com');
assert($profile['user'] instanceof User);
assert($profile['user']->login === 'admin-editado');
assert($profile['user']->tenantId === 1);
assert($profile['user']->type === 'admin');
assert($_SESSION['name'] === 'Admin Perfil');

$wrongPassword = $service->changeOwnPassword('errada', 'nova-senha-10', 'nova-senha-10');
assert($wrongPassword['ok'] === false);

$changedPassword = $service->changeOwnPassword('admin-password', 'nova-senha-10', 'nova-senha-10');
assert($changedPassword['ok'] === true);
assert(password_verify('nova-senha-10', $repository->findById(2)?->password ?? ''));

$resetPassword = $service->resetPassword(2, 'reset-senha-10', 'reset-senha-10');
assert($resetPassword['ok'] === true);
assert(password_verify('reset-senha-10', $repository->findById(2)?->password ?? ''));

$_SESSION = [];
$token = CsrfService::token();
assert(CsrfService::validate($token) === true);
assert(CsrfService::validate('invalid') === false);

$indexBody = View::render('users/index.php', [
    'users' => $service->list('admin', 1, 1)['users'],
    'tenants' => [new Tenant(1, 'TEN000001', 'Empresa A', 'empresa-a', 'active', '2026-01-01', '2026-01-01')],
    'search' => 'admin',
    'tenantId' => 1,
    'total' => 16,
    'page' => 1,
    'pages' => 2,
    'e' => static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8'),
]);
assert(str_contains($indexBody, 'Codigo'));
assert(str_contains($indexBody, 'Empresa'));
assert(str_contains($indexBody, 'page=2'));
assert(str_contains($indexBody, 'tenant_id=1'));
assert(!str_contains($indexBody, 'password'));
assert(!str_contains($indexBody, 'hash'));

$editBody = View::render('users/edit.php', [
    'managedUser' => $repository->findById(2),
    'values' => ['tenant_id' => '1', 'name' => 'Admin', 'login' => 'admin', 'email' => '', 'type' => 'admin', 'status' => 'active'],
    'errors' => [],
    'tenants' => [new Tenant(1, 'TEN000001', 'Empresa A', 'empresa-a', 'active', '2026-01-01', '2026-01-01')],
    'csrfField' => CsrfService::field(),
    'e' => static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8'),
]);
assert(str_contains($editBody, 'USR000002'));
assert(!str_contains($editBody, 'name="code"'));
assert(!str_contains($editBody, 'name="id"'));
assert(!str_contains($editBody, 'name="password"'));
assert(!str_contains($editBody, 'password_hash'));

$profileBody = View::render('profile/show.php', [
    'currentUser' => $repository->findById(2),
    'currentTenant' => new Tenant(1, 'TEN000001', 'Empresa A', 'empresa-a', 'active', '2026-01-01', '2026-01-01'),
    'values' => ['name' => 'Admin', 'email' => 'admin@example.com'],
    'errors' => [],
    'csrfField' => CsrfService::field(),
    'e' => static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8'),
]);
assert(str_contains($profileBody, 'name="name"'));
assert(str_contains($profileBody, 'name="email"'));
assert(!str_contains($profileBody, 'name="login"'));
assert(!str_contains($profileBody, 'name="tenant_id"'));
assert(!str_contains($profileBody, 'name="type"'));

$routes = (string) file_get_contents(dirname(__DIR__) . '/routes/api.php');
assert(str_contains($routes, "\$router->get('/admin/users/create'"));
assert(str_contains($routes, "\$router->post('/admin/users/{id}/reset-password'"));
assert(str_contains($routes, "\$router->post('/profile'"));
assert(str_contains($routes, "\$router->post('/change-password'"));
assert(!str_contains($routes, "\$router->delete("));

$router = new Router();
$router->get('/admin/users/create', static fn (): Response => Response::html('create'));
$router->get('/admin/users/{id}', static fn (Request $request): Response => Response::html($request->param('id') ?? ''));
assert(userTestResponseStatus($router->dispatch(new Request('GET', '/admin/users/create'))) === 200);

echo 'UserCrudTest passed.' . PHP_EOL;
