<?php

declare(strict_types=1);

namespace HPucca\Platform\Repositories;

interface EventMetricsRepositoryContract
{
    /**
     * @param array<string, string> $filters
     * @return array{received: int, processed: int, failed: int, pending: int, processing: int, scheduled_retry: int, avg_attempts_finalized: float|null}
     */
    public function summary(array $filters): array;

    /**
     * @param array<string, string> $filters
     * @return array<int, array{day: string, received: int, processed: int, failed: int}>
     */
    public function dailySeries(array $filters, string $timezone): array;

    /**
     * @param array<string, string> $filters
     * @return array<int, array<string, mixed>>
     */
    public function recentFailures(array $filters, int $limit = 10): array;

    /**
     * @param array<string, string> $filters
     * @return array<int, array<string, mixed>>
     */
    public function scheduledRetries(array $filters, int $limit = 10): array;

    /**
     * @param array<string, string> $filters
     * @return array<int, array<string, mixed>>
     */
    public function processingEvents(array $filters, int $limit = 10): array;

    /**
     * @param array<string, string> $filters
     * @return array<int, array<string, mixed>>
     */
    public function topAttempts(array $filters, int $limit = 10): array;
}
