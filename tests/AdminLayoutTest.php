<?php

declare(strict_types=1);

use HPucca\Platform\Core\Response;
use HPucca\Platform\Core\View;
use HPucca\Platform\Middleware\AuthMiddleware;
use HPucca\Platform\Middleware\OwnerMiddleware;
use HPucca\Platform\Services\AuthService;
use HPucca\Platform\Services\CsrfService;

require dirname(__DIR__) . '/vendor/autoload.php';

ini_set('session.save_path', dirname(__DIR__) . '/storage/cache');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function responseStatus(Response $response): int
{
    $property = new ReflectionProperty($response, 'statusCode');
    $property->setAccessible(true);

    return (int) $property->getValue($response);
}

function responseHeaders(Response $response): array
{
    $property = new ReflectionProperty($response, 'headers');
    $property->setAccessible(true);

    return $property->getValue($response);
}

function responseBody(Response $response): string
{
    ob_start();
    $response->send();
    $body = ob_get_clean();

    return $body === false ? '' : $body;
}

$_SESSION = [];

$redirect = (new AuthMiddleware(new AuthService()))->handle(
    static fn (): Response => Response::html('protected')
);
assert(responseStatus($redirect) === 302);
assert(responseHeaders($redirect)['Location'] === '/login');

$_SESSION = [
    'user_id' => 1,
    'user_code' => 'USR000001',
    'tenant_id' => 1,
    'tenant_name' => 'Empresa A',
    'login' => 'operador',
    'name' => 'Usuario Operador',
    'type' => 'owner',
];

$protected = (new AuthMiddleware(new AuthService()))->handle(
    static fn (): Response => Response::html('protected')
);
assert(responseStatus($protected) === 200);

$html = View::admin('placeholders/module.php', [
    'title' => 'Empresas',
    'activeMenu' => 'companies',
    'breadcrumbs' => ['Dashboard' => '/dashboard', 'Empresas' => null],
    'user' => AuthService::sessionUser(),
    'tenant' => AuthService::sessionTenant(),
    'message' => 'O cadastro de empresas sera implementado nas proximas etapas.',
]);
$body = responseBody($html);

assert(str_contains($body, 'aria-current="page"'));
assert(str_contains($body, CsrfService::FIELD));
assert(str_contains($body, 'Empresas'));
assert(str_contains($body, 'Usuario Operador'));
assert(str_contains($body, 'Empresa A'));
assert(!str_contains($body, 'password_hash'));
assert(!str_contains($body, 'secret'));
assert(!str_contains($body, 'senha-hash'));

$ownerAccess = (new OwnerMiddleware())->handle(static fn (): Response => Response::html('owner'));
assert(responseStatus($ownerAccess) === 200);

$_SESSION['type'] = 'user';
$blockedOwnerAccess = (new OwnerMiddleware())->handle(static fn (): Response => Response::html('owner'));
assert(responseStatus($blockedOwnerAccess) === 403);

$routes = (string) file_get_contents(dirname(__DIR__) . '/routes/api.php');
assert(str_contains($routes, "\$router->post('/logout'"));
assert(!str_contains($routes, "\$router->get('/logout'"));
assert(str_contains($routes, "\$router->get('/admin/companies'"));
assert(str_contains($routes, "\$router->get('/admin/companies/create'"));
assert(str_contains($routes, "\$router->post('/admin/companies'"));
assert(str_contains($routes, "\$router->get('/admin/companies/{id}'"));
assert(str_contains($routes, "\$router->get('/admin/companies/{id}/edit'"));
assert(str_contains($routes, "\$router->post('/admin/companies/{id}'"));
assert(str_contains($routes, "\$router->post('/admin/companies/{id}/activate'"));
assert(str_contains($routes, "\$router->post('/admin/companies/{id}/deactivate'"));
assert(str_contains($routes, "\$router->get('/admin/users'"));
assert(str_contains($routes, "\$router->get('/profile'"));
assert(str_contains($routes, "\$router->get('/change-password'"));

$_SESSION = [];

echo 'AdminLayoutTest passed.' . PHP_EOL;
