<?php

declare(strict_types=1);

namespace HPucca\Platform\Controllers;

use HPucca\Platform\Core\Response;
use HPucca\Platform\Core\View;
use HPucca\Platform\Services\AuthService;

final readonly class ProfileController
{
    public function show(): Response
    {
        return View::admin('placeholders/module.php', [
            'title' => 'Perfil',
            'activeMenu' => 'profile',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Perfil' => null],
            'user' => AuthService::sessionUser(),
            'tenant' => AuthService::sessionTenant(),
            'message' => 'A edicao de perfil sera implementada nas proximas etapas.',
        ]);
    }
}
