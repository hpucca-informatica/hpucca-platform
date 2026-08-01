<?php

declare(strict_types=1);

namespace HPucca\Platform\Core;

final readonly class Request
{
    public function __construct(
        private string $method,
        private string $path,
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        return new self($method, $path);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function input(string $key, string $default = '', bool $trim = true): string
    {
        $value = $_POST[$key] ?? $default;

        if (!is_string($value)) {
            return $default;
        }

        return $trim ? trim($value) : $value;
    }
}
