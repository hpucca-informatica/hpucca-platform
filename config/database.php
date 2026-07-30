<?php

declare(strict_types=1);

return [
    'host' => $_ENV['DB_HOST'] ?? '',
    'port' => (int) ($_ENV['DB_PORT'] ?? 5432),
    'name' => $_ENV['DB_NAME'] ?? '',
    'user' => $_ENV['DB_USER'] ?? '',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'sslmode' => $_ENV['DB_SSLMODE'] ?? 'prefer',
];
