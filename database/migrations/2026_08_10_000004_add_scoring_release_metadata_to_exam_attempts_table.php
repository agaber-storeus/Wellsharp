<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table): void {
            $table->timestampTz('scored_at')->nullable()->after('passed');
            $table->timestampTz('released_at')->nullable()->after('scored_at');
            $table->foreignId('released_by_user_id')->nullable()->after('released_at')->constrained('users')->nullOnDelete();
            $table->index(['exam_schedule_id', 'released_at']);
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table): void {
            $table->dropForeign(['released_by_user_id']);
            $table->dropIndex(['exam_schedule_id', 'released_at']);
            $table->dropColumn(['scored_at', 'released_at', 'released_by_user_id']);
        });
    }
};
