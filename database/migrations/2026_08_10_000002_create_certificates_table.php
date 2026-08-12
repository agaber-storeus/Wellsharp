<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('certificate_number', 64)->unique();
            $table->foreignId('exam_attempt_id')->unique()->constrained('exam_attempts')->restrictOnDelete();
            $table->foreignId('student_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('exam_id')->constrained('exams')->restrictOnDelete();
            $table->foreignId('exam_schedule_id')->constrained('exam_schedules')->restrictOnDelete();
            $table->foreignId('training_class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('training_provider_id')->nullable()->constrained('training_providers')->nullOnDelete();
            $table->foreignId('instructor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('student_name', 220);
            $table->string('student_email')->nullable();
            $table->string('student_wellsharp_id', 64);
            $table->string('exam_name', 180);
            $table->string('exam_code', 64)->nullable();
            $table->string('subject_name', 180);
            $table->string('class_number', 64)->nullable();
            $table->string('group_name', 180)->nullable();
            $table->string('provider_name', 180)->nullable();
            $table->string('instructor_name', 220)->nullable();
            $table->decimal('score', 5, 2);
            $table->unsignedTinyInteger('passing_score');
            $table->timestampTz('issued_at');
            $table->string('status', 24)->default('issued')->index();
            $table->timestampTz('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestampsTz();
            $table->index(['student_user_id', 'status']);
            $table->index(['training_provider_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
