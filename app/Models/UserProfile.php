<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
