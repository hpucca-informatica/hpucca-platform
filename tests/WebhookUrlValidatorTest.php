<?php

declare(strict_types=1);

use HPucca\Platform\Services\WebhookUrlValidator;

require dirname(__DIR__) . '/vendor/autoload.php';

$production = new WebhookUrlValidator('production');

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

$local = new WebhookUrlValidator('local');
assert($local->isValid('http://n8n.example.com/webhook'));
assert(!$local->isValid('http://localhost/webhook'));

echo 'WebhookUrlValidatorTest passed.' . PHP_EOL;
