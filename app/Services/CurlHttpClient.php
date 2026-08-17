<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

final readonly class CurlHttpClient implements HttpClientContract
{
    private const RESPONSE_BODY_LIMIT_BYTES = 8192;

    public function postJson(string $url, array $payload, int $timeoutSeconds): HttpResponse
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!is_string($json)) {
            throw new HttpTransportException('HTTP request payload could not be encoded.');
        }

        $handle = curl_init($url);

        if ($handle === false) {
            throw new HttpTransportException('HTTP request could not be initialized.');
        }

        $body = '';

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => max(1, $timeoutSeconds),
            CURLOPT_CONNECTTIMEOUT => min(5, max(1, $timeoutSeconds)),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body): int {
                $remaining = self::RESPONSE_BODY_LIMIT_BYTES - strlen($body);

                if ($remaining > 0) {
                    $body .= substr($chunk, 0, $remaining);
                }

                return strlen($chunk);
            },
        ]);

        $result = curl_exec($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $errorNumber = curl_errno($handle);

        curl_close($handle);

        if ($result === false || $errorNumber !== 0) {
            throw new HttpTransportException('HTTP transport failure.');
        }

        return new HttpResponse($statusCode, $body);
    }
}
