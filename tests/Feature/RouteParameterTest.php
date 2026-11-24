<?php

use App\Models\User;

test('non-numeric model ids are not found rather than a server error', function (string $method, string $uri) {
    $this->actingAs(User::factory()->create())
        ->json($method, $uri)
        ->assertNotFound();
})->with([
    'link' => ['GET', '/links/abc'],
    'group' => ['GET', '/groups/abc'],
    'tag' => ['PUT', '/tags/abc'],
    'public link' => ['DELETE', '/publicLinks/abc'],
    'api token' => ['DELETE', '/settings/api-tokens/abc'],
    'id beyond bigint' => ['GET', '/links/99999999999999999999'],
]);

test('non-numeric API link ids are not found rather than a server error', function () {
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->deleteJson('/api/links/abc')
        ->assertNotFound();
});
