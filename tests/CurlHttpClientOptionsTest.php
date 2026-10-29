<?php

declare(strict_types=1);

namespace HPucca\Platform\Services {
    function curl_init(string $url): object { $GLOBALS['requestUrl'] = $url; return new \stdClass(); }
    function curl_setopt_array($handle, array $options): bool { $GLOBALS['options'] = $options; return $GLOBALS['configure']; }
    function curl_exec($handle): bool { $GLOBALS['executed'] = true; return true; }
    function curl_getinfo($handle, int $option): int { return 204; }
    function curl_errno($handle): int { return 0; }
    function curl_close($handle): void { $GLOBALS['closed'] = true; }
}
namespace {
    use HPucca\Platform\Services\CurlHttpClient;
    use HPucca\Platform\Services\HttpTransportException;
    use HPucca\Platform\Services\WebhookUrlValidator;
    require dirname(__DIR__) . '/vendor/autoload.php';
    foreach (['8.8.8.8', '2606:4700:4700::1111'] as $ip) {
        $GLOBALS['configure'] = true;
        $GLOBALS['executed'] = false;
        $queries = 0;
        $validator = new WebhookUrlValidator('production', static function (string $host) use (&$queries, $ip): array {
            // A second lookup would be a rebinding attempt. Transport must use
            // only the one result it validated, encoded in CURLOPT_RESOLVE.
            return ++$queries === 1 ? [$ip] : ['127.0.0.1'];
        });
        $url = 'https://n8n.test:8443/webhook';
        $response = (new CurlHttpClient($validator))->postJson($url, ['test' => true], 3);
        assert($response->statusCode === 204);
        assert($queries === 1);
        assert($GLOBALS['requestUrl'] === $url);
        $options = $GLOBALS['options'];
        $address = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
        assert($options[CURLOPT_RESOLVE] === ['n8n.test:8443:' . $address]);
        assert($options[CURLOPT_SSL_VERIFYHOST] === 2);
        assert($options[CURLOPT_SSL_VERIFYPEER] === true);
        assert($options[CURLOPT_PROXY] === '' && $options[CURLOPT_PRE_PROXY] === '');
        assert($options[CURLOPT_NOPROXY] === '*');
        assert($options[CURLOPT_FOLLOWLOCATION] === false);
        assert($options[CURLOPT_MAXREDIRS] === 0);
        assert($options[CURLOPT_REDIR_PROTOCOLS] === (CURLPROTO_HTTP | CURLPROTO_HTTPS));
    }
    // Public literals require no DNS; cURL connects to that exact literal.
    foreach (['8.8.8.8', '[2606:4700:4700::1111]'] as $host) {
        (new CurlHttpClient(new WebhookUrlValidator('production')))->postJson('https://' . $host . '/', [], 1);
        assert(!isset($GLOBALS['options'][CURLOPT_RESOLVE]));
    }
    $GLOBALS['configure'] = false;
    $GLOBALS['executed'] = false;
    $GLOBALS['closed'] = false;
    try {
        (new CurlHttpClient(new WebhookUrlValidator('production')))->postJson('https://8.8.8.8/', [], 1);
        assert(false);
    } catch (HttpTransportException) {
        assert(!$GLOBALS['executed'] && $GLOBALS['closed']);
    }
    echo 'CurlHttpClientOptionsTest passed.' . PHP_EOL;
}
