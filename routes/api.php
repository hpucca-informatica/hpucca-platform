<?php

declare(strict_types=1);

use HPucca\Platform\Controllers\HealthController;
use HPucca\Platform\Core\Request;
use HPucca\Platform\Core\Response;
use HPucca\Platform\Core\Router;

/** @var Router $router */
$router->get('/api/v1/health', static function (Request $request): Response {
    return (new HealthController())->show();
});
