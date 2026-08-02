<?php

declare(strict_types=1);

namespace HPucca\Platform\Core;

final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = []): string
    {
        $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        extract($data, EXTR_SKIP);

        ob_start();
        require dirname(__DIR__, 2) . '/views/' . $view;
        $content = ob_get_clean();

        return $content === false ? '' : $content;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function admin(string $view, array $data = []): Response
    {
        $content = self::render($view, $data);
        $layoutData = array_merge($data, [
            'content' => $content,
        ]);

        return Response::html(self::render('layouts/admin.php', $layoutData));
    }
}
