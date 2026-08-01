<?php

declare(strict_types=1);

namespace HPucca\Platform\Controllers;

use HPucca\Platform\Core\Database;
use HPucca\Platform\Core\Request;
use HPucca\Platform\Core\Response;
use HPucca\Platform\Repositories\TenantRepository;
use HPucca\Platform\Repositories\UserRepository;
use HPucca\Platform\Services\AuthService;
use Throwable;

final readonly class AuthController
{
    public function __construct(
        private Database $database,
    ) {
    }

    public function loginForm(): Response
    {
        return $this->render('login.php');
    }

    public function login(Request $request): Response
    {
        $tenant = $request->input('tenant');
        $login = $request->input('login');
        $password = $request->input('password', '', false);

        if ($tenant === '' || $login === '' || $password === '') {
            return $this->render('login.php', [
                'error' => 'Informe empresa, usuario e senha.',
            ], 422);
        }

        try {
            $connection = $this->database->connection();
            $auth = new AuthService(
                new TenantRepository($connection),
                new UserRepository($connection),
            );
        } catch (Throwable) {
            return $this->render('login.php', [
                'error' => 'Autenticacao indisponivel no momento.',
            ], 503);
        }

        if (!$auth->attempt($tenant, $login, $password)) {
            return $this->render('login.php', [
                'error' => 'Empresa, usuario ou senha invalidos.',
            ], 401);
        }

        return Response::redirect('/dashboard');
    }

    public function logout(): Response
    {
        (new AuthService())->logout();

        return Response::redirect('/login');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $view, array $data = [], int $statusCode = 200): Response
    {
        extract($data, EXTR_SKIP);

        ob_start();
        require dirname(__DIR__, 2) . '/views/' . $view;
        $content = ob_get_clean();

        return Response::html($content === false ? '' : $content, $statusCode);
    }
}
