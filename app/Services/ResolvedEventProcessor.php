<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

use HPucca\Platform\Models\Event;
use HPucca\Platform\Models\IntegrationDestination;

final readonly class ResolvedEventProcessor implements EventProcessorContract
{
    public function __construct(
        private EventProcessorResolver $resolver = new EventProcessorResolver(),
    ) {
    }

    public function process(Event $event): void
    {
        if ($event->destinationId === null) {
            throw new PermanentProcessingException('Integration destination is not configured.');
        }

        $destination = new IntegrationDestination(
            $event->destinationId,
            $event->destinationCode ?? '',
            $event->tenantId,
            $event->tenantName,
            'active',
            $event->destinationName ?? '',
            '',
            $event->destinationType ?? '',
            $event->destinationStatus ?? '',
            $event->destinationConfig ?? '{}',
            '',
            '',
        );

        $this->resolver->resolve($destination)->process($event);
    }
}
