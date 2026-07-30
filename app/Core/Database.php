<?php

declare(strict_types=1);

namespace HPucca\Platform\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private ?PDO $connection = null;

    /**
     * @param array{
     *     host: string,
     *     port: int,
     *     name: string,
     *     user: string,
     *     password: string,
     *     sslmode: string
     * } $config
     */
    public function __construct(
        private readonly array $config,
    ) {
    }

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        try {
            $this->connection = new PDO($this->dsn(), $this->config['user'], $this->config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException) {
            throw new RuntimeException('Database connection failed.');
        }

        return $this->connection;
    }

    public function isConnected(): bool
    {
        try {
            $this->connection()->query('SELECT 1');

            return true;
        } catch (RuntimeException | PDOException) {
            return false;
        }
    }

    private function dsn(): string
    {
        return sprintf(
            'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
            $this->config['host'],
            $this->config['port'],
            $this->config['name'],
            $this->config['sslmode'],
        );
    }
}
