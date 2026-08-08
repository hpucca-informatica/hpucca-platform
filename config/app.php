<?php

declare(strict_types=1);

return [
    'name' => $_ENV['APP_NAME'] ?? 'HPucca Platform',
    'env' => $_ENV['APP_ENV'] ?? 'local',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? true, FILTER_VALIDATE_BOOL),
    'timezone' => $_ENV['APP_TIMEZONE'] ?? date_default_timezone_get(),
    'version' => $_ENV['APP_VERSION'] ?? '0.2.0',
    'event_dispatch_limit_default' => max(1, (int) ($_ENV['EVENT_DISPATCH_LIMIT_DEFAULT'] ?? 10)),
    'event_dispatch_limit_max' => max(1, (int) ($_ENV['EVENT_DISPATCH_LIMIT_MAX'] ?? 100)),
    'event_processing_timeout_minutes' => max(1, (int) ($_ENV['EVENT_PROCESSING_TIMEOUT_MINUTES'] ?? 15)),
];
