<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per distinct version of a link's extracted article text.
     *
     * `content_text` is the source of truth that summaries, chunks and
     * embeddings are rebuilt from, so it is never truncated. `metadata`
     * holds the response validators (etag, last_modified) and the extractor
     * that produced the text.
     */
    public function up(): void
    {
        Schema::create('link_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained()->cascadeOnDelete();
            $table->string('extractor', 50);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('final_url')->nullable();
            $table->text('title')->nullable();
            $table->string('author')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->longText('content_text');
            $table->char('content_hash', 64);
            $table->unsignedInteger('word_count');
            $table->text('summary')->nullable();
            $table->string('summary_model')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('fetched_at');
            $table->timestampsTz();

            $table->index(['link_id', 'fetched_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('link_snapshots');
    }
};
