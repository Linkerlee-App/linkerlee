<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('inbox_token', 32)->nullable()->unique()->after('email');
        });

        DB::table('users')->whereNull('inbox_token')->orderBy('id')->each(function ($user): void {
            DB::table('users')->where('id', $user->id)->update([
                'inbox_token' => Str::random(24),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['inbox_token']);
            $table->dropColumn('inbox_token');
        });
    }
};
