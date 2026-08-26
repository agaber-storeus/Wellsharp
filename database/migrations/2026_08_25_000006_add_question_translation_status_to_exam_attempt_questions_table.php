<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempt_questions', function (Blueprint $table): void {
            $table->string('question_translation_status', 16)->nullable()->after('translated_options');
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempt_questions', function (Blueprint $table): void {
            $table->dropColumn('question_translation_status');
        });
    }
};
