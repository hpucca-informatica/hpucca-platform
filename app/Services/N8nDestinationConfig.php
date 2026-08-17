<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

final readonly class N8nDestinationConfig
{
    public function __construct(
        public string $webhookUrl,
        public int $timeoutSeconds,
    ) {
    }

    public static function fromJson(string $json): self
    {
        $config = json_decode($json, true);

        if (!is_array($config)) {
            throw new PermanentProcessingException('Permanent n8n delivery failure.');
        }

        $webhookUrl = trim((string) ($config['webhook_url'] ?? ''));
        $timeoutSeconds = (int) ($config['timeout_seconds'] ?? 10);

        if ($webhookUrl === '' || $timeoutSeconds < 1 || $timeoutSeconds > 30) {
            throw new PermanentProcessingException('Permanent n8n delivery failure.');
        }

        return new self($webhookUrl, $timeoutSeconds);
    }

    public static function maskedEndpoint(string $url): string
    {
        $parts = parse_url($url);

        if (!is_array($parts)) {
            return '-';
        }

        $scheme = (string) ($parts['scheme'] ?? '');
        $host = (string) ($parts['host'] ?? '');
        $path = (string) ($parts['path'] ?? '');

        if ($scheme === '' || $host === '') {
            return '-';
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn (string $segment): bool => $segment !== ''));
        $last = $segments === [] ? '' : '/' . end($segments);

        return $scheme . '://' . $host . ($last === '' ? '' : '/...' . $last);
    }
}
