<?php

declare(strict_types=1);

$error = isset($error) && is_string($error) ? $error : '';
$e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - HPucca Platform</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f6f8; color: #17202a; }
        main { max-width: 360px; margin: 12vh auto; padding: 24px; background: #ffffff; border: 1px solid #d9e2ec; }
        label { display: block; margin: 16px 0 6px; font-weight: 700; }
        input { box-sizing: border-box; width: 100%; padding: 10px; border: 1px solid #b8c4d0; }
        button { margin-top: 20px; width: 100%; padding: 10px; border: 0; background: #12355b; color: #ffffff; font-weight: 700; }
        .error { padding: 10px; background: #fde8e8; color: #8a1f1f; }
    </style>
</head>
<body>
    <main>
        <h1>HPucca Platform</h1>
        <?php if ($error !== ''): ?>
            <p class="error"><?= $e($error) ?></p>
        <?php endif; ?>
        <form method="post" action="/login">
            <label for="tenant">Empresa</label>
            <input id="tenant" name="tenant" type="text" autocomplete="organization" required>

            <label for="login">Usuario</label>
            <input id="login" name="login" type="text" autocomplete="username" required>

            <label for="password">Senha</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>

            <button type="submit">Entrar</button>
        </form>
    </main>
</body>
</html>
