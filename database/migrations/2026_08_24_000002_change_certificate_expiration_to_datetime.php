<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table): void {
            // MySQL TIMESTAMP cannot represent certificate expirations beyond
            // 2038. DATETIME preserves the existing date/time values and
            // supports the full validity period configured by the application.
            $table->dateTime('expires_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table): void {
            $table->timestampTz('expires_at')->nullable()->change();
        });
    }
};
