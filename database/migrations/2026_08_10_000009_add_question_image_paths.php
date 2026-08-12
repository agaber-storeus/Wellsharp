<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->string('question_image_path', 512)->nullable()->after('question_text');
            $table->string('correct_answer_image_path', 512)->nullable()->after('correct_answer_text');
        });

        Schema::table('question_options', function (Blueprint $table): void {
            $table->string('image_path', 512)->nullable()->after('option_text');
        });
    }

    public function down(): void
    {
        Schema::table('question_options', function (Blueprint $table): void {
            $table->dropColumn('image_path');
        });

        Schema::table('questions', function (Blueprint $table): void {
            $table->dropColumn(['question_image_path', 'correct_answer_image_path']);
        });
    }
};
