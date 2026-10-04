<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_provider_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_provider_id')->constrained('training_providers')->restrictOnDelete();
            $table->string('location');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
            $table->index(['training_provider_id', 'is_active']);
        });

        DB::table('training_providers')
            ->whereNotNull('address')
            ->where('address', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($providers): void {
                foreach ($providers as $provider) {
                    DB::table('training_provider_locations')->insert([
                        'training_provider_id' => $provider->id,
                        'location' => $provider->address,
                        'latitude' => $provider->latitude ?? null,
                        'longitude' => $provider->longitude ?? null,
                        'is_active' => true,
                        'created_at' => $provider->created_at,
                        'updated_at' => $provider->updated_at,
                    ]);
                }
            });

        Schema::table('exam_schedules', function (Blueprint $table): void {
            $table->foreignId('training_provider_location_id')->nullable()->after('training_provider_id')->constrained('training_provider_locations')->restrictOnDelete();
        });
        Schema::table('classes', function (Blueprint $table): void {
            $table->foreignId('training_provider_location_id')->nullable()->after('training_provider_id')->constrained('training_provider_locations')->restrictOnDelete();
        });

        $locations = DB::table('training_provider_locations')->pluck('id', 'training_provider_id');
        foreach ($locations as $providerId => $locationId) {
            DB::table('exam_schedules')->where('training_provider_id', $providerId)->update(['training_provider_location_id' => $locationId]);
            DB::table('classes')->where('training_provider_id', $providerId)->update(['training_provider_location_id' => $locationId]);
        }
    }

    public function down(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('training_provider_location_id');
        });
        Schema::table('classes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('training_provider_location_id');
        });
        Schema::dropIfExists('training_provider_locations');
    }
};
