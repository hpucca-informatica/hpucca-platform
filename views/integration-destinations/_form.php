<?php

declare(strict_types=1);

use HPucca\Platform\Models\Tenant;

$values = isset($values) && is_array($values) ? $values : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];
$tenants = isset($tenants) && is_array($tenants) ? $tenants : [];
$action = isset($action) && is_string($action) ? $action : '/admin/integration-destinations';
$submitLabel = isset($submitLabel) && is_string($submitLabel) ? $submitLabel : 'Salvar';

$fieldValue = static fn (string $key): string => is_string($values[$key] ?? null) ? $values[$key] : '';
$fieldError = static fn (string $key): string => is_string($errors[$key] ?? null) ? $errors[$key] : '';
?>
<?php if ($fieldError('general') !== ''): ?>
    <p class="form-error"><?= $e($fieldError('general')) ?></p>
<?php endif; ?>

<form class="entity-form" method="post" action="<?= $e($action) ?>">
    <?= $csrfField ?? '' ?>
    <div class="form-grid">
        <div class="form-field">
            <label for="destination-tenant">Empresa</label>
            <select id="destination-tenant" name="tenant_id" required>
                <option value="">Selecione</option>
                <?php foreach ($tenants as $tenantOption): ?>
                    <?php if ($tenantOption instanceof Tenant): ?>
                        <option value="<?= $e((string) $tenantOption->id) ?>" <?= $fieldValue('tenant_id') === (string) $tenantOption->id ? 'selected' : '' ?>>
                            <?= $e($tenantOption->name) ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <?php if ($fieldError('tenant_id') !== ''): ?>
                <span class="form-error"><?= $e($fieldError('tenant_id')) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="destination-name">Nome</label>
            <input id="destination-name" name="name" type="text" maxlength="150" required value="<?= $e($fieldValue('name')) ?>" data-slug-source>
            <?php if ($fieldError('name') !== ''): ?>
                <span class="form-error"><?= $e($fieldError('name')) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="destination-slug">Slug</label>
            <input id="destination-slug" name="slug" type="text" maxlength="100" required value="<?= $e($fieldValue('slug')) ?>" data-slug-target>
            <?php if ($fieldError('slug') !== ''): ?>
                <span class="form-error"><?= $e($fieldError('slug')) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="destination-type">Tipo</label>
            <select id="destination-type" name="type">
                <option value="n8n" <?= $fieldValue('type') === 'n8n' ? 'selected' : '' ?>>n8n</option>
            </select>
            <?php if ($fieldError('type') !== ''): ?>
                <span class="form-error"><?= $e($fieldError('type')) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="destination-status">Status</label>
            <select id="destination-status" name="status">
                <option value="active" <?= $fieldValue('status') === 'active' ? 'selected' : '' ?>>Ativo</option>
                <option value="inactive" <?= $fieldValue('status') === 'inactive' ? 'selected' : '' ?>>Inativo</option>
            </select>
            <?php if ($fieldError('status') !== ''): ?>
                <span class="form-error"><?= $e($fieldError('status')) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-actions">
        <button class="button-primary" type="submit"><?= $e($submitLabel) ?></button>
        <a href="/admin/integration-destinations">Cancelar</a>
    </div>
</form>
