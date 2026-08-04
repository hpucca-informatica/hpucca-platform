<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

final class ApiKeyService
{
    public const PREFIX = 'hpk_live_';

    public function generate(): string
    {
        return self::PREFIX . bin2hex(random_bytes(32));
    }

    public function hash(string $apiKey): string
    {
        return password_hash($apiKey, PASSWORD_DEFAULT);
    }

    public function verify(string $apiKey, string $hash): bool
    {
        if (!str_starts_with($apiKey, self::PREFIX) || $hash === '') {
            return false;
        }

        return password_verify($apiKey, $hash);
    }
}
