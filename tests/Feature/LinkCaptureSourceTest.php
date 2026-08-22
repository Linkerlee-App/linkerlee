<?php

use App\Enums\LinkSource;
use App\Models\Link;
use App\Models\User;
use App\Services\ImportService;
use Illuminate\Support\Facades\Http;

test('a link saved through the web app records the web source', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/links', [
            'link' => 'https://laravel.com',
            'title' => 'Laravel',
            'groups' => [],
            'tags' => [],
        ])
        ->assertRedirect();

    expect(Link::where('user_id', $user->id)->first()->source)->toBe(LinkSource::Web);
});

test('an API client with no telling origin records the api source', function () {
    $user = User::factory()->create();
    $token = $user->createToken('script', ['create'])->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/links', ['link' => 'https://laravel.com'])
        ->assertOk();

    expect(Link::where('user_id', $user->id)->first()->source)->toBe(LinkSource::Api);
});

test('a browser extension is recognised by its origin without declaring itself', function (string $origin) {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create'])->plainTextToken;

    $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Origin' => $origin,
    ])
        ->postJson('/api/links', ['link' => 'https://laravel.com'])
        ->assertOk();

    expect(Link::where('user_id', $user->id)->first()->source)->toBe(LinkSource::Extension);
})->with([
    'chrome' => 'chrome-extension://abcdefghijklmnopabcdefghijklmnop',
    'firefox' => 'moz-extension://11111111-2222-3333-4444-555555555555',
    'safari' => 'safari-web-extension://11111111-2222-3333-4444-555555555555',
]);

test('an API client may declare itself the extension', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create'])->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/links', [
            'link' => 'https://laravel.com',
            'source' => 'extension',
        ])
        ->assertOk();

    expect(Link::where('user_id', $user->id)->first()->source)->toBe(LinkSource::Extension);
});

test('an empty source falls back to the automatic decision', function () {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create'])->plainTextToken;

    $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Origin' => 'chrome-extension://abcdefghijklmnopabcdefghijklmnop',
    ])
        ->postJson('/api/links', [
            'link' => 'https://laravel.com',
            'source' => '',
        ])
        ->assertOk();

    expect(Link::where('user_id', $user->id)->first()->source)->toBe(LinkSource::Extension);
});

test('an API client cannot claim a source it could not have come from', function (string $claimed) {
    $user = User::factory()->create();
    $token = $user->createToken('ext', ['create'])->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/links', [
            'link' => 'https://laravel.com',
            'source' => $claimed,
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect(Link::where('user_id', $user->id)->count())->toBe(0);
})->with(['email', 'import', 'web', 'nonsense']);

test('a link saved by email records the email source', function () {
    config()->set('services.mailgun.webhook_signing_key', 'test-signing-key');
    config()->set('services.mailgun.inbound_domain', 'mg.linkerlee.com');

    Http::fake(fn () => Http::response('<html><head><title>Fetched</title></head></html>'));

    $user = User::factory()->create();

    $timestamp = (string) time();
    $token = 'test-token-abc';

    $this->post('/webhooks/mailgun/inbound', [
        'timestamp' => $timestamp,
        'token' => $token,
        'signature' => hash_hmac('sha256', $timestamp.$token, 'test-signing-key'),
        'recipient' => 'save@mg.linkerlee.com',
        'sender' => $user->email,
        'subject' => 'A cool article',
        'body-plain' => 'check out https://laravel.com today',
    ])->assertOk();

    expect(Link::where('user_id', $user->id)->first()->source)->toBe(LinkSource::Email);
});

test('an imported link records the import source', function () {
    $user = User::factory()->create();

    app(ImportService::class)->importUserData(
        ['links' => [['link' => 'https://laravel.com', 'title' => 'Laravel']]],
        ['links'],
        $user,
    );

    expect(Link::where('user_id', $user->id)->first()->source)->toBe(LinkSource::Import);
});

test('links saved before the source was recorded stay unknown', function () {
    $link = Link::factory()->create(['user_id' => User::factory()->create()->id]);

    expect($link->fresh()->source)->toBeNull();
});
