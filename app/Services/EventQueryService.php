<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

use HPucca\Platform\Models\Event;
use HPucca\Platform\Repositories\EventRepositoryContract;
use HPucca\Platform\Repositories\IntegrationSourceRepositoryContract;
use HPucca\Platform\Repositories\TenantRepository;

final readonly class EventQueryService
{
    public function __construct(
        private EventRepositoryContract $events,
        private IntegrationSourceRepositoryContract $sources,
        private TenantRepository $tenants,
    ) {
    }

    /**
     * @param array<string, string> $filters
     * @return array{events: Event[], tenants: array<int, mixed>, sources: array<int, mixed>, filters: array<string, string>, total: int, page: int, pages: int, perPage: int}
     */
    public function list(array $filters, int $page, int $perPage = 15): array
    {
        $filters = $this->filters($filters);
        $total = $this->events->count($filters);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        return [
            'events' => $this->events->paginate($filters, $page, $perPage),
            'tenants' => $this->tenants->all(),
            'sources' => $this->sources->paginate('', null, 1, 1000),
            'filters' => $filters,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
        ];
    }

    public function find(int $id): ?Event
    {
        return $this->events->findById($id);
    }

    /**
     * @param array<string, string> $filters
     * @return array<string, string>
     */
    private function filters(array $filters): array
    {
        return [
            'search' => trim($filters['search'] ?? ''),
            'tenant_id' => trim($filters['tenant_id'] ?? ''),
            'source_id' => trim($filters['source_id'] ?? ''),
            'event_type' => trim($filters['event_type'] ?? ''),
            'status' => trim($filters['status'] ?? ''),
            'received_from' => trim($filters['received_from'] ?? ''),
            'received_to' => trim($filters['received_to'] ?? ''),
        ];
    }
}
