<?php

declare(strict_types=1);

$errors = isset($errors) && is_array($errors) ? $errors : [];
$fieldError = static fn (string $key): string => is_string($errors[$key] ?? null) ? $errors[$key] : '';
?>
<section class="page-section">
    <div class="section-header">
        <div>
            <p class="eyebrow">Seguranca</p>
            <h2>Alterar senha</h2>
        </div>
    </div>

    <?php if ($fieldError('general') !== ''): ?>
        <p class="form-error"><?= $e($fieldError('general')) ?></p>
    <?php endif; ?>

    <form class="entity-form" method="post" action="/change-password">
        <?= $csrfField ?? '' ?>
        <div class="form-grid">
            <div class="form-field">
                <label for="current-password">Senha atual</label>
                <input id="current-password" name="current_password" type="password" autocomplete="current-password" required>
                <?php if ($fieldError('current_password') !== ''): ?>
                    <span class="form-error"><?= $e($fieldError('current_password')) ?></span>
                <?php endif; ?>
            </div>
            <div class="form-field">
                <label for="new-password">Nova senha</label>
                <input id="new-password" name="password" type="password" minlength="10" autocomplete="new-password" required>
                <?php if ($fieldError('password') !== ''): ?>
                    <span class="form-error"><?= $e($fieldError('password')) ?></span>
                <?php endif; ?>
            </div>
            <div class="form-field">
                <label for="new-password-confirmation">Confirmar nova senha</label>
                <input id="new-password-confirmation" name="password_confirmation" type="password" minlength="10" autocomplete="new-password" required>
                <?php if ($fieldError('password_confirmation') !== ''): ?>
                    <span class="form-error"><?= $e($fieldError('password_confirmation')) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-actions">
            <button class="button-primary" type="submit">Alterar senha</button>
        </div>
    </form>
</section>
