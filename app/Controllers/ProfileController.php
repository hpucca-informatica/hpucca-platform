<?php

declare(strict_types=1);

namespace HPucca\Platform\Controllers;

use HPucca\Platform\Core\Config;
use HPucca\Platform\Core\Database;
use HPucca\Platform\Core\Request;
use HPucca\Platform\Core\Response;
use HPucca\Platform\Core\View;
use HPucca\Platform\Repositories\TenantRepository;
use HPucca\Platform\Repositories\UserRepository;
use HPucca\Platform\Services\AuthService;
use HPucca\Platform\Services\CsrfService;
use HPucca\Platform\Services\FlashService;
use HPucca\Platform\Services\UserService;

final readonly class ProfileController
{
    public function __construct(
        private Database $database,
    ) {
    }

    public function show(): Response
    {
        $service = $this->service();
        $current = $service->currentUser();

        if ($current === null) {
            return Response::redirect('/login');
        }

        return $this->admin('profile/show.php', [
            'title' => 'Perfil',
            'activeMenu' => 'profile',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Perfil' => null],
            'currentUser' => $current,
            'currentTenant' => $service->tenantFor($current),
            'values' => ['name' => $current->name, 'email' => $current->email ?? ''],
            'errors' => [],
        ]);
    }

    public function update(Request $request): Response
    {
        if (!CsrfService::validate($request->input(CsrfService::FIELD, '', false))) {
            return $this->admin('placeholders/module.php', [
                'title' => 'Requisicao recusada',
                'activeMenu' => 'profile',
                'breadcrumbs' => ['Dashboard' => '/dashboard', 'Perfil' => null],
                'message' => 'Nao foi possivel validar a requisicao.',
            ], 403);
        }

        $service = $this->service();
        $result = $service->updateProfile($request->input('name'), $request->input('email'));

        if ($result['errors'] !== []) {
            $current = $service->currentUser();

            return $this->admin('profile/show.php', [
                'title' => 'Perfil',
                'activeMenu' => 'profile',
                'breadcrumbs' => ['Dashboard' => '/dashboard', 'Perfil' => null],
                'currentUser' => $current,
                'currentTenant' => $current === null ? null : $service->tenantFor($current),
                'values' => $result['values'],
                'errors' => $result['errors'],
            ], 422);
        }

        FlashService::add('Perfil atualizado com sucesso.', 'success');

        return Response::redirect('/profile');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function admin(string $view, array $data, int $status = 200): Response
    {
        return View::admin($view, array_merge([
            'user' => AuthService::sessionUser(),
            'tenant' => AuthService::sessionTenant(),
            'version' => Config::get('app.version'),
        ], $data))->withStatus($status);
    }

    private function service(): UserService
    {
        $connection = $this->database->connection();

        return new UserService(new UserRepository($connection), new TenantRepository($connection), new AuthService());
    }
}
