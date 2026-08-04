<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

use InvalidArgumentException;
use PDO;

final readonly class PublicCodeGenerator
{
    /**
     * @var array<string, string>
     */
    private const SEQUENCES = [
        'TEN' => 'tenants_code_seq',
        'USR' => 'users_code_seq',
        'SRC' => 'integration_sources_code_seq',
        'EVT' => 'events_code_seq',
    ];

    public function __construct(
        private PDO $connection,
    ) {
    }

    public function next(string $prefix): string
    {
        $sequence = self::SEQUENCES[$prefix] ?? null;

        if ($sequence === null) {
            throw new InvalidArgumentException('Unsupported public code prefix.');
        }

        $number = (int) $this->connection->query(sprintf("SELECT nextval('%s')", $sequence))->fetchColumn();

        return self::format($prefix, $number);
    }

    public static function format(string $prefix, int $number): string
    {
        return sprintf('%s%s', $prefix, str_pad((string) $number, 6, '0', STR_PAD_LEFT));
    }
}
