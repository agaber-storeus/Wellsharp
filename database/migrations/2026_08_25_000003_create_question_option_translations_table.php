<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_option_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_option_id')->constrained('question_options')->cascadeOnDelete();
            $table->foreignId('translation_language_id')->constrained('translation_languages')->restrictOnDelete();
            $table->string('source_hash', 64);
            $table->text('translated_text');
            $table->string('provider', 32);
            $table->timestampTz('translated_at');
            $table->timestampsTz();
            $table->unique(['question_option_id', 'translation_language_id'], 'question_option_translations_option_language_unique');
            $table->index(['translation_language_id', 'source_hash'], 'question_option_translations_language_hash_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_option_translations');
    }
};
