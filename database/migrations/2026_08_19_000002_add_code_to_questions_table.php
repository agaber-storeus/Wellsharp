<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable + unique: safe to add to a populated table. NULLs never collide
     * under a unique index, so existing questions keep code = null until the
     * backfill command (wellsharp:backfill-identifiers) assigns one.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->string('code', 5)->nullable()->unique()->after('public_id');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->dropColumn('code');
        });
    }
};
