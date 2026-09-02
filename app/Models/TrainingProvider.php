<?php

namespace App\Models;

use App\Enums\ProviderStatus;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingProvider extends Model
{
    use HasFactory, HasPublicUlid;

    protected $fillable = ['provider_number', 'name', 'email', 'phone', 'address', 'latitude', 'longitude', 'status'];

    protected function casts(): array
    {
        return ['status' => ProviderStatus::class, 'archived_at' => 'datetime', 'latitude' => 'float', 'longitude' => 'float'];
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(TrainingClass::class);
    }

    public function examSchedules(): HasMany
    {
        return $this->hasMany(ExamSchedule::class, 'training_provider_id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'training_provider_id');
    }

    public function isActive(): bool
    {
        return $this->status === ProviderStatus::Active && is_null($this->archived_at);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
