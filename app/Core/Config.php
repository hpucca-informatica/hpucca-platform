<?php

declare(strict_types=1);

namespace HPucca\Platform\Core;

use InvalidArgumentException;

final class Config
{
    /**
     * @var array<string, mixed>|null
     */
    private static ?array $items = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::items();

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private static function items(): array
    {
        if (self::$items !== null) {
            return self::$items;
        }

        $configPath = dirname(__DIR__, 2) . '/config';
        self::$items = [];

        foreach (glob($configPath . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            $values = require $file;

            if (!is_array($values)) {
                throw new InvalidArgumentException(sprintf('Config file "%s" must return an array.', $name));
            }

            self::$items[$name] = $values;
        }

        return self::$items;
    }
}
