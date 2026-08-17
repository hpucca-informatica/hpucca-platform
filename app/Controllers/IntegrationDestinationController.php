<?php

declare(strict_types=1);

namespace HPucca\Platform\Controllers;

use HPucca\Platform\Core\Config;
use HPucca\Platform\Core\Database;
use HPucca\Platform\Core\Request;
use HPucca\Platform\Core\Response;
use HPucca\Platform\Core\View;
use HPucca\Platform\Models\IntegrationDestination;
use HPucca\Platform\Repositories\IntegrationDestinationRepository;
use HPucca\Platform\Repositories\TenantRepository;
use HPucca\Platform\Services\AuthService;
use HPucca\Platform\Services\CsrfService;
use HPucca\Platform\Services\FlashService;
use HPucca\Platform\Services\IntegrationDestinationService;
use HPucca\Platform\Services\PublicCodeGenerator;
use RuntimeException;
use Throwable;

final readonly class IntegrationDestinationController
{
    public function __construct(
        private Database $database,
    ) {
    }

    public function index(Request $request): Response
    {
        $tenantQuery = $request->query('tenant_id');
        $tenantId = $tenantQuery === '' ? null : max(1, (int) $tenantQuery);

        return $this->admin('integration-destinations/index.php', [
            'title' => 'Destinos de integracao',
            'activeMenu' => 'integration-destinations',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Destinos de integracao' => null],
            ...$this->service()->list($request->query('search'), $tenantId, $request->integerQuery('page')),
        ]);
    }

    public function create(): Response
    {
        return $this->form('integration-destinations/create.php', [
            'tenant_id' => '',
            'name' => '',
            'slug' => '',
            'type' => 'n8n',
            'status' => 'active',
        ]);
    }

    public function store(Request $request): Response
    {
        if (!$this->csrf($request)) {
            return $this->forbidden();
        }

        $result = $this->service()->create($this->destinationInput($request));

        if ($result['errors'] !== []) {
            return $this->form('integration-destinations/create.php', $result['values'], $result['errors'], 422);
        }

        FlashService::add('Destino de integracao cadastrado com sucesso.', 'success');

        return Response::redirect('/admin/integration-destinations/' . $result['destination']->id);
    }

    public function show(Request $request): Response
    {
        $destination = $this->destinationFromRequest($request);

        if (!$destination instanceof IntegrationDestination) {
            FlashService::add('Destino de integracao nao encontrado.', 'error');

            return Response::redirect('/admin/integration-destinations');
        }

        return $this->admin('integration-destinations/show.php', [
            'title' => 'Destino de integracao',
            'activeMenu' => 'integration-destinations',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Destinos de integracao' => '/admin/integration-destinations', $destination->name => null],
            'destination' => $destination,
        ]);
    }

    public function edit(Request $request): Response
    {
        $destination = $this->destinationFromRequest($request);

        if (!$destination instanceof IntegrationDestination) {
            FlashService::add('Destino de integracao nao encontrado.', 'error');

            return Response::redirect('/admin/integration-destinations');
        }

        return $this->form('integration-destinations/edit.php', [
            'tenant_id' => (string) $destination->tenantId,
            'name' => $destination->name,
            'slug' => $destination->slug,
            'type' => $destination->type,
            'status' => $destination->status,
        ], [], 200, $destination);
    }

    public function update(Request $request): Response
    {
        if (!$this->csrf($request)) {
            return $this->forbidden();
        }

        $id = $this->id($request);
        $destination = $this->service()->find($id);

        if (!$destination instanceof IntegrationDestination) {
            FlashService::add('Destino de integracao nao encontrado.', 'error');

            return Response::redirect('/admin/integration-destinations');
        }

        $result = $this->service()->update($id, $this->destinationInput($request));

        if ($result['errors'] !== []) {
            return $this->form('integration-destinations/edit.php', $result['values'], $result['errors'], 422, $destination);
        }

        FlashService::add('Destino de integracao atualizado com sucesso.', 'success');

        return Response::redirect('/admin/integration-destinations/' . $id);
    }

    public function activate(Request $request): Response
    {
        if (!$this->csrf($request)) {
            return $this->forbidden();
        }

        $id = $this->id($request);
        $activated = $this->service()->activate($id);
        FlashService::add($activated ? 'Destino ativado com sucesso.' : 'Nao foi possivel ativar o destino.', $activated ? 'success' : 'error');

        return Response::redirect('/admin/integration-destinations/' . $id);
    }

    public function deactivate(Request $request): Response
    {
        if (!$this->csrf($request)) {
            return $this->forbidden();
        }

        $id = $this->id($request);
        $deactivated = $this->service()->deactivate($id);
        FlashService::add($deactivated ? 'Destino inativado com sucesso.' : 'Nao foi possivel inativar o destino.', $deactivated ? 'success' : 'error');

        return Response::redirect('/admin/integration-destinations/' . $id);
    }

    private function service(): IntegrationDestinationService
    {
        try {
            $connection = $this->database->connection();

            return new IntegrationDestinationService(
                new IntegrationDestinationRepository($connection),
                new TenantRepository($connection),
                new PublicCodeGenerator($connection),
            );
        } catch (Throwable) {
            throw new RuntimeException('Integration destination module unavailable.');
        }
    }

    private function admin(string $view, array $data, int $status = 200): Response
    {
        return View::admin($view, array_merge([
            'user' => AuthService::sessionUser(),
            'tenant' => AuthService::sessionTenant(),
            'version' => Config::get('app.version'),
        ], $data))->withStatus($status);
    }

    private function form(
        string $view,
        array $values,
        array $errors = [],
        int $status = 200,
        ?IntegrationDestination $destination = null,
    ): Response {
        return $this->admin($view, [
            'title' => str_contains($view, 'create') ? 'Novo destino de integracao' : 'Editar destino de integracao',
            'activeMenu' => 'integration-destinations',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Destinos de integracao' => '/admin/integration-destinations', 'Formulario' => null],
            'values' => $values,
            'errors' => $errors,
            'destination' => $destination,
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
            'activeMenu' => 'integration-destinations',
            'breadcrumbs' => ['Dashboard' => '/dashboard', 'Destinos de integracao' => '/admin/integration-destinations', 'Requisicao recusada' => null],
            'message' => 'Nao foi possivel validar a requisicao.',
        ], 403);
    }

    private function destinationInput(Request $request): array
    {
        return [
            'tenant_id' => $request->input('tenant_id'),
            'name' => $request->input('name'),
            'slug' => $request->input('slug'),
            'type' => $request->input('type'),
            'status' => $request->input('status'),
        ];
    }

    private function destinationFromRequest(Request $request): ?IntegrationDestination
    {
        return $this->service()->find($this->id($request));
    }

    private function id(Request $request): int
    {
        return max(0, (int) ($request->param('id') ?? 0));
    }
}
