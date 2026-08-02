<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

final class FlashService
{
    private const SESSION_KEY = 'flash_messages';

    public static function add(string $message): void
    {
        self::ensureSessionStarted();
        $_SESSION[self::SESSION_KEY][] = $message;
    }

    /**
     * @return string[]
     */
    public static function consume(): array
    {
        self::ensureSessionStarted();
        $messages = $_SESSION[self::SESSION_KEY] ?? [];
        unset($_SESSION[self::SESSION_KEY]);

        return is_array($messages) ? array_values(array_filter($messages, 'is_string')) : [];
    }

    private static function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
