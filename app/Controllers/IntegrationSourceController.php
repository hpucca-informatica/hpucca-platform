<?php

declare(strict_types=1);

namespace HPucca\Platform\Controllers;

use HPucca\Platform\Core\Config;
use HPucca\Platform\Core\Database;
use HPucca\Platform\Core\Request;
use HPucca\Platform\Core\Response;
use HPucca\Platform\Core\View;
use HPucca\Platform\Models\IntegrationSource;
use HPucca\Platform\Repositories\IntegrationSourceRepository;
use HPucca\Platform\Repositories\TenantRepository;
use HPucca\Platform\Services\ApiKeyService;
use HPucca\Platform\Services\AuthService;
use HPucca\Platform\Services\CsrfService;
use HPucca\Platform\Services\FlashService;
use HPucca\Platform\Services\IntegrationSourceService;
use HPucca\Platform\Services\PublicCodeGenerator;
use RuntimeException;
use Throwable;

final readonly class IntegrationSourceController
{
    public function __construct(
        private Database $database,
    ) {
    }

    public function index(Request $request): Response
    {
        $tenantQuery = $request->query('tenant_id');
        $tenantId = $tenantQuery === '' ? null : max(1, (int) $tenantQuery);

        return $this->admin('integration-sources/index.php', [
            'title' => 'Fontes de integracao',
            'activeMenu' => 'integration-sources',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Fontes de integracao' => null],
            ...$this->service()->list($request->query('search'), $tenantId, $request->integerQuery('page')),
        ]);
    }

    public function create(): Response
    {
        return $this->form('integration-sources/create.php', [
            'tenant_id' => '',
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

        $result = $this->service()->create($this->sourceInput($request));

        if ($result['errors'] !== []) {
            return $this->form('integration-sources/create.php', $result['values'], $result['errors'], 422);
        }

        FlashService::add('Fonte de integracao cadastrada com sucesso.');

        return $this->admin('integration-sources/api-key-created.php', [
            'title' => 'API key criada',
            'activeMenu' => 'integration-sources',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Fontes de integracao' => '/admin/integration-sources', 'API key' => null],
            'source' => $result['source'],
            'apiKey' => $result['apiKey'],
        ]);
    }

    public function show(Request $request): Response
    {
        $source = $this->sourceFromRequest($request);

        if (!$source instanceof IntegrationSource) {
            FlashService::add('Fonte de integracao nao encontrada.');

            return Response::redirect('/admin/integration-sources');
        }

        return $this->admin('integration-sources/show.php', [
            'title' => 'Fonte de integracao',
            'activeMenu' => 'integration-sources',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Fontes de integracao' => '/admin/integration-sources', $source->name => null],
            'source' => $source,
        ]);
    }

    public function edit(Request $request): Response
    {
        $source = $this->sourceFromRequest($request);

        if (!$source instanceof IntegrationSource) {
            FlashService::add('Fonte de integracao nao encontrada.');

            return Response::redirect('/admin/integration-sources');
        }

        return $this->form('integration-sources/edit.php', [
            'tenant_id' => (string) $source->tenantId,
            'name' => $source->name,
            'slug' => $source->slug,
            'status' => $source->status,
        ], [], 200, $source);
    }

    public function update(Request $request): Response
    {
        if (!$this->csrf($request)) {
            return $this->forbidden();
        }

        $id = $this->id($request);
        $source = $this->service()->find($id);

        if (!$source instanceof IntegrationSource) {
            FlashService::add('Fonte de integracao nao encontrada.');

            return Response::redirect('/admin/integration-sources');
        }

        $result = $this->service()->update($id, $this->sourceInput($request));

        if ($result['errors'] !== []) {
            return $this->form('integration-sources/edit.php', $result['values'], $result['errors'], 422, $source);
        }

        FlashService::add('Fonte de integracao atualizada com sucesso.');

        return Response::redirect('/admin/integration-sources/' . $id);
    }

    public function activate(Request $request): Response
    {
        if (!$this->csrf($request)) {
            return $this->forbidden();
        }

        $id = $this->id($request);
        FlashService::add($this->service()->activate($id) ? 'Fonte ativada com sucesso.' : 'Nao foi possivel ativar a fonte.');

        return Response::redirect('/admin/integration-sources/' . $id);
    }

    public function deactivate(Request $request): Response
    {
        if (!$this->csrf($request)) {
            return $this->forbidden();
        }

        $id = $this->id($request);
        FlashService::add($this->service()->deactivate($id) ? 'Fonte inativada com sucesso.' : 'Nao foi possivel inativar a fonte.');

        return Response::redirect('/admin/integration-sources/' . $id);
    }

    private function service(): IntegrationSourceService
    {
        try {
            $connection = $this->database->connection();

            return new IntegrationSourceService(
                new IntegrationSourceRepository($connection),
                new TenantRepository($connection),
                new PublicCodeGenerator($connection),
                new ApiKeyService(),
            );
        } catch (Throwable) {
            throw new RuntimeException('Integration source module unavailable.');
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
        ?IntegrationSource $source = null,
    ): Response {
        return $this->admin($view, [
            'title' => str_contains($view, 'create') ? 'Nova fonte de integracao' : 'Editar fonte de integracao',
            'activeMenu' => 'integration-sources',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Fontes de integracao' => '/admin/integration-sources', 'Formulario' => null],
            'values' => $values,
            'errors' => $errors,
            'source' => $source,
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
            'activeMenu' => 'integration-sources',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Fontes de integracao' => '/admin/integration-sources', 'Requisicao recusada' => null],
            'message' => 'Nao foi possivel validar a requisicao.',
        ], 403);
    }

    /**
     * @return array{tenant_id: string, name: string, slug: string, status: string}
     */
    private function sourceInput(Request $request): array
    {
        return [
            'tenant_id' => $request->input('tenant_id'),
            'name' => $request->input('name'),
            'slug' => $request->input('slug'),
            'status' => $request->input('status'),
        ];
    }

    private function sourceFromRequest(Request $request): ?IntegrationSource
    {
        return $this->service()->find($this->id($request));
    }

    private function id(Request $request): int
    {
        return max(0, (int) ($request->param('id') ?? 0));
    }
}
