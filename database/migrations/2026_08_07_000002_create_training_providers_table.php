<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_providers', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('provider_number', 64)->unique();
            $table->string('name', 160);
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('address')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->timestampTz('archived_at')->nullable()->index();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_providers');
    }
};
