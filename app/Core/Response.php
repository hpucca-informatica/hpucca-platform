<?php

declare(strict_types=1);

namespace HPucca\Platform\Core;

final readonly class Response
{
    public function __construct(
        private string $content,
        private int $statusCode = 200,
        private array $headers = [],
    ) {
    }

    public static function json(array $data, int $statusCode = 200): self
    {
        return new self(json_encode($data, JSON_THROW_ON_ERROR), $statusCode, [
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
    }

    public static function html(string $content, int $statusCode = 200): self
    {
        return new self($content, $statusCode, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }

    public static function redirect(string $location, int $statusCode = 302): self
    {
        return new self('', $statusCode, [
            'Location' => $location,
        ]);
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header(sprintf('%s: %s', $name, $value));
        }

        echo $this->content;
    }
}
