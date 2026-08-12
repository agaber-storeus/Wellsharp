<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table): void {
            $table->timestampTz('actual_started_at')->nullable()->after('starts_at');
            $table->timestampTz('actual_ended_at')->nullable()->after('ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table): void {
            $table->dropColumn(['actual_started_at', 'actual_ended_at']);
        });
    }
};
