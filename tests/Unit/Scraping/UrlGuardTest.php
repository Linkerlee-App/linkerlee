<?php

use App\Scraping\UrlGuard;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    // No real DNS lookups in tests: an unset resolver would call gethostbynamel().
    UrlGuard::$resolver = fn (string $host): array|false => ['93.184.216.34'];
});

afterEach(function () {
    UrlGuard::$resolver = null;
});

test('allows a public https host', function () {
    expect(UrlGuard::check('https://example.com/article'))->toBeNull();
});

test('rejects a loopback ip', function () {
    expect(UrlGuard::check('http://127.0.0.1'))->not->toBeNull();
});

test('rejects a private network ip', function () {
    expect(UrlGuard::check('http://10.0.0.5'))->not->toBeNull();
});

test('rejects the cloud metadata link-local address', function () {
    expect(UrlGuard::check('http://169.254.169.254/latest/meta-data'))->not->toBeNull();
});

test('rejects bracketed ipv6 loopback', function () {
    expect(UrlGuard::check('http://[::1]'))->not->toBeNull();
});

test('rejects the file scheme regardless of host resolution', function () {
    expect(UrlGuard::check('file:///etc/passwd'))->not->toBeNull();
});

test('rejects a hostname that resolves only to private addresses, without a real DNS lookup', function () {
    UrlGuard::$resolver = fn (string $host): array|false => ['10.1.2.3'];

    expect(UrlGuard::check('http://internal.example.test'))->not->toBeNull();
});

test('rejects a hostname the resolver cannot resolve at all', function () {
    UrlGuard::$resolver = fn (string $host): array|false => false;

    expect(UrlGuard::check('https://does-not-resolve.example.test'))->not->toBeNull();
});

test('rejects an unsupported scheme like ftp', function () {
    expect(UrlGuard::check('ftp://example.com/file'))->not->toBeNull();
});

test('rejects localhost, resolved via a stubbed resolver rather than a real DNS lookup', function () {
    UrlGuard::$resolver = fn (string $host): array|false => $host === 'localhost' ? ['127.0.0.1'] : false;

    expect(UrlGuard::check('http://localhost:5432'))->not->toBeNull();
});

test('rejects the carrier-grade nat range, which hosts the alibaba cloud metadata service', function () {
    expect(UrlGuard::check('http://100.100.100.200/latest/meta-data'))->not->toBeNull()
        ->and(UrlGuard::check('http://100.64.0.1'))->not->toBeNull()
        ->and(UrlGuard::check('http://100.127.255.254'))->not->toBeNull();
});

test('allows public addresses just outside the carrier-grade nat range', function () {
    expect(UrlGuard::check('http://100.63.255.255'))->toBeNull()
        ->and(UrlGuard::check('http://100.128.0.1'))->toBeNull();
});

test('rejects a hostname that resolves into the carrier-grade nat range', function () {
    UrlGuard::$resolver = fn (string $host): array|false => ['100.100.100.200'];

    expect(UrlGuard::check('http://metadata.example.test'))->not->toBeNull();
});

test('rejects nat64 addresses that embed a private ipv4 address', function () {
    expect(UrlGuard::check('http://[64:ff9b::a9fe:a9fe]'))->not->toBeNull()
        ->and(UrlGuard::check('http://[64:ff9b::7f00:1]'))->not->toBeNull();
});

test('allows a public ipv6 address', function () {
    expect(UrlGuard::check('http://[2606:4700:4700::1111]'))->toBeNull();
});

test('gives one generic message for unresolvable and private hosts, so it cannot be used to probe internal dns', function () {
    UrlGuard::$resolver = fn (string $host): array|false => false;
    $unresolvable = UrlGuard::check('https://does-not-resolve.example.test');

    UrlGuard::$resolver = fn (string $host): array|false => ['10.1.2.3'];
    $private = UrlGuard::check('https://internal.example.test');

    $literal = UrlGuard::check('http://127.0.0.1');

    expect($unresolvable)->toBe(UrlGuard::HOST_NOT_ALLOWED)
        ->and($private)->toBe(UrlGuard::HOST_NOT_ALLOWED)
        ->and($literal)->toBe(UrlGuard::HOST_NOT_ALLOWED)
        ->and(UrlGuard::HOST_NOT_ALLOWED)->not->toContain('example.test');
});

test('rejects an ipv4-mapped ipv6 literal of the cloud metadata address', function () {
    expect(UrlGuard::check('http://[::ffff:169.254.169.254]'))->not->toBeNull();
});
