<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

final class FlashService
{
    private const SESSION_KEY = 'flash_messages';

    public static function add(string $message, string $type = 'info'): void
    {
        self::ensureSessionStarted();
        $_SESSION[self::SESSION_KEY][] = [
            'type' => self::normalizeType($type),
            'message' => $message,
        ];
    }

    /**
     * @return array<int, array{type: string, message: string}>
     */
    public static function consume(): array
    {
        self::ensureSessionStarted();
        $messages = $_SESSION[self::SESSION_KEY] ?? [];
        unset($_SESSION[self::SESSION_KEY]);

        if (!is_array($messages)) {
            return [];
        }

        $normalized = [];
        foreach ($messages as $message) {
            if (is_string($message)) {
                $normalized[] = ['type' => 'info', 'message' => $message];
                continue;
            }

            if (!is_array($message) || !is_string($message['message'] ?? null)) {
                continue;
            }

            $type = is_string($message['type'] ?? null) ? $message['type'] : 'info';
            $normalized[] = [
                'type' => self::normalizeType($type),
                'message' => $message['message'],
            ];
        }

        return $normalized;
    }

    private static function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private static function normalizeType(string $type): string
    {
        return in_array($type, ['success', 'error', 'warning', 'info'], true) ? $type : 'info';
    }
}
