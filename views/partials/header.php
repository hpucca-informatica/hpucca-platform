<?php

declare(strict_types=1);
?>
<header class="admin-header">
    <button class="menu-toggle" type="button" data-menu-toggle aria-label="Abrir ou recolher menu">
        <span aria-hidden="true"></span>
        <span aria-hidden="true"></span>
        <span aria-hidden="true"></span>
    </button>
    <div>
        <strong>HPucca Platform</strong>
        <span><?= $e($tenantName) ?></span>
    </div>
    <div class="header-user" aria-label="Usuario autenticado">
        <span><?= $e($userName) ?></span>
    </div>
</header>
