<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_languages', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32);
            $table->string('code', 16);
            $table->string('name', 120);
            $table->string('native_name', 120)->nullable();
            $table->string('direction', 3)->default('ltr');
            $table->boolean('is_enabled')->default(false)->index();
            $table->json('provider_metadata')->nullable();
            $table->timestampTz('last_synced_at')->nullable();
            $table->timestampsTz();
            $table->unique(['provider', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_languages');
    }
};
