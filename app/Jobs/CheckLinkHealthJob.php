<?php

namespace App\Jobs;

use App\Enums\ExtractionStatus;
use App\Enums\HealthStatus;
use App\Health\CheckOutcome;
use App\Health\CheckSchedule;
use App\Health\HealthClassifier;
use App\Models\Link;
use App\Scraping\RecordOutcome;
use App\Scraping\ScrapingManager;
use App\Scraping\SnapshotRecorder;
use App\Scraping\UrlGuard;
use GuzzleHttp\Psr7\Exception\MalformedUriException;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Checks that a link's page is still there and whether its content changed,
 * then schedules the next check.
 *
 * A conditional GET probes the URL (walking redirects by hand so every hop is
 * SSRF-checked, and never reading the body). A page that still answers is
 * re-extracted and recorded through {@see SnapshotRecorder} with noise
 * comparison on, so a changed view counter is not a new snapshot. A page that
 * fails is only marked Gone or Error after `link_health.failure_threshold`
 * checks in a row; until then its status is left as it was. Snapshots are
 * never deleted here, whatever the page does.
 *
 * Not retried: the next scheduled check is the retry.
 */
class CheckLinkHealthJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    /**
     * The most redirects followed before the probe gives up as an error.
     */
    private const MAX_REDIRECTS = 5;

    /**
     * The largest value the `consecutive_failures` column holds.
     */
    private const MAX_CONSECUTIVE_FAILURES = 255;

    /**
     * Statuses that mean the site refused the bot, not that the page is
     * gone or broken: the check learns nothing about the page's health.
     */
    private const BLOCKED_STATUSES = [401, 403, 429];

    /**
     * How long, in seconds, the unique lock outlives the job's delay when the
     * job never gets processed (a lost worker, a flushed queue), so a later
     * health-check run is not blocked for long.
     */
    public const UNIQUE_LOCK_SECONDS = 3600;

    public int $uniqueFor = self::UNIQUE_LOCK_SECONDS;

    public int $tries = 1;

    public int $timeout = 60;

    /**
     * A link force-deleted before the job runs makes the job vanish quietly.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Link $link)
    {
        $this->onQueue('health');
    }

    public function uniqueId(): string
    {
        return (string) $this->link->id;
    }

    /**
     * Delays the job by the given seconds and stretches its unique lock to
     * cover the delay plus {@see self::UNIQUE_LOCK_SECONDS}, so the lock does
     * not expire while the job is still waiting to run.
     */
    public function delayedBy(int $seconds): static
    {
        $this->uniqueFor = $seconds + self::UNIQUE_LOCK_SECONDS;

        return $this->delay(now()->addSeconds($seconds));
    }

    /**
     * Probes the URL and writes the outcome, the failure count and the next
     * check in one save at the end. A trashed link is skipped.
     *
     * The extraction runs on the probe's final URL, but is recorded against
     * the URL the check started from, so a redirected link is not mistaken
     * for one the user edited. When the user did edit it mid-check, the
     * recorder has already queued a fresh extraction, and only
     * `last_checked_at` is written.
     *
     * A 304 is a success: the validators come from the last good fetch, so
     * the page is Ok (or Redirected, via the chain) and unchanged, with no
     * extraction.
     *
     * A bot wall (401/403/429 at the probe, or a Blocked extraction) is
     * "unknown": the status and failure count stand and the interval grows.
     * An Unsupported extraction (a PDF, an image) is a live page with no text
     * to snapshot. Only a Failed extraction counts as a failure.
     */
    public function handle(ScrapingManager $scraping, SnapshotRecorder $recorder, HealthClassifier $classifier, CheckSchedule $schedule): void
    {
        if ($this->link->trashed()) {
            return;
        }

        $url = $this->link->link;
        $probe = $this->probe($url, $classifier);

        if (in_array($probe['status'], self::BLOCKED_STATUSES, true)) {
            $this->finishUnknown($schedule);

            return;
        }

        $health = $probe['status'] === null
            ? HealthStatus::Error
            : $classifier->classifyResponse(
                $probe['status'],
                parse_url($url, PHP_URL_HOST) ?: null,
                parse_url($probe['finalUrl'], PHP_URL_HOST) ?: null,
                $probe['permanentRedirect'],
            );

        if ($health === HealthStatus::Gone || $health === HealthStatus::Error) {
            $this->finishFailure($schedule, $health);

            return;
        }

        $redirectUrl = $health === HealthStatus::Redirected ? $probe['finalUrl'] : null;

        if ($probe['status'] === 304) {
            $this->finish($schedule, CheckOutcome::Unchanged, $health, 0, ['redirect_url' => $redirectUrl]);

            return;
        }

        $result = $scraping->extractFor($probe['finalUrl']);

        if ($result->status === ExtractionStatus::Blocked) {
            $this->finishUnknown($schedule);

            return;
        }

        if ($result->status === ExtractionStatus::Unsupported) {
            $this->finish($schedule, CheckOutcome::Unchanged, $health, 0, ['redirect_url' => $redirectUrl]);

            return;
        }

        if (! $result->isOk()) {
            $this->finishFailure($schedule, HealthStatus::Error);

            return;
        }

        if ($classifier->isSoft404($result, $this->link->latestSnapshot)) {
            $this->finish($schedule, CheckOutcome::Suspect, HealthStatus::Suspect, 0, ['redirect_url' => $redirectUrl]);

            return;
        }

        $outcome = $recorder->record($this->link, $result, $url, compareForNoise: true);

        if ($outcome === RecordOutcome::Superseded) {
            $this->link->forceFill(['last_checked_at' => now()])->save();

            return;
        }

        if ($outcome === RecordOutcome::Failed) {
            return;
        }

        $this->finish(
            $schedule,
            $outcome === RecordOutcome::Created ? CheckOutcome::Changed : CheckOutcome::Unchanged,
            $health,
            0,
            ['redirect_url' => $redirectUrl],
        );
    }

    /**
     * Sends a conditional GET and follows up to {@see self::MAX_REDIRECTS}
     * redirects by hand, running {@see UrlGuard::check()} on the URL and on
     * every hop before it is requested. The body is never read, and the walk
     * as a whole is bounded by `link_health.probe_deadline_seconds`.
     *
     * A null status means the probe failed without a usable response: the
     * URL or a hop was refused by the guard, a Location could not be parsed,
     * the connection failed, the deadline passed, or there were too many
     * redirects.
     *
     * `permanentRedirect` describes the last hop that moved to a different
     * host (ignoring "www."): true when it was a 301/308, false when it was
     * temporary. Hops that stay on the host leave it alone, so a canonical
     * 301 to "www." followed by a 302 to a login host is not permanent.
     *
     * @return array{status: int|null, finalUrl: string, permanentRedirect: bool}
     */
    private function probe(string $url, HealthClassifier $classifier): array
    {
        $current = $url;
        $permanentRedirect = false;
        $deadline = now()->addSeconds((int) config('link_health.probe_deadline_seconds'));

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $remainingSeconds = (int) floor(now()->diffInSeconds($deadline, false));

            if ($remainingSeconds <= 0 || UrlGuard::check($current) !== null) {
                return self::failedProbe($current, $permanentRedirect);
            }

            $response = $this->request($current, min((int) config('scraping.timeout'), $remainingSeconds));

            if ($response === null) {
                return self::failedProbe($current, $permanentRedirect);
            }

            $status = $response['status'];

            if (! in_array($status, [301, 302, 303, 307, 308], true) || $response['location'] === null) {
                return ['status' => $status, 'finalUrl' => $current, 'permanentRedirect' => $permanentRedirect];
            }

            try {
                $next = (string) UriResolver::resolve(Utils::uriFor($current), Utils::uriFor($response['location']));
            } catch (MalformedUriException) {
                return self::failedProbe($current, $permanentRedirect);
            }

            $currentHost = parse_url($current, PHP_URL_HOST) ?: null;
            $nextHost = parse_url($next, PHP_URL_HOST) ?: null;

            if ($currentHost === null || $nextHost === null || $classifier->hostsDiffer($currentHost, $nextHost)) {
                $permanentRedirect = in_array($status, [301, 308], true);
            }

            $current = $next;
        }

        return self::failedProbe($current, $permanentRedirect);
    }

    /**
     * The probe's result when it ended without a usable response.
     *
     * @return array{status: null, finalUrl: string, permanentRedirect: bool}
     */
    private static function failedProbe(string $url, bool $permanentRedirect): array
    {
        return ['status' => null, 'finalUrl' => $url, 'permanentRedirect' => $permanentRedirect];
    }

    /**
     * One hop of the probe: a streamed GET that does not follow redirects,
     * carrying the link's validators, whose body is closed unread. The
     * timeout is capped by what is left of the probe's deadline.
     *
     * @return array{status: int, location: string|null}|null null when the connection failed
     */
    private function request(string $url, int $timeoutSeconds): ?array
    {
        try {
            $response = Http::timeout($timeoutSeconds)
                ->withUserAgent((string) config('scraping.user_agent'))
                ->withHeaders(array_filter([
                    'If-None-Match' => $this->link->etag,
                    'If-Modified-Since' => $this->link->last_modified,
                ]))
                ->withOptions([
                    'allow_redirects' => false,
                    'stream' => true,
                    'force_ip_resolve' => 'v4',
                ])
                ->get($url);
        } catch (ConnectionException) {
            return null;
        }

        $response->toPsrResponse()->getBody()->close();

        return [
            'status' => $response->status(),
            'location' => $response->header('Location') ?: null,
        ];
    }

    /**
     * Counts one more failure. Below `link_health.failure_threshold` the
     * status is left as it was; at the threshold it becomes Gone or Error.
     * A link already Gone stays Gone on a mere error: only a success moves
     * it off. Only a Gone link backs off to the longest interval.
     */
    private function finishFailure(CheckSchedule $schedule, HealthStatus $failure): void
    {
        $failures = min((int) $this->link->consecutive_failures + 1, self::MAX_CONSECUTIVE_FAILURES);
        $reachedThreshold = $failures >= (int) config('link_health.failure_threshold');

        $status = match (true) {
            $this->link->health_status === HealthStatus::Gone => HealthStatus::Gone,
            $reachedThreshold => $failure,
            default => $this->link->health_status,
        };

        $this->finish(
            $schedule,
            $status === HealthStatus::Gone ? CheckOutcome::Gone : CheckOutcome::Failure,
            $status,
            $failures,
        );
    }

    /**
     * Records a check that learned nothing about the page (the site blocked
     * the bot): the status and failure count stand, and the interval grows
     * as for an unchanged page.
     */
    private function finishUnknown(CheckSchedule $schedule): void
    {
        $this->finish($schedule, CheckOutcome::Unchanged, $this->link->health_status, (int) $this->link->consecutive_failures);
    }

    /**
     * Writes the check's result and the next check in one save.
     *
     * @param  array<string, mixed>  $extra
     */
    private function finish(CheckSchedule $schedule, CheckOutcome $outcome, ?HealthStatus $status, int $failures, array $extra = []): void
    {
        $this->link->forceFill([
            ...$schedule->next($this->link, $outcome),
            ...$extra,
            'health_status' => $status,
            'consecutive_failures' => $failures,
            'last_checked_at' => now(),
        ])->save();
    }
}
