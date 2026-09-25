<?php

namespace App\Providers;

use App\Enrichment\EmbeddingManager;
use App\Enrichment\SummaryManager;
use App\Events\LinkCreated;
use App\Jobs\ExtractContentJob;
use App\Scraping\ScrapingManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ScrapingManager::class, fn ($app): ScrapingManager => new ScrapingManager($app));
        $this->app->singleton(SummaryManager::class, fn ($app): SummaryManager => new SummaryManager($app));
        $this->app->singleton(EmbeddingManager::class, fn ($app): EmbeddingManager => new EmbeddingManager($app));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('viewLogViewer', function ($user = null) {
            if (app()->environment('local')) {
                return true;
            }
            if (! $user) {
                return false;
            }

            return in_array($user->email, (array) config('log-viewer.allowed_emails', []), true);
        });

        $this->configureDefaults();
        $this->configureRoutePatterns();
        $this->configureEvents();
    }

    /**
     * Every newly saved link gets its article text extracted in the background.
     *
     * Dispatched after commit, so a link created inside a transaction (an
     * import, for example) never races the job against a row that is not
     * there yet.
     */
    protected function configureEvents(): void
    {
        Event::listen(LinkCreated::class, fn (LinkCreated $event) => ExtractContentJob::dispatch($event->link)->afterCommit());
    }

    /**
     * Keep non-numeric ids away from integer columns. Postgres rejects a
     * comparison like `id = 'abc'` with an error, so without these a mistyped
     * URL is a 500 rather than a 404. Eighteen digits keeps every id inside bigint.
     */
    protected function configureRoutePatterns(): void
    {
        Route::patterns(array_fill_keys(
            ['link', 'linkId', 'group', 'tag', 'publicLink', 'tokenId'],
            '[1-9][0-9]{0,17}',
        ));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(8)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
