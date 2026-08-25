<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table): void {
            $table->string('question_selection_mode', 24)->nullable()->default('manual')->after('question_order_mode');
            $table->unsignedInteger('question_count')->nullable()->after('question_selection_mode');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table): void {
            $table->dropColumn(['question_selection_mode', 'question_count']);
        });
    }
};
