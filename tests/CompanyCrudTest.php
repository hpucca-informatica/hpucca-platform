<?php

declare(strict_types=1);

use HPucca\Platform\Core\Request;
use HPucca\Platform\Core\Response;
use HPucca\Platform\Core\Router;
use HPucca\Platform\Core\View;
use HPucca\Platform\Controllers\CompanyController;
use HPucca\Platform\Core\Database;
use HPucca\Platform\Models\Tenant;
use HPucca\Platform\Middleware\OwnerMiddleware;
use HPucca\Platform\Repositories\CompanyRepositoryContract;
use HPucca\Platform\Services\AuthService;
use HPucca\Platform\Services\CompanyService;
use HPucca\Platform\Services\CsrfService;
use HPucca\Platform\Services\PublicCodeGenerator;

require dirname(__DIR__) . '/vendor/autoload.php';

ini_set('session.save_path', dirname(__DIR__) . '/storage/cache');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

final class InMemoryCompanyRepository implements CompanyRepositoryContract
{
    /**
     * @var array<int, Tenant>
     */
    private array $companies = [];

    public function __construct()
    {
        $this->companies[1] = new Tenant(1, 'TEN000001', 'Empresa A', 'empresa-a', 'active', '2026-01-01', '2026-01-01');
    }

    public function paginate(string $search, int $page, int $perPage): array
    {
        $items = array_values(array_filter(
            $this->companies,
            static fn (Tenant $tenant): bool => $search === ''
                || str_contains(strtolower($tenant->name), strtolower($search))
                || str_contains(strtolower($tenant->slug), strtolower($search))
                || str_contains(strtolower($tenant->code), strtolower($search))
        ));

        return array_slice($items, max(0, ($page - 1) * $perPage), $perPage);
    }

    public function count(string $search): int
    {
        return count($this->paginate($search, 1, PHP_INT_MAX));
    }

    public function findById(int $id): ?Tenant
    {
        return $this->companies[$id] ?? null;
    }

    public function findBySlug(string $slug): ?Tenant
    {
        foreach ($this->companies as $company) {
            if ($company->slug === $slug) {
                return $company;
            }
        }

        return null;
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $company = $this->findBySlug($slug);

        return $company instanceof Tenant && $company->id !== $ignoreId;
    }

    public function create(array $data, PublicCodeGenerator $codes): Tenant
    {
        $id = count($this->companies) + 1;
        $tenant = new Tenant(
            $id,
            PublicCodeGenerator::format('TEN', $id),
            $data['name'],
            $data['slug'],
            $data['status'],
            '2026-01-01',
            '2026-01-01',
        );
        $this->companies[$id] = $tenant;

        return $tenant;
    }

    public function update(int $id, array $data): Tenant
    {
        $current = $this->companies[$id];
        $tenant = new Tenant(
            $current->id,
            $current->code,
            $data['name'],
            $data['slug'],
            $data['status'],
            $current->createdAt,
            '2026-01-02',
        );
        $this->companies[$id] = $tenant;

        return $tenant;
    }

    public function activate(int $id): bool
    {
        return $this->setStatus($id, 'active');
    }

    public function deactivate(int $id): bool
    {
        return $this->setStatus($id, 'inactive');
    }

    private function setStatus(int $id, string $status): bool
    {
        $current = $this->companies[$id] ?? null;

        if (!$current instanceof Tenant) {
            return false;
        }

        $this->companies[$id] = new Tenant(
            $current->id,
            $current->code,
            $current->name,
            $current->slug,
            $status,
            $current->createdAt,
            '2026-01-02',
        );

        return true;
    }
}

function testResponseStatus(Response $response): int
{
    $property = new ReflectionProperty($response, 'statusCode');
    $property->setAccessible(true);

    return (int) $property->getValue($response);
}

function testResponseBody(Response $response): string
{
    ob_start();
    $response->send();
    $body = ob_get_clean();

    return $body === false ? '' : $body;
}

$repository = new InMemoryCompanyRepository();
$service = new CompanyService($repository, new PublicCodeGenerator(new PDO('sqlite::memory:')));

$listed = $service->list('empresa', -10);
assert($listed['page'] === 1);
assert($listed['perPage'] === 15);
assert($listed['total'] === 1);

$invalid = $service->create(['name' => 'A', 'slug' => 'Empresa A', 'status' => 'active']);
assert($invalid['company'] === null);
assert(isset($invalid['errors']['name']));
assert(isset($invalid['errors']['slug']));

$created = $service->create(['name' => 'Empresa B', 'slug' => 'empresa-b', 'status' => 'active']);
assert($created['company'] instanceof Tenant);
assert($created['company']->code === 'TEN000002');

$duplicate = $service->create(['name' => 'Empresa C', 'slug' => 'empresa-b', 'status' => 'active']);
assert(isset($duplicate['errors']['slug']));

$updated = $service->update(2, ['name' => 'Empresa Beta', 'slug' => 'empresa-beta', 'status' => 'inactive']);
assert($updated['company'] instanceof Tenant);
assert($updated['company']->code === 'TEN000002');
assert($updated['company']->status === 'inactive');

$currentTenantUpdate = $service->update(1, ['name' => 'Empresa A', 'slug' => 'empresa-a', 'status' => 'inactive'], 1);
assert(isset($currentTenantUpdate['errors']['status']));

assert($service->deactivate(1, 1) === false);
assert($service->activate(2) === true);
assert($service->find(2)?->status === 'active');
assert($service->activate(999) === false);
assert($service->deactivate(999, null) === false);

foreach (['admin', 'manager', 'user'] as $type) {
    $_SESSION = [
        'user_id' => 1,
        'user_code' => 'USR000001',
        'tenant_id' => 1,
        'tenant_name' => 'Empresa A',
        'login' => 'operador',
        'name' => 'Usuario Operador',
        'type' => $type,
    ];
    $blocked = (new OwnerMiddleware())->handle(static fn (): Response => Response::html('owner'));
    assert(testResponseStatus($blocked) === 403);
}

$_SESSION = [];
$token = CsrfService::token();
assert(CsrfService::validate($token) === true);
assert(CsrfService::validate('invalid') === false);
assert(str_contains(CsrfService::field(), CsrfService::FIELD));

$_SESSION = [
    'user_id' => 1,
    'user_code' => 'USR000001',
    'tenant_id' => 1,
    'tenant_name' => 'Empresa A',
    'login' => 'operador',
    'name' => 'Usuario Operador',
    'type' => 'owner',
];
$_POST = [];
$missingCsrf = (new CompanyController(new Database([])))->store(new Request('POST', '/admin/companies'));
assert(testResponseStatus($missingCsrf) === 403);

$_POST = [CsrfService::FIELD => 'invalid'];
$invalidCsrf = (new CompanyController(new Database([])))->store(new Request('POST', '/admin/companies'));
assert(testResponseStatus($invalidCsrf) === 403);

$indexBody = View::render('companies/index.php', [
    'companies' => [
        new Tenant(1, 'TEN000001', 'Empresa A', 'empresa-a', 'active', '2026-01-01', '2026-01-02'),
    ],
    'search' => 'empresa',
    'total' => 16,
    'page' => 2,
    'pages' => 2,
    'e' => static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8'),
]);
assert(str_contains($indexBody, 'Criada em'));
assert(!str_contains($indexBody, 'Atualizada em'));
assert(str_contains($indexBody, '2026-01-01'));
assert(!str_contains($indexBody, '2026-01-02'));
assert(str_contains($indexBody, 'search=empresa'));
assert(str_contains($indexBody, 'page=1'));
assert(!str_contains($indexBody, 'password'));
assert(!str_contains($indexBody, 'hash'));

$editBody = View::render('companies/edit.php', [
    'company' => new Tenant(1, 'TEN000001', 'Empresa A', 'empresa-a', 'active', '2026-01-01', '2026-01-02'),
    'values' => ['name' => 'Empresa A', 'slug' => 'empresa-a', 'status' => 'active'],
    'errors' => [],
    'csrfField' => CsrfService::field(),
    'e' => static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8'),
]);
assert(str_contains($editBody, 'TEN000001'));
assert(!str_contains($editBody, 'name="code"'));
assert(!str_contains($editBody, 'name="id"'));
assert(!str_contains($editBody, 'name="created_at"'));
assert(!str_contains($editBody, 'name="updated_at"'));
assert(!str_contains($editBody, 'password'));
assert(!str_contains($editBody, 'hash'));

$router = new Router();
$router->get('/admin/companies/{id}/edit', static fn (Request $request) => Response::html($request->param('id') ?? ''));
$response = $router->dispatch(new Request('GET', '/admin/companies/42/edit'));
assert(testResponseStatus($response) === 200);

echo 'CompanyCrudTest passed.' . PHP_EOL;
