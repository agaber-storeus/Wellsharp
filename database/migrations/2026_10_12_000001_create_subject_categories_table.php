<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 180);
            $table->string('code', 64)->unique();
            $table->timestamps();
        });

        Schema::table('courses', function (Blueprint $table): void {
            $table->foreignId('subject_category_id')->nullable()->index()->constrained('subject_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('subject_category_id');
        });
        Schema::dropIfExists('subject_categories');
    }
};
