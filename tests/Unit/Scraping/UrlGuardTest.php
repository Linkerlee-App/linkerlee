<?php

use App\Scraping\UrlGuard;

beforeEach(function () {
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

test('uses gethostbynamel when no resolver is set', function () {
    UrlGuard::$resolver = null;

    // localhost always resolves to a loopback address via the real resolver.
    expect(UrlGuard::check('http://localhost:5432'))->not->toBeNull();
});
