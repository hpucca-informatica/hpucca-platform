<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

interface HttpClientContract
{
    /**
     * @param array<string, mixed> $payload
     */
    public function postJson(string $url, array $payload, int $timeoutSeconds): HttpResponse;
}
