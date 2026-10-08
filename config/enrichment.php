<?php

use App\Enrichment\Providers\AnthropicSummaryProvider;
use App\Enrichment\Providers\FakeEmbeddingProvider;
use App\Enrichment\Providers\FakeSummaryProvider;
use App\Enrichment\Providers\NullSummaryProvider;
use App\Enrichment\Providers\OllamaEmbeddingProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Summaries
    |--------------------------------------------------------------------------
    |
    | Produces the 2-3 sentence summary stored on a link_snapshot. The
    | default, "none", turns summaries off so page text never leaves the
    | server; chunk 0 is then the title alone. "anthropic" sends page text to
    | Anthropic, so the privacy page must say so before it is enabled. "fake"
    | is forced in tests (SUMMARY_DRIVER=fake in phpunit.xml), so no test ever
    | calls the Anthropic API.
    |
    */

    'summary' => [
        'driver' => env('SUMMARY_DRIVER', 'none'),

        'drivers' => [
            'none' => NullSummaryProvider::class,
            'anthropic' => AnthropicSummaryProvider::class,
            'fake' => FakeSummaryProvider::class,
        ],

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('SUMMARY_MODEL', 'claude-haiku-4-5'),
            'max_input_chars' => 24000,
            'timeout' => (int) env('SUMMARY_TIMEOUT', 30),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Embeddings
    |--------------------------------------------------------------------------
    |
    | Produces the vectors stored on content_chunks.embedding. "fake" is
    | forced in tests (EMBEDDING_DRIVER=fake in phpunit.xml), so no test
    | ever calls a local Ollama server.
    |
    */

    'embedding' => [
        'driver' => env('EMBEDDING_DRIVER', 'ollama'),

        'drivers' => [
            'ollama' => OllamaEmbeddingProvider::class,
            'fake' => FakeEmbeddingProvider::class,
        ],

        'ollama' => [
            'url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),
            'model' => env('EMBEDDING_MODEL', 'nomic-embed-text'),
            'dimensions' => (int) env('EMBEDDING_DIMENSIONS', 768),
            // nomic-embed-text expects task-specific prefixes on its input
            // text. Chunk text is a document being indexed, so it is
            // prepended with this before it is embedded.
            'document_prefix' => 'search_document: ',
            'timeout' => (int) env('OLLAMA_TIMEOUT', 60),
        ],

        // Chunks sent per embedding request. EmbedChunksJob embeds one batch
        // per run and re-dispatches itself, so this also bounds a job's run.
        // A loop is capped at ceil(max_chunks / batch_size) + 1 runs.
        'batch_size' => (int) env('EMBEDDING_BATCH_SIZE', 32),
    ],

    /*
    |--------------------------------------------------------------------------
    | Chunking
    |--------------------------------------------------------------------------
    |
    | How a snapshot's content_text is split into content_chunks before
    | embedding. chars_per_token is a rough estimate used to convert a token
    | budget into a character budget without a real tokenizer. max_chunks caps
    | the chunks per snapshot (chunk 0 included): the rest of an enormous page
    | is left out of search, but its full text stays on the snapshot.
    |
    */

    'chunking' => [
        'size_tokens' => 800,
        'overlap_tokens' => 100,
        'chars_per_token' => 4,
        'max_chunks' => 500,
    ],

];
