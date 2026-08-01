<?php

declare(strict_types=1);

use HPucca\Platform\Models\Tenant;
use HPucca\Platform\Models\User;

$e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$user = $user instanceof User ? $user : null;
$tenant = $tenant instanceof Tenant ? $tenant : null;
$version = isset($version) && is_string($version) ? $version : '';
$databaseStatus = isset($databaseStatus) && is_string($databaseStatus) ? $databaseStatus : 'unavailable';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - HPucca Platform</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f6f8; color: #17202a; }
        header { display: flex; justify-content: space-between; align-items: center; padding: 18px 32px; background: #ffffff; border-bottom: 1px solid #d9e2ec; }
        main { max-width: 960px; margin: 32px auto; padding: 0 24px; }
        dl { display: grid; grid-template-columns: 180px 1fr; gap: 12px; padding: 24px; background: #ffffff; border: 1px solid #d9e2ec; }
        dt { font-weight: 700; }
        button { padding: 8px 12px; border: 0; background: #12355b; color: #ffffff; font-weight: 700; }
    </style>
</head>
<body>
    <header>
        <strong>HPucca Platform</strong>
        <form method="post" action="/logout">
            <button type="submit">Sair</button>
        </form>
    </header>
    <main>
        <h1>Dashboard</h1>
        <dl>
            <dt>Usuario</dt>
            <dd><?= $e($user?->name ?? '') ?></dd>
            <dt>Codigo</dt>
            <dd><?= $e($user?->code ?? '') ?></dd>
            <dt>Login</dt>
            <dd><?= $e($user?->login ?? '') ?></dd>
            <dt>Categoria</dt>
            <dd><?= $e($user?->type ?? '') ?></dd>
            <dt>Empresa</dt>
            <dd><?= $e($tenant?->name ?? '') ?></dd>
            <dt>Versao</dt>
            <dd><?= $e($version) ?></dd>
            <dt>Status do banco</dt>
            <dd><?= $e($databaseStatus) ?></dd>
        </dl>
    </main>
</body>
</html>
