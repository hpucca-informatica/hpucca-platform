<?php

declare(strict_types=1);

use HPucca\Platform\Services\CurlHttpClient;
use HPucca\Platform\Services\HttpTransportException;

require dirname(__DIR__) . '/vendor/autoload.php';

$code = (string) file_get_contents(dirname(__DIR__) . '/app/Services/CurlHttpClient.php');

assert(str_contains($code, 'curl_init'));
assert(str_contains($code, 'CURLOPT_POST'));
assert(str_contains($code, 'Content-Type: application/json'));
assert(str_contains($code, 'CURLOPT_TIMEOUT'));
assert(str_contains($code, 'CURLOPT_CONNECTTIMEOUT'));
assert(str_contains($code, 'RESPONSE_BODY_LIMIT_BYTES'));
assert(!str_contains($code, 'Guzzle'));
assert(!str_contains($code, 'Authorization'));
assert(!str_contains($code, 'Bearer'));
assert(!str_contains($code, 'error_log($json'));
assert(!str_contains($code, 'echo $json'));
assert(class_exists(CurlHttpClient::class));

if (!extension_loaded('curl')) {
    echo 'CurlHttpClientTest skipped behavior checks: ext-curl is not available.' . PHP_EOL;
    return;
}

$server = curlHttpClientStartServer();

try {
    $client = new CurlHttpClient();
    $response = $client->postJson($server['baseUrl'] . '/ok', ['name' => 'Ana'], 2);
    assert($response->statusCode === 201);

    $request = json_decode((string) file_get_contents($server['requestFile']), true);
    assert(is_array($request));
    assert($request['method'] === 'POST');
    assert(str_contains((string) $request['content_type'], 'application/json'));
    assert($request['body'] === '{"name":"Ana"}');
    assert(!isset($request['authorization']) || $request['authorization'] === '');

    $large = $client->postJson($server['baseUrl'] . '/large', ['ok' => true], 2);
    assert($large->statusCode === 200);
    assert(strlen($large->body) === 8192);

    $redirect = $client->postJson($server['baseUrl'] . '/redirect', ['ok' => true], 2);
    assert($redirect->statusCode === 302);
    assert($redirect->body === '');

    try {
        $client->postJson('http://127.0.0.1:1/unavailable', ['ok' => true], 1);
        assert(false);
    } catch (HttpTransportException $exception) {
        assert($exception->getMessage() === 'HTTP transport failure.');
    }
} finally {
    proc_terminate($server['process']);
    proc_close($server['process']);
    @unlink($server['router']);
    @unlink($server['requestFile']);
    @rmdir($server['directory']);
}

echo 'CurlHttpClientTest passed.' . PHP_EOL;

/**
 * @return array{baseUrl: string, directory: string, router: string, requestFile: string, process: resource}
 */
function curlHttpClientStartServer(): array
{
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hpucca-curl-test-' . bin2hex(random_bytes(4));
    mkdir($directory);
    $requestFile = $directory . DIRECTORY_SEPARATOR . 'request.json';
    $router = $directory . DIRECTORY_SEPARATOR . 'router.php';
    file_put_contents($router, sprintf(<<<'PHP'
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/ok') {
    file_put_contents(%s, json_encode([
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
        'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? '',
        'body' => file_get_contents('php://input'),
    ], JSON_UNESCAPED_SLASHES));
    http_response_code(201);
    echo 'created';
    return;
}
if ($path === '/large') {
    http_response_code(200);
    echo str_repeat('x', 9000);
    return;
}
if ($path === '/redirect') {
    http_response_code(302);
    header('Location: /ok');
    return;
}
http_response_code(404);
PHP, var_export($requestFile, true)));

    $port = curlHttpClientFreePort();
    $command = [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $directory, $router];
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, $directory);

    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start local HTTP test server.');
    }

    $baseUrl = 'http://127.0.0.1:' . $port;
    $deadline = microtime(true) + 5;
    do {
        $socket = @fsockopen('127.0.0.1', $port);
        if (is_resource($socket)) {
            fclose($socket);

            return [
                'baseUrl' => $baseUrl,
                'directory' => $directory,
                'router' => $router,
                'requestFile' => $requestFile,
                'process' => $process,
            ];
        }

        usleep(50000);
    } while (microtime(true) < $deadline);

    proc_terminate($process);
    proc_close($process);
    throw new RuntimeException('Local HTTP test server did not start.');
}

function curlHttpClientFreePort(): int
{
    $socket = stream_socket_server('tcp://127.0.0.1:0');

    if (!is_resource($socket)) {
        throw new RuntimeException('Unable to allocate local port.');
    }

    $name = stream_socket_get_name($socket, false);
    fclose($socket);

    return (int) substr((string) $name, strrpos((string) $name, ':') + 1);
}
