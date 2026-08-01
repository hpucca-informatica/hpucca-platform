<?php

declare(strict_types=1);

namespace HPucca\Platform\Controllers;

use HPucca\Platform\Core\Config;
use HPucca\Platform\Core\Database;
use HPucca\Platform\Core\Response;
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

        return $this->render('dashboard.php', [
            'user' => $user,
            'tenant' => $tenant,
            'version' => Config::get('app.version'),
            'databaseStatus' => $this->database->isConnected() ? 'connected' : 'unavailable',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $view, array $data = []): Response
    {
        extract($data, EXTR_SKIP);

        ob_start();
        require dirname(__DIR__, 2) . '/views/' . $view;
        $content = ob_get_clean();

        return Response::html($content === false ? '' : $content);
    }
}
