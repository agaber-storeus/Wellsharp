<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 64)->unique();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->foreignId('training_provider_id')->nullable()->constrained('training_providers')->nullOnDelete();
            $table->foreignId('course_level_id')->nullable()->constrained('course_levels')->restrictOnDelete();
            $table->string('status', 24)->default('active')->index();
            $table->timestampTz('archived_at')->nullable()->index();
            $table->timestampsTz();
        });

        foreach (['stack', 'supplement', 'language'] as $pivot) {
            $reference = match ($pivot) {
                'stack' => 'stacks',
                'supplement' => 'supplements',
                'language' => 'languages',
            };
            $column = "{$pivot}_id";

            Schema::create("course_{$pivot}", function (Blueprint $table) use ($reference, $column): void {
                $table->foreignId('course_id')->constrained()->cascadeOnDelete();
                $table->foreignId($column)->constrained($reference)->restrictOnDelete();
                $table->primary(['course_id', $column]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('course_language');
        Schema::dropIfExists('course_supplement');
        Schema::dropIfExists('course_stack');
        Schema::dropIfExists('courses');
    }
};
