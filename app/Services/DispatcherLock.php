<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

use PDO;

final class DispatcherLock
{
    // Reserved global advisory lock for the Event Dispatcher Scheduler; do not reuse in other subsystems.
    private const LOCK_KEY = 6320001;

    private bool $acquired = false;

    public function __construct(
        private readonly PDO $connection,
    ) {
    }

    public function acquire(): bool
    {
        if ($this->acquired) {
            return true;
        }

        $statement = $this->connection->prepare('SELECT pg_try_advisory_lock(:lock_key)');
        $statement->execute(['lock_key' => self::LOCK_KEY]);
        $result = $statement->fetchColumn();
        $this->acquired = in_array($result, [true, 1, '1', 't', 'true'], true);

        return $this->acquired;
    }

    public function release(): void
    {
        if (!$this->acquired) {
            return;
        }

        $statement = $this->connection->prepare('SELECT pg_advisory_unlock(:lock_key)');
        $statement->execute(['lock_key' => self::LOCK_KEY]);
        $this->acquired = false;
    }
}
