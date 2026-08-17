<?php

declare(strict_types=1);

use HPucca\Platform\Models\IntegrationDestination;
use HPucca\Platform\Models\Tenant;

$destinations = isset($destinations) && is_array($destinations) ? $destinations : [];
$tenants = isset($tenants) && is_array($tenants) ? $tenants : [];
$search = isset($search) && is_string($search) ? $search : '';
$tenantId = isset($tenantId) && is_int($tenantId) ? $tenantId : null;
$page = isset($page) && is_int($page) ? $page : 1;
$pages = isset($pages) && is_int($pages) ? $pages : 1;
$tenantQuery = $tenantId === null ? '' : '&tenant_id=' . rawurlencode((string) $tenantId);
?>
<div class="page-actions">
    <a class="button-primary" href="/admin/integration-destinations/create">Novo destino</a>
</div>

<form class="toolbar-form" method="get" action="/admin/integration-destinations">
    <input name="search" type="search" placeholder="Buscar por codigo, nome, slug, tipo ou empresa" value="<?= $e($search) ?>">
    <select name="tenant_id">
        <option value="">Todas as empresas</option>
        <?php foreach ($tenants as $tenantOption): ?>
            <?php if ($tenantOption instanceof Tenant): ?>
                <option value="<?= $e((string) $tenantOption->id) ?>" <?= $tenantId === $tenantOption->id ? 'selected' : '' ?>>
                    <?= $e($tenantOption->name) ?>
                </option>
            <?php endif; ?>
        <?php endforeach; ?>
    </select>
    <button type="submit">Filtrar</button>
    <a href="/admin/integration-destinations">Limpar</a>
</form>

<?php if ($destinations === []): ?>
    <section class="empty-state">
        <h2>Nenhum destino encontrado.</h2>
        <p>Cadastre um destino de integracao para preparar o processamento externo.</p>
        <a class="button-primary" href="/admin/integration-destinations/create">Criar primeiro destino</a>
    </section>
<?php else: ?>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Nome</th>
                    <th>Empresa</th>
                    <th>Slug</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th>Criacao</th>
                    <th>Atualizacao</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($destinations as $listedDestination): ?>
                    <?php if ($listedDestination instanceof IntegrationDestination): ?>
                        <tr>
                            <td><?= $copyableText($listedDestination->code, 'Copiar', 'Codigo copiado', 'code-copy') ?></td>
                            <td><?= $e($listedDestination->name) ?></td>
                            <td><?= $e($listedDestination->tenantName) ?></td>
                            <td><code><?= $e($listedDestination->slug) ?></code></td>
                            <td><?= $e($listedDestination->type) ?></td>
                            <td><?= $statusBadge($listedDestination->status) ?></td>
                            <td><?= $e($formatDate($listedDestination->createdAt)) ?></td>
                            <td><?= $e($formatDate($listedDestination->updatedAt)) ?></td>
                            <td class="table-actions">
                                <a href="/admin/integration-destinations/<?= $e((string) $listedDestination->id) ?>">Ver</a>
                                <a href="/admin/integration-destinations/<?= $e((string) $listedDestination->id) ?>/edit">Editar</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<nav class="pagination" aria-label="Paginacao">
    <?php if ($page > 1): ?>
        <a href="/admin/integration-destinations?page=<?= $e((string) ($page - 1)) ?>&search=<?= $e(rawurlencode($search)) ?><?= $e($tenantQuery) ?>">Anterior</a>
    <?php endif; ?>
    <span>Pagina <?= $e((string) $page) ?> de <?= $e((string) $pages) ?></span>
    <?php if ($page < $pages): ?>
        <a href="/admin/integration-destinations?page=<?= $e((string) ($page + 1)) ?>&search=<?= $e(rawurlencode($search)) ?><?= $e($tenantQuery) ?>">Proxima</a>
    <?php endif; ?>
</nav>
