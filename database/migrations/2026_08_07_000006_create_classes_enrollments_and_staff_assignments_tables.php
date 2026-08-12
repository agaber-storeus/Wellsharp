<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('class_number', 64)->unique();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->foreignId('training_provider_id')->nullable()->constrained('training_providers')->nullOnDelete();
            $table->string('status', 24)->default('planned')->index();
            $table->timestampTz('starts_at')->nullable()->index();
            $table->timestampTz('ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });

        Schema::create('enrollments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('student_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('enrolled')->index();
            $table->timestampTz('enrolled_at');
            $table->timestampTz('withdrawn_at')->nullable();
            $table->timestampsTz();
            $table->unique(['class_id', 'student_user_id']);
            $table->index(['student_user_id', 'status']);
        });

        Schema::create('class_staff_assignments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('assignment_role', 24);
            $table->string('status', 24)->default('active')->index();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('assigned_at');
            $table->timestampTz('ended_at')->nullable();
            $table->timestampsTz();
            $table->index(['user_id', 'assignment_role', 'status']);
            $table->unique(['class_id', 'user_id', 'assignment_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_staff_assignments');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('classes');
    }
};
