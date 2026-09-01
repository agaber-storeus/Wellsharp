<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table): void {
            $table->string('start_mode', 24)->default('automatic')->after('status')->index();
        });

        DB::table('exam_schedules')->orderBy('id')->chunkById(100, function ($schedules): void {
            foreach ($schedules as $schedule) {
                $mode = DB::table('exams')->where('id', $schedule->exam_id)->value('start_mode');
                if ($mode) {
                    DB::table('exam_schedules')->where('id', $schedule->id)->update(['start_mode' => $mode]);
                }
            }
        });

        Schema::table('exams', function (Blueprint $table): void {
            $table->dropIndex(['start_mode']);
            $table->dropColumn('start_mode');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table): void {
            $table->string('start_mode', 24)->default('automatic')->after('status')->index();
        });

        DB::table('exam_schedules')->orderBy('id')->chunkById(100, function ($schedules): void {
            foreach ($schedules as $schedule) {
                DB::table('exams')->where('id', $schedule->exam_id)->update(['start_mode' => $schedule->start_mode]);
            }
        });

        Schema::table('exam_schedules', function (Blueprint $table): void {
            $table->dropIndex(['start_mode']);
            $table->dropColumn('start_mode');
        });
    }
};
