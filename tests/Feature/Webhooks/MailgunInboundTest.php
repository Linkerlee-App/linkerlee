<?php

use App\Models\Link;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.mailgun.webhook_signing_key', 'test-signing-key');
    config()->set('services.mailgun.inbound_domain', 'in.linkerlee.com');

    Http::fake(function () {
        return Http::response('<html><head><title>Fetched Title</title></head></html>');
    });
});

function mailgunPayload(array $overrides = []): array
{
    $timestamp = (string) time();
    $token = 'test-token-abc';
    $signature = hash_hmac('sha256', $timestamp.$token, config('services.mailgun.webhook_signing_key'));

    return array_merge([
        'timestamp' => $timestamp,
        'token' => $token,
        'signature' => $signature,
        'recipient' => 'save@in.linkerlee.com',
        'sender' => 'someone@example.com',
        'subject' => 'A cool article',
        'body-plain' => 'check out https://laravel.com today',
    ], $overrides);
}

test('rejects requests with an invalid signature', function () {
    $payload = mailgunPayload(['signature' => 'nope']);

    $this->post('/webhooks/mailgun/inbound', $payload)->assertStatus(406);
});

test('rejects stale requests', function () {
    $timestamp = (string) (time() - 600);
    $token = 'stale-token';
    $signature = hash_hmac('sha256', $timestamp.$token, 'test-signing-key');

    $this->post('/webhooks/mailgun/inbound', [
        'timestamp' => $timestamp,
        'token' => $token,
        'signature' => $signature,
        'recipient' => 'save@in.linkerlee.com',
        'sender' => 'someone@example.com',
        'subject' => 'A cool article',
        'body-plain' => 'https://laravel.com',
    ])->assertStatus(406);
});

test('returns 406 when no user matches', function () {
    $this->post('/webhooks/mailgun/inbound', mailgunPayload())->assertStatus(406);
});

test('creates a link via inbox token in the recipient', function () {
    $user = User::factory()->create(['inbox_token' => str_repeat('a', 24)]);

    $payload = mailgunPayload([
        'recipient' => 'inbox-'.$user->inbox_token.'@in.linkerlee.com',
        'sender' => 'somebody-unrelated@example.com',
        'subject' => 'A great post',
        'body-plain' => 'Read this https://laravel.com soon',
    ]);

    $this->post('/webhooks/mailgun/inbound', $payload)->assertOk();

    expect(Link::query()->where('user_id', $user->id)->count())->toBe(1);

    $link = Link::query()->where('user_id', $user->id)->first();
    expect($link->link)->toBe('https://laravel.com');
    expect($link->title)->toBe('A great post');
});

test('falls back to matching by sender email', function () {
    $user = User::factory()->create(['email' => 'me@example.com']);

    $payload = mailgunPayload([
        'recipient' => 'save@in.linkerlee.com',
        'sender' => 'me@example.com',
        'subject' => 'https://laravel.com',
        'body-plain' => 'https://laravel.com',
    ]);

    $this->post('/webhooks/mailgun/inbound', $payload)->assertOk();

    $link = Link::query()->where('user_id', $user->id)->first();
    expect($link)->not->toBeNull();
    expect($link->link)->toBe('https://laravel.com');
    expect($link->title)->toBe('Fetched Title');
});

test('returns 406 when no url is found', function () {
    User::factory()->create(['email' => 'me@example.com']);

    $payload = mailgunPayload([
        'sender' => 'me@example.com',
        'subject' => 'no link',
        'body-plain' => 'nothing here',
    ]);

    $this->post('/webhooks/mailgun/inbound', $payload)->assertStatus(406);
});
