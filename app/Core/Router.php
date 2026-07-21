<?php

declare(strict_types=1);

namespace HPucca\Platform\Core;

final class Router
{
    /**
     * @var array<string, array<string, callable(Request): Response>>
     */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function dispatch(Request $request): Response
    {
        $handler = $this->routes[$request->method()][$request->path()] ?? null;

        if ($handler === null) {
            return Response::json([
                'error' => 'Not Found',
            ], 404);
        }

        return $handler($request);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $this->routes[$method][$path] = $handler;
    }
}
