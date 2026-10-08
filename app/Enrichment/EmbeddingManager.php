<?php

namespace App\Enrichment;

use App\Enrichment\Contracts\EmbeddingProvider;
use App\Scraping\ScrapingManager;
use Illuminate\Support\Manager;
use InvalidArgumentException;

/**
 * Resolves {@see EmbeddingProvider} drivers by name, the same config-class-map
 * Manager as {@see ScrapingManager}.
 *
 * `createDriver()` is overridden instead of adding one `create*Driver()`
 * method per entry in `config('enrichment.embedding.drivers')`: every driver
 * is a plain class the container can build, so a driver name only needs to
 * be looked up in config and handed to the container.
 */
final class EmbeddingManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('enrichment.embedding.driver');
    }

    /**
     * @param  string|null  $driver
     */
    public function driver($driver = null): EmbeddingProvider
    {
        return parent::driver($driver);
    }

    /**
     * @param  string  $driver
     */
    protected function createDriver($driver): EmbeddingProvider
    {
        $class = $this->config->get("enrichment.embedding.drivers.{$driver}");

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
     * {@see EmbeddingProvider} contract because only this method needs it.
     */
    public function provider(?string $model = null): EmbeddingProvider
    {
        $provider = $this->driver();

        if ($model === null) {
            return $provider;
        }

        return $provider->withModel($model);
    }
}
