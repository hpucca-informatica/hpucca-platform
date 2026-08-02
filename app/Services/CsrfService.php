<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

final class CsrfService
{
    private const SESSION_KEY = 'csrf_token';
    public const FIELD = '_csrf';

    public static function token(): string
    {
        self::ensureSessionStarted();

        if (!isset($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function field(): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            self::FIELD,
            htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8'),
        );
    }

    public static function validate(string $token): bool
    {
        self::ensureSessionStarted();
        $sessionToken = $_SESSION[self::SESSION_KEY] ?? '';

        return is_string($sessionToken) && $sessionToken !== '' && hash_equals($sessionToken, $token);
    }

    private static function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
