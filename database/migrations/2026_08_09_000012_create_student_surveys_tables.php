<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_surveys', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('student_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('exam_schedule_id')->constrained('exam_schedules')->cascadeOnDelete();
            $table->string('status', 24)->default('started')->index();
            $table->timestampTz('contact_confirmed_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['student_user_id', 'exam_schedule_id']);
        });

        Schema::create('student_survey_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_survey_id')->constrained('student_surveys')->cascadeOnDelete();
            $table->string('question_key', 100);
            $table->text('answer')->nullable();
            $table->timestampsTz();
            $table->unique(['student_survey_id', 'question_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_survey_answers');
        Schema::dropIfExists('student_surveys');
    }
};
