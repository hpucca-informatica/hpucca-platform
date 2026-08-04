<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

final readonly class SensitivePayloadValidator
{
    /**
     * @var string[]
     */
    private const SENSITIVE_KEYS = [
        'password',
        'senha',
        'passwd',
        'token',
        'access_token',
        'refresh_token',
        'api_key',
        'apikey',
        'secret',
        'client_secret',
        'authorization',
    ];

    private const MAX_DEPTH = 32;

    /**
     * @param array<string, mixed> $payload
     */
    public function containsSensitiveData(array $payload): bool
    {
        return $this->containsSensitiveValue($payload, 0);
    }

    /**
     * @return string[]
     */
    public function sensitiveKeys(): array
    {
        return self::SENSITIVE_KEYS;
    }

    private function containsSensitiveValue(mixed $value, int $depth): bool
    {
        if ($depth > self::MAX_DEPTH) {
            return false;
        }

        if (is_string($value)) {
            return str_contains(strtolower($value), ApiKeyService::PREFIX);
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $key => $nestedValue) {
            if (is_string($key) && in_array($this->normalizeKey($key), self::SENSITIVE_KEYS, true)) {
                return true;
            }

            if ($this->containsSensitiveValue($nestedValue, $depth + 1)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeKey(string $key): string
    {
        $normalized = strtolower(trim($key));
        $normalized = preg_replace('/[\s-]+/', '_', $normalized) ?? $normalized;

        return preg_replace('/_+/', '_', $normalized) ?? $normalized;
    }
}
