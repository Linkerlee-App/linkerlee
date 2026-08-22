<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Filtering by source is always scoped to one user, and `source` on its own
     * holds five values across the whole table — far too few to be worth an
     * index. The composite is what the listing query can actually use.
     */
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->index(['user_id', 'source'], 'links_user_id_source_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->dropIndex('links_user_id_source_index');
        });
    }
};
