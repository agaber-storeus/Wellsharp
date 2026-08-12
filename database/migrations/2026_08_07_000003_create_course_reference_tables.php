<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['course_levels', 'stacks', 'supplements', 'languages'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->string('name', 100);
                $table->string('slug', 120)->unique();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->timestampsTz();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
        Schema::dropIfExists('supplements');
        Schema::dropIfExists('stacks');
        Schema::dropIfExists('course_levels');
    }
};
