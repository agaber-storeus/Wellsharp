<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingProviderLocation extends Model
{
    use HasFactory;

    protected $fillable = ['location', 'latitude', 'longitude', 'is_active'];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'is_active' => 'boolean'];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(TrainingProvider::class, 'training_provider_id');
    }

    public function examSchedules(): HasMany
    {
        return $this->hasMany(ExamSchedule::class, 'training_provider_location_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(TrainingClass::class, 'training_provider_location_id');
    }
}
