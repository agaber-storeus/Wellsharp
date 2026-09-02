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
            $table->foreignId('training_provider_id')->nullable()->after('training_class_id')->constrained('training_providers')->nullOnDelete();
            $table->index(['training_provider_id', 'start_date']);
        });

        // Preserve the most specific existing assignment first. Legacy schedules
        // inherit from their class, then from the course only when no class value
        // exists. This keeps the migration lossless before the course column is
        // removed.
        DB::table('exam_schedules')->orderBy('id')->chunkById(100, function ($schedules): void {
            foreach ($schedules as $schedule) {
                $providerId = DB::table('classes')->where('id', $schedule->training_class_id)->value('training_provider_id');
                if ($providerId === null) {
                    $providerId = DB::table('exams')
                        ->join('courses', 'courses.id', '=', 'exams.course_id')
                        ->where('exams.id', $schedule->exam_id)
                        ->value('courses.training_provider_id');
                }
                if ($providerId !== null) {
                    DB::table('exam_schedules')->where('id', $schedule->id)->update(['training_provider_id' => $providerId]);
                }
            }
        });

        Schema::table('courses', function (Blueprint $table): void {
            $table->dropForeign(['training_provider_id']);
            $table->dropColumn('training_provider_id');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->foreignId('training_provider_id')->nullable()->constrained('training_providers')->nullOnDelete();
        });

        Schema::table('exam_schedules', function (Blueprint $table): void {
            $table->dropForeign(['training_provider_id']);
            $table->dropIndex(['training_provider_id', 'start_date']);
            $table->dropColumn('training_provider_id');
        });
    }
};
