<?php

declare(strict_types=1);

use HPucca\Platform\Models\Event;
use HPucca\Platform\Models\IntegrationSource;
use HPucca\Platform\Models\Tenant;

$events = isset($events) && is_array($events) ? $events : [];
$tenants = isset($tenants) && is_array($tenants) ? $tenants : [];
$sources = isset($sources) && is_array($sources) ? $sources : [];
$filters = isset($filters) && is_array($filters) ? $filters : [];
$page = isset($page) && is_int($page) ? $page : 1;
$pages = isset($pages) && is_int($pages) ? $pages : 1;
$filterValue = static fn (string $key): string => is_string($filters[$key] ?? null) ? $filters[$key] : '';
$query = http_build_query(array_filter($filters, static fn (string $value): bool => $value !== ''));
?>
<form class="toolbar-form" method="get" action="/admin/events">
    <input name="search" type="search" placeholder="Codigo ou external_id" value="<?= $e($filterValue('search')) ?>">
    <select name="tenant_id">
        <option value="">Todas as empresas</option>
        <?php foreach ($tenants as $tenantOption): ?>
            <?php if ($tenantOption instanceof Tenant): ?>
                <option value="<?= $e((string) $tenantOption->id) ?>" <?= $filterValue('tenant_id') === (string) $tenantOption->id ? 'selected' : '' ?>>
                    <?= $e($tenantOption->name) ?>
                </option>
            <?php endif; ?>
        <?php endforeach; ?>
    </select>
    <select name="source_id">
        <option value="">Todas as fontes</option>
        <?php foreach ($sources as $sourceOption): ?>
            <?php if ($sourceOption instanceof IntegrationSource): ?>
                <option value="<?= $e((string) $sourceOption->id) ?>" <?= $filterValue('source_id') === (string) $sourceOption->id ? 'selected' : '' ?>>
                    <?= $e($sourceOption->name) ?>
                </option>
            <?php endif; ?>
        <?php endforeach; ?>
    </select>
    <input name="event_type" type="text" placeholder="Tipo" value="<?= $e($filterValue('event_type')) ?>">
    <select name="status">
        <option value="">Todos os status</option>
        <?php foreach (['pending', 'processing', 'processed', 'failed'] as $status): ?>
            <option value="<?= $e($status) ?>" <?= $filterValue('status') === $status ? 'selected' : '' ?>><?= $e($status) ?></option>
        <?php endforeach; ?>
    </select>
    <input name="received_from" type="date" value="<?= $e($filterValue('received_from')) ?>">
    <input name="received_to" type="date" value="<?= $e($filterValue('received_to')) ?>">
    <button type="submit">Filtrar</button>
    <a href="/admin/events">Limpar</a>
</form>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Codigo</th>
                <th>Empresa</th>
                <th>Fonte</th>
                <th>Tipo</th>
                <th>External ID</th>
                <th>Status</th>
                <th>Tentativas</th>
                <th>Recebido em</th>
                <th>Acoes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($events as $listedEvent): ?>
                <?php if ($listedEvent instanceof Event): ?>
                    <tr>
                        <td><?= $e($listedEvent->code) ?></td>
                        <td><?= $e($listedEvent->tenantName) ?></td>
                        <td><?= $e($listedEvent->integrationSourceName) ?></td>
                        <td><?= $e($listedEvent->eventType) ?></td>
                        <td><?= $e($listedEvent->externalId) ?></td>
                        <td><?= $e($listedEvent->status) ?></td>
                        <td><?= $e((string) $listedEvent->attempts) ?></td>
                        <td><?= $e($listedEvent->receivedAt) ?></td>
                        <td><a href="/admin/events/<?= $e((string) $listedEvent->id) ?>">Ver</a></td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($events === []): ?>
                <tr>
                    <td colspan="9">Nenhum evento encontrado.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<nav class="pagination" aria-label="Paginacao">
    <?php if ($page > 1): ?>
        <a href="/admin/events?page=<?= $e((string) ($page - 1)) ?><?= $query === '' ? '' : '&' . $e($query) ?>">Anterior</a>
    <?php endif; ?>
    <span>Pagina <?= $e((string) $page) ?> de <?= $e((string) $pages) ?></span>
    <?php if ($page < $pages): ?>
        <a href="/admin/events?page=<?= $e((string) ($page + 1)) ?><?= $query === '' ? '' : '&' . $e($query) ?>">Proxima</a>
    <?php endif; ?>
</nav>
