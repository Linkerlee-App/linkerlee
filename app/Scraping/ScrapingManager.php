<?php

namespace App\Scraping;

use App\Enums\ExtractionStatus;
use App\Scraping\Contracts\ContentExtractor;
use Illuminate\Support\Manager;
use InvalidArgumentException;

/**
 * Resolves {@see ContentExtractor} drivers by name and walks the per-URL
 * fallback chain described in `config('scraping')`.
 *
 * `createDriver()` is overridden instead of adding one `create*Driver()`
 * method per entry in `config('scraping.drivers')`: every driver is a plain
 * class the container can build, so a driver name only needs to be looked up
 * in config and handed to the container.
 */
final class ScrapingManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('scraping.default');
    }

    /**
     * @param  string|null  $driver
     */
    public function driver($driver = null): ContentExtractor
    {
        return parent::driver($driver);
    }

    /**
     * @param  string  $driver
     */
    protected function createDriver($driver): ContentExtractor
    {
        $class = $this->config->get("scraping.drivers.{$driver}");

        if (! is_string($class)) {
            throw new InvalidArgumentException("Driver [{$driver}] not supported.");
        }

        return $this->container->make($class);
    }

    /**
     * Extracts article text for the given URL by walking the fallback
     * chain: the domain override (or the default driver) followed by the
     * configured fallbacks, skipping any driver that does not support the
     * URL.
     *
     * The first result that is ok and clears `min_word_count` wins. Failing
     * that, the ok result with the highest word count wins. Failing that,
     * the first driver's result is returned. When nothing in the chain
     * supports the URL at all, an unsupported result is returned without
     * calling any driver.
     */
    public function extractFor(string $url): ExtractionResult
    {
        $chain = $this->supportedChain($url);

        if ($chain === []) {
            return ExtractionResult::failure(ExtractionStatus::Unsupported, 'none', 'No extractor supports this URL');
        }

        $minWordCount = (int) $this->config->get('scraping.min_word_count', 0);
        $results = [];

        foreach ($chain as $name) {
            $result = $this->driver($name)->extract($url);
            $results[] = $result;

            if ($result->isOk() && $result->wordCount >= $minWordCount) {
                return $result;
            }
        }

        return $this->bestOf($results);
    }

    /**
     * The ordered, deduplicated list of driver names whose `supports()`
     * accepts the URL: the domain override (or the default) followed by
     * the configured fallbacks.
     *
     * @return list<string>
     */
    private function supportedChain(string $url): array
    {
        $names = array_values(array_unique([
            $this->driverForDomain($url) ?? $this->getDefaultDriver(),
            ...(array) $this->config->get('scraping.fallbacks', []),
        ]));

        return array_values(array_filter(
            $names,
            fn (string $name): bool => $this->driver($name)->supports($url),
        ));
    }

    /**
     * The driver name overridden for this URL's host in
     * `config('scraping.domains')`, matching subdomains too, or null when
     * nothing matches and the default driver should be used.
     */
    private function driverForDomain(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = self::stripWww(strtolower($host));

        foreach ((array) $this->config->get('scraping.domains', []) as $domain => $driver) {
            $domain = self::stripWww(strtolower((string) $domain));

            if ($host === $domain || str_ends_with($host, ".{$domain}")) {
                return $driver;
            }
        }

        return null;
    }

    private static function stripWww(string $host): string
    {
        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    /**
     * The ok result with the highest word count, or the first result when
     * none of them is ok.
     *
     * @param  list<ExtractionResult>  $results
     */
    private function bestOf(array $results): ExtractionResult
    {
        $best = null;

        foreach ($results as $result) {
            if (! $result->isOk()) {
                continue;
            }

            if ($best === null || $result->wordCount > $best->wordCount) {
                $best = $result;
            }
        }

        return $best ?? $results[0];
    }
}
