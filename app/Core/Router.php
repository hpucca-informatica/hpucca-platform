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

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch(Request $request): Response
    {
        $handler = $this->routes[$request->method()][$request->path()] ?? null;

        if ($handler !== null) {
            return $handler($request);
        }

        foreach ($this->routes[$request->method()] ?? [] as $path => $routeHandler) {
            $params = $this->match($path, $request->path());

            if ($params !== null) {
                return $routeHandler($request->withParams($params));
            }
        }

        return Response::json([
            'error' => 'Not Found',
        ], 404);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $this->routes[$method][$path] = $handler;
    }

    /**
     * @return array<string, string>|null
     */
    private function match(string $routePath, string $requestPath): ?array
    {
        $routeSegments = explode('/', trim($routePath, '/'));
        $requestSegments = explode('/', trim($requestPath, '/'));

        if (count($routeSegments) !== count($requestSegments)) {
            return null;
        }

        $params = [];

        foreach ($routeSegments as $index => $routeSegment) {
            $requestSegment = $requestSegments[$index] ?? '';

            if (str_starts_with($routeSegment, '{') && str_ends_with($routeSegment, '}')) {
                $params[trim($routeSegment, '{}')] = $requestSegment;
                continue;
            }

            if ($routeSegment !== $requestSegment) {
                return null;
            }
        }

        return $params;
    }
}
