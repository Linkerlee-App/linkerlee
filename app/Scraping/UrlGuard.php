<?php

namespace App\Scraping;

use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Keeps extraction drivers from being turned into an SSRF probe.
 *
 * A saved link's URL is attacker-controlled: it can point at the metadata
 * service of a cloud provider, at a service on localhost, or anywhere else
 * on the private network the app happens to run in. Every driver must call
 * {@see self::check()} before making a request, and refuse the URL when it
 * returns anything other than null.
 *
 * The check resolves A records only, so a driver that connects itself must
 * pin the connection to IPv4 too (Guzzle's `force_ip_resolve => v4`);
 * otherwise a host with a public A record and an AAAA record of `::1` would
 * pass here and then be reached over IPv6.
 *
 * Accepted residual risk: DNS rebinding. The host is resolved once here and
 * again by the HTTP client when it connects, and a hostile DNS server can
 * answer the two lookups differently. Closing that needs the client to
 * connect to the exact IP checked here, which is not done yet.
 */
final class UrlGuard
{
    /**
     * The one message returned for a host that is unresolvable or resolves to
     * a non-public address. The owner sees it, so it deliberately does not say
     * which, or it would tell them what exists on the internal network.
     */
    public const HOST_NOT_ALLOWED = "The URL's host is not allowed.";

    /**
     * Non-public ranges that PHP's FILTER_FLAG_NO_PRIV_RANGE and
     * FILTER_FLAG_NO_RES_RANGE do not cover: carrier-grade NAT, where Alibaba
     * Cloud serves its metadata (100.100.100.200), and the NAT64 prefix,
     * which can embed any IPv4 address, private ones included.
     */
    private const EXTRA_DENIED_RANGES = ['100.64.0.0/10', '64:ff9b::/96'];

    /**
     * Resolves a hostname to its IPs. Overridden in tests to avoid real DNS
     * lookups; left null in production, which falls back to gethostbynamel().
     *
     * @var (\Closure(string): (array<int, string>|false))|null
     */
    public static ?\Closure $resolver = null;

    /**
     * @return string|null an error message, or null when the URL is allowed
     */
    public static function check(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'])) {
            return "The URL \"{$url}\" could not be parsed.";
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return "The scheme \"{$scheme}\" is not allowed.";
        }

        if (! isset($parts['host']) || $parts['host'] === '') {
            return "The URL \"{$url}\" has no host.";
        }

        $host = trim($parts['host'], '[]');

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::isPublicIp($host) ? null : self::HOST_NOT_ALLOWED;
        }

        $ips = self::resolve($host);

        if ($ips === false || $ips === []) {
            return self::HOST_NOT_ALLOWED;
        }

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                return self::HOST_NOT_ALLOWED;
            }
        }

        return null;
    }

    private static function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            && ! IpUtils::checkIp($ip, self::EXTRA_DENIED_RANGES);
    }

    /**
     * @return array<int, string>|false
     */
    private static function resolve(string $host): array|false
    {
        if (self::$resolver !== null) {
            return (self::$resolver)($host);
        }

        return gethostbynamel($host);
    }
}
