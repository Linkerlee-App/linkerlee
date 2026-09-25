<?php

namespace App\Scraping;

/**
 * Keeps extraction drivers from being turned into an SSRF probe.
 *
 * A saved link's URL is attacker-controlled: it can point at the metadata
 * service of a cloud provider, at a service on localhost, or anywhere else
 * on the private network the app happens to run in. Every driver must call
 * {@see self::check()} before making a request, and refuse the URL when it
 * returns anything other than null.
 */
final class UrlGuard
{
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
            return self::isPublicIp($host)
                ? null
                : "The host \"{$host}\" is a private, loopback or link-local address.";
        }

        $ips = self::resolve($host);

        if ($ips === false || $ips === []) {
            return "The host \"{$host}\" could not be resolved.";
        }

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                return "The host \"{$host}\" resolves to a private, loopback or link-local address.";
            }
        }

        return null;
    }

    private static function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
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
