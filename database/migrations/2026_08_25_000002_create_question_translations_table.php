<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->foreignId('translation_language_id')->constrained('translation_languages')->restrictOnDelete();
            $table->string('source_hash', 64);
            $table->text('translated_text');
            $table->string('provider', 32);
            $table->timestampTz('translated_at');
            $table->timestampsTz();
            $table->unique(['question_id', 'translation_language_id'], 'question_translations_question_language_unique');
            $table->index(['translation_language_id', 'source_hash'], 'question_translations_language_hash_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_translations');
    }
};
