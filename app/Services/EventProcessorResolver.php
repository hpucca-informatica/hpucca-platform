<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

use HPucca\Platform\Models\IntegrationDestination;

final readonly class EventProcessorResolver
{
    public function __construct(
        private EventProcessorContract $n8nProcessor = new N8nEventProcessor(),
    ) {
    }

    public function resolve(IntegrationDestination $destination): EventProcessorContract
    {
        if ($destination->type === 'n8n') {
            return $this->n8nProcessor;
        }

        throw new PermanentProcessingException('Unsupported integration destination type.');
    }
}
