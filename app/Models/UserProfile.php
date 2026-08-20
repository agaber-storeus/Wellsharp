<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'first_name', 'last_name', 'phone', 'birthday', 'address', 'country', 'state', 'city', 'postal_code', 'company', 'position', 'company_contact', 'employee_id', 'profile_photo_path', 'age', 'gender'];

    protected function casts(): array
    {
        return ['birthday' => 'date', 'age' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->last_name]))) ?: 'Unnamed user';
    }

    /**
     * Age is never accepted as its own input - it is always derived from the
     * date of birth so the two values can never drift apart.
     */
    public static function calculateAge(null|string|\DateTimeInterface $birthday): ?int
    {
        if (blank($birthday)) {
            return null;
        }

        return Carbon::parse($birthday)->age;
    }
}
