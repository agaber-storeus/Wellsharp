<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table): void {
            $table->string('stack_offered', 64)->nullable()->after('class_id');
            $table->string('supplement_offered', 64)->nullable()->after('stack_offered');
        });
    }

    public function down(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table): void {
            $table->dropColumn(['stack_offered', 'supplement_offered']);
        });
    }
};
