<?php

declare(strict_types=1);

namespace HPucca\Platform\Services {
    function dns_get_record(string $host, int $type): array|false
    {
        $GLOBALS['dnsQueries'][] = [$host, $type];
        return $GLOBALS['dnsRecords'][$type];
    }
}
namespace {
    use HPucca\Platform\Services\WebhookUrlValidator;
    require dirname(__DIR__) . '/vendor/autoload.php';
    $validator = new WebhookUrlValidator('production');
    $a = [['type' => 'A', 'ip' => '8.8.8.8']];
    $aaaa = [['type' => 'AAAA', 'ipv6' => '2606:4700:4700::1111']];
    foreach ([
        [$a, $aaaa, true], [$a, [], true], [[], $aaaa, true], [[], [], false],
        [false, $aaaa, false], [$a, false, false],
        [$a, [['type' => 'AAAA', 'ipv6' => '::1']], false],
        [[['type' => 'A', 'ip' => '10.0.0.1']], $aaaa, false],
        [[['type' => 'CNAME', 'target' => 'private.test']], [], false],
    ] as [$v4, $v6, $expected]) {
        $GLOBALS['dnsRecords'] = [DNS_A => $v4, DNS_AAAA => $v6];
        $GLOBALS['dnsQueries'] = [];
        assert($validator->isValid('https://n8n.test/webhook') === $expected);
        assert($GLOBALS['dnsQueries'][0] === ['n8n.test', DNS_A]);
        if ($v4 !== false) {
            assert($GLOBALS['dnsQueries'][1] === ['n8n.test', DNS_AAAA]);
        }
    }
    echo 'WebhookDnsResolutionTest passed.' . PHP_EOL;
}
