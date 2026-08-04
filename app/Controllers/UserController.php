<?php

declare(strict_types=1);

namespace HPucca\Platform\Controllers;

use HPucca\Platform\Core\Config;
use HPucca\Platform\Core\Database;
use HPucca\Platform\Core\Request;
use HPucca\Platform\Core\Response;
use HPucca\Platform\Core\View;
use HPucca\Platform\Models\User;
use HPucca\Platform\Repositories\TenantRepository;
use HPucca\Platform\Repositories\UserRepository;
use HPucca\Platform\Services\AuthService;
use HPucca\Platform\Services\CsrfService;
use HPucca\Platform\Services\FlashService;
use HPucca\Platform\Services\PublicCodeGenerator;
use HPucca\Platform\Services\UserService;
use RuntimeException;
use Throwable;

final readonly class UserController
{
    public function __construct(
        private Database $database,
    ) {
    }

    public function index(Request $request): Response
    {
        $tenantQuery = $request->query('tenant_id');
        $tenantId = $tenantQuery === '' ? null : max(1, (int) $tenantQuery);
        $result = $this->service()->list($request->query('search'), $tenantId, $request->integerQuery('page'));

        return $this->admin('users/index.php', [
            'title' => 'Usuarios',
            'activeMenu' => 'users',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Usuarios' => null],
            ...$result,
        ]);
    }

    public function create(): Response
    {
        return $this->form('users/create.php', [
            'tenant_id' => '',
            'name' => '',
            'login' => '',
            'email' => '',
            'type' => 'user',
            'status' => 'active',
        ]);
    }

    public function store(Request $request): Response
    {
        if (!$this->csrf($request)) {
            return $this->forbidden();
        }

        $result = $this->service()->create($this->userInput($request) + [
            'password' => $request->input('password', '', false),
            'password_confirmation' => $request->input('password_confirmation', '', false),
        ]);

        if ($result['errors'] !== []) {
            return $this->form('users/create.php', $result['values'], $result['errors'], 422);
        }

        FlashService::add('Usuario cadastrado com sucesso.', 'success');

        return Response::redirect('/admin/users/' . $result['user']->id);
    }

    public function show(Request $request): Response
    {
        $user = $this->userFromRequest($request);

        if (!$user instanceof User) {
            FlashService::add('Usuario nao encontrado.', 'error');

            return Response::redirect('/admin/users');
        }

        return $this->admin('users/show.php', [
            'title' => 'Usuario',
            'activeMenu' => 'users',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Usuarios' => '/admin/users', $user->name => null],
            'managedUser' => $user,
            'managedTenant' => $this->service()->tenantFor($user),
        ]);
    }

    public function edit(Request $request): Response
    {
        $user = $this->userFromRequest($request);

        if (!$user instanceof User) {
            FlashService::add('Usuario nao encontrado.', 'error');

            return Response::redirect('/admin/users');
        }

        return $this->form('users/edit.php', $this->valuesFromUser($user), [], 200, $user);
    }

    public function update(Request $request): Response
    {
        if (!$this->csrf($request)) {
            return $this->forbidden();
        }

        $id = $this->id($request);
        $user = $this->service()->find($id);

        if (!$user instanceof User) {
            FlashService::add('Usuario nao encontrado.', 'error');

            return Response::redirect('/admin/users');
        }

        $result = $this->service()->update($id, $this->userInput($request), (new AuthService())->userId());

        if ($result['errors'] !== []) {
            return $this->form('users/edit.php', $result['values'], $result['errors'], 422, $user);
        }

        FlashService::add('Usuario atualizado com sucesso.', 'success');

        return Response::redirect('/admin/users/' . $id);
    }

    public function activate(Request $request): Response
    {
        if (!$this->csrf($request)) {
            return $this->forbidden();
        }

        $id = $this->id($request);
        $result = $this->service()->activate($id);

        if (!$result['ok']) {
            FlashService::add($result['message'], 'warning');

            return Response::redirect('/admin/users');
        }

        FlashService::add('Usuario ativado com sucesso.', 'success');

        return Response::redirect('/admin/users/' . $id);
    }

    public function deactivate(Request $request): Response
    {
        if (!$this->csrf($request)) {
            return $this->forbidden();
        }

        $id = $this->id($request);
        $result = $this->service()->deactivate($id, (new AuthService())->userId());

        if (!$result['ok']) {
            FlashService::add($result['message'], 'warning');

            return Response::redirect('/admin/users');
        }

        FlashService::add('Usuario inativado com sucesso.', 'success');

        return Response::redirect('/admin/users/' . $id);
    }

    public function showResetPassword(Request $request): Response
    {
        $user = $this->userFromRequest($request);

        if (!$user instanceof User) {
            FlashService::add('Usuario nao encontrado.', 'error');

            return Response::redirect('/admin/users');
        }

        return $this->admin('users/reset-password.php', [
            'title' => 'Redefinir senha',
            'activeMenu' => 'users',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Usuarios' => '/admin/users', $user->name => '/admin/users/' . $user->id, 'Senha' => null],
            'managedUser' => $user,
            'errors' => [],
        ]);
    }

    public function resetPassword(Request $request): Response
    {
        if (!$this->csrf($request)) {
            return $this->forbidden();
        }

        $id = $this->id($request);
        $user = $this->service()->find($id);

        if (!$user instanceof User) {
            FlashService::add('Usuario nao encontrado.', 'error');

            return Response::redirect('/admin/users');
        }

        $result = $this->service()->resetPassword(
            $id,
            $request->input('password', '', false),
            $request->input('password_confirmation', '', false),
        );

        if (!$result['ok']) {
            return $this->admin('users/reset-password.php', [
                'title' => 'Redefinir senha',
                'activeMenu' => 'users',
                'breadcrumbs' => ['Dashboard' => '/dashboard', 'Usuarios' => '/admin/users', $user->name => '/admin/users/' . $user->id, 'Senha' => null],
                'managedUser' => $user,
                'errors' => $result['errors'],
            ], 422);
        }

        FlashService::add('Senha redefinida com sucesso.', 'success');

        return Response::redirect('/admin/users/' . $id);
    }

    private function service(): UserService
    {
        try {
            $connection = $this->database->connection();

            return new UserService(
                new UserRepository($connection),
                new TenantRepository($connection),
                new AuthService(),
                new PublicCodeGenerator($connection),
            );
        } catch (Throwable) {
            throw new RuntimeException('User module unavailable.');
        }
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

    /**
     * @param array<string, string> $values
     * @param array<string, string> $errors
     */
    private function form(string $view, array $values, array $errors = [], int $status = 200, ?User $managedUser = null): Response
    {
        return $this->admin($view, [
            'title' => str_contains($view, 'create') ? 'Novo usuario' : 'Editar usuario',
            'activeMenu' => 'users',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Usuarios' => '/admin/users', 'Formulario' => null],
            'values' => $values,
            'errors' => $errors,
            'managedUser' => $managedUser,
            'tenants' => $this->service()->tenants(),
        ], $status);
    }

    private function csrf(Request $request): bool
    {
        return CsrfService::validate($request->input(CsrfService::FIELD, '', false));
    }

    private function forbidden(): Response
    {
        return $this->admin('placeholders/module.php', [
            'title' => 'Requisicao recusada',
            'activeMenu' => 'users',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Usuarios' => '/admin/users', 'Requisicao recusada' => null],
            'message' => 'Nao foi possivel validar a requisicao.',
        ], 403);
    }

    /**
     * @return array<string, string>
     */
    private function userInput(Request $request): array
    {
        return [
            'tenant_id' => $request->input('tenant_id'),
            'name' => $request->input('name'),
            'login' => $request->input('login'),
            'email' => $request->input('email'),
            'type' => $request->input('type'),
            'status' => $request->input('status'),
        ];
    }

    private function userFromRequest(Request $request): ?User
    {
        return $this->service()->find($this->id($request));
    }

    /**
     * @return array<string, string>
     */
    private function valuesFromUser(User $user): array
    {
        return [
            'tenant_id' => (string) $user->tenantId,
            'name' => $user->name,
            'login' => $user->login,
            'email' => $user->email ?? '',
            'type' => $user->type,
            'status' => $user->status,
        ];
    }

    private function id(Request $request): int
    {
        return max(0, (int) ($request->param('id') ?? 0));
    }
}
