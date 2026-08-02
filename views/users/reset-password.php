<?php

declare(strict_types=1);

use HPucca\Platform\Models\User;

$managedUser = $managedUser instanceof User ? $managedUser : null;
$errors = isset($errors) && is_array($errors) ? $errors : [];
$fieldError = static fn (string $key): string => is_string($errors[$key] ?? null) ? $errors[$key] : '';
?>
<?php if ($managedUser instanceof User): ?>
    <section class="page-section">
        <div class="section-header">
            <div>
                <p class="eyebrow">Senha</p>
                <h2><?= $e($managedUser->name) ?></h2>
            </div>
        </div>

        <?php if ($fieldError('general') !== ''): ?>
            <p class="form-error"><?= $e($fieldError('general')) ?></p>
        <?php endif; ?>

        <form class="entity-form" method="post" action="/admin/users/<?= $e((string) $managedUser->id) ?>/reset-password">
            <?= $csrfField ?? '' ?>
            <div class="form-grid">
                <div class="form-field">
                    <label for="reset-password">Nova senha</label>
                    <input id="reset-password" name="password" type="password" minlength="10" autocomplete="new-password" required>
                    <?php if ($fieldError('password') !== ''): ?>
                        <span class="form-error"><?= $e($fieldError('password')) ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-field">
                    <label for="reset-password-confirmation">Confirmar nova senha</label>
                    <input id="reset-password-confirmation" name="password_confirmation" type="password" minlength="10" autocomplete="new-password" required>
                    <?php if ($fieldError('password_confirmation') !== ''): ?>
                        <span class="form-error"><?= $e($fieldError('password_confirmation')) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="form-actions">
                <button class="button-primary" type="submit">Redefinir senha</button>
                <a href="/admin/users/<?= $e((string) $managedUser->id) ?>">Cancelar</a>
            </div>
        </form>
    </section>
<?php endif; ?>
