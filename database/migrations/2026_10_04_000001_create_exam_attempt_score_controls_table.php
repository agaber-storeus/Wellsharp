<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempt_score_controls', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_attempt_question_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 32)->index();
            $table->decimal('numeric_value', 8, 2)->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->json('before_state');
            $table->json('after_state');
            $table->text('reason');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('reverted_at')->nullable();
            $table->foreignId('reverted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('revert_reason')->nullable();
            $table->timestampsTz();
            $table->index(['exam_attempt_id', 'type', 'reverted_at'], 'attempt_score_controls_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempt_score_controls');
    }
};
