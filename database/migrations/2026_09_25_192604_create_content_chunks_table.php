<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per chunk of a link snapshot's `content_text`, embedded for
     * semantic search. Rebuilt from the snapshot alone: deleting a
     * snapshot's chunks and re-chunking never needs a refetch.
     *
     * No vector index is created on `embedding`, per the project's global
     * constraint against search indexes in this PR.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('content_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('link_snapshot_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('ordinal');
            $table->text('text');
            $table->unsignedInteger('token_count');
            $table->vector('embedding', 768)->nullable();
            $table->string('embedding_model')->nullable();
            $table->timestampsTz();

            $table->unique(['link_snapshot_id', 'ordinal']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * Does not drop the `vector` extension: other tables or a later
     * migration may still depend on it.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_chunks');
    }
};
