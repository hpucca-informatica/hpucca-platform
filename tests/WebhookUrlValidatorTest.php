<?php

declare(strict_types=1);

use HPucca\Platform\Services\WebhookUrlValidator;

require dirname(__DIR__) . '/vendor/autoload.php';

$production = new WebhookUrlValidator('production', static fn (string $host): array => ['8.8.8.8']);

assert($production->isValid('https://n8n.example.com/webhook/hpucca-events'));
assert(!$production->isValid('http://n8n.example.com/webhook'));
assert(!$production->isValid('https://localhost/webhook'));
assert(!$production->isValid('https://127.0.0.1/webhook'));
assert(!$production->isValid('https://127.1/webhook'));
assert(!$production->isValid('https://2130706433/webhook'));
assert(!$production->isValid('https://[::1]/webhook'));
assert(!$production->isValid('https://[::ffff:127.0.0.1]/webhook'));
assert(!$production->isValid('https://[::ffff:10.0.0.1]/webhook'));
assert(!$production->isValid('https://[::ffff:192.168.1.1]/webhook'));
assert(!$production->isValid('https://[fc00::]/webhook'));
assert(!$production->isValid('https://[fd00::]/webhook'));
assert(!$production->isValid('https://[fe80::]/webhook'));
assert(!$production->isValid('https://10.1.2.3/webhook'));
assert(!$production->isValid('https://172.16.0.1/webhook'));
assert(!$production->isValid('https://172.31.255.255/webhook'));
assert($production->isValid('https://172.32.0.1/webhook'));
assert(!$production->isValid('https://192.168.1.10/webhook'));
assert(!$production->isValid('https://169.254.1.10/webhook'));
assert(!$production->isValid('https://0.0.0.0/webhook'));
assert(!$production->isValid('https://user:pass@n8n.example.com/webhook'));
assert(!$production->isValid('not a url'));
assert(!$production->isValid('https:///webhook'));
assert(!$production->isValid('file:///etc/passwd'));
assert(!$production->isValid('ftp://n8n.example.com/webhook'));
assert(!$production->isValid('data:text/plain,test'));

$local = new WebhookUrlValidator('local', static fn (string $host): array => ['8.8.8.8']);
assert($local->isValid('http://n8n.example.com/webhook'));
assert(!$local->isValid('http://localhost/webhook'));

echo 'WebhookUrlValidatorTest passed.' . PHP_EOL;

// Byte classification must not depend on the spelling of an IP address.
$blocked = [
    '0:0:0:0:0:0:0:1', '::', '::ffff:8.8.8.8', '0:0:0:0:0:ffff:7f00:1',
    '::ffff:7f00:1', '64:ff9b::808:808', '100::1', '2001::1', '2001:db8::1',
    '2002:0808:0808::1', '3fff::1', 'fc00::1', 'fe80::1', 'fec0::1', 'ff02::1',
    '0.1.2.3', '10.0.0.1', '100.64.0.1', '100.127.255.255', '127.255.0.1',
    '169.254.169.254', '172.31.255.255', '192.0.0.9', '192.0.2.1', '192.88.99.1',
    '192.168.0.1', '198.18.0.1', '198.19.255.255', '198.51.100.1', '203.0.113.1',
    '224.0.0.1', '239.255.255.255', '240.0.0.1', '255.255.255.255',
];
foreach ($blocked as $ip) {
    $host = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
    assert(!$production->isValid('https://' . $host . '/webhook'), $ip);
    // DNS must apply exactly the same byte policy.
    $dns = new WebhookUrlValidator('production', static fn (string $host): array => [$ip]);
    assert(!$dns->isValid('https://n8n.test/webhook'), $ip);
}
foreach (['0x7f000001', '0x7f.0.0.1', '0177.0.0.1', '010.010.010.010',
    '2130706433', '127.1', '127.0.1', '8.8.8', '134744072', '008.008.008.008'] as $host) {
    assert(!$production->isValid('https://' . $host . '/webhook'), $host);
}
foreach (['8.8.8.8', '1.1.1.1', '100.128.0.1', '172.32.0.1', '198.20.0.1',
    '[2606:4700:4700::1111]', '[2001:4860:4860::8888]'] as $host) {
    assert($production->isValid('https://' . $host . '/webhook'), $host);
}
foreach ([[], ['8.8.8.8', '10.0.0.1'], ['8.8.8.8', '::1'], ['bad-ip'],
    ['2606:4700:4700::1111', '169.254.169.254']] as $ips) {
    $dns = new WebhookUrlValidator('production', static fn (string $host): array => $ips);
    assert(!$dns->isValid('https://n8n.test/webhook'));
}
$failure = new WebhookUrlValidator('production', static function (string $host): array {
    throw new RuntimeException('DNS unavailable');
});
assert(!$failure->isValid('https://n8n.test/webhook'));
$dual = new WebhookUrlValidator('production', static fn (string $host): array => ['2606:4700:4700::1111', '8.8.8.8']);
assert($dual->resolve('https://n8n.test:8443/webhook') === [
    'host' => 'n8n.test', 'port' => 8443, 'ip' => '2606:4700:4700::1111', 'literal' => false,
]);
foreach (["https://n8n.test\\@127.0.0.1/x", "https://n8n.test/\nfoo", 'https://n8n.test:0/',
    'https://n8n.test:65536/', 'https://n8n.test../', 'https://[fe80::1%25eth0]/'] as $url) {
    assert(!$production->isValid($url), $url);
}
echo 'WebhookUrlValidatorTest SSRF regressions passed.' . PHP_EOL;
