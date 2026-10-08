<?php

namespace App\Enrichment;

use App\Enrichment\Contracts\SummaryProvider;
use App\Scraping\ScrapingManager;
use Illuminate\Support\Manager;
use InvalidArgumentException;

/**
 * Resolves {@see SummaryProvider} drivers by name, the same config-class-map
 * Manager as {@see ScrapingManager}.
 *
 * `createDriver()` is overridden instead of adding one `create*Driver()`
 * method per entry in `config('enrichment.summary.drivers')`: every driver
 * is a plain class the container can build, so a driver name only needs to
 * be looked up in config and handed to the container.
 */
final class SummaryManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('enrichment.summary.driver');
    }

    /**
     * @param  string|null  $driver
     */
    public function driver($driver = null): SummaryProvider
    {
        return parent::driver($driver);
    }

    /**
     * @param  string  $driver
     */
    protected function createDriver($driver): SummaryProvider
    {
        $class = $this->config->get("enrichment.summary.drivers.{$driver}");

        if (! is_string($class)) {
            throw new InvalidArgumentException("Driver [{$driver}] not supported.");
        }

        return $this->container->make($class);
    }

    /**
     * The configured driver, using the given model instead of the
     * configured default when one is given.
     *
     * Every concrete provider implements `withModel(string $model): static`,
     * returning a clone configured for that model; it is left out of the
     * {@see SummaryProvider} contract because only this method needs it.
     */
    public function provider(?string $model = null): SummaryProvider
    {
        $provider = $this->driver();

        if ($model === null) {
            return $provider;
        }

        return $provider->withModel($model);
    }
}
