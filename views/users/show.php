<?php

declare(strict_types=1);

use HPucca\Platform\Models\Tenant;
use HPucca\Platform\Models\User;

$managedUser = $managedUser instanceof User ? $managedUser : null;
$managedTenant = isset($managedTenant) && $managedTenant instanceof Tenant ? $managedTenant : null;
?>
<?php if ($managedUser instanceof User): ?>
    <section class="page-section">
        <div class="section-header">
            <div>
                <p class="eyebrow">Usuario</p>
                <h2><?= $e($managedUser->name) ?></h2>
            </div>
            <div class="header-actions">
                <a class="button-primary" href="/admin/users/<?= $e((string) $managedUser->id) ?>/edit">Editar</a>
                <a href="/admin/users/<?= $e((string) $managedUser->id) ?>/reset-password">Redefinir senha</a>
            </div>
        </div>

        <dl class="details-list">
            <div><dt>Codigo</dt><dd><?= $e($managedUser->code) ?></dd></div>
            <div><dt>Nome</dt><dd><?= $e($managedUser->name) ?></dd></div>
            <div><dt>Login</dt><dd><?= $e($managedUser->login) ?></dd></div>
            <div><dt>Email</dt><dd><?= $e($managedUser->email ?? '') ?></dd></div>
            <div><dt>Empresa</dt><dd><?= $e($managedTenant?->name ?? '') ?></dd></div>
            <div><dt>Categoria</dt><dd><?= $e($managedUser->type) ?></dd></div>
            <div><dt>Status</dt><dd><span class="status-pill status-<?= $e($managedUser->status) ?>"><?= $e($managedUser->status) ?></span></dd></div>
            <div><dt>Criado em</dt><dd><?= $e($managedUser->createdAt) ?></dd></div>
            <div><dt>Atualizado em</dt><dd><?= $e($managedUser->updatedAt) ?></dd></div>
        </dl>

        <div class="form-actions">
            <?php if ($managedUser->status === 'active'): ?>
                <form method="post" action="/admin/users/<?= $e((string) $managedUser->id) ?>/deactivate">
                    <?= $csrfField ?? '' ?>
                    <button type="submit">Inativar</button>
                </form>
            <?php else: ?>
                <form method="post" action="/admin/users/<?= $e((string) $managedUser->id) ?>/activate">
                    <?= $csrfField ?? '' ?>
                    <button type="submit">Ativar</button>
                </form>
            <?php endif; ?>
            <a href="/admin/users">Voltar</a>
        </div>
    </section>
<?php endif; ?>
