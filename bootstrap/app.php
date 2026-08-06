<?php

declare(strict_types=1);

use Dotenv\Dotenv;

$rootPath = dirname(__DIR__);

if (class_exists(Dotenv::class)) {
    Dotenv::createImmutable($rootPath)->safeLoad();
}

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $https = ($_SERVER['HTTPS'] ?? '') === 'on';
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $https,
    ]);
    session_start();
}
