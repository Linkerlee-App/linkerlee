<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Content-extraction state for each link. Existing links start as
     * `pending`, which is exactly what they are until the backfill runs.
     *
     * `etag` and `last_modified` are the validators from the last successful
     * fetch, kept for conditional requests on the next one.
     */
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->string('extraction_status', 20)->default('pending')->index();
            $table->text('extraction_error')->nullable();
            $table->timestampTz('extracted_at')->nullable();
            $table->foreignId('latest_snapshot_id')->nullable()->constrained('link_snapshots')->nullOnDelete();
            $table->string('etag')->nullable();
            $table->string('last_modified')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->dropConstrainedForeignId('latest_snapshot_id');
            $table->dropIndex(['extraction_status']);
            $table->dropColumn(['extraction_status', 'extraction_error', 'extracted_at', 'etag', 'last_modified']);
        });
    }
};
