<?php

declare(strict_types=1);

namespace HPucca\Platform\Middleware;

use HPucca\Platform\Core\Response;
use HPucca\Platform\Core\View;
use HPucca\Platform\Services\AuthService;

final readonly class OwnerMiddleware
{
    public function handle(callable $next): Response
    {
        $user = AuthService::sessionUser();

        if (($user['type'] ?? '') !== 'owner') {
            return View::admin('placeholders/module.php', [
                'title' => 'Acesso negado',
                'activeMenu' => '',
                'breadcrumbs' => ['Dashboard' => '/dashboard', 'Acesso negado' => null],
                'user' => $user,
                'tenant' => AuthService::sessionTenant(),
                'message' => 'Voce nao tem acesso a este modulo.',
            ])->withStatus(403);
        }

        return $next();
    }
}
