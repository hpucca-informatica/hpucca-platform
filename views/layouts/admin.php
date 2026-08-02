<?php

declare(strict_types=1);

use HPucca\Platform\Models\Tenant;
use HPucca\Platform\Models\User;

$title = isset($title) && is_string($title) ? $title : 'Administrativo';
$content = isset($content) && is_string($content) ? $content : '';
$activeMenu = isset($activeMenu) && is_string($activeMenu) ? $activeMenu : '';
$breadcrumbs = isset($breadcrumbs) && is_array($breadcrumbs) ? $breadcrumbs : [];
$flash = isset($flash) && is_array($flash) ? $flash : [];

$userName = '';
$tenantName = '';

if ($user instanceof User) {
    $userName = $user->name;
} elseif (is_array($user ?? null)) {
    $userName = is_string($user['name'] ?? null) ? $user['name'] : '';
}

if ($tenant instanceof Tenant) {
    $tenantName = $tenant->name;
} elseif (is_array($tenant ?? null)) {
    $tenantName = is_string($tenant['name'] ?? null) ? $tenant['name'] : '';
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($title) ?> - HPucca Platform</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <div class="admin-shell" data-admin-shell>
        <?php require dirname(__DIR__) . '/partials/sidebar.php'; ?>
        <div class="admin-main">
            <?php require dirname(__DIR__) . '/partials/header.php'; ?>
            <main class="admin-content" id="main-content" tabindex="-1">
                <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
                <?php if ($breadcrumbs !== []): ?>
                    <nav class="breadcrumbs" aria-label="Breadcrumb">
                        <ol>
                            <?php foreach ($breadcrumbs as $label => $url): ?>
                                <li>
                                    <?php if (is_string($url)): ?>
                                        <a href="<?= $e($url) ?>"><?= $e((string) $label) ?></a>
                                    <?php else: ?>
                                        <span><?= $e((string) $label) ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </nav>
                <?php endif; ?>
                <h1><?= $e($title) ?></h1>
                <?= $content ?>
            </main>
        </div>
    </div>
    <script src="/assets/js/admin.js" defer></script>
</body>
</html>
