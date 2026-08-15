<?php

declare(strict_types=1);

use HPucca\Platform\Models\IntegrationSource;
use HPucca\Platform\Models\Tenant;

$filters = isset($filters) && is_array($filters) ? $filters : [];
$summary = isset($summary) && is_array($summary) ? $summary : [];
$dailySeries = isset($dailySeries) && is_array($dailySeries) ? $dailySeries : [];
$recentFailures = isset($recentFailures) && is_array($recentFailures) ? $recentFailures : [];
$scheduledRetries = isset($scheduledRetries) && is_array($scheduledRetries) ? $scheduledRetries : [];
$processingEvents = isset($processingEvents) && is_array($processingEvents) ? $processingEvents : [];
$topAttempts = isset($topAttempts) && is_array($topAttempts) ? $topAttempts : [];
$tenants = isset($tenants) && is_array($tenants) ? $tenants : [];
$sources = isset($sources) && is_array($sources) ? $sources : [];
$periodOptions = isset($periodOptions) && is_array($periodOptions) ? $periodOptions : [];
$statusOptions = isset($statusOptions) && is_array($statusOptions) ? $statusOptions : [];
$timezone = is_string($timezone ?? null) ? $timezone : '';
$processingTimeoutMinutes = is_int($processingTimeoutMinutes ?? null) ? $processingTimeoutMinutes : 15;
$filterValue = static fn (string $key): string => is_string($filters[$key] ?? null) ? $filters[$key] : '';
$number = static fn (string $key): string => number_format((int) ($summary[$key] ?? 0), 0, ',', '.');
$successRate = $summary['success_rate'] ?? null;
$maxSeries = 1;
foreach ($dailySeries as $point) {
    if (is_array($point)) {
        $maxSeries = max($maxSeries, (int) ($point['received'] ?? 0), (int) ($point['processed'] ?? 0), (int) ($point['failed'] ?? 0));
    }
}
$barHeight = static fn (int $value): string => (string) max(4, min(100, (int) round(($value / $maxSeries) * 100)));
?>
<form class="toolbar-form automation-filters" method="get" action="/admin/automation">
    <label>
        Periodo
        <select name="period">
            <?php foreach ($periodOptions as $periodKey => $periodLabel): ?>
                <option value="<?= $e((string) $periodKey) ?>" <?= $filterValue('period') === (string) $periodKey ? 'selected' : '' ?>>
                    <?= $e((string) $periodLabel) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        De
        <input name="date_from" type="date" value="<?= $e($filterValue('date_from')) ?>">
    </label>
    <label>
        Ate
        <input name="date_to" type="date" value="<?= $e($filterValue('date_to')) ?>">
    </label>
    <label>
        Empresa
        <select name="tenant_id">
            <option value="">Todas</option>
            <?php foreach ($tenants as $tenantOption): ?>
                <?php if ($tenantOption instanceof Tenant): ?>
                    <option value="<?= $e((string) $tenantOption->id) ?>" <?= $filterValue('tenant_id') === (string) $tenantOption->id ? 'selected' : '' ?>>
                        <?= $e($tenantOption->name) ?>
                    </option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        Fonte
        <select name="source_id">
            <option value="">Todas</option>
            <?php foreach ($sources as $sourceOption): ?>
                <?php if ($sourceOption instanceof IntegrationSource): ?>
                    <option value="<?= $e((string) $sourceOption->id) ?>" <?= $filterValue('source_id') === (string) $sourceOption->id ? 'selected' : '' ?>>
                        <?= $e($sourceOption->name) ?>
                    </option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        Tipo
        <input name="event_type" type="text" placeholder="lead.created" value="<?= $e($filterValue('event_type')) ?>">
    </label>
    <label>
        Status
        <select name="status">
            <option value="">Todos</option>
            <?php foreach ($statusOptions as $statusOption): ?>
                <option value="<?= $e((string) $statusOption) ?>" <?= $filterValue('status') === (string) $statusOption ? 'selected' : '' ?>>
                    <?= $e((string) $statusOption) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit">Filtrar</button>
    <a href="/admin/automation">Limpar</a>
</form>

<section class="stats-grid automation-stats" aria-label="Resumo operacional">
    <article class="stat-card"><span>Eventos recebidos</span><strong><?= $e($number('received')) ?></strong></article>
    <article class="stat-card"><span>Processados</span><strong><?= $e($number('processed')) ?></strong></article>
    <article class="stat-card"><span>Falhos</span><strong><?= $e($number('failed')) ?></strong></article>
    <article class="stat-card"><span>Pendentes</span><strong><?= $e($number('pending')) ?></strong></article>
    <article class="stat-card"><span>Em processing</span><strong><?= $e($number('processing')) ?></strong></article>
    <article class="stat-card"><span>Retry agendado</span><strong><?= $e($number('scheduled_retry')) ?></strong><small>Incluido em Pendentes.</small></article>
    <article class="stat-card"><span>Taxa de sucesso</span><strong><?= $successRate === null ? '-' : $e(number_format((float) $successRate, 1, ',', '.') . '%') ?></strong></article>
    <article class="stat-card"><span>Attempts medio finalizados</span><strong><?= $summary['avg_attempts_finalized'] === null ? '-' : $e(number_format((float) $summary['avg_attempts_finalized'], 2, ',', '.')) ?></strong></article>
</section>

<section class="page-section">
    <div class="section-header">
        <div>
            <p class="eyebrow">received_at em <?= $e($timezone) ?></p>
            <h2>Eventos por dia</h2>
        </div>
    </div>
    <?php if ($dailySeries === []): ?>
        <section class="empty-state"><h2>Nenhum evento no periodo.</h2><p>Os dados aparecem quando eventos chegam ao pipeline.</p></section>
    <?php else: ?>
        <div class="automation-chart" role="img" aria-label="Eventos recebidos, processados e falhos por dia">
            <?php foreach ($dailySeries as $point): ?>
                <?php if (is_array($point)): ?>
                    <div class="chart-day">
                        <div class="chart-bars">
                            <span class="bar received" style="height: <?= $e($barHeight((int) ($point['received'] ?? 0))) ?>%"></span>
                            <span class="bar processed" style="height: <?= $e($barHeight((int) ($point['processed'] ?? 0))) ?>%"></span>
                            <span class="bar failed" style="height: <?= $e($barHeight((int) ($point['failed'] ?? 0))) ?>%"></span>
                        </div>
                        <strong><?= $e((string) ($point['day'] ?? '')) ?></strong>
                        <span><?= $e((string) ($point['received'] ?? 0)) ?> / <?= $e((string) ($point['processed'] ?? 0)) ?> / <?= $e((string) ($point['failed'] ?? 0)) ?></span>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <p class="chart-legend"><span class="received"></span>Recebidos <span class="processed"></span>Processados <span class="failed"></span>Falhos</p>
    <?php endif; ?>
</section>

<?php
$renderRows = static function (array $rows, array $columns, string $emptyTitle, string $emptyText) use ($e, $formatDate, $statusBadge): void {
    if ($rows === []) {
        ?>
        <section class="empty-state"><h2><?= $e($emptyTitle) ?></h2><p><?= $e($emptyText) ?></p></section>
        <?php
        return;
    }
    ?>
    <div class="table-wrapper">
        <table class="data-table automation-table">
            <thead><tr><?php foreach ($columns as $label => $_): ?><th><?= $e((string) $label) ?></th><?php endforeach; ?><th>Acoes</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php if (is_array($row)): ?>
                        <tr>
                            <?php foreach ($columns as $key): ?>
                                <td>
                                    <?php if ($key === 'status'): ?>
                                        <?= $statusBadge((string) ($row[$key] ?? '')) ?>
                                    <?php elseif (in_array($key, ['failed_at', 'available_at', 'updated_at'], true)): ?>
                                        <?= $e($formatDate(is_string($row[$key] ?? null) ? $row[$key] : null)) ?>
                                    <?php elseif ($key === 'event_type'): ?>
                                        <code><?= $e((string) ($row[$key] ?? '')) ?></code>
                                    <?php elseif ($key === 'processing_state'): ?>
                                        <?= ((string) ($row[$key] ?? '')) === 'stale' ? '<span class="status-badge status-failed"><span aria-hidden="true"></span>Stale</span>' : '<span class="status-badge status-processing"><span aria-hidden="true"></span>Recente</span>' ?>
                                    <?php else: ?>
                                        <?= $e((string) ($row[$key] ?? '-')) ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td><a href="/admin/events/<?= $e((string) ($row['id'] ?? '')) ?>">Ver</a></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
};
?>

<section class="page-section">
    <div class="section-header"><h2>Ultimas falhas</h2></div>
    <?php $renderRows($recentFailures, [
        'Codigo' => 'code',
        'Empresa' => 'tenant_name',
        'Fonte' => 'integration_source_name',
        'Tipo' => 'event_type',
        'External ID' => 'external_id',
        'Tentativas' => 'attempts',
        'Falhou em' => 'failed_at',
        'Ultimo erro' => 'last_error',
    ], 'Nenhuma falha no periodo.', 'Quando eventos falharem, os mais recentes aparecerao aqui.'); ?>
</section>

<section class="page-section">
    <div class="section-header"><h2>Proximos retries</h2></div>
    <?php $renderRows($scheduledRetries, [
        'Codigo' => 'code',
        'Empresa' => 'tenant_name',
        'Fonte' => 'integration_source_name',
        'Tipo' => 'event_type',
        'Tentativas' => 'attempts',
        'Disponivel em' => 'available_at',
    ], 'Nenhum evento aguardando retry.', 'Nao ha eventos pendentes com nova tentativa futura.'); ?>
</section>

<section class="page-section">
    <div class="section-header"><h2>Em processamento</h2><p>Timeout: <?= $e((string) $processingTimeoutMinutes) ?> minutos</p></div>
    <?php $renderRows($processingEvents, [
        'Codigo' => 'code',
        'Empresa' => 'tenant_name',
        'Fonte' => 'integration_source_name',
        'Tentativas' => 'attempts',
        'Atualizado em' => 'updated_at',
        'Idade aprox. min' => 'processing_age_minutes',
        'Estado' => 'processing_state',
    ], 'Nenhum evento em processing.', 'Nao ha eventos reservados pelo Dispatcher neste momento.'); ?>
</section>

<section class="page-section">
    <div class="section-header"><h2>Eventos com mais tentativas</h2></div>
    <?php $renderRows($topAttempts, [
        'Codigo' => 'code',
        'Status' => 'status',
        'Tentativas' => 'attempts',
        'Empresa' => 'tenant_name',
        'Fonte' => 'integration_source_name',
        'Tipo' => 'event_type',
    ], 'Nenhum evento com tentativas acumuladas.', 'Eventos com attempts maior que zero aparecerao aqui.'); ?>
</section>

<section class="notice-card automation-notes">
    <h2>Definicoes</h2>
    <p>Periodo e graficos usam received_at; retries usam status, attempts e available_at; stale processing usa updated_at e o timeout configurado.</p>
</section>
