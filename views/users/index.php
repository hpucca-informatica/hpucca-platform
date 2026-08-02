<?php

declare(strict_types=1);

use HPucca\Platform\Models\Tenant;

$users = isset($users) && is_array($users) ? $users : [];
$tenants = isset($tenants) && is_array($tenants) ? $tenants : [];
$search = isset($search) && is_string($search) ? $search : '';
$tenantId = isset($tenantId) && is_int($tenantId) ? $tenantId : null;
$total = isset($total) && is_int($total) ? $total : 0;
$page = isset($page) && is_int($page) ? $page : 1;
$pages = isset($pages) && is_int($pages) ? $pages : 1;
$tenantQuery = $tenantId === null ? '' : '&tenant_id=' . rawurlencode((string) $tenantId);
?>
<section class="page-section">
    <div class="section-header">
        <div>
            <p class="eyebrow">Cadastros</p>
            <h2>Usuarios</h2>
        </div>
        <a class="button-primary" href="/admin/users/create">Novo usuario</a>
    </div>

    <form class="toolbar-form" method="get" action="/admin/users">
        <label for="user-search">Buscar</label>
        <input id="user-search" name="search" type="search" value="<?= $e($search) ?>" placeholder="Codigo, nome, login ou email">
        <label for="tenant-filter">Empresa</label>
        <select id="tenant-filter" name="tenant_id">
            <option value="">Todas</option>
            <?php foreach ($tenants as $tenantOption): ?>
                <?php if ($tenantOption instanceof Tenant): ?>
                    <option value="<?= $e((string) $tenantOption->id) ?>" <?= $tenantId === $tenantOption->id ? 'selected' : '' ?>>
                        <?= $e($tenantOption->name) ?>
                    </option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
        <button type="submit">Filtrar</button>
        <?php if ($search !== '' || $tenantId !== null): ?>
            <a href="/admin/users">Limpar</a>
        <?php endif; ?>
    </form>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Codigo</th>
                    <th scope="col">Nome</th>
                    <th scope="col">Login</th>
                    <th scope="col">Empresa</th>
                    <th scope="col">Email</th>
                    <th scope="col">Categoria</th>
                    <th scope="col">Status</th>
                    <th scope="col">Criado em</th>
                    <th scope="col">Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $listedUser): ?>
                    <?php if (is_array($listedUser)): ?>
                        <tr>
                            <td><?= $e((string) ($listedUser['code'] ?? '')) ?></td>
                            <td><?= $e((string) ($listedUser['name'] ?? '')) ?></td>
                            <td><?= $e((string) ($listedUser['login'] ?? '')) ?></td>
                            <td><?= $e((string) ($listedUser['tenant_name'] ?? '')) ?></td>
                            <td><?= $e((string) ($listedUser['email'] ?? '')) ?></td>
                            <td><?= $e((string) ($listedUser['type'] ?? '')) ?></td>
                            <td><span class="status-pill status-<?= $e((string) ($listedUser['status'] ?? '')) ?>"><?= $e((string) ($listedUser['status'] ?? '')) ?></span></td>
                            <td><?= $e((string) ($listedUser['created_at'] ?? '')) ?></td>
                            <td class="table-actions">
                                <a href="/admin/users/<?= $e((string) ($listedUser['id'] ?? '')) ?>">Ver</a>
                                <a href="/admin/users/<?= $e((string) ($listedUser['id'] ?? '')) ?>/edit">Editar</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($users === []): ?>
                    <tr>
                        <td colspan="9">Nenhum usuario encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <nav class="pagination" aria-label="Paginacao">
        <span><?= $e((string) $total) ?> registro(s)</span>
        <?php if ($page > 1): ?>
            <a href="/admin/users?page=<?= $e((string) ($page - 1)) ?>&search=<?= $e(rawurlencode($search)) ?><?= $e($tenantQuery) ?>">Anterior</a>
        <?php endif; ?>
        <span>Pagina <?= $e((string) $page) ?> de <?= $e((string) $pages) ?></span>
        <?php if ($page < $pages): ?>
            <a href="/admin/users?page=<?= $e((string) ($page + 1)) ?>&search=<?= $e(rawurlencode($search)) ?><?= $e($tenantQuery) ?>">Proxima</a>
        <?php endif; ?>
    </nav>
</section>
