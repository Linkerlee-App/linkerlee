<?php

use App\Enums\ExtractionStatus;
use App\Events\LinkSnapshotCreated;
use App\Models\Link;
use App\Models\LinkSnapshot;
use App\Models\User;
use App\Scraping\ExtractionResult;
use App\Scraping\RecordOutcome;
use App\Scraping\SnapshotRecorder;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Http::preventStrayRequests();

    $this->link = Link::factory()->create(['user_id' => User::factory()]);
    $this->recorder = app(SnapshotRecorder::class);
});

/**
 * An ok extraction result carrying the given article text.
 */
function okExtraction(string $text = 'The quick brown fox jumps over the lazy dog.', array $attrs = []): ExtractionResult
{
    return ExtractionResult::ok('fake', $text, $attrs);
}

test('an ok result creates a snapshot, points the link at it and fires LinkSnapshotCreated', function () {
    Event::fake([LinkSnapshotCreated::class]);

    $outcome = $this->recorder->record($this->link, okExtraction('Hello there world', [
        'httpStatus' => 200,
        'finalUrl' => 'https://example.com/final',
        'etag' => '"abc"',
        'lastModified' => 'Wed, 21 Oct 2026 07:28:00 GMT',
        'title' => 'Hello',
        'author' => 'Jane',
    ]));

    expect($outcome)->toBe(RecordOutcome::Created);

    $snapshot = LinkSnapshot::query()->sole();
    $link = $this->link->fresh();

    expect($snapshot->link_id)->toBe($link->id)
        ->and($snapshot->extractor)->toBe('fake')
        ->and($snapshot->http_status)->toBe(200)
        ->and($snapshot->final_url)->toBe('https://example.com/final')
        ->and($snapshot->title)->toBe('Hello')
        ->and($snapshot->author)->toBe('Jane')
        ->and($snapshot->content_text)->toBe('Hello there world')
        ->and($snapshot->content_hash)->toBe(hash('sha256', 'Hello there world'))
        ->and($snapshot->word_count)->toBe(3)
        ->and($snapshot->metadata)->toMatchArray(['etag' => '"abc"', 'last_modified' => 'Wed, 21 Oct 2026 07:28:00 GMT'])
        ->and($snapshot->fetched_at)->not->toBeNull();

    expect($link->latest_snapshot_id)->toBe($snapshot->id)
        ->and($link->latestSnapshot->is($snapshot))->toBeTrue()
        ->and($link->snapshots)->toHaveCount(1)
        ->and($link->extraction_status)->toBe(ExtractionStatus::Ok)
        ->and($link->extraction_error)->toBeNull()
        ->and($link->extracted_at)->not->toBeNull()
        ->and($link->etag)->toBe('"abc"')
        ->and($link->last_modified)->toBe('Wed, 21 Oct 2026 07:28:00 GMT');

    Event::assertDispatchedTimes(LinkSnapshotCreated::class, 1);
    Event::assertDispatched(LinkSnapshotCreated::class, fn (LinkSnapshotCreated $event) => $event->snapshot->is($snapshot));
});

test('recording the same content twice leaves one snapshot and bumps extracted_at and the etag', function () {
    Event::fake([LinkSnapshotCreated::class]);

    $this->recorder->record($this->link, okExtraction('Same text', ['etag' => '"v1"']));
    $firstExtractedAt = $this->link->fresh()->extracted_at;

    $this->travel(5)->minutes();

    $outcome = $this->recorder->record($this->link, okExtraction('Same text', ['etag' => '"v2"']));
    $link = $this->link->fresh();

    expect($outcome)->toBe(RecordOutcome::Unchanged)
        ->and(LinkSnapshot::query()->count())->toBe(1)
        ->and($link->extraction_status)->toBe(ExtractionStatus::Ok)
        ->and($link->etag)->toBe('"v2"')
        ->and($link->extracted_at->greaterThan($firstExtractedAt))->toBeTrue();

    Event::assertDispatchedTimes(LinkSnapshotCreated::class, 1);
});

test('a failed result records the status and error and keeps the prior snapshot', function () {
    $this->recorder->record($this->link, okExtraction('Original text'));
    $snapshot = LinkSnapshot::query()->sole();

    $outcome = $this->recorder->record(
        $this->link,
        ExtractionResult::failure(ExtractionStatus::Blocked, 'fake', 'Host resolves to a private address'),
    );
    $link = $this->link->fresh();

    expect($outcome)->toBe(RecordOutcome::Failed)
        ->and($link->extraction_status)->toBe(ExtractionStatus::Blocked)
        ->and($link->extraction_error)->toBe('Host resolves to a private address')
        ->and($link->latest_snapshot_id)->toBe($snapshot->id)
        ->and(LinkSnapshot::query()->count())->toBe(1);
});

test('a stored extraction error is capped at 1000 characters', function () {
    $this->recorder->record(
        $this->link,
        ExtractionResult::failure(ExtractionStatus::Failed, 'fake', str_repeat('x', 5000)),
    );

    expect(mb_strlen($this->link->fresh()->extraction_error))->toBe(1000);
});

test('the extraction error is hidden from serialization', function () {
    $this->recorder->record($this->link, ExtractionResult::failure(ExtractionStatus::Failed, 'fake', 'boom'));

    expect($this->link->fresh()->toArray())->not->toHaveKey('extraction_error');
});

test('a new snapshot beyond the retention limit prunes the oldest ones', function () {
    config()->set('scraping.keep_snapshots', 5);

    LinkSnapshot::factory()
        ->count(6)
        ->for($this->link)
        ->sequence(fn ($sequence) => ['fetched_at' => now()->subDays(10 - $sequence->index)])
        ->create();

    $this->recorder->record($this->link, okExtraction('Brand new text'));
    $link = $this->link->fresh();

    expect($link->snapshots()->count())->toBe(5)
        ->and($link->snapshots()->pluck('id'))->toContain($link->latest_snapshot_id)
        ->and($link->latestSnapshot->content_text)->toBe('Brand new text');
});

test('pruning 7 snapshots keeps 5 and never deletes the latest one, even when it is the oldest', function () {
    config()->set('scraping.keep_snapshots', 5);

    $snapshots = LinkSnapshot::factory()
        ->count(7)
        ->for($this->link)
        ->sequence(fn ($sequence) => ['fetched_at' => now()->subDays(10 - $sequence->index)])
        ->create();

    $oldest = $snapshots->first();
    $this->link->forceFill(['latest_snapshot_id' => $oldest->id])->save();

    $this->recorder->pruneSnapshots($this->link);

    $remaining = $this->link->snapshots()->pluck('id');

    expect($remaining)->toHaveCount(5)
        ->and($remaining)->toContain($oldest->id)
        ->and($remaining->all())->toEqualCanonicalizing([
            $oldest->id,
            ...$snapshots->slice(3)->pluck('id')->all(),
        ]);
});

test('the lock serializes two recorders with identical content into one snapshot', function () {
    Sleep::fake(syncWithCarbon: true);

    $first = Link::query()->find($this->link->id);
    $second = Link::query()->find($this->link->id);
    $held = Cache::lock("link-snapshot:{$this->link->id}", 30);
    expect($held->get())->toBeTrue();

    $firstOutcome = null;

    Sleep::whenFakingSleep(function () use (&$firstOutcome, $held, $first) {
        if ($firstOutcome !== null) {
            return;
        }

        $held->release();
        $firstOutcome = $this->recorder->record($first, okExtraction('Identical text'));
    });

    $secondOutcome = $this->recorder->record($second, okExtraction('Identical text'));

    expect($firstOutcome)->toBe(RecordOutcome::Created)
        ->and($secondOutcome)->toBe(RecordOutcome::Unchanged)
        ->and(LinkSnapshot::query()->count())->toBe(1);
});

test('a recorder gives up when the lock stays held', function () {
    Sleep::fake(syncWithCarbon: true);

    expect(Cache::lock("link-snapshot:{$this->link->id}", 30)->get())->toBeTrue();

    expect(fn () => $this->recorder->record($this->link, okExtraction()))->toThrow(LockTimeoutException::class);

    expect(LinkSnapshot::query()->count())->toBe(0);
});

test('an over-long author, etag and last_modified are capped at 255 characters instead of failing the insert', function () {
    $outcome = $this->recorder->record($this->link, okExtraction('Body text', [
        'author' => str_repeat('a', 300),
        'etag' => str_repeat('e', 300),
        'lastModified' => str_repeat('m', 300),
    ]));

    $snapshot = LinkSnapshot::query()->sole();
    $link = $this->link->fresh();

    expect($outcome)->toBe(RecordOutcome::Created)
        ->and(mb_strlen($snapshot->author))->toBe(255)
        ->and(mb_strlen($snapshot->metadata['etag']))->toBe(255)
        ->and(mb_strlen($snapshot->metadata['last_modified']))->toBe(255)
        ->and(mb_strlen($link->etag))->toBe(255)
        ->and(mb_strlen($link->last_modified))->toBe(255);
});

test('an over-long etag on unchanged content is capped at 255 characters', function () {
    $this->recorder->record($this->link, okExtraction('Same body'));

    $outcome = $this->recorder->record($this->link, okExtraction('Same body', ['etag' => str_repeat('e', 300)]));

    expect($outcome)->toBe(RecordOutcome::Unchanged)
        ->and(mb_strlen($this->link->fresh()->etag))->toBe(255);
});

test('a link force-deleted before the recorder re-reads it fails silently with no writes', function () {
    Event::fake([LinkSnapshotCreated::class]);

    $stale = Link::query()->find($this->link->id);
    $this->link->forceDelete();

    $outcome = $this->recorder->record($stale, okExtraction('Body text'));

    expect($outcome)->toBe(RecordOutcome::Failed)
        ->and(LinkSnapshot::query()->count())->toBe(0)
        ->and(Cache::lock("link-snapshot:{$stale->id}", 1)->get())->toBeTrue();

    Event::assertNotDispatched(LinkSnapshotCreated::class);
});

test('the caller\'s link instance is refreshed with current state and its loaded relations reloaded', function () {
    $this->recorder->record($this->link, okExtraction('First body'));

    $caller = Link::query()->with('latestSnapshot')->find($this->link->id);
    $this->recorder->record($this->link, okExtraction('Second body'));

    $this->recorder->record($caller, ExtractionResult::failure(ExtractionStatus::Failed, 'fake', 'boom'));

    expect($caller->relationLoaded('latestSnapshot'))->toBeTrue()
        ->and($caller->latestSnapshot->content_text)->toBe('Second body')
        ->and($caller->extraction_status)->toBe(ExtractionStatus::Failed);
});
