<?php

declare(strict_types=1);

namespace HPucca\Platform\Core;

final readonly class Request
{
    public function __construct(
        private string $method,
        private string $path,
        private array $params = [],
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

    public function query(string $key, string $default = ''): string
    {
        $value = $_GET[$key] ?? $default;

        if (!is_string($value)) {
            return $default;
        }

        return trim($value);
    }

    public function header(string $name, string $default = ''): string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $_SERVER[$serverKey] ?? null;

        if ($value === null && strtolower($name) === 'content-type') {
            $value = $_SERVER['CONTENT_TYPE'] ?? null;
        }

        return is_string($value) ? trim($value) : $default;
    }

    public function contentType(): string
    {
        $contentType = $this->header('Content-Type');
        $parts = explode(';', $contentType);

        return strtolower(trim($parts[0] ?? ''));
    }

    public function rawBody(): string
    {
        $body = file_get_contents('php://input');

        return $body === false ? '' : $body;
    }

    public function integerQuery(string $key, int $default = 1): int
    {
        $value = filter_var($_GET[$key] ?? null, FILTER_VALIDATE_INT);

        return $value === false || $value === null ? $default : max(1, $value);
    }

    public function param(string $key): ?string
    {
        $value = $this->params[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param array<string, string> $params
     */
    public function withParams(array $params): self
    {
        return new self($this->method, $this->path, $params);
    }
}
