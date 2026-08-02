<?php

declare(strict_types=1);

use HPucca\Platform\Models\Tenant;

$companies = isset($companies) && is_array($companies) ? $companies : [];
$search = isset($search) && is_string($search) ? $search : '';
$total = isset($total) && is_int($total) ? $total : 0;
$page = isset($page) && is_int($page) ? $page : 1;
$pages = isset($pages) && is_int($pages) ? $pages : 1;
?>
<section class="page-section">
    <div class="section-header">
        <div>
            <p class="eyebrow">Cadastros</p>
            <h2>Empresas</h2>
        </div>
        <a class="button-primary" href="/admin/companies/create">Nova empresa</a>
    </div>

    <form class="toolbar-form" method="get" action="/admin/companies">
        <label for="company-search">Buscar</label>
        <input
            id="company-search"
            name="search"
            type="search"
            value="<?= $e($search) ?>"
            placeholder="Nome, slug ou codigo"
        >
        <button type="submit">Filtrar</button>
        <?php if ($search !== ''): ?>
            <a href="/admin/companies">Limpar</a>
        <?php endif; ?>
    </form>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Codigo</th>
                    <th scope="col">Nome</th>
                    <th scope="col">Slug</th>
                    <th scope="col">Status</th>
                    <th scope="col">Criada em</th>
                    <th scope="col">Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($companies as $company): ?>
                    <?php if ($company instanceof Tenant): ?>
                        <tr>
                            <td><?= $e($company->code) ?></td>
                            <td><?= $e($company->name) ?></td>
                            <td><?= $e($company->slug) ?></td>
                            <td><span class="status-pill status-<?= $e($company->status) ?>"><?= $e($company->status) ?></span></td>
                            <td><?= $e($company->createdAt) ?></td>
                            <td class="table-actions">
                                <a href="/admin/companies/<?= $e((string) $company->id) ?>">Ver</a>
                                <a href="/admin/companies/<?= $e((string) $company->id) ?>/edit">Editar</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($companies === []): ?>
                    <tr>
                        <td colspan="6">Nenhuma empresa encontrada.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <nav class="pagination" aria-label="Paginacao">
        <span><?= $e((string) $total) ?> registro(s)</span>
        <?php if ($page > 1): ?>
            <a href="/admin/companies?page=<?= $e((string) ($page - 1)) ?>&search=<?= $e(rawurlencode($search)) ?>">Anterior</a>
        <?php endif; ?>
        <span>Pagina <?= $e((string) $page) ?> de <?= $e((string) $pages) ?></span>
        <?php if ($page < $pages): ?>
            <a href="/admin/companies?page=<?= $e((string) ($page + 1)) ?>&search=<?= $e(rawurlencode($search)) ?>">Proxima</a>
        <?php endif; ?>
    </nav>
</section>
