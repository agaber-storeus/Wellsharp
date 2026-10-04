<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table): void {
            if (! Schema::hasColumn('exam_schedules', 'start_time')) {
                $table->time('start_time')->nullable()->after('start_date');
            }
            if (! Schema::hasColumn('exam_schedules', 'end_time')) {
                $table->time('end_time')->nullable()->after('end_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table): void {
            if (Schema::hasColumn('exam_schedules', 'start_time')) {
                $table->dropColumn('start_time');
            }
            if (Schema::hasColumn('exam_schedules', 'end_time')) {
                $table->dropColumn('end_time');
            }
        });
    }
};
