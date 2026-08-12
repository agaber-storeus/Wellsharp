<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->string('unit', 160)->nullable()->after('difficulty');
            $table->string('lesson', 160)->nullable()->after('unit');
            $table->decimal('default_marks', 8, 2)->default(1)->after('lesson');
            $table->unsignedInteger('default_time_seconds')->default(60)->after('default_marks');
            $table->text('solution_text')->nullable()->after('correct_answer_boolean');
            $table->string('solution_video_url', 2048)->nullable()->after('solution_text');
            $table->boolean('attachment_enabled')->default(false)->after('solution_video_url');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->dropColumn(['unit', 'lesson', 'default_marks', 'default_time_seconds', 'solution_text', 'solution_video_url', 'attachment_enabled']);
        });
    }
};
