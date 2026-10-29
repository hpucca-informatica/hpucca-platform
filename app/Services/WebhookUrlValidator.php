<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

use Closure;
use Throwable;

final readonly class WebhookUrlValidator
{
    /** @param Closure(string): list<string>|null $dnsResolver */
    public function __construct(private string $environment, private ?Closure $dnsResolver = null)
    {
    }

    public function isValid(string $url): bool
    {
        return $this->resolve($url) !== null;
    }

    /** @return array{host: string, port: int, ip: string, literal: bool}|null */
    public function resolve(string $url): ?array
    {
        // Reject parser ambiguities before handing the same URL to libcurl.
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            return null;
        }
        try {
            $parts = parse_url($url);
        } catch (Throwable) {
            return null;
        }
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if (!in_array($scheme, ['http', 'https'], true)
            || ($this->environment === 'production' && $scheme !== 'https')
            || $host === '' || $port < 1 || $port > 65535) {
            return null;
        }

        $ip = str_starts_with($host, '[') && str_ends_with($host, ']') ? substr($host, 1, -1) : $host;
        if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            return $this->isPublicIp($ip) ? ['host' => $host, 'port' => $port, 'ip' => $ip, 'literal' => true] : null;
        }

        // Only ASCII DNS names. Numeric/hex/octal/short forms must never reach cURL's parser.
        $name = rtrim($host, '.');
        if (strlen($name) > 253 || str_ends_with($host, '..')
            || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $name)
            || preg_match('/^(?:0x[0-9a-f]+|[0-9]+)(?:\.(?:0x[0-9a-f]+|[0-9]+))*$/iD', $name)
            || $name === 'localhost' || str_ends_with($name, '.localhost')) {
            return null;
        }

        try {
            $addresses = $this->dnsResolver !== null ? ($this->dnsResolver)($host) : $this->lookup($host);
        } catch (Throwable) {
            return null;
        }
        if (!is_array($addresses) || $addresses === []) {
            return null;
        }
        foreach ($addresses as $address) {
            if (!is_string($address) || !$this->isPublicIp($address)) {
                return null;
            }
        }
        return ['host' => $host, 'port' => $port, 'ip' => $addresses[0], 'literal' => false];
    }

    /** @return list<string>|null */
    private function lookup(string $host): ?array
    {
        $addresses = [];
        // Query both families separately: failure of either query is fail-closed.
        foreach ([DNS_A, DNS_AAAA] as $type) {
            $records = @dns_get_record($host, $type);
            if ($records === false) {
                return null;
            }
            foreach ($records as $record) {
                if (($record['type'] ?? '') === 'A') {
                    $addresses[] = $record['ip'] ?? '';
                } elseif (($record['type'] ?? '') === 'AAAA') {
                    $addresses[] = $record['ipv6'] ?? '';
                }
            }
        }
        return array_values(array_unique($addresses));
    }

    private function isPublicIp(string $ip): bool
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }
        if (strlen($packed) === 4) {
            foreach (['0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8',
                '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24',
                '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24',
                '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4'] as $range) {
                if ($this->inPrefix($packed, $range)) {
                    return false;
                }
            }
            return true;
        }
        // Allow only global unicast; excludes mapped IPv4, NAT64, ULA, link-local,
        // multicast, unspecified, loopback and unallocated space by construction.
        if (!$this->inPrefix($packed, '2000::/3')) {
            return false;
        }
        foreach (['2001::/23', '2001:db8::/32', '2002::/16', '3fff::/20'] as $range) {
            if ($this->inPrefix($packed, $range)) {
                return false;
            }
        }
        return true;
    }

    private function inPrefix(string $packed, string $cidr): bool
    {
        [$network, $bits] = explode('/', $cidr);
        $prefix = inet_pton($network);
        $whole = intdiv((int) $bits, 8);
        $remaining = (int) $bits % 8;
        return substr($packed, 0, $whole) === substr($prefix, 0, $whole)
            && ($remaining === 0 || ((ord($packed[$whole]) ^ ord($prefix[$whole])) & (0xff << (8 - $remaining))) === 0);
    }
}
