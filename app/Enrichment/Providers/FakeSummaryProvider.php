<?php

namespace App\Enrichment\Providers;

use App\Enrichment\Contracts\SummaryProvider;

/**
 * The driver used everywhere in the test suite (`SUMMARY_DRIVER=fake` in
 * phpunit.xml), so no test ever calls the Anthropic API.
 */
final class FakeSummaryProvider implements SummaryProvider
{
    private const DEFAULT_MODEL = 'fake-summary';

    /**
     * Every call to {@see self::summarize()}, in call order.
     *
     * @var list<array{title: string, text: string}>
     */
    public static array $calls = [];

    public function __construct(private readonly string $model = self::DEFAULT_MODEL) {}

    public static function reset(): void
    {
        self::$calls = [];
    }

    public function summarize(string $title, string $text): string
    {
        self::$calls[] = ['title' => $title, 'text' => $text];

        return "Summary of {$title}";
    }

    public function model(): string
    {
        return $this->model;
    }

    public function withModel(string $model): static
    {
        return new self($model);
    }
}
