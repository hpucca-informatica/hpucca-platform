<?php

declare(strict_types=1);

use HPucca\Platform\Models\Tenant;
use HPucca\Platform\Models\User;

$user = $user instanceof User ? $user : null;
$tenant = $tenant instanceof Tenant ? $tenant : null;
$version = isset($version) && is_string($version) ? $version : '';
$databaseStatus = isset($databaseStatus) && is_string($databaseStatus) ? $databaseStatus : 'unavailable';
?>
<section class="page-section">
    <div class="section-header">
        <div>
            <p class="eyebrow">Visao geral</p>
            <h2>Dashboard</h2>
        </div>
    </div>

    <div class="stats-grid" aria-label="Resumo do usuario e ambiente">
        <article class="stat-card">
            <span>Usuario</span>
            <strong><?= $e($user?->name ?? '') ?></strong>
        </article>
        <article class="stat-card">
            <span>Codigo publico</span>
            <strong><?= $e($user?->code ?? '') ?></strong>
        </article>
        <article class="stat-card">
            <span>Login</span>
            <strong><?= $e($user?->login ?? '') ?></strong>
        </article>
        <article class="stat-card">
            <span>Categoria</span>
            <strong><?= $e($user?->type ?? '') ?></strong>
        </article>
        <article class="stat-card">
            <span>Empresa</span>
            <strong><?= $e($tenant?->name ?? '') ?></strong>
        </article>
        <article class="stat-card">
            <span>Versao</span>
            <strong><?= $e($version) ?></strong>
        </article>
        <article class="stat-card">
            <span>Status do banco</span>
            <strong><?= $e($databaseStatus) ?></strong>
        </article>
    </div>
</section>

<section class="page-section">
    <div class="section-header">
        <div>
            <p class="eyebrow">Acesso rapido</p>
            <h2>Atalhos administrativos</h2>
        </div>
    </div>

    <nav class="quick-links" aria-label="Acesso rapido">
        <a href="/admin/companies">Empresas</a>
        <a href="/admin/users">Usuarios</a>
        <a href="/profile">Perfil</a>
        <a href="/change-password">Alterar senha</a>
    </nav>
</section>
