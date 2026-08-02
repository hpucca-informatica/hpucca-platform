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

final readonly class PasswordController
{
    public function __construct(
        private Database $database,
    ) {
    }

    public function show(): Response
    {
        return $this->admin('password/change.php', [
            'title' => 'Alterar senha',
            'activeMenu' => 'change-password',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Alterar senha' => null],
            'errors' => [],
        ]);
    }

    public function update(Request $request): Response
    {
        if (!CsrfService::validate($request->input(CsrfService::FIELD, '', false))) {
            return $this->admin('placeholders/module.php', [
                'title' => 'Requisicao recusada',
                'activeMenu' => 'change-password',
                'breadcrumbs' => ['Dashboard' => '/dashboard', 'Alterar senha' => null],
                'message' => 'Nao foi possivel validar a requisicao.',
            ], 403);
        }

        $result = $this->service()->changeOwnPassword(
            $request->input('current_password', '', false),
            $request->input('password', '', false),
            $request->input('password_confirmation', '', false),
        );

        if (!$result['ok']) {
            return $this->admin('password/change.php', [
                'title' => 'Alterar senha',
                'activeMenu' => 'change-password',
                'breadcrumbs' => ['Dashboard' => '/dashboard', 'Alterar senha' => null],
                'errors' => $result['errors'],
            ], 422);
        }

        FlashService::add('Senha alterada com sucesso.');

        return Response::redirect('/change-password');
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
