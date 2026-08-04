<?php

declare(strict_types=1);

namespace HPucca\Platform\Repositories;

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
