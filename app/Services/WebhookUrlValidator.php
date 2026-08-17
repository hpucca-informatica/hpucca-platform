<?php

declare(strict_types=1);

namespace HPucca\Platform\Services;

final readonly class WebhookUrlValidator
{
    public function __construct(
        private string $environment,
    ) {
    }

    public function isValid(string $url): bool
    {
        $parts = parse_url($url);

        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($url === '' || $scheme === '' || $host === '') {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        if ($this->environment === 'production' && $scheme !== 'https') {
            return false;
        }

        return !$this->isBlockedHost($host);
    }

    private function isBlockedHost(string $host): bool
    {
        $normalized = trim($host, "[] \t\n\r\0\x0B.");

        if ($normalized === '' || $normalized === 'localhost') {
            return true;
        }

        if ($this->isAmbiguousIpv4($normalized)) {
            return true;
        }

        $ipv4 = filter_var($normalized, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        if (is_string($ipv4)) {
            return $this->isBlockedIpv4($ipv4);
        }

        $ipv6 = filter_var($normalized, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        if (is_string($ipv6)) {
            return $this->isBlockedIpv6($normalized);
        }

        return str_ends_with($normalized, '.localhost');
    }

    private function isAmbiguousIpv4(string $host): bool
    {
        if (preg_match('/^[0-9.]+$/', $host) !== 1) {
            return false;
        }

        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false;
    }

    private function isBlockedIpv4(string $ip): bool
    {
        $long = ip2long($ip);

        if ($long === false) {
            return true;
        }

        $unsigned = sprintf('%u', $long);
        $value = (int) $unsigned;

        return $this->inRange($value, '0.0.0.0', '0.255.255.255')
            || $this->inRange($value, '10.0.0.0', '10.255.255.255')
            || $this->inRange($value, '127.0.0.0', '127.255.255.255')
            || $this->inRange($value, '169.254.0.0', '169.254.255.255')
            || $this->inRange($value, '172.16.0.0', '172.31.255.255')
            || $this->inRange($value, '192.168.0.0', '192.168.255.255');
    }

    private function isBlockedIpv6(string $ip): bool
    {
        $packed = inet_pton($ip);

        if ($packed === false) {
            return true;
        }

        $bytes = array_values(unpack('C*', $packed) ?: []);

        if ($ip === '::1') {
            return true;
        }

        if ($bytes[0] === 0xfc || $bytes[0] === 0xfd) {
            return true;
        }

        if ($bytes[0] === 0xfe && ($bytes[1] & 0xc0) === 0x80) {
            return true;
        }

        if (str_starts_with($ip, '::ffff:')) {
            $mapped = substr($ip, 7);

            if (filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                return $this->isBlockedIpv4($mapped);
            }

            return true;
        }

        return false;
    }

    private function inRange(int $value, string $start, string $end): bool
    {
        return $value >= (int) sprintf('%u', ip2long($start))
            && $value <= (int) sprintf('%u', ip2long($end));
    }
}
