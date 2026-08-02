<?php

declare(strict_types=1);

namespace HPucca\Platform\Controllers;

use HPucca\Platform\Core\Response;
use HPucca\Platform\Core\View;
use HPucca\Platform\Services\AuthService;

final readonly class PasswordController
{
    public function show(): Response
    {
        return View::admin('placeholders/module.php', [
            'title' => 'Alterar senha',
            'activeMenu' => 'change-password',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Alterar senha' => null],
            'user' => AuthService::sessionUser(),
            'tenant' => AuthService::sessionTenant(),
            'message' => 'A alteracao de senha sera implementada nas proximas etapas.',
        ]);
    }
}
