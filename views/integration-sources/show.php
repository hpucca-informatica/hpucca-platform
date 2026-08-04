<?php

declare(strict_types=1);

use HPucca\Platform\Models\IntegrationSource;

$source = $source instanceof IntegrationSource ? $source : null;
?>
<?php if ($source instanceof IntegrationSource): ?>
    <section class="entity-details">
        <dl>
            <dt>Codigo</dt>
            <dd><?= $e($source->code) ?></dd>
            <dt>Nome</dt>
            <dd><?= $e($source->name) ?></dd>
            <dt>Empresa</dt>
            <dd><?= $e($source->tenantName) ?></dd>
            <dt>Slug</dt>
            <dd><?= $e($source->slug) ?></dd>
            <dt>Status</dt>
            <dd><?= $e($source->status) ?></dd>
            <dt>Ultimo uso</dt>
            <dd><?= $e($source->lastUsedAt ?? '-') ?></dd>
            <dt>Criacao</dt>
            <dd><?= $e($source->createdAt) ?></dd>
            <dt>Atualizacao</dt>
            <dd><?= $e($source->updatedAt) ?></dd>
        </dl>
    </section>

    <div class="form-actions">
        <a class="button-primary" href="/admin/integration-sources/<?= $e((string) $source->id) ?>/edit">Editar</a>
        <?php if ($source->status === 'active'): ?>
            <form method="post" action="/admin/integration-sources/<?= $e((string) $source->id) ?>/deactivate">
                <?= $csrfField ?? '' ?>
                <button type="submit">Inativar</button>
            </form>
        <?php else: ?>
            <form method="post" action="/admin/integration-sources/<?= $e((string) $source->id) ?>/activate">
                <?= $csrfField ?? '' ?>
                <button type="submit">Ativar</button>
            </form>
        <?php endif; ?>
        <a href="/admin/integration-sources">Voltar</a>
    </div>
<?php endif; ?>
