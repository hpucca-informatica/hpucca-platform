<?php

declare(strict_types=1);

namespace HPucca\Platform\Controllers;

use HPucca\Platform\Core\Config;
use HPucca\Platform\Core\Database;
use HPucca\Platform\Core\Response;

final readonly class HealthController
{
    public function __construct(
        private Database $database,
    ) {
    }

    public function show(): Response
    {
        return Response::json([
            'status' => 'ok',
            'version' => Config::get('app.version'),
            'database' => $this->database->isConnected() ? 'connected' : 'unavailable',
        ]);
    }

    public function database(): Response
    {
        if ($this->database->isConnected()) {
            return Response::json([
                'status' => 'ok',
                'database' => 'connected',
            ]);
        }

        return Response::json([
            'status' => 'error',
            'database' => 'unavailable',
        ], 503);
    }
}
