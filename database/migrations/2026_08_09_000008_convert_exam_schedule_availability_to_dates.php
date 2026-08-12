<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('exam_schedules', 'start_date')) {
            Schema::table('exam_schedules', function (Blueprint $table): void {
                $table->date('start_date')->nullable()->after('group_id');
                $table->date('end_date')->nullable()->after('start_date');
            });
        }

        DB::statement('UPDATE exam_schedules SET start_date = date(starts_at), end_date = COALESCE(date(ends_at), date(starts_at))');

        Schema::table('exam_schedules', function (Blueprint $table): void {
            $table->index('exam_id', 'exam_schedules_exam_id_index');
            $table->dropIndex(['exam_id', 'starts_at']);
            $table->dropColumn(['starts_at', 'ends_at', 'timezone']);
            $table->index(['exam_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table): void {
            $table->timestampTz('starts_at')->nullable()->after('group_id');
            $table->timestampTz('ends_at')->nullable()->after('starts_at');
            $table->string('timezone', 64)->default('UTC')->after('status');
        });

        DB::table('exam_schedules')->select(['id', 'start_date', 'end_date'])->get()->each(function (object $schedule): void {
            DB::table('exam_schedules')->where('id', $schedule->id)->update([
                'starts_at' => $schedule->start_date,
                'ends_at' => $schedule->end_date.' 23:59:59',
            ]);
        });

        Schema::table('exam_schedules', function (Blueprint $table): void {
            $table->dropIndex(['exam_id', 'start_date']);
            $table->index(['exam_id', 'starts_at']);
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};
