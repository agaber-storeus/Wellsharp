<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempt_questions', function (Blueprint $table): void {
            $table->text('translated_question_text')->nullable()->after('option_order');
            $table->json('translated_options')->nullable()->after('translated_question_text');
            $table->text('back_translated_answer')->nullable()->after('answer');
            $table->string('answer_translation_status', 16)->nullable()->after('back_translated_answer');
            $table->string('answer_translation_provider', 32)->nullable()->after('answer_translation_status');
            $table->timestampTz('answer_translated_at')->nullable()->after('answer_translation_provider');
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempt_questions', function (Blueprint $table): void {
            $table->dropColumn([
                'translated_question_text',
                'translated_options',
                'back_translated_answer',
                'answer_translation_status',
                'answer_translation_provider',
                'answer_translated_at',
            ]);
        });
    }
};
