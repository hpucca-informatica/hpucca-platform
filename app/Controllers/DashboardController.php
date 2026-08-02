<?php

declare(strict_types=1);

namespace HPucca\Platform\Controllers;

use HPucca\Platform\Core\Config;
use HPucca\Platform\Core\Database;
use HPucca\Platform\Core\Response;
use HPucca\Platform\Core\View;
use HPucca\Platform\Repositories\TenantRepository;
use HPucca\Platform\Repositories\UserRepository;
use HPucca\Platform\Services\AuthService;
use HPucca\Platform\Services\UserService;
use RuntimeException;

final readonly class DashboardController
{
    public function __construct(
        private Database $database,
    ) {
    }

    public function index(): Response
    {
        try {
            $connection = $this->database->connection();
            $auth = new AuthService(new TenantRepository($connection), new UserRepository($connection));
            $users = new UserService(
                new UserRepository($connection),
                new TenantRepository($connection),
                $auth,
            );
            $user = $users->currentUser();
            $tenant = $user === null ? null : $users->tenantFor($user);
        } catch (RuntimeException) {
            $user = null;
            $tenant = null;
        }

        if ($user === null || $tenant === null) {
            return Response::redirect('/login');
        }

        return View::admin('dashboard.php', [
            'title' => 'Dashboard',
            'activeMenu' => 'dashboard',
            'breadcrumbs' => ['Dashboard' => null],
            'user' => $user,
            'tenant' => $tenant,
            'version' => Config::get('app.version'),
            'databaseStatus' => $this->database->isConnected() ? 'connected' : 'unavailable',
        ]);
    }
}
