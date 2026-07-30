<?php

declare(strict_types=1);

use HPucca\Platform\Core\Request;
use HPucca\Platform\Core\Response;
use HPucca\Platform\Core\Router;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/bootstrap/app.php';

try {
    $request = Request::fromGlobals();
    $router = new Router();

    require dirname(__DIR__) . '/routes/api.php';

    $response = $router->dispatch($request);
} catch (Throwable) {
    $response = Response::json([
        'error' => 'Internal Server Error',
    ], 500);
}

$response->send();
