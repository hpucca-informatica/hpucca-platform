<?php

declare(strict_types=1);

namespace HPucca\Platform\Controllers;

use HPucca\Platform\Core\Response;

final class HealthController
{
    public function show(): Response
    {
        return Response::json([
            'status' => 'ok',
            'version' => '0.1.0',
        ]);
    }
}
