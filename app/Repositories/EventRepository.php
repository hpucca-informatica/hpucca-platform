<?php

declare(strict_types=1);

namespace HPucca\Platform\Repositories;

use DateTimeImmutable;
use HPucca\Platform\Models\Event;
use HPucca\Platform\Services\PublicCodeGenerator;
use PDO;
use PDOException;

final readonly class EventRepository implements EventRepositoryContract
{
    public function __construct(
        private PDO $connection,
    ) {
    }

    /**
     * @param array<string, string> $filters
     * @return Event[]
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        [$where, $params] = $this->where($filters);
        $statement = $this->connection->prepare(
            $this->selectSql() . "
             WHERE {$where}
             ORDER BY e.received_at DESC
             LIMIT :limit OFFSET :offset"
        );

        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $statement->bindValue('limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn (array $row): Event => $this->map($row), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param array<string, string> $filters
     */
    public function count(array $filters): int
    {
        [$where, $params] = $this->where($filters);
        $statement = $this->connection->prepare(
            "SELECT COUNT(*)
             FROM events e
             INNER JOIN tenants t ON t.id = e.tenant_id
             INNER JOIN integration_sources s ON s.id = e.integration_source_id
             WHERE {$where}"
        );
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    public function findById(int $id): ?Event
    {
        $statement = $this->connection->prepare($this->selectSql() . ' WHERE e.id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->map($row);
    }

    public function findDuplicate(int $sourceId, string $externalId, string $eventType): ?Event
    {
        $statement = $this->connection->prepare(
            $this->selectSql() . ' WHERE e.integration_source_id = :source_id AND e.external_id = :external_id AND e.event_type = :event_type LIMIT 1'
        );
        $statement->execute([
            'source_id' => $sourceId,
            'external_id' => $externalId,
            'event_type' => $eventType,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->map($row);
    }

    public function reserveNextPending(): ?Event
    {
        $transaction = $this->beginShortTransaction();

        try {
            $statement = $this->connection->prepare(
                "SELECT e.id
                 FROM events e
                 INNER JOIN tenants t ON t.id = e.tenant_id
                 INNER JOIN integration_sources s ON s.id = e.integration_source_id
                 WHERE e.status = 'pending'
                   AND e.available_at <= CURRENT_TIMESTAMP
                   AND t.status = 'active'
                   AND s.status = 'active'
                 ORDER BY e.available_at ASC, e.id ASC
                 FOR UPDATE OF e SKIP LOCKED
                 LIMIT 1"
            );
            $statement->execute();
            $eventId = $statement->fetchColumn();

            if ($eventId === false) {
                $this->commitShortTransaction($transaction);

                return null;
            }

            $update = $this->connection->prepare(
                "UPDATE events
                 SET status = 'processing',
                     attempts = attempts + 1,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id
                   AND status = 'pending'"
            );
            $update->execute(['id' => (int) $eventId]);

            if ($update->rowCount() !== 1) {
                $this->rollBackShortTransaction($transaction);

                return null;
            }

            $this->commitShortTransaction($transaction);

            return $this->findById((int) $eventId);
        } catch (\Throwable $exception) {
            $this->rollBackShortTransaction($transaction);

            throw $exception;
        }
    }

    public function markProcessed(int $eventId, DateTimeImmutable $processedAt): bool
    {
        $statement = $this->connection->prepare(
            "UPDATE events
             SET status = 'processed',
                 processed_at = :processed_at,
                 failed_at = NULL,
                 last_error = NULL,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND status = 'processing'"
        );
        $statement->execute([
            'id' => $eventId,
            'processed_at' => $processedAt->format('Y-m-d H:i:sP'),
        ]);

        return $statement->rowCount() === 1;
    }

    public function markFailed(int $eventId, string $sanitizedError, DateTimeImmutable $failedAt): bool
    {
        $statement = $this->connection->prepare(
            "UPDATE events
             SET status = 'failed',
                 processed_at = NULL,
                 failed_at = :failed_at,
                 last_error = :last_error,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND status = 'processing'"
        );
        $statement->execute([
            'id' => $eventId,
            'failed_at' => $failedAt->format('Y-m-d H:i:sP'),
            'last_error' => substr($sanitizedError, 0, 1000),
        ]);

        return $statement->rowCount() === 1;
    }

    public function requeueFailed(int $eventId): bool
    {
        $statement = $this->connection->prepare(
            "UPDATE events
             SET status = 'pending',
                 available_at = CURRENT_TIMESTAMP,
                 failed_at = NULL,
                 last_error = NULL,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND status = 'failed'"
        );
        $statement->execute(['id' => $eventId]);

        return $statement->rowCount() === 1;
    }

    public function recoverStaleProcessing(int $timeoutMinutes): int
    {
        $statement = $this->connection->prepare(
            "UPDATE events
             SET status = 'pending',
                 available_at = CURRENT_TIMESTAMP,
                 processed_at = NULL,
                 failed_at = NULL,
                 last_error = NULL,
                 updated_at = CURRENT_TIMESTAMP
             WHERE status = 'processing'
               AND updated_at < CURRENT_TIMESTAMP - (CAST(:timeout_minutes AS INTEGER) * INTERVAL '1 minute')"
        );
        $statement->execute(['timeout_minutes' => max(1, $timeoutMinutes)]);

        return $statement->rowCount();
    }

    public function scheduleRetry(int $eventId, DateTimeImmutable $availableAt, string $sanitizedError): bool
    {
        $statement = $this->connection->prepare(
            "UPDATE events
             SET status = 'pending',
                 available_at = :available_at,
                 processed_at = NULL,
                 failed_at = NULL,
                 last_error = :last_error,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND status = 'processing'"
        );
        $statement->execute([
            'id' => $eventId,
            'available_at' => $availableAt->format('Y-m-d H:i:sP'),
            'last_error' => substr($sanitizedError, 0, 1000),
        ]);

        return $statement->rowCount() === 1;
    }

    /**
     * @param array{tenant_id: int, integration_source_id: int, event_type: string, external_id: string, payload: string, occurred_at: string|null} $data
     * @return array{event: Event, duplicate: bool}
     */
    public function create(array $data, PublicCodeGenerator $codes): array
    {
        try {
            $statement = $this->connection->prepare(
                'INSERT INTO events (code, tenant_id, integration_source_id, event_type, external_id, payload, occurred_at)
                 VALUES (:code, :tenant_id, :integration_source_id, :event_type, :external_id, CAST(:payload AS jsonb), :occurred_at)
                 RETURNING id'
            );
            $statement->execute([
                'code' => $codes->next('EVT'),
                'tenant_id' => $data['tenant_id'],
                'integration_source_id' => $data['integration_source_id'],
                'event_type' => $data['event_type'],
                'external_id' => $data['external_id'],
                'payload' => $data['payload'],
                'occurred_at' => $data['occurred_at'],
            ]);

            $event = $this->findById((int) $statement->fetchColumn()) ?? throw new \RuntimeException('Event not found after create.');

            return ['event' => $event, 'duplicate' => false];
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23505') {
                $duplicate = $this->findDuplicate(
                    $data['integration_source_id'],
                    $data['external_id'],
                    $data['event_type'],
                );

                if ($duplicate !== null) {
                    return ['event' => $duplicate, 'duplicate' => true];
                }
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, string> $filters
     * @return array{0: string, 1: array<string, string|int>}
     */
    private function where(array $filters): array
    {
        $where = "(
            :search = ''
            OR LOWER(e.code) LIKE LOWER(:like_search)
            OR LOWER(e.external_id) LIKE LOWER(:like_search)
        )";
        $params = [
            'search' => $filters['search'] ?? '',
            'like_search' => '%' . ($filters['search'] ?? '') . '%',
        ];

        foreach (['tenant_id' => 'e.tenant_id', 'source_id' => 'e.integration_source_id'] as $key => $column) {
            $value = trim($filters[$key] ?? '');
            if ($value !== '') {
                $where .= " AND {$column} = :{$key}";
                $params[$key] = max(1, (int) $value);
            }
        }

        foreach (['event_type' => 'e.event_type', 'status' => 'e.status'] as $key => $column) {
            $value = trim($filters[$key] ?? '');
            if ($value !== '') {
                $where .= " AND {$column} = :{$key}";
                $params[$key] = $value;
            }
        }

        if (($filters['received_from'] ?? '') !== '') {
            $where .= ' AND e.received_at >= :received_from';
            $params['received_from'] = $filters['received_from'] . ' 00:00:00';
        }

        if (($filters['received_to'] ?? '') !== '') {
            $where .= ' AND e.received_at <= :received_to';
            $params['received_to'] = $filters['received_to'] . ' 23:59:59';
        }

        return [$where, $params];
    }

    private function selectSql(): string
    {
        return 'SELECT e.id, e.code, e.tenant_id, t.name AS tenant_name,
                    e.integration_source_id, s.name AS integration_source_name,
                    e.event_type, e.external_id, e.payload::TEXT AS payload, e.status, e.attempts,
                    e.available_at, e.processed_at, e.failed_at, e.last_error, e.occurred_at,
                    e.received_at, e.created_at, e.updated_at
                FROM events e
                INNER JOIN tenants t ON t.id = e.tenant_id
                INNER JOIN integration_sources s ON s.id = e.integration_source_id';
    }

    private function beginShortTransaction(): string
    {
        if (!$this->connection->inTransaction()) {
            $this->connection->beginTransaction();

            return 'transaction';
        }

        $this->connection->exec('SAVEPOINT event_repository_reserve');

        return 'savepoint';
    }

    private function commitShortTransaction(string $transaction): void
    {
        if ($transaction === 'savepoint') {
            $this->connection->exec('RELEASE SAVEPOINT event_repository_reserve');

            return;
        }

        $this->connection->commit();
    }

    private function rollBackShortTransaction(string $transaction): void
    {
        if ($transaction === 'savepoint') {
            if ($this->connection->inTransaction()) {
                $this->connection->exec('ROLLBACK TO SAVEPOINT event_repository_reserve');
            }

            return;
        }

        if ($this->connection->inTransaction()) {
            $this->connection->rollBack();
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): Event
    {
        return new Event(
            (int) $row['id'],
            (string) $row['code'],
            (int) $row['tenant_id'],
            (string) $row['tenant_name'],
            (int) $row['integration_source_id'],
            (string) $row['integration_source_name'],
            (string) $row['event_type'],
            (string) $row['external_id'],
            (string) $row['payload'],
            (string) $row['status'],
            (int) $row['attempts'],
            (string) $row['available_at'],
            is_string($row['processed_at'] ?? null) ? $row['processed_at'] : null,
            is_string($row['failed_at'] ?? null) ? $row['failed_at'] : null,
            is_string($row['last_error'] ?? null) ? $row['last_error'] : null,
            is_string($row['occurred_at'] ?? null) ? $row['occurred_at'] : null,
            (string) $row['received_at'],
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }
}
