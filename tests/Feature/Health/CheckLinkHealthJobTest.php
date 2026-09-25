<?php

use App\Enums\ExtractionStatus;
use App\Enums\HealthStatus;
use App\Events\LinkSnapshotCreated;
use App\Health\CheckSchedule;
use App\Health\HealthClassifier;
use App\Jobs\CheckLinkHealthJob;
use App\Jobs\ExtractContentJob;
use App\Models\Link;
use App\Models\LinkSnapshot;
use App\Models\User;
use App\Scraping\Drivers\FakeExtractor;
use App\Scraping\ExtractionResult;
use App\Scraping\ScrapingManager;
use App\Scraping\SnapshotRecorder;
use App\Scraping\UrlGuard;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::preventStrayRequests();
    FakeExtractor::reset();

    UrlGuard::$resolver = fn (string $host): array => ['93.184.216.34'];

    $this->travelTo(now()->startOfSecond());

    $this->link = Link::factory()->create([
        'user_id' => User::factory(),
        'link' => 'https://example.com/article',
    ]);
});

afterEach(function () {
    UrlGuard::$resolver = null;
    FakeExtractor::reset();
});

/**
 * Runs the job's handle() the way the worker would, resolving its
 * dependencies from the container.
 */
function runHealthCheck(Link $link): void
{
    (new CheckLinkHealthJob($link))->handle(
        app(ScrapingManager::class),
        app(SnapshotRecorder::class),
        app(HealthClassifier::class),
        app(CheckSchedule::class),
    );
}

/**
 * Article text of the given length whose words all differ from the ones
 * produced for any other seed, so two seeds never look like noise.
 */
function healthArticle(string $seed, int $words = 300): string
{
    return implode(' ', array_map(fn (int $i): string => "{$seed}{$i}", range(1, $words)));
}

/**
 * Gives the link a stored snapshot of the given text and a clean health
 * state, as if it had been extracted and checked before.
 */
function givenSnapshot(Link $link, string $text, array $attributes = []): Link
{
    app(SnapshotRecorder::class)->record($link, ExtractionResult::ok('fake', $text), $link->link);

    $link->forceFill([
        'health_status' => HealthStatus::Ok,
        'check_interval_days' => 7,
        'consecutive_failures' => 0,
        ...$attributes,
    ])->save();

    return $link->fresh();
}

test('the job is unique per link until processing and runs once on the health queue', function () {
    $job = new CheckLinkHealthJob($this->link);

    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job)->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class)
        ->and($job->uniqueFor)->toBe(3600)
        ->and($job->uniqueId())->toBe((string) $this->link->id)
        ->and($job->queue)->toBe('health')
        ->and($job->tries)->toBe(1)
        ->and($job->timeout)->toBe(60)
        ->and($job->deleteWhenMissingModels)->toBeTrue();
});

test('a 200 with the same content hash is ok and unchanged, and the interval doubles', function () {
    $link = givenSnapshot($this->link, healthArticle('same'));
    Http::fake(['https://example.com/article' => Http::response('', 200)]);
    FakeExtractor::respondWith('https://example.com/article', ExtractionResult::ok('fake', healthArticle('same')));

    runHealthCheck($link);
    $link->refresh();

    expect($link->health_status)->toBe(HealthStatus::Ok)
        ->and($link->consecutive_failures)->toBe(0)
        ->and($link->check_interval_days)->toBe(14)
        ->and($link->next_check_at->equalTo(now()->addDays(14)))->toBeTrue()
        ->and($link->last_checked_at->equalTo(now()))->toBeTrue()
        ->and($link->content_changed_at)->toBeNull()
        ->and(LinkSnapshot::query()->count())->toBe(1);
});

test('a 200 with only a noisy diff is unchanged and stores no snapshot', function () {
    $words = explode(' ', healthArticle('noise'));
    $link = givenSnapshot($this->link, implode(' ', $words));
    $latestId = $link->latest_snapshot_id;

    $words[299] = 'updated-view-counter';
    Http::fake(['https://example.com/article' => Http::response('', 200)]);
    FakeExtractor::respondWith('https://example.com/article', ExtractionResult::ok('fake', implode(' ', $words)));

    runHealthCheck($link);
    $link->refresh();

    expect($link->health_status)->toBe(HealthStatus::Ok)
        ->and($link->check_interval_days)->toBe(14)
        ->and($link->latest_snapshot_id)->toBe($latestId)
        ->and($link->content_changed_at)->toBeNull()
        ->and(LinkSnapshot::query()->count())->toBe(1);
});

test('a 200 with a real change stores a snapshot, stamps content_changed_at and resets the interval', function () {
    $link = givenSnapshot($this->link, healthArticle('before'), ['check_interval_days' => 56]);
    Event::fake([LinkSnapshotCreated::class]);
    Http::fake(['https://example.com/article' => Http::response('', 200)]);
    FakeExtractor::respondWith('https://example.com/article', ExtractionResult::ok('fake', healthArticle('after')));

    runHealthCheck($link);
    $link->refresh();

    expect($link->health_status)->toBe(HealthStatus::Ok)
        ->and($link->latestSnapshot->content_text)->toBe(healthArticle('after'))
        ->and($link->content_changed_at->equalTo(now()))->toBeTrue()
        ->and($link->check_interval_days)->toBe(7)
        ->and($link->next_check_at->equalTo(now()->addDays(7)))->toBeTrue()
        ->and(LinkSnapshot::query()->count())->toBe(2);

    Event::assertDispatched(LinkSnapshotCreated::class);
});

test('a 304 is unchanged without calling the extractor', function () {
    $link = givenSnapshot($this->link, healthArticle('cached'), [
        'consecutive_failures' => 2,
        'etag' => '"v1"',
        'last_modified' => 'Fri, 15 Mar 2024 09:00:00 GMT',
    ]);
    FakeExtractor::reset();
    Http::fake(['https://example.com/article' => Http::response('', 304)]);

    runHealthCheck($link);
    $link->refresh();

    expect(FakeExtractor::$calls)->toBe([])
        ->and($link->health_status)->toBe(HealthStatus::Ok)
        ->and($link->consecutive_failures)->toBe(0)
        ->and($link->check_interval_days)->toBe(14)
        ->and($link->last_checked_at->equalTo(now()))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->header('If-None-Match') === ['"v1"']
        && $request->header('If-Modified-Since') === ['Fri, 15 Mar 2024 09:00:00 GMT']);
});

test('a 304 on a never-checked link marks it ok', function () {
    Http::fake(['https://example.com/article' => Http::response('', 304)]);

    runHealthCheck($this->link);

    expect($this->link->fresh()->health_status)->toBe(HealthStatus::Ok);
});

test('a 304 on a gone link is a success: ok, failures reset and the stale redirect_url cleared', function () {
    $link = givenSnapshot($this->link, healthArticle('back'), [
        'health_status' => HealthStatus::Gone,
        'consecutive_failures' => 4,
        'redirect_url' => 'https://stale.test/old',
        'etag' => '"v1"',
    ]);
    FakeExtractor::reset();
    Http::fake(['https://example.com/article' => Http::response('', 304)]);

    runHealthCheck($link);
    $link->refresh();

    expect(FakeExtractor::$calls)->toBe([])
        ->and($link->health_status)->toBe(HealthStatus::Ok)
        ->and($link->consecutive_failures)->toBe(0)
        ->and($link->redirect_url)->toBeNull()
        ->and($link->check_interval_days)->toBe(14)
        ->and($link->next_check_at->equalTo(now()->addDays(14)))->toBeTrue();
});

test('a permanent redirect chain ending in a 304 is redirected and stores redirect_url', function () {
    $link = givenSnapshot($this->link, healthArticle('moved'), [
        'health_status' => HealthStatus::Error,
        'consecutive_failures' => 3,
        'etag' => '"v1"',
    ]);
    FakeExtractor::reset();
    Http::fake([
        'https://example.com/article' => Http::response('', 301, ['Location' => 'https://new-home.test/article']),
        'https://new-home.test/article' => Http::response('', 304),
    ]);

    runHealthCheck($link);
    $link->refresh();

    expect(FakeExtractor::$calls)->toBe([])
        ->and($link->health_status)->toBe(HealthStatus::Redirected)
        ->and($link->redirect_url)->toBe('https://new-home.test/article')
        ->and($link->consecutive_failures)->toBe(0)
        ->and($link->check_interval_days)->toBe(14);
});

test('the conditional headers are only sent when the link has validators', function () {
    Http::fake(['https://example.com/article' => Http::response('', 200)]);

    runHealthCheck($this->link);

    Http::assertSent(fn (Request $request): bool => ! $request->hasHeader('If-None-Match')
        && ! $request->hasHeader('If-Modified-Since')
        && $request->header('User-Agent') === [config('scraping.user_agent')]);
});

test('a 301 chain to another host is redirected, stores redirect_url and extracts from the final url', function () {
    $link = givenSnapshot($this->link, healthArticle('old-home'));
    Http::fake([
        'https://example.com/article' => Http::response('', 301, ['Location' => 'https://www.example.com/article']),
        'https://www.example.com/article' => Http::response('', 308, ['Location' => 'https://new-home.test/article']),
        'https://new-home.test/article' => Http::response('', 200),
    ]);
    FakeExtractor::respondWith('https://new-home.test/*', ExtractionResult::ok('fake', healthArticle('new-home')));

    runHealthCheck($link);
    $link->refresh();

    expect($link->health_status)->toBe(HealthStatus::Redirected)
        ->and($link->redirect_url)->toBe('https://new-home.test/article')
        ->and($link->link)->toBe('https://example.com/article')
        ->and(FakeExtractor::$calls)->toBe(['https://new-home.test/article'])
        ->and($link->latestSnapshot->content_text)->toBe(healthArticle('new-home'))
        ->and(LinkSnapshot::query()->count())->toBe(2);
});

test('a redirected link with changed content is recorded rather than superseded', function () {
    $link = givenSnapshot($this->link, healthArticle('first'));
    Queue::fake();
    Http::fake([
        'https://example.com/article' => Http::response('', 301, ['Location' => 'https://moved.test/article']),
        'https://moved.test/article' => Http::response('', 200),
    ]);
    FakeExtractor::respondWith('https://moved.test/*', ExtractionResult::ok('fake', healthArticle('second')));

    runHealthCheck($link);
    $link->refresh();

    expect($link->latestSnapshot->content_text)->toBe(healthArticle('second'))
        ->and($link->content_changed_at->equalTo(now()))->toBeTrue()
        ->and($link->check_interval_days)->toBe(7);

    Queue::assertNotPushed(ExtractContentJob::class);
});

test('a relative Location is resolved against the current url', function () {
    Http::fake([
        'https://example.com/article' => Http::response('', 301, ['Location' => '/moved-here']),
        'https://example.com/moved-here' => Http::response('', 200),
    ]);

    runHealthCheck($this->link);

    expect($this->link->fresh()->health_status)->toBe(HealthStatus::Ok)
        ->and(FakeExtractor::$calls)->toBe(['https://example.com/moved-here']);
});

test('a 302 to another host stays ok', function () {
    Http::fake([
        'https://example.com/article' => Http::response('', 302, ['Location' => 'https://elsewhere.test/login']),
        'https://elsewhere.test/login' => Http::response('', 200),
    ]);

    runHealthCheck($this->link);
    $link = $this->link->fresh();

    expect($link->health_status)->toBe(HealthStatus::Ok)
        ->and($link->redirect_url)->toBeNull();
});

test('a redirect hop to a private address is never requested and counts as an error', function () {
    Http::fake([
        'https://example.com/article' => Http::response('', 302, ['Location' => 'http://10.0.0.5/internal']),
    ]);

    runHealthCheck($this->link);
    $link = $this->link->fresh();

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '10.0.0.5'));

    expect($link->consecutive_failures)->toBe(1)
        ->and(FakeExtractor::$calls)->toBe([]);
});

test('a link whose own url is private is never requested', function () {
    $this->link->forceFill(['link' => 'http://169.254.169.254/latest/meta-data'])->save();
    Http::fake();

    runHealthCheck($this->link);

    Http::assertNothingSent();

    expect($this->link->fresh()->consecutive_failures)->toBe(1);
});

test('more than five redirect hops is an error', function () {
    $responses = [];

    foreach (range(0, 5) as $hop) {
        $responses["https://example.com/hop{$hop}"] = Http::response('', 302, ['Location' => 'https://example.com/hop'.($hop + 1)]);
    }

    $this->link->forceFill(['link' => 'https://example.com/hop0'])->save();
    Http::fake($responses);

    runHealthCheck($this->link);

    Http::assertSentCount(6);

    expect($this->link->fresh()->consecutive_failures)->toBe(1)
        ->and(FakeExtractor::$calls)->toBe([]);
});

test('one 404 leaves the status ok, counts one failure and retries in a day', function () {
    $link = givenSnapshot($this->link, healthArticle('page'));
    Http::fake(['https://example.com/article' => Http::response('', 404)]);

    runHealthCheck($link);
    $link->refresh();

    expect($link->health_status)->toBe(HealthStatus::Ok)
        ->and($link->consecutive_failures)->toBe(1)
        ->and($link->check_interval_days)->toBe(7)
        ->and($link->next_check_at->equalTo(now()->addDay()))->toBeTrue()
        ->and($link->last_checked_at->equalTo(now()))->toBeTrue();
});

test('a third consecutive 404 marks the link gone and schedules it at the max interval', function () {
    $link = givenSnapshot($this->link, healthArticle('page'));
    Http::fake(['https://example.com/article' => Http::response('', 404)]);

    runHealthCheck($link);
    runHealthCheck($link->fresh());
    runHealthCheck($link->fresh());
    $link->refresh();

    expect($link->health_status)->toBe(HealthStatus::Gone)
        ->and($link->consecutive_failures)->toBe(3)
        ->and($link->next_check_at->equalTo(now()->addDays(90)))->toBeTrue();
});

test('404, 404 then 200 resets the failure count', function () {
    $link = givenSnapshot($this->link, healthArticle('flaky'));
    Http::fake([
        'https://example.com/article' => Http::sequence()
            ->push('', 404)
            ->push('', 404)
            ->push('', 200),
    ]);
    FakeExtractor::respondWith('https://example.com/article', ExtractionResult::ok('fake', healthArticle('flaky')));

    runHealthCheck($link);
    runHealthCheck($link->fresh());

    expect($link->fresh()->consecutive_failures)->toBe(2);

    runHealthCheck($link->fresh());
    $link->refresh();

    expect($link->health_status)->toBe(HealthStatus::Ok)
        ->and($link->consecutive_failures)->toBe(0);
});

test('three 503s mark the link error and keep retrying daily', function () {
    Http::fake(['https://example.com/article' => Http::response('', 503)]);

    runHealthCheck($this->link);
    runHealthCheck($this->link->fresh());
    runHealthCheck($this->link->fresh());
    $link = $this->link->fresh();

    expect($link->health_status)->toBe(HealthStatus::Error)
        ->and($link->consecutive_failures)->toBe(3)
        ->and($link->next_check_at->equalTo(now()->addDay()))->toBeTrue();
});

test('a connection failure counts as an error', function () {
    Http::fake(['https://example.com/article' => Http::failedConnection()]);

    runHealthCheck($this->link);
    $link = $this->link->fresh();

    expect($link->consecutive_failures)->toBe(1)
        ->and($link->health_status)->toBeNull()
        ->and($link->last_checked_at->equalTo(now()))->toBeTrue();
});

test('a failed extraction after a 200 counts as an error and touches no snapshot', function () {
    $link = givenSnapshot($this->link, healthArticle('kept'));
    $latestId = $link->latest_snapshot_id;
    Http::fake(['https://example.com/article' => Http::response('', 200)]);
    FakeExtractor::respondWith('https://example.com/article', ExtractionResult::failure(ExtractionStatus::Failed, 'fake', 'Unable to extract'));

    runHealthCheck($link);
    $link->refresh();

    expect($link->consecutive_failures)->toBe(1)
        ->and($link->health_status)->toBe(HealthStatus::Ok)
        ->and($link->extraction_status)->toBe(ExtractionStatus::Ok)
        ->and($link->latest_snapshot_id)->toBe($latestId);
});

test('a soft-404 title marks the link suspect and leaves the latest snapshot alone', function () {
    $link = givenSnapshot($this->link, healthArticle('real'));
    $latestId = $link->latest_snapshot_id;
    Http::fake(['https://example.com/article' => Http::response('', 200)]);
    FakeExtractor::respondWith('https://example.com/article', ExtractionResult::ok('fake', healthArticle('other'), ['title' => 'Page Not Found']));

    runHealthCheck($link);
    $link->refresh();

    expect($link->health_status)->toBe(HealthStatus::Suspect)
        ->and($link->latest_snapshot_id)->toBe($latestId)
        ->and($link->latestSnapshot->content_text)->toBe(healthArticle('real'))
        ->and(LinkSnapshot::query()->count())->toBe(1)
        ->and($link->check_interval_days)->toBe(7)
        ->and($link->next_check_at->equalTo(now()->addDays(7)))->toBeTrue();
});

test('a collapsed word count marks the link suspect', function () {
    $link = givenSnapshot($this->link, healthArticle('long', 1000));
    Http::fake(['https://example.com/article' => Http::response('', 200)]);
    FakeExtractor::respondWith('https://example.com/article', ExtractionResult::ok('fake', healthArticle('short', 100), ['title' => 'Still here']));

    runHealthCheck($link);
    $link->refresh();

    expect($link->health_status)->toBe(HealthStatus::Suspect)
        ->and(LinkSnapshot::query()->count())->toBe(1);
});

test('a gone link keeps every snapshot and its latest snapshot', function () {
    $link = givenSnapshot($this->link, healthArticle('v1'));
    app(SnapshotRecorder::class)->record($link, ExtractionResult::ok('fake', healthArticle('v2')), $link->link);
    $link->refresh();
    $snapshotIds = $link->snapshots()->pluck('id')->sort()->values()->all();
    $latestId = $link->latest_snapshot_id;
    Http::fake(['https://example.com/article' => Http::response('', 410)]);

    runHealthCheck($link);
    runHealthCheck($link->fresh());
    runHealthCheck($link->fresh());
    $link->refresh();

    expect($link->health_status)->toBe(HealthStatus::Gone)
        ->and($link->latest_snapshot_id)->toBe($latestId)
        ->and($link->snapshots()->pluck('id')->sort()->values()->all())->toBe($snapshotIds)
        ->and(Link::query()->whereKey($link->id)->exists())->toBeTrue();
});

test('a trashed link is skipped', function () {
    Http::fake();
    $this->link->delete();

    runHealthCheck($this->link);

    Http::assertNothingSent();

    expect(FakeExtractor::$calls)->toBe([])
        ->and(Link::withTrashed()->find($this->link->id)->last_checked_at)->toBeNull();
});

test('repeated real changes keep only the configured five snapshots, including the original', function () {
    $link = givenSnapshot($this->link, healthArticle('rev0'));
    Http::fake(['https://example.com/article' => Http::response('', 200)]);

    foreach (range(1, 6) as $revision) {
        $this->travel(1)->days();
        FakeExtractor::respondWith('https://example.com/article', ExtractionResult::ok('fake', healthArticle("rev{$revision}")));

        runHealthCheck($link->fresh());
    }

    $link->refresh();

    expect($link->snapshots()->count())->toBe(5)
        ->and($link->snapshots()->pluck('content_text'))->toContain(healthArticle('rev0'))
        ->and($link->latestSnapshot->content_text)->toBe(healthArticle('rev6'));
});

test('a url edited mid-check writes only last_checked_at and leaves the schedule alone', function () {
    $link = givenSnapshot($this->link, healthArticle('before-edit'), ['consecutive_failures' => 2]);
    $scheduledFor = $link->next_check_at;
    Queue::fake();
    Http::fake(['https://example.com/article' => Http::response('', 200)]);
    FakeExtractor::respondWith('https://example.com/article', function (string $url) use ($link): ExtractionResult {
        Link::query()->whereKey($link->id)->update(['link' => 'https://example.com/edited']);

        return ExtractionResult::ok('fake', healthArticle('stale'));
    });

    runHealthCheck($link);
    $link->refresh();

    expect($link->link)->toBe('https://example.com/edited')
        ->and($link->last_checked_at->equalTo(now()))->toBeTrue()
        ->and($link->consecutive_failures)->toBe(2)
        ->and($link->check_interval_days)->toBe(7)
        ->and($link->next_check_at->equalTo($scheduledFor))->toBeTrue()
        ->and(LinkSnapshot::query()->count())->toBe(1);

    Queue::assertPushed(ExtractContentJob::class);
});

test('a 403 probe is unknown: status and failures are untouched and the interval still grows', function () {
    $link = givenSnapshot($this->link, healthArticle('guarded'), ['consecutive_failures' => 1]);
    Http::fake(['https://example.com/article' => Http::response('', 403)]);

    runHealthCheck($link);
    $link->refresh();

    expect($link->health_status)->toBe(HealthStatus::Ok)
        ->and($link->consecutive_failures)->toBe(1)
        ->and($link->check_interval_days)->toBe(14)
        ->and($link->last_checked_at->equalTo(now()))->toBeTrue()
        ->and(FakeExtractor::$calls)->toBe([]);
});

test('a blocked extraction after a 200 is unknown: status and failures are untouched', function () {
    $link = givenSnapshot($this->link, healthArticle('bot-wall'), ['consecutive_failures' => 2]);
    Http::fake(['https://example.com/article' => Http::response('', 200)]);
    FakeExtractor::respondWith('https://example.com/article', ExtractionResult::failure(ExtractionStatus::Blocked, 'fake', 'Blocked with HTTP 403'));

    runHealthCheck($link);
    $link->refresh();

    expect($link->health_status)->toBe(HealthStatus::Ok)
        ->and($link->consecutive_failures)->toBe(2)
        ->and($link->check_interval_days)->toBe(14)
        ->and($link->last_checked_at->equalTo(now()))->toBeTrue()
        ->and(LinkSnapshot::query()->count())->toBe(1);
});

test('an unsupported extraction after a 200 is a live page: ok, failures reset and no snapshot', function () {
    $link = givenSnapshot($this->link, healthArticle('pdf'), ['consecutive_failures' => 2, 'health_status' => HealthStatus::Error]);
    Http::fake(['https://example.com/article' => Http::response('', 200)]);
    FakeExtractor::respondWith('https://example.com/article', ExtractionResult::failure(ExtractionStatus::Unsupported, 'fake', 'Unsupported content type: application/pdf'));

    runHealthCheck($link);
    $link->refresh();

    expect($link->health_status)->toBe(HealthStatus::Ok)
        ->and($link->consecutive_failures)->toBe(0)
        ->and($link->check_interval_days)->toBe(14)
        ->and(LinkSnapshot::query()->count())->toBe(1);
});

test('a 301 to www followed by a 302 to another host stays ok', function () {
    Http::fake([
        'https://example.com/article' => Http::response('', 301, ['Location' => 'https://www.example.com/article']),
        'https://www.example.com/article' => Http::response('', 302, ['Location' => 'https://sso.provider.test/login']),
        'https://sso.provider.test/login' => Http::response('', 200),
    ]);

    runHealthCheck($this->link);
    $link = $this->link->fresh();

    expect($link->health_status)->toBe(HealthStatus::Ok)
        ->and($link->redirect_url)->toBeNull();
});

test('a gone link stays gone when a later check only errors', function () {
    $link = givenSnapshot($this->link, healthArticle('dead'), ['health_status' => HealthStatus::Gone, 'consecutive_failures' => 3]);
    Http::fake(['https://example.com/article' => Http::response('', 503)]);

    runHealthCheck($link);
    $link->refresh();

    expect($link->health_status)->toBe(HealthStatus::Gone)
        ->and($link->consecutive_failures)->toBe(4)
        ->and($link->next_check_at->equalTo(now()->addDays(90)))->toBeTrue();
});

test('a probe that runs past its total deadline across hops is an error', function () {
    config(['link_health.probe_deadline_seconds' => 30]);

    Http::fake(function (Request $request) {
        $this->travel(20)->seconds();
        $hop = (int) str_replace('https://example.com/hop', '', $request->url());

        return Http::response('', 302, ['Location' => 'https://example.com/hop'.($hop + 1)]);
    });
    $this->link->forceFill(['link' => 'https://example.com/hop0'])->save();

    runHealthCheck($this->link);

    Http::assertSentCount(2);

    expect($this->link->fresh()->consecutive_failures)->toBe(1)
        ->and(FakeExtractor::$calls)->toBe([]);
});

test('a malformed Location header is an error, not a crash', function () {
    Http::fake(['https://example.com/article' => Http::response('', 301, ['Location' => 'http://'])]);

    runHealthCheck($this->link);

    expect($this->link->fresh()->consecutive_failures)->toBe(1)
        ->and(FakeExtractor::$calls)->toBe([]);
});
