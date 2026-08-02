<?php

declare(strict_types=1);

$values = isset($values) && is_array($values) ? $values : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];
$action = isset($action) && is_string($action) ? $action : '/admin/companies';
$submitLabel = isset($submitLabel) && is_string($submitLabel) ? $submitLabel : 'Salvar';
$readonlyCode = isset($readonlyCode) && is_string($readonlyCode) ? $readonlyCode : '';

$fieldValue = static fn (string $key): string => is_string($values[$key] ?? null) ? $values[$key] : '';
$fieldError = static fn (string $key): string => is_string($errors[$key] ?? null) ? $errors[$key] : '';
?>
<?php if ($fieldError('general') !== ''): ?>
    <p class="form-error"><?= $e($fieldError('general')) ?></p>
<?php endif; ?>

<form class="entity-form" method="post" action="<?= $e($action) ?>">
    <?= $csrfField ?? '' ?>
    <div class="form-grid">
        <?php if ($readonlyCode !== ''): ?>
            <div class="form-field">
                <span class="readonly-label">Codigo</span>
                <strong class="readonly-value"><?= $e($readonlyCode) ?></strong>
            </div>
        <?php endif; ?>

        <div class="form-field">
            <label for="company-name">Nome</label>
            <input id="company-name" name="name" type="text" maxlength="150" required value="<?= $e($fieldValue('name')) ?>">
            <?php if ($fieldError('name') !== ''): ?>
                <span class="form-error"><?= $e($fieldError('name')) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="company-slug">Slug</label>
            <input id="company-slug" name="slug" type="text" maxlength="150" required value="<?= $e($fieldValue('slug')) ?>">
            <?php if ($fieldError('slug') !== ''): ?>
                <span class="form-error"><?= $e($fieldError('slug')) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="company-status">Status</label>
            <select id="company-status" name="status">
                <option value="active" <?= $fieldValue('status') === 'active' ? 'selected' : '' ?>>Ativa</option>
                <option value="inactive" <?= $fieldValue('status') === 'inactive' ? 'selected' : '' ?>>Inativa</option>
            </select>
            <?php if ($fieldError('status') !== ''): ?>
                <span class="form-error"><?= $e($fieldError('status')) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-actions">
        <button class="button-primary" type="submit"><?= $e($submitLabel) ?></button>
        <a href="/admin/companies">Cancelar</a>
    </div>
</form>
