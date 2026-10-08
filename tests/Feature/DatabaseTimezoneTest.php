<?php

use App\Models\Link;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

/**
 * config('database.connections.pgsql.timezone') pins the Postgres session to
 * UTC. Without it, the session inherits the server's own default (locally,
 * Europe/Bucharest) and every `timestampTz` value silently skews: Laravel
 * writes a naive "Y-m-d H:i:s" string with no offset, so Postgres stores it
 * as that wall-clock time in the session timezone rather than in UTC.
 */
test('the postgres session timezone is pinned to UTC', function () {
    expect(DB::selectOne('show timezone')->TimeZone)->toBe('UTC');
});

test('a naive timestamp written as timestamptz reads back at the exact instant it was written', function () {
    $this->travelTo(now()->startOfSecond());

    $writtenAt = now();

    $readBack = DB::selectOne('select ?::timestamptz as instant', [$writtenAt->format('Y-m-d H:i:s')])->instant;

    expect(Carbon::parse($readBack)->equalTo($writtenAt))->toBeTrue();
});

test('a timestampTz column round-trips through the database at the exact instant it was written', function () {
    $this->travelTo(now()->startOfSecond());

    $writtenAt = now();

    $link = Link::factory()->create(['user_id' => User::factory()]);
    $link->forceFill(['next_check_at' => $writtenAt])->save();

    $fresh = Link::query()->find($link->id);

    expect($fresh->next_check_at->equalTo($writtenAt))->toBeTrue();
});
