<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Compare emails and inbox tokens case-insensitively, as MySQL's `_ci`
     * collation always did.
     *
     * Fortify lowercases the email on login, the Mailgun webhook lowercases the
     * inbox address, and the unique indexes must reject case variants. Postgres
     * compares `varchar` byte for byte, so without `citext` a stored
     * `Me@Example.com` could never sign in and no mixed-case token matched.
     */
    public function up(): void
    {
        Schema::ensureExtensionExists('citext');

        DB::statement('alter table users alter column email type citext');
        DB::statement('alter table users alter column inbox_token type citext');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('alter table users alter column email type varchar(255)');
        DB::statement('alter table users alter column inbox_token type varchar(32)');
    }
};
