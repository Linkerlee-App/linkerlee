<?php

use App\Support\InboundEmailParser;

test('extracts the first url from the body', function () {
    $url = InboundEmailParser::extractUrl('Check this out https://laravel.com it is cool', 'Subject');

    expect($url)->toBe('https://laravel.com');
});

test('falls back to subject when body has no url', function () {
    $url = InboundEmailParser::extractUrl('no link here', 'https://example.com/page');

    expect($url)->toBe('https://example.com/page');
});

test('returns null when neither source contains a url', function () {
    expect(InboundEmailParser::extractUrl('hello', 'world'))->toBeNull();
});

test('strips trailing punctuation from extracted url', function () {
    $url = InboundEmailParser::extractUrl('see https://example.com/page.', null);

    expect($url)->toBe('https://example.com/page');
});

test('returns subject as title and strips reply or forward prefix', function () {
    expect(InboundEmailParser::extractTitle('Fwd: Hello World', 'https://example.com'))->toBe('Hello World');
    expect(InboundEmailParser::extractTitle('Re: a thread', 'https://example.com'))->toBe('a thread');
});

test('returns null title when subject equals the url', function () {
    expect(InboundEmailParser::extractTitle('https://example.com', 'https://example.com'))->toBeNull();
});

test('returns null title when subject is empty', function () {
    expect(InboundEmailParser::extractTitle('', 'https://example.com'))->toBeNull();
    expect(InboundEmailParser::extractTitle(null, 'https://example.com'))->toBeNull();
});
