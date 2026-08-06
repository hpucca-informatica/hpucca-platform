<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

use HPucca\Platform\Models\Event;
use RuntimeException;

final class SimulatedEventProcessor implements EventProcessorContract
{
    public function process(Event $event): void
    {
        $payload = json_decode($event->payload, true);

        if (is_array($payload) && ($payload['simulate_failure'] ?? false) === true) {
            throw new RuntimeException('Simulated event processing failure.');
        }
    }
}
