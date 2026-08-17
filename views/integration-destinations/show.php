<?php

declare(strict_types=1);

use HPucca\Platform\Models\IntegrationDestination;
use HPucca\Platform\Services\N8nDestinationConfig;

$destination = $destination instanceof IntegrationDestination ? $destination : null;
$config = [];
$endpoint = '-';
$timeout = '-';

if ($destination instanceof IntegrationDestination) {
    $decodedConfig = json_decode($destination->config, true);
    $config = is_array($decodedConfig) ? $decodedConfig : [];
    $endpoint = N8nDestinationConfig::maskedEndpoint((string) ($config['webhook_url'] ?? ''));
    $timeout = (string) ($config['timeout_seconds'] ?? '10');
}
?>
<?php if ($destination instanceof IntegrationDestination): ?>
    <section class="entity-details">
        <dl>
            <dt>Codigo</dt>
            <dd><?= $copyableText($destination->code, 'Copiar', 'Codigo copiado', 'code-copy') ?></dd>
            <dt>Nome</dt>
            <dd><?= $e($destination->name) ?></dd>
            <dt>Empresa</dt>
            <dd><?= $e($destination->tenantName) ?></dd>
            <dt>Slug</dt>
            <dd><?= $e($destination->slug) ?></dd>
            <dt>Tipo</dt>
            <dd><?= $e($destination->type) ?></dd>
            <dt>Status</dt>
            <dd><?= $statusBadge($destination->status) ?></dd>
            <dt>Timeout</dt>
            <dd><?= $e($timeout) ?>s</dd>
            <dt>Endpoint</dt>
            <dd><code><?= $e($endpoint) ?></code></dd>
            <dt>Criacao</dt>
            <dd><?= $e($formatDate($destination->createdAt)) ?></dd>
            <dt>Atualizacao</dt>
            <dd><?= $e($formatDate($destination->updatedAt)) ?></dd>
        </dl>
    </section>

    <div class="form-actions">
        <a class="button-primary" href="/admin/integration-destinations/<?= $e((string) $destination->id) ?>/edit">Editar</a>
        <?php if ($destination->status === 'active'): ?>
            <form method="post" action="/admin/integration-destinations/<?= $e((string) $destination->id) ?>/deactivate">
                <?= $csrfField ?? '' ?>
                <button type="submit">Inativar</button>
            </form>
        <?php else: ?>
            <form method="post" action="/admin/integration-destinations/<?= $e((string) $destination->id) ?>/activate">
                <?= $csrfField ?? '' ?>
                <button type="submit">Ativar</button>
            </form>
        <?php endif; ?>
        <a href="/admin/integration-destinations">Voltar</a>
    </div>
<?php endif; ?>
