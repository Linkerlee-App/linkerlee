<?php

use App\Enums\ExtractionStatus;
use App\Events\LinkCreated;
use App\Jobs\ExtractContentJob;
use App\Models\Link;
use App\Models\LinkSnapshot;
use App\Models\User;
use App\Scraping\Drivers\FakeExtractor;
use App\Scraping\ExtractionResult;
use App\Scraping\ScrapingManager;
use App\Scraping\SnapshotRecorder;
use App\Scraping\TransientExtractionException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::preventStrayRequests();
    FakeExtractor::reset();

    $this->link = Link::factory()->create([
        'user_id' => User::factory(),
        'link' => 'https://example.com/article',
    ]);
});

afterEach(function () {
    FakeExtractor::reset();
});

/**
 * Runs the job's handle() the way the worker would, resolving its
 * dependencies from the container.
 */
function runExtractContentJob(ExtractContentJob $job): void
{
    $job->handle(app(ScrapingManager::class), app(SnapshotRecorder::class));
}

test('the job is unique per link and runs on the ingestion queue with the agreed retry settings', function () {
    $job = new ExtractContentJob($this->link);

    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job)->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class)
        ->and($job->uniqueFor)->toBe(3600)
        ->and($job->uniqueId())->toBe((string) $this->link->id)
        ->and($job->queue)->toBe('ingestion')
        ->and($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([30, 120, 600])
        ->and($job->timeout)->toBe(60)
        ->and($job->deleteWhenMissingModels)->toBeTrue();
});

test('an ok extraction creates a snapshot and marks the link ok', function () {
    FakeExtractor::respondWith('https://example.com/*', ExtractionResult::ok('fake', 'Some article body text'));

    runExtractContentJob(new ExtractContentJob($this->link));

    $link = $this->link->fresh();

    expect(FakeExtractor::$calls)->toBe(['https://example.com/article'])
        ->and($link->extraction_status)->toBe(ExtractionStatus::Ok)
        ->and($link->latestSnapshot->content_text)->toBe('Some article body text');
});

test('running the job twice on unchanged content leaves one snapshot', function () {
    FakeExtractor::respondWith('*', ExtractionResult::ok('fake', 'Unchanged body'));

    runExtractContentJob(new ExtractContentJob($this->link));
    runExtractContentJob(new ExtractContentJob($this->link));

    expect(LinkSnapshot::query()->count())->toBe(1);
});

test('a transient failure records the status and throws so the job retries', function () {
    FakeExtractor::respondWith('*', ExtractionResult::failure(ExtractionStatus::Failed, 'fake', 'HTTP 503', ['transient' => true]));

    $job = (new ExtractContentJob($this->link))->withFakeQueueInteractions();
    $job->job->attempts = 1;

    expect(fn () => runExtractContentJob($job))->toThrow(TransientExtractionException::class, 'HTTP 503');

    $link = $this->link->fresh();

    expect($link->extraction_status)->toBe(ExtractionStatus::Failed)
        ->and($link->extraction_error)->toBe('HTTP 503');
});

test('a transient failure on the last attempt is recorded without throwing', function () {
    FakeExtractor::respondWith('*', ExtractionResult::failure(ExtractionStatus::Failed, 'fake', 'HTTP 503', ['transient' => true]));

    $job = (new ExtractContentJob($this->link))->withFakeQueueInteractions();
    $job->job->attempts = 3;

    runExtractContentJob($job);

    expect($this->link->fresh()->extraction_status)->toBe(ExtractionStatus::Failed);
});

test('a permanent failure is recorded without throwing', function () {
    FakeExtractor::respondWith('*', ExtractionResult::failure(ExtractionStatus::Unsupported, 'fake', 'Not HTML'));

    runExtractContentJob(new ExtractContentJob($this->link));

    $link = $this->link->fresh();

    expect($link->extraction_status)->toBe(ExtractionStatus::Unsupported)
        ->and($link->extraction_error)->toBe('Not HTML');
});

test('failed() marks the link failed with the exception message', function () {
    (new ExtractContentJob($this->link))->failed(new RuntimeException('Worker timed out'));

    $link = $this->link->fresh();

    expect($link->extraction_status)->toBe(ExtractionStatus::Failed)
        ->and($link->extraction_error)->toBe('Worker timed out');
});

test('failed() caps the stored error at 1000 characters', function () {
    (new ExtractContentJob($this->link))->failed(new RuntimeException(str_repeat('y', 3000)));

    expect(mb_strlen($this->link->fresh()->extraction_error))->toBe(1000);
});

test('failed() does not throw when the link is gone', function () {
    $job = new ExtractContentJob($this->link);
    $this->link->forceDelete();

    $job->failed(new RuntimeException('boom'));

    expect(Link::withTrashed()->count())->toBe(0);
});

test('a trashed link is skipped', function () {
    $this->link->delete();

    runExtractContentJob(new ExtractContentJob($this->link));

    expect(FakeExtractor::$calls)->toBe([])
        ->and(LinkSnapshot::query()->count())->toBe(0);
});

test('a trashed link is still skipped after the job is serialized onto the queue', function () {
    $this->link->delete();

    ExtractContentJob::dispatch($this->link);

    expect(FakeExtractor::$calls)->toBe([])
        ->and(LinkSnapshot::query()->count())->toBe(0);
});

test('a link force-deleted before the job runs makes the job disappear silently', function () {
    $job = new ExtractContentJob($this->link);
    $this->link->forceDelete();

    dispatch($job);

    expect(FakeExtractor::$calls)->toBe([])
        ->and(LinkSnapshot::query()->count())->toBe(0);
});

test('LinkCreated dispatches exactly one ExtractContentJob on the ingestion queue', function () {
    Queue::fake();

    LinkCreated::dispatch($this->link);

    Queue::assertPushedOn('ingestion', ExtractContentJob::class, fn (ExtractContentJob $job) => $job->link->is($this->link));
    Queue::assertPushedTimes(ExtractContentJob::class, 1);
});

test('a url edited while the job is extracting discards the old page and re-queues extraction', function () {
    FakeExtractor::respondWith('https://example.com/article', function (string $url): ExtractionResult {
        Link::query()->whereKey($this->link->id)->update([
            'link' => 'https://example.com/edited',
            'extraction_status' => ExtractionStatus::Pending,
        ]);

        return ExtractionResult::ok('fake', 'The old page body');
    });

    Queue::fake();

    runExtractContentJob(new ExtractContentJob($this->link));

    $link = $this->link->fresh();

    expect(FakeExtractor::$calls)->toBe(['https://example.com/article'])
        ->and(LinkSnapshot::query()->count())->toBe(0)
        ->and($link->extraction_status)->toBe(ExtractionStatus::Pending);

    Queue::assertPushedOn('ingestion', ExtractContentJob::class, fn (ExtractContentJob $job) => $job->link->is($this->link));
});

test('a transient failure for a superseded url does not throw, since a fresh job is already queued', function () {
    FakeExtractor::respondWith('*', function (string $url): ExtractionResult {
        Link::query()->whereKey($this->link->id)->update(['link' => 'https://example.com/edited']);

        return ExtractionResult::failure(ExtractionStatus::Failed, 'fake', 'HTTP 503', ['transient' => true]);
    });

    Queue::fake();

    $job = (new ExtractContentJob($this->link))->withFakeQueueInteractions();
    $job->job->attempts = 1;

    runExtractContentJob($job);

    $job->assertNotFailed();
    Queue::assertPushed(ExtractContentJob::class);
});

test('a dispatch made while the job runs is not dropped by the unique lock', function () {
    $nestedDispatches = 0;

    FakeExtractor::respondWith('*', function (string $url) use (&$nestedDispatches): ExtractionResult {
        if ($nestedDispatches++ === 0) {
            ExtractContentJob::dispatch($this->link);
        }

        return ExtractionResult::ok('fake', 'Body text');
    });

    ExtractContentJob::dispatch($this->link);

    expect(FakeExtractor::$calls)->toHaveCount(2);
});
