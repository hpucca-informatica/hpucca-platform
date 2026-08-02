<?php

declare(strict_types=1);

use HPucca\Platform\Models\Tenant;
use HPucca\Platform\Models\User;

$currentUser = $currentUser instanceof User ? $currentUser : null;
$currentTenant = isset($currentTenant) && $currentTenant instanceof Tenant ? $currentTenant : null;
$values = isset($values) && is_array($values) ? $values : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];
$fieldValue = static fn (string $key): string => is_string($values[$key] ?? null) ? $values[$key] : '';
$fieldError = static fn (string $key): string => is_string($errors[$key] ?? null) ? $errors[$key] : '';
?>
<?php if ($currentUser instanceof User): ?>
    <section class="page-section">
        <div class="section-header">
            <div>
                <p class="eyebrow">Minha conta</p>
                <h2>Perfil</h2>
            </div>
        </div>

        <dl class="details-list">
            <div><dt>Codigo</dt><dd><?= $e($currentUser->code) ?></dd></div>
            <div><dt>Empresa</dt><dd><?= $e($currentTenant?->name ?? '') ?></dd></div>
            <div><dt>Login</dt><dd><?= $e($currentUser->login) ?></dd></div>
            <div><dt>Categoria</dt><dd><?= $e($currentUser->type) ?></dd></div>
            <div><dt>Status</dt><dd><?= $e($currentUser->status) ?></dd></div>
        </dl>

        <form class="entity-form" method="post" action="/profile">
            <?= $csrfField ?? '' ?>
            <div class="form-grid">
                <div class="form-field">
                    <label for="profile-name">Nome</label>
                    <input id="profile-name" name="name" type="text" maxlength="150" required value="<?= $e($fieldValue('name')) ?>">
                    <?php if ($fieldError('name') !== ''): ?>
                        <span class="form-error"><?= $e($fieldError('name')) ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-field">
                    <label for="profile-email">Email</label>
                    <input id="profile-email" name="email" type="email" maxlength="255" value="<?= $e($fieldValue('email')) ?>">
                    <?php if ($fieldError('email') !== ''): ?>
                        <span class="form-error"><?= $e($fieldError('email')) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="form-actions">
                <button class="button-primary" type="submit">Salvar perfil</button>
            </div>
        </form>
    </section>
<?php endif; ?>
