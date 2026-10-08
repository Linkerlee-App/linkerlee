<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SummarizeSnapshotJob looks up an existing summary of the same text by
     * `content_hash` before calling the provider; without this index every
     * lookup is a sequential scan of link_snapshots.
     */
    public function up(): void
    {
        Schema::table('link_snapshots', function (Blueprint $table) {
            $table->index('content_hash');
        });
    }

    public function down(): void
    {
        Schema::table('link_snapshots', function (Blueprint $table) {
            $table->dropIndex(['content_hash']);
        });
    }
};
