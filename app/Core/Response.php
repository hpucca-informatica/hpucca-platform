<?php

declare(strict_types=1);

namespace HPucca\Platform\Core;

final readonly class Response
{
    public function __construct(
        private array $data,
        private int $statusCode = 200,
        private array $headers = [],
    ) {
    }

    public static function json(array $data, int $statusCode = 200): self
    {
        return new self($data, $statusCode, [
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header(sprintf('%s: %s', $name, $value));
        }

        echo json_encode($this->data, JSON_THROW_ON_ERROR);
    }
}
