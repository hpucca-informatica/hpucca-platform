<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

use HPucca\Platform\Models\Event;

final class SimulatedEventProcessor implements EventProcessorContract
{
    public function process(Event $event): void
    {
        $payload = json_decode($event->payload, true);

        if (!is_array($payload)) {
            return;
        }

        $failure = $payload['simulate_failure'] ?? false;

        if ($failure === 'transient' || $failure === true) {
            throw new TransientProcessingException('Simulated transient event processing failure.');
        }

        if ($failure === 'permanent') {
            throw new PermanentProcessingException('Simulated permanent event processing failure.');
        }
    }
}
