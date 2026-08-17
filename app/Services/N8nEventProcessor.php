<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

use HPucca\Platform\Models\Event;

final readonly class N8nEventProcessor implements EventProcessorContract
{
    public function process(Event $event): void
    {
        throw new PermanentProcessingException('N8n destination transport is not configured.');
    }
}
