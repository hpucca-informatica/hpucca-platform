<?php

declare(strict_types=1);

namespace HPucca\Platform\Core;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class ViewHelper
{
    public static function formatDate(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '-';
        }

        try {
            $date = new DateTimeImmutable($value);
            $timezone = new DateTimeZone((string) Config::get('app.timezone', date_default_timezone_get()));

            return $date->setTimezone($timezone)->format('d/m/Y H:i:s');
        } catch (Throwable) {
            return '-';
        }
    }

    public static function statusBadge(string $status): string
    {
        $labels = [
            'active' => 'Ativa',
            'inactive' => 'Inativa',
            'pending' => 'Pendente',
            'processing' => 'Processando',
            'processed' => 'Processado',
            'failed' => 'Falhou',
        ];
        $status = strtolower(trim($status));
        $label = $labels[$status] ?? ucfirst($status === '' ? 'desconhecido' : $status);
        $class = preg_match('/^[a-z0-9-]+$/', $status) === 1 ? $status : 'unknown';

        return sprintf(
            '<span class="status-badge status-%s"><span aria-hidden="true"></span>%s</span>',
            self::escape($class),
            self::escape($label),
        );
    }

    public static function copyableText(string $value, string $buttonLabel, string $feedback, string $class = ''): string
    {
        $id = 'copy-' . bin2hex(random_bytes(6));
        $classes = trim('copyable-text ' . $class);

        return sprintf(
            '<span class="%s"><code id="%s">%s</code><button type="button" class="button-small copy-button" data-copy-target="#%s" data-copy-feedback="%s" aria-label="%s">%s</button><span class="copy-feedback" data-copy-status aria-live="polite"></span></span>',
            self::escape($classes),
            self::escape($id),
            self::escape($value),
            self::escape($id),
            self::escape($feedback),
            self::escape($buttonLabel . ' ' . $value),
            self::escape($buttonLabel),
        );
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
