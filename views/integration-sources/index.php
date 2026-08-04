<?php

declare(strict_types=1);

use HPucca\Platform\Models\IntegrationSource;
use HPucca\Platform\Models\Tenant;

$sources = isset($sources) && is_array($sources) ? $sources : [];
$tenants = isset($tenants) && is_array($tenants) ? $tenants : [];
$search = isset($search) && is_string($search) ? $search : '';
$tenantId = isset($tenantId) && is_int($tenantId) ? $tenantId : null;
$page = isset($page) && is_int($page) ? $page : 1;
$pages = isset($pages) && is_int($pages) ? $pages : 1;
$tenantQuery = $tenantId === null ? '' : '&tenant_id=' . rawurlencode((string) $tenantId);
?>
<div class="page-actions">
    <a class="button-primary" href="/admin/integration-sources/create">Nova fonte</a>
</div>

<form class="toolbar-form" method="get" action="/admin/integration-sources">
    <input name="search" type="search" placeholder="Buscar por codigo, nome, slug ou empresa" value="<?= $e($search) ?>">
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
    <a href="/admin/integration-sources">Limpar</a>
</form>

<?php if ($sources === []): ?>
    <section class="empty-state">
        <h2>Nenhuma fonte encontrada.</h2>
        <p>Cadastre uma fonte de integracao para receber eventos de sistemas externos.</p>
        <a class="button-primary" href="/admin/integration-sources/create">Criar primeira fonte</a>
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
                    <th>Status</th>
                    <th>Ultimo uso</th>
                    <th>Criacao</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sources as $listedSource): ?>
                    <?php if ($listedSource instanceof IntegrationSource): ?>
                        <tr>
                            <td><?= $copyableText($listedSource->code, 'Copiar', 'Codigo copiado', 'code-copy') ?></td>
                            <td><?= $e($listedSource->name) ?></td>
                            <td><?= $e($listedSource->tenantName) ?></td>
                            <td><code><?= $e($listedSource->slug) ?></code></td>
                            <td><?= $statusBadge($listedSource->status) ?></td>
                            <td><?= $e($formatDate($listedSource->lastUsedAt)) ?></td>
                            <td><?= $e($formatDate($listedSource->createdAt)) ?></td>
                            <td class="table-actions">
                                <a href="/admin/integration-sources/<?= $e((string) $listedSource->id) ?>">Ver</a>
                                <a href="/admin/integration-sources/<?= $e((string) $listedSource->id) ?>/edit">Editar</a>
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
        <a href="/admin/integration-sources?page=<?= $e((string) ($page - 1)) ?>&search=<?= $e(rawurlencode($search)) ?><?= $e($tenantQuery) ?>">Anterior</a>
    <?php endif; ?>
    <span>Pagina <?= $e((string) $page) ?> de <?= $e((string) $pages) ?></span>
    <?php if ($page < $pages): ?>
        <a href="/admin/integration-sources?page=<?= $e((string) ($page + 1)) ?>&search=<?= $e(rawurlencode($search)) ?><?= $e($tenantQuery) ?>">Proxima</a>
    <?php endif; ?>
</nav>
