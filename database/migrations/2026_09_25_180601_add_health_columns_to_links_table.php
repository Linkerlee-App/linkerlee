<?php

use App\Enums\HealthStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link-health bookkeeping: whether the last check found the page still
     * there, when it was last and will next be checked, and how many checks
     * in a row have failed.
     *
     * `health_status` is nullable rather than defaulting to a case of
     * {@see HealthStatus}, so a link that has never been checked
     * is distinguishable from one that has. `redirect_url` holds the final
     * URL when a check finds the link permanently redirected.
     *
     * Existing links are backfilled with a `next_check_at` spread across the
     * next week, picked at random, so the health check's first run does not
     * try to check every link in the account at once.
     */
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->string('health_status', 20)->nullable();
            $table->timestampTz('last_checked_at')->nullable();
            $table->timestampTz('next_check_at')->nullable()->index();
            $table->unsignedSmallInteger('check_interval_days')->default(7);
            $table->unsignedTinyInteger('consecutive_failures')->default(0);
            $table->timestampTz('content_changed_at')->nullable();
            $table->text('redirect_url')->nullable();
        });

        DB::statement("update links set next_check_at = now() + (random() * interval '7 days') where next_check_at is null");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->dropIndex(['next_check_at']);
            $table->dropColumn([
                'health_status',
                'last_checked_at',
                'next_check_at',
                'check_interval_days',
                'consecutive_failures',
                'content_changed_at',
                'redirect_url',
            ]);
        });
    }
};
