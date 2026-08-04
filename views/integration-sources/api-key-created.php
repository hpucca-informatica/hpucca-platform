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
            <dd class="api-key-copy">
                <label for="integration-source-api-key">API key gerada</label>
                <input id="integration-source-api-key" type="text" readonly value="<?= $e($apiKey) ?>" data-api-key-value>
                <button type="button" class="copy-button" data-copy-target="#integration-source-api-key" data-copy-feedback="Copiado!" aria-label="Copiar API Key">Copiar API Key</button>
                <span class="copy-feedback" data-copy-status aria-live="polite"></span>
            </dd>
        </dl>
    </section>
    <div class="form-actions">
        <a class="button-primary" href="/admin/integration-sources/<?= $e((string) $source->id) ?>">Concluir</a>
    </div>
<?php endif; ?>
