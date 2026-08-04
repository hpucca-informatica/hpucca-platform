<?php

declare(strict_types=1);

$menu = [
    'Principal' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => '/dashboard'],
    ],
    'Cadastros' => [
        ['key' => 'companies', 'label' => 'Empresas', 'href' => '/admin/companies'],
        ['key' => 'users', 'label' => 'Usuarios', 'href' => '/admin/users'],
    ],
    'Sistema' => [
        ['key' => 'profile', 'label' => 'Perfil', 'href' => '/profile'],
        ['key' => 'change-password', 'label' => 'Alterar senha', 'href' => '/change-password'],
    ],
];

$isOwner = false;
if (($user ?? null) instanceof \HPucca\Platform\Models\User) {
    $isOwner = $user->type === 'owner';
} elseif (is_array($user ?? null)) {
    $isOwner = ($user['type'] ?? '') === 'owner';
}

if ($isOwner) {
    $menu['Automacao'] = [
        ['key' => 'integration-sources', 'label' => 'Fontes de integracao', 'href' => '/admin/integration-sources'],
        ['key' => 'events', 'label' => 'Eventos', 'href' => '/admin/events'],
    ];
}
?>
<aside class="admin-sidebar" aria-label="Menu administrativo">
    <a class="skip-link" href="#main-content">Ir para o conteudo</a>
    <div class="sidebar-brand">
        <span>HPucca</span>
        <strong>Platform</strong>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($menu as $group => $items): ?>
            <section class="nav-group" aria-label="<?= $e((string) $group) ?>">
                <h2><?= $e((string) $group) ?></h2>
                <?php foreach ($items as $item): ?>
                    <?php $isActive = $activeMenu === $item['key']; ?>
                    <a
                        href="<?= $e($item['href']) ?>"
                        class="<?= $isActive ? 'is-active' : '' ?>"
                        <?= $isActive ? 'aria-current="page"' : '' ?>
                    >
                        <?= $e($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </nav>
    <form class="sidebar-logout" method="post" action="/logout">
        <?= $csrfField ?? '' ?>
        <button type="submit">Sair</button>
    </form>
</aside>
