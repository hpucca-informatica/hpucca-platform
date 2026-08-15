<?php

declare(strict_types=1);

namespace HPucca\Platform\Repositories;

use PDO;

final readonly class EventMetricsRepository implements EventMetricsRepositoryContract
{
    public function __construct(
        private PDO $connection,
    ) {
    }

    /**
     * @param array<string, string> $filters
     * @return array{received: int, processed: int, failed: int, pending: int, processing: int, scheduled_retry: int, avg_attempts_finalized: float|null}
     */
    public function summary(array $filters): array
    {
        [$where, $params] = $this->where($filters);
        $statement = $this->connection->prepare(
            "SELECT
                COUNT(*) AS received,
                COALESCE(SUM(CASE WHEN e.status = 'processed' THEN 1 ELSE 0 END), 0) AS processed,
                COALESCE(SUM(CASE WHEN e.status = 'failed' THEN 1 ELSE 0 END), 0) AS failed,
                COALESCE(SUM(CASE WHEN e.status = 'pending' THEN 1 ELSE 0 END), 0) AS pending,
                COALESCE(SUM(CASE WHEN e.status = 'processing' THEN 1 ELSE 0 END), 0) AS processing,
                COALESCE(SUM(CASE WHEN e.status = 'pending' AND e.attempts > 0 AND e.available_at > CURRENT_TIMESTAMP THEN 1 ELSE 0 END), 0) AS scheduled_retry,
                AVG(CASE WHEN e.status IN ('processed', 'failed') THEN e.attempts ELSE NULL END) AS avg_attempts_finalized
             FROM events e
             INNER JOIN tenants t ON t.id = e.tenant_id
             INNER JOIN integration_sources s ON s.id = e.integration_source_id
             WHERE {$where}"
        );
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'received' => (int) ($row['received'] ?? 0),
            'processed' => (int) ($row['processed'] ?? 0),
            'failed' => (int) ($row['failed'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
            'processing' => (int) ($row['processing'] ?? 0),
            'scheduled_retry' => (int) ($row['scheduled_retry'] ?? 0),
            'avg_attempts_finalized' => $row['avg_attempts_finalized'] === null ? null : (float) $row['avg_attempts_finalized'],
        ];
    }

    /**
     * @param array<string, string> $filters
     * @return array<int, array{day: string, received: int, processed: int, failed: int}>
     */
    public function dailySeries(array $filters, string $timezone): array
    {
        [$where, $params] = $this->where($filters);
        $params['timezone'] = $timezone;
        $statement = $this->connection->prepare(
            "SELECT
                TO_CHAR(DATE_TRUNC('day', e.received_at AT TIME ZONE :timezone), 'YYYY-MM-DD') AS day,
                COUNT(*) AS received,
                COALESCE(SUM(CASE WHEN e.status = 'processed' THEN 1 ELSE 0 END), 0) AS processed,
                COALESCE(SUM(CASE WHEN e.status = 'failed' THEN 1 ELSE 0 END), 0) AS failed
             FROM events e
             INNER JOIN tenants t ON t.id = e.tenant_id
             INNER JOIN integration_sources s ON s.id = e.integration_source_id
             WHERE {$where}
             GROUP BY day
             ORDER BY day ASC"
        );
        $statement->execute($params);

        return array_map(static fn (array $row): array => [
            'day' => (string) $row['day'],
            'received' => (int) $row['received'],
            'processed' => (int) $row['processed'],
            'failed' => (int) $row['failed'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param array<string, string> $filters
     * @return array<int, array<string, mixed>>
     */
    public function recentFailures(array $filters, int $limit = 10): array
    {
        return $this->limitedEvents(
            $filters,
            "e.status = 'failed'",
            'e.failed_at DESC NULLS LAST, e.updated_at DESC',
            $limit,
            ['failed_at', 'last_error', 'external_id'],
        );
    }

    /**
     * @param array<string, string> $filters
     * @return array<int, array<string, mixed>>
     */
    public function scheduledRetries(array $filters, int $limit = 10): array
    {
        return $this->limitedEvents(
            $filters,
            "e.status = 'pending' AND e.attempts > 0 AND e.available_at > CURRENT_TIMESTAMP",
            'e.available_at ASC, e.updated_at ASC',
            $limit,
            ['available_at'],
        );
    }

    /**
     * @param array<string, string> $filters
     * @return array<int, array<string, mixed>>
     */
    public function processingEvents(array $filters, int $limit = 10): array
    {
        return $this->limitedEvents(
            $filters,
            "e.status = 'processing'",
            'e.updated_at DESC',
            $limit,
            ['updated_at'],
        );
    }

    /**
     * @param array<string, string> $filters
     * @return array<int, array<string, mixed>>
     */
    public function topAttempts(array $filters, int $limit = 10): array
    {
        return $this->limitedEvents(
            $filters,
            'e.attempts > 0',
            'e.attempts DESC, e.updated_at DESC',
            $limit,
            ['updated_at'],
        );
    }

    /**
     * @param array<string, string> $filters
     * @param string[] $extraColumns
     * @return array<int, array<string, mixed>>
     */
    private function limitedEvents(array $filters, string $extraWhere, string $orderBy, int $limit, array $extraColumns): array
    {
        [$where, $params] = $this->where($filters);
        $columns = [
            'e.id',
            'e.code',
            'e.status',
            'e.attempts',
            'e.tenant_id',
            't.name AS tenant_name',
            'e.integration_source_id',
            's.name AS integration_source_name',
            'e.event_type',
        ];

        foreach ($extraColumns as $column) {
            $columns[] = 'e.' . $column;
        }

        $statement = $this->connection->prepare(
            'SELECT ' . implode(', ', $columns) . "
             FROM events e
             INNER JOIN tenants t ON t.id = e.tenant_id
             INNER JOIN integration_sources s ON s.id = e.integration_source_id
             WHERE {$where}
               AND {$extraWhere}
             ORDER BY {$orderBy}
             LIMIT :limit"
        );

        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $statement->bindValue('limit', max(1, min(20, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, string> $filters
     * @return array{0: string, 1: array<string, string|int>}
     */
    private function where(array $filters): array
    {
        $where = 'e.received_at >= :received_from AND e.received_at <= :received_to';
        $params = [
            'received_from' => $filters['received_from'],
            'received_to' => $filters['received_to'],
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

        return [$where, $params];
    }
}
