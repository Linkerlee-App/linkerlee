<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable with no backfill: links saved before this column existed have no
     * known capture source, and guessing one would put made-up history in the
     * database. Null reads as "unknown", not as a missing value.
     */
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->string('source', 20)->nullable()->after('metadata_fetched_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
