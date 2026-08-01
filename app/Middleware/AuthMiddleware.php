<?php

declare(strict_types=1);

namespace HPucca\Platform\Middleware;

use HPucca\Platform\Core\Response;
use HPucca\Platform\Services\AuthService;

final readonly class AuthMiddleware
{
    public function __construct(
        private AuthService $auth,
    ) {
    }

    public function handle(callable $next): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/login');
        }

        return $next();
    }
}
