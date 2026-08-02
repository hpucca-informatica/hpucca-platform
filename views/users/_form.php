<?php

declare(strict_types=1);

use HPucca\Platform\Models\Tenant;

$values = isset($values) && is_array($values) ? $values : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];
$tenants = isset($tenants) && is_array($tenants) ? $tenants : [];
$action = isset($action) && is_string($action) ? $action : '/admin/users';
$submitLabel = isset($submitLabel) && is_string($submitLabel) ? $submitLabel : 'Salvar';
$readonlyCode = isset($readonlyCode) && is_string($readonlyCode) ? $readonlyCode : '';
$includePassword = isset($includePassword) && is_bool($includePassword) ? $includePassword : false;

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
            <label for="user-tenant">Empresa</label>
            <select id="user-tenant" name="tenant_id" required>
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
            <label for="user-name">Nome</label>
            <input id="user-name" name="name" type="text" maxlength="150" required value="<?= $e($fieldValue('name')) ?>">
            <?php if ($fieldError('name') !== ''): ?>
                <span class="form-error"><?= $e($fieldError('name')) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="user-login">Login</label>
            <input id="user-login" name="login" type="text" maxlength="60" required value="<?= $e($fieldValue('login')) ?>">
            <?php if ($fieldError('login') !== ''): ?>
                <span class="form-error"><?= $e($fieldError('login')) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="user-email">Email</label>
            <input id="user-email" name="email" type="email" maxlength="255" value="<?= $e($fieldValue('email')) ?>">
            <?php if ($fieldError('email') !== ''): ?>
                <span class="form-error"><?= $e($fieldError('email')) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="user-type">Categoria</label>
            <select id="user-type" name="type">
                <?php foreach (['owner', 'admin', 'manager', 'user'] as $type): ?>
                    <option value="<?= $e($type) ?>" <?= $fieldValue('type') === $type ? 'selected' : '' ?>><?= $e($type) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($fieldError('type') !== ''): ?>
                <span class="form-error"><?= $e($fieldError('type')) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="user-status">Status</label>
            <select id="user-status" name="status">
                <option value="active" <?= $fieldValue('status') === 'active' ? 'selected' : '' ?>>Ativo</option>
                <option value="inactive" <?= $fieldValue('status') === 'inactive' ? 'selected' : '' ?>>Inativo</option>
            </select>
            <?php if ($fieldError('status') !== ''): ?>
                <span class="form-error"><?= $e($fieldError('status')) ?></span>
            <?php endif; ?>
        </div>

        <?php if ($includePassword): ?>
            <div class="form-field">
                <label for="user-password">Senha</label>
                <input id="user-password" name="password" type="password" minlength="10" autocomplete="new-password" required>
                <?php if ($fieldError('password') !== ''): ?>
                    <span class="form-error"><?= $e($fieldError('password')) ?></span>
                <?php endif; ?>
            </div>
            <div class="form-field">
                <label for="user-password-confirmation">Confirmar senha</label>
                <input id="user-password-confirmation" name="password_confirmation" type="password" minlength="10" autocomplete="new-password" required>
                <?php if ($fieldError('password_confirmation') !== ''): ?>
                    <span class="form-error"><?= $e($fieldError('password_confirmation')) ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="form-actions">
        <button class="button-primary" type="submit"><?= $e($submitLabel) ?></button>
        <a href="/admin/users">Cancelar</a>
    </div>
</form>
