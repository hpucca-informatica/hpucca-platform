<?php

declare(strict_types=1);

namespace HPucca\Platform\Controllers;

use HPucca\Platform\Core\Response;
use HPucca\Platform\Core\View;
use HPucca\Platform\Services\AuthService;

final readonly class CompanyController
{
    public function index(): Response
    {
        return View::admin('placeholders/module.php', [
            'title' => 'Empresas',
            'activeMenu' => 'companies',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Empresas' => null],
            'user' => AuthService::sessionUser(),
            'tenant' => AuthService::sessionTenant(),
            'message' => 'O cadastro de empresas sera implementado nas proximas etapas.',
        ]);
    }
}
