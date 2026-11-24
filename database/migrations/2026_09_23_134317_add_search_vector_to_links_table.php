<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A stored tsvector over the searchable text, kept current by Postgres.
     *
     * Each column is coalesced before concatenation: `description` and
     * `page_text` are often NULL, and a NULL anywhere in the expression would
     * make the whole vector NULL and the link unfindable. The `simple` config
     * does no stemming, so a bookmark in any language is indexed word for word.
     *
     * Only the first 100,000 characters are indexed. A tsvector is capped at
     * 1 MB, and because the column is generated, overflowing it would reject
     * the whole row instead of just leaving the tail unsearchable.
     *
     * There is deliberately no GIN index: search ORs this column with ILIKE
     * matches and is always scoped to one user, so the planner takes the
     * `user_id` index and a GIN index would only add write cost.
     */
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->rawColumn(
                'search_vector',
                "tsvector generated always as (to_tsvector('simple', left(coalesce(title, '') || ' ' || coalesce(description, '') || ' ' || coalesce(page_text, ''), 100000))) stored",
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->dropColumn('search_vector');
        });
    }
};
