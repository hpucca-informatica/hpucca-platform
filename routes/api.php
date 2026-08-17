<?php

declare(strict_types=1);

use HPucca\Platform\Controllers\AuthController;
use HPucca\Platform\Controllers\AutomationController;
use HPucca\Platform\Controllers\CompanyController;
use HPucca\Platform\Controllers\DashboardController;
use HPucca\Platform\Controllers\EventController;
use HPucca\Platform\Controllers\EventWebhookController;
use HPucca\Platform\Controllers\HealthController;
use HPucca\Platform\Controllers\IntegrationSourceController;
use HPucca\Platform\Controllers\IntegrationDestinationController;
use HPucca\Platform\Controllers\PasswordController;
use HPucca\Platform\Controllers\ProfileController;
use HPucca\Platform\Controllers\UserController;
use HPucca\Platform\Core\Config;
use HPucca\Platform\Core\Database;
use HPucca\Platform\Core\Request;
use HPucca\Platform\Core\Response;
use HPucca\Platform\Core\Router;
use HPucca\Platform\Middleware\AuthMiddleware;
use HPucca\Platform\Middleware\OwnerMiddleware;
use HPucca\Platform\Services\AuthService;
use HPucca\Platform\Services\CsrfService;

/** @var Router $router */
$router->get('/api/v1/health', static function (Request $request): Response {
    return (new HealthController(new Database(Config::get('database'))))->show();
});

$router->get('/api/v1/health/database', static function (Request $request): Response {
    return (new HealthController(new Database(Config::get('database'))))->database();
});

$router->post('/api/v1/events', static function (Request $request): Response {
    return (new EventWebhookController(new Database(Config::get('database'))))->store($request);
});

$router->get('/login', static function (Request $request): Response {
    return (new AuthController(new Database(Config::get('database'))))->loginForm();
});

$router->post('/login', static function (Request $request): Response {
    return (new AuthController(new Database(Config::get('database'))))->login($request);
});

$router->post('/logout', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static function () use ($request): Response {
            if (!CsrfService::validate($request->input(CsrfService::FIELD, '', false))) {
                return Response::redirect('/dashboard');
            }

            return (new AuthController(new Database(Config::get('database'))))->logout();
        }
    );
});

$router->get('/dashboard', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new DashboardController(new Database(Config::get('database'))))->index()
    );
});

$router->get('/admin/companies', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new CompanyController(new Database(Config::get('database'))))->index($request)
        )
    );
});

$router->get('/admin/companies/create', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new CompanyController(new Database(Config::get('database'))))->create()
        )
    );
});

$router->post('/admin/companies', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new CompanyController(new Database(Config::get('database'))))->store($request)
        )
    );
});

$router->get('/admin/companies/{id}', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new CompanyController(new Database(Config::get('database'))))->show($request)
        )
    );
});

$router->get('/admin/companies/{id}/edit', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new CompanyController(new Database(Config::get('database'))))->edit($request)
        )
    );
});

$router->post('/admin/companies/{id}', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new CompanyController(new Database(Config::get('database'))))->update($request)
        )
    );
});

$router->post('/admin/companies/{id}/activate', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new CompanyController(new Database(Config::get('database'))))->activate($request)
        )
    );
});

$router->post('/admin/companies/{id}/deactivate', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new CompanyController(new Database(Config::get('database'))))->deactivate($request)
        )
    );
});

$router->get('/admin/users', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new UserController(new Database(Config::get('database'))))->index($request)
        )
    );
});

$router->get('/admin/users/create', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new UserController(new Database(Config::get('database'))))->create()
        )
    );
});

$router->post('/admin/users', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new UserController(new Database(Config::get('database'))))->store($request)
        )
    );
});

$router->get('/admin/users/{id}', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new UserController(new Database(Config::get('database'))))->show($request)
        )
    );
});

$router->get('/admin/users/{id}/edit', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new UserController(new Database(Config::get('database'))))->edit($request)
        )
    );
});

$router->post('/admin/users/{id}', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new UserController(new Database(Config::get('database'))))->update($request)
        )
    );
});

$router->post('/admin/users/{id}/activate', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new UserController(new Database(Config::get('database'))))->activate($request)
        )
    );
});

$router->post('/admin/users/{id}/deactivate', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new UserController(new Database(Config::get('database'))))->deactivate($request)
        )
    );
});

$router->get('/admin/users/{id}/reset-password', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new UserController(new Database(Config::get('database'))))->showResetPassword($request)
        )
    );
});

$router->post('/admin/users/{id}/reset-password', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new UserController(new Database(Config::get('database'))))->resetPassword($request)
        )
    );
});

$router->get('/admin/integration-sources', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new IntegrationSourceController(new Database(Config::get('database'))))->index($request)
        )
    );
});

$router->get('/admin/integration-sources/create', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new IntegrationSourceController(new Database(Config::get('database'))))->create()
        )
    );
});

$router->post('/admin/integration-sources', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new IntegrationSourceController(new Database(Config::get('database'))))->store($request)
        )
    );
});

$router->get('/admin/integration-sources/{id}', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new IntegrationSourceController(new Database(Config::get('database'))))->show($request)
        )
    );
});

$router->get('/admin/integration-sources/{id}/edit', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new IntegrationSourceController(new Database(Config::get('database'))))->edit($request)
        )
    );
});

$router->post('/admin/integration-sources/{id}', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new IntegrationSourceController(new Database(Config::get('database'))))->update($request)
        )
    );
});

$router->post('/admin/integration-sources/{id}/activate', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new IntegrationSourceController(new Database(Config::get('database'))))->activate($request)
        )
    );
});

$router->post('/admin/integration-sources/{id}/deactivate', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new IntegrationSourceController(new Database(Config::get('database'))))->deactivate($request)
        )
    );
});

$router->get('/admin/integration-destinations', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new IntegrationDestinationController(new Database(Config::get('database'))))->index($request)
        )
    );
});

$router->get('/admin/integration-destinations/create', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new IntegrationDestinationController(new Database(Config::get('database'))))->create()
        )
    );
});

$router->post('/admin/integration-destinations', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new IntegrationDestinationController(new Database(Config::get('database'))))->store($request)
        )
    );
});

$router->get('/admin/integration-destinations/{id}', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new IntegrationDestinationController(new Database(Config::get('database'))))->show($request)
        )
    );
});

$router->get('/admin/integration-destinations/{id}/edit', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new IntegrationDestinationController(new Database(Config::get('database'))))->edit($request)
        )
    );
});

$router->post('/admin/integration-destinations/{id}', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new IntegrationDestinationController(new Database(Config::get('database'))))->update($request)
        )
    );
});

$router->post('/admin/integration-destinations/{id}/activate', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new IntegrationDestinationController(new Database(Config::get('database'))))->activate($request)
        )
    );
});

$router->post('/admin/integration-destinations/{id}/deactivate', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new IntegrationDestinationController(new Database(Config::get('database'))))->deactivate($request)
        )
    );
});

$router->get('/admin/automation', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new AutomationController(new Database(Config::get('database'))))->index($request)
        )
    );
});

$router->get('/admin/events', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new EventController(new Database(Config::get('database'))))->index($request)
        )
    );
});

$router->get('/admin/events/{id}', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new EventController(new Database(Config::get('database'))))->show($request)
        )
    );
});

$router->post('/admin/events/{id}/reprocess', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new OwnerMiddleware())->handle(
            static fn (): Response => (new EventController(new Database(Config::get('database'))))->reprocess($request)
        )
    );
});

$router->get('/profile', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new ProfileController(new Database(Config::get('database'))))->show()
    );
});

$router->post('/profile', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new ProfileController(new Database(Config::get('database'))))->update($request)
    );
});

$router->get('/change-password', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new PasswordController(new Database(Config::get('database'))))->show()
    );
});

$router->post('/change-password', static function (Request $request): Response {
    return (new AuthMiddleware(new AuthService()))->handle(
        static fn (): Response => (new PasswordController(new Database(Config::get('database'))))->update($request)
    );
});
