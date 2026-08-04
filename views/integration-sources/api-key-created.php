<?php

declare(strict_types=1);

use HPucca\Platform\Models\IntegrationSource;

$source = $source instanceof IntegrationSource ? $source : null;
$apiKey = isset($apiKey) && is_string($apiKey) ? $apiKey : '';
?>
<?php if ($source instanceof IntegrationSource && $apiKey !== ''): ?>
    <section class="entity-details">
        <p class="form-error">Copie esta API key agora. Ela nao podera ser consultada novamente.</p>
        <dl>
            <dt>Fonte</dt>
            <dd><?= $e($source->name) ?></dd>
            <dt>Codigo</dt>
            <dd><?= $e($source->code) ?></dd>
            <dt>API key</dt>
            <dd><input type="text" readonly value="<?= $e($apiKey) ?>"></dd>
        </dl>
    </section>
    <div class="form-actions">
        <a class="button-primary" href="/admin/integration-sources/<?= $e((string) $source->id) ?>">Concluir</a>
    </div>
<?php endif; ?>
