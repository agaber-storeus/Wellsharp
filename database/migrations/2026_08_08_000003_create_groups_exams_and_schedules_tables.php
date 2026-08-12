<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->unsignedTinyInteger('age')->nullable()->after('phone');
            $table->string('gender', 64)->nullable()->after('age');
        });

        Schema::create('student_groups', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 180);
            $table->string('code', 64)->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('group_memberships', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('group_id')->constrained('student_groups')->restrictOnDelete();
            $table->foreignId('student_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('active')->index();
            $table->timestampTz('joined_at');
            $table->timestampTz('removed_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['group_id', 'student_user_id', 'status']);
            $table->index(['student_user_id', 'status']);
        });

        Schema::create('exams', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->string('name', 180);
            $table->string('code', 64)->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('question_order_mode', 24)->default('static');
            $table->string('status', 24)->default('draft')->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['course_id', 'status']);
        });

        Schema::create('exam_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->restrictOnDelete();
            $table->unsignedInteger('display_order');
            $table->decimal('points', 8, 2)->nullable();
            $table->timestampsTz();
            $table->unique(['exam_id', 'question_id']);
            $table->unique(['exam_id', 'display_order']);
        });

        Schema::create('exam_group_assignments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('exam_id')->constrained('exams')->restrictOnDelete();
            $table->foreignId('group_id')->constrained('student_groups')->restrictOnDelete();
            $table->string('status', 24)->default('active')->index();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('assigned_at');
            $table->timestampTz('removed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['exam_id', 'group_id', 'status']);
        });

        Schema::create('exam_schedules', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('exam_id')->constrained('exams')->restrictOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('student_groups')->nullOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('status', 24)->default('scheduled')->index();
            $table->string('timezone', 64)->default('UTC');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['exam_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_schedules');
        Schema::dropIfExists('exam_group_assignments');
        Schema::dropIfExists('exam_questions');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('group_memberships');
        Schema::dropIfExists('student_groups');
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->dropColumn(['age', 'gender']);
        });
    }
};
