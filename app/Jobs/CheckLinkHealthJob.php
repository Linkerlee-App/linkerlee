<?php

namespace App\Jobs;

use App\Enums\HealthStatus;
use App\Health\CheckOutcome;
use App\Health\CheckSchedule;
use App\Health\HealthClassifier;
use App\Models\Link;
use App\Scraping\RecordOutcome;
use App\Scraping\ScrapingManager;
use App\Scraping\SnapshotRecorder;
use App\Scraping\UrlGuard;
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
     * How long the unique lock outlives a job that never gets processed (a
     * lost worker, a flushed queue), so the next health-check run is not
     * blocked for long.
     */
    public int $uniqueFor = 3600;

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
     * Probes the URL and writes the outcome, the failure count and the next
     * check in one save at the end. A trashed link is skipped.
     *
     * The extraction runs on the probe's final URL, but is recorded against
     * the URL the check started from, so a redirected link is not mistaken
     * for one the user edited. When the user did edit it mid-check, the
     * recorder has already queued a fresh extraction, and only
     * `last_checked_at` is written.
     */
    public function handle(ScrapingManager $scraping, SnapshotRecorder $recorder, HealthClassifier $classifier, CheckSchedule $schedule): void
    {
        if ($this->link->trashed()) {
            return;
        }

        $url = $this->link->link;
        $probe = $this->probe($url);

        if ($probe['status'] === 304) {
            $this->finish($schedule, CheckOutcome::Unchanged, $this->link->health_status ?? HealthStatus::Ok, 0);

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

        $result = $scraping->extractFor($probe['finalUrl']);

        if (! $result->isOk()) {
            $this->finishFailure($schedule, HealthStatus::Error);

            return;
        }

        $redirectUrl = $health === HealthStatus::Redirected ? $probe['finalUrl'] : null;

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
     * every hop before it is requested. The body is never read.
     *
     * A null status means the probe failed without a usable response: the
     * URL or a hop was refused by the guard, the connection failed, or there
     * were too many redirects. `permanentRedirect` is true when any hop was a
     * 301/308 to a different host.
     *
     * @return array{status: int|null, finalUrl: string, permanentRedirect: bool}
     */
    private function probe(string $url): array
    {
        $current = $url;
        $permanentRedirect = false;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            if (UrlGuard::check($current) !== null) {
                return ['status' => null, 'finalUrl' => $current, 'permanentRedirect' => $permanentRedirect];
            }

            $response = $this->request($current);

            if ($response === null) {
                return ['status' => null, 'finalUrl' => $current, 'permanentRedirect' => $permanentRedirect];
            }

            $status = $response['status'];

            if (! in_array($status, [301, 302, 303, 307, 308], true) || $response['location'] === null) {
                return ['status' => $status, 'finalUrl' => $current, 'permanentRedirect' => $permanentRedirect];
            }

            $next = (string) UriResolver::resolve(Utils::uriFor($current), Utils::uriFor($response['location']));

            if (in_array($status, [301, 308], true)
                && strcasecmp((string) parse_url($current, PHP_URL_HOST), (string) parse_url($next, PHP_URL_HOST)) !== 0) {
                $permanentRedirect = true;
            }

            $current = $next;
        }

        return ['status' => null, 'finalUrl' => $current, 'permanentRedirect' => $permanentRedirect];
    }

    /**
     * One hop of the probe: a streamed GET that does not follow redirects,
     * carrying the link's validators, whose body is closed unread.
     *
     * @return array{status: int, location: string|null}|null null when the connection failed
     */
    private function request(string $url): ?array
    {
        try {
            $response = Http::timeout((int) config('scraping.timeout'))
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
     * Only a Gone link backs off to the longest interval.
     */
    private function finishFailure(CheckSchedule $schedule, HealthStatus $failure): void
    {
        $failures = min((int) $this->link->consecutive_failures + 1, self::MAX_CONSECUTIVE_FAILURES);
        $reachedThreshold = $failures >= (int) config('link_health.failure_threshold');

        $this->finish(
            $schedule,
            $reachedThreshold && $failure === HealthStatus::Gone ? CheckOutcome::Gone : CheckOutcome::Failure,
            $reachedThreshold ? $failure : $this->link->health_status,
            $failures,
        );
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
