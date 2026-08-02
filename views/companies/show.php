<?php

declare(strict_types=1);

use HPucca\Platform\Models\Tenant;

$company = $company instanceof Tenant ? $company : null;
?>
<?php if ($company instanceof Tenant): ?>
    <section class="page-section">
        <div class="section-header">
            <div>
                <p class="eyebrow">Empresa</p>
                <h2><?= $e($company->name) ?></h2>
            </div>
            <div class="header-actions">
                <a class="button-primary" href="/admin/companies/<?= $e((string) $company->id) ?>/edit">Editar</a>
            </div>
        </div>

        <dl class="details-list">
            <div>
                <dt>Codigo</dt>
                <dd><?= $e($company->code) ?></dd>
            </div>
            <div>
                <dt>Slug</dt>
                <dd><?= $e($company->slug) ?></dd>
            </div>
            <div>
                <dt>Status</dt>
                <dd><span class="status-pill status-<?= $e($company->status) ?>"><?= $e($company->status) ?></span></dd>
            </div>
            <div>
                <dt>Criada em</dt>
                <dd><?= $e($company->createdAt) ?></dd>
            </div>
            <div>
                <dt>Atualizada em</dt>
                <dd><?= $e($company->updatedAt) ?></dd>
            </div>
        </dl>

        <div class="form-actions">
            <?php if ($company->status === 'active'): ?>
                <form method="post" action="/admin/companies/<?= $e((string) $company->id) ?>/deactivate">
                    <?= $csrfField ?? '' ?>
                    <button type="submit">Inativar</button>
                </form>
            <?php else: ?>
                <form method="post" action="/admin/companies/<?= $e((string) $company->id) ?>/activate">
                    <?= $csrfField ?? '' ?>
                    <button type="submit">Ativar</button>
                </form>
            <?php endif; ?>
            <a href="/admin/companies">Voltar</a>
        </div>
    </section>
<?php endif; ?>
