<?php

namespace App\Enrichment\Providers;

use App\Enrichment\Contracts\EmbeddingProvider;
use App\Enrichment\EnrichmentProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Embeds content chunk text with a local Ollama server's `/api/embed`
 * endpoint (the `nomic-embed-text` model by default).
 */
final class OllamaEmbeddingProvider implements EmbeddingProvider
{
    private readonly string $model;

    public function __construct(?string $model = null)
    {
        $this->model = $model ?? (string) config('enrichment.embedding.ollama.model');
    }

    public function model(): string
    {
        return $this->model;
    }

    public function dimensions(): int
    {
        return (int) config('enrichment.embedding.ollama.dimensions');
    }

    public function withModel(string $model): static
    {
        return new self($model);
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embed(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $url = rtrim((string) config('enrichment.embedding.ollama.url'), '/');

        try {
            $response = Http::timeout((int) config('enrichment.embedding.ollama.timeout', 60))
                ->post("{$url}/api/embed", [
                    'model' => $this->model,
                    'input' => $texts,
                ]);
        } catch (ConnectionException $exception) {
            throw new EnrichmentProviderException("Connection error while embedding with Ollama: {$exception->getMessage()}", previous: $exception);
        }

        if ($response->failed()) {
            throw new EnrichmentProviderException("Ollama embedding request failed with HTTP {$response->status()}: {$response->body()}");
        }

        $embeddings = $response->json('embeddings');

        if (! is_array($embeddings) || count($embeddings) !== count($texts)) {
            throw new EnrichmentProviderException('Ollama embedding response did not return one vector per input text.');
        }

        return array_map(function (mixed $vector): array {
            if (! is_array($vector)) {
                throw new EnrichmentProviderException('Ollama embedding response contained a malformed vector.');
            }

            return array_values(array_map(static fn (mixed $component): float => (float) $component, $vector));
        }, array_values($embeddings));
    }
}
