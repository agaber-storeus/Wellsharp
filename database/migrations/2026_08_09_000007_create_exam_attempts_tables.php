<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('exam_id')->constrained('exams')->restrictOnDelete();
            $table->foreignId('exam_schedule_id')->constrained('exam_schedules')->cascadeOnDelete();
            $table->foreignId('student_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->string('status', 24)->default('in_progress')->index();
            $table->timestampTz('started_at');
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampsTz();
            $table->unique(['exam_schedule_id', 'student_user_id', 'attempt_number'], 'exam_attempts_schedule_student_attempt_unique');
            $table->index(['student_user_id', 'status']);
        });

        Schema::create('exam_attempt_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->restrictOnDelete();
            $table->unsignedInteger('display_order');
            $table->decimal('points', 8, 2)->nullable();
            $table->timestampsTz();
            $table->unique(['exam_attempt_id', 'question_id']);
            $table->unique(['exam_attempt_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempt_questions');
        Schema::dropIfExists('exam_attempts');
    }
};
