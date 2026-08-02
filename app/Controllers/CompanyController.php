<?php

declare(strict_types=1);

namespace HPucca\Platform\Controllers;

use HPucca\Platform\Core\Config;
use HPucca\Platform\Core\Database;
use HPucca\Platform\Core\Request;
use HPucca\Platform\Core\Response;
use HPucca\Platform\Core\View;
use HPucca\Platform\Models\Tenant;
use HPucca\Platform\Repositories\CompanyRepository;
use HPucca\Platform\Services\AuthService;
use HPucca\Platform\Services\CompanyService;
use HPucca\Platform\Services\CsrfService;
use HPucca\Platform\Services\FlashService;
use HPucca\Platform\Services\PublicCodeGenerator;
use RuntimeException;
use Throwable;

final readonly class CompanyController
{
    public function __construct(
        private Database $database,
    ) {
    }

    public function index(Request $request): Response
    {
        $service = $this->service();
        $result = $service->list($request->query('search'), $request->integerQuery('page'));

        return $this->admin('companies/index.php', [
            'title' => 'Empresas',
            'activeMenu' => 'companies',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Empresas' => null],
            ...$result,
        ]);
    }

    public function create(): Response
    {
        return $this->form('companies/create.php', [
            'name' => '',
            'slug' => '',
            'status' => 'active',
        ]);
    }

    public function store(Request $request): Response
    {
        if (!$this->csrf($request)) {
            return $this->forbidden();
        }

        $result = $this->service()->create($this->companyInput($request));

        if ($result['errors'] !== []) {
            return $this->form('companies/create.php', $result['values'], $result['errors'], 422);
        }

        FlashService::add('Empresa cadastrada com sucesso.');

        return Response::redirect('/admin/companies/' . $result['company']->id);
    }

    public function show(Request $request): Response
    {
        $company = $this->companyFromRequest($request);

        if ($company === null) {
            return Response::redirect('/admin/companies');
        }

        return $this->admin('companies/show.php', [
            'title' => 'Empresa',
            'activeMenu' => 'companies',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Empresas' => '/admin/companies', $company->name => null],
            'company' => $company,
        ]);
    }

    public function edit(Request $request): Response
    {
        $company = $this->companyFromRequest($request);

        if ($company === null) {
            return Response::redirect('/admin/companies');
        }

        return $this->form('companies/edit.php', [
            'name' => $company->name,
            'slug' => $company->slug,
            'status' => $company->status,
        ], [], 200, $company);
    }

    public function update(Request $request): Response
    {
        if (!$this->csrf($request)) {
            return $this->forbidden();
        }

        $id = $this->id($request);
        $company = $this->service()->find($id);

        if ($company === null) {
            return Response::redirect('/admin/companies');
        }

        $result = $this->service()->update($id, $this->companyInput($request), (new AuthService())->tenantId());

        if ($result['errors'] !== []) {
            return $this->form('companies/edit.php', $result['values'], $result['errors'], 422, $company);
        }

        FlashService::add('Empresa atualizada com sucesso.');

        return Response::redirect('/admin/companies/' . $id);
    }

    public function activate(Request $request): Response
    {
        if (!$this->csrf($request)) {
            return $this->forbidden();
        }

        $id = $this->id($request);
        $company = $this->service()->find($id);

        if ($company === null) {
            FlashService::add('Empresa nao encontrada.');

            return Response::redirect('/admin/companies');
        }

        if (!$this->service()->activate($id)) {
            FlashService::add('Nao foi possivel alterar o status da empresa.');

            return Response::redirect('/admin/companies/' . $id);
        }

        FlashService::add('Empresa ativada com sucesso.');

        return Response::redirect('/admin/companies/' . $id);
    }

    public function deactivate(Request $request): Response
    {
        if (!$this->csrf($request)) {
            return $this->forbidden();
        }

        $id = $this->id($request);
        $company = $this->service()->find($id);

        if ($company === null) {
            FlashService::add('Empresa nao encontrada.');

            return Response::redirect('/admin/companies');
        }

        if (!$this->service()->deactivate($id, (new AuthService())->tenantId())) {
            $message = (new AuthService())->tenantId() === $id
                ? 'Nao e possivel inativar a empresa atual.'
                : 'Nao foi possivel alterar o status da empresa.';
            FlashService::add($message);

            return Response::redirect('/admin/companies/' . $id);
        }

        FlashService::add('Empresa inativada com sucesso.');

        return Response::redirect('/admin/companies/' . $id);
    }

    private function service(): CompanyService
    {
        try {
            $connection = $this->database->connection();

            return new CompanyService(
                new CompanyRepository($connection),
                new PublicCodeGenerator($connection),
            );
        } catch (Throwable) {
            throw new RuntimeException('Company module unavailable.');
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
    private function form(
        string $view,
        array $values,
        array $errors = [],
        int $status = 200,
        ?Tenant $company = null,
    ): Response {
        return $this->admin($view, [
            'title' => str_contains($view, 'create') ? 'Nova empresa' : 'Editar empresa',
            'activeMenu' => 'companies',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Empresas' => '/admin/companies', 'Formulario' => null],
            'values' => $values,
            'errors' => $errors,
            'company' => $company,
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
            'activeMenu' => 'companies',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Empresas' => '/admin/companies', 'Requisicao recusada' => null],
            'message' => 'Nao foi possivel validar a requisicao.',
        ], 403);
    }

    /**
     * @return array{name: string, slug: string, status: string}
     */
    private function companyInput(Request $request): array
    {
        return [
            'name' => $request->input('name'),
            'slug' => $request->input('slug'),
            'status' => $request->input('status'),
        ];
    }

    private function companyFromRequest(Request $request): ?Tenant
    {
        return $this->service()->find($this->id($request));
    }

    private function id(Request $request): int
    {
        return max(0, (int) ($request->param('id') ?? 0));
    }
}
