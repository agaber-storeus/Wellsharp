<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table): void {
            $table->unsignedTinyInteger('passing_score')->nullable()->after('description');
            $table->unsignedTinyInteger('retake_score')->nullable()->after('passing_score');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table): void {
            $table->dropColumn(['passing_score', 'retake_score']);
        });
    }
};
