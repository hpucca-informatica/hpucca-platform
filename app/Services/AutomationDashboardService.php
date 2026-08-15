<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

use DateTimeImmutable;
use DateTimeZone;
use HPucca\Platform\Core\Config;
use HPucca\Platform\Repositories\EventMetricsRepositoryContract;
use HPucca\Platform\Repositories\IntegrationSourceRepositoryContract;
use HPucca\Platform\Repositories\TenantRepositoryContract;
use Throwable;

final readonly class AutomationDashboardService
{
    private const PERIODS = ['today', '24h', '7d', '30d', 'custom'];
    private const STATUSES = ['pending', 'processing', 'processed', 'failed'];

    public function __construct(
        private EventMetricsRepositoryContract $metrics,
        private IntegrationSourceRepositoryContract $sources,
        private TenantRepositoryContract $tenants,
    ) {
    }

    /**
     * @param array<string, string> $input
     * @return array<string, mixed>
     */
    public function dashboard(array $input): array
    {
        $timezone = new DateTimeZone((string) Config::get('app.timezone', date_default_timezone_get()));
        $filters = $this->filters($input, $timezone);
        $summary = $this->metrics->summary($filters);
        $successDenominator = $summary['processed'] + $summary['failed'];
        $processingTimeoutMinutes = max(1, (int) Config::get('app.event_processing_timeout_minutes', 15));

        $processingEvents = array_map(
            fn (array $event): array => $this->withProcessingState($event, $processingTimeoutMinutes),
            $this->metrics->processingEvents($filters)
        );

        return [
            'filters' => $filters,
            'periodOptions' => [
                'today' => 'Hoje',
                '24h' => 'Ultimas 24 horas',
                '7d' => 'Ultimos 7 dias',
                '30d' => 'Ultimos 30 dias',
                'custom' => 'Intervalo personalizado',
            ],
            'statusOptions' => self::STATUSES,
            'tenants' => $this->tenants->all(),
            'sources' => $this->sources->paginate('', null, 1, 1000),
            'summary' => [
                ...$summary,
                'success_rate' => $successDenominator === 0 ? null : round(($summary['processed'] / $successDenominator) * 100, 1),
                'success_denominator' => $successDenominator,
            ],
            'dailySeries' => $this->metrics->dailySeries($filters, $timezone->getName()),
            'recentFailures' => $this->metrics->recentFailures($filters),
            'scheduledRetries' => $this->metrics->scheduledRetries($filters),
            'processingEvents' => $processingEvents,
            'topAttempts' => $this->metrics->topAttempts($filters),
            'timezone' => $timezone->getName(),
            'processingTimeoutMinutes' => $processingTimeoutMinutes,
            'metricNotes' => [
                'period' => 'As metricas do periodo usam received_at convertido a partir do APP_TIMEZONE.',
                'success_rate' => 'Taxa de sucesso = processed / (processed + failed). Pendentes, retries e processing ficam fora do denominador.',
                'scheduled_retry' => "Retry agendado = status pending, attempts > 0 e available_at > CURRENT_TIMESTAMP.",
                'stale_processing' => 'Processing possivelmente stale usa updated_at anterior ao timeout operacional configurado.',
            ],
        ];
    }

    /**
     * @param array<string, string> $input
     * @return array<string, string>
     */
    private function filters(array $input, DateTimeZone $timezone): array
    {
        $period = in_array($input['period'] ?? '', self::PERIODS, true) ? (string) $input['period'] : 'today';
        $now = new DateTimeImmutable('now', $timezone);
        [$from, $to] = match ($period) {
            '24h' => [$now->modify('-24 hours'), $now],
            '7d' => [$now->modify('-6 days')->setTime(0, 0, 0), $now->setTime(23, 59, 59)],
            '30d' => [$now->modify('-29 days')->setTime(0, 0, 0), $now->setTime(23, 59, 59)],
            'custom' => $this->customRange($input, $timezone, $now),
            default => [$now->setTime(0, 0, 0), $now->setTime(23, 59, 59)],
        };

        $status = trim($input['status'] ?? '');
        if (!in_array($status, self::STATUSES, true)) {
            $status = '';
        }

        return [
            'period' => $period,
            'date_from' => $from->format('Y-m-d'),
            'date_to' => $to->format('Y-m-d'),
            'received_from' => $from->format('Y-m-d H:i:sP'),
            'received_to' => $to->format('Y-m-d H:i:sP'),
            'tenant_id' => $this->positiveIntegerString($input['tenant_id'] ?? ''),
            'source_id' => $this->positiveIntegerString($input['source_id'] ?? ''),
            'event_type' => mb_substr(trim($input['event_type'] ?? ''), 0, 100),
            'status' => $status,
        ];
    }

    /**
     * @param array<string, string> $input
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function customRange(array $input, DateTimeZone $timezone, DateTimeImmutable $fallback): array
    {
        $from = $this->dateFromInput($input['date_from'] ?? '', $timezone)?->setTime(0, 0, 0)
            ?? $fallback->setTime(0, 0, 0);
        $to = $this->dateFromInput($input['date_to'] ?? '', $timezone)?->setTime(23, 59, 59)
            ?? $fallback->setTime(23, 59, 59);

        if ($from > $to) {
            return [$to->setTime(0, 0, 0), $from->setTime(23, 59, 59)];
        }

        return [$from, $to];
    }

    private function dateFromInput(string $value, DateTimeZone $timezone): ?DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            return new DateTimeImmutable($value, $timezone);
        } catch (Throwable) {
            return null;
        }
    }

    private function positiveIntegerString(string $value): string
    {
        $value = trim($value);

        if ($value === '' || filter_var($value, FILTER_VALIDATE_INT) === false) {
            return '';
        }

        return (string) max(1, (int) $value);
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    private function withProcessingState(array $event, int $timeoutMinutes): array
    {
        $updatedAt = is_string($event['updated_at'] ?? null) ? $event['updated_at'] : '';
        $event['processing_age_minutes'] = null;
        $event['processing_state'] = 'recent';

        try {
            $updated = new DateTimeImmutable($updatedAt);
            $age = max(0, (int) floor((time() - $updated->getTimestamp()) / 60));
            $event['processing_age_minutes'] = $age;
            $event['processing_state'] = $age >= $timeoutMinutes ? 'stale' : 'recent';
        } catch (Throwable) {
            $event['processing_state'] = 'unknown';
        }

        return $event;
    }
}
