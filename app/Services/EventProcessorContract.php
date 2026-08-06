<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

use HPucca\Platform\Models\Event;

interface EventProcessorContract
{
    public function process(Event $event): void;
}
