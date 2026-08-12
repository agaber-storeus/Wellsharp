<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $seen = [];
        DB::table('questions')
            ->select(['id', 'course_id', 'question_text'])
            ->orderBy('id')
            ->chunkById(500, function ($questions) use (&$seen): void {
                foreach ($questions as $question) {
                    $hash = hash('sha256', mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $question->question_text))));
                    $key = $question->course_id.':'.$hash;
                    if (isset($seen[$key])) {
                        throw new RuntimeException("Duplicate question text exists for course {$question->course_id}; resolve it before applying this migration.");
                    }
                    $seen[$key] = true;
                }
            });

        Schema::table('questions', function (Blueprint $table): void {
            $table->string('question_text_hash', 64)->nullable()->after('question_text');
        });

        DB::table('questions')
            ->select(['id', 'question_text'])
            ->orderBy('id')
            ->chunkById(500, function ($questions): void {
                foreach ($questions as $question) {
                    $hash = hash('sha256', mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $question->question_text))));
                    DB::table('questions')->where('id', $question->id)->update(['question_text_hash' => $hash]);
                }
            });

        Schema::table('questions', function (Blueprint $table): void {
            $table->unique(['course_id', 'question_text_hash'], 'questions_course_question_text_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->dropUnique('questions_course_question_text_hash_unique');
            $table->dropColumn('question_text_hash');
        });
    }
};
