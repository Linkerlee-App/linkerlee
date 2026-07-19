<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->string('favicon_url', 2048)->nullable()->after('description');
            $table->string('preview_image_url', 2048)->nullable()->after('favicon_url');
            $table->mediumText('page_text')->nullable()->after('preview_image_url');
            $table->timestamp('metadata_fetched_at')->nullable()->after('page_text');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('links', function (Blueprint $table) {
                $table->fullText(['title', 'description', 'page_text'], 'links_content_fulltext');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('links', function (Blueprint $table) {
                $table->dropFullText('links_content_fulltext');
            });
        }

        Schema::table('links', function (Blueprint $table) {
            $table->dropColumn(['favicon_url', 'preview_image_url', 'page_text', 'metadata_fetched_at']);
        });
    }
};
