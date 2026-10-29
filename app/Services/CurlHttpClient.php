<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

final readonly class CurlHttpClient implements HttpClientContract
{
    private const RESPONSE_BODY_LIMIT_BYTES = 8192;

    public function __construct(private ?WebhookUrlValidator $urlValidator = null)
    {
    }

    public function postJson(string $url, array $payload, int $timeoutSeconds): HttpResponse
    {
        $target = ($this->urlValidator ?? new WebhookUrlValidator(
            (string) \HPucca\Platform\Core\Config::get('app.env', 'local'),
        ))->resolve($url);
        if ($target === null) {
            throw new PermanentProcessingException('Permanent n8n delivery failure.');
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!is_string($json)) {
            throw new HttpTransportException('HTTP request payload could not be encoded.');
        }

        $handle = curl_init($url);

        if ($handle === false) {
            throw new HttpTransportException('HTTP request could not be initialized.');
        }

        $body = '';

        // The URL retains the hostname for Host, SNI and certificate verification.
        $address = str_contains($target['ip'], ':') ? '[' . $target['ip'] . ']' : $target['ip'];
        $options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => max(1, $timeoutSeconds),
            CURLOPT_CONNECTTIMEOUT => min(5, max(1, $timeoutSeconds)),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_PROXY => '',
            CURLOPT_PRE_PROXY => '',
            CURLOPT_NOPROXY => '*',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body): int {
                $remaining = self::RESPONSE_BODY_LIMIT_BYTES - strlen($body);

                if ($remaining > 0) {
                    $body .= substr($chunk, 0, $remaining);
                }

                return strlen($chunk);
            },
        ];
        if (!$target['literal']) {
            $options[CURLOPT_RESOLVE] = [$target['host'] . ':' . $target['port'] . ':' . $address];
        }
        if (!curl_setopt_array($handle, $options)) {
            curl_close($handle);
            throw new HttpTransportException('HTTP transport configuration failure.');
        }

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
