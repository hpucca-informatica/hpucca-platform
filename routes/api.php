<?php

declare(strict_types=1);

use HPucca\Platform\Controllers\HealthController;
use HPucca\Platform\Controllers\AuthController;
use HPucca\Platform\Controllers\DashboardController;
use HPucca\Platform\Core\Config;
use HPucca\Platform\Core\Database;
use HPucca\Platform\Core\Request;
use HPucca\Platform\Core\Response;
use HPucca\Platform\Core\Router;
use HPucca\Platform\Middleware\AuthMiddleware;
use HPucca\Platform\Services\AuthService;

/** @var Router $router */
$router->get('/api/v1/health', static function (Request $request): Response {
    return (new HealthController(new Database(Config::get('database'))))->show();
});

$router->get('/api/v1/health/database', static function (Request $request): Response {
    return (new HealthController(new Database(Config::get('database'))))->database();
});

$router->get('/login', static function (Request $request): Response {
    return (new AuthController(new Database(Config::get('database'))))->loginForm();
});

$router->post('/login', static function (Request $request): Response {
    return (new AuthController(new Database(Config::get('database'))))->login($request);
});

$router->post('/logout', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new AuthController(new Database(Config::get('database'))))->logout()
    );
});

$router->get('/dashboard', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new DashboardController(new Database(Config::get('database'))))->index()
    );
});
