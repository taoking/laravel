<?php

namespace App\Support\Security;

class UrlSafetyInspector
{
    /**
     * @return array{allowed: bool, reason: string, host: string|null, resolved_ips: list<string>}
     */
    public function inspect(string $url): array
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            return $this->blocked('invalid_url');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return $this->blocked('unsupported_scheme', $host);
        }

        if ($host === '') {
            return $this->blocked('missing_host');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return $this->blocked('credentials_not_allowed', $host);
        }

        if ($this->isLocalHostname($host)) {
            return $this->blocked('local_hostname_blocked', $host);
        }

        if (isset($parts['port']) && ! in_array((int) $parts['port'], [80, 443], true)) {
            return $this->blocked('non_standard_port_blocked', $host);
        }

        $ips = $this->resolveIps($host);

        if ($ips === []) {
            return $this->blocked('host_cannot_be_resolved', $host);
        }

        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                return $this->blocked('private_or_reserved_ip_blocked', $host, $ips);
            }
        }

        return [
            'allowed' => true,
            'reason' => 'allowed',
            'host' => $host,
            'resolved_ips' => $ips,
        ];
    }

    private function isLocalHostname(string $host): bool
    {
        return $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local');
    }

    /**
     * @return list<string>
     */
    private function resolveIps(string $host): array
    {
        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = gethostbynamel($host);

        if ($records === false) {
            return [];
        }

        return array_values(array_unique($records));
    }

    private function isPublicIp(string $ip): bool
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );
    }

    /**
     * @param  list<string>  $ips
     * @return array{allowed: bool, reason: string, host: string|null, resolved_ips: list<string>}
     */
    private function blocked(string $reason, ?string $host = null, array $ips = []): array
    {
        return [
            'allowed' => false,
            'reason' => $reason,
            'host' => $host,
            'resolved_ips' => $ips,
        ];
    }
}
